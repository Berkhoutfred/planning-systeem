<?php
declare(strict_types=1);
// Bestand: beheer/reizen/index.php

include '../../beveiliging.php';
require_role(['tenant_admin', 'planner_user', 'platform_owner']);
require '../includes/db.php';
require_once __DIR__ . '/_tenant_context.php';
require_once __DIR__ . '/../includes/module_access.php';

if (!heeft_reizen_module($actieve_modules)) {
    vereis_tenant_module($pdo, $tenantId, ['dagtochten', 'coopdagtochten', 'busreizen']);
}

// ── Snelle acties ──────────────────────────────────────────
if (isset($_GET['archiveer']) && is_numeric($_GET['archiveer'])) {
    $archiveId = (int) $_GET['archiveer'];
    $archiveReis = reis_ophaal_met_toegang($pdo, $reisCtx, $archiveId);
    if ($archiveReis && reis_mag_bewerken_voor_tenant($reisCtx, (int) $archiveReis['tenant_id'])) {
        $pdo->prepare("UPDATE busreizen SET status='archief' WHERE id=? AND tenant_id=?")
            ->execute([$archiveId, (int) $archiveReis['tenant_id']]);
        header('Location: index.php?msg=gearchiveerd');
        exit;
    }
}
if (isset($_GET['toggle_status'])) {
    $toggleId = (int) $_GET['toggle_status'];
    $toggleReis = reis_ophaal_met_toegang($pdo, $reisCtx, $toggleId);
    if ($toggleReis && reis_mag_bewerken_voor_tenant($reisCtx, (int) $toggleReis['tenant_id'])) {
        $tid = (int) $toggleReis['tenant_id'];
        $nieuw = $toggleReis['status'] === 'gepubliceerd' ? 'concept' : 'gepubliceerd';
        $pdo->prepare('UPDATE busreizen SET status=? WHERE id=? AND tenant_id=?')
            ->execute([$nieuw, $toggleId, $tid]);
        coop_invalidate_partner_site_caches($pdo, $tid);
    }
    header('Location: index.php');
    exit;
}

// ── Filters ────────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterStatus = $_GET['status'] ?? 'actief'; // standaard verberg archief

[$whereParts, $params] = reis_lijst_where($reisCtx, $filterType, $filterStatus, $pdo);

