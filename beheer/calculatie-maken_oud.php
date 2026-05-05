<?php
include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// 1. We halen eerst alle klanten op voor het keuzemenu
$stmt = $pdo->query("SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten ORDER BY bedrijfsnaam ASC, achternaam ASC");
$klanten = $stmt->fetchAll();

// 2. Als het formulier is ingevuld (OPSLAAN)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Even de komma in de prijs vervangen door een punt (voor de database)
        $prijs = str_replace(',', '.', $_POST['prijs']);

        $sql = "INSERT INTO calculaties (klant_id, rit_datum, vertrek_adres, aankomst_adres, aantal_personen, afstand_km, prijs, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'concept')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['klant_id'],
            $_POST['datum'],
            $_POST['van'],
            $_POST['naar'],
            $_POST['personen'],
            $_POST['km'],
            $prijs
        ]);

        echo "<script>window.location.href='calculaties.php';</script>";
        exit;

    } catch (PDOException $e) {
        $foutmelding = "Er ging iets mis: " . $e->getMessage();
    }
}
?>

<div style="max-width: 600px; margin: auto;">
    <h2>Nieuwe Calculatie Maken</h2>

    <?php if (isset($foutmelding)): ?>
        <div style="background: #ffcccc; color: #cc0000; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $foutmelding; ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        
        <div style="margin-bottom: 15px;">
            <label><strong>Klant:</strong></label><br>
            <select name="klant_id" required style="width: 100%; padding: 8px;">
                <option value="">-- Kies een klant --</option>
                <?php foreach ($klanten as $klant): ?>
                    <option value="<?php echo $klant['id']; ?>">
                        <?php 
                            if(!empty($klant['bedrijfsnaam'])) {
                                echo htmlspecialchars($klant['bedrijfsnaam'] . ' (' . $klant['voornaam'] . ')');
                            } else {
                                echo htmlspecialchars($klant['voornaam'] . ' ' . $klant['achternaam']);
                            }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><small><a href="klant-toevoegen.php">Of maak eerst een nieuwe klant aan</a></small>
        </div>

        <div style="margin-bottom: 15px;">
            <label>Datum Rit:</label><br>
            <input type="date" name="datum" required style="width: 100%; padding: 8px;">
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label>Vertrek (Van):</label><br>
                <input type="text" name="van" required placeholder="Bijv. Amsterdam" style="width: 100%; padding: 8px;">
            </div>
            <div style="flex: 1;">
                <label>Bestemming (Naar):</label><br>
                <input type="text" name="naar" required placeholder="Bijv. Parijs" style="width: 100%; padding: 8px;">
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label>Aantal Personen:</label><br>
                <input type="number" name="personen" style="width: 100%; padding: 8px;">
            </div>
            <div style="flex: 1;">
                <label>Afstand (km):</label><br>
                <input type="number" name="km" placeholder="Schatting" style="width: 100%; padding: 8px;">
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label><strong>Berekende Prijs (€):</strong></label><br>
            <input type="text" name="prijs" placeholder="0,00" required style="width: 100%; padding: 8px; font-size: 18px; font-weight: bold;">
        </div>

        <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            Opslaan in overzicht
        </button>
        <a href="calculaties.php" style="margin-left: 10px;">Annuleren</a>

    </form>
</div>

<?php include 'includes/footer.php'; ?>