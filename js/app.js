// app.js - Versie 8.1 (De Fix)
const app = {
    init: function() {
        // Controleer of de database bestaat, zo niet, maak leeg aan
        if(!state.db.klanten) state.db.klanten = [];
        if(!state.db.ritten) state.db.ritten = [];
        
        // Data integriteit fix
        state.db.klanten.forEach(k => { if(!k.contactpersonen) k.contactpersonen = []; });
        
        this.renderDashboard();
        this.renderMonthButtons();
        
        // Datum van vandaag invullen
        const t = new Date().toISOString().split('T')[0];
        const datumVeld = document.getElementById('rit_datum');
        if(datumVeld) {
            datumVeld.value = t;
            document.getElementById('rit_datum_eind').value = t;
        }

        // --- HIER ZAT DE FOUT: De rekenmachine moet ook gestart worden ---
        if(typeof calc !== 'undefined') {
            calc.init(); 
        }
    },

    nav: function(id) {
        document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
        const target = document.getElementById(id);
        if(target) target.classList.add('active');
        
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        if(event && event.currentTarget) event.currentTarget.classList.add('active');
    },

    changeYear: function(d) {
        state.app.currentYear += d;
        document.getElementById('display-year').innerText = state.app.currentYear;
        this.renderDashboard();
    },

    renderMonthButtons: function() {
        const m = ['Jan','Feb','Mrt','Apr','Mei','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
        const c = document.getElementById('month-buttons');
        if(!c) return;
        
        c.innerHTML = `<button onclick="state.app.currentMonth=-1;app.renderDashboard();" style="padding:2px 5px; margin:1px; cursor:pointer; font-weight:bold; border:1px solid #ccc;">Alle</button>`;
        m.forEach((naam, i) => {
            c.innerHTML += `<button onclick="state.app.currentMonth=${i};app.renderDashboard();" style="padding:2px 5px; margin:1px; cursor:pointer; border:1px solid #ccc;">${naam}</button>`;
        });
    },

    renderDashboard: function() {
        const tbody = document.getElementById('rit-tabel-body');
        if(!tbody) return;
        tbody.innerHTML = '';
        
        const filtered = state.db.ritten.filter(r => {
            const d = new Date(r.datum);
            return d.getFullYear() === state.app.currentYear && 
                   (state.app.currentMonth === -1 || d.getMonth() === state.app.currentMonth);
        }).sort((a,b) => new Date(a.datum) - new Date(b.datum));

        if(filtered.length === 0) {
            document.getElementById('empty-state').style.display = 'block';
            return;
        }
        document.getElementById('empty-state').style.display = 'none';

        filtered.forEach(r => {
            const k = state.db.klanten.find(x => x.id == r.klantId);
            const dStr = new Date(r.datum).toLocaleDateString('nl-NL', {day:'2-digit', month:'2-digit'});
            const ico = (type) => r.status[type] ? 'done' : '';

            tbody.innerHTML += `
                <tr>
                    <td><button class="btn-icon" onclick="calc.loadRit(${r.id})"><i class="fa-solid fa-pen"></i></button></td>
                    <td><b>${dStr}</b></td>
                    <td>
                        <div style="font-weight:bold; color:#004a99;">${k ? k.naam : '?'}</div>
                        <div style="font-size:11px; color:#666;">${r.dest}</div>
                    </td>
                    <td style="text-align:right; font-weight:bold;">${r.prijs}</td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'offerte')"><i class="status-icon fa-solid fa-check ${ico('offerte')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'bevestiging')"><i class="status-icon fa-solid fa-check ${ico('bevestiging')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'opdracht')"><i class="status-icon fa-solid fa-bus ${ico('opdracht')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'factuur')"><i class="status-icon fa-solid fa-file ${ico('factuur')}"></i></td>
                </tr>
            `;
        });
    },

    toggleStatus: function(id, field) {
        const r = state.db.ritten.find(x => x.id === id);
        if(r) {
            r.status[field] = !r.status[field];
            saveDB();
            this.renderDashboard();
        }
    },

    opslaan: function() {
        if(!document.getElementById('calc-klant-id').value) return alert("Selecteer een klant!");
        
        // Tijden ophalen
        const tijden = {};
        document.querySelectorAll('.select-uur').forEach(el => {
            const id = el.id.replace('h_','');
            if(el && el.nextElementSibling) {
                tijden[id] = el.value + ':' + el.nextElementSibling.value;
            }
        });

        // Bussen ophalen
        const busLijst = [];
        document.querySelectorAll('.bus-check:checked').forEach(c => busLijst.push(c.value));

        const rit = {
            id: state.app.activeRitId || Date.now(),
            datum: document.getElementById('rit_datum').value,
            klantId: document.getElementById('calc-klant-id').value,
            dest: document.getElementById('route-eind').value,
            prijs: document.getElementById('prijs-display').innerText,
            status: state.app.activeRitId ? state.db.ritten.find(x=>x.id==state.app.activeRitId).status : {offerte:false, bevestiging:false, opdracht:false, factuur:false},
            details: {
                datum_eind: document.getElementById('rit_datum_eind').value,
                pax: document.getElementById('calc-pax').value,
                contactIdx: document.getElementById('calc-contact').value,
                route: {
                    start: document.getElementById('route-start').value,
                    ophaal: document.getElementById('route-ophaal').value,
                    eind: document.getElementById('route-eind').value
                },
                tijden: tijden,
                bussen: busLijst,
                extra: document.getElementById('kosten-extra').value
            }
        };

        if(state.app.activeRitId) {
            const idx = state.db.ritten.findIndex(x => x.id == state.app.activeRitId);
            state.db.ritten[idx] = rit;
        } else {
            state.db.ritten.unshift(rit);
        }
        
        saveDB();
        calc.resetForm();
        this.nav('dashboard');
        this.renderDashboard();
    }
};

// INITIALISATIE (Start alles op)
window.onload = function() {
    // 1. Start de App (Motor)
    app.init();
    
    // 2. Start Google Maps (als geladen)
    if(window.initMaps) window.initMaps();
};
