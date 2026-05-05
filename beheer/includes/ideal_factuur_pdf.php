<?php
/**
 * Concept- of definitieve factuur-PDF voor dashboard-iDEAL-ritten (FPDF).
 */
declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';
require_once __DIR__ . '/tenant_instellingen_db.php';

function ideal_pdf_safe_iconv(string $text): string
{
    return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
}

/**
 * @param array<int, array<string, mixed>> $ritten rijen met omschrijving, van, naar, datum_start, prijsafspraak
 * @param array<string, mixed> $klant
 * @return string PDF binary (Output S)
 */
function ideal_factuur_render_pdf(array $ritten, array $klant, string $factuurNummerLabel, bool $isConcept, float $totaalIncl, int $tenantId = 0): string
{
    $klantNaam = !empty($klant['bedrijfsnaam'])
        ? (string) $klant['bedrijfsnaam']
        : trim(($klant['voornaam'] ?? '') . ' ' . ($klant['achternaam'] ?? ''));

    $tenantNaam = 'BusAI';
    $tenantLogo = '';
    $tenantAdres = '';
    $tenantPcPlaats = '';
    $tenantEmail = '';
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $tenantCfg = tenant_instellingen_get($GLOBALS['pdo'], $tenantId);
        $tenantNaam = (string) ($tenantCfg['bedrijfsnaam'] ?? 'BusAI');
        $tenantLogo = trim((string) ($tenantCfg['logo_pad'] ?? ''));
        $tenantAdres = trim((string) ($tenantCfg['adres'] ?? ''));
        $tenantPcPlaats = trim((string) ($tenantCfg['postcode'] ?? '') . ' ' . (string) ($tenantCfg['plaats'] ?? ''));
        $tenantEmail = trim((string) ($tenantCfg['email'] ?? ''));
    }

    $pdf = new class extends FPDF {
        public string $tenantNaam = 'BusAI';
        public string $tenantLogoAbs = '';
        public string $tenantAdres = '';
        public string $tenantPcPlaats = '';
        public string $tenantEmail = '';

        public function Header(): void
        {
            $logoPad = $this->tenantLogoAbs;
            if (is_file($logoPad)) {
                $this->Image($logoPad, 10, 8, 55);
            } else {
                $this->SetFont('Arial', 'B', 14);
                $this->Cell(50, 10, ideal_pdf_safe_iconv($this->tenantNaam), 0, 0, 'L');
            }
        }

        public function Footer(): void
        {
            $this->SetY(-16);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 4, ideal_pdf_safe_iconv('Factuur gegenereerd via kantoorportaal. Controleer gegevens voor verzending.'), 0, 0, 'C');
        }
    };
    $pdf->tenantNaam = $tenantNaam;
    $pdf->tenantLogoAbs = $tenantLogo !== '' ? (__DIR__ . '/../' . ltrim($tenantLogo, '/')) : (__DIR__ . '/../images/berkhout_logo.png');
    $pdf->tenantAdres = $tenantAdres;
    $pdf->tenantPcPlaats = $tenantPcPlaats;
    $pdf->tenantEmail = $tenantEmail;
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $blauw = [0, 51, 102];
    $rood = [220, 53, 69];
    $oranje = [255, 94, 20];

    $xLeft = 10;
    $xRight = 120;
    $yTop = 32;

    if ($isConcept) {
        $pdf->SetXY($xRight, 24);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor($rood[0], $rood[1], $rood[2]);
        $pdf->Cell(80, 6, 'CONCEPT (nog niet verzonden)', 0, 1, 'R');
        $pdf->SetTextColor(0);
    }

    $pdf->SetY($yTop);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(80, 4, ideal_pdf_safe_iconv($tenantAdres !== '' ? $tenantAdres : ($tenantNaam . ' / administratie')), 0, 1, 'L');
    if ($tenantPcPlaats !== '') {
        $pdf->Cell(80, 4, ideal_pdf_safe_iconv($tenantPcPlaats), 0, 1, 'L');
    }
    if ($tenantEmail !== '') {
        $pdf->Cell(80, 4, ideal_pdf_safe_iconv($tenantEmail), 0, 1, 'L');
    }

    $pdf->SetXY($xRight, 58);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 5, ideal_pdf_safe_iconv($klantNaam), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetX($xRight);
    $pdf->Cell(80, 4, ideal_pdf_safe_iconv((string) ($klant['adres'] ?? '')), 0, 1, 'L');
    $pdf->SetX($xRight);
    $pc = trim((string) ($klant['postcode'] ?? '') . ' ' . (string) ($klant['plaats'] ?? ''));
    $pdf->Cell(80, 4, ideal_pdf_safe_iconv($pc), 0, 1, 'L');
    $pdf->SetX($xRight);
    $pdf->Cell(80, 4, ideal_pdf_safe_iconv((string) ($klant['email'] ?? '')), 0, 1, 'L');

    $pdf->SetY($yTop + 28);
    $pdf->SetX($xLeft);
    $factuurDatum = date('d-m-Y');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, ideal_pdf_safe_iconv('Zutphen, ' . $factuurDatum), 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(40, 5, ideal_pdf_safe_iconv('Debiteurnummer:'), 0, 0, 'L');
    $pdf->Cell(50, 5, ideal_pdf_safe_iconv((string) ($klant['id'] ?? '')), 0, 1, 'L');
    $pdf->Cell(40, 5, ideal_pdf_safe_iconv('Factuurnummer:'), 0, 0, 'L');
    $pdf->Cell(50, 5, ideal_pdf_safe_iconv($factuurNummerLabel), 0, 1, 'L');

    $w_datum = 26;
    $w_oms = 124;
    $w_tot = 40;
    $pdf->Ln(6);
    $pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($w_datum, 7, ideal_pdf_safe_iconv('  Datum'), 0, 0, 'L', true);
    $pdf->Cell($w_oms, 7, ideal_pdf_safe_iconv('Omschrijving / route'), 0, 0, 'L', true);
    $pdf->Cell($w_tot, 7, ideal_pdf_safe_iconv('Bedrag  '), 0, 1, 'R', true);
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 9);

    foreach ($ritten as $rit) {
        $datumStr = date('d-m-Y', strtotime((string) $rit['datum_start']));
        $oms = (string) ($rit['regel_oms'] ?? 'Rit');
        $bedrag = isset($rit['regel_bedrag']) ? (float) $rit['regel_bedrag'] : 0.0;
        $pdf->Cell($w_datum, 7, '  ' . ideal_pdf_safe_iconv($datumStr), 'B', 0, 'L');
        $pdf->Cell($w_oms, 7, ideal_pdf_safe_iconv($oms), 'B', 0, 'L');
        $pdf->Cell($w_tot, 7, chr(128) . ' ' . number_format($bedrag, 2, ',', '.') . '  ', 'B', 1, 'R');
    }

    $totaalExcl = $totaalIncl / 1.09;
    $btw = $totaalIncl - $totaalExcl;
    $totaalAfgerond = round($totaalIncl / 5) * 5;

    $pdf->Ln(10);
    $xT = 110;
    $pdf->SetDrawColor($oranje[0], $oranje[1], $oranje[2]);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($xT, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(2);
    $wL = 60;
    $wB = 30;
    $pdf->SetX($xT);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($wL, 5, ideal_pdf_safe_iconv('Subtotaal (excl. btw)'), 0, 0, 'R');
    $pdf->Cell($wB, 5, chr(128) . ' ' . number_format($totaalExcl, 2, ',', '.') . '  ', 0, 1, 'R');
    $pdf->SetX($xT);
    $pdf->Cell($wL, 5, ideal_pdf_safe_iconv('Btw 9%'), 0, 0, 'R');
    $pdf->Cell($wB, 5, chr(128) . ' ' . number_format($btw, 2, ',', '.') . '  ', 0, 1, 'R');
    $pdf->Ln(2);
    $pdf->SetX(10);
    $pdf->SetFillColor($blauw[0], $blauw[1], $blauw[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(190 - $wB, 7, ideal_pdf_safe_iconv('Totaal te voldoen (iDEAL):'), 0, 0, 'R', true);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell($wB, 7, chr(128) . ' ' . number_format($totaalAfgerond, 2, ',', '.') . '  ', 0, 1, 'R', true);
    $pdf->SetTextColor(0);

    $pdf->Ln(12);
    $pdf->SetFont('Arial', '', 9);
    $txt = $isConcept
        ? 'Dit is een voorbeeld. Na uw akkoord in het portaal wordt de definitieve factuur per e-mail verstuurd en wordt de iDEAL-betaallink geactiveerd.'
        : 'Betaal veilig via de iDEAL-link in uw e-mail. Bij vragen: neem contact op met het kantoor.';
    $pdf->MultiCell(0, 5, ideal_pdf_safe_iconv($txt), 0, 'C');

    return $pdf->Output('S');
}
