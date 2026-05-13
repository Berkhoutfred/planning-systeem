-- PDF-bijlagen per calculatie (tenant-geïsoleerd; download via beveiligd endpoint).
-- Eenmalig uitvoeren; bij "already exists" bestaat de tabel al.

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
