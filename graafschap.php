<?php
die("<!DOCTYPE html><html lang='nl'><head><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Gesloten</title></head><body style='font-family:sans-serif; text-align:center; padding:50px 20px; background:#f4f7f6;'><div style='background:white; max-width:500px; margin:0 auto; padding:30px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.1);'><h2 style='color:#dc2626; margin-top:0;'>Ticketverkoop Gesloten 🛑</h2><p style='color:#4b5563;'>De online ticketverkoop voor de retourritten van vannacht is definitief gesloten. De bussen vertrekken om 02:15 uur.</p></div></body></html>");
// graafschap.php - Speciaal boekingsformulier voor Graafschap College
// VERSIE: ROUTES (A=19, B=60, C=19) - Onzichtbare telling + Ongebruikte haltes verborgen!
require_once __DIR__ . '/env.php';

// Database verbinding
$servername = env_value('LEGACY_DB_HOST', '127.0.0.1');
$username   = env_value('LEGACY_DB_USER', '');
$password   = env_value('LEGACY_DB_PASS', '');
$dbname     = env_value('LEGACY_DB_NAME', '');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Verbinding mislukt: " . $conn->connect_error); }

$event_id = 3;

// --- DE VIP DEUR BEVEILIGING (Koppeling met dashboard Aan/Uit) ---
$sql_status = "SELECT is_active FROM party_events WHERE id = ?";
$stmt_status = $conn->prepare($sql_status);
$stmt_status->bind_param("i", $event_id);
$stmt_status->execute();
$status_result = $stmt_status->get_result();
$event_data = $status_result->fetch_assoc();

if (!$event_data || $event_data['is_active'] == 0) {
    die("<!DOCTYPE html><html lang='nl'><head><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Gesloten</title></head><body style='font-family:sans-serif; text-align:center; padding:50px 20px; background:#f4f7f6;'><div style='background:white; max-width:500px; margin:0 auto; padding:30px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.1);'><h2 style='color:#dc2626; margin-top:0;'>Ticketverkoop Gesloten 🛑</h2><p style='color:#4b5563;'>De online ticketverkoop voor de Graafschap College retourritten is op dit moment gesloten.</p><p style='font-size:14px; color:#6b7280;'>Heb je vragen over je ticket? Neem dan contact op met de organisatie.</p></div></body></html>");
}
// -------------------------------------------------------------

// =============================================================
// DE VIRTUELE ROUTE CONTROLLER (STIL OP DE ACHTERGROND)
// =============================================================

// 1. We definiëren de bussen en de zoekwoorden voor de haltes
$routes = [
    'Route A' => [
        'max' => 19,
        'zoekwoorden' => ['Ulft', 'Gendringen', 'heerenberg', 'Zevenaar', 'Drempt'] 
    ],
    'Route B' => [
        'max' => 60, 
        'zoekwoorden' => ['Aalten', 'Winterswijk', 'Groenlo', 'Beltrum', 'Eibergen', 'Haaksbergen', 'Neede']
    ],
    'Route C' => [
        'max' => 19, 
        'zoekwoorden' => ['Gaanderen', 'Doetinchem', 'Vorden']
    ]
];

// 2. We beginnen met tellen op Nul
$verkocht = ['Route A' => 0, 'Route B' => 0, 'Route C' => 0];

// 3. Haal alle verkochte tickets op voor dit evenement
$sql_tickets = "SELECT t.bestemming FROM tickets t JOIN orders o ON t.order_id = o.id WHERE o.event_id = ?";
$stmt_tickets = $conn->prepare($sql_tickets);
$stmt_tickets->bind_param("i", $event_id);
$stmt_tickets->execute();
$tickets_result = $stmt_tickets->get_result();

while($t = $tickets_result->fetch_assoc()) {
    $bestemming = strtolower($t['bestemming']);
    
    // Kijk in welke route dit ticket valt, en tel er 1 bij op!
    foreach($routes as $route_naam => $route_data) {
        foreach($route_data['zoekwoorden'] as $zoekwoord) {
            if (strpos($bestemming, strtolower($zoekwoord)) !== false) {
                $verkocht[$route_naam]++;
                break 2; 
            }
        }
    }
}
// =============================================================

