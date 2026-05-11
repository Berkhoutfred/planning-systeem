<?php

declare(strict_types=1);

/**
 * Zichtbaar build-label voor calculatie-UI (maken / bewerken / wizard).
 *
 * - Verhoog NR bij elke relevante wijziging (idem voor alle schermen).
 * - TIME = lokale release-tijd (HH:MM), handmatig bij deploy — makkelijker dan datum voor cache-check.
 *
 * @return array{nr:int,time:string}
 */
return [
    'nr' => 28,
    'time' => '19:00',
];
