<?php

declare(strict_types=1);

require_once __DIR__ . '/route_v2.php';

/**
 * Verschuift datums in route_v2 days/events met een vast aantal dagen (kopie naar nieuwe ritdatum).
 *
 * @param array<string,mixed> $payload Genormaliseerde route_v2 payload
 * @return array<string,mixed>
 */
function calculatie_duplicate_shift_route_v2_day_dates(array $payload, int $deltaDays): array
{
    if ($deltaDays === 0) {
        return $payload;
    }

    $days = $payload['days'] ?? null;
    if (!is_array($days)) {
        return $payload;
    }

    foreach ($days as $i => $day) {
        if (!is_array($day)) {
            continue;
        }
        $d = calculatie_route_v2_normalize_date((string) ($day['date'] ?? ''));
        if ($d !== '') {
            $days[$i]['date'] = calculatie_route_v2_date_add_days($d, $deltaDays);
        }
        $events = $day['events'] ?? null;
        if (is_array($events)) {
            foreach ($events as $j => $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $ed = calculatie_route_v2_normalize_date((string) ($ev['date'] ?? ''));
                if ($ed !== '') {
                    $events[$j]['date'] = calculatie_route_v2_date_add_days($ed, $deltaDays);
                }
            }
            $days[$i]['events'] = $events;
        }
    }

    $payload['days'] = $days;

    return $payload;
}

/**
 * Herbouwt route_v2_json voor een kopie met nieuwe ritdatum(s).
 */
