<?php
require_once __DIR__ . '/tenant_instellingen_db.php';
if (!isset($pdo) && file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}
// --- DE "BESTANDEN-CHECK" METHODE ---
// Bepalen van het relatieve pad op basis van waar we zijn
if (file_exists('klanten.php')) {
    // We zitten in de hoofdmap (/beheer/)
    $path = ''; 
} else {
    // We zitten in een submap (zoals /calculatie/ of /wagenpark/)
    $path = '../'; 
}

// --- SLIMME MENU HIGHLIGHT LOGICA ---
// Kijk hoe het huidige bestand heet (bijv. 'agenda.php' of 'planbord.php')
$huidige_pagina = basename($_SERVER['PHP_SELF']);

// De stijl voor de actieve knop
$actief_stijl = "background: rgba(255,255,255,0.15); font-weight: bold; border-bottom-color: #4fc3f7;";

// Check of we in een administratie-pagina zitten voor het uitklapmenu
$admin_paginas = ['ritten.php', 'nieuwe_rit.php', 'rit-bekijken.php', 'klanten.php', 'klant-bewerken.php', 'voertuigen.php', 'voertuig-bewerken.php', 'voertuig-toevoegen.php', 'chauffeurs.php', 'chauffeur-bewerken.php', 'chauffeur-toevoegen.php', 'vakantierooster_chauffeur.php', 'chauffeur_vakantie.php', 'uren_controle.php', 'loonadministratie.php', 'overzicht.php', 'dagbesteding.php', 'radeland_mail.php', 'mail_sjablonen.php', 'gebruikers.php'];
$is_admin_actief = in_array($huidige_pagina, $admin_paginas);
$is_platform_owner = function_exists('current_user_role') && current_user_role() === 'platform_owner';
$tenantSwitchCurrent = function_exists('current_tenant_id') ? current_tenant_id() : 0;
$tenantSwitchCsrf = function_exists('auth_get_csrf_token') ? auth_get_csrf_token() : '';
$tenantSwitchRedirect = (string) ($_SERVER['REQUEST_URI'] ?? '/beheer/dashboard.php');
$tenantSwitchOptions = [
    1 => 'Omgeving 1 - Productie',
    2 => 'Omgeving 2 - Test',
];
$tenantLabel = 'BusAI';
if (isset($pdo) && $pdo instanceof PDO && function_exists('current_tenant_id')) {
    $tid = current_tenant_id();
    $tenantLabel = 'BusAI | ' . ($tenantSwitchOptions[$tid] ?? ('Omgeving ' . (string) $tid));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BusAI</title>
    
    <link rel="stylesheet" href="<?php echo $path; ?>style.css?v=<?php echo time(); ?>"> 
    
    <style>
        /* Basis instellingen */
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #f4f7f6; color: #333; }
        
        /* Header Balk */
        header { 
            background: #003366; 
            color: white; 
            padding: 0 14px; 
            height: 52px; 
            display: flex; 
            align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            gap: 8px;
        }
        
        /* Navigatie Menu */
        nav { 
            display: flex; 
            height: 100%; 
            gap: 2px; 
            flex-grow: 1; 
            margin-left: 12px;
            min-width: 0;
            overflow-x: auto;
            overflow-y: hidden;
        }
        nav::-webkit-scrollbar { height: 5px; }
        nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.35); border-radius: 999px; }
        
        nav > a { 
            color: white; 
            text-decoration: none; 
            padding: 0 10px; 
            display: flex; 
            align-items: center; 
            height: 100%; 
            font-size: 13px;
            border-bottom: 3px solid transparent;
            transition: 0.2s;
            white-space: nowrap;
        }
        
        nav > a:hover { 
            background: rgba(255,255,255,0.1); 
            border-bottom-color: #4fc3f7;
        }

        /* Toolbar rechts: buiten <nav> zodat uitklapmenu niet wordt geknipt door nav overflow */
        .header-toolbar {
            display: flex;
            align-items: center;
            height: 100%;
            flex-shrink: 0;
            gap: 6px;
            margin-left: auto;
            position: relative;
        }

        /* --- DROPDOWN MENU STYLING --- */
        .dropdown { position: relative; display: flex; align-items: center; height: 100%; flex-shrink: 0; }
        
        .dropdown-btn { 
            color: white; 
            text-decoration: none; 
            padding: 0 10px; 
            display: flex; 
            align-items: center; 
            height: 100%; 
            font-size: 13px; 
            cursor: pointer; 
            border-bottom: 3px solid transparent; 
            transition: 0.2s; 
            font-weight: bold; 
            background: rgba(255,255,255,0.05); 
            white-space: nowrap;
        }
        
        .dropdown:hover .dropdown-btn { background: rgba(255,255,255,0.1); border-bottom-color: #4fc3f7; }
        
        .dropdown-content { 
            display: none; 
            position: absolute; 
            top: 52px; 
            right: 0; 
            background-color: #fff; 
            min-width: 260px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
            border-radius: 0 0 6px 6px; 
            z-index: 1000; 
            overflow: hidden; 
            border: 1px solid #eee;
            border-top: 3px solid #003366; 
        }
        
        .dropdown:hover .dropdown-content { display: block; }

        /* VERNIEUWDE, DUIDELIJKE TUSSENKOPJES */
        .dropdown-header {
            padding: 14px 18px 8px 18px;
            font-size: 12px;
            text-transform: uppercase;
            color: #003366; /* Berkhout Blauw */
            font-weight: 800; /* Lekker dik gedrukt */
            letter-spacing: 0.5px;
            background-color: #f4f7f6; /* Zacht contrastkleurtje */
            border-bottom: 2px solid #e2e8f0;
            margin-top: 5px;
        }
        
        .dropdown-header:first-child {
            margin-top: 0; /* Geen witruimte boven de allereerste kop */
        }
        
        .dropdown-content a { 
            color: #444; 
            padding: 10px 18px; 
            text-decoration: none; 
            display: block; 
            font-size: 14px;
            border-bottom: 1px solid #f9f9f9; 
            transition: background 0.2s, padding-left 0.2s, color 0.2s; 
        }
        
        .dropdown-content a:last-child { border-bottom: none; }
        .dropdown-content a:hover { background-color: #f1f9ff; color: #003366; font-weight: bold; padding-left: 22px; }

        /* Container en Tabellen STIJL */
        .container { padding: 30px; background: white; max-width: 1200px; margin: 30px auto; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { border-bottom: 1px solid #eee; padding: 12px; text-align: left; }
        th { background: #f8f9fa; color: #003366; font-weight: 600; text-transform: uppercase; }
    </style>
</head>
<body>

<header>
    <h2 style="margin:0; white-space: nowrap; font-size:17px; line-height:1.1;"><?php echo htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
    
    <nav>
        <a href="<?php echo $path; ?>dashboard.php" style="<?php if($huidige_pagina == 'index.php' || $huidige_pagina == 'dashboard.php') echo $actief_stijl; ?>">Dashboard</a>
        
        <a href="<?php echo $path; ?>calculaties.php" style="<?php if($huidige_pagina == 'calculaties.php' || $huidige_pagina == 'calculaties_bewerken.php') echo $actief_stijl; ?>">Offertes & Sales</a>
        
        <a href="<?php echo $path; ?>live_planbord.php" style="<?php if($huidige_pagina == 'live_planbord.php') echo $actief_stijl; ?>">Live Planbord</a>
        
        <a href="<?php echo $path; ?>weekrooster.php" style="<?php if($huidige_pagina == 'weekrooster.php') echo $actief_stijl; ?>">Weekrooster</a>
        
        <a href="<?php echo $path; ?>agenda.php" style="<?php if($huidige_pagina == 'agenda.php') echo $actief_stijl; ?>">Agenda</a>

        <a href="<?php echo $path; ?>evenementen/party_beheer.php" style="<?php if($huidige_pagina == 'party_beheer.php') echo $actief_stijl; ?>">Evenementen</a>

        <?php if ($is_platform_owner): ?>
            <a href="<?php echo $path; ?>platform_owner.php" style="<?php if($huidige_pagina == 'platform_owner.php') echo $actief_stijl; ?>">Platform Owner</a>
        <?php endif; ?>
    </nav>

    <div class="header-toolbar">
        <form method="post" action="<?php echo $path; ?>switch_tenant.php" style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
            <?php if ($tenantSwitchCsrf !== ''): ?>
                <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($tenantSwitchCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($tenantSwitchRedirect, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="tenant_switcher" style="font-size:11px; font-weight:700; color:#cfe8ff; text-transform:uppercase;">Tenant</label>
            <select id="tenant_switcher" name="tenant_id" onchange="this.form.submit()" style="height:30px; min-width:150px; border-radius:6px; border:1px solid #1f4f7f; background:#0f3d66; color:#fff; padding:0 8px; font-size:12px;">
                <?php foreach ($tenantSwitchOptions as $tid => $tlabel): ?>
                    <option value="<?php echo (int) $tid; ?>" <?php echo ((int) $tenantSwitchCurrent === (int) $tid) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tlabel, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="dropdown">
            <div class="dropdown-btn" style="<?php if($is_admin_actief) echo $actief_stijl; ?>">
                Administratie &#9662;
            </div>
            <div class="dropdown-content">
                
                <div class="dropdown-header">Relaties & Communicatie</div>
                <a href="<?php echo $path; ?>klanten.php">Klanten</a>
                <a href="<?php echo $path; ?>mail_sjablonen.php">E-mail Sjablonen</a>
                <a href="<?php echo $path; ?>bedrijfsinstellingen.php">Bedrijfsinstellingen</a>
                
                <div class="dropdown-header">Planning & Operatie</div>
                <a href="<?php echo $path; ?>ritten.php">Ritverwerking</a>
                <a href="<?php echo $path; ?>vaste_ritten/overzicht.php">Vaste Ritten (Planning)</a>
                <a href="<?php echo $path; ?>vaste_ritten/dagbesteding.php">Stamrooster (Passagiers)</a>
                <a href="<?php echo $path; ?>vaste_ritten/radeland_mail.php">Radeland mail</a>
                
                <div class="dropdown-header">Personeel & Wagenpark</div>
                <a href="<?php echo $path; ?>chauffeurs.php">Chauffeurs</a>
                <a href="<?php echo $path; ?>gebruikers.php">Gebruikers (SaaS)</a>
                <a href="<?php echo $path; ?>vakantierooster_chauffeur.php">Vakantierooster</a>
                <a href="<?php echo $path; ?>voertuigen.php">Voertuigen</a>
                <a href="<?php echo $path; ?>uren_controle.php">Uren Controle (Digi Excel)</a>
                <a href="<?php echo $path; ?>loonadministratie.php">Loonadministratie</a>
                
            </div>
        </div>
    </div>
</header>