$sql = 'SELECT b.*,
        (SELECT COUNT(*) FROM busreis_boekingen bk
         WHERE bk.busreis_id = b.id AND bk.betaal_status = \'betaald\') AS boekingen_betaald,
        (SELECT COUNT(*) FROM busreis_boekingen bk2
         WHERE bk2.busreis_id = b.id AND bk2.status != \'geannuleerd\') AS boekingen_totaal
        FROM busreizen b
        WHERE ' . implode(' AND ', $whereParts) . '
        ORDER BY b.datum_van ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reizen = $stmt->fetchAll();

// ── Statistieken ───────────────────────────────────────────
$allowedIds = reis_toegestane_tenant_ids($reisCtx, $pdo);
$idPlaceholders = implode(',', array_fill(0, count($allowedIds), '?'));
$stats = $pdo->prepare("
    SELECT
        COUNT(*) AS totaal,
        SUM(status='gepubliceerd') AS gepubliceerd,
        SUM(status='concept') AS concept,
        SUM(status='vol') AS vol
    FROM busreizen WHERE tenant_id IN ($idPlaceholders) AND status != 'archief'
");
$stats->execute($allowedIds);
$s = $stats->fetch() ?: ['totaal' => 0, 'gepubliceerd' => 0, 'concept' => 0, 'vol' => 0];

$boekStats = $pdo->prepare("
    SELECT COUNT(*) AS totaal,
           COALESCE(SUM(bk.totaal),0) AS omzet
    FROM busreis_boekingen bk
    JOIN busreizen b ON b.id = bk.busreis_id
    WHERE b.tenant_id IN ($idPlaceholders) AND bk.betaal_status='betaald'
");
$boekStats->execute($allowedIds);
$bs = $boekStats->fetch() ?: ['totaal' => 0, 'omzet' => 0];

$isPlatformOwner = function_exists('current_user_role') && current_user_role() === 'platform_owner';
$tenantNamen = [];
if ($isPlatformOwner) {
    $tenantNamen = array_column(
        $pdo->query("SELECT id, naam FROM tenants WHERE status = 'active' ORDER BY naam ASC")->fetchAll(),
        'naam',
        'id'
    );
}

include '../includes/header.php';
?>

<style>
.rz-page { max-width:1280px; margin:0 auto; padding:24px 20px; }

/* Stat cards */
.rz-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.rz-stat  { background:#fff; border-radius:10px; padding:16px 20px;
             box-shadow:0 1px 6px rgba(0,0,0,.07); border-left:4px solid #003d82; }
.rz-stat.groen  { border-left-color:#1a7f4b; }
.rz-stat.oranje { border-left-color:#c05800; }
.rz-stat.rood   { border-left-color:#b91c1c; }
.rz-stat-val  { font-size:26px; font-weight:800; color:#002855; line-height:1; margin-bottom:4px; }
.rz-stat-lbl  { font-size:12px; color:#64748b; font-weight:500; }

/* Toolbar */
.rz-toolbar { display:flex; justify-content:space-between; align-items:center;
               flex-wrap:wrap; gap:12px; margin-bottom:18px; }
.rz-toolbar h1 { font-size:20px; font-weight:800; color:#002855; margin:0;
                  display:flex; align-items:center; gap:8px; }
.rz-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.rz-filters select, .rz-filters input {
    padding:7px 12px; border:1px solid #d1d5db; border-radius:6px;
    font-size:13px; color:#374151; background:#fff; }
.rz-filters select:focus { outline:none; border-color:#003d82; }

/* Knoppen */
.btn-rz { display:inline-flex; align-items:center; gap:6px; padding:8px 16px;
           border-radius:7px; font-size:13px; font-weight:600; text-decoration:none;
           border:none; cursor:pointer; transition:.15s; }
.btn-blauw  { background:#003d82; color:#fff; }
.btn-blauw:hover  { background:#002855; color:#fff; }
.btn-groen  { background:#1a7f4b; color:#fff; }
.btn-groen:hover  { background:#155f38; color:#fff; }
.btn-grijs  { background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; }
.btn-grijs:hover  { background:#e2e8f0; }
.btn-rood   { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.btn-rood:hover { background:#fca5a5; }
.btn-sm { padding:5px 10px; font-size:12px; }

/* Badge */
.badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px;
         border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.3px; }
.badge-concept      { background:#f1f5f9; color:#475569; }
.badge-gepubliceerd { background:#dcfce7; color:#15803d; }
.badge-vol          { background:#fef3c7; color:#92400e; }
.badge-archief      { background:#fee2e2; color:#991b1b; }
.badge-dagtocht     { background:#dbeafe; color:#1e40af; }
.badge-meerdaags    { background:#ede9fe; color:#6d28d9; }
.badge-hartemink    { background:#fef9c3; color:#713f12; }
.badge-berkhout     { background:#e0f2fe; color:#0369a1; }
.badge-beide        { background:#f0fdf4; color:#166534; }
.badge-coop         { background:#ede9fe; color:#5b21b6; }

/* Tabel */
.rz-table-wrap { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07); overflow:hidden; }
.rz-table { width:100%; border-collapse:collapse; }
.rz-table thead th { background:#002855; color:#fff; padding:11px 14px;
                      text-align:left; font-size:12px; font-weight:600;
                      letter-spacing:.3px; white-space:nowrap; }
.rz-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
.rz-table tbody tr:hover { background:#f8faff; }
.rz-table tbody td { padding:12px 14px; font-size:13px; color:#374151;
                      vertical-align:middle; }
.rz-table .foto { width:52px; height:38px; object-fit:cover; border-radius:5px;
                   border:1px solid #e2e8f0; }
.rz-table .foto-leeg { width:52px; height:38px; border-radius:5px; background:#f1f5f9;
                        display:flex; align-items:center; justify-content:center;
                        color:#94a3b8; font-size:16px; border:1px dashed #e2e8f0; }
.rz-balk { height:6px; border-radius:3px; background:#e2e8f0; overflow:hidden; width:100px; }
.rz-balk-inner { height:100%; background:#003d82; border-radius:3px; transition:.3s; }
.rz-balk-inner.vol { background:#b91c1c; }

/* Lege staat */
.rz-leeg { padding:60px 20px; text-align:center; }
.rz-leeg i { font-size:48px; color:#e2e8f0; display:block; margin-bottom:16px; }
.rz-leeg p { color:#94a3b8; font-size:14px; margin-bottom:20px; }

/* Melding */
.rz-melding { padding:12px 16px; border-radius:7px; margin-bottom:18px;
               font-size:13px; font-weight:500; display:flex; align-items:center; gap:8px; }
.rz-melding.ok   { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.rz-melding.warn { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
</style>

<div class="rz-page">

<?php if (isset($_GET['msg'])): ?>
    <div class="rz-melding ok">
        <i class="fa-solid fa-circle-check"></i>
        <?php if ($_GET['msg'] === 'opgeslagen') echo 'Reis opgeslagen.'; ?>
        <?php if ($_GET['msg'] === 'gearchiveerd') echo 'Reis gearchiveerd.'; ?>
        <?php if ($_GET['msg'] === 'verwijderd') echo 'Reis verwijderd.'; ?>
        <?php if ($_GET['msg'] === 'geen_toegang') echo 'Geen rechten om reizen te bewerken (alleen inzage).'; ?>
    </div>
<?php endif; ?>

<?php if ($isPlatformOwner): ?>
    <div class="rz-melding ok" style="margin-bottom:16px;">
        <i class="fa-solid fa-crown"></i>
        Platform-modus: je ziet reizen van alle tenants. Bewerken kan alleen voor
        <strong><?= htmlspecialchars(current_tenant_name() ?: current_tenant_slug(), ENT_QUOTES) ?></strong>
        (huidige tenant rechtsboven).
    </div>
<?php elseif ($isCoopPartner): ?>
    <div class="rz-melding warn" style="margin-bottom:16px;">
        <i class="fa-solid fa-eye"></i>
        Coöp-modus: je ziet reizen en boekingen van de netwerk-leider. Bewerken is niet toegestaan.
    </div>
<?php elseif ($isHybrideModus): ?>
    <div class="rz-melding ok" style="margin-bottom:16px;">
        <i class="fa-solid fa-layer-group"></i>
        Hybride modus: coöp-dagtochten van de netwerk-leider (alleen inzage) én eigen dagtochten die je zelf beheert.
    </div>
<?php endif; ?>

<!-- Statistieken -->
<div class="rz-stats">
    <div class="rz-stat">
        <div class="rz-stat-val"><?= (int)$s['totaal'] ?></div>
        <div class="rz-stat-lbl"><i class="fa-solid fa-bus"></i> Totaal reizen</div>
    </div>
    <div class="rz-stat groen">
        <div class="rz-stat-val"><?= (int)$s['gepubliceerd'] ?></div>
        <div class="rz-stat-lbl"><i class="fa-solid fa-globe"></i> Gepubliceerd</div>
    </div>
    <div class="rz-stat oranje">
        <div class="rz-stat-val"><?= (int)$bs['totaal'] ?></div>
        <div class="rz-stat-lbl"><i class="fa-solid fa-users"></i> Betaalde boekingen</div>
    </div>
    <div class="rz-stat rood">
        <div class="rz-stat-val">€ <?= number_format((float)$bs['omzet'], 0, ',', '.') ?></div>
        <div class="rz-stat-lbl"><i class="fa-solid fa-euro-sign"></i> Omzet boekingen</div>
    </div>
</div>

<!-- Toolbar -->
<div class="rz-toolbar">
    <h1><i class="fa-solid fa-route"></i> Busreizen &amp; Dagtochten</h1>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap;">
            <select name="type" onchange="this.form.submit()">
                <option value="" <?= $filterType==='' ? 'selected' : '' ?>>Alle typen</option>
                <option value="dagtocht"  <?= $filterType==='dagtocht'  ? 'selected' : '' ?>>Dagtochten</option>
                <option value="meerdaags" <?= $filterType==='meerdaags' ? 'selected' : '' ?>>Meerdaagse reizen</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="actief"       <?= $filterStatus==='actief'       ? 'selected' : '' ?>>Actief (excl. archief)</option>
                <option value="concept"      <?= $filterStatus==='concept'      ? 'selected' : '' ?>>Concept</option>
                <option value="gepubliceerd" <?= $filterStatus==='gepubliceerd' ? 'selected' : '' ?>>Gepubliceerd</option>
                <option value="vol"          <?= $filterStatus==='vol'          ? 'selected' : '' ?>>Vol</option>
                <option value="archief"      <?= $filterStatus==='archief'      ? 'selected' : '' ?>>Archief</option>
                <option value=""             <?= $filterStatus===''             ? 'selected' : '' ?>>Alles</option>
            </select>
        </form>
        <?php if ($magEigenReizenBewerken): ?>
        <a href="bewerken.php" class="btn-rz btn-groen">
            <i class="fa-solid fa-plus"></i> Nieuwe dagtocht
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tabel -->
<div class="rz-table-wrap">
<?php if (empty($reizen)): ?>
    <div class="rz-leeg">
        <i class="fa-solid fa-route"></i>
        <p>Nog geen reizen gevonden voor de gekozen filters.</p>
        <?php if ($magEigenReizenBewerken): ?>
        <a href="bewerken.php" class="btn-rz btn-groen">
            <i class="fa-solid fa-plus"></i> Eerste dagtocht aanmaken
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table class="rz-table">
        <thead>
            <tr>
                <th style="width:60px;"></th>
                <th>Reis</th>
                <th>Type</th>
                <th>Bron</th>
                <th>Datum</th>
                <th>Vervoerder</th>
                <th>Prijs pp</th>
                <th>Boekingen</th>
                <th>Status</th>
                <th style="width:160px;">Acties</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reizen as $r):
            $reisTenantId = (int) $r['tenant_id'];
            $magDezeReisBewerken = reis_mag_bewerken_voor_tenant($reisCtx, $reisTenantId);
            $bron = reis_bron_label($reisCtx, $reisTenantId);
            $pct = $r['max_deelnemers'] > 0
                ? min(100, round($r['boekingen_totaal'] / $r['max_deelnemers'] * 100))
                : 0;
            $datumStr = date('d M Y', strtotime($r['datum_van']));
            if ($r['type'] === 'meerdaags' && $r['datum_tot']) {
                $datumStr .= ' – ' . date('d M Y', strtotime($r['datum_tot']));
            }
        ?>
            <tr>
                <td>
                    <?php if ($r['foto_pad']): ?>
                        <img src="<?= htmlspecialchars('../../' . ltrim($r['foto_pad'], '/'), ENT_QUOTES) ?>"
                             class="foto" alt="">
                    <?php else: ?>
                        <div class="foto-leeg"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="color:#002855;"><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></strong>
                    <?php if ($r['bestemming']): ?>
                        <br><small style="color:#94a3b8;"><i class="fa-solid fa-location-dot" style="width:10px;"></i>
                        <?= htmlspecialchars($r['bestemming'], ENT_QUOTES) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?= $r['type'] ?>">
                        <?= $r['type'] === 'dagtocht' ? '<i class="fa-solid fa-sun"></i> Dagtocht' : '<i class="fa-solid fa-moon"></i> Meerdaags' ?>
                    </span>
                </td>
                <td>
                    <?php if ($isPlatformOwner): ?>
                        <span class="badge badge-berkhout">
                            <i class="fa-solid fa-building"></i>
                            <?= htmlspecialchars((string) ($tenantNamen[$reisTenantId] ?? 'Tenant #' . $reisTenantId), ENT_QUOTES) ?>
                        </span>
                    <?php elseif ($bron === 'coop'): ?>
                        <span class="badge badge-coop"><i class="fa-solid fa-handshake"></i> Coöp</span>
                    <?php else: ?>
                        <span class="badge badge-gepubliceerd"><i class="fa-solid fa-house"></i> Eigen</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap; font-size:12.5px;"><?= htmlspecialchars($datumStr, ENT_QUOTES) ?></td>
                <td>
                    <span class="badge badge-<?= $r['vervoerder'] ?>">
                        <?= ucfirst(htmlspecialchars($r['vervoerder'], ENT_QUOTES)) ?>
                    </span>
                </td>
                <td style="font-weight:600; color:#002855;">
                    € <?= number_format((float)$r['prijs_pp'], 2, ',', '.') ?>
                </td>
                <td>
                    <div style="font-size:12px; margin-bottom:4px;">
                        <strong><?= (int)$r['boekingen_totaal'] ?></strong>
                        <span style="color:#94a3b8;">/ <?= (int)$r['max_deelnemers'] ?></span>
                    </div>
                    <div class="rz-balk">
                        <div class="rz-balk-inner <?= $pct >= 100 ? 'vol' : '' ?>"
                             style="width:<?= $pct ?>%;"></div>
                    </div>
                </td>
                <td>
                    <span class="badge badge-<?= $r['status'] ?>">
                        <?php
                        $labels = ['concept'=>'Concept','gepubliceerd'=>'Gepubliceerd','vol'=>'Vol','archief'=>'Archief'];
                        echo $labels[$r['status']] ?? $r['status'];
                        ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                        <?php if ($magDezeReisBewerken): ?>
                        <a href="bewerken.php?id=<?= $r['id'] ?>" class="btn-rz btn-blauw btn-sm"
                           title="Bewerken"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <a href="boekingen.php?id=<?= $r['id'] ?>" class="btn-rz btn-grijs btn-sm"
                           title="Boekingen"><i class="fa-solid fa-users"></i></a>
                        <?php if ($magDezeReisBewerken && $r['status'] !== 'archief'): ?>
                        <a href="index.php?toggle_status=<?= $r['id'] ?>"
                           class="btn-rz btn-sm <?= $r['status']==='gepubliceerd' ? 'btn-rood' : 'btn-groen' ?>"
                           title="<?= $r['status']==='gepubliceerd' ? 'Depubliceren' : 'Publiceren' ?>">
                            <i class="fa-solid fa-<?= $r['status']==='gepubliceerd' ? 'eye-slash' : 'globe' ?>"></i>
                        </a>
                        <a href="index.php?archiveer=<?= $r['id'] ?>"
                           class="btn-rz btn-grijs btn-sm"
                           onclick="return confirm('Reis archiveren?')"
                           title="Archiveren"><i class="fa-solid fa-box-archive"></i></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

</div>

<?php include '../includes/footer.php'; ?>
