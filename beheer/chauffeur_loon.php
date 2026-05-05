<?php
// Bestand: beheer/chauffeur_loon.php
// Doel: Printbare urenoverzichten per chauffeur (of allemaal) genereren

include '../beveiliging.php';
require 'includes/db.php';

$v_maand = isset($_GET['v_maand']) ? (int)$_GET['v_maand'] : date('n');
$v_jaar = isset($_GET['v_jaar']) ? (int)$_GET['v_jaar'] : date('Y');
$chauffeur_id = isset($_GET['chauffeur_id']) ? (int)$_GET['chauffeur_id'] : 0; // NIEUW: Luister naar specifieke chauffeur

$maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
$dagen_nl = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

// Bepaal de échte maand waarin is gewerkt
$werk_maand = $v_maand - 1;
$werk_jaar = $v_jaar;
if ($werk_maand == 0) { $werk_maand = 12; $werk_jaar--; }

// Haal uren op (voor één chauffeur óf allemaal)
$query = "
    SELECT c.id, c.voornaam, c.achternaam, l.datum, l.type_vervoer, l.uren_basis, l.toeslag_avond, l.toeslag_weekend, l.toeslag_zon_feest, l.toeslag_ov_avond_nacht, l.toeslag_ov_zaterdag, l.toeslag_ov_zondag, l.onderbreking_aantal, l.notities
    FROM loon_uren l
    JOIN chauffeurs c ON l.chauffeur_id = c.id
    WHERE MONTH(l.datum) = ? AND YEAR(l.datum) = ?
";

$params = [$werk_maand, $werk_jaar];

// Als er een specifieke chauffeur is aangeklikt, voeg dit toe aan de zoekopdracht
if ($chauffeur_id > 0) {
    $query .= " AND c.id = ?";
    $params[] = $chauffeur_id;
}

$query .= " ORDER BY c.voornaam ASC, c.achternaam ASC, l.datum ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alle_ritten = $stmt->fetchAll();

