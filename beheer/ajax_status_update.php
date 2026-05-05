<?php
// Bestand: beheer/ajax_status_update.php
// Doel: Update de status (datum) van een document in de database (tenant-safe).

declare(strict_types=1);

require_once __DIR__ . '/../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/split_ritten.php';

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode((string) file_get_contents('php://input'), true) ?: [];
$id = isset($input['id']) ? (int) $input['id'] : 0;
$type = isset($input['type']) ? (string) $input['type'] : '';
$datum = isset($input['datum']) ? (string) $input['datum'] : date('Y-m-d H:i:s');
$actie = isset($input['actie']) ? (string) $input['actie'] : 'opslaan';
$entity = isset($input['entity']) ? (string) $input['entity'] : 'calculatie';

$tenantId = current_tenant_id();

if ($tenantId <= 0 || $id <= 0 || $type === '') {
    echo json_encode(['success' => false, 'message' => 'Ongeldige invoer']);
    exit;
}

if ($entity === 'sales_rit_dossier') {
    require_once __DIR__ . '/includes/sales_rit_dossiers.php';
    sales_rit_dossiers_ensure_schema($pdo);
    $kolomSales = '';
    if ($type === 'offerte') {
        $kolomSales = 'datum_prijs_gedeeld';
    } elseif ($type === 'bevestiging') {
        $kolomSales = 'datum_klant_akkoord';
    } else {
        echo json_encode(['success' => false, 'message' => 'Alleen offerte- of bevestigingsdatum voor sales-ritten.']);
        exit;
    }
    try {
        if ($actie === 'verwijderen') {
            $st = $pdo->prepare("UPDATE sales_rit_dossiers SET `$kolomSales` = NULL WHERE id = ? AND tenant_id = ?");
            $st->execute([$id, $tenantId]);
        } else {
            $datumVal = $datum;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumVal)) {
                $datumVal .= ' 12:00:00';
            }
            $st = $pdo->prepare("UPDATE sales_rit_dossiers SET `$kolomSales` = ? WHERE id = ? AND tenant_id = ?");
            $st->execute([$datumVal, $id, $tenantId]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$kolom = '';
if ($type === 'offerte') {
    $kolom = 'datum_offerte_verstuurd';
}
if ($type === 'bevestiging') {
    $kolom = 'datum_bevestiging_verstuurd';
}
if ($type === 'ritopdracht') {
    $kolom = 'datum_ritopdracht_verstuurd';
}
if ($type === 'factuur') {
    $kolom = 'datum_factuur_verstuurd';
}

if ($type === 'betaald') {
    $sql = 'UPDATE calculaties SET is_betaald = ? WHERE id = ? AND tenant_id = ?';
    $waarde = ($actie === 'opslaan') ? 1 : 0;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$waarde, $id, $tenantId]);
    echo json_encode(['success' => true, 'nieuwe_status' => $waarde]);
    exit;
}

if ($kolom === '') {
    echo json_encode(['success' => false, 'message' => 'Onbekend type']);
    exit;
}

$allowedKolom = [
    'datum_offerte_verstuurd',
    'datum_bevestiging_verstuurd',
    'datum_ritopdracht_verstuurd',
    'datum_factuur_verstuurd',
];
if (!in_array($kolom, $allowedKolom, true)) {
    echo json_encode(['success' => false, 'message' => 'Ongeldige kolom']);
    exit;
}

try {
    if ($actie === 'verwijderen') {
        $stmt = $pdo->prepare("UPDATE calculaties SET `$kolom` = NULL WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
    } else {
        $stmt = $pdo->prepare("UPDATE calculaties SET `$kolom` = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$datum, $id, $tenantId]);

        if ($type === 'bevestiging' && $actie === 'opslaan') {
            maakParapluRittenAan($pdo, $id);
        }
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
