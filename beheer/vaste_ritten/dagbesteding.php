<?php
// Bestand: beheer/vaste_ritten/dagbesteding.php
// VERSIE: 1.5 - Stamrooster Beheer (Met duidelijke ✅ en ❌ knoppen)

include '../../beveiliging.php';
require '../includes/db.php';

// --- 1. ROUTES AUTOMATISCH AANMAKEN ---
$check_routes = $pdo->query("SELECT COUNT(*) FROM vr_routes")->fetchColumn();
if ($check_routes == 0) {
    $pdo->query("INSERT INTO vr_routes (naam) VALUES ('Route Radeland Lochem'), ('Route Eerbeek')");
}

// --- 2. PASSAGIER VERWIJDEREN (Inactief maken) ---
if (isset($_GET['verwijder'])) {
    $verwijder_id = (int)$_GET['verwijder'];
    $route_terug = isset($_GET['route']) ? (int)$_GET['route'] : 1;
    
    $stmt = $pdo->prepare("UPDATE vr_passagiers SET actief = 0 WHERE id = ?");
    $stmt->execute([$verwijder_id]);
    
    header("Location: dagbesteding.php?route=$route_terug&msg=verwijderd");
    exit;
}

// --- 3. PASSAGIER TOEVOEGEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'toevoegen') {
    $route_id = $_POST['route_id'];
    $naam = trim($_POST['naam']);
    $opstap = trim($_POST['opstap_plek']);
    
    // Standaard iedereen op ma t/m vr aanwezig zetten (1) bij het toevoegen
    if (!empty($naam)) {
        $stmt = $pdo->prepare("INSERT INTO vr_passagiers (route_id, naam, opstap_plek, ma_h, ma_t, di_h, di_t, wo_h, wo_t, do_h, do_t, vr_h, vr_t) VALUES (?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)");
        $stmt->execute([$route_id, $naam, $opstap]);
        header("Location: dagbesteding.php?route=$route_id&msg=toegevoegd");
        exit;
    }
}

// --- 4. ROOSTER OPSLAAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'rooster_opslaan') {
    $route_terug = $_POST['route_id'];
    
    if (isset($_POST['rooster'])) {
        $stmt_update = $pdo->prepare("UPDATE vr_passagiers SET ma_h=?, ma_t=?, di_h=?, di_t=?, wo_h=?, wo_t=?, do_h=?, do_t=?, vr_h=?, vr_t=? WHERE id=?");
        
        foreach ($_POST['rooster'] as $p_id => $dagen) {
            $ma_h = isset($dagen['ma_h']) ? 1 : 0; $ma_t = isset($dagen['ma_t']) ? 1 : 0;
            $di_h = isset($dagen['di_h']) ? 1 : 0; $di_t = isset($dagen['di_t']) ? 1 : 0;
            $wo_h = isset($dagen['wo_h']) ? 1 : 0; $wo_t = isset($dagen['wo_t']) ? 1 : 0;
            $do_h = isset($dagen['do_h']) ? 1 : 0; $do_t = isset($dagen['do_t']) ? 1 : 0;
            $vr_h = isset($dagen['vr_h']) ? 1 : 0; $vr_t = isset($dagen['vr_t']) ? 1 : 0;
            
            $stmt_update->execute([$ma_h, $ma_t, $di_h, $di_t, $wo_h, $wo_t, $do_h, $do_t, $vr_h, $vr_t, $p_id]);
        }
    }
    
    header("Location: dagbesteding.php?route=$route_terug&msg=rooster_opgeslagen");
    exit;
}

// --- 5. DATA OPHALEN ---
$routes = $pdo->query("SELECT * FROM vr_routes ORDER BY id ASC")->fetchAll();
$gekozen_route = isset($_GET['route']) ? (int)$_GET['route'] : $routes[0]['id'];

$stmt_pass = $pdo->prepare("SELECT * FROM vr_passagiers WHERE route_id = ? AND actief = 1 ORDER BY id ASC");
$stmt_pass->execute([$gekozen_route]);
$passagiers = $stmt_pass->fetchAll();

