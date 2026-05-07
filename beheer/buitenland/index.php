<?php
declare(strict_types=1);

/**
 * Buitenland: doorverwijzing naar geünificeerde calculatie (maken.php?module=buitenland).
 */

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);

header('Location: ../calculatie/maken.php?module=buitenland', true, 302);
exit;
