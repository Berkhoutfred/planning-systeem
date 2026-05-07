-- Office login: 6-cijferige e-mailcode (alleen code of na wachtwoord).
-- Voer dit eenmalig uit op elke omgeving (lokaal + productie).

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
