<?php
// Bestand: beheer/ritten.php
// VERSIE: Kantoor Portaal 5.7 - UX Fixes (Slimme prijs extractie, Zachte kleuren, Kolombreedtes & Accordion geheugen)

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open'; 

$zoek_chauffeur = isset($_GET['zoek_chauffeur']) ? $_GET['zoek_chauffeur'] : '';
$zoek_datum_van = isset($_GET['zoek_datum_van']) ? $_GET['zoek_datum_van'] : '';
$zoek_datum_tot = isset($_GET['zoek_datum_tot']) ? $_GET['zoek_datum_tot'] : '';

$stmtKlanten = $pdo->prepare("SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE tenant_id = ? ORDER BY bedrijfsnaam, achternaam ASC");
$stmtKlanten->execute([$tenantId]);
$klanten_lijst = $stmtKlanten->fetchAll();

$stmtChauffeurs = $pdo->prepare("SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? ORDER BY voornaam ASC");
$stmtChauffeurs->execute([$tenantId]);
$chauffeurs_lijst = $stmtChauffeurs->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['actie']) && ($_POST['actie'] == 'verwerk_rit' || $_POST['actie'] == 'doorboek_losse_rit')) {
        $rit_id = (int)$_POST['rit_id'];
        
        // Fix voor de redirect zodat mapjes 'LOS' open blijven staan!
        $posted_dienst_id = $_POST['dienst_id'] ?? '';
        $dienst_id_db = (!empty($posted_dienst_id) && $posted_dienst_id !== 'LOS') ? (int)$posted_dienst_id : null; 
        
        $tijd = $_POST['tijd']; 
        
        $klant_id = null;
        if (!empty($_POST['klant_input'])) {
            $parts = explode(' - ', $_POST['klant_input']);
            if (is_numeric($parts[0])) $klant_id = (int)$parts[0];
        }
        
        $chauffeur_id = !empty($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : null;
        $van_adres = $_POST['van_adres'];
        $naar_adres = $_POST['naar_adres'];
        $betaalwijze = $_POST['betaalwijze'];
        $bedrag = !empty($_POST['bedrag']) ? str_replace(',', '.', $_POST['bedrag']) : 0;
        $bijzonderheden = trim($_POST['bijzonderheden']);
        
        try {
            $pdo->beginTransaction();
            $stmt_origineel = $pdo->prepare("SELECT datum_start FROM ritten WHERE id = ? AND tenant_id = ?");
            $stmt_origineel->execute([$rit_id, $tenantId]);
            $orig = $stmt_origineel->fetch();
            if (!$orig) {
                throw new RuntimeException("Rit niet gevonden binnen deze tenant.");
            }
            $nieuwe_datum_start = date('Y-m-d', strtotime($orig['datum_start'])) . ' ' . $tijd . ':00';

            if ($klant_id !== null) {
                $stmtKlant = $pdo->prepare("SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtKlant->execute([$klant_id, $tenantId]);
                if (!$stmtKlant->fetchColumn()) {
                    throw new RuntimeException("Klant hoort niet bij deze tenant.");
                }
            }

            if ($chauffeur_id !== null) {
                $stmtChauffeur = $pdo->prepare("SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtChauffeur->execute([$chauffeur_id, $tenantId]);
                if (!$stmtChauffeur->fetchColumn()) {
                    throw new RuntimeException("Chauffeur hoort niet bij deze tenant.");
                }
            }

            $stmt_rit = $pdo->prepare("UPDATE ritten SET datum_start = ?, klant_id = ?, chauffeur_id = ?, betaalwijze = ?, betaald_bedrag = ?, werk_notities = ?, status = 'voltooid' WHERE id = ? AND tenant_id = ?");
            $stmt_rit->execute([$nieuwe_datum_start, $klant_id, $chauffeur_id, $betaalwijze, $bedrag, $bijzonderheden, $rit_id, $tenantId]);

            $stmt_check = $pdo->prepare("SELECT id FROM ritregels WHERE rit_id = ? AND tenant_id = ?");
            $stmt_check->execute([$rit_id, $tenantId]);
            if ($stmt_check->rowCount() > 0) {
                $pdo->prepare("UPDATE ritregels SET van_adres = ?, naar_adres = ? WHERE rit_id = ? AND tenant_id = ?")->execute([$van_adres, $naar_adres, $rit_id, $tenantId]);
            } else {
                $pdo->prepare("INSERT INTO ritregels (tenant_id, rit_id, van_adres, naar_adres) VALUES (?, ?, ?, ?)")->execute([$tenantId, $rit_id, $van_adres, $naar_adres]);
            }

            $redirect_dienst_url = !empty($posted_dienst_id) ? "&open_dienst=" . urlencode($posted_dienst_id) : "";

            if ($_POST['actie'] == 'doorboek_losse_rit') {
                $dt_only = date('Y-m-d', strtotime($nieuwe_datum_start));
                $stmt_dienst = $pdo->prepare("INSERT INTO diensten (tenant_id, naam, geplande_datum, start_tijd, eind_tijd, chauffeur_id, status) VALUES (?, ?, ?, ?, ?, ?, 'gecontroleerd')");
                $stmt_dienst->execute([$tenantId, 'Losse Rit', $dt_only, $nieuwe_datum_start, $nieuwe_datum_start, $chauffeur_id]);
                $new_dienst_id = $pdo->lastInsertId();

                $pdo->prepare("UPDATE ritten SET dienst_id = ? WHERE id = ? AND tenant_id = ?")->execute([$new_dienst_id, $rit_id, $tenantId]);
                
                $pdo->commit();
                header("Location: ritten.php?filter=".$filter.$redirect_dienst_url."&msg=los_doorgeboekt");
                exit;
            } else {
                $pdo->commit();
                header("Location: ritten.php?filter=".$filter.$redirect_dienst_url."&msg=rit_verwerkt");
                exit;
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Fout bij opslaan rit: " . $e->getMessage());
        }
    }

    if (isset($_POST['actie']) && $_POST['actie'] == 'verwijder_rit') {
        $rit_id = (int)$_POST['rit_id'];
        $posted_dienst_id = $_POST['dienst_id'] ?? '';
        
        $pdo->prepare("DELETE FROM ritregels WHERE rit_id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
        $pdo->prepare("DELETE FROM ritten WHERE id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
        
        $redirect_dienst_url = !empty($posted_dienst_id) ? "&open_dienst=" . urlencode($posted_dienst_id) : "";
        header("Location: ritten.php?filter=".$filter.$redirect_dienst_url."&msg=rit_verwijderd");
        exit;
    }

    if (isset($_POST['actie']) && $_POST['actie'] == 'goedkeuren_dienst') {
        $dienst_id = (int)$_POST['dienst_id'];
        $pdo->prepare("UPDATE diensten SET status = 'gecontroleerd' WHERE id = ? AND tenant_id = ?")->execute([$dienst_id, $tenantId]);
        header("Location: ritten.php?filter=".$filter."&msg=dienst_goedgekeurd");
        exit;
    }
}

// --- TABBLAD LOGICA ---
$diensten_inbox = [];
$losse_ritten = [];

if ($filter == 'historie') {
    $query = "SELECT d.*, c.voornaam, c.achternaam FROM diensten d LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id AND c.tenant_id = d.tenant_id WHERE d.tenant_id = ? AND d.status = 'gecontroleerd'";
    $params = [$tenantId];

    if (!empty($zoek_chauffeur)) {
        $query .= " AND d.chauffeur_id = ?";
        $params[] = $zoek_chauffeur;
    }
    if (!empty($zoek_datum_van)) {
        $query .= " AND DATE(d.start_tijd) >= ?";
        $params[] = $zoek_datum_van;
    }
    if (!empty($zoek_datum_tot)) {
        $query .= " AND DATE(d.start_tijd) <= ?";
        $params[] = $zoek_datum_tot;
    }

    $query .= " ORDER BY d.eind_tijd DESC LIMIT 50";
    $stmt_diensten = $pdo->prepare($query);
    $stmt_diensten->execute($params);
    $diensten_inbox = $stmt_diensten->fetchAll();

} elseif ($filter == 'onverwerkt') {
    $stmt_diensten = $pdo->prepare("SELECT d.*, c.voornaam, c.achternaam FROM diensten d LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id AND c.tenant_id = d.tenant_id WHERE d.tenant_id = ? AND d.status NOT IN ('afgerond', 'gecontroleerd') AND d.geplande_datum < CURDATE() ORDER BY d.geplande_datum DESC");
    $stmt_diensten->execute([$tenantId]);
    $diensten_inbox = $stmt_diensten->fetchAll();

    $stmt_losse = $pdo->prepare("
        SELECT r.*, COALESCE(r.klant_id, c.klant_id) as werkende_klant_id, c.prijs as calc_prijs,
               (SELECT van_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_van,
               (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_naar,
               (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id ORDER BY id ASC LIMIT 1) as calc_van,
               (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id AND type = 't_aankomst_best' LIMIT 1) as calc_naar
        FROM ritten r 
        LEFT JOIN calculaties c ON r.calculatie_id = c.id AND c.tenant_id = r.tenant_id
        WHERE r.dienst_id IS NULL 
        AND r.tenant_id = ?
        AND DATE(r.datum_start) < CURDATE() 
        ORDER BY r.datum_start DESC
    ");
    $stmt_losse->execute([$tenantId]);
    $losse_ritten = $stmt_losse->fetchAll();

    if (count($losse_ritten) > 0) {
        $diensten_inbox[] = [
            'id' => 'LOS',
            'naam' => 'LOSSE RITTEN (Geen mapje)',
            'geplande_datum' => date('Y-m-d', strtotime('-1 day')),
            'start_tijd' => null,
            'eind_tijd' => null,
            'voornaam' => 'Diverse',
            'achternaam' => 'Chauffeurs',
            'status' => 'actief'
        ];
    }

} else {
    $stmt_diensten = $pdo->prepare("SELECT d.*, c.voornaam, c.achternaam FROM diensten d LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id AND c.tenant_id = d.tenant_id WHERE d.tenant_id = ? AND d.status = 'afgerond' ORDER BY d.eind_tijd ASC");
    $stmt_diensten->execute([$tenantId]);
    $diensten_inbox = $stmt_diensten->fetchAll();
}

include 'includes/header.php';
?>

<style>
    body { background-color: #f4f7f6; font-family: 'Segoe UI', Arial, sans-serif; }
    .container { max-width: 100%; padding: 20px; } 

    .tab-nav { display: flex; background: #fff; border-radius: 8px 8px 0 0; border-bottom: 2px solid #003366; }
    .tab-link { flex: 1; text-align: center; padding: 15px 10px; text-decoration: none; color: #555; font-size: 15px; font-weight: bold; background: #e9ecef; border-right: 1px solid #ccc; transition: 0.2s; }
    .tab-link:last-child { border-right: none; }
    .tab-link:hover { background: #dee2e6; }
    .tab-link.actief { background: #fff; color: #003366; border-top: 3px solid #003366; border-bottom: 2px solid #fff; margin-bottom: -2px; }

    .tab-content { background: #fff; padding: 20px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); min-height: 500px; }

    .filter-balk { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; }
    .filter-knoppen { display: flex; gap: 10px; }
    .btn-filter { padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #ccc; }
    .btn-filter.actief { background: #003366; color: white; border-color: #003366; }
    .btn-filter.inactief { background: #f8f9fa; color: #555; }
    
    /* Zachtere kleuren voor het 'Onverwerkt' mapje en de knop */
    .btn-onverwerkt { background: #fff; color: #d9534f; border-color: #d9534f; }
    .btn-onverwerkt.actief { background: #d9534f; color: white; border-color: #d9534f; }
    .btn-onverwerkt:hover { background: #f2dede; color: #d9534f; }
    
    .zoek-balk { display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 6px; border: 1px solid #ddd; }
    .zoek-input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
    .btn-zoek { background: #17a2b8; color: white; border: none; padding: 7px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }

    .master-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
    .dienst-header { background: #1a365d; color: white; cursor: pointer; transition: 0.2s; }
    .dienst-header:hover { background: #2c5282; }
    .dienst-header.losse-ritten { background: #d9534f; border-top: 3px solid #fff; } /* Zachter rood/koraal */
    .dienst-header.losse-ritten:hover { background: #c9302c; }
    .dienst-header td { padding: 8px 10px; border-bottom: 2px solid #fff; vertical-align: middle; white-space: nowrap; }
    
    .btn-dienst-ok { background: #48bb78; color: white; border: 1px solid #2f855a; padding: 6px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; float: right; transition: 0.2s; }
    
    .ritten-container { display: none; background: #f8f9fa; border: 2px solid #1a365d; border-top: none; }
    .excel-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .excel-table th { background: #e2e8f0; color: #4a5568; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #cbd5e0; }
    .excel-table td { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    
    .grid-input, .grid-select { width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #cbd5e0; border-radius: 3px; font-size: 12px; font-family: inherit; background: #fff; }
    .grid-input:focus, .grid-select:focus { outline: none; border-color: #3182ce; box-shadow: 0 0 0 1px #3182ce; }
    
    .btn-action { padding: 6px 10px; border: none; border-radius: 3px; font-weight: bold; cursor: pointer; color: white; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; }
    .btn-verwerk { background: #3182ce; }
    .btn-verwijder { background: #e53e3e; }
    .btn-doorboek { background: #28a745; }
    .rit-done { background: #f0fff4 !important; opacity: 0.9; border-left: 4px solid #28a745;}
    
    .msg { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    .msg-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
    
    .bedrag-regel { font-size: 13px; display: inline-flex; gap: 15px; align-items: center; }
</style>

<datalist id="klanten_zoeklijst">
    <?php foreach($klanten_lijst as $k): ?>
        <option value="<?php echo $k['id'] . ' - ' . htmlspecialchars($k['bedrijfsnaam'] ?: ($k['voornaam'].' '.$k['achternaam'])); ?>">
    <?php endforeach; ?>
</datalist>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="margin: 0; color: #003366;">Administratie & Controle</h1>
    </div>

    <div class="tab-nav">
        <a href="ritten.php" class="tab-link actief">1. Diensten</a>
        <a href="kas.php" class="tab-link">2. Kas</a>
        <a href="pin.php" class="tab-link">3. Pin</a>
        <a href="facturatie.php" class="tab-link">4. Facturatie</a>
    </div>

    <div class="tab-content">
        
        <div class="filter-balk">
            <div class="filter-knoppen">
                <a href="?filter=open" class="btn-filter <?php echo ($filter == 'open') ? 'actief' : 'inactief'; ?>">Inbox (Te Controleren)</a>
                <a href="?filter=onverwerkt" class="btn-filter btn-onverwerkt <?php echo ($filter == 'onverwerkt') ? 'actief' : ''; ?>">⚠️ Onverwerkt / Vergeten</a>
                <a href="?filter=historie" class="btn-filter <?php echo ($filter == 'historie') ? 'actief' : 'inactief'; ?>">Archief (Goedgekeurd)</a>
            </div>

            <?php if($filter == 'historie'): ?>
            <form method="GET" class="zoek-balk">
                <input type="hidden" name="filter" value="historie">
                <span style="font-size: 12px; font-weight: bold; color: #555;">Zoek op:</span>
                <select name="zoek_chauffeur" class="zoek-input">
                    <option value="">Alle Chauffeurs</option>
                    <?php foreach($chauffeurs_lijst as $ch): ?>
                        <option value="<?php echo $ch['id']; ?>" <?php echo ($zoek_chauffeur == $ch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ch['voornaam'].' '.$ch['achternaam']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="zoek_datum_van" value="<?php echo $zoek_datum_van; ?>" class="zoek-input" title="Vanaf datum">
                <input type="date" name="zoek_datum_tot" value="<?php echo $zoek_datum_tot; ?>" class="zoek-input" title="Tot en met datum">
                <button type="submit" class="btn-zoek"><i class="fas fa-search"></i> Zoeken</button>
                <a href="?filter=historie" style="font-size: 12px; color: #dc3545; text-decoration: none; margin-left: 5px;">Reset</a>
            </form>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'rit_verwerkt') echo '<div class="msg msg-success">✅ Rit is succesvol opgeslagen.</div>'; ?>
            <?php if ($_GET['msg'] == 'los_doorgeboekt') echo '<div class="msg msg-success">✅ Losse rit opgeslagen én succesvol doorgeboekt naar administratie!</div>'; ?>
            <?php if ($_GET['msg'] == 'rit_verwijderd') echo '<div class="msg msg-error">🗑️ Rit is verwijderd.</div>'; ?>
            <?php if ($_GET['msg'] == 'dienst_goedgekeurd') echo '<div class="msg msg-success">✅ Dienst goedgekeurd! Verwerkt naar Kas, Pin en Facturatie.</div>'; ?>
        <?php endif; ?>

        <?php if (count($diensten_inbox) == 0): ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px;">
                <i class="fas fa-check-double" style="font-size: 50px; color: #28a745; margin-bottom: 15px;"></i>
                <h2 style="margin:0; color:#333;">Alles is weggewerkt!</h2>
            </div>
        <?php else: ?>

            <table class="master-table">
                <tbody>
                    <?php foreach ($diensten_inbox as $dienst): 
                        $disp_datum = !empty($dienst['start_tijd']) ? date('d-m-Y', strtotime($dienst['start_tijd'])) : (!empty($dienst['geplande_datum']) ? date('d-m-Y', strtotime($dienst['geplande_datum'])) : '-');
                        $disp_tijd = (!empty($dienst['start_tijd']) && !empty($dienst['eind_tijd'])) ? date('H:i', strtotime($dienst['start_tijd'])) . ' - ' . date('H:i', strtotime($dienst['eind_tijd'])) : '<span style="color:#ffcc00;"><i class="fas fa-exclamation-triangle"></i> Niet Gereden / Niet Afgerond</span>';

                        $is_los = ($dienst['id'] === 'LOS');
                        $header_class = $is_los ? "dienst-header losse-ritten" : "dienst-header";
                        $naam_weergave = $is_los ? $dienst['naam'] : ($dienst['naam'] ? '📁 ' . $dienst['naam'] : 'Dienst #' . $dienst['id']);

                        if ($is_los) {
                            $ritten = $losse_ritten;
                        } else {
                            $stmt_ritten = $pdo->prepare("
                                SELECT r.*, COALESCE(r.klant_id, c.klant_id) as werkende_klant_id, c.prijs as calc_prijs,
                                       (SELECT van_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_van,
                                       (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_naar,
                                       (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id ORDER BY id ASC LIMIT 1) as calc_van,
                                       (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id AND type = 't_aankomst_best' LIMIT 1) as calc_naar
                                FROM ritten r 
                                LEFT JOIN calculaties c ON r.calculatie_id = c.id AND c.tenant_id = r.tenant_id
                                WHERE r.dienst_id = ? AND r.tenant_id = ? ORDER BY r.datum_start ASC
                            ");
                            $stmt_ritten->execute([$dienst['id'], $tenantId]);
                            $ritten = $stmt_ritten->fetchAll();
                        }
                        
                        $totaal_contant = 0; $totaal_pin = 0; $totaal_rek = 0;
                        foreach($ritten as $r) {
                            $bedrag = (float)$r['betaald_bedrag'];
                            if($r['betaalwijze'] == 'Contant') $totaal_contant += $bedrag;
                            elseif($r['betaalwijze'] == 'PIN') $totaal_pin += $bedrag;
                            else $totaal_rek += $bedrag;
                        }
                        $totaal_dienst = $totaal_contant + $totaal_pin + $totaal_rek;
                    ?>
                        <tr class="<?php echo $header_class; ?>" onclick="toggleDienst('dienst_<?php echo $dienst['id']; ?>')">
                            <td width="30" style="text-align: center; font-size: 16px; font-weight: bold;" id="icon_dienst_<?php echo $dienst['id']; ?>">+</td>
                            <td><strong><?php echo $naam_weergave; ?></strong></td>
                            <td><?php echo $is_los ? 'Diverse Data' : $disp_datum; ?></td>
                            <td><?php echo htmlspecialchars($dienst['voornaam'] . ' ' . $dienst['achternaam']); ?></td>
                            <td><?php echo $disp_tijd; ?></td>
                            
                            <td style="text-align: right;">
                                <div class="bedrag-regel">
                                    <span style="color:#e2e8f0;">Contant: € <?php echo number_format($totaal_contant,2,',','.'); ?></span>
                                    <span style="color:#e2e8f0;">PIN: € <?php echo number_format($totaal_pin,2,',','.'); ?></span>
                                    <span style="color:#e2e8f0;">Rek: € <?php echo number_format($totaal_rek,2,',','.'); ?></span>
                                    <strong style="color:#fff; border-left: 2px solid rgba(255,255,255,0.3); padding-left: 10px;">Opbrengst: € <?php echo number_format($totaal_dienst,2,',','.'); ?></strong>
                                </div>
                            </td>
                            
                            <td width="160" onclick="event.stopPropagation();">
                                <?php if($filter == 'historie'): ?>
                                    <span style="float: right; color: #68d391; font-weight: bold;"><i class="fas fa-archive"></i> Gearchiveerd</span>
                                <?php elseif(!$is_los): ?>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Dienst doorboeken naar Kas, Pin en Facturatie?');">
                                        <input type="hidden" name="actie" value="goedkeuren_dienst">
                                        <input type="hidden" name="dienst_id" value="<?php echo $dienst['id']; ?>">
                                        <button type="submit" class="btn-dienst-ok">Dienst Doorboeken</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr id="dienst_<?php echo $dienst['id']; ?>" class="ritten-container">
                            <td colspan="7" style="padding: 15px; <?php echo $is_los ? 'border: 2px solid #d9534f; border-top: none;' : ''; ?>">
                                <table class="excel-table">
                                    <thead>
                                        <tr>
                                            <th width="75">Datum / Tijd</th>
                                            <th width="15%">Van</th>
                                            <th width="15%">Naar</th>
                                            <th width="18%">Klant (Typen = Zoeken)</th>
                                            <th width="12%">Chauffeur</th> 
                                            <th width="100">Betaling</th>
                                            <th width="100">Bedrag (€)</th>
                                            <th width="18%">Bijzonderheden</th>
                                            <th width="120">Actie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($ritten) == 0): ?>
                                            <tr><td colspan="9" style="color:#777; font-style:italic;">Geen ritten aan deze dienst gekoppeld.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($ritten as $rit): 
                                                $klant_display = '';
                                                $huidig_klant_id = !empty($rit['werkende_klant_id']) ? $rit['werkende_klant_id'] : $rit['klant_id'];
                                                if ($huidig_klant_id) {
                                                    foreach($klanten_lijst as $k) {
                                                        if ($k['id'] == $huidig_klant_id) {
                                                            $klant_display = $k['id'] . ' - ' . htmlspecialchars($k['bedrijfsnaam'] ?: ($k['voornaam'].' '.$k['achternaam']));
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                // --- SLIMME TEKST LEZER VOOR DE EXTRA PRIJS ---
                                                $werk_notities = $rit['werk_notities'];
                                                $extra_prijs_gevonden = 0;
                                                
                                                // Zoek naar "Ingevoerde prijs: € 217,50" in de notities
                                                if (preg_match('/Ingevoerde prijs:\s*€\s*([0-9,.]+)/i', $werk_notities, $matches)) {
                                                    // Haal het bedrag eruit en converteer de komma naar een punt
                                                    $extra_prijs_gevonden = (float)str_replace(',', '.', $matches[1]);
                                                    // Knip de zin "Ingevoerde prijs: € 217,50" uit de tekst, zodat de bijzonderheden strak blijven
                                                    $werk_notities = trim(str_replace($matches[0], '', $werk_notities));
                                                }

                                                // Kies het juiste bedrag om te tonen in het vakje
                                                if ($rit['betaald_bedrag'] > 0) {
                                                    $toon_bedrag = $rit['betaald_bedrag'];
                                                } elseif ($extra_prijs_gevonden > 0) {
                                                    $toon_bedrag = $extra_prijs_gevonden;
                                                } else {
                                                    $toon_bedrag = $rit['calc_prijs'] ?? 0;
                                                }
                                            ?>
                                            <tr class="<?php echo ($rit['status'] == 'voltooid') ? 'rit-done' : ''; ?>">
                                                <form id="form_rit_<?php echo $rit['id']; ?>" method="POST">
                                                    <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                                    <input type="hidden" name="dienst_id" value="<?php echo $dienst['id']; ?>">
                                                </form>
                                                
                                                <td>
                                                    <?php if($is_los): ?>
                                                        <div style="font-size: 10px; color: #d9534f; font-weight: bold; margin-bottom: 2px; text-align: center;"><?php echo date('d-m', strtotime($rit['datum_start'])); ?></div>
                                                    <?php endif; ?>
                                                    <input type="time" name="tijd" value="<?php echo date('H:i', strtotime($rit['datum_start'])); ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input" required>
                                                </td>
                                                <td><input type="text" name="van_adres" value="<?php echo htmlspecialchars($rit['rr_van'] ?: $rit['calc_van']); ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input"></td>
                                                <td><input type="text" name="naar_adres" value="<?php echo htmlspecialchars($rit['rr_naar'] ?: $rit['calc_naar']); ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input"></td>
                                                <td><input type="text" name="klant_input" list="klanten_zoeklijst" value="<?php echo $klant_display; ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input" autocomplete="off"></td>
                                                
                                                <td>
                                                    <select name="chauffeur_id" form="form_rit_<?php echo $rit['id']; ?>" class="grid-select">
                                                        <option value="">-- Kies --</option>
                                                        <?php foreach($chauffeurs_lijst as $ch): ?>
                                                            <option value="<?php echo $ch['id']; ?>" <?php echo ($rit['chauffeur_id'] == $ch['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($ch['voornaam'].' '.$ch['achternaam']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                
                                                <td>
                                                    <select name="betaalwijze" form="form_rit_<?php echo $rit['id']; ?>" class="grid-select">
                                                        <option value="Op Rekening" <?php echo ($rit['betaalwijze'] == 'Op Rekening' || $rit['betaalwijze'] == 'Rekening') ? 'selected' : ''; ?>>Op Rekening</option>
                                                        <option value="Contant" <?php echo ($rit['betaalwijze'] == 'Contant') ? 'selected' : ''; ?>>Contant</option>
                                                        <option value="PIN" <?php echo ($rit['betaalwijze'] == 'PIN') ? 'selected' : ''; ?>>PIN</option>
                                                    </select>
                                                </td>
                                                
                                                <td><input type="number" step="0.01" name="bedrag" value="<?php echo $toon_bedrag; ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input" style="font-weight:bold; color:#0056b3;"></td>
                                                
                                                <td><input type="text" name="bijzonderheden" value="<?php echo htmlspecialchars($werk_notities); ?>" form="form_rit_<?php echo $rit['id']; ?>" class="grid-input" style="font-size:11px;"></td>
                                                
                                                <td style="text-align: center;">
                                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                                        <?php if($is_los): ?>
                                                            <button type="submit" form="form_rit_<?php echo $rit['id']; ?>" name="actie" value="verwerk_rit" class="btn-action btn-verwerk" title="Alleen Opslaan"><i class="fas fa-save"></i></button>
                                                            <button type="submit" form="form_rit_<?php echo $rit['id']; ?>" name="actie" value="doorboek_losse_rit" class="btn-action btn-doorboek" title="Opslaan & Direct Doorboeken!" onclick="return confirm('Deze losse rit opslaan en direct naar de Administratie/Facturatie schieten?');"><i class="fas fa-check-double"></i></button>
                                                        <?php else: ?>
                                                            <button type="submit" form="form_rit_<?php echo $rit['id']; ?>" name="actie" value="verwerk_rit" class="btn-action btn-verwerk" title="Opslaan"><i class="fas fa-save"></i></button>
                                                        <?php endif; ?>
                                                        
                                                        <form method="POST" style="margin:0;" onsubmit="return confirm('🚨 WEET JE ZEKER dat je dit wilt verwijderen?');">
                                                            <input type="hidden" name="actie" value="verwijder_rit">
                                                            <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                                            <input type="hidden" name="dienst_id" value="<?php echo $is_los ? 'LOS' : $dienst['id']; ?>">
                                                            <button type="submit" class="btn-action btn-verwijder" title="Verwijderen"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDienst(rowId) {
    var row = document.getElementById(rowId);
    var icon = document.getElementById('icon_' + rowId);
    if (row.style.display === "table-row") {
        row.style.display = "none";
        icon.innerHTML = "+";
    } else {
        row.style.display = "table-row";
        icon.innerHTML = "−";
    }
}
<?php if(isset($_GET['open_dienst'])): ?>
    window.onload = function() { toggleDienst('dienst_<?php echo $_GET['open_dienst']; ?>'); };
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>