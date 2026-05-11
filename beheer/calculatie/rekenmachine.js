/**
 * Bestand: beheer/calculatie/rekenmachine.js
 * Versie: MASTER 3.3 - Definitieve Adres & Contact Fix
 */

let activeTimeInput = null;
let userManuallyChangedPrice = false; 
let directionsService; 
let reisTijden = {}; 
const BUS_FACTOR = 1.15;      
const BUFFER_VOORSTAAN = 15;  
const BUFFER_NAZORG = 15;     

/** Zichtbare route-Km / zones (segment-tabel); niet de verborgen legacy POST-spiegel (#legacy_heen_mirror). */
function isRouteKmInputForTotals(el) {
    return el && el.matches('.km-calc') && !el.closest('#legacy_heen_mirror');
}

window.startHetSysteem = function() {
    directionsService = new google.maps.DirectionsService();
    init();
}

function init() {
    const typeSelect = document.getElementById('rittype_select');
    if(typeSelect) {
        typeSelect.addEventListener('change', function() {
            updateVisibility();
            calculateRoute(); 
        });
    }

    document.querySelectorAll('.google-autocomplete').forEach(el => {
        const ac = new google.maps.places.Autocomplete(el);
        ac.addListener('place_changed', () => {
            // Autocopy logica voor dagtochten
            if (el.id === 'addr_t_aankomst_best') {
                const type = document.getElementById('rittype_select').value;
                const terugVeld = document.getElementById('addr_t_vertrek_best');
                if (
                    (type === 'dagtocht' || type === 'schoolreis') &&
                    terugVeld &&
                    window.__calcTerugreisUserShow === true
                ) {
                    if (!terugVeld.value) {
                        terugVeld.value = el.value;
                    }
                }
            }
            calculateRoute(); 
        });
    });

    const klantSelect = document.getElementById('klant_select');
    if (klantSelect) {
        // Direct triggeren bij laden als er al een waarde is
        if(klantSelect.value) {
            fillKlantCard(klantSelect);
            loadContacts(klantSelect.value);
        }
        
        // Triggeren bij wijziging
        klantSelect.addEventListener('change', function() {
            fillKlantCard(this);
            loadContacts(this.value);
        });
    }

    document.querySelectorAll('.reken-trigger').forEach(el => {
        el.addEventListener('input', () => { userManuallyChangedPrice = false; rekenen(); });
        el.addEventListener('change', () => { userManuallyChangedPrice = false; rekenen(); });
        if(el.classList.contains('google-autocomplete')) el.addEventListener('change', calculateRoute);
    });
    
    document.querySelectorAll('.fiscal-calc').forEach(el => { el.addEventListener('input', rekenen); });

    const prijsVeld = document.getElementById('verkoopprijs');
    if (prijsVeld) { 
        prijsVeld.addEventListener('input', () => { userManuallyChangedPrice = true; rekenen(); }); 
    }

    document.querySelectorAll('.custom-time-input').forEach(input => {
        input.addEventListener('click', function(e) { e.preventDefault(); openTimeModal(this); });
    });
    /** Segment-tabel (#heen_segmenten_body): rijen worden dynamisch toegevoegd — modal hier apart koppelen. */
    document.addEventListener(
        'click',
        function (e) {
            const el = e.target.closest('input.heen-vt, input.heen-at');
            if (!el || !document.getElementById('heen_segmenten_body')?.contains(el)) {
                return;
            }
            if (el.dataset.timeEditable !== '1') {
                return;
            }
            e.preventDefault();
            openTimeModal(el);
        },
        true
    );
    document.getElementById('closeModalBtn').addEventListener('click', closeTimeModal);

    document.addEventListener('change', function (e) {
        const row = e.target.closest && e.target.closest('.tz-row');
        if (!row) return;
        if (e.target.matches('[name="tussendagen_van[]"], [name="tussendagen_naar[]"]')) {
            calculateTussendagenKm(row);
        }
    });

    document.getElementById('chk_grens2')?.addEventListener('change', function () {
        const row = document.getElementById('row_grens2');
        if (row) row.style.display = this.checked ? 'flex' : 'none';
        calculateRoute();
        rekenen();
    });
    (function bootGrens2Ui() {
        const chk = document.getElementById('chk_grens2');
        const row = document.getElementById('row_grens2');
        const addr = document.getElementById('addr_t_grens2');
        if (!chk || !row) return;
        if (addr && addr.value.trim() !== '') chk.checked = true;
        row.style.display = chk.checked ? 'flex' : 'none';
    })();

    rekenen();
    tryInitialRouteIfNoKm();
    if (typeof window.calculatieExtrasAfterInit === 'function') {
        window.calculatieExtrasAfterInit();
    }
    if (typeof window.routeHeenSegmentenInit === 'function') {
        window.routeHeenSegmentenInit(typeof window.HEEN_SEGMENTS_BOOT !== 'undefined' ? window.HEEN_SEGMENTS_BOOT : null);
    }
    updateVisibility();
}

/** Eénmalig na laden: als alle zichtbare km-regels nog 0 zijn en kernadressen staan, routes laten berekenen (o.a. wizard-import). */
function tryInitialRouteIfNoKm() {
    setTimeout(function () {
        let sum = 0;
        document.querySelectorAll('.km-calc').forEach(function (i) {
            if (!isRouteKmInputForTotals(i)) return;
            if (i.offsetParent !== null) {
                sum += parseFloat(i.value) || 0;
            }
        });
        const vg = document.getElementById('addr_t_vertrek_klant');
        const ab = document.getElementById('addr_t_aankomst_best');
        if (sum < 0.5 && vg && ab && vg.value.trim() !== '' && ab.value.trim() !== '') {
            calculateRoute();
        }
    }, 450);
}

