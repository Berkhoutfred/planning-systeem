<?php
// Bestand: beheer/toggle_status.php
require 'includes/db.php';

if (isset($_GET['id']) && isset($_GET['col'])) {
    $id = intval($_GET['id']);
    $col = $_GET['col'];

    // Beveiliging: alleen toegestane kolommen mogen gewijzigd worden
    $toegestaan = ['status_offerte', 'status_bevestiging', 'status_ritopdracht', 'status_factuur', 'status_betaald'];
    
    if (in_array($col, $toegestaan)) {
        // 1. Haal huidige status op
        $stmt = $pdo->prepare("SELECT $col FROM calculaties WHERE id = ?");
        $stmt->execute([$id]);
        $huidig = $stmt->fetchColumn();

        // 2. Draai om (0 wordt 1, 1 wordt 0)
        $nieuw = ($huidig == 1) ? 0 : 1;

        // 3. Opslaan
        $update = $pdo->prepare("UPDATE calculaties SET $col = ? WHERE id = ?");
        $update->execute([$nieuw, $id]);
    }
}

// Stuur direct terug naar het dashboard
header("Location: index.php");
exit;
?>