<?php
// Bestand: beheer/diesel_verzenden.php
// VERSIE: 1.3 - Met slimme contactpersonen dropdown!

include '../beveiliging.php';
require 'includes/db.php';

// --- 1. SJABLONEN OPHALEN ---
$sjablonen = $pdo->query("SELECT * FROM mail_sjablonen ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 2. GESELECTEERDE KLANTEN & CONTACTPERSONEN OPHALEN ---
$geselecteerde_klanten = [];
$contactpersonen_per_klant = []; // Hier bewaren we de contacten in

if (isset($_POST['klant_ids']) && is_array($_POST['klant_ids'])) {
    $in_lijst = str_repeat('?,', count($_POST['klant_ids']) - 1) . '?';
    
    // Klanten ophalen
    $stmt = $pdo->prepare("SELECT id, bedrijfsnaam, voornaam, achternaam, email FROM klanten WHERE id IN ($in_lijst)");
    $stmt->execute($_POST['klant_ids']);
    $geselecteerde_klanten = $stmt->fetchAll();

    // Contactpersonen ophalen voor deze klanten
    $stmt_contacten = $pdo->prepare("SELECT klant_id, naam, email FROM klant_contactpersonen WHERE klant_id IN ($in_lijst)");
    $stmt_contacten->execute($_POST['klant_ids']);
    $alle_contacten = $stmt_contacten->fetchAll();

    // Netjes sorteren per klant, zodat we ze straks makkelijk in de dropdown kunnen zetten
    foreach ($alle_contacten as $contact) {
        $contactpersonen_per_klant[$contact['klant_id']][] = $contact;
    }
}

include 'includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1200px; margin: auto; padding: 20px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #003366; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; box-sizing: border-box; }
    textarea.form-control { min-height: 250px; resize: vertical; line-height: 1.5; }
    
    .klanten-lijst { border: 1px solid #eee; border-radius: 6px; overflow: hidden; }
    .klant-rij { display: flex; align-items: flex-start; padding: 15px; border-bottom: 1px solid #eee; background: #fafafa; gap: 15px; flex-wrap: wrap; flex-direction: column; }
    .klant-rij:last-child { border-bottom: none; }
    
    .klant-velden { display: flex; gap: 15px; width: 100%; }
    
    .btn-verzend { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; display: block; width: 100%; transition: 0.2s; }
    .btn-verzend:hover { background: #218838; }
</style>

<div class="container">
    <h1 style="color:#003366; margin-bottom:20px;">Dieseltoeslag Verzenden</h1>

    <?php if (empty($geselecteerde_klanten)): ?>
        <div class="card" style="border-left: 4px solid #ffc107;">
            <h3>Geen klanten geselecteerd</h3>
            <p>Je hebt nog geen klanten aangevinkt. Ga terug naar het klantenoverzicht om klanten te selecteren.</p>
            <a href="klanten.php" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#003366; color:white; text-decoration:none; border-radius:4px;">Terug naar Klanten</a>
        </div>
    <?php else: ?>
        <form action="diesel_verstuur_actie.php" method="POST">
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div class="card" style="flex: 1; min-width: 400px;">
                    <h3 style="margin-top:0; border-bottom: 2px solid #eee; padding-bottom: 10px;">1. Bepaal de inhoud</h3>
                    
                    <div class="form-group">
                        <label>Kies een Sjabloon:</label>
                        <select id="sjabloon_kieser" class="form-control" onchange="vulSjabloonIn()">
                            <option value="">-- Kies een standaard tekst --</option>
                            <?php foreach($sjablonen as $sjabloon): ?>
                                <option value="<?= $sjabloon['id'] ?>"><?= htmlspecialchars($sjabloon['titel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Onderwerp van de e-mail:</label>
                        <input type="text" id="mail_onderwerp" name="onderwerp" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Bericht:</label>
                        <textarea id="mail_bericht" name="bericht" class="form-control" required></textarea>
                    </div>
                </div>

                <div class="card" style="flex: 2; min-width: 500px;">
                    <h3 style="margin-top:0; border-bottom: 2px solid #eee; padding-bottom: 10px;">2. Controleer ontvangers</h3>
                    
                    <div class="klanten-lijst">
                        <?php foreach($geselecteerde_klanten as $klant): 
                            $klant_id = $klant['id'];
                            $weergave_naam = !empty($klant['bedrijfsnaam']) ? $klant['bedrijfsnaam'] : trim($klant['voornaam'] . ' ' . $klant['achternaam']);
                            
                            // Check of we contactpersonen hebben gevonden voor deze specifieke klant
                            $heeft_contacten = isset($contactpersonen_per_klant[$klant_id]) && count($contactpersonen_per_klant[$klant_id]) > 0;
                        ?>
                            <div class="klant-rij">
                                <div style="font-weight: bold; color: #003366; font-size: 16px;">
                                    <?= htmlspecialchars($weergave_naam) ?>
                                    <input type="hidden" name="klant_id[]" value="<?= $klant_id ?>">
                                </div>
                                
                                <?php if ($heeft_contacten): ?>
                                    <div style="background: #e8f5e9; padding: 10px; border-radius: 4px; width: 100%; box-sizing: border-box; border: 1px solid #c8e6c9;">
                                        <small style="color:#2e7d32; font-weight:bold;">Contactpersoon kiezen:</small>
                                        <select class="form-control contact-dropdown" data-klantid="<?= $klant_id ?>" style="padding: 6px; margin-top: 5px; border-color: #a5d6a7;">
                                            <option value="" data-naam="<?= htmlspecialchars($weergave_naam) ?>" data-email="<?= htmlspecialchars($klant['email']) ?>">-- Standaard Klantgegevens --</option>
                                            
                                            <?php foreach ($contactpersonen_per_klant[$klant_id] as $cp): ?>
                                                <option value="cp" data-naam="<?= htmlspecialchars($cp['naam']) ?>" data-email="<?= htmlspecialchars($cp['email']) ?>">
                                                    <?= htmlspecialchars($cp['naam']) ?> (<?= htmlspecialchars($cp['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                            
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="klant-velden">
                                    <div style="flex: 1;">
                                        <small style="color:#666; font-weight:bold;">Vervang [NAAM] door:</small>
                                        <input type="text" id="naam_veld_<?= $klant_id ?>" name="klant_contact_naam[<?= $klant_id ?>]" value="<?= htmlspecialchars($weergave_naam) ?>" class="form-control" style="padding: 8px;" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <small style="color:#666; font-weight:bold;">E-mailadres:</small>
                                        <input type="email" id="email_veld_<?= $klant_id ?>" name="klant_email[<?= $klant_id ?>]" value="<?= htmlspecialchars($klant['email']) ?>" class="form-control" style="padding: 8px;" required>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <br>
                    <button type="submit" class="btn-verzend">Verstuur <?= count($geselecteerde_klanten) ?> e-mails</button>
                </div>
            </div>

        </form>
    <?php endif; ?>
</div>

<script>
    // --- SJABLOON LOGICA ---
    const sjablonenData = {
        <?php foreach($sjablonen as $s): ?>
        "<?= $s['id'] ?>": {
            "onderwerp": <?= json_encode($s['onderwerp']) ?>,
            "bericht": <?= json_encode($s['bericht']) ?>
        },
        <?php endforeach; ?>
    };

    function vulSjabloonIn() {
        const keuzelijst = document.getElementById('sjabloon_kieser');
        const gekozenId = keuzelijst.value;
        const vakOnderwerp = document.getElementById('mail_onderwerp');
        const vakBericht = document.getElementById('mail_bericht');

        if (gekozenId && sjablonenData[gekozenId]) {
            vakOnderwerp.value = sjablonenData[gekozenId].onderwerp;
            vakBericht.value = sjablonenData[gekozenId].bericht;
        } else {
            vakOnderwerp.value = '';
            vakBericht.value = '';
        }
    }

    // --- DROPDOWN LOGICA (Vul vakjes in bij het kiezen van een contactpersoon) ---
    document.querySelectorAll('.contact-dropdown').forEach(function(dropdown) {
        dropdown.addEventListener('change', function() {
            // Haal de gekozen optie op
            var gekozenOptie = this.options[this.selectedIndex];
            
            // Lees de data uit de optie
            var nieuweNaam = gekozenOptie.getAttribute('data-naam');
            var nieuweEmail = gekozenOptie.getAttribute('data-email');
            var klantId = this.getAttribute('data-klantid');
            
            // Vul de normale tekstvakjes in met de nieuwe gegevens
            document.getElementById('naam_veld_' + klantId).value = nieuweNaam;
            document.getElementById('email_veld_' + klantId).value = nieuweEmail;
        });
    });
</script>

<?php include 'includes/footer.php'; ?>