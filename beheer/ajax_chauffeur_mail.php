<?php
// Bestand: beheer/ajax_chauffeur_mail.php
// Doel: Verstuurt de ritopdracht naar de chauffeur (veilig gescheiden van de klant-mails)

include '../beveiliging.php';
require 'includes/db.php';

header('Content-Type: application/json');

// Haal de data op die het planbord heeft gestuurd
$input = json_decode(file_get_contents('php://input'), true);
$actie = isset($input['actie']) ? $input['actie'] : '';
$rit_id = isset($input['rit_id']) ? intval($input['rit_id']) : 0;

if ($actie !== 'mail_ritopdracht' || $rit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige aanvraag of geen rit ID.']);
    exit;
}

try {
    // 1. Haal alle gegevens van de rit én het e-mailadres van de chauffeur op
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            c.titel,
            k.bedrijfsnaam, k.voornaam AS klant_voornaam, k.achternaam AS klant_achternaam,
            ch.voornaam AS chauf_voornaam, ch.achternaam AS chauf_achternaam, ch.email AS chauf_email,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres,
            (SELECT omschrijving FROM ritregels WHERE rit_id = r.id LIMIT 1) as vaste_rit_naam,
            (SELECT naar_adres FROM ritregels WHERE rit_id = r.id LIMIT 1) as vaste_rit_bestemming
        FROM ritten r
        LEFT JOIN calculaties c ON r.calculatie_id = c.id
        LEFT JOIN klanten k ON c.klant_id = k.id
        LEFT JOIN chauffeurs ch ON r.chauffeur_id = ch.id
        WHERE r.id = ?
    ");
    $stmt->execute([$rit_id]);
    $rit = $stmt->fetch();

    if (!$rit) {
        echo json_encode(['success' => false, 'message' => 'De rit is niet gevonden in de database.']);
        exit;
    }

    // Controleer of we überhaupt een e-mailadres hebben om naartoe te sturen!
    if (empty($rit['chauf_email'])) {
        echo json_encode(['success' => false, 'message' => 'FOUT: Deze chauffeur heeft geen e-mailadres ingevuld in het systeem!']);
        exit;
    }

    // 2. Bepaal de juiste namen en teksten (Vaste rit of Calculatie)
    if (!empty($rit['calculatie_id'])) {
        $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : $rit['klant_voornaam'].' '.$rit['klant_achternaam'];
        $bestemming = $rit['bestemming_adres'];
        $orderNummer = "Order #" . $rit['calculatie_id'] . " (Bus " . $rit['paraplu_volgnummer'] . ")";
    } else {
        $klantNaam = $rit['vaste_rit_naam'];
        $bestemming = $rit['vaste_rit_bestemming'];
        $orderNummer = "Vaste Rit";
    }

    $datum = date('d-m-Y', strtotime($rit['datum_start']));
    $tijd_start = date('H:i', strtotime($rit['datum_start']));
    
    // 3. Bouw de E-mail op (Mooie HTML opmaak)
    $ontvanger = $rit['chauf_email'];
    $onderwerp = "Ritopdracht: " . $datum . " - " . ($bestemming ?? 'Onbekend');
    
    $bericht = "
    <html>
    <head>
      <title>Ritopdracht BusAI</title>
    </head>
    <body style='font-family: Arial, sans-serif; color: #333; background-color: #f4f4f4; padding: 20px;'>
        <div style='background-color: #fff; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
            <h2 style='color: #003366; border-bottom: 2px solid #003366; padding-bottom: 10px;'>Hallo " . htmlspecialchars($rit['chauf_voornaam']) . ",</h2>
            <p>Hier zijn de gegevens voor je geplande rit op <strong>" . $datum . "</strong>.</p>
            
            <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; width: 140px; color: #666;'><strong>Order / Rit:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>" . htmlspecialchars($orderNummer) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Datum:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . $datum . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Starttijd:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #d97706; font-size: 18px; font-weight: bold;'>" . $tijd_start . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Klant:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($klantNaam) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Bestemming:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($bestemming ?? 'Niet opgegeven') . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; color: #666;'><strong>Passagiers:</strong></td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($rit['geschatte_pax'] ?? '?') . " personen</td>
                </tr>
            </table>

            <div style='background-color: #fff3cd; color: #856404; padding: 15px; border-left: 5px solid #ffeeba; margin-top: 25px;'>
                <strong>⚠️ LET OP:</strong><br>
                Deze e-mail is een momentopname. Controleer <strong>altijd</strong> de Chauffeurs App voor de meest actuele tijden en eventuele last-minute wijzigingen!
            </div>
            
            <p style='margin-top: 30px; color: #777; font-size: 12px;'>
                Met vriendelijke groet,<br>
                <strong>Planning BusAI</strong>
            </p>
        </div>
    </body>
    </html>
    ";

    // Headers configureren zodat het als een nette HTML mail aankomt
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: planning@busai.nl" . "\r\n"; // Dit adres is de afzender

    // 4. Verstuur de e-mail!
    if (mail($ontvanger, $onderwerp, $bericht, $headers)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'De mailserver weigert het bericht te verzenden. Controleer de serverinstellingen.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
?>