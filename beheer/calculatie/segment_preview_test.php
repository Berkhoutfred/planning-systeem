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

    /* Opties onder de rit — rondjes + afkorting (alleen layout-proef) */
    .seg-opt-block { margin-top: 28px; }
    .seg-opt-block h2 {
        font-size: 0.95rem; font-weight: 700; color: #003366; margin: 0 0 6px; letter-spacing: -0.02em;
    }
    .seg-opt-lead { font-size: 0.85rem; color: #5c6370; margin: 0 0 14px; line-height: 1.45; }
    .seg-opt-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
    .seg-opt-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 10px 14px; border-radius: 8px; border: 1px solid #e8ecf2;
        background: #fafbfd;
    }
    .seg-opt-ring {
        flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%;
        border: 2px solid #003366; display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 800; color: #003366; letter-spacing: 0.02em;
        background: #fff;
    }
    .seg-opt-body { min-width: 0; padding-top: 2px; }
    .seg-opt-body strong { display: block; font-size: 13px; color: #2c323a; font-weight: 600; }
    .seg-opt-body span.note { display: block; font-size: 12px; color: #6b7280; margin-top: 3px; line-height: 1.35; }

    .seg-opt-example { margin-top: 18px; padding: 14px 16px; border-radius: 8px; border: 1px dashed #93a4bd; background: #f4f7fb; }
    .seg-opt-example > p { margin: 0 0 10px; font-size: 12px; font-weight: 600; color: #003366; }
    .seg-opt-example .seg-card { box-shadow: none; }
    .seg-example-row td { background: rgba(0, 51, 102, 0.06) !important; }
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

    <?php
    $optiesOnderRit = [
        ['afk' => 'ER', 'label' => 'Extra ritregel', 'note' => 'Nog een segment in de ketting (extra Van → Naar).'],
        ['afk' => 'RG', 'label' => 'Retour naar garage', 'note' => 'Voegt een segment toe: laatste punt → garage (zie voorbeeld hieronder).'],
        ['afk' => 'ED', 'label' => 'Extra dag', 'note' => 'Tussen/accommodatie op een andere kalenderdag (zoals je «extra rijdag» nu).'],
        ['afk' => 'RS', 'label' => 'Retour naar start / eerste adres', 'note' => 'Laatste stap terug naar het eerste ophaaladres.'],
        ['afk' => 'LZ', 'label' => 'Later ophalen / tweede aanrij', 'note' => 'Rit splitst: eerst terug naar zaak, later opnieuw vertrek.'],
    ];
    ?>

    <div class="seg-opt-block">
        <h2>Opties onder de ritregel (proef)</h2>
        <p class="seg-opt-lead">Rondje met korte afkorting — zo kun je opties scannen zonder lange zinnen. In productie worden dit vinkjes of keuzes die iets met de ketting doen.</p>
        <ul class="seg-opt-list">
<?php foreach ($optiesOnderRit as $op) :
    $afk = htmlspecialchars($op['afk']);
    $lb = htmlspecialchars($op['label']);
    $no = htmlspecialchars($op['note']);
    ?>
            <li class="seg-opt-item">
                <span class="seg-opt-ring" title="<?= $afk ?>"><?= $afk ?></span>
                <div class="seg-opt-body">
                    <strong><?= $lb ?></strong>
                    <span class="note"><?= $no ?></span>
                </div>
            </li>
<?php endforeach; ?>
        </ul>

        <div class="seg-opt-example">
            <p>Voorbeeld: optie «Retour naar garage» (RG) — er komt direct een extra segment onder de hoofdrit:</p>
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
                        <tr class="seg-example-row">
                            <td class="seg-t">15:35</td>
                            <td class="seg-van-naar">Hotel Alpenblick, Innsbruck</td>
                            <td class="seg-van-naar">Industrieweg 95, Zutphen (garage)</td>
                            <td class="seg-t">23:10</td>
                            <td class="seg-km">982</td>
                            <td class="seg-zone">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="seg-opt-lead" style="margin:10px 0 0;">(Cijfers fictief; alleen om het idee te tonen: één regel die «doorrekent» na de laatste hoofdrit.)</p>
        </div>
    </div>

    <p class="seg-back">
        <a href="maken.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Terug naar calculatie maken</a>
    </p>
</div>

<?php include '../includes/footer.php'; ?>
