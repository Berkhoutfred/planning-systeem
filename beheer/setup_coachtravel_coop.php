<?php
declare(strict_types=1);

/**
 * Eenmalige provisioning: CoachTravel tenant + Coöp netwerk + Berkhout partner.
 * CLI: php setup_coachtravel_coop.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Alleen via CLI uitvoeren.\n");
    exit(1);
}

require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/reis_netwerk.php';

const COACH_SLUG = 'coachtravel';
const COACH_NAAM = 'Coach Travel';
const COACH_EMAIL = 'mariska@coachtravel.nl';
const COACH_NAAM_CONTACT = 'Mariska Winnemuller';
const NETWERK_NAAM = 'CoachTravel Trio';
const NETWERK_SLUG = 'coachtravel_trio';
const BERKHOUT_TENANT_ID = 1;

function gen_wachtwoord(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $pw = '';
    for ($i = 0; $i < 12; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $pw . '!';
}

reis_netwerk_ensure_tables($pdo);

$log = [];

try {
    $pdo->beginTransaction();

    // ── 1. Coach Travel tenant ───────────────────────────────
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
    $stmt->execute([COACH_SLUG]);
    $coachId = (int) ($stmt->fetchColumn() ?: 0);

    if ($coachId <= 0) {
        $pdo->prepare("INSERT INTO tenants (naam, slug, status) VALUES (?, ?, 'active')")
            ->execute([COACH_NAAM, COACH_SLUG]);
        $coachId = (int) $pdo->lastInsertId();
        $log[] = "Tenant aangemaakt: Coach Travel (#{$coachId})";
    } else {
        $log[] = "Tenant bestond al: Coach Travel (#{$coachId})";
    }

    // ── 2. Modules CoachTravel ───────────────────────────────
    $pdo->prepare('INSERT INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, \'coopdagtochten\', 1)
                   ON DUPLICATE KEY UPDATE actief = 1')
        ->execute([$coachId]);
    // Geen basis/planbord — Coach Travel is reizen-portaal, geen taxi-ERP.
    foreach (['basis', 'planbord', 'evenementen', 'vaste_ritten', 'social_media', 'busreizen', 'dagtochten'] as $mod) {
        $pdo->prepare('UPDATE tenant_modules SET actief = 0 WHERE tenant_id = ? AND module_code = ?')
            ->execute([$coachId, $mod]);
    }
    $log[] = 'Modules CoachTravel: alleen coopdagtochten (ERP-modules uit)';

    // ── 3. Instellingen + rekenvariabelen ────────────────────
    $pdo->prepare('INSERT INTO tenant_instellingen (tenant_id, bedrijfsnaam, email)
                   VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE bedrijfsnaam = VALUES(bedrijfsnaam), email = VALUES(email)')
        ->execute([$coachId, COACH_NAAM, COACH_EMAIL]);

    $pdo->prepare('INSERT IGNORE INTO tenant_rekenvariabelen (tenant_id, km_prijs_basis, starttarief) VALUES (?, 0, 0)')
        ->execute([$coachId]);

    // ── 4. Admin gebruiker ─────────────────────────────────────
    $stmtUser = $pdo->prepare('SELECT id, tenant_id FROM users WHERE email = ? LIMIT 1');
    $stmtUser->execute([COACH_EMAIL]);
    $bestaandeUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $nieuwWachtwoord = null;
    if ($bestaandeUser) {
        if ((int) $bestaandeUser['tenant_id'] !== $coachId) {
            throw new RuntimeException('E-mail ' . COACH_EMAIL . ' is al in gebruik bij andere tenant.');
        }
        $log[] = 'Admin bestond al: ' . COACH_EMAIL;
    } else {
        $nieuwWachtwoord = gen_wachtwoord();
        $hash = password_hash($nieuwWachtwoord, PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO users (tenant_id, email, wachtwoord_hash, volledige_naam, rol, actief, email_otp_enabled)
             VALUES (?, ?, ?, ?, \'tenant_admin\', 1, 0)'
        )->execute([$coachId, COACH_EMAIL, $hash, COACH_NAAM_CONTACT]);
        $log[] = 'Admin aangemaakt: ' . COACH_EMAIL;
    }

    // ── 5. Reis-netwerk ────────────────────────────────────────
    $stmtNet = $pdo->prepare('SELECT id FROM reis_netwerken WHERE slug = ? LIMIT 1');
    $stmtNet->execute([NETWERK_SLUG]);
    $netwerkId = (int) ($stmtNet->fetchColumn() ?: 0);

    if ($netwerkId <= 0) {
        $pdo->prepare(
            'INSERT INTO reis_netwerken (naam, slug, leiding_tenant_id, status) VALUES (?, ?, ?, \'active\')'
        )->execute([NETWERK_NAAM, NETWERK_SLUG, $coachId]);
        $netwerkId = (int) $pdo->lastInsertId();
        $log[] = "Netwerk aangemaakt: {$netwerkId}";
    } else {
        $pdo->prepare('UPDATE reis_netwerken SET leiding_tenant_id = ?, status = \'active\' WHERE id = ?')
            ->execute([$coachId, $netwerkId]);
        $log[] = "Netwerk bijgewerkt: {$netwerkId}";
    }

    // Leider-partner rij
    $pdo->prepare(
        'INSERT INTO reis_netwerk_partners (netwerk_id, tenant_id, rol, mag_bewerken, mag_bekijken, partner_label)
         VALUES (?, ?, \'leider\', 1, 1, ?)
         ON DUPLICATE KEY UPDATE rol=\'leider\', mag_bewerken=1, mag_bekijken=1, partner_label=VALUES(partner_label)'
    )->execute([$netwerkId, $coachId, COACH_NAAM]);

    // ── 6. Berkhout als partner ────────────────────────────────
    $chkBerkhout = $pdo->prepare('SELECT id FROM tenants WHERE id = ? LIMIT 1');
    $chkBerkhout->execute([BERKHOUT_TENANT_ID]);
    if (!$chkBerkhout->fetchColumn()) {
        throw new RuntimeException('Berkhout tenant #' . BERKHOUT_TENANT_ID . ' niet gevonden.');
    }

    $pdo->prepare(
        'INSERT INTO reis_netwerk_partners (netwerk_id, tenant_id, rol, mag_bewerken, mag_bekijken, partner_label)
         VALUES (?, ?, \'partner\', 0, 1, ?)
         ON DUPLICATE KEY UPDATE rol=\'partner\', mag_bewerken=0, mag_bekijken=1, partner_label=VALUES(partner_label)'
    )->execute([$netwerkId, BERKHOUT_TENANT_ID, 'Berkhout Busreizen']);

    $pdo->prepare('INSERT INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, \'coopdagtochten\', 1)
                   ON DUPLICATE KEY UPDATE actief = 1')
        ->execute([BERKHOUT_TENANT_ID]);

    // Legacy busreizen-module uitzetten (coop neemt over)
    $pdo->prepare('UPDATE tenant_modules SET actief = 0 WHERE tenant_id = ? AND module_code = \'busreizen\'')
        ->execute([BERKHOUT_TENANT_ID]);

    $log[] = 'Berkhout gekoppeld als partner (read-only)';

    // ── 7. Bestaande reizen/boekingen → CoachTravel (leider) ───
    $movedReizen = $pdo->exec("UPDATE busreizen SET tenant_id = {$coachId} WHERE tenant_id = " . BERKHOUT_TENANT_ID);
    $movedBoekingen = $pdo->exec("UPDATE busreis_boekingen SET tenant_id = {$coachId} WHERE tenant_id = " . BERKHOUT_TENANT_ID);
    $log[] = "Data gemigreerd naar CoachTravel: {$movedReizen} reizen, {$movedBoekingen} boekingen";

    $pdo->commit();

    echo "=== COACHTRAVEL COÖP PROvisioning OK ===\n";
    foreach ($log as $line) {
        echo "- {$line}\n";
    }
    echo "\nInlog CoachTravel:\n";
    echo "  URL:       https://tourplan.nl/login.php\n";
    echo "  E-mail:    " . COACH_EMAIL . "\n";
    if ($nieuwWachtwoord !== null) {
        echo "  Wachtwoord: {$nieuwWachtwoord}\n";
        echo "  (nieuw gegenereerd — doorgeven aan Mariska)\n";
    } else {
        echo "  Wachtwoord: (bestaand account — ongewijzigd)\n";
    }
    echo "\nNetwerk: " . NETWERK_NAAM . " (leider #{$coachId}, partner Berkhout #" . BERKHOUT_TENANT_ID . ")\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FOUT: ' . $e->getMessage() . "\n");
    exit(1);
}
