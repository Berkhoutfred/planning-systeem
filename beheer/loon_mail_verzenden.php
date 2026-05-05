<?php
// Bestand: beheer/loon_mail_verzenden.php
// Doel: Genereer tokens en verstuur unieke mail-links (Nu met SMTP Anti-Spam Beveiliging!)

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// Laad de nieuwe PHPMailer bestanden in
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

// === E-MAIL INSTELLINGEN VIA ENV ===
$smtp_host = env_value('SMTP_HOST', 'smtp.hostinger.com');
$smtp_user = env_value('SMTP_USER', '');
$smtp_pass = env_value('SMTP_PASS', '');
$afzender_naam = env_value('SMTP_FROM_NAME', 'BusAI Administratie');
// ================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v_maand = isset($_POST['v_maand']) ? (int)$_POST['v_maand'] : 0;
    $v_jaar = isset($_POST['v_jaar']) ? (int)$_POST['v_jaar'] : 0;
    $chauffeurs = isset($_POST['mail_chauffeurs']) ? $_POST['mail_chauffeurs'] : [];

    $maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
    $maand_naam = $maand_namen[$v_maand] ?? '';

    if (empty($chauffeurs)) {
        echo "<div style='padding:40px; text-align:center; font-family:sans-serif;'>
                <h2 style='color:#d97706;'><i class='fas fa-exclamation-triangle'></i> Geen chauffeurs geselecteerd!</h2>
                <br>
                <a href='loonadministratie.php?v_maand=$v_maand&v_jaar=$v_jaar' style='padding:10px 20px; background:#003366; color:white; text-decoration:none; border-radius:5px; font-weight:bold;'>Terug naar dashboard</a>
              </div>";
        include 'includes/footer.php';
        exit;
    }

    $succes_aantal = 0;
    $fouten = [];
    $basis_url = "https://www.busai.nl/beheer/mijn_uren.php";

    foreach ($chauffeurs as $cid) {
        $cid = (int)$cid;
        $stmt = $pdo->prepare("SELECT voornaam, achternaam, email FROM chauffeurs WHERE id = ?");
        $stmt->execute([$cid]);
        $chauffeur = $stmt->fetch();

        if ($chauffeur && !empty($chauffeur['email'])) {
            $token = bin2hex(random_bytes(32));
            
            $update_stmt = $pdo->prepare("UPDATE chauffeurs SET token = ? WHERE id = ?");
            $update_stmt->execute([$token, $cid]);

            $unieke_link = $basis_url . "?id=" . $cid . "&token=" . $token . "&m=" . $v_maand . "&j=" . $v_jaar;

            // --- HET NIEUWE VERZEND PROCES MET SMTP ---
            $mail = new PHPMailer(true);

            try {
                // Server instellingen
                $mail->isSMTP();
                $mail->Host       = $smtp_host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp_user;
                $mail->Password   = $smtp_pass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Veilige SSL verbinding
                $mail->Port       = 465;
                $mail->CharSet    = 'UTF-8';

                // Ontvanger en Afzender
                $mail->setFrom($smtp_user, $afzender_naam);
                $mail->addAddress($chauffeur['email'], $chauffeur['voornaam'] . ' ' . $chauffeur['achternaam']);
                $mail->addReplyTo($smtp_user, $afzender_naam);

                // Inhoud van de mail
                $mail->isHTML(false); // We sturen veilige, simpele tekst
                $mail->Subject = "Jouw urenoverzicht van " . $maand_naam . " " . $v_jaar;
                
                $message = "Beste " . $chauffeur['voornaam'] . ",\n\n";
                $message .= "Hierbij ontvang je de persoonlijke, beveiligde link naar jouw urenoverzicht voor de verloning van " . $maand_naam . " " . $v_jaar . ".\n\n";
                $message .= "Klik op de onderstaande link om je uren te bekijken:\n";
                $message .= $unieke_link . "\n\n";
                $message .= "Kloppen je uren niet of heb je vragen? Laat het ons dan zo snel mogelijk weten door te reageren op deze mail.\n\n";
                $message .= "Met vriendelijke groet,\n\nBusAI";

                $mail->Body = $message;

                // Verstuur!
                $mail->send();
                $succes_aantal++;
                
            } catch (Exception $e) {
                $fouten[] = "Mail naar " . $chauffeur['voornaam'] . " mislukt. Foutmelding: {$mail->ErrorInfo}";
            }
        }
    }

    // Resultaat scherm
    echo "<div style='padding:40px; font-family:sans-serif; max-width: 800px; margin: auto; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-top: 20px;'>";
    echo "<h2 style='color:#28a745; margin-top:0;'><i class='fas fa-check-circle'></i> Verzenden voltooid!</h2>";
    echo "<p style='font-size:16px;'>Er zijn <strong>$succes_aantal</strong> e-mails succesvol verzonden via de beveiligde server.</p>";
    
    if (!empty($fouten)) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin-top: 15px;'>";
        echo "<strong><i class='fas fa-exclamation-circle'></i> Let op, er waren enkele fouten:</strong><br><br>";
        foreach ($fouten as $fout) { echo "- $fout <br>"; }
        echo "</div>";
    }

    echo "<br><br><a href='loonadministratie.php?v_maand=$v_maand&v_jaar=$v_jaar' style='padding:12px 25px; background:#003366; color:white; text-decoration:none; border-radius:5px; font-weight:bold;'><i class='fas fa-arrow-left'></i> Terug naar dashboard</a>";
    echo "</div>";

} else {
    echo "<div style='padding:30px;'><h2 style='color:red;'>Directe toegang niet toegestaan!</h2></div>";
}

include 'includes/footer.php';
?>