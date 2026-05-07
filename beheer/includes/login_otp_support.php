<?php
declare(strict_types=1);

/**
 * 6-cijferige logincode per mail (kantoor-login).
 * Vereist migratie migrations/20260507_office_login_otp.sql + werkende SMTP_* in .env.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!function_exists('login_otp_schema_ready')) {
    function login_otp_schema_ready(PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $pdo->query('SELECT 1 FROM office_login_otp LIMIT 1');
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_otp_enabled'");
            $cached = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $cached = false;
        }

        return $cached;
    }
}

if (!function_exists('login_otp_rate_allow_request')) {
    function login_otp_rate_allow_request(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $now = time();
        $window = 900;
        $max = 3;
        if (!isset($_SESSION['login_otp_rate']) || !is_array($_SESSION['login_otp_rate'])) {
            $_SESSION['login_otp_rate'] = [];
        }
        $list = $_SESSION['login_otp_rate'][$email] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        $list = array_values(array_filter($list, static function ($t) use ($now, $window): bool {
            return is_int($t) && ($now - $t) < $window;
        }));
        if (count($list) >= $max) {
            return false;
        }
        $list[] = $now;
        $_SESSION['login_otp_rate'][$email] = $list;

        return true;
    }
}

if (!function_exists('login_otp_generate_plain_code')) {
    function login_otp_generate_plain_code(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('login_otp_send_mail')) {
    function login_otp_send_mail(string $toEmail, string $plainCode): bool
    {
        $root = dirname(__DIR__, 2);
        $base = rtrim((string) env_value('APP_BASE_URL', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $base = $scheme . '://' . $host;
        }

        $subject = 'Je inlogcode voor het beheerportaal';
        $body = '<p>Je eenmalige inlogcode is:</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($plainCode, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Deze code is 15 minuten geldig. Heb jij dit niet aangevraagd? Negeer deze mail.</p>'
            . '<p><a href="' . htmlspecialchars($base . '/login.php', ENT_QUOTES, 'UTF-8') . '">Naar inloggen</a></p>';

        $paths = [
            $root . '/beheer/includes/PHPMailer/Exception.php',
            $root . '/beheer/includes/PHPMailer/PHPMailer.php',
            $root . '/beheer/includes/PHPMailer/SMTP.php',
        ];
        foreach ($paths as $p) {
            if (!is_file($p)) {
                return false;
            }
        }
        require_once $paths[0];
        require_once $paths[1];
        require_once $paths[2];

        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = (string) env_value('SMTP_HOST', 'smtp.hostinger.com');
            $mail->SMTPAuth = true;
            $mail->Username = (string) env_value('SMTP_USER', '');
            $mail->Password = (string) env_value('SMTP_PASS', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int) env_value('SMTP_PORT', '465');

            if ($mail->Username === '' || $mail->Password === '') {
                return false;
            }

            $mail->setFrom(
                (string) env_value('SMTP_FROM_EMAIL', 'noreply@localhost'),
                (string) env_value('SMTP_FROM_NAME', 'Beheerportaal')
            );
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();

            return true;
        } catch (PHPMailerException $e) {
            error_log('login_otp mail: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('login_otp_cleanup_stale')) {
    function login_otp_cleanup_stale(PDO $pdo): void
    {
        try {
            $pdo->exec("DELETE FROM office_login_otp WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        } catch (Throwable $e) {
            // negeren
        }
    }
}

if (!function_exists('login_otp_create_and_send')) {
    /**
     * @return array{ok:bool, message?:string, challenge_id?:int}
     */
    function login_otp_create_and_send(
        PDO $pdo,
        string $email,
        string $tenantSlug,
        string $challengeType,
        int $userId
    ): array {
        if (!login_otp_schema_ready($pdo)) {
            return ['ok' => false, 'message' => 'Inlogcodes zijn nog niet geactiveerd (database-migratie ontbreekt).'];
        }
        if (!login_otp_rate_allow_request($email)) {
            return ['ok' => false, 'message' => 'Te veel aanvragen. Probeer het over een kwartier opnieuw.'];
        }

        login_otp_cleanup_stale($pdo);

        $emailNorm = strtolower(trim($email));
        $tenantSlug = trim($tenantSlug);
        $plain = login_otp_generate_plain_code();
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $expires = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();
            $del = $pdo->prepare(
                'DELETE FROM office_login_otp WHERE email_normalized = ? AND tenant_slug = ? AND consumed_at IS NULL'
            );
            $del->execute([$emailNorm, $tenantSlug]);

            $ins = $pdo->prepare(
                'INSERT INTO office_login_otp (email_normalized, tenant_slug, user_id, challenge_type, code_hash, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$emailNorm, $tenantSlug, $userId, $challengeType, $hash, $expires]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('login_otp_create: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Kon geen code aanmaken. Probeer later opnieuw.'];
        }

        if (!login_otp_send_mail($emailNorm, $plain)) {
            return ['ok' => false, 'message' => 'Kon geen e-mail versturen. Controleer SMTP-instellingen in .env.'];
        }

        return ['ok' => true, 'challenge_id' => $id];
    }
}