include '../includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1400px; margin: auto; padding: 20px; }
    
    .top-bar { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; }
    .route-tabs { display: flex; gap: 10px; }
    .route-tab { padding: 10px 20px; background: #e9ecef; color: #333; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.2s; }
    .route-tab.active { background: #003366; color: white; }
    
    .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .card h3 { margin-top: 0; color: #003366; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    
    table.rooster { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.rooster th, table.rooster td { border: 1px solid #ddd; padding: 6px; text-align: center; }
    table.rooster th { background: #f8f9fa; color: #003366; }
    table.rooster td.naam-cel { text-align: left; font-weight: bold; background: #fafafa; }
    table.rooster td.opstap-cel { text-align: left; color: #666; font-size: 13px; }
    
    /* --- DE NIEUWE ✅ en ❌ TOGGLE KNOPPEN --- */
    .toggle-btn { cursor: pointer; display: inline-block; }
    .toggle-btn input { display: none; /* Verberg het originele saaie vinkje */ }
    .toggle-btn .icon {
        display: inline-block; 
        width: 32px; 
        height: 32px; 
        line-height: 32px;
        border-radius: 6px; 
        text-align: center; 
        font-size: 14px;
        background: #f8d7da; /* Rood (Afwezig) */
        border: 1px solid #f5c6cb; 
        color: #721c24;
        transition: 0.2s;
    }
    .toggle-btn .icon::after { content: '❌'; }
    
    .toggle-btn input:checked + .icon {
        background: #d4edda; /* Groen (Aanwezig) */
        border: 1px solid #c3e6cb; 
        color: #155724;
    }
    .toggle-btn input:checked + .icon::after { content: '✅'; }
    /* ----------------------------------------- */
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
    .btn-green { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn-save-big { background: #003366; color: white; padding: 12px 25px; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer; margin-top: 20px; float: right; }
    
    .btn-delete-text { background: #dc3545; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; transition: 0.2s; }
    .btn-delete-text:hover { background: #a71d2a; }
    
    .alert-success { background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb; font-weight: bold; }
</style>

<div class="container">
    
    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] == 'verwijderd'): ?>
            <div class="alert-success">✅ Passagier is verwijderd.</div>
        <?php elseif($_GET['msg'] == 'toegevoegd'): ?>
            <div class="alert-success">✅ Passagier is toegevoegd. Je kunt nu hieronder het rooster bewerken.</div>
        <?php elseif($_GET['msg'] == 'rooster_opgeslagen'): ?>
            <div class="alert-success">✅ Stamrooster is succesvol opgeslagen!</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="top-bar">
        <h1 style="margin:0; color:#003366;">Stamrooster Dagbesteding</h1>
        <div class="route-tabs">
            <?php foreach($routes as $r): ?>
                <a href="?route=<?= $r['id'] ?>" class="route-tab <?= ($r['id'] == $gekozen_route) ? 'active' : '' ?>">
                    <?= htmlspecialchars($r['naam']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h3>Nieuwe Passagier Toevoegen aan deze route</h3>
        <form method="POST">
            <input type="hidden" name="actie" value="toevoegen">
            <input type="hidden" name="route_id" value="<?= $gekozen_route ?>">
            
            <div class="form-grid">
                <div>
                    <label style="font-weight:bold; font-size:13px;">Naam Passagier (bijv. Rick Bouwhuis)</label>
                    <input type="text" name="naam" class="form-control" required>
                </div>
                <div>
                    <label style="font-weight:bold; font-size:13px;">Opstapplek (bijv. PLURYN)</label>
                    <input type="text" name="opstap_plek" class="form-control">
                </div>
                <div>
                    <button type="submit" class="btn-green">Passagier Toevoegen</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="overflow: hidden;">
        <h3>Vaste Weekrooster Aanpassen</h3>
        
        <?php if(count($passagiers) > 0): ?>
            <form method="POST">
                <input type="hidden" name="actie" value="rooster_opslaan">
                <input type="hidden" name="route_id" value="<?= $gekozen_route ?>">
                
                <table class="rooster">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 200px;">Naam</th>
                            <th rowspan="2" style="width: 150px;">Opstapplek</th>
                            <th colspan="2">Maandag</th>
                            <th colspan="2">Dinsdag</th>
                            <th colspan="2">Woensdag</th>
                            <th colspan="2">Donderdag</th>
                            <th colspan="2">Vrijdag</th>
                            <th rowspan="2" style="width: 90px;">Actie</th>
                        </tr>
                        <tr>
                            <th style="font-size: 11px;">Heen</th><th style="font-size: 11px;">Terug</th>
                            <th style="font-size: 11px;">Heen</th><th style="font-size: 11px;">Terug</th>
                            <th style="font-size: 11px;">Heen</th><th style="font-size: 11px;">Terug</th>
                            <th style="font-size: 11px;">Heen</th><th style="font-size: 11px;">Terug</th>
                            <th style="font-size: 11px;">Heen</th><th style="font-size: 11px;">Terug</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($passagiers as $p): $id = $p['id']; ?>
                            <tr>
                                <td class="naam-cel">
                                    <?= htmlspecialchars($p['naam']) ?>
                                    <input type="hidden" name="rooster[<?= $id ?>][id]" value="<?= $id ?>">
                                </td>
                                <td class="opstap-cel"><?= htmlspecialchars($p['opstap_plek']) ?></td>
                                
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][ma_h]" value="1" <?= $p['ma_h'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][ma_t]" value="1" <?= $p['ma_t'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][di_h]" value="1" <?= $p['di_h'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][di_t]" value="1" <?= $p['di_t'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][wo_h]" value="1" <?= $p['wo_h'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][wo_t]" value="1" <?= $p['wo_t'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][do_h]" value="1" <?= $p['do_h'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][do_t]" value="1" <?= $p['do_t'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][vr_h]" value="1" <?= $p['vr_h'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                <td><label class="toggle-btn"><input type="checkbox" name="rooster[<?= $id ?>][vr_t]" value="1" <?= $p['vr_t'] ? 'checked' : '' ?>><span class="icon"></span></label></td>
                                
                                <td>
                                    <a href="?route=<?= $gekozen_route ?>&verwijder=<?= $p['id'] ?>" 
                                       onclick="return confirm('Weet je zeker dat je <?= htmlspecialchars(addslashes($p['naam'])) ?> wilt verwijderen?');" 
                                       class="btn-delete-text">
                                        ❌ Wis
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn-save-big"><i class="fas fa-save"></i> Rooster Opslaan</button>
            </form>
        <?php else: ?>
            <p style="color: #666; font-style: italic;">Nog geen passagiers in deze route.</p>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>