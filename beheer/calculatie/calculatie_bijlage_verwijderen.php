<?php
declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/calculatie_bijlagen.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(400);
    exit;
}

$bijlageId = (int) ($_POST['bijlage_id'] ?? 0);
$calcId = (int) ($_POST['calculatie_id'] ?? 0);

if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Sessie ongeldig; vernieuw de pagina.'));
    exit;
}

if ($bijlageId <= 0 || $calcId <= 0) {
    header('Location: ../calculaties.php');
    exit;
}

$chk = $pdo->prepare('SELECT id FROM calculaties WHERE id = ? AND tenant_id = ? LIMIT 1');
$chk->execute([$calcId, $tenantId]);
if (!$chk->fetchColumn()) {
    http_response_code(403);
    exit;
}

$row = calculatie_bijlage_fetch_by_id($pdo, $tenantId, $bijlageId);
if ($row === null || (int) $row['calculatie_id'] !== $calcId) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Bijlage niet gevonden.'));
    exit;
}

calculatie_bijlage_delete($pdo, $tenantId, $bijlageId);

header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_ok=1&bijlage_msg=' . rawurlencode('Bijlage verwijderd.') . '#bijlagen');
exit;
