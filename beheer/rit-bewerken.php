<?php
// Bestand: beheer/rit-bewerken.php
// VERSIE: Live Planbord - Moderne Rit Bewerken (Inclusief PAX)

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

if (!isset($_GET['id'])) { 
    echo "<div style='padding:20px;'>Geen rit geselecteerd. <a href='live_planbord.php'>Terug naar planbord</a></div>"; 
    exit; 
}
$rit_id = (int)$_GET['id'];

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
    $betaalwijze = $_POST['betaalwijze']; 
    $geschatte_pax = !empty($_POST['geschatte_pax']) ? (int)$_POST['geschatte_pax'] : null; // NIEUW: PAX
    
    $van_adres = $_POST['van_adres'];
    $naar_adres = $_POST['naar_adres'];
    
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
    $omschrijving = trim($_POST['omschrijving']) ?: $standaard_naam;

    try {
        $pdo->beginTransaction();

        // Let op: geschatte_pax toegevoegd aan de update query
        $stmt_update = $pdo->prepare("UPDATE ritten SET datum_start = ?, datum_eind = ?, klant_id = ?, voertuig_type = ?, prijsafspraak = ?, betaalwijze = ?, geschatte_pax = ? WHERE id = ? AND tenant_id = ?");
        $stmt_update->execute([$datum_start, $datum_eind, $klant_id, $voertuig_type, $prijsafspraak, $betaalwijze, $geschatte_pax, $rit_id, $tenantId]);

        $stmt_update_regels = $pdo->prepare("UPDATE ritregels SET omschrijving = ?, van_adres = ?, naar_adres = ? WHERE rit_id = ? AND tenant_id = ? LIMIT 1");
        $stmt_update_regels->execute([$omschrijving, $van_adres, $naar_adres, $rit_id, $tenantId]);

        $pdo->commit();
        echo "<script>window.location.href='live_planbord.php';</script>";
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "❌ Fout bij opslaan: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT r.*, rg.omschrijving, rg.van_adres, rg.naar_adres 
    FROM ritten r 
    LEFT JOIN ritregels rg ON r.id = rg.rit_id AND rg.tenant_id = r.tenant_id
    WHERE r.id = ? AND r.tenant_id = ? LIMIT 1
");
$stmt->execute([$rit_id, $tenantId]);
$rit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rit) { 
    echo "<div style='padding:20px;'>Rit niet gevonden in de database.</div>"; 
    exit; 
}

require_once __DIR__ . '/includes/ideal_factuur_helpers.php';
$idealWizardRitId = $rit_id;
foreach (ideal_parse_bundle_ids($rit['werk_notities'] ?? null) as $bid) {
    if ($bid > 0) {
        $idealWizardRitId = min($idealWizardRitId, $bid);
    }
}

$start_ts = strtotime($rit['datum_start']);
$rit_datum_start = date('d-m-Y', $start_ts);
$rit_tijd_start_uur = date('H', $start_ts);
$rit_tijd_start_min = date('i', $start_ts);

