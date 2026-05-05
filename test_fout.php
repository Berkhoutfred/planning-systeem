<?php
// test_fout.php
// Laat alle foutmeldingen zien zodat we weten wat er mist

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Foutenzoeker</h1>";

// 1. Controleren of beveiliging.php bestaat
if (file_exists('beveiliging.php')) {
    echo "✅ Het bestand <b>beveiliging.php</b> is gevonden.<br>";
    
    // Proberen te laden
    try {
        include 'beveiliging.php';
        echo "✅ Het bestand <b>beveiliging.php</b> is succesvol geladen.<br>";
    } catch (Exception $e) {
        echo "❌ FOUT in beveiliging.php: " . $e->getMessage() . "<br>";
    }

} else {
    echo "❌ <h2 style='color:red'>FOUT: Het bestand 'beveiliging.php' is verdwenen!</h2>";
    echo "Dit is de reden dat je pagina's niet werken. We moeten dit bestand opnieuw maken.<br>";
}

// 2. Controleren of database werkt
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "✅ Database verbinding werkt prima.<br>";
} catch(PDOException $e) {
    echo "❌ Database fout: " . $e->getMessage() . "<br>";
}

echo "<hr>Klaar met testen.";
?>