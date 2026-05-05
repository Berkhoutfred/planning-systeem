<?php
// beheer/chauffeur-toevoegen.php
// VERSIE: Uitgebreid profiel (Inclusief Code 95, Pasjes & Pincode voor App)

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "INSERT INTO chauffeurs (
            voornaam, achternaam, email, telefoon, wachtwoord, pincode,
            geboortedatum, datum_in_dienst, datum_uit_dienst,
            rijbewijsnummer, rijbewijs_verloopt, 
            bestuurderskaart, bestuurderskaart_geldig_tot, 
            code95_geldig_tot, ehbo,
            code95_cursus1_naam, code95_cursus1_datum,
            code95_cursus2_naam, code95_cursus2_datum,
            code95_cursus3_naam, code95_cursus3_datum,
            code95_cursus4_naam, code95_cursus4_datum,
            code95_cursus5_naam, code95_cursus5_datum
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?, 
            ?, ?
        )";
        
        $tijdelijk_ww = password_hash('welkom123', PASSWORD_DEFAULT);

        // Slimme functie: Maakt van een leeg datumveld netjes 'NULL' voor de database
        $nulldate = function($val) { return !empty($val) ? $val : null; };
        $nullstr = function($val) { return !empty($val) ? $val : null; };

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['voornaam'],
            $_POST['achternaam'],
            $nullstr($_POST['email']),
            $nullstr($_POST['telefoon']),
            $tijdelijk_ww,
            $nullstr($_POST['pincode']), // NIEUW: Pincode
            
            $nulldate($_POST['geboortedatum']),
            $nulldate($_POST['datum_in_dienst']),
            $nulldate($_POST['datum_uit_dienst']),
            
            $nullstr($_POST['rijbewijsnummer']),
            $nulldate($_POST['rijbewijs_verloopt']),
            
            $nullstr($_POST['bestuurderskaart']),
            $nulldate($_POST['bestuurderskaart_geldig_tot']),
            
            $nulldate($_POST['code95_geldig_tot']),
            $_POST['ehbo'] ?? 'nee',
            
            $nullstr($_POST['c1_naam']), $nulldate($_POST['c1_datum']),
            $nullstr($_POST['c2_naam']), $nulldate($_POST['c2_datum']),
            $nullstr($_POST['c3_naam']), $nulldate($_POST['c3_datum']),
            $nullstr($_POST['c4_naam']), $nulldate($_POST['c4_datum']),
            $nullstr($_POST['c5_naam']), $nulldate($_POST['c5_datum'])
        ]);

        echo "<script>window.location.href='chauffeurs.php';</script>";
        exit;
    } catch (PDOException $e) {
        $foutmelding = "Fout bij opslaan: " . $e->getMessage();
    }
}
?>

<div style="max-width: 800px; margin: auto; padding-bottom: 50px;">
    <h2>Nieuwe Chauffeur Toevoegen</h2>

    <?php if(isset($foutmelding)) echo "<div style='color:red; background:#fee2e2; padding:10px; margin-bottom:15px; border-radius:5px;'>$foutmelding</div>"; ?>

    <form method="POST" style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Segoe UI', sans-serif;">
        
        <h3 style="border-bottom: 2px solid #003366; padding-bottom: 5px; color: #003366;">1. Persoonsgegevens</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Voornaam: *</label>
                <input type="text" name="voornaam" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Achternaam: *</label>
                <input type="text" name="achternaam" required style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Geboortedatum:</label>
                <input type="date" name="geboortedatum" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>EHBO Certificaat:</label>
                <select name="ehbo" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
                    <option value="nee">Nee</option>
                    <option value="ja">Ja, in bezit</option>
                </select>
            </div>
            <div>
                <label>Email:</label>
                <input type="email" name="email" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Telefoonnummer:</label>
                <input type="text" name="telefoon" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div style="grid-column: span 2; background: #e3f2fd; padding: 15px; border-radius: 4px; border: 1px solid #b6d4fe;">
                <label style="font-weight: bold; color: #003366;">Pincode (Voor inloggen Chauffeurs-App):</label>
                <input type="text" name="pincode" placeholder="Bijv. 1234" style="width: 100%; max-width: 200px; padding: 8px; border:1px solid #ccc; border-radius:4px; display: block; margin-top: 5px;">
                <span style="font-size: 12px; color: #555;">Kies een simpele code waarmee de chauffeur snel kan inloggen op zijn mobiel.</span>
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #003366; padding-bottom: 5px; color: #003366;">2. Contract</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Datum in dienst:</label>
                <input type="date" name="datum_in_dienst" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Datum uit dienst:</label>
                <input type="date" name="datum_uit_dienst" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #003366; padding-bottom: 5px; color: #003366;">3. Rijbewijs & Pasjes</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div>
                <label>Rijbewijsnummer:</label>
                <input type="text" name="rijbewijsnummer" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Rijbewijs geldig tot:</label>
                <input type="date" name="rijbewijs_verloopt" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Bestuurderskaart nummer:</label>
                <input type="text" name="bestuurderskaart" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label>Bestuurderskaart geldig tot:</label>
                <input type="date" name="bestuurderskaart_geldig_tot" style="width: 100%; padding: 8px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <h3 style="border-bottom: 2px solid #003366; padding-bottom: 5px; color: #003366;">4. Code 95 Cursussen</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-weight: bold; color: #d97706;">Code 95 Geldig Tot:</label>
            <input type="date" name="code95_geldig_tot" style="width: 100%; max-width: 385px; padding: 8px; border:2px solid #fcd34d; border-radius:4px; display: block; margin-top:5px;">
        </div>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
            <tr style="background: #eee;">
                <th style="padding: 10px; text-align: left;">Cursus Nummer</th>
                <th style="padding: 10px; text-align: left;">Naam Cursus</th>
                <th style="padding: 10px; text-align: left;">Behaald op</th>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 1</td>
                <td style="padding: 5px;"><input type="text" name="c1_naam" placeholder="Bijv. VCA" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c1_datum" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 2</td>
                <td style="padding: 5px;"><input type="text" name="c2_naam" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c2_datum" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 3</td>
                <td style="padding: 5px;"><input type="text" name="c3_naam" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c3_datum" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 4</td>
                <td style="padding: 5px;"><input type="text" name="c4_naam" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c4_datum" style="width:100%; padding:6px;"></td>
            </tr>
            <tr>
                <td style="padding: 5px;">Cursus 5</td>
                <td style="padding: 5px;"><input type="text" name="c5_naam" style="width:100%; padding:6px;"></td>
                <td style="padding: 5px;"><input type="date" name="c5_datum" style="width:100%; padding:6px;"></td>
            </tr>
        </table>

        <div style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px;">
            <button type="submit" style="background: #28a745; color: white; padding: 12px 25px; font-size: 16px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer;">
                💾 Opslaan
            </button>
            <a href="chauffeurs.php" style="margin-left: 15px; color: #555; text-decoration: none;">Annuleren</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>