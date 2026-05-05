<?php
declare(strict_types=1);

/**
 * Kantoor-voordeur: login uitsluitend tenant-strikt (sessie-tenant = gekozen actieve tenant).
 * POST-afhandeling staat hier i.p.v. in beveiliging.php (zie AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP).
 */
define('AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP', true);
define('AUTH_SKIP_GUARD', true);
require_once __DIR__ . '/beveiliging.php';

if (isset($_SESSION['ingelogd']) && $_SESSION['ingelogd'] === true) {
    header('Location: /beheer/');
    exit;
}

$foutmelding = $foutmelding ?? '';

$csrfToken = auth_get_csrf_token();
$redirectInput = (string) ($_POST['redirect_to'] ?? ($_GET['redirect_to'] ?? '/beheer/'));
$redirectTo = auth_sanitize_redirect_path($redirectInput);
$logoWebPath = '/assets/brand/busai-logo-horizontal.png';
$logoFsPath = __DIR__ . $logoWebPath;
$hasLoginLogo = is_file($logoFsPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wachtwoord_poging'])) {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $foutmelding = 'Sessie verlopen. Probeer opnieuw in te loggen.';
    } elseif (auth_is_temporarily_blocked()) {
        $foutmelding = 'Te veel mislukte pogingen. Wacht 5 minuten en probeer opnieuw.';
    } else {
        $password = trim((string) $_POST['wachtwoord_poging']);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '') {
            $foutmelding = 'Vul je e-mailadres in.';
            auth_note_failed_attempt();
        } else {
            // Tenant automatisch bepalen op basis van uniek e-mailadres.
            $tenantStmt = $pdo->prepare("
                SELECT t.slug
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE LOWER(TRIM(u.email)) = ?
                  AND u.actief = 1
                  AND t.status = 'active'
                ORDER BY t.id ASC
                LIMIT 2
            ");
            $tenantStmt->execute([$email]);
            $tenantRows = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($tenantRows) === 0) {
                $foutmelding = 'Inloggen mislukt. Controleer e-mail en wachtwoord.';
                auth_note_failed_attempt();
            } elseif (count($tenantRows) > 1) {
                $foutmelding = 'Dit e-mailadres bestaat in meerdere omgevingen. Gebruik een uniek account of neem contact op met beheer.';
                auth_note_failed_attempt();
            } else {
                $tenantSlug = (string) ($tenantRows[0]['slug'] ?? '');
                $ok = attempt_office_login($pdo, $email, $password, $tenantSlug);

                if (!$ok) {
                    auth_note_failed_attempt();
                    $foutmelding = 'Inloggen mislukt. Controleer e-mail en wachtwoord.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - BusAI Beheer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        busai: {
                            primary: '#0B3E69',
                            primaryHover: '#0A3459',
                            surface: '#F3F6F8'
                        }
                    }
                }
            }
        };
    </script>
</head>
<body class="min-h-screen bg-busai-surface text-slate-900 antialiased">
    <main class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60 p-6 sm:p-8">
            <div class="flex flex-col items-center text-center mb-6">
                <?php if ($hasLoginLogo): ?>
                    <img
                        src="<?php echo h($logoWebPath); ?>"
                        alt="BusAI"
                        class="h-12 w-auto mb-4"
                        loading="eager"
                    >
                <?php endif; ?>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Inloggen op BusAI</h1>
                <p class="mt-2 text-sm text-slate-600">Log in op het BusAI beheerportaal.</p>
            </div>

            <?php if ($foutmelding !== ''): ?>
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <?php echo h($foutmelding); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" autocomplete="off" class="space-y-4">
                <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">E-mailadres</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="bijv. admin@bedrijf.nl"
                        required
                        autofocus
                        autocomplete="username"
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-busai-primary focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/15"
                    >
                </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Wachtwoord</label>
                        <input
                            type="password"
                            id="password"
                            name="wachtwoord_poging"
                            placeholder="Wachtwoord"
                            required
                            autocomplete="current-password"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-busai-primary focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/15"
                        >
                    </div>

                    <button
                        type="submit"
                        class="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-busai-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-busai-primaryHover focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/20"
                    >
                        Inloggen
                    </button>
                </form>

                <p class="mt-4 text-xs text-slate-500">
                    Gebruik je account e-mailadres. De omgeving wordt automatisch bepaald.
                </p>
        </div>
    </main>
</body>
</html>
