<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['platform_owner']);
require 'includes/db.php';
require_once __DIR__ . '/includes/auth_tenant.php';
require_once __DIR__ . '/includes/tenant_instellingen_db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    die('Alleen POST toegestaan.');
}

if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
    http_response_code(403);
    die('Sessie verlopen. Vernieuw de pagina.');
}

$tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;
if ($tenantId <= 0) {
    http_response_code(400);
    die('Ongeldige tenant.');
}

tenant_instellingen_bootstrap($pdo);

$stmt = $pdo->prepare("SELECT id, slug FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    http_response_code(404);
    die('Tenant niet gevonden of niet actief.');
}

$home = auth_user_home_tenant($pdo, (int) ($_SESSION['user_id'] ?? 0));
if ($home === null || (string) $home['rol'] !== 'platform_owner') {
    http_response_code(403);
    die('Alleen platform owner mag van omgeving wisselen.');
}

if (!auth_may_use_tenant($pdo, 'platform_owner', (int) $home['id'], (int) $tenant['id'])) {
    http_response_code(403);
    die('Deze omgeving is niet toegestaan.');
}

$_SESSION['tenant_id'] = (int) $tenant['id'];
$_SESSION['tenant_slug'] = (string) $tenant['slug'];

$redirect = (string) ($_POST['redirect_to'] ?? '/beheer/dashboard.php');
if (!str_starts_with($redirect, '/')) {
    $redirect = '/beheer/dashboard.php';
}
header('Location: ' . $redirect);
exit;
