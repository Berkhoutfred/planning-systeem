<?php
// Bestand: beheer/klant-formulier.php
include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';

// 1. Check: Is dit een nieuwe klant of een bestaande?
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($id > 0);

// Variabelen voorbereiden (leeg bij nieuw, gevuld bij edit)
$klant = [
    'bedrijfsnaam' => '', 'klantnummer' => '', 'voornaam' => '', 'achternaam' => '',
    'email' => '', 'email_factuur' => '', 'naam_factuur' => '', 'telefoon' => '', 'adres' => '', 'postcode' => '', 'plaats' => '', 'notities' => '',
    'is_gecontroleerd' => 0 
];
$contactpersonen = [];
$afdelingen = []; // NIEUW: Lege array voor afdelingen

// DATA OPHALEN (Alleen als we bewerken)
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM klanten WHERE id = ?");
    $stmt->execute([$id]);
    $klant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$klant) die("Klant niet gevonden.");

    // Contactpersonen ophalen
    $stmtCp = $pdo->prepare("SELECT * FROM klant_contactpersonen WHERE klant_id = ?");
    $stmtCp->execute([$id]);
    $contactpersonen = $stmtCp->fetchAll(PDO::FETCH_ASSOC);

    // NIEUW: Afdelingen ophalen
    $stmtAfd = $pdo->prepare("SELECT * FROM klant_afdelingen WHERE klant_id = ?");
    $stmtAfd->execute([$id]);
    $afdelingen = $stmtAfd->fetchAll(PDO::FETCH_ASSOC);
}

// 2. OPSLAAN (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction(); // Start transactie (alles of niets)

        $is_gecontroleerd = isset($_POST['is_gecontroleerd']) ? 1 : 0;

        // Gegevens uit formulier (NIEUW: $_POST['naam_factuur'] toegevoegd)
        $data = [
            $_POST['bedrijfsnaam'], $_POST['klantnummer'], $_POST['voornaam'], $_POST['achternaam'],
            $_POST['email'], $_POST['email_factuur'], $_POST['naam_factuur'], $_POST['telefoon'], $_POST['adres'], $_POST['postcode'], 
            $_POST['plaats'], $_POST['notities'], $is_gecontroleerd
        ];

        if ($is_edit) {
            // UPDATE BESTAANDE
            $sql = "UPDATE klanten SET bedrijfsnaam=?, klantnummer=?, voornaam=?, achternaam=?, email=?, email_factuur=?, naam_factuur=?, telefoon=?, adres=?, postcode=?, plaats=?, notities=?, is_gecontroleerd=? WHERE id=?";
            $data[] = $id; 
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
        } else {
            // INSERT NIEUWE
            $sql = "INSERT INTO klanten (bedrijfsnaam, klantnummer, voornaam, achternaam, email, email_factuur, naam_factuur, telefoon, adres, postcode, plaats, notities, is_gecontroleerd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $id = $pdo->lastInsertId(); // Nieuwe ID ophalen
        }

        // CONTACTPERSONEN VERWERKEN
        if($is_edit) {
            $pdo->prepare("DELETE FROM klant_contactpersonen WHERE klant_id = ?")->execute([$id]);
        }
        if (isset($_POST['cp_naam'])) {
            $stmtCp = $pdo->prepare("INSERT INTO klant_contactpersonen (klant_id, naam, telefoon, email) VALUES (?, ?, ?, ?)");
            for ($i = 0; $i < count($_POST['cp_naam']); $i++) {
                if (!empty($_POST['cp_naam'][$i])) {
                    $stmtCp->execute([
                        $id, $_POST['cp_naam'][$i], $_POST['cp_telefoon'][$i], $_POST['cp_email'][$i]
                    ]);
                }
            }
        }

        // NIEUW: AFDELINGEN VERWERKEN
        if($is_edit) {
            $pdo->prepare("DELETE FROM klant_afdelingen WHERE klant_id = ?")->execute([$id]);
        }
        if (isset($_POST['afd_naam'])) {
            $stmtAfd = $pdo->prepare("INSERT INTO klant_afdelingen (klant_id, naam) VALUES (?, ?)");
            for ($i = 0; $i < count($_POST['afd_naam']); $i++) {
                if (!empty(trim($_POST['afd_naam'][$i]))) { // Alleen opslaan als het niet leeg is
                    $stmtAfd->execute([
                        $id, trim($_POST['afd_naam'][$i])
                    ]);
                }
            }
        }

        $pdo->commit(); // Bevestig transactie
        echo "<script>window.location.href='klanten.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); // Fout? Draai alles terug
        $foutmelding = "Er ging iets mis: " . $e->getMessage();
    }
}
?>

