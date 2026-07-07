<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-Coop-Key, X-Coop-Partner, Authorization');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../beheer/includes/db.php';
require_once __DIR__ . '/../../beheer/includes/reis_netwerk.php';
require_once __DIR__ . '/../../beheer/includes/reis_media.php';
require_once __DIR__ . '/../../reizen/_prijs.php';

function coop_json(int $code, array $payload): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function coop_ok(array $data): never
{
    coop_json(200, ['ok' => true] + $data);
}

function coop_err(int $code, string $message): never
{
    coop_json($code, ['ok' => false, 'error' => $message]);
}

function coop_partner_auth(PDO $pdo): array
{
    $partnerSlug = trim((string) ($_SERVER['HTTP_X_COOP_PARTNER'] ?? $_GET['partner'] ?? ''));
    $apiKey = trim((string) ($_SERVER['HTTP_X_COOP_KEY'] ?? ''));

    if ($apiKey === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $apiKey = trim($m[1]);
        }
    }

    if ($partnerSlug === '' || $apiKey === '') {
        coop_err(401, 'Authenticatie vereist (X-Coop-Partner + X-Coop-Key).');
    }

    reis_netwerk_ensure_tables($pdo);

    $stmt = $pdo->prepare(
        'SELECT p.*, n.id AS netwerk_id, n.naam AS netwerk_naam, n.slug AS netwerk_slug,
                n.leiding_tenant_id, t.slug AS tenant_slug, t.naam AS tenant_naam
         FROM reis_netwerk_partners p
         JOIN reis_netwerken n ON n.id = p.netwerk_id AND n.status = \'active\'
         JOIN tenants t ON t.id = p.tenant_id
         WHERE t.slug = ? AND p.mag_bekijken = 1
         LIMIT 1'
    );
    $stmt->execute([$partnerSlug]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partner || !hash_equals((string) ($partner['api_key'] ?? ''), $apiKey)) {
        coop_err(403, 'Ongeldige partner of API-sleutel.');
    }

    return $partner;
}

function coop_base_url(): string
{
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'tourplan.nl');

    return $proto . '://' . $host;
}

function coop_media_url(?string $pad): ?string
{
    if ($pad === null || trim($pad) === '') {
        return null;
    }
    if (str_starts_with($pad, 'http://') || str_starts_with($pad, 'https://')) {
        return $pad;
    }

    return rtrim(coop_base_url(), '/') . '/' . ltrim($pad, '/');
}

function coop_media_srcset(string $srcset): string
{
    if (trim($srcset) === '') {
        return '';
    }

    $base = rtrim(coop_base_url(), '/');
    $parts = [];
    foreach (explode(',', $srcset) as $part) {
        $part = trim($part);
        if (preg_match('#^(/[^\s]+)\s+(\d+w)$#', $part, $m)) {
            $parts[] = $base . $m[1] . ' ' . $m[2];
        }
    }

    return implode(', ', $parts);
}

