<?php
// party_toevoegen.php
// VERSIE: VOLGORDE AANGEPAST + FOOLPROOF TIJD & DATUM

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

// Haal locaties op voor de dropdown
$locaties_result = $pdo->query("SELECT naam FROM party_locaties ORDER BY naam ASC")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $naam        = $_POST['naam'];
    $locatie     = $_POST['locatie'];
    $is_retour   = (int)$_POST['type_rit'];
    $datum       = $_POST['datum'];
    
    // We plakken de uren en minuten netjes aan elkaar (bijv "23:30")
    $vertrektijd = $_POST['uur'] . ':' . $_POST['minuut']; 
    
    $prijs       = str_replace(',', '.', $_POST['prijs']); 
    $max_tickets = (int)$_POST['max_tickets'];
    
    // FOTO UPLOAD LOGICA
    $afbeelding_naam = NULL;
    if (isset($_FILES['afbeelding']) && $_FILES['afbeelding']['error'] == 0) {
        $map = 'beheer/images/'; 
        $bestandsnaam = time() . '_' . basename($_FILES['afbeelding']['name']);
        $doel_bestand = $map . $bestandsnaam;
        
        if (move_uploaded_file($_FILES['afbeelding']['tmp_name'], $doel_bestand)) {
            $afbeelding_naam = $bestandsnaam;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO party_events (naam, datum, vertrektijd, locatie, prijs, max_tickets, reis_type, is_active, afbeelding) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
    
    if ($stmt->execute([$naam, $datum, $vertrektijd, $locatie, $prijs, $max_tickets, $is_retour, $afbeelding_naam])) {
        $nieuw_id = $pdo->lastInsertId();
        // Na het opslaan gaan we direct naar de pagina om de haltes toe te voegen
        header("Location: party_haltes.php?event_id=" . $nieuw_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Nieuw Event Toevoegen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/nl.js"></script>

    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #111; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        
        label { display: block; font-weight: bold; margin-top: 15px; color: #444; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        
        .tijd-box select { width: 48%; display: inline-block; }
        
        input[type="file"] { background: #f8fafc; padding: 8px; border: 1px dashed #cbd5e1; cursor: pointer; color: #475569; width: 100%; margin-top: 5px; box-sizing: border-box;}
        
        .btn-opslaan { background: #22c55e; color: white; padding: 15px; border: none; width: 100%; margin-top: 25px; font-size: 16px; font-weight: bold; cursor: pointer; border-radius: 5px; }
        .btn-opslaan:hover { background: #16a34a; }
        
        .terug-link { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <h2>➕ Nieuw Event Toevoegen</h2>

    <form method="post" enctype="multipart/form-data">
        
        <label>1. Naam Evenement</label>
        <input type="text" name="naam" required placeholder="Bijv. Carnaval City Lido">

        <label>2. Locatie Evenement</label>
        <select name="locatie" required>
            <option value="">-- Kies een opgeslagen locatie --</option>
            <?php 
            foreach($locaties_result as $rij) {
                echo '<option value="' . htmlspecialchars($rij['naam']) . '">' . htmlspecialchars($rij['naam']) . '</option>';
            }
            ?>
            <option value="Anders">Anders (Zonder vast adres)</option>
        </select>

        <label>3. 📸 Event Afbeelding (Optioneel)</label>
        <input type="file" name="afbeelding" accept="image/*">

        <label>4. Soort Rit</label>
        <select name="type_rit">
            <option value="1">Retour (Heen & Terug)</option>
            <option value="0">Alleen Enkele Reis (Heen)</option>
        </select>

        <div style="display:flex; gap:15px;">
            <div style="flex:1;">
                <label>5. Datum Feest</label>
                <input type="text" id="mooie_datum" name="datum" required placeholder="Kies een datum...">
            </div>
            <div style="flex:1;">
                <label>6. Eindtijd (Retour)</label>
                <div class="tijd-box">
                    <select name="uur" required>
                        <?php for($i=0; $i<=23; $i++) { $u=str_pad($i,2,'0',STR_PAD_LEFT); echo "<option value='$u'>$u</option>"; } ?>
                    </select>
                    <b>:</b>
                    <select name="minuut" required>
                        <option value="00">00</option>
                        <option value="15">15</option>
                        <option value="30">30</option>
                        <option value="45">45</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:15px;">
            <div style="flex:1;">
                <label>7. Prijs per ticket (€)</label>
                <input type="number" step="0.01" name="prijs" required placeholder="12.50">
            </div>
            <div style="flex:1;">
                <label>8. Aantal Beschikbare Stoelen</label>
                <input type="number" name="max_tickets" value="50" required>
            </div>
        </div>

        <button type="submit" class="btn-opslaan">💾 Opslaan & Door naar Haltes toevoegen ➔</button>
        <a href="party_beheer.php" class="terug-link">Annuleren</a>
    </form>
</div>

<script>
    flatpickr("#mooie_datum", {
        locale: "nl",
        altInput: true,
        altFormat: "l j F Y", // Dit ziet de gebruiker: Zaterdag 21 februari 2026
        dateFormat: "Y-m-d",   // Dit gaat naar de database: 2026-02-21
        minDate: "today"
    });
</script>

</body>
</html>