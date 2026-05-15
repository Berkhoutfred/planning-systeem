<?php
declare(strict_types=1);

require_once __DIR__ . '/route_v2.php';
require_once dirname(__DIR__, 2) . '/includes/pdf_instructie_klant.php';
require_once dirname(__DIR__, 2) . '/includes/tenant_instellingen_db.php';

function offerte_presentatie_base_select_sql(): string
{
    return "
        SELECT c.*,
               k.bedrijfsnaam,
               k.voornaam AS klant_vn,
               k.achternaam AS klant_an,
               k.adres,
               k.postcode,
               k.plaats,
               k.email,
               k.telefoon,
               cp.naam AS contactpersoon_naam
        FROM calculaties c
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        LEFT JOIN klant_contactpersonen cp ON c.contact_id = cp.id AND cp.tenant_id = c.tenant_id
    ";
}

function offerte_presentatie_fetch_by_id(PDO $pdo, int $id, string $publicToken = '', ?int $tenantId = null): ?array
{
    if ($id <= 0) {
        return null;
    }

    if ($publicToken !== '') {
        $stmt = $pdo->prepare(offerte_presentatie_base_select_sql() . "
            WHERE c.id = ? AND c.token = ? AND c.tenant_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$id, $publicToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    if (($tenantId ?? 0) <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(offerte_presentatie_base_select_sql() . "
        WHERE c.id = ? AND c.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, (int) $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function offerte_presentatie_fetch_by_token(PDO $pdo, string $token): ?array
{
    $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
    if ($token === '') {
        return null;
    }

    $stmt = $pdo->prepare(offerte_presentatie_base_select_sql() . "
        WHERE c.token = ? AND c.tenant_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function offerte_presentatie_fetch_regels(PDO $pdo, int $calculatieId, int $tenantId): array
{
    if ($calculatieId <= 0 || $tenantId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT * FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ? ORDER BY id ASC');
    $stmt->execute([$calculatieId, $tenantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function offerte_presentatie_regels_by_type(array $regels): array
{
    $map = [];
    foreach ($regels as $regel) {
        if (!is_array($regel)) {
            continue;
        }
        $type = trim((string) ($regel['type'] ?? ''));
        if ($type === '') {
            continue;
        }
        $map[$type] = $regel;
    }
    return $map;
}

function offerte_presentatie_build_legacy_data(array $regelMap): array
{
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
        $regel = $regelMap[$type] ?? [];
        $data[$type] = [
            'tijd' => calculatie_route_v2_normalize_hhmm($regel['tijd'] ?? ''),
            'adres' => trim((string) ($regel['adres'] ?? '')),
            'km' => calculatie_route_v2_normalize_float($regel['km'] ?? 0),
        ];
    }
    return $data;
}

function offerte_presentatie_build_legacy_route2_segments(array $regelMap): array
{
    $map = [
        ['type' => 't_garage_rit2', 'kind' => 'garage_start'],
        ['type' => 't_vertrek_best', 'kind' => 'route2_depart'],
        ['type' => 't_voorstaan_rit2', 'kind' => 'preposition'],
        ['type' => 't_retour_klant', 'kind' => 'route2_customer'],
        ['type' => 't_retour_garage', 'kind' => 'route2_garage_end'],
    ];

    $rows = [];
    foreach ($map as $cfg) {
        $regel = $regelMap[$cfg['type']] ?? [];
        $time = calculatie_route_v2_normalize_hhmm($regel['tijd'] ?? '');
        $address = trim((string) ($regel['adres'] ?? ''));
        $km = calculatie_route_v2_normalize_float($regel['km'] ?? 0);
        if ($time === '' && $address === '' && $km <= 0.0) {
            continue;
        }
        $rows[] = [
            'type' => $cfg['type'],
            'kind' => $cfg['kind'],
            'time' => $time,
            'address' => $address,
            'km' => $km,
            'zone' => 'nl',
        ];
    }

    return $rows;
}

function offerte_presentatie_decode_json_array($json): array
{
    $json = trim((string) $json);
    if ($json === '') {
        return [];
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function offerte_presentatie_payload(array $rit, array $regelMap): ?array
{
    $payload = calculatie_route_v2_decode((string) ($rit['route_v2_json'] ?? ''));
    if (is_array($payload)) {
        return $payload;
    }

    $legacyData = offerte_presentatie_build_legacy_data($regelMap);
    $route1Segments = route_heen_segments_from_regels($legacyData);
    $route2Segments = offerte_presentatie_build_legacy_route2_segments($regelMap);
    $tussendagen = offerte_presentatie_decode_json_array($rit['tussendagen_meta'] ?? null);
    $buitenland = offerte_presentatie_decode_json_array($rit['buitenland_meta'] ?? null);

    return calculatie_route_v2_normalize_payload([
        'schema' => 2,
        'rittype' => (string) ($rit['rittype'] ?? 'dagtocht'),
        'dates' => [
            'start' => (string) ($rit['rit_datum'] ?? ''),
            'end' => (string) ($rit['rit_datum_eind'] ?? $rit['rit_datum'] ?? ''),
        ],
        'route1' => [
            'label' => 'Route 1',
            'return_mode' => '',
            'segments' => $route1Segments,
        ],
        'route2' => [
            'enabled' => !empty($route2Segments),
            'segments' => $route2Segments,
        ],
        'tussendagen' => $tussendagen,
        'buitenland' => $buitenland,
    ]);
}

function offerte_presentatie_order_nummer(array $rit): string
{
    $jaar = !empty($rit['rit_datum']) ? date('y', strtotime((string) $rit['rit_datum'])) : date('y');
    return $jaar . str_pad((string) ((int) ($rit['id'] ?? 0)), 3, '0', STR_PAD_LEFT);
}

function offerte_presentatie_rittype_label(string $rittype): string
{
    $map = [
        'dagtocht' => 'Dagtocht',
        'schoolreis' => 'Schoolreis',
        'enkel' => 'Enkele rit',
        'brenghaal' => 'Breng & haal',
        'trein' => 'Treinstremming',
        'meerdaags' => 'Meerdaagse rit',
        'buitenland' => 'Buitenland',
    ];
    return $map[$rittype] ?? ucfirst($rittype);
}

function offerte_presentatie_format_date(string $date, bool $withWeekday = false): string
{
    $date = calculatie_route_v2_normalize_date($date);
    if ($date === '') {
        return '';
    }

    $days = ['Zondag', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag'];
    $months = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    $base = date('d', $ts) . ' ' . ($months[(int) date('n', $ts)] ?? date('m', $ts)) . ' ' . date('Y', $ts);
    if (!$withWeekday) {
        return $base;
    }

    return ($days[(int) date('w', $ts)] ?? '') . ' ' . $base;
}

function offerte_presentatie_format_time_with_offset(string $time, int $offset = 0): string
{
    $time = calculatie_route_v2_normalize_hhmm($time);
    if ($time === '') {
        return '';
    }
    if ($offset <= 0) {
        return $time;
    }
    return $time . ' (+' . $offset . ' dag' . ($offset === 1 ? '' : 'en') . ')';
}

function offerte_presentatie_format_currency(float $amount): string
{
    return 'EUR ' . number_format($amount, 2, ',', '.');
}

/**
 * Geeft een korte plaatsnaam terug uit een volledig adres.
 * Strategie: strip Nederland/land → zoek postcodepatroon → neem laatste niet-straat segment.
 */
function offerte_presentatie_adres_naar_plaats(string $adres): string
{
    $s = trim($adres);
    if ($s === '' || $s === '?') {
        return '?';
    }
    // Strip land aan het einde
    $s = (string) preg_replace('/,\s*(nederland|netherlands|belgi[eë]|belgium|duitsland|germany|france|luxemburg)\s*$/iu', '', $s);
    $chunks = array_values(array_filter(array_map('trim', explode(',', $s)), fn ($p) => $p !== ''));
    if ($chunks === []) {
        return '?';
    }
    $streetish = static function (string $c): bool {
        return (bool) preg_match('/straat|weg|laan|plein|dreef|singel|dijk|kade|\bpad\b|route|\bhof\b|industrieweg|stationsplein|college|school|station|airport|terminal|luchthaven/i', $c);
    };
    // Postcode patroon NL: 1234 AB Stad
    foreach (array_reverse($chunks) as $chunk) {
        if (preg_match('/\b\d{4}\s?[A-Z]{2}\s+(.+)$/u', $chunk, $m)) {
            return trim($m[1]);
        }
    }
    // Neem het laatste niet-straatachtige segment
    foreach (array_reverse($chunks) as $chunk) {
        if (!$streetish($chunk)) {
            $words = preg_split('/\s+/u', $chunk, -1, PREG_SPLIT_NO_EMPTY);
            if ($words && count($words) >= 1) {
                // Als het een naam is zoals "Stayokay Gorssel", neem laatste woord
                $last = (string) end($words);
                if (preg_match('/^[A-Za-zÀ-ÿ][\w\-\']*$/u', $last) && mb_strlen($last) >= 3) {
                    return $chunk;
                }
            }
            return $chunk;
        }
    }
    // Fallback: eerste segment
    return $chunks[0];
}

/**
 * Maakt een leesbare route-string voor in overzichten en het verzamelofferte-blad.
 * - Standaard: "Vertrekplaats – Bestemming"
 * - Breng & haal: "Vertrekplaats – Bestemming v.v."
 */
function offerte_presentatie_route_label(array $rit, array $regelMap, array $payload): string
{
    $rittype = trim((string) ($rit['rittype'] ?? ''));

    // Primaire bron: calculatie_regels (meest betrouwbaar)
    $vanAdres = trim((string) ($regelMap['t_vertrek_klant']['adres'] ?? ''));
    $naarAdres = trim((string) ($regelMap['t_aankomst_best']['adres'] ?? ''));

    // Fallback: route_v2 payload — neem eerste niet-garage segment als vertrek,
    // en het laatste segment-doel als bestemming
    if ($vanAdres === '' || $naarAdres === '') {
        $days = $payload['days'] ?? [];
        if ($days !== []) {
            $firstDay = $days[0] ?? [];
            $routes = $firstDay['routes'] ?? [];
            foreach ($routes as $route) {
                $segs = $route['segments'] ?? [];
                foreach ($segs as $seg) {
                    $kind = (string) ($seg['kind'] ?? '');
                    $isGarageKind = in_array($kind, ['garage_to_customer', 'garage_start', 'garage_end', 'customer_to_garage'], true);
                    if ($vanAdres === '' && !$isGarageKind && !empty($seg['from'])) {
                        $vanAdres = (string) $seg['from'];
                    }
                    if (!empty($seg['to']) && !in_array($kind, ['garage_end', 'customer_to_garage'], true)) {
                        $naarAdres = (string) $seg['to'];
                    }
                }
                if ($vanAdres !== '') {
                    break;
                }
            }
        }
    }

    $van = offerte_presentatie_adres_naar_plaats($vanAdres);
    $naar = offerte_presentatie_adres_naar_plaats($naarAdres);

    // Negeer garage-achtige vertreklocaties als we een bestemming hebben
    if (preg_match('/industrieweg|garage/i', $van) && $naar !== '?') {
        $van = $naar;
    }

    if ($van === '?' && $naar === '?') {
        return '';
    }
    $suffix = $rittype === 'brenghaal' ? ' v.v.' : '';
    if ($van === $naar || $van === '?') {
        return $naar . $suffix;
    }
    // Gebruik gewone koppeltekens die door windows-1252 (FPDF) correct worden weergegeven
    return $van . ' - ' . $naar . $suffix;
}

function offerte_presentatie_format_km(float $km): string
{
    if ($km <= 0.0) {
        return '';
    }

    $rounded = round($km, 1);
    if (abs($rounded - round($rounded)) < 0.00001) {
        return number_format($rounded, 0, ',', '.');
    }

    return number_format($rounded, 1, ',', '.');
}

function offerte_presentatie_zone_label(string $zone): string
{
    $zone = calculatie_route_v2_normalize_zone($zone);
    if ($zone === 'ov') {
        return '0%';
    }
    if ($zone === 'nl') {
        return '';
    }
    return strtoupper($zone);
}

function offerte_presentatie_route_table_from_segments(array $route): array
{
    $rows = [];
    foreach (is_array($route['segments'] ?? null) ? $route['segments'] : [] as $segment) {
        if (!is_array($segment)) {
            continue;
        }

        $rows[] = [
            'depart' => calculatie_route_v2_normalize_hhmm($segment['depart_at'] ?? $segment['vertrektijd'] ?? ''),
            'depart_display' => offerte_presentatie_format_time_with_offset(
                (string) ($segment['depart_at'] ?? $segment['vertrektijd'] ?? ''),
                max(0, (int) ($segment['depart_day_offset'] ?? 0))
            ),
            'from' => trim((string) ($segment['from'] ?? $segment['van'] ?? '')),
            'to' => trim((string) ($segment['to'] ?? $segment['naar'] ?? '')),
            'arrive' => calculatie_route_v2_normalize_hhmm($segment['arrive_at'] ?? $segment['aankomst_tijd'] ?? ''),
            'arrive_display' => offerte_presentatie_format_time_with_offset(
                (string) ($segment['arrive_at'] ?? $segment['aankomst_tijd'] ?? ''),
                max(0, (int) ($segment['arrive_day_offset'] ?? 0))
            ),
            'km' => calculatie_route_v2_normalize_float($segment['km'] ?? 0),
            'km_display' => offerte_presentatie_format_km(calculatie_route_v2_normalize_float($segment['km'] ?? 0)),
            'zone' => calculatie_route_v2_normalize_zone($segment['zone'] ?? 'nl'),
            'zone_display' => offerte_presentatie_zone_label((string) ($segment['zone'] ?? 'nl')),
        ];
    }
    return $rows;
}

function offerte_presentatie_route_table_from_legacy_points(array $route): array
{
    $rows = [];
    foreach (is_array($route['segments'] ?? null) ? $route['segments'] : [] as $point) {
        if (!is_array($point)) {
            continue;
        }

        $rows[] = [
            'time' => calculatie_route_v2_normalize_hhmm($point['time'] ?? ''),
            'time_display' => offerte_presentatie_format_time_with_offset(
                (string) ($point['time'] ?? ''),
                max(0, (int) ($point['time_day_offset'] ?? 0))
            ),
            'location' => trim((string) ($point['address'] ?? '')),
            'km' => calculatie_route_v2_normalize_float($point['km'] ?? 0),
            'km_display' => offerte_presentatie_format_km(calculatie_route_v2_normalize_float($point['km'] ?? 0)),
            'zone' => calculatie_route_v2_normalize_zone($point['zone'] ?? 'nl'),
            'zone_display' => offerte_presentatie_zone_label((string) ($point['zone'] ?? 'nl')),
        ];
    }
    return $rows;
}

function offerte_presentatie_event_rows(array $events): array
{
    $rows = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $rows[] = [
            'label' => trim((string) ($event['label'] ?? '')),
            'date_display' => offerte_presentatie_format_date((string) ($event['date'] ?? ''), true),
            'time' => calculatie_route_v2_normalize_hhmm($event['time'] ?? ''),
            'time_display' => calculatie_route_v2_normalize_hhmm($event['time'] ?? ''),
            'from' => trim((string) ($event['from'] ?? '')),
            'to' => trim((string) ($event['to'] ?? '')),
            'km' => calculatie_route_v2_normalize_float($event['km'] ?? 0),
            'km_display' => offerte_presentatie_format_km(calculatie_route_v2_normalize_float($event['km'] ?? 0)),
            'zone' => calculatie_route_v2_normalize_zone($event['zone'] ?? 'nl'),
            'zone_display' => offerte_presentatie_zone_label((string) ($event['zone'] ?? 'nl')),
        ];
    }
    return $rows;
}

function offerte_presentatie_build_route_days(?array $payload): array
{
    $days = [];
    foreach (is_array($payload['days'] ?? null) ? $payload['days'] : [] as $day) {
        if (!is_array($day)) {
            continue;
        }

        $routeBlocks = [];
        $showZone = false;
        foreach (is_array($day['routes'] ?? null) ? $day['routes'] : [] as $route) {
            if (!is_array($route) || empty($route['enabled'])) {
                continue;
            }

            $mode = trim((string) ($route['mode'] ?? 'segment_table'));
            if ($mode === 'legacy_route') {
                $rows = offerte_presentatie_route_table_from_legacy_points($route);
                $tableType = 'legacy_route';
            } else {
                $rows = offerte_presentatie_route_table_from_segments($route);
                $tableType = 'segment_table';
            }

            if ($rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                if (($row['zone'] ?? 'nl') !== 'nl') {
                    $showZone = true;
                    break;
                }
            }

            $routeBlocks[] = [
                'label' => trim((string) ($route['label'] ?? 'Route')),
                'table_type' => $tableType,
                'show_zone' => false,
                'rows' => $rows,
            ];
        }

        $eventRows = offerte_presentatie_event_rows(is_array($day['events'] ?? null) ? $day['events'] : []);
        foreach ($eventRows as $row) {
            if (($row['zone'] ?? 'nl') !== 'nl') {
                $showZone = true;
                break;
            }
        }

        if ($routeBlocks === [] && $eventRows === []) {
            continue;
        }

        $headingLabel = trim((string) ($day['label'] ?? 'Dag'));
        $mergeSingleRouteIntoHeading = count($routeBlocks) === 1 && trim((string) ($routeBlocks[0]['label'] ?? '')) !== '';

        foreach ($routeBlocks as $idx => $routeBlock) {
            $routeBlock['show_zone'] = $showZone;
            $routeBlock['inline_with_day_heading'] = $mergeSingleRouteIntoHeading && $idx === 0;
            $routeBlocks[$idx] = $routeBlock;
        }

        if ($mergeSingleRouteIntoHeading) {
            $headingLabel .= ' · ' . trim((string) ($routeBlocks[0]['label'] ?? ''));
        }

        $days[] = [
            'label' => trim((string) ($day['label'] ?? 'Dag')),
            'heading_label' => $headingLabel,
            'kind' => trim((string) ($day['kind'] ?? 'travel')),
            'date' => calculatie_route_v2_normalize_date($day['date'] ?? ''),
            'date_display' => offerte_presentatie_format_date((string) ($day['date'] ?? ''), true),
            'show_zone' => $showZone,
            'routes' => $routeBlocks,
            'events' => $eventRows,
        ];
    }

    return $days;
}

/**
 * Routeplanning voor klant-PDF/web: geen aanrijden garage→instapplaats en geen retour naar garage;
 * voor standaard ritten voorkeur voor de zichtbare lijn (instap → bestemming) uit calculatie_regels.
 *
 * @param array<string, array{tijd?:string,adres?:string,km?:mixed}> $regelMap
 * @return list<array<string, mixed>>
 */
function offerte_presentatie_build_klant_route_days(array $rit, ?array $payload, array $regelMap): array
{
    $rittype = trim((string) ($rit['rittype'] ?? ''));
    $legacy = offerte_presentatie_build_legacy_data($regelMap);
    $vl = trim((string) ($legacy['t_vertrek_klant']['adres'] ?? ''));
    $best = trim((string) ($legacy['t_aankomst_best']['adres'] ?? ''));

    $rows = [];
    if (is_array($payload)) {
        $segs = calculatie_route_v2_route1_source_segments_for_boot($payload);
        $filtered = [];
        foreach ($segs as $origIdx => $seg) {
            if (!is_array($seg)) {
                continue;
            }
            if (trim((string) ($seg['return_kind'] ?? '')) !== '') {
                continue;
            }
            if (calculatie_route_v2_route1_kind($seg, $origIdx) === 'garage_to_customer') {
                continue;
            }
            $filtered[] = $seg;
        }
        if ($filtered !== []) {
            $rows = offerte_presentatie_route_table_from_segments(['segments' => $filtered]);
        }
    }

    if (
        $rows === []
        && $vl !== ''
        && $best !== ''
        && in_array($rittype, ['dagtocht', 'schoolreis', 'trein', 'enkel'], true)
    ) {
        $rows = offerte_presentatie_route_table_from_segments(['segments' => [[
            'from' => $vl,
            'to' => $best,
            'depart_at' => (string) ($legacy['t_vertrek_klant']['tijd'] ?? ''),
            'arrive_at' => (string) ($legacy['t_aankomst_best']['tijd'] ?? ''),
            'km' => (float) ($legacy['t_vertrek_klant']['km'] ?? 0) + (float) ($legacy['t_aankomst_best']['km'] ?? 0),
            'zone' => 'nl',
        ]]]);
    }

    if ($rows === []) {
        return [];
    }

    $showZone = false;
    foreach ($rows as $row) {
        if (($row['zone'] ?? 'nl') !== 'nl') {
            $showZone = true;
            break;
        }
    }

    $start = is_array($payload)
        ? calculatie_route_v2_normalize_date($payload['dates']['start'] ?? ($rit['rit_datum'] ?? ''))
        : calculatie_route_v2_normalize_date((string) ($rit['rit_datum'] ?? ''));
    if ($start === '') {
        $start = calculatie_route_v2_normalize_date((string) ($rit['rit_datum'] ?? ''));
    }

    return [[
        'label' => 'Rit',
        'heading_label' => 'Route',
        'kind' => 'travel',
        'date' => $start,
        'date_display' => offerte_presentatie_format_date($start, true),
        'show_zone' => $showZone,
        'routes' => [[
            'label' => '',
            'table_type' => 'segment_table',
            'show_zone' => $showZone,
            'inline_with_day_heading' => true,
            'rows' => $rows,
        ]],
        'events' => [],
    ]];
}

/**
 * Bouw de klant-zichtbare terugrit-blokken voor een brenghaal-rit.
 * Probeert achtereenvolgens: payload days route2, payload['route2'], legacy regelMap.
 * Garage-starts en garage-ends worden eruit gefilterd.
 *
 * @return list<array<string, mixed>>
 */
function offerte_presentatie_build_route2_klant_blocks(?array $payload, array $regelMap): array
{
    // Segmenten ophalen
    $segs = [];

    if (is_array($payload)) {
        // 1) Zoek in payload['days'] → routes → route_index = 2
        foreach ($payload['days'] ?? [] as $day) {
            if (!is_array($day)) {
                continue;
            }
            foreach ($day['routes'] ?? [] as $route) {
                if (!is_array($route)) {
                    continue;
                }
                if ((int) ($route['route_index'] ?? 0) === 2 && !empty($route['enabled'])) {
                    $rawSegs = is_array($route['segments'] ?? null) ? $route['segments'] : [];
                    if ($rawSegs !== []) {
                        $segs = $rawSegs;
                        break 2;
                    }
                }
            }
        }

        // 2) Fallback: payload['route2']['segments']
        if ($segs === []) {
            $r2 = $payload['route2'] ?? [];
            if (!empty($r2['enabled']) && !empty($r2['segments']) && is_array($r2['segments'])) {
                $segs = $r2['segments'];
            }
        }
    }

    // 3) Fallback: legacy regelMap (t_vertrek_best, t_retour_klant)
    if ($segs === []) {
        $legacyMap = [
            ['type' => 't_vertrek_best',  'kind' => 'route2_depart'],
            ['type' => 't_retour_klant',  'kind' => 'route2_customer'],
        ];
        foreach ($legacyMap as $cfg) {
            $regel = $regelMap[$cfg['type']] ?? [];
            $time  = calculatie_route_v2_normalize_hhmm($regel['tijd'] ?? '');
            $adres = trim((string) ($regel['adres'] ?? ''));
            if ($time !== '' || $adres !== '') {
                $segs[] = ['kind' => $cfg['kind'], 'time' => $time, 'time_day_offset' => 0, 'address' => $adres, 'km' => 0, 'zone' => 'nl'];
            }
        }
    }

    if ($segs === []) {
        return [];
    }

    // Filter garage-starts en garage-ends (niet relevant voor klant)
    $garageKinds = ['garage_start', 'route2_garage_end', 'garage_end'];
    $filtered = array_values(array_filter($segs, static function (array $s) use ($garageKinds): bool {
        return !in_array(trim((string) ($s['kind'] ?? '')), $garageKinds, true);
    }));

    if ($filtered === []) {
        $filtered = $segs; // als alle punten garage-achtig zijn, toon ze toch
    }

    // Sorteer op tijd zodat de terugrit in chronologische volgorde staat
    usort($filtered, static function (array $a, array $b): int {
        $tA = calculatie_route_v2_normalize_hhmm($a['time'] ?? '');
        $tB = calculatie_route_v2_normalize_hhmm($b['time'] ?? '');
        return strcmp($tA, $tB);
    });

    // Converteer opeenvolgende waypoints naar Van→Naar segmentrijen (zelfde formaat als heenrit)
    $segRows = [];
    $count   = count($filtered);
    for ($i = 0; $i < $count - 1; $i++) {
        $wp1       = $filtered[$i];
        $wp2       = $filtered[$i + 1];
        $segRows[] = [
            'depart'         => calculatie_route_v2_normalize_hhmm($wp1['time'] ?? ''),
            'depart_display' => offerte_presentatie_format_time_with_offset(
                (string) ($wp1['time'] ?? ''),
                max(0, (int) ($wp1['time_day_offset'] ?? 0))
            ),
            'from'           => trim((string) ($wp1['address'] ?? '')),
            'to'             => trim((string) ($wp2['address'] ?? '')),
            'arrive'         => calculatie_route_v2_normalize_hhmm($wp2['time'] ?? ''),
            'arrive_display' => offerte_presentatie_format_time_with_offset(
                (string) ($wp2['time'] ?? ''),
                max(0, (int) ($wp2['time_day_offset'] ?? 0))
            ),
            'zone'           => calculatie_route_v2_normalize_zone($wp1['zone'] ?? 'nl'),
            'zone_display'   => offerte_presentatie_zone_label((string) ($wp1['zone'] ?? 'nl')),
        ];
    }

    // Fallback: slechts 1 waypoint beschikbaar → gebruik legacy tijdlijn
    if ($segRows === []) {
        $rows = offerte_presentatie_route_table_from_legacy_points(['segments' => $filtered]);
        if ($rows === []) {
            return [];
        }
        return [[
            'label'                   => 'Terugrit',
            'table_type'              => 'legacy_route',
            'show_zone'               => false,
            'inline_with_day_heading' => false,
            'rows'                    => $rows,
        ]];
    }

    return [[
        'label'                   => 'Terugrit',
        'table_type'              => 'segment_table',
        'show_zone'               => false,
        'inline_with_day_heading' => false,
        'rows'                    => $segRows,
    ]];
}

function offerte_presentatie_logo_web_src(string $logoPad): string
{
    $logoPad = trim($logoPad);
    if ($logoPad === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $logoPad)) {
        return $logoPad;
    }

    $clean = ltrim($logoPad, '/');
    if (str_starts_with($clean, 'beheer/')) {
        return $clean;
    }

    return 'beheer/' . $clean;
}

function offerte_presentatie_build(PDO $pdo, array $rit): array
{
    $tenantId = (int) ($rit['tenant_id'] ?? 0);
    $tenantInst = tenant_instellingen_get($pdo, $tenantId);
    $regels = offerte_presentatie_fetch_regels($pdo, (int) ($rit['id'] ?? 0), $tenantId);
    $regelMap = offerte_presentatie_regels_by_type($regels);
    $payload = offerte_presentatie_payload($rit, $regelMap);

    $bedrijf = trim((string) ($rit['bedrijfsnaam'] ?? ''));
    $persoon = trim((string) ($rit['contactpersoon_naam'] ?? ''));
    if ($persoon === '') {
        $persoon = trim((string) ($rit['klant_vn'] ?? '') . ' ' . (string) ($rit['klant_an'] ?? ''));
    }

    $klantNaam = $bedrijf !== '' ? $bedrijf : $persoon;
    $aanhefNaam = $persoon !== '' ? $persoon : ($klantNaam !== '' ? $klantNaam : 'relatie');
    $adres = trim((string) ($rit['adres'] ?? ''));
    $postcodePlaats = trim((string) ($rit['postcode'] ?? '') . ' ' . (string) ($rit['plaats'] ?? ''));
    $instructie = pdf_filter_instructie_voor_klant(isset($rit['instructie_kantoor']) ? (string) $rit['instructie_kantoor'] : null);

    // DB-kolom `prijs` is LEIDEND inclusief BTW; excl. en btw berekenen we hieruit.
    $prijsIncl = round((float) ($rit['prijs'] ?? 0), 2);
    $prijsExcl = round($prijsIncl / 1.09, 2);
    $btwBedrag = round($prijsIncl - $prijsExcl, 2);

    $startDate = calculatie_route_v2_normalize_date($payload['dates']['start'] ?? ($rit['rit_datum'] ?? ''));
    $endDate = calculatie_route_v2_normalize_date($payload['dates']['end'] ?? ($rit['rit_datum_eind'] ?? $rit['rit_datum'] ?? ''));
    $pakketLosseRijdagen = !empty($payload['flags']['losse_rijdagen_pakket']);
    $rittypeNorm = trim((string) ($rit['rittype'] ?? ''));
    $useKlantRoute = !$pakketLosseRijdagen
        && !in_array($rittypeNorm, ['meerdaags', 'buitenland'], true);

    if (!$useKlantRoute) {
        $routeDays = offerte_presentatie_build_route_days($payload);
    } else {
        $routeDays = offerte_presentatie_build_klant_route_days($rit, $payload, $regelMap);
        if ($routeDays === []) {
            $routeDays = offerte_presentatie_build_route_days($payload);
        }
        // Voeg terugrit toe aan eerste dag bij brenghaal
        if ($rittypeNorm === 'brenghaal' && isset($routeDays[0])) {
            $route2Blocks = offerte_presentatie_build_route2_klant_blocks($payload, $regelMap);
            if ($route2Blocks !== []) {
                $routeDays[0]['routes'] = array_merge($routeDays[0]['routes'], $route2Blocks);
            }
        }
    }
    $intro = 'Hartelijk dank voor uw aanvraag. Wij doen u hierbij graag onze vrijblijvende offerte toekomen op basis van actuele beschikbaarheid. Hieronder vindt u de besproken ritgegevens, routeplanning en prijsopbouw.';
    if ($pakketLosseRijdagen) {
        $intro .= ' Deze offerte omvat meerdere losse rijdagen op opeenvolgende of gekozen data (tussendoor naar de zaak/garage), samengevat in één totaalprijs; de route staat per rijdag vermeld.';
    }

    return [
        'company' => [
            'name' => trim((string) ($tenantInst['bedrijfsnaam'] ?? '')),
            'address' => trim((string) ($tenantInst['adres'] ?? '')),
            'postcode' => trim((string) ($tenantInst['postcode'] ?? '')),
            'city' => trim((string) ($tenantInst['plaats'] ?? '')),
            'phone' => trim((string) ($tenantInst['telefoon'] ?? '')),
            'email' => trim((string) ($tenantInst['email'] ?? '')),
            'logo_pad' => trim((string) ($tenantInst['logo_pad'] ?? '')),
            'logo_web_src' => offerte_presentatie_logo_web_src((string) ($tenantInst['logo_pad'] ?? '')),
        ],
        'offer' => [
            'id' => (int) ($rit['id'] ?? 0),
            'order_nummer' => offerte_presentatie_order_nummer($rit),
            'date' => date('Y-m-d'),
            'date_display' => date('d-m-Y'),
            'expiry_date' => date('Y-m-d', strtotime('+14 days')),
            'expiry_date_display' => date('d-m-Y', strtotime('+14 days')),
            'status' => trim((string) ($rit['status'] ?? '')),
        ],
        'customer' => [
            'display_name' => $klantNaam,
            'company_name' => $bedrijf,
            'contact_name' => $persoon,
            'address' => $adres,
            'postcode_city' => $postcodePlaats,
            'email' => trim((string) ($rit['email'] ?? '')),
            'phone' => trim((string) ($rit['telefoon'] ?? '')),
        ],
        'salutation' => 'Beste ' . $aanhefNaam . ',',
        'intro' => $intro,
        'trip' => [
            'rittype' => trim((string) ($rit['rittype'] ?? '')),
            'rittype_label' => offerte_presentatie_rittype_label((string) ($rit['rittype'] ?? '')),
            'pakket_losse_rijdagen' => $pakketLosseRijdagen,
            'passagiers' => (int) ($rit['passagiers'] ?? 0),
            'start_date' => $startDate,
            'start_date_display' => offerte_presentatie_format_date($startDate, true),
            'end_date' => $endDate,
            'end_date_display' => offerte_presentatie_format_date($endDate, true),
            'route_label' => offerte_presentatie_route_label($rit, $regelMap, $payload),
        ],
        'route_days' => $routeDays,
        'notes' => $instructie,
        'price' => [
            'excl' => $prijsExcl,
            'btw' => $btwBedrag,
            'incl' => $prijsIncl,
            'excl_display' => offerte_presentatie_format_currency($prijsExcl),
            'btw_display' => offerte_presentatie_format_currency($btwBedrag),
            'incl_display' => offerte_presentatie_format_currency($prijsIncl),
        ],
    ];
}
