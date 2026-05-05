<?php
include '../beveiliging.php';
// Zet foutmeldingen aan voor de zekerheid
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Toegangspoort</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .knop {
            background-color: #003366;
            color: white;
            padding: 20px 40px;
            text-decoration: none;
            font-size: 24px;
            border-radius: 8px;
            display: inline-block;
        }
        .knop:hover { background-color: #0055aa; }
    </style>
</head>
<body>
    <h1>Welkom terug</h1>
    <p>Klik op de knop om naar je systeem te gaan:</p>
    
    <br><br>
    
    <a href="dashboard.php" class="knop">Ga naar Dashboard &rarr;</a>
    
    <br><br><br>
    
    <small>Als deze knop niet werkt, is 'dashboard.php' verdwenen.</small>
</body>
</html>