<?php
// Bestand: beheer/chauffeur_vakantie.php
// Doel: Afwezigheid (vakantie/ziek) per chauffeur beheren

include '../beveiliging.php';
require 'includes/db.php';

// Controleer of er een ID is meegegeven
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<div style='padding:20px; font-family:sans-serif;'>Geen chauffeur geselecteerd. <a href='chauffeurs.php'>Ga terug naar overzicht</a></div>");
}

$chauffeur_id = (int)$_GET['id'];

// Haal de gegevens van deze specifieke chauffeur op
$stmt = $pdo->prepare("SELECT * FROM chauffeurs WHERE id = ?");
$stmt->execute([$chauffeur_id]);
$chauffeur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chauffeur) {
    die("<div style='padding:20px; font-family:sans-serif;'>Chauffeur niet gevonden in de database.</div>");
}

// ==========================================
// FORMULIER VERWERKEN (Toevoegen)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'toevoegen') {
    $startdatum = $_POST['startdatum'];
    $einddatum = $_POST['einddatum'];
    $type = $_POST['type'];
    $opmerking = trim($_POST['opmerking']);
    
    if (!empty($startdatum) && !empty($einddatum)) {
        $stmt_insert = $pdo->prepare("INSERT INTO afwezigheid (chauffeur_id, startdatum, einddatum, type, opmerking, status) VALUES (?, ?, ?, ?, ?, 'Bevestigd')");
        $stmt_insert->execute([$chauffeur_id, $startdatum, $einddatum, $type, $opmerking]);
        
        header("Location: chauffeur_vakantie.php?id=" . $chauffeur_id . "&msg=toegevoegd");
        exit;
    }
}

// ==========================================
// ITEM VERWIJDEREN
// ==========================================
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt_del = $pdo->prepare("DELETE FROM afwezigheid WHERE id = ? AND chauffeur_id = ?");
    $stmt_del->execute([$del_id, $chauffeur_id]);
    
    header("Location: chauffeur_vakantie.php?id=" . $chauffeur_id . "&msg=verwijderd");
    exit;
}

// Haal alle afwezigheden van deze chauffeur op, nieuwste eerst
$stmt_afw = $pdo->prepare("SELECT * FROM afwezigheid WHERE chauffeur_id = ? ORDER BY startdatum DESC");
$stmt_afw->execute([$chauffeur_id]);
$afwezigheden = $stmt_afw->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
    .container-afwezigheid { display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap; }
    .form-box { flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-width: 300px; border-top: 4px solid #003366; }
    .lijst-box { flex: 2; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-width: 400px; border-top: 4px solid #dd6b20; }
    
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 14px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
    
    .btn-submit { background: #003366; color: #fff; border: none; padding: 10px 15px; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; font-size: 15px; }
    .btn-submit:hover { background: #002244; }
    
    .afw-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .afw-table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; }
    .afw-table td { padding: 10px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
    
    .badge-type { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; color: #fff; }
    .type-Vakantie { background: #28a745; }
    .type-Ziek { background: #dc3545; }
    .type-Verlof { background: #17a2b8; }
    .type-Overig { background: #6c757d; }
    
    .msg { padding: 10px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; }
    .msg-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
</style>

<div style="margin-bottom: 20px;">
    <a href="chauffeurs.php" style="color: #555; text-decoration: none; font-weight: bold;">&laquo; Terug naar Chauffeurs</a>
    <h1 style="color: #003366; margin-top: 10px;">
        Afwezigheid: <?php echo htmlspecialchars($chauffeur['voornaam'] . ' ' . $chauffeur['achternaam']); ?>
    </h1>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] == 'toegevoegd'): ?>
        <div class="msg msg-success">✅ Periode succesvol toegevoegd aan het rooster.</div>
    <?php elseif ($_GET['msg'] == 'verwijderd'): ?>
        <div class="msg msg-success">🗑️ Periode is succesvol verwijderd.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="container-afwezigheid">
    
    <div class="form-box">
        <h3 style="margin-top: 0; color: #003366; border-bottom: 1px solid #eee; padding-bottom: 10px;">Nieuwe Periode Invoeren</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="actie" value="toevoegen">
            
            <div class="form-group">
                <label>Type Afwezigheid</label>
                <select name="type" class="form-control" required>
                    <option value="Vakantie">Vakantie</option>
                    <option value="Ziek">Ziek</option>
                    <option value="Verlof">Bijzonder Verlof</option>
                    <option value="Overig">Overig</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Startdatum</label>
                <input type="date" name="startdatum" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Einddatum (T/M)</label>
                <input type="date" name="einddatum" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Korte Opmerking (Optioneel)</label>
                <input type="text" name="opmerking" class="form-control" placeholder="Bijv. Ibiza of Doktersbezoek">
            </div>
            
            <button type="submit" class="btn-submit">+ Toevoegen aan Rooster</button>
        </form>
    </div>

    <div class="lijst-box">
        <h3 style="margin-top: 0; color: #dd6b20; border-bottom: 1px solid #eee; padding-bottom: 10px;">Overzicht Afwezigheid</h3>
        
        <?php if (count($afwezigheden) == 0): ?>
            <p style="color: #666; font-style: italic;">Er zijn nog geen vakanties of afwezigheden geregistreerd voor deze chauffeur.</p>
        <?php else: ?>
            <table class="afw-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Van</th>
                        <th>T/M</th>
                        <th>Opmerking</th>
                        <th style="text-align: right;">Actie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($afwezigheden as $afw): ?>
                        <tr>
                            <td><span class="badge-type type-<?php echo $afw['type']; ?>"><?php echo $afw['type']; ?></span></td>
                            <td><strong><?php echo date('d-m-Y', strtotime($afw['startdatum'])); ?></strong></td>
                            <td><strong><?php echo date('d-m-Y', strtotime($afw['einddatum'])); ?></strong></td>
                            <td style="color: #555;"><?php echo htmlspecialchars($afw['opmerking']); ?></td>
                            <td style="text-align: right;">
                                <a href="?id=<?php echo $chauffeur_id; ?>&delete_id=<?php echo $afw['id']; ?>" onclick="return confirm('Weet je zeker dat je deze afwezigheid wilt verwijderen?');" style="color: #dc3545; text-decoration: none;" title="Verwijderen">
                                    <i class="fas fa-trash"></i> Verwijder
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>