<?php
// Bestand: beheer/ritten.php
// VERSIE: Dubbele Inbox met Super Slimme Schaartjes (Tijd + Van/Naar) én Touringcar Inbox

include '../beveiliging.php';
require 'includes/db.php';

// --- ACTIE 1: NIEUWE RIT TOEVOEGEN (Kantoor) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'nieuw') {
    $datum        = $_POST['datum'];
    $tijd         = !empty($_POST['tijd']) ? $_POST['tijd'] : '-';
    $chauffeur    = $_POST['chauffeur'];
    $voertuig     = $_POST['voertuig'];
    $soort_rit    = $_POST['soort_rit'];
    $klant        = trim($_POST['klant']);
    $prijs        = str_replace(',', '.', $_POST['prijs']); 
    
    // Bewaar in database inclusief de tijd!
    $route_opslaan = "[" . $tijd . "] " . trim($_POST['van']) . ' ➡️ ' . trim($_POST['naar']) . ' (Betaling: ' . $_POST['betaalwijze'] . ')';

    $stmt = $pdo->prepare("INSERT INTO ritgegevens (datum, chauffeur_naam, type_dienst, voertuig_nummer, status, bron_type, adhoc_klant, adhoc_route, adhoc_prijs) VALUES (?, ?, ?, ?, 'nieuw', 'adhoc', ?, ?, ?)");
    $stmt->execute([$datum, $chauffeur, $soort_rit, $voertuig, $klant, $route_opslaan, $prijs]);
    header("Location: ritten.php");
    exit;
}

// --- ACTIE 2: BESTAANDE TAXI/VRIJE RIT VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rit_id']) && !isset($_POST['actie'])) {
    $id = $_POST['rit_id'];
    $sql = "UPDATE ritgegevens SET chauffeur_naam=?, voertuig_nummer=?, datum=?, opmerkingen=?, status='verwerkt' WHERE id=?";
    $pdo->prepare($sql)->execute([$_POST['chauffeur'], $_POST['voertuig'], $_POST['datum'], $_POST['opmerkingen'], $id]);

    if ($_POST['bron_type'] === 'adhoc') {
        $prijs = str_replace(',', '.', $_POST['adhoc_prijs']);
        $tijd  = !empty($_POST['tijd']) ? $_POST['tijd'] : '-';
        
        $route_opslaan = "[" . $tijd . "] " . trim($_POST['van']) . ' ➡️ ' . trim($_POST['naar']) . ' (Betaling: ' . $_POST['betaalwijze'] . ')';
        
        $sqlAdhoc = "UPDATE ritgegevens SET type_dienst=?, adhoc_klant=?, adhoc_route=?, adhoc_prijs=? WHERE id=?";
        $pdo->prepare($sqlAdhoc)->execute([$_POST['type_dienst'], $_POST['adhoc_klant'], $route_opslaan, $prijs, $id]);
    } else {
        $pdo->prepare("UPDATE ritgegevens SET totaal_km=? WHERE id=?")->execute([$_POST['totaal_km'] ?? 0, $id]);
        if (isset($_POST['regel'])) {
            foreach ($_POST['regel'] as $regel_id => $data) {
                $bedrag = isset($data['bedrag']) ? str_replace(',','.', $data['bedrag']) : 0;
                $km = isset($data['km']) ? $data['km'] : 0;
                $pdo->prepare("UPDATE ritregels SET tijd=?, km_stand=?, bedrag=? WHERE id=?")->execute([$data['tijd'], $km, $bedrag, $regel_id]);
            }
        }
    }
    header("Location: ritten.php");
    exit;
}

