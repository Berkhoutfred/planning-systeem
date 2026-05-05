<?php
// Bestand: beheer/diesel_verstuur_actie.php
// VERSIE: 1.5 - Met persoonlijke [NAAM] vervanging!

include '../beveiliging.php';
require 'includes/db.php';
require_once 'includes/tenant_instellingen_db.php';
include 'includes/header.php';

require 'includes/PHPMailer/Exception.php';
require 'includes/PHPMailer/PHPMailer.php';
require 'includes/PHPMailer/SMTP.php';

echo "<div style='max-width: 900px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px;'>";
echo "<h1 style='color:#003366;'>E-mails Verzenden...</h1>";

$tenantId = function_exists('current_tenant_id') ? current_tenant_id() : 0;
$tenantCfg = tenant_instellingen_get($pdo, $tenantId);
$brandNaam = trim((string) ($tenantCfg['bedrijfsnaam'] ?? 'BusAI'));
$brandTel = trim((string) ($tenantCfg['telefoon'] ?? ''));
$brandEmail = trim((string) ($tenantCfg['email'] ?? env_value('SMTP_FROM_EMAIL', 'info@busai.nl')));
$brandLogoPad = trim((string) ($tenantCfg['logo_pad'] ?? ''));
$appBase = rtrim((string) env_value('APP_BASE_URL', ''), '/');
if ($appBase === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $appBase = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$logoUrl = $brandLogoPad !== '' ? ($appBase . '/beheer/' . ltrim($brandLogoPad, '/')) : ($appBase . '/beheer/images/berkhout_logo.png');
$webLabel = preg_replace('#^https?://#', '', $appBase);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['klant_id'])) {
    
    $onderwerp = trim($_POST['onderwerp']);
    $bericht_inhoud = trim($_POST['bericht']); // Hier staat nog [NAAM] in
    $klant_ids = $_POST['klant_id'];
    $klant_emails = $_POST['klant_email'];
    
    $succes_teller = 0;
    $fout_teller = 0;

    echo "<ul>";

    foreach ($klant_ids as $id) {
        $email = trim($klant_emails[$id]);
        
        if (empty($email)) {
            $fout_teller++;
            continue;
        }

        // --- NIEUW: HAAL DE NAAM VAN DE KLANT OP ---
        $stmt_naam = $pdo->prepare("SELECT bedrijfsnaam, voornaam, achternaam FROM klanten WHERE id = ?");
        $stmt_naam->execute([$id]);
        $klant_info = $stmt_naam->fetch();

        $klant_naam = '';
        if (!empty($klant_info['bedrijfsnaam'])) {
            $klant_naam = $klant_info['bedrijfsnaam']; // Gebruik bedrijfsnaam als die er is
        } else {
            $klant_naam = trim($klant_info['voornaam'] . ' ' . $klant_info['achternaam']); // Anders voor + achternaam
        }

        // Mocht er echt he-le-maal geen naam zijn ingevuld, val dan terug op 'klant'
        if (empty($klant_naam)) {
            $klant_naam = 'klant';
        }

        // --- VERVANG [NAAM] DOOR DE ECHTE NAAM ---
        $persoonlijk_bericht = str_replace('[NAAM]', $klant_naam, $bericht_inhoud);

        // Nu gaan we mailen
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = env_value('SMTP_HOST', 'smtp.hostinger.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env_value('SMTP_USER', ''); 
            $mail->Password   = env_value('SMTP_PASS', ''); 
            
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; 
            $mail->Port       = (int) env_value('SMTP_PORT', '465'); 

            $mail->setFrom(env_value('SMTP_FROM_EMAIL', $brandEmail), env_value('SMTP_FROM_NAME', $brandNaam));
            $mail->addAddress($email);

            $mail->isHTML(true); 
            $mail->Subject = $onderwerp;
            
            // Gebruik nu het PERSOONLIJKE bericht (waar [NAAM] is vervangen)
            $bericht_html = nl2br(htmlspecialchars($persoonlijk_bericht));
            
            $volledige_mail = "
            <div style='font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #000000; line-height: 1.5;'>
                {$bericht_html}
                <br><br>
                Met vriendelijke groet,<br><br>
                Fred Stravers<br>
                " . htmlspecialchars($brandNaam, ENT_QUOTES, 'UTF-8') . "<br>
                " . htmlspecialchars($brandTel, ENT_QUOTES, 'UTF-8') . "<br>
                <a href='" . htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') . "' style='color: #0000EE; text-decoration: underline;'>" . htmlspecialchars($webLabel, ENT_QUOTES, 'UTF-8') . "</a><br><br>
                <img src='" . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($brandNaam, ENT_QUOTES, 'UTF-8') . "' width='250' style='display: block; width: 250px; height: auto;'>
            </div>
            ";
            
            $mail->Body = $volledige_mail;
            $mail->AltBody = $persoonlijk_bericht . "\n\nMet vriendelijke groet,\n\nFred Stravers\n" . $brandNaam . "\n" . $brandTel . "\n" . $webLabel;

            $mail->send();
            
            $pdo->prepare("UPDATE klanten SET diesel_mail_gehad = 1 WHERE id = ?")->execute([$id]);

            $succes_teller++;
            echo "<li style='color:green;'>✅ Verzonden naar: $email (als: $klant_naam)</li>";

        } catch (Exception $e) {
            $fout_teller++;
            echo "<li style='color:red;'>❌ Fout bij $email - " . $mail->ErrorInfo . "</li>";
        }
    }
    echo "</ul>";
    echo "<p><strong>Klaar!</strong> $succes_teller gelukt, $fout_teller mislukt.</p>";

} else {
    echo "<p>Geen gegevens ontvangen.</p>";
}

echo "<br><a href='klanten.php' style='padding:10px 15px; background:#003366; color:white; text-decoration:none; border-radius:4px;'>Terug naar Klantenoverzicht</a>";
echo "</div>";

include 'includes/footer.php';
?>