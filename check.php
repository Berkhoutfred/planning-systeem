<?php
// check.php - Even spieken hoe de kolommen heten
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'beveiliging.php';

$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', ''); 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    
    // Vraag de database welke kolommen er zijn
    $stmt = $pdo->query("DESCRIBE party_events");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Kolommen in de tabel 'party_events':</h1>";
    echo "<ul>";
    foreach($columns as $col) {
        echo "<li style='font-size:20px; margin-bottom:10px;'>🔹 <strong>" . $col['Field'] . "</strong></li>";
    }
    echo "</ul>";

} catch(PDOException $e) {
    echo "Fout: " . $e->getMessage();
}
?>