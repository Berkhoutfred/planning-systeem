<?php
// Bestand: beheer/includes/db.php
// Doel: centrale, veilige DB-bootstrap + tenant context (v1 basis)
//
// Ook bruikbaar zonder beveiliging.php (bijv. pdf_offerte in iframe, offerte.php):
// laad .env + optionele env.php, zelfde volgorde als beveiliging.php.

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

$__erpRoot = dirname(__DIR__, 2);
load_env_file($__erpRoot . '/.env');

$__envPhp = $__erpRoot . '/env.php';
if (is_file($__envPhp)) {
    require_once $__envPhp;
}

if (!function_exists('create_pdo_connection')) {
    function create_pdo_connection(): PDO
    {
        $host = env_value('DB_HOST', '127.0.0.1');
        $db = env_value('DB_NAME', 'erp');
        $user = env_value('DB_USER', 'root');
        $pass = env_value('DB_PASS', '');
        $charset = env_value('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $db, $charset);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }
}

if (!function_exists('resolve_tenant_context')) {
    function resolve_tenant_context(PDO $pdo): array
    {
        $defaultSlug = trim((string) env_value('TENANT_DEFAULT_SLUG', 'pilot_transport'));
        if ($defaultSlug === '') {
            $defaultSlug = 'pilot_transport';
        }

        $sessionTenantId = null;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id']) && is_numeric($_SESSION['tenant_id'])) {
            $sessionTenantId = (int) $_SESSION['tenant_id'];
        }

        // 1) Probeer sessie-tenant als die bestaat
        if ($sessionTenantId !== null) {
            $stmt = $pdo->prepare('SELECT id, slug, naam, status FROM tenants WHERE id = ? LIMIT 1');
            $stmt->execute([$sessionTenantId]);
            $tenant = $stmt->fetch();
            if ($tenant && $tenant['status'] === 'active') {
                return [
                    'id' => (int) $tenant['id'],
                    'slug' => (string) $tenant['slug'],
                    'naam' => (string) $tenant['naam'],
                ];
            }
        }

        // 2) Anders default slug uit .env
        $stmt = $pdo->prepare('SELECT id, slug, naam, status FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$defaultSlug]);
        $tenant = $stmt->fetch();
        if ($tenant && $tenant['status'] === 'active') {
            return [
                'id' => (int) $tenant['id'],
                'slug' => (string) $tenant['slug'],
                'naam' => (string) $tenant['naam'],
            ];
        }

        // 3) Fallback: eerste actieve tenant
        $stmt = $pdo->query("SELECT id, slug, naam FROM tenants WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        $tenant = $stmt->fetch();
        if ($tenant) {
            return [
                'id' => (int) $tenant['id'],
                'slug' => (string) $tenant['slug'],
                'naam' => (string) $tenant['naam'],
            ];
        }

        throw new RuntimeException('Geen actieve tenant gevonden in tabel tenants.');
    }
}

if (!function_exists('current_tenant_id')) {
    function current_tenant_id(): int
    {
        global $appTenantContext;
        return (int) ($appTenantContext['id'] ?? 0);
    }
}

if (!function_exists('current_tenant_slug')) {
    function current_tenant_slug(): string
    {
        global $appTenantContext;
        return (string) ($appTenantContext['slug'] ?? '');
    }
}

if (!function_exists('current_tenant_name')) {
    function current_tenant_name(): string
    {
        global $appTenantContext;
        return (string) ($appTenantContext['naam'] ?? '');
    }
}

try {
    // Backward compatible: bestaande code verwacht deze variabele.
    $pdo = create_pdo_connection();
    $appTenantContext = resolve_tenant_context($pdo);
} catch (Throwable $e) {
    // Vermijd het lekken van gevoelige DB-details in productie.
    throw new RuntimeException('Database initialisatie mislukt.', 0, $e);
}

require_once __DIR__ . '/tenant_crypto.php';
require_once __DIR__ . '/tenant_secrets.php';
require_once __DIR__ . '/tenant_settings.php';

// GEEN afsluitende tag hieronder!