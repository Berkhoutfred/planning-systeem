<?php
// Bestand: beheer/vaste_ritten/radeland_mail.php
// VERSIE: 1.0 - Afwijkingen (Vrijdag-mail) Verwerken INCLUSIEF Auto-Schoonmaak

include '../../beveiliging.php';
require '../includes/db.php';

// --- 1. DE AUTOMATISCHE STOFZUIGER ---
// Verwijdert alle mutaties ouder dan 14 dagen
$pdo->exec("DELETE FROM vr_mutaties WHERE datum < DATE_SUB(CURDATE(), INTERVAL 14 DAY)");

// --- 2. FORMULIER OPSLAAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opslaan'])) {
    $datum_opslaan = $_POST['gekozen_datum'];
    $route_opslaan = $_POST['gekozen_route'];
    
    // We bereiden de database voor
    $stmt_insert = $pdo->prepare("INSERT INTO vr_mutaties (passagier_id, datum, rit_type, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
    $stmt_delete = $pdo->prepare("DELETE FROM vr_mutaties WHERE passagier_id = ? AND datum = ? AND rit_type = ?");
    
    // Loop door alle passagiers op het formulier
    if (isset($_POST['passagier'])) {
        foreach ($_POST['passagier'] as $p_id => $data) {
            $base_h = $data['base_h'];
            $base_t = $data['base_t'];
            $ingevuld_h = isset($data['heen']) ? $data['heen'] : 0;
            $ingevuld_t = isset($data['terug']) ? $data['terug'] : 0;
            
            // Heenrit vergelijken met stamrooster
            if ($ingevuld_h != $base_h) {
                $status = ($ingevuld_h == 1) ? 'extra_aanwezig' : 'afwezig';
                $stmt_insert->execute([$p_id, $datum_opslaan, 'heen', $status, $status]);
            } else {
                $stmt_delete->execute([$p_id, $datum_opslaan, 'heen']);
            }
            
            // Terugrit vergelijken met stamrooster
            if ($ingevuld_t != $base_t) {
                $status = ($ingevuld_t == 1) ? 'extra_aanwezig' : 'afwezig';
                $stmt_insert->execute([$p_id, $datum_opslaan, 'terug', $status, $status]);
            } else {
                $stmt_delete->execute([$p_id, $datum_opslaan, 'terug']);
            }
        }
    }
    
    header("Location: radeland_mail.php?route=$route_opslaan&datum=$datum_opslaan&msg=opgeslagen");
    exit;
}

// --- 3. DATA OPHALEN VOOR SCHERM ---
$routes = $pdo->query("SELECT * FROM vr_routes ORDER BY id ASC")->fetchAll();
$gekozen_route = isset($_GET['route']) ? (int)$_GET['route'] : (isset($routes[0]) ? $routes[0]['id'] : 0);

// Bepaal de datum (Standaard de datum van VANDAAG)
$gekozen_datum = isset($_GET['datum']) ? $_GET['datum'] : date('Y-m-d');
$dag_nr = date('N', strtotime($gekozen_datum)); // 1=Ma, 2=Di, enz.

// Vertaal dagnummer naar kolom in de database
$dag_map = [1 => 'ma', 2 => 'di', 3 => 'wo', 4 => 'do', 5 => 'vr', 6 => 'za', 7 => 'zo'];
$db_dag = isset($dag_map[$dag_nr]) ? $dag_map[$dag_nr] : '';

// Haal passagiers op
$passagiers = [];
if ($gekozen_route > 0) {
    $stmt_pass = $pdo->prepare("SELECT * FROM vr_passagiers WHERE route_id = ? AND actief = 1 ORDER BY naam ASC");
    $stmt_pass->execute([$gekozen_route]);
    $passagiers = $stmt_pass->fetchAll();
}

// Haal mutaties op voor deze specifieke datum
$mutaties = [];
$stmt_mut = $pdo->prepare("SELECT passagier_id, rit_type, status FROM vr_mutaties WHERE datum = ?");
$stmt_mut->execute([$gekozen_datum]);
while ($row = $stmt_mut->fetch()) {
    $mutaties[$row['passagier_id']][$row['rit_type']] = $row['status'];
}

