<?php
// Bestand: beheer/uren_controle.php
// VERSIE: 1.1 - De "Digitale Excel" met Slimme Verwijder-logica

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

// --- DE REKENMOTOR ---
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

// --- DATA OPSLAAN & BEREKENEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan_uren'])) {
    $chauffeur_id = intval($_POST['chauffeur_id']);
    $maand = intval($_POST['maand']);
    $jaar = intval($_POST['jaar']);

    $chkCh = $pdo->prepare('SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1');
    $chkCh->execute([$chauffeur_id, $tenantId]);
    if (!$chkCh->fetchColumn()) {
        header('Location: uren_controle.php');
        exit;
    }
    
    $stmt_insert = $pdo->prepare("INSERT INTO loon_uren 
        (chauffeur_id, datum, type_vervoer, van_a, tot_a, van_b, tot_b, van_c, tot_c, 
         uren_basis, toeslag_avond, toeslag_weekend, toeslag_zon_feest, 
         toeslag_ov_avond_nacht, toeslag_ov_zaterdag, toeslag_ov_zondag, onderbreking_aantal) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        type_vervoer=VALUES(type_vervoer), van_a=VALUES(van_a), tot_a=VALUES(tot_a), 
        van_b=VALUES(van_b), tot_b=VALUES(tot_b), van_c=VALUES(van_c), tot_c=VALUES(tot_c),
        uren_basis=VALUES(uren_basis), toeslag_avond=VALUES(toeslag_avond), toeslag_weekend=VALUES(toeslag_weekend), 
        toeslag_zon_feest=VALUES(toeslag_zon_feest), toeslag_ov_avond_nacht=VALUES(toeslag_ov_avond_nacht), 
        toeslag_ov_zaterdag=VALUES(toeslag_ov_zaterdag), toeslag_ov_zondag=VALUES(toeslag_ov_zondag), 
        onderbreking_aantal=VALUES(onderbreking_aantal)");

    foreach ($_POST['dagen'] as $dag => $data) {
        $echte_datum = sprintf("%04d-%02d-%02d", $jaar, $maand, $dag);
        
        $van_a = trim($data['van_a'] ?? ''); $tot_a = trim($data['tot_a'] ?? '');
        $van_b = trim($data['van_b'] ?? ''); $tot_b = trim($data['tot_b'] ?? '');
        $van_c = trim($data['van_c'] ?? ''); $tot_c = trim($data['tot_c'] ?? '');
        $is_ov = ($data['type'] === 'OV');
        $type_vervoer = $is_ov ? 'OV' : 'Groepsvervoer';

        // Tijd berekening logic (Aangepast zodat 00:00 naar 00:00 gewoon NUL uur is)
        $uren_a = 0; $uren_b = 0; $uren_c = 0;
        $ts_a_start = 0; $ts_a_eind = 0;
        $ts_b_start = 0; $ts_b_eind = 0;
        $ts_c_start = 0; $ts_c_eind = 0;
        
        if (!empty($van_a) && !empty($tot_a)) {
            $ts_a_start = strtotime("$echte_datum $van_a");
            $ts_a_eind = strtotime("$echte_datum $tot_a");
            if ($ts_a_eind < $ts_a_start) $ts_a_eind += 86400; // Alleen als eind echt KLEINER is dan start
            $uren_a = ($ts_a_eind - $ts_a_start) / 3600;
        }
        if (!empty($van_b) && !empty($tot_b)) {
            $ts_b_start = strtotime("$echte_datum $van_b");
            if ($ts_a_eind > 0 && $ts_b_start < $ts_a_eind) { $ts_b_start += 86400; }
            $ts_b_eind = strtotime("$echte_datum $tot_b");
            while ($ts_b_eind < $ts_b_start) { $ts_b_eind += 86400; }
            $uren_b = ($ts_b_eind - $ts_b_start) / 3600;
        }
        if (!empty($van_c) && !empty($tot_c)) {
            $ts_c_start = strtotime("$echte_datum $van_c");
            $vorige_eind = $ts_b_eind > 0 ? $ts_b_eind : $ts_a_eind;
            if ($vorige_eind > 0 && $ts_c_start < $vorige_eind) { $ts_c_start += 86400; }
            $ts_c_eind = strtotime("$echte_datum $tot_c");
            while ($ts_c_eind < $ts_c_start) { $ts_c_eind += 86400; }
            $uren_c = ($ts_c_eind - $ts_c_start) / 3600;
        }
        
        $netto_uren = $uren_a + $uren_b + $uren_c;
        
        // DE MAGISCHE PRULLENBAK:
        // Als je alles wist, of overal 00:00 typt, is de tijd 0. Dan gooien we hem uit de DB!
        if($netto_uren <= 0) {
            $pdo->prepare('DELETE lu FROM loon_uren lu INNER JOIN chauffeurs c ON c.id = lu.chauffeur_id AND c.tenant_id = ? WHERE lu.chauffeur_id = ? AND lu.datum = ?')
                ->execute([$tenantId, $chauffeur_id, $echte_datum]);
            continue; 
        }
        
        $onderbreking_aantal = 0;
        if ($ts_a_eind > 0 && $ts_b_start > 0 && ($ts_b_start - $ts_a_eind) > 3540) { $onderbreking_aantal++; }
        if ($ts_b_eind > 0 && $ts_c_start > 0 && ($ts_c_start - $ts_b_eind) > 3540) { $onderbreking_aantal++; }
        
        $emmers_a = bereken_rit_toeslag_uren($ts_a_start, $ts_a_eind, $is_ov);
        $emmers_b = bereken_rit_toeslag_uren($ts_b_start, $ts_b_eind, $is_ov);
        $emmers_c = bereken_rit_toeslag_uren($ts_c_start, $ts_c_eind, $is_ov);
        
        $totaal_emmers = [];
        foreach ($emmers_a as $key => $uren) { 
            $totaal_emmers[$key] = $uren + $emmers_b[$key] + $emmers_c[$key]; 
        }

        $stmt_insert->execute([
            $chauffeur_id, $echte_datum, $type_vervoer, $van_a, $tot_a, $van_b, $tot_b, $van_c, $tot_c,
            round($netto_uren, 2),
            $totaal_emmers['ongeregeld_nacht'],
            $totaal_emmers['ongeregeld_zat'],
            $totaal_emmers['ongeregeld_zon'],
            $totaal_emmers['ov_nacht'],
            $totaal_emmers['ov_zat'],
            $totaal_emmers['ov_zon'],
            $onderbreking_aantal
        ]);
    }
    
    header("Location: uren_controle.php?chauffeur_id=$chauffeur_id&maand=$maand&jaar=$jaar&msg=opgeslagen");
    exit;
}

