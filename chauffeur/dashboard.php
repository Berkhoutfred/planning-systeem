<?php
// Bestand: chauffeur/dashboard.php
// VERSIE: Chauffeurs App 3.7 - (Inzien-knop hersteld + Telegram Koppel Banner)

session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['chauffeur_id'])) {
    header("Location: index.php");
    exit;
}

require '../beheer/includes/db.php';

$chauffeur_id = (int) $_SESSION['chauffeur_id'];
$chauffeur_naam = (string) $_SESSION['chauffeur_naam'];

if (!isset($_SESSION['chauffeur_tenant_id'])) {
    $stmt_bt = $pdo->prepare('SELECT tenant_id FROM chauffeurs WHERE id = ? AND archief = 0 LIMIT 1');
    $stmt_bt->execute([$chauffeur_id]);
    $row_bt = $stmt_bt->fetch(PDO::FETCH_ASSOC);
    if (!$row_bt || (int) $row_bt['tenant_id'] < 1) {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    $_SESSION['chauffeur_tenant_id'] = (int) $row_bt['tenant_id'];
}

$tenantId = (int) $_SESSION['chauffeur_tenant_id'];

$stmt_chk = $pdo->prepare('SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? AND archief = 0 LIMIT 1');
$stmt_chk->execute([$chauffeur_id, $tenantId]);
if (!$stmt_chk->fetch()) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ==========================================
// 1. CHECK ACTIEVE DIENST & PAUZES
// ==========================================
$actieve_dienst = null;
$actieve_pauze = null;

$stmt_dienst = $pdo->prepare("SELECT * FROM diensten WHERE chauffeur_id = ? AND tenant_id = ? AND status = 'actief' ORDER BY id DESC LIMIT 1");
$stmt_dienst->execute([$chauffeur_id, $tenantId]);
$actieve_dienst = $stmt_dienst->fetch();

if ($actieve_dienst) {
    $stmt_pauze = $pdo->prepare("SELECT dp.* FROM dienst_pauzes dp INNER JOIN diensten d ON d.id = dp.dienst_id AND d.tenant_id = ? AND d.chauffeur_id = ? WHERE dp.dienst_id = ? AND dp.eind_pauze IS NULL LIMIT 1");
    $stmt_pauze->execute([$tenantId, $chauffeur_id, $actieve_dienst['id']]);
    $actieve_pauze = $stmt_pauze->fetch();
}

// ==========================================
// 2. CHECK TELEGRAM KOPPELING
// ==========================================
$stmt_chauf = $pdo->prepare('SELECT telegram_chat_id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1');
$stmt_chauf->execute([$chauffeur_id, $tenantId]);
$chauffeur_data = $stmt_chauf->fetch();
$telegram_gekoppeld = !empty($chauffeur_data['telegram_chat_id']);

// ==========================================
// 3. ACTIES VERWERKEN 
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie'])) {
    $actie = $_POST['actie'];
    
    if ($actie == 'start_dienst' && !$actieve_dienst) {
        $stmt = $pdo->prepare("INSERT INTO diensten (tenant_id, chauffeur_id, start_tijd, status) VALUES (?, ?, NOW(), 'actief')");
        $stmt->execute([$tenantId, $chauffeur_id]);
        header("Location: dashboard.php");
        exit;
    } 
    elseif ($actie == 'start_pauze' && $actieve_dienst && !$actieve_pauze) {
        $stmt = $pdo->prepare("INSERT INTO dienst_pauzes (dienst_id, start_pauze) VALUES (?, NOW())");
        $stmt->execute([$actieve_dienst['id']]);
        header("Location: dashboard.php");
        exit;
    }
    elseif ($actie == 'eind_pauze' && $actieve_pauze) {
        $stmt = $pdo->prepare("
            UPDATE dienst_pauzes dp
            INNER JOIN diensten d ON d.id = dp.dienst_id AND d.chauffeur_id = ? AND d.tenant_id = ?
            SET dp.eind_pauze = NOW()
            WHERE dp.id = ? AND dp.eind_pauze IS NULL
        ");
        $stmt->execute([$chauffeur_id, $tenantId, $actieve_pauze['id']]);
        header("Location: dashboard.php");
        exit;
    }
}

// ==========================================
// 4. RITTEN OPHALEN & FILTEREN
// ==========================================
// Standaard 'alles': ritten uit het planbord hebben vaak een ritdatum in de toekomst;
// met alleen 'vandaag' leek de lijst leeg terwijl Telegram wél ging (zelfde toewijzing).
$weergave = isset($_GET['weergave']) ? $_GET['weergave'] : 'alles';
$sql_extra = "";
$titel_weergave = "📌 Nog te rijden";

if ($weergave === 'dienst' && $actieve_dienst) {
    // Toon ALLEEN de afgeronde ritten die in de huidige envelop (dienst) zitten
    $sql_extra = " AND r.dienst_id = " . (int)$actieve_dienst['id'];
    $titel_weergave = "✅ Afgerond in huidige dienst";
} else {
    // Niet afgerond (DB-enum is 'voltooid', hoofdletterongevoelig)
    $sql_extra = " AND LOWER(TRIM(COALESCE(r.status, ''))) != 'voltooid' ";
    // Geen filter op dienst_id: ook ritten gekoppeld aan een dienst-/mapje-selectie op het planbord
    // moeten hier zichtbaar zijn zolang ze nog niet voltooid zijn.

    if ($weergave === 'vandaag') {
        $sql_extra .= " AND DATE(r.datum_start) = CURDATE() ";
        $titel_weergave = "📍 Volgende rit(ten) - Vandaag";
    } elseif ($weergave === 'morgen') {
        $sql_extra .= " AND DATE(r.datum_start) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) ";
        $titel_weergave = "📍 Volgende rit(ten) - Morgen";
    } else {
        $sql_extra .= " AND DATE(r.datum_start) >= CURDATE() ";
        $titel_weergave = "📍 Aankomende ritten (vanaf vandaag)";
    }
}

try {
    $stmt_ritten = $pdo->prepare("
        SELECT 
            r.id AS rit_id, r.datum_start, r.status, r.calculatie_id, r.dienst_id,
            k.bedrijfsnaam, k.voornaam, k.achternaam,
            v.voertuig_nummer, v.naam as bus_naam,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = r.tenant_id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as vaste_rit_naam,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as vaste_rit_bestemming
        FROM ritten r 
        LEFT JOIN calculaties c ON r.calculatie_id = c.id AND c.tenant_id = r.tenant_id
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = r.tenant_id
        LEFT JOIN voertuigen v ON r.voertuig_id = v.id AND v.tenant_id = r.tenant_id
        WHERE r.chauffeur_id = ?
          AND r.tenant_id = ?
        " . $sql_extra . "
        ORDER BY (DATE(r.datum_start) = CURDATE()) DESC, r.datum_start ASC
    ");
    $stmt_ritten->execute([$chauffeur_id, $tenantId]);
    $ritten_lijst = $stmt_ritten->fetchAll();
} catch (PDOException $e) {
    die("Fout bij ophalen ritten: " . $e->getMessage());
}

$dagen_nl = ['Monday'=>'Maandag', 'Tuesday'=>'Dinsdag', 'Wednesday'=>'Woensdag', 'Thursday'=>'Donderdag', 'Friday'=>'Vrijdag', 'Saturday'=>'Zaterdag', 'Sunday'=>'Zondag'];
$maanden_nl = ['01'=>'januari', '02'=>'februari', '03'=>'maart', '04'=>'april', '05'=>'mei', '06'=>'juni', '07'=>'juli', '08'=>'augustus', '09'=>'september', '10'=>'oktober', '11'=>'november', '12'=>'december'];

function formatMooieDatum($dateStr, $dagen_nl, $maanden_nl) {
    $d = new DateTime($dateStr);
    $dag = $dagen_nl[$d->format('l')];
    $maand = $maanden_nl[$d->format('m')];
    $vandaag = new DateTime(); $vandaag->setTime(0,0,0);
    $check_datum = clone $d; $check_datum->setTime(0,0,0);
    
    if ($check_datum == $vandaag) {
        return "<span style='color:#e63946; font-weight:bold;'>VANDAAG</span> (" . $d->format('d') . " $maand)";
    }
    return "<strong>$dag</strong> " . $d->format('d') . " $maand";
}

$theme_color = "#003366"; 
if ($actieve_dienst && !$actieve_pauze) $theme_color = "#28a745"; 
if ($actieve_dienst && $actieve_pauze) $theme_color = "#fd7e14"; 
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chauffeurs App - Berkhout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo $theme_color; ?>;
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-main: #2c3e50;
            --text-muted: #7f8c8d;
        }

        body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg-color); margin: 0; padding: 0; color: var(--text-main); -webkit-font-smoothing: antialiased; }
        
        .app-header { background: var(--primary); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; transition: background 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .app-header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; transition: 0.2s; }

        .hero-section { background: var(--primary); color: white; padding: 30px 20px 40px 20px; text-align: center; border-radius: 0 0 25px 25px; margin-bottom: 20px; transition: background 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .hero-title { font-size: 24px; margin: 0 0 5px 0; font-weight: 800; }
        .hero-subtitle { font-size: 15px; opacity: 0.9; margin: 0 0 25px 0; }
        
        .btn-massive { display: block; width: 100%; border: none; padding: 18px; border-radius: 15px; font-size: 20px; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 6px 15px rgba(0,0,0,0.2); transition: transform 0.1s; text-decoration: none; box-sizing: border-box; }
        .btn-massive:active { transform: translateY(3px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .btn-start { background: #ffffff; color: #003366; }
        .btn-pauze { background: #ffffff; color: #fd7e14; margin-bottom: 15px; }
        .btn-hervat { background: #ffffff; color: #28a745; margin-bottom: 15px; }
        .btn-stop { background: #dc3545; color: #ffffff; border: 2px solid rgba(255,255,255,0.2); }

        .content { padding: 0 15px 30px 15px; }

        /* Telegram Banner Stijl */
        .telegram-banner { background: #0088cc; color: white; border-radius: 16px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(0,136,204,0.2); border: 2px solid #0077b3; }
        .telegram-info { flex: 1; }
        .telegram-title { font-size: 16px; font-weight: 800; margin: 0 0 4px 0; }
        .telegram-text { font-size: 13px; opacity: 0.9; margin: 0; line-height: 1.4; }
        .telegram-btn { background: white; color: #0088cc; text-decoration: none; padding: 10px 16px; border-radius: 10px; font-weight: 800; font-size: 14px; white-space: nowrap; margin-left: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: 0.2s; }
        .telegram-btn:active { transform: scale(0.95); }

        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .action-btn { background: var(--card-bg); border-radius: 15px; padding: 20px 10px; text-align: center; text-decoration: none; color: var(--text-main); font-weight: 700; box-shadow: 0 4px 10px rgba(0,0,0,0.04); display: flex; flex-direction: column; align-items: center; border: 1px solid #eee; }
        .action-icon { font-size: 28px; margin-bottom: 8px; color: var(--primary); }

        .filter-container { display: flex; gap: 4px; margin-bottom: 20px; background: #e9ecef; padding: 4px; border-radius: 10px; width: 100%; box-sizing: border-box; }
        .filter-btn { flex: 1; text-align: center; padding: 10px 2px; border-radius: 8px; color: var(--text-muted); text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .filter-btn.active { background: #ffffff; color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-btn.active-dienst { background: #28a745; color: white; box-shadow: 0 2px 8px rgba(40,167,69,0.3); }

        .section-title { font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 5px; }
        
        .rit-card { background: var(--card-bg); border-radius: 16px; padding: 18px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; display: block; text-decoration: none; color: inherit; position: relative; overflow: hidden; }
        .rit-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: #007bff; }
        .rit-card.toekomst::before { background: #17a2b8; }
        .rit-card.voltooid::before { background: #28a745; }
        
        .rit-card.voltooid { background: #f8f9fa; border-color: #d4edda; opacity: 0.9; }
        .rit-card.voltooid .rit-titel { color: #555; }
        
        .rit-datum { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; font-weight: 600; }
        .rit-titel { font-size: 18px; font-weight: 800; margin: 0 0 8px 0; color: var(--text-main); }
        .rit-info { font-size: 14px; margin: 4px 0; color: #555; display: flex; align-items: center; }
        .rit-info i { width: 20px; color: var(--primary); opacity: 0.7; }
        
        .open-btn { background: #f4f7f6; text-align: center; padding: 12px; border-radius: 10px; margin-top: 15px; font-weight: 700; color: var(--primary); display: flex; justify-content: center; align-items: center; gap: 8px; }
        .open-btn.locked { background: #fef0f0; color: #dc3545; }
        .open-btn.voltooid { background: #e8f5e9; color: #28a745; border: 1px solid #c3e6cb; }

        .geen-ritten { text-align: center; padding: 40px 20px; background: transparent; color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>

    <div class="app-header">
        <h1>🚌 Berkhout Reizen</h1>
        <a href="?logout=1" class="logout-btn">Uitloggen</a>
    </div>

    <div class="hero-section">
        <?php if (!$actieve_dienst): ?>
            <h2 class="hero-title">Goedemorgen, <?php echo htmlspecialchars($chauffeur_naam); ?>!</h2>
            <p class="hero-subtitle">Je bent momenteel niet ingeklokt.</p>
            <form method="POST">
                <input type="hidden" name="actie" value="start_dienst">
                <button type="submit" class="btn-massive btn-start"><i class="fas fa-play-circle"></i> Start Dienst</button>
            </form>
        <?php else: ?>
            <?php if ($actieve_pauze): ?>
                <h2 class="hero-title">☕ Pauze Actief</h2>
                <p class="hero-subtitle">Geniet van je rustmoment.</p>
                <form method="POST" style="margin-bottom: 10px;">
                    <input type="hidden" name="actie" value="eind_pauze">
                    <button type="submit" class="btn-massive btn-hervat"><i class="fas fa-play"></i> Hervat Dienst</button>
                </form>
            <?php else: ?>
                <h2 class="hero-title">🟢 Dienst is Actief</h2>
                <p class="hero-subtitle">Ingeklokt sinds <?php echo date('H:i', strtotime($actieve_dienst['start_tijd'])); ?>.</p>
                <form method="POST" style="margin-bottom: 10px;">
                    <input type="hidden" name="actie" value="start_pauze">
                    <button type="submit" class="btn-massive btn-pauze"><i class="fas fa-coffee"></i> Start Pauze</button>
                </form>
            <?php endif; ?>
            <a href="dienst_afronden.php" class="btn-massive btn-stop" style="display: block; text-align: center;"><i class="fas fa-stop-circle"></i> Einde Dienst</a>
        <?php endif; ?>
    </div>

    <div class="content">
        
        <?php if(!$telegram_gekoppeld): ?>
            <div class="telegram-banner">
                <div class="telegram-info">
                    <h3 class="telegram-title"><i class="fab fa-telegram-plane"></i> Rit-meldingen</h3>
                    <p class="telegram-text">Krijg direct een berichtje bij wijzigingen of een nieuwe rit.</p>
                </div>
                <a href="https://t.me/BerkhoutRittenBot?start=koppel_<?php echo (int) $chauffeur_id; ?>_<?php echo (int) $tenantId; ?>" class="telegram-btn" target="_blank">Koppelen</a>
            </div>
        <?php endif; ?>
        
        <div class="action-grid">
            <a href="nieuwe_rit.php" class="action-btn">
                <i class="fas fa-taxi action-icon"></i>
                <span>Extra Rit</span>
            </a>
            <a href="https://www.berkhoutreizen.nl/scan_module_br26.php" class="action-btn">
                <i class="fas fa-qrcode action-icon"></i>
                <span>Ticket Scanner</span>
            </a>
        </div>

        <div class="filter-container">
            <a href="?weergave=vandaag" class="filter-btn <?php echo ($weergave == 'vandaag') ? 'active' : ''; ?>">Vandaag</a>
            <a href="?weergave=morgen" class="filter-btn <?php echo ($weergave == 'morgen') ? 'active' : ''; ?>">Morgen</a>
            <a href="?weergave=alles" class="filter-btn <?php echo ($weergave == 'alles') ? 'active' : ''; ?>">Alles</a>
            
            <?php if($actieve_dienst): ?>
                <a href="?weergave=dienst" class="filter-btn <?php echo ($weergave == 'dienst') ? 'active-dienst' : ''; ?>">
                    <i class="fas fa-envelope"></i> Dienst
                </a>
            <?php endif; ?>
        </div>

        <div class="section-title">
            <?php echo $titel_weergave; ?>
        </div>
        
        <?php if(count($ritten_lijst) == 0): ?>
            <div class="geen-ritten">
                <?php if($weergave == 'dienst'): ?>
                    <i class="fas fa-envelope-open-text" style="font-size: 30px; margin-bottom: 10px; color: #888;"></i><br>
                    Je hebt nog geen ritten in deze envelop gestopt.
                <?php else: ?>
                    <i class="fas fa-check-circle" style="font-size: 30px; margin-bottom: 10px; color: #28a745;"></i><br>
                    Je planning is helemaal leeg!
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <?php foreach($ritten_lijst as $rit): 
                if (!empty($rit['calculatie_id'])) {
                    $klant = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                    $bestemming = $rit['bestemming_adres'];
                } else {
                    $klant = "[VASTE RIT] " . $rit['vaste_rit_naam'];
                    $bestemming = $rit['vaste_rit_bestemming'];
                }

                $bestemming = $bestemming ?? 'Adres onbekend';
                $bus = $rit['bus_naam'] ? $rit['voertuig_nummer'].' - '.$rit['bus_naam'] : 'Bus nog onbekend';
                $tijd = date('H:i', strtotime($rit['datum_start']));
                
                $is_vandaag = (date('Y-m-d', strtotime($rit['datum_start'])) == date('Y-m-d'));
                
                // Alleen echte status 'voltooid' (niet dienst_id: die kan al gezet zijn bij plannen via mapje)
                $is_voltooid = (strtolower(trim((string)($rit['status'] ?? ''))) === 'voltooid');
                
                $card_class = $is_vandaag ? 'rit-card' : 'rit-card toekomst';
                if ($is_voltooid) $card_class .= ' voltooid';
            ?>
            
            <a href="rit_bekijken.php?id=<?php echo $rit['rit_id']; ?>" class="<?php echo $card_class; ?>">
                <div class="rit-datum">
                    <?php echo formatMooieDatum($rit['datum_start'], $dagen_nl, $maanden_nl); ?>
                </div>
                <div class="rit-titel"><?php echo htmlspecialchars($klant); ?></div>
                <div class="rit-info"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($bestemming); ?></div>
                <div class="rit-info"><i class="far fa-clock"></i> Vertrek: <strong><?php echo $tijd; ?></strong></div>
                <div class="rit-info"><i class="fas fa-bus"></i> <?php echo htmlspecialchars($bus); ?></div>
                
                <?php if($is_voltooid): ?>
                    <div class="open-btn voltooid"><i class="fas fa-search"></i> Inzien / Wijzigen</div>
                <?php elseif(!$actieve_dienst): ?>
                    <div class="open-btn locked"><i class="fas fa-lock"></i> Klok in om te rijden</div>
                <?php else: ?>
                    <div class="open-btn">Bekijk & Afronden <i class="fas fa-arrow-right"></i></div>
                <?php endif; ?>
            </a>
            
            <?php endforeach; ?>
            
        <?php endif; ?>
        
    </div>

</body>
</html>