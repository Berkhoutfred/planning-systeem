<?php
declare(strict_types=1);
// Bestand: beheer/reizen/bewerken.php

include '../../beveiliging.php';
require_role(['tenant_admin', 'planner_user', 'platform_owner']);
require '../includes/db.php';
require_once __DIR__ . '/_tenant_context.php';

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isNieuw  = $id === 0;

if ($isNieuw && !$magEigenReizenBewerken) {
    header('Location: index.php?msg=geen_toegang');
    exit;
}

// ── Bestaande reis ophalen ─────────────────────────────────
$reis = null;
$haltes = [];
$staffels = [];
$dagprogramma = [];
$opties = [];

if (!$isNieuw) {
    $reis = reis_ophaal_met_toegang($pdo, $reisCtx, $id);
    if (!$reis) {
        header('Location: index.php');
        exit;
    }
    if (!reis_mag_bewerken_voor_tenant($reisCtx, (int) $reis['tenant_id'])) {
        header('Location: index.php?msg=geen_toegang');
        exit;
    }
    $dataTenantId = (int) $reis['tenant_id'];
    $haltes = $pdo->prepare("SELECT * FROM busreis_haltes WHERE busreis_id=? ORDER BY sort_order ASC");
    $haltes->execute([$id]);
    $haltes = $haltes->fetchAll();

    $staffels = $pdo->prepare("SELECT * FROM busreis_staffels WHERE busreis_id=? ORDER BY pax_van ASC");
    $staffels->execute([$id]);
    $staffels = $staffels->fetchAll();

    $dagprogramma = $pdo->prepare("SELECT * FROM busreis_dagprogramma WHERE busreis_id=? ORDER BY sort_order, dag_nummer ASC");
    $dagprogramma->execute([$id]);
    $dagprogramma = $dagprogramma->fetchAll();

    $opties = $pdo->prepare("SELECT * FROM busreis_opties WHERE busreis_id=? ORDER BY sort_order ASC");
    $opties->execute([$id]);
    $opties = $opties->fetchAll();
}

