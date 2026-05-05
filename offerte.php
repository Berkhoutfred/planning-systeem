<?php
// Bestand: /offerte.php (IN DE HOOFDMAP VAN DE WEBSITE!)
// VERSIE: Split-Screen + Directe Planbord Koppeling (split_ritten)
// Tenant-safe: alle reads/writes scoped op calculaties.tenant_id; token ook via POST.

require 'beheer/includes/db.php';

$rawToken = $_POST['offerte_token'] ?? $_GET['token'] ?? '';
$token = preg_replace('/[^a-zA-Z0-9]/', '', (string) $rawToken);

if ($token === '') {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Ongeldige of ontbrekende link.</h2>");
}

$stmt = $pdo->prepare("
    SELECT c.*, k.bedrijfsnaam, k.voornaam, k.achternaam
    FROM calculaties c
    LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
    WHERE c.token = ? AND c.tenant_id IS NOT NULL
    LIMIT 1
");
$stmt->execute([$token]);
$rit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rit) {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze offerte is niet gevonden of de link is verlopen.</h2>");
}

$tenantId = (int) $rit['tenant_id'];
if ($tenantId <= 0) {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze offerte is niet gevonden of de link is verlopen.</h2>");
}

$klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['voornaam'] . ' ' . $rit['achternaam'];
$jaar_rit = !empty($rit['rit_datum']) ? date('y', strtotime($rit['rit_datum'])) : date('y');
$orderNummer = $jaar_rit . str_pad((string) $rit['id'], 3, '0', STR_PAD_LEFT);

$actie_uitgevoerd = false;

// FORMULIER AFHANDELEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actie'])) {
    if ($_POST['actie'] === 'accepteren' && $rit['status'] !== 'klant_akkoord' && $rit['status'] !== 'geaccepteerd') {
        $pax = !empty($_POST['definitieve_pax']) ? (int) $_POST['definitieve_pax'] : (int) $rit['passagiers'];
        $contact = trim((string) ($_POST['contact_dag_zelf'] ?? ''));
        $nu = date('Y-m-d H:i:s');

        $upd = $pdo->prepare(
            "UPDATE calculaties SET status = 'klant_akkoord', geaccepteerd_op = ?, definitieve_pax = ?, contact_dag_zelf = ? WHERE id = ? AND tenant_id = ?"
        );
        $upd->execute([$nu, $pax, $contact, $rit['id'], $tenantId]);

        if ($upd->rowCount() === 0) {
            die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze actie kon niet worden uitgevoerd.</h2>");
        }

        $rit['status'] = 'klant_akkoord';
        $actie_uitgevoerd = 'klant_akkoord';

        $splitBestand = 'beheer/includes/split_ritten.php';
        if (file_exists($splitBestand)) {
            require_once $splitBestand;
            if (function_exists('maakParapluRittenAan')) {
                maakParapluRittenAan($pdo, (int) $rit['id']);
            }
        }

        $naar_email = 'info@berkhoutreizen.nl';
        $onderwerp = 'NIEUW AKKOORD: Offerte #' . $orderNummer;

        $bericht = "Beste planner,\n\n";
        $bericht .= 'Klant ' . $klantNaam . " heeft zojuist digitaal akkoord gegeven op de offerte.\n\n";
        $bericht .= 'Ritdatum: ' . date('d-m-Y', strtotime((string) $rit['rit_datum'])) . "\n";
        $bericht .= 'Definitieve Pax: ' . $pax . "\n";
        $bericht .= 'Contact op de dag zelf: ' . $contact . "\n\n";
        $bericht .= "De rit is inmiddels op het live planbord gezet. Ga naar het dashboard om de bus in te plannen en de bevestiging te versturen.\n\n";
        $bericht .= "https://www.berkhoutreizen.nl/beheer/";

        $headers = "From: no-reply@berkhoutreizen.nl\r\n";
        $headers .= "Reply-To: no-reply@berkhoutreizen.nl\r\n";

        @mail($naar_email, $onderwerp, $bericht, $headers);
    } elseif ($_POST['actie'] === 'wijziging' && $rit['status'] !== 'geaccepteerd' && $rit['status'] !== 'klant_akkoord') {
        $opmerking = trim((string) ($_POST['klant_opmerking'] ?? ''));

        $upd = $pdo->prepare(
            "UPDATE calculaties SET status = 'wijziging_verzocht', klant_opmerking = ? WHERE id = ? AND tenant_id = ?"
        );
        $upd->execute([$opmerking, $rit['id'], $tenantId]);

        if ($upd->rowCount() === 0) {
            die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze actie kon niet worden uitgevoerd.</h2>");
        }

        $rit['status'] = 'wijziging_verzocht';
        $actie_uitgevoerd = 'wijziging';
    }
}

