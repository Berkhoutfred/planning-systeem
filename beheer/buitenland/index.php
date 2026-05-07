<?php
declare(strict_types=1);

/**
 * Buitenland: nieuwe calculatie — zelfde UX-lijn als calculatie/maken.php (zoeken, van/t/m, dagprogramma).
 */

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('<div style="padding:20px;font-family:sans-serif;color:#721c24;">Tenant context ontbreekt.</div>');
}

$buitenlandDbReady = false;
try {
    $chk = $pdo->query("SHOW COLUMNS FROM calculaties LIKE 'offerte_module'");
    $buitenlandDbReady = (bool) $chk->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $buitenlandDbReady = false;
}

$error = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $buitenlandDbReady) {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $error = 'Sessie verlopen. Vernieuw de pagina.';
    } else {
        $klantId = (int) ($_POST['klant_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $afdelingIdRaw = trim((string) ($_POST['afdeling_id'] ?? ''));
        $afdelingId = ($afdelingIdRaw !== '' && $afdelingIdRaw !== '0') ? (int) $afdelingIdRaw : null;

        $titel = trim((string) ($_POST['titel'] ?? ''));
        $ritDatumRaw = trim((string) ($_POST['rit_datum'] ?? ''));
        $ritDatumEindRaw = trim((string) ($_POST['rit_datum_eind'] ?? ''));
        $passagiers = (int) ($_POST['passagiers'] ?? 0);
        $vertrek = trim((string) ($_POST['vertrek_adres'] ?? ''));
        $bestemming = trim((string) ($_POST['bestemming'] ?? ''));
        $kmNl = max(0, (int) ($_POST['km_nl'] ?? 0));
        $kmDe = max(0, (int) ($_POST['km_de'] ?? 0));
        $overn = (string) ($_POST['overnachting'] ?? 'klant');
        $overnBedragRaw = trim((string) ($_POST['overnachting_bedrag'] ?? ''));
        $toeslagen = trim((string) ($_POST['toeslagen_notities'] ?? ''));
        $prijsRaw = trim((string) ($_POST['prijs_indicatie'] ?? ''));

        $dagprogrammaIn = $_POST['dagprogramma'] ?? [];
        $dagprogrammaList = [];
        if (is_array($dagprogrammaIn)) {
            foreach ($dagprogrammaIn as $datumStr => $tekst) {
                $datumStr = (string) $datumStr;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumStr)) {
                    continue;
                }
                $dagprogrammaList[] = [
                    'datum' => $datumStr,
                    'tekst' => trim((string) $tekst),
                ];
            }
            usort($dagprogrammaList, static function ($a, $b) {
                return strcmp($a['datum'], $b['datum']);
            });
        }

        if ($klantId <= 0 || $titel === '' || $vertrek === '' || $bestemming === '') {
            $error = 'Klant, titel, vertrek en bestemming zijn verplicht.';
        } elseif ($ritDatumRaw === '' || strtotime($ritDatumRaw) === false) {
            $error = 'Vul een geldige startdatum in.';
        } elseif ($ritDatumEindRaw === '' || strtotime($ritDatumEindRaw) === false) {
            $error = 'Vul een geldige einddatum in.';
        } elseif ($passagiers < 1 || $passagiers > 999) {
            $error = 'Passagiers: tussen 1 en 999.';
        } elseif (!in_array($overn, ['klant', 'eigen'], true)) {
            $error = 'Ongeldige keuze overnachting.';
        } else {
            $ritDatumSql = date('Y-m-d', strtotime($ritDatumRaw));
            $ritDatumEindSql = date('Y-m-d', strtotime($ritDatumEindRaw));
            if ($ritDatumEindSql < $ritDatumSql) {
                $error = 'Einddatum moet op of na de startdatum liggen.';
            }
        }

        if ($error === '') {
            $stmtKlant = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmtKlant->execute([$klantId, $tenantId]);
            if (!$stmtKlant->fetchColumn()) {
                $error = 'Deze klant hoort niet bij jouw omgeving.';
            }
        }

        if ($error === '' && $contactId > 0) {
            $stmtContact = $pdo->prepare(
                'SELECT id FROM klant_contactpersonen WHERE id = ? AND klant_id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmtContact->execute([$contactId, $klantId, $tenantId]);
            if (!$stmtContact->fetchColumn()) {
                $error = 'De geselecteerde contactpersoon hoort niet bij deze klant.';
            }
        }

        if ($error === '' && $afdelingId !== null && $afdelingId > 0) {
            $stmtAfdeling = $pdo->prepare(
                'SELECT id FROM klant_afdelingen WHERE id = ? AND klant_id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmtAfdeling->execute([$afdelingId, $klantId, $tenantId]);
            if (!$stmtAfdeling->fetchColumn()) {
                $error = 'De geselecteerde afdeling hoort niet bij deze klant.';
            }
        }

        if ($error === '') {
            $allowedDates = [];
            $ts = strtotime($ritDatumSql . ' 12:00:00');
            $endTs = strtotime($ritDatumEindSql . ' 12:00:00');
            if ($ts !== false && $endTs !== false) {
                for ($t = $ts; $t <= $endTs; $t += 86400) {
                    $allowedDates[date('Y-m-d', $t)] = true;
                }
            }
            $dagprogrammaFiltered = [];
            foreach ($dagprogrammaList as $row) {
                if (isset($allowedDates[$row['datum']])) {
                    $dagprogrammaFiltered[] = $row;
                }
            }

            $overnBedrag = null;
            if ($overn === 'eigen' && $overnBedragRaw !== '') {
                $norm = str_replace(',', '.', $overnBedragRaw);
                if (is_numeric($norm)) {
                    $overnBedrag = round((float) $norm, 2);
                }
            }

            $prijsExcl = 0.0;
            if ($prijsRaw !== '') {
                $pn = str_replace(',', '.', $prijsRaw);
                if (is_numeric($pn)) {
                    $prijsExcl = round((float) $pn, 2);
                }
            }

            $totaalKm = $kmNl + $kmDe;
            $vertrekDatumSql = $ritDatumSql . ' 08:00:00';

            $meta = [
                'module_version' => 2,
                'overnachting_door' => $overn === 'klant' ? 'klant' : 'eigen',
                'overnachting_bedrag_eur' => $overnBedrag,
                'toeslagen_notities' => $toeslagen,
                'km_splitsing' => ['nl' => $kmNl, 'de' => $kmDe],
                'btw_scope_v1' => 'NL en DE km-handmatig; uitbreiding FR etc. later.',
                'dagprogramma' => $dagprogrammaFiltered,
            ];
            try {
                $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                $metaJson = '{}';
            }

            $instructie = "[Buitenland-module]\n"
                . 'Periode: ' . $ritDatumSql . ' t/m ' . $ritDatumEindSql . "\n"
                . 'Overnachting: ' . ($overn === 'klant' ? 'door klant (standaard)' : 'door ons')
                . ($overnBedrag !== null ? ' — budget € ' . number_format($overnBedrag, 2, ',', '.') : '')
                . "\nKm NL/DE: NL {$kmNl} — DE {$kmDe} — totaal {$totaalKm}"
                . ($toeslagen !== '' ? "\nToeslagen/handmatig: " . $toeslagen : '');
            foreach ($dagprogrammaFiltered as $dp) {
                if (($dp['tekst'] ?? '') !== '') {
                    $instructie .= "\n" . $dp['datum'] . ': ' . $dp['tekst'];
                }
            }

            $contactDb = $contactId > 0 ? $contactId : 0;

            $token = '';
            for ($i = 0; $i < 5; $i++) {
                $token = bin2hex(random_bytes(16));
                $chk = $pdo->prepare('SELECT id FROM calculaties WHERE token = ? AND tenant_id = ? LIMIT 1');
                $chk->execute([$token, $tenantId]);
                if (!$chk->fetchColumn()) {
                    break;
                }
                $token = '';
            }
            if ($token === '') {
                $error = 'Kon geen unieke token genereren. Probeer opnieuw.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmtCalc = $pdo->prepare(
                        'INSERT INTO calculaties (
                            tenant_id, token, titel, klant_id, contact_id, afdeling_id, rittype, passagiers,
                            rit_datum, rit_datum_eind,
                            vertrek_datum, vertrek_locatie, bestemming,
                            vertrek_adres, aankomst_adres, klant_opmerking,
                            voertuig_id, extra_voertuigen, totaal_km, totaal_uren, prijs,
                            km_tussen, km_nl, km_de, instructie_kantoor,
                            offerte_module, buitenland_meta,
                            aangemaakt_op, status
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?,
                            ?, ?, ?,
                            ?, ?, NULL,
                            NULL, NULL, ?, 0, ?,
                            0, ?, ?, ?,
                            ?, ?,
                            NOW(), ?
                        )'
                    );

                    $locVert = function_exists('mb_substr')
                        ? mb_substr($vertrek, 0, 255, 'UTF-8')
                        : substr($vertrek, 0, 255);
                    $locBest = function_exists('mb_substr')
                        ? mb_substr($bestemming, 0, 255, 'UTF-8')
                        : substr($bestemming, 0, 255);

                    $stmtCalc->execute([
                        $tenantId,
                        $token,
                        function_exists('mb_substr') ? mb_substr($titel, 0, 150, 'UTF-8') : substr($titel, 0, 150),
                        $klantId,
                        $contactDb,
                        $afdelingId,
                        'buitenland',
                        $passagiers,
                        $ritDatumSql,
                        $ritDatumEindSql,
                        $vertrekDatumSql,
                        $locVert,
                        $locBest,
                        $vertrek,
                        $bestemming,
                        $totaalKm,
                        $prijsExcl,
                        $kmNl,
                        $kmDe,
                        $instructie,
                        'buitenland',
                        $metaJson,
                        'offerte',
                    ]);

                    $calculatieId = (int) $pdo->lastInsertId();
                    if ($calculatieId <= 0) {
                        throw new RuntimeException('Geen calculatie-ID.');
                    }

                    $stmtRegel = $pdo->prepare(
                        'INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmtRegel->execute([
                        $tenantId,
                        $calculatieId,
                        't_vertrek_klant',
                        'Vertrek (buitenland)',
                        '',
                        $vertrek,
                        (float) $kmNl,
                    ]);
                    $stmtRegel->execute([
                        $tenantId,
                        $calculatieId,
                        't_aankomst_best',
                        'Bestemming (buitenland)',
                        '',
                        $bestemming,
                        (float) $kmDe,
                    ]);

                    $pdo->commit();
                    header('Location: ../calculatie/calculaties_bewerken.php?id=' . $calculatieId, true, 302);
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('buitenland insert: ' . $e->getMessage());
                    $error = 'Opslaan mislukt. Controleer de database-migratie of neem contact op met beheer.';
                }
            }
        }
    }
}

