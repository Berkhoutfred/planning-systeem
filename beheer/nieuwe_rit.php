<?php
// Bestand: beheer/nieuwe_rit.php
// VERSIE: Kantoor Portaal - Inclusief PAX (Aantal Personen)

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    function formatDatumDB($nl_datum) {
        if (empty($nl_datum)) return null;
        $d = DateTime::createFromFormat('d-m-Y', $nl_datum);
        return $d ? $d->format('Y-m-d') : null;
    }

    $db_datum_start = formatDatumDB($_POST['datum_start']);
    $tijd_start = str_pad($_POST['tijd_start_uur'], 2, "0", STR_PAD_LEFT) . ':' . str_pad($_POST['tijd_start_min'], 2, "0", STR_PAD_LEFT);
    $datum_start = $db_datum_start . ' ' . $tijd_start . ':00';

    $datum_eind = null;
    if (!empty($_POST['datum_eind']) && $_POST['tijd_eind_uur'] !== '' && $_POST['tijd_eind_min'] !== '') {
        $db_datum_eind = formatDatumDB($_POST['datum_eind']);
        $tijd_eind = str_pad($_POST['tijd_eind_uur'], 2, "0", STR_PAD_LEFT) . ':' . str_pad($_POST['tijd_eind_min'], 2, "0", STR_PAD_LEFT);
        $datum_eind = $db_datum_eind . ' ' . $tijd_eind . ':00';
    }
    
    $klant_id = !empty($_POST['klant_id']) ? (int)$_POST['klant_id'] : null;
    $voertuig_type = $_POST['voertuig_type'];
    $prijsafspraak = !empty($_POST['prijsafspraak']) ? (float)str_replace(',', '.', $_POST['prijsafspraak']) : null;
    $betaalwijze = $_POST['betaalwijze'] ?? 'Contant'; 
    $geschatte_pax = !empty($_POST['geschatte_pax']) ? (int)$_POST['geschatte_pax'] : null; // NIEUW: PAX

    $standaard_naam = "Directe Rit";
    if ($klant_id) {
        $stmt_knaam = $pdo->prepare("SELECT bedrijfsnaam, voornaam, achternaam FROM klanten WHERE id = ? AND tenant_id = ?");
        $stmt_knaam->execute([$klant_id, $tenantId]);
        $kData = $stmt_knaam->fetch();
        if ($kData) {
            $standaard_naam = !empty($kData['bedrijfsnaam']) ? $kData['bedrijfsnaam'] : trim($kData['voornaam'] . ' ' . $kData['achternaam']);
        } else {
            $klant_id = null;
        }
    }
    
    $van_adres = $_POST['van_adres'];
    $naar_adres = $_POST['naar_adres'];
    $omschrijving = trim($_POST['omschrijving']) ?: $standaard_naam;

    $is_retour = isset($_POST['is_retour']) ? true : false;

    try {
        $pdo->beginTransaction();

        // Let op: geschatte_pax toegevoegd aan de query
        $stmt_heen = $pdo->prepare("INSERT INTO ritten (tenant_id, datum_start, datum_eind, klant_id, voertuig_type, prijsafspraak, betaalwijze, geschatte_pax, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'gepland')");
        $stmt_heen->execute([$tenantId, $datum_start, $datum_eind, $klant_id, $voertuig_type, $prijsafspraak, $betaalwijze, $geschatte_pax]);
        $heen_rit_id = $pdo->lastInsertId();

        $stmt_heen_regels = $pdo->prepare("INSERT INTO ritregels (tenant_id, rit_id, omschrijving, van_adres, naar_adres) VALUES (?, ?, ?, ?, ?)");
        $stmt_heen_regels->execute([$tenantId, $heen_rit_id, $omschrijving, $van_adres, $naar_adres]);

        if ($is_retour && !empty($_POST['datum_retour']) && $_POST['tijd_retour_uur'] !== '' && $_POST['tijd_retour_min'] !== '') {
            $db_datum_retour = formatDatumDB($_POST['datum_retour']);
            $tijd_retour = str_pad($_POST['tijd_retour_uur'], 2, "0", STR_PAD_LEFT) . ':' . str_pad($_POST['tijd_retour_min'], 2, "0", STR_PAD_LEFT);
            $retour_start = $db_datum_retour . ' ' . $tijd_retour . ':00';
            
            $retour_van = $naar_adres;
            $retour_naar = $van_adres;
            $retour_omschrijving = "Retour: " . $omschrijving;

            // Let op: geschatte_pax toegevoegd aan de query
            $stmt_retour = $pdo->prepare("INSERT INTO ritten (tenant_id, datum_start, klant_id, voertuig_type, prijsafspraak, betaalwijze, geschatte_pax, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'gepland')");
            $stmt_retour->execute([$tenantId, $retour_start, $klant_id, $voertuig_type, $prijsafspraak, $betaalwijze, $geschatte_pax]);
            $retour_rit_id = $pdo->lastInsertId();

            $stmt_retour_regels = $pdo->prepare("INSERT INTO ritregels (tenant_id, rit_id, omschrijving, van_adres, naar_adres) VALUES (?, ?, ?, ?, ?)");
            $stmt_retour_regels->execute([$tenantId, $retour_rit_id, $retour_omschrijving, $retour_van, $retour_naar]);
            
            $msg = "✅ Heenrit én Retourrit succesvol opgeslagen!";
        } else {
            $msg = "✅ Rit succesvol opgeslagen!";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "❌ Fout bij inboeken: " . $e->getMessage();
    }
}

