<?php
// party_ticket_print.php
// VERSIE: TICKET MET VERTREKTIJD ⏰
require_once __DIR__ . '/env.php';

require_once __DIR__ . '/beheer/includes/db.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if(!$order_id) die("Geen bestelling gevonden.");

// Gegevens ophalen
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) die("Bestelling bestaat niet.");

// ---> DE TOKEN VEILIGHEIDSCHECK <---
// Oude klanten (t/m 176) mogen naar binnen. Alles vanaf 175 MOET een token hebben!
if ($order_id > 176) {
    $geheime_sleutel = env_value('TICKET_TOKEN_SECRET', '');
    $ontvanger = $order['klant_email'] ?? ($order['email'] ?? '');
    
    // We berekenen hier exact dezelfde wiskundige code als de postkamer straks doet
    $verwachte_token = hash('sha256', $order_id . $ontvanger . $geheime_sleutel);
    
    $meegegeven_token = $_GET['token'] ?? '';
    
    // Komt de code in de link niet overeen met onze berekening? BAM, deur dicht.
    if ($meegegeven_token !== $verwachte_token) {
        die("<!DOCTYPE html><html lang='nl'><head><title>Geen Toegang</title></head>
             <body style='font-family:sans-serif; text-align:center; padding-top:10vh; background:#f3f4f6; color:#333;'>
                <h2 style='color:#dc3545; font-size:30px;'>Toegang Geweigerd 🛑</h2>
                <p style='font-size:18px;'>Ongeldige of ontbrekende beveiligingssleutel.</p>
                <p style='color:#666;'>Klik op de originele, veilige link in je bevestigingsmail om je tickets te openen.</p>
             </body></html>");
    }
}

// ---> STATUS CHECK <---
if (strtolower($order['status']) !== 'betaald') {
    echo '<!DOCTYPE html><html lang="nl"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta http-equiv="refresh" content="3">'; 
    echo '<title>Betaling Controleren...</title>';
    echo '<style>body{font-family:sans-serif;text-align:center;padding-top:10vh;background:#f3f4f6;color:#333;} .loader{border:4px solid #ddd;border-top:4px solid #2563eb;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:30px auto;} @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}</style>';
    echo '</head><body>';
    echo '<h2>We controleren je betaling...</h2>';
    echo '<div class="loader"></div>';
    echo '<p>Een moment geduld alsjeblieft. Dit scherm vernieuwt automatisch.</p>';
    echo '</body></html>';
    exit;
}

// Tickets ophalen
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE order_id = ?");
$stmt->execute([$order_id]);
$tickets = $stmt->fetchAll();

// Event ophalen
$stmt = $pdo->prepare("SELECT * FROM party_events WHERE id = ?");
$stmt->execute([$order['event_id']]);
$event = $stmt->fetch();

$datum_mooi = date('d-m-Y', strtotime($event['datum']));

// Locatie ophalen als die bestaat
$locatie_details = null;
if (!empty($event['locatie'])) {
    $loc_stmt = $pdo->prepare("SELECT * FROM party_locaties WHERE naam = ?");
    $loc_stmt->execute([$event['locatie']]);
    $locatie_details = $loc_stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Tickets Downloaden</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; background: #555; padding: 20px; display: flex; flex-direction: column; align-items: center;}
        
        #print-area {
            width: 700px;
            background: white;
            padding: 20px;
            min-height: 800px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        .ticket {
            border: 2px dashed #ccc;
            margin-bottom: 25px;
            page-break-inside: avoid;
            background: #fff;
            overflow: hidden; 
        }

        .header { background: #2563eb; color: white; padding: 15px; text-align: center; }
        .header h2 { margin: 0; text-transform: uppercase; letter-spacing: 2px; font-size: 22px; font-weight: 800;}

        /* De content is nu een flexbox die items netjes in het midden uitlijnt */
        .content { padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Links: De tekst krijgt de ruimte die overblijft, maar laat een randje leeg */
        .info { flex: 1; padding-right: 15px; }
        .info h1 { margin: 0 0 5px 0; font-size: 26px; color: #111; }
        
        .adres-lijn { font-size: 13px; color: #666; margin-bottom: 15px; }
        .adres-lijn a { color: #2563eb; text-decoration: none; font-weight: bold; }

        .label { font-size: 10px; text-transform: uppercase; color: #888; margin-top: 12px; display: block; font-weight: bold; }
        .value { font-size: 16px; font-weight: bold; color: #333; }
        .halte { color: #e11d48; font-size: 18px; margin-bottom: 2px; }
        .vertrek-tijd { font-size: 14px; color: #16a34a; font-weight: bold; } /* NIEUWE STIJL VOOR DE TIJD */

        /* Midden: Het fotoblok */
        .middle-image-box {
            flex: 0 0 220px; 
            text-align: center;
            padding: 0 15px;
        }
        .middle-image-box img {
            max-width: 100%;
            max-height: 140px;
            border-radius: 6px;
            object-fit: contain;
        }

        /* Rechts: De QR Code */
        .qr-box { text-align: center; border-left: 1px solid #eee; padding-left: 15px; min-width: 140px; }
        .code { font-family: monospace; font-size: 14px; margin-top: 5px; color: #555; }

        .extra-info-box {
            background: #fdf2f8; 
            color: #be185d;
            padding: 10px;
            font-size: 13px;
            margin: 0px 20px 20px 20px;
            border-radius: 6px;
            border: 1px solid #fbcfe8;
        }

        .btn-download {
            background: #22c55e; color: white; border: none; padding: 15px 40px; 
            font-size: 20px; border-radius: 8px; cursor: pointer; font-weight: bold;
            margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: 0.2s;
        }
        .btn-download:hover { transform: scale(1.05); background: #16a34a; }

        .no-print { margin-bottom: 20px; text-align: center; color: white;}
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="downloadPDF()" class="btn-download">⬇️ Download als PDF</button>
    <br>
    <small>Klik op de knop om de tickets op te slaan</small>
</div>

<div id="print-area">
    <?php foreach($tickets as $ticket): 
        // 1. ZOEKEN NAAR DE BIJBEHORENDE VERTREKTIJD VOOR DIT SPECIFIEKE TICKET
        $tijd_stmt = $pdo->prepare("SELECT tijd FROM party_opstap_locaties WHERE event_id = ? AND naam = ? LIMIT 1");
        $tijd_stmt->execute([$event['id'], $ticket['bestemming']]);
        $halte_tijd = $tijd_stmt->fetchColumn();
    ?>
    <div class="ticket">
        <div class="header">
            <h2>🚌 BUSTICKET</h2>
        </div>

        <div class="content">
            <div class="info">
                <span class="label">Evenement</span>
                <h1><?php echo htmlspecialchars($event['naam'] ?? 'Onbekend Evenement'); ?></h1>
                
                <?php if ($locatie_details && !empty($locatie_details['adres'])): ?>
                    <div class="adres-lijn">
                        📍 <?php echo htmlspecialchars($locatie_details['adres']); ?>
                        <?php if (!empty($locatie_details['maps_link'])): ?>
                            <br><a href="<?php echo htmlspecialchars($locatie_details['maps_link']); ?>" target="_blank">🔗 Bekijk op Google Maps</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="adres-lijn" style="font-weight:bold;">
                        <?php echo htmlspecialchars($event['locatie'] ?? ''); ?>
                    </div>
                <?php endif; ?>
                
                <span class="label">Datum</span>
                <div class="value"><?php echo $datum_mooi; ?></div>

                <span class="label">Gekozen Halte / Afzetplek</span>
                <div class="value halte">📍 <?php echo htmlspecialchars($ticket['bestemming']); ?></div>
                
                <?php if ($halte_tijd): ?>
                    <div class="vertrek-tijd">⏰ Vertrektijd: <?php echo substr($halte_tijd, 0, 5); ?> uur</div>
                <?php endif; ?>

                <span class="label">Passagier</span>
                <div class="value"><?php echo htmlspecialchars($order['klant_naam']); ?></div>
            </div>

            <?php if (!empty($event['afbeelding'])): ?>
            <div class="middle-image-box">
                <img src="beheer/images/<?php echo htmlspecialchars($event['afbeelding']); ?>" crossorigin="anonymous">
            </div>
            <?php endif; ?>

            <div class="qr-box">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $ticket['unieke_code']; ?>" width="130" crossorigin="anonymous">
                <div class="code"><?php echo $ticket['unieke_code']; ?></div>
            </div>
        </div>

        <?php if(!empty($event['ticket_info'])): ?>
        <div class="extra-info-box">
            <strong>ℹ️ Info:</strong> <?php echo nl2br(htmlspecialchars($event['ticket_info'])); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
    function downloadPDF() {
        var element = document.getElementById('print-area');
        var opt = {
            margin:       10,
            filename:     'Tickets-Berkhout.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>