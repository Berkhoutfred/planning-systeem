<?php
/**
 * PDF: loonuren + CAO-toeslagen meerdaagse reis Slowakije — printversie.
 * CLI:  php beheer/loon_meerdaagse_overzicht_pdf.php
 * Web:  beheer/loon_meerdaagse_overzicht_pdf.php  (download / printen in browser)
 */

declare(strict_types=1);

require __DIR__ . '/includes/fpdf/fpdf.php';

function pdf_txt(string $text): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
}

function pdf_eur(float $bedrag): string
{
    return pdf_txt(number_format($bedrag, 2, ',', '.'));
}

function pdf_uren(float $uren): string
{
    return pdf_txt(number_format($uren, 2, ',', '.'));
}

const TARIEF_ZATERDAG = 4.01;
const TARIEF_ZONDAG   = 6.04;
const TARIEF_NACHT    = 4.01;

/** @return array{meta: array<string, string>, chauffeurs: array<int, string>, dagen: array<int, array<string, mixed>>} */
function slowakije_april_2026_data(): array
{
    return [
        'meta' => [
            'bestemming'     => 'Slowakije',
            'periode'        => '11-04-2026 t/m 17-04-2026',
            'vertrek_nl'     => '11-04-2026 22:00',
            'aankomst_sk'    => '12-04-2026 13:30',
            'retour_vertrek' => '16-04-2026 22:00',
            'terugkomst_nl'  => '17-04-2026 13:30',
        ],
        'chauffeurs' => ['Hilbert van Dam', 'Hans Roordink'],
        'dagen' => [
            ['label' => '11-04', 'dag' => 'Za', 'cao' => '1e dag reis',     'rit' => '22:00-24:00',              'rit_u' => 2.00,  'loon_u' => 2.00,  'toeslagen' => [['Zaterdag', 2.00, TARIEF_ZATERDAG]]],
            ['label' => '12-04', 'dag' => 'Zo', 'cao' => 'Tussenliggend',   'rit' => '00:00-13:30 aankomst',     'rit_u' => 13.50, 'loon_u' => 8.00,  'toeslagen' => [['Zondag', 13.50, TARIEF_ZONDAG]]],
            ['label' => '13-04', 'dag' => 'Ma', 'cao' => 'Tussenliggend',   'rit' => '08:00-17:00',              'rit_u' => 9.00,  'loon_u' => 8.00,  'toeslagen' => []],
            ['label' => '14-04', 'dag' => 'Di', 'cao' => 'Tussenliggend',   'rit' => '08:00-18:00',              'rit_u' => 10.00, 'loon_u' => 8.00,  'toeslagen' => []],
            ['label' => '15-04', 'dag' => 'Wo', 'cao' => 'Tussenliggend',   'rit' => '08:00-17:15',              'rit_u' => 9.25,  'loon_u' => 8.00,  'toeslagen' => []],
            ['label' => '16-04', 'dag' => 'Do', 'cao' => 'Tussenliggend',   'rit' => '22:00-24:00 retour',       'rit_u' => 2.00,  'loon_u' => 8.00,  'toeslagen' => []],
            ['label' => '17-04', 'dag' => 'Vr', 'cao' => 'Laatste dag reis', 'rit' => '00:00-13:30 terugkomst',  'rit_u' => 13.50, 'loon_u' => 13.50, 'toeslagen' => [['Nacht 00-06', 6.00, TARIEF_NACHT]]],
        ],
    ];
}

class LoonPrintPdf extends FPDF
{
    public function header(): void
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, pdf_txt('Loonoverzicht meerdaagse reis — Slowakije'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, pdf_txt('Taxi Berkhout  |  CAO Touringcar  |  April 2026'), 0, 1, 'C');
        $this->SetDrawColor(0, 0, 0);
        $this->Line(15, 20, 195, 20);
        $this->Ln(3);
    }

    public function footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(95, 5, pdf_txt('Afgedrukt: ' . date('d-m-Y H:i')), 0, 0, 'L');
        $this->Cell(0, 5, pdf_txt('Pagina ' . $this->PageNo()), 0, 0, 'R');
    }
}