$stmtKlanten = $pdo->prepare("SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE tenant_id = ? ORDER BY bedrijfsnaam, achternaam ASC");
$stmtKlanten->execute([$tenantId]);
$klanten_lijst = $stmtKlanten->fetchAll();

include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/nl.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(env_value('GOOGLE_MAPS_API_KEY', '')); ?>&libraries=places"></script>

<style>
    body { background-color: #f4f7f6; font-family: 'Segoe UI', Arial, sans-serif; }
    
    .container { max-width: 1000px; padding: 30px; margin: 30px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 4px solid #003366; }
    .container h2 { margin: 0 0 25px 0; font-size: 22px; color: #003366; }
    
    .grid-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px 20px; margin-bottom: 20px; }
    .col-2 { grid-column: span 2; }
    .col-4 { grid-column: span 4; }
    .grid-tijd { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    
    label { display: block; font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 5px; }
    
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #fff; height: 42px; }
    .form-control:focus { border-color: #003366; outline: none; box-shadow: 0 0 0 3px rgba(0,51,102,0.1); }
    
    select.form-control { -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="black" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); background-repeat: no-repeat; background-position-x: 96%; background-position-y: 50%; }

    .btn-submit { background: #003366; color: white; border: none; padding: 12px 20px; border-radius: 4px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s; height: 42px; width: 100%; }
    .btn-submit:hover { background: #002244; }
    
    .msg { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    .msg-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
    
    #retour_sectie { display: none; background: #e3f2fd; padding: 20px; border: 1px solid #b6d4fe; border-radius: 6px; margin-top: 15px; margin-bottom: 20px; }
    
    .select2-container--default .select2-selection--single { height: 42px; border: 1px solid #ccc; border-radius: 4px; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { font-size: 14px; color: #333; padding-left: 10px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }

    @media (max-width: 800px) {
        .container { padding: 15px; margin: 15px; }
        .grid-form { grid-template-columns: 1fr; gap: 15px; }
        .col-2, .col-4 { grid-column: span 1; }
    }
</style>

<div class="container">
    <h2>⚡ Snel Rit Inboeken</h2>

    <?php if ($msg) echo "<div class='msg msg-success'>$msg</div>"; ?>
    <?php if ($error) echo "<div class='msg msg-error'>$error</div>"; ?>

    <form method="POST">
        <div class="grid-form">
            <div>
                <label>Datum *</label>
                <input type="text" name="datum_start" class="form-control datepicker" required value="<?php echo date('d-m-Y'); ?>">
            </div>
            <div class="grid-tijd">
                <div>
                    <label>Tijd (Uur) *</label>
                    <select name="tijd_start_uur" class="form-control" required>
                        <?php for($h=0; $h<24; $h++) { printf("<option value='%02d'>%02d</option>", $h, $h); } ?>
                    </select>
                </div>
                <div>
                    <label>(Min) *</label>
                    <select name="tijd_start_min" class="form-control" required>
                        <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label>Einddatum</label>
                <input type="text" name="datum_eind" class="form-control datepicker" placeholder="Kies datum">
            </div>
            <div class="grid-tijd">
                <div>
                    <label>Eindtijd (Uur)</label>
                    <select name="tijd_eind_uur" class="form-control">
                        <option value="">--</option>
                        <?php for($h=0; $h<24; $h++) { printf("<option value='%02d'>%02d</option>", $h, $h); } ?>
                    </select>
                </div>
                <div>
                    <label>(Min)</label>
                    <select name="tijd_eind_min" class="form-control">
                        <option value="">--</option>
                        <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid-form">
            <div class="col-2">
                <label>Van Adres *</label>
                <input type="text" id="van_adres" name="van_adres" class="form-control" required autocomplete="off" placeholder="Bijv. Dorpsstraat 1">
            </div>
            <div class="col-2">
                <label>Naar Adres *</label>
                <input type="text" id="naar_adres" name="naar_adres" class="form-control" required autocomplete="off" placeholder="Bijv. Ziekenhuis">
            </div>
        </div>

        <div class="grid-form">
            <div class="col-2">
                <label>Klant (Optioneel)</label>
                <select name="klant_id" class="form-control search-klant" style="width: 100%;">
                    <option value="">-- Geen klant (Passant) --</option>
                    <?php foreach($klanten_lijst as $k): ?>
                        <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['bedrijfsnaam'] ?: ($k['voornaam'].' '.$k['achternaam'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Voertuig</label>
                <select name="voertuig_type" class="form-control">
                    <option value="Taxi">Taxi</option>
                    <option value="Rolstoelbus">Rolstoelbus</option>
                    <option value="Touringcar">Touringcar</option>
                </select>
            </div>
            <div>
                <label>Aantal Pers. (PAX)</label>
                <input type="number" name="geschatte_pax" class="form-control" placeholder="Bijv. 4" min="1">
            </div>
        </div>
        
        <div class="grid-form" style="align-items: end;">
            <div>
                <label>Betaalwijze</label>
                <select name="betaalwijze" class="form-control">
                    <option value="Contant" selected>Contant</option>
                    <option value="Pin">Pin (SumUp)</option>
                    <option value="Op Rekening Vast">Op Rekening (Vast bedrag)</option>
                    <option value="Op Rekening Meter">Op Rekening (Op meter)</option>
                    <option value="iDEAL">iDEAL (Betaallink na rit)</option>
                </select>
            </div>
            <div>
                <label>Prijsafspraak (€)</label>
                <input type="number" step="0.01" name="prijsafspraak" class="form-control" placeholder="0.00">
            </div>
            <div class="col-2">
                <label>Bijzonderheden / Rit omschrijving</label>
                <input type="text" name="omschrijving" class="form-control" placeholder="Bijv. Rolstoel mee">
            </div>
        </div>

        <div id="retour_sectie">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #003366;">Gegevens Retourrit (Adressen draaien automatisch om)</h3>
            <div class="grid-form" style="margin-bottom:0;">
                <div>
                    <label>Datum Retour *</label>
                    <input type="text" name="datum_retour" class="form-control datepicker">
                </div>
                <div class="grid-tijd">
                    <div>
                        <label>Tijd (Uur) *</label>
                        <select name="tijd_retour_uur" class="form-control">
                            <option value="">--</option>
                            <?php for($h=0; $h<24; $h++) { printf("<option value='%02d'>%02d</option>", $h, $h); } ?>
                        </select>
                    </div>
                    <div>
                        <label>(Min) *</label>
                        <select name="tijd_retour_min" class="form-control">
                            <option value="">--</option>
                            <option value="00">00</option><option value="15">15</option><option value="30">30</option><option value="45">45</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 20px; margin-top: 15px; border-top: 1px solid #eee; padding-top: 20px;">
            <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color:#003366; font-size: 14px;">
                <input type="checkbox" name="is_retour" id="check_retour" style="width: 18px; height: 18px;"> <b>+ Retourrit (v.v.) toevoegen</b>
            </label>
            <button type="submit" class="btn-submit" style="width: auto; padding: 12px 30px;">💾 Opslaan in Planbord</button>
        </div>

    </form>
</div>

<script>
$(document).ready(function() {
    $('.search-klant').select2();
    
    flatpickr.localize(flatpickr.l10ns.nl);
    flatpickr(".datepicker", { 
        dateFormat: "d-m-Y",
        allowInput: true
    });

    $('#check_retour').change(function() {
        if(this.checked) {
            $('#retour_sectie').slideDown(200);
            $('input[name="datum_retour"], select[name="tijd_retour_uur"], select[name="tijd_retour_min"]').prop('required', true);
        } else {
            $('#retour_sectie').slideUp(200);
            $('input[name="datum_retour"], select[name="tijd_retour_uur"], select[name="tijd_retour_min"]').prop('required', false);
        }
    });
});

function initAutocomplete() {
    var options = { types: ['geocode', 'establishment'], componentRestrictions: {country: "nl"} };
    new google.maps.places.Autocomplete(document.getElementById('van_adres'), options);
    new google.maps.places.Autocomplete(document.getElementById('naar_adres'), options);
}
google.maps.event.addDomListener(window, 'load', initAutocomplete);
</script>

<?php include 'includes/footer.php'; ?>