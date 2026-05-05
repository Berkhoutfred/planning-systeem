<?php
// Bestand: chauffeur/telegram_webhook.php
// VERSIE: Tenant-veilige updates + bot-token uit omgeving (TELEGRAM_BOT_TOKEN)

declare(strict_types=1);

require '../beheer/includes/db.php';

$botToken = trim((string) env_value('TELEGRAM_BOT_TOKEN', ''));
if ($botToken === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'telegram_not_configured';
    exit;
}

$apiBase = 'https://api.telegram.org/bot' . $botToken . '/';

/**
 * @param array<string, mixed> $params
 */
function telegram_webhook_request(string $apiBase, string $method, array $params): void
{
    $ch = curl_init($apiBase . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    curl_close($ch);
}

$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);
if (!is_array($update)) {
    http_response_code(400);
    exit;
}

// ==========================================
// DEEL 1: CHAUFFEUR KOPPELT ZIJN ACCOUNT
// ==========================================
if (isset($update['message']['text'])) {
    $chat_id = (string) ($update['message']['chat']['id'] ?? '');
    $text = trim((string) $update['message']['text']);

    if (str_starts_with($text, '/start koppel_')) {
        $suffix = substr($text, strlen('/start koppel_'));

        if (preg_match('/^(\d+)_(\d+)$/', $suffix, $m)) {
            $cid = (int) $m[1];
            $tid = (int) $m[2];
            $stmt = $pdo->prepare('
                UPDATE chauffeurs c
                INNER JOIN tenants t ON t.id = c.tenant_id AND t.status = \'active\'
                SET c.telegram_chat_id = ?
                WHERE c.id = ? AND c.tenant_id = ? AND c.archief = 0
            ');
            $stmt->execute([$chat_id, $cid, $tid]);

            $reply = '✅ Welkom bij de Berkhout Reizen Bot! Je account is succesvol gekoppeld. Je ontvangt vanaf nu direct een melding als kantoor een nieuwe rit voor je klaarzet.';
            telegram_webhook_request($apiBase, 'sendMessage', [
                'chat_id' => $chat_id,
                'text' => $reply,
            ]);
        } elseif (preg_match('/^(\d+)$/', $suffix, $m)) {
            $cid = (int) $m[1];
            $stmt = $pdo->prepare('
                UPDATE chauffeurs c
                INNER JOIN tenants t ON t.id = c.tenant_id AND t.status = \'active\'
                SET c.telegram_chat_id = ?
                WHERE c.id = ? AND c.archief = 0
            ');
            $stmt->execute([$chat_id, $cid]);

            $reply = '✅ Welkom bij de Berkhout Reizen Bot! Je account is succesvol gekoppeld. Je ontvangt vanaf nu direct een melding als kantoor een nieuwe rit voor je klaarzet.';
            telegram_webhook_request($apiBase, 'sendMessage', [
                'chat_id' => $chat_id,
                'text' => $reply,
            ]);
        }
    }
}

// ==========================================
// DEEL 2: CHAUFFEUR DRUKT OP "GELEZEN" KNOP
// ==========================================
if (isset($update['callback_query'])) {
    $callback_query_id = (string) ($update['callback_query']['id'] ?? '');
    $chat_id = (string) ($update['callback_query']['message']['chat']['id'] ?? '');
    $message_id = (int) ($update['callback_query']['message']['message_id'] ?? 0);
    $data = (string) ($update['callback_query']['data'] ?? '');

    $originele_tekst = (string) ($update['callback_query']['message']['text'] ?? 'Rit Update');

    if (str_starts_with($data, 'gelezen_')) {
        $rit_id = (int) substr($data, strlen('gelezen_'));

        if ($rit_id > 0 && $chat_id !== '' && $message_id > 0) {
            date_default_timezone_set('Europe/Amsterdam');

            $stmt = $pdo->prepare('
                UPDATE ritten r
                INNER JOIN chauffeurs c
                    ON c.telegram_chat_id = ?
                    AND c.id = r.chauffeur_id
                    AND c.tenant_id = r.tenant_id
                    AND c.archief = 0
                SET r.geaccepteerd_tijdstip = NOW()
                WHERE r.id = ?
            ');
            $stmt->execute([$chat_id, $rit_id]);
            $gewijzigd = $stmt->rowCount() > 0;

            telegram_webhook_request($apiBase, 'answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $gewijzigd ? 'Rit geaccepteerd!' : 'Geen wijziging (al verwerkt of geen toegang).',
            ]);

            if ($gewijzigd) {
                $nieuwe_tekst = $originele_tekst . "\n\n✅ <i>Geaccepteerd op " . date('d-m-Y H:i') . '</i>';

                $blijvende_knoppen = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📱 Open Chauffeurs App', 'url' => 'https://www.berkhoutreizen.nl/chauffeur/dashboard.php'],
                        ],
                    ],
                ];

                telegram_webhook_request($apiBase, 'editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => (string) $message_id,
                    'text' => $nieuwe_tekst,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($blijvende_knoppen),
                ]);
            }
        }
    }
}
