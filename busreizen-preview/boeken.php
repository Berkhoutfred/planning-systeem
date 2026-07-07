<?php
declare(strict_types=1);

require __DIR__ . '/_db.php';
require '/home/u473845697/domains/tourplan.nl/public_html/env.php';
require_once __DIR__ . '/../reizen/_prijs.php';
require_once __DIR__ . '/../reizen/_scope.php';

// Token check (POST vanuit detail.php)
$token = $_POST['preview_token'] ?? '';
if ($token !== 'CoachTravel2026') {
    header('Location: https://www.berkhoutreizen.nl/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?token=CoachTravel2026');
    exit;
}

$busreisId = (int)($_POST['busreis_id'] ?? 0);
if (!$busreisId) { header('Location: index.php?token=CoachTravel2026'); exit; }

$scopeTenantId = busreis_scope_tenant_id($pdo, 'preview');
$stmt = $pdo->prepare("SELECT * FROM busreizen WHERE id=? AND tenant_id=? AND status='gepubliceerd'");
$stmt->execute([$busreisId, $scopeTenantId]);
$r = $stmt->fetch();
if (!$r) { header('Location: index.php?token=CoachTravel2026'); exit; }

// Capaciteitscheck
$geboekt = (int)$pdo->query("SELECT COUNT(*) FROM busreis_boekingen WHERE busreis_id={$busreisId} AND status!='geannuleerd'")->fetchColumn();
if ($geboekt >= $r['max_deelnemers']) {
    header("Location: detail.php?token=CoachTravel2026&slug={$r['slug']}&fout=vol");
    exit;
}

// Formulierdata
$aantal      = max(1, min((int)($_POST['aantal_deelnemers'] ?? 1), $r['max_deelnemers'] - $geboekt));
$voornaam    = trim($_POST['voornaam'] ?? '');
$achternaam  = trim($_POST['achternaam'] ?? '');
$email       = strtolower(trim($_POST['email'] ?? ''));
$telefoon    = trim($_POST['telefoon'] ?? '');
$telThuis    = trim($_POST['telefoon_thuisblijver'] ?? '');
$adres       = trim($_POST['adres'] ?? '');
$postcode    = trim($_POST['postcode'] ?? '');
$woonplaats  = trim($_POST['woonplaats'] ?? '');
$opmerkingen = trim($_POST['opmerkingen'] ?? '');
$halteId     = !empty($_POST['halte_id']) ? (int)$_POST['halte_id'] : null;
$enkelpersoon = !empty($_POST['enkelpersoon']) ? 1 : 0;
$optiesJson  = $_POST['opties_json'] ?? '[]';

// Validatie
$fouten = [];
if (!$voornaam)   $fouten[] = 'Voornaam ontbreekt.';
if (!$achternaam) $fouten[] = 'Achternaam ontbreekt.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fouten[] = 'E-mailadres is ongeldig.';
if (!$telefoon)   $fouten[] = 'Telefoonnummer ontbreekt.';
if (!$telThuis)   $fouten[] = 'Telefoonnummer thuisblijver ontbreekt.';

if ($fouten) {
    $msg = urlencode(implode(' ', $fouten));
    header("Location: detail.php?token=CoachTravel2026&slug={$r['slug']}&fout={$msg}");
    exit;
}

// Prijs berekenen (incl. vroegboekkorting indien actief)
$opties      = json_decode($optiesJson, true) ?: [];
$optiesTotal = array_sum(array_column($opties, 'prijs'));
$prijs = busreis_bereken_prijs($r, $aantal, $enkelpersoon, (float) $optiesTotal);
$subtotaal   = $prijs['subtotaal'];
$totaal      = $prijs['totaal'];

$ref      = 'BR-' . date('Y') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
$deelnemers = $_POST['deelnemers'] ?? [];

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO busreis_boekingen
        (busreis_id,tenant_id,boeking_ref,voornaam,achternaam,email,telefoon,adres,postcode,woonplaats,
         telefoon_thuisblijver,aantal_deelnemers,halte_id,enkelpersoon_toeslag,gekozen_opties,
         subtotaal,reserveringskosten,totaal,betaal_status,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'open','nieuw')");
    $ins->execute([
        $busreisId, (int)($r['tenant_id'] ?? 1), $ref,
        $voornaam, $achternaam, $email, $telefoon, $adres, $postcode, $woonplaats,
        $telThuis, $aantal, $halteId, $enkelpersoon,
        json_encode($opties, JSON_UNESCAPED_UNICODE),
        round($subtotaal, 2), round($prijs['reserveringskosten'], 2), round($totaal, 2)
    ]);
    $boekingId = (int)$pdo->lastInsertId();

    $insD = $pdo->prepare("INSERT INTO busreis_deelnemers (boeking_id,voornaam,achternaam,is_hoofdboeker) VALUES (?,?,?,?)");
    foreach ($deelnemers as $i => $d) {
        $vn = trim($d['voornaam'] ?? '');
        $an = trim($d['achternaam'] ?? '');
        if ($vn || $an) {
            $insD->execute([$boekingId, $vn, $an, $i === 0 ? 1 : 0]);
        }
    }

    $pdo->commit();

    // Mollie betaling aanmaken
    $mollieKey    = env_value('MOLLIE_API_KEY_TEST', ''); // Preview gebruikt testmodus
    $beschrijving = "Busreis: {$r['titel']} | {$ref}";
    $redirectUrl  = 'https://www.berkhoutreizen.nl/busreizen-preview/bedankt.php?ref=' . urlencode($ref);
    $webhookUrl   = 'https://www.berkhoutreizen.nl/busreizen-preview/webhook.php';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.mollie.com/v2/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$mollieKey}", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'      => ['currency' => 'EUR', 'value' => number_format($totaal, 2, '.', '')],
            'description' => $beschrijving,
            'redirectUrl' => $redirectUrl,
            'webhookUrl'  => $webhookUrl,
            'metadata'    => ['boeking_id' => $boekingId, 'boeking_ref' => $ref],
        ]),
    ]);
    $resp = json_decode((string)curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['id']) && !empty($resp['_links']['checkout']['href'])) {
        $pdo->prepare("UPDATE busreis_boekingen SET mollie_payment_id=? WHERE id=?")
            ->execute([$resp['id'], $boekingId]);
        header('Location: ' . $resp['_links']['checkout']['href']);
        exit;
    } else {
        throw new RuntimeException('Mollie retourneerde geen betaallink: ' . json_encode($resp));
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[BUSREIZEN-PREVIEW] Fout bij boeking: ' . $e->getMessage());
    header("Location: detail.php?token=CoachTravel2026&slug={$r['slug']}&fout=betaalfout");
    exit;
}
