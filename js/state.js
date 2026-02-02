
// state.js - Het geheugen van de app
const state = {
    db: { klanten: [], ritten: [] },
    app: { currentYear: new Date().getFullYear(), currentMonth: new Date().getMonth(), activeRitId: null, activeClientId: null },
    route: { km: 0, ritSec: 0, aanrijSec: 0, berekend: false }
};

// Configuratie Bussen (Aangepast zoals gevraagd)
const config = {
    busOpties: [ 
        {label:'19p', prijs:0.75}, 
        {label:'23p', prijs:0.75}, 
        {label:'50p', prijs:1.00}, 
        {label:'55p', prijs:1.00}, 
        {label:'60p', prijs:1.20}, 
        {label:'62p', prijs:1.20} 
    ]
};

// Functie om op te slaan
function saveDB() {
    localStorage.setItem('berkhout_v4', JSON.stringify(state.db));
}
