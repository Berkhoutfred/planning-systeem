<?php
// Bestand: beheer/calculaties.php
// Versie: 9.5 - Slimme Filters, Bussen Calculatie & Opvolg-Mails

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once __DIR__ . '/includes/sales_rit_dossiers.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("Tenant context ontbreekt. Controleer login/tenant configuratie.");
}

// --- CHAUFFEURS OPHALEN (Behouden voor veiligheid) ---
try {
    $stmtChauffeurs = $pdo->prepare("SELECT id, voornaam, achternaam FROM chauffeurs WHERE tenant_id = ? ORDER BY voornaam ASC");
    $stmtChauffeurs->execute([$tenantId]);
    $chauffeurs = $stmtChauffeurs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database fout bij ophalen chauffeurs: " . $e->getMessage());
}

// --- 1. ACTIE VANGER ---
if (isset($_GET['do_actie'], $_GET['sales_dossier_id'])) {
    sales_rit_dossiers_ensure_schema($pdo);
    $actie = (string) $_GET['do_actie'];
    $sid = (int) $_GET['sales_dossier_id'];
    try {
        if ($sid <= 0) {
            throw new InvalidArgumentException('Ongeldig dossier.');
        }
        if ($actie === 'verwijder_sales') {
            $pdo->prepare('DELETE FROM sales_rit_dossiers WHERE id = ? AND tenant_id = ?')->execute([$sid, $tenantId]);
            $msg = 'Sales-dossier verwijderd (ritten blijven op het planbord).';
        } elseif ($actie === 'archiveer_sales') {
            $pdo->prepare("UPDATE sales_rit_dossiers SET status = 'gearchiveerd' WHERE id = ? AND tenant_id = ?")->execute([$sid, $tenantId]);
            $msg = 'Sales-dossier gearchiveerd.';
        } elseif ($actie === 'herstel_sales') {
            $pdo->prepare("UPDATE sales_rit_dossiers SET status = 'open', afwijzings_reden = NULL WHERE id = ? AND tenant_id = ?")->execute([$sid, $tenantId]);
            $msg = 'Sales-dossier weer actief.';
        } else {
            throw new InvalidArgumentException('Onbekende actie.');
        }
    } catch (Throwable $e) {
        die("<div style='padding:20px; background:#dc3545; color:white; font-weight:bold;'>🛑 DATABASE FOUT: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>");
    }
    $m = $_GET['maand'] ?? date('n');
    $j = $_GET['jaar'] ?? date('Y');
    $v = $_GET['view'] ?? 'actief';
    header('Location: calculaties.php?maand=' . urlencode((string) $m) . '&jaar=' . urlencode((string) $j) . '&view=' . urlencode((string) $v) . '&actie_msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['do_actie']) && isset($_GET['rit_id'])) {
    $actie = $_GET['do_actie'];
    $rit_id = intval($_GET['rit_id']);
    
    try {
        if ($actie === 'verwijder') {
            $pdo->prepare("DELETE FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
            $pdo->prepare("DELETE FROM calculaties WHERE id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
            $msg = "Rit definitief verwijderd.";
        } elseif ($actie === 'archiveer') {
            $pdo->prepare("UPDATE calculaties SET status = 'gearchiveerd' WHERE id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
            $msg = "Rit verplaatst naar archief.";
        } elseif ($actie === 'herstel') {
            $pdo->prepare("UPDATE calculaties SET status = 'concept', afwijzings_reden = NULL WHERE id = ? AND tenant_id = ?")->execute([$rit_id, $tenantId]);
            $msg = "Rit succesvol hersteld naar actief (Optie).";
        }
    } catch (PDOException $e) {
        die("<div style='padding:20px; background:#dc3545; color:white; font-weight:bold;'>🛑 DATABASE FOUT: " . $e->getMessage() . "</div>");
    }
    
    $m = $_GET['maand'] ?? date('n'); $j = $_GET['jaar'] ?? date('Y'); $v = $_GET['view'] ?? 'actief';
    header("Location: calculaties.php?maand=$m&jaar=$j&view=$v&actie_msg=" . urlencode($msg));
    exit;
}

// --- ACTIE: OFFERTE AFWIJZEN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actie']) && $_POST['actie'] == 'afwijzen') {
    $reden = trim((string) ($_POST['reden'] ?? ''));
    $salesDossier = isset($_POST['sales_dossier_id']) ? (int) $_POST['sales_dossier_id'] : 0;
    if ($salesDossier > 0) {
        sales_rit_dossiers_ensure_schema($pdo);
        $pdo->prepare("UPDATE sales_rit_dossiers SET status = 'afgewezen', afwijzings_reden = ? WHERE id = ? AND tenant_id = ?")->execute([$reden, $salesDossier, $tenantId]);
    } else {
        $rit_id = intval($_POST['rit_id'] ?? 0);
        $pdo->prepare("UPDATE calculaties SET status = 'afgewezen', afwijzings_reden = ? WHERE id = ? AND tenant_id = ?")->execute([$reden, $rit_id, $tenantId]);
    }
    $m = $_POST['maand'] ?? date('n'); $j = $_POST['jaar'] ?? date('Y');
    header("Location: calculaties.php?maand=$m&jaar=$j&view=actief&actie_msg=" . urlencode("Offerte afgewezen en succesvol gearchiveerd."));
    exit;
}

include 'includes/header.php';

// --- HULPFUNCTIE: NEDERLANDSE DATUM ---
function datumNL($datum) {
    $dagen = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    $maanden = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
    $ts = strtotime($datum);
    return $dagen[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $maanden[date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Ruwe adresregel bevat typische straat-/locatie-indicator (dashboard compacte route). */
function sales_calc_streetish_fragment(string $s): bool
{
    $t = mb_strtolower($s, 'UTF-8');

    return (bool) preg_match(
        '/straat|weg|laan|plein|dreef|singel|dijk|kade|(\bpad\b)|route|(\bhof\b)|industrieweg|stationsplein|tunnel|brug|bernard|complex|college|school|station|terminal|airport|luchthaven/i',
        $t
    );
}

function sales_calc_trunc_plaats(string $s, int $max = 42): string
{
    $s = trim($s);
    if ($s === '') {
        return '?';
    }
    if (mb_strlen($s) <= $max) {
        return $s;
    }

    return mb_substr($s, 0, $max - 1) . '…';
}

/**
 * Laatste “woord” in segment als plaats-indicator (bijv. "Stayokay Gorssel" → Gorssel).
 */
function sales_calc_plaats_token_from_segment(string $segment): string
{
    $segment = trim($segment);
    if ($segment === '') {
        return '?';
    }
    $partsWs = preg_split('/\s+/u', $segment, -1, PREG_SPLIT_NO_EMPTY);
    if (count($partsWs) >= 2) {
        $last = (string) end($partsWs);
        if (preg_match('/^[A-Za-zÀ-ÿ][A-Za-zà-ÿ\-\']*$/u', $last) && mb_strlen($last) >= 3 && !preg_match('/^\d+$/', $last)) {
            return sales_calc_trunc_plaats($last, 36);
        }
    }

    return sales_calc_trunc_plaats($segment, 42);
}

/**
 * Bestemming / aankomst: korte plaats uit lang Google-adres.
 */
function sales_calc_plaats_from_adres(?string $adres): string
{
    $raw = trim((string) $adres);
    if ($raw === '' || $raw === '?') {
        return '?';
    }
    $s = preg_replace('/,\s*(Nederland|Netherlands|België|Belgium|Duitsland|Germany|France|Luxemburg)\s*$/iu', '', $raw);
    $chunks = array_values(array_filter(array_map('trim', explode(',', $s)), static function ($p) {
        return $p !== '';
    }));
    $n = count($chunks);
    if ($n === 0) {
        return '?';
    }

    for ($i = $n - 1; $i >= 0; $i--) {
        if (preg_match('/\b(\d{4}\s?[A-Z]{2})\s+(.+)$/u', $chunks[$i], $m)) {
            $city = trim((string) ($m[2] ?? ''));
            if ($city !== '') {
                return sales_calc_trunc_plaats($city);
            }
        }
    }

    if ($n >= 2 && preg_match('/\bvan\s+[A-Za-zÀ-ÿ]/u', $chunks[$n - 1]) && sales_calc_streetish_fragment($chunks[$n - 2])) {
        return sales_calc_plaats_token_from_segment($chunks[0]);
    }

    for ($i = $n - 1; $i >= 0; $i--) {
        if (sales_calc_streetish_fragment($chunks[$i])) {
            continue;
        }
        $tok = sales_calc_plaats_token_from_segment($chunks[$i]);
        if ($tok !== '' && $tok !== '?') {
            return $tok;
        }
    }

    return sales_calc_plaats_token_from_segment($chunks[0]);
}

/** Vertrek: eerste deel is vaak de plaats; anders tweede segment na straat. */
function sales_calc_plaats_from_adres_vertrek(?string $adres): string
{
    $raw = trim((string) $adres);
    if ($raw === '' || $raw === '?') {
        return '?';
    }
    $s = preg_replace('/,\s*(Nederland|Netherlands|België|Belgium|Duitsland|Germany|France|Luxemburg)\s*$/iu', '', $raw);
    $chunks = array_values(array_filter(array_map('trim', explode(',', $s)), static function ($p) {
        return $p !== '';
    }));
    $n = count($chunks);
    if ($n === 0) {
        return '?';
    }
    if (!sales_calc_streetish_fragment($chunks[0])) {
        return sales_calc_plaats_token_from_segment($chunks[0]);
    }
    if ($n >= 2) {
        return sales_calc_plaats_token_from_segment($chunks[1]);
    }

    return sales_calc_plaats_from_adres($adres);
}

// --- 2. NAVIGATIE, FILTERS & VIEW LOGICA ---
$huidigeMaand = date('n'); $huidigJaar = date('Y');
$view = $_GET['view'] ?? 'actief'; 

// De filter variabelen
$search_q = $_GET['q'] ?? '';
$search_datum = $_GET['zoek_datum'] ?? '';
$search_type = $_GET['zoek_type'] ?? '';

$maand = isset($_GET['maand']) ? intval($_GET['maand']) : $huidigeMaand;
$jaar = isset($_GET['jaar']) ? intval($_GET['jaar']) : $huidigJaar;

$vorigeMaand = $maand - 1; $vorigeJaar = $jaar;
if($vorigeMaand < 1) { $vorigeMaand = 12; $vorigeJaar--; }
$volgendeMaand = $maand + 1; $volgendeJaar = $jaar;
if($volgendeMaand > 12) { $volgendeMaand = 1; $volgendeJaar++; }

$maanden = [1=>'JANUARI', 2=>'FEBRUARI', 3=>'MAART', 4=>'APRIL', 5=>'MEI', 6=>'JUNI', 7=>'JULI', 8=>'AUGUSTUS', 9=>'SEPTEMBER', 10=>'OKTOBER', 11=>'NOVEMBER', 12=>'DECEMBER'];

if ($view === 'archief') {
    $status_filter = "(c.status = 'gearchiveerd' OR c.status = 'afgewezen')";
} else {
    $status_filter = "(c.status != 'gearchiveerd' AND c.status != 'afgewezen' OR c.status IS NULL)";
}

// Dynamische WHERE opbouwen voor de zoekfilters
$where = [$status_filter];
$params = [$tenantId];

if(!empty($search_q)) {
    $where[] = "(k.bedrijfsnaam LIKE ? OR k.voornaam LIKE ? OR k.achternaam LIKE ? OR k.plaats LIKE ?)";
    $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%";
}
if(!empty($search_datum)) {
    $where[] = "c.rit_datum = ?";
    $params[] = $search_datum;
} else if (empty($search_q) && empty($search_type)) {
    // Als we NIET zoeken, filteren we netjes op de geselecteerde maand/jaar
    $where[] = "MONTH(c.rit_datum) = ? AND YEAR(c.rit_datum) = ?";
    $params[] = $maand;
    $params[] = $jaar;
}
if(!empty($search_type)) {
    $where[] = "c.rittype = ?";
    $params[] = $search_type;
}

$where_sql = implode(" AND ", $where);

// --- 3. DATA OPHALEN ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            k.bedrijfsnaam, k.voornaam, k.achternaam, k.plaats, k.email,
            v.naam as bus_type, v.capaciteit as bus_cap,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_vertrek_klant' LIMIT 1) as vertrek_adres,
            (SELECT adres FROM calculatie_regels WHERE calculatie_id = c.id AND tenant_id = c.tenant_id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        LEFT JOIN calculatie_voertuigen v ON c.voertuig_id = v.id AND v.tenant_id = c.tenant_id
        WHERE c.tenant_id = ? AND $where_sql
        ORDER BY c.rit_datum ASC
    ");
    $stmt->execute($params);
    $calcRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($calcRows as &$cr) {
        $cr['_kind'] = 'calculatie';
    }
    unset($cr);

    $salesRows = [];
    if ($search_type === '') {
        sales_rit_dossiers_ensure_schema($pdo);
        $whereSales = [];
        $paramsSales = [$tenantId];
        if ($view === 'archief') {
            $whereSales[] = "d.status IN ('afgewezen','gearchiveerd')";
        } else {
            $whereSales[] = "d.status = 'open'";
        }
        if ($search_q !== '') {
            $whereSales[] = '(k.bedrijfsnaam LIKE ? OR k.voornaam LIKE ? OR k.achternaam LIKE ? OR k.plaats LIKE ?)';
            $pq = '%' . $search_q . '%';
            array_push($paramsSales, $pq, $pq, $pq, $pq);
        }
        if ($search_datum !== '') {
            $whereSales[] = 'DATE(r.datum_start) = ?';
            $paramsSales[] = $search_datum;
        } elseif ($search_q === '') {
            $whereSales[] = 'MONTH(r.datum_start) = ? AND YEAR(r.datum_start) = ?';
            $paramsSales[] = $maand;
            $paramsSales[] = $jaar;
        }
        $sqlSales = '
            SELECT d.id AS sales_dossier_id, d.status AS sales_dossier_status,
                d.datum_prijs_gedeeld, d.datum_klant_akkoord, d.afwijzings_reden,
                d.heen_rit_id, d.retour_rit_id,
                DATE(r.datum_start) AS rit_datum,
                r.voertuig_type AS r_voertuig_type, r.geschatte_pax AS passagiers, r.prijsafspraak,
                r.betaalwijze,
                k.bedrijfsnaam, k.voornaam, k.achternaam, k.plaats, k.email,
                (SELECT van_adres FROM ritregels rg2 WHERE rg2.rit_id = r.id AND rg2.tenant_id = r.tenant_id ORDER BY rg2.id ASC LIMIT 1) AS vertrek_adres,
                (SELECT naar_adres FROM ritregels rg3 WHERE rg3.rit_id = r.id AND rg3.tenant_id = r.tenant_id ORDER BY rg3.id ASC LIMIT 1) AS bestemming_adres
            FROM sales_rit_dossiers d
            INNER JOIN ritten r ON r.id = d.heen_rit_id AND r.tenant_id = d.tenant_id
            LEFT JOIN klanten k ON k.id = r.klant_id AND k.tenant_id = r.tenant_id
            WHERE d.tenant_id = ? AND ' . implode(' AND ', $whereSales);
        $stSales = $pdo->prepare($sqlSales);
        $stSales->execute($paramsSales);
        $salesRows = $stSales->fetchAll(PDO::FETCH_ASSOC);
        foreach ($salesRows as &$sr) {
            $sr['_kind'] = 'sales_rit';
            $st = (string) ($sr['sales_dossier_status'] ?? '');
            if ($st === 'afgewezen') {
                $sr['status'] = 'afgewezen';
            } elseif ($st === 'gearchiveerd') {
                $sr['status'] = 'gearchiveerd';
            } else {
                $sr['status'] = 'offerte';
            }
            $sr['rittype'] = 'Direct (sales)';
            $sr['bus_type'] = null;
            $sr['bus_cap'] = 0;
            $sr['datum_offerte_verstuurd'] = $sr['datum_prijs_gedeeld'];
            $sr['datum_bevestiging_verstuurd'] = $sr['datum_klant_akkoord'];
            $sr['token'] = '';
        }
        unset($sr);
    }

    $ritten = array_merge($calcRows, $salesRows);
    usort($ritten, static function ($a, $b) {
        $datumA = (string) ($a['rit_datum'] ?? '');
        $datumB = (string) ($b['rit_datum'] ?? '');
        $dateComp = strcmp($datumA, $datumB);
        if ($dateComp !== 0) {
            return $dateComp;
        }
        $idA = (int) ($a['id'] ?? 0);
        $idB = (int) ($b['id'] ?? 0);
        return $idA <=> $idB;
    });

    // --- 4. CAPACITEITSCHECKER (POTLOODBOEKINGEN) ---
    $druktePerDag = [];
    foreach ($ritten as $r) {
        if (($r['_kind'] ?? '') === 'sales_rit') {
            continue;
        }
        $d = $r['rit_datum'];
        $bus = !empty($r['bus_type']) ? $r['bus_type'] : 'Onbekend';
        
        $pax = (int)$r['passagiers'];
        $cap = (int)$r['bus_cap'];
        $bussen_nodig = ($cap > 0 && $pax > $cap) ? ceil($pax / $cap) : 1;

        if(!isset($druktePerDag[$d])) $druktePerDag[$d] = [];
        if(!isset($druktePerDag[$d][$bus])) $druktePerDag[$d][$bus] = 0;
        
        $druktePerDag[$d][$bus] += $bussen_nodig;
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
    .dashboard-wrapper { display: flex; flex-direction: column; height: calc(100vh - 52px); padding: 8px 10px 10px; box-sizing: border-box; }

    .actie-msg-banner {
        flex: 0 0 auto;
        background: #d4edda;
        color: #155724;
        padding: 6px 10px;
        margin-bottom: 6px;
        border-radius: 4px;
        border: 1px solid #c3e6cb;
        font-weight: 600;
        font-size: 13px;
    }

    .top-bar {
        flex: 0 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 12px;
        background: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        margin-bottom: 6px;
    }
    .page-title { margin: 0; color: #003366; font-size: 17px; font-weight: bold; line-height: 1.2; }
    .top-bar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }

    .btn-green { background: #28a745; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 13px; display:inline-block; }
    .btn-grey { background: #6c757d; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 13px; display:inline-block; }
    .btn-blue { background: #007bff; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 13px; display:inline-block; border:none; cursor:pointer; }

    /* FILTERS */
    .filter-bar {
        flex: 0 0 auto;
        background: #fff;
        padding: 8px 10px;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        margin-bottom: 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px 10px;
        align-items: center;
    }
    .filter-bar > strong { font-size: 12px; white-space: nowrap; }
    .form-control { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 13px; }

    .month-bar { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; background: #003366; color: white; padding: 8px 12px; font-weight: bold; font-size: 14px; border-radius: 6px 6px 0 0; }
    .nav-link { color: white; text-decoration: none; opacity: 0.8; }
    .nav-link:hover { opacity: 1; }

    .table-scroll-container { flex: 1 1 auto; min-height: 0; background: #fff; border: 1px solid #ddd; border-top: none; overflow-y: auto; border-radius: 0 0 6px 6px; }
    .rit-table { width: 100%; border-collapse: collapse; }

    .rit-table th { background-color: #f1f5f9; color: #003366; padding: 5px 8px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #ddd; letter-spacing: 0.02em; }
    .rit-table td { padding: 5px 8px; border-bottom: 1px solid #f0f0f0; color: #333; font-size: 12px; vertical-align: middle; }
    .rit-table tr:hover td { background-color: #f1f9ff; }

    .rit-route-compact {
        white-space: nowrap;
        max-width: 280px;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 12px;
    }
    .rit-route-compact .route-plaats { color: #334155; }
    .rit-route-compact .route-plaats--naar { color: #003366; }
    .rit-route-compact .route-arrow { color: #94a3b8; margin: 0 5px; font-weight: 600; }

    .archief-row { opacity: 0.7; background-color: #fcfcfc; }
    .warning-row { background-color: #fff3cd !important; border-left: 4px solid #ffc107; }
    .sales-rit-row { border-left: 4px solid #dd6b20; background: linear-gradient(90deg, rgba(221,107,32,0.06) 0%, #fff 12px); }
    .buitenland-row { border-left: 4px solid #0d9488; background: linear-gradient(90deg, rgba(13,148,136,0.07) 0%, #fff 12px); }

    .status-col { text-align: center; cursor: pointer; width: 45px; border-left: 1px solid #f5f5f5; }
    .status-icon { font-size: 16px; color: #e0e0e0; }
    .status-icon.active { color: #28a745; }

    .badge { padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .badge-optie { background: #e2e3e5; color: #383d41; border: 1px dashed #adb5bd; }
    .badge-definitief { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .badge-afgewezen { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .badge-buitenland { background: #ccfbf1; color: #0f766e; border: 1px solid #5eead4; font-weight: 700; }

    .action-icons a { margin-right: 8px; font-size: 16px; cursor: pointer; text-decoration:none; }
    .btn-edit { color: #003366; }
    .btn-pdf { color: #007bff; }
    .btn-reminder { color: #fd7e14; }
    .btn-archive { color: #6c757d; }
    .btn-restore { color: #28a745; }
    .btn-reject { color: #dc3545; }
    .btn-delete { color: #dc3545; opacity: 0.8; }
    .btn-delete:hover { opacity: 1; }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 0; border: 1px solid #888; width: 400px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .modal-header { background: #003366; color: white; padding: 15px; display:flex; justify-content:space-between; align-items:center; }
    .modal-header.rood { background: #dc3545; }
    .modal-body { padding: 20px; }
</style>

<div class="dashboard-wrapper">
    
    <?php if(isset($_GET['actie_msg'])): ?>
    <div class="actie-msg-banner">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars((string) $_GET['actie_msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <div class="top-bar">
        <h1 class="page-title">
            <?= $view === 'archief' ? '<i class="fas fa-archive" style="color:#6c757d;"></i> Archief Offertes' : '<i class="fas fa-file-signature"></i> Sales & Offertes' ?>
        </h1>
        <div class="top-bar-actions">
            <?php if($view === 'archief'): ?>
                <a href="?maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=actief" class="btn-blue"><i class="fas fa-list"></i> Terug naar Actief</a>
            <?php else: ?>
                <a href="?maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=archief" class="btn-grey"><i class="fas fa-archive"></i> Bekijk Archief</a>
            <?php endif; ?>
            <a href="calculatie/verzamelofferte.php" class="btn-grey"><i class="fas fa-layer-group"></i> Verzamelofferte</a>
            <a href="calculatie/maken.php" class="btn-green"><i class="fas fa-plus"></i> Nieuwe Offerte</a>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="hidden" name="view" value="<?= $view ?>">
        <input type="hidden" name="maand" value="<?= $maand ?>">
        <input type="hidden" name="jaar" value="<?= $jaar ?>">
        
        <strong style="color:#003366;">Zoeken</strong>
        <input type="text" name="q" placeholder="Klant of plaats…" value="<?= htmlspecialchars($search_q) ?>" class="form-control" style="margin:0; width:min(200px, 100%);">
        <input type="date" name="zoek_datum" value="<?= htmlspecialchars($search_datum) ?>" class="form-control" style="margin:0; width:140px;">
        
        <select name="zoek_type" class="form-control" style="margin:0; width:150px;">
            <option value="">-- Soort Rit --</option>
            <option value="dagtocht" <?= $search_type == 'dagtocht' ? 'selected' : '' ?>>Dagtocht</option>
            <option value="enkel" <?= $search_type == 'enkel' ? 'selected' : '' ?>>Enkele Rit</option>
            <option value="meerdaags" <?= $search_type == 'meerdaags' ? 'selected' : '' ?>>Meerdaags</option>
            <option value="schoolreis" <?= $search_type == 'schoolreis' ? 'selected' : '' ?>>Schoolreis</option>
            <option value="brenghaal" <?= $search_type == 'brenghaal' ? 'selected' : '' ?>>Breng & Haal</option>
            <option value="trein" <?= $search_type == 'trein' ? 'selected' : '' ?>>Treinstremming</option>
            <option value="buitenland" <?= $search_type == 'buitenland' ? 'selected' : '' ?>>Buitenland</option>
        </select>
        
        <button type="submit" class="btn-blue" style="margin:0;"><i class="fas fa-search"></i> Zoek</button>
        
        <?php if(!empty($search_q) || !empty($search_datum) || !empty($search_type)): ?>
            <a href="calculaties.php?view=<?= $view ?>" class="btn-grey" style="margin:0; text-decoration:none;"><i class="fas fa-times"></i> Wis Filters</a>
            <span style="font-size:12px; color:#888; font-style:italic;">Doorzoekt de hele database.<?= !empty($search_type) ? ' Sales-ritten (taxi) worden bij “Soort rit” verborgen.' : '' ?></span>
        <?php endif; ?>
    </form>

    <div class="month-bar">
        <a href="?maand=<?= $vorigeMaand ?>&jaar=<?= $vorigeJaar ?>&view=<?= $view ?>" class="nav-link"><i class="fas fa-chevron-left"></i> VORIGE</a>
        <span><?= $maanden[$maand] ?> <?= $jaar ?> <?= $view === 'archief' ? '(Archief)' : '' ?></span>
        <a href="?maand=<?= $volgendeMaand ?>&jaar=<?= $volgendeJaar ?>&view=<?= $view ?>" class="nav-link">VOLGENDE <i class="fas fa-chevron-right"></i></a>
    </div>

    <div class="table-scroll-container">
        <table class="rit-table">
            <thead>
                <tr>
                    <th style="width:40px; padding-left:15px;">#</th>
                    <th style="width:200px;">Datum (NL)</th>
                    <th style="width:200px;">Klant</th>
                    <th>Route <span style="font-weight:600; text-transform:none; color:#64748b;">(plaats)</span></th>
                    <th style="width:160px;">Passagiers & Vervoer</th>
                    <th style="text-align:center; width:60px;" title="Offerte Verzonden">OFF</th>
                    <th style="text-align:center; width:60px;" title="Bevestiging Verzonden">BEV</th>
                    <th style="text-align:center; width:90px;">Status</th>
                    <th style="text-align:right; padding-right:15px; width: 140px;">Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($ritten) == 0): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding: 28px 16px; color:#999; font-size:15px;">
                            <i class="fas fa-folder-open" style="font-size:30px; margin-bottom:10px; display:block; color:#ddd;"></i>
                            <?= $view === 'archief' ? 'Geen afgewezen offertes gevonden.' : 'Geen actieve aanvragen gevonden met deze filters.' ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php
                foreach ($ritten as $r):
                    $isSales = (($r['_kind'] ?? '') === 'sales_rit');
                    $salesId = $isSales ? (int) ($r['sales_dossier_id'] ?? 0) : 0;
                    $heenRitId = $isSales ? (int) ($r['heen_rit_id'] ?? 0) : 0;
                    $calcId = !$isSales ? (int) ($r['id'] ?? 0) : 0;
                    $statusModalId = $isSales ? $salesId : $calcId;
                    $rowDomId = $isSales ? ('s' . $salesId) : ('c' . $calcId);

                    $klantNaam = !empty($r['bedrijfsnaam']) ? $r['bedrijfsnaam'] : trim(($r['voornaam'] ?? '') . ' ' . ($r['achternaam'] ?? ''));
                    $plaats = $r['plaats'] ?? '';
                    $klantEmail = $r['email'] ?? '';

                    $vertrekVol = trim((string) ($r['vertrek_adres'] ?? ''));
                    $bestemmingVol = trim((string) ($r['bestemming_adres'] ?? ''));
                    if ($vertrekVol === '') {
                        $vertrekVol = '?';
                    }
                    if ($bestemmingVol === '') {
                        $bestemmingVol = '?';
                    }
                    $vertrekLabel = $vertrekVol === '?' ? '?' : sales_calc_plaats_from_adres_vertrek($vertrekVol);
                    $bestemmingLabel = $bestemmingVol === '?' ? '?' : sales_calc_plaats_from_adres($bestemmingVol);
                    $routeVvSuffix = ($r['rittype'] ?? '') === 'brenghaal' ? ' v.v.' : '';
                    $routeTitle = $vertrekVol === '?' && $bestemmingVol === '?'
                        ? ''
                        : ($vertrekVol . ' → ' . $bestemmingVol . $routeVvSuffix);

                    $st_offerte = !empty($r['datum_offerte_verstuurd']) ? 'active' : '';
                    $st_bevest = !empty($r['datum_bevestiging_verstuurd']) ? 'active' : '';

                    $status_badge = '<span class="badge badge-optie">✏️ Optie</span>';
                    if (!empty($r['datum_bevestiging_verstuurd'])) {
                        $status_badge = '<span class="badge badge-definitief">✅ Akkoord vastgelegd</span>';
                    }
                    if ($r['status'] === 'afgewezen') {
                        $status_badge = '<span class="badge badge-afgewezen">❌ Afgewezen</span><br><span style="font-size:10px; color:#721c24;">' . htmlspecialchars((string) ($r['afwijzings_reden'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
                    } elseif ($isSales && ($r['sales_dossier_status'] ?? '') === 'gearchiveerd') {
                        $status_badge = '<span class="badge badge-optie">📁 Gearchiveerd</span>';
                    }

                    if ($isSales) {
                        $pax = (int) $r['passagiers'];
                        $cap = 0;
                        $bus_naam = !empty($r['r_voertuig_type']) ? (string) $r['r_voertuig_type'] : 'Onbekend';
                        $bussen_nodig = 1;
                        $waarschuwing = '';
                        $prijsTxt = isset($r['prijsafspraak']) && $r['prijsafspraak'] !== null && $r['prijsafspraak'] !== ''
                            ? '€ ' . number_format((float) $r['prijsafspraak'], 2, ',', '.')
                            : '—';
                        $vervoerBlok = '<span style="font-size:11px; color:#9a3412; font-weight:bold;">Sales-rit</span><br>'
                            . '<span style="font-size:12px; color:#444;">' . htmlspecialchars($bus_naam, ENT_QUOTES, 'UTF-8') . '</span>'
                            . '<div style="font-size:11px; color:#666; margin-top:4px;">Indicatie: <strong>' . $prijsTxt . '</strong> · ' . htmlspecialchars((string) ($r['betaalwijze'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
                    } else {
                        $pax = (int) $r['passagiers'];
                        $cap = (int) $r['bus_cap'];
                        $bus_naam = !empty($r['bus_type']) ? $r['bus_type'] : 'Onbekend';
                        $bussen_nodig = ($cap > 0 && $pax > $cap) ? (int) ceil($pax / $cap) : 1;
                        $waarschuwing = '';
                        $vervoerBlok = 'Calculatie: <strong>' . $bussen_nodig . 'x</strong> ' . htmlspecialchars($bus_naam, ENT_QUOTES, 'UTF-8');
                    }

                    $row_class = $view === 'archief' ? 'archief-row' : '';
                    if ($isSales) {
                        $row_class = trim($row_class . ' sales-rit-row');
                    }

                    $isBuitenlandCalc = !$isSales
                        && (
                            (($r['offerte_module'] ?? '') === 'buitenland')
                            || (($r['rittype'] ?? '') === 'buitenland')
                        );
                    if ($isBuitenlandCalc) {
                        $row_class = trim($row_class . ' buitenland-row');
                    }

                    if (!$isSales && $view !== 'archief') {
                        $aantal_die_dag = $druktePerDag[$r['rit_datum']][$bus_naam] ?? 0;
                        $max_bussen_van_dit_type = 2;
                        if ($aantal_die_dag > $max_bussen_van_dit_type && $bus_naam !== 'Onbekend') {
                            $row_class = trim($row_class . ' warning-row');
                            $waarschuwing = "<div style='color:#dc3545; font-size:11px; font-weight:bold; margin-top:4px;'><i class='fas fa-exclamation-triangle'></i> Let op: Er zijn $aantal_die_dag x $bus_naam gereserveerd deze dag!</div>";
                        }
                    }

                    $aanhef = trim((string) ($r['voornaam'] ?? '')) !== '' ? (string) $r['voornaam'] : 'klant';
                    $mail_subject = rawurlencode('Uw aanvraag bij BusAI (' . date('d-m-Y', strtotime((string) $r['rit_datum'])) . ')');
                    $mail_body = rawurlencode("Beste {$aanhef},\n\nWe hebben onlangs een offerte verzonden voor de busreis naar {$bestemmingVol} op " . date('d-m-Y', strtotime((string) $r['rit_datum'])) . ".\n\nWe vroegen ons af of u hier al naar heeft kunnen kijken en of de rit definitief doorgaat?\nWe horen graag van u, zodat we de bus voor u gereserveerd kunnen houden.\n\nMet vriendelijke groet,\n\nBusAI");

                    $iconOff = $isSales ? ('icon-offerte-s-' . $salesId) : ('icon-offerte-' . $calcId);
                    $iconBev = $isSales ? ('icon-bevestiging-s-' . $salesId) : ('icon-bevestiging-' . $calcId);
                    $entityArg = $isSales ? 'sales_rit_dossier' : 'calculatie';
                    ?>
                <tr id="row-<?= htmlspecialchars($rowDomId, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars(trim($row_class), ENT_QUOTES, 'UTF-8') ?>">
                    <td style="padding-left:15px;">
                        <strong><?= $isSales ? 'S' . $salesId : (int) $calcId ?></strong>
                        <?php if ($isSales): ?>
                            <div style="font-size:9px; color:#c2410c; font-weight:bold; text-transform:uppercase;">Rit #<?= $heenRitId ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:bold; color:#003366; font-size: 13px; line-height:1.2;"><?= ucfirst(datumNL((string) $r['rit_datum'])) ?></div>
                        <span style="font-size:10px; color:#888; text-transform:uppercase; font-weight:bold;"><?= htmlspecialchars((string) ($r['rittype'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($isBuitenlandCalc)): ?>
                            <div style="margin-top:4px;"><span class="badge badge-buitenland"><i class="fas fa-globe-europe"></i> Buitenland</span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color:#333;"><?= htmlspecialchars($klantNaam, ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <span style="font-size:12px; color:#888;"><?= htmlspecialchars($plaats, ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td class="rit-route-compact"<?= $routeTitle !== '' ? ' title="' . htmlspecialchars($routeTitle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <span class="route-plaats"><?= htmlspecialchars($vertrekLabel, ENT_QUOTES, 'UTF-8') ?></span><span class="route-arrow">→</span><strong class="route-plaats route-plaats--naar"><?= htmlspecialchars($bestemmingLabel . $routeVvSuffix, ENT_QUOTES, 'UTF-8') ?></strong>
                    </td>
                    <td>
                        <div style="font-weight:bold; font-size:12px; color:#333; margin-bottom:2px;"><i class="fas fa-users" style="color:#888;"></i> <?= (int) $pax ?> personen</div>
                        <div style="font-size:10px; color:#666; background:#e9ecef; padding:2px 5px; border-radius:3px; display:inline-block; border:1px solid #ccc;">
                            <?= $vervoerBlok ?>
                        </div>
                        <?= $waarschuwing ?>
                    </td>

                    <td class="status-col" onclick='openStatusSlim(<?= (int) $statusModalId ?>, "offerte", <?= json_encode($st_offerte, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, 0, <?= json_encode((string) ($r['token'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($entityArg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <i id="<?= htmlspecialchars($iconOff, ENT_QUOTES, 'UTF-8') ?>" class="fas fa-check-circle status-icon <?= htmlspecialchars($st_offerte, ENT_QUOTES, 'UTF-8') ?>"></i>
                    </td>
                    <td class="status-col" onclick='openStatusSlim(<?= (int) $statusModalId ?>, "bevestiging", <?= json_encode($st_bevest, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, 0, <?= json_encode((string) ($r['token'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($entityArg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <i id="<?= htmlspecialchars($iconBev, ENT_QUOTES, 'UTF-8') ?>" class="fas fa-check-circle status-icon <?= htmlspecialchars($st_bevest, ENT_QUOTES, 'UTF-8') ?>"></i>
                    </td>

                    <td style="text-align:center;"><?= $status_badge ?></td>

                    <td style="text-align:right; padding-right:15px;" class="action-icons">
                        <?php if ($isSales): ?>
                            <a href="rit-bewerken.php?id=<?= $heenRitId ?>" class="btn-edit" style="color:#c2410c;" title="Rit bewerken (planbord)"><i class="fas fa-taxi"></i></a>
                        <?php else: ?>
                            <a href="calculatie/calculaties_bewerken.php?id=<?= (int) $calcId ?>" class="btn-edit" title="Offerte Bewerken"><i class="fas fa-pen"></i></a>
                            <a href="calculatie/calculatie_kopie.php?bron_id=<?= (int) $calcId ?>" class="btn-edit" style="color:#0d6efd;" title="Kopie met nieuwe datum"><i class="fas fa-copy"></i></a>
                        <?php endif; ?>

                        <?php if ($view === 'actief'): ?>
                            <a href="mailto:<?= htmlspecialchars($klantEmail, ENT_QUOTES, 'UTF-8') ?>?subject=<?= $mail_subject ?>&body=<?= $mail_body ?>" class="btn-reminder" title="Stuur opvolgmail (Gaat rit door?)"><i class="fas fa-envelope-open-text"></i></a>
                            <?php if ($isSales): ?>
                                <a onclick="openAfwijzenModalSales(<?= $salesId ?>)" class="btn-reject" title="Sales-dossier afwijzen"><i class="fas fa-times-circle"></i></a>
                            <?php else: ?>
                                <a onclick="openAfwijzenModal(<?= (int) $calcId ?>)" class="btn-reject" title="Niet geaccepteerd / Afwijzen"><i class="fas fa-times-circle"></i></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isSales): ?>
                                <a onclick="herstelSalesRit(<?= $salesId ?>)" class="btn-restore" title="Sales-dossier terugzetten"><i class="fas fa-recycle"></i></a>
                                <a onclick="verwijderSalesRit(<?= $salesId ?>)" class="btn-delete" title="Sales-dossier verwijderen"><i class="fas fa-trash-alt"></i></a>
                            <?php else: ?>
                                <a onclick="herstelRit(<?= (int) $calcId ?>)" class="btn-restore" title="Terugzetten naar Actief (Optie)"><i class="fas fa-recycle"></i></a>
                                <a onclick="verwijderRit(<?= (int) $calcId ?>)" class="btn-delete" title="Definitief Verwijderen"><i class="fas fa-trash-alt"></i></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="afwijzenModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header rood">
            <span style="font-weight:bold;">❌ Offerte Niet Geaccepteerd</span>
            <span style="cursor:pointer; font-size:24px;" onclick="sluitAfwijzenModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p style="margin-top:0; color:#333;">Geef aan waarom deze rit niet doorgaat. Deze reden wordt opgeslagen en de offerte verdwijnt naar het archief.</p>
            <form method="POST" action="calculaties.php">
                <input type="hidden" name="actie" value="afwijzen">
                <input type="hidden" name="rit_id" id="afwijzen_rit_id" value="0">
                <input type="hidden" name="sales_dossier_id" id="afwijzen_sales_dossier_id" value="0">
                <input type="hidden" name="maand" value="<?= $maand ?>">
                <input type="hidden" name="jaar" value="<?= $jaar ?>">
                
                <label style="font-weight:bold; font-size:12px; color:#555;">Reden van afwijzing:</label>
                <select name="reden" class="form-control" required style="width:100%; padding:10px; margin-bottom:15px;">
                    <option value="">-- Kies een reden --</option>
                    <option value="Prijs was te hoog">Prijs was te hoog</option>
                    <option value="Aanvraag geannuleerd door klant">Aanvraag geannuleerd door klant</option>
                    <option value="Klant heeft voor concurrent gekozen">Klant heeft voor concurrent gekozen</option>
                    <option value="Geen bussen/capaciteit beschikbaar">Geen bussen/capaciteit beschikbaar</option>
                    <option value="Ritdatum is veranderd">Ritdatum is veranderd</option>
                    <option value="Anders (Zie notities)">Anders (Zie notities)</option>
                </select>
                
                <div style="text-align:right;">
                    <button type="button" onclick="sluitAfwijzenModal()" style="background:#ccc; border:none; padding:10px 15px; border-radius:4px; cursor:pointer;">Annuleren</button>
                    <button type="submit" style="background:#dc3545; color:white; border:none; padding:10px 15px; border-radius:4px; margin-left:5px; cursor:pointer; font-weight:bold;">Bevestig Afwijzing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="statusModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle" style="font-weight:bold;">Document Verzenden</span>
            <span style="cursor:pointer; font-size:24px;" onclick="closeStatus()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="modalRitId">
            <input type="hidden" id="modalType">
            
            <p style="margin-top:0; color:#666;">Wat wil je doen met deze <span id="modalTypeDisplay" style="font-weight:bold;"></span>?</p>

            <div id="statusModalPdfMailRow" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                <a href="#" id="btnDownload" target="_blank" style="display:block; width:100%; padding:15px; text-align:center; border:1px solid #ddd; border-radius:5px; text-decoration:none; font-weight:bold; color:#dc3545; box-sizing:border-box;">
                    <i class="fas fa-file-pdf" style="font-size:24px; display:block; margin-bottom:5px;"></i> DOWNLOAD
                </a>
                <div style="display:block; width:100%; padding:15px; text-align:center; border:1px solid #ddd; border-radius:5px; font-weight:bold; color:#003366; cursor:pointer; box-sizing:border-box;" onclick="verstuurEmail()">
                    <i class="fas fa-envelope" style="font-size:24px; display:block; margin-bottom:5px;"></i> EMAILEN
                </div>
            </div>
            
            <div id="chauffeurSelectieBlok" style="display:none;">
                <select id="modalChauffeur" class="form-control"><option value="0">-- Kies --</option></select>
            </div>
            
            <label style="font-size:12px; font-weight:bold;">Datum verwerkt/gemaild:</label>
            <input type="date" id="modalDatum" class="form-control" style="width:100%; padding:10px; margin-top:5px; margin-bottom:20px; box-sizing:border-box;">
            
            <div style="text-align:right;">
                <button onclick="verwijderStatus()" style="background:#dc3545; color:white; border:none; padding:8px 15px; border-radius:4px; float:left; cursor:pointer;">Reset</button>
                <button onclick="closeStatus()" style="background:#ccc; border:none; padding:8px 15px; border-radius:4px; cursor:pointer;">Sluit</button>
                <button onclick="saveStatus()" style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; margin-left:5px; cursor:pointer;">Handmatig Opslaan</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openAfwijzenModal(id) {
        document.getElementById('afwijzen_rit_id').value = id;
        document.getElementById('afwijzen_sales_dossier_id').value = 0;
        document.getElementById('afwijzenModal').style.display = 'block';
    }
    function openAfwijzenModalSales(sid) {
        document.getElementById('afwijzen_sales_dossier_id').value = sid;
        document.getElementById('afwijzen_rit_id').value = 0;
        document.getElementById('afwijzenModal').style.display = 'block';
    }
    function sluitAfwijzenModal() {
        document.getElementById('afwijzenModal').style.display = 'none';
    }

    function openStatusSlim(id, type, status, chauffeurId, token, entity) {
        let chauffeurBlok = document.getElementById('chauffeurSelectieBlok');
        chauffeurBlok.style.display = 'none'; 
        
        if(typeof openStatus === 'function') {
            openStatus(id, type, status, typeof token === 'string' ? token : '', entity || 'calculatie');
        } else {
            alert("Error: Kan verzenden.js niet inladen.");
        }
    }

    function verwijderRit(id) {
        if(confirm('Weet je zeker dat je offerte #' + id + ' wilt verwijderen?')) {
            if(confirm('⚠️ LAATSTE WAARSCHUWING! Dit is definitief.')) {
                window.location.href = '?do_actie=verwijder&rit_id=' + id + '&maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=<?= $view ?>';
            }
        }
    }
    function verwijderSalesRit(sid) {
        if(confirm('Sales-dossier S' + sid + ' verwijderen? De rit blijft op het planbord staan.')) {
            window.location.href = '?do_actie=verwijder_sales&sales_dossier_id=' + sid + '&maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=<?= $view ?>';
        }
    }

    function archiveerRit(id) {
        if(confirm('Wil je deze offerte archiveren zonder een afwijzingsreden op te geven?')) {
            window.location.href = '?do_actie=archiveer&rit_id=' + id + '&maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=<?= $view ?>';
        }
    }

    function herstelRit(id) {
        if(confirm('Wil je deze offerte weer terughalen naar je actieve opties?')) {
            window.location.href = '?do_actie=herstel&rit_id=' + id + '&maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=<?= $view ?>';
        }
    }
    function herstelSalesRit(sid) {
        if(confirm('Sales-dossier weer actief maken?')) {
            window.location.href = '?do_actie=herstel_sales&sales_dossier_id=' + sid + '&maand=<?= $maand ?>&jaar=<?= $jaar ?>&view=<?= $view ?>';
        }
    }
</script>

<script src="calculatie/verzenden.js?v=<?= time() ?>"></script>
<?php include 'includes/footer.php'; ?>