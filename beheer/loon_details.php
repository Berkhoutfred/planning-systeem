<?php
// Bestand: beheer/loon_details.php
// VERSIE: Kantoor - Loonadministratie Details (Inclusief Taxi & Kantoor)

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

$melding = '';

// 1. Controleer of we weten om wie en welke maand het gaat
$chauffeur_id = isset($_GET['chauffeur_id']) ? (int)$_GET['chauffeur_id'] : 0;
$werk_maand = isset($_GET['maand']) ? (int)$_GET['maand'] : 0;
$werk_jaar = isset($_GET['jaar']) ? (int)$_GET['jaar'] : 0;

if ($chauffeur_id === 0 || $werk_maand === 0 || $werk_jaar === 0) {
    die("<div style='padding:20px;'><h3 style='color:red;'>Fout in de link: Geen geldige chauffeur of maand doorgekregen.</h3><a href='loonadministratie.php'>Terug naar overzicht</a></div>");
}

// Bereken de juiste maand voor de Terug-knop
$terug_v_maand = $werk_maand + 1;
$terug_v_jaar = $werk_jaar;
if ($terug_v_maand == 13) { $terug_v_maand = 1; $terug_v_jaar++; }

// 2. Haal de gegevens van de chauffeur op
$stmt_chauf = $pdo->prepare("SELECT voornaam, achternaam, contracturen FROM chauffeurs WHERE id = ?");
$stmt_chauf->execute([$chauffeur_id]);
$chauffeur = $stmt_chauf->fetch();

if (!$chauffeur) {
    die("<div style='padding:20px;'><h3 style='color:red;'>Chauffeur niet gevonden in de database.</h3></div>");
}

$maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
$dagen_nl = [1=>'maandag', 2=>'dinsdag', 3=>'woensdag', 4=>'donderdag', 5=>'vrijdag', 6=>'zaterdag', 7=>'zondag'];

// --- ACTIES VERWERKEN ---

// Actie A: Een volledige regel updaten
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'update_regel') {
    $regel_id = (int)$_POST['regel_id'];
    
    $type_vervoer = $_POST['type_vervoer'];
    $u_basis      = (float)str_replace(',', '.', $_POST['uren_basis']);
    $t_avond      = (float)str_replace(',', '.', $_POST['toeslag_avond']);
    $t_weekend    = (float)str_replace(',', '.', $_POST['toeslag_weekend']);
    $t_zon        = (float)str_replace(',', '.', $_POST['toeslag_zon_feest']);
    $t_ov_nacht   = (float)str_replace(',', '.', $_POST['toeslag_ov_avond_nacht']);
    $t_ov_zat     = (float)str_replace(',', '.', $_POST['toeslag_ov_zaterdag']);
    $t_ov_zon     = (float)str_replace(',', '.', $_POST['toeslag_ov_zondag']);
    $onderbreking = (int)$_POST['onderbreking_aantal'];
    $notities     = trim($_POST['notities']);
    
    $stmt_upd = $pdo->prepare("UPDATE loon_uren SET 
        type_vervoer = ?, uren_basis = ?, 
        toeslag_avond = ?, toeslag_weekend = ?, toeslag_zon_feest = ?, 
        toeslag_ov_avond_nacht = ?, toeslag_ov_zaterdag = ?, toeslag_ov_zondag = ?, 
        onderbreking_aantal = ?, notities = ? 
        WHERE id = ? AND chauffeur_id = ?");
        
    if ($stmt_upd->execute([$type_vervoer, $u_basis, $t_avond, $t_weekend, $t_zon, $t_ov_nacht, $t_ov_zat, $t_ov_zon, $onderbreking, $notities, $regel_id, $chauffeur_id])) {
        $melding = "<div class='alert alert-success'>✅ Uren en toeslagen succesvol aangepast!</div>";
    }
}

// Actie B: Een compleet nieuwe dag toevoegen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'toevoegen_dag') {
    $nieuwe_datum = $_POST['nieuwe_datum'];
    $nieuw_type = $_POST['nieuw_type_vervoer'];
    $nieuwe_notitie = trim($_POST['nieuwe_notitie']);
    $nieuwe_uren = (float)str_replace(',', '.', $_POST['nieuwe_uren']);
    
    try {
        $stmt_ins = $pdo->prepare("INSERT INTO loon_uren 
            (chauffeur_id, datum, uren_basis, type_vervoer, notities, toeslag_avond, toeslag_weekend, toeslag_zon_feest, toeslag_ov_avond_nacht, toeslag_ov_zaterdag, toeslag_ov_zondag, onderbreking_aantal) 
            VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0)");
        $stmt_ins->execute([$chauffeur_id, $nieuwe_datum, $nieuwe_uren, $nieuw_type, $nieuwe_notitie]);
        $melding = "<div class='alert alert-success'>✅ Nieuwe dag (".$nieuwe_datum.") succesvol toegevoegd!</div>";
    } catch (PDOException $e) {
        $melding = "<div class='alert alert-danger'>❌ Fout: Bestaat deze datum al voor deze chauffeur? <br><small>" . $e->getMessage() . "</small></div>";
    }
}

