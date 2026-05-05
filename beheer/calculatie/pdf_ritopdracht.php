<?php
// Bestand: beheer/calculatie/pdf_ritopdracht.php
// Doel: Ritstaat voor de chauffeur (Anti-Crash Versie)

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Controleer bestanden
if (!file_exists('../includes/db.php')) die("Fout: Kan db.php niet vinden.");
if (!file_exists('../includes/fpdf/fpdf.php')) die("Fout: Kan fpdf.php niet vinden.");

require '../includes/db.php';
require '../includes/fpdf/fpdf.php';
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
        SELECT c.*,
               k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats, k.telefoon, k.email,
               v.naam as bus_naam, v.kenteken
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        LEFT JOIN calculatie_voertuigen v ON c.voertuig_id = v.id AND v.tenant_id = c.tenant_id
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
        SELECT c.*,
               k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats, k.telefoon, k.email,
               v.naam as bus_naam, v.kenteken
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        LEFT JOIN calculatie_voertuigen v ON c.voertuig_id = v.id AND v.tenant_id = c.tenant_id
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
$mijn_logo = trim((string) ($tenantInst['logo_pad'] ?? ''));

if (!empty($rit['klant_id'])) {
    $kid = (int) $rit['klant_id'];
    $chk = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
    $chk->execute([$kid, $tenantId]);
    if (!$chk->fetchColumn()) {
        die('Rit niet gevonden.');
    }
}

// Naam samenstellen
$klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam'];

// Regels (Route)
$stmtRegels = $pdo->prepare("SELECT * FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ? ORDER BY id ASC");
$stmtRegels->execute([$id, $tenantId]);
$regels = $stmtRegels->fetchAll(PDO::FETCH_ASSOC);

function getRegel($regels, $type) {
    foreach($regels as $r) { if($r['type'] == $type) return $r; }
    return null;
}
$voorstaan = getRegel($regels, 't_voorstaan');
$bestemming = getRegel($regels, 't_aankomst_best');

// --- PDF STARTEN ---
class PDF extends FPDF {
    function Header() {
        global $mijn_bedrijfsnaam, $mijn_logo;
        $blauw  = [0, 51, 102];
        
        // Logo Check
        $logoPad = $mijn_logo !== '' ? ('../' . ltrim($mijn_logo, '/')) : '../images/berkhout_logo.png';
        $logoGeldig = (file_exists($logoPad) && filesize($logoPad) > 0 && @getimagesize($logoPad));

        if($logoGeldig) {
            $this->Image($logoPad, 10, 8, 45); 
        } else {
            $this->SetFont('Arial','B',16);
            $this->Cell(50, 10, safe_iconv($mijn_bedrijfsnaam), 1, 0, 'C');
        }

        // Titel
        $this->SetXY(120, 10);
        $this->SetFont('Arial','B',24);
        $this->SetTextColor($blauw[0], $blauw[1], $blauw[2]); 
        $this->Cell(80, 10, 'RITOPDRACHT', 0, 1, 'R');
        $this->SetFont('Arial','B',10);
        $this->SetTextColor(0);
        
        $this->Ln(15);
    }

