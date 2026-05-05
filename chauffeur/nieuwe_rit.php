<?php
// Bestand: chauffeur/nieuwe_rit.php
// VERSIE: Chauffeurs App 4.5 - Extra rit (tenant-safe: dienst, ritten, ritregels, voertuigen)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

if (!isset($_SESSION['chauffeur_id'])) {
    header('Location: index.php');
    exit;
}

require '../beheer/includes/db.php';

// PHPMailer bestanden inladen
require '../beheer/includes/PHPMailer/Exception.php';
require '../beheer/includes/PHPMailer/PHPMailer.php';
require '../beheer/includes/PHPMailer/SMTP.php';

$chauffeur_id = (int) $_SESSION['chauffeur_id'];
$chauffeur_naam = (string) $_SESSION['chauffeur_naam'];

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

$bericht = '';

// ==========================================
// 1. AJAX: MOLLIE AANROEPEN
// ==========================================
if (isset($_POST['ajax_ideal_bedrag'])) {
    require '../beheer/includes/mollie_connect.php';
    $bedrag = str_replace(',', '.', (string) ($_POST['ajax_ideal_bedrag'] ?? ''));
    $tijdelijk_rit_id = time();
    $mollie_response = maakMollieBetalingAan($bedrag, 'Extra Taxirit', $tijdelijk_rit_id, [
        'chauffeur_id' => $chauffeur_id,
        'tenant_id' => $tenantId,
        'flow' => 'extra_rit',
    ]);

    if (isset($mollie_response['_links']['checkout']['href']) && isset($mollie_response['id'])) {
        echo json_encode([
            'url' => $mollie_response['_links']['checkout']['href'],
            'mollie_id' => $mollie_response['id'],
        ]);
    } else {
        echo json_encode(['error' => 'Fout bij Mollie. Controleer de API sleutel.']);
    }
    exit;
}

