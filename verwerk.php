<?php
// Foutmeldingen aan voor het testen
require_once __DIR__ . '/env.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JOUW MOLLIE SLEUTEL
$mollie_api_key = env_value('MOLLIE_API_KEY_TEST', '');

// 1. Verbinding maken met de database
$servername = env_value('LEGACY_DB_HOST', 'localhost');
$db_name = env_value('LEGACY_DB_NAME', ''); 
$db_user = env_value('LEGACY_DB_USER', ''); 
$db_pass = env_value('LEGACY_DB_PASS', ''); 

try {
    $conn = new PDO("mysql:host=$servername;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// 2. Gegevens verwerken
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $naam = $_POST['naam'] ?? '';
    $email = $_POST['email'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $vorden = isset($_POST['vorden']) ? (int)$_POST['vorden'] : 0;
    $zutphen = isset($_POST['zutphen']) ? (int)$_POST['zutphen'] : 0;
    
    $totaal_tickets = $vorden + $zutphen;
    $totaal_bedrag = $totaal_tickets * 12.00;

    if ($totaal_tickets < 1) {
        die("Kies minimaal 1 ticket.");
    }

    // 3. Opslaan in database
    $stmt = $conn->prepare("INSERT INTO orders (klant_naam, klant_email, klant_tel, totaal_bedrag, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->execute([$naam, $email, $tel, $totaal_bedrag]);
    $order_id = $conn->lastInsertId();

    // Tickets aanmaken
    for ($i = 0; $i < $vorden; $i++) {
        $conn->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code) VALUES (?, 'Vorden', ?)")->execute([$order_id, bin2hex(random_bytes(8))]);
    }
    for ($i = 0; $i < $zutphen; $i++) {
        $conn->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code) VALUES (?, 'Zutphen', ?)")->execute([$order_id, bin2hex(random_bytes(8))]);
    }

    // 4. MOLLIE BETALING STARTEN
    // Omdat we geen ingewikkelde installatie willen, praten we direct met Mollie via 'curl'
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $mijn_domein = $protocol . "://" . $_SERVER['HTTP_HOST'];
    
    $mollie_data = [
        "amount" => [
            "currency" => "EUR",
            "value" => number_format($totaal_bedrag, 2, ".", "")
        ],
        "description" => "Bestelling #" . $order_id,
        "redirectUrl" => $mijn_domein . "/bedankt.php", 
        "webhookUrl"  => $mijn_domein . "/webhook.php", // <--- DEZE REGEL IS NIEUW
        "metadata" => [
            "order_id" => $order_id
        ]
    ];

    $ch = curl_init("https://api.mollie.com/v2/payments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $mollie_api_key,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mollie_data));

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response = json_decode($result, true);

    if ($http_code == 201 && isset($response['_links']['checkout']['href'])) {
        // SUCCES: Stuur de klant naar de betaalpagina van Mollie
        header("Location: " . $response['_links']['checkout']['href']);
        exit;
    } else {
        // FOUT BIJ MOLLIE
        echo "<h1>Er ging iets mis bij het starten van de betaling.</h1>";
        echo "<pre>";
        print_r($response);
        echo "</pre>";
    }
}
?>