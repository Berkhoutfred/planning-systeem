<?php
declare(strict_types=1);

require_once __DIR__ . '/route_heen_segments.php';
require_once __DIR__ . '/calculatie_feature_flags.php';

const CALCULATIE_ROUTE_V2_ROLLOVER_THRESHOLD_MIN = 180;

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

function calculatie_route_v2_time_to_minutes(string $value): ?int
{
    $value = calculatie_route_v2_normalize_hhmm($value);
    if ($value === '') {
        return null;
    }
    [$hour, $minute] = array_map('intval', explode(':', $value));
    return ($hour * 60) + $minute;
}

function calculatie_route_v2_date_add_days(string $date, int $days): string
{
    $date = calculatie_route_v2_normalize_date($date);
    if ($date === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($date);
        if ($days !== 0) {
            $dt = $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
        }
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return $date;
    }
}

function calculatie_route_v2_max_date(string $left, string $right): string
{
    if ($left === '') {
        return $right;
    }
    if ($right === '') {
        return $left;
    }
    return strcmp($left, $right) >= 0 ? $left : $right;
}

function calculatie_route_v2_normalize_event_code($value): string
{
    $code = strtoupper(trim((string) $value));
    return in_array($code, ['XD', 'RD'], true) ? $code : 'XD';
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
            'depart_day_offset' => max(0, (int) ($row['depart_day_offset'] ?? 0)),
            'arrive_day_offset' => max(0, (int) ($row['arrive_day_offset'] ?? 0)),
            'km' => $km,
            'zone' => $zone,
        ];
    }
    return $segments;
}

function calculatie_route_v2_route2_segments_from_rows(array $rows): array
{
    $segments = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $time = calculatie_route_v2_normalize_hhmm($row['time'] ?? '');
        $address = trim((string) ($row['address'] ?? ''));
        $km = calculatie_route_v2_normalize_float($row['km'] ?? 0);
        if ($time === '' && $address === '' && $km <= 0.0) {
            continue;
        }
        $segments[] = [
            'type' => trim((string) ($row['type'] ?? '')),
            'kind' => trim((string) ($row['kind'] ?? '')),
            'time' => $time,
            'time_day_offset' => max(0, (int) ($row['time_day_offset'] ?? 0)),
            'address' => $address,
            'km' => $km,
            'zone' => calculatie_route_v2_normalize_zone($row['zone'] ?? 'nl'),
        ];
    }
    return $segments;
}

function calculatie_route_v2_apply_time_rollovers(array $times): array
{
    $offsets = [];
    $dayOffset = 0;
    $previous = null;
    foreach ($times as $time) {
        $minutes = calculatie_route_v2_time_to_minutes((string) $time);
        if ($minutes !== null && $previous !== null && $minutes < ($previous - CALCULATIE_ROUTE_V2_ROLLOVER_THRESHOLD_MIN)) {
            $dayOffset++;
        }
        $offsets[] = $minutes === null ? null : $dayOffset;
        if ($minutes !== null) {
            $previous = $minutes;
        }
    }
    return $offsets;
}

function calculatie_route_v2_enrich_route1_segments_with_offsets(array $segments): array
{
    $events = [];
    foreach ($segments as $index => $segment) {
        if (!is_array($segment)) {
            continue;
        }
        $departAt = calculatie_route_v2_normalize_hhmm($segment['depart_at'] ?? '');
        $arriveAt = calculatie_route_v2_normalize_hhmm($segment['arrive_at'] ?? '');
        if ($departAt !== '') {
            $events[] = ['segment_index' => $index, 'field' => 'depart_day_offset', 'time' => $departAt];
        }
        if ($arriveAt !== '') {
            $events[] = ['segment_index' => $index, 'field' => 'arrive_day_offset', 'time' => $arriveAt];
        }
    }
    $offsetValues = calculatie_route_v2_apply_time_rollovers(array_map(static function (array $event): string {
        return $event['time'];
    }, $events));
    $firstOffset = null;
    $maxOffset = 0;
    foreach ($events as $idx => $event) {
        $offset = $offsetValues[$idx];
        if ($offset === null) {
            continue;
        }
        $segmentIndex = (int) $event['segment_index'];
        $field = (string) $event['field'];
        $segments[$segmentIndex][$field] = $offset;
        if ($firstOffset === null) {
            $firstOffset = $offset;
        }
        $maxOffset = max($maxOffset, $offset);
    }
    return [
        'segments' => $segments,
        'start_day_offset' => $firstOffset ?? 0,
        'end_day_offset' => $maxOffset,
    ];
}

