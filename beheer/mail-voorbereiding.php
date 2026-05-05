<?php
// Bestand: beheer/mail-voorbereiding.php
include '../beveiliging.php';
require 'includes/db.php';
require_once 'includes/tenant_instellingen_db.php';
include 'includes/header.php';

if (!isset($_POST['geselecteerde_klanten']) || empty($_POST['geselecteerde_klanten'])) {
    echo "<div style='padding:20px;'>";
    echo "<h2>Oeps! Geen klanten geselecteerd.</h2>";
    echo "<p>U heeft geen klanten aangevinkt om een e-mail naar te sturen.</p>";
    echo "<a href='klanten.php' style='background:#007bff; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Ga terug naar het overzicht</a>";
    echo "</div>";
    include 'includes/footer.php';
    exit;
}

$klant_ids = $_POST['geselecteerde_klanten'];

$placeholders = implode(',', array_fill(0, count($klant_ids), '?'));
$stmt = $pdo->prepare("SELECT id, bedrijfsnaam, voornaam, achternaam, email FROM klanten WHERE id IN ($placeholders)");
$stmt->execute($klant_ids);
$klantenlijst = $stmt->fetchAll();

$tenantId = function_exists('current_tenant_id') ? current_tenant_id() : 0;
$tenantCfg = tenant_instellingen_get($pdo, $tenantId);
$brandNaam = trim((string) ($tenantCfg['bedrijfsnaam'] ?? 'BusAI'));

// Concept tekst mét jouw briljante rekenvoorbeeld!
$standaard_onderwerp = "Belangrijke informatie omtrent uw boeking: Brandstoftoeslag";
$standaard_bericht = "Beste klant,\n\nWij kijken ernaar uit om uw geplande rit binnenkort te verzorgen, helaas moeten wij een dieseltoeslag gaan hanteren.\n\nZoals u wellicht op uw offerte heeft gezien en in het nieuws heeft vernomen, hebben wij helaas te maken met extreem gestegen dieselprijzen. Wij vinden het belangrijk om hier transparant in te zijn. Daarom hanteren wij geen vast percentage, maar een eerlijke berekening op basis van de feiten. Voor de inzet van een grote touringcar (verbruik 1 liter op 3 kilometer) berekenen we uitsluitend de daadwerkelijke liters en het prijsverschil.\n\nHoe berekenen wij dit? Een eerlijk rekenvoorbeeld:\n- Uw totale rit: 120 kilometer\n- Benodigde brandstof: 40 liter (120 km / 3)\n- Gecalculeerde prijs: € 1,75 per liter\n- Actuele dagprijs: € 2,15 per liter (Verschil: € 0,40)\n- Extra kosten: 40 liter x € 0,40 = € 16,- (incl. BTW)\n\nOp deze manier berekenen wij uitsluitend de pure brandstofstijging aan u door voor de kilometers die wij voor u rijden. Wel zo eerlijk!\n\nWij begrijpen dat een prijsstijging vervelend nieuws is. Daarom bieden wij u de mogelijkheid om, indien gewenst, uw rit kosteloos te annuleren. Wilt u hiervan gebruikmaken? Laat het ons dan zo spoedig mogelijk weten door te reageren op deze e-mail.\n\nHoren wij niets van u? Dan gaan we er vanuit dat de rit gewoon doorgaat en brengen wij u op de afgesproken dag met veel plezier naar uw bestemming!\n\nMet vriendelijke groet,\n\n" . $brandNaam;
?>

<div style="max-width: 900px; margin: 0 auto; padding: 20px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 8px;">
    <h1 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">📧 E-mail Voorbereiden</h1>
    
    <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ffeeba;">
        <strong>Controle:</strong> U staat op het punt om een e-mail te sturen naar <strong><?php echo count($klantenlijst); ?></strong> geselecteerde klant(en). Controleer de lijst en de tekst hieronder goed. Er wordt nog niets verstuurd totdat u op de groene knop drukt.
    </div>

    <div style="display: flex; gap: 20px;">
        
        <div style="flex: 1; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
            <h3 style="margin-top: 0;">Ontvangers:</h3>
            <ul style="list-style-type: none; padding-left: 0; margin: 0; max-height: 400px; overflow-y: auto;">
                <?php foreach ($klantenlijst as $klant): 
                    $naam = !empty($klant['bedrijfsnaam']) ? $klant['bedrijfsnaam'] : $klant['voornaam'] . ' ' . $klant['achternaam'];
                    $email_check = empty($klant['email']) ? "<span style='color:red;'>(Geen e-mailadres!)</span>" : $klant['email'];
                ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                        <strong><?php echo htmlspecialchars($naam); ?></strong><br>
                        <small><?php echo $email_check; ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="flex: 2;">
            <form method="POST" action="mail-verzenden.php">
                <?php foreach ($klant_ids as $id): ?>
                    <input type="hidden" name="klant_ids[]" value="<?php echo htmlspecialchars($id); ?>">
                <?php endforeach; ?>

                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Onderwerp:</label>
                <input type="text" name="onderwerp" value="<?php echo htmlspecialchars($standaard_onderwerp); ?>" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;" required>

                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Bericht:</label>
                <textarea name="bericht" rows="22" style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit;" required><?php echo htmlspecialchars($standaard_bericht); ?></textarea>

                <div style="display: flex; justify-content: space-between;">
                    <a href="klanten.php" style="background: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Annuleren & Terug</a>
                    <button type="submit" style="background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px;">
                        ✉️ E-mails Definitief Verzenden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>