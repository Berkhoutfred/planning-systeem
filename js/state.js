// state.js - Versie 7.8 (Crash-bestendig)
const state = {
    // Probeer data te laden. Bij fouten: begin met schone lei.
    db: (function() {
        try {
            const s = localStorage.getItem('berkhout_v7');
            return s ? JSON.parse(s) : { klanten: [], ritten: [] };
        } catch (e) {
            console.warn("Data reset ivm update");
            return { klanten: [], ritten: [] };
        }
    })(),
    app: { currentYear: new Date().getFullYear(), currentMonth: -1, activeRitId: null, activeClientId: null },
    route: { km: 0, ritSec: 0, aanrijSec: 0, berekend: false }
};

const config = {
    busOpties: [ 
        {label:'19p', prijs:0.75}, {label:'23p', prijs:0.75}, 
        {label:'50p', prijs:1.00}, {label:'55p', prijs:1.00}, 
        {label:'60p', prijs:1.20}, {label:'62p', prijs:1.20} 
    ]
};

function saveDB() {
    localStorage.setItem('berkhout_v7', JSON.stringify(state.db));
}
