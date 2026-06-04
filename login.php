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
    $tenantStmt->execute([mb_strtolower(trim($email), 'UTF-8')]);
    $candidates = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($candidates) === 0) {
        return null;
    }
    if (count($candidates) > 1) {
        return null;
    }

    $row = $candidates[0];

    return [(string) $row['slug'], (int) $row['user_id']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['office_action'] ?? '');

    if ($action === 'request_email_otp') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '') {
            $foutmelding = 'Voer een geldig e-mailadres in.';
        } else {
            $resolved = login_resolve_tenant_and_user_id($pdo, $email);
            if ($resolved === null) {
                $foutmelding = 'Geen actief account gevonden voor dit e-mailadres.';
            } else {
                [$tenantSlug, $userId] = $resolved;
                auth_set_context_by_slug($pdo, $tenantSlug);

                $code = (string) random_int(100000, 999999);
                $challengeId = bin2hex(random_bytes(16));
                $_SESSION['office_login_otp_ctx'] = [
                    'challenge_id' => $challengeId,
                    'code' => $code,
                    'email' => $email,
                    'user_id' => $userId,
                    'set_at' => time(),
                ];

                try {
                    office_send_login_otp_email($pdo, $email, $code);
                    header('Location: /login.php?step=code&redirect_to=' . rawurlencode($redirectTo), true, 302);
                    exit;
                } catch (Exception $e) {
                    $foutmelding = 'Kon de code niet verzenden. Probeer het opnieuw.';
                    unset($_SESSION['office_login_otp_ctx']);
                }
            }
        }
    } elseif ($action === 'verify_otp') {
        $inputCode = trim((string) ($_POST['login_otp_code'] ?? ''));
        if (!is_array($otpCtx) || !isset($otpCtx['code'], $otpCtx['user_id'], $otpCtx['email'])) {
            $foutmelding = 'Geen actieve verificatiesessie. Begin opnieuw.';
        } elseif ($inputCode !== (string) $otpCtx['code']) {
            $foutmelding = 'Onjuiste code. Controleer je e-mail en probeer opnieuw.';
        } else {
            $userId = (int) $otpCtx['user_id'];
            $email = (string) $otpCtx['email'];
            $resolved = login_resolve_tenant_and_user_id($pdo, $email);
            if ($resolved === null) {
                $foutmelding = 'Account niet meer actief.';
                unset($_SESSION['office_login_otp_ctx']);
            } else {
                [$tenantSlug, $resolvedUserId] = $resolved;
                if ($resolvedUserId !== $userId) {
                    $foutmelding = 'Account mismatch. Begin opnieuw.';
                    unset($_SESSION['office_login_otp_ctx']);
                } else {
                    auth_set_context_by_slug($pdo, $tenantSlug);
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND tenant_id = ? AND actief = 1 LIMIT 1');
                    $stmt->execute([$userId, current_tenant_id()]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$row) {
                        $foutmelding = 'Account niet gevonden.';
                        unset($_SESSION['office_login_otp_ctx']);
                    } else {
                        auth_reset_failed_attempts();
                        unset($_SESSION['office_login_otp_ctx']);
                        office_perform_login_from_office_row($pdo, $row);
                        header('Location: ' . $redirectTo, true, 302);
                        exit;
                    }
                }
            }
        }
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $poging = (string) ($_POST['wachtwoord_poging'] ?? '');

        if ($email === '' || $poging === '') {
            $foutmelding = 'Vul zowel e-mailadres als wachtwoord in.';
        } else {
            $resolved = login_resolve_tenant_and_user_id($pdo, $email);
            if ($resolved === null) {
                $foutmelding = 'Ongeldig e-mailadres of wachtwoord.';
                auth_increment_failed_attempts();
            } else {
                [$tenantSlug, $userId] = $resolved;
                auth_set_context_by_slug($pdo, $tenantSlug);

                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND tenant_id = ? AND actief = 1 LIMIT 1');
                $stmt->execute([$userId, current_tenant_id()]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $foutmelding = 'Ongeldig e-mailadres of wachtwoord.';
                    auth_increment_failed_attempts();
                } else {
                    $requireOtp = auth_should_require_otp();
                    if (password_verify($poging, (string) $row['wachtwoord'])) {
                        if ($requireOtp) {
                            $code = (string) random_int(100000, 999999);
                            $challengeId = bin2hex(random_bytes(16));
                            $_SESSION['office_login_otp_ctx'] = [
                                'challenge_id' => $challengeId,
                                'code' => $code,
                                'email' => $email,
                                'user_id' => $userId,
                                'set_at' => time(),
                            ];
                            try {
                                office_send_login_otp_email($pdo, $email, $code);
                                header('Location: /login.php?step=code&redirect_to=' . rawurlencode($redirectTo), true, 302);
                                exit;
                            } catch (Exception $e) {
                                $foutmelding = 'Kon de verificatiecode niet verzenden.';
                            }
                        } else {
                            auth_reset_failed_attempts();
                            office_perform_login_from_office_row($pdo, $row);
                            header('Location: ' . $redirectTo, true, 302);
                            exit;
                        }
                    } else {
                        $foutmelding = 'Ongeldig e-mailadres of wachtwoord.';
                        auth_increment_failed_attempts();
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
