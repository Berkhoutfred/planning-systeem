<?php
declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/calculatie_bijlagen.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    http_response_code(403);
    exit;
}

$bijlageId = (int) ($_GET['id'] ?? 0);
if ($bijlageId <= 0) {
    http_response_code(404);
    exit;
}

$row = calculatie_bijlage_fetch_by_id($pdo, $tenantId, $bijlageId);
if ($row === null) {
    http_response_code(404);
    exit;
}

$path = calculatie_bijlagen_tenant_dir($tenantId) . '/' . $row['stored_filename'];
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$downloadName = preg_replace('/[\r\n"]/', '', (string) $row['original_name']);
if ($downloadName === '') {
    $downloadName = 'bijlage.pdf';
}
if (!str_ends_with(strtolower($downloadName), '.pdf')) {
    $downloadName .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($path);
exit;