// --- ACTIE 3: TOURINGCAR RIT VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'verwerk_touringcar') {
    $id = $_POST['rit_id'];
    
    // Waardes ophalen uit het formulier
    $start = !empty($_POST['werk_start']) ? $_POST['werk_start'] : null;
    $eind = !empty($_POST['werk_eind']) ? $_POST['werk_eind'] : null;
    $pauze = (int)$_POST['werk_pauze'];
    
    $km_start = !empty($_POST['km_start']) ? (int)$_POST['km_start'] : null;
    $km_eind = !empty($_POST['km_eind']) ? (int)$_POST['km_eind'] : null;
    $werkelijke_km = !empty($_POST['werkelijke_km']) ? (int)$_POST['werkelijke_km'] : null;
    
    $betaalwijze = !empty($_POST['betaalwijze']) ? $_POST['betaalwijze'] : 'Rekening';
    $betaald_bedrag = !empty($_POST['betaald_bedrag']) ? str_replace(',', '.', $_POST['betaald_bedrag']) : null;
    
    $notities = trim($_POST['werk_notities']);
    
    // Update de database en status op 'Verwerkt' zetten
    $sql = "UPDATE calculaties SET werk_start=?, werk_eind=?, werk_pauze=?, km_start=?, km_eind=?, werkelijke_km=?, betaalwijze=?, betaald_bedrag=?, werk_notities=?, rit_status='Verwerkt' WHERE id=?";
    $pdo->prepare($sql)->execute([$start, $eind, $pauze, $km_start, $km_eind, $werkelijke_km, $betaalwijze, $betaald_bedrag, $notities, $id]);
    
    header("Location: ritten.php");
    exit;
}

include 'includes/header.php';

// OPHALEN DATA VOOR INBOX 1: Vrije Ritten & Taxi 
$stmt = $pdo->query("SELECT * FROM ritgegevens WHERE status = 'nieuw' ORDER BY datum ASC, id ASC");

