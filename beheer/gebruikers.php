<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'platform_owner']);
require 'includes/db.php';
include 'includes/header.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$isPlatformOwner = current_user_role() === 'platform_owner';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

function g_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function g_allowed_roles(bool $isPlatformOwner): array
{
    if ($isPlatformOwner) {
        return ['tenant_admin', 'planner_user'];
    }
    return ['planner_user'];
}

function g_can_manage_role(bool $isPlatformOwner, string $role): bool
{
    return in_array($role, g_allowed_roles($isPlatformOwner), true);
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $error = 'Sessie verlopen. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'create_user') {
                $volledigeNaam = trim((string) ($_POST['volledige_naam'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['wachtwoord'] ?? '');
                $rol = (string) ($_POST['rol'] ?? 'planner_user');
                $actief = isset($_POST['actief']) ? 1 : 0;

                if ($volledigeNaam === '' || $email === '' || $password === '') {
                    throw new RuntimeException('Naam, e-mail en wachtwoord zijn verplicht.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('E-mailadres is ongeldig.');
                }
                if (strlen($password) < 10) {
                    throw new RuntimeException('Wachtwoord moet minimaal 10 tekens hebben.');
                }
                if (!g_can_manage_role($isPlatformOwner, $rol)) {
                    throw new RuntimeException('Je mag deze rol niet aanmaken.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (tenant_id, email, wachtwoord_hash, volledige_naam, rol, actief)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tenantId, $email, $hash, $volledigeNaam, $rol, $actief]);

                $success = 'Gebruiker succesvol aangemaakt.';
            }

            if ($action === 'toggle_active') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                $nieuwActief = isset($_POST['nieuw_actief']) ? (int) $_POST['nieuw_actief'] : -1;
                if ($userId <= 0 || !in_array($nieuwActief, [0, 1], true)) {
                    throw new RuntimeException('Ongeldige gebruiker/status.');
                }

                $stmtGet = $pdo->prepare("SELECT id, rol FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtGet->execute([$userId, $tenantId]);
                $target = $stmtGet->fetch(PDO::FETCH_ASSOC);
                if (!$target) {
                    throw new RuntimeException('Gebruiker niet gevonden binnen tenant.');
                }
                if (!g_can_manage_role($isPlatformOwner, (string) $target['rol'])) {
                    throw new RuntimeException('Je mag deze gebruiker niet wijzigen.');
                }

                // Voorkom dat je je eigen account uitschakelt.
                if ($userId === $currentUserId && $nieuwActief === 0) {
                    throw new RuntimeException('Je kunt je eigen account niet deactiveren.');
                }

                $stmtUpd = $pdo->prepare("UPDATE users SET actief = ? WHERE id = ? AND tenant_id = ?");
                $stmtUpd->execute([$nieuwActief, $userId, $tenantId]);
                $success = 'Gebruikerstatus bijgewerkt.';
            }

            if ($action === 'reset_password') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                $nieuwWachtwoord = (string) ($_POST['nieuw_wachtwoord'] ?? '');
                if ($userId <= 0 || $nieuwWachtwoord === '') {
                    throw new RuntimeException('Gebruiker en nieuw wachtwoord zijn verplicht.');
                }
                if (strlen($nieuwWachtwoord) < 10) {
                    throw new RuntimeException('Nieuw wachtwoord moet minimaal 10 tekens hebben.');
                }

                $stmtGet = $pdo->prepare("SELECT id, rol FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmtGet->execute([$userId, $tenantId]);
                $target = $stmtGet->fetch(PDO::FETCH_ASSOC);
                if (!$target) {
                    throw new RuntimeException('Gebruiker niet gevonden binnen tenant.');
                }
                if (!g_can_manage_role($isPlatformOwner, (string) $target['rol'])) {
                    throw new RuntimeException('Je mag dit wachtwoord niet resetten.');
                }

                $hash = password_hash($nieuwWachtwoord, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("UPDATE users SET wachtwoord_hash = ? WHERE id = ? AND tenant_id = ?");
                $stmtUpd->execute([$hash, $userId, $tenantId]);
                $success = 'Wachtwoord succesvol gereset.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Dit e-mailadres bestaat al.';
            } else {
                $error = 'Databasefout: ' . $e->getMessage();
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$allowedRoles = g_allowed_roles($isPlatformOwner);
$placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
$params = array_merge([$tenantId], $allowedRoles);

$stmtUsers = $pdo->prepare("
    SELECT id, volledige_naam, email, rol, actief, laatste_login_at, created_at
    FROM users
    WHERE tenant_id = ? AND rol IN ($placeholders)
    ORDER BY rol ASC, volledige_naam ASC
");
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = auth_get_csrf_token();
?>

<style>
    .g-wrap { max-width: 1200px; margin: 20px auto; padding: 0 20px; font-family: Arial, sans-serif; }
    .g-card { background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:20px; }
    .g-card h2 { margin:0; padding:14px 16px; border-bottom:1px solid #eee; color:#003366; font-size:16px; }
    .g-body { padding:16px; }
    .g-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .g-msg { padding:10px 12px; border-radius:6px; margin-bottom:14px; font-weight:bold; }
    .ok { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
    .err { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
    label { display:block; margin-bottom:6px; font-size:12px; font-weight:bold; color:#555; text-transform:uppercase; }
    input, select { width:100%; padding:9px; margin-bottom:12px; border:1px solid #ccc; border-radius:4px; }
    button { background:#003366; color:white; border:none; padding:9px 12px; border-radius:4px; font-weight:bold; cursor:pointer; }
    button:hover { background:#00264d; }
    table { width:100%; border-collapse: collapse; font-size:13px; }
    th, td { border:1px solid #e5e7eb; padding:8px; text-align:left; vertical-align: middle; }
    th { background:#f8fafc; color:#374151; }
    .badge { padding:2px 8px; border-radius:12px; font-size:11px; font-weight:bold; }
    .active { background:#dcfce7; color:#166534; }
    .inactive { background:#fee2e2; color:#991b1b; }
    .tools { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .tools form { margin:0; }
    .btn-gray { background:#6b7280; }
    .btn-orange { background:#d97706; }
    @media (max-width: 900px) { .g-grid { grid-template-columns: 1fr; } }
</style>

<div class="g-wrap">
    <h1 style="margin:0 0 14px; color:#003366;">Gebruikersbeheer</h1>
    <p style="margin:0 0 18px; color:#666;">
        Tenant: <strong><?php echo g_h(current_tenant_name() ?: current_tenant_slug()); ?></strong>
    </p>

    <?php if ($success !== ''): ?>
        <div class="g-msg ok"><?php echo g_h($success); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="g-msg err"><?php echo g_h($error); ?></div>
    <?php endif; ?>

    <section class="g-card">
        <h2>Nieuwe gebruiker</h2>
        <div class="g-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="auth_csrf_token" value="<?php echo g_h($csrfToken); ?>">
                <input type="hidden" name="action" value="create_user">

                <div class="g-grid">
                    <div>
                        <label for="volledige_naam">Volledige naam</label>
                        <input id="volledige_naam" name="volledige_naam" required>
                    </div>
                    <div>
                        <label for="email">E-mailadres</label>
                        <input id="email" type="email" name="email" required>
                    </div>
                </div>

                <div class="g-grid">
                    <div>
                        <label for="wachtwoord">Wachtwoord</label>
                        <input id="wachtwoord" type="password" name="wachtwoord" minlength="10" required>
                    </div>
                    <div>
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol">
                            <?php if ($isPlatformOwner): ?>
                                <option value="tenant_admin">tenant_admin</option>
                            <?php endif; ?>
                            <option value="planner_user" selected>planner_user</option>
                        </select>
                    </div>
                </div>

                <label style="display:flex; align-items:center; gap:8px; text-transform:none; font-size:13px;">
                    <input type="checkbox" name="actief" checked style="width:auto; margin:0;">
                    Actief
                </label>

                <button type="submit">Gebruiker aanmaken</button>
            </form>
        </div>
    </section>

    <section class="g-card">
        <h2>Bestaande gebruikers</h2>
        <div class="g-body">
            <table>
                <thead>
                    <tr>
                        <th>Naam</th>
                        <th>E-mail</th>
                        <th>Rol</th>
                        <th>Status</th>
                        <th>Laatste login</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="6" style="text-align:center; color:#666;">Nog geen gebruikers gevonden.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo g_h($u['volledige_naam']); ?></td>
                        <td><?php echo g_h($u['email']); ?></td>
                        <td><?php echo g_h($u['rol']); ?></td>
                        <td>
                            <span class="badge <?php echo ((int) $u['actief'] === 1) ? 'active' : 'inactive'; ?>">
                                <?php echo ((int) $u['actief'] === 1) ? 'actief' : 'inactief'; ?>
                            </span>
                        </td>
                        <td><?php echo $u['laatste_login_at'] ? g_h($u['laatste_login_at']) : '-'; ?></td>
                        <td>
                            <div class="tools">
                                <form method="POST">
                                    <input type="hidden" name="auth_csrf_token" value="<?php echo g_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                    <input type="hidden" name="nieuw_actief" value="<?php echo ((int) $u['actief'] === 1) ? '0' : '1'; ?>">
                                    <button type="submit" class="btn-gray">
                                        <?php echo ((int) $u['actief'] === 1) ? 'Deactiveer' : 'Activeer'; ?>
                                    </button>
                                </form>

                                <form method="POST" onsubmit="return confirm('Wachtwoord resetten voor deze gebruiker?');">
                                    <input type="hidden" name="auth_csrf_token" value="<?php echo g_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                    <input type="password" name="nieuw_wachtwoord" placeholder="Nieuw wachtwoord" minlength="10" required style="width:150px; margin:0;">
                                    <button type="submit" class="btn-orange">Reset wachtwoord</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
