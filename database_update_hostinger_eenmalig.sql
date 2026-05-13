-- =============================================================================
-- EENMALIGE DATABASE-UPDATE (Hostinger / phpMyAdmin)
-- =============================================================================
-- Samenvoeging van alle losse wijzigingen t/m 2026-05-14.
-- Selecteer eerst de JUISTE database (je tenant-/ERP-schema).
--
-- Als een regel al is uitgevoerd, geeft MySQL/MariaDB vaak:
--   "Duplicate column name …" of "Table … already exists"
-- Dat is normaal: sla die ene statement over of negeer de fout en ga verder.
--
-- Volgorde: office OTP → calculatie-kolommen → calculatie_bijlagen-tabel
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1) Office-login: e-mail OTP (users + challenge-tabel)
-- -----------------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN email_otp_enabled TINYINT(1) NOT NULL DEFAULT 0
    AFTER actief;

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

-- -----------------------------------------------------------------------------
-- 2) Calculaties: buitenland-module
-- -----------------------------------------------------------------------------
ALTER TABLE calculaties
    ADD COLUMN offerte_module VARCHAR(32) NOT NULL DEFAULT 'standaard'
        COMMENT 'standaard|buitenland';

ALTER TABLE calculaties
    ADD COLUMN buitenland_meta LONGTEXT NULL
        COMMENT 'JSON metadata module buitenland';

-- -----------------------------------------------------------------------------
-- 3) Calculaties: tussenritten (JSON)
-- -----------------------------------------------------------------------------
ALTER TABLE calculaties
    ADD COLUMN tussendagen_meta LONGTEXT NULL
        COMMENT 'JSON tussenritten';

-- -----------------------------------------------------------------------------
-- 4) Calculaties: km-zones CH + OV (naast NL/DE)
-- -----------------------------------------------------------------------------
ALTER TABLE calculaties
  ADD COLUMN km_ch DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_de,
  ADD COLUMN km_ov DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_ch;

-- -----------------------------------------------------------------------------
-- 5) Calculaties: route v2 (JSON)
-- -----------------------------------------------------------------------------
ALTER TABLE calculaties
    ADD COLUMN route_v2_json LONGTEXT NULL
        COMMENT 'JSON canoniek route-model v2';

-- -----------------------------------------------------------------------------
-- 6) Calculaties: CAO onderbrekingen (breng & haal, 0–2)
-- -----------------------------------------------------------------------------
ALTER TABLE calculaties
    ADD COLUMN cao_onderbreking_aantal TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'CAO art.37: aantal kwalificerende onderbrekingen (0-2), alleen brenghaal';

-- -----------------------------------------------------------------------------
-- 7) Calculaties: PDF-bijlagen (per tenant)
-- -----------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Klaar. Controleer in phpMyAdmin of kolommen/tabel aanwezig zijn.
-- =============================================================================