// Actie C: Een foute dag verwijderen
if (isset($_GET['verwijder'])) {
    $verwijder_id = (int)$_GET['verwijder'];
    $stmt_del = $pdo->prepare("DELETE FROM loon_uren WHERE id = ? AND chauffeur_id = ?");
    $stmt_del->execute([$verwijder_id, $chauffeur_id]);
    $melding = "<div class='alert alert-success'>🗑️ Dag definitief verwijderd uit het systeem.</div>";
}

// --- EINDE ACTIES ---

// 3. Haal alle geregistreerde dagen op
$stmt_dagen = $pdo->prepare("
    SELECT * FROM loon_uren 
    WHERE chauffeur_id = ? AND MONTH(datum) = ? AND YEAR(datum) = ? 
    ORDER BY datum ASC
");
$stmt_dagen->execute([$chauffeur_id, $werk_maand, $werk_jaar]);
$gewerkte_dagen = $stmt_dagen->fetchAll();
?>

<style>
    .detail-container { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .back-link { display: inline-block; margin-bottom: 15px; color: #555; text-decoration: none; font-weight: 600; font-size: 15px; }
    .back-link:hover { color: #003366; text-decoration: underline; }
    
    .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    .loon-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
    .loon-table th, .loon-table td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
    .loon-table th { background: #003366; color: white; }
    .loon-table tr:nth-child(even) { background-color: #f9f9f9; }
    .loon-table tr:hover { background-color: #f1f5f9; }
    
    .input-text { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 12px; }
    
    .edit-grid { display: grid; grid-template-columns: 55px 1fr; gap: 4px; align-items: center; font-size: 11px; margin-bottom: 3px; }
    .edit-grid label { color: #555; font-weight: bold; }
    .edit-grid input { padding: 4px; font-size: 12px; width: 60px; border: 1px solid #ccc; border-radius: 3px; text-align: center; }
    
    .btn-opslaan { background: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; margin-bottom: 5px; }
    .btn-opslaan:hover { background: #218838; }
    
    .btn-verwijder { background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; text-decoration: none; display: block; text-align: center; font-weight: bold; }
    .btn-verwijder:hover { background: #c82333; }
    
    .toevoeg-box { background: #e8f4f8; padding: 20px; border-radius: 6px; border: 1px solid #b8daff; margin-bottom: 30px; }
    .toevoeg-box h4 { margin-top: 0; color: #0056b3; }
</style>

<div class="container" style="max-width: 1400px; margin: auto; padding: 20px;">
    
    <a href="loonadministratie.php?v_maand=<?php echo $terug_v_maand; ?>&v_jaar=<?php echo $terug_v_jaar; ?>" class="back-link">&larr; Terug naar Dashboard</a>
    
    <div class="detail-container">
        <h2 style="color: #003366; margin-top: 0;">
            <i class="fas fa-user-edit"></i> Cockpit: <?php echo htmlspecialchars($chauffeur['voornaam'] . ' ' . $chauffeur['achternaam']); ?>
        </h2>
        <p style="color: #666; font-size: 16px;">
            Gewerkte maand: <strong><?php echo $maand_namen[$werk_maand] . ' ' . $werk_jaar; ?></strong> 
            <span style="color: #999; margin-left: 10px;">(Contracturen: <?php echo (float)$chauffeur['contracturen']; ?>)</span>
        </p>
        
        <?php echo $melding; ?>

        <div class="toevoeg-box">
            <h4><i class="fas fa-plus-circle"></i> Handmatig een dag toevoegen (Bijv. Ziekte of Correctie)</h4>
            <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="actie" value="toevoegen_dag">
                
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Datum</label>
                    <input type="date" name="nieuwe_datum" class="input-text" style="width: 130px;" required value="<?php echo $werk_jaar . '-' . str_pad($werk_maand, 2, '0', STR_PAD_LEFT) . '-01'; ?>">
                </div>
                
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Type Vervoer</label>
                    <select name="nieuw_type_vervoer" class="input-text" style="width: 130px;">
                        <option value="Groepsvervoer">Groepsvervoer</option>
                        <option value="OV">OV</option>
                        <option value="Taxi">Taxi</option>
                        <option value="Kantoor">Kantoor</option>
                        <option value="Onbekend">Onbekend/Ziek</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Basis Uren</label>
                    <input type="number" step="0.25" name="nieuwe_uren" class="input-text" style="width: 80px; text-align: center;" value="0.00">
                </div>
                
                <div style="flex-grow: 1; max-width: 400px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Bijzonderheden (Optioneel)</label>
                    <input type="text" name="nieuwe_notitie" class="input-text" placeholder="Bijv: Ziek of Reiskosten">
                </div>
                
                <div>
                    <button type="submit" class="btn-opslaan" style="margin: 0; padding: 8px 20px;"><i class="fas fa-save"></i> Toevoegen</button>
                </div>
            </form>
        </div>

        <h4 style="color: #333; margin-bottom: 10px;">Geregistreerde ritten & uren:</h4>
        
        <?php if(count($gewerkte_dagen) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="loon-table">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Datum & Type</th>
                            <th style="width: 140px; background:#28a745;">Basis & Onderb.</th>
                            <th style="width: 140px; background:#004080;">Toeslag (Normaal)</th>
                            <th style="width: 140px; background:#6f42c1;">Toeslag (OV)</th>
                            <th>Bijzonderheden / Notities</th>
                            <th style="width: 100px; background:#333;">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($gewerkte_dagen as $dag): 
                            $dag_nr = date('N', strtotime($dag['datum']));
                            $mooie_datum = $dagen_nl[$dag_nr] . ' ' . date('d-m-Y', strtotime($dag['datum']));
                        ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="actie" value="update_regel">
                                    <input type="hidden" name="regel_id" value="<?php echo $dag['id']; ?>">
                                    
                                    <td>
                                        <strong style="color: #003366; font-size: 14px;"><?php echo $mooie_datum; ?></strong><br>
                                        <select name="type_vervoer" class="input-text" style="margin-top: 8px; background: #f4f7f6;">
                                            <option value="Groepsvervoer" <?php if($dag['type_vervoer'] == 'Groepsvervoer') echo 'selected'; ?>>Groepsvervoer</option>
                                            <option value="OV" <?php if($dag['type_vervoer'] == 'OV') echo 'selected'; ?>>OV</option>
                                            <option value="Taxi" <?php if($dag['type_vervoer'] == 'Taxi') echo 'selected'; ?>>Taxi</option>
                                            <option value="Kantoor" <?php if($dag['type_vervoer'] == 'Kantoor') echo 'selected'; ?>>Kantoor</option>
                                            <option value="Onbekend" <?php if($dag['type_vervoer'] == 'Onbekend') echo 'selected'; ?>>Onbekend</option>
                                        </select>
                                    </td>
                                    
                                    <td style="background: #f8fff9;">
                                        <div class="edit-grid">
                                            <label>Basis:</label>
                                            <input type="number" step="0.25" name="uren_basis" value="<?php echo (float)$dag['uren_basis']; ?>">
                                        </div>
                                        <div class="edit-grid">
                                            <label>Onderb:</label>
                                            <input type="number" step="1" name="onderbreking_aantal" value="<?php echo (int)$dag['onderbreking_aantal']; ?>">
                                        </div>
                                    </td>
                                    
                                    <td style="background: #f4f8ff;">
                                        <div class="edit-grid">
                                            <label>Avond:</label>
                                            <input type="number" step="0.25" name="toeslag_avond" value="<?php echo (float)$dag['toeslag_avond']; ?>">
                                        </div>
                                        <div class="edit-grid">
                                            <label>Zat:</label>
                                            <input type="number" step="0.25" name="toeslag_weekend" value="<?php echo (float)$dag['toeslag_weekend']; ?>">
                                        </div>
                                        <div class="edit-grid">
                                            <label>Zon/F:</label>
                                            <input type="number" step="0.25" name="toeslag_zon_feest" value="<?php echo (float)$dag['toeslag_zon_feest']; ?>">
                                        </div>
                                    </td>
                                    
                                    <td style="background: #fbf8ff;">
                                        <div class="edit-grid">
                                            <label>Nacht:</label>
                                            <input type="number" step="0.25" name="toeslag_ov_avond_nacht" value="<?php echo (float)$dag['toeslag_ov_avond_nacht']; ?>">
                                        </div>
                                        <div class="edit-grid">
                                            <label>Zat:</label>
                                            <input type="number" step="0.25" name="toeslag_ov_zaterdag" value="<?php echo (float)$dag['toeslag_ov_zaterdag']; ?>">
                                        </div>
                                        <div class="edit-grid">
                                            <label>Zon/F:</label>
                                            <input type="number" step="0.25" name="toeslag_ov_zondag" value="<?php echo (float)$dag['toeslag_ov_zondag']; ?>">
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <textarea name="notities" class="input-text" rows="3" placeholder="Bijv: Reiskosten € 15,-" style="height: 100%; min-height: 70px;"><?php echo htmlspecialchars($dag['notities']); ?></textarea>
                                    </td>
                                    
                                    <td>
                                        <button type="submit" class="btn-opslaan" title="Opslaan"><i class="fas fa-save"></i> Opslaan</button>
                                        
                                        <a href="?chauffeur_id=<?php echo $chauffeur_id; ?>&maand=<?php echo $werk_maand; ?>&jaar=<?php echo $werk_jaar; ?>&verwijder=<?php echo $dag['id']; ?>" 
                                           class="btn-verwijder" 
                                           onclick="if(confirm('Weet je zeker dat je de uren van <?php echo $mooie_datum; ?> wilt verwijderen?')) { return confirm('⚠️ LET OP: Deze actie wist de dag definitief uit de database! Weet je het 100% zeker?'); } else { return false; }" 
                                           title="Verwijderen"><i class="fas fa-trash-alt"></i> Wis Dag</a>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 5px; border: 1px solid #ffeeba;">
                Er staan nog geen ritten in het systeem voor deze maand. Voeg er hierboven handmatig eentje toe.
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>