<?php
// Bestand: beheer/planbord.php
// VERSIE: Het 3-Fasen Planbord (Inclusief Tijden & Opgelost Datum Filter)

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// --- PLANNING OPSLAAN LOGICA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rit_id']) && isset($_POST['bron'])) {
    $rit_id = (int)$_POST['rit_id'];
    $bron = $_POST['bron']; // 'calculatie' of 'vaste_rit'
    
    // Als het veld leeg is, maken we er netjes NULL van in de database
    $voertuig_id = !empty($_POST['voertuig_id']) ? (int)$_POST['voertuig_id'] : null;
    $chauffeur_id = !empty($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : null;

    try {
        if ($bron === 'calculatie') {
            $stmt_update = $pdo->prepare("UPDATE calculaties SET voertuig_id = ?, chauffeur_id = ? WHERE id = ?");
        } else {
            $stmt_update = $pdo->prepare("UPDATE ritten SET voertuig_id = ?, chauffeur_id = ? WHERE id = ?");
        }
        
        $stmt_update->execute([$voertuig_id, $chauffeur_id, $rit_id]);
        
        echo "<script>window.location.href='planbord.php?msg=opslaan_gelukt';</script>";
        exit;
    } catch (PDOException $e) {
        $foutmelding = "Fout bij opslaan planning: " . $e->getMessage();
    }
}
// --------------------------------

// --- DATA OPHALEN VOOR DE DROPDOWNS ---
$stmt_chauf = $pdo->query("SELECT id, voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam ASC");
$chauffeurs = $stmt_chauf->fetchAll();

$stmt_bus = $pdo->query("SELECT id, voertuig_nummer, naam, kenteken FROM voertuigen WHERE archief = 0 AND status != 'werkplaats' ORDER BY naam ASC");
$bussen = $stmt_bus->fetchAll();

