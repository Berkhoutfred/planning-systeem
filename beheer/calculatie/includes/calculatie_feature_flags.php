<?php

declare(strict_types=1);

/**
 * Centrale feature-schakelaars calculatie (geen geheimen; optioneel .env override).
 *
 * Losse pakketdagen op één offerte: standaard UIT voor alle tenants.
 * Zet CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED=1 in .env om tijdelijk te herstellen.
 */
function calculatie_feature_losse_pakket_dagen_enabled(): bool
{
    $v = getenv('CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED');
    if ($v === false || $v === '') {
        $v = $_ENV['CALCULATIE_LOSSE_PAKKET_DAGEN_ENABLED'] ?? '0';
    }
    $v = strtolower(trim((string) $v));

    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}
