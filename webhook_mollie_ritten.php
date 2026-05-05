<?php
/**
 * Mollie-webhook voor dashboard-iDEAL (metadata: tenant_id, rit_ids).
 * Configureer in Mollie-dashboard: URL naar dit bestand + POST id.
 */
declare(strict_types=1);

require_once __DIR__ . '/beheer/includes/db.php';
require_once __DIR__ . '/beheer/includes/ideal_factuur_helpers.php';

header('Content-Type: text/plain; charset=utf-8');

$paymentId = trim((string) ($_POST['id'] ?? ''));
if ($paymentId === '') {
    http_response_code(400);
    echo 'no id';

    exit;
}

$fetch = ideal_mollie_fetch_payment($paymentId);
if (!$fetch['ok'] || $fetch['raw'] === null) {
    http_response_code(200);
    echo 'fetch failed';

    exit;
}

$meta = $fetch['raw']->metadata ?? null;
$tenantId = 0;
$ritIds = [];
if (is_object($meta)) {
    if (isset($meta->tenant_id)) {
        $tenantId = (int) $meta->tenant_id;
    }
    if (isset($meta->rit_ids)) {
        foreach (preg_split('/\s*,\s*/', (string) $meta->rit_ids) ?: [] as $p) {
            if (ctype_digit($p)) {
                $ritIds[] = (int) $p;
            }
        }
    }
    if ($ritIds === [] && isset($meta->rit_id) && ctype_digit((string) $meta->rit_id)) {
        $ritIds[] = (int) $meta->rit_id;
    }
}

if ($tenantId <= 0 || $ritIds === []) {
    http_response_code(200);
    echo 'no metadata';

    exit;
}

$paid = (bool) $fetch['paid'];
$newStatus = $paid ? 'paid' : (string) ($fetch['status'] ?? 'unknown');

$in = implode(',', array_fill(0, count($ritIds), '?'));
$params = array_merge([$tenantId], $ritIds);
$st = $pdo->prepare("SELECT id, werk_notities FROM ritten WHERE tenant_id = ? AND id IN ($in)");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$upd = $pdo->prepare('UPDATE ritten SET werk_notities = ? WHERE id = ? AND tenant_id = ?');
foreach ($rows as $r) {
    $merged = ideal_werk_merge_tag((string) ($r['werk_notities'] ?? ''), 'IDEAL_STATUS', $newStatus);
    $upd->execute([$merged, (int) $r['id'], $tenantId]);
}

http_response_code(200);
echo 'ok';