if (!function_exists('login_otp_verify')) {
    /**
     * @return array{ok:bool, message?:string, user?:array<string,mixed>}
     */
    function login_otp_verify(PDO $pdo, int $challengeId, string $email, string $tenantSlug, string $code): array
    {
        if (!login_otp_schema_ready($pdo)) {
            return ['ok' => false, 'message' => 'Inlogcodes zijn nog niet geactiveerd.'];
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Vul de 6-cijferige code in.'];
        }

        $emailNorm = strtolower(trim($email));
        $tenantSlug = trim($tenantSlug);

        $stmt = $pdo->prepare(
            'SELECT id, code_hash, attempt_count, expires_at, consumed_at, user_id, challenge_type
             FROM office_login_otp
             WHERE id = ? AND email_normalized = ? AND tenant_slug = ?
             LIMIT 1'
        );
        $stmt->execute([$challengeId, $emailNorm, $tenantSlug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'message' => 'Code ongeldig of verlopen.'];
        }
        if ($row['consumed_at'] !== null) {
            return ['ok' => false, 'message' => 'Deze code is al gebruikt.'];
        }
        $exp = strtotime((string) $row['expires_at']);
        if ($exp !== false && $exp < time()) {
            return ['ok' => false, 'message' => 'Deze code is verlopen. Vraag een nieuwe aan.'];
        }
        if ((int) $row['attempt_count'] >= 8) {
            return ['ok' => false, 'message' => 'Te veel foute pogingen. Vraag een nieuwe code aan.'];
        }

        if (!password_verify($code, (string) $row['code_hash'])) {
            $upd = $pdo->prepare('UPDATE office_login_otp SET attempt_count = attempt_count + 1 WHERE id = ?');
            $upd->execute([(int) $row['id']]);

            return ['ok' => false, 'message' => 'Code onjuist.'];
        }

        $pdo->prepare('UPDATE office_login_otp SET consumed_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);

        $userId = (int) $row['user_id'];
        $q = $pdo->prepare(
            "SELECT u.id, u.email, u.volledige_naam, u.rol, t_ctx.id AS session_tenant_id, t_ctx.slug AS session_tenant_slug
             FROM users u
             INNER JOIN tenants t_ctx ON t_ctx.slug = ? AND t_ctx.status = 'active'
             WHERE u.id = ?
               AND u.actief = 1
               AND (u.rol = 'platform_owner' OR u.tenant_id = t_ctx.id)
             LIMIT 1"
        );
        $q->execute([$tenantSlug, $userId]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['ok' => false, 'message' => 'Gebruiker niet gevonden of geen toegang.'];
        }

        return ['ok' => true, 'user' => $user];
    }
}
