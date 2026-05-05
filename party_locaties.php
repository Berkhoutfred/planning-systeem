<?php
// party_locaties.php
// VERSIE: 1.0 - Beheer van vaste feestlocaties

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

// 1. LOCATIE TOEVOEGEN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actie']) && $_POST['actie'] == 'toevoegen') {
    $naam = trim($_POST['naam']);
    $adres = trim($_POST['adres']);
    $maps_link = trim($_POST['maps_link']);
    
    $stmt = $pdo->prepare("INSERT INTO party_locaties (naam, adres, maps_link) VALUES (?, ?, ?)");
    $stmt->execute([$naam, $adres, $maps_link]);
    
    // Voorkom dubbele invoer bij F5 / vernieuwen
    header("Location: party_locaties.php");
    exit;
}

// 2. LOCATIE VERWIJDEREN
if (isset($_GET['verwijder_id'])) {
    $del_id = (int)$_GET['verwijder_id'];
    $stmt = $pdo->prepare("DELETE FROM party_locaties WHERE id = ?");
    $stmt->execute([$del_id]);
    header("Location: party_locaties.php");
    exit;
}

// 3. ALLE LOCATIES OPHALEN
$locaties = $pdo->query("SELECT * FROM party_locaties ORDER BY naam ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Locaties Beheren</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #111; border-bottom: 2px solid #eee; padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        
        .btn-terug { background: #64748b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .btn-terug:hover { background: #475569; }

        /* Formulier Stijl */
        .form-box { background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 30px; }
        label { display: block; font-weight: bold; margin-top: 10px; color: #444; font-size: 14px; }
        input[type="text"], input[type="url"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; }
        .btn-opslaan { background: #22c55e; color: white; padding: 10px 20px; border: none; margin-top: 15px; font-size: 14px; font-weight: bold; cursor: pointer; border-radius: 5px; }
        .btn-opslaan:hover { background: #16a34a; }

        /* Tabel Stijl */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        th { background: #f1f5f9; font-size: 14px; color: #475569; }
        .btn-verwijder { color: #ef4444; text-decoration: none; font-weight: bold; }
        .btn-verwijder:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>
        📍 Feestlocaties Beheren
        <a href="party_beheer.php" class="btn-terug">⬅ Terug naar Beheer</a>
    </h2>

    <div class="form-box">
        <h3>Nieuwe locatie toevoegen</h3>
        <form method="post">
            <input type="hidden" name="actie" value="toevoegen">
            
            <label>Naam Locatie (bijv. De Radstake)</label>
            <input type="text" name="naam" required placeholder="Naam van de locatie">

            <label>Volledig Adres (voor op het ticket)</label>
            <input type="text" name="adres" placeholder="Twente-Route 43, 7055 BE Heelweg">

            <label>Google Maps Link (optioneel)</label>
            <input type="url" name="maps_link" placeholder="https://maps.app.goo.gl/...">

            <button type="submit" class="btn-opslaan">➕ Locatie Opslaan</button>
        </form>
    </div>

    <h3>Opgeslagen Locaties</h3>
    <table>
        <tr>
            <th>Naam</th>
            <th>Adres</th>
            <th>Acties</th>
        </tr>
        <?php if (count($locaties) > 0): ?>
            <?php foreach($locaties as $rij): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($rij['naam']); ?></strong></td>
                <td>
                    <?php echo htmlspecialchars($rij['adres']); ?><br>
                    <?php if (!empty($rij['maps_link'])): ?>
                        <a href="<?php echo htmlspecialchars($rij['maps_link']); ?>" target="_blank" style="font-size: 12px; color: #3b82f6;">Bekijk op Maps</a>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="party_locaties.php?verwijder_id=<?php echo $rij['id']; ?>" class="btn-verwijder" onclick="return confirm('Weet je zeker dat je deze locatie wilt verwijderen?');">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" style="text-align: center; color: #94a3b8; font-style: italic;">Er zijn nog geen locaties toegevoegd.</td>
            </tr>
        <?php endif; ?>
    </table>

</div>

</body>
</html>