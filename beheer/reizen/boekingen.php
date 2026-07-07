<?php
declare(strict_types=1);
// Bestand: beheer/reizen/boekingen.php

include '../../beveiliging.php';
require_role(['tenant_admin', 'planner_user', 'platform_owner']);
require '../includes/db.php';
require_once __DIR__ . '/_tenant_context.php';
$reisId   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Boeking annuleren ──────────────────────────────────────
if (isset($_GET['annuleer']) && $reisId) {
    $annuleerReis = reis_ophaal_met_toegang($pdo, $reisCtx, $reisId);
    if ($annuleerReis && reis_mag_bewerken_voor_tenant($reisCtx, (int) $annuleerReis['tenant_id'])) {
        $bId = (int) $_GET['annuleer'];
        $tid = (int) $annuleerReis['tenant_id'];
        $pdo->prepare("UPDATE busreis_boekingen SET status='geannuleerd' WHERE id=? AND busreis_id=? AND tenant_id=?")
            ->execute([$bId, $reisId, $tid]);
        header("Location: boekingen.php?id={$reisId}&msg=geannuleerd");
        exit;
    }
}

// ── Reis ophalen ───────────────────────────────────────────
if ($reisId) {
    $reis = reis_ophaal_met_toegang($pdo, $reisCtx, $reisId);
} else {
    $reis = null;
}

$boekingenTenantId = $reis ? (int) $reis['tenant_id'] : $dataTenantId;
$magDezeReisBewerken = $reis
    ? reis_mag_bewerken_voor_tenant($reisCtx, (int) $reis['tenant_id'])
    : false;

// ── Filters ────────────────────────────────────────────────
$filterStatus = $_GET['betaal_status'] ?? '';
$allowedIds = reis_toegestane_tenant_ids($reisCtx, $pdo);
$idPlaceholders = implode(',', array_fill(0, count($allowedIds), '?'));
$where  = ['bk.tenant_id IN (' . $idPlaceholders . ')'];
$params = $allowedIds;
if ($reisId) { $where[] = 'bk.busreis_id = ?'; $params[] = $reisId; }
if (in_array($filterStatus, ['open','betaald','mislukt','terugbetaald','geannuleerd'], true)) {
    $where[] = 'bk.betaal_status = ?'; $params[] = $filterStatus;
}

$sql = 'SELECT bk.*, b.titel AS reis_titel, b.datum_van, b.type AS reis_type,
        (SELECT COUNT(*) FROM busreis_deelnemers d WHERE d.boeking_id = bk.id) AS aantal_deelnemers_db
        FROM busreis_boekingen bk
        JOIN busreizen b ON b.id = bk.busreis_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY bk.aangemaakt_op DESC';
$stmtBk = $pdo->prepare($sql);
$stmtBk->execute($params);
$boekingen = $stmtBk->fetchAll();

// ── Stats ──────────────────────────────────────────────────
$statsQ = $reisId
    ? "SELECT SUM(betaal_status='betaald') AS betaald, SUM(betaal_status='open') AS open,
              SUM(betaal_status='geannuleerd') AS geannuleerd,
              COALESCE(SUM(CASE WHEN betaal_status='betaald' THEN totaal END),0) AS omzet,
              SUM(CASE WHEN betaal_status='betaald' THEN aantal_deelnemers END) AS deelnemers
       FROM busreis_boekingen WHERE busreis_id=? AND tenant_id=?"
    : "SELECT SUM(betaal_status='betaald') AS betaald, SUM(betaal_status='open') AS open,
              SUM(betaal_status='geannuleerd') AS geannuleerd,
              COALESCE(SUM(CASE WHEN betaal_status='betaald' THEN totaal END),0) AS omzet,
              SUM(CASE WHEN betaal_status='betaald' THEN aantal_deelnemers END) AS deelnemers
       FROM busreis_boekingen WHERE tenant_id IN ($idPlaceholders)";
$stmtSt = $pdo->prepare($statsQ);
$stmtSt->execute($reisId ? [$reisId, $boekingenTenantId] : $allowedIds);
$st = $stmtSt->fetch();

