<?php
// Bestand: beheer/calculatie-bewerken.php
// Legacy bewerkscherm — tenant-safe; instellingen via tenant_calculatie_instellingen_merged().

declare(strict_types=1);

require_once __DIR__ . '/../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/includes/header.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die("<div class='container' style='padding:20px;'><h3>Tenant context ontbreekt.</h3><a href='calculaties.php'>Terug</a></div>");
}

if (!isset($_GET['id']) || $_GET['id'] === '' || $_GET['id'] === '0') {
    die("<div class='container' style='padding:20px;'><h3>Geen ID opgegeven.</h3><a href='calculaties.php'>Terug</a></div>");
}

$id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare('SELECT * FROM calculaties WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$id, $tenantId]);
    $rit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rit) {
        die("<div class='container' style='padding:20px;'><h3>Rit niet gevonden of niet van jouw tenant.</h3><a href='calculaties.php'>Terug</a></div>");
    }

    $instellingen = tenant_calculatie_instellingen_merged($pdo, $tenantId);
    $btwMul = 1 + ($instellingen['btw_nl'] / 100.0);

    $opgeslagen_prijs_excl = (float) $rit['prijs'];
    $opgeslagen_prijs_incl = $opgeslagen_prijs_excl * $btwMul;
    $opgeslagen_prijs_incl_afgerond = ceil($opgeslagen_prijs_incl / 5.0) * 5.0;

    $stmtRegels = $pdo->prepare(
        'SELECT * FROM calculatie_regels WHERE calculatie_id = ? AND tenant_id = ?'
    );
    $stmtRegels->execute([$id, $tenantId]);
    $dbRegels = $stmtRegels->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($dbRegels as $r) {
        $tijdKort = substr((string) $r['tijd'], 0, 5);
        $data[$r['type']] = [
            'tijd' => $tijdKort,
            'adres' => $r['adres'],
            'km' => $r['km'],
        ];
    }

    $stmtKlanten = $pdo->prepare(
        'SELECT id, bedrijfsnaam, voornaam, achternaam FROM klanten WHERE tenant_id = ? ORDER BY bedrijfsnaam ASC'
    );
    $stmtKlanten->execute([$tenantId]);
    $klanten = $stmtKlanten->fetchAll(PDO::FETCH_ASSOC);

    $stmtBussen = $pdo->prepare(
        'SELECT * FROM calculatie_voertuigen WHERE tenant_id = ? AND actief = 1 ORDER BY capaciteit ASC'
    );
    $stmtBussen->execute([$tenantId]);
    $bussen = $stmtBussen->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('DB Fout: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function val(array $data, string $rij, string $veld): string
{
    return isset($data[$rij][$veld]) ? htmlspecialchars((string) $data[$rij][$veld], ENT_QUOTES, 'UTF-8') : '';
}

$uurloonJs = (float) $instellingen['uurloon_basis'];
$ritDatumEind = (string) ($rit['rit_datum_eind'] ?? $rit['rit_datum'] ?? date('Y-m-d'));
$passagiers = (int) ($rit['passagiers'] ?? 0);
$kmTussen = (float) ($rit['km_tussen'] ?? 0);
$kmNl = (float) ($rit['km_nl'] ?? 0);
$kmDe = (float) ($rit['km_de'] ?? 0);
$instructie = (string) ($rit['instructie_kantoor'] ?? '');
$contactId = (int) ($rit['contact_id'] ?? 0);
$afdelingId = (int) ($rit['afdeling_id'] ?? 0);
?>

<style>
    .container { max-width: 1200px; margin: 0 auto; }
    .timeline-row { display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 5px; align-items: center; }
    .timeline-label { width: 140px; font-weight: bold; color: #555; }
    .timeline-time { width: 90px; }
    .timeline-addr { flex: 1; }
    .timeline-km { width: 80px; text-align: right; }
    .time-input { cursor: pointer; background-color: #fff; text-align: center; border: 1px solid #ccc; font-weight: bold; padding: 8px; width: 100%; border-radius: 4px; }
    .km-input { background: #f8f9fa; border: 1px solid #ccc; text-align: center; font-weight: bold; color: #555; padding: 8px; width: 100%; border-radius: 4px; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .totals-box { margin-top: 20px; padding: 20px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 5px; }
    #timeModal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 340px; border-radius: 10px; text-align: center; }
    .time-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 5px; margin-top: 10px; }
    .time-btn { padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .time-btn:hover { background: #ddd; }
    .close-btn { background: #ff4444; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; float: right; }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Bewerken (#<?= (int) $rit['id'] ?>)</h2>
        <a href="calculaties.php" style="background:#eee; padding:10px 20px; border-radius:4px; text-decoration:none; color:#333;">Terug</a>
    </div>

    <form action="calculatie/calculaties_update.php" method="POST" id="mainForm">
        <input type="hidden" name="id" value="<?= (int) $rit['id'] ?>">
        <input type="hidden" name="contact_id" value="<?= $contactId > 0 ? $contactId : '' ?>">
        <input type="hidden" name="afdeling_id" value="<?= $afdelingId > 0 ? $afdelingId : '' ?>">
        <input type="hidden" name="passagiers" value="<?= $passagiers ?>">
        <input type="hidden" name="rit_datum_eind" value="<?= htmlspecialchars($ritDatumEind, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="km_tussen" value="<?= htmlspecialchars((string) $kmTussen, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="km_nl" value="<?= htmlspecialchars((string) $kmNl, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="km_de" value="<?= htmlspecialchars((string) $kmDe, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="instructie_kantoor" value="<?= htmlspecialchars($instructie, ENT_QUOTES, 'UTF-8') ?>">

        <div style="background:#fff; padding:15px; border:1px solid #ddd; margin-bottom:20px; border-radius:5px;">
            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    <label>Klant:</label>
                    <select name="klant_id" class="form-control">
                        <?php foreach ($klanten as $k):
                            $sel = ((int) $k['id'] === (int) $rit['klant_id']) ? 'selected' : '';
                            $naam = !empty($k['bedrijfsnaam'])
                                ? (string) $k['bedrijfsnaam']
                                : trim((string) $k['voornaam'] . ' ' . (string) $k['achternaam']);
                            ?>
                            <option value="<?= (int) $k['id'] ?>" <?= $sel ?>><?= htmlspecialchars($naam, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="width:200px;">
                    <label>Datum:</label>
                    <input type="text" name="rit_datum" id="rit_datum" class="form-control" value="<?= htmlspecialchars((string) $rit['rit_datum'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="width:200px;">
                    <label>Type:</label>
                    <select name="rittype" class="form-control">
                        <option value="dagtocht" <?= ($rit['rittype'] === 'dagtocht' ? 'selected' : '') ?>>Dagtocht</option>
                        <option value="enkel" <?= ($rit['rittype'] === 'enkel' ? 'selected' : '') ?>>Enkele Reis</option>
                    </select>
                </div>
            </div>
        </div>

        <h3>Route &amp; Tijden</h3>

        <div id="timeline_container">
            <?php
            $rows = [
                't_garage' => 'Vertrek Garage',
                't_voorstaan' => 'Voorstaan',
                't_vertrek_klant' => 'Vertrekadres',
                't_aankomst_best' => 'Bestemming',
                't_vertrek_best' => 'Vertrek (Terug)',
                't_retour_klant' => 'Retour Klant',
                't_retour_garage' => 'Terug in Garage',
            ];
            foreach ($rows as $key => $label):
                ?>
            <div class="timeline-row" data-type="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                <div class="timeline-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="timeline-time">
                    <input type="text" name="time[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" class="form-control time-input time-picker-trigger" value="<?= val($data, $key, 'tijd') ?>" readonly>
                </div>
                <div class="timeline-addr">
                    <input type="text" id="addr_<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" name="addr[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" class="form-control addr-input" value="<?= val($data, $key, 'adres') ?>">
                </div>
                <div class="timeline-km">
                    <input type="text" name="km[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" class="form-control km-input" value="<?= val($data, $key, 'km') ?>" readonly>
                </div>
                <input type="hidden" name="label[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="totals-box">
            <div style="display:flex; gap:20px; align-items:flex-end;">
                <div class="form-group">
                    <label>Voertuig</label>
                    <select name="voertuig_id" id="voertuig_select" class="form-control">
                        <?php foreach ($bussen as $b):
                            $sel = ((int) $b['id'] === (int) $rit['voertuig_id']) ? 'selected' : '';
                            ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $sel ?> data-prijs="<?= htmlspecialchars((string) $b['km_kostprijs'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $b['naam'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Totaal</label>
                    <div style="display:flex; gap:5px;">
                        <input type="text" name="total_km" id="total_km" value="<?= htmlspecialchars((string) $rit['totaal_km'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" readonly style="width:70px;" placeholder="KM">
                        <input type="text" name="total_uren" id="total_uren" value="<?= htmlspecialchars((string) $rit['totaal_uren'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" readonly style="width:70px;" placeholder="Uren">
                    </div>
                </div>
                <div style="text-align:right; flex:1;">
                    <div style="margin-bottom:5px; font-size:12px; color:#666;">TOTAAL PRIJS INCL. <?= htmlspecialchars((string) $instellingen['btw_nl'], ENT_QUOTES, 'UTF-8') ?>% BTW (indicatie)</div>
                    <div style="font-size:28px; font-weight:bold; color:#333;">€ <span id="calc_price_display"><?= number_format($opgeslagen_prijs_incl_afgerond, 2, ',', '.') ?></span></div>
                    <label style="font-size:11px; color:#555;">Verkoopprijs excl. BTW (zo opgeslagen in database)</label>
                    <input type="number" step="0.01" name="verkoopprijs" id="calc_price_input" value="<?= htmlspecialchars((string) $opgeslagen_prijs_excl, ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="width:120px; text-align:right; float:right; display:block; border: 2px solid #0056b3;">
                </div>
            </div>
        </div>

        <br>
        <button type="submit" class="btn-save" style="background:#28a745; color:white; padding:15px; width:100%; border:none; font-size:18px; cursor:pointer; font-weight:bold; border-radius:5px;">
            WIJZIGINGEN OPSLAAN
        </button>
    </form>
</div>

<div id="timeModal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Kies Tijd</span>
            <button type="button" class="close-btn" onclick="closeTimeModal()">X</button>
        </div>
        <div id="modalGrid" class="time-grid"></div>
    </div>
</div>

<script>const UUR_TARIEF = <?= json_encode($uurloonJs, JSON_THROW_ON_ERROR) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/nl.js"></script>
<script>let isInitieleLading = true;</script>
<script src="calculatie/rekenmachine.js?v=<?= (int) time() ?>"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode((string) env_value('GOOGLE_MAPS_API_KEY', '')) ?>&libraries=places&callback=initMaps" async defer></script>
<script>
    flatpickr("#rit_datum", { locale: "nl", dateFormat: "Y-m-d" });

    window.addEventListener('load', function() {
        if (typeof calculateTotals === 'function') {
            calculateTotals();
        }
        setTimeout(() => { isInitieleLading = false; }, 1000);

        const priceInput = document.getElementById('calc_price_input');
        const priceDisplay = document.getElementById('calc_price_display');
        const btwFactor = <?= json_encode($btwMul, JSON_THROW_ON_ERROR) ?>;

        if (priceInput && priceDisplay) {
            priceInput.addEventListener('input', function() {
                const excl = parseFloat(this.value.replace(',', '.')) || 0;
                const incl = excl * btwFactor;
                priceDisplay.innerText = incl.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        }
    });

    if (typeof window.rekenen === 'function') {
        const oudeRekenen = window.rekenen;
        window.rekenen = function() {
            oudeRekenen();
            if (!isInitieleLading) {
                /* prijslogica blijft in rekenmachine.js waar van toepassing */
            }
        };
    }
</script>

</body>
</html>
