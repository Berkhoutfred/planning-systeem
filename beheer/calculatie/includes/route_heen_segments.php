<?php
declare(strict_types=1);

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

    $tVl = substr((string) ($data['t_vertrek_klant']['tijd'] ?? ''), 0, 5);
    $tBest = substr((string) ($data['t_aankomst_best']['tijd'] ?? ''), 0, 5);

    $segs = [];

    if ($gGarage !== '' || $gVl !== '') {
        $segs[] = [
            'vertrektijd' => $tVl !== '' ? $tVl : '08:00',
            'aankomst_tijd' => '',
            'van' => $gGarage !== '' ? $gGarage : $gVl,
            'naar' => $gVl !== '' ? $gVl : $gGarage,
            'km' => $kmVl,
            'zone' => 'nl',
        ];
    }

    if ($gVs !== '') {
        $prevNaar = $gVl !== '' ? $gVl : $gGarage;
        $segs[] = [
            'vertrektijd' => '',
            'aankomst_tijd' => '',
            'van' => $prevNaar,
            'naar' => $gVs,
            'km' => $kmVs,
            'zone' => 'nl',
        ];
    }

    if ($gG2 !== '') {
        $prevNaar = $gVs !== '' ? $gVs : ($gVl !== '' ? $gVl : '');
        $segs[] = [
            'vertrektijd' => '',
            'aankomst_tijd' => '',
            'van' => $prevNaar !== '' ? $prevNaar : $gVs,
            'naar' => $gG2,
            'km' => $kmG2,
            'zone' => 'de',
        ];
    }

    if ($gBest !== '') {
        $prevNaar = $gG2 !== '' ? $gG2 : ($gVs !== '' ? $gVs : ($gVl !== '' ? $gVl : ''));
        $segs[] = [
            'vertrektijd' => '',
            'aankomst_tijd' => $tBest,
            'van' => $prevNaar !== '' ? $prevNaar : ($gVl !== '' ? $gVl : $gBest),
            'naar' => $gBest,
            'km' => $kmBest,
            'zone' => 'nl',
        ];
    }

    if ($segs === []) {
        return [
            [
                'vertrektijd' => '08:00',
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
