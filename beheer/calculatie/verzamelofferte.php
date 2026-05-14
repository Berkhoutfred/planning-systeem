<?php

declare(strict_types=1);

require_once __DIR__ . '/../../beveiliging.php';
require_role(['tenant_admin', 'planner_user']);
require_once __DIR__ . '/../includes/db.php';

$tenantId = current_tenant_id();
if ($tenantId <= 0) {
    die('<div style="padding:20px;">Geen tenantcontext.</div>');
}

function offerte_verzamel_tabellen_bestaat(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'offerte_verzamelingen'");
        return $stmt !== false && $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$tabellenOk = offerte_verzamel_tabellen_bestaat($pdo);
$csrf = auth_get_csrf_token();
$fout = '';

if ($tabellenOk && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!auth_validate_csrf_token($_POST['auth_csrf_token'] ?? null)) {
        $fout = 'Ongeldige sessie; vernieuw de pagina en probeer opnieuw.';
    } else {
        $titel = trim((string) ($_POST['titel'] ?? ''));
        if ($titel === '') {
            $titel = 'Verzamelofferte';
        }
        if (function_exists('mb_substr')) {
            $titel = mb_substr($titel, 0, 255, 'UTF-8');
        } else {
            $titel = substr($titel, 0, 255);
        }
        $ids = $_POST['calc_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
        if ($ids === []) {
            $fout = 'Selecteer minimaal één offerte.';
        } elseif (count($ids) > 40) {
            $fout = 'Maximaal 40 offertes per verzameling.';
        } else {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $chk = $pdo->prepare("SELECT id FROM calculaties WHERE tenant_id = ? AND id IN ($ph)");
            $chk->execute(array_merge([$tenantId], $ids));
            $gevonden = $chk->fetchAll(PDO::FETCH_COLUMN);
            $gevonden = is_array($gevonden) ? array_map('intval', $gevonden) : [];
            sort($gevonden);
            $idsSorted = $ids;
            sort($idsSorted);
            if ($gevonden !== $idsSorted) {
                $fout = 'Een of meer offertes horen niet bij deze tenant of bestaan niet.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $insB = $pdo->prepare('INSERT INTO offerte_verzamelingen (tenant_id, titel) VALUES (?, ?)');
                    $insB->execute([$tenantId, $titel]);
                    $vzId = (int) $pdo->lastInsertId();
                    if ($vzId <= 0) {
                        throw new RuntimeException('Aanmaken mislukt.');
                    }
                    $insI = $pdo->prepare(
                        'INSERT INTO offerte_verzameling_items (tenant_id, verzameling_id, calculatie_id, sort_order) VALUES (?, ?, ?, ?)'
                    );
                    foreach ($ids as $sort => $cid) {
                        $insI->execute([$tenantId, $vzId, $cid, $sort]);
                    }
                    $pdo->commit();
                    header('Location: verzamelofferte_pdf.php?vz_id=' . $vzId);
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $fout = 'Opslaan mislukt. Controleer of de databasemigratie is uitgevoerd.';
                }
            }
        }
    }
}

$lijst = [];
if ($tabellenOk) {
    $st = $pdo->prepare(
        'SELECT c.id, c.titel, c.rit_datum, c.rit_datum_eind, c.prijs, c.status,
                k.bedrijfsnaam, k.voornaam, k.achternaam
         FROM calculaties c
         LEFT JOIN klanten k ON k.id = c.klant_id AND k.tenant_id = c.tenant_id
         WHERE c.tenant_id = ?
         ORDER BY c.rit_datum DESC, c.id DESC
         LIMIT 350'
    );
    $st->execute([$tenantId]);
    $lijst = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pageTitle = 'Verzamelofferte';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div style="max-width:960px;margin:24px auto;padding:24px;background:#fff;border-radius:8px;">
    <h1 style="color:#003366;margin-top:0;"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="color:#555;">Selecteer meerdere calculaties en genereer één PDF met per offerte dezelfde secties als de standaard offerte-PDF.</p>

    <?php if (!$tabellenOk): ?>
        <div style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:16px;border-radius:6px;">
            De tabellen voor verzameloffertes ontbreken nog. Voer de migratie uit:
            <code style="display:block;margin-top:8px;">migrations/20260513_offerte_verzamelingen.sql</code>
        </div>
    <?php else: ?>
        <?php if ($fout !== ''): ?>
            <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:6px;margin-bottom:16px;"><?= htmlspecialchars($fout, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="verzamelofferte.php">
            <input type="hidden" name="auth_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label style="display:block;font-weight:600;color:#003366;margin-bottom:6px;">Titel op voorblad</label>
            <input type="text" name="titel" style="max-width:480px;margin:8px 0 20px;padding:8px;width:100%;border:1px solid #ccc;border-radius:4px;" maxlength="255" value="Verzamelofferte" required>

            <div style="max-height:480px;overflow:auto;border:1px solid #e0e6ed;border-radius:6px;">
                <table style="width:100%;border-collapse:collapse;margin:0;font-size:13px;">
                    <thead style="position:sticky;top:0;background:#f4f6f9;z-index:1;">
                        <tr style="border-bottom:2px solid #dde4ec;">
                            <th style="padding:8px;width:40px;"></th>
                            <th style="padding:8px;text-align:left;">#</th>
                            <th style="padding:8px;text-align:left;">Datum</th>
                            <th style="padding:8px;text-align:left;">Klant</th>
                            <th style="padding:8px;text-align:left;">Titel</th>
                            <th style="padding:8px;text-align:right;">Prijs excl.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lijst as $row): ?>
                            <?php
                            $kid = (int) ($row['id'] ?? 0);
                            $klant = trim((string) ($row['bedrijfsnaam'] ?? ''));
                            if ($klant === '') {
                                $klant = trim((string) ($row['voornaam'] ?? '') . ' ' . (string) ($row['achternaam'] ?? ''));
                            }
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:6px;"><input type="checkbox" name="calc_ids[]" value="<?= $kid ?>"></td>
                                <td style="padding:6px;"><?= $kid ?></td>
                                <td style="padding:6px;"><?= htmlspecialchars((string) ($row['rit_datum'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="padding:6px;"><?= htmlspecialchars($klant !== '' ? $klant : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="padding:6px;"><?= htmlspecialchars((string) ($row['titel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="padding:6px;text-align:right;">€ <?= number_format((float) ($row['prijs'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="font-size:12px;color:#666;">Toont de <?= count($lijst) ?> meest recente calculaties van jouw tenant.</p>
            <button type="submit" style="margin-top:12px;padding:10px 20px;font-weight:bold;background:#003366;color:#fff;border:none;border-radius:5px;cursor:pointer;">
                <i class="fas fa-file-pdf"></i> PDF genereren
            </button>
            <a href="../calculaties.php" style="margin-top:12px;margin-left:8px;display:inline-block;padding:10px 16px;background:#6c757d;color:#fff;text-decoration:none;border-radius:5px;">Terug</a>
        </form>
    <?php endif; ?>
</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
