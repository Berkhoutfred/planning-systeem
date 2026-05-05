<?php
// Bestand: beheer/kas.php
// VERSIE: Kantoor Portaal - Tabblad 2 (Kasopmaak & Dagstaat - Compacte Excel Stijl)

include '../beveiliging.php';
require 'includes/db.php';

$actief_tab = 'kassa';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open'; 

// ==========================================
// ACTIES VERWERKEN (Kas Tellen)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['actie']) && $_POST['actie'] == 'opslaan_kas') {
        $dienst_id = (int)$_POST['dienst_id'];
        $kas_datum = $_POST['kas_datum']; 
        $omzet = (float)$_POST['omzet_bedrag'];
        $geteld = !empty($_POST['kas_afgedragen']) ? (float)str_replace(',', '.', $_POST['kas_afgedragen']) : 0;
        $verschil = $geteld - $omzet;
        $notitie = trim($_POST['kas_notitie']);
        
        try {
            $stmt = $pdo->prepare("UPDATE diensten SET kas_afgedragen = ?, kas_verschil = ?, kas_notitie = ?, kas_status = 'Akkoord' WHERE id = ?");
            $stmt->execute([$geteld, $verschil, $notitie, $dienst_id]);
            
            header("Location: kas.php?filter=".$filter."&open_dag=".$kas_datum."&msg=kas_opgeslagen");
            exit;
        } catch (PDOException $e) {
            die("Fout bij opslaan kas: " . $e->getMessage());
        }
    }
}

