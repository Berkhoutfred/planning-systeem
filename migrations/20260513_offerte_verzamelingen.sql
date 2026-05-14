-- Verzamelofferte: bundel + gekoppelde calculaties (tenant-scoped).
-- Voer uit op de productie-/stagingdatabase na deploy.

CREATE TABLE IF NOT EXISTS offerte_verzamelingen (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  titel VARCHAR(255) NOT NULL DEFAULT '',
  aangemaakt_op DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ov_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offerte_verzameling_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  verzameling_id INT UNSIGNED NOT NULL,
  calculatie_id INT UNSIGNED NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bundle_calc (verzameling_id, calculatie_id),
  KEY idx_ovi_bundle (verzameling_id),
  KEY idx_ovi_tenant_calc (tenant_id, calculatie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
