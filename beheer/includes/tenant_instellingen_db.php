<?php
/**
 * Tenant instellingen + bootstrap helper.
 */
declare(strict_types=1);

if (!function_exists('tenant_instellingen_ensure_schema')) {
    function tenant_instellingen_ensure_schema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tenant_instellingen (
                tenant_id INT UNSIGNED NOT NULL,
                bedrijfsnaam VARCHAR(190) NOT NULL DEFAULT '',
                adres VARCHAR(255) NULL DEFAULT NULL,
                postcode VARCHAR(32) NULL DEFAULT NULL,
                plaats VARCHAR(120) NULL DEFAULT NULL,
                telefoon VARCHAR(60) NULL DEFAULT NULL,
                email VARCHAR(190) NULL DEFAULT NULL,
                boekhoud_email VARCHAR(190) NULL DEFAULT NULL,
                kvk_nummer VARCHAR(64) NULL DEFAULT NULL,
                btw_nummer VARCHAR(64) NULL DEFAULT NULL,
                iban VARCHAR(64) NULL DEFAULT NULL,
                logo_pad VARCHAR(255) NULL DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Compatibel met oudere MySQL/MariaDB versies zonder "ADD COLUMN IF NOT EXISTS".
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName !== '') {
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?'
            );
            $chk->execute([$dbName, 'tenant_instellingen', 'boekhoud_email']);
            if ((int) $chk->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE tenant_instellingen ADD COLUMN boekhoud_email VARCHAR(190) NULL DEFAULT NULL AFTER email');
            }
        }
    }
}

if (!function_exists('tenant_instellingen_seed_defaults')) {
    function tenant_instellingen_seed_defaults(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO tenant_instellingen (tenant_id, bedrijfsnaam, plaats)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([1, 'Berkhout Reizen', 'Zutphen']);
        $stmt->execute([2, 'BusAI Testomgeving', 'Zutphen']);
    }
}

if (!function_exists('tenant_instellingen_ensure_core_tenants')) {
    function tenant_instellingen_ensure_core_tenants(PDO $pdo): void
    {
        // Zorg dat de twee basis-omgevingen bestaan voordat afhankelijke tabellen worden gevuld.
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO tenants (id, slug, naam, status)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([1, 'pilot_transport', 'Pilot Transport', 'active']);
        $stmt->execute([2, 'busai_testomgeving', 'BusAI Testomgeving', 'active']);
    }
}

if (!function_exists('tenant_instellingen_clone_klanten_1_to_2_if_empty')) {
    function tenant_instellingen_clone_klanten_1_to_2_if_empty(PDO $pdo): void
    {
        $tenantExistsStmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE id = ?');
        $tenantExistsStmt->execute([2]);
        if ((int) $tenantExistsStmt->fetchColumn() === 0) {
            // Zonder tenant #2 zou de clone op FK falen; sla in dat geval veilig over.
            return;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM klanten WHERE tenant_id = ?');
        $countStmt->execute([2]);
        if ((int) $countStmt->fetchColumn() > 0) {
            return;
        }

        $colsStmt = $pdo->query('SHOW COLUMNS FROM klanten');
        $columns = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($columns === []) {
            return;
        }

        $insertCols = [];
        $selectCols = [];
        foreach ($columns as $col) {
            $name = (string) ($col['Field'] ?? '');
            if ($name === '' || $name === 'id') {
                continue;
            }
            $insertCols[] = "`$name`";
            if ($name === 'tenant_id') {
                $selectCols[] = '2 AS `tenant_id`';
            } else {
                $selectCols[] = "`$name`";
            }
        }
        if ($insertCols === [] || $selectCols === []) {
            return;
        }

        $sql = 'INSERT INTO klanten (' . implode(', ', $insertCols) . ')
                SELECT ' . implode(', ', $selectCols) . '
                FROM klanten
                WHERE tenant_id = 1';
        $pdo->exec($sql);
    }
}

if (!function_exists('tenant_instellingen_bootstrap')) {
    function tenant_instellingen_bootstrap(PDO $pdo): void
    {
        tenant_instellingen_ensure_core_tenants($pdo);
        tenant_instellingen_ensure_schema($pdo);
        tenant_instellingen_seed_defaults($pdo);
        tenant_instellingen_clone_klanten_1_to_2_if_empty($pdo);
    }
}

if (!function_exists('tenant_instellingen_get')) {
    /**
     * @return array{
     *   tenant_id:int,
     *   bedrijfsnaam:string,
     *   adres:string,
     *   postcode:string,
     *   plaats:string,
     *   telefoon:string,
     *   email:string,
     *   boekhoud_email:string,
     *   kvk_nummer:string,
     *   btw_nummer:string,
     *   iban:string,
     *   logo_pad:string
     * }
     */
    function tenant_instellingen_get(PDO $pdo, int $tenantId): array
    {
        tenant_instellingen_bootstrap($pdo);

        $defaults = [
            'tenant_id' => $tenantId,
            'bedrijfsnaam' => $tenantId === 2 ? 'BusAI Testomgeving' : 'Berkhout Reizen',
            'adres' => '',
            'postcode' => '',
            'plaats' => '',
            'telefoon' => '',
            'email' => '',
            'boekhoud_email' => '',
            'kvk_nummer' => '',
            'btw_nummer' => '',
            'iban' => '',
            'logo_pad' => '',
        ];
        if ($tenantId <= 0) {
            return $defaults;
        }

        $stmt = $pdo->prepare('SELECT * FROM tenant_instellingen WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults;
        }

        foreach ($defaults as $k => $v) {
            if ($k === 'tenant_id') {
                $defaults[$k] = (int) ($row[$k] ?? $tenantId);
            } else {
                $defaults[$k] = trim((string) ($row[$k] ?? ''));
            }
        }
        return $defaults;
    }
}
