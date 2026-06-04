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
$logoWebPath = '/assets/tourplan-logo-header-600.png';
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
    <title>Inloggen - Tourplan</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-fadeIn { animation: fadeIn 0.6s ease-out; }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #0B3E69 0%, #1a5c8f 50%, #003366 100%);
        }
        .glow-on-hover:hover {
            box-shadow: 0 0 30px rgba(11, 62, 105, 0.4);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen antialiased">
    <!-- Decorative circles -->
    <div class="absolute top-10 right-10 w-72 h-72 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float"></div>
    <div class="absolute bottom-10 left-10 w-72 h-72 bg-cyan-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float" style="animation-delay: 2s;"></div>
    
    <main class="relative min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md animate-fadeIn">
            <!-- Logo & Hero Section -->
            <div class="text-center mb-8">
                <?php if ($hasLoginLogo): ?>
                    <img
                        src="<?php echo h($logoWebPath); ?>"
                        alt="Tourplan"
                        class="h-20 w-auto mx-auto mb-6 drop-shadow-2xl"
                        loading="eager"
                    >
                <?php endif; ?>
                <h1 class="text-4xl font-bold text-white mb-3 tracking-tight">Welkom bij Tourplan</h1>
                <p class="text-blue-100 text-lg">Modern Transport Planning Software</p>
            </div>

            <!-- Login Card -->
            <div class="glass-effect rounded-3xl shadow-2xl p-8 glow-on-hover transition-all duration-300"

            <?php if ($infoMelding !== ''): ?>
                <div class="mb-6 rounded-xl border-2 border-emerald-400 bg-emerald-50 px-5 py-4 text-sm text-emerald-800 font-medium shadow-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo h($infoMelding); ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($foutmelding !== ''): ?>
                <div class="mb-6 rounded-xl border-2 border-rose-400 bg-rose-50 px-5 py-4 text-sm text-rose-800 font-medium shadow-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo h($foutmelding); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$showOtpCodeForm && !$showEmailOtpRequest): ?>
                <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 text-sm font-semibold shadow-inner">
                    <a
                        class="flex-1 rounded-xl px-4 py-2.5 text-center bg-white text-slate-900 shadow-md transition-all duration-200"
                        href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >🔑 Wachtwoord</a>
                    <a
                        class="flex-1 rounded-xl px-4 py-2.5 text-center text-slate-600 hover:text-slate-900 hover:bg-white/50 transition-all duration-200"
                        href="/login.php?step=email&redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >📧 E-mail code</a>
                </div>

                <form method="POST" action="/login.php" autocomplete="off" class="space-y-5">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">

                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">E-mailadres</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="admin@bedrijf.nl"
                            required
                            autofocus
                            autocomplete="username"
                            class="block w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 shadow-sm transition-all duration-200 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 hover:border-slate-300"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Wachtwoord</label>
                        <input
                            type="password"
                            id="password"
                            name="wachtwoord_poging"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                            class="block w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 shadow-sm transition-all duration-200 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 hover:border-slate-300"
                        >
                    </div>

                    <button
                        type="submit"
                        class="mt-6 w-full rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-4 text-base font-bold text-white shadow-lg transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-500/40"
                    >
                        Inloggen →
                    </button>
                </form>
            <?php elseif ($showEmailOtpRequest): ?>
                <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 text-sm font-semibold shadow-inner">
                    <a
                        class="flex-1 rounded-xl px-4 py-2.5 text-center text-slate-600 hover:text-slate-900 hover:bg-white/50 transition-all duration-200"
                        href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >🔑 Wachtwoord</a>
                    <a
                        class="flex-1 rounded-xl px-4 py-2.5 text-center bg-white text-slate-900 shadow-md transition-all duration-200"
                        href="/login.php?step=email&redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>"
                    >📧 E-mail code</a>
                </div>
                <form method="POST" action="/login.php" autocomplete="off" class="space-y-5">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">
                    <input type="hidden" name="office_action" value="request_email_otp">
                    <div>
                        <label for="email_otp" class="mb-2 block text-sm font-semibold text-slate-700">E-mailadres</label>
                        <input
                            type="email"
                            id="email_otp"
                            name="email"
                            placeholder="admin@bedrijf.nl"
                            required
                            autofocus
                            autocomplete="username"
                            class="block w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3.5 text-sm text-slate-900 shadow-sm transition-all duration-200 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 hover:border-slate-300"
                        >
                    </div>
                    <button
                        type="submit"
                        class="mt-6 w-full rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-4 text-base font-bold text-white shadow-lg transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-500/40"
                    >
                        Stuur code (6 cijfers) →
                    </button>
                </form>
                <p class="mt-5 text-xs text-slate-500 text-center">Je ontvangt een eenmalige code per e-mail. Die code is 15 minuten geldig.</p>
            <?php else: ?>
                <p class="mb-6 text-sm text-slate-700 text-center bg-blue-50 p-4 rounded-xl border border-blue-200">
                    Vul de 6-cijferige code in die we net naar<br>
                    <strong class="text-blue-700"><?php echo h((string) ($otpCtx['email'] ?? '')); ?></strong> hebben gestuurd.
                </p>
                <form method="POST" action="/login.php" autocomplete="off" class="space-y-5">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo h($redirectTo); ?>">
                    <input type="hidden" name="office_action" value="verify_otp">
                    <div>
                        <label for="login_otp_code" class="mb-2 block text-sm font-semibold text-slate-700 text-center">Verificatiecode</label>
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
                            class="block w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-4 text-lg text-slate-900 shadow-sm tracking-[0.5em] text-center font-mono font-bold focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 hover:border-slate-300 transition-all duration-200"
                        >
                    </div>
                    <button
                        type="submit"
                        class="mt-6 w-full rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-4 text-base font-bold text-white shadow-lg transition-all duration-200 hover:from-blue-700 hover:to-blue-800 hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-500/40"
                    >
                        Bevestigen en inloggen →
                    </button>
                </form>
                <p class="mt-5 text-center text-sm">
                    <a class="text-blue-600 hover:text-blue-700 font-semibold hover:underline transition-colors" href="/login.php?redirect_to=<?php echo h(rawurlencode($redirectTo)); ?>">← Annuleren en opnieuw beginnen</a>
                </p>
            <?php endif; ?>

            <?php if (!$showOtpCodeForm && !$showEmailOtpRequest): ?>
                <p class="mt-6 text-xs text-center text-slate-500">
                    Gebruik je account e-mailadres. De omgeving wordt automatisch bepaald.
                </p>
            <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-sm text-blue-100 font-medium">
                    © <?php echo date('Y'); ?> Tourplan - Transport Planning Software
                </p>
                <p class="mt-1 text-xs text-blue-200/60">
                    Veilig inloggen met multi-tenant ondersteuning
                </p>
            </div>
        </div>
    </main>
</body>
</html>
