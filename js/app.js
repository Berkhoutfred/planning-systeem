// app.js - Hoofdprogramma
const app = {
    init: function() {
        const s = localStorage.getItem('berkhout_v4');
        if(s) state.db = JSON.parse(s);
        // Data fix
        state.db.klanten.forEach(k => { if(!k.contactpersonen) k.contactpersonen = []; });
        
        this.renderDashboard();
        this.renderMonthButtons();
        
        // Init andere modules
        if(typeof calc !== 'undefined') calc.init();
    },

    nav: function(id) {
        document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        event.currentTarget.classList.add('active');
    },

    changeYear: function(d) {
        state.app.currentYear += d;
        document.getElementById('display-year').innerText = state.app.currentYear;
        this.renderDashboard();
    },

    renderMonthButtons: function() {
        const m = ['Jan','Feb','Mrt','Apr','Mei','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
        const c = document.getElementById('month-buttons');
        c.innerHTML = `<button onclick="state.app.currentMonth=-1;app.renderMonthButtons();app.renderDashboard()" style="padding:5px; margin:2px; ${state.app.currentMonth===-1?'background:#004a99;color:white':''}">Alle</button>`;
        m.forEach((naam, i) => {
            c.innerHTML += `<button onclick="state.app.currentMonth=${i};app.renderMonthButtons();app.renderDashboard()" style="padding:5px; margin:2px; ${state.app.currentMonth===i?'background:#004a99;color:white':''}">${naam}</button>`;
        });
    },

    renderDashboard: function() {
        const tbody = document.getElementById('rit-tabel-body');
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
                    <td class="status-cell" onclick="pdf.openModal(${r.id})"><i class="status-icon fa-solid fa-circle-check ${ico('offerte')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'bevestiging')"><i class="status-icon fa-solid fa-circle-check ${ico('bevestiging')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'opdracht')"><i class="status-icon fa-solid fa-bus ${ico('opdracht')}"></i></td>
                    <td class="status-cell" onclick="app.toggleStatus(${r.id}, 'factuur')"><i class="status-icon fa-solid fa-file-invoice-dollar ${ico('factuur')}"></i></td>
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
        
        const tijden = {};
        document.querySelectorAll('.select-uur').forEach(el => {
            const id = el.id.replace('h_','');
            tijden[id] = el.value + ':' + el.nextElementSibling.value;
        });

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
                bussen: Array.from(document.querySelectorAll('.bus-check:checked')).map(c => c.value),
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

window.onload = () => app.init();
