<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['platform_owner']);
require 'includes/db.php';
include 'includes/header.php';

function ag_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$rows = [];
$tabelOk = false;
$totaalCt = 0;

try {
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $chk->execute([$dbName, 'api_gebruik']);
    $tabelOk = (int) $chk->fetchColumn() > 0;

    if ($tabelOk) {
        $stmt = $pdo->query(
            "SELECT a.*, t.naam AS tenant_naam
             FROM api_gebruik a
             LEFT JOIN tenants t ON t.id = a.tenant_id
             ORDER BY a.id DESC
             LIMIT 500"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $totaalCt += (int) ($row['kosten_ct'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $rows = [];
}

?>

<div style="max-width:1200px;margin:20px auto;padding:0 20px;font-family:Arial,sans-serif;">
    <h1 style="margin:0 0 6px;color:#003366;"><i class="fa-solid fa-chart-bar"></i> API-gebruik</h1>
    <p style="margin:0 0 18px;color:#666;font-size:13px;">Overzicht van externe API-kosten (social media, AI, enz.).</p>

    <?php if (!$tabelOk): ?>
        <div style="background:#fff7ed;color:#9a3412;padding:12px 14px;border-radius:8px;border:1px solid #fed7aa;">
            Tabel <code>api_gebruik</code> is nog niet aanwezig op deze omgeving.
        </div>
    <?php else: ?>
        <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;">
                <div style="font-size:22px;font-weight:800;color:#003366;"><?= count($rows) ?></div>
                <div style="font-size:12px;color:#64748b;">Regels (max. 500)</div>
            </div>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;">
                <div style="font-size:22px;font-weight:800;color:#003366;">€ <?= number_format($totaalCt / 100, 2, ',', '.') ?></div>
                <div style="font-size:12px;color:#64748b;">Totaal kosten (getoond)</div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">Datum</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">Tenant</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">API</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">Module</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">Eenheden</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">Kosten</th>
                        <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;">Omschrijving</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" style="padding:16px;color:#64748b;">Nog geen API-gebruik geregistreerd.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;"><?= ag_h((string) ($r['aangemaakt_op'] ?? $r['created_at'] ?? '')) ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;"><?= ag_h((string) ($r['tenant_naam'] ?? $r['tenant_id'] ?? '')) ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;"><?= ag_h((string) ($r['api_naam'] ?? '')) ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;"><?= ag_h((string) ($r['module'] ?? '')) ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;text-align:right;"><?= ag_h((string) ($r['eenheden'] ?? '')) ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;text-align:right;">€ <?= number_format(((int) ($r['kosten_ct'] ?? 0)) / 100, 2, ',', '.') ?></td>
                            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;"><?= ag_h((string) ($r['omschrijving'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
