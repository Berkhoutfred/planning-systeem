<?php
// party_handmatig.php
// VERSIE: DATABASE FIX (mollie_id verwijderd)

// Foutmeldingen aanzetten zodat we zien wat er misgaat
ini_set('display_errors', 1); error_reporting(E_ALL);

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

$event_id = $_GET['event_id'] ?? 0;
if(!$event_id) die("Geen Event ID meegegeven.");

$melding = "";

// Haltes ophalen
$stmt = $pdo->prepare("SELECT * FROM party_opstap_locaties WHERE event_id = ?");
$stmt->execute([$event_id]);
$locaties = $stmt->fetchAll();

// OPSLAAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $naam  = $_POST['naam'];
        $email = $_POST['email'];
        $tel   = $_POST['tel'];
        $tickets_post = $_POST['tickets'] ?? [];
        
        // 1. Totaal berekenen
        $totaal_bedrag = 0;
        foreach($locaties as $loc) {
            $aantal = (int)($tickets_post[$loc['id']] ?? 0);
            if($aantal > 0) {
                $totaal_bedrag += $aantal * $loc['prijs'];
            }
        }

        if($totaal_bedrag > 0) {
            // 2. Order aanmaken (zonder mollie_id)
            $sql = "INSERT INTO orders (event_id, klant_naam, email, tel, totaal_bedrag, status, datum) 
                    VALUES (?, ?, ?, ?, ?, 'betaald', NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$event_id, $naam, $email, $tel, $totaal_bedrag]);
            $order_id = $pdo->lastInsertId();

            // 3. Tickets aanmaken
            foreach($locaties as $loc) {
                $aantal = (int)($tickets_post[$loc['id']] ?? 0);
                for($i=0; $i<$aantal; $i++) {
                    $unieke_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
                    
                    $pdo->prepare("INSERT INTO tickets (order_id, event_id, locatie_id, prijs, bestemming, status, unieke_code) VALUES (?, ?, ?, ?, ?, 'betaald', ?)")
                        ->execute([$order_id, $event_id, $loc['id'], $loc['prijs'], $loc['naam'], $unieke_code]);
                }
            }
            
            // Succes! Terugsturen
            header("Location: party_bestellingen.php?event_id=$event_id");
            exit;

        } else {
            $melding = "⚠️ Je moet minimaal 1 ticket selecteren.";
        }
    } catch (Exception $e) {
        $melding = "❌ Fout bij opslaan: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Handmatig Toevoegen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #2563eb; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; }
        .btn:hover { background: #1d4ed8; }
        
        .ticket-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .ticket-row input { width: 60px; text-align: center; margin: 0; font-size: 16px; padding: 5px; }
        
        .alert { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #fca5a5; }
    </style>
</head>
<body>

<div class="container">
    <a href="party_bestellingen.php?event_id=<?php echo $event_id; ?>" style="text-decoration:none; color:#666; font-size: 14px;">⬅️ Annuleren & Terug</a>
    <h2 style="margin-top: 10px;">➕ Klant Toevoegen</h2>
    
    <?php if($melding): ?>
        <div class="alert"><?php echo $melding; ?></div>
    <?php endif; ?>

    <?php if(count($locaties) == 0): ?>
        <p style="color:red;">⚠️ Er zijn geen tickets/haltes gevonden voor dit evenement.</p>
        <p>Ga eerst naar "Haltes & Prijzen" in het beheer om tickets aan te maken.</p>
    <?php else: ?>

    <form method="post">
        <label>Naam Klant *</label>
        <input type="text" name="naam" required placeholder="Bijv. Jan Jansen">

        <label>E-mail (optioneel)</label>
        <input type="email" name="email" placeholder="Voor tickets per mail">

        <label>Telefoon (optioneel)</label>
        <input type="text" name="tel" placeholder="06...">

        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px;">Tickets kiezen</h3>
        
        <?php foreach($locaties as $loc): ?>
        <div class="ticket-row">
            <div>
                <strong><?php echo htmlspecialchars($loc['naam']); ?></strong><br>
                <small style="color:#666;">€ <?php echo number_format($loc['prijs'], 2, ',', '.'); ?> p.s.</small>
            </div>
            <input type="number" name="tickets[<?php echo $loc['id']; ?>]" value="0" min="0">
        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn">💾 Opslaan & Toevoegen</button>
    </form>
    
    <?php endif; ?>
</div>

</body>
</html>