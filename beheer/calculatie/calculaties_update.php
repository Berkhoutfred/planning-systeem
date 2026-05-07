<?php
// Bestand: beheer/calculatie/calculaties_update.php
// Tenant-safe: alle writes alleen na lock + verificatie calculatie_id binnen huidige tenant.

declare(strict_types=1);

try {
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
        throw new RuntimeException('Tenant context ontbreekt.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
        die("<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da;'>Geen geldige aanvraag (ID ontbreekt of geen POST).</div>");
    }

    $id = (int) $_POST['id'];
    if ($id <= 0) {
        throw new RuntimeException('Ongeldig calculatie-ID.');
    }

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
    $prijsExcl = isset($_POST['verkoopprijs']) && $_POST['verkoopprijs'] !== ''
        ? (float) str_replace(',', '.', (string) $_POST['verkoopprijs'])
        : 0.0;

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

    $pdo->beginTransaction();

    $stmtLock = $pdo->prepare(
        'SELECT id FROM calculaties WHERE id = ? AND tenant_id = ? LIMIT 1 FOR UPDATE'
    );
    $stmtLock->execute([$id, $tenantId]);
    if (!$stmtLock->fetchColumn()) {
        $pdo->rollBack();
        throw new RuntimeException('Deze offerte hoort niet bij jouw tenant of bestaat niet.');
    }

    if ($klantId <= 0) {
        throw new RuntimeException('Geen geldige klant geselecteerd.');
    }

    $stmtKlant = $pdo->prepare('SELECT id FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmtKlant->execute([$klantId, $tenantId]);
    if (!$stmtKlant->fetchColumn()) {
        throw new RuntimeException('De geselecteerde klant hoort niet bij deze tenant.');
    }

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
        $sql = 'UPDATE calculaties SET
                klant_id = ?, contact_id = ?, afdeling_id = ?, rittype = ?, passagiers = ?,
                rit_datum = ?, rit_datum_eind = ?, voertuig_id = ?, extra_voertuigen = ?,
                totaal_km = ?, totaal_uren = ?, prijs = ?, km_tussen = ?, km_nl = ?, km_de = ?, km_ch = ?, km_ov = ?,
                instructie_kantoor = ?
                WHERE id = ? AND tenant_id = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $klantId,
            $contactId,
            $afdelingId,
            $rittype,
            $passagiers,
            $rit_datum,
            $rit_datum_eind,
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
            $id,
            $tenantId,
        ]);
    } else {
        $sql = 'UPDATE calculaties SET
                klant_id = ?, contact_id = ?, afdeling_id = ?, rittype = ?, passagiers = ?,
                rit_datum = ?, rit_datum_eind = ?, voertuig_id = ?, extra_voertuigen = ?,
                totaal_km = ?, totaal_uren = ?, prijs = ?, km_tussen = ?, km_nl = ?, km_de = ?,
                instructie_kantoor = ?
                WHERE id = ? AND tenant_id = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $klantId,
            $contactId,
            $afdelingId,
            $rittype,
            $passagiers,
            $rit_datum,
            $rit_datum_eind,
            $hoofdbusId,
            $extraVoertuigenString,
            $totaalKm,
            $totaalUren,
            $prijsExcl,
            $kmTussen,
            $kmNl,
            $kmDe,
            $instructie,
            $id,
            $tenantId,
        ]);
    }

    $stmtDel = $pdo->prepare('DELETE FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ?');
    $stmtDel->execute([$id, $tenantId]);

    $volgorde = [
        't_garage',
        't_voorstaan',
        't_vertrek_klant',
        't_aankomst_best',
        't_retour_garage_heen',
        't_garage_rit2',
        't_voorstaan_rit2',
        't_vertrek_best',
        't_retour_klant',
        't_retour_garage',
    ];

    $stmtRegel = $pdo->prepare(
        'INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($volgorde as $type) {
        $label = isset($labels[$type]) ? (string) $labels[$type] : $type;
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
            $stmtRegel->execute([$tenantId, $id, $type, $label, $tijd, $adres, $km]);
        }
    }

    calculatie_persist_meta_columns($pdo, $tenantId, $id, $metaPack);

    $pdo->commit();

    $maand = date('n', strtotime($rit_datum));
    $jaar = date('Y', strtotime($rit_datum));
    header('Location: ../calculaties.php?maand=' . $maand . '&jaar=' . $jaar . '&actie_msg=' . urlencode('Offerte succesvol bijgewerkt!'));
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die(
        "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da; border: 2px solid #f5c6cb; border-radius: 5px;'><strong>Databasefout:</strong><br><br>"
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
    );
} catch (Throwable $t) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die(
        "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da; border: 2px solid #f5c6cb; border-radius: 5px;'><strong>Fout:</strong><br><br>"
        . htmlspecialchars($t->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
    );
}
