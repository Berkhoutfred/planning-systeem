<?php
// Bestand: beheer/mijn_uren.php
// Doel: Beveiligde landingspagina voor chauffeurs om hun eigen uren in te zien (Mobielvriendelijk)

// LET OP: We includen expres GEEN '../beveiliging.php' en 'includes/header.php'! 
// Deze pagina moet toegankelijk zijn zonder in te loggen, maar is beveiligd via het token.

require 'includes/db.php';

// Haal de gegevens uit de link
$chauffeur_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token_uit_link = isset($_GET['token']) ? $_GET['token'] : '';
$v_maand = isset($_GET['m']) ? (int)$_GET['m'] : 0;
$v_jaar = isset($_GET['j']) ? (int)$_GET['j'] : 0;

$maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
$dagen_nl = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

// BEVEILIGINGSCHECK: Klopt het ID en het token?
$foutmelding = "";
if ($chauffeur_id === 0 || empty($token_uit_link) || $v_maand === 0 || $v_jaar === 0) {
    $foutmelding = "Ongeldige of onvolledige link. Vraag een nieuwe link aan bij de administratie.";
} else {
    $stmt_check = $pdo->prepare("SELECT id, voornaam, achternaam, token FROM chauffeurs WHERE id = ? AND archief = 0");
    $stmt_check->execute([$chauffeur_id]);
    $chauffeur = $stmt_check->fetch();

    if (!$chauffeur) {
        $foutmelding = "Chauffeur niet gevonden of inactief.";
    } elseif ($chauffeur['token'] !== $token_uit_link) {
        $foutmelding = "Deze link is verlopen of ongeldig. Vraag een nieuwe link aan bij de administratie.";
    }
}

// Als er GEEN foutmelding is, gaan we de uren ophalen!
$ritten = [];
$totalen = ['basis' => 0, 'avond' => 0, 'weekend' => 0, 'zon' => 0, 'ov_nacht' => 0, 'ov_zat' => 0, 'ov_zon' => 0, 'onderbreking' => 0];

