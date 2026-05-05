<?php
// Bestand: beheer/includes/tenant_settings.php
// Centrale merge: tenant_rekenvariabelen + legacy calculatie_instellingen.
//
// Verwachte tabel tenant_rekenvariabelen (minimaal):
//   tenant_id (PK), km_prijs_basis, starttarief, uurloon_basis
// Legacy calculatie_instellingen: uurloon_basis, touringcar_factor, winstmarge_perc, btw_nl, mwst_de, tenant_id

if (!function_exists('tenant_rekenvariabelen_ensure_row')) {
    function tenant_rekenvariabelen_ensure_row(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO tenant_rekenvariabelen (tenant_id) VALUES (?)'
        );
        $stmt->execute([$tenantId]);
    }
}

if (!function_exists('tenant_reken_try_sync_uurloon_from_legacy')) {
    /**
     * Eenmalig effect: als uurloon in rekenvariabelen nog leeg/0 is maar legacy wel een waarde heeft,
     * schrijf die door naar tenant_rekenvariabelen (uurloon hoort daar thuis na migratie).
     */
    function tenant_reken_try_sync_uurloon_from_legacy(PDO $pdo, int $tenantId, ?array $reken, ?array $legacy): void
    {
        if ($tenantId <= 0 || !$legacy || !isset($legacy['uurloon_basis'])) {
            return;
        }
        $legacyUur = (float) $legacy['uurloon_basis'];
        $current = isset($reken['uurloon_basis']) ? (float) $reken['uurloon_basis'] : null;
        $unset = $reken === null
            || !array_key_exists('uurloon_basis', $reken)
            || $reken['uurloon_basis'] === null
            || $reken['uurloon_basis'] === ''
            || abs($current ?? 0.0) < 0.00001;

        if (!$unset) {
            return;
        }
        if ($legacyUur < 0.00001) {
            return;
        }

        $upd = $pdo->prepare(
            'UPDATE tenant_rekenvariabelen SET uurloon_basis = ? WHERE tenant_id = ?'
        );
        $upd->execute([$legacyUur, $tenantId]);
    }
}

if (!function_exists('tenant_calculatie_instellingen_merged')) {
    /**
     * Eén associatieve array voor calculatie-UI en server-side logica.
     * Getallen uit tenant_rekenvariabelen vullen aan / overschrijven waar logisch;
     * percentages/factoren die (nog) alleen in calculatie_instellingen staan blijven daar vandaan komen.
     *
     * @return array{
     *   uurloon_basis: float,
     *   km_prijs_basis: float,
     *   starttarief: float,
     *   touringcar_factor: float,
     *   winstmarge_perc: float,
     *   btw_nl: float,
     *   mwst_de: float
     * }
     */
    function tenant_calculatie_instellingen_merged(PDO $pdo, int $tenantId): array
    {
        $defaults = [
            'uurloon_basis' => 35.0,
            'km_prijs_basis' => 0.0,
            'starttarief' => 0.0,
            'touringcar_factor' => 1.15,
            'winstmarge_perc' => 25.0,
            'btw_nl' => 9.0,
            'mwst_de' => 19.0,
        ];

        if ($tenantId <= 0) {
            return $defaults;
        }

        tenant_rekenvariabelen_ensure_row($pdo, $tenantId);

        $stmtLegacy = $pdo->prepare(
            'SELECT * FROM calculatie_instellingen WHERE tenant_id = ? LIMIT 1'
        );
        $stmtLegacy->execute([$tenantId]);
        $legacy = $stmtLegacy->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmtReken = $pdo->prepare(
            'SELECT * FROM tenant_rekenvariabelen WHERE tenant_id = ? LIMIT 1'
        );
        $stmtReken->execute([$tenantId]);
        $reken = $stmtReken->fetch(PDO::FETCH_ASSOC) ?: null;

        tenant_reken_try_sync_uurloon_from_legacy($pdo, $tenantId, $reken, $legacy);
        if ($reken !== null) {
            $stmtReken->execute([$tenantId]);
            $reken = $stmtReken->fetch(PDO::FETCH_ASSOC) ?: $reken;
        }

        // uurloon: voorkeur voor ingevulde waarde in tenant_rekenvariabelen; anders legacy calculatie_instellingen.
        // Let op: als INSERT voor rekenvariabelen een default uurloon (bv. 35) zet terwijl legacy afwijkt, voer eenmalig SQL uit
        // om reken.uurloon_basis te zetten — sync hieronder vult alleen NULL/0 aan vanuit legacy.
        $uurloon = $defaults['uurloon_basis'];
        $rekenUurCandidate = null;
        if ($reken !== null && isset($reken['uurloon_basis']) && $reken['uurloon_basis'] !== null && $reken['uurloon_basis'] !== '') {
            $rekenUurCandidate = (float) $reken['uurloon_basis'];
        }
        if ($rekenUurCandidate !== null && $rekenUurCandidate > 0.00001) {
            $uurloon = $rekenUurCandidate;
        } elseif ($legacy !== null && isset($legacy['uurloon_basis'])) {
            $uurloon = (float) $legacy['uurloon_basis'];
        }

        $km = $defaults['km_prijs_basis'];
        $start = $defaults['starttarief'];
        if ($reken !== null) {
            if (isset($reken['km_prijs_basis']) && $reken['km_prijs_basis'] !== null && $reken['km_prijs_basis'] !== '') {
                $km = (float) $reken['km_prijs_basis'];
            }
            if (isset($reken['starttarief']) && $reken['starttarief'] !== null && $reken['starttarief'] !== '') {
                $start = (float) $reken['starttarief'];
            }
        }

        $touring = $defaults['touringcar_factor'];
        $winst = $defaults['winstmarge_perc'];
        $btwNl = $defaults['btw_nl'];
        $mwstDe = $defaults['mwst_de'];
        if ($legacy !== null) {
            if (isset($legacy['touringcar_factor'])) {
                $touring = (float) $legacy['touringcar_factor'];
            }
            if (isset($legacy['winstmarge_perc'])) {
                $winst = (float) $legacy['winstmarge_perc'];
            }
            if (isset($legacy['btw_nl'])) {
                $btwNl = (float) $legacy['btw_nl'];
            }
            if (isset($legacy['mwst_de'])) {
                $mwstDe = (float) $legacy['mwst_de'];
            }
        }

        return [
            'uurloon_basis' => $uurloon,
            'km_prijs_basis' => $km,
            'starttarief' => $start,
            'touringcar_factor' => $touring,
            'winstmarge_perc' => $winst,
            'btw_nl' => $btwNl,
            'mwst_de' => $mwstDe,
        ];
    }
}
