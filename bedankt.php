<?php
// Bestand: /public_html/bedankt.php
// Doel: Openbare bedankpagina voor klanten na een iDEAL betaling via Mollie
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bedankt voor uw betaling | Berkhout Reizen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .bedankt-container {
            background-color: #ffffff;
            max-width: 500px;
            width: 90%;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 5px solid #003366; /* Berkhout Blauw */
        }
        .icon-box {
            background-color: #d4edda;
            color: #28a745;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 40px;
            margin: 0 auto 20px auto;
        }
        h1 {
            color: #003366; /* Berkhout Blauw */
            margin-bottom: 15px;
            font-size: 24px;
        }
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .btn-home {
            background-color: #ff5e14; /* Berkhout Oranje */
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s ease;
            display: inline-block;
        }
        .btn-home:hover {
            background-color: #e04b0c;
        }
        .logo-bottom {
            margin-top: 30px;
            opacity: 0.8;
            max-width: 150px;
        }
    </style>
</head>
<body>

    <div class="bedankt-container">
        <div class="icon-box">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Betaling Geslaagd!</h1>
        
        <p>
            Hartelijk dank voor uw betaling. We hebben deze in goede orde ontvangen en verwerkt in onze administratie.<br><br>
            Bedankt voor het vertrouwen in <strong>Berkhout Reizen</strong> en we hopen u in de toekomst graag weer van dienst te mogen zijn!
        </p>
        
        <a href="https://www.berkhoutreizen.nl" class="btn-home">Terug naar de website</a>
        
        <br>
        <img src="images/berkhout_logo.png" alt="Berkhout Reizen" class="logo-bottom" onerror="this.style.display='none'">
    </div>

</body>
</html>