<?php
// beheer/chauffeur-bewerken.php
// VERSIE: Uitgebreid profiel bewerken (Schoon, met robuuste datum-opslag)

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    echo "Geen ID opgegeven.";
    exit;
}
$id = (int)$_GET['id'];

// OPSLAAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE chauffeurs SET 
            voornaam = ?, achternaam = ?, email = ?, telefoon = ?, pincode = ?,
            geboortedatum = ?, datum_in_dienst = ?, datum_uit_dienst = ?,
            rijbewijsnummer = ?, rijbewijs_verloopt = ?,
            bestuurderskaart = ?, bestuurderskaart_geldig_tot = ?,
            code95_geldig_tot = ?, ehbo = ?,
            code95_cursus1_naam = ?, code95_cursus1_datum = ?,
            code95_cursus2_naam = ?, code95_cursus2_datum = ?,
            code95_cursus3_naam = ?, code95_cursus3_datum = ?,
            code95_cursus4_naam = ?, code95_cursus4_datum = ?,
            code95_cursus5_naam = ?, code95_cursus5_datum = ?
            WHERE id = ?";
            
        // Functie om strings netjes op te vangen
        $nullstr = function($val) { return !empty($val) ? $val : null; };
        
        // Robuuste functie om datums ALTIJD als YYYY-MM-DD naar MySQL te sturen
        $formatDate = function($val) {
            if(empty($val) || $val == '0000-00-00') return null;
            $time = strtotime(str_replace('/', '-', $val));
            if($time) {
                return date('Y-m-d', $time);
            }
            return null;
        };

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['voornaam'],
            $_POST['achternaam'],
            $nullstr($_POST['email']),
            $nullstr($_POST['telefoon']),
            $nullstr($_POST['pincode']), 
            
            $formatDate($_POST['geboortedatum']),
            $formatDate($_POST['datum_in_dienst']),
            $formatDate($_POST['datum_uit_dienst']),
            
            $nullstr($_POST['rijbewijsnummer']),
            $formatDate($_POST['rijbewijs_verloopt']),
            
            $nullstr($_POST['bestuurderskaart']),
            $formatDate($_POST['bestuurderskaart_geldig_tot']),
            
            $formatDate($_POST['code95_geldig_tot']),
            $_POST['ehbo'] ?? 'nee',
            
            $nullstr($_POST['c1_naam']), $formatDate($_POST['c1_datum']),
            $nullstr($_POST['c2_naam']), $formatDate($_POST['c2_datum']),
            $nullstr($_POST['c3_naam']), $formatDate($_POST['c3_datum']),
            $nullstr($_POST['c4_naam']), $formatDate($_POST['c4_datum']),
            $nullstr($_POST['c5_naam']), $formatDate($_POST['c5_datum']),
            
            $id
        ]);

        // Na opslaan geruisloos terug naar het overzicht
        echo "<script>window.location.href='chauffeurs.php';</script>";
        exit;
    } catch (PDOException $e) {
        $foutmelding = "Fout bij opslaan: " . $e->getMessage();
    }
}

// HUIDIGE GEGEVENS OPHALEN
$stmt = $pdo->prepare("SELECT * FROM chauffeurs WHERE id = ?");
$stmt->execute([$id]);
$driver = $stmt->fetch();

if (!$driver) {
    echo "Chauffeur niet gevonden.";
    exit;
}
?>

