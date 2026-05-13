-- Handmatige onderbrekingen (0/1/2) voor CAO onderbrekingstoeslag bij breng & haal.
-- Eenmalig uitvoeren; bij "Duplicate column" bestaat de kolom al.

ALTER TABLE calculaties
    ADD COLUMN cao_onderbreking_aantal TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'CAO art.37: aantal kwalificerende onderbrekingen (0-2), alleen brenghaal';
