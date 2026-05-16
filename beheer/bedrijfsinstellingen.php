<?php
declare(strict_types=1);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once __DIR__ . '/includes/tenant_instellingen_db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('Tenant context ontbreekt.');
}

tenant_instellingen_bootstrap($pdo);

$melding = '';
$fout = '';
if (isset($_GET['reset_ok']) && $_GET['reset_ok'] === '1') {
    $melding = 'Testdata is opgeschoond voor tenant 2. Klanten en tenant-instellingen zijn behouden.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $fout = 'Sessie verlopen. Vernieuw de pagina.';
    } else {
        $current = tenant_instellingen_get($pdo, $tenantId);
        $logoPad = (string) ($current['logo_pad'] ?? '');

        $fields = [
            'bedrijfsnaam' => trim((string) ($_POST['bedrijfsnaam'] ?? '')),
            'adres' => trim((string) ($_POST['adres'] ?? '')),
            'postcode' => trim((string) ($_POST['postcode'] ?? '')),
            'plaats' => trim((string) ($_POST['plaats'] ?? '')),
            'telefoon' => trim((string) ($_POST['telefoon'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'boekhoud_email' => trim((string) ($_POST['boekhoud_email'] ?? '')),
            'kvk_nummer' => trim((string) ($_POST['kvk_nummer'] ?? '')),
            'btw_nummer' => trim((string) ($_POST['btw_nummer'] ?? '')),
            'iban' => trim((string) ($_POST['iban'] ?? '')),
        ];

        if ($fields['bedrijfsnaam'] === '') {
            $fout = 'Bedrijfsnaam is verplicht.';
        } elseif ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $fout = 'E-mailadres is ongeldig.';
        } elseif ($fields['boekhoud_email'] !== '' && !filter_var($fields['boekhoud_email'], FILTER_VALIDATE_EMAIL)) {
            $fout = 'Boekhoud/BCC e-mailadres is ongeldig.';
        } else {
            if (isset($_FILES['logo_bestand']) && is_array($_FILES['logo_bestand']) && ($_FILES['logo_bestand']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $err = (int) ($_FILES['logo_bestand']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($err !== UPLOAD_ERR_OK) {
                    $fout = 'Uploaden van logo is mislukt.';
                } else {
                    $tmp = (string) ($_FILES['logo_bestand']['tmp_name'] ?? '');
                    $origName = (string) ($_FILES['logo_bestand']['name'] ?? '');
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
                    if (!in_array($ext, $allowed, true)) {
                        $fout = 'Alleen PNG, JPG, JPEG, WEBP of GIF toegestaan.';
                    } else {
                        $uploadDirAbs = __DIR__ . '/uploads/tenant_logos';
                        if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0775, true) && !is_dir($uploadDirAbs)) {
                            $fout = 'Kon uploadmap niet aanmaken.';
                        } else {
                            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                            $safeBase = $safeBase !== '' ? $safeBase : 'logo';
                            $fileName = 'tenant_' . $tenantId . '_' . $safeBase . '_' . time() . '.' . $ext;
                            $targetAbs = $uploadDirAbs . '/' . $fileName;
                            if (!move_uploaded_file($tmp, $targetAbs)) {
                                $fout = 'Kon logo niet opslaan.';
                            } else {
                                $logoPad = 'uploads/tenant_logos/' . $fileName;
                            }
                        }
                    }
                }
            }
        }

        if ($fout === '') {
            $sql = 'UPDATE tenant_instellingen
                    SET bedrijfsnaam = ?, adres = ?, postcode = ?, plaats = ?, telefoon = ?, email = ?,
                        boekhoud_email = ?, kvk_nummer = ?, btw_nummer = ?, iban = ?, logo_pad = ?
                    WHERE tenant_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $fields['bedrijfsnaam'],
                $fields['adres'],
                $fields['postcode'],
                $fields['plaats'],
                $fields['telefoon'],
                $fields['email'],
                $fields['boekhoud_email'],
                $fields['kvk_nummer'],
                $fields['btw_nummer'],
                $fields['iban'],
                $logoPad,
                $tenantId,
            ]);
            $melding = 'Bedrijfsinstellingen zijn opgeslagen.';
        }
    }
}

$csrf = auth_get_csrf_token();
$inst = tenant_instellingen_get($pdo, $tenantId);

