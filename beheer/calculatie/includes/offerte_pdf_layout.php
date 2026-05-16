<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/fpdf/fpdf.php';


function safe_iconv($text): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) ($text ?? ''));
}

function offerte_pdf_logo_path(string $logoPad): string
{
    $logoPad = trim($logoPad);
    if ($logoPad === '') {
        return '';
    }

    $clean = ltrim($logoPad, '/');
    $candidates = [
        dirname(__DIR__) . '/' . $clean,
        dirname(__DIR__, 2) . '/' . $clean,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && filesize($candidate) > 0 && @getimagesize($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function offerte_pdf_section_header(FPDF $pdf, string $title): void
{
    $pdf->Ln(6);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(190, 7, safe_iconv('  ' . strtoupper($title)), 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
}

/**
 * Lichte sectie-scheiding: blauw vetgedrukte titel + dunne blauwe lijn; geen gevuld blok.
 */
function offerte_pdf_section_rule(FPDF $pdf, string $title): void
{
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(190, 5, safe_iconv($title), 0, 1, 'L');
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->SetLineWidth(0.35);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
}

function offerte_pdf_meta_row(FPDF $pdf, string $label, string $value): void
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(90, 102, 118);
    $pdf->Cell(38, 6, safe_iconv($label), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(52, 6, safe_iconv($value), 0, 1, 'L');
}

function offerte_pdf_kv_row(FPDF $pdf, string $label, string $value, bool $fill = false): void
{
    if ($fill) {
        $pdf->SetFillColor(248, 251, 254);
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(45, 7, safe_iconv('  ' . $label), 0, 0, 'L', $fill);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(145, 7, safe_iconv($value), 0, 1, 'L', $fill);
    $pdf->SetDrawColor(235, 235, 235);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
}

class OffertePDF extends FPDF
{
    public array $vm = [];
    /** 'offerte' = individueel offerte-blad (meta-box in header); anders = cover/default */
    public string $page_type = 'default';
    private array $widths = [];
    private array $aligns = [];

    public function Header(): void
    {
        $company = $this->vm['company'] ?? [];
        $offer   = $this->vm['offer']   ?? [];
        $logoPath = offerte_pdf_logo_path((string) ($company['logo_pad'] ?? ''));

        if ($logoPath !== '') {
            $this->Image($logoPath, 10, 10, 52);
        } else {
            $this->SetXY(10, 12);
            $this->SetFont('Arial', 'B', 20);
            $this->SetTextColor(0, 51, 102);
            $this->Cell(95, 8, safe_iconv((string) ($company['name'] ?? 'Offerte')), 0, 0, 'L');
        }

        if ($this->page_type === 'offerte' && !empty($offer['order_nummer'])) {
            // Meta-infobox rechtsboven iets hoger zodat er ruimte is boven de oranjelijn
            $this->SetFillColor(248, 251, 254);
            $this->SetDrawColor(220, 228, 236);
            $this->Rect(118, 7, 82, 21, 'FD');
            $this->SetXY(122, 8);
            offerte_pdf_meta_row($this, 'Offertenummer', '#' . (string) ($offer['order_nummer'] ?? ''));
            $this->SetX(122);
            offerte_pdf_meta_row($this, 'Offertedatum', (string) ($offer['date_display'] ?? ''));
            $this->SetX(122);
            offerte_pdf_meta_row($this, 'Vervaldatum', (string) ($offer['expiry_date_display'] ?? ''));
        } else {
            // Cover-pagina: bewaar het italic label rechts
            $this->SetXY(120, 14);
            $this->SetFont('Arial', 'I', 12);
            $this->SetTextColor(217, 119, 6);
            $this->Cell(80, 5, safe_iconv('Offerteoverzicht'), 0, 1, 'R');
        }

        $this->Ln(8);
        $this->SetDrawColor(217, 119, 6);
        $this->SetLineWidth(0.6);
        $this->Line(10, 32, 200, 32);
    }

    public function Footer(): void
    {
        $company = $this->vm['company'] ?? [];
        $chunks = [];
        if (!empty($company['address'])) {
            $chunks[] = (string) $company['address'];
        }
        $postcodeCity = trim((string) ($company['postcode'] ?? '') . ' ' . (string) ($company['city'] ?? ''));
        if ($postcodeCity !== '') {
            $chunks[] = $postcodeCity;
        }
        if (!empty($company['phone'])) {
            $chunks[] = 'T: ' . (string) $company['phone'];
        }
        if (!empty($company['email'])) {
            $chunks[] = 'E: ' . (string) $company['email'];
        }

        $this->SetY(-22);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(10, 275, 200, 275);
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, safe_iconv($chunks !== [] ? implode('  |  ', $chunks) : (string) ($company['name'] ?? '')), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150);
        $this->Cell(0, 5, safe_iconv('Offerte ' . (string) ($company['name'] ?? '')), 0, 0, 'C');
    }

    public function SetWidths(array $widths): void
    {
        $this->widths = $widths;
    }

    public function SetAligns(array $aligns): void
    {
        $this->aligns = $aligns;
    }

    public function Row(array $data, bool $header = false): void
    {
        $nb = 0;
        foreach ($data as $i => $txt) {
            $nb = max($nb, $this->NbLines($this->widths[$i] ?? 20, (string) $txt));
        }
        $h = 5 * max(1, $nb);
        $this->CheckPageBreak($h);

        foreach ($data as $i => $txt) {
            $w = $this->widths[$i] ?? 20;
            $a = $this->aligns[$i] ?? 'L';
            $x = $this->GetX();
            $y = $this->GetY();

            $this->SetDrawColor(230, 236, 243);
            if ($header) {
                $this->SetFillColor(0, 51, 102);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 8);
                $this->Rect($x, $y, $w, $h, 'FD');
            } else {
                $this->SetFillColor(255, 255, 255);
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('Arial', '', 8.5);
                $this->Rect($x, $y, $w, $h);
            }

            $this->MultiCell($w, 5, safe_iconv((string) $txt), 0, $a, false);
            $this->SetXY($x + $w, $y);
        }

        $this->Ln($h);
    }

    private function CheckPageBreak(float $h): void
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    private function NbLines(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'] ?? [];
        if ($w === 0.0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

function offerte_pdf_render_route_table(OffertePDF $pdf, array $route, bool $showZone): void
{
    if (($route['rows'] ?? []) === []) {
        return;
    }

    $routeLabel = trim((string) ($route['label'] ?? ''));
    if ($routeLabel !== '') {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(190, 6, safe_iconv($routeLabel), 0, 1, 'L');
    }

    if (($route['table_type'] ?? 'segment_table') === 'legacy_route') {
        // Km niet tonen op klantenofferte
        $widths = $showZone ? [26, 128, 16] : [26, 164];
        $header = $showZone ? ['Tijd', 'Locatie', 'Zone'] : ['Tijd', 'Locatie'];
        $aligns = $showZone ? ['L', 'L', 'C'] : ['L', 'L'];
        $pdf->SetWidths($widths);
        $pdf->SetAligns($aligns);
        $pdf->Row($header, true);

        foreach ($route['rows'] as $row) {
            $cells = $showZone
                ? [(string) $row['time_display'], (string) $row['location'], (string) $row['zone_display']]
                : [(string) $row['time_display'], (string) $row['location']];
            $pdf->Row($cells);
        }
        $pdf->Ln(2);
        return;
    }

    // Aankomsttijd niet tonen in offerte (vertrektijd is de commitment; aankomst is schatting)
    // Kolombreedte: Vertrek 22 + Van 84 + Naar 84 = 190mm (geen zone) | met zone: 22+76+76+16=190
    $widths = $showZone ? [22, 76, 76, 16] : [22, 84, 84];
    $header = $showZone ? ['Vertrek', 'Van', 'Naar', 'Zone'] : ['Vertrek', 'Van', 'Naar'];
    $aligns = $showZone ? ['L', 'L', 'L', 'C'] : ['L', 'L', 'L'];
    $pdf->SetWidths($widths);
    $pdf->SetAligns($aligns);
    $pdf->Row($header, true);

    foreach ($route['rows'] as $row) {
        $cells = $showZone
            ? [(string) $row['depart_display'], (string) $row['from'], (string) $row['to'], (string) $row['zone_display']]
            : [(string) $row['depart_display'], (string) $row['from'], (string) $row['to']];
        $pdf->Row($cells);
    }
    $pdf->Ln(2);
}

function offerte_pdf_render_event_table(OffertePDF $pdf, array $day): void
{
    if (($day['events'] ?? []) === []) {
        return;
    }

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(190, 6, safe_iconv('Dagactiviteiten'), 0, 1, 'L');

    $showZone = !empty($day['show_zone']);
    // Km niet tonen op klantenofferte
    $widths = $showZone ? [22, 38, 18, 46, 50, 16] : [22, 44, 20, 52, 52];
    $header = $showZone ? ['Type', 'Datum', 'Tijd', 'Van', 'Naar', 'Zone'] : ['Type', 'Datum', 'Tijd', 'Van', 'Naar'];
    $aligns = $showZone ? ['L', 'L', 'L', 'L', 'L', 'C'] : ['L', 'L', 'L', 'L', 'L'];
    $pdf->SetWidths($widths);
    $pdf->SetAligns($aligns);
    $pdf->Row($header, true);

    foreach ($day['events'] as $row) {
        $cells = $showZone
            ? [(string) $row['label'], (string) $row['date_display'], (string) $row['time_display'], (string) $row['from'], (string) $row['to'], (string) $row['zone_display']]
            : [(string) $row['label'], (string) $row['date_display'], (string) $row['time_display'], (string) $row['from'], (string) $row['to']];
        $pdf->Row($cells);
    }
    $pdf->Ln(2);
}

function offerte_pdf_render_offer_body(OffertePDF $pdf, array $view): void
{
    $pdf->SetY(42);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(190, 5, safe_iconv((string) ($view['customer']['display_name'] ?? '')), 0, 1, 'L');
    if (!empty($view['customer']['company_name']) && !empty($view['customer']['contact_name'])) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(190, 5, safe_iconv('t.a.v. ' . (string) $view['customer']['contact_name']), 0, 1, 'L');
    }
    if (!empty($view['customer']['address'])) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(190, 5, safe_iconv((string) $view['customer']['address']), 0, 1, 'L');
    }
    if (!empty($view['customer']['postcode_city'])) {
        $pdf->Cell(190, 5, safe_iconv((string) $view['customer']['postcode_city']), 0, 1, 'L');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(190, 5, safe_iconv((string) ($view['salutation'] ?? '')), 0, 1, 'L');
    $pdf->Ln(1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(190, 5.5, safe_iconv((string) ($view['intro'] ?? '')));

    offerte_pdf_section_header($pdf, 'Ritgegevens');
    $fill = false;
    offerte_pdf_kv_row($pdf, 'Soort reis', (string) ($view['trip']['rittype_label'] ?? ''), $fill); $fill = !$fill;
    offerte_pdf_kv_row($pdf, 'Aantal passagiers', (string) ($view['trip']['passagiers'] ?? 0) . ' personen', $fill); $fill = !$fill;
    offerte_pdf_kv_row($pdf, 'Vertrekdatum', (string) ($view['trip']['start_date_display'] ?? ''), $fill); $fill = !$fill;
    offerte_pdf_kv_row($pdf, 'Einddatum', (string) ($view['trip']['end_date_display'] ?? ''), $fill);
    if (!empty($view['trip']['pakket_losse_rijdagen'])) {
        $fill = !$fill;
        offerte_pdf_kv_row($pdf, 'Meerdere losse rijdagen', 'Ja (één offerte, route per dag)', $fill);
    }

    if (($view['route_days'] ?? []) === []) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(190, 6, safe_iconv('Er zijn nog geen routegegevens beschikbaar.'), 0, 1, 'L');
    } else {
        foreach ($view['route_days'] as $day) {
            $pdf->SetFillColor(248, 251, 254);
            $pdf->SetDrawColor(226, 234, 242);
            $pdf->Rect(10, $pdf->GetY(), 190, 8, 'FD');
            // Alleen datum, geen route-label
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->Cell(190, 8, safe_iconv((string) ($day['date_display'] ?? '')), 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);

            foreach ($day['routes'] as $route) {
                if (!empty($route['inline_with_day_heading'])) {
                    $route['label'] = '';
                }
                offerte_pdf_render_route_table($pdf, $route, !empty($route['show_zone']));
            }
            offerte_pdf_render_event_table($pdf, $day);
        }
    }

    if (!empty($view['notes'])) {
        offerte_pdf_section_header($pdf, 'Bijzonderheden');
        $pdf->SetFont('Arial', '', 9.5);
        $pdf->MultiCell(190, 5.5, safe_iconv((string) $view['notes']));
    }

    offerte_pdf_section_header($pdf, 'Prijs');
    $pdf->SetDrawColor(235, 235, 235);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    // Labels rechts uitlijnen, direct voor de bedragen (spacer 90 + label 60 + bedrag 40 = 190mm)
    $pdf->Cell(90, 6, '', 0, 0);
    $pdf->Cell(60, 6, safe_iconv('Excl. btw'), 0, 0, 'R');
    $pdf->Cell(40, 6, safe_iconv((string) ($view['price']['excl_display'] ?? '')), 0, 1, 'R');
    $pdf->Cell(90, 6, '', 0, 0);
    $pdf->Cell(60, 6, safe_iconv('BTW-bedrag'), 0, 0, 'R');
    $pdf->Cell(40, 6, safe_iconv((string) ($view['price']['btw_display'] ?? '')), 0, 1, 'R');
    // Dunne scheidingslijn boven het totaalregel (alleen rechterhelft)
    $pdf->SetDrawColor(180, 200, 220);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(100, $pdf->GetY() + 1, 200, $pdf->GetY() + 1);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(217, 119, 6);
    $pdf->Cell(90, 8, '', 0, 0);
    $pdf->Cell(60, 8, safe_iconv('Totaal incl. btw'), 0, 0, 'R');
    $pdf->Cell(40, 8, safe_iconv((string) ($view['price']['incl_display'] ?? '')), 0, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Ln(5);
    $slottekst = 'Indien van het bovenstaande programma wordt afgeweken, kan er een prijsaanpassing volgen. '
        . 'Wij vertrouwen erop u met deze offerte een passende aanbieding te hebben gedaan en zien uw reactie gaarne tegemoet. '
        . 'De aanbieding is exclusief eventuele parkeer-, tol- en/of verblijfskosten. '
        . 'Wij behouden ons het recht voor onze reissommen te wijzigen, indien daartoe aanleiding bestaat door prijs en/of brandstofverhogingen door derden.';
    $pdf->MultiCell(190, 5.0, safe_iconv($slottekst));
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 5, safe_iconv('Met vriendelijke groet,'), 0, 1, 'L');
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(190, 5, safe_iconv((string) ($view['company']['name'] ?? '')), 0, 1, 'L');
}

