<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    coop_err(405, 'Alleen GET toegestaan.');
}

$partner = coop_partner_auth($pdo);
$partnerSlug = (string) $partner['tenant_slug'];
$tenantIds = coop_api_tenant_ids($pdo, $partner);

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug !== '') {
    $reis = null;
    $id = 0;

    foreach ($tenantIds as $tenantId) {
        $stmt = $pdo->prepare(
            "SELECT b.*,
                (SELECT COUNT(*) FROM busreis_boekingen bk
                 WHERE bk.busreis_id = b.id AND bk.status != 'geannuleerd') AS boekingen
             FROM busreizen b
             WHERE b.tenant_id = ? AND b.slug = ? AND b.status IN ('gepubliceerd','vol')
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $slug]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($candidate) {
            $reis = $candidate;
            $id = (int) $reis['id'];
            break;
        }
    }

    if (!$reis) {
        coop_err(404, 'Reis niet gevonden.');
    }

    $data = coop_format_reis($reis, true);
    $data['boek_url'] .= '&src=' . rawurlencode($partnerSlug);
    $data['bron'] = (int) $reis['tenant_id'] === (int) $partner['leiding_tenant_id'] ? 'coop' : 'eigen';

    $haltes = $pdo->prepare('SELECT id, naam, adres, vertrek_tijd, sort_order FROM busreis_haltes WHERE busreis_id=? ORDER BY sort_order, vertrek_tijd');
    $haltes->execute([$id]);
    $data['haltes'] = array_map(static function (array $h): array {
        return [
            'id' => (int) $h['id'],
            'naam' => (string) $h['naam'],
            'adres' => (string) ($h['adres'] ?? ''),
            'vertrek_tijd' => $h['vertrek_tijd'] ? substr((string) $h['vertrek_tijd'], 0, 5) : null,
        ];
    }, $haltes->fetchAll(PDO::FETCH_ASSOC));

    $opties = $pdo->prepare('SELECT id, naam, prijs, sort_order FROM busreis_opties WHERE busreis_id=? ORDER BY sort_order');
    $opties->execute([$id]);
    $data['opties'] = array_map(static function (array $o): array {
        return [
            'id' => (int) $o['id'],
            'naam' => (string) $o['naam'],
            'prijs' => (float) $o['prijs'],
        ];
    }, $opties->fetchAll(PDO::FETCH_ASSOC));

    $dagprog = $pdo->prepare('SELECT dag_nummer, titel, omschrijving AS beschrijving FROM busreis_dagprogramma WHERE busreis_id=? ORDER BY sort_order, dag_nummer');
    $dagprog->execute([$id]);
    $data['dagprogramma'] = $dagprog->fetchAll(PDO::FETCH_ASSOC);

    coop_ok([
        'netwerk' => (string) $partner['netwerk_naam'],
        'partner' => $partnerSlug,
        'reis' => $data,
    ]);
}

$filterType = trim((string) ($_GET['type'] ?? ''));
$reizen = coop_fetch_reizen($pdo, $partner, $filterType);

coop_ok([
    'netwerk' => (string) $partner['netwerk_naam'],
    'partner' => $partnerSlug,
    'count' => count($reizen),
    'reizen' => $reizen,
]);