<div style="max-width: 800px; margin: auto; padding-bottom: 50px;">
    <h2>Chauffeur Wijzigen: <?php echo htmlspecialchars($driver['voornaam'] . ' ' . $driver['achternaam']); ?></h2>

    <?php if(isset($foutmelding)) echo "<div style='color:red; background:#fee2e2; padding:10px; margin-bottom:15px; border-radius:5px;'>$foutmelding</div>"; ?>

    <form method="POST" style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Segoe UI', sans-serif;">
        
        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">1. Persoonsgegevens</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Voornaam: *</label>
                <input type="text" name="voornaam" value="<?php echo htmlspecialchars($driver['voornaam'] ?? ''); ?>" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Achternaam: *</label>
                <input type="text" name="achternaam" value="<?php echo htmlspecialchars($driver['achternaam'] ?? ''); ?>" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Geboortedatum:</label>
                <input type="date" name="geboortedatum" value="<?php echo htmlspecialchars($driver['geboortedatum'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>EHBO Certificaat:</label>
                <select name="ehbo" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="nee" <?php if(($driver['ehbo'] ?? 'nee') == 'nee') echo 'selected'; ?>>Nee</option>
                    <option value="ja" <?php if(($driver['ehbo'] ?? '') == 'ja') echo 'selected'; ?>>Ja, in bezit</option>
                </select>
            </div>
            <div>
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($driver['email'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Telefoonnummer:</label>
                <input type="text" name="telefoon" value="<?php echo htmlspecialchars($driver['telefoon'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="grid-column: span 2; background: #e3f2fd; padding: 15px; border-radius: 4px; border: 1px solid #b6d4fe;">
                <label style="font-weight: bold; color: #003366;">Pincode (Voor inloggen Chauffeurs-App):</label>
                <input type="text" name="pincode" value="<?php echo htmlspecialchars($driver['pincode'] ?? ''); ?>" placeholder="Bijv. 1234" style="width: 100%; max-width: 200px; padding: 8px; border:1px solid #ccc; border-radius:4px; display: block; margin-top: 5px;">
                <span style="font-size: 12px; color: #555;">Kies een simpele code waarmee de chauffeur snel kan inloggen op zijn mobiel.</span>
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">2. Contract</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Datum in dienst:</label>
                <input type="date" name="datum_in_dienst" value="<?php echo htmlspecialchars($driver['datum_in_dienst'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Datum uit dienst:</label>
                <input type="date" name="datum_uit_dienst" value="<?php echo htmlspecialchars($driver['datum_uit_dienst'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">3. Rijbewijs & Pasjes</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Rijbewijsnummer:</label>
                <input type="text" name="rijbewijsnummer" value="<?php echo htmlspecialchars($driver['rijbewijsnummer'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Rijbewijs geldig tot:</label>
                <input type="date" name="rijbewijs_verloopt" value="<?php echo htmlspecialchars($driver['rijbewijs_verloopt'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Bestuurderskaart nummer:</label>
                <input type="text" name="bestuurderskaart" value="<?php echo htmlspecialchars($driver['bestuurderskaart'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Bestuurderskaart geldig tot:</label>
                <input type="date" name="bestuurderskaart_geldig_tot" value="<?php echo htmlspecialchars($driver['bestuurderskaart_geldig_tot'] ?? ''); ?>" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 5px; color: #007bff;">4. Code 95 Cursussen</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-weight: bold; color: #d97706;">Code 95 Geldig Tot:</label>
            <input type="date" name="code95_geldig_tot" value="<?php echo htmlspecialchars($driver['code95_geldig_tot'] ?? ''); ?>" style="width: 100%; max-width: 385px; padding: 8px; border:2px solid #fcd34d; border-radius:4px; display: block; margin-top:5px;">
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr style="background: #eee;">
                <th style="padding: 10px; text-align: left;">Cursus Nummer</th>
                <th style="padding: 10px; text-align: left;">Naam Cursus</th>
                <th style="padding: 10px; text-align: left;">Behaald op</th>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 1</td>
                <td style="padding: 5px;"><input type="text" name="c1_naam" value="<?php echo htmlspecialchars($driver['code95_cursus1_naam'] ?? ''); ?>" placeholder="Bijv. VCA" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c1_datum" value="<?php echo htmlspecialchars($driver['code95_cursus1_datum'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 2</td>
                <td style="padding: 5px;"><input type="text" name="c2_naam" value="<?php echo htmlspecialchars($driver['code95_cursus2_naam'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c2_datum" value="<?php echo htmlspecialchars($driver['code95_cursus2_datum'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 3</td>
                <td style="padding: 5px;"><input type="text" name="c3_naam" value="<?php echo htmlspecialchars($driver['code95_cursus3_naam'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c3_datum" value="<?php echo htmlspecialchars($driver['code95_cursus3_datum'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 4</td>
                <td style="padding: 5px;"><input type="text" name="c4_naam" value="<?php echo htmlspecialchars($driver['code95_cursus4_naam'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c4_datum" value="<?php echo htmlspecialchars($driver['code95_cursus4_datum'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 5</td>
                <td style="padding: 5px;"><input type="text" name="c5_naam" value="<?php echo htmlspecialchars($driver['code95_cursus5_naam'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c5_datum" value="<?php echo htmlspecialchars($driver['code95_cursus5_datum'] ?? ''); ?>" style="width:100%; padding:6px;"></td>
            </tr>
        </table>

        <div style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px;">
            <button type="submit" style="background: #007bff; color: white; padding: 12px 25px; font-size: 16px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer;">
                💾 Wijzigingen Opslaan
            </button>
            <a href="chauffeurs.php" style="margin-left: 15px; color: #555; text-decoration: none;">Annuleren</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>