// --- DE ADRES VUL FUNCTIE (GECORRIGEERD) ---
function fillKlantCard(s) { 
    const o = s.options[s.selectedIndex]; 
    if(!o || !o.value) return; 
    
    // Info kaart vullen
    document.getElementById('c_naam').innerText = o.dataset.naam||''; 
    document.getElementById('c_adres').innerText = o.dataset.adres||''; 
    document.getElementById('c_plaats').innerText = o.dataset.plaats||''; 
    document.getElementById('c_tel').innerText = o.dataset.tel||''; 
    document.getElementById('c_email').innerText = o.dataset.email||''; 
    document.getElementById('klant_info_card').style.display = 'block'; 

    // FIX: Altijd invullen, ook als het veld al iets bevat
    const fullAdres = (o.dataset.adres || '') + ', ' + (o.dataset.plaats || '');
    
    // We vullen deze velden standaard met het adres van de klant
    // Maar alleen als er daadwerkelijk een adres beschikbaar is
    if((o.dataset.adres || '').length > 2) {
        ['addr_t_vertrek_klant', 'addr_t_retour_klant'].forEach(id => {
            const el = document.getElementById(id);
            if(el) {
                el.value = fullAdres;
                // Trigger een change event zodat Google Maps/Calc weet dat er iets staat
                // (Optioneel, soms nodig voor autocomplete styling)
            }
        });
    }
    if (typeof window.routeHeenRefreshFromLegacy === 'function') {
        window.routeHeenRefreshFromLegacy();
    }
}

// --- DE CONTACTPERSONEN FIX (SLIMME ROUTE) ---
function loadContacts(kid) { 
    const select = document.getElementById('contact_select');
    if(!select) return;
    
    select.innerHTML = '<option value="0">Laden...</option>';

    // Probeer eerst de standaard map
    fetch('../ajax_contacts.php?klant_id='+kid)
    .then(r => {
        if(!r.ok) throw new Error("404");
        return r.json();
    })
    .then(data => populateContacts(data))
    .catch(err => {
        console.log("Pad 1 mislukt, probeer pad 2...");
        // Als dat faalt, probeer de includes map
        fetch('../includes/ajax_contacts.php?klant_id='+kid)
        .then(r => r.json())
        .then(data => populateContacts(data))
        .catch(e => {
            console.error("Contacten laden volledig mislukt", e);
            select.innerHTML = '<option value="0">-- Geen contacten gevonden --</option>';
        });
    });
}

function populateContacts(data) {
    const s = document.getElementById('contact_select');
    s.innerHTML = '<option value="0">-- Algemeen --</option>'; 
    data.forEach(c => {
        s.innerHTML += `<option value="${c.id}">${c.naam} ${c.achternaam || ''}</option>`;
    });
}

/**
 * Terugreis-blok tonen alleen als er echte terugreis-planning is (DB of handmatig),
 * niet doordat klantadres in retour_klant staat of bestemming naar vertrek_best is gekopieerd.
 */
function terugreisSectionHasData() {
    const typeEl = document.getElementById('rittype_select');
    const type = typeEl ? typeEl.value : '';
    const tb = document.getElementById('time_t_vertrek_best');
    if (tb && tb.value.trim() !== '') {
        return true;
    }
    if (type === 'brenghaal') {
        const vb = document.getElementById('addr_t_vertrek_best');
        const vs2 = document.getElementById('addr_t_voorstaan_rit2');
        if (vb && vb.value.trim() !== '') {
            return true;
        }
        if (vs2 && vs2.value.trim() !== '') {
            return true;
        }
    }
    return false;
}

// --- ZICHTBAARHEID (V3.0 Logic) ---
function updateVisibility() {
    const type = document.getElementById('rittype_select').value;
    const blockTerug = document.getElementById('block_terug');
    const barTerug = document.getElementById('terugreis_gate_bar');
    const blockMeerdaags = document.getElementById('block_meerdaags');
    const headerTerug = document.getElementById('header_terug');
    const labelVertrekTerug = document.getElementById('label_vertrek_terug');
    
    const rowGarageRit2 = document.getElementById('row_garage_rit2');
    const rowVoorstaanRit2 = document.getElementById('row_voorstaan_rit2');
    const rowRetourGarageHeen = document.getElementById('row_retour_garage_heen');

    if (!blockTerug) return;

    const userOpenedTerug = window.__calcTerugreisUserShow === true;
    const hasTerugData = terugreisSectionHasData();
    let showTerugBlock = false;
    if (type === 'enkel') {
        showTerugBlock = false;
    } else {
        showTerugBlock = userOpenedTerug || hasTerugData;
    }

    if (barTerug) {
        barTerug.style.display = type === 'enkel' || showTerugBlock ? 'none' : 'flex';
    }

    blockTerug.style.display = showTerugBlock ? 'block' : 'none';
    if (blockMeerdaags) blockMeerdaags.style.display = 'none';
    if(headerTerug) headerTerug.innerText = "TERUGREIS";
    if(labelVertrekTerug) labelVertrekTerug.innerText = "Vertrek Bestemming";
    if(rowGarageRit2) rowGarageRit2.style.display = 'none';
    if(rowVoorstaanRit2) rowVoorstaanRit2.style.display = 'none';
    if(rowRetourGarageHeen) rowRetourGarageHeen.style.display = 'none';

    if (type === 'enkel') {
        if(rowRetourGarageHeen) rowRetourGarageHeen.style.display = 'flex'; 
    }
    if (type === 'meerdaags' || type === 'buitenland') {
        if (blockMeerdaags) blockMeerdaags.style.display = 'block';
    }
    if (showTerugBlock && type === 'brenghaal') {
        if(headerTerug) headerTerug.innerText = "RIT 2 / OPHALEN";
        if(labelVertrekTerug) labelVertrekTerug.innerText = "Klant Instappen (Ophaaladres)";
        if(rowRetourGarageHeen) rowRetourGarageHeen.style.display = 'flex'; 
        if(rowGarageRit2) rowGarageRit2.style.display = 'flex'; 
        if(rowVoorstaanRit2) rowVoorstaanRit2.style.display = 'flex'; 
        
        const g1 = document.getElementById('addr_t_garage');
        const g2 = document.getElementById('addr_t_garage_rit2');
        if(g1 && g2 && !g2.value) g2.value = g1.value;
        const g1end = document.getElementById('addr_t_retour_garage_heen');
        if(g1 && g1end && !g1end.value) g1end.value = g1.value;
    }
}

