<?php
declare(strict_types=1);

/**
 * Prijsberekening busreizen incl. vroegboekkorting (per persoon, t/m deadline-dag).
 */

function busreis_vroegboek_actief(array $reis, ?int $timestamp = null): bool
{
    $korting = (float) ($reis['vroegboekkorting'] ?? 0);
    $deadline = trim((string) ($reis['vroegboek_deadline'] ?? ''));
    if ($korting <= 0 || $deadline === '') {
        return false;
    }

    $ts = $timestamp ?? time();

    return $ts <= strtotime($deadline . ' 23:59:59');
}

/**
 * @return array{
 *   subtotaal: float,
 *   reserveringskosten: float,
 *   totaal: float,
 *   vroegboek_per_persoon: float,
 *   vroegboek_totaal: float
 * }
 */
function busreis_bereken_prijs(array $reis, int $aantal, int $enkelpersoon, float $optiesPerPersoon = 0.0, ?int $timestamp = null): array
{
    $aantal = max(1, $aantal);
    $enkelpersoon = $enkelpersoon ? 1 : 0;

    $bruto = ((float) $reis['prijs_pp'] * $aantal)
        + ((float) ($reis['toeslag_enkelpersoon'] ?? 0) * $aantal * $enkelpersoon)
        + ($optiesPerPersoon * $aantal);

    $vroegboekPerPersoon = busreis_vroegboek_actief($reis, $timestamp)
        ? (float) ($reis['vroegboekkorting'] ?? 0)
        : 0.0;
    $vroegboekTotaal = $vroegboekPerPersoon * $aantal;

    $subtotaal = max(0.0, $bruto - $vroegboekTotaal);
    $reserveringskosten = (float) ($reis['reserveringskosten'] ?? 0);

    return [
        'subtotaal' => round($subtotaal, 2),
        'reserveringskosten' => round($reserveringskosten, 2),
        'totaal' => round($subtotaal + $reserveringskosten, 2),
        'vroegboek_per_persoon' => round($vroegboekPerPersoon, 2),
        'vroegboek_totaal' => round($vroegboekTotaal, 2),
    ];
}
