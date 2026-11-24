-- Meerdaagse / producttekst: hotelomschrijving + bewerkbare inbegrepen-lijsten.
-- Platformbreed (alle tenants via busreizen.tenant_id op de parent).
-- Eenmalig uitvoeren (phpMyAdmin / deploy). Bij "Duplicate column" / "already exists":
-- kolom of tabel bestaat al — dan verder gaan.

ALTER TABLE busreizen
    ADD COLUMN hotel_omschrijving TEXT NULL
        COMMENT 'Brochure-achtige hoteltekst (plain, newlines)'
        AFTER hotel_sterren;

CREATE TABLE IF NOT EXISTS busreis_productregels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    busreis_id INT UNSIGNED NOT NULL,
    soort ENUM('inbegrepen','niet_inbegrepen','hotel_inbegrepen') NOT NULL,
    tekst VARCHAR(500) NOT NULL,
    bedrag DECIMAL(10,2) NULL DEFAULT NULL,
    toelichting VARCHAR(500) NULL DEFAULT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_bpr_reis_soort (busreis_id, soort, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
