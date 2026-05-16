<?php
// Bestand: beheer/calculatie/pdf_factuur.php
// Versie: Crash-proof & Luxe Layout (Met perfecte ademruimte)
// Tenant-safe: calculatie + klant via k.tenant_id = c.tenant_id; regels op tenant_id; publiek id+token of beheer sessie.

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../includes/db.php')) {
    die('Fout: Kan db.php niet vinden.');
}
if (!file_exists('../includes/fpdf/fpdf.php')) {
    die('Fout: Kan fpdf.php niet vinden.');
}

require '../includes/db.php';
require '../includes/fpdf/fpdf.php';
require '../includes/tenant_instellingen_db.php';

function safe($str) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) ($str ?? ''));
}

if (!isset($_GET['id']) || $_GET['id'] === '' || $_GET['id'] === '0') {
    die('Geen ID opgegeven.');
}
$id = (int) $_GET['id'];
if ($id <= 0) {
    die('Geen ID opgegeven.');
}

$publicToken = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_GET['token']) : '';

if ($publicToken !== '') {
    $stmt = $pdo->prepare('
        SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.token = ? AND c.tenant_id IS NOT NULL
        LIMIT 1
    ');
    $stmt->execute([$id, $publicToken]);
} else {
    $sessionTenantId = current_tenant_id();
    if ($sessionTenantId <= 0) {
        die('Tenant context ontbreekt.');
    }
    $stmt = $pdo->prepare('
        SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
        LIMIT 1
    ');
    $stmt->execute([$id, $sessionTenantId]);
}

$rit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rit) {
    die('Rit niet gevonden.');
}

$tenantId = (int) ($rit['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    die('Rit niet gevonden.');
}

$tenantInst = tenant_instellingen_get($pdo, $tenantId);
$mijn_bedrijfsnaam = trim((string) ($tenantInst['bedrijfsnaam'] ?? 'BusAI'));
$mijn_adres = trim((string) ($tenantInst['adres'] ?? ''));
$mijn_postcode_plaats = trim((string) ($tenantInst['postcode'] ?? '') . ' ' . (string) ($tenantInst['plaats'] ?? ''));
$mijn_telefoon = trim((string) ($tenantInst['telefoon'] ?? ''));
$mijn_email = trim((string) ($tenantInst['email'] ?? ''));

if (!empty($rit['klant_id'])) {
    $kid = (int) $rit['klant_id'];
    $chk = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
    $chk->execute([$kid, $tenantId]);
    if (!$chk->fetchColumn()) {
        die('Rit niet gevonden.');
    }
}

$klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam'];

$stmtRegels = $pdo->prepare('SELECT * FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ? ORDER BY id ASC');
$stmtRegels->execute([$id, $tenantId]);
$regels = $stmtRegels->fetchAll(PDO::FETCH_ASSOC);

$startAdres = 'Onbekend';
$eindAdres = 'Onbekend';

foreach ($regels as $r) {
    if ($r['type'] === 't_vertrek_klant') {
        $startAdres = $r['adres'] ?? 'Onbekend';
    }
    if ($r['type'] === 't_aankomst_best') {
        $eindAdres = $r['adres'] ?? 'Onbekend';
    }
}

$startDisplay = (strlen($startAdres) > 35) ? substr($startAdres, 0, 32) . '...' : $startAdres;
$eindDisplay = (strlen($eindAdres) > 35) ? substr($eindAdres, 0, 32) . '...' : $eindAdres;

$mijn_iban = trim((string) ($tenantInst['iban'] ?? ''));
$mijn_kvk = trim((string) ($tenantInst['kvk_nummer'] ?? ''));
$mijn_btw = trim((string) ($tenantInst['btw_nummer'] ?? ''));
$mijn_logo = trim((string) ($tenantInst['logo_pad'] ?? ''));
$betaaltermijn = 14;

$factuurDatum = date('d-m-Y');
$vervalDatum = date('d-m-Y', strtotime('+' . $betaaltermijn . ' days'));
$factuurNr = date('Y') . '-' . sprintf('%04d', $rit['id']);

class PDF extends FPDF {
    public function Header() {
        global $mijn_bedrijfsnaam, $mijn_logo;
        $blauw = [0, 51, 102];
        $oranje = [255, 94, 20];

        $logoPad = $mijn_logo !== '' ? ('../' . ltrim($mijn_logo, '/')) : '../images/berkhout_logo.png';
        $logoGeldig = (file_exists($logoPad) && filesize($logoPad) > 0 && @getimagesize($logoPad));

        if ($logoGeldig) {
            $this->Image($logoPad, 10, 8, 45);
        } else {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(50, 10, safe($mijn_bedrijfsnaam), 1, 0, 'C');
        }

        $this->SetXY(120, 10);
        $this->SetFont('Arial', 'B', 24);
        $this->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
        $this->Cell(80, 10, 'FACTUUR', 0, 1, 'R');
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0);

        $this->Ln(15);
        $this->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, 35, 200, 35);
        $this->Ln(10);
    }

    public function Footer() {
        global $mijn_bedrijfsnaam, $mijn_adres, $mijn_postcode_plaats, $mijn_telefoon, $mijn_email, $mijn_kvk, $mijn_btw, $mijn_iban;
        $sep = '  |  ';

        // Rij 1: bedrijfsnaam | adres, postcode+stad | KvK
        $r1 = [];
        if ($mijn_bedrijfsnaam !== '')  $r1[] = $mijn_bedrijfsnaam;
        if ($mijn_adres !== '' && $mijn_postcode_plaats !== '') $r1[] = $mijn_adres . ', ' . $mijn_postcode_plaats;
        elseif ($mijn_adres !== '')     $r1[] = $mijn_adres;
        if ($mijn_kvk !== '')           $r1[] = 'KvK ' . $mijn_kvk;

        // Rij 2: telefoon | e-mail | IBAN | BTW
        $r2 = [];
        if ($mijn_telefoon !== '') $r2[] = 'T ' . $mijn_telefoon;
        if ($mijn_email !== '')    $r2[] = $mijn_email;
        if ($mijn_iban !== '')     $r2[] = 'IBAN ' . $mijn_iban;
        if ($mijn_btw !== '')      $r2[] = 'BTW ' . $mijn_btw;

        $this->SetY(-27);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(10, 270, 200, 270);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, safe($r1 !== [] ? implode($sep, $r1) : ''), 0, 1, 'C');
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 5, safe($r2 !== [] ? implode($sep, $r2) : ''), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$blauw = [0, 51, 102];
$oranje = [255, 94, 20];

$yStart = $pdf->GetY();

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 5, safe($klantNaam), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 5, safe('T.a.v. Administratie'), 0, 1);
$pdf->Cell(0, 5, safe($rit['adres']), 0, 1);
$pdf->Cell(0, 5, safe(($rit['postcode'] ?? '') . ' ' . ($rit['plaats'] ?? '')), 0, 1);

