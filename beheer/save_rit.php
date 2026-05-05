<?php
include '../beveiliging.php';
// We mogen van overal benaderd worden (CORS), handig voor je app
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require 'includes/db.php';

// 1. Ontvang de JSON data van de App
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Even checken of er wel data is
if (empty($data)) {
    http_response_code(400);
    echo json_encode(["message" => "Geen gegevens ontvangen"]);
    exit;
}

try {
    // We starten een 'transactie'. Dat betekent: alles moet goed gaan, of we doen niks.
    // Zo krijg je geen halve ritten in het systeem.
    $pdo->beginTransaction();

    // 2. Bepaal de opmerkingen (Taxi of Bus?)
    $opmerkingen = "";
    if (!empty($data['taxiBijzonderheden'])) {
        $opmerkingen = $data['taxiBijzonderheden'];
    } elseif (!empty($data['busBijzonderheden'])) {
        $opmerkingen = $data['busBijzonderheden'];
    }

    // 3. Sla de HOOFDGEGEVENS op (De Moeder) in 'ritgegevens'
    $stmt = $pdo->prepare("INSERT INTO ritgegevens (chauffeur_naam, voertuig_nummer, datum, type_dienst, opmerkingen, totaal_km, status) VALUES (?, ?, ?, ?, ?, ?, 'nieuw')");
    
    $stmt->execute([
        $data['chauffeur'],
        $data['voertuig'],
        $data['datum'],
        $data['type'],
        $opmerkingen,
        intval($data['totaalKm']) // Zorg dat het een getal is
    ]);

    // Pak het ID van de rit die we net hebben gemaakt
    $rit_id = $pdo->lastInsertId();

    // 4. Sla de REGELS op (De Kinderen) in 'ritregels'
    
    // A. Is het een TAXI rit?
    if (!empty($data['taxiRitten'])) {
        $stmtRegel = $pdo->prepare("INSERT INTO ritregels (rit_id, tijd, van_adres, naar_adres, bedrag, betaalwijze, klant_naam, omschrijving) VALUES (?, ?, ?, ?, ?, ?, ?, 'Taxirit')");
        
        foreach ($data['taxiRitten'] as $rit) {
            $stmtRegel->execute([
                $rit_id,
                $rit['tijd'],
                $rit['van'],
                $rit['naar'],
                $rit['bedrag'],
                $rit['betaal'],
                $rit['klant'] ?? '' // Als klant leeg is, vul leeg in
            ]);
        }
    }

    // B. Is het een BUS rit?
    if (!empty($data['busRitten'])) {
        $stmtRegel = $pdo->prepare("INSERT INTO ritregels (rit_id, tijd, omschrijving, km_stand) VALUES (?, ?, ?, ?)");
        
        foreach ($data['busRitten'] as $rit) {
            $stmtRegel->execute([
                $rit_id,
                $rit['tijd'],
                $rit['label'], // Bijv: "Vertrek garage"
                intval($rit['km'])
            ]);
        }
    }

    // Alles is gelukt! Zet het definitief in de database.
    $pdo->commit();

    // Stuur een berichtje terug naar de App
    echo json_encode(["message" => "Rit succesvol opgeslagen!", "id" => $rit_id]);

} catch (Exception $e) {
    // Oeps, foutje. Draai alles terug.
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["message" => "Fout bij opslaan: " . $e->getMessage()]);
}
?>