window.updateVisibility = updateVisibility;

// --- ROUTE MOTOR ---
function calculateRoute() {
    const type = document.getElementById('rittype_select').value;
    const route1ReturnMode = getHeenRoute1ReturnMode();

    // HEEN: garage → vertrekadres → 1e grens → [optioneel 2e grens] → bestemming
    const s1 = document.getElementById('addr_t_garage').value;
    const sVl = document.getElementById('addr_t_vertrek_klant').value;
    const sGrens = document.getElementById('addr_t_voorstaan').value;
    const chkG2 = document.getElementById('chk_grens2');
    const sGrens2 = document.getElementById('addr_t_grens2') ? document.getElementById('addr_t_grens2').value.trim() : '';
    const useGrens2 = chkG2 && chkG2.checked && sGrens2 !== '';
    const s4 = document.getElementById('addr_t_aankomst_best').value;
    const lastHeenStop = s4 || (useGrens2 ? sGrens2 : '') || sGrens || sVl;
    const sRetKlantHeen = document.getElementById('addr_t_retour_klant')?.value.trim() || '';
    const sEnd1 = document.getElementById('addr_t_retour_garage_heen')?.value.trim() || '';

    let stopsHeen = [];
    if(s1) stopsHeen.push({loc: s1, id: 'addr_t_garage'});
    if(sVl) stopsHeen.push({loc: sVl, id: 'addr_t_vertrek_klant'});
    if(sGrens) stopsHeen.push({loc: sGrens, id: 'addr_t_voorstaan'});
    if(useGrens2) stopsHeen.push({loc: sGrens2, id: 'addr_t_grens2'});
    if(s4) stopsHeen.push({loc: s4, id: 'addr_t_aankomst_best'});

    if (route1ReturnMode === 'rk') {
        if (sRetKlantHeen) stopsHeen.push({loc: sRetKlantHeen, id: 'addr_t_retour_klant'});
        if (sEnd1) stopsHeen.push({loc: sEnd1, id: 'addr_t_retour_garage_heen'});
    } else if (route1ReturnMode === 'rg') {
        if (lastHeenStop && sEnd1) stopsHeen.push({loc: sEnd1, id: 'addr_t_retour_garage_heen'});
    }
    
    if(stopsHeen.length >= 2) runGoogleRoute(stopsHeen);

    // TERUG
    if(type !== 'enkel') {
        if(type === 'brenghaal') {
             const g2 = document.getElementById('addr_t_garage_rit2').value || s1;
             const v2 = document.getElementById('addr_t_voorstaan_rit2').value; 
             const k_ophaal = document.getElementById('addr_t_vertrek_best').value; 
             const k_uitstap = document.getElementById('addr_t_retour_klant').value; 
             const g_eind = document.getElementById('addr_t_retour_garage').value || s1;

             let stopsRit2 = [];
             if(g2) stopsRit2.push({loc: g2, id: 'addr_t_garage_rit2'});
             if(v2) stopsRit2.push({loc: v2, id: 'addr_t_voorstaan_rit2'});
             if(k_ophaal) stopsRit2.push({loc: k_ophaal, id: 'addr_t_vertrek_best'});
             if(k_uitstap) stopsRit2.push({loc: k_uitstap, id: 'addr_t_retour_klant'});
             stopsRit2.push({loc: g_eind, id: 'addr_t_retour_garage'});
             
             if(stopsRit2.length >= 2) runGoogleRoute(stopsRit2);
        } else {
             const s5 = document.getElementById('addr_t_vertrek_best').value; 
             const s6 = document.getElementById('addr_t_retour_klant').value; 
             const s7 = document.getElementById('addr_t_retour_garage').value || s1; 
             const terugOpen = window.__calcTerugreisUserShow === true || terugreisSectionHasData();
             let stopsTerug = [];
             // Gap logic: Start berekening vanaf Aankomst Heen
             if(s4) stopsTerug.push({loc: s4, id: 'dummy_start_terug'}); 
             
             if(s5) stopsTerug.push({loc: s5, id: 'addr_t_vertrek_best'});
             if(s6) stopsTerug.push({loc: s6, id: 'addr_t_retour_klant'});
             stopsTerug.push({loc: s7, id: 'addr_t_retour_garage'});

             if(terugOpen && s5 && stopsTerug.length >= 2) runGoogleRoute(stopsTerug);
        }
    }

    setTimeout(function () {
        calculateTussendagenKmAll();
    }, 350);
}

