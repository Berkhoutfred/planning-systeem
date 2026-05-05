<?php
// Bestand: beheer/live_planbord.php
// Doel: Live Planbord - Inclusief "Bevestig Klant" knop, Filter voor Akkoorden, OFFERTE weergave én SLIMME VAKANTIE CHECK

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// --- HULPFUNCTIE: ADRES NAAR STAD ---
function extract_stad($adres) {
    if (empty(trim((string)$adres))) return '-';
    $adres = str_ireplace([', Nederland', ' Nederland', ', NL'], '', (string)$adres);
    if (preg_match('/[0-9]{4}\s?[A-Za-z]{2}\s+([^,]+)/', $adres, $matches)) return trim($matches[1]);
    if (strpos($adres, ',') !== false) {
        $parts = explode(',', $adres);
        return trim(end($parts));
    }
    return trim($adres);
}

// --- NIEUW: HULPFUNCTIE: CHAUFFEUR STATUS CHECK ---
// Kijkt bliksemsnel in de ingeladen lijst of iemand vandaag op vakantie of beschikbaar is
function check_chauffeur_status($chauffeur_id, $rit_datum, $afwezigheden) {
    foreach ($afwezigheden as $afw) {
        if ($afw['chauffeur_id'] == $chauffeur_id) {
            if ($rit_datum >= $afw['startdatum'] && $rit_datum <= $afw['einddatum']) {
                return $afw['type'];
            }
        }
    }
    return false;
}

// --- TIJD & FILTER LOGICA ---
$vandaag = date('Y-m-d');
$morgen = date('Y-m-d', strtotime('+1 day'));

$filter_van = $vandaag;
$filter_tot = date('Y-m-d', strtotime('+7 days'));

if (!empty($_GET['week']) && !empty($_GET['jaar'])) {
    $week = (int)$_GET['week'];
    $jaar = (int)$_GET['jaar'];
    $dto = new DateTime();
    $dto->setISODate($jaar, $week);
    $filter_van = $dto->format('Y-m-d'); 
    $dto->modify('+6 days');
    $filter_tot = $dto->format('Y-m-d'); 
} 
elseif (!empty($_GET['datum_van']) && !empty($_GET['datum_tot'])) {
    $filter_van = $_GET['datum_van'];
    $filter_tot = $_GET['datum_tot'];
}

$verberg_groen = isset($_GET['verberg_groen']) ? 1 : 0;
$alleen_akkoord = isset($_GET['alleen_akkoord']) ? 1 : 0; 

$huidig_jaar = date('Y');
$dagen_nl = ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'];

