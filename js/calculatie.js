// calculatie.js - De Rekenmachine
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

    // --- MAPS (BELANGRIJK) ---
    initMaps: function() {
        const opts = { componentRestrictions: {country:'nl'} };
        // Koppel aan alle adresvelden
        ['route-start','route-ophaal','route-eind','route-via1','route-via2'].forEach(id => {
            const el = document.getElementById(id);
            if(el) new google.maps.places.Autocomplete(el, opts).addListener('place_changed', calc.berekenRoute);
        });
        
        // Koppel aan CRM adres
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
        if(document.getElementById('route-via1').value) w.push({location:document.getElementById('route-via1').value});
        if(document.getElementById('route-via2').value) w.push({location:document.getElementById('route-via2').value});

        new google.maps.DirectionsService().route({origin:s, destination:e, waypoints:w, travelMode:'DRIVING'}, (res, stat) => {
            if(stat === 'OK') {
                const l = res.routes[0].legs;
                state.route.aanrijSec = l[0].duration.value;
                let m=0, r=0;
                l.forEach((leg, i) => { m += leg.distance.value; if(i>0) r += leg.duration.value; });
                state.route.ritSec = r;

                const type = document.getElementById('rit-type').value;
                const factor = (type === 'vv') ? 2 : (type === 'brenghaal' ? 4 : 1);
                state.route.km = Math.round((m * factor) / 1000);
                state.route.berekend = true;

                document.getElementById('info-rit').innerText = Math.round(r/60) + " min";
                document.getElementById('info-aanrij').innerText = Math.round(state.route.aanrijSec/60) + " min";
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

        const set = (id, d) => {
            const h=document.getElementById('h_'+id), m=document.getElementById('m_'+id);
            if(h&&m){ h.value=d.getHours().toString().padStart(2,'0'); m.value=d.getMinutes().toString().padStart(2,'0'); }
        };
        const get = (id) => {
            const h=parseInt(document.getElementById('h_'+id).value), m=parseInt(document.getElementById('m_'+id).value);
            return new Date(2000,0,1,h,m);
        };

        if(type === 'vv') {
            const vk = get('std_3');
            set('std_1', new Date(vk - ams - 900000)); // -15 min
            set('std_2', new Date(vk - 900000));
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
            vv: [{id:'std_1',l:'Vertrek Garage'},{id:'std_2',l:'Voorstaan'},{id:'std_3',l:'Vertrek Klant',hl:true,h:8,m:45},{id:'std_4',l:'Aankomst Best.'},{id:'std_5',l:'Vertrek Retour',h:17},{id:'std_6',l:'Aankomst Klant'},{id:'std_7',l:'Aankomst Garage'}],
            enkel: [{id:'std_1',l:'Vertrek Garage'},{id:'std_3',l:'Vertrek Klant',hl:true,h:13},{id:'std_4',l:'Aankomst Best.'},{id:'std_7',l:'Aankomst Garage'}]
        };
        const list = (type === 'enkel') ? tpl.enkel : tpl.vv;
        
        c.innerHTML = list.map(t => {
            let uOpts='', mOpts='';
            for(let i=0;i<24;i++) uOpts+=`<option value="${i.toString().padStart(2,'0')}" ${i==(t.h||8)?'selected':''}>${i.toString().padStart(2,'0')}</option>`;
            ['00','15','30','45'].forEach(m => mOpts+=`<option value="${m}" ${m==(t.m||0)?'selected':''}>${m}</option>`);
            const ch = t.hl ? "calc.syncTijden()" : "calc.rekenUren()";
            const sty = t.hl ? 'border-left:3px solid var(--success);background:#f0fdf4;' : '';
            return `<div class="time-row" style="${sty}"><span style="font-size:12px;width:100px;">${t.l}</span><div class="time-select-group"><select id="h_${t.id}" class="select-uur" onchange="${ch}">${uOpts}</select>:<select id="m_${t.id}" class="select-min" onchange="${ch}">${mOpts}</select></div></div>`;
        }).join('');
        this.rekenUren();
    },

    rekenUren: function() {
        const el = document.querySelectorAll('.select-uur'); if(el.length<2)return;
        const s = new Date(2000,0,1,el[0].value,el[0].nextElementSibling.value);
        const e = new Date(2000,0,1,el[el.length-1].value,el[el.length-1].nextElementSibling.value);
        let d = (e-s)/3600000; if(d<0)d+=24;
        document.getElementById('uren-display').innerText = d.toFixed(2);
        this.rekenPrijs();
    },

    rekenPrijs: function() {
        const km = state.route.km;
        const uren = parseFloat(document.getElementById('uren-display').innerText)||0;
        const extra = parseFloat(document.getElementById('kosten-extra').value)||0;
        let bp=0, n=0;
        document.querySelectorAll('.bus-check:checked').forEach(c => { bp+=config.busOpties[c.value].prijs; n++; });
        document.getElementById('calc-bus-count').value = n;
        
        if(n===0){ document.getElementById('prijs-display').innerText="€ 0,00"; return; }
        
        const kp = (km*bp) + (uren*35*n) + extra;
        const vp = Math.ceil((kp*1.25)/5)*5; // Marge & Afronden
        const vi = vp*1.09;
        
        document.getElementById('prijs-display').innerText = "€ " + vi.toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('btw-display').innerText = "BTW: € " + (vi-vp).toLocaleString('nl-NL',{minimumFractionDigits:2});
        document.getElementById('live-profit').innerText = "€ " + Math.round(vp-kp);
    }
};

// GLOBAL EXPORT VOOR GOOGLE MAPS
window.initMaps = calc.initMaps;
