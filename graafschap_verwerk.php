<?php
// graafschap_verwerk.php
// VERSIE: Inclusief telefoonnummer-fix en event_id in orders!

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    die("Verbindingsfout database.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Gegevens uit formulier (Nu luistert hij naar álle mogelijke namen!)
    $event_id   = $_POST['event_id'] ?? 3;
    $voornaam   = trim($_POST['voornaam'] ?? '');
    $achternaam = trim($_POST['achternaam'] ?? '');
    
    // Fallback voor als het formulier gewoon 'naam' gebruikt in plaats van losse voor/achternamen
    $naam = trim($_POST['naam'] ?? ($voornaam . ' ' . $achternaam));
    
    $email      = trim($_POST['email'] ?? '');
    
    // ---> DE TELEFOON FIX <---
    $tel        = trim($_POST['tel'] ?? $_POST['telefoon'] ?? '');
    
    $opstap_id  = (int)($_POST['opstap_id'] ?? 0);

    if (!preg_match('/@student\.graafschapcollege\.nl$/i', $email)) {
        die("Fout: Je moet een geldig @student.graafschapcollege.nl e-mailadres gebruiken.");
    }

    if ($opstap_id === 0) {
        die("Fout: Je hebt geen opstaplocatie gekozen.");
    }

    $stmt = $pdo->prepare("SELECT naam FROM party_events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event_naam = $stmt->fetchColumn();

    if (!$event_naam) die("Evenement niet gevonden.");

    // 2. EERST PRIJS BEREKENEN (Locatie ophalen)
    $stmt = $pdo->prepare("SELECT * FROM party_opstap_locaties WHERE id = ? AND event_id = ?");
    $stmt->execute([$opstap_id, $event_id]);
    $locatie = $stmt->fetch();

    if (!$locatie) {
        die("Ongeldige opstaplocatie.");
    }

    $totaal_bedrag = $locatie['prijs']; 
    $totaal_fmt = number_format($totaal_bedrag, 2, '.', '');

    // 3. DAARNA ORDER AANMAKEN
    $stmt = $pdo->prepare("INSERT INTO orders (event_id, klant_naam, klant_email, klant_tel, totaal_bedrag, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
    $stmt->execute([$event_id, $naam, $email, $tel, $totaal_fmt]);
    $order_id = $pdo->lastInsertId();

    // 4. TICKET AANMAKEN
    function maakCode() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    $omschrijving = $event_naam . " - " . $locatie['naam'] . " (" . substr($locatie['tijd'], 0, 5) . ")";
    $unieke_code = maakCode();

    $stmt = $pdo->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code, is_gescand) VALUES (?, ?, ?, 0)");
    $stmt->execute([$order_id, $omschrijving, $unieke_code]);

    // 5. NAAR MOLLIE
    $mollie_key = env_value('MOLLIE_API_KEY_LIVE', ''); 

    $payload = [
        "amount" => [
            "currency" => "EUR",
            "value"    => $totaal_fmt
        ],
        "description" => $event_naam . " (Order #" . $order_id . ")",
        "redirectUrl" => "https://www.berkhoutreizen.nl/party_ticket_print.php?order_id=" . $order_id,
        "webhookUrl"  => "https://www.berkhoutreizen.nl/graafschap_webhook.php", 
        "metadata"    => [ "order_id" => $order_id ]
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
        echo "Fout bij Mollie: " . ($payment['detail'] ?? 'Onbekend');
    }
}
?>