$csrf = auth_get_csrf_token();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1200px; margin: auto; padding: 20px; }
    .section-box { background: #fff; padding: 0; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #ddd; }
    .box-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
    .box-title { color:#003366; font-size:16px; font-weight:bold; text-transform: uppercase; margin:0; }
    .box-body { padding: 20px; }
    .form-grid-4 { display:grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: end; }
    .form-grid-3 { display:grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; }
    .form-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-control { width: 100%; height: 40px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
    label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; display: block; }
    textarea.form-control { height: auto; min-height: 72px; resize: vertical; }
    #klant_info_card { background: #eef; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccd; font-size: 13px; display:none; cursor:pointer; }
    .bl-warn { background: #fff3cd; border-left: 4px solid #856404; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; color: #533; }
    .bl-err { background: #f8d7da; color: #721c24; padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
    .btn-save { width:100%; max-width:520px; padding:15px; background:#003366; color:white; border:none; border-radius:6px; font-weight:bold; font-size:16px; cursor:pointer; margin-top: 12px;}
    .btn-save:hover { background: #00264d; }
    .btn-sec { display:inline-block; padding:12px 18px; background:#6c757d; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; margin-left:10px; }
    .dag-row { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 10px; background: #fafafa; }
    .dag-row strong { color: #003366; font-size: 14px; }
    .modal-overlay { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(2px); }
    .modal-content { background-color: #fefefe; margin: 10vh auto; padding: 0; border: 1px solid #999; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); overflow: hidden; max-width: 500px; }
    .modal-header { background: #28a745; color:white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; font-weight:bold; }
</style>

<div class="container">
    <?php if (!$buitenlandDbReady): ?>
        <div class="bl-warn">
            <strong>Database nog niet bijgewerkt.</strong> Voer de SQL uit uit <code>migrations/20260508_buitenland_module.sql</code> in phpMyAdmin en vernieuw deze pagina.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="bl-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="" id="hoofdFormulier">
        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="section-box" style="border-top: 4px solid #003366;">
            <div class="box-header"><h3 class="box-title"><i class="fas fa-user"></i> Klantgegevens</h3></div>
            <div class="box-body">
                <div class="form-grid-3">
                    <div>
                        <label>Klant zoeken (zoals bij normale offerte)</label>
                        <div style="position:relative;">
                            <input type="text" id="klant_zoek_input" class="form-control" placeholder="Typ minimaal 2 letters: bedrijfs- of achternaam..." autocomplete="off" style="font-weight:bold; border: 2px solid #003366;" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <input type="hidden" name="klant_id" id="klant_id_hidden" value="">
                            <div id="klant_resultaten_lijst" style="display:none; position:absolute; z-index:1000; background:#fff; width:100%; max-height:250px; overflow-y:auto; border:1px solid #003366; border-top:none; box-shadow:0 4px 10px rgba(0,0,0,0.2);"></div>
                        </div>
                        <div id="klant_info_card">
                            <strong id="c_naam"></strong><br>
                            <span id="c_adres"></span>, <span id="c_plaats"></span><br>
                            <span id="c_tel"></span><span id="c_email"></span>
                            <div style="font-size:11px;color:#666;margin-top:6px;">Klik om andere klant te kiezen</div>
                        </div>
                    </div>
                    <div>
                        <label>Contactpersoon</label>
                        <select name="contact_id" id="contact_select" class="form-control" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <option value="0">— Algemeen —</option>
                        </select>
                    </div>
                    <div>
                        <label>Afdeling / groep</label>
                        <select name="afdeling_id" id="afdeling_select" class="form-control" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <option value="0">— Geen —</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #17a2b8;">
            <div class="box-header"><h3 class="box-title"><i class="fas fa-info-circle"></i> Ritgegevens</h3></div>
            <div class="box-body">
                <div class="form-grid-4">
                    <div><label>Passagiers</label><input type="number" name="passagiers" class="form-control" min="1" max="999" value="50" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                    <div><label>Startdatum (van)</label><input type="date" name="rit_datum" id="rit_datum" class="form-control" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                    <div><label>Einddatum (t/m)</label><input type="date" name="rit_datum_eind" id="rit_datum_eind" class="form-control" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                    <div style="grid-column: span 1;"><label>Titel offerte *</label><input type="text" name="titel" class="form-control" required maxlength="150" placeholder="Bijv. Tour Duitsland" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                </div>
            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #28a745;">
            <div class="box-header"><h3 class="box-title"><i class="fas fa-route"></i> Route</h3></div>
            <div class="box-body">
                <div class="form-grid-2">
                    <div><label>Vertrekadres *</label><textarea name="vertrek_adres" id="vertrek_adres" class="form-control" required <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></textarea></div>
                    <div><label>Bestemming *</label><textarea name="bestemming" id="bestemming" class="form-control" required <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></textarea></div>
                </div>
                <div class="form-grid-4" style="margin-top:14px;">
                    <div><label>Km Nederland</label><input type="number" name="km_nl" id="km_nl" class="form-control" min="0" step="1" value="0" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                    <div><label>Km Duitsland</label><input type="number" name="km_de" id="km_de" class="form-control" min="0" step="1" value="0" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></div>
                    <div></div><div></div>
                </div>
            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #0d9488;">
            <div class="box-header"><h3 class="box-title"><i class="fas fa-calendar-alt"></i> Dagprogramma</h3></div>
            <div class="box-body">
                <p style="margin:0 0 12px; font-size:13px; color:#555;">Voor elke dag tussen start- en einddatum kun je hier het programma invullen (optioneel).</p>
                <div id="dagprogramma_container"></div>
            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #805ad5;">
            <div class="box-header"><h3 class="box-title"><i class="fas fa-hotel"></i> Overnachting &amp; prijs</h3></div>
            <div class="box-body">
                <div class="form-grid-4">
                    <div style="grid-column: span 2;">
                        <label>Overnachting</label>
                        <select name="overnachting" class="form-control" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <option value="klant">Door klant (standaard)</option>
                            <option value="eigen">Door ons regelen</option>
                        </select>
                    </div>
                    <div style="grid-column: span 2;">
                        <label>€ indicatie overnachting (alleen bij „door ons”)</label>
                        <input type="text" name="overnachting_bedrag" class="form-control" placeholder="Leeg = n.v.t." <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: span 2;">
                        <label>Indicatie prijs excl. BTW (optioneel)</label>
                        <input type="text" name="prijs_indicatie" class="form-control" placeholder="0,00" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: span 4;">
                        <label>Toeslagen / chauffeur (tekst)</label>
                        <textarea name="toeslagen_notities" class="form-control" rows="3" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>></textarea>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-save" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>><i class="fas fa-save"></i> Opslaan en verder in offerte</button>
        <a href="../dashboard.php" class="btn-sec">Terug naar dashboard</a>
    </form>
</div>

<div id="nieuweKlantModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header"><span>Nieuwe klant</span><span style="cursor:pointer;" onclick="sluitKlantModal()">&times;</span></div>
        <div style="padding: 20px;">
            <form id="formNieuweKlant">
                <label>Bedrijfsnaam / school</label><input type="text" name="bedrijfsnaam" id="nk_bedrijf" class="form-control">
                <div class="form-grid-2" style="margin-top:10px;">
                    <div><label>Voornaam</label><input type="text" name="voornaam" id="nk_voornaam" class="form-control"></div>
                    <div><label>Achternaam</label><input type="text" name="achternaam" id="nk_achternaam" class="form-control"></div>
                </div>
                <label style="margin-top:10px;">Adres</label><input type="text" name="adres" id="nk_adres" class="form-control">
                <label style="margin-top:10px;">Plaats</label><input type="text" name="plaats" id="nk_plaats" class="form-control">
                <div class="form-grid-2" style="margin-top:10px;">
                    <div><label>Telefoon</label><input type="text" name="telefoon" id="nk_telefoon" class="form-control"></div>
                    <div><label>E-mail</label><input type="email" name="email" id="nk_email" class="form-control"></div>
                </div>
                <button type="button" onclick="slaNieuweKlantOp()" class="btn-save" style="margin-top:16px;background:#28a745;">Klant opslaan</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function sluitKlantModal() {
        var m = document.getElementById('nieuweKlantModal');
        if (m) m.style.display = 'none';
    }
    function openNieuweKlantModal() {
        document.getElementById('klant_resultaten_lijst').style.display = 'none';
        document.getElementById('nieuweKlantModal').style.display = 'block';
        var q = document.getElementById('klant_zoek_input').value;
        document.getElementById('nk_bedrijf').value = q;
    }
    window.sluitKlantModal = sluitKlantModal;
    window.openNieuweKlantModal = openNieuweKlantModal;

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function addDaysIso(iso, n) {
        var d = new Date(iso + 'T12:00:00');
        d.setDate(d.getDate() + n);
        return d.toISOString().slice(0, 10);
    }

    function enumerateDates(startIso, endIso) {
        var out = [];
        if (!startIso || !endIso || startIso > endIso) return out;
        var cur = startIso;
        var guard = 0;
        while (cur <= endIso && guard < 400) {
            out.push(cur);
            cur = addDaysIso(cur, 1);
            guard++;
        }
        return out;
    }

    function rebuildDagprogramma() {
        var start = document.getElementById('rit_datum').value;
        var end = document.getElementById('rit_datum_eind').value;
        var box = document.getElementById('dagprogramma_container');
        if (!box) return;
        var oldVal = {};
        box.querySelectorAll('[data-dag-datum]').forEach(function (ta) {
            oldVal[ta.getAttribute('data-dag-datum')] = ta.value;
        });
        box.innerHTML = '';
        if (!start || !end) return;
        if (end < start) {
            box.innerHTML = '<p style="color:#c2410c;font-size:13px;">Einddatum moet op of na de startdatum liggen.</p>';
            return;
        }
        var dates = enumerateDates(start, end);
        dates.forEach(function (iso) {
            var row = document.createElement('div');
            row.className = 'dag-row';
            var lbl = document.createElement('strong');
            lbl.textContent = iso;
            var ta = document.createElement('textarea');
            ta.name = 'dagprogramma[' + iso + ']';
            ta.className = 'form-control';
            ta.setAttribute('data-dag-datum', iso);
            ta.rows = 3;
            ta.placeholder = 'Programma deze dag (optioneel)';
            ta.value = oldVal[iso] || '';
            row.appendChild(lbl);
            row.appendChild(document.createElement('br'));
            row.appendChild(ta);
            box.appendChild(row);
        });
    }
    window.rebuildDagprogramma = rebuildDagprogramma;

    function slaNieuweKlantOp() {
        var form = document.getElementById('formNieuweKlant');
        var formData = new FormData(form);
        fetch('../ajax_nieuwe_klant.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Opslaan mislukt');
                    return;
                }
                sluitKlantModal();
                document.getElementById('klant_zoek_input').style.display = 'none';
                document.getElementById('klant_id_hidden').value = data.klant.id;
                document.getElementById('c_naam').textContent = data.klant.weergave_naam || '';
                document.getElementById('c_adres').textContent = data.klant.adres || '';
                document.getElementById('c_plaats').textContent = data.klant.plaats || '';
                document.getElementById('c_tel').textContent = data.klant.telefoon || '';
                document.getElementById('c_email').textContent = data.klant.email ? (' | ' + data.klant.email) : '';
                document.getElementById('klant_info_card').style.display = 'block';
                form.reset();
                document.getElementById('contact_select').innerHTML = '<option value="0">— Algemeen —</option>';
                document.getElementById('afdeling_select').innerHTML = '<option value="0">— Geen —</option>';
                var va = ((data.klant.adres || '') + ', ' + (data.klant.plaats || '')).trim();
                if (va.length > 2) {
                    var vx = document.getElementById('vertrek_adres');
                    if (vx && !vx.value) vx.value = va;
                }
            })
            .catch(function () { alert('Netwerkfout bij opslaan klant.'); });
    }
    window.slaNieuweKlantOp = slaNieuweKlantOp;

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('klant_zoek_input');
        var list = document.getElementById('klant_resultaten_lijst');
        var form = document.getElementById('hoofdFormulier');
        var rd = document.getElementById('rit_datum');
        var re = document.getElementById('rit_datum_eind');

        function bindKlantSelect(klant) {
            input.style.display = 'none';
            document.getElementById('klant_id_hidden').value = klant.id;
            document.getElementById('c_naam').textContent = klant.weergave_naam || '';
            document.getElementById('c_adres').textContent = klant.adres || '';
            document.getElementById('c_plaats').textContent = klant.plaats || '';
            document.getElementById('c_tel').textContent = klant.telefoon || '';
            document.getElementById('c_email').textContent = klant.email ? (' | ' + klant.email) : '';
            document.getElementById('klant_info_card').style.display = 'block';
            list.style.display = 'none';

            var cs = document.getElementById('contact_select');
            cs.innerHTML = '<option value="0">— Algemeen —</option>';
            fetch('../ajax_get_contacten.php?klant_id=' + encodeURIComponent(klant.id))
                .then(function (r) { return r.json(); })
                .then(function (contacten) {
                    if (contacten && contacten.length) {
                        contacten.forEach(function (c) {
                            var opt = document.createElement('option');
                            opt.value = c.id;
                            opt.textContent = (c.voornaam || '') + ' ' + (c.achternaam || '');
                            cs.appendChild(opt);
                        });
                    }
                });

            var asel = document.getElementById('afdeling_select');
            asel.innerHTML = '<option value="0">— Geen —</option>';
            fetch('../ajax_get_afdelingen.php?klant_id=' + encodeURIComponent(klant.id))
                .then(function (r) { return r.json(); })
                .then(function (afd) {
                    if (afd && afd.length) {
                        afd.forEach(function (a) {
                            var opt = document.createElement('option');
                            opt.value = a.id;
                            opt.textContent = a.naam || '';
                            asel.appendChild(opt);
                        });
                    }
                });

            var vol = (klant.adres || '') + ', ' + (klant.plaats || '');
            if (vol.trim().length > 2 && vol !== ',') {
                var va = document.getElementById('vertrek_adres');
                if (va && !va.value) va.value = vol.trim();
            }
        }

        if (rd && re) {
            rd.addEventListener('change', function () {
                if (re.value < rd.value) re.value = rd.value;
                rebuildDagprogramma();
            });
            re.addEventListener('change', rebuildDagprogramma);
        }
        rebuildDagprogramma();

        if (form) {
            form.addEventListener('submit', function (e) {
                var kid = document.getElementById('klant_id_hidden').value;
                if (!kid || kid === '0') {
                    e.preventDefault();
                    alert('Kies eerst een klant (zoeken of nieuwe klant aanmaken).');
                    if (input) { input.style.display = 'block'; input.focus(); }
                }
            });
        }

        if (input) {
            input.addEventListener('keyup', function () {
                var query = input.value.trim();
                if (query.length < 2) {
                    list.style.display = 'none';
                    return;
                }
                fetch('../ajax_zoek_klant.php?q=' + encodeURIComponent(query))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!Array.isArray(data)) return;
                        list.innerHTML = '';
                        list.style.display = 'block';
                        if (data.length > 0) {
                            data.forEach(function (klant) {
                                var div = document.createElement('div');
                                div.style.padding = '8px 12px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.innerHTML = '<strong>' + escapeHtml(klant.weergave_naam || '') + '</strong> <span style="font-size:11px;color:#888;">(' + escapeHtml(klant.plaats || '') + ')</span>';
                                div.onmouseover = function () { div.style.backgroundColor = '#f0f8ff'; };
                                div.onmouseout = function () { div.style.backgroundColor = '#fff'; };
                                div.onclick = function () { bindKlantSelect(klant); };
                                list.appendChild(div);
                            });
                        } else {
                            list.innerHTML = '<div style="padding:12px;text-align:center;color:#721c24;">Geen treffer</div>' +
                                '<div style="padding:12px;background:#f8f9fa;text-align:center;cursor:pointer;" onclick="openNieuweKlantModal()"><strong style="color:#0056b3;">+ Nieuwe klant aanmaken</strong></div>';
                        }
                    });
            });
        }

        var infoCard = document.getElementById('klant_info_card');
        if (infoCard) {
            infoCard.onclick = function () {
                if (!confirm('Andere klant kiezen?')) return;
                input.value = '';
                input.style.display = 'block';
                document.getElementById('klant_id_hidden').value = '';
                infoCard.style.display = 'none';
                document.getElementById('contact_select').innerHTML = '<option value="0">— Algemeen —</option>';
                document.getElementById('afdeling_select').innerHTML = '<option value="0">— Geen —</option>';
                input.focus();
            };
        }

        document.addEventListener('click', function (ev) {
            if (!list || list.style.display === 'none') return;
            var wrap = input ? input.closest('div') : null;
            if (wrap && !wrap.contains(ev.target)) list.style.display = 'none';
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
