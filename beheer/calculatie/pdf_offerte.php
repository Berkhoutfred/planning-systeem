<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '../includes/db.php';
require_once __DIR__ . '/includes/offerte_pdf_layout.php';
require_once __DIR__ . '/includes/offerte_presentatie.php';

if (!isset($_GET['id']) || $_GET['id'] === '' || $_GET['id'] === '0') {
    die('Geen ID opgegeven.');
}

$id = (int) $_GET['id'];
if ($id <= 0) {
    die('Geen ID opgegeven.');
}

$publicToken = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_GET['token']) : '';
$tenantId = $publicToken !== '' ? 0 : (int) current_tenant_id();

$rit = offerte_presentatie_fetch_by_id($pdo, $id, $publicToken, $tenantId > 0 ? $tenantId : null);
if (!$rit) {
    die('Rit niet gevonden in database.');
}

$view = offerte_presentatie_build($pdo, $rit);
$pdf = new OffertePDF();
$pdf->vm = $view;
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

offerte_pdf_render_offer_body($pdf, $view);

$slug = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($view['company']['name'] ?? 'Offerte'));
$pdf->Output('I', 'Offerte-' . $slug . '-' . (string) ($view['offer']['order_nummer'] ?? '000') . '.pdf');
