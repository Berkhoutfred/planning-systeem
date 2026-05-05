<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('<div style="padding:24px;font-family:sans-serif;color:#721c24;">Tenant context ontbreekt.</div>');
}

$nieuwe_akkoorden = [];
$inkomende_aanvragen = [];
$alerts = [];
$nu = new DateTime('today');

try {
    $stmt_akkoorden = $pdo->prepare(
        "SELECT c.id, c.rit_datum, c.geaccepteerd_op, k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
         WHERE c.tenant_id = ?
           AND c.status = 'klant_akkoord'
         ORDER BY c.geaccepteerd_op DESC"
    );
    $stmt_akkoorden->execute([$tenantId]);
    $nieuwe_akkoorden = $stmt_akkoorden->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $nieuwe_akkoorden = [];
}

try {
    $stmtAanvragen = $pdo->prepare(
        "SELECT c.id, c.rit_datum, c.aangemaakt_op, k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
         WHERE c.tenant_id = ?
           AND c.status = 'aanvraag'
         ORDER BY c.aangemaakt_op DESC, c.id DESC"
    );
    $stmtAanvragen->execute([$tenantId]);
    $inkomende_aanvragen = $stmtAanvragen->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inkomende_aanvragen = [];
}

$checkDatum = static function ($datumDb, string $naam, string $onderwerp, string $icon) use (&$alerts, $nu): void {
    if (!$datumDb || $datumDb === '0000-00-00') {
        return;
    }
    $check = new DateTime((string) $datumDb);
    $diff = $nu->diff($check);
    $dagen = $diff->days;
    $isVerleden = $diff->invert === 1;

    if ($isVerleden && $dagen > 0) {
        $alerts[] = ['icon' => $icon, 'titel' => $naam, 'msg' => "$onderwerp is VERLOPEN!", 'type' => 'danger', 'sortering' => 1];
    } elseif (!$isVerleden && $dagen <= 60) {
        $dagenTekst = ($dagen === 0) ? 'VANDAAG' : "over $dagen dagen";
        $alerts[] = ['icon' => $icon, 'titel' => $naam, 'msg' => "$onderwerp verloopt $dagenTekst", 'type' => 'warning', 'sortering' => 2];
    }
};

$checkVerjaardag = static function ($datumDb, string $naam) use (&$alerts, $nu): void {
    if (!$datumDb || $datumDb === '0000-00-00') {
        return;
    }
    $verjaardag = new DateTime((string) $datumDb);
    $verjaardag->setDate((int) $nu->format('Y'), (int) $verjaardag->format('m'), (int) $verjaardag->format('d'));
    if ($verjaardag < $nu) {
        $verjaardag->modify('+1 year');
    }
    $dagen = $nu->diff($verjaardag)->days;
    if ($dagen === 0) {
        $alerts[] = ['icon' => 'fa-birthday-cake', 'titel' => $naam, 'msg' => 'Is VANDAAG jarig! 🎂', 'type' => 'info', 'sortering' => 0];
    }
};

try {
    $stmtV = $pdo->prepare('SELECT naam, apk_datum, tacho_datum FROM voertuigen WHERE tenant_id = ? AND archief = 0');
    $stmtV->execute([$tenantId]);
    foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $checkDatum($v['apk_datum'] ?? null, (string) ($v['naam'] ?? '-'), 'APK', 'fa-bus');
        $checkDatum($v['tacho_datum'] ?? null, (string) ($v['naam'] ?? '-'), 'Tacho', 'fa-tachometer-alt');
    }

    $stmtC = $pdo->prepare('SELECT voornaam, achternaam, rijbewijs_verloopt, bestuurderskaart_geldig_tot, code95_geldig_tot, geboortedatum FROM chauffeurs WHERE tenant_id = ? AND archief = 0 AND actief = 1');
    $stmtC->execute([$tenantId]);
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $naam = trim(((string) ($c['voornaam'] ?? '')) . ' ' . ((string) ($c['achternaam'] ?? '')));
        if ($naam === '') {
            $naam = 'Onbekend';
        }
        $checkDatum($c['rijbewijs_verloopt'] ?? null, $naam, 'Rijbewijs', 'fa-id-card');
        $checkDatum($c['bestuurderskaart_geldig_tot'] ?? null, $naam, 'Chauffeurskaart', 'fa-id-badge');
        $checkDatum($c['code95_geldig_tot'] ?? null, $naam, 'Code 95', 'fa-graduation-cap');
        $checkVerjaardag($c['geboortedatum'] ?? null, $naam);
    }

    usort($alerts, static fn(array $a, array $b): int => ((int) $a['sortering']) <=> ((int) $b['sortering']));
} catch (PDOException $e) {
    $alerts = [];
}

