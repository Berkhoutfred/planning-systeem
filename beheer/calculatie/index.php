<?php
// Bestand: beheer/calculatie/index.php
// Versie: 3.5 - Sticky Fix & Datumkiezer

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../../beveiliging.php';
require '../includes/db.php';
include '../includes/header.php';

// --- NAVIGATIE LOGICA ---
$huidigeMaand = date('n');
$huidigJaar = date('Y');

// Als er een datum gekozen is via de "Ga naar datum" kiezer
if (isset($_GET['zoek_datum']) && !empty($_GET['zoek_datum'])) {
    $datum = strtotime($_GET['zoek_datum']);
    $maand = date('n', $datum);
    $jaar = date('Y', $datum);
    // Voor de value in het input veld
    $gekozenDatumWaarde = $_GET['zoek_datum'];
} else {
    $maand = isset($_GET['maand']) ? intval($_GET['maand']) : $huidigeMaand;
    $jaar = isset($_GET['jaar']) ? intval($_GET['jaar']) : $huidigJaar;
    $gekozenDatumWaarde = '';
}

// Navigatie knoppen
$vorigeMaand = $maand - 1; $vorigeJaar = $jaar;
if($vorigeMaand < 1) { $vorigeMaand = 12; $vorigeJaar--; }

$volgendeMaand = $maand + 1; $volgendeJaar = $jaar;
if($volgendeMaand > 12) { $volgendeMaand = 1; $volgendeJaar++; }

$maanden = [1=>'JANUARI', 2=>'FEBRUARI', 3=>'MAART', 4=>'APRIL', 5=>'MEI', 6=>'JUNI', 7=>'JULI', 8=>'AUGUSTUS', 9=>'SEPTEMBER', 10=>'OKTOBER', 11=>'NOVEMBER', 12=>'DECEMBER'];

