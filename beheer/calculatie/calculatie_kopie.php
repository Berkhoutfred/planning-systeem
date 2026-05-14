<?php

declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/calculatie_dupliceren.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('<div style="padding:20px;">Geen tenantcontext.</div>');
}

$csrf = auth_get_csrf_token();
$bronId = (int) ($_GET['bron_id'] ?? $_GET['id'] ?? 0);
$err = isset($_GET['err']) ? (string) $_GET['err'] : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        header('Location: calculatie_kopie.php?bron_id=' . $bronId . '&err=' . rawurlencode('Ongeldige sessie; vernieuw de pagina en probeer opnieuw.'));
        exit;
    }
    $bronId = (int) ($_POST['bron_id'] ?? 0);
    $d1 = trim((string) ($_POST['rit_datum'] ?? ''));
    $d2 = trim((string) ($_POST['rit_datum_eind'] ?? ''));
    $titelRaw = trim((string) ($_POST['titel'] ?? ''));
    $titelOpt = $titelRaw !== '' ? $titelRaw : null;

    try {
        $newId = calculatie_duplicate_voor_tenant($pdo, $tenantId, $bronId, $d1, $d2, $titelOpt);
        header('Location: calculaties_bewerken.php?id=' . $newId . '&actie_msg=' . rawurlencode('Kopie aangemaakt als nieuw concept.'));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$bron = null;
if ($bronId > 0) {
    $st = $pdo->prepare(
        'SELECT c.id, c.titel, c.rit_datum, c.rit_datum_eind, c.rittype, c.prijs,
                k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON k.id = c.klant_id AND k.tenant_id = c.tenant_id
         WHERE c.id = ? AND c.tenant_id = ?
         LIMIT 1'
    );
    $st->execute([$bronId, $tenantId]);
    $bron = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pageTitle = 'Offerte kopiëren';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div style="max-width:640px;margin:24px auto;padding:24px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
    <h1 style="color:#003366;font-size:22px;margin-top:0;"><i class="fas fa-copy"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="color:#555;font-size:14px;">Maak een nieuwe calculatie op basis van een bestaande offerte. Kies de nieuwe ritdatum (en einddatum); regels en bedragen worden gekopieerd als <strong>concept</strong>.</p>

    <?php if ($err !== ''): ?>
        <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;border:1px solid #f5c6cb;">
            <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$bron): ?>
        <p style="color:#856404;">Geen geldige bron-offerte. Ga terug naar <a href="../calculaties.php">Sales &amp; Offertes</a> en kies <strong>Kopie</strong> bij een regel.</p>
    <?php else: ?>
        <?php
        $klant = trim((string) ($bron['bedrijfsnaam'] ?? ''));
        if ($klant === '') {
            $klant = trim((string) ($bron['voornaam'] ?? '') . ' ' . (string) ($bron['achternaam'] ?? ''));
        }
        ?>
        <div style="background:#f4f6f9;padding:12px;border-radius:6px;margin-bottom:20px;font-size:14px;">
            <div><strong>Bron</strong> #<?= (int) $bron['id'] ?> — <?= htmlspecialchars((string) ($bron['titel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="color:#666;margin-top:4px;"><?= htmlspecialchars($klant, ENT_QUOTES, 'UTF-8') ?></div>
            <div style="color:#666;margin-top:4px;">Huidige data: <?= htmlspecialchars((string) ($bron['rit_datum'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($bron['rit_datum_eind']) && (string) $bron['rit_datum_eind'] !== (string) $bron['rit_datum']): ?>
                    t/m <?= htmlspecialchars((string) $bron['rit_datum_eind'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" action="calculatie_kopie.php">
            <input type="hidden" name="auth_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="bron_id" value="<?= (int) $bron['id'] ?>">

            <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;">Nieuwe ritdatum</label>
            <input type="date" name="rit_datum" required style="max-width:220px;margin-bottom:16px;padding:8px;border:1px solid #ccc;border-radius:4px;"
                   value="<?= htmlspecialchars((string) ($bron['rit_datum'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>">

            <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;">Nieuwe einddatum</label>
            <input type="date" name="rit_datum_eind" style="max-width:220px;margin-bottom:16px;padding:8px;border:1px solid #ccc;border-radius:4px;"
                   value="<?= htmlspecialchars((string) ($bron['rit_datum_eind'] ?? $bron['rit_datum'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>">
            <p style="font-size:12px;color:#666;margin-top:-8px;margin-bottom:16px;">Laat gelijk aan ritdatum voor een enkele dag.</p>

            <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;">Titel (optioneel)</label>
            <input type="text" name="titel" style="margin-bottom:20px;padding:8px;width:100%;max-width:520px;border:1px solid #ccc;border-radius:4px;" maxlength="255"
                   placeholder="Leeg = titel van bron + « (kopie)»"
                   value="">

            <button type="submit" style="padding:10px 20px;font-weight:bold;background:#28a745;color:#fff;border:none;border-radius:5px;cursor:pointer;">
                <i class="fas fa-check"></i> Kopie aanmaken
            </button>
            <a href="../calculaties.php" style="margin-left:10px;display:inline-block;padding:10px 16px;background:#6c757d;color:#fff;text-decoration:none;border-radius:5px;">Annuleren</a>
        </form>
    <?php endif; ?>
</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