/** Tussenrit-rijen: km via Google Directions (van → naar). */
function calculateTussendagenKmAll() {
    if (typeof directionsService === 'undefined' || !directionsService) return;
    document.querySelectorAll('.tz-row').forEach(function (row) {
        calculateTussendagenKm(row);
    });
}

function calculateTussendagenKm(rowEl) {
    if (!directionsService || !rowEl) return;
    const van = rowEl.querySelector('[name="tussendagen_van[]"]');
    const naar = rowEl.querySelector('[name="tussendagen_naar[]"]');
    const kmInput = rowEl.querySelector('.km-calc');
    if (!van || !naar || !kmInput) return;
    const a = van.value.trim();
    const b = naar.value.trim();
    if (a.length < 4 || b.length < 4) return;

    directionsService.route({
        origin: a,
        destination: b,
        travelMode: 'DRIVING',
        unitSystem: google.maps.UnitSystem.METRIC
    }, function (response, status) {
        if (status !== 'OK' || !response.routes || !response.routes[0]) return;
        const leg = response.routes[0].legs[0];
        if (!leg) return;
        kmInput.value = Math.ceil(leg.distance.value / 1000);
        if (typeof window.updateRouteV2HiddenInput === 'function') window.updateRouteV2HiddenInput();
        if (typeof rekenen === 'function') rekenen();
    });
}

window.calculateTussendagenKm = calculateTussendagenKm;
window.calculateTussendagenKmAll = calculateTussendagenKmAll;

function runGoogleRoute(stopMap) {
    const origin = stopMap[0].loc;
    const destination = stopMap[stopMap.length-1].loc;
    const waypoints = [];
    for(let i=1; i<stopMap.length-1; i++) waypoints.push({location: stopMap[i].loc, stopover: true});

    directionsService.route({
        origin: origin, destination: destination, waypoints: waypoints,
        travelMode: 'DRIVING', unitSystem: google.maps.UnitSystem.METRIC
    }, function(response, status) {
        if (status === 'OK') {
            const legs = response.routes[0].legs;
            for(let i=0; i<legs.length; i++) {
                if(i+1 >= stopMap.length) break;
                const targetId = stopMap[i+1].id;
                if(targetId.includes('dummy')) continue; 

                const addrInput = document.getElementById(targetId);
                if(addrInput) {
                    const row = addrInput.closest('.rit-row');
                    if(row) {
                        const kmInput = row.querySelector('.km-calc');
                        if(kmInput) kmInput.value = Math.ceil(legs[i].distance.value / 1000);
                    }
                }
                reisTijden[targetId] = Math.ceil((legs[i].duration.value * BUS_FACTOR) / 60);
            }
            updatePlanning();
            if (typeof window.syncHeenSegmentDisplayFromLegacy === 'function') {
                window.syncHeenSegmentDisplayFromLegacy();
            }
            rekenen();
            calculateTussendagenKmAll();
        }
    });
}

// --- TIJDMACHINE ---
function getHeenVoorrijTijd() {
    const tVertrek = document.getElementById('time_t_vertrek_klant').value;
    if (!tVertrek) {
        return '';
    }
    return formatTime(addMinutes(parseTime(tVertrek), -BUFFER_VOORSTAAN));
}

function getHeenSegmentRowsForPlanning() {
    const body = document.getElementById('heen_segmenten_body');
    if (!body) return [];
    const rows = Array.from(body.querySelectorAll('tr.heen-seg-row'));
    while (rows.length > 2) {
        const last = rows[rows.length - 1];
        const naar = last.querySelector('.heen-naar')?.value.trim() || '';
        const km = parseFloat(last.querySelector('.heen-km')?.value || '0') || 0;
        if (naar !== '' || km > 0) break;
        rows.pop();
    }
    return rows;
}

function getHeenSegmentCoreRows(rows) {
    return rows.filter(function (row) {
        return !row.dataset.returnKind;
    });
}

function getHeenRoute1ReturnMode() {
    const kinds = getHeenSegmentRowsForPlanning()
        .filter(function (row) { return !!row.dataset.returnKind; })
        .map(function (row) { return row.dataset.returnKind; });
    if (kinds.length === 1 && kinds[0] === 'rg') return 'rg';
    if (kinds.length === 2 && kinds[0] === 'rk-klant' && kinds[1] === 'rk-garage') return 'rk';
    return '';
}

function getHeenSegmentLegMinutes(rows) {
    const coreRows = getHeenSegmentCoreRows(rows);
    const activeCount = coreRows.length;
    const ritVl = reisTijden['addr_t_vertrek_klant'] || 30;
    const ritVs = reisTijden['addr_t_voorstaan'] || 0;
    const ritG2 = reisTijden['addr_t_grens2'] || 0;
    const ritBest = reisTijden['addr_t_aankomst_best'] || 0;
    let legs = [];
    if (activeCount > 1) {
        if (activeCount === 2) legs = [ritVl, ritBest];
        else if (activeCount === 3) legs = [ritVl, ritVs, ritBest];
        else legs = [ritVl, ritVs, ritG2, ritBest];
    }
    rows.slice(coreRows.length).forEach(function (row) {
        if (row.dataset.returnKind === 'rk-klant') {
            legs.push(reisTijden['addr_t_retour_klant'] || 0);
        } else if (row.dataset.returnKind) {
            legs.push(reisTijden['addr_t_retour_garage_heen'] || 30);
        }
    });
    return legs;
}

