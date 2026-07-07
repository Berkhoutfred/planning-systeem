<?php
declare(strict_types=1);

require_once __DIR__ . '/../beveiliging.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/module_access.php';

$tenantId = current_tenant_id();
if ($tenantId > 0 && tenant_is_reizen_portaal($pdo, $tenantId)) {
    header('Location: ' . beheer_reizen_portaal_home(), true, 302);
    exit;
}

header('Location: dashboard.php', true, 302);
exit;
