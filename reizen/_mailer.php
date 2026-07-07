<?php
declare(strict_types=1);
// Bestand: reizen/_mailer.php — mail helper voor busreizen module

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function busreis_stuur_bevestiging(array $boeking, array $reis, array $deelnemers, ?array $halte, PDO $pdo): bool
{
    $phpmailerBase = dirname(__DIR__) . '/beheer/includes/PHPMailer/';
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'PHPMailer.php';
    require_once $phpmailerBase . 'SMTP.php';
    require_once __DIR__ . '/_prijs.php';

    $nl_maanden = ['January'=>'Januari','February'=>'Februari','March'=>'Maart','April'=>'April',
        'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Augustus','September'=>'September',
        'October'=>'Oktober','November'=>'November','December'=>'December'];

    $datumStr = strtr(date('d F Y', strtotime($reis['datum_van'])), $nl_maanden);
    if ($reis['type'] === 'meerdaags' && !empty($reis['datum_tot'])) {
        $datumStr .= ' – ' . strtr(date('d F Y', strtotime($reis['datum_tot'])), $nl_maanden);
    }

    $opties = json_decode($boeking['gekozen_opties'] ?? '[]', true) ?: [];
    $optiesPerPersoon = array_sum(array_column($opties, 'prijs'));
    $boekingTs = !empty($boeking['aangemaakt_op']) ? strtotime((string) $boeking['aangemaakt_op']) : time();
    $prijs = busreis_bereken_prijs(
        $reis,
        (int) $boeking['aantal_deelnemers'],
        (int) ($boeking['enkelpersoon_toeslag'] ?? 0),
        (float) $optiesPerPersoon,
        $boekingTs ?: null
    );
    $totaal = number_format((float)$boeking['totaal'], 2, ',', '.');
    $ref    = $boeking['boeking_ref'];

    // ── HTML EMAIL BODY ─────────────────────────────────────────────────────────
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="nl" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<title>Boekingsbevestiging <?= htmlspecialchars($ref, ENT_QUOTES) ?></title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
body{margin:0!important;padding:0!important;background-color:#eef2f7}
@media only screen and (max-width:620px){
  .wrapper{width:100%!important;padding:0 8px!important}
  .main{border-radius:0!important}
  .hide-mobile{display:none!important}
  .stack-col td{display:block!important;width:100%!important}
  .pad-m{padding:20px 16px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

<!-- Preheader tekst (verborgen) -->
<div style="display:none;font-size:1px;color:#eef2f7;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
Uw boeking voor <?= htmlspecialchars($reis['titel'], ENT_QUOTES) ?> is bevestigd! Referentie: <?= htmlspecialchars($ref, ENT_QUOTES) ?>
</div>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#eef2f7;">
<tr><td align="center" style="padding:24px 16px;">

  <table class="wrapper" width="600" cellpadding="0" cellspacing="0" role="presentation">

    <!-- ── HEADER ────────────────────────────────────────────────────── -->
    <tr><td class="main" style="border-radius:16px 16px 0 0;overflow:hidden;">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td style="background:linear-gradient(135deg,#001d42 0%,#002f6e 55%,#004aad 100%);
                     padding:28px 36px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr>
                <td>
                  <div style="font-size:20px;font-weight:900;color:#ffffff;letter-spacing:-0.3px;">
                    &#128660; Coach Travel
                    <span style="color:#5bc8f5;">&times; Berkhout Reizen</span>
                  </div>
                  <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:4px;">Uw reisspecialist per luxe touringcar</div>
                </td>
                <td align="right" class="hide-mobile">
                  <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                      <td style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
                                 border-radius:20px;padding:5px 14px;">
                        <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.8);letter-spacing:.5px;">SGR BESCHERMD</div>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Hero: check + bevestiging -->
        <tr>
          <td style="background:#002855;padding:36px 36px 28px;text-align:center;">
            <div style="width:64px;height:64px;background:rgba(34,197,94,.15);border:2px solid rgba(34,197,94,.4);
                        border-radius:50%;margin:0 auto 16px;line-height:64px;font-size:28px;">&#10003;</div>
            <div style="font-size:26px;font-weight:900;color:#ffffff;line-height:1.2;margin-bottom:8px;">
              Uw boeking is bevestigd!
            </div>
            <div style="font-size:14px;color:rgba(255,255,255,.65);line-height:1.6;max-width:420px;margin:0 auto 22px;">
              Bedankt, <strong style="color:#fff;"><?= htmlspecialchars($boeking['voornaam'], ENT_QUOTES) ?></strong>.
              Uw betaling is ontvangen en uw plaatsen zijn gereserveerd voor
              <em style="color:#5bc8f5;"><?= htmlspecialchars($reis['titel'], ENT_QUOTES) ?></em>.
            </div>
            <!-- Referentienummer -->
            <table cellpadding="0" cellspacing="0" role="presentation" align="center">
              <tr>
                <td style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);
                           border-radius:8px;padding:10px 24px;">
                  <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.5);letter-spacing:.8px;text-transform:uppercase;margin-bottom:4px;">Uw boekingsreferentie</div>
                  <div style="font-size:22px;font-weight:900;color:#5bc8f5;letter-spacing:2px;"><?= htmlspecialchars($ref, ENT_QUOTES) ?></div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td></tr>

    <!-- ── REIS DETAILS ──────────────────────────────────────────────── -->
    <tr><td style="background:#ffffff;padding:0 36px;" class="pad-m">

      <!-- Sectie: reis -->
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:28px;">
        <tr>
          <td style="border-bottom:2px solid #eef2f7;padding-bottom:10px;margin-bottom:16px;">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;">Uw Reis</div>
          </td>
        </tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f8faff;border-radius:10px;border:1px solid #e8eef7;margin:12px 0 24px;overflow:hidden;">
        <tr>
          <td style="padding:20px 22px;">
            <div style="font-size:18px;font-weight:900;color:#002855;margin-bottom:14px;line-height:1.25;">
              <?= htmlspecialchars($reis['titel'], ENT_QUOTES) ?>
            </div>
            <!-- Info rijen -->
            <?php
            $rows = [
                ['&#128197;', 'Datum', $datumStr],
                ['&#128336;', 'Vertregtijd', $reis['vertrek_tijd'] ? substr($reis['vertrek_tijd'],0,5).' uur' : '—'],
                ['&#128205;', 'Bestemming', $reis['bestemming'] ?? '—'],
                ['&#128101;', 'Deelnemers', $boeking['aantal_deelnemers'] . ' ' . ($boeking['aantal_deelnemers']==1?'persoon':'personen')],
            ];
            if ($halte) {
                $rows[] = ['&#128652;', 'Opstapplaats', $halte['naam'] . ($halte['vertrek_tijd'] ? ' om '.substr($halte['vertrek_tijd'],0,5) : '')];
            }
            if ($reis['type']==='meerdaags' && $reis['hotel_naam']) {
                $rows[] = ['&#127970;', 'Hotel', $reis['hotel_naam'] . ($reis['hotel_sterren'] ? ' ('.str_repeat('★',(int)$reis['hotel_sterren']).')' : '')];
            }
            foreach ($rows as [$icon, $label, $val]): ?>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:8px;">
              <tr>
                <td width="24" style="font-size:14px;vertical-align:top;padding-top:1px;"><?= $icon ?></td>
                <td width="130" style="font-size:13px;color:#64748b;vertical-align:top;padding-left:8px;"><?= $label ?></td>
                <td style="font-size:13px;font-weight:600;color:#1a2533;vertical-align:top;"><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></td>
              </tr>
            </table>
            <?php endforeach; ?>
          </td>
        </tr>
      </table>

      <!-- Deelnemers -->
      <?php if (!empty($deelnemers)): ?>
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr><td style="border-bottom:2px solid #eef2f7;padding-bottom:10px;margin-bottom:12px;">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;">Deelnemers</div>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:12px 0 24px;">
        <?php foreach ($deelnemers as $i => $d): ?>
        <tr>
          <td style="padding:7px 14px;background:<?= $i%2===0 ? '#f8faff' : '#fff' ?>;border-radius:6px;font-size:13px;color:#374151;">
            <strong style="color:#002855;"><?= (int)$i+1 ?>.</strong>
            <?= htmlspecialchars($d['voornaam'].' '.$d['achternaam'], ENT_QUOTES) ?>
            <?php if ((int)$d['is_hoofdboeker']): ?>
            <span style="font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:10px;margin-left:6px;font-weight:600;">Hoofdboeker</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>

      <!-- Prijsoverzicht -->
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr><td style="border-bottom:2px solid #eef2f7;padding-bottom:10px;">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;">Prijsoverzicht</div>
        </td></tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:12px 0 0;">
        <tr>
          <td style="font-size:13px;color:#374151;padding:6px 0;">
            Prijs (<?= (int)$boeking['aantal_deelnemers'] ?> pers. &times; &euro; <?= number_format((float)$reis['prijs_pp'],2,',','.') ?>)
          </td>
          <td align="right" style="font-size:13px;color:#374151;padding:6px 0;white-space:nowrap;">
            &euro; <?= number_format((float)$reis['prijs_pp'] * $boeking['aantal_deelnemers'], 2, ',', '.') ?>
          </td>
        </tr>
        <?php if ($boeking['enkelpersoon_toeslag'] && $reis['toeslag_enkelpersoon'] > 0): ?>
        <tr>
          <td style="font-size:13px;color:#374151;padding:6px 0;">Toeslag 1-persoonskamer</td>
          <td align="right" style="font-size:13px;color:#374151;padding:6px 0;white-space:nowrap;">
            &euro; <?= number_format((float)$reis['toeslag_enkelpersoon'] * $boeking['aantal_deelnemers'], 2, ',', '.') ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php foreach ($opties as $o): ?>
        <tr>
          <td style="font-size:13px;color:#374151;padding:6px 0;"><?= htmlspecialchars($o['naam'], ENT_QUOTES) ?> (&times;<?= (int)$boeking['aantal_deelnemers'] ?>)</td>
          <td align="right" style="font-size:13px;color:#374151;padding:6px 0;white-space:nowrap;">
            &euro; <?= number_format((float)$o['prijs'] * $boeking['aantal_deelnemers'], 2, ',', '.') ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($prijs['vroegboek_totaal'] > 0): ?>
        <tr>
          <td style="font-size:13px;color:#15803d;padding:6px 0;">Vroegboekkorting (&times;<?= (int)$boeking['aantal_deelnemers'] ?>)</td>
          <td align="right" style="font-size:13px;color:#15803d;padding:6px 0;white-space:nowrap;">
            - &euro; <?= number_format((float)$prijs['vroegboek_totaal'], 2, ',', '.') ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php if ($boeking['reserveringskosten'] > 0): ?>
        <tr>
          <td style="font-size:13px;color:#374151;padding:6px 0;">Reserveringskosten</td>
          <td align="right" style="font-size:13px;color:#374151;padding:6px 0;white-space:nowrap;">
            &euro; <?= number_format((float)$boeking['reserveringskosten'], 2, ',', '.') ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td colspan="2" style="border-top:2px solid #e2e8f0;padding-top:8px;"></td>
        </tr>
        <tr>
          <td style="font-size:16px;font-weight:900;color:#002855;padding:4px 0 20px;">Totaal betaald</td>
          <td align="right" style="font-size:16px;font-weight:900;color:#16a34a;padding:4px 0 20px;white-space:nowrap;">
            &euro; <?= $totaal ?>
          </td>
        </tr>
      </table>

    </td></tr>

    <!-- ── WAT NU? ────────────────────────────────────────────────────── -->
    <tr><td style="background:#f8faff;border-top:1px solid #e8eef7;border-bottom:1px solid #e8eef7;padding:28px 36px;" class="pad-m">
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin-bottom:18px;">Wat kunt u verwachten?</div>
      <?php
      $stappen = [
          ['color'=>'#004aad','icon'=>'&#9993;&#65039;','titel'=>'Bevestiging per e-mail',
           'tekst'=>'U heeft zojuist deze bevestiging ontvangen op '.$boeking['email'].'. Bewaar deze e-mail goed.'],
          ['color'=>'#7c3aed','icon'=>'&#128203;','titel'=>'Definitieve reisinformatie',
           'tekst'=>'Uiterlijk 1 week voor vertrek ontvangt u de precieze vertrektijden, praktische tips en reisinformatie.'],
          ['color'=>'#16a34a','icon'=>'&#128652;','titel'=>'Op de dag van vertrek',
           'tekst'=>'Wees 10 minuten voor vertrek aanwezig op uw opstapplaats. Uw chauffeur en reisleider staan voor u klaar.'],
      ];
      foreach ($stappen as $i => $s): ?>
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:16px;">
        <tr>
          <td width="42" valign="top">
            <div style="width:36px;height:36px;background:<?= $s['color'] ?>;border-radius:50%;
                        text-align:center;line-height:36px;font-size:16px;"><?= $s['icon'] ?></div>
          </td>
          <td valign="top" style="padding-left:12px;">
            <div style="font-size:13.5px;font-weight:700;color:#002855;margin-bottom:3px;"><?= $s['titel'] ?></div>
            <div style="font-size:12.5px;color:#64748b;line-height:1.55;"><?= $s['tekst'] ?></div>
          </td>
        </tr>
      </table>
      <?php endforeach; ?>
    </td></tr>

    <!-- ── NOODCONTACT ────────────────────────────────────────────────── -->
    <?php if ($boeking['telefoon_thuisblijver']): ?>
    <tr><td style="background:#fff8ed;border-bottom:1px solid #fde68a;padding:16px 36px;" class="pad-m">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td width="28" valign="top" style="font-size:18px;">&#128222;</td>
          <td style="padding-left:10px;">
            <div style="font-size:12px;font-weight:700;color:#92400e;">Noodcontact thuisblijver</div>
            <div style="font-size:13px;color:#374151;margin-top:2px;"><?= htmlspecialchars($boeking['telefoon_thuisblijver'], ENT_QUOTES) ?></div>
          </td>
        </tr>
      </table>
    </td></tr>
    <?php endif; ?>

    <!-- ── CONTACT ────────────────────────────────────────────────────── -->
    <tr><td style="background:#ffffff;padding:24px 36px;" class="pad-m">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td style="text-align:center;">
            <div style="font-size:14px;font-weight:700;color:#002855;margin-bottom:6px;">Vragen over uw boeking?</div>
            <div style="font-size:13px;color:#64748b;margin-bottom:14px;">Wij helpen u graag verder.</div>
            <table cellpadding="0" cellspacing="0" role="presentation" align="center">
              <tr>
                <td style="background:#002855;border-radius:8px;padding:11px 24px;">
                  <a href="tel:0854862007" style="color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                    &#128222; 085 - 486 20 07
                  </a>
                </td>
                <td width="12"></td>
                <td style="background:#f1f5f9;border-radius:8px;padding:11px 24px;">
                  <a href="mailto:info@coachtravel.nl" style="color:#002855;text-decoration:none;font-size:14px;font-weight:600;">
                    &#9993; info@coachtravel.nl
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td></tr>

    <!-- ── FOOTER ─────────────────────────────────────────────────────── -->
    <tr><td style="background:#002855;border-radius:0 0 16px 16px;padding:22px 36px 26px;">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td style="text-align:center;">
            <!-- Trust badges -->
            <table cellpadding="0" cellspacing="0" role="presentation" align="center" style="margin-bottom:14px;">
              <tr>
                <td style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,.6);">SGR</td>
                <td width="8"></td>
                <td style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,.6);">ANVR</td>
                <td width="8"></td>
                <td style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,.6);">MOLLIE BETALING</td>
                <td width="8"></td>
                <td style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:6px;padding:5px 12px;font-size:11px;font-weight:700;color:rgba(255,255,255,.6);">CALAMITEITENFONDS</td>
              </tr>
            </table>
            <div style="font-size:11px;color:rgba(255,255,255,.45);line-height:1.6;">
              Coach Travel &times; Berkhout Reizen &mdash; <?= date('Y') ?><br>
              Postbus 123 &mdash; info@coachtravel.nl &mdash; 085 - 486 20 07<br>
              <span style="color:rgba(255,255,255,.3);">
                U ontvangt dit bericht omdat u een boeking heeft geplaatst via onze website.
                Referentie: <?= htmlspecialchars($ref, ENT_QUOTES) ?>
              </span>
            </div>
          </td>
        </tr>
      </table>
    </td></tr>

  </table><!-- /wrapper -->
