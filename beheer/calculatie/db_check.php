<?php
// Bestand: beheer/calculatie/db_check.php
// Doel: Controleren of de database klaar is voor het nieuwe dashboard

include '../../beveiliging.php';
require '../includes/db.php';

echo "<h1>Database Diagnose</h1>";

// 1. Haal alle kolommen op van de tabel 'calculaties'
try {
    $stmt = $pdo->query("DESCRIBE calculaties");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tabel: calculaties</h3>";
    echo "<ul>";
    
    // De kolommen die we zoeken
    $nodig = [
        'datum_offerte_verstuurd',
        'datum_bevestiging_verstuurd',
        'datum_ritopdracht_verstuurd',
        'datum_factuur_verstuurd',
        'is_betaald'
    ];
    
    $missend = [];
    
    foreach ($nodig as $n) {
        if (in_array($n, $columns)) {
            echo "<li style='color:green;'>✅ Kolom <strong>$n</strong> bestaat.</li>";
        } else {
            echo "<li style='color:red;'>❌ Kolom <strong>$n</strong> ontbreekt!</li>";
            $missend[] = $n;
        }
    }
    echo "</ul>";
    
    if (count($missend) > 0) {
        echo "<div style='background:#ffeebb; padding:15px; border:1px solid #ffcc00;'>";
        echo "<strong>Conclusie:</strong> We moeten de database updaten voordat we verder kunnen.<br>";
        echo "Geef dit door aan de developer.";
        echo "</div>";
    } else {
        echo "<div style='background:#d4edda; padding:15px; border:1px solid #c3e6cb; color:#155724;'>";
        echo "<strong>Conclusie:</strong> Alles is groen! We kunnen direct door met bouwen.";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "Fout bij uitlezen database: " . $e->getMessage();
}
?>