function coop_format_reis(array $r, bool $detail = false): array
{
    $base = coop_base_url();
    $bezetting = busreis_bezetting((int) ($r['boekingen'] ?? 0), (int) $r['max_deelnemers']);
    $fotoMedia = reis_media_resolve((string) ($r['foto_pad'] ?? ''), 'hero');
    $fotoPad = (string) ($r['foto_pad'] ?? '');
    $fotoSrc = $fotoMedia['src'] !== '' ? ltrim($fotoMedia['src'], '/') : $fotoPad;
    $cardMedia = $fotoPad !== '' ? reis_media_resolve($fotoPad, 'card') : ['src' => '', 'srcset' => ''];
    $out = [
        'id' => (int) $r['id'],
        'slug' => (string) $r['slug'],
        'type' => (string) $r['type'],
        'vervoerder' => (string) $r['vervoerder'],
        'titel' => (string) $r['titel'],
        'beschrijving' => (string) ($r['beschrijving'] ?? ''),
        'bestemming' => (string) ($r['bestemming'] ?? ''),
        'categorie' => (string) ($r['categorie'] ?? ''),
        'datum_van' => (string) $r['datum_van'],
        'datum_tot' => $r['datum_tot'] ? (string) $r['datum_tot'] : null,
        'vertrek_tijd' => $r['vertrek_tijd'] ? substr((string) $r['vertrek_tijd'], 0, 5) : null,
        'terug_tijd' => $r['terug_tijd'] ? substr((string) $r['terug_tijd'], 0, 5) : null,
        'prijs_pp' => (float) $r['prijs_pp'],
        'toeslag_enkelpersoon' => (float) ($r['toeslag_enkelpersoon'] ?? 0),
        'reserveringskosten' => (float) ($r['reserveringskosten'] ?? 0),
        'vroegboekkorting' => (float) ($r['vroegboekkorting'] ?? 0),
        'vroegboek_deadline' => $r['vroegboek_deadline'] ? (string) $r['vroegboek_deadline'] : null,
        'max_deelnemers' => (int) $r['max_deelnemers'],
        'boekingen' => (int) ($r['boekingen'] ?? 0),
        'plaatsen_vrij' => $bezetting['vrij'],
        'plaatsen_pct_vrij' => $bezetting['pct_vrij'],
        'vertrekgarantie' => (int) ($r['vertrekgarantie'] ?? 0) === 1,
        'anvr_sgr' => (int) ($r['anvr_sgr'] ?? 0) === 1,
        'status' => (string) $r['status'],
        'foto_url' => coop_media_url($fotoSrc !== '' ? $fotoSrc : null),
        'foto_srcset' => coop_media_srcset($fotoMedia['srcset']),
        'foto_card_url' => coop_media_url($cardMedia['src'] !== '' ? ltrim($cardMedia['src'], '/') : ($fotoSrc !== '' ? $fotoSrc : null)),
        'boek_url' => $base . '/reizen/detail.php?slug=' . rawurlencode((string) $r['slug']) . '&boek=1',
    ];

    if ($detail) {
        $out['hotel_naam'] = $r['hotel_naam'] ?? null;
        $out['hotel_sterren'] = isset($r['hotel_sterren']) ? (int) $r['hotel_sterren'] : null;
        $out['brochure_url'] = coop_media_url($r['brochure_pdf'] ?? null);
    }

    return $out;
}

/** @return list<int> Tenant-IDs waarvan gepubliceerde reizen in de API mogen verschijnen. */
function coop_api_tenant_ids(PDO $pdo, array $partner): array
{
    $ids = [(int) $partner['leiding_tenant_id']];

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tenant_modules
         WHERE tenant_id = ? AND module_code IN ('dagtochten', 'busreizen') AND actief = 1"
    );
    $stmt->execute([(int) $partner['tenant_id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        $ids[] = (int) $partner['tenant_id'];
    }

    return array_values(array_unique(array_filter(
        $ids,
        static fn(int $id): bool => !busreis_is_test_tenant_id($pdo, $id)
    )));
}

/** @return list<array<string, mixed>> */
function coop_fetch_reizen(PDO $pdo, array $partner, string $filterType = ''): array
{
    $partnerSlug = (string) $partner['tenant_slug'];
    $leidingId = (int) $partner['leiding_tenant_id'];
    $eigenId = (int) $partner['tenant_id'];
    $reizen = [];

    foreach (coop_api_tenant_ids($pdo, $partner) as $tenantId) {
        $where = ['b.tenant_id = ?', "b.status IN ('gepubliceerd','vol')", 'b.datum_van >= CURDATE()'];
        $params = [$tenantId];

        if (in_array($filterType, ['dagtocht', 'meerdaags'], true)) {
            $where[] = 'b.type = ?';
            $params[] = $filterType;
        }

        $sql = 'SELECT b.*,
            (SELECT COUNT(*) FROM busreis_boekingen bk
             WHERE bk.busreis_id = b.id AND bk.status != \'geannuleerd\') AS boekingen
            FROM busreizen b
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY b.datum_van ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = coop_format_reis($row);
            $item['boek_url'] .= '&src=' . rawurlencode($partnerSlug);
            $item['bron'] = $tenantId === $leidingId ? 'coop' : 'eigen';
            $reizen[] = $item;
        }
    }

    usort($reizen, static fn(array $a, array $b): int => strcmp((string) $a['datum_van'], (string) $b['datum_van']));

    return $reizen;
}
