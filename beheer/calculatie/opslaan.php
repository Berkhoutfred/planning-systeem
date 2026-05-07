<?php
// Bestand: beheer/calculatie/opslaan.php
// Tenant-safe insert van nieuwe calculaties + regels; instellingen via tenant_calculatie_instellingen_merged().

declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';

$appDebug = in_array(
    strtolower((string) env_value('APP_DEBUG', '')),
    ['1', 'true', 'yes'],
    true
);
if ($appDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da;'><strong>Fout:</strong> Tenant context ontbreekt.</div>");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: maken.php');
    exit;
}

$defaultRegelLabels = [
    't_garage' => 'Vertrek Garage',
    't_voorstaan' => 'Naar grens',
    't_grens2' => 'Tweede grens',
    't_vertrek_klant' => 'Vertrekadres',
    't_aankomst_best' => 'Bestemming',
    't_retour_garage_heen' => 'Retour garage (heen)',
    't_garage_rit2' => 'Garage rit 2',
    't_voorstaan_rit2' => 'Voorstaan rit 2',
    't_vertrek_best' => 'Vertrek (Terug)',
    't_retour_klant' => 'Retour Klant',
    't_retour_garage' => 'Terug in Garage',
];

try {
    $instellingen = tenant_calculatie_instellingen_merged($pdo, $tenantId);
    $btwMul = 1 + ($instellingen['btw_nl'] / 100.0);

    $allowedRittypes = [
        'dagtocht',
        'schoolreis',
        'enkel',
        'brenghaal',
        'trein',
        'meerdaags',
        'buitenland',
    ];
    $rittypeRaw = (string) ($_POST['rittype'] ?? 'dagtocht');
    $rittype = in_array($rittypeRaw, $allowedRittypes, true) ? $rittypeRaw : 'dagtocht';

    $klantId = !empty($_POST['klant_id']) ? (int) $_POST['klant_id'] : 0;
    $contactId = !empty($_POST['contact_id']) ? (int) $_POST['contact_id'] : null;
    $afdelingId = !empty($_POST['afdeling_id']) ? (int) $_POST['afdeling_id'] : null;
    $passagiers = !empty($_POST['passagiers']) ? (int) $_POST['passagiers'] : 0;

    $ritDatumRaw = trim((string) ($_POST['rit_datum'] ?? ''));
    $rit_datum = ($ritDatumRaw !== '' && strtotime($ritDatumRaw) !== false)
        ? date('Y-m-d', strtotime($ritDatumRaw))
        : date('Y-m-d');

    $ritDatumEindRaw = trim((string) ($_POST['rit_datum_eind'] ?? ''));
    $rit_datum_eind = ($ritDatumEindRaw !== '' && strtotime($ritDatumEindRaw) !== false)
        ? date('Y-m-d', strtotime($ritDatumEindRaw))
        : $rit_datum;

    $hoofdbusId = !empty($_POST['voertuig_id']) ? (int) $_POST['voertuig_id'] : null;

    $extraBussenArray = [];
    foreach (array_keys($_POST) as $key) {
        if (str_starts_with((string) $key, 'bus_extra_') && !empty($_POST[$key])) {
            $extraBussenArray[] = (int) $_POST[$key];
        }
    }
    $extraBussenArray = array_values(array_unique(array_filter($extraBussenArray, static fn (int $v): bool => $v > 0)));
    $extraVoertuigenString = $extraBussenArray !== [] ? implode(',', $extraBussenArray) : null;

    $totaalKm = isset($_POST['total_km']) && $_POST['total_km'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['total_km'])
        : 0.0;
    $totaalUren = isset($_POST['total_uren']) && $_POST['total_uren'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['total_uren'])
        : 0.0;

    $formPrijsRaw = isset($_POST['verkoopprijs']) && $_POST['verkoopprijs'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['verkoopprijs'])
        : 0.0;
    // maken.js vult verkoopprijs als exclusief BTW; merged btw_nl gebruiken als ooit incl. wordt gepost (hidden vlag).
    $prijsIsExcl = !isset($_POST['verkoopprijs_is_inclusief_btw']) || $_POST['verkoopprijs_is_inclusief_btw'] !== '1';
    $prijsExcl = $prijsIsExcl ? $formPrijsRaw : ($btwMul > 0.0 ? $formPrijsRaw / $btwMul : $formPrijsRaw);

    $kmTussen = isset($_POST['km_tussen']) && $_POST['km_tussen'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['km_tussen'])
        : 0.0;
    $kmNl = isset($_POST['km_nl']) && $_POST['km_nl'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['km_nl'])
        : 0.0;
    $kmDe = isset($_POST['km_de']) && $_POST['km_de'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['km_de'])
        : 0.0;
    $kmCh = isset($_POST['km_ch']) && $_POST['km_ch'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['km_ch'])
        : 0.0;
    $kmOv = isset($_POST['km_ov']) && $_POST['km_ov'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['km_ov'])
        : 0.0;
    $instructie = (string) ($_POST['instructie_kantoor'] ?? '');

    require_once __DIR__ . '/includes/calculatie_meta.php';
    $metaPack = calculatie_parse_meta_from_post($_POST, $rittype);
    $instructie = calculatie_append_buitenland_dagprogramma($instructie, $rittype, $_POST);
    $instructie = calculatie_append_tussendagen_to_instructie($instructie, $metaPack['tussendagen_json']);

    $labels = $_POST['label'] ?? [];
    $times = $_POST['time'] ?? [];
    $addrs = $_POST['addr'] ?? [];
    $kms = $_POST['km'] ?? [];
    if (!is_array($labels)) {
        $labels = [];
    }
    if (!is_array($times)) {
        $times = [];
    }
    if (!is_array($addrs)) {
        $addrs = [];
    }
    if (!is_array($kms)) {
        $kms = [];
    }

    // Legacy-kolommen (NOT NULL in veel installaties): vertrek_datum, vertrek_locatie, bestemming
    $addrVertrekKlant = isset($addrs['t_vertrek_klant']) ? trim((string) $addrs['t_vertrek_klant']) : '';
    $addrBestemming = isset($addrs['t_aankomst_best']) ? trim((string) $addrs['t_aankomst_best']) : '';
    $addrGarage = isset($addrs['t_garage']) ? trim((string) $addrs['t_garage']) : '';

    $vertrekLocatie = $addrVertrekKlant !== '' ? $addrVertrekKlant : ($addrGarage !== '' ? $addrGarage : 'Onbekend');
    $bestemmingStr = $addrBestemming !== '' ? $addrBestemming : 'Onbekend';
    $vertrekLocatie = function_exists('mb_substr')
        ? mb_substr($vertrekLocatie, 0, 255, 'UTF-8')
        : substr($vertrekLocatie, 0, 255);
    $bestemmingStr = function_exists('mb_substr')
        ? mb_substr($bestemmingStr, 0, 255, 'UTF-8')
        : substr($bestemmingStr, 0, 255);

    $tijdVk = isset($times['t_vertrek_klant']) ? trim((string) $times['t_vertrek_klant']) : '';
    if ($tijdVk !== '' && strlen($tijdVk) === 5) {
        $tijdVk .= ':00';
    }
    if ($tijdVk === '' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $tijdVk)) {
        $tijdVk = '00:00:00';
    }
    try {
        $vertrekDatumSql = (new DateTimeImmutable($rit_datum . ' ' . $tijdVk))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        $vertrekDatumSql = $rit_datum . ' 00:00:00';
    }

    if ($klantId <= 0) {
        throw new RuntimeException('Geen geldige klant geselecteerd.');
    }

    $pdo->beginTransaction();

    $stmtKlant = $pdo->prepare(
        'SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1'
    );
    $stmtKlant->execute([$klantId, $tenantId]);
    $klantRow = $stmtKlant->fetch(PDO::FETCH_ASSOC);
    if (!$klantRow) {
        throw new RuntimeException('De geselecteerde klant hoort niet bij deze tenant.');
    }

    $titelInput = trim((string) ($_POST['titel'] ?? ''));
    if ($titelInput !== '') {
        $titel = $titelInput;
    } else {
        $displayNaam = !empty($klantRow['bedrijfsnaam'])
            ? (string) $klantRow['bedrijfsnaam']
            : trim((string) ($klantRow['voornaam'] ?? '') . ' ' . (string) ($klantRow['achternaam'] ?? ''));
        $displayNaam = trim($displayNaam);
        $titel = $displayNaam !== ''
            ? 'Offerte – ' . $displayNaam . ' (' . $rit_datum . ')'
            : 'Offerte ' . $rit_datum;
    }
    $titel = function_exists('mb_substr')
        ? mb_substr($titel, 0, 150, 'UTF-8')
        : substr($titel, 0, 150);

    if ($contactId !== null) {
        $stmtContact = $pdo->prepare(
            'SELECT id FROM klant_contactpersonen WHERE id = ? AND klant_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmtContact->execute([$contactId, $klantId, $tenantId]);
        if (!$stmtContact->fetchColumn()) {
            throw new RuntimeException('De geselecteerde contactpersoon hoort niet bij deze klant/tenant.');
        }
    }

    if ($afdelingId !== null) {
        $stmtAfdeling = $pdo->prepare(
            'SELECT id FROM klant_afdelingen WHERE id = ? AND klant_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmtAfdeling->execute([$afdelingId, $klantId, $tenantId]);
        if (!$stmtAfdeling->fetchColumn()) {
            throw new RuntimeException('De geselecteerde afdeling hoort niet bij deze klant/tenant.');
        }
    }

    $alleVoertuigIds = [];
    if ($hoofdbusId !== null && $hoofdbusId > 0) {
        $alleVoertuigIds[] = $hoofdbusId;
    }
    foreach ($extraBussenArray as $extraBusId) {
        $alleVoertuigIds[] = $extraBusId;
    }
    foreach ($metaPack['tussendagen_bus_ids'] as $tid) {
        if ($tid > 0) {
            $alleVoertuigIds[] = $tid;
        }
    }
    $alleVoertuigIds = array_values(array_unique(array_filter($alleVoertuigIds, static fn ($v) => (int) $v > 0)));

    if ($alleVoertuigIds !== []) {
        $placeholders = implode(',', array_fill(0, count($alleVoertuigIds), '?'));
        $sqlVoertuig = "SELECT COUNT(*) FROM calculatie_voertuigen WHERE tenant_id = ? AND id IN ($placeholders)";
        $stmtVoertuig = $pdo->prepare($sqlVoertuig);
        $stmtVoertuig->execute(array_merge([$tenantId], $alleVoertuigIds));
        $gevonden = (int) $stmtVoertuig->fetchColumn();

        if ($gevonden !== count($alleVoertuigIds)) {
            throw new RuntimeException('Een of meer geselecteerde voertuigen zijn niet geldig voor deze tenant.');
        }
    }

    $hasKmCh = calculatie_db_has_column($pdo, 'calculaties', 'km_ch');
    $hasKmOv = calculatie_db_has_column($pdo, 'calculaties', 'km_ov');

    if ($hasKmCh && $hasKmOv) {
        $stmt = $pdo->prepare(
            'INSERT INTO calculaties (
                tenant_id, titel, klant_id, contact_id, afdeling_id, rittype, passagiers,
                rit_datum, rit_datum_eind,
                vertrek_datum, vertrek_locatie, bestemming,
                voertuig_id, extra_voertuigen, totaal_km, totaal_uren, prijs,
                km_tussen, km_nl, km_de, km_ch, km_ov, instructie_kantoor,
                aangemaakt_op, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), \'concept\')'
        );

        $stmt->execute([
            $tenantId,
            $titel,
            $klantId,
            $contactId,
            $afdelingId,
            $rittype,
            $passagiers,
            $rit_datum,
            $rit_datum_eind,
            $vertrekDatumSql,
            $vertrekLocatie,
            $bestemmingStr,
            $hoofdbusId,
            $extraVoertuigenString,
            $totaalKm,
            $totaalUren,
            $prijsExcl,
            $kmTussen,
            $kmNl,
            $kmDe,
            $kmCh,
            $kmOv,
            $instructie,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO calculaties (
                tenant_id, titel, klant_id, contact_id, afdeling_id, rittype, passagiers,
                rit_datum, rit_datum_eind,
                vertrek_datum, vertrek_locatie, bestemming,
                voertuig_id, extra_voertuigen, totaal_km, totaal_uren, prijs,
                km_tussen, km_nl, km_de, instructie_kantoor,
                aangemaakt_op, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), \'concept\')'
        );

        $stmt->execute([
            $tenantId,
            $titel,
            $klantId,
            $contactId,
            $afdelingId,
            $rittype,
            $passagiers,
            $rit_datum,
            $rit_datum_eind,
            $vertrekDatumSql,
            $vertrekLocatie,
            $bestemmingStr,
            $hoofdbusId,
            $extraVoertuigenString,
            $totaalKm,
            $totaalUren,
            $prijsExcl,
            $kmTussen,
            $kmNl,
            $kmDe,
            $instructie,
        ]);
    }

    $calculatieId = (int) $pdo->lastInsertId();
    if ($calculatieId <= 0) {
        throw new RuntimeException('Opslaan van de calculatie is mislukt (geen ID).');
    }

    $stmtRegel = $pdo->prepare(
        'INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $mogelijkeRegels = [
        't_garage',
        't_vertrek_klant',
        't_voorstaan',
        't_grens2',
        't_aankomst_best',
        't_retour_garage_heen',
        't_garage_rit2',
        't_voorstaan_rit2',
        't_vertrek_best',
        't_retour_klant',
        't_retour_garage',
    ];

    foreach ($mogelijkeRegels as $type) {
        $labelDefault = $defaultRegelLabels[$type] ?? $type;
        $label = isset($labels[$type]) ? (string) $labels[$type] : $labelDefault;
        $label = function_exists('mb_substr')
            ? mb_substr($label, 0, 255, 'UTF-8')
            : substr($label, 0, 255);
        $tijd = isset($times[$type]) ? (string) $times[$type] : '';
        $adres = isset($addrs[$type]) ? (string) $addrs[$type] : '';
        $km = isset($kms[$type]) && $kms[$type] !== ''
            ? (float) str_replace(',', '.', (string) $kms[$type])
            : 0.0;

        if ($tijd !== '' && strlen($tijd) === 5) {
            $tijd .= ':00';
        }

        if ($adres !== '' || $tijd !== '' || $km > 0.0) {
            $stmtRegel->execute([$tenantId, $calculatieId, $type, $label, $tijd, $adres, $km]);
        }
    }

    calculatie_persist_meta_columns($pdo, $tenantId, $calculatieId, $metaPack);

    $pdo->commit();

    if (isset($_POST['naar_dashboard']) && (string) $_POST['naar_dashboard'] === '1') {
        $maand = date('n', strtotime($rit_datum));
        $jaar = date('Y', strtotime($rit_datum));
        header(
            'Location: ../calculaties.php?maand=' . $maand . '&jaar=' . $jaar . '&actie_msg='
            . urlencode('Nieuwe offerte succesvol opgeslagen')
        );
        exit;
    }

    header('Location: calculaties_bewerken.php?id=' . $calculatieId);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die(
        "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da;'><strong>Databasefout:</strong> "
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
    );
} catch (Throwable $t) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die(
        "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da;'><strong>Fout:</strong> "
        . htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
    );
}
