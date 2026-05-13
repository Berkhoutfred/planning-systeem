-- Eenmalig (Hostinger/phpMyAdmin): kolom voor bericht alleen chauffeur.
-- Bij "Duplicate column name" → kolom bestond al; verder gaan.

ALTER TABLE calculaties
    ADD COLUMN opmerkingen_chauffeur TEXT NULL DEFAULT NULL
    COMMENT 'Alleen chauffeur; niet op klantofferte'
    AFTER instructie_kantoor;
