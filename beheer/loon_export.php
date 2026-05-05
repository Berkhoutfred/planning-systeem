<?php
// Bestand: beheer/loon_export.php
// VERSIE: Kantoor - Loonadministratie Export (Stap 6.1: CSV Generatie)

require 'includes/db.php';

// Welke maand hebben we doorgekregen van het dashboard?
$v_maand = isset($_GET['v_maand']) ? (int)$_GET['v_maand'] : date('n');
$v_jaar = isset($_GET['v_jaar']) ? (int)$_GET['v_jaar'] : date('Y');

$werk_maand = $v_maand - 1;
$werk_jaar = $v_jaar;
if ($werk_maand == 0) { $werk_maand = 12; $werk_jaar--; }

$maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
$bestandsnaam = "Loon_Export_" . $maand_namen[$v_maand] . "_" . $v_jaar . ".csv";

// Vertel de browser dat we een CSV bestand gaan downloaden
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $bestandsnaam);

$output = fopen('php://output', 'w');

// BOM (Byte Order Mark) toevoegen zodat Excel de speciale tekens (zoals €) goed leest
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// De kolomnamen (we gebruiken de puntkomma ';' zodat de Nederlandse Excel het direct snapt)
fputcsv($output, ['Chauffeur', 'Contracturen', 'Dagen', 'Totaal Gewerkt', 'Basis Uren', 'Meeruren', 'Avond', 'Weekend', 'Zon/Feest', 'OV Nacht', 'OV Zaterdag', 'OV Zondag', 'Onderbrekingen', 'Bijzonderheden'], ';');

// Haal exact dezelfde data op als op het dashboard
$query = "
    SELECT 
        c.voornaam, c.achternaam, c.contracturen,
        COUNT(l.id) as totaal_dagen,
        COALESCE(SUM(l.uren_basis), 0) as totaal_gewerkt,
        COALESCE(SUM(l.toeslag_avond), 0) as totaal_avond,
        COALESCE(SUM(l.toeslag_weekend), 0) as totaal_weekend,
        COALESCE(SUM(l.toeslag_zon_feest), 0) as totaal_zon_feest,
        COALESCE(SUM(l.toeslag_ov_avond_nacht), 0) as totaal_ov_nacht,
        COALESCE(SUM(l.toeslag_ov_zaterdag), 0) as totaal_ov_zat,
        COALESCE(SUM(l.toeslag_ov_zondag), 0) as totaal_ov_zon,
        COALESCE(SUM(l.onderbreking_aantal), 0) as totaal_onderbreking,
        GROUP_CONCAT(DISTINCT NULLIF(l.notities, '') SEPARATOR ' | ') as bijzonderheden
    FROM chauffeurs c
    LEFT JOIN loon_uren l ON c.id = l.chauffeur_id AND MONTH(l.datum) = ? AND YEAR(l.datum) = ?
    WHERE c.archief = 0
    GROUP BY c.id
    ORDER BY c.voornaam ASC, c.achternaam ASC
";
$stmt_data = $pdo->prepare($query);
$stmt_data->execute([$werk_maand, $werk_jaar]);
$loon_data = $stmt_data->fetchAll();

// Zet alle data in de rijen
foreach($loon_data as $rij) {
    $contract = (float)$rij['contracturen'];
    $gewerkt = (float)$rij['totaal_gewerkt'];
    
    if ($contract > 0) {
        $basis = $contract; 
        $meer = max(0, $gewerkt - $contract);
    } else {
        $basis = $gewerkt;
        $meer = 0;
    }

    // Alles wegschrijven met een Nederlandse komma in plaats van een punt
    fputcsv($output, [
        $rij['voornaam'] . ' ' . $rij['achternaam'],
        number_format($contract, 2, ',', ''),
        $rij['totaal_dagen'],
        number_format($gewerkt, 2, ',', ''),
        number_format($basis, 2, ',', ''),
        number_format($meer, 2, ',', ''),
        number_format($rij['totaal_avond'], 2, ',', ''),
        number_format($rij['totaal_weekend'], 2, ',', ''),
        number_format($rij['totaal_zon_feest'], 2, ',', ''),
        number_format($rij['totaal_ov_nacht'], 2, ',', ''),
        number_format($rij['totaal_ov_zat'], 2, ',', ''),
        number_format($rij['totaal_ov_zon'], 2, ',', ''),
        $rij['totaal_onderbreking'],
        $rij['bijzonderheden']
    ], ';');
}

fclose($output);
exit;
?>