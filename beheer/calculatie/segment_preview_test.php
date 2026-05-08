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
 * Per segment: vertrektijd en aankomsttijd; vertrek rij N = aankomst rij N-1.
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
    .seg-test-wrap { max-width: 1160px; margin: 0 auto 40px; padding: 0 16px 24px; font-family: 'Segoe UI', sans-serif; }
    .seg-test-wrap h1 { color: #003366; font-size: 22px; margin-bottom: 8px; }
    .seg-banner {
        background: #fff8e6; border: 1px solid #ffc107; border-radius: 8px;
        padding: 14px 18px; margin-bottom: 24px; font-size: 14px; color: #856404;
    }
    .seg-banner strong { display: block; margin-bottom: 6px; color: #664d03; }
    .seg-table-wrap { overflow-x: auto; border: 1px solid #ddd; border-radius: 8px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
    .seg-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .seg-table th {
        text-align: left; background: #003366; color: #fff; padding: 10px 12px; font-weight: 600; white-space: nowrap;
    }
    .seg-table td { padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
    .seg-table tr:nth-child(even) td { background: #f9fbfd; }
    .seg-table tr:last-child td { border-bottom: none; }
    .seg-table .col-km { text-align: right; white-space: nowrap; width: 52px; }
    .seg-table .col-zone { text-align: center; width: 48px; font-weight: 700; color: #003366; }
    .seg-table .col-vertrek-tijd,
    .seg-table .col-aankomst-tijd { white-space: nowrap; width: 72px; font-weight: 600; color: #003366; }
    .seg-chain-hint { font-size: 11px; color: #6c757d; margin-top: 4px; font-style: italic; }
    .seg-chain-match { color: #198754; }
    .seg-back { margin-top: 28px; font-size: 14px; }
    .seg-back a { color: #003366; font-weight: 600; }
</style>

<div class="seg-test-wrap">
    <h1><i class="fas fa-route" aria-hidden="true"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <div class="seg-banner">
        <strong><i class="fas fa-flask" aria-hidden="true"></i> Alleen een layout-proef</strong>
        Kolommen: <strong>vertrektijd</strong> (eerste stop van het segment) · vertrekadres · aankomstadres ·
        <strong>aankomsttijd</strong> (bij die aankomst) · km · zone. In de ketting is
        <strong>vertrektijd van de volgende rij gelijk aan aankomsttijd van de vorige</strong>
        (zelfde moment: je vertrekt pas weer vanaf die plek).
        Het <strong>aankomstadres</strong> van rij 1 is het <strong>vertrekadres</strong> van rij 2 — zoals eerder.
    </div>

    <div class="seg-table-wrap">
        <table class="seg-table">
            <thead>
                <tr>
                    <th>Vertrektijd</th>
                    <th>Vertrek (adres)</th>
                    <th>Aankomst (adres)</th>
                    <th>Aankomsttijd</th>
                    <th class="col-km">Km</th>
                    <th class="col-zone">Zone</th>
                </tr>
            </thead>
            <tbody>
<?php
$prevNaar = null;
$prevAankomstTijd = null;
foreach ($demoSegmenten as $idx => $seg) {
    $vt = htmlspecialchars($seg['vertrektijd']);
    $at = htmlspecialchars($seg['aankomst_tijd']);
    $van = htmlspecialchars($seg['van']);
    $naar = htmlspecialchars($seg['naar']);
    $km = htmlspecialchars($seg['km']);
    $zone = htmlspecialchars($seg['zone']);

    $hintVertrek = '';
    if ($prevNaar !== null && $prevNaar === $seg['van']) {
        $hintVertrek .= '<div class="seg-chain-hint"><span class="seg-chain-match">✓ Adres = vorig aankomstadres</span></div>';
    } elseif ($idx > 0) {
        $hintVertrek .= '<div class="seg-chain-hint">(productie: gelijk vorig aankomstadres)</div>';
    }

    $hintTijd = '';
    if ($idx > 0 && $prevAankomstTijd !== null && $prevAankomstTijd === $seg['vertrektijd']) {
        $hintTijd = '<div class="seg-chain-hint"><span class="seg-chain-match">✓ = aankomsttijd vorig segment (= jouw vertrek hier)</span></div>';
    } elseif ($idx > 0) {
        $hintTijd = '<div class="seg-chain-hint">(productie: gelijk vorige aankomsttijd)</div>';
    }

    $hintAankomstTijd = '';
    if ($idx < count($demoSegmenten) - 1) {
        $nextVt = $demoSegmenten[$idx + 1]['vertrektijd'] ?? '';
        if ($nextVt !== '' && $nextVt === $seg['aankomst_tijd']) {
            $hintAankomstTijd = '<div class="seg-chain-hint"><span class="seg-chain-match">✓ = vertrektijd volgende rij</span></div>';
        }
    }

    $prevNaar = $seg['naar'];
    $prevAankomstTijd = $seg['aankomst_tijd'];

    echo "                <tr>\n";
    echo "                    <td class=\"col-vertrek-tijd\">{$vt}{$hintTijd}</td>\n";
    echo "                    <td>{$van}{$hintVertrek}</td>\n";
    echo "                    <td>{$naar}</td>\n";
    echo "                    <td class=\"col-aankomst-tijd\">{$at}{$hintAankomstTijd}</td>\n";
    echo "                    <td class=\"col-km\">{$km}</td>\n";
    echo "                    <td class=\"col-zone\">{$zone}</td>\n";
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
