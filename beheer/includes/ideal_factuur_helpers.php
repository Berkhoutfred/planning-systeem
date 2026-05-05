<?php
/**
 * iDEAL-factuur wizard: machine-leesbare tags in ritten.werk_notities (geen DB-migratie).
 * Sleutels: IDEAL_BUNDLE, IDEAL_PAYMENT_ID, IDEAL_STATUS
 */
declare(strict_types=1);

function ideal_werk_merge_tag(string $werk, string $key, string $value): string
{
    $werk = (string) $werk;
    $lines = preg_split('/\R/', $werk) ?: [];
    $kept = [];
    $prefix = $key . '=';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, $prefix)) {
            continue;
        }
        $kept[] = $line;
    }
    $kept[] = $prefix . $value;

    return implode("\n", $kept) . "\n";
}

function ideal_werk_get_tag(?string $werk, string $key): ?string
{
    if ($werk === null || $werk === '') {
        return null;
    }
    if (preg_match('/^' . preg_quote($key, '/') . '=(.+)$/m', $werk, $m)) {
        return trim($m[1]);
    }

    return null;
}

/** @return int[] */
function ideal_parse_bundle_ids(?string $werk): array
{
    $raw = ideal_werk_get_tag($werk, 'IDEAL_BUNDLE');
    if ($raw === null || $raw === '') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $ids = [];
    foreach ($parts as $p) {
        if (ctype_digit($p)) {
            $ids[] = (int) $p;
        }
    }

    return array_values(array_unique($ids));
}

function ideal_mollie_api_key(): string
{
    $k = trim((string) env_value('MOLLIE_API_KEY_LIVE', ''));
    if ($k !== '') {
        return $k;
    }

    return trim((string) env_value('MOLLIE_API_KEY_TEST', ''));
}

/**
 * @return array{ok:bool, href:?string, id:?string, error:?string}
 */
function ideal_mollie_create_payment(float $amountEur, string $description, string $redirectUrl, array $metadata): array
{
    $key = ideal_mollie_api_key();
    if ($key === '' || $amountEur <= 0) {
        return ['ok' => false, 'href' => null, 'id' => null, 'error' => 'Geen Mollie API-key of ongeldig bedrag.'];
    }
    $payload = [
        'amount' => ['currency' => 'EUR', 'value' => number_format($amountEur, 2, '.', '')],
        'description' => function_exists('mb_substr') ? mb_substr($description, 0, 120, 'UTF-8') : substr($description, 0, 120),
        'redirectUrl' => $redirectUrl,
        'metadata' => $metadata,
    ];
    $ch = curl_init('https://api.mollie.com/v2/payments');
    if ($ch === false) {
        return ['ok' => false, 'href' => null, 'id' => null, 'error' => 'curl init'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 25,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    if ($result === false) {
        return ['ok' => false, 'href' => null, 'id' => null, 'error' => 'Netwerkfout Mollie'];
    }
    $response = json_decode((string) $result, false);
    if (!is_object($response)) {
        return ['ok' => false, 'href' => null, 'id' => null, 'error' => 'Ongeldig antwoord Mollie'];
    }
    if (isset($response->_links->checkout->href, $response->id)) {
        return ['ok' => true, 'href' => (string) $response->_links->checkout->href, 'id' => (string) $response->id, 'error' => null];
    }
    $detail = is_object($response) && isset($response->detail) ? (string) $response->detail : substr((string) $result, 0, 200);

    return ['ok' => false, 'href' => null, 'id' => null, 'error' => $detail];
}

/**
 * @return array{ok:bool, status:?string, paid:?bool, raw:?object, error:?string}
 */
function ideal_mollie_fetch_payment(string $paymentId): array
{
    $key = ideal_mollie_api_key();
    if ($key === '' || $paymentId === '') {
        return ['ok' => false, 'status' => null, 'paid' => null, 'raw' => null, 'error' => 'Geen key of payment id'];
    }
    $ch = curl_init('https://api.mollie.com/v2/payments/' . rawurlencode($paymentId));
    if ($ch === false) {
        return ['ok' => false, 'status' => null, 'paid' => null, 'raw' => null, 'error' => 'curl'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($result === false || $code >= 400) {
        return ['ok' => false, 'status' => null, 'paid' => null, 'raw' => null, 'error' => 'HTTP ' . $code];
    }
    $response = json_decode((string) $result, false);
    if (!is_object($response) || !isset($response->status)) {
        return ['ok' => false, 'status' => null, 'paid' => null, 'raw' => null, 'error' => 'parse'];
    }
    $st = (string) $response->status;
    $paid = ($st === 'paid');

    return ['ok' => true, 'status' => $st, 'paid' => $paid, 'raw' => $response, 'error' => null];
}
