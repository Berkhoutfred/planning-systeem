/**
 * Heenroute als segmenten-tabel; synct naar verborgen legacy calculatie_regels-velden.
 * Vereist: DOM met #heen_segmenten_body, legacy velden #addr_t_* met zelfde ids als voorheen.
 */
(function () {
    'use strict';

    const MAX_SEG = 4;
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

    /** Eerste segment: aankomst-kolom = vertrek klant − 15 min (readonly). */
    function applyFirstRowKlantTijden(rows) {
        const r0 = rows[0];
        if (!r0 || !r0.classList.contains('heen-seg-first')) return;
        const vtEl = r0.querySelector('.heen-vt');
        const atEl = r0.querySelector('.heen-at');
        const tvLegacy = document.getElementById('time_t_vertrek_klant');
        let vt = vtEl && vtEl.value && vtEl.value.trim() ? vtEl.value.trim().substring(0, 5) : '';
        if (!vt && tvLegacy && tvLegacy.value && tvLegacy.value.trim()) {
            vt = tvLegacy.value.trim().substring(0, 5);
        }
        if (vtEl && vt) {
            vtEl.value = vt;
            if (tvLegacy) tvLegacy.value = vt;
        }
        const naarEl = r0.querySelector('.heen-naar');
        const addrMirror = r0.querySelector('.heen-at-addr');
        if (addrMirror && naarEl) {
            addrMirror.value = naarEl.value.trim();
        }
        if (atEl) {
            atEl.readOnly = true;
            atEl.classList.add('heen-at--auto');
            atEl.value = vt ? hmMinusMinutes(vt, KLANT_VOORVERTREK_MIN) : '';
            atEl.title = 'Aankomsttijd bij klant (= vertrek − ' + KLANT_VOORVERTREK_MIN + ' min)';
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

    function isRgChipActive() {
        const gar = document.getElementById('addr_t_garage');
        const ret = document.getElementById('addr_t_retour_garage_heen');
        if (!gar || !ret) return false;
        const g = normalizeAddr(gar.value);
        const r = normalizeAddr(ret.value);
        return g !== '' && r === g;
    }

    /** Vertrek bestemming = heen-bestemming; uitstap = eerste klantvertrek (segment-naar rit 1). */
    function isRkChipActive() {
        const vb = document.getElementById('addr_t_vertrek_best');
        const ab = document.getElementById('addr_t_aankomst_best');
        const rk = document.getElementById('addr_t_retour_klant');
        const vl = document.getElementById('addr_t_vertrek_klant');
        if (!vb || !ab || !rk || !vl) return false;
        return (
            normalizeAddr(vb.value) === normalizeAddr(ab.value) &&
            normalizeAddr(rk.value) === normalizeAddr(vl.value) &&
            normalizeAddr(ab.value) !== ''
        );
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
        chainVanNaar();
        const rows = getRows();
        const n = rows.length;
        if (n < 2) return;

        const seg = rows.map(function (row) {
            return {
                van: row.querySelector('.heen-van')?.value.trim() || '',
                naar: row.querySelector('.heen-naar')?.value.trim() || '',
                km: row.querySelector('.heen-km')?.value || '0',
                zone: row.querySelector('.heen-zone')?.value || 'nl',
                vt: row.querySelector('.heen-vt')?.value || '',
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

        setAddr('addr_t_garage', seg[0].van);
        setAddr('addr_t_vertrek_klant', seg[0].naar);
        setKm('t_vertrek_klant', seg[0].km);
        setZoneSelectInRow('row_vertrek_klant', seg[0].zone);

        const tv = document.getElementById('time_t_vertrek_klant');
        const r0 = rows[0];
        const vtEl = r0 ? r0.querySelector('.heen-vt') : null;
        if (tv && vtEl) {
            if (seg[0].vt) {
                tv.value = seg[0].vt;
                vtEl.value = seg[0].vt;
            } else if (vtEl.value && vtEl.value.trim()) {
                tv.value = vtEl.value.trim().substring(0, 5);
            } else if (tv.value && tv.value.trim()) {
                vtEl.value = tv.value.trim().substring(0, 5);
            }
        }
        applyFirstRowKlantTijden(rows);

        const chkG2 = document.getElementById('chk_grens2');
        const rowG2El = document.getElementById('row_grens2');

        if (n === 2) {
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

        if (typeof window.calculateRoute === 'function') window.calculateRoute();
        if (typeof window.rekenen === 'function') window.rekenen();
        updateHeenOptChipStates();
    }

    function bindRow(row) {
        row.querySelectorAll('.heen-van, .heen-naar, .heen-km, .heen-zone, .heen-vt, .heen-at').forEach(function (el) {
            el.addEventListener('input', syncLegacyFromSegments);
            el.addEventListener('change', syncLegacyFromSegments);
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
        if (idx >= MAX_SEG) return;

        const tr = document.createElement('tr');
        tr.className = 'heen-seg-row' + (idx === 0 ? ' heen-seg-first' : '');
        const zoneDisplay = showZoneColumn() ? '' : 'display:none';
        const tdAank =
            idx === 0
                ? '<td class="heen-td-t heen-td-aankomst-split">' +
                  '<input type="text" class="form-control heen-at-addr" readonly tabindex="-1" placeholder="Klant (aankomst)" title="Zelfde adres als Naar op deze rij" />' +
                  '<input type="time" class="form-control heen-at reken-trigger" step="60" title="Aankomsttijd (vertrek − 15 min)" />' +
                  '</td>'
                : '<td class="heen-td-t"><input type="time" class="form-control heen-at reken-trigger" step="60" title="Aankomst bij Naar"></td>';
        tr.innerHTML =
            '<td class="heen-td-t"><input type="time" class="form-control heen-vt reken-trigger" step="60" title="Vertrek bij klant"></td>' +
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

        if (idx === 0) {
            tr.querySelector('.heen-van').placeholder = 'Garage';
            tr.querySelector('.heen-naar').placeholder = 'Klant (vertrek)';
            tr.querySelector('.heen-vt').title = 'Vertrek bij klant';
            const rm = tr.querySelector('.heen-rm');
            if (rm) {
                rm.style.visibility = 'hidden';
                rm.disabled = true;
            }
        }

        tb.appendChild(tr);
        bindRow(tr);

        tr.querySelector('.heen-rm').addEventListener('click', function () {
            if (tb.querySelectorAll('tr.heen-seg-row').length <= 2) return;
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
            addRow({ van: 'Industrieweg 95, Zutphen', naar: '', km: '0', zone: 'nl', vertrektijd: '08:00' });
            addRow({ van: '', naar: '', km: '0', zone: 'nl' });
        }
        chainVanNaar();
        syncLegacyFromSegments();
    }

    function wireOptieKnopen() {
        const btnRg = document.getElementById('btn_heen_opt_rg');
        const btnRk = document.getElementById('btn_heen_opt_rk');
        if (btnRg) {
            btnRg.addEventListener('click', function () {
                const garEl = document.getElementById('addr_t_garage');
                const retEl = document.getElementById('addr_t_retour_garage_heen');
                const gaddr = garEl ? garEl.value.trim() : '';
                if (!retEl) return;
                if (!gaddr) return;
                if (isRgChipActive()) {
                    retEl.value = '';
                } else {
                    retEl.value = garEl.value;
                }
                updateHeenOptChipStates();
                if (typeof window.calculateRoute === 'function') window.calculateRoute();
                if (typeof window.rekenen === 'function') window.rekenen();
            });
        }
        if (btnRk) {
            btnRk.addEventListener('click', function () {
                const vb = document.getElementById('addr_t_vertrek_best');
                const ab = document.getElementById('addr_t_aankomst_best');
                const rk = document.getElementById('addr_t_retour_klant');
                const vl = document.getElementById('addr_t_vertrek_klant');
                if (!vb || !ab || !rk || !vl) return;
                if (isRkChipActive()) {
                    vb.value = '';
                    rk.value = '';
                } else {
                    vb.value = ab.value;
                    rk.value = vl.value;
                    window.__calcTerugreisUserShow = true;
                    if (typeof window.updateVisibility === 'function') window.updateVisibility();
                }
                updateHeenOptChipStates();
                if (typeof window.calculateRoute === 'function') window.calculateRoute();
                if (typeof window.rekenen === 'function') window.rekenen();
            });
        }
        document.getElementById('btn_show_terugreis')?.addEventListener('click', function () {
            window.__calcTerugreisUserShow = true;
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
        if (typeof window.routeHeenRefreshFromLegacy === 'function') {
            window.routeHeenRefreshFromLegacy();
        }
        if (typeof window.updateVisibility === 'function') window.updateVisibility();

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
        const rows = getRows();
        const n = rows.length;
        if (n < 2) return;

        const legacyKm = function (key) {
            const el = document.querySelector('input[name="km[' + key + ']"]');
            if (!el || el.value === '') return '0';
            return String(el.value);
        };
        const setRowKm = function (idx, v) {
            const inp = rows[idx] && rows[idx].querySelector('.heen-km');
            if (inp) inp.value = v;
        };

        setRowKm(0, legacyKm('t_vertrek_klant'));
        if (n === 2) {
            setRowKm(1, legacyKm('t_aankomst_best'));
        } else if (n === 3) {
            setRowKm(1, legacyKm('t_voorstaan'));
            setRowKm(2, legacyKm('t_aankomst_best'));
        } else {
            setRowKm(1, legacyKm('t_voorstaan'));
            setRowKm(2, legacyKm('t_grens2'));
            setRowKm(n - 1, legacyKm('t_aankomst_best'));
        }
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
})();
