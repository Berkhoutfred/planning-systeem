<?php
// webhook.php - Versie: Mooie mail + QR Codes
require_once __DIR__ . '/env.php';

// 1. Instellingen
$mollie_api_key = env_value('MOLLIE_API_KEY_TEST', '');

$servername = env_value('LEGACY_DB_HOST', 'localhost');
$db_name = env_value('LEGACY_DB_NAME', ''); 
$db_user = env_value('LEGACY_DB_USER', ''); 
$db_pass = env_value('LEGACY_DB_PASS', ''); 

try {
    $conn = new PDO("mysql:host=$servername;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database fout");
}

// 2. Mollie ID ophalen
$payment_id = $_POST['id'] ?? '';
if (empty($payment_id)) { die("Geen ID"); }

// 3. Status checken bij Mollie
$ch = curl_init("https://api.mollie.com/v2/payments/" . $payment_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mollie_api_key]);
$result = curl_exec($ch);
curl_close($ch);
$payment = json_decode($result, true);

// 4. Als betaald is: Database updaten EN Mailen
if (isset($payment['status']) && $payment['status'] == 'paid') {
    
    $order_id = $payment['metadata']['order_id'];
    
    // A. Update status naar 'betaald'
    $stmt = $conn->prepare("UPDATE orders SET status = 'betaald', betaald_op = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);

    // B. Klantgegevens ophalen
    $stmt_klant = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt_klant->execute([$order_id]);
    $klant = $stmt_klant->fetch();

    // C. Tickets ophalen
    $stmt_tickets = $conn->prepare("SELECT * FROM tickets WHERE order_id = ?");
    $stmt_tickets->execute([$order_id]);
    $tickets = $stmt_tickets->fetchAll();

    // D. De E-mail Opmaak (HTML + CSS)
    $to = $klant['klant_email'];
    $subject = "Je tickets voor Berkhout Reizen";
    
    // Hier begint de opmaak van de mail
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
      <div style='max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; border: 1px solid #ddd;'>
        
        <h1 style='color: #2c3e50; text-align: center;'>Bedankt voor je bestelling!</h1>
        <p style='text-align: center; color: #555;'>Beste " . htmlspecialchars($klant['klant_naam']) . ", hier zijn je tickets.</p>
        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
    ";
    
    // Voor elk ticket maken we een mooi blokje met een QR code
    foreach ($tickets as $ticket) {
        // Dit linkje maakt de QR code
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $ticket['unieke_code'];

        $message .= "
        <div style='border: 2px dashed #333; padding: 15px; margin-bottom: 20px; background-color: #fffdf0; text-align: center;'>
            <h3 style='margin: 0; color: #d35400;'>BUS TICKET: " . $ticket['bestemming'] . "</h3>
            <p style='margin: 5px 0; color: #777;'>Code: " . $ticket['unieke_code'] . "</p>
            <br>
            <img src='" . $qr_url . "' alt='QR Code' width='150' height='150' style='border: 5px solid white;'>
            <p style='font-size: 12px; color: #999;'>Laat deze code scannen bij de chauffeur</p>
        </div>
        ";
    }

    $message .= "
        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='text-align: center; font-size: 12px; color: #aaa;'>Berkhout Reizen</p>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: info@berkhoutreizen.nl" . "\r\n";

    mail($to, $subject, $message, $headers);
}

http_response_code(200);
?>