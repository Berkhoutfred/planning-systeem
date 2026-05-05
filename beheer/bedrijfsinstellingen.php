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

$csrf = auth_generate_csrf_token();
$inst = tenant_instellingen_get($pdo, $tenantId);

include 'includes/header.php';
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="max-w-5xl mx-auto mt-8 mb-10 bg-white rounded-xl shadow-sm border border-slate-200">
    <div class="px-7 py-5 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Bedrijfsinstellingen</h1>
            <p class="text-sm text-slate-500 mt-1">Tenant #<?php echo (int) $tenantId; ?> - stel je BusAI bedrijfsgegevens in.</p>
        </div>
        <a href="dashboard.php" class="text-sm px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200">Terug naar dashboard</a>
    </div>

    <?php if ($melding !== ''): ?>
        <div class="mx-7 mt-5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm font-medium">
            <?php echo htmlspecialchars($melding, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($fout !== ''): ?>
        <div class="mx-7 mt-5 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm font-medium">
            <?php echo htmlspecialchars($fout, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="px-7 py-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Bedrijfsnaam *</span>
            <input name="bedrijfsnaam" required maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['bedrijfsnaam'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">E-mailadres</span>
            <input type="email" name="email" maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['email'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block md:col-span-2">
            <span class="text-sm font-semibold text-slate-700">Adres</span>
            <input name="adres" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['adres'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Postcode</span>
            <input name="postcode" maxlength="32" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['postcode'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Plaats</span>
            <input name="plaats" maxlength="120" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['plaats'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Telefoon</span>
            <input name="telefoon" maxlength="60" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['telefoon'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">KVK-nummer</span>
            <input name="kvk_nummer" maxlength="64" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['kvk_nummer'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">Boekhoud/BCC e-mailadres (optioneel)</span>
            <input type="email" name="boekhoud_email" maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['boekhoud_email'], ENT_QUOTES, 'UTF-8'); ?>">
            <span class="mt-1 block text-xs text-slate-500">Wordt automatisch als BCC gebruikt bij factuurmails voor deze tenant.</span>
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">BTW-nummer</span>
            <input name="btw_nummer" maxlength="64" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['btw_nummer'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <label class="block">
            <span class="text-sm font-semibold text-slate-700">IBAN</span>
            <input name="iban" maxlength="64" class="mt-1 w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" value="<?php echo htmlspecialchars((string) $inst['iban'], ENT_QUOTES, 'UTF-8'); ?>">
        </label>

        <div class="block md:col-span-2">
            <span class="text-sm font-semibold text-slate-700">Logo upload</span>
            <input type="file" name="logo_bestand" accept=".png,.jpg,.jpeg,.webp,.gif" class="mt-2 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-slate-200">
            <?php if (!empty($inst['logo_pad'])): ?>
                <div class="mt-3 flex items-center gap-3">
                    <img src="<?php echo htmlspecialchars((string) $inst['logo_pad'], ENT_QUOTES, 'UTF-8'); ?>" alt="Huidig logo" class="h-12 w-auto rounded border border-slate-200">
                    <span class="text-xs text-slate-500"><?php echo htmlspecialchars((string) $inst['logo_pad'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="md:col-span-2 flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="dashboard.php" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Annuleren</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Opslaan</button>
        </div>
    </form>

    <?php if ($tenantId === 2): ?>
        <div class="px-7 pb-7">
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-rose-900">Schone lei testomgeving</h2>
                    <p class="text-sm text-rose-700">Wist alle ritten, calculaties, sales-dossiers en factuurdata van tenant 2. Klanten en tenant-instellingen blijven intact.</p>
                </div>
                <form method="post" action="reset_testdata.php" onsubmit="return confirm('Weet je zeker dat je alle test-ritten/calculaties/sales/facturen wilt wissen voor tenant 2?');">
                    <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 whitespace-nowrap">Reset tenant 2</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
