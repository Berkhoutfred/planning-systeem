<?php
// Bestand: chauffeur/check_betaling.php
// VERSIE: Mollie-status alleen als betaling bij chauffeur + tenant hoort (metadata en/of rit)

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['chauffeur_id'])) {
    echo json_encode(['status' => 'geen_toegang']);
    exit;
}

require '../beheer/includes/db.php';
require '../beheer/includes/mollie_connect.php';

$chauffeur_id = (int) $_SESSION['chauffeur_id'];

if (!isset($_SESSION['chauffeur_tenant_id'])) {
    $stmt_bt = $pdo->prepare('SELECT tenant_id FROM chauffeurs WHERE id = ? AND archief = 0 LIMIT 1');
    $stmt_bt->execute([$chauffeur_id]);
    $row_bt = $stmt_bt->fetch(PDO::FETCH_ASSOC);
    if (!$row_bt || (int) $row_bt['tenant_id'] < 1) {
        session_destroy();
        echo json_encode(['status' => 'geen_toegang']);
        exit;
    }
    $_SESSION['chauffeur_tenant_id'] = (int) $row_bt['tenant_id'];
}

$tenantId = (int) $_SESSION['chauffeur_tenant_id'];

$stmt_chk = $pdo->prepare('SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? AND archief = 0 LIMIT 1');
$stmt_chk->execute([$chauffeur_id, $tenantId]);
if (!$stmt_chk->fetch()) {
    session_destroy();
    echo json_encode(['status' => 'geen_toegang']);
    exit;
}

$mollie_id = trim((string) ($_POST['mollie_id'] ?? ''));
if ($mollie_id === '' || !preg_match('/^tr_[A-Za-z0-9]+$/', $mollie_id)) {
    echo json_encode(['status' => 'fout_ongeldig_id']);
    exit;
}

$ch = curl_init('https://api.mollie.com/v2/payments/' . rawurlencode($mollie_id));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $mollie_api_key,
    'Accept: application/json',
]);
$raw = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$payment = json_decode((string) $raw, true);
if (!is_array($payment) || $http >= 400) {
    echo json_encode(['status' => 'onbekend']);
    exit;
}

$md = $payment['metadata'] ?? [];
if (!is_array($md)) {
    $md = [];
}

$metaCh = isset($md['chauffeur_id']) && $md['chauffeur_id'] !== '' ? (int) $md['chauffeur_id'] : 0;
$metaTn = isset($md['tenant_id']) && $md['tenant_id'] !== '' ? (int) $md['tenant_id'] : 0;
$metaRit = isset($md['rit_id']) && $md['rit_id'] !== '' ? (int) $md['rit_id'] : 0;

$allowed = false;

if ($metaCh > 0 && $metaTn > 0 && $metaCh === $chauffeur_id && $metaTn === $tenantId) {
    $allowed = true;
}

if (!$allowed && $metaRit > 0) {
    $stmt_r = $pdo->prepare('SELECT 1 FROM ritten WHERE id = ? AND chauffeur_id = ? AND tenant_id = ? LIMIT 1');
    $stmt_r->execute([$metaRit, $chauffeur_id, $tenantId]);
    $allowed = (bool) $stmt_r->fetchColumn();
}

if (!$allowed) {
    echo json_encode(['status' => 'geen_toegang']);
    exit;
}

if (isset($payment['status']) && is_string($payment['status'])) {
    echo json_encode(['status' => $payment['status']]);
    exit;
}

echo json_encode(['status' => 'onbekend']);
