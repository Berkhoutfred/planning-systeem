/**
 * CAO-toeslagen fase 1: art. 37 lid 2 (touringcar) + onderbrekingstoeslag (brenghaal).
 * Zie beheer/calculatie/docs/CAO-toeslagen-referentie.txt voor scope en afspraken.
 * Tarieven: vanaf 1-1-2026 (EUR per uur / vast bedrag).
 */
(function () {
    'use strict';

    var RATE_SAT = 4.01;
    var RATE_SUN_FEAST = 6.04;
    var RATE_NIGHT_WEEKDAY = 4.01;
    var ONDERBREKING_STUK = 15.92;

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function parseHmToMinutes(hm) {
        if (!hm || typeof hm !== 'string') return null;
        var m = String(hm).trim().match(/^(\d{1,2}):(\d{2})/);
        if (!m) return null;
        var h = parseInt(m[1], 10);
        var mi = parseInt(m[2], 10);
        if (isNaN(h) || isNaN(mi) || mi > 59) return null;
        return h * 60 + mi;
    }

    function weekdaySun0(d) {
        return d.getDay();
    }

    function easterSunday(y) {
        var a = y % 19;
        var b = Math.floor(y / 100);
        var c = y % 100;
        var d = Math.floor(b / 4);
        var e = b % 4;
        var f = Math.floor((b + 8) / 25);
        var g = Math.floor((b - f + 1) / 3);
        var h = (19 * a + b - d - g + 15) % 30;
        var i = Math.floor(c / 4);
        var k = c % 4;
        var l = (32 + 2 * e + 2 * i - h - k) % 7;
        var m = Math.floor((a + 11 * h + 22 * l) / 451);
        var month = Math.floor((h + l - 7 * m + 114) / 31);
        var day = ((h + l - 7 * m + 114) % 31) + 1;
        return new Date(y, month - 1, day, 12, 0, 0, 0);
    }

    function sameCalendarDate(a, b) {
        return a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth()
            && a.getDate() === b.getDate();
    }

    function isKoningsdag(d) {
        var y = d.getFullYear();
        var k = new Date(y, 3, 27, 12, 0, 0, 0);
        if (k.getDay() === 0) {
            k = new Date(y, 3, 26, 12, 0, 0, 0);
        }
        return sameCalendarDate(d, k);
    }

    function isFixedHoliday(d) {
        var y = d.getFullYear();
        var mo = d.getMonth() + 1;
        var da = d.getDate();
        if (mo === 1 && da === 1) return true;
        if (mo === 12 && (da === 25 || da === 26)) return true;
        if (isKoningsdag(d)) return true;
        var eSun = easterSunday(y);
        var eMon = new Date(eSun);
        eMon.setDate(eMon.getDate() + 1);
        var asc = new Date(eSun);
        asc.setDate(asc.getDate() + 39);
        var pen = new Date(eSun);
        pen.setDate(pen.getDate() + 49);
        var pen2 = new Date(eSun);
        pen2.setDate(pen2.getDate() + 50);
        if (sameCalendarDate(d, eSun) || sameCalendarDate(d, eMon)) return true;
        if (sameCalendarDate(d, asc)) return true;
        if (sameCalendarDate(d, pen) || sameCalendarDate(d, pen2)) return true;
        return false;
    }

    function isSunOrFeast(d) {
        if (isFixedHoliday(d)) return true;
        return weekdaySun0(d) === 0;
    }

    function isSaturday(d) {
        return weekdaySun0(d) === 6;
    }

    function isWeekdayMonFri(d) {
        var w = weekdaySun0(d);
        return w >= 1 && w <= 5;
    }

    function bucketForMinute(d) {
        if (isSunOrFeast(d)) {
            return 'feest';
        }
        if (isSaturday(d)) {
            return 'zaterdag';
        }
        var mins = d.getHours() * 60 + d.getMinutes();
        if (isWeekdayMonFri(d) && mins < 6 * 60) {
            return 'nacht';
        }
        return 'basis';
    }

    function addMinutesToDate(d, minutes) {
        return new Date(d.getTime() + minutes * 60000);
    }

    function localDateTime(dateYmd, hm) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(dateYmd || '')) return null;
        var p = parseHmToMinutes(hm);
        if (p === null) return null;
        var parts = dateYmd.split('-');
        var y = parseInt(parts[0], 10);
        var mo = parseInt(parts[1], 10) - 1;
        var da = parseInt(parts[2], 10);
        var h = Math.floor(p / 60);
        var m = p % 60;
        return new Date(y, mo, da, h, m, 0, 0);
    }

    /**
     * Zelfde kettinglogica als calcChronologicalSpan in rekenmachine.js:
     * eind = start + (ontwikkelde minuten t.o.v. eerste tijd).
     */
    function intervalFromTimeChain(startYmd, times) {
        var values = (Array.isArray(times) ? times : []).map(function (t) {
            return String(t || '').trim();
        }).filter(function (t) { return t !== ''; });
        if (values.length < 2) return null;
        var toM = function (time) {
            return parseHmToMinutes(time);
        };
        var startMin = toM(values[0]);
        if (startMin === null) return null;
        var startDt = localDateTime(startYmd, values[0]);
        if (!startDt) return null;
        var previous = startMin;
        for (var i = 1; i < values.length; i++) {
            var current = toM(values[i]);
            if (current === null) continue;
            while (current < previous) {
                current += 24 * 60;
            }
            previous = current;
        }
        return { start: startDt, end: addMinutesToDate(startDt, previous - startMin) };
    }

    function simpleDayInterval(ymd, hmStart, hmEnd) {
        var a = localDateTime(ymd, hmStart);
        var b = localDateTime(ymd, hmEnd);
        if (!a || !b) return null;
        if (b <= a) {
            b = addMinutesToDate(b, 24 * 60);
        }
        return { start: a, end: b };
    }

    function accumulateBuckets(intervals) {
        var satMin = 0;
        var feastMin = 0;
        var nightMin = 0;
        for (var i = 0; i < intervals.length; i++) {
            var iv = intervals[i];
            var t = new Date(iv.start.getTime());
            var end = iv.end;
            while (t < end) {
                var b = bucketForMinute(t);
                if (b === 'feast') feastMin += 1;
                else if (b === 'zaterdag') satMin += 1;
                else if (b === 'nacht') nightMin += 1;
                t = addMinutesToDate(t, 1);
            }
        }
        return { satMin: satMin, feastMin: feastMin, nightMin: nightMin };
    }

    function euroFromMinutes(mins, rate) {
        return (mins / 60) * rate;
    }

    function ceilToMultiple5(euro) {
        if (euro <= 0) return 0;
        return Math.ceil(euro / 5) * 5;
    }

    /**
     * @returns {{ lines: {key:string,label:string,amount:number}[], rawTotal: number, roundedTotal: number }}
     */
    function berekenCaoToeslagen(input) {
        var type = String(input.rittype || '');
        var lines = [];
        var rawOnreg = 0;

        if (type === 'trein' || type === 'meerdaags' || type === 'buitenland') {
            return { lines: [], rawTotal: 0, roundedTotal: 0 };
        }

        var ritDatum = String(input.ritDatum || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(ritDatum)) {
            return { lines: [], rawTotal: 0, roundedTotal: 0 };
        }

        var intervals = [];

        if (type === 'dagtocht' || type === 'schoolreis') {
            var r2 = !!input.route2Visible;
            var t1 = String(input.tGarage || '').trim();
            var tHeen = String(input.tHeenEind || '').trim();
            var ts = String(input.tRoute2Start || '').trim();
            var te = String(input.tRoute2End || '').trim();
            if (r2 && t1 && tHeen && ts && te) {
                var ivc = intervalFromTimeChain(ritDatum, [t1, tHeen, ts, te]);
                if (ivc) intervals.push(ivc);
            } else if (t1 && tHeen) {
                var ivs = simpleDayInterval(ritDatum, t1, tHeen);
                if (ivs) intervals.push(ivs);
            }
        } else if (type === 'enkel') {
            var t1e = String(input.tGarage || '').trim();
            var tEnd = String(input.tRoute1End || '').trim();
            if (t1e && tEnd) {
                var ive = simpleDayInterval(ritDatum, t1e, tEnd);
                if (ive) intervals.push(ive);
            }
        } else if (type === 'brenghaal') {
            var t1b = String(input.tGarage || '').trim();
            var tEnd1 = String(input.tRoute1End || '').trim();
            var tS2 = String(input.tRoute2Start || '').trim();
            var tE2 = String(input.tRoute2End || '').trim();
            if (t1b && tEnd1) {
                var iv1 = simpleDayInterval(ritDatum, t1b, tEnd1);
                if (iv1) intervals.push(iv1);
            }
            var d2 = String(input.ritDatumEind || ritDatum).trim();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(d2)) d2 = ritDatum;
            if (tS2 && tE2) {
                var iv2 = simpleDayInterval(d2, tS2, tE2);
                if (iv2) intervals.push(iv2);
            }
        }

        if (intervals.length > 0) {
            var acc = accumulateBuckets(intervals);
            var eSat = euroFromMinutes(acc.satMin, RATE_SAT);
            var eFeast = euroFromMinutes(acc.feastMin, RATE_SUN_FEAST);
            var eNight = euroFromMinutes(acc.nightMin, RATE_NIGHT_WEEKDAY);
            rawOnreg = eSat + eFeast + eNight;
            if (eSat > 0.001) {
                lines.push({
                    key: 'onreg_za',
                    label: 'Onregelmatigheid zaterdag (art. 37 lid 2a)',
                    amount: eSat
                });
            }
            if (eFeast > 0.001) {
                lines.push({
                    key: 'onreg_zon_feest',
                    label: 'Onregelmatigheid zon-/feestdag (art. 37 lid 2b)',
                    amount: eFeast
                });
            }
            if (eNight > 0.001) {
                lines.push({
                    key: 'onreg_nacht',
                    label: 'Onregelmatigheid nacht doordeweeks 00:00–06:00 (art. 37 lid 2c)',
                    amount: eNight
                });
            }
        }

        var rawOb = 0;
        if (type === 'brenghaal') {
            var n = Math.max(0, Math.min(2, parseInt(String(input.onderbrekingAantal || 0), 10) || 0));
            rawOb = n * ONDERBREKING_STUK;
            if (rawOb > 0.001) {
                lines.push({
                    key: 'onderbreking',
                    label: 'Onderbrekingstoeslag (' + n + '× art. 37)',
                    amount: rawOb
                });
            }
        }

        var rawTotal = rawOnreg + rawOb;
        var rounded = ceilToMultiple5(rawTotal);
        if (rounded > rawTotal + 0.001) {
            lines.push({
                key: 'afronding_5',
                label: 'Afronding CAO-toeslagen (som → veelvoud €5)',
                amount: rounded - rawTotal
            });
        }

        return { lines: lines, rawTotal: rawTotal, roundedTotal: rounded };
    }

    window.berekenCaoToeslagen = berekenCaoToeslagen;
})();
