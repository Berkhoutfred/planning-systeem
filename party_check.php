<?php
// party_check.php
// VERSIE: JUISTE AFZENDER + MOOIE MAIL

session_start();
ini_set('display_errors', 1); error_reporting(E_ALL);

if (!file_exists('mollie_connect.php')) { die("Bestand mollie_connect.php mist."); }
require 'mollie_connect.php';

require_once __DIR__ . '/beheer/includes/db.php';

$order_id = $_GET['order_id'] ?? 0;
$mollie_id = $_SESSION['mollie_id'] ?? '';

if (!$order_id || !$mollie_id) { die("Geen order of betaling gevonden."); }

$betaling = mollieRequest("payments/" . $mollie_id, [], 'GET');
$status = $betaling['status'] ?? 'open';

if ($status == 'paid') {
    
    // 1. UPDATE STATUS
    $stmt = $pdo->prepare("UPDATE orders SET status = 'betaald' WHERE id = ?");
    $stmt->execute([$order_id]);

    // 2. TICKET INFO
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $tickets = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT naam, datum, locatie FROM party_events WHERE id = ?");
    $stmt->execute([$order['event_id']]);
    $event = $stmt->fetch();
    
    $event_datum_mooi = date('d-m-Y', strtotime($event['datum']));

    // 3. MAAK DE TICKETS (HTML)
    $mail_body_tickets = "";
    
    foreach ($tickets as $index => $ticket) {
        if(empty($ticket['unieke_code'])) {
            $unieke_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
        } else {
            $unieke_code = $ticket['unieke_code'];
        }
        
        $pdo->prepare("UPDATE tickets SET unieke_code = ?, status = 'betaald' WHERE id = ?")
            ->execute([$unieke_code, $ticket['id']]);

        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $unieke_code;
        
        $mail_body_tickets .= "
        <div style='max-width: 500px; margin: 0 auto 20px auto; border: 2px dashed #ccc; background: #fff; font-family: Helvetica, Arial, sans-serif;'>
            <div style='background: #2563eb; color: white; padding: 15px; text-align: center;'>
                <h3 style='margin:0; text-transform: uppercase; letter-spacing: 1px;'>Bus Ticket</h3>
            </div>
            <table width='100%' cellpadding='15'>
                <tr>
                    <td valign='top' style='color: #333;'>
                        <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Evenement</p>
                        <h2 style='margin: 0 0 10px 0; color: #111;'>{$event['naam']}</h2>
                        <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Datum</p>
                        <p style='margin: 0 0 10px 0; font-weight: bold;'>$event_datum_mooi</p>
                        <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Opstaplocatie</p>
                        <p style='margin: 0 0 10px 0; font-weight: bold; color: #e11d48;'>📍 {$ticket['bestemming']}</p>
                        <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Passagier</p>
                        <p style='margin: 0;'>{$order['klant_naam']}</p>
                    </td>
                    <td width='130' align='center' valign='middle' style='border-left: 1px solid #eee;'>
                        <img src='$qr_url' alt='QR' width='120' height='120' style='display:block;'>
                        <p style='margin: 5px 0 0 0; font-family: monospace; font-size: 14px; color: #555;'>$unieke_code</p>
                    </td>
                </tr>
            </table>
            <div style='background: #f9fafb; padding: 10px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #eee;'>
                Toon dit ticket op je telefoon aan de chauffeur
            </div>
        </div>";
    }

    // 4. VERSTUUR MAIL
    $onderwerp = "🎟️ Je tickets voor " . $event['naam'];
    $html_bericht = "
    <html><body style='background-color: #f3f4f6; padding: 20px; font-family: Helvetica, Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; border: 1px solid #ddd;'>
            <h2 style='text-align: center; color: #166534;'>Bedankt voor je bestelling!</h2>
            <p style='text-align: center; color: #555;'>Beste {$order['klant_naam']},<br>Hier zijn je tickets. Fijne reis!</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            $mail_body_tickets
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='text-align: center; font-size: 12px; color: #999;'><strong>Touringcarbedrijf Berkhout</strong></p>
        </div>
    </body></html>";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: info@taxiberkhout.nl" . "\r\n"; // <--- HIER IS HIJ AANGEPAST

    mail($order['email'], $onderwerp, $html_bericht, $headers);

    header("Location: party_succes.php?order_id=" . $order_id);
    exit;

} elseif ($status == 'canceled') {
    echo "<h2 style='text-align:center;'>Betaling geannuleerd</h2><p style='text-align:center;'><a href='/'>Terug naar website</a></p>";
} else {
    echo "<h2>Betaling nog niet verwerkt</h2><p>Status: $status. Ververs de pagina.</p>";
}
?>