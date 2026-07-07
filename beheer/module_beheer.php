<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['platform_owner']);
require 'includes/db.php';
require_once 'includes/module_access.php';
include 'includes/header.php';

function mb_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$melding = '';
$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $fout = 'Sessie verlopen. Vernieuw de pagina.';
    } else {
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $moduleCode = trim((string) ($_POST['module_code'] ?? ''));
        $actief = isset($_POST['actief']) ? 1 : 0;

        if ($tenantId <= 0 || $moduleCode === '') {
            $fout = 'Ongeldige tenant of module.';
        } else {
            $chk = $pdo->prepare('SELECT code FROM modules WHERE code = ? AND actief = 1 LIMIT 1');
            $chk->execute([$moduleCode]);
            if (!$chk->fetchColumn()) {
                $fout = 'Onbekende module.';
            } else {
                $pdo->prepare(
                    'INSERT INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE actief = VALUES(actief)'
                )->execute([$tenantId, $moduleCode, $actief]);
                $melding = 'Module opgeslagen.';
            }
        }
    }
}

$tenants = $pdo->query("SELECT id, naam, slug FROM tenants WHERE status = 'active' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query('SELECT code, naam FROM modules WHERE actief = 1 ORDER BY volgorde, code')->fetchAll(PDO::FETCH_ASSOC);

$tenantModules = [];
$stmtTm = $pdo->query('SELECT tenant_id, module_code, actief FROM tenant_modules');
foreach ($stmtTm->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $tenantModules[(int) $row['tenant_id']][(string) $row['module_code']] = (int) $row['actief'] === 1;
}

$csrf = auth_get_csrf_token();
?>

<div style="max-width:1200px;margin:20px auto;padding:0 20px;font-family:Arial,sans-serif;">
    <h1 style="margin:0 0 6px;color:#003366;"><i class="fa-solid fa-puzzle-piece"></i> Module beheer</h1>
    <p style="margin:0 0 18px;color:#666;font-size:13px;">Schakel modules per tenant in of uit.</p>

    <?php if ($melding !== ''): ?>
        <div style="background:#d4edda;color:#155724;padding:10px 12px;border-radius:6px;margin-bottom:14px;"><?= mb_h($melding) ?></div>
    <?php endif; ?>
    <?php if ($fout !== ''): ?>
        <div style="background:#f8d7da;color:#721c24;padding:10px 12px;border-radius:6px;margin-bottom:14px;"><?= mb_h($fout) ?></div>
    <?php endif; ?>

    <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">Tenant</th>
                    <?php foreach ($modules as $mod): ?>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;white-space:nowrap;"><?= mb_h((string) $mod['naam']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <?php $tid = (int) $tenant['id']; ?>
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                            <strong><?= mb_h((string) $tenant['naam']) ?></strong><br>
                            <span style="color:#64748b;font-size:11px;"><?= mb_h((string) $tenant['slug']) ?></span>
                        </td>
                        <?php foreach ($modules as $mod): ?>
                            <?php
                            $code = (string) $mod['code'];
                            $isAan = !empty($tenantModules[$tid][$code]);
                            ?>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:center;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="auth_csrf_token" value="<?= mb_h($csrf) ?>">
                                    <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                    <input type="hidden" name="module_code" value="<?= mb_h($code) ?>">
                                    <label style="cursor:pointer;">
                                        <input type="checkbox" name="actief" value="1" <?= $isAan ? 'checked' : '' ?> onchange="this.form.submit()">
                                    </label>
                                </form>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="margin-top:16px;font-size:12px;color:#64748b;">
        Uitgebreid tenant-beheer staat op <a href="platform_owner.php">Platform Owner</a>.
    </p>
</div>

<?php include 'includes/footer.php'; ?>
