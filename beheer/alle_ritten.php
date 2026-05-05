<?php
// Bestand: beheer/alle_ritten.php
// VERSIE: Rittenarchief met kascontrole (Contant, Pin, Rekening)

include '../beveiliging.php';
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt. Controleer login/tenant configuratie.');
}

$ritgegevensTenantSql = "
    EXISTS (
        SELECT 1 FROM chauffeurs c
        WHERE c.tenant_id = ? AND c.archief = 0
          AND TRIM(CONCAT(TRIM(IFNULL(c.voornaam,'')), ' ', TRIM(IFNULL(c.achternaam,'')))) COLLATE utf8mb4_unicode_ci
            = TRIM(IFNULL(rg.chauffeur_naam,'')) COLLATE utf8mb4_unicode_ci
    )
    AND EXISTS (
        SELECT 1 FROM voertuigen v
        WHERE v.tenant_id = ? AND v.archief = 0
          AND TRIM(CAST(IFNULL(v.voertuig_nummer,'') AS CHAR)) = TRIM(CAST(IFNULL(rg.voertuig_nummer,'') AS CHAR))
    )
";

// --- ACTIE: BESTAANDE RIT WIJZIGEN IN ARCHIEF ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rit_id'])) {
    $id = (int) $_POST['rit_id'];

    $chk = $pdo->prepare("SELECT 1 FROM ritgegevens rg WHERE rg.id = ? AND $ritgegevensTenantSql");
    $chk->execute([$id, $tenantId, $tenantId]);
    if (!$chk->fetchColumn()) {
        header('Location: alle_ritten.php');
        exit;
    }

    $sql = "UPDATE ritgegevens SET chauffeur_naam=?, voertuig_nummer=?, datum=?, opmerkingen=? WHERE id=?";
    $pdo->prepare($sql)->execute([$_POST['chauffeur'], $_POST['voertuig'], $_POST['datum'], $_POST['opmerkingen'], $id]);

    if ($_POST['bron_type'] === 'adhoc') {
        $prijs = str_replace(',', '.', $_POST['prijs']);
        $route_opslaan = trim($_POST['van']) . ' | ' . trim($_POST['naar']) . ' | ' . $_POST['betaalwijze'];
        $sqlAdhoc = "UPDATE ritgegevens SET type_dienst=?, adhoc_klant=?, adhoc_route=?, adhoc_prijs=? WHERE id=?";
        $pdo->prepare($sqlAdhoc)->execute([$_POST['type_dienst'], $_POST['adhoc_klant'], $route_opslaan, $prijs, $id]);
    } else {
        $pdo->prepare("UPDATE ritgegevens SET totaal_km=? WHERE id=?")->execute([$_POST['totaal_km'] ?? 0, $id]);
        if (isset($_POST['regel'])) {
            foreach ($_POST['regel'] as $regel_id => $data) {
                $regel_id = (int) $regel_id;
                $bedrag = isset($data['bedrag']) ? str_replace(',','.', $data['bedrag']) : 0;
                $km = isset($data['km']) ? $data['km'] : 0;
                $pdo->prepare('UPDATE ritregels SET tijd=?, km_stand=?, bedrag=? WHERE id=? AND rit_id=?')
                    ->execute([$data['tijd'], $km, $bedrag, $regel_id, $id]);
            }
        }
    }
    
    $q = http_build_query([
        'maand' => $_POST['f_maand'] ?? date('m'),
        'jaar' => $_POST['f_jaar'] ?? date('Y'),
        'chauffeur' => $_POST['f_chauffeur'] ?? '',
        'bus' => $_POST['f_bus'] ?? ''
    ]);
    header("Location: alle_ritten.php?" . $q);
    exit;
}

include 'includes/header.php';

// --- FILTERS OPHALEN ---
$f_maand = $_GET['maand'] ?? date('m');
$f_jaar = $_GET['jaar'] ?? date('Y');
$f_chauffeur = $_GET['chauffeur'] ?? '';
$f_bus = $_GET['bus'] ?? '';

