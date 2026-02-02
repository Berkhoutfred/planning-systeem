// calculatie.js - Rekenmachine & Maps
const calc = {
    init: function() {
        // Bouw Bussen Grid
        const container = document.getElementById('bus-container');
        container.innerHTML = config.busOpties.map((b, i) => `
            <label class="bus-option">
                <input type="checkbox" class="bus-check" value="${i}" onchange="calc.rekenPrijs()">
                <div><b>${b.label}</b><br>€${b.prijs}</div>
            </label>
        `).join('');
        this.wisselTijdLijst();
    },

    resetForm: function() {
        state.app.activeRitId = null;
        document.getElementById('calc-title').innerText = "Nieuwe Aanvraag";
        document.querySelectorAll('input:not(#route-start)').forEach(i => i.value = '');
        document.getElementById('calc-contact').innerHTML = '<option value="">-- Kies eerst klant --</option>';
        document.getElementById('klant-details').classList.add('hidden');
        document.querySelectorAll('.bus-check').forEach(c => c.checked = false);
        state.route = { km:0, ritSec:0, aanrijSec:0, berekend:false };
        document.getElementById('info-totaal').innerText = "0 km";
        document.getElementById('prijs-display').innerText = "€ 0,00";
        document.getElementById('live-profit').innerText = "€ 0";
    },

    syncDates: function() {
        document.getElementById('rit_datum_eind').value = document.getElementById('rit_datum').value;
        this.rekenUren();
    },

    // --- GOOGLE MAPS FUNCTIES ---
    
    // Deze functie wordt door Google aangeroepen!
    initMaps: function() {
        const opts = { componentRestrictions: {country:'nl'} };
        
        // Koppel autocomplete aan Calculatie velden
        ['route-start','route-ophaal','route-eind','route-via1','route-via2'].forEach(id => {
            const el = document.getElementById(id);
            if(el) {
                const ac = new google.maps.places.Autocomplete(el, opts);
                ac.addListener('place_changed', () => calc.berekenRoute());
            }
        });

        // Koppel autocomplete aan CRM adres veld
        const crmAdres = document.getElementById('crm-adres');
        if(crmAdres) {
            const ac = new google.maps.places.Autocomplete(crmAdres, opts);
            ac.addListener('place_changed', () => {
                const p = ac.getPlace();
                // Stad automatisch invullen
                let stad = '';
                if(p.address_components) {
                    p.address_components.forEach(c => { 
                        if(c.types.includes('locality')) stad = c.long_name; 
                    });
                }
                document.getElementById('crm-plaats').value = stad;
            });
        }
    },

    berekenRoute: function() {
        const s = document.getElementById('route-start').value;
        const o = document.getElementById('route-ophaal').value;
        const e = document.getElementById('route-eind').value;
        
        if(!s || !o || !e) return;

        const svc = new google.maps.DirectionsService();
        const w = [];
        if(document.getElementById('route-via1').value) w.push({location:document.getElementById('route-via1').value});
        if(document.getElementById('route-via2').value) w.push({location:document.getElementById('route-via2').value});

        svc.route({origin:s, destination:e, waypoints:w, travelMode:'DRIVING'}, (res, stat) => {
            if(stat === 'OK') {
                const legs = res.routes[0].legs;
                // Eerste stuk = Aanrijtijd
                state.route.aanrijSec = legs[0].duration.value;
                
                let meters = 0; let rSec = 0;
                legs.forEach((l, i) => {
                    meters += l.distance.value;
                    if(i > 0) rSec += l.duration.value;
                });
                state.route.ritSec = rSec;

                const type = document.getElementById('rit-type').value;
                const factor = (type === 'vv') ? 2 : (type === 'brenghaal' ? 4 : 1);
                state.route.km = Math.round((meters * factor) / 1000);
                
                state.route.berekend = true;

                document.getElementById('info-rit').innerText = Math.round(rSec/60) + " min";
                document.getElementById('info-aanrij').innerText = Math.round(state.route.aanrijSec/60) + " min";
                document.getElementById('info-totaal').innerText = state.route.km + " km";

                this.syncTijden();
                this.rekenPrijs();
            }
        });
    },

    syncTijden: function() {
        if(!state.route.berekend) return; 
        const type = document.getElementById('rit-type').value;
        const aanrijMs = state.route.aanrijSec * 1000;
        const ritMs = state.route.ritSec * 1000;

        const setVal = (id, date) => {
            const h = document.getElementById('h_'+id); const m = document.getElementById('m_'+id);
            if(h && m) { h.value = date.getHours().toString().padStart(2,'0'); m.value = date.getMinutes().toString().padStart(2,'0'); }
        };
        const getVal = (id) => {
            const h = parseInt(document.getElementById('h_'+id).value); const m = parseInt(document.getElementById('m_'+id).value);
            return new Date(2000,0,1,h,m);
        };

        if(type === 'vv') { 
            const vertrekKlant = getVal('std_3');
            setVal('std_1', new Date(vertrekKlant.getTime() - (15*60000) - aanrijMs));
            setVal('std_2', new Date(vertrekKlant.getTime() - (15*60000)));
            setVal('std_4', new Date(vertrekKlant.getTime() + ritMs));
            
            const vertrekRetour = getVal('std_5');
            const aankomstKlant = new Date(vertrekRetour.getTime() + ritMs);
            setVal('std_6', aankomstKlant);
            setVal('std_7', new Date(aankomstKlant.getTime() + aanrijMs));
        }
        this.rekenUren();
    },

    wisselTijdLijst: function() {
        const type = document.getElementById('rit-type').value;
        const container = document.getElementById('tijd-container');
        const tpl = {
            vv: [
                {id:'std_1', l:'Vertrek Garage'}, {id:'std_2', l:'Voorstaan'},
                {id:'std_3', l:'Vertrek Klant', hl:true, h:8, m:45}, {id:'std_4', l:'Aankomst Best.'},
                {id:'std_5', l:'Vertrek Retour', h:17, m:0}, {id:'std_6', l:'Aankomst Klant'},
                {id:'std_7', l:'Aankomst Garage'}
            ],
            enkel: [
                {id:'std_1', l:'Vertrek Garage'}, {id:'std_3', l:'Vertrek Klant', hl:true},
                {id:'std_4', l:'Aankomst Best.'}, {id:'std_7', l:'Aankomst Garage'}
            ]
        };
        const list = (type === 'enkel') ? tpl.enkel : tpl.vv;
        
        container.innerHTML = list.map(t => {
            let uOpts='', mOpts='';
            for(let i=0;i<24;i++) uOpts+=`<option value="${i.toString().padStart(2,'0')}" ${i==(t.h||8)?'selected':''}>${i.toString().padStart(2,'0')}</option>`;
            ['00','15','30','45'].forEach(m => mOpts+=`<option value="${m}" ${m==(t.m||0)?'selected':''}>${m}</option>`);
            const changeFn = (t.hl) ? "calc.syncTijden()" : "calc.rekenUren()";
            const style = t.hl ? 'border-left:3px solid var(--success); background:#f0fdf4;' : '';
            
            return `<div class="time-row" style="${style}">
                <span style="font-size:12px;width:100px;">${t.l}</span>
                <div class="time-select-group">
                    <select id="h_${t.id}" class="select-uur" onchange="${changeFn}">${uOpts}</select>:
                    <select id="m_${t.id}" class="select-min" onchange="${changeFn}">${mOpts}</select>
                </div>
            </div>`;
        }).join('');
        this.rekenUren();
    },

    rekenUren: function() {
        const urenInputs = document.querySelectorAll('.select-uur');
        if(urenInputs.length < 2) return;
        const first = urenInputs[0]; 
        const last = urenInputs[urenInputs.length-1];
        const start = new Date(2000,0,1, parseInt(first.value), parseInt(first.nextElementSibling.value));
        const end = new Date(2000,0,1, parseInt(last.value), parseInt(last.nextElementSibling.value));
        let diff = (end - start) / 3600000;
        if(diff < 0) diff += 24;
        document.getElementById('uren-display').innerText = diff.toFixed(2);
        this.rekenPrijs();
    },

    rekenPrijs: function() {
        const km = state.route.km;
        const uren = parseFloat(document.getElementById('uren-display').innerText) || 0;
        const extra = parseFloat(document.getElementById('kosten-extra').value) || 0;
        
        let busPrijs = 0; let aantalBussen = 0;
        document.querySelectorAll('.bus-check:checked').forEach(c => {
            busPrijs += config.busOpties[c.value].prijs;
            aantalBussen++;
        });
        document.getElementById('calc-bus-count').value = aantalBussen;

        if(aantalBussen === 0) {
            document.getElementById('prijs-display').innerText = "€ 0,00";
            return;
        }

        const kostprijs = (km * busPrijs) + (uren * 35 * aantalBussen) + extra;
        let verkoop = Math.ceil((kostprijs * 1.25) / 5) * 5; 
        const verkoopIncl = verkoop * 1.09;
        
        document.getElementById('prijs-display').innerText = "€ " + verkoopIncl.toLocaleString('nl-NL', {minimumFractionDigits:2});
        document.getElementById('btw-display').innerText = "BTW: € " + (verkoopIncl - verkoop).toLocaleString('nl-NL', {minimumFractionDigits:2});
        document.getElementById('live-profit').innerText = "€ " + Math.round(verkoop - kostprijs);
    }
};

// Global initMaps voor Google Script
window.initMaps = calc.initMaps;
