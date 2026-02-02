// pdf.js - Offerte Generator
const pdf = {
    openModal: function(id) {
        state.app.activeRitId = id;
        const r = state.db.ritten.find(x => x.id == id);
        const k = state.db.klanten.find(x => x.id == r.klantId);
        const today = new Date().toLocaleDateString('nl-NL');

        // Gegevens prepareren
        const prijsRaw = parseFloat(r.prijs.replace(/[^0-9,.-]+/g,"").replace(',','.'));
        const prijsExcl = prijsRaw / 1.09;
        const btw = prijsRaw - prijsExcl;

        let attn = "T.a.v. Contactpersoon";
        if(r.details.contactIdx && k.contactpersonen[r.details.contactIdx]) {
            attn = "T.a.v. " + k.contactpersonen[r.details.contactIdx].naam;
        }

        const t = (key) => r.details.tijden[key] ? r.details.tijden[key].replace(':',':') + ' uur' : '--:--';
        const busAantal = r.details.bussen.length || 1;

        [cite_start]// HTML Layout (Precies jouw voorbeeld [cite: 1-40])
        const html = `
            <div style="display:flex; justify-content:space-between; margin-bottom:30px;">
                <div>
                    <div class="pdf-logo">Berkhout Busreizen</div>
                    <div style="font-size:12px;">
                        Industrieweg 95A<br>7202 CA Zutphen<br>
                        0575-525345<br>info@taxiberkhout.nl<br>www.berkhoutreizen.nl
                    </div>
                </div>
                <div style="text-align:right; font-size:12px;">
                    <div style="font-size:14px; font-weight:bold; color:#004a99; font-style:italic; margin-bottom:10px;">Snel, veilig & comfortabel</div>
                    Zutphen, ${today}<br>
                    <strong>Betreft:</strong> uw offerte
                </div>
            </div>

            <div style="font-size:14px; font-weight:bold; margin-bottom:20px;">
                ${k.naam}<br>
                ${attn}<br>
                ${k.adres}<br>
                ${k.plaats}
            </div>

            <div style="margin-bottom:20px;">
                Geachte ${k.contactpersonen[r.details.contactIdx] ? k.contactpersonen[r.details.contactIdx].naam : 'relatie'},<br><br>
                Wij danken u voor uw aanvraag en hebben hierbij het genoegen u een vrijblijvende offerte, op basis van beschikbaarheid, te doen toekomen.
                Hieronder volgt het programma zoals is besproken en de kosten hiervoor.
            </div>

            <h3 style="border-bottom:1px solid #000;">Programma</h3>
            <table class="pdf-table">
                <tr><td class="pdf-label">Vertrekdatum</td><td>${new Date(r.datum).toLocaleDateString('nl-NL')}</td></tr>
                <tr><td class="pdf-label">Einddatum</td><td>${new Date(r.details.datum_eind).toLocaleDateString('nl-NL')}</td></tr>
                <tr><td class="pdf-label">Aantal touringcars</td><td>${busAantal}</td></tr>
                <tr><td class="pdf-label">Aantal passagiers</td><td>${r.details.pax}</td></tr>
            </table>

            <table class="pdf-table">
                <tr><td class="pdf-label">Vertrekadres</td><td>${r.details.route.start}</td></tr>
                <tr><td class="pdf-label">Vertrekplaats</td><td>Zutphen</td></tr>
                <tr><td class="pdf-label">Voorstaan</td><td>${t('std_2')}</td></tr>
                <tr><td class="pdf-label">Vertrektijd</td><td>${t('std_1')}</td></tr>
                <tr><td colspan="2" style="height:5px"></td></tr>
                <tr><td class="pdf-label">Bestemming</td><td>${r.dest}</td></tr>
                <tr><td class="pdf-label">Adres</td><td>${r.details.route.eind}</td></tr>
                <tr><td class="pdf-label">Aankomsttijd</td><td>${t('std_4')}</td></tr>
                <tr><td colspan="2" style="height:5px"></td></tr>
                <tr><td class="pdf-label">Vertrektijd retour</td><td>${t('std_5')}</td></tr>
                <tr><td class="pdf-label">Aankomsttijd retour</td><td>${t('std_7')}</td></tr>
            </table>

            <h3 style="border-bottom:1px solid #000;">Financieel Overzicht</h3>
            <table class="money-table">
                <tr><th>Beschrijving</th><th>Bedrag</th></tr>
                <tr><td>Vervoerskosten (excl. BTW)</td><td>€ ${prijsExcl.toLocaleString('nl-NL',{minimumFractionDigits:2})}</td></tr>
                <tr><td>BTW (9%)</td><td>€ ${btw.toLocaleString('nl-NL',{minimumFractionDigits:2})}</td></tr>
                <tr><td class="money-total">Totaalprijs incl. BTW:</td><td class="money-total">€ ${prijsRaw.toLocaleString('nl-NL',{minimumFractionDigits:2})}</td></tr>
            </table>

            <div class="pdf-footer">
                Indien van het bovenstaande programma wordt afgeweken, kan er een prijsaanpassing volgen.
                De aanbieding is exclusief eventuele parkeer-, tol- en/of verblijfskosten. Wij behouden ons het recht voor onze reissommen te wijzigen, indien daartoe aanleiding bestaat door prijs en/of brandstofverhogingen door derden.<br><br>
                Wij vertrouwen erop u met deze offerte een passende aanbieding te hebben gedaan en zien uw reactie gaarne tegemoet.<br><br>
                Met vriendelijke groeten,<br><strong>Berkhout Reizen</strong><br>Fred Stravers
            </div>
        `;

        document.getElementById('pdf-content').innerHTML = html;
        document.getElementById('offerte-modal').classList.remove('hidden');
    },

    generateAndMail: function() {
        const el = document.getElementById('pdf-content');
        const opt = {
            margin: 0,
            filename: 'Offerte_Berkhout.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(el).save().then(() => {
            app.toggleStatus(state.app.activeRitId, 'offerte');
            document.getElementById('offerte-modal').classList.add('hidden');
            
            // Mail link
            const r = state.db.ritten.find(x => x.id == state.app.activeRitId);
            const k = state.db.klanten.find(x => x.id == r.klantId);
            const email = k.contactpersonen[0] ? k.contactpersonen[0].email : '';
            window.location.href = `mailto:${email}?subject=Offerte Berkhout&body=Zie bijlage.`;
        });
    }
};
