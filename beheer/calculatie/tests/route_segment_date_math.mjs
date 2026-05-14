/**
 * Pariteitstest voor datumkolom-logica in route_heen_segmenten.js
 * (addIsoDays + assignTimeDayOffsets + eerste-rij per dag).
 * Run: node beheer/calculatie/tests/route_segment_date_math.mjs
 *
 * Houd in sync met route_heen_segmenten.js bij wijzigingen daar.
 */

function parseHm(str) {
    if (!str || typeof str !== 'string') return null;
    const m = String(str).trim().match(/^(\d{1,2}):(\d{2})/);
    if (!m) return null;
    const h = parseInt(m[1], 10);
    const mi = parseInt(m[2], 10);
    if (isNaN(h) || isNaN(mi)) return null;
    return h * 60 + mi;
}

function addIsoDays(dateStr, days) {
    const value = String(dateStr || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '';
    const base = new Date(value + 'T00:00:00');
    if (Number.isNaN(base.getTime())) return value;
    base.setDate(base.getDate() + days);
    const year = base.getFullYear();
    const month = String(base.getMonth() + 1).padStart(2, '0');
    const day = String(base.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

function assignTimeDayOffsets(times) {
    const offsets = [];
    let previousMinutes = null;
    let dayOffset = 0;
    times.forEach(function (time) {
        const minutes = parseHm(time || '');
        if (minutes !== null && previousMinutes !== null && minutes < (previousMinutes - 180)) {
            dayOffset += 1;
        }
        offsets.push(minutes === null ? null : dayOffset);
        if (minutes !== null) {
            previousMinutes = minutes;
        }
    });
    return offsets;
}

/** Zelfde als refreshSegmentDateColumnInTable: ISO per rij op index i (alleen vt-offset, zoals in bron). */
function rowIsoForIndex(rowsTimesPairs, baseYmd, rowIndex) {
    const times = rowsTimesPairs.flat();
    const offsets = assignTimeDayOffsets(times);
    let off = offsets[rowIndex * 2];
    if (off === null) {
        if (rowIndex === 0) {
            off = 0;
        } else {
            let inherit = 0;
            const pd = offsets[(rowIndex - 1) * 2];
            const pa = offsets[(rowIndex - 1) * 2 + 1];
            if (pd !== null) inherit = Math.max(inherit, pd);
            if (pa !== null) inherit = Math.max(inherit, pa);
            off = inherit;
        }
    }
    return addIsoDays(baseYmd, off || 0);
}

function assertEq(a, b, msg) {
    if (a !== b) {
        throw new Error(msg + ': expected ' + JSON.stringify(b) + ', got ' + JSON.stringify(a));
    }
}

// --- Tests ---

// 1) Lege tijden: alle rijen erven offset 0 → zelfde kalenderdag als base
const emptyTwoRows = [
    ['', ''],
    ['', '']
];
assertEq(rowIsoForIndex(emptyTwoRows, '2026-05-19', 0), '2026-05-19', 'dagtocht-base rij0');
assertEq(rowIsoForIndex(emptyTwoRows, '2026-05-19', 1), '2026-05-19', 'dagtocht-base rij1');
assertEq(rowIsoForIndex(emptyTwoRows, '2026-05-24', 0), '2026-05-24', 'meerdaags-eind-base rij0');

// 2) Nachtelijke sprong: tweede vertrek vroeger dan vorige → +1 dag
const overnight = [
    ['22:00', '23:00'],
    ['01:00', '02:00']
];
const off = assignTimeDayOffsets(overnight.flat());
assertEq(off[2], 1, 'overnight: tweede vt moet dag-offset 1 zijn');
assertEq(rowIsoForIndex(overnight, '2026-05-19', 1), '2026-05-20', 'overnight rij1 iso');

// 3) Geen sprong binnen zelfde dag
const sameday = [
    ['09:00', '09:15'],
    ['09:30', '10:00']
];
assertEq(rowIsoForIndex(sameday, '2026-05-19', 0), '2026-05-19', 'zelfde dag rij0');
assertEq(rowIsoForIndex(sameday, '2026-05-19', 1), '2026-05-19', 'zelfde dag rij1');

console.log('route_segment_date_math.mjs: alle checks OK (' + new Date().toISOString() + ')');
