<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
require_once __DIR__ . '/../reizen/_scope.php';

$filterType  = $_GET['type']  ?? '';
$filterMaand = $_GET['maand'] ?? '';
$filterMax   = (int)($_GET['max'] ?? 0);

[$tenantSql, $tenantParams] = busreis_scope_sql($pdo, 'b', 'preview');

$where  = ["status = 'gepubliceerd'", "datum_van >= CURDATE()", $tenantSql];
$params = $tenantParams;
if (in_array($filterType, ['dagtocht','meerdaags'], true)) { $where[] = 'type = ?'; $params[] = $filterType; }
if ($filterMaand && preg_match('/^\d{4}-\d{2}$/', $filterMaand)) { $where[] = "DATE_FORMAT(datum_van,'%Y-%m') = ?"; $params[] = $filterMaand; }
if ($filterMax > 0) { $where[] = 'prijs_pp <= ?'; $params[] = $filterMax; }

$stmt = $pdo->prepare("SELECT b.*,
    (SELECT COUNT(*) FROM busreis_boekingen bk WHERE bk.busreis_id=b.id AND bk.status!='geannuleerd') AS boekingen
    FROM busreizen b WHERE " . implode(' AND ', $where) . " ORDER BY datum_van ASC");
$stmt->execute($params);
$reizen = $stmt->fetchAll();

$maandenStmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(datum_van,'%Y-%m') AS ym, DATE_FORMAT(datum_van,'%M %Y') AS label FROM busreizen WHERE tenant_id = ? AND status='gepubliceerd' AND datum_van>=CURDATE() ORDER BY datum_van");
$maandenStmt->execute([busreis_scope_tenant_id($pdo, 'preview')]);
$maanden = $maandenStmt->fetchAll();
$nlM = ['January'=>'Januari','February'=>'Februari','March'=>'Maart','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Augustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'December'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Preview — Busreizen & Dagtochten | Berkhout Reizen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#002855;--blue:#004aad;--gold:#f59e0b;--green:#16a34a}
html{scroll-behavior:smooth}
body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2533}

/* Preview banner */
.preview-banner{background:linear-gradient(90deg,#7c3aed,#4f46e5);color:#fff;padding:10px 20px;text-align:center;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:10px;position:sticky;top:0;z-index:999}
.preview-banner i{font-size:14px}
.preview-banner a{color:rgba(255,255,255,.8);font-size:12px;margin-left:16px;text-decoration:underline}

/* Nav */
.nav{background:var(--navy);height:58px;display:flex;align-items:center;padding:0 clamp(16px,4vw,50px);justify-content:space-between;box-shadow:0 2px 14px rgba(0,0,0,.25)}
.nav-logo{font-size:17px;font-weight:900;color:#fff;text-decoration:none;display:flex;align-items:center;gap:9px}
.nav-logo span{color:#5bc8f5}
.nav-tel{color:rgba(255,255,255,.75);font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-tel:hover{color:#fff}

/* Hero */
.hero{background:#001428;padding:clamp(64px,10vw,100px) clamp(16px,4vw,60px);text-align:center;position:relative;overflow:hidden;min-height:420px;display:flex;align-items:center;justify-content:center}
.hero-bg{position:absolute;inset:0;background-image:url(https://tourplan.nl/uploads/reizen/hero.webp);background-size:cover;background-position:center 55%;filter:brightness(.72) saturate(1.1);transform:scale(1.03)}
.hero::before{content:'';position:absolute;inset:0;z-index:1;background:linear-gradient(to bottom,rgba(0,15,45,.05) 0%,rgba(0,25,65,.28) 50%,rgba(0,8,25,.72) 100%)}
.hero-inner{position:relative;z-index:2;max-width:700px;margin:0 auto}
.hero-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#fbbf24;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;letter-spacing:.5px;margin-bottom:20px}
.hero-logo{height:120px;width:auto;object-fit:contain;display:block;margin:0 auto 24px;filter:drop-shadow(0 6px 24px rgba(0,0,0,.6))}
.hero h1{font-size:clamp(28px,5vw,46px);font-weight:900;color:#fff;line-height:1.15;margin-bottom:16px}
.hero h1 em{color:#5bc8f5;font-style:normal}
.hero p{font-size:clamp(14px,2vw,17px);color:rgba(255,255,255,.7);line-height:1.6;max-width:520px;margin:0 auto 32px}
.hero-usps{display:flex;justify-content:center;gap:24px;flex-wrap:wrap}
.hero-usp{display:flex;align-items:center;gap:7px;color:rgba(255,255,255,.65);font-size:13px}
.hero-usp i{color:#5bc8f5}

/* Filter */
.filter-wrap{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.filter-inner{max-width:1200px;margin:0 auto;padding:0 clamp(16px,4vw,40px);display:flex;align-items:center;gap:0}
.type-tabs{display:flex;height:52px}
.type-tab{display:flex;align-items:center;gap:7px;padding:0 20px;font-size:13.5px;font-weight:600;color:#64748b;border-bottom:3px solid transparent;cursor:pointer;text-decoration:none;white-space:nowrap;transition:.15s}
.type-tab:hover{color:var(--navy)}
.type-tab.actief{color:var(--navy);border-bottom-color:var(--blue)}
.filter-sep{width:1px;height:28px;background:#e2e8f0;margin:0 12px;flex-shrink:0}
.filter-selects{display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap;padding:8px 0}
.filter-selects select{padding:7px 12px;border:1px solid #e2e8f0;border-radius:7px;font-size:13px;color:#374151;background:#f8faff;cursor:pointer;font-family:inherit}
.filter-count{margin-left:auto;font-size:12.5px;color:#94a3b8;white-space:nowrap}

/* Grid */
.grid-wrap{max-width:1200px;margin:0 auto;padding:clamp(24px,4vw,40px) clamp(16px,4vw,40px)}
.grid-titel{font-size:15px;font-weight:700;color:#002855;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}

/* Kaart */
.kaart{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07);transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column;text-decoration:none;color:inherit}
.kaart:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.12)}
.kaart-foto-wrap{position:relative;padding-top:56%;overflow:hidden;flex-shrink:0}
.kaart-foto{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .4s}
.kaart:hover .kaart-foto{transform:scale(1.04)}
.kaart-foto-placeholder{position:absolute;inset:0;background:linear-gradient(135deg,#e8eef7,#d1dce8);display:flex;align-items:center;justify-content:center}
.kaart-foto-placeholder i{font-size:40px;color:#b8c8d8}
.kaart-badges{position:absolute;top:12px;left:12px;display:flex;gap:6px;flex-wrap:wrap}
.kaart-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;backdrop-filter:blur(4px)}
.badge-vert{background:rgba(245,158,11,.9);color:#fff}
.badge-type-dag{background:rgba(59,130,246,.85);color:#fff}
.badge-type-meer{background:rgba(109,40,217,.85);color:#fff}
.kaart-body{padding:18px 20px;flex:1;display:flex;flex-direction:column}
.kaart-cat{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin-bottom:7px}
.kaart-titel{font-size:17px;font-weight:800;color:var(--navy);line-height:1.25;margin-bottom:8px}
.kaart-dest{font-size:13px;color:#64748b;display:flex;align-items:center;gap:5px;margin-bottom:12px}
.kaart-info{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f1f5f9}
.kaart-info-item{font-size:12.5px;color:#475569;display:flex;align-items:center;gap:5px}
.kaart-info-item i{color:#94a3b8;font-size:12px}
.kaart-footer{display:flex;align-items:center;justify-content:space-between;margin-top:auto}
.kaart-prijs-label{font-size:10.5px;color:#94a3b8;font-weight:500}
.kaart-prijs-val{font-size:22px;font-weight:900;color:var(--navy);line-height:1}
.kaart-prijs-sub{font-size:11px;color:#94a3b8}
.kaart-cta{background:var(--navy);color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px;transition:background .15s}
.kaart:hover .kaart-cta{background:var(--blue)}

/* Trust */
.trust{background:#fff;border-top:1px solid #e8eef7;margin-top:40px;padding:28px clamp(16px,4vw,60px)}
.trust-inner{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:center;gap:clamp(20px,4vw,52px);flex-wrap:wrap}
.trust-item{display:flex;flex-direction:column;align-items:center;gap:6px}
.trust-logo{height:40px;width:auto;object-fit:contain;filter:grayscale(20%);opacity:.85;transition:opacity .2s}
.trust-logo:hover{opacity:1;filter:none}
.trust-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
.trust-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;text-align:center}
.trust-sep{width:1px;height:36px;background:#e2e8f0}

footer{background:var(--navy);color:rgba(255,255,255,.5);text-align:center;padding:24px;font-size:12px}
footer a{color:rgba(255,255,255,.6)}

@media(max-width:640px){.filter-inner{flex-wrap:wrap}.filter-sep{display:none}.type-tabs{width:100%}}
</style>
</head>
<body>

<!-- Preview banner -->
<div class="preview-banner">
    <i class="fa-solid fa-eye"></i>
    Dit is een <strong>privé preview</strong> — niet zichtbaar voor het publiek
    <a href="https://www.berkhoutreizen.nl/">Terug naar de website</a>
</div>

<nav class="nav">
    <a href="<?= preview_url('index.php') ?>" class="nav-logo">
        <i class="fa-solid fa-route"></i> Berkhout Reizen <span>× Coach Travel</span>
    </a>
    <a href="tel:0854862007" class="nav-tel"><i class="fa-solid fa-phone"></i> 085 - 486 20 07</a>
</nav>

<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-inner">
        <img src="coachtravel-logo.webp" alt="Coach Travel" class="hero-logo">
        <h1>Reizen met <em>een verhaal</em></h1>
        <p>Comfortabel op reis per luxe touringcar — van uw voordeur tot de bestemming, volledig verzorgd en zonder één zorg.</p>
        <div class="hero-usps">
            <div class="hero-usp"><i class="fa-solid fa-door-open"></i> Deur-tot-deur</div>
            <div class="hero-usp"><i class="fa-solid fa-user-tie"></i> Vaste reisleider</div>
            <div class="hero-usp"><i class="fa-solid fa-users"></i> Klein gezelschap</div>
            <div class="hero-usp"><i class="fa-solid fa-star"></i> Volledig verzorgd</div>
        </div>
    </div>
</section>

<div class="filter-wrap">
    <div class="filter-inner">
        <div class="type-tabs">
            <a href="<?= preview_url('index.php', ['type'=>'']) ?>" class="type-tab <?= $filterType==='' ? 'actief' : '' ?>"><i class="fa-solid fa-list"></i> Alle reizen</a>
            <a href="<?= preview_url('index.php', ['type'=>'dagtocht']) ?>" class="type-tab <?= $filterType==='dagtocht' ? 'actief' : '' ?>"><i class="fa-solid fa-sun"></i> Dagtochten</a>
            <a href="<?= preview_url('index.php', ['type'=>'meerdaags']) ?>" class="type-tab <?= $filterType==='meerdaags' ? 'actief' : '' ?>"><i class="fa-solid fa-moon"></i> Meerdaagse reizen</a>
        </div>
        <div class="filter-sep"></div>
        <form class="filter-selects" method="GET">
            <input type="hidden" name="token" value="<?= htmlspecialchars(PREVIEW_TOKEN, ENT_QUOTES) ?>">
            <?php if ($filterType): ?><input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>"><?php endif; ?>
            <select name="maand" onchange="this.form.submit()">
                <option value="">Alle maanden</option>
                <?php foreach ($maanden as $m): $lbl = strtr($m['label'], $nlM); ?>
                <option value="<?= $m['ym'] ?>" <?= $filterMaand===$m['ym']?'selected':'' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="max" onchange="this.form.submit()">
                <option value="0">Alle prijzen</option>
                <option value="100"  <?= $filterMax===100 ?'selected':''?>>Tot € 100</option>
                <option value="250"  <?= $filterMax===250 ?'selected':''?>>Tot € 250</option>
                <option value="500"  <?= $filterMax===500 ?'selected':''?>>Tot € 500</option>
                <option value="1000" <?= $filterMax===1000?'selected':''?>>Tot € 1.000</option>
                <option value="1500" <?= $filterMax===1500?'selected':''?>>Tot € 1.500</option>
            </select>
        </form>
        <div class="filter-count"><?= count($reizen) ?> <?= count($reizen)===1?'reis':'reizen' ?></div>
    </div>
</div>

<div class="grid-wrap">
    <div class="grid-titel"><i class="fa-solid fa-route" style="color:#004aad"></i>
        <?php if ($filterType==='dagtocht') echo 'Dagtochten';
        elseif ($filterType==='meerdaags') echo 'Meerdaagse reizen';
        else echo 'Alle busreizen &amp; dagtochten'; ?>
    </div>
    <div class="grid">
    <?php if (empty($reizen)): ?>
        <div style="grid-column:1/-1;padding:80px 20px;text-align:center;color:#94a3b8">
            <i class="fa-solid fa-compass" style="font-size:52px;display:block;margin-bottom:16px;color:#d1dce8"></i>
            <h3 style="font-size:18px;margin-bottom:8px">Geen reizen gevonden</h3>
            <p style="font-size:14px">Probeer een andere filter.</p>
        </div>
    <?php else: foreach ($reizen as $r):
        $vrij  = max(0, $r['max_deelnemers'] - $r['boekingen']);
        $nDagen = $r['datum_tot'] ? (int)((strtotime($r['datum_tot']) - strtotime($r['datum_van'])) / 86400) + 1 : 1;
        $datumStr = date('d M', strtotime($r['datum_van']));
        if ($r['type']==='meerdaags' && $r['datum_tot']) $datumStr .= ' – '.date('d M Y', strtotime($r['datum_tot']));
        else $datumStr .= ' '.date('Y', strtotime($r['datum_van']));
        $fp = ltrim($r['foto_pad'] ?? '', '/');
        $cardPad = $fp ? preg_replace('/(\.[a-z]+)$/i', '_card$1', $fp) : '';
    ?>
    <a href="<?= preview_url('detail.php', ['slug' => $r['slug']]) ?>" class="kaart">
        <div class="kaart-foto-wrap">
            <?php if ($fp): ?>
            <picture>
                <source srcset="https://tourplan.nl/<?= htmlspecialchars($cardPad, ENT_QUOTES) ?>" type="image/webp">
                <img class="kaart-foto" src="https://tourplan.nl/<?= htmlspecialchars($fp, ENT_QUOTES) ?>"
                     alt="<?= htmlspecialchars($r['titel'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
            </picture>
            <?php else: ?>
            <div class="kaart-foto-placeholder"><i class="fa-solid fa-<?= $r['type']==='meerdaags'?'moon':'sun' ?>"></i></div>
            <?php endif; ?>
            <div class="kaart-badges">
                <?php if ($r['type']==='dagtocht'): ?><span class="kaart-badge badge-type-dag"><i class="fa-solid fa-sun"></i> Dagtocht</span>
                <?php else: ?><span class="kaart-badge badge-type-meer"><i class="fa-solid fa-moon"></i> <?= $nDagen ?> dagen</span><?php endif; ?>
                <?php if ($r['vertrekgarantie']): ?><span class="kaart-badge badge-vert"><i class="fa-solid fa-shield-check"></i> Vertrekgarantie</span><?php endif; ?>
            </div>
        </div>
        <div class="kaart-body">
            <?php if ($r['categorie']): ?><div class="kaart-cat"><?= htmlspecialchars($r['categorie'], ENT_QUOTES) ?></div><?php endif; ?>
            <div class="kaart-titel"><?= htmlspecialchars($r['titel'], ENT_QUOTES) ?></div>
            <?php if ($r['bestemming']): ?><div class="kaart-dest"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($r['bestemming'], ENT_QUOTES) ?></div><?php endif; ?>
            <div class="kaart-info">
                <div class="kaart-info-item"><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($datumStr, ENT_QUOTES) ?></div>
                <?php if ($r['vertrek_tijd']): ?><div class="kaart-info-item"><i class="fa-solid fa-clock"></i> <?= substr($r['vertrek_tijd'],0,5) ?></div><?php endif; ?>
            </div>
            <div class="kaart-footer">
                <div class="kaart-prijs">
                    <div class="kaart-prijs-label">Vanaf</div>
                    <div class="kaart-prijs-val">€ <?= number_format((float)$r['prijs_pp'],0,',','.') ?></div>
                    <div class="kaart-prijs-sub">per persoon<?= $r['type']==='meerdaags'?' all-in':'' ?></div>
                </div>
                <div class="kaart-cta">Bekijk reis <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </div>
    </a>
    <?php endforeach; endif; ?>
    </div>
</div>

<div class="trust">
    <div class="trust-inner">
        <div class="trust-item">
            <img src="sgr-logo-rgb.svg" alt="SGR Garantiefonds" class="trust-logo" style="height:36px">
            <div class="trust-label">Reizen onder<br>SGR-garantie</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <img src="sgrz-logo.svg" alt="SGRZ" class="trust-logo" style="height:36px">
            <div class="trust-label">SGRZ zakelijke<br>garantie</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <img src="anvr-logo.svg" alt="ANVR" class="trust-logo" style="height:32px">
            <div class="trust-label">ANVR-lid</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <img src="mollie-logo.png" alt="Mollie - veilig betalen" class="trust-logo" style="height:28px">
            <div class="trust-label">Veilig betalen</div>
        </div>
        <div class="trust-sep"></div>
        <div class="trust-item">
            <div class="trust-icon" style="background:#ede9fe;color:#7c3aed"><i class="fa-solid fa-headset"></i></div>
            <div class="trust-label">Persoonlijk<br>advies</div>
        </div>
    </div>
</div>

<footer><p>&copy; <?= date('Y') ?> Berkhout Reizen &times; Coach Travel &mdash; <a href="tel:0854862007">085 - 486 20 07</a></p></footer>
</body>
</html>