function syncHeenSegmentPlanning() {
    const rows = getHeenSegmentRowsForPlanning();
    const coreRows = getHeenSegmentCoreRows(rows);
    if (coreRows.length < 2) {
        return false;
    }

    const legacyGarage = document.getElementById('time_t_garage');
    const legacyVertrekKlant = document.getElementById('time_t_vertrek_klant');
    const legacyVoorstaan = document.getElementById('time_t_voorstaan');
    const legacyGrens2 = document.getElementById('time_t_grens2');
    const legacyBest = document.getElementById('time_t_aankomst_best');
    const legacyRetourKlant = document.getElementById('time_t_retour_klant');
    const legacyGarageEnd = document.getElementById('time_t_retour_garage_heen');
    const legMinutes = getHeenSegmentLegMinutes(rows);

    const row0Vt = rows[0].querySelector('.heen-vt');
    const row0At = rows[0].querySelector('.heen-at');
    const leadVt = rows[1].querySelector('.heen-vt');

    if (!leadVt || !legacyVertrekKlant) {
        return false;
    }

    const leadTime = (leadVt.value || legacyVertrekKlant.value || '').trim().substring(0, 5);
    leadVt.readOnly = true;
    leadVt.dataset.timeEditable = '1';
    leadVt.classList.remove('heen-vt--auto');
    leadVt.title = 'Vertrek bij klant';

    if (row0Vt) {
        row0Vt.readOnly = true;
        row0Vt.dataset.timeEditable = '1';
        row0Vt.classList.remove('heen-vt--auto');
        row0Vt.title = 'Vertrek vanuit garage';
    }
    if (row0At) {
        row0At.readOnly = true;
        row0At.dataset.timeEditable = '1';
        row0At.classList.remove('heen-at--auto');
        row0At.title = 'Aankomst bij klant';
    }

    if (!leadTime) {
        legacyVertrekKlant.value = '';
        if (row0At && row0At.dataset.manual !== '1') row0At.value = '';
        if (row0Vt && row0Vt.dataset.manual !== '1') row0Vt.value = '';
        if (legacyGarage && legacyGarage.dataset.manual !== '1') legacyGarage.value = '';
        if (legacyBest) legacyBest.value = '';
        if (legacyVoorstaan) legacyVoorstaan.value = '';
        if (legacyGrens2) legacyGrens2.value = '';
        if (legacyRetourKlant) legacyRetourKlant.value = '';
        if (legacyGarageEnd) legacyGarageEnd.value = '';
        for (let i = 1; i < rows.length; i++) {
            const vtEl = rows[i].querySelector('.heen-vt');
            const atEl = rows[i].querySelector('.heen-at');
            const kind = rows[i].dataset.returnKind || '';
            if (vtEl) {
                vtEl.readOnly = true;
                vtEl.dataset.timeEditable = '1';
                vtEl.classList.remove('heen-vt--auto');
                vtEl.title = i === 1 ? 'Vertrek bij klant' : (kind ? 'Vertrek voor retourregel' : 'Vertrek vanaf deze stop');
                if (i > 1 && vtEl.dataset.manual !== '1') {
                    vtEl.value = '';
                }
            }
            if (atEl) {
                atEl.readOnly = true;
                atEl.classList.add('heen-at--auto');
                delete atEl.dataset.timeEditable;
                atEl.value = '';
                atEl.title = 'Automatische aankomsttijd op deze stop';
            }
        }
        return true;
    }

    legacyVertrekKlant.value = leadTime;
    leadVt.value = leadTime;

    const dLead = parseTime(leadTime);
    let dRow0At = addMinutes(dLead, -BUFFER_VOORSTAAN);
    if (row0At && row0At.dataset.manual === '1' && row0At.value.trim()) {
        dRow0At = parseTime(row0At.value.trim());
    } else if (row0At) {
        row0At.value = formatTime(dRow0At);
    }

    const dGarageAuto = addMinutes(dRow0At, -(legMinutes[0] || 0));
    if (legacyGarage && legacyGarage.dataset.manual !== '1') {
        legacyGarage.value = formatTime(dGarageAuto);
    }
    if (row0Vt && row0Vt.dataset.manual !== '1') {
        row0Vt.value = legacyGarage && legacyGarage.value ? legacyGarage.value.trim().substring(0, 5) : formatTime(dGarageAuto);
    }

    let previousArrival = null;
    let finalArrival = null;
    let stop1Vertrek = '';
    let stop2Vertrek = '';
    let retourKlantArrival = '';
    let garageArrival = '';

    for (let i = 1; i < rows.length; i++) {
        const vtEl = rows[i].querySelector('.heen-vt');
        const atEl = rows[i].querySelector('.heen-at');
        const kind = rows[i].dataset.returnKind || '';
        if (!vtEl || !atEl) continue;

        vtEl.readOnly = true;
        vtEl.dataset.timeEditable = '1';
        vtEl.classList.remove('heen-vt--auto');
        vtEl.title = i === 1 ? 'Vertrek bij klant' : (kind ? 'Vertrek voor retourregel' : 'Vertrek vanaf deze stop');

        let vertrekTime = '';
        if (i === 1) {
            vertrekTime = leadTime;
        } else if (vtEl.dataset.manual === '1' && vtEl.value.trim()) {
            vertrekTime = vtEl.value.trim().substring(0, 5);
        } else if (previousArrival) {
            vertrekTime = formatTime(previousArrival);
        }

        vtEl.value = vertrekTime;
        if (!vertrekTime) {
            atEl.value = '';
            atEl.readOnly = true;
            atEl.classList.add('heen-at--auto');
            delete atEl.dataset.timeEditable;
            atEl.title = 'Automatische aankomsttijd op deze stop';
            previousArrival = null;
            finalArrival = null;
            continue;
        }

        const dVertrek = parseTime(vertrekTime);
        const dAankomst = addMinutes(dVertrek, legMinutes[i] || 0);
        atEl.value = formatTime(dAankomst);
        atEl.readOnly = true;
        atEl.classList.add('heen-at--auto');
        delete atEl.dataset.timeEditable;
        atEl.title = 'Automatische aankomsttijd op deze stop';

        previousArrival = dAankomst;
        if (i < coreRows.length) {
            finalArrival = dAankomst;
            if (i === 2) stop1Vertrek = vertrekTime;
            if (i === 3) stop2Vertrek = vertrekTime;
        } else if (kind === 'rk-klant') {
            retourKlantArrival = formatTime(dAankomst);
        } else if (kind === 'rg' || kind === 'rk-garage') {
            garageArrival = formatTime(dAankomst);
        }
    }

    if (legacyVoorstaan) legacyVoorstaan.value = stop1Vertrek;
    if (legacyGrens2) legacyGrens2.value = stop2Vertrek;
    if (legacyBest) legacyBest.value = finalArrival ? formatTime(finalArrival) : '';
    if (legacyRetourKlant) legacyRetourKlant.value = retourKlantArrival;
    if (legacyGarageEnd) legacyGarageEnd.value = garageArrival;

    return true;
}

