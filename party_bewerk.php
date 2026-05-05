<?php
// party_bewerk.php
// VERSIE: GECORRIGEERDE KOLOMNAMEN (klant_email & klant_tel bij ophalen EN opslaan)

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

$id = $_GET['id'] ?? 0;

// 1. TICKET STATUS OMSCHAKELEN (SCANNEN)
if (isset($_GET['toggle_ticket'])) {
    $ticket_id = (int)$_GET['toggle_ticket'];
    
    // Check huidige status
    $stmt = $pdo->prepare("SELECT is_gescand FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $huidige_status = $stmt->fetchColumn();

    if ($huidige_status == 1) {
        // Was gescand -> Reset
        $pdo->prepare("UPDATE tickets SET is_gescand = 0, gescand_op = NULL WHERE id = ?")->execute([$ticket_id]);
    } else {
        // Was niet gescand -> Scan nu
        $pdo->prepare("UPDATE tickets SET is_gescand = 1, gescand_op = NOW() WHERE id = ?")->execute([$ticket_id]);
    }

    header("Location: party_bewerk.php?id=" . $id);
    exit;
}

// 2. WIJZIGINGEN OPSLAAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    
    // LET OP: Hier gebruiken we nu de JUISTE database kolommen (klant_email en klant_tel) voor het opslaan!
    $stmt = $pdo->prepare("UPDATE orders SET klant_naam=?, klant_email=?, klant_tel=?, status=? WHERE id=?");
    $stmt->execute([$_POST['naam'], $_POST['email'], $_POST['tel'], $_POST['status'], $id]);

    // Tickets updaten (Vorden & Zutphen)
    updateTickets($pdo, $id, 'Vorden', (int)$_POST['vorden']);
    updateTickets($pdo, $id, 'Zutphen', (int)$_POST['zutphen']);

    // Nieuwe totaalprijs berekenen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE order_id = ?");
    $stmt->execute([$id]);
    $totaal_tickets = $stmt->fetchColumn();
    
    // Prijs is 12.00 per stuk (pas dit aan als de prijs anders is)
    $nieuw_bedrag = number_format($totaal_tickets * 12.00, 2, '.', '');
    
    $pdo->prepare("UPDATE orders SET totaal_bedrag = ? WHERE id = ?")->execute([$nieuw_bedrag, $id]);

    // Terug naar het overzicht (met event_id zodat je in de juiste lijst komt)
    $stmt = $pdo->prepare("SELECT event_id FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $orderInfo = $stmt->fetch();
    
    header("Location: party_bestellingen.php?event_id=" . ($orderInfo['event_id'] ?? 0));
    exit;
}

function updateTickets($pdo, $order_id, $stad, $nieuw_aantal) {
    // We tellen flexibel (trimmen spaties)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE order_id=? AND bestemming LIKE ?");
    $stmt->execute([$order_id, "%$stad%"]); 
    $huidig_aantal = $stmt->fetchColumn();

    if ($nieuw_aantal > $huidig_aantal) {
        $erbij = $nieuw_aantal - $huidig_aantal;
        for($i=0; $i<$erbij; $i++) {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $pdo->prepare("INSERT INTO tickets (order_id, bestemming, unieke_code, is_gescand) VALUES (?, ?, ?, 0)")
                ->execute([$order_id, $stad, $code]);
        }
    } elseif ($nieuw_aantal < $huidig_aantal) {
        $eraf = $huidig_aantal - $nieuw_aantal;
        // Verwijder de laatste tickets van deze stad
        $pdo->prepare("DELETE FROM tickets WHERE order_id=? AND bestemming LIKE ? ORDER BY id DESC LIMIT $eraf")
            ->execute([$order_id, "%$stad%"]);
    }
}

// 3. DATA OPHALEN
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if(!$order) die("Bestelling niet gevonden.");

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE order_id = ? ORDER BY bestemming, id ASC");
$stmt->execute([$id]);
$alle_tickets = $stmt->fetchAll();

// Tellen (ongevoelig voor hoofdletters/spaties)
$aantal_vorden = 0; 
$aantal_zutphen = 0;

foreach($alle_tickets as $t) {
    $bestemming = strtolower(trim($t['bestemming'])); // alles kleine letters maken voor de check
    if(strpos($bestemming, 'vorden') !== false) $aantal_vorden++;
    if(strpos($bestemming, 'zutphen') !== false) $aantal_zutphen++;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bewerken #<?php echo $order['id']; ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 40px; background: #f0f2f5; }
        .box { background: white; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input, select { width: 100%; padding: 10px; margin: 5px 0 20px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        label { font-weight: bold; color: #555; font-size: 14px; }
        .btn-save { background: #2563eb; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; font-weight:bold;}
        .btn-save:hover { background: #1d4ed8; }
        .btn-back { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; }
        
        .ticket-list { margin-top: 30px; border-top: 2px dashed #eee; padding-top: 20px; }
        .ticket-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; align-items: center; }
        .ticket-code { font-family: monospace; font-size: 14px; color: #333; background: #f9f9f9; padding: 2px 6px; border-radius: 4px; border:1px solid #ddd; }
        
        .scan-action { text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .btn-checkin { background: #dcfce7; color: #166534; } 
        .btn-undo    { background: #fee2e2; color: #991b1b; } 
    </style>
</head>
<body>

<div class="box">
    <h2>✏️ Bestelling #<?php echo $order['id']; ?></h2>
    
    <form method="post">
        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">

        <label>Status Bestelling:</label>
        <select name="status">
            <option value="nieuw" <?php if($order['status'] == 'nieuw') echo 'selected'; ?>>❌ Niet Betaald (Nieuw)</option>
            <option value="betaald" <?php if($order['status'] == 'betaald') echo 'selected'; ?>>✅ Betaald</option>
            <option value="geannuleerd" <?php if($order['status'] == 'geannuleerd') echo 'selected'; ?>>🗑️ Geannuleerd</option>
        </select>
        
        <label>Naam:</label>
        <input type="text" name="naam" value="<?php echo htmlspecialchars($order['klant_naam']); ?>">
        
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($order['klant_email'] ?? ''); ?>">
        
        <label>Telefoon:</label>
        <input type="text" name="tel" value="<?php echo htmlspecialchars($order['klant_tel'] ?? ''); ?>">
        
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
        
        <label>Aantal Vorden (Nu: <?php echo $aantal_vorden; ?>)</label>
        <input type="number" name="vorden" min="0" value="<?php echo $aantal_vorden; ?>">
        
        <label>Aantal Zutphen (Nu: <?php echo $aantal_zutphen; ?>)</label>
        <input type="number" name="zutphen" min="0" value="<?php echo $aantal_zutphen; ?>">
        
        <button type="submit" class="btn-save">💾 Opslaan & Wijzigen</button>
    </form>
    
    <div class="ticket-list">
        <h3>🎫 Tickets Beheren</h3>
        <?php if(count($alle_tickets) == 0): ?>
            <p style="color:#888;">Geen tickets gevonden.</p>
        <?php else: ?>
            <?php foreach($alle_tickets as $t): ?>
                <div class="ticket-item">
                    <div>
                        <strong><?php echo htmlspecialchars($t['bestemming']); ?></strong>
                        <br>
                        <span class="ticket-code"><?php echo $t['unieke_code']; ?></span>
                    </div>
                    
                    <div>
                        <?php if($t['is_gescand']): ?>
                            <span style="color:green; font-weight:bold; font-size:12px; margin-right:5px;">✅ Gescand</span>
                            <a href="?id=<?php echo $id; ?>&toggle_ticket=<?php echo $t['id']; ?>" class="scan-action btn-undo">Reset</a>
                        <?php else: ?>
                            <span style="color:#991b1b; font-weight:bold; font-size:12px; margin-right:5px;">Open</span>
                            <a href="?id=<?php echo $id; ?>&toggle_ticket=<?php echo $t['id']; ?>" class="scan-action btn-checkin">Scannen</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="party_bestellingen.php?event_id=<?php echo $order['event_id']; ?>" class="btn-back">⬅️ Terug naar overzicht</a>
</div>

</body>
</html>