if (empty($foutmelding)) {
    // Bepaal de échte werkmaand (zoals in je dashboard)
    $werk_maand = $v_maand - 1;
    $werk_jaar = $v_jaar;
    if ($werk_maand == 0) { $werk_maand = 12; $werk_jaar--; }

    $query = "
        SELECT datum, type_vervoer, uren_basis, toeslag_avond, toeslag_weekend, toeslag_zon_feest, toeslag_ov_avond_nacht, toeslag_ov_zaterdag, toeslag_ov_zondag, onderbreking_aantal, notities
        FROM loon_uren 
        WHERE chauffeur_id = ? AND MONTH(datum) = ? AND YEAR(datum) = ?
        ORDER BY datum ASC
    ";
    $stmt_uren = $pdo->prepare($query);
    $stmt_uren->execute([$chauffeur_id, $werk_maand, $werk_jaar]);
    $ritten = $stmt_uren->fetchAll();

    foreach ($ritten as $rit) {
        $totalen['basis'] += $rit['uren_basis'];
        $totalen['avond'] += $rit['toeslag_avond'];
        $totalen['weekend'] += $rit['toeslag_weekend'];
        $totalen['zon'] += $rit['toeslag_zon_feest'];
        $totalen['ov_nacht'] += $rit['toeslag_ov_avond_nacht'];
        $totalen['ov_zat'] += $rit['toeslag_ov_zaterdag'];
        $totalen['ov_zon'] += $rit['toeslag_ov_zondag'];
        $totalen['onderbreking'] += $rit['onderbreking_aantal'];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Mijn Uren - BusAI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header-card { background: #003366; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header-card h1 { margin: 0 0 5px 0; font-size: 22px; }
        .header-card p { margin: 0; color: #a9d0f5; font-size: 14px; }
        
        .error-card { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 5px solid #dc3545; margin-bottom: 20px; text-align: center; }
        
        .rit-card { background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid #28a745; }
        .rit-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .rit-datum { font-weight: bold; font-size: 16px; color: #003366; }
        .rit-type { font-size: 12px; background: #e2e8f0; padding: 3px 8px; border-radius: 12px; color: #555; font-weight: bold; }
        .rit-type.ov { background: #6f42c1; color: white; }
        
        .rit-detail { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .rit-detail.basis { font-weight: bold; color: #155724; font-size: 15px; }
        .toeslag-badge { display: inline-block; background: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 3px; font-size: 12px; margin: 2px 2px 0 0; border: 1px solid #ffeeba; }
        .onderbreking { color: #d97706; font-weight: bold; }
        .notitie { margin-top: 10px; padding: 8px; background: #f8f9fa; border-left: 3px solid #17a2b8; font-size: 13px; font-style: italic; color: #555; }
        
        .totaal-card { background: #003366; color: white; padding: 20px; border-radius: 8px; margin-top: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .totaal-card h2 { margin: 0 0 15px 0; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 10px; font-size: 18px; }
        .totaal-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 15px; }
        .totaal-row.hoofd { font-weight: bold; font-size: 18px; color: #28a745; margin-bottom: 15px; }
        .totaal-row.toeslag { color: #a9d0f5; font-size: 14px; }
        
        .footer { text-align: center; margin-top: 30px; padding-bottom: 20px; font-size: 12px; color: #888; }
        
        /* Specifieke optimalisatie voor telefoons */
        @media (max-width: 600px) {
            .container { padding: 10px; }
            .header-card h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <?php if (!empty($foutmelding)): ?>
        <div class="error-card">
            <h2 style="margin-top:0;"><i class="fas fa-lock"></i> Toegang Geweigerd</h2>
            <p><?php echo htmlspecialchars($foutmelding); ?></p>
        </div>
    <?php else: ?>
        
        <div class="header-card">
            <h1><?php echo htmlspecialchars($chauffeur['voornaam'] . ' ' . $chauffeur['achternaam']); ?></h1>
            <p>Urenoverzicht: <?php echo $maand_namen[$v_maand] . ' ' . $v_jaar; ?></p>
            <span style="font-size: 11px; color: rgba(255,255,255,0.5);">(Ritten van: <?php echo $maand_namen[$werk_maand] . ' ' . $werk_jaar; ?>)</span>
        </div>

        <?php if (empty($ritten)): ?>
            <div style="text-align:center; padding: 40px; background:white; border-radius:8px;">
                <h3 style="color:#666;">Geen ritten gevonden</h3>
                <p style="color:#999;">Er staan geen uren geregistreerd voor deze periode.</p>
            </div>
        <?php else: ?>
            
            <?php foreach ($ritten as $rit): 
                $dag_naam = $dagen_nl[date('N', strtotime($rit['datum'])) - 1] ?? '';
            ?>
                <div class="rit-card">
                    <div class="rit-header">
                        <div class="rit-datum">
                            <?php echo $dag_naam . ' ' . date('d-m-Y', strtotime($rit['datum'])); ?>
                        </div>
                        <div class="rit-type <?php echo $rit['type_vervoer'] == 'OV' ? 'ov' : ''; ?>">
                            <?php echo $rit['type_vervoer'] == 'OV' ? 'Openbaar Vervoer' : 'Normaal Vervoer'; ?>
                        </div>
                    </div>
                    
                    <div class="rit-detail basis">
                        <span>Basis Uren:</span>
                        <span><?php echo number_format($rit['uren_basis'], 2, ',', ''); ?> u</span>
                    </div>
                    
                    <?php if ($rit['onderbreking_aantal'] > 0): ?>
                    <div class="rit-detail onderbreking">
                        <span>Onderbrekingen:</span>
                        <span><?php echo $rit['onderbreking_aantal']; ?>x</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Toeslagen verzamelen
                    $heeft_toeslag = false;
                    ob_start(); // We vangen de output even op om te kijken of er iets is
                    if ($rit['toeslag_avond'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_avond'], 2, ',', '') . "u Nacht</span> "; $heeft_toeslag = true; }
                    if ($rit['toeslag_weekend'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_weekend'], 2, ',', '') . "u Zat.</span> "; $heeft_toeslag = true; }
                    if ($rit['toeslag_zon_feest'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_zon_feest'], 2, ',', '') . "u Zon/Feest</span> "; $heeft_toeslag = true; }
                    if ($rit['toeslag_ov_avond_nacht'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_avond_nacht'], 2, ',', '') . "u Nacht (OV)</span> "; $heeft_toeslag = true; }
                    if ($rit['toeslag_ov_zaterdag'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_zaterdag'], 2, ',', '') . "u Zat. (OV)</span> "; $heeft_toeslag = true; }
                    if ($rit['toeslag_ov_zondag'] > 0) { echo "<span class='toeslag-badge'>" . number_format($rit['toeslag_ov_zondag'], 2, ',', '') . "u Zon. (OV)</span> "; $heeft_toeslag = true; }
                    $toeslag_html = ob_get_clean();
                    
                    if ($heeft_toeslag): 
                    ?>
                        <div style="margin-top: 8px;">
                            <?php echo $toeslag_html; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($rit['notities'])): ?>
                        <div class="notitie">
                            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($rit['notities']); ?>
                        </div>
                    <?php endif; ?>
                    
                </div>
            <?php endforeach; ?>
            
            <div class="totaal-card">
                <h2>Totaal overzicht van de maand</h2>
                
                <div class="totaal-row hoofd">
                    <span>Totaal Gewerkte Uren:</span>
                    <span><?php echo number_format($totalen['basis'], 2, ',', ''); ?> u</span>
                </div>
                
                <?php if($totalen['avond'] > 0): ?> <div class="totaal-row toeslag"><span>Toeslag Nacht:</span><span><?php echo number_format($totalen['avond'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                <?php if($totalen['weekend'] > 0): ?> <div class="totaal-row toeslag"><span>Toeslag Zaterdag:</span><span><?php echo number_format($totalen['weekend'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                <?php if($totalen['zon'] > 0): ?> <div class="totaal-row toeslag"><span>Toeslag Zon/Feest:</span><span><?php echo number_format($totalen['zon'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                
                <?php if($totalen['ov_nacht'] > 0): ?> <div class="totaal-row toeslag"><span>OV Toeslag Nacht:</span><span><?php echo number_format($totalen['ov_nacht'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                <?php if($totalen['ov_zat'] > 0): ?> <div class="totaal-row toeslag"><span>OV Toeslag Zaterdag:</span><span><?php echo number_format($totalen['ov_zat'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                <?php if($totalen['ov_zon'] > 0): ?> <div class="totaal-row toeslag"><span>OV Toeslag Zondag:</span><span><?php echo number_format($totalen['ov_zon'], 2, ',', ''); ?> u</span></div> <?php endif; ?>
                
                <?php if($totalen['onderbreking'] > 0): ?> <div class="totaal-row toeslag" style="color:#f39c12;"><span>Onderbrekingen:</span><span><?php echo $totalen['onderbreking']; ?>x</span></div> <?php endif; ?>
            </div>
            
        <?php endif; ?>
        
        <div class="footer">
            <p><strong>Kloppen deze uren niet?</strong><br>Neem dan zo snel mogelijk contact op met de administratie van BusAI door te reageren op de e-mail.</p>
            <p>&copy; <?php echo date('Y'); ?> BusAI. Alle rechten voorbehouden.</p>
        </div>

    <?php endif; ?>
    
</div>

</body>
</html>