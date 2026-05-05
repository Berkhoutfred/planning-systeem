<?php
// graafschap_webhook.php - PERFECTE VERSIE (MET TOKEN UPDATE VOOR UITSMIJTER)
require_once __DIR__ . '/env.php';
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch(PDOException $e) { die(); }

// 1. VUL HIER JE EIGEN TEST/LIVE KEY IN!!!
$mollie_key = env_value('MOLLIE_API_KEY_LIVE', ''); 

$payment_id = $_POST['id'] ?? '';
if (empty($payment_id)) die();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mollie.com/v2/payments/" . $payment_id);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mollie_key]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$payment = json_decode($response, true);

if (isset($payment['status']) && $payment['status'] == 'paid') {
    $order_id = $payment['metadata']['order_id'];
    
    // 2. Database updaten
    $stmt = $pdo->prepare("UPDATE orders SET status = 'betaald' WHERE id = ?");
    $stmt->execute([$order_id]);

    // 3. Gegevens ophalen
    $stmt = $pdo->prepare("SELECT klant_naam, klant_email FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $email = $order['klant_email'];
        $naam  = $order['klant_naam'];

        // --- DE CHIRURGISCHE INGREEP: TOKEN BEREKENEN ---
        $geheime_sleutel = env_value('TICKET_TOKEN_SECRET', '');
        $token = hash('sha256', $order_id . $email . $geheime_sleutel);
        // ------------------------------------------------

        $subject = "Je busticket voor het Graafschap Feest (#$order_id)";
        
        $message = "<html><body style='font-family: Arial, sans-serif; color: #333;'>";
        $message .= "<p>Beste " . htmlspecialchars($naam) . ",</p>";
        $message .= "<p>Bedankt voor je bestelling! Je betaling is succesvol ontvangen.</p>";
        $message .= "<p>Je kunt je ticket(s) downloaden via de onderstaande link:<br>";
        
        // --- DE LINK IS NU VOORZIEN VAN DE JUISTE SLEUTEL ---
        $message .= "<a href='https://www.berkhoutreizen.nl/party_ticket_print.php?order_id=" . $order_id . "&token=" . $token . "' style='color: #2563eb; font-weight: bold;'>Klik hier om je tickets te downloaden</a></p>";
        
        $message .= "<p><strong>Belangrijk:</strong> Zorg dat je in de nacht van 2 op 3 april uiterlijk om 02:10 uur bij de bus bent. De bus vertrekt stipt om 02:15 uur.</p>";
        $message .= "<br><br><p>Met vriendelijke groet,</p>";
        
        // HET NIEUWE LOGO STAAT HIER DIRECT GOED
        $message .= "<img src='https://www.berkhoutreizen.nl/beheer/images/berkhout_logo.png?v=3' alt='Berkhout Reizen' style='width:200px; height:auto;'><br><br>";
        
        $message .= "<p style='font-size: 12px; color: #666;'>T. 0575-525345<br>E. info@taxiberkhout.nl<br>W. <a href='https://www.berkhoutreizen.nl' style='color: #666;'>www.berkhoutreizen.nl</a></p>";
        $message .= "</body></html>";

        // 4. Kogelvrije e-mail instellingen (Headers)
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: Berkhout Reizen <info@taxiberkhout.nl>\r\n";
        $headers .= "Bcc: info@taxiberkhout.nl\r\n";
        $headers .= "Reply-To: info@taxiberkhout.nl\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        mail($email, $subject, $message, $headers);
    }
}
?>