function updatePlanning() {
    const segmentPlanningHandled = syncHeenSegmentPlanning();
    const tVertrek = document.getElementById('time_t_vertrek_klant').value;
    if(tVertrek && !segmentPlanningHandled) {
        const dVertrek = parseTime(tVertrek);
        const tVoorrij = getHeenVoorrijTijd();
        const dVoorrij = tVoorrij ? parseTime(tVoorrij) : addMinutes(dVertrek, -BUFFER_VOORSTAAN);
        const ritGarageNaarVl = reisTijden['addr_t_vertrek_klant'] || 30;
        const dGarage = addMinutes(dVoorrij, -ritGarageNaarVl);
        const elGarage = document.getElementById('time_t_garage');
        if (elGarage && elGarage.dataset.manual !== '1') {
            elGarage.value = formatTime(dGarage);
        }

        const ritVlNaarGrens = reisTijden['addr_t_voorstaan'] || 0;
        const chkG2El = document.getElementById('chk_grens2');
        const useG2 = chkG2El && chkG2El.checked;
        const ritG1NaarG2 = useG2 ? (reisTijden['addr_t_grens2'] || 0) : 0;
        const ritLastNaarBest = reisTijden['addr_t_aankomst_best'] || 0;
        const dAankomst = addMinutes(dVertrek, ritVlNaarGrens + ritG1NaarG2 + ritLastNaarBest);
        document.getElementById('time_t_aankomst_best').value = formatTime(dAankomst);

        const elHiddenVs = document.getElementById('time_t_voorstaan');
        if (elHiddenVs) {
            elHiddenVs.value = formatTime(addMinutes(dVertrek, ritVlNaarGrens));
        }
        const elHiddenG2 = document.getElementById('time_t_grens2');
        if (elHiddenG2) {
            if (useG2) {
                elHiddenG2.value = formatTime(addMinutes(dVertrek, ritVlNaarGrens + ritG1NaarG2));
            } else {
                elHiddenG2.value = '';
            }
        }

        const ritNaarGarageHeen = reisTijden['addr_t_retour_garage_heen'] || 30;
        const dGarageHeenEnd = addMinutes(dAankomst, ritNaarGarageHeen + 15); 
        if(document.getElementById('time_t_retour_garage_heen'))
            document.getElementById('time_t_retour_garage_heen').value = formatTime(dGarageHeenEnd);
    } else if (!segmentPlanningHandled) {
        const elGarage = document.getElementById('time_t_garage');
        const elBest = document.getElementById('time_t_aankomst_best');
        const elVs = document.getElementById('time_t_voorstaan');
        const elG2 = document.getElementById('time_t_grens2');
        if (elGarage && elGarage.dataset.manual !== '1') elGarage.value = '';
        if (elBest) elBest.value = '';
        if (elVs) elVs.value = '';
        if (elG2) elG2.value = '';
    }
    
    const type = document.getElementById('rittype_select').value;
    
    if (type === 'brenghaal') {
        const tOphaal = document.getElementById('time_t_vertrek_best').value;
        if(tOphaal) {
            const dOphaal = parseTime(tOphaal);
            const dVoorstaan2 = addMinutes(dOphaal, -BUFFER_VOORSTAAN);
            document.getElementById('time_t_voorstaan_rit2').value = formatTime(dVoorstaan2);
            
            const ritVanGarage2 = reisTijden['addr_t_voorstaan_rit2'] || 30;
            const dGarageStart2 = addMinutes(dVoorstaan2, -ritVanGarage2);
            document.getElementById('time_t_garage_rit2').value = formatTime(dGarageStart2);
            
            const ritNaarUitstap = reisTijden['addr_t_retour_klant'] || 60;
            const dUitstap = addMinutes(dOphaal, ritNaarUitstap);
            document.getElementById('time_t_retour_klant').value = formatTime(dUitstap);
            
            const ritNaarGarageEind = reisTijden['addr_t_retour_garage'] || 30;
            const dGarageEind = addMinutes(dUitstap, ritNaarGarageEind + BUFFER_NAZORG);
            document.getElementById('time_t_retour_garage').value = formatTime(dGarageEind);
        }
    } 
    else if (type === 'dagtocht' || type === 'schoolreis' || type === 'meerdaags' || type === 'buitenland' || type === 'trein') {
        const tVertrekTerug = document.getElementById('time_t_vertrek_best').value;
        if(tVertrekTerug) {
            const dTerug = parseTime(tVertrekTerug);
            const ritTerug = reisTijden['addr_t_retour_klant'] || 60;
            const dKlantTerug = addMinutes(dTerug, ritTerug);
            document.getElementById('time_t_retour_klant').value = formatTime(dKlantTerug);
            
            const ritGarage = reisTijden['addr_t_retour_garage'] || 30;
            const dGarageTerug = addMinutes(dKlantTerug, ritGarage + BUFFER_NAZORG);
            document.getElementById('time_t_retour_garage').value = formatTime(dGarageTerug);
        }
    }
    if (typeof window.updateRouteV2HiddenInput === 'function') window.updateRouteV2HiddenInput();
}

