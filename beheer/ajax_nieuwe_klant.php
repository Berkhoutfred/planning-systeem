<?php
// Bestand: beheer/ajax_nieuwe_klant.php
// Doel: Slaat veilig een nieuwe klant op vanuit de offerte pop-up en voorkomt dubbele!

header('Content-Type: application/json');
require 'includes/db.php';

// Gegevens netjes oppakken uit het formulier
$bedrijf = trim($_POST['bedrijfsnaam'] ?? '');
$voornaam = trim($_POST['voornaam'] ?? '');
$achternaam = trim($_POST['achternaam'] ?? '');
$adres = trim($_POST['adres'] ?? '');
$plaats = trim($_POST['plaats'] ?? '');
$telefoon = trim($_POST['telefoon'] ?? '');
$email = trim($_POST['email'] ?? '');

// Beveiliging: Bedrijfsnaam óf Achternaam is verplicht
if(empty($bedrijf) && empty($achternaam)) {
    echo json_encode(['success' => false, 'message' => 'Vul minimaal een Bedrijfsnaam of Achternaam in.']);
    exit;
}

// Dubbele invoer controle (Zoeken of we deze al hebben)
$stmt = $pdo->prepare("SELECT id FROM klanten WHERE (bedrijfsnaam = ? AND bedrijfsnaam != '') OR (achternaam = ? AND achternaam != '' AND email = ? AND email != '') LIMIT 1");
$stmt->execute([$bedrijf, $achternaam, $email]);
if($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Let op: Deze klant of dit e-mailadres staat al in de database! Typ hem in via de zoekbalk.']);
    exit;
}

try {
    // Hoera, nieuwe klant opslaan!
    $stmt = $pdo->prepare("INSERT INTO klanten (bedrijfsnaam, voornaam, achternaam, adres, plaats, telefoon, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$bedrijf, $voornaam, $achternaam, $adres, $plaats, $telefoon, $email]);
    
    $nieuw_id = $pdo->lastInsertId();
    $weergave_naam = !empty($bedrijf) ? $bedrijf : trim($voornaam . ' ' . $achternaam);

    // Stuur succes en de nieuwe gegevens terug naar het offerte scherm
    echo json_encode([
        'success' => true, 
        'klant' => [
            'id' => $nieuw_id,
            'weergave_naam' => $weergave_naam,
            'adres' => $adres,
            'plaats' => $plaats,
            'telefoon' => $telefoon,
            'email' => $email
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
?>