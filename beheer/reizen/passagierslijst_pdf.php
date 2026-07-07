<?php
declare(strict_types=1);
// Bestand: beheer/reizen/passagierslijst_pdf.php
// Exporteert een volledige passagierslijst als A4-PDF voor chauffeur en reisleider.

include '../../beveiliging.php';
require_role(['tenant_admin', 'planner_user', 'platform_owner']);
require '../includes/db.php';
require_once __DIR__ . '/_tenant_context.php';
require_once __DIR__ . '/../includes/fpdf/fpdf.php';

$reisId   = (int)($_GET['id'] ?? 0);

if (!$reisId) { header('Location: index.php'); exit; }

// ── Data ophalen ────────────────────────────────────────────────────────────
$reis = $pdo->prepare("SELECT * FROM busreizen WHERE id=? AND tenant_id=?");
$reis->execute([$reisId, $dataTenantId]);
$reis = $reis->fetch();
if (!$reis) { header('Location: index.php'); exit; }

$boekingen = $pdo->prepare("
    SELECT bk.*,
           h.naam AS halte_naam, h.adres AS halte_adres, h.vertrek_tijd AS halte_tijd
    FROM busreis_boekingen bk
    LEFT JOIN busreis_haltes h ON h.id = bk.halte_id
    WHERE bk.busreis_id = ? AND bk.tenant_id = ? AND bk.status != 'geannuleerd'
    ORDER BY h.vertrek_tijd ASC, bk.achternaam ASC, bk.voornaam ASC
");
$boekingen->execute([$reisId, $dataTenantId]);
$boekingen = $boekingen->fetchAll();

// Deelnemers ophalen per boeking
$deelnemers = [];
foreach ($boekingen as $b) {
    $stmt = $pdo->prepare("SELECT * FROM busreis_deelnemers WHERE boeking_id=? ORDER BY is_hoofdboeker DESC, id ASC");
    $stmt->execute([$b['id']]);
    $deelnemers[$b['id']] = $stmt->fetchAll();
}

// Haltes (voor volgorde en tijden)
$haltes = $pdo->prepare("SELECT * FROM busreis_haltes WHERE busreis_id=? ORDER BY sort_order, vertrek_tijd ASC");
$haltes->execute([$reisId]);
$haltes = $haltes->fetchAll();

// Statistieken
$totaalPax   = array_sum(array_column($boekingen, 'aantal_deelnemers'));
$totaalOmzet = array_sum(array_column($boekingen, 'totaal'));
$aantalBetaald = count(array_filter($boekingen, fn($b) => $b['betaal_status'] === 'betaald'));

// NL maanden
$nlMaanden = ['January'=>'Januari','February'=>'Februari','March'=>'Maart','April'=>'April',
    'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Augustus','September'=>'September',
    'October'=>'Oktober','November'=>'November','December'=>'December'];
$datumStr  = strtr(date('d F Y', strtotime($reis['datum_van'])), $nlMaanden);

// Groepeer boekingen per halte (of "Geen halte")
$perHalte = [];
foreach ($boekingen as $b) {
    $key = $b['halte_naam'] ?: '— Geen opstapplaats opgegeven —';
    $perHalte[$key][] = $b;
}

// ── FPDF klasse met aangepaste header/footer ────────────────────────────────
function s(string $txt): string
{
    return @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $txt) ?: $txt;
}

class PassagiersLijstPDF extends FPDF
{
    public string $reisTitel  = '';
    public string $datumStr   = '';
    public string $afdrukDatum = '';
    public int    $totaalPax  = 0;

