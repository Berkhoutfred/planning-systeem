<?php
// Bestand: beheer/calculatie/pdf_bevestiging.php
// Aangepast voor DEFINITIEVE BEVESTIGING (Crash-proof versie)

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../includes/db.php')) die("Fout: Kan db.php niet vinden.");
if (!file_exists('../includes/fpdf/fpdf.php')) die("Fout: Kan fpdf.php niet vinden.");

require '../includes/db.php';
require '../includes/fpdf/fpdf.php'; 

// --- NIEUW: VEILIGHEIDSFILTER VOOR ICONV (Voorkomt PHP 8+ Null Crashes) ---
function safe_iconv($text) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT', (string)($text ?? ''));
}

if (!isset($_GET['id']) || empty($_GET['id'])) die("Geen ID opgegeven.");
$id = intval($_GET['id']);

// Data Ophalen
$stmt = $pdo->prepare("SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.adres, k.postcode, k.plaats FROM calculaties c LEFT JOIN klanten k ON c.klant_id = k.id WHERE c.id = ?");
$stmt->execute([$id]);
$rit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rit) die("Rit niet gevonden.");

$klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam'];

// Regels ophalen
$stmtRegels = $pdo->prepare("SELECT * FROM calculatie_regels WHERE calculatie_id = ?");
$stmtRegels->execute([$id]);
$alleRegels = $stmtRegels->fetchAll(PDO::FETCH_ASSOC);

function getRegel($regels, $type) {
    foreach($regels as $r) { if($r['type'] == $type) return $r; }
    return null;
}
$vertrekKlant = getRegel($alleRegels, 't_vertrek_klant');
$aankomstBest = getRegel($alleRegels, 't_aankomst_best');
$vertrekBest  = getRegel($alleRegels, 't_vertrek_best');
$retourKlant  = getRegel($alleRegels, 't_retour_klant');

// --- PDF OPBOUWEN ---
class PDF extends FPDF {
    function Header() {
        $blauw  = [0, 51, 102];
        $oranje = [255, 94, 20];

        // Veilige Logo Check
        $logoPad = '../images/berkhout_logo.png'; 
        $logoGeldig = (file_exists($logoPad) && filesize($logoPad) > 0 && @getimagesize($logoPad));

        if($logoGeldig) {
            $this->Image($logoPad, 10, 10, 50); 
        } else {
            $this->SetFont('Arial','B',20);
            $this->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
            $this->Cell(60, 15, 'BERKHOUT', 1, 0, 'C');
        }

        $this->SetXY(120, 10);
        $this->SetFont('Arial','B',10);
        $this->SetTextColor($blauw[0], $blauw[1], $blauw[2]); 
        $this->Cell(80, 5, 'Berkhout Busreizen', 0, 1, 'R');
        $this->SetFont('Arial','',9);
        $this->SetTextColor(50, 50, 50); 
        $this->SetX(120);
        $this->Cell(80, 5, 'Industrieweg 95', 0, 1, 'R');
        $this->SetX(120);
        $this->Cell(80, 5, '7201 EP Zutphen', 0, 1, 'R');
        $this->SetX(120);
        $this->Cell(80, 5, 'T: 0575 - 123 456', 0, 1, 'R');
        $this->SetX(120);
        $this->Cell(80, 5, 'E: info@berkhoutreizen.nl', 0, 1, 'R');
        
        $this->Ln(15);
        $this->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
        $this->SetLineWidth(0.5);
        $this->Line(10, 38, 200, 38); 
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(128);
        $this->Cell(0,10,'Pagina '.$this->PageNo().'/{nb} - Bevestiging Berkhout Busreizen',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$blauw  = [0, 51, 102];
$oranje = [255, 94, 20]; 

// ADRES
$pdf->SetFont('Arial','B',11);
$pdf->SetTextColor(0); 
$pdf->Cell(0, 5, safe_iconv($klantNaam), 0, 1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0, 5, safe_iconv('T.a.v. Contactpersoon'), 0, 1);
$pdf->Cell(0, 5, safe_iconv($rit['adres']), 0, 1);
$pdf->Cell(0, 5, ($rit['postcode'] ?? '') . ' ' . safe_iconv($rit['plaats']), 0, 1);

// META
$pdf->Ln(10);
$pdf->SetFont('Arial','',10);
$pdf->Cell(20, 5, 'Datum:', 0, 0);
$pdf->Cell(50, 5, date('d-m-Y'), 0, 1);

$pdf->SetFont('Arial','B',10);
$pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]); 
$pdf->Cell(20, 5, 'Betreft:', 0, 0);
$pdf->SetFont('Arial','B',10); 
$pdf->Cell(0, 5, 'OPDRACHTBEVESTIGING #' . $rit['id'], 0, 1);
$pdf->SetTextColor(0); 

// INTRO 
$pdf->Ln(8);
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 5, safe_iconv("Geachte relatie,\n\nHartelijk dank voor uw opdracht. Hierbij bevestigen wij definitief de gemaakte afspraken voor uw busreis. Wij hebben de rit voor u ingepland."));

