<?php
// Bestand: beheer/klanten.php
include '../beveiliging.php';
require 'includes/db.php';

// --- AJAX ACTIE: SNEL GECONTROLEERD VINKEN ---
if (isset($_POST['ajax_controle_id']) && isset($_POST['nieuwe_status'])) {
    $klant_id = (int)$_POST['ajax_controle_id'];
    $status = (int)$_POST['nieuwe_status'];
    
    $stmt = $pdo->prepare("UPDATE klanten SET is_gecontroleerd = ? WHERE id = ?");
    if ($stmt->execute([$status, $klant_id])) {
        echo "success";
    } else {
        echo "error";
    }
    exit;
}

include 'includes/header.php';

// --- ACTIES AFHANDELEN (Archiveren of Verwijderen) ---
if (isset($_GET['actie']) && isset($_GET['id'])) {
    $actie = $_GET['actie'];
    $klant_id = (int)$_GET['id'];

    if ($actie == 'archiveer') {
        $pdo->prepare("UPDATE klanten SET gearchiveerd = 1 WHERE id = ?")->execute([$klant_id]);
        $melding = "<div style='background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:5px;'>Klant succesvol gearchiveerd.</div>";
    } elseif ($actie == 'verwijder') {
        try {
            $pdo->prepare("DELETE FROM klanten WHERE id = ?")->execute([$klant_id]);
            $melding = "<div style='background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:5px;'>Klant definitief verwijderd.</div>";
        } catch (PDOException $e) {
            $melding = "<div style='background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:5px;'><b>Fout:</b> Kan klant niet verwijderen omdat er nog ritten of offertes aan vastzitten. Archiveer de klant in plaats daarvan.</div>";
        }
    }
}

// --- PAGINERING & ZOEKEN INSTELLINGEN ---
$zoekterm = isset($_GET['zoek']) ? trim($_GET['zoek']) : '';
$records_per_pagina = 50; // Aantal klanten per pagina (kun je aanpassen naar 100 als je wilt)
$huidige_pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($huidige_pagina - 1) * $records_per_pagina;

// Basis SQL voor het zoeken
$where_sql = "WHERE gearchiveerd = 0";
$params = [];

if ($zoekterm != '') {
    $where_sql .= " AND (
        bedrijfsnaam LIKE ? 
        OR voornaam LIKE ? 
        OR achternaam LIKE ? 
        OR plaats LIKE ? 
        OR email LIKE ?
        OR EXISTS (
            SELECT 1 FROM klant_contactpersonen cp 
            WHERE cp.klant_id = klanten.id 
            AND (cp.naam LIKE ? OR cp.email LIKE ?)
        )
    )";
    $wildcard = '%' . $zoekterm . '%';
    // Omdat we 7 vraagtekens in de query hebben, vullen we de array met 7 keer de zoekterm
    $params = array_fill(0, 7, $wildcard);
}

// 1. Eerst tellen we hoeveel klanten er in TOTAAL zijn (belangrijk voor de pagina-knoppen)
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM klanten $where_sql");
$count_stmt->execute($params);
$totaal_klanten = $count_stmt->fetchColumn();
$totaal_paginas = ceil($totaal_klanten / $records_per_pagina);

// 2. Dan halen we ALLEEN de klanten voor DEZE pagina op (LIMIT en OFFSET)
$sql = "SELECT * FROM klanten $where_sql ORDER BY IF(bedrijfsnaam != '', bedrijfsnaam, achternaam) ASC LIMIT $records_per_pagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h1>Klanten Overzicht</h1>
    
    <form method="GET" action="klanten.php" style="display:flex; gap:10px;">
        <input type="text" name="zoek" placeholder="Zoek op naam, plaats of e-mail..." value="<?php echo htmlspecialchars($zoekterm); ?>" autofocus style="padding:8px; border:1px solid #ccc; border-radius:4px; width:250px;">
        <button type="submit" style="padding:8px 15px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer;">Zoeken</button>
        <?php if($zoekterm != ''): ?>
            <a href="klanten.php" style="padding:8px 15px; background:#dc3545; color:white; text-decoration:none; border-radius:4px;">Reset</a>
        <?php endif; ?>
    </form>

    <a href="klant-formulier.php" style="background:#28a745; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;">+ Nieuwe Klant</a>
