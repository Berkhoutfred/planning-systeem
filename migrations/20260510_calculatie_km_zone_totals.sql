-- Fiscale km-op splitsing per zone (calculatie-formulier).
-- Voer uit op de tenant-database indien deze kolommen nog ontbreken.

ALTER TABLE calculaties
  ADD COLUMN km_ch DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_de,
  ADD COLUMN km_ov DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER km_ch;
