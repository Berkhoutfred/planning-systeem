<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '../includes/db.php';
require_once __DIR__ . '/includes/offerte_pdf_layout.php';
require_once __DIR__ . '/includes/offerte_presentatie.php';

$tenantId = (int) current_tenant_id();
if ($tenantId <= 0) {
    die('Geen tenantcontext.');
}

$vzId = (int) ($_GET['vz_id'] ?? 0);
if ($vzId <= 0) {
    die('Geen verzameling opgegeven.');
}

$b = $pdo->prepare('SELECT id, titel FROM offerte_verzamelingen WHERE id = ? AND tenant_id = ? LIMIT 1');
$b->execute([$vzId, $tenantId]);
$bundle = $b->fetch(PDO::FETCH_ASSOC);
if (!is_array($bundle)) {
    die('Verzameling niet gevonden.');
}

$it = $pdo->prepare(
    'SELECT i.calculatie_id
     FROM offerte_verzameling_items i
     INNER JOIN calculaties c ON c.id = i.calculatie_id AND c.tenant_id = i.tenant_id
     WHERE i.verzameling_id = ? AND i.tenant_id = ?
     ORDER BY i.sort_order ASC, i.calculatie_id ASC'
);
$it->execute([$vzId, $tenantId]);
$calcIds = $it->fetchAll(PDO::FETCH_COLUMN);
$calcIds = is_array($calcIds) ? array_map('intval', $calcIds) : [];

if ($calcIds === []) {
    die('Deze verzameling bevat geen offertes.');
}

$rows = [];
foreach ($calcIds as $cid) {
    $rit = offerte_presentatie_fetch_by_id($pdo, $cid, '', $tenantId);
    if (!$rit) {
        continue;
    }
    $rows[] = [
        'view' => offerte_presentatie_build($pdo, $rit),
        'titel' => trim((string) ($rit['titel'] ?? '')),
    ];
}

if ($rows === []) {
    die('Geen geldige offertes in verzameling.');
}

// Chronologisch sorteren: oudste datum bovenaan
usort($rows, static function (array $a, array $b): int {
    $da = (string) ($a['view']['trip']['start_date'] ?? '');
    $db = (string) ($b['view']['trip']['start_date'] ?? '');
    $cmp = strcmp($da, $db);
    if ($cmp !== 0) {
        return $cmp;
    }
    return (int) ($a['view']['offer']['id'] ?? 0) <=> (int) ($b['view']['offer']['id'] ?? 0);
});

$firstView = $rows[0]['view'];
$bundleTitel = trim((string) ($bundle['titel'] ?? 'Verzamelofferte'));

$pdf = new OffertePDF();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 25);
$pdf->page_type = 'cover';
$pdf->vm = $firstView;
$pdf->AddPage();

// --- Cover-pagina: klantadressering (links) + bundel-info-box (rechts) ---
$pdf->SetY(42);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(100, 5, safe_iconv((string) ($firstView['customer']['display_name'] ?? '')), 0, 1, 'L');
if (!empty($firstView['customer']['company_name']) && !empty($firstView['customer']['contact_name'])) {
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(100, 5, safe_iconv('t.a.v. ' . (string) $firstView['customer']['contact_name']), 0, 1, 'L');
}
if (!empty($firstView['customer']['address'])) {
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(100, 5, safe_iconv((string) $firstView['customer']['address']), 0, 1, 'L');
}
if (!empty($firstView['customer']['postcode_city'])) {
    $pdf->Cell(100, 5, safe_iconv((string) $firstView['customer']['postcode_city']), 0, 1, 'L');
}

// Bundel-info box rechts
$pdf->SetXY(120, 44);
$pdf->SetFillColor(248, 251, 254);
$pdf->SetDrawColor(220, 228, 236);
$pdf->Rect(120, 42, 80, 30, 'FD');
$pdf->SetXY(125, 45);
offerte_pdf_meta_row($pdf, 'Bundel', '#' . $vzId);
$pdf->SetX(125);
offerte_pdf_meta_row($pdf, 'Datum', date('d-m-Y'));
$pdf->SetX(125);
offerte_pdf_meta_row($pdf, 'Aantal offertes', (string) count($rows));
$pdf->SetX(125);
offerte_pdf_meta_row($pdf, 'Vervaldatum', date('d-m-Y', strtotime('+14 days')));

// Zorg dat cursor onder de bundel-info-box uitkomt
$pdf->SetY(max($pdf->GetY(), 76));

