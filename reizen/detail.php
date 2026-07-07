<?php
declare(strict_types=1);
// Bestand: reizen/detail.php

require_once __DIR__ . '/../beheer/includes/db.php';
require_once __DIR__ . '/_prijs.php';
require_once __DIR__ . '/_media.php';
require_once __DIR__ . '/_scope.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: index.php'); exit; }

$scopeTenantId = busreis_scope_tenant_id($pdo, 'public');
$stmt = $pdo->prepare("SELECT * FROM busreizen WHERE slug=? AND tenant_id=? AND status IN ('gepubliceerd','vol')");
$stmt->execute([$slug, $scopeTenantId]);
$r = $stmt->fetch();
if (!$r) { header('Location: index.php'); exit; }

$id = (int)$r['id'];

$haltes      = $pdo->prepare("SELECT * FROM busreis_haltes WHERE busreis_id=? ORDER BY sort_order, vertrek_tijd ASC");
$haltes->execute([$id]); $haltes = $haltes->fetchAll();

$dagprog     = $pdo->prepare("SELECT * FROM busreis_dagprogramma WHERE busreis_id=? ORDER BY sort_order, dag_nummer ASC");
$dagprog->execute([$id]); $dagprog = $dagprog->fetchAll();

$opties      = $pdo->prepare("SELECT * FROM busreis_opties WHERE busreis_id=? ORDER BY sort_order ASC");
$opties->execute([$id]); $opties = $opties->fetchAll();

$boekingen   = (int)$pdo->prepare("SELECT COUNT(*) FROM busreis_boekingen WHERE busreis_id=? AND status!='geannuleerd'")->execute([$id]) ? $pdo->query("SELECT COUNT(*) FROM busreis_boekingen WHERE busreis_id=$id AND status!='geannuleerd'")->fetchColumn() : 0;

$vrij        = max(0, $r['max_deelnemers'] - $boekingen);
$isVol       = $vrij === 0 || $r['status'] === 'vol';
$nDagen      = $r['datum_tot'] ? (int)((strtotime($r['datum_tot']) - strtotime($r['datum_van'])) / 86400) + 1 : 1;
$bronPartner = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) ($_GET['src'] ?? ''))));
$vroegboekActief = busreis_vroegboek_actief($r);
$bezetting       = busreis_bezetting($boekingen, (int) $r['max_deelnemers']);
$bookingDirect   = isset($_GET['boek']) || isset($_GET['boeken']);
$boekQuery       = $bronPartner !== '' ? '&src=' . urlencode($bronPartner) : '';