include 'includes/header.php';

// --- NAVIGATIE / SELECTIE ---
$stmtCh = $pdo->prepare('SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? ORDER BY voornaam');
$stmtCh->execute([$tenantId]);
$chauffeurs = $stmtCh->fetchAll();

$huidigeMaand = date('n');
$huidigJaar = date('Y');

$geselecteerdeChauffeur = isset($_GET['chauffeur_id']) ? intval($_GET['chauffeur_id']) : 0;
$maand = isset($_GET['maand']) ? intval($_GET['maand']) : $huidigeMaand;
$jaar = isset($_GET['jaar']) ? intval($_GET['jaar']) : $huidigJaar;

$dagenInMaand = cal_days_in_month(CAL_GREGORIAN, $maand, $jaar);
$maanden = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];

$bestaande_uren = [];
if ($geselecteerdeChauffeur > 0) {
    $stmt = $pdo->prepare('
        SELECT lu.*
        FROM loon_uren lu
        INNER JOIN chauffeurs c ON c.id = lu.chauffeur_id AND c.tenant_id = ?
        WHERE lu.chauffeur_id = ? AND MONTH(lu.datum) = ? AND YEAR(lu.datum) = ?
    ');
    $stmt->execute([$tenantId, $geselecteerdeChauffeur, $maand, $jaar]);
    while($rij = $stmt->fetch()) {
        $dag = (int)date('d', strtotime($rij['datum']));
        $bestaande_uren[$dag] = $rij;
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1400px; margin: auto; padding: 20px; }
    .top-bar { background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; }
    
    .filter-form { display: flex; gap: 15px; align-items: center; }
    .form-control { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
    .btn-blue { background: #003366; color: white; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    
    .excel-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
    .excel-table th { background: #003366; color: white; padding: 12px 10px; font-size: 13px; text-transform: uppercase; border: 1px solid #002244; position: sticky; top: 0; z-index: 10; }
    .excel-table td { padding: 5px; border: 1px solid #e0e0e0; vertical-align: middle; }
    .excel-table tr:hover { background-color: #f1f9ff; }
    .excel-table tr:nth-child(even) { background-color: #fafafa; }
    
    .weekend-row { background-color: #fff3cd !important; }
    .weekend-row:hover { background-color: #ffeeba !important; }
    
    .time-input { width: 65px; text-align: center; border: 1px solid #ccc; padding: 6px; border-radius: 3px; font-family: monospace; font-size: 14px; background: #fff; }
    .time-input:focus { border-color: #003366; outline: none; box-shadow: 0 0 3px rgba(0,51,102,0.3); }
    .type-select { padding: 6px; border: 1px solid #ccc; border-radius: 3px; background: #fff; font-size: 13px; }
    
    .btn-save-big { background: #28a745; color: white; padding: 15px; border: none; border-radius: 6px; font-weight: bold; font-size: 18px; width: 100%; cursor: pointer; margin-top: 20px; box-shadow: 0 4px 6px rgba(40,167,69,0.3); transition: 0.2s; }
    .btn-save-big:hover { background: #218838; transform: translateY(-2px); }
</style>

<div class="container">
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'opgeslagen'): ?>
        <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px; border:1px solid #c3e6cb; font-weight:bold; font-size: 16px;">
            <i class="fas fa-check-circle"></i> Uren succesvol berekend en opgeslagen in de loonadministratie!
        </div>
    <?php endif; ?>

    <div class="top-bar">
        <h1 style="margin:0; color:#003366; font-size:22px;"><i class="fas fa-file-excel"></i> Digitale Uren Excel</h1>
        
        <form method="GET" class="filter-form">
            <select name="chauffeur_id" class="form-control" required style="font-weight:bold; border-color:#003366;">
                <option value="">-- Kies Chauffeur --</option>
                <?php foreach($chauffeurs as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $geselecteerdeChauffeur ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="maand" class="form-control">
                <?php foreach($maanden as $mNum => $mNaam): ?>
                    <option value="<?= $mNum ?>" <?= $mNum == $maand ? 'selected' : '' ?>><?= $mNaam ?></option>
                <?php endforeach; ?>
            </select>
            
            <input type="number" name="jaar" class="form-control" value="<?= $jaar ?>" style="width:80px;">
            
            <button type="submit" class="btn-blue">Laden</button>
        </form>
    </div>

    <?php if($geselecteerdeChauffeur > 0): ?>
        <form method="POST">
            <input type="hidden" name="opslaan_uren" value="1">
            <input type="hidden" name="chauffeur_id" value="<?= $geselecteerdeChauffeur ?>">
            <input type="hidden" name="maand" value="<?= $maand ?>">
            <input type="hidden" name="jaar" value="<?= $jaar ?>">
            
            <div style="overflow-x: auto;">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Dag</th>
                            <th style="width: 90px;">Datum</th>
                            <th style="width: 100px;">Soort (OV)</th>
                            <th style="text-align:center; background:#004080;">VAN (A)</th>
                            <th style="text-align:center; background:#004080;">TOT (A)</th>
                            <th style="text-align:center; background:#004d99;">VAN (B)</th>
                            <th style="text-align:center; background:#004d99;">TOT (B)</th>
                            <th style="text-align:center; background:#0059b3;">VAN (C)</th>
                            <th style="text-align:center; background:#0059b3;">TOT (C)</th>
                            <th style="text-align:center; background:#28a745;">Totaal Uren</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for($dag = 1; $dag <= $dagenInMaand; $dag++): 
                            $timestamp = mktime(0,0,0, $maand, $dag, $jaar);
                            $dagNaam = date('D', $timestamp);
                            $isWeekend = (date('N', $timestamp) >= 6);
                            
                            $b = $bestaande_uren[$dag] ?? null;
                            $vanA = $b['van_a'] ?? ''; $totA = $b['tot_a'] ?? '';
                            $vanB = $b['van_b'] ?? ''; $totB = $b['tot_b'] ?? '';
                            $vanC = $b['van_c'] ?? ''; $totC = $b['tot_c'] ?? '';
                            $type = $b['type_vervoer'] ?? 'Groepsvervoer';
                            $totaal = $b['uren_basis'] ?? 0;
                        ?>
                        <tr class="<?= $isWeekend ? 'weekend-row' : '' ?>">
                            <td style="font-weight:bold; color:#555; text-align:center;"><?= str_replace(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], ['Ma','Di','Wo','Do','Vr','Za','Zo'], $dagNaam) ?></td>
                            <td style="text-align:center; color:#333;"><?= sprintf("%02d-%02d-%04d", $dag, $maand, $jaar) ?></td>
                            <td>
                                <select name="dagen[<?= $dag ?>][type]" class="type-select">
                                    <option value="Normaal" <?= $type != 'OV' ? 'selected' : '' ?>>Normaal</option>
                                    <option value="OV" <?= $type == 'OV' ? 'selected' : '' ?>>OV</option>
                                </select>
                            </td>
                            
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][van_a]" class="time-input" value="<?= $vanA ?>"></td>
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][tot_a]" class="time-input" value="<?= $totA ?>"></td>
                            
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][van_b]" class="time-input" value="<?= $vanB ?>"></td>
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][tot_b]" class="time-input" value="<?= $totB ?>"></td>
                            
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][van_c]" class="time-input" value="<?= $vanC ?>"></td>
                            <td style="text-align:center;"><input type="time" name="dagen[<?= $dag ?>][tot_c]" class="time-input" value="<?= $totC ?>"></td>
                            
                            <td style="text-align:center; font-weight:bold; color: <?= $totaal > 0 ? '#28a745' : '#ccc' ?>;">
                                <?= $totaal > 0 ? number_format($totaal, 2, ',', '.') . ' u' : '-' ?>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn-save-big"><i class="fas fa-calculator"></i> Uren Berekenen & Opslaan in Loonadministratie</button>
        </form>
    <?php else: ?>
        <div style="background:#fff; padding:50px; text-align:center; border-radius:8px; border:1px solid #ddd; color:#666;">
            <i class="fas fa-hand-pointer" style="font-size:40px; color:#003366; margin-bottom:15px;"></i>
            <h2>Kies een chauffeur om te beginnen</h2>
            <p>Selecteer hierboven een chauffeur en een maand om de uren in te vullen of te bewerken.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>