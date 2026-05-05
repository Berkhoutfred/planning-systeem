<?php
// Bestand: beheer/ajax_mail_versturen.php
// VERSIE: DIGITAAL ACCORDEREN (Met magische tokens en succes-knoppen)

ini_set("log_errors", 1);
ini_set("error_log", "mail_fouten.log");

ob_start(); 

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once 'includes/split_ritten.php'; // Inladen van de Paraplu Split-Logica

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!file_exists('includes/PHPMailer/PHPMailer.php')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Kan PHPMailer niet vinden! Controleer of de map in beheer/includes/ staat.']);
    exit;
}

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
$type = isset($input['type']) ? trim((string)$input['type']) : '';
$chauffeur_id = isset($input['chauffeur_id']) ? intval($input['chauffeur_id']) : 0; 

$tenantId = current_tenant_id();
$allowedTypes = ['offerte', 'bevestiging', 'ritopdracht', 'factuur'];

if ($tenantId <= 0 || $id <= 0 || !in_array($type, $allowedTypes, true)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag.']);
    exit;
}

header('Content-Type: application/json');

try {
    // 1. Haal de calculatie en klantgegevens op
    $stmt = $pdo->prepare("
        SELECT c.*, k.email, k.bedrijfsnaam, k.voornaam, k.achternaam 
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    $rit = $stmt->fetch();

    if (!$rit) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Rit niet gevonden in de database.']);
        exit;
    }

    $pdfToken = trim((string) ($rit['token'] ?? ''));
    if ($pdfToken === '') {
        $pdfToken = bin2hex(random_bytes(20));
        $pdo->prepare('UPDATE calculaties SET token = ? WHERE id = ? AND tenant_id = ?')->execute([$pdfToken, $id, $tenantId]);
    }

    $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam'];
    $onderwerp = ucfirst($type) . " betreft rit #" . $id . " - BusAI";
    
    $baseUrl = rtrim((string) env_value('APP_BASE_URL', ''), '/');
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    $domein_beheer = $baseUrl . "/beheer/calculatie";
    $pdfLink = $domein_beheer . '/pdf_' . $type . '.php?id=' . rawurlencode((string) $id) . '&token=' . rawurlencode($pdfToken);
    
    // --- STIJL & HANDTEKENING ---
    $kleur_knop = '#003366'; 
    $kleur_link = '#003366'; 
    $afzender_blok = "
        <strong>BusAI</strong><br>
        T.&nbsp;&nbsp;0575-525345<br>
        E.&nbsp;&nbsp;info@berkhoutreizen.nl<br>
        W.&nbsp;&nbsp;<a href='https://www.berkhoutreizen.nl' style='color: $kleur_link; text-decoration:none; font-weight:bold;'>www.berkhoutreizen.nl</a><br><br>
        <img src='https://www.busai.nl/beheer/images/berkhout_logo.png' alt='BusAI' style='max-width: 250px; height: auto;'>
    ";

    $ontvanger_email = '';
    $ontvanger_naam = '';
    $planbord_notitie = ''; 

    // --- DE WISSEL: Naar WIE sturen we WAT? ---
    
    if ($type === 'ritopdracht') {
        // == ROUTE A: RITOPDRACHT (NAAR CHAUFFEUR) ==
        if ($chauffeur_id <= 0) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Geen geldige chauffeur geselecteerd voor de ritopdracht.']);
            exit;
        }

        $stmtChauffeur = $pdo->prepare("SELECT voornaam, achternaam, email FROM chauffeurs WHERE id = ? AND tenant_id = ?");
        $stmtChauffeur->execute([$chauffeur_id, $tenantId]);
        $chauffeur = $stmtChauffeur->fetch();

        if (!$chauffeur || empty(trim($chauffeur['email']))) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Deze chauffeur heeft geen (geldig) e-mailadres in het systeem staan.']);
            exit;
        }

        $ontvanger_email = trim($chauffeur['email']);
        $ontvanger_naam = trim($chauffeur['voornaam'] . ' ' . $chauffeur['achternaam']);
        
        $aanhef = "Beste " . $chauffeur['voornaam'] . ",";
        $bericht_inhoud = "
            <p>Hierbij ontvang je de ritopdracht voor rit <strong>#$id</strong> (Klant: $klantNaam).</p>
            <p>Via de onderstaande knop kun je jouw ritopdracht direct bekijken of downloaden. Hierin vind je alle adressen, tijden en belangrijke bijzonderheden of instructies voor deze rit.</p>
            <br>
            <p><a href='$pdfLink' style='background-color: $kleur_knop; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Bekijk Ritopdracht</a></p>
            <br>
            <p>Goede reis en alvast bedankt!</p>
        ";

        $checkPlanbord = $pdo->prepare("SELECT id FROM ritten WHERE calculatie_id = ? AND tenant_id = ? LIMIT 1");
        $checkPlanbord->execute([$id, $tenantId]);
        
        if ($checkPlanbord->rowCount() > 0) {
            $updatePlanbord = $pdo->prepare("UPDATE ritten SET chauffeur_id = ? WHERE calculatie_id = ? AND tenant_id = ?");
            $updatePlanbord->execute([$chauffeur_id, $id, $tenantId]);
            $planbord_notitie = " (en direct gekoppeld op je planbord!)";
        } else {
            $planbord_notitie = " (Nog niet op het planbord gekoppeld)";
        }

    } else {
        // == ROUTE B: OFFERTE / BEVESTIGING / FACTUUR (NAAR KLANT) ==
        if (empty($rit['email'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Deze klant heeft geen e-mailadres.']);
            exit;
        }

        $ontvanger_email = trim($rit['email']);
        $ontvanger_naam = $klantNaam;

        if ($type === 'offerte') {
            $acceptLink = $baseUrl . '/offerte.php?token=' . rawurlencode($pdfToken);

            $aanhef = "Beste $klantNaam,";
            $bericht_inhoud = "
                <p>Hartelijk dank voor uw aanvraag! We hebben er zin in om een fantastische rit voor u te verzorgen.</p>
                <p>Via de onderstaande knop kunt u onze vrijblijvende offerte direct online bekijken. Bent u akkoord of wilt u nog iets aanpassen? Dat kunt u veilig en gemakkelijk aangeven op uw persoonlijke offerte-pagina.</p>
                <br>
                <p><a href='$acceptLink' style='background-color: #28a745; color: white; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;'>Bekijk & Beantwoord Offerte</a></p>
                <br>
                <p>Na uw digitale akkoord sturen wij u direct de definitieve ritbevestiging toe.</p>
            ";
            
        } elseif ($type === 'bevestiging') {
            $aanhef = "Geachte $klantNaam,";
            $bericht_inhoud = "
                <p>Graag willen wij u hartelijk danken voor het bevestigen van onze offerte.</p>
                <p>Via de onderstaande knop kunt u de definitieve ritbevestiging bekijken en downloaden. Wij vragen u vriendelijk alle vermelde tijden, bestemming adres(sen), factuuradres en overige gegevens te controleren op volledigheid en juistheid.</p>
                <p>Graag ontvangen wij van u, binnen 7 werkdagen, de ritbevestiging getekend retour.</p>
                <br>
                <p><a href='$pdfLink' style='background-color: $kleur_knop; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Bekijk Bevestiging</a></p>
                <br>
                <p>Wij vertrouwen erop u hiermee voldoende te hebben geïnformeerd. Mocht u echter nog vragen of opmerkingen hebben, dan zijn wij uiteraard graag bereid u verder van dienst te zijn.</p>
            ";
            
        } else { // Bijv Factuur
            $aanhef = "Geachte $klantNaam,";
            $bericht_inhoud = "
                <p>Hierbij ontvangt u de <strong>$type</strong> voor uw rit met nummer #$id.</p>
                <br>
                <p><a href='$pdfLink' style='background-color: $kleur_knop; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Bekijk $type</a></p>
                <br>
                <p>Heeft u vragen of wilt u ergens over overleggen? Reageer dan gerust op deze mail of neem contact met ons op.</p>
            ";
        }
    }

    // --- SAMENVOEGEN VAN DE DEFINITIEVE MAIL ---
    $bericht = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px; color: #333; line-height: 1.5;'>
        <p>$aanhef</p>
        $bericht_inhoud
        <p>Met vriendelijke groet,<br><br>$afzender_blok</p>
    </body>
    </html>
    ";

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet    = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env_value('SMTP_USER', ''); 
        $mail->Password   = env_value('SMTP_PASS', ''); 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int) env_value('SMTP_PORT', '465');

        $mail->setFrom(env_value('SMTP_FROM_EMAIL', 'info@busai.nl'), env_value('SMTP_FROM_NAME', 'BusAI'));
        $mail->addAddress($ontvanger_email, $ontvanger_naam);

        $mail->isHTML(true);
        $mail->Subject = $onderwerp;
        $mail->Body    = $bericht;

        $mail->send();
        
        // Vinkje in calculaties database (Datum Verstuurd opslaan)
        $kolom = 'datum_' . $type . '_verstuurd';
        $nu = date('Y-m-d H:i:s');
        if (in_array($type, $allowedTypes, true)) {
            $upd = $pdo->prepare("UPDATE calculaties SET $kolom = ? WHERE id = ? AND tenant_id = ?");
            $upd->execute([$nu, $id, $tenantId]);
            
            // Als het specifiek een offerte was, springt de status nu naar verzonden!
            if ($type === 'offerte') {
                $pdo->prepare("UPDATE calculaties SET status = 'verzonden' WHERE id = ? AND tenant_id = ?")->execute([$id, $tenantId]);
            }

            // NIEUW: Als een bevestiging is gemaild, roep de Split-Logica aan!
            if ($type === 'bevestiging') {
                maakParapluRittenAan($pdo, $id);
            }
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Document succesvol gemaild naar ' . $ontvanger_email . $planbord_notitie]);
        
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Kon mail niet versturen: ' . $mail->ErrorInfo]);
    }

} catch (Exception $e) {
    error_log("DB Error: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Systeemfout: ' . $e->getMessage()]);
}
?>