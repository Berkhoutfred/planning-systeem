<?php
// party_event_bewerk.php
// VERSIE: MET EXTRA TICKET INFO VELD

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

$id = $_GET['id'] ?? 0;
$melding = "";

// OPSLAAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $naam = $_POST['naam'];
    $datum = $_POST['datum'];
    $tijd = $_POST['vertrektijd']; // Let op: naam in formulier moet matchen
    $locatie = $_POST['locatie'];
    $max_tickets = $_POST['max_tickets'];
    $ticket_info = $_POST['ticket_info']; // <--- NIEUW

    $sql = "UPDATE party_events SET naam=?, datum=?, vertrektijd=?, locatie=?, max_tickets=?, ticket_info=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$naam, $datum, $tijd, $locatie, $max_tickets, $ticket_info, $id]);
    
    $melding = "✅ Opgeslagen!";
}

// OPHALEN
$stmt = $pdo->prepare("SELECT * FROM party_events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) die("Evenement niet gevonden.");
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Evenement Bewerken</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input, textarea { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;}
        .btn { background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .alert { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <a href="party_beheer.php" style="text-decoration:none; color:#666;">⬅️ Terug</a>
    <h1>✏️ <?php echo htmlspecialchars($event['naam']); ?> bewerken</h1>

    <?php if($melding): ?><div class="alert"><?php echo $melding; ?></div><?php endif; ?>

    <form method="post">
        <label>Naam Evenement</label>
        <input type="text" name="naam" value="<?php echo htmlspecialchars($event['naam']); ?>" required>

        <label>Datum</label>
        <input type="date" name="datum" value="<?php echo $event['datum']; ?>" required>
        
        <label>Vertrektijd (terugreis)</label>
        <input type="time" name="vertrektijd" value="<?php echo $event['vertrektijd']; ?>">

        <label>Locatie (Bestemming)</label>
        <input type="text" name="locatie" value="<?php echo htmlspecialchars($event['locatie']); ?>" required>

        <label>Max aantal tickets</label>
        <input type="number" name="max_tickets" value="<?php echo $event['max_tickets']; ?>">

        <label><strong>Extra Info op Ticket</strong> (Bijv: "Zorg dat je er 15 min eerder bent")</label>
        <textarea name="ticket_info" rows="4" placeholder="Typ hier instructies voor de klant..."><?php echo htmlspecialchars($event['ticket_info'] ?? ''); ?></textarea>

        <button type="submit" class="btn">💾 Opslaan</button>
    </form>
</div>

</body>
</html>