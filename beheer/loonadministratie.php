<?php
// Bestand: beheer/loonadministratie.php
// VERSIE: Kantoor - Loonadministratie Dashboard (Stap 5.2: Inclusief Checkboxes voor Mailen)

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

include 'includes/header.php';

try {
    $stmt_maanden = $pdo->prepare('
        SELECT DISTINCT MONTH(l.datum) as maand, YEAR(l.datum) as jaar
        FROM loon_uren l
        INNER JOIN chauffeurs c ON c.id = l.chauffeur_id AND c.tenant_id = ?
        ORDER BY jaar DESC, maand DESC
    ');
    $stmt_maanden->execute([$tenantId]);
    $gewerkte_maanden = $stmt_maanden->fetchAll();

    $maand_namen = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];

    $verlonings_opties = [];
    foreach ($gewerkte_maanden as $gm) {
        $v_maand = $gm['maand'] + 1;
        $v_jaar = $gm['jaar'];
        if ($v_maand == 13) { $v_maand = 1; $v_jaar++; } 
        
        $verlonings_opties[] = [
            'verloning_maand' => $v_maand,
            'verloning_jaar' => $v_jaar,
            'werk_maand' => $gm['maand'],
            'werk_jaar' => $gm['jaar']
        ];
    }

    $huidige_verloning_maand = isset($_GET['v_maand']) ? (int)$_GET['v_maand'] : ($verlonings_opties[0]['verloning_maand'] ?? date('n'));
    $huidige_verloning_jaar = isset($_GET['v_jaar']) ? (int)$_GET['v_jaar'] : ($verlonings_opties[0]['verloning_jaar'] ?? date('Y'));

    $zoek_werk_maand = $huidige_verloning_maand - 1;
    $zoek_werk_jaar = $huidige_verloning_jaar;
    if ($zoek_werk_maand == 0) { $zoek_werk_maand = 12; $zoek_werk_jaar--; }

    $query = "
        SELECT 
            c.id as chauffeur_id,
            c.voornaam, 
            c.achternaam,
            c.email,
            c.contracturen,
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
        WHERE c.archief = 0 AND c.tenant_id = ?
        GROUP BY c.id
        ORDER BY c.voornaam ASC, c.achternaam ASC
    ";
    $stmt_data = $pdo->prepare($query);
    $stmt_data->execute([$zoek_werk_maand, $zoek_werk_jaar, $tenantId]);
    $loon_data = $stmt_data->fetchAll();

} catch (PDOException $e) {
    die("<div style='padding:30px; font-family:sans-serif;'><h2 style='color:red;'>❌ Database Fout!</h2><p>" . $e->getMessage() . "</p></div>");
}
?>

