<?php
// Bestand: chauffeur/dienst_afronden.php
// VERSIE: Chauffeurs App 3.7 - (Kasopmaak en Uren bevestiging - ZONDER km stand)

session_start();

if (!isset($_SESSION['chauffeur_id'])) {
    header("Location: index.php");
    exit;
}

require '../beheer/includes/db.php';

$chauffeur_id = (int) $_SESSION['chauffeur_id'];

if (!isset($_SESSION['chauffeur_tenant_id'])) {
    $stmt_bt = $pdo->prepare('SELECT tenant_id FROM chauffeurs WHERE id = ? AND archief = 0 LIMIT 1');
    $stmt_bt->execute([$chauffeur_id]);
    $row_bt = $stmt_bt->fetch(PDO::FETCH_ASSOC);
    if (!$row_bt || (int) $row_bt['tenant_id'] < 1) {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    $_SESSION['chauffeur_tenant_id'] = (int) $row_bt['tenant_id'];
}

$tenantId = (int) $_SESSION['chauffeur_tenant_id'];

$stmt_chk = $pdo->prepare('SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? AND archief = 0 LIMIT 1');
$stmt_chk->execute([$chauffeur_id, $tenantId]);
if (!$stmt_chk->fetch()) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ==========================================
// 1. ZOEK ACTIEVE DIENST
// ==========================================
$stmt_dienst = $pdo->prepare("SELECT * FROM diensten WHERE chauffeur_id = ? AND tenant_id = ? AND status = 'actief' ORDER BY id DESC LIMIT 1");
$stmt_dienst->execute([$chauffeur_id, $tenantId]);
$actieve_dienst = $stmt_dienst->fetch();

if (!$actieve_dienst) {
    header("Location: dashboard.php");
    exit;
}
$dienst_id = $actieve_dienst['id'];

// ==========================================
// 2. DATA BEREKENEN VOOR HET OVERZICHT
// ==========================================
$stmt_ritten = $pdo->prepare('SELECT id FROM ritten WHERE dienst_id = ? AND tenant_id = ?');
$stmt_ritten->execute([$dienst_id, $tenantId]);
$aantal_ritten = $stmt_ritten->rowCount();

$stmt_pauze_tijd = $pdo->prepare('SELECT SUM(TIMESTAMPDIFF(MINUTE, dp.start_pauze, IFNULL(dp.eind_pauze, NOW()))) as pauze_minuten FROM dienst_pauzes dp INNER JOIN diensten d ON d.id = dp.dienst_id AND d.tenant_id = ? AND d.chauffeur_id = ? WHERE dp.dienst_id = ?');
$stmt_pauze_tijd->execute([$tenantId, $chauffeur_id, $dienst_id]);
$totale_pauze = $stmt_pauze_tijd->fetchColumn() ?: 0;

$stmt_kas = $pdo->prepare("SELECT SUM(betaald_bedrag) FROM ritten WHERE dienst_id = ? AND tenant_id = ? AND betaalwijze = 'Contant'");
$stmt_kas->execute([$dienst_id, $tenantId]);
$totaal_contant = $stmt_kas->fetchColumn() ?: 0;

$start_tijd_weergave = date('H:i', strtotime($actieve_dienst['start_tijd']));
$nu_tijd_weergave = date('H:i');

$start_dt = new DateTime($actieve_dienst['start_tijd']);
$nu_dt = new DateTime();
$bruto_minuten = ($nu_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
$netto_minuten = max(0, $bruto_minuten - $totale_pauze);

$gewerkte_uren = floor($netto_minuten / 60);
$gewerkte_min_rest = round($netto_minuten % 60);

// ==========================================
// 3. DIENST DEFINITIEF AFSLUITEN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $notities = trim($_POST['notities']);

    try {
        $stmt_pauze = $pdo->prepare('
            UPDATE dienst_pauzes dp
            INNER JOIN diensten d ON d.id = dp.dienst_id AND d.chauffeur_id = ? AND d.tenant_id = ?
            SET dp.eind_pauze = NOW()
            WHERE dp.dienst_id = ? AND dp.eind_pauze IS NULL
        ');
        $stmt_pauze->execute([$chauffeur_id, $tenantId, $dienst_id]);

        // Query geüpdatet: km_eind is eruit gehaald
        $stmt_update = $pdo->prepare('UPDATE diensten SET eind_tijd = NOW(), notities = ?, status = ? WHERE id = ? AND chauffeur_id = ? AND tenant_id = ?');
        $stmt_update->execute([$notities, 'afgerond', $dienst_id, $chauffeur_id, $tenantId]);

        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        die("Fout bij afsluiten: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dienst Afronden - Berkhout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #2c3e50; }
        .app-header { background: #dc3545; color: white; padding: 15px 20px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .back-btn { color: white; text-decoration: none; font-size: 20px; margin-right: 15px; }
        .app-header h1 { margin: 0; font-size: 18px; font-weight: 700; }
        .content { padding: 20px; }
        
        .card { background: white; border-radius: 16px; padding: 25px 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        
        .summary-box { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 20px; border: 1px solid #e9ecef; }
        .summary-regel { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #dee2e6; font-size: 16px; }
        .summary-regel:last-child { border-bottom: none; }
        .summary-regel span { font-weight: bold; color: #003366; }
        
        .kas-box { background: #e8f5e9; border: 2px solid #c3e6cb; border-radius: 12px; padding: 15px; text-align: center; margin-bottom: 20px; }
        .kas-box h4 { margin: 0 0 5px 0; color: #155724; }
        .kas-box .bedrag { font-size: 28px; font-weight: 900; color: #28a745; margin: 0; }
        
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #444; font-size: 14px; }
        input[type="number"], textarea { width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fafafa; }
        input:focus, textarea:focus { border-color: #dc3545; outline: none; background: #fff; }
        
        .opslaan-btn { background: #dc3545; color: white; border: none; padding: 18px; width: 100%; border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; margin-top: 25px; transition: 0.2s; text-transform: uppercase; }
        .opslaan-btn:active { transform: scale(0.98); background: #c82333; }
    </style>
</head>
<body>

    <div class="app-header">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>Dienst Afsluiten</h1>
    </div>

    <div class="content">
        
        <div class="card">
            <h3 style="margin-top: 0; color: #003366; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;"><i class="fas fa-clipboard-check"></i> Controleer je dienst</h3>
            
            <p style="font-size: 14px; color: #666;">Controleer of onderstaande gegevens kloppen. Zo niet, ga dan terug om ritten nog aan te passen.</p>

            <div class="kas-box">
                <h4><i class="fas fa-euro-sign"></i> Contant af te dragen</h4>
                <p class="bedrag">&euro; <?php echo number_format((float)$totaal_contant, 2, ',', '.'); ?></p>
            </div>

            <div class="summary-box">
                <div class="summary-regel">Starttijd: <span><?php echo $start_tijd_weergave; ?></span></div>
                <div class="summary-regel">Pauze: <span><?php echo $totale_pauze; ?> min</span></div>
                <div class="summary-regel" style="color: #28a745; border-top: 2px solid #dee2e6; margin-top: 5px;">Netto gewerkt: <span style="font-size: 18px;"><?php echo $gewerkte_uren; ?>u <?php echo $gewerkte_min_rest; ?>m</span></div>
                <div class="summary-regel">Aantal ritten: <span><?php echo $aantal_ritten; ?> ritten</span></div>
            </div>

            <form method="POST">
                <label>Algemene notities voor kantoor (Optioneel):</label>
                <textarea name="notities" rows="3" placeholder="Bijv: Rechter spiegel hapert een beetje, of file gehad..."></textarea>
                
                <button type="submit" class="opslaan-btn"><i class="fas fa-lock"></i> Ja, Sluit Dienst Af</button>
            </form>
        </div>

    </div>
</body>
</html>