/** @param array<int, string> $chauffeurs */
function genereer_loon_meerdaagse_pdf(array $chauffeurs, string $outputPath = '', string $outputMode = 'I'): void
{
    $data = slowakije_april_2026_data();
    $meta = $data['meta'];
    $dagen = $data['dagen'];

    $totaalLoon = 0.0;
    $totaalToeslag = 0.0;

    foreach ($dagen as $dag) {
        $totaalLoon += (float) $dag['loon_u'];
        foreach ($dag['toeslagen'] as $t) {
            $totaalToeslag += (float) $t[1] * (float) $t[2];
        }
    }

    $pdf = new LoonPrintPdf('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetMargins(15, 24, 15);
    $pdf->AddPage();

    // Reisgegevens — compact 2 kolommen
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 5, pdf_txt('Reisgegevens'), 0, 1);
    $pdf->SetFont('Arial', '', 8.5);
    $links = [
        'Periode'        => $meta['periode'],
        'Vertrek NL'     => $meta['vertrek_nl'],
        'Aankomst SK'    => $meta['aankomst_sk'],
    ];
    $rechts = [
        'Chauffeurs'     => implode(' / ', $chauffeurs),
        'Retour vertrek' => $meta['retour_vertrek'],
        'Terugkomst NL'  => $meta['terugkomst_nl'],
    ];
    $y0 = $pdf->GetY();
    foreach ($links as $k => $v) {
        $pdf->Cell(28, 4.5, pdf_txt($k . ':'), 0, 0);
        $pdf->Cell(62, 4.5, pdf_txt($v), 0, 1);
    }
    $pdf->SetXY(105, $y0);
    foreach ($rechts as $k => $v) {
        $pdf->SetX(105);
        $pdf->Cell(28, 4.5, pdf_txt($k . ':'), 0, 0);
        $pdf->Cell(0, 4.5, pdf_txt($v), 0, 1);
    }
    $pdf->Ln(2);

    // Hoofdtabel — printvriendelijk (zwart/wit, duidelijke randen)
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 5, pdf_txt('Verloning per kalenderdag (per chauffeur)'), 0, 1);

    $w = [18, 10, 28, 38, 14, 14, 38, 16];
    $hdr = ['Datum', 'Dag', 'CAO-status', 'Werkelijke rit', 'Rit u', 'Loon u', 'Toeslag', 'EUR'];
    $align = ['L', 'C', 'L', 'L', 'R', 'R', 'L', 'R'];

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    for ($i = 0; $i < count($hdr); $i++) {
        $pdf->Cell($w[$i], 7, pdf_txt($hdr[$i]), 1, 0, $align[$i]);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetLineWidth(0.2);

    foreach ($dagen as $dag) {
        $toeslagTxt = '-';
        $eurTxt = '-';
        $eur = 0.0;
        foreach ($dag['toeslagen'] as $t) {
            $e = (float) $t[1] * (float) $t[2];
            $eur += $e;
            $toeslagTxt = pdf_uren((float) $t[1]) . ' u ' . $t[0];
        }
        if ($eur > 0) {
            $eurTxt = pdf_eur($eur);
        }

        $pdf->Cell($w[0], 6, pdf_txt($dag['label']), 1, 0, 'L');
        $pdf->Cell($w[1], 6, pdf_txt($dag['dag']), 1, 0, 'C');
        $pdf->Cell($w[2], 6, pdf_txt($dag['cao']), 1, 0, 'L');
        $pdf->Cell($w[3], 6, pdf_txt($dag['rit']), 1, 0, 'L');
        $pdf->Cell($w[4], 6, pdf_uren((float) $dag['rit_u']), 1, 0, 'R');
        $pdf->Cell($w[5], 6, pdf_uren((float) $dag['loon_u']), 1, 0, 'R');
        $pdf->Cell($w[6], 6, pdf_txt($toeslagTxt), 1, 0, 'L');
        $pdf->Cell($w[7], 6, pdf_txt($eurTxt), 1, 1, 'R');
    }

    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 7, pdf_txt('TOTAAL PER CHAUFFEUR'), 1, 0, 'R');
    $pdf->Cell($w[4], 7, '', 1, 0, 'R');
    $pdf->Cell($w[5], 7, pdf_uren($totaalLoon), 1, 0, 'R');
    $pdf->Cell($w[6], 7, pdf_txt('Onregelmatigheid'), 1, 0, 'L');
    $pdf->Cell($w[7], 7, pdf_eur($totaalToeslag), 1, 1, 'R');

    $pdf->Ln(3);

    // Samenvatting
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 5, pdf_txt('Samenvatting'), 0, 1);
    $pdf->SetFont('Arial', '', 8.5);

    $samenvatting = [
        ['Loonuren per chauffeur', pdf_uren($totaalLoon) . ' uur'],
        ['  waarvan 1e dag (11-04, dienstdeel 2 u)', '2,00 uur'],
        ['  waarvan 5x tussenliggend (12-16-04)', '40,00 uur'],
        ['  waarvan laatste dag (17-04)', '13,50 uur'],
        ['Toeslagen per chauffeur', 'EUR ' . pdf_eur($totaalToeslag)],
        ['  zaterdag 11-04 (2,00 u x EUR 4,01)', 'EUR 8,02'],
        ['  zondag 12-04 (13,50 u x EUR 6,04)', 'EUR 81,54'],
        ['  nacht 17-04 (6,00 u x EUR 4,01)', 'EUR 24,06'],
        ['---', '---'],
        ['TOTAAL ' . count($chauffeurs) . ' chauffeurs — loonuren', pdf_uren($totaalLoon * count($chauffeurs)) . ' uur'],
        ['TOTAAL ' . count($chauffeurs) . ' chauffeurs — toeslagen', 'EUR ' . pdf_eur($totaalToeslag * count($chauffeurs))],
    ];

    foreach ($samenvatting as [$label, $waarde]) {
        $bold = str_starts_with($label, 'TOTAAL');
        $pdf->SetFont('Arial', $bold ? 'B' : '', 8.5);
        $pdf->Cell(120, 5, pdf_txt($label), 0, 0, 'L');
        $pdf->Cell(0, 5, pdf_txt($waarde), 0, 1, 'R');
    }

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->MultiCell(0, 3.5, pdf_txt(
        'Loonuren volgen CAO art. 16 (meerdaagse reis): 1e dag = 6/6 dienstdeel op die kalenderdag (2 u). '
        . 'Tussenliggende dagen = 8 u netto. Laatste dag = 6/6 dienstdeel (13,5 u). '
        . 'Toeslagen volgen art. 37 lid 2 over werkelijke rijtijd (tarieven 2026). '
        . 'Niet opgenomen: zakelijke vergoeding (art. 40) en onderbrekingstoeslag.'
    ), 0, 'L');

    // Handtekeningregels voor print
    $pdf->Ln(8);
    $pdf->SetFont('Arial', '', 9);
    $y = $pdf->GetY();
    $pdf->Cell(85, 5, pdf_txt('Gecontroleerd door:'), 0, 0);
    $pdf->Cell(85, 5, pdf_txt('Datum:'), 0, 1);
    $pdf->Ln(10);
    $pdf->Line(15, $pdf->GetY(), 95, $pdf->GetY());
    $pdf->Line(110, $pdf->GetY(), 160, $pdf->GetY());

    if ($outputPath !== '') {
        $pdf->Output('F', $outputPath);
        return;
    }

    $pdf->Output($outputMode, 'Loon_Slowakije_april_2026.pdf');
}

// --- CLI ---
if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $dir = __DIR__ . '/exports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $out = $dir . '/Loon_Slowakije_april_2026_PRINT.pdf';
    $data = slowakije_april_2026_data();
    genereer_loon_meerdaagse_pdf($data['chauffeurs'], $out);
    echo "PDF opgeslagen: {$out}\n";
    exit(0);
}

// --- Web: open in browser en print (Ctrl+P / Cmd+P) ---
require __DIR__ . '/../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);

$data = slowakije_april_2026_data();
genereer_loon_meerdaagse_pdf($data['chauffeurs'], '', 'I');
