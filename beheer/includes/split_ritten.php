<?php
// Bestand: beheer/includes/split_ritten.php
// Vangt een geaccepteerde calculatie op en hakt deze (indien nodig) in losse ritten voor het planbord.

if (!function_exists('split_ritten_normaliseer_tijd')) {
    /**
     * Zet DB-tijd (HH:MM of HH:MM:SS) om naar HH:MM:SS zonder dubbele :00 suffix.
     */
    function split_ritten_normaliseer_tijd(?string $tijd): string
    {
        $tijd = trim((string) $tijd);
        if ($tijd === '') {
            return '00:00:00';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $tijd, $m)) {
            $sec = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;

            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], $sec);
        }

        return '00:00:00';
    }
}

function maakParapluRittenAan($pdo, $calculatie_id)
{
    try {
        $calculatie_id = (int) $calculatie_id;
        if ($calculatie_id <= 0) {
            return false;
        }

        // 1. Bestaan deze ritten al? (dubbele klik / herhaalde bevestiging)
        $stmtTenant = $pdo->prepare('SELECT tenant_id FROM calculaties WHERE id = ? LIMIT 1');
        $stmtTenant->execute([$calculatie_id]);
        $tenant_id = (int) $stmtTenant->fetchColumn();
        if ($tenant_id <= 0) {
            return false;
        }

        $check = $pdo->prepare('SELECT id FROM ritten WHERE calculatie_id = ? AND tenant_id = ?');
        $check->execute([$calculatie_id, $tenant_id]);
        if ($check->rowCount() > 0) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT * FROM calculaties WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$calculatie_id, $tenant_id]);
        $calc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$calc) {
            return false;
        }

        $stmt_tijden = $pdo->prepare(
            "SELECT type, tijd FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ? AND type IN ('t_vertrek_klant', 't_retour_klant')"
        );
        $stmt_tijden->execute([$calculatie_id, $tenant_id]);
        $tijden = $stmt_tijden->fetchAll(PDO::FETCH_KEY_PAIR);

        $tijd_start = split_ritten_normaliseer_tijd(
            isset($tijden['t_vertrek_klant']) ? (string) $tijden['t_vertrek_klant'] : ''
        );
        $tijd_eind = split_ritten_normaliseer_tijd(
            isset($tijden['t_retour_klant']) ? (string) $tijden['t_retour_klant'] : ''
        );
        if ($tijd_eind === '00:00:00') {
            $tijd_eind = '23:59:59';
        }

        $ritDatum = (string) ($calc['rit_datum'] ?? date('Y-m-d'));
        $datum_start = $ritDatum . ' ' . $tijd_start;
        $datum_eind_calc = !empty($calc['rit_datum_eind']) ? (string) $calc['rit_datum_eind'] : $ritDatum;
        $datum_eind = $datum_eind_calc . ' ' . $tijd_eind;

        $bussen_wens = [];
        if (!empty($calc['voertuig_id'])) {
            $bussen_wens[] = (int) $calc['voertuig_id'];
        }
        if (!empty($calc['extra_voertuigen'])) {
            $extra = array_map('trim', explode(',', (string) $calc['extra_voertuigen']));
            foreach ($extra as $ex_bus) {
                if ($ex_bus !== '' && ctype_digit($ex_bus)) {
                    $bussen_wens[] = (int) $ex_bus;
                }
            }
        }

        // Geen bus gekozen: toch één planregel (anders verschijnt niets op live planbord)
        if ($bussen_wens === []) {
            $bussen_wens = [null];
        }

        $aantal_bussen = count($bussen_wens);
        $pax_totaal = (int) ($calc['passagiers'] ?? 0);
        $pax_per_bus = $aantal_bussen > 0 ? (int) floor($pax_totaal / $aantal_bussen) : 0;
        $pax_rest = $aantal_bussen > 0 ? $pax_totaal % $aantal_bussen : 0;

        $klantId = isset($calc['klant_id']) ? (int) $calc['klant_id'] : null;
        if ($klantId <= 0) {
            $klantId = null;
        }

        $insert_sql = 'INSERT INTO ritten (tenant_id, calculatie_id, klant_id, datum_start, datum_eind, instructies, voertuig_categorie_wens, paraplu_volgnummer, is_hoofdrit, geschatte_pax, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'gepland\')';
        $stmt_insert = $pdo->prepare($insert_sql);

        $volgnummer = 1;
        foreach ($bussen_wens as $index => $categorie_wens) {
            $is_hoofdrit = ($volgnummer === 1) ? 1 : 0;
            $pax_deze_bus = $pax_per_bus + (($index === $aantal_bussen - 1) ? $pax_rest : 0);

            $stmt_insert->execute([
                $tenant_id,
                $calculatie_id,
                $klantId,
                $datum_start,
                $datum_eind,
                $calc['instructie_kantoor'] ?? null,
                $categorie_wens,
                $volgnummer,
                $is_hoofdrit,
                $pax_deze_bus,
            ]);
            $volgnummer++;
        }

        return true;
    } catch (PDOException $e) {
        error_log('Fout bij splitParapluRit: ' . $e->getMessage());

        return false;
    }
}
