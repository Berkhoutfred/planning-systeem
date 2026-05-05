<?php
// Bestand: chauffeur/rit_bekijken.php
// VERSIE: Chauffeurs App 4.0 - (Minimalistisch, strak getrokken met nieuwe_rit.php)

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

$rit_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['rit_id']) ? (int) $_POST['rit_id'] : 0);

// ==========================================
// 1. AJAX: ONZICHTBAAR MOLLIE AANROEPEN
// ==========================================
if (isset($_POST['ajax_ideal_bedrag'])) {
    $stmt_rv = $pdo->prepare('SELECT id FROM ritten WHERE id = ? AND chauffeur_id = ? AND tenant_id = ? LIMIT 1');
    $stmt_rv->execute([$rit_id, $chauffeur_id, $tenantId]);
    if (!$stmt_rv->fetch()) {
        echo json_encode(['error' => 'Rit niet gevonden of geen toegang.']);
        exit;
    }
    require '../beheer/includes/mollie_connect.php';
    $bedrag = str_replace(',', '.', $_POST['ajax_ideal_bedrag']);
    $mollie_response = maakMollieBetalingAan($bedrag, 'Taxirit #' . $rit_id, $rit_id, [
        'chauffeur_id' => $chauffeur_id,
        'tenant_id' => $tenantId,
        'flow' => 'rit_detail',
    ]);
    if (isset($mollie_response['_links']['checkout']['href'])) {
        echo json_encode(['url' => $mollie_response['_links']['checkout']['href']]);
    } else {
        echo json_encode(['error' => 'Fout bij Mollie. Controleer de API sleutel.']);
    }
    exit; 
}

// ==========================================
// 2. CHECK ACTIEVE DIENST
// ==========================================
$stmt_dienst = $pdo->prepare("SELECT id FROM diensten WHERE chauffeur_id = ? AND tenant_id = ? AND status = 'actief' ORDER BY id DESC LIMIT 1");
$stmt_dienst->execute([$chauffeur_id, $tenantId]);
$actieve_dienst = $stmt_dienst->fetch();