// Sorteer ze netjes per chauffeur in een bakje
$chauffeurs_data = [];
foreach ($alle_ritten as $rit) {
    $cid = $rit['id'];
    if (!isset($chauffeurs_data[$cid])) {
        $chauffeurs_data[$cid] = [
            'naam' => $rit['voornaam'] . ' ' . $rit['achternaam'],
            'ritten' => [],
            'totalen' => [
                'basis' => 0, 'avond' => 0, 'weekend' => 0, 'zon' => 0,
                'ov_nacht' => 0, 'ov_zat' => 0, 'ov_zon' => 0, 'onderbreking' => 0
            ]
        ];
    }
    $chauffeurs_data[$cid]['ritten'][] = $rit;
    
    // Tellingen updaten
    $chauffeurs_data[$cid]['totalen']['basis'] += $rit['uren_basis'];
    $chauffeurs_data[$cid]['totalen']['avond'] += $rit['toeslag_avond'];
    $chauffeurs_data[$cid]['totalen']['weekend'] += $rit['toeslag_weekend'];
    $chauffeurs_data[$cid]['totalen']['zon'] += $rit['toeslag_zon_feest'];
    $chauffeurs_data[$cid]['totalen']['ov_nacht'] += $rit['toeslag_ov_avond_nacht'];
    $chauffeurs_data[$cid]['totalen']['ov_zat'] += $rit['toeslag_ov_zaterdag'];
    $chauffeurs_data[$cid]['totalen']['ov_zon'] += $rit['toeslag_ov_zondag'];
    $chauffeurs_data[$cid]['totalen']['onderbreking'] += $rit['onderbreking_aantal'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Urenoverzicht <?php echo $chauffeur_id > 0 && !empty($chauffeurs_data) ? reset($chauffeurs_data)['naam'] : 'Chauffeurs'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn-print { background: #6f42c1; color: white; padding: 12px 25px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; font-weight: bold; }
        .btn-print:hover { background: #59339d; }
        
        .page { background: white; max-width: 900px; margin: 0 auto 40px auto; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #003366; padding-bottom: 20px; margin-bottom: 20px; }
        .header-links h1 { margin: 0; color: #003366; font-size: 24px; text-transform: uppercase; }
        .header-links p { margin: 5px 0 0 0; color: #666; font-size: 14px; }
        .header-rechts { text-align: right; }
        .header-rechts h2 { margin: 0; color: #333; font-size: 20px; }
        .header-rechts p { margin: 5px 0 0 0; font-weight: bold; color: #17a2b8; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        th, td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; }
        th { background: #f8f9fa; color: #003366; font-weight: bold; }
        
        .tot-row td { background: #003366; color: white; font-weight: bold; font-size: 14px; }
        .toeslag-badge { display: inline-block; background: #e2e8f0; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin: 1px 0; color:#333; }
        
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none; }
            .page { box-shadow: none; margin: 0; padding: 20px; max-width: 100%; page-break-after: always; border-radius: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Opslaan als PDF / Printen</button>
        <p style="margin-top: 10px; color: #666;">Tip: Kies bij bestemming 'Opslaan als PDF' in het printscherm om dit document digitaal te kunnen mailen!</p>
    </div>

    <?php if (empty($chauffeurs_data)): ?>
        <div class="page" style="text-align: center;">
            <h2 style="color: #dc3545;">Geen ritten gevonden!</h2>
            <p>Er zijn geen ritten gevonden voor deze selectie in <?php echo $maand_namen[$werk_maand] . ' ' . $werk_jaar; ?>.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($chauffeurs_data as $data): ?>
    <div class="page">
        <div class="header">
            <div class="header-links">
                <h1>BusAI</h1>
                <p>Specificatie Gewerkte Uren</p>
            </div>
            <div class="header-rechts">
                <h2><?php echo htmlspecialchars($data['naam']); ?></h2>
                <p>Verloningsmaand: <?php echo $maand_namen[$v_maand] . ' ' . $v_jaar; ?></p>
                <span style="font-size:12px; color:#999;">(Rittenperiode: <?php echo $maand_namen[$werk_maand] . ' ' . $werk_jaar; ?>)</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Dag</th>
                    <th>Type Vervoer</th>
                    <th style="text-align:center;">Basis Uren</th>
                    <th>Toeslagen / Opmerkingen</th>
                    <th style="text-align:center;">Onderb.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['ritten'] as $rit): 
                    $dag_naam = $dagen_nl[date('N', strtotime($rit['datum'])) - 1] ?? '';
                ?>
                <tr>
                    <td><?php echo date('d-m-Y', strtotime($rit['datum'])); ?></td>
                    <td style="text-transform: capitalize;"><?php echo strftime('%A', strtotime($rit['datum'])); ?></td>
                    <td><?php echo $rit['type_vervoer'] == 'OV' ? '<strong>OV</strong>' : 'Normaal'; ?></td>
                    <td style="text-align:center; font-weight:bold;"><?php echo number_format($rit['uren_basis'], 2, ',', ''); ?></td>
                    <td>
                        <?php 
                        if ($rit['toeslag_avond'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_avond'], 2, ',', '') . "u (Nacht)</span> ";
                        if ($rit['toeslag_weekend'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_weekend'], 2, ',', '') . "u (Zaterdag)</span> ";
                        if ($rit['toeslag_zon_feest'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_zon_feest'], 2, ',', '') . "u (Zon/Feest)</span> ";
                        
                        if ($rit['toeslag_ov_avond_nacht'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_avond_nacht'], 2, ',', '') . "u (Nacht OV)</span> ";
                        if ($rit['toeslag_ov_zaterdag'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_zaterdag'], 2, ',', '') . "u (Zaterdag OV)</span> ";
                        if ($rit['toeslag_ov_zondag'] > 0) echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_zondag'], 2, ',', '') . "u (Zondag OV)</span> ";
                        
                        if (!empty($rit['notities'])) {
                            echo "<br><span style='font-style:italic; color:#0056b3; font-size:11px;'>Notitie: " . htmlspecialchars($rit['notities']) . "</span>";
                        }
                        if ($rit['toeslag_avond'] == 0 && $rit['toeslag_weekend'] == 0 && $rit['toeslag_zon_feest'] == 0 && $rit['toeslag_ov_avond_nacht'] == 0 && $rit['toeslag_ov_zaterdag'] == 0 && $rit['toeslag_ov_zondag'] == 0 && empty($rit['notities'])) {
                            echo "<span style='color:#ccc;'>-</span>";
                        }
                        ?>
                    </td>
                    <td style="text-align:center; color:#d97706; font-weight:bold;">
                        <?php echo $rit['onderbreking_aantal'] > 0 ? $rit['onderbreking_aantal'] . 'x' : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="tot-row">
                    <td colspan="3" style="text-align: right;">TOTAAL DEZE MAAND:</td>
                    <td style="text-align:center;"><?php echo number_format($data['totalen']['basis'], 2, ',', ''); ?> u</td>
                    <td colspan="2">
                        <span style="font-size:11px; font-weight:normal; display:block;">
                            <?php 
                            $t = $data['totalen'];
                            $tot_str = [];
                            if($t['avond'] > 0) $tot_str[] = number_format($t['avond'], 2, ',', '')."u Nacht";
                            if($t['weekend'] > 0) $tot_str[] = number_format($t['weekend'], 2, ',', '')."u Zaterdag";
                            if($t['zon'] > 0) $tot_str[] = number_format($t['zon'], 2, ',', '')."u Zon/Feest";
                            if($t['ov_nacht'] > 0) $tot_str[] = number_format($t['ov_nacht'], 2, ',', '')."u Nacht(OV)";
                            if($t['ov_zat'] > 0) $tot_str[] = number_format($t['ov_zat'], 2, ',', '')."u Zat(OV)";
                            if($t['ov_zon'] > 0) $tot_str[] = number_format($t['ov_zon'], 2, ',', '')."u Zon(OV)";
                            if($t['onderbreking'] > 0) $tot_str[] = $t['onderbreking']."x Onderbreking";
                            
                            echo empty($tot_str) ? 'Geen toeslagen' : implode(" | ", $tot_str);
                            ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #777;">
            <p>Dit is een digitaal gegenereerd urenoverzicht. Controleer deze uren zorgvuldig.</p>
        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>