<?php
/**
 * Demo / testpagina: route als keten van segmenten (alleen visueel, geen opslag).
 * URL: beheer/calculatie/segment_preview_test.php
 */
declare(strict_types=1);

include '../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);

$pageTitle = 'Proef: route per segment';
include '../includes/header.php';

/**
 * @var list<array{vertrektijd: string, van: string, naar: string, aankomst_tijd: string, km: string, zone: string}>
 */
$demoSegmenten = [
    [
        'vertrektijd' => '07:30',
        'van' => 'Industrieweg 95, Zutphen (garage)',
        'naar' => 'De Stoven 37, Zutphen (vertrek passagiers)',
        'aankomst_tijd' => '07:45',
        'km' => '12',
        'zone' => 'NL',
    ],
    [
        'vertrektijd' => '07:45',
        'van' => 'De Stoven 37, Zutphen (vertrek passagiers)',
        'naar' => 'Grensovergang Venlo (NL → DE)',
        'aankomst_tijd' => '09:15',
        'km' => '95',
        'zone' => 'NL',
    ],
    [
        'vertrektijd' => '09:15',
        'van' => 'Grensovergang Venlo (NL → DE)',
        'naar' => 'Grens Passau (DE → AT)',
        'aankomst_tijd' => '13:50',
        'km' => '280',
        'zone' => 'DE',
    ],
    [
        'vertrektijd' => '13:50',
        'van' => 'Grens Passau (DE → AT)',
        'naar' => 'Hotel Alpenblick, Innsbruck',
        'aankomst_tijd' => '15:35',
        'km' => '118',
        'zone' => 'CH',
    ],
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    .seg-test-wrap { max-width: 1100px; margin: 0 auto 40px; padding: 0 16px 32px; font-family: 'Segoe UI', system-ui, sans-serif; }
    .seg-test-wrap h1 { color: #003366; font-size: 1.35rem; font-weight: 700; margin: 0 0 20px; letter-spacing: -0.02em; }
    .seg-lead { color: #5c6370; font-size: 0.95rem; margin: 0 0 22px; line-height: 1.45; }
    .seg-card {
        border-radius: 10px;
        border: 1px solid #e2e6ec;
        background: linear-gradient(180deg, #fbfcfe 0%, #fff 48%);
        box-shadow: 0 2px 12px rgba(0, 31, 71, 0.06);
        overflow: hidden;
    }
    .seg-table { width: 100%; border-collapse: collapse; font-size: 13px; color: #2c323a; }
    .seg-table thead th {
        text-align: left;
        background: #003366;
        color: #fff;
        padding: 12px 14px;
        font-weight: 600;
        font-size: 11px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .seg-table thead th:first-child { padding-left: 18px; border-radius: 0; }
    .seg-table thead th:last-child { padding-right: 18px; }
    .seg-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef1f5;
        vertical-align: middle;
        line-height: 1.4;
    }
    .seg-table tbody td:first-child { padding-left: 18px; }
    .seg-table tbody td:last-child { padding-right: 18px; }
    .seg-table tbody tr:last-child td { border-bottom: none; }
    .seg-table tbody tr:hover td { background: rgba(0, 51, 102, 0.04); }
    .seg-t {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        color: #003366;
        white-space: nowrap;
        width: 1%;
    }
    .seg-van-naar { min-width: 200px; max-width: 340px; }
    .seg-km {
        text-align: right;
        font-variant-numeric: tabular-nums;
        color: #2c323a;
        width: 1%;
    }
    .seg-zone {
        text-align: center;
        font-weight: 700;
        font-size: 12px;
        color: #003366;
        width: 1%;
    }
    .seg-back { margin-top: 26px; font-size: 14px; }
    .seg-back a { color: #003366; font-weight: 600; text-decoration: none; }
    .seg-back a:hover { text-decoration: underline; }
</style>

<div class="seg-test-wrap">
    <h1><i class="fas fa-route" aria-hidden="true"></i> <?= htmlspecialchars($pageTitle) ?></h1>
    <p class="seg-lead">Layout-proef · geen data uit je omgeving.</p>

    <div class="seg-card">
        <table class="seg-table">
            <thead>
                <tr>
                    <th>Vertrektijd</th>
                    <th>Van</th>
                    <th>Naar</th>
                    <th>Aankomsttijd</th>
                    <th>Km</th>
                    <th>Zone</th>
                </tr>
            </thead>
            <tbody>
<?php
foreach ($demoSegmenten as $seg) {
    $vt = htmlspecialchars($seg['vertrektijd']);
    $at = htmlspecialchars($seg['aankomst_tijd']);
    $van = htmlspecialchars($seg['van']);
    $naar = htmlspecialchars($seg['naar']);
    $km = htmlspecialchars($seg['km']);
    $zone = htmlspecialchars($seg['zone']);

    echo "                <tr>\n";
    echo "                    <td class=\"seg-t\">{$vt}</td>\n";
    echo "                    <td class=\"seg-van-naar\">{$van}</td>\n";
    echo "                    <td class=\"seg-van-naar\">{$naar}</td>\n";
    echo "                    <td class=\"seg-t\">{$at}</td>\n";
    echo "                    <td class=\"seg-km\">{$km}</td>\n";
    echo "                    <td class=\"seg-zone\">{$zone}</td>\n";
    echo "                </tr>\n";
}
?>
            </tbody>
        </table>
    </div>

    <p class="seg-back">
        <a href="maken.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Terug naar calculatie maken</a>
    </p>
</div>

<?php include '../includes/footer.php'; ?>
