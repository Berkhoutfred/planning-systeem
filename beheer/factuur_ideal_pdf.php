<?php
/**
 * PDF-preview of definitieve factuur voor iDEAL-wizard (GET).
 */
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once 'includes/ideal_factuur_load.php';
require_once 'includes/ideal_factuur_pdf.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    http_response_code(403);
    exit('Geen tenant.');
}

$ritId = isset($_GET['rit_id']) ? (int) $_GET['rit_id'] : 0;
$concept = !isset($_GET['definitief']) || $_GET['definitief'] !== '1';

$bundle = ideal_factuur_load_bundle($pdo, $tenantId, $ritId);
if (!$bundle['ok']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $bundle['error'] ?? 'Onbekende fout';

    exit;
}

$primary = $bundle['primary'];
if (($primary['betaalwijze'] ?? '') !== 'iDEAL') {
    http_response_code(400);
    exit('Deze rit is geen iDEAL-factuur.');
}

$fnLabel = $concept
    ? ('CONCEPT (rit #' . $ritId . ')')
    : (string) ($primary['factuurnummer'] ?? 'CONCEPT');

$pdfBin = ideal_factuur_render_pdf(
    $bundle['ritten'],
    $bundle['klant'],
    $fnLabel,
    $concept,
    (float) $bundle['totaal'],
    $tenantId
);

$fname = $concept ? 'Concept_factuur_rit_' . $ritId . '.pdf' : 'Factuur_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $fnLabel) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fname . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfBin;
