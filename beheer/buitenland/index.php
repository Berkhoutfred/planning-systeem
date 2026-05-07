<?php
declare(strict_types=1);

/**
 * Startscherm module Buitenland: nieuwe calculatie met offerte_module=buitenland.
 * Door naar calculaties_bewerken.php — PDF/bevestiging/planbord ongewijzigd.
 */

require_once __DIR__ . '/../beveiliging.php';
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

$klanten = [];
try {
    $stmtK = $pdo->prepare(
        'SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE tenant_id = ? ORDER BY bedrijfsnaam ASC, achternaam ASC'
    );
    $stmtK->execute([$tenantId]);
    $klanten = $stmtK->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Kon klanten niet laden.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $buitenlandDbReady) {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $error = 'Sessie verlopen. Vernieuw de pagina.';
    } else {
        $klantId = (int) ($_POST['klant_id'] ?? 0);
        $titel = trim((string) ($_POST['titel'] ?? ''));
        $ritDatumRaw = trim((string) ($_POST['rit_datum'] ?? ''));
        $passagiers = (int) ($_POST['passagiers'] ?? 0);
        $vertrek = trim((string) ($_POST['vertrek_adres'] ?? ''));
        $bestemming = trim((string) ($_POST['bestemming'] ?? ''));
        $kmNl = max(0, (int) ($_POST['km_nl'] ?? 0));
        $kmDe = max(0, (int) ($_POST['km_de'] ?? 0));
        $overn = (string) ($_POST['overnachting'] ?? 'klant');
        $overnBedragRaw = trim((string) ($_POST['overnachting_bedrag'] ?? ''));
        $toeslagen = trim((string) ($_POST['toeslagen_notities'] ?? ''));
        $prijsRaw = trim((string) ($_POST['prijs_indicatie'] ?? ''));

        if ($klantId <= 0 || $titel === '' || $vertrek === '' || $bestemming === '') {
            $error = 'Klant, titel, vertrek en bestemming zijn verplicht.';
        } elseif ($ritDatumRaw === '' || strtotime($ritDatumRaw) === false) {
            $error = 'Vul een geldige ritdatum in.';
        } elseif ($passagiers < 1 || $passagiers > 999) {
            $error = 'Passagiers: tussen 1 en 999.';
        } elseif (!in_array($overn, ['klant', 'eigen'], true)) {
            $error = 'Ongeldige keuze overnachting.';
        } else {
            $stmtKlant = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmtKlant->execute([$klantId, $tenantId]);
            if (!$stmtKlant->fetchColumn()) {
                $error = 'Deze klant hoort niet bij jouw omgeving.';
            } else {
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

                $ritDatumSql = date('Y-m-d', strtotime($ritDatumRaw));
                $vertrekDatumSql = $ritDatumSql . ' 08:00:00';
                $totaalKm = $kmNl + $kmDe;

                $meta = [
                    'module_version' => 1,
                    'overnachting_door' => $overn === 'klant' ? 'klant' : 'eigen',
                    'overnachting_bedrag_eur' => $overnBedrag,
                    'toeslagen_notities' => $toeslagen,
                    'km_splitsing' => ['nl' => $kmNl, 'de' => $kmDe],
                    'btw_scope_v1' => 'NL en DE km-handmatig; uitbreiding FR etc. later.',
                ];
                try {
                    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    $metaJson = '{}';
                }

                $instructie = "[Buitenland-module]\n"
                    . 'Overnachting: ' . ($overn === 'klant' ? 'door klant (standaard)' : 'door ons')
                    . ($overnBedrag !== null ? ' — budget € ' . number_format($overnBedrag, 2, ',', '.') : '')
                    . "\nKm NL/DE: NL {$kmNl} — DE {$kmDe} — totaal {$totaalKm}"
                    . ($toeslagen !== '' ? "\nToeslagen/handmatig: " . $toeslagen : '');

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
                                ?, ?, ?, ?, 0, NULL, ?, ?,
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
                            'buitenland',
                            $passagiers,
                            $ritDatumSql,
                            $ritDatumSql,
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
                        $error = 'Opslaan mislukt. Voer migratie 20260508_buitenland_module.sql uit of controleer logs.';
                    }
                }
            }
        }
    }
}

