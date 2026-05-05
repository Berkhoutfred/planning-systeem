<?php
// Testdata
$bussen = [17, 18, 19, 23, 50, 55, 60, 62];
$chauffeurs = ['Kies...', 'Jan', 'Piet', 'Klaas', 'Anna'];

// Testdata aangepast rond de tijd van nu (ca. 20:15) om het live-effect te zien
$dummy_ritten = [
    [
        'id' => 1, 'datum' => 'Donderdag 19-03-2026', 'aanvang' => '16:00', 
        'van' => 'Amsterdam', 'naar' => 'Utrecht', 'klant' => 'Klant A', 
        'status' => 'Voltooid', 'chauffeur_gekozen' => 'Anna', 'bus_gekozen' => 17
    ],
    [
        'id' => 2, 'datum' => 'Donderdag 19-03-2026', 'aanvang' => '19:45', 
        'van' => 'Rotterdam', 'naar' => 'Schiphol', 'klant' => 'Klant B', 
        'status' => 'Onderweg', 'chauffeur_gekozen' => 'Piet', 'bus_gekozen' => 18
    ],
    [
        'id' => 3, 'datum' => 'Donderdag 19-03-2026', 'aanvang' => '20:10', 
        'van' => 'Zutphen', 'naar' => 'Apeldoorn', 'klant' => 'Klant C', 
        'status' => 'Onderweg', 'chauffeur_gekozen' => 'Jan', 'bus_gekozen' => 50
    ],
    [
        'id' => 4, 'datum' => 'Donderdag 19-03-2026', 'aanvang' => '22:30', 
        'van' => 'Den Haag', 'naar' => 'Gouda', 'klant' => 'Klant D', 
        'status' => 'Gepland', 'chauffeur_gekozen' => '', 'bus_gekozen' => ''
    ]
];
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Rittenagenda Prototype (Live)</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: middle; }
        th { background-color: #f4f4f4; }
        
        .center { text-align: center; }
        .col-bus { width: 20px; padding: 2px; font-size: 11px; } 
        .col-smal { white-space: nowrap; } 

        .filter-balk {
            background-color: #e9ecef; padding: 15px; margin-bottom: 20px;
            border-radius: 5px; display: flex; flex-direction: column; gap: 15px;
            position: sticky; top: 0; z-index: 100; /* Zorgt dat de balk in beeld blijft bij scrollen */
        }
        .filter-rij { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .btn-actie {
            padding: 5px 10px; background-color: #007bff; color: white;
            border: none; border-radius: 3px; cursor: pointer; font-size: 12px;
        }
        .btn-actie:hover { background-color: #0056b3; }
        .bus-filters label { margin-right: 8px; cursor: pointer; }

        /* Nieuwe CSS voor de Live Statussen! */
        .rij-verleden {
            background-color: #f9f9f9;
            color: #999; /* Grijze tekst */
        }
        .rij-verleden td { border-color: #eee; }
        
        .rij-actief {
            background-color: #e6f9e6; /* Lichtgroene achtergrond */
            border-left: 5px solid #28a745 !important; /* Dikke groene rand links */
            font-weight: bold;
        }
        
        /* Een leuk extraatje: een knipperend rood 'live' bolletje voor de actieve rit */
        .live-indicator { display: none; color: red; font-size: 18px; line-height: 1; vertical-align: middle; margin-right: 5px; animation: knipper 1.5s infinite; }
        .rij-actief .live-indicator { display: inline-block; }
        @keyframes knipper { 0% { opacity: 1; } 50% { opacity: 0.2; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <h2>Rittenagenda Beheer (Live Dashboard)</h2>

    <div class="filter-balk">
        <div class="filter-rij">
            <strong>Periode:</strong>
            <label for="datum_exact">Exacte datum:</label>
            <input type="date" id="datum_exact" name="datum_exact" value="<?= date('Y-m-d') ?>">
            <button class="btn-actie" id="btn_nu_scrollen" style="background-color: #dc3545;" type="button">Scroll naar NU</button>
        </div>
        
        <div class="filter-rij">
            <strong>Toon Bussen:</strong>
            <button class="btn-actie" id="toggle_alle_bussen" type="button">Alle aan/uit</button>
            <div class="bus-filters">
                <?php foreach($bussen as $bus): ?>
                    <label><input type="checkbox" class="bus-vinkje" value="<?= $bus ?>" checked> <?= $bus ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-smal">Datum</th>
                <th class="col-smal">Aanvang</th>
                <th>Van</th>
                <th>Naar</th>
                <th>Klant</th>
                <th>Status</th>
                <th>Chauffeur</th>
                <?php foreach($bussen as $bus): ?>
                    <th class="center col-bus bus-kolom-<?= $bus ?>"><?= $bus ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($dummy_ritten as $rit): ?>
                <tr class="rit-rij" data-bus="<?= $rit['bus_gekozen'] ?>" data-tijd="<?= $rit['aanvang'] ?>">
                    <td class="col-smal"><?= $rit['datum'] ?></td>
                    <td class="col-smal">
                        <span class="live-indicator">•</span><?= $rit['aanvang'] ?>
                    </td>
                    <td><?= $rit['van'] ?></td>
                    <td><?= $rit['naar'] ?></td>
                    <td><?= $rit['klant'] ?></td>
                    <td><?= $rit['status'] ?></td>
                    
                    <td>
                        <select name="chauffeur_rit_<?= $rit['id'] ?>">
                            <?php foreach($chauffeurs as $chauffeur): ?>
                                <?php $selected = ($rit['chauffeur_gekozen'] == $chauffeur) ? 'selected' : ''; ?>
                                <option value="<?= $chauffeur ?>" <?= $selected ?>><?= $chauffeur ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <?php foreach($bussen as $bus): ?>
                        <td class="center col-bus bus-kolom-<?= $bus ?>">
                            <?php $checked = ($rit['bus_gekozen'] == $bus) ? 'checked' : ''; ?>
                            <input type="radio" class="bus-radio" name="bus_rit_<?= $rit['id'] ?>" value="<?= $bus ?>" <?= $checked ?> style="margin:0;">
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        // --- 1. Live Tijd Logica (De Klok) ---
        function updateLiveTijden() {
            const nu = new Date();
            // Maak er een makkelijk vergelijkbaar getal van, bijv. 20:15 wordt 20.25 (20 + 15/60)
            const huidigeTijdDecimaal = nu.getHours() + (nu.getMinutes() / 60);

            const rijen = document.querySelectorAll('.rit-rij');
            
            rijen.forEach(rij => {
                const tijdString = rij.getAttribute('data-tijd'); // bijv "20:10"
                if(!tijdString) return;

                const delen = tijdString.split(':');
                const ritTijdDecimaal = parseInt(delen[0]) + (parseInt(delen[1]) / 60);

                // Verwijder eerst oude classes
                rij.classList.remove('rij-verleden', 'rij-actief');

                // Logica:
                // Als de rit meer dan 1,5 uur (1.5) in het verleden ligt: verleden
                // Als de rit in de afgelopen 1,5 uur is gestart, of in de komende 15 minuut start: Actief!
                if (ritTijdDecimaal < (huidigeTijdDecimaal - 1.5)) {
                    rij.classList.add('rij-verleden');
                } else if (ritTijdDecimaal >= (huidigeTijdDecimaal - 1.5) && ritTijdDecimaal <= (huidigeTijdDecimaal + 0.25)) {
                    rij.classList.add('rij-actief');
                }
            });
        }

        // Voer de klok direct 1x uit, en daarna elke minuut (60000 milliseconden)
        updateLiveTijden();
        setInterval(updateLiveTijden, 60000);

        // --- 2. Scroll naar NU knop ---
        document.getElementById('btn_nu_scrollen').addEventListener('click', function() {
            const actieveRij = document.querySelector('.rij-actief');
            if(actieveRij) {
                // Scroll zachtjes naar de rit die nu bezig is
                actieveRij.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // --- 3. Bus Filter Logica (Gekopieerd van vorige stap) ---
        const busVinkjes = document.querySelectorAll('.bus-vinkje');
        const knopAlleBussen = document.getElementById('toggle_alle_bussen');
        const rittenLijst = document.querySelectorAll('.rit-rij');

        function updateTabel() {
            const actieveBussen = Array.from(busVinkjes).filter(v => v.checked).map(v => v.value);
            busVinkjes.forEach(vinkje => {
                const kolommen = document.querySelectorAll('.bus-kolom-' + vinkje.value);
                kolommen.forEach(cel => { cel.style.display = vinkje.checked ? '' : 'none'; });
            });
            rittenLijst.forEach(rij => {
                const ritBus = rij.getAttribute('data-bus');
                if (ritBus === '' || actieveBussen.includes(ritBus)) {
                    rij.style.display = '';
                } else {
                    rij.style.display = 'none';
                }
            });
        }

        busVinkjes.forEach(vinkje => vinkje.addEventListener('change', updateTabel));
        knopAlleBussen.addEventListener('click', function() {
            let allesAan = Array.from(busVinkjes).every(v => v.checked);
            busVinkjes.forEach(vinkje => vinkje.checked = !allesAan);
            updateTabel();
        });
        document.querySelectorAll('.bus-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                this.closest('tr').setAttribute('data-bus', this.value);
            });
        });
    </script>

</body>
</html>