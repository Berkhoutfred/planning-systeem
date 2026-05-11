<?php 
// Bestand: beheer/calculatie/maken.php 
// Versie: 4.3 - Inclusief BTW-Fix (Opslaan als Excl, weergave Incl) en Contactpersoon Voorbereiding

include '../../beveiliging.php'; 
require_role(['tenant_admin', 'planner_user']);
require '../includes/db.php'; 
include '../includes/header.php'; 

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

$moduleBuitenland = isset($_GET['module']) && $_GET['module'] === 'buitenland';

// STANDAARD WAARDEN
$rit = [
    'id' => 0, 'klant_id' => 0, 'contact_id' => 0, 'afdeling_id' => 0, 'rittype' => 'dagtocht', 
    'passagiers' => 50, 'rit_datum' => date('Y-m-d'), 'rit_datum_eind' => date('Y-m-d'), 
    'totaal_km' => 0, 'totaal_uren' => 0, 'prijs' => 0, 'voertuig_id' => 0,
    'km_nl' => 0, 'km_de' => 0, 'km_eu' => 0, 'km_tussen' => 0
]; 
if ($moduleBuitenland) {
    $rit['rittype'] = 'buitenland';
}
$data = [];

$uiBuildConf = require __DIR__ . '/includes/ui_build.php';
$uiBuildLabel = 'nr. ' . (int) ($uiBuildConf['nr'] ?? 1) . ' · ' . htmlspecialchars((string) ($uiBuildConf['time'] ?? ''), ENT_QUOTES, 'UTF-8');

