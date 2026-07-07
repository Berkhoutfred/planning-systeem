<?php
declare(strict_types=1);

/**
 * Harde tenant-isolatie: sessie moet altijd kloppen met users.tenant_id.
 * Sandbox (testomgeving) = home tenant platform owner. Klant-tenants (Berkhout, Coach Travel) apart.
 */

require_once __DIR__ . '/../../reizen/_scope.php';

if (!function_exists('auth_test_tenant_id')) {
    function auth_test_tenant_id(PDO $pdo): int
    {
        return busreis_scope_tenant_id($pdo, 'preview');
    }
}

if (!function_exists('auth_force_logout')) {
    function auth_force_logout(string $reason = 'sessie_ongeldig'): never
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
        header('Location: /login.php?uitgelogd=1&reden=' . rawurlencode($reason));
        exit;
    }
}

if (!function_exists('auth_user_home_tenant')) {
    /** @return array{id:int,slug:string,rol:string,actief:int}|null */
    function auth_user_home_tenant(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT u.tenant_id AS id, u.rol, u.actief, t.slug
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('auth_may_use_tenant')) {
    function auth_may_use_tenant(PDO $pdo, string $rol, int $homeTenantId, int $requestedTenantId): bool
    {
        if ($requestedTenantId <= 0 || $homeTenantId <= 0) {
            return false;
        }

        if ($rol === 'platform_owner') {
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$requestedTenantId]);

            return (bool) $stmt->fetchColumn();
        }

        if ($requestedTenantId !== $homeTenantId) {
            return false;
        }

        $testTenantId = auth_test_tenant_id($pdo);
        if ($testTenantId > 0 && $requestedTenantId === $testTenantId && $homeTenantId !== $testTenantId) {
            return false;
        }

        return true;
    }
}

if (!function_exists('auth_handhaaf_tenant_sessie')) {
    /**
     * Elke beheer-request: sessie-tenant moet passen bij ingelogde gebruiker.
     */
    function auth_handhaaf_tenant_sessie(PDO $pdo): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $sessionTenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $sessionRol = function_exists('current_user_role') ? current_user_role() : '';

        if ($userId <= 0) {
            return;
        }

        $home = auth_user_home_tenant($pdo, $userId);
        if ($home === null || (int) $home['actief'] !== 1) {
            auth_force_logout('account_onbekend');
        }

        $homeTenantId = (int) $home['id'];
        $homeRol = (string) $home['rol'];
        $homeSlug = strtolower((string) $home['slug']);

        if ($sessionRol !== '' && $sessionRol !== $homeRol) {
            auth_force_logout('rol_klopt_niet');
        }

        if (!auth_may_use_tenant($pdo, $homeRol, $homeTenantId, $sessionTenantId)) {
            auth_force_logout('verkeerde_omgeving');
        }

        if ($homeRol !== 'platform_owner') {
            if (!function_exists('tenant_reizen_portaal_slugs')) {
                require_once __DIR__ . '/module_access.php';
            }
            if (in_array($homeSlug, tenant_reizen_portaal_slugs(), true)) {
                $stmt = $pdo->prepare('SELECT slug FROM tenants WHERE id = ? LIMIT 1');
                $stmt->execute([$sessionTenantId]);
                $sessionSlug = strtolower((string) ($stmt->fetchColumn() ?: ''));
                if ($sessionSlug !== $homeSlug) {
                    auth_force_logout('coachtravel_omgeving');
                }
            }
        }
    }
}
