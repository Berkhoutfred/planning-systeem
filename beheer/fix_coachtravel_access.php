<?php
declare(strict_types=1);

/**
 * Eenmalig: Coach Travel mag alleen dagtochten beheren — geen taxi-ERP/testomgeving.
 * CLI: php beheer/fix_coachtravel_access.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI.');
}

require __DIR__ . '/includes/db.php';

const COACH_SLUG = 'coachtravel';
const ERP_MODULES = ['basis', 'planbord', 'evenementen', 'vaste_ritten', 'social_media', 'busreizen', 'dagtochten'];

$stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
$stmt->execute([COACH_SLUG]);
$coachId = (int) ($stmt->fetchColumn() ?: 0);
if ($coachId <= 0) {
    fwrite(STDERR, "Tenant '" . COACH_SLUG . "' niet gevonden.\n");
    exit(1);
}

$pdo->prepare('INSERT INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, \'coopdagtochten\', 1)
               ON DUPLICATE KEY UPDATE actief = 1')
    ->execute([$coachId]);

$off = $pdo->prepare('UPDATE tenant_modules SET actief = 0 WHERE tenant_id = ? AND module_code = ?');
foreach (ERP_MODULES as $mod) {
    $off->execute([$coachId, $mod]);
}

$mods = $pdo->prepare('SELECT module_code FROM tenant_modules WHERE tenant_id = ? AND actief = 1 ORDER BY module_code');
$mods->execute([$coachId]);

echo "OK Coach Travel (#{$coachId}) — actieve modules: " . implode(', ', $mods->fetchAll(PDO::FETCH_COLUMN)) . "\n";