// Aanhef van eerste klant
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 5, safe_iconv((string) ($firstView['salutation'] ?? '')), 0, 1, 'L');
$pdf->Ln(1);
$pdf->SetFont('Arial', '', 10);
$intro = 'Bijgaand ontvangt u een overzicht van de voor u uitgebrachte offerte(s). '
    . 'Elke offerte is op de volgende pagina\'s volledig uitgewerkt met ritgegevens, routeplanning en prijsopbouw. '
    . 'Wij vertrouwen erop u hiermee een passend aanbod te hebben gedaan.';
$pdf->MultiCell(190, 5.5, safe_iconv($intro));

// Lichte scheiding boven tabel
offerte_pdf_section_rule($pdf, 'Offerteoverzicht');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(40, 7, safe_iconv(' Datum '), 1, 0, 'R', true);
$pdf->Cell(22, 7, safe_iconv(' Offertenr. '), 1, 0, 'L', true);
$pdf->Cell(78, 7, safe_iconv(' Route '), 1, 0, 'L', true);
$pdf->Cell(50, 7, safe_iconv(' Totaal incl. '), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

$totIncl = 0.0;
foreach ($rows as $r) {
    $vw = $r['view'];
    $routeLabel = trim((string) ($vw['trip']['route_label'] ?? ''));
    if ($routeLabel === '') {
        $routeLabel = $r['titel'] !== '' ? $r['titel'] : ('Offerte #' . (string) ($vw['offer']['order_nummer'] ?? ''));
    }
    if (function_exists('mb_substr')) {
        $routeLabel = mb_substr($routeLabel, 0, 55, 'UTF-8');
    } else {
        $routeLabel = substr($routeLabel, 0, 55);
    }
    $inclCell = (string) ($vw['price']['incl_display'] ?? '');
    if ($inclCell === '') {
        $inclCell = offerte_presentatie_format_currency((float) ($vw['price']['incl'] ?? 0));
    }
    $totIncl += (float) ($vw['price']['incl'] ?? 0);
    $fill = false;
    $pdf->SetFillColor(248, 251, 254);
    $pdf->Cell(40, 7, safe_iconv((string) ($vw['trip']['start_date_display'] ?? '')), 1, 0, 'R', $fill);
    $pdf->Cell(22, 7, safe_iconv('#' . (string) ($vw['offer']['order_nummer'] ?? '')), 1, 0, 'L', $fill);
    $pdf->Cell(78, 7, safe_iconv($routeLabel), 1, 0, 'L', $fill);
    $pdf->Cell(50, 7, safe_iconv($inclCell), 1, 1, 'R', $fill);
}

// Dunne scheidingslijn + totaalregel
$pdf->SetDrawColor(180, 200, 220);
$pdf->SetLineWidth(0.3);
$pdf->Line(10, $pdf->GetY() + 1, 200, $pdf->GetY() + 1);
$pdf->SetLineWidth(0.2);
$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(217, 119, 6);
$pdf->Cell(140, 8, safe_iconv('  Totaal incl. btw'), 0, 0, 'R');
$pdf->Cell(50, 8, safe_iconv(offerte_presentatie_format_currency($totIncl)), 0, 1, 'R');
$pdf->SetTextColor(0, 0, 0);

// Slottekst
$pdf->SetFont('Arial', '', 9);
$pdf->Ln(6);
$slottekst = 'Indien van het bovenstaande programma wordt afgeweken, kan er een prijsaanpassing volgen. '
    . 'Wij vertrouwen erop u met deze offerte een passende aanbieding te hebben gedaan en zien uw reactie gaarne tegemoet. '
    . 'De aanbieding is exclusief eventuele parkeer-, tol- en/of verblijfskosten. '
    . 'Wij behouden ons het recht voor onze reissommen te wijzigen, indien daartoe aanleiding bestaat door prijs en/of brandstofverhogingen door derden.';
$pdf->MultiCell(190, 5.0, safe_iconv($slottekst));
$pdf->Ln(6);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 5, safe_iconv('Met vriendelijke groet,'), 0, 1, 'L');
$pdf->Ln(1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 5, safe_iconv((string) ($firstView['company']['name'] ?? '')), 0, 1, 'L');

// --- Individuele volledige offertes ---
foreach ($rows as $r) {
    $vw = $r['view'];
    $pdf->page_type = 'offerte';
    $pdf->vm = $vw;
    $pdf->AddPage();
    offerte_pdf_render_offer_body($pdf, $vw);
}

$slug = preg_replace('/[^A-Za-z0-9_-]/', '_', $bundleTitel !== '' ? $bundleTitel : 'Verzamelofferte');
$pdf->Output('I', 'Verzamelofferte-' . $slug . '-' . $vzId . '.pdf');
