<?php
declare(strict_types=1);
// Bestand: reizen/index.php — publieke cataloguspagina busreizen

require_once __DIR__ . '/../beheer/includes/db.php';
require_once __DIR__ . '/_prijs.php';
require_once __DIR__ . '/_media.php';

// Filters
$filterType = $_GET['type'] ?? '';
$filterMaand = $_GET['maand'] ?? '';
$filterMax = (int)($_GET['max'] ?? 0);

$where  = ["b.status = 'gepubliceerd'", "b.datum_van >= CURDATE()"];
$params = [];

if (in_array($filterType, ['dagtocht','meerdaags'], true)) {
    $where[] = 'b.type = ?';
    $params[] = $filterType;
}
if ($filterMaand && preg_match('/^\d{4}-\d{2}$/', $filterMaand)) {
    $where[] = "DATE_FORMAT(b.datum_van,'%Y-%m') = ?";
    $params[] = $filterMaand;
}
if ($filterMax > 0) {
    $where[] = 'b.prijs_pp <= ?';
    $params[] = $filterMax;
}

$sql = "SELECT b.*,
    (SELECT COUNT(*) FROM busreis_boekingen bk
     WHERE bk.busreis_id=b.id AND bk.status != 'geannuleerd') AS boekingen,
    (SELECT COUNT(*) FROM busreis_haltes h WHERE h.busreis_id=b.id) AS haltes
    FROM busreizen b
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.datum_van ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reizen = $stmt->fetchAll();