    public function Header(): void
    {
        // Navy balk bovenaan
        $this->SetFillColor(0, 40, 85);
        $this->Rect(0, 0, 210, 14, 'F');

        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(8, 3);
        $this->Cell(120, 8, s('PASSAGIERSLIJST — ' . $this->reisTitel), 0, 0, 'L');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(180, 210, 255);
        $this->SetXY(8, 7);
        $this->Cell(100, 5, s('Coach Travel × Berkhout Reizen'), 0, 0, 'L');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(220, 235, 255);
        $this->SetXY(130, 4);
        $this->Cell(70, 5, s($this->datumStr . '   |   ' . $this->totaalPax . ' deelnemers'), 0, 0, 'R');

        $this->SetY(16);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(95, 5, s('Afgedrukt: ' . $this->afdrukDatum . '   |   Vertrouwelijk — uitsluitend bestemd voor chauffeur en reisleider'), 0, 0, 'L');
        $this->Cell(95, 5, s('Pagina ' . $this->PageNo() . '/{nb}'), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new PassagiersLijstPDF('P', 'mm', 'A4');
$pdf->reisTitel   = $reis['titel'];
$pdf->datumStr    = $datumStr;
$pdf->afdrukDatum = date('d-m-Y H:i');
$pdf->totaalPax   = $totaalPax;
$pdf->AliasNbPages();
$pdf->SetMargins(8, 18, 8);
$pdf->SetAutoPageBreak(true, 14);

// ── PAGINA 1: VOORBLAD ──────────────────────────────────────────────────────
$pdf->AddPage();

// Trip infoblok
$pdf->SetFillColor(245, 248, 255);
$pdf->SetDrawColor(220, 230, 245);
$pdf->RoundedRect = null; // FPDF heeft geen rounded rect, we doen gewone

$pdf->Rect(8, 18, 194, 48, 'FD');

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 40, 85);
$pdf->SetXY(14, 22);
$pdf->Cell(130, 9, s($reis['titel']), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(60, 80, 100);
$pdf->SetX(14);
$pdf->Cell(130, 6, s($datumStr), 0, 1, 'L');

// Info kolommen
$col1x = 14;
$col2x = 80;
$col3x = 145;
$infoY = 36;
$lh    = 6;

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(100, 120, 140);

$infoItems = [
    [$col1x, 'VERTREKDATUM', $datumStr],
    [$col1x, 'VERTREKTIJD',  $reis['vertrek_tijd'] ? substr($reis['vertrek_tijd'],0,5).' uur' : '—'],
    [$col2x, 'TYPE',         $reis['type'] === 'meerdaags' ? 'Meerdaagse reis' : 'Dagtocht'],
    [$col2x, 'DEELNEMERS',   $totaalPax . ' personen (' . count($boekingen) . ' boekingen)'],
    [$col3x, 'BETAALD',      $aantalBetaald . ' / ' . count($boekingen) . ' boekingen'],
    [$col3x, 'OMZET',        '€ ' . number_format($totaalOmzet,2,',','.')],
];

$iRow = 0;
foreach ($infoItems as [$cx, $lbl, $val]) {
    $yPos = $infoY + ($iRow % 2) * 12;
    if ($iRow % 2 === 0 && $iRow > 0) $iRow++; // skip
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetTextColor(130, 155, 185);
    $pdf->SetXY($cx, $yPos);
    $pdf->Cell(60, 4, s($lbl), 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0, 40, 85);
    $pdf->SetX($cx);
    $pdf->Cell(60, 5, s((string)$val), 0, 1, 'L');
    $iRow++;
}

// Chauffeur / bus velden
$fieldY = 50;
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(130, 155, 185);
$pdf->SetXY($col1x, $fieldY);
$pdf->Cell(55, 4, s('CHAUFFEUR'), 0, 0, 'L');
$pdf->SetXY($col2x, $fieldY);
$pdf->Cell(55, 4, s('KENTEKEN / BUS'), 0, 0, 'L');
$pdf->SetXY($col3x, $fieldY);
$pdf->Cell(55, 4, s('REISLEIDER'), 0, 0, 'L');

$pdf->SetDrawColor(180, 200, 230);
$pdf->SetLineWidth(0.3);
$lineY = $fieldY + 9;
$pdf->Line($col1x, $lineY, $col1x + 58, $lineY);
$pdf->Line($col2x, $lineY, $col2x + 58, $lineY);
$pdf->Line($col3x, $lineY, $col3x + 58, $lineY);
$pdf->SetDrawColor(220, 230, 245);

// Opmerkingen/bijzonderheden blok
$specialeWensen = array_filter($boekingen, fn($b) => !empty($b['opmerkingen']));
if (!empty($specialeWensen)) {
    $pdf->SetY(70);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0, 40, 85);
    $pdf->SetFillColor(255, 251, 235);
    $pdf->SetDrawColor(253, 224, 135);
    $pdf->Cell(194, 6, s('  ⚠  BIJZONDERE WENSEN / AANDACHTSPUNTEN'), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(80, 60, 0);
    foreach ($specialeWensen as $b) {
        $naam = $b['voornaam'] . ' ' . $b['achternaam'];
        $pdf->SetX(8);
        $pdf->MultiCell(194, 5, s("  {$naam} [{$b['boeking_ref']}]: {$b['opmerkingen']}"), 0, 'L');
    }
    $pdf->SetDrawColor(220, 230, 245);
}

// Halte-overzicht
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 40, 85);
$pdf->SetFillColor(235, 243, 255);
$pdf->SetDrawColor(200, 220, 245);
$pdf->Cell(194, 6, s('  OVERZICHT PER OPSTAPPLAATS'), 1, 1, 'L', true);

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(0, 40, 85);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 6, s('Opstapplaats'), 1, 0, 'L', true);
$pdf->Cell(30, 6, s('Vertrektijd'), 1, 0, 'C', true);
$pdf->Cell(30, 6, s('Deelnemers'), 1, 0, 'C', true);
$pdf->Cell(64, 6, s('Adres'), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(30, 50, 70);
$alternatief = false;
foreach ($perHalte as $halteNaam => $hBoeking) {
    $paxAantal = array_sum(array_column($hBoeking, 'aantal_deelnemers'));
    $halteRij = array_values(array_filter($haltes, fn($h) => $h['naam'] === $halteNaam));
    $halteInfo = $halteRij[0] ?? null;
    $tijd   = $halteInfo ? substr($halteInfo['vertrek_tijd'] ?? '', 0, 5) : '—';
    $adres  = $halteInfo ? ($halteInfo['adres'] ?? '—') : '—';

    $pdf->SetFillColor($alternatief ? 245 : 255, $alternatief ? 248 : 255, $alternatief ? 255 : 255);
    $pdf->Cell(70, 5, s($halteNaam), 1, 0, 'L', true);
    $pdf->Cell(30, 5, s($tijd), 1, 0, 'C', true);
    $pdf->Cell(30, 5, s($paxAantal . ' pers. (' . count($hBoeking) . ' boek.)'), 1, 0, 'C', true);
    $pdf->Cell(64, 5, s($adres), 1, 1, 'L', true);
    $alternatief = !$alternatief;
}
$pdf->SetDrawColor(200, 220, 245);

// ── PER HALTE: deelnemerslijsten ────────────────────────────────────────────
$colW = [8, 8, 52, 24, 40, 24, 38]; // check, #, naam, ref, opties, betaal, opmerkingen
$colH = ['', '#', 'Naam', 'Referentie', s("Bijboekingen"), 'Betaling', 'Opmerkingen'];
$tabelBreedte = array_sum($colW); // = 194

$volgnummer = 0;

foreach ($perHalte as $halteNaam => $hBoekingen) {
    $pdf->AddPage();

    // Halte-kop banner
    $pdf->SetFillColor(0, 74, 173);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Rect(8, 18, 194, 10, 'F');
    $pdf->SetXY(12, 19);

    $halteRij = array_values(array_filter($haltes, fn($h) => $h['naam'] === $halteNaam));
    $halteInfo = $halteRij[0] ?? null;
    $tijd = $halteInfo ? substr($halteInfo['vertrek_tijd'] ?? '', 0, 5) : '';
    $paxTotaal = array_sum(array_column($hBoekingen, 'aantal_deelnemers'));
    $tijdLabel = $tijd ? "   ({$tijd} uur)" : '';
    $pdf->Cell(130, 8, s("  🚏  {$halteNaam}{$tijdLabel}"), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(200, 225, 255);
    $pdf->Cell(60, 8, s("{$paxTotaal} deelnemers"), 0, 1, 'R');

    $pdf->SetY(30);
    $pdf->SetTextColor(0, 0, 0);

    // Adres halte
    if ($halteInfo && $halteInfo['adres']) {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 120, 140);
        $pdf->SetX(8);
        $pdf->Cell(194, 5, s('Adres: ' . $halteInfo['adres']), 0, 1, 'L');
    }

    // Tabelkop
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(0, 40, 85);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(0, 40, 85);
    $pdf->SetX(8);
    foreach ($colW as $ci => $cw) {
        $pdf->Cell($cw, 6, s($colH[$ci]), 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Rijen
    $rijAlt = false;
    foreach ($hBoekingen as $b) {
        $isHoofd = true; // eerste rij van boeking = hoofdboeker
        $deelns  = $deelnemers[$b['id']] ?? [];
        $opties  = json_decode($b['gekozen_opties'] ?? '[]', true) ?: [];
        $optiesStr = implode(', ', array_column($opties, 'naam'));

        // Betaalstatus kleur
        if ($b['betaal_status'] === 'betaald') {
            $betaalStr  = 'Betaald';
            $betaalKleur = [0, 120, 60];
        } elseif ($b['betaal_status'] === 'open') {
            $betaalStr  = 'Open';
            $betaalKleur = [160, 100, 0];
        } else {
            $betaalStr  = ucfirst($b['betaal_status']);
            $betaalKleur = [180, 0, 0];
        }

        // Enkelpersoon
        $extra = [];
        if ($b['enkelpersoon_toeslag']) $extra[] = '1p-kamer';
        if ($extra) $optiesStr = implode(', ', array_merge($extra, array_column($opties, 'naam')));

        // Rijen per deelnemer
        foreach ($deelns as $di => $d) {
            $volgnummer++;
            $naam = trim($d['voornaam'] . ' ' . $d['achternaam']);
            if (!$naam) $naam = trim($b['voornaam'] . ' ' . $b['achternaam']);
            $isHB = (bool)$d['is_hoofdboeker'];

            // Controleer pagina-einde
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFillColor(0, 74, 173);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Rect(8, 18, 194, 9, 'F');
                $pdf->SetXY(12, 19);
                $pdf->Cell(190, 7, s("  🚏  {$halteNaam} (vervolg)"), 0, 1, 'L');
                $pdf->SetY(30);
                $pdf->SetFont('Arial', 'B', 7.5);
                $pdf->SetFillColor(0, 40, 85);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetDrawColor(0, 40, 85);
                $pdf->SetX(8);
                foreach ($colW as $ci => $cw) {
                    $pdf->Cell($cw, 6, s($colH[$ci]), 1, 0, 'C', true);
                }
                $pdf->Ln();
            }

            $bgR = $rijAlt ? 245 : 255;
            $bgG = $rijAlt ? 248 : 255;
            $bgB = $rijAlt ? 255 : 255;
            if ($isHB) { $bgR = 235; $bgG = 243; $bgB = 255; }

            $pdf->SetFillColor($bgR, $bgG, $bgB);
            $pdf->SetDrawColor(210, 220, 235);
            $pdf->SetTextColor(20, 40, 60);
            $pdf->SetX(8);

            $rijH = 6;

            // Checkbox
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell($colW[0], $rijH, '[ ]', 1, 0, 'C', true);

            // Nummer
            $pdf->Cell($colW[1], $rijH, s((string)$volgnummer), 1, 0, 'C', true);

            // Naam (hoofdboeker bold)
            if ($isHB) {
                $pdf->SetFont('Arial', 'B', 8);
            } else {
                $pdf->SetFont('Arial', '', 8);
            }
            $naamLabel = $naam . ($isHB ? ' *' : '');
            $pdf->Cell($colW[2], $rijH, s($naamLabel), 1, 0, 'L', true);

            // Referentie (alleen hoofdboeker)
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($colW[3], $rijH, s($isHB ? $b['boeking_ref'] : ''), 1, 0, 'C', true);

            // Bijboekingen (alleen hoofdboeker)
            $pdf->Cell($colW[4], $rijH, s($isHB ? (mb_substr($optiesStr,0,28,'UTF-8')) : ''), 1, 0, 'L', true);

            // Betaling
            if ($isHB) {
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->SetTextColor(...$betaalKleur);
            } else {
                $pdf->SetFont('Arial', '', 7);
                $pdf->SetTextColor(160, 160, 160);
            }
            $pdf->Cell($colW[5], $rijH, s($isHB ? $betaalStr : ''), 1, 0, 'C', true);

            // Opmerkingen (alleen hoofdboeker)
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->SetTextColor(80, 80, 80);
            $oms = $isHB ? mb_substr($b['opmerkingen'] ?? '', 0, 30, 'UTF-8') : '';
            $pdf->Cell($colW[6], $rijH, s($oms), 1, 1, 'L', true);

            $pdf->SetTextColor(20, 40, 60);
            $pdf->SetFont('Arial', '', 8);

            // Noodcontact (direct onder hoofdboeker, licht geel)
            if ($isHB && $b['telefoon_thuisblijver']) {
                $pdf->SetFillColor(255, 251, 230);
                $pdf->SetFont('Arial', 'I', 7);
                $pdf->SetTextColor(120, 80, 0);
                $pdf->SetX(8);
                $pdf->Cell($colW[0] + $colW[1], $rijH - 1, '', 0, 0, 'C', true);
                $pdf->Cell(
                    array_sum(array_slice($colW, 2)),
                    $rijH - 1,
                    s("  📞 Thuisblijver: {$b['telefoon_thuisblijver']}   |   📧 {$b['email']}   |   📱 {$b['telefoon']}"),
                    0, 1, 'L', true
                );
                $pdf->SetTextColor(20, 40, 60);
                $pdf->SetFillColor($bgR, $bgG, $bgB);
            }
        }

        // Scheidingslijn per boeking
        $pdf->SetDrawColor(190, 210, 235);
        $pdf->Line(8, $pdf->GetY(), 202, $pdf->GetY());
        $pdf->SetDrawColor(210, 220, 235);
        $rijAlt = !$rijAlt;
    }

    // Halte-subtotaal
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->SetTextColor(0, 40, 85);
    $pdf->SetX(8);
    $pdf->Cell(array_sum(array_slice($colW,0,3)), 5, s("  Totaal {$halteNaam}"), 1, 0, 'L', true);
    $pdf->Cell(array_sum(array_slice($colW,3)), 5, s("{$paxTotaal} deelnemers  |  " . count($hBoekingen) . ' boekingen'), 1, 1, 'C', true);
}

// ── SLOTPAGINA: SAMENVATTING ────────────────────────────────────────────────
$pdf->AddPage();

// Opties samenvatting
$alleOpties = [];
foreach ($boekingen as $b) {
    $opties = json_decode($b['gekozen_opties'] ?? '[]', true) ?: [];
    foreach ($opties as $o) {
        $k = $o['naam'];
        if (!isset($alleOpties[$k])) $alleOpties[$k] = ['naam'=>$k,'prijs'=>$o['prijs'],'aantal'=>0,'omzet'=>0];
        $alleOpties[$k]['aantal'] += $b['aantal_deelnemers'];
        $alleOpties[$k]['omzet']  += $o['prijs'] * $b['aantal_deelnemers'];
    }
}

$pdf->SetY(20);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 40, 85);
$pdf->Cell(194, 8, s('Samenvatting — ' . $reis['titel']), 0, 1, 'L');

// Hoofdstats
$statItems = [
    ['Totaal deelnemers',  $totaalPax . ' personen'],
    ['Totaal boekingen',   count($boekingen) . ' boekingen'],
    ['Betaald',            $aantalBetaald . ' / ' . count($boekingen)],
    ['Openstaand',         (count($boekingen) - $aantalBetaald) . ' boekingen'],
    ['Totale omzet',       '€ ' . number_format($totaalOmzet,2,',','.')],
];

$pdf->SetFillColor(245, 248, 255);
$pdf->SetDrawColor(210, 225, 245);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(40, 60, 80);

foreach ($statItems as [$lbl, $val]) {
    $pdf->SetX(8);
    $pdf->Cell(70, 6, s($lbl), 1, 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(50, 6, s($val), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 9);
}

// Opties
if (!empty($alleOpties)) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 40, 85);
    $pdf->Cell(194, 7, s('Bijboekingen overzicht'), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(0, 40, 85);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetX(8);
    $pdf->Cell(90, 6, s('Optie'), 1, 0, 'L', true);
    $pdf->Cell(30, 6, s('Prijs p.p.'), 1, 0, 'C', true);
    $pdf->Cell(30, 6, s('Aantal'), 1, 0, 'C', true);
    $pdf->Cell(44, 6, s('Omzet'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(30, 50, 70);
    $altOpt = false;
    foreach ($alleOpties as $o) {
        $pdf->SetFillColor($altOpt ? 245 : 255, $altOpt ? 248 : 255, $altOpt ? 255 : 255);
        $pdf->SetX(8);
        $pdf->Cell(90, 5, s($o['naam']), 1, 0, 'L', true);
        $pdf->Cell(30, 5, s('€ ' . number_format($o['prijs'],2,',','.')), 1, 0, 'C', true);
        $pdf->Cell(30, 5, s($o['aantal'] . ' pers.'), 1, 0, 'C', true);
        $pdf->Cell(44, 5, s('€ ' . number_format($o['omzet'],2,',','.')), 1, 1, 'C', true);
        $altOpt = !$altOpt;
    }
}

// Handtekening ruimte
$pdf->Ln(12);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 40, 85);
$pdf->Cell(97, 5, s('Handtekening chauffeur:'), 0, 0, 'L');
$pdf->Cell(97, 5, s('Handtekening reisleider:'), 0, 1, 'L');

$pdf->SetDrawColor(160, 180, 210);
$pdf->Line(8,  $pdf->GetY() + 14, 100, $pdf->GetY() + 14);
$pdf->Line(105, $pdf->GetY() + 14, 197, $pdf->GetY() + 14);
$pdf->Ln(18);

$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(140, 140, 140);
$pdf->Cell(194, 5, s('Naam: ________________________________'), 0, 0, 'L');
$pdf->Cell(0, 5, s('Naam: ________________________________'), 0, 1, 'L');

// Slotbalk
$pdf->Ln(8);
$pdf->SetFillColor(0, 40, 85);
$pdf->Rect(8, $pdf->GetY(), 194, 10, 'F');
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetX(8);
$pdf->Cell(194, 10,
    s('  Coach Travel × Berkhout Reizen   |   085 - 486 20 07   |   info@coachtravel.nl   |   Goede reis!'),
    0, 1, 'C');

// ── OUTPUT ──────────────────────────────────────────────────────────────────
$bestandsnaam = 'passagierslijst_' . preg_replace('/[^a-z0-9]/i', '_', $reis['titel']) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $bestandsnaam);
exit;
