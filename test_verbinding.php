<?php
require_once __DIR__ . '/env.php';
$host = env_value('LEGACY_DB_HOST', '127.0.0.1');
$db   = env_value('LEGACY_DB_NAME', '');
$user = env_value('LEGACY_DB_USER', '');
$pass = env_value('LEGACY_DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "<h1>✅ HIER IS DE TEST: Verbinding is gelukt!</h1>";
} catch(PDOException $e) {
    echo "<h1>❌ FOUT:</h1> " . $e->getMessage();
}
?>