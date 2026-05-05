<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once __DIR__ . '/includes/tenant_instellingen_db.php';

$tenantId = current_tenant_id();
if ($tenantId !== 2) {
    http_response_code(403);
    die('Reset is alleen toegestaan in tenant 2 (BusAI Testomgeving).');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    die('Alleen POST toegestaan.');
}

if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
    http_response_code(403);
    die('Sessie verlopen. Vernieuw de pagina.');
}

tenant_instellingen_bootstrap($pdo);

try {
    $pdo->beginTransaction();

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tableNames = [
        'ritregels',
        'ritten',
        'calculatie_regels',
        'calculaties',
        'sales_rit_dossiers',
        'factuur_regels',
        'facturen',
    ];

    $hasTableStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $hasTenantStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?'
    );

    foreach ($tableNames as $table) {
        $hasTableStmt->execute([$dbName, $table]);
        if ((int) $hasTableStmt->fetchColumn() === 0) {
            continue;
        }
        $hasTenantStmt->execute([$dbName, $table, 'tenant_id']);
        if ((int) $hasTenantStmt->fetchColumn() === 0) {
            continue;
        }
        $pdo->prepare("DELETE FROM `$table` WHERE tenant_id = ?")->execute([$tenantId]);
    }

    $pdo->commit();
    header('Location: bedrijfsinstellingen.php?reset_ok=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    die('Reset mislukt: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