// ==========================================
// 2. AJAX: DIRECT BONNETJE VERSTUREN (VOORDAT DE RIT IS OPGESLAGEN)
// ==========================================
if (isset($_POST['ajax_stuur_bon'])) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $datum = (string) ($_POST['datum'] ?? '');
    $van = trim((string) ($_POST['van'] ?? ''));
    $naar = trim((string) ($_POST['naar'] ?? ''));
    $bedrag = (float) str_replace(',', '.', (string) ($_POST['bedrag'] ?? '0'));
    $betaalwijze = (string) ($_POST['betaalwijze'] ?? '');

    if ($email === '' || $bedrag <= 0 || $van === '' || $naar === '') {
        echo json_encode(['success' => false, 'error' => 'Vul eerst adres, bedrag en e-mailadres in.']);
        exit;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = env_value('SMTP_HOST', 'smtp.hostinger.com');
        $mail->SMTPAuth = true;
        $mail->Username = env_value('SMTP_USER', '');
        $mail->Password = env_value('SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) env_value('SMTP_PORT', '465');

        $mail->setFrom(env_value('SMTP_FROM_EMAIL', 'info@berkhoutreizen.nl'), env_value('SMTP_FROM_NAME', 'Berkhout Reizen'));
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Betaalbewijs Berkhout Reizen';

        $btw_bedrag = $bedrag - ($bedrag / 1.09);
        $netto_bedrag = $bedrag - $btw_bedrag;

        $html_body = "
        <div style='font-family: Arial, sans-serif; max-width: 450px; margin: 0 auto; border: 1px solid #ddd; padding: 25px; border-radius: 12px; background: #fff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='https://www.berkhoutreizen.nl/beheer/images/berkhout_logo.png' alt='Berkhout Reizen' style='max-height: 60px;'>
            </div>
            <h2 style='color: #003366; text-align: center; margin-top: 0; font-size: 20px;'>Betaalbewijs</h2>
            <p style='text-align: center; color: #555; font-size: 14px;'>Hartelijk dank voor uw reis met Berkhout Reizen.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            
            <table style='width: 100%; font-size: 14px; color: #333; line-height: 1.6;'>
                <tr><td style='color: #777; width: 35%;'>Datum:</td><td style='text-align: right; font-weight:bold;'>" . date('d-m-Y', strtotime($datum)) . "</td></tr>
                <tr><td style='color: #777;'>Vertrek:</td><td style='text-align: right; font-weight:bold;'>" . htmlspecialchars($van, ENT_QUOTES, 'UTF-8') . "</td></tr>
                <tr><td style='color: #777;'>Bestemming:</td><td style='text-align: right; font-weight:bold;'>" . htmlspecialchars($naar, ENT_QUOTES, 'UTF-8') . "</td></tr>
                <tr><td style='color: #777;'>Betaling:</td><td style='text-align: right; font-weight:bold;'>" . htmlspecialchars($betaalwijze, ENT_QUOTES, 'UTF-8') . "</td></tr>
            </table>
            
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            
            <table style='width: 100%; font-size: 14px; color: #333; line-height: 1.6;'>
                <tr><td style='color: #777;'>Bedrag excl. BTW:</td><td style='text-align: right;'>€ " . number_format($netto_bedrag, 2, ',', '.') . "</td></tr>
                <tr><td style='color: #777;'>BTW (9%):</td><td style='text-align: right;'>€ " . number_format($btw_bedrag, 2, ',', '.') . "</td></tr>
            </table>
            
            <div style='margin-top: 15px; padding-top: 15px; border-top: 2px solid #003366; display: flex; justify-content: space-between; align-items: center; font-size: 18px; font-weight: bold; color: #003366;'>
                <span style='float:left;'>Totaal betaald:</span>
                <span style='float:right;'>€ " . number_format($bedrag, 2, ',', '.') . "</span>
                <div style='clear:both;'></div>
            </div>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 35px; font-size: 12px; color: #666; text-align: center; line-height: 1.5;'>
                <strong>Berkhout Reizen</strong><br>
                Industrieweg 95A, 7202 CA Zutphen<br>
                T: 0575-525345 | E: administratie@berkhoutreizen.nl<br>
                KvK: 08085361 | IBAN: NL77ABNA0558239994
            </div>
        </div>";

        $mail->Body = $html_body;
        $mail->send();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
    }
    exit;
}

// ==========================================
// 3. CHECK ACTIEVE DIENST
// ==========================================
$stmt_dienst = $pdo->prepare("SELECT id FROM diensten WHERE chauffeur_id = ? AND tenant_id = ? AND status = 'actief' ORDER BY id DESC LIMIT 1");
$stmt_dienst->execute([$chauffeur_id, $tenantId]);
$actieve_dienst = $stmt_dienst->fetch(PDO::FETCH_ASSOC);

