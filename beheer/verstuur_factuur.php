<?php
// Bestand: beheer/verstuur_factuur.php
// VERSIE: DEFINITIEVE CORRECTIE (Oranje streep boven subtotaal, zwarte streep onder BTW weg)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'includes/db.php';
require 'includes/fpdf/fpdf.php'; 
require_once 'includes/tenant_instellingen_db.php';

// --- PHPMailer Inladen ---
require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==========================================
// JOUW INSTELLINGEN
// ==========================================
$mollie_api_key = env_value('MOLLIE_API_KEY_TEST', ''); 
$smtp_wachtwoord = env_value('SMTP_ADMIN_PASS', env_value('SMTP_PASS', '')); 
// ==========================================

function safe_iconv($text) {
    return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string)($text ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['klant_id'])) {
    die("Geen geldige aanvraag. Ga terug naar het administratie overzicht.");
}

$klant_id = (int)$_POST['klant_id'];
$actie_type = $_POST['actie_type'] ?? 'inzien'; 
$tenantId = function_exists('current_tenant_id') ? current_tenant_id() : 0;
$tenantCfg = tenant_instellingen_get($pdo, $tenantId);
$brandNaam = trim((string) ($tenantCfg['bedrijfsnaam'] ?? 'BusAI'));
$brandAdres = trim((string) ($tenantCfg['adres'] ?? ''));
$brandPcPlaats = trim((string) ($tenantCfg['postcode'] ?? '') . ' ' . (string) ($tenantCfg['plaats'] ?? ''));
$brandTel = trim((string) ($tenantCfg['telefoon'] ?? ''));
$brandEmail = trim((string) ($tenantCfg['email'] ?? ''));
$boekhoudEmail = trim((string) ($tenantCfg['boekhoud_email'] ?? ''));
$brandKvK = trim((string) ($tenantCfg['kvk_nummer'] ?? ''));
$brandBtw = trim((string) ($tenantCfg['btw_nummer'] ?? ''));
$brandIban = trim((string) ($tenantCfg['iban'] ?? ''));
$brandLogoPad = trim((string) ($tenantCfg['logo_pad'] ?? ''));
$appBase = rtrim((string) env_value('APP_BASE_URL', ''), '/');
if ($appBase === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $appBase = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$brandWeb = preg_replace('#^https?://#', '', $appBase);

// --- 1. DATA OPHALEN ---
$stmtKlant = $pdo->prepare("SELECT * FROM klanten WHERE id = ?");
$stmtKlant->execute([$klant_id]);
$klant = $stmtKlant->fetch(PDO::FETCH_ASSOC);

if (!$klant) die("Klant niet gevonden.");

$bedrijf = !empty($klant['bedrijfsnaam']) ? $klant['bedrijfsnaam'] : '';
$persoon = trim(($klant['voornaam'] ?? '') . ' ' . ($klant['achternaam'] ?? ''));
$klantNaamWeergave = !empty($bedrijf) ? $bedrijf : $persoon;

$stmtRitten = $pdo->prepare("
    SELECT r.*, 
           (SELECT van_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as rr_van,
           (SELECT naar_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as rr_naar,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id ORDER BY id ASC LIMIT 1) as calc_van,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND type = 't_aankomst_best' LIMIT 1) as calc_naar
    FROM ritten r
    JOIN diensten d ON r.dienst_id = d.id
    WHERE r.klant_id = ? 
      AND (r.betaalwijze = 'Op Rekening' OR r.betaalwijze = 'Rekening')
      AND r.factuur_status = 'Te factureren'
      AND d.status = 'gecontroleerd'
    ORDER BY r.datum_start ASC
");
$stmtRitten->execute([$klant_id]);
$ritten = $stmtRitten->fetchAll(PDO::FETCH_ASSOC);

if (count($ritten) === 0) die("Geen openstaande ritten gevonden voor deze klant om te factureren.");

// --- 2. FACTUURNUMMER GENEREREN ---
$factuurNummer = 'CONCEPT';
$factuurDatum = date('d-m-Y');
$vervalDatum = date('d-m-Y', strtotime('+14 days'));

if ($actie_type !== 'inzien') {
    $jaar = date('Y');
    $stmtNr = $pdo->query("SELECT COUNT(DISTINCT factuurnummer) as totaal FROM ritten WHERE factuurnummer LIKE '$jaar%'");
    $volgNummer = $stmtNr->fetch()['totaal'] + 1;
    $factuurNummer = $jaar . str_pad($volgNummer, 3, '0', STR_PAD_LEFT); 
}

// --- 3. DE PDF OPBOUWEN ---
class PDF extends FPDF {
    function Header() {
        global $brandNaam, $brandLogoPad;
        $logoPad = $brandLogoPad !== '' ? ltrim($brandLogoPad, '/') : 'images/berkhout_logo.png'; 
        if(file_exists($logoPad)) {
            $this->Image($logoPad, 10, 8, 65); 
        } else {
            $this->SetFont('Arial','B',16);
            $this->Cell(50, 10, safe_iconv($brandNaam), 1, 0, 'C');
        }
    }

    function Footer() {
        global $brandWeb, $brandKvK, $brandBtw;
        $this->SetY(-20);
        $this->SetFont('Arial','',9);
        $this->SetTextColor(120, 120, 120); 
        $footerTekst = $brandWeb . "  |  Kvk nummer: " . $brandKvK . "  |  Btw nummer: " . $brandBtw;
        $this->Cell(0, 5, safe_iconv($footerTekst), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$blauw = [0, 51, 102];
$rood = [220, 53, 69];
$oranje = [255, 94, 20]; // Oranje kleur gedefinieerd

// ==========================================
// DE TOP SECTIE
// ==========================================
$xLeft  = 10;
$xRight = 120; 
$yTop = 35; 

// CONCEPT FACTUUR
if ($actie_type === 'inzien') {
    $pdf->SetXY($xRight, 25); 
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor($rood[0], $rood[1], $rood[2]); 
    $pdf->Cell(80, 6, 'CONCEPT FACTUUR', 0, 1, 'R'); 
    $pdf->SetTextColor(0); 
}

// JOUW BEDRIJFSGEGEVENS (LINKS)
$pdf->SetY($yTop); 
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(0); 

$mijnGegevens = array_values(array_filter([
    $brandAdres,
    $brandPcPlaats,
    $brandTel,
    $brandEmail,
]));

foreach($mijnGegevens as $regel) {
    $pdf->SetX($xLeft);
    $pdf->Cell(80, 5, safe_iconv($regel), 0, 1, 'L');
}

$yOnderMijnGegevens = $pdf->GetY();

// ADRES KLANT (RECHTS)
$pdf->SetXY($xRight, 60); 
$pdf->SetFont('Arial','B',11);
$pdf->Cell(80, 5, safe_iconv($klantNaamWeergave), 0, 1, 'L');
$pdf->SetFont('Arial','',11);
$pdf->SetX($xRight);
$pdf->Cell(80, 5, safe_iconv('T.a.v. Administratie'), 0, 1, 'L');
$pdf->SetX($xRight);
$pdf->Cell(80, 5, safe_iconv($klant['adres']), 0, 1, 'L');
$pdf->SetX($xRight);
$pdf->Cell(80, 5, safe_iconv(($klant['postcode'] ?? '') . ' ' . ($klant['plaats'] ?? '')), 0, 1, 'L');

// ==========================================
// HET FACTUUR KENMERK BLOKJE (LINKS)
// ==========================================
$pdf->SetY($yOnderMijnGegevens + 30); 
$pdf->SetX($xLeft);
$pdf->SetFont('Arial','',10);

$pdf->Cell(0, 5, safe_iconv("Zutphen, " . $factuurDatum), 0, 1, 'L');
$pdf->Ln(5);

function kenmerkRegel($pdf, $label, $waarde, $xLeft) {
    $pdf->SetX($xLeft);
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(35, 5, $label, 0, 0, 'L');
    $pdf->Cell(50, 5, safe_iconv($waarde), 0, 1, 'L'); 
}

kenmerkRegel($pdf, 'Debiteurnummer:', $klant['id'], $xLeft);
kenmerkRegel($pdf, 'Factuurdatum:', $factuurDatum, $xLeft);
kenmerkRegel($pdf, 'Factuurnummer:', $factuurNummer, $xLeft);

$pdf->Ln(2); 

// ==========================================
// TABEL HEADER 
// ==========================================
$w_datum = 25; $w_omschrijving = 135; $w_totaal = 30;
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Arial','B',10);
$pdf->Cell($w_datum, 8, '  Datum', 0, 0, 'L', true);
$pdf->Cell($w_omschrijving, 8, 'Programma', 0, 0, 'L', true); 
$pdf->Cell($w_totaal, 8, 'Prijs  ', 0, 1, 'R', true); 
$pdf->SetTextColor(0); $pdf->SetFont('Arial','',10);

// ==========================================
// TABEL INHOUD
// ==========================================
$totaalIncl = 0; $totaalExcl = 0;
if (!function_exists('haalStad')) {
    function haalStad($adres) {
        $adres = trim((string)$adres); $adres = str_ireplace([', Nederland', ' Nederland', ', The Netherlands'], '', $adres); $adres = trim($adres);
        if (preg_match('/[0-9]{4}\s?[A-Za-z]{2}\s+([^,]+)/', $adres, $matches)) return trim($matches[1]);
        if (strpos($adres, ',') !== false) { $delen = explode(',', $adres); return trim(end($delen)); }
        return strlen($adres) > 20 ? substr($adres, 0, 20).'...' : $adres;
    }
}

foreach ($ritten as $rit) {
    $datumStr = date('d-m-Y', strtotime($rit['datum_start']));
    $vanAdres = $rit['rr_van'] ?: $rit['calc_van']; $naarAdres = $rit['rr_naar'] ?: $rit['calc_naar'];
    $vanStad = haalStad($vanAdres); $naarStad = haalStad($naarAdres);
    $jaar_rit = date('y', strtotime($rit['datum_start'])); $ritNummer = $jaar_rit . str_pad($rit['id'], 3, '0', STR_PAD_LEFT);
    $omschrijving = "Vervoer $vanStad - $naarStad (Rit: $ritNummer)";
    
    $ritPrijsIncl = (float)$rit['betaald_bedrag']; 
    $ritPrijsExcl = $ritPrijsIncl / 1.09; 
    $totaalIncl += $ritPrijsIncl; $totaalExcl += $ritPrijsExcl;

    $pdf->SetX(10);
    $pdf->Cell($w_datum, 8, '  ' . $datumStr, 0, 0, 'L'); 
    $pdf->Cell($w_omschrijving, 8, safe_iconv($omschrijving), 0, 0, 'L');
    $pdf->Cell($w_totaal, 8, chr(128).' '.number_format($ritPrijsIncl, 2, ',', '.').'  ', 0, 1, 'R');
}

// ==========================================
// TOTALEN BLOK 
// ==========================================
$btwBedrag = $totaalIncl - $totaalExcl;
$totaalAfgerond = round($totaalIncl / 5) * 5;

$pdf->Ln(15); 
$xTotalen = 110; 
$w_label = 60; $w_bedrag = 30; 

// Oranje streep BOVEN Subtotaal
$pdf->SetDrawColor($oranje[0], $oranje[1], $oranje[2]); 
$pdf->SetLineWidth(0.3);
$pdf->Line($xTotalen, $pdf->GetY(), 200, $pdf->GetY()); 
$pdf->Ln(2);

// Subtotaal
$pdf->SetX($xTotalen);
$pdf->SetFont('Arial','',10);
$pdf->Cell($w_label, 6, 'Subtotaal (Excl. BTW)', 0, 0, 'R');
$pdf->Cell($w_bedrag, 6, chr(128).' '.number_format($totaalExcl, 2, ',', '.').'  ', 0, 1, 'R');

// BTW
$pdf->SetX($xTotalen);
$pdf->SetFont('Arial','',10);
$pdf->Cell($w_label, 6, 'Nederland 9% x '.chr(128).' '.number_format($totaalExcl, 2, ',', '.'), 0, 0, 'R');
$pdf->Cell($w_bedrag, 6, chr(128).' '.number_format($btwBedrag, 2, ',', '.').'  ', 0, 1, 'R');
$pdf->Ln(3);

// TOTAAL TE VOLDOEN (Blauwe balk volle breedte)
$pdf->SetX(10);
$pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]); 
$pdf->SetTextColor(255, 255, 255); 
$pdf->SetFont('Arial','B',11);
$pdf->Cell(190 - $w_bedrag, 8, 'Totaalbedrag te voldoen:', 0, 0, 'R', true); 
$pdf->SetFont('Arial','B',12);
$pdf->Cell($w_bedrag, 8, chr(128).' '.number_format($totaalAfgerond, 2, ',', '.').'  ', 0, 1, 'R', true);
$pdf->SetTextColor(0);

// ==========================================
// GECENTREERDE BETAALINSTRUCTIES
// ==========================================
$pdf->Ln(20); 
$pdf->SetX(10); 
$pdf->SetFont('Arial','',10);

$klantNr = $klant['id'];
$facNr   = $factuurNummer;

if ($actie_type === 'mailen') {
    $tekst = "Vriendelijk verzoeken wij u het bedrag, binnen 14 dagen, te betalen via de bijgeleverde iDEAL-link o.v.v. uw klantnummer: $klantNr en factuurnummer: $facNr. Langs deze weg willen wij u hartelijk bedanken voor het gestelde vertrouwen en hopen u in de toekomst nogmaals van dienst te kunnen zijn!";
} else {
    $tekst = "Vriendelijk verzoeken wij u het bedrag, binnen 14 dagen, over te maken op " . $brandIban . " t.n.v. " . $brandNaam . " o.v.v. uw klantnummer: $klantNr en factuurnummer: $facNr. Langs deze weg willen wij u hartelijk bedanken voor het gestelde vertrouwen en hopen u in de toekomst nogmaals van dienst te kunnen zijn!";
}

$pdf->MultiCell(0, 5, safe_iconv($tekst), 0, 'C');

// --- EIND PDF GENERATIE ---
if ($actie_type === 'inzien') {
    $pdf->Output('I', 'Concept_Factuur.pdf');
    exit;
}

$factuurBestand = 'Factuur_' . $factuurNummer . '.pdf';
$pdfContent = $pdf->Output('S');

// --- DATABASE UPDATEN ---
$stmtUpdate = $pdo->prepare("UPDATE ritten SET factuur_status = 'Gefactureerd', factuurnummer = ?, factuur_datum = NOW() WHERE klant_id = ? AND factuur_status = 'Te factureren' AND betaalwijze IN ('Op Rekening', 'Rekening')");
$stmtUpdate->execute([$factuurNummer, $klant_id]);

// --- NATIVE MOLLIE KOPPELING VIA CURL ---
$betaalLink = "";
if ($actie_type === 'mailen') {
    $mollieData = [
        "amount" => ["currency" => "EUR", "value" => number_format($totaalAfgerond, 2, '.', '')],
        "description" => "Factuur " . $factuurNummer . " - " . $brandNaam,
        "redirectUrl" => $appBase . "/bedankt.php",
        "metadata" => ["factuurnummer" => $factuurNummer]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mollie.com/v2/payments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mollieData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mollie_api_key, "Content-Type: application/json"]);

    $result = curl_exec($ch); curl_close($ch);
    $response = json_decode($result);
    
    if (isset($response->_links->checkout->href)) {
        $betaalLink = $response->_links->checkout->href;
    } else {
        die("<h3>Fout bij aanmaken Mollie link!</h3>Mollie zegt: " . ($response->detail ?? 'Onbekend probleem. Check je API Key.'));
    }
}

// --- E-MAIL VERZENDEN ---
if ($actie_type === 'mailen') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com'); 
        $mail->SMTPAuth   = true;
        $mail->Username   = env_value('SMTP_ADMIN_USER', env_value('SMTP_USER', '')); 
        $mail->Password   = $smtp_wachtwoord;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = (int) env_value('SMTP_PORT', '465');

        $mail->setFrom(env_value('SMTP_ADMIN_USER', env_value('SMTP_FROM_EMAIL', 'info@busai.nl')), $brandNaam . ' Administratie');
        $mail->addAddress($klant['email']);
        if ($boekhoudEmail !== '' && filter_var($boekhoudEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addBCC($boekhoudEmail);
        }

        $mail->addStringAttachment($pdfContent, $factuurBestand);
        $mail->isHTML(true);
        $mail->Subject = safe_iconv("Factuur $factuurNummer - " . $brandNaam);
        
        $body = "Beste " . $klantNaamWeergave . ",<br><br>";
        $body .= "In de bijlage vindt u de factuur (<strong>$factuurNummer</strong>) voor de geleverde diensten.<br><br>";
        $body .= "U kunt deze factuur direct en veilig online betalen via de onderstaande link:<br><br>";
        $body .= "<a href='$betaalLink' style='background:#ff5e14; color:#fff; padding:12px 25px; text-decoration:none; border-radius:5px; display:inline-block; font-weight:bold;'>Direct betalen via iDEAL</a><br><br>";
        $body .= "Met vriendelijke groet,<br><strong>" . safe_iconv($brandNaam) . "</strong>";
        
        $mail->Body = safe_iconv($body);
        $mail->send();
        
        header("Location: facturatie.php?filter=open&msg=klant_gefactureerd");
        exit;
    } catch (Exception $e) {
        die("<h3>Mail kon niet verzonden worden!</h3> Foutmelding: {$mail->ErrorInfo}");
    }
} else if ($actie_type === 'downloaden') {
    $pdf->Output('D', $factuurBestand);
    exit;
}
?>