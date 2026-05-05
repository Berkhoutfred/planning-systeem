<?php
// Bestand: beheer/ajax_get_contacten.php
// Doel: Haalt de contactpersonen op en matcht de kolom 'naam' perfect met het formulier!

header('Content-Type: application/json');
ini_set('display_errors', 0); 

if (!file_exists('includes/db.php')) {
    echo json_encode([["id" => 0, "voornaam" => "⚠️ FOUT:", "achternaam" => "db.php onvindbaar"]]);
    exit;
}

require 'includes/db.php';

$klant_id = isset($_GET['klant_id']) ? (int)$_GET['klant_id'] : 0;

if ($klant_id > 0) {
    try {
        // De Magische Truc: we selecteren 'naam', maar sturen het terug als 'voornaam'
        $stmt = $pdo->prepare("SELECT id, naam AS voornaam, '' AS achternaam FROM klant_contactpersonen WHERE klant_id = ? ORDER BY naam ASC");
        $stmt->execute([$klant_id]);
        $contacten = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($contacten);

    } catch (PDOException $e) {
        echo json_encode([["id" => 0, "voornaam" => "❌ DB FOUT:", "achternaam" => $e->getMessage()]]);
    }
} else {
    echo json_encode([]);
}
?>