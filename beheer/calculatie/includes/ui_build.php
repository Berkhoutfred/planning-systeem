<?php

declare(strict_types=1);

/**
 * Zichtbaar versielabel voor calculatie-UI (maken / bewerken / wizard).
 *
 * - `nr`: handmatig +1 bij elke relevante UI-wijziging (cache-busting op ?v= queries).
 * - `date`: aanbevolen — releasedatum YYYY-MM-DD (Europe/Amsterdam), géén kloktijd; zo voorkom je
 *   verwarring met “huidige tijd” op het scherm.
 * - `time`: alleen nog als fallback als `date` leeg is (oudere installs); mag HH:MM zijn, is géén servertijd.
 *
 * @return array{nr:int,date?:string,time?:string}
 */
return [
    'nr' => 79,
    'date' => '2026-05-15',
    'time' => '',
];