<style>
    .dashboard-container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .header-acties { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .btn-import { background: #003366; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 13px; border: none; cursor: pointer; }
    .btn-import:hover { background: #002244; color: white; }
    .btn-verzenden { background: #e83e8c; }
    .btn-verzenden:hover { background: #d02c77; }
    
    .filter-box { background: #f4f7f6; padding: 10px 15px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block; }
    .filter-box select { padding: 6px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; margin-right: 10px; }
    .btn-filter { background: #28a745; color: white; border: none; padding: 7px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    
    .loon-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
    .loon-table th, .loon-table td { border: 1px solid #ddd; padding: 5px 6px; text-align: center; }
    .loon-table th { background: #003366; color: white; font-size: 11px; white-space: nowrap; }
    .loon-table th.groep-normaal { background: #004080; }
    .loon-table th.groep-ov { background: #6f42c1; }
    .loon-table td.text-left { text-align: left; font-weight: bold; color: #003366; white-space: nowrap; }
    .loon-table tr:nth-child(even) { background-color: #f9f9f9; }
    .loon-table tr:hover { background-color: #f1f5f9; }
    
    .nul-waarde { color: #ccc; }
    .badge-contract { background: #e2e8f0; padding: 2px 4px; border-radius: 4px; font-size: 10px; color: #333; font-weight: normal; }
    .col-meeruren { background-color: #fff3cd; font-weight: bold; color: #856404; }
    .badge-ziek { background: #dc3545; color: white; padding: 2px 4px; border-radius: 3px; font-size: 9px; margin-left: 5px; }
    .col-bijzonderheden { font-style: italic; color: #0056b3; text-align: left !important; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    
    .btn-detail { background: #17a2b8; color: white; padding: 5px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold; display: inline-block; white-space: nowrap; margin-bottom: 2px; }
    .btn-detail:hover { background: #138496; }
    
    .checkbox-cell { width: 30px; }
    input[type=checkbox] { transform: scale(1.2); cursor: pointer; }
</style>

<div class="container" style="max-width: 1400px; margin: auto; padding: 15px;">
    
    <div class="dashboard-container">
        <div class="header-acties">
            <h2 style="color: #003366; margin: 0; font-size: 20px;"><i class="fas fa-file-invoice-dollar"></i> Loonadministratie Dashboard</h2>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a target="_blank" href="chauffeur_loon.php?v_maand=<?php echo $huidige_verloning_maand; ?>&v_jaar=<?php echo $huidige_verloning_jaar; ?>" class="btn-import" style="background: #6f42c1;"><i class="fas fa-print"></i> Alle Overzichten PDF</a>
                <a href="loon_export.php?v_maand=<?php echo $huidige_verloning_maand; ?>&v_jaar=<?php echo $huidige_verloning_jaar; ?>" class="btn-import" style="background: #28a745;"><i class="fas fa-file-excel"></i> Export</a>
                <a href="loon_import.php" class="btn-import" style="background: #003366;"><i class="fas fa-file-upload"></i> Import</a>
            </div>
        </div>
        
        <div class="filter-box">
            <form method="GET" action="">
                <label style="font-weight: bold; margin-right: 10px; font-size: 13px;">Verloningsmaand:</label>
                <select name="v_maand">
                    <?php foreach($maand_namen as $num => $naam): ?>
                        <option value="<?php echo $num; ?>" <?php if($num == $huidige_verloning_maand) echo 'selected'; ?>><?php echo $naam; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="v_jaar">
                    <?php 
                    $jaren_lijst = [];
                    foreach($verlonings_opties as $vo) { $jaren_lijst[$vo['verloning_jaar']] = $vo['verloning_jaar']; }
                    if(empty($jaren_lijst)) $jaren_lijst[date('Y')] = date('Y');
                    rsort($jaren_lijst);
                    foreach($jaren_lijst as $j): ?>
                        <option value="<?php echo $j; ?>" <?php if($j == $huidige_verloning_jaar) echo 'selected'; ?>><?php echo $j; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter">Toon</button>
            </form>
        </div>
    </div>

    <div class="dashboard-container">
        
        <?php if(count($loon_data) > 0): ?>
            <form action="loon_mail_verzenden.php" method="POST" id="mailForm">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px;">
                    <div>
                        <h3 style="margin-top: 0; color: #333; margin-bottom: 5px; font-size: 16px;">
                            Verloning: <?php echo $maand_namen[$huidige_verloning_maand] . ' ' . $huidige_verloning_jaar; ?>
                        </h3>
                        <p style="color: #666; margin-top: 0; font-style: italic; font-size: 12px;">(Gebaseerd op ritten van <?php echo $maand_namen[$zoek_werk_maand] . ' ' . $zoek_werk_jaar; ?>)</p>
                    </div>
                    
                    <input type="hidden" name="v_maand" value="<?php echo $huidige_verloning_maand; ?>">
                    <input type="hidden" name="v_jaar" value="<?php echo $huidige_verloning_jaar; ?>">
                    
                    <button type="submit" class="btn-import btn-verzenden" onclick="return confirm('Weet je zeker dat je de geselecteerde chauffeurs een mail wilt sturen met hun beveiligde link?');">
                        <i class="fas fa-paper-plane"></i> Verzend Geselecteerde Mails
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table class="loon-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="checkbox-cell">
                                    <input type="checkbox" id="checkAll" title="Selecteer alles">
                                </th>
                                <th rowspan="2" class="text-left">Chauffeur</th>
                                <th rowspan="2" style="background:#17a2b8;">Contr.</th>
                                <th rowspan="2" style="background:#6c757d;">Dgn</th>
                                <th rowspan="2" style="background:#6c757d;">Gew.</th>
                                <th rowspan="2" style="background:#28a745; font-size:12px;">Basis</th>
                                <th rowspan="2" style="background:#ffc107; color:#333; font-size:12px;">Meer</th>
                                <th colspan="3" class="groep-normaal">Normaal</th>
                                <th colspan="3" class="groep-ov">OV</th>
                                <th rowspan="2" style="background:#d97706;">Ond.</th>
                                <th rowspan="2" style="background:#6c757d;">Opmerk.</th>
                                <th rowspan="2" style="background:#003366; border-left: 2px solid #fff;">Acties</th>
                            </tr>
                            <tr>
                                <th class="groep-normaal">Nacht</th>
                                <th class="groep-normaal">Zat.</th>
                                <th class="groep-normaal">Z/F</th>
                                <th class="groep-ov">Nacht</th>
                                <th class="groep-ov">Zat.</th>
                                <th class="groep-ov">Zon.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($loon_data as $rij): 
                                $contract = (float)$rij['contracturen'];
                                $gewerkt = (float)$rij['totaal_gewerkt'];
                                $dagen = (int)$rij['totaal_dagen'];
                                
                                if ($contract > 0) {
                                    $basis_uren = $contract; 
                                    $meeruren = max(0, $gewerkt - $contract);
                                } else {
                                    $basis_uren = $gewerkt;
                                    $meeruren = 0;
                                }
                                
                                // Check of er een email is ingevuld, zo nee: vinkje uitschakelen (disabled)
                                $heeft_email = !empty($rij['email']);
                            ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <?php if($heeft_email): ?>
                                            <input type="checkbox" name="mail_chauffeurs[]" value="<?php echo $rij['chauffeur_id']; ?>" class="chauffeurCheckbox">
                                        <?php else: ?>
                                            <input type="checkbox" disabled title="Geen e-mailadres bekend!">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-left">
                                        <?php echo htmlspecialchars($rij['voornaam'] . ' ' . $rij['achternaam']); ?>
                                        <?php if(!$heeft_email): ?>
                                            <span style="color:red; font-size:10px; margin-left:5px;" title="Geen emailadres in de database!"><i class="fas fa-exclamation-triangle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if($contract > 0): ?>
                                            <span class="badge-contract"><?php echo number_format($contract, 2, ',', ''); ?></span>
                                        <?php else: ?>
                                            <span class="badge-contract">0</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="font-weight: bold; color: #333;"><?php echo $dagen; ?></td>
                                    <td style="color: #666;"><?php echo number_format($gewerkt, 2, ',', ''); ?></td>
                                    <td style="font-weight: bold; color: #155724;"><?php echo number_format($basis_uren, 2, ',', ''); ?></td>
                                    <td class="<?php echo ($meeruren > 0) ? 'col-meeruren' : 'nul-waarde'; ?>">
                                        <?php echo number_format($meeruren, 2, ',', ''); ?>
                                    </td>
                                    
                                    <td class="<?php echo ($rij['totaal_avond'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_avond'], 2, ',', ''); ?></td>
                                    <td class="<?php echo ($rij['totaal_weekend'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_weekend'], 2, ',', ''); ?></td>
                                    <td class="<?php echo ($rij['totaal_zon_feest'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_zon_feest'], 2, ',', ''); ?></td>
                                    
                                    <td class="<?php echo ($rij['totaal_ov_nacht'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_ov_nacht'], 2, ',', ''); ?></td>
                                    <td class="<?php echo ($rij['totaal_ov_zat'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_ov_zat'], 2, ',', ''); ?></td>
                                    <td class="<?php echo ($rij['totaal_ov_zon'] > 0) ? '' : 'nul-waarde'; ?>"><?php echo number_format($rij['totaal_ov_zon'], 2, ',', ''); ?></td>
                                    
                                    <td style="font-weight: bold; color: <?php echo ($rij['totaal_onderbreking'] > 0) ? '#d97706' : '#ccc'; ?>;">
                                        <?php echo ($rij['totaal_onderbreking'] > 0) ? $rij['totaal_onderbreking'] : '0'; ?>
                                    </td>
                                    
                                    <td class="col-bijzonderheden" title="<?php echo htmlspecialchars($rij['bijzonderheden'] ?? ''); ?>">
                                        <?php echo !empty($rij['bijzonderheden']) ? htmlspecialchars($rij['bijzonderheden']) : '<span class="nul-waarde">-</span>'; ?>
                                    </td>
                                    
                                    <td style="border-left: 2px solid #ddd; white-space: nowrap; text-align: left;">
                                        <a target="_blank" href="chauffeur_loon.php?v_maand=<?php echo $huidige_verloning_maand; ?>&v_jaar=<?php echo $huidige_verloning_jaar; ?>&chauffeur_id=<?php echo $rij['chauffeur_id']; ?>" class="btn-detail" style="background: #6f42c1;" title="Print of PDF"><i class="fas fa-file-pdf"></i> PDF</a>
                                        <a href="loon_details.php?chauffeur_id=<?php echo $rij['chauffeur_id']; ?>&maand=<?php echo $zoek_werk_maand; ?>&jaar=<?php echo $zoek_werk_jaar; ?>" class="btn-detail" title="Details / Notities"><i class="fas fa-pen"></i> Wijzig</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <script>
                document.getElementById('checkAll').addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('.chauffeurCheckbox');
                    for (var i = 0; i < checkboxes.length; i++) {
                        if(!checkboxes[i].disabled) {
                            checkboxes[i].checked = this.checked;
                        }
                    }
                });
            </script>
            
        <?php else: ?>
            <div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 5px; margin-top: 15px; font-size: 13px;">
                Geen uren gevonden. Controleer de geselecteerde verloningsmaand.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>