// Haal de haltes op voor de dropdown
$sql = "SELECT id, naam, tijd, prijs FROM party_opstap_locaties WHERE event_id = ? ORDER BY naam ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$haltes_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Ticketverkoop Graafschap College - Berkhout Reizen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 650px; margin: 0 auto; background: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .logo-balk { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .logo-balk img { max-height: 90px; max-width: 48%; object-fit: contain; }
        h2 { margin-top: 0; color: #111; font-size: 22px; text-align: center; margin-bottom: 15px; }
        .info-box { background: #f8fafc; border-left: 4px solid #2563eb; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; }
        .info-box p { margin: 5px 0; font-size: 14px; color: #444; }
        label { display: block; font-weight: 600; margin-top: 15px; color: #222; font-size: 13px; }
        input, select { width: 100%; padding: 10px; margin-top: 4px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border-color 0.3s; }
        input:focus, select:focus { border-color: #2563eb; outline: none; }
        .hint { font-size: 11px; color: #666; margin-top: 4px; display: block; }
        .btn-kopen { background: #2563eb; color: white; padding: 14px; border: none; width: 100%; margin-top: 25px; font-size: 16px; font-weight: bold; cursor: pointer; border-radius: 6px; transition: background 0.3s, transform 0.1s; }
        .btn-kopen:hover { background: #1d4ed8; transform: translateY(-1px); }
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }
        @media(max-width: 600px) { .row { flex-direction: column; gap: 0; } .logo-balk { flex-direction: column; gap: 15px; } }
    </style>
</head>
<body>

<div class="container">
    <div class="logo-balk">
        <img src="beheer/images/Logo_Graafschap_College_1.png" alt="Graafschap College">
        <img src="beheer/images/berkhout_logo.png" alt="Berkhout Reizen">
    </div>

    <h2>Busticket (Retour) Graafschap Feest</h2>

    <div class="info-box">
        <p>🕒 <strong>Vertrek retourrit:</strong> Nacht van 2 op 3 april om 02:15 uur</p>
        <p>💶 <strong>Prijs per ticket:</strong> € 10,00</p>
        <p>🚌 <strong>Vervoer:</strong> Touringcar / Taxi Berkhout</p>
    </div>

    <form action="graafschap_verwerk.php" method="POST">
        <input type="hidden" name="event_id" value="3">
        
        <div class="row">
            <div class="col"><label>Voornaam *</label><input type="text" name="voornaam" required></div>
            <div class="col"><label>Achternaam *</label><input type="text" name="achternaam" required></div>
        </div>

        <div class="row">
            <div class="col"><label>School E-mailadres *</label><input type="email" name="email" required pattern=".*@student\.graafschapcollege\.nl$" title="Gebruik je @student.graafschapcollege.nl e-mailadres"></div>
            <div class="col"><label>Mobiel Telefoonnummer *</label><input type="tel" name="telefoon" required pattern="06[0-9]{8}" minlength="10" maxlength="10" title="Vul een geldig 10-cijferig 06-nummer in"></div>
        </div>

        <label>Kies je eindbestemming / halte *</label>
        <select name="opstap_id" required>
            <option value="" disabled selected>Selecteer je halte uit de lijst...</option>
            <?php
            if ($haltes_result->num_rows > 0) {
                while($row = $haltes_result->fetch_assoc()) {
                    $halte_naam = strtolower($row['naam']);
                    $mijn_route = null;
                    
                    // Bij welke route hoort deze halte?
                    foreach($routes as $route_naam => $route_data) {
                        foreach($route_data['zoekwoorden'] as $zoekwoord) {
                            if (strpos($halte_naam, strtolower($zoekwoord)) !== false) {
                                $mijn_route = $route_naam;
                                break 2;
                            }
                        }
                    }

                    // FILTER: Als de halte NIET in een van de 3 routes zit, sla hem dan over!
                    if (!$mijn_route) {
                        continue; 
                    }

                    // Als de halte WEL in de route zit, checken we stilletjes de capaciteit
                    $plekken_over = $routes[$mijn_route]['max'] - $verkocht[$mijn_route];
                    
                    if ($plekken_over <= 0) {
                        // UITVERKOCHT! Maak de optie onklikbaar (rood woordje erachter)
                        echo '<option value="" disabled style="color:red;">' . htmlspecialchars($row['naam']) . ' (UITVERKOCHT)</option>';
                    } else {
                        // NOG PLEK! Toon de halte gewoon mooi schoon, ZONDER de aantallen.
                        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['naam']) . '</option>';
                    }
                }
            }
            ?>
        </select>

        <label style="font-weight: normal; margin-top: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px;">
            <input type="checkbox" required style="width: auto; margin: 0;">
            Ik ga ermee akkoord dat Taxi Berkhout mijn gegevens veilig opslaat voor deze rit.
        </label>

        <button type="submit" class="btn-kopen">Afrekenen via iDEAL (€ 10,00)</button>
    </form>
</div>

</body>
</html>