/** Zone-tags → km_nl / km_de / km_ch / km_ov + km_tussen = som extra-rijdag-km */
function syncFiscalFromZones() {
    const typeEl = document.getElementById('rittype_select');
    const type = typeEl ? typeEl.value : '';
    if (type !== 'meerdaags' && type !== 'buitenland') {
        return;
    }
    let nl = 0, de = 0, ch = 0, ov = 0;
    let tzSum = 0;

    document.querySelectorAll('.km-zone-select').forEach(function (sel) {
        if (sel.closest('#legacy_heen_mirror')) return;
        const row = sel.closest('.rit-row') || sel.closest('.tz-row') || sel.closest('tr.heen-seg-row');
        if (!row) return;
        const kmEl = row.querySelector('.km-calc');
        if (!kmEl) return;
        if (kmEl.offsetParent === null) return;
        const km = parseFloat(kmEl.value) || 0;
        const z = sel.value;
        if (z === 'nl') nl += km;
        else if (z === 'de') de += km;
        else if (z === 'ch') ch += km;
        else ov += km;
        if (row.classList.contains('tz-row')) {
            tzSum += km;
        }
    });

    const round1 = function (x) { return Math.round(x * 10) / 10; };
    const setVal = function (id, v) {
        const el = document.getElementById(id);
        if (el) el.value = String(round1(v));
    };
    setVal('km_nl', nl);
    setVal('km_de', de);
    setVal('km_ch', ch);
    setVal('km_ov', ov);
    setVal('km_tussen', tzSum);

    const fiscalSum = nl + de + ch + ov;
    let routeKm = 0;
    document.querySelectorAll('.km-calc').forEach(function (i) {
        if (!isRouteKmInputForTotals(i)) return;
        if (i.offsetParent !== null) routeKm += parseFloat(i.value) || 0;
    });
    const fc = document.getElementById('fiscal_check');
    if (fc) {
        const delta = round1(routeKm - fiscalSum);
        fc.value = 'route ' + round1(routeKm) + ' · zones ' + round1(fiscalSum) + ' · Δ ' + delta;
    }
}

// --- REKENEN ---
function rekenen() {
    const type = document.getElementById('rittype_select').value;
    syncFiscalFromZones();

    let totaalKm = 0;
    document.querySelectorAll('.km-calc').forEach(i => {
        if (!isRouteKmInputForTotals(i)) return;
        if(i.offsetParent !== null) totaalKm += parseFloat(i.value) || 0;
    });

    document.getElementById('total_km').value = totaalKm;

    let uren = 0;
    const t1 = document.getElementById('time_t_garage').value;
    
    if(type === 'dagtocht' || type === 'schoolreis' || type === 'trein') {
        const tEnd = document.getElementById('time_t_retour_garage').value;
        if(t1 && tEnd) uren = calcDiff(t1, tEnd);
    } 
    else if (type === 'enkel') {
        const tEnd = document.getElementById('time_t_retour_garage_heen').value;
        if(t1 && tEnd) uren = calcDiff(t1, tEnd);
    }
    else if (type === 'brenghaal') {
        const tEnd1 = document.getElementById('time_t_retour_garage_heen').value;
        let uren1 = (t1 && tEnd1) ? calcDiff(t1, tEnd1) : 0;
        const tStart2 = document.getElementById('time_t_garage_rit2').value; 
        const tEnd2 = document.getElementById('time_t_retour_garage').value;
        let uren2 = (tStart2 && tEnd2) ? calcDiff(tStart2, tEnd2) : 0;
        uren = uren1 + uren2;
    }
    else if (type === 'meerdaags' || type === 'buitenland') {
        const t2 = document.getElementById('time_t_aankomst_best').value;
        const t3 = document.getElementById('time_t_vertrek_best').value;
        const t4 = document.getElementById('time_t_retour_garage').value;

        let urenHeen = (t1 && t2) ? calcDiff(t1, t2) + 0.5 : 0;
        let urenTerug = (t3 && t4) ? calcDiff(t3, t4) : 0;

        const datumStart = document.getElementById('rit_datum').value;
        const datumEind = document.getElementById('rit_datum_eind').value;

        const kalenderdagen = countKalenderdagenInclusive(datumStart, datumEind);
        const MIN_NETTO_PER_AANLOOPDAG = 8;

        /*
         * Meerdaagse reizen (kalender): CAO — 1e en laatste kalenderdag 6/6 diensttijd (hier: uit tijdenvelden),
         * met minimaal 8 uur netto per die dagen; tussenliggende kalenderdagen telden als 8 uur netto (loonberekening).
         * Pauze/staffel art. 16 lid 1 sub d: niet apart gemodelleerd; alleen netto‑minimum afgedwongen.
         */
        if (kalenderdagen <= 1) {
            uren = urenHeen + urenTerug;
        } else {
            const eersteDag = Math.max(urenHeen, MIN_NETTO_PER_AANLOOPDAG);
            const tussenliggendeDagen = Math.max(0, kalenderdagen - 2);
            const laatsteDag = Math.max(urenTerug, MIN_NETTO_PER_AANLOOPDAG);
            uren = eersteDag + tussenliggendeDagen * 8 + laatsteDag;
        }
    }
    document.getElementById('total_uren').value = uren.toFixed(2);
    
    const LOON = (typeof SERVER_DATA !== 'undefined') ? SERVER_DATA.uurloon : 35.00;
    const busSelect = document.getElementById('bus_select');
    let busPrijs = 0;
    if(busSelect && busSelect.selectedIndex > -1) {
        busPrijs = parseFloat(busSelect.options[busSelect.selectedIndex].dataset.km) || 0;
    }
    
    const kostTotaal = (totaalKm * busPrijs) + (uren * LOON);
    if(document.getElementById('display_kost')) 
        document.getElementById('display_kost').innerText = "€ " + kostTotaal.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const prijsInVeld = document.getElementById('verkoopprijs');
    if(!prijsInVeld) return;
    
    let prijsIn = parseFloat(prijsInVeld.value) || 0;
    if(!userManuallyChangedPrice && kostTotaal > 0) {
        let marge = (type === 'meerdaags' || type === 'buitenland') ? 1.35 : 1.25;
        let prijsEx = kostTotaal * marge;
        prijsIn = prijsEx * 1.09; 
        prijsIn = Math.ceil(prijsIn / 5) * 5; 
        prijsInVeld.value = prijsIn.toFixed(2);
    }
    
    let prijsEx = prijsIn / 1.09; 
    let winst = prijsEx - kostTotaal;
    if(document.getElementById('display_ex_btw'))
        document.getElementById('display_ex_btw').innerText = "Excl: € " + prijsEx.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const dWinst = document.getElementById('display_winst');
    if(dWinst) {
        dWinst.innerText = "€ " + winst.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        dWinst.style.color = winst >= 0 ? '#28a745' : '#dc3545';
    }
    if(prijsEx > 0 && document.getElementById('display_perc')) {
        const perc = (winst / prijsEx) * 100;
        document.getElementById('display_perc').innerText = perc.toFixed(1) + "%";
    }
}

