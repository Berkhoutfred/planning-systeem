<?php
// Bestand: beheer/chauffeurs_archief.php
// VERSIE: Het Chauffeurs Archief

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// ---> HERSTEL LOGICA <---
// Als iemand op de 'Zet terug actief' knop heeft geklikt
if (isset($_GET['restore_id'])) {
    $rest_id = (int)$_GET['restore_id'];
    if ($rest_id > 0) {
        // We zetten 'archief' weer netjes op 0
        $stmt_rest = $pdo->prepare("UPDATE chauffeurs SET archief = 0 WHERE id = ?");
        $stmt_rest->execute([$rest_id]);
        
        // Ververs de pagina
        echo "<script>window.location.href='chauffeurs_archief.php';</script>";
        exit;
    }
}
// ----------------------------

// Chauffeurs ophalen (Alleen de GEARCHIVEERDE chauffeurs: archief = 1)
$stmt = $pdo->query("SELECT * FROM chauffeurs WHERE archief = 1 ORDER BY voornaam ASC");
$aantal_archief = $stmt->rowCount();
?>

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1 style="color: #6c757d;">🗄️ Chauffeurs Archief</h1>
    <a href="chauffeurs.php" style="background:#007bff; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;">⬅️ Terug naar Actief</a>
</div>

<p style="color: #666; font-size: 14px; margin-bottom: 20px;">
    Hier zie je alle chauffeurs die uit dienst zijn. Ze zijn verborgen in het hoofdmenu en de planning, maar hun gegevens en ritten blijven hier veilig bewaard.
</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px; opacity: 0.9;">
    <thead>
        <tr style="background-color: #e9ecef; border-bottom: 2px solid #ced4da;">
            <th style="padding: 12px; text-align: left; color: #495057;">Naam</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Email</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Datum Uit Dienst</th>
            <th style="padding: 12px; text-align: left; color: #495057;">Actie</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($driver = $stmt->fetch()): ?>
            <tr style="border-bottom: 1px solid #ced4da; background: #f8f9fa;">
                <td style="padding: 12px; color: #6c757d;">
                    <strong><?php echo htmlspecialchars($driver['voornaam'] . ' ' . $driver['achternaam']); ?></strong>
                </td>
                <td style="padding: 12px; color: #6c757d;"><?php echo htmlspecialchars($driver['email'] ?? '-'); ?></td>
                <td style="padding: 12px; color: #6c757d;">
                    <?php 
                        $datum_uit = $driver['datum_uit_dienst'] ?? null;
                        if($datum_uit) {
                            echo date('d-m-Y', strtotime($datum_uit));
                        } else {
                            echo "Onbekend";
                        }
                    ?>
                </td>
                <td style="padding: 12px;">
                    <a href="?restore_id=<?php echo $driver['id']; ?>" onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($driver['voornaam']); ?> weer ACTIEF wilt maken? Hij/zij verschijnt dan weer in de planning.');" style="color: #28a745; text-decoration: none; font-weight: bold; background: #d4edda; padding: 6px 12px; border-radius: 4px; border: 1px solid #c3e6cb;">
                        ♻️ Zet terug actief
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php if ($aantal_archief == 0): ?>
    <div style="background: #e9ecef; padding: 20px; text-align: center; border-radius: 8px; margin-top: 20px; border: 1px solid #ced4da;">
        <p style="color: #6c757d; margin: 0; font-size: 16px;"><em>Het archief is op dit moment helemaal leeg.</em></p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>