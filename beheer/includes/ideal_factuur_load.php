<?php
declare(strict_types=1);

require_once __DIR__ . '/ideal_factuur_helpers.php';

/**
 * @return array{ok:bool, error?:string, primary?:array, ritten?:array, klant?:array, totaal?:float}
 */
function ideal_factuur_load_bundle(PDO $pdo, int $tenantId, int $primaryRitId): array
{
    if ($primaryRitId <= 0) {
        return ['ok' => false, 'error' => 'Ongeldig rit-id.'];
    }

    $stmt = $pdo->prepare(
        'SELECT r.*, rr.omschrijving AS regel_oms, rr.van_adres, rr.naar_adres
         FROM ritten r
         LEFT JOIN ritregels rr ON rr.rit_id = r.id AND rr.tenant_id = r.tenant_id
         WHERE r.id = ? AND r.tenant_id = ?
         LIMIT 1'
    );
    $stmt->execute([$primaryRitId, $tenantId]);
    $primary = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$primary) {
        return ['ok' => false, 'error' => 'Rit niet gevonden.'];
    }

    $ids = ideal_parse_bundle_ids($primary['werk_notities'] ?? null);
    if ($ids === []) {
        $ids = [$primaryRitId];
    }
    if (!in_array($primaryRitId, $ids, true)) {
        array_unshift($ids, $primaryRitId);
        $ids = array_values(array_unique($ids));
    }
    sort($ids);

    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT r.*, rr.omschrijving AS regel_oms, rr.van_adres, rr.naar_adres
            FROM ritten r
            LEFT JOIN ritregels rr ON rr.rit_id = r.id AND rr.tenant_id = r.tenant_id
            WHERE r.tenant_id = ? AND r.id IN ($in)
            ORDER BY r.datum_start ASC, r.id ASC";
    $params = array_merge([$tenantId], $ids);
    $st2 = $pdo->prepare($sql);
    $st2->execute($params);
    $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return ['ok' => false, 'error' => 'Geen ritregels.'];
    }

    $totaal = 0.0;
    foreach ($rows as $r) {
        if ($r['prijsafspraak'] !== null && (float) $r['prijsafspraak'] > 0) {
            $totaal = max($totaal, (float) $r['prijsafspraak']);
        }
    }

    foreach ($rows as &$r) {
        $p = $r['prijsafspraak'] !== null ? (float) $r['prijsafspraak'] : 0.0;
        if ($totaal > 0 && $p > 0 && abs($p - $totaal) < 0.005) {
            $r['regel_bedrag'] = $totaal;
        } else {
            $r['regel_bedrag'] = 0.0;
        }
        $van = trim((string) ($r['van_adres'] ?? ''));
        $naar = trim((string) ($r['naar_adres'] ?? ''));
        $oms = trim((string) ($r['regel_oms'] ?? 'Rit'));
        $r['regel_oms'] = $oms . ($van !== '' || $naar !== '' ? ' — ' . $van . ' → ' . $naar : '');
    }
    unset($r);

    $klantId = (int) ($primary['klant_id'] ?? 0);
    if ($klantId <= 0) {
        return ['ok' => false, 'error' => 'Geen klant op rit (iDEAL vereist klant + e-mail).'];
    }
    $kst = $pdo->prepare('SELECT * FROM klanten WHERE id = ? AND tenant_id = ? LIMIT 1');
    $kst->execute([$klantId, $tenantId]);
    $klant = $kst->fetch(PDO::FETCH_ASSOC);
    if (!$klant) {
        return ['ok' => false, 'error' => 'Klant niet gevonden.'];
    }

    return [
        'ok' => true,
        'primary' => $primary,
        'ritten' => $rows,
        'klant' => $klant,
        'totaal' => $totaal,
    ];
}

function ideal_factuur_next_nummer(PDO $pdo, int $tid): string
{
    $jaar = date('Y');
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT factuurnummer) FROM ritten WHERE tenant_id = ? AND factuurnummer IS NOT NULL AND factuurnummer != \'\' AND factuurnummer NOT LIKE ? AND factuurnummer LIKE ?'
    );
    $stmt->execute([$tid, 'CONCEPT%', $jaar . '%']);
    $n = (int) $stmt->fetchColumn() + 1;

    return $jaar . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
}
