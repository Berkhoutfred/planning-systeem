<?php
declare(strict_types=1);

/**
 * Meta voor calculatie: tussenritten (JSON) + buitenland-module (JSON).
 */

function calculatie_db_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            return $cache[$key] = false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$dbName, $table, $column]);
        return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function calculatie_append_buitenland_dagprogramma(string $base, string $rittype, array $post): string
{
    if ($rittype !== 'buitenland') {
        return $base;
    }
    $lines = [];
    foreach (($post['dagprogramma'] ?? []) as $datumStr => $tekst) {
        $tekst = trim((string) $tekst);
        if ($tekst === '') {
            continue;
        }
        $lines[] = (string) $datumStr . ': ' . $tekst;
    }
    if ($lines === []) {
        return $base;
    }

    return $base . "\n\n[Dagprogramma]\n" . implode("\n", $lines);
}

/**
 * @return array{tussendagen_json: string|null, buitenland_json: string|null, offerte_module: string|null, tussendagen_bus_ids: int[]}
 */
function calculatie_parse_meta_from_post(array $post, string $rittype): array
{
    $enabled = !empty($post['tussendagen_enabled']);
    $datums = $post['tussendagen_datum'] ?? [];
    $items = [];
    $busExtra = [];

    if ($enabled && is_array($datums)) {
        $n = count($datums);
        $vanList = $post['tussendagen_van'] ?? [];
        $naarList = $post['tussendagen_naar'] ?? [];
        $kmList = $post['tussendagen_km'] ?? [];
        $paxList = $post['tussendagen_pax'] ?? [];
        $busList = $post['tussendagen_bus'] ?? [];

        for ($i = 0; $i < $n; $i++) {
            $d = isset($datums[$i]) ? trim((string) $datums[$i]) : '';
            if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                continue;
            }
            $van = isset($vanList[$i]) ? trim((string) $vanList[$i]) : '';
            $naar = isset($naarList[$i]) ? trim((string) $naarList[$i]) : '';
            $km = isset($kmList[$i]) ? (float) str_replace(',', '.', (string) $kmList[$i]) : 0.0;
            $pax = isset($paxList[$i]) ? max(0, (int) $paxList[$i]) : 0;
            $bid = isset($busList[$i]) ? (int) $busList[$i] : 0;
            if ($bid > 0) {
                $busExtra[] = $bid;
            }
            if ($van === '' && $naar === '' && $km <= 0 && $pax <= 0 && $bid <= 0) {
                continue;
            }
            $items[] = [
                'datum' => $d,
                'van' => $van,
                'naar' => $naar,
                'km' => round($km, 2),
                'passagiers' => $pax,
                'voertuig_id' => $bid > 0 ? $bid : null,
            ];
        }
    }

    $tussJson = null;
    if (!$enabled) {
        $tussJson = null;
    } elseif ($items !== []) {
        $payload = ['enabled' => true, 'items' => $items, 'schema' => 1];
        try {
            $tussJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $tussJson = null;
        }
    } else {
        try {
            $tussJson = json_encode(['enabled' => true, 'items' => [], 'schema' => 1], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $tussJson = null;
        }
    }

    $buitenJson = null;
    $offerteModule = null;
    if ($rittype === 'buitenland') {
        $offerteModule = 'buitenland';
        $overn = (string) ($post['buitenland_overnachting'] ?? 'klant');
        if (!in_array($overn, ['klant', 'eigen'], true)) {
            $overn = 'klant';
        }
        $bedRaw = trim((string) ($post['buitenland_overnachting_bedrag'] ?? ''));
        $bed = null;
        if ($overn === 'eigen' && $bedRaw !== '') {
            $bn = str_replace(',', '.', $bedRaw);
            if (is_numeric($bn)) {
                $bed = round((float) $bn, 2);
            }
        }
        $dpIn = $post['dagprogramma'] ?? [];
        $dagprogramma = [];
        if (is_array($dpIn)) {
            foreach ($dpIn as $datumStr => $tekst) {
                $datumStr = (string) $datumStr;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumStr)) {
                    continue;
                }
                $dagprogramma[] = [
                    'datum' => $datumStr,
                    'tekst' => trim((string) $tekst),
                ];
            }
            usort($dagprogramma, static function ($a, $b) {
                return strcmp($a['datum'], $b['datum']);
            });
        }
        $meta = [
            'module_version' => 3,
            'overnachting_door' => $overn === 'klant' ? 'klant' : 'eigen',
            'overnachting_bedrag_eur' => $bed,
            'dagprogramma' => $dagprogramma,
            'btw_scope_v1' => 'NL/DE km via hoofdformulier (km_nl/km_de)',
        ];
        try {
            $buitenJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $buitenJson = null;
        }
    }

    return [
        'tussendagen_json' => $tussJson,
        'buitenland_json' => $buitenJson,
        'offerte_module' => $offerteModule,
        'tussendagen_bus_ids' => array_values(array_unique(array_filter($busExtra, static fn (int $id): bool => $id > 0))),
    ];
}

function calculatie_append_tussendagen_to_instructie(string $base, ?string $tussendagenJson): string
{
    if ($tussendagenJson === null || $tussendagenJson === '') {
        return $base;
    }
    try {
        $d = json_decode($tussendagenJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return $base;
    }
    $items = $d['items'] ?? [];
    if (!is_array($items) || $items === []) {
        return $base;
    }
    $lines = ["\n[Tussenritten]"];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $datum = (string) ($it['datum'] ?? '');
        $van = (string) ($it['van'] ?? '');
        $naar = (string) ($it['naar'] ?? '');
        $km = $it['km'] ?? '';
        $pax = $it['passagiers'] ?? '';
        $bid = $it['voertuig_id'] ?? '';
        $lines[] = $datum . ': ' . $van . ' → ' . $naar . ' | km ' . $km . ' | pax ' . $pax . ($bid ? ' | bus#' . $bid : '');
    }
    return $base . implode("\n", $lines);
}

/**
 * UPDATE calculaties meta-kolommen indien aanwezig.
 */
function calculatie_persist_meta_columns(
    PDO $pdo,
    int $tenantId,
    int $calculatieId,
    array $metaPack
): void {
    $sets = [];
    $params = [];

    if (calculatie_db_has_column($pdo, 'calculaties', 'tussendagen_meta')) {
        $sets[] = 'tussendagen_meta = ?';
        $params[] = $metaPack['tussendagen_json'];
    }
    if (($metaPack['offerte_module'] ?? null) === 'buitenland' && calculatie_db_has_column($pdo, 'calculaties', 'offerte_module')) {
        $sets[] = 'offerte_module = ?';
        $params[] = 'buitenland';
    }

    if (calculatie_db_has_column($pdo, 'calculaties', 'buitenland_meta')) {
        $sets[] = 'buitenland_meta = ?';
        $params[] = $metaPack['buitenland_json'];
    }

    if ($sets === []) {
        return;
    }

    $params[] = $calculatieId;
    $params[] = $tenantId;

    $sql = 'UPDATE calculaties SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
