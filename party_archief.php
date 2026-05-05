<?php
// party_archief.php
// VERSIE: ARCHIEF OVERZICHT

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists('beveiliging.php')) {
    include 'beveiliging.php';
}

require_once __DIR__ . '/beheer/includes/db.php';

// 1. TERUGZETTEN LOGICA (Uit archief halen)
if (isset($_GET['herstel_id'])) {
    $id = (int)$_GET['herstel_id'];
    $pdo->prepare("UPDATE party_events SET is_archived = 0 WHERE id = ?")->execute([$id]);
    header("Location: party_archief.php");
    exit;
}

// 2. DEFINITIEF VERWIJDEREN (Optioneel)
if (isset($_GET['verwijder_id'])) {
    $id = (int)$_GET['verwijder_id'];
    
    // We verwijderen eerst de tickets en orders om de database schoon te houden
    $pdo->prepare("DELETE FROM tickets WHERE order_id IN (SELECT id FROM orders WHERE event_id = ?)")->execute([$id]);
    $pdo->prepare("DELETE FROM orders WHERE event_id = ?")->execute([$id]);
    
    // Dan het evenement zelf
    $pdo->prepare("DELETE FROM party_events WHERE id = ?")->execute([$id]);
    
    header("Location: party_archief.php");
    exit;
}

// 3. ARCHIEF DATA OPHALEN
try {
    // Let op: WHERE e.is_archived = 1
    $sql = "SELECT e.*, 
            (SELECT COUNT(t.id) FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.event_id = e.id AND o.status='betaald') as verkocht,
            (SELECT COALESCE(SUM(o.totaal_bedrag), 0) FROM orders o WHERE o.event_id = e.id AND o.status='betaald') as omzet
            FROM party_events e 
            WHERE e.is_archived = 1 
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
    <title>Archief - Berkhout</title>
    <style>
        body { font-family: sans-serif; background: #e2e8f0; padding: 20px; } /* Donkerdere achtergrond voor archief */
        .container { max-width: 1150px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top:20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; }
        
        /* Grijze tekst voor gearchiveerde items */
        td { color: #475569; }
        strong { color: #334155; }

        .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 4px; color: white; font-size: 12px; font-weight: bold; margin-right: 5px; display: inline-block; }
        .btn-groen { background: #10b981; } /* Herstellen */
        .btn-rood { background: #ef4444; } /* Verwijderen */
        .btn-blauw { background: #3b82f6; } /* Bestellingen bekijken */

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
        .btn-hoofd { 
            text-decoration: none; padding: 10px 20px; border-radius: 6px; 
            font-weight: bold; color: white; font-size: 14px;
            display: inline-block; background-color: #64748b;
        }
        .btn-hoofd:hover { background-color: #475569; }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <h1 style="margin: 0; color: #334155;">🗄️ Archief Evenementen</h1>
        <a href="party_beheer.php" class="btn-hoofd">⬅️ Terug naar Actief Beheer</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Evenement</th>
                <th>Verkocht</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($events) > 0): ?>
                <?php foreach($events as $e): ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($e['datum'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($e['naam']); ?></strong><br>
                            <small><?php echo htmlspecialchars($e['locatie']); ?></small>
                        </td>
                        
                        <td>
                            <?php echo $e['verkocht']; ?> / <?php echo $e['max_tickets']; ?><br>
                            <small>€ <?php echo number_format($e['omzet'], 2, ',', '.'); ?></small>
                        </td>
                        
                        <td>
                            <a href="party_bestellingen.php?event_id=<?php echo $e['id']; ?>" class="btn-action btn-blauw" title="Bekijk bestellingen">📂</a>
                            
                            <a href="?herstel_id=<?php echo $e['id']; ?>" class="btn-action btn-groen" onclick="return confirm('Dit feest weer actief maken op je dashboard?');" title="Zet terug">♻️ Herstellen</a>
                            
                            <a href="?verwijder_id=<?php echo $e['id']; ?>" class="btn-action btn-rood" onclick="return confirm('WEET JE DIT 100% ZEKER? Dit verwijdert het feest, alle orders en alle tickets permanent. Dit kan NIET ongedaan worden gemaakt!');" title="Permanent Verwijderen">🗑️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
                        <em>Het archief is momenteel leeg.</em>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>