<?php
declare(strict_types=1);

/**
 * Centrale module-toegang per tenant.
 * Menu (header.php) verbergt items; deze helpers blokkeren ook directe URL's.
 */

if (!function_exists('tenant_actieve_modules')) {
    /** @return list<string> */
    function tenant_actieve_modules(PDO $pdo, int $tenantId): array
    {
        static $cache = [];
        if ($tenantId <= 0) {
            return [];
        }
        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }

        $stmt = $pdo->prepare('SELECT module_code FROM tenant_modules WHERE tenant_id = ? AND actief = 1');
        $stmt->execute([$tenantId]);
        $cache[$tenantId] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $cache[$tenantId];
    }
}

if (!function_exists('tenant_heeft_module')) {
    function tenant_heeft_module(PDO $pdo, int $tenantId, string $code): bool
    {
        return in_array($code, tenant_actieve_modules($pdo, $tenantId), true);
    }
}

if (!function_exists('tenant_heeft_een_van_modules')) {
    /** @param list<string> $codes */
    function tenant_heeft_een_van_modules(PDO $pdo, int $tenantId, array $codes): bool
    {
        foreach ($codes as $code) {
            if (tenant_heeft_module($pdo, $tenantId, $code)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vereis_tenant_module')) {
    /**
     * Blokkeert pagina wanneer module ontbreekt (behalve platform_owner).
     *
     * @param list<string>|string $codes
     */
    function vereis_tenant_module(PDO $pdo, int $tenantId, string|array $codes, string $redirect = 'dashboard.php'): void
    {
        if (function_exists('current_user_role') && current_user_role() === 'platform_owner') {
            return;
        }

        $codes = is_array($codes) ? $codes : [$codes];
        if (tenant_heeft_een_van_modules($pdo, $tenantId, $codes)) {
            return;
        }

        $qs = http_build_query(['msg' => 'geen_module', 'mod' => $codes[0] ?? '']);
        header('Location: ' . $redirect . ($qs !== '' ? '?' . $qs : ''));
        exit;
    }
}

if (!function_exists('tenant_reizen_portaal_slugs')) {
    /** @return list<string> */
    function tenant_reizen_portaal_slugs(): array
    {
        $raw = function_exists('env_value')
            ? (string) env_value('REIZEN_PORTAAL_TENANT_SLUGS', 'coachtravel')
            : 'coachtravel';

        return array_values(array_filter(array_map(
            static fn(string $s): string => strtolower(trim($s)),
            explode(',', $raw)
        )));
    }
}

if (!function_exists('tenant_is_reizen_portaal')) {
    /**
     * Tenant mag uitsluitend dagtochten/busreizen beheren — geen taxi-ERP, planbord of testomgeving.
     */
    function tenant_is_reizen_portaal(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }
        if (function_exists('current_user_role') && current_user_role() === 'platform_owner') {
            return false;
        }

        $stmt = $pdo->prepare('SELECT slug FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $slug = strtolower((string) ($stmt->fetchColumn() ?: ''));
        if ($slug !== '' && in_array($slug, tenant_reizen_portaal_slugs(), true)) {
            return true;
        }

        $mods = tenant_actieve_modules($pdo, $tenantId);
        $reizenMods = ['coopdagtochten', 'busreizen', 'dagtochten'];
        $erpMods = ['basis', 'planbord', 'evenementen', 'vaste_ritten', 'social_media'];

        $hasReizen = false;
        foreach ($reizenMods as $code) {
            if (in_array($code, $mods, true)) {
                $hasReizen = true;
                break;
            }
        }
        if (!$hasReizen) {
            return false;
        }

        foreach ($erpMods as $code) {
            if (in_array($code, $mods, true)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('beheer_reizen_portaal_home')) {
    function beheer_reizen_portaal_home(): string
    {
        return '/beheer/reizen/index.php';
    }
}

if (!function_exists('login_post_auth_redirect')) {
    function login_post_auth_redirect(PDO $pdo, string $requested): string
    {
        $requested = auth_sanitize_redirect_path($requested);
        $tenantId = function_exists('current_tenant_id') ? current_tenant_id() : 0;
        if ($tenantId <= 0 || !tenant_is_reizen_portaal($pdo, $tenantId)) {
            return $requested;
        }
        if (str_contains($requested, '/beheer/reizen/')) {
            return $requested;
        }

        return beheer_reizen_portaal_home();
    }
}

if (!function_exists('beheer_handhaaf_reizen_portaal')) {
    /**
     * Blokkeert directe URL's naar taxi-ERP voor reizen-only tenants (Coach Travel e.d.).
     */
    function beheer_handhaaf_reizen_portaal(PDO $pdo): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (!str_contains($uri, '/beheer/')) {
            return;
        }

        $tenantId = function_exists('current_tenant_id') ? current_tenant_id() : 0;
        if ($tenantId <= 0 || !tenant_is_reizen_portaal($pdo, $tenantId)) {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '' || !str_contains($script, '/beheer/')) {
            return;
        }

        $rel = substr($script, (int) strpos($script, '/beheer/') + strlen('/beheer/'));
        $allowedPrefixes = ['reizen/'];
        $allowedExact = [
            'reizen/index.php',
            'reizen/bewerken.php',
            'reizen/boekingen.php',
            'reizen/passagierslijst_pdf.php',
            'mijn_instellingen.php',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return;
            }
        }
        if (in_array($rel, $allowedExact, true)) {
            return;
        }

        header('Location: ' . beheer_reizen_portaal_home() . '?msg=geen_toegang');
        exit;
    }
}
