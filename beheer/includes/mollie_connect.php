<?php
// Bestand: beheer/includes/mollie_connect.php
// VERSIE: Directe API Connectie voor iDEAL
require_once dirname(__DIR__, 2) . '/env.php';

// Jouw geheime test-sleutel (Deze passen we later aan naar de 'Live' sleutel)
$mollie_api_key = env_value('MOLLIE_API_KEY_TEST', '');

function maakMollieBetalingAan($bedrag, $omschrijving, $rit_id, array $tenant_ctx = []) {
    global $mollie_api_key;
    
    $url = "https://api.mollie.com/v2/payments";
    
    // De webhook URL is het onzichtbare deurtje waar Mollie op klopt als de klant betaald heeft
    // Dit bestandje maken we in de volgende stap!
    $webhook_url = "https://www.berkhoutreizen.nl/chauffeur/mollie_webhook.php";
    
    // Zorg dat het bedrag altijd exact 2 decimalen heeft (bijv. 15.00)
    $bedrag_geformatteerd = number_format((float)$bedrag, 2, '.', '');
    
    $data = [
        "amount" => [
            "currency" => "EUR",
            "value" => $bedrag_geformatteerd
        ],
        "description" => $omschrijving,
        "redirectUrl" => "https://www.berkhoutreizen.nl/chauffeur/rit_bekijken.php?id=" . $rit_id,
        "webhookUrl"  => $webhook_url,
        "metadata"    => array_merge(
            [
                'rit_id' => (string) $rit_id,
            ],
            !empty($tenant_ctx['chauffeur_id']) ? ['chauffeur_id' => (string) (int) $tenant_ctx['chauffeur_id']] : [],
            !empty($tenant_ctx['tenant_id']) ? ['tenant_id' => (string) (int) $tenant_ctx['tenant_id']] : [],
            !empty($tenant_ctx['flow']) ? ['flow' => (string) $tenant_ctx['flow']] : []
        ),
        "method" => "ideal" // Forceert direct iDEAL
    ];
    
    // Het signaal naar Mollie sturen
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $mollie_api_key,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Mollie stuurt een antwoord terug met o.a. de betaallink
    return json_decode($response, true);
}
?>