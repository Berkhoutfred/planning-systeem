<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['platform_owner']);
require 'includes/db.php';
require_once 'includes/reis_netwerk.php';
include 'includes/header.php';

function po_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function po_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    return trim($value, '_');
}

$success      = '';
$error        = '';
$nieuw_tenant = null; // bevat gegevens na succesvolle aanmaak voor uitnodiging

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'create_tenant') {
                $naam = trim((string) ($_POST['tenant_naam'] ?? ''));
                $slugInput = trim((string) ($_POST['tenant_slug'] ?? ''));
                $status = (string) ($_POST['tenant_status'] ?? 'active');
                $slug = po_slugify($slugInput !== '' ? $slugInput : $naam);

                if ($naam === '' || $slug === '') {
                    throw new RuntimeException('Tenantnaam en slug zijn verplicht.');
                }
                if (!in_array($status, ['active', 'inactive'], true)) {
                    throw new RuntimeException('Ongeldige tenant-status.');
                }

                $stmt = $pdo->prepare('INSERT INTO tenants (naam, slug, status) VALUES (?, ?, ?)');
                $stmt->execute([$naam, $slug, $status]);

                $success = 'Tenant succesvol aangemaakt.';
            }

            if ($action === 'create_tenant_admin') {
                $tenantId = (int) ($_POST['admin_tenant_id'] ?? 0);
                $volledigeNaam = trim((string) ($_POST['admin_naam'] ?? ''));
                $email = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
                $password = (string) ($_POST['admin_password'] ?? '');
                $actief = isset($_POST['admin_actief']) ? 1 : 0;

                if ($tenantId <= 0 || $volledigeNaam === '' || $email === '' || $password === '') {
                    throw new RuntimeException('Tenant, naam, e-mail en wachtwoord zijn verplicht.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('E-mailadres is ongeldig.');
                }
                if (strlen($password) < 10) {
                    throw new RuntimeException('Wachtwoord moet minimaal 10 tekens hebben.');
                }

                $stmtTenant = $pdo->prepare('SELECT id FROM tenants WHERE id = ? LIMIT 1');
                $stmtTenant->execute([$tenantId]);
                if (!$stmtTenant->fetchColumn()) {
                    throw new RuntimeException('Geselecteerde tenant bestaat niet.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('
                    INSERT INTO users (tenant_id, email, wachtwoord_hash, volledige_naam, rol, actief, email_otp_enabled)
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ');
                $stmt->execute([$tenantId, $email, $passwordHash, $volledigeNaam, 'tenant_admin', $actief]);

                $success = 'Tenant admin succesvol aangemaakt.';
            }

            // ── ALLES IN ÉÉN: tenant + admin + modules + instellingen ──
            if ($action === 'create_volledig') {
                $bedrijfsnaam = trim((string) ($_POST['v_bedrijfsnaam'] ?? ''));
                $contactnaam  = trim((string) ($_POST['v_contactnaam']  ?? ''));
                $email        = strtolower(trim((string) ($_POST['v_email'] ?? '')));
                $modules_in   = (array) ($_POST['v_modules'] ?? ['basis']);

                // Auto-genereer wachtwoord als niet ingevuld
                $wachtwoord = trim((string) ($_POST['v_wachtwoord'] ?? ''));
                $auto_pw    = false;
                if ($wachtwoord === '') {
                    $wachtwoord = ucfirst(substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 5))
                                . rand(100, 999) . '!';
                    $auto_pw = true;
                }

                if ($bedrijfsnaam === '' || $contactnaam === '' || $email === '') {
                    throw new RuntimeException('Bedrijfsnaam, contactpersoon en e-mail zijn verplicht.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('E-mailadres is ongeldig.');
                }
                if (strlen($wachtwoord) < 8) {
                    throw new RuntimeException('Wachtwoord moet minimaal 8 tekens hebben.');
                }

                $pdo->beginTransaction();

                // 1. Tenant aanmaken
                $slug = po_slugify($bedrijfsnaam);
                // Zorg voor unieke slug
                $bestaand = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE slug LIKE ?");
                $bestaand->execute([$slug . '%']);
                if ((int)$bestaand->fetchColumn() > 0) {
                    $slug .= '_' . rand(10, 99);
                }
                $pdo->prepare("INSERT INTO tenants (naam, slug, status) VALUES (?, ?, 'active')")
                    ->execute([$bedrijfsnaam, $slug]);
                $tid = (int) $pdo->lastInsertId();

                // 2. Admin gebruiker
                $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (tenant_id, email, wachtwoord_hash, volledige_naam, rol, actief)
                               VALUES (?, ?, ?, ?, 'tenant_admin', 1)")
                    ->execute([$tid, $email, $hash, $contactnaam]);

                // 3. Modules activeren
                foreach ($modules_in as $mc) {
                    $pdo->prepare("INSERT IGNORE INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, ?, 1)")
                        ->execute([$tid, $mc]);
                }

                // 4. Bedrijfsinstellingen aanmaken
                $pdo->prepare("INSERT INTO tenant_instellingen (tenant_id, bedrijfsnaam, email) VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE bedrijfsnaam=VALUES(bedrijfsnaam)")
                    ->execute([$tid, $bedrijfsnaam, $email]);

                // 5. Rekenvariabelen aanmaken
                $pdo->prepare("INSERT IGNORE INTO tenant_rekenvariabelen (tenant_id, km_prijs_basis, starttarief) VALUES (?, 0, 0)")
                    ->execute([$tid]);

                $pdo->commit();

                $nieuw_tenant = [
                    'bedrijfsnaam' => $bedrijfsnaam,
                    'email'        => $email,
                    'wachtwoord'   => $wachtwoord,
                    'auto_pw'      => $auto_pw,
                    'modules'      => $modules_in,
                    'login_url'    => 'https://tourplan.nl/login.php',
                ];
                $success = 'Tenant "' . $bedrijfsnaam . '" succesvol aangemaakt!';
            }

            if ($action === 'create_reis_netwerk') {
                $netwerkNaam = trim((string) ($_POST['rn_naam'] ?? ''));
                $netwerkSlug = po_slugify(trim((string) ($_POST['rn_slug'] ?? '')) ?: $netwerkNaam);
                $leidingTenantId = (int) ($_POST['rn_leiding_tenant_id'] ?? 0);

                if ($netwerkNaam === '' || $netwerkSlug === '') {
                    throw new RuntimeException('Netwerknaam is verplicht.');
                }
                if ($leidingTenantId <= 0) {
                    throw new RuntimeException('Kies een leidinggevende tenant.');
                }

                reis_netwerk_ensure_tables($pdo);

                $chk = $pdo->prepare('SELECT id FROM tenants WHERE id = ? LIMIT 1');
                $chk->execute([$leidingTenantId]);
                if (!$chk->fetchColumn()) {
                    throw new RuntimeException('Leiding-tenant bestaat niet.');
                }

                $pdo->beginTransaction();

                $pdo->prepare('INSERT INTO reis_netwerken (naam, slug, leiding_tenant_id, status) VALUES (?, ?, ?, \'active\')')
                    ->execute([$netwerkNaam, $netwerkSlug, $leidingTenantId]);
                $netwerkId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO reis_netwerk_partners (netwerk_id, tenant_id, rol, mag_bewerken, mag_bekijken, partner_label)
                     VALUES (?, ?, \'leider\', 1, 1, ?)
                     ON DUPLICATE KEY UPDATE rol=\'leider\', mag_bewerken=1, mag_bekijken=1'
                )->execute([$netwerkId, $leidingTenantId, $netwerkNaam]);

                $pdo->prepare('INSERT IGNORE INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, \'coopdagtochten\', 1)')
                    ->execute([$leidingTenantId]);

                $pdo->commit();

                $success = 'Reis-netwerk "' . $netwerkNaam . '" aangemaakt. Leider: tenant #' . $leidingTenantId . '.';
            }

            if ($action === 'add_reis_netwerk_partner') {
                $netwerkId = (int) ($_POST['rp_netwerk_id'] ?? 0);
                $partnerTenantId = (int) ($_POST['rp_tenant_id'] ?? 0);
                $partnerLabel = trim((string) ($_POST['rp_label'] ?? ''));
                $rol = (string) ($_POST['rp_rol'] ?? 'partner');

                if ($netwerkId <= 0 || $partnerTenantId <= 0) {
                    throw new RuntimeException('Netwerk en tenant zijn verplicht.');
                }
                if (!in_array($rol, ['partner', 'leider'], true)) {
                    throw new RuntimeException('Ongeldige rol.');
                }

                reis_netwerk_ensure_tables($pdo);

                $netwerk = $pdo->prepare('SELECT id, leiding_tenant_id FROM reis_netwerken WHERE id = ? LIMIT 1');
                $netwerk->execute([$netwerkId]);
                $netwerkRow = $netwerk->fetch(PDO::FETCH_ASSOC);
                if (!$netwerkRow) {
                    throw new RuntimeException('Netwerk niet gevonden.');
                }

                $magBewerken = $rol === 'leider' ? 1 : 0;

                $pdo->beginTransaction();

                if ($rol === 'leider') {
                    $pdo->prepare('UPDATE reis_netwerken SET leiding_tenant_id = ? WHERE id = ?')
                        ->execute([$partnerTenantId, $netwerkId]);
                    $pdo->prepare(
                        'UPDATE reis_netwerk_partners SET rol=\'partner\', mag_bewerken=0 WHERE netwerk_id=? AND rol=\'leider\' AND tenant_id != ?'
                    )->execute([$netwerkId, $partnerTenantId]);
                }

                $pdo->prepare(
                    'INSERT INTO reis_netwerk_partners (netwerk_id, tenant_id, rol, mag_bewerken, mag_bekijken, partner_label)
                     VALUES (?, ?, ?, ?, 1, ?)
                     ON DUPLICATE KEY UPDATE rol=VALUES(rol), mag_bewerken=VALUES(mag_bewerken), mag_bekijken=1, partner_label=VALUES(partner_label)'
                )->execute([$netwerkId, $partnerTenantId, $rol, $magBewerken, $partnerLabel !== '' ? $partnerLabel : null]);

                $pdo->prepare('INSERT IGNORE INTO tenant_modules (tenant_id, module_code, actief) VALUES (?, \'coopdagtochten\', 1)')
                    ->execute([$partnerTenantId]);

                $pdo->commit();

                $success = 'Partner toegevoegd aan netwerk.';
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$csrfToken = auth_get_csrf_token();

$modules = $pdo->query("SELECT * FROM modules WHERE actief=1 ORDER BY volgorde")->fetchAll(PDO::FETCH_ASSOC);
$tenants = $pdo->query("SELECT id, naam, slug, status FROM tenants ORDER BY naam ASC")->fetchAll(PDO::FETCH_ASSOC);

$adminsStmt = $pdo->query("
    SELECT u.id, u.volledige_naam, u.email, u.actief, u.created_at, u.tenant_id, t.naam AS tenant_naam, t.slug AS tenant_slug
    FROM users u
    LEFT JOIN tenants t ON t.id = u.tenant_id
    WHERE u.rol = 'tenant_admin'
    ORDER BY t.naam ASC, u.volledige_naam ASC, u.email ASC
");
$tenantAdmins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);

$adminsByTenant = [];
foreach ($tenantAdmins as $admin) {
    $tid = (int) ($admin['tenant_id'] ?? 0);
    if ($tid <= 0) {
        continue;
    }
    $adminsByTenant[$tid][] = $admin;
}

if (!function_exists('po_is_legacy_admin')) {
    function po_is_legacy_admin(array $admin): bool
    {
        $email = strtolower((string) ($admin['email'] ?? ''));

        return str_ends_with($email, '.local') || str_contains($email, 'pilot-transport');
    }
}

$moduleNamesByTenant = [];
$modListStmt = $pdo->query("
    SELECT tm.tenant_id, m.naam, m.code
    FROM tenant_modules tm
    JOIN modules m ON m.code = tm.module_code
    WHERE tm.actief = 1
    ORDER BY m.volgorde ASC, m.naam ASC
");
foreach ($modListStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $moduleNamesByTenant[(int) $row['tenant_id']][] = (string) $row['naam'];
}

$selectedTenantId = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
$selectedTenant = null;
$selectedLoginUsers = [];
if ($selectedTenantId > 0) {
    foreach ($tenants as $tenant) {
        if ((int) $tenant['id'] === $selectedTenantId) {
            $selectedTenant = $tenant;
            break;
        }
    }
    if ($selectedTenant !== null) {
        $loginStmt = $pdo->prepare("
            SELECT volledige_naam, email, rol, actief, email_otp_enabled, laatste_login_at
            FROM users
            WHERE tenant_id = ?
              AND rol IN ('tenant_admin', 'planner_user')
            ORDER BY actief DESC, rol ASC, volledige_naam ASC
        ");
        $loginStmt->execute([$selectedTenantId]);
        $selectedLoginUsers = $loginStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$owners = $pdo->query("SELECT volledige_naam, email FROM users WHERE rol='platform_owner' AND actief=1 ORDER BY email")->fetchAll(PDO::FETCH_ASSOC);

reis_netwerk_ensure_tables($pdo);
$reisNetwerken = $pdo->query(
    'SELECT n.*, t.naam AS leiding_naam
     FROM reis_netwerken n
     JOIN tenants t ON t.id = n.leiding_tenant_id
     ORDER BY n.id DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$netwerkPartners = [];
if ($reisNetwerken !== []) {
    $ids = array_map(static fn(array $r): int => (int) $r['id'], $reisNetwerken);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtPartners = $pdo->prepare(
        "SELECT p.*, t.naam AS tenant_naam, t.slug AS tenant_slug
         FROM reis_netwerk_partners p
         JOIN tenants t ON t.id = p.tenant_id
         WHERE p.netwerk_id IN ($placeholders)
         ORDER BY p.netwerk_id, p.sort_order, p.id"
    );
    $stmtPartners->execute($ids);
    foreach ($stmtPartners->fetchAll(PDO::FETCH_ASSOC) as $partner) {
        $netwerkPartners[(int) $partner['netwerk_id']][] = $partner;
    }
}
?>

<style>
    .po-wrap { max-width: 1200px; margin: 20px auto; padding: 0 20px; font-family: Arial, sans-serif; }
    .po-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .po-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .po-card h2 { margin: 0; padding: 14px 16px; border-bottom: 1px solid #eee; font-size: 16px; color: #003366; }
    .po-card .body { padding: 16px; }
    .po-msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-weight: bold; }
    .ok { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .err { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: bold; color: #555; text-transform: uppercase; }
    input, select { width: 100%; padding: 9px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; }
    button { background: #003366; color: white; border: none; padding: 10px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; }
    button:hover { background: #00264d; }
    .po-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .po-table th, .po-table td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
    .po-table th { background: #f8fafc; color: #374151; }
    .badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
    .badge.active { background: #dcfce7; color: #166534; }
    .badge.inactive { background: #fee2e2; color: #991b1b; }
    .badge.legacy { background: #fef3c7; color: #92400e; }
    .po-note { font-size: 12px; color: #64748b; margin: 0 0 14px; line-height: 1.5; }
    .tenant-browser { display: grid; grid-template-columns: minmax(260px, 340px) 1fr; gap: 20px; align-items: start; }
    .tenant-zoek { margin-bottom: 12px; }
    .tenant-zoek input { margin-bottom: 6px; }
    .tenant-zoek-hint { font-size: 12px; color: #94a3b8; margin: 0; }
    .tenant-lijst { display: flex; flex-direction: column; gap: 8px; max-height: 420px; overflow-y: auto; }
    .tenant-pick {
        display: block; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 10px;
        text-decoration: none; color: inherit; background: #fff; transition: .15s;
    }
    .tenant-pick:hover { border-color: #003366; background: #f8faff; }
    .tenant-pick.actief { border-color: #003366; background: #eff6ff; box-shadow: inset 0 0 0 1px #003366; }
    .tenant-pick-naam { font-size: 15px; font-weight: 700; color: #003366; margin: 0 0 4px; }
    .tenant-pick-meta { font-size: 12px; color: #64748b; }
    .tenant-detail-empty {
        padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 14px;
        border: 1px dashed #e2e8f0; border-radius: 10px; background: #fafafa;
    }
    .tenant-detail-head { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: start; margin-bottom: 16px; }
    .tenant-detail-title { margin: 0; font-size: 20px; color: #003366; }
    .tenant-detail-sub { margin: 6px 0 0; font-size: 13px; color: #64748b; }
    .tenant-tag-row { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 18px; }
    .tenant-tag { background: #f1f5f9; color: #475569; font-size: 12px; padding: 4px 10px; border-radius: 999px; }
    @media (max-width: 900px) {
        .po-grid { grid-template-columns: 1fr; }
        .tenant-browser { grid-template-columns: 1fr; }
    }
</style>

<div class="po-wrap">
    <h1 style="margin:0 0 4px; color:#003366;"><i class="fa-solid fa-crown"></i> Platform Owner</h1>
    <p style="margin:0 0 20px; color:#888; font-size:13px;">Beheer tenants, gebruikers en modules.</p>

    <?php if ($error !== ''): ?>
        <div class="po-msg err"><i class="fa-solid fa-exclamation-circle"></i> <?php echo po_h($error); ?></div>
    <?php endif; ?>

    <?php if ($nieuw_tenant): ?>
    <!-- Uitnodigingskaartje -->
    <div style="background:linear-gradient(135deg,#002855,#003d82); color:#fff; border-radius:12px; padding:28px; margin-bottom:28px; position:relative;">
        <div style="font-size:22px; font-weight:700; margin-bottom:6px;">
            <i class="fa-solid fa-party-horn"></i> <?php echo po_h($nieuw_tenant['bedrijfsnaam']); ?> is aangemaakt!
        </div>
        <p style="margin:0 0 20px; opacity:.8;">Stuur onderstaande inloggegevens door naar de klant.</p>
        <div id="uitnodiging-tekst" style="background:rgba(255,255,255,0.1); border-radius:8px; padding:18px; font-family:monospace; font-size:13px; line-height:1.8; white-space:pre-wrap;">Welkom bij Tourplan!

Jouw inloggegevens:
🌐 Inlogpagina : <?php echo po_h($nieuw_tenant['login_url']); ?>

📧 E-mail      : <?php echo po_h($nieuw_tenant['email']); ?>

🔑 Wachtwoord  : <?php echo po_h($nieuw_tenant['wachtwoord']); ?>
<?php if ($nieuw_tenant['auto_pw']): ?>
⚠️  Verander je wachtwoord na eerste inlog!<?php endif; ?>

Actieve modules: <?php echo po_h(implode(', ', $nieuw_tenant['modules'])); ?>
</div>
        <button onclick="kopieerUitnodiging()" style="margin-top:14px; background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); padding:9px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600;">
            <i class="fa-solid fa-copy"></i> Kopieer naar klembord
        </button>
    </div>
    <script>
    function kopieerUitnodiging() {
        navigator.clipboard.writeText(document.getElementById('uitnodiging-tekst').innerText)
            .then(() => { alert('Gekopieerd! Plak dit in een e-mail of WhatsApp.'); });
    }
    </script>
    <?php elseif ($success !== ''): ?>
        <div class="po-msg ok"><i class="fa-solid fa-check-circle"></i> <?php echo po_h($success); ?></div>
    <?php endif; ?>

    <section class="po-card" style="margin-bottom:28px;">
        <h2><i class="fa-solid fa-building"></i> Tenants bekijken</h2>
        <div class="body tenant-browser">
            <div>
                <div class="tenant-zoek">
                    <label for="tenant-zoek">Zoek tenant</label>
                    <input type="search" id="tenant-zoek" placeholder="Naam of slug…" autocomplete="off">
                    <p class="tenant-zoek-hint" id="tenant-zoek-hint">Standaard de eerste 5 tenants. Zoek om een andere te vinden.</p>
                </div>
                <div class="tenant-lijst" id="tenant-lijst">
                    <?php foreach ($tenants as $tenant):
                        $tid = (int) $tenant['id'];
                        $admins = $adminsByTenant[$tid] ?? [];
                        $activeCount = count(array_filter(
                            $admins,
                            static fn(array $a): bool => (int) ($a['actief'] ?? 0) === 1 && !po_is_legacy_admin($a)
                        ));
                        $zoekHaystack = strtolower((string) $tenant['naam'] . ' ' . (string) $tenant['slug']);
                    ?>
                        <a
                            href="platform_owner.php?tenant=<?php echo $tid; ?>"
                            class="tenant-pick<?php echo $selectedTenantId === $tid ? ' actief' : ''; ?>"
                            data-zoek="<?php echo po_h($zoekHaystack); ?>"
                        >
                            <p class="tenant-pick-naam"><?php echo po_h((string) $tenant['naam']); ?></p>
                            <div class="tenant-pick-meta">
                                <?php echo po_h((string) $tenant['slug']); ?>
                                · <?php echo $activeCount; ?> inlog<?php echo $activeCount === 1 ? '' : 's'; ?>
                                · <span class="badge <?php echo $tenant['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo po_h((string) $tenant['status']); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <?php if ($selectedTenant === null): ?>
                    <div class="tenant-detail-empty">
                        <i class="fa-solid fa-hand-pointer" style="font-size:28px; margin-bottom:12px; display:block; opacity:.4;"></i>
                        Klik links op een tenant om beheerders en inlogtoegang te zien.
                    </div>
                <?php else:
                    $tid = (int) $selectedTenant['id'];
                    $modNamen = $moduleNamesByTenant[$tid] ?? [];
                ?>
                    <div class="tenant-detail-head">
                        <div>
                            <h3 class="tenant-detail-title"><?php echo po_h((string) $selectedTenant['naam']); ?></h3>
                            <p class="tenant-detail-sub">
                                slug: <code><?php echo po_h((string) $selectedTenant['slug']); ?></code>
                                · status: <?php echo po_h((string) $selectedTenant['status']); ?>
                            </p>
                        </div>
                        <span class="badge <?php echo $selectedTenant['status'] === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo po_h((string) $selectedTenant['status']); ?>
                        </span>
                    </div>

                    <?php if ($modNamen !== []): ?>
                        <div class="tenant-tag-row">
                            <?php foreach ($modNamen as $modNaam): ?>
                                <span class="tenant-tag"><?php echo po_h($modNaam); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="po-note">Geen actieve modules.</p>
                    <?php endif; ?>

                    <h4 style="margin:0 0 10px; font-size:14px; color:#003366;">Inlogtoegang</h4>
                    <?php
                    $zichtbareUsers = array_filter(
                        $selectedLoginUsers,
                        static fn(array $u): bool => !po_is_legacy_admin($u) || (int) ($u['actief'] ?? 0) === 1
                    );
                    ?>
                    <?php if ($zichtbareUsers === []): ?>
                        <p class="po-note">Nog geen actieve beheerder voor deze tenant.</p>
                    <?php else: ?>
                        <table class="po-table">
                            <thead>
                                <tr>
                                    <th>Naam</th>
                                    <th>E-mail (inloggen)</th>
                                    <th>Rol</th>
                                    <th>Inlogcode</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selectedLoginUsers as $user):
                                    if (po_is_legacy_admin($user)) {
                                        continue;
                                    }
                                ?>
                                    <tr<?php echo (int) ($user['actief'] ?? 0) !== 1 ? ' style="opacity:.55;"' : ''; ?>>
                                        <td><?php echo po_h((string) $user['volledige_naam']); ?></td>
                                        <td><?php echo po_h((string) $user['email']); ?></td>
                                        <td><?php echo po_h((string) $user['rol']); ?></td>
                                        <td>
                                            <?php if ((int) ($user['actief'] ?? 0) !== 1): ?>
                                                <span class="badge inactive">inactief</span>
                                            <?php elseif ((int) ($user['email_otp_enabled'] ?? 0) === 1): ?>
                                                <span class="badge active">e-mailcode</span>
                                            <?php else: ?>
                                                wachtwoord
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ((string) ($selectedTenant['slug'] ?? '') === 'testomgeving'): ?>
                        <h4 style="margin:18px 0 10px; font-size:14px; color:#003366;">Platform owners</h4>
                        <table class="po-table">
                            <thead><tr><th>Naam</th><th>E-mail</th></tr></thead>
                            <tbody>
                                <?php foreach ($owners as $owner): ?>
                                    <tr>
                                        <td><?php echo po_h((string) $owner['volledige_naam']); ?></td>
                                        <td><?php echo po_h((string) $owner['email']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="po-note">Sandbox — alleen voor platform/test, niet voor klantwerk.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <script>
    (function () {
        const LIMIT = 5;
        const input = document.getElementById('tenant-zoek');
        const hint = document.getElementById('tenant-zoek-hint');
        const items = Array.from(document.querySelectorAll('#tenant-lijst .tenant-pick'));
        if (!input || items.length === 0) return;

        function applyFilter() {
            const q = input.value.trim().toLowerCase();
            let visible = 0;
            items.forEach(function (el) {
                const hay = el.getAttribute('data-zoek') || '';
                const match = q === '' || hay.indexOf(q) !== -1;
                let show = match;
                if (q === '' && match) {
                    show = visible < LIMIT;
                    if (show) visible++;
                }
                el.style.display = show ? '' : 'none';
            });
            if (q === '') {
                hint.textContent = items.length > LIMIT
                    ? 'Eerste ' + LIMIT + ' van ' + items.length + ' tenants. Zoek om een andere te vinden.'
                    : items.length + ' tenant(s).';
            } else {
                const n = items.filter(function (el) {
                    return el.style.display !== 'none';
                }).length;
                hint.textContent = n === 0 ? 'Geen tenants gevonden.' : n + ' resultaat' + (n === 1 ? '' : 'en') + '.';
            }
        }

        input.addEventListener('input', applyFilter);
        applyFilter();
    })();
    </script>

    <!-- NIEUW: Alles-in-één formulier -->
    <div class="po-card" style="margin-bottom:28px; border:2px solid #003d82;">
        <h2 style="background:linear-gradient(135deg,#002855,#003d82); color:#fff; border-radius:6px 6px 0 0;">
            <i class="fa-solid fa-plus"></i> Nieuwe tenant uitnodigen
        </h2>
        <div class="body">
            <form method="POST">
                <input type="hidden" name="auth_csrf_token" value="<?php echo po_h($csrfToken); ?>">
                <input type="hidden" name="action" value="create_volledig">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div>
                        <label for="v_bedrijfsnaam">Bedrijfsnaam *</label>
                        <input id="v_bedrijfsnaam" name="v_bedrijfsnaam" required placeholder="Bijv. Jansen Reizen">
                    </div>
                    <div>
                        <label for="v_contactnaam">Naam contactpersoon *</label>
                        <input id="v_contactnaam" name="v_contactnaam" required placeholder="Bijv. Jan Jansen">
                    </div>
                    <div>
                        <label for="v_email">E-mailadres *</label>
                        <input id="v_email" name="v_email" type="email" required placeholder="jan@jansen.nl">
                    </div>
                    <div>
                        <label for="v_wachtwoord">Wachtwoord <span style="font-weight:400; color:#aaa;">(leeg = automatisch)</span></label>
                        <input id="v_wachtwoord" name="v_wachtwoord" type="text" placeholder="Laat leeg voor automatisch wachtwoord">
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label>Modules activeren</label>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:6px;">
                    <?php foreach ($modules as $mod): ?>
                    <label style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 14px; cursor:pointer; font-size:13px; font-weight:500;">
                        <input type="checkbox" name="v_modules[]" value="<?php echo po_h($mod['code']); ?>"
                            <?php echo $mod['code'] === 'basis' ? 'checked' : ''; ?>
                            style="accent-color:#003d82; width:16px; height:16px;">
                        <i class="<?php echo po_h($mod['icoon']); ?>" style="color:#003d82;"></i>
                        <?php echo po_h($mod['naam']); ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                    <p style="margin:10px 0 0; font-size:12px; color:#888;">
                        <strong>Dagtochten</strong> = standalone. <strong>Coöp Dagtochten</strong> = samenwerking (koppel daarna partners via netwerk hieronder). Niet beide tegelijk.
                    </p>
                </div>

                <button type="submit" style="margin-top:20px; background:#003d82; color:#fff; border:none; padding:11px 28px; border-radius:7px; font-size:14px; font-weight:700; cursor:pointer;">
                    <i class="fa-solid fa-user-plus"></i> Tenant aanmaken & inloggegevens genereren
                </button>
            </form>
        </div>
    </div>

    <div class="po-grid">
        <section class="po-card">
            <h2>Nieuwe Tenant</h2>
            <div class="body">
                <form method="POST">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo po_h($csrfToken); ?>">
                    <input type="hidden" name="action" value="create_tenant">

                    <label for="tenant_naam">Tenantnaam</label>
                    <input id="tenant_naam" name="tenant_naam" required placeholder="Bijv. Pilot Transport">

                    <label for="tenant_slug">Slug</label>
                    <input id="tenant_slug" name="tenant_slug" placeholder="Bijv. pilot_transport">

                    <label for="tenant_status">Status</label>
                    <select id="tenant_status" name="tenant_status">
                        <option value="active">active</option>
                        <option value="inactive">inactive</option>
                    </select>

                    <button type="submit">Tenant aanmaken</button>
                </form>
            </div>
        </section>

        <section class="po-card">
            <h2>Nieuwe Tenant Admin</h2>
            <div class="body">
                <form method="POST">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo po_h($csrfToken); ?>">
                    <input type="hidden" name="action" value="create_tenant_admin">

                    <label for="admin_tenant_id">Tenant</label>
                    <select id="admin_tenant_id" name="admin_tenant_id" required>
                        <option value="">-- Kies tenant --</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo (int) $tenant['id']; ?>">
                                <?php echo po_h($tenant['naam'] . ' (' . $tenant['slug'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="admin_naam">Volledige naam</label>
                    <input id="admin_naam" name="admin_naam" required>

                    <label for="admin_email">E-mail</label>
                    <input id="admin_email" name="admin_email" type="email" required>

                    <label for="admin_password">Wachtwoord</label>
                    <input id="admin_password" name="admin_password" type="password" required minlength="10">

                    <label style="display:flex; align-items:center; gap:8px; text-transform:none; font-size:13px;">
                        <input type="checkbox" name="admin_actief" checked style="width:auto; margin:0;">
                        Actief
                    </label>

                    <button type="submit">Tenant admin aanmaken</button>
                </form>
            </div>
        </section>
    </div>

    <section class="po-card" style="margin-top:20px; border:2px solid #0d9488;">
        <h2 style="background:linear-gradient(135deg,#0f766e,#0d9488); color:#fff; border-radius:6px 6px 0 0;">
            <i class="fa-solid fa-handshake"></i> Coöp Dagtochten — netwerken
        </h2>
        <div class="body">
            <div class="po-grid">
                <div>
                    <h3 style="margin:0 0 12px; font-size:14px; color:#003366;">Nieuw netwerk</h3>
                    <form method="POST">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo po_h($csrfToken); ?>">
                        <input type="hidden" name="action" value="create_reis_netwerk">

                        <label for="rn_naam">Netwerknaam</label>
                        <input id="rn_naam" name="rn_naam" required placeholder="Bijv. CoachTravel Trio">

                        <label for="rn_slug">Slug</label>
                        <input id="rn_slug" name="rn_slug" placeholder="coachtravel_trio">

                        <label for="rn_leiding_tenant_id">Leiding (beheert reizen)</label>
                        <select id="rn_leiding_tenant_id" name="rn_leiding_tenant_id" required>
                            <option value="">-- Kies tenant --</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo (int) $tenant['id']; ?>">
                                    <?php echo po_h($tenant['naam'] . ' (' . $tenant['slug'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">Netwerk aanmaken</button>
                    </form>
                </div>

                <div>
                    <h3 style="margin:0 0 12px; font-size:14px; color:#003366;">Partner toevoegen</h3>
                    <form method="POST">
                        <input type="hidden" name="auth_csrf_token" value="<?php echo po_h($csrfToken); ?>">
                        <input type="hidden" name="action" value="add_reis_netwerk_partner">

                        <label for="rp_netwerk_id">Netwerk</label>
                        <select id="rp_netwerk_id" name="rp_netwerk_id" required>
                            <option value="">-- Kies netwerk --</option>
                            <?php foreach ($reisNetwerken as $netwerk): ?>
                                <option value="<?php echo (int) $netwerk['id']; ?>">
                                    <?php echo po_h($netwerk['naam']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="rp_tenant_id">Tenant</label>
                        <select id="rp_tenant_id" name="rp_tenant_id" required>
                            <option value="">-- Kies tenant --</option>
                            <?php foreach ($tenants as $tenant): ?>
                                <option value="<?php echo (int) $tenant['id']; ?>">
                                    <?php echo po_h($tenant['naam'] . ' (' . $tenant['slug'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="rp_rol">Rol</label>
                        <select id="rp_rol" name="rp_rol">
                            <option value="partner">Partner (alleen inzage)</option>
                            <option value="leider">Leider (volledig beheer)</option>
                        </select>

                        <label for="rp_label">Label <span style="font-weight:400; color:#aaa;">(optioneel)</span></label>
                        <input id="rp_label" name="rp_label" placeholder="Bijv. Berkhout Reizen">

                        <button type="submit">Partner koppelen</button>
                    </form>
                </div>
            </div>

            <?php if ($reisNetwerken !== []): ?>
            <div style="margin-top:24px;">
                <h3 style="margin:0 0 12px; font-size:14px; color:#003366;">Actieve netwerken</h3>
                <?php foreach ($reisNetwerken as $netwerk): ?>
                    <div style="border:1px solid #e5e7eb; border-radius:8px; padding:14px; margin-bottom:12px;">
                        <strong><?php echo po_h($netwerk['naam']); ?></strong>
                        <span style="color:#888; font-size:12px;">(<?php echo po_h($netwerk['slug']); ?>)</span>
                        <div style="font-size:12px; color:#666; margin:6px 0 10px;">
                            Leiding: <?php echo po_h((string) $netwerk['leiding_naam']); ?>
                        </div>
                        <table class="po-table">
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Rol</th>
                                    <th>Bewerken</th>
                                    <th>Inzage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($netwerkPartners[(int) $netwerk['id']] ?? [] as $partner): ?>
                                    <tr>
                                        <td><?php echo po_h((string) $partner['tenant_naam']); ?></td>
                                        <td><?php echo po_h((string) $partner['rol']); ?></td>
                                        <td><?php echo (int) $partner['mag_bewerken'] === 1 ? 'ja' : 'nee'; ?></td>
                                        <td><?php echo (int) $partner['mag_bekijken'] === 1 ? 'ja' : 'nee'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="margin:20px 0 0; color:#888; font-size:13px;">Nog geen netwerken. Maak eerst CoachTravel aan als tenant, dan een netwerk met CoachTravel als leider.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
