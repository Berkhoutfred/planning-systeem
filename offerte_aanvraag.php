<?php
/**
 * Publiek offerte-aanvraag: multi-step wizard (tenant via ?tenant=slug).
 * Geen beheer-login. Zet in .env: GOOGLE_MAPS_API_KEY=jouw_key
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/beheer/includes/db.php';

$offerteScript = (string) ($_SERVER['SCRIPT_NAME'] ?? '/offerte_aanvraag.php');
$mapsApiKey = trim((string) env_value('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY'));
$loadGoogleMaps = $mapsApiKey !== '' && $mapsApiKey !== 'YOUR_GOOGLE_MAPS_API_KEY';

function offerte_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function offerte_norm_time(string $t): string
{
    $t = trim($t);
    if ($t === '') {
        return '00:00:00';
    }
    if (preg_match('/^\d{2}:\d{2}$/', $t)) {
        return $t . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
        return $t;
    }

    return '00:00:00';
}

function offerte_csrf_token(): string
{
    if (empty($_SESSION['offerte_csrf'])) {
        $_SESSION['offerte_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['offerte_csrf'];
}

/** @return array{id:int,slug:string,naam:string}|null */
function offerte_resolve_tenant(PDO $pdo): ?array
{
    $slug = trim((string) ($_GET['tenant'] ?? ''));
    if ($slug === '' && function_exists('current_tenant_slug')) {
        $slug = trim(current_tenant_slug());
    }
    if ($slug === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, slug, naam FROM tenants WHERE slug = ? AND status = ? LIMIT 1');
    $stmt->execute([$slug, 'active']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? [
        'id' => (int) $row['id'],
        'slug' => (string) $row['slug'],
        'naam' => (string) $row['naam'],
    ] : null;
}

function offerte_str_truncate(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }

    return substr($s, 0, $max);
}

/**
 * Standaard garage voor calculatie-regels (zelfde idee als maken.php).
 * Volgorde: .env DEFAULT_GARAGE_ADRES, anders laatste niet-lege t_garage van deze tenant.
 */
function offerte_default_garage_adres(PDO $pdo, int $tenantId): string
{
    $fromEnv = trim((string) env_value('DEFAULT_GARAGE_ADRES', ''));
    if ($fromEnv !== '') {
        return offerte_str_truncate($fromEnv, 255);
    }

    $stmt = $pdo->prepare(
        'SELECT cr.adres FROM calculatie_regels cr
         INNER JOIN calculaties c ON c.id = cr.calculatie_id AND c.tenant_id = cr.tenant_id
         WHERE cr.tenant_id = ? AND cr.type = ? AND LENGTH(TRIM(cr.adres)) > 2
         ORDER BY cr.id DESC LIMIT 1'
    );
    $stmt->execute([$tenantId, 't_garage']);
    $row = $stmt->fetchColumn();
    if ($row !== false && is_string($row)) {
        $t = trim($row);
        if ($t !== '') {
            return offerte_str_truncate($t, 255);
        }
    }

    return '';
}

$tenant = offerte_resolve_tenant($pdo);
$bedankt = isset($_GET['bedankt']) && (string) $_GET['bedankt'] === '1';
$foutmelding = '';

$uiBuildConf = require __DIR__ . '/beheer/calculatie/includes/ui_build.php';
$uiBuildTag = trim((string) ($uiBuildConf['date'] ?? ''));
if ($uiBuildTag === '') {
    $uiBuildTag = trim((string) ($uiBuildConf['time'] ?? ''));
}
$offerteWizardBuildLabel = 'nr. ' . (int) ($uiBuildConf['nr'] ?? 1) . ($uiBuildTag !== '' ? ' · ' . htmlspecialchars($uiBuildTag, ENT_QUOTES, 'UTF-8') : '');

if ($tenant === null) {
    $foutmelding = 'Deze pagina is niet bereikbaar zonder geldige organisatie. Gebruik de link van uw vervoerder (met ?tenant=...) of neem contact op.';
}

if ($tenant !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(offerte_csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        $foutmelding = 'Uw sessie is verlopen. Vernieuw de pagina en probeer opnieuw.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        header('Location: ' . $offerteScript . '?tenant=' . urlencode($tenant['slug']) . '&bedankt=1');
        exit;
    } else {
        $ritType = (string) ($_POST['rit_type'] ?? '');
        if (!in_array($ritType, ['enkel', 'brenghaal', 'dagtocht'], true)) {
            $foutmelding = 'Kies een geldig type rit.';
        } else {
            $vertrek = trim((string) ($_POST['vertrek_adres'] ?? ''));
            $bestemming = trim((string) ($_POST['bestemming_adres'] ?? ''));
            $ritDatum = trim((string) ($_POST['rit_datum'] ?? ''));
            $ritTijd = trim((string) ($_POST['rit_tijd'] ?? ''));
            $retourDatum = trim((string) ($_POST['retour_datum'] ?? ''));
            $tijdVertrekBest = trim((string) ($_POST['tijd_vertrek_bestemming'] ?? ''));
            $personen = (int) ($_POST['aantal_personen'] ?? 0);
            $bijz = trim((string) ($_POST['bijzonderheden'] ?? ''));
            $bedrijf = trim((string) ($_POST['bedrijfsnaam'] ?? ''));
            $voornaam = trim((string) ($_POST['voornaam'] ?? ''));
            $achternaam = trim((string) ($_POST['achternaam'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $telefoon = trim((string) ($_POST['telefoon'] ?? ''));

            $needsRetour = in_array($ritType, ['brenghaal', 'dagtocht'], true);

            if ($vertrek === '' || $bestemming === '') {
                $foutmelding = 'Vul vertrek- en bestemmingsadres in.';
            } elseif ($ritDatum === '' || strtotime($ritDatum) === false) {
                $foutmelding = 'Kies een geldige vertrekdatum.';
            } elseif ($needsRetour && ($retourDatum === '' || strtotime($retourDatum) === false)) {
                $foutmelding = 'Kies een geldige retourdatum.';
            } elseif ($needsRetour && strtotime($retourDatum) !== false && strtotime($retourDatum) < strtotime($ritDatum)) {
                $foutmelding = 'De retourdatum moet op of na de vertrekdatum liggen.';
            } elseif ($needsRetour && $tijdVertrekBest === '') {
                $foutmelding = 'Vul de vertrektijd vanaf de bestemming in (retour).';
            } elseif ($personen < 1) {
                $foutmelding = 'Het aantal personen moet minimaal 1 zijn.';
            } elseif ($voornaam === '' || $achternaam === '') {
                $foutmelding = 'Vul voornaam en achternaam in.';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $foutmelding = 'Vul een geldig e-mailadres in.';
            } else {
                $ritDatumSql = date('Y-m-d', strtotime($ritDatum));
                $ritDatumEind = $needsRetour ? date('Y-m-d', strtotime($retourDatum)) : $ritDatumSql;
                $tijdHeen = offerte_norm_time($ritTijd);
                $tijdRetourBest = $needsRetour ? offerte_norm_time($tijdVertrekBest) : '00:00:00';

                try {
                    $vertrekDatumSql = (new DateTimeImmutable($ritDatumSql . ' ' . $tijdHeen))->format('Y-m-d H:i:s');
                } catch (Throwable $e) {
                    $vertrekDatumSql = $ritDatumSql . ' 00:00:00';
                }

                $vertrekLoc = offerte_str_truncate($vertrek, 255);
                $bestemmingStr = offerte_str_truncate($bestemming, 255);
                $naamTitel = trim($voornaam . ' ' . $achternaam);
                $titel = offerte_str_truncate(
                    'Aanvraag – ' . ($bedrijf !== '' ? $bedrijf . ' – ' : '') . $naamTitel . ' (' . $ritDatumSql . ')',
                    150
                );

                // instructie_kantoor = alleen wat de klant invult onder "Bijzonderheden" (staat op PDF naar klant).
                // Route, data, personen staan al in calculatie-velden + titel; geen interne dump meer.
                $instructieKantoor = $bijz !== '' ? offerte_str_truncate($bijz, 8000) : null;
                $klantOpmerking = null;

                $tenantId = $tenant['id'];

                try {
                    $pdo->beginTransaction();

                    $stmtBestaand = $pdo->prepare('SELECT id FROM klanten WHERE tenant_id = ? AND LOWER(TRIM(email)) = ? LIMIT 1');
                    $stmtBestaand->execute([$tenantId, $email]);
                    $klantId = (int) $stmtBestaand->fetchColumn();

                    if ($klantId <= 0) {
                        $stmtInsK = $pdo->prepare('
                            INSERT INTO klanten (
                                tenant_id, bedrijfsnaam, voornaam, achternaam, email, telefoon,
                                adres, postcode, plaats, notities, gearchiveerd, is_gecontroleerd
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
                        ');
                        $stmtInsK->execute([
                            $tenantId,
                            $bedrijf !== '' ? $bedrijf : null,
                            $voornaam,
                            $achternaam,
                            $email,
                            $telefoon !== '' ? $telefoon : null,
                            null,
                            null,
                            null,
                            'Aangemaakt via offerte-aanvraag wizard.',
                        ]);
                        $klantId = (int) $pdo->lastInsertId();
                    } else {
                        $pdo->prepare('
                            UPDATE klanten SET
                                bedrijfsnaam = COALESCE(NULLIF(?, \'\'), bedrijfsnaam),
                                voornaam = ?,
                                achternaam = ?,
                                telefoon = COALESCE(NULLIF(?, \'\'), telefoon)
                            WHERE id = ? AND tenant_id = ?
                        ')->execute([
                            $bedrijf,
                            $voornaam,
                            $achternaam,
                            $telefoon,
                            $klantId,
                            $tenantId,
                        ]);
                    }

                    if ($klantId <= 0) {
                        throw new RuntimeException('Klant kon niet worden opgeslagen.');
                    }

                    $token = '';
                    for ($i = 0; $i < 5; $i++) {
                        $token = bin2hex(random_bytes(32));
                        $chk = $pdo->prepare('SELECT id FROM calculaties WHERE token = ? LIMIT 1');
                        $chk->execute([$token]);
                        if (!$chk->fetchColumn()) {
                            break;
                        }
                        $token = '';
                    }
                    if ($token === '') {
                        throw new RuntimeException('Kon geen unieke token genereren.');
                    }

                    $garageAdres = offerte_default_garage_adres($pdo, $tenantId);

                    $stmtCalc = $pdo->prepare('
                        INSERT INTO calculaties (
                            tenant_id, token, titel, klant_id, contact_id, afdeling_id, rittype, passagiers,
                            rit_datum, rit_datum_eind,
                            vertrek_datum, vertrek_locatie, bestemming,
                            vertrek_adres, aankomst_adres, klant_opmerking,
                            voertuig_id, extra_voertuigen, totaal_km, totaal_uren, prijs,
                            km_tussen, km_nl, km_de, instructie_kantoor,
                            aangemaakt_op, status
                        ) VALUES (
                            ?, ?, ?, ?, 0, NULL, ?, ?,
                            ?, ?,
                            ?, ?, ?,
                            ?, ?, ?,
                            NULL, NULL, 0, 0, 0,
                            0, 0, 0, ?,
                            NOW(), ?
                        )
                    ');
                    $stmtCalc->execute([
                        $tenantId,
                        $token,
                        $titel,
                        $klantId,
                        $ritType,
                        $personen,
                        $ritDatumSql,
                        $ritDatumEind,
                        $vertrekDatumSql,
                        $vertrekLoc,
                        $bestemmingStr,
                        $vertrek,
                        $bestemming,
                        $klantOpmerking,
                        $instructieKantoor,
                        'aanvraag',
                    ]);

                    $calculatieId = (int) $pdo->lastInsertId();
                    if ($calculatieId <= 0) {
                        throw new RuntimeException('Calculatie kon niet worden aangemaakt.');
                    }

                    $stmtRegel = $pdo->prepare('
                        INSERT INTO calculatie_regels (tenant_id, calculatie_id, type, label, tijd, adres, km)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ');

                    $regelLabels = [
                        't_garage' => 'Vertrek Garage',
                        't_voorstaan' => 'Voorstaan',
                        't_vertrek_klant' => 'Vertrekadres',
                        't_aankomst_best' => 'Bestemming',
                        't_retour_garage_heen' => 'Retour garage (heen)',
                        't_garage_rit2' => 'Garage rit 2',
                        't_voorstaan_rit2' => 'Voorstaan rit 2',
                        't_vertrek_best' => 'Vertrek (Terug)',
                        't_retour_klant' => 'Retour Klant',
                        't_retour_garage' => 'Terug in Garage',
                    ];

                    $insR = static function (int $tenantId, int $calculatieId, string $type, string $label, string $tijd, string $adres) use ($stmtRegel): void {
                        $stmtRegel->execute([$tenantId, $calculatieId, $type, $label, $tijd, $adres]);
                    };

                    if ($garageAdres !== '') {
                        $insR($tenantId, $calculatieId, 't_garage', $regelLabels['t_garage'], '', $garageAdres);
                    }
                    // Voorrijden (t_voorstaan): zelfde ophaallocatie; rekenmachine verdeelt km garage→voorstaan→klant
                    $insR($tenantId, $calculatieId, 't_voorstaan', $regelLabels['t_voorstaan'], '', $vertrek);

                    $insR($tenantId, $calculatieId, 't_vertrek_klant', $regelLabels['t_vertrek_klant'], $tijdHeen, $vertrek);
                    $insR($tenantId, $calculatieId, 't_aankomst_best', $regelLabels['t_aankomst_best'], $tijdHeen, $bestemming);

                    if ($ritType === 'enkel' && $garageAdres !== '') {
                        $insR($tenantId, $calculatieId, 't_retour_garage_heen', $regelLabels['t_retour_garage_heen'], '', $garageAdres);
                    }

                    if ($ritType === 'dagtocht') {
                        $insR($tenantId, $calculatieId, 't_vertrek_best', $regelLabels['t_vertrek_best'], $tijdRetourBest, $bestemming);
                        $insR($tenantId, $calculatieId, 't_retour_klant', $regelLabels['t_retour_klant'], $tijdRetourBest, $vertrek);
                        if ($garageAdres !== '') {
                            $insR($tenantId, $calculatieId, 't_retour_garage', $regelLabels['t_retour_garage'], '', $garageAdres);
                        }
                    }

                    if ($ritType === 'brenghaal') {
                        if ($garageAdres !== '') {
                            $insR($tenantId, $calculatieId, 't_retour_garage_heen', $regelLabels['t_retour_garage_heen'], '', $garageAdres);
                            $insR($tenantId, $calculatieId, 't_garage_rit2', $regelLabels['t_garage_rit2'], '', $garageAdres);
                            $insR($tenantId, $calculatieId, 't_voorstaan_rit2', $regelLabels['t_voorstaan_rit2'], '', $vertrek);
                            $insR($tenantId, $calculatieId, 't_retour_garage', $regelLabels['t_retour_garage'], '', $garageAdres);
                        }
                        $insR($tenantId, $calculatieId, 't_vertrek_best', $regelLabels['t_vertrek_best'], $tijdRetourBest, $bestemming);
                        $insR($tenantId, $calculatieId, 't_retour_klant', $regelLabels['t_retour_klant'], $tijdRetourBest, $vertrek);
                    }

                    $pdo->commit();
                    unset($_SESSION['offerte_csrf']);
                    header('Location: ' . $offerteScript . '?tenant=' . urlencode($tenant['slug']) . '&bedankt=1');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $foutmelding = 'Opslaan is mislukt. Probeer het later opnieuw of neem telefonisch contact op.';
                }
            }
        }
    }
}

$csrf = offerte_csrf_token();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offerte aanvragen<?php echo $tenant ? ' – ' . offerte_h($tenant['naam']) : ''; ?></title>
    <style>
        :root {
            --bg0: #0f172a;
            --bg1: #1e293b;
            --card: #ffffff;
            --muted: #64748b;
            --txt: #0f172a;
            --line: #e2e8f0;
            --primary: #2563eb;
            --primary-d: #1d4ed8;
            --accent: #0ea5e9;
            --ok-bg: #ecfdf5;
            --ok-bd: #6ee7b7;
            --ok-tx: #065f46;
            --err-bg: #fef2f2;
            --err-bd: #fecaca;
            --err-tx: #b91c1c;
            --radius: 14px;
            --shadow: 0 22px 50px -12px rgba(15, 23, 42, 0.22);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(165deg, var(--bg0) 0%, var(--bg1) 45%, #f1f5f9 45%, #f8fafc 100%);
            color: var(--txt);
        }
        .shell { max-width: 640px; margin: 0 auto; padding: 28px 18px 56px; }
        .brand {
            text-align: center;
            margin-bottom: 22px;
            color: #f8fafc;
        }
        .brand h1 { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; }
        .brand p { margin: 8px 0 0; font-size: 0.9rem; opacity: 0.88; }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
        }
        .card-inner { padding: 26px 22px 28px; }
        .progress {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 26px;
            padding: 0 4px;
        }
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .progress-step::after {
            content: "";
            position: absolute;
            top: 14px;
            left: 50%;
            width: 100%;
            height: 3px;
            background: var(--line);
            z-index: 0;
        }
        .progress-step:last-child::after { display: none; }
        .progress-dot {
            position: relative;
            z-index: 1;
            width: 30px;
            height: 30px;
            margin: 0 auto 6px;
            border-radius: 50%;
            background: var(--line);
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 800;
            display: grid;
            place-items: center;
            transition: 0.25s ease;
        }
        .progress-step.active .progress-dot {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.25);
        }
        .progress-step.done .progress-dot {
            background: #10b981;
            color: #fff;
        }
        .progress-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .progress-step.active .progress-label { color: var(--primary-d); }

        .step-panel { display: none; animation: fadeIn 0.28s ease; }
        .step-panel.is-active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .step-title { font-size: 1.05rem; font-weight: 800; margin: 0 0 4px; color: #0f172a; }
        .step-desc { font-size: 0.85rem; color: var(--muted); margin: 0 0 18px; line-height: 1.45; }

        .type-grid { display: grid; gap: 12px; }
        @media (min-width: 520px) { .type-grid { grid-template-columns: repeat(3, 1fr); } }

        .type-card {
            border: 2px solid var(--line);
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s ease;
            background: #fafafa;
        }
        .type-card:hover { border-color: #93c5fd; background: #f8fafc; }
        .type-card.selected {
            border-color: var(--primary);
            background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.12);
        }
        .type-card .ic { font-size: 1.6rem; margin-bottom: 6px; }
        .type-card strong { display: block; font-size: 0.82rem; color: #1e293b; }
        .type-card span { font-size: 0.72rem; color: var(--muted); display: block; margin-top: 4px; line-height: 1.35; }

        label { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; margin: 14px 0 6px; }
        input, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.95rem;
            transition: border 0.15s, box-shadow 0.15s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        textarea { min-height: 92px; resize: vertical; }
        .inp-date-lg, .inp-time-lg {
            min-height: 48px;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .time-pill-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin: 10px 0 14px;
        }
        .time-pill-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-right: 4px;
        }
        .time-pill {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 7px 11px;
            border-radius: 999px;
            cursor: pointer;
            transition: 0.15s ease;
        }
        .time-pill:hover { border-color: var(--primary); color: var(--primary-d); background: #eff6ff; }
        .datetime-callout {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 16px;
            margin-bottom: 18px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f0f9ff 0%, #f8fafc 55%);
            border: 1px solid #bae6fd;
            box-shadow: 0 1px 0 rgba(255,255,255,0.8) inset;
        }
        .datetime-callout--sec {
            background: linear-gradient(135deg, #fefce8 0%, #fffbeb 50%);
            border-color: #fde68a;
            margin-bottom: 0;
        }
        .datetime-callout-ic {
            font-size: 1.35rem;
            line-height: 1;
            opacity: 0.85;
        }
        .datetime-callout-k {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0369a1;
            margin-bottom: 4px;
        }
        .datetime-callout--sec .datetime-callout-k { color: #a16207; }
        .datetime-callout-v {
            font-size: 1.02rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }
        .pax-field { margin-top: 2px; }
        .pax-main-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            margin: 14px 0 10px;
        }
        .bus-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: 1fr 1fr;
            margin-bottom: 14px;
        }
        @media (min-width: 520px) {
            .bus-grid { grid-template-columns: repeat(4, 1fr); }
        }
        .bus-card {
            border: 2px solid var(--line);
            border-radius: 12px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            background: #fafafa;
            transition: 0.18s ease;
            font: inherit;
            color: inherit;
        }
        .bus-card:hover { border-color: #93c5fd; background: #f8fafc; }
        .bus-card.selected {
            border-color: var(--primary);
            background: linear-gradient(180deg, #eff6ff 0%, #fff 100%);
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.12);
        }
        .bus-card-cap {
            display: block;
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary-d);
            line-height: 1.1;
        }
        .bus-card-t { display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; margin-top: 4px; }
        .bus-card-s { display: block; font-size: 0.68rem; color: var(--muted); margin-top: 2px; line-height: 1.3; }
        .bus-card--custom .bus-card-cap { font-size: 1.35rem; letter-spacing: 0.05em; }
        .pax-custom-wrap { margin-top: 6px; }
        .pax-custom-wrap[hidden] { display: none !important; }
        .pax-stepper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .pax-stepper .pax-sbtn {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            border: 2px solid var(--line);
            background: #fff;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-d);
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }
        .pax-stepper .pax-sbtn:hover { border-color: var(--primary); background: #eff6ff; }
        .pax-stepper #aantal_personen_ui {
            width: 5rem;
            max-width: 100%;
            text-align: center;
            font-weight: 800;
            font-size: 1.05rem;
            padding: 10px 6px;
        }
        .pax-hint { font-size: 0.78rem; color: var(--muted); margin: 10px 0 0; line-height: 1.45; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 480px) { .row2 { grid-template-columns: 1fr; } }

        .retour-extra { display: none; }
        .retour-extra.is-on { display: block; }

        .nav-row { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
        .btn {
            flex: 1;
            min-width: 120px;
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            transition: 0.18s;
        }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-primary { background: linear-gradient(180deg, var(--primary) 0%, var(--primary-d) 100%); color: #fff; }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-primary:disabled { opacity: 0.55; cursor: not-allowed; filter: none; }

        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

        .err {
            background: var(--err-bg);
            border: 1px solid var(--err-bd);
            color: var(--err-tx);
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 16px;
        }
        .thanks-hero {
            text-align: center;
            padding: 12px 8px 8px;
        }
        .thanks-hero .big {
            width: 64px; height: 64px; margin: 0 auto 14px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: grid; place-items: center;
            font-size: 1.8rem;
            color: #fff;
            box-shadow: 0 10px 28px rgba(16, 185, 129, 0.35);
        }
        .thanks-hero h2 { margin: 0; font-size: 1.35rem; font-weight: 800; }
        .thanks-hero p { margin: 10px 0 0; color: var(--muted); font-size: 0.92rem; line-height: 1.5; }
        .err-page .card-inner { padding: 28px 22px; }
        .offerte-build-label {
            text-align: center;
            font-size: 0.68rem;
            color: var(--muted);
            margin: 22px 8px 0;
            opacity: 0.88;
            letter-spacing: 0.02em;
        }
    </style>
    <?php if ($tenant !== null && !$bedankt && $loadGoogleMaps): ?>
    <script defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo offerte_h($mapsApiKey); ?>&libraries=places&callback=offerteInitMaps"></script>
    <?php endif; ?>
</head>
<body>
<div class="shell">
    <?php if ($tenant === null): ?>
        <div class="brand"><h1>Offerte aanvragen</h1></div>
        <div class="card err-page"><div class="card-inner"><p class="err" style="margin:0;"><?php echo offerte_h($foutmelding); ?></p></div></div>
    <?php elseif ($bedankt): ?>
        <div class="brand"><h1><?php echo offerte_h($tenant['naam']); ?></h1><p>Offerte-aanvraag</p></div>
        <div class="card">
            <div class="card-inner thanks-hero">
                <div class="big">✓</div>
                <h2>Bedankt voor uw aanvraag</h2>
                <p>We hebben uw gegevens ontvangen. Een medewerker neemt zo snel mogelijk contact met u op.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="brand">
            <h1>Offerte aanvragen</h1>
            <p><?php echo offerte_h($tenant['naam']); ?></p>
        </div>
        <div class="card">
            <div class="card-inner">
                <div class="progress" id="progress-bar" aria-hidden="true">
                    <?php for ($s = 1; $s <= 4; $s++): ?>
                    <div class="progress-step<?php echo $s === 1 ? ' active' : ''; ?>" data-progress="<?php echo $s; ?>">
                        <div class="progress-dot"><?php echo $s; ?></div>
                        <div class="progress-label"><?php
                            echo ['Type', 'Route', 'Gezelschap', 'Contact'][$s - 1];
                        ?></div>
                    </div>
                    <?php endfor; ?>
                </div>

                <?php if ($foutmelding !== ''): ?>
                    <div class="err"><?php echo offerte_h($foutmelding); ?></div>
                <?php endif; ?>

                <form id="wizard-form" method="post" action="<?php echo offerte_h($offerteScript); ?>?tenant=<?php echo offerte_h($tenant['slug']); ?>" novalidate>
                    <input type="hidden" name="csrf" value="<?php echo offerte_h($csrf); ?>">
                    <input type="hidden" name="rit_type" id="rit_type" value="enkel">
                    <div class="hp" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <section class="step-panel is-active" data-step="1">
                        <h2 class="step-title">Welke rit wilt u plannen?</h2>
                        <p class="step-desc">Kies het type rit. U kunt later nog details aanpassen met onze planner.</p>
                        <div class="type-grid" role="radiogroup" aria-label="Type rit">
                            <div class="type-card selected" data-rit="enkel" role="radio" aria-checked="true" tabindex="0">
                                <div class="ic">→</div>
                                <strong>Enkele rit</strong>
                                <span>Van A naar B, één richting</span>
                            </div>
                            <div class="type-card" data-rit="brenghaal" role="radio" aria-checked="false" tabindex="0">
                                <div class="ic">⇄</div>
                                <strong>Breng en haal</strong>
                                <span>Heen en terug (retour)</span>
                            </div>
                            <div class="type-card" data-rit="dagtocht" role="radio" aria-checked="false" tabindex="0">
                                <div class="ic">☀</div>
                                <strong>Dagtocht</strong>
                                <span>Langere geplande dag</span>
                            </div>
                        </div>
                    </section>

                    <section class="step-panel" data-step="2">
                        <h2 class="step-title">Route &amp; tijden</h2>
                        <p class="step-desc">Vul de adressen in. Zodra de kaart geladen is, krijgt u adresvoorstellen.</p>
                        <label for="vertrek_adres">Vertrekadres *</label>
                        <input type="text" name="vertrek_adres" id="vertrek_adres" maxlength="500" autocomplete="street-address">

                        <div class="row2">
                            <div>
                                <label for="rit_datum">Vertrekdatum *</label>
                                <input type="date" name="rit_datum" id="rit_datum" class="inp-date-lg">
                            </div>
                            <div>
                                <label for="rit_tijd">Vertrektijd *</label>
                                <input type="time" name="rit_tijd" id="rit_tijd" class="inp-time-lg" step="300">
                            </div>
                        </div>
                        <div class="time-pill-row" data-time-target="rit_tijd" aria-label="Veel gekozen vertrektijden">
                            <span class="time-pill-label">Snel:</span>
                            <button type="button" class="time-pill" data-time="07:00">07:00</button>
                            <button type="button" class="time-pill" data-time="08:00">08:00</button>
                            <button type="button" class="time-pill" data-time="08:30">08:30</button>
                            <button type="button" class="time-pill" data-time="09:00">09:00</button>
                            <button type="button" class="time-pill" data-time="10:00">10:00</button>
                            <button type="button" class="time-pill" data-time="12:00">12:00</button>
                            <button type="button" class="time-pill" data-time="14:00">14:00</button>
                            <button type="button" class="time-pill" data-time="16:00">16:00</button>
                            <button type="button" class="time-pill" data-time="17:00">17:00</button>
                        </div>
                        <div class="datetime-callout" id="callout-heen" aria-live="polite">
                            <span class="datetime-callout-ic" aria-hidden="true">◷</span>
                            <div>
                                <div class="datetime-callout-k">Vertrek (heen)</div>
                                <div class="datetime-callout-v" id="leesbaar-heen">Kies datum en tijd</div>
                            </div>
                        </div>

                        <label for="bestemming_adres">Bestemmingsadres *</label>
                        <input type="text" name="bestemming_adres" id="bestemming_adres" maxlength="500" autocomplete="off">

                        <div class="retour-extra" id="retour-extra">
                            <div class="row2">
                                <div>
                                    <label for="retour_datum">Retourdatum *</label>
                                    <input type="date" name="retour_datum" id="retour_datum" class="inp-date-lg">
                                </div>
                                <div>
                                    <label for="tijd_vertrek_bestemming">Vertrek vanaf bestemming *</label>
                                    <input type="time" name="tijd_vertrek_bestemming" id="tijd_vertrek_bestemming" class="inp-time-lg" step="300">
                                </div>
                            </div>
                            <div class="time-pill-row" data-time-target="tijd_vertrek_bestemming" aria-label="Veel gekozen retourtijden">
                                <span class="time-pill-label">Snel:</span>
                                <button type="button" class="time-pill" data-time="14:00">14:00</button>
                                <button type="button" class="time-pill" data-time="15:00">15:00</button>
                                <button type="button" class="time-pill" data-time="16:00">16:00</button>
                                <button type="button" class="time-pill" data-time="17:00">17:00</button>
                                <button type="button" class="time-pill" data-time="18:00">18:00</button>
                                <button type="button" class="time-pill" data-time="19:00">19:00</button>
                                <button type="button" class="time-pill" data-time="20:00">20:00</button>
                            </div>
                            <div class="datetime-callout datetime-callout--sec" id="callout-retour" aria-live="polite">
                                <span class="datetime-callout-ic" aria-hidden="true">↩</span>
                                <div>
                                    <div class="datetime-callout-k">Vertrek retour (vanaf bestemming)</div>
                                    <div class="datetime-callout-v" id="leesbaar-retour">Kies retourdatum en tijd</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="step-panel" data-step="3">
                        <h2 class="step-title">Gezelschap</h2>
                        <p class="step-desc">Kies het busformaat dat bij uw groep past, of vul zelf een aantal in. Bijzonderheden zijn optioneel.</p>
                        <div class="pax-field">
                            <span class="pax-main-label" id="pax-mode-label">Aantal personen / busformaat *</span>
                            <div class="bus-grid" role="radiogroup" aria-labelledby="pax-mode-label">
                                <button type="button" class="bus-card selected" data-pax="19" aria-pressed="true">
                                    <span class="bus-card-cap">19</span>
                                    <span class="bus-card-t">personen</span>
                                    <span class="bus-card-s">Kleinere bus</span>
                                </button>
                                <button type="button" class="bus-card" data-pax="50" aria-pressed="false">
                                    <span class="bus-card-cap">50</span>
                                    <span class="bus-card-t">personen</span>
                                    <span class="bus-card-s">Grote bus</span>
                                </button>
                                <button type="button" class="bus-card" data-pax="60" aria-pressed="false">
                                    <span class="bus-card-cap">60</span>
                                    <span class="bus-card-t">personen</span>
                                    <span class="bus-card-s">Grote bus</span>
                                </button>
                                <button type="button" class="bus-card bus-card--custom" data-pax-mode="custom" aria-pressed="false">
                                    <span class="bus-card-cap">…</span>
                                    <span class="bus-card-t">Zelf invullen</span>
                                    <span class="bus-card-s">Ander aantal</span>
                                </button>
                            </div>
                            <input type="hidden" name="aantal_personen" id="aantal_personen" value="19">
                            <div class="pax-custom-wrap" id="pax-custom-wrap" hidden>
                                <label for="aantal_personen_ui">Aantal personen</label>
                                <div class="pax-stepper">
                                    <button type="button" class="pax-sbtn" id="pax-minus" aria-label="Eén minder">−</button>
                                    <input type="number" id="aantal_personen_ui" min="1" max="999" value="19" inputmode="numeric" title="Aantal personen">
                                    <button type="button" class="pax-sbtn" id="pax-plus" aria-label="Eén meer">+</button>
                                </div>
                            </div>
                            <p class="pax-hint">Alleen tekst bij &quot;Bijzonderheden&quot; kan later op uw offerte verschijnen.</p>
                        </div>

                        <label for="bijzonderheden">Bijzonderheden <span style="font-weight:400;text-transform:none;color:var(--muted);">(optioneel)</span></label>
                        <textarea name="bijzonderheden" id="bijzonderheden" maxlength="4000" placeholder="Bijv. rolstoel, extra bagage, kinderzitjes…"></textarea>
                    </section>

                    <section class="step-panel" data-step="4">
                        <h2 class="step-title">Contactgegevens</h2>
                        <p class="step-desc">Laatste stap: zo kunnen we u bereiken voor de offerte.</p>
                        <label for="bedrijfsnaam">Bedrijfsnaam <span style="font-weight:400;text-transform:none;color:var(--muted);">(optioneel)</span></label>
                        <input type="text" name="bedrijfsnaam" id="bedrijfsnaam" maxlength="120" autocomplete="organization">

                        <div class="row2">
                            <div>
                                <label for="voornaam">Voornaam *</label>
                                <input type="text" name="voornaam" id="voornaam" maxlength="80" autocomplete="given-name">
                            </div>
                            <div>
                                <label for="achternaam">Achternaam *</label>
                                <input type="text" name="achternaam" id="achternaam" maxlength="80" autocomplete="family-name">
                            </div>
                        </div>

                        <label for="email">E-mailadres *</label>
                        <input type="email" name="email" id="email" maxlength="120" autocomplete="email">

                        <label for="telefoon">Telefoonnummer</label>
                        <input type="tel" name="telefoon" id="telefoon" maxlength="40" autocomplete="tel">
                    </section>

                    <div class="nav-row">
                        <button type="button" class="btn btn-secondary" id="btn-back" style="display:none;">Terug</button>
                        <button type="button" class="btn btn-primary" id="btn-next">Volgende</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit" style="display:none;">Offerte aanvragen</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($tenant !== null): ?>
        <p class="offerte-build-label"><?php echo offerte_h('Versie: ' . $offerteWizardBuildLabel); ?></p>
    <?php endif; ?>
</div>

<?php if ($tenant !== null && !$bedankt): ?>
<script>
(function () {
    var current = 1;
    var form = document.getElementById('wizard-form');
    if (!form) return;

    var ritInput = document.getElementById('rit_type');
    var cards = document.querySelectorAll('.type-card');
    var retourBlock = document.getElementById('retour-extra');
    var btnBack = document.getElementById('btn-back');
    var btnNext = document.getElementById('btn-next');
    var btnSubmit = document.getElementById('btn-submit');
    var steps = document.querySelectorAll('.step-panel');
    var progress = document.querySelectorAll('.progress-step');

    function ritNeedsRetour() {
        var t = ritInput.value;
        return t === 'brenghaal' || t === 'dagtocht';
    }

    function formatNlDatum(dStr) {
        if (!dStr) return '';
        var p = dStr.split('-');
        if (p.length !== 3) return '';
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        if (isNaN(d.getTime())) return '';
        try {
            return d.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        } catch (e1) {
            return dStr;
        }
    }

    function formatTijdNl(tStr) {
        if (!tStr) return '';
        var p = tStr.split(':');
        var h = parseInt(p[0], 10);
        var mi = parseInt(p[1] || '0', 10);
        if (isNaN(h) || isNaN(mi)) return tStr;
        return ('0' + h).slice(-2) + ':' + ('0' + mi).slice(-2);
    }

    function updateLeesbareTijden() {
        var dh = document.getElementById('rit_datum');
        var th = document.getElementById('rit_tijd');
        var elH = document.getElementById('leesbaar-heen');
        if (elH && dh && th) {
            if (!dh.value || !th.value) {
                elH.textContent = 'Kies een datum en een tijd voor vertrek.';
            } else {
                elH.textContent = formatNlDatum(dh.value) + ' · om ' + formatTijdNl(th.value) + ' uur';
            }
        }
        var elR = document.getElementById('leesbaar-retour');
        if (!elR) return;
        if (!ritNeedsRetour()) return;
        var dr = document.getElementById('retour_datum');
        var tr = document.getElementById('tijd_vertrek_bestemming');
        if (!dr || !tr) return;
        if (!dr.value || !tr.value) {
            elR.textContent = 'Kies retourdatum en vertrektijd (aan de bestemming).';
        } else {
            elR.textContent = formatNlDatum(dr.value) + ' · om ' + formatTijdNl(tr.value) + ' uur';
        }
    }

    document.querySelectorAll('.time-pill-row').forEach(function (row) {
        var tid = row.getAttribute('data-time-target');
        var inp = tid ? document.getElementById(tid) : null;
        if (!inp) return;
        row.querySelectorAll('.time-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                var t = pill.getAttribute('data-time');
                if (t) inp.value = t;
                inp.dispatchEvent(new Event('input', { bubbles: true }));
                inp.dispatchEvent(new Event('change', { bubbles: true }));
                updateLeesbareTijden();
            });
        });
    });

    ['rit_datum', 'rit_tijd', 'retour_datum', 'tijd_vertrek_bestemming'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateLeesbareTijden);
            el.addEventListener('change', updateLeesbareTijden);
        }
    });

    (function initPaxBus() {
        var hidden = document.getElementById('aantal_personen');
        var wrap = document.getElementById('pax-custom-wrap');
        var ui = document.getElementById('aantal_personen_ui');
        var minus = document.getElementById('pax-minus');
        var plus = document.getElementById('pax-plus');
        var busCards = document.querySelectorAll('.bus-card');
        if (!hidden || !wrap || !ui || !busCards.length) return;

        var customMode = false;

        function setBusVisual(selPax) {
            busCards.forEach(function (c) {
                var isCustom = c.getAttribute('data-pax-mode') === 'custom';
                var px = c.getAttribute('data-pax');
                var on = isCustom ? customMode : (!customMode && px && parseInt(px, 10) === selPax);
                c.classList.toggle('selected', !!on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function syncUiFromHidden() {
            var v = parseInt(hidden.value, 10);
            if (isNaN(v) || v < 1) v = 19;
            ui.value = String(Math.min(999, v));
        }

        function selectBus(pax) {
            customMode = false;
            hidden.value = String(pax);
            wrap.hidden = true;
            syncUiFromHidden();
            setBusVisual(pax);
        }

        function selectCustom() {
            customMode = true;
            wrap.hidden = false;
            syncUiFromHidden();
            setBusVisual(0);
            try { ui.focus(); } catch (e2) {}
        }

        busCards.forEach(function (c) {
            c.addEventListener('click', function () {
                if (c.getAttribute('data-pax-mode') === 'custom') {
                    selectCustom();
                    return;
                }
                var px = parseInt(c.getAttribute('data-pax'), 10);
                if (!isNaN(px)) selectBus(px);
            });
        });

        function bump(delta) {
            var v = Math.max(1, Math.min(999, (parseInt(ui.value, 10) || 1) + delta));
            ui.value = String(v);
            hidden.value = String(v);
        }
        if (minus) minus.addEventListener('click', function () { bump(-1); });
        if (plus) plus.addEventListener('click', function () { bump(1); });
        ui.addEventListener('input', function () {
            var v = parseInt(ui.value, 10);
            if (isNaN(v) || v < 1) return;
            hidden.value = String(Math.min(999, v));
        });
        ui.addEventListener('change', function () {
            var v = Math.max(1, Math.min(999, parseInt(ui.value, 10) || 1));
            hidden.value = String(v);
            ui.value = hidden.value;
        });

        selectBus(19);
    })();

    function updateRetourVisibility() {
        if (ritNeedsRetour()) {
            retourBlock.classList.add('is-on');
        } else {
            retourBlock.classList.remove('is-on');
        }
        updateLeesbareTijden();
    }

    cards.forEach(function (c) {
        c.addEventListener('click', function () {
            cards.forEach(function (x) {
                x.classList.remove('selected');
                x.setAttribute('aria-checked', 'false');
            });
            c.classList.add('selected');
            c.setAttribute('aria-checked', 'true');
            ritInput.value = c.getAttribute('data-rit') || 'enkel';
            updateRetourVisibility();
        });
        c.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                c.click();
            }
        });
    });

    function setProgress(n) {
        progress.forEach(function (p, i) {
            p.classList.remove('active', 'done');
            var stepNum = i + 1;
            if (stepNum < n) p.classList.add('done');
            if (stepNum === n) p.classList.add('active');
        });
    }

    function showStep(n) {
        current = n;
        steps.forEach(function (el) {
            var s = parseInt(el.getAttribute('data-step'), 10);
            el.classList.toggle('is-active', s === n);
        });
        btnBack.style.display = n > 1 ? 'block' : 'none';
        btnNext.style.display = n < 4 ? 'block' : 'none';
        btnSubmit.style.display = n === 4 ? 'block' : 'none';
        setProgress(n);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function valStep1() {
        return ritInput.value === 'enkel' || ritInput.value === 'brenghaal' || ritInput.value === 'dagtocht';
    }
    function valStep2() {
        var v = document.getElementById('vertrek_adres').value.trim();
        var b = document.getElementById('bestemming_adres').value.trim();
        var d = document.getElementById('rit_datum').value;
        var t = document.getElementById('rit_tijd').value;
        if (!v || !b || !d) return 'Vul vertrek- en bestemmingsadres en vertrekdatum in.';
        if (!t) return 'Vul een vertrektijd in.';
        if (ritNeedsRetour()) {
            var rd = document.getElementById('retour_datum').value;
            var tb = document.getElementById('tijd_vertrek_bestemming').value;
            if (!rd) return 'Vul de retourdatum in.';
            if (!tb) return 'Vul de vertrektijd vanaf de bestemming in.';
        }
        return '';
    }
    function valStep3() {
        var p = parseInt(document.getElementById('aantal_personen').value, 10);
        if (!p || p < 1 || p > 999) return 'Vul een geldig aantal personen (1–999).';
        return '';
    }
    function valStep4() {
        var vn = document.getElementById('voornaam').value.trim();
        var an = document.getElementById('achternaam').value.trim();
        var em = document.getElementById('email').value.trim();
        if (!vn || !an) return 'Vul voornaam en achternaam in.';
        if (!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return 'Vul een geldig e-mailadres in.';
        return '';
    }

    btnNext.addEventListener('click', function () {
        var err = '';
        if (current === 1) {
            if (!valStep1()) err = 'Kies een type rit.';
        } else if (current === 2) {
            err = valStep2();
        } else if (current === 3) {
            err = valStep3();
        }
        if (err) {
            alert(err);
            return;
        }
        if (current < 4) showStep(current + 1);
    });

    btnBack.addEventListener('click', function () {
        if (current > 1) showStep(current - 1);
    });

    form.addEventListener('submit', function (e) {
        var err = '';
        if (!valStep1()) err = 'Kies een type rit.';
        if (!err) err = valStep2();
        if (!err) err = valStep3();
        if (!err) err = valStep4();
        if (err) {
            e.preventDefault();
            alert(err);
            if (!valStep2()) showStep(2);
            else if (!valStep3()) showStep(3);
            else showStep(4);
        }
    });

    updateRetourVisibility();
    updateLeesbareTijden();
    showStep(1);
})();

function offerteInitMaps() {
    var v = document.getElementById('vertrek_adres');
    var b = document.getElementById('bestemming_adres');
    if (!v || !b || !window.google || !google.maps || !google.maps.places) return;
    try {
        var acOpts = {
            fields: ['formatted_address', 'geometry', 'name', 'address_components'],
            componentRestrictions: { country: ['nl', 'be', 'de'] }
        };
        new google.maps.places.Autocomplete(v, acOpts);
        new google.maps.places.Autocomplete(b, acOpts);
    } catch (e) {}
}
</script>
<?php endif; ?>
</body>
</html>
