/**
 * Heenroute als segmenten-tabel; synct naar verborgen legacy calculatie_regels-velden.
 * Vereist: DOM met #heen_segmenten_body, legacy velden #addr_t_* met zelfde ids als voorheen.
 */
(function () {
    'use strict';

    const MAX_SEG = 6;
    /** Aankomst bij klant (rij 1) = vertrek bij klant − dit aantal minuten. */
    const KLANT_VOORVERTREK_MIN = 15;

    function parseHm(str) {
        if (!str || typeof str !== 'string') return null;
        const m = String(str).trim().match(/^(\d{1,2}):(\d{2})/);
        if (!m) return null;
        const h = parseInt(m[1], 10);
        const mi = parseInt(m[2], 10);
        if (isNaN(h) || isNaN(mi)) return null;
        return h * 60 + mi;
    }

    function formatHm(totalMin) {
        let t = totalMin % (24 * 60);
        if (t < 0) t += 24 * 60;
        const h = Math.floor(t / 60);
        const mi = t % 60;
        return (h < 10 ? '0' : '') + h + ':' + (mi < 10 ? '0' : '') + mi;
    }

    function hmMinusMinutes(hm, mins) {
        const p = parseHm(hm);
        if (p === null) return '';
        return formatHm(p - mins);
    }

    function getEffectiveRows(rows) {
        const src = Array.isArray(rows) ? rows.slice() : getRows();
        while (src.length > 2) {
            const last = src[src.length - 1];
            const naar = last.querySelector('.heen-naar')?.value.trim() || '';
            const km = parseFloat(last.querySelector('.heen-km')?.value || '0') || 0;
            if (naar !== '' || km > 0) {
                break;
            }
            src.pop();
        }
        const firstReturnIdx = src.findIndex(function (row) {
            return !!row.dataset.returnKind;
        });
        if (firstReturnIdx > 2) {
            for (let i = firstReturnIdx - 1; i >= 2; i--) {
                const row = src[i];
                const naar = row.querySelector('.heen-naar')?.value.trim() || '';
                const km = parseFloat(row.querySelector('.heen-km')?.value || '0') || 0;
                if (naar !== '' || km > 0 || row.dataset.returnKind) {
                    break;
                }
                src.splice(i, 1);
            }
        }
        return src;
    }

    function getRowPartitions(rows) {
        const allRows = Array.isArray(rows) ? rows.slice() : getRows();
        const activeRows = getEffectiveRows(allRows);
        const coreRows = [];
        const returnRows = [];
        activeRows.forEach(function (row) {
            if (row.dataset.returnKind) {
                returnRows.push(row);
            } else {
                coreRows.push(row);
            }
        });
        return { allRows: allRows, activeRows: activeRows, coreRows: coreRows, returnRows: returnRows };
    }

    function getMainFormEl() {
        return document.getElementById('hoofdFormulier') || document.getElementById('mainForm');
    }

    function readTrimmedValue(id) {
        const el = document.getElementById(id);
        return el && typeof el.value === 'string' ? el.value.trim() : '';
    }

    function readKmInput(nameKey) {
        const el = document.querySelector('input[name="km[' + nameKey + ']"]');
        if (!el || el.value === '') return 0;
        return parseFloat(el.value) || 0;
    }

    function readZoneInRow(rowId) {
        const row = document.getElementById(rowId);
        const sel = row ? row.querySelector('.km-zone-select') : null;
        return sel ? (sel.value || 'nl') : 'nl';
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

    function enrichRoute1ForPlanner(route1Payload) {
        const events = [];
        const segments = (route1Payload.segments || []).map(function (segment, index) {
            const next = Object.assign({}, segment, { seq: index + 1 });
            if (next.depart_at) events.push({ segmentIndex: index, key: 'depart_day_offset', time: next.depart_at });
            if (next.arrive_at) events.push({ segmentIndex: index, key: 'arrive_day_offset', time: next.arrive_at });
            return next;
        });
        const offsets = assignTimeDayOffsets(events.map(function (event) { return event.time; }));
        let startOffset = 0;
        let endOffset = 0;
        let seenStart = false;
        events.forEach(function (event, idx) {
            const offset = offsets[idx];
            if (offset === null) return;
            segments[event.segmentIndex][event.key] = offset;
            if (!seenStart) {
                startOffset = offset;
                seenStart = true;
            }
            if (offset > endOffset) endOffset = offset;
        });
        return {
            route_index: 1,
            code: 'R1',
            label: route1Payload.label || 'Route 1',
            mode: 'segment_table',
            enabled: segments.length > 0,
            return_mode: route1Payload.return_mode || '',
            start_day_offset: startOffset,
            end_day_offset: endOffset,
            segments: segments
        };
    }

    function enrichRoute2ForPlanner(route2Payload) {
        const segments = (route2Payload.segments || []).map(function (segment) {
            return Object.assign({}, segment);
        });
        const offsets = assignTimeDayOffsets(segments.map(function (segment) { return segment.time || ''; }));
        let startOffset = 0;
        let endOffset = 0;
        let seenStart = false;
        segments.forEach(function (segment, idx) {
            const offset = offsets[idx];
            if (offset === null) return;
            segment.time_day_offset = offset;
            if (!seenStart) {
                startOffset = offset;
                seenStart = true;
            }
            if (offset > endOffset) endOffset = offset;
        });
        return {
            route_index: 2,
            code: 'R2',
            label: 'Route 2',
            mode: 'legacy_route',
            enabled: !!route2Payload.enabled || segments.length > 0,
            start_day_offset: startOffset,
            end_day_offset: endOffset,
            segments: segments
        };
    }

    function buildPlannerDays(route1Payload, route2Payload, tussPayload, startDate) {
        const days = [];
        const firstDay = {
            seq: 1,
            day_index: 0,
            date: startDate || '',
            kind: 'travel',
            label: 'Dag 1',
            routes: [],
            events: []
        };
        const route1 = enrichRoute1ForPlanner(route1Payload);
        const route2 = enrichRoute2ForPlanner(route2Payload);
        if (route1.segments.length > 0) firstDay.routes.push(route1);
        if (route2.enabled || route2.segments.length > 0) firstDay.routes.push(route2);
        if (firstDay.date || firstDay.routes.length > 0) {
            days.push(firstDay);
        }

        const grouped = {};
        (tussPayload.items || []).forEach(function (item, idx) {
            const date = item.datum || addIsoDays(startDate, idx + 1);
            if (!grouped[date]) grouped[date] = [];
            grouped[date].push({
                code: 'XD',
                label: 'Extra dag',
                date: date,
                time: item.tijd || '',
                from: item.van || '',
                to: item.naar || '',
                km: item.km || 0,
                zone: item.zone || 'nl'
            });
        });

        Object.keys(grouped).sort().forEach(function (date) {
            days.push({
                seq: days.length + 1,
                day_index: days.length,
                date: date,
                kind: 'extra_drive',
                label: 'Extra dag',
                routes: [],
                events: grouped[date]
            });
        });

        return days;
    }

    function resolvePlannerEndDate(days, fallbackEndDate) {
        let endDate = fallbackEndDate || '';
        days.forEach(function (day) {
            if (!day || !day.date) return;
            if (!endDate || day.date > endDate) endDate = day.date;
            (day.routes || []).forEach(function (route) {
                const candidate = addIsoDays(day.date, route.end_day_offset || 0);
                if (candidate && (!endDate || candidate > endDate)) {
                    endDate = candidate;
                }
            });
            (day.events || []).forEach(function (event) {
                if (event.date && (!endDate || event.date > endDate)) {
                    endDate = event.date;
                }
            });
        });
        return endDate;
    }

    function buildRoute1SegmentsPayload() {
        const parts = getRowPartitions(getRows());
        const activeRows = parts.activeRows;
        const payload = [];
        activeRows.forEach(function (row, idx) {
            const returnKind = row.dataset.returnKind || '';
            payload.push({
                seq: idx + 1,
                day_index: 0,
                kind: returnKind === 'rg'
                    ? 'return_to_garage'
                    : (returnKind === 'rk-klant'
                        ? 'return_to_customer'
                        : (returnKind === 'rk-garage' ? 'return_to_garage' : (idx === 0 ? 'garage_to_customer' : 'stop'))),
                return_kind: returnKind,
                from: row.querySelector('.heen-van')?.value.trim() || '',
                to: row.querySelector('.heen-naar')?.value.trim() || '',
                depart_at: row.querySelector('.heen-vt')?.value.trim() || '',
                arrive_at: row.querySelector('.heen-at')?.value.trim() || '',
                km: parseFloat(row.querySelector('.heen-km')?.value || '0') || 0,
                zone: row.querySelector('.heen-zone')?.value || 'nl'
            });
        });
        return payload;
    }

    function buildRoute2Payload() {
        const route2Enabled = window.__calcTerugreisUserShow === true || readTrimmedValue('addr_t_vertrek_best') !== '' || readTrimmedValue('time_t_vertrek_best') !== '';
        if (!route2Enabled) {
            return { enabled: false, segments: [] };
        }
        const rows = [
            { type: 't_garage_rit2', kind: 'garage_start', time: readTrimmedValue('time_t_garage_rit2'), address: readTrimmedValue('addr_t_garage_rit2'), km: 0, zone: readZoneInRow('row_garage_rit2') },
            { type: 't_voorstaan_rit2', kind: 'preposition', time: readTrimmedValue('time_t_voorstaan_rit2'), address: readTrimmedValue('addr_t_voorstaan_rit2'), km: readKmInput('t_voorstaan_rit2'), zone: readZoneInRow('row_voorstaan_rit2') },
            { type: 't_vertrek_best', kind: 'route2_depart', time: readTrimmedValue('time_t_vertrek_best'), address: readTrimmedValue('addr_t_vertrek_best'), km: readKmInput('t_vertrek_best'), zone: readZoneInRow('row_vertrek_best') },
            { type: 't_retour_klant', kind: 'route2_customer', time: readTrimmedValue('time_t_retour_klant'), address: readTrimmedValue('addr_t_retour_klant'), km: readKmInput('t_retour_klant'), zone: readZoneInRow('row_retour_klant') },
            { type: 't_retour_garage', kind: 'route2_garage_end', time: readTrimmedValue('time_t_retour_garage'), address: readTrimmedValue('addr_t_retour_garage'), km: readKmInput('t_retour_garage'), zone: readZoneInRow('row_garage_terug') }
        ].filter(function (row) {
            return row.time !== '' || row.address !== '' || row.km > 0;
        });
        return { enabled: rows.length > 0, segments: rows };
    }

    function buildTussendagenPayload() {
        const items = [];
        document.querySelectorAll('#tussendagen_rows .tz-row').forEach(function (row) {
            const datum = row.querySelector('[name="tussendagen_datum[]"]')?.value.trim() || '';
            const tijd = row.querySelector('[name="tussendagen_tijd[]"]')?.value.trim() || '';
            const van = row.querySelector('[name="tussendagen_van[]"]')?.value.trim() || '';
            const naar = row.querySelector('[name="tussendagen_naar[]"]')?.value.trim() || '';
            const km = parseFloat(row.querySelector('[name="tussendagen_km[]"]')?.value || '0') || 0;
            const zone = row.querySelector('[name="tussendagen_zone[]"]')?.value || 'nl';
            if (datum === '' && tijd === '' && van === '' && naar === '' && km <= 0) {
                return;
            }
            items.push({ datum: datum, tijd: tijd, van: van, naar: naar, km: km, zone: zone });
        });
        return { enabled: !!document.getElementById('tussendagen_enabled')?.checked, items: items };
    }

    function buildBuitenlandPayload() {
        if (ritType() !== 'buitenland') {
            return null;
        }
        const dagprogramma = [];
        document.querySelectorAll('#dagprogramma_container textarea[name^="dagprogramma["]').forEach(function (ta) {
            const datum = (ta.name.match(/\[(.*?)\]/) || [null, ''])[1] || '';
            const tekst = ta.value.trim();
            if (datum === '' && tekst === '') return;
            dagprogramma.push({ datum: datum, tekst: tekst });
        });
        return {
            overnachting_door: readTrimmedValue('buitenland_overnachting') || 'klant',
            overnachting_bedrag_eur: readTrimmedValue('buitenland_overnachting_bedrag') || '',
            dagprogramma: dagprogramma
        };
    }

    function buildRouteV2Payload() {
        const startDate = readTrimmedValue('rit_datum');
        const route1Payload = {
            label: 'Route 1',
            return_mode: isRkChipActive() ? 'rk' : (isRgChipActive() ? 'rg' : ''),
            segments: buildRoute1SegmentsPayload()
        };
        const route2Payload = buildRoute2Payload();
        const tussPayload = buildTussendagenPayload();
        const plannerFallbackDays = buildPlannerDays(route1Payload, route2Payload, tussPayload, startDate);
        const plannerData = typeof window.routePlannerBuildData === 'function'
            ? window.routePlannerBuildData({
                startDate: startDate,
                fallbackEndDate: readTrimmedValue('rit_datum_eind') || startDate,
                route1: route1Payload,
                route2: route2Payload,
                tussendagen: tussPayload,
                fallbackDays: plannerFallbackDays
            })
            : null;
        const plannerDays = plannerData && Array.isArray(plannerData.days) ? plannerData.days : plannerFallbackDays;
        const finalTussPayload = plannerData && plannerData.tussendagen ? plannerData.tussendagen : tussPayload;
        const endDate = plannerData && plannerData.endDate
            ? plannerData.endDate
            : resolvePlannerEndDate(plannerDays, readTrimmedValue('rit_datum_eind') || startDate);
        return {
            schema: 2,
            rittype: ritType(),
            dates: { start: startDate, end: endDate },
            days: plannerDays,
            route1: route1Payload,
            route2: route2Payload,
            tussendagen: finalTussPayload,
            buitenland: buildBuitenlandPayload()
        };
    }

    function updateRouteV2HiddenInput() {
        const hidden = document.getElementById('route_v2_json');
        if (!hidden) return;
        try {
            const payload = buildRouteV2Payload();
            hidden.value = JSON.stringify(payload);
            const endEl = document.getElementById('rit_datum_eind');
            if (endEl && payload && payload.dates && payload.dates.end && endEl.value !== payload.dates.end) {
                endEl.value = payload.dates.end;
                if (typeof window.rebuildDagprogrammaBL === 'function') {
                    window.rebuildDagprogrammaBL();
                }
            }
        } catch (e) {
            hidden.value = '';
        }
    }

    /** Eerste segment: links vertrek garage, rechts aankomst bij klant. */
    function applyFirstRowKlantTijden(rows) {
        const r0 = rows[0];
        if (!r0 || !r0.classList.contains('heen-seg-first')) return;
        const vtEl = r0.querySelector('.heen-vt');
        const atEl = r0.querySelector('.heen-at');
        const tgLegacy = document.getElementById('time_t_garage');
        const tvLegacy = document.getElementById('time_t_vertrek_klant');
        const vt = tgLegacy && tgLegacy.value && tgLegacy.value.trim() ? tgLegacy.value.trim().substring(0, 5) : '';
        const at = tvLegacy && tvLegacy.value && tvLegacy.value.trim()
            ? hmMinusMinutes(tvLegacy.value.trim().substring(0, 5), KLANT_VOORVERTREK_MIN)
            : '';
        const naarEl = r0.querySelector('.heen-naar');
        const vlAddr = document.getElementById('addr_t_vertrek_klant');
        if (naarEl && vlAddr && vlAddr.value.trim() && !naarEl.value.trim()) {
            naarEl.value = vlAddr.value.trim();
        }
        if (vtEl) {
            vtEl.readOnly = true;
            vtEl.classList.remove('heen-vt--auto');
            vtEl.dataset.timeEditable = '1';
            if (vtEl.dataset.manual !== '1') {
                vtEl.value = vt;
            }
            vtEl.title = 'Vertrek vanuit garage';
        }
        if (atEl) {
            atEl.readOnly = true;
            atEl.classList.remove('heen-at--auto');
            atEl.dataset.timeEditable = '1';
            if (atEl.dataset.manual !== '1') {
                atEl.value = at;
            }
            atEl.title = 'Aankomst bij klant';
        }
    }

    /**
     * Zichtbare tijden volgen de legacy-ketting:
     * rij 1 vertrek = vertrek garage
     * rij 1 aankomst = aankomst / voorstaan bij klant
     * rij 2 vertrek = vertrek klant (leidend)
     * volgende rijen lopen automatisch door tot de eindbestemming.
     */
    function applyAutoSegmentTijden(rows) {
        if (!rows || rows.length < 1) return;
        const parts = getRowPartitions(rows);
        const activeRows = parts.activeRows;
        const coreRows = parts.coreRows;
        const returnRows = parts.returnRows;
        if (coreRows.length < 1) return;

        if (coreRows.length === 1) {
            const row0 = coreRows[0];
            const row0Vt = row0 ? row0.querySelector('.heen-vt') : null;
            const row0At = row0 ? row0.querySelector('.heen-at') : null;
            const tBest = document.getElementById('time_t_aankomst_best')?.value.trim() || '';
            const tRetKlant = document.getElementById('time_t_retour_klant')?.value.trim() || '';
            const tRetGarage = document.getElementById('time_t_retour_garage_heen')?.value.trim() || '';

            if (row0Vt) {
                row0Vt.readOnly = true;
                row0Vt.classList.remove('heen-vt--auto');
                row0Vt.dataset.timeEditable = '1';
                row0Vt.title = 'Vertrek vanuit garage';
            }
            if (row0At) {
                row0At.readOnly = true;
                row0At.classList.remove('heen-at--auto');
                row0At.dataset.timeEditable = '1';
                if (row0At.dataset.manual !== '1') {
                    row0At.value = tBest;
                }
                row0At.title = 'Aankomst bij klant';
            }

            let previousArrival = row0At ? row0At.value.trim() : '';
            returnRows.forEach(function (row) {
                const vtEl = row.querySelector('.heen-vt');
                const atEl = row.querySelector('.heen-at');
                const kind = row.dataset.returnKind || '';
                const arrival = kind === 'rk-klant' ? tRetKlant : tRetGarage;
                if (vtEl) {
                    vtEl.readOnly = true;
                    vtEl.classList.remove('heen-vt--auto');
                    vtEl.dataset.timeEditable = '1';
                    if (vtEl.dataset.manual !== '1') {
                        vtEl.value = previousArrival || '';
                    }
                    vtEl.title = 'Vertrek voor retourregel';
                }
                if (atEl) {
                    atEl.readOnly = true;
                    atEl.classList.add('heen-at--auto');
                    delete atEl.dataset.timeEditable;
                    atEl.value = arrival || '';
                    atEl.title = 'Automatische aankomsttijd voor retourregel';
                }
                previousArrival = arrival || previousArrival;
            });

            for (let i = activeRows.length; i < rows.length; i++) {
                const vtEl = rows[i].querySelector('.heen-vt');
                const atEl = rows[i].querySelector('.heen-at');
                if (vtEl) {
                    vtEl.value = '';
                    vtEl.readOnly = true;
                    vtEl.classList.remove('heen-vt--auto');
                    vtEl.dataset.timeEditable = '1';
                    vtEl.title = 'Vul eerst een bestemming in';
                }
                if (atEl) {
                    atEl.value = '';
                    atEl.readOnly = true;
                    atEl.classList.add('heen-at--auto');
                    delete atEl.dataset.timeEditable;
                    atEl.title = 'Vul eerst een bestemming in';
                }
            }
            return;
        }

        applyFirstRowKlantTijden(activeRows);

        const tv = document.getElementById('time_t_vertrek_klant')?.value.trim() || '';
        const tVs = document.getElementById('time_t_voorstaan')?.value.trim() || '';
        const tG2 = document.getElementById('time_t_grens2')?.value.trim() || '';
        const tBest = document.getElementById('time_t_aankomst_best')?.value.trim() || '';
        const tRetKlant = document.getElementById('time_t_retour_klant')?.value.trim() || '';
        const tRetGarage = document.getElementById('time_t_retour_garage_heen')?.value.trim() || '';

        let stopAankomsten = [tBest];
        if (coreRows.length === 3) {
            stopAankomsten = [tVs, tBest];
        } else if (coreRows.length >= 4) {
            stopAankomsten = [tVs, tG2, tBest];
        }
        returnRows.forEach(function (row) {
            if (row.dataset.returnKind === 'rk-klant') {
                stopAankomsten.push(tRetKlant);
            } else {
                stopAankomsten.push(tRetGarage);
            }
        });

        for (let i = 1; i < activeRows.length; i++) {
            const vtEl = activeRows[i].querySelector('.heen-vt');
            const atEl = activeRows[i].querySelector('.heen-at');
            const kind = activeRows[i].dataset.returnKind || '';

            if (vtEl) {
                vtEl.readOnly = true;
                vtEl.classList.remove('heen-vt--auto');
                vtEl.dataset.timeEditable = '1';
                if (i === 1) {
                    vtEl.value = tv;
                    vtEl.title = 'Vertrek bij klant';
                } else if (vtEl.dataset.manual !== '1') {
                    vtEl.value = stopAankomsten[i - 2] || '';
                    vtEl.title = kind ? 'Vertrek voor retourregel' : 'Vertrek vanaf deze stop';
                }
            }
            if (atEl) {
                atEl.readOnly = true;
                atEl.classList.add('heen-at--auto');
                delete atEl.dataset.timeEditable;
                atEl.value = stopAankomsten[i - 1] || '';
                atEl.title = kind ? 'Automatische aankomsttijd voor retourregel' : 'Automatische aankomsttijd op deze stop';
            }
        }

        for (let i = activeRows.length; i < rows.length; i++) {
            const vtEl = rows[i].querySelector('.heen-vt');
            const atEl = rows[i].querySelector('.heen-at');
            if (vtEl) {
                vtEl.value = '';
                vtEl.readOnly = true;
                vtEl.classList.remove('heen-vt--auto');
                vtEl.dataset.timeEditable = '1';
                vtEl.title = 'Vul eerst een bestemming in';
            }
            if (atEl) {
                atEl.value = '';
                atEl.readOnly = true;
                atEl.classList.add('heen-at--auto');
                delete atEl.dataset.timeEditable;
                atEl.title = 'Vul eerst een bestemming in';
            }
        }
    }

    function ritType() {
        const el = document.getElementById('rittype_select');
        return el ? el.value : 'dagtocht';
    }

    function showZoneColumn() {
        const t = ritType();
        return t === 'meerdaags' || t === 'buitenland';
    }

    function syncZoneColumnVisibility() {
        const on = showZoneColumn();
        document.querySelectorAll('.heen-zone-col').forEach(function (c) {
            c.style.display = on ? '' : 'none';
        });
    }

    function getRows() {
        const tb = document.getElementById('heen_segmenten_body');
        return tb ? Array.from(tb.querySelectorAll('tr.heen-seg-row')) : [];
    }

    function normalizeAddr(s) {
        return String(s || '')
            .trim()
            .replace(/\s+/g, ' ');
    }

    const DEFAULT_GARAGE_ADDRESS = 'Industrieweg 95a, Zutphen';
    const LEGACY_GARAGE_ADDRESS = 'Industrieweg 95, Zutphen';

    function normalizeGarageAddress(s) {
        const value = normalizeAddr(s);
        if (!value) return '';
        return value.toLowerCase() === LEGACY_GARAGE_ADDRESS.toLowerCase()
            ? DEFAULT_GARAGE_ADDRESS
            : value;
    }

    function getGarageAddress(rows) {
        const list = Array.isArray(rows) ? rows : getRows();
        const row0 = list[0];
        const fromRow = row0 ? normalizeGarageAddress(row0.querySelector('.heen-van')?.value || '') : '';
        if (fromRow) return fromRow;
        return normalizeGarageAddress(document.getElementById('addr_t_garage')?.value || '');
    }

    function getKlantAddress(rows) {
        const list = Array.isArray(rows) ? rows : getRows();
        const row0 = list[0];
        const fromRow = row0 ? normalizeAddr(row0.querySelector('.heen-naar')?.value || '') : '';
        if (fromRow) return fromRow;
        return normalizeAddr(document.getElementById('addr_t_vertrek_klant')?.value || '');
    }

    function syncReturnRowTargets(rows) {
        const list = Array.isArray(rows) ? rows : getRows();
        const garage = getGarageAddress(list);
        const klant = getKlantAddress(list);
        list.forEach(function (row) {
            const naarEl = row.querySelector('.heen-naar');
            if (!naarEl || !row.dataset.returnKind) return;
            if (row.dataset.returnKind === 'rg' || row.dataset.returnKind === 'rk-garage') {
                if (garage) naarEl.value = garage;
            } else if (row.dataset.returnKind === 'rk-klant') {
                if (klant) naarEl.value = klant;
            }
        });
    }

    function removeReturnRows() {
        getRows().forEach(function (row) {
            if (row.dataset.returnKind) {
                row.remove();
            }
        });
    }

    function appendReturnRows(mode) {
        const beforeRows = getRows();
        const parts = getRowPartitions(beforeRows);
        const coreRows = parts.coreRows;
        const activeCount = coreRows.length;
        const lastCore = coreRows[coreRows.length - 1];
        const lastNaar = normalizeAddr(lastCore?.querySelector('.heen-naar')?.value || '');
        const garage = getGarageAddress(beforeRows);
        const klant = getKlantAddress(beforeRows);
        if (!lastNaar || !garage) return;

        removeReturnRows();

        if (mode === 'rg') {
            if (coreRows.length < 1) return;
            if (activeCount + 1 > MAX_SEG) return;
            addRow({ van: lastNaar, naar: garage, km: '0', zone: 'nl', return_kind: 'rg' });
        } else if (mode === 'rk') {
            if (coreRows.length < 2) return;
            if (!klant) return;
            if (activeCount + 2 > MAX_SEG) return;
            addRow({ van: lastNaar, naar: klant, km: '0', zone: 'nl', return_kind: 'rk-klant' });
            addRow({ van: klant, naar: garage, km: '0', zone: 'nl', return_kind: 'rk-garage' });
        }
        syncLegacyFromSegments();
    }

    function isRgChipActive() {
        const parts = getRowPartitions(getRows());
        return parts.returnRows.length === 1 && parts.returnRows[0].dataset.returnKind === 'rg';
    }

    /** Vertrek bestemming = heen-bestemming; uitstap = eerste klantvertrek (segment-naar rit 1). */
    function isRkChipActive() {
        const kinds = getRowPartitions(getRows()).returnRows.map(function (row) {
            return row.dataset.returnKind || '';
        });
        return kinds.length === 2 && kinds[0] === 'rk-klant' && kinds[1] === 'rk-garage';
    }

    function updateHeenOptChipStates() {
        const btnRg = document.getElementById('btn_heen_opt_rg');
        const btnRk = document.getElementById('btn_heen_opt_rk');
        if (btnRg) {
            const on = isRgChipActive();
            btnRg.classList.toggle('is-active', on);
            btnRg.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
        if (btnRk) {
            const on = isRkChipActive();
            btnRk.classList.toggle('is-active', on);
            btnRk.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    }

    /** Sync ketting: Van[i] = Naar[i-1] */
    function chainVanNaar() {
        const rows = getRows();
        for (let i = 1; i < rows.length; i++) {
            const prevNaar = rows[i - 1].querySelector('.heen-naar');
            const van = rows[i].querySelector('.heen-van');
            if (!prevNaar || !van) continue;
            const pv = prevNaar.value.trim();
            if (pv && van.value.trim() !== pv) {
                van.value = pv;
            }
        }
    }

    /**
     * Segmentwaarden → legacy addr/km/zone/chk grens2
     */
    function syncLegacyFromSegments() {
        const allRows = getRows();
        syncReturnRowTargets(allRows);
        chainVanNaar();
        const parts = getRowPartitions(allRows);
        const rows = parts.activeRows;
        const coreRows = parts.coreRows;
        const returnRows = parts.returnRows;
        const n = coreRows.length;
        if (n < 1) return;

        const firstVanEl = allRows[0] ? allRows[0].querySelector('.heen-van') : null;
        const normalizedGarage = getGarageAddress(allRows);
        if (firstVanEl && normalizedGarage && normalizeAddr(firstVanEl.value) !== normalizedGarage) {
            firstVanEl.value = normalizedGarage;
        }

        const seg = coreRows.map(function (row) {
            return {
                van: row.querySelector('.heen-van')?.value.trim() || '',
                naar: row.querySelector('.heen-naar')?.value.trim() || '',
                km: row.querySelector('.heen-km')?.value || '0',
                zone: row.querySelector('.heen-zone')?.value || 'nl',
                vt: row.querySelector('.heen-vt')?.value || '',
                at: row.querySelector('.heen-at')?.value || ''
            };
        });
        const retSeg = returnRows.map(function (row) {
            return {
                kind: row.dataset.returnKind || '',
                naar: row.querySelector('.heen-naar')?.value.trim() || '',
                km: row.querySelector('.heen-km')?.value || '0',
                zone: row.querySelector('.heen-zone')?.value || 'nl',
                at: row.querySelector('.heen-at')?.value || ''
            };
        });

        const setAddr = function (id, v) {
            const el = document.getElementById(id);
            if (el) el.value = v;
        };
        const setKm = function (nameKey, v) {
            const el = document.querySelector('input[name="km[' + nameKey + ']"]');
            if (el) el.value = v;
        };
        const setZoneSelectInRow = function (rowId, z) {
            const row = document.getElementById(rowId);
            if (!row) return;
            const sel = row.querySelector('.km-zone-select');
            if (sel && z) sel.value = z;
        };
        const setTime = function (id, v) {
            const el = document.getElementById(id);
            if (el) el.value = v;
        };

        setAddr('addr_t_garage', normalizeGarageAddress(seg[0].van));
        setAddr('addr_t_vertrek_klant', seg[0].naar);
        setKm('t_vertrek_klant', seg[0].km);
        setZoneSelectInRow('row_vertrek_klant', seg[0].zone);

        const tg = document.getElementById('time_t_garage');
        const tv = document.getElementById('time_t_vertrek_klant');
        const r0 = rows[0];
        const r1 = rows[1] && !rows[1].dataset.returnKind ? rows[1] : null;
        const vtEl = r0 ? r0.querySelector('.heen-vt') : null;
        const atEl = r0 ? r0.querySelector('.heen-at') : null;
        const leadVtEl = r1 ? r1.querySelector('.heen-vt') : null;
        if (tg && vtEl) {
            if (seg[0].vt) {
                tg.value = seg[0].vt;
                if (vtEl.dataset.manual !== '1') {
                    vtEl.value = seg[0].vt;
                }
            } else if (vtEl.value && vtEl.value.trim()) {
                tg.value = vtEl.value.trim().substring(0, 5);
            } else if (tg.value && tg.value.trim() && vtEl.dataset.manual !== '1') {
                vtEl.value = tg.value.trim().substring(0, 5);
            }
        }
        if (tv && leadVtEl) {
            if (seg[1] && seg[1].vt) {
                tv.value = seg[1].vt;
                leadVtEl.value = seg[1].vt;
            } else if (leadVtEl.value && leadVtEl.value.trim()) {
                tv.value = leadVtEl.value.trim().substring(0, 5);
            } else if (tv.value && tv.value.trim()) {
                leadVtEl.value = tv.value.trim().substring(0, 5);
            }
        } else if (tv) {
            tv.value = '';
        }
        if (n === 1 && atEl && atEl.dataset.manual !== '1') {
            atEl.value = seg[0].at || '';
        } else if (atEl && atEl.dataset.manual !== '1') {
            const lead = tv && tv.value && tv.value.trim() ? tv.value.trim().substring(0, 5) : '';
            atEl.value = lead ? hmMinusMinutes(lead, KLANT_VOORVERTREK_MIN) : '';
        }
        const chkG2 = document.getElementById('chk_grens2');
        const rowG2El = document.getElementById('row_grens2');

        setAddr('addr_t_retour_klant', '');
        setKm('t_retour_klant', '0');
        setZoneSelectInRow('row_retour_klant', 'nl');
        setTime('time_t_retour_klant', '');
        setAddr('addr_t_retour_garage_heen', '');
        setKm('t_retour_garage_heen', '0');
        setZoneSelectInRow('row_retour_garage_heen', 'nl');
        setTime('time_t_retour_garage_heen', '');

        if (n === 1) {
            setAddr('addr_t_voorstaan', '');
            setKm('t_voorstaan', '0');
            setTime('time_t_voorstaan', '');
            if (chkG2) chkG2.checked = false;
            setAddr('addr_t_grens2', '');
            setKm('t_grens2', '0');
            setTime('time_t_grens2', '');
            if (rowG2El) rowG2El.style.display = 'none';
            setAddr('addr_t_aankomst_best', '');
            setKm('t_aankomst_best', '0');
            setZoneSelectInRow('row_aankomst_best', 'nl');
            setTime('time_t_aankomst_best', seg[0].at || '');
        } else if (n === 2) {
            setAddr('addr_t_voorstaan', '');
            setKm('t_voorstaan', '0');
            if (chkG2) chkG2.checked = false;
            setAddr('addr_t_grens2', '');
            setKm('t_grens2', '0');
            if (rowG2El) rowG2El.style.display = 'none';
            setAddr('addr_t_aankomst_best', seg[1].naar);
            setKm('t_aankomst_best', seg[1].km);
            setZoneSelectInRow('row_aankomst_best', seg[1].zone);
            const tb = document.getElementById('time_t_aankomst_best');
            if (tb && seg[1].at) tb.value = seg[1].at;
        } else if (n === 3) {
            setAddr('addr_t_voorstaan', seg[1].naar);
            setKm('t_voorstaan', seg[1].km);
            setZoneSelectInRow('row_voorstaan', seg[1].zone);
            if (chkG2) chkG2.checked = false;
            setAddr('addr_t_grens2', '');
            setKm('t_grens2', '0');
            if (rowG2El) rowG2El.style.display = 'none';
            setAddr('addr_t_aankomst_best', seg[2].naar);
            setKm('t_aankomst_best', seg[2].km);
            setZoneSelectInRow('row_aankomst_best', seg[2].zone);
            const tb = document.getElementById('time_t_aankomst_best');
            if (tb && seg[2].at) tb.value = seg[2].at;
        } else if (n >= 4) {
            setAddr('addr_t_voorstaan', seg[1].naar);
            setKm('t_voorstaan', seg[1].km);
            setZoneSelectInRow('row_voorstaan', seg[1].zone);
            if (chkG2) chkG2.checked = true;
            const rowG2 = document.getElementById('row_grens2');
            if (rowG2) rowG2.style.display = 'flex';
            setAddr('addr_t_grens2', seg[2].naar);
            setKm('t_grens2', seg[2].km);
            setZoneSelectInRow('row_grens2', seg[2].zone);
            const last = seg[n - 1];
            setAddr('addr_t_aankomst_best', last.naar);
            setKm('t_aankomst_best', last.km);
            setZoneSelectInRow('row_aankomst_best', last.zone);
            const tb = document.getElementById('time_t_aankomst_best');
            if (tb && last.at) tb.value = last.at;
        }

        if (retSeg.length === 1 && retSeg[0].kind === 'rg') {
            setAddr('addr_t_retour_garage_heen', retSeg[0].naar);
            setKm('t_retour_garage_heen', retSeg[0].km);
            setZoneSelectInRow('row_retour_garage_heen', retSeg[0].zone);
            setTime('time_t_retour_garage_heen', retSeg[0].at);
        } else if (retSeg.length >= 2 && retSeg[0].kind === 'rk-klant' && retSeg[1].kind === 'rk-garage') {
            setAddr('addr_t_retour_klant', retSeg[0].naar);
            setKm('t_retour_klant', retSeg[0].km);
            setZoneSelectInRow('row_retour_klant', retSeg[0].zone);
            setTime('time_t_retour_klant', retSeg[0].at);
            setAddr('addr_t_retour_garage_heen', retSeg[1].naar);
            setKm('t_retour_garage_heen', retSeg[1].km);
            setZoneSelectInRow('row_retour_garage_heen', retSeg[1].zone);
            setTime('time_t_retour_garage_heen', retSeg[1].at);
        }

        applyAutoSegmentTijden(allRows);

        if (typeof window.calculateRoute === 'function') window.calculateRoute();
        if (typeof window.rekenen === 'function') window.rekenen();
        updateHeenOptChipStates();
        updateRouteV2HiddenInput();
    }

    function bindRow(row) {
        row.querySelectorAll('.heen-van, .heen-naar, .heen-km, .heen-zone, .heen-vt, .heen-at').forEach(function (el) {
            el.addEventListener('input', syncLegacyFromSegments);
            el.addEventListener('change', syncLegacyFromSegments);
        });
        const markManual = function (el) {
            if (!el) return;
            if (el.value && el.value.trim() !== '') {
                el.dataset.manual = '1';
            } else {
                delete el.dataset.manual;
            }
        };
        row.querySelectorAll('.heen-vt').forEach(function (el) {
            el.addEventListener('input', function () { markManual(el); });
            el.addEventListener('change', function () { markManual(el); });
        });
        if (row.classList.contains('heen-seg-first')) {
            const atEl = row.querySelector('.heen-at');
            if (atEl) {
                atEl.addEventListener('input', function () { markManual(atEl); });
                atEl.addEventListener('change', function () { markManual(atEl); });
            }
        }
        row.querySelectorAll('.heen-vt, .heen-at').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.dataset.timeEditable !== '1') return;
                e.preventDefault();
                if (typeof window.openTimeModal === 'function') window.openTimeModal(el);
            });
        });
        row.querySelectorAll('.heen-van, .heen-naar').forEach(function (el) {
            el.addEventListener('blur', chainVanNaar);
        });
    }

    function addRow(prefill) {
        const tb = document.getElementById('heen_segmenten_body');
        if (!tb) return;
        const p = prefill || {};
        const idx = tb.querySelectorAll('tr.heen-seg-row').length;
        const coreCount = getRows().filter(function (row) { return !row.dataset.returnKind; }).length;
        if (idx >= MAX_SEG) return;
        if (!p.return_kind && coreCount >= 4) return;
        const insertBefore = !p.return_kind
            ? Array.from(tb.querySelectorAll('tr.heen-seg-row')).find(function (row) { return !!row.dataset.returnKind; }) || null
            : null;

        const tr = document.createElement('tr');
        tr.className = 'heen-seg-row' + (idx === 0 ? ' heen-seg-first' : '');
        const zoneDisplay = showZoneColumn() ? '' : 'display:none';
        const tdAank = '<td class="heen-td-t"><input type="text" class="form-control custom-time-input heen-at reken-trigger" placeholder="--:--" readonly title="Vertrek bij klant"></td>';
        tr.innerHTML =
            '<td class="heen-td-t"><input type="text" class="form-control custom-time-input heen-vt reken-trigger" placeholder="--:--" readonly title="Vertrek vanuit garage"></td>' +
            '<td><input type="text" class="form-control google-autocomplete heen-van reken-trigger" placeholder="Van" autocomplete="off"></td>' +
            '<td><input type="text" class="form-control google-autocomplete heen-naar reken-trigger" placeholder="Naar" autocomplete="off"></td>' +
            tdAank +
            '<td class="heen-zone-col" style="' + zoneDisplay + '"><select class="form-control km-zone-select heen-zone reken-trigger" title="Zone">' +
            '<option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></td>' +
            '<td class="heen-td-km"><input type="number" class="form-control km-calc heen-km reken-trigger" step="0.1" min="0" value="0"></td>' +
            '<td class="heen-td-rm"><button type="button" class="btn-remove-bus heen-rm" title="Verwijder">&times;</button></td>';

        if (p.vertrektijd) tr.querySelector('.heen-vt').value = p.vertrektijd;
        if (p.aankomst_tijd) {
            const atIn = tr.querySelector('.heen-at');
            if (atIn) atIn.value = p.aankomst_tijd;
        }
        if (p.van) tr.querySelector('.heen-van').value = p.van;
        if (p.naar) tr.querySelector('.heen-naar').value = p.naar;
        if (p.km != null) tr.querySelector('.heen-km').value = String(p.km);
        if (p.zone) tr.querySelector('.heen-zone').value = String(p.zone);
        if (p.return_kind) tr.dataset.returnKind = String(p.return_kind);

        if (idx === 0) {
            tr.querySelector('.heen-van').placeholder = 'Garage';
            tr.querySelector('.heen-naar').placeholder = 'Klant (vertrek)';
            tr.querySelector('.heen-vt').title = 'Vertrek vanuit garage';
            tr.querySelector('.heen-at').title = 'Aankomst bij klant';
            const rm = tr.querySelector('.heen-rm');
            if (rm) {
                rm.style.visibility = 'hidden';
                rm.disabled = true;
            }
        } else if (idx === 1) {
            tr.querySelector('.heen-vt').title = 'Vertrek bij klant';
        }

        if (insertBefore) {
            tb.insertBefore(tr, insertBefore);
        } else {
            tb.appendChild(tr);
        }
        bindRow(tr);

        tr.querySelector('.heen-rm').addEventListener('click', function () {
            const remainingCore = getRows().filter(function (row) {
                return row !== tr && !row.dataset.returnKind;
            }).length;
            if (!tr.dataset.returnKind && remainingCore < 1) return;
            if (tb.querySelectorAll('tr.heen-seg-row').length <= 1) return;
            tr.remove();
            syncLegacyFromSegments();
        });

        if (window.google && google.maps && google.maps.places) {
            tr.querySelectorAll('.google-autocomplete').forEach(function (acEl) {
                try {
                    const ac = new google.maps.places.Autocomplete(acEl, { componentRestrictions: { country: ['nl', 'de', 'be', 'at', 'fr'] } });
                    ac.addListener('place_changed', function () {
                        syncLegacyFromSegments();
                    });
                } catch (e) {}
            });
        }
    }

    function bootFromData(rows) {
        const tb = document.getElementById('heen_segmenten_body');
        if (!tb) return;
        tb.innerHTML = '';
        if (rows && rows.length >= 1) {
            rows.forEach(function (r) {
                addRow(r);
            });
            if (rows.length === 1) {
                addRow({ van: '', naar: '', km: '0', zone: 'nl' });
            }
        } else {
            addRow({ van: DEFAULT_GARAGE_ADDRESS, naar: '', km: '0', zone: 'nl', vertrektijd: '' });
            addRow({ van: '', naar: '', km: '0', zone: 'nl' });
        }
        chainVanNaar();
        // Geen syncLegacyFromSegments() hier: die zette addr_t_vertrek_klant leeg zolang rij 1 "Naar"
        // nog leeg is, waardoor het adres uit fillKlantCard werd gewist vóór routeHeenRefreshFromLegacy().
        // Volledige legacy-sync gebeurt aan het eind van routeHeenSegmentenInit via routeHeenRefreshFromLegacy().
    }

    function wireOptieKnopen() {
        const btnRg = document.getElementById('btn_heen_opt_rg');
        const btnRk = document.getElementById('btn_heen_opt_rk');
        if (btnRg) {
            btnRg.addEventListener('click', function () {
                if (isRgChipActive()) {
                    removeReturnRows();
                    syncLegacyFromSegments();
                } else {
                    appendReturnRows('rg');
                }
            });
        }
        if (btnRk) {
            btnRk.addEventListener('click', function () {
                if (isRkChipActive()) {
                    removeReturnRows();
                    syncLegacyFromSegments();
                } else {
                    appendReturnRows('rk');
                }
            });
        }
        document.getElementById('btn_show_terugreis')?.addEventListener('click', function () {
            window.__calcTerugreisUserShow = true;
            const ab = document.getElementById('addr_t_aankomst_best');
            const vb = document.getElementById('addr_t_vertrek_best');
            if (ab && vb && !vb.value.trim()) {
                vb.value = ab.value.trim();
            }
            if (typeof window.updateVisibility === 'function') window.updateVisibility();
        });
        const refreshIds = [
            'addr_t_garage',
            'addr_t_retour_garage_heen',
            'addr_t_aankomst_best',
            'addr_t_vertrek_klant',
            'addr_t_vertrek_best',
            'addr_t_retour_klant'
        ];
        refreshIds.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', updateHeenOptChipStates);
            el.addEventListener('change', updateHeenOptChipStates);
        });
    }

    window.routeHeenSegmentenInit = function (bootRows) {
        const tb = document.getElementById('heen_segmenten_body');
        if (!tb) return;

        document.getElementById('btn_heen_seg_add')?.addEventListener('click', function () {
            addRow({});
            syncLegacyFromSegments();
        });

        const rt = document.getElementById('rittype_select');
        if (rt) {
            rt.addEventListener('change', function () {
                syncZoneColumnVisibility();
                syncLegacyFromSegments();
            });
        }

        bootFromData(bootRows || window.HEEN_SEGMENTS_BOOT || null);
        syncZoneColumnVisibility();
        wireOptieKnopen();
        updateHeenOptChipStates();
        const form = getMainFormEl();
        if (form) {
            const syncRouteV2 = function (e) {
                if (e && e.target && e.target.id === 'route_v2_json') return;
                updateRouteV2HiddenInput();
            };
            form.addEventListener('input', syncRouteV2, true);
            form.addEventListener('change', syncRouteV2, true);
            form.addEventListener('submit', function () {
                updateRouteV2HiddenInput();
            });
        }
        if (typeof window.routeHeenRefreshFromLegacy === 'function') {
            window.routeHeenRefreshFromLegacy();
        }
        if (typeof window.updateVisibility === 'function') window.updateVisibility();
        updateRouteV2HiddenInput();

        document.getElementById('chk_grens2')?.addEventListener('change', function () {
            syncLegacyFromSegments();
        });
    };

    window.syncHeenSegmentsFromLegacy = syncLegacyFromSegments;
    window.updateHeenOptChipStates = updateHeenOptChipStates;

    /**
     * Google Directions schrijft km naar legacy (#legacy_heen_mirror .rit-row).
     * Kopieer die km terug naar de zichtbare segmentrijen (zelfde mapping als syncLegacyFromSegments).
     */
    window.syncHeenSegmentDisplayFromLegacy = function () {
        const parts = getRowPartitions(getRows());
        const rows = parts.allRows;
        const activeRows = parts.activeRows;
        const coreRows = parts.coreRows;
        const returnRows = parts.returnRows;
        const n = coreRows.length;
        if (n < 1) return;

        const legacyKm = function (key) {
            const el = document.querySelector('input[name="km[' + key + ']"]');
            if (!el || el.value === '') return '0';
            return String(el.value);
        };
        const setRowKm = function (row, v) {
            const inp = row && row.querySelector('.heen-km');
            if (inp) inp.value = v;
        };

        setRowKm(coreRows[0], legacyKm('t_vertrek_klant'));
        if (n === 2) {
            setRowKm(coreRows[1], legacyKm('t_aankomst_best'));
        } else if (n === 3) {
            setRowKm(coreRows[1], legacyKm('t_voorstaan'));
            setRowKm(coreRows[2], legacyKm('t_aankomst_best'));
        } else {
            setRowKm(coreRows[1], legacyKm('t_voorstaan'));
            setRowKm(coreRows[2], legacyKm('t_grens2'));
            setRowKm(coreRows[n - 1], legacyKm('t_aankomst_best'));
        }

        if (returnRows.length === 1 && returnRows[0].dataset.returnKind === 'rg') {
            setRowKm(returnRows[0], legacyKm('t_retour_garage_heen'));
        } else if (returnRows.length >= 2) {
            setRowKm(returnRows[0], legacyKm('t_retour_klant'));
            setRowKm(returnRows[1], legacyKm('t_retour_garage_heen'));
        }

        for (let i = activeRows.length; i < rows.length; i++) {
            const kmEl = rows[i].querySelector('.heen-km');
            const vtEl = rows[i].querySelector('.heen-vt');
            const atEl = rows[i].querySelector('.heen-at');
            if (kmEl) kmEl.value = '0';
            if (vtEl) vtEl.value = '';
            if (atEl) atEl.value = '';
        }

        applyAutoSegmentTijden(rows);
        updateRouteV2HiddenInput();
    };

    /** Na klant/adres uit DB: verborgen velden → eerste segmenten bijwerken */
    window.routeHeenRefreshFromLegacy = function () {
        const rows = getRows();
        const vl = document.getElementById('addr_t_vertrek_klant');
        const bv = vl ? vl.value.trim() : '';
        if (rows[0] && bv) {
            const n0 = rows[0].querySelector('.heen-naar');
            const v1 = rows[1] ? rows[1].querySelector('.heen-van') : null;
            if (n0) n0.value = bv;
            if (v1) v1.value = bv;
        }
        syncLegacyFromSegments();
    };

    window.updateRouteV2HiddenInput = updateRouteV2HiddenInput;
})();