$pdf->SetXY(130, $yStart);
$pdf->SetFillColor(245, 248, 250);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Rect(125, $yStart - 2, 75, 25, 'F');

$pdf->SetXY(130, $yStart);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 100, 100);

function metaRow($pdf, $label, $waarde, $isAccent = false) {
    global $blauw, $oranje;
    $pdf->SetX(130);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(35, 6, $label, 0, 0);

    $pdf->SetFont('Arial', 'B', 10);
    if ($isAccent === 'blauw') {
        $pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
    } elseif ($isAccent === 'oranje') {
        $pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
    } else {
        $pdf->SetTextColor(0);
    }
    $pdf->Cell(35, 6, $waarde, 0, 1, 'R');
}

metaRow($pdf, 'Factuurnummer:', $factuurNr, 'blauw');
metaRow($pdf, 'Factuurdatum:', $factuurDatum);
metaRow($pdf, 'Vervaldatum:', $vervalDatum);
$pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'CONCEPT FACTUUR', 0, 1, 'R');

$pdf->Ln(15);
$pdf->SetTextColor(0);

$w_datum = 25;
$w_omschrijving = 120;
$w_totaal = 45;

$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell($w_datum, 8, '  Datum', 0, 0, 'L', true);
$pdf->Cell($w_omschrijving, 8, 'Omschrijving Rit', 0, 0, 'L', true);
$pdf->Cell($w_totaal, 8, 'Bedrag  ', 0, 1, 'R', true);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 10);

