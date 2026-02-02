
// crm.js - Klantenbeheer
const crm = {
    nieuwKlantScherm: function() {
        state.app.activeClientId = null;
        // Velden leegmaken
        ['crm-naam','crm-adres','crm-plaats'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('crm-contacts-container').innerHTML = '';
        this.addContactRow(); // Altijd 1 lege regel
        
        // Wissel van lijst naar detail
        document.getElementById('crm-list-view').classList.add('hidden');
        document.getElementById('crm-detail-view').classList.remove('hidden');
    },

    terugNaarLijst: function() {
        document.getElementById('crm-list-view').classList.remove('hidden');
        document.getElementById('crm-detail-view').classList.add('hidden');
    },

    addContactRow: function(d = {}) {
        const div = document.createElement('div');
        div.style.cssText = "display:grid; grid-template-columns:1fr 1fr 30px; gap:5px; margin-bottom:5px;";
        div.innerHTML = `
            <input placeholder="Naam" value="${d.naam||''}">
            <input placeholder="Email" value="${d.email||''}">
            <button onclick="this.parentElement.remove()" style="background:#eee;border:1px solid #ccc;cursor:pointer;">x</button>
        `;
        document.getElementById('crm-contacts-container').appendChild(div);
    },

    saveClient: function() {
        const naam = document.getElementById('crm-naam').value;
        if(!naam) return alert("Naam is verplicht");

        const contacts = [];
        document.querySelectorAll('#crm-contacts-container div').forEach(row => {
            const inputs = row.querySelectorAll('input');
            if(inputs[0].value) contacts.push({naam: inputs[0].value, email: inputs[1].value});
        });

        const client = {
            id: state.app.activeClientId || Date.now(),
            naam: naam,
            adres: document.getElementById('crm-adres').value,
            plaats: document.getElementById('crm-plaats').value,
            contactpersonen: contacts
        };

        if(state.app.activeClientId) {
            // Bestaande updaten
            const idx = state.db.klanten.findIndex(x => x.id == state.app.activeClientId);
            state.db.klanten[idx] = client;
        } else {
            // Nieuwe toevoegen
            state.db.klanten.push(client);
        }

        saveDB();
        this.renderClientList();
        this.terugNaarLijst();
    },

    renderClientList: function() {
        const q = document.getElementById('crm-search').value.toLowerCase();
        const tbody = document.getElementById('crm-table-body');
        tbody.innerHTML = '';

        state.db.klanten.filter(k => k.naam.toLowerCase().includes(q)).forEach(k => {
            tbody.innerHTML += `
                <tr>
                    <td><b>${k.naam}</b></td>
                    <td>${k.plaats}</td>
                    <td>${k.contactpersonen[0]?.email || '-'}</td>
                    <td><button class="btn-icon" onclick="crm.selectForOffer(${k.id})"><i class="fa-solid fa-arrow-right"></i></button></td>
                </tr>
            `;
        });
    },

    // Vanuit CRM direct een offerte starten
    selectForOffer: function(id) {
        calc.resetForm();
        app.nav('calculatie');
        this.fillCalcClient(state.db.klanten.find(k => k.id == id));
    },

    // Zoekbalk functionaliteit in Calculatie scherm
    filterCalcClients: function() {
        const q = document.getElementById('calc-klant-zoek').value.toLowerCase();
        const res = document.getElementById('calc-klant-results');
        res.innerHTML = '';
        if(q.length < 2) { res.style.display='none'; return; }

        state.db.klanten.filter(k => k.naam.toLowerCase().includes(q)).forEach(k => {
            res.style.display = 'block';
            const div = document.createElement('div');
            div.className = 'search-item';
            div.innerHTML = `<b>${k.naam}</b> <small>(${k.plaats})</small>`;
            div.onclick = () => {
                this.fillCalcClient(k);
                res.style.display = 'none';
            };
            res.appendChild(div);
        });
    },

    fillCalcClient: function(k) {
        document.getElementById('calc-klant-zoek').value = k.naam;
        document.getElementById('calc-klant-id').value = k.id;
        document.getElementById('route-ophaal').value = k.adres + ', ' + k.plaats;
        
        // Detail blokje tonen
        const det = document.getElementById('klant-details');
        det.classList.remove('hidden');
        det.innerHTML = `<b>${k.naam}</b><br>${k.adres}, ${k.plaats}`;

        // Contact dropdown vullen
        const sel = document.getElementById('calc-contact');
        sel.innerHTML = k.contactpersonen.map((c,i) => `<option value="${i}">${c.naam}</option>`).join('');
        
        // Als er een route is ingevuld, herberekenen met nieuw startadres
        if(document.getElementById('route-eind').value) calc.berekenRoute(); 
    }
};
