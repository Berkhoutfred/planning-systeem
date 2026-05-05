<?php
// verwerk_party.php
// VERSIE: LIVE BETALINGEN (CURL METHODE)
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
    die("Verbindingsfout database. Controleer wachtwoord.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Gegevens uit formulier
    $naam  = $_POST['naam'] ?? '';
    $email = $_POST['email'] ?? '';
    $tel   = $_POST['tel'] ?? '';
    $aantal_vorden  = (int)($_POST['vorden'] ?? 0);
    $aantal_zutphen = (int)($_POST['zutphen'] ?? 0);
    
    $totaal_tickets = $aantal_vorden + $aantal_zutphen;
    
    if ($totaal_tickets == 0) {
        die("Kies minimaal 1 ticket.");
    }

    // Prijs berekenen (12.00 euro per stuk, string formaat voor Mollie)
    $totaal_bedrag = number_format($totaal_tickets * 12.00, 2, '.', '');

    // 2. ORDER OPSLAAN (Status: 'open')
    $stmt = $pdo->prepare("INSERT INTO orders (klant_naam, klant_email, klant_tel, totaal_bedrag, status, created_at) VALUES (?, ?, ?, ?, 'open', NOW())");
    $stmt->execute([$naam, $email, $tel, $totaal_bedrag]);
    $order_id = $pdo->lastInsertId();

    // 3. TICKETS AANMAKEN (Cruciaal voor de QR codes later!)
    function maakCode() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    // Tickets Vorden
    for ($i = 0; $i < $aantal_vorden; $i++) {
        $stmt = $pdo->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code, is_gescand) VALUES (?, 'Vorden', ?, 0)");
        $stmt->execute([$order_id, maakCode()]);
    }

    // Tickets Zutphen
    for ($i = 0; $i < $aantal_zutphen; $i++) {
        $stmt = $pdo->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code, is_gescand) VALUES (?, 'Zutphen', ?, 0)");
        $stmt->execute([$order_id, maakCode()]);
    }

    // 4. NAAR MOLLIE (LIVE)
    // ---------------------------------------------------------
    // VUL HIERONDER JE EIGEN LIVE KEY IN (begint met live_)
    $mollie_key = env_value('MOLLIE_API_KEY_LIVE', ''); 
    // ---------------------------------------------------------

    $payload = [
        "amount" => [
            "currency" => "EUR",
            "value"    => $totaal_bedrag
        ],
        "description" => "Tickets Order #" . $order_id,
        "redirectUrl" => "https://www.berkhoutreizen.nl/party_ticket_print.php?order_id=" . $order_id, // Direct naar ticket na betalen!
        "webhookUrl"  => "https://www.berkhoutreizen.nl/webhook_party.php", 
        "metadata"    => [
            "order_id" => $order_id
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mollie.com/v2/payments");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mollie_key, "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($result, true);

    if (isset($payment['_links']['checkout']['href'])) {
        header("Location: " . $payment['_links']['checkout']['href']);
        exit;
    } else {
        // Foutafhandeling: Laat zien wat er mis ging
        echo "<h1>Er ging iets mis bij Mollie</h1>";
        echo "Melding: " . ($payment['detail'] ?? 'Geen details beschikbaar');
        echo "<br>Check of je LIVE key klopt!";
    }
}
?>