include '../includes/header.php';
?>
<style>
.bk-page { max-width:1280px; margin:0 auto; padding:24px 20px; }
.bk-kop  { display:flex; align-items:center; justify-content:space-between;
            margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.bk-kop h1 { font-size:19px; font-weight:800; color:#002855; margin:0;
              display:flex; align-items:center; gap:9px; }
.bk-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:20px; }
.bk-stat  { background:#fff; border-radius:9px; padding:14px 18px;
             box-shadow:0 1px 5px rgba(0,0,0,.07); border-left:4px solid #003d82; }
.bk-stat.groen  { border-left-color:#1a7f4b; }
.bk-stat.oranje { border-left-color:#c05800; }
.bk-stat.rood   { border-left-color:#b91c1c; }
.bk-stat.paars  { border-left-color:#6d28d9; }
.bk-stat-val { font-size:22px; font-weight:800; color:#002855; line-height:1; margin-bottom:3px; }
.bk-stat-lbl { font-size:11.5px; color:#64748b; }

.bk-filters { display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
.bk-filters select { padding:7px 12px; border:1px solid #d1d5db; border-radius:6px;
                      font-size:13px; color:#374151; background:#fff; }

.bk-table-wrap { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07); overflow:hidden; }
.bk-table { width:100%; border-collapse:collapse; }
.bk-table thead th { background:#002855; color:#fff; padding:10px 13px;
                      text-align:left; font-size:11.5px; font-weight:600; white-space:nowrap; }
.bk-table tbody tr { border-bottom:1px solid #f1f5f9; }
.bk-table tbody tr:hover { background:#f8faff; }
.bk-table tbody td { padding:10px 13px; font-size:12.5px; color:#374151; vertical-align:top; }

.badge { display:inline-flex; align-items:center; gap:3px; padding:3px 8px;
         border-radius:20px; font-size:10.5px; font-weight:700; }
.badge-betaald     { background:#dcfce7; color:#15803d; }
.badge-open        { background:#fef3c7; color:#92400e; }
.badge-mislukt     { background:#fee2e2; color:#b91c1c; }
.badge-geannuleerd { background:#f1f5f9; color:#64748b; }
.badge-terugbetaald{ background:#ede9fe; color:#6d28d9; }

.btn-sm { display:inline-flex; align-items:center; gap:4px; padding:5px 9px;
          border-radius:5px; font-size:11.5px; font-weight:600; text-decoration:none;
          border:none; cursor:pointer; transition:.15s; }
.btn-blauw { background:#003d82; color:#fff; }
.btn-blauw:hover { background:#002855; color:#fff; }
.btn-rood  { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.btn-rood:hover  { background:#fca5a5; }
.btn-grijs { background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; }
.btn-grijs:hover { background:#e2e8f0; }

.bk-leeg { padding:50px 20px; text-align:center; color:#94a3b8; }
.bk-melding { padding:10px 16px; border-radius:7px; margin-bottom:16px; font-size:13px;
               display:flex; align-items:center; gap:8px; }
.bk-melding.ok   { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
.bk-melding.warn { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }

/* Detail uitklap */
.detail-rij td { background:#f8faff !important; border-bottom:2px solid #e8eef7 !important; }
.detail-inner { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:4px 0; }
.detail-blok h4 { font-size:11px; font-weight:800; text-transform:uppercase;
                   letter-spacing:.5px; color:#003d82; margin-bottom:8px; }
.detail-blok p  { font-size:12px; color:#374151; margin:3px 0; }
.detail-blok strong { color:#002855; }
</style>

<div class="bk-page">

<?php if (isset($_GET['msg'])): ?>
<div class="bk-melding <?= $_GET['msg']==='geannuleerd' ? 'warn' : 'ok' ?>">
    <i class="fa-solid fa-<?= $_GET['msg']==='geannuleerd' ? 'triangle-exclamation' : 'circle-check' ?>"></i>
    <?php if ($_GET['msg']==='geannuleerd') echo 'Boeking geannuleerd.'; ?>
</div>
<?php endif; ?>

<div class="bk-kop">
    <h1>
        <i class="fa-solid fa-users"></i>
        <?= $reis ? 'Boekingen: ' . htmlspecialchars($reis['titel'], ENT_QUOTES) : 'Alle boekingen' ?>
    </h1>
    <div style="display:flex; gap:8px;">
        <?php if ($reis && $magDezeReisBewerken): ?>
        <a href="bewerken.php?id=<?= $reisId ?>" class="btn-sm btn-grijs">
            <i class="fa-solid fa-pen"></i> Reis bewerken
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn-sm btn-grijs">
            <i class="fa-solid fa-arrow-left"></i> Terug naar reizen
        </a>
    </div>
</div>

<!-- Stats -->
<div class="bk-stats">
    <div class="bk-stat groen">
        <div class="bk-stat-val"><?= (int)$st['betaald'] ?></div>
        <div class="bk-stat-lbl"><i class="fa-solid fa-check"></i> Betaald</div>
    </div>
    <div class="bk-stat oranje">
        <div class="bk-stat-val"><?= (int)$st['open'] ?></div>
        <div class="bk-stat-lbl"><i class="fa-solid fa-clock"></i> Open / in afwachting</div>
    </div>
    <div class="bk-stat rood">
        <div class="bk-stat-val"><?= (int)$st['geannuleerd'] ?></div>
        <div class="bk-stat-lbl"><i class="fa-solid fa-ban"></i> Geannuleerd</div>
    </div>
    <div class="bk-stat paars">
        <div class="bk-stat-val"><?= (int)$st['deelnemers'] ?></div>
        <div class="bk-stat-lbl"><i class="fa-solid fa-users"></i> Bevestigde deelnemers</div>
    </div>
    <div class="bk-stat">
        <div class="bk-stat-val">€ <?= number_format((float)$st['omzet'], 0, ',', '.') ?></div>
        <div class="bk-stat-lbl"><i class="fa-solid fa-euro-sign"></i> Omzet (betaald)</div>
    </div>
</div>

<!-- Filters -->
<div class="bk-filters">
    <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <?php if ($reisId): ?><input type="hidden" name="id" value="<?= $reisId ?>"><?php endif; ?>
        <select name="betaal_status" onchange="this.form.submit()">
            <option value="">Alle statussen</option>
            <option value="betaald"     <?= $filterStatus==='betaald'     ? 'selected' : '' ?>>Betaald</option>
            <option value="open"        <?= $filterStatus==='open'        ? 'selected' : '' ?>>Open</option>
            <option value="mislukt"     <?= $filterStatus==='mislukt'     ? 'selected' : '' ?>>Mislukt</option>
            <option value="geannuleerd" <?= $filterStatus==='geannuleerd' ? 'selected' : '' ?>>Geannuleerd</option>
            <option value="terugbetaald"<?= $filterStatus==='terugbetaald'? 'selected' : '' ?>>Terugbetaald</option>
        </select>
    </form>
    <span style="font-size:12px; color:#94a3b8;"><?= count($boekingen) ?> boekingen gevonden</span>
</div>

<!-- Tabel -->
<div class="bk-table-wrap">
<?php if (empty($boekingen)): ?>
    <div class="bk-leeg"><i class="fa-solid fa-inbox" style="font-size:36px; display:block; margin-bottom:12px;"></i>
    Geen boekingen gevonden.</div>
<?php else: ?>
<table class="bk-table">
    <thead>
        <tr>
            <th style="width:28px;"></th>
            <th>Ref.</th>
            <?php if (!$reisId): ?><th>Reis</th><?php endif; ?>
            <th>Hoofdboeker</th>
            <th>Pers.</th>
            <th>Halte</th>
            <th>Totaal</th>
            <th>Betaalstatus</th>
            <th>Aangemaakt</th>
            <th>Acties</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($boekingen as $bk):
        $deelnemers = $pdo->prepare("SELECT * FROM busreis_deelnemers WHERE boeking_id=? ORDER BY is_hoofdboeker DESC");
        $deelnemers->execute([$bk['id']]);
        $deelnemers = $deelnemers->fetchAll();
        $halteNaam = '';
        if ($bk['halte_id']) {
            $hq = $pdo->prepare("SELECT naam FROM busreis_haltes WHERE id=?");
            $hq->execute([$bk['halte_id']]);
            $halteNaam = $hq->fetchColumn() ?: '';
        }
    ?>
    <tr onclick="toggleDetail(<?= $bk['id'] ?>)" style="cursor:pointer;">
        <td style="text-align:center; color:#94a3b8; font-size:11px;">
            <i class="fa-solid fa-chevron-right" id="chevron-<?= $bk['id'] ?>"></i>
        </td>
        <td><strong style="font-family:monospace; color:#003d82;"><?= htmlspecialchars($bk['boeking_ref'], ENT_QUOTES) ?></strong></td>
        <?php if (!$reisId): ?>
        <td>
            <div style="font-size:12px; font-weight:600; color:#002855;"><?= htmlspecialchars($bk['reis_titel'], ENT_QUOTES) ?></div>
            <div style="font-size:11px; color:#94a3b8;"><?= date('d M Y', strtotime($bk['datum_van'])) ?></div>
        </td>
        <?php endif; ?>
        <td>
            <div style="font-weight:600;"><?= htmlspecialchars($bk['voornaam'] . ' ' . $bk['achternaam'], ENT_QUOTES) ?></div>
            <div style="font-size:11.5px; color:#64748b;"><?= htmlspecialchars($bk['email'], ENT_QUOTES) ?></div>
        </td>
        <td style="text-align:center; font-weight:700;"><?= (int)$bk['aantal_deelnemers'] ?></td>
        <td style="font-size:12px;"><?= $halteNaam ? htmlspecialchars($halteNaam, ENT_QUOTES) : '<span style="color:#d1d5db;">—</span>' ?></td>
        <td style="font-weight:700; color:#002855;">€ <?= number_format((float)$bk['totaal'], 2, ',', '.') ?></td>
        <td>
            <span class="badge badge-<?= $bk['betaal_status'] ?>">
                <?php
                $labels = ['betaald'=>'Betaald','open'=>'Open','mislukt'=>'Mislukt',
                           'geannuleerd'=>'Geannuleerd','terugbetaald'=>'Terugbetaald'];
                echo $labels[$bk['betaal_status']] ?? $bk['betaal_status'];
                ?>
            </span>
        </td>
        <td style="font-size:11.5px; color:#64748b;"><?= date('d M H:i', strtotime($bk['aangemaakt_op'])) ?></td>
        <td onclick="event.stopPropagation()">
            <?php if ($magDezeReisBewerken && $bk['status'] !== 'geannuleerd'): ?>
            <a href="?id=<?= $reisId ?>&annuleer=<?= $bk['id'] ?>"
               onclick="return confirm('Boeking <?= htmlspecialchars($bk['boeking_ref'], ENT_QUOTES) ?> annuleren?')"
               class="btn-sm btn-rood" title="Annuleren">
                <i class="fa-solid fa-ban"></i>
            </a>
            <?php endif; ?>
        </td>
    </tr>
    <!-- Detail uitklap -->
    <tr class="detail-rij" id="detail-<?= $bk['id'] ?>" style="display:none;">
        <td colspan="<?= $reisId ? 9 : 10 ?>">
            <div class="detail-inner">
                <div class="detail-blok">
                    <h4><i class="fa-solid fa-user"></i> Contactgegevens hoofdboeker</h4>
                    <p><strong>Naam:</strong> <?= htmlspecialchars($bk['voornaam'] . ' ' . $bk['achternaam'], ENT_QUOTES) ?></p>
                    <p><strong>E-mail:</strong> <?= htmlspecialchars($bk['email'], ENT_QUOTES) ?></p>
                    <?php if ($bk['telefoon']): ?><p><strong>Telefoon:</strong> <?= htmlspecialchars($bk['telefoon'], ENT_QUOTES) ?></p><?php endif; ?>
                    <?php if ($bk['adres']): ?><p><strong>Adres:</strong> <?= htmlspecialchars($bk['adres'] . ' ' . $bk['postcode'] . ' ' . $bk['woonplaats'], ENT_QUOTES) ?></p><?php endif; ?>
                    <?php if ($bk['telefoon_thuisblijver']): ?>
                    <p><strong><i class="fa-solid fa-phone" style="color:#b91c1c;"></i> Thuisblijver:</strong>
                        <?= htmlspecialchars($bk['telefoon_thuisblijver'], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                    <?php if ($bk['opmerkingen']): ?><p><strong>Opmerkingen:</strong> <?= htmlspecialchars($bk['opmerkingen'], ENT_QUOTES) ?></p><?php endif; ?>
                </div>
                <div class="detail-blok">
                    <h4><i class="fa-solid fa-users"></i> Deelnemers (<?= count($deelnemers) ?>)</h4>
                    <?php foreach ($deelnemers as $d): ?>
                    <p>
                        <?= htmlspecialchars($d['voornaam'] . ' ' . $d['achternaam'], ENT_QUOTES) ?>
                        <?php if ($d['is_hoofdboeker']): ?><span style="font-size:10px; color:#64748b;">(hoofdboeker)</span><?php endif; ?>
                        <?php if ($d['enkelpersoon_kamer']): ?><span style="font-size:10px; color:#6d28d9;">(1p kamer)</span><?php endif; ?>
                    </p>
                    <?php endforeach; ?>
                    <?php if ($bk['gekozen_opties']): ?>
                    <h4 style="margin-top:10px;"><i class="fa-solid fa-ticket"></i> Gekozen opties</h4>
                    <?php
                    $opts = json_decode($bk['gekozen_opties'], true) ?? [];
                    foreach ($opts as $opt): ?>
                        <p><?= htmlspecialchars($opt['naam'] ?? '', ENT_QUOTES) ?>
                            — € <?= number_format((float)($opt['prijs'] ?? 0), 2, ',', '.') ?></p>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($bk['mollie_payment_id']): ?>
                    <p style="margin-top:8px; font-size:11px; color:#94a3b8;">
                        <i class="fa-solid fa-credit-card"></i>
                        Mollie ID: <?= htmlspecialchars($bk['mollie_payment_id'], ENT_QUOTES) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</div>
</div>

<script>
function toggleDetail(id) {
    const rij   = document.getElementById('detail-' + id);
    const chev  = document.getElementById('chevron-' + id);
    const open  = rij.style.display !== 'none';
    rij.style.display = open ? 'none' : '';
    if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
}
</script>

<?php include '../includes/footer.php'; ?>