$pdfOfferteUrl = 'beheer/calculatie/pdf_offerte.php?id=' . (int) $rit['id'] . '&token=' . rawurlencode($token);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offerte #<?php echo htmlspecialchars($orderNummer, ENT_QUOTES, 'UTF-8'); ?> - Berkhout Reizen</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9eef3;
            background-image: linear-gradient(to bottom, #003366 0%, #003366 200px, #e9eef3 200px);
            background-repeat: no-repeat;
            background-attachment: fixed;
            margin: 0;
            padding: 40px 20px;
            color: #333;
        }

        .main-wrapper {
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .split-layout {
            display: flex;
            flex-direction: row;
            height: 85vh;
            min-height: 650px;
        }

        .pdf-side {
            flex: 0 0 70%;
            background-color: #525659;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }
        .pdf-side iframe { width: 100%; height: 100%; flex-grow: 1; border: none; }

        .action-side {
            flex: 0 0 30%;
            background-color: #fdfdfd;
            padding: 35px 25px;
            overflow-y: auto;
        }

        .info-blok {
            background-color: #f4f7f6;
            border-left: 3px solid #003366;
            padding: 12px 15px;
            margin-bottom: 25px;
            border-radius: 0 4px 4px 0;
        }
        .info-blok p { margin: 0 0 5px 0; font-size: 13px; color: #555; }
        .info-blok p strong { color: #003366; }
        .info-blok p:last-child { margin-bottom: 0; }

        .btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 25px; }

        .btn { padding: 10px 12px; border: none; border-radius: 4px; font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.2s; text-align: center; text-decoration: none; display: block; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-success:hover { background-color: #218838; transform: translateY(-1px); }
        .btn-warning { background-color: #ffc107; color: #333; }
        .btn-warning:hover { background-color: #e0a800; transform: translateY(-1px); }
        .btn-secondary { background-color: #f1f3f5; color: #444; border: 1px solid #ced4da; }
        .btn-secondary:hover { background-color: #e2e6ea; transform: translateY(-1px); }

        .form-section { display: none; background: #fffdf5; border: 1px solid #ffecb5; border-radius: 6px; padding: 15px; margin-top: 15px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #555; }
        .form-control { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 13px; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .status-badge { display: block; padding: 10px 15px; border-radius: 4px; font-weight: bold; font-size: 13px; margin-top: 20px; text-align: center; }
        .badge-blue { background: #e6f2ff; color: #004085; border: 1px solid #b8daff; }
        .badge-orange { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .checkbox-group { display: flex; align-items: flex-start; gap: 8px; margin-top: 15px; margin-bottom: 15px; }
        .checkbox-group input { width: 16px; height: 16px; margin-top: 2px; cursor: pointer; }
        .checkbox-group label { font-size: 12px; cursor: pointer; line-height: 1.4; color: #555; }

        @media (max-width: 900px) {
            .split-layout { flex-direction: column; height: auto; }
            .pdf-side { height: 60vh; flex: none; border-right: none; border-bottom: 2px solid #ddd; }
            .action-side { flex: none; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <div class="split-layout">

        <div class="pdf-side">
            <iframe src="<?php echo htmlspecialchars($pdfOfferteUrl, ENT_QUOTES, 'UTF-8'); ?>#toolbar=0&navpanes=0" title="Offerte">
                <p style="padding: 20px; color: white;">Uw browser ondersteunt geen directe PDF weergave. <a href="<?php echo htmlspecialchars($pdfOfferteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="color: #ff5e14;">Klik hier om de offerte te downloaden.</a></p>
            </iframe>
        </div>

        <div class="action-side">

            <?php if ($rit['status'] === 'klant_akkoord' || $actie_uitgevoerd === 'klant_akkoord' || $rit['status'] === 'geaccepteerd'): ?>

                <div>
                    <h2 style="color: #003366; font-size: 20px; margin-top:0;">Bedankt voor uw akkoord!</h2>
                    <p style="font-size: 13px; color: #666;">Wij hebben uw gegevens in goede orde ontvangen.</p>
                    <div style="background: #f8f9fa; border-radius: 6px; padding: 12px; margin: 20px 0; border: 1px solid #eee;">
                        <p style="margin: 0; font-size: 13px; line-height: 1.5;"><strong>Wat gebeurt er nu?</strong><br>Ons kantoor controleert direct de actuele beschikbaarheid. Zodra deze definitief akkoord is, sturen wij u de bevestiging per e-mail toe.</p>
                    </div>
                    <div class="status-badge badge-blue">⏳ Wachten op bevestiging</div>

                    <a href="<?php echo htmlspecialchars($pdfOfferteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-secondary" style="margin-top: 15px;">🖨️ Print / Download PDF</a>
                </div>

            <?php elseif ($rit['status'] === 'wijziging_verzocht' || $actie_uitgevoerd === 'wijziging'): ?>

                <div>
                    <h2 style="color: #856404; font-size: 20px; margin-top:0;">Wijziging doorgegeven</h2>
                    <p style="font-size: 13px; color: #666;">Wij hebben uw opmerking ontvangen. Ons kantoor zal hier zo snel mogelijk naar kijken en een aangepaste offerte sturen.</p>
                    <div class="status-badge badge-orange">ℹ️ In behandeling</div>
                </div>

            <?php else: ?>

                <div id="intro-text">
                    <h2 style="margin-top:0; color: #003366; font-size: 22px;">Uw Offerte</h2>

                    <div class="info-blok">
                        <p>Klant: <strong><?php echo htmlspecialchars($klantNaam, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p>Kenmerk: <strong>#<?php echo htmlspecialchars($orderNummer, ENT_QUOTES, 'UTF-8'); ?></strong></p>
                    </div>

                    <p style="color: #444; line-height: 1.6; font-size: 13px;">Welkom in uw persoonlijke portaal. Wij hebben uw aanvraag met zorg voor u uitgewerkt.</p>
                    <p style="color: #444; line-height: 1.6; font-size: 13px;">In het document hiernaast vindt u de exacte tijden, de route en de tarieven. Controleer deze gegevens rustig.</p>
                    <p style="color: #444; line-height: 1.6; font-size: 13px;"><strong>Alles naar wens?</strong><br>Geef dan uw akkoord via de groene knop, of stuur ons een wijziging door.</p>
                </div>

                <div id="action-buttons">
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="toonFormulier('form-akkoord')">Ik ga akkoord met de offerte</button>
                        <button type="button" class="btn btn-warning" onclick="toonFormulier('form-wijziging')">Ik wil iets aanpassen</button>
                        <a href="<?php echo htmlspecialchars($pdfOfferteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn btn-secondary">🖨️ Print / Download</a>
                    </div>
                </div>

                <div id="form-akkoord" class="form-section">
                    <h3 style="margin-top:0; color:#155724; font-size: 16px;">Gegevens bevestigen</h3>
                    <p style="font-size:12px; margin-bottom:15px; color: #555;">Na uw akkoord controleren wij de beschikbaarheid.</p>

                    <form method="POST">
                        <input type="hidden" name="offerte_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="actie" value="accepteren">

                        <div class="form-group">
                            <label>Definitief Aantal Personen (Optioneel)</label>
                            <input type="number" name="definitieve_pax" class="form-control" value="<?php echo htmlspecialchars((string) $rit['passagiers'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-group">
                            <label>Contactpersoon + 06-nummer (Optioneel)</label>
                            <input type="text" name="contact_dag_zelf" class="form-control" placeholder="Bijv. Jan de Vries, 06-12345678">
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="voorwaarden" required>
                            <label for="voorwaarden">Ja, ik ga namens <strong><?php echo htmlspecialchars($klantNaam, ENT_QUOTES, 'UTF-8'); ?></strong> akkoord met de offerte en voorwaarden.</label>
                        </div>

                        <button type="submit" class="btn btn-success" style="width:100%;">✔️ Doorsturen naar kantoor</button>
                        <button type="button" onclick="verbergFormulieren()" style="background:none; border:none; color:#666; text-decoration:underline; width:100%; margin-top:8px; font-size: 12px; cursor:pointer;">Annuleren en terug</button>
                    </form>
                </div>

                <div id="form-wijziging" class="form-section">
                    <h3 style="margin-top:0; color:#856404; font-size: 16px;">Wijziging Aanvragen</h3>
                    <p style="font-size:12px; margin-bottom:15px; color: #555;">Wat wilt u aanpassen aan de offerte?</p>

                    <form method="POST">
                        <input type="hidden" name="offerte_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="actie" value="wijziging">

                        <div class="form-group">
                            <textarea name="klant_opmerking" class="form-control" placeholder="Typ hier uw wijzigingen..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-warning" style="width:100%;">Verstuur Wijziging</button>
                        <button type="button" onclick="verbergFormulieren()" style="background:none; border:none; color:#666; text-decoration:underline; width:100%; margin-top:8px; font-size: 12px; cursor:pointer;">Annuleren en terug</button>
                    </form>
                </div>

                <script>
                    function toonFormulier(id) {
                        document.getElementById('action-buttons').style.display = 'none';
                        document.getElementById('intro-text').style.display = 'none';
                        document.getElementById('form-akkoord').style.display = 'none';
                        document.getElementById('form-wijziging').style.display = 'none';

                        document.getElementById(id).style.display = 'block';
                    }

                    function verbergFormulieren() {
                        document.getElementById('form-akkoord').style.display = 'none';
                        document.getElementById('form-wijziging').style.display = 'none';
                        document.getElementById('intro-text').style.display = 'block';
                        document.getElementById('action-buttons').style.display = 'block';
                    }
                </script>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
