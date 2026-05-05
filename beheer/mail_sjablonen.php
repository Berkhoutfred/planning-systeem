<?php
// Bestand: beheer/sjablonen.php
include '../beveiliging.php';
require 'includes/db.php';

// --- ACTIES AFHANDELEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['actie']) && $_POST['actie'] == 'toevoegen') {
        $stmt = $pdo->prepare("INSERT INTO mail_sjablonen (titel, onderwerp, bericht) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['titel'], $_POST['onderwerp'], $_POST['bericht']]);
        $msg = "Nieuw sjabloon toegevoegd!";
    } elseif (isset($_POST['actie']) && $_POST['actie'] == 'opslaan') {
        $stmt = $pdo->prepare("UPDATE mail_sjablonen SET titel=?, onderwerp=?, bericht=? WHERE id=?");
        $stmt->execute([$_POST['titel'], $_POST['onderwerp'], $_POST['bericht'], $_POST['id']]);
        $msg = "Sjabloon succesvol gewijzigd!";
    } elseif (isset($_POST['actie']) && $_POST['actie'] == 'verwijderen') {
        $stmt = $pdo->prepare("DELETE FROM mail_sjablonen WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Sjabloon verwijderd!";
    }
}

// Haal alle sjablonen op
$sjablonen = $pdo->query("SELECT * FROM mail_sjablonen ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1000px; margin: auto; padding: 20px; }
    .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 15px; font-family: inherit; }
    textarea.form-control { min-height: 150px; resize: vertical; }
    .btn-save { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn-delete { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; float: right; }
    .btn-add { background: #003366; color: white; padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .alert { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: bold; }
</style>

<div class="container">
    <h1 style="color:#003366;"><i class="fas fa-file-alt"></i> E-mail Sjablonen Beheer</h1>
    
    <?php if(isset($msg)) echo "<div class='alert'>✅ $msg</div>"; ?>

    <div class="card" style="border-left: 4px solid #003366;">
        <h3 style="margin-top:0;">+ Nieuw Sjabloon Maken</h3>
        <form method="POST">
            <input type="hidden" name="actie" value="toevoegen">
            <label>Titel (voor in je keuzemenu, bijv. 'Schoolreisjes'):</label>
            <input type="text" name="titel" class="form-control" required>
            
            <label>Onderwerp van de e-mail:</label>
            <input type="text" name="onderwerp" class="form-control" required>
            
            <label>Standaard tekstbericht:</label>
            <textarea name="bericht" class="form-control" required></textarea>
            
            <button type="submit" class="btn-add">Toevoegen</button>
        </form>
    </div>

    <hr style="border: 0; border-top: 2px solid #ddd; margin: 30px 0;">

    <h3 style="color:#003366;">Bestaande Sjablonen Wijzigen</h3>
    
    <?php foreach($sjablonen as $s): ?>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                
                <label>Titel (keuzemenu):</label>
                <input type="text" name="titel" class="form-control" value="<?= htmlspecialchars($s['titel']) ?>" required>
                
                <label>Onderwerp:</label>
                <input type="text" name="onderwerp" class="form-control" value="<?= htmlspecialchars($s['onderwerp']) ?>" required>
                
                <label>Bericht:</label>
                <textarea name="bericht" class="form-control" required><?= htmlspecialchars($s['bericht']) ?></textarea>
                
                <button type="submit" name="actie" value="opslaan" class="btn-save">💾 Wijzigingen Opslaan</button>
                <button type="submit" name="actie" value="verwijderen" class="btn-delete" onclick="return confirm('Weet je zeker dat je dit sjabloon wilt weggooien?');">🗑️ Verwijder</button>
            </form>
        </div>
    <?php endforeach; ?>

</div>

<?php include 'includes/footer.php'; ?>