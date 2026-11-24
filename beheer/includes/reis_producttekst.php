<?php
declare(strict_types=1);

/**
 * Producttekst voor busreizen: inbegrepen / niet-inbegrepen / hotel-inbegrepen.
 * Kindregels hangen aan busreis_id; tenant-isolatie via parent busreizen.
 */

/** @return list<string> */
function reis_producttekst_soorten(): array
{
    return ['inbegrepen', 'niet_inbegrepen', 'hotel_inbegrepen'];
}

/**
 * Standaard "inbegrepen"-regels als de tenant nog niets heeft ingevuld.
 *
 * @return list<string>
 */
function reis_inbegrepen_defaults(array $reis): array
{
    $items = [
        'Vervoer per luxe touringcar',
        'Begeleiding door reisleider',
        'Opstap dicht bij u in de regio',
    ];
    if (($reis['type'] ?? '') === 'meerdaags') {
        $items[] = 'Verblijf in hotel';
        $items[] = 'Dagprogramma';
    }
    if (!empty($reis['anvr_sgr'])) {
        $items[] = 'SGR reisgeld bescherming';
    }

    return $items;
}

/**
 * @return array{inbegrepen: list<array>, niet_inbegrepen: list<array>, hotel_inbegrepen: list<array>}
 */
function reis_productregels_laden(PDO $pdo, int $busreisId): array
{
    $out = [
        'inbegrepen' => [],
        'niet_inbegrepen' => [],
        'hotel_inbegrepen' => [],
    ];
    if ($busreisId <= 0) {
        return $out;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, soort, tekst, bedrag, toelichting, sort_order
             FROM busreis_productregels
             WHERE busreis_id = ?
             ORDER BY soort ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$busreisId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $soort = (string) ($row['soort'] ?? '');
            if (!isset($out[$soort])) {
                continue;
            }
            $out[$soort][] = $row;
        }
    } catch (PDOException $e) {
        // Tabel ontbreekt nog (migratie niet gedraaid): lege lijsten, front valt terug op defaults.
    }

    return $out;
}

/**
 * Slaat productregels op (replace-all per reis), zelfde patroon als busreis_opties.
 */
function reis_productregels_opslaan(PDO $pdo, int $busreisId, array $post): void
{
    if ($busreisId <= 0) {
        return;
    }

    try {
        $pdo->prepare('DELETE FROM busreis_productregels WHERE busreis_id = ?')->execute([$busreisId]);
    } catch (PDOException $e) {
        throw new RuntimeException(
            'Producttekst-tabel ontbreekt. Voer migrations/20261124_busreis_producttekst.sql uit.',
            0,
            $e
        );
    }

    $batches = [
        'inbegrepen' => [
            'tekst' => $post['inbegrepen_tekst'] ?? [],
        ],
        'niet_inbegrepen' => [
            'tekst' => $post['niet_inbegrepen_tekst'] ?? [],
            'bedrag' => $post['niet_inbegrepen_bedrag'] ?? [],
            'toelichting' => $post['niet_inbegrepen_toelichting'] ?? [],
        ],
        'hotel_inbegrepen' => [
            'tekst' => $post['hotel_inbegrepen_tekst'] ?? [],
        ],
    ];

    $ins = $pdo->prepare(
        'INSERT INTO busreis_productregels
            (busreis_id, soort, tekst, bedrag, toelichting, sort_order)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($batches as $soort => $fields) {
        $teksten = is_array($fields['tekst'] ?? null) ? $fields['tekst'] : [];
        $order = 0;
        foreach ($teksten as $i => $raw) {
            $tekst = trim((string) $raw);
            if ($tekst === '') {
                continue;
            }
            if (mb_strlen($tekst) > 500) {
                $tekst = mb_substr($tekst, 0, 500);
            }

            $bedrag = null;
            if ($soort === 'niet_inbegrepen') {
                $bedragRaw = trim((string) (($fields['bedrag'][$i] ?? '') ));
                if ($bedragRaw !== '') {
                    $bedrag = (float) str_replace(',', '.', $bedragRaw);
                }
            }

            $toelichting = null;
            if ($soort === 'niet_inbegrepen') {
                $toe = trim((string) ($fields['toelichting'][$i] ?? ''));
                if ($toe !== '') {
                    $toelichting = mb_strlen($toe) > 500 ? mb_substr($toe, 0, 500) : $toe;
                }
            }

            $ins->execute([$busreisId, $soort, $tekst, $bedrag, $toelichting, $order]);
            $order++;
        }
    }
}

/**
 * Publieke inbegrepen-lijst: DB of defaults.
 *
 * @param list<array<string,mixed>> $regels
 * @return list<string>
 */
function reis_inbegrepen_voor_weergave(array $reis, array $regels): array
{
    if ($regels !== []) {
        $items = [];
        foreach ($regels as $r) {
            $t = trim((string) ($r['tekst'] ?? ''));
            if ($t !== '') {
                $items[] = $t;
            }
        }
        if ($items !== []) {
            // SGR blijft zichtbaar als vlag aan staat en tenant het niet zelf noemde
            if (!empty($reis['anvr_sgr'])) {
                $hasSgr = false;
                foreach ($items as $item) {
                    if (stripos($item, 'SGR') !== false) {
                        $hasSgr = true;
                        break;
                    }
                }
                if (!$hasSgr) {
                    $items[] = 'SGR reisgeld bescherming';
                }
            }

            return $items;
        }
    }

    return reis_inbegrepen_defaults($reis);
}

/**
 * @param list<array<string,mixed>> $regels
 * @return list<array{tekst:string,bedrag:?float,toelichting:?string}>
 */
function reis_niet_inbegrepen_voor_weergave(array $regels): array
{
    $out = [];
    foreach ($regels as $r) {
        $t = trim((string) ($r['tekst'] ?? ''));
        if ($t === '') {
            continue;
        }
        $bedrag = $r['bedrag'] ?? null;
        $out[] = [
            'tekst' => $t,
            'bedrag' => $bedrag !== null && $bedrag !== '' ? (float) $bedrag : null,
            'toelichting' => ($r['toelichting'] ?? null) !== null && trim((string) $r['toelichting']) !== ''
                ? trim((string) $r['toelichting'])
                : null,
        ];
    }

    return $out;
}

/**
 * @param list<array<string,mixed>> $regels
 * @return list<string>
 */
function reis_hotel_inbegrepen_voor_weergave(array $regels): array
{
    $items = [];
    foreach ($regels as $r) {
        $t = trim((string) ($r['tekst'] ?? ''));
        if ($t !== '') {
            $items[] = $t;
        }
    }

    return $items;
}
