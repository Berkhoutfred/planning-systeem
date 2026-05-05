<?php
// Bestand: beheer/calculatie/db_fix_extra.php
// Doel: Ontbrekende kolommen voor kilometers en contactpersoon toevoegen

include '../../beveiliging.php';
require '../includes/db.php';

echo "<h1>Database Update Start...</h1>";

function addColumn($pdo, $table, $column, $type) {
    try {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
        echo "<p style='color:green'>✅ Kolom <strong>$column</strong> toegevoegd.</p>";
    } catch (PDOException $e) {
        // Code 42S21 betekent 'Column already exists', dat is prima.
        if ($e->getCode() == '42S21') {
            echo "<p style='color:orange'>Skipped: Kolom <strong>$column</strong> bestond al.</p>";
        } else {
            echo "<p style='color:red'>Fout bij $column: " . $e->getMessage() . "</p>";
        }
    }
}

// 1. Voeg contactpersoon toe (voor de zekerheid)
addColumn($pdo, 'calculaties', 'contact_id', 'INT DEFAULT 0');

// 2. Voeg de fiscale kilometers toe (waar je foutmelding over ging)
addColumn($pdo, 'calculaties', 'km_tussen', 'INT DEFAULT 0');
addColumn($pdo, 'calculaties', 'km_nl', 'INT DEFAULT 0');
addColumn($pdo, 'calculaties', 'km_de', 'INT DEFAULT 0');

// 3. Voeg eventueel ontbrekende totaalvelden toe
addColumn($pdo, 'calculaties', 'totaal_km', 'INT DEFAULT 0');
addColumn($pdo, 'calculaties', 'totaal_uren', 'DECIMAL(10,2) DEFAULT 0.00');

echo "<hr><h2>✅ Klaar! Je kunt dit tabblad sluiten.</h2>";
echo "<p><a href='maken.php'>Ga terug naar Nieuwe Rit</a></p>";
?>