</div>

<?php if(isset($melding)) echo $melding; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'mails_verzonden') echo "<div style='background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px; border: 1px solid #c3e6cb; font-weight:bold;'>✅ De geselecteerde e-mails zijn succesvol verzonden!</div>"; ?>

<div style="margin-bottom: 15px; color: #666; font-size: 14px;">
    <?php if($zoekterm != ''): ?>
        <strong><?php echo $totaal_klanten; ?></strong> resultaten gevonden voor "<?php echo htmlspecialchars($zoekterm); ?>"
    <?php else: ?>
        Totaal <strong><?php echo $totaal_klanten; ?></strong> actieve klanten in het systeem.
    <?php endif; ?>
</div>

<form method="POST" action="diesel_verzenden.php">
    
    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" style="background:#003366; color:white; padding:10px 20px; border:none; border-radius:5px; font-weight:bold; cursor:pointer; font-size: 15px;">
            <i class="fas fa-paper-plane"></i> E-mail sturen naar selectie
        </button>
    </div>

    <table style="width:100%; border-collapse:collapse; background:white; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <thead style="background:#f8f9fa; border-bottom:2px solid #ddd;">
            <tr>
                <th style="padding:12px; text-align:center; width:50px;">
                    <input type="checkbox" id="selecteerAlles" title="Selecteer alles op deze pagina">
                </th>
                <th style="padding:12px; text-align:left;">Nr.</th>
                <th style="padding:12px; text-align:left;">Bedrijf / Naam</th>
                <th style="padding:12px; text-align:left;">Plaats</th>
                <th style="padding:12px; text-align:center;">Gecontroleerd</th>
                <th style="padding:12px; text-align:left;">Contact</th>
                <th style="padding:12px; text-align:left;">Omzet</th>
                <th style="padding:12px; text-align:left;">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($klant = $stmt->fetch()): 
                $naam = !empty($klant['bedrijfsnaam']) ? $klant['bedrijfsnaam'] : $klant['voornaam'] . ' ' . $klant['achternaam'];
                $isCheck = isset($klant['is_gecontroleerd']) ? (int)$klant['is_gecontroleerd'] : 0;
            ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; text-align:center;">
                        <input type="checkbox" name="klant_ids[]" value="<?php echo $klant['id']; ?>" class="klantCheckbox">
                    </td>
                    
                    <td style="padding:12px;"><?php echo htmlspecialchars($klant['klantnummer']); ?></td>
                    <td style="padding:12px;">
                        <strong><?php echo htmlspecialchars($naam); ?></strong>

                        <?php if(isset($klant['diesel_mail_gehad']) && $klant['diesel_mail_gehad'] == 1): ?>
                            <span style="display:inline-block; margin-left: 10px; background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; border: 1px solid #c3e6cb;">✅ Dieselmail gehad</span>
                        <?php endif; ?>
                        
                        <br>
                        <span style="font-size:12px; color:#888;">
                            <?php if(!empty($klant['bedrijfsnaam'])) echo htmlspecialchars($klant['voornaam'] . ' ' . $klant['achternaam']); ?>
                        </span>
                    </td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($klant['plaats']); ?></td>
                    
                    <td style="padding:12px; text-align:center;">
                        <input type="checkbox" 
                               class="controle-vinkje" 
                               data-id="<?php echo $klant['id']; ?>" 
                               <?php echo ($isCheck == 1) ? 'checked' : ''; ?>
                               style="transform: scale(1.5); cursor: pointer;"
                               title="Markeer als gecontroleerd">
                    </td>

                    <td style="padding:12px;">
                        <small>📧 Algemeen: <?php echo htmlspecialchars($klant['email']); ?></small><br>
                        <?php if(!empty($klant['email_factuur'])): ?>
                            <small style="color: #17a2b8;">🧾 Factuur: <?php echo htmlspecialchars($klant['email_factuur']); ?></small><br>
                        <?php endif; ?>
                        <small>📞 <?php echo htmlspecialchars($klant['telefoon']); ?></small>
                    </td>
                    <td style="padding:12px;">
                        <strong>€ 0,00</strong><br>
                        <span style="font-size:11px; color:#999;">(Wordt nog gekoppeld)</span>
                    </td>
                    <td style="padding:12px; display:flex; gap:5px; flex-wrap:wrap;">
                        <a href="calculatie/maken.php?klant_id=<?php echo $klant['id']; ?>" style="background:#6f42c1; color:white; padding:6px 10px; text-decoration:none; border-radius:4px; font-size:13px; font-weight:bold;">+ Maak Offerte</a>
                        <a href="klant-overzicht.php?id=<?php echo $klant['id']; ?>" style="background:#17a2b8; color:white; padding:6px 10px; text-decoration:none; border-radius:4px; font-size:13px;">Overzicht</a>
                        <a href="klant-formulier.php?id=<?php echo $klant['id']; ?>" style="background:#ffc107; color:black; padding:6px 10px; text-decoration:none; border-radius:4px; font-size:13px;">Wijzig</a>
                        <a href="klanten.php?actie=archiveer&id=<?php echo $klant['id']; ?>" onclick="return confirm('Weet u zeker dat u deze klant wilt archiveren?');" style="background:#6c757d; color:white; padding:6px 10px; text-decoration:none; border-radius:4px; font-size:13px;">Archiveer</a>
                        <a href="#" onclick="dubbeleWaarschuwing(<?php echo $klant['id']; ?>); return false;" style="background:#dc3545; color:white; padding:6px 10px; text-decoration:none; border-radius:4px; font-size:13px;">Verwijder</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</form>

