<?php
// Bestand: beheer/vaste_ritten/toevoegen.php
// VERSIE: A/B Planning (Periode vs Specifieke Datums) met JS Toggles

include '../../beveiliging.php';
require '../includes/db.php';
include '../includes/header.php';

$melding = '';

// --- FORMULIER OPSLAAN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'opslaan') {
    $klant_id = !empty($_POST['klant_id']) ? (int)$_POST['klant_id'] : NULL;
    $prijs = !empty($_POST['prijs']) ? (float)str_replace(',', '.', $_POST['prijs']) : 0.00;
    $naam = trim($_POST['naam']);
    
    // De nieuwe A/B keuze opvangen
    $type_planning = $_POST['type_planning']; // 'periode' of 'datums'
    
    // Logica: Afhankelijk van de keuze slaan we velden over
    $startdatum = ($type_planning === 'periode' && !empty($_POST['startdatum'])) ? $_POST['startdatum'] : NULL;
    $einddatum = ($type_planning === 'periode' && !empty($_POST['einddatum'])) ? $_POST['einddatum'] : NULL;
    $specifieke_datums = ($type_planning === 'datums') ? trim($_POST['specifieke_datums']) : NULL;
    
    $ma = ($type_planning === 'periode' && isset($_POST['rijdt_ma'])) ? 1 : 0;
    $di = ($type_planning === 'periode' && isset($_POST['rijdt_di'])) ? 1 : 0;
    $wo = ($type_planning === 'periode' && isset($_POST['rijdt_wo'])) ? 1 : 0;
    $do = ($type_planning === 'periode' && isset($_POST['rijdt_do'])) ? 1 : 0;
    $vr = ($type_planning === 'periode' && isset($_POST['rijdt_vr'])) ? 1 : 0;
    $za = ($type_planning === 'periode' && isset($_POST['rijdt_za'])) ? 1 : 0;
    $zo = ($type_planning === 'periode' && isset($_POST['rijdt_zo'])) ? 1 : 0;
    
    $uitzonderingen = ($type_planning === 'periode') ? trim($_POST['uitzondering_datums']) : NULL;

    // Deze gelden voor beide opties
    $vertrektijd = $_POST['vertrektijd'];
    $aankomsttijd = $_POST['aankomsttijd'];
    $heeft_retour = isset($_POST['heeft_retour']) ? 1 : 0;
    $retour_vertrektijd = ($heeft_retour && !empty($_POST['retour_vertrektijd'])) ? $_POST['retour_vertrektijd'] : NULL;

    $ophaaladres = trim($_POST['ophaaladres']);
    $bestemming = trim($_POST['bestemming']);
    $voertuig_id = !empty($_POST['voertuig_id']) ? (int)$_POST['voertuig_id'] : NULL;
    $chauffeur_id = !empty($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : NULL;
    $notities = trim($_POST['notities']);

    try {
        $stmt = $pdo->prepare("INSERT INTO vaste_ritten 
            (klant_id, prijs, type_planning, naam, startdatum, einddatum, specifieke_datums, vertrektijd, aankomsttijd, retour_vertrektijd, ophaaladres, bestemming, voertuig_id, chauffeur_id, rijdt_ma, rijdt_di, rijdt_wo, rijdt_do, rijdt_vr, rijdt_za, rijdt_zo, uitzondering_datums, notities) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([$klant_id, $prijs, $type_planning, $naam, $startdatum, $einddatum, $specifieke_datums, $vertrektijd, $aankomsttijd, $retour_vertrektijd, $ophaaladres, $bestemming, $voertuig_id, $chauffeur_id, $ma, $di, $wo, $do, $vr, $za, $zo, $uitzonderingen, $notities]);
        
        echo "<script>window.location.href='overzicht.php';</script>";
        exit;
    } catch (PDOException $e) {
        $melding = "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 15px; font-size:14px;'>Fout bij opslaan: " . $e->getMessage() . "</div>";
    }
}

// --- DATA OPHALEN VOOR KEUZEMENU'S ---
$klanten = $pdo->query("SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten ORDER BY bedrijfsnaam ASC, achternaam ASC")->fetchAll();
$chauffeurs = $pdo->query("SELECT id, voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam ASC")->fetchAll();
$voertuigen = $pdo->query("SELECT id, naam, kenteken FROM voertuigen WHERE archief = 0 ORDER BY naam ASC")->fetchAll();
?>

<style>
    .compact-form { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .grid-compact { display: grid; gap: 15px; margin-bottom: 15px; }
    .col-6 { grid-template-columns: repeat(6, 1fr); }
    .span-1 { grid-column: span 1; }
    .span-2 { grid-column: span 2; }
    .span-3 { grid-column: span 3; }
    .span-4 { grid-column: span 4; }
    .span-6 { grid-column: span 6; }
    
    .form-group { margin-bottom: 0; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #444; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-control { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box; background-color: #fdfdfd; transition: border-color 0.15s ease-in-out; }
    .form-control:focus { border-color: #80bdff; outline: none; background-color: #fff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
    
    .dagen-balk { display: flex; flex-wrap: wrap; gap: 15px; background: #f8f9fa; padding: 10px 15px; border-radius: 4px; border: 1px solid #e9ecef; }
    .dagen-balk label { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; cursor: pointer; margin: 0; }
    
    .keuze-toggle-box { display: flex; gap: 30px; background: #e9ecef; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #dde2e6; }
    .keuze-toggle-box label { font-size: 15px; font-weight: bold; color: #003366; display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none; }
    .keuze-toggle-box input[type="radio"] { transform: scale(1.3); cursor: pointer; margin:0; }

    .btn-opslaan { background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; margin-top: 15px; transition: background-color 0.2s; }
    .btn-opslaan:hover { background: #218838; }
    
    hr { border: 0; border-top: 1px solid #e9ecef; margin: 20px 0; }
</style>

<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="color: #003366; margin: 0; font-size: 22px;"><i class="fas fa-route"></i> Nieuwe Vaste Rit (Sjabloon)</h2>
        <a href="overzicht.php" style="color: #6c757d; text-decoration: none; font-weight: bold; font-size: 14px;"><i class="fas fa-arrow-left"></i> Annuleren & Terug</a>
    </div>

    <div class="compact-form">
        <?php echo $melding; ?>

        <form method="POST">
            <input type="hidden" name="actie" value="opslaan">

            <div class="grid-compact col-6">
                <div class="form-group span-2">
                    <label>Klant (Voor Facturatie) *</label>
                    <select name="klant_id" class="form-control" required>
                        <option value="">- Selecteer -</option>
                        <?php foreach($klanten as $k): 
                            $kNaam = !empty($k['bedrijfsnaam']) ? $k['bedrijfsnaam'] : $k['voornaam'].' '.$k['achternaam'];
                        ?>
                            <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($kNaam); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group span-1">
                    <label>Prijs (&euro;) *</label>
                    <input type="number" step="0.01" name="prijs" required placeholder="0.00" class="form-control">
                </div>
                <div class="form-group span-3">
                    <label>Naam / Contract *</label>
                    <input type="text" name="naam" required placeholder="Bijv. Dagbesteding Radeland" class="form-control">
                </div>

                <div class="form-group span-3">
                    <label>Ophaaladres *</label>
                    <input type="text" name="ophaaladres" id="ophaaladres" required placeholder="Typ om te zoeken via Google Maps..." class="form-control">
                </div>
                <div class="form-group span-3">
                    <label>Bestemming *</label>
                    <input type="text" name="bestemming" id="bestemming" required placeholder="Typ om te zoeken via Google Maps..." class="form-control">
                </div>

                <div class="form-group span-3">
                    <label>Standaard Voertuig</label>
                    <select name="voertuig_id" class="form-control">
                        <option value="">- Kies later op planbord -</option>
                        <?php foreach($voertuigen as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['naam'] . ' (' . $v['kenteken'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group span-3">
                    <label>Standaard Chauffeur</label>
                    <select name="chauffeur_id" class="form-control">
                        <option value="">- Kies later op planbord -</option>
                        <?php foreach($chauffeurs as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>

            <div class="keuze-toggle-box">
                <label>
                    <input type="radio" name="type_planning" value="periode" checked onchange="togglePlanning()"> 
                    <i class="far fa-calendar-alt"></i> Vaste Periode & Dagen
                </label>
                <label>
                    <input type="radio" name="type_planning" value="datums" onchange="togglePlanning()"> 
                    <i class="fas fa-list-ul"></i> Reeks Losse Datums
                </label>
            </div>

            <div class="grid-compact col-6" style="background:#f1f8ff; padding:15px; border-radius:6px; border:1px solid #cce5ff; margin-bottom:15px;">
                <div class="form-group span-1">
                    <label>Vertrek *</label>
                    <input type="time" name="vertrektijd" required class="form-control">
                </div>
                <div class="form-group span-1">
                    <label>Aankomst *</label>
                    <input type="time" name="aankomsttijd" required class="form-control">
                </div>
                <div class="form-group span-2" style="padding: 5px 10px; display: flex; align-items: center; gap: 15px;">
                    <label style="margin:0; font-size:14px; cursor:pointer; display:flex; align-items:center; gap:6px; color:#0056b3; font-weight: bold; text-transform: none;">
                        <input type="checkbox" name="heeft_retour" id="retourCheck" onchange="toggleRetour()" style="margin:0; transform: scale(1.2);"> 
                        <i class="fas fa-exchange-alt"></i> Retourrit?
                    </label>
                    <input type="time" name="retour_vertrektijd" id="retourTijd" class="form-control" style="display:none; width: 100px; padding: 6px;" title="Vertrektijd Retour">
                </div>
            </div>

            <div id="blok_periode">
                <div class="grid-compact col-6">
                    <div class="form-group span-2">
                        <label>Startdatum Periode *</label>
                        <input type="date" name="startdatum" id="startdatum" required class="form-control">
                    </div>
                    <div class="form-group span-2">
                        <label>Einddatum Periode *</label>
                        <input type="date" name="einddatum" id="einddatum" required class="form-control">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Op welke dagen binnen deze periode? *</label>
                    <div class="dagen-balk">
                        <label><input type="checkbox" name="rijdt_ma" value="1"> Ma</label>
                        <label><input type="checkbox" name="rijdt_di" value="1"> Di</label>
                        <label><input type="checkbox" name="rijdt_wo" value="1"> Wo</label>
                        <label><input type="checkbox" name="rijdt_do" value="1"> Do</label>
                        <label><input type="checkbox" name="rijdt_vr" value="1"> Vr</label>
                        <label><input type="checkbox" name="rijdt_za" value="1"> Za</label>
                        <label><input type="checkbox" name="rijdt_zo" value="1"> Zo</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Uitzonderingsdatums (Binnen deze periode NIET rijden op)</label>
                    <input type="text" name="uitzondering_datums" class="form-control" placeholder="Bijv: 06-04-2026, 27-04-2026">
                </div>
            </div>

            <div id="blok_datums" style="display: none; background: #fffdf5; padding: 15px; border-radius: 6px; border: 1px solid #ffeeba;">
                <div class="form-group">
                    <label>Vul specifieke datums in (gescheiden door een komma) *</label>
                    <input type="text" name="specifieke_datums" id="specifieke_datums" class="form-control" placeholder="Bijv: 12-04-2026, 19-04-2026, 03-05-2026">
                    <small style="color: #856404; font-size:12px; margin-top:5px; display:block;"><i class="fas fa-info-circle"></i> Alleen op de hierboven ingevulde datums zal het systeem een rit genereren.</small>
                </div>
            </div>

            <hr>

            <div class="form-group">
                <label>Interne Notities (Zichtbaar op planbord)</label>
                <input type="text" name="notities" class="form-control" placeholder="Opmerkingen voor het planbord...">
            </div>

            <button type="submit" class="btn-opslaan"><i class="fas fa-save"></i> Sjabloon Opslaan & Genereren</button>
        </form>
    </div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(env_value('GOOGLE_MAPS_API_KEY', '')); ?>&libraries=places"></script>
<script>
    function initAutocomplete() {
        var ophaalInput = document.getElementById('ophaaladres');
        var bestInput = document.getElementById('bestemming');
        
        var options = {
            componentRestrictions: { country: "nl" } 
        };

        new google.maps.places.Autocomplete(ophaalInput, options);
        new google.maps.places.Autocomplete(bestInput, options);
    }
    
    google.maps.event.addDomListener(window, 'load', initAutocomplete);

    function toggleRetour() {
        var check = document.getElementById('retourCheck');
        var tijdInput = document.getElementById('retourTijd');
        
        if(check.checked) {
            tijdInput.style.display = 'block';
            tijdInput.required = true;
        } else {
            tijdInput.style.display = 'none';
            tijdInput.required = false;
            tijdInput.value = '';
        }
    }

    function togglePlanning() {
        var type = document.querySelector('input[name="type_planning"]:checked').value;
        var blokPeriode = document.getElementById('blok_periode');
        var blokDatums = document.getElementById('blok_datums');
        var start = document.getElementById('startdatum');
        var eind = document.getElementById('einddatum');
        var spec = document.getElementById('specifieke_datums');

        if (type === 'periode') {
            // Laat periode zien
            blokPeriode.style.display = 'block';
            blokDatums.style.display = 'none';
            // Verplicht de datums van de periode, specifieke datums niet meer
            start.required = true;
            eind.required = true;
            spec.required = false;
            spec.value = ''; // Maak leeg voor de zekerheid
        } else {
            // Laat losse datums zien
            blokPeriode.style.display = 'none';
            blokDatums.style.display = 'block';
            // Verplicht de specifieke datums, de start/eind van periode niet meer
            start.required = false;
            eind.required = false;
            spec.required = true;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>