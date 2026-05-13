<?php
declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/calculatie_bijlagen.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(400);
    exit;
}

$calcId = (int) ($_POST['calculatie_id'] ?? 0);
if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Sessie ongeldig; vernieuw de pagina.'));
    exit;
}

if ($calcId <= 0) {
    header('Location: ../calculaties.php');
    exit;
}

$chk = $pdo->prepare('SELECT id FROM calculaties WHERE id = ? AND tenant_id = ? LIMIT 1');
$chk->execute([$calcId, $tenantId]);
if (!$chk->fetchColumn()) {
    http_response_code(403);
    exit;
}

$file = $_FILES['bijlage_pdf'] ?? null;
if (!$file || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Geen bestand of uploadfout.'));
    exit;
}

$maxBytes = 8 * 1024 * 1024;
$size = (int) ($file['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Bestand te groot (max. 8 MB).'));
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Ongeldige upload.'));
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp);
if ($mime !== 'application/pdf') {
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Alleen PDF-bestanden zijn toegestaan.'));
    exit;
}

$origName = (string) ($file['name'] ?? 'bijlage.pdf');
if (function_exists('mb_substr')) {
    $origName = mb_substr($origName, 0, 255, 'UTF-8') ?: 'bijlage.pdf';
} else {
    $origName = substr($origName, 0, 255) ?: 'bijlage.pdf';
}

$stored = bin2hex(random_bytes(16)) . '.pdf';
$target = null;

try {
    calculatie_bijlagen_ensure_tenant_dir($tenantId);
    $target = calculatie_bijlagen_tenant_dir($tenantId) . '/' . $stored;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('move_uploaded_file');
    }
    $ins = $pdo->prepare(
        'INSERT INTO calculatie_bijlagen (tenant_id, calculatie_id, original_name, stored_filename, mime, file_size)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$tenantId, $calcId, $origName, $stored, 'application/pdf', $size]);
} catch (Throwable $e) {
    if ($target !== null && is_file($target)) {
        @unlink($target);
    }
    header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_err=1&bijlage_msg=' . rawurlencode('Opslaan mislukt (database of schijf).'));
    exit;
}

header('Location: calculaties_bewerken.php?id=' . $calcId . '&bijlage_ok=1#bijlagen');
exit;