// Helpers
/** Kalenderdagen tussen vertrek- en einddatum (beide inclusief). */
function countKalenderdagenInclusive(startStr, endStr) {
    if (!startStr || !endStr) return 1;
    const d1 = new Date(startStr + 'T12:00:00');
    const d2 = new Date(endStr + 'T12:00:00');
    if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return 1;
    const diffDays = Math.round((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24));
    return diffDays >= 0 ? diffDays + 1 : 1;
}

function calcDiff(tStart, tEnd) {
    let d1 = parseTime(tStart);
    let d2 = parseTime(tEnd);
    let diff = (d2 - d1) / 1000 / 60 / 60;
    if(diff < 0) diff += 24; 
    return diff;
}
function parseTime(str) {
    if(!str) return new Date();
    const [h, m] = str.split(':').map(Number);
    const d = new Date(); d.setHours(h, m, 0, 0);
    return d;
}
function formatTime(d) {
    let h = d.getHours(); let m = d.getMinutes();
    return (h<10?'0':'')+h + ':' + (m<10?'0':'')+m;
}
function addMinutes(d, min) {
    return new Date(d.getTime() + min * 60000);
}
function openTimeModal(el) { activeTimeInput = el; showHours(); document.getElementById('timeModal').style.display = 'block'; }
function closeTimeModal() { document.getElementById('timeModal').style.display = 'none'; activeTimeInput = null; }
function showHours() { const g = document.getElementById('modalGrid'); if(!g) return; g.innerHTML = ''; for(let i=0; i<24; i++) createTimeBtn(i, (i<10?'0':'')+i+":00"); }
function createTimeBtn(h, label) { const b = document.createElement('div'); b.className = 'time-btn'; b.innerText = label; b.onclick = () => showMinutes(h); document.getElementById('modalGrid').appendChild(b); }
function showMinutes(h) {
    const g = document.getElementById('modalGrid');
    if (!g) {
        return;
    }
    g.innerHTML = '';
    for (let i = 0; i < 60; i += 5) {
        const b = document.createElement('div');
        b.className = 'time-btn';
        const time = (h < 10 ? '0' : '') + h + ':' + (i < 10 ? '0' : '') + i;
        b.innerText = time;
        b.onclick = function () {
            if (activeTimeInput) {
                activeTimeInput.value = time;
                if (
                    activeTimeInput.id === 'time_t_garage' ||
                    activeTimeInput.id === 'time_t_garage_rit2'
                ) {
                    activeTimeInput.dataset.manual = '1';
                }
                if (activeTimeInput.matches('input.heen-vt')) {
                    activeTimeInput.dataset.manual = '1';
                    if (activeTimeInput.matches('tr.heen-seg-first .heen-vt')) {
                        const garageLegacy = document.getElementById('time_t_garage');
                        if (garageLegacy) garageLegacy.dataset.manual = '1';
                    }
                }
                if (activeTimeInput.matches('tr.heen-seg-first .heen-at')) {
                    activeTimeInput.dataset.manual = '1';
                }
                const segBody = document.getElementById('heen_segmenten_body');
                if (
                    segBody &&
                    segBody.contains(activeTimeInput) &&
                    typeof window.syncHeenSegmentsFromLegacy === 'function'
                ) {
                    window.syncHeenSegmentsFromLegacy();
                }
                updatePlanning();
                rekenen();
            }
            closeTimeModal();
        };
        g.appendChild(b);
    }
}