<?php
// Bestand: beheer/facturatie.php
// VERSIE: Kantoor Portaal - Tabblad 4 (Met Factuur Undo/Credit Functie)

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

// --- DE UNDO / HEROPENEN FUNCTIE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'heropen_factuur') {
    $factuurnummer = $_POST['factuurnummer'] ?? '';
    if (!empty($factuurnummer)) {
        // Zet ritten terug naar 'Te factureren' en wis het oude factuurnummer
        $stmt = $pdo->prepare("UPDATE ritten SET factuur_status = 'Te factureren', factuurnummer = NULL, factuur_datum = NULL WHERE factuurnummer = ? AND tenant_id = ?");
        $stmt->execute([$factuurnummer, $tenantId]);
        header("Location: facturatie.php?filter=historie&msg=factuur_heropend");
        exit;
    }
}

$actief_tab = 'facturatie';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open'; 

// ==========================================
// DATA OPHALEN & GROEPEREN PER KLANT
// ==========================================
$status_filter = ($filter == 'historie') ? 'Gefactureerd' : 'Te factureren';

$stmt_facturen = $pdo->prepare("
    SELECT r.*,
           k.bedrijfsnaam, k.voornaam as k_voornaam, k.achternaam as k_achternaam, 
           k.email as k_email, k.adres as k_adres, k.postcode as k_postcode, k.plaats as k_plaats,
           (SELECT van_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_van,
           (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as rr_naar,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id ORDER BY id ASC LIMIT 1) as calc_van,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id AND type = 't_aankomst_best' LIMIT 1) as calc_naar
    FROM ritten r
    JOIN diensten d ON r.dienst_id = d.id AND d.tenant_id = r.tenant_id
    LEFT JOIN klanten k ON r.klant_id = k.id AND k.tenant_id = r.tenant_id
    WHERE r.tenant_id = ?
      AND d.status = 'gecontroleerd' 
      AND (r.betaalwijze = 'Op Rekening' OR r.betaalwijze = 'Rekening')
      AND r.factuur_status = ?
    ORDER BY k.bedrijfsnaam ASC, k.achternaam ASC, r.datum_start ASC
");
$stmt_facturen->execute([$tenantId, $status_filter]);
$alle_ritten = $stmt_facturen->fetchAll();

$klanten_groep = [];
$totaal_bedrag_lijst = 0;

foreach($alle_ritten as $rit) {
    $klant_id = $rit['klant_id'] ?: 0; 
    
    if(!isset($klanten_groep[$klant_id])) {
        $klant_naam = empty($rit['bedrijfsnaam']) ? trim($rit['k_voornaam'] . ' ' . $rit['k_achternaam']) : $rit['bedrijfsnaam'];
        if(empty($klant_naam)) $klant_naam = "⚠️ ONBEKENDE KLANT (Koppel eerst een klant in Tab 1)";
        
        $klanten_groep[$klant_id] = [
            'naam' => $klant_naam,
            'email' => $rit['k_email'] ?: 'Geen e-mail bekend',
            'adres' => $rit['k_adres'] ?: 'Adres onbekend',
            'pc_plaats' => trim(($rit['k_postcode'] ?? '') . ' ' . ($rit['k_plaats'] ?? '')),
            'ritten' => [],
            'klant_totaal' => 0
        ];
    }
    
    $bedrag = (float)$rit['betaald_bedrag'];
    $klanten_groep[$klant_id]['ritten'][] = $rit;
    $klanten_groep[$klant_id]['klant_totaal'] += $bedrag;
    $totaal_bedrag_lijst += $bedrag;
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

    .filter-balk { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; }
    .btn-filter { padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; border: 1px solid #ccc; }
    .btn-filter.actief { background: #003366; color: white; border-color: #003366; }
    .btn-filter.inactief { background: #f8f9fa; color: #555; }

    .master-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
    
    .klant-header { background: #1a365d; color: white; cursor: pointer; transition: 0.2s; }
    .klant-header:hover { background: #2c5282; }
    .klant-header td { padding: 8px 10px; border-bottom: 2px solid #fff; vertical-align: middle; white-space: nowrap; }
    .klant-header strong { font-size: 14px; letter-spacing: 0.5px; }
    
    .btn-dienst-ok { background: #48bb78; color: white; border: 1px solid #2f855a; padding: 6px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; float: right; transition: 0.2s; }
    .btn-dienst-ok:hover { background: #38a169; }

    .ritten-container { display: none; background: #f8f9fa; border: 2px solid #1a365d; border-top: none; }
    
    .excel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .excel-table th { background: #e2e8f0; color: #4a5568; padding: 10px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #cbd5e0; }
    .excel-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    
    .msg { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    
    .bedrag-regel { font-size: 14px; display: inline-flex; align-items: center; }

    /* MODAL STYLES */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
    .modal-content { background: #fff; padding: 25px; border-radius: 10px; width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .modal-title { margin-top: 0; color: #003366; border-bottom: 2px solid #f4f7f6; padding-bottom: 10px; }
    .adres-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; text-align: left; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
    .adres-box strong { color: #1a365d; }
    .btn-actie { display: block; width: 100%; padding: 12px; margin-bottom: 10px; border-radius: 6px; font-weight: bold; cursor: pointer; border: none; text-align: left; transition: 0.2s; }
    .btn-actie i { margin-right: 10px; width: 20px; text-align: center; }
    .btn-inzien { background: #edf2f7; color: #2d3748; }
    .btn-inzien:hover { background: #e2e8f0; }
    .btn-download { background: #4299e1; color: white; }
    .btn-download:hover { background: #3182ce; }
    .btn-mail { background: #48bb78; color: white; }
    .btn-mail:hover { background: #38a169; }
    .btn-close { background: #fff; color: #e53e3e; border: 1px solid #e53e3e; margin-top: 10px; text-align: center; }
    .btn-close:hover { background: #fff5f5; }
</style>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="margin: 0; color: #003366;">Administratie & Controle</h1>
    </div>

    <div class="tab-nav">
        <a href="ritten.php" class="tab-link">1. Diensten</a>
        <a href="kas.php" class="tab-link">2. Kas</a>
        <a href="pin.php" class="tab-link">3. Pin</a>
        <a href="facturatie.php" class="tab-link actief">4. Facturatie</a>
    </div>

    <div class="tab-content">
        
        <div class="filter-balk">
            <div>
                <a href="?filter=open" class="btn-filter <?php echo ($filter == 'open') ? 'actief' : 'inactief'; ?>">Te Factureren (Open)</a>
                <a href="?filter=historie" class="btn-filter <?php echo ($filter == 'historie') ? 'actief' : 'inactief'; ?>">Gefactureerd (Archief)</a>
            </div>
            <div style="background: #fffaf0; border: 1px solid #fbd38d; padding: 8px 15px; border-radius: 6px; color: #dd6b20; font-weight: bold; font-size: 14px;">
                Totaal <?php echo ($filter == 'open') ? 'Te Factureren' : 'Gefactureerd'; ?>: € <?php echo number_format($totaal_bedrag_lijst, 2, ',', '.'); ?>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'klant_gefactureerd'): ?>
            <div class="msg msg-success">✅ Actie succesvol uitgevoerd! Factuur is verstuurd naar klant en SnelStart.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'factuur_heropend'): ?>
            <div class="msg msg-success">✅ Factuur succesvol geannuleerd/heropend! De ritten staan weer klaar in 'Te Factureren' voor een nieuw factuurnummer.</div>
        <?php endif; ?>

        <?php if (count($klanten_groep) == 0): ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px;">
                <i class="fas fa-file-invoice-dollar" style="font-size: 50px; color: #dd6b20; margin-bottom: 15px;"></i>
                <h2 style="margin:0; color:#333;">Geen openstaande facturen</h2>
                <p style="color:#666;">Alle ritten 'Op Rekening' zijn verwerkt of er zijn geen nieuwe ritten.</p>
            </div>
        <?php else: ?>

            <table class="master-table">
                <tbody>
                    <?php foreach ($klanten_groep as $klant_id => $data): 
                        $aantal_ritten = count($data['ritten']);
                    ?>
                        <tr class="klant-header" onclick="toggleKlant('klant_<?php echo $klant_id; ?>')">
                            <td width="30" style="text-align: center; font-size: 16px; font-weight: bold;" id="icon_klant_<?php echo $klant_id; ?>">+</td>
                            <td width="300"><strong><i class="fas fa-building"></i> <?php echo htmlspecialchars($data['naam']); ?></strong></td>
                            <td width="200" style="color: #cbd5e0; font-size: 12px;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($data['email']); ?></td>
                            <td width="150" style="color: #cbd5e0; font-size: 12px;"><?php echo $aantal_ritten; ?> rit(ten)</td>
                            
                            <td style="text-align: right;">
                                <div class="bedrag-regel">
                                    <strong style="color:#fff; border-left: 2px solid #2c5282; padding-left: 10px;">
                                        Totaal: € <?php echo number_format($data['klant_totaal'], 2, ',', '.'); ?>
                                    </strong>
                                </div>
                            </td>
                            
                            <td width="200" onclick="event.stopPropagation();">
                                <?php if($filter == 'open'): ?>
                                    <?php if($klant_id > 0): ?>
                                        <button type="button" class="btn-dienst-ok" 
                                            onclick="openFactuurModal(
                                                '<?php echo $klant_id; ?>', 
                                                '<?php echo addslashes($data['naam']); ?>', 
                                                '<?php echo number_format($data['klant_totaal'], 2, ',', '.'); ?>',
                                                '<?php echo addslashes($data['email']); ?>',
                                                '<?php echo addslashes($data['adres']); ?>',
                                                '<?php echo addslashes($data['pc_plaats']); ?>'
                                            )">
                                            <i class="fas fa-file-invoice"></i> Maak Factuur
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#fc8181; font-weight:bold; font-size:12px;">Klant onbekend! Pas aan in Tab 1.</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="float: right; color: #68d391; font-weight: bold; font-size: 12px;"><i class="fas fa-check-double"></i> Gefactureerd</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr id="klant_<?php echo $klant_id; ?>" class="ritten-container">
                            <td colspan="6" style="padding: 15px;">
                                
                                <table class="excel-table">
                                    <thead>
                                        <tr>
                                            <th width="120">Datum</th>
                                            <th>Van</th>
                                            <th>Naar</th>
                                            <?php if($filter == 'historie'): ?>
                                                <th width="150" style="color:#0056b3;">FactuurNr</th>
                                            <?php endif; ?>
                                            <th width="150" style="text-align: right;">Bedrag (€)</th>
                                            <?php if($filter == 'historie'): ?>
                                                <th width="150" style="text-align: center;">Actie</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['ritten'] as $rit): 
                                            $van_adres = $rit['rr_van'] ?: $rit['calc_van'];
                                            $naar_adres = $rit['rr_naar'] ?: $rit['calc_naar'];
                                        ?>
                                        <tr>
                                            <td><strong><?php echo date('d-m-Y', strtotime($rit['datum_start'])); ?></strong></td>
                                            <td><?php echo htmlspecialchars($van_adres); ?></td>
                                            <td><?php echo htmlspecialchars($naar_adres); ?></td>
                                            
                                            <?php if($filter == 'historie'): ?>
                                                <td style="color:#0056b3; font-weight:bold;">#<?php echo htmlspecialchars($rit['factuurnummer']); ?></td>
                                            <?php endif; ?>
                                            
                                            <td style="text-align: right; font-weight: bold; color: #dd6b20; background: #fffaf0; border-left: 2px solid #fbd38d;">
                                                € <?php echo number_format($rit['betaald_bedrag'], 2, ',', '.'); ?>
                                            </td>
                                            
                                            <?php if($filter == 'historie'): ?>
                                                <td style="text-align: center;">
                                                    <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ WAARSCHUWING!\n\nJe gaat factuur <?php echo $rit['factuurnummer']; ?> annuleren/heropenen.\nDe klant en SnelStart hebben deze mogelijk al ontvangen.\nZij moeten deze oude factuur uit hun systeem halen (Crediteren).\n\nNa het heropenen komen deze ritten weer in je \'Te Factureren\' lijst en krijgen ze een NIEUW factuurnummer.\n\nWil je doorgaan?');">
                                                        <input type="hidden" name="actie" value="heropen_factuur">
                                                        <input type="hidden" name="factuurnummer" value="<?php echo $rit['factuurnummer']; ?>">
                                                        <button type="submit" style="background:#e53e3e; color:white; border:none; padding:6px 12px; border-radius:4px; font-weight:bold; cursor:pointer;" title="Factuur Ongedaan Maken"><i class="fas fa-undo"></i> Heropenen</button>
                                                    </form>
                                                </td>
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

<div id="factuurModal" class="modal-overlay">
    <div class="modal-content">
        <h3 class="modal-title" id="modalKlantNaam"></h3>
        
        <div class="adres-box">
            <strong>Factuurgegevens check:</strong><br>
            <span id="displayAdres"></span><br>
            <span id="displayPCPlaats"></span><br><br>
            <strong>E-mailadres voor verzending:</strong><br>
            <span id="displayEmail" style="color: #2b6cb0; font-weight: bold;"></span>
        </div>

        <p style="text-align: center; margin-bottom: 20px;">
            Totaalbedrag: <strong style="font-size: 18px; color: #dd6b20;">€ <span id="modalBedrag"></span></strong>
        </p>
        
        <button onclick="doeActie('inzien')" class="btn-actie btn-inzien">
            <i class="fas fa-eye"></i> 1. Concept Inzien
        </button>
        
        <button onclick="doeActie('downloaden')" class="btn-actie btn-download">
            <i class="fas fa-download"></i> 2. Definitief Maken & Downloaden
        </button>
        
        <button onclick="doeActie('mailen')" class="btn-actie btn-mail">
            <i class="fas fa-envelope"></i> 3. Definitief Maken & Mailen (+ iDEAL)
        </button>
        
        <button onclick="sluitModal()" class="btn-actie btn-close">
            <i class="fas fa-times"></i> Annuleren
        </button>
        
        <form id="modalForm" method="POST" action="verstuur_factuur.php" target="_blank">
            <input type="hidden" name="klant_id" id="modalKlantId" value="">
            <input type="hidden" name="actie_type" id="modalActieType" value="">
        </form>
    </div>
</div>

<script>
function toggleKlant(rowId) {
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

function openFactuurModal(klantId, klantNaam, bedrag, email, adres, pc_plaats) {
    document.getElementById('modalKlantId').value = klantId;
    document.getElementById('modalKlantNaam').innerText = klantNaam;
    document.getElementById('modalBedrag').innerText = bedrag;
    
    document.getElementById('displayEmail').innerText = email;
    document.getElementById('displayAdres').innerText = adres;
    document.getElementById('displayPCPlaats').innerText = pc_plaats;
    
    document.getElementById('factuurModal').style.display = 'flex';
}

function sluitModal() {
    document.getElementById('factuurModal').style.display = 'none';
}

function doeActie(type) {
    document.getElementById('modalActieType').value = type;
    
    if(type === 'inzien') {
        document.getElementById('modalForm').target = '_blank';
        document.getElementById('modalForm').submit();
    } else {
        var msg = (type === 'mailen') ? "Weet je zeker dat je de factuur definitief wilt maken en direct wilt MAILEN naar de klant (en SnelStart)?" : "Weet je zeker dat je de factuur definitief wilt maken en DOWNLOADEN?";
        
        if(confirm(msg)) {
            document.getElementById('modalForm').target = (type === 'downloaden') ? '_blank' : '_self'; 
            document.getElementById('modalForm').submit();
            sluitModal();
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>