$csrf = auth_get_csrf_token();
?>
<style>
    .bl-wrap { max-width: 820px; margin: 20px auto; padding: 0 16px 40px; font-family: 'Segoe UI', sans-serif; }
    .bl-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
    .bl-hd { background: #003366; color: #fff; padding: 14px 18px; font-size: 17px; font-weight: bold; }
    .bl-bd { padding: 18px 20px 24px; }
    .bl-note { background: #e8f4fc; border-left: 4px solid #003366; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; color: #333; line-height: 1.5; }
    .bl-warn { background: #fff3cd; border-left: 4px solid #856404; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; color: #533; }
    .bl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 720px) { .bl-grid { grid-template-columns: 1fr; } }
    label { display: block; font-size: 12px; font-weight: 600; color: #444; margin-bottom: 4px; }
    input[type=text], input[type=number], input[type=date], select, textarea {
        width: 100%; padding: 9px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box;
    }
    textarea { min-height: 72px; resize: vertical; }
    .bl-actions { margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .bl-btn { background: #003366; color: #fff; border: none; padding: 11px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px; }
    .bl-btn:hover { background: #00264d; }
    .bl-btn-sec { background: #6c757d; text-decoration: none; display: inline-block; line-height: 1.2; color: #fff; }
    .bl-err { background: #f8d7da; color: #721c24; padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
</style>

<div class="bl-wrap">
    <div class="bl-card">
        <div class="bl-hd">Nieuwe offerte — Buitenland</div>
        <div class="bl-bd">
            <?php if (!$buitenlandDbReady): ?>
                <div class="bl-warn">
                    <strong>Database nog niet bijgewerkt.</strong> Voer uit:
                    <code style="background:#fff;padding:2px 6px;border-radius:4px;">migrations/20260508_buitenland_module.sql</code>
                </div>
            <?php endif; ?>

            <div class="bl-note">
                <strong>Fase 1.</strong> Dit dossier krijgt marker <em>buitenland</em>; daarna werk je verder in het bestaande offertescherm (PDF, mail, bevestiging, planbord).
                CAO-/BTW-automatisering volgt later; nu vastleggen km NL/DE, overnachting en toeslagen (tekst).
            </div>

            <?php if ($error !== ''): ?>
                <div class="bl-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="bl-grid">
                    <div style="grid-column: 1 / -1;">
                        <label for="klant_id">Klant *</label>
                        <select name="klant_id" id="klant_id" required <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <option value="">— Kies klant —</option>
                            <?php foreach ($klanten as $k): ?>
                                <?php
                                $nm = trim((string) ($k['bedrijfsnaam'] ?? ''));
                                if ($nm === '') {
                                    $nm = trim(($k['voornaam'] ?? '') . ' ' . ($k['achternaam'] ?? ''));
                                }
                                ?>
                                <option value="<?php echo (int) $k['id']; ?>"><?php echo htmlspecialchars($nm, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="titel">Titel *</label>
                        <input type="text" name="titel" id="titel" required maxlength="150"
                               placeholder="Bijv. Meerdaagse tour Duitsland"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label for="rit_datum">Ritdatum *</label>
                        <input type="date" name="rit_datum" id="rit_datum" required value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label for="passagiers">Passagiers *</label>
                        <input type="number" name="passagiers" id="passagiers" required min="1" max="999" value="50"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="vertrek_adres">Vertrekadres *</label>
                        <textarea name="vertrek_adres" id="vertrek_adres" required></textarea>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="bestemming">Bestemming *</label>
                        <textarea name="bestemming" id="bestemming" required></textarea>
                    </div>
                    <div>
                        <label for="km_nl">Km Nederland *</label>
                        <input type="number" name="km_nl" id="km_nl" required min="0" step="1" value="0"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label for="km_de">Km Duitsland *</label>
                        <input type="number" name="km_de" id="km_de" required min="0" step="1" value="0"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>Overnachting</label>
                        <select name="overnachting" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                            <option value="klant">Door klant (standaard)</option>
                            <option value="eigen">Door ons regelen</option>
                        </select>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="overnachting_bedreg">€ indicatie overnachting (alleen bij „door ons”)</label>
                        <input type="text" name="overnachting_bedrag" id="overnachting_bedreg" placeholder="Leeg = n.v.t."
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="prijs_indicatie">Indicatie prijs excl. BTW (optioneel)</label>
                        <input type="text" name="prijs_indicatie" id="prijs_indicatie" placeholder="0,00"
                            <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label for="toeslagen_notities">Toeslagen / chauffeur (tekst)</label>
                        <textarea name="toeslagen_notities" id="toeslagen_notities"></textarea>
                    </div>
                </div>

                <div class="bl-actions">
                    <button type="submit" class="bl-btn" <?php echo !$buitenlandDbReady ? 'disabled' : ''; ?>>Opslaan en verder in offerte</button>
                    <a href="../dashboard.php" class="bl-btn bl-btn-sec">Terug naar dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