try {
    $instellingen = tenant_calculatie_instellingen_merged($pdo, $tenantId);
    $chauffeur_uurloon = $instellingen['uurloon_basis'];

    // Haal bussen op en zet ze in een Javascript string voor het dynamisch toevoegen
    $stmtBussen = $pdo->prepare("SELECT * FROM calculatie_voertuigen WHERE tenant_id = ? AND actief = 1 ORDER BY capaciteit ASC");
    $stmtBussen->execute([$tenantId]);
    $bussen = $stmtBussen->fetchAll();
    
    $busOptiesHTML = "<option value=''>-- Kies extra voertuig --</option>";
    foreach($bussen as $b) {
        $busOptiesHTML .= "<option value='".$b['id']."' data-km='".$b['km_kostprijs']."'>".htmlspecialchars($b['naam'])." (€".number_format($b['km_kostprijs'], 2, ',', '.')."/km)</option>";
    }
    $busOptiesTussendagHTML = "<option value=''>— Zelfde als hoofdbus —</option>";
    foreach ($bussen as $b) {
        $busOptiesTussendagHTML .= "<option value='" . (int) $b['id'] . "' data-km='" . htmlspecialchars((string) $b['km_kostprijs'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($b['naam']) . " (€" . number_format((float) $b['km_kostprijs'], 2, ',', '.') . "/km)</option>";
    }
} catch (PDOException $e) {
    die("Database Fout: " . $e->getMessage());
}

function val($data, $rij, $veld, $default = '') { 
    return isset($data[$rij][$veld]) ? htmlspecialchars($data[$rij][$veld]) : $default; 
} 
?> 

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> 

<style> 
    body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .container { max-width: 1200px; margin: auto; padding: 20px; }
    .section-box { background: #fff; padding: 0; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #ddd; }
    .box-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
    .box-title { color:#003366; font-size:16px; font-weight:bold; text-transform: uppercase; margin:0; }
    .header-rit-2 { background: #003366; color: white; padding: 10px 20px; font-weight: bold; text-transform: uppercase; font-size: 14px; margin-top: 25px; border-radius: 4px 4px 0 0; }
    .box-body { padding: 20px; }
    .form-grid-4 { display:grid; grid-template-columns: repeat(4, 1fr); gap: 15px; align-items: end; }
    .form-grid-3 { display:grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; }
    .form-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-control { width: 100%; height: 40px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box; } 
    label { font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; display: block; }
    .rit-row { display: flex; gap: 15px; margin-bottom: 8px; align-items: flex-end; padding-bottom: 8px; border-bottom: 1px dashed #f0f0f0; }
    .col-tijd { width: 85px; } .col-adres { flex: 1; } .col-km { width: 75px; }
    .route-compact .rit-row { margin-bottom: 4px; padding-bottom: 4px; align-items: center; flex-wrap: nowrap; }
    .route-compact label { font-size: 11px; margin-bottom: 2px; white-space: nowrap; }
    /* Smalle één-regel adresvelden (overschrijft o.a. globale .form-control hoogte) */
    .route-compact .col-adres {
        flex: 1 1 260px;
        min-width: 0;
        max-width: min(100%, 380px);
    }
    .route-compact .col-adres input.form-control,
    .route-compact .col-adres input.google-autocomplete {
        height: 32px !important;
        min-height: 32px !important;
        max-height: 32px !important;
        line-height: 1.25 !important;
        padding: 4px 10px !important;
        font-size: 13px !important;
        box-sizing: border-box !important;
    }
    .route-compact .col-tijd { width: 74px; flex-shrink: 0; }
    .route-compact .col-tijd .form-control.custom-time-input {
        height: 32px !important;
        min-height: 32px !important;
        padding: 4px 4px !important;
        font-size: 12px !important;
    }
    .route-compact .col-km { width: 62px; flex-shrink: 0; }
    .route-compact .col-km .form-control { height: 32px !important; min-height: 32px !important; padding: 4px 6px !important; font-size: 13px !important; }
    .route-compact .col-zone { width: 52px; flex-shrink: 0; }
    .route-compact .col-zone .form-control { height: 32px !important; padding: 2px 2px !important; font-size: 11px !important; }
    .col-tijd-muted label { color: #aaa; font-size: 10px; }
    .tijd-hint { font-size: 10px; color: #999; display: block; padding-bottom: 4px; }
    .custom-time-input { background-color: #fff !important; cursor: pointer; text-align: center; font-weight: bold; color: #003366; border: 1px solid #003366; }
    
    /* MODALS */
    .modal-overlay { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(2px); }
    .modal-content { background-color: #fefefe; margin: 10vh auto; padding: 0; border: 1px solid #999; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); font-family: sans-serif; overflow: hidden; }
    .modal-header { background: #003366; color:white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; font-weight:bold; }
    .time-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; padding: 15px; max-height: 400px; overflow-y: auto;}
    .time-btn { background: #f0f8ff; border: 1px solid #b0d4f1; color: #003366; padding: 10px 0; text-align: center; cursor: pointer; border-radius: 4px; font-weight: bold; }
    
    .profit-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px; } 
    .profit-box { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #ddd; } 
    .profit-value { font-size:22px; font-weight:bold; color: #333; margin-top: 5px;}
    .btn-save { width:100%; padding:15px; background:#28a745; color:white; border:none; border-radius:6px; font-weight:bold; font-size:16px; cursor:pointer; margin-top: 20px;}
    .btn-save:hover { background: #218838; }
    #klant_info_card { background: #eef; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccd; font-size: 13px; display:none; }
    
    .extra-bus-row { display:flex; align-items:center; gap:10px; margin-top:10px; padding:10px; background:#f8f9fa; border:1px dashed #ccc; border-radius:4px; }
    .btn-add-bus { background: #003366; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; margin-top: 10px; }
    .btn-remove-bus { background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
    .tz-wrap { font-size: 12px; margin-top: 8px; }
    /* Extra rijdag: zelfde smalle rit-regels als heen/terug */
    #wrap_extra_rijdag { max-width: 100%; }
    #wrap_extra_rijdag .tz-toggle-row { margin-bottom: 0; padding-bottom: 4px; border-bottom: none; align-items: center; }
    #block_tussendagen_inner { margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e0e0e0; }
    #block_tussendagen_inner .rit-row.tz-row { margin-bottom: 4px; padding-bottom: 4px; }
    #block_tussendagen_inner .tz-col-datum { width: 118px; flex-shrink: 0; }
    #block_tussendagen_inner .tz-col-datum .form-control { height: 32px !important; font-size: 12px !important; padding: 4px 6px !important; }
    #block_tussendagen_inner .tz-col-tijd { width: 74px; flex-shrink: 0; }
    #block_tussendagen_inner .tz-col-tijd .form-control { height: 32px !important; font-size: 12px !important; padding: 4px 4px !important; }
    #btn_tz_add { font-size: 11px; padding: 4px 10px; margin-top: 4px; background: #003366; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    .rit-row-check-only { align-items: center; min-height: 28px; }
    .rit-row-check-only input[type="checkbox"] { width: 14px; height: 14px; flex-shrink: 0; }
    .rit-row-check-only label { margin: 0; font-size: 11px; font-weight: 700; color: #003366; cursor: pointer; white-space: nowrap; }
    #block_buitenland_extra { display: none; margin-top: 12px; padding: 12px 14px; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 6px; font-size: 13px; }
    .legacy-heen-sr-only {
        position: absolute !important;
        width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
    }
    .heen-seg-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px; }
    .heen-seg-table th { text-align: left; background: #003366; color: #fff; padding: 8px 10px; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
    .heen-seg-table td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .heen-seg-table .form-control { height: 30px !important; font-size: 12px !important; padding: 4px 8px !important; }
    .heen-td-t { width: 76px; }
    .heen-td-km { width: 64px; }
    .heen-td-rm { width: 36px; text-align: center; }
    .heen-opt-row { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e0e0e0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
    .heen-opt-row > .heen-opt-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
    .heen-opt-mini {
        display: inline-block; padding: 2px 8px; min-width: 0;
        font-size: 11px; font-weight: 700; color: #003366;
        background: #fff; border: 1px solid #94a3b8; border-radius: 4px;
        cursor: pointer; line-height: 1.2;
    }
    .heen-opt-mini:hover { border-color: #003366; background: #f1f5f9; }
    .heen-opt-mini:focus { outline: none; box-shadow: 0 0 0 2px rgba(0,51,102,.2); }
    .heen-opt-mini.is-active { border-color: #003366; background: #e8eef5; }
    .terugreis-gate-bar { margin-top: 14px; align-items: center; gap: 10px; }
    .btn-terugreis-open {
        font-size: 11px; font-weight: 700; color: #003366;
        padding: 5px 12px; border: 1px dashed #003366; border-radius: 6px;
        background: #fff; cursor: pointer;
    }
    .btn-terugreis-open:hover { background: #f8fafc; }
    .heen-vt--auto,
    .heen-at--auto { background: #f8fafc !important; color: #475569; font-size: 11px !important; }
    .heen-seg-table input.heen-vt,
    .heen-seg-table input.heen-at:not([readonly]) { cursor: pointer; }
    .calculatie-ui-build {
        text-align: center;
        font-size: 11px;
        color: #64748b;
        margin: 16px 0 8px;
        letter-spacing: 0.02em;
    }
</style> 

<div class="container"> 
    <form action="opslaan.php" method="POST" id="hoofdFormulier"> 
        
        <input type="hidden" name="naar_dashboard" value="1"> 
        
        <div class="section-box" style="border-top: 4px solid #003366;"> 
            <div class="box-header"><h3 class="box-title"><i class="fas fa-user"></i> Klantgegevens</h3></div> 
            <div class="box-body">
                <div class="form-grid-3"> 
                    <div> 
                        <label>Klant zoeken in eigen database</label> 
                        <div style="position:relative;">
                            <input type="text" id="klant_zoek_input" class="form-control" placeholder="Typ bedrijfs- of achternaam..." autocomplete="off" style="font-weight:bold; border: 2px solid #003366;">
                            <input type="hidden" name="klant_id" id="klant_id_hidden" required>
                            
                            <div id="klant_resultaten_lijst" style="display:none; position:absolute; z-index:1000; background:#fff; width:100%; max-height:250px; overflow-y:auto; border:1px solid #003366; border-top:none; box-shadow:0 4px 10px rgba(0,0,0,0.2);"></div>
                        </div>
                        <div id="klant_info_card"> 
                            <strong id="c_naam"></strong><br><span id="c_adres"></span>, <span id="c_plaats"></span><br><span id="c_tel"></span> | <span id="c_email"></span> 
                        </div>
                    </div> 
                    <div> 
                        <label>Contactpersoon</label> 
                        <select name="contact_id" id="contact_select" class="form-control">
                            <option value="0">-- Algemeen --</option>
                        </select> 
                    </div> 
                    <div> 
                        <label>Afdeling / Groep</label> 
                        <select name="afdeling_id" id="afdeling_select" class="form-control">
                            <option value="0">-- Geen afdeling --</option>
                        </select> 
                    </div>
                </div> 
            </div>
        </div> 

        <div class="section-box" style="border-top: 4px solid #17a2b8;"> 
            <div class="box-header"><h3 class="box-title"><i class="fas fa-info-circle"></i> Ritgegevens</h3></div> 
            <div class="box-body">
                <div class="form-grid-4"> 
                    <div><label>Passagiers (Totaal personen)</label><input type="number" name="passagiers" class="form-control" value="<?= $rit['passagiers'] ?>"></div> 
                    <div>
                        <label>Soort Reis</label>
                        <select name="rittype" id="rittype_select" class="form-control" style="font-weight:bold; border: 2px solid #003366;">
                            <option value="dagtocht">Dagtocht (Standaard)</option>
                            <option value="schoolreis">Schoolreis</option>
                            <option value="enkel">Enkele Rit</option>
                            <option value="brenghaal">Breng & Haal (Split)</option>
                            <option value="trein">Treinstremming</option>
                            <option value="meerdaags">Meerdaagse Rit</option>
                            <option value="buitenland" <?= ($rit['rittype'] === 'buitenland') ? 'selected' : '' ?>>Buitenland</option>
                        </select>
                    </div> 
                    <div><label>Vertrekdatum</label><input type="date" name="rit_datum" id="rit_datum" class="form-control" value="<?= $rit['rit_datum'] ?>"></div> 
                    <div><label>Einddatum</label><input type="date" name="rit_datum_eind" id="rit_datum_eind" class="form-control" value="<?= $rit['rit_datum_eind'] ?>"></div> 
                </div> 
            </div>
        </div> 

        <div class="section-box" style="border-top: 4px solid #28a745;"> 
            <div class="box-header"><h3 class="box-title"><i class="fas fa-route"></i> Routeplanning</h3></div> 
            <div class="box-body">

                <div class="route-compact heen-segment-wrap" style="background: #fdfdfd; padding: 8px 10px; border: 1px solid #eee; border-radius: 4px;">
                    <div style="font-weight:bold; color:#003366; margin-bottom:6px; border-bottom:1px solid #ddd; padding-bottom:5px;">
                        HEENREIS / RIT 1 — segmenten
                    </div>
                    <p style="font-size:11px;color:#666;margin:0 0 8px;line-height:1.35;">Eerste rij is altijd <strong>garage → klant</strong>. Rechts vul je <strong>vertrek klant</strong> in; links wordt <strong>vertrek garage</strong> automatisch terug gerekend met 15 minuten marge. Volgende rijen lopen daarna door vanaf het klantadres.</p>
                    <table class="heen-seg-table">
                        <thead>
                            <tr>
                                <th class="heen-td-t">Vertrek</th>
                                <th>Van</th>
                                <th>Naar</th>
                                <th class="heen-td-t">Aankomst</th>
                                <th class="heen-zone-col" style="display:none;">Zone</th>
                                <th class="heen-td-km">Km</th>
                                <th class="heen-td-rm"></th>
                            </tr>
                        </thead>
                        <tbody id="heen_segmenten_body"></tbody>
                    </table>
                    <button type="button" class="btn-add-bus" id="btn_heen_seg_add" style="margin-top:8px;font-size:11px;padding:4px 10px;">+ Segment</button>
                    <div class="heen-opt-row" role="toolbar" aria-label="Regels">
                        <span class="heen-opt-label">Regels</span>
                        <button type="button" class="heen-opt-mini" id="btn_heen_opt_rg" aria-pressed="false" title="Retour garage na rit 1">RG</button>
                        <button type="button" class="heen-opt-mini" id="btn_heen_opt_rk" aria-pressed="false" title="Terug: bestemming → 1e klantadres">RK</button>
                    </div>
                </div>

                <div id="legacy_heen_mirror" class="legacy-heen-sr-only" aria-hidden="true">
                    <div class="rit-row" id="row_garage">
                        <input type="text" name="time[t_garage]" id="time_t_garage" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly>
                        <input type="text" name="addr[t_garage]" id="addr_t_garage" class="form-control reken-trigger" value="Industrieweg 95, Zutphen" placeholder="Garage..." autocomplete="off">
                    </div>
                    <div class="rit-row" id="row_vertrek_klant">
                        <input type="text" name="time[t_vertrek_klant]" id="time_t_vertrek_klant" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly>
                        <input type="text" name="addr[t_vertrek_klant]" id="addr_t_vertrek_klant" class="form-control reken-trigger" placeholder="Vertrekadres" autocomplete="off">
                        <input type="number" name="km[t_vertrek_klant]" class="form-control km-calc reken-trigger" value="0">
                        <select class="form-control km-zone-select reken-trigger" title="Fiscale zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select>
                    </div>
                    <div class="rit-row" id="row_voorstaan">
                        <input type="hidden" name="time[t_voorstaan]" id="time_t_voorstaan" value="">
                        <input type="text" name="addr[t_voorstaan]" id="addr_t_voorstaan" class="form-control reken-trigger" placeholder="Eerste grens" autocomplete="off">
                        <input type="number" name="km[t_voorstaan]" class="form-control km-calc reken-trigger" value="0">
                        <select class="form-control km-zone-select reken-trigger"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select>
                    </div>
                    <div class="rit-row rit-row-check-only" id="row_chk_grens2_wrap">
                        <input type="checkbox" id="chk_grens2" value="1">
                        <label for="chk_grens2">Tweede grens</label>
                    </div>
                    <div class="rit-row" id="row_grens2" style="display:none;">
                        <input type="hidden" name="time[t_grens2]" id="time_t_grens2" value="">
                        <input type="text" name="addr[t_grens2]" id="addr_t_grens2" class="form-control reken-trigger" placeholder="2e grens" autocomplete="off">
                        <input type="number" name="km[t_grens2]" class="form-control km-calc reken-trigger" value="0">
                        <select class="form-control km-zone-select reken-trigger"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select>
                    </div>
                    <div class="rit-row" id="row_aankomst_best">
                        <input type="text" name="time[t_aankomst_best]" id="time_t_aankomst_best" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly>
                        <input type="text" name="addr[t_aankomst_best]" id="addr_t_aankomst_best" class="form-control reken-trigger" placeholder="Bestemming" autocomplete="off">
                        <input type="number" name="km[t_aankomst_best]" class="form-control km-calc reken-trigger" value="0">
                        <select class="form-control km-zone-select reken-trigger"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select>
                    </div>
                    <div class="rit-row" id="row_retour_garage_heen" style="display:none;">
                        <input type="text" name="time[t_retour_garage_heen]" id="time_t_retour_garage_heen" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly>
                        <input type="text" name="addr[t_retour_garage_heen]" id="addr_t_retour_garage_heen" class="form-control reken-trigger" value="Industrieweg 95, Zutphen" placeholder="Garage..." autocomplete="off">
                        <input type="number" name="km[t_retour_garage_heen]" class="form-control km-calc reken-trigger" value="0">
                        <select class="form-control km-zone-select reken-trigger"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select>
                    </div>
                </div>

                <div id="wrap_extra_rijdag" class="route-compact" style="margin-top:10px;padding:6px 10px;border:1px solid #eee;background:#fdfdfd;border-radius:4px;">
                    <div class="rit-row tz-toggle-row">
                        <label class="rit-row-check-only" style="display:flex;align-items:center;gap:8px;width:100%;margin:0;">
                            <input type="checkbox" name="tussendagen_enabled" id="tussendagen_enabled" value="1">
                            <span style="font-size:11px;font-weight:700;color:#003366;">Extra rijdag</span>
                        </label>
                    </div>
                    <div id="block_tussendagen_inner" style="display:none;">
                        <div id="tussendagen_rows"></div>
                        <button type="button" id="btn_tz_add">+ regel</button>
                    </div>
                </div>

                <div id="terugreis_gate_bar" class="terugreis-gate-bar" style="display:none;">
                    <button type="button" id="btn_show_terugreis" class="btn-terugreis-open" title="Terugreis / rit 2 tonen">+ Rit 2 · terugreis</button>
                </div>

                <div id="block_terug" style="margin-top:20px;display:none;">
                    <div class="header-rit-2" id="header_terug">TERUGREIS / RIT 2</div>
                    <div class="route-compact" style="background: #fdfdfd; padding: 8px 10px; border: 1px solid #eee; border-top:none; border-radius: 0 0 4px 4px;">
                        
                        <div class="rit-row" id="row_garage_rit2" style="display:none; background:#f9f9f9; padding:5px; margin-bottom:10px; border-radius:4px;">
                            <div class="col-tijd"><label>Start Rit 2</label><input type="text" name="time[t_garage_rit2]" id="time_t_garage_rit2" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly></div>
                            <div class="col-adres"><label>Garage Start (Rit 2)</label><input type="text" name="addr[t_garage_rit2]" id="addr_t_garage_rit2" class="form-control google-autocomplete" value="Industrieweg 95, Zutphen" placeholder="Garage..."></div>
                        </div>

                        <div class="rit-row" id="row_voorstaan_rit2" style="display:none;">
                            <div class="col-tijd"><label>Voorstaan</label><input type="text" name="time[t_voorstaan_rit2]" id="time_t_voorstaan_rit2" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly></div>
                            <div class="col-adres"><label>Voorrijden Retour (Leeg)</label><input type="text" name="addr[t_voorstaan_rit2]" id="addr_t_voorstaan_rit2" class="form-control google-autocomplete" placeholder="Locatie..."></div>
                            <div class="col-km"><label>Km</label><input type="number" name="km[t_voorstaan_rit2]" class="form-control km-calc reken-trigger" value="0"></div>
                            <div class="col-zone"><label>Zone</label><select class="form-control km-zone-select reken-trigger" title="Fiscale zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></div>
                        </div>

                        <div class="rit-row" id="row_vertrek_best">
                            <div class="col-tijd"><label>Vertrek</label><input type="text" name="time[t_vertrek_best]" id="time_t_vertrek_best" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly></div>
                            <div class="col-adres"><label id="label_vertrek_terug">Vertrek Bestemming</label><input type="text" name="addr[t_vertrek_best]" id="addr_t_vertrek_best" class="form-control google-autocomplete" placeholder="Startpunt..."></div>
                            <div class="col-km"><label>Km</label><input type="number" name="km[t_vertrek_best]" class="form-control km-calc reken-trigger" value="0"></div>
                            <div class="col-zone"><label>Zone</label><select class="form-control km-zone-select reken-trigger" title="Fiscale zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></div>
                        </div>
                        
                        <div class="rit-row" id="row_retour_klant">
                            <div class="col-tijd"><label>Aankomst</label><input type="text" name="time[t_retour_klant]" id="time_t_retour_klant" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly></div>
                            <div class="col-adres"><label>Uitstap Klant</label><input type="text" name="addr[t_retour_klant]" id="addr_t_retour_klant" class="form-control google-autocomplete" placeholder="Afzetadres..."></div>
                            <div class="col-km"><label>Km</label><input type="number" name="km[t_retour_klant]" class="form-control km-calc reken-trigger" value="0"></div>
                            <div class="col-zone"><label>Zone</label><select class="form-control km-zone-select reken-trigger" title="Fiscale zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></div>
                        </div>

                        <div class="rit-row" id="row_garage_terug" style="border-top:1px dashed #ccc; padding-top:10px;">
                            <div class="col-tijd"><label>Einde Rit</label><input type="text" name="time[t_retour_garage]" id="time_t_retour_garage" class="form-control custom-time-input reken-trigger" placeholder="--:--" readonly></div>
                            <div class="col-adres"><label>Garage Retour (Einde)</label><input type="text" name="addr[t_retour_garage]" id="addr_t_retour_garage" class="form-control google-autocomplete" value="Industrieweg 95, Zutphen" placeholder="Garage..."></div>
                            <div class="col-km"><label>Km</label><input type="number" name="km[t_retour_garage]" class="form-control km-calc reken-trigger" value="0"></div>
                            <div class="col-zone"><label>Zone</label><select class="form-control km-zone-select reken-trigger" title="Fiscale zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></div>
                        </div>
                    </div>
                </div>

                <div id="block_meerdaags" style="display:none; margin-top:12px; padding:8px 10px; background:#e3f2fd; border:1px solid #90caf9; border-radius:4px;">
                    <div style="font-weight:bold; color:#0d47a1; font-size:12px; margin-bottom:4px;"><i class="fas fa-percentage"></i> Fiscaal km · NL 9% · DE 19% · CH/overig 0%</div>
                    <p style="font-size:10px;color:#1565c0;margin:0 0 8px;">Automatisch uit zone-keuzes per segment en extra rijdagen. CAO-uren meerdaags: zie handleiding; niet automatisch.</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                        <div style="width:62px;"><label style="font-size:10px;">Σ extra</label><input type="number" name="km_tussen" id="km_tussen" class="form-control" readonly style="background:#f0f0f0;font-weight:bold;font-size:13px;padding:4px;" value="0" step="0.1"></div>
                        <div style="width:62px;"><label style="font-size:10px;">NL</label><input type="number" name="km_nl" id="km_nl" class="form-control" readonly style="background:#eef6ff;font-size:13px;padding:4px;" value="0" step="0.1"></div>
                        <div style="width:62px;"><label style="font-size:10px;">DE</label><input type="number" name="km_de" id="km_de" class="form-control" readonly style="background:#eef6ff;font-size:13px;padding:4px;" value="0" step="0.1"></div>
                        <div style="width:62px;"><label style="font-size:10px;">CH</label><input type="number" name="km_ch" id="km_ch" class="form-control" readonly style="background:#f7f7f7;font-size:13px;padding:4px;" value="0" step="0.1"></div>
                        <div style="width:62px;"><label style="font-size:10px;">0%</label><input type="number" name="km_ov" id="km_ov" class="form-control" readonly style="background:#f7f7f7;font-size:13px;padding:4px;" value="0" step="0.1"></div>
                        <div style="flex:1; min-width:160px;"><label style="font-size:10px;">Check Δ</label><input type="text" id="fiscal_check" class="form-control" disabled style="background:#eee;font-size:12px;padding:4px;"></div>
                    </div>
                </div>

                <div id="block_buitenland_extra">
                    <strong style="color:#0f766e;">Buitenland — extra</strong>
                    <div class="form-grid-4" style="margin-top:10px;">
                        <div style="grid-column: span 2;">
                            <label>Overnachting</label>
                            <select name="buitenland_overnachting" id="buitenland_overnachting" class="form-control">
                                <option value="klant">Door klant</option>
                                <option value="eigen">Door ons</option>
                            </select>
                        </div>
                        <div style="grid-column: span 2;">
                            <label>€ indicatie (alleen „door ons”)</label>
                            <input type="text" name="buitenland_overnachting_bedrag" id="buitenland_overnachting_bedrag" class="form-control" placeholder="—">
                        </div>
                    </div>
                    <p style="margin:10px 0 6px;font-size:12px;color:#444;">Dagprogramma per kalenderdag (tussen vertrek- en einddatum):</p>
                    <div id="dagprogramma_container"></div>
                </div>

            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #ffc107; background-color: #fffdf5;"> 
            <div class="box-header" style="background-color: transparent; border-bottom: 1px dashed #ffeeba;">
                <h3 class="box-title" style="color:#856404;"><i class="fas fa-exclamation-triangle"></i> Bijzonderheden / Instructies</h3>
                <span style="font-size: 11px; color: #856404; font-weight: bold;">(Zichtbaar op Offerte & Chauffeurs App)</span>
            </div> 
            <div class="box-body" style="padding-top: 10px;">
                <label style="color: #856404;">Heeft de klant speciale wensen? (Bijv: Rolstoel, parkeren, extra bagage, verrassingsrit)</label>
                <textarea name="instructie_kantoor" class="form-control" rows="3" style="height: auto; border-color: #ffc107;" placeholder="Typ hier de instructies of wensen van de klant..."></textarea>
            </div>
        </div>

        <div class="section-box" style="border-top: 4px solid #dc3545;"> 
            <div class="box-header" style="background: #fcf1f2;"><h3 class="box-title" style="color:#dc3545;"><i class="fas fa-euro-sign"></i> Financieel & Vervoer</h3></div> 
            <div class="box-body">
                
                <div style="display:flex; gap:15px; align-items: flex-end; margin-bottom: 10px;"> 
                    <div style="flex:2;"> 
                        <label style="color:#dc3545;">1. Hoofdvoertuig (Prijs per KM)</label> 
                        <select name="voertuig_id" id="bus_select" class="form-control reken-trigger bus-select-class" style="font-weight:bold; border-color:#dc3545;"> 
                            <?= $busOptiesHTML ?>
                        </select> 
                    </div> 
                    <div style="flex:1;"><label>Totaal KM</label><input type="text" id="total_km" name="total_km" class="form-control" readonly style="background:#eee; font-weight:bold;"></div> 
                    <div style="flex:1;"><label>Totaal Uren (Wordt nu goed opgeteld!)</label><input type="text" id="total_uren" name="total_uren" class="form-control" readonly style="background:#eee; font-weight:bold;"></div> 
                </div> 

                <div id="extra_bussen_container"></div>
                
                <button type="button" class="btn-add-bus" onclick="voegExtraBusToe()">
                    <i class="fas fa-plus"></i> Extra bus toevoegen aan deze calculatie
                </button>
                <span style="font-size:11px; color:#888; margin-left: 10px;">Elke bus berekent zijn eigen KM-prijs + het uurloon van een chauffeur!</span>
                
                <div class="profit-grid"> 
                    <div class="profit-box"> 
                        <div style="font-size:12px; color:#666;">TOTAAL KOSTPRIJS</div> 
                        <div id="display_kost" class="profit-value">€ 0,00</div> 
                    </div> 
                    <div class="profit-box" style="background:#e3f2fd; border:1px solid #90caf9;"> 
                        <div style="font-size:12px; color:#0056b3; font-weight:bold;">VERKOOPPRIJS (EXCL. BTW)</div> 
                        <input type="number" step="0.01" name="verkoopprijs" id="verkoopprijs" class="form-control" style="font-size:20px; text-align:center; margin-top:5px; border:2px solid #0056b3;" value="0"> 
                        <div id="display_incl_btw" style="font-size:12px; margin-top:5px; font-weight:bold; color:#d97706;">Incl. 9% BTW: € 0,00</div>
                    </div> 
                    <div class="profit-box"> 
                        <div style="font-size:12px; color:#666;">WINST MARGE</div> 
                        <div id="display_winst" class="profit-value">€ 0,00</div> 
                        <div id="display_perc" style="font-size:11px;">0%</div> 
                    </div> 
                </div> 
            </div>
        </div> 

        <button type="submit" class="btn-save"><i class="fas fa-save"></i> RIT OPSLAAN & TERUG NAAR OVERZICHT</button>
        <p class="calculatie-ui-build">BusAI calculatie · bijgewerkt: <?= $uiBuildLabel ?></p>
    </form> 
</div> 

<div id="timeModal" class="modal-overlay">
    <div class="modal-content" style="width: 340px;">
        <div class="modal-header"><span>Kies Tijdstip</span><span class="close-btn" id="closeModalBtn" style="cursor:pointer;">&times;</span></div>
        <div id="modalGrid" class="time-grid"></div>
    </div>
</div>

<div id="nieuweKlantModal" class="modal-overlay">
    <div class="modal-content" style="width: 500px; margin: 5vh auto;">
        <div class="modal-header" style="background: #28a745;"><span>➕ Nieuwe Klant Aanmaken</span><span class="close-btn" onclick="sluitKlantModal()" style="cursor:pointer; font-size: 20px;">&times;</span></div>
        <div class="modal-body" style="padding: 20px;">
            <form id="formNieuweKlant">
                <label>Bedrijfsnaam / School</label><input type="text" name="bedrijfsnaam" id="nk_bedrijf" class="form-control">
                <div class="form-grid-2" style="margin-top:10px;"><div><label>Voornaam</label><input type="text" name="voornaam" id="nk_voornaam" class="form-control"></div><div><label>Achternaam</label><input type="text" name="achternaam" id="nk_achternaam" class="form-control"></div></div>
                <label style="margin-top:10px;">Adres</label><input type="text" name="adres" id="nk_adres" class="form-control google-autocomplete">
                <label style="margin-top:10px;">Plaats</label><input type="text" name="plaats" id="nk_plaats" class="form-control">
                <div class="form-grid-2" style="margin-top:10px;"><div><label>Telefoonnummer</label><input type="text" name="telefoon" id="nk_telefoon" class="form-control"></div><div><label>E-mailadres</label><input type="email" name="email" id="nk_email" class="form-control"></div></div>
                <button type="button" onclick="slaNieuweKlantOp()" class="btn-save" style="margin-top:20px; background:#003366;">💾 Klant Opslaan</button>
            </form>
        </div>
    </div>
</div>

<script>
// --- LOGICA VOOR MEERDERE BUSSEN ---
const HTML_BUS_OPTIES = `<?= $busOptiesHTML ?>`;
let extraBusTeller = 1;

function voegExtraBusToe() {
    extraBusTeller++;
    const container = document.getElementById('extra_bussen_container');
    
    const div = document.createElement('div');
    div.className = 'extra-bus-row';
    div.id = 'extra_bus_rij_' + extraBusTeller;
    
    div.innerHTML = `
        <div style="flex:1;">
            <label style="color:#003366; font-size:12px;">Extra Bus Toevoegen</label>
            <select name="bus_extra_${extraBusTeller}" class="form-control reken-trigger bus-select-class" style="margin:0; border-color:#003366;">
                ${HTML_BUS_OPTIES}
            </select>
        </div>
        <button type="button" class="btn-remove-bus" onclick="verwijderExtraBus(${extraBusTeller})" style="margin-top: 15px;"><i class="fas fa-trash"></i></button>
    `;
    
    container.appendChild(div);
    
    const newSelect = div.querySelector('.bus-select-class');
    newSelect.addEventListener('change', () => { userManuallyChangedPrice = false; window.rekenen(); });
    
    window.rekenen();
}

function verwijderExtraBus(id) {
    document.getElementById('extra_bus_rij_' + id).remove();
    window.rekenen();
}

// --- ORIGINELE KLANT/DATUM JAVASCRIPT ---
function sluitKlantModal() { document.getElementById('nieuweKlantModal').style.display = 'none'; }
function openNieuweKlantModal() { document.getElementById('klant_resultaten_lijst').style.display = 'none'; document.getElementById('nieuweKlantModal').style.display = 'block'; let getypt = document.getElementById('klant_zoek_input').value; document.getElementById('nk_bedrijf').value = getypt; }
function slaNieuweKlantOp() { let form = document.getElementById('formNieuweKlant'); let formData = new FormData(form); fetch('../ajax_nieuwe_klant.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => { if(data.success) { sluitKlantModal(); document.getElementById('klant_zoek_input').style.display = 'none'; document.getElementById('klant_id_hidden').value = data.klant.id; document.getElementById('c_naam').innerText = data.klant.weergave_naam; document.getElementById('c_adres').innerText = data.klant.adres; document.getElementById('c_plaats').innerText = data.klant.plaats; document.getElementById('c_tel').innerText = data.klant.telefoon; document.getElementById('c_email').innerText = data.klant.email ? ' | ' + data.klant.email : ''; document.getElementById('klant_info_card').style.display = 'block'; let va=(data.klant.adres||'')+', '+(data.klant.plaats||''); if(va.length>2&&va!==', '){ let vx=document.getElementById('addr_t_vertrek_klant'), rx=document.getElementById('addr_t_retour_klant'); if(vx)vx.value=va; if(rx)rx.value=va;} if (typeof window.routeHeenRefreshFromLegacy === 'function') window.routeHeenRefreshFromLegacy(); form.reset(); let contactSelect = document.getElementById('contact_select'); if(contactSelect) contactSelect.innerHTML = '<option value="0">-- Algemeen --</option>'; let afdelingSelect = document.getElementById('afdeling_select'); if(afdelingSelect) afdelingSelect.innerHTML = '<option value="0">-- Geen afdeling --</option>'; } else { alert('❌ Fout: ' + data.message); } }).catch(err => { console.error(err); alert('Er ging iets mis met het opslaan. Controleer de verbinding.'); }); }
document.addEventListener('DOMContentLoaded', function() { const input = document.getElementById('klant_zoek_input'); const list = document.getElementById('klant_resultaten_lijst'); const form = document.getElementById('hoofdFormulier'); const vertrekDatumVeld = document.getElementById('rit_datum'); const eindDatumVeld = document.getElementById('rit_datum_eind'); if (vertrekDatumVeld && eindDatumVeld) { vertrekDatumVeld.addEventListener('change', function() { eindDatumVeld.value = this.value; }); } if(form) { form.addEventListener('submit', function(e) { const klantId = document.getElementById('klant_id_hidden').value; if(!klantId || klantId === "0" || klantId === "") { e.preventDefault(); alert("🛑 STOP: Je moet eerst een klant zoeken (of nieuw aanmaken) voordat je kunt opslaan!"); if(input) input.focus(); } }); } if(input) { input.addEventListener('keyup', function() { let query = this.value; if(query.length < 2) { list.style.display = 'none'; return; } fetch('../ajax_zoek_klant.php?q=' + encodeURIComponent(query)).then(response => response.json()).then(data => { list.innerHTML = ''; list.style.display = 'block'; if(data.length > 0) { data.forEach(klant => { let div = document.createElement('div'); div.style.padding = '8px 12px'; div.style.cursor = 'pointer'; div.style.borderBottom = '1px solid #eee'; div.onmouseover = function() { this.style.backgroundColor = '#f0f8ff'; }; div.onmouseout = function() { this.style.backgroundColor = '#fff'; }; div.innerHTML = `<strong>${klant.weergave_naam}</strong> <span style='font-size:11px; color:#888;'>(${klant.plaats})</span>`; div.onclick = function() { input.style.display = 'none'; document.getElementById('klant_id_hidden').value = klant.id; document.getElementById('c_naam').innerText = klant.weergave_naam; document.getElementById('c_adres').innerText = klant.adres || ''; document.getElementById('c_plaats').innerText = klant.plaats || ''; document.getElementById('c_tel').innerText = klant.telefoon || ''; document.getElementById('c_email').innerText = klant.email ? ' | ' + klant.email : ''; document.getElementById('klant_info_card').style.display = 'block'; list.style.display = 'none'; let contactSelect = document.getElementById('contact_select'); if(contactSelect) { contactSelect.innerHTML = '<option value="0">-- Algemeen --</option>'; fetch('../ajax_get_contacten.php?klant_id=' + klant.id).then(response => response.json()).then(contacten => { if(contacten && contacten.length > 0) { contacten.forEach(c => { let opt = document.createElement('option'); opt.value = c.id; opt.innerHTML = c.voornaam + ' ' + (c.achternaam || ''); contactSelect.appendChild(opt); }); } }); } let afdelingSelect = document.getElementById('afdeling_select'); if(afdelingSelect) { afdelingSelect.innerHTML = '<option value="0">-- Geen afdeling --</option>'; fetch('../ajax_get_afdelingen.php?klant_id=' + klant.id).then(response => response.json()).then(afdelingen => { if(afdelingen && afdelingen.length > 0) { afdelingen.forEach(a => { let opt = document.createElement('option'); opt.value = a.id; opt.innerHTML = a.naam; afdelingSelect.appendChild(opt); }); } }); } let volledigAdres = (klant.adres || '') + ', ' + (klant.plaats || ''); if (volledigAdres.length > 2 && volledigAdres !== ', ') { let veldVertrek = document.getElementById('addr_t_vertrek_klant'); let veldRetour = document.getElementById('addr_t_retour_klant'); if(veldVertrek) veldVertrek.value = volledigAdres; if(veldRetour) veldRetour.value = volledigAdres; } if (typeof window.routeHeenRefreshFromLegacy === 'function') window.routeHeenRefreshFromLegacy(); }; list.appendChild(div); }); } else { list.innerHTML = `<div style="padding: 15px; text-align: center; color: #dc3545; border-bottom: 1px solid #eee;"><strong>❌ Klant niet gevonden in database</strong></div><div style="padding: 12px; background: #f8f9fa; text-align: center; cursor: pointer;" onmouseover="this.style.backgroundColor='#e2e6ea'" onmouseout="this.style.backgroundColor='#f8f9fa'" onclick="openNieuweKlantModal()"><strong style="color: #0056b3; font-size: 15px;">➕ Nieuwe Klant Aanmaken</strong></div>`; } }); }); } let infoCard = document.getElementById('klant_info_card'); if(infoCard) { infoCard.onclick = function() { if(confirm('Andere klant zoeken?')) { if(input) { input.value = ''; input.style.display = 'block'; input.focus(); } document.getElementById('klant_id_hidden').value = ''; this.style.display = 'none'; let contactSelect = document.getElementById('contact_select'); if(contactSelect) contactSelect.innerHTML = '<option value="0">-- Algemeen --</option>'; let afdelingSelect = document.getElementById('afdeling_select'); if(afdelingSelect) afdelingSelect.innerHTML = '<option value="0">-- Geen afdeling --</option>'; } }; }
});
</script>

<script>
    const SERVER_DATA = { uurloon: <?= floatval($chauffeur_uurloon) ?>, contact_id: 0 };
</script>

<script>
window.HTML_BUS_TUSSENDAG = <?= json_encode($busOptiesTussendagHTML ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
(function () {
    function bindPlaces(el) {
        if (!window.google || !google.maps || !google.maps.places) return;
        try {
            const ac = new google.maps.places.Autocomplete(el, { componentRestrictions: { country: ['nl', 'de', 'be', 'at', 'fr'] } });
            ac.addListener('place_changed', function () {
                const row = el.closest && el.closest('.tz-row');
                if (row && typeof window.calculateTussendagenKm === 'function') {
                    window.calculateTussendagenKm(row);
                }
                if (typeof window.calculateRoute === 'function') window.calculateRoute();
            });
        } catch (e) {}
    }
    function wireRow(div) {
        div.querySelectorAll('.google-autocomplete').forEach(bindPlaces);
        div.querySelectorAll('.reken-trigger').forEach(function (el) {
            function trig() {
                if (typeof window.userManuallyChangedPrice !== 'undefined') userManuallyChangedPrice = false;
                if (typeof window.rekenen === 'function') window.rekenen();
            }
            el.addEventListener('input', trig);
            el.addEventListener('change', trig);
        });
        const rm = div.querySelector('.btn-remove-bus');
        if (rm) rm.addEventListener('click', function () {
            div.remove();
            if (typeof window.rekenen === 'function') window.rekenen();
        });
    }
    function addTzRow(prefill) {
        const rows = document.getElementById('tussendagen_rows');
        if (!rows) return;
        const p = prefill || {};
        if (p.bus == null && p.voertuig_id != null) {
            p.bus = p.voertuig_id;
        }
        const div = document.createElement('div');
        div.className = 'rit-row tz-row';
        div.innerHTML =
            '<div class="tz-col-datum"><label style="font-size:11px;">Datum</label><input type="date" name="tussendagen_datum[]" class="form-control reken-trigger" title="Datum"></div>' +
            '<div class="tz-col-tijd"><label style="font-size:11px;">Tijd</label><input type="time" name="tussendagen_tijd[]" class="form-control reken-trigger" title="Vertrek"></div>' +
            '<div class="col-adres"><label style="font-size:11px;">Van</label><input type="text" name="tussendagen_van[]" class="form-control google-autocomplete" placeholder="Van"></div>' +
            '<div class="col-adres"><label style="font-size:11px;">Naar</label><input type="text" name="tussendagen_naar[]" class="form-control google-autocomplete" placeholder="Naar"></div>' +
            '<div class="col-km"><label style="font-size:11px;">Km</label><input type="number" name="tussendagen_km[]" class="form-control km-calc reken-trigger tz-km" step="0.1" min="0" title="Km"></div>' +
            '<div class="col-zone"><label style="font-size:11px;">Zone</label><select name="tussendagen_zone[]" class="form-control km-zone-select reken-trigger" title="Zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></div>' +
            '<input type="hidden" name="tussendagen_pax[]" value="0">' +
            '<input type="hidden" name="tussendagen_bus[]" value="">' +
            '<button type="button" class="btn-remove-bus" title="Verwijder">&times;</button>';
        const dt = div.querySelector('input[type="date"]');
        if (dt && p.datum) dt.value = p.datum;
        const tm = div.querySelector('input[type="time"]');
        if (tm && p.tijd) {
            let t = String(p.tijd).trim();
            if (/^\d{2}:\d{2}:\d{2}$/.test(t)) {
                t = t.slice(0, 5);
            }
            tm.value = t;
        }
        const kmEl = div.querySelector('.tz-km');
        if (p.km != null && kmEl) kmEl.value = String(p.km);
        const vans = div.querySelectorAll('.google-autocomplete');
        if (vans[0] && p.van) vans[0].value = p.van;
        if (vans[1] && p.naar) vans[1].value = p.naar;
        const zSel = div.querySelector('.km-zone-select');
        if (zSel && p.zone) zSel.value = String(p.zone);
        wireRow(div);
        rows.appendChild(div);
        setTimeout(function () {
            if (typeof window.calculateTussendagenKm === 'function') window.calculateTussendagenKm(div);
        }, 300);
        if (typeof window.rekenen === 'function') window.rekenen();
    }

    function rebuildDagprogrammaBL() {
        const rt = document.getElementById('rittype_select');
        const box = document.getElementById('dagprogramma_container');
        if (!rt || !box || rt.value !== 'buitenland') return;
        const startEl = document.getElementById('rit_datum');
        const endEl = document.getElementById('rit_datum_eind');
        if (!startEl || !endEl) return;
        const start = startEl.value;
        const end = endEl.value;
        const oldVal = {};
        box.querySelectorAll('[data-dag-datum]').forEach(function (ta) {
            oldVal[ta.getAttribute('data-dag-datum')] = ta.value;
        });
        const bootDp = (typeof window.CALC_BUITENLAND_DP === 'object' && window.CALC_BUITENLAND_DP) ? window.CALC_BUITENLAND_DP : {};
        Object.keys(bootDp).forEach(function (dk) {
            if (oldVal[dk] === undefined) {
                oldVal[dk] = bootDp[dk];
            }
        });
        box.innerHTML = '';
        if (!start || !end || end < start) return;
        let cur = start;
        let guard = 0;
        while (cur <= end && guard < 400) {
            const wrap = document.createElement('div');
            wrap.style.marginBottom = '10px';
            const lbl = document.createElement('div');
            lbl.style.fontSize = '11px';
            lbl.style.fontWeight = '700';
            lbl.style.color = '#0f766e';
            lbl.textContent = cur;
            const ta = document.createElement('textarea');
            ta.name = 'dagprogramma[' + cur + ']';
            ta.className = 'form-control';
            ta.rows = 2;
            ta.setAttribute('data-dag-datum', cur);
            ta.placeholder = 'Programma (optioneel)';
            if (oldVal[cur]) ta.value = oldVal[cur];
            wrap.appendChild(lbl);
            wrap.appendChild(ta);
            box.appendChild(wrap);
            const d = new Date(cur + 'T12:00:00');
            d.setDate(d.getDate() + 1);
            cur = d.toISOString().slice(0, 10);
            guard++;
        }
    }
    window.rebuildDagprogrammaBL = rebuildDagprogrammaBL;

    window.calculatieExtrasAfterInit = function () {
        const cb = document.getElementById('tussendagen_enabled');
        const inner = document.getElementById('block_tussendagen_inner');
        const tzRows = document.getElementById('tussendagen_rows');
        const wrap = document.getElementById('wrap_extra_rijdag');
        const rt = document.getElementById('rittype_select');
        const startEl = document.getElementById('rit_datum');
        const endEl = document.getElementById('rit_datum_eind');
        function allowTz() {
            const type = rt ? rt.value : '';
            if (type === 'meerdaags' || type === 'buitenland') return true;
            return !!(startEl && endEl && startEl.value && endEl.value && endEl.value > startEl.value);
        }
        function syncTz() {
            if (!inner) return;
            const allow = allowTz();
            if (wrap) wrap.style.display = allow ? '' : 'none';
            if (!allow && cb) cb.checked = false;
            const on = cb && cb.checked;
            inner.style.display = on ? 'block' : 'none';
            if (on && tzRows && tzRows.children.length === 0) {
                addTzRow({});
            }
        }
        if (cb) {
            cb.addEventListener('change', syncTz);
            syncTz();
        }
        const btnAdd = document.getElementById('btn_tz_add');
        if (btnAdd) btnAdd.addEventListener('click', function () { addTzRow({}); });

        function syncBuiten() {
            const bl = document.getElementById('block_buitenland_extra');
            if (!bl || !rt) return;
            bl.style.display = rt.value === 'buitenland' ? 'block' : 'none';
            rebuildDagprogrammaBL();
            syncTz();
        }
        if (rt) {
            rt.addEventListener('change', syncBuiten);
            syncBuiten();
        }
        document.getElementById('rit_datum')?.addEventListener('change', function () {
            const e = document.getElementById('rit_datum_eind');
            if (e && e.value < this.value) e.value = this.value;
            rebuildDagprogrammaBL();
            syncTz();
        });
        document.getElementById('rit_datum_eind')?.addEventListener('change', function () {
            rebuildDagprogrammaBL();
            syncTz();
        });
    };
})();
</script>

<script src="js/route_heen_segmenten.js?v=<?= time() ?>"></script>
<script src="rekenmachine.js?v=<?= time() ?>"></script> 

<script>
setTimeout(() => {
    if(typeof window.rekenen === 'function') {
        window.oudeRekenen = window.rekenen;
    }

    window.rekenen = function() {
        if(typeof window.oudeRekenen === 'function') {
            window.oudeRekenen();
        }
        customFinancieleBerekening();
    };
    
    const hoofdBus = document.getElementById('bus_select');
    if(hoofdBus) {
        hoofdBus.addEventListener('change', () => { userManuallyChangedPrice = false; window.rekenen(); });
    }
    
    const verkoopprijsVeld = document.getElementById('verkoopprijs');
    if(verkoopprijsVeld) {
        verkoopprijsVeld.addEventListener('input', () => {
            userManuallyChangedPrice = true;
            customFinancieleBerekening();
        });
    }
}, 500);

function customFinancieleBerekening() {
    const type = document.getElementById('rittype_select').value;
    let totaalKm = parseFloat(document.getElementById('total_km').value) || 0;
    let uren = parseFloat(document.getElementById('total_uren').value) || 0;
    const LOON = (typeof SERVER_DATA !== 'undefined') ? SERVER_DATA.uurloon : 35.00;
    
    let totaleKostprijs = 0;

    document.querySelectorAll('.bus-select-class').forEach(selectElement => {
        if(selectElement && selectElement.selectedIndex > 0) {
            let kmPrijs = parseFloat(selectElement.options[selectElement.selectedIndex].dataset.km) || 0;
            let kostenDezeBus = (totaalKm * kmPrijs) + (uren * LOON);
            totaleKostprijs += kostenDezeBus;
        }
    });

    if(document.getElementById('display_kost')) 
        document.getElementById('display_kost').innerText = "€ " + totaleKostprijs.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const prijsInVeld = document.getElementById('verkoopprijs');
    if(!prijsInVeld) return;
    
    let prijsEx = 0;
    let prijsIn = 0;
    
    if(!userManuallyChangedPrice && totaleKostprijs > 0) {
        let marge = (type === 'meerdaags' || type === 'buitenland') ? 1.35 : 1.25;
        let berekendEx = totaleKostprijs * marge;
        let berekendIn = berekendEx * 1.09; 
        
        // Afronden op 5 euro inclusief BTW (Zoals je origineel had)
        prijsIn = Math.ceil(berekendIn / 5) * 5; 
        
        // Reken strak terug naar Excl. BTW voor de database
        prijsEx = prijsIn / 1.09; 
        prijsInVeld.value = prijsEx.toFixed(2);
    } else {
        // Gebruiker heeft zelf een EXCLUSIEF bedrag getypt
        prijsEx = parseFloat(prijsInVeld.value) || 0;
        prijsIn = prijsEx * 1.09;
    }
    
    let winst = prijsEx - totaleKostprijs;
    
    if(document.getElementById('display_incl_btw'))
        document.getElementById('display_incl_btw').innerText = "Incl. 9% BTW: € " + prijsIn.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    const dWinst = document.getElementById('display_winst');
    if(dWinst) {
        dWinst.innerText = "€ " + winst.toLocaleString('nl-NL', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        dWinst.style.color = winst >= 0 ? '#28a745' : '#dc3545';
    }
    if(prijsEx > 0 && document.getElementById('display_perc')) {
        const perc = (winst / prijsEx) * 100;
        document.getElementById('display_perc').innerText = perc.toFixed(1) + "%";
    }
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(env_value('GOOGLE_MAPS_API_KEY', '')); ?>&libraries=places&callback=startHetSysteem" async defer></script>