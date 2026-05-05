<?php
// party_verwerk_dynamisch.php
// VERSIE: LIVE-KLAAR (Met event_id correctie!)
require_once __DIR__ . '/env.php';

// 1. DATABASE VERBINDING
require_once __DIR__ . '/beheer/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Gegevens uit formulier
    $event_id = $_POST['event_id'] ?? 0;
    $naam     = $_POST['naam'] ?? '';
    $email    = $_POST['email'] ?? '';
    $tel      = $_POST['tel'] ?? '';
    
    // De tickets array uit party_boeken.php
    $tickets_input = $_POST['tickets'] ?? [];

    // Even de naam van het event ophalen voor op het bankafschrift
    $stmt = $pdo->prepare("SELECT naam FROM party_events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event_naam = $stmt->fetchColumn();

    if (!$event_naam) die("Evenement niet gevonden.");

    // 2. PRIJS BEREKENEN & PREPAREREN
    $totaal_bedrag = 0;
    $tickets_om_te_maken = []; 

    foreach ($tickets_input as $locatie_id => $aantal) {
        $aantal = (int)$aantal;
        
        if ($aantal > 0) {
            // Haal prijs op uit DB (Veiligheid!)
            $stmt = $pdo->prepare("SELECT * FROM party_opstap_locaties WHERE id = ?");
            $stmt->execute([$locatie_id]);
            $locatie = $stmt->fetch();

            if ($locatie) {
                $regel_totaal = $aantal * $locatie['prijs'];
                $totaal_bedrag += $regel_totaal;

                // Bewaar info voor het aanmaken van tickets straks
                $tickets_om_te_maken[] = [
                    'locatie_naam' => $locatie['naam'],
                    'tijd'         => $locatie['tijd'],
                    'aantal'       => $aantal
                ];
            }
        }
    }

    if ($totaal_bedrag <= 0) {
        die("Kies minimaal 1 ticket.");
    }

    $totaal_fmt = number_format($totaal_bedrag, 2, '.', '');

    // 3. ORDER AANMAKEN (OPLOSSING: event_id is nu toegevoegd!)
    $stmt = $pdo->prepare("INSERT INTO orders (event_id, klant_naam, klant_email, klant_tel, totaal_bedrag, status, created_at) VALUES (?, ?, ?, ?, ?, 'open', NOW())");
    $stmt->execute([$event_id, $naam, $email, $tel, $totaal_fmt]);
    $order_id = $pdo->lastInsertId();

    // 4. TICKETS AANMAKEN (OPLOSSING: event_id is nu toegevoegd!)
    function maakCode() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    foreach ($tickets_om_te_maken as $item) {
        for ($i = 0; $i < $item['aantal']; $i++) {
            // We maken een mooie omschrijving: "EventNaam - Opstapplaats (Tijd)"
            $omschrijving = $event_naam . " - " . $item['locatie_naam'] . " (" . substr($item['tijd'], 0, 5) . ")";
            $unieke_code = maakCode();

            $stmt = $pdo->prepare("INSERT INTO tickets (order_id, event_id, bestemming, unieke_code, is_gescand) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$order_id, $event_id, $omschrijving, $unieke_code]);
        }
    }

    // 5. NAAR MOLLIE (De werkende 'Lite' methode)
    // ---------------------------------------------------------
    // VUL HIER JE NIEUWE LIVE KEY IN (begint met live_)
    $mollie_key = env_value('MOLLIE_API_KEY_LIVE', ''); 
    // ---------------------------------------------------------

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $payload = [
        "amount" => [
            "currency" => "EUR",
            "value"    => $totaal_fmt
        ],
        "description" => $event_naam . " (Order #" . $order_id . ")",
        "redirectUrl" => $scheme . "://" . $host . "/party_ticket_print.php?order_id=" . $order_id,
        "webhookUrl"  => $scheme . "://" . $host . "/webhook_party.php", 
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
        echo "<br>Check je API key!";
    }
}
?>