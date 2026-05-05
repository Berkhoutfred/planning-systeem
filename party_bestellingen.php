<?php
// party_bestellingen.php
// VERSIE: HERSTEL + HALTE STATISTIEKEN

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

$event_id = $_GET['event_id'] ?? 0;

// 1. STATUS UPDATE VERWERKEN (Snelmenu)
if (isset($_POST['update_status']) && isset($_POST['order_id'])) {
    $nieuwe_status = $_POST['nieuwe_status']; 
    $id = (int)$_POST['order_id'];
    
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$nieuwe_status, $id]);
    $pdo->prepare("UPDATE tickets SET status = ? WHERE order_id = ?")->execute([$nieuwe_status, $id]);
    
    header("Location: party_bestellingen.php?event_id=" . $event_id);
    exit;
}

// 2. VERWIJDEREN
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM tickets WHERE order_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
    
    header("Location: party_bestellingen.php?event_id=" . $event_id);
    exit;
}

// 3. GEGEVENS OPHALEN
$stmt = $pdo->prepare("SELECT * FROM party_events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE event_id = ? ORDER BY datum DESC");
$stmt->execute([$event_id]);
$orders = $stmt->fetchAll();

// 4. STATISTIEKEN PER HALTE OPHALEN
$halte_stats_stmt = $pdo->prepare("
    SELECT t.bestemming as halte_naam, COUNT(t.id) as aantal 
    FROM tickets t 
    JOIN orders o ON t.order_id = o.id 
    WHERE o.event_id = ? AND o.status = 'betaald' 
    GROUP BY t.bestemming 
    ORDER BY aantal DESC
");
$halte_stats_stmt->execute([$event_id]);
$halte_stats = $halte_stats_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bestellingen Beheer</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 1250px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f9fafb; color: #555; font-size: 12px; text-transform: uppercase; }
        tr:hover { background: #fcfcfc; }

        .btn-back { background: #333; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; display:inline-block; margin-bottom:15px;}
        
        /* KNOPPEN STIJL */
        .btn-resend { background: #f59e0b; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 12px; display:inline-block;}
        .btn-edit   { background: #2563eb; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 12px; margin-left: 5px; display:inline-block;}
        .btn-delete { color: #dc2626; text-decoration: none; font-size: 18px; margin-left: 10px; vertical-align: middle;}
        
        select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer;}
        .top-buttons { float: right; }
        .btn-action { display: inline-block; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; color: white; margin-left: 5px; }
        .btn-add { background: #2563eb; }
        .btn-excel { background: #16a34a; }

        /* NIEUW: STATISTIEKEN BLOK */
        .stats-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .stats-box h3 { margin-top: 0; margin-bottom: 10px; font-size: 16px; color: #334155; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 15px; }
        .stat-item { background: white; border: 1px solid #e2e8f0; padding: 10px 15px; border-radius: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .stat-naam { font-size: 14px; color: #64748b; display: block; margin-bottom: 5px; }
        .stat-aantal { font-size: 18px; font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>

<a href="party_beheer.php" class="btn-back">⬅️ Terug</a>

<div class="container">
    <div class="top-buttons">
        <a href="party_export.php?event_id=<?php echo $event_id; ?>" class="btn-action btn-excel">📗 Export Excel</a>
        <a href="party_handmatig.php?event_id=<?php echo $event_id; ?>" class="btn-action btn-add">➕ Handmatig Toevoegen</a>
    </div>

    <h1>📂 <?php echo htmlspecialchars($event['naam'] ?? 'Bestellingen'); ?></h1>

    <?php if(count($halte_stats) > 0): ?>
    <div class="stats-box">
        <h3>📊 Verkochte tickets per halte (Betaald)</h3>
        <div class="stats-grid">
            <?php 
            $totaal = 0;
            foreach($halte_stats as $stat): 
                $totaal += $stat['aantal'];
            ?>
                <div class="stat-item">
                    <span class="stat-naam"><?php echo htmlspecialchars($stat['halte_naam']); ?></span>
                    <span class="stat-aantal"><?php echo $stat['aantal']; ?> tickets</span>
                </div>
            <?php endforeach; ?>
            <div class="stat-item" style="background: #e0f2fe; border-color: #bae6fd;">
                <span class="stat-naam" style="color: #0369a1;">Totaal Betaald</span>
                <span class="stat-aantal" style="color: #0c4a6e;"><?php echo $totaal; ?> tickets</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Klant</th>
                <th>Tickets</th>
                <th>Bedrag</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($orders as $order): ?>
            <tr>
                <td>#<?php echo $order['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($order['klant_naam']); ?></strong><br>
                    <small><?php echo htmlspecialchars($order['klant_email']); ?> | <?php echo htmlspecialchars($order['klant_tel'] ?? ''); ?></small>
                </td>
                <td>
                    <?php 
                    $t_stmt = $pdo->prepare("SELECT COUNT(*) as a, bestemming FROM tickets WHERE order_id=? GROUP BY bestemming");
                    $t_stmt->execute([$order['id']]);
                    foreach($t_stmt->fetchAll() as $r) { echo $r['a'] . "x " . htmlspecialchars($r['bestemming']) . "<br>"; }
                    ?>
                </td>
                <td>€ <?php echo number_format($order['totaal_bedrag'], 2, ',', '.'); ?></td>
                
                <td>
                    <form method="post" action="party_bestellingen.php?event_id=<?php echo $event_id; ?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <select name="nieuwe_status" onchange="this.form.submit()">
                            <option value="nieuw" <?php if($order['status']=='nieuw') echo 'selected'; ?>>❌ Niet Betaald</option>
                            <option value="betaald" <?php if($order['status']=='betaald') echo 'selected'; ?>>✅ Betaald</option>
                        </select>
                    </form>
                </td>

                <td style="white-space:nowrap;">
                    <a href="party_resend.php?id=<?php echo $order['id']; ?>" class="btn-resend" onclick="return confirm('Tickets opnieuw versturen?')">✉️ Resend</a>
                    
                    <a href="party_bewerk.php?id=<?php echo $order['id']; ?>" class="btn-edit" title="Gegevens wijzigen">✏️</a>

                    <a href="party_bestellingen.php?event_id=<?php echo $event_id; ?>&delete_id=<?php echo $order['id']; ?>" class="btn-delete" onclick="return confirm('Definitief verwijderen?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>