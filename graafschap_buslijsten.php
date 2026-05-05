<?php
// graafschap_buslijsten.php
// VERSIE: Bussen benoemd + Optie voor 1 Totaaloverzicht!

include 'beveiliging.php';
require_once __DIR__ . '/env.php';

$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
} catch(PDOException $e) { die("Database fout"); }

$event_id = 3; // Graafschap Feest

// 1. De Bussen gekoppeld aan de routes
$bussen = [
    'A' => [
        'naam' => 'Bus 1 (Route A - Max 19)',
        'kleur' => '#ef4444', 
        'haltes' => ['ulft' => 1, 'gendringen' => 2, 'heerenberg' => 3, 'zevenaar' => 4, 'drempt' => 5]
    ],
    'B' => [
        'naam' => 'Bus 2 (Route B - Max 60)',
        'kleur' => '#3b82f6', 
        'haltes' => ['aalten' => 1, 'winterswijk' => 2, 'groenlo' => 3, 'beltrum' => 4, 'eibergen' => 5, 'haaksbergen' => 6, 'neede' => 7]
    ],
    'C' => [
        'naam' => 'Bus 3 (Route C - Max 19)',
        'kleur' => '#22c55e', 
        'haltes' => ['gaanderen' => 1, 'doetinchem' => 2, 'vorden' => 3]
    ]
];

$download_actie = $_GET['download'] ?? null;

if ($download_actie) {
    
    // Haal ALLEEN de betaalde tickets op!
    $sql = "SELECT o.klant_naam, o.klant_tel, o.tel, t.bestemming 
            FROM orders o 
            JOIN tickets t ON o.id = t.order_id 
            WHERE o.event_id = ? 
            AND o.status IN ('paid', 'betaald', 'voltooid', 'succes', 'completed')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id]);
    $tickets = $stmt->fetchAll();

    $buslijst = [];

    foreach ($tickets as $t) {
        $bestemming = strtolower($t['bestemming']);
        $hoort_in_bus = null;
        $volgorde = 99;

        // Zoek de juiste bus en halte-volgorde
        foreach($bussen as $bus_letter => $data) {
            foreach($data['haltes'] as $zoekwoord => $v) {
                if (strpos($bestemming, $zoekwoord) !== false) {
                    $hoort_in_bus = $bus_letter;
                    $volgorde = $v;
                    break 2;
                }
            }
        }

        // Als we één specifieke bus downloaden, filter de rest eruit. 
        // Bij 'alles' laten we iedereen erin!
        if ($download_actie == 'alles' || $download_actie == $hoort_in_bus) {
            
            if ($hoort_in_bus) {
                $telefoon = 'Onbekend';
                if (!empty($t['klant_tel'])) {
                    $telefoon = ' ' . $t['klant_tel']; 
                } elseif (!empty($t['tel'])) {
                    $telefoon = ' ' . $t['tel'];
                }

                $delen = explode(" - ", $t['bestemming']);
                $korte_halte = end($delen); 

                $buslijst[] = [
                    'bus_naam' => $bussen[$hoort_in_bus]['naam'],
                    'bus_letter' => $hoort_in_bus,
                    'volgorde' => $volgorde,
                    'naam' => $t['klant_naam'],
                    'telefoon' => $telefoon,
                    'halte' => $korte_halte
                ];
            }
        }
    }

    // Sorteren: Eerst op Bus, daarna op Halte-volgorde!
    usort($buslijst, function($a, $b) {
        if ($a['bus_letter'] == $b['bus_letter']) {
            return $a['volgorde'] <=> $b['volgorde'];
        }
        return strcmp($a['bus_letter'], $b['bus_letter']);
    });

    // Bepaal bestandsnaam
    $bestandsnaam = ($download_actie == 'alles') ? 'Totaaloverzicht-Alle-Bussen' : str_replace(" ", "-", $bussen[$download_actie]['naam']);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Printlijst-' . $bestandsnaam . '.csv');

    $output = fopen('php://output', 'w');
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // De kolommen
    if ($download_actie == 'alles') {
        fputcsv($output, ['Toegewezen Bus', 'Stop', 'Uitstaplocatie', 'Naam Passagier', 'Telefoonnummer'], ';');
        foreach ($buslijst as $rij) {
            fputcsv($output, [$rij['bus_naam'], $rij['volgorde'], $rij['halte'], $rij['naam'], $rij['telefoon']], ';');
        }
    } else {
        fputcsv($output, ['Stop', 'Uitstaplocatie', 'Naam Passagier', 'Telefoonnummer', 'Aanwezig?'], ';');
        foreach ($buslijst as $rij) {
            fputcsv($output, [$rij['volgorde'], $rij['halte'], $rij['naam'], $rij['telefoon'], '[   ]'], ';');
        }
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Buslijsten Graafschap Feest</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; padding: 40px 20px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-top: 0; font-size: 24px; }
        p { color: #6b7280; margin-bottom: 30px; }
        .btn { display: block; width: 100%; padding: 18px; margin-bottom: 15px; color: white; text-decoration: none; font-size: 18px; font-weight: bold; border-radius: 8px; transition: transform 0.2s; box-sizing: border-box; }
        .btn:hover { transform: scale(1.02); }
        .btn-alles { background-color: #1f2937; margin-top: 30px; border: 2px dashed #4b5563; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚌 Print-Dashboard Chauffeurs</h1>
    <p>Selecteer een losse bus voor de chauffeur, of download het totaaloverzicht voor de administratie. (Alleen betaalde tickets!)</p>

    <?php foreach($bussen as $letter => $data): ?>
        <a href="?download=<?php echo $letter; ?>" class="btn" style="background-color: <?php echo $data['kleur']; ?>;">
            📥 Download <?php echo $data['naam']; ?>
        </a>
    <?php endforeach; ?>

    <a href="?download=alles" class="btn btn-alles">
        📋 Download ALLE Bussen in 1 Excel
    </a>

    <a href="beheer/index.php" style="display:inline-block; margin-top:20px; color:#6b7280; text-decoration:none;">⬅ Terug naar Beheer</a>
</div>

</body>
</html>