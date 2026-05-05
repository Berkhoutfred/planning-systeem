<?php
// Bestand: chauffeur/index.php
// VERSIE: Chauffeurs App - Tenant-safe login (chauffeur + tenant_slug + pin)

declare(strict_types=1);

session_start();

require '../beheer/includes/db.php';

/**
 * @return array{id: int, slug: string}
 */
function chauffeur_login_resolve_tenant(PDO $pdo): array
{
    $slug = trim((string) ($_POST['tenant_slug'] ?? $_GET['tenant'] ?? ''));
    if ($slug === '') {
        $slug = current_tenant_slug();
    }
    $stmt = $pdo->prepare("SELECT id, slug FROM tenants WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Organisatie niet gevonden of niet actief.');
    }

    return ['id' => (int) $row['id'], 'slug' => (string) $row['slug']];
}

$foutmelding = '';
$tenantSlugDisplay = '';

try {
    $tenantCtx = chauffeur_login_resolve_tenant($pdo);
    $loginTenantId = $tenantCtx['id'];
    $tenantSlugDisplay = $tenantCtx['slug'];
} catch (Throwable $e) {
    die('<p style="font-family:sans-serif;padding:24px;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedSlug = trim((string) ($_POST['tenant_slug'] ?? ''));
    if ($postedSlug === '' || $postedSlug !== $tenantSlugDisplay) {
        $foutmelding = 'Ongeldige aanmeldpoging. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $chauffeur_id = (int) ($_POST['chauffeur_id'] ?? 0);
        $pincode = trim((string) ($_POST['pincode'] ?? ''));

        if ($chauffeur_id > 0 && $pincode !== '') {
            $stmt = $pdo->prepare(
                'SELECT id, voornaam, achternaam, tenant_id FROM chauffeurs WHERE id = ? AND pincode = ? AND archief = 0 AND tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$chauffeur_id, $pincode, $loginTenantId]);
            $chauffeur = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($chauffeur) {
                $_SESSION['chauffeur_id'] = (int) $chauffeur['id'];
                $_SESSION['chauffeur_naam'] = (string) $chauffeur['voornaam'];
                $_SESSION['chauffeur_tenant_id'] = (int) $chauffeur['tenant_id'];
                header('Location: dashboard.php');
                exit;
            }
            $foutmelding = 'Pincode onjuist of chauffeur hoort niet bij deze organisatie.';
        } else {
            $foutmelding = 'Vul a.u.b. een pincode in.';
        }
    }
}

$stmt_chauf = $pdo->prepare(
    'SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? AND archief = 0 ORDER BY voornaam ASC, achternaam ASC'
);
$stmt_chauf->execute([$loginTenantId]);
$chauffeurs = $stmt_chauf->fetchAll(PDO::FETCH_ASSOC);

$tenantNaam = '';
$tn = $pdo->prepare('SELECT naam FROM tenants WHERE id = ? LIMIT 1');
$tn->execute([$loginTenantId]);
if ($rowTn = $tn->fetch(PDO::FETCH_ASSOC)) {
    $tenantNaam = (string) $rowTn['naam'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Berkhout Reizen - Chauffeurs App</title>
    
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: #f4f7f6;
            margin: 0; 
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; 
        }
        .login-box {
            background: white;
            padding: 50px 30px 30px 30px; 
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .logo-img {
            max-width: 330px; 
            width: 100%;      
            height: auto;
            margin-bottom: 30px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .tenant-badge {
            font-size: 13px;
            color: #555;
            margin-bottom: 16px;
            padding: 8px 12px;
            background: #eef2f7;
            border-radius: 8px;
            border: 1px solid #dde3ea;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        select, input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box; 
            background: #f9f9f9;
        }
        select:focus, input:focus {
            border-color: #003366;
            outline: none;
            background: #fff;
        }
        button {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(40,167,69,0.3);
        }
        button:active {
            background: #218838;
            transform: translateY(1px);
        }
        .fout {
            background: #fee2e2;
            color: #dc3545;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            body { padding: 10px; }
            .login-box { 
                padding-top: 40px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }
        }
    </style>
</head>
<body>

    <div class="login-box">
        <img src="../beheer/images/berkhout_logo.png" alt="Berkhout Reizen" class="logo-img">
        
        <h2 style="margin-top: 0; color: #333; font-size: 22px;">Chauffeur Inlog</h2>
        <?php if ($tenantNaam !== ''): ?>
            <div class="tenant-badge"><?php echo htmlspecialchars($tenantNaam, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <?php if ($foutmelding !== ''): ?>
            <div class="fout"><?php echo htmlspecialchars($foutmelding, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="tenant_slug" value="<?php echo htmlspecialchars($tenantSlugDisplay, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Wie ben jij?</label>
                <select name="chauffeur_id" required>
                    <option value="">-- Selecteer je naam --</option>
                    <?php foreach ($chauffeurs as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['voornaam'] . ' ' . $c['achternaam'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Pincode:</label>
                <input type="password" name="pincode" pattern="\d*" inputmode="numeric" placeholder="Voer je code in" required>
            </div>
            
            <button type="submit">Inloggen ➡️</button>
        </form>
    </div>

</body>
</html>
