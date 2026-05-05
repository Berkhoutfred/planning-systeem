<?php
// Bestand: beheer/ajax_planbord_opslaan.php
// Doel: Slaat een planbord-wijziging op, ondersteunt 'Diensten/Mapjes', controleert dubbele boekingen EN verstuurt Telegrams!

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';

header('Content-Type: application/json');

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tenant context ontbreekt.']);
    exit;
}

// Haal de data op die via Javascript is verstuurd
$input = json_decode(file_get_contents('php://input'), true);
$rit_id = isset($input['rit_id']) ? intval($input['rit_id']) : 0;
$voertuig_id = !empty($input['voertuig_id']) ? intval($input['voertuig_id']) : null;

// NIEUW: We vangen de dropdown-waarde op (Dit kan een Chauffeur ID of een Dienst ID zijn)
$ruwe_waarde = isset($input['chauffeur_id']) ? $input['chauffeur_id'] : '';

$chauffeur_id = null;
$dienst_id = null;

if (!empty($ruwe_waarde)) {
    // Check of we een 'Dienst' (Mapje) binnenkrijgen
    if (strpos((string)$ruwe_waarde, 'dienst_') === 0) {
        $dienst_id = intval(str_replace('dienst_', '', $ruwe_waarde));
        
        // Zoek supersnel op wie de chauffeur van deze dienst is
        $stmt_dienst = $pdo->prepare("SELECT chauffeur_id FROM diensten WHERE id = ? AND tenant_id = ?");
        $stmt_dienst->execute([$dienst_id, $tenantId]);
        $dienst_data = $stmt_dienst->fetch();
        if ($dienst_data) {
            $chauffeur_id = $dienst_data['chauffeur_id'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Ongeldige dienst voor deze tenant.']);
            exit;
        }
    } else {
        // Het is een losse chauffeur koppeling
        $chauffeur_id = intval($ruwe_waarde);
    }
}

if ($rit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geen geldige rit geselecteerd.']);
    exit;
}

try {
    if ($voertuig_id !== null) {
        $stmtVoertuig = $pdo->prepare("SELECT id FROM voertuigen WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmtVoertuig->execute([$voertuig_id, $tenantId]);
        if (!$stmtVoertuig->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Geselecteerde bus hoort niet bij deze tenant.']);
            exit;
        }
    }

    if ($chauffeur_id !== null) {
        $stmtChauffeur = $pdo->prepare("SELECT id FROM chauffeurs WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmtChauffeur->execute([$chauffeur_id, $tenantId]);
        if (!$stmtChauffeur->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Geselecteerde chauffeur hoort niet bij deze tenant.']);
            exit;
        }
    }

    // 1. Haal de huidige ritgegevens op om te vergelijken (nu inclusief dienst_id)
    $stmt_huidig = $pdo->prepare("SELECT datum_start, datum_eind, voertuig_id, chauffeur_id, dienst_id FROM ritten WHERE id = ? AND tenant_id = ?");
    $stmt_huidig->execute([$rit_id, $tenantId]);
    $deze_rit = $stmt_huidig->fetch();
    
    if (!$deze_rit) {
        echo json_encode(['success' => false, 'message' => 'Rit niet gevonden in database.']);
        exit;
    }

    $start = $deze_rit['datum_start'];
    $eind = $deze_rit['datum_eind'];
    $oude_bus = $deze_rit['voertuig_id'];
    $oude_chauf = $deze_rit['chauffeur_id'];
    $oude_dienst = $deze_rit['dienst_id'];

    // CHECK: Is er daadwerkelijk iets gewijzigd?
    $is_gewijzigd = false;
    if ($oude_bus != $voertuig_id || $oude_chauf != $chauffeur_id || $oude_dienst != $dienst_id) {
        $is_gewijzigd = true;
    }

    // Als er NIKS is gewijzigd, zijn we direct klaar (geen spam, geen reset)
    if (!$is_gewijzigd) {
        echo json_encode(['success' => true]);
        exit;
    }

    // 2. CONTROLE: Is de geselecteerde BUS al bezet?
    if ($voertuig_id !== null) {
        $checkBus = $pdo->prepare("
            SELECT id FROM ritten 
            WHERE tenant_id = ? AND voertuig_id = ? AND id != ? 
            AND (datum_start < ? AND datum_eind > ?)
        ");
        $checkBus->execute([$tenantId, $voertuig_id, $rit_id, $eind, $start]);
        if ($checkBus->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Let op: Deze BUS is al gepland op (een deel van) deze tijden!']);
            exit;
        }
    }

    // 3. CONTROLE: Is de geselecteerde CHAUFFEUR al bezet?
    if ($chauffeur_id !== null) {
        $checkChauf = $pdo->prepare("
            SELECT id FROM ritten 
            WHERE tenant_id = ? AND chauffeur_id = ? AND id != ? 
            AND (datum_start < ? AND datum_eind > ?)
        ");
        $checkChauf->execute([$tenantId, $chauffeur_id, $rit_id, $eind, $start]);
        if ($checkChauf->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Let op: Deze CHAUFFEUR is al gepland op (een deel van) deze tijden!']);
            exit;
        }
    }

    // 4. ALLES VEILIG EN GEWIJZIGD? Sla op en RESET het acceptatie-vinkje!
    // NIEUW: We slaan nu ook het dienst_id netjes op.
    $stmt_update = $pdo->prepare("UPDATE ritten SET voertuig_id = ?, chauffeur_id = ?, dienst_id = ?, geaccepteerd_tijdstip = NULL WHERE id = ? AND tenant_id = ?");
    $stmt_update->execute([$voertuig_id, $chauffeur_id, $dienst_id, $rit_id, $tenantId]);

    // ==========================================
    // 5. TELEGRAM PING VERSTUREN (Omdat het gewijzigd is)
    // ==========================================
    if ($chauffeur_id !== null) {
        try {
            $stmt_info = $pdo->prepare("
                SELECT 
                    r.datum_start, r.calculatie_id,
                    v.voertuig_nummer,
                    k.bedrijfsnaam, k.voornaam, k.achternaam,
                    (SELECT adres FROM calculatie_regels WHERE calculatie_id = r.calculatie_id AND tenant_id = r.tenant_id AND type = 't_aankomst_best' LIMIT 1) as bestemming_adres,
                    (SELECT omschrijving FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as vaste_rit_naam,
                    (SELECT naar_adres FROM ritregels WHERE rit_id = r.id AND tenant_id = r.tenant_id LIMIT 1) as vaste_rit_bestemming
                FROM ritten r 
                LEFT JOIN voertuigen v ON r.voertuig_id = v.id AND v.tenant_id = r.tenant_id
                LEFT JOIN calculaties c ON r.calculatie_id = c.id AND c.tenant_id = r.tenant_id
                LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = r.tenant_id
                WHERE r.id = ? AND r.tenant_id = ?
            ");
            $stmt_info->execute([$rit_id, $tenantId]);
            $rit_info = $stmt_info->fetch();

            if ($rit_info) {
                if (!empty($rit_info['calculatie_id'])) {
                    $klant = !empty($rit_info['bedrijfsnaam']) ? $rit_info['bedrijfsnaam'] : trim($rit_info['voornaam'] . ' ' . $rit_info['achternaam']);
                    $bestemming = $rit_info['bestemming_adres'] ?? 'Adres onbekend';
                } else {
                    $klant = $rit_info['vaste_rit_naam'];
                    $bestemming = $rit_info['vaste_rit_bestemming'] ?? 'Adres onbekend';
                }
                
                $bus_naam = !empty($rit_info['voertuig_nummer']) ? "Bus " . $rit_info['voertuig_nummer'] : "Nog onbekend";
                $tijd_weergave = date('d-m-Y \o\m H:i', strtotime($rit_info['datum_start']));

                $bericht = "🚨 <b>Rit Update (Planbord)</b>\n\n";
                $bericht .= "<b>Start:</b> " . $tijd_weergave . "\n";
                $bericht .= "<b>Klant:</b> " . $klant . "\n";
                $bericht .= "<b>Naar:</b> " . $bestemming . "\n";
                $bericht .= "<b>Bus:</b> " . $bus_naam . "\n\n";
                $bericht .= "<i>Check je app dashboard voor de actuele planning.</i>";

                require_once 'includes/telegram_functies.php';
                stuurTelegramMelding($pdo, $chauffeur_id, $bericht, $rit_id);
            }
        } catch (Exception $e) {
            // Mocht Telegram falen, negeren we het zodat planbord werkt
        }
    }
    // ==========================================

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
?>