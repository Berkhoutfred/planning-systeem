<?php
declare(strict_types=1);

require 'beheer/includes/db.php';
require_once 'beheer/calculatie/includes/offerte_presentatie.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$rawToken = $_POST['offerte_token'] ?? $_GET['token'] ?? '';
$token = preg_replace('/[^a-zA-Z0-9]/', '', (string) $rawToken);

if ($token === '') {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Ongeldige of ontbrekende link.</h2>");
}

$rit = offerte_presentatie_fetch_by_token($pdo, $token);
if (!$rit) {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze offerte is niet gevonden of de link is verlopen.</h2>");
}

$tenantId = (int) ($rit['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze offerte is niet gevonden of de link is verlopen.</h2>");
}

$view = offerte_presentatie_build($pdo, $rit);
$actie_uitgevoerd = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actie'])) {
    if ($_POST['actie'] === 'accepteren' && $rit['status'] !== 'klant_akkoord' && $rit['status'] !== 'geaccepteerd') {
        $pax = !empty($_POST['definitieve_pax']) ? (int) $_POST['definitieve_pax'] : (int) ($rit['passagiers'] ?? 0);
        $contact = trim((string) ($_POST['contact_dag_zelf'] ?? ''));
        $nu = date('Y-m-d H:i:s');

        $upd = $pdo->prepare(
            "UPDATE calculaties SET status = 'klant_akkoord', geaccepteerd_op = ?, definitieve_pax = ?, contact_dag_zelf = ? WHERE id = ? AND tenant_id = ?"
        );
        $upd->execute([$nu, $pax, $contact, $rit['id'], $tenantId]);

        if ($upd->rowCount() === 0) {
            die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze actie kon niet worden uitgevoerd.</h2>");
        }

        $rit['status'] = 'klant_akkoord';
        $actie_uitgevoerd = 'klant_akkoord';

        $splitBestand = 'beheer/includes/split_ritten.php';
        if (file_exists($splitBestand)) {
            require_once $splitBestand;
            if (function_exists('maakParapluRittenAan')) {
                maakParapluRittenAan($pdo, (int) $rit['id']);
            }
        }

        $naarEmail = 'info@berkhoutreizen.nl';
        $onderwerp = 'NIEUW AKKOORD: Offerte #' . $view['offer']['order_nummer'];
        $bericht = "Beste planner,\n\n";
        $bericht .= 'Klant ' . ($view['customer']['display_name'] ?: 'onbekend') . " heeft zojuist digitaal akkoord gegeven op de offerte.\n\n";
        $bericht .= 'Ritdatum: ' . date('d-m-Y', strtotime((string) ($rit['rit_datum'] ?? 'now'))) . "\n";
        $bericht .= 'Definitieve Pax: ' . $pax . "\n";
        $bericht .= 'Contact op de dag zelf: ' . $contact . "\n\n";
        $bericht .= "De rit is inmiddels op het live planbord gezet. Ga naar het dashboard om de bus in te plannen en de bevestiging te versturen.\n\n";
        $bericht .= "https://www.berkhoutreizen.nl/beheer/";

        $headers = "From: no-reply@berkhoutreizen.nl\r\n";
        $headers .= "Reply-To: no-reply@berkhoutreizen.nl\r\n";

        @mail($naarEmail, $onderwerp, $bericht, $headers);
    } elseif ($_POST['actie'] === 'wijziging' && $rit['status'] !== 'geaccepteerd' && $rit['status'] !== 'klant_akkoord') {
        $opmerking = trim((string) ($_POST['klant_opmerking'] ?? ''));

        $upd = $pdo->prepare(
            "UPDATE calculaties SET status = 'wijziging_verzocht', klant_opmerking = ? WHERE id = ? AND tenant_id = ?"
        );
        $upd->execute([$opmerking, $rit['id'], $tenantId]);

        if ($upd->rowCount() === 0) {
            die("<h2 style='text-align:center; font-family:sans-serif; margin-top:50px; color:#dc3545;'>Deze actie kon niet worden uitgevoerd.</h2>");
        }

        $rit['status'] = 'wijziging_verzocht';
        $actie_uitgevoerd = 'wijziging';
    }
}

