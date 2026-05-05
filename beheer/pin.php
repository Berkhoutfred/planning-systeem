<?php
// Bestand: beheer/pin.php
// VERSIE: Kantoor Portaal - Tabblad 3 (PIN & SumUp Controle)

include '../beveiliging.php';
require 'includes/db.php';

$actief_tab = 'pin';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'open'; 

// ==========================================
// ACTIES VERWERKEN (PIN Afvinken)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['actie']) && $_POST['actie'] == 'pin_akkoord') {
        $rit_id = (int)$_POST['rit_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE ritten SET pin_status = 'Akkoord' WHERE id = ?");
            $stmt->execute([$rit_id]);
            
            header("Location: pin.php?filter=".$filter."&msg=pin_akkoord");
            exit;
        } catch (PDOException $e) {
            die("Fout bij afvinken PIN: " . $e->getMessage());
        }
    }
}

// ==========================================
// DATA OPHALEN
// ==========================================
// We tonen alle ritten met betaalwijze 'PIN' uit diensten die in Tab 1 zijn goedgekeurd.
$status_filter = ($filter == 'historie') ? 'Akkoord' : 'Te controleren';

$stmt_pin = $pdo->prepare("
    SELECT r.*, d.id as dienst_nummer, c.voornaam as ch_voornaam, c.achternaam as ch_achternaam,
           (SELECT omschrijving FROM ritregels WHERE rit_id = r.id LIMIT 1) as rr_soort,
           (SELECT van_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as rr_van,
           (SELECT naar_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as rr_naar,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id ORDER BY id ASC LIMIT 1) as calc_van,
           (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND type = 't_aankomst_best' LIMIT 1) as calc_naar,
           k.bedrijfsnaam, k.voornaam as k_voornaam, k.achternaam as k_achternaam
    FROM ritten r
    JOIN diensten d ON r.dienst_id = d.id
    LEFT JOIN chauffeurs c ON d.chauffeur_id = c.id
    LEFT JOIN klanten k ON r.klant_id = k.id
    WHERE d.status = 'gecontroleerd' 
      AND r.betaalwijze = 'PIN' 
      AND r.pin_status = ?
    ORDER BY r.datum_start ASC
");
$stmt_pin->execute([$status_filter]);
$pin_ritten = $stmt_pin->fetchAll();

// Bereken totaalbedrag van de huidige lijst
$totaal_pin_bedrag = 0;
foreach($pin_ritten as $rit) {
    $totaal_pin_bedrag += (float)$rit['betaald_bedrag'];
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

    /* PLATTE EXCEL TABEL */
    .excel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .excel-table th { background: #1a365d; color: white; padding: 10px 8px; text-align: left; font-size: 12px; text-transform: uppercase; border-right: 1px solid #2c5282; }
    .excel-table td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 8px; vertical-align: middle; background: #fff; }
    .excel-table tr:hover td { background-color: #f8f9fa; }
    
    .btn-action { padding: 6px 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; color: white; font-size: 12px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .btn-verwerk { background: #3182ce; }
    .btn-verwerk:hover { background: #2b6cb0; }
    
    .msg { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
    
    .bedrag-blauw { color: #3182ce; font-weight: bold; font-size: 14px; }
</style>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="margin: 0; color: #003366;">Administratie & Controle</h1>
    </div>

    <div class="tab-nav">
        <a href="ritten.php" class="tab-link">1. Diensten</a>
        <a href="kas.php" class="tab-link">2. Kas</a>
        <a href="pin.php" class="tab-link actief">3. Pin</a>
        <a href="facturatie.php" class="tab-link">4. Facturatie</a>
    </div>

    <div class="tab-content">
        
        <div class="filter-balk">
            <div>
                <a href="?filter=open" class="btn-filter <?php echo ($filter == 'open') ? 'actief' : 'inactief'; ?>">Te Controleren (Open)</a>
                <a href="?filter=historie" class="btn-filter <?php echo ($filter == 'historie') ? 'actief' : 'inactief'; ?>">Archief (Akkoord)</a>
            </div>
            <div style="background: #ebf8ff; border: 1px solid #90cdf4; padding: 8px 15px; border-radius: 6px; color: #2b6cb0; font-weight: bold; font-size: 14px;">
                Totaal <?php echo ($filter == 'open') ? 'Openstaand' : 'Gecontroleerd'; ?>: € <?php echo number_format($totaal_pin_bedrag, 2, ',', '.'); ?>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'pin_akkoord'): ?>
            <div class="msg msg-success">✅ PIN-transactie afgevinkt en verplaatst naar archief.</div>
        <?php endif; ?>

        <?php if (count($pin_ritten) == 0): ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px;">
                <i class="fas fa-credit-card" style="font-size: 50px; color: #3182ce; margin-bottom: 15px;"></i>
                <h2 style="margin:0; color:#333;">Geen PIN transacties</h2>
                <p style="color:#666;">Alle transacties zijn gecontroleerd of er zijn geen nieuwe PIN-ritten.</p>
            </div>
        <?php else: ?>

            <table class="excel-table">
                <thead>
                    <tr>
                        <th width="140">Datum & Tijd</th>
                        <th width="100">Dienst</th>
                        <th width="150">Chauffeur</th>
                        <th width="180">Klant / Soort</th>
                        <th>Van -> Naar</th>
                        <th width="120" style="text-align: right;">Bedrag (€)</th>
                        <th width="120" style="text-align: center;">SumUp Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pin_ritten as $rit): 
                        $klant = empty($rit['calculatie_id']) ? "[Extra] " . $rit['rr_soort'] : (!empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['k_voornaam'] . ' ' . $rit['k_achternaam']);
                        $van_adres = $rit['rr_van'] ?: $rit['calc_van'];
                        $naar_adres = $rit['rr_naar'] ?: $rit['calc_naar'];
                    ?>
                    
                    <tr>
                        <td><strong><?php echo date('d-m-Y', strtotime($rit['datum_start'])); ?></strong><br><span style="color:#666; font-size:11px;"><?php echo date('H:i', strtotime($rit['datum_start'])); ?></span></td>
                        <td>#<?php echo $rit['dienst_nummer']; ?></td>
                        <td><?php echo htmlspecialchars($rit['ch_voornaam'] . ' ' . $rit['ch_achternaam']); ?></td>
                        <td><?php echo htmlspecialchars($klant); ?></td>
                        <td style="font-size: 12px; color: #555;">
                            <?php echo htmlspecialchars($van_adres); ?> <i class="fas fa-arrow-right" style="font-size:9px; color:#ccc;"></i> <?php echo htmlspecialchars($naar_adres); ?>
                        </td>
                        
                        <td style="text-align: right; background: #ebf8ff; border-right: 2px solid #cbd5e0;">
                            <span class="bedrag-blauw">€ <?php echo number_format($rit['betaald_bedrag'], 2, ',', '.'); ?></span>
                        </td>
                        
                        <td style="text-align: center;">
                            <?php if($filter == 'open'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="actie" value="pin_akkoord">
                                    <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                    <button type="submit" class="btn-action btn-verwerk"><i class="fas fa-check"></i> Klopt!</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #48bb78; font-weight: bold; font-size: 12px;"><i class="fas fa-check-double"></i> Akkoord</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>