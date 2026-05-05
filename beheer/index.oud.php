<?php
// Bestand: beheer/index.php
// Versie: DASHBOARD 2.3 - Precies 3 knoppen zoals afgesproken

include '../beveiliging.php';
require 'includes/db.php';
include 'includes/header.php';
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1400px; margin: auto; padding: 20px; }
    
    /* DASHBOARD GRID - Precies 3 kolommen */
    .dash-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
    
    .dash-card { position: relative; background: #fff; padding: 30px 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; transition: transform 0.2s, box-shadow 0.2s; border-bottom: 4px solid transparent; }
    .dash-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
    
    .dash-icon { font-size: 40px; color: #003366; margin-bottom: 15px; }
    .dash-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; }
    .dash-link { color: #555; text-decoration: none; font-weight: 600; font-size: 14px; }
    .dash-link:hover { color: #003366; }

    /* Maakt de hele kaart een link */
    .dash-link::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; }

    /* SPECIFIEKE KLEUREN */
    .card-nieuw { border-bottom-color: #28a745; }
    .card-calc { border-bottom-color: #17a2b8; }
    .card-planbord { border-bottom-color: #007bff; }

    /* ALERTS SECTIE */
    .alert-section { margin-top: 40px; }
    .alert-header { background: #003366; color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
    .alert-container { background: #fff; padding: 20px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 150px; }
    
    .empty-state { text-align: center; color: #999; font-style: italic; margin-top: 40px; }
</style>

<div class="container">
    <h1 style="color:#333; margin-bottom: 30px;">Dashboard</h1>

    <div class="dash-grid">
        <div class="dash-card card-nieuw">
            <div class="dash-icon" style="color:#28a745;"><i class="fas fa-plus-circle"></i></div>
            <div class="dash-title">Nieuwe Rit</div>
            <a href="calculatie/maken.php" class="dash-link">Maken &rarr;</a>
        </div>

        <div class="dash-card card-calc">
            <div class="dash-icon" style="color:#17a2b8;"><i class="fas fa-file-invoice"></i></div>
            <div class="dash-title">Offerte & Sales</div>
            <a href="calculaties.php" class="dash-link">Alle offertes &rarr;</a>
        </div>

        <div class="dash-card card-planbord">
            <div class="dash-icon" style="color:#007bff;"><i class="fas fa-map-marked-alt"></i></div>
            <div class="dash-title">Live Planbord</div>
            <a href="live_planbord.php" class="dash-link">Planning openen &rarr;</a>
        </div>
    </div>

    <div class="alert-section">
        <div class="alert-header">
            <span><i class="fas fa-bell"></i> MELDINGEN & ONDERHOUD</span>
            <span style="background: #dc3545; font-size: 12px; padding: 2px 8px; border-radius: 10px;">BETA</span>
        </div>
        <div class="alert-container">
            <div class="empty-state">
                <i class="fas fa-check-circle" style="font-size: 40px; color: #28a745; margin-bottom: 15px;"></i><br>
                Geen dringende meldingen op dit moment.<br>
                (Dit blok wordt later gekoppeld aan de voertuig- en chauffeursdatabase)
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>