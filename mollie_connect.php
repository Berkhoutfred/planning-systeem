<?php
// mollie_connect.php
// Simpele verbinding met Mollie zonder Composer

// ---------------------------------------------------
// 🔴 VUL HIER JOUW MOLLIE API KEY IN
// Kies 'test_...' om te oefenen of 'live_...' voor het echt
// ---------------------------------------------------
$mollie_api_key = 'live_DUG2JmqP2aFUGJTE2RMcgVcpAEsVFT'; 


function mollieRequest($endpoint, $data = [], $method = 'POST') {
    global $mollie_api_key;
    
    $url = "https://api.mollie.com/v2/" . $endpoint;
    
    $headers = [
        "Authorization: Bearer " . $mollie_api_key,
        "Content-Type: application/json",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        die('Mollie Fout: ' . curl_error($ch));
    }
    
    curl_close($ch);
    return json_decode($response, true);
}
?>