// ==========================================
// 4. FORMULIER OPSLAAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $actieve_dienst && !isset($_POST['ajax_ideal_bedrag']) && !isset($_POST['ajax_stuur_bon'])) {
    $datum = (string) ($_POST['datum'] ?? '');
    $tijd = (string) ($_POST['tijd'] ?? '');
    $voertuig_id = (int) ($_POST['voertuig'] ?? 0);
    $soort_rit = (string) ($_POST['soort_rit'] ?? '');

    $km_eind = (isset($_POST['km_eind']) && $_POST['km_eind'] !== '') ? (int) $_POST['km_eind'] : null;

    $klant = trim((string) ($_POST['klant'] ?? ''));
    if ($klant === '') {
        $klant = 'Losse rit';
    }

    $van_adres = trim((string) ($_POST['van_adres'] ?? ''));
    $naar_adres = trim((string) ($_POST['naar_adres'] ?? ''));

    $betaalwijze = !empty($_POST['betaalwijze']) ? (string) $_POST['betaalwijze'] : 'Contant';
    $prijs_num = (float) str_replace(',', '.', (string) ($_POST['invoer_bedrag'] ?? '0'));

    $bon_email = trim((string) ($_POST['bon_email'] ?? ''));

    $stmt_v = $pdo->prepare('SELECT id FROM voertuigen WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt_v->execute([$voertuig_id, $tenantId]);
    if (!$stmt_v->fetch()) {
        $bericht = "<div class='msg-fout'>❌ Ongeldig voertuig voor deze organisatie.</div>";
    } elseif ($datum === '' || $tijd === '') {
        $bericht = "<div class='msg-fout'>❌ Datum en tijd zijn verplicht.</div>";
    } else {
        if ($betaalwijze === 'Rekening') {
            $betaald_bedrag = null;
        } else {
            $betaald_bedrag = $prijs_num;
        }

        $datum_start = $datum . ' ' . $tijd . ':00';
        $db_betaalwijze = ($betaalwijze === 'Rekening') ? 'Rekening' : $betaalwijze;

        $notitie_kantoor = "⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\n";
        $notitie_kantoor .= 'Soort: ' . $soort_rit . "\n";
        $notitie_kantoor .= 'Klant: ' . $klant . "\n";

        if ($betaalwijze === 'Rekening') {
            $factuur_email = trim((string) ($_POST['factuur_email'] ?? ''));
            $factuur_tel = trim((string) ($_POST['factuur_tel'] ?? ''));
            if ($factuur_email !== '' || $factuur_tel !== '') {
                $notitie_kantoor .= "-- Factuurgegevens --\n";
                if ($factuur_email !== '') {
                    $notitie_kantoor .= 'Email: ' . $factuur_email . "\n";
                }
                if ($factuur_tel !== '') {
                    $notitie_kantoor .= 'Tel: ' . $factuur_tel . "\n";
                }
            }
        } else {
            if ($bon_email !== '') {
                $notitie_kantoor .= "\n✅ BONNETJE IS DIGITAAL VERSTUURD NAAR: " . $bon_email . "\n";
            }
        }

        if ($prijs_num > 0) {
            $notitie_kantoor .= 'Ingevoerde prijs: € ' . number_format($prijs_num, 2, ',', '.');
        }

        try {
            $pdo->beginTransaction();

            $stmt_rit = $pdo->prepare('
                INSERT INTO ritten (tenant_id, chauffeur_id, voertuig_id, dienst_id, datum_start, status, betaalwijze, betaald_bedrag, werk_notities, km_eind)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt_rit->execute([
                $tenantId,
                $chauffeur_id,
                $voertuig_id,
                $actieve_dienst['id'],
                $datum_start,
                'Voltooid',
                $db_betaalwijze,
                $betaald_bedrag,
                $notitie_kantoor,
                $km_eind,
            ]);

            $nieuwe_rit_id = (int) $pdo->lastInsertId();

            $stmt_regel = $pdo->prepare('
                INSERT INTO ritregels (tenant_id, rit_id, omschrijving, van_adres, naar_adres)
                VALUES (?, ?, ?, ?, ?)
            ');
            $omschrijving = $soort_rit . ' (' . $klant . ')';
            $stmt_regel->execute([$tenantId, $nieuwe_rit_id, $omschrijving, $van_adres, $naar_adres]);

            $pdo->commit();

            header('Location: dashboard.php?weergave=dienst');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $bericht = "<div class='msg-fout'>❌ Fout bij opslaan: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

$stmt_bussen = $pdo->prepare('SELECT id, voertuig_nummer, naam FROM voertuigen WHERE tenant_id = ? ORDER BY voertuig_nummer ASC');
$stmt_bussen->execute([$tenantId]);
$bussen = $stmt_bussen->fetchAll(PDO::FETCH_ASSOC);

$huidige_tijd = date('H:i');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Extra Rit - Berkhout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .app-header { background: #003366; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .app-header h1 { margin: 0; font-size: 18px; }
        .back-btn { background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 14px; font-weight: bold; }
        
        .content { padding: 12px; max-width: 500px; margin: 0 auto; padding-bottom: 40px; }
        .form-card { background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #28a745; }
        
        .form-group { margin-bottom: 12px; }
        
        .grid-2x2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        .grid-2x2 > div { display: flex; flex-direction: column; }
        
        label { display: block; margin-bottom: 4px; font-weight: bold; color: #555; font-size: 13px; }
        
        input[type="text"], input[type="number"], input[type="date"], input[type="time"], input[type="email"], input[type="tel"], select { 
            -webkit-appearance: none; 
            -moz-appearance: none; 
            appearance: none;
            width: 100%; 
            height: 50px; 
            min-height: 50px;
            padding: 0 12px; 
            border: 2px solid #e0e0e0; 
            border-radius: 8px; 
            font-size: 16px; 
            box-sizing: border-box; 
            background-color: #fafafa; 
            margin: 0;
            font-family: inherit;
        }
        
        select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23555' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        input:focus, select:focus { border-color: #28a745; outline: none; background: white; }
        
        .route-box { background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        
        .btn-submit { background: #28a745; color: white; border: none; padding: 14px; width: 100%; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; box-shadow: 0 4px 10px rgba(40,167,69,0.2); text-transform: uppercase; }
        
        .blokkade-msg { background: #fef0f0; color: #dc3545; padding: 20px; border-radius: 12px; border: 2px solid #f5c6cb; text-align: center; margin-bottom: 20px; }
        .msg-fout { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; border-left: 5px solid #dc3545; }

        #ideal_div, #factuur_gegevens_div { display: none; margin-top: 8px; padding: 12px; border-radius: 8px; }
        #ideal_div { background: #e0f2fe; border: 2px solid #bae6fd; }
        #factuur_gegevens_div { background: #f8f9fa; border: 2px dashed #ced4da; }
        
        #betaal_status_radar { padding: 12px; border-radius: 8px; margin-top: 15px; font-weight: bold; font-size: 14px; display: none; }
        
        .checkbox-container { display: flex; align-items: center; gap: 10px; font-weight: normal; cursor: pointer; color: #444; font-size: 14px; margin-top: 10px; }
        .checkbox-container input[type="checkbox"] { width: 22px; height: 22px; margin: 0; cursor: pointer; }
    </style>
</head>
<body>

    <div class="app-header">
        <h1>🚕 Extra Rit Invoeren</h1>
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Terug</a>
    </div>

    <div class="content">
        <?php echo $bericht; ?>
        
        <?php if (!$actieve_dienst): ?>
            <div class="blokkade-msg">
                <i class="fas fa-lock" style="font-size: 30px; margin-bottom: 10px;"></i>
                <h4 style="margin: 0 0 5px 0;">Niet ingeklokt</h4>
                <p style="margin: 0; font-size: 14px;">Je moet eerst je dienst starten op het dashboard voordat je een extra rit kunt toevoegen.</p>
            </div>
        <?php else: ?>
            <div class="form-card">
                <form method="POST" id="ritFormulier">
                    
                    <div class="grid-2x2">
                        <div>
                            <label>Datum</label>
                            <input type="date" name="datum" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label>Tijd</label>
                            <input type="time" name="tijd" value="<?php echo htmlspecialchars($huidige_tijd, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div>
                            <label>Voertuig</label>
                            <select name="voertuig" required>
                                <?php foreach ($bussen as $bus): ?>
                                    <option value="<?php echo (int) $bus['id']; ?>">Bus <?php echo htmlspecialchars((string) $bus['voertuig_nummer'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Soort</label>
                            <select name="soort_rit" required>
                                <option value="Taxirit">Taxirit</option>
                                <option value="Treinstremming">Trein NS</option>
                                <option value="Touringcarrit">Touringcarrit</option>
                            </select>
                        </div>
                    </div>

                    <div class="route-box">
                        <div class="form-group">
                            <label style="color: #003366;"><i class="fas fa-map-marker-alt"></i> Vertrekadres</label>
                            <input type="text" name="van_adres" id="van_adres" placeholder="Van..." required autocomplete="off">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="color: #003366;"><i class="fas fa-flag-checkered"></i> Bestemming</label>
                            <input type="text" name="naar_adres" id="naar_adres" placeholder="Naar..." required autocomplete="off">
                        </div>
                    </div>

                    <div class="grid-2x2" style="margin-top: 15px; margin-bottom: 5px;">
                        <div>
                            <label>Bedrag</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 13px; font-size: 18px; font-weight: bold; color: #0369a1;">€</span>
                                <input type="text" id="invoer_bedrag" name="invoer_bedrag" inputmode="decimal" placeholder="" style="background: #e0f2fe; border: 2px solid #bae6fd; font-size: 20px; font-weight: bold; color: #0369a1; padding-left: 32px;">
                            </div>
                        </div>
                        <div>
                            <label>Betaalwijze</label>
                            <select name="betaalwijze" id="betaalwijze_select" onchange="zetBetaalwijze(this.value)" required>
                                <option value="Contant">Contant</option>
                                <option value="Pin">Gepind</option>
                                <option value="iDEAL">iDEAL</option>
                                <option value="Rekening">Rekening</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="bonnetje_container">
                        <label class="checkbox-container">
                            <input type="checkbox" id="wil_bonnetje" onchange="toggleBonnetje()">
                            Klant wil een bonnetje
                        </label>
                        <div id="bonnetje_email_div" style="display: none; margin-top: 8px;">
                            <div style="display: flex; gap: 8px;">
                                <input type="email" id="bon_email_input" name="bon_email" placeholder="E-mailadres klant..." style="flex: 2;">
                                <button type="button" id="btn_stuur_bon" onclick="verstuurBonnetje()" style="flex: 1; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px;">
                                    <i class="fas fa-paper-plane"></i> Verstuur
                                </button>
                            </div>
                            <div id="bon_status_msg" style="margin-top: 8px; font-size: 13px; font-weight: bold; display: none;"></div>
                        </div>
                    </div>

                    <div id="ideal_div">
                        <button type="button" id="btn_maak_qr" onclick="genereerMollieQR()" style="width:100%; height:50px; background: #0284c7; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size:16px;">
                            <i class="fas fa-qrcode"></i> Maak iDEAL QR Code
                        </button>
                        
                        <div id="qr_resultaat" style="text-align: center; margin-top: 12px; display: none;">
                            <img id="qr_afbeelding" src="" alt="QR Code" style="width: 180px; height: 180px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: 3px solid white;">
                            <p style="font-size: 12px; color: #555; margin-top: 8px; font-weight:bold;">Laat de klant deze code scannen.</p>
                            
                            <div id="betaal_status_radar"></div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label id="label_klantnaam">Klantnaam <span id="klant_optioneel_tekst" style="font-weight:normal; color:#888;">(Optioneel)</span></label>
                        <input type="text" name="klant" id="input_klantnaam" placeholder="">
                    </div>

                    <div id="factuur_gegevens_div">
                        <p style="margin-top:0; color:#555; font-size:12px; font-weight:bold;"><i class="fas fa-info-circle"></i> Optionele gegevens voor de factuur</p>
                        <div class="form-group">
                            <label>E-mailadres</label>
                            <input type="email" name="factuur_email" placeholder="">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Telefoonnummer</label>
                            <input type="tel" name="factuur_tel" placeholder="">
                        </div>
                    </div>

                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-top: 15px; border: 1px solid #e9ecef;">
                        <label style="margin-top: 0;">Eindstand Bus (Kilometerteller):</label>
                        <input type="number" name="km_eind" pattern="\d*" inputmode="numeric" placeholder="" required>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Rit Opslaan</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function() {
            var rf = document.getElementById('ritFormulier');
            if (!rf) return;
            rf.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); return false; }
            });
        })();

        let radarInterval = null;
        let radarPogingen = 0;
        const MAX_POGINGEN = 60; 

        function toggleBonnetje() {
            var cb = document.getElementById('wil_bonnetje');
            var div = document.getElementById('bonnetje_email_div');
            
            if (cb.checked) {
                div.style.display = 'block';
            } else {
                div.style.display = 'none';
                document.getElementById('bon_email_input').value = ''; 
                document.getElementById('bon_status_msg').style.display = 'none';
                let btn = document.getElementById('btn_stuur_bon');
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Verstuur';
                btn.style.background = '#007bff';
                btn.disabled = false;
            }
        }

        function verstuurBonnetje() {
            var email = document.getElementById('bon_email_input').value;
            var bedrag = document.getElementById('invoer_bedrag').value;
            var datum = document.querySelector('input[name="datum"]').value;
            var van = document.getElementById('van_adres').value;
            var naar = document.getElementById('naar_adres').value;
            var betaalwijze = document.getElementById('betaalwijze_select').value;
            
            var btn = document.getElementById('btn_stuur_bon');
            var msg = document.getElementById('bon_status_msg');

            if(!email || !bedrag || !van || !naar) {
                alert('Vul eerst de datum, vertrekadres, bestemming en het bedrag in voordat je de bon verstuurt.');
                return;
            }

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig...';
            btn.disabled = true;

            var formData = new FormData();
            formData.append('ajax_stuur_bon', '1');
            formData.append('email', email);
            formData.append('bedrag', bedrag);
            formData.append('datum', datum);
            formData.append('van', van);
            formData.append('naar', naar);
            formData.append('betaalwijze', betaalwijze);

            fetch('nieuwe_rit.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Verzonden!';
                    btn.style.background = '#28a745';
                    msg.style.display = 'block';
                    msg.style.color = '#155724';
                    msg.innerHTML = '✅ Het betaalbewijs is succesvol verstuurd!';
                } else {
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Verstuur';
                    btn.disabled = false;
                    alert('Fout bij verzenden: ' + (data.error || 'Onbekende fout.'));
                }
            })
            .catch(error => {
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Verstuur';
                btn.disabled = false;
                alert('Netwerkfout bij verzenden.');
            });
        }

        function zetBetaalwijze(keuze) {
            let klantInput = document.getElementById('input_klantnaam');
            let klantOptioneelTekst = document.getElementById('klant_optioneel_tekst');
            let factuurDiv = document.getElementById('factuur_gegevens_div');
            let idealDiv = document.getElementById('ideal_div');
            let bonnetjeContainer = document.getElementById('bonnetje_container');
            
            if(radarInterval) clearInterval(radarInterval);
            
            if(keuze === 'Rekening') {
                idealDiv.style.display = 'none';
                bonnetjeContainer.style.display = 'none';
                
                document.getElementById('wil_bonnetje').checked = false;
                toggleBonnetje();
                
                klantInput.required = true;
                klantInput.placeholder = "Naam en evt. adres...";
                klantOptioneelTekst.innerHTML = '<span style="color:red">*</span>';
                factuurDiv.style.display = 'block';
            } else if (keuze === 'iDEAL') {
                idealDiv.style.display = 'block';
                bonnetjeContainer.style.display = 'block';
                resetKlantnaamVeld(klantInput, klantOptioneelTekst, factuurDiv);
            } else {
                idealDiv.style.display = 'none';
                bonnetjeContainer.style.display = 'block';
                resetKlantnaamVeld(klantInput, klantOptioneelTekst, factuurDiv);
            }
        }
        
        function resetKlantnaamVeld(input, tekst, div) {
            input.required = false;
            input.placeholder = "";
            tekst.innerHTML = '<span style="font-weight:normal; color:#888;">(Optioneel)</span>';
            div.style.display = 'none';
        }

        function genereerMollieQR() {
            var bedrag = document.getElementById('invoer_bedrag').value;
            if(bedrag === '') {
                alert('Vul eerst een bedrag in voordat je de QR code maakt!');
                return;
            }
            
            var btn = document.getElementById('btn_maak_qr');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Even wachten...';
            
            var formData = new FormData();
            formData.append('ajax_ideal_bedrag', bedrag);
            
            fetch('nieuwe_rit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.url && data.mollie_id) {
                    var qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" + encodeURIComponent(data.url);
                    document.getElementById('qr_afbeelding').src = qrUrl;
                    document.getElementById('qr_resultaat').style.display = 'block';
                    
                    btn.innerHTML = '<i class="fas fa-check"></i> QR Code Gemaakt!';
                    btn.style.background = '#28a745';
                    
                    startBetalingRadar(data.mollie_id);
                    
                } else {
                    alert(data.error || 'Er is een fout opgetreden.');
                    btn.innerHTML = '<i class="fas fa-qrcode"></i> Probeer Opnieuw';
                }
            })
            .catch(error => {
                alert('Fout bij het verbinden met Mollie.');
                btn.innerHTML = '<i class="fas fa-qrcode"></i> Probeer Opnieuw';
            });
        }
        
        function startBetalingRadar(mollieId) {
            if(radarInterval) clearInterval(radarInterval);
            radarPogingen = 0;
            
            let radarDiv = document.getElementById('betaal_status_radar');
            radarDiv.style.display = 'block';
            radarDiv.style.background = '#fef3c7';
            radarDiv.style.border = '2px solid #fde68a';
            radarDiv.style.color = '#d97706';
            radarDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Wachten tot klant betaalt...';
            
            radarInterval = setInterval(function() {
                radarPogingen++;
                
                if(radarPogingen >= MAX_POGINGEN) {
                    clearInterval(radarInterval);
                    radarDiv.style.background = '#fee2e2';
                    radarDiv.style.border = '2px solid #fca5a5';
                    radarDiv.style.color = '#b91c1c';
                    radarDiv.innerHTML = '<i class="fas fa-times-circle"></i> Tijd verlopen. Laat de klant opnieuw scannen.';
                    return;
                }
                
                var formData = new FormData();
                formData.append('mollie_id', mollieId);
                
                fetch('check_betaling.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'paid') {
                        clearInterval(radarInterval);
                        radarDiv.style.background = '#dcfce7';
                        radarDiv.style.border = '2px solid #86efac';
                        radarDiv.style.color = '#15803d';
                        radarDiv.innerHTML = '<i class="fas fa-check-circle" style="font-size: 18px;"></i> BETALING GELUKT!';
                        
                        document.getElementById('btn_maak_qr').innerHTML = '<i class="fas fa-check"></i> iDEAL Betaald!';
                    } else if (data.status === 'canceled' || data.status === 'failed' || data.status === 'expired') {
                        clearInterval(radarInterval);
                        radarDiv.style.background = '#fee2e2';
                        radarDiv.style.border = '2px solid #fca5a5';
                        radarDiv.style.color = '#b91c1c';
                        radarDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Betaling mislukt of geannuleerd.';
                    }
                });
            }, 3000);
        }
        
        window.onload = function() {
            var bs = document.getElementById('betaalwijze_select');
            if (bs) { zetBetaalwijze(bs.value); }
        };

        function startGoogleMaps() {
            var va = document.getElementById('van_adres');
            var na = document.getElementById('naar_adres');
            if (!va || !na) return;
            var opties = { types: ['geocode', 'establishment'] };
            new google.maps.places.Autocomplete(va, opties);
            new google.maps.places.Autocomplete(na, opties);
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(env_value('GOOGLE_MAPS_API_KEY', '')); ?>&libraries=places&callback=startGoogleMaps" async defer></script>
</body>
</html>
