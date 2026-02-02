// calculatie.js - Rekenmachine
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
        
        // Velden leegmaken
        document.querySelectorAll('input:not(#route-start)').forEach(i => i.value = '');
        // Contactpersoon dropdown specifiek leegmaken!
        document.getElementById('calc-contact').innerHTML = '<option value="">-- Kies eerst klant --</option>';
        document.getElementById('klant-details').classList.add('hidden');
        document.getElementById('bus-container').querySelectorAll('input').forEach(c => c.checked = false);
        
        // State resetten
        state.route = { km:0, ritSec:0, aanrijSec:0, berekend:false };
        document.getElementById('info-totaal').innerText = "0 km";
        document.getElementById('prijs-display').innerText = "€ 0,00";
        document.getElementById('live-profit').innerText = "€ 0";
    },

    syncDates: function() {
        document.getElementById('rit_datum_eind').value = document.getElementById('rit_datum').value;
        this.rekenUren();
    },

    // Google Maps logic
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
                // Leg 0 = Garage -> Klant (Aanrijtijd)
                state.route.aanrijSec = legs[0].duration.value;
                
                let meters = 0; let rSec = 0;
                legs.forEach((l, i) => {
                    meters += l.distance.value;
                    if(i > 0) rSec += l.duration.value; // Alle legs na de eerste zijn 'rit'
                });
                state.route.ritSec = rSec;

                // Dagtocht = 2x KM, BrengHaal = 4x KM (ongeveer)
                const type = document.getElementById('rit-type').value;
                const factor = (type === 'vv') ? 2 : (type === 'brenghaal' ? 4 : 1);
                state.route.km = Math.round((meters * factor) / 1000);
                
                state.route.berekend = true;

                // UI Update
                document.getElementById('info-rit').innerText = Math.round(rSec/60) + " min";
                document.getElementById('info-aanrij').innerText = Math.round(state.route.aanrijSec/60) + " min";
                document.getElementById('info-totaal').innerText = state.route.km + " km";

                this.syncTijden(); // Nu pas tijden overschrijven
                this.rekenPrijs();
            }
        });
    },

    // DE FIX: Garage Vertrektijd = Klant Vertrek - 15min - Aanrijtijd
    syncTijden: function() {
        if(!state.route.berekend) return; 

        const type = document.getElementById('rit-type').value;
        const aanrijMs = state.route.aanrijSec * 1000;
        const ritMs = state.route.ritSec * 1000;

        const setVal = (id, date) => {
            const h = document.getElementById('h_'+id); 
            const m = document.getElementById('m_'+id);
            if(h && m) {
                h.value = date.getHours().toString().padStart(2,'0');
                m.value = date.getMinutes().toString().padStart(2,'0');
            }
        };
        const getVal = (id) => {
            const h = parseInt(document.getElementById('h_'+id).value);
            const m = parseInt(document.getElementById('m_'+id).value);
            return new Date(2000,0,1,h,m);
        };

        if(type === 'vv') { // Dagtocht
            const vertrekKlant = getVal('std_3');
            
            // Garage Vertrek
            const garageVertrek = new Date(vertrekKlant.getTime() - (15*60000) - aanrijMs);
            setVal('std_1', garageVertrek);
            setVal('std_2', new Date(vertrekKlant.getTime() - (15*60000)));

            // Aankomst Bestemming
            setVal('std_4', new Date(vertrekKlant.getTime() + ritMs));

            // Retour
            const vertrekRetour = getVal('std_5');
            const aankomstKlant = new Date(vertrekRetour.getTime() + ritMs);
            setVal('std_6', aankomstKlant);
            
            // Garage Aankomst
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
        
        // FIX: Update bus teller veld
        document.getElementById('calc-bus-count').value = aantalBussen;

        if(aantalBussen === 0) {
            document.getElementById('prijs-display').innerText = "€ 0,00";
            return;
        }

        const kostprijs = (km * busPrijs) + (uren * 35 * aantalBussen) + extra;
        
        // Marge 1.25 en afronden op 5 euro
        let verkoop = kostprijs * 1.25;
        verkoop = Math.ceil(verkoop / 5) * 5; 
        
        const verkoopIncl = verkoop * 1.09;
        const winst = verkoop - kostprijs;
        
        document.getElementById('prijs-display').innerText = "€ " + verkoopIncl.toLocaleString('nl-NL', {minimumFractionDigits:2});
        document.getElementById('btw-display').innerText = "BTW: € " + (verkoopIncl - verkoop).toLocaleString('nl-NL', {minimumFractionDigits:2});
        document.getElementById('live-profit').innerText = "€ " + Math.round(winst);
    },

    loadRit: function(id) {
        state.app.activeRitId = id;
        const r = state.db.ritten.find(x => x.id == id);
        
        app.nav('calculatie');
        document.getElementById('calc-title').innerText = "Rit Bewerken";
        
        document.getElementById('rit_datum').value = r.datum;
        document.getElementById('rit_datum_eind').value = r.details.datum_eind;
        
        // Klant laden
        const klant = state.db.klanten.find(k => k.id == r.klantId);
        crm.fillCalcClient(klant);
        
        document.getElementById('calc-pax').value = r.details.pax;
        document.getElementById('route-eind').value = r.details.route.eind;
        document.getElementById('kosten-extra').value = r.details.extra || 0;
        
        // Bussen aanvinken
        document.querySelectorAll('.bus-check').forEach(c => c.checked = false);
        r.details.bussen.forEach(val => {
            const el = document.querySelector(`.bus-check[value="${val}"]`);
            if(el) el.checked = true;
        });

        // Tijden herstellen
        setTimeout(() => {
            for(const [k,v] of Object.entries(r.details.tijden)) {
                const p = v.split(':');
                const h = document.getElementById('h_'+k);
                const m = document.getElementById('m_'+k);
                if(h && m) { h.value=p[0]; m.value=p[1]; }
            }
            this.rekenUren();
        }, 200);
    }
};
