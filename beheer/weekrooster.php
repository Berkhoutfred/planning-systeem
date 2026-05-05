<?php
// Bestand: beheer/weekrooster.php
// VERSIE: Strak & Simpel Rooster - Diensten zien eruit als normale ritten

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$dienst_msg = '';
$dienst_error = '';

// --- DIENST AANMAKEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'nieuwe_dienst') {
    $d_naam = trim($_POST['dienst_naam']);
    $d_datum = $_POST['dienst_datum'];
    $d_chauffeur = !empty($_POST['dienst_chauffeur']) ? (int)$_POST['dienst_chauffeur'] : null;
    
    if (!empty($d_naam) && !empty($d_datum)) {
        if ($d_chauffeur !== null) {
            $stmtChauffeur = $pdo->prepare("SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmtChauffeur->execute([$d_chauffeur, $tenantId]);
            if (!$stmtChauffeur->fetchColumn()) {
                $dienst_error = "Geselecteerde chauffeur hoort niet bij deze tenant.";
                $d_chauffeur = null;
            }
        }

        $stmt_check = $pdo->prepare("SELECT id FROM diensten WHERE tenant_id = ? AND naam = ? AND geplande_datum = ?");
        $stmt_check->execute([$tenantId, $d_naam, $d_datum]);
        
        if ($dienst_error !== '') {
            // bewust leeg, foutmelding al gezet
        } elseif ($stmt_check->rowCount() > 0) {
            $dienst_error = "Let op: De dienst '{$d_naam}' bestaat al op deze datum!";
        } else {
            $stmt_nd = $pdo->prepare("INSERT INTO diensten (tenant_id, naam, geplande_datum, chauffeur_id, status) VALUES (?, ?, ?, ?, 'actief')");
            $stmt_nd->execute([$tenantId, $d_naam, $d_datum, $d_chauffeur]);
            $dienst_msg = "Dienst succesvol aangemaakt!";
        }
    }
}

