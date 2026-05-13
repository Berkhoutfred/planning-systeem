/*
 * HOSTINGER / phpMyAdmin — EENMALIGE UPDATE (t/m 2026-05-14, incl. opmerkingen_chauffeur)
 *
 * STAP VOOR STAP (belangrijk):
 * 1) Klik links je JUISTE database aan.
 * 2) Tab "SQL".
 * 3) Plak ALLEEN ÉÉN blok tegelijk (van "/* STAP …" tot en met de puntkomma ;).
 * 4) Klik "Uitvoeren".
 * 5) Ga naar het volgende blok.
 *
 * "Duplicate column" / "already exists" = die stap was al gedaan; ga door naar de volgende.
 *
 * STAP 1A: als fout op "AFTER actief" → gebruik STAP 1A-ALTERNATIEF (zonder AFTER).
 */

SET NAMES utf8mb4;

/* ========== STAP 1A: kolom email_otp_enabled op users ========== */
ALTER TABLE users
    ADD COLUMN email_otp_enabled TINYINT(1) NOT NULL DEFAULT 0
    AFTER actief;

/*
 * ========== STAP 1A-ALTERNATIEF (alleen als STAP 1A faalt op "Unknown column actief") ==========
 * Voer dan ALLEEN dit uit (niet tegelijk met STAP 1A):
 *
 * ALTER TABLE users
 *     ADD COLUMN email_otp_enabled TINYINT(1) NOT NULL DEFAULT 0;
 */

/* ========== STAP 1B: tabel office_login_otp ========== */
CREATE TABLE IF NOT EXISTS office_login_otp (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_normalized VARCHAR(190) NOT NULL,
    tenant_slug VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    challenge_type ENUM('email_only', 'after_password') NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_lookup (email_normalized, tenant_slug, consumed_at),
    KEY idx_otp_expires (expires_at),
    KEY idx_otp_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ========== STAP 2A: calculaties.offerte_module ========== */
ALTER TABLE calculaties
    ADD COLUMN offerte_module VARCHAR(32) NOT NULL DEFAULT 'standaard'
        COMMENT 'standaard|buitenland';

/* ========== STAP 2B: calculaties.buitenland_meta ========== */
ALTER TABLE calculaties
    ADD COLUMN buitenland_meta LONGTEXT NULL
        COMMENT 'JSON metadata module buitenland';

/* ========== STAP 3: calculaties.tussendagen_meta ========== */
ALTER TABLE calculaties
    ADD COLUMN tussendagen_meta LONGTEXT NULL
        COMMENT 'JSON tussenritten';

/* ========== STAP 4: calculaties.km_ch + km_ov ========== */
ALTER TABLE calculaties
  ADD COLUMN km_ch DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_de,
  ADD COLUMN km_ov DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_ch;

/* ========== STAP 5: calculaties.route_v2_json ========== */
ALTER TABLE calculaties
    ADD COLUMN route_v2_json LONGTEXT NULL
        COMMENT 'JSON canoniek route-model v2';

/* ========== STAP 6: calculaties.cao_onderbreking_aantal ========== */
ALTER TABLE calculaties
    ADD COLUMN cao_onderbreking_aantal TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'CAO art.37: aantal kwalificerende onderbrekingen (0-2), alleen brenghaal';

/* ========== STAP 8: calculaties.opmerkingen_chauffeur (bericht alleen chauffeur) ========== */
ALTER TABLE calculaties
    ADD COLUMN opmerkingen_chauffeur TEXT NULL DEFAULT NULL
        COMMENT 'Alleen chauffeur; niet op klantofferte'
    AFTER instructie_kantoor;

/* ========== STAP 7: tabel calculatie_bijlagen ========== */
CREATE TABLE IF NOT EXISTS calculatie_bijlagen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    calculatie_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(64) NOT NULL,
    mime VARCHAR(127) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tenant_calc (tenant_id, calculatie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