    function Footer() {
        global $mijn_bedrijfsnaam;
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,safe_iconv($mijn_bedrijfsnaam . ' - Ritopdracht'),0,0,'C');
    }

    // --- DE HULPFUNCTIE DIE MISTE (NU BINNEN DE CLASS) ---
    function NbLines($w, $txt) {
        // Berekent hoeveel regels een tekst in beslag neemt bij een bepaalde breedte
        $cw = &$this->CurrentFont['cw'];
        if($w==0) $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',(string)$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb) {
            $c = $s[$i];
            if($c=="\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue;
            }
            if($c==' ') $sep = $i;
            $l += $cw[$c];
            if($l>$wmax) {
                if($sep==-1) {
                    if($i==$j) $i++;
                } else $i = $sep+1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$blauw  = [0, 51, 102];
$grijs  = [240, 240, 240];

// --- BLOK 1: HOOFDGEGEVENS ---
$pdf->SetFont('Arial','',10);

$yStart = $pdf->GetY();
$pdf->SetFillColor($grijs[0], $grijs[1], $grijs[2]);

// Chauffeur & Bus
$pdf->Cell(35, 8, 'Chauffeur:', 1, 0, 'L', true);
$pdf->Cell(55, 8, '.......................................', 1, 1);

$pdf->Cell(35, 8, 'Bus:', 1, 0, 'L', true);
$pdf->Cell(55, 8, safe_iconv($rit['bus_naam']), 1, 1);

$pdf->Cell(35, 8, 'Kenteken:', 1, 0, 'L', true);
$pdf->Cell(55, 8, $rit['kenteken'] ?? '...................', 1, 1);

// Klantgegevens Rechts
$pdf->SetXY(110, $yStart);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(80, 8, 'Klantgegevens:', 1, 1, 'L', true);

$pdf->SetXY(110, $yStart + 8);
$pdf->SetFont('Arial','',10);
$adresBlok = $klantNaam . "\n" . ($rit['adres'] ?? '') . "\n" . ($rit['postcode'] ?? '') . " " . ($rit['plaats'] ?? '');
$pdf->MultiCell(80, 5.3, safe_iconv($adresBlok), 'LR', 'L');

// Telefoon
$pdf->SetXY(110, $yStart + 24);
$pdf->Cell(80, 0, '', 'T'); 
$pdf->SetXY(110, $yStart + 24);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25, 6, 'Tel:', 'L', 0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(55, 6, $rit['telefoon'] ?? '', 'R', 1);

// --- BLOK 2: RIT DETAILS ---
$pdf->Ln(5);
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  RITGEGEVENS', 0, 1, 'L', true);
$pdf->SetTextColor(0);

$pdf->SetFont('Arial','',10);
// Rij 1
$pdf->Cell(35, 7, 'Datum:', 0, 0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60, 7, date('d-m-Y', strtotime($rit['rit_datum'])), 0, 0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(35, 7, 'Aantal Personen:', 0, 0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(60, 7, $rit['passagiers'] ?? '0', 0, 1);

// Rij 2
$pdf->SetFont('Arial','',10);
$pdf->Cell(35, 7, 'Voorstaan:', 0, 0);
$pdf->SetFont('Arial','B',10);
$tijdVoorstaan = $voorstaan ? substr($voorstaan['tijd'], 0, 5) : '..:..';
$pdf->Cell(60, 7, $tijdVoorstaan . ' uur', 0, 0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(35, 7, 'Rit Type:', 0, 0);
$pdf->Cell(60, 7, ucfirst($rit['rittype'] ?? ''), 0, 1);

// Rij 3
$pdf->Cell(35, 7, 'Bestemming:', 0, 0);
$bestAdres = $bestemming ? $bestemming['adres'] : '';
$pdf->MultiCell(0, 7, safe_iconv($bestAdres));


// --- BLOK 3: PLANNING (DE TABEL) ---
$pdf->Ln(5);
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  PLANNING & UITVOERING', 0, 1, 'L', true);

// Headers
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',9);

$w1 = 50; 
$w2 = 20; 
$w3 = 25; 
$w4 = 25; 
$w5 = 70; 

$pdf->Cell($w1, 8, 'Activiteit', 1, 0, 'L', true);
$pdf->Cell($w2, 8, 'Plan', 1, 0, 'C', true);
$pdf->Cell($w3, 8, 'Werkelijk', 1, 0, 'C', true);
$pdf->Cell($w4, 8, 'KM Stand', 1, 0, 'C', true);
$pdf->Cell($w5, 8, 'Locatie / Info', 1, 1, 'L', true);

$pdf->SetFont('Arial','',9);

// Data Loop
foreach($regels as $r) {
    $label = $r['label'] ?? '';
    if($r['type'] == 't_garage') $label = "Vertrek Garage";
    if($r['type'] == 't_retour_garage') $label = "Terug in Garage";

    $tijd = isset($r['tijd']) ? substr($r['tijd'], 0, 5) : '';
    $adres = safe_iconv($r['adres']);

    // Hoogte bepalen met NbLines
    $nb = $pdf->NbLines($w5, $adres);
    $h = 8 * max(1, $nb);

    if($pdf->GetY() + $h > 270) $pdf->AddPage();

    $pdf->Cell($w1, $h, safe_iconv($label), 1, 0, 'L');
    $pdf->Cell($w2, $h, $tijd, 1, 0, 'C');
    $pdf->Cell($w3, $h, '', 1, 0, 'C'); 
    $pdf->Cell($w4, $h, '', 1, 0, 'C'); 
    
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->MultiCell($w5, 8, $adres, 1, 'L');
    $pdf->SetXY($x + $w5, $y); 
    
    // Correcte nieuwe regel
    $pdf->SetXY(10, $y + $h);
}

// --- BLOK 4: CHECKLIST ---
if($pdf->GetY() > 220) $pdf->AddPage();

$pdf->Ln(10);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 6, 'CONTROLE & AFHANDELING', 'B', 1);
$pdf->Ln(2);

$pdf->SetFont('Arial','',10);

$vragen = [
    "Is er schade aan de touringcar?" => "JA / NEE",
    "Is de bus van binnen en buiten schoon?" => "JA / NEE",
    "Heeft u de touringcar afgetankt?" => "JA / NEE",
    "Zijn er gevonden voorwerpen?" => "JA / NEE",
    "Heeft u iets op rekening gezet? (Bedrag: ...)" => "JA / NEE",
    "Heeft de klant contant afgerekend? (Bedrag: ...)" => "JA / NEE",
];

foreach($vragen as $vr => $optie) {
    $pdf->Cell(130, 7, safe_iconv($vr), 0, 0);
    $pdf->Cell(50, 7, $optie, 0, 1, 'R');
    $pdf->Line(10, $pdf->GetY(), 190, $pdf->GetY()); 
}

$pdf->Ln(5);
$pdf->Cell(0, 6, 'Bijzonderheden / Opmerkingen:', 0, 1);
$pdf->Rect(10, $pdf->GetY(), 180, 15);
$pdf->Ln(18);

$pdf->Cell(90, 6, 'Datum: ' . date('d-m-Y'), 0, 0);
$pdf->Cell(90, 6, 'Handtekening Chauffeur:', 0, 1);
$pdf->Rect(100, $pdf->GetY(), 80, 20); 

$pdf->Output('I', 'Ritopdracht-'.$id.'.pdf');
?>