$pdfOfferteUrl = 'beheer/calculatie/pdf_offerte.php?id=' . (int) $rit['id'] . '&token=' . rawurlencode($token);
$hideForms = $rit['status'] === 'klant_akkoord'
    || $actie_uitgevoerd === 'klant_akkoord'
    || $rit['status'] === 'geaccepteerd'
    || $rit['status'] === 'wijziging_verzocht'
    || $actie_uitgevoerd === 'wijziging';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offerte #<?php echo h($view['offer']['order_nummer']); ?> - <?php echo h($view['company']['name'] ?: 'Offerte'); ?></title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --brand-blue: #003366;
            --light-blue: #90caf9;
            --page-bg: #eef4f9;
            --card-bg: #ffffff;
            --line: #d8e3ee;
            --muted: #5f6f82;
            --accent: #d97706;
            --success: #28a745;
            --warning: #f1b70b;
        }

        body {
            margin: 0;
            padding: 32px 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            background: linear-gradient(180deg, var(--brand-blue) 0, var(--brand-blue) 220px, var(--page-bg) 220px);
        }

        .main-wrapper {
            max-width: 1440px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .split-layout {
            display: flex;
            min-height: 85vh;
        }

        .preview-side {
            flex: 1 1 auto;
            min-width: 0;
            padding: 28px;
            background: #f5f8fc;
            overflow: auto;
            border-right: 1px solid #dde7f0;
        }

        .offer-page {
            max-width: 920px;
            margin: 0 auto;
            padding: 34px;
            background: var(--card-bg);
            border: 1px solid #dfe7f0;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .offer-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 24px;
        }

        .brand-block {
            min-width: 0;
        }

        .brand-logo {
            max-height: 72px;
            max-width: 240px;
            display: block;
            margin-bottom: 10px;
        }

        .brand-name {
            font-size: 28px;
            font-weight: 700;
            color: var(--brand-blue);
            margin: 0 0 10px;
        }

        .brand-lines {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .meta-card {
            min-width: 260px;
            background: #f8fbfe;
            border: 1px solid #dbe7f3;
            border-top: 4px solid var(--light-blue);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 14px;
            padding: 6px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .meta-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .meta-row span:first-child {
            color: var(--muted);
        }

        .meta-row strong {
            color: var(--brand-blue);
        }

        .section-box {
            background: #fff;
            border: 1px solid var(--line);
            border-top: 4px solid var(--light-blue);
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .box-header {
            background: #f8fbfe;
            padding: 14px 18px;
            border-bottom: 1px solid #e8eef5;
        }

        .box-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--brand-blue);
        }

        .box-body {
            padding: 18px;
        }

        .address-grid,
        .trip-grid,
        .price-grid {
            display: grid;
            gap: 16px;
        }

        .address-grid {
            grid-template-columns: 1.1fr 1fr;
        }

        .trip-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .trip-card,
        .mini-card,
        .price-card {
            background: #f8fbfe;
            border: 1px solid #dbe7f3;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .mini-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .mini-value {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.45;
        }

        .route-day {
            margin-bottom: 18px;
            border: 1px solid #e3ecf4;
            border-radius: 10px;
            overflow: hidden;
        }

        .route-day:last-child {
            margin-bottom: 0;
        }

        .route-day-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 12px 16px;
            background: #f8fbfe;
            border-bottom: 1px solid #e6eef6;
        }

        .route-day-title {
            font-weight: 700;
            color: var(--brand-blue);
        }

        .route-day-date {
            color: var(--muted);
            font-size: 13px;
        }

        .route-block {
            padding: 14px 16px 2px;
        }

        .route-block-title {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-blue);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .route-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 14px;
        }

        .route-table th {
            text-align: left;
            background: var(--brand-blue);
            color: #fff;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .route-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .route-table tr:last-child td {
            border-bottom: none;
        }

        .route-col-time,
        .route-col-km {
            white-space: nowrap;
        }

        .events-wrap {
            padding: 0 16px 16px;
        }

        .price-grid {
            grid-template-columns: 1.1fr 1fr 1fr;
        }

        .price-card.primary {
            background: #eef7ff;
            border-color: #b8daff;
        }

        .price-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--brand-blue);
        }

        .price-card.primary .price-value {
            color: var(--accent);
        }

        .intro-text,
        .notes-text,
        .closing-text {
            color: #374151;
            font-size: 14px;
            line-height: 1.7;
        }

        .closing-text {
            margin-top: 22px;
        }

        .action-side {
            flex: 0 0 360px;
            padding: 28px 24px;
            background: #fdfefe;
            overflow-y: auto;
        }

        .info-blok {
            background: #f4f8fb;
            border-left: 3px solid var(--brand-blue);
            padding: 12px 14px;
            margin-bottom: 24px;
            border-radius: 0 6px 6px 0;
        }

        .info-blok p {
            margin: 0 0 6px;
            font-size: 13px;
            color: #4b5563;
        }

        .info-blok p:last-child {
            margin-bottom: 0;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 22px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, background-color 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #f6c343; color: #3f3200; }
        .btn-warning:hover { background: #ebb625; }
        .btn-secondary { background: #f3f6f9; color: #334155; border: 1px solid #d5dde7; }
        .btn-secondary:hover { background: #e8eef5; }

        .form-section {
            display: none;
            margin-top: 14px;
            padding: 16px;
            background: #fffdf6;
            border: 1px solid #fde8a8;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 700;
            color: #4b5563;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
        }

        textarea.form-control {
            min-height: 96px;
            resize: vertical;
        }

        .status-badge {
            display: block;
            margin-top: 18px;
            padding: 11px 14px;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
        }

        .badge-blue { background: #e6f2ff; color: #004085; border: 1px solid #b8daff; }
        .badge-orange { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 14px 0;
        }

        .checkbox-group input {
            width: 16px;
            height: 16px;
            margin-top: 2px;
        }

        .checkbox-group label {
            font-size: 12px;
            line-height: 1.5;
            color: #4b5563;
        }

        .muted {
            color: var(--muted);
        }

        @media (max-width: 1180px) {
            .split-layout {
                flex-direction: column;
            }

            .preview-side {
                border-right: none;
                border-bottom: 1px solid #dde7f0;
            }

            .action-side {
                flex-basis: auto;
            }
        }

        @media (max-width: 860px) {
            body {
                padding: 16px;
            }

            .preview-side,
            .action-side,
            .offer-page {
                padding: 18px;
            }

            .offer-top,
            .route-day-head,
            .address-grid {
                display: block;
            }

            .meta-card {
                margin-top: 18px;
                min-width: 0;
            }

            .trip-grid,
            .price-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <div class="split-layout">
        <div class="preview-side">
            <div class="offer-page">
                <div class="offer-top">
                    <div class="brand-block">
                        <?php if (!empty($view['company']['logo_web_src'])): ?>
                            <img src="<?php echo h($view['company']['logo_web_src']); ?>" alt="<?php echo h($view['company']['name']); ?>" class="brand-logo">
                        <?php endif; ?>
                        <div class="brand-name" style="<?php echo !empty($view['company']['logo_web_src']) ? 'font-size:22px;' : ''; ?>"><?php echo h($view['company']['name']); ?></div>
                        <div class="brand-lines">
                            <?php if (!empty($view['company']['address'])): ?><div><?php echo h($view['company']['address']); ?></div><?php endif; ?>
                            <?php if (!empty($view['company']['postcode']) || !empty($view['company']['city'])): ?><div><?php echo h(trim($view['company']['postcode'] . ' ' . $view['company']['city'])); ?></div><?php endif; ?>
                            <?php if (!empty($view['company']['phone'])): ?><div>T: <?php echo h($view['company']['phone']); ?></div><?php endif; ?>
                            <?php if (!empty($view['company']['email'])): ?><div>E: <?php echo h($view['company']['email']); ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="meta-card">
                        <div class="meta-row"><span>Offertenummer</span><strong>#<?php echo h($view['offer']['order_nummer']); ?></strong></div>
                        <div class="meta-row"><span>Offertedatum</span><strong><?php echo h($view['offer']['date_display']); ?></strong></div>
                        <div class="meta-row"><span>Vervaldatum</span><strong><?php echo h($view['offer']['expiry_date_display']); ?></strong></div>
                    </div>
                </div>

                <div class="section-box">
                    <div class="box-header"><h2 class="box-title">Klantgegevens</h2></div>
                    <div class="box-body address-grid">
                        <div class="mini-card">
                            <span class="mini-label">Klant</span>
                            <div class="mini-value">
                                <?php echo h($view['customer']['display_name']); ?>
                                <?php if (!empty($view['customer']['company_name']) && !empty($view['customer']['contact_name'])): ?>
                                    <br><span class="muted">t.a.v. <?php echo h($view['customer']['contact_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mini-card">
                            <span class="mini-label">Adres</span>
                            <div class="mini-value">
                                <?php if (!empty($view['customer']['address'])): ?><div><?php echo h($view['customer']['address']); ?></div><?php endif; ?>
                                <?php if (!empty($view['customer']['postcode_city'])): ?><div><?php echo h($view['customer']['postcode_city']); ?></div><?php endif; ?>
                                <?php if (empty($view['customer']['address']) && empty($view['customer']['postcode_city'])): ?><span class="muted">Niet ingevuld</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-box">
                    <div class="box-header"><h2 class="box-title">Aanhef</h2></div>
                    <div class="box-body">
                        <div class="intro-text">
                            <p style="margin-top:0;"><strong><?php echo h($view['salutation']); ?></strong></p>
                            <p style="margin-bottom:0;"><?php echo h($view['intro']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="section-box">
                    <div class="box-header"><h2 class="box-title">Ritgegevens</h2></div>
                    <div class="box-body trip-grid">
                        <div class="trip-card">
                            <span class="mini-label">Soort reis</span>
                            <div class="mini-value"><?php echo h($view['trip']['rittype_label']); ?></div>
                        </div>
                        <div class="trip-card">
                            <span class="mini-label">Passagiers</span>
                            <div class="mini-value"><?php echo h((string) $view['trip']['passagiers']); ?></div>
                        </div>
                        <div class="trip-card">
                            <span class="mini-label">Vertrekdatum</span>
                            <div class="mini-value"><?php echo h($view['trip']['start_date_display']); ?></div>
                        </div>
                        <div class="trip-card">
                            <span class="mini-label">Einddatum</span>
                            <div class="mini-value"><?php echo h($view['trip']['end_date_display']); ?></div>
                        </div>
                        <?php if (!empty($view['trip']['pakket_losse_rijdagen'])): ?>
                        <div class="trip-card" style="grid-column: 1 / -1;">
                            <span class="mini-label">Meerdere losse rijdagen</span>
                            <div class="mini-value">Ja: één offerte; route per dag hieronder.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-box">
                    <div class="box-header"><h2 class="box-title">Routeplanning</h2></div>
                    <div class="box-body">
                        <?php if ($view['route_days'] === []): ?>
                            <div class="muted">Er zijn nog geen routegegevens beschikbaar.</div>
                        <?php else: ?>
                            <?php foreach ($view['route_days'] as $day): ?>
                                <div class="route-day">
                                    <div class="route-day-head">
                                        <div class="route-day-title"><?php echo h($day['heading_label'] ?? $day['label']); ?></div>
                                        <div class="route-day-date"><?php echo h($day['date_display']); ?></div>
                                    </div>

                                    <?php foreach ($day['routes'] as $route): ?>
                                        <div class="route-block">
                                            <?php if (empty($route['inline_with_day_heading'])): ?>
                                                <h3 class="route-block-title"><?php echo h($route['label']); ?></h3>
                                            <?php endif; ?>

                                            <?php if ($route['table_type'] === 'legacy_route'): ?>
                                                <table class="route-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="route-col-time">Tijd</th>
                                                            <th>Locatie</th>
                                                            <?php if ($route['show_zone']): ?><th>Zone</th><?php endif; ?>
                                                            <th class="route-col-km">Km</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($route['rows'] as $row): ?>
                                                            <tr>
                                                                <td class="route-col-time"><?php echo h($row['time_display']); ?></td>
                                                                <td><?php echo h($row['location']); ?></td>
                                                                <?php if ($route['show_zone']): ?><td><?php echo h($row['zone_display']); ?></td><?php endif; ?>
                                                                <td class="route-col-km"><?php echo h($row['km_display']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <table class="route-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="route-col-time">Vertrek</th>
                                                            <th>Van</th>
                                                            <th>Naar</th>
                                                            <th class="route-col-time">Aankomst</th>
                                                            <?php if ($route['show_zone']): ?><th>Zone</th><?php endif; ?>
                                                            <th class="route-col-km">Km</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($route['rows'] as $row): ?>
                                                            <tr>
                                                                <td class="route-col-time"><?php echo h($row['depart_display']); ?></td>
                                                                <td><?php echo h($row['from']); ?></td>
                                                                <td><?php echo h($row['to']); ?></td>
                                                                <td class="route-col-time"><?php echo h($row['arrive_display']); ?></td>
                                                                <?php if ($route['show_zone']): ?><td><?php echo h($row['zone_display']); ?></td><?php endif; ?>
                                                                <td class="route-col-km"><?php echo h($row['km_display']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($day['events'] !== []): ?>
                                        <div class="events-wrap">
                                            <h3 class="route-block-title">Dagactiviteiten</h3>
                                            <table class="route-table">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Datum</th>
                                                        <th class="route-col-time">Tijd</th>
                                                        <th>Van</th>
                                                        <th>Naar</th>
                                                        <?php if ($day['show_zone']): ?><th>Zone</th><?php endif; ?>
                                                        <th class="route-col-km">Km</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($day['events'] as $row): ?>
                                                        <tr>
                                                            <td><?php echo h($row['label']); ?></td>
                                                            <td><?php echo h($row['date_display']); ?></td>
                                                            <td class="route-col-time"><?php echo h($row['time_display']); ?></td>
                                                            <td><?php echo h($row['from']); ?></td>
                                                            <td><?php echo h($row['to']); ?></td>
                                                            <?php if ($day['show_zone']): ?><td><?php echo h($row['zone_display']); ?></td><?php endif; ?>
                                                            <td class="route-col-km"><?php echo h($row['km_display']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($view['notes'])): ?>
                    <div class="section-box">
                        <div class="box-header"><h2 class="box-title">Bijzonderheden</h2></div>
                        <div class="box-body">
                            <div class="notes-text"><?php echo nl2br(h($view['notes'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-box">
                    <div class="box-header"><h2 class="box-title">Prijs</h2></div>
                    <div class="box-body price-grid">
                        <div class="price-card">
                            <span class="mini-label">Excl. btw</span>
                            <div class="price-value" style="font-size:22px;"><?php echo h($view['price']['excl_display']); ?></div>
                        </div>
                        <div class="price-card">
                            <span class="mini-label">BTW-bedrag</span>
                            <div class="price-value" style="font-size:22px;"><?php echo h($view['price']['btw_display']); ?></div>
                        </div>
                        <div class="price-card primary">
                            <span class="mini-label">Totaal incl. btw</span>
                            <div class="price-value"><?php echo h($view['price']['incl_display']); ?></div>
                        </div>
                    </div>
                    <div class="box-body" style="padding-top:0;">
                        <div class="closing-text">
                            <p style="margin:0 0 12px;">Wij vertrouwen erop u hiermee een passende aanbieding te hebben gedaan en zien uw reactie graag tegemoet.</p>
                            <p style="margin:0;">Met vriendelijke groet,<br><strong><?php echo h($view['company']['name']); ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-side">
            <?php if ($rit['status'] === 'klant_akkoord' || $actie_uitgevoerd === 'klant_akkoord' || $rit['status'] === 'geaccepteerd'): ?>
                <div>
                    <h2 style="color:#003366; font-size:22px; margin-top:0;">Bedankt voor uw akkoord</h2>
                    <p class="muted" style="font-size:13px; line-height:1.6;">Wij hebben uw gegevens in goede orde ontvangen. Ons kantoor controleert nu de actuele beschikbaarheid en stuurt daarna de definitieve bevestiging.</p>
                    <div class="status-badge badge-blue">Wachten op bevestiging</div>
                    <a href="<?php echo h($pdfOfferteUrl); ?>" target="_blank" class="btn btn-secondary" style="margin-top:15px;">Print / Download PDF</a>
                </div>
            <?php elseif ($rit['status'] === 'wijziging_verzocht' || $actie_uitgevoerd === 'wijziging'): ?>
                <div>
                    <h2 style="color:#856404; font-size:22px; margin-top:0;">Wijziging doorgegeven</h2>
                    <p class="muted" style="font-size:13px; line-height:1.6;">Wij hebben uw opmerking ontvangen. Ons kantoor kijkt dit zo snel mogelijk na en stuurt u een aangepaste offerte.</p>
                    <div class="status-badge badge-orange">In behandeling</div>
                    <a href="<?php echo h($pdfOfferteUrl); ?>" target="_blank" class="btn btn-secondary" style="margin-top:15px;">Print / Download PDF</a>
                </div>
            <?php else: ?>
                <div id="intro-text">
                    <h2 style="margin-top:0; color:#003366; font-size:24px;">Uw offerte</h2>
                    <div class="info-blok">
                        <p>Klant: <strong><?php echo h($view['customer']['display_name']); ?></strong></p>
                        <p>Kenmerk: <strong>#<?php echo h($view['offer']['order_nummer']); ?></strong></p>
                    </div>
                    <p class="muted" style="font-size:13px; line-height:1.7;">Links ziet u dezelfde offerte-opbouw als op het document zelf: klantkop, routeplanning en prijs. Controleer deze gegevens rustig.</p>
                    <p class="muted" style="font-size:13px; line-height:1.7;"><strong>Alles naar wens?</strong><br>Geef dan uw akkoord door, of stuur ons een wijziging.</p>
                </div>

                <div id="action-buttons">
                    <div class="btn-group">
                        <button type="button" class="btn btn-success" onclick="toonFormulier('form-akkoord')">Ik ga akkoord met de offerte</button>
                        <button type="button" class="btn btn-warning" onclick="toonFormulier('form-wijziging')">Ik wil iets aanpassen</button>
                        <a href="<?php echo h($pdfOfferteUrl); ?>" target="_blank" class="btn btn-secondary">Print / Download PDF</a>
                    </div>
                </div>

                <div id="form-akkoord" class="form-section">
                    <h3 style="margin-top:0; color:#155724; font-size:16px;">Gegevens bevestigen</h3>
                    <p class="muted" style="font-size:12px; margin-bottom:14px;">Na uw akkoord controleren wij de beschikbaarheid.</p>
                    <form method="POST">
                        <input type="hidden" name="offerte_token" value="<?php echo h($token); ?>">
                        <input type="hidden" name="actie" value="accepteren">
                        <div class="form-group">
                            <label>Definitief aantal personen (optioneel)</label>
                            <input type="number" name="definitieve_pax" class="form-control" value="<?php echo h((string) ($rit['passagiers'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Contactpersoon + 06-nummer (optioneel)</label>
                            <input type="text" name="contact_dag_zelf" class="form-control" placeholder="Bijv. Jan de Vries, 06-12345678">
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="voorwaarden" required>
                            <label for="voorwaarden">Ja, ik ga namens <strong><?php echo h($view['customer']['display_name']); ?></strong> akkoord met de offerte en voorwaarden.</label>
                        </div>
                        <button type="submit" class="btn btn-success">Doorsturen naar kantoor</button>
                        <button type="button" onclick="verbergFormulieren()" style="background:none; border:none; color:#64748b; text-decoration:underline; width:100%; margin-top:10px; font-size:12px; cursor:pointer;">Annuleren en terug</button>
                    </form>
                </div>

                <div id="form-wijziging" class="form-section">
                    <h3 style="margin-top:0; color:#856404; font-size:16px;">Wijziging aanvragen</h3>
                    <p class="muted" style="font-size:12px; margin-bottom:14px;">Wat wilt u aangepast zien aan deze offerte?</p>
                    <form method="POST">
                        <input type="hidden" name="offerte_token" value="<?php echo h($token); ?>">
                        <input type="hidden" name="actie" value="wijziging">
                        <div class="form-group">
                            <textarea name="klant_opmerking" class="form-control" placeholder="Typ hier uw wijzigingen..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning">Verstuur wijziging</button>
                        <button type="button" onclick="verbergFormulieren()" style="background:none; border:none; color:#64748b; text-decoration:underline; width:100%; margin-top:10px; font-size:12px; cursor:pointer;">Annuleren en terug</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$hideForms): ?>
<script>
function toonFormulier(id) {
    document.getElementById('action-buttons').style.display = 'none';
    document.getElementById('intro-text').style.display = 'none';
    document.getElementById('form-akkoord').style.display = 'none';
    document.getElementById('form-wijziging').style.display = 'none';
    document.getElementById(id).style.display = 'block';
}

function verbergFormulieren() {
    document.getElementById('form-akkoord').style.display = 'none';
    document.getElementById('form-wijziging').style.display = 'none';
    document.getElementById('intro-text').style.display = 'block';
    document.getElementById('action-buttons').style.display = 'block';
}
</script>
<?php endif; ?>
</body>
</html>
