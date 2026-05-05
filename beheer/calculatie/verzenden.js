/**
 * Bestand: beheer/calculatie/verzenden.js
 * Functie: Regelt de popup met Download & ECHTE Email actie
 */

let actieveRitId = 0;
let actiefType = '';
let actieveToken = '';
/** @type {'calculatie'|'sales_rit_dossier'} */
let statusDocumentEntity = 'calculatie';

document.addEventListener('DOMContentLoaded', function() {
    const vandaag = new Date().toISOString().split('T')[0];
    const datumVeld = document.getElementById('modalDatum');
    if(datumVeld) datumVeld.value = vandaag;
});

function statusIconElement(type, id) {
    if (statusDocumentEntity === 'sales_rit_dossier') {
        return document.getElementById('icon-' + type + '-s-' + id);
    }
    return document.getElementById('icon-' + type + '-' + id);
}

/**
 * @param {string} [entity] calculatie | sales_rit_dossier
 */
function openStatus(id, type, status, token, entity) {
    actieveRitId = id;
    actiefType = type;
    actieveToken = typeof token === 'string' ? token : '';
    statusDocumentEntity = entity === 'sales_rit_dossier' ? 'sales_rit_dossier' : 'calculatie';

    const pdfMailRow = document.getElementById('statusModalPdfMailRow');
    if (pdfMailRow) {
        pdfMailRow.style.display = statusDocumentEntity === 'sales_rit_dossier' ? 'none' : 'grid';
    }

    // Teksten instellen
    if (statusDocumentEntity === 'sales_rit_dossier') {
        document.getElementById('modalTitle').innerText =
            (type === 'bevestiging' ? 'Klant akkoord' : 'Prijs / voorstel') + ' — sales-rit #' + id;
        document.getElementById('modalTypeDisplay').innerText =
            'Geen calculatie-PDF: leg vast wanneer je extern hebt gemaild of gebeld.';
    } else {
        document.getElementById('modalTitle').innerText = type.charAt(0).toUpperCase() + type.slice(1) + " #" + id;
        document.getElementById('modalTypeDisplay').innerText = type;
    }

    // Inputs resetten
    document.getElementById('modalRitId').value = id;
    document.getElementById('modalType').value = type;

    const btnDownload = document.getElementById('btnDownload');
    if (statusDocumentEntity === 'sales_rit_dossier') {
        if (btnDownload) btnDownload.removeAttribute('href');
    } else {
        let qs = 'id=' + encodeURIComponent(String(id));
        if (actieveToken) {
            qs += '&token=' + encodeURIComponent(actieveToken);
        }
        let pdfUrl = 'calculatie/pdf_offerte.php?' + qs;
        if (type === 'bevestiging') pdfUrl = 'calculatie/pdf_bevestiging.php?' + qs;
        if (type === 'factuur') pdfUrl = 'calculatie/pdf_factuur.php?' + qs;
        if (type === 'ritopdracht') pdfUrl = 'calculatie/pdf_ritopdracht.php?' + qs;
        if (btnDownload) btnDownload.href = pdfUrl;
    }

    document.getElementById('statusModal').style.display = 'block';
}

function closeStatus() {
    document.getElementById('statusModal').style.display = 'none';
    actieveRitId = 0;
    actiefType = '';
    actieveToken = '';
    statusDocumentEntity = 'calculatie';
    const pdfMailRow = document.getElementById('statusModalPdfMailRow');
    if (pdfMailRow) {
        pdfMailRow.style.display = 'grid';
    }
}

function saveStatus() {
    const datum = document.getElementById('modalDatum').value;
    updateDatabase(actieveRitId, actiefType, datum, 'opslaan');
}

function verwijderStatus() {
    if(!confirm("Weet je zeker dat je deze status wilt resetten? Het vinkje wordt weer grijs.")) return;
    updateDatabase(actieveRitId, actiefType, null, 'verwijderen');
}

// --- AANGEPAST: DE SLIMME EMAIL KNOP ---
function verstuurEmail() {
    if (statusDocumentEntity === 'sales_rit_dossier') {
        alert('Voor sales-ritten geen automatische calculatie-mail. Gebruik je eigen e-mail of telefoon; leg daarna de datum vast met “Handmatig Opslaan”.');
        return;
    }
    // 1. Chauffeur check bij Ritopdracht
    let chauffeurId = 0;
    let chauffeurNaam = "";
    
    if (actiefType === 'ritopdracht') {
        const select = document.getElementById('modalChauffeur');
        if (select) {
            chauffeurId = select.value;
            chauffeurNaam = select.options[select.selectedIndex].text;
            
            if (chauffeurId == "0") {
                alert("Let op: Je moet wel eerst een chauffeur kiezen om de ritopdracht naar te mailen!");
                return; // Stop het script
            }
            
            if(!confirm("Weet je zeker dat je de ritopdracht direct wilt mailen naar chauffeur: " + chauffeurNaam + "?")) return;
        }
    } else {
        // Bij offerte, bevestiging, factuur
        if(!confirm("Weet je zeker dat je de " + actiefType + " direct wilt mailen naar de klant?")) return;
    }

    // 2. Visuele feedback (Draaiend icoontje)
    const mailBtn = document.querySelector('.action-btn[onclick="verstuurEmail()"]');
    let orgText = "";
    
    if(mailBtn) {
        orgText = mailBtn.innerHTML;
        mailBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:24px; display:block; margin-bottom:5px;"></i> BEZIG...';
        mailBtn.style.pointerEvents = 'none';
    }

    // 3. Data versturen (Nu mét chauffeur_id!)
    fetch('ajax_mail_versturen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            id: actieveRitId, 
            type: actiefType,
            chauffeur_id: chauffeurId // Hier sturen we de geselecteerde chauffeur mee!
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert("✅ Succes: " + data.message);
            // Sluit popup en zet vinkje op groen
            const icon = statusIconElement(actiefType, actieveRitId);
            if(icon) {
                icon.classList.add('active');
                icon.parentElement.title = "Zojuist verzonden";
            }
            closeStatus();
        } else {
            alert("❌ Fout: " + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("Er ging iets mis met de verbinding (controleer je internet).");
    })
    .finally(() => {
        // Herstel de originele knop altijd, ook bij een fout
        if(mailBtn) {
            mailBtn.innerHTML = orgText;
            mailBtn.style.pointerEvents = 'auto';
        }
    });
}

function updateDatabase(id, type, datum, actie) {
    fetch('ajax_status_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            id: id, 
            type: type, 
            datum: datum, 
            actie: actie,
            entity: statusDocumentEntity
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const icon = statusIconElement(type, id);
            if (!icon) {
                alert('Icoon niet gevonden; vernieuw de pagina.');
                closeStatus();
                return;
            }
            if(actie === 'opslaan') {
                icon.classList.add('active'); 
                icon.parentElement.title = "Verzonden: " + datum;
            } else {
                icon.classList.remove('active'); 
                icon.parentElement.title = "";
            }
            closeStatus();
        } else {
            alert("Fout bij opslaan: " + (data.message || 'Onbekende fout'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("Er ging iets mis met de verbinding.");
    });
}

window.onclick = function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target == modal) {
        closeStatus();
    }
}