include 'includes/header.php';
?>
<style>
    body { background: #f4f7f6; }
    .attn-wrap { max-width: 1160px; margin: 28px auto; padding: 0 20px; }
    .attn-top h1 { margin: 0; color: #0b3e69; font-size: 26px; }
    .attn-top p { margin: 6px 0 0 0; color: #5b6878; }
    .attn-grid { margin-top: 20px; display: grid; gap: 16px; grid-template-columns: 1fr; }
    .attn-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    .attn-head { padding: 11px 14px; font-weight: 700; color: #fff; display: flex; justify-content: space-between; align-items: center; }
    .attn-head small { opacity: 0.9; font-weight: 700; }
    .attn-head.a { background: #7fd3e4; color: #0b3e69; }
    .attn-head.b { background: #1f4fa3; }
    .attn-head.c { background: #c24141; }
    .attn-body { padding: 10px 14px; }
    .attn-row { padding: 10px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; gap: 10px; align-items: center; }
    .attn-row:last-child { border-bottom: none; }
    .muted { color: #6b7280; font-style: italic; }
    .btn { background: #0b3e69; color: #fff; text-decoration: none; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .btn:hover { background: #082f52; }
    .tag { font-size: 12px; color: #6b7280; }
    .danger { color: #c53030; font-weight: 700; }
    .warning { color: #b45309; font-weight: 700; }
    .info { color: #1d4ed8; font-weight: 700; }
</style>

<div class="attn-wrap">
    <div class="attn-top">
        <h1>Aandachtspunten</h1>
        <p>Alle openstaande opvolgpunten op een plek.</p>
    </div>

    <div class="attn-grid">
        <section class="attn-card" id="akkoorden">
            <div class="attn-head a"><span><i class="fas fa-bell"></i> Nieuwe Akkoorden (Inplannen)</span><small><?php echo count($nieuwe_akkoorden); ?></small></div>
            <div class="attn-body">
                <?php if ($nieuwe_akkoorden === []): ?>
                    <div class="attn-row muted">Geen openstaande akkoorden.</div>
                <?php else: foreach ($nieuwe_akkoorden as $akk):
                    $klantWeergave = !empty($akk['bedrijfsnaam']) ? (string) $akk['bedrijfsnaam'] : trim(((string) ($akk['voornaam'] ?? '')) . ' ' . ((string) ($akk['achternaam'] ?? '')));
                    $ritDatum = (string) ($akk['rit_datum'] ?? '');
                    ?>
                    <div class="attn-row">
                        <div>
                            <strong><?php echo htmlspecialchars($klantWeergave !== '' ? $klantWeergave : 'Onbekende klant', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="tag">Ritdatum: <?php echo ($ritDatum !== '' && strtotime($ritDatum) !== false) ? date('d-m-Y', strtotime($ritDatum)) : 'Onbekend'; ?></span>
                        </div>
                        <a class="btn" href="live_planbord.php?datum_van=<?php echo urlencode($ritDatum); ?>&datum_tot=<?php echo urlencode($ritDatum); ?>">Bekijk &amp; plan</a>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="attn-card" id="aanvragen">
            <div class="attn-head b"><span><i class="fas fa-inbox"></i> Inkomende Aanvragen (Nieuw)</span><small><?php echo count($inkomende_aanvragen); ?></small></div>
            <div class="attn-body">
                <?php if ($inkomende_aanvragen === []): ?>
                    <div class="attn-row muted">Geen nieuwe aanvragen.</div>
                <?php else: foreach ($inkomende_aanvragen as $aanvraag):
                    $naam = !empty($aanvraag['bedrijfsnaam']) ? (string) $aanvraag['bedrijfsnaam'] : trim(((string) ($aanvraag['voornaam'] ?? '')) . ' ' . ((string) ($aanvraag['achternaam'] ?? '')));
                    $ritDatum = (string) ($aanvraag['rit_datum'] ?? '');
                    ?>
                    <div class="attn-row">
                        <div>
                            <strong><?php echo htmlspecialchars($naam !== '' ? $naam : 'Onbekende klant', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="tag">Ritdatum: <?php echo ($ritDatum !== '' && strtotime($ritDatum) !== false) ? date('d-m-Y', strtotime($ritDatum)) : 'Onbekend'; ?></span>
                        </div>
                        <a class="btn" href="calculatie/calculaties_bewerken.php?id=<?php echo (int) ($aanvraag['id'] ?? 0); ?>">Bekijk</a>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <section class="attn-card" id="waarschuwingen">
            <div class="attn-head c"><span><i class="fas fa-exclamation-triangle"></i> Actie Vereist (Waarschuwingen)</span><small><?php echo count($alerts); ?></small></div>
            <div class="attn-body">
                <?php if ($alerts === []): ?>
                    <div class="attn-row muted">Geen waarschuwingen actief.</div>
                <?php else: foreach ($alerts as $alert):
                    $type = (string) ($alert['type'] ?? '');
                    $cls = $type === 'danger' ? 'danger' : ($type === 'warning' ? 'warning' : 'info');
                    ?>
                    <div class="attn-row">
                        <div>
                            <strong><i class="fas <?php echo htmlspecialchars((string) ($alert['icon'] ?? 'fa-circle'), ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars((string) ($alert['titel'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="<?php echo $cls; ?>"><?php echo htmlspecialchars((string) ($alert['msg'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
