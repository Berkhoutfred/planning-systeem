<?php
/**
 * Sales-pijplijn voor directe ritten (taxi): dossier naast calculatie-offertes.
 * Tabel wordt bij eerste gebruik aangemaakt (CREATE IF NOT EXISTS).
 */
declare(strict_types=1);

function sales_rit_dossiers_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sales_rit_dossiers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            heen_rit_id INT UNSIGNED NOT NULL,
            retour_rit_id INT UNSIGNED NULL DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'open\',
            afwijzings_reden VARCHAR(500) NULL,
            datum_prijs_gedeeld DATETIME NULL,
            datum_klant_akkoord DATETIME NULL,
            aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sales_tnt_heen (tenant_id, heen_rit_id),
            KEY idx_sales_tnt_status (tenant_id, status),
            KEY idx_sales_heen (heen_rit_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * @return int dossier-id
 */
function sales_rit_dossiers_insert(PDO $pdo, int $tenantId, int $heenRitId, ?int $retourRitId): int
{
    sales_rit_dossiers_ensure_schema($pdo);
    $st = $pdo->prepare(
        'INSERT INTO sales_rit_dossiers (tenant_id, heen_rit_id, retour_rit_id, status)
         VALUES (?, ?, ?, \'open\')'
    );
    $st->execute([$tenantId, $heenRitId, $retourRitId]);

    return (int) $pdo->lastInsertId();
}