// OPHALEN DATA VOOR INBOX 2: Geplande Touringcar
$stmt_touringcar = $pdo->query("
    SELECT c.*, 
           k.bedrijfsnaam, k.voornaam AS k_voornaam, k.achternaam AS k_achternaam, 
           v.voertuig_nummer, v.naam as bus_naam,
           ch.voornaam AS ch_voornaam, ch.achternaam AS ch_achternaam
    FROM calculaties c
    LEFT JOIN klanten k ON c.klant_id = k.id
    LEFT JOIN voertuigen v ON c.voertuig_id = v.id
    LEFT JOIN chauffeurs ch ON c.chauffeur_id = ch.id
    WHERE c.rit_status = 'Voltooid'
    ORDER BY c.rit_datum ASC
");

$bussen = $pdo->query("SELECT voertuig_nummer, naam FROM voertuigen ORDER BY voertuig_nummer ASC")->fetchAll();
$chauffeurs = $pdo->query("SELECT voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam ASC")->fetchAll();
?>

<div style="margin-bottom: 20px;">
    <h1 style="margin-bottom:5px; color:#0056b3;">🚕 Inbox: Vrije Ritten & Taxi</h1>
</div>

<div style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; overflow-x: auto; margin-bottom: 50px;">
    <table style="width:100%; border-collapse:collapse; min-width: 1100px;">
        <thead style="background:#003366; color:white; font-size: 13px;">
            <tr>
                <th style="padding:12px; text-align:left; border-radius:8px 0 0 0;">Datum</th>
                <th style="padding:12px; text-align:left;">Tijd</th>
                <th style="padding:12px; text-align:left;">Van</th>
                <th style="padding:12px; text-align:left;">Naar</th>
                <th style="padding:12px; text-align:left;">Bedrag</th>
                <th style="padding:12px; text-align:left;">Betaling</th>
                <th style="padding:12px; text-align:left;">Chauffeur</th>
                <th style="padding:12px; text-align:left;">Bus</th>
                <th style="padding:12px; text-align:left;">Klantnaam</th>
                <th style="padding:12px; text-align:right; border-radius:0 8px 0 0;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background: #e6f2ff; border-bottom: 2px solid #0056b3; cursor: pointer;" onclick="document.getElementById('rij_toevoegen').style.display = (document.getElementById('rij_toevoegen').style.display === 'none') ? 'table-row' : 'none';">
                <td colspan="10" style="padding: 12px; font-weight: bold; color: #0056b3; text-align: center;">
                    ➕ Klik hier om direct zelf een rit toe te voegen
                </td>
            </tr>
            
            <tr id="rij_toevoegen" style="display: none; background: #fafafa; border-bottom: 3px solid #0056b3;">
                <td colspan="10" style="padding: 20px;">
                    <form method="POST" action="ritten.php" style="margin:0; background:white; padding:20px; border:1px solid #ddd; border-radius:6px;">
                        <input type="hidden" name="actie" value="nieuw">
                        
                        <div style="display:grid; grid-template-columns: repeat(6, 1fr); gap:15px; margin-bottom:15px;">
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Datum</label><input type="date" name="datum" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Tijd</label><input type="time" name="tijd" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Chauffeur</label>
                                <select name="chauffeur" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                    <option value="">-- Kies --</option>
                                    <?php foreach($chauffeurs as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?>"><?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Bus</label>
                                <select name="voertuig" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                    <option value="">-- Kies --</option>
                                    <?php foreach($bussen as $bus): ?>
                                        <option value="<?php echo htmlspecialchars($bus['voertuig_nummer']); ?>"><?php echo htmlspecialchars($bus['voertuig_nummer']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Soort Rit</label>
                                <select name="soort_rit" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                    <option value="Straattaxi">Straattaxi</option>
                                    <option value="Treinstremming NS">Treinstremming NS</option>
                                    <option value="Dagbesteding">Dagbesteding</option>
                                    <option value="Anders">Anders</option>
                                </select>
                            </div>
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Klant</label><input type="text" name="klant" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:15px;">
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Van</label><input type="text" name="van" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Naar</label><input type="text" name="naar" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Bedrag (€)</label><input type="text" name="prijs" placeholder="0.00" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Betaling</label>
                                <select name="betaalwijze" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                    <option value="Contant">Contant</option>
                                    <option value="PIN">PIN</option>
                                    <option value="Op Rekening">Op Rekening</option>
                                </select>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <button type="button" onclick="document.getElementById('rij_toevoegen').style.display='none';" style="background:transparent; border:none; color:#666; cursor:pointer; text-decoration:underline; font-weight:bold; margin-right:15px;">Annuleren</button>
                            <button type="submit" style="background:#0056b3; color:white; border:none; padding:10px 15px; border-radius:4px; font-weight:bold; cursor:pointer;">💾 Opslaan in Inbox</button>
                        </div>
                    </form>
                </td>
            </tr>

            <?php 
            if ($stmt->rowCount() == 0): ?>
                <tr><td colspan="10" style="padding:40px; text-align:center; color:#888;">Geen openstaande taxi- of vrije ritten in deze inbox. 🎉</td></tr>
            <?php endif;

            while ($rit = $stmt->fetch()): 
                $form_id = "form_rit_" . $rit['id'];
                $isAdhoc = ($rit['bron_type'] === 'adhoc');
                
                $tijd = "-"; $van = "-"; $naar = "-"; $betaalwijze = "-"; $bedrag = "-"; $klant = "-";

                if ($isAdhoc) {
                    $klant = htmlspecialchars($rit['adhoc_klant']);
                    $route_zin = $rit['adhoc_route'];
                    
                    // 1. Tijd eruit vissen als die tussen haakjes staat: [14:30]
                    if (preg_match('/^\[(\d{2}:\d{2})\]\s*(.*)$/', $route_zin, $matches)) {
                        $tijd = $matches[1];
                        $route_zin = $matches[2]; // De rest van de zin overhouden
                    }

                    // 2. Betaling eruit vissen
                    if (strpos($route_zin, ' (Betaling: ') !== false) {
                        $parts = explode(' (Betaling: ', $route_zin);
                        $route_zin = $parts[0];
                        $betaalwijze = htmlspecialchars(str_replace(')', '', $parts[1]));
                    } elseif (strpos($route_zin, ' | ') !== false) {
                        $parts = explode(' | ', $route_zin);
                        if (isset($parts[2])) {
                             $betaalwijze = htmlspecialchars(trim($parts[2]));
                             $route_zin = $parts[0] . ' | ' . $parts[1]; // Rest bewaren voor Van/Naar
                        }
                    }

                    // 3. Van en Naar knippen (SUPER ROBUUST: Geen spaties meer nodig rond de pijl!)
                    if (strpos($route_zin, '➡️') !== false) {
                        $parts = explode('➡️', $route_zin);
                        $van = trim(htmlspecialchars($parts[0]));
                        $naar = trim(htmlspecialchars($parts[1]));
                    } elseif (strpos($route_zin, ' | ') !== false) {
                        $parts = explode(' | ', $route_zin);
                        $van = trim(htmlspecialchars($parts[0]));
                        $naar = isset($parts[1]) ? trim(htmlspecialchars($parts[1])) : '-';
                    } else {
                        $van = trim(htmlspecialchars($route_zin));
                    }

                    $bedrag = "&euro; " . number_format((float)$rit['adhoc_prijs'], 2, ',', '.');
                } else {
                    $klant = "<span style='color:#888; font-size:11px;'>Gepland</span>";
                    $bedrag = $rit['totaal_km'] . " km";
                    $van = "<span style='color:#888; font-size:11px;'>Zie uitklap</span>";
                    $naar = "<span style='color:#888; font-size:11px;'>Zie uitklap</span>";
                }
            ?>
                <tr style="border-bottom:1px solid #eee; background: <?php echo $isAdhoc ? '#fffdf5' : '#ffffff'; ?>;">
                    <td style="padding:12px; font-weight:bold;"><?php echo date('d-m-Y', strtotime($rit['datum'])); ?></td>
                    <td style="padding:12px; color: #0056b3; font-weight:bold;"><?php echo $tijd; ?></td>
                    <td style="padding:12px;"><?php echo $van; ?></td>
                    <td style="padding:12px;"><?php echo $naar; ?></td>
                    <td style="padding:12px; font-weight:bold;"><?php echo $bedrag; ?></td>
                    <td style="padding:12px;"><span style="background:#e9ecef; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold; border:1px solid #ccc;"><?php echo $betaalwijze; ?></span></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($rit['chauffeur_naam']); ?></td>
                    <td style="padding:12px; font-weight:bold;">Bus <?php echo htmlspecialchars($rit['voertuig_nummer']); ?></td>
                    <td style="padding:12px; font-size:13px;"><?php echo $klant; ?></td>
                    <td style="padding:12px; text-align:right;">
                        <button type="button" onclick="document.getElementById('detail_<?php echo $rit['id']; ?>').style.display = (document.getElementById('detail_<?php echo $rit['id']; ?>').style.display === 'none') ? 'table-row' : 'none';" style="background:#f0f0f0; border:1px solid #ccc; padding:6px 10px; border-radius:4px; cursor:pointer; font-weight:bold; margin-right:5px; font-size:12px;">
                            ✏️ Inzien
                        </button>
                        <button type="submit" form="<?php echo $form_id; ?>" style="background:#28a745; color:white; border:none; padding:7px 10px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">
                            ✅ Definitief
                        </button>
                    </td>
                </tr>

                <tr id="detail_<?php echo $rit['id']; ?>" style="display:none; background:#fafafa; border-bottom:3px solid #ccc;">
                    <td colspan="10" style="padding:20px;">
                        <form id="<?php echo $form_id; ?>" method="POST" action="ritten.php" style="margin:0; background:white; padding:20px; border:1px solid #ddd; border-radius:6px;">
                            <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                            <input type="hidden" name="bron_type" value="<?php echo $rit['bron_type']; ?>">
                            
                            <div style="display:grid; grid-template-columns: repeat(6, 1fr); gap:15px; margin-bottom:15px;">
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Datum</label><input type="date" name="datum" value="<?php echo $rit['datum']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Tijd</label><input type="time" name="tijd" value="<?php echo ($tijd !== '-') ? $tijd : ''; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Chauffeur</label><input type="text" name="chauffeur" value="<?php echo htmlspecialchars($rit['chauffeur_naam']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Bus</label><input type="text" name="voertuig" value="<?php echo htmlspecialchars($rit['voertuig_nummer']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                
                                <?php if($isAdhoc): ?>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Soort Rit</label><input type="text" name="type_dienst" value="<?php echo htmlspecialchars($rit['type_dienst']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Klant</label><input type="text" name="adhoc_klant" value="<?php echo htmlspecialchars($rit['adhoc_klant']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <?php endif; ?>
                            </div>

                            <?php if($isAdhoc): ?>
                                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:15px;">
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Van</label><input type="text" name="van" value="<?php echo $van; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Naar</label><input type="text" name="naar" value="<?php echo $naar; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Afgerekend (€)</label><input type="text" name="adhoc_prijs" value="<?php echo htmlspecialchars($rit['adhoc_prijs']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Betaling</label>
                                        <select name="betaalwijze" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                            <option value="Contant" <?php if($betaalwijze == 'Contant') echo 'selected'; ?>>Contant</option>
                                            <option value="PIN" <?php if($betaalwijze == 'PIN') echo 'selected'; ?>>PIN</option>
                                            <option value="Op Rekening" <?php if($betaalwijze == 'Op Rekening') echo 'selected'; ?>>Op Rekening</option>
                                        </select>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Totaal KM (Dag)</label>
                                    <input type="number" name="totaal_km" value="<?php echo $rit['totaal_km']; ?>" style="width:150px; padding:6px; border:1px solid #ccc; margin-bottom:10px; border-radius:4px;">
                                </div>
                            <?php endif; ?>

                            <label style="display:block; font-size:11px; font-weight:bold; color:#666; margin-bottom:2px;">Opmerkingen chauffeur / Kantoor notitie</label>
                            <textarea name="opmerkingen" style="width:100%; height:40px; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; font-family:inherit;"><?php echo htmlspecialchars($rit['opmerkingen']); ?></textarea>
                            
                            <div style="text-align:right; margin-top:15px;">
                                <button type="button" onclick="document.getElementById('detail_<?php echo $rit['id']; ?>').style.display='none';" style="background:transparent; border:none; color:#666; cursor:pointer; text-decoration:underline; font-weight:bold; margin-right:15px; font-size:12px;">Sluiten</button>
                                <button type="submit" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">💾 Aanpassingen Opslaan</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div style="margin-bottom: 20px;">
    <h1 style="margin-bottom:5px; color:#28a745;">🚌 Inbox: Geplande Touringcar Ritten</h1>
    <p style="color:#666; font-size:14px; margin-top:0;">Dit zijn de ritten uit het calculatiesysteem die door de chauffeur zijn afgerond.</p>
</div>

<div style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; overflow-x: auto;">
    <table style="width:100%; border-collapse:collapse; min-width: 1100px;">
        <thead style="background:#28a745; color:white; font-size: 13px;">
            <tr>
                <th style="padding:12px; text-align:left; border-radius:8px 0 0 0;">Rit Datum</th>
                <th style="padding:12px; text-align:left;">Chauffeur</th>
                <th style="padding:12px; text-align:left;">Bus</th>
                <th style="padding:12px; text-align:left;">Klantnaam</th>
                <th style="padding:12px; text-align:center;">Werktijd</th>
                <th style="padding:12px; text-align:center;">Totaal Uren</th>
                <th style="padding:12px; text-align:center;">Pauze</th>
                <th style="padding:12px; text-align:center;">Gepland vs Gereden KM</th>
                <th style="padding:12px; text-align:left;">Notities Chauffeur</th>
                <th style="padding:12px; text-align:right; border-radius:0 8px 0 0;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($stmt_touringcar->rowCount() == 0): ?>
                <tr><td colspan="10" style="padding:40px; text-align:center; color:#888;">Geen afgeronde touringcar ritten om te verwerken. Alles is bijgewerkt! ✅</td></tr>
            <?php endif;

            while ($touringcar_rit = $stmt_touringcar->fetch()): 
                $klant = !empty($touringcar_rit['bedrijfsnaam']) ? $touringcar_rit['bedrijfsnaam'] : $touringcar_rit['k_voornaam'] . ' ' . $touringcar_rit['k_achternaam'];
                $chauffeur_naam = trim($touringcar_rit['ch_voornaam'] . ' ' . $touringcar_rit['ch_achternaam']);

                $start = !empty($touringcar_rit['werk_start']) ? date('H:i', strtotime($touringcar_rit['werk_start'])) : '-';
                $eind = !empty($touringcar_rit['werk_eind']) ? date('H:i', strtotime($touringcar_rit['werk_eind'])) : '-';
                $pauze = (int)$touringcar_rit['werk_pauze'];
                
                // --- NIEUW: AUTOMATISCHE UREN BEREKENING ---
                $totale_uren_weergave = "-";
                if ($start !== '-' && $eind !== '-') {
                    $s_ts = strtotime($touringcar_rit['werk_start']);
                    $e_ts = strtotime($touringcar_rit['werk_eind']);
                    
                    // Als de rit na middernacht eindigt (bijv 02:00)
                    if ($e_ts < $s_ts) {
                        $e_ts += 86400; // Tel er 24 uur bij op
                    }
                    
                    $gewerkte_minuten = floor(($e_ts - $s_ts) / 60);
                    $netto_minuten = max(0, $gewerkte_minuten - $pauze); // Netto = Gewerkte tijd min pauze
                    
                    if ($netto_minuten > 0) {
                        $uren = floor($netto_minuten / 60);
                        $min = $netto_minuten % 60;
                        $decimaal = round($netto_minuten / 60, 2);
                        $totale_uren_weergave = $uren . "u " . $min . "m<br><span style='font-size:10px; color:#888;'>(" . str_replace('.', ',', $decimaal) . " uur)</span>";
                    } else {
                        $totale_uren_weergave = "0u 0m";
                    }
                }
                
                // KM Berekening
                $gepland_km = $touringcar_rit['totaal_km'] ?? 0;
                $gereden_km = $touringcar_rit['werkelijke_km'] ?? 0;
                
                $km_kleur = "#333";
                if ($gepland_km > 0 && $gereden_km > 0) {
                    $verschil_procent = abs(($gereden_km - $gepland_km) / $gepland_km * 100);
                    if ($verschil_procent > 10) {
                        $km_kleur = "#dc3545"; 
                    }
                }
                
                $form_id_touring = "form_touring_" . $touringcar_rit['id'];
            ?>
                <tr style="border-bottom:1px solid #eee; background: #ffffff;">
                    <td style="padding:12px; font-weight:bold;"><?php echo date('d-m-Y', strtotime($touringcar_rit['rit_datum'])); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($chauffeur_naam); ?></td>
                    <td style="padding:12px; font-weight:bold;">Bus <?php echo htmlspecialchars($touringcar_rit['voertuig_nummer']); ?></td>
                    <td style="padding:12px; font-size:13px;"><?php echo htmlspecialchars($klant); ?></td>
                    <td style="padding:12px; text-align:center; font-weight:bold;"><?php echo $start; ?> - <?php echo $eind; ?></td>
                    
                    <td style="padding:12px; text-align:center; font-weight:bold; color:#0056b3;">
                        <?php echo $totale_uren_weergave; ?>
                    </td>
                    
                    <td style="padding:12px; text-align:center;"><?php echo $pauze; ?> min</td>
                    <td style="padding:12px; text-align:center; color: <?php echo $km_kleur; ?>;">
                        <span style="font-size: 11px; color:#888;">Gepland: <?php echo $gepland_km; ?> km</span><br>
                        <strong>Gereden: <?php echo $gereden_km; ?> km</strong>
                    </td>
                    <td style="padding:12px; font-size:12px; color:#555; max-width: 200px;">
                        <i><?php echo htmlspecialchars($touringcar_rit['werk_notities']); ?></i>
                    </td>
                    <td style="padding:12px; text-align:right;">
                        <button type="button" onclick="document.getElementById('detail_touring_<?php echo $touringcar_rit['id']; ?>').style.display = (document.getElementById('detail_touring_<?php echo $touringcar_rit['id']; ?>').style.display === 'none') ? 'table-row' : 'none';" style="background:#f0f0f0; border:1px solid #ccc; padding:6px 10px; border-radius:4px; cursor:pointer; font-weight:bold; margin-right:5px; font-size:12px;">
                            ✏️ Inzien
                        </button>
                        <button type="submit" form="<?php echo $form_id_touring; ?>" style="background:#28a745; color:white; border:none; padding:7px 10px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">
                            ✅ Verwerken
                        </button>
                    </td>
                </tr>

                <tr id="detail_touring_<?php echo $touringcar_rit['id']; ?>" style="display:none; background:#fafafa; border-bottom:3px solid #28a745;">
                    <td colspan="10" style="padding:20px;">
                        <form id="<?php echo $form_id_touring; ?>" method="POST" action="ritten.php" style="margin:0; background:white; padding:20px; border:1px solid #ddd; border-radius:6px;">
                            <input type="hidden" name="actie" value="verwerk_touringcar">
                            <input type="hidden" name="rit_id" value="<?php echo $touringcar_rit['id']; ?>">
                            
                            <h3 style="margin-top:0; color:#28a745; font-size:16px;">Controleer en Verwerk Rit</h3>
                            
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:15px;">
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Werk Start</label><input type="time" name="werk_start" value="<?php echo !empty($touringcar_rit['werk_start']) ? date('H:i', strtotime($touringcar_rit['werk_start'])) : ''; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Werk Eind</label><input type="time" name="werk_eind" value="<?php echo !empty($touringcar_rit['werk_eind']) ? date('H:i', strtotime($touringcar_rit['werk_eind'])) : ''; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Pauze (minuten)</label><input type="number" name="werk_pauze" value="<?php echo $touringcar_rit['werk_pauze']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:15px;">
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">KM Start</label><input type="number" name="km_start" value="<?php echo $touringcar_rit['km_start']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">KM Eind</label><input type="number" name="km_eind" value="<?php echo $touringcar_rit['km_eind']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Gereden KM (Berekend)</label><input type="number" name="werkelijke_km" value="<?php echo $touringcar_rit['werkelijke_km']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            </div>
                            
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px; margin-bottom:15px;">
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Betaalwijze</label>
                                    <select name="betaalwijze" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                        <option value="Rekening" <?php if($touringcar_rit['betaalwijze'] == 'Rekening') echo 'selected'; ?>>Op Rekening</option>
                                        <option value="Contant" <?php if($touringcar_rit['betaalwijze'] == 'Contant') echo 'selected'; ?>>Contant in de bus</option>
                                        <option value="PIN" <?php if($touringcar_rit['betaalwijze'] == 'PIN') echo 'selected'; ?>>Gepind in de bus</option>
                                    </select>
                                </div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Ontvangen Bedrag (Indien Contant/PIN)</label><input type="text" name="betaald_bedrag" value="<?php echo $touringcar_rit['betaald_bedrag']; ?>" placeholder="0.00" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                            </div>

                            <label style="display:block; font-size:11px; font-weight:bold; color:#666; margin-bottom:2px;">Notities van chauffeur (en ruimte voor kantoor)</label>
                            <textarea name="werk_notities" style="width:100%; height:60px; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; font-family:inherit;"><?php echo htmlspecialchars($touringcar_rit['werk_notities']); ?></textarea>
                            
                            <div style="text-align:right; margin-top:15px;">
                                <button type="button" onclick="document.getElementById('detail_touring_<?php echo $touringcar_rit['id']; ?>').style.display='none';" style="background:transparent; border:none; color:#666; cursor:pointer; text-decoration:underline; font-weight:bold; margin-right:15px; font-size:12px;">Annuleren</button>
                                <button type="submit" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">✅ Wijzigingen Opslaan & Verwerken</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>