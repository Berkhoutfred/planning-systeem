<?php
// party_succes.php
// VERSIE: HERSTELDE CONFETTI & KNOP

require_once __DIR__ . '/beheer/includes/db.php';

$order_id = (int)$_GET['order_id'];
if(!$order_id) die("Geen bestelling gevonden.");

// Order info ophalen
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if(!$order) die("Order niet gevonden.");
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bestelling Geslaagd!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 40px 20px; text-align: center; color: #333; }
        .container { 
            max-width: 500px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
            border-top: 6px solid #22c55e; 
            position: relative;
            z-index: 10; 
        }
        
        h1 { color: #166534; margin-bottom: 10px; }
        .checkmark { font-size: 60px; color: #22c55e; display: block; margin-bottom: 20px; }
        .order-box { background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb; }
        
        .btn-download { 
            display: block; 
            width: 100%;
            padding: 15px 0; 
            background: #2563eb; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: bold; 
            font-size: 18px;
            margin: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-download:hover { background: #1d4ed8; }

        .btn-home { color:#888; text-decoration:none; font-size:14px; margin-top: 20px; display: inline-block;}
    </style>
</head>
<body>

<div class="container">
    <span class="checkmark">✅</span>
    <h1>Bedankt, <?php echo htmlspecialchars($order['klant_naam']); ?>!</h1>
    <p>Je betaling is geslaagd. We wensen je alvast veel plezier!</p>

    <div class="order-box">
        <p><strong>Ordernummer:</strong> #<?php echo $order['id']; ?></p>
        
        <a href="party_ticket_print.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn-download">
            🎟️ Download Tickets
        </a>

        <p style="font-size:13px; color:#666;">
            (De tickets zijn ook per mail verstuurd naar <em><?php echo htmlspecialchars($order['email']); ?></em>)
        </p>
    </div>
    
    <a href="/" class="btn-home">Terug naar de website</a>
</div>

<script>
    // CONFETTI KNALLER
    var count = 200;
    var defaults = { origin: { y: 0.7 } };

    function fire(particleRatio, opts) {
        confetti(Object.assign({}, defaults, opts, {
            particleCount: Math.floor(count * particleRatio)
        }));
    }

    fire(0.25, { spread: 26, startVelocity: 55, });
    fire(0.2, { spread: 60, });
    fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
    fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
    fire(0.1, { spread: 120, startVelocity: 45, });
</script>

</body>
</html>