<?php
/**
 * Centrale auth + sessie. Laadt .env via dezelfde helpers als voorheen in env.php,
 * zodat dit bestand ook werkt als env.php (nog) ontbreekt op de server.
 */
declare(strict_types=1);

if (!function_exists('load_env_file')) {
    /**
     * @param mixed $path
     */
    function load_env_file($path): void
    {
        static $loadedPaths = [];
        $path = (string) $path;
        if ($path === '' || isset($loadedPaths[$path]) || !is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $loadedPaths[$path] = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        $loadedPaths[$path] = true;
    }
}

if (!function_exists('env_value')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function env_value(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

$erpRoot = __DIR__;
load_env_file($erpRoot . '/.env');

if (is_file($erpRoot . '/env.php')) {
    require_once $erpRoot . '/env.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/beheer/includes/db.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('auth_sanitize_redirect_path')) {
    function auth_sanitize_redirect_path(string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '/beheer/';
        }

        if (strpos($candidate, '://') !== false) {
            return '/beheer/';
        }

        if ($candidate[0] !== '/') {
            return '/beheer/';
        }

        if (strpos($candidate, '/beheer/') !== 0) {
            return '/beheer/';
        }

        return $candidate;
    }
}

if (!function_exists('auth_redirect_target')) {
    function auth_redirect_target(): string
    {
        $postedRedirect = (string) ($_POST['redirect_to'] ?? '');
        if ($postedRedirect !== '') {
            return auth_sanitize_redirect_path($postedRedirect);
        }

        $queryRedirect = (string) ($_GET['redirect_to'] ?? '');
        if ($queryRedirect !== '') {
            return auth_sanitize_redirect_path($queryRedirect);
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/beheer/');
        return auth_sanitize_redirect_path($requestUri);
    }
}

if (!function_exists('auth_login_url')) {
    function auth_login_url(string $requestedUri = ''): string
    {
        $target = auth_sanitize_redirect_path($requestedUri);
        return '/login.php?redirect_to=' . urlencode($target);
    }
}

if (!function_exists('auth_get_csrf_token')) {
    function auth_get_csrf_token(): string
    {
        if (empty($_SESSION['auth_csrf_token'])) {
            $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['auth_csrf_token'];
    }
}

if (!function_exists('auth_validate_csrf_token')) {
    function auth_validate_csrf_token(?string $token): bool
    {
        if (!isset($_SESSION['auth_csrf_token']) || $token === null) {
            return false;
        }

        return hash_equals((string) $_SESSION['auth_csrf_token'], $token);
    }
}

if (!function_exists('auth_note_failed_attempt')) {
    function auth_note_failed_attempt(): void
    {
        $now = time();
        if (!isset($_SESSION['auth_failed_at']) || ($now - (int) $_SESSION['auth_failed_at']) > 900) {
            $_SESSION['auth_failed_count'] = 0;
        }

        $_SESSION['auth_failed_count'] = ((int) ($_SESSION['auth_failed_count'] ?? 0)) + 1;
        $_SESSION['auth_failed_at'] = $now;
    }
}

if (!function_exists('auth_is_temporarily_blocked')) {
    function auth_is_temporarily_blocked(): bool
    {
        $count = (int) ($_SESSION['auth_failed_count'] ?? 0);
        $failedAt = (int) ($_SESSION['auth_failed_at'] ?? 0);
        if ($count < 5) {
            return false;
        }

        return (time() - $failedAt) < 300;
    }
}

if (!function_exists('auth_reset_failed_attempts')) {
    function auth_reset_failed_attempts(): void
    {
        unset($_SESSION['auth_failed_count'], $_SESSION['auth_failed_at']);
    }
}

if (!function_exists('perform_login')) {
    function perform_login(array $sessionData): void
    {
        session_regenerate_id(true);

        $_SESSION['ingelogd'] = true;
        $_SESSION['user_id'] = $sessionData['user_id'] ?? null;
        $_SESSION['user_email'] = $sessionData['user_email'] ?? null;
        $_SESSION['user_naam'] = $sessionData['user_naam'] ?? null;
        $_SESSION['rol'] = $sessionData['rol'] ?? null;
        $_SESSION['tenant_id'] = $sessionData['tenant_id'] ?? null;
        $_SESSION['tenant_slug'] = $sessionData['tenant_slug'] ?? null;
        $_SESSION['ingelogd_op'] = date('Y-m-d H:i:s');

        auth_reset_failed_attempts();
        header('Location: ' . auth_redirect_target());
        exit;
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string
    {
        return (string) ($_SESSION['rol'] ?? '');
    }
}

if (!function_exists('has_any_role')) {
    function has_any_role(array $roles): bool
    {
        $currentRole = current_user_role();
        if ($currentRole === '') {
            return false;
        }

        if ($currentRole === 'platform_owner') {
            return true;
        }

        foreach ($roles as $role) {
            if ($currentRole === (string) $role) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('require_role')) {
    function require_role(array $roles): void
    {
        if (has_any_role($roles)) {
            return;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $expectsJson = strpos($accept, 'application/json') !== false
            || strpos($contentType, 'application/json') !== false
            || $xrw === 'xmlhttprequest';

        http_response_code(403);
        if ($expectsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Geen toegang voor deze rol.',
            ]);
            exit;
        }

        echo "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px;'><strong>Geen toegang:</strong> je rol heeft geen rechten voor deze pagina.</div>";
        exit;
    }
}

if (!function_exists('attempt_user_login')) {
    function attempt_user_login(PDO $pdo, string $email, string $password): bool
    {
        $sql = "
            SELECT
                u.id, u.tenant_id, u.email, u.wachtwoord_hash, u.volledige_naam, u.rol, u.actief,
                t.slug AS tenant_slug,
                t.status AS tenant_status
            FROM users u
            LEFT JOIN tenants t ON t.id = u.tenant_id
            WHERE u.email = ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) $user['actief'] !== 1) {
            return false;
        }

        if (!password_verify($password, (string) $user['wachtwoord_hash'])) {
            return false;
        }

        $isPlatformOwner = ((string) $user['rol']) === 'platform_owner';
        $tenantIsActive = isset($user['tenant_status']) && ((string) $user['tenant_status']) === 'active';

        if (!$isPlatformOwner && !$tenantIsActive) {
            return false;
        }

        $tenantId = $user['tenant_id'] !== null ? (int) $user['tenant_id'] : current_tenant_id();
        $tenantSlug = $user['tenant_slug'] !== null ? (string) $user['tenant_slug'] : current_tenant_slug();

        $upd = $pdo->prepare('UPDATE users SET laatste_login_at = NOW() WHERE id = ?');
        $upd->execute([(int) $user['id']]);

        perform_login([
            'user_id' => (int) $user['id'],
            'user_email' => (string) $user['email'],
            'user_naam' => (string) $user['volledige_naam'],
            'rol' => (string) $user['rol'],
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenantSlug,
        ]);

        return true;
    }
}

if (!function_exists('attempt_legacy_login')) {
    function attempt_legacy_login(string $password): bool
    {
        $legacyPassword = (string) env_value('ADMIN_PASSWORD', '');
        if ($legacyPassword === '' || !hash_equals($legacyPassword, $password)) {
            return false;
        }

        perform_login([
            'user_id' => null,
            'user_email' => 'legacy-admin@local',
            'user_naam' => 'Legacy Admin',
            'rol' => 'tenant_admin',
            'tenant_id' => current_tenant_id(),
            'tenant_slug' => current_tenant_slug(),
        ]);

        return true;
    }
}

/**
 * Kantoor-login: controleer e-mail + wachtwoord zonder sessie te starten.
 * Retourneert gebruikersrij (incl. email_otp_enabled) of null.
 */
if (!function_exists('office_password_check_office_login')) {
    function office_password_check_office_login(PDO $pdo, string $email, string $password, string $tenantSlug): ?array
    {
        $email = strtolower(trim($email));
        $tenantSlug = trim($tenantSlug);
        if ($email === '' || $tenantSlug === '') {
            return null;
        }

        $sql = "
            SELECT
                u.id,
                u.tenant_id,
                u.email,
                u.wachtwoord_hash,
                u.volledige_naam,
                u.rol,
                u.actief,
                u.email_otp_enabled,
                t_ctx.id AS session_tenant_id,
                t_ctx.slug AS session_tenant_slug
            FROM users u
            INNER JOIN tenants t_ctx
                ON t_ctx.slug = ?
               AND t_ctx.status = 'active'
            WHERE u.email = ?
              AND u.actief = 1
              AND (
                    u.rol = 'platform_owner'
                    OR u.tenant_id = t_ctx.id
              )
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantSlug, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['wachtwoord_hash'])) {
            return null;
        }

        return $user;
    }
}

if (!function_exists('office_perform_login_from_office_row')) {
    /**
     * @param array<string,mixed> $user
     */
    function office_perform_login_from_office_row(PDO $pdo, array $user): void
    {
        $upd = $pdo->prepare('UPDATE users SET laatste_login_at = NOW() WHERE id = ?');
        $upd->execute([(int) $user['id']]);

        perform_login([
            'user_id' => (int) $user['id'],
            'user_email' => (string) $user['email'],
            'user_naam' => (string) $user['volledige_naam'],
            'rol' => (string) $user['rol'],
            'tenant_id' => (int) $user['session_tenant_id'],
            'tenant_slug' => (string) $user['session_tenant_slug'],
        ]);
    }
}

/**
 * Kantoor-login: e-mail + wachtwoord strikt gekoppeld aan een gekozen actieve tenant (slug).
 * Platform-eigenaar mag inloggen in elke actieve tenant; overige rollen alleen op eigen tenant_id.
 */
if (!function_exists('attempt_office_login')) {
    function attempt_office_login(PDO $pdo, string $email, string $password, string $tenantSlug): bool
    {
        $user = office_password_check_office_login($pdo, $email, $password, $tenantSlug);
        if ($user === null) {
            return false;
        }

        if ((int) ($user['email_otp_enabled'] ?? 0) === 1) {
            return false;
        }

        office_perform_login_from_office_row($pdo, $user);

        return true;
    }
}

/**
 * Nood-/legacy admin: alleen met expliciete tenant-slug (geen default-tenant uit omgeving).
 */
if (!function_exists('attempt_legacy_office_login')) {
    function attempt_legacy_office_login(PDO $pdo, string $password, string $tenantSlug): bool
    {
        $tenantSlug = trim($tenantSlug);
        if ($tenantSlug === '') {
            return false;
        }

        $legacyPassword = (string) env_value('ADMIN_PASSWORD', '');
        if ($legacyPassword === '' || !hash_equals($legacyPassword, $password)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT id, slug FROM tenants WHERE slug = ? AND status = ? LIMIT 1');
        $stmt->execute([$tenantSlug, 'active']);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tenant) {
            return false;
        }

        perform_login([
            'user_id' => null,
            'user_email' => 'legacy-admin@local',
            'user_naam' => 'Legacy Admin',
            'rol' => 'tenant_admin',
            'tenant_id' => (int) $tenant['id'],
            'tenant_slug' => (string) $tenant['slug'],
        ]);

        return true;
    }
}

$foutmelding = '';

if (isset($_GET['uitloggen'])) {
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
    header('Location: /beheer/');
    exit;
}

if (
    (!defined('AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP') || AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP !== true)
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['wachtwoord_poging'])
) {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $foutmelding = 'Sessie verlopen. Probeer opnieuw in te loggen.';
    } elseif (auth_is_temporarily_blocked()) {
        $foutmelding = 'Te veel mislukte pogingen. Wacht 5 minuten en probeer opnieuw.';
    } else {
        $password = trim((string) $_POST['wachtwoord_poging']);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        $ok = false;
        if ($email !== '') {
            $ok = attempt_user_login($pdo, $email, $password);
        }

        if (!$ok) {
            $ok = attempt_legacy_login($password);
        }

        if (!$ok) {
            auth_note_failed_attempt();
            $foutmelding = 'Inloggen mislukt. Controleer je gegevens.';
        }
    }
}

if (!isset($_SESSION['ingelogd']) || $_SESSION['ingelogd'] !== true) {
    if (defined('AUTH_SKIP_GUARD') && AUTH_SKIP_GUARD === true) {
        return;
    }

    $requestedUri = (string) ($_SERVER['REQUEST_URI'] ?? '/beheer/');
    header('Location: ' . auth_login_url($requestedUri));
    exit;
}