<div style="max-width: 900px; margin: auto;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2><?= $is_edit ? 'Klant Bewerken' : 'Nieuwe Klant' ?></h2>
        <a href="klanten.php" style="background:#6c757d; color:white; padding:8px 15px; text-decoration:none; border-radius:4px;">Terug</a>
    </div>

    <?php if (isset($foutmelding)): ?>
        <div style="background:#ffcccc; color:#cc0000; padding:10px; margin-bottom:20px; border-radius:5px;">
            <?= $foutmelding ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <div style="background:#fff9e6; padding:15px 20px; border:1px solid #ffeeba; border-radius:5px; margin-bottom:20px; display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" name="is_gecontroleerd" id="is_gecontroleerd" value="1" <?= ($klant['is_gecontroleerd'] == 1) ? 'checked' : '' ?> style="transform: scale(1.5); cursor: pointer;">
            <label for="is_gecontroleerd" style="font-weight: bold; cursor: pointer; color: #856404; margin: 0;">
                Klantgegevens zijn gecontroleerd
            </label>
        </div>

        <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Algemene Gegevens</h3>
            
            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label>Bedrijfsnaam:</label>
                    <input type="text" name="bedrijfsnaam" value="<?= htmlspecialchars($klant['bedrijfsnaam']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="width:150px;">
                    <label>Klantnummer:</label>
                    <input type="text" name="klantnummer" value="<?= htmlspecialchars($klant['klantnummer']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label>Voornaam: *</label>
                    <input type="text" name="voornaam" required value="<?= htmlspecialchars($klant['voornaam']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex:1;">
                    <label>Achternaam: *</label>
                    <input type="text" name="achternaam" required value="<?= htmlspecialchars($klant['achternaam']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
            </div>
        </div>

        <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Adres- en Contactgegevens</h3>
            
            <div style="margin-bottom:15px;">
                <label style="font-weight:bold; color:#0056b3;">🔍 Zoek adres via Google Maps:</label>
                <input type="text" id="google_search" placeholder="Typ bedrijfsnaam of adres..." style="width:100%; padding:10px; border:2px solid #0056b3; border-radius:4px; background-color:#f0f8ff;">
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:2;">
                    <label>Adres + Huisnummer:</label>
                    <input type="text" name="adres" id="adres" value="<?= htmlspecialchars($klant['adres']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; background:#fafafa;">
                </div>
                <div style="flex:1;">
                    <label>Postcode:</label>
                    <input type="text" name="postcode" id="postcode" value="<?= htmlspecialchars($klant['postcode']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; background:#fafafa;">
                </div>
                <div style="flex:1;">
                    <label>Plaats:</label>
                    <input type="text" name="plaats" id="plaats" value="<?= htmlspecialchars($klant['plaats']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; background:#fafafa;">
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:15px;">
                <div style="flex:1;">
                    <label>Algemeen Telefoon:</label>
                    <input type="text" name="telefoon" value="<?= htmlspecialchars($klant['telefoon']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex:1;">
                    <label>Algemeen Email: *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($klant['email']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
            </div>
            
            <div style="background: #f8fcfe; padding: 15px; border: 1px solid #17a2b8; border-radius: 4px; display:flex; gap:15px;">
                <div style="flex:1;">
                    <label style="color:#17a2b8; font-weight:bold;">T.a.v. Facturatie (Naam / Afdeling):</label>
                    <input type="text" name="naam_factuur" placeholder="Bijv. Financiële Administratie" value="<?= htmlspecialchars($klant['naam_factuur'] ?? '') ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div style="flex:1;">
                    <label style="color:#17a2b8; font-weight:bold;">Factuur Emailadres:</label>
                    <input type="email" name="email_factuur" placeholder="Indien afwijkend van algemeen" value="<?= htmlspecialchars($klant['email_factuur']) ?>" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>
            </div>

        </div>

        <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Onderverdelingen / Afdelingen</h3>
            <p style="color:#666; font-size:14px;">Maak specifieke afdelingen aan (bijv. 'Elftal 1', 'Groep 8') zodat je deze straks bij een rit kunt selecteren.</p>

            <div id="afdelingen_container">
                <?php foreach($afdelingen as $afd): ?>
                <div class="afd-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                    <div style="flex:1;">
                        <input type="text" name="afd_naam[]" value="<?= htmlspecialchars($afd['naam']) ?>" placeholder="Naam van afdeling of groep" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:#dc3545; color:white; border:none; padding:8px 12px; cursor:pointer; border-radius:4px;">Verwijder</button>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="voegAfdelingToe()" style="background:#6f42c1; color:white; border:none; padding:8px 15px; cursor:pointer; border-radius:4px; margin-top:10px;">
                + Afdeling Toevoegen
            </button>
        </div>

        <div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:5px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Contactpersonen</h3>
            <p style="color:#666; font-size:14px;">Voeg hier extra contactpersonen toe (bijv. planners, leerkrachten).</p>

            <div id="contactpersonen_container">
                <?php foreach($contactpersonen as $cp): ?>
                <div class="cp-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center; background:#f9f9f9; padding:10px; border-radius:4px;">
                    <div style="flex:1;"><input type="text" name="cp_naam[]" value="<?= htmlspecialchars($cp['naam']) ?>" placeholder="Naam" style="width:100%; padding:6px;"></div>
                    <div style="flex:1;"><input type="text" name="cp_telefoon[]" value="<?= htmlspecialchars($cp['telefoon']) ?>" placeholder="Telefoon (Direct)" style="width:100%; padding:6px;"></div>
                    <div style="flex:1;"><input type="text" name="cp_email[]" value="<?= htmlspecialchars($cp['email']) ?>" placeholder="Email (Direct)" style="width:100%; padding:6px;"></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:#dc3545; color:white; border:none; padding:5px 10px; cursor:pointer; border-radius:3px;">X</button>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="voegRijToe()" style="background:#17a2b8; color:white; border:none; padding:8px 15px; cursor:pointer; border-radius:4px; margin-top:10px;">
                + Contactpersoon Toevoegen
            </button>
        </div>

        <div style="margin-bottom:20px;">
            <label>Interne Notities:</label>
            <textarea name="notities" rows="3" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"><?= htmlspecialchars($klant['notities']) ?></textarea>
        </div>

        <button type="submit" style="background:#28a745; color:white; padding:15px 30px; border:none; border-radius:5px; cursor:pointer; font-size:18px; font-weight:bold; width:100%;">
            💾 Opslaan
        </button>

    </form>
</div>

<script>
    // 1. Functie om nieuwe contactpersonen toe te voegen
    function voegRijToe() {
        const div = document.createElement('div');
        div.className = 'cp-row';
        div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px; align-items:center; background:#f9f9f9; padding:10px; border-radius:4px;';
        div.innerHTML = `
            <div style="flex:1;"><input type="text" name="cp_naam[]" placeholder="Naam" style="width:100%; padding:6px;"></div>
            <div style="flex:1;"><input type="text" name="cp_telefoon[]" placeholder="Telefoon" style="width:100%; padding:6px;"></div>
            <div style="flex:1;"><input type="text" name="cp_email[]" placeholder="Email" style="width:100%; padding:6px;"></div>
            <button type="button" onclick="this.parentElement.remove()" style="background:#dc3545; color:white; border:none; padding:5px 10px; cursor:pointer; border-radius:3px;">X</button>
        `;
        document.getElementById('contactpersonen_container').appendChild(div);
    }

    // NIEUW: 1B. Functie om nieuwe afdelingen toe te voegen
    function voegAfdelingToe() {
        const div = document.createElement('div');
        div.className = 'afd-row';
        div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px; align-items:center;';
        div.innerHTML = `
            <div style="flex:1;">
                <input type="text" name="afd_naam[]" placeholder="Naam van afdeling of groep (bijv. Elftal 1)" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:#dc3545; color:white; border:none; padding:8px 12px; cursor:pointer; border-radius:4px;">Verwijder</button>
        `;
        document.getElementById('afdelingen_container').appendChild(div);
    }

    // 2. Google Maps Autocomplete
    function initAutocomplete() {
        const input = document.getElementById("google_search");
        if(!input) return;

        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ["address_components", "geometry", "name"],
            types: ["establishment", "geocode"]
        });

        autocomplete.addListener("place_changed", () => {
            const place = autocomplete.getPlace();
            if (!place.address_components) return;

            document.getElementById("adres").value = "";
            document.getElementById("postcode").value = "";
            document.getElementById("plaats").value = "";

            let route = "";
            let street_number = "";

            for (const component of place.address_components) {
                const type = component.types[0];
                if (type === "route") route = component.long_name;
                if (type === "street_number") street_number = component.long_name;
                if (type === "postal_code") document.getElementById("postcode").value = component.long_name;
                if (type === "locality") document.getElementById("plaats").value = component.long_name;
            }
            
            document.getElementById("adres").value = route + " " + street_number;
        });
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(env_value('GOOGLE_MAPS_API_KEY', '')); ?>&libraries=places&callback=initAutocomplete" async defer></script>

<?php include 'includes/footer.php'; ?>