// --- DIENST SPLITSEN ---
if (isset($_GET['splits_dienst'])) {
    $splits_id = (int)$_GET['splits_dienst'];
    $stmt_orig = $pdo->prepare("SELECT * FROM diensten WHERE id = ? AND tenant_id = ?");
    $stmt_orig->execute([$splits_id, $tenantId]);
    $origineel = $stmt_orig->fetch();
    
    if ($origineel) {
        $nieuwe_naam = $origineel['naam'] . " (Deel 2)";
        $stmt_splits = $pdo->prepare("INSERT INTO diensten (tenant_id, naam, geplande_datum, chauffeur_id, status) VALUES (?, ?, ?, NULL, 'actief')");
        $stmt_splits->execute([$tenantId, $nieuwe_naam, $origineel['geplande_datum']]);
        
        $redirect_url = "weekrooster.php";
        if (isset($_GET['j']) && isset($_GET['w'])) {
            $redirect_url .= "?j=" . urlencode($_GET['j']) . "&w=" . urlencode($_GET['w']);
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

// --- DIENST VERWIJDEREN ---
if (isset($_GET['delete_dienst'])) {
    $del_id = (int)$_GET['delete_dienst'];
    $pdo->prepare("DELETE FROM diensten WHERE id = ? AND tenant_id = ?")->execute([$del_id, $tenantId]);
    $pdo->prepare("UPDATE ritten SET dienst_id = NULL WHERE dienst_id = ? AND tenant_id = ?")->execute([$del_id, $tenantId]);
    
    $redirect_url = "weekrooster.php";
    if (isset($_GET['j']) && isset($_GET['w'])) {
        $redirect_url .= "?j=" . urlencode($_GET['j']) . "&w=" . urlencode($_GET['w']);
    }
    header("Location: " . $redirect_url);
    exit;
}

include 'includes/header.php';

// --- WEEK LOGICA ---
$week = isset($_GET['w']) ? (int)$_GET['w'] : (int)date('W');
$jaar = isset($_GET['j']) ? (int)$_GET['j'] : (int)date('Y');

$vorige_week = $week - 1; $vorig_jaar = $jaar;
if ($vorige_week < 1) { $vorige_week = 52; $vorig_jaar--; }
$volgende_week = $week + 1; $volgend_jaar = $jaar;
if ($volgende_week > 52) { $volgende_week = 1; $volgend_jaar++; }

$dto = new DateTime();
$dto->setISODate($jaar, $week);
$start_datum = $dto->format('Y-m-d');
$eind_datum = clone $dto;
$eind_datum->modify('+6 days');
$eind_datum_str = $eind_datum->format('Y-m-d');

// --- NOTITIES OPSLAAN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notitie'])) {
    $notitie = $_POST['notitie'];
    $stmt_notitie = $pdo->prepare("INSERT INTO week_notities (tenant_id, jaar, week, notitie) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE notitie = VALUES(notitie)");
    $stmt_notitie->execute([$tenantId, $jaar, $week, $notitie]);
    echo "<script>window.location.href='weekrooster.php?j=$jaar&w=$week';</script>";
    exit;
}

$stmt_get_notitie = $pdo->prepare("SELECT notitie FROM week_notities WHERE tenant_id = ? AND jaar = ? AND week = ?");
$stmt_get_notitie->execute([$tenantId, $jaar, $week]);
$huidige_notitie = $stmt_get_notitie->fetchColumn();

// --- DATA OPHALEN ---
try {
    $stmt_chauf = $pdo->prepare("SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? AND archief = 0 ORDER BY voornaam ASC");
    $stmt_chauf->execute([$tenantId]);
    $chauffeurs_lijst = $stmt_chauf->fetchAll(PDO::FETCH_ASSOC);

    $stmt_diensten = $pdo->prepare("
        SELECT d.id, d.naam, d.geplande_datum, c.voornaam, c.achternaam 
        FROM diensten d 
        LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id AND c.tenant_id = d.tenant_id
        WHERE d.tenant_id = ? AND d.geplande_datum >= ? AND d.geplande_datum <= ?
        ORDER BY d.geplande_datum ASC, d.naam ASC
    ");
    $stmt_diensten->execute([$tenantId, $start_datum, $eind_datum_str]);
    $week_diensten = $stmt_diensten->fetchAll(PDO::FETCH_ASSOC);

    $stmt_calc = $pdo->prepare("
        SELECT 
            c.id, c.rit_datum, c.voertuig_id, c.chauffeur_id, NULL as dienst_id,
            k.bedrijfsnaam, k.voornaam, k.achternaam,
            v.voertuig_nummer, v.naam as bus_naam,
            ch.voornaam as chauf_naam,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_aankomst_best' LIMIT 1) as bestemming,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_vertrek_klant' LIMIT 1) as start_tijd,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_retour_klant' LIMIT 1) as eind_tijd,
            'calculatie' as bron
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        LEFT JOIN voertuigen v ON c.voertuig_id = v.id AND v.tenant_id = c.tenant_id
        LEFT JOIN chauffeurs ch ON c.chauffeur_id = ch.id AND ch.tenant_id = c.tenant_id
        WHERE c.tenant_id = ? AND c.rit_datum >= ? AND c.rit_datum <= ? AND (c.voertuig_id IS NOT NULL OR c.chauffeur_id IS NOT NULL) AND c.datum_bevestiging_verstuurd IS NOT NULL
    ");
    $stmt_calc->execute([$tenantId, $start_datum, $eind_datum_str]);
    $lijst_calculaties = $stmt_calc->fetchAll();

    $stmt_vaste = $pdo->prepare("
        SELECT 
            r.id, DATE(r.datum_start) as rit_datum, r.voertuig_id, r.chauffeur_id, r.dienst_id,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as bedrijfsnaam,
            '' as voornaam, '' as achternaam,
            v.voertuig_nummer, v.naam as bus_naam,
            ch.voornaam as chauf_naam,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as bestemming,
            (SELECT TIME(datum_start) FROM ritten WHERE id = r.id) as start_tijd,
            (SELECT TIME(datum_eind) FROM ritten WHERE id = r.id) as eind_tijd,
            'vaste_rit' as bron
        FROM ritten r
        LEFT JOIN voertuigen v ON r.voertuig_id = v.id AND v.tenant_id = r.tenant_id
        LEFT JOIN chauffeurs ch ON r.chauffeur_id = ch.id AND ch.tenant_id = r.tenant_id
        WHERE r.tenant_id = ? AND DATE(r.datum_start) >= ? AND DATE(r.datum_start) <= ? AND r.calculatie_id IS NULL AND (r.voertuig_id IS NOT NULL OR r.dienst_id IS NOT NULL OR r.chauffeur_id IS NOT NULL)
    ");
    $stmt_vaste->execute([$tenantId, $start_datum, $eind_datum_str]);
    $lijst_vaste_ritten = $stmt_vaste->fetchAll();

    $ritten_db = array_merge($lijst_calculaties, $lijst_vaste_ritten);
    usort($ritten_db, function($a, $b) {
        $timeA = strtotime($a['rit_datum'] . ' ' . ($a['start_tijd'] ?? '00:00'));
        $timeB = strtotime($b['rit_datum'] . ' ' . ($b['start_tijd'] ?? '00:00'));
        return $timeA - $timeB;
    });

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

$rooster = [];
$diensten_rooster = [];

for ($i = 0; $i < 7; $i++) {
    $d = clone $dto;
    $d->modify("+$i days");
    $datum_sleutel = $d->format('Y-m-d');
    $rooster[$datum_sleutel] = [];
    $diensten_rooster[$datum_sleutel] = [];
}

foreach ($week_diensten as $dienst) {
    $diensten_rooster[$dienst['geplande_datum']][] = $dienst;
}

foreach ($ritten_db as $rit) {
    if (!empty($rit['dienst_id'])) {
        foreach($week_diensten as $wd) {
            if ($wd['id'] == $rit['dienst_id']) {
                $chauf_label = !empty($wd['voornaam']) ? $wd['voornaam'] : "Nog in te delen";
                $rit['chauf_naam'] = $chauf_label;
            }
        }
    }
    $rooster[$rit['rit_datum']][] = $rit;
}

$dagen_nl = ['Monday'=>'maandag', 'Tuesday'=>'dinsdag', 'Wednesday'=>'woensdag', 'Thursday'=>'donderdag', 'Friday'=>'vrijdag', 'Saturday'=>'zaterdag', 'Sunday'=>'zondag'];
$maanden_nl = ['01'=>'januari', '02'=>'februari', '03'=>'maart', '04'=>'april', '05'=>'mei', '06'=>'juni', '07'=>'juli', '08'=>'augustus', '09'=>'september', '10'=>'oktober', '11'=>'november', '12'=>'december'];

function formatDatum($dateStr, $dagen_nl, $maanden_nl) {
    $d = new DateTime($dateStr);
    $dag = $dagen_nl[$d->format('l')];
    $maand = $maanden_nl[$d->format('m')];
    return "<strong>$dag</strong>, " . $d->format('d') . " $maand " . $d->format('Y');
}

$dagen_links = array_slice(array_keys($rooster), 0, 3);
$dagen_rechts = array_slice(array_keys($rooster), 3, 4);
?>

<style>
    .rooster-container { max-width: 1400px; margin: 20px auto; padding: 0 20px; font-family: 'Segoe UI', Arial, sans-serif; }
    .nav-balk { display: flex; justify-content: space-between; align-items: center; background: #003366; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
    .nav-balk a { color: white; text-decoration: none; font-weight: bold; padding: 8px 15px; background: rgba(255,255,255,0.1); border-radius: 4px; }
    .nav-balk a:hover { background: rgba(255,255,255,0.2); }
    
    .rooster-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    
    .dag-tabel { width: 100%; border-collapse: collapse; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); background: white; }
    .dag-tabel th { background: #f0f0f0; border: 2px solid #333; padding: 8px; text-align: left; font-size: 14px; color: #000; }
    .dag-tabel td { border: 1px solid #ccc; padding: 8px; font-size: 13px; color: #000; }
    
    .dag-titel { background: #fff !important; text-align: left !important; font-size: 16px !important; border-bottom: 2px solid #333 !important; position: relative;}
    .kolom-kop { background: #003366 !important; color: white !important; font-size: 12px !important; text-transform: uppercase; border-color: #003366 !important; }
    .ordernr-link { color: #0056b3; text-decoration: none; font-weight: bold; background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
    .ordernr-link:hover { background: #003366; color: white; }
    .notitie-vak { width: 100%; height: 100px; padding: 10px; border: 2px solid #333; font-family: inherit; font-size: 14px; box-sizing: border-box; }
    
    .badge-label { background: #e9ecef; color: #333; padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 5px; }

    .btn-voeg-dienst { position: absolute; right: 10px; top: 8px; background: #28a745; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; }
    .btn-voeg-dienst:hover { background: #218838; }
    .nieuw-dienst-form { display: none; background: #f8f9fa; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-top: 5px; }

    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        body { background: white; }
        header, .nav-balk, .geen-print, .btn-voeg-dienst, .nieuw-dienst-form { display: none !important; }
        .rooster-container { margin: 0; padding: 0; max-width: 100%; }
        .rooster-grid { gap: 15px; }
        .dag-tabel { box-shadow: none; margin-bottom: 15px; }
        .dag-tabel td, .dag-tabel th { padding: 4px 6px; font-size: 11px; }
        .kolom-kop { background: #e0e0e0 !important; color: black !important; -webkit-print-color-adjust: exact; }
        .badge-label { border: 1px solid #999; }
        a { text-decoration: none; color: black; }
    }
    @media (max-width: 900px) { .rooster-grid { grid-template-columns: 1fr; } }
</style>

<div class="rooster-container">
    
    <?php if ($dienst_msg): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-left: 5px solid #28a745; border-radius: 4px; margin-bottom: 15px; font-weight: bold;"><?= $dienst_msg ?></div>
    <?php endif; ?>
    <?php if ($dienst_error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-left: 5px solid #dc3545; border-radius: 4px; margin-bottom: 15px; font-weight: bold;"><?= $dienst_error ?></div>
    <?php endif; ?>

    <div class="nav-balk">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="?j=<?= $vorig_jaar ?>&w=<?= $vorige_week ?>">⬅️ Vorige</a>
            <h2 style="margin: 0;">ROOSTER WEEK <?= $week ?> (<?= $jaar ?>) <span style="font-weight:normal; font-size:16px;">| <?= date('d-m-Y', strtotime($start_datum)) ?> t/m <?= date('d-m-Y', strtotime($eind_datum_str)) ?></span></h2>
            <a href="?j=<?= $volgend_jaar ?>&w=<?= $volgende_week ?>">Volgende ➡️</a>
        </div>
        <a href="vaste_diensten_generator.php" style="background: #17a2b8; color: white; border: 1px solid #138496; border-radius: 4px; padding: 8px 15px; font-size: 13px; text-decoration: none;"><i class="fas fa-magic"></i> 🔄 Bulk Generator</a>
    </div>

    <?php
    function renderDagTabel($datum, $rooster, $diensten_rooster, $dagen_nl, $maanden_nl, $chauffeurs_lijst, $jaar, $week) {
        $html = '<table class="dag-tabel"><thead>';
        $html .= '<tr><th colspan="5" class="dag-titel">';
        $html .= formatDatum($datum, $dagen_nl, $maanden_nl);
        
        $form_id = 'form_' . md5($datum);
        $html .= '<button onclick="document.getElementById(\''.$form_id.'\').style.display=\'block\'" class="btn-voeg-dienst geen-print">+ Dienst</button>';
        
        $html .= '<form method="POST" id="'.$form_id.'" class="nieuw-dienst-form geen-print">';
        $html .= '<input type="hidden" name="actie" value="nieuwe_dienst">';
        $html .= '<input type="hidden" name="dienst_datum" value="'.$datum.'">';
        $html .= '<input type="text" name="dienst_naam" placeholder="Naam (bijv. Radeland 1)" required style="padding:4px; font-size:12px; width:130px; margin-right:5px;">';
        $html .= '<select name="dienst_chauffeur" style="padding:4px; font-size:12px; margin-right:5px;">';
        $html .= '<option value="">-- Optioneel: Chauffeur --</option>';
        foreach($chauffeurs_lijst as $chauf) {
            $html .= '<option value="'.$chauf['id'].'">'.htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']).'</option>';
        }
        $html .= '</select>';
        $html .= '<button type="submit" style="background:#003366; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer; font-size:11px;">Aanmaken</button>';
        $html .= '<button type="button" onclick="document.getElementById(\''.$form_id.'\').style.display=\'none\'" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:11px; margin-left:5px;">Annuleren</button>';
        $html .= '</form>';
        
        $html .= '</th></tr>';

        $html .= '<tr>';
        $html .= '<th class="kolom-kop" style="width: 35%;">Rit / Bestemming</th>';
        $html .= '<th class="kolom-kop" style="width: 15%;">Bus</th>';
        $html .= '<th class="kolom-kop" style="width: 20%;">Chauffeur</th>';
        $html .= '<th class="kolom-kop" style="width: 20%;">Tijden</th>';
        $html .= '<th class="kolom-kop" style="width: 10%;">Order / Actie</th>';
        $html .= '</tr></thead><tbody>';

        $heeft_inhoud = false;

        // 1. DIENSTEN ALS NORMALE RIJ + RITTEN ERONDER
        if (!empty($diensten_rooster[$datum])) {
            foreach($diensten_rooster[$datum] as $dienst) {
                $heeft_inhoud = true;
                $del_url = "?delete_dienst=".$dienst['id']."&j=".$jaar."&w=".$week;
                $splits_url = "?splits_dienst=".$dienst['id']."&j=".$jaar."&w=".$week;
                $chauf_label = !empty($dienst['voornaam']) ? $dienst['voornaam'] : "Nog in te delen";
                
                // Dienst Rij (Ziet eruit als een normale rit, maar met [DIENST] badge)
                $html .= '<tr>';
                $html .= '<td><span class="badge-label">DIENST</span> <strong>' . htmlspecialchars($dienst['naam']) . '</strong></td>';
                $html .= '<td style="text-align:center;">-</td>';
                $html .= '<td>' . htmlspecialchars($chauf_label) . '</td>';
                $html .= '<td style="text-align:center;">-</td>';
                $html .= '<td style="text-align:center;" class="geen-print">';
                $html .= '<a href="'.$splits_url.'" style="text-decoration:none; margin-right:8px; font-size:13px;" title="Splits (Kloon) deze dienst">✂️</a>';
                $html .= '<a href="'.$del_url.'" style="text-decoration:none; font-size:13px; color:#dc3545;" title="Verwijder Dienst" onclick="return confirm(\'Dienst verwijderen? Gekoppelde ritten vallen los.\');">✖</a>';
                $html .= '</td></tr>';

                // Gekoppelde Ritten als normale rijen er direct onder
                if (!empty($rooster[$datum])) {
                    foreach($rooster[$datum] as $rit) {
                        if ($rit['dienst_id'] == $dienst['id']) {
                            $klant = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                            $bestemming = mb_strimwidth($rit['bestemming'] ?? 'Onbekend', 0, 25, "..");
                            $bus = $rit['voertuig_nummer'] ? $rit['voertuig_nummer'] : '-';
                            $tijd_start = $rit['start_tijd'] ? date('H:i', strtotime($rit['start_tijd'])) : '..:..';
                            $tijd_eind = $rit['eind_tijd'] ? date('H:i', strtotime($rit['eind_tijd'])) : '..:..';
                            
                            $html .= '<tr>';
                            $html .= '<td><strong>' . htmlspecialchars($klant) . '</strong><br><span style="font-size:11px; color:#555;">' . htmlspecialchars($bestemming) . '</span></td>';
                            $html .= '<td style="text-align:center; font-weight:bold;">' . htmlspecialchars($bus) . '</td>';
                            $html .= '<td>' . htmlspecialchars($chauf_label) . '</td>';
                            $html .= '<td style="text-align:center;">' . $tijd_start . ' - ' . $tijd_eind . '</td>';
                            $html .= '<td style="text-align:center;">';
                            if ($rit['bron'] == 'calculatie') {
                                $html .= '<a href="calculatie/calculaties_bewerken.php?id=' . $rit['id'] . '" class="ordernr-link">#' . $rit['id'] . '</a>';
                            } else {
                                $html .= '<span style="color: #6c757d; font-size: 11px;">#' . $rit['id'] . '</span>';
                            }
                            $html .= '</td></tr>';
                        }
                    }
                }
            }
        }

        // 2. LOSSE RITTEN ALS NORMALE RIJ
        if (!empty($rooster[$datum])) {
            foreach($rooster[$datum] as $rit) {
                if (empty($rit['dienst_id'])) {
                    $heeft_inhoud = true;
                    $klant = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                    $bestemming = mb_strimwidth($rit['bestemming'] ?? 'Onbekend', 0, 25, "..");
                    $bus = $rit['voertuig_nummer'] ? $rit['voertuig_nummer'] : '-';
                    $chauf = $rit['chauf_naam'] ? $rit['chauf_naam'] : '<span style="color:red;font-weight:bold;">Niet ingedeeld</span>';
                    $tijd_start = $rit['start_tijd'] ? date('H:i', strtotime($rit['start_tijd'])) : '..:..';
                    $tijd_eind = $rit['eind_tijd'] ? date('H:i', strtotime($rit['eind_tijd'])) : '..:..';
                    
                    $html .= '<tr>';
                    $html .= '<td><strong>' . htmlspecialchars($klant) . '</strong>';
                    if ($rit['bron'] == 'vaste_rit') {
                        $html .= '<span class="badge-label" style="margin-left: 5px; background: #17a2b8; color: white;">VAST</span>';
                    }
                    $html .= '<br><span style="font-size:11px; color:#555;">' . htmlspecialchars($bestemming) . '</span></td>';
                    $html .= '<td style="text-align:center; font-weight:bold;">' . htmlspecialchars($bus) . '</td>';
                    $html .= '<td>' . $chauf . '</td>';
                    $html .= '<td style="text-align:center;">' . $tijd_start . ' - ' . $tijd_eind . '</td>';
                    $html .= '<td style="text-align:center;">';
                    if ($rit['bron'] == 'calculatie') {
                        $html .= '<a href="calculatie/calculaties_bewerken.php?id=' . $rit['id'] . '" class="ordernr-link">#' . $rit['id'] . '</a>';
                    } else {
                        $html .= '<span style="color: #6c757d; font-size: 11px;">#' . $rit['id'] . '</span>';
                    }
                    $html .= '</td></tr>';
                }
            }
        }

        if (!$heeft_inhoud) {
            $html .= '<tr><td colspan="5" style="color: #999; text-align: center; font-style: italic;">Geen ritten of diensten gepland</td></tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }
    ?>

    <div class="rooster-grid">
        <div>
            <?php foreach($dagen_links as $datum) { echo renderDagTabel($datum, $rooster, $diensten_rooster, $dagen_nl, $maanden_nl, $chauffeurs_lijst, $jaar, $week); } ?>
        </div>
        <div>
            <?php foreach($dagen_rechts as $datum) { echo renderDagTabel($datum, $rooster, $diensten_rooster, $dagen_nl, $maanden_nl, $chauffeurs_lijst, $jaar, $week); } ?>
        </div>
    </div>

    <div style="margin-top: 10px; background: white; padding: 15px; border: 2px solid #333; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <h3 style="margin-top: 0; font-size: 16px;">Opmerkingen / Bijzonderheden voor Week <?= $week ?></h3>
        <form method="POST" class="geen-print">
            <textarea name="notitie" class="notitie-vak" placeholder="Typ hier bijzonderheden zoals 'Hilbert kan eventueel 26e werken...'"><?= htmlspecialchars($huidige_notitie ?? '') ?></textarea>
            <button type="submit" style="margin-top: 10px; background: #28a745; color: white; border: none; padding: 8px 15px; font-weight: bold; cursor: pointer;">Opslaan</button>
        </form>
        <div style="display: none; white-space: pre-wrap; font-size: 14px;" onload="this.style.display='block'" class="print-only"><?= htmlspecialchars($huidige_notitie ?? '') ?></div>
        <style> @media print { .print-only { display: block !important; } } </style>
    </div>

</div>

<?php include 'includes/footer.php'; ?>