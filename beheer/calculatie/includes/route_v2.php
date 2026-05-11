<?php
declare(strict_types=1);

require_once __DIR__ . '/route_heen_segments.php';

function calculatie_route_v2_normalize_hhmm($value): string
{
    $value = trim((string) $value);
    return preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value) ? substr($value, 0, 5) : '';
}

function calculatie_route_v2_normalize_date($value): string
{
    $value = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function calculatie_route_v2_normalize_zone($value): string
{
    $value = trim((string) $value);
    return in_array($value, ['nl', 'de', 'ch', 'ov'], true) ? $value : 'nl';
}

function calculatie_route_v2_normalize_float($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    $num = str_replace(',', '.', (string) $value);
    return is_numeric($num) ? round((float) $num, 2) : 0.0;
}

function calculatie_route_v2_route1_kind(array $segment, int $index): string
{
    $returnKind = (string) ($segment['return_kind'] ?? '');
    if ($returnKind === 'rg') {
        return 'return_to_garage';
    }
    if ($returnKind === 'rk-klant') {
        return 'return_to_customer';
    }
    if ($returnKind === 'rk-garage') {
        return 'return_to_garage';
    }
    if ($index === 0) {
        return 'garage_to_customer';
    }
    return 'stop';
}

function calculatie_route_v2_route1_segments_from_rows(array $rows): array
{
    $segments = [];
    foreach ($rows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $from = trim((string) ($row['from'] ?? $row['van'] ?? ''));
        $to = trim((string) ($row['to'] ?? $row['naar'] ?? ''));
        $departAt = calculatie_route_v2_normalize_hhmm($row['depart_at'] ?? $row['vertrektijd'] ?? '');
        $arriveAt = calculatie_route_v2_normalize_hhmm($row['arrive_at'] ?? $row['aankomst_tijd'] ?? '');
        $km = calculatie_route_v2_normalize_float($row['km'] ?? 0);
        $zone = calculatie_route_v2_normalize_zone($row['zone'] ?? 'nl');
        $returnKind = trim((string) ($row['return_kind'] ?? ''));
        if ($from === '' && $to === '' && $departAt === '' && $arriveAt === '' && $km <= 0.0) {
            continue;
        }
        $segments[] = [
            'seq' => count($segments) + 1,
            'day_index' => max(0, (int) ($row['day_index'] ?? 0)),
            'kind' => calculatie_route_v2_route1_kind($row, $idx),
            'return_kind' => in_array($returnKind, ['rg', 'rk-klant', 'rk-garage'], true) ? $returnKind : '',
            'from' => $from,
            'to' => $to,
            'depart_at' => $departAt,
            'arrive_at' => $arriveAt,
            'km' => $km,
            'zone' => $zone,
        ];
    }
    return $segments;
}

function calculatie_route_v2_build_legacy_data_from_post(array $post): array
{
    $times = is_array($post['time'] ?? null) ? $post['time'] : [];
    $addrs = is_array($post['addr'] ?? null) ? $post['addr'] : [];
    $kms = is_array($post['km'] ?? null) ? $post['km'] : [];
    $types = [
        't_garage',
        't_vertrek_klant',
        't_voorstaan',
        't_grens2',
        't_aankomst_best',
        't_retour_klant',
        't_retour_garage_heen',
    ];
    $data = [];
    foreach ($types as $type) {
        $data[$type] = [
            'tijd' => calculatie_route_v2_normalize_hhmm($times[$type] ?? ''),
            'adres' => trim((string) ($addrs[$type] ?? '')),
            'km' => calculatie_route_v2_normalize_float($kms[$type] ?? 0),
        ];
    }
    return $data;
}

function calculatie_route_v2_build_route2_from_post(array $post): array
{
    $times = is_array($post['time'] ?? null) ? $post['time'] : [];
    $addrs = is_array($post['addr'] ?? null) ? $post['addr'] : [];
    $kms = is_array($post['km'] ?? null) ? $post['km'] : [];
    $map = [
        ['type' => 't_garage_rit2', 'kind' => 'garage_start'],
        ['type' => 't_voorstaan_rit2', 'kind' => 'preposition'],
        ['type' => 't_vertrek_best', 'kind' => 'route2_depart'],
        ['type' => 't_retour_klant', 'kind' => 'route2_customer'],
        ['type' => 't_retour_garage', 'kind' => 'route2_garage_end'],
    ];
    $rows = [];
    foreach ($map as $item) {
        $type = $item['type'];
        $time = calculatie_route_v2_normalize_hhmm($times[$type] ?? '');
        $address = trim((string) ($addrs[$type] ?? ''));
        $km = calculatie_route_v2_normalize_float($kms[$type] ?? 0);
        if ($time === '' && $address === '' && $km <= 0.0) {
            continue;
        }
        $rows[] = [
            'type' => $type,
            'kind' => $item['kind'],
            'time' => $time,
            'address' => $address,
            'km' => $km,
            'zone' => 'nl',
        ];
    }
    return $rows;
}

function calculatie_route_v2_build_tussendagen_from_post(array $post): array
{
    $enabled = !empty($post['tussendagen_enabled']);
    $items = [];
    $datums = is_array($post['tussendagen_datum'] ?? null) ? $post['tussendagen_datum'] : [];
    $vanList = is_array($post['tussendagen_van'] ?? null) ? $post['tussendagen_van'] : [];
    $naarList = is_array($post['tussendagen_naar'] ?? null) ? $post['tussendagen_naar'] : [];
    $tijdList = is_array($post['tussendagen_tijd'] ?? null) ? $post['tussendagen_tijd'] : [];
    $kmList = is_array($post['tussendagen_km'] ?? null) ? $post['tussendagen_km'] : [];
    $zoneList = is_array($post['tussendagen_zone'] ?? null) ? $post['tussendagen_zone'] : [];
    $count = count($datums);
    for ($i = 0; $i < $count; $i++) {
        $datum = calculatie_route_v2_normalize_date($datums[$i] ?? '');
        $van = trim((string) ($vanList[$i] ?? ''));
        $naar = trim((string) ($naarList[$i] ?? ''));
        $tijd = calculatie_route_v2_normalize_hhmm($tijdList[$i] ?? '');
        $km = calculatie_route_v2_normalize_float($kmList[$i] ?? 0);
        $zone = calculatie_route_v2_normalize_zone($zoneList[$i] ?? 'nl');
        if ($datum === '' && $van === '' && $naar === '' && $tijd === '' && $km <= 0.0) {
            continue;
        }
        $items[] = [
            'datum' => $datum,
            'tijd' => $tijd,
            'van' => $van,
            'naar' => $naar,
            'km' => $km,
            'zone' => $zone,
        ];
    }
    return [
        'enabled' => $enabled,
        'items' => $items,
    ];
}

function calculatie_route_v2_build_buitenland_from_post(array $post, string $rittype): ?array
{
    if ($rittype !== 'buitenland') {
        return null;
    }
    $overn = trim((string) ($post['buitenland_overnachting'] ?? 'klant'));
    if (!in_array($overn, ['klant', 'eigen'], true)) {
        $overn = 'klant';
    }
    $bedrag = trim((string) ($post['buitenland_overnachting_bedrag'] ?? ''));
    $dagprogrammaIn = is_array($post['dagprogramma'] ?? null) ? $post['dagprogramma'] : [];
    $dagprogramma = [];
    foreach ($dagprogrammaIn as $datum => $tekst) {
        $datum = calculatie_route_v2_normalize_date($datum);
        $tekst = trim((string) $tekst);
        if ($datum === '' && $tekst === '') {
            continue;
        }
        $dagprogramma[] = [
            'datum' => $datum,
            'tekst' => $tekst,
        ];
    }
    usort($dagprogramma, static function (array $a, array $b): int {
        return strcmp((string) ($a['datum'] ?? ''), (string) ($b['datum'] ?? ''));
    });
    return [
        'overnachting_door' => $overn,
        'overnachting_bedrag_eur' => $bedrag !== '' ? calculatie_route_v2_normalize_float($bedrag) : null,
        'dagprogramma' => $dagprogramma,
    ];
}

function calculatie_route_v2_normalize_payload(array $payload): array
{
    $rittype = trim((string) ($payload['rittype'] ?? 'dagtocht'));
    $dates = is_array($payload['dates'] ?? null) ? $payload['dates'] : [];
    $route1 = is_array($payload['route1'] ?? null) ? $payload['route1'] : [];
    $route2 = is_array($payload['route2'] ?? null) ? $payload['route2'] : [];
    $tussendagen = is_array($payload['tussendagen'] ?? null) ? $payload['tussendagen'] : ['enabled' => false, 'items' => []];
    $buitenland = $payload['buitenland'] ?? null;

    $normalizedRoute1 = [
        'label' => 'Route 1',
        'return_mode' => in_array((string) ($route1['return_mode'] ?? ''), ['rg', 'rk'], true) ? (string) $route1['return_mode'] : '',
        'segments' => calculatie_route_v2_route1_segments_from_rows(is_array($route1['segments'] ?? null) ? $route1['segments'] : []),
    ];
    $normalizedRoute2 = [
        'enabled' => !empty($route2['enabled']),
        'segments' => [],
    ];
    foreach (is_array($route2['segments'] ?? null) ? $route2['segments'] : [] as $seg) {
        if (!is_array($seg)) {
            continue;
        }
        $time = calculatie_route_v2_normalize_hhmm($seg['time'] ?? '');
        $address = trim((string) ($seg['address'] ?? ''));
        $km = calculatie_route_v2_normalize_float($seg['km'] ?? 0);
        if ($time === '' && $address === '' && $km <= 0.0) {
            continue;
        }
        $normalizedRoute2['segments'][] = [
            'type' => trim((string) ($seg['type'] ?? '')),
            'kind' => trim((string) ($seg['kind'] ?? '')),
            'time' => $time,
            'address' => $address,
            'km' => $km,
            'zone' => calculatie_route_v2_normalize_zone($seg['zone'] ?? 'nl'),
        ];
    }

    $normalizedTuss = [
        'enabled' => !empty($tussendagen['enabled']),
        'items' => [],
    ];
    foreach (is_array($tussendagen['items'] ?? null) ? $tussendagen['items'] : [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $datum = calculatie_route_v2_normalize_date($item['datum'] ?? '');
        $van = trim((string) ($item['van'] ?? ''));
        $naar = trim((string) ($item['naar'] ?? ''));
        $tijd = calculatie_route_v2_normalize_hhmm($item['tijd'] ?? '');
        $km = calculatie_route_v2_normalize_float($item['km'] ?? 0);
        if ($datum === '' && $van === '' && $naar === '' && $tijd === '' && $km <= 0.0) {
            continue;
        }
        $normalizedTuss['items'][] = [
            'datum' => $datum,
            'tijd' => $tijd,
            'van' => $van,
            'naar' => $naar,
            'km' => $km,
            'zone' => calculatie_route_v2_normalize_zone($item['zone'] ?? 'nl'),
        ];
    }

    $normalizedBuitenland = null;
    if (is_array($buitenland)) {
        $normalizedBuitenland = [
            'overnachting_door' => in_array((string) ($buitenland['overnachting_door'] ?? 'klant'), ['klant', 'eigen'], true)
                ? (string) ($buitenland['overnachting_door'] ?? 'klant')
                : 'klant',
            'overnachting_bedrag_eur' => isset($buitenland['overnachting_bedrag_eur']) && $buitenland['overnachting_bedrag_eur'] !== null && $buitenland['overnachting_bedrag_eur'] !== ''
                ? calculatie_route_v2_normalize_float($buitenland['overnachting_bedrag_eur'])
                : null,
            'dagprogramma' => [],
        ];
        foreach (is_array($buitenland['dagprogramma'] ?? null) ? $buitenland['dagprogramma'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $datum = calculatie_route_v2_normalize_date($item['datum'] ?? '');
            $tekst = trim((string) ($item['tekst'] ?? ''));
            if ($datum === '' && $tekst === '') {
                continue;
            }
            $normalizedBuitenland['dagprogramma'][] = ['datum' => $datum, 'tekst' => $tekst];
        }
    }

    return [
        'schema' => 1,
        'rittype' => $rittype,
        'dates' => [
            'start' => calculatie_route_v2_normalize_date($dates['start'] ?? ''),
            'end' => calculatie_route_v2_normalize_date($dates['end'] ?? ''),
        ],
        'route1' => $normalizedRoute1,
        'route2' => $normalizedRoute2,
        'tussendagen' => $normalizedTuss,
        'buitenland' => $normalizedBuitenland,
    ];
}

function calculatie_route_v2_decode(?string $json): ?array
{
    $json = trim((string) $json);
    if ($json === '') {
        return null;
    }
    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return null;
    }
    if (!is_array($decoded)) {
        return null;
    }
    return calculatie_route_v2_normalize_payload($decoded);
}

function calculatie_route_v2_from_post(array $post, string $rittype): ?string
{
    $candidate = calculatie_route_v2_decode((string) ($post['route_v2_json'] ?? ''));
    if ($candidate === null) {
        $legacyData = calculatie_route_v2_build_legacy_data_from_post($post);
        $candidate = calculatie_route_v2_normalize_payload([
            'schema' => 1,
            'rittype' => $rittype,
            'dates' => [
                'start' => (string) ($post['rit_datum'] ?? ''),
                'end' => (string) ($post['rit_datum_eind'] ?? $post['rit_datum'] ?? ''),
            ],
            'route1' => [
                'label' => 'Route 1',
                'return_mode' => '',
                'segments' => route_heen_segments_from_regels($legacyData),
            ],
            'route2' => [
                'enabled' => false,
                'segments' => calculatie_route_v2_build_route2_from_post($post),
            ],
            'tussendagen' => calculatie_route_v2_build_tussendagen_from_post($post),
            'buitenland' => calculatie_route_v2_build_buitenland_from_post($post, $rittype),
        ]);
    }

    try {
        return json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return null;
    }
}

function calculatie_route_v2_extract_route1_segments(?array $payload): array
{
    $segments = [];
    if (!is_array($payload) || !is_array($payload['route1']['segments'] ?? null)) {
        return $segments;
    }
    foreach ($payload['route1']['segments'] as $segment) {
        if (!is_array($segment)) {
            continue;
        }
        $segments[] = [
            'vertrektijd' => calculatie_route_v2_normalize_hhmm($segment['depart_at'] ?? ''),
            'aankomst_tijd' => calculatie_route_v2_normalize_hhmm($segment['arrive_at'] ?? ''),
            'van' => trim((string) ($segment['from'] ?? '')),
            'naar' => trim((string) ($segment['to'] ?? '')),
            'km' => (string) calculatie_route_v2_normalize_float($segment['km'] ?? 0),
            'zone' => calculatie_route_v2_normalize_zone($segment['zone'] ?? 'nl'),
            'return_kind' => in_array((string) ($segment['return_kind'] ?? ''), ['rg', 'rk-klant', 'rk-garage'], true)
                ? (string) $segment['return_kind']
                : '',
        ];
    }
    return $segments;
}
