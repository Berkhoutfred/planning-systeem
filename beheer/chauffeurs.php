<?php
// Bestand: beheer/chauffeurs.php
// VERSIE: Met archiveren functie én Afwezigheid knop

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

include 'includes/header.php';

// ---> ARCHIVEER LOGICA <---
if (isset($_GET['archive_id'])) {
    $arch_id = (int)$_GET['archive_id'];
    if ($arch_id > 0) {
        $stmt_arch = $pdo->prepare('UPDATE chauffeurs SET archief = 1 WHERE id = ? AND tenant_id = ?');
        $stmt_arch->execute([$arch_id, $tenantId]);
        
        echo "<script>window.location.href='chauffeurs.php';</script>";
        exit;
    }
}
// ----------------------------

// Chauffeurs ophalen (Alleen de actieve chauffeurs: archief = 0)
$stmt = $pdo->prepare('SELECT * FROM chauffeurs WHERE tenant_id = ? AND archief = 0 ORDER BY voornaam ASC');
$stmt->execute([$tenantId]);
$aantal_actief = $stmt->rowCount();
?>

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Chauffeurs</h1>
    <div>
        <a href="chauffeurs_archief.php" style="background:#6c757d; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; margin-right: 10px;">Archief Bekijken</a>
        <a href="chauffeur-toevoegen.php" style="background:#28a745; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;">+ Nieuwe Chauffeur</a>
    </div>
</div>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
            <th style="padding: 12px; text-align: left;">Naam</th>
            <th style="padding: 12px; text-align: left;">Email</th>
            <th style="padding: 12px; text-align: left;">Telefoon</th>
            <th style="padding: 12px; text-align: left;">Rijbewijs Geldig Tot</th> 
            <th style="padding: 12px; text-align: left;">Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($driver = $stmt->fetch()): ?>
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 12px;"><strong><?php echo htmlspecialchars($driver['voornaam'] . ' ' . $driver['achternaam']); ?></strong></td>
                <td style="padding: 12px;"><?php echo htmlspecialchars($driver['email'] ?? ''); ?></td>
                <td style="padding: 12px;"><?php echo htmlspecialchars($driver['telefoon'] ?? ''); ?></td>
                <td style="padding: 12px;">
                    <?php 
                        $datum = $driver['rijbewijs_verloopt'] ?? null;
                        if($datum) {
                            echo date('d-m-Y', strtotime($datum));
                        } else {
                            echo "-";
                        }
                    ?>
                </td>
                <td style="padding: 12px;">
                    <a href="chauffeur_vakantie.php?id=<?php echo $driver['id']; ?>" style="color: #dd6b20; text-decoration: none; font-weight: bold; margin-right: 15px;" title="Beheer Vakanties & Verlof">📅 Vakantie</a>
                    
                    <a href="chauffeur-bewerken.php?id=<?php echo $driver['id']; ?>" style="color: #007bff; text-decoration: none; font-weight: bold; margin-right: 15px;">✏️ Wijzig</a>
                    
                    <a href="?archive_id=<?php echo $driver['id']; ?>" onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($driver['voornaam']); ?> naar het archief wilt verplaatsen?');" style="color: #dc3545; text-decoration: none; font-weight: bold;">🗑️ Archiveer</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php if ($aantal_actief == 0): ?>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-top: 20px; border: 1px solid #dee2e6;">
        <p style="color: #6c757d; margin: 0; font-size: 16px;"><em>Er zijn momenteel geen actieve chauffeurs in het systeem.</em></p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>