// Beschikbare maanden voor filter
$maanden = $pdo->query("SELECT DISTINCT DATE_FORMAT(datum_van,'%Y-%m') AS ym,
    DATE_FORMAT(datum_van,'%M %Y') AS label
    FROM busreizen WHERE status='gepubliceerd' AND datum_van>=CURDATE()
    ORDER BY datum_van ASC")->fetchAll();

$nl_maanden = ['January'=>'Januari','February'=>'Februari','March'=>'Maart',
    'April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Augustus',
    'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'December'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Ontdek onze busreizen en dagtochten. Comfortabel op reis met Coach Travel &amp; Berkhout Reizen. Volledig verzorgd van deur tot deur.">
<title>Busreizen &amp; Dagtochten | Coach Travel &times; Berkhout Reizen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --navy:   #002855;
    --blue:   #004aad;
    --gold:   #f59e0b;
    --green:  #16a34a;
    --light:  #f0f4f8;
    --card-r: 14px;
}
html { scroll-behavior: smooth; }
body { font-family: 'Inter', sans-serif; background: var(--light); color: #1a2533; }

/* ── NAV ── */
.nav {
    background: var(--navy);
    padding: 0 clamp(16px, 4vw, 60px);
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 16px rgba(0,0,0,.25);
}
.nav-logo { font-size: 18px; font-weight: 900; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 10px; }
.nav-logo span { color: #5bc8f5; }
.nav-right { display: flex; align-items: center; gap: 18px; }
.nav-tel { color: rgba(255,255,255,.75); font-size: 13.5px; text-decoration: none; display: flex; align-items: center; gap: 6px; }
.nav-tel:hover { color: #fff; }
.nav-beheer { background: rgba(255,255,255,.1); color: rgba(255,255,255,.75); padding: 6px 14px; border-radius: 6px; font-size: 12.5px; text-decoration: none; border: 1px solid rgba(255,255,255,.15); }
.nav-beheer:hover { background: rgba(255,255,255,.18); color: #fff; }

/* ── HERO ── */
.hero {
    background: #001428;
    padding: clamp(64px, 10vw, 100px) clamp(16px, 4vw, 60px);
    text-align: center;
    position: relative;
    overflow: hidden;
    min-height: 440px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hero-bg {
    position: absolute; inset: 0;
    background-image: url(/uploads/reizen/hero.webp);
    background-position: center 55%;
    filter: brightness(.52) saturate(1.15);
    transform: scale(1.03);
    transition: transform 10s ease;
    will-change: transform;
}
.hero:hover .hero-bg { transform: scale(1.07); }
.hero::before {
    content: '';
    position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(
        to bottom,
        rgba(0,15,45,.2) 0%,
        rgba(0,25,65,.5) 55%,
        rgba(0,8,25,.82) 100%
    );
}
.hero-inner { position: relative; max-width: 700px; margin: 0 auto; }
.hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.3); color: #fbbf24; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: .5px; margin-bottom: 20px; }
.hero h1 { font-size: clamp(28px, 5vw, 46px); font-weight: 900; color: #fff; line-height: 1.15; margin-bottom: 16px; }
.hero h1 em { color: #5bc8f5; font-style: normal; }
.hero p { font-size: clamp(14px, 2vw, 17px); color: rgba(255,255,255,.7); line-height: 1.6; max-width: 520px; margin: 0 auto 32px; }
.hero-usps { display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; }
.hero-usp { display: flex; align-items: center; gap: 7px; color: rgba(255,255,255,.65); font-size: 13px; }
.hero-usp i { color: #5bc8f5; }

/* ── FILTER ── */
.filter-wrap { background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.08); position: sticky; top: 64px; z-index: 90; }
.filter-inner { max-width: 1200px; margin: 0 auto; padding: 0 clamp(16px, 4vw, 40px); display: flex; align-items: center; gap: 0; }

/* Type tabs */
.type-tabs { display: flex; height: 52px; }
.type-tab { display: flex; align-items: center; gap: 7px; padding: 0 20px; font-size: 13.5px; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; white-space: nowrap; transition: .15s; }
.type-tab:hover { color: var(--navy); }
.type-tab.actief { color: var(--navy); border-bottom-color: var(--blue); }
.type-tab i { font-size: 14px; }

.filter-sep { width: 1px; height: 28px; background: #e2e8f0; margin: 0 12px; flex-shrink: 0; }

.filter-selects { display: flex; gap: 8px; align-items: center; flex: 1; flex-wrap: wrap; padding: 8px 0; }
.filter-selects select { padding: 7px 12px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 13px; color: #374151; background: #f8faff; cursor: pointer; font-family: inherit; }
.filter-selects select:focus { outline: none; border-color: var(--blue); }
.filter-count { margin-left: auto; font-size: 12.5px; color: #94a3b8; white-space: nowrap; }

/* ── GRID ── */
.grid-wrap { max-width: 1200px; margin: 0 auto; padding: clamp(24px, 4vw, 40px) clamp(16px, 4vw, 40px); }
.grid-titel { font-size: 15px; font-weight: 700; color: #002855; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }

/* ── KAART ── */
.kaart {
    background: #fff;
    border-radius: var(--card-r);
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .2s, box-shadow .2s;
    display: flex; flex-direction: column;
    text-decoration: none; color: inherit;
}
.kaart:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
.kaart-foto-wrap { position: relative; padding-top: 56%; overflow: hidden; flex-shrink: 0; }
.kaart-foto { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.kaart:hover .kaart-foto { transform: scale(1.04); }
.kaart-foto-placeholder { position: absolute; inset: 0; background: linear-gradient(135deg, #e8eef7, #d1dce8); display: flex; align-items: center; justify-content: center; }
.kaart-foto-placeholder i { font-size: 40px; color: #b8c8d8; }
.kaart-badges { position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap; }
.kaart-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .3px; backdrop-filter: blur(4px); }
.badge-vert { background: rgba(245,158,11,.9); color: #fff; }
.badge-type-dag { background: rgba(59,130,246,.85); color: #fff; }
.badge-type-meer { background: rgba(109,40,217,.85); color: #fff; }
.badge-vol { background: rgba(185,28,28,.9); color: #fff; }
.badge-vrij { background: rgba(22,163,74,.85); color: #fff; }
.kaart-body { padding: 18px 20px; flex: 1; display: flex; flex-direction: column; }
.kaart-cat { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; margin-bottom: 7px; }
.kaart-titel { font-size: 17px; font-weight: 800; color: var(--navy); line-height: 1.25; margin-bottom: 8px; }
.kaart-dest { font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 5px; margin-bottom: 12px; }
.kaart-info { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; }
.kaart-info-item { font-size: 12.5px; color: #475569; display: flex; align-items: center; gap: 5px; }
.kaart-info-item i { color: #94a3b8; font-size: 12px; }
.kaart-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.kaart-prijs { }
.kaart-prijs-label { font-size: 10.5px; color: #94a3b8; font-weight: 500; }
.kaart-prijs-val { font-size: 22px; font-weight: 900; color: var(--navy); line-height: 1; }
.kaart-prijs-sub { font-size: 11px; color: #94a3b8; }
.kaart-cta { background: var(--navy); color: #fff; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 6px; transition: background .15s; }
.kaart:hover .kaart-cta { background: var(--blue); }

/* Plaatsen indicator */
.plaatsen { font-size: 11.5px; display: flex; align-items: center; gap: 5px; }
.plaatsen.weinig { color: #b91c1c; font-weight: 600; }
.plaatsen.genoeg { color: #16a34a; }
.plaatsen-balk { width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
.plaatsen-balk-inner { height: 100%; border-radius: 2px; }
.vol-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; }
.vol-overlay span { background: #b91c1c; color: #fff; font-size: 14px; font-weight: 800; padding: 8px 20px; border-radius: 6px; letter-spacing: .5px; }

/* ── LEEG ── */
.leeg { grid-column: 1/-1; padding: 80px 20px; text-align: center; }
.leeg i { font-size: 52px; color: #d1dce8; display: block; margin-bottom: 16px; }
.leeg h3 { font-size: 18px; color: #94a3b8; margin-bottom: 8px; }
.leeg p { color: #b8c8d8; font-size: 14px; }

/* ── TRUST ── */
.trust { background: #fff; border-top: 1px solid #e8eef7; margin-top: 40px; padding: 28px clamp(16px,4vw,60px); }
.trust-inner { max-width: 800px; margin: 0 auto; display: flex; align-items: center; justify-content: center; gap: clamp(20px,4vw,48px); flex-wrap: wrap; }
.trust-item { display: flex; flex-direction: column; align-items: center; gap: 5px; }
.trust-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.trust-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; text-align: center; }
.trust-sep { width: 1px; height: 36px; background: #e2e8f0; }

/* ── FOOTER ── */
footer { background: var(--navy); color: rgba(255,255,255,.5); text-align: center; padding: 24px; font-size: 12px; }
footer a { color: rgba(255,255,255,.6); }

/* ── RESPONSIVE ── */
@media (max-width: 640px) {
    .filter-inner { flex-wrap: wrap; gap: 0; }
    .filter-sep { display: none; }
    .type-tabs { width: 100%; }
    .filter-selects { padding-bottom: 10px; }
    .hero-usps { gap: 12px; }
    .nav-beheer { display: none; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a href="index.php" class="nav-logo">
        <i class="fa-solid fa-route"></i>
        Coach Travel <span>× Berkhout</span>
    </a>
    <div class="nav-right">
        <a href="tel:0854862007" class="nav-tel">
            <i class="fa-solid fa-phone"></i> 085 - 486 20 07
        </a>
        <a href="/beheer/reizen/index.php" class="nav-beheer">
            <i class="fa-solid fa-lock"></i> Beheer
        </a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-inner" style="position:relative;z-index:2;">
        <div class="hero-badge">
            <i class="fa-solid fa-shield-check"></i> SGR &amp; Calamiteitenfonds aangesloten
        </div>
        <h1>Reizen met <em>een verhaal</em></h1>
        <p>Comfortabel op reis per luxe touringcar — van uw voordeur tot de bestemming, volledig verzorgd en zonder één zorg.</p>
        <div class="hero-usps">
            <div class="hero-usp"><i class="fa-solid fa-door-open"></i> Deur-tot-deur</div>
            <div class="hero-usp"><i class="fa-solid fa-user-tie"></i> Vaste chauffeur &amp; reisleider</div>
            <div class="hero-usp"><i class="fa-solid fa-users"></i> Klein gezelschap</div>
            <div class="hero-usp"><i class="fa-solid fa-star"></i> Volledig verzorgd</div>
        </div>
    </div>
</section>

<!-- FILTER -->
<div class="filter-wrap">
    <div class="filter-inner">
        <div class="type-tabs">
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>''])) ?>"
               class="type-tab <?= $filterType==='' ? 'actief' : '' ?>">
                <i class="fa-solid fa-list"></i> Alle reizen
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'dagtocht'])) ?>"
               class="type-tab <?= $filterType==='dagtocht' ? 'actief' : '' ?>">
                <i class="fa-solid fa-sun"></i> Dagtochten
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['type'=>'meerdaags'])) ?>"
               class="type-tab <?= $filterType==='meerdaags' ? 'actief' : '' ?>">
                <i class="fa-solid fa-moon"></i> Meerdaagse reizen
            </a>
        </div>
        <div class="filter-sep"></div>
        <form class="filter-selects" method="GET">
            <?php if ($filterType): ?><input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>"><?php endif; ?>
            <select name="maand" onchange="this.form.submit()">
                <option value="">Alle maanden</option>
                <?php foreach ($maanden as $m):
                    $label = strtr($m['label'], $nl_maanden); ?>
                <option value="<?= $m['ym'] ?>" <?= $filterMaand===$m['ym']?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="max" onchange="this.form.submit()">
                <option value="0">Alle prijzen</option>
                <option value="100"  <?= $filterMax===100  ?'selected':'' ?>>Tot € 100</option>
                <option value="250"  <?= $filterMax===250  ?'selected':'' ?>>Tot € 250</option>
                <option value="500"  <?= $filterMax===500  ?'selected':'' ?>>Tot € 500</option>
                <option value="1000" <?= $filterMax===1000 ?'selected':'' ?>>Tot € 1.000</option>
                <option value="1500" <?= $filterMax===1500 ?'selected':'' ?>>Tot € 1.500</option>
            </select>
        </form>
        <div class="filter-count"><?= count($reizen) ?> <?= count($reizen)===1?'reis':'reizen' ?></div>
    </div>
</div>

<!-- GRID -->
<div class="grid-wrap">
    <div class="grid-titel">
        <i class="fa-solid fa-route" style="color:#004aad;"></i>
        <?php if ($filterType==='dagtocht') echo 'Dagtochten';
        elseif ($filterType==='meerdaags') echo 'Meerdaagse reizen';
        else echo 'Alle busreizen &amp; dagtochten'; ?>
    </div>
    <div class="grid" id="reisGrid">
    <?php if (empty($reizen)): ?>
        <div class="leeg">
            <i class="fa-solid fa-compass"></i>
            <h3>Geen reizen gevonden</h3>
            <p>Probeer een andere filter of neem contact met ons op.</p>
        </div>
    <?php else: foreach ($reizen as $r):
        $bezetting = busreis_bezetting((int) $r['boekingen'], (int) $r['max_deelnemers']);
        $vrij     = $bezetting['vrij'];
        $pctVrij  = $bezetting['pct_vrij'];
        $isVol    = $vrij === 0;
        $isWeinig = !$isVol && $pctVrij <= 25;
        $nDagen   = $r['datum_tot'] ? (int)((strtotime($r['datum_tot']) - strtotime($r['datum_van'])) / 86400) + 1 : 1;
        $datumStr = date('d M', strtotime($r['datum_van']));
        if ($r['type']==='meerdaags' && $r['datum_tot']) $datumStr .= ' – '.date('d M Y', strtotime($r['datum_tot']));
        else $datumStr .= ' ' . date('Y', strtotime($r['datum_van']));
    ?>
    <a href="detail.php?slug=<?= urlencode($r['slug']) ?>&amp;boek=1" class="kaart">
        <div class="kaart-foto-wrap">
            <?php if ($r['foto_pad']): ?>
                <?= busreis_foto_picture((string) $r['foto_pad'], (string) $r['titel'], 'kaart-foto', 'card', 'lazy') ?>
            <?php else: ?>
                <div class="kaart-foto-placeholder">
                    <i class="fa-solid fa-<?= $r['type']==='meerdaags'?'moon':'sun' ?>"></i>
                </div>
            <?php endif; ?>
            <div class="kaart-badges">
                <?php if ($r['type']==='dagtocht'): ?>
                <span class="kaart-badge badge-type-dag"><i class="fa-solid fa-sun"></i> Dagtocht</span>
                <?php else: ?>
                <span class="kaart-badge badge-type-meer"><i class="fa-solid fa-moon"></i> <?= $nDagen ?> dagen</span>
                <?php endif; ?>
                <?php if ($r['vertrekgarantie']): ?>
                <span class="kaart-badge badge-vert"><i class="fa-solid fa-shield-check"></i> Vertrekgarantie</span>
                <?php endif; ?>
                <?php if ($isVol): ?>
                <span class="kaart-badge badge-vol">VOL</span>
                <?php endif; ?>
            </div>
            <?php if ($isVol): ?>
            <div class="vol-overlay"><span>VOLGEBOEKT</span></div>
            <?php endif; ?>
        </div>
        <div class="kaart-body">
            <?php if ($r['categorie']): ?>
            <div class="kaart-cat"><?= htmlspecialchars($r['categorie'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <div class="kaart-titel"><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></div>
            <?php if ($r['bestemming']): ?>
            <div class="kaart-dest">
                <i class="fa-solid fa-location-dot"></i>
                <?= htmlspecialchars($r['bestemming'], ENT_QUOTES) ?>
            </div>
            <?php endif; ?>
            <div class="kaart-info">
                <div class="kaart-info-item"><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($datumStr, ENT_QUOTES) ?></div>
                <?php if ($r['vertrek_tijd']): ?>
                <div class="kaart-info-item"><i class="fa-solid fa-clock"></i> <?= substr($r['vertrek_tijd'],0,5) ?></div>
                <?php endif; ?>
                <?php if ($r['haltes'] > 0): ?>
                <div class="kaart-info-item"><i class="fa-solid fa-map-pin"></i> <?= (int)$r['haltes'] ?> haltes</div>
                <?php endif; ?>
            </div>
            <div class="kaart-footer">
                <div class="kaart-prijs">
                    <div class="kaart-prijs-label">Vanaf</div>
                    <div class="kaart-prijs-val">€ <?= number_format((float)$r['prijs_pp'], 0, ',', '.') ?></div>
                    <div class="kaart-prijs-sub">per persoon<?= $r['type']==='meerdaags'?' all-in':'' ?></div>
                </div>
                <?php if (!$isVol): ?>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                    <div class="plaatsen <?= $isWeinig?'weinig':'genoeg' ?>">
                        <?php if ($isWeinig): ?>
                        <i class="fa-solid fa-fire"></i> Nog <?= $pctVrij ?>% vrij
                        <?php else: ?>
                        <i class="fa-solid fa-circle-check"></i> Nog <?= $pctVrij ?>% vrij
                        <?php endif; ?>
                    </div>
                    <div class="kaart-cta">Nu reserveren <i class="fa-solid fa-arrow-right"></i></div>
                </div>
                <?php else: ?>
                <div class="kaart-cta" style="background:#94a3b8; cursor:default;">Volgeboekt</div>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; endif; ?>
    </div>
</div>

<!-- TRUST BAR -->
<div class="trust">
    <div class="trust-inner">
        <div class="trust-item">
            <div class="trust-icon" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="trust-label">SGR<br>Beschermd</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <div class="trust-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="fa-solid fa-certificate"></i></div>
            <div class="trust-label">ANVR<br>Gecertificeerd</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <div class="trust-icon" style="background:#dcfce7; color:#16a34a;"><i class="fa-solid fa-lock"></i></div>
            <div class="trust-label">Veilig<br>betalen</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <div class="trust-icon" style="background:#f0fdf4; color:#15803d;"><i class="fa-solid fa-door-open"></i></div>
            <div class="trust-label">Deur-tot-<br>deur</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <div class="trust-icon" style="background:#ede9fe; color:#7c3aed;"><i class="fa-solid fa-headset"></i></div>
            <div class="trust-label">Persoonlijk<br>advies</div>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Coach Travel &times; Berkhout Reizen &mdash;
    <a href="tel:0854862007">085 - 486 20 07</a> &mdash;
    <a href="mailto:info@coachtravel.nl">info@coachtravel.nl</a></p>
</footer>

</body>
</html>
