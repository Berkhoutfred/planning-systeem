<?php
session_start();

// HIER HET CHAUFFEURS WACHTWOORD:
$wachtwoord = "bus123"; 

// 1. Inloggen
if (isset($_POST['bus_wachtwoord'])) {
    if ($_POST['bus_wachtwoord'] === $wachtwoord) {
        $_SESSION['chauffeur_ingelogd'] = true;
    } else {
        $fout = "Fout wachtwoord!";
    }
}

// 2. Check: Is de chauffeur ingelogd?
if (!isset($_SESSION['chauffeur_ingelogd']) || $_SESSION['chauffeur_ingelogd'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chauffeur Inlog</title>
    </head>
    <body style="background:#333; font-family:sans-serif; color:white; display:flex; justify-content:center; align-items:center; height:100vh; margin:0;">
        
        <div style="text-align:center; padding:20px;">
            <h1>🚌 Bus App</h1>
            <p>Code invoeren a.u.b.</p>
            
            <form method="post">
                <input type="tel" name="bus_wachtwoord" placeholder="Code..." style="padding:15px; font-size:18px; border-radius:5px; border:none; text-align:center; width:150px;" required>
                <br><br>
                <button type="submit" style="padding:15px 30px; font-size:18px; background:#facc15; border:none; border-radius:5px; cursor:pointer; font-weight:bold; color:black;">Starten</button>
            </form>
            
            <?php if(isset($fout)) echo "<p style='color:red; margin-top:10px;'>$fout</p>"; ?>
        </div>

    </body>
    </html>
<?php
    exit; // STOP! Laat de rit-informatie eronder NIET zien.
}
?>