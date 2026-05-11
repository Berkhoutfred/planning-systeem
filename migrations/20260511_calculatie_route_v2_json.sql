-- Canoniek route-model als JSON naast de bestaande calculatie_regels.
-- Eenmalig per omgeving uitvoeren in phpMyAdmin (Database -> SQL).
-- Als "Duplicate column" verschijnt: kolom bestaat al en hoeft niet opnieuw.

ALTER TABLE calculaties
    ADD COLUMN route_v2_json LONGTEXT NULL
        COMMENT 'JSON canoniek route-model v2';