<?php if ($stmt->rowCount() == 0): ?>
    <p style="text-align:center; color:#666; margin-top:20px;">
        <em>Er zijn geen actieve klanten gevonden.</em>
    </p>
<?php endif; ?>

<?php if ($totaal_paginas > 1): ?>
<div style="margin-top: 25px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap;">
    <?php 
        // Zorg dat de zoekterm netjes in de URL blijft als we naar pagina 2 gaan
        $zoek_url = ($zoekterm != '') ? '&zoek=' . urlencode($zoekterm) : '';
        
        for ($i = 1; $i <= $totaal_paginas; $i++): 
            $is_actief = ($i == $huidige_pagina);
    ?>
        <a href="klanten.php?p=<?php echo $i; ?><?php echo $zoek_url; ?>" 
           style="padding: 8px 12px; border: 1px solid #007bff; border-radius: 4px; text-decoration: none; 
                  <?php echo $is_actief ? 'background-color: #007bff; color: white; font-weight: bold;' : 'background-color: white; color: #007bff;'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('selecteerAlles').addEventListener('change', function(e) {
    let checkboxes = document.querySelectorAll('.klantCheckbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = e.target.checked;
    }
});

function dubbeleWaarschuwing(klantId) {
    if (confirm("WAARSCHUWING 1: Weet u zeker dat u deze klant wilt verwijderen?")) {
        if (confirm("WAARSCHUWING 2: Dit is permanent! Alle gegevens gaan verloren. Klik op OK om definitief te wissen.")) {
            window.location.href = "klanten.php?actie=verwijder&id=" + klantId;
        }
    }
}

document.querySelectorAll('.controle-vinkje').forEach(function(vinkje) {
    vinkje.addEventListener('change', function() {
        let isChecked = this.checked ? 1 : 0;
        let klantId = this.getAttribute('data-id');

        let formData = new FormData();
        formData.append('ajax_controle_id', klantId);
        formData.append('nieuwe_status', isChecked);

        fetch('klanten.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if(data !== 'success') {
                alert('Fout bij opslaan. Vernieuw de pagina.');
                this.checked = !isChecked;
            }
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>

<?php include 'includes/footer.php'; ?>