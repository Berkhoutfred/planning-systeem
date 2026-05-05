<?php
// scan_module_br26.php - Versie 8.2: Smart Login & Oude Ritten Blokkade 📱

session_start();
header("X-Robots-Tag: noindex, nofollow"); // Onzichtbaar voor Google
require_once __DIR__ . '/env.php';

$wachtwoord = env_value('SCAN_MODULE_PASSWORD', ''); 
$is_chauffeur = isset($_SESSION['chauffeur_id']); // Kijkt of de chauffeur al in de dashboard app zit

// 1. DATABASE VERBINDING
$servername = env_value('LEGACY_DB_HOST', 'localhost');
$db_name = env_value('LEGACY_DB_NAME', ''); 
$db_user = env_value('LEGACY_DB_USER', ''); 
$db_pass = env_value('LEGACY_DB_PASS', ''); 

try {
    $conn = new PDO("mysql:host=$servername;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) { die("Database fout bij verbinden"); }

// 2. UITLOGGEN (Helemaal eruit en terug naar dashboard)
if (isset($_GET['logout'])) {
    unset($_SESSION['app_ingelogd']);
    unset($_SESSION['actief_event_id']);
    unset($_SESSION['actief_event_naam']);
    
    if ($is_chauffeur) {
        header("Location: chauffeur/dashboard.php"); 
    } else {
        header("Location: scan_module_br26.php");
    }
    exit;
}

// 3. ANDER RITNUMMER KIEZEN (Blijf in de scanner, maar reset de rit)
if (isset($_GET['ander_ritnummer'])) {
    unset($_SESSION['app_ingelogd']);
    unset($_SESSION['actief_event_id']);
    unset($_SESSION['actief_event_naam']);
    header("Location: scan_module_br26.php");
    exit;
}

$login_fout = "";

if (isset($_POST['login'])) {
    // Mag inloggen als hij al in het dashboard zit, OF als hij het wachtwoord typt
    $mag_inloggen = false;
    if ($is_chauffeur) {
        $mag_inloggen = true;
    } elseif (isset($_POST['pass']) && $_POST['pass'] === $wachtwoord) {
        $mag_inloggen = true;
    }

    if ($mag_inloggen && !empty($_POST['rit_nummer'])) {
        $rit_id = (int)$_POST['rit_nummer'];
        
        // Controleer of de rit bestaat én vandaag of in de toekomst is
        try {
            // LET OP: Ik ga er vanuit dat de kolom in party_events 'datum' heet.
            $stmt = $conn->prepare("SELECT naam FROM party_events WHERE id = ? AND datum >= CURDATE()");
            $stmt->execute([$rit_id]);
            $event_naam = $stmt->fetchColumn();

            if ($event_naam) {
                $_SESSION['app_ingelogd'] = true;
                $_SESSION['actief_event_id'] = $rit_id;
                $_SESSION['actief_event_naam'] = $event_naam;
            } else {
                $login_fout = "❌ Rit-nummer onbekend of rit is al geweest!";
            }
        } catch(PDOException $e) {
            // Vangnet: Als de kolomnaam geen 'datum' is, crasht het systeem niet maar geeft het deze melding:
            $login_fout = "❌ Systeemfout: Controleer of de kolom in de database 'datum' heet.";
        }
    } else {
        $login_fout = "❌ Wachtwoord of Rit-nummer onjuist!";
    }
}

if (!isset($_SESSION['app_ingelogd']) || !isset($_SESSION['actief_event_id'])) {
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no"></head><body style="font-family:-apple-system, system-ui; text-align:center; padding:50px 20px; background:#111; color:white; margin:0;">
    <div style="background:#222; padding:30px; border-radius:15px; max-width:400px; margin:0 auto; border: 1px solid #333;">
        <h2 style="margin-top:0;">🚌 Ticket Scanner</h2>';
        
        if ($is_chauffeur) {
            echo '<p style="color:#4ade80; margin-bottom:20px; font-weight:bold;">Welkom terug, ' . htmlspecialchars($_SESSION['chauffeur_naam'] ?? 'Chauffeur') . '! 👋<br><span style="color:#aaa; font-size:12px; font-weight:normal;">Wachtwoord is automatisch overgeslagen.</span></p>';
        }

        if ($login_fout) {
            echo '<div style="background:#7f1d1d; color:white; padding:10px; border-radius:8px; margin-bottom:15px; font-weight:bold;">'.$login_fout.'</div>';
        }

    echo '<form method="post" style="display: flex; flex-direction: column; gap: 15px;">';
            
        if (!$is_chauffeur) {
            echo '<input type="password" name="pass" placeholder="Wachtwoord" required style="padding:15px; font-size:16px; border-radius:8px; border:none; width: 100%; box-sizing: border-box;">';
        }
            
        echo '<input type="number" name="rit_nummer" placeholder="Rit-nummer (Bijv. 12)" required style="padding:15px; font-size:16px; border-radius:8px; border:none; width: 100%; box-sizing: border-box;">

        <button type="submit" name="login" style="padding:15px; background:#2563eb; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">Start Scanner ➡️</button>
    </form>';
    
    if ($is_chauffeur) {
        echo '<a href="chauffeur/dashboard.php" style="display:block; margin-top:20px; color:#aaa; text-decoration:none; font-size:14px;">⬅️ Terug naar Dashboard</a>';
    } else {
        echo '<p style="color:#666; font-size:12px; margin-top:20px;">Vraag de planning om het juiste Rit-nummer.</p>';
    }

    echo '</div></body></html>';
    exit;
}

$actief_event_id = $_SESSION['actief_event_id'];
$actief_event_naam = $_SESSION['actief_event_naam'];

// ---------------------------------------------------------
// API: SCANNEN
// ---------------------------------------------------------
if (isset($_POST['scan_code'])) {
    $code = $_POST['scan_code'];
    $stmt = $conn->prepare("SELECT * FROM tickets WHERE unieke_code = ?");
    $stmt->execute([$code]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        echo json_encode(['status' => 'error', 'title' => '❌ ONGELDIG', 'msg' => 'Code onbekend in het systeem']);
    } else {
        if ($ticket['event_id'] != $actief_event_id) {
            echo json_encode(['status' => 'error', 'title' => '⚠️ VERKEERDE RIT', 'msg' => 'Dit ticket is voor een ander evenement!']);
            exit;
        }

        $stmt_order = $conn->prepare("SELECT status, klant_naam FROM orders WHERE id = ?");
        $stmt_order->execute([$ticket['order_id']]);
        $order = $stmt_order->fetch();

        if ($order['status'] !== 'betaald') {
            echo json_encode(['status' => 'error', 'title' => '⚠️ NIET BETAALD', 'msg' => 'Order staat nog open']);
        } elseif ($ticket['is_gescand']) {
            $tijd = $ticket['gescand_op'] ? date('H:i', strtotime($ticket['gescand_op'])) : 'Eerder';
            echo json_encode(['status' => 'warning', 'title' => '⛔ REEDS GESCAND', 'msg' => 'Om ' . $tijd . ' uur al gebruikt!<br>' . htmlspecialchars($order['klant_naam'])]);
        } else {
            $stmt_update = $conn->prepare("UPDATE tickets SET is_gescand = 1, gescand_op = NOW() WHERE id = ?");
            $stmt_update->execute([$ticket['id']]);
            echo json_encode(['status' => 'success', 'title' => '✅ GELDIG', 'msg' => '<strong>' . htmlspecialchars($order['klant_naam']) . '</strong><br><span style="font-size:0.8em">📍 ' . htmlspecialchars($ticket['bestemming']) . '</span>']);
        }
    }
    exit; 
}

// ---------------------------------------------------------
// API: RESETTEN
// ---------------------------------------------------------
if (isset($_POST['reset_id'])) {
    $stmt = $conn->prepare("UPDATE tickets SET is_gescand = 0, gescand_op = NULL WHERE id = ? AND event_id = ?");
    $stmt->execute([$_POST['reset_id'], $actief_event_id]);
    echo "ok";
    exit;
}

// ---------------------------------------------------------
// API: LIJST
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'get_list') {
    $stmt_tot = $conn->prepare("SELECT COUNT(*) FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.status = 'betaald' AND t.event_id = ?");
    $stmt_tot->execute([$actief_event_id]);
    $totaal = $stmt_tot->fetchColumn();

    $stmt_bin = $conn->prepare("SELECT COUNT(*) FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.status = 'betaald' AND t.is_gescand = 1 AND t.event_id = ?");
    $stmt_bin->execute([$actief_event_id]);
    $binnen = $stmt_bin->fetchColumn();

    $nog_te_gaan = $totaal - $binnen;

    $stmt_lijst = $conn->prepare("SELECT t.*, o.klant_naam FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.status = 'betaald' AND t.event_id = ? ORDER BY t.is_gescand DESC, t.gescand_op DESC");
    $stmt_lijst->execute([$actief_event_id]);
    $lijst = $stmt_lijst->fetchAll(PDO::FETCH_ASSOC);

    $html = "";
    if (count($lijst) == 0) {
        $html = "<tr><td colspan='2' style='text-align:center; color:#888; padding:30px;'>Geen passagiers gevonden voor deze rit.</td></tr>";
    } else {
        foreach ($lijst as $rij) {
            $html .= "<tr><td><strong>" . htmlspecialchars($rij['klant_naam']) . "</strong><br><small style='color:#888;'>" . htmlspecialchars($rij['bestemming']) . "</small></td><td style='text-align:right;'>";
            if ($rij['is_gescand']) {
                $tijd = ($rij['gescand_op']) ? date("H:i", strtotime($rij['gescand_op'])) : '?';
                $html .= "<span class='tag-ok'>✔ $tijd</span><br><a href='#' onclick='doReset(" . $rij['id'] . "); return false;' style='font-size:10px; color:#666; text-decoration:none;'>Reset</a>";
            } else {
                $html .= "<span class='tag-wait'>Nog niet</span>";
            }
            $html .= "</td></tr>";
        }
    }

    echo json_encode(['totaal' => $totaal, 'binnen' => $binnen, 'te_gaan' => $nog_te_gaan, 'html' => $html]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#000000">
    <title>Berkhout Scanner</title>
    
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #111; color: white; padding-bottom: 80px; }
        
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 70px; background: #222; border-top: 1px solid #333; display: flex; z-index: 900; padding-bottom: env(safe-area-inset-bottom); }
        .nav-btn { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 11px; color: #666; cursor: pointer; user-select: none; }
        .nav-btn.active { color: #3b82f6; font-weight: bold; background: #1a1a1a; }
        .nav-icon { font-size: 24px; margin-bottom: 4px; }
        
        .tab-content { display: none; height: 100vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .tab-content.active { display: block; }
        
        /* Dashboard Styling */
        .dash-container { padding: 20px; padding-top: 40px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .stat-card { background: #222; padding: 15px 5px; border-radius: 10px; text-align: center; border: 1px solid #333; }
        .stat-num { font-size: 22px; font-weight: bold; }
        .stat-lbl { font-size: 10px; text-transform: uppercase; color: #888; margin-top:5px; }
        
        table { width: 100%; background: #222; border-collapse: collapse; border-radius: 10px; overflow: hidden; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #333; color: #eee; }
        .tag-ok { background: #064e3b; color: #6ee7b7; padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;}
        .tag-wait { background: #450a0a; color: #fca5a5; padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;}
        
        /* Scanner Styling */
        .scan-wrapper { background: black; height: 100%; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        #reader { width: 100%; max-width: 500px; border-radius: 10px; overflow: hidden; border: 2px solid #444; }
        
        #start-btn { 
            padding: 20px 40px; font-size: 18px; font-weight: bold; 
            background: #2563eb; color: white; border: none; border-radius: 50px; 
            cursor: pointer; z-index: 10; box-shadow: 0 0 20px rgba(37, 99, 235, 0.5); 
        }
        
        #result-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 800; 
            display: none; flex-direction: column; justify-content: center; align-items: center; 
            text-align: center; color: white; padding: 20px; box-sizing: border-box;
        }
        .bg-success { background-color: #15803d; }
        .bg-error { background-color: #b91c1c; }
        .bg-warning { background-color: #c2410c; }
        
        .refresh-btn { float: right; font-size: 20px; cursor: pointer; text-decoration: none; padding: 5px; }
    </style>
</head>
<body>

    <div id="tab-scan" class="tab-content active">
        <div class="scan-wrapper">
            <div style="position:absolute; top:20px; left:0; width:100%; text-align:center; color:#888; font-size:12px; font-weight:bold; text-transform:uppercase; z-index:5;">
                Actieve Rit: <?php echo htmlspecialchars($actief_event_naam); ?><br>
                <a href="?ander_ritnummer=1" style="display:inline-block; margin-top:8px; background:#4b5563; color:white; padding:6px 16px; border-radius:20px; text-decoration:none; font-size:11px; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">🔄 Wijzig Rit</a>
            </div>
            <button id="start-btn" onclick="startCamera()">📷 Start Camera</button>
            <div id="reader"></div>
            
            <div id="result-overlay" onclick="resetScanner()">
                <div style="font-size: 80px; margin-bottom: 20px;" id="res-icon"></div>
                <h1 id="res-title" style="font-size:2.5rem; margin:0; line-height: 1.1;">TITEL</h1>
                <p id="res-msg" style="font-size:1.5rem; margin-top: 20px; color: rgba(255,255,255,0.9);">Bericht</p>
                <div style="margin-top:60px; border:2px solid rgba(255,255,255,0.3); padding:15px 40px; border-radius:50px; font-weight: bold; background: rgba(0,0,0,0.2);">Tik om door te gaan</div>
            </div>
        </div>
    </div>

    <div id="tab-dash" class="tab-content">
        <div class="dash-container">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0;">📋 Passagiers</h2>
                <div onclick="updateList()" class="refresh-btn">🔄</div>
            </div>
            
            <div style="background:#1e3a8a; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold; color:#bfdbfe; font-size:14px;">
                <?php echo htmlspecialchars($actief_event_naam); ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div id="val-totaal" class="stat-num">-</div><div class="stat-lbl">Totaal</div></div>
                <div class="stat-card"><div id="val-binnen" class="stat-num" style="color:#4ade80;">-</div><div class="stat-lbl">Binnen</div></div>
                <div class="stat-card"><div id="val-tegaan" class="stat-num" style="color:#f87171;">-</div><div class="stat-lbl">Te gaan</div></div>
            </div>
            
            <table><tbody id="lijst-body"><tr><td colspan="2" style="text-align:center; color:#666;">Laden...</td></tr></tbody></table>
            
            <div style="margin-top: 40px; padding-bottom: 120px; text-align: center; display: flex; justify-content: center; gap: 10px;">
                <a href="?ander_ritnummer=1" style="flex: 1; max-width: 200px; padding: 15px 10px; background: #4b5563; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">🔄 Rit Wijzigen</a>
                
                <a href="?logout=1" style="flex: 1; max-width: 200px; padding: 15px 10px; background: #991b1b; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">🚪 Uitloggen</a>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <div class="nav-btn active" onclick="switchTab('scan', this)">
            <div class="nav-icon">📷</div>
            <div>Scanner</div>
        </div>
        <div class="nav-btn" onclick="switchTab('dash', this)">
            <div class="nav-icon">📋</div>
            <div>Lijst</div>
        </div>
    </div>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            btn.classList.add('active');
            if (tabId === 'dash') updateList();
        }

        function updateList() {
            fetch('scan_module_br26.php?action=get_list').then(res => res.json()).then(data => {
                document.getElementById('val-totaal').innerText = data.totaal;
                document.getElementById('val-binnen').innerText = data.binnen;
                document.getElementById('val-tegaan').innerText = data.te_gaan;
                document.getElementById('lijst-body').innerHTML = data.html;
            }).catch(err => {
                document.getElementById('lijst-body').innerHTML = "<tr><td colspan='2' style='text-align:center; color:#ef4444;'>Fout bij laden.</td></tr>";
            });
        }

        function doReset(id) {
            if(!confirm("Zeker weten resetten?")) return;
            let formData = new FormData(); formData.append('reset_id', id);
            fetch('scan_module_br26.php', { method: 'POST', body: formData }).then(res => { updateList(); });
        }

        const html5QrCode = new Html5Qrcode("reader");
        const startBtn = document.getElementById('start-btn');
        const readerDiv = document.getElementById('reader');
        const overlay = document.getElementById('result-overlay');

        function startCamera() {
            startBtn.style.display = 'none';
            readerDiv.style.display = 'block';
            
            html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
            .catch(err => { 
                alert("Kan camera niet starten. Controleer toestemming of gebruik HTTPS."); 
                location.reload(); 
            });
        }

        function onScanSuccess(decodedText) {
            html5QrCode.pause(); 
            
            let formData = new FormData();
            formData.append('scan_code', decodedText);

            fetch('scan_module_br26.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                document.getElementById('res-title').innerHTML = data.title;
                document.getElementById('res-msg').innerHTML = data.msg;
                overlay.style.display = 'flex';
                
                if (data.status === 'success') {
                    overlay.className = 'bg-success';
                    document.getElementById('res-icon').innerHTML = '✅';
                } else if (data.status === 'warning') {
                    overlay.className = 'bg-warning';
                    document.getElementById('res-icon').innerHTML = '⛔';
                } else {
                    overlay.className = 'bg-error';
                    document.getElementById('res-icon').innerHTML = '❌';
                }
            })
            .catch(err => { alert("Verbindingsfout"); resetScanner(); });
        }

        function resetScanner() {
            overlay.style.display = 'none';
            html5QrCode.resume();
        }
    </script>
</body>
</html>