// ── Opslaan ────────────────────────────────────────────────
$fout = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_validate_csrf_token($_POST['csrf'] ?? null)) {
        $fout = 'Sessie verlopen. Vernieuw de pagina.';
    } else {
        // Velden ophalen
        $type          = in_array($_POST['type'] ?? '', ['dagtocht','meerdaags'], true) ? $_POST['type'] : 'dagtocht';
        $vervoerder    = in_array($_POST['vervoerder'] ?? '', ['hartemink','berkhout','beide'], true) ? $_POST['vervoerder'] : 'berkhout';
        $titel         = trim($_POST['titel'] ?? '');
        $beschrijving  = trim($_POST['beschrijving'] ?? '');
        $bestemming    = trim($_POST['bestemming'] ?? '');
        $categorie     = trim($_POST['categorie'] ?? '');
        $datum_van     = trim($_POST['datum_van'] ?? '');
        $datum_tot     = trim($_POST['datum_tot'] ?? '') ?: null;
        $vertrek_tijd  = trim($_POST['vertrek_tijd'] ?? '') ?: null;
        $terug_tijd    = trim($_POST['terug_tijd'] ?? '') ?: null;
        $hotel_naam    = trim($_POST['hotel_naam'] ?? '') ?: null;
        $hotel_sterren = isset($_POST['hotel_sterren']) && $_POST['hotel_sterren'] !== '' ? (int)$_POST['hotel_sterren'] : null;
        $prijs_pp      = (float)str_replace(',', '.', $_POST['prijs_pp'] ?? '0');
        $toeslag_ep    = (float)str_replace(',', '.', $_POST['toeslag_enkelpersoon'] ?? '0');
        $reservk       = (float)str_replace(',', '.', $_POST['reserveringskosten'] ?? '15');
        $vroegboek     = (float)str_replace(',', '.', $_POST['vroegboekkorting'] ?? '0');
        $vroegdeadline = trim($_POST['vroegboek_deadline'] ?? '') ?: null;
        $max           = (int)($_POST['max_deelnemers'] ?? 50);
        $vertrekgar    = isset($_POST['vertrekgarantie']) ? 1 : 0;
        $anvr          = isset($_POST['anvr_sgr']) ? 1 : 0;
        $status        = in_array($_POST['status'] ?? '', ['concept','gepubliceerd','vol','archief'], true) ? $_POST['status'] : 'concept';

        // Slug genereren
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $titel)), '-'));
        if (!$slug) $slug = 'reis-' . time();

        // Foto upload
        $fotoPad = $reis['foto_pad'] ?? null;
        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                $uploadDir = '../../beheer/uploads/reizen/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $bestandsnaam = 'reis_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $bestandsnaam)) {
                    $fotoPad = 'beheer/uploads/reizen/' . $bestandsnaam;
                    require_once __DIR__ . '/../includes/reis_media.php';
                    reis_media_generate_variants($uploadDir . $bestandsnaam);
                }
            }
        }

        // Brochure PDF upload
        $brochurePdf = $reis['brochure_pdf'] ?? null;
        if (!empty($_FILES['brochure_pdf']['name'])) {
            $ext = strtolower(pathinfo($_FILES['brochure_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $uploadDir = '../../beheer/uploads/reizen/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $bestandsnaam = 'brochure_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['brochure_pdf']['tmp_name'], $uploadDir . $bestandsnaam)) {
                    $brochurePdf = 'beheer/uploads/reizen/' . $bestandsnaam;
                }
            }
        }

        if ($titel === '' || $datum_van === '') {
            $fout = 'Vul minimaal een titel en vertrekdatum in.';
        } else {
            try {
                if ($isNieuw) {
                    // Zorg voor unieke slug
                    $slugBase = $slug;
                    $i = 1;
                    while (true) {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM busreizen WHERE tenant_id=? AND slug=?");
                        $chk->execute([$dataTenantId, $slug]);
                        if ((int)$chk->fetchColumn() === 0) break;
                        $slug = $slugBase . '-' . $i++;
                    }
                    $ins = $pdo->prepare("INSERT INTO busreizen
                        (tenant_id,type,vervoerder,titel,slug,beschrijving,bestemming,categorie,
                         datum_van,datum_tot,vertrek_tijd,terug_tijd,hotel_naam,hotel_sterren,
                         prijs_pp,toeslag_enkelpersoon,reserveringskosten,vroegboekkorting,vroegboek_deadline,
                         max_deelnemers,vertrekgarantie,anvr_sgr,foto_pad,brochure_pdf,status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([
                        $dataTenantId,$type,$vervoerder,$titel,$slug,$beschrijving,$bestemming,$categorie,
                        $datum_van,$datum_tot,$vertrek_tijd,$terug_tijd,$hotel_naam,$hotel_sterren,
                        $prijs_pp,$toeslag_ep,$reservk,$vroegboek,$vroegdeadline,
                        $max,$vertrekgar,$anvr,$fotoPad,$brochurePdf,$status
                    ]);
                    $id = (int)$pdo->lastInsertId();
                } else {
                    $upd = $pdo->prepare("UPDATE busreizen SET
                        type=?,vervoerder=?,titel=?,beschrijving=?,bestemming=?,categorie=?,
                        datum_van=?,datum_tot=?,vertrek_tijd=?,terug_tijd=?,hotel_naam=?,hotel_sterren=?,
                        prijs_pp=?,toeslag_enkelpersoon=?,reserveringskosten=?,vroegboekkorting=?,vroegboek_deadline=?,
                        max_deelnemers=?,vertrekgarantie=?,anvr_sgr=?,
                        foto_pad=COALESCE(?,foto_pad),brochure_pdf=COALESCE(?,brochure_pdf),status=?
                        WHERE id=? AND tenant_id=?");
                    $upd->execute([
                        $type,$vervoerder,$titel,$beschrijving,$bestemming,$categorie,
                        $datum_van,$datum_tot,$vertrek_tijd,$terug_tijd,$hotel_naam,$hotel_sterren,
                        $prijs_pp,$toeslag_ep,$reservk,$vroegboek,$vroegdeadline,
                        $max,$vertrekgar,$anvr,$fotoPad,$brochurePdf,$status,
                        $id,$dataTenantId
                    ]);
                }

                // Haltes opslaan
                $pdo->prepare("DELETE FROM busreis_haltes WHERE busreis_id=?")->execute([$id]);
                $halteNamen = $_POST['halte_naam'] ?? [];
                foreach ($halteNamen as $i2 => $naam) {
                    $naam = trim($naam);
                    if ($naam === '') continue;
                    $pdo->prepare("INSERT INTO busreis_haltes (busreis_id,naam,adres,vertrek_tijd,sort_order) VALUES (?,?,?,?,?)")
                        ->execute([$id, $naam,
                            trim($_POST['halte_adres'][$i2] ?? '') ?: null,
                            trim($_POST['halte_tijd'][$i2]  ?? '') ?: null,
                            $i2]);
                }

                // Staffels opslaan
                $pdo->prepare("DELETE FROM busreis_staffels WHERE busreis_id=?")->execute([$id]);
                $stPaxVan = $_POST['staffel_van'] ?? [];
                foreach ($stPaxVan as $i2 => $van) {
                    $van = (int)$van; $tot = (int)($_POST['staffel_tot'][$i2] ?? 0);
                    $prijs = (float)str_replace(',', '.', $_POST['staffel_prijs'][$i2] ?? '0');
                    if ($van > 0 && $tot > 0 && $prijs > 0) {
                        $pdo->prepare("INSERT INTO busreis_staffels (busreis_id,pax_van,pax_tot,prijs_pp) VALUES (?,?,?,?)")
                            ->execute([$id, $van, $tot, $prijs]);
                    }
                }

                // Dagprogramma opslaan
                $pdo->prepare("DELETE FROM busreis_dagprogramma WHERE busreis_id=?")->execute([$id]);
                $dagNummers = $_POST['dag_nummer'] ?? [];
                foreach ($dagNummers as $i2 => $dagNr) {
                    $oms = trim($_POST['dag_omschrijving'][$i2] ?? '');
                    if ($oms === '') continue;
                    $pdo->prepare("INSERT INTO busreis_dagprogramma (busreis_id,dag_nummer,titel,omschrijving,sort_order) VALUES (?,?,?,?,?)")
                        ->execute([$id, (int)$dagNr,
                            trim($_POST['dag_titel'][$i2] ?? '') ?: null,
                            $oms, $i2]);
                }

                // Opties opslaan
                $pdo->prepare("DELETE FROM busreis_opties WHERE busreis_id=?")->execute([$id]);
                $optieNamen = $_POST['optie_naam'] ?? [];
                foreach ($optieNamen as $i2 => $naam) {
                    $naam = trim($naam);
                    if ($naam === '') continue;
                    $pdo->prepare("INSERT INTO busreis_opties (busreis_id,naam,beschrijving,prijs,sort_order) VALUES (?,?,?,?,?)")
                        ->execute([$id, $naam,
                            trim($_POST['optie_beschrijving'][$i2] ?? '') ?: null,
                            (float)str_replace(',', '.', $_POST['optie_prijs'][$i2] ?? '0'),
                            $i2]);
                }

                coop_invalidate_partner_site_caches($pdo, $dataTenantId);

                header("Location: index.php?msg=opgeslagen");
                exit;

            } catch (PDOException $e) {
                $fout = 'Fout bij opslaan: ' . $e->getMessage();
            }
        }
    }
}

$csrf = auth_get_csrf_token();
$titel_pagina = $isNieuw ? 'Nieuwe reis aanmaken' : 'Reis bewerken: ' . htmlspecialchars($reis['titel'] ?? '', ENT_QUOTES);

include '../includes/header.php';
?>

<style>
.bw-page  { max-width:960px; margin:0 auto; padding:24px 20px; }
.bw-kop   { display:flex; align-items:center; justify-content:space-between;
             margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.bw-kop h1 { font-size:20px; font-weight:800; color:#002855; margin:0;
              display:flex; align-items:center; gap:10px; }

/* Secties */
.bw-sectie { background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);
              margin-bottom:18px; overflow:hidden; }
.bw-sectie-kop { background:#f8faff; border-bottom:1px solid #e8eef7;
                  padding:13px 20px; display:flex; align-items:center; gap:9px;
                  font-size:13.5px; font-weight:700; color:#002855; cursor:pointer; }
.bw-sectie-kop i.icon { color:#004aad; width:18px; text-align:center; }
.bw-sectie-body { padding:20px; }

/* Grid */
.rij     { display:grid; gap:14px; margin-bottom:14px; }
.rij-2   { grid-template-columns:1fr 1fr; }
.rij-3   { grid-template-columns:1fr 1fr 1fr; }
.rij-4   { grid-template-columns:1fr 1fr 1fr 1fr; }

/* Veld */
.veld label { display:block; font-size:12px; font-weight:600; color:#374151;
               margin-bottom:5px; letter-spacing:.2px; }
.veld input, .veld select, .veld textarea {
    width:100%; padding:9px 12px; border:1px solid #d1d5db; border-radius:7px;
    font-size:13px; color:#1a2533; font-family:inherit; background:#fff;
    transition:border-color .15s; }
.veld input:focus, .veld select:focus, .veld textarea:focus {
    outline:none; border-color:#003d82; box-shadow:0 0 0 3px rgba(0,61,130,.08); }
.veld textarea { resize:vertical; min-height:90px; line-height:1.5; }
.veld small { display:block; margin-top:4px; font-size:11px; color:#94a3b8; }

/* Toggle sectie */
.schakelaar-rij { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:14px; }
.schakelaar { display:flex; align-items:center; gap:8px; cursor:pointer;
               font-size:13px; color:#374151; }
.schakelaar input[type=checkbox] { width:16px; height:16px; cursor:pointer; }

/* Type keuze knoppen */
.type-keuze { display:flex; gap:12px; margin-bottom:4px; }
.type-btn { flex:1; padding:14px; border:2px solid #e2e8f0; border-radius:9px;
             background:#f8faff; cursor:pointer; text-align:center; transition:.15s;
             display:flex; flex-direction:column; align-items:center; gap:6px; }
.type-btn i  { font-size:22px; color:#94a3b8; transition:.15s; }
.type-btn span { font-size:13px; font-weight:600; color:#374151; }
.type-btn small { font-size:11px; color:#94a3b8; }
.type-btn.actief { border-color:#003d82; background:#eff6ff; }
.type-btn.actief i  { color:#003d82; }
.type-btn.actief span { color:#002855; }

/* Dynamische lijsten (haltes, staffels, dagprogramma, opties) */
.dyn-rij { display:flex; gap:8px; align-items:flex-start; margin-bottom:8px;
            background:#f8faff; border:1px solid #e8eef7; border-radius:7px; padding:10px 12px; }
.dyn-rij .veld { flex:1; margin:0; }
.dyn-rij .btn-del { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;
                     border-radius:6px; padding:7px 10px; cursor:pointer; font-size:13px;
                     flex-shrink:0; transition:.15s; margin-top:20px; }
.dyn-rij .btn-del:hover { background:#fca5a5; }
.btn-toevoegen { background:#eff6ff; color:#1d4ed8; border:1px dashed #93c5fd;
                  border-radius:7px; padding:8px 16px; font-size:13px; font-weight:600;
                  cursor:pointer; display:inline-flex; align-items:center; gap:6px;
                  transition:.15s; margin-top:6px; }
.btn-toevoegen:hover { background:#dbeafe; }

/* Foto preview */
.foto-preview { width:120px; height:80px; object-fit:cover; border-radius:7px;
                 border:1px solid #e2e8f0; display:block; margin-top:8px; }

/* Fout melding */
.bw-fout { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;
            border-radius:7px; padding:12px 16px; margin-bottom:18px;
            font-size:13px; font-weight:500; }

/* Actie knoppen */
.bw-acties { display:flex; justify-content:space-between; align-items:center;
              background:#fff; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);
              padding:16px 20px; }
.btn-rz { display:inline-flex; align-items:center; gap:6px; padding:10px 20px;
           border-radius:7px; font-size:13.5px; font-weight:600; text-decoration:none;
           border:none; cursor:pointer; transition:.15s; }
.btn-blauw  { background:#003d82; color:#fff; }
.btn-blauw:hover  { background:#002855; color:#fff; }
.btn-groen  { background:#1a7f4b; color:#fff; }
.btn-groen:hover  { background:#155f38; color:#fff; }
.btn-grijs  { background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; text-decoration:none; }
.btn-grijs:hover  { background:#e2e8f0; }
</style>

<div class="bw-page">

    <div class="bw-kop">
        <h1>
            <i class="fa-solid fa-<?= $isNieuw ? 'plus-circle' : 'pen-to-square' ?>"></i>
            <?= $titel_pagina ?>
        </h1>
        <a href="index.php" class="btn-rz btn-grijs">
            <i class="fa-solid fa-arrow-left"></i> Terug
        </a>
    </div>

    <?php if ($fout): ?>
        <div class="bw-fout"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($fout, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="reisForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

        <!-- ── TYPE ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-tag icon"></i> Type reis &amp; vervoerder
            </div>
            <div class="bw-sectie-body">
                <div class="rij rij-2">
                    <div class="veld">
                        <label>Type reis</label>
                        <div class="type-keuze">
                            <div class="type-btn <?= ($reis['type'] ?? 'dagtocht')==='dagtocht' ? 'actief' : '' ?>"
                                 onclick="setType('dagtocht',this)">
                                <i class="fa-solid fa-sun"></i>
                                <span>Dagtocht</span>
                                <small>1 dag, heen &amp; terug</small>
                            </div>
                            <div class="type-btn <?= ($reis['type'] ?? '')==='meerdaags' ? 'actief' : '' ?>"
                                 onclick="setType('meerdaags',this)">
                                <i class="fa-solid fa-moon"></i>
                                <span>Meerdaagse reis</span>
                                <small>Hotel, dagprogramma</small>
                            </div>
                        </div>
                        <input type="hidden" name="type" id="typeInput"
                               value="<?= htmlspecialchars($reis['type'] ?? 'dagtocht', ENT_QUOTES) ?>">
                    </div>
                    <div class="veld">
                        <label>Uitvoerende vervoerder</label>
                        <select name="vervoerder">
                            <option value="berkhout"  <?= ($reis['vervoerder'] ?? 'berkhout')==='berkhout'  ? 'selected' : '' ?>>Berkhout Reizen</option>
                            <option value="hartemink" <?= ($reis['vervoerder'] ?? '')==='hartemink' ? 'selected' : '' ?>>Touringcar Hartemink</option>
                            <option value="beide"     <?= ($reis['vervoerder'] ?? '')==='beide'     ? 'selected' : '' ?>>Beide (gecombineerd)</option>
                        </select>
                        <small>Bepaalt welk logo op de publieke pagina verschijnt</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── BASISGEGEVENS ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-circle-info icon"></i> Basisgegevens
            </div>
            <div class="bw-sectie-body">
                <div class="rij">
                    <div class="veld">
                        <label>Titel <span style="color:#b91c1c;">*</span></label>
                        <input type="text" name="titel" required maxlength="150"
                               value="<?= htmlspecialchars($reis['titel'] ?? '', ENT_QUOTES) ?>"
                               placeholder="bijv. Dagtocht Keukenhof">
                    </div>
                </div>
                <div class="rij rij-2">
                    <div class="veld">
                        <label>Bestemming</label>
                        <input type="text" name="bestemming" maxlength="150"
                               value="<?= htmlspecialchars($reis['bestemming'] ?? '', ENT_QUOTES) ?>"
                               placeholder="bijv. Lisse, Karinthië">
                    </div>
                    <div class="veld">
                        <label>Categorie</label>
                        <input type="text" name="categorie" maxlength="100"
                               value="<?= htmlspecialchars($reis['categorie'] ?? '', ENT_QUOTES) ?>"
                               placeholder="bijv. natuur, musical, stedentrip">
                    </div>
                </div>
                <div class="veld">
                    <label>Omschrijving</label>
                    <textarea name="beschrijving" placeholder="Beschrijf de reis voor de klant..."><?= htmlspecialchars($reis['beschrijving'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── DATUMS & TIJDEN ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-calendar-days icon"></i> Datums &amp; tijden
            </div>
            <div class="bw-sectie-body">
                <div class="rij rij-4">
                    <div class="veld">
                        <label>Vertrekdatum <span style="color:#b91c1c;">*</span></label>
                        <input type="date" name="datum_van" required
                               value="<?= htmlspecialchars($reis['datum_van'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="veld meerdaags-veld">
                        <label>Retourdatum</label>
                        <input type="date" name="datum_tot"
                               value="<?= htmlspecialchars($reis['datum_tot'] ?? '', ENT_QUOTES) ?>">
                        <small>Alleen bij meerdaagse reis</small>
                    </div>
                    <div class="veld">
                        <label>Vertrektijd</label>
                        <input type="time" name="vertrek_tijd"
                               value="<?= htmlspecialchars($reis['vertrek_tijd'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="veld">
                        <label>Verwachte terugkomst</label>
                        <input type="time" name="terug_tijd"
                               value="<?= htmlspecialchars($reis['terug_tijd'] ?? '', ENT_QUOTES) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PRIJZEN ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-euro-sign icon"></i> Prijzen
            </div>
            <div class="bw-sectie-body">
                <div class="rij rij-4">
                    <div class="veld">
                        <label>Prijs per persoon (€) <span style="color:#b91c1c;">*</span></label>
                        <input type="text" name="prijs_pp" inputmode="decimal"
                               value="<?= number_format((float)($reis['prijs_pp'] ?? 0), 2, ',', '') ?>"
                               placeholder="0,00">
                    </div>
                    <div class="veld meerdaags-veld">
                        <label>Toeslag enkelpersoon (€)</label>
                        <input type="text" name="toeslag_enkelpersoon" inputmode="decimal"
                               value="<?= number_format((float)($reis['toeslag_enkelpersoon'] ?? 0), 2, ',', '') ?>"
                               placeholder="0,00">
                    </div>
                    <div class="veld">
                        <label>Reserveringskosten (€)</label>
                        <input type="text" name="reserveringskosten" inputmode="decimal"
                               value="<?= number_format((float)($reis['reserveringskosten'] ?? 15), 2, ',', '') ?>"
                               placeholder="15,00">
                        <small>Standaard € 15,– per boeking</small>
                    </div>
                    <div class="veld">
                        <label>Vroegboekkorting (€)</label>
                        <input type="text" name="vroegboekkorting" inputmode="decimal"
                               value="<?= number_format((float)($reis['vroegboekkorting'] ?? 0), 2, ',', '') ?>"
                               placeholder="0,00">
                    </div>
                </div>
                <div class="rij rij-4">
                    <div class="veld">
                        <label>Vroegboek deadline</label>
                        <input type="date" name="vroegboek_deadline"
                               value="<?= htmlspecialchars($reis['vroegboek_deadline'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="veld">
                        <label>Max. deelnemers</label>
                        <input type="number" name="max_deelnemers" min="1" max="999"
                               value="<?= (int)($reis['max_deelnemers'] ?? 50) ?>">
                    </div>
                    <div class="veld" style="display:flex; flex-direction:column; justify-content:flex-end; padding-bottom:2px;">
                        <div class="schakelaar-rij">
                            <label class="schakelaar">
                                <input type="checkbox" name="vertrekgarantie" value="1"
                                       <?= !empty($reis['vertrekgarantie']) ? 'checked' : '' ?>>
                                <span><i class="fa-solid fa-shield-check" style="color:#1a7f4b;"></i> Vertrekgarantie</span>
                            </label>
                            <label class="schakelaar">
                                <input type="checkbox" name="anvr_sgr" value="1"
                                       <?= !empty($reis['anvr_sgr']) ? 'checked' : '' ?>>
                                <span><i class="fa-solid fa-certificate" style="color:#003d82;"></i> ANVR / SGR logo's tonen</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MEERDAAGS: HOTEL ── -->
        <div class="bw-sectie meerdaags-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-hotel icon"></i> Hotel &amp; accommodatie
                <span style="font-size:11px; color:#94a3b8; font-weight:400; margin-left:6px;">(meerdaagse reizen)</span>
            </div>
            <div class="bw-sectie-body">
                <div class="rij rij-2">
                    <div class="veld">
                        <label>Naam hotel</label>
                        <input type="text" name="hotel_naam" maxlength="150"
                               value="<?= htmlspecialchars($reis['hotel_naam'] ?? '', ENT_QUOTES) ?>"
                               placeholder="bijv. Hotel Seehof">
                    </div>
                    <div class="veld">
                        <label>Sterren</label>
                        <select name="hotel_sterren">
                            <option value="">— niet opgeven —</option>
                            <?php for ($n=1;$n<=5;$n++): ?>
                            <option value="<?= $n ?>" <?= (int)($reis['hotel_sterren'] ?? 0)===$n ? 'selected' : '' ?>>
                                <?= str_repeat('★',$n) ?> (<?= $n ?> sterren)
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── HALTES ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-map-pin icon"></i> Opstapplaatsen / haltes
            </div>
            <div class="bw-sectie-body">
                <div id="haltes-container">
                    <?php if (empty($haltes)): ?>
                    <div class="dyn-rij" id="halte-0">
                        <div class="veld"><label>Naam halte</label><input type="text" name="halte_naam[]" placeholder="bijv. Station Doetinchem"></div>
                        <div class="veld"><label>Adres</label><input type="text" name="halte_adres[]" placeholder="optioneel"></div>
                        <div class="veld" style="max-width:110px;"><label>Vertrektijd</label><input type="time" name="halte_tijd[]"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php else: foreach ($haltes as $h): ?>
                    <div class="dyn-rij">
                        <div class="veld"><label>Naam halte</label><input type="text" name="halte_naam[]" value="<?= htmlspecialchars($h['naam'], ENT_QUOTES) ?>" placeholder="bijv. Station Doetinchem"></div>
                        <div class="veld"><label>Adres</label><input type="text" name="halte_adres[]" value="<?= htmlspecialchars($h['adres'] ?? '', ENT_QUOTES) ?>" placeholder="optioneel"></div>
                        <div class="veld" style="max-width:110px;"><label>Vertrektijd</label><input type="time" name="halte_tijd[]" value="<?= htmlspecialchars($h['vertrek_tijd'] ?? '', ENT_QUOTES) ?>"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn-toevoegen" onclick="voegHalteToe()">
                    <i class="fa-solid fa-plus"></i> Halte toevoegen
                </button>
            </div>
        </div>

        <!-- ── GROEPSSTAFFELS ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-users icon"></i> Groepsprijzen (staffels)
                <span style="font-size:11px; color:#94a3b8; font-weight:400; margin-left:6px;">(optioneel, voor groepsboekingen)</span>
            </div>
            <div class="bw-sectie-body">
                <div id="staffels-container">
                    <?php if (empty($staffels)): ?>
                    <div class="dyn-rij">
                        <div class="veld" style="max-width:100px;"><label>Pax vanaf</label><input type="number" name="staffel_van[]" min="1" placeholder="10"></div>
                        <div class="veld" style="max-width:100px;"><label>Pax t/m</label><input type="number" name="staffel_tot[]" min="1" placeholder="20"></div>
                        <div class="veld" style="max-width:130px;"><label>Prijs pp (€)</label><input type="text" name="staffel_prijs[]" placeholder="40,00"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php else: foreach ($staffels as $st): ?>
                    <div class="dyn-rij">
                        <div class="veld" style="max-width:100px;"><label>Pax vanaf</label><input type="number" name="staffel_van[]" value="<?= (int)$st['pax_van'] ?>" min="1"></div>
                        <div class="veld" style="max-width:100px;"><label>Pax t/m</label><input type="number" name="staffel_tot[]" value="<?= (int)$st['pax_tot'] ?>" min="1"></div>
                        <div class="veld" style="max-width:130px;"><label>Prijs pp (€)</label><input type="text" name="staffel_prijs[]" value="<?= number_format((float)$st['prijs_pp'],2,',','') ?>"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn-toevoegen" onclick="voegStaffelToe()">
                    <i class="fa-solid fa-plus"></i> Staffel toevoegen
                </button>
            </div>
        </div>

        <!-- ── DAGPROGRAMMA (meerdaags) ── -->
        <div class="bw-sectie meerdaags-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-list-check icon"></i> Dagprogramma
                <span style="font-size:11px; color:#94a3b8; font-weight:400; margin-left:6px;">(meerdaagse reizen)</span>
            </div>
            <div class="bw-sectie-body">
                <div id="dagprog-container">
                    <?php if (empty($dagprogramma)): ?>
                    <div class="dyn-rij" style="flex-direction:column; gap:8px;">
                        <div style="display:flex; gap:8px; align-items:center; width:100%;">
                            <div class="veld" style="max-width:80px; margin:0;"><label>Dag</label><input type="number" name="dag_nummer[]" min="1" value="1"></div>
                            <div class="veld" style="flex:1; margin:0;"><label>Titel dag</label><input type="text" name="dag_titel[]" placeholder="bijv. Aankomst &amp; welkomstdiner"></div>
                            <button type="button" class="btn-del" onclick="verwijderRij(this.closest('.dyn-rij'))" style="margin-top:20px;"><i class="fa-solid fa-trash"></i></button>
                        </div>
                        <div class="veld" style="margin:0; width:100%;"><label>Omschrijving</label><textarea name="dag_omschrijving[]" rows="2" placeholder="Wat doet de reiziger op deze dag?"></textarea></div>
                    </div>
                    <?php else: foreach ($dagprogramma as $dp): ?>
                    <div class="dyn-rij" style="flex-direction:column; gap:8px;">
                        <div style="display:flex; gap:8px; align-items:center; width:100%;">
                            <div class="veld" style="max-width:80px; margin:0;"><label>Dag</label><input type="number" name="dag_nummer[]" min="1" value="<?= (int)$dp['dag_nummer'] ?>"></div>
                            <div class="veld" style="flex:1; margin:0;"><label>Titel dag</label><input type="text" name="dag_titel[]" value="<?= htmlspecialchars($dp['titel'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn-del" onclick="verwijderRij(this.closest('.dyn-rij'))" style="margin-top:20px;"><i class="fa-solid fa-trash"></i></button>
                        </div>
                        <div class="veld" style="margin:0; width:100%;"><label>Omschrijving</label><textarea name="dag_omschrijving[]" rows="2"><?= htmlspecialchars($dp['omschrijving'] ?? '', ENT_QUOTES) ?></textarea></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn-toevoegen" onclick="voegDagToe()">
                    <i class="fa-solid fa-plus"></i> Dag toevoegen
                </button>
            </div>
        </div>

        <!-- ── BIJBOEKINGEN / OPTIES ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-ticket icon"></i> Bijboekingen &amp; opties
                <span style="font-size:11px; color:#94a3b8; font-weight:400; margin-left:6px;">(klant kan dit bijboeken)</span>
            </div>
            <div class="bw-sectie-body">
                <div id="opties-container">
                    <?php if (empty($opties)): ?>
                    <div class="dyn-rij">
                        <div class="veld"><label>Naam</label><input type="text" name="optie_naam[]" placeholder="bijv. Truffeljacht"></div>
                        <div class="veld"><label>Toelichting</label><input type="text" name="optie_beschrijving[]" placeholder="optioneel"></div>
                        <div class="veld" style="max-width:120px;"><label>Prijs (€)</label><input type="text" name="optie_prijs[]" placeholder="0,00"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php else: foreach ($opties as $o): ?>
                    <div class="dyn-rij">
                        <div class="veld"><label>Naam</label><input type="text" name="optie_naam[]" value="<?= htmlspecialchars($o['naam'], ENT_QUOTES) ?>"></div>
                        <div class="veld"><label>Toelichting</label><input type="text" name="optie_beschrijving[]" value="<?= htmlspecialchars($o['beschrijving'] ?? '', ENT_QUOTES) ?>"></div>
                        <div class="veld" style="max-width:120px;"><label>Prijs (€)</label><input type="text" name="optie_prijs[]" value="<?= number_format((float)$o['prijs'],2,',','') ?>"></div>
                        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" class="btn-toevoegen" onclick="voegOptieToe()">
                    <i class="fa-solid fa-plus"></i> Optie toevoegen
                </button>
            </div>
        </div>

        <!-- ── MEDIA ── -->
        <div class="bw-sectie">
            <div class="bw-sectie-kop">
                <i class="fa-solid fa-images icon"></i> Foto &amp; brochure
            </div>
            <div class="bw-sectie-body">
                <div class="rij rij-2">
                    <div class="veld">
                        <label>Hoofdfoto</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($reis['foto_pad'])): ?>
                            <img src="<?= htmlspecialchars('../../' . ltrim($reis['foto_pad'],'/'), ENT_QUOTES) ?>"
                                 class="foto-preview" alt="Huidige foto">
                        <?php endif; ?>
                        <small>JPG, PNG of WebP — max 8MB</small>
                    </div>
                    <div class="veld meerdaags-veld">
                        <label>Brochure PDF</label>
                        <input type="file" name="brochure_pdf" accept="application/pdf">
                        <?php if (!empty($reis['brochure_pdf'])): ?>
                            <div style="margin-top:8px; font-size:12px; color:#1d4ed8;">
                                <i class="fa-solid fa-file-pdf"></i>
                                Huidige brochure: <?= htmlspecialchars(basename($reis['brochure_pdf']), ENT_QUOTES) ?>
                            </div>
                        <?php endif; ?>
                        <small>PDF — alleen meerdaagse reizen</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── STATUS & OPSLAAN ── -->
        <div class="bw-acties">
            <div class="veld" style="margin:0; min-width:200px;">
                <label>Status</label>
                <select name="status">
                    <option value="concept"      <?= ($reis['status'] ?? 'concept')==='concept'      ? 'selected' : '' ?>>Concept (niet zichtbaar)</option>
                    <option value="gepubliceerd" <?= ($reis['status'] ?? '')==='gepubliceerd' ? 'selected' : '' ?>>Gepubliceerd (zichtbaar)</option>
                    <option value="vol"          <?= ($reis['status'] ?? '')==='vol'          ? 'selected' : '' ?>>Vol (geen nieuwe boekingen)</option>
                    <option value="archief"      <?= ($reis['status'] ?? '')==='archief'      ? 'selected' : '' ?>>Archief</option>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="index.php" class="btn-rz btn-grijs">Annuleren</a>
                <button type="submit" class="btn-rz btn-groen">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $isNieuw ? 'Reis aanmaken' : 'Wijzigingen opslaan' ?>
                </button>
            </div>
        </div>

    </form>
</div>

<script>
// ── Type toggle ────────────────────────────────────────────
function setType(type, el) {
    document.getElementById('typeInput').value = type;
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('actief'));
    el.classList.add('actief');
    updateMeerdaagsVisibility(type);
}

function updateMeerdaagsVisibility(type) {
    const show = type === 'meerdaags';
    document.querySelectorAll('.meerdaags-sectie').forEach(s => {
        s.style.display = show ? '' : 'none';
    });
    document.querySelectorAll('.meerdaags-veld').forEach(v => {
        v.style.opacity = show ? '1' : '0.4';
        v.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !show);
    });
}

// Initieel instellen
document.addEventListener('DOMContentLoaded', function() {
    const type = document.getElementById('typeInput').value;
    updateMeerdaagsVisibility(type);
});

// ── Dynamisch rijen verwijderen ────────────────────────────
function verwijderRij(el) {
    const rij = el.closest ? el.closest('.dyn-rij') : el;
    if (rij) rij.remove();
}

// ── Halte toevoegen ────────────────────────────────────────
function voegHalteToe() {
    const c = document.getElementById('haltes-container');
    const div = document.createElement('div');
    div.className = 'dyn-rij';
    div.innerHTML = `
        <div class="veld"><label>Naam halte</label><input type="text" name="halte_naam[]" placeholder="bijv. Station Doetinchem"></div>
        <div class="veld"><label>Adres</label><input type="text" name="halte_adres[]" placeholder="optioneel"></div>
        <div class="veld" style="max-width:110px;"><label>Vertrektijd</label><input type="time" name="halte_tijd[]"></div>
        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>`;
    c.appendChild(div);
}

// ── Staffel toevoegen ──────────────────────────────────────
function voegStaffelToe() {
    const c = document.getElementById('staffels-container');
    const div = document.createElement('div');
    div.className = 'dyn-rij';
    div.innerHTML = `
        <div class="veld" style="max-width:100px;"><label>Pax vanaf</label><input type="number" name="staffel_van[]" min="1" placeholder="10"></div>
        <div class="veld" style="max-width:100px;"><label>Pax t/m</label><input type="number" name="staffel_tot[]" min="1" placeholder="20"></div>
        <div class="veld" style="max-width:130px;"><label>Prijs pp (€)</label><input type="text" name="staffel_prijs[]" placeholder="40,00"></div>
        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>`;
    c.appendChild(div);
}

// ── Dag toevoegen ──────────────────────────────────────────
function voegDagToe() {
    const c = document.getElementById('dagprog-container');
    const dagNr = c.querySelectorAll('.dyn-rij').length + 1;
    const div = document.createElement('div');
    div.className = 'dyn-rij';
    div.style.cssText = 'flex-direction:column; gap:8px;';
    div.innerHTML = `
        <div style="display:flex; gap:8px; align-items:center; width:100%;">
            <div class="veld" style="max-width:80px; margin:0;"><label>Dag</label><input type="number" name="dag_nummer[]" min="1" value="${dagNr}"></div>
            <div class="veld" style="flex:1; margin:0;"><label>Titel dag</label><input type="text" name="dag_titel[]" placeholder="bijv. Vrije dag in de stad"></div>
            <button type="button" class="btn-del" onclick="verwijderRij(this.closest('.dyn-rij'))" style="margin-top:20px;"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="veld" style="margin:0; width:100%;"><label>Omschrijving</label><textarea name="dag_omschrijving[]" rows="2" placeholder="Wat doet de reiziger op deze dag?"></textarea></div>`;
    c.appendChild(div);
}

// ── Optie toevoegen ────────────────────────────────────────
function voegOptieToe() {
    const c = document.getElementById('opties-container');
    const div = document.createElement('div');
    div.className = 'dyn-rij';
    div.innerHTML = `
        <div class="veld"><label>Naam</label><input type="text" name="optie_naam[]" placeholder="bijv. Truffeljacht"></div>
        <div class="veld"><label>Toelichting</label><input type="text" name="optie_beschrijving[]" placeholder="optioneel"></div>
        <div class="veld" style="max-width:120px;"><label>Prijs (€)</label><input type="text" name="optie_prijs[]" placeholder="0,00"></div>
        <button type="button" class="btn-del" onclick="verwijderRij(this)"><i class="fa-solid fa-trash"></i></button>`;
    c.appendChild(div);
}
</script>

<?php include '../includes/footer.php'; ?>
