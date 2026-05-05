<?php
// Bestand: party_beheer.php
// VERSIE: Custom Root-Header (Speciaal voor bestanden buiten de beheer-map) & Strakke knoppen

// 1. Foutmeldingen forceren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Beveiliging checken 
if (file_exists('beveiliging.php')) {
    include 'beveiliging.php';
} elseif (file_exists('beheer/beveiliging.php')) {
    include 'beheer/beveiliging.php';
}

// 3. Database verbinden (lokale ERP-config)
require_once __DIR__ . '/beheer/includes/db.php';

// 4A. AAN/UIT KNOP LOGICA
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $pdo->prepare("UPDATE party_events SET is_active = IF(is_active=1, 0, 1) WHERE id = ?")->execute([$id]);
    header("Location: party_beheer.php");
    exit;
}

// 4B. ARCHIVEREN LOGICA
if (isset($_GET['archiveer_id'])) {
    $id = (int)$_GET['archiveer_id'];
    $pdo->prepare("UPDATE party_events SET is_archived = 1 WHERE id = ?")->execute([$id]);
    header("Location: party_beheer.php");
    exit;
}

// 5. DATA OPHALEN (Alleen de actieve feesten!)
try {
    $sql = "SELECT e.*, 
            (SELECT COUNT(t.id) FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.event_id = e.id AND o.status='betaald') as verkocht,
            (SELECT COALESCE(SUM(o.totaal_bedrag), 0) FROM orders o WHERE o.event_id = e.id AND o.status='betaald') as omzet
            FROM party_events e 
            WHERE e.is_archived = 0 
            ORDER BY datum DESC";
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    die("❌ Fout bij ophalen lijst: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Party Beheer | Berkhout Reizen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        body { margin: 0; padding: 0; background: #f4f7f6; font-family: sans-serif; }
        
        /* EXACTE KOPIE VAN DE BLAUWE HEADER */
        .custom-header { background: #003366; display: flex; justify-content: space-between; align-items: center; height: 60px; padding: 0 20px; }
        .custom-header a { color: white; text-decoration: none; font-size: 14px; font-weight: bold; padding: 0 15px; height: 100%; display: flex; align-items: center; transition: 0.2s; }
        .custom-header a:hover { background: rgba(255,255,255,0.1); }
        .custom-header .logo { font-size: 22px; font-weight: 900; padding: 0 20px 0 0; letter-spacing: -0.5px; }
        .custom-header .logo:hover { background: transparent; }
        .header-links-left, .header-links-right { display: flex; height: 100%; align-items: center; }
        .active-tab { background: #31527c; } /* Lichter blauw voor de actieve tab */

        /* DASHBOARD STYLING */
        .dashboard-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .titel-balk { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .titel-balk h1 { margin: 0; color: #003366; font-size: 26px; font-weight: bold; }
        .knoppen-balk { display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* KNOPPEN */
        .btn { padding: 8px 15px; border-radius: 5px; font-size: 13px; font-weight: bold; text-decoration: none; color: white; transition: 0.2s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.15); color: white; }
        .btn-groen { background: #28a745; }
        .btn-blauw { background: #007bff; }
        .btn-oranje { background: #fd7e14; }
        .btn-paars { background: #6f42c1; }
        .btn-rood { background: #dc3545; }
        .btn-light { background: #f8f9fa; color: #333; border: 1px solid #ddd; box-shadow: none; }
        .btn-light:hover { background: #e2e6ea; color: #333; }

        /* TABEL PANEEL */
        .info-panel { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid #eaeaea; }
        .tabel-modern { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
        .tabel-modern th, .tabel-modern td { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
        .tabel-modern th { background-color: #f8f9fa; color: #4a5568; font-weight: bold; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        .tabel-modern tr:last-child td { border-bottom: none; }
        .tabel-modern tr:hover { background-color: #fcfcfc; }

        /* BADGES */
        .status-badge { padding: 5px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; display: inline-block; text-decoration: none; transition: 0.2s; }
        .status-badge:hover { opacity: 0.8; }
        .bg-green { background: #e6ffed; color: #1e7e34; border: 1px solid #c3e6cb; }
        .bg-red { background: #ffeded; color: #c82333; border: 1px solid #f5c6cb; }
        .rit-tag { background: #e9ecef; color: #495057; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; margin-top: 6px; }
        .actie-knoppen { display: flex; gap: 6px; }
        .actie-knoppen .btn { padding: 6px 12px; }
        
        .footer { text-align: left; padding: 20px; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>

<div class="custom-header">
    <div class="header-links-left">
        <a href="../index.php" class="logo">Berkhout Reizen</a>
        <a href="../index.php">Dashboard</a>
        <a href="../calculaties.php">Offertes & Sales</a>
        <a href="../live_planbord.php">Live Planbord</a>
        <a href="../weekrooster.php">Weekrooster</a>
        <a href="../agenda.php">Agenda</a>
        <a href="party_beheer.php" class="active-tab">Evenementen</a>
    </div>
    <div class="header-links-right">
        <a href="../index.php">Administratie <i class="fas fa-cog" style="margin-left:5px;"></i></a>
    </div>
</div>

<div class="dashboard-container">
    
    <div class="titel-balk">
        <h1>🎉 Party Beheer</h1>
        <div class="knoppen-balk">
            <a href="party_archief.php" class="btn btn-oranje"><i class="fas fa-archive"></i> Archief</a>
            <a href="party_locaties.php" class="btn btn-blauw"><i class="fas fa-map-marker-alt"></i> Locaties Beheren</a>
            <a href="party_toevoegen.php" class="btn btn-groen"><i class="fas fa-plus"></i> Nieuw Event</a>
        </div>
    </div>

    <div class="info-panel">
        <table class="tabel-modern">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Evenement</th>
                    <th>Status</th>
                    <th>Verkocht</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($events) == 0): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 40px; color:#666; font-style:italic;">Geen actieve evenementen gevonden.</td></tr>
                <?php else: ?>
                    <?php foreach($events as $e) { ?>
                        <tr>
                            <td>
                                <strong style="color: #333; font-size: 14px;"><?php echo date('d-m-Y', strtotime($e['datum'])); ?></strong>
                            </td>
                            <td>
                                <strong style="font-size: 15px; color: #003366;"><?php echo htmlspecialchars($e['naam']); ?></strong><br>
                                <span style="color: #6c757d; font-size: 12px;"><i class="fas fa-map-pin" style="color:#adb5bd; margin-right:4px;"></i><?php echo htmlspecialchars($e['locatie']); ?></span><br>
                                <div class="rit-tag"><i class="fas fa-bus-alt" style="margin-right:4px; color:#6c757d;"></i> Rit-nummer: <?php echo $e['id']; ?></div>
                            </td>
                            <td>
                                <a href="?toggle_status=<?php echo $e['id']; ?>" class="status-badge <?php echo ($e['is_active'] == 1) ? 'bg-green' : 'bg-red'; ?>">
                                    <?php echo ($e['is_active'] == 1) ? '● Online' : '✖ Offline'; ?>
                                </a>
                            </td>
                            <td>
                                <strong style="font-size: 15px; color: #333;"><?php echo $e['verkocht']; ?></strong> <span style="color:#adb5bd; font-size: 13px;">/ <?php echo $e['max_tickets']; ?></span><br>
                                <span style="color:#28a745; font-weight:bold; font-size:12px;">€ <?php echo number_format($e['omzet'], 2, ',', '.'); ?></span>
                            </td>
                            <td>
                                <div class="actie-knoppen">
                                    <a href="party_bestellingen.php?event_id=<?php echo $e['id']; ?>" class="btn btn-blauw" title="Bestellingen"><i class="fas fa-users"></i> Orders</a>
                                    <a href="party_haltes.php?event_id=<?php echo $e['id']; ?>" class="btn btn-paars" title="Haltes"><i class="fas fa-map-signs"></i> Haltes</a>
                                    <a href="party_event_bewerk.php?id=<?php echo $e['id']; ?>" class="btn btn-oranje" title="Bewerken"><i class="fas fa-edit"></i> Bewerken</a>
                                    <a href="?archiveer_id=<?php echo $e['id']; ?>" class="btn btn-rood" onclick="return confirm('Weet je zeker dat je dit evenement wilt archiveren?');" title="Archiveren"><i class="fas fa-trash-alt"></i> Archiveren</a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="footer">
    &copy; <?php echo date('Y'); ?> Dit is een product van Berkhout Reizen
</div>

</body>
</html>