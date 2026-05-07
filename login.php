<?php
declare(strict_types=1);

/**
 * Kantoor-voordeur: login uitsluitend tenant-strikt (sessie-tenant = gekozen actieve tenant).
 * POST-afhandeling staat hier i.p.v. in beveiliging.php (zie AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP).
 */
define('AUTH_DELEGATE_LOGIN_TO_LOGIN_PHP', true);
define('AUTH_SKIP_GUARD', true);
require_once __DIR__ . '/beveiliging.php';
require_once __DIR__ . '/beheer/includes/login_otp_support.php';

if (isset($_SESSION['ingelogd']) && $_SESSION['ingelogd'] === true) {
    header('Location: /beheer/');
    exit;
}

$foutmelding = $foutmelding ?? '';
$infoMelding = '';

$csrfToken = auth_get_csrf_token();
$redirectInput = (string) ($_POST['redirect_to'] ?? ($_GET['redirect_to'] ?? '/beheer/'));
$redirectTo = auth_sanitize_redirect_path($redirectInput);
$logoWebPath = '/assets/brand/busai-logo-horizontal.png';
$logoFsPath = __DIR__ . $logoWebPath;
$hasLoginLogo = is_file($logoFsPath);

$otpCtx = $_SESSION['office_login_otp_ctx'] ?? null;
if (is_array($otpCtx) && isset($otpCtx['set_at']) && (time() - (int) $otpCtx['set_at']) > 1200) {
    unset($_SESSION['office_login_otp_ctx']);
    $otpCtx = null;
}

/**
 * Zelfde tenant-detectie als wachtwoord-login.
 *
 * @return array{0: string, 1: int}|null slug + user id, of null bij fout
 */
function login_resolve_tenant_and_user_id(PDO $pdo, string $email): ?array
{
    $tenantStmt = $pdo->prepare("
        SELECT t.slug, u.id AS user_id
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
    if (count($tenantRows) !== 1) {
        return null;
    }

    return [(string) ($tenantRows[0]['slug'] ?? ''), (int) ($tenantRows[0]['user_id'] ?? 0)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['office_action'] ?? '') === 'verify_otp') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $foutmelding = 'Sessie verlopen. Probeer opnieuw in te loggen.';
    } elseif (auth_is_temporarily_blocked()) {
        $foutmelding = 'Te veel mislukte pogingen. Wacht 5 minuten en probeer opnieuw.';
    } elseif (!is_array($otpCtx) || !isset($otpCtx['challenge_id'], $otpCtx['email'], $otpCtx['tenant_slug'])) {
        $foutmelding = 'Geen actieve inlogcode-sessie. Vraag opnieuw een code aan.';
    } else {
        $code = trim((string) ($_POST['login_otp_code'] ?? ''));
        $res = login_otp_verify(
            $pdo,
            (int) $otpCtx['challenge_id'],
            (string) $otpCtx['email'],
            (string) $otpCtx['tenant_slug'],
            $code
        );
        if (!$res['ok']) {
            auth_note_failed_attempt();
            $foutmelding = (string) ($res['message'] ?? 'Code onjuist.');
        } else {
            auth_reset_failed_attempts();
            unset($_SESSION['office_login_otp_ctx']);
            $user = $res['user'];
            office_perform_login_from_office_row($pdo, $user);
            header('Location: ' . $redirectTo, true, 302);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['office_action'] ?? '') === 'request_email_otp') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $foutmelding = 'Sessie verlopen. Probeer opnieuw in te loggen.';
    } elseif (auth_is_temporarily_blocked()) {
        $foutmelding = 'Te veel mislukte pogingen. Wacht 5 minuten en probeer opnieuw.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '') {
            $foutmelding = 'Vul je e-mailadres in.';
        } else {
            $resolved = login_resolve_tenant_and_user_id($pdo, $email);
            if ($resolved === null) {
                $infoMelding = 'Als dit adres bij ons hoort, staat de code in je inbox.';
            } else {
                [$tenantSlug, $userId] = $resolved;
                $created = login_otp_create_and_send($pdo, $email, $tenantSlug, 'email_only', $userId);
                if (!$created['ok']) {
                    $foutmelding = (string) ($created['message'] ?? 'Kon geen code versturen.');
                } else {
                    $_SESSION['office_login_otp_ctx'] = [
                        'challenge_id' => (int) $created['challenge_id'],
                        'email' => $email,
                        'tenant_slug' => $tenantSlug,
                        'mode' => 'email_only',
                        'redirect_to' => $redirectTo,
                        'set_at' => time(),
                    ];
                    header('Location: /login.php?step=code&redirect_to=' . rawurlencode($redirectTo), true, 302);
                    exit;
                }
            }
        }
    }
}

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
                $row = office_password_check_office_login($pdo, $email, $password, $tenantSlug);
                if ($row === null) {
                    auth_note_failed_attempt();
                    $foutmelding = 'Inloggen mislukt. Controleer e-mail en wachtwoord.';
                } else {
                    $otpOn = (int) ($row['email_otp_enabled'] ?? 0) === 1 && login_otp_schema_ready($pdo);
                    if ($otpOn) {
                        $created = login_otp_create_and_send($pdo, $email, $tenantSlug, 'after_password', (int) $row['id']);
                        if (!$created['ok']) {
                            $foutmelding = (string) ($created['message'] ?? 'Kon geen tweede factor-mail versturen.');
                        } else {
                            auth_reset_failed_attempts();
                            $_SESSION['office_login_otp_ctx'] = [
                                'challenge_id' => (int) $created['challenge_id'],
                                'email' => $email,
                                'tenant_slug' => $tenantSlug,
                                'mode' => 'after_password',
                                'redirect_to' => $redirectTo,
                                'set_at' => time(),
                            ];
                            header('Location: /login.php?step=code&redirect_to=' . rawurlencode($redirectTo), true, 302);
                            exit;
                        }
                    } else {
                        auth_reset_failed_attempts();
                        office_perform_login_from_office_row($pdo, $row);
                        header('Location: ' . $redirectTo, true, 302);
                        exit;
                    }
                }
            }
        }
    }
}

