<?php
// Bestand: beheer/vaste_ritten/overzicht.php
// VERSIE: Vaste ritten overzicht inclusief Uitrol-knop

include '../../beveiliging.php';
require '../includes/db.php';
include '../includes/header.php';

// --- WIS / ARCHIVEER LOGICA ---
if (isset($_GET['wis_id'])) {
    $wis_id = (int)$_GET['wis_id'];
    if ($wis_id > 0) {
        $stmt_wis = $pdo->prepare("UPDATE vaste_ritten SET actief = 0 WHERE id = ?");
        $stmt_wis->execute([$wis_id]);
        
        echo "<script>window.location.href='overzicht.php';</script>";
        exit;
    }
}

// Haal alle actieve sjablonen op uit de database
$stmt = $pdo->query("SELECT * FROM vaste_ritten WHERE actief = 1 ORDER BY startdatum ASC");
$sjablonen = $stmt->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
    <div>
        <h1 style="margin-bottom: 5px; color: #003366;"><i class="fas fa-route"></i> Vaste Ritten / Contractvervoer</h1>
        <p style="color: #666; margin-top: 0;">Beheer hier de sjablonen voor ritten die wekelijks terugkeren (bijv. Dagbesteding).</p>
    </div>
    <div>
        <a href="toevoegen.php" style="background:#28a745; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; font-weight: bold;">+ Nieuw Sjabloon</a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'uitgerold'): ?>
    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; border: 1px solid #c3e6cb;">
        ✅ Succes! De motor is klaar. Er zijn in totaal <?php echo (int)$_GET['aantal']; ?> ritten gegenereerd en in het planbord gezet!
    </div>
<?php endif; ?>

<div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                <th style="padding: 12px; text-align: left;">Sjabloon Naam</th>
                <th style="padding: 12px; text-align: left;">Periode</th>
                <th style="padding: 12px; text-align: left;">Tijden</th>
                <th style="padding: 12px; text-align: left;">Dagen</th>
                <th style="padding: 12px; text-align: left;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($sjablonen) > 0): ?>
                <?php foreach ($sjablonen as $rit): ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 12px;"><strong><?php echo htmlspecialchars($rit['naam']); ?></strong></td>
                        <td style="padding: 12px;">
                            <?php echo date('d-m-Y', strtotime($rit['startdatum'])) . ' t/m ' . date('d-m-Y', strtotime($rit['einddatum'])); ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo date('H:i', strtotime($rit['vertrektijd'])) . ' - ' . date('H:i', strtotime($rit['aankomsttijd'])); ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php 
                                $dagen = [];
                                if($rit['rijdt_ma']) $dagen[] = 'Ma';
                                if($rit['rijdt_di']) $dagen[] = 'Di';
                                if($rit['rijdt_wo']) $dagen[] = 'Wo';
                                if($rit['rijdt_do']) $dagen[] = 'Do';
                                if($rit['rijdt_vr']) $dagen[] = 'Vr';
                                if($rit['rijdt_za']) $dagen[] = 'Za';
                                if($rit['rijdt_zo']) $dagen[] = 'Zo';
                                echo implode(', ', $dagen);
                            ?>
                        </td>
                        <td style="padding: 12px;">
                            <a href="uitrollen.php?id=<?php echo $rit['id']; ?>" onclick="return confirm('Weet je zeker dat je de ritten voor \'<?php echo htmlspecialchars(addslashes($rit['naam'])); ?>\' wilt genereren en in het planbord zetten?');" style="color: #28a745; text-decoration: none; font-weight: bold; margin-right: 15px;">🚀 Uitrollen</a>
                            
                            <a href="wijzigen.php?id=<?php echo $rit['id']; ?>" style="color: #007bff; text-decoration: none; font-weight: bold; margin-right: 15px;">✏️ Wijzig</a>
                            
                            <a href="?wis_id=<?php echo $rit['id']; ?>" onclick="return confirm('Weet je zeker dat je het sjabloon \'<?php echo htmlspecialchars(addslashes($rit['naam'])); ?>\' wilt verwijderen?');" style="color: #dc3545; text-decoration: none; font-weight: bold;">🗑️ Wis</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="padding: 30px; text-align: center; color: #666; font-style: italic;">
                        Er zijn nog geen sjablonen voor vaste ritten aangemaakt.<br>
                        Klik rechtsboven op de groene knop om te beginnen.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>