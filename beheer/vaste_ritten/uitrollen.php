<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Bestand: beheer/vaste_ritten/uitrollen.php
// VERSIE: De motor die het sjabloon omzet naar losse ritten in de database

include '../../beveiliging.php';
require '../includes/db.php';

// Check of we een sjabloon ID hebben gekregen
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Geen sjabloon geselecteerd.");
}

$id = (int)$_GET['id'];

// 1. Haal alle gegevens van dit specifieke sjabloon op
$stmt = $pdo->prepare("SELECT * FROM vaste_ritten WHERE id = ?");
$stmt->execute([$id]);
$sjabloon = $stmt->fetch();

if (!$sjabloon) {
    die("Sjabloon niet gevonden in de database.");
}

// 2. Zet de uitzonderingsdatums (feestdagen) om in een slim lijstje
$uitzonderingen = [];
if (!empty($sjabloon['uitzondering_datums'])) {
    $dates = explode(',', $sjabloon['uitzondering_datums']);
    foreach ($dates as $d) {
        $time = strtotime(trim($d));
        if ($time) {
            $uitzonderingen[] = date('Y-m-d', $time);
        }
    }
}

// 3. De kalender voorbereiden (van start tot eind)
$start_datum = new DateTime($sjabloon['startdatum']);
$eind_datum = new DateTime($sjabloon['einddatum']);
$eind_datum->modify('+1 day'); // Zorg dat de allerlaatste dag ook nog meedoet

$interval = DateInterval::createFromDateString('1 day');
$periode = new DatePeriod($start_datum, $interval, $eind_datum);

$aantal_aangemaakt = 0;

// 4. De motor starten: we lopen door elke dag van de kalender heen
foreach ($periode as $dt) {
    $datum_string = $dt->format('Y-m-d');
    $dag_vd_week = $dt->format('N'); // 1 = maandag, 7 = zondag

    // Rijden we op deze dag van de week?
    $rijdt_vandaag = false;
    if ($dag_vd_week == 1 && $sjabloon['rijdt_ma'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 2 && $sjabloon['rijdt_di'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 3 && $sjabloon['rijdt_wo'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 4 && $sjabloon['rijdt_do'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 5 && $sjabloon['rijdt_vr'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 6 && $sjabloon['rijdt_za'] == 1) $rijdt_vandaag = true;
    if ($dag_vd_week == 7 && $sjabloon['rijdt_zo'] == 1) $rijdt_vandaag = true;

    // Als we niet rijden, of als het een feestdag is: overslaan!
    if (!$rijdt_vandaag) continue;
    if (in_array($datum_string, $uitzonderingen)) continue;

    // We mogen rijden! Tijd en datum samenvoegen
    $start_datetime = $datum_string . ' ' . $sjabloon['vertrektijd'];
    $eind_datetime = $datum_string . ' ' . $sjabloon['aankomsttijd'];

  // --- DEEL A: Plaats de kapstok in de tabel 'ritten' ---
    // We gebruiken NULL in plaats van 0, omdat deze vaste rit niet aan een calculatie is gekoppeld.
    $stmt_rit = $pdo->prepare("INSERT INTO ritten 
        (calculatie_id, chauffeur_id, voertuig_id, datum_start, datum_eind, instructies, status) 
        VALUES (NULL, ?, ?, ?, ?, ?, 'gepland')");
    
    $stmt_rit->execute([
        $sjabloon['chauffeur_id'],
        $sjabloon['voertuig_id'],
        $start_datetime,
        $eind_datetime,
        $sjabloon['notities']
    ]);

    // Vraag aan de database: "Welk bonnetje (ID) heeft deze nieuwe rit zojuist gekregen?"
    $nieuwe_rit_id = $pdo->lastInsertId();

    // --- DEEL B: Hang de adressen eraan in de tabel 'ritregels' ---
    $stmt_regel = $pdo->prepare("INSERT INTO ritregels 
        (rit_id, tijd, omschrijving, van_adres, naar_adres) 
        VALUES (?, ?, ?, ?, ?)");
    
    $stmt_regel->execute([
        $nieuwe_rit_id,
        $sjabloon['vertrektijd'],
        $sjabloon['naam'],
        $sjabloon['ophaaladres'],
        $sjabloon['bestemming']
    ]);

    $aantal_aangemaakt++;
}

// 5. Klaar! Stuur de planner terug met een succesmelding
header("Location: overzicht.php?msg=uitgerold&aantal=" . $aantal_aangemaakt);
exit;