function calculatie_route_v2_enrich_route2_segments_with_offsets(array $segments): array
{
    $times = array_map(static function (array $segment): string {
        return calculatie_route_v2_normalize_hhmm($segment['time'] ?? '');
    }, $segments);
    $offsets = calculatie_route_v2_apply_time_rollovers($times);
    $firstOffset = null;
    $maxOffset = 0;
    foreach ($segments as $idx => $segment) {
        $offset = $offsets[$idx] ?? null;
        if ($offset === null) {
            continue;
        }
        $segments[$idx]['time_day_offset'] = $offset;
        if ($firstOffset === null) {
            $firstOffset = $offset;
        }
        $maxOffset = max($maxOffset, $offset);
    }
    return [
        'segments' => $segments,
        'start_day_offset' => $firstOffset ?? 0,
        'end_day_offset' => $maxOffset,
    ];
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

function calculatie_route_v2_normalize_tussendagen(array $tussendagen): array
{
    $normalized = [
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
        $normalized['items'][] = [
            'datum' => $datum,
            'tijd' => $tijd,
            'van' => $van,
            'naar' => $naar,
            'km' => $km,
            'zone' => calculatie_route_v2_normalize_zone($item['zone'] ?? 'nl'),
        ];
    }
    return $normalized;
}

function calculatie_route_v2_normalize_buitenland($buitenland): ?array
{
    if (!is_array($buitenland)) {
        return null;
    }
    $normalized = [
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
        $normalized['dagprogramma'][] = ['datum' => $datum, 'tekst' => $tekst];
    }
    return $normalized;
}

function calculatie_route_v2_route1_from_days(array $days): ?array
{
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        foreach (is_array($day['routes'] ?? null) ? $day['routes'] : [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $routeIndex = max(1, (int) ($route['route_index'] ?? 0));
            $code = strtoupper(trim((string) ($route['code'] ?? '')));
            if ($routeIndex !== 1 && $code !== 'R1') {
                continue;
            }
            if (!is_array($route['segments'] ?? null)) {
                continue;
            }
            return [
                'label' => trim((string) ($route['label'] ?? 'Route 1')) ?: 'Route 1',
                'return_mode' => in_array((string) ($route['return_mode'] ?? ''), ['rg', 'rk'], true) ? (string) ($route['return_mode'] ?? '') : '',
                'segments' => calculatie_route_v2_route1_segments_from_rows($route['segments']),
            ];
        }
    }
    return null;
}

function calculatie_route_v2_route2_from_days(array $days): ?array
{
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        foreach (is_array($day['routes'] ?? null) ? $day['routes'] : [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $routeIndex = max(1, (int) ($route['route_index'] ?? 0));
            $code = strtoupper(trim((string) ($route['code'] ?? '')));
            if ($routeIndex !== 2 && $code !== 'R2') {
                continue;
            }
            return [
                'enabled' => !empty($route['enabled']),
                'segments' => calculatie_route_v2_route2_segments_from_rows(is_array($route['segments'] ?? null) ? $route['segments'] : []),
            ];
        }
    }
    return null;
}

function calculatie_route_v2_tussendagen_from_days(array $days): array
{
    $items = [];
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        $dayDate = calculatie_route_v2_normalize_date($day['date'] ?? '');
        $dayKind = trim((string) ($day['kind'] ?? ''));
        if ($dayKind !== 'extra_drive' && $dayKind !== 'rest') {
            continue;
        }
        foreach (is_array($day['events'] ?? null) ? $day['events'] : [] as $event) {
            if (!is_array($event)) {
                continue;
            }
            if (calculatie_route_v2_normalize_event_code($event['code'] ?? '') !== 'XD') {
                continue;
            }
            $items[] = [
                'datum' => calculatie_route_v2_normalize_date($event['date'] ?? '') ?: $dayDate,
                'tijd' => calculatie_route_v2_normalize_hhmm($event['time'] ?? ''),
                'van' => trim((string) ($event['from'] ?? '')),
                'naar' => trim((string) ($event['to'] ?? '')),
                'km' => calculatie_route_v2_normalize_float($event['km'] ?? 0),
                'zone' => calculatie_route_v2_normalize_zone($event['zone'] ?? 'nl'),
            ];
        }
    }
    return ['enabled' => !empty($items), 'items' => $items];
}

