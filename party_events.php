<?php
include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

// 1. ACTIE: Verwijderen
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM party_events WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: party_events.php");
    exit;
}

// 2. ACTIE: Status wisselen
if (isset($_GET['wissel_id'])) {
    $id = (int)$_GET['wissel_id'];
    $huidige_status = $_GET['status'];
    $nieuwe_status = ($huidige_status == 'actief') ? 'inactief' : 'actief';
    $stmt = $pdo->prepare("UPDATE party_events SET status = ? WHERE id = ?");
    $stmt->execute([$nieuwe_status, $id]);
    header("Location: party_events.php"); 
    exit;
}

// 3. LIJST OPHALEN
$result = $pdo->query("SELECT * FROM party_events ORDER BY datum ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Evenementen Beheer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; }
        
        /* Knoppen */
        .btn { padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; display: inline-block; }
        .btn-terug { background: #6b7280; color: white; margin-bottom: 20px; }
        .btn-groen { background: #22c55e; color: white; float: right; }
        .btn-rood { background: #ef4444; color: white; font-size: 12px; padding: 5px 10px; margin-left: 5px; }
        .btn-blauw { background: #3b82f6; color: white; font-size: 12px; padding: 5px 10px; }

        /* Status labels */
        .status-aan { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-uit { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9fafb; }
    </style>
</head>
<body>

<div class="container">
<a href="party_beheer.php" class="btn btn-terug">⬅️ Naar Bestellingen (Dashboard)</a>
<a href="party_toevoegen.php" class="btn btn-groen">+ Nieuw Event</a>

    <h1>📅 Events Beheer</h1>
    
    <table>
        <thead>
            <tr>
                <th>Datum / Tijd</th>
                <th>Naam</th>
                <th>Locatie</th>
                <th>Prijs</th>
                <th>Status</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($result as $row): ?>
            <tr>
                <td>
                    <?php echo date('d-m-Y', strtotime($row['datum'])); ?><br>
                    <small style="color:#666;"><?php echo substr($row['vertrektijd'], 0, 5); ?> uur</small>
                </td>
                <td style="font-weight:bold;"><?php echo $row['naam']; ?></td>
                <td><?php echo $row['locatie']; ?></td>
                <td>€ <?php echo number_format($row['prijs'], 2, ',', '.'); ?></td>
                <td>
                    <a href="party_events.php?wissel_id=<?php echo $row['id']; ?>&status=<?php echo $row['status']; ?>" style="text-decoration:none;">
                        <?php if($row['status'] == 'actief'): ?>
                            <span class="status-aan">✅ Actief</span>
                        <?php else: ?>
                            <span class="status-uit">❌ Inactief</span>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                    <a href="party_event_bewerk.php?id=<?php echo $row['id']; ?>" class="btn btn-blauw">✏️</a>
                    <a href="party_events.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-rood" onclick="return confirm('Weet je het zeker?');">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>