// --- DATABASE QUERY OPBOUWEN (ritgegevens zonder tenant_id: koppel via chauffeur + voertuig binnen tenant) ---
$sql = "SELECT rg.* FROM ritgegevens rg
WHERE rg.status = 'verwerkt' AND MONTH(rg.datum) = ? AND YEAR(rg.datum) = ?
  AND $ritgegevensTenantSql";
$params = [$f_maand, $f_jaar, $tenantId, $tenantId];

if (!empty($f_chauffeur)) {
    $sql .= " AND rg.chauffeur_naam = ?";
    $params[] = $f_chauffeur;
}
if (!empty($f_bus)) {
    $sql .= " AND rg.voertuig_nummer = ?";
    $params[] = $f_bus;
}
$sql .= " ORDER BY rg.datum DESC, rg.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ritten = $stmt->fetchAll();

// --- STATISTIEKEN BEREKENEN (KASOPMAAK) ---
$totaal_omzet = 0;
$totaal_contant = 0;
$totaal_pin = 0;
$totaal_rekening = 0;

foreach ($ritten as $r) {
    if ($r['bron_type'] === 'adhoc') {
        $prijs = (float)$r['adhoc_prijs'];
        $totaal_omzet += $prijs;
        
        // Betaalwijze filteren uit de opgeslagen route
        $betaalwijze = '';
        if (strpos($r['adhoc_route'], ' | ') !== false) {
            $parts = explode(' | ', $r['adhoc_route']);
            $betaalwijze = $parts[2] ?? '';
        } elseif (strpos($r['adhoc_route'], ' (Betaling: ') !== false) {
            $parts = explode(' (Betaling: ', $r['adhoc_route']);
            $betaalwijze = str_replace(')', '', $parts[1] ?? '');
        }

        // Tellen per categorie
        if (stripos($betaalwijze, 'Contant') !== false) {
            $totaal_contant += $prijs;
        } elseif (stripos($betaalwijze, 'PIN') !== false) {
            $totaal_pin += $prijs;
        } elseif (stripos($betaalwijze, 'Rekening') !== false) {
            $totaal_rekening += $prijs;
        }
    }
}

$maanden = [1=>'Januari', 2=>'Februari', 3=>'Maart', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Augustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'December'];
$stmtChLijst = $pdo->prepare("SELECT DISTINCT rg.chauffeur_naam FROM ritgegevens rg WHERE rg.chauffeur_naam != '' AND $ritgegevensTenantSql ORDER BY rg.chauffeur_naam");
$stmtChLijst->execute([$tenantId, $tenantId]);
$chauffeurslijst = $stmtChLijst->fetchAll(PDO::FETCH_COLUMN);

$stmtBusLijst = $pdo->prepare("SELECT DISTINCT rg.voertuig_nummer FROM ritgegevens rg WHERE rg.voertuig_nummer != '' AND $ritgegevensTenantSql ORDER BY rg.voertuig_nummer");
$stmtBusLijst->execute([$tenantId, $tenantId]);
$bussenlijst = $stmtBusLijst->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="margin-bottom: 20px;">
    <h1 style="margin-bottom:5px;">🗄️ Alle Ritten (Archief)</h1>
    <p style="color: #666; font-size: 14px; margin-top:0;">Overzicht van alle definitief verwerkte ritten voor controle en facturatie.</p>
</div>

