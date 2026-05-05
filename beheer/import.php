<?php
// Bestand: beheer/import.php
include '../beveiliging.php';
require 'includes/db.php';

// De exacte naam van jouw geüploade bestand
$bestand = 'Masterlijst_ERP_Hostinger_Updated.csv';

echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
echo "<h1>Klanten Import</h1>";

if (!file_exists($bestand)) {
    die("<p style='color:red;'>Fout: Het bestand '$bestand' is niet gevonden in de beheer map. Upload het eerst via Hostinger.</p></div>");
}

if (($handle = fopen($bestand, "r")) !== FALSE) {
    // Lees de allereerste rij (de koppen) en sla deze over, want die hoeven niet in de database
    fgetcsv($handle, 1000, ";");

    $succes = 0;
    $fouten = 0;

    // We bereiden de database voor: dit is de 'brug' die we zojuist hebben besproken
    $stmt = $pdo->prepare("INSERT INTO klanten (klantnummer, bedrijfsnaam, voornaam, adres, postcode, plaats, email) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Lees de rest van het bestand regel voor regel uit
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        
        // Koppel de CSV kolommen aan variabelen
        $debiteurnummer = !empty($data[0]) ? $data[0] : null;
        $bedrijfsnaam   = !empty($data[1]) ? $data[1] : '';
        $contactpersoon = !empty($data[2]) ? $data[2] : ''; // Gaat straks in de 'voornaam' kolom
        $adres          = !empty($data[3]) ? $data[3] : '';
        $postcode       = !empty($data[4]) ? $data[4] : '';
        $plaats         = !empty($data[5]) ? $data[5] : '';
        $email          = !empty($data[6]) ? $data[6] : '';

        // Beveiliging: Sla rijen over die compleet leeg zijn (zonder bedrijf én zonder naam)
        if (empty($bedrijfsnaam) && empty($contactpersoon)) {
            continue;
        }

        // Voer de data in de database in
        try {
            $stmt->execute([$debiteurnummer, $bedrijfsnaam, $contactpersoon, $adres, $postcode, $plaats, $email]);
            $succes++;
        } catch (PDOException $e) {
            $fouten++;
            // Zet hier een // voor als je de specifieke technische database-fouten niet hoeft te zien
            echo "<p style='color:red; font-size: 12px;'>Fout bij klant: $bedrijfsnaam - " . $e->getMessage() . "</p>";
        }
    }
    fclose($handle);
    
    // Het eindrapport op je scherm
    echo "<h2 style='color:green;'>Import Voltooid! 🎉</h2>";
    echo "<p>Aantal klanten succesvol toegevoegd: <strong>$succes</strong></p>";
    if ($fouten > 0) {
        echo "<p style='color:red;'>Aantal overgeslagen (bijv. door dubbele gegevens): <strong>$fouten</strong></p>";
    }
    echo "<br><a href='klanten.php' style='background:#007bff; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Ga naar het Klanten Overzicht</a>";
    echo "</div>";

} else {
    echo "<p style='color:red;'>Kan het CSV bestand niet openen.</p></div>";
}
?>