// calculatie.js - V7.2 Fixes
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
        
        ['info-aanrij-km','info-aanrij-tijd','info-rit-km','info-rit-tijd','info-totaal'].forEach(id => document.getElementById(id).innerText = '-');
        document.getElementById('prijs-display').innerText = "€ 0,00";
        document.getElementById('live-profit').innerText = "€ 0";
    },

    syncDates: function() {
        document.getElementById('rit_datum_eind').value = document.getElementById('rit_datum').value;
        this.rekenUren();
    },

    // MAPS
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

        const w = [];
        // Google Maps Route aanvragen
        // Leg 0: Garage -> Ophaal (Aanrij)
        // Leg 1: Ophaal -> Bestemming (Rit)
        const waypoints = [{location: o}]; 
        
        new google.maps.DirectionsService().route({origin:s, destination:e, waypoints:waypoints, travelMode:'DRIVING'}, (res, stat) => {
            if(stat === 'OK') {
                const legs = res.routes[0].legs;
                
                // Leg 1: Garage -> Klant (Aanrij)
                const aanrijDist = legs[0].distance.value;
                const aanrijTime = legs[0].duration.value;
                state.route.aanrijSec = aanrijTime;

                // Leg 2: Klant -> Bestemming (De Rit)
                const ritDist = legs[1].distance.value;
                const ritTime = legs[1].duration.value;
                state.route.ritSec = ritTime;

                // Totaal berekenen
                const type = document.getElementById('rit-type').value;
                
                // Dagtocht (VV) = (Aanrij + Rit) * 2
                // BrengHaal = (Aanrij + Rit + Rit + Aanrij) oftewel 2x volledig op en neer
                let totalMeters = 0;
                
                if(type === 'brenghaal') {
                    // Garage-Klant-Bestemming-Garage EN Garage-Bestemming-Klant-Garage
                    totalMeters = (aanrijDist + ritDist) * 4; // Ruwe schatting voor 2x retour
                } else if(type === 'vv') {
                    totalMeters = (aanrijDist + ritDist) * 2;
                } else {
                    totalMeters = aanrijDist + ritDist;
                }
                
                state.route.km = Math.round(totalMeters / 1000);
                state.route.berekend = true;

                // UI Vullen
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
        
        // 15 min voorstaan
        const voorstaan = 15 * 60000; 

        const set = (id, d) => {
            const h=document.getElementById('h_'+id), m=document.getElementById('m_'+id);
            if(h&&m){ 
                h.value=d.getHours().toString().padStart(2,'0'); 
                // Rond af op dichtstbijzijnde kwartier voor weergave in dropdown
                let min = d.getMinutes();
                if(min < 7) min="00"; else if(min < 22) min="15"; else if(min < 37) min="30"; else min="45";
                m.value=min;
            }
        };
        const get = (id) => {
            const h=parseInt(document.getElementById('h_'+id).value), m=parseInt(document.getElementById('m_'+id).value);
            return new Date(2000,0,1,h,m);
        };

        // Bereken Vertrek Garage op basis van Vertrek Klant
        if(document.getElementById('h_std_3')) {
            const vk = get('std_3');
            set('std_1', new Date(vk - ams - voorstaan)); // Vertrek Garage = Klant - Aanrij - 15min
            set('std_2', new Date(vk - voorstaan));       // Voorstaan = Klant - 15min
        }

        if(type === 'vv') {
            const vk = get('std_3');
            set('std_4', new Date(vk.getTime() + rms)); // Aankomst = Vertrek + Rit
            
            const vr = get('std_5');
            const ak = new Date(vr.getTime() + rms);
            set('std_6', ak);
            set('std_7', new Date(ak.getTime() + ams)); // Garage = Aankomst Klant + Aanrij
        }
        
        this.rekenUren();
    },

    wisselTijdLijst: function() {
        const type = document.getElementById('rit-type').value;
        const c = document.getElementById('tijd-container');
        
        // Templates met DROPDOWNS
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
                {id:'std_x', l:'--- PAUZE ---'}, // Visuele scheiding
                {id:'std_5',l:'V. Garage (Terug)'},{id:'std_6',l:'V. Best. (Terug)', h:17},{id:'std_7',l:'A. Klant (Terug)'},{id:'std_8',l:'A. Garage (Terug)'}
            ]
        };
        
        const list = tpl[type] || tpl.vv;
        
        // Bouw de HTML met SELECTS
        c.innerHTML = list.map(t => {
            if(t.id === 'std_x') return `<div style="text-align:center; font-weight:bold; padding:5px; color:#ccc;">----------</div>`;
            
            // Uren opties (00-23)
            let uOpts = ''; for(let i=0;i<24;i++) { 
                let val = i.toString().padStart(2,'0'); 
                uOpts += `<option value="${val}" ${i==(t.h||8)?'selected':''}>${val}</option>`;
            }
            // Minuten opties (00,15,30,45)
            let mOpts = ''; ['00','15','30','45'].forEach(m => {
                mOpts += `<option value="${m}" ${m==(t.m||'00')?'selected':''}>${m}</option>`;
            });

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
        
        // Pak start en eindtijd van de lijst
        // Bij breng/haal moeten we oppassen, we tellen gewoon totaal verschil
        const s = new Date(2000,0,1,el[0].value,el[0].nextElementSibling.value);
        const e = new Date(2000,0,1,el[el.length-1].value,el[el.length-1].nextElementSibling.value);
        
        let d = (e-s)/3600000; 
        if(d<0) d+=24;
        
        // Bij breng/haal (dat zijn er 8) kunnen we grofweg zeggen: de rit is 2x de enkele tijd + pauze? 
        // Nee, veiligste is gewoon begin tot eind.
        
        document.getElementById('uren-display').innerText = d.toFixed(2);
        this.rekenPrijs();
    },

    rekenPrijs: function() {
        const km = state.route.km;
        const uren = parseFloat(document.getElementById('uren-display').innerText)||0;
        const extra = parseFloat(document.getElementById('kosten-extra').value)||0;
        
        let bp=0, n=0;
        // Bussen tellen FIX
        document.querySelectorAll('.bus-check:checked').forEach(c => { 
            bp+=config.busOpties[c.value].prijs; 
            n++; 
        });
        document.getElementById('calc-bus-count').value = n; // Update teller veld
        
        if(n===0){ document.getElementById('prijs-display').innerText="€ 0,00"; return; }
        
        const kp = (km*bp) + (uren*35*n) + extra;
        const vp = Math.ceil((kp*1.25)/5)*5; 
        const vi = vp*1.09;
        
        document.getElementById('prijs-display').innerText = "€ " + vi.toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('btw-display').innerText = "BTW: € " + (vi-vp).toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('live-profit').innerText = "€ " + Math.round(vp-kp);
    }
};

window.initMaps = calc.initMaps;
