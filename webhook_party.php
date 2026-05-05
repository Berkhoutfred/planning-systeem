<?php
// webhook_party.php
// VERSIE: VEILIG & LOGGEND (Met Feestelijk Onderwerp & Token-slot!)

ini_set("log_errors", 1);
ini_set("error_log", "webhook_fouten.log");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'beheer/includes/PHPMailer/Exception.php';
require 'beheer/includes/PHPMailer/PHPMailer.php';
require 'beheer/includes/PHPMailer/SMTP.php';
require_once __DIR__ . '/env.php';

// 1. DATABASE VERBINDING
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database verbinding mislukt: " . $e->getMessage());
    die(); 
}

// 2. MOLLIE ID OPHALEN
$mollie_id = $_POST['id'] ?? '';

if (!empty($mollie_id)) {

    // VUL HIERONDER JE **NIEUWE** LIVE KEY IN!
    $mollie_key = env_value('MOLLIE_API_KEY_LIVE', ''); 

    // 3. STATUS CHECKEN BIJ MOLLIE
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mollie.com/v2/payments/" . $mollie_id);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mollie_key]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    if(curl_errno($ch)){
        error_log("Curl fout: " . curl_error($ch));
    }
    curl_close($ch);

    $payment = json_decode($result, true);

    // 4. ALS BETAALD IS -> ACTIE!
    if (isset($payment['status']) && $payment['status'] == 'paid') {
        
        $order_id = $payment['metadata']['order_id'] ?? 0;

        if ($order_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $huidige_status = $stmt->fetchColumn();

                if ($huidige_status != 'betaald') {

                    // A. UPDATE DATABASE
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'betaald' WHERE id = ?");
                    $stmt->execute([$order_id]);

                    // B. GEGEVENS OPHALEN (Inclusief Evenement Naam!)
                    $stmt = $pdo->prepare("
                        SELECT o.*, e.naam AS event_naam 
                        FROM orders o 
                        LEFT JOIN party_events e ON o.event_id = e.id 
                        WHERE o.id = ?
                    ");
                    $stmt->execute([$order_id]);
                    $order = $stmt->fetch();

                    // C. MAIL OPSTELLEN
                    $ontvanger = $order['klant_email'] ?? ($order['email'] ?? ''); 

                    if (!empty($ontvanger)) {
                        $eventNaam = $order['event_naam'] ?? 'Berkhout Reizen';
                        
                        // NIEUW 1: Het Feestelijke Onderwerp
                        $subject = "Gefeliciteerd! Hier zijn je bustickets voor " . htmlspecialchars($eventNaam) . " 🥳";
                        
                        // NIEUW 2: De onkraakbare Token genereren
                        $geheime_sleutel = env_value('TICKET_TOKEN_SECRET', '');
                        $token = hash('sha256', $order_id . $ontvanger . $geheime_sleutel);
                        
                        $link = "https://www.berkhoutreizen.nl/party_ticket_print.php?order_id=" . $order_id . "&token=" . $token;
                        $klantnaam = $order['klant_naam'] ?? 'Klant';

                        $message = "<html><body style='font-family: sans-serif; padding: 20px; background-color: #f4f4f4;'>";
                        $message .= "<div style='background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: auto;'>";
                        $message .= "<h2 style='color: #2563eb;'>Bedankt " . htmlspecialchars($klantnaam) . "!</h2>";
                        $message .= "<p>De betaling is succesvol verwerkt.</p>";
                        $message .= "<p>Klik op de onderstaande knop om je tickets te bekijken, op te slaan of te printen:</p>";
                        $message .= "<br>";
                        
                        // NIEUW 3: De strakke knop zonder emoji, zodat hij nooit meer crasht in de mail
                        $message .= "<a href='$link' style='background:#22c55e; color:white; padding:15px 25px; text-decoration:none; font-weight:bold; border-radius:5px; display:inline-block;'>Bekijk & Download Tickets</a>";
                        
                        $message .= "<br><br>";
                        $message .= "<p style='font-size: 12px; color: #666;'>Werkt de knop niet? Kopieer dan deze veilige link: <br> $link</p>";
                        $message .= "</div></body></html>";

                        // D. VERSTUREN MET PHPMAILER
                        $mail = new PHPMailer(true);

                        try {
                            $mail->isSMTP();
                            $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com');
                            $mail->SMTPAuth   = true;
                            $mail->Username   = env_value('SMTP_USER', ''); 
                            $mail->Password   = env_value('SMTP_PASS', '');
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->Port       = (int) env_value('SMTP_PORT', '465');

                            $mail->setFrom(env_value('SMTP_FROM_EMAIL', 'info@berkhoutreizen.nl'), env_value('SMTP_FROM_NAME', 'Berkhout Reizen'));
                            $mail->addAddress($ontvanger, $klantnaam);

                            $mail->isHTML(true);
                            $mail->Subject = $subject;
                            $mail->Body    = $message;

                            $mail->send();
                            error_log("Succes: Ticket voor order $order_id verzonden naar $ontvanger");

                        } catch (Exception $e) {
                            error_log("Fout bij SMTP verzenden voor order $order_id: " . $mail->ErrorInfo);
                        }

                    } else {
                        error_log("Kon geen emailadres vinden voor order $order_id");
                    }
                }
            } catch(PDOException $e) {
                error_log("Database fout tijdens update van order $order_id: " . $e->getMessage());
            }
        } else {
             error_log("Geen order_id gevonden in Mollie metadata");
        }
    }
} else {
    error_log("Webhook aangeroepen zonder Mollie ID");
}
?>