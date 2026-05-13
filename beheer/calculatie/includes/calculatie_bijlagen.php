<?php
declare(strict_types=1);

/**
 * PDF-bijlagen bij een calculatie: opslag onder beheer/uploads/calculatie_bijlagen/{tenant_id}/.
 */

function calculatie_bijlagen_base_dir(): string
{
    return dirname(__DIR__) . '/uploads/calculatie_bijlagen';
}

function calculatie_bijlagen_tenant_dir(int $tenantId): string
{
    return calculatie_bijlagen_base_dir() . '/' . $tenantId;
}

function calculatie_bijlagen_ensure_tenant_dir(int $tenantId): void
{
    $d = calculatie_bijlagen_tenant_dir($tenantId);
    if (is_dir($d)) {
        return;
    }
    if (!@mkdir($d, 0750, true) && !is_dir($d)) {
        throw new RuntimeException('Kon uploadmap niet aanmaken.');
    }
}

/**
 * @return list<array{id:int, original_name:string, mime:string, file_size:int, created_at:string}>
 */
function calculatie_bijlagen_fetch_list(PDO $pdo, int $tenantId, int $calculatieId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, original_name, mime, file_size, created_at
         FROM calculatie_bijlagen
         WHERE tenant_id = ? AND calculatie_id = ?
         ORDER BY id DESC'
    );
    $stmt->execute([$tenantId, $calculatieId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array{id:int, tenant_id:int, calculatie_id:int, original_name:string, stored_filename:string, mime:string, file_size:int}|null
 */
function calculatie_bijlage_fetch_by_id(PDO $pdo, int $tenantId, int $bijlageId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, tenant_id, calculatie_id, original_name, stored_filename, mime, file_size
         FROM calculatie_bijlagen
         WHERE id = ? AND tenant_id = ?
         LIMIT 1'
    );
    $stmt->execute([$bijlageId, $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function calculatie_bijlage_delete(PDO $pdo, int $tenantId, int $bijlageId): bool
{
    $row = calculatie_bijlage_fetch_by_id($pdo, $tenantId, $bijlageId);
    if ($row === null) {
        return false;
    }
    $path = calculatie_bijlagen_tenant_dir($tenantId) . '/' . $row['stored_filename'];
    if (is_file($path)) {
        @unlink($path);
    }
    $del = $pdo->prepare('DELETE FROM calculatie_bijlagen WHERE id = ? AND tenant_id = ? LIMIT 1');
    $del->execute([$bijlageId, $tenantId]);

    return $del->rowCount() > 0;
}
