<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['platform_owner']);
require 'includes/db.php';
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

$success = '';
$error = '';

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
                    INSERT INTO users (tenant_id, email, wachtwoord_hash, volledige_naam, rol, actief)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$tenantId, $email, $passwordHash, $volledigeNaam, 'tenant_admin', $actief]);

                $success = 'Tenant admin succesvol aangemaakt.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$csrfToken = auth_get_csrf_token();

$tenants = $pdo->query("SELECT id, naam, slug, status FROM tenants ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$adminsStmt = $pdo->query("
    SELECT u.id, u.volledige_naam, u.email, u.actief, u.created_at, t.naam AS tenant_naam
    FROM users u
    LEFT JOIN tenants t ON t.id = u.tenant_id
    WHERE u.rol = 'tenant_admin'
    ORDER BY u.id DESC
    LIMIT 100
");
$tenantAdmins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);
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
    @media (max-width: 900px) { .po-grid { grid-template-columns: 1fr; } }
</style>

<div class="po-wrap">
    <h1 style="margin:0 0 14px; color:#003366;">Platform Owner</h1>
    <p style="margin:0 0 18px; color:#666;">Beheer tenants en maak tenant admins aan.</p>

    <?php if ($success !== ''): ?>
        <div class="po-msg ok"><?php echo po_h($success); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="po-msg err"><?php echo po_h($error); ?></div>
    <?php endif; ?>

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

    <div class="po-grid" style="margin-top:20px;">
        <section class="po-card">
            <h2>Tenants</h2>
            <div class="body">
                <table class="po-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naam</th>
                            <th>Slug</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr>
                                <td><?php echo (int) $tenant['id']; ?></td>
                                <td><?php echo po_h($tenant['naam']); ?></td>
                                <td><?php echo po_h($tenant['slug']); ?></td>
                                <td>
                                    <span class="badge <?php echo $tenant['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo po_h($tenant['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="po-card">
            <h2>Tenant Admins (laatste 100)</h2>
            <div class="body">
                <table class="po-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Naam</th>
                            <th>E-mail</th>
                            <th>Tenant</th>
                            <th>Actief</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenantAdmins as $admin): ?>
                            <tr>
                                <td><?php echo (int) $admin['id']; ?></td>
                                <td><?php echo po_h($admin['volledige_naam']); ?></td>
                                <td><?php echo po_h($admin['email']); ?></td>
                                <td><?php echo po_h((string) $admin['tenant_naam']); ?></td>
                                <td><?php echo (int) $admin['actief'] === 1 ? 'ja' : 'nee'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
