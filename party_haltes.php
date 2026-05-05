<?php
// party_haltes.php
// VERSIE: FLEXIBEL (MET OF ZONDER EVENT_ID) + WWW FIX

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

// Zorg dat de halte-bibliotheek lokaal altijd bestaat.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS party_haltes_bibliotheek (
        id INT AUTO_INCREMENT PRIMARY KEY,
        naam VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Kijk of we voor een specifiek event bezig zijn
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$event = null;

if ($event_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM party_events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
}

// --- ACTIES VERWERKEN ---

// 1. HALTE KOPPELEN AAN EVENT
if (isset($_POST['koppel_halte']) && $event_id > 0) {
    $naam  = $_POST['gekozen_halte_naam'];
    $prijs = str_replace(',', '.', $_POST['prijs']);
    $tijd  = $_POST['tijd'];

    if (!empty($naam)) {
        $stmt = $pdo->prepare("INSERT INTO party_opstap_locaties (event_id, naam, prijs, tijd) VALUES (?, ?, ?, ?)");
        $stmt->execute([$event_id, $naam, $prijs, $tijd]);
    }
    header("Location: party_haltes.php?event_id=" . $event_id);
    exit;
}

// 2. HALTE ONTKOPPELEN VAN EVENT
if (isset($_GET['delete_opstap'])) {
    $opstap_id = (int)$_GET['delete_opstap'];
    $pdo->prepare("DELETE FROM party_opstap_locaties WHERE id = ?")->execute([$opstap_id]);
    header("Location: party_haltes.php?event_id=" . $event_id);
    exit;
}

// 3. NIEUWE HALTE IN BIBLIOTHEEK MAKEN
if (isset($_POST['nieuwe_bib_halte'])) {
    $naam = trim($_POST['nieuwe_naam']);
    if (!empty($naam)) {
        $stmt = $pdo->prepare("INSERT INTO party_haltes_bibliotheek (naam) VALUES (?)");
        $stmt->execute([$naam]);
    }
    $link = ($event_id > 0) ? "?event_id=$event_id" : "";
    header("Location: party_haltes.php" . $link);
    exit;
}

// 4. HALTE UIT BIBLIOTHEEK VERWIJDEREN
if (isset($_GET['delete_bib'])) {
    $id = (int)$_GET['delete_bib'];
    $pdo->prepare("DELETE FROM party_haltes_bibliotheek WHERE id = ?")->execute([$id]);
    $link = ($event_id > 0) ? "?event_id=$event_id" : "";
    header("Location: party_haltes.php" . $link);
    exit;
}

// --- DATA OPHALEN ---
$gekoppelde_haltes = [];
if ($event_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM party_opstap_locaties WHERE event_id = ? ORDER BY tijd ASC");
    $stmt->execute([$event_id]);
    $gekoppelde_haltes = $stmt->fetchAll();
}

$bibliotheek = $pdo->query("SELECT * FROM party_haltes_bibliotheek ORDER BY naam ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Haltes Beheer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { font-size: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; color: #1f2937; }
        .btn-back { display: inline-block; padding: 8px 15px; background: #4b5563; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        .box { background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 5px; box-sizing: border-box; }
        .btn-add { background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-del { color: #dc2626; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="party_beheer.php" class="btn-back">⬅️ Terug naar Dashboard</a>

    <?php if ($event): ?>
        <h1>📍 Haltes voor: <?php echo htmlspecialchars($event['naam']); ?></h1>
        
        <h3>Gekoppelde haltes</h3>
        <table>
            <thead><tr><th>Halte</th><th>Tijd</th><th>Prijs</th><th></th></tr></thead>
            <tbody>
                <?php foreach($gekoppelde_haltes as $gh): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($gh['naam']); ?></strong></td>
                    <td><?php echo htmlspecialchars($gh['tijd']); ?></td>
                    <td>€ <?php echo number_format($gh['prijs'], 2, ',', '.'); ?></td>
                    <td><a href="?event_id=<?php echo $event_id; ?>&delete_opstap=<?php echo $gh['id']; ?>" class="btn-del">🗑️</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="box">
            <h4>Halte uit lijst toevoegen</h4>
            <form method="post" action="party_haltes.php?event_id=<?php echo $event_id; ?>">
                <input type="hidden" name="koppel_halte" value="1">
                <div style="display:flex; gap:10px;">
                    <select name="gekozen_halte_naam" required>
                        <?php foreach($bibliotheek as $b): ?>
                            <option value="<?php echo htmlspecialchars($b['naam']); ?>"><?php echo htmlspecialchars($b['naam']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="time" name="tijd" required>
                    <input type="text" name="prijs" placeholder="10.00" style="width:80px;">
                    <button type="submit" class="btn-add">OK</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <h1>📍 Haltes Bibliotheek</h1>
    <?php endif; ?>

    <div style="margin-top:40px; border-top: 2px solid #eee; padding-top:20px;">
        <h3>Beheer alle locaties (Bibliotheek)</h3>
        <table>
            <?php foreach($bibliotheek as $b): ?>
            <tr>
                <td><?php echo htmlspecialchars($b['naam']); ?></td>
                <td style="text-align:right;">
                    <a href="?<?php echo $event_id ? "event_id=$event_id&" : ""; ?>delete_bib=<?php echo $b['id']; ?>" class="btn-del" onclick="return confirm('Helemaal verwijderen uit bibliotheek?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <form method="post" action="party_haltes.php?<?php echo $event_id ? "event_id=$event_id" : ""; ?>">
            <input type="hidden" name="nieuwe_bib_halte" value="1">
            <div style="display:flex; gap:10px;">
                <input type="text" name="nieuwe_naam" placeholder="Nieuwe halte naam..." required>
                <button type="submit" class="btn-add" style="background:#059669;">Nieuwe Halte Aanmaken</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>