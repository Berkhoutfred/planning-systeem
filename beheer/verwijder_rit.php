<?php
// Bestand: beheer/verwijder_rit.php
// Let op: Dit bestand staat nu direct in de map 'beheer'

include '../beveiliging.php';
require 'includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Fout: Geen ID opgegeven.");
}

$id = intval($_GET['id']);

try {
    // STAP A: Eerst de route-regels verwijderen
    $stmt = $pdo->prepare("DELETE FROM calculatie_regels WHERE calculatie_id = ?");
    $stmt->execute([$id]);

    // STAP B: De rit zelf verwijderen
    $stmt = $pdo->prepare("DELETE FROM calculaties WHERE id = ?");
    $stmt->execute([$id]);

    // STAP C: Terug naar het overzicht (zit in dezelfde map)
    header("Location: calculaties.php?msg=verwijderd");
    exit;

} catch (PDOException $e) {
    echo "<h1>Er ging iets mis</h1>";
    echo "<p>Foutmelding: " . $e->getMessage() . "</p>";
    echo "<br><a href='calculaties.php'>Terug naar overzicht</a>";
}
?>