$nl_maanden  = ['January'=>'Januari','February'=>'Februari','March'=>'Maart','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Augustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'December'];
$datumStr    = strtr(date('d F Y', strtotime($r['datum_van'])), $nl_maanden);
if ($r['type']==='meerdaags' && $r['datum_tot']) $datumStr .= ' – ' . strtr(date('d F Y', strtotime($r['datum_tot'])), $nl_maanden);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= htmlspecialchars(substr(strip_tags($r['beschrijving'] ?? ''), 0, 155), ENT_QUOTES) ?>">
<title><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?> | Coach Travel × Berkhout Reizen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --navy:#002855; --blue:#004aad; --gold:#f59e0b; --green:#16a34a; }
html { scroll-behavior: smooth; }
body { font-family: 'Inter', sans-serif; background: #f0f4f8; color: #1a2533; }

/* NAV */
.nav { background: var(--navy); height: 58px; display: flex; align-items: center; padding: 0 clamp(16px,4vw,50px); justify-content: space-between; position: sticky; top: 0; z-index: 200; box-shadow: 0 2px 14px rgba(0,0,0,.25); }
.nav-logo { font-size: 16px; font-weight: 900; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
.nav-logo span { color: #5bc8f5; }
.nav-back { color: rgba(255,255,255,.7); font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 6px; }
.nav-back:hover { color: #fff; }

/* HERO */
.hero { position: relative; height: clamp(280px, 45vw, 480px); overflow: hidden; }
.hero-img { width: 100%; height: 100%; object-fit: cover; object-position: center; image-rendering: auto; }
.hero-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg,#001d42,#004aad); display: flex; align-items: center; justify-content: center; }
.hero-placeholder i { font-size: 80px; color: rgba(255,255,255,.12); }
.hero-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,20,50,.85) 0%, rgba(0,20,50,.3) 50%, transparent 100%); }
.hero-content { position: absolute; bottom: 0; left: 0; right: 0; padding: clamp(20px,4vw,40px); }
.hero-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,.6); margin-bottom: 10px; }
.hero-breadcrumb a { color: rgba(255,255,255,.6); text-decoration: none; }
.hero-breadcrumb a:hover { color: #fff; }
.hero-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.hero-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.hb-type { background: rgba(59,130,246,.85); color: #fff; }
.hb-meer { background: rgba(109,40,217,.85); color: #fff; }
.hb-vert { background: rgba(245,158,11,.9); color: #fff; }
.hb-sgr  { background: rgba(22,163,74,.8); color: #fff; }
.hero h1 { font-size: clamp(22px,4vw,38px); font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 10px; max-width: 700px; }
.hero-meta { display: flex; gap: 16px; flex-wrap: wrap; }
.hero-meta-item { display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,.8); font-size: 13.5px; }
.hero-meta-item i { color: #5bc8f5; }

/* LAYOUT */
.page-wrap { max-width: 1200px; margin: 0 auto; padding: 32px clamp(16px,4vw,40px); display: grid; grid-template-columns: 1fr 360px; gap: 28px; align-items: start; }
@media (max-width: 900px) { .page-wrap { grid-template-columns: 1fr; } }

/* SECTIE */
.sectie { background: #fff; border-radius: 12px; padding: 24px 28px; margin-bottom: 20px; box-shadow: 0 1px 8px rgba(0,0,0,.06); }
.sectie h2 { font-size: 16px; font-weight: 800; color: var(--navy); margin-bottom: 16px; display: flex; align-items: center; gap: 9px; padding-bottom: 12px; border-bottom: 2px solid #f0f4f8; }
.sectie h2 i { color: var(--blue); }
.beschrijving p { font-size: 14.5px; color: #374151; line-height: 1.7; margin-bottom: 10px; }

/* Dagprogramma accordion */
.dag-item { border: 1px solid #e8eef7; border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
.dag-kop { display: flex; align-items: center; gap: 12px; padding: 13px 16px; cursor: pointer; background: #f8faff; transition: background .15s; user-select: none; }
.dag-kop:hover { background: #eff6ff; }
.dag-num { width: 28px; height: 28px; background: var(--navy); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; }
.dag-kop-info { flex: 1; }
.dag-kop-titel { font-size: 13.5px; font-weight: 700; color: var(--navy); }
.dag-kop-sub { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }
.dag-chevron { color: #94a3b8; font-size: 12px; transition: transform .2s; }
.dag-item.open .dag-chevron { transform: rotate(180deg); }
.dag-body { display: none; padding: 14px 16px 14px 56px; font-size: 13.5px; color: #374151; line-height: 1.65; }
.dag-item.open .dag-body { display: block; }

/* Haltes */
.haltes-lijst { display: flex; flex-direction: column; gap: 0; }
.halte-item { display: flex; align-items: flex-start; gap: 14px; padding: 12px 0; position: relative; }
.halte-item:not(:last-child)::after { content: ''; position: absolute; left: 11px; top: 36px; bottom: -4px; width: 2px; background: #e2e8f0; }
.halte-dot { width: 24px; height: 24px; border-radius: 50%; background: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
.halte-dot i { font-size: 10px; color: #fff; }
.halte-naam { font-size: 14px; font-weight: 600; color: var(--navy); }
.halte-adres { font-size: 12.5px; color: #64748b; margin-top: 2px; }
.halte-tijd { margin-left: auto; font-size: 13px; font-weight: 700; color: var(--blue); white-space: nowrap; flex-shrink: 0; background: #eff6ff; padding: 3px 9px; border-radius: 20px; }

/* Opties */
.opties-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 10px; }
.optie-card { border: 1px solid #e8eef7; border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 4px; }
.optie-naam { font-size: 13.5px; font-weight: 700; color: var(--navy); }
.optie-oms  { font-size: 12px; color: #64748b; }
.optie-prijs { font-size: 15px; font-weight: 800; color: var(--green); margin-top: 4px; }

/* ── BOOKING WIDGET ── */
.widget { background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden; position: sticky; top: 78px; }
.widget-prijs { background: var(--navy); padding: 22px 24px; }
.widget-prijs-label { font-size: 12px; color: rgba(255,255,255,.6); font-weight: 500; margin-bottom: 4px; }
.widget-prijs-val { font-size: 34px; font-weight: 900; color: #fff; line-height: 1; }
.widget-prijs-sub { font-size: 12.5px; color: rgba(255,255,255,.5); margin-top: 4px; }
.widget-body { padding: 20px 24px; }
.widget-info { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.widget-info-row { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #374151; }
.widget-info-row i { width: 18px; text-align: center; color: var(--blue); }
.widget-info-row strong { margin-left: auto; font-weight: 600; color: var(--navy); }
.widget-sep { border: none; border-top: 1px solid #f1f5f9; margin: 14px 0; }
.widget-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
.widget-badge { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.wb-vert  { background: #fef3c7; color: #92400e; }
.wb-sgr   { background: #dcfce7; color: #15803d; }
.wb-mol   { background: #ede9fe; color: #6d28d9; }

/* Plaatsen indicator */
.widget-plaatsen { background: <?= ($bezetting['pct_vrij'] <= 20 && !$isVol) ? '#fff7ed' : ($isVol ? '#fee2e2' : '#f0fdf4') ?>; border: 1px solid <?= ($bezetting['pct_vrij'] <= 20 && !$isVol) ? '#fed7aa' : ($isVol ? '#fca5a5' : '#bbf7d0') ?>; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: <?= ($bezetting['pct_vrij'] <= 20 && !$isVol) ? '#9a3412' : ($isVol ? '#b91c1c' : '#15803d') ?>; }
.widget-plaatsen i { font-size: 14px; }
.plaatsen-balk { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.plaatsen-balk-fill { height: 100%; background: <?= $isVol ? '#b91c1c' : ($bezetting['pct_vrij'] <= 20 ? '#f59e0b' : '#16a34a') ?>; width: <?= $bezetting['pct_vol'] ?>%; border-radius: 3px; transition: width .3s; }
.plaatsen-pct { margin-left: auto; font-variant-numeric: tabular-nums; white-space: nowrap; }

/* Direct boeken: focus op reserveren, info ingeklapt */
body.booking-direct .hero { height: clamp(160px, 28vw, 220px); }
body.booking-direct .hero-content h1 { font-size: clamp(18px, 3.5vw, 26px); }
body.booking-direct .page-wrap > .content-kolom { display: none; }
body.booking-direct .page-wrap { grid-template-columns: minmax(0, 1fr); max-width: 440px; padding-top: 20px; }
body.booking-direct .widget { position: static; box-shadow: 0 8px 32px rgba(0,0,0,.12); }
body.booking-direct .nav-back span { display: none; }

.btn-boek { width: 100%; padding: 15px; background: <?= $isVol ? '#94a3b8' : 'var(--green)' ?>; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 800; cursor: <?= $isVol ? 'default' : 'pointer' ?>; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background .15s; letter-spacing: .3px; }
.btn-boek:hover:not(:disabled) { background: #15803d; }

.widget-tel { margin-top: 14px; text-align: center; font-size: 12.5px; color: #94a3b8; }
.widget-tel a { color: var(--blue); font-weight: 600; text-decoration: none; }
.widget-tel a:hover { text-decoration: underline; }

/* ── BOOKING FORM PANEL ── */
.form-panel { display: none; }
.form-panel.open { display: block; }
.form-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 300; display: flex; align-items: flex-end; justify-content: center; padding: 0; }
@media (min-width: 700px) { .form-overlay { align-items: center; padding: 20px; } }
.form-sheet { background: #fff; width: 100%; max-width: 660px; max-height: 92vh; border-radius: 20px 20px 0 0; overflow: hidden; display: flex; flex-direction: column; }
@media (min-width: 700px) { .form-sheet { border-radius: 16px; } }
.form-header { background: var(--navy); color: #fff; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.form-header h3 { font-size: 16px; font-weight: 800; }
.form-close { background: rgba(255,255,255,.12); border: none; color: #fff; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }

/* Stappen */
.stap-balk { background: #f8faff; border-bottom: 1px solid #e8eef7; padding: 12px 24px; display: flex; gap: 4px; flex-shrink: 0; }
.stap-dot { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.stap-num { width: 26px; height: 26px; border-radius: 50%; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; background: #e2e8f0; color: #64748b; }
.stap-lijn { flex: 1; height: 2px; background: #e2e8f0; align-self: center; margin: 0 2px; }
.stap-dot.actief .stap-num { background: var(--navy); color: #fff; }
.stap-dot.klaar  .stap-num { background: var(--green); color: #fff; }
.stap-dot .stap-label { font-size: 10px; color: #94a3b8; font-weight: 600; white-space: nowrap; }
.stap-dot.actief .stap-label { color: var(--navy); font-weight: 700; }

.form-body { padding: 20px 24px; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; flex: 1; min-height: 0; }
.form-footer { padding: 14px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; flex-shrink: 0; background: #fff; }

/* Formulier velden */
.frij { display: grid; gap: 12px; margin-bottom: 12px; }
.frij-2 { grid-template-columns: 1fr 1fr; }
.fveld label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
.fveld label span { color: #b91c1c; }
.fveld input, .fveld select, .fveld textarea {
    width: 100%; padding: 9px 12px; border: 1.5px solid #d1d5db; border-radius: 7px;
    font-size: 13.5px; color: #1a2533; font-family: inherit; background: #fff;
    transition: border-color .15s; }
.fveld input:focus, .fveld select:focus, .fveld textarea:focus {
    outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0,74,173,.08); }
.fveld.fout input, .fveld.fout select { border-color: #b91c1c; }
.fveld .fout-msg { font-size: 11px; color: #b91c1c; margin-top: 3px; display: none; }
.fveld.fout .fout-msg { display: block; }

/* Persoon blok */
.persoon-blok { background: #f8faff; border: 1px solid #e8eef7; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
.persoon-blok-titel { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--navy); margin-bottom: 12px; }

/* Opties checkboxes */
.optie-keuze { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid #e8eef7; border-radius: 8px; margin-bottom: 8px; cursor: pointer; transition: border-color .15s; }
.optie-keuze:hover { border-color: var(--blue); }
.optie-keuze input[type=checkbox] { width: 18px; height: 18px; margin-top: 2px; cursor: pointer; flex-shrink: 0; }
.optie-keuze-info { flex: 1; }
.optie-keuze-naam { font-size: 13.5px; font-weight: 600; color: var(--navy); }
.optie-keuze-oms  { font-size: 12px; color: #64748b; margin-top: 2px; }
.optie-keuze-prijs { font-size: 14px; font-weight: 800; color: var(--green); }

/* Prijsoverzicht */
.prijs-overzicht { background: #f8faff; border: 1px solid #e8eef7; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; }
.prijs-rij { display: flex; justify-content: space-between; font-size: 13.5px; color: #374151; padding: 5px 0; }
.prijs-rij.totaal { font-size: 16px; font-weight: 800; color: var(--navy); padding-top: 10px; border-top: 2px solid #e2e8f0; margin-top: 4px; }

/* Knoppen */
.btn-v { padding: 11px 22px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: .15s; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; }
.btn-blauw { background: var(--navy); color: #fff; }
.btn-blauw:hover { background: var(--blue); }
.btn-groen { background: var(--green); color: #fff; }
.btn-groen:hover { background: #15803d; }
.btn-grijs { background: #f1f5f9; color: #374151; }
.btn-grijs:hover { background: #e2e8f0; }
.btn-full { width: 100%; justify-content: center; padding: 14px; font-size: 15px; }

/* Fout banner */
.form-fout { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 7px; padding: 10px 14px; margin-bottom: 12px; font-size: 13px; display: none; }

/* Nacht mode voor halte selector */
.halte-select { width: 100%; padding: 10px 12px; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: 13.5px; color: #1a2533; font-family: inherit; background: #fff; }

@media (max-width: 640px) {
    .frij-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body<?= $bookingDirect ? ' class="booking-direct"' : '' ?>>

<!-- NAV -->
<nav class="nav">
    <a href="index.php" class="nav-logo">
        <i class="fa-solid fa-route"></i> Coach Travel <span>× Berkhout</span>
    </a>
    <a href="index.php" class="nav-back">
        <i class="fa-solid fa-arrow-left"></i> Alle reizen
    </a>
</nav>

<!-- HERO -->
<div class="hero">
    <?php if ($r['foto_pad']): ?>
        <?= busreis_foto_picture((string) $r['foto_pad'], (string) $r['titel'], 'hero-img', 'hero') ?>
    <?php else: ?>
        <div class="hero-placeholder"><i class="fa-solid fa-<?= $r['type']==='meerdaags'?'moon':'bus' ?>"></i></div>
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-breadcrumb">
            <a href="index.php">Reizen</a>
            <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i>
            <span><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></span>
        </div>
        <div class="hero-badges">
            <?php if ($r['type']==='dagtocht'): ?><span class="hero-badge hb-type"><i class="fa-solid fa-sun"></i> Dagtocht</span>
            <?php else: ?><span class="hero-badge hb-meer"><i class="fa-solid fa-moon"></i> <?= $nDagen ?> dagen</span><?php endif; ?>
            <?php if ($r['vertrekgarantie']): ?><span class="hero-badge hb-vert"><i class="fa-solid fa-shield-check"></i> Vertrekgarantie</span><?php endif; ?>
            <?php if ($r['anvr_sgr']): ?><span class="hero-badge hb-sgr"><i class="fa-solid fa-certificate"></i> SGR</span><?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></h1>
        <div class="hero-meta">
            <div class="hero-meta-item"><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($datumStr, ENT_QUOTES) ?></div>
            <?php if ($r['vertrek_tijd']): ?>
            <div class="hero-meta-item"><i class="fa-solid fa-clock"></i> Vertrek <?= substr($r['vertrek_tijd'],0,5) ?></div>
            <?php endif; ?>
            <?php if ($r['bestemming']): ?>
            <div class="hero-meta-item"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($r['bestemming'], ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PAGE WRAP -->
<div class="page-wrap">

    <!-- LINKS: content -->
    <div class="content-kolom">
        <!-- Beschrijving -->
        <?php if ($r['beschrijving']): ?>
        <div class="sectie beschrijving">
            <h2><i class="fa-solid fa-circle-info"></i> Over deze reis</h2>
            <?php foreach (explode("\n", nl2br(htmlspecialchars($r['beschrijving'], ENT_QUOTES))) as $regel): ?>
                <p><?= $regel ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Dagprogramma (meerdaags) -->
        <?php if ($r['type']==='meerdaags' && !empty($dagprog)): ?>
        <div class="sectie">
            <h2><i class="fa-solid fa-list-check"></i> Dagprogramma</h2>
            <?php foreach ($dagprog as $dp): ?>
            <div class="dag-item">
                <div class="dag-kop" onclick="this.closest('.dag-item').classList.toggle('open')">
                    <div class="dag-num"><?= (int)$dp['dag_nummer'] ?></div>
                    <div class="dag-kop-info">
                        <div class="dag-kop-titel"><?= htmlspecialchars($dp['titel'] ?? 'Dag '.$dp['dag_nummer'], ENT_QUOTES) ?></div>
                        <div class="dag-kop-sub">Dag <?= (int)$dp['dag_nummer'] ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down dag-chevron"></i>
                </div>
                <div class="dag-body"><?= nl2br(htmlspecialchars($dp['omschrijving'] ?? '', ENT_QUOTES)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Hotel (meerdaags) -->
        <?php if ($r['type']==='meerdaags' && $r['hotel_naam']): ?>
        <div class="sectie">
            <h2><i class="fa-solid fa-hotel"></i> Accommodatie</h2>
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:44px; height:44px; background:#eff6ff; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid fa-hotel" style="color:var(--blue); font-size:20px;"></i>
                </div>
                <div>
                    <div style="font-size:15px; font-weight:700; color:var(--navy);"><?= htmlspecialchars($r['hotel_naam'], ENT_QUOTES) ?></div>
                    <?php if ($r['hotel_sterren']): ?>
                    <div style="font-size:13px; color:#f59e0b;"><?= str_repeat('★',(int)$r['hotel_sterren']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Haltes -->
        <?php if (!empty($haltes)): ?>
        <div class="sectie">
            <h2><i class="fa-solid fa-map-pin"></i> Opstapplaatsen</h2>
            <div class="haltes-lijst">
            <?php foreach ($haltes as $i => $h): ?>
                <div class="halte-item">
                    <div class="halte-dot"><i class="fa-solid fa-<?= $i===0 ? 'circle-dot' : ($i===count($haltes)-1 ? 'flag' : 'map-pin') ?>"></i></div>
                    <div style="flex:1;">
                        <div class="halte-naam"><?= htmlspecialchars($h['naam'], ENT_QUOTES) ?></div>
                        <?php if ($h['adres']): ?>
                        <div class="halte-adres"><?= htmlspecialchars($h['adres'], ENT_QUOTES) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($h['vertrek_tijd']): ?>
                    <div class="halte-tijd"><?= substr($h['vertrek_tijd'],0,5) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bijboekingen -->
        <?php if (!empty($opties)): ?>
        <div class="sectie">
            <h2><i class="fa-solid fa-ticket"></i> Bijboekingen</h2>
            <div class="opties-grid">
            <?php foreach ($opties as $o): ?>
                <div class="optie-card">
                    <div class="optie-naam"><?= htmlspecialchars($o['naam'], ENT_QUOTES) ?></div>
                    <?php if ($o['beschrijving']): ?><div class="optie-oms"><?= htmlspecialchars($o['beschrijving'], ENT_QUOTES) ?></div><?php endif; ?>
                    <div class="optie-prijs">€ <?= number_format((float)$o['prijs'],2,',','.') ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inbegrepen -->
        <div class="sectie">
            <h2><i class="fa-solid fa-circle-check"></i> Inbegrepen in de prijs</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px;">
                <?php
                $items = ['Vervoer per luxe touringcar', 'Begeleiding door reisleider', 'Opstap dicht bij u in de regio'];
                if ($r['type']==='meerdaags') { $items[] = 'Verblijf in hotel'; $items[] = 'Dagprogramma'; }
                if ($r['anvr_sgr']) { $items[] = 'SGR reisgeld bescherming'; }
                foreach ($items as $item): ?>
                <div style="display:flex; align-items:center; gap:7px; font-size:13.5px; color:#374151;">
                    <i class="fa-solid fa-check" style="color:var(--green); font-size:12px; width:14px;"></i>
                    <?= htmlspecialchars($item, ENT_QUOTES) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Trust -->
        <?php if ($r['anvr_sgr']): ?>
        <div style="background:#fff; border-radius:12px; padding:18px 24px; box-shadow:0 1px 8px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
            <i class="fa-solid fa-shield-halved" style="font-size:28px; color:#d97706;"></i>
            <div>
                <div style="font-size:13px; font-weight:700; color:var(--navy);">Uw reisgeld is beschermd</div>
                <div style="font-size:12px; color:#64748b;">Coach Travel is aangesloten bij SGR en het Calamiteitenfonds.</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RECHTS: booking widget -->
    <div>
        <div class="widget">
            <div class="widget-prijs">
                <div class="widget-prijs-label">Prijs per persoon</div>
                <div class="widget-prijs-val">€ <?= number_format((float)$r['prijs_pp'],0,',','.') ?>
                    <?php if ($r['reserveringskosten'] > 0): ?>
                    <span style="font-size:14px; font-weight:400; opacity:.6;">+ € <?= number_format((float)$r['reserveringskosten'],0,',','.') ?> reserveringskosten</span>
                    <?php endif; ?>
                </div>
                <?php if ($r['type']==='meerdaags'): ?>
                <div class="widget-prijs-sub">Volledig verzorgd all-in
                    <?php if ($r['toeslag_enkelpersoon'] > 0): ?>
                    &mdash; toeslag 1p kamer € <?= number_format((float)$r['toeslag_enkelpersoon'],0,',','.') ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="widget-body">
                <div class="widget-info">
                    <div class="widget-info-row">
                        <i class="fa-solid fa-calendar"></i> Vertrekdatum
                        <strong><?= strtr(date('d F Y', strtotime($r['datum_van'])), $nl_maanden) ?></strong>
                    </div>
                    <?php if ($r['vertrek_tijd']): ?>
                    <div class="widget-info-row">
                        <i class="fa-solid fa-clock"></i> Vertrektijd
                        <strong><?= substr($r['vertrek_tijd'],0,5) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($r['type']==='meerdaags'): ?>
                    <div class="widget-info-row">
                        <i class="fa-solid fa-moon"></i> Duur
                        <strong><?= $nDagen ?> dagen / <?= $nDagen-1 ?> nachten</strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($vroegboekActief): ?>
                    <div class="widget-info-row" style="background:#fef9c3; padding:6px 10px; border-radius:6px; color:#92400e;">
                        <i class="fa-solid fa-tag"></i> Vroegboekkorting
                        <strong>€ <?= number_format((float)$r['vroegboekkorting'],0,',','.') ?> korting</strong>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Plaatsen -->
                <div class="widget-plaatsen">
                    <?php if ($isVol): ?>
                    <i class="fa-solid fa-ban"></i> Volgeboekt
                    <span class="plaatsen-pct">0% vrij</span>
                    <?php elseif ($bezetting['pct_vrij'] <= 20): ?>
                    <i class="fa-solid fa-fire"></i> Nog <?= $bezetting['pct_vrij'] ?>% beschikbaar
                    <span class="plaatsen-pct"><?= $bezetting['pct_vrij'] ?>% vrij</span>
                    <?php else: ?>
                    <i class="fa-solid fa-circle-check"></i> Nog <?= $bezetting['pct_vrij'] ?>% beschikbaar
                    <span class="plaatsen-pct"><?= $bezetting['pct_vrij'] ?>% vrij</span>
                    <?php endif; ?>
                    <div class="plaatsen-balk"><div class="plaatsen-balk-fill"></div></div>
                </div>

                <div class="widget-badges">
                    <?php if ($r['vertrekgarantie']): ?>
                    <span class="widget-badge wb-vert"><i class="fa-solid fa-shield-check"></i> Vertrekgarantie</span>
                    <?php endif; ?>
                    <?php if ($r['anvr_sgr']): ?>
                    <span class="widget-badge wb-sgr"><i class="fa-solid fa-certificate"></i> SGR</span>
                    <?php endif; ?>
                    <span class="widget-badge wb-mol"><i class="fa-solid fa-lock"></i> Veilig betalen</span>
                </div>

                <?php if (!$isVol): ?>
                <button class="btn-boek" onclick="openForm()">
                    <i class="fa-solid fa-ticket"></i> Nu reserveren
                </button>
                <?php else: ?>
                <button class="btn-boek" disabled>
                    <i class="fa-solid fa-ban"></i> Volgeboekt
                </button>
                <?php endif; ?>

                <div class="widget-tel">
                    Liever bellen? <a href="tel:0854862007">085 - 486 20 07</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$isVol): ?>
<!-- ── BOOKING PANEL ── -->
<div class="form-panel" id="formPanel">
<div class="form-overlay" onclick="sluitForm(event)">
<div class="form-sheet" onclick="event.stopPropagation()">

    <div class="form-header">
        <h3><i class="fa-solid fa-ticket"></i> Reserveren — <?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></h3>
        <button class="form-close" onclick="sluitForm()">×</button>
    </div>

    <!-- Stappenbalk -->
    <div class="stap-balk" id="stapBalk">
        <div class="stap-dot actief" id="stap1dot">
            <div class="stap-num">1</div>
            <div class="stap-label">Deelnemers</div>
        </div>
        <div class="stap-lijn"></div>
        <div class="stap-dot" id="stap2dot">
            <div class="stap-num">2</div>
            <div class="stap-label">Gegevens</div>
        </div>
        <div class="stap-lijn"></div>
        <?php if (!empty($opties)): ?>
        <div class="stap-dot" id="stap3dot">
            <div class="stap-num">3</div>
            <div class="stap-label">Extra's</div>
        </div>
        <div class="stap-lijn"></div>
        <?php endif; ?>
        <div class="stap-dot" id="stap4dot">
            <div class="stap-num"><?= !empty($opties) ? 4 : 3 ?></div>
            <div class="stap-label">Betalen</div>
        </div>
    </div>

    <form method="POST" action="boeken.php" id="reisForm">
        <input type="hidden" name="busreis_id" value="<?= $id ?>">
        <?php if ($bronPartner !== ''): ?>
        <input type="hidden" name="bron_partner" value="<?= htmlspecialchars($bronPartner, ENT_QUOTES) ?>">
        <?php endif; ?>
        <input type="hidden" name="opties_json" id="optiesJson" value="">
        <input type="hidden" name="totaal_prijs" id="totaalPrijs" value="">

        <!-- STAP 1: Deelnemers + halte -->
        <div class="form-body" id="stap1">
            <div class="frij frij-2">
                <div class="fveld">
                    <label>Aantal deelnemers <span>*</span></label>
                    <select name="aantal_deelnemers" id="aantalSel" onchange="updateDeelnemers(this.value)">
                        <?php for ($n=1; $n<=min($vrij,20); $n++): ?>
                        <option value="<?= $n ?>"><?= $n ?> <?= $n===1?'persoon':'personen' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php if (!empty($haltes)): ?>
                <div class="fveld">
                    <label>Opstapplaats</label>
                    <select name="halte_id" class="halte-select">
                        <option value="">— Kies uw opstapplaats —</option>
                        <?php foreach ($haltes as $h): ?>
                        <option value="<?= $h['id'] ?>">
                            <?= htmlspecialchars($h['naam'], ENT_QUOTES) ?>
                            <?php if ($h['vertrek_tijd']): ?>(<?= substr($h['vertrek_tijd'],0,5) ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($r['type']==='meerdaags' && $r['toeslag_enkelpersoon'] > 0): ?>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:12px; background:#f8faff; border:1px solid #e8eef7; border-radius:8px; font-size:13.5px; color:#374151; margin-bottom:12px;">
                <input type="checkbox" name="enkelpersoon" id="enkelpersoonChk" value="1" style="width:18px;height:18px;" onchange="berekenPrijs()">
                <span>Enkelpersoonskamer gewenst <strong>(+ € <?= number_format((float)$r['toeslag_enkelpersoon'],2,',','.') ?></strong> per persoon)</span>
            </label>
            <?php endif; ?>

            <div id="deelnemersBlokkken"></div>

            <!-- Live prijsoverzicht -->
            <div class="prijs-overzicht" id="prijsBox"></div>
        </div>

        <!-- STAP 2: Contactgegevens hoofdboeker -->
        <div class="form-body" id="stap2" style="display:none;">
            <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Vul de gegevens in van de hoofdboeker (de persoon die betaalt en verantwoordelijk is voor de boeking).</p>
            <div class="frij frij-2">
                <div class="fveld"><label>Voornaam <span>*</span></label><input type="text" name="voornaam" autocomplete="given-name" required></div>
                <div class="fveld"><label>Achternaam <span>*</span></label><input type="text" name="achternaam" autocomplete="family-name" required></div>
            </div>
            <div class="frij">
                <div class="fveld"><label>E-mailadres <span>*</span></label><input type="email" name="email" autocomplete="email" required></div>
            </div>
            <div class="frij frij-2">
                <div class="fveld"><label>Telefoonnummer <span>*</span></label><input type="tel" name="telefoon" autocomplete="tel" required></div>
                <div class="fveld">
                    <label>Telefoon thuisblijver <span>*</span>
                        <span style="font-size:10px; color:#94a3b8; font-weight:400;"> (noodcontact)</span>
                    </label>
                    <input type="tel" name="telefoon_thuisblijver" required>
                </div>
            </div>
            <div class="frij frij-2">
                <div class="fveld"><label>Adres</label><input type="text" name="adres" autocomplete="street-address"></div>
                <div class="fveld"><label>Postcode</label><input type="text" name="postcode" autocomplete="postal-code" maxlength="7"></div>
            </div>
            <div class="frij">
                <div class="fveld"><label>Woonplaats</label><input type="text" name="woonplaats" autocomplete="address-level2"></div>
            </div>
            <div class="frij">
                <div class="fveld"><label>Opmerkingen / speciale wensen</label><textarea name="opmerkingen" rows="3" placeholder="bijv. dieetwensen, rolstoeltoegankelijkheid, etc."></textarea></div>
            </div>
        </div>

        <!-- STAP 3: Extra opties (alleen als er opties zijn) -->
        <?php if (!empty($opties)): ?>
        <div class="form-body" id="stap3" style="display:none;">
            <p style="font-size:13px; color:#64748b; margin-bottom:14px;">Voeg optionele extras toe aan uw reis.</p>
            <?php foreach ($opties as $o): ?>
            <label class="optie-keuze">
                <input type="checkbox" class="optie-chk"
                       data-naam="<?= htmlspecialchars($o['naam'], ENT_QUOTES) ?>"
                       data-prijs="<?= (float)$o['prijs'] ?>"
                       value="<?= $o['id'] ?>"
                       onchange="berekenPrijs()">
                <div class="optie-keuze-info">
                    <div class="optie-keuze-naam"><?= htmlspecialchars($o['naam'], ENT_QUOTES) ?></div>
                    <?php if ($o['beschrijving']): ?><div class="optie-keuze-oms"><?= htmlspecialchars($o['beschrijving'], ENT_QUOTES) ?></div><?php endif; ?>
                </div>
                <div class="optie-keuze-prijs">+ € <?= number_format((float)$o['prijs'],2,',','.') ?></div>
            </label>
            <?php endforeach; ?>
            <div class="prijs-overzicht" id="prijsBoxOpties" style="margin-top:14px;"></div>
        </div>
        <?php endif; ?>

        <!-- STAP 4: Overzicht + betalen -->
        <div class="form-body" id="stap<?= !empty($opties) ? 4 : 3 ?>" style="display:none;">
            <div id="overzicht"></div>
            <div class="prijs-overzicht" id="prijsBoxFinal"></div>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 14px; font-size:12.5px; color:#15803d; display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <i class="fa-solid fa-lock"></i>
                U wordt doorgestuurd naar de beveiligde betaalomgeving van Mollie. Uw gegevens zijn veilig.
            </div>
            <div id="formFout" class="form-fout"></div>
        </div>

        <!-- Footer knoppen -->
        <div class="form-footer">
            <button type="button" class="btn-v btn-grijs" id="btnTerug" onclick="vorigeStap()" style="display:none;">
                <i class="fa-solid fa-arrow-left"></i> Terug
            </button>
            <button type="button" class="btn-v btn-blauw" id="btnVolgende" onclick="volgendeStap()">
                Volgende <i class="fa-solid fa-arrow-right"></i>
            </button>
            <button type="submit" class="btn-v btn-groen" id="btnBetalen" style="display:none;">
                <i class="fa-solid fa-lock"></i> Betalen via Mollie
            </button>
        </div>
    </form>
</div>
</div>
</div>
<?php endif; ?>

<script>
const PRIJS_PP     = <?= (float)$r['prijs_pp'] ?>;
const RESERVK      = <?= (float)$r['reserveringskosten'] ?>;
const TOESLAG_EP   = <?= (float)($r['toeslag_enkelpersoon'] ?? 0) ?>;
const VROEGBOEK_PP = <?= $vroegboekActief ? (float)$r['vroegboekkorting'] : 0 ?>;
const HEEFT_OPTIES = <?= !empty($opties) ? 'true' : 'false' ?>;
const MAX_STAP     = <?= !empty($opties) ? 4 : 3 ?>;
const BOOKING_DIRECT = <?= $bookingDirect ? 'true' : 'false' ?>;

let huidigeStap = 1;

function openForm() {
    document.getElementById('formPanel').classList.add('open');
    document.body.style.overflow = 'hidden';
    updateDeelnemers(1);
    berekenPrijs();
}
function sluitForm(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('formPanel').classList.remove('open');
    document.body.style.overflow = '';
}

function updateDeelnemers(n) {
    n = parseInt(n) || 1;
    const c = document.getElementById('deelnemersBlokkken');
    c.innerHTML = '';
    for (let i = 0; i < n; i++) {
        const isH = i === 0;
        c.innerHTML += `
        <div class="persoon-blok">
            <div class="persoon-blok-titel">${isH ? '👤 Hoofdboeker' : `Persoon ${i+1}`}</div>
            <div class="frij frij-2">
                <div class="fveld"><label>Voornaam *</label><input type="text" name="deelnemers[${i}][voornaam]" required></div>
                <div class="fveld"><label>Achternaam *</label><input type="text" name="deelnemers[${i}][achternaam]" required></div>
            </div>
        </div>`;
    }
    berekenPrijs();
}

function berekenPrijs() {
    const n   = parseInt(document.getElementById('aantalSel')?.value || 1);
    const ep  = document.getElementById('enkelpersoonChk')?.checked ? 1 : 0;
    let optiesTotal = 0, optiesArr = [];
    document.querySelectorAll('.optie-chk').forEach(chk => {
        if (chk.checked) {
            const p = parseFloat(chk.dataset.prijs) || 0;
            optiesTotal += p;
            optiesArr.push({ id: chk.value, naam: chk.dataset.naam, prijs: p });
        }
    });
    const vroegboekTotaal = VROEGBOEK_PP * n;
    const subtotaal = (PRIJS_PP * n) + (TOESLAG_EP * n * ep) + (optiesTotal * n) - vroegboekTotaal;
    const totaal    = subtotaal + RESERVK;

    const html = `
        <div class="prijs-rij"><span>Prijs (${n} pers. × € ${PRIJS_PP.toFixed(2).replace('.',',')})</span><span>€ ${(PRIJS_PP*n).toFixed(2).replace('.',',')}</span></div>
        ${ep ? `<div class="prijs-rij"><span>Toeslag 1p kamer (${n} pers.)</span><span>€ ${(TOESLAG_EP*n).toFixed(2).replace('.',',')}</span></div>` : ''}
        ${optiesArr.map(o=>`<div class="prijs-rij"><span>${o.naam} (${n}×)</span><span>€ ${(o.prijs*n).toFixed(2).replace('.',',')}</span></div>`).join('')}
        ${VROEGBOEK_PP > 0 ? `<div class="prijs-rij" style="color:#15803d"><span>Vroegboekkorting (${n}× € ${VROEGBOEK_PP.toFixed(2).replace('.',',')})</span><span>- € ${vroegboekTotaal.toFixed(2).replace('.',',')}</span></div>` : ''}
        <div class="prijs-rij"><span>Reserveringskosten</span><span>€ ${RESERVK.toFixed(2).replace('.',',')}</span></div>
        <div class="prijs-rij totaal"><span>Totaal</span><span>€ ${totaal.toFixed(2).replace('.',',')}</span></div>`;

    ['prijsBox','prijsBoxOpties','prijsBoxFinal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    });

    document.getElementById('optiesJson').value = JSON.stringify(optiesArr);
    document.getElementById('totaalPrijs').value = totaal.toFixed(2);
    updateOverzicht(n);
}

function updateOverzicht(n) {
    const el = document.getElementById('overzicht');
    if (!el) return;
    const voornaam = document.querySelector('[name="voornaam"]')?.value || '—';
    const achternaam = document.querySelector('[name="achternaam"]')?.value || '';
    const email = document.querySelector('[name="email"]')?.value || '—';
    el.innerHTML = `
        <div style="background:#f8faff; border:1px solid #e8eef7; border-radius:8px; padding:14px 16px; margin-bottom:12px;">
            <div style="font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--navy); margin-bottom:10px;">Uw boeking</div>
            <div style="font-size:13px; color:#374151; line-height:1.8;">
                <strong><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></strong><br>
                📅 <?= htmlspecialchars($datumStr, ENT_QUOTES) ?><br>
                👥 ${n} deelnemer${n>1?'s':''}<br>
                📧 ${email || '—'}
            </div>
        </div>`;
}

function volgendeStap() {
    if (!valideerStap(huidigeStap)) return;
    setStap(huidigeStap + 1);
}
function vorigeStap() { setStap(huidigeStap - 1); }

function valideerStap(stap) {
    if (stap === 2) {
        const verplicht = ['voornaam','achternaam','email','telefoon','telefoon_thuisblijver'];
        let ok = true;
        verplicht.forEach(naam => {
            const el = document.querySelector(`[name="${naam}"]`);
            if (el && !el.value.trim()) {
                el.closest('.fveld')?.classList.add('fout');
                ok = false;
            } else if (el) {
                el.closest('.fveld')?.classList.remove('fout');
            }
        });
        if (!ok) { document.getElementById('formFout').style.display=''; document.getElementById('formFout').textContent='Vul alle verplichte velden in.'; }
        return ok;
    }
    return true;
}

function setStap(n) {
    const totaal = MAX_STAP;
    for (let i = 1; i <= totaal; i++) {
        const el = document.getElementById('stap' + i);
        if (el) el.style.display = i === n ? 'block' : 'none';
        const dot = document.getElementById('stap' + i + 'dot');
        if (dot) {
            dot.classList.remove('actief','klaar');
            if (i < n) dot.classList.add('klaar');
            else if (i === n) dot.classList.add('actief');
        }
    }
    huidigeStap = n;
    document.getElementById('btnTerug').style.display = n > 1 ? '' : 'none';
    document.getElementById('btnVolgende').style.display = n < totaal ? '' : 'none';
    document.getElementById('btnBetalen').style.display = n === totaal ? '' : 'none';
    if (n === totaal) { berekenPrijs(); updateOverzicht(parseInt(document.getElementById('aantalSel')?.value||1)); }
    document.querySelector('.form-sheet')?.scrollTo(0, 0);
}

// Eerste dag accordion open
document.addEventListener('DOMContentLoaded', () => {
    const eerste = document.querySelector('.dag-item');
    if (eerste) eerste.classList.add('open');
    if (BOOKING_DIRECT && !<?= $isVol ? 'true' : 'false' ?>) {
        openForm();
    }
});
</script>

</body>
</html>
