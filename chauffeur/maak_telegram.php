<?php
// Bestand: chauffeur/maak_telegram.php
require '../beheer/includes/db.php';

try {
    // We voegen de kolom 'telegram_chat_id' toe aan de tabel 'chauffeurs'
    $sql = "ALTER TABLE chauffeurs ADD telegram_chat_id VARCHAR(50) NULL";
    $pdo->exec($sql);
    
    echo "<h2 style='color: green;'>✅ Gelukt! Het Telegram-vakje is succesvol toegevoegd aan de database.</h2>";
    echo "<p>Je kunt dit bestandje (maak_telegram.php) nu weer uit de map 'chauffeur' verwijderen om het netjes te houden.</p>";
} catch (PDOException $e) {
    echo "<h2 style='color: orange;'>⚠️ Let op: </h2>";
    echo "<p>Het systeem zegt: " . $e->getMessage() . "</p>";
    echo "<p>Als er staat 'Duplicate column name', dan bestond het vakje al en zijn we ook klaar!</p>";
}
?>