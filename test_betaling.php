<?php
// test_betaling.php (LITE VERSIE - GEEN EXTRA MAP NODIG)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. JOUW LIVE KEY (Vervang de X'jes!)
$api_key = "live_DUG2JmqP2aFUGJTE2RMcgVcpAEsVFT"; 

// 2. DE BETALING DATA
$data = [
    "amount" => [
        "currency" => "EUR",
        "value" => "1.00"
    ],
    "description" => "Test betaling Berkhout",
    "redirectUrl" => "https://www.berkhoutreizen.nl/party_beheer.php",
    "webhookUrl"  => "https://www.berkhoutreizen.nl/webhook_party.php",
    "metadata"    => ["order_id" => "99999"]
];

// 3. VERBINDING MAKEN MET MOLLIE VIA CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mollie.com/v2/payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $api_key,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// 4. RESULTAAT CONTROLEREN
if ($http_code == 201) {
    // Succes! Stuur door naar de betaalpagina
    header("Location: " . $result['_links']['checkout']['href'], true, 303);
    exit;
} else {
    echo "Fout bij Mollie (Code $http_code): " . ($result['detail'] ?? 'Onbekende fout');
}
?>