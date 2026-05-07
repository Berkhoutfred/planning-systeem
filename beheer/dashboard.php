<?php
/**
 * Kantoor-dashboard (voorheen index): cockpit + modal "Nieuwe Opdracht".
 * AJAX: POST JSON naar dit bestand met query ?ajax=nieuwe_opdracht
 */
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('<div style="padding:24px;font-family:sans-serif;color:#721c24;">Tenant context ontbreekt.</div>');
}

$googleMapsKey = trim((string) env_value('GOOGLE_MAPS_API_KEY', ''));

// --- AJAX: nieuwe opdracht opslaan ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_GET['ajax'] ?? '') === 'nieuwe_opdracht') {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $in = json_decode((string) $raw, true);
    if (!is_array($in)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige aanvraag.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!auth_validate_csrf_token(isset($in['csrf']) ? (string) $in['csrf'] : null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sessie verlopen. Vernieuw de pagina.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mainType = (string) ($in['main_type'] ?? '');
    $snelSubtype = (string) ($in['snel_subtype'] ?? '');
    $klantMode = (string) ($in['klant_mode'] ?? 'passant');
    $klantIdIn = isset($in['klant_id']) ? (int) $in['klant_id'] : 0;
    $klantLabel = trim((string) ($in['klant_label'] ?? ''));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $datum = trim((string) ($in['datum'] ?? ''));
    $tijd = trim((string) ($in['tijd'] ?? ''));
    $vertrekAdres = trim((string) ($in['vertrek_adres'] ?? ''));
    $bestemmingAdres = trim((string) ($in['bestemming_adres'] ?? ''));
    $voertuigKeuze = (string) ($in['voertuig_type'] ?? 'taxi');
    $bijz = trim((string) ($in['bijzonderheden'] ?? ''));
    $geschattePaxIn = isset($in['geschatte_pax']) ? trim((string) $in['geschatte_pax']) : '';
    $prijsRaw = isset($in['prijsafspraak']) ? trim((string) $in['prijsafspraak']) : '';
    $betaalIn = (string) ($in['betaalwijze'] ?? 'Contant');
    $isRetour = !empty($in['is_retour']);
    $retourDatum = trim((string) ($in['retour_datum'] ?? ''));
    $retourTijd = trim((string) ($in['retour_tijd'] ?? ''));

    if (!in_array($mainType, ['snel', 'touringcar', 'trein'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kies een geldig opdrachttype.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($mainType === 'snel' && !in_array($snelSubtype, ['direct', 'offerte', 'sales_rit'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Kies direct inplannen, sales-pijplijn of klassieke offerte.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($klantMode, ['bestaand', 'passant'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige klantmodus.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($datum === '' || strtotime($datum) === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Vul een geldige datum in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($tijd === '' || !preg_match('/^\d{1,2}:\d{2}$/', $tijd)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Vul een geldige tijd in (uu:mm).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($vertrekAdres === '' || $bestemmingAdres === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Vul vertrek- en bestemmingsadres in.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $betaalWhitelist = ['Contant', 'Pin', 'Op Rekening Vast', 'Op Rekening Meter', 'iDEAL'];
    if (!in_array($betaalIn, $betaalWhitelist, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldige betaalwijze.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($geschattePaxIn === '' || !ctype_digit($geschattePaxIn)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Vul het aantal personen in (minimaal 1).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $geschattePax = (int) $geschattePaxIn;
    if ($geschattePax < 1 || $geschattePax > 999) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Aantal personen: minimaal 1, maximaal 999.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $prijsBedrag = null;
    if ($prijsRaw !== '') {
        $prijsNorm = str_replace(',', '.', $prijsRaw);
        if (!is_numeric($prijsNorm)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ongeldig bedrag.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $prijsBedrag = round((float) $prijsNorm, 2);
        if ($prijsBedrag < 0 || $prijsBedrag > 999999.99) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Bedrag buiten toegestaan bereik.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $voertuigMap = [
        'taxi' => 'Taxi',
        'rolstoel' => 'Rolstoelbus',
        'touring' => 'Touringcar',
    ];
    if ($mainType === 'trein') {
        $voertuigType = 'Treinstremming';
    } else {
        if (!isset($voertuigMap[$voertuigKeuze])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Ongeldig voertuigtype.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $voertuigType = $voertuigMap[$voertuigKeuze];
    }

    $ritDatumSql = date('Y-m-d', strtotime($datum));
    $tParts = explode(':', $tijd);
    $tijdSql = sprintf('%02d:%02d:00', (int) $tParts[0], (int) ($tParts[1] ?? 0));
    $vertrekDatumSql = $ritDatumSql . ' ' . $tijdSql;

    $wantGepland = ($mainType === 'trein') || ($mainType === 'snel' && in_array($snelSubtype, ['direct', 'sales_rit'], true));
    $wantCalculatie = ($mainType === 'touringcar') || ($mainType === 'snel' && $snelSubtype === 'offerte');

    $retourDatumSql = null;
    $retourTijdSql = null;
    $retourVertrekDatumSql = null;
    if ($wantGepland && $isRetour) {
        if ($retourDatum === '' || strtotime($retourDatum) === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Vul een geldige retourdatum in.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($retourTijd === '' || !preg_match('/^\d{1,2}:\d{2}$/', $retourTijd)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Vul een geldige retourtijd in (uu:mm).'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $retourDatumSql = date('Y-m-d', strtotime($retourDatum));
        $rtParts = explode(':', $retourTijd);
        $retourTijdSql = sprintf('%02d:%02d:00', (int) $rtParts[0], (int) ($rtParts[1] ?? 0));
        $retourVertrekDatumSql = $retourDatumSql . ' ' . $retourTijdSql;
    }

    if ($wantGepland && $betaalIn === 'iDEAL' && ($prijsBedrag === null || $prijsBedrag <= 0)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Voor iDEAL is een bedrag (prijsafspraak) verplicht.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return array{0:string,1:string,2:?string} voornaam, achternaam, bedrijfsnaam */
    $parseKlant = static function (string $label): array {
        $label = trim($label);
        if ($label === '') {
            return ['-', '-', null];
        }
        if (preg_match('/\b(b\.?v\.?|nv|vof|holding|groep|stichting)\b/i', $label)) {
            return ['-', 'Contact', $label];
        }
        if (preg_match('/\s/', $label)) {
            $p = preg_split('/\s+/', $label, 2);

            return [trim($p[0]), trim($p[1] ?? '') ?: '-', null];
        }

        return [$label, '-', null];
    };

    $dashboardGarage = static function (PDO $pdo, int $tid): string {
        $fromEnv = trim((string) env_value('DEFAULT_GARAGE_ADRES', ''));
        if ($fromEnv !== '') {
            return function_exists('mb_substr') ? mb_substr($fromEnv, 0, 255, 'UTF-8') : substr($fromEnv, 0, 255);
        }
        $stmt = $pdo->prepare(
            'SELECT cr.adres FROM calculatie_regels cr
             INNER JOIN calculaties c ON c.id = cr.calculatie_id AND c.tenant_id = cr.tenant_id
             WHERE cr.tenant_id = ? AND cr.type = ? AND LENGTH(TRIM(cr.adres)) > 2
             ORDER BY cr.id DESC LIMIT 1'
        );
        $stmt->execute([$tid, 't_garage']);
        $row = $stmt->fetchColumn();
        if ($row !== false && is_string($row) && trim($row) !== '') {
            return function_exists('mb_substr') ? mb_substr(trim($row), 0, 255, 'UTF-8') : substr(trim($row), 0, 255);
        }

        return '';
    };

    try {
        $pdo->beginTransaction();

        $klantId = 0;
        $klantWeergaveNaam = '';

        if ($klantMode === 'bestaand') {
            if ($klantIdIn <= 0) {
                throw new InvalidArgumentException('Kies een klant uit de lijst of schakel over op passant.');
            }
            $stK = $pdo->prepare('SELECT id, email, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stK->execute([$klantIdIn, $tenantId]);
            $kRow = $stK->fetch(PDO::FETCH_ASSOC);
            if (!$kRow) {
                throw new InvalidArgumentException('Klant niet gevonden in deze tenant.');
            }
            $klantId = (int) $kRow['id'];
            $dbMail = strtolower(trim((string) ($kRow['email'] ?? '')));
            if ($wantGepland && $betaalIn === 'iDEAL' && ($dbMail === '' || !filter_var($dbMail, FILTER_VALIDATE_EMAIL))) {
                throw new InvalidArgumentException('Voor iDEAL is een geldig e-mailadres op de klantkaart verplicht.');
            }
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Ongeldig e-mailadres.');
                }
                if ($email !== $dbMail) {
                    $pdo->prepare('UPDATE klanten SET email = ? WHERE id = ? AND tenant_id = ?')->execute([$email, $klantId, $tenantId]);
                    $dbMail = $email;
                }
            }
            $email = $dbMail;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Vul een geldig e-mailadres in (of werk de klantkaart bij).');
            }
            $klantWeergaveNaam = !empty($kRow['bedrijfsnaam']) ? (string) $kRow['bedrijfsnaam'] : trim($kRow['voornaam'] . ' ' . $kRow['achternaam']);
            if ($klantLabel !== '') {
                [$vn, $an, $bedrijf] = $parseKlant($klantLabel);
                $pdo->prepare(
                    'UPDATE klanten SET bedrijfsnaam = COALESCE(NULLIF(?, \'\'), bedrijfsnaam), voornaam = ?, achternaam = ? WHERE id = ? AND tenant_id = ?'
                )->execute([$bedrijf, $vn, $an, $klantId, $tenantId]);
            }
        } else {
            if ($klantLabel === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Vul klant/bedrijf en een geldig e-mailadres in.');
            }
            [$vn, $an, $bedrijf] = $parseKlant($klantLabel);
            $stmtBestaand = $pdo->prepare('SELECT id FROM klanten WHERE tenant_id = ? AND LOWER(TRIM(email)) = ? LIMIT 1');
            $stmtBestaand->execute([$tenantId, $email]);
            $klantId = (int) $stmtBestaand->fetchColumn();
            if ($klantId <= 0) {
                $stmtInsK = $pdo->prepare(
                    'INSERT INTO klanten (tenant_id, bedrijfsnaam, voornaam, achternaam, email, telefoon, adres, postcode, plaats, notities, gearchiveerd, is_gecontroleerd)
                     VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, ?, 0, 0)'
                );
                $stmtInsK->execute([
                    $tenantId,
                    $bedrijf,
                    $vn,
                    $an,
                    $email,
                    'Aangemaakt via dashboard (Nieuwe Opdracht).',
                ]);
                $klantId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare(
                    'UPDATE klanten SET bedrijfsnaam = COALESCE(NULLIF(?, \'\'), bedrijfsnaam), voornaam = ?, achternaam = ? WHERE id = ? AND tenant_id = ?'
                )->execute([$bedrijf, $vn, $an, $klantId, $tenantId]);
            }
        }

        if ($klantId <= 0) {
            throw new RuntimeException('Klant kon niet worden gekoppeld.');
        }

        if ($klantWeergaveNaam === '') {
            $qN = $pdo->prepare('SELECT bedrijfsnaam, voornaam, achternaam FROM klanten WHERE id = ? AND tenant_id = ?');
            $qN->execute([$klantId, $tenantId]);
            $kr = $qN->fetch(PDO::FETCH_ASSOC);
            if ($kr) {
                $klantWeergaveNaam = !empty($kr['bedrijfsnaam']) ? $kr['bedrijfsnaam'] : trim($kr['voornaam'] . ' ' . $kr['achternaam']);
            } else {
                $klantWeergaveNaam = $klantLabel !== '' ? $klantLabel : ('Klant #' . $klantId);
            }
        }

        if ($wantGepland) {
            $omsch = $bijz !== ''
                ? (function_exists('mb_substr') ? mb_substr($bijz, 0, 255, 'UTF-8') : substr($bijz, 0, 255))
                : ($mainType === 'trein' ? 'Treinstremming' : ($snelSubtype === 'sales_rit' ? 'Snelle rit (sales-pijplijn)' : 'Snelle rit (direct)'));
            $instr = 'Dashboard Nieuwe Opdracht' . "\n"
                . 'E-mail: ' . $email . "\n"
                . 'Voertuig: ' . $voertuigType . "\n"
                . 'Betaalwijze: ' . $betaalIn;
            if ($snelSubtype === 'sales_rit') {
                $instr .= "\n\n[SALES-PIJPLIJN] Rit staat ook bij Offertes & Sales (opvolgen prijsvoorstel / klantreactie).";
            }
            if ($bijz !== '') {
                $instr .= "\nBijzonderheden: " . $bijz;
            }

            $factuurNr = null;
            $factuurStatus = 'Te factureren';
            $factuurDatumSql = null;
            if ($betaalIn === 'iDEAL') {
                $bedragFmt = $prijsBedrag !== null ? number_format((float) $prijsBedrag, 2, ',', '.') : '';
                $instr .= "\n\n=== iDEAL — voorlopig overzicht (nog geen factuurnummer) ===\n";
                $instr .= 'Totaal (voor beide ritten indien retour): € ' . $bedragFmt . "\n";
                $instr .= 'Leg 1 — Heen: ' . $vertrekDatumSql . ' | ' . $vertrekAdres . ' → ' . $bestemmingAdres . "\n";
                if ($isRetour && $retourVertrekDatumSql !== null) {
                    $instr .= 'Leg 2 — Retour: ' . $retourVertrekDatumSql . ' | ' . $bestemmingAdres . ' → ' . $vertrekAdres . "\n";
                }
                $instr .= "Factuur en iDEAL-link: rond af via het portaal (factuur-wizard na opslaan).\n";
            }

            $stmtRit = $pdo->prepare(
                'INSERT INTO ritten (tenant_id, datum_start, datum_eind, klant_id, voertuig_type, prijsafspraak, betaalwijze, geschatte_pax, status, instructies, factuur_status, factuurnummer, factuur_datum)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, \'gepland\', ?, ?, ?, ?)'
            );
            $stmtRit->execute([
                $tenantId,
                $vertrekDatumSql,
                $klantId,
                $voertuigType,
                $prijsBedrag,
                $betaalIn,
                $geschattePax,
                $instr,
                $factuurStatus,
                $factuurNr,
                $factuurDatumSql,
            ]);
            $ritId = (int) $pdo->lastInsertId();

            $stmtRegel = $pdo->prepare(
                'INSERT INTO ritregels (tenant_id, rit_id, omschrijving, van_adres, naar_adres) VALUES (?, ?, ?, ?, ?)'
            );
            $stmtRegel->execute([$tenantId, $ritId, $omsch, $vertrekAdres, $bestemmingAdres]);

            $retourRitId = null;
            if ($isRetour && $retourVertrekDatumSql !== null) {
                $retourOmsch = 'Retour: ' . $omsch;
                if ($betaalIn === 'iDEAL') {
                    $retourRitBetaal = 'iDEAL';
                    $retourRitPrijs = null;
                    $retourRitInstr = $instr . "\n\n[Zelfde dossier — retourleg] Gekoppelde heenrit #" . $ritId
                        . '. Totaalbedrag op heenrit; factuur/iDEAL via wizard op heenrit.';
                    $retourRitFactStatus = $factuurStatus;
                    $retourRitFactNr = $factuurNr;
                    $retourRitFactDt = $factuurDatumSql;
                } else {
                    $retourRitBetaal = $betaalIn;
                    $retourRitPrijs = $prijsBedrag;
                    $retourRitInstr = $instr . "\n\n[Retour] Bijbehorende heenrit #" . $ritId . '.';
                    $retourRitFactStatus = $factuurStatus;
                    $retourRitFactNr = $factuurNr;
                    $retourRitFactDt = $factuurDatumSql;
                }

                $stmtRit2 = $pdo->prepare(
                    'INSERT INTO ritten (tenant_id, datum_start, datum_eind, klant_id, voertuig_type, prijsafspraak, betaalwijze, geschatte_pax, status, instructies, factuur_status, factuurnummer, factuur_datum)
                     VALUES (?, ?, NULL, ?, ?, ?, ?, ?, \'gepland\', ?, ?, ?, ?)'
                );
                $stmtRit2->execute([
                    $tenantId,
                    $retourVertrekDatumSql,
                    $klantId,
                    $voertuigType,
                    $retourRitPrijs,
                    $retourRitBetaal,
                    $geschattePax,
                    $retourRitInstr,
                    $retourRitFactStatus,
                    $retourRitFactNr,
                    $retourRitFactDt,
                ]);
                $retourRitId = (int) $pdo->lastInsertId();
                $stmtRegel2 = $pdo->prepare(
                    'INSERT INTO ritregels (tenant_id, rit_id, omschrijving, van_adres, naar_adres) VALUES (?, ?, ?, ?, ?)'
                );
                $stmtRegel2->execute([$tenantId, $retourRitId, $retourOmsch, $bestemmingAdres, $vertrekAdres]);
            }

            if ($mainType === 'snel' && $snelSubtype === 'sales_rit') {
                require_once __DIR__ . '/includes/sales_rit_dossiers.php';
                sales_rit_dossiers_insert($pdo, $tenantId, $ritId, $retourRitId ?: null);
            }

            $pdo->commit();

            if ($betaalIn === 'iDEAL') {
                $bundleIds = [$ritId];
                if ($retourRitId) {
                    $bundleIds[] = $retourRitId;
                }
                $tagVal = 'IDEAL_BUNDLE=' . implode(',', $bundleIds);
                $updB = $pdo->prepare(
                    'UPDATE ritten SET werk_notities = TRIM(CONCAT(COALESCE(werk_notities, \'\'), CHAR(10), ?)) WHERE tenant_id = ? AND id IN (' . implode(',', array_fill(0, count($bundleIds), '?')) . ')'
                );
                $updB->execute(array_merge([$tagVal, $tenantId], $bundleIds));
            }

            $msg = 'Opdracht opgeslagen en staat op het live planbord.';
            if ($retourRitId) {
                $msg = 'Heen- en retourrit opgeslagen op het live planbord.';
            }
            $idealWizardUrl = null;
            if ($betaalIn === 'iDEAL') {
                $msg .= ' Open de factuur-wizard om het PDF-voorbeeld te bekijken, goed te keuren en iDEAL te activeren.';
                $idealWizardUrl = 'factuur_ideal_wizard.php?rit_id=' . $ritId;
            }
            if ($snelSubtype === 'sales_rit') {
                $msg .= ' Ook zichtbaar onder Offertes & Sales.';
            }

            echo json_encode([
                'ok' => true,
                'kind' => 'rit',
                'id' => $ritId,
                'retour_rit_id' => $retourRitId ?: null,
                'message' => $msg,
                'ideal_wizard_url' => $idealWizardUrl,
                'ideal_url' => null,
                'calculaties_url' => $snelSubtype === 'sales_rit' ? 'calculaties.php' : null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($wantCalculatie) {
            $garageAdres = $dashboardGarage($pdo, $tenantId);
            $titel = function_exists('mb_substr')
                ? mb_substr('Dashboard – ' . $klantWeergaveNaam . ' (' . $ritDatumSql . ')', 0, 150, 'UTF-8')
                : substr('Dashboard – ' . $klantWeergaveNaam . ' (' . $ritDatumSql . ')', 0, 150);
            $bron = $mainType === 'touringcar' ? 'Touringcar (offerte)' : 'Snelle rit (offerte)';
            $calcPax = $geschattePax;
            $calcPrijs = $prijsBedrag ?? 0.0;
            $instructie = '[Dashboard] ' . $bron . "\nE-mail: " . $email
                . "\nVoertuigwens: " . $voertuigType
                . "\nIndicatieprijs: " . ($prijsBedrag !== null ? (string) $prijsBedrag : '-')
                . "\nBetaalwens (offerte): " . $betaalIn;
            if ($bijz !== '') {
                $instructie .= "\nBijzonderheden: " . $bijz;
            }
            if ($isRetour) {
                $instructie .= "\nLet op: klant vroeg om retour — nog niet als aparte rit ingepland (offerte-flow).";
            }

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
                throw new RuntimeException('Kon geen token genereren.');
            }

            $stmtCalc = $pdo->prepare(
                'INSERT INTO calculaties (
                    tenant_id, token, titel, klant_id, contact_id, afdeling_id, rittype, passagiers,
                    rit_datum, rit_datum_eind,
                    vertrek_datum, vertrek_locatie, bestemming,
                    vertrek_adres, aankomst_adres, klant_opmerking,
                    voertuig_id, extra_voertuigen, totaal_km, totaal_uren, prijs,
                    km_tussen, km_nl, km_de, instructie_kantoor,
                    aangemaakt_op, status
                ) VALUES (
                    ?, ?, ?, ?, 0, NULL, ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, NULL,
                    NULL, NULL, 0, 0, ?,
                    0, 0, 0, ?,
                    NOW(), ?
                )'
            );
            $stmtCalc->execute([
                $tenantId,
                $token,
                $titel,
                $klantId,
                'enkel',
                $calcPax,
                $ritDatumSql,
                $ritDatumSql,
                $vertrekDatumSql,
                function_exists('mb_substr') ? mb_substr($vertrekAdres, 0, 255, 'UTF-8') : substr($vertrekAdres, 0, 255),
                function_exists('mb_substr') ? mb_substr($bestemmingAdres, 0, 255, 'UTF-8') : substr($bestemmingAdres, 0, 255),
                $vertrekAdres,
                $bestemmingAdres,
                $calcPrijs,
                $instructie,
                'offerte',
            ]);
            $calculatieId = (int) $pdo->lastInsertId();
            if ($calculatieId <= 0) {
                throw new RuntimeException('Calculatie opslaan mislukt.');
            }

            $stmtRegel = $pdo->prepare(
                'INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km) VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            $labels = [
                't_garage' => 'Vertrek Garage',
                't_voorstaan' => 'Voorstaan',
                't_vertrek_klant' => 'Vertrek Klant',
                't_aankomst_best' => 'Bestemming',
                't_retour_garage_heen' => 'Retour garage (heen)',
            ];
            $insR = static function (PDO $pdo, int $tid, int $cid, string $type, string $label, string $tijd, string $adres) use ($stmtRegel): void {
                $stmtRegel->execute([$tid, $cid, $type, $label, $tijd, $adres]);
            };
            if ($garageAdres !== '') {
                $insR($pdo, $tenantId, $calculatieId, 't_garage', $labels['t_garage'], '', $garageAdres);
            }
            $insR($pdo, $tenantId, $calculatieId, 't_voorstaan', $labels['t_voorstaan'], '', $vertrekAdres);
            $insR($pdo, $tenantId, $calculatieId, 't_vertrek_klant', $labels['t_vertrek_klant'], $tijdSql, $vertrekAdres);
            $insR($pdo, $tenantId, $calculatieId, 't_aankomst_best', $labels['t_aankomst_best'], $tijdSql, $bestemmingAdres);
            if ($garageAdres !== '') {
                $insR($pdo, $tenantId, $calculatieId, 't_retour_garage_heen', $labels['t_retour_garage_heen'], '', $garageAdres);
            }

            $pdo->commit();
            echo json_encode([
                'ok' => true,
                'kind' => 'calculatie',
                'id' => $calculatieId,
                'message' => 'Offerte-opdracht opgeslagen in calculaties.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new RuntimeException('Geen geldige route.');
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Opslaan mislukt. Probeer opnieuw of neem contact op met beheer.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// --- Pagina-data (zelfde als vorige index) ---
$uur = date('H');
$groet = ($uur < 12) ? 'Goedemorgen' : (($uur < 18) ? 'Goedemiddag' : 'Goedenavond');

try {
    $stmt_akkoorden = $pdo->prepare(
        "SELECT c.id, c.rit_datum, c.geaccepteerd_op, k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
         WHERE c.tenant_id = ?
           AND c.status = 'klant_akkoord'
         ORDER BY c.geaccepteerd_op DESC"
    );
    $stmt_akkoorden->execute([$tenantId]);
    $nieuwe_akkoorden = $stmt_akkoorden->fetchAll();
} catch (PDOException $e) {
    $nieuwe_akkoorden = [];
}

$inkomende_aanvragen = [];
try {
    $stmtAanvragen = $pdo->prepare(
        "SELECT c.id, c.rit_datum, c.aangemaakt_op, k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
         WHERE c.tenant_id = ?
           AND c.status = 'aanvraag'
         ORDER BY c.aangemaakt_op DESC, c.id DESC
         LIMIT 8"
    );
    $stmtAanvragen->execute([$tenantId]);
    $inkomende_aanvragen = $stmtAanvragen->fetchAll();
} catch (PDOException $e) {
    $inkomende_aanvragen = [];
}

$alerts = [];
$nu = new DateTime('today');

function checkDatum($datum_db, $naam, $onderwerp, $icon, &$alerts, $nu)
{
    if (!$datum_db || $datum_db == '0000-00-00') {
        return;
    }

    $check = new DateTime($datum_db);
    $diff = $nu->diff($check);
    $dagen = $diff->days;
    $is_verleden = $diff->invert == 1;

    if ($is_verleden && $dagen > 0) {
        $alerts[] = ['icon' => $icon, 'titel' => $naam, 'msg' => "$onderwerp is VERLOPEN!", 'type' => 'danger', 'sortering' => 1];
    } elseif (!$is_verleden && $dagen <= 60) {
        $dagen_tekst = ($dagen == 0) ? 'VANDAAG' : "over $dagen dagen";
        $alerts[] = ['icon' => $icon, 'titel' => $naam, 'msg' => "$onderwerp verloopt $dagen_tekst", 'type' => 'warning', 'sortering' => 2];
    }
}

function checkVerjaardag($datum_db, $naam, &$alerts, $nu)
{
    if (!$datum_db || $datum_db == '0000-00-00') {
        return;
    }

    $verjaardag = new DateTime($datum_db);
    $verjaardag->setDate((int) $nu->format('Y'), (int) $verjaardag->format('m'), (int) $verjaardag->format('d'));

    if ($verjaardag < $nu) {
        $verjaardag->modify('+1 year');
    }

    $dagen = $nu->diff($verjaardag)->days;

    if ($dagen == 0) {
        $alerts[] = ['icon' => 'fa-birthday-cake', 'titel' => $naam, 'msg' => 'Is VANDAAG jarig! 🎂', 'type' => 'info', 'sortering' => 0];
    }
}

try {
    $stmtV = $pdo->prepare('SELECT naam, apk_datum, tacho_datum FROM voertuigen WHERE tenant_id = ? AND archief = 0');
    $stmtV->execute([$tenantId]);
    foreach ($stmtV->fetchAll() as $v) {
        checkDatum($v['apk_datum'], $v['naam'], 'APK', 'fa-bus', $alerts, $nu);
        checkDatum($v['tacho_datum'], $v['naam'], 'Tacho', 'fa-tachometer-alt', $alerts, $nu);
    }

    $stmtC = $pdo->prepare('SELECT voornaam, achternaam, rijbewijs_verloopt, bestuurderskaart_geldig_tot, code95_geldig_tot, geboortedatum FROM chauffeurs WHERE tenant_id = ? AND archief = 0 AND actief = 1');
    $stmtC->execute([$tenantId]);
    foreach ($stmtC->fetchAll() as $c) {
        $naam = trim($c['voornaam'] . ' ' . $c['achternaam']);
        checkDatum($c['rijbewijs_verloopt'], $naam, 'Rijbewijs', 'fa-id-card', $alerts, $nu);
        checkDatum($c['bestuurderskaart_geldig_tot'], $naam, 'Chauffeurskaart', 'fa-id-badge', $alerts, $nu);
        checkDatum($c['code95_geldig_tot'], $naam, 'Code 95', 'fa-graduation-cap', $alerts, $nu);
        checkVerjaardag($c['geboortedatum'], $naam, $alerts, $nu);
    }

    usort($alerts, static function ($a, $b) {
        return $a['sortering'] <=> $b['sortering'];
    });
} catch (PDOException $e) {
}

$aantalAkkoorden = count($nieuwe_akkoorden);
$aantalAanvragen = count($inkomende_aanvragen);
$aantalAlerts = count($alerts);

$akkoordPreview = 'Geen openstaande punten.';
if ($aantalAkkoorden > 0) {
    $akk0 = $nieuwe_akkoorden[0];
    $akNaam = !empty($akk0['bedrijfsnaam']) ? (string) $akk0['bedrijfsnaam'] : trim(((string) ($akk0['voornaam'] ?? '')) . ' ' . ((string) ($akk0['achternaam'] ?? '')));
    $akDatum = (string) ($akk0['rit_datum'] ?? '');
    $akDatumTxt = ($akDatum !== '' && strtotime($akDatum) !== false) ? date('d-m-Y', strtotime($akDatum)) : 'onbekend';
    $akkoordPreview = trim(($akNaam !== '' ? $akNaam : 'Klant') . ' - ritdatum ' . $akDatumTxt);
}

$aanvraagPreview = 'Geen nieuwe aanvragen.';
if ($aantalAanvragen > 0) {
    $aan0 = $inkomende_aanvragen[0];
    $aanNaam = !empty($aan0['bedrijfsnaam']) ? (string) $aan0['bedrijfsnaam'] : trim(((string) ($aan0['voornaam'] ?? '')) . ' ' . ((string) ($aan0['achternaam'] ?? '')));
    $aanDatum = (string) ($aan0['rit_datum'] ?? '');
    $aanDatumTxt = ($aanDatum !== '' && strtotime($aanDatum) !== false) ? date('d-m-Y', strtotime($aanDatum)) : 'onbekend';
    $aanvraagPreview = trim(($aanNaam !== '' ? $aanNaam : 'Klant') . ' - ritdatum ' . $aanDatumTxt);
}

$alertPreview = 'Geen waarschuwingen actief.';
if ($aantalAlerts > 0) {
    $alertPreview = (string) ($alerts[0]['msg'] ?? 'Controle op documenten en verloopdatums nodig.');
}

$dashCsrf = auth_get_csrf_token();

$dashUurOptions = '';
for ($h = 0; $h < 24; ++$h) {
    $hv = sprintf('%02d', $h);
    $dashUurOptions .= '<option value="' . $hv . '">' . $hv . "</option>\n";
}

include 'includes/header.php';
?>

<style>
    body { background-color: #f4f7f6; }

    .dashboard-container { max-width: 1240px; margin: 28px auto; padding: 0 20px; }

    .welkomst-balk { margin-bottom: 22px; color: #003366; }
    .welkomst-balk h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.4px; }
    .welkomst-balk p { margin: 4px 0 0 0; color: #5b6878; font-size: 14px; }

    .main-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; align-items: start; }

    .actie-lijst { display: flex; flex-direction: column; gap: 13px; }
    .action-card {
        display: flex; align-items: center; background: #fff; border-radius: 8px; padding: 16px 18px; height: 96px; box-sizing: border-box;
        text-decoration: none; color: #333; box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        border: 1px solid #eaeaea; border-left: 5px solid #ccc; transition: all 0.2s cubic-bezier(0.25, 0.8, 0.25, 1);
        cursor: pointer; font: inherit; text-align: left; width: 100%;
    }
    .action-card:hover { transform: translateX(5px); box-shadow: 0 6px 15px rgba(0,0,0,0.08); }

    .card-nieuw { border-left-color: #2b6cb0; } .card-nieuw i { color: #2b6cb0; }
    .card-planbord { border-left-color: #38a169; } .card-planbord i { color: #38a169; }
    .card-sales { border-left-color: #805ad5; } .card-sales i { color: #805ad5; }

    .action-card i { font-size: 26px; width: 52px; text-align: center; }
    .action-text { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
    .action-text h3 { margin: 0 0 4px 0; font-size: 15px; font-weight: 700; line-height: 1.25; }
    .action-text p {
        margin: 0;
        font-size: 12px;
        color: #718096;
        line-height: 1.35;
        min-height: calc(1.35em * 2);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .info-kolom { display: flex; flex-direction: column; gap: 13px; }
    .card-akkoord-overview { border-left-color: #7fd3e4; }
    .card-akkoord-overview i { color: #0b3e69; }
    .card-aanvraag-overview { border-left-color: #1f4fa3; }
    .card-aanvraag-overview i { color: #1f4fa3; }
    .card-alert-overview { border-left-color: #c24141; }
    .card-alert-overview i { color: #c24141; }
    .action-card-count {
        margin-left: auto;
        min-width: 34px;
        height: 34px;
        border-radius: 999px;
        background: #edf2f7;
        border: 1px solid #d2dbe6;
        color: #0b3e69;
        font-size: 13px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 8px;
    }

    .text-danger { color: #e53e3e; font-weight: bold; }
    .text-warning { color: #dd6b20; font-weight: bold; }
    .text-info { color: #3182ce; font-weight: bold; }
    .text-muted { color: #718096; font-size: 12px; }

    .icon-box { width: 25px; text-align: center; display: inline-block; color: #a0aec0; }


    /* Modal */
    .dash-modal-overlay {
        display: none; position: fixed; inset: 0; z-index: 20000;
        background: rgba(15, 23, 42, 0.55); align-items: center; justify-content: center; padding: 16px;
    }
    .dash-modal-overlay.is-open { display: flex; }
    .dash-modal {
        background: #fff; border-radius: 8px; max-width: 600px; width: 100%; max-height: 92vh; overflow: auto;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08); border: 1px solid #eaeaea; border-left: 5px solid #2b6cb0;
    }
    .dash-modal-hd { padding: 16px 20px; border-bottom: 1px solid #eaeaea; display: flex; justify-content: space-between; align-items: center; }
    .dash-modal-hd h2 { margin: 0; font-size: 17px; color: #003366; }
    .dash-modal-bd { padding: 18px 20px 22px; }
    .dash-step { display: none; }
    .dash-step.is-active { display: block; }
    .dash-opt {
        display: flex; align-items: center; gap: 12px; width: 100%; margin-bottom: 10px; padding: 14px 14px;
        border: 1px solid #eaeaea; border-radius: 8px; background: #fff; cursor: pointer; text-align: left; font: inherit;
        border-left: 5px solid #cbd5e1; transition: 0.15s;
    }
    .dash-opt:hover { border-left-color: #2b6cb0; background: #f7fafc; }
    .dash-opt.is-selected { border-left-color: #2b6cb0; background: #ebf4ff; }
    a.dash-opt.dash-opt-buitenland { text-decoration: none; color: inherit; box-sizing: border-box; }
    .dash-opt.dash-opt-buitenland { border-left-color: #99f6e4; }
    .dash-opt.dash-opt-buitenland:hover { border-left-color: #0d9488; background: #f0fdfa; }
    .dash-opt strong { display: block; font-size: 14px; color: #1a202c; }
    .dash-opt span { font-size: 12px; color: #718096; }
    .dash-row { margin-bottom: 12px; }
    .dash-row label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #4a5568; margin-bottom: 4px; }
    .dash-row input, .dash-row textarea, .dash-row select {
        width: 100%; padding: 10px 11px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; box-sizing: border-box;
    }
    .dash-row textarea { min-height: 70px; resize: vertical; }
    .dash-seg { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .dash-seg label { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; text-transform: none; letter-spacing: 0; color: #2d3748; cursor: pointer; }
    .dash-zoek-wrap { position: relative; }
    #dash_klant_resultaten {
        display: none; position: absolute; z-index: 21001; left: 0; right: 0; top: 100%; margin-top: 2px;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; max-height: 220px; overflow-y: auto;
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    #dash_klant_resultaten div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    #dash_klant_resultaten div:hover { background: #ebf4ff; }
    #dash_klant_resultaten div:last-child { border-bottom: none; }
    .dash-hint { font-size: 11px; color: #718096; margin-top: 4px; }
    .dash-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
    .dash-btn { padding: 10px 16px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; font-family: inherit; }
    .dash-btn-sec { background: #edf2f7; color: #2d3748; border: 1px solid #e2e8f0; }
    .dash-btn-prim { background: #003366; color: #fff; }
    .dash-btn-prim:disabled { opacity: 0.55; cursor: not-allowed; }
    .dash-close { background: none; border: none; font-size: 22px; line-height: 1; cursor: pointer; color: #718096; padding: 4px 8px; }
    .dash-muted { font-size: 12px; color: #718096; margin-top: 8px; }

    #dash-toast {
        display: none; position: fixed; bottom: 24px; right: 24px; z-index: 21000;
        background: #276749; color: #fff; padding: 14px 18px; border-radius: 8px; font-weight: 600; font-size: 14px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.18); max-width: 360px;
    }
    #dash-toast.is-on { display: block; }

    /* Google Places-suggesties boven de modal */
    .pac-container { z-index: 25000 !important; }

    .dash-time-card {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 4px;
    }
    .dash-time-card-label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #003366;
        margin-bottom: 8px;
    }
    .dash-time-grid { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .dash-time-grid select {
        flex: 1;
        min-width: 72px;
        max-width: 120px;
        font-size: 15px;
        font-weight: 600;
        padding: 11px 10px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #1a202c;
    }
    .dash-time-sep { font-size: 20px; font-weight: 700; color: #4a5568; padding: 0 2px; }
    .dash-time-hint { font-size: 11px; color: #718096; margin-top: 6px; }

    .dash-retour-box {
        margin-top: 6px;
        padding: 14px;
        border-radius: 8px;
        border: 1px dashed #2b6cb0;
        background: #f0f7ff;
    }
    .dash-retour-box h4 { margin: 0 0 10px 0; font-size: 14px; color: #003366; }

    @media (max-width: 900px) {
        .main-grid { grid-template-columns: 1fr; }
        .action-card { height: auto; min-height: 96px; }
        .panel-content { max-height: none; }
    }
</style>

<div class="dashboard-container">

    <div class="welkomst-balk">
        <h1><?php echo htmlspecialchars($groet, ENT_QUOTES, 'UTF-8'); ?>, welkom terug.</h1>
        <p>Overzicht van planning, sales en opvolging.</p>
    </div>

    <div class="main-grid">

        <div class="actie-lijst">
            <button type="button" class="action-card card-nieuw" id="btn-open-nieuwe-opdracht" aria-haspopup="dialog">
                <i class="fas fa-plus-circle"></i>
                <div class="action-text">
                    <h3>Nieuwe Opdracht</h3>
                    <p>Start een nieuwe rit, offerte of treinstremming.</p>
                </div>
            </button>

            <a href="live_planbord.php" class="action-card card-planbord">
                <i class="fas fa-map-marked-alt"></i>
                <div class="action-text">
                    <h3>Live Planbord</h3>
                    <p>Wie rijdt wat? Koppel chauffeurs aan ritten.</p>
                </div>
            </a>

            <a href="calculaties.php" class="action-card card-sales">
                <i class="fas fa-chart-line"></i>
                <div class="action-text">
                    <h3>Offertes &amp; Sales</h3>
                    <p>Beheer lopende offertes en wachtende klanten.</p>
                </div>
            </a>
        </div>

        <div class="info-kolom">
            <a href="aandachtspunten.php#akkoorden" class="action-card card-akkoord-overview">
                <i class="fas fa-bell"></i>
                <div class="action-text">
                    <h3>Nieuwe Akkoorden (Inplannen)</h3>
                    <p><?php echo htmlspecialchars($akkoordPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <span class="action-card-count"><?php echo (int) $aantalAkkoorden; ?></span>
            </a>

            <a href="aandachtspunten.php#aanvragen" class="action-card card-aanvraag-overview">
                <i class="fas fa-inbox"></i>
                <div class="action-text">
                    <h3>Inkomende Aanvragen (Nieuw)</h3>
                    <p><?php echo htmlspecialchars($aanvraagPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <span class="action-card-count"><?php echo (int) $aantalAanvragen; ?></span>
            </a>

            <a href="aandachtspunten.php#waarschuwingen" class="action-card card-alert-overview">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="action-text">
                    <h3>Actie Vereist (Waarschuwingen)</h3>
                    <p><?php echo htmlspecialchars($alertPreview, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <span class="action-card-count"><?php echo (int) $aantalAlerts; ?></span>
            </a>
        </div>
    </div>
</div>

<div class="dash-modal-overlay" id="modal-nieuwe-opdracht" role="dialog" aria-modal="true" aria-labelledby="modal-nieuwe-opdracht-title" aria-hidden="true">
    <div class="dash-modal" role="document">
        <div class="dash-modal-hd">
            <h2 id="modal-nieuwe-opdracht-title">Nieuwe Opdracht</h2>
            <button type="button" class="dash-close" id="modal-nieuwe-opdracht-close" aria-label="Sluiten">&times;</button>
        </div>
        <div class="dash-modal-bd">
            <div class="dash-step is-active" data-step="1">
                <p class="dash-muted" style="margin-top:0;">Stap 1 — Type opdracht <span style="font-weight:400;">(tik een optie om door te gaan)</span></p>
                <button type="button" class="dash-opt" data-main="snel">
                    <i class="fas fa-taxi" style="color:#dd6b20;width:28px;text-align:center;"></i>
                    <div><strong>Optie A — Snelle rit</strong><span>Taxi / koerier</span></div>
                </button>
                <button type="button" class="dash-opt" data-main="touringcar">
                    <i class="fas fa-bus" style="color:#003366;width:28px;text-align:center;"></i>
                    <div><strong>Optie B — Touringcar</strong><span>Dagtocht, enkele rit of retour (offerte)</span></div>
                </button>
                <button type="button" class="dash-opt" data-main="trein">
                    <i class="fas fa-train" style="color:#805ad5;width:28px;text-align:center;"></i>
                    <div><strong>Optie C — Treinstremming</strong><span>Vaste prijs, direct naar planbord</span></div>
                </button>
                <a href="buitenland/index.php" class="dash-opt dash-opt-buitenland">
                    <i class="fas fa-globe-europe" style="color:#0d9488;width:28px;text-align:center;"></i>
                    <div><strong>Optie D — Buitenland</strong><span>Offerte met km NL/DE, overnachting; zelfde doorstroom als offerte</span></div>
                </a>
                <div class="dash-actions">
                    <button type="button" class="dash-btn dash-btn-sec" data-actie="annuleer">Annuleren</button>
                </div>
            </div>

            <div class="dash-step" data-step="2">
                <p class="dash-muted" style="margin-top:0;">Stap 2 — Snelle rit <span style="font-weight:400;">(tik een optie)</span></p>
                <div id="dash-step2-snel" style="display:flex; flex-direction:column; gap:12px;">
                    <button type="button" class="dash-opt" data-snel="direct">
                        <i class="fas fa-bolt" style="color:#38a169;width:28px;text-align:center;"></i>
                        <div><strong>Direct inplannen</strong><span>Meteen op het live planbord, buiten sales</span></div>
                    </button>
                    <button type="button" class="dash-opt" data-snel="sales_rit" style="border-left-color:#dd6b20;">
                        <i class="fas fa-handshake" style="color:#dd6b20;width:28px;text-align:center;"></i>
                        <div><strong>Rit + Sales-pijplijn</strong><span>Zelfde rit op planbord, ook zichtbaar bij Offertes &amp; Sales</span></div>
                    </button>
                    <button type="button" class="dash-opt" data-snel="offerte">
                        <i class="fas fa-file-invoice" style="color:#2b6cb0;width:28px;text-align:center;"></i>
                        <div><strong>Klassieke offerte</strong><span>Calculatie / touringcar-offerte (nog geen rit)</span></div>
                    </button>
                </div>
                <div class="dash-actions">
                    <button type="button" class="dash-btn dash-btn-sec" data-actie="terug">Terug</button>
                </div>
            </div>

            <div class="dash-step" data-step="3">
                <p class="dash-muted" style="margin-top:0;">Stap 3 — Ritgegevens</p>
                <div class="dash-seg" id="dash_klant_mode_row">
                    <label><input type="radio" name="dash_klant_mode" id="dash_klant_mode_passant" value="passant" checked> Onbekende klant / passant</label>
                    <label><input type="radio" name="dash_klant_mode" id="dash_klant_mode_best" value="bestaand"> Bestaande klant (zoeken)</label>
                </div>
                <div class="dash-row" id="dash_row_klant_zoek" style="display:none;">
                    <label for="dash_klant_zoek">Zoek klant</label>
                    <div class="dash-zoek-wrap">
                        <input type="text" id="dash_klant_zoek" autocomplete="off" maxlength="120" placeholder="Typ minimaal 2 letters (bedrijf, naam of e-mail)…">
                        <input type="hidden" id="dash_klant_id" value="">
                        <div id="dash_klant_resultaten" role="listbox"></div>
                    </div>
                    <p class="dash-hint">Na selectie worden de klantgegevens ingevuld. E-mail is nodig voor iDEAL.</p>
                </div>
                <div class="dash-row" id="dash_row_klant_label">
                    <label for="dash_klant_label">Klantnaam / bedrijf *</label>
                    <input type="text" id="dash_klant_label" autocomplete="organization" maxlength="200">
                </div>
                <div class="dash-row">
                    <label for="dash_email">E-mailadres *</label>
                    <input type="email" id="dash_email" autocomplete="email" maxlength="120">
                </div>
                <div class="dash-row">
                    <label for="dash_datum">Datum heenrit *</label>
                    <input type="date" id="dash_datum">
                </div>
                <div class="dash-row">
                    <div class="dash-time-card">
                        <span class="dash-time-card-label"><i class="far fa-clock"></i> Vertrektijd (24-uurs)</span>
                        <div class="dash-time-grid">
                            <select id="dash_tijd_uur" aria-label="Uur heenrit"><?php echo $dashUurOptions; ?></select>
                            <span class="dash-time-sep">:</span>
                            <select id="dash_tijd_min" aria-label="Minuten heenrit">
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                            </select>
                        </div>
                        <p class="dash-time-hint">Kies uur en kwartier — duidelijk en gelijk aan het planbord (stappen van 15 minuten).</p>
                    </div>
                </div>
                <div class="dash-row" id="dash_row_voertuig">
                    <label for="dash_voertuig_type">Voertuig</label>
                    <select id="dash_voertuig_type">
                        <option value="taxi">Taxi / koerier</option>
                        <option value="rolstoel">Rolstoelbus</option>
                        <option value="touring">Touringcar</option>
                    </select>
                </div>
                <div class="dash-row" id="dash_retour_toggle_row" style="display:none;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:600;color:#003366;text-transform:none;">
                        <input type="checkbox" id="dash_is_retour" style="width:18px;height:18px;">
                        Retourrit toevoegen (zelfde route terug, ander tijdstip)
                    </label>
                </div>
                <div class="dash-retour-box" id="dash_retour_velden" style="display:none;">
                    <h4><i class="fas fa-exchange-alt"></i> Retour</h4>
                    <div class="dash-row">
                        <label for="dash_retour_datum">Datum retour *</label>
                        <input type="date" id="dash_retour_datum">
                    </div>
                    <div class="dash-row">
                        <div class="dash-time-card" style="background:linear-gradient(135deg,#fff 0%,#f0f7ff 100%);">
                            <span class="dash-time-card-label"><i class="far fa-clock"></i> Vertrektijd retour (24-uurs)</span>
                            <div class="dash-time-grid">
                                <select id="dash_retour_tijd_uur" aria-label="Uur retour"><?php echo $dashUurOptions; ?></select>
                                <span class="dash-time-sep">:</span>
                                <select id="dash_retour_tijd_min" aria-label="Minuten retour">
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
                                </select>
                            </div>
                            <p class="dash-time-hint">Adressen worden automatisch omgedraaid (bestemming → vertrek).</p>
                        </div>
                    </div>
                </div>
                <div class="dash-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label for="dash_geschatte_pax">Aantal personen (PAX) *</label>
                        <input type="number" id="dash_geschatte_pax" min="1" max="999" step="1" value="1" required>
                    </div>
                    <div>
                        <label for="dash_prijsafspraak">Bedrag (€)</label>
                        <input type="text" id="dash_prijsafspraak" inputmode="decimal" placeholder="Optioneel, bv. 45,50">
                    </div>
                </div>
                <div class="dash-row">
                    <label for="dash_betaalwijze">Betaalwijze</label>
                    <select id="dash_betaalwijze">
                        <option value="Contant">Contant</option>
                        <option value="Pin">Pin</option>
                        <option value="Op Rekening Vast">Op rekening (vast bedrag)</option>
                        <option value="Op Rekening Meter">Op rekening (op meter)</option>
                        <option value="iDEAL">iDEAL (factuur + betaallink; rit komt op planbord)</option>
                    </select>
                    <p class="dash-hint" id="dash_ideal_hint" style="display:none;">iDEAL: vul een bedrag in. Er wordt een factuurnummer toegekend en (bij ingestelde Mollie-key) een betaallink toegevoegd aan de rit.</p>
                    <p class="dash-hint" id="dash_ideal_retour_hint" style="display:none;">Met retour: vul <strong>één totaalbedrag</strong> voor heen én terug — op de factuur staan beide ritten; er is maar <strong>één</strong> iDEAL-betaling en dezelfde link staat bij beide ritregels.</p>
                </div>
                <div class="dash-row">
                    <label for="dash_bijzonderheden">Bijzonderheden / wensen</label>
                    <textarea id="dash_bijzonderheden" maxlength="2000" placeholder="Bijv. rolstoel, extra bagage, specifieke instructies…"></textarea>
                </div>
                <div class="dash-row">
                    <label for="vertrek_adres">Vertrekadres *</label>
                    <input type="text" id="vertrek_adres" maxlength="2000" placeholder="Typ adres (Google-aanvulling indien geconfigureerd)" autocomplete="street-address">
                </div>
                <div class="dash-row">
                    <label for="bestemming_adres">Bestemmingsadres *</label>
                    <input type="text" id="bestemming_adres" maxlength="2000" placeholder="Typ adres" autocomplete="street-address">
                </div>
                <?php if ($googleMapsKey === ''): ?>
                    <p class="dash-hint" style="margin-top:-4px;">Adresaanvulling: zet <code>GOOGLE_MAPS_API_KEY</code> in je <code>.env</code> en zet in Google Cloud de <strong>Places API</strong> aan voor dit project.</p>
                <?php endif; ?>
                <div class="dash-actions">
                    <button type="button" class="dash-btn dash-btn-sec" data-actie="terug">Terug</button>
                    <button type="button" class="dash-btn dash-btn-prim" id="dash-submit">Opslaan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="dash-toast" role="status" aria-live="polite"></div>

<script>
window.dashMapsReady = function () { window.__dashMapsLoaded = true; };
</script>
<?php if ($googleMapsKey !== ''): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($googleMapsKey, ENT_QUOTES, 'UTF-8'); ?>&libraries=places&language=nl&region=NL&callback=dashMapsReady" async defer></script>
<?php endif; ?>

<script>
(function () {
    var csrf = <?php echo json_encode($dashCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var hasMapsKey = <?php echo $googleMapsKey !== '' ? 'true' : 'false'; ?>;
    var overlay = document.getElementById('modal-nieuwe-opdracht');
    var openBtn = document.getElementById('btn-open-nieuwe-opdracht');
    var closeBtn = document.getElementById('modal-nieuwe-opdracht-close');
    var toast = document.getElementById('dash-toast');
    var main = null;
    var snel = null;
    var step = 1;
    var mapsBound = false;
    var zoekTimer = null;

    function setStep(n) {
        step = n;
        overlay.querySelectorAll('.dash-step').forEach(function (el) {
            el.classList.toggle('is-active', parseInt(el.getAttribute('data-step'), 10) === n);
        });
        if (n === 3) {
            syncFormVoorType();
            requestAnimationFrame(function () {
                scheduleBindMaps();
            });
        }
    }

    function wantGepland() {
        return main === 'trein' || (main === 'snel' && (snel === 'direct' || snel === 'sales_rit'));
    }

    function syncFormVoorType() {
        var rowV = document.getElementById('dash_row_voertuig');
        var sel = document.getElementById('dash_voertuig_type');
        var retRow = document.getElementById('dash_retour_toggle_row');
        var retVel = document.getElementById('dash_retour_velden');
        if (main === 'trein') {
            rowV.style.display = 'none';
        } else {
            rowV.style.display = 'block';
            if (main === 'touringcar') sel.value = 'touring';
            else if (main === 'snel') sel.value = 'taxi';
        }
        if (wantGepland()) {
            retRow.style.display = 'block';
        } else {
            retRow.style.display = 'none';
            retVel.style.display = 'none';
            document.getElementById('dash_is_retour').checked = false;
        }
        updateIdealHint();
    }

    function updateIdealHint() {
        var h = document.getElementById('dash_ideal_hint');
        var hr = document.getElementById('dash_ideal_retour_hint');
        var b = document.getElementById('dash_betaalwijze');
        var ret = document.getElementById('dash_is_retour');
        if (!h || !b) return;
        var ideal = wantGepland() && b.value === 'iDEAL';
        h.style.display = ideal ? 'block' : 'none';
        if (hr) {
            hr.style.display = (ideal && ret && ret.checked) ? 'block' : 'none';
        }
    }

    function bindPlacesOnce() {
        if (!hasMapsKey || mapsBound) return;
        if (!window.google || !google.maps || !google.maps.places) return;
        var v = document.getElementById('vertrek_adres');
        var b = document.getElementById('bestemming_adres');
        if (!v || !b) return;
        var opts = { componentRestrictions: { country: 'nl' } };
        try {
            if (typeof google.maps.places.Autocomplete === 'function') {
                new google.maps.places.Autocomplete(v, opts);
                new google.maps.places.Autocomplete(b, opts);
            }
            mapsBound = true;
        } catch (e) {
            if (window.console && console.warn) console.warn('Google Places:', e);
        }
    }

    function scheduleBindMaps() {
        if (!hasMapsKey || mapsBound) return;
        var tries = 0;
        function tick() {
            tries += 1;
            if (mapsBound || tries > 60) return;
            bindPlacesOnce();
            if (!mapsBound) setTimeout(tick, 120);
        }
        setTimeout(tick, 60);
    }

    function openModal() {
        main = null;
        snel = null;
        step = 1;
        overlay.querySelectorAll('.dash-opt').forEach(function (o) { o.classList.remove('is-selected'); });
        document.getElementById('dash_klant_mode_passant').checked = true;
        document.getElementById('dash_klant_zoek').value = '';
        document.getElementById('dash_klant_id').value = '';
        document.getElementById('dash_klant_label').value = '';
        document.getElementById('dash_email').value = '';
        document.getElementById('dash_datum').value = '';
        document.getElementById('dash_tijd_uur').value = '09';
        document.getElementById('dash_tijd_min').value = '00';
        document.getElementById('dash_retour_datum').value = '';
        document.getElementById('dash_retour_tijd_uur').value = '17';
        document.getElementById('dash_retour_tijd_min').value = '00';
        document.getElementById('dash_is_retour').checked = false;
        document.getElementById('dash_retour_velden').style.display = 'none';
        document.getElementById('vertrek_adres').value = '';
        document.getElementById('bestemming_adres').value = '';
        document.getElementById('dash_geschatte_pax').value = '1';
        document.getElementById('dash_prijsafspraak').value = '';
        document.getElementById('dash_betaalwijze').value = 'Contant';
        document.getElementById('dash_bijzonderheden').value = '';
        document.getElementById('dash_klant_resultaten').style.display = 'none';
        document.getElementById('dash_klant_resultaten').innerHTML = '';
        document.getElementById('dash-step2-snel').style.display = 'block';
        toggleKlantMode();
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        setStep(1);
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('is-on');
        setTimeout(function () { toast.classList.remove('is-on'); }, 5500);
    }

    function goFromStep1() {
        if (!main) return;
        if (main === 'snel') {
            snel = null;
            overlay.querySelectorAll('[data-snel]').forEach(function (b) { b.classList.remove('is-selected'); });
            setStep(2);
        } else {
            snel = '';
            setStep(3);
        }
    }

    function goFromStep2() {
        setStep(3);
    }

    function toggleKlantMode() {
        var best = document.getElementById('dash_klant_mode_best').checked;
        document.getElementById('dash_row_klant_zoek').style.display = best ? 'block' : 'none';
        document.getElementById('dash_row_klant_label').style.display = best ? 'none' : 'block';
        if (best) {
            document.getElementById('dash_klant_label').value = '';
        } else {
            document.getElementById('dash_klant_id').value = '';
            document.getElementById('dash_klant_zoek').value = '';
        }
    }

    overlay.querySelectorAll('[data-main]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            overlay.querySelectorAll('[data-main]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            main = btn.getAttribute('data-main');
            goFromStep1();
        });
    });

    overlay.querySelectorAll('[data-snel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            overlay.querySelectorAll('[data-snel]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            snel = btn.getAttribute('data-snel');
            goFromStep2();
        });
    });

    document.getElementById('dash_klant_mode_passant').addEventListener('change', toggleKlantMode);
    document.getElementById('dash_klant_mode_best').addEventListener('change', toggleKlantMode);
    document.getElementById('dash_betaalwijze').addEventListener('change', updateIdealHint);
    document.getElementById('dash_is_retour').addEventListener('change', function () {
        document.getElementById('dash_retour_velden').style.display = this.checked ? 'block' : 'none';
        updateIdealHint();
    });

    var zoekInput = document.getElementById('dash_klant_zoek');
    var resBox = document.getElementById('dash_klant_resultaten');

    zoekInput.addEventListener('input', function () {
        var q = zoekInput.value.trim();
        if (zoekTimer) clearTimeout(zoekTimer);
        if (q.length < 2) {
            resBox.style.display = 'none';
            resBox.innerHTML = '';
            return;
        }
        zoekTimer = setTimeout(function () {
            fetch('ajax_zoek_klant.php?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!Array.isArray(data)) return;
                    resBox.innerHTML = '';
                    if (data.length === 0) {
                        resBox.style.display = 'block';
                        var d0 = document.createElement('div');
                        d0.textContent = 'Geen resultaten';
                        resBox.appendChild(d0);
                        return;
                    }
                    data.forEach(function (k) {
                        var div = document.createElement('div');
                        div.textContent = k.weergave_naam + (k.plaats ? ' — ' + k.plaats : '');
                        div.addEventListener('click', function () {
                            document.getElementById('dash_klant_id').value = String(k.id);
                            document.getElementById('dash_email').value = (k.email || '').trim();
                            document.getElementById('dash_klant_label').value = k.weergave_naam || '';
                            resBox.style.display = 'none';
                            zoekInput.value = k.weergave_naam || '';
                        });
                        resBox.appendChild(div);
                    });
                    resBox.style.display = 'block';
                }).catch(function () {});
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!resBox || resBox.style.display === 'none') return;
        var wrap = document.querySelector('.dash-zoek-wrap');
        if (wrap && !wrap.contains(e.target)) resBox.style.display = 'none';
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    overlay.querySelectorAll('[data-actie="annuleer"]').forEach(function (b) {
        b.addEventListener('click', closeModal);
    });
    overlay.querySelectorAll('[data-actie="terug"]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (step === 3) {
                if (main === 'snel') setStep(2);
                else setStep(1);
            } else if (step === 2) {
                setStep(1);
            }
        });
    });

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    document.getElementById('dash-submit').addEventListener('click', function () {
        var btn = this;
        var mode = document.getElementById('dash_klant_mode_best').checked ? 'bestaand' : 'passant';
        var kid = parseInt(document.getElementById('dash_klant_id').value, 10) || 0;
        var kl = document.getElementById('dash_klant_label').value.trim();
        var em = document.getElementById('dash_email').value.trim();
        var dt = document.getElementById('dash_datum').value;
        var tm = document.getElementById('dash_tijd_uur').value + ':' + document.getElementById('dash_tijd_min').value;
        var va = document.getElementById('vertrek_adres').value.trim();
        var ba = document.getElementById('bestemming_adres').value.trim();
        var pax = document.getElementById('dash_geschatte_pax').value.trim();
        var pr = document.getElementById('dash_prijsafspraak').value.trim();
        var bw = document.getElementById('dash_betaalwijze').value;
        var bijz = document.getElementById('dash_bijzonderheden').value.trim();
        var vType = document.getElementById('dash_voertuig_type').value;

        if (mode === 'bestaand' && kid <= 0) {
            alert('Zoek en selecteer een bestaande klant, of kies passant.');
            return;
        }
        if (mode === 'passant' && (!kl || !em)) {
            alert('Vul klantnaam/bedrijf en e-mail in.');
            return;
        }
        if (mode === 'bestaand' && !em) {
            alert('Deze klant heeft geen e-mail in het systeem. Vul of corrigeer het e-mailadres.');
            return;
        }
        if (!dt || !tm || !va || !ba) {
            alert('Vul datum, tijd en beide adressen in.');
            return;
        }
        if (!pax || parseInt(pax, 10) < 1) {
            alert('Vul het aantal personen in (minimaal 1).');
            return;
        }
        if (wantGepland() && bw === 'iDEAL' && pr === '') {
            alert('Voor iDEAL is een bedrag verplicht.');
            return;
        }
        var isRet = document.getElementById('dash_is_retour').checked;
        var rdt = document.getElementById('dash_retour_datum').value;
        var rtm = document.getElementById('dash_retour_tijd_uur').value + ':' + document.getElementById('dash_retour_tijd_min').value;
        if (wantGepland() && isRet) {
            if (!rdt || rtm.length < 4) {
                alert('Vul datum en tijd voor de retourrit in.');
                return;
            }
        }

        var payload = {
            csrf: csrf,
            main_type: main,
            snel_subtype: main === 'snel' ? snel : '',
            klant_mode: mode,
            klant_id: kid,
            klant_label: kl,
            email: em,
            datum: dt,
            tijd: tm,
            vertrek_adres: va,
            bestemming_adres: ba,
            voertuig_type: vType,
            geschatte_pax: pax,
            prijsafspraak: pr,
            betaalwijze: bw,
            bijzonderheden: bijz,
            is_retour: wantGepland() && isRet,
            retour_datum: (wantGepland() && isRet) ? rdt : '',
            retour_tijd: (wantGepland() && isRet) ? rtm : ''
        };
        btn.disabled = true;
        fetch('dashboard.php?ajax=nieuwe_opdracht', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            btn.disabled = false;
            if (x.j && x.j.ok) {
                showToast(x.j.message || 'Opgeslagen.');
                if (x.j.ideal_wizard_url && window.confirm('Open de factuur-wizard om het PDF-voorbeeld te controleren en iDEAL te activeren?')) {
                    window.open(x.j.ideal_wizard_url, '_blank', 'noopener,noreferrer');
                } else if (x.j.ideal_url && window.confirm('iDEAL-link beschikbaar. In nieuw tabblad openen?')) {
                    window.open(x.j.ideal_url, '_blank', 'noopener,noreferrer');
                }
                if (x.j.calculaties_url && window.confirm('Naar Offertes & Sales om het nieuwe dossier te bekijken?')) {
                    window.location.href = x.j.calculaties_url;
                    return;
                }
                closeModal();
            } else {
                alert((x.j && x.j.error) ? x.j.error : 'Opslaan mislukt.');
            }
        }).catch(function () {
            btn.disabled = false;
            alert('Netwerkfout. Probeer opnieuw.');
        });
    });

})();
</script>

<?php include 'includes/footer.php'; ?>
