-- Module buitenland: marker op calculatie + JSON-metadata (overnachting, toeslagen).
-- Eenmalig uitvoeren op elke omgeving (lokaal + productie).

ALTER TABLE calculaties
    ADD COLUMN offerte_module VARCHAR(32) NOT NULL DEFAULT 'standaard'
        COMMENT 'standaard|buitenland'
        AFTER status;

ALTER TABLE calculaties
    ADD COLUMN buitenland_meta JSON NULL
        COMMENT 'Overnachting, toeslagen-notities; uitbreidbaar'
        AFTER offerte_module;
