<?php
// Bestand: beheer/calculatie/verwijderen.php

// Beveiliging en Database (we gaan nu 1 of 2 mappen terug)
include '../../beveiliging.php';
require '../includes/db.php'; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Fout: Geen ID opgegeven.");
}

$id = intval($_GET['id']);

try {
    $pdo->beginTransaction();

    // STAP A: Eerst de route-regels wissen
    $stmt1 = $pdo->prepare("DELETE FROM calculatie_regels WHERE calculatie_id = ?");
    $stmt1->execute([$id]);

    // STAP B: Dan de rit zelf wissen
    $stmt2 = $pdo->prepare("DELETE FROM calculaties WHERE id = ?");
    $stmt2->execute([$id]);

    $pdo->commit();

    // STAP C: Terug naar het overzicht
    header("Location: ../calculaties.php?msg=verwijderd");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<h1>Fout bij verwijderen</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}