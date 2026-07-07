<?php
declare(strict_types=1);
/**
 * Eenmalig: scheid platform-owner (sandbox) van klant-tenants.
 *
 * CLI: php beheer/setup_tenant_rollen.php
 *
 * - Platform owner → home tenant = testomgeving (sandbox, geen klantwerk)
 * - Berkhout Busreizen → gewone klant-tenant
 * - Coach Travel → partner-tenant (ongewijzigd)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI.');
}

require __DIR__ . '/includes/db.php';

const SANDBOX_SLUG = 'testomgeving';
const BERKHOUT_SLUG = 'berkhoutreizen';
const BERKHOUT_ADMIN_EMAIL = 'administratie@taxiberkhout.nl';

$fixes = [];

$sandbox = $pdo->prepare('SELECT id, naam FROM tenants WHERE slug = ? LIMIT 1');
$sandbox->execute([SANDBOX_SLUG]);
$sandboxRow = $sandbox->fetch(PDO::FETCH_ASSOC);
if (!$sandboxRow) {
    exit("Sandbox-tenant '" . SANDBOX_SLUG . "' niet gevonden.\n");
}
$sandboxId = (int) $sandboxRow['id'];

$berkhout = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
$berkhout->execute([BERKHOUT_SLUG]);
$berkhoutId = (int) ($berkhout->fetchColumn() ?: 0);
if ($berkhoutId <= 0) {
    exit("Tenant '" . BERKHOUT_SLUG . "' niet gevonden.\n");
}

$pdo->prepare("UPDATE tenants SET naam = 'Tourplan Sandbox' WHERE id = ? AND naam = 'Testomgeving'")
    ->execute([$sandboxId]);
$fixes[] = 'Sandbox-tenant hernoemd naar Tourplan Sandbox (indien nodig)';

$stmt = $pdo->query("SELECT id, email, tenant_id FROM users WHERE rol = 'platform_owner' AND actief = 1");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $owner) {
    if ((int) $owner['tenant_id'] === $sandboxId) {
        continue;
    }
    $upd = $pdo->prepare('UPDATE users SET tenant_id = ? WHERE id = ?');
    $upd->execute([$sandboxId, (int) $owner['id']]);
    $fixes[] = 'Platform owner ' . $owner['email'] . ' → sandbox-tenant';
}

$adminStmt = $pdo->prepare('SELECT id, tenant_id FROM users WHERE LOWER(email) = ? AND actief = 1 LIMIT 1');
$adminStmt->execute([strtolower(BERKHOUT_ADMIN_EMAIL)]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if ($admin && (int) $admin['tenant_id'] !== $berkhoutId) {
    $pdo->prepare('UPDATE users SET tenant_id = ?, rol = ? WHERE id = ?')
        ->execute([$berkhoutId, 'tenant_admin', (int) $admin['id']]);
    $fixes[] = BERKHOUT_ADMIN_EMAIL . ' → Berkhout Busreizen (tenant_admin)';
}

echo "Tenant-rollen bijgewerkt:\n";
foreach ($fixes as $fix) {
    echo "  - $fix\n";
}
if ($fixes === []) {
    echo "  (alles stond al goed)\n";
}

echo "\nInloggen:\n";
echo "  Platform beheer → platform_owner account (sandbox: " . SANDBOX_SLUG . ")\n";
echo "  Berkhout dagelijks → " . BERKHOUT_ADMIN_EMAIL . " (tenant: " . BERKHOUT_SLUG . ")\n";
