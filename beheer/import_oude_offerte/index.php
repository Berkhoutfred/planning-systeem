<?php
// Bestand: beheer/import_oude_offerte/index.php
// Doel: Fase 3 - De Uitgebreide Scanner mét Doorstuur-knop

include '../../beveiliging.php';
$path = '../'; 
include '../includes/header.php';

$geplakte_tekst = '';
$gevonden_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['geplakte_tekst'])) {
    $geplakte_tekst = $_POST['geplakte_tekst'];
    
    // --- DE SLIMME SCANNER V2.3 ---
    
    if (preg_match('/^\s*([^\r\n]+)/', $geplakte_tekst, $match)) $gevonden_data['Mogelijke Klant'] = trim($match[1]);
    if (preg_match('/Bijzonderheden:\s*(.*?)\s*(?:Beschrijving|Vervoerskosten|contactpersoon|Totaalprijs|The following)/is', $geplakte_tekst, $match)) $gevonden_data['Bijzonderheden'] = trim(str_replace('"', '', $match[1])); 
    if (preg_match('/[\r\n]Programma\s+(.*?)\s+Vertrekdatum/is', $geplakte_tekst, $match)) $gevonden_data['Soort Rit'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/Vertrekdatum.*?(\d{2}-\d{2}-\d{4})/is', $geplakte_tekst, $match)) $gevonden_data['Vertrekdatum'] = $match[1];
    if (preg_match('/Einddatum.*?(\d{2}-\d{2}-\d{4})/is', $geplakte_tekst, $match)) $gevonden_data['Einddatum'] = $match[1];
    if (preg_match('/Aantal passagiers.*?(\d+)/is', $geplakte_tekst, $match)) $gevonden_data['Aantal Passagiers'] = $match[1];
    if (preg_match('/Aantal touringcars.*?(\d+)/is', $geplakte_tekst, $match)) $gevonden_data['Aantal Touringcars'] = $match[1];
    if (preg_match('/Vertrekadres\s+(.*?)\s+Vertrekplaats/is', $geplakte_tekst, $match)) $gevonden_data['Vertrekadres'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/Vertrekplaats\s+(.*?)\s+Voorstaan/is', $geplakte_tekst, $match)) $gevonden_data['Vertrekplaats'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/Vertrektijd\s+([0-9]{2}:[0-9]{2})/is', $geplakte_tekst, $match)) $gevonden_data['Vertrektijd'] = $match[1];
    if (preg_match('/\bBestemming\b\s+(.*?)\s+\bAdres\b/is', $geplakte_tekst, $match)) $gevonden_data['Bestemming'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/\bAdres\b\s+(.*?)\s+\bPlaats\b/is', $geplakte_tekst, $match)) $gevonden_data['Bestemmingsadres'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/\bPlaats\b\s+(.*?)\s+Aankomsttijd/is', $geplakte_tekst, $match)) $gevonden_data['Bestemmingsplaats'] = trim(str_replace(['"', ','], '', $match[1]));
    if (preg_match('/Aankomsttijd bestemming\s+([0-9]{2}:[0-9]{2})/is', $geplakte_tekst, $match)) $gevonden_data['Aankomsttijd'] = $match[1];
    if (preg_match('/Vertrektijd retour\s+([0-9]{2}:[0-9]{2})/is', $geplakte_tekst, $match)) $gevonden_data['Vertrektijd Retour'] = $match[1];
    if (preg_match('/Aankomsttijd retour\s+([0-9]{2}:[0-9]{2})/is', $geplakte_tekst, $match)) $gevonden_data['Aankomsttijd Retour'] = $match[1];
    
    if (preg_match('/Totaalprijs incl\. BTW:.*?€\s*([0-9]+,[0-9]{2})/is', $geplakte_tekst, $match)) {
        $gevonden_data['Prijs'] = $match[1];
    } elseif (preg_match('/€\s*([0-9]+,[0-9]{2})/is', $geplakte_tekst, $match)) {
        $gevonden_data['Prijs'] = $match[1];
    }
}
?>

<div style="max-width: 900px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
    <h1 style="color:#003366;"><i class="fas fa-file-import"></i> Oude Offerte Importeren</h1>
    <p>Open de oude PDF, selecteer alles (<b>Ctrl + A</b>), kopieer (<b>Ctrl + C</b>) en plak de tekst in het vak (<b>Ctrl + V</b>).</p>

    <form method="POST" action="">
        <textarea name="geplakte_tekst" style="width: 100%; height: 200px; padding: 15px; font-family: monospace; border: 2px dashed #ccc; border-radius: 8px; margin-bottom: 20px;" placeholder="Plak hier de tekst uit de PDF..."><?php echo htmlspecialchars($geplakte_tekst); ?></textarea>
        
        <button type="submit" style="background:#003366; color:white; padding:15px 30px; border:none; border-radius:5px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%;">
            Start Scanner
        </button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <hr style="margin: 30px 0; border: 1px solid #eee;">
        <h2 style="color:#003366;">Resultaat van de Scanner:</h2>
        
        <?php if (!empty($gevonden_data)): ?>
            <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; border-left: 5px solid #28a745; margin-bottom: 20px;">
                <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
                    <?php foreach ($gevonden_data as $label => $waarde): ?>
                        <tr>
                            <td style="padding: 10px 0; font-weight: bold; width: 250px; border-bottom: 1px solid #c8e6c9;"><?php echo $label; ?>:</td>
                            <td style="padding: 10px 0; color: #003366; border-bottom: 1px solid #c8e6c9;"><?php echo htmlspecialchars(trim($waarde)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <form method="POST" action="../calculatie/maken.php">
                <?php foreach ($gevonden_data as $label => $waarde): ?>
                    <input type="hidden" name="import_data[<?php echo htmlspecialchars($label); ?>]" value="<?php echo htmlspecialchars(trim($waarde)); ?>">
                <?php endforeach; ?>
                
                <button type="submit" style="background:#28a745; color:white; padding:15px 30px; border:none; border-radius:5px; font-size: 18px; font-weight: bold; cursor: pointer; width: 100%; box-shadow: 0 4px 6px rgba(40, 167, 69, 0.3);">
                    <i class="fas fa-magic"></i> Deze gegevens meenemen naar Nieuwe Offerte
                </button>
            </form>

        <?php else: ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;">
                Oeps! Ik kon geen herkenbare gegevens vinden.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>