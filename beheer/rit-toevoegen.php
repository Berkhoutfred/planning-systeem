<?php
// Bestand: beheer/rit-toevoegen.php
// VERSIE: Kantoor-formulier voor het handmatig toevoegen van ritten (Inclusief Telegram Ping)

include '../beveiliging.php';
require 'includes/db.php';

// Formulier is verzonden
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $datum        = $_POST['datum'];
    $chauffeur    = $_POST['chauffeur'];
    $voertuig     = $_POST['voertuig'];
    $soort_rit    = $_POST['soort_rit'];
    $klant        = trim($_POST['klant']);
    $route        = trim($_POST['route']);
    $prijs        = str_replace(',', '.', $_POST['prijs']); 
    $betaalwijze  = $_POST['betaalwijze'];
    
    // Betaalwijze achter de route plakken voor de weergave in de inbox
    $complete_route = $route . " (Betaling: " . $betaalwijze . ")";

    $stmt = $pdo->prepare("
        INSERT INTO ritgegevens 
        (datum, chauffeur_naam, type_dienst, voertuig_nummer, status, bron_type, adhoc_klant, adhoc_route, adhoc_prijs) 
        VALUES (?, ?, ?, ?, 'nieuw', 'adhoc', ?, ?, ?)
    ");
    
    $stmt->execute([
        $datum, 
        $chauffeur, 
        $soort_rit, 
        $voertuig, 
        $klant, 
        $complete_route, 
        $prijs
    ]);
    
    // ==========================================
    // TELEGRAM PING VERSTUREN
    // ==========================================
    // 1. Zoek het ID van de chauffeur op basis van de ingevulde naam
    $stmt_chauf = $pdo->prepare("SELECT id FROM chauffeurs WHERE CONCAT(voornaam, ' ', achternaam) = ? LIMIT 1");
    $stmt_chauf->execute([$chauffeur]);
    $chauf_data = $stmt_chauf->fetch();

    if ($chauf_data) {
        $chauffeur_id = $chauf_data['id'];
        
        // 2. Bouw een strak opgemaakt bericht
        $bericht = "🚨 <b>Nieuwe Rit Toegewezen! (Kantoor)</b>\n\n";
        $bericht .= "<b>Datum:</b> " . date('d-m-Y', strtotime($datum)) . "\n";
        $bericht .= "<b>Soort:</b> " . $soort_rit . "\n";
        $bericht .= "<b>Klant:</b> " . $klant . "\n";
        $bericht .= "<b>Route:</b> " . $route . "\n";
        $bericht .= "<b>Bus:</b> " . $voertuig . "\n\n";
        $bericht .= "<i>Open je dashboard in de app voor meer details.</i>";

        // 3. Haal het gereedschapje erbij en stuur het bericht
        require_once 'includes/telegram_functies.php';
        stuurTelegramMelding($pdo, $chauffeur_id, $bericht);
    }
    // ==========================================
    
    // Terugsturen naar de inbox
    header("Location: ritten.php");
    exit;
}

// Haal actieve voertuigen en chauffeurs op voor de dropdowns
$bussen = $pdo->query("SELECT voertuig_nummer, naam FROM voertuigen ORDER BY voertuig_nummer ASC")->fetchAll();
$chauffeurs = $pdo->query("SELECT voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam ASC")->fetchAll();

include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-top: 5px solid #0056b3;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #003366;">➕ Rit Invoeren (Kantoor)</h2>
        <a href="ritten.php" style="color: #666; text-decoration: none; font-weight: bold;">❮ Annuleren</a>
    </div>

    <form method="POST">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Datum</label>
                <input type="date" name="datum" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
            </div>
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Chauffeur</label>
                <select name="chauffeur" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                    <option value="">-- Kies chauffeur --</option>
                    <?php foreach($chauffeurs as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?>">
                            <?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Voertuig</label>
                <select name="voertuig" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                    <option value="">-- Kies bus --</option>
                    <?php foreach($bussen as $bus): ?>
                        <option value="<?php echo htmlspecialchars($bus['voertuig_nummer']); ?>">
                            Bus <?php echo htmlspecialchars($bus['voertuig_nummer'] . ' - ' . $bus['naam']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Soort Rit</label>
                <select name="soort_rit" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                    <option value="Straattaxi">Straattaxi</option>
                    <option value="Treinstremming NS">Treinstremming NS</option>
                    <option value="Dagbesteding">Dagbesteding</option>
                    <option value="Anders (Extra Rit)">Anders (Extra Rit)</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Klantnaam / Opdrachtgever</label>
            <input type="text" name="klant" placeholder="Bijv. Dhr. Jansen of NS" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; box-sizing:border-box;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Route (Van - Naar)</label>
            <input type="text" name="route" placeholder="Bijv. Station Zutphen - Arnhem" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; box-sizing:border-box;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px; color:#d9534f;">Bedrag (€)</label>
                <input type="text" name="prijs" placeholder="0.00" required style="width:100%; padding:10px; border:2px solid #d9534f; border-radius:5px; font-weight:bold;">
            </div>
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Betaalwijze</label>
                <select name="betaalwijze" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                    <option value="Contant">Contant</option>
                    <option value="PIN">PIN</option>
                    <option value="Op Rekening">Op Rekening</option>
                </select>
            </div>
        </div>

        <button type="submit" style="background:#0056b3; color:white; border:none; padding:15px; width:100%; border-radius:5px; font-size:16px; font-weight:bold; cursor:pointer;">
            💾 Rit Opslaan & Naar Inbox
        </button>

    </form>
</div>

<?php include 'includes/footer.php'; ?>