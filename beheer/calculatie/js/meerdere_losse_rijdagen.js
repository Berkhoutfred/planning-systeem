/**
 * Meerdere losse rijdagen onder één offerte: extra travel-dagen in route_v2_json (na dag 1).
 * Vereist route_heen_segmenten.js (window.__calc* helpers + updateRouteV2HiddenInput).
 */
(function () {
    'use strict';

    var MAX_EXTRA_DAYS = 4;

    function addIsoDays(dateStr, days) {
        var value = String(dateStr || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return '';
        }
        var base = new Date(value + 'T00:00:00');
        if (Number.isNaN(base.getTime())) {
            return value;
        }
        base.setDate(base.getDate() + days);
        var y = base.getFullYear();
        var m = String(base.getMonth() + 1).padStart(2, '0');
        var d = String(base.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function readRitDatum() {
        var el = document.getElementById('rit_datum');
        return el && el.value ? String(el.value).trim().substring(0, 10) : '';
    }

    function showZoneColumn() {
        var el = document.getElementById('rittype_select');
        var t = el ? el.value : 'dagtocht';
        return t === 'meerdaags' || t === 'buitenland';
    }

    function syncLosseZoneColumns() {
        var on = showZoneColumn();
        document.querySelectorAll('.lr-zone-col').forEach(function (c) {
            c.style.display = on ? '' : 'none';
        });
    }

    function segmentKindFromRow(row, idx) {
        var rk = row.dataset.returnKind || '';
        if (rk === 'rg') {
            return 'return_to_garage';
        }
        if (rk === 'rk-klant') {
            return 'return_to_customer';
        }
        if (rk === 'rk-garage') {
            return 'return_to_garage';
        }
        return idx === 0 ? 'garage_to_customer' : 'stop';
    }

    function readLosseSegmentsFromTbody(tbody) {
        if (!tbody) {
            return [];
        }
        var rows = Array.from(tbody.querySelectorAll('tr.lr-seg-row'));
        var payload = [];
        rows.forEach(function (row, idx) {
            var returnKind = row.dataset.returnKind || '';
            payload.push({
                seq: idx + 1,
                day_index: 0,
                kind: segmentKindFromRow(row, idx),
                return_kind: returnKind,
                from: row.querySelector('.lr-van') ? row.querySelector('.lr-van').value.trim() : '',
                to: row.querySelector('.lr-naar') ? row.querySelector('.lr-naar').value.trim() : '',
                depart_at: row.querySelector('.lr-vt') ? row.querySelector('.lr-vt').value.trim().substring(0, 5) : '',
                arrive_at: row.querySelector('.lr-at') ? row.querySelector('.lr-at').value.trim().substring(0, 5) : '',
                km: parseFloat(row.querySelector('.lr-km') ? row.querySelector('.lr-km').value : '0') || 0,
                zone: row.querySelector('.lr-zone') ? row.querySelector('.lr-zone').value || 'nl' : 'nl'
            });
        });
        while (payload.length > 2) {
            var last = payload[payload.length - 1];
            if ((last.to || '') !== '' || (last.from || '') !== '' || last.km > 0) {
                break;
            }
            payload.pop();
        }
        return payload;
    }

    function segmentToDomRow(seg) {
        var rk = String(seg.return_kind || '').trim();
        var from = String(seg.from || '').trim();
        var to = String(seg.to || '').trim();
        var dep = String(seg.depart_at || seg.vertrektijd || '').trim().substring(0, 5);
        var arr = String(seg.arrive_at || seg.aankomst_tijd || '').trim().substring(0, 5);
        var km = seg.km != null ? String(seg.km) : '0';
        var zone = String(seg.zone || 'nl').trim() || 'nl';
        var tr = document.createElement('tr');
        tr.className = 'lr-seg-row';
        if (rk) {
            tr.dataset.returnKind = rk;
        }
        tr.innerHTML =
            '<td><input type="text" class="form-control lr-van reken-trigger" placeholder="Van" autocomplete="off" /></td>' +
            '<td><input type="text" class="form-control lr-naar reken-trigger google-autocomplete" placeholder="Naar" autocomplete="off" /></td>' +
            '<td><input type="text" class="form-control lr-vt reken-trigger" placeholder="--:--" /></td>' +
            '<td><input type="text" class="form-control lr-at reken-trigger" placeholder="--:--" /></td>' +
            '<td class="lr-zone-col" style="display:none;"><select class="form-control lr-zone reken-trigger">' +
            '<option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></td>' +
            '<td><input type="number" class="form-control lr-km km-calc reken-trigger" step="0.1" min="0" value="0" /></td>';
        tr.querySelector('.lr-van').value = from;
        tr.querySelector('.lr-naar').value = to;
        tr.querySelector('.lr-vt').value = dep;
        tr.querySelector('.lr-at').value = arr;
        tr.querySelector('.lr-km').value = km;
        var zel = tr.querySelector('.lr-zone');
        if (zel) {
            zel.value = zone;
        }
        return tr;
    }

    function fillLosseTbody(tbody, segments) {
        tbody.innerHTML = '';
        if (!segments || !segments.length) {
            return;
        }
        segments.forEach(function (seg) {
            tbody.appendChild(segmentToDomRow(seg));
        });
        syncLosseZoneColumns();
    }

    function defaultDateForNewRow() {
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        var last = wrap ? wrap.querySelector('.calc-losse-rijdag-row:last-of-type .lr-date') : null;
        var lastVal = last && last.value ? last.value : '';
        var base = lastVal || readRitDatum();
        return addIsoDays(base, lastVal ? 1 : 1);
    }

    function copyMainRouteIntoTbody(tbody) {
        var fn = window.__calcBuildRoute1SegmentsPayload;
        if (typeof fn !== 'function') {
            return;
        }
        var segments = fn();
        fillLosseTbody(tbody, segments);
    }

    function createLosseRow(opts) {
        opts = opts || {};
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        if (!wrap) {
            return null;
        }
        var row = document.createElement('div');
        row.className = 'calc-losse-rijdag-row';
        var dateVal = opts.date || defaultDateForNewRow();
        row.innerHTML =
            '<div class="lr-row-head">' +
            '<div class="lr-row-date"><label>Datum rit</label>' +
            '<input type="date" class="form-control lr-date reken-trigger" /></div>' +
            '<div class="lr-row-actions">' +
            '<button type="button" class="btn-lr-copy">Zelfde route als dag 1</button>' +
            '<button type="button" class="btn-lr-remove">Verwijderen</button></div></div>' +
            '<table class="lr-seg-table"><thead><tr>' +
            '<th>Van</th><th>Naar</th><th>Vertrek</th><th>Aankomst</th>' +
            '<th class="lr-zone-col" style="display:none;">Zone</th><th>Km</th></tr></thead><tbody class="lr-seg-body"></tbody></table>';
        var dateInp = row.querySelector('.lr-date');
        if (dateInp) {
            dateInp.value = String(dateVal).substring(0, 10);
        }
        var tb = row.querySelector('.lr-seg-body');
        if (opts.segments && opts.segments.length) {
            fillLosseTbody(tb, opts.segments);
        } else {
            copyMainRouteIntoTbody(tb);
        }
        wrap.appendChild(row);
        syncLosseZoneColumns();
        return row;
    }

    function countLosseRows() {
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        return wrap ? wrap.querySelectorAll('.calc-losse-rijdag-row').length : 0;
    }

    function collectLossePakketState() {
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        var rows = [];
        if (!wrap) {
            return { rows: rows };
        }
        wrap.querySelectorAll('.calc-losse-rijdag-row').forEach(function (el) {
            var dateEl = el.querySelector('.lr-date');
            var date = dateEl && dateEl.value ? dateEl.value.trim().substring(0, 10) : '';
            var tb = el.querySelector('.lr-seg-body');
            var segments = readLosseSegmentsFromTbody(tb);
            rows.push({ date: date, segments: segments });
        });
        return { rows: rows };
    }

    function updateLosseAddButtonState() {
        var btn = document.getElementById('btn_calc_losse_rijdag_add');
        if (!btn) {
            return;
        }
        btn.disabled = countLosseRows() >= MAX_EXTRA_DAYS;
    }

    function triggerRouteV2Sync() {
        if (typeof window.updateRouteV2HiddenInput === 'function') {
            window.updateRouteV2HiddenInput();
        }
    }

    function clearLosseRows() {
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        if (wrap) {
            wrap.innerHTML = '';
        }
        updateLosseAddButtonState();
    }

    function setPanelVisible(on) {
        var inner = document.getElementById('block_losse_rijdagen_inner');
        if (inner) {
            inner.style.display = on ? '' : 'none';
        }
    }

    window.mergeCalcLosseRijdagenIntoPayload = function (payload) {
        if (!payload || typeof payload !== 'object') {
            return payload;
        }
        var cb = document.getElementById('calc_losse_rijdagen_enabled');
        var pack = collectLossePakketState();
        payload.flags = payload.flags || {};
        if (!cb || !cb.checked) {
            payload.flags.losse_rijdagen_pakket = false;
            return payload;
        }
        var validRows = pack.rows.filter(function (r) {
            return r.date && r.segments && r.segments.length > 0;
        });
        if (!validRows.length) {
            payload.flags.losse_rijdagen_pakket = false;
            return payload;
        }
        var enrich = window.__calcEnrichRoute1ForPlanner;
        var resolveEnd = window.__calcResolvePlannerEndDate;
        if (typeof enrich !== 'function' || typeof resolveEnd !== 'function') {
            payload.flags.losse_rijdagen_pakket = false;
            return payload;
        }
        payload.flags.losse_rijdagen_pakket = true;
        var days = Array.isArray(payload.days) ? payload.days.slice() : [];
        var firstTravelIdx = days.findIndex(function (d) {
            return d && d.kind === 'travel';
        });
        if (firstTravelIdx < 0) {
            return payload;
        }
        var head = days.slice(0, firstTravelIdx + 1);
        var tail = days.slice(firstTravelIdx + 1);
        var baseRoute1 = payload.route1 || { label: 'Route 1', return_mode: '', segments: [] };
        var inserted = [];
        validRows.forEach(function (row, idx) {
            var route1Clone = {
                label: 'Route 1',
                return_mode: baseRoute1.return_mode || '',
                segments: row.segments
            };
            var enriched = enrich(route1Clone);
            inserted.push({
                seq: 0,
                day_index: 0,
                date: row.date,
                kind: 'travel',
                label: 'Dag ' + String(idx + 2),
                routes: [enriched],
                events: []
            });
        });
        var merged = head.concat(inserted, tail);
        merged.forEach(function (d, i) {
            d.seq = i + 1;
            d.day_index = i;
        });
        payload.days = merged;
        payload.dates = payload.dates || {};
        var prevEnd = payload.dates.end || payload.dates.start || '';
        payload.dates.end = resolveEnd(merged, prevEnd);
        return payload;
    };

    window.calcLosseRijdagenBootFromPayload = function (payload) {
        var cb = document.getElementById('calc_losse_rijdagen_enabled');
        if (!cb) {
            return;
        }
        clearLosseRows();
        cb.checked = false;
        setPanelVisible(false);
        if (!payload || !payload.days || !payload.flags || !payload.flags.losse_rijdagen_pakket) {
            updateLosseAddButtonState();
            return;
        }
        var travelDays = payload.days.filter(function (d) {
            return d && d.kind === 'travel';
        });
        if (travelDays.length < 2) {
            updateLosseAddButtonState();
            return;
        }
        cb.checked = true;
        setPanelVisible(true);
        travelDays.slice(1).forEach(function (day) {
            var r1 = null;
            (day.routes || []).forEach(function (r) {
                if (r1) {
                    return;
                }
                var idx = r && r.route_index != null ? parseInt(r.route_index, 10) : 0;
                var code = String(r && r.code ? r.code : '').toUpperCase();
                if (idx === 1 || code === 'R1') {
                    r1 = r;
                }
            });
            if (!r1 || !Array.isArray(r1.segments) || !r1.segments.length) {
                return;
            }
            var segs = r1.segments.map(function (s) {
                return Object.assign({}, s);
            });
            createLosseRow({ date: day.date || '', segments: segs });
        });
        if (!countLosseRows()) {
            cb.checked = false;
            setPanelVisible(false);
        }
        updateLosseAddButtonState();
        syncLosseZoneColumns();
    };

    function bindLosseRijdagenUi() {
        var cb = document.getElementById('calc_losse_rijdagen_enabled');
        var inner = document.getElementById('block_losse_rijdagen_inner');
        var btnAdd = document.getElementById('btn_calc_losse_rijdag_add');
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        if (!cb || !inner || !btnAdd || !wrap) {
            return;
        }
        cb.addEventListener('change', function () {
            setPanelVisible(cb.checked);
            if (!cb.checked) {
                clearLosseRows();
            } else if (!countLosseRows()) {
                createLosseRow({});
            }
            triggerRouteV2Sync();
            updateLosseAddButtonState();
        });
        btnAdd.addEventListener('click', function () {
            if (countLosseRows() >= MAX_EXTRA_DAYS) {
                return;
            }
            createLosseRow({});
            triggerRouteV2Sync();
            updateLosseAddButtonState();
        });
        wrap.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }
            var row = t.closest('.calc-losse-rijdag-row');
            if (!row) {
                return;
            }
            if (t.classList.contains('btn-lr-remove')) {
                row.remove();
                triggerRouteV2Sync();
                updateLosseAddButtonState();
                return;
            }
            if (t.classList.contains('btn-lr-copy')) {
                var tb = row.querySelector('.lr-seg-body');
                copyMainRouteIntoTbody(tb);
                triggerRouteV2Sync();
            }
        });
        wrap.addEventListener('input', function () {
            triggerRouteV2Sync();
        });
        wrap.addEventListener('change', function () {
            triggerRouteV2Sync();
        });
        var rt = document.getElementById('rittype_select');
        if (rt) {
            rt.addEventListener('change', function () {
                syncLosseZoneColumns();
            });
        }
        syncLosseZoneColumns();
        updateLosseAddButtonState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindLosseRijdagenUi);
    } else {
        bindLosseRijdagenUi();
    }
})();
