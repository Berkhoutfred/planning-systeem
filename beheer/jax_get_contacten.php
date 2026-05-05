<?php
// Bestand: beheer/ajax_get_contacten.php
// Doel: Haalt razendsnel de contactpersonen van één specifieke klant op.

require 'includes/db.php';

$klant_id = isset($_GET['klant_id']) ? (int)$_GET['klant_id'] : 0;

if ($klant_id > 0) {
    try {
        // Zoek alle contactpersonen die bij deze klant_id horen
        $stmt = $pdo->prepare("SELECT id, voornaam, achternaam FROM klant_contacten WHERE klant_id = ? ORDER BY voornaam ASC");
        $stmt->execute([$klant_id]);
        $contacten = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Stuur ze netjes terug naar de calculatie
        echo json_encode($contacten);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>