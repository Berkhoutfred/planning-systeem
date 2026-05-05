<?php
// Bestand: beheer/voertuigen.php
// VERSIE: Uitgebreid Wagenpark Overzicht (Met archiveren)

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
        $stmt_arch = $pdo->prepare('UPDATE voertuigen SET archief = 1 WHERE id = ? AND tenant_id = ?');
        $stmt_arch->execute([$arch_id, $tenantId]);
        
        echo "<script>window.location.href='voertuigen.php';</script>";
        exit;
    }
}
// ----------------------------

// Voertuigen ophalen (Alleen actieve: archief = 0)
$stmt = $pdo->prepare('SELECT * FROM voertuigen WHERE tenant_id = ? AND archief = 0 ORDER BY naam ASC');
$stmt->execute([$tenantId]);
$aantal_actief = $stmt->rowCount();
?>

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>🚌 Wagenpark</h1>
    <div>
        <a href="voertuigen_archief.php" style="background:#6c757d; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; margin-right: 10px;">Archief Bekijken</a>
        <a href="voertuig-toevoegen.php" style="background:#28a745; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;">+ Nieuw Voertuig</a>
    </div>
</div>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
            <th style="padding: 12px; text-align: left;">Nr. & Naam</th>
            <th style="padding: 12px; text-align: left;">Kenteken & Type</th>
            <th style="padding: 12px; text-align: left;">Capaciteit</th>
            <th style="padding: 12px; text-align: left;">APK Tot</th>
            <th style="padding: 12px; text-align: left;">Status</th>
            <th style="padding: 12px; text-align: left;">Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($bus = $stmt->fetch()): ?>
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 12px;">
                    <strong><?php echo htmlspecialchars($bus['voertuig_nummer'] ?? ''); ?> <?php echo htmlspecialchars($bus['naam']); ?></strong>
                </td>
                <td style="padding: 12px;">
                    <span style="background: #fbbf24; color: #000; padding: 2px 6px; border-radius: 4px; font-weight: bold; border: 1px solid #d97706; font-family: monospace;">
                        <?php echo htmlspecialchars($bus['kenteken']); ?>
                    </span><br>
                    <span style="font-size: 12px; color: #6c757d;"><?php echo htmlspecialchars($bus['type'] ?? ''); ?></span>
                </td>
                <td style="padding: 12px;"><?php echo htmlspecialchars($bus['zitplaatsen']); ?> pax</td>
                <td style="padding: 12px;">
                    <?php 
                        $apk = $bus['apk_datum'] ?? null;
                        if($apk) {
                            echo date('d-m-Y', strtotime($apk));
                        } else {
                            echo "<span style='color:#ccc;'>Onbekend</span>";
                        }
                    ?>
                </td>
                <td style="padding: 12px;">
                    <?php 
                    $status = strtolower($bus['status'] ?? 'beschikbaar');
                    $kleur = '#28a745'; // Groen voor beschikbaar
                    $bg = '#d4edda';
                    $icoon = '🟢';
                    
                    if($status == 'werkplaats' || $status == 'stuk' || $status == 'onderhoud') {
                        $kleur = '#dc3545'; // Rood voor defect/werkplaats
                        $bg = '#f8d7da';
                        $icoon = '🔴';
                        $status = 'Werkplaats'; // Uniforme weergave
                    }
                    ?>
                    <span style="color:<?php echo $kleur; ?>; background: <?php echo $bg; ?>; padding: 4px 8px; border-radius: 4px; font-weight:bold; font-size: 12px;">
                        <?php echo $icoon . ' ' . htmlspecialchars(ucfirst($status)); ?>
                    </span>
                </td>
                <td style="padding: 12px;">
                    <a href="voertuig-bewerken.php?id=<?php echo $bus['id']; ?>" style="color: #007bff; text-decoration: none; font-weight: bold; margin-right: 15px;">✏️ Wijzig</a>
                    
                    <a href="?archive_id=<?php echo $bus['id']; ?>" onclick="return confirm('Weet je zeker dat je bus <?php echo htmlspecialchars($bus['naam']); ?> naar het archief wilt verplaatsen?');" style="color: #dc3545; text-decoration: none; font-weight: bold;">🗑️ Archiveer</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php if ($aantal_actief == 0): ?>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-top: 20px; border: 1px solid #dee2e6;">
        <p style="color: #6c757d; margin: 0; font-size: 16px;"><em>Je actieve garage is nog leeg. Voeg je eerste bus toe!</em></p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>