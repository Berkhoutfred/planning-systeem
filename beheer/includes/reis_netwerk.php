<?php
declare(strict_types=1);

/**
 * Helper voor Coöp Dagtochten — reis_netwerken + reis_netwerk_partners.
 */

require_once dirname(__DIR__, 2) . '/reizen/_scope.php';

if (!function_exists('reis_module_codes')) {
    /** @return list<string> */
    function reis_module_codes(): array
    {
        return ['busreizen', 'dagtochten', 'coopdagtochten'];
    }
}

if (!function_exists('heeft_reizen_module')) {
    function heeft_reizen_module(array $actieve_modules): bool
    {
        foreach (reis_module_codes() as $code) {
            if (in_array($code, $actieve_modules, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('heeft_coopdagtochten_module')) {
    function heeft_coopdagtochten_module(array $actieve_modules): bool
    {
        return in_array('coopdagtochten', $actieve_modules, true);
    }
}

if (!function_exists('reis_netwerk_ensure_tables')) {
    function reis_netwerk_ensure_tables(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS reis_netwerken (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                naam VARCHAR(150) NOT NULL,
                slug VARCHAR(80) NOT NULL,
                leiding_tenant_id INT UNSIGNED NOT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                bijgewerkt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_slug (slug),
                KEY idx_leiding (leiding_tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS reis_netwerk_partners (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                netwerk_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                rol ENUM('leider','partner') NOT NULL DEFAULT 'partner',
                mag_bewerken TINYINT(1) NOT NULL DEFAULT 0,
                mag_bekijken TINYINT(1) NOT NULL DEFAULT 1,
                partner_label VARCHAR(100) DEFAULT NULL,
                sort_order SMALLINT NOT NULL DEFAULT 0,
                aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_netwerk_tenant (netwerk_id, tenant_id),
                KEY idx_tenant (tenant_id),
                KEY idx_netwerk (netwerk_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $done = true;
    }
}

if (!function_exists('reis_netwerk_partner_voor_tenant')) {
    /**
     * @return array<string, mixed>|null
     */
    function reis_netwerk_partner_voor_tenant(PDO $pdo, int $tenantId): ?array
    {
        if ($tenantId <= 0) {
            return null;
        }

        reis_netwerk_ensure_tables($pdo);

        $stmt = $pdo->prepare(
            'SELECT p.*, n.naam AS netwerk_naam, n.slug AS netwerk_slug, n.leiding_tenant_id, n.status AS netwerk_status
             FROM reis_netwerk_partners p
             JOIN reis_netwerken n ON n.id = p.netwerk_id
             WHERE p.tenant_id = ? AND n.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('reis_netwerk_is_leider')) {
    function reis_netwerk_is_leider(PDO $pdo, int $tenantId): bool
    {
        $partner = reis_netwerk_partner_voor_tenant($pdo, $tenantId);

        return $partner !== null && (string) ($partner['rol'] ?? '') === 'leider';
    }
}

if (!function_exists('reis_hybrid_context')) {
    /**
     * Context voor hybride scherm: eigen dagtochten + coöp-reizen netwerk-leider.
     *
     * @return array{
     *   tenant_id:int,
     *   eigen_tenant_id:int,
     *   coop_leiding_tenant_id:int|null,
     *   has_coop:bool,
     *   has_eigen:bool,
     *   is_hybride:bool,
     *   mag_eigen_bewerken:bool,
     *   mag_coop_bewerken:bool,
     *   coop_partner:array<string,mixed>|null
     * }
     */
    function reis_hybrid_context(PDO $pdo, int $tenantId, array $actieve_modules): array
    {
        $partner = reis_netwerk_partner_voor_tenant($pdo, $tenantId);
        $hasCoop = heeft_coopdagtochten_module($actieve_modules) && $partner !== null;
        $hasEigen = in_array('dagtochten', $actieve_modules, true)
            || in_array('busreizen', $actieve_modules, true);
        $isCoopLeider = $hasCoop && $partner !== null && (string) ($partner['rol'] ?? '') === 'leider';
        $isNetwerkLeider = reis_netwerk_is_leider($pdo, $tenantId);

        return [
            'tenant_id' => $tenantId,
            'eigen_tenant_id' => $tenantId,
            'coop_leiding_tenant_id' => $hasCoop ? (int) $partner['leiding_tenant_id'] : null,
            'has_coop' => $hasCoop,
            'has_eigen' => $hasEigen,
            'is_hybride' => $hasCoop && $hasEigen
                && $partner !== null
                && (string) ($partner['rol'] ?? '') === 'partner',
            'mag_eigen_bewerken' => $hasEigen || $isCoopLeider || $isNetwerkLeider,
            'mag_coop_bewerken' => $hasCoop && (int) ($partner['mag_bewerken'] ?? 0) === 1,
            'coop_partner' => $partner,
        ];
    }
}

if (!function_exists('reis_toegestane_tenant_ids')) {
    /** @param array<string, mixed> $ctx */
    function reis_toegestane_tenant_ids(array $ctx, ?PDO $pdo = null): array
    {
        $ids = [];
        if (!empty($ctx['has_eigen'])) {
            $ids[] = (int) $ctx['eigen_tenant_id'];
        }
        if (!empty($ctx['has_coop']) && !empty($ctx['coop_leiding_tenant_id'])) {
            $ids[] = (int) $ctx['coop_leiding_tenant_id'];
        }
        if ($ids === [] && !empty($ctx['coop_leiding_tenant_id'])) {
            $ids[] = (int) $ctx['coop_leiding_tenant_id'];
        }
        if ($ids === []) {
            $ids[] = (int) $ctx['tenant_id'];
        }

        $ids = array_values(array_unique($ids));

        $isOwner = function_exists('current_user_role') && current_user_role() === 'platform_owner';
        if ($isOwner && $pdo instanceof PDO) {
            $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active' ORDER BY id ASC");
            $all = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            return $all !== [] ? $all : $ids;
        }

        if (!$isOwner && $pdo instanceof PDO) {
            $homeId = (int) ($ctx['tenant_id'] ?? 0);
            $ids = array_values(array_filter(
                $ids,
                static fn(int $tid): bool => $tid === $homeId || !busreis_is_test_tenant_id($pdo, $tid)
            ));
        }

        if ($ids === [] && !empty($ctx['tenant_id'])) {
            $ids[] = (int) $ctx['tenant_id'];
        }

        return $ids;
    }
}

if (!function_exists('reis_mag_bewerken_voor_tenant')) {
    /** @param array<string, mixed> $ctx */
    function reis_mag_bewerken_voor_tenant(array $ctx, int $reisTenantId): bool
    {
        if ($reisTenantId <= 0) {
            return false;
        }

        // Platform owner: status wijzigen op alle zichtbare reizen (ook andere tenants).
        if (function_exists('current_user_role') && current_user_role() === 'platform_owner') {
            return true;
        }

        // Eigen tenant-data: Coach Travel-leider, Berkhout met eigen reizen, etc.
        if (function_exists('current_tenant_id') && function_exists('current_user_role')) {
            $rol = current_user_role();
            if (in_array($rol, ['tenant_admin', 'planner_user'], true)
                && $reisTenantId === current_tenant_id()) {
                return true;
            }
        }

        if ($reisTenantId === (int) $ctx['eigen_tenant_id']) {
            return !empty($ctx['mag_eigen_bewerken']);
        }
        if (!empty($ctx['coop_leiding_tenant_id']) && $reisTenantId === (int) $ctx['coop_leiding_tenant_id']) {
            return !empty($ctx['mag_coop_bewerken']);
        }

        return false;
    }
}

if (!function_exists('reis_ophaal_met_toegang')) {
    /**
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    function reis_ophaal_met_toegang(PDO $pdo, array $ctx, int $reisId): ?array
    {
        if ($reisId <= 0) {
            return null;
        }

        $allowed = reis_toegestane_tenant_ids($ctx, $pdo);
        if ($allowed === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $stmt = $pdo->prepare(
            "SELECT * FROM busreizen WHERE id = ? AND tenant_id IN ($placeholders) LIMIT 1"
        );
        $stmt->execute(array_merge([$reisId], $allowed));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('reis_lijst_where')) {
    /**
     * @param array<string, mixed> $ctx
     * @return array{0:list<string>,1:array<string, mixed>}
     */
    function reis_lijst_where(array $ctx, string $filterType, string $filterStatus, ?PDO $pdo = null, string $alias = 'b'): array
    {
        $allowed = reis_toegestane_tenant_ids($ctx, $pdo);
        $placeholders = [];
        $params = [];
        foreach ($allowed as $i => $tid) {
            $key = ':tid' . $i;
            $placeholders[] = $key;
            $params[$key] = $tid;
        }

        $where = [$alias . '.tenant_id IN (' . implode(',', $placeholders) . ')'];
        if (in_array($filterType, ['dagtocht', 'meerdaags'], true)) {
            $where[] = $alias . '.type = :type';
            $params[':type'] = $filterType;
        }
        if ($filterStatus === 'actief') {
            $where[] = $alias . ".status != 'archief'";
        } elseif (in_array($filterStatus, ['concept', 'gepubliceerd', 'vol', 'archief'], true)) {
            $where[] = $alias . '.status = :status';
            $params[':status'] = $filterStatus;
        }

        return [$where, $params];
    }
}

if (!function_exists('reis_bron_label')) {
    /** @param array<string, mixed> $ctx */
    function reis_bron_label(array $ctx, int $reisTenantId): string
    {
        if (!empty($ctx['is_hybride']) && !empty($ctx['coop_leiding_tenant_id'])
            && $reisTenantId === (int) $ctx['coop_leiding_tenant_id']) {
            return 'coop';
        }

        return 'eigen';
    }
}

if (!function_exists('reis_netwerk_mag_bewerken')) {
    function reis_netwerk_mag_bewerken(PDO $pdo, int $tenantId, array $actieve_modules): bool
    {
        $ctx = reis_hybrid_context($pdo, $tenantId, $actieve_modules);

        return $ctx['mag_eigen_bewerken'] || $ctx['mag_coop_bewerken'];
    }
}

if (!function_exists('reis_netwerk_leiding_tenant_id')) {
    function reis_netwerk_leiding_tenant_id(PDO $pdo, int $tenantId, array $actieve_modules): int
    {
        if (!heeft_coopdagtochten_module($actieve_modules)) {
            return $tenantId;
        }

        $partner = reis_netwerk_partner_voor_tenant($pdo, $tenantId);

        return $partner ? (int) $partner['leiding_tenant_id'] : $tenantId;
    }
}

if (!function_exists('coop_partner_wp_document_roots')) {
    /** @return array<string, string> hostname => absolute WP root */
    function coop_partner_wp_document_roots(): array
    {
        return [
            'berkhoutreizen.nl' => '/home/u473845697/domains/berkhoutreizen.nl/public_html',
        ];
    }
}

if (!function_exists('coop_resolve_wp_cli')) {
    function coop_resolve_wp_cli(): string
    {
        foreach (['/usr/local/bin/wp', '/usr/bin/wp'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'wp';
    }
}

if (!function_exists('coop_spawn_shell')) {
    function coop_spawn_shell(string $cmd, bool $background = false): void
    {
        if ($background) {
            $wrapped = 'nohup bash -c ' . escapeshellarg($cmd) . ' > /dev/null 2>&1 &';
            if (function_exists('proc_open')) {
                $descriptors = [
                    0 => ['file', '/dev/null', 'r'],
                    1 => ['file', '/dev/null', 'w'],
                    2 => ['file', '/dev/null', 'w'],
                ];
                $proc = @proc_open($wrapped, $descriptors, $pipes);
                if (is_resource($proc)) {
                    proc_close($proc);
                }

                return;
            }
            if (function_exists('exec')) {
                exec($wrapped);

                return;
            }

            return;
        }

        if (function_exists('exec')) {
            exec($cmd);
        }
    }
}

if (!function_exists('coop_purge_partner_wp_cache')) {
    function coop_purge_partner_wp_cache(string $wpRoot): void
    {
        if ($wpRoot === '' || !is_dir($wpRoot)) {
            return;
        }

        $wpCli = coop_resolve_wp_cli();
        $eval = escapeshellarg(
            'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \'_transient_berkhout_coop%\' OR option_name LIKE \'_transient_timeout_berkhout_coop%\'");'
        );
        $cmd = 'cd ' . escapeshellarg($wpRoot)
            . ' && ' . escapeshellarg($wpCli) . ' transient delete berkhout_coop_reizen_list 2>/dev/null'
            . ' && ' . escapeshellarg($wpCli) . ' eval ' . $eval . ' 2>/dev/null'
            . ' && ' . escapeshellarg($wpCli) . ' litespeed-purge all 2>/dev/null';

        // Achtergrond: wp-cli + LiteSpeed duurt ~8s en blokkeerde publiceren/depubliceren in de browser.
        coop_spawn_shell($cmd, true);
    }
}

if (!function_exists('coop_invalidate_partner_site_caches')) {
    /** Leeg partner-websites na wijziging coöp/eigen dagtocht. */
    function coop_invalidate_partner_site_caches(PDO $pdo, int $reisTenantId): void
    {
        if ($reisTenantId <= 0) {
            return;
        }

        reis_netwerk_ensure_tables($pdo);

        $stmt = $pdo->prepare(
            'SELECT DISTINCT p2.website_url
             FROM reis_netwerken n
             JOIN reis_netwerk_partners p ON p.netwerk_id = n.id
             JOIN reis_netwerk_partners p2 ON p2.netwerk_id = n.id
             WHERE n.status = "active"
               AND p.tenant_id = ?
               AND p2.website_url IS NOT NULL
               AND TRIM(p2.website_url) <> ""'
        );
        $stmt->execute([$reisTenantId]);

        $roots = coop_partner_wp_document_roots();
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $url) {
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            if ($host !== '' && isset($roots[$host])) {
                coop_purge_partner_wp_cache($roots[$host]);
            }
        }
    }
}
