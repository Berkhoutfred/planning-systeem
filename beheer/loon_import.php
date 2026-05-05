<?php
// Bestand: beheer/loon_import.php
// VERSIE: Kantoor - Loonadministratie (Slimme Parser & Leeg-Scherm Fix)

session_start();
ini_set('auto_detect_line_endings', TRUE);

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

$melding = '';
$totaal_regels = 0;

$stmt_chauffeurs = $pdo->query("SELECT id, voornaam, achternaam FROM chauffeurs");
$db_chauffeurs = $stmt_chauffeurs->fetchAll();

$tarief_onderbreking = 15.92;

function schoon_naam($naam) {
    $naam = mb_strtolower(trim($naam), 'UTF-8');
    $zoek = ['é', 'è', 'ë', 'ê', 'á', 'à', 'ä', 'â', 'ó', 'ò', 'ö', 'ô', 'í', 'ì', 'ï', 'î', 'ú', 'ù', 'ü', 'û'];
    $vervang = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'a', 'o', 'o', 'o', 'o', 'i', 'i', 'i', 'i', 'u', 'u', 'u', 'u'];
    $naam = str_replace($zoek, $vervang, $naam);
    return preg_replace('/[^a-z]/', '', $naam);
}

function bereken_rit_toeslag_uren($start_ts, $eind_ts, $is_ov) {
    $emmers = [
        'ongeregeld_nacht' => 0, 'ongeregeld_zat' => 0, 'ongeregeld_zon' => 0,
        'ov_nacht' => 0, 'ov_zat' => 0, 'ov_zon' => 0
    ];
    if (!$start_ts || !$eind_ts || $start_ts == $eind_ts) return $emmers;
    
    $start_dag_nr = date('N', $start_ts); 
    
    for ($ts = $start_ts; $ts < $eind_ts; $ts += 60) {
        $dag_nr = date('N', $ts);
        $uur = (float) date('G', $ts) + ((float) date('i', $ts) / 60);
        if ($is_ov) {
            if ($dag_nr == 7 || ($start_dag_nr == 7 && $dag_nr == 1 && $uur < 6.0)) { $emmers['ov_zon'] += 1; } 
            elseif ($dag_nr == 6) { $emmers['ov_zat'] += 1; } 
            else { if ($uur >= 19.0 || $uur < 7.5) { $emmers['ov_nacht'] += 1; } }
        } else {
            if ($dag_nr == 7) { $emmers['ongeregeld_zon'] += 1; } 
            elseif ($dag_nr == 6) { $emmers['ongeregeld_zat'] += 1; } 
            else { if ($uur >= 0.0 && $uur < 6.0) { $emmers['ongeregeld_nacht'] += 1; } }
        }
    }
    foreach($emmers as $key => $minuten) { $emmers[$key] = $minuten / 60; }
    return $emmers;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'scannen' && isset($_FILES['csv_bestand'])) {
    if ($_FILES['csv_bestand']['error'] === UPLOAD_ERR_OK) {
        $bestand = $_FILES['csv_bestand']['tmp_name'];
        $ruwe_tekst = file_get_contents($bestand);
        if (substr($ruwe_tekst, 0, 3) === "\xEF\xBB\xBF") { $ruwe_tekst = substr($ruwe_tekst, 3); }
        $ruwe_tekst = str_replace(array("\r\n", "\r"), "\n", $ruwe_tekst);
        $regels = explode("\n", $ruwe_tekst);
        
        if (count($regels) > 1) {
            $scheidingsteken = (strpos($regels[0], ';') !== false) ? ';' : ',';
            $alle_data_per_chauffeur = [];
            $totale_uren_per_chauffeur = []; 
            
            $dagen_nl = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];
            $maanden_nl = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
            $maanden_en = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
            
            $actieve_chauffeur = 'Onbekend'; // Het slimme geheugen voor de namen
            
            foreach ($regels as $regel) {
                $regel = trim($regel);
                if (empty($regel)) continue;
                
                $data = str_getcsv($regel, $scheidingsteken);
                
                $waarde_a = trim($data[0] ?? '', " \t\n\r\0\x0B\xEF\xBB\xBF\"");
                $waarde_b = trim($data[1] ?? '');
                
                // DE SLIMME DETECTIE: Is dit een header-regel met een naam?
                if (strpos(strtoupper($waarde_b), 'VAN (A)') !== false) {
                    $actieve_chauffeur = $waarde_a; // Onthoud deze naam!
                    continue; // Sla deze regel over en ga direct naar de tijden eronder
                }
                
                // Controleer of de regel wel begint met een geldige datum (een maandnaam)
                $is_datum = false;
                foreach($maanden_nl as $m) { if(stripos($waarde_a, $m) !== false) $is_datum = true; }
                
                if (!$is_datum) {
                    continue; // Sla over, dit is geen gewerkte dag
                }
                
                $huidige_chauffeur = $actieve_chauffeur;
                $datum_veld = $waarde_a;
                
                if (!isset($alle_data_per_chauffeur[$huidige_chauffeur])) {
                    $alle_data_per_chauffeur[$huidige_chauffeur] = [];
                    $totale_uren_per_chauffeur[$huidige_chauffeur] = 0;
                }
                
                $vertaald = str_ireplace($dagen_nl, '', $datum_veld);
                $vertaald = str_ireplace($maanden_nl, $maanden_en, $vertaald);
                $echte_datum = date('Y-m-d', strtotime(trim($vertaald)));
                if (!$echte_datum || $echte_datum == '1970-01-01') continue; 
                
                // Haal de tijden op vanaf de juiste kolommen (Nieuwe layout)
                $van_a = trim($data[1] ?? ''); $tot_a = trim($data[2] ?? '');
                $van_b = trim($data[5] ?? ''); $tot_b = trim($data[6] ?? '');
                $van_c = ''; $tot_c = '';
                
                if ($van_a === $tot_a) { $van_a = ''; $tot_a = ''; }
                if ($van_b === $tot_b) { $van_b = ''; $tot_b = ''; }
                
                $is_ov = false;
                foreach ($data as $cel) {
                    $cel_schoon = strtolower(trim($cel));
                    // Pakt 'OV', 'JA', of gewoon een 'j' (wat vaak in Excel staat als OV vinkje)
                    if ($cel_schoon === 'ov' || $cel_schoon === 'ja' || $cel_schoon === 'j') { 
                        $is_ov = true; 
                        break; 
                    }
                }
                
                $uren_a = 0; $uren_b = 0; $uren_c = 0;
                $ts_a_start = 0; $ts_a_eind = 0;
                $ts_b_start = 0; $ts_b_eind = 0;
                $ts_c_start = 0; $ts_c_eind = 0;
                
                if (!empty($van_a) && !empty($tot_a)) {
                    $ts_a_start = strtotime("$echte_datum $van_a");
                    $ts_a_eind = strtotime("$echte_datum $tot_a");
                    if ($ts_a_eind <= $ts_a_start) $ts_a_eind += 86400; 
                    $uren_a = ($ts_a_eind - $ts_a_start) / 3600;
                }
                if (!empty($van_b) && !empty($tot_b)) {
                    $ts_b_start = strtotime("$echte_datum $van_b");
                    if ($ts_a_eind > 0 && $ts_b_start < $ts_a_eind) { $ts_b_start += 86400; }
                    $ts_b_eind = strtotime("$echte_datum $tot_b");
                    while ($ts_b_eind <= $ts_b_start) { $ts_b_eind += 86400; }
                    $uren_b = ($ts_b_eind - $ts_b_start) / 3600;
                }
                
                $netto_uren = $uren_a + $uren_b + $uren_c;
                $totale_uren_per_chauffeur[$huidige_chauffeur] += $netto_uren;
                
                $onderbreking_aantal = 0;
                if ($ts_a_eind > 0 && $ts_b_start > 0 && ($ts_b_start - $ts_a_eind) > 3540) { $onderbreking_aantal++; }
                
                $emmers_a = bereken_rit_toeslag_uren($ts_a_start, $ts_a_eind, $is_ov);
                $emmers_b = bereken_rit_toeslag_uren($ts_b_start, $ts_b_eind, $is_ov);
                
                $totaal_emmers = [];
                foreach ($emmers_a as $key => $uren) { 
                    $totaal_emmers[$key] = $uren + $emmers_b[$key]; 
                }
                
                $rij_data = [];
                $rij_data['is_ov'] = $is_ov;
                $rij_data['echte_datum'] = $echte_datum;
                $rij_data['netto_uren'] = round($netto_uren, 2);
                $rij_data['toeslag_emmers'] = $totaal_emmers;
                $rij_data['onderbreking_aantal'] = $onderbreking_aantal;
                $rij_data['van_a'] = $van_a; $rij_data['tot_a'] = $tot_a;
                $rij_data['van_b'] = $van_b; $rij_data['tot_b'] = $tot_b;
                $rij_data['van_c'] = $van_c; $rij_data['tot_c'] = $tot_c;
                
                $alle_data_per_chauffeur[$huidige_chauffeur][] = $rij_data;
                $totaal_regels++;
            }
            
            foreach ($alle_data_per_chauffeur as $naam => $regels) {
                if ($totale_uren_per_chauffeur[$naam] <= 0) { unset($alle_data_per_chauffeur[$naam]); }
            }
            $_SESSION['import_klaar_voor_opslag'] = $alle_data_per_chauffeur;
            
            $melding = "<div class='alert alert-success'>✅ <strong>Scan Voltooid!</strong> Controleer de tabel hieronder.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'opslaan') {
    if (isset($_SESSION['import_klaar_voor_opslag'])) {
        $op_te_slaan_data = $_SESSION['import_klaar_voor_opslag'];
        $aantal_opgeslagen = 0;
        $niet_gevonden = []; 
        
        try {
            $stmt_insert = $pdo->prepare("INSERT INTO loon_uren 
                (chauffeur_id, datum, type_vervoer, uren_basis, toeslag_avond, toeslag_weekend, toeslag_zon_feest, toeslag_ov_avond_nacht, toeslag_ov_zaterdag, toeslag_ov_zondag, onderbreking_aantal) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                type_vervoer=VALUES(type_vervoer), uren_basis=VALUES(uren_basis), toeslag_avond=VALUES(toeslag_avond), toeslag_weekend=VALUES(toeslag_weekend), toeslag_zon_feest=VALUES(toeslag_zon_feest), toeslag_ov_avond_nacht=VALUES(toeslag_ov_avond_nacht), toeslag_ov_zaterdag=VALUES(toeslag_ov_zaterdag), toeslag_ov_zondag=VALUES(toeslag_ov_zondag), onderbreking_aantal=VALUES(onderbreking_aantal)");
            
            foreach ($op_te_slaan_data as $chauffeur_naam => $regels) {
                $chauffeur_id = null;
                $csv_schoon = schoon_naam($chauffeur_naam);
                
                foreach ($db_chauffeurs as $c) {
                    $db_vol_schoon = schoon_naam($c['voornaam']) . schoon_naam($c['achternaam']);
                    if ($db_vol_schoon === $csv_schoon) { $chauffeur_id = $c['id']; break; }
                }
                
                if (!$chauffeur_id) {
                    foreach ($db_chauffeurs as $c) {
                        $db_vol_schoon = schoon_naam($c['voornaam']) . schoon_naam($c['achternaam']);
                        if (!empty($csv_schoon) && !empty($db_vol_schoon)) {
                            if (strpos($db_vol_schoon, $csv_schoon) !== false || strpos($csv_schoon, $db_vol_schoon) !== false) {
                                $chauffeur_id = $c['id']; break;
                            }
                        }
                    }
                }
                
                if (!$chauffeur_id) {
                    foreach ($db_chauffeurs as $c) {
                        $db_voor = schoon_naam($c['voornaam']);
                        $db_achter = schoon_naam($c['achternaam']);
                        if (strlen($db_achter) >= 4 && strpos($csv_schoon, substr($db_achter, 0, 4)) !== false) {
                            if (!empty($db_voor) && strpos($csv_schoon, substr($db_voor, 0, 2)) !== false) {
                                $chauffeur_id = $c['id']; break;
                            }
                        }
                    }
                }

                if (!$chauffeur_id) {
                    foreach ($db_chauffeurs as $c) {
                        $db_voor = schoon_naam($c['voornaam']);
                        if (strlen($db_voor) > 5 && strpos($csv_schoon, $db_voor) !== false) {
                            $chauffeur_id = $c['id']; break;
                        }
                    }
                }
                
                if ($chauffeur_id) {
                    foreach ($regels as $rij) {
                        $type_vervoer = $rij['is_ov'] ? 'OV' : 'Groepsvervoer';
                        $stmt_insert->execute([
                            $chauffeur_id, $rij['echte_datum'], $type_vervoer, $rij['netto_uren'],
                            $rij['toeslag_emmers']['ongeregeld_nacht'], $rij['toeslag_emmers']['ongeregeld_zat'], $rij['toeslag_emmers']['ongeregeld_zon'],
                            $rij['toeslag_emmers']['ov_nacht'], $rij['toeslag_emmers']['ov_zat'], $rij['toeslag_emmers']['ov_zon'],
                            $rij['onderbreking_aantal']
                        ]);
                        $aantal_opgeslagen++;
                    }
                } else {
                    $niet_gevonden[] = htmlspecialchars($chauffeur_naam);
                }
            }
            unset($_SESSION['import_klaar_voor_opslag']);
            if ($aantal_opgeslagen > 0) {
                $melding = "<div class='alert alert-success' style='font-size: 18px;'>🎉 <strong>Succes!</strong> Er zijn " . $aantal_opgeslagen . " werkdagen veilig in de database opgeslagen.</div>";
            }
            if (count($niet_gevonden) > 0) {
                $melding .= "<div class='alert alert-warning'>⚠️ <strong>Let op:</strong> De volgende chauffeurs staan wél in de Excel, maar niet in de database. Hun uren zijn overgeslagen: <strong>" . implode(", ", array_unique($niet_gevonden)) . "</strong></div>";
            }
        } catch (PDOException $e) {
            $melding = "<div class='alert alert-danger'>❌ Fout bij opslaan in database: " . $e->getMessage() . "</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'annuleren') {
    unset($_SESSION['import_klaar_voor_opslag']);
    $melding = "<div class='alert alert-warning'>⚠️ Import geannuleerd. Je kunt een nieuw bestand kiezen.</div>";
}
?>

<style>
    .import-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .loon-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
    .loon-table th, .loon-table td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
    .loon-table th { background: #003366; color: white; position: sticky; top: 0; z-index: 10;}
    .loon-table tr:nth-child(even) { background-color: #f9f9f9; }
    .btn-upload { background: #003366; color: white; padding: 12px 25px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 15px; }
    .btn-upload:hover { background: #002244; }
    .btn-save { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 18px; width: 100%; margin-top: 20px; box-shadow: 0 4px 6px rgba(40,167,69,0.3); transition: 0.2s; }
    .btn-save:hover { background: #218838; transform: translateY(-2px); }
    .btn-cancel { background: none; border: none; color: #dc3545; text-decoration: underline; cursor: pointer; display: block; width: 100%; text-align: center; margin-top: 15px; font-size: 14px; }
    .back-link { display: inline-block; margin-bottom: 15px; color: #555; text-decoration: none; font-weight: 600; }
    .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .chauffeur-sectie { margin-top: 30px; border-left: 4px solid #003366; padding-left: 15px; }
    .highlight-col { background-color: #e8f4f8; font-weight: bold; text-align: right; border-left: 2px solid #003366; }
    .badge-ov { background: #6f42c1; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
    .toeslag-label { display: inline-block; background: #e2e8f0; padding: 2px 5px; border-radius: 3px; font-size: 11px; color: #333; margin-bottom: 2px; }
</style>

<div class="container" style="max-width: 1400px; margin: auto; padding: 20px;">
    <a href="loonadministratie.php" class="back-link">&larr; Terug naar Loonadministratie</a>
    
    <div class="import-container">
        <h2 style="color: #003366; margin-top: 0;">📥 Uren Importeren & Toeslagen Berekenen</h2>
        <?php echo $melding; ?>
        <?php if (!isset($_SESSION['import_klaar_voor_opslag']) || count($_SESSION['import_klaar_voor_opslag']) == 0): ?>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 20px; background: #f4f7f6; padding: 25px; border-radius: 6px; border: 1px dashed #ccc;">
                <input type="hidden" name="actie" value="scannen">
                <input type="file" name="csv_bestand" accept=".csv" required style="background: white; padding: 10px; width: 100%; max-width: 400px;">
                <br>
                <button type="submit" class="btn-upload"><i class="fas fa-search"></i> Stap 1: Bestand Scannen (Nog niet opslaan)</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['import_klaar_voor_opslag']) && count($_SESSION['import_klaar_voor_opslag']) > 0): ?>
    <div class="import-container" style="border: 2px solid #28a745;">
        <h3 style="color: #28a745;">🔍 Controleer de uren voor het opslaan</h3>
        
        <?php foreach ($_SESSION['import_klaar_voor_opslag'] as $chauffeur_naam => $regels): ?>
            <div class="chauffeur-sectie">
                <h4 style="color: #003366; margin-bottom: 5px;">👤 <?php echo htmlspecialchars($chauffeur_naam); ?></h4>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc;">
                    <table class="loon-table" style="margin-top: 0;">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Type</th>
                                <th>Blok A</th>
                                <th>Blok B</th>
                                <th>Blok C</th>
                                <th class="highlight-col">Totaal Uren</th>
                                <th class="highlight-col" style="text-align: left;">Toeslagen</th>
                                <th class="highlight-col">Onderbreking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regels as $rij): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($rij['echte_datum'])); ?></td>
                                    <td><?php echo $rij['is_ov'] ? '<span class="badge-ov">OV</span>' : 'Normaal'; ?></td>
                                    <td><?php echo !empty($rij['van_a']) ? htmlspecialchars($rij['van_a'] . ' - ' . $rij['tot_a']) : '-'; ?></td>
                                    <td><?php echo !empty($rij['van_b']) ? htmlspecialchars($rij['van_b'] . ' - ' . $rij['tot_b']) : '-'; ?></td>
                                    <td><?php echo !empty($rij['van_c']) ? htmlspecialchars($rij['van_c'] . ' - ' . $rij['tot_c']) : '-'; ?></td>
                                    
                                    <td class="highlight-col"><?php echo number_format($rij['netto_uren'], 2, ',', ''); ?> u</td>
                                    <td class="highlight-col" style="text-align: left; font-weight: normal;">
                                        <?php 
                                        $heeft_toeslag = false;
                                        $labels = ['ongeregeld_nacht' => 'Nacht', 'ongeregeld_zat' => 'Zaterdag', 'ongeregeld_zon' => 'Zondag', 'ov_nacht' => 'Nacht (OV)', 'ov_zat' => 'Zaterdag (OV)', 'ov_zon' => 'Zondag (OV)'];
                                        foreach ($rij['toeslag_emmers'] as $sleutel => $uren) {
                                            if ($uren > 0) {
                                                echo "<div class='toeslag-label'>" . number_format($uren, 2, ',', '') . " u &rarr; " . $labels[$sleutel] . "</div><br>";
                                                $heeft_toeslag = true;
                                            }
                                        }
                                        if (!$heeft_toeslag) echo "<span style='color: #999;'>-</span>";
                                        ?>
                                    </td>
                                    <td class="highlight-col" style="color: #d97706;">
                                        <?php echo ($rij['onderbreking_aantal'] > 0) ? '<strong>' . $rij['onderbreking_aantal'] . 'x</strong>' : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        
        <form method="POST" style="margin-top: 30px;">
            <input type="hidden" name="actie" value="opslaan">
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Stap 2: Alles klopt! Sla op in de Database</button>
        </form>
        <form method="POST">
            <input type="hidden" name="actie" value="annuleren">
            <button type="submit" class="btn-cancel">Annuleren en opnieuw beginnen</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>