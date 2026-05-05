<?php
// Bestand: beheer/vakantierooster_chauffeur.php
// Doel: Totaaloverzicht van alle geplande afwezigheden van de chauffeurs

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// Haal alle actuele en toekomstige afwezigheden op. 
// We sorteren op startdatum zodat het chronologisch loopt.
$query = "
    SELECT a.*, c.voornaam, c.achternaam
    FROM afwezigheid a
    JOIN chauffeurs c ON a.chauffeur_id = c.id
    WHERE a.einddatum >= CURDATE()
    ORDER BY a.startdatum ASC
";
$stmt = $pdo->query($query);
$rooster = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<style>
    .rooster-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-top: 4px solid #003366; margin-top: 20px; }
    .rooster-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 15px; }
    .rooster-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
    .rooster-table td { padding: 12px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
    .rooster-table tr:hover { background-color: #f1f7fd; }
    
    .badge-type { padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; color: #fff; display: inline-block; text-align: center; min-width: 75px; }
    .type-Vakantie { background: #28a745; }
    .type-Ziek { background: #dc3545; }
    .type-Verlof { background: #17a2b8; }
    .type-Overig { background: #6c757d; }

    .btn-actie { background: #dd6b20; color: #fff; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block; }
    .btn-actie:hover { background: #c05615; }
    
    .maand-header { background: #e9ecef; font-weight: bold; padding: 10px 15px; margin-top: 20px; border-radius: 5px; color: #003366; border-left: 4px solid #dd6b20; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center;">
    <h1>Vakantierooster</h1>
    <div>
        <a href="chauffeurs.php" style="background:#6c757d; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; margin-right: 10px;">&laquo; Chauffeurslijst</a>
    </div>
</div>

<div class="rooster-container">
    <p style="color: #555; margin-bottom: 20px; font-size: 16px;">
        Hieronder zie je het actuele en komende afwezigheidsrooster van alle chauffeurs. Ideaal om in één oogopslag te zien met wie je rekening moet houden bij de planning.
    </p>

    <?php if (count($rooster) == 0): ?>
        <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; border: 1px solid #dee2e6;">
            <p style="color: #6c757d; margin: 0; font-size: 16px;"><em>Er staan momenteel geen actuele of toekomstige afwezigheden gepland in het systeem.</em></p>
        </div>
    <?php else: ?>
        <table class="rooster-table">
            <thead>
                <tr>
                    <th>Chauffeur</th>
                    <th>Type</th>
                    <th>Van</th>
                    <th>T/M</th>
                    <th>Opmerking</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $huidige_maand = '';
                
                foreach ($rooster as $item): 
                    // Slimme truc om de datum om te zetten naar een leesbare Nederlandse maandnaam
                    $start_maand = date('F Y', strtotime($item['startdatum']));
                    $maanden = [
                        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maart', 
                        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 
                        'July' => 'Juli', 'August' => 'Augustus', 'September' => 'September', 
                        'October' => 'Oktober', 'November' => 'November', 'December' => 'December'
                    ];
                    $maand_nl = strtr($start_maand, $maanden);

                    // Toon een duidelijke scheidingslijn als we aan een nieuwe maand beginnen
                    if ($huidige_maand !== $maand_nl) {
                        $huidige_maand = $maand_nl;
                        echo "<tr><td colspan='6' style='padding: 0;'><div class='maand-header'>📅 " . htmlspecialchars($huidige_maand) . "</div></td></tr>";
                    }
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['voornaam'] . ' ' . $item['achternaam']); ?></strong></td>
                        <td><span class="badge-type type-<?php echo $item['type']; ?>"><?php echo $item['type']; ?></span></td>
                        <td><?php echo date('d-m-Y', strtotime($item['startdatum'])); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($item['einddatum'])); ?></td>
                        <td style="color: #666; font-style: italic;"><?php echo htmlspecialchars($item['opmerking'] ?? '-'); ?></td>
                        <td>
                            <a href="chauffeur_vakantie.php?id=<?php echo $item['chauffeur_id']; ?>" class="btn-actie" title="Bekijk dossier van deze chauffeur">Dossier</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>