// --- DEEL 1: CALCULATIES / OFFERTES OPHALEN ---
try {
    $stmt_calc = $pdo->prepare("
        SELECT 
            c.id, c.voertuig_id, c.chauffeur_id, c.rit_datum, 
            c.datum_bevestiging_verstuurd, c.datum_offerte_verstuurd,
            k.bedrijfsnaam, k.voornaam, k.achternaam,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_vertrek_klant' LIMIT 1) as start_tijd,
            (SELECT tijd FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_retour_klant' LIMIT 1) as eind_tijd,
            'calculatie' as bron
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id 
        WHERE c.rit_datum >= CURDATE() AND c.datum_offerte_verstuurd IS NOT NULL
    ");
    $stmt_calc->execute();
    $lijst_calculaties = $stmt_calc->fetchAll();

// --- DEEL 2: VASTE RITTEN OPHALEN ---
    $stmt_vaste = $pdo->prepare("
        SELECT 
            r.id, r.voertuig_id, r.chauffeur_id, DATE(r.datum_start) as rit_datum,
            CURRENT_TIMESTAMP as datum_bevestiging_verstuurd, CURRENT_TIMESTAMP as datum_offerte_verstuurd,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id LIMIT 1) as bedrijfsnaam,
            '' as voornaam, '' as achternaam,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as bestemming_adres,
            (SELECT TIME(datum_start) FROM ritten WHERE id = r.id) as start_tijd,
            (SELECT TIME(datum_eind) FROM ritten WHERE id = r.id) as eind_tijd,
            'vaste_rit' as bron
        FROM ritten r
        WHERE DATE(r.datum_start) >= CURDATE() AND r.calculatie_id IS NULL
    ");
    $stmt_vaste->execute();
    $lijst_vaste_ritten = $stmt_vaste->fetchAll();

// --- DEEL 3: ALLES SAMENVOEGEN EN SORTEREN OP DATUM ---
    $alle_ritten = array_merge($lijst_calculaties, $lijst_vaste_ritten);
    usort($alle_ritten, function($a, $b) {
        return strtotime($a['rit_datum']) - strtotime($b['rit_datum']);
    });

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// Ritten splitsen in 3 logische fases
$geen_bus = [];
$geen_chauffeur = [];
$volledig_gepland = [];

foreach ($alle_ritten as $rit) {
    if (empty($rit['voertuig_id'])) {
        $geen_bus[] = $rit;
    } elseif (!empty($rit['voertuig_id']) && empty($rit['chauffeur_id'])) {
        $geen_chauffeur[] = $rit;
    } else {
        $volledig_gepland[] = $rit;
    }
}
?>

<div style="max-width: 1200px; margin: 20px auto; padding: 0 20px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1 style="color: #003366; margin: 0;">🗺️ Planbord</h1>
        <div style="font-size: 14px; color: #666;">
            <strong>Proces:</strong> Wijs eerst een bus toe. De chauffeur kan later!
        </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'opslaan_gelukt'): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            ✅ Planning succesvol bijgewerkt!
        </div>
    <?php endif; ?>

    <?php if(isset($foutmelding)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?php echo $foutmelding; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
        
        <div style="background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #dc3545; overflow: hidden;">
            <div style="background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 18px; color: #dc3545;">🔴 1. Geen Bus Toegewezen (<?php echo count($geen_bus); ?>)</h2>
            </div>
            <div style="padding: 20px;">
                <?php if(count($geen_bus) == 0): ?>
                    <p style="color: #28a745; font-style: italic; margin: 0;">Alle ritten hebben minimaal een bus toegewezen gekregen!</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach($geen_bus as $rit): 
                                $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                                $start = !empty($rit['start_tijd']) ? date('H:i', strtotime($rit['start_tijd'])) : '??:??';
                                $eind = !empty($rit['eind_tijd']) ? date('H:i', strtotime($rit['eind_tijd'])) : '??:??';
                            ?>
                            <tr style="border-bottom: 1px solid #f5f5f5;">
                                <td style="padding: 15px 10px; width: 140px;">
                                    <strong><?php echo date('d-m-Y', strtotime($rit['rit_datum'])); ?></strong><br>
                                    <span style="color: #d97706; font-size: 12px; font-weight: bold;">🕒 <?= $start ?> - <?= $eind ?></span><br>
                                    <span style="font-size: 11px; color: #888;">#<?php echo $rit['id']; ?></span>
                                </td>
                                <td style="padding: 15px 10px;">
                                    <strong><?php echo htmlspecialchars($klantNaam); ?></strong>
                                    
                                    <?php if ($rit['bron'] == 'calculatie' && empty($rit['datum_bevestiging_verstuurd'])): ?>
                                        <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">OPTIE</span>
                                    <?php elseif ($rit['bron'] == 'vaste_rit'): ?>
                                        <span style="background: #17a2b8; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">VASTE RIT</span>
                                    <?php endif; ?>
                                    
                                    <br>
                                    <span style="font-size: 13px; color: #555;">📍 <?php echo htmlspecialchars($rit['bestemming_adres'] ?? 'Onbekend'); ?></span>
                                </td>
                                <td style="padding: 15px 10px; text-align: right;">
                                    <form method="POST" style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                        <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                        <input type="hidden" name="bron" value="<?php echo $rit['bron']; ?>">
                                        <select name="voertuig_id" style="padding: 8px; border: 2px solid #dc3545; background:#fff8f8; border-radius: 4px; width: 150px;">
                                            <option value="">-- Kies Bus --</option>
                                            <?php foreach($bussen as $bus): ?>
                                                <option value="<?php echo $bus['id']; ?>"><?php echo htmlspecialchars($bus['voertuig_nummer'] . ' ' . $bus['naam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="chauffeur_id" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 150px;">
                                            <option value="">-- Chauffeur (Later) --</option>
                                            <?php foreach($chauffeurs as $chauf): ?>
                                                <option value="<?php echo $chauf['id']; ?>"><?php echo htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" style="background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">Opslaan</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div style="background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #fd7e14; overflow: hidden;">
            <div style="background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 18px; color: #fd7e14;">🟠 2. Wacht op Chauffeur (<?php echo count($geen_chauffeur); ?>)</h2>
            </div>
            <div style="padding: 20px;">
                <?php if(count($geen_chauffeur) == 0): ?>
                    <p style="color: #666; font-style: italic; margin: 0;">Er zijn momenteel geen ritten die wachten op een chauffeur.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach($geen_chauffeur as $rit): 
                                $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                                $start = !empty($rit['start_tijd']) ? date('H:i', strtotime($rit['start_tijd'])) : '??:??';
                                $eind = !empty($rit['eind_tijd']) ? date('H:i', strtotime($rit['eind_tijd'])) : '??:??';
                            ?>
                            <tr style="border-bottom: 1px solid #f5f5f5;">
                                <td style="padding: 15px 10px; width: 140px;">
                                    <strong><?php echo date('d-m-Y', strtotime($rit['rit_datum'])); ?></strong><br>
                                    <span style="color: #d97706; font-size: 12px; font-weight: bold;">🕒 <?= $start ?> - <?= $eind ?></span><br>
                                    <span style="font-size: 12px; color: #888;">#<?php echo $rit['id']; ?></span>
                                </td>
                                <td style="padding: 15px 10px;">
                                    <strong><?php echo htmlspecialchars($klantNaam); ?></strong>
                                    
                                    <?php if ($rit['bron'] == 'calculatie' && empty($rit['datum_bevestiging_verstuurd'])): ?>
                                        <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">OPTIE</span>
                                    <?php elseif ($rit['bron'] == 'vaste_rit'): ?>
                                        <span style="background: #17a2b8; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">VASTE RIT</span>
                                    <?php endif; ?>

                                    <br>
                                    <span style="font-size: 13px; color: #555;">📍 <?php echo htmlspecialchars($rit['bestemming_adres'] ?? 'Onbekend'); ?></span>
                                </td>
                                <td style="padding: 15px 10px; text-align: right;">
                                    <form method="POST" style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                        <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                        <input type="hidden" name="bron" value="<?php echo $rit['bron']; ?>">
                                        <select name="voertuig_id" style="padding: 8px; border: 1px solid #ccc; background:#f8f9fa; border-radius: 4px; width: 150px;">
                                            <option value="">-- Wis Bus --</option>
                                            <?php foreach($bussen as $bus): ?>
                                                <option value="<?php echo $bus['id']; ?>" <?php if($rit['voertuig_id'] == $bus['id']) echo 'selected'; ?>><?php echo htmlspecialchars($bus['voertuig_nummer'] . ' ' . $bus['naam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="chauffeur_id" style="padding: 8px; border: 2px solid #fd7e14; background:#fffbf8; border-radius: 4px; width: 150px;">
                                            <option value="">-- Kies Chauffeur --</option>
                                            <?php foreach($chauffeurs as $chauf): ?>
                                                <option value="<?php echo $chauf['id']; ?>"><?php echo htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" style="background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">Opslaan</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div style="background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #28a745; overflow: hidden;">
            <div style="background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 18px; color: #28a745;">✅ 3. Volledig Gepland (<?php echo count($volledig_gepland); ?>)</h2>
            </div>
            <div style="padding: 20px;">
                <?php if(count($volledig_gepland) == 0): ?>
                    <p style="color: #666; text-align: center; margin: 20px 0;">Er zijn nog geen ritten volledig ingepland.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php foreach($volledig_gepland as $rit): 
                                $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'].' '.$rit['achternaam'];
                                $start = !empty($rit['start_tijd']) ? date('H:i', strtotime($rit['start_tijd'])) : '??:??';
                                $eind = !empty($rit['eind_tijd']) ? date('H:i', strtotime($rit['eind_tijd'])) : '??:??';
                            ?>
                            <tr style="border-bottom: 1px solid #f5f5f5; background: #fafafa;">
                                <td style="padding: 15px 10px; width: 140px;">
                                    <strong><?php echo date('d-m-Y', strtotime($rit['rit_datum'])); ?></strong><br>
                                    <span style="color: #d97706; font-size: 12px; font-weight: bold;">🕒 <?= $start ?> - <?= $eind ?></span><br>
                                    <span style="font-size: 12px; color: #888;">#<?php echo $rit['id']; ?></span>
                                </td>
                                <td style="padding: 15px 10px;">
                                    <strong><?php echo htmlspecialchars($klantNaam); ?></strong>
                                    
                                    <?php if ($rit['bron'] == 'calculatie' && empty($rit['datum_bevestiging_verstuurd'])): ?>
                                        <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">OPTIE</span>
                                    <?php elseif ($rit['bron'] == 'vaste_rit'): ?>
                                        <span style="background: #17a2b8; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 5px;">VASTE RIT</span>
                                    <?php endif; ?>

                                    <br>
                                    <span style="font-size: 13px; color: #555;">📍 <?php echo htmlspecialchars($rit['bestemming_adres'] ?? 'Onbekend'); ?></span>
                                </td>
                                <td style="padding: 15px 10px; text-align: right;">
                                    <form method="POST" style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                        <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                                        <input type="hidden" name="bron" value="<?php echo $rit['bron']; ?>">
                                        <select name="voertuig_id" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 150px; background: #f8f9fa;">
                                            <option value="">-- Wis Bus --</option>
                                            <?php foreach($bussen as $bus): ?>
                                                <option value="<?php echo $bus['id']; ?>" <?php if($rit['voertuig_id'] == $bus['id']) echo 'selected'; ?>><?php echo htmlspecialchars($bus['voertuig_nummer'] . ' ' . $bus['naam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="chauffeur_id" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 150px; background: #f8f9fa;">
                                            <option value="">-- Wis Chauffeur --</option>
                                            <?php foreach($chauffeurs as $chauf): ?>
                                                <option value="<?php echo $chauf['id']; ?>" <?php if($rit['chauffeur_id'] == $chauf['id']) echo 'selected'; ?>><?php echo htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" style="background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 12px;">Wijzig</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>