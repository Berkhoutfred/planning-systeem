<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/reis_netwerk.php';

$tenantId = current_tenant_id();

$actieve_modules = [];
if (isset($pdo) && $pdo instanceof PDO) {
    $stmtMod = $pdo->prepare('SELECT module_code FROM tenant_modules WHERE tenant_id = ? AND actief = 1');
    $stmtMod->execute([$tenantId]);
    $actieve_modules = $stmtMod->fetchAll(PDO::FETCH_COLUMN);
}

$reisCtx = reis_hybrid_context($pdo, $tenantId, $actieve_modules);

$eigenTenantId = (int) $reisCtx['eigen_tenant_id'];
$coopLeidingTenantId = $reisCtx['coop_leiding_tenant_id'];
$isHybrideModus = (bool) $reisCtx['is_hybride'];
$magEigenReizenBewerken = (bool) $reisCtx['mag_eigen_bewerken'];
$magCoopReizenBewerken = (bool) $reisCtx['mag_coop_bewerken'];

/** Primair tenant voor nieuwe/eigen reizen. */
$dataTenantId = $reisCtx['has_eigen']
    ? $eigenTenantId
    : reis_netwerk_leiding_tenant_id($pdo, $tenantId, $actieve_modules);

$magReizenBewerken = $magEigenReizenBewerken || $magCoopReizenBewerken;

$isCoopPartner = $reisCtx['has_coop']
    && !$magEigenReizenBewerken
    && !$magCoopReizenBewerken;
