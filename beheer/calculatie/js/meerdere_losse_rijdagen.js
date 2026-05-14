/**
 * Meerdere losse rijdagen onder één offerte: extra travel-dagen in route_v2_json (na dag 1).
 * Zelfde segment-tabel als dag 1 (heen-seg-table) + Google Places zoals route_heen_segmenten.addRow.
 * Vereist route_heen_segmenten.js (window.__calc* helpers + updateRouteV2HiddenInput).
 */
(function () {
    'use strict';

    var MAX_EXTRA_DAYS = 6;
    var MAX_SEG = 6;
    var DEFAULT_GARAGE_ADDRESS = 'Industrieweg 95a, Zutphen';

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

    function readGarageFromMain() {
        var el = document.getElementById('addr_t_garage');
        var v = el && el.value ? el.value.trim() : '';
        return v || DEFAULT_GARAGE_ADDRESS;
    }

    function showZoneColumn() {
        var el = document.getElementById('rittype_select');
        var t = el ? el.value : 'dagtocht';
        return t === 'meerdaags' || t === 'buitenland';
    }

    function syncLosseZoneColumns() {
        var on = showZoneColumn();
        document.querySelectorAll('#calc_losse_rijdagen_rows .heen-zone-col').forEach(function (c) {
            c.style.display = on ? '' : 'none';
        });
    }

    function formatShortNlDate(iso) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(iso || ''))) {
            return '';
        }
        var p = String(iso).split('-');
        return p[2] + '-' + p[1] + '-' + p[0];
    }

    function refreshLosseDayDates(losseRow) {
        if (!losseRow) {
            return;
        }
        var dateEl = losseRow.querySelector('.lr-date');
        var iso = dateEl && dateEl.value ? dateEl.value.trim().substring(0, 10) : '';
        var label = formatShortNlDate(iso);
        losseRow.querySelectorAll('.heen-seg-date').forEach(function (td) {
            td.textContent = label;
        });
    }

    /** Ketting Van[i] = Naar[i-1] (zelfde tekst als vorige «Naar», o.a. na Google Places). */
    function chainLosseVanNaar(tbody, options) {
        if (!tbody) {
            return;
        }
        var rows = Array.from(tbody.querySelectorAll('tr.heen-seg-row'));
        var activeEl = options && options.preserveActiveAddress ? document.activeElement : null;
        var norm = typeof window.__calcNormalizeAddr === 'function' ? window.__calcNormalizeAddr : function (s) {
            return String(s || '').trim().replace(/\s+/g, ' ');
        };
        var i;
        for (i = 1; i < rows.length; i++) {
            var prevNaar = rows[i - 1].querySelector('.heen-naar');
            var van = rows[i].querySelector('.heen-van');
            if (!prevNaar || !van) {
                continue;
            }
            if (activeEl && van === activeEl) {
                continue;
            }
            var pv = prevNaar.value.trim();
            if (!pv) {
                continue;
            }
            if (norm(van.value) !== norm(pv)) {
                van.value = pv;
            }
        }
    }

    function applyLosseTijdenIfAvailable(tbody) {
        if (tbody && typeof window.__calcApplyLosseHeenSegmentTijden === 'function') {
            window.__calcApplyLosseHeenSegmentTijden(tbody);
        }
    }

    function bindLosseGooglePlaces(tr, tbody) {
        if (!window.google || !google.maps || !google.maps.places) {
            return;
        }
        tr.querySelectorAll('.google-autocomplete:not([readonly])').forEach(function (acEl) {
            try {
                var ac = new google.maps.places.Autocomplete(acEl, {
                    componentRestrictions: { country: ['nl', 'de', 'be', 'at', 'fr'] }
                });
                ac.addListener('place_changed', function () {
                    chainLosseVanNaar(tbody, {});
                    applyLosseTijdenIfAvailable(tbody);
                    if (typeof window.updateRouteV2HiddenInput === 'function') {
                        window.updateRouteV2HiddenInput();
                    }
                });
            } catch (e) {}
        });
    }

    function bindLosseHeenRow(tr, tbody) {
        var addressSyncTimer = null;
        var flushAddressSync = function () {
            if (addressSyncTimer) {
                clearTimeout(addressSyncTimer);
                addressSyncTimer = null;
            }
            chainLosseVanNaar(tbody, {});
            applyLosseTijdenIfAvailable(tbody);
            if (typeof window.updateRouteV2HiddenInput === 'function') {
                window.updateRouteV2HiddenInput();
            }
        };
        tr.querySelectorAll('.heen-km, .heen-zone').forEach(function (el) {
            el.addEventListener('input', flushAddressSync);
            el.addEventListener('change', flushAddressSync);
        });
        tr.querySelectorAll('.heen-naar').forEach(function (el) {
            el.addEventListener('input', function () {
                if (addressSyncTimer) {
                    clearTimeout(addressSyncTimer);
                }
                addressSyncTimer = setTimeout(function () {
                    addressSyncTimer = null;
                    chainLosseVanNaar(tbody, { preserveActiveAddress: true });
                    applyLosseTijdenIfAvailable(tbody);
                    if (typeof window.updateRouteV2HiddenInput === 'function') {
                        window.updateRouteV2HiddenInput();
                    }
                }, 450);
            });
            el.addEventListener('change', flushAddressSync);
            el.addEventListener('blur', function () {
                chainLosseVanNaar(tbody, {});
                applyLosseTijdenIfAvailable(tbody);
            });
        });
        var markManual = function (el) {
            if (!el) {
                return;
            }
            if (el.value && el.value.trim() !== '') {
                el.dataset.manual = '1';
            } else {
                delete el.dataset.manual;
            }
        };
        tr.querySelectorAll('.heen-vt').forEach(function (el) {
            var runVt = function () {
                markManual(el);
                applyLosseTijdenIfAvailable(tbody);
                if (typeof window.updateRouteV2HiddenInput === 'function') {
                    window.updateRouteV2HiddenInput();
                }
            };
            el.addEventListener('input', runVt);
            el.addEventListener('change', runVt);
        });
        tr.querySelectorAll('.heen-at').forEach(function (el) {
            var runAt = function () {
                markManual(el);
                applyLosseTijdenIfAvailable(tbody);
                if (typeof window.updateRouteV2HiddenInput === 'function') {
                    window.updateRouteV2HiddenInput();
                }
            };
            el.addEventListener('input', runAt);
            el.addEventListener('change', runAt);
        });
        tr.querySelectorAll('.heen-vt, .heen-at').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.dataset.timeEditable !== '1') {
                    return;
                }
                e.preventDefault();
                if (typeof window.openTimeModal === 'function') {
                    window.openTimeModal(el);
                }
            });
        });
        bindLosseGooglePlaces(tr, tbody);
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
        var rows = Array.from(tbody.querySelectorAll('tr.heen-seg-row'));
        var payload = [];
        rows.forEach(function (row, idx) {
            var returnKind = row.dataset.returnKind || '';
            payload.push({
                seq: idx + 1,
                day_index: 0,
                kind: segmentKindFromRow(row, idx),
                return_kind: returnKind,
                from: row.querySelector('.heen-van') ? row.querySelector('.heen-van').value.trim() : '',
                to: row.querySelector('.heen-naar') ? row.querySelector('.heen-naar').value.trim() : '',
                depart_at: row.querySelector('.heen-vt') ? row.querySelector('.heen-vt').value.trim().substring(0, 5) : '',
                arrive_at: row.querySelector('.heen-at') ? row.querySelector('.heen-at').value.trim().substring(0, 5) : '',
                km: parseFloat(row.querySelector('.heen-km') ? row.querySelector('.heen-km').value : '0') || 0,
                zone: row.querySelector('.heen-zone') ? row.querySelector('.heen-zone').value || 'nl' : 'nl'
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

    function segmentToPrefill(seg) {
        return {
            van: seg.from != null ? String(seg.from) : '',
            naar: seg.to != null ? String(seg.to) : '',
            km: seg.km != null ? seg.km : 0,
            zone: seg.zone || 'nl',
            return_kind: String(seg.return_kind || '').trim(),
            vertrektijd: seg.depart_at || seg.vertrektijd || '',
            aankomst_tijd: seg.arrive_at || seg.aankomst_tijd || ''
        };
    }

    /**
     * Zelfde DOM-structuur als route_heen_segmenten.addRow, maar in een losse tbody.
     */
    function createLosseHeenSegmentRow(tb, prefill) {
        if (!tb) {
            return null;
        }
        var p = prefill || {};
        var idx = tb.querySelectorAll('tr.heen-seg-row').length;
        var coreCount = Array.from(tb.querySelectorAll('tr.heen-seg-row')).filter(function (row) {
            return !row.dataset.returnKind;
        }).length;
        if (idx >= MAX_SEG) {
            return null;
        }
        if (!p.return_kind && coreCount >= 4) {
            return null;
        }
        var insertBefore = !p.return_kind
            ? Array.from(tb.querySelectorAll('tr.heen-seg-row')).find(function (row) {
                return !!row.dataset.returnKind;
            }) || null
            : null;

        var tr = document.createElement('tr');
        tr.className = 'heen-seg-row' + (idx === 0 ? ' heen-seg-first' : '');
        var zoneDisplay = showZoneColumn() ? '' : 'display:none';
        var tdAank = '<td class="heen-td-t"><input type="text" class="form-control custom-time-input heen-at reken-trigger" placeholder="--:--" readonly title="Aankomst bij klant"></td>';
        tr.innerHTML =
            '<td class="heen-td-d heen-seg-date"></td>' +
            '<td class="heen-td-t"><input type="text" class="form-control custom-time-input heen-vt reken-trigger" placeholder="--:--" readonly title="Vertrek"></td>' +
            '<td><input type="text" class="form-control google-autocomplete heen-van reken-trigger" placeholder="Van" autocomplete="off"></td>' +
            '<td><input type="text" class="form-control google-autocomplete heen-naar reken-trigger" placeholder="Naar" autocomplete="off"></td>' +
            tdAank +
            '<td class="heen-zone-col" style="' + zoneDisplay + '"><select class="form-control km-zone-select heen-zone reken-trigger" title="Zone">' +
            '<option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></td>' +
            '<td class="heen-td-km"><input type="number" class="form-control km-calc heen-km reken-trigger" step="0.1" min="0" value="0"></td>' +
            '<td class="heen-td-rm"><button type="button" class="btn-remove-bus heen-rm" title="Verwijder">&times;</button></td>';

        if (p.vertrektijd) {
            tr.querySelector('.heen-vt').value = p.vertrektijd;
        }
        if (p.aankomst_tijd) {
            var atIn = tr.querySelector('.heen-at');
            if (atIn) {
                atIn.value = p.aankomst_tijd;
            }
        }
        if (p.van) {
            tr.querySelector('.heen-van').value = p.van;
        }
        if (p.naar) {
            tr.querySelector('.heen-naar').value = p.naar;
        }
        if (p.km != null) {
            tr.querySelector('.heen-km').value = String(p.km);
        }
        if (p.zone) {
            tr.querySelector('.heen-zone').value = String(p.zone);
        }
        if (p.return_kind) {
            tr.dataset.returnKind = String(p.return_kind);
        }

        var vanInput = tr.querySelector('.heen-van');
        var naarInput = tr.querySelector('.heen-naar');
        if (vanInput) {
            vanInput.readOnly = true;
            vanInput.title = idx === 0 ? 'Automatische garage/startlocatie' : 'Automatisch vanaf vorige locatie';
        }
        if (p.return_kind && naarInput) {
            naarInput.readOnly = true;
            naarInput.title = 'Automatisch doel voor retourregel';
        }

        if (idx === 0) {
            if (!p.van || !String(p.van).trim()) {
                vanInput.value = readGarageFromMain();
            }
            tr.querySelector('.heen-van').placeholder = 'Garage';
            tr.querySelector('.heen-naar').placeholder = 'Klant (vertrek)';
            tr.querySelector('.heen-vt').title = 'Vertrek vanuit garage';
            tr.querySelector('.heen-at').title = 'Aankomst bij klant';
            tr.querySelector('.heen-vt').dataset.timeEditable = '1';
            tr.querySelector('.heen-at').dataset.timeEditable = '1';
            var rm = tr.querySelector('.heen-rm');
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

        bindLosseHeenRow(tr, tb);

        tr.querySelector('.heen-rm').addEventListener('click', function () {
            if (tr.classList.contains('heen-seg-first')) {
                return;
            }
            var remainingCore = Array.from(tb.querySelectorAll('tr.heen-seg-row')).filter(function (row) {
                return row !== tr && !row.dataset.returnKind;
            }).length;
            if (!tr.dataset.returnKind && remainingCore < 1) {
                return;
            }
            if (tb.querySelectorAll('tr.heen-seg-row').length <= 1) {
                return;
            }
            tr.remove();
            chainLosseVanNaar(tb, {});
            applyLosseTijdenIfAvailable(tb);
            refreshLosseDayDates(tb.closest('.calc-losse-rijdag-row'));
            if (typeof window.updateRouteV2HiddenInput === 'function') {
                window.updateRouteV2HiddenInput();
            }
        });

        chainLosseVanNaar(tb, {});
        applyLosseTijdenIfAvailable(tb);
        return tr;
    }

    function fillLosseTbody(tbody, segments) {
        tbody.innerHTML = '';
        if (!segments || !segments.length) {
            return;
        }
        segments.forEach(function (seg) {
            createLosseHeenSegmentRow(tbody, segmentToPrefill(seg));
        });
        syncLosseZoneColumns();
        refreshLosseDayDates(tbody.closest('.calc-losse-rijdag-row'));
        applyLosseTijdenIfAvailable(tbody);
    }

    function defaultDateForNewRow() {
        var wrap = document.getElementById('calc_losse_rijdagen_rows');
        var last = wrap ? wrap.querySelector('.calc-losse-rijdag-row:last-of-type .lr-date') : null;
        var lastVal = last && last.value ? last.value : '';
        var base = lastVal || readRitDatum();
        return addIsoDays(base, 1);
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
            '<div class="route-compact heen-segment-wrap losse-heen-wrap" style="background:#fdfdfd;padding:8px 10px;border:1px solid #eee;border-radius:4px;">' +
            '<table class="heen-seg-table"><thead><tr>' +
            '<th class="heen-td-d" scope="col" aria-label="Dag"></th>' +
            '<th class="heen-td-t">Vertrek</th><th>Van</th><th>Naar</th><th class="heen-td-t">Aankomst</th>' +
            '<th class="heen-zone-col" style="display:none;">Zone</th><th class="heen-td-km">Km</th><th class="heen-td-rm"></th>' +
            '</tr></thead><tbody class="lr-seg-body"></tbody></table>' +
            '<button type="button" class="btn-add-bus btn-lr-seg-add" style="margin-top:8px;font-size:11px;padding:4px 10px;">+ regel</button>' +
            '</div>';
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
        refreshLosseDayDates(row);
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
            var addSeg = t.closest('.btn-lr-seg-add');
            if (addSeg) {
                var lr = addSeg.closest('.calc-losse-rijdag-row');
                var tb = lr && lr.querySelector('.lr-seg-body');
                if (tb) {
                    createLosseHeenSegmentRow(tb, {});
                    applyLosseTijdenIfAvailable(tb);
                    refreshLosseDayDates(lr);
                    triggerRouteV2Sync();
                }
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
                var tb2 = row.querySelector('.lr-seg-body');
                copyMainRouteIntoTbody(tb2);
                triggerRouteV2Sync();
            }
        });
        wrap.addEventListener('input', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('lr-date')) {
                refreshLosseDayDates(e.target.closest('.calc-losse-rijdag-row'));
            }
            triggerRouteV2Sync();
        });
        wrap.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('lr-date')) {
                refreshLosseDayDates(e.target.closest('.calc-losse-rijdag-row'));
            }
            triggerRouteV2Sync();
        });
        var rt = document.getElementById('rittype_select');
        if (rt) {
            rt.addEventListener('change', function () {
                syncLosseZoneColumns();
            });
        }
        document.getElementById('rit_datum')?.addEventListener('change', function () {
            wrap.querySelectorAll('.calc-losse-rijdag-row').forEach(refreshLosseDayDates);
        });
        syncLosseZoneColumns();
        updateLosseAddButtonState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindLosseRijdagenUi);
    } else {
        bindLosseRijdagenUi();
    }
})();