// ==========================================
// 3. FORMULIER OPSLAAN 
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $actieve_dienst && !isset($_POST['ajax_ideal_bedrag'])) {
    
    $km_eind = (isset($_POST['km_eind']) && $_POST['km_eind'] !== '') ? (int)$_POST['km_eind'] : null;
    $km_start = null; 
    $werkelijke_km = null;
    
    $plan_keuze = isset($_POST['plan_keuze']) ? $_POST['plan_keuze'] : 'ja';
    
    $bestaande_notities = $_POST['oorspronkelijke_notities'] ?? '';
    if ($plan_keuze === 'ja') {
        $notities = $bestaande_notities;
        if (empty($notities) || strpos($notities, '⚡ EXTRA RIT') === false) {
           $notities = "✅ Rit volgens plan afgerond.";
        }
    } else {
        $nieuwe_notitie = trim($_POST['werk_notities']);
        if (strpos($bestaande_notities, '⚡ EXTRA RIT') !== false) {
             $notities = $bestaande_notities . "\n[Opmerking Chauffeur:] " . $nieuwe_notitie;
        } else {
             $notities = $nieuwe_notitie;
        }
    }

    $is_vaste_rit_post = (isset($_POST['is_vaste_rit']) && $_POST['is_vaste_rit'] == '1');
    if ($is_vaste_rit_post) {
        $betaalwijze = 'Rekening';
        $betaald_bedrag = null;
    } else {
        $betaalwijze = !empty($_POST['betaalwijze']) ? $_POST['betaalwijze'] : 'Rekening';
        if ($betaalwijze === 'iDEAL') {
            $betaald_bedrag = (!empty($_POST['ideal_bedrag_opslaan'])) ? str_replace(',', '.', $_POST['ideal_bedrag_opslaan']) : null;
        } else {
            $betaald_bedrag = (!empty($_POST['betaald_bedrag'])) ? str_replace(',', '.', $_POST['betaald_bedrag']) : null;
        }
    }

    try {
        $update_stmt = $pdo->prepare("
            UPDATE ritten 
            SET werk_notities = ?, werkelijke_km = ?, km_start = ?, km_eind = ?, betaalwijze = ?, betaald_bedrag = ?, status = 'Voltooid', dienst_id = ?
            WHERE id = ? AND chauffeur_id = ? AND tenant_id = ?
        ");
        $update_stmt->execute([$notities, $werkelijke_km, $km_start, $km_eind, $betaalwijze, $betaald_bedrag, $actieve_dienst['id'], $rit_id, $chauffeur_id, $tenantId]);
        
        header("Location: dashboard.php?weergave=dienst");
        exit;
        
    } catch (PDOException $e) {
        die("FOUT bij opslaan: " . $e->getMessage());
    }
}

// ==========================================
// 4. RIT GEGEVENS OPHALEN
// ==========================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*, c.titel, c.opmerkingen_chauffeur AS instructie_kantoor,
            k.bedrijfsnaam, k.voornaam, k.achternaam, k.telefoon as klant_tel, k.mobiel,
            v.voertuig_nummer, v.naam as bus_naam,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as vaste_rit_naam
        FROM ritten r 
        LEFT JOIN calculaties c ON r.calculatie_id = c.id AND c.tenant_id = r.tenant_id
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = r.tenant_id
        LEFT JOIN voertuigen v ON r.voertuig_id = v.id AND v.tenant_id = r.tenant_id
        WHERE r.id = ? AND r.chauffeur_id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$rit_id, $chauffeur_id, $tenantId]);
    $rit = $stmt->fetch();

    if (!$rit) { die("Rit niet gevonden."); }

    $regels = [];
    if (!empty($rit['calculatie_id'])) {
        $stmt_regels = $pdo->prepare('SELECT type, tijd, adres FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt_regels->execute([$rit['calculatie_id'], $tenantId]);
        $regels = $stmt_regels->fetchAll();
    } else {
        $stmt_regels = $pdo->prepare('SELECT tijd, van_adres, naar_adres, omschrijving FROM ritregels WHERE rit_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt_regels->execute([$rit['id'], $tenantId]);
        $regels = $stmt_regels->fetchAll();
    }
} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

$is_vaste_rit = empty($rit['calculatie_id']) && (strpos($rit['werk_notities'] ?? '', '⚡ EXTRA RIT') === false);

if(strpos($rit['werk_notities'] ?? '', '⚡ EXTRA RIT') !== false) {
    if (strpos($rit['werk_notities'] ?? '', 'Klant: Losse rit') !== false) {
        $klant_weergave = "Losse rit";
    } else {
        preg_match('/Klant: (.*)/', $rit['werk_notities'], $matches);
        $klant_weergave = isset($matches[1]) ? trim($matches[1]) : "Losse rit";
    }
} else {
    $klant_weergave = !$is_vaste_rit ? (!empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam']) : "[VASTE RIT] " . $rit['vaste_rit_naam'];
}

$huidige_betaalwijze = !empty($rit['betaalwijze']) ? $rit['betaalwijze'] : 'Rekening';
$is_voltooid = ($rit['status'] === 'Voltooid');

$ingevulde_km = $rit['km_eind'] ?? '';
$ingevuld_bedrag = ($rit['betaald_bedrag'] > 0) ? $rit['betaald_bedrag'] : '';

$oorspronkelijke_notities = $rit['werk_notities'] ?? '';
$zichtbare_notities = $oorspronkelijke_notities;
if (strpos($oorspronkelijke_notities, '⚡ EXTRA RIT') !== false) {
    if (strpos($oorspronkelijke_notities, '[Opmerking Chauffeur:]') !== false) {
        $delen = explode('[Opmerking Chauffeur:]', $oorspronkelijke_notities);
        $zichtbare_notities = trim($delen[1]);
    } else {
        $zichtbare_notities = '';
    }
} elseif (strpos($oorspronkelijke_notities, '✅') !== false) {
    $zichtbare_notities = '';
}

$is_volgens_plan = (empty($zichtbare_notities));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rit Details - Berkhout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #2c3e50; }
        .app-header { background: #003366; color: white; padding: 15px 20px; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .back-btn { color: white; text-decoration: none; font-size: 20px; margin-right: 15px; }
        .app-header h1 { margin: 0; font-size: 18px; font-weight: 700; }
        .content { padding: 12px; max-width: 500px; margin: 0 auto; padding-bottom: 40px; }
        
        .card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        .card h3 { margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; color: #003366; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        
        .info-regel { margin: 6px 0; font-size: 14px; }
        .info-regel strong { color: #555; display: inline-block; width: 60px; }
        
        .stop-item { padding: 10px 0; border-bottom: 1px dashed #e0e0e0; display: flex; font-size: 14px; }
        .stop-item:last-child { border-bottom: none; }
        .stop-tijd { font-weight: bold; color: #d97706; width: 50px; flex-shrink: 0; }
        .stop-adres { flex-grow: 1; line-height: 1.4; }
        
        label { display: block; margin-top: 15px; margin-bottom: 4px; font-weight: bold; color: #444; font-size: 13px; }
        
        /* STRAKKE VELDEN NET ALS IN NIEUWE RIT */
        input[type="number"], input[type="text"], textarea { 
            -webkit-appearance: none; 
            appearance: none;
            width: 100%; 
            min-height: 50px; 
            padding: 10px 12px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 16px; 
            box-sizing: border-box; 
            background: #fafafa; 
            font-family: inherit;
        }
        input:focus, textarea:focus { border-color: #28a745; outline: none; background: white; }
        
        .opslaan-btn { background: <?php echo $is_voltooid ? '#007bff' : '#28a745'; ?>; color: white; border: none; padding: 14px; width: 100%; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: 0.2s; text-transform: uppercase; }
        
        .blokkade-msg { background: #fef0f0; color: #dc3545; padding: 20px; border-radius: 12px; border: 2px solid #f5c6cb; text-align: center; margin-bottom: 20px; }
        
        .keuze-btn { flex: 1; padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa; font-size: 15px; font-weight: bold; color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .keuze-btn.actief-ja { background: #d4edda; border-color: #28a745; color: #155724; }
        .keuze-btn.actief-nee { background: #f8d7da; border-color: #dc3545; color: #721c24; }

        .betaal-btn { flex: 1; padding: 12px 2px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fafafa; font-size: 12px; font-weight: bold; color: #555; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 6px; transition: 0.2s; }
        .betaal-btn i { font-size: 20px; color: #a0aec0; }
        .betaal-btn.actief { background: #e6f2ff; border-color: #007bff; color: #003366; }
        .betaal-btn.actief i { color: #007bff; }
        .betaal-btn.actief-ideal { background: #e0f2fe; border-color: #0284c7; color: #0369a1; }
        .betaal-btn.actief-ideal i { color: #0284c7; }

        #contant_bedrag_div, #ideal_div, #notities_div { display: none; margin-top: 10px; padding: 12px; border-radius: 8px; }
        #contant_bedrag_div { background: #e6f2ff; border: 2px solid #b8daff; }
        #notities_div { background: #fff3cd; border: 2px solid #ffeeba; }
        #ideal_div { background: #e0f2fe; border: 2px solid #bae6fd; }
    </style>
</head>
<body>

    <div class="app-header">
        <a href="dashboard.php<?php echo $is_voltooid ? '?weergave=dienst' : ''; ?>" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>Rit Details</h1>
    </div>

    <div class="content">

        <div class="card">
            <h3><i class="fas fa-info-circle"></i> Klant & Voertuig</h3>
            <div class="info-regel"><strong>Klant:</strong> <?php echo htmlspecialchars($klant_weergave); ?></div>
            <div class="info-regel"><strong>Bus:</strong> <?php echo htmlspecialchars($rit['voertuig_nummer'] . ' - ' . $rit['bus_naam']); ?></div>
            <div class="info-regel"><strong>Datum:</strong> <?php echo date('d-m-Y', strtotime($rit['datum_start'])); ?></div>
        </div>

        <div class="card">
            <h3><i class="fas fa-map-marked-alt"></i> Rijschema</h3>
            <?php if(count($regels) > 0): ?>
                <?php foreach($regels as $regel): 
                    $weergave_tijd = (!empty($regel['tijd']) && $regel['tijd'] != '00:00:00') ? date('H:i', strtotime($regel['tijd'])) : date('H:i', strtotime($rit['datum_start']));
                ?>
                    <div class="stop-item">
                        <div class="stop-tijd"><?php echo $weergave_tijd; ?></div>
                        <div class="stop-adres">
                            <?php if(!empty($regel['van_adres']) && !empty($regel['naar_adres'])): ?>
                                <strong>Van:</strong> <?php echo htmlspecialchars($regel['van_adres']); ?><br>
                                <strong>Naar:</strong> <?php echo htmlspecialchars($regel['naar_adres']); ?>
                            <?php else: ?>
                                <strong><?php echo isset($regel['type']) ? ucfirst(htmlspecialchars(str_replace(['t_', '_'], ['', ' '], $regel['type']))) : htmlspecialchars($regel['omschrijving'] ?? 'Stop'); ?></strong><br>
                                <?php echo htmlspecialchars($regel['adres'] ?? $regel['van_adres'] ?? ''); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="info-regel">Geen specifieke adresgegevens ingevoerd.</div>
            <?php endif; ?>
        </div>

        <?php if(!$actieve_dienst): ?>
            <div class="blokkade-msg">
                <i class="fas fa-lock"></i>
                <h4>Voorbereidings-modus</h4>
                <p>Klok eerst in op het dashboard om ritten af te kunnen ronden.</p>
            </div>
        <?php else: ?>
            
            <?php if($is_voltooid): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight:bold; text-align:center;">
                    ✅ Deze rit zit al in je dienst.<br>Je kunt de gegevens hieronder nog aanpassen.
                </div>
            <?php endif; ?>

            <div class="card" style="border-left: 5px solid #003366;">
                <h3 style="color: #003366; border-bottom: none;"><i class="fas fa-clipboard-check"></i> <?php echo $is_voltooid ? 'Rit Afronden' : 'Rit Afronden'; ?></h3>
                
                <form method="POST">
                    <input type="hidden" name="is_vaste_rit" value="<?php echo $is_vaste_rit ? '1' : '0'; ?>">
                    <input type="hidden" name="oorspronkelijke_notities" value="<?php echo htmlspecialchars($oorspronkelijke_notities); ?>">

                    <?php if(!$is_vaste_rit): ?>
                        <label style="margin-top:0;">Hoe heeft de klant afgerekend?</label>
                        
                        <div style="display: flex; gap: 8px; margin-top: 5px;">
                            <button type="button" id="btn-betaal-Rekening" class="betaal-btn" onclick="zetBetaalwijze('Rekening')">
                                <i class="fas fa-file-invoice"></i> Rekening
                            </button>
                            <button type="button" id="btn-betaal-Contant" class="betaal-btn" onclick="zetBetaalwijze('Contant')">
                                <i class="fas fa-coins"></i> Contant
                            </button>
                            <button type="button" id="btn-betaal-Pin" class="betaal-btn" onclick="zetBetaalwijze('Pin')">
                                <i class="fas fa-credit-card"></i> Gepind
                            </button>
                            <button type="button" id="btn-betaal-iDEAL" class="betaal-btn" onclick="zetBetaalwijze('iDEAL')">
                                <i class="fas fa-qrcode"></i> iDEAL
                            </button>
                        </div>
                        
                        <input type="hidden" name="betaalwijze" id="verborgen_betaalwijze" value="<?php echo $huidige_betaalwijze; ?>">
                        
                        <div id="contant_bedrag_div">
                            <label>Ontvangen Contant/Pin Bedrag</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 15px; font-size: 18px; font-weight: bold; color: #0056b3;">€</span>
                                <input type="text" name="betaald_bedrag" value="<?php echo $ingevuld_bedrag; ?>" inputmode="decimal" placeholder="" style="background: #e6f2ff; border-color: #b8daff; font-size: 20px; font-weight: bold; color: #0056b3; padding-left: 32px;">
                            </div>
                        </div>
                        
                        <div id="ideal_div">
                            <label style="color: #0284c7;">Te betalen via iDEAL</label>
                            <div style="position: relative; margin-bottom: 10px;">
                                <span style="position: absolute; left: 12px; top: 15px; font-size: 18px; font-weight: bold; color: #0369a1;">€</span>
                                <input type="text" id="ideal_invoer_bedrag" name="ideal_bedrag_opslaan" value="<?php echo $ingevuld_bedrag; ?>" inputmode="decimal" placeholder="" style="background: #e0f2fe; border-color: #bae6fd; font-size: 20px; font-weight: bold; color: #0369a1; padding-left: 32px;">
                            </div>
                            
                            <button type="button" id="btn_maak_qr" onclick="genereerMollieQR()" style="width:100%; height:50px; background: #0284c7; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size:16px;">
                                <i class="fas fa-qrcode"></i> Maak QR Code
                            </button>
                            
                            <div id="qr_resultaat" style="text-align: center; margin-top: 15px; display: none;">
                                <img id="qr_afbeelding" src="" alt="QR Code" style="width: 180px; height: 180px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: 3px solid white;">
                                <p style="font-size: 12px; color: #555; margin-top: 8px; font-weight:bold;">Laat de klant deze code scannen.</p>
                                
                                <div id="betaal_status_radar" style="display:none; color: #d97706; font-weight:bold; margin-top:10px;">
                                    <i class="fas fa-spinner fa-spin"></i> Wachten op betaling...
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

                    <label>Alles volgens plan verlopen?</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button type="button" id="btn-ja" class="keuze-btn" onclick="zetKeuze('ja')"><i class="fas fa-thumbs-up"></i> Ja</button>
                        <button type="button" id="btn-nee" class="keuze-btn" onclick="zetKeuze('nee')"><i class="fas fa-thumbs-down"></i> Nee</button>
                    </div>
                    <input type="hidden" id="plan_keuze" name="plan_keuze" value="<?php echo $is_volgens_plan ? 'ja' : 'nee'; ?>">
                    
                    <div id="notities_div">
                        <label style="color: #856404;">Wat was er aan de hand?</label>
                        <textarea name="werk_notities" rows="3"><?php echo htmlspecialchars($zichtbare_notities); ?></textarea>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-top: 15px; border: 1px solid #e9ecef;">
                        <label style="margin-top: 0;">Eindstand Bus (Kilometerteller):</label>
                        <input type="number" name="km_eind" value="<?php echo $ingevulde_km; ?>" pattern="\d*" inputmode="numeric" placeholder="" required>
                    </div>
                    
                    <button type="submit" class="opslaan-btn"><i class="fas <?php echo $is_voltooid ? 'fa-save' : 'fa-check'; ?>"></i> <?php echo $is_voltooid ? 'Wijzigingen Opslaan' : 'Rit Goedkeuren'; ?></button>
                </form>
            </div>

            <script>
                function zetKeuze(keuze) {
                    document.getElementById('plan_keuze').value = keuze;
                    let notitiesDiv = document.getElementById('notities_div');
                    let btnJa = document.getElementById('btn-ja');
                    let btnNee = document.getElementById('btn-nee');

                    if(keuze === 'ja') {
                        btnJa.classList.add('actief-ja'); btnNee.classList.remove('actief-nee');
                        notitiesDiv.style.display = 'none';
                    } else {
                        btnNee.classList.add('actief-nee'); btnJa.classList.remove('actief-ja');
                        notitiesDiv.style.display = 'block';
                    }
                }
                
                function zetBetaalwijze(keuze) {
                    document.getElementById('verborgen_betaalwijze').value = keuze;
                    
                    document.getElementById('btn-betaal-Rekening').classList.remove('actief', 'actief-ideal');
                    document.getElementById('btn-betaal-Contant').classList.remove('actief', 'actief-ideal');
                    document.getElementById('btn-betaal-Pin').classList.remove('actief', 'actief-ideal');
                    document.getElementById('btn-betaal-iDEAL').classList.remove('actief', 'actief-ideal');
                    
                    if(keuze === 'iDEAL') {
                        document.getElementById('btn-betaal-iDEAL').classList.add('actief-ideal');
                    } else {
                        document.getElementById('btn-betaal-' + keuze).classList.add('actief');
                    }
                    
                    document.getElementById('contant_bedrag_div').style.display = (keuze === 'Contant' || keuze === 'Pin') ? 'block' : 'none';
                    document.getElementById('ideal_div').style.display = (keuze === 'iDEAL') ? 'block' : 'none';
                }
                
                function genereerMollieQR() {
                    var bedrag = document.getElementById('ideal_invoer_bedrag').value;
                    if(bedrag === '') {
                        alert('Vul eerst een bedrag in voordat je de QR code maakt!');
                        return;
                    }
                    
                    var btn = document.getElementById('btn_maak_qr');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Even wachten...';
                    
                    var formData = new FormData();
                    formData.append('ajax_ideal_bedrag', bedrag);
                    formData.append('rit_id', <?php echo $rit_id; ?>);
                    
                    fetch('rit_bekijken.php?id=<?php echo $rit_id; ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.url) {
                            var qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" + encodeURIComponent(data.url);
                            document.getElementById('qr_afbeelding').src = qrUrl;
                            document.getElementById('qr_resultaat').style.display = 'block';
                            document.getElementById('betaal_status_radar').style.display = 'block';
                            
                            btn.innerHTML = '<i class="fas fa-check"></i> QR Code Gemaakt!';
                            btn.style.background = '#28a745';
                        } else {
                            alert(data.error);
                            btn.innerHTML = '<i class="fas fa-qrcode"></i> Probeer Opnieuw';
                        }
                    })
                    .catch(error => {
                        alert('Fout bij het verbinden met de server.');
                        btn.innerHTML = '<i class="fas fa-qrcode"></i> Probeer Opnieuw';
                    });
                }
                
                window.onload = function() { 
                    var startBetaalwijze = document.getElementById('verborgen_betaalwijze').value;
                    if(startBetaalwijze !== '') {
                        zetBetaalwijze(startBetaalwijze);
                    }
                    var startKeuze = document.getElementById('plan_keuze').value;
                    zetKeuze(startKeuze);
                };
            </script>
        <?php endif; ?>
    </div>
</body>
</html>