</td></tr>
</table><!-- /outer -->

</body>
</html>
    <?php
    $html = ob_get_clean();

    // ── PLAIN TEXT FALLBACK ──────────────────────────────────────────────────
    $text  = "BOEKINGSBEVESTIGING — {$ref}\n";
    $text .= str_repeat('=', 50) . "\n\n";
    $text .= "Coach Travel × Berkhout Reizen\n\n";
    $text .= "Beste {$boeking['voornaam']},\n\n";
    $text .= "Uw boeking is bevestigd. Hieronder vindt u de details.\n\n";
    $text .= "REIS: {$reis['titel']}\n";
    $text .= "Datum: {$datumStr}\n";
    $text .= "Deelnemers: {$boeking['aantal_deelnemers']}\n";
    $text .= "Totaal: € {$totaal}\n\n";
    $text .= "Vragen? Bel 085 - 486 20 07 of mail info@coachtravel.nl\n";

    // ── MAIL VERSTUREN ───────────────────────────────────────────────────────
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet  = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = (string)env_value('SMTP_HOST', 'smtp.hostinger.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)env_value('SMTP_USER', '');
        $mail->Password   = (string)env_value('SMTP_PASS', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)env_value('SMTP_PORT', '465');

        $mail->setFrom(
            (string)env_value('SMTP_FROM_EMAIL', 'info@berkhoutreizen.nl'),
            (string)env_value('SMTP_FROM_NAME', 'Coach Travel × Berkhout Reizen')
        );
        $mail->addAddress($boeking['email'], $boeking['voornaam'] . ' ' . $boeking['achternaam']);
        $mail->addReplyTo('info@coachtravel.nl', 'Coach Travel Reizen');

        // BCC naar administratie
        $adminMail = (string)env_value('SMTP_ADMIN_USER', 'administratie@taxiberkhout.nl');
        if ($adminMail) $mail->addBCC($adminMail, 'Administratie');

        $mail->isHTML(true);
        $mail->Subject  = "✅ Bevestiging: {$reis['titel']} | Ref. {$ref}";
        $mail->Body     = $html;
        $mail->AltBody  = $text;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[BUSREIZEN MAIL] ' . $e->getMessage());
        return false;
    }
}
