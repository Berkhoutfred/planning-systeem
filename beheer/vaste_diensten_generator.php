<?php
// Bestand: beheer/vaste_diensten_generator.php
// Doel: Bulk generator voor Vaste Diensten (Mapjes) voor een lange periode

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $naam = trim($_POST['naam']);
    $chauffeur_id = !empty($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : null;
    $start_datum = $_POST['start_datum'];
    $eind_datum = $_POST['eind_datum'];
    $gekozen_dagen = isset($_POST['dagen']) ? $_POST['dagen'] : [];

    if (empty($naam) || empty($start_datum) || empty($eind_datum) || empty($gekozen_dagen)) {
        $error = "Vul alle verplichte velden in en kies minimaal één dag.";
    } elseif (strtotime($eind_datum) < strtotime($start_datum)) {
        $error = "De einddatum mag niet vóór de startdatum liggen.";
    } else {
        $aangemaakt = 0;
        $overgeslagen = 0;

        // Maak objecten van de datums om makkelijk te kunnen rekenen
        $start = new DateTime($start_datum);
        $eind = new DateTime($eind_datum);
        $eind->modify('+1 day'); // Zorg dat de einddatum zelf ook wordt meegenomen

        $interval = new DateInterval('P1D'); // Stapgrootte: 1 dag (1 Day)
        $periode = new DatePeriod($start, $interval, $eind);

        // Zet de database queries alvast klaar (dat is veel sneller dan telkens opnieuw opbouwen)
        $stmt_check = $pdo->prepare("SELECT id FROM diensten WHERE tenant_id = ? AND naam = ? AND geplande_datum = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO diensten (tenant_id, naam, geplande_datum, chauffeur_id, status) VALUES (?, ?, ?, ?, 'actief')");

        // Loop door élke dag in de gekozen periode
        foreach ($periode as $datum) {
            $dag_nr = $datum->format('N'); // Geeft 1 (Ma) t/m 7 (Zo)
            
            // Controleer of deze dag is aangevinkt door de gebruiker
            if (in_array($dag_nr, $gekozen_dagen)) {
                $db_datum = $datum->format('Y-m-d');
                
                // Dubbel-check: Bestaat er al een dienst met deze naam op deze dag?
                if ($chauffeur_id !== null) {
                    $stmtChauffeur = $pdo->prepare("SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1");
                    $stmtChauffeur->execute([$chauffeur_id, $tenantId]);
                    if (!$stmtChauffeur->fetchColumn()) {
                        $error = "Geselecteerde chauffeur hoort niet bij deze tenant.";
                        break;
                    }
                }

                $stmt_check->execute([$tenantId, $naam, $db_datum]);
                if ($stmt_check->rowCount() == 0) {
                    // Hij bestaat nog niet, dus we maken hem aan!
                    $stmt_insert->execute([$tenantId, $naam, $db_datum, $chauffeur_id]);
                    $aangemaakt++;
                } else {
                    // Hij bestaat al, dus we slaan hem over om fouten te voorkomen
                    $overgeslagen++;
                }
            }
        }
        
        if ($error === '') {
            $msg = "✅ Klaar! Er zijn <strong>$aangemaakt</strong> nieuwe diensten in het rooster gezet.";
            if ($overgeslagen > 0) {
                $msg .= " <br><span style='font-size: 12px; color: #555;'>($overgeslagen dagen zijn overgeslagen omdat deze dienst daar al bestond).</span>";
            }
        }
    }
}

// Haal chauffeurs op voor de optionele dropdown
try {
    $stmt_chauf = $pdo->prepare("SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? AND archief = 0 ORDER BY voornaam ASC");
    $stmt_chauf->execute([$tenantId]);
    $chauffeurs_lijst = $stmt_chauf->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

include 'includes/header.php';
?>

<style>
    body { background-color: #f4f7f6; font-family: 'Segoe UI', Arial, sans-serif; }
    .container { max-width: 800px; padding: 30px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 4px solid #17a2b8; }
    
    .header-balk { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    .header-balk h2 { margin: 0; font-size: 22px; color: #003366; }
    
    .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; margin-bottom: 15px; height: 42px; }
    .form-control:focus { border-color: #17a2b8; outline: none; }
    
    label { display: block; font-size: 12px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 5px; }
    
    .dagen-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-bottom: 20px; }
    .dag-box { background: #f8f9fa; border: 1px solid #ddd; padding: 10px; border-radius: 4px; text-align: center; cursor: pointer; transition: 0.2s; }
    .dag-box:hover { background: #e9ecef; }
    .dag-box input[type="checkbox"] { transform: scale(1.3); margin-bottom: 5px; cursor: pointer; }
    .dag-naam { font-weight: bold; font-size: 13px; color: #333; display: block; }
    
    .btn-submit { background: #17a2b8; color: white; border: none; padding: 12px 25px; border-radius: 4px; font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.2s; width: 100%; }
    .btn-submit:hover { background: #138496; }
    
    .btn-terug { background: #6c757d; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 13px; }
    .btn-terug:hover { background: #5a6268; }

    .msg-success { background: #d4edda; color: #155724; padding: 15px; border-left: 5px solid #28a745; border-radius: 4px; margin-bottom: 20px; font-size: 15px; }
    .msg-error { background: #f8d7da; color: #721c24; padding: 15px; border-left: 5px solid #dc3545; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
</style>

<div class="container">
    <div class="header-balk">
        <h2>🔄 Vaste Diensten (Bulk) Aanmaken</h2>
        <a href="weekrooster.php" class="btn-terug">❮ Terug naar Weekrooster</a>
    </div>

    <?php if ($msg) echo "<div class='msg-success'>$msg</div>"; ?>
    <?php if ($error) echo "<div class='msg-error'>$error</div>"; ?>

    <p style="color: #666; font-size: 14px; margin-bottom: 25px;">
        Met deze tool maak je in één klap de dienst-mapjes aan voor een langere periode. Kies de naam, de periode en vink de dagen aan. Het systeem genereert ze direct in het rooster.
    </p>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label>Naam van de Dienst *</label>
                <input type="text" name="naam" class="form-control" placeholder="Bijv. Radeland 1" required>
            </div>
            <div>
                <label>Koppel Chauffeur (Optioneel)</label>
                <select name="chauffeur_id" class="form-control">
                    <option value="">-- Nog in te delen --</option>
                    <?php foreach($chauffeurs_lijst as $chauf): ?>
                        <option value="<?= $chauf['id'] ?>"><?= htmlspecialchars($chauf['voornaam'] . ' ' . $chauf['achternaam']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label>Start Datum *</label>
                <input type="date" name="start_datum" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label>Eind Datum *</label>
                <input type="date" name="eind_datum" class="form-control" required>
            </div>
        </div>

        <label style="margin-top: 10px;">Op welke dagen is deze dienst? *</label>
        <div class="dagen-grid">
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="1"><span class="dag-naam">Ma</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="2"><span class="dag-naam">Di</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="3"><span class="dag-naam">Wo</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="4"><span class="dag-naam">Do</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="5"><span class="dag-naam">Vr</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="6"><span class="dag-naam">Za</span></label>
            <label class="dag-box"><input type="checkbox" name="dagen[]" value="7"><span class="dag-naam">Zo</span></label>
        </div>

        <button type="submit" class="btn-submit">⚡ Genereer Diensten Reeks</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>