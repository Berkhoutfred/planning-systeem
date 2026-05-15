<?php
include '../../beveiliging.php';
require '../includes/db.php';
include '../includes/header.php';

// 1. Check of er een ID is meegegeven
if (!isset($_GET['id'])) {
    echo "<div style='padding:20px; color:red;'>Geen rit geselecteerd. <a href='index.php'>Terug naar overzicht</a></div>";
    include '../includes/footer.php';
    exit;
}

$id = $_GET['id'];

// 2. Haal ALLE details op (incl. klantnaam, busnaam, rittype)
$sql = "SELECT 
            c.*, 
            k.bedrijfsnaam, k.voornaam, k.achternaam, k.email, k.telefoon, k.adres AS klant_adres, k.plaats AS klant_plaats,
            v.naam AS bus_naam, v.kenteken,
            r.naam AS rit_naam
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id
        LEFT JOIN calculatie_voertuigen v ON c.voertuig_id = v.id
        LEFT JOIN calculatie_rittypes r ON c.rittype_id = r.id
        WHERE c.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$rit = $stmt->fetch();

if (!$rit) {
    echo "<div style='padding:20px; color:red;'>Rit niet gevonden.</div>";
    include '../includes/footer.php';
    exit;
}
?>

<div style="max-width: 900px; margin: auto;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <a href="index.php" style="text-decoration: none; color: #666;">&larr; Terug naar overzicht</a>
            <h2 style="margin-top: 5px; margin-bottom: 0;">Calculatie #<?php echo $rit['id']; ?></h2>
            <span style="background: orange; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?php echo ucfirst($rit['status']); ?></span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="#" style="background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">✏️ Bewerken</a>
            <a href="#" style="background: #17a2b8; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">📄 PDF Bekijken</a>
            <a href="#" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">📧 Verstuur Offerte</a>
        </div>
    </div>

    <div style="display: flex; gap: 20px; align-items: flex-start;">
        
        <div style="flex: 2;">
            <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Rit informatie</h3>
                <table style="width: 100%;">
                    <tr><td style="width: 140px; font-weight: bold; padding: 5px 0;">Datum:</td><td><?php echo date('d-m-Y', strtotime($rit['rit_datum'])); ?></td></tr>
                    <tr><td style="font-weight: bold; padding: 5px 0;">Vertrek:</td><td><?php echo htmlspecialchars($rit['vertrek_adres']); ?></td></tr>
                    <tr><td style="font-weight: bold; padding: 5px 0;">Bestemming:</td><td><?php echo htmlspecialchars($rit['aankomst_adres']); ?></td></tr>
                    <tr><td style="font-weight: bold; padding: 5px 0;">Afstand:</td><td><?php echo $rit['afstand_km']; ?> km (enkele reis)</td></tr>
                    <tr><td style="font-weight: bold; padding: 5px 0;">Geschatte tijd:</td><td><?php echo $rit['uren']; ?> uur</td></tr>
                </table>
            </div>

            <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
                <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Voertuig & Klant</h3>
                <p>
                    <strong>Bus:</strong> <?php echo htmlspecialchars($rit['bus_naam']); ?> 
                    <?php if($rit['kenteken']) echo "(".htmlspecialchars($rit['kenteken']).")"; ?><br>
                    <strong>Aantal:</strong> <?php echo $rit['aantal_bussen']; ?>x<br>
                    <strong>Type:</strong> <?php echo htmlspecialchars($rit['rit_naam']); ?>
                </p>
                <hr style="border: 0; border-top: 1px solid #eee;">
                <p>
                    <strong>Klant:</strong> <?php echo htmlspecialchars($rit['bedrijfsnaam'] ?: $rit['voornaam'] . ' ' . $rit['achternaam']); ?><br>
                    <?php if($rit['klant_adres']) echo htmlspecialchars($rit['klant_adres']) . ", " . htmlspecialchars($rit['klant_plaats']) . "<br>"; ?>
                    <a href="mailto:<?php echo htmlspecialchars($rit['email']); ?>"><?php echo htmlspecialchars($rit['email']); ?></a><br>
                    <?php echo htmlspecialchars($rit['telefoon']); ?>
                </p>
            </div>
        </div>

        <div style="flex: 1;">
            <div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
                <h3 style="margin-top: 0; color: #333;">Financieel</h3>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Kostprijs:</span>
                    <strong>€ <?php echo number_format($rit['kostprijs'], 2, ',', '.'); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Extra kosten:</span>
                    <span>€ <?php echo number_format($rit['extra_kosten'], 2, ',', '.'); ?></span>
                </div>
                
                <hr>

                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 1.1em;">
                    <span>Verkoopprijs:</span>
                    <strong>€ <?php echo number_format($rit['prijs'], 2, ',', '.'); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; color: #666; font-size: 0.9em; margin-bottom: 15px;">
                    <span>(incl. BTW)</span>
                </div>

                <div style="background: #e9ecef; padding: 10px; border-radius: 4px; text-align: center;">
                    <small>Winstmarge (excl. BTW)</small><br>
                    <strong style="color: #28a745;"><?php echo number_format(($rit['prijs'] / 1.09) - $rit['kostprijs'], 2, ',', '.'); ?></strong>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>