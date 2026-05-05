<?php
// Bestand: beheer/includes/tenant_secrets.php
// CRUD op tenant_secrets met AES-256-GCM (zie tenant_crypto.php).
//
// Verwachte tabel (aanpassen indien jouw migratie afwijkt):
// CREATE TABLE tenant_secrets (
//   tenant_id INT UNSIGNED NOT NULL,
//   secret_name VARCHAR(128) NOT NULL,
//   ciphertext TEXT NOT NULL,
//   updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//   PRIMARY KEY (tenant_id, secret_name)
// );

require_once __DIR__ . '/tenant_crypto.php';

if (!function_exists('tenant_secret_get')) {
    /**
     * Haalt ontsleutelde waarde op, of null als niet aanwezig.
     */
    function tenant_secret_get(PDO $pdo, int $tenantId, string $secretName): ?string
    {
        if ($tenantId <= 0 || $secretName === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT ciphertext FROM tenant_secrets WHERE tenant_id = ? AND secret_name = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $secretName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['ciphertext'] === null || $row['ciphertext'] === '') {
            return null;
        }

        return tenant_secret_decrypt_plaintext((string) $row['ciphertext']);
    }
}

if (!function_exists('tenant_secret_set')) {
    /**
     * Slaat geheim op (upsert). Lege string wist het geheim.
     */
    function tenant_secret_set(PDO $pdo, int $tenantId, string $secretName, string $value): void
    {
        if ($tenantId <= 0 || $secretName === '') {
            throw new InvalidArgumentException('tenant_id en secret_name zijn verplicht.');
        }

        if ($value === '') {
            tenant_secret_delete($pdo, $tenantId, $secretName);
            return;
        }

        $cipher = tenant_secret_encrypt_plaintext($value);
        $sql = 'INSERT INTO tenant_secrets (tenant_id, secret_name, ciphertext)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE ciphertext = VALUES(ciphertext), updated_at = CURRENT_TIMESTAMP';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId, $secretName, $cipher]);
    }
}

if (!function_exists('tenant_secret_delete')) {
    function tenant_secret_delete(PDO $pdo, int $tenantId, string $secretName): void
    {
        if ($tenantId <= 0 || $secretName === '') {
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM tenant_secrets WHERE tenant_id = ? AND secret_name = ?');
        $stmt->execute([$tenantId, $secretName]);
    }
}