// --- DATA OPHALEN ---
try {
    $stmt = $pdo->prepare("
        SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam, k.plaats 
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id 
        WHERE MONTH(c.rit_datum) = ? AND YEAR(c.rit_datum) = ?
        ORDER BY c.rit_datum ASC
    ");
    $stmt->execute([$maand, $jaar]);
    $ritten = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1600px; margin: 20px auto; padding: 0 15px; }

    /* BOVENBALK */
    .top-bar { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        background: #fff; 
        padding: 15px 20px; 
        border-radius: 8px; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .page-title { margin: 0; color: #003366; font-size: 24px; font-weight: bold; }
    
    /* DATUM ZOEKEN */
    .search-box { display: flex; align-items: center; gap: 10px; background: #f8f9fa; padding: 5px 10px; border-radius: 5px; border: 1px solid #ddd; }
    .date-input { border: none; background: transparent; font-family: inherit; font-size: 14px; color: #333; outline: none; }
    .btn-go { background: #003366; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; }
    
    .btn-nieuw { 
        background: #28a745; color: white; padding: 10px 20px; 
        text-decoration: none; border-radius: 5px; font-weight: bold; 
        display: inline-flex; align-items: center; gap: 8px;
        box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
    }

    /* NAVIGATIE BALK */
    .nav-header { 
        display: flex; justify-content: space-between; align-items: center; 
        background: #003366; color: white; padding: 10px 20px; 
        border-radius: 8px 8px 0 0; font-weight: bold;
    }
    .nav-arrow { color: white; text-decoration: none; font-size: 18px; padding: 5px 15px; border-radius: 4px; transition: background 0.2s; }
    .nav-arrow:hover { background: rgba(255,255,255,0.2); }

    /* TABEL MET STICKY HEADER (De Fix) */
    .table-wrapper {
        /* Dit zorgt dat de tabel niet 'geclipped' wordt, essentieel voor sticky */
        overflow: visible; 
    }
    
    .rit-table { 
        width: 100%; 
        border-collapse: separate; /* Nodig voor sticky */
        border-spacing: 0; 
        background: #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        border-radius: 0 0 8px 8px;
    }

    .rit-table thead th {
        position: sticky; /* HET PLAKWERK */
        top: 0;           /* Plakt bovenaan */
        z-index: 1000;    /* Blijft boven alles */
        background-color: #003366; /* Achtergrondkleur */
        color: white;
        padding: 15px 10px;
        text-align: left;
        font-size: 13px;
        text-transform: uppercase;
        border-bottom: 2px solid #002a55;
    }

    .rit-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f0f0f0;
        color: #333;
        font-size: 14px;
        vertical-align: middle;
        background: #fff; /* Zorgt dat tekst er niet doorheen schijnt bij scrollen */
    }
    
    .rit-table tr:hover td { background-color: #f8fbff; }

    /* STATUS ICONS */
    .status-cell { text-align: center; width: 60px; cursor: pointer; }
    .status-icon { font-size: 18px; color: #ddd; transition: 0.2s; }
    .status-icon.active { color: #28a745; } /* Groen */
    .status-icon:hover { color: #aaa; transform: scale(1.1); }
    .status-icon.active:hover { color: #218838; }

    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
    .badge-dag { background: #e3f2fd; color: #0056b3; }
    .badge-meer { background: #fff3cd; color: #856404; }

    .btn-actie { color: #003366; font-size: 16px; margin: 0 5px; text-decoration: none; }
    .btn-actie.pdf { color: #dc3545; }
    .btn-actie:hover { opacity: 0.7; }

</style>

<div class="container">
    <div style="text-align: center; color: #ccc; font-size: 10px; margin-bottom: 5px;">VERSIE 3.5 - STICKY HEADER & DATUM FIX</div>

    <div class="top-bar">
        <h1 class="page-title"><i class="fas fa-route"></i> Ritplanning</h1>
        
        <form method="GET" class="search-box">
            <i class="fas fa-calendar-alt" style="color:#666;"></i>
            <input type="date" name="zoek_datum" class="date-input" value="<?= $gekozenDatumWaarde ?>">
            <button type="submit" class="btn-go">GA</button>
            <?php if(!empty($gekozenDatumWaarde)): ?>
                <a href="index.php" style="color:#dc3545; text-decoration:none; font-size:18px; padding:0 5px;">&times;</a>
            <?php endif; ?>
        </form>

        <a href="maken.php" class="btn-nieuw"><i class="fas fa-plus"></i> Nieuwe Rit Maken</a>
    </div>

    <div class="nav-header">
        <a href="?maand=<?= $vorigeMaand ?>&jaar=<?= $vorigeJaar ?>" class="nav-arrow"><i class="fas fa-chevron-left"></i> VORIGE</a>
        <span style="font-size: 20px; text-transform: uppercase;"><?= $maanden[$maand] ?> <?= $jaar ?></span>
        <a href="?maand=<?= $volgendeMaand ?>&jaar=<?= $volgendeJaar ?>" class="nav-arrow">VOLGENDE <i class="fas fa-chevron-right"></i></a>
    </div>

    <div class="table-wrapper">
        <table class="rit-table">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th style="width:90px;">Datum</th>
                    <th style="width:200px;">Klant</th>
                    <th>Bestemming</th>
                    <th style="width:100px;">Type</th>
                    <th style="text-align:center;" title="Offerte"><i class="fas fa-file-invoice"></i></th>
                    <th style="text-align:center;" title="Bevestiging"><i class="fas fa-check-double"></i></th>
                    <th style="text-align:center;" title="Ritopdracht"><i class="fas fa-bus"></i></th>
                    <th style="text-align:center;" title="Factuur"><i class="fas fa-euro-sign"></i></th>
                    <th style="text-align:center;" title="Betaald"><i class="fas fa-hand-holding-usd"></i></th>
                    <th style="text-align:center; width:90px;">Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($ritten) == 0): ?>
                    <tr><td colspan="11" style="text-align:center; padding: 50px; color:#999;">Geen ritten gevonden in deze maand.</td></tr>
                <?php endif; ?>

                <?php foreach($ritten as $r): 
                    $klantNaam = !empty($r['bedrijfsnaam']) ? $r['bedrijfsnaam'] : $r['voornaam'].' '.$r['achternaam'];
                    
                    // Route info
                    $stmtD = $pdo->prepare("SELECT adres FROM calculatie_regels WHERE calculatie_id = ? AND type = 't_aankomst_best' LIMIT 1");
                    $stmtD->execute([$r['id']]);
                    $bestemming = $stmtD->fetchColumn() ?: '-';

                    // Vinkjes Logica
                    $st_offerte = !empty($r['datum_offerte_verstuurd']) ? 'active' : '';
                    $st_bevest  = !empty($r['datum_bevestiging_verstuurd']) ? 'active' : '';
                    $st_opdracht= !empty($r['datum_ritopdracht_verstuurd']) ? 'active' : '';
                    $st_factuur = !empty($r['datum_factuur_verstuurd']) ? 'active' : '';
                    $st_betaald = ($r['is_betaald'] == 1) ? 'active' : '';
                ?>
                <tr>
                    <td><strong><?= $r['id'] ?></strong></td>
                    <td>
                        <div style="font-weight:bold; color:#003366;"><?= date('d M', strtotime($r['rit_datum'])) ?></div>
                        <div style="font-size:11px; color:#999;"><?= date('D', strtotime($r['rit_datum'])) ?></div>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($klantNaam) ?></strong><br>
                        <span style="font-size:12px; color:#888;"><?= htmlspecialchars($r['plaats']) ?></span>
                    </td>
                    <td style="color:#555;"><?= htmlspecialchars($bestemming) ?></td>
                    <td><span class="badge <?= ($r['rittype']=='meerdaags') ? 'badge-meer' : 'badge-dag' ?>"><?= $r['rittype'] ?></span></td>
                    
                    <td class="status-cell"><i class="fas fa-check-circle status-icon <?= $st_offerte ?>" title="Offerte"></i></td>
                    <td class="status-cell"><i class="fas fa-check-circle status-icon <?= $st_bevest ?>" title="Bevestiging"></i></td>
                    <td class="status-cell"><i class="fas fa-check-circle status-icon <?= $st_opdracht ?>" title="Ritopdracht"></i></td>
                    <td class="status-cell"><i class="fas fa-check-circle status-icon <?= $st_factuur ?>" title="Factuur"></i></td>
                    <td class="status-cell"><i class="fas fa-check-circle status-icon <?= $st_betaald ?>" title="Betaald"></i></td>

                    <td style="text-align:center;">
                        <a href="calculaties_bewerken.php?id=<?= $r['id'] ?>" class="btn-actie" title="Bewerken"><i class="fas fa-pen"></i></a>
                        <a href="pdf_offerte.php?id=<?= $r['id'] ?>" target="_blank" class="btn-actie pdf" title="PDF"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>