$stepGet = (string) ($_GET['step'] ?? '');
$showOtpCodeForm = ($stepGet === 'code' && is_array($otpCtx) && isset($otpCtx['challenge_id']));
$showEmailOtpRequest = ($stepGet === 'email');

if ($stepGet === 'code' && !$showOtpCodeForm) {
    header('Location: /login.php?redirect_to=' . rawurlencode($redirectTo), true, 302);
    exit;
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

            <?php if ($infoMelding !== ''): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <?php echo h($infoMelding); ?>
                </div>
            <?php endif; ?>
            <?php if ($foutmelding !== ''): ?>
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <?php echo h($foutmelding); ?>
                </div>
            <?php endif; ?>

            <?php if (!$showOtpCodeForm && !$showEmailOtpRequest): ?>
                <div class="mb-4 flex rounded-xl border border-slate-200 bg-slate-50 p-1 text-sm font-medium">
                    <a
                        class="flex-1 rounded-lg px-3 py-2 text-center bg-white text-slate-900 shadow-sm"
                        href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >Wachtwoord</a>
                    <a
                        class="flex-1 rounded-lg px-3 py-2 text-center text-slate-600 hover:text-slate-900"
                        href="/login.php?step=email&redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >Code per e-mail</a>
                </div>

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
            <?php elseif ($showEmailOtpRequest): ?>
                <div class="mb-4 flex rounded-xl border border-slate-200 bg-slate-50 p-1 text-sm font-medium">
                    <a
                        class="flex-1 rounded-lg px-3 py-2 text-center text-slate-600 hover:text-slate-900"
                        href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >Wachtwoord</a>
                    <a
                        class="flex-1 rounded-lg px-3 py-2 text-center bg-white text-slate-900 shadow-sm"
                        href="/login.php?step=email&redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >Code per e-mail</a>
                </div>
                <form method="POST" action="/login.php" autocomplete="off" class="space-y-4">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">
                    <input type="hidden" name="office_action" value="request_email_otp">
                    <div>
                        <label for="email_otp" class="mb-1 block text-sm font-medium text-slate-700">E-mailadres</label>
                        <input
                            type="email"
                            id="email_otp"
                            name="email"
                            placeholder="bijv. admin@bedrijf.nl"
                            required
                            autofocus
                            autocomplete="username"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-busai-primary focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/15"
                        >
                    </div>
                    <button
                        type="submit"
                        class="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-busai-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-busai-primaryHover focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/20"
                    >
                        Stuur code (6 cijfers)
                    </button>
                </form>
                <p class="mt-4 text-xs text-slate-500">Je ontvangt een eenmalige code per e-mail. Die code is 15 minuten geldig.</p>
            <?php else: ?>
                <p class="mb-4 text-sm text-slate-600 text-center">
                    Vul de 6-cijferige code in die we net naar<br>
                    <strong><?php echo h((string) ($otpCtx['email'] ?? '')); ?></strong> hebben gestuurd.
                </p>
                <form method="POST" action="/login.php" autocomplete="off" class="space-y-4">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">
                    <input type="hidden" name="office_action" value="verify_otp">
                    <div>
                        <label for="login_otp_code" class="mb-1 block text-sm font-medium text-slate-700">Code</label>
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            id="login_otp_code"
                            name="login_otp_code"
                            placeholder="000000"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm tracking-widest text-center text-lg font-mono focus:border-busai-primary focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/15"
                        >
                    </div>
                    <button
                        type="submit"
                        class="mt-2 inline-flex w-full items-center justify-center rounded-xl bg-busai-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-busai-primaryHover focus:outline-none focus:ring-4 focus:ring-[#0B3E69]/20"
                    >
                        Bevestigen en inloggen
                    </button>
                </form>
                <p class="mt-4 text-center text-xs">
                    <a class="text-busai-primary hover:underline" href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>">Annuleren en opnieuw beginnen</a>
                </p>
            <?php endif; ?>

            <?php if (!$showOtpCodeForm && !$showEmailOtpRequest): ?>
                <p class="mt-4 text-xs text-slate-500">
                    Gebruik je account e-mailadres. De omgeving wordt automatisch bepaald.
                </p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
