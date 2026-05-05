<?php
// Bestand: beheer/includes/telegram_functies.php
// VERSIE: Centrale module voor pushmeldingen (Nu mét Acceptatie-knop EN App-link!)

function stuurTelegramMelding($pdo, $chauffeur_id, $bericht, $rit_id = null) {
    // Jouw unieke Bot Token
    $botToken = "8254937749:AAGloNfCE4nI16WgbCRCgSSegjM-hs6wzU0";

    // Kijk in de database of deze chauffeur zijn Telegram heeft gekoppeld
    $stmt = $pdo->prepare("SELECT telegram_chat_id FROM chauffeurs WHERE id = ?");
    $stmt->execute([$chauffeur_id]);
    $chauf = $stmt->fetch();

    if ($chauf && !empty($chauf['telegram_chat_id'])) {
        $chat_id = $chauf['telegram_chat_id'];
        $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $bericht,
            'parse_mode' => 'HTML' 
        ];

        // Als er een rit_id bekend is, plakken we de interactieve knoppen eronder!
        if ($rit_id !== null) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        // Knop 1: De Gelezen knop
                        ['text' => '✅ Gelezen & Begrepen', 'callback_data' => 'gelezen_' . $rit_id]
                    ],
                    [
                        // Knop 2: De directe link naar de app
                        ['text' => '📱 Open Chauffeurs App', 'url' => 'https://www.berkhoutreizen.nl/chauffeur/dashboard.php']
                    ]
                ]
            ];
            $data['reply_markup'] = json_encode($keyboard);
        } else {
            // Als het zomaar een los bericht is (zonder rit_id), sturen we in ieder geval de App-link mee
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📱 Open Chauffeurs App', 'url' => 'https://www.berkhoutreizen.nl/chauffeur/dashboard.php']
                    ]
                ]
            ];
            $data['reply_markup'] = json_encode($keyboard);
        }

        // Verstuur via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
        curl_exec($ch);
        curl_close($ch);
        
        return true;
    }
    
    return false; // Chauffeur heeft geen Telegram gekoppeld
}
?>