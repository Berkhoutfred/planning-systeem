<?php
// Bestand: beheer/ajax_contacts.php
require 'includes/db.php';

if (isset($_GET['klant_id'])) {
    $klant_id = intval($_GET['klant_id']);
    
    // Haal contactpersonen op
    $stmt = $pdo->prepare("SELECT id, naam, functie FROM klant_contactpersonen WHERE klant_id = ? ORDER BY naam ASC");
    $stmt->execute([$klant_id]);
    $contacten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stuur terug als JSON (data die Javascript begrijpt)
    header('Content-Type: application/json');
    echo json_encode($contacten);
}
?>