<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once 'includes/ideal_factuur_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Geen tenant']);

    exit;
}

$ritId = isset($_GET['rit_id']) ? (int) $_GET['rit_id'] : 0;
if ($ritId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'rit_id ontbreekt']);

    exit;
}

$st = $pdo->prepare('SELECT werk_notities FROM ritten WHERE id = ? AND tenant_id = ? LIMIT 1');
$st->execute([$ritId, $tenantId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Rit niet gevonden']);

    exit;
}

$pid = ideal_werk_get_tag($row['werk_notities'] ?? '', 'IDEAL_PAYMENT_ID');
$local = ideal_werk_get_tag($row['werk_notities'] ?? '', 'IDEAL_STATUS');
if ($pid === null || $pid === '') {
    echo json_encode(['ok' => true, 'has_payment' => false, 'local_status' => $local, 'mollie_status' => null, 'paid' => false]);

    exit;
}

$fetch = ideal_mollie_fetch_payment($pid);
if (!$fetch['ok']) {
    echo json_encode([
        'ok' => true,
        'has_payment' => true,
        'payment_id' => $pid,
        'local_status' => $local,
        'mollie_status' => null,
        'paid' => false,
        'fetch_error' => $fetch['error'],
    ]);

    exit;
}

echo json_encode([
    'ok' => true,
    'has_payment' => true,
    'payment_id' => $pid,
    'local_status' => $local,
    'mollie_status' => $fetch['status'],
    'paid' => (bool) $fetch['paid'],
], JSON_UNESCAPED_UNICODE);