include 'includes/header.php';
?>
<style>
.bi-card {
    max-width: 860px;
    margin: 28px auto 40px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    font-family: 'Segoe UI', sans-serif;
}
.bi-card-header {
    padding: 20px 28px 18px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.bi-card-header h1 {
    margin: 0 0 3px;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}
.bi-card-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}
.bi-back-btn {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 7px;
    background: #f1f5f9;
    color: #475569;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    border: 1px solid #e2e8f0;
    transition: background 0.15s;
}
.bi-back-btn:hover { background: #e2e8f0; }
.bi-alert {
    margin: 16px 28px 0;
    padding: 11px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
}
.bi-alert-ok  { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
.bi-alert-err { background: #fff1f2; border: 1px solid #fca5a5; color: #991b1b; }
.bi-form {
    padding: 24px 28px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 24px;
}
@media (max-width: 600px) { .bi-form { grid-template-columns: 1fr; } }
.bi-field { display: flex; flex-direction: column; }
.bi-field.full { grid-column: 1 / -1; }
.bi-label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 5px;
    letter-spacing: 0.01em;
}
.bi-input {
    padding: 8px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    color: #1e293b;
    background: #fff;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.bi-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.bi-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
}
.bi-form-footer {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding-top: 6px;
    border-top: 1px solid #e2e8f0;
    margin-top: 4px;
}
.bi-btn-cancel {
    padding: 8px 18px;
    border-radius: 7px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s;
}
.bi-btn-cancel:hover { background: #f8fafc; }
.bi-btn-save {
    padding: 8px 22px;
    border-radius: 7px;
    border: none;
    background: #4f46e5;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.bi-btn-save:hover { background: #4338ca; }
.bi-logo-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}
.bi-logo-preview img {
    height: 48px;
    width: auto;
    border-radius: 5px;
    border: 1px solid #e2e8f0;
}
.bi-logo-preview span { font-size: 12px; color: #94a3b8; }
.bi-danger-zone {
    margin: 0 28px 28px;
    padding: 16px 20px;
    border-radius: 8px;
    background: #fff5f5;
    border: 1px solid #fca5a5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.bi-danger-zone h2 { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #7f1d1d; }
.bi-danger-zone p  { margin: 0; font-size: 13px; color: #b91c1c; }
.bi-btn-danger {
    padding: 8px 18px;
    border-radius: 7px;
    border: none;
    background: #dc2626;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
}
.bi-btn-danger:hover { background: #b91c1c; }
</style>

<div class="bi-card">
    <div class="bi-card-header">
        <div>
            <h1>Bedrijfsinstellingen</h1>
            <p>Tenant #<?php echo (int) $tenantId; ?> &mdash; stel je BusAI bedrijfsgegevens in.</p>
        </div>
        <a href="dashboard.php" class="bi-back-btn">&larr; Dashboard</a>
    </div>

    <?php if ($melding !== ''): ?>
        <div class="bi-alert bi-alert-ok"><?php echo htmlspecialchars($melding, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($fout !== ''): ?>
        <div class="bi-alert bi-alert-err"><?php echo htmlspecialchars($fout, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="bi-form">
        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="bi-field">
            <label class="bi-label">Bedrijfsnaam *</label>
            <input class="bi-input" name="bedrijfsnaam" required maxlength="190"
                   value="<?php echo htmlspecialchars((string) $inst['bedrijfsnaam'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">E-mailadres</label>
            <input class="bi-input" type="email" name="email" maxlength="190"
                   value="<?php echo htmlspecialchars((string) $inst['email'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field full">
            <label class="bi-label">Adres</label>
            <input class="bi-input" name="adres" maxlength="255"
                   value="<?php echo htmlspecialchars((string) $inst['adres'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">Postcode</label>
            <input class="bi-input" name="postcode" maxlength="32"
                   value="<?php echo htmlspecialchars((string) $inst['postcode'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">Plaats</label>
            <input class="bi-input" name="plaats" maxlength="120"
                   value="<?php echo htmlspecialchars((string) $inst['plaats'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">Telefoon</label>
            <input class="bi-input" name="telefoon" maxlength="60"
                   value="<?php echo htmlspecialchars((string) $inst['telefoon'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">KVK-nummer</label>
            <input class="bi-input" name="kvk_nummer" maxlength="64"
                   value="<?php echo htmlspecialchars((string) $inst['kvk_nummer'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">BTW-nummer</label>
            <input class="bi-input" name="btw_nummer" maxlength="64"
                   value="<?php echo htmlspecialchars((string) $inst['btw_nummer'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field">
            <label class="bi-label">IBAN</label>
            <input class="bi-input" name="iban" maxlength="64"
                   value="<?php echo htmlspecialchars((string) $inst['iban'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="bi-field full">
            <label class="bi-label">Boekhoud / BCC e-mailadres <span style="font-weight:400;color:#94a3b8;">(optioneel)</span></label>
            <input class="bi-input" type="email" name="boekhoud_email" maxlength="190"
                   value="<?php echo htmlspecialchars((string) $inst['boekhoud_email'], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="bi-hint">Wordt automatisch als BCC toegevoegd bij factuurmails voor deze tenant.</span>
        </div>

        <div class="bi-field full">
            <label class="bi-label">Logo uploaden <span style="font-weight:400;color:#94a3b8;">(.png, .jpg, .webp, .gif)</span></label>
            <input type="file" name="logo_bestand" accept=".png,.jpg,.jpeg,.webp,.gif"
                   style="font-size:13px; color:#475569; margin-top:2px;">
            <?php if (!empty($inst['logo_pad'])): ?>
                <div class="bi-logo-preview">
                    <img src="<?php echo htmlspecialchars((string) $inst['logo_pad'], ENT_QUOTES, 'UTF-8'); ?>" alt="Huidig logo">
                    <span><?php echo htmlspecialchars((string) $inst['logo_pad'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="bi-form-footer">
            <a href="dashboard.php" class="bi-btn-cancel">Annuleren</a>
            <button type="submit" class="bi-btn-save">Opslaan</button>
        </div>
    </form>

    <?php if ($tenantId === 2): ?>
        <div class="bi-danger-zone">
            <div>
                <h2>Schone lei testomgeving</h2>
                <p>Wist alle ritten, calculaties, sales-dossiers en factuurdata van tenant 2. Klanten en instellingen blijven intact.</p>
            </div>
            <form method="post" action="reset_testdata.php"
                  onsubmit="return confirm('Weet je zeker dat je alle test-ritten/calculaties/sales/facturen wilt wissen voor tenant 2?');">
                <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="bi-btn-danger">Reset tenant 2</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
