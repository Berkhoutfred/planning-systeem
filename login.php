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
require_once __DIR__ . '/beheer/includes/module_access.php';

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
            header('Location: ' . login_post_auth_redirect($pdo, $redirectTo), true, 302);
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
                        header('Location: ' . login_post_auth_redirect($pdo, $redirectTo), true, 302);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            height: 100vh;
            overflow: hidden;
        }
        
        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            height: 100vh;
        }
        
        /* LEFT SIDE - BRANDING */
        .brand-side {
            background: linear-gradient(135deg, #0B3E69 0%, #1a5c8f 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .brand-side::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 8s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 480px;
        }
        
        .brand-logo {
            width: 280px;
            height: auto;
            margin-bottom: 48px;
            filter: drop-shadow(0 10px 40px rgba(0,0,0,0.3));
        }
        
        .brand-title {
            font-size: 42px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        
        .brand-subtitle {
            font-size: 20px;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-bottom: 48px;
            line-height: 1.5;
        }
        
        .feature-list {
            text-align: left;
            margin-top: 48px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            color: rgba(255,255,255,0.95);
            font-size: 16px;
        }
        
        .feature-icon {
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        /* RIGHT SIDE - FORM */
        .form-side {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px;
        }
        
        .form-container {
            width: 100%;
            max-width: 420px;
        }
        
        .form-header {
            margin-bottom: 40px;
        }
        
        .form-title {
            font-size: 32px;
            font-weight: 700;
            color: #0B3E69;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .form-description {
            font-size: 16px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* TABS */
        .auth-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 32px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }
        
        .auth-tab.active {
            background: white;
            color: #0B3E69;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        /* ALERTS */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* FORM ELEMENTS */
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }
        
        .form-input:hover {
            border-color: #cbd5e1;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #0B3E69;
            box-shadow: 0 0 0 3px rgba(11, 62, 105, 0.1);
        }
        
        .form-input::placeholder {
            color: #94a3b8;
        }
        
        .submit-button {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #0B3E69 0%, #1a5c8f 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(11, 62, 105, 0.3);
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 62, 105, 0.4);
        }
        
        .submit-button:active {
            transform: translateY(0);
        }
        
        .form-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
        
        .otp-code-input {
            text-align: center;
            font-family: 'SF Mono', Monaco, monospace;
            letter-spacing: 0.5em;
            font-size: 24px;
            font-weight: 700;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            .brand-side {
                display: none;
            }
        }
        
        @media (max-width: 640px) {
            .form-side {
                padding: 32px 24px;
            }
            .form-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- LEFT SIDE - BRANDING -->
        <div class="brand-side">
            <div class="brand-content">
                <?php if ($hasLoginLogo): ?>
                    <img src="<?php echo htmlspecialchars($logoWebPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Tourplan" class="brand-logo">
                <?php endif; ?>
                
                <h1 class="brand-title">Enterprise Resource Planning</h1>
                <p class="brand-subtitle">Breng offertes, bevestigingen, planning en facturering samen in één centraal systeem.</p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Offertes &amp; bevestigingen</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Live planbord</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <span>Facturering &amp; administratie</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT SIDE - FORM -->
        <div class="form-side">
            <div class="form-container">
                <div class="form-header">
                    <h2 class="form-title">Welkom terug</h2>
                    <p class="form-description">Log in op je Tourplan account</p>
                </div>
                
                <?php if ($infoMelding !== ''): ?>
                    <div class="alert alert-success">
                        <span>✓</span>
                        <?php echo htmlspecialchars($infoMelding, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($foutmelding !== ''): ?>
                    <div class="alert alert-error">
                        <span>×</span>
                        <?php echo htmlspecialchars($foutmelding, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$showOtpCodeForm && !$showEmailOtpRequest): ?>
                    <div class="auth-tabs">
                        <a href="/login.php?redirect_to=<?php echo htmlspecialchars(rawurlencode($redirectTo), ENT_QUOTES, 'UTF-8'); ?>" class="auth-tab active">Wachtwoord</a>
                        <a href="/login.php?step=email&redirect_to=<?php echo htmlspecialchars(rawurlencode($redirectTo), ENT_QUOTES, 'UTF-8'); ?>" class="auth-tab">E-mail code</a>
                    </div>
                    
                    <form method="POST" action="/login.php" autocomplete="off">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <div class="form-group">
                            <label for="email" class="form-label">E-mailadres</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="naam@bedrijf.nl"
                                required
                                autofocus
                                autocomplete="username"
                                class="form-input"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Wachtwoord</label>
                            <input
                                type="password"
                                id="password"
                                name="wachtwoord_poging"
                                placeholder="Voer je wachtwoord in"
                                required
                                autocomplete="current-password"
                                class="form-input"
                            >
                        </div>
                        
                        <button type="submit" class="submit-button">Inloggen</button>
                        
                        <div class="form-footer">
                            Je account wordt automatisch herkend
                        </div>
                    </form>
                <?php elseif ($showEmailOtpRequest): ?>
                    <div class="auth-tabs">
                        <a href="/login.php?redirect_to=<?php echo htmlspecialchars(rawurlencode($redirectTo), ENT_QUOTES, 'UTF-8'); ?>" class="auth-tab">Wachtwoord</a>
                        <a href="/login.php?step=email&redirect_to=<?php echo htmlspecialchars(rawurlencode($redirectTo), ENT_QUOTES, 'UTF-8'); ?>" class="auth-tab active">E-mail code</a>
                    </div>
                    
                    <form method="POST" action="/login.php" autocomplete="off">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="office_action" value="request_email_otp">
                        
                        <div class="form-group">
                            <label for="email_otp" class="form-label">E-mailadres</label>
                            <input
                                type="email"
                                id="email_otp"
                                name="email"
                                placeholder="naam@bedrijf.nl"
                                required
                                autofocus
                                autocomplete="username"
                                class="form-input"
                            >
                        </div>
                        
                        <button type="submit" class="submit-button">Stuur inlogcode</button>
                        
                        <div class="form-footer">
                            Je ontvangt een 6-cijferige code per e-mail
                        </div>
                    </form>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; background: #f1f5f9; border-radius: 12px; margin-bottom: 24px; font-size: 14px; color: #475569;">
                        Voer de 6-cijferige code in die is verzonden naar<br>
                        <strong style="color: #0B3E69;"><?php echo htmlspecialchars((string) ($otpCtx['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                    
                    <form method="POST" action="/login.php" autocomplete="off">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="office_action" value="verify_otp">
                        
                        <div class="form-group">
                            <label for="login_otp_code" class="form-label" style="text-align: center;">Verificatiecode</label>
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
                                class="form-input otp-code-input"
                            >
                        </div>
                        
                        <button type="submit" class="submit-button">Verifiëren en inloggen</button>
                        
                        <div class="form-footer">
                            <a href="/login.php?redirect_to=<?php echo htmlspecialchars(rawurlencode($redirectTo), ENT_QUOTES, 'UTF-8'); ?>" style="color: #0B3E69; font-weight: 600; text-decoration: none;">← Terug naar inloggen</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