$rit_datum_eind = '';
$rit_tijd_eind_uur = '';
$rit_tijd_eind_min = '';
if (!empty($rit['datum_eind'])) {
    $eind_ts = strtotime($rit['datum_eind']);
    $rit_datum_eind = date('d-m-Y', $eind_ts);
    $rit_tijd_eind_uur = date('H', $eind_ts);
    $rit_tijd_eind_min = date('i', $eind_ts);
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
    .container { max-width: 1000px; padding: 30px; margin: 30px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 4px solid #dd6b20; }
    
    .header-balk { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    .header-balk h2 { margin: 0; font-size: 22px; color: #003366; }
    
    .grid-form { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px 20px; margin-bottom: 20px; }
    .col-2 { grid-column: span 2; }
    .grid-tijd { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    
    label { display: block; font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 5px; }
    
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #fff; height: 42px; }
    .form-control:focus { border-color: #dd6b20; outline: none; box-shadow: 0 0 0 3px rgba(221,107,32,0.1); }
    
    select.form-control { -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="black" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); background-repeat: no-repeat; background-position-x: 96%; background-position-y: 50%; }

    .btn-submit { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 4px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s; height: 42px; box-shadow: 0 2px 4px rgba(40,167,69,0.3); }
    .btn-submit:hover { background: #218838; transform: translateY(-1px); }
    
    .btn-terug { background: #6c757d; color: white; padding: 10px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; }
    .btn-terug:hover { background: #5a6268; }

    .msg-error { background: #f8d7da; color: #721c24; padding: 15px; border-left: 5px solid #dc3545; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
    
    .select2-container--default .select2-selection--single { height: 42px; border: 1px solid #ccc; border-radius: 4px; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { font-size: 14px; color: #333; padding-left: 10px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
</style>

<div class="container">
    <div class="header-balk">
        <h2>✏️ Rit Wijzigen (#<?= $rit['id'] ?>)</h2>
        <a href="live_planbord.php" class="btn-terug">❮ Annuleren & Terug</a>
    </div>

    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>

    <?php if (($rit['betaalwijze'] ?? '') === 'iDEAL' && ($rit['factuur_status'] ?? '') === 'Te factureren'): ?>
        <div style="background: linear-gradient(135deg, #fff8e6 0%, #fff3cd 100%); padding: 16px 18px; border-radius: 8px; border: 1px solid #ffc107; margin-bottom: 22px; color: #664d03;">
            <strong><i class="fas fa-file-invoice-dollar"></i> iDEAL-factuur</strong> — Bekijk het PDF-voorbeeld, keur goed en verstuur. Daarna wordt de betaallink aangemaakt.
            <a href="factuur_ideal_wizard.php?rit_id=<?php echo (int) $idealWizardRitId; ?>" style="display:inline-block;margin-top:10px;background:#003366;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold;">Open factuur-wizard</a>
        </div>
    <?php endif; ?>

    <form method="POST">
        
        <?php if (!empty($rit['calculatie_id'])): ?>
            <div style="background: #e6f2ff; padding: 15px; border-radius: 5px; border: 1px solid #b8daff; margin-bottom: 20px; color: #004085;">
                <i class="fas fa-info-circle"></i> <strong>Let op:</strong> Dit is een rit die is voortgekomen uit een geaccepteerde offerte. 
                Het is raadzaam om grote adreswijzigingen ook in het oorspronkelijke dossier te vermelden.
            </div>
        <?php endif; ?>

        <div class="grid-form">
            <div>
                <label>Datum *</label>
                <input type="text" name="datum_start" class="form-control datepicker" required value="<?= $rit_datum_start ?>">
            </div>
            <div class="grid-tijd">
                <div>
                    <label>Tijd (Uur) *</label>
                    <select name="tijd_start_uur" class="form-control" required>
                        <?php for($h=0; $h<24; $h++) { 
                            $val = sprintf("%02d", $h);
                            $sel = ($val == $rit_tijd_start_uur) ? 'selected' : '';
                            echo "<option value='{$val}' {$sel}>{$val}</option>"; 
                        } ?>
                    </select>
                </div>
                <div>
                    <label>(Min) *</label>
                    <select name="tijd_start_min" class="form-control" required>
                        <?php 
                        $minuten = ['00', '15', '30', '45'];
                        foreach($minuten as $m) {
                            $sel = ($m == $rit_tijd_start_min) ? 'selected' : '';
                            echo "<option value='{$m}' {$sel}>{$m}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div>
                <label>Einddatum</label>
                <input type="text" name="datum_eind" class="form-control datepicker" value="<?= $rit_datum_eind ?>">
            </div>
            <div class="grid-tijd">
                <div>
                    <label>Eindtijd (Uur)</label>
                    <select name="tijd_eind_uur" class="form-control">
                        <option value="">--</option>
                        <?php for($h=0; $h<24; $h++) { 
                            $val = sprintf("%02d", $h);
                            $sel = ($val == $rit_tijd_eind_uur) ? 'selected' : '';
                            echo "<option value='{$val}' {$sel}>{$val}</option>"; 
                        } ?>
                    </select>
                </div>
                <div>
                    <label>(Min)</label>
                    <select name="tijd_eind_min" class="form-control">
                        <option value="">--</option>
                        <?php 
                        foreach($minuten as $m) {
                            $sel = ($m == $rit_tijd_eind_min) ? 'selected' : '';
                            echo "<option value='{$m}' {$sel}>{$m}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid-form">
            <div class="col-2">
                <label>Van Adres *</label>
                <input type="text" id="van_adres" name="van_adres" class="form-control" required value="<?= htmlspecialchars($rit['van_adres']) ?>">
            </div>
            <div class="col-2">
                <label>Naar Adres *</label>
                <input type="text" id="naar_adres" name="naar_adres" class="form-control" required value="<?= htmlspecialchars($rit['naar_adres']) ?>">
            </div>
        </div>

        <div class="grid-form">
            <div class="col-2">
                <label>Klant (Optioneel)</label>
                <select name="klant_id" class="form-control search-klant" style="width: 100%;">
                    <option value="">-- Geen klant (Passant) --</option>
                    <?php foreach($klanten_lijst as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= ($rit['klant_id'] == $k['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['bedrijfsnaam'] ?: ($k['voornaam'].' '.$k['achternaam'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Voertuig</label>
                <select name="voertuig_type" class="form-control">
                    <option value="Taxi" <?= ($rit['voertuig_type'] == 'Taxi') ? 'selected' : '' ?>>Taxi</option>
                    <option value="Rolstoelbus" <?= ($rit['voertuig_type'] == 'Rolstoelbus') ? 'selected' : '' ?>>Rolstoelbus</option>
                    <option value="Touringcar" <?= ($rit['voertuig_type'] == 'Touringcar') ? 'selected' : '' ?>>Touringcar</option>
                </select>
            </div>
            <div>
                <label>Aantal Pers. (PAX)</label>
                <input type="number" name="geschatte_pax" class="form-control" value="<?= htmlspecialchars($rit['geschatte_pax']) ?>" min="1">
            </div>
        </div>
        
        <div class="grid-form" style="align-items: end;">
            <div>
                <label>Betaalwijze</label>
                <select name="betaalwijze" class="form-control">
                    <option value="Contant" <?= ($rit['betaalwijze'] == 'Contant') ? 'selected' : '' ?>>Contant</option>
                    <option value="Pin" <?= ($rit['betaalwijze'] == 'Pin') ? 'selected' : '' ?>>Pin (SumUp)</option>
                    <option value="Op Rekening Vast" <?= ($rit['betaalwijze'] == 'Op Rekening Vast') ? 'selected' : '' ?>>Op Rekening (Vast)</option>
                    <option value="Op Rekening Meter" <?= ($rit['betaalwijze'] == 'Op Rekening Meter') ? 'selected' : '' ?>>Op Rekening (Meter)</option>
                    <option value="iDEAL" <?= ($rit['betaalwijze'] == 'iDEAL') ? 'selected' : '' ?>>iDEAL (Betaallink na rit)</option>
                </select>
            </div>
            <div>
                <label>Prijsafspraak (€)</label>
                <input type="number" step="0.01" name="prijsafspraak" class="form-control" value="<?= htmlspecialchars($rit['prijsafspraak']) ?>">
            </div>
            <div class="col-2">
                <label>Bijzonderheden / Rit omschrijving</label>
                <input type="text" name="omschrijving" class="form-control" value="<?= htmlspecialchars($rit['omschrijving']) ?>">
            </div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 15px; border-top: 1px solid #eee; padding-top: 20px;">
            <button type="submit" class="btn-submit">💾 Wijzigingen Opslaan</button>
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
});

function initAutocomplete() {
    var options = { types: ['geocode', 'establishment'], componentRestrictions: {country: "nl"} };
    new google.maps.places.Autocomplete(document.getElementById('van_adres'), options);
    new google.maps.places.Autocomplete(document.getElementById('naar_adres'), options);
}
google.maps.event.addDomListener(window, 'load', initAutocomplete);
</script>

<?php include 'includes/footer.php'; ?>