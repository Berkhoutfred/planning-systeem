<?php
// Bestand: beheer/ajax_bevestiging_sturen.php
// Doel: Status updaten & Bevestigingsmail naar klant sturen

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

include '../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require 'includes/db.php';
require_once __DIR__ . '/includes/split_ritten.php';

$data = json_decode(file_get_contents('php://input'), true);
$calculatie_id = isset($data['calculatie_id']) ? intval($data['calculatie_id']) : 0;
$tenantId = current_tenant_id();

if ($tenantId <= 0 || $calculatie_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Geen geldig rit ID ontvangen.']);
    exit;
}

try {
    // 1. Haal de gegevens van de klant en rit op
    $stmt = $pdo->prepare("
        SELECT c.*, k.email, k.voornaam, k.achternaam, k.bedrijfsnaam 
        FROM calculaties c 
        LEFT JOIN klanten k ON c.klant_id = k.id AND k.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$calculatie_id, $tenantId]);
    $rit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rit) {
        echo json_encode(['success' => false, 'message' => 'Rit niet gevonden in de database.']);
        exit;
    }

    if (empty($rit['email'])) {
        echo json_encode(['success' => false, 'message' => 'Klant heeft geen e-mailadres geregistreerd.']);
        exit;
    }

    $pdfToken = trim((string) ($rit['token'] ?? ''));
    if ($pdfToken === '') {
        $pdfToken = bin2hex(random_bytes(20));
        $pdo->prepare('UPDATE calculaties SET token = ? WHERE id = ? AND tenant_id = ?')->execute([$pdfToken, $calculatie_id, $tenantId]);
    }

    // Slim ordernummer
    $jaar_rit = !empty($rit['rit_datum']) ? date('y', strtotime($rit['rit_datum'])) : date('y');
    $orderNummer = $jaar_rit . str_pad($rit['id'], 3, '0', STR_PAD_LEFT);
    $klantNaam = !empty($rit['bedrijfsnaam']) ? $rit['bedrijfsnaam'] : trim($rit['voornaam'] . ' ' . $rit['achternaam']);

    // 2. Update de status naar 'geaccepteerd' (Definitief rond)
    $nu = date('Y-m-d H:i:s');
    $upd = $pdo->prepare("UPDATE calculaties SET status = 'geaccepteerd', geaccepteerd_op = ? WHERE id = ? AND tenant_id = ?");
    $upd->execute([$nu, $calculatie_id, $tenantId]);

    // Zelfde flow als bij mail/handmatige bevestiging: ritten voor live planbord aanmaken
    maakParapluRittenAan($pdo, $calculatie_id);

    // 3. Stuur de definitieve bevestigingsmail naar de klant
    $naar_email = $rit['email'];
    $onderwerp = "Definitieve Ritbevestiging #" . $orderNummer . " - BusAI";
    
    $baseUrl = rtrim((string) env_value('APP_BASE_URL', ''), '/');
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    $pdf_link = $baseUrl . '/beheer/calculatie/pdf_bevestiging.php?id=' . rawurlencode((string) $calculatie_id) . '&token=' . rawurlencode($pdfToken);

    $bericht = "Beste " . $klantNaam . ",\n\n";
    $bericht .= "Goed nieuws! Wij hebben uw rit definitief voor u ingepland.\n\n";
    $bericht .= "U kunt uw definitieve ritbevestiging (met alle afgesproken tijden en adressen) bekijken en downloaden via onderstaande link:\n\n";
    $bericht .= $pdf_link . "\n\n";
    $bericht .= "Mochten er nog vragen zijn, neem dan gerust contact met ons op.\n\n";
    $bericht .= "Wij wensen u alvast een veilige en prettige reis toe!\n\n";
    $bericht .= "Met vriendelijke groeten,\n\n";
    $bericht .= "BusAI\n";
    $bericht .= "T: 0575-525345\n";

    $headers = "From: info@busai.nl\r\n";
    $headers .= "Reply-To: info@busai.nl\r\n";

    if (@mail($naar_email, $onderwerp, $bericht, $headers)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Status geüpdatet, maar kon de e-mail niet versturen via de server.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
?>