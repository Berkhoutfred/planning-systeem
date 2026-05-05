<?php
// BESTAND: agenda_lijst.php
// VERSIE: VEILIGHEIDSUPDATE (Graafschap ID=3 verborgen voor publiek)
require_once __DIR__ . '/env.php';

// 1. INSTELLINGEN
$ontvanger_email = 'info@taxiberkhout.nl';

// 2. DATABASE VERBINDING
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', ''); 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch(PDOException $e) { die(""); }

// 3. FORMULIER VERWERKEN
$melding = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verstuur_aanvraag'])) {
    $naam = htmlspecialchars($_POST['naam']);
    $bedrijf = htmlspecialchars($_POST['bedrijf']); 
    $email = htmlspecialchars($_POST['email']);
    $tel = htmlspecialchars($_POST['tel']);
    $bericht = htmlspecialchars($_POST['bericht']); 

    $onderwerp = "Aanvraag via Agenda/Uitgaan pagina";
    
    $inhoud = "Er is een aanvraag binnengekomen via de Uitgaan-pagina:\n\n";
    $inhoud .= "Naam: $naam\n";
    $inhoud .= "Bedrijf/Groep: $bedrijf\n";
    $inhoud .= "Email: $email\n";
    $inhoud .= "Telefoon: $tel\n\n";
    $inhoud .= "Bericht:\n$bericht\n";

    $headers = "From: no-reply@berkhoutreizen.nl\r\n";
    $headers .= "Reply-To: $email\r\n";

    if(mail($ontvanger_email, $onderwerp, $inhoud, $headers)){
        $melding = "<div style='background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #bbf7d0; text-align:center;'>✅ Bedankt! Uw bericht is verstuurd. We nemen contact op.</div>";
    } else {
        $melding = "<div style='color:red; margin-bottom:15px; text-align:center;'>Er ging iets mis. Probeer het later of bel ons.</div>";
    }
}

// 4. EVENTS OPHALEN
// ---> HIER IS DE CHIRURGISCHE FIX <---
// AND id != 3 zorgt ervoor dat Graafschap College (ID 3) nooit hier verschijnt!
$sql = "SELECT * FROM party_events WHERE datum >= CURDATE() AND is_active = 1 AND id != 3 ORDER BY datum ASC";
$events = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 10px; background: transparent; }
    
    /* STIJL VOOR DE EVENEMENTEN */
    .event-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-left: 6px solid #f97316; /* Oranje rand */
        margin-bottom: 20px;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
    }
    .info h3 { margin: 0 0 5px 0; color: #1f2937; font-size: 18px; }
    .info p { margin: 0; color: #666; font-size: 14px; line-height: 1.5; }
    .info .sub-text { font-size: 13px; color: #888; font-style: italic; display: block; margin-top: 5px; }
    
    .btn {
        background: #2563eb;
        color: white;
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        white-space: nowrap;
        transition: background 0.2s;
        display: inline-block;
    }
    .btn:hover { background: #1d4ed8; }

    /* STIJL VOOR HET FORMULIER BLOK (ONDERIN) */
    .custom-block {
        background: #f9fafb;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        max-width: 700px;
        margin: 40px auto 10px auto; 
        text-align: center;
    }
    .custom-block p { 
        color: #333; 
        line-height: 1.6; 
        margin-bottom: 20px; 
        font-size: 15px; 
        font-weight: 500;
    }
    
    /* De uitklap knop */
    .toggle-btn {
        background: white;
        border: 2px solid #f97316;
        color: #f97316;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: bold;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .toggle-btn:hover { background: #f97316; color: white; }

    /* Het formulier is standaard VERBORGEN */
    #aanvraagFormulier {
        display: none;
        margin-top: 20px;
        text-align: left;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }

    .form-row { display: flex; gap: 10px; margin-bottom: 10px; }
    
    .form-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        box-sizing: border-box;
        font-family: inherit;
        font-size: 14px;
    }
    .form-btn {
        background: #f97316;
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 6px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        width: 100%;
        transition: background 0.2s;
    }
    .form-btn:hover { background: #ea580c; }

    @media (max-width: 600px) {
        .event-card { flex-direction: column; text-align: center; gap: 15px; }
        .btn { width: 100%; display: block; box-sizing: border-box; }
        .form-row { flex-direction: column; gap: 10px; }
    }
</style>
</head>
<body>

<?php if(count($events) > 0): ?>
    <?php foreach($events as $event): ?>
    <div class="event-card">
        <div class="info">
            <h3>🚍 Busticket voor: <?php echo htmlspecialchars($event['naam']); ?></h3>
            
            <p>
                📅 <?php echo date('d-m-Y', strtotime($event['datum'])); ?> <br>
                📍 Locatie: <?php echo htmlspecialchars($event['locatie']); ?>
            </p>
            
            <span class="sub-text">✅ Vervoer per luxe touringcar</span>
        </div>
        
        <a href="party_boeken.php?id=<?php echo $event['id']; ?>" class="btn" target="_top">
            Tickets Bestellen ➝
        </a>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="custom-block">
    <?php echo $melding; ?>

    <p>
        Verder hebben we op dit moment geen evenementenvervoer of dagtochten gepland.<br>
        Wilt u vervoer regelen voor uw eigen groep of evenement? Vul het formulier in en wij nemen contact op!
    </p>

    <button type="button" class="toggle-btn" onclick="toggleForm()">
        🔽 Open Aanvraagformulier
    </button>

    <div id="aanvraagFormulier">
        <form method="post">
            <div class="form-row">
                <input type="text" name="naam" placeholder="Uw naam" class="form-input" required>
                <input type="text" name="bedrijf" placeholder="Bedrijf / Groepsnaam" class="form-input">
            </div>

            <div class="form-row">
                <input type="email" name="email" placeholder="E-mailadres" class="form-input" required>
                
                <input type="tel" name="tel" placeholder="Telefoonnummer" class="form-input" required pattern="[0-9\s\-\+]{10,}" title="Vul een geldig nummer in (minimaal 10 cijfers)">
            </div>

            <div style="margin-bottom: 15px;">
                <textarea name="bericht" rows="4" placeholder="Wat wilt u gaan doen? (Bijv. aantal personen, bestemming, datum...)" class="form-input" required></textarea>
            </div>
            
            <button type="submit" name="verstuur_aanvraag" class="form-btn">
                Verstuur aanvraag ➝
            </button>
        </form>
    </div>
</div>

<script>
    function toggleForm() {
        var form = document.getElementById("aanvraagFormulier");
        var btn = document.querySelector(".toggle-btn");
        
        if (form.style.display === "none" || form.style.display === "") {
            form.style.display = "block";
            btn.innerHTML = "🔼 Sluit Formulier";
            btn.style.background = "#f97316";
            btn.style.color = "white";
        } else {
            form.style.display = "none";
            btn.innerHTML = "🔽 Open Aanvraagformulier";
            btn.style.background = "white";
            btn.style.color = "#f97316";
        }
    }
</script>

</body>
</html>