function calculatie_route_v2_build_days_from_compat(array $route1, array $route2, array $tussendagen, string $startDate): array
{
    $days = [];
    $route1Offsets = calculatie_route_v2_enrich_route1_segments_with_offsets($route1['segments'] ?? []);
    $route2Offsets = calculatie_route_v2_enrich_route2_segments_with_offsets($route2['segments'] ?? []);
    $dayDate = $startDate;

    if ($dayDate !== '' || !empty($route1Offsets['segments']) || !empty($route2Offsets['segments'])) {
        $routes = [];
        if (!empty($route1Offsets['segments'])) {
            $routes[] = [
                'route_index' => 1,
                'code' => 'R1',
                'label' => trim((string) ($route1['label'] ?? 'Route 1')) ?: 'Route 1',
                'mode' => 'segment_table',
                'enabled' => true,
                'return_mode' => in_array((string) ($route1['return_mode'] ?? ''), ['rg', 'rk'], true) ? (string) ($route1['return_mode'] ?? '') : '',
                'start_day_offset' => (int) ($route1Offsets['start_day_offset'] ?? 0),
                'end_day_offset' => (int) ($route1Offsets['end_day_offset'] ?? 0),
                'segments' => $route1Offsets['segments'],
            ];
        }
        if (!empty($route2Offsets['segments']) || !empty($route2['enabled'])) {
            $routes[] = [
                'route_index' => 2,
                'code' => 'R2',
                'label' => 'Route 2',
                'mode' => 'legacy_route',
                'enabled' => !empty($route2['enabled']) || !empty($route2Offsets['segments']),
                'start_day_offset' => (int) ($route2Offsets['start_day_offset'] ?? 0),
                'end_day_offset' => (int) ($route2Offsets['end_day_offset'] ?? 0),
                'segments' => $route2Offsets['segments'],
            ];
        }
        $days[] = [
            'seq' => 1,
            'day_index' => 0,
            'date' => $dayDate,
            'kind' => 'travel',
            'label' => 'Dag 1',
            'routes' => $routes,
            'events' => [],
        ];
    }

    $groupedExtraDays = [];
    foreach (is_array($tussendagen['items'] ?? null) ? $tussendagen['items'] : [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $date = calculatie_route_v2_normalize_date($item['datum'] ?? '');
        if ($date === '' && $startDate !== '') {
            $date = calculatie_route_v2_date_add_days($startDate, count($groupedExtraDays) + 1);
        }
        if ($date === '') {
            $date = '';
        }
        if (!isset($groupedExtraDays[$date])) {
            $groupedExtraDays[$date] = [];
        }
        $groupedExtraDays[$date][] = [
            'code' => 'XD',
            'label' => 'Extra dag',
            'date' => $date,
            'time' => calculatie_route_v2_normalize_hhmm($item['tijd'] ?? ''),
            'from' => trim((string) ($item['van'] ?? '')),
            'to' => trim((string) ($item['naar'] ?? '')),
            'km' => calculatie_route_v2_normalize_float($item['km'] ?? 0),
            'zone' => calculatie_route_v2_normalize_zone($item['zone'] ?? 'nl'),
        ];
    }

    ksort($groupedExtraDays);
    foreach ($groupedExtraDays as $date => $events) {
        $days[] = [
            'seq' => count($days) + 1,
            'day_index' => count($days),
            'date' => $date,
            'kind' => 'extra_drive',
            'label' => 'Extra dag',
            'routes' => [],
            'events' => $events,
        ];
    }

    return $days;
}

function calculatie_route_v2_normalize_days(array $days, string $fallbackStartDate): array
{
    $normalized = [];
    foreach ($days as $idx => $day) {
        if (!is_array($day)) {
            continue;
        }
        $dayDate = calculatie_route_v2_normalize_date($day['date'] ?? '');
        if ($dayDate === '' && $fallbackStartDate !== '') {
            $dayDate = calculatie_route_v2_date_add_days($fallbackStartDate, $idx);
        }
        $dayKind = trim((string) ($day['kind'] ?? 'travel'));
        if (!in_array($dayKind, ['travel', 'extra_drive', 'rest'], true)) {
            $dayKind = 'travel';
        }
        $routes = [];
        foreach (is_array($day['routes'] ?? null) ? $day['routes'] : [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $routeIndex = max(1, (int) ($route['route_index'] ?? 1));
            $mode = trim((string) ($route['mode'] ?? ''));
            $code = strtoupper(trim((string) ($route['code'] ?? ('R' . $routeIndex))));
            $label = trim((string) ($route['label'] ?? ('Route ' . $routeIndex))) ?: ('Route ' . $routeIndex);
            if ($mode === '' || $mode === 'segment_table' || is_array($route['segments'] ?? null) && isset($route['segments'][0]['from'])) {
                $routeSegments = calculatie_route_v2_route1_segments_from_rows(is_array($route['segments'] ?? null) ? $route['segments'] : []);
                $routeOffsets = calculatie_route_v2_enrich_route1_segments_with_offsets($routeSegments);
                $routes[] = [
                    'route_index' => $routeIndex,
                    'code' => $code,
                    'label' => $label,
                    'mode' => 'segment_table',
                    'enabled' => !empty($route['enabled']) || !empty($routeOffsets['segments']),
                    'return_mode' => in_array((string) ($route['return_mode'] ?? ''), ['rg', 'rk'], true) ? (string) ($route['return_mode'] ?? '') : '',
                    'start_day_offset' => (int) ($routeOffsets['start_day_offset'] ?? 0),
                    'end_day_offset' => (int) ($routeOffsets['end_day_offset'] ?? 0),
                    'segments' => $routeOffsets['segments'],
                ];
                continue;
            }

            $routeSegments = calculatie_route_v2_route2_segments_from_rows(is_array($route['segments'] ?? null) ? $route['segments'] : []);
            $routeOffsets = calculatie_route_v2_enrich_route2_segments_with_offsets($routeSegments);
            $routes[] = [
                'route_index' => $routeIndex,
                'code' => $code,
                'label' => $label,
                'mode' => 'legacy_route',
                'enabled' => !empty($route['enabled']) || !empty($routeOffsets['segments']),
                'start_day_offset' => (int) ($routeOffsets['start_day_offset'] ?? 0),
                'end_day_offset' => (int) ($routeOffsets['end_day_offset'] ?? 0),
                'segments' => $routeOffsets['segments'],
            ];
        }

        $events = [];
        foreach (is_array($day['events'] ?? null) ? $day['events'] : [] as $event) {
            if (!is_array($event)) {
                continue;
            }
            $code = calculatie_route_v2_normalize_event_code($event['code'] ?? '');
            $eventDate = calculatie_route_v2_normalize_date($event['date'] ?? '') ?: $dayDate;
            $time = calculatie_route_v2_normalize_hhmm($event['time'] ?? '');
            $from = trim((string) ($event['from'] ?? ''));
            $to = trim((string) ($event['to'] ?? ''));
            $label = trim((string) ($event['label'] ?? ($code === 'RD' ? 'Rustdag' : 'Extra dag')));
            $km = calculatie_route_v2_normalize_float($event['km'] ?? 0);
            if ($eventDate === '' && $time === '' && $from === '' && $to === '' && $km <= 0.0 && $label === '') {
                continue;
            }
            $events[] = [
                'code' => $code,
                'label' => $label !== '' ? $label : ($code === 'RD' ? 'Rustdag' : 'Extra dag'),
                'date' => $eventDate,
                'time' => $time,
                'from' => $from,
                'to' => $to,
                'km' => $km,
                'zone' => calculatie_route_v2_normalize_zone($event['zone'] ?? 'nl'),
            ];
        }

        if ($dayDate === '' && empty($routes) && empty($events)) {
            continue;
        }

        $normalized[] = [
            'seq' => count($normalized) + 1,
            'day_index' => count($normalized),
            'date' => $dayDate,
            'kind' => $dayKind,
            'label' => trim((string) ($day['label'] ?? ('Dag ' . (count($normalized) + 1)))) ?: ('Dag ' . (count($normalized) + 1)),
            'routes' => $routes,
            'events' => $events,
        ];
    }
    return $normalized;
}

function calculatie_route_v2_resolve_dates(string $startDate, string $endDate, array $days): array
{
    $start = $startDate;
    $end = $endDate;
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        $dayDate = calculatie_route_v2_normalize_date($day['date'] ?? '');
        if ($start === '' && $dayDate !== '') {
            $start = $dayDate;
        }
        $end = calculatie_route_v2_max_date($end, $dayDate);
        foreach (is_array($day['routes'] ?? null) ? $day['routes'] : [] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $end = calculatie_route_v2_max_date($end, calculatie_route_v2_date_add_days($dayDate, max(0, (int) ($route['end_day_offset'] ?? 0))));
        }
        foreach (is_array($day['events'] ?? null) ? $day['events'] : [] as $event) {
            if (!is_array($event)) {
                continue;
            }
            $end = calculatie_route_v2_max_date($end, calculatie_route_v2_normalize_date($event['date'] ?? ''));
        }
    }
    if ($end === '' && $start !== '') {
        $end = $start;
    }
    return ['start' => $start, 'end' => $end];
}

/**
 * Verwijdert losse-pakketdagen (extra travel-dagen met day_index ≥ 1) en zet de vlag uit.
 * Gebruikt wanneer CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED uit staat.
 */
function calculatie_route_v2_remove_losse_pakket_package_data(array $payload): array
{
    $flags = is_array($payload['flags'] ?? null) ? $payload['flags'] : [];
    $flags['losse_rijdagen_pakket'] = false;
    $payload['flags'] = $flags;

    $days = is_array($payload['days'] ?? null) ? $payload['days'] : [];
    $filtered = [];
    foreach ($days as $day) {
        if (!is_array($day)) {
            continue;
        }
        $idx = (int) ($day['day_index'] ?? 0);
        $kind = trim((string) ($day['kind'] ?? ''));
        if ($idx >= 1 && $kind === 'travel') {
            continue;
        }
        $filtered[] = $day;
    }
    $reindexed = [];
    foreach ($filtered as $i => $day) {
        if (!is_array($day)) {
            continue;
        }
        $day['seq'] = $i + 1;
        $day['day_index'] = $i;
        $reindexed[] = $day;
    }
    $payload['days'] = $reindexed;

    $startDate = calculatie_route_v2_normalize_date($payload['dates']['start'] ?? '');
    $payload['dates'] = calculatie_route_v2_resolve_dates($startDate, $startDate, $reindexed);

    return $payload;
}

function calculatie_route_v2_normalize_payload(array $payload): array
{
    $rittype = trim((string) ($payload['rittype'] ?? 'dagtocht'));
    $dates = is_array($payload['dates'] ?? null) ? $payload['dates'] : [];
    $route1 = is_array($payload['route1'] ?? null) ? $payload['route1'] : [];
    $route2 = is_array($payload['route2'] ?? null) ? $payload['route2'] : [];
    $tussendagen = is_array($payload['tussendagen'] ?? null) ? $payload['tussendagen'] : ['enabled' => false, 'items' => []];
    $buitenland = $payload['buitenland'] ?? null;
    $startDate = calculatie_route_v2_normalize_date($dates['start'] ?? '');
    $endDate = calculatie_route_v2_normalize_date($dates['end'] ?? '');

    $normalizedRoute1 = [
        'label' => trim((string) ($route1['label'] ?? 'Route 1')) ?: 'Route 1',
        'return_mode' => in_array((string) ($route1['return_mode'] ?? ''), ['rg', 'rk'], true) ? (string) ($route1['return_mode'] ?? '') : '',
        'segments' => calculatie_route_v2_route1_segments_from_rows(is_array($route1['segments'] ?? null) ? $route1['segments'] : []),
    ];
    $normalizedRoute2 = [
        'enabled' => !empty($route2['enabled']),
        'segments' => calculatie_route_v2_route2_segments_from_rows(is_array($route2['segments'] ?? null) ? $route2['segments'] : []),
    ];
    $normalizedTuss = calculatie_route_v2_normalize_tussendagen($tussendagen);
    $normalizedBuitenland = calculatie_route_v2_normalize_buitenland($buitenland);
    $normalizedDays = calculatie_route_v2_normalize_days(is_array($payload['days'] ?? null) ? $payload['days'] : [], $startDate ?: $endDate);

    if (empty($normalizedRoute1['segments'])) {
        $route1FromDays = calculatie_route_v2_route1_from_days($normalizedDays);
        if ($route1FromDays !== null) {
            $normalizedRoute1 = $route1FromDays;
        }
    }
    if (empty($normalizedRoute2['segments']) && !$normalizedRoute2['enabled']) {
        $route2FromDays = calculatie_route_v2_route2_from_days($normalizedDays);
        if ($route2FromDays !== null) {
            $normalizedRoute2 = $route2FromDays;
        }
    }
    if (empty($normalizedTuss['items'])) {
        $normalizedTuss = calculatie_route_v2_tussendagen_from_days($normalizedDays);
    }
    if (empty($normalizedDays)) {
        $normalizedDays = calculatie_route_v2_build_days_from_compat($normalizedRoute1, $normalizedRoute2, $normalizedTuss, $startDate ?: $endDate);
    }

    $resolvedDates = calculatie_route_v2_resolve_dates($startDate, $endDate, $normalizedDays);
    $flagsIn = is_array($payload['flags'] ?? null) ? $payload['flags'] : [];

    $out = [
        'schema' => 2,
        'rittype' => $rittype,
        'dates' => $resolvedDates,
        'days' => $normalizedDays,
        'route1' => $normalizedRoute1,
        'route2' => $normalizedRoute2,
        'tussendagen' => $normalizedTuss,
        'buitenland' => $normalizedBuitenland,
        'flags' => [
            'losse_rijdagen_pakket' => !empty($flagsIn['losse_rijdagen_pakket']),
        ],
    ];

    if (!calculatie_feature_losse_pakket_dagen_enabled()) {
        $out = calculatie_route_v2_remove_losse_pakket_package_data($out);
    }

    return $out;
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
            'schema' => 2,
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

/**
 * Route-1-segmenten voor UI-boot / extract: gebruik route1.segments als die echte adressen bevat,
 * anders planner-days (route_index 1 / code R1). Voorkomt lege bewerkpagina als route alleen in `days` staat.
 *
 * @return list<array<string, mixed>>
 */
function calculatie_route_v2_route1_source_segments_for_boot(array $payload): array
{
    $route1 = is_array($payload['route1'] ?? null) ? $payload['route1'] : [];
    $direct = is_array($route1['segments'] ?? null) ? $route1['segments'] : [];
    $hasAddresses = false;
    foreach ($direct as $seg) {
        if (!is_array($seg)) {
            continue;
        }
        $from = trim((string) ($seg['from'] ?? $seg['van'] ?? ''));
        $to = trim((string) ($seg['to'] ?? $seg['naar'] ?? ''));
        if ($from !== '' || $to !== '') {
            $hasAddresses = true;
            break;
        }
    }
    if ($hasAddresses) {
        return $direct;
    }

    $fromDays = calculatie_route_v2_route1_from_days(is_array($payload['days'] ?? null) ? $payload['days'] : []);
    if (is_array($fromDays) && is_array($fromDays['segments'] ?? null)) {
        return $fromDays['segments'];
    }

    return [];
}

function calculatie_route_v2_extract_route1_segments(?array $payload): array
{
    $segments = [];
    if (!is_array($payload)) {
        return $segments;
    }
    $sourceSegments = calculatie_route_v2_route1_source_segments_for_boot($payload);
    foreach ($sourceSegments as $segment) {
        if (!is_array($segment)) {
            continue;
        }
        // Zelfde veldnamen als calculatie_route_v2_route1_segments_from_rows (from/to + depart/arrive)
        // én legacy/UI-boot (van/naar + vertrektijd/aankomst_tijd); anders lege tabel bij heropenen.
        $segments[] = [
            'vertrektijd' => calculatie_route_v2_normalize_hhmm($segment['depart_at'] ?? $segment['vertrektijd'] ?? ''),
            'aankomst_tijd' => calculatie_route_v2_normalize_hhmm($segment['arrive_at'] ?? $segment['aankomst_tijd'] ?? ''),
            'van' => trim((string) ($segment['from'] ?? $segment['van'] ?? '')),
            'naar' => trim((string) ($segment['to'] ?? $segment['naar'] ?? '')),
            'km' => (string) calculatie_route_v2_normalize_float($segment['km'] ?? 0),
            'zone' => calculatie_route_v2_normalize_zone($segment['zone'] ?? 'nl'),
            'return_kind' => in_array((string) ($segment['return_kind'] ?? ''), ['rg', 'rk-klant', 'rk-garage'], true)
                ? (string) $segment['return_kind']
                : '',
        ];
    }
    return $segments;
}