function calculatie_duplicate_prepare_route_v2_json(
    ?string $routeJson,
    string $bronRitDatum,
    string $nieuweRitDatum,
    string $nieuweRitDatumEind
): ?string {
    $routeJson = trim((string) $routeJson);
    if ($routeJson === '') {
        return null;
    }

    $decoded = calculatie_route_v2_decode($routeJson);
    if (!is_array($decoded)) {
        return null;
    }

    $oldStart = calculatie_route_v2_normalize_date($bronRitDatum);
    $newStart = calculatie_route_v2_normalize_date($nieuweRitDatum);
    $newEnd = calculatie_route_v2_normalize_date($nieuweRitDatumEind);

    if ($newStart === '') {
        try {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return null;
        }
    }

    $delta = 0;
    if ($oldStart !== '') {
        $t0 = strtotime($oldStart . ' 12:00:00');
        $t1 = strtotime($newStart . ' 12:00:00');
        if ($t0 !== false && $t1 !== false) {
            $delta = (int) round(($t1 - $t0) / 86400);
        }
    }

    if ($delta !== 0) {
        $decoded = calculatie_duplicate_shift_route_v2_day_dates($decoded, $delta);
    }

    $decoded['dates'] = is_array($decoded['dates'] ?? null) ? $decoded['dates'] : [];
    $decoded['dates']['start'] = $newStart;
    $decoded['dates']['end'] = $newEnd !== '' ? $newEnd : $newStart;

    $normalized = calculatie_route_v2_normalize_payload($decoded);

    try {
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Dupliceert een calculatie inclusief calculatie_regels. Retourneert het nieuwe ID.
 *
 * @throws RuntimeException bij DB- of validatiefouten
 */
function calculatie_duplicate_voor_tenant(
    PDO $pdo,
    int $tenantId,
    int $bronCalculatieId,
    string $nieuweRitDatum,
    string $nieuweRitDatumEind,
    ?string $nieuweTitel
): int {
    if ($tenantId <= 0 || $bronCalculatieId <= 0) {
        throw new RuntimeException('Ongeldige tenant of bron-offerte.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nieuweRitDatum)) {
        throw new RuntimeException('Ongeldige ritdatum.');
    }
    if ($nieuweRitDatumEind !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nieuweRitDatumEind)) {
        throw new RuntimeException('Ongeldige einddatum.');
    }
    $eindSql = $nieuweRitDatumEind !== '' ? $nieuweRitDatumEind : $nieuweRitDatum;
    if (strcmp($eindSql, $nieuweRitDatum) < 0) {
        throw new RuntimeException('Einddatum mag niet vóór de ritdatum liggen.');
    }

    $stmt = $pdo->prepare('SELECT * FROM calculaties WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$bronCalculatieId, $tenantId]);
    $bron = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($bron)) {
        throw new RuntimeException('Bron-offerte niet gevonden.');
    }

    $meta = $pdo->query('SHOW COLUMNS FROM calculaties')->fetchAll(PDO::FETCH_ASSOC);
    if ($meta === false) {
        throw new RuntimeException('Kan tabelstructuur niet lezen.');
    }

    $nieuweTitelPrepared = null;
    if ($nieuweTitel !== null) {
        $t = trim($nieuweTitel);
        $nieuweTitelPrepared = function_exists('mb_substr')
            ? mb_substr($t, 0, 255, 'UTF-8')
            : substr($t, 0, 255);
    } else {
        $basis = trim((string) ($bron['titel'] ?? ''));
        if ($basis === '') {
            $basis = 'Offerte';
        }
        if (!preg_match('/\(kopie\)\s*$/iu', $basis)) {
            $basis .= ' (kopie)';
        }
        $nieuweTitelPrepared = $basis;
    }

    $routePrepared = calculatie_duplicate_prepare_route_v2_json(
        isset($bron['route_v2_json']) ? (string) $bron['route_v2_json'] : null,
        (string) ($bron['rit_datum'] ?? ''),
        $nieuweRitDatum,
        $eindSql
    );

    unset($bron['id']);

    $bronRitDatum = (string) ($bron['rit_datum'] ?? '');

    $bron['titel'] = $nieuweTitelPrepared;
    $bron['rit_datum'] = $nieuweRitDatum;
    $bron['rit_datum_eind'] = $eindSql;
    $bron['status'] = 'concept';
    $bron['token'] = bin2hex(random_bytes(16));
    $bron['aangemaakt_op'] = date('Y-m-d H:i:s');

    foreach ([
        'datum_offerte_verstuurd',
        'datum_bevestiging_verstuurd',
        'datum_ritopdracht_verstuurd',
        'datum_factuur_verstuurd',
    ] as $leegDatumKolom) {
        if (array_key_exists($leegDatumKolom, $bron)) {
            $bron[$leegDatumKolom] = null;
        }
    }

    if (array_key_exists('vertrek_datum', $bron)) {
        $bron['vertrek_datum'] = $nieuweRitDatum;
    }

    if ($routePrepared !== null && array_key_exists('route_v2_json', $bron)) {
        $bron['route_v2_json'] = $routePrepared;
    }

    $insertCols = [];
    $insertVals = [];
    foreach ($meta as $col) {
        $field = (string) ($col['Field'] ?? '');
        if ($field === '' || $field === 'id') {
            continue;
        }
        $extra = strtolower((string) ($col['Extra'] ?? ''));
        if (str_contains($extra, 'generated') || str_contains($extra, 'virtual')) {
            continue;
        }
        if (!array_key_exists($field, $bron)) {
            continue;
        }
        $insertCols[] = '`' . str_replace('`', '``', $field) . '`';
        $insertVals[] = $bron[$field];
    }

    if ($insertCols === []) {
        throw new RuntimeException('Geen kolommen voor INSERT.');
    }

    $pdo->beginTransaction();
    try {
        $ph = implode(',', array_fill(0, count($insertCols), '?'));
        $sql = 'INSERT INTO calculaties (' . implode(',', $insertCols) . ') VALUES (' . $ph . ')';
        $ins = $pdo->prepare($sql);
        $ins->execute($insertVals);
        $newId = (int) $pdo->lastInsertId();
        if ($newId <= 0) {
            throw new RuntimeException('Kopiëren mislukt (geen nieuw ID).');
        }

        $copyRegels = $pdo->prepare(
            'INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km)
             SELECT tenant_id, ?, type, label, tijd, adres, km
             FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ?'
        );
        $copyRegels->execute([$newId, $bronCalculatieId, $tenantId]);

        $pdo->commit();

        return $newId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
