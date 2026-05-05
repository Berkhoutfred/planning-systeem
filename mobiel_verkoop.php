<?php
// mobiel_verkoop.php - Mobiele Verkoop App (Alleen Actieve Events) 📱

session_start();
header("X-Robots-Tag: noindex, nofollow"); // Onzichtbaar voor Google
require_once __DIR__ . '/env.php';

// ---> STEL HIER JOUW GEHEIME PINCODE / WACHTWOORD IN <---
$geheime_pin = env_value('MOBILE_SALES_PIN', ''); 

// 1. DATABASE VERBINDING
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database fout");
}

// 2. LOGIN LOGICA
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: mobiel_verkoop.php");
    exit;
}

$login_fout = "";
if (isset($_POST['login'])) {
    if ($_POST['pin'] === $geheime_pin) {
        $_SESSION['baas_ingelogd'] = true;
    } else {
        $login_fout = "❌ Verkeerde code";
    }
}

// 3. HET INLOGSCHERM
if (!isset($_SESSION['baas_ingelogd'])) {
    echo '<!DOCTYPE html><html><meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no"><body style="font-family:-apple-system, system-ui; text-align:center; padding:50px 20px; background:#f3f4f6; color:#111;">
    <div style="background:white; padding:30px; border-radius:15px; max-width:400px; margin:0 auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0; color:#1e3a8a;">📊 Verkoop Monitor</h2>
        <p style="color:#666; font-size:14px; margin-bottom:20px;">Alleen toegang voor directie.</p>';
        
        if ($login_fout) echo '<div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:8px; margin-bottom:15px; font-weight:bold;">'.$login_fout.'</div>';

    echo '<form method="post" style="display: flex; flex-direction: column; gap: 15px;">
            <input type="password" name="pin" placeholder="Voer code in..." required style="padding:15px; font-size:18px; border-radius:8px; border:2px solid #e5e7eb; width: 100%; box-sizing: border-box; text-align:center;">
            <button type="submit" name="login" style="padding:15px; background:#1e3a8a; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer;">Bekijk Cijfers</button>
        </form>
    </div></body></html>';
    exit;
}

// 4. DATA OPHALEN (Nu ALLEEN de actieve, online feesten!)
$sql = "SELECT e.*, 
        (SELECT COUNT(t.id) FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.event_id = e.id AND o.status='betaald') as verkocht,
        (SELECT COALESCE(SUM(o.totaal_bedrag), 0) FROM orders o WHERE o.event_id = e.id AND o.status='betaald') as omzet
        FROM party_events e 
        WHERE e.is_archived = 0 AND e.is_active = 1
        ORDER BY datum DESC";
$stmt = $pdo->query($sql);
$events = $stmt->fetchAll();

$totaal_omzet_actief = 0;
$totaal_tickets_actief = 0;
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e3a8a">
    <title>📱 Verkoop Monitor</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; color: #333; padding-bottom: 40px; }
        .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 5px 0 0 0; font-size: 12px; opacity: 0.8; }
        
        .container { padding: 15px; }
        
        .card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 5px solid #16a34a; }
        
        .event-naam { font-size: 16px; font-weight: bold; color: #111; margin: 0 0 5px 0; }
        .event-datum { font-size: 12px; color: #666; margin-bottom: 12px; }
        
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-box { background: #f8fafc; padding: 10px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-titel { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
        .stat-waarde { font-size: 18px; font-weight: bold; color: #0f172a; }
        .stat-omzet { color: #16a34a; }
        
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; margin-bottom: 10px; background: #dcfce7; color: #166534; }

        .totaal-balk { background: #1e3a8a; color: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 10px 15px rgba(30, 58, 138, 0.2); }
        
        .refresh-btn { display: block; text-align: center; background: #e0e7ff; color: #4338ca; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>📈 Berkhout Monitor</h1>
        <p>Actuele Verkoopstanden</p>
    </div>

    <div class="container">
        
        <a href="" class="refresh-btn">🔄 Ververs Cijfers</a>

        <?php 
        foreach($events as $e) { 
            $totaal_omzet_actief += $e['omzet'];
            $totaal_tickets_actief += $e['verkocht'];
        } 
        ?>

        <div class="totaal-balk">
            <div style="font-size: 12px; text-transform: uppercase; opacity: 0.9; margin-bottom: 5px;">Totale Omzet (Nu Online)</div>
            <div style="font-size: 32px; font-weight: bold; margin-bottom: 10px;">€ <?php echo number_format($totaal_omzet_actief, 2, ',', '.'); ?></div>
            <div style="font-size: 14px;">🎫 <?php echo $totaal_tickets_actief; ?> tickets verkocht</div>
        </div>

        <?php if(count($events) == 0): ?>
            <div style="text-align:center; padding:30px; color:#666; font-style:italic;">Er zijn momenteel geen actieve evenementen.</div>
        <?php else: ?>
            <h3 style="font-size: 14px; color: #666; margin-bottom: 15px; text-transform: uppercase;">Overzicht per rit</h3>

            <?php foreach($events as $e): ?>
                <div class="card">
                    <div class="status-badge">● Online (Rit: <?php echo $e['id']; ?>)</div>
                    
                    <h2 class="event-naam"><?php echo htmlspecialchars($e['naam']); ?></h2>
                    <div class="event-datum">📅 <?php echo date('d-m-Y', strtotime($e['datum'])); ?></div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-titel">Tickets</div>
                            <div class="stat-waarde"><?php echo $e['verkocht']; ?> <span style="font-size: 12px; color: #94a3b8;">/ <?php echo $e['max_tickets']; ?></span></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-titel">Omzet</div>
                            <div class="stat-waarde stat-omzet">€ <?php echo number_format($e['omzet'], 2, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="?logout=1" style="color: #ef4444; text-decoration: none; font-weight: bold; padding: 10px 20px; background: white; border-radius: 8px; border: 1px solid #fca5a5;">🚪 Uitloggen</a>
        </div>

    </div>

</body>
</html>