// DB-kolom `prijs` is LEIDEND inclusief BTW.
$prijsIncl = (float) ($rit['prijs'] ?? 0);
$btwPerc = 9;
$prijsExcl = round($prijsIncl / (1 + $btwPerc / 100), 2);
$btwBedrag = round($prijsIncl - $prijsExcl, 2);
$totaal = $prijsIncl;
$totaalAfgerond = round($totaal / 5) * 5;

$currentY = $pdf->GetY();

$pdf->SetXY(10, $currentY);
$pdf->Cell($w_datum, 8, '  ' . date('d-m-Y', strtotime((string) $rit['rit_datum'])), 'B', 0, 'L');
$pdf->Cell($w_omschrijving, 8, safe('Vervoer ' . $startDisplay . ' - ' . $eindDisplay . ' (Rit: ' . $rit['id'] . ')'), 'B', 0, 'L');
$pdf->Cell($w_totaal, 8, chr(128) . ' ' . number_format($prijsExcl, 2, ',', '.') . '  ', 'B', 1, 'R');

$pdf->Ln(15);
$xTotalen = 10 + $w_datum + 40;
$w_label = 80;
$w_bedrag = 45;

$pdf->SetX($xTotalen);
$pdf->Cell($w_label, 6, 'Subtotaal (Excl. BTW)', 0, 0, 'R');
$pdf->Cell($w_bedrag, 6, chr(128) . ' ' . number_format($prijsExcl, 2, ',', '.') . '  ', 0, 1, 'R');

$pdf->SetX($xTotalen);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell($w_label, 6, '9% BTW over ' . chr(128) . ' ' . number_format($prijsExcl, 2, ',', '.'), 0, 0, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($w_bedrag, 6, chr(128) . ' ' . number_format($btwBedrag, 2, ',', '.') . '  ', 0, 1, 'R');

$pdf->SetX($xTotalen + 30);
$pdf->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetLineWidth(0.5);
$pdf->Cell(95, 2, '', 'B', 1, 'R');
$pdf->Ln(3);

$pdf->SetX($xTotalen);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->Cell($w_label, 8, 'TOTAAL TE VOLDOEN:', 0, 0, 'R');
$pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($w_bedrag, 8, chr(128) . ' ' . number_format($totaalAfgerond, 2, ',', '.') . '  ', 0, 1, 'R');
$pdf->SetTextColor(0);

$pdf->Ln(20);
$pdf->SetX(10);
$pdf->SetFont('Arial', '', 10);

$rawTekst = 'Wij verzoeken u vriendelijk het bovenstaande totaalbedrag uiterlijk op ' . $vervalDatum . ' over te maken op IBAN ' . $mijn_iban . "\nt.n.v. " . $mijn_bedrijfsnaam . ' onder vermelding van factuurnummer ' . $factuurNr . '.';

$safeTekst = safe($rawTekst);
$finalTekst = str_replace('[EURO]', chr(128), $safeTekst);

$pdf->MultiCell(0, 5, $finalTekst);

$pdf->Ln(15);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Hartelijk dank voor de opdracht.', 0, 1, 'C');

$pdf->Output('I', 'Factuur-' . $factuurNr . '.pdf');
