<?php
declare(strict_types=1);

/** HH:MM minus minuten (wrap binnen 24 uur). */
function route_heen_time_minus_minutes(string $hhmm, int $minutes): string
{
    $hhmm = substr(trim($hhmm), 0, 5);
    if ($hhmm === '' || !preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
        return '';
    }
    $h = (int) $m[1];
    $mi = (int) $m[2];
    $total = $h * 60 + $mi - $minutes;
    $total = (($total % (24 * 60)) + (24 * 60)) % (24 * 60);
    return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
}

/**
 * Heenroute als segmenten voor UI / JSON-boot (parallel aan calculatie_regels).
 *
 * @param array<string, array{tijd?:string,adres?:string,km?:mixed}> $data Zoals calculaties_bewerken $data
 * @return list<array{vertrektijd:string,aankomst_tijd:string,van:string,naar:string,km:string,zone:string}>
 */
function route_heen_segments_from_regels(array $data): array
{
    $gGarage = trim((string) ($data['t_garage']['adres'] ?? ''));
    $gVl = trim((string) ($data['t_vertrek_klant']['adres'] ?? ''));
    $gVs = trim((string) ($data['t_voorstaan']['adres'] ?? ''));
    $gG2 = trim((string) ($data['t_grens2']['adres'] ?? ''));
    $gBest = trim((string) ($data['t_aankomst_best']['adres'] ?? ''));

    $kmVl = isset($data['t_vertrek_klant']['km']) ? (string) $data['t_vertrek_klant']['km'] : '0';
    $kmVs = isset($data['t_voorstaan']['km']) ? (string) $data['t_voorstaan']['km'] : '0';
    $kmG2 = isset($data['t_grens2']['km']) ? (string) $data['t_grens2']['km'] : '0';
    $kmBest = isset($data['t_aankomst_best']['km']) ? (string) $data['t_aankomst_best']['km'] : '0';

    $tGarage = substr((string) ($data['t_garage']['tijd'] ?? ''), 0, 5);
    $tVl = substr((string) ($data['t_vertrek_klant']['tijd'] ?? ''), 0, 5);
    $tVs = substr((string) ($data['t_voorstaan']['tijd'] ?? ''), 0, 5);
    $tG2 = substr((string) ($data['t_grens2']['tijd'] ?? ''), 0, 5);
    $tBest = substr((string) ($data['t_aankomst_best']['tijd'] ?? ''), 0, 5);

    $segs = [];

    if ($gGarage !== '' || $gVl !== '') {
        $segs[] = [
            'vertrektijd' => $tGarage,
            'aankomst_tijd' => $tVl !== '' ? route_heen_time_minus_minutes($tVl, 15) : '',
            'van' => $gGarage !== '' ? $gGarage : $gVl,
            'naar' => $gVl !== '' ? $gVl : $gGarage,
            'km' => $kmVl,
            'zone' => 'nl',
        ];
    }

    if ($gBest !== '' || $gVs !== '' || $gG2 !== '') {
        $prevNaar = $gVl !== '' ? $gVl : $gGarage;
        $segs[] = [
            'vertrektijd' => $tVl,
            'aankomst_tijd' => $gVs !== '' ? $tVs : ($gG2 !== '' ? $tG2 : $tBest),
            'van' => $prevNaar,
            'naar' => $gVs !== '' ? $gVs : ($gG2 !== '' ? $gG2 : $gBest),
            'km' => $gVs !== '' ? $kmVs : ($gG2 !== '' ? $kmG2 : $kmBest),
            'zone' => $gG2 !== '' && $gVs === '' ? 'de' : 'nl',
        ];
    }

    if ($gVs !== '' && ($gG2 !== '' || $gBest !== '')) {
        $prevNaar = $gVs;
        $segs[] = [
            'vertrektijd' => $tVs,
            'aankomst_tijd' => $gG2 !== '' ? $tG2 : $tBest,
            'van' => $prevNaar,
            'naar' => $gG2 !== '' ? $gG2 : $gBest,
            'km' => $gG2 !== '' ? $kmG2 : $kmBest,
            'zone' => $gG2 !== '' ? 'de' : 'nl',
        ];
    }

    if ($gG2 !== '' && $gBest !== '') {
        $prevNaar = $gG2;
        $segs[] = [
            'vertrektijd' => $tG2,
            'aankomst_tijd' => $tBest,
            'van' => $prevNaar,
            'naar' => $gBest,
            'km' => $kmBest,
            'zone' => 'nl',
        ];
    }

    if ($segs === []) {
        return [
            [
                'vertrektijd' => '',
                'aankomst_tijd' => '',
                'van' => '',
                'naar' => '',
                'km' => '0',
                'zone' => 'nl',
            ],
            [
                'vertrektijd' => '',
                'aankomst_tijd' => '',
                'van' => '',
                'naar' => '',
                'km' => '0',
                'zone' => 'nl',
            ],
        ];
    }

    if (count($segs) === 1) {
        $segs[] = [
            'vertrektijd' => '',
            'aankomst_tijd' => '',
            'van' => '',
            'naar' => '',
            'km' => '0',
            'zone' => 'nl',
        ];
    }

    return $segs;
}
