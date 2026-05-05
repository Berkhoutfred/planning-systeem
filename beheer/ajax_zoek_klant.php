<?php
/**
 * Tenant-veilige klant-zoekfunctie voor autocomplete (dashboard, calculaties, …).
 */
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$zoek = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

if (strlen($zoek) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, bedrijfsnaam, voornaam, achternaam, adres, plaats, telefoon, email
         FROM klanten
         WHERE tenant_id = ?
           AND (bedrijfsnaam LIKE ? OR voornaam LIKE ? OR achternaam LIKE ? OR email LIKE ?)
         ORDER BY bedrijfsnaam ASC, achternaam ASC
         LIMIT 15'
    );

    $term = '%' . $zoek . '%';
    $stmt->execute([$tenantId, $term, $term, $term, $term]);
    $resultaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = [];
    foreach ($resultaten as $k) {
        $naam = !empty($k['bedrijfsnaam']) ? $k['bedrijfsnaam'] : trim($k['voornaam'] . ' ' . $k['achternaam']);
        $k['weergave_naam'] = $naam;
        $output[] = $k;
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Zoeken mislukt.'], JSON_UNESCAPED_UNICODE);
}
