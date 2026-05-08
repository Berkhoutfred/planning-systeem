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

/** Voorbeeldsegmenten: elk segment = tijd · vertrek · aankomst · km · zone; vertrek rij 2 = aankomst rij 1 */
$demoSegmenten = [
    [
        'tijd' => '07:30',
        'van' => 'Industrieweg 95, Zutphen (garage)',
        'naar' => 'De Stoven 37, Zutphen (vertrek passagiers)',
        'km' => '12',
        'zone' => 'NL',
    ],
    [
        'tijd' => '08:05',
        'van' => 'De Stoven 37, Zutphen (vertrek passagiers)',
        'naar' => 'Grensovergang Venlo (NL → DE)',
        'km' => '95',
        'zone' => 'NL',
    ],
    [
        'tijd' => '09:40',
        'van' => 'Grensovergang Venlo (NL → DE)',
        'naar' => 'Grens Passau (DE → AT)',
        'km' => '280',
        'zone' => 'DE',
    ],
    [
        'tijd' => '13:50',
        'van' => 'Grens Passau (DE → AT)',
        'naar' => 'Hotel Alpenblick, Innsbruck',
        'km' => '118',
        'zone' => 'CH',
    ],
];
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    .seg-test-wrap { max-width: 1100px; margin: 0 auto 40px; padding: 0 16px 24px; font-family: 'Segoe UI', sans-serif; }
    .seg-test-wrap h1 { color: #003366; font-size: 22px; margin-bottom: 8px; }
    .seg-banner {
        background: #fff8e6; border: 1px solid #ffc107; border-radius: 8px;
        padding: 14px 18px; margin-bottom: 24px; font-size: 14px; color: #856404;
    }
    .seg-banner strong { display: block; margin-bottom: 6px; color: #664d03; }
    .seg-section-title {
        font-size: 15px; font-weight: 700; color: #003366; margin: 28px 0 12px;
        padding-bottom: 6px; border-bottom: 2px solid #003366;
    }
    /* Variant A: tabel */
    .seg-table-wrap { overflow-x: auto; border: 1px solid #ddd; border-radius: 8px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
    .seg-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .seg-table th {
        text-align: left; background: #003366; color: #fff; padding: 10px 12px; font-weight: 600; white-space: nowrap;
    }
    .seg-table td { padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
    .seg-table tr:nth-child(even) td { background: #f9fbfd; }
    .seg-table tr:last-child td { border-bottom: none; }
    .seg-table .col-km { text-align: right; white-space: nowrap; width: 56px; }
    .seg-table .col-zone { text-align: center; width: 52px; font-weight: 700; color: #003366; }
    .seg-table .col-tijd { white-space: nowrap; width: 64px; font-weight: 600; color: #003366; }
    .seg-chain-hint { font-size: 11px; color: #6c757d; margin-top: 4px; font-style: italic; }
    .seg-chain-match { color: #198754; }
    /* Variant B: compacte balken (zoals route-compact) */
    .seg-compact { background: #fdfdfd; padding: 10px 12px; border: 1px solid #eee; border-radius: 8px; }
    .seg-compact-row {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; margin-bottom: 8px;
        padding-bottom: 8px; border-bottom: 1px dashed #e8e8e8; font-size: 12px;
    }
    .seg-compact-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .seg-cc { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .seg-cc label { font-size: 10px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: .02em; }
    .seg-cc span.val { font-size: 13px; color: #222; line-height: 1.35; word-break: break-word; }
    .seg-cc-tijd { width: 56px; flex-shrink: 0; }
    .seg-cc-km { width: 48px; flex-shrink: 0; text-align: right; }
    .seg-cc-zone { width: 40px; flex-shrink: 0; text-align: center; font-weight: 700; color: #003366; }
    .seg-cc-van { flex: 1 1 200px; max-width: 340px; }
    .seg-cc-naar { flex: 1 1 200px; max-width: 340px; }
    .seg-back { margin-top: 28px; font-size: 14px; }
    .seg-back a { color: #003366; font-weight: 600; }
</style>

<div class="seg-test-wrap">
    <h1><i class="fas fa-route" aria-hidden="true"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <div class="seg-banner">
        <strong><i class="fas fa-flask" aria-hidden="true"></i> Alleen een layout-proef</strong>
        Deze pagina slaat niets op en wijzigt geen offertes. Zo kun je beoordelen of een <em>segmentweergave</em>
        (tijd · vertrek · aankomst · km · zone) past bij jullie werkwijze. In een echte koppeling zou rij 2
        automatisch het <strong>aankomstadres van rij 1</strong> als vertrek tonen.
    </div>

    <h2 class="seg-section-title">Variant A — Tabel (overzichtelijk op print / PDF-achtig)</h2>
    <div class="seg-table-wrap">
        <table class="seg-table">
            <thead>
                <tr>
                    <th>Tijd</th>
                    <th>Vertrek (adres)</th>
                    <th>Aankomst (adres)</th>
                    <th class="col-km">Km</th>
                    <th class="col-zone">Zone</th>
                </tr>
            </thead>
            <tbody>
<?php
$prevNaar = null;
foreach ($demoSegmenten as $idx => $seg) {
    $tijd = htmlspecialchars($seg['tijd']);
    $van = htmlspecialchars($seg['van']);
    $naar = htmlspecialchars($seg['naar']);
    $km = htmlspecialchars($seg['km']);
    $zone = htmlspecialchars($seg['zone']);
    $rijNr = $idx + 1;
    $hint = '';
    if ($prevNaar !== null && $prevNaar === $seg['van']) {
        $hint = '<div class="seg-chain-hint"><span class="seg-chain-match">✓ Vertrek = aankomst vorig segment</span></div>';
    } elseif ($idx > 0) {
        $hint = '<div class="seg-chain-hint">(In productie: hier gelijk aan vorig aankomstadres)</div>';
    }
    $prevNaar = $seg['naar'];
    echo "                <tr>\n";
    echo "                    <td class=\"col-tijd\">{$tijd}</td>\n";
    echo "                    <td>{$van}{$hint}</td>\n";
    echo "                    <td>{$naar}</td>\n";
    echo "                    <td class=\"col-km\">{$km}</td>\n";
    echo "                    <td class=\"col-zone\">{$zone}</td>\n";
    echo "                </tr>\n";
}
?>
            </tbody>
        </table>
    </div>

    <h2 class="seg-section-title">Variant B — Compacte balken (vergelijkbaar met route-editor)</h2>
    <div class="seg-compact">
<?php
$prevNaar2 = null;
foreach ($demoSegmenten as $idx => $seg) {
    $chainNote = '';
    if ($idx > 0 && $prevNaar2 !== null && $prevNaar2 === $seg['van']) {
        $chainNote = ' <span style="color:#198754;font-size:10px;">(ketting OK)</span>';
    }
    $prevNaar2 = $seg['naar'];
    $tijd = htmlspecialchars($seg['tijd']);
    $van = htmlspecialchars($seg['van']) . $chainNote;
    $naar = htmlspecialchars($seg['naar']);
    $km = htmlspecialchars($seg['km']);
    $zone = htmlspecialchars($seg['zone']);
    echo "        <div class=\"seg-compact-row\">\n";
    echo "            <div class=\"seg-cc seg-cc-tijd\"><label>Tijd</label><span class=\"val\">{$tijd}</span></div>\n";
    echo "            <div class=\"seg-cc seg-cc-van\"><label>Vertrek</label><span class=\"val\">{$van}</span></div>\n";
    echo "            <div class=\"seg-cc seg-cc-naar\"><label>Aankomst</label><span class=\"val\">{$naar}</span></div>\n";
    echo "            <div class=\"seg-cc seg-cc-km\"><label>Km</label><span class=\"val\">{$km}</span></div>\n";
    echo "            <div class=\"seg-cc seg-cc-zone\"><label>Zone</label><span class=\"val\">{$zone}</span></div>\n";
    echo "        </div>\n";
}
?>
    </div>

    <p class="seg-back">
        <a href="maken.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Terug naar calculatie maken</a>
    </p>
</div>

<?php include '../includes/footer.php'; ?>
