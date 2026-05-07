-- Module buitenland: marker op calculatie + metadata (JSON als tekst in LONGTEXT).
-- Eenmalig per omgeving uitvoeren in phpMyAdmin (Database → SQL).
-- Als "Duplicate column" verschijnt: kolommen bestaan al — dan hoeft dit niet opnieuw.

ALTER TABLE calculaties
    ADD COLUMN offerte_module VARCHAR(32) NOT NULL DEFAULT 'standaard'
        COMMENT 'standaard|buitenland';

ALTER TABLE calculaties
    ADD COLUMN buitenland_meta LONGTEXT NULL
        COMMENT 'JSON metadata module buitenland';
