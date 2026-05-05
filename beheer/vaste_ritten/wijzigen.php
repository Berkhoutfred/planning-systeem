<?php
// Bestand: beheer/vaste_ritten/bewerken.php
// VERSIE: Bestaand Sjabloon Vaste Ritten Wijzigen

include '../../beveiliging.php';
require '../includes/db.php';
include '../includes/header.php';

$melding = '';

// Check of er een ID is meegegeven in de link
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href='overzicht.php';</script>";
    exit;
}

$id = (int)$_GET['id'];

// --- FORMULIER OPSLAAN (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'opslaan') {
    $naam = trim($_POST['naam']);
    $startdatum = $_POST['startdatum'];
    $einddatum = $_POST['einddatum'];
    $vertrektijd = $_POST['vertrektijd'];
    $aankomsttijd = $_POST['aankomsttijd'];
    $ophaaladres = trim($_POST['ophaaladres']);
    $bestemming = trim($_POST['bestemming']);
    $voertuig_id = !empty($_POST['voertuig_id']) ? (int)$_POST['voertuig_id'] : NULL;
    $chauffeur_id = !empty($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : NULL;
    $uitzonderingen = trim($_POST['uitzondering_datums']);
    $notities = trim($_POST['notities']);
    
    $ma = isset($_POST['rijdt_ma']) ? 1 : 0;
    $di = isset($_POST['rijdt_di']) ? 1 : 0;
    $wo = isset($_POST['rijdt_wo']) ? 1 : 0;
    $do = isset($_POST['rijdt_do']) ? 1 : 0;
    $vr = isset($_POST['rijdt_vr']) ? 1 : 0;
    $za = isset($_POST['rijdt_za']) ? 1 : 0;
    $zo = isset($_POST['rijdt_zo']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE vaste_ritten SET 
            naam = ?, startdatum = ?, einddatum = ?, vertrektijd = ?, aankomsttijd = ?, 
            ophaaladres = ?, bestemming = ?, voertuig_id = ?, chauffeur_id = ?, 
            rijdt_ma = ?, rijdt_di = ?, rijdt_wo = ?, rijdt_do = ?, rijdt_vr = ?, rijdt_za = ?, rijdt_zo = ?, 
            uitzondering_datums = ?, notities = ? 
            WHERE id = ?");
        
        $stmt->execute([$naam, $startdatum, $einddatum, $vertrektijd, $aankomsttijd, $ophaaladres, $bestemming, $voertuig_id, $chauffeur_id, $ma, $di, $wo, $do, $vr, $za, $zo, $uitzonderingen, $notities, $id]);
        
        $melding = "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>Sjabloon succesvol gewijzigd!</div>";
    } catch (PDOException $e) {
        $melding = "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>Fout bij opslaan: " . $e->getMessage() . "</div>";
    }
}

// --- HUIDIGE GEGEVENS OPHALEN ---
$stmt_rit = $pdo->prepare("SELECT * FROM vaste_ritten WHERE id = ?");
$stmt_rit->execute([$id]);
$rit = $stmt_rit->fetch();

if (!$rit) {
    echo "Rit niet gevonden.";
    exit;
}

// --- DATA OPHALEN VOOR KEUZEMENU'S ---
$chauffeurs = $pdo->query("SELECT id, voornaam, achternaam FROM chauffeurs WHERE archief = 0 ORDER BY voornaam")->fetchAll();
$voertuigen = $pdo->query("SELECT id, naam, kenteken FROM voertuigen WHERE archief = 0 ORDER BY naam")->fetchAll();
?>

<div style="max-width: 900px; margin: 0 auto;">
    <a href="overzicht.php" style="color: #555; text-decoration: none; font-weight: bold; margin-bottom: 15px; display: inline-block;">&larr; Terug naar overzicht</a>

    <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h2 style="color: #003366; margin-top: 0;">✏️ Vaste Rit Wijzigen</h2>
        
        <?php echo $melding; ?>

        <form method="POST">
            <input type="hidden" name="actie" value="opslaan">

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h4 style="margin-top: 0; color: #333;">1. Algemene Informatie</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Naam van de Rit / Contract *</label>
                    <input type="text" name="naam" required value="<?php echo htmlspecialchars($rit['naam']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Standaard Voertuig</label>
                        <select name="voertuig_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">- Kies later op planbord -</option>
                            <?php foreach($voertuigen as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo ($rit['voertuig_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['naam'] . ' (' . $v['kenteken'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Standaard Chauffeur</label>
                        <select name="chauffeur_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="">- Kies later op planbord -</option>
                            <?php foreach($chauffeurs as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($rit['chauffeur_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h4 style="margin-top: 0; color: #333;">2. Periode en Tijden</h4>
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Startdatum *</label>
                        <input type="date" name="startdatum" required value="<?php echo $rit['startdatum']; ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Einddatum *</label>
                        <input type="date" name="einddatum" required value="<?php echo $rit['einddatum']; ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Vertrektijd *</label>
                        <input type="time" name="vertrektijd" required value="<?php echo $rit['vertrektijd']; ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Aankomsttijd *</label>
                        <input type="time" name="aankomsttijd" required value="<?php echo $rit['aankomsttijd']; ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Welke dagen in de week? *</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <label><input type="checkbox" name="rijdt_ma" value="1" <?php echo $rit['rijdt_ma'] ? 'checked' : ''; ?>> Maandag</label>
                        <label><input type="checkbox" name="rijdt_di" value="1" <?php echo $rit['rijdt_di'] ? 'checked' : ''; ?>> Dinsdag</label>
                        <label><input type="checkbox" name="rijdt_wo" value="1" <?php echo $rit['rijdt_wo'] ? 'checked' : ''; ?>> Woensdag</label>
                        <label><input type="checkbox" name="rijdt_do" value="1" <?php echo $rit['rijdt_do'] ? 'checked' : ''; ?>> Donderdag</label>
                        <label><input type="checkbox" name="rijdt_vr" value="1" <?php echo $rit['rijdt_vr'] ? 'checked' : ''; ?>> Vrijdag</label>
                        <label><input type="checkbox" name="rijdt_za" value="1" <?php echo $rit['rijdt_za'] ? 'checked' : ''; ?>> Zaterdag</label>
                        <label><input type="checkbox" name="rijdt_zo" value="1" <?php echo $rit['rijdt_zo'] ? 'checked' : ''; ?>> Zondag</label>
                    </div>
                </div>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h4 style="margin-top: 0; color: #333;">3. Locaties & Uitzonderingen</h4>
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Ophaaladres *</label>
                        <input type="text" name="ophaaladres" required value="<?php echo htmlspecialchars($rit['ophaaladres']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Bestemming *</label>
                        <input type="text" name="bestemming" required value="<?php echo htmlspecialchars($rit['bestemming']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Uitzonderingsdatums (Feestdagen waarop NIET gereden wordt)</label>
                    <input type="text" name="uitzondering_datums" value="<?php echo htmlspecialchars($rit['uitzondering_datums']); ?>" placeholder="Bijv. 06-04-2026, 27-04-2026" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                </div>

                <div>
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Interne Notities</label>
                    <textarea name="notities" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"><?php echo htmlspecialchars($rit['notities']); ?></textarea>
                </div>
            </div>

            <button type="submit" style="background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%;"><i class="fas fa-save"></i> Wijzigingen Opslaan</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>