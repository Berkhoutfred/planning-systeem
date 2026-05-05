<?php
// party_resend.php
// VERSIE: VEILIG MET PHPMAILER, FEESTELIJK ONDERWERP & TOKEN-BEVEILIGING

include 'beveiliging.php';
require_once __DIR__ . '/env.php';

// PHPMailer inladen (pad naar je beheer map)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'beheer/includes/PHPMailer/Exception.php';
require 'beheer/includes/PHPMailer/PHPMailer.php';
require 'beheer/includes/PHPMailer/SMTP.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Geen geldig order ID.");
}

// 1. DATABASE VERBINDING
require_once __DIR__ . '/beheer/includes/db.php';

// 2. KLANTGEGEVENS & EVENEMENT OPHALEN
$stmt = $pdo->prepare("
    SELECT o.*, e.naam AS event_naam 
    FROM orders o 
    LEFT JOIN party_events e ON o.event_id = e.id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Bestelling niet gevonden in de database.");
}

// Pak het e-mailadres uit de juiste doos
$ontvanger = $order['klant_email'] ?? ($order['email'] ?? '');
$klantnaam = $order['klant_naam'] ?? 'Klant';
$eventNaam = $order['event_naam'] ?? 'Berkhout Reizen';

if (empty($ontvanger)) {
    die("Fout: Deze klant heeft geen e-mailadres in het systeem staan.");
}

// 3. TOKEN GENEREREN EN E-MAIL OPBOUWEN
$geheime_sleutel = env_value('TICKET_TOKEN_SECRET', '');
$token = hash('sha256', $id . $ontvanger . $geheime_sleutel);

$subject = "Gefeliciteerd! Hier zijn je bustickets voor " . htmlspecialchars($eventNaam) . " 🥳";
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$link = $scheme . "://" . $host . "/party_ticket_print.php?order_id=" . $id . "&token=" . $token;

$message = "<html><body style='font-family: sans-serif; padding: 20px; background-color: #f4f4f4;'>";
$message .= "<div style='background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: auto;'>";
$message .= "<h2 style='color: #2563eb;'>Bedankt " . htmlspecialchars($klantnaam) . "!</h2>";
$message .= "<p>Hierbij ontvang je (nogmaals) de link naar jouw reservering.</p>";
$message .= "<p>Klik op de onderstaande knop om je tickets te bekijken, op te slaan of uit te printen:</p>";
$message .= "<br>";
$message .= "<a href='$link' style='background:#22c55e; color:white; padding:15px 25px; text-decoration:none; font-weight:bold; border-radius:5px; display:inline-block;'>Bekijk & Download Tickets</a>";
$message .= "<br><br>";
$message .= "<p style='font-size: 12px; color: #666;'>Werkt de knop niet? Kopieer en plak dan deze veilige link in je internetbrowser: <br> $link</p>";
$message .= "</div></body></html>";

// 4. VERSTUREN MET PHPMAILER
$mail = new PHPMailer(true);
$verzend_bericht = "";
$succes = false;

try {
    // === JOUW E-MAIL INSTELLINGEN ===
    $mail->isSMTP();
    $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = env_value('SMTP_USER', ''); 
    $mail->Password   = env_value('SMTP_PASS', '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) env_value('SMTP_PORT', '465');

    // Afzender & Ontvanger
    $mail->setFrom(env_value('SMTP_FROM_EMAIL', 'info@berkhoutreizen.nl'), env_value('SMTP_FROM_NAME', 'Berkhout Reizen'));
    $mail->addAddress($ontvanger, $klantnaam);

    // Inhoud
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;

    $mail->send();
    $succes = true;
    $verzend_bericht = "✅ Ticket succesvol en veilig verzonden naar: <strong>" . htmlspecialchars($ontvanger) . "</strong>!";
} catch (Exception $e) {
    $succes = false;
    $verzend_bericht = "❌ Fout bij verzenden: " . $mail->ErrorInfo;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Ticket Verzenden</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 100%; }
        .btn { background: #333; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 25px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="color: <?php echo $succes ? '#16a34a' : '#dc2626'; ?>; font-size: 32px; margin-top: 0;">
            <?php echo $succes ? 'Verzonden!' : 'Mislukt!'; ?>
        </h2>
        <p style="font-size: 16px; color: #555;"><?php echo $verzend_bericht; ?></p>
        
        <a href="party_bestellingen.php?event_id=<?php echo $order['event_id'] ?? 0; ?>" class="btn">⬅️ Terug naar overzicht</a>
    </div>
</body>
</html>