<div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; width: 100%; flex-wrap: wrap; margin: 0;">
        <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Maand</label><select name="maand" style="padding:8px; border:1px solid #ccc; border-radius:4px;"><?php foreach($maanden as $num => $naam): ?><option value="<?php echo $num; ?>" <?php if($f_maand == $num) echo 'selected'; ?>><?php echo $naam; ?></option><?php endforeach; ?></select></div>
        <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Jaar</label><select name="jaar" style="padding:8px; border:1px solid #ccc; border-radius:4px;"><?php for($j = 2024; $j <= date('Y') + 1; $j++): ?><option value="<?php echo $j; ?>" <?php if($f_jaar == $j) echo 'selected'; ?>><?php echo $j; ?></option><?php endfor; ?></select></div>
        <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Chauffeur</label><select name="chauffeur" style="padding:8px; border:1px solid #ccc; border-radius:4px;"><option value="">Alle chauffeurs</option><?php foreach($chauffeurslijst as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php if($f_chauffeur == $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
        <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Bus</label><select name="bus" style="padding:8px; border:1px solid #ccc; border-radius:4px;"><option value="">Alle bussen</option><?php foreach($bussenlijst as $b): ?><option value="<?php echo htmlspecialchars($b); ?>" <?php if($f_bus == $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option><?php endforeach; ?></select></div>
        <div>
            <button type="submit" style="background:#003366; color:white; border:none; padding:9px 20px; border-radius:4px; font-weight:bold; cursor:pointer;">🔍 Filteren</button>
            <a href="alle_ritten.php" style="color:#666; margin-left:10px; text-decoration:none; font-size:13px;">Wissen</a>
        </div>
    </form>
</div>

<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 20px;">
    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #0056b3;">
        <div style="font-size:11px; color:#666; font-weight:bold; text-transform:uppercase;">Aantal Ritten</div>
        <div style="font-size:20px; font-weight:bold; color:#003366;"><?php echo count($ritten); ?></div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #28a745;">
        <div style="font-size:11px; color:#666; font-weight:bold; text-transform:uppercase;">Geselecteerde Omzet</div>
        <div style="font-size:20px; font-weight:bold; color:#28a745;">&euro; <?php echo number_format($totaal_omzet, 2, ',', '.'); ?></div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #17a2b8;">
        <div style="font-size:11px; color:#666; font-weight:bold; text-transform:uppercase;">Hiervan Contant</div>
        <div style="font-size:20px; font-weight:bold; color:#17a2b8;">&euro; <?php echo number_format($totaal_contant, 2, ',', '.'); ?></div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #ffc107;">
        <div style="font-size:11px; color:#666; font-weight:bold; text-transform:uppercase;">Hiervan PIN</div>
        <div style="font-size:20px; font-weight:bold; color:#d39e00;">&euro; <?php echo number_format($totaal_pin, 2, ',', '.'); ?></div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #6c757d;">
        <div style="font-size:11px; color:#666; font-weight:bold; text-transform:uppercase;">Op Rekening</div>
        <div style="font-size:20px; font-weight:bold; color:#475569;">&euro; <?php echo number_format($totaal_rekening, 2, ',', '.'); ?></div>
    </div>
</div>

<div style="background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; overflow-x: auto;">
    <table style="width:100%; border-collapse:collapse; min-width: 1100px;">
        <thead style="background:#f8f9fa; color:#333; font-size: 12px; text-transform:uppercase; border-bottom:2px solid #ddd;">
            <tr>
                <th style="padding:15px; text-align:left;">Datum</th>
                <th style="padding:15px; text-align:left;">Tijd</th>
                <th style="padding:15px; text-align:left;">Van</th>
                <th style="padding:15px; text-align:left;">Naar</th>
                <th style="padding:15px; text-align:left;">Bedrag</th>
                <th style="padding:15px; text-align:left;">Betaling</th>
                <th style="padding:15px; text-align:left;">Chauffeur</th>
                <th style="padding:15px; text-align:left;">Bus</th>
                <th style="padding:15px; text-align:left;">Klantnaam</th>
                <th style="padding:15px; text-align:right;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (count($ritten) == 0): ?>
                <tr><td colspan="10" style="padding:40px; text-align:center; color:#888;">Geen verwerkte ritten gevonden.</td></tr>
            <?php endif;

            foreach ($ritten as $rit): 
                $form_id = "form_archief_" . $rit['id'];
                $isAdhoc = ($rit['bron_type'] === 'adhoc');
                
                $tijd = "-"; 
                $van = "-";
                $naar = "-";
                $betaalwijze = "-";
                $bedrag = "-";
                $klant = "-";

                if ($isAdhoc) {
                    $klant = htmlspecialchars($rit['adhoc_klant']);
                    if (strpos($rit['adhoc_route'], ' | ') !== false) {
                        $parts = explode(' | ', $rit['adhoc_route']);
                        $van = htmlspecialchars($parts[0] ?? '-');
                        $naar = htmlspecialchars($parts[1] ?? '-');
                        $betaalwijze = htmlspecialchars($parts[2] ?? '-');
                    } elseif (strpos($rit['adhoc_route'], ' (Betaling: ') !== false) {
                        $parts = explode(' (Betaling: ', $rit['adhoc_route']);
                        $oude_route = explode('-', $parts[0]);
                        $van = trim(htmlspecialchars($oude_route[0]));
                        $naar = isset($oude_route[1]) ? trim(htmlspecialchars($oude_route[1])) : '-';
                        $betaalwijze = isset($parts[1]) ? htmlspecialchars(str_replace(')', '', $parts[1])) : '-';
                    }
                    $bedrag = "&euro; " . number_format((float)$rit['adhoc_prijs'], 2, ',', '.');
                } else {
                    $klant = "<span style='color:#888; font-size:11px;'>Gepland</span>";
                    $bedrag = "-";
                    $van = "<span style='color:#888; font-size:11px;'>Zie uitklap</span>";
                    $naar = "<span style='color:#888; font-size:11px;'>Zie uitklap</span>";
                }
            ?>
                <tr style="border-bottom:1px solid #eee; background: <?php echo $isAdhoc ? '#fffdf5' : '#ffffff'; ?>;">
                    <td style="padding:12px; font-weight:bold; color:#0056b3;"><?php echo date('d-m-Y', strtotime($rit['datum'])); ?></td>
                    <td style="padding:12px;"><?php echo $tijd; ?></td>
                    <td style="padding:12px;"><?php echo $van; ?></td>
                    <td style="padding:12px;"><?php echo $naar; ?></td>
                    <td style="padding:12px; font-weight:bold;"><?php echo $bedrag; ?></td>
                    <td style="padding:12px;"><span style="background:#e9ecef; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold; border:1px solid #ccc;"><?php echo $betaalwijze; ?></span></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($rit['chauffeur_naam']); ?></td>
                    <td style="padding:12px; font-weight:bold;">Bus <?php echo htmlspecialchars($rit['voertuig_nummer']); ?></td>
                    <td style="padding:12px; font-size:13px;"><?php echo $klant; ?></td>
                    <td style="padding:12px; text-align:right;">
                        <button type="button" onclick="document.getElementById('detail_<?php echo $rit['id']; ?>').style.display = (document.getElementById('detail_<?php echo $rit['id']; ?>').style.display === 'none') ? 'table-row' : 'none';" style="background:#f0f0f0; border:1px solid #ccc; padding:6px 10px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:12px;">
                            ✏️ Wijzig
                        </button>
                    </td>
                </tr>

                <tr id="detail_<?php echo $rit['id']; ?>" style="display:none; background:#fafafa; border-bottom:3px solid #ccc;">
                    <td colspan="10" style="padding:20px;">
                        <form method="POST" action="alle_ritten.php" style="margin:0; background:white; padding:20px; border:1px solid #ddd; border-radius:6px;">
                            <input type="hidden" name="rit_id" value="<?php echo $rit['id']; ?>">
                            <input type="hidden" name="bron_type" value="<?php echo $rit['bron_type']; ?>">
                            
                            <input type="hidden" name="f_maand" value="<?php echo $f_maand; ?>">
                            <input type="hidden" name="f_jaar" value="<?php echo $f_jaar; ?>">
                            <input type="hidden" name="f_chauffeur" value="<?php echo htmlspecialchars($f_chauffeur); ?>">
                            <input type="hidden" name="f_bus" value="<?php echo htmlspecialchars($f_bus); ?>">
                            
                            <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:15px; margin-bottom:15px;">
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Datum</label><input type="date" name="datum" value="<?php echo $rit['datum']; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Chauffeur</label><input type="text" name="chauffeur" value="<?php echo htmlspecialchars($rit['chauffeur_naam']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Bus</label><input type="text" name="voertuig" value="<?php echo htmlspecialchars($rit['voertuig_nummer']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                
                                <?php if($isAdhoc): ?>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Soort Rit</label><input type="text" name="type_dienst" value="<?php echo htmlspecialchars($rit['type_dienst']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Klant</label><input type="text" name="adhoc_klant" value="<?php echo htmlspecialchars($rit['adhoc_klant']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                <?php endif; ?>
                            </div>

                            <?php if($isAdhoc): ?>
                                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:15px;">
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Van</label><input type="text" name="van" value="<?php echo $van; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Naar</label><input type="text" name="naar" value="<?php echo $naar; ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div><label style="display:block; font-size:11px; font-weight:bold; color:#666;">Afgerekend (€)</label><input type="text" name="prijs" value="<?php echo htmlspecialchars($rit['adhoc_prijs']); ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></div>
                                    <div>
                                        <label style="display:block; font-size:11px; font-weight:bold; color:#666;">Betaling</label>
                                        <select name="betaalwijze" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                            <option value="Contant" <?php if($betaalwijze == 'Contant') echo 'selected'; ?>>Contant</option>
                                            <option value="PIN" <?php if($betaalwijze == 'PIN') echo 'selected'; ?>>PIN</option>
                                            <option value="Op Rekening" <?php if($betaalwijze == 'Op Rekening') echo 'selected'; ?>>Op Rekening</option>
                                        </select>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom:15px;">
                                    <?php
                                    $stmtR = $pdo->prepare("SELECT * FROM ritregels WHERE rit_id = ? ORDER BY id ASC");
                                    $stmtR->execute([$rit['id']]);
                                    $regels = $stmtR->fetchAll();
                                    ?>
                                    <table style="width:100%; background:#f9f9f9; border:1px solid #ddd; border-collapse:collapse;">
                                        <tr><th style="padding:6px; text-align:left; font-size:11px; border-bottom:1px solid #ddd;">Regel</th><th style="padding:6px; text-align:left; font-size:11px; border-bottom:1px solid #ddd;">Tijd</th></tr>
                                        <?php foreach($regels as $r): ?>
                                            <tr>
                                                <td style="padding:6px; font-size:11px; border-bottom:1px solid #eee;"><?php echo htmlspecialchars($r['omschrijving']); ?></td>
                                                <td style="padding:6px; border-bottom:1px solid #eee;"><input type="time" name="regel[<?php echo $r['id']; ?>][tijd]" value="<?php echo $r['tijd']; ?>" style="padding:4px; border:1px solid #ccc; border-radius:3px;"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <label style="display:block; font-size:11px; font-weight:bold; color:#666; margin-bottom:2px;">Opmerkingen chauffeur / Kantoor notitie</label>
                            <textarea name="opmerkingen" style="width:100%; height:40px; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; font-family:inherit;"><?php echo htmlspecialchars($rit['opmerkingen']); ?></textarea>
                            
                            <div style="text-align:right; margin-top:15px;">
                                <button type="button" onclick="document.getElementById('detail_<?php echo $rit['id']; ?>').style.display='none';" style="background:transparent; border:none; color:#666; cursor:pointer; text-decoration:underline; font-weight:bold; margin-right:15px; font-size:12px;">Sluiten</button>
                                <button type="submit" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">💾 Aanpassingen Opslaan</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>