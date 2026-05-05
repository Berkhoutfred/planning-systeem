<?php
// Bestand: beheer/ajax_get_afdelingen.php
include '../beveiliging.php';
require 'includes/db.php';

if (isset($_GET['klant_id'])) {
    $klant_id = (int)$_GET['klant_id'];
    $stmt = $pdo->prepare("SELECT id, naam FROM klant_afdelingen WHERE klant_id = ? ORDER BY naam ASC");
    $stmt->execute([$klant_id]);
    $afdelingen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($afdelingen);
}
?>