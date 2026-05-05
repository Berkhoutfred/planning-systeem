<?php
// beheer/voertuig-bewerken.php
// VERSIE: Compleet Voertuigdossier (Bewerken)

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    echo "Geen voertuig ID opgegeven.";
    exit;
}
$id = (int)$_GET['id'];

// OPSLAAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE voertuigen SET 
            voertuig_nummer = ?, naam = ?, type = ?, kenteken = ?, chassisnummer = ?, 
            zitplaatsen = ?, status = ?, euroklasse = ?, 
            apk_datum = ?, tacho_datum = ?, brandblusser_datum = ?, 
            km_kostprijs = ?, onderhoud_notities = ?
            WHERE id = ?";
            
        // Slimme functies om lege velden netjes als NULL in de database te zetten
        $nulldate = function($val) { return !empty($val) ? $val : null; };
        $nullstr  = function($val) { return !empty($val) ? $val : null; };
        $nullnum  = function($val) { return !empty($val) ? str_replace(',', '.', $val) : null; };

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nullstr($_POST['voertuig_nummer']),
            $_POST['naam'],
            $nullstr($_POST['type']),
            $_POST['kenteken'],
            $nullstr($_POST['chassisnummer']),
            
            $_POST['zitplaatsen'],
            $_POST['status'] ?? 'beschikbaar',
            $nullstr($_POST['euroklasse']),
            
            $nulldate($_POST['apk_datum']),
            $nulldate($_POST['tacho_datum']),
            $nulldate($_POST['brandblusser_datum']),
            
            $nullnum($_POST['km_kostprijs']),
            $nullstr($_POST['onderhoud_notities']),
            
            $id
        ]);

        echo "<script>window.location.href='voertuigen.php';</script>";
        exit;
    } catch (PDOException $e) {
        $foutmelding = "Fout bij opslaan: " . $e->getMessage();
    }
}

// OPHALEN
$stmt = $pdo->prepare("SELECT * FROM voertuigen WHERE id = ?");
$stmt->execute([$id]);
$bus = $stmt->fetch();

if (!$bus) {
    echo "Voertuig niet gevonden.";
    exit;
}

// Bepaal de juiste status voor de dropdown (zet oude statussen om naar de nieuwe)
$huidige_status = strtolower($bus['status'] ?? 'beschikbaar');
if ($huidige_status == 'stuk' || $huidige_status == 'onderhoud') {
    $huidige_status = 'werkplaats';
}
?>

<div style="max-width: 800px; margin: auto; padding-bottom: 50px;">
    <h2>Voertuig Wijzigen: <?php echo htmlspecialchars($bus['naam']); ?></h2>

    <?php if(isset($foutmelding)) echo "<div style='color:red; background:#fee2e2; padding:10px; margin-bottom:15px; border-radius:5px;'>$foutmelding</div>"; ?>

    <form method="POST" style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Segoe UI', sans-serif;">
        
        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">1. Identificatie & Capaciteit</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Voertuignummer (bijv. 12):</label>
                <input type="text" name="voertuig_nummer" value="<?php echo htmlspecialchars($bus['voertuig_nummer'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Merk / Naam (bijv. Mercedes): *</label>
                <input type="text" name="naam" value="<?php echo htmlspecialchars($bus['naam'] ?? ''); ?>" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Type (bijv. Tourismo):</label>
                <input type="text" name="type" value="<?php echo htmlspecialchars($bus['type'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Kenteken: *</label>
                <input type="text" name="kenteken" value="<?php echo htmlspecialchars($bus['kenteken'] ?? ''); ?>" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="grid-column: span 2;">
                <label>Chassisnummer (VIN):</label>
                <input type="text" name="chassisnummer" value="<?php echo htmlspecialchars($bus['chassisnummer'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Aantal Zitplaatsen: *</label>
                <input type="number" name="zitplaatsen" value="<?php echo htmlspecialchars($bus['zitplaatsen'] ?? ''); ?>" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Euroklasse (bijv. Euro 6):</label>
                <input type="text" name="euroklasse" value="<?php echo htmlspecialchars($bus['euroklasse'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="grid-column: span 2;">
                <label>Huidige Status:</label>
                <select name="status" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="beschikbaar" <?php if($huidige_status == 'beschikbaar') echo 'selected'; ?>>🟢 Beschikbaar (Actief)</option>
                    <option value="werkplaats" <?php if($huidige_status == 'werkplaats') echo 'selected'; ?>>🔴 In de Werkplaats / Defect</option>
                </select>
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">2. Keuringen & Veiligheid</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>APK Geldig Tot:</label>
                <input type="date" name="apk_datum" value="<?php echo htmlspecialchars($bus['apk_datum'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Tacho Geijkt Tot:</label>
                <input type="date" name="tacho_datum" value="<?php echo htmlspecialchars($bus['tacho_datum'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Brandblusser Tot:</label>
                <input type="date" name="brandblusser_datum" value="<?php echo htmlspecialchars($bus['brandblusser_datum'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">3. Financieel & Onderhoudslogboek</h3>
        <div style="margin-bottom: 15px;">
            <label>Kostprijs per km (€):</label><br>
            <span style="font-size: 12px; color: #666;">(Wordt later gebruikt voor de exacte ritten-calculatie)</span>
            <input type="number" step="0.01" name="km_kostprijs" value="<?php echo htmlspecialchars($bus['km_kostprijs'] ?? ''); ?>" placeholder="bijv. 1.25" style="width: 100%; max-width: 200px; padding: 8px; border:1px solid #ccc; border-radius:4px; display: block; margin-top: 5px;">
        </div>
        <div style="margin-bottom: 15px;">
            <label>Onderhoudsnotities / Afwezigheid:</label><br>
            <span style="font-size: 12px; color: #666;">(Bijv: "12-05-2026: Nieuwe remschijven" of "Staat t/m vrijdag stil ivm schade")</span>
            <textarea name="onderhoud_notities" rows="4" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px; margin-top: 5px;"><?php echo htmlspecialchars($bus['onderhoud_notities'] ?? ''); ?></textarea>
        </div>

        <div style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px;">
            <button type="submit" style="background: #007bff; color: white; padding: 12px 25px; font-size: 16px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer;">
                💾 Wijzigingen Opslaan
            </button>
            <a href="voertuigen.php" style="margin-left: 15px; color: #555; text-decoration: none;">Annuleren</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>