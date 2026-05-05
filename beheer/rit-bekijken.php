<?php
include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

if (!isset($_GET['id'])) { echo "Geen ID."; exit; }
$id = $_GET['id'];

// --- ACTIE: VERWIJDEREN ---
if (isset($_POST['verwijder_rit'])) {
    // Eerst de regels (kinderen) weg, dan de rit (moeder)
    $pdo->prepare("DELETE FROM ritregels WHERE rit_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM ritgegevens WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href='ritten.php';</script>";
    exit;
}

// --- ACTIE: GOEDKEUREN (STATUS) ---
if (isset($_POST['markeer_verwerkt'])) {
    $pdo->prepare("UPDATE ritgegevens SET status = 'verwerkt' WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href='rit-bekijken.php?id=$id';</script>";
    exit;
}

// --- ACTIE: HEROPENEN (TERUG NAAR NIEUW) ---
if (isset($_POST['markeer_nieuw'])) {
    $pdo->prepare("UPDATE ritgegevens SET status = 'nieuw' WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href='rit-bekijken.php?id=$id';</script>";
    exit;
}

// DATA OPHALEN
$stmt = $pdo->prepare("SELECT * FROM ritgegevens WHERE id = ?");
$stmt->execute([$id]);
$rit = $stmt->fetch();

$stmtRegels = $pdo->prepare("SELECT * FROM ritregels WHERE rit_id = ? ORDER BY id ASC");
$stmtRegels->execute([$id]);
$regels = $stmtRegels->fetchAll();
?>

<div style="max-width: 800px; margin: auto;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <a href="ritten.php" style="text-decoration:none; color:#666;">❮ Terug naar overzicht</a>
        
        <div style="display:flex; gap:10px;">
            <a href="rit-bewerken.php?id=<?php echo $id; ?>" style="background:#ffc107; color:black; padding:10px 15px; text-decoration:none; border-radius:5px; font-weight:bold;">
                ✏️ Aanpassen
            </a>

            <form method="POST" style="margin:0;">
                <?php if($rit['status'] == 'nieuw'): ?>
                    <button type="submit" name="markeer_verwerkt" style="background:#28a745; color:white; border:none; padding:10px 15px; border-radius:5px; cursor:pointer; font-weight:bold;">
                        ✅ Goedkeuren
                    </button>
                <?php else: ?>
                    <button type="submit" name="markeer_nieuw" style="background:#6c757d; color:white; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">
                        🔄 Heropenen
                    </button>
                <?php endif; ?>
            </form>

            <form method="POST" onsubmit="return confirm('Weet je zeker dat je deze rit definitief wilt verwijderen?');" style="margin:0;">
                <button type="submit" name="verwijder_rit" style="background:#dc3545; color:white; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">
                    🗑️
                </button>
            </form>
        </div>
    </div>

    <div style="background:white; padding:30px; border-radius:10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid <?php echo ($rit['status']=='nieuw') ? '#007bff' : '#28a745'; ?>;">
        
        <h1 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
            <?php echo htmlspecialchars($rit['type_dienst']); ?>
            <?php if($rit['status']=='verwerkt') echo "<span style='font-size:0.5em; color:#28a745; float:right;'> (VERWERKT)</span>"; ?>
        </h1>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom:30px;">
            <div><strong>Datum:</strong><br><?php echo date('d-m-Y', strtotime($rit['datum'])); ?></div>
            <div><strong>Chauffeur:</strong><br><?php echo htmlspecialchars($rit['chauffeur_naam']); ?></div>
            <div><strong>Voertuig:</strong><br>Bus <?php echo htmlspecialchars($rit['voertuig_nummer']); ?></div>
        </div>

        <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
            <thead style="background:#f9f9f9; border-bottom:2px solid #ddd;">
                <tr>
                    <th style="text-align:left; padding:8px;">Tijd</th>
                    <th style="text-align:left; padding:8px;">Omschrijving</th>
                    <?php if($rit['type_dienst'] == 'Taxirit'): ?>
                        <th style="text-align:right; padding:8px;">Bedrag</th>
                        <th style="text-align:left; padding:8px;">Betaalwijze</th>
                    <?php else: ?>
                        <th style="text-align:right; padding:8px;">KM Stand</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($regels as $regel): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px;"><?php echo htmlspecialchars($regel['tijd']); ?></td>
                        <td style="padding:8px;">
                            <?php 
                                echo htmlspecialchars($regel['omschrijving']);
                                if(!empty($regel['van_adres'])) echo "<br><small>Van: ".htmlspecialchars($regel['van_adres'])."</small>";
                                if(!empty($regel['naar_adres'])) echo "<br><small>Naar: ".htmlspecialchars($regel['naar_adres'])."</small>";
                            ?>
                        </td>
                        <?php if($rit['type_dienst'] == 'Taxirit'): ?>
                            <td style="text-align:right; padding:8px;">€ <?php echo number_format($regel['bedrag'], 2, ',', '.'); ?></td>
                            <td style="padding:8px;"><?php echo htmlspecialchars($regel['betaalwijze']); ?></td>
                        <?php else: ?>
                            <td style="text-align:right; padding:8px;"><?php echo $regel['km_stand']; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="background:#f8f9fa; padding:15px; border-radius:5px;">
            <strong>Opmerkingen:</strong><br>
            <?php echo nl2br(htmlspecialchars($rit['opmerkingen'])); ?><br><br>
            <strong>Totaal KM:</strong> <?php echo $rit['totaal_km']; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>