// PROGRAMMA
$pdf->Ln(8);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0, 8, '  BEVESTIGING RITGEGEVENS', 0, 1, 'L', true); 
$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',10);

function row($pdf, $label, $waarde, $fill = false) {
    $pdf->SetFillColor(245, 245, 245); 
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(50, 7, '  ' . $label, 0, 0, 'L', $fill);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0, 7, safe_iconv($waarde), 0, 1, 'R', $fill);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 190, $pdf->GetY());
}

setlocale(LC_TIME, 'nl_NL');
row($pdf, 'Reisdatum:', date('d-m-Y', strtotime($rit['rit_datum'])), false);
$vAdres = $vertrekKlant['adres'] ?? 'Nader te bepalen';
$vTijd  = substr($vertrekKlant['tijd'] ?? '00:00', 0, 5);
row($pdf, 'Vertrekadres:', $vAdres, true);
row($pdf, 'Vertrektijd:', $vTijd . ' uur', true);
$bAdres = $aankomstBest['adres'] ?? 'Nader te bepalen';
row($pdf, 'Bestemming:', $bAdres, false);
$tTijd = substr($vertrekBest['tijd'] ?? '00:00', 0, 5);
if($tTijd != '00:00') { row($pdf, 'Vertrek retour:', $tTijd . ' uur', true); }
$kAdres = $retourKlant['adres'] ?? 'Zelfde als vertrek';
$kTijd  = substr($retourKlant['tijd'] ?? '00:00', 0, 5);
row($pdf, 'Eindbestemming:', $kAdres, false);
row($pdf, 'Verwachte thuiskomst:', $kTijd . ' uur', false);

$passagiersTekst = ($rit['passagiers'] ?? '0') . ' personen';
row($pdf, 'Aantal personen:', $passagiersTekst, true);

// PRIJS
$pdf->Ln(10);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0, 8, '  OVEREENGEKOMEN PRIJS', 0, 1, 'L', true); 

$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',10);
$prijsExcl = $rit['prijs'] ?? 0; 
$btw = $prijsExcl * 0.09;
$totaal = round(($prijsExcl + $btw) / 5) * 5; 

$pdf->Ln(2);
$pdf->Cell(140, 7, 'Busvervoer (Totaalprijs)', 0, 0);
$pdf->Cell(0, 7, chr(128).' '.number_format($prijsExcl, 2, ',', '.'), 0, 1, 'R');
$pdf->Cell(140, 7, 'BTW (9%)', 0, 0);
$pdf->Cell(0, 7, chr(128).' '.number_format($btw, 2, ',', '.'), 0, 1, 'R');
$pdf->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetLineWidth(0.5);
$pdf->Line(150, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(1);
$pdf->SetTextColor($oranje[0], $oranje[1], $oranje[2]);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(140, 9, 'Totaalbedrag (Incl. BTW)', 0, 0);
$pdf->Cell(0, 9, chr(128).' '.number_format($totaal, 2, ',', '.'), 0, 1, 'R');
$pdf->SetTextColor(0); 

// VOORWAARDEN
$pdf->Ln(8);
$pdf->SetFont('Arial','B',9);
$pdf->SetTextColor($blauw[0], $blauw[1], $blauw[2]);
$pdf->Cell(0, 5, 'BELANGRIJKE INFORMATIE:', 0, 1);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial','',8);
$voorwaarden = [
    "- U dient eventuele wijzigingen minimaal 48 uur voor vertrek door te geven.",
    "- Algeheel rookverbod in onze touringcars.",
    "- Annuleren volgens Algemene Voorwaarden Busvervoer Nederland.",
    "- Factuur volgt na afloop van de rit."
];
foreach($voorwaarden as $v) {
    $pdf->Cell(3, 4, '-', 0, 0);
    $pdf->MultiCell(0, 4, safe_iconv(substr($v, 2)));
}

// ONDERTEKENING
$pdf->Ln(10);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor(200, 200, 200);
$yStart = $pdf->GetY();
$pdf->Rect(10, $yStart, 190, 30, 'FD'); 
$pdf->SetXY(15, $yStart + 5);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 5, 'AKKOORD VERZONDEN', 0, 1);
$pdf->SetFont('Arial','',9);
$pdf->MultiCell(0, 5, "Deze bevestiging is per mail verzonden en daarmee bindend.");

$pdf->Output('I', 'Bevestiging-Berkhout-'.$id.'.pdf');
?>