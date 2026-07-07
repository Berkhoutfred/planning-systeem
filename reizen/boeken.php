<?php
declare(strict_types=1);
// Bestand: reizen/boeken.php — verwerkt formulier + stuurt door naar Mollie

require_once __DIR__ . '/../beheer/includes/db.php';
require_once __DIR__ . '/../env.php';
require_once __DIR__ . '/_prijs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$busreisId = (int)($_POST['busreis_id'] ?? 0);
if (!$busreisId) { header('Location: index.php'); exit; }

// Reis ophalen en valideren
$stmt = $pdo->prepare("SELECT * FROM busreizen WHERE id=? AND status='gepubliceerd'");
$stmt->execute([$busreisId]);
$r = $stmt->fetch();
if (!$r) { header('Location: index.php'); exit; }

// Capaciteitscheck
$geboekt = (int)$pdo->prepare("SELECT COUNT(*) FROM busreis_boekingen WHERE busreis_id=? AND status!='geannuleerd'")->execute([$busreisId]) ? $pdo->query("SELECT COUNT(*) FROM busreis_boekingen WHERE busreis_id=$busreisId AND status!='geannuleerd'")->fetchColumn() : 0;
if ($geboekt >= $r['max_deelnemers']) { header("Location: detail.php?slug={$r['slug']}&fout=vol"); exit; }

// Formulierdata ophalen
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
    header("Location: detail.php?slug={$r['slug']}&fout={$msg}");
    exit;
}

// Prijs berekenen (incl. vroegboekkorting indien actief)
$opties = json_decode($optiesJson, true) ?: [];
$optiesTotal = array_sum(array_column($opties, 'prijs'));
$prijs = busreis_bereken_prijs($r, $aantal, $enkelpersoon, (float) $optiesTotal);
$subtotaal = $prijs['subtotaal'];
$totaal = $prijs['totaal'];

// Boekingreferentie genereren
$ref = 'BR-' . date('Y') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);

$bronPartner = trim((string) ($_POST['bron_partner'] ?? ''));
if (strlen($bronPartner) > 80) {
    $bronPartner = substr($bronPartner, 0, 80);
}

// Deelnemers ophalen
$deelnemers = $_POST['deelnemers'] ?? [];

try {
    $pdo->beginTransaction();

    // Boeking aanmaken
    $cols = 'busreis_id,tenant_id,boeking_ref,voornaam,achternaam,email,telefoon,adres,postcode,woonplaats,
         telefoon_thuisblijver,aantal_deelnemers,halte_id,enkelpersoon_toeslag,gekozen_opties,
         subtotaal,reserveringskosten,totaal,betaal_status,status';
    $vals = '?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'open\',\'nieuw\'';
    $bind = [
        $busreisId, (int)($r['tenant_id'] ?? 1), $ref,
        $voornaam, $achternaam, $email, $telefoon, $adres, $postcode, $woonplaats,
        $telThuis, $aantal, $halteId, $enkelpersoon,
        json_encode($opties, JSON_UNESCAPED_UNICODE),
        $subtotaal, $prijs['reserveringskosten'], $totaal,
    ];

    if ($bronPartner !== '') {
        $cols .= ',bron_partner';
        $vals .= ',?';
        $bind[] = $bronPartner;
    }

    $ins = $pdo->prepare("INSERT INTO busreis_boekingen ({$cols}) VALUES ({$vals})");
    $ins->execute($bind);
    $boekingId = (int)$pdo->lastInsertId();

    // Deelnemers opslaan
    $insD = $pdo->prepare("INSERT INTO busreis_deelnemers (boeking_id,voornaam,achternaam,is_hoofdboeker) VALUES (?,?,?,?)");
    foreach ($deelnemers as $i => $d) {
        $vn = trim($d['voornaam'] ?? '');
        $an = trim($d['achternaam'] ?? '');
        if ($vn || $an) {
            $insD->execute([$boekingId, $vn, $an, $i===0 ? 1 : 0]);
        }
    }

    $pdo->commit();

    // Mollie betaling aanmaken
    $mollieKey = env_value('MOLLIE_API_KEY_LIVE', '');
    $beschrijving = "Busreis: {$r['titel']} | {$ref}";
    $redirectUrl  = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'tourplan.nl') . '/reizen/bedankt.php?ref=' . urlencode($ref);
    $webhookUrl   = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'tourplan.nl') . '/reizen/webhook.php';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL           => 'https://api.mollie.com/v2/payments',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => ["Authorization: Bearer {$mollieKey}", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS    => json_encode([
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
        // Mollie payment ID opslaan
        $pdo->prepare("UPDATE busreis_boekingen SET mollie_payment_id=? WHERE id=?")
            ->execute([$resp['id'], $boekingId]);

        // Doorsturen naar Mollie
        header('Location: ' . $resp['_links']['checkout']['href']);
        exit;
    } else {
        throw new RuntimeException('Mollie retourneerde geen betaallink: ' . json_encode($resp));
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[BUSREIZEN] Fout bij aanmaken boeking: ' . $e->getMessage());
    header("Location: detail.php?slug={$r['slug']}&fout=betaalfout");
    exit;
}