// ==========================================
// DATA OPHALEN & GROEPEREN PER DAG
// ==========================================
$stmt_kas = $pdo->query("
    SELECT d.id, DATE(d.start_tijd) as kas_datum, c.voornaam, c.achternaam,
           d.kas_afgedragen, d.kas_verschil, d.kas_notitie, d.kas_status,
           SUM(r.betaald_bedrag) as omzet_bedrag
    FROM diensten d
    LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id
    JOIN ritten r ON d.id = r.dienst_id
    WHERE d.status = 'gecontroleerd' AND r.betaalwijze = 'Contant'
    GROUP BY d.id
    HAVING omzet_bedrag > 0
    ORDER BY kas_datum ASC, d.start_tijd ASC
");
$alle_kas_diensten = $stmt_kas->fetchAll();

$dagen_open = [];
$dagen_historie = [];
$grouped_by_date = [];

foreach($alle_kas_diensten as $d) {
    $datum = $d['kas_datum'];
    if(!isset($grouped_by_date[$datum])) {
        $grouped_by_date[$datum] = [];
    }
    $grouped_by_date[$datum][] = $d;
}

foreach($grouped_by_date as $datum => $diensten) {
    $dag_is_open = false;
    foreach($diensten as $d) {
        if($d['kas_status'] !== 'Akkoord') {
            $dag_is_open = true;
            break;
        }
    }
    
    if($dag_is_open) {
        $dagen_open[$datum] = $diensten;
    } else {
        $dagen_historie[$datum] = $diensten;
    }
}

$weergave_dagen = ($filter == 'historie') ? $dagen_historie : $dagen_open;

if ($filter == 'historie') {
    krsort($weergave_dagen);
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

    .filter-balk { margin-bottom: 15px; display: flex; gap: 10px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
    .btn-filter { padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #ccc; }
    .btn-filter.actief { background: #003366; color: white; border-color: #003366; }
    .btn-filter.inactief { background: #f8f9fa; color: #555; }

    .master-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
    
    /* DAG HEADER - PLAT EN STRAK ZOALS TAB 1 */
    .dag-header { background: #1a365d; color: white; cursor: pointer; transition: 0.2s; }
    .dag-header:hover { background: #2c5282; }
    .dag-header td { padding: 8px 10px; border-bottom: 2px solid #fff; vertical-align: middle; white-space: nowrap; }
    .dag-header strong { font-size: 14px; letter-spacing: 0.5px; }
    
    .dag-container { display: none; background: #f8f9fa; border: 2px solid #1a365d; border-top: none; }
    
    /* EXCEL STIJL SUBTABEL */
    .excel-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .excel-table th { background: #e2e8f0; color: #4a5568; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #cbd5e0; }
    .excel-table td { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    
    .grid-input { width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #cbd5e0; border-radius: 3px; font-size: 12px; font-family: inherit; background: #fff; font-weight: bold; text-align: right; }
    .grid-input:focus { outline: none; border-color: #38a169; box-shadow: 0 0 0 1px #38a169; }
    
    /* Notitie veld specifiek links uitgelijnd en normaal font */
    .input-notitie { font-weight: normal; text-align: left; }
    
    .btn-action { padding: 6px 10px; border: none; border-radius: 3px; font-weight: bold; cursor: pointer; color: white; font-size: 11px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .btn-verwerk { background: #48bb78; }
    .btn-verwerk:hover { background: #38a169; }
    
    .msg { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    
    .bedrag-regel { font-size: 13px; display: inline-flex; gap: 15px; align-items: center; }
    .omzet-text { color: #4a5568; font-weight: bold; font-size: 13px; }
    .verschil-groen { color: #38a169; font-weight: bold; font-size: 13px; }
    .verschil-rood { color: #e53e3e; font-weight: bold; font-size: 13px; }
    .rit-done { background: #f0fff4 !important; opacity: 0.8; }
</style>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="margin: 0; color: #003366;">Administratie & Controle</h1>
    </div>

    <div class="tab-nav">
        <a href="ritten.php" class="tab-link">1. Diensten</a>
        <a href="kas.php" class="tab-link actief">2. Kas</a>
        <a href="pin.php" class="tab-link">3. Pin</a>
        <a href="facturatie.php" class="tab-link">4. Facturatie</a>
    </div>

    <div class="tab-content">
        
        <div class="filter-balk">
            <a href="?filter=open" class="btn-filter <?php echo ($filter == 'open') ? 'actief' : 'inactief'; ?>">Open Kasdagen (Te Tellen)</a>
            <a href="?filter=historie" class="btn-filter <?php echo ($filter == 'historie') ? 'actief' : 'inactief'; ?>">Kasboek (Afgesloten Dagen)</a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'kas_opgeslagen'): ?>
            <div class="msg msg-success">✅ Envelop succesvol geteld en opgeslagen in het kasboek.</div>
        <?php endif; ?>

        <?php if (count($weergave_dagen) == 0): ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px;">
                <i class="fas fa-cash-register" style="font-size: 40px; color: #48bb78; margin-bottom: 15px;"></i>
                <h3 style="margin:0; color:#333;">Geen kas om te tellen</h3>
                <p style="color:#666;">Alle contante ritten zijn verwerkt.</p>
            </div>
        <?php else: ?>

            <table class="master-table">
                <tbody>
                    <?php foreach ($weergave_dagen as $datum => $diensten): 
                        
                        $dag_omzet = 0;
                        $dag_geteld = 0;
                        $aantal_enveloppen = count($diensten);
                        $enveloppen_geteld = 0;

                        foreach($diensten as $d) {
                            $dag_omzet += $d['omzet_bedrag'];
                            if ($d['kas_status'] == 'Akkoord') {
                                $dag_geteld += $d['kas_afgedragen'];
                                $enveloppen_geteld++;
                            }
                        }
                        
                        $dag_verschil = $dag_geteld - $dag_omzet;
                        $voortgang = "($enveloppen_geteld / $aantal_enveloppen enveloppen)";
                    ?>
                        <tr class="dag-header" onclick="toggleDag('dag_<?php echo $datum; ?>')">
                            <td width="30" style="text-align: center; font-size: 16px; font-weight: bold;" id="icon_dag_<?php echo $datum; ?>">+</td>
                            <td width="120"><strong><i class="far fa-calendar-alt"></i> <?php echo date('d-m-Y', strtotime($datum)); ?></strong></td>
                            <td width="150" style="color: #cbd5e0; font-size: 12px;"><?php echo $voortgang; ?></td>
                            
                            <td style="text-align: right;">
                                <div class="bedrag-regel">
                                    <span style="color:#e2e8f0;">Omzet: € <?php echo number_format($dag_omzet,2,',','.'); ?></span>
                                    
                                    <?php if($filter == 'historie'): ?>
                                        <strong style="color:#fff; border-left: 2px solid #2c5282; padding-left: 15px;">
                                            Dagtotaal Geteld: € <?php echo number_format($dag_geteld,2,',','.'); ?>
                                        </strong>
                                        <span style="margin-left: 10px;">
                                            <?php 
                                            if($dag_verschil == 0) echo '<span style="color:#68d391;">(Klopt)</span>';
                                            elseif($dag_verschil > 0) echo '<span style="color:#68d391;">(+ € '.number_format($dag_verschil,2,',','.').')</span>';
                                            else echo '<span style="color:#fc8181;">(- € '.number_format(abs($dag_verschil),2,',','.').')</span>';
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td width="120" style="text-align: right;">
                                <?php if($filter == 'historie'): ?>
                                    <span style="color: #68d391; font-weight: bold;"><i class="fas fa-lock"></i> Afgesloten</span>
                                <?php else: ?>
                                    <span style="color: #fbd38d; font-weight: bold;"><i class="fas fa-folder-open"></i> Open</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr id="dag_<?php echo $datum; ?>" class="dag-container">
                            <td colspan="5" style="padding: 15px;">
                                
                                <table class="excel-table">
                                    <thead>
                                        <tr>
                                            <th width="100">Dienst</th>
                                            <th width="180">Chauffeur</th>
                                            <th width="120" style="text-align: right;">Omzet (€)</th>
                                            <th width="140" style="text-align: right;">Fysiek Geteld (€)</th>
                                            <th width="120">Verschil</th>
                                            <th>Notitie Kasverschil</th>
                                            <th width="120">Actie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($diensten as $rit): 
                                            $is_geteld = ($rit['kas_status'] == 'Akkoord');
                                            $row_bg = $is_geteld ? 'rit-done' : '';
                                        ?>
                                        <tr class="<?php echo $row_bg; ?>">
                                            <form id="form_kas_<?php echo $rit['id']; ?>" method="POST">
                                                <input type="hidden" name="actie" value="opslaan_kas">
                                                <input type="hidden" name="dienst_id" value="<?php echo $rit['id']; ?>">
                                                <input type="hidden" name="kas_datum" value="<?php echo $datum; ?>">
                                                <input type="hidden" name="omzet_bedrag" id="omzet_<?php echo $rit['id']; ?>" value="<?php echo $rit['omzet_bedrag']; ?>">
                                            </form>
                                            
                                            <td><strong>#<?php echo $rit['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($rit['voornaam'].' '.$rit['achternaam']); ?></td>
                                            
                                            <td style="text-align: right; background: #edf2f7; border-right: 2px solid #cbd5e0;">
                                                <span class="omzet-text">€ <?php echo number_format($rit['omzet_bedrag'],2,',','.'); ?></span>
                                            </td>
                                            
                                            <?php if(!$is_geteld || $filter == 'open'): ?>
                                                <td>
                                                    <input type="number" step="0.01" name="kas_afgedragen" id="input_<?php echo $rit['id']; ?>" value="<?php echo $rit['kas_afgedragen']; ?>" form="form_kas_<?php echo $rit['id']; ?>" class="grid-input" placeholder="0.00" onkeyup="rekenVerschil(<?php echo $rit['id']; ?>)" required>
                                                </td>
                                                <td id="verschil_vak_<?php echo $rit['id']; ?>">
                                                    <?php 
                                                    if($is_geteld) {
                                                        if($rit['kas_verschil'] == 0) echo '<span class="verschil-groen">Klopt precies</span>';
                                                        elseif($rit['kas_verschil'] > 0) echo '<span class="verschil-groen">+ € ' . number_format($rit['kas_verschil'], 2, ',', '.') . '</span>';
                                                        else echo '<span class="verschil-rood">- € ' . number_format(abs($rit['kas_verschil']), 2, ',', '.') . '</span>';
                                                    } else {
                                                        echo '<span style="color:#a0aec0; font-size:11px;">Typ bedrag...</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <input type="text" name="kas_notitie" value="<?php echo htmlspecialchars($rit['kas_notitie']); ?>" form="form_kas_<?php echo $rit['id']; ?>" class="grid-input input-notitie" placeholder="Bijv. fooi">
                                                </td>
                                                <td style="text-align: center;">
                                                    <button type="submit" form="form_kas_<?php echo $rit['id']; ?>" class="btn-action btn-verwerk"><i class="fas fa-save"></i> Opslaan</button>
                                                </td>
                                            <?php else: ?>
                                                <td style="text-align: right; font-weight: bold; font-size: 13px;">€ <?php echo number_format($rit['kas_afgedragen'],2,',','.'); ?></td>
                                                <td>
                                                    <?php 
                                                    if($rit['kas_verschil'] == 0) echo '<span class="verschil-groen">Klopt</span>';
                                                    elseif($rit['kas_verschil'] > 0) echo '<span class="verschil-groen">+ € ' . number_format($rit['kas_verschil'], 2, ',', '.') . '</span>';
                                                    else echo '<span class="verschil-rood">- € ' . number_format(abs($rit['kas_verschil']), 2, ',', '.') . '</span>';
                                                    ?>
                                                </td>
                                                <td style="color: #666; font-style: italic; font-size: 12px;"><?php echo htmlspecialchars($rit['kas_notitie']); ?></td>
                                                <td style="text-align: center; color: #48bb78; font-weight: bold; font-size: 11px;"><i class="fas fa-check"></i> Geteld</td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
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
function toggleDag(rowId) {
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

function rekenVerschil(id) {
    let omzet = parseFloat(document.getElementById('omzet_' + id).value) || 0;
    let geteld_input = document.getElementById('input_' + id).value.replace(',', '.');
    let vakje = document.getElementById('verschil_vak_' + id);
    
    if(geteld_input === "") {
        vakje.innerHTML = '<span style="color:#a0aec0; font-size:11px;">Typ bedrag...</span>';
        return;
    }
    
    let geteld = parseFloat(geteld_input) || 0;
    let verschil = geteld - omzet;
    
    if (verschil === 0) {
        vakje.innerHTML = '<span class="verschil-groen">Klopt precies</span>';
    } else if (verschil > 0) {
        vakje.innerHTML = '<span class="verschil-groen">+ € ' + verschil.toFixed(2).replace('.', ',') + '</span>';
    } else {
        vakje.innerHTML = '<span class="verschil-rood">- € ' + Math.abs(verschil).toFixed(2).replace('.', ',') + '</span>';
    }
}

<?php if(isset($_GET['open_dag'])): ?>
    window.onload = function() { toggleDag('dag_<?php echo $_GET['open_dag']; ?>'); };
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>