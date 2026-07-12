<?php
require_once __DIR__ . '/tenant_instellingen_db.php';
if (!isset($pdo) && file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

// Relatief pad naar /beheer/ root
$path = '';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$parts = array_values(array_filter(explode('/', $scriptDir), static fn($p) => $p !== ''));
$idx = array_search('beheer', $parts, true);
if ($idx !== false) {
    $depthBelowBeheer = count($parts) - ($idx + 1);
    $path = str_repeat('../', max(0, $depthBelowBeheer));
}

$huidige_pagina  = basename($_SERVER['PHP_SELF']);
$huidig_pad      = $_SERVER['PHP_SELF'] ?? '';
$is_platform_owner = function_exists('current_user_role') && current_user_role() === 'platform_owner';

// Bedrijfsnaam ophalen
$bedrijfsnaam = '';
if (isset($pdo) && $pdo instanceof PDO && function_exists('current_tenant_id')) {
    try {
        $tid = current_tenant_id();
        $stn = $pdo->prepare("SELECT naam FROM tenants WHERE id = ? LIMIT 1");
        $stn->execute([$tid]);
        $bedrijfsnaam = $stn->fetchColumn() ?: '';
    } catch (Throwable) {}
}

// Laad actieve modules voor huidige tenant
require_once __DIR__ . '/module_access.php';

$actieve_modules = [];
if (isset($pdo) && $pdo instanceof PDO && function_exists('current_tenant_id')) {
    try {
        $tid = current_tenant_id();
        $actieve_modules = tenant_actieve_modules($pdo, $tid);
    } catch (Throwable) {}
}

function heeft_module(array $actieve_modules, string $code): bool {
    return in_array($code, $actieve_modules, true);
}

$is_reizen_portaal = isset($pdo) && $pdo instanceof PDO && function_exists('current_tenant_id')
    && tenant_is_reizen_portaal($pdo, current_tenant_id());

require_once __DIR__ . '/reis_netwerk.php';

// Actieve sectie detectie
function nav_actief(string $huidig_pad, string $huidige_pagina, array $paginas, array $paden = []): string {
    if (in_array($huidige_pagina, $paginas, true)) return 'actief';
    foreach ($paden as $pad) {
        if (str_contains($huidig_pad, $pad)) return 'actief';
    }
    return '';
}

$actief_planning  = nav_actief($huidig_pad, $huidige_pagina,
    ['live_planbord.php','weekrooster.php','agenda.php','party_beheer.php','ritten.php','nieuwe_rit.php','rit-bekijken.php','overzicht.php','dagbesteding.php','radeland_mail.php'],
    ['/vaste_ritten/']
);
$actief_verkoop   = nav_actief($huidig_pad, $huidige_pagina,
    ['calculaties.php','calculaties_bewerken.php','klanten.php','klant-bewerken.php','mail_sjablonen.php']
);
$actief_marketing = nav_actief($huidig_pad, $huidige_pagina, [], ['/social_media/']);
$actief_reizen    = nav_actief($huidig_pad, $huidige_pagina, [], ['/reizen/']);
$actief_beheer    = nav_actief($huidig_pad, $huidige_pagina,
    ['chauffeurs.php','chauffeur-bewerken.php','chauffeur-toevoegen.php','voertuigen.php','voertuig-bewerken.php','voertuig-toevoegen.php','vakantierooster_chauffeur.php','chauffeur_vakantie.php','uren_controle.php','loonadministratie.php','gebruikers.php','bedrijfsinstellingen.php']
);
$actief_dashboard = nav_actief($huidig_pad, $huidige_pagina, ['dashboard.php','index.php']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tourplan</title>
    <link rel="icon" type="image/png" href="<?php echo $path; ?>../assets/favicon.png">
    <link rel="stylesheet" href="<?php echo $path; ?>style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background: #f0f4f8; color: #1a2533; }

        /* ── HEADER ── */
        header {
            background: linear-gradient(135deg, #002855 0%, #003d82 100%);
            color: #fff;
            height: 56px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .header-logo img {
            height: 40px;
            width: auto;
            display: block;
            filter: drop-shadow(0 1px 3px rgba(0,0,0,0.3));
        }

        .header-divider {
            width: 1px;
            height: 28px;
            background: rgba(255,255,255,0.2);
            flex-shrink: 0;
        }

        /* ── NAVIGATIE ── */
        nav {
            display: flex;
            height: 100%;
            align-items: stretch;
            gap: 2px;
            flex: 1;
        }

        /* Directe link (Dashboard) */
        nav > a {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            color: rgba(255,255,255,0.88);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: background .18s, border-color .18s, color .18s;
            white-space: nowrap;
            border-radius: 4px 4px 0 0;
        }
        nav > a:hover,
        nav > a.actief {
            background: rgba(255,255,255,0.1);
            border-bottom-color: #5bc8f5;
            color: #fff;
        }

        /* ── DROPDOWN ── */
        .dd {
            position: relative;
            display: flex;
            align-items: stretch;
        }

        .dd-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 0 14px;
            color: rgba(255,255,255,0.88);
            font-size: 13.5px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: background .18s, border-color .18s, color .18s;
            white-space: nowrap;
            border-radius: 4px 4px 0 0;
            user-select: none;
        }
        .dd-btn .chevron {
            font-size: 10px;
            opacity: .7;
            transition: transform .2s;
        }
        .dd-btn.actief,
        .dd.open .dd-btn {
            background: rgba(255,255,255,0.1);
            border-bottom-color: #5bc8f5;
            color: #fff;
        }
        .dd.open .dd-btn .chevron { transform: rotate(180deg); }

        /* Dropdown panel */
        .dd-menu {
            display: none;
            position: absolute;
            top: 56px;
            left: 0;
            min-width: 240px;
            background: #fff;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            border: 1px solid rgba(0,0,0,0.08);
            border-top: 3px solid #003d82;
            z-index: 1000;
            overflow: hidden;
            animation: fadeDown .15s ease;
        }
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .dd.open .dd-menu { display: block; }

        .dd-sectie {
            padding: 10px 16px 6px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: #003d82;
            background: #f4f8ff;
            border-bottom: 1px solid #e8eef7;
            margin-top: 4px;
        }
        .dd-sectie:first-child { margin-top: 0; }

        .dd-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            color: #2d3748;
            text-decoration: none;
            font-size: 13.5px;
            border-left: 3px solid transparent;
            transition: background .15s, border-color .15s, color .15s, padding-left .15s;
        }
        .dd-menu a i {
            width: 16px;
            text-align: center;
            color: #6b7fa3;
            font-size: 13px;
            flex-shrink: 0;
        }
        .dd-menu a:hover {
            background: #f0f6ff;
            border-left-color: #003d82;
            color: #003d82;
            padding-left: 20px;
        }
        .dd-menu a:hover i { color: #003d82; }
        .dd-menu a.actief {
            background: #e8f0fe;
            border-left-color: #003d82;
            color: #003d82;
            font-weight: 600;
        }
        .dd-menu hr {
            margin: 4px 0;
            border: none;
            border-top: 1px solid #edf2f7;
        }

        /* ── RECHTER TOOLBAR ── */
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .header-bedrijf {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            white-space: nowrap;
            letter-spacing: .3px;
        }

        .btn-uitloggen {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.88);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background .18s, border-color .18s;
            white-space: nowrap;
        }
        .btn-uitloggen:hover {
            background: rgba(255,50,50,0.25);
            border-color: rgba(255,100,100,0.5);
            color: #fff;
        }

        .btn-platform {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(255,193,7,0.15);
            color: #ffc107;
            border: 1px solid rgba(255,193,7,0.3);
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: background .18s;
            white-space: nowrap;
        }
        .btn-platform:hover { background: rgba(255,193,7,0.28); color: #ffd54f; }

        /* ── CONTAINER & TABELLEN ── */
        .container {
            padding: 28px 32px;
            background: #fff;
            max-width: 1280px;
            margin: 28px auto;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 14px; }
        th, td { border-bottom: 1px solid #eef2f7; padding: 11px 14px; text-align: left; }
        th { background: #f7f9fc; color: #003d82; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        tr:hover td { background: #fafcff; }

        /* ── MOBIEL HAMBURGERMENU (desktop ongewijzigd) ── */
        .nav-toggle,
        .nav-overlay,
        .nav-mobile-foot { display: none; }

        @media (max-width: 960px) {
            header {
                padding: 0 12px;
                gap: 8px;
            }

            .header-divider,
            .header-right { display: none !important; }

            .nav-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                margin-left: auto;
                padding: 0;
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.22);
                border-radius: 8px;
                color: #fff;
                font-size: 18px;
                cursor: pointer;
                flex-shrink: 0;
                transition: background .18s, border-color .18s;
            }
            .nav-toggle:hover,
            .nav-toggle:focus-visible {
                background: rgba(255,255,255,0.18);
                border-color: rgba(255,255,255,0.35);
                outline: none;
            }

            .nav-overlay {
                display: none;
                position: fixed;
                inset: 56px 0 0 0;
                background: rgba(0, 20, 45, 0.45);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity .22s ease, visibility .22s ease;
                z-index: 950;
            }
            body.nav-open .nav-overlay {
                display: block;
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            nav {
                position: fixed;
                top: 56px;
                left: 0;
                bottom: 0;
                width: min(320px, 88vw);
                min-height: calc(100dvh - 56px);
                flex: none;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 8px 0 24px;
                background: linear-gradient(180deg, #002855 0%, #003d82 100%);
                box-shadow: 8px 0 32px rgba(0,0,0,0.28);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                transform: translateX(-105%);
                transition: transform .24s ease;
                z-index: 960;
            }
            body.nav-open nav {
                transform: translateX(0);
            }
            body.nav-open { overflow: hidden; }

            nav > a,
            .dd-btn {
                width: 100%;
                min-height: 48px;
                padding: 12px 18px;
                border-bottom: none;
                border-radius: 0;
                justify-content: flex-start;
                font-size: 15px;
            }
            nav > a.actief,
            .dd-btn.actief,
            .dd.open .dd-btn {
                background: rgba(255,255,255,0.12);
                border-left: 4px solid #5bc8f5;
                padding-left: 14px;
            }

            .dd {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            .dd-btn .chevron { margin-left: auto; }

            .dd-menu {
                display: none;
                position: static;
                top: auto;
                left: auto;
                min-width: 0;
                width: 100%;
                border: none;
                border-radius: 0;
                box-shadow: none;
                background: rgba(0,0,0,0.14);
                animation: none;
            }
            .dd.open .dd-menu { display: block; }

            .dd-sectie {
                background: rgba(255,255,255,0.06);
                color: rgba(255,255,255,0.72);
                border-bottom-color: rgba(255,255,255,0.08);
                padding-left: 22px;
            }
            .dd-menu a {
                color: rgba(255,255,255,0.92);
                padding: 11px 18px 11px 28px;
                border-left: none;
                font-size: 14px;
            }
            .dd-menu a i { color: rgba(255,255,255,0.55); }
            .dd-menu a:hover,
            .dd-menu a.actief {
                background: rgba(255,255,255,0.1);
                color: #fff;
                padding-left: 32px;
                border-left: none;
            }
            .dd-menu a.actief { font-weight: 600; }
            .dd-menu hr { border-top-color: rgba(255,255,255,0.1); }

            .nav-mobile-foot {
                display: block;
                margin-top: auto;
                padding: 16px 18px 8px;
                border-top: 1px solid rgba(255,255,255,0.14);
            }
            .nav-mobile-foot .nav-mobile-bedrijf {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 600;
                color: rgba(255,255,255,0.55);
                margin-bottom: 12px;
            }
            .nav-mobile-foot a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 0;
                color: rgba(255,255,255,0.9);
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
            }
            .nav-mobile-foot a i { width: 16px; text-align: center; opacity: .75; }
            .nav-mobile-foot a.nav-mobile-platform { color: #ffd54f; }
            .nav-mobile-foot .btn-uitloggen-mobile {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                margin-top: 10px;
                padding: 11px 14px;
                background: rgba(255,255,255,0.1);
                color: #fff;
                border: 1px solid rgba(255,255,255,0.22);
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
            }
        }
    </style>
</head>
<body>

<header>
    <a class="header-logo" href="<?php echo $path; ?>dashboard.php">
        <img src="<?php echo $path; ?>../assets/tourplan-logo-header-600.png" alt="Tourplan">
    </a>

    <div class="header-divider"></div>

    <nav id="hoofdnav">
        <!-- Dashboard -->
        <?php if ($is_reizen_portaal): ?>
        <a href="<?php echo $path; ?>reizen/index.php" class="<?php echo str_contains($huidig_pad, '/reizen/') ? 'actief' : ''; ?>">
            <i class="fa-solid fa-route"></i> Dagtochten
        </a>
        <?php else: ?>
        <a href="<?php echo $path; ?>dashboard.php" class="<?php echo $actief_dashboard; ?>">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <?php endif; ?>

        <?php if (!$is_reizen_portaal && (heeft_module($actieve_modules, 'basis') || heeft_module($actieve_modules, 'planbord') || heeft_module($actieve_modules, 'evenementen') || heeft_module($actieve_modules, 'vaste_ritten'))): ?>
        <!-- Planning -->
        <div class="dd">
            <div class="dd-btn <?php echo $actief_planning; ?>">
                <i class="fa-solid fa-calendar-days"></i> Planning
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu">
                <?php if (heeft_module($actieve_modules, 'basis')): ?>
                <div class="dd-sectie">Overzichten</div>
                <a href="<?php echo $path; ?>weekrooster.php"     class="<?php echo $huidige_pagina==='weekrooster.php'?'actief':''; ?>"><i class="fa-solid fa-table-columns"></i> Weekrooster</a>
                <a href="<?php echo $path; ?>agenda.php"          class="<?php echo $huidige_pagina==='agenda.php'?'actief':''; ?>"><i class="fa-solid fa-calendar-week"></i> Agenda</a>
                <?php endif; ?>
                <?php if (heeft_module($actieve_modules, 'planbord')): ?>
                <a href="<?php echo $path; ?>live_planbord.php"   class="<?php echo $huidige_pagina==='live_planbord.php'?'actief':''; ?>"><i class="fa-solid fa-map-location-dot"></i> Live Planbord</a>
                <?php endif; ?>
                <?php if (heeft_module($actieve_modules, 'evenementen')): ?>
                <a href="<?php echo $path; ?>evenementen/party_beheer.php" class="<?php echo $huidige_pagina==='party_beheer.php'?'actief':''; ?>"><i class="fa-solid fa-star"></i> Evenementen</a>
                <?php endif; ?>
                <?php if (heeft_module($actieve_modules, 'basis')): ?>
                <div class="dd-sectie">Ritten</div>
                <a href="<?php echo $path; ?>ritten.php"          class="<?php echo $huidige_pagina==='ritten.php'?'actief':''; ?>"><i class="fa-solid fa-bus"></i> Ritverwerking</a>
                <?php endif; ?>
                <?php if (heeft_module($actieve_modules, 'vaste_ritten')): ?>
                <?php if (!heeft_module($actieve_modules, 'basis')): ?><div class="dd-sectie">Ritten</div><?php endif; ?>
                <a href="<?php echo $path; ?>vaste_ritten/overzicht.php"   class="<?php echo str_contains($huidig_pad,'/vaste_ritten/')?'actief':''; ?>"><i class="fa-solid fa-rotate"></i> Vaste Ritten</a>
                <a href="<?php echo $path; ?>vaste_ritten/dagbesteding.php"><i class="fa-solid fa-person-walking"></i> Stamrooster</a>
                <a href="<?php echo $path; ?>vaste_ritten/radeland_mail.php"><i class="fa-solid fa-envelope-open-text"></i> Radeland Mail</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_reizen_portaal && heeft_module($actieve_modules, 'basis')): ?>
        <!-- Verkoop -->
        <div class="dd">
            <div class="dd-btn <?php echo $actief_verkoop; ?>">
                <i class="fa-solid fa-chart-line"></i> Verkoop
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu">
                <div class="dd-sectie">Sales</div>
                <a href="<?php echo $path; ?>calculaties.php" class="<?php echo in_array($huidige_pagina,['calculaties.php','calculaties_bewerken.php'])?'actief':''; ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Offertes & Sales</a>
                <div class="dd-sectie">Relaties</div>
                <a href="<?php echo $path; ?>klanten.php"     class="<?php echo in_array($huidige_pagina,['klanten.php','klant-bewerken.php'])?'actief':''; ?>"><i class="fa-solid fa-users"></i> Klanten</a>
                <a href="<?php echo $path; ?>mail_sjablonen.php" class="<?php echo $huidige_pagina==='mail_sjablonen.php'?'actief':''; ?>"><i class="fa-solid fa-envelopes-bulk"></i> E-mail Sjablonen</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_reizen_portaal && heeft_module($actieve_modules, 'social_media')): ?>
        <!-- Marketing -->
        <div class="dd">
            <div class="dd-btn <?php echo $actief_marketing; ?>">
                <i class="fa-solid fa-bullhorn"></i> Marketing
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu">
                <div class="dd-sectie">Social Media</div>
                <a href="<?php echo $path; ?>social_media/index.php"          class="<?php echo str_contains($huidig_pad,'/social_media/') && $huidige_pagina==='index.php'?'actief':''; ?>"><i class="fa-brands fa-facebook"></i> Overzicht & Goedkeuren</a>
                <a href="<?php echo $path; ?>social_media/evenement_teksten.php" class="<?php echo $huidige_pagina==='evenement_teksten.php'?'actief':''; ?>"><i class="fa-solid fa-pen-to-square"></i> Teksten beheren</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_platform_owner || heeft_reizen_module($actieve_modules)):
            $reizen_menu_titel = 'Dagtochten';
            if (heeft_module($actieve_modules, 'coopdagtochten')) {
                $reizen_menu_titel = 'Coöp Dagtochten';
            } elseif (heeft_module($actieve_modules, 'busreizen')) {
                $reizen_menu_titel = 'Busreizen & Dagtochten';
            }
            $coop_mag_bewerken = !isset($pdo) || !($pdo instanceof PDO) || !function_exists('current_tenant_id')
                ? true
                : reis_netwerk_mag_bewerken($pdo, current_tenant_id(), $actieve_modules);
        ?>
        <!-- Dagtochten / Coöp / legacy busreizen -->
        <div class="dd">
            <div class="dd-btn <?php echo $actief_reizen; ?>">
                <i class="fa-solid fa-route"></i> Reizen
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu">
                <div class="dd-sectie"><?php echo htmlspecialchars($reizen_menu_titel, ENT_QUOTES, 'UTF-8'); ?></div>
                <a href="<?php echo $path; ?>reizen/index.php" class="<?php echo str_contains($huidig_pad,'/reizen/') && $huidige_pagina==='index.php'?'actief':''; ?>"><i class="fa-solid fa-list"></i> Alle reizen</a>
                <?php if ($coop_mag_bewerken): ?>
                <a href="<?php echo $path; ?>reizen/bewerken.php" class="<?php echo $huidige_pagina==='bewerken.php' && str_contains($huidig_pad,'/reizen/')?'actief':''; ?>"><i class="fa-solid fa-plus"></i> Nieuwe reis</a>
                <?php endif; ?>
                <hr>
                <a href="<?php echo $path; ?>reizen/boekingen.php" class="<?php echo $huidige_pagina==='boekingen.php' && str_contains($huidig_pad,'/reizen/')?'actief':''; ?>"><i class="fa-solid fa-users"></i> Alle boekingen</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$is_reizen_portaal && heeft_module($actieve_modules, 'basis')): ?>
        <!-- Beheer -->
        <div class="dd">
            <div class="dd-btn <?php echo $actief_beheer; ?>">
                <i class="fa-solid fa-gear"></i> Beheer
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu">
                <div class="dd-sectie">Personeel</div>
                <a href="<?php echo $path; ?>chauffeurs.php"             class="<?php echo in_array($huidige_pagina,['chauffeurs.php','chauffeur-bewerken.php','chauffeur-toevoegen.php'])?'actief':''; ?>"><i class="fa-solid fa-id-card"></i> Chauffeurs</a>
                <a href="<?php echo $path; ?>vakantierooster_chauffeur.php" class="<?php echo in_array($huidige_pagina,['vakantierooster_chauffeur.php','chauffeur_vakantie.php'])?'actief':''; ?>"><i class="fa-solid fa-umbrella-beach"></i> Vakantierooster</a>
                <a href="<?php echo $path; ?>uren_controle.php"          class="<?php echo $huidige_pagina==='uren_controle.php'?'actief':''; ?>"><i class="fa-solid fa-clock"></i> Uren Controle</a>
                <a href="<?php echo $path; ?>loonadministratie.php"      class="<?php echo $huidige_pagina==='loonadministratie.php'?'actief':''; ?>"><i class="fa-solid fa-money-bill-wave"></i> Loonadministratie</a>
                <div class="dd-sectie">Wagenpark</div>
                <a href="<?php echo $path; ?>voertuigen.php"             class="<?php echo in_array($huidige_pagina,['voertuigen.php','voertuig-bewerken.php','voertuig-toevoegen.php'])?'actief':''; ?>"><i class="fa-solid fa-bus-simple"></i> Voertuigen</a>
                <div class="dd-sectie">Systeem</div>
                <a href="<?php echo $path; ?>gebruikers.php"             class="<?php echo $huidige_pagina==='gebruikers.php'?'actief':''; ?>"><i class="fa-solid fa-user-gear"></i> Gebruikers</a>
                <a href="<?php echo $path; ?>bedrijfsinstellingen.php"   class="<?php echo $huidige_pagina==='bedrijfsinstellingen.php'?'actief':''; ?>"><i class="fa-solid fa-building"></i> Bedrijfsinstellingen</a>
                <a href="<?php echo $path; ?>mijn_instellingen.php"      class="<?php echo $huidige_pagina==='mijn_instellingen.php'?'actief':''; ?>"><i class="fa-solid fa-lock"></i> Mijn Koppelingen</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-mobile-foot">
            <?php if ($bedrijfsnaam !== ''): ?>
                <div class="nav-mobile-bedrijf">
                    <i class="fa-solid fa-building"></i>
                    <?php echo htmlspecialchars($bedrijfsnaam, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($is_platform_owner): ?>
                <a href="<?php echo $path; ?>platform_owner.php" class="nav-mobile-platform">
                    <i class="fa-solid fa-crown"></i> Platform Owner
                </a>
                <a href="<?php echo $path; ?>module_beheer.php">
                    <i class="fa-solid fa-puzzle-piece"></i> Module Beheer
                </a>
                <a href="<?php echo $path; ?>api_gebruik_overzicht.php">
                    <i class="fa-solid fa-chart-bar"></i> API-gebruik
                </a>
            <?php endif; ?>
            <a href="/beveiliging.php?uitloggen=1" class="btn-uitloggen-mobile">
                <i class="fa-solid fa-right-from-bracket"></i> Uitloggen
            </a>
        </div>
    </nav>

    <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Menu openen" aria-expanded="false" aria-controls="hoofdnav">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <div class="nav-overlay" id="nav-overlay"></div>

    <!-- Rechter toolbar -->
    <div class="header-right">
        <?php if ($bedrijfsnaam !== ''): ?>
            <span class="header-bedrijf"><i class="fa-solid fa-building" style="margin-right:4px;opacity:.6;"></i><?php echo htmlspecialchars($bedrijfsnaam, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>

        <?php if ($is_platform_owner): ?>
        <div class="dd">
            <div class="dd-btn" style="color:#ffc107; background:rgba(255,193,7,0.12); border-radius:6px; border:1px solid rgba(255,193,7,0.3); margin:0 4px;">
                <i class="fa-solid fa-crown"></i> Platform
                <span class="chevron"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="dd-menu" style="right:0; left:auto;">
                <div class="dd-sectie">Platform beheer</div>
                <a href="<?php echo $path; ?>platform_owner.php"       class="<?php echo $huidige_pagina==='platform_owner.php'?'actief':''; ?>"><i class="fa-solid fa-gauge"></i> Platform Owner</a>
                <a href="<?php echo $path; ?>module_beheer.php"        class="<?php echo $huidige_pagina==='module_beheer.php'?'actief':''; ?>"><i class="fa-solid fa-puzzle-piece"></i> Module Beheer</a>
                <a href="<?php echo $path; ?>api_gebruik_overzicht.php" class="<?php echo $huidige_pagina==='api_gebruik_overzicht.php'?'actief':''; ?>"><i class="fa-solid fa-chart-bar"></i> API-gebruik</a>
            </div>
        </div>
        <?php endif; ?>

        <a href="/beveiliging.php?uitloggen=1" class="btn-uitloggen">
            <i class="fa-solid fa-right-from-bracket"></i> Uitloggen
        </a>
    </div>
</header>

<script>
(function() {
    var MOBILE_MAX = 960;
    var navToggle = document.getElementById('nav-toggle');
    var navOverlay = document.getElementById('nav-overlay');
    var navEl = document.getElementById('hoofdnav');

    function isMobileNav() {
        return window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches;
    }

    function setNavOpen(open) {
        document.body.classList.toggle('nav-open', open);
        if (navToggle) {
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Menu sluiten' : 'Menu openen');
            var icon = navToggle.querySelector('i');
            if (icon) {
                icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
            }
        }
    }

    function closeNav() {
        setNavOpen(false);
        document.querySelectorAll('.dd.open').forEach(function(d) { d.classList.remove('open'); });
    }

    if (navToggle) {
        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!isMobileNav()) return;
            setNavOpen(!document.body.classList.contains('nav-open'));
        });
    }
    if (navOverlay) {
        navOverlay.addEventListener('click', closeNav);
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNav();
    });
    window.addEventListener('resize', function() {
        if (!isMobileNav()) closeNav();
    });

    // Klik op dropdown-knop opent/sluit het menu
    document.querySelectorAll('.dd-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dd = btn.closest('.dd');
            var wasOpen = dd.classList.contains('open');
            // Sluit alle dropdowns
            document.querySelectorAll('.dd.open').forEach(function(d) { d.classList.remove('open'); });
            // Open deze als hij dicht was
            if (!wasOpen) dd.classList.add('open');
        });
    });
    // Klik buiten het menu sluit alles (alleen desktop dropdowns)
    document.addEventListener('click', function() {
        if (isMobileNav()) return;
        document.querySelectorAll('.dd.open').forEach(function(d) { d.classList.remove('open'); });
    });
    // Klik in het menu zelf sluit niet (behalve op een link)
    document.querySelectorAll('.dd-menu').forEach(function(menu) {
        menu.addEventListener('click', function(e) { e.stopPropagation(); });
    });

    // Mobiel: menu sluiten na navigatie
    if (navEl) {
        navEl.querySelectorAll('a[href]').forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobileNav()) closeNav();
            });
        });
    }
})();
</script>
