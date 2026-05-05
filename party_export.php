<?php
// party_export.php
// VERSIE: EXCEL-PROOF (Leest juiste kolommen + Korte, schone ticketnamen)

include 'beveiliging.php';

require_once __DIR__ . '/beheer/includes/db.php';

$event_id = $_GET['event_id'] ?? 0;

// Event info
$stmt = $pdo->prepare("SELECT naam FROM party_events WHERE id = ?");
$stmt->execute([$event_id]);
$event_naam = $stmt->fetchColumn();

// Orders ophalen
$stmt = $pdo->prepare("SELECT * FROM orders WHERE event_id = ? ORDER BY datum DESC");
$stmt->execute([$event_id]);
$orders = $stmt->fetchAll();

// HEADERS VOOR DOWNLOAD
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Bestellingen-' . preg_replace("/[^a-zA-Z0-9]+/", "", $event_naam) . '.csv');

// OPEN OUTPUT STREAM
$output = fopen('php://output', 'w');

// EXCEL HEADER RIJ
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
fputcsv($output, ['Order ID', 'Datum', 'Naam', 'Email', 'Telefoon', 'Gekozen Tickets', 'Bedrag', 'Status'], ';'); 

// DATA RIJEN
foreach ($orders as $order) {
    // Tickets ophalen en samenvoegen in 1 cel
    $stmt_t = $pdo->prepare("SELECT COUNT(*) as aantal, bestemming FROM tickets WHERE order_id = ? GROUP BY bestemming");
    $stmt_t->execute([$order['id']]);
    $tickets_txt = [];
    foreach($stmt_t->fetchAll() as $t) {
        
        // ---> DE SCHOONMAAK TRUC <---
        // We knippen de naam van het evenement (en het streepje) eruit!
        $schone_bestemming = str_replace($event_naam . " - ", "", $t['bestemming']);
        
        $tickets_txt[] = $t['aantal'] . "x " . $schone_bestemming;
    }
    $ticket_string = implode(", ", $tickets_txt);

    // FIX VOOR TELEFOON
    $telefoon = 'Onbekend';
    if (!empty($order['klant_tel'])) {
        $telefoon = ' ' . $order['klant_tel']; 
    } elseif (!empty($order['tel'])) {
        $telefoon = ' ' . $order['tel'];
    }

    // FIX VOOR EMAIL
    $email_adres = '';
    if (!empty($order['klant_email'])) {
        $email_adres = $order['klant_email'];
    } elseif (!empty($order['email'])) {
        $email_adres = $order['email'];
    }

    // Rij wegschrijven
    fputcsv($output, [
        $order['id'],
        date('d-m-Y H:i', strtotime($order['datum'])),
        $order['klant_naam'],
        $email_adres,
        $telefoon,
        $ticket_string,
        '€ ' . number_format($order['totaal_bedrag'], 2, ',', ''),
        strtoupper($order['status'])
    ], ';'); 
}

fclose($output);
exit;
?>