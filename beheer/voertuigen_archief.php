<?php
// Bestand: beheer/voertuigen_archief.php
// VERSIE: Het Wagenpark Archief

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// ---> HERSTEL LOGICA <---
// Als iemand op de 'Zet terug actief' knop heeft geklikt
if (isset($_GET['restore_id'])) {
    $rest_id = (int)$_GET['restore_id'];
    if ($rest_id > 0) {
        // We zetten 'archief' weer netjes op 0, zodat de bus weer in de planning verschijnt
        $stmt_rest = $pdo->prepare("UPDATE voertuigen SET archief = 0 WHERE id = ?");
        $stmt_rest->execute([$rest_id]);
        
        // Ververs de pagina
        echo "<script>window.location.href='voertuigen_archief.php';</script>";
        exit;
    }
}
// ----------------------------

// Voertuigen ophalen (Alleen de GEARCHIVEERDE bussen: archief = 1)
$stmt = $pdo->query("SELECT * FROM voertuigen WHERE archief = 1 ORDER BY naam ASC");
$aantal_archief = $stmt->rowCount();
?>

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1 style="color: #6c757d;">🗄️ Wagenpark Archief</h1>
    <a href="voertuigen.php" style="background:#007bff; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;">⬅️ Terug naar Actief</a>
</div>

<p style="color: #666; font-size: 14px; margin-bottom: 20px;">
    Hier zie je alle bussen die verkocht, afgeschreven of langdurig uit de running zijn. Ze zijn verborgen in de planning, maar het onderhoudsverleden en de gereden ritten blijven hier veilig bewaard.
</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px; opacity: 0.9;">
    <thead>
        <tr style="background-color: #e9ecef; border-bottom: 2px solid #ced4da;">
            <th style="padding: 12px; text-align: left; color: #495057;">Nr. & Naam</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Kenteken & Type</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Laatste Status</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($bus = $stmt->fetch()): ?>
            <tr style="border-bottom: 1px solid #ced4da; background: #f8f9fa;">
                <td style="padding: 12px; color: #6c757d;">
                    <strong><?php echo htmlspecialchars($bus['voertuig_nummer'] ?? ''); ?> <?php echo htmlspecialchars($bus['naam']); ?></strong>
                </td>
                <td style="padding: 12px; color: #6c757d;">
                    <span style="background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #cbd5e1; font-family: monospace;">
                        <?php echo htmlspecialchars($bus['kenteken']); ?>
                    </span><br>
                    <span style="font-size: 12px;"><?php echo htmlspecialchars($bus['type'] ?? 'Onbekend type'); ?></span>
                </td>
                <td style="padding: 12px; color: #6c757d;">
                    <em><?php echo htmlspecialchars(ucfirst($bus['status'] ?? 'Onbekend')); ?></em>
                </td>
                <td style="padding: 12px;">
                    <a href="?restore_id=<?php echo $bus['id']; ?>" onclick="return confirm('Weet je zeker dat je bus <?php echo htmlspecialchars($bus['naam']); ?> weer ACTIEF wilt maken?');" style="color: #28a745; text-decoration: none; font-weight: bold; background: #d4edda; padding: 6px 12px; border-radius: 4px; border: 1px solid #c3e6cb;">
                        ♻️ Zet terug actief
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php if ($aantal_archief == 0): ?>
    <div style="background: #e9ecef; padding: 20px; text-align: center; border-radius: 8px; margin-top: 20px; border: 1px solid #ced4da;">
        <p style="color: #6c757d; margin: 0; font-size: 16px;"><em>Er staan momenteel geen bussen in het archief.</em></p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>