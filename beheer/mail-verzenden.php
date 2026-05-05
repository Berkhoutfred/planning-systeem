<?php
// Bestand: beheer/mail-verzenden.php
include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// De correcte route naar PHPMailer op basis van jullie server
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

echo "<div style='max-width: 900px; margin: 0 auto; padding: 20px;'>";
echo "<h1>✉️ E-mails Verzenden...</h1>";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['klant_ids'])) {
    
    $klant_ids = $_POST['klant_ids'];
    $onderwerp = trim($_POST['onderwerp']);
    $bericht_inhoud = trim($_POST['bericht']);
    
    $succes_teller = 0;
    $fout_teller = 0;

    $placeholders = implode(',', array_fill(0, count($klant_ids), '?'));
    $stmt = $pdo->prepare("SELECT bedrijfsnaam, voornaam, achternaam, email FROM klanten WHERE id IN ($placeholders)");
    $stmt->execute($klant_ids);
    $klanten = $stmt->fetchAll();

    echo "<ul style='list-style-type:none; padding:0;'>";

    foreach ($klanten as $klant) {
        if (empty($klant['email'])) {
            $fout_teller++;
            echo "<li style='color:red;'>⚠️ Overgeslagen: Geen e-mailadres bekend voor " . htmlspecialchars($klant['bedrijfsnaam'] ?: $klant['voornaam']) . "</li>";
            continue;
        }

        $naam = !empty($klant['bedrijfsnaam']) ? $klant['bedrijfsnaam'] : $klant['voornaam'] . ' ' . $klant['achternaam'];
        $email = $klant['email'];

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env_value('SMTP_USER', ''); 
            $mail->Password   = env_value('SMTP_PASS', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
            $mail->Port       = (int) env_value('SMTP_PORT', '465'); 

            $mail->setFrom(env_value('SMTP_FROM_EMAIL', 'info@busai.nl'), env_value('SMTP_FROM_NAME', 'BusAI'));
            $mail->addAddress($email, $naam);

            $mail->isHTML(false); 
            $mail->Subject = $onderwerp;
            $mail->Body = $bericht_inhoud;

            $mail->send();
            $succes_teller++;
            echo "<li style='color:green;'>✅ Verzonden naar: " . htmlspecialchars($naam) . " ($email)</li>";

        } catch (Exception $e) {
            $fout_teller++;
            echo "<li style='color:red;'>❌ Fout bij verzenden naar: " . htmlspecialchars($naam) . " - " . $mail->ErrorInfo . "</li>";
        }
    }

    echo "</ul>";
    echo "<div style='margin-top:20px; padding:15px; background:#e2e3e5; border-radius:5px;'>";
    echo "<strong>Rapportage:</strong><br>";
    echo "$succes_teller e-mails succesvol verstuurd.<br>";
    if ($fout_teller > 0) {
        echo "$fout_teller e-mails mislukt of overgeslagen.<br>";
    }
    echo "</div>";

} else {
    echo "<p style='color:red;'>Geen gegevens ontvangen.</p>";
}

echo "<br><br><a href='klanten.php' style='background:#007bff; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Terug naar Klanten</a>";
echo "</div>";

include 'includes/footer.php';
?>