try {
    $sql = "
        SELECT 
            r.id AS rit_id, r.calculatie_id, r.datum_start, r.datum_eind, r.geschatte_pax,
            r.voertuig_id, r.chauffeur_id, r.geaccepteerd_tijdstip, r.paraplu_volgnummer,
            c.titel, c.status AS calculatie_status, 
            k.bedrijfsnaam, k.voornaam, k.achternaam,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_vertrek' LIMIT 1) as vertrek_adres,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id LIMIT 1) as vaste_rit_naam,
            (SELECT van_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as vaste_rit_vertrek,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as vaste_rit_bestemming
        FROM ritten r
        LEFT JOIN calculaties c ON r.calculatie_id = c.id
        LEFT JOIN klanten k ON c.klant_id = k.id
        WHERE DATE(r.datum_start) >= :datum_van AND DATE(r.datum_start) <= :datum_tot
    ";

    if ($verberg_groen) {
        $sql .= " AND (r.voertuig_id IS NULL OR r.chauffeur_id IS NULL OR r.geaccepteerd_tijdstip IS NULL)";
    }
    
    if ($alleen_akkoord) {
        $sql .= " AND c.status = 'klant_akkoord'";
    }

    $sql .= " ORDER BY r.datum_start ASC, r.calculatie_id ASC, r.paraplu_volgnummer ASC";

    $stmt_planbord = $pdo->prepare($sql);
    $stmt_planbord->execute(['datum_van' => $filter_van, 'datum_tot' => $filter_tot]);
    $alle_ritten = $stmt_planbord->fetchAll();

    $stmt_chauf = $pdo->query("SELECT id, voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam ASC");
    $chauffeurs = $stmt_chauf->fetchAll();

    $stmt_bus = $pdo->query("SELECT id, voertuig_nummer, naam FROM voertuigen WHERE archief = 0 AND status != 'werkplaats' ORDER BY voertuig_nummer ASC");
    $bussen = $stmt_bus->fetchAll();

    // --- NIEUW: VAKANTIES OPHALEN VOOR DEZE PERIODE ---
    // We halen in één klap alle afwezigheden op die overlappen met onze filter periode
    $stmt_afw = $pdo->prepare("SELECT chauffeur_id, startdatum, einddatum, type FROM afwezigheid WHERE einddatum >= :filter_van AND startdatum <= :filter_tot");
    $stmt_afw->execute(['filter_van' => $filter_van, 'filter_tot' => $filter_tot]);
    $alle_afwezigheden = $stmt_afw->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
$nu_tijd = time();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    .status-rood { background-color: #fff0f0; border-left: 5px solid #dc3545; }
    .status-oranje { background-color: #fff8e6; border-left: 5px solid #fd7e14; }
    .status-groen { background-color: #f0fff0; border-left: 5px solid #28a745; }
    
    .rit-geweest td { opacity: 0.5; background-color: #f9f9f9 !important; }
    .rit-geweest:hover td { opacity: 0.9; }
    
    .text-onderweg { color: #0056b3; font-weight: bold; }
    .pulse-dot { display: inline-block; width: 8px; height: 8px; background-color: #dc3545; border-radius: 50%; margin-right: 5px; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(220, 53, 69, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

    .tabel-planbord { width: 100%; border-collapse: collapse; background: white; font-size: 13px; }
    .tabel-planbord th, .tabel-planbord td { border: 1px solid #dee2e6; padding: 6px 8px; vertical-align: middle; transition: all 0.2s; }
    .tabel-planbord th { background-color: #003366; color: white; text-align: center; font-size: 11px; padding: 10px 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .tabel-planbord td.links { text-align: left; }
    .tabel-planbord td.midden { text-align: center; }
    
    .kolom-datum { min-width: 200px; }
    .kolom-klant { min-width: 160px; }
    .kolom-route { min-width: 180px; line-height: 1.5; }
    .kolom-chauf { min-width: 155px; }

    .bus-kolom { width: 22px; cursor: pointer; padding: 2px !important; }
    .bus-kolom:hover { background-color: #e9ecef; }
    input[type="radio"] { cursor: pointer; margin: 0; transform: scale(1.1); }
    
    .btn-opslaan { background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; transition: 0.2s; }
    .btn-opslaan:hover { background: #0056b3; }
    
    .btn-bevestig { background: #28a745; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 11px; margin-top: 5px; width: 100%; transition: 0.2s; box-shadow: 0 2px 4px rgba(40,167,69,0.3); }
    .btn-bevestig:hover { background: #218838; transform: translateY(-1px); }
    
    .nav-balk { background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 15px; display: flex; flex-wrap: wrap; align-items: center; gap: 20px; font-size: 13px; }
    .nav-sectie { display: flex; align-items: center; gap: 10px; border-right: 2px solid #ddd; padding-right: 20px; }
    .nav-sectie:last-child { border-right: none; }
    
    .btn-snel { background: #6c757d; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 12px; }
    .btn-snel:hover { background: #5a6268; }
    .input-sm { padding: 5px; border: 1px solid #ccc; border-radius: 4px; width: 70px; }
    .input-date { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
    .btn-actie { background: #28a745; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-actie:hover { background: #218838; }
</style>

<div style="max-width: 100%; margin: 15px; padding: 0 5px;">
    <h1 style="color: #003366; margin-bottom: 5px; font-size: 22px;">🗺️ Live Planbord</h1>
    
    <p style="margin-bottom: 15px; color: #555; font-size: 12px; display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
        <span><span style="display:inline-block; width:10px; height:10px; background:#dc3545; margin-right:4px;"></span> Incompleet</span>
        <span><span style="display:inline-block; width:10px; height:10px; background:#fd7e14; margin-right:4px;"></span> Wacht op acceptatie</span>
        <span><span style="display:inline-block; width:10px; height:10px; background:#28a745; margin-right:4px;"></span> 100% Rond</span>
    </p>

    <div class="nav-balk">
        <div class="nav-sectie">
            <strong>Snel:</strong>
            <a href="?datum_van=<?= $vandaag ?>&datum_tot=<?= $vandaag ?>" class="btn-snel">Vandaag</a>
            <a href="?datum_van=<?= $morgen ?>&datum_tot=<?= $morgen ?>" class="btn-snel">Morgen</a>
        </div>
        <form method="GET" class="nav-sectie" style="margin:0;">
            <strong>Week:</strong>
            <input type="number" name="week" class="input-sm" placeholder="Nr" min="1" max="53" required>
            <input type="number" name="jaar" class="input-sm" value="<?= $huidig_jaar ?>" required>
            <button type="submit" class="btn-actie" style="background: #17a2b8;">Toon Week</button>
        </form>
        <form method="GET" class="nav-sectie" style="margin:0; border:none; display:flex; align-items:center; flex-wrap:wrap; gap:10px;">
            <strong>Periode:</strong>
            <input type="date" name="datum_van" class="input-date" value="<?= $filter_van ?>" required>
            <span>t/m</span>
            <input type="date" name="datum_tot" class="input-date" value="<?= $filter_tot ?>" required>
            
            <label style="cursor: pointer; font-size: 12px; margin-left: 5px;">
                <input type="checkbox" name="verberg_groen" value="1" <?= $verberg_groen ? 'checked' : '' ?>> Verberg compleet
            </label>
            
            <label style="cursor: pointer; font-size: 12px; font-weight: bold; color: #003366; background: #e6f2ff; padding: 4px 8px; border-radius: 4px; border: 1px solid #b8daff;">
                <input type="checkbox" name="alleen_akkoord" value="1" <?= $alleen_akkoord ? 'checked' : '' ?>> Wacht op Bevestiging
            </label>

            <button type="submit" class="btn-actie">Toepassen</button>
        </form>
    </div>

    <div style="overflow-x: auto; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <table class="tabel-planbord">
            <thead>
                <tr>
                    <th class="links kolom-datum">Datum & Tijd</th>
                    <th class="links kolom-klant">Klant / Rit</th>
                    <th class="links kolom-route">Route</th>
                    <th class="midden" style="width: 50px;">PAX</th>
                    <th class="midden kolom-chauf">Chauffeur</th>
                    <th class="midden" style="color: #ffcccc;" title="Wis toegewezen bus">WIS</th>
                    <?php foreach($bussen as $bus): ?>
                        <th title="<?= $bus['naam'] ?>"><?= $bus['voertuig_nummer'] ?></th>
                    <?php endforeach; ?>
                    <th class="midden" style="min-width: 90px;">Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($alle_ritten) == 0): ?>
                    <tr><td colspan="100%" class="midden" style="padding: 20px;">Geen ritten gevonden in deze periode die aan het filter voldoen.</td></tr>
                <?php else: ?>
                    <?php foreach($alle_ritten as $rit): 
                        
                        // DEEL 1: KLANT EN ROUTE
                        if (!empty($rit['calculatie_id'])) {
                            $klantBase = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                            
                            $jaar_rit = date('y', strtotime($rit['datum_start']));
                            $offerteNummer = $jaar_rit . str_pad($rit['calculatie_id'], 3, '0', STR_PAD_LEFT);

                            $klantWeergave = "<strong style='font-size: 14px;'>" . htmlspecialchars($klantBase) . "</strong><br>";
                            
                            $pdfLink = "calculatie/pdf_offerte.php?id=" . $rit['calculatie_id'];
                            $klantWeergave .= "<a href='" . $pdfLink . "' target='_blank' style='text-decoration:none;' title='Klik om de offerte PDF te openen'>";
                            $klantWeergave .= "<span style='display:inline-block; margin-top:3px; color:#004085; font-size:11px; font-weight:bold; background:#cce5ff; padding:3px 6px; border-radius:3px; border:1px solid #b8daff; transition: 0.2s;' onmouseover='this.style.background=\"#b8daff\"' onmouseout='this.style.background=\"#cce5ff\"'><i class='fas fa-file-signature'></i> OFFERTE #" . $offerteNummer . "</span>";
                            $klantWeergave .= "</a>";
                            
                            $van_stad = extract_stad($rit['vertrek_adres']);
                            $naar_stad = extract_stad($rit['bestemming_adres']);
                        } else {
                            $klantWeergave = "<i class='fas fa-sync-alt' style='color:#17a2b8; margin-right:6px;' title='Vaste Rit'></i>" . htmlspecialchars($rit['vaste_rit_naam']);
                            $van_stad = extract_stad($rit['vaste_rit_vertrek']);
                            $naar_stad = extract_stad($rit['vaste_rit_bestemming']);
                        }

                        // DEEL 2: DATUM EN TIJD
                        $start_tijd = strtotime($rit['datum_start']);
                        $eind_tijd = !empty($rit['datum_eind']) ? strtotime($rit['datum_eind']) : ($start_tijd + 7200); 
                        $dag_nr = date('w', $start_tijd);
                        $dag_naam = $dagen_nl[$dag_nr];
                        $datum_weergave = $dag_naam . ' ' . date('d-m-Y', $start_tijd);
                        $tijd_weergave = date('H:i', $start_tijd);
                        
                        $rit_datum_puur = date('Y-m-d', $start_tijd); // Voor de vakantie check!

                        $tijd_html = "<span style='color: #d97706; font-weight: bold;'>{$tijd_weergave}</span>";
                        $extra_klasse = "";

                        if ($nu_tijd > $eind_tijd) {
                            $extra_klasse = "rit-geweest"; 
                        } elseif ($nu_tijd >= $start_tijd && $nu_tijd <= $eind_tijd) {
                            $tijd_html = "<span class='pulse-dot'></span><span class='text-onderweg'>{$tijd_weergave} NU</span>";
                        }

                        // DEEL 3: KLEUREN
                        $rij_klasse = 'status-rood'; 
                        if (!empty($rit['voertuig_id']) && !empty($rit['chauffeur_id'])) {
                            if (empty($rit['geaccepteerd_tijdstip'])) {
                                $rij_klasse = 'status-oranje'; 
                            } else {
                                $rij_klasse = 'status-groen'; 
                            }
                        }

                        $pax_aantal = !empty($rit['geschatte_pax']) ? htmlspecialchars($rit['geschatte_pax']) : '-';
                    ?>
                    <tr class="<?= $rij_klasse ?> <?= $extra_klasse ?>">
                        
                        <td class="links" style="white-space: nowrap; font-size: 13px;">
                            <div style="display: flex; align-items: center;">
                                <span style="color:#444; font-weight:600; display:inline-block; width: 145px;"><?= $datum_weergave ?></span>
                                <?= $tijd_html ?>
                            </div>
                        </td>
                        
                        <td class="links" style="font-weight: 500; font-size: 13px; color: #222;">
                            <?= $klantWeergave ?>
                        </td>
                        
                        <td class="links kolom-route">
                            <span style="color:#6c757d; font-size: 12px; font-weight:600;">Van:</span> <span style="font-size: 13px; color:#333;"><?= htmlspecialchars($van_stad) ?></span><br>
                            <span style="color:#6c757d; font-size: 12px; font-weight:600;">Naar:</span> <span style="font-size: 13px; color:#333;"><?= htmlspecialchars($naar_stad) ?></span>
                        </td>
                        
                        <td class="midden" style="font-weight: 800; color: #333; font-size: 14px;">
                            <?= $pax_aantal ?>
                        </td>
                        
                        <td class="midden">
                            <select name="chauffeur_<?= $rit['rit_id'] ?>" style="width: 100%; padding: 5px; font-size: 12px; border: 1px solid #ccc; border-radius:4px; font-weight: 500;">
                                <option value="">-- Kies --</option>
                                <?php foreach($chauffeurs as $chauf): 
                                    // Bepaal de status van deze chauffeur op deze specifieke datum
                                    $chauf_status = check_chauffeur_status($chauf['id'], $rit_datum_puur, $alle_afwezigheden);
                                    
                                    $weergaveNaam = htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']);
                                    $optieStijl = "";
                                    $is_disabled = "";
                                    
                                    if ($chauf_status === 'Beschikbaar') {
                                        $weergaveNaam .= " [WIL WERKEN!]";
                                        $optieStijl = "color: #28a745; font-weight: bold;";
                                    } elseif ($chauf_status) { 
                                        // Alle overige statussen (Vakantie, Ziek, etc.)
                                        $weergaveNaam .= " [" . strtoupper($chauf_status) . "]";
                                        $optieStijl = "color: #dc3545; font-weight: bold; background: #fff0f0;";
                                        
                                        // Blokkeer hem, TENZIJ hij per ongeluk al aan deze rit was gekoppeld (zodat je het kunt oplossen)
                                        if ($rit['chauffeur_id'] != $chauf['id']) {
                                            $is_disabled = "disabled";
                                        }
                                    }
                                ?>
                                    <option value="<?= $chauf['id'] ?>" <?= ($rit['chauffeur_id'] == $chauf['id']) ? 'selected' : '' ?> <?= $is_disabled ?> style="<?= $optieStijl ?>">
                                        <?= $weergaveNaam ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td class="midden bus-kolom" onclick="this.querySelector('input').checked = true;" style="background-color: #fff8f8; border-right: 2px solid #dee2e6;">
                            <input type="radio" name="bus_<?= $rit['rit_id'] ?>" value="" <?= empty($rit['voertuig_id']) ? 'checked' : '' ?>>
                        </td>

                        <?php foreach($bussen as $bus): ?>
                            <td class="midden bus-kolom" onclick="this.querySelector('input').checked = true;">
                                <input type="radio" name="bus_<?= $rit['rit_id'] ?>" value="<?= $bus['id'] ?>" <?= ($rit['voertuig_id'] == $bus['id']) ? 'checked' : '' ?>>
                            </td>
                        <?php endforeach; ?>

                        <td class="midden">
                            <div style="display:flex; flex-direction:column; align-items:center;">
                                <button type="button" class="btn-opslaan" style="width:100%;" onclick="opslaanRit(<?= $rit['rit_id'] ?>)">Opslaan</button>
                                
                                <?php if (isset($rit['calculatie_status']) && $rit['calculatie_status'] === 'klant_akkoord'): ?>
                                    <button type="button" class="btn-bevestig" onclick="bevestigRit(<?= $rit['calculatie_id'] ?>)">✅ Bevestig</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function opslaanRit(ritId) {
    const chaufSelect = document.querySelector(`select[name="chauffeur_${ritId}"]`);
    const chauffeurId = chaufSelect ? chaufSelect.value : null;

    const busRadios = document.querySelectorAll(`input[name="bus_${ritId}"]`);
    let voertuigId = null;
    busRadios.forEach(radio => {
        if (radio.checked) voertuigId = radio.value;
    });

    fetch('ajax_planbord_opslaan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rit_id: ritId, chauffeur_id: chauffeurId, voertuig_id: voertuigId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); 
        } else {
            alert('FOUT: ' + data.message); 
        }
    })
    .catch(error => { alert('Systeemfout bij opslaan.'); });
}

function bevestigRit(calculatieId) {
    if(!confirm('Weet je zeker dat je de definitieve bevestiging per e-mail naar de klant wilt sturen?')) return;

    event.target.innerText = "⏳ Verzenden...";
    event.target.style.background = "#6c757d";

    fetch('ajax_bevestiging_sturen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ calculatie_id: calculatieId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Bevestiging is succesvol verstuurd naar de klant!');
            location.reload(); 
        } else {
            alert('FOUT: ' + data.message); 
            event.target.innerText = "✅ Bevestig";
            event.target.style.background = "#28a745";
        }
    })
    .catch(error => { 
        alert('Systeemfout bij het versturen van de mail.'); 
        console.error(error);
    });
}
</script>

<?php include 'includes/footer.php'; ?>