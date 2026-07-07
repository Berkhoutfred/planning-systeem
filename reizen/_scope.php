<?php
declare(strict_types=1);

/**
 * Tenant-scheiding voor publieke busreis-catalogi.
 * Productie (tourplan.nl/reizen/) ≠ preview (busreizen-preview/) ≠ testtenant ERP.
 */

function busreis_scope_tenant_slug(string $context = 'public'): string
{
    $key = $context === 'preview' ? 'BUSREIS_PREVIEW_TENANT_SLUG' : 'BUSREIS_PUBLIC_TENANT_SLUG';
    $default = $context === 'preview' ? 'tourplan_testomgeving' : 'coachtravel';
    if (function_exists('env_value')) {
        return trim((string) env_value($key, $default));
    }

    return $default;
}

function busreis_scope_tenant_id(PDO $pdo, string $context = 'public'): int
{
    static $cache = [];
    $key = $context;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $slug = busreis_scope_tenant_slug($context);
    if ($slug === '') {
        $cache[$key] = 0;

        return 0;
    }

    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$slug]);
    $cache[$key] = (int) ($stmt->fetchColumn() ?: 0);

    return $cache[$key];
}

/** @return array{0:string,1:list<mixed>} SQL-fragment + params voor WHERE tenant_id = ? */
function busreis_scope_sql(PDO $pdo, string $alias = 'b', string $context = 'public'): array
{
    $tenantId = busreis_scope_tenant_id($pdo, $context);
    if ($tenantId <= 0) {
        return ['1=0', []];
    }

    return [$alias . '.tenant_id = ?', [$tenantId]];
}

function busreis_scope_guard(PDO $pdo, int $reisTenantId, string $context = 'public'): bool
{
    $allowed = busreis_scope_tenant_id($pdo, $context);

    return $allowed > 0 && $reisTenantId === $allowed;
}

function busreis_is_test_tenant_id(PDO $pdo, int $tenantId): bool
{
    if ($tenantId <= 0) {
        return false;
    }

    static $testId = null;
    if ($testId === null) {
        $testId = busreis_scope_tenant_id($pdo, 'preview');
    }

    return $tenantId === $testId;
}
