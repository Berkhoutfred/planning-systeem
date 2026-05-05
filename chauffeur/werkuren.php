<?php
// Bestand: chauffeur/werkuren.php
// VERSIE: Chauffeurs App - Uren 2.1 (tenant-safe sessie + laden/opslaan loon_uren binnen tenant)

session_start();

if (!isset($_SESSION['chauffeur_id'])) {
    header('Location: index.php');
    exit;
}

require '../beheer/includes/db.php';

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

$succes_melding = '';

function werkuren_parse_datum(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);

    return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : null;
}

// --- DE REKENMOTOR (Exact gelijk aan kantoor) ---
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

// Standaard datum = vandaag of uit formulier / GET (?datum=)
if (isset($_GET['datum'])) {
    $parsed = werkuren_parse_datum((string) $_GET['datum']);
    $gekozen_datum = $parsed ?? date('Y-m-d');
} elseif (isset($_POST['datum'])) {
    $parsed = werkuren_parse_datum((string) $_POST['datum']);
    $gekozen_datum = $parsed ?? date('Y-m-d');
} else {
    $gekozen_datum = date('Y-m-d');
}

$stmt_load = $pdo->prepare('
    SELECT l.* FROM loon_uren l
    INNER JOIN chauffeurs c ON c.id = l.chauffeur_id AND c.tenant_id = ?
    WHERE l.chauffeur_id = ? AND l.datum = ?
    LIMIT 1
');
$stmt_load->execute([$tenantId, $chauffeur_id, $gekozen_datum]);
$uren_rij = $stmt_load->fetch(PDO::FETCH_ASSOC) ?: null;

// --- FORMULIER VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $echte_datum = werkuren_parse_datum((string) ($_POST['datum'] ?? ''));
    if ($echte_datum === null) {
        $succes_melding = '⚠️ Ongeldige datum.';
    } else {
        $is_ov = (($_POST['type_vervoer'] ?? '') === 'OV');
        $type_vervoer = $is_ov ? 'OV' : 'Groepsvervoer';
        
        $van_a = (!empty($_POST['van_a_u']) && $_POST['van_a_m'] !== '') ? $_POST['van_a_u'].':'.$_POST['van_a_m'] : '';
        $tot_a = (!empty($_POST['tot_a_u']) && $_POST['tot_a_m'] !== '') ? $_POST['tot_a_u'].':'.$_POST['tot_a_m'] : '';
        
        $van_b = (!empty($_POST['van_b_u']) && $_POST['van_b_m'] !== '') ? $_POST['van_b_u'].':'.$_POST['van_b_m'] : '';
        $tot_b = (!empty($_POST['tot_b_u']) && $_POST['tot_b_m'] !== '') ? $_POST['tot_b_u'].':'.$_POST['tot_b_m'] : '';
        
        $van_c = (!empty($_POST['van_c_u']) && $_POST['van_c_m'] !== '') ? $_POST['van_c_u'].':'.$_POST['van_c_m'] : '';
        $tot_c = (!empty($_POST['tot_c_u']) && $_POST['tot_c_m'] !== '') ? $_POST['tot_c_u'].':'.$_POST['tot_c_m'] : '';

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

        if (!empty($van_c) && !empty($tot_c)) {
            $ts_c_start = strtotime("$echte_datum $van_c");
            $vorige_eind = $ts_b_eind > 0 ? $ts_b_eind : $ts_a_eind;
            if ($vorige_eind > 0 && $ts_c_start < $vorige_eind) { $ts_c_start += 86400; }
            $ts_c_eind = strtotime("$echte_datum $tot_c");
            while ($ts_c_eind <= $ts_c_start) { $ts_c_eind += 86400; }
            $uren_c = ($ts_c_eind - $ts_c_start) / 3600;
        }
        
        $netto_uren = $uren_a + $uren_b + $uren_c;
        
        if ($netto_uren > 0) {
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

            $stmt_insert = $pdo->prepare("INSERT INTO loon_uren 
                (chauffeur_id, datum, type_vervoer, van_a, tot_a, van_b, tot_b, van_c, tot_c, 
                 uren_basis, toeslag_avond, toeslag_weekend, toeslag_zon_feest, 
                 toeslag_ov_avond_nacht, toeslag_ov_zaterdag, toeslag_ov_zondag, onderbreking_aantal) 
                SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                FROM chauffeurs c
                WHERE c.id = ? AND c.tenant_id = ? AND c.archief = 0
                LIMIT 1
                ON DUPLICATE KEY UPDATE 
                type_vervoer=VALUES(type_vervoer), van_a=VALUES(van_a), tot_a=VALUES(tot_a), 
                van_b=VALUES(van_b), tot_b=VALUES(tot_b), van_c=VALUES(van_c), tot_c=VALUES(tot_c),
                uren_basis=VALUES(uren_basis), toeslag_avond=VALUES(toeslag_avond), toeslag_weekend=VALUES(toeslag_weekend), 
                toeslag_zon_feest=VALUES(toeslag_zon_feest), toeslag_ov_avond_nacht=VALUES(toeslag_ov_avond_nacht), 
                toeslag_ov_zaterdag=VALUES(toeslag_ov_zaterdag), toeslag_ov_zondag=VALUES(toeslag_ov_zondag), 
                onderbreking_aantal=VALUES(onderbreking_aantal)");

            try {
                $stmt_insert->execute([
                    $chauffeur_id, $echte_datum, $type_vervoer, $van_a, $tot_a, $van_b, $tot_b, $van_c, $tot_c,
                    round($netto_uren, 2),
                    $totaal_emmers['ongeregeld_nacht'],
                    $totaal_emmers['ongeregeld_zat'],
                    $totaal_emmers['ongeregeld_zon'],
                    $totaal_emmers['ov_nacht'],
                    $totaal_emmers['ov_zat'],
                    $totaal_emmers['ov_zon'],
                    $onderbreking_aantal,
                    $chauffeur_id,
                    $tenantId,
                ]);
                $succes_melding = '✅ Je uren voor ' . date('d-m-Y', strtotime($echte_datum)) . ' zijn succesvol doorgegeven aan kantoor!';
                $gekozen_datum = $echte_datum;
                $stmt_load->execute([$tenantId, $chauffeur_id, $gekozen_datum]);
                $uren_rij = $stmt_load->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $e) {
                $succes_melding = '⚠️ Opslaan mislukt. Probeer het opnieuw of neem contact op met kantoor.';
            }
        } else {
            $succes_melding = '⚠️ Vul minimaal 1 geldig tijdsblok in.';
        }
    }
}

function maakTijdPicker(string $naam, ?string $default_tijd): string {
    $u_sel = '';
    $m_sel = '';
    $kwartieren = ['00', '15', '30', '45'];
    if ($default_tijd !== null && $default_tijd !== '') {
        $parts = explode(':', $default_tijd);
        if (count($parts) >= 2) {
            $hi = max(0, min(23, (int) $parts[0]));
            $mi = max(0, min(59, (int) $parts[1]));
            $u_sel = str_pad((string) $hi, 2, '0', STR_PAD_LEFT);
            $m_try = str_pad((string) $mi, 2, '0', STR_PAD_LEFT);
            $m_sel = in_array($m_try, $kwartieren, true) ? $m_try : '';
        }
    }

    $html = '<div class="custom-time-picker">';
    
    $html .= '<select name="'.$naam.'_u" class="time-sel" onchange="berekenTotaal()">';
    $html .= '<option value="">--</option>';
    for ($i = 0; $i < 24; $i++) { 
        $v = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $sel = ($u_sel !== '' && $v === $u_sel) ? ' selected' : '';
        $html .= "<option value=\"$v\"$sel>$v</option>";
    }
    $html .= '</select>';
    
    $html .= '<span class="time-sep">:</span>';
    
    $html .= '<select name="'.$naam.'_m" class="time-sel" onchange="berekenTotaal()">';
    $html .= '<option value="">--</option>';
    foreach ($kwartieren as $km) {
        $sel = ($m_sel !== '' && $km === $m_sel) ? ' selected' : '';
        $html .= "<option value=\"$km\"$sel>$km</option>";
    }
    $html .= '</select>';
    
    $html .= '</div>';
    return $html;
}

$type_ov_selected = ($uren_rij && (($uren_rij['type_vervoer'] ?? '') === 'OV'));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Uren Doorgeven - Berkhout App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .app-header { background: #003366; color: white; padding: 15px 20px; display: flex; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .back-btn { color: white; text-decoration: none; font-size: 20px; margin-right: 15px; font-weight: bold; }
        .app-header h1 { margin: 0; font-size: 18px; }
        .content { padding: 15px; }
        
        .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 3px 8px rgba(0,0,0,0.05); }
        .card p.intro { color: #666; font-size: 14px; margin-top: 0; margin-bottom: 20px; }
        
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #444; font-size: 14px;}
        input[type="date"], select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box; background: #f9f9f9; }
        input:focus, select:focus { border-color: #28a745; outline: none; }
        
        .tijd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 5px; }
        .custom-time-picker { display: flex; align-items: center; background: #f9f9f9; border: 2px solid #ddd; border-radius: 6px; overflow: hidden; }
        .custom-time-picker:focus-within { border-color: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.3); }
        .time-sel { flex: 1; border: none; background: transparent; padding: 12px 5px; font-size: 18px; text-align: center; text-align-last: center; font-weight: bold; color: #003366; cursor: pointer; outline: none; -webkit-appearance: none; appearance: none; }
        .time-sep { font-weight: bold; color: #999; font-size: 18px; padding: 0 2px; }
        
        .opslaan-btn { background: #28a745; color: white; border: none; padding: 15px; width: 100%; border-radius: 6px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 20px; box-shadow: 0 4px 6px rgba(40,167,69,0.2); }
        .opslaan-btn:active { background: #218838; }
        .opslaan-btn:disabled { background: #ccc; box-shadow: none; cursor: not-allowed; }
        
        .succes-msg { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center; border: 1px solid #c3e6cb; }
        .error-msg { background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; text-align: center; border: 1px solid #ffeeba; }
        
        .blok-titel { color: #003366; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 10px; margin-top: 25px; font-size: 16px;}
        
        .totaal-box { background: #e8f5e9; border: 2px solid #c8e6c9; border-radius: 8px; padding: 15px; margin-top: 30px; text-align: center; }
        .totaal-box h3 { margin: 0 0 5px 0; font-size: 14px; color: #2e7d32; text-transform: uppercase; }
        #totaal_weergave { font-size: 24px; font-weight: bold; color: #1b5e20; }
    </style>
</head>
<body>

    <div class="app-header">
        <a href="dashboard.php" class="back-btn">⬅️ Terug</a>
        <h1>🕒 Uren Doorgeven</h1>
    </div>

    <div class="content">
        <?php if ($succes_melding !== ''): ?>
            <div class="<?= strpos($succes_melding, '⚠️') !== false ? 'error-msg' : 'succes-msg' ?>">
                <?php echo htmlspecialchars($succes_melding, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <p class="intro">Kies in het linkervak de uren, en in het rechtervak de kwartieren. Andere dag? Pas de datum aan (optioneel: <code>?datum=YYYY-MM-DD</code> in de URL).</p>
            
            <form method="POST" id="urenForm">
                
                <label>Datum van de werkdag:</label>
                <input type="date" name="datum" value="<?php echo htmlspecialchars($gekozen_datum, ENT_QUOTES, 'UTF-8'); ?>" required>
                
                <label>Type Vervoer:</label>
                <select name="type_vervoer">
                    <option value="Normaal"<?php echo $type_ov_selected ? '' : ' selected'; ?>>Normaal (Groepsvervoer / Toeringcar)</option>
                    <option value="OV"<?php echo $type_ov_selected ? ' selected' : ''; ?>>Openbaar Vervoer (Treinstremming / Lijn)</option>
                </select>
                
                <h3 class="blok-titel">Blok A (Start van de dag)</h3>
                <div class="tijd-grid">
                    <div>
                        <label style="margin-top:0;">Starttijd:</label>
                        <?php echo maakTijdPicker('van_a', $uren_rij['van_a'] ?? null); ?>
                    </div>
                    <div>
                        <label style="margin-top:0;">Eindtijd:</label>
                        <?php echo maakTijdPicker('tot_a', $uren_rij['tot_a'] ?? null); ?>
                    </div>
                </div>

                <h3 class="blok-titel">Blok B (Na lange pauze)</h3>
                <div class="tijd-grid">
                    <div>
                        <label style="margin-top:0;">Starttijd:</label>
                        <?php echo maakTijdPicker('van_b', $uren_rij['van_b'] ?? null); ?>
                    </div>
                    <div>
                        <label style="margin-top:0;">Eindtijd:</label>
                        <?php echo maakTijdPicker('tot_b', $uren_rij['tot_b'] ?? null); ?>
                    </div>
                </div>
                
                <h3 class="blok-titel">Blok C (Extra blok)</h3>
                <div class="tijd-grid">
                    <div>
                        <label style="margin-top:0;">Starttijd:</label>
                        <?php echo maakTijdPicker('van_c', $uren_rij['van_c'] ?? null); ?>
                    </div>
                    <div>
                        <label style="margin-top:0;">Eindtijd:</label>
                        <?php echo maakTijdPicker('tot_c', $uren_rij['tot_c'] ?? null); ?>
                    </div>
                </div>

                <div class="totaal-box">
                    <h3>Totaal Berekende Uren</h3>
                    <div id="totaal_weergave"><span style="color:#999; font-size:16px;">Vul je tijden in...</span></div>
                </div>

                <button type="submit" id="submitBtn" class="opslaan-btn" disabled>Verzenden naar Kantoor</button>
            </form>
        </div>
    </div>

    <script>
        function berekenTotaal() {
            let totalMinutes = 0;
            let valid = true;
            let errorMsg = '';
            let btn = document.getElementById('submitBtn');

            const blocks = ['a', 'b', 'c'];
            let lastEndMins = 0;
            let blockCount = 0;

            for (let i = 0; i < blocks.length; i++) {
                let u_start = document.querySelector(`[name="van_${blocks[i]}_u"]`).value;
                let m_start = document.querySelector(`[name="van_${blocks[i]}_m"]`).value;
                let u_end = document.querySelector(`[name="tot_${blocks[i]}_u"]`).value;
                let m_end = document.querySelector(`[name="tot_${blocks[i]}_m"]`).value;

                let hasStart = (u_start !== '' && m_start !== '');
                let hasEnd = (u_end !== '' && m_end !== '');

                if ((u_start !== '' || m_start !== '') && !hasStart) { errorMsg = `Blok ${blocks[i].toUpperCase()} start is niet compleet.`; valid = false; break; }
                if ((u_end !== '' || m_end !== '') && !hasEnd) { errorMsg = `Blok ${blocks[i].toUpperCase()} eind is niet compleet.`; valid = false; break; }

                if (hasStart && !hasEnd) { errorMsg = `Vul ook de eindtijd in bij Blok ${blocks[i].toUpperCase()}.`; valid = false; break; }
                if (!hasStart && hasEnd) { errorMsg = `Vul eerst de starttijd in bij Blok ${blocks[i].toUpperCase()}.`; valid = false; break; }

                if (hasStart && hasEnd) {
                    blockCount++;
                    let startMins = parseInt(u_start) * 60 + parseInt(m_start);
                    let endMins = parseInt(u_end) * 60 + parseInt(m_end);

                    if (startMins < lastEndMins && i > 0) {
                        startMins += 24 * 60;
                    }
                    if (endMins <= startMins) {
                        endMins += 24 * 60;
                    }

                    if (i > 0 && startMins < lastEndMins) {
                        errorMsg = `Tijdfout: Blok ${blocks[i].toUpperCase()} start vóórdat het vorige blok is geëindigd!`;
                        valid = false;
                        break;
                    }

                    let duration = endMins - startMins;
                    if (duration > 16 * 60) {
                        errorMsg = `Let op: Blok ${blocks[i].toUpperCase()} is berekend op meer dan 16 uur. Controleer je invoer!`;
                        valid = false;
                        break;
                    }

                    totalMinutes += duration;
                    lastEndMins = endMins;
                }
            }

            let display = document.getElementById('totaal_weergave');
            
            if (!valid) {
                display.innerHTML = `<span style="color:#dc3545; font-size:14px;">⚠️ ${errorMsg}</span>`;
                btn.disabled = true;
            } else if (blockCount > 0) {
                let h = Math.floor(totalMinutes / 60);
                let m = totalMinutes % 60;
                let dec = (totalMinutes / 60).toFixed(2).replace('.', ',');
                display.innerHTML = `<span style="color:#28a745; font-size:22px; font-weight:bold;">${h} uur en ${m} min</span> <br><span style="font-size:14px; color:#555;">(In decimalen: ${dec} u)</span>`;
                btn.disabled = false;
            } else {
                display.innerHTML = `<span style="color:#999; font-size:16px;">Vul je tijden in...</span>`;
                btn.disabled = true;
            }
        }
        document.addEventListener('DOMContentLoaded', berekenTotaal);
    </script>
</body>
</html>