include '../includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1000px; margin: auto; padding: 20px; }
    
    .top-bar { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .filter-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
    
    .form-control { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; font-weight: bold; color: #003366; }
    .btn-blue { background: #003366; color: white; padding: 12px 25px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px; }
    
    .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    table.rooster { width: 100%; border-collapse: collapse; font-size: 15px; }
    table.rooster th, table.rooster td { border-bottom: 1px solid #eee; padding: 15px 10px; text-align: left; vertical-align: middle; }
    table.rooster th { background: #f8f9fa; color: #003366; font-size: 13px; text-transform: uppercase; }
    
    /* Toggle Switch Stijlen */
    .status-select { padding: 8px; border-radius: 4px; font-weight: bold; border: 2px solid #ddd; cursor: pointer; outline: none; width: 130px; }
    .status-select.aanwezig { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .status-select.afwezig { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    
    .btn-save-big { background: #28a745; color: white; padding: 15px; width: 100%; border: none; border-radius: 6px; font-weight: bold; font-size: 18px; cursor: pointer; margin-top: 20px; transition: 0.2s; box-shadow: 0 4px 6px rgba(40,167,69,0.3); }
    .btn-save-big:hover { background: #218838; transform: translateY(-2px); }
    
    .alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; text-align: center; font-size: 16px; }
</style>

<div class="container">
    
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'opgeslagen'): ?>
        <div class="alert-success">✅ Dagplanning voor <?= date('d-m-Y', strtotime($gekozen_datum)) ?> succesvol opgeslagen!</div>
    <?php endif; ?>

    <div class="top-bar">
        <h1 style="margin:0 0 15px 0; color:#003366;"><i class="fas fa-envelope-open-text"></i> Radeland / Eerbeek Mail Verwerken</h1>
        
        <form method="GET" class="filter-grid">
            <div>
                <label style="font-size: 13px; font-weight: bold; color: #666;">Kies de Route:</label>
                <select name="route" class="form-control" onchange="this.form.submit()">
                    <?php foreach($routes as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($r['id'] == $gekozen_route) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['naam']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size: 13px; font-weight: bold; color: #666;">Kies de Datum:</label>
                <input type="date" name="datum" class="form-control" value="<?= $gekozen_datum ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <?php if($dag_nr >= 6): ?>
        <div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 6px; text-align: center; font-weight: bold;">
            ⚠️ Je hebt een dag in het weekend geselecteerd. Dagbesteding rijdt normaal gesproken alleen doordeweeks.
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="opslaan" value="1">
            <input type="hidden" name="gekozen_datum" value="<?= $gekozen_datum ?>">
            <input type="hidden" name="gekozen_route" value="<?= $gekozen_route ?>">
            
            <div class="card">
                <table class="rooster">
                    <thead>
                        <tr>
                            <th>Naam Passagier</th>
                            <th style="width:160px;">Heenrit (Ochtend)</th>
                            <th style="width:160px;">Terugrit (Middag)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach($passagiers as $p): 
                            $p_id = $p['id'];
                            
                            // Haal de standaard aan/afwezigheid op uit het stamrooster voor déze dag
                            $base_h = $p[$db_dag.'_h'];
                            $base_t = $p[$db_dag.'_t'];
                            
                            // Kijk of er voor vandaag een mutatie (kruisje) in de database staat
                            $mut_h = isset($mutaties[$p_id]['heen']) ? $mutaties[$p_id]['heen'] : '';
                            $mut_t = isset($mutaties[$p_id]['terug']) ? $mutaties[$p_id]['terug'] : '';
                            
                            // Wat is de uiteindelijke status op DIT moment?
                            $status_h = $base_h;
                            if ($mut_h == 'afwezig') $status_h = 0;
                            if ($mut_h == 'extra_aanwezig') $status_h = 1;
                            
                            $status_t = $base_t;
                            if ($mut_t == 'afwezig') $status_t = 0;
                            if ($mut_t == 'extra_aanwezig') $status_t = 1;
                        ?>
                            <tr>
                                <td style="font-weight:bold; font-size:16px;">
                                    <?= htmlspecialchars($p['naam']) ?>
                                    <div style="font-size:12px; color:#999; font-weight:normal;">Opstap: <?= htmlspecialchars($p['opstap_plek']) ?></div>
                                    
                                    <input type="hidden" name="passagier[<?= $p_id ?>][base_h]" value="<?= $base_h ?>">
                                    <input type="hidden" name="passagier[<?= $p_id ?>][base_t]" value="<?= $base_t ?>">
                                </td>
                                
                                <td>
                                    <select name="passagier[<?= $p_id ?>][heen]" class="status-select <?= $status_h ? 'aanwezig' : 'afwezig' ?>" onchange="kleurUpdate(this)">
                                        <option value="1" <?= $status_h ? 'selected' : '' ?>>✅ Aanwezig</option>
                                        <option value="0" <?= !$status_h ? 'selected' : '' ?>>❌ AFWEZIG</option>
                                    </select>
                                </td>
                                
                                <td>
                                    <select name="passagier[<?= $p_id ?>][terug]" class="status-select <?= $status_t ? 'aanwezig' : 'afwezig' ?>" onchange="kleurUpdate(this)">
                                        <option value="1" <?= $status_t ? 'selected' : '' ?>>✅ Aanwezig</option>
                                        <option value="0" <?= !$status_t ? 'selected' : '' ?>>❌ AFWEZIG</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn-save-big"><i class="fas fa-save"></i> Afmeldingen Opslaan</button>
        </form>
    <?php endif; ?>

</div>

<script>
    // Een klein scriptje zodat de knoppen direct live rood of groen worden als je erop klikt!
    function kleurUpdate(selectBox) {
        if(selectBox.value == "1") {
            selectBox.className = "status-select aanwezig";
        } else {
            selectBox.className = "status-select afwezig";
        }
    }
</script>

<?php include '../includes/footer.php'; ?>