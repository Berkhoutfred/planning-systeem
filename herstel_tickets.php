<?php
// herstel_tickets.php
// VERSIE: DIEP HERSTEL (Koppelt zwevende orders aan het juiste event)
require_once __DIR__ . '/env.php';

// 1. DATABASE VERBINDING
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database verbinding mislukt.");
}

$bericht = "";

// Haal alle evenementen op voor het keuzemenu
$stmt = $pdo->query("SELECT id, naam, datum FROM party_events ORDER BY datum DESC");
$events = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $event_id = (int)$_POST['event_id'];

    // Check of order bestaat
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $bericht = "❌ Order #$order_id niet gevonden in de database.";
    } else {
        // 1. REPARATIE: Update de order (Zet event_id goed én status op betaald)
        $stmt = $pdo->prepare("UPDATE orders SET status = 'betaald', event_id = ? WHERE id = ?");
        $stmt->execute([$event_id, $order_id]);

        // 2. REPARATIE: Update ook alle losse tickets van deze order met het juiste event_id
        // (Soms kijkt het dashboard naar de status van het ticket zelf, dus die zetten we voor de zekerheid ook goed)
        $stmt = $pdo->prepare("UPDATE tickets SET event_id = ? WHERE order_id = ?");
        $stmt->execute([$event_id, $order_id]);
        
        // Check of de tickets tabel toevallig ook een status kolom heeft en update die stilzwijgend
        try {
            $pdo->prepare("UPDATE tickets SET status = 'betaald' WHERE order_id = ?")->execute([$order_id]);
        } catch(Exception $e) { /* Negeer als kolom niet bestaat */ }

        // 3. Mail opstellen en versturen
        $ontvanger = $order['klant_email'] ?? ($order['email'] ?? '');
        
        if (!empty($ontvanger)) {
            $subject = "Je tickets voor Berkhout Reizen (Order #$order_id)";
            $link = "https://www.berkhoutreizen.nl/party_ticket_print.php?order_id=" . $order_id;

            $message = "<html><body style='font-family: sans-serif; padding: 20px; background-color: #f4f4f4;'>";
            $message .= "<div style='background: white; padding: 20px; border-radius: 5px; max-width: 600px; margin: auto;'>";
            $message .= "<h2 style='color: #2563eb;'>Bedankt " . htmlspecialchars($order['klant_naam']) . "!</h2>";
            $message .= "<p>De betaling is succesvol verwerkt. <em>(Onze excuses voor de verlate bevestiging door een technische storing aan onze kant).</em></p>";
            $message .= "<p>Jouw tickets zijn nu definitief klaargezet. Klik op de onderstaande knop om ze te bekijken en op te slaan:</p>";
            $message .= "<br>";
            $message .= "<a href='$link' style='background:#22c55e; color:white; padding:15px 25px; text-decoration:none; font-weight:bold; border-radius:5px; display:inline-block;'>⬇️ Download Tickets</a>";
            $message .= "<br><br>";
            $message .= "<p style='font-size: 12px; color: #666;'>Werkt de knop niet? Kopieer deze link: <br> $link</p>";
            $message .= "</div></body></html>";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: info@berkhoutreizen.nl\r\n";

            mail($ontvanger, $subject, $message, $headers);

            $bericht = "✅ Succes! Order #$order_id is gerepareerd, gekoppeld aan het evenement, en de correcte tickets zijn gemaild naar $ontvanger.";
        } else {
            $bericht = "⚠️ Order #$order_id is gerepareerd en zichtbaar in je dashboard, maar er was geen e-mailadres om de tickets naar te mailen.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Diep Herstel Tickets</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 50px; text-align: center; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input, select { padding: 12px; width: 100%; box-sizing: border-box; font-size: 16px; margin-bottom: 20px; border: 2px solid #ccc; border-radius: 6px; }
        button { padding: 12px 24px; font-size: 16px; width: 100%; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:hover { background: #1d4ed8; }
        .melding { margin-top: 25px; font-weight: bold; font-size: 16px; padding: 15px; background: #dcfce7; color: #166534; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🛠️ Diep Herstel Tool</h2>
        <p>Koppel een 'zwevende' betaling handmatig aan het juiste evenement en verstuur de gerepareerde tickets direct naar de klant.</p>
        
        <form method="POST">
            <label style="display:block; text-align:left; font-weight:bold; margin-bottom:5px;">1. Mollie Order ID:</label>
            <input type="number" name="order_id" placeholder="Bijv. 115" required>
            
            <label style="display:block; text-align:left; font-weight:bold; margin-bottom:5px;">2. Bij welk evenement hoort dit?</label>
            <select name="event_id" required>
                <option value="">-- Kies het juiste evenement --</option>
                <?php foreach($events as $ev): ?>
                    <option value="<?php echo $ev['id']; ?>">
                        <?php echo date('d-m-Y', strtotime($ev['datum'])) . " - " . htmlspecialchars($ev['naam']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Repareer & Mail Klant</button>
        </form>

        <?php if($bericht): ?>
            <div class="melding"><?php echo $bericht; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>