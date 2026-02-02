// calculatie.js - Versie 7.8 (Fixes)
const calc = {
    init: function() {
        const container = document.getElementById('bus-container');
        if(!container) return;
        
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
        
        const contactSelect = document.getElementById('calc-contact');
        if(contactSelect) contactSelect.innerHTML = '<option value="">-- Kies eerst klant --</option>';
        
        document.getElementById('klant-details').classList.add('hidden');
        document.querySelectorAll('.bus-check').forEach(c => c.checked = false);
        state.route = { km:0, ritSec:0, aanrijSec:0, berekend:false };
        
        ['info-aanrij-km','info-aanrij-tijd','info-rit-km','info-rit-tijd','info-totaal'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.innerText = '-';
        });
        document.getElementById('prijs-display').innerText = "€ 0,00";
        document.getElementById('live-profit').innerText = "€ 0";
    },

    syncDates: function() {
        document.getElementById('rit_datum_eind').value = document.getElementById('rit_datum').value;
        this.rekenUren();
    },

    initMaps: function() {
        const opts = { componentRestrictions: {country:'nl'} };
        ['route-start','route-ophaal','route-eind','route-via1','route-via2'].forEach(id => {
            const el = document.getElementById(id);
            if(el) new google.maps.places.Autocomplete(el, opts).addListener('place_changed', calc.berekenRoute);
        });
        
        const crmEl = document.getElementById('crm-adres');
        if(crmEl) {
            const ac = new google.maps.places.Autocomplete(crmEl, opts);
            ac.addListener('place_changed', () => {
                const p = ac.getPlace();
                let stad = '';
                if(p.address_components) p.address_components.forEach(c => { if(c.types.includes('locality')) stad = c.long_name; });
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
        // Route: Garage -> Ophaal -> Eind
        svc.route({origin:s, destination:e, waypoints:[{location:o}], travelMode:'DRIVING'}, (res, stat) => {
            if(stat === 'OK') {
                const legs = res.routes[0].legs;
                
                // Garage -> Klant
                const aanrijDist = legs[0].distance.value;
                const aanrijTime = legs[0].duration.value;
                state.route.aanrijSec = aanrijTime;

                // Klant -> Bestemming
                const ritDist = legs[1].distance.value;
                const ritTime = legs[1].duration.value;
                state.route.ritSec = ritTime;

                // Totaal KM
                const type = document.getElementById('rit-type').value;
                let totalMeters = 0;
                
                if(type === 'brenghaal') totalMeters = (aanrijDist + ritDist) * 4; 
                else if(type === 'vv') totalMeters = (aanrijDist + ritDist) * 2;
                else totalMeters = aanrijDist + ritDist;
                
                state.route.km = Math.round(totalMeters / 1000);
                state.route.berekend = true;

                // UI Update
                document.getElementById('info-aanrij-km').innerText = Math.round(aanrijDist/1000) + " km";
                document.getElementById('info-aanrij-tijd').innerText = Math.round(aanrijTime/60) + " min";
                
                document.getElementById('info-rit-km').innerText = Math.round(ritDist/1000) + " km";
                document.getElementById('info-rit-tijd').innerText = Math.round(ritTime/60) + " min";

                document.getElementById('info-totaal').innerText = state.route.km + " km";

                calc.syncTijden();
                calc.rekenPrijs();
            }
        });
    },

    syncTijden: function() {
        if(!state.route.berekend) return;
        const type = document.getElementById('rit-type').value;
        const ams = state.route.aanrijSec * 1000;
        const rms = state.route.ritSec * 1000;
        const voorstaan = 15 * 60000; 

        const set = (id, d) => {
            const h=document.getElementById('h_'+id), m=document.getElementById('m_'+id);
            if(h&&m){ 
                h.value=d.getHours().toString().padStart(2,'0'); 
                // Kwartier afronding
                let min = d.getMinutes();
                if(min < 7) min="00"; else if(min < 22) min="15"; else if(min < 37) min="30"; else min="45";
                m.value=min;
            }
        };
        const get = (id) => {
            const h=parseInt(document.getElementById('h_'+id).value), m=parseInt(document.getElementById('m_'+id).value);
            return new Date(2000,0,1,h,m);
        };

        if(document.getElementById('h_std_3')) {
            const vk = get('std_3');
            set('std_1', new Date(vk - ams - voorstaan)); 
            set('std_2', new Date(vk - voorstaan));
        }

        if(type === 'vv') {
            const vk = get('std_3');
            set('std_4', new Date(vk.getTime() + rms)); 
            const vr = get('std_5');
            const ak = new Date(vr.getTime() + rms);
            set('std_6', ak);
            set('std_7', new Date(ak.getTime() + ams)); 
        }
        this.rekenUren();
    },

    wisselTijdLijst: function() {
        const type = document.getElementById('rit-type').value;
        const c = document.getElementById('tijd-container');
        
        const tpl = {
            vv: [
                {id:'std_1',l:'Vertrek Garage'},{id:'std_2',l:'Voorstaan'},
                {id:'std_3',l:'Vertrek Klant',hl:true,h:8,m:'45'},{id:'std_4',l:'Aankomst Best.'},
                {id:'std_5',l:'Vertrek Retour',h:17},{id:'std_6',l:'Aankomst Klant'},{id:'std_7',l:'Aankomst Garage'}
            ],
            enkel: [
                {id:'std_1',l:'Vertrek Garage'},{id:'std_3',l:'Vertrek Klant',hl:true,h:13},
                {id:'std_4',l:'Aankomst Best.'},{id:'std_7',l:'Aankomst Garage'}
            ],
            brenghaal: [
                {id:'std_1',l:'V. Garage (Heen)'},{id:'std_3',l:'V. Klant (Heen)',hl:true,h:8,m:'45'},{id:'std_4',l:'A. Best. (Heen)'},
                {id:'std_x', l:'--- PAUZE ---'}, 
                {id:'std_5',l:'V. Garage (Terug)'},{id:'std_6',l:'V. Best. (Terug)', h:17},{id:'std_7',l:'A. Klant (Terug)'},{id:'std_8',l:'A. Garage (Terug)'}
            ]
        };
        
        const list = tpl[type] || tpl.vv;
        
        c.innerHTML = list.map(t => {
            if(t.id === 'std_x') return `<div style="text-align:center; font-weight:bold; padding:5px; color:#ccc;">----------</div>`;
            
            let uOpts = ''; for(let i=0;i<24;i++) { let val=i.toString().padStart(2,'0'); uOpts += `<option value="${val}" ${i==(t.h||8)?'selected':''}>${val}</option>`; }
            let mOpts = ''; ['00','15','30','45'].forEach(m => { mOpts += `<option value="${m}" ${m==(t.m||'00')?'selected':''}>${m}</option>`; });

            const ch = t.hl ? "calc.syncTijden()" : "calc.rekenUren()";
            const sty = t.hl ? 'border-left:3px solid var(--success);background:#f0fdf4;' : '';
            
            return `
            <div class="time-row" style="${sty}">
                <span style="font-size:12px;width:100px;">${t.l}</span>
                <div class="time-select-group">
                    <select id="h_${t.id}" class="select-uur" onchange="${ch}">${uOpts}</select> : 
                    <select id="m_${t.id}" class="select-min" onchange="${ch}">${mOpts}</select>
                </div>
            </div>`;
        }).join('');
        this.rekenUren();
    },

    rekenUren: function() {
        const el = document.querySelectorAll('.select-uur'); if(el.length<2)return;
        const s = new Date(2000,0,1,el[0].value,el[0].nextElementSibling.value);
        const e = new Date(2000,0,1,el[el.length-1].value,el[el.length-1].nextElementSibling.value);
        let d = (e-s)/3600000; if(d<0) d+=24;
        document.getElementById('uren-display').innerText = d.toFixed(2);
        this.rekenPrijs();
    },

    rekenPrijs: function() {
        const km = state.route.km;
        const uren = parseFloat(document.getElementById('uren-display').innerText)||0;
        const extra = parseFloat(document.getElementById('kosten-extra').value)||0;
        
        let busPrijs = 0; let n = 0;
        document.querySelectorAll('.bus-check:checked').forEach(c => { 
            busPrijs += config.busOpties[c.value].prijs; 
            n++; 
        });
        
        // FIX: Update het teller veld
        const busCountEl = document.getElementById('calc-bus-count');
        if(busCountEl) busCountEl.value = n;
        
        if(n===0){ document.getElementById('prijs-display').innerText="€ 0,00"; return; }
        
        const kostprijs = (km * busPrijs) + (uren * 35 * n) + extra;
        const verkoop = Math.ceil((kostprijs * 1.25) / 5) * 5; 
        const verkoopIncl = verkoop * 1.09;
        
        document.getElementById('prijs-display').innerText = "€ " + verkoopIncl.toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('btw-display').innerText = "BTW: € " + (verkoopIncl - verkoop).toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('live-profit').innerText = "€ " + Math.round(verkoop - kostprijs);
    },

    loadRit: function(id) {
        state.app.activeRitId = id;
        const r = state.db.ritten.find(x => x.id == id);
        if(!r) return;

        app.nav('calculatie');
        document.getElementById('calc-title').innerText = "Rit Bewerken";
        
        document.getElementById('rit_datum').value = r.datum;
        document.getElementById('rit_datum_eind').value = r.details.datum_eind;
        
        // Klant laden
        const klant = state.db.klanten.find(k => k.id == r.klantId);
        if(klant) crm.fillCalcClient(klant);
        
        document.getElementById('calc-pax').value = r.details.pax;
        document.getElementById('route-eind').value = r.details.route.eind;
        document.getElementById('kosten-extra').value = r.details.extra || 0;
        
        // Bussen aanvinken
        document.querySelectorAll('.bus-check').forEach(c => c.checked = false);
        if(r.details.bussen) {
            r.details.bussen.forEach(val => {
                const el = document.querySelector(`.bus-check[value="${val}"]`);
                if(el) el.checked = true;
            });
        }

        // Tijden herstellen (even wachten tot dropdowns gebouwd zijn)
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

window.initMaps = calc.initMaps;
