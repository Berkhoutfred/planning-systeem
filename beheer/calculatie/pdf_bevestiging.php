<?php
// Bestand: beheer/calculatie/pdf_bevestiging.php
// VERSIE: De "Perfecte" Ultra-Strakke Bevestiging (Minimalistisch & Luxe)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../includes/db.php')) die("Fout: Kan db.php niet vinden.");
if (!file_exists('../includes/fpdf/fpdf.php')) die("Fout: Kan fpdf.php niet vinden.");

require '../includes/db.php';
require '../includes/fpdf/fpdf.php';
require '../includes/pdf_instructie_klant.php';
require '../includes/tenant_instellingen_db.php';

function safe_iconv($text) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) ($text ?? ''));
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
        SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats, k.email, k.telefoon
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
        SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats, k.email, k.telefoon
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
        LIMIT 1
    ');
    $stmt->execute([$id, $sessionTenantId]);
}

$rit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rit) {
    die('Rit niet gevonden in database.');
}

$tenantId = (int) ($rit['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    die('Rit niet gevonden in database.');
}

$tenantInst = tenant_instellingen_get($pdo, $tenantId);
$mijn_bedrijfsnaam = trim((string) ($tenantInst['bedrijfsnaam'] ?? 'BusAI'));
$mijn_adres = trim((string) ($tenantInst['adres'] ?? ''));
$mijn_postcode_plaats = trim((string) ($tenantInst['postcode'] ?? '') . ' ' . (string) ($tenantInst['plaats'] ?? ''));
$mijn_telefoon = trim((string) ($tenantInst['telefoon'] ?? ''));
$mijn_email = trim((string) ($tenantInst['email'] ?? ''));
$mijn_logo = trim((string) ($tenantInst['logo_pad'] ?? ''));

if (!empty($rit['klant_id'])) {
    $kid = (int) $rit['klant_id'];
    $chk = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
    $chk->execute([$kid, $tenantId]);
    if (!$chk->fetchColumn()) {
        die('Rit niet gevonden in database.');
    }
}

// Slim ordernummer (Jaar + 3-cijferig ID)
$jaar_rit = !empty($rit['rit_datum']) ? date('y', strtotime($rit['rit_datum'])) : date('y');
$orderNummer = $jaar_rit . str_pad($rit['id'], 3, '0', STR_PAD_LEFT);

// Klantnaam en T.a.v. samenstellen
$bedrijf = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : '';
$persoon = trim(($rit['voornaam'] ?? '') . ' ' . ($rit['achternaam'] ?? ''));

$klantNaamWeergave = !empty($bedrijf) ? $bedrijf : $persoon;
$aanhefNaam = !empty($persoon) ? $persoon : 'relatie';

// Regels ophalen
$stmtRegels = $pdo->prepare("SELECT * FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ?");
$stmtRegels->execute([$id, $tenantId]);
$alleRegels = $stmtRegels->fetchAll(PDO::FETCH_ASSOC);

function getRegel($regels, $type) {
    foreach($regels as $r) {
        if($r['type'] == $type) return $r;
    }
    return null;
}

$vertrekKlant = getRegel($alleRegels, 't_vertrek_klant');
$aankomstBest = getRegel($alleRegels, 't_aankomst_best');
$vertrekBest  = getRegel($alleRegels, 't_vertrek_best');
$retourKlant  = getRegel($alleRegels, 't_retour_klant');

// --- DE PDF OPBOUWEN ---

class PDF extends FPDF {
    function Header() {
        global $mijn_bedrijfsnaam, $mijn_logo;
        $blauw  = [0, 51, 102];
        $oranje = [255, 94, 20];

        $logoPad = $mijn_logo !== '' ? ('../' . ltrim($mijn_logo, '/')) : '../images/berkhout_logo.png';
        if(file_exists($logoPad) && filesize($logoPad) > 0 && @getimagesize($logoPad)) {
            $this->Image($logoPad, 10, 10, 55); 
        } else {
            $this->SetXY(10, 10);
            $this->SetFont('Arial','B',22);
            $this->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
            $this->Cell(60, 10, safe_iconv($mijn_bedrijfsnaam), 0, 0, 'L');
        }

        // LUXE HEADER (Alleen logo en slogan)
        $this->SetXY(120, 14); 
        $this->SetFont('Arial','I',12);
        $this->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
        $this->Cell(80, 5, 'Snel, veilig & vertrouwd', 0, 1, 'R'); 
        
        // Strakke accentlijn
        $this->Ln(8);
        $this->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
        $this->SetLineWidth(0.6);
        $this->Line(10, 32, 200, 32); 
    }

    function Footer() {
        global $mijn_bedrijfsnaam, $mijn_adres, $mijn_postcode_plaats, $mijn_telefoon, $mijn_email;
        $this->SetY(-22);
        
        // Grijze lijn boven footer
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(10, 275, 200, 275);
        
        // BEDRIJFSGEGEVENS IN DE BREEDTE
        $this->SetFont('Arial','',8.5);
        $this->SetTextColor(80, 80, 80);
        $chunks = [];
        if ($mijn_adres !== '') { $chunks[] = $mijn_adres; }
        if ($mijn_postcode_plaats !== '') { $chunks[] = $mijn_postcode_plaats; }
        if ($mijn_telefoon !== '') { $chunks[] = 'T: ' . $mijn_telefoon; }
        if ($mijn_email !== '') { $chunks[] = 'E: ' . $mijn_email; }
        $bedrijfsInfo = $chunks !== [] ? implode('  |  ', $chunks) : $mijn_bedrijfsnaam;
        $this->Cell(0, 5, safe_iconv($bedrijfsInfo), 0, 1, 'C');
        
        // Pagina aanduiding / Document tekst
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(150);
        $this->Cell(0, 5, safe_iconv('Ritbevestiging ' . $mijn_bedrijfsnaam), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$blauw  = [0, 51, 102];
$oranje = [255, 94, 20]; 

// --- ADRESSERING KLANT ---
$pdf->SetY(48);
$pdf->SetFont('Arial','B',11);
$pdf->SetTextColor(0); 
$pdf->Cell(0, 5, safe_iconv($bedrijf), 0, 1);

if (!empty($persoon)) {
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(0, 5, safe_iconv('t.a.v. ' . $persoon), 0, 1); 
}

$pdf->SetFont('Arial','',11);
$pdf->Cell(0, 5, safe_iconv($rit['adres']), 0, 1);
$pdf->Cell(0, 5, safe_iconv(($rit['postcode'] ?? '') . ' ' . $rit['plaats']), 0, 1); 

// --- META INFO BLOK (Aangepast voor Bevestiging) ---
$pdf->SetXY(120, 48);
$pdf->SetFillColor(245, 248, 250);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Rect(120, 46, 80, 22, 'F'); 

$pdf->SetXY(125, 48);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(30, 5, 'Ritnummer:', 0, 0); // Was Offertenummer
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->Cell(40, 5, '#' . $orderNummer, 0, 1, 'R');

$pdf->SetXY(125, 54);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(30, 5, 'Datum:', 0, 0);
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(0);
$pdf->Cell(40, 5, date('d-m-Y'), 0, 1, 'R');

$pdf->SetXY(125, 60);
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->Cell(70, 5, 'DEFINITIEVE BEVESTIGING', 0, 1, 'R');

// --- INTRODUCTIE ---
$pdf->SetY(78); 
$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor(0);
$pdf->Cell(0, 5, safe_iconv('Geachte ' . $aanhefNaam . ','), 0, 1); 
$pdf->Ln(2);

$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 5.5, safe_iconv("Hartelijk dank voor uw reservering en het in ons gestelde vertrouwen. Hierbij bevestigen wij definitief uw boeking. Hieronder treft u de afgesproken tijden, adressen en de bijbehorende kostenspecificatie aan.")); 

// --- PROGRAMMA TABEL ---
$pdf->Ln(8);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 7, '  PROGRAMMA', 0, 1, 'L', true);

$pdf->SetTextColor(0);

function chic_row($pdf, $label, $waarde, $fill = false) {
    if ($fill) $pdf->SetFillColor(248, 249, 250); 
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(45, 7, '  ' . safe_iconv($label), 0, 0, 'L', $fill);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(0, 7, safe_iconv($waarde), 0, 1, 'L', $fill);
    $pdf->SetDrawColor(235, 235, 235);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
}

$dagen = ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'];
$dagNummer = date('w', strtotime($rit['rit_datum']));
$datumTekst = $dagen[$dagNummer] . ' ' . date('d-m-Y', strtotime($rit['rit_datum']));

// Let op: Bij bevestiging tonen we het DEFINITIEVE aantal personen (als ingevuld)
$paxWeergave = !empty($rit['definitieve_pax']) ? $rit['definitieve_pax'] : $rit['passagiers'];

chic_row($pdf, 'Reisdatum:', $datumTekst, false);
chic_row($pdf, 'Aantal passagiers:', $paxWeergave . ' personen (Definitief)', true);

$vAdres = trim($vertrekKlant['adres'] ?? '');
$vTijd  = substr($vertrekKlant['tijd'] ?? '00:00', 0, 5);
$vVoorstaan = ($vTijd != '00:00') ? date('H:i', strtotime('-15 minutes', strtotime($vTijd))) : '';

if($vVoorstaan) chic_row($pdf, 'Voorstaan:', $vVoorstaan . ' uur', false);
chic_row($pdf, 'Vertrek:', $vTijd . ' uur - ' . $vAdres, true);

$bAdres = trim($aankomstBest['adres'] ?? '');
$aankomstTijd = substr($aankomstBest['tijd'] ?? '00:00', 0, 5);
if($aankomstTijd != '00:00') {
    chic_row($pdf, 'Geplande aankomst:', $aankomstTijd . ' uur - ' . $bAdres, false);
} else {
    chic_row($pdf, 'Bestemming:', $bAdres, false);
}

$tTijd = substr($vertrekBest['tijd'] ?? '00:00', 0, 5);
if($tTijd != '00:00') {
    $tVoorstaan = date('H:i', strtotime('-15 minutes', strtotime($tTijd)));
    chic_row($pdf, 'Voorstaan retour:', $tVoorstaan . ' uur', true);
    chic_row($pdf, 'Vertrek retour:', $tTijd . ' uur', false);
}

$kAdres = trim($retourKlant['adres'] ?? '');
$kTijd  = substr($retourKlant['tijd'] ?? '00:00', 0, 5);
if($kTijd != '00:00') chic_row($pdf, 'Verwachte thuiskomst:', $kTijd . ' uur - ' . $kAdres, true);

// Bijzonderheden in de tabel — alleen echte klanttekst
$instructie = pdf_filter_instructie_voor_klant(isset($rit['instructie_kantoor']) ? (string) $rit['instructie_kantoor'] : null);
if ($instructie !== '') {
    $startY = $pdf->GetY();
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(45, 6, '  Bijzonderheden:', 0, 0, 'L');
    $pdf->SetXY(55, $startY); 
    $pdf->SetFont('Arial','',9);
    $pdf->MultiCell(0, 6, safe_iconv($instructie)); 
    $pdf->SetDrawColor(235, 235, 235);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
}

// --- STRAKKE PRIJS SPECIFICATIE ---
$pdf->Ln(10);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(150, 7, '  OMSCHRIJVING', 0, 0, 'L', true);
$pdf->Cell(40, 7, 'BEDRAG', 0, 1, 'R', true);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',10);

$prijsExcl = $rit['prijs'] ?? 0; 
$btw = $prijsExcl * 0.09;
$totaal = round(($prijsExcl + $btw) / 5) * 5; 

$pdf->Ln(2);
$pdf->Cell(150, 6, '  Vervoerskosten (1 touringcar) - Excl. BTW', 0, 0);
$pdf->Cell(40, 6, chr(128).' '.number_format($prijsExcl, 2, ',', '.'), 0, 1, 'R');

$pdf->SetFont('Arial','I',9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(150, 5, '  Nederland 9% BTW over ' . chr(128).' '.number_format($prijsExcl, 2, ',', '.'), 0, 0);
$pdf->Cell(40, 5, chr(128).' '.number_format($btw, 2, ',', '.'), 0, 1, 'R');

$pdf->Ln(3);
$pdf->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetLineWidth(0.5);
$pdf->Line(160, $pdf->GetY(), 200, $pdf->GetY()); 
$pdf->Ln(2);

$pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(150, 8, 'TOTAALPRIJS INCL. BTW:  ', 0, 0, 'R');
$pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(40, 8, chr(128).' '.number_format($totaal, 2, ',', '.'), 0, 1, 'R');


// --- AFSLUITING & VOORWAARDEN (Aangepast voor Bevestiging) ---
$pdf->Ln(10);
$pdf->SetTextColor(100, 100, 100); 
$pdf->SetFont('Arial','',8); 

$voorwaardenTekst = "De aanbieding is exclusief eventuele parkeer-, tol- en/of verblijfskosten. Wij behouden ons het recht voor onze reissommen te wijzigen, indien daartoe aanleiding bestaat door prijs en/of brandstofverhogingen door derden.";
$pdf->MultiCell(0, 4, safe_iconv($voorwaardenTekst)); 

$pdf->Ln(6);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',10);
// Geen vraag meer om een reactie, maar een mooie wens:
$pdf->Cell(0, 5, safe_iconv('Wij wensen u alvast een prettige en veilige reis met ' . $mijn_bedrijfsnaam . '.'), 0, 1);
$pdf->Ln(4);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 5, safe_iconv($mijn_bedrijfsnaam), 0, 1); 
$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 5, safe_iconv('Fred Stravers'), 0, 1); 

$slug = preg_replace('/[^A-Za-z0-9_-]/', '_', $mijn_bedrijfsnaam);
$pdf->Output('I', 'Bevestiging-' . $slug . '-' . $orderNummer . '.pdf');
?>