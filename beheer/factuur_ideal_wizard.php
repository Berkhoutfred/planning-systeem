<?php
/**
 * iDEAL-factuur: preview → akkoord → mail + Mollie + status.
 */
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once 'includes/ideal_factuur_helpers.php';
require_once 'includes/ideal_factuur_load.php';
require_once 'includes/ideal_factuur_pdf.php';

require_once 'includes/PHPMailer/Exception.php';
require_once 'includes/PHPMailer/PHPMailer.php';
require_once 'includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt.');
}

$ritId = isset($_GET['rit_id']) ? (int) $_GET['rit_id'] : 0;
if ($ritId <= 0) {
    die('Geen rit_id. Ga terug naar het planbord.');
}

$bundle = ideal_factuur_load_bundle($pdo, $tenantId, $ritId);
if (!$bundle['ok']) {
    die(htmlspecialchars($bundle['error'] ?? 'Fout', ENT_QUOTES, 'UTF-8'));
}

$primary = $bundle['primary'];
if (($primary['betaalwijze'] ?? '') !== 'iDEAL') {
    die('Deze rit is geen iDEAL-factuur.');
}

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'verzend') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $flashErr = 'Sessie/CSRF ongeldig. Vernieuw de pagina.';
    } elseif (empty($_POST['bevestig'])) {
        $flashErr = 'Vink aan dat u de factuur heeft gecontroleerd.';
    } else {
        $ids = array_map(static function ($r) {
            return (int) $r['id'];
        }, $bundle['ritten']);
        $stChk = $pdo->prepare('SELECT id, factuur_status, werk_notities FROM ritten WHERE tenant_id = ? AND id = ? LIMIT 1');
        $allOk = true;
        foreach ($ids as $rid) {
            $stChk->execute([$tenantId, $rid]);
            $rw = $stChk->fetch(PDO::FETCH_ASSOC);
            if (!$rw || ($rw['factuur_status'] ?? '') !== 'Te factureren') {
                $allOk = false;

                break;
            }
        }
        if (!$allOk) {
            $flashErr = 'Deze ritten zijn niet meer in status “Te factureren” (al verwerkt?).';
        } elseif (ideal_werk_get_tag($primary['werk_notities'] ?? '', 'IDEAL_PAYMENT_ID')) {
            $flashErr = 'Er is al een iDEAL-betaling aangemaakt voor dit dossier. Gebruik de status hieronder.';
        } elseif ($bundle['totaal'] <= 0) {
            $flashErr = 'Geen geldig totaalbedrag op de rit.';
        } else {
            $factuurNr = ideal_factuur_next_nummer($pdo, $tenantId);
            $pdfDef = ideal_factuur_render_pdf(
                $bundle['ritten'],
                $bundle['klant'],
                $factuurNr,
                false,
                (float) $bundle['totaal'],
                $tenantId
            );

            $base = rtrim((string) env_value('APP_BASE_URL', ''), '/');
            if ($base === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $base = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $redirectUrl = $base . '/bedankt.php';
            $idList = implode(',', $ids);
            $m = ideal_mollie_create_payment(
                (float) $bundle['totaal'],
                'Factuur ' . $factuurNr . ' — ritten ' . $idList,
                $redirectUrl,
                [
                    'tenant_id' => (string) $tenantId,
                    'rit_ids' => $idList,
                    'rit_id' => (string) $ritId,
                    'factuurnummer' => $factuurNr,
                ]
            );
            if (!$m['ok'] || !$m['href'] || !$m['id']) {
                $flashErr = 'Mollie: ' . ($m['error'] ?? 'kon geen betaling aanmaken');
            } else {
                try {
                    $pdo->beginTransaction();
                    $updR = $pdo->prepare(
                        'UPDATE ritten SET factuurnummer = ?, factuur_datum = NOW(), factuur_status = \'Gefactureerd\',
                         werk_notities = ?, instructies = CONCAT(COALESCE(instructies, \'\'), ?)
                         WHERE tenant_id = ? AND id = ?'
                    );
                    $linkExtra = "\n[iDEAL betaallink] " . $m['href'];
                    foreach ($ids as $rid) {
                        $stOne = $pdo->prepare('SELECT werk_notities FROM ritten WHERE id = ? AND tenant_id = ? LIMIT 1');
                        $stOne->execute([$rid, $tenantId]);
                        $wrow = $stOne->fetch(PDO::FETCH_ASSOC);
                        $wk = ideal_werk_merge_tag((string) ($wrow['werk_notities'] ?? ''), 'IDEAL_PAYMENT_ID', $m['id']);
                        $wk = ideal_werk_merge_tag($wk, 'IDEAL_STATUS', 'open');
                        $updR->execute([$factuurNr, $wk, $linkExtra, $tenantId, $rid]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $flashErr = 'Database: opslaan mislukt.';

                    goto after_post;
                }

                $smtpPass = (string) env_value('SMTP_ADMIN_PASS', env_value('SMTP_PASS', ''));
                $mailOk = false;
                if ($smtpPass !== '' && filter_var($bundle['klant']['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = (string) env_value('SMTP_HOST', 'smtp.hostinger.com');
                        $mail->SMTPAuth = true;
                        $mail->Username = (string) env_value('SMTP_ADMIN_USER', 'administratie@taxiberkhout.nl');
                        $mail->Password = $smtpPass;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port = (int) env_value('SMTP_PORT', '465');
                        $mail->setFrom($mail->Username, 'Administratie');
                        $mail->addAddress((string) $bundle['klant']['email']);
                        $mail->addStringAttachment($pdfDef, 'Factuur_' . $factuurNr . '.pdf');
                        $mail->isHTML(true);
                        $mail->Subject = 'Factuur ' . $factuurNr . ' — iDEAL';
                        $naam = htmlspecialchars((string) ($bundle['klant']['bedrijfsnaam'] ?: trim(($bundle['klant']['voornaam'] ?? '') . ' ' . ($bundle['klant']['achternaam'] ?? ''))), ENT_QUOTES, 'UTF-8');
                        $mail->Body = '<p>Beste ' . $naam . ",</p><p>Hierbij ontvangt u de factuur <strong>{$factuurNr}</strong>.</p>"
                            . '<p>Betaal via iDEAL:</p><p><a href="' . htmlspecialchars($m['href'], ENT_QUOTES, 'UTF-8') . '">Open iDEAL-betaalpagina</a></p>'
                            . '<p>Met vriendelijke groet,<br>Kantoor</p>';
                        $mail->send();
                        $mailOk = true;
                    } catch (MailException $e) {
                        $flashErr = 'Factuur opgeslagen en iDEAL aangemaakt, maar e-mail verzenden mislukte: ' . $mail->ErrorInfo;
                    }
                } else {
                    $flashErr = 'Factuur en iDEAL zijn aangemaakt; geen SMTP of geen geldig klant-e-mail — stuur de link handmatig.';
                }
                if ($mailOk || $flashErr === '') {
                    $flashOk = 'Factuur ' . $factuurNr . ' is vastgelegd. iDEAL-link staat bij de rit(instructies). '
                        . ($mailOk ? 'E-mail naar klant is verstuurd.' : '');
                }
            }
        }
    }
}

after_post:
$bundle = ideal_factuur_load_bundle($pdo, $tenantId, $ritId);
if (!$bundle['ok']) {
    die(htmlspecialchars($bundle['error'] ?? 'Fout', ENT_QUOTES, 'UTF-8'));
}
$primary = $bundle['primary'];
$csrf = auth_get_csrf_token();
$pid = ideal_werk_get_tag($primary['werk_notities'] ?? '', 'IDEAL_PAYMENT_ID');
$stLocal = ideal_werk_get_tag($primary['werk_notities'] ?? '', 'IDEAL_STATUS');

include 'includes/header.php';
?>

<style>
    .wiz-wrap { max-width: 920px; margin: 24px auto; padding: 0 16px; }
    .wiz-h1 { color: #003366; font-size: 22px; margin: 0 0 8px 0; }
    .wiz-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .wiz-card h2 { margin: 0 0 12px 0; font-size: 15px; color: #1a202c; }
    .wiz-iframe { width: 100%; height: 640px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f7fafc; }
    .wiz-meta { font-size: 14px; color: #4a5568; line-height: 1.6; }
    .wiz-ok { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 14px; }
    .wiz-err { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 14px; }
    .wiz-btn { background: #003366; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px; }
    .wiz-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .wiz-muted { font-size: 13px; color: #718096; margin-top: 10px; }
    .wiz-status { font-family: ui-monospace, monospace; font-size: 13px; background: #edf2f7; padding: 10px; border-radius: 6px; }
</style>

<div class="wiz-wrap">
    <h1 class="wiz-h1">Factuur &amp; iDEAL</h1>
    <p class="wiz-muted">Rit #<?php echo (int) $ritId; ?> · Controleer het document, keur goed en verstuur naar de klant.</p>

    <?php if ($flashOk !== ''): ?>
        <div class="wiz-ok"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <div class="wiz-err"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="wiz-card">
        <h2>1. Voorbeeld (wat de klant krijgt)</h2>
        <p class="wiz-meta">
            Totaal: <strong>€ <?php echo number_format((float) $bundle['totaal'], 2, ',', '.'); ?></strong>
            · Klant: <?php echo htmlspecialchars((string) ($bundle['klant']['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <iframe class="wiz-iframe" title="Factuur preview" src="factuur_ideal_pdf.php?rit_id=<?php echo (int) $ritId; ?>"></iframe>
    </div>

    <div class="wiz-card">
        <h2>2. Akkoord &amp; verzenden</h2>
        <?php if (($primary['factuur_status'] ?? '') === 'Te factureren' && !$pid): ?>
            <form method="post" action="">
                <input type="hidden" name="actie" value="verzend">
                <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <label style="display:flex;gap:10px;align-items:flex-start;margin:14px 0;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="bevestig" value="1" style="margin-top:4px;width:18px;height:18px;">
                    <span>Ik heb het <strong>factuurvoorbeeld</strong> gecontroleerd (bedrag, klant, ritten). Ik wil dit nu definitief vastleggen, de factuur per e-mail versturen (indien SMTP ingesteld) en de <strong>iDEAL-betaallink</strong> activeren.</span>
                </label>
                <button type="submit" class="wiz-btn">Verstuur factuur &amp; activeer iDEAL</button>
            </form>
        <?php else: ?>
            <p class="wiz-meta">Deze factuur is al definitief gezet of er is al een iDEAL-betaling aangemaakt. Controleer de status hieronder.</p>
        <?php endif; ?>
    </div>

    <div class="wiz-card">
        <h2>3. Betalingsstatus</h2>
        <p class="wiz-meta">Lokaal: <span id="wiz-local"><?php echo htmlspecialchars((string) ($stLocal ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($pid): ?> · Mollie payment: <code id="wiz-pid"><?php echo htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'); ?></code><?php endif; ?></p>
        <div class="wiz-status" id="wiz-mollie-out">Nog niet opgevraagd. Klik op “Status verversen”.</div>
        <p style="margin-top:12px;">
            <button type="button" class="wiz-btn" id="wiz-refresh">Status verversen (Mollie)</button>
            <a href="live_planbord.php" class="wiz-btn" style="display:inline-block;text-decoration:none;background:#4a5568;">Terug naar planbord</a>
        </p>
        <p class="wiz-muted">Webhook: stel in Mollie in op <code><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/webhook_mollie_ritten.php', ENT_QUOTES, 'UTF-8'); ?></code> zodat “betaald” automatisch wordt bijgewerkt.</p>
    </div>
</div>

<script>
(function () {
    var ritId = <?php echo (int) $ritId; ?>;
    var btn = document.getElementById('wiz-refresh');
    var out = document.getElementById('wiz-mollie-out');
    function refresh() {
        if (!out) return;
        out.textContent = 'Bezig met ophalen…';
        fetch('ajax_ideal_status.php?rit_id=' + encodeURIComponent(String(ritId)), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.ok) {
                    out.textContent = 'Fout bij ophalen.';
                    return;
                }
                if (!j.has_payment) {
                    out.textContent = 'Nog geen iDEAL-betaling aangemaakt voor deze rit.';
                    return;
                }
                var line = 'Mollie-status: ' + (j.mollie_status || '?') + (j.paid ? ' (BETAALD)' : ' (nog niet betaald)');
                if (j.fetch_error) line += ' — opmerking: ' + j.fetch_error;
                out.textContent = line;
            }).catch(function () { out.textContent = 'Netwerkfout.'; });
    }
    if (btn) btn.addEventListener('click', refresh);
    <?php if ($pid): ?>refresh();<?php endif; ?>
})();
</script>

<?php include 'includes/footer.php'; ?>
