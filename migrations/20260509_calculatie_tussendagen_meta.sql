-- Optioneel: tussenritten als JSON (extra dagen met eigen route/km/bus/pax).
-- Als kolom al bestaat: overslaan.

ALTER TABLE calculaties
    ADD COLUMN tussendagen_meta LONGTEXT NULL
        COMMENT 'JSON tussenritten';
