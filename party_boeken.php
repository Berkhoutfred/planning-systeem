<?php
// BESTAND: party_boeken.php
// VERSIE: VEILIG (Nacht-Filter + Strakke blauwe/witte styling voor alles)

// ---> HIER IS DE SNOEIHARDE BEVEILIGING <---
$id = $_GET['id'] ?? 0;

if ($id == 3) {
    header("Location: graafschap.php");
    exit; 
}
// ---------------------------------------------

// 1. DATABASE VERBINDING & GEGEVENS OPHALEN
require_once __DIR__ . '/beheer/includes/db.php';

// Event info ophalen
$stmt = $pdo->prepare("SELECT * FROM party_events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) { die("<h3 style='text-align:center; font-family:sans-serif; margin-top:50px;'>⚠️ Geen evenement gevonden. Controleer de link.</h3>"); }

// Locaties ophalen
$stmt = $pdo->prepare("SELECT * FROM party_opstap_locaties WHERE event_id = ? ORDER BY tijd ASC");
$stmt->execute([$id]);
$alle_locaties = $stmt->fetchAll();

// ---> DE SLIMME TIJD-FILTER <---
$normale_haltes = [];
$terug_haltes = [];

foreach($alle_locaties as $loc) {
    // We pakken alleen de uren
    $uur = (int)substr($loc['tijd'], 0, 2);
    
    // Tussen 00:00 en 11:59? Dan is het een terugreis!
    if ($uur >= 0 && $uur < 12) {
        $terug_haltes[] = $loc; 
    } else {
        $normale_haltes[] = $loc; 
    }
}

// BEPAAL DE TEKSTEN
if ($event['reis_type'] == 'enkel_terug') {
    $titel_keuze = "Waar wil je uitstappen?";
    $toon_tijd   = false; 
} else {
    $titel_keuze = "Waar wil je opstappen?";
    $toon_tijd   = true;  
}
?>

<div style="max-width: 500px; margin: 50px auto; font-family: 'Segoe UI', sans-serif; border-top: 8px solid #f97316; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
    
    <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 22px; color: #1f2937; text-transform: uppercase; letter-spacing: 1px;">Touringcar Berkhout</h2>
        
        <p style="margin: 10px 0 5px 0; color: #666; font-size: 14px; font-weight: bold;">
            🎟️ Bustickets voor:
        </p>

        <span style="background: #ffedd5; color: #9a3412; font-size: 13px; font-weight: bold; padding: 6px 15px; border-radius: 99px; display: inline-block; border: 1px solid #fed7aa;">
            🎭 <?php echo htmlspecialchars($event['naam']); ?> (<?php echo date('d-m-Y', strtotime($event['datum'])); ?>)
        </span>
    </div>

    <form action="party_verwerk_dynamisch.php" method="POST" style="display: flex; flex-direction: column; gap: 12px;">
        
        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">

        <label style="font-weight:bold; font-size:12px; color:#555; margin-bottom:-5px;">Jouw gegevens</label>
        
        <input type="text" name="naam" placeholder="Volledige Naam" required style="padding: 12px; border: 2px solid #f3f4f6; border-radius: 10px; font-size: 16px;">
        
        <input type="email" name="email" placeholder="E-mailadres" required style="padding: 12px; border: 2px solid #f3f4f6; border-radius: 10px; font-size: 16px;">
        
        <input type="tel" name="tel" 
               placeholder="06-nummer (bijv. 0612345678)" 
               required 
               pattern="^(\+316|06)[0-9]{8}$"
               title="Vul een geldig 06-nummer in (bijv. 0612345678). Geen spaties of streepjes."
               style="padding: 12px; border: 2px solid #f3f4f6; border-radius: 10px; font-size: 16px;">
        
        <div style="border-top: 1px solid #f3f4f6; padding-top: 15px; margin-top: 5px;">
            <p style="font-size: 14px; font-weight: bold; color: #4b5563; margin-bottom: 10px;">
                <?php echo $titel_keuze; ?>
            </p>
            
            <?php if(count($alle_locaties) == 0): ?>
                <p style="color:red; text-align:center;">Nog geen haltes beschikbaar.</p>
            <?php else: ?>
                
                <?php foreach($normale_haltes as $loc): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f9fafb; padding: 12px; border-radius: 10px; margin-bottom: 8px; border: 1px solid #f3f4f6;">
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight: 500; color:#1f2937;"><?php echo htmlspecialchars($loc['naam']); ?></span>
                        
                        <span style="font-size:12px; color:#6b7280;">
                            <?php if($toon_tijd): ?>
                                Vertrek: <?php echo substr($loc['tijd'], 0, 5); ?> uur - 
                            <?php endif; ?>
                            <strong>€ <?php echo number_format($loc['prijs'], 2, ',', '.'); ?></strong>
                        </span>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <button type="button" onclick="veranderAantal('<?php echo $loc['id']; ?>', -1)" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #e5e7eb; font-size: 18px; font-weight: bold; cursor: pointer; color: #374151; display: flex; align-items: center; justify-content: center;">-</button>
                        
                        <input type="number" 
                               id="ticket_<?php echo $loc['id']; ?>"
                               name="tickets[<?php echo $loc['id']; ?>]" 
                               class="ticket-aantal" 
                               data-prijs="<?php echo $loc['prijs']; ?>" 
                               value="0" min="0" readonly
                               style="width: 30px; padding: 0; text-align: center; border: none; background: transparent; font-weight: bold; font-size: 16px; color: #111; pointer-events: none;">
                        
                        <button type="button" onclick="veranderAantal('<?php echo $loc['id']; ?>', 1)" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #2563eb; font-size: 18px; font-weight: bold; cursor: pointer; color: white; display: flex; align-items: center; justify-content: center;">+</button>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(count($terug_haltes) > 0): ?>
                    <div style="margin-top: 20px; margin-bottom: 10px;">
                        <p style="font-size: 14px; font-weight: bold; color: #4b5563; margin: 0;">
                            Alleen terugreis:
                        </p>
                    </div>

                    <?php foreach($terug_haltes as $loc): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f9fafb; padding: 12px; border-radius: 10px; margin-bottom: 8px; border: 1px solid #f3f4f6;">
                        <div style="display:flex; flex-direction:column;">
                            <span style="font-weight: 500; color:#1f2937;"><?php echo htmlspecialchars($loc['naam']); ?></span>
                            
                            <span style="font-size:12px; color:#6b7280;">
                                <?php if($toon_tijd): ?>
                                    Vertrek: <?php echo substr($loc['tijd'], 0, 5); ?> uur - 
                                <?php endif; ?>
                                <strong>€ <?php echo number_format($loc['prijs'], 2, ',', '.'); ?></strong>
                            </span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <button type="button" onclick="veranderAantal('<?php echo $loc['id']; ?>', -1)" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #e5e7eb; font-size: 18px; font-weight: bold; cursor: pointer; color: #374151; display: flex; align-items: center; justify-content: center;">-</button>
                            
                            <input type="number" 
                                   id="ticket_<?php echo $loc['id']; ?>"
                                   name="tickets[<?php echo $loc['id']; ?>]" 
                                   class="ticket-aantal" 
                                   data-prijs="<?php echo $loc['prijs']; ?>" 
                                   value="0" min="0" readonly
                                   style="width: 30px; padding: 0; text-align: center; border: none; background: transparent; font-weight: bold; font-size: 16px; color: #111; pointer-events: none;">
                            
                            <button type="button" onclick="veranderAantal('<?php echo $loc['id']; ?>', 1)" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #2563eb; font-size: 18px; font-weight: bold; cursor: pointer; color: white; display: flex; align-items: center; justify-content: center;">+</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <div style="background: #eff6ff; padding: 15px; border-radius: 10px; text-align: center; margin-top: 5px;">
            <span style="color: #1e40af; font-size: 14px; font-weight: 500;">Totaalbedrag:</span><br>
            <strong style="color: #1e3a8a; font-size: 24px;">€ <span id="totaal">0,00</span></strong>
        </div>

        <label style="font-size: 12px; color: #6b7280; display: flex; gap: 10px; align-items: flex-start; cursor: pointer; margin-top: 5px;">
            <input type="checkbox" required style="margin-top: 3px;"> 
            <span>Ik ga akkoord met de algemene voorwaarden en verwerking van gegevens.</span>
        </label>

        <button type="submit" style="background: #2563eb; color: white; padding: 16px; border: none; border-radius: 12px; font-weight: bold; font-size: 16px; cursor: pointer; transition: background 0.2s; margin-top: 5px;">
            Tickets Bestellen
        </button>
    </form>
</div>

<script>
    function veranderAantal(id, verandering) {
        const input = document.getElementById('ticket_' + id);
        let huidigAantal = parseInt(input.value) || 0;
        let nieuwAantal = huidigAantal + verandering;

        if (nieuwAantal < 0) {
            nieuwAantal = 0;
        }

        input.value = nieuwAantal;
        reken(); 
    }

    function reken() {
        let totaal = 0;
        const inputs = document.querySelectorAll('.ticket-aantal');
        
        inputs.forEach(input => {
            const aantal = parseInt(input.value) || 0; 
            const prijs = parseFloat(input.getAttribute('data-prijs')); 
            totaal += aantal * prijs;
        });

        document.getElementById('totaal').innerText = totaal.toFixed(2).replace('.', ',');
    }
</script>