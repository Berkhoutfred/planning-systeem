<?php
// Bestand: beheer/agenda.php
// VERSIE: De Week-Agenda Voertuig Matrix (Ma-Zo) - Datum Filter Opgelost

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

include 'includes/header.php';

// --- WEEK LOGICA (Maandag t/m Zondag) ---
$week = isset($_GET['w']) ? (int)$_GET['w'] : (int)date('W');
$jaar = isset($_GET['j']) ? (int)$_GET['j'] : (int)date('o'); // 'o' pakt het ISO-jaar, veiliger rond de jaarwisseling

// Huidige week berekenen (Start = Maandag)
$dto = new DateTime();
$dto->setISODate($jaar, $week);
$start_datum = $dto->format('Y-m-d');

$eind_dto = clone $dto;
$eind_dto->modify('+6 days');
$eind_datum = $eind_dto->format('Y-m-d');

// Navigatie berekenen voor de knoppen
$vorige_dto = clone $dto;
$vorige_dto->modify('-1 week');
$vorige_week = $vorige_dto->format('W');
$vorig_jaar = $vorige_dto->format('o');

$volgende_dto = clone $dto;
$volgende_dto->modify('+1 week');
$volgende_week = $volgende_dto->format('W');
$volgend_jaar = $volgende_dto->format('o');

// De 7 dagen opbouwen
$dagen = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $dto;
    $d->modify("+$i days");
    $dagen[] = $d->format('Y-m-d');
}

// --- DATA OPHALEN ---
$stmt_bus = $pdo->prepare("SELECT id, voertuig_nummer, naam, zitplaatsen FROM voertuigen WHERE tenant_id = ? AND archief = 0 AND status != 'werkplaats' ORDER BY zitplaatsen DESC, naam ASC");
$stmt_bus->execute([$tenantId]);
$bussen = $stmt_bus->fetchAll();

// Ritten ophalen (Samengevoegd uit calculaties en vaste ritten)
try {
    // Deel 1: Calculaties met een toegewezen bus
    // LET OP: Nu kijken we naar datum_bevestiging_verstuurd in plaats van status_bevestiging
    $stmt_calc = $pdo->prepare("
        SELECT 
            c.id, c.rit_datum, c.voertuig_id, 
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_retour_klant' LIMIT 1) as bestemming,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_vertrek_klant' LIMIT 1) as start_tijd,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_aankomst_klant' LIMIT 1) as eind_tijd,
            'calculatie' as bron
        FROM calculaties c 
        WHERE c.tenant_id = ?
          AND c.rit_datum >= ? AND c.rit_datum <= ? AND c.voertuig_id IS NOT NULL AND c.datum_bevestiging_verstuurd IS NOT NULL
    ");
    $stmt_calc->execute([$tenantId, $start_datum, $eind_datum]);
    $lijst_calculaties = $stmt_calc->fetchAll();

    // Deel 2: Vaste Ritten met een toegewezen bus
    $stmt_vaste = $pdo->prepare("
        SELECT 
            r.id, DATE(r.datum_start) as rit_datum, r.voertuig_id,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as bestemming,
            (SELECT TIME(datum_start) FROM ritten WHERE id = r.id AND tenant_id = r.tenant_id) as start_tijd,
            (SELECT TIME(datum_eind) FROM ritten WHERE id = r.id AND tenant_id = r.tenant_id) as eind_tijd,
            'vaste_rit' as bron
        FROM ritten r
        WHERE r.tenant_id = ?
          AND DATE(r.datum_start) >= ? AND DATE(r.datum_start) <= ? AND r.calculatie_id IS NULL AND r.voertuig_id IS NOT NULL
    ");
    $stmt_vaste->execute([$tenantId, $start_datum, $eind_datum]);
    $lijst_vaste_ritten = $stmt_vaste->fetchAll();

    // Deel 3: Samenvoegen en sorteren op tijd
    $ritten_db = array_merge($lijst_calculaties, $lijst_vaste_ritten);
    usort($ritten_db, function($a, $b) {
        return strtotime($a['start_tijd'] ?? '00:00') - strtotime($b['start_tijd'] ?? '00:00');
    });

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

$planning = [];
foreach ($ritten_db as $rit) {
    $datum = $rit['rit_datum'];
    $bus_id = $rit['voertuig_id'];
    $planning[$datum][$bus_id][] = $rit; 
}

$NL_dagen = ['Sun'=>'Zondag', 'Mon'=>'Maandag', 'Tue'=>'Dinsdag', 'Wed'=>'Woensdag', 'Thu'=>'Donderdag', 'Fri'=>'Vrijdag', 'Sat'=>'Zaterdag'];
?>

<div style="max-width: 1400px; margin: 20px auto; padding: 0 20px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        
        <div style="display: flex; gap: 10px; align-items: center; background: #003366; padding: 10px 15px; border-radius: 8px; color: white;">
            <a href="?j=<?php echo $vorig_jaar; ?>&w=<?php echo $vorige_week; ?>" style="color: white; text-decoration: none; font-weight: bold; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px;">⬅️ Vorige</a>
            <h2 style="margin: 0 15px; font-size: 20px;">Week <?php echo $week; ?> <span style="font-weight: normal; font-size: 16px;">(<?php echo $jaar; ?>)</span></h2>
            <a href="?j=<?php echo $volgend_jaar; ?>&w=<?php echo $volgende_week; ?>" style="color: white; text-decoration: none; font-weight: bold; padding: 5px 10px; background: rgba(255,255,255,0.2); border-radius: 4px;">Volgende ➡️</a>
            <a href="agenda.php" style="color: #003366; text-decoration: none; font-weight: bold; padding: 5px 10px; background: #fff; border-radius: 4px; margin-left: 10px;">Huidige Week</a>
        </div>

        <div style="font-size: 14px; color: #666; background: white; padding: 10px 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <span style="display:inline-block; width:12px; height:12px; background:#e8f5e9; border:1px solid #c8e6c9; margin-right:5px;"></span> = Vrij
            <span style="display:inline-block; width:12px; height:12px; background:#e3f2fd; border:1px solid #b6d4fe; margin-right:5px; margin-left:15px;"></span> = Gepland
        </div>
    </div>

    <div style="background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
            <thead>
                <tr style="background: #003366; color: white;">
                    <th style="padding: 15px; text-align: left; width: 220px; position: sticky; left: 0; background: #002244; z-index: 2;">Bus / Voertuig</th>
                    <?php foreach($dagen as $dag): 
                        $dagNaam = $NL_dagen[date('D', strtotime($dag))];
                        $dagNummer = date('d-m-Y', strtotime($dag));
                        $isVandaag = ($dag == date('Y-m-d')) ? 'background: #28a745;' : '';
                    ?>
                        <th style="padding: 12px 10px; text-align: center; border-left: 1px solid #004488; font-size: 14px; width: calc(100% / 7); <?php echo $isVandaag; ?>">
                            <?php echo $dagNaam; ?><br>
                            <span style="font-size: 12px; font-weight: normal; opacity: 0.8;"><?php echo $dagNummer; ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($bussen as $bus): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        
                        <td style="padding: 15px; position: sticky; left: 0; background: #f8f9fa; border-right: 2px solid #ddd; z-index: 1;">
                            <strong style="font-size: 15px;"><?php echo htmlspecialchars($bus['voertuig_nummer'] ?? ''); ?> <?php echo htmlspecialchars($bus['naam']); ?></strong><br>
                            <span style="font-size: 12px; color: #666;"><?php echo $bus['zitplaatsen']; ?> zitplaatsen</span>
                        </td>

                        <?php foreach($dagen as $dag): 
                            $heeft_rit = isset($planning[$dag][$bus['id']]);
                            
                            if($heeft_rit) {
                                $bg_color = '#e3f2fd'; // Lichtblauw (Gepland)
                                $border_color = '#b6d4fe';
                            } else {
                                $bg_color = '#e8f5e9'; // Lichtgroen (Vrij)
                                $border_color = '#c8e6c9';
                            }
                        ?>
                            <td style="padding: 8px; text-align: center; border-left: 1px solid #eee; background: <?php echo $bg_color; ?>; vertical-align: top;">
                                <?php 
                                if($heeft_rit) {
                                    foreach($planning[$dag][$bus['id']] as $geplande_rit) {
                                        $bestemming_kort = mb_strimwidth($geplande_rit['bestemming'] ?? 'Rit #'.$geplande_rit['id'], 0, 25, "...");
                                        
                                        // Tijden formatten
                                        $start = !empty($geplande_rit['start_tijd']) ? date('H:i', strtotime($geplande_rit['start_tijd'])) : '??:??';
                                        $eind = !empty($geplande_rit['eind_tijd']) ? date('H:i', strtotime($geplande_rit['eind_tijd'])) : '??:??';
                                        
                                        // Label bepalen
                                        $label = ($geplande_rit['bron'] == 'vaste_rit') ? ' <span style="background: #17a2b8; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: bold;">VAST</span>' : '';

                                        echo '<div style="background: white; border: 1px solid '.$border_color.'; border-radius: 6px; padding: 8px; margin-bottom: 8px; font-size: 12px; color: #003366; text-align: left; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer;" title="Rit #'.$geplande_rit['id'].' naar '.htmlspecialchars($geplande_rit['bestemming']).'">';
                                        
                                        echo '<strong style="color: #d97706; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 4px;"><span>🕒 ' . $start . ' - ' . $eind . '</span>' . $label . '</strong>';
                                        echo '📍 ' . htmlspecialchars($bestemming_kort);
                                        
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include 'includes/footer.php'; ?>