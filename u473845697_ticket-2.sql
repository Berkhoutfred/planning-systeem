-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Gegenereerd op: 20 apr 2026 om 20:16
-- Serverversie: 11.8.6-MariaDB-log
-- PHP-versie: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u473845697_ticket`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `afwezigheid`
--

CREATE TABLE `afwezigheid` (
  `id` int(11) NOT NULL,
  `chauffeur_id` int(11) NOT NULL,
  `startdatum` date NOT NULL,
  `einddatum` date NOT NULL,
  `type` enum('Vakantie','Ziek','Verlof','Overig') DEFAULT 'Vakantie',
  `opmerking` text DEFAULT NULL,
  `status` enum('Ingevoerd','Bevestigd') DEFAULT 'Bevestigd',
  `toegevoegd_op` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `afwezigheid`
--

INSERT INTO `afwezigheid` (`id`, `chauffeur_id`, `startdatum`, `einddatum`, `type`, `opmerking`, `status`, `toegevoegd_op`) VALUES
(3, 3, '2026-08-21', '2026-08-23', 'Vakantie', 'Huntenpop', 'Bevestigd', '2026-04-14 15:39:30'),
(4, 3, '2026-05-01', '2026-05-03', 'Vakantie', '', 'Bevestigd', '2026-04-14 15:41:53'),
(5, 3, '2026-07-11', '2026-08-02', 'Vakantie', '', 'Bevestigd', '2026-04-14 15:42:15'),
(6, 21, '2026-04-20', '2026-04-24', 'Vakantie', 'vrijdag 13:00 uur weer retour', 'Bevestigd', '2026-04-20 11:31:27'),
(8, 11, '2026-08-15', '2026-09-29', 'Vakantie', '', 'Bevestigd', '2026-04-20 13:48:02'),
(9, 11, '2026-06-06', '2026-06-21', 'Vakantie', '', 'Bevestigd', '2026-04-20 14:36:34');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `calculaties`
--

CREATE TABLE `calculaties` (
  `id` int(11) NOT NULL,
  `token` varchar(64) DEFAULT NULL,
  `klant_id` int(11) NOT NULL,
  `contact_id` int(11) DEFAULT 0,
  `afdeling_id` int(11) DEFAULT NULL,
  `rittype` varchar(50) DEFAULT NULL,
  `voertuig_id` int(11) DEFAULT NULL,
  `rittype_id` int(11) DEFAULT NULL,
  `aantal_bussen` int(11) DEFAULT 1,
  `titel` varchar(150) NOT NULL COMMENT 'Bijv. Schoolreisje Groep 8',
  `vertrek_datum` datetime NOT NULL,
  `vertrek_locatie` varchar(255) NOT NULL,
  `bestemming` varchar(255) NOT NULL,
  `prijs_totaal` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'concept',
  `aangemaakt_op` datetime DEFAULT current_timestamp(),
  `rit_datum` date DEFAULT NULL,
  `rit_datum_eind` date DEFAULT NULL,
  `vertrek_adres` varchar(255) DEFAULT NULL,
  `aankomst_adres` varchar(255) DEFAULT NULL,
  `aantal_personen` int(11) DEFAULT NULL,
  `afstand_km` int(11) DEFAULT NULL,
  `km_duitsland` decimal(10,1) DEFAULT 0.0,
  `uren` decimal(10,2) DEFAULT 0.00,
  `toeslag_percentage` int(11) DEFAULT 0,
  `prijs` decimal(10,2) DEFAULT 0.00,
  `btw_bedrag_nl` decimal(10,2) DEFAULT 0.00,
  `btw_bedrag_duitsland` decimal(10,2) DEFAULT 0.00,
  `kostprijs` decimal(10,2) DEFAULT 0.00,
  `extra_kosten` decimal(10,2) DEFAULT 0.00,
  `passagiers` int(11) DEFAULT 0,
  `totaal_km` int(11) DEFAULT 0,
  `totaal_uren` decimal(10,2) DEFAULT 0.00,
  `status_offerte` tinyint(4) DEFAULT 0,
  `status_bevestiging` tinyint(4) DEFAULT 0,
  `status_ritopdracht` tinyint(4) DEFAULT 0,
  `status_factuur` tinyint(4) DEFAULT 0,
  `status_betaald` tinyint(4) DEFAULT 0,
  `datum_offerte_verstuurd` datetime DEFAULT NULL,
  `is_betaald` tinyint(1) NOT NULL DEFAULT 0,
  `datum_bevestiging_verstuurd` datetime DEFAULT NULL,
  `datum_factuur_verstuurd` datetime DEFAULT NULL,
  `datum_ritopdracht_verstuurd` datetime DEFAULT NULL,
  `km_tussen` int(11) DEFAULT 0,
  `km_nl` int(11) DEFAULT 0,
  `km_de` int(11) DEFAULT 0,
  `chauffeur_id` int(11) DEFAULT NULL,
  `werk_start` time DEFAULT NULL,
  `werk_eind` time DEFAULT NULL,
  `werk_pauze` int(11) DEFAULT 0,
  `werk_notities` text DEFAULT NULL,
  `instructie_kantoor` text DEFAULT NULL,
  `chf_van_a` varchar(5) DEFAULT NULL,
  `chf_tot_a` varchar(5) DEFAULT NULL,
  `chf_van_b` varchar(5) DEFAULT NULL,
  `chf_tot_b` varchar(5) DEFAULT NULL,
  `werkelijke_km` int(11) DEFAULT NULL,
  `km_start` int(11) DEFAULT NULL,
  `km_eind` int(11) DEFAULT NULL,
  `betaalwijze` varchar(50) DEFAULT 'Rekening',
  `betaald_bedrag` decimal(10,2) DEFAULT NULL,
  `rit_status` varchar(50) DEFAULT 'Gepland',
  `contactpersoon` varchar(255) DEFAULT NULL,
  `is_gefactureerd` tinyint(1) DEFAULT 0,
  `afwijzings_reden` text DEFAULT NULL,
  `extra_voertuigen` varchar(255) DEFAULT NULL,
  `opmerkingen_chauffeur` text DEFAULT NULL,
  `geaccepteerd_op` datetime DEFAULT NULL,
  `klant_opmerking` text DEFAULT NULL,
  `definitieve_pax` int(11) DEFAULT NULL,
  `contact_dag_zelf` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `calculaties`
--

INSERT INTO `calculaties` (`id`, `token`, `klant_id`, `contact_id`, `afdeling_id`, `rittype`, `voertuig_id`, `rittype_id`, `aantal_bussen`, `titel`, `vertrek_datum`, `vertrek_locatie`, `bestemming`, `prijs_totaal`, `status`, `aangemaakt_op`, `rit_datum`, `rit_datum_eind`, `vertrek_adres`, `aankomst_adres`, `aantal_personen`, `afstand_km`, `km_duitsland`, `uren`, `toeslag_percentage`, `prijs`, `btw_bedrag_nl`, `btw_bedrag_duitsland`, `kostprijs`, `extra_kosten`, `passagiers`, `totaal_km`, `totaal_uren`, `status_offerte`, `status_bevestiging`, `status_ritopdracht`, `status_factuur`, `status_betaald`, `datum_offerte_verstuurd`, `is_betaald`, `datum_bevestiging_verstuurd`, `datum_factuur_verstuurd`, `datum_ritopdracht_verstuurd`, `km_tussen`, `km_nl`, `km_de`, `chauffeur_id`, `werk_start`, `werk_eind`, `werk_pauze`, `werk_notities`, `instructie_kantoor`, `chf_van_a`, `chf_tot_a`, `chf_van_b`, `chf_tot_b`, `werkelijke_km`, `km_start`, `km_eind`, `betaalwijze`, `betaald_bedrag`, `rit_status`, `contactpersoon`, `is_gefactureerd`, `afwijzings_reden`, `extra_voertuigen`, `opmerkingen_chauffeur`, `geaccepteerd_op`, `klant_opmerking`, `definitieve_pax`, `contact_dag_zelf`) VALUES
(2, NULL, 211, 31, NULL, 'dagtocht', 4, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 10:28:14', '2026-03-23', '2026-03-23', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 325.00, 0.00, 0.00, 0.00, 0.00, 45, 32, 2.30, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, '2026-04-10 00:00:00', NULL, '2026-03-20 00:00:00', 0, 0, 0, 5, NULL, NULL, 0, NULL, 'contactpersoon Tabois tel. 0614848323', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, NULL, 211, 31, NULL, 'dagtocht', 4, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 10:35:19', '2026-03-24', '2026-03-24', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 925.00, 0.00, 0.00, 0.00, 0.00, 50, 311, 10.35, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, NULL, 211, 56, NULL, 'dagtocht', 4, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 11:06:19', '2026-03-25', '2026-03-25', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 875.00, 0.00, 0.00, 0.00, 0.00, 50, 248, 12.53, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, NULL, 167, 16, NULL, 'dagtocht', 3, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 11:18:44', '2026-03-25', '2026-03-25', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 450.00, 0.00, 0.00, 0.00, 0.00, 30, 87, 4.83, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 11:19:04', 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, NULL, 601, 75, NULL, 'dagtocht', 3, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 11:39:37', '2026-03-27', '2026-03-27', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 750.00, 0.00, 0.00, 0.00, 0.00, 58, 262, 9.68, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, 5, NULL, NULL, 0, NULL, '1e adres: 08:20 uur Beukenlaan 34 Barchem, Rijden Barchemseweg / Zutphenseweg\r\n2e adres: 08:40 uur Bushalte Lochemseweg Harfsen, Intappen tegenover de supermarkt (richting Epse)\r\nRetour zelfde adressen', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, NULL, 983, 76, NULL, 'dagtocht', 4, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 11:48:59', '2026-03-28', '2026-03-28', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 700.00, 0.00, 0.00, 0.00, 0.00, 50, 169, 7.08, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-21 13:10:50', 0, 0, 0, 12, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, NULL, 90, 0, NULL, 'dagtocht', 5, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 11:51:13', '2026-03-28', '2026-03-28', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 575.00, 0.00, 0.00, 0.00, 0.00, 19, 184, 6.98, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, NULL, NULL, NULL, 0, NULL, 'dit is het 2e ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, NULL, 1248, 78, NULL, 'brenghaal', 4, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 12:13:18', '2026-03-28', '2026-03-28', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 750.00, 0.00, 0.00, 0.00, 0.00, 30, 289, 5.58, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, NULL, 167, 16, NULL, 'schoolreis', 1, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-20 17:04:08', '2026-03-26', '2026-03-26', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 450.00, 0.00, 0.00, 0.00, 0.00, 26, 99, 4.62, 0, 0, 0, 0, 0, '2026-03-20 00:00:00', 0, NULL, NULL, '2026-03-20 00:00:00', 0, 0, 0, 3, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, NULL, 1, 0, NULL, 'dagtocht', NULL, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-21 13:29:44', '2026-03-22', '2026-03-22', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 360.00, 0.00, 0.00, 0.00, 0.00, 50, 95, 7.47, 0, 0, 0, 0, 0, NULL, 0, NULL, NULL, '2026-03-21 13:36:37', 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, NULL, 1, 0, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-23 17:31:51', '2026-03-23', '2026-03-23', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 2045.00, 0.00, 0.00, 0.00, 0.00, 50, 279, 12.65, 0, 0, 0, 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, '9', NULL, NULL, NULL, NULL, NULL),
(13, '670fcc0cd533fa1891f11963a28425f367e9122f', 8, 8, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'geaccepteerd', '2026-03-23 20:16:34', '2026-03-26', '2026-03-26', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 935.00, 0.00, 0.00, 0.00, 0.00, 80, 59, 8.10, 0, 0, 0, 0, 0, '2026-03-31 15:12:00', 0, '2026-03-23 00:00:00', NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, '8', NULL, '2026-03-31 23:11:46', NULL, 80, ''),
(14, NULL, 209, 0, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-30 10:50:11', '2026-03-30', '2026-03-30', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 900.00, 0.00, 0.00, 0.00, 0.00, 50, 302, 7.17, 0, 0, 0, 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, '4a7e735ffd1b0f2037552e4793d08be6c9a2ea20', 8, 232, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'geaccepteerd', '2026-03-30 21:49:25', '2026-03-30', '2026-03-30', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 650.00, 0.00, 0.00, 0.00, 0.00, 50, 229, 7.07, 0, 0, 0, 0, 0, '2026-03-31 23:20:18', 0, '2026-03-31 21:30:29', NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, 'WC Open', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, '2026-03-31 23:20:56', NULL, 50, ''),
(16, NULL, 1357, 0, NULL, 'brenghaal', NULL, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-03-31 11:40:54', '2026-03-14', '2026-03-17', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 335.00, 0.00, 0.00, 0.00, 0.00, 50, 239, 6.95, 0, 0, 0, 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, NULL, 1357, 0, NULL, 'dagtocht', NULL, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-04-09 14:17:38', '2026-04-17', '2026-04-17', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 50, 3318, 0.00, 0, 0, 0, 0, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, NULL, 94, 293, 4, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-04-12 11:42:22', '2026-04-12', '2026-04-12', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 750.00, 0.00, 0.00, 0.00, 0.00, 50, 194, 7.55, 0, 0, 0, 0, 0, '2026-04-12 00:00:00', 0, '2026-04-12 00:00:00', NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, 'Prijs is incl. dieseltoeslag', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, NULL, 8, NULL, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-04-17 23:43:51', '2026-04-17', '2026-04-17', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 310.00, 0.00, 0.00, 0.00, 0.00, 50, 24, 5.77, 0, 0, 0, 0, 0, '2026-04-17 00:00:00', 0, '2026-04-17 00:00:00', NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, NULL, 8, 232, NULL, 'dagtocht', 8, NULL, 1, '', '0000-00-00 00:00:00', '', '', 0.00, 'concept', '2026-04-17 23:48:00', '2026-04-17', '2026-04-17', NULL, NULL, NULL, NULL, 0.0, 0.00, 0, 200.00, 0.00, 0.00, 0.00, 0.00, 50, 24, 3.77, 0, 0, 0, 0, 0, '2026-04-17 00:00:00', 0, '2026-04-17 00:00:00', NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Rekening', NULL, 'Gepland', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `calculatie_instellingen`
--

CREATE TABLE `calculatie_instellingen` (
  `id` int(11) NOT NULL,
  `uurloon_basis` decimal(10,2) NOT NULL DEFAULT 35.00,
  `touringcar_factor` decimal(4,2) NOT NULL DEFAULT 1.15,
  `winstmarge_perc` decimal(5,2) NOT NULL DEFAULT 25.00,
  `btw_nl` decimal(5,2) NOT NULL DEFAULT 9.00,
  `mwst_de` decimal(5,2) NOT NULL DEFAULT 19.00,
  `laatst_gewijzigd` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `calculatie_instellingen`
--

INSERT INTO `calculatie_instellingen` (`id`, `uurloon_basis`, `touringcar_factor`, `winstmarge_perc`, `btw_nl`, `mwst_de`, `laatst_gewijzigd`) VALUES
(1, 35.00, 1.15, 25.00, 9.00, 19.00, '2026-02-13 21:18:05');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `calculatie_regels`
--

CREATE TABLE `calculatie_regels` (
  `id` int(11) NOT NULL,
  `calculatie_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `label` varchar(255) NOT NULL,
  `tijd` varchar(10) NOT NULL,
  `adres` varchar(255) NOT NULL,
  `km` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `calculatie_regels`
--

INSERT INTO `calculatie_regels` (`id`, `calculatie_id`, `type`, `label`, `tijd`, `adres`, `km`) VALUES
(358, 47, 't_garage', '', '', 'Industrieweg 95, Zutphen', 0),
(359, 47, 't_voorstaan', '', '', 'stationstraat wijhe, wijhe', 0),
(360, 47, 't_vertrek_klant', '', '', 'stationstraat wijhe, wijhe', 0),
(361, 47, 't_retour_garage_heen', '', '', 'Industrieweg 95, Zutphen', 0),
(362, 47, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(363, 47, 't_retour_klant', '', '', 'stationstraat wijhe, wijhe', 0),
(364, 47, 't_retour_garage', '', '', 'Industrieweg 95, Zutphen', 0),
(365, 39, 't_garage', 't_garage', '07:15:00', '', 0),
(366, 39, 't_voorstaan', 't_voorstaan', '07:45:00', 'De Stoven 37, Zutphen', 0),
(367, 39, 't_vertrek_klant', 't_vertrek_klant', '08:00:00', 'De Stoven 37, Zutphen', 0),
(368, 39, 't_aankomst_best', 't_aankomst_best', '08:52:00', 'Bommelwereld, Maarsevonder, Groenlo, Nederland', 42),
(369, 39, 't_vertrek_best', 't_vertrek_best', '16:00:00', 'Bommelwereld, Maarsevonder, Groenlo, Nederland', 0),
(370, 39, 't_retour_klant', 't_retour_klant', '17:00:00', 'De Stoven 37, Zutphen', 0),
(371, 39, 't_retour_garage', 't_retour_garage', '17:45:00', '', 0),
(400, 36, 't_garage', 't_garage', '12:33:00', 'Industrieweg 95, Zutphen', 0),
(401, 36, 't_voorstaan', 't_voorstaan', '12:45:00', 'De Stoven 37, Zutphen', 7),
(402, 36, 't_vertrek_klant', 't_vertrek_klant', '13:00:00', 'De Stoven 37, Zutphen', 0),
(403, 36, 't_aankomst_best', 't_aankomst_best', '19:00:00', 'Arnhem, Nederland', 38),
(404, 36, 't_vertrek_best', 't_vertrek_best', '19:30:00', 'Apenheul, J.C. Wilslaan, Apeldoorn, Nederland', 34),
(405, 36, 't_retour_klant', 't_retour_klant', '20:19:00', 'De Stoven 37, Zutphen', 42),
(406, 36, 't_retour_garage', 't_retour_garage', '20:47:00', 'Industrieweg 95, Zutphen', 7),
(430, 49, 't_garage', 't_garage', '18:20:00', 'Industrieweg 95, Zutphen', 0),
(431, 49, 't_voorstaan', 't_voorstaan', '18:45:00', '', 0),
(432, 49, 't_vertrek_klant', 't_vertrek_klant', '19:00:00', 'Oud Lochemseweg 40, Wilp, Nederland', 19),
(433, 49, 't_aankomst_best', 't_aankomst_best', '20:52:00', 'Lekkerkerk, Nederland', 123),
(434, 49, 't_vertrek_best', 't_vertrek_best', '23:30:00', 'Lekkerkerk, Nederland', 0),
(435, 49, 't_retour_klant', 't_retour_klant', '01:20:00', 'Oud Lochemseweg 40, Wilp, Nederland', 121),
(436, 49, 't_retour_garage', 't_retour_garage', '02:00:00', 'Industrieweg 95, Zutphen', 20),
(437, 50, 't_garage', '', '18:20', 'Industrieweg 95, Zutphen', 0),
(438, 50, 't_voorstaan', '', '18:45', 'Oud Lochemseweg 40, Wilp, Nederland', 19),
(439, 50, 't_vertrek_klant', '', '19:00', 'Oud Lochemseweg 40, Wilp, Nederland', 0),
(440, 50, 't_aankomst_best', '', '20:52', 'Lekkerkerk, Nederland', 123),
(441, 50, 't_retour_garage_heen', '', '21:37', 'Industrieweg 95, Zutphen', 0),
(442, 50, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(443, 50, 't_vertrek_best', '', '23:30', 'Lekkerkerk, Nederland', 0),
(444, 50, 't_retour_klant', '', '01:20', 'Oud Lochemseweg, Wilp, Nederland', 121),
(445, 50, 't_retour_garage', '', '02:00', 'Industrieweg 95, Zutphen', 20),
(446, 51, 't_garage', '', '07:33', 'Industrieweg 95, Zutphen', 0),
(447, 51, 't_voorstaan', '', '07:45', 'Fletcher Resort-Hotel Zutphen, De Stoven, Zutphen, Nederland', 7),
(448, 51, 't_vertrek_klant', '', '08:00', 'Fletcher Resort-Hotel Zutphen, De Stoven, Zutphen, Nederland', 0),
(449, 51, 't_aankomst_best', '', '08:57', 'Aalten, Nederland', 51),
(450, 51, 't_retour_garage_heen', '', '10:11', 'Industrieweg 95, Zutphen', 46),
(451, 51, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(452, 51, 't_retour_garage', '', '', 'Industrieweg 95, Zutphen', 0),
(469, 53, 't_garage', '', '11:33', 'Industrieweg 95, Zutphen', 0),
(470, 53, 't_voorstaan', '', '11:45', 'De Stoven 37, Zutphen', 7),
(471, 53, 't_vertrek_klant', '', '12:00', 'De Stoven 37, Zutphen', 0),
(472, 53, 't_aankomst_best', '', '14:22', 'Groningen, Nederland', 144),
(473, 53, 't_retour_garage_heen', '', '15:07', 'Industrieweg 95, Zutphen', 0),
(474, 53, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(475, 53, 't_vertrek_best', '', '20:00', 'Groningen, Nederland', 0),
(476, 53, 't_retour_klant', '', '22:17', 'De Stoven 37, Zutphen', 143),
(477, 53, 't_retour_garage', '', '22:45', 'Industrieweg 95, Zutphen', 7),
(478, 40, 't_garage', 't_garage', '11:15:00', '', 0),
(479, 40, 't_voorstaan', 't_voorstaan', '11:45:00', 'De Stoven 37, Zutphen', 0),
(480, 40, 't_vertrek_klant', 't_vertrek_klant', '12:00:00', 'De Stoven 37, Zutphen', 0),
(481, 40, 't_aankomst_best', 't_aankomst_best', '12:12:00', 'Geweldigershoek, Zutphen, Nederland', 5),
(482, 40, 't_vertrek_best', 't_vertrek_best', '21:00:00', 'Geweldigershoek, Zutphen, Nederland', 0),
(483, 40, 't_retour_klant', 't_retour_klant', '22:00:00', 'De Stoven 37, Zutphen', 0),
(484, 40, 't_retour_garage', 't_retour_garage', '22:45:00', '', 0),
(501, 54, 't_garage', 't_garage', '11:33:00', 'Industrieweg 95, Zutphen', 0),
(502, 54, 't_voorstaan', 't_voorstaan', '11:45:00', 'De Stoven 37, Zutphen', 7),
(503, 54, 't_vertrek_klant', 't_vertrek_klant', '12:00:00', 'De Stoven 37, Zutphen', 0),
(504, 54, 't_aankomst_best', 't_aankomst_best', '13:49:00', 'Meerweg 26, Hijken, Nederland', 102),
(505, 54, 't_retour_garage_heen', 't_retour_garage_heen', '14:34:00', 'Industrieweg 95, Zutphen', 0),
(506, 54, 't_garage_rit2', 't_garage_rit2', '', 'Industrieweg 95, Zutphen', 0),
(507, 54, 't_voorstaan_rit2', 't_voorstaan_rit2', '', '', 0),
(508, 54, 't_vertrek_best', 't_vertrek_best', '16:00:00', 'Meerweg 26, Hijken, Nederland', 0),
(509, 54, 't_retour_klant', 't_retour_klant', '17:47:00', 'De Stoven 37, Zutphen', 102),
(510, 54, 't_retour_garage', 't_retour_garage', '18:15:00', 'Industrieweg 95, Zutphen', 7),
(561, 52, 't_garage', 't_garage', '15:25:00', 'Industrieweg 95, Zutphen', 0),
(562, 52, 't_voorstaan', 't_voorstaan', '15:45:00', 'De Stoven 37, Zutphen', 15),
(563, 52, 't_vertrek_klant', 't_vertrek_klant', '16:00:00', 'De Stoven 37, Zutphen', 0),
(564, 52, 't_aankomst_best', 't_aankomst_best', '16:45:00', 'Aalten, Nederland', 36),
(565, 52, 't_retour_garage_heen', 't_retour_garage_heen', '', '', 0),
(566, 52, 't_garage_rit2', 't_garage_rit2', '', '', 0),
(567, 52, 't_voorstaan_rit2', 't_voorstaan_rit2', '', '', 0),
(568, 52, 't_vertrek_best', 't_vertrek_best', '20:00:00', 'Aalten, Nederland', 0),
(569, 52, 't_retour_klant', 't_retour_klant', '20:45:00', 'De Stoven 37, Zutphen', 34),
(570, 52, 't_retour_garage', 't_retour_garage', '21:21:00', 'Industrieweg 95, Zutphen', 15),
(580, 55, 't_garage', 't_garage', '08:19:00', 'Industrieweg 95, Zutphen', 0),
(581, 55, 't_voorstaan', 't_voorstaan', '08:45:00', ' , Lochem', 19),
(582, 55, 't_vertrek_klant', 't_vertrek_klant', '09:00:00', ' , Lochem', 0),
(583, 55, 't_aankomst_best', 't_aankomst_best', '11:24:00', 'Den Haag, Nederland', 166),
(584, 55, 't_retour_garage_heen', 't_retour_garage_heen', '12:09:00', 'Industrieweg 95, Zutphen', 0),
(585, 55, 't_garage_rit2', 't_garage_rit2', '', 'Industrieweg 95, Zutphen', 0),
(586, 55, 't_voorstaan_rit2', 't_voorstaan_rit2', '', '', 0),
(587, 55, 't_vertrek_best', 't_vertrek_best', '18:00:00', 'Den Haag, Nederland', 0),
(588, 55, 't_retour_klant', 't_retour_klant', '20:21:00', ' , Lochem', 172),
(589, 55, 't_retour_garage', 't_retour_garage', '21:00:00', 'Industrieweg 95, Zutphen', 18),
(699, 2, 't_garage', '', '07:51', 'Industrieweg 95, Zutphen', 0),
(700, 2, 't_voorstaan', '', '08:00', 'Lage Weide 1, Warnsveld', 5),
(701, 2, 't_vertrek_klant', '', '08:15', 'Lage Weide 1, Warnsveld', 0),
(702, 2, 't_aankomst_best', '', '08:32', 'Dorpsstraat 24, Vorden, Nederland', 12),
(703, 2, 't_retour_garage_heen', '', '09:17', 'Industrieweg 95, Zutphen', 0),
(704, 2, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(705, 2, 't_vertrek_best', '', '09:30', 'Dorpsstraat 24, Vorden, Nederland', 0),
(706, 2, 't_retour_klant', '', '09:45', 'Lage Weide 1, Warnsveld', 10),
(707, 2, 't_retour_garage', '', '10:09', 'Industrieweg 95, Zutphen', 5),
(708, 3, 't_garage', '', '08:06', 'Industrieweg 95, Zutphen', 0),
(709, 3, 't_voorstaan', '', '08:15', 'Lage Weide 1, Warnsveld', 5),
(710, 3, 't_vertrek_klant', '', '08:30', 'Lage Weide 1, Warnsveld', 0),
(711, 3, 't_aankomst_best', '', '10:36', 'Paul Nijghkade 5, Rotterdam, Nederland', 150),
(712, 3, 't_retour_garage_heen', '', '11:21', 'Industrieweg 95, Zutphen', 0),
(713, 3, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(714, 3, 't_vertrek_best', '', '16:00', 'Markthal, Verlengde Nieuwstraat, Rotterdam, Nederland', 4),
(715, 3, 't_retour_klant', '', '18:03', 'Lage Weide 1, Warnsveld', 147),
(716, 3, 't_retour_garage', '', '18:27', 'Industrieweg 95, Zutphen', 5),
(717, 4, 't_garage', '', '08:36', 'Industrieweg 95, Zutphen', 0),
(718, 4, 't_voorstaan', '', '08:45', 'Lage Weide 1, Warnsveld', 5),
(719, 4, 't_vertrek_klant', '', '09:00', 'Lage Weide 1, Warnsveld', 0),
(720, 4, 't_aankomst_best', '', '10:45', 'Museumplein, Amsterdam, Nederland', 119),
(721, 4, 't_retour_garage_heen', '', '11:30', 'Industrieweg 95, Zutphen', 0),
(722, 4, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(723, 4, 't_vertrek_best', '', '19:00', 'Museumplein, Amsterdam, Nederland', 0),
(724, 4, 't_retour_klant', '', '20:44', 'Lage Weide 1, Warnsveld', 119),
(725, 4, 't_retour_garage', '', '21:08', 'Industrieweg 95, Zutphen', 5),
(726, 5, 't_garage', '', '09:20', 'Industrieweg 95, Zutphen', 0),
(727, 5, 't_voorstaan', '', '09:30', 'Dr. De Visserstraat 4, Zutphen', 6),
(728, 5, 't_vertrek_klant', '', '09:45', 'Dr. De Visserstraat 4, Zutphen', 0),
(729, 5, 't_aankomst_best', '', '10:42', 'Paleis Het Loo, Koninklijk Park, Apeldoorn, Nederland', 31),
(730, 5, 't_retour_garage_heen', '', '11:27', 'Industrieweg 95, Zutphen', 0),
(731, 5, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(732, 5, 't_vertrek_best', '', '12:45', 'Paleis Het Loo, Koninklijk Park, Apeldoorn, Nederland', 0),
(733, 5, 't_retour_klant', '', '13:44', 'Dr. De Visserstraat 4, Zutphen', 44),
(734, 5, 't_retour_garage', '', '14:10', 'Industrieweg 95, Zutphen', 6),
(735, 6, 't_garage', '', '07:36', 'Industrieweg 95, Zutphen', 0),
(736, 6, 't_voorstaan', '', '08:05', 'Beukenlaan 34, Barchem, Nederland', 22),
(737, 6, 't_vertrek_klant', '', '08:20', 'Beukenlaan 34, Barchem, Nederland', 0),
(738, 6, 't_aankomst_best', '', '10:07', 'Oosthalen 8, Hooghalen, Nederland', 110),
(739, 6, 't_retour_garage_heen', '', '10:52', 'Industrieweg 95, Zutphen', 0),
(740, 6, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(741, 6, 't_vertrek_best', '', '14:45', 'Oosthalen 8, Hooghalen, Nederland', 0),
(742, 6, 't_retour_klant', '', '16:33', 'Beukenlaan 34, Barchem, Nederland', 108),
(743, 6, 't_retour_garage', '', '17:17', 'Industrieweg 95, Zutphen', 22),
(744, 7, 't_garage', '', '12:32', 'Industrieweg 95, Zutphen', 0),
(745, 7, 't_voorstaan', '', '12:45', 'Meijerinkpad 1, Zutphen', 9),
(746, 7, 't_vertrek_klant', '', '13:00', 'Meijerinkpad 1, Zutphen', 0),
(747, 7, 't_aankomst_best', '', '14:08', 'SDC\'12, Kappelhofsweg, Denekamp, Nederland', 75),
(748, 7, 't_retour_garage_heen', '', '14:53', 'Industrieweg 95, Zutphen', 0),
(749, 7, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(750, 7, 't_vertrek_best', '', '18:00', 'SDC\'12, Kappelhofsweg, Denekamp, Nederland', 0),
(751, 7, 't_retour_klant', '', '19:08', 'Meijerinkpad 1, Zutphen', 76),
(752, 7, 't_retour_garage', '', '19:37', 'Industrieweg 95, Zutphen', 9),
(753, 8, 't_garage', '', '10:32', 'Industrieweg 95, Zutphen', 0),
(754, 8, 't_voorstaan', '', '10:45', 'Meijerinkpad 1, Zutphen', 9),
(755, 8, 't_vertrek_klant', '', '11:00', 'Meijerinkpad 1, Zutphen', 0),
(756, 8, 't_aankomst_best', '', '12:18', 'S.V. Nieuwleusen, Koningin Julianalaan, Nieuwleusen, Nederland', 83),
(757, 8, 't_retour_garage_heen', '', '13:03', 'Industrieweg 95, Zutphen', 0),
(758, 8, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(759, 8, 't_vertrek_best', '', '15:45', 'S.V. Nieuwleusen, Koningin Julianalaan, Nieuwleusen, Nederland', 0),
(760, 8, 't_retour_klant', '', '17:02', 'Meijerinkpad 1, Zutphen', 83),
(761, 8, 't_retour_garage', '', '17:31', 'Industrieweg 95, Zutphen', 9),
(762, 9, 't_garage', '', '12:23', 'Industrieweg 95, Zutphen', 0),
(763, 9, 't_voorstaan', '', '12:45', 'Loubergweg 7, Eerbeek', 14),
(764, 9, 't_vertrek_klant', '', '13:00', 'Loubergweg 7, Eerbeek', 0),
(765, 9, 't_aankomst_best', '', '14:03', 'Preston Palace, Laan van Iserlohn, Almelo, Nederland', 73),
(766, 9, 't_retour_garage_heen', '', '15:09', 'Industrieweg 95, Zutphen', 57),
(767, 9, 't_garage_rit2', '', '18:23', 'Industrieweg 95, Zutphen', 0),
(768, 9, 't_voorstaan_rit2', '', '19:15', 'Preston Palace, Laan van Iserlohn, Almelo, Nederland', 57),
(769, 9, 't_vertrek_best', '', '19:30', 'Preston Palace, Laan van Iserlohn, Almelo, Nederland', 0),
(770, 9, 't_retour_klant', '', '20:34', 'Loubergweg 7, Eerbeek', 73),
(771, 9, 't_retour_garage', '', '21:12', 'Industrieweg 95, Zutphen', 15),
(772, 10, 't_garage', '', '09:05', 'Industrieweg 95, Zutphen', 0),
(773, 10, 't_voorstaan', '', '09:15', 'Dr. De Visserstraat 4, Zutphen', 6),
(774, 10, 't_vertrek_klant', '', '09:30', 'Dr. De Visserstraat 4, Zutphen', 0),
(775, 10, 't_aankomst_best', '', '10:16', 'Rijssens Museum, Kasteellaan, Rijssen, Nederland', 45),
(776, 10, 't_retour_garage_heen', '', '11:01', 'Industrieweg 95, Zutphen', 0),
(777, 10, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(778, 10, 't_vertrek_best', '', '12:30', 'Rijssens Museum, Kasteellaan, Rijssen, Nederland', 0),
(779, 10, 't_retour_klant', '', '13:16', 'Dr. De Visserstraat 4, Zutphen', 42),
(780, 10, 't_retour_garage', '', '13:42', 'Industrieweg 95, Zutphen', 6),
(800, 11, 't_garage', 't_garage', '11:15:00', 'Industrieweg 95, Zutphen', 0),
(801, 11, 't_voorstaan', 't_voorstaan', '11:45:00', 'Industrieweg 95A, Zutphen', 1),
(802, 11, 't_vertrek_klant', 't_vertrek_klant', '12:00:00', 'Industrieweg 95A, Zutphen', 0),
(803, 11, 't_aankomst_best', 't_aankomst_best', '12:58:00', 'Aalten, Nederland', 47),
(804, 11, 't_retour_garage_heen', 't_retour_garage_heen', '13:43:00', 'Industrieweg 95, Zutphen', 0),
(805, 11, 't_garage_rit2', 't_garage_rit2', '', 'Industrieweg 95, Zutphen', 0),
(806, 11, 't_voorstaan_rit2', 't_voorstaan_rit2', '', '', 0),
(807, 11, 't_vertrek_best', 't_vertrek_best', '17:00:00', 'Aalten, Nederland', 0),
(808, 11, 't_retour_klant', 't_retour_klant', '17:58:00', 'Industrieweg 95A, Zutphen', 46),
(809, 11, 't_retour_garage', 't_retour_garage', '18:43:00', 'Industrieweg 95, Zutphen', 1),
(810, 12, 't_garage', '', '11:15', 'Industrieweg 95, Zutphen', 0),
(811, 12, 't_voorstaan', '', '11:45', 'Industrieweg 95A, Zutphen', 1),
(812, 12, 't_vertrek_klant', '', '12:00', 'Industrieweg 95A, Zutphen', 0),
(813, 12, 't_aankomst_best', '', '14:14', 'Groningen, Nederland', 139),
(814, 12, 't_retour_garage_heen', '', '14:59', 'Industrieweg 95, Zutphen', 0),
(815, 12, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(816, 12, 't_vertrek_best', '', '21:00', 'Groningen, Nederland', 0),
(817, 12, 't_retour_klant', '', '23:09', 'Industrieweg 95A, Zutphen', 138),
(818, 12, 't_retour_garage', '', '23:54', 'Industrieweg 95, Zutphen', 1),
(819, 13, 't_garage', '', '15:15', 'Industrieweg 95, Zutphen', 0),
(820, 13, 't_voorstaan', '', '15:45', 'Industrieweg 95a, Zutphen', 1),
(821, 13, 't_vertrek_klant', '', '16:00', 'Industrieweg 95a, Zutphen', 0),
(822, 13, 't_aankomst_best', '', '16:35', 'Apeldoorn, Nederland', 28),
(823, 13, 't_retour_garage_heen', '', '17:20', 'Industrieweg 95, Zutphen', 0),
(824, 13, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(825, 13, 't_vertrek_best', '', '22:00', 'Apeldoorn, Nederland', 0),
(826, 13, 't_retour_klant', '', '22:36', 'Industrieweg 95a, Zutphen', 29),
(827, 13, 't_retour_garage', '', '23:21', 'Industrieweg 95, Zutphen', 1),
(828, 14, 't_garage', '', '15:20', 'Industrieweg 95, Zutphen', 0),
(829, 14, 't_voorstaan', '', '15:45', 'Adm. Helfrichlaan 89, Dieren', 18),
(830, 14, 't_vertrek_klant', '', '16:00', 'Adm. Helfrichlaan 89, Dieren', 0),
(831, 14, 't_aankomst_best', '', '17:51', 'Rotterdam Ahoy, Ahoyweg, Rotterdam, Nederland', 133),
(832, 14, 't_retour_garage_heen', '', '18:36', 'Industrieweg 95, Zutphen', 0),
(833, 14, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(834, 14, 't_vertrek_best', '', '20:00', 'Rotterdam Ahoy, Ahoyweg, Rotterdam, Nederland', 0),
(835, 14, 't_retour_klant', '', '21:50', 'Adm. Helfrichlaan 89, Dieren', 134),
(836, 14, 't_retour_garage', '', '22:30', 'Industrieweg 95, Zutphen', 17),
(837, 15, 't_garage', '', '11:15', 'Industrieweg 95, Zutphen', 0),
(838, 15, 't_voorstaan', '', '11:45', 'Industrieweg 95a, Zutphen', 1),
(839, 15, 't_vertrek_klant', '', '12:00', 'Industrieweg 95a, Zutphen', 0),
(840, 15, 't_aankomst_best', '', '13:34', 'Amsterdam, Nederland', 114),
(841, 15, 't_retour_garage_heen', '', '14:19', 'Industrieweg 95, Zutphen', 0),
(842, 15, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(843, 15, 't_vertrek_best', '', '16:00', 'Amsterdam, Nederland', 0),
(844, 15, 't_retour_klant', '', '17:34', 'Industrieweg 95a, Zutphen', 113),
(845, 15, 't_retour_garage', '', '18:19', 'Industrieweg 95, Zutphen', 1),
(846, 16, 't_garage', '', '07:03', 'Industrieweg 95, Zutphen', 0),
(847, 16, 't_voorstaan', '', '08:45', 'Flughafen Weeze - Flughafen Niederrhein GmbH (NRN), Flughafen-Ring, Weeze, Duitsland', 97),
(848, 16, 't_vertrek_klant', '', '09:00', 'Flughafen Weeze (NRN), Flughafen-Ring, Weeze, Duitsland', 0),
(849, 16, 't_aankomst_best', '', '10:38', 'Apeldoorn, Nederland', 117),
(850, 16, 't_retour_garage_heen', '', '11:30', 'Industrieweg 95, Zutphen', 25),
(851, 16, 't_garage_rit2', '', '04:45', 'Industrieweg 95, Zutphen', 0),
(852, 16, 't_voorstaan_rit2', '', '05:15', '', 0),
(853, 16, 't_vertrek_best', '', '05:30', 'Apeldoorn', 0),
(854, 16, 't_retour_klant', '', '06:30', '', 0),
(855, 16, 't_retour_garage', '', '07:15', 'Industrieweg 95, Zutphen', 0),
(856, 17, 't_garage', '', '', 'Industrieweg 95, Zutphen', 0),
(857, 17, 't_voorstaan', '', '', 'Flughafen Weeze (NRN), Flughafen-Ring, Weeze, Duitsland', 97),
(858, 17, 't_vertrek_klant', '', '', 'Flughafen Weeze (NRN), Flughafen-Ring, Weeze, Duitsland', 1583),
(859, 17, 't_retour_garage_heen', '', '', 'Industrieweg 95, Zutphen', 0),
(860, 17, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(861, 17, 't_retour_klant', '', '', 'connecto, ?', 0),
(862, 17, 't_retour_garage', '', '', 'Industrieweg 95, Zutphen', 1638),
(926, 18, 't_garage', 't_garage', '11:24:00', 'Industrieweg 95, Zutphen', 0),
(927, 18, 't_voorstaan', 't_voorstaan', '11:30:00', 'Fanny Blankers-Koenweg 8, Zutphen', 3),
(928, 18, 't_vertrek_klant', 't_vertrek_klant', '11:45:00', 'Fanny Blankers-Koenweg 8, Zutphen', 0),
(929, 18, 't_aankomst_best', 't_aankomst_best', '13:20:00', 'CVV Germanicus, Sportlaan, Coevorden, Nederland', 94),
(930, 18, 't_retour_garage_heen', 't_retour_garage_heen', '14:05:00', 'Industrieweg 95, Zutphen', 0),
(931, 18, 't_garage_rit2', 't_garage_rit2', '', 'Industrieweg 95, Zutphen', 0),
(932, 18, 't_vertrek_best', 't_vertrek_best', '17:00:00', 'CVV Germanicus, Sportlaan, Coevorden, Nederland', 0),
(933, 18, 't_retour_klant', 't_retour_klant', '18:35:00', 'Fanny Blankers-Koenweg 8, Zutphen', 94),
(934, 18, 't_retour_garage', 't_retour_garage', '18:57:00', 'Industrieweg 95, Zutphen', 3),
(935, 19, 't_garage', '', '11:15', 'Industrieweg 95, Zutphen', 0),
(936, 19, 't_voorstaan', '', '11:45', '', 1),
(937, 19, 't_vertrek_klant', '', '12:00', '', 0),
(938, 19, 't_aankomst_best', '', '12:16', '', 11),
(939, 19, 't_retour_garage_heen', '', '13:01', 'Industrieweg 95, Zutphen', 0),
(940, 19, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(941, 19, 't_vertrek_best', '', '16:00', '', 0),
(942, 19, 't_retour_klant', '', '16:16', '', 11),
(943, 19, 't_retour_garage', '', '17:01', 'Industrieweg 95, Zutphen', 1),
(944, 20, 't_garage', '', '12:15', 'Industrieweg 95, Zutphen', 0),
(945, 20, 't_voorstaan', '', '12:45', 'Industrieweg 95a, Zutphen', 1),
(946, 20, 't_vertrek_klant', '', '13:00', 'Industrieweg 95a, Zutphen', 0),
(947, 20, 't_aankomst_best', '', '13:16', 'Baak, Nederland', 11),
(948, 20, 't_retour_garage_heen', '', '14:01', 'Industrieweg 95, Zutphen', 0),
(949, 20, 't_garage_rit2', '', '', 'Industrieweg 95, Zutphen', 0),
(950, 20, 't_vertrek_best', '', '15:00', 'Baak, Nederland', 0),
(951, 20, 't_retour_klant', '', '15:16', 'Industrieweg 95a, Zutphen', 11),
(952, 20, 't_retour_garage', '', '16:01', 'Industrieweg 95, Zutphen', 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `calculatie_rittypes`
--

CREATE TABLE `calculatie_rittypes` (
  `id` int(11) NOT NULL,
  `naam` varchar(50) NOT NULL,
  `km_multiplier` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `calculatie_rittypes`
--

INSERT INTO `calculatie_rittypes` (`id`, `naam`, `km_multiplier`) VALUES
(1, 'Dagtocht', 2),
(2, 'Enkele reis', 2),
(3, 'Breng & haal', 4),
(4, 'Meerdaagse reis', 2);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `calculatie_voertuigen`
--

CREATE TABLE `calculatie_voertuigen` (
  `id` int(11) NOT NULL,
  `naam` varchar(50) NOT NULL,
  `kenteken` varchar(20) DEFAULT NULL,
  `capaciteit` int(11) NOT NULL,
  `km_kostprijs` decimal(10,2) NOT NULL,
  `actief` tinyint(1) DEFAULT 1,
  `heeft_wc` tinyint(1) DEFAULT 0,
  `heeft_rolstoel` tinyint(1) DEFAULT 0,
  `heeft_koffie` tinyint(1) DEFAULT 0,
  `heeft_tafels` tinyint(1) DEFAULT 0,
  `apk_datum` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `calculatie_voertuigen`
--

INSERT INTO `calculatie_voertuigen` (`id`, `naam`, `kenteken`, `capaciteit`, `km_kostprijs`, `actief`, `heeft_wc`, `heeft_rolstoel`, `heeft_koffie`, `heeft_tafels`, `apk_datum`) VALUES
(1, 'Bus 19', NULL, 19, 0.75, 0, 0, 0, 0, 0, NULL),
(2, 'Bus 23', NULL, 19, 0.75, 0, 0, 0, 0, 0, NULL),
(3, 'Bus 50', NULL, 50, 1.00, 0, 0, 0, 0, 0, NULL),
(4, 'Bus 55', NULL, 53, 1.00, 0, 0, 0, 0, 0, NULL),
(5, 'Bus 60', NULL, 60, 1.20, 0, 0, 0, 0, 0, NULL),
(6, 'Bus 62', NULL, 62, 1.20, 0, 0, 0, 0, 0, NULL),
(7, 'Bus max. 19 personen', NULL, 19, 0.75, 1, 0, 0, 0, 0, NULL),
(8, 'Bus max. 50 personen', NULL, 50, 1.00, 1, 0, 0, 0, 0, NULL),
(9, 'Bus max. 60 personen', NULL, 60, 1.20, 1, 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `chauffeurs`
--

CREATE TABLE `chauffeurs` (
  `id` int(11) NOT NULL,
  `voornaam` varchar(50) NOT NULL,
  `achternaam` varchar(50) NOT NULL,
  `pincode` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `telefoon` varchar(20) DEFAULT NULL,
  `rijbewijs_verloopt` date DEFAULT NULL,
  `wachtwoord` varchar(255) DEFAULT NULL COMMENT 'Voor later inloggen',
  `actief` tinyint(1) DEFAULT 1,
  `geboortedatum` date DEFAULT NULL,
  `datum_in_dienst` date DEFAULT NULL,
  `datum_uit_dienst` date DEFAULT NULL,
  `rijbewijsnummer` varchar(50) DEFAULT NULL,
  `bestuurderskaart` varchar(50) DEFAULT NULL,
  `bestuurderskaart_geldig_tot` date DEFAULT NULL,
  `code95_geldig_tot` date DEFAULT NULL,
  `code95_cursus1_naam` varchar(100) DEFAULT NULL,
  `code95_cursus1_datum` date DEFAULT NULL,
  `code95_cursus2_naam` varchar(100) DEFAULT NULL,
  `code95_cursus2_datum` date DEFAULT NULL,
  `code95_cursus3_naam` varchar(100) DEFAULT NULL,
  `code95_cursus3_datum` date DEFAULT NULL,
  `code95_cursus4_naam` varchar(100) DEFAULT NULL,
  `code95_cursus4_datum` date DEFAULT NULL,
  `code95_cursus5_naam` varchar(100) DEFAULT NULL,
  `code95_cursus5_datum` date DEFAULT NULL,
  `ehbo` enum('ja','nee') DEFAULT 'nee',
  `archief` tinyint(1) NOT NULL DEFAULT 0,
  `contracturen` decimal(5,2) DEFAULT 0.00,
  `token` varchar(64) DEFAULT NULL,
  `rol` enum('chauffeur','planner','beheerder') NOT NULL DEFAULT 'chauffeur',
  `telegram_chat_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `chauffeurs`
--

INSERT INTO `chauffeurs` (`id`, `voornaam`, `achternaam`, `pincode`, `email`, `telefoon`, `rijbewijs_verloopt`, `wachtwoord`, `actief`, `geboortedatum`, `datum_in_dienst`, `datum_uit_dienst`, `rijbewijsnummer`, `bestuurderskaart`, `bestuurderskaart_geldig_tot`, `code95_geldig_tot`, `code95_cursus1_naam`, `code95_cursus1_datum`, `code95_cursus2_naam`, `code95_cursus2_datum`, `code95_cursus3_naam`, `code95_cursus3_datum`, `code95_cursus4_naam`, `code95_cursus4_datum`, `code95_cursus5_naam`, `code95_cursus5_datum`, `ehbo`, `archief`, `contracturen`, `token`, `rol`, `telegram_chat_id`) VALUES
(1, 'Freddy', 'Stravers', '5500', 'freddystravers1975@live.nl', '0641255791', '2028-01-07', '$2y$10$uBDaL00OluacD6V1dqKnB.Oeb47NHz/I8uZxQa9oKIIM52W7eLG02', 1, '1975-01-03', '1999-10-01', NULL, NULL, NULL, NULL, '2028-11-18', NULL, '2027-03-20', NULL, '2027-09-12', NULL, '2026-03-26', NULL, '2026-09-12', NULL, '2027-01-16', 'nee', 0, 0.00, 'cfa3bc20e4e4dac3c0fd0c83262023b9fbcd2e4e40fe5a48033815aca22e6801', 'chauffeur', '8455096805'),
(2, 'Freddy', 'Stravers', NULL, 'freddystravers1975@live.nl', '0641255791', NULL, '$2y$10$ac8aIZVx3a1vbX2sJKul5u0SPsZhT5KbCdIDro/MV7d7SZeTcyvdW', 1, '1975-01-03', '1999-10-01', NULL, NULL, NULL, NULL, '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 1, 0.00, NULL, 'chauffeur', NULL),
(3, 'Jan ', 'Jolink', '5400', 'Janjolink8@gmail.com', '06 10400240', '2028-08-28', '$2y$10$D1BNv3CNsvVzOk4tLNLQyOQkw3wwUwxvEUM57IpmM3Xf0yabl6Bq6', 1, '1962-07-30', '2018-11-01', '2033-08-28', '5472946451', '5472946451', '2028-10-25', '2028-09-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 160.00, NULL, 'chauffeur', '8667558738'),
(4, 'Marco', 'Mensink', NULL, 'm.mensink26@gmail.com', '06 20933761', '2030-07-21', NULL, 1, '1969-12-03', '0200-09-01', NULL, '5332083546', NULL, '2029-12-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(5, 'Fred', 'Haan', NULL, 'janniehaan@gmail.com', '06 53215447', '2030-01-16', NULL, 1, '1950-07-29', '2020-01-01', NULL, '2147483647', '55185380203', '2027-03-12', '2030-12-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(6, 'Gerard', 'Hellewegen', NULL, 'g.hellewegen@chello.nl', '06 53168055', '2033-01-23', NULL, 1, '1959-12-08', '2023-06-11', NULL, '5742630674', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(7, 'Harm', 'Borninkhof', '5651', 'h.borninkhof@gmail.com', '06 31697041', '2026-06-21', NULL, 1, '1955-12-26', '2023-01-01', NULL, '5330601966', '5923453025', '2026-09-28', '2027-06-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', '8727074357'),
(8, 'Anton', 'Beker', NULL, 'anton.beker@arriva.nl', '06 46711028', NULL, NULL, 1, NULL, '2024-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(9, 'Monique', 'Westerholt', NULL, 'moonwesterholt@hotmail.com', '06 43431733', '2033-01-26', NULL, 1, '1971-01-29', '2022-01-01', NULL, '5414530612', NULL, '2027-11-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 138.66, NULL, 'chauffeur', NULL),
(10, 'Jesse', 'Berkhout', NULL, 'jesseberkhout@live.nl', '0621413839', '2035-01-09', NULL, 1, '1998-11-16', '2025-01-10', NULL, '5080698527', NULL, '2030-01-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, '8f516dfa1bcb22a3e341ffbeaeca092cf8cb0dbe0ca8f969ac0f3af6bf34c66b', 'chauffeur', NULL),
(11, 'Hilbert', 'van Dam', NULL, 'h.j.vandam@upcmail.nl', '06 51666992', '2029-08-22', NULL, 1, '1958-01-09', NULL, NULL, '5452779756', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(12, 'Hans', 'Roordink', NULL, 'hansroordink@hotmail.com', '06 21988678', '2027-01-13', NULL, 1, '1952-01-13', NULL, NULL, '5206670785', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(13, 'Angelique ', 'Everts-Pelgrim', '5500', 'info@taxiberkhout.nl', NULL, NULL, '$2y$10$GbOYEN312tshO2u2Q2kzxe6sRmqQ1lwnZsyJ.Dejlj4KXGl13WZM6', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 1, 0.00, NULL, 'chauffeur', NULL),
(14, 'Angelique', 'Everts', '1008', 'angeliqueeverts@planet.nl', '06 53582979', '2033-11-11', '$2y$10$ta8rHiRDpJNEXzSj0tSdl.ZTiN4rn1WrljLpMYB.4EsXxt.eZLJt6', 1, '1967-09-02', '2015-01-01', '2028-02-17', NULL, NULL, NULL, '2028-10-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(15, 'Eddy', 'Veltkamp', '1356', 'jeveltkamp@kpnmail.nl', '06 43564850', '2030-06-19', '$2y$10$Jnl/xDIpTuvyqcL8UiRnLuwQ/VtIDB0E8UEhdyt4FUlSuSyhVY.7y', 1, '1956-07-13', '2024-06-01', NULL, '5848978318', '121363338', '2029-10-24', '2035-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', '8744346807'),
(16, 'Herman ', 'Bobbink', '5500', 'h.bobbink@upcmail.nl', NULL, '2026-08-15', '$2y$10$AtIbdER7zgaPvQzrl.KOL.hJ5aMqPNogzPfPxPWfl8R27bX.hPf.m', 1, '1967-05-12', '2018-09-01', NULL, '5384215348', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(17, 'Rutger', 'Smits', NULL, 'rutgersmits90@gmail.com', '06 40839992', NULL, '$2y$10$9Ta..NO7CwutxszxZGNjyeQtlQmFyRSVbcx8t/Krf7IldOx2mfdj2', 1, '1990-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(18, 'Jasper ', 'Emmers', NULL, 'emmers.chauffeursdiensten@gmail.com', NULL, NULL, '$2y$10$ymmDS9OxY3ekxGAEGwE/m.CBDZd0VciFqhzFl88xeFh0No/YImPsW', 1, NULL, '2026-03-02', NULL, NULL, NULL, NULL, '2027-04-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(19, 'Edwin', 'Verhaagen', NULL, 'erverhaagen@gmail.com', '06 18477022', NULL, '$2y$10$/fPfOHNSVCXZ0tVdBBgKw.Mfw15NbBInFp4Shcgvg//.WKNSLnnGi', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2027-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(20, 'Niek', 'Berkhout', '1111', 'niekberkhout@live.nl', NULL, NULL, '$2y$10$4p2e7DEbwZZmVtRAyij5O.p9yn3Zn4U2oQeRTOEqZHp8hPkqPWgR.', 1, '1966-01-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL),
(21, 'Patrick', 'Velthuis', '1175', 'Patrick.velthuis@gmail.com', '0622961683', '2034-06-04', '$2y$10$8RE6trkj8DscZIk7pm5es.ia9lK83woPWYOeWkR52R54KzZ/5VwCO', 1, '1975-11-01', '2024-05-01', NULL, '5139060254', '5729175224', '2029-03-22', '2029-06-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', '8705557962'),
(22, 'Marius', 'Mennink', NULL, 'mennink@kpnplanet.nl', NULL, NULL, '$2y$10$KXC9VCDmkwpe4JqqkMKco.K74Vje2Fcev/vsgrIkbJgl/rQmztlsq', 1, '1957-07-13', NULL, NULL, NULL, NULL, NULL, '2028-01-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'nee', 0, 0.00, NULL, 'chauffeur', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `diensten`
--

CREATE TABLE `diensten` (
  `id` int(11) NOT NULL,
  `naam` varchar(100) DEFAULT NULL,
  `geplande_datum` date DEFAULT NULL,
  `chauffeur_id` int(11) DEFAULT NULL,
  `voertuig_id` int(11) DEFAULT NULL,
  `start_tijd` datetime DEFAULT NULL,
  `eind_tijd` datetime DEFAULT NULL,
  `km_start` int(11) DEFAULT NULL,
  `km_eind` int(11) DEFAULT NULL,
  `status` enum('actief','afgerond','gecontroleerd') NOT NULL DEFAULT 'actief',
  `notities` text DEFAULT NULL,
  `datum_aangemaakt` timestamp NOT NULL DEFAULT current_timestamp(),
  `kas_afgedragen` decimal(10,2) DEFAULT NULL,
  `kas_verschil` decimal(10,2) DEFAULT NULL,
  `kas_status` enum('Te tellen','Akkoord') NOT NULL DEFAULT 'Te tellen',
  `kas_notitie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `diensten`
--

INSERT INTO `diensten` (`id`, `naam`, `geplande_datum`, `chauffeur_id`, `voertuig_id`, `start_tijd`, `eind_tijd`, `km_start`, `km_eind`, `status`, `notities`, `datum_aangemaakt`, `kas_afgedragen`, `kas_verschil`, `kas_status`, `kas_notitie`) VALUES
(1, NULL, NULL, 1, NULL, '2026-03-24 11:49:40', '2026-03-24 14:38:21', NULL, 50000, 'gecontroleerd', '', '2026-03-24 11:49:40', NULL, NULL, 'Te tellen', NULL),
(2, NULL, NULL, 3, NULL, '2026-03-24 13:55:05', '2026-03-24 16:04:50', NULL, 107744, 'gecontroleerd', '', '2026-03-24 13:55:05', NULL, NULL, 'Te tellen', NULL),
(3, NULL, NULL, 1, NULL, '2026-03-24 15:22:59', '2026-03-25 07:03:10', NULL, 246513, 'gecontroleerd', '', '2026-03-24 15:22:59', NULL, NULL, 'Te tellen', NULL),
(4, NULL, NULL, 7, NULL, '2026-03-24 17:02:11', '2026-03-30 13:57:30', NULL, 22222222, 'gecontroleerd', '', '2026-03-24 17:02:11', NULL, NULL, 'Te tellen', NULL),
(5, NULL, NULL, 1, NULL, '2026-03-25 07:03:13', '2026-03-25 16:54:05', NULL, 494089, 'gecontroleerd', '', '2026-03-25 07:03:13', NULL, NULL, 'Te tellen', NULL),
(6, NULL, NULL, 3, NULL, '2026-03-25 07:54:12', '2026-03-25 15:32:08', NULL, 107841, 'gecontroleerd', 'Telefoonhouders zijn afgebroken.', '2026-03-25 07:54:12', NULL, NULL, 'Te tellen', NULL),
(7, NULL, NULL, 3, NULL, '2026-03-26 07:15:42', '2026-03-26 15:34:40', NULL, 107943, 'gecontroleerd', '', '2026-03-26 07:15:42', NULL, NULL, 'Te tellen', NULL),
(8, NULL, NULL, 1, NULL, '2026-03-26 08:13:43', '2026-03-26 22:53:33', NULL, 50000, 'gecontroleerd', '', '2026-03-26 08:13:43', NULL, NULL, 'Te tellen', NULL),
(9, NULL, NULL, 1, NULL, '2026-03-26 22:53:37', '2026-03-28 11:18:56', NULL, 50000, 'gecontroleerd', '', '2026-03-26 22:53:37', NULL, NULL, 'Te tellen', NULL),
(10, NULL, NULL, 3, NULL, '2026-03-27 07:30:54', '2026-03-27 16:15:40', NULL, 246879, 'afgerond', '', '2026-03-27 07:30:54', NULL, NULL, 'Te tellen', NULL),
(11, NULL, NULL, 1, NULL, '2026-03-28 11:19:17', '2026-03-29 13:14:17', NULL, 50000, 'afgerond', '', '2026-03-28 11:19:17', NULL, NULL, 'Te tellen', NULL),
(12, NULL, NULL, 7, NULL, '2026-03-30 13:57:37', '2026-03-30 14:03:00', NULL, 5555, 'gecontroleerd', '', '2026-03-30 13:57:37', NULL, NULL, 'Te tellen', NULL),
(13, NULL, NULL, 15, NULL, '2026-03-30 14:55:52', '2026-03-30 15:08:36', NULL, 5, 'gecontroleerd', '', '2026-03-30 14:55:52', NULL, NULL, 'Te tellen', NULL),
(14, NULL, NULL, 15, NULL, '2026-03-30 15:08:48', '2026-03-30 15:09:35', NULL, 222, 'gecontroleerd', '', '2026-03-30 15:08:48', NULL, NULL, 'Te tellen', NULL),
(15, NULL, NULL, 15, NULL, '2026-03-30 15:10:48', '2026-03-30 17:17:50', NULL, 1, 'gecontroleerd', '', '2026-03-30 15:10:48', NULL, NULL, 'Te tellen', NULL),
(16, NULL, NULL, 15, NULL, '2026-03-30 17:18:01', '2026-04-02 19:44:02', NULL, NULL, 'gecontroleerd', '', '2026-03-30 17:18:01', NULL, NULL, 'Te tellen', NULL),
(17, NULL, NULL, 15, NULL, '2026-04-02 19:44:15', NULL, NULL, NULL, 'actief', NULL, '2026-04-02 19:44:15', NULL, NULL, 'Te tellen', NULL),
(18, NULL, NULL, 21, NULL, '2026-04-04 10:53:34', '2026-04-04 10:54:13', NULL, NULL, 'gecontroleerd', '', '2026-04-04 10:53:34', NULL, NULL, 'Te tellen', NULL),
(19, NULL, NULL, 1, NULL, '2026-04-04 13:29:22', '2026-04-07 07:19:27', NULL, NULL, 'gecontroleerd', '', '2026-04-04 13:29:22', NULL, NULL, 'Te tellen', NULL),
(20, NULL, NULL, 1, NULL, '2026-04-07 07:19:31', '2026-04-07 21:20:45', NULL, NULL, 'gecontroleerd', '', '2026-04-07 07:19:31', NULL, NULL, 'Te tellen', NULL),
(23, 'Radeland 1', '2026-04-13', 1, NULL, NULL, NULL, NULL, NULL, 'gecontroleerd', NULL, '2026-04-10 21:53:49', NULL, NULL, 'Te tellen', NULL),
(29, 'Radeland 1', '2026-04-14', NULL, NULL, NULL, NULL, NULL, NULL, 'gecontroleerd', NULL, '2026-04-10 22:34:25', NULL, NULL, 'Te tellen', NULL),
(30, 'Radeland 1', '2026-04-15', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-04-10 22:34:25', NULL, NULL, 'Te tellen', NULL),
(31, 'Radeland 1', '2026-04-16', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-04-10 22:34:25', NULL, NULL, 'Te tellen', NULL),
(32, 'Radeland 1', '2026-04-17', NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '2026-04-10 22:34:25', NULL, NULL, 'Te tellen', NULL),
(33, NULL, NULL, 1, NULL, '2026-04-11 00:23:24', '2026-04-11 13:58:51', NULL, NULL, 'gecontroleerd', '', '2026-04-11 00:23:24', NULL, NULL, 'Te tellen', NULL),
(34, NULL, NULL, 1, NULL, '2026-04-11 13:58:56', '2026-04-15 12:19:12', NULL, NULL, 'gecontroleerd', '', '2026-04-11 13:58:56', NULL, NULL, 'Te tellen', NULL),
(35, 'Losse Rit', '2026-04-12', 1, NULL, '2026-04-12 11:45:00', '2026-04-12 11:45:00', NULL, NULL, 'gecontroleerd', NULL, '2026-04-15 14:21:50', NULL, NULL, 'Te tellen', NULL),
(36, NULL, NULL, 1, NULL, '2026-04-16 08:02:10', '2026-04-16 14:57:30', NULL, NULL, 'afgerond', '', '2026-04-16 08:02:10', NULL, NULL, 'Te tellen', NULL),
(37, 'Losse Rit', '2026-03-02', 1, NULL, '2026-03-02 08:00:00', '2026-03-02 08:00:00', NULL, NULL, 'gecontroleerd', NULL, '2026-04-16 10:32:50', NULL, NULL, 'Te tellen', NULL),
(38, NULL, NULL, 1, NULL, '2026-04-17 23:46:35', '2026-04-17 23:49:51', NULL, NULL, 'gecontroleerd', '', '2026-04-17 23:46:35', NULL, NULL, 'Te tellen', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `dienst_pauzes`
--

CREATE TABLE `dienst_pauzes` (
  `id` int(11) NOT NULL,
  `dienst_id` int(11) NOT NULL,
  `start_pauze` datetime NOT NULL,
  `eind_pauze` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `dienst_pauzes`
--

INSERT INTO `dienst_pauzes` (`id`, `dienst_id`, `start_pauze`, `eind_pauze`) VALUES
(1, 1, '2026-03-24 11:53:47', '2026-03-24 11:56:41'),
(2, 2, '2026-03-24 15:15:37', '2026-03-24 15:15:42'),
(3, 3, '2026-03-24 15:23:05', '2026-03-24 15:23:09'),
(4, 5, '2026-03-25 10:04:08', '2026-03-25 13:54:15'),
(5, 6, '2026-03-25 13:02:43', '2026-03-25 13:47:14'),
(6, 7, '2026-03-26 13:11:45', '2026-03-26 14:10:46'),
(7, 10, '2026-03-27 13:07:52', '2026-03-27 14:04:43'),
(8, 13, '2026-03-30 15:08:18', '2026-03-30 15:08:25'),
(9, 17, '2026-04-06 13:23:56', '2026-04-06 13:24:09'),
(10, 19, '2026-04-07 07:19:05', '2026-04-07 07:19:27'),
(11, 38, '2026-04-17 23:46:36', '2026-04-17 23:46:37');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `titel` varchar(255) NOT NULL,
  `datum` datetime NOT NULL,
  `locatie` varchar(255) NOT NULL,
  `prijs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_tickets` int(11) NOT NULL DEFAULT 100,
  `verkocht` int(11) NOT NULL DEFAULT 0,
  `status` enum('actief','uitverkocht','voorbij') DEFAULT 'actief'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `events`
--

INSERT INTO `events` (`id`, `titel`, `datum`, `locatie`, `prijs`, `max_tickets`, `verkocht`, `status`) VALUES
(1, 'Zomerfeest 2026', '2026-07-20 20:00:00', 'De Zaak', 15.00, 250, 0, 'actief');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klanten`
--

CREATE TABLE `klanten` (
  `id` int(11) NOT NULL,
  `klantnummer` varchar(50) DEFAULT NULL,
  `bedrijfsnaam` varchar(100) DEFAULT NULL,
  `voornaam` varchar(50) DEFAULT NULL,
  `achternaam` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefoon` varchar(20) DEFAULT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `plaats` varchar(100) DEFAULT NULL,
  `notities` text DEFAULT NULL,
  `aangemaakt_op` datetime DEFAULT current_timestamp(),
  `gearchiveerd` tinyint(1) NOT NULL DEFAULT 0,
  `diesel_mail_gehad` tinyint(1) NOT NULL DEFAULT 0,
  `is_gecontroleerd` tinyint(1) DEFAULT 0,
  `email_factuur` varchar(255) DEFAULT NULL,
  `naam_factuur` varchar(255) DEFAULT NULL,
  `mobiel` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klanten`
--

INSERT INTO `klanten` (`id`, `klantnummer`, `bedrijfsnaam`, `voornaam`, `achternaam`, `email`, `telefoon`, `adres`, `postcode`, `plaats`, `notities`, `aangemaakt_op`, `gearchiveerd`, `diesel_mail_gehad`, `is_gecontroleerd`, `email_factuur`, `naam_factuur`, `mobiel`) VALUES
(1, NULL, 'Taxi Berkhout', 'Fred', 'Stravers', 'info@taxiberkhout.nl', '0641255791', 'Industrieweg 95A', '7202 CA', 'Zutphen', '', '2026-02-07 14:51:07', 1, 0, 0, NULL, NULL, NULL),
(8, '', '', 'Fred ', 'Stravers', 'info@berkhoutreizen.nl', '', 'Industrieweg 95a', '7202 CA', 'Zutphen', '', '2026-03-11 16:10:08', 0, 1, 0, '', '', NULL),
(9, '3.0', 'Zonnehuizen', 't.a.v. Afd. crediteurenadministratie', '', '', NULL, 'Postbus 99', '3700 AB', 'Zeist', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(10, '5.0', 'Mw. Lammertink', '', '', '', NULL, 'Schoolstraat 14', '7205 BP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(12, '7.0', 'Sociale Zaken', 'Dhr. P. de Vries', '', '', NULL, 'Postbus 41', '7200 AA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(13, '8.0', 'National Academic Zorgservice', '', '', '', NULL, 'Postbus 2500', '6401 DA', 'Heerlen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(14, '9.0', 'Mediq BV', 'Apotheek', '', '', NULL, 'Postbus 2429', '3500 GK', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(15, '10.0', 'Schotpoort Traffic Center', 'Afd. Crediteurenadministratie', '', 'administratie@schotpoort.nl', NULL, 'Postbus 82', '6960 AB', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(16, '11.0', 'Regiomail', 'Arjan Vaanholt', '', '', NULL, 'Skagerrakstraat 5', '7202 BZ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(17, '12', 'Bakkerij Verbeek', 'Joris', 'Steinhoff', 'jsteinhoff@biobakkerijverbeek.nl', '0575563599', 'Saturnusweg 1', '6971 GX', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(18, '13.0', 'Mvr. Knook', 'Appartement 9', '', '', NULL, 'Spoorstraat 1', '6971 CA', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(19, '14.0', 'Visscher Holland', 'Afd. Crediteurenadministratie', '', '', NULL, 'Hermesweg 20', '7202 BR', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(21, '16.0', 'Van den Berg Touringcars BV', 'Dhr. H. Visscher', '', 'leonorekeijzer@gmail.com', NULL, 'van Heeckerenweg 9', '7471 SH', 'Goor', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(22, '17.0', 'Rechtbank Zutphen', 'T.a.v. FEZ  afd. Bestuursrecht', '', '', NULL, 'Postbus 9008', '7200 GJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(23, '18', 'GGnet - DaAr', 'Dhr. K. ', 'Wagenaar', 'oo@a.nl', '', 'De Ruyterstraat 4', '7311 HS', 'Apeldoorn', 'Geen emailadres bekend.', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(24, '19.0', 'Terlet NV', '', '', 'invoice@terlet.com', NULL, 'Oostzeestraat 6', '7202 CM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(25, '20.0', 'PCO Assurantie Advies', 'Dhr. J. Weijman', '', '', NULL, 'Postbus 4019', '7200 BA', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(26, '21', 'Addink', 'C. ', 'Addink', 'carel@addink.nl', '', 'Doggersbank 2', '7202 CT', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(28, '23.0', 'SaxionNext', 'Dhr. F. Visscher', '', '', NULL, 'Postbus 2119', '7420 AC', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(29, '24.0', 'Heuting & Zn', '', '', '', NULL, 'Mercuriusweg 26', '6971 GV', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(30, '25.0', 'Pro Rail', 't.a.v. Tom  Veenendaal', '', '', NULL, 'Postbus 2212', '3500 GE', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(31, '26.0', 'Landgoed het Haveke', '', '', '', NULL, 'Zutphenseweg 113', '7211 EC', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(32, '27', 'Cinemajestic', 'Ron', 'Gerrits', 'rfgerrits@cinemajestic.nl', '0619614551', 'Dreef 8', '7202 AG', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(34, '29.0', 'Stichting Anderz', 'Inzake R. Kalthof', '', '', NULL, 'Zutphenseweg 106', '7241 SE', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(35, '30.0', 'Van den Beld Personenvervoer', 'afd. Administratie', '', 'facturen@vandenbeld.nl', NULL, 'Molenweg 2-1', '8181 BJ', 'Heerde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(36, '31.0', 'Ohra BV', '', '', '', NULL, 'Postbus 4172', '5004 JD', 'Tilburg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(37, '32.0', 'Stadsbrouwerij Cambrinus Zutphen', '', '', '', NULL, 'Houtmarkt 56b', '7201 KM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(38, '33.0', 'Woonzorgcentrum de polbeek', '', '', '', NULL, 'van Dorenborchstraat 1', '7203 CA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(40, '35.0', 'VOF Molkenboer / Koldeweij', '', '', '', NULL, 'Industrieweg 99', '7202 CA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(42, '37.0', 'Van der Zande Export B.V.', 't.a.v. Bastiaan Bakker', '', '', NULL, 'Rigastraat 24', '7418 EW', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(43, '38.0', 'V.V. Warnsveldse Boys', '', '', 'voorzitter@warnsveldseboys.nl', NULL, 'Veldesebosweg 26', '7231 DW', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(45, '40.0', 'Mw. E. Rietdijk', '', '', '', NULL, 'Rietbergstraat 87', '7201 GD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(48, '43.0', 'Verkeersschool Voskamp', 'Administratie', '', 'info@Verkeersschoolvoskamp.nl', NULL, 'Lage Weide 11', '7231 NN', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(49, '44.0', 'SSC DJI', 'o.v.v. 206 PI Achterhoek', '', 'facturen@dji.minjus.nl', NULL, 'Postbus 90832', '2509 LV', 'Den Haag', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(50, '45.0', 'Restaurant \'t Schultenhues', '', '', 'info@schultenhues.nl', NULL, '\'s Gravenhof 5-7', '7201 DN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(51, '46.0', 'Landgoed Klein Engelenburg', '', '', '', NULL, 'Spoorstraat 1', '6971 CA', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(52, '47.0', 'Mw. Runia', '', '', '', NULL, '\'t Nutteler 28', '7231 NR', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(56, '52.0', 'Pluryn', 'afd. Gezinswonen Stedendriehoek', '', 'crediteuren@pluryn.nl', NULL, 'Postbus 29', '6500 AA', 'Nijmegen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(58, '54.0', 'SOS international', '', '', 'salvagefacturen@sosinternational.nl', NULL, 'Postbus 12122', '1100 AC', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(62, '58.0', 'Mw. J. Hofman - Diks', '', '', '', NULL, 'Kruizemuntstraat 12', '7383 XM', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(63, '59.0', 'Steffex B.V.', 'T.a.v. D. Guldenaar', '', '', NULL, 'Postbus 360', '7200 AJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(64, '60', 'Bumaga BV', 'Sanne ', 'Tiekstra', 's.tiekstra@bumaga.nl', '', 'IJsselburcht 3', '6825 BS', 'Arnhem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(65, '61.0', 'Huis de Voorst', 'afd. Administratie', '', 'info@huisdevoorst.nl', NULL, 'Binnenweg 10', '7211 MA', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(67, '63.0', 'Mevrouw H. Boer', '', '', '', NULL, 'Prins Bernardlaan 20', '7204 AM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(70, '70.0', 'Willems en de Koning', 'Crediteurenadministratie', '', '', NULL, 'Postbus 5448', '6802 EK', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(71, '71', 'Arriva Personenvervoer B.V.', 'Arriva ', 'Achterhoek', 'digitaal.factuur@arriva.nl', '', 'Postbus 626', '8440 AP', 'Heerenveen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(72, '72.0', 'Munckhof', 'Crediteurenadministratie', '', 'busregie@munckhof.nl', NULL, 'Handelsstraat 15', '5961 PV', 'Horst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(77, '77.0', 'Thermen Bussloo', 'Crediteuren administratie', '', 'info@thermenbussloo.nl', NULL, 'Bloemenksweg 38', '7383 RN', 'Bussloo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(79, '79.0', 'Huize Welgelegen B.V.', '', '', '', NULL, 'Molenstraat 2', '7231 KN', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(80, '80.0', 'Stichting Krasnakin', 'T.a.v. dhr. Maurits van Uhm', '', 'maurits.van.uhm@planet.nl', NULL, 'Henri Dunantlaan 3', '7261 BW', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(81, '81.0', 'Trajectum', 'T.a.v. Fin. administratie', '', 'facturen@trajectum.info', NULL, 'Postbus 300', '7200 AH', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(82, '82.0', 'Tandtechnisch laboratrium Zutphen', 'Crediteurenadministratie', '', '', NULL, 'Deventerweg 113', '7203 AE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(83, '83.0', 'UniWarm', 'Dhr. P. Riethorst', '', 'info@uniwarm.nl', NULL, 'Nijverheidsweg 3', '7251 JV', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(84, '84.0', 'Taxi Weekenstroo', 'Financiele administratie', '', '', NULL, 'Nieuwstad 13', '7241 DM', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(85, '85', 'Gemeente Zutphen', 't.a.v.  ', 'Dhr. W. Jaeger', 'Burgemeester@zutphen.nl', '', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(89, '97.0', 'Supersnack', '', '', 'supersnack1@live.nl', NULL, 'Overwelving 7', '7201 LT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(90, '98', 'FC Zutphen', 'T.a.v. ', 'Penningmeester', 'penningmeester@fczutphen.nl', '0575524376', 'Meijerinkpad 1', '7207 ED', 'Zutphen', '', '2026-03-11 16:39:33', 0, 1, 1, '', 'T.a.v. Penningmeester Ivo Pelrgrim', NULL),
(94, '103', 'AZC', 'Gerton', 'Damen', 'penningmeester@azczutphen.nl', '', 'Fanny Blankers-Koenweg 8', '7203 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, 'penningmeester@azczutphen.nl', 'Fin. administratie', NULL),
(95, '104', 'G4S Security B.V.', 'T.a.v. afd. ', 'Crediteuren, KP 518', 'crediteuren@nl.G4S.com', '0204307500', 'Postbus 12630', '1100AP', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(96, '105.0', 'Nico Veer', '', '', 'nveer@heijmans.nl', NULL, 'Landpaal 3', '6852 GR', 'Huissen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(97, '106.0', 'Imagro', 'Afd. Crediteuren administratie', '', 'rieke@imagro.nl', NULL, 'Sint Janstraat 22', '6595 AC', 'Ottersum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(98, '107.0', 'Roger van London', '', '', 'roger_karlijn@hotmail.nl', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(99, '108.0', 'Taxi Klomp', 'Rob van IJperen', '', 'm.abbasy@klompgroep.nl', NULL, 'Postbus 179', '6716 AE', 'Ede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(100, '109.0', 'V.V.M. Consultants', 'T.a.v. Genie', '', 'nfo@vvmconsultants.nl', NULL, 'IJsselkade 61', '7201 HD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(101, '110', 'Bas Ballonvaarten', 'T.a.v. ', 'Bas Spierenburg', 'info@basballonvaart.nl', '', 'Verwoldseweg 26', '7245 VW', 'Laren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(102, '111.0', 'Wake me Coaching en Advies', 'T.a.v. W. Veltman', '', 'administratie@berkhoutreizen.nl', NULL, 'Postbus 75', '7255 ZH', 'Hengelo (Gld)', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(103, '112.0', 'Restaurant Galantijn', '', '', '', NULL, 'Stationstraat 9', '7201 MC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(104, '113.0', 'Mvr. v/d Veen', '', '', '', NULL, 'Zaadmarkt 104', '7201 DE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(105, '114', 'Gelre apotheek Zutphen', 'Dhr. ', 'Bruggeman', 'administratiegaz@gelre.nl', '0621138251', 'Den Elterweg 77', '7207 AE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(106, '115.0', 'Landgoed Avegoor', 'afd. financiële administratie', '', 'manager@avegoor.nl', NULL, 'Zutphensestraatweg 2', '6955 AG', 'Ellecom', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(107, '116.0', 'Klein Kranenburg', '', '', '', NULL, 'Molengracht 6b', '7201 LX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(108, '117.0', 'Oad Reizen', 'Jos Kunneman', '', 'j.kunneman@oad.nl', NULL, 'Udenseweg 45', '5411 SB', 'Zeeland', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(110, '119.0', 'Oosterberg Elekro', 'Mvr. Tara van dijk', '', 'Tara.van.Dijk@oosterberg.nl', NULL, 'Pollaan 4-6', '7202 BX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(111, '120.0', 'Mevr. E. Funke', 'Mvr. Habbekottee', '', 'Emmy@Funke.com', NULL, 'Weesperstraat 50', '1398 XZ', 'Muiden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(113, '122.0', 'Karwei Zutphen', '', '', 'Karweizutphen@outlook.com', NULL, 'Stoven 47', '7206 AZ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(114, '123.0', 'Kompaan College Zutphen', 'Dhr. F. Westerholt, Docent Voertuigtechniek', '', 'c.hendriks@hetstedelijkzutphen.nl', NULL, 'Wijnhofstraat 1', '7203 DV', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(115, '124.0', 'Villa de Luchte', 'T.a.v. Dhr. Zandstra', '', '', NULL, 'Zutphenseweg 91', '7241 KP', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(116, '125.0', 'Woonbedrijf Ieder1', 'Dhr. Axel Oude Veldhuis', '', 'inkoopfacturen@ieder1.nl', NULL, 'Postbus 888', '7400 AW', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(118, '127.0', 'Mevr. Mieras-Berkeveld', '', '', 'lberkeveld@hotmail.com', NULL, 'Maarten Boshof 2', '7207 PS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(119, '128.0', 'Natuurmonumenten', 'T.a.v. Mevr. J. v/d Berg', '', '', NULL, 'Postbus 9955', '1243 ZS', '´s Graveland', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(120, '129.0', 'Schippershuis Vastgoed B.V.', '', '', '', NULL, 'Zonnehorst 17', '7207 BT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(122, '131.0', 'Het Plein', 'Dhr. P. der Vries', '', 'E.Ouderdorp@zutphen.nl', NULL, 'Postbus 9010', '7200 GL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(123, '132.0', 'Stemmer Imaging B.V.', 'T.a.v. Evelien Snijders', '', 'E.Snijders@stemmer-imaging.nl', NULL, 'Zonnehorst 17', '7207 BT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(124, '133', 'Bronsbergen', 'Mark ', 'Schiphorst', 'fort@bronsbergen.nl', '', 'Bronsbergen 25', '7207 AD', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(125, '134.0', 'Sims', 'Dhr. v/d Sol', '', '', NULL, 'Lunettestraat 38', '7204 NL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(126, '135', 'Happy Media Partners', 'Hans ', 'Velders', 'h.velders@bruiloft.nl', '0575-848980', 'Henriette Polaklaan 35', '7207 HS', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(128, '137.0', 'Sitel', 'Dhr. Norbert Geuverink', '', 'norbert.geuverink@Sitel.com', NULL, 'Twentheplein 11', '7607 GZ', 'Almelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(130, '139.0', 'Straetus Incasso Ijsselstreek', 'Dhr. R. Sommer', '', 'IJsselstreek@straetus.nl', NULL, 'Marspoortstraat 15', '7201 JA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(131, '140.0', 'KCI World', 'Dhr. Elshof', '', 't.elshof@kci-world.com', NULL, 'Jacob Damsingel 17', '7201 AN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(132, '141', 'De Vrije School Noord en Oost Nederland', 'Henk ', 'Assinck', 'hassinck@vszutphen.nl', '', 'Tengnagelshoek 9-A', '7201 NE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(134, '143.0', 'Palsma- Hoogerwerf', 'Mw. Palsma', '', '', NULL, 'Diepenbrocklaan 28', '6815 AJ', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(135, '144.0', 'Mw. Haaren', '', '', '', NULL, 'Beethoven 282', '7204 RL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(136, '145.0', 'Nieuwsuur NOS', 'Crediteurenadministratie', '', 'production@nieuwsuur.nl', NULL, 'Postbus 29200', '1202 MP', 'Hilversum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(139, '148.0', 'Huis ter Weegen', '', '', 'G.Grijpma@domusmagnus.com', NULL, 'Rijkstraatweg 158', '7231 AK', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(140, '149.0', 'Taxi Goverde', '', '', 'S.Luijkx@taxigoverde.nl', NULL, 'Industrieweg 10a', '4762 AE', 'Zevenbergen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(141, '150.0', 'J.H. Wolsink', '', '', '', NULL, 'van Bourlostraat 6', '7203 CL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(142, '151.0', 'Taxicentral Ermelo B.V.', '', '', 'corindastrijkert@omvr.nl', NULL, 'Eendenparkweg 3', '3852 LC', 'Ermelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(143, '152.0', 'Mvr. J.V. Donderwinkel', '', '', '', NULL, 'Ing. Lelystraat 5', '7204 LJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(144, '153', 'Hema', 'Afd.', 'Administratie', 'fm-110@vab-hema.nl', '', 'Beukestraat 14', '7201 LE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(147, '156.0', 'PV Poolhoogte', '', '', 'maureen.groothedde@headlam.com', NULL, 'Bettinkhorst 4', '7207 BP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(149, '158.0', 'Volkshuis', 't.a.v. dhr. Coen Mijnen', '', 'coen@volkshuis.nl', NULL, 'Houtmarkt 62', '7201 KM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(152, '161', 'Buurtvereniging Dorskampkwartier', 'Hans ', 'Van Geel', 'j.geel802@upcmail.nl', 'j.geel802@upcmail.nl', '\'t Spiker 33', '7231 JL', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(154, '163', 'BDL-Grouptravel', 'Mw. B. Diender - Lohuis', 'Diender - Lohuis', 'betsy@bdl-grouptravel.nl', '06 4300 8189', 'Rossinistraat 101', '7442 GW', 'Nijverdal', '', '2026-03-11 16:39:33', 0, 0, 1, 'info@bdl-grouptravel.nl', '', NULL),
(155, '164.0', 'TK Architectuur & Bouwmanagement bv', 'Ir. A.W. Keijmel', '', '', NULL, 'Mensinksdijkje 6a', '7433 AN', 'Schalkhaar', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(157, '166.0', 'South West Tours B.V.', 'T.a.v. Ron Veenhuis', '', 'leonorekeijzer@gmail.com', NULL, 'De Singel 29', '7722 RR', 'Dalfsen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(159, '168.0', 'W.I.B.', 'T.a.v. Herjon Nieuwburg', '', 'penningmeester@wib.nu', NULL, 'Noorderhavenstraat 49', '7202 DD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(160, '169.0', 'Vogel Bewindvoering', '', '', 'mvogel@vogelbewind.nl', NULL, 'Postbus 3001', '7600 EA', 'Almelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(162, '171.0', 'Markolle Zozijn', '', '', '', NULL, 'Markolle 2', '7207 PA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(163, '172.0', 'Mw. v.d. Heide', '', '', '', NULL, 'Willem Dreesstraat 5', '7204 JP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(164, '173', 'Henriëtte Hartsenkliniek Zutphen', 'Dhr. P. ', 'van Dijk', 'facturen@tactus.nl', '', 'Piet Heinstraat 27', '7204 JN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(165, '174.0', 'Oolaboo', 't.a.v. Jeroen Wilmink', '', 'Jeroen@Oolaboo.com', NULL, 'Zonnehorst 19', '7207 AB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(166, '175.0', 'Obs de Parel', '', '', '', NULL, 'Het Zwanevlot 316', '7206 CS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(167, '176.0', 'OBS de Waaier', 'Mvr. Sascha de Vos', 'de Vos', 'saschadevos@archipelprimair.nl', '', 'Dr. De Visserstraat 4', '7204 KM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 1, 0, NULL, NULL, NULL),
(168, '177.0', 'Mw. T. van Duin', '', '', 'tanja.vanduin@live.nl', NULL, 'Dorskamp 45', '7231 JX', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(169, '178.0', 'Wormgoor Koudetechniek B.V.', 'Dhr. R. Wormgoor', '', '', NULL, 'Haaksbergerweg 1', '7471 LS', 'Goor', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(171, '180', 'Brand Oil Zutphen', 'T.a.v. Mvr. C. ', 'Voortman', 'chantal@brandoil.nl', '0575-517957', 'De Stoven 23', '7206 AZ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(173, '182', 'Combigro Helmink Foodservice', 'Mvr. Monique ', 'de Blieck', 'M.deBlieck@combigrohelmink.nl', '0575516461', 'Zonnehorst 4', '7207 BT', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(176, '185', 'Happy bus', 'Dhr. ', 'Leen Berkhoff', 'happybus.nl@gmail.com', '', 'Kalmoesstraat 205', '7322 NP', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(177, '186', 'Circulus - Berkel B.V', 'T.a.v. ', 'Mevr. Ciske Sangers', 'ciske.sangers@circulus-berkel.nl', '', 'Boggelderenk 3', '7207 BW', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(178, '187.0', 'Mekers Tours', '', '', 'leonorekeijzer@gmail.com', NULL, 'Zeddamseweg 39', '7075 ED', 'Etten', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(179, '188.0', 'Mw. Wolf', '', '', '', NULL, 'Hobbemakade 436', '7204 TG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(180, '189', 'Boode Bathmen', 'Liesbeth', '.', 'liesbeth@boode.nl', '', 'Brink 10', '7437 ZG', 'Bathmen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(183, '192.0', 'Jusitiele informatiedienst', 'T.a.v. Mvr. A. Klaster', '', 'directiesecretariaat@justid.nl', NULL, 'Egbert Gorterstraat 6', '7607 GB', 'Almelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(184, '193.0', 'Waterschap Rijn en IJssel', 'Dhr. Niels Corbijn', '', 'n.corbijn@wrij.nl', NULL, 'Liemersweg 2', '7006 GG', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(186, '195.0', 'Mw. Mohamud', '', '', '', NULL, 'Hungerstraat 13', '7415 ZS', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(187, '196.0', 'Vion Apeldoorn', 'Afd. Shared Service Center', '', '', NULL, 'Postbus 1', '5280 AA', 'Boxtel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(190, '199', 'Basisschool J.F. Kennedy', 'Dennis ', 'Dijkgraaf', 'd.dijkgraaf@skbg.nl', '0681702924', 'Leeuweriklaan 21', '7203 JD', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(191, '200', 'Gelderse Business Club', 'Mvr. Anita ', 'Klosters', 'info@geldersebusinessclub.nl', '', 'Piet Heinstraat 3', '7204 JN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(193, '202', 'GGnet Didam', 'T.a.v. ', 'Mvr. Marion Petersen', 'M.Petersen@ggnet.nl', '0889331130', 'Nieuwe Meursweg 14 t/m 90', '6942 RA', 'Didam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(194, '203.0', 'O.B.S. Hagewinde', 'Mvr. Cindy Broekhuis', '', 'cindybroekhuis@archipelprimair.nl', NULL, 'Kerkstraat 14', '7384 AS', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(195, '204.0', 'Partybussen', 'Dhr. Keest Buijtenhuis', '', 'administratie@partybussen.nl', NULL, 'Proveniersstraat 54b', '3033 CM', 'Rotterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(196, '205', 'NG Party\'s', 'Dhr. Harold ', 'Hulleman', 'ngpartys@outlook.com', '', 'Multatulistraat 14', '7204 DA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(197, '206.0', 'Women in Business', 'Jeanet de Vries', '', 'penningmeester@wib.nu', NULL, 'Noorderhavenstraat 49', '7202 DD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(198, '207.0', 'Vrije School de Berkel', 'Dhr. Frank van der Linden', '', '', NULL, 'Weerdslag 14 B', '7206 BR', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(199, '208', 'Basisschool De Kleine Wereld', 'Geeske', 'van Apseren', 'ordekleinewereld@gmail.com', '0571272284', 'Jachtlustplein 30 C', '7391 BW', 'Twello', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(200, '209.0', 'Justitiële Informatiedienst', '', '', 'w.hovinga@justid.nl', NULL, 'Postbus 337', '7600 AH', 'Almelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(203, '213', 'CBS De Wegwijzer', 'T.a.v. ', 'Mw. A. Mezzo', 'A.mezzo@gelderveste.nl', '0575-517712', 'Paulus Potterstraat 4', '7204 CV', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(204, '214.0', 'Mevr. A.H. Boer - Pellen', '', '', '', NULL, 'Badhuisweg21', '7201 GM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(206, '216.0', 'Syntus', 'Crediteurenadministratie', '', '', NULL, 'Postbus 297', '7400 AG', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(207, '218.0', 'Mw. J.W.M. Peterman - Berns', '', '', 'brandenbarg@gmx.com', NULL, 'Zegerijstraat 17', '6971 ZN', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(208, '219.0', 'Qlip B.V.', '', '', '', NULL, 'Postbus 292', '3830 AG', 'Leusden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(209, '220.0', 'Polysport Activiteitenpark & Catering', 't.a.v. Patrick Peters', '', 'info@polysport.nl', NULL, 'Adm. Helfrichlaan 89', '6952 GD', 'Dieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(210, '221.0', 'Praxis', 'T.a.v. Personeelsvereniging', '', 'pv-praxis2036@hotmail.com', NULL, 'Spankerseweg 50', '6951 CH', 'Dieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(211, '222.0', 'Isendoorn College', 'Karin ', 'Soeteman', 'soe@isendoorn.nl', '0575 760 760', 'Lage Weide 1', '7231 NN', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 1, 0, 'facturen@isendoorn.nl', NULL, NULL),
(212, '223', 'Gemeente Deventer', 't.a.v. ', 'Rene Sueters', 'renesueters@kpnmail.nl', '06-53972019', 'Postbus 5000', '7400 GC', 'Deventer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(213, '224.0', 'P.J. Casteren', '', '', '', NULL, 'Braamkamp 54', '7206 HC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(214, '225.0', 'Mw. Bakker', '', '', 'solex100@hotmail.com', NULL, 'De Waarden 383', '7206 GX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(216, '227.0', 'Hotel Hof van Gelre B.V.', 'Dhr. Arne Heitmeijer', '', 'a.heitmeijer@hofvangelre.nl', NULL, 'Nieuweweg 38', '7241 EW', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(217, '228.0', 'Vrouwen van nu Warnsveld', 'Anne Mieke imhoff', '', '', NULL, 'Caro van Eycklaan 56', '7207 GG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(218, '229.0', 'Stichting B.O.G.', 'Mw. A. Aartsen', '', 'a.aartsen@stbog.nl', NULL, 'Berkelsingel 30', '7201 BL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(219, '230', 'Carezu B.V.', 'Linda ', 'Wesselink', 'linda.wesselink@gelre.nl', '', 'Den Elterweg 77', '7207 AE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(220, '231.0', 'Mw. C. Omta', 'Manager', '', '', NULL, 'Piet Heinstraat 27', '7204 JN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(221, '232', '', 'Dhr. ', 'Willem Geerken', 'karinhainje@gmail.com', '', 'Vordensebinnenweg 9', '7231 BA', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(222, '234.0', 'Jan de Wit Group', 'afd. financiële administratie', '', '', NULL, 'Mollerusweg 1', '2031 BZ', 'Haarlem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(223, '235', '', 'Geerten ', 'Harink', 'geertenharink@hotmail.com', '', 'Leestenspad 112', '7232 AL', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(224, '236.0', 'Personenvervoer IJsselstreek B.V.', 'afd. financiële administratie', '', 'crediteuren@wdkgroep.nl', NULL, 'Postbus 5448', '6802 EK', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(226, '239.0', 'R. Geukes', '', '', 'rgflapper@gmail.com', NULL, 'Ien Dalessingel 271', '7207 LE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(227, '240.0', 'London Verzekeringen', 'T.a.v. A. Tossink', '', '', NULL, 'Postbus 60', '3000 AB', 'Rotterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(228, '241', 'AHorn Bouwsystemen B.V.', 'Rens ', 'van Velden', 'rens@ahorn-bouwsystemen.nl', '', 'Postbus 580', '7200 AN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(229, '242.0', 'NVM IJsselstreek / Oost- Gelderland', 'T.a.v. Mevr. Klosters', '', '', NULL, 'Roskampweg 11', '7213 AH', 'Gorssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(230, '243', 'Gemeente Bronckhorst', 'Sybrenne ', 'Hamer', 's.hamer@bronckhorst.nl', '', 'Elderinkweg 2', '7255 KA', 'Hengelo GLD', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(231, '244.0', 'VVV Zutphen', 'T.a.v. Mark Schuitemaker', '', 'Mark@inzutphen.nl', NULL, 'Houtmarkt 75', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(232, '245', 'Gamma Zutphen', 'T.a.v. Tiddo ', 'Potgeiser', 'mt.gamma.zutphen@filippo.nl', '0575512893', 'Pollaan 50F', '7202 BX', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(233, '246.0', 'Wijnhuistoren', 'afd. Crediteuren Administratie', '', '', NULL, 'Groenmarkt 40', '7201 HZ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(234, '247.0', 'IJsselmeeuwen', 't.a.v. dhr. Bruil', '', 'saabrio99@gmail.com', NULL, 'Deventerweg 197', '7203 AJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(235, '248', '', 'Buzio ', 'van Dijk', 'info@buzio.nl', ' 0643433235', 'Mozartstraat 44', '7204 PE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(236, '249', '', 'Edwin', 'Verhaagen', 'erverhaagen@gmail.com', '06-18477022', '\'t Spiker 43', '7231 JM', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(238, '251.0', 'Nederlandse Gasunie', 'Afd. Factuurafhandeling', '', '', NULL, 'Postbus 19', '9700 MA', 'Groningen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(239, '252', '', 'Gea', 'Marcus', 'gea@hansepans.nl', '0575530420', 'Weg naar Vierakker 18', '7204 LB', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(242, '255.0', 'Mw. van der Pol', '', '', 'ruthvanderpol@gmail.com', NULL, 'Rhienderinklaan 6', '7231 DC', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(243, '256.0', 'Jos van Heese', '', '', 'josvanheese@hotmail.com', NULL, 'Reinier Claeszenstraat 41-2', '1056 WE', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(244, '257.0', 'Unieke Uitjes .nl', '', '', '', NULL, 'Deventerweg 67C', '7245 PJ', 'Laren GLD', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(245, '258.0', 'Senefelder Misset', 't.a.v. Karin Hartwig', '', '', NULL, 'Postbus 68', '7000 AB', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(248, '261', 'Bedshop De Duif', 'Tristan', 'Wesselink', 'info@bedshop.nl', '0575512816', 'Postbus 249', '7200 AE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(249, '262.0', 'Landgoed Woodbrooke', '', '', '', NULL, 'Woodbrookersweg 1', '7244 RB', 'Barchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(252, '265.0', 'Vrouwen van nu Warnsveld', '', '', '', NULL, 'Caro van Eycklaan 56', '7207 GG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(254, '267.0', 'Sportclub Brummen', 'T.a.v. dhr. Kempes', '', 'gerrit.kempes@gmail.com', NULL, 'L.R. Beijenlaan 18', '6971 LE', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(255, '268', 'De Vrije School de Berkel', 'Elina', 'Brok - Schrijvers', 'e.schrijvers@vrijeschooldeberkel.nl', '0575 524011', 'Weerdslag 14b', '7206 BR', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(257, '270.0', 'Resa bewindvoering', 'T.a.v. Sharon Klomp', '', 'info@resa.nl', NULL, 'Postbus 766', '7400 AT', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(258, '271.0', 'Keurslager Vlogman', 'Dhr. D. Vlogman', '', 'info@vlogman.keurslager.nl', NULL, 'Zutphenseweg 16', '7251 DK', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(259, '272', 'Aventus', 'T.a.v. ', 'Financiële Administratie', 'facturen@aventus.nl', '', 'Laan van de Mensenrechten 500', '7331 VZ', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(260, '273.0', 'Nederlandse Gasunie N.V.', 'T.a.v. Dhr. O. Tromop', '', '', NULL, 'Postbus 162', '7400 AD', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(261, '274.0', 'Innovatiepartners', 't.a.v. dhr. R. Boon', '', '', NULL, 'Wezenland 470', '7415 JK', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(262, '275.0', 'Veluwonen', 'T.a.v. dhr. Koren', '', '', NULL, 'Stuijvenburchstraat 20', '6961 DR', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(264, '277.0', 'Het Mozaiek sbo', '', '', 'emmyvandevaart@archipelprimair.nl', NULL, 'Paulus Potterstraat 8', '7204 CV', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(265, '278.0', 'Mw. I. Coenen', '', '', '', NULL, 'Klooster 1', '7232 BA', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(266, '279.0', 'Loteringen UItvaartbegeleiding', 't.a.v. Lot Rohde', '', 'lot@loteringenuitvaart.nl', NULL, 'Albert Cuypstraat 30', '7204 BS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(267, '280.0', 'Lobke Bolijn', '', '', '', NULL, 'Grotenhuisweg 39a', '7384 CS', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(268, '281.0', 'Stichting WGDGO', 'T.a.v. Mevr. J. Golstein', '', 'jenneke.wgdgozutphen@gmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(269, '282.0', 'Mw. Groeneveld', '', '', '', NULL, 'Zutphensestraat 384', '6971 JS', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(271, '284.0', 'Hotel Bakker', 'T.a.v. mevr. L. Geubels - Bakker', '', 'liesbeth@bakker.nl', NULL, 'Dorpstraat 24', '7251 BB', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(272, '285', 'Gemeente Zutphen Team Maatschappelijke Zaken', 'T.a.v. ', 'dhr. J. van de Voorde', 'J.vandeVoorde@zutphen.nl', '', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(273, '286.0', 'Mw. Jansen Van Doorn', '', '', 'paulienrogier@hotmail.com', NULL, 'Havekerweg 10', '7211 DB', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(274, '287.0', 'Kersten Kunstofcoating B.V.', 'T.a.v. dhr. P. Brinkhorst', '', '', NULL, 'Vulcanusweg 2', '6974 GW', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(278, '291.0', 'IJsseldal Wonen', 'T.a.v. dhr. Sluizeman', '', 'B.Sluizeman@ijsseldalwonen.nl', NULL, 'Marktplein 110', '7390 AC', 'Twello', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(279, '292.0', 'Kraus Installatietechniek', 'T.a.v. dhr. A.A.D. Vriezekolk', '', '', NULL, 'Pollaan 46', '7202 BX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(280, '293.0', 'Kunstbus Bronckhorst', 'T.a.v. mevr. ten Duis', '', 'pg.tenduis@planet.nl', NULL, 'De Boonk 7', '7251 BS', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(283, '296', 'Brouwer Tour', '.', '.', 'facturen@brouwercompany.nl', '', 'Keyserswey 38-40', '2201 CW', 'Noordwijk', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(285, '298.0', 'Judoschool Pot', 'Dhr. Daniel Pot', '', 'judoschoolpot@gmail.com', NULL, 'Coehoornsingel 100', '7201 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(286, '299.0', 'Solidus-Solutions.com', 'T.a.v. dhr. Ron Schilder', '', 'Ron.Schilder@solidus-solutions.com', NULL, 'Hanzeweg 5', '7202 CG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(287, '300.0', 'Voortman Steel Group', 'afd. Crediteurenadministratie', '', '', NULL, 'Postbus 87', '7460 AB', 'Rijssen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(288, '301', 'H.H. Hulst', 'H.H.', 'Hulst', 'A@A.nl', '', 'Schurinklaan 9', '7211 DD', 'Eefde', 'Geen emailadres!', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(289, '302.0', 'Hotel Landgoed Ehzerwold', 'afd. Administratie', '', 'astrid@ehzerwold.nl', NULL, 'Ehzerallee 14', '7218 BS', 'Almen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(290, '303.0', 'Holtslag', '', '', '', NULL, 'Tuinstraat 8', '6971 BJ', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(291, '304', '', 'Hartman', '.', 'A@A.nl', '', 'Tichelkuilen 3', '7206 BA', 'Zutphen', 'Geen Emailadres!', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(292, '305.0', 'P. Breukink', '', '', '', NULL, 'Deventerweg 96', '7203 AN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(293, '306.0', 'Kamphuis Hoogwerkers bv', '', '', 'planning@hoogwerken.nl', NULL, 'Loohorst 7', '7207 BL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(295, '308', 'Buro MK', 'Murat ', 'Nergiz', 'murat@buromk.nl', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(296, '309.0', 'Stichting Zutphen Promotie', 't.a.v. Henriette van Noord', '', '', NULL, 'Houtmarkt 75', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(297, '310.0', 'Nieuwe Sociëteit', 't.a.v. Dhr. J. Veldhoen', '', '', NULL, 'Beukerstraat 13', '7201 LA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(298, '311', 'Dutchwood Production', 'Rico ', 'van Ginkel', 'r.vanginkel@dutchwoodproduction.nl', '0624170089', 'Tweedebroekdijk 2', '7122 LB', 'Aalten', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(299, '312.0', 'Stichting Vrienden van Lea Dasberg', 'T.a.v. Dhr. Erik Nengerman', '', 'info@erikevenementen.nl', NULL, 'Markolle 3', '7207 PA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(300, '313.0', 'Restaurant Vaticano', 'Dhr. Rafiek', '', 'info@vaticano.nl', NULL, 'Houtmarkt 79', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(301, '314.0', 'Ons Belang Hengelaarsver.', 'T.a.v. dhr. Wagener', '', 'G.wagener8@upcmail.nl', NULL, 'Breegrave 108', '7231 JJ', 'Warnveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(302, '315.0', 'Rabobank', 'T.a.v. Annamarije van Werven', '', 'Annamarije.van.Werven@rabobank.nl', NULL, 'De Dreef 6', '7202 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(304, '317.0', 'Olympia Uitzendbureau', 'Rob Wagenvoort', '', 'r.wagenvoort@olympia.nl', NULL, 'Waterkant 28', '7207 MX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(306, '319.0', 'Schmidtmedica', '', '', '', NULL, 'Piet Heinstraat 11', '7204 JN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(307, '320.0', 'VGGNet', 'Gebouw Laakveld', '', '', NULL, 'Vordenseweg 12', '7231 PA', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(308, '321', 'Gordijnenatelier Waagemans b.v.', 'Afd.', 'Administratie', 'info@waagemans.info', '', 'Brinkhorst 12', '7207 BG', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(309, '322.0', 'Mw F.I. Vroon - van Blijkshof', '', '', '', NULL, 'van Dorenborchstraat 139', '7203 CC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(310, '323.0', 'Microtek Medical', '', '', 'gmb-eu-microtekAP@medline.com', NULL, 'Hekkehorst 24', '7207 BN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(311, '324.0', 'Restaurant Vaticano', 'T.a.v. dhr. Rafiek', '', 'info@vaticano.nl', NULL, 'Houtmarkt 79', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(312, '325.0', 'Nederlandse Genootschap van Burgemeesters', '', '', 'r.vanbennekom@burgemeesters.nl', NULL, 'Postbus 30435', '2500 GK', 'Den Haag', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(313, '326', 'Fonville', 'T.a.v. ', 'Fonvilla Schoonhouden', 'crediteurenadministratie@fonville.nl', '0524-512053', 'Miggelenbergweg 65', '7351 BP', 'Hoenderloo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(315, '328.0', 'Reiscommissie Vrouwen Aktief', 'T.a.v. Wanda en Irma', '', 'w.konijnenberg@hotmail.com', NULL, 'Leusvelderweg 6 110', '7383 RC', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(316, '329.0', 'Louisa', '', '', '', NULL, 'Beethovenstraat 130', '7204 RG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(317, '330.0', 'Stichting Het Burgerweeshuis Zutphen', '', '', 'bwhzutphen@planet.nl', NULL, 'Noorderhavenstraat 49', '7202 DD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(318, '331', 'H. Pater', 'H', 'Pater', 'h.pater64@kpnmail.nl', '', 'Braamkamp 64', '7206 HC', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(319, '332.0', 'Mw. M. Palm', '', '', 'mpmiekepalm@gmail.com', NULL, 'Nieuweweg 11', '7231 AW', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(320, '333.0', 'Keijenburgse Boys', 'Dhr. Henk Jansen', '', 'h.jansen1@upcmail.nl', NULL, 'Pastoor Thuisstraat 21', '7256 AW', 'Keijenborg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(321, '334.0', 'P.A.A. Driever', '', '', 'verbinding@ingridrebel.nl', NULL, 'Deventerweg 36', '7203 AK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(322, '335.0', 'Mieke Palm', '', '', 'mpmiekepalm@gmail.com', NULL, 'Nieuweweg 11', '7231 AW', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(324, '337.0', 'Politie Landelijke Eenheid', 'T.a.v. Thamar Vlaanderen', '', 'thamar.vlaanderen@politie.nl', NULL, 'Hoofdstraat 54', '3972 LB', 'Driebergen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(327, '340.0', 'Mw. Maas', '', '', '', NULL, 'Emmerikseweg 26', '7204 SM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(328, '341.0', 'Onderwijs Zorg Centrum Zutphen', 'Marc Nagtegaal', '', 'info@ozc-zutphen.nl', NULL, 'Brandts Buijsstraat 6', '7203 AC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(329, '344.0', 'Nieuwe Sociëteit', 'T.a.v. Dhr J. Veldhoen', '', 'j.veldhoen@upcmail.nl', NULL, 'Beukerstraat 13', '7201 LA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(330, '345.0', 'Rotary Club \"Gorssel - Zutphen\"', 'T.a.v. Erik van Ekeris', '', 'ekeris@ansul.nl', NULL, 'Marsweg 1', '7213 LW', 'Gorssel', NULL, '2026-03-11 16:39:33', 1, 0, 0, NULL, NULL, NULL),
(331, '346.0', 'Rene Scholten', '', '', 'rene@scholtenzutphen.nl', NULL, 'Paul Rodenkolaan 116', '7207 CJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(332, '347.0', 'Schepers Tours BV', '', '', 'leonorekeijzer@gmail.com', NULL, 'Hoofdweg 10', '7676 AE', 'Westerhaar', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(333, '348', 'Hans Ruumpol Transport B.V.', 'Hans ', 'Ruumpol', 'hans@ruumpol.nl', '', 'Hazenberg 30', '6971 LC', 'Brummen', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(336, '351.0', 'Wil en Sietske Steigerwald', '', '', '', NULL, '\'t Spiker 69', '7231 JN', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(338, '353.0', 'Roekevisch', '', '', '', NULL, 'Nijkampsweg 7', '7241 SX', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(340, '355.0', 'Ver. oud Brandweerlieden Apeldoorn', 'T.a.v. Dhr. Henk de Boer', '', 'hwdeb@hotmail.com', NULL, 'Handelsstraat 167', '7311 CK', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(341, '356.0', 'OBS de Hagewinde', 'T.a.v. Juffrouw Marije Knol', '', 'Marijeknol@archipelprimair.nl', NULL, 'Kerkstraat 14', '7384 AS', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(342, '357.0', 'PV \'De Troep\'', 'T.a.v. Carolien van Heusden- Evers', '', 'c.vanheusden@biovitalis.eu', NULL, 'Hengelderweg 6', '7383 RG', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(344, '359.0', 'Klantenservice Taxiboeken', '', '', 'administratie@mobilityid.nl', NULL, 'Keizelbos 1', '1721 PJ', 'Broek op Langedijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(346, '361.0', 'Medema Bewindvoeringen', 't.a.v. Dhr. Medema', '', 'info@medemabewindvoeringen.nl', NULL, 'Postbus 1195', '3840 BD', 'Harderwijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(347, '362', 'Loterijclub Fortuna', 'T.a.v. ', 'Co Veenendaal', 'co.veenendaal@planet.nl', '0653154539', 'Rijksstraatweg 190', '7383 AP', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(348, '363.0', 'Krol Reizen', 't.a.v. Patrick Hol', '', 'krol@krolreizen.nl', NULL, 'Postbus 165', '4000 AD', 'Tiel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(349, '364.0', 'Mobilis B.V.', '', '', 'e.biharie@tbi-l2t.nl', NULL, 'Postbus 20175', '7302 HD', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(350, '365.0', 'Touringcar Hartemink', '', '', 'leonorekeijzer@gmail.com', NULL, 'Kiefteweg 6', '7151 HT', 'Eibergen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(352, '367.0', 'RIHO Climate Systems', 'T.a.v. dhr. Jurgen Riethorst', '', 'jurgen@rihoclimatesystems.nl', NULL, 'Nijverheidsweg 3', '7251 JV', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(353, '368.0', 'Vitalis Biologische Zaden B.V.', 'T.a.v. mvr. Regina van Mourik', '', 'c.vanheusden@biovitalis.eu', NULL, 'Hengelderweg 6', '7383 RG', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(354, '369.0', 'Mw. Bulten', '', '', '', NULL, 'Almenseweg 52', '7251 HS', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(355, '370.0', 'Mw. A. Goorman - de Vries', '', '', 'astridgoorman@hotmail.com', NULL, 'De Windvang 6', '7383 XW', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(356, '371.0', 'SL Lederwaren b.v.', '', '', 'peter@slbags.com', NULL, 'Zonnehorst 16', '7207 BT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(357, '372.0', 'Kok Installatietechniek', 'T.a.v. Dhr. Kok', '', 'info@kokinstalleert.nl', NULL, 'Parkelderweg 22', '7391 ET', 'Twello', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(358, '373.0', 'Payroll Select', 'T.a.v. Mvr. D. Zark - Everts', '', 'D.everts@payrollselect.nl', NULL, 'Hanzeweg 5', '7418  AW', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(359, '374.0', 'Mw. T Wendrich - Vredenberg', '', '', '', NULL, 'v. Dorenborchstraat 57', '7203 CB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(360, '375.0', 'Mw. Nab', '', '', '', NULL, 'Troelstralaan 61', '6971 CP', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(361, '376.0', 'IJsselzorg B.V', '', '', 'administratie@ijsselzorg.nl', NULL, 'Hermesweg 17', '7202 BR', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(362, '377.0', 'Stichting Vrienden PHB', 't.a.v. Mw. C. Mazel-Nauta', '', 'carolinemazel@hotmail.com', NULL, 'Almenseweg 60', '7251 HS', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(364, '379.0', 'SB Post', 'Deme Schotpoort', '', 'D.Schotpoort@sbpost.nl', NULL, 'Coldenhovenseweg 74', '6961 EG', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(365, '380', 'De Zonnebloem', 'T.a.v. ', 'Gerda Nagtegaal', 'gerna48@upcmail.nl', '', 'Schoolstraat 25', '7205 BM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(366, '381', 'Geers B.V.', 'T.a.v. ', 'Erwin', 'info@dezigno.nl', '', 'Kapelweg 22A', '7218 NJ', 'Almen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(367, '382', 'Gemeente Zutphen', 't.a.v. Team RED,', 'mevr. S. van Galen', 'carmen.hunger@woab.nl', '', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(368, '383.0', 'Politie Oost-Gelderland', 'T.a.v. M. Zijlstra', '', 'mieke.zijlstra@politie.nl', NULL, 'Postbus 618', '7300 AP', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(370, '385.0', 'Schippers Fluisterboot', 'T.a.v. Dhr. G. Wormgoor', '', 'g.wormgoor1@kpnplanet.nl', NULL, 'Braamkamp 237', '7206 HL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(374, '389.0', 'J. Overdijk', '', '', 'overd289@planet.nl', NULL, 'st. Laurensbaai 10', '2904 AL', 'Capelle a/d IJssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(375, '390.0', 'INCAA Computers BV', 'T.a.v. Dennis Vriezekolk', '', 'dennis.vriezekolk@incaacomputers.com', NULL, 'Puttenstein 20', '7339 BD', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(376, '391.0', 'Installatiebedrijf Eefting BV', 'T.a.v. Peter Smale', '', 'p.smale@eefting-epse.nl', NULL, 'Lochemseweg 26', '7214 RK', 'Epse', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(377, '392.0', 'Winkels Techniek BV', 'Crediteurenadministratie', '', 'crediteuren@winkelstechniek.nl', NULL, 'Postbus6179', '7503 GD', 'Enschede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(380, '395', 'De Achterban', 'T.a.v. Cor', 'Brandsma', 'info@corbrandsma.nl', '0263197740', 'Fie Carelsenstraat 10', '7207 GN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(381, '396.0', 'Rijnstate Ziekenhuis', '', '', 'hblansjaar@rijnstate.nl', NULL, 'Wagnerlaan 55', '6815 AD', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(383, '398', 'De Gorsselnarren', 't.a.v. ', 'Administratie', 'cvdegorsselnarren@gmail.com', '', 'Hankweg 1a', '7214 DJ', 'Epse', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(384, '399.0', 'Zutphen Promotie', 't.a.v. Dhr. M. Schuitemaker', '', 'mark@inzutphen.nl', NULL, 'Houtmarkt 75', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(385, '400.0', 'OVE Eerbeek', 't.a.v. José Wolfs', '', 'wolfs.bj@gmail.com', NULL, 'Brummenseweg 12', '6961 LR', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(386, '401.0', 'V&M BV', '', '', 'crediteuren@venm.nl', NULL, 'Postbus 20164', '7302 HD', 'Apeldroon', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(387, '402.0', 'Welzorg Autolease', '', '', 'onderhoud@welzorgautolease.nl', NULL, 'Rietveldenweg 51', '5222 AP', 'Den Bosch', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(388, '403.0', 'Nidos', 'afd. Administratie', '', 'administratie.tilburg@nidos.nl', NULL, 'Saal van Zwanenberg 7', '5026 RM', 'Tilburg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `klanten` (`id`, `klantnummer`, `bedrijfsnaam`, `voornaam`, `achternaam`, `email`, `telefoon`, `adres`, `postcode`, `plaats`, `notities`, `aangemaakt_op`, `gearchiveerd`, `diesel_mail_gehad`, `is_gecontroleerd`, `email_factuur`, `naam_factuur`, `mobiel`) VALUES
(389, '404.0', 'Mw. W. Linschooten', '', '', 'daakhaos@dds.nl', NULL, 'Tichelkuilen 143', '7206 BK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(390, '405.0', 'Metos B.V.', 'T.a.v. Wytse van den Toren', '', 'Wytse.van.den.toren@metos.nl', NULL, 'Birnieweg 2', '7411 HH', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(391, '406', 'Stichting Walburiskerk Zutphen', 't.a.v. Anja Kuiken', 'Anja Kuiken', 'info@walburgiskerk.nl', '', 'Kerkhof 3', '7201 DM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(392, '407', 'Circulus-Berkel B.V.', 'Chris ', 'van Vliet', 'chris.van.vliet@circulus-berkel.nl', '', 'Aalsvoort 100', '7241 MB', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(393, '408.0', 'Legamaster International BV', 'T.a.v. Activiteiten Commissie / Ingmar Fokke', '', 'ifokke@legamaster.com', NULL, 'Kwinkweerd 62', '7241 CW', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(394, '409.0', 'SV Keijenburgse Boys', 'T.a.v. de Penningmeester', '', 'h.jansen@stajawapening.nl', NULL, 'Pastoor Thuisstraat 21', '7256 AW', 'Keijenborg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(396, '411.0', 'Oosterberg', 't.a.v. Geert Bergsma', '', 'geert.bergsma@oosterberg.nl', NULL, 'IJsseldijk 8', '7325 WZ', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(397, '412', 'Bestelkantoor Arnhem', 't.a.v. ', 'Mw Reimerink', 'efacturen@uwv.nl', '', 'Kronenburgsingel 4', '6831 EX', 'Arnhem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(398, '413.0', 'Hoofdbestuur B.G.V.', 'T.a.v. Penningmeester M. Jongman', '', 'c.barendsen.sr@gmail.com', NULL, 'Molenweg 103', '7205 BC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(399, '415.0', 'PV de Polbeek', 't.a.v. J. Nijk', '', '', NULL, 'Spittaalstraat 96a', '7201 EG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(400, '416.0', 'Pay Systems BV', '', '', 'helenavanvulpen@gmail.com', NULL, 'Hanzeweg 26', '7241 CS', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(401, '417', ' ', 'Bram', 'Uiterweerd', 'uiterweerdb@gmail.com', '0683982561 ', 'Oude eerbeekseweg 19', '6971 BL', 'Eerbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(403, '419.0', 'VOF Hetebrij Personenvervoer', 'Crediteurenadministratie', '', 'facturen@hetebrijpersonenvervoer.nl', NULL, 'Ambachtsweg 1/A', '8131 TW', 'Wijhe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(404, '420.0', 'Mw Bosgoed', '', '', '', NULL, 'Oranjelaan 5', '7231 EW', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(406, '422.0', 'P. van Enk', '', '', '', NULL, 'Ien Dalessingel 137', '7207 LC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(407, '423.0', 'Taxi DZA', '', '', '', NULL, 'Tichelerstraat 13b', '7202 BC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(409, '425.0', 'Lazz Sport SL', '', '', 'Gerard@toernooivoetbal.nl', NULL, 'Salvador Olivella 17, Local 79C', '08870 Sitg', 'Barcelona', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(410, '426', 'Harlekijn Holland', 'T.a.v. Martine ', 'Tjie a Loi', 'martine@harlekijnholland.com', '', 'Landgoed de Paltz 1', '3768 MZ', 'Soest', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(411, '427.0', 'Mw. Jansen', '', '', '', NULL, 'Emmerikseweg 252 - 214', '7206 DE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(412, '428.0', 'Mw. Brummelman', '', '', '', NULL, 'De Steege 14', '7251 CN', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(413, '429.0', 'Mw. van Schaik', '', '', '', NULL, 'Geweldigershoek 39 Kamer 70', '7201 NC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(415, '431.0', 'Secretariaat Poli Cardiologie', 'Mw. Loppersum / Mw. Weekhout', '', '', NULL, 'Den Elterweg 77', '7207 AE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(416, '432.0', 'Mw. Bleijswijk', '', '', '', NULL, 'Lange Hofstraat 15a', '7201 HT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(419, '437.0', 'Mw. D. Cazier', '', '', 'xlucolllorien@gmail.com', NULL, 'St. Nicolaasstraat 64', '1012 NK', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(420, '438.0', 'v.v. Sportclub Eefde', '', '', 'j.peters@kdbarchitecten.nl', NULL, 'Meijkerinkstraat 5', '7200 AC', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(422, '440.0', 'Mw. Smallegoor', '', '', '', NULL, 'Hoetinkhof 269', '7251 WN', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(423, '441', 'Gemeente Zutphen', 'Team: Werken   ', 'Dhr. E. Nordkamp', 'E.Nordkamp@zutphen.nl', '', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(425, '443.0', 'Robin Franken', '', '', 'R.Franken@hotmail.nl', NULL, 'Klaproos 6', '7382 CH', 'Klarenbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(426, '444', 'Gemeente Zutphen', 'Beheer en Onderhoud', '(Dhr. E. Bruntink)', 'e.bruntink@zutphen.nl', '06 22229205', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(427, '445.0', 'Sutfene', 't.a.v. Dhr. P. Boxma Facilitair Regisseur i/o', '', 'pboxma@sutfene.nl', NULL, 'Postbus 283', '7200 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(429, '447.0', 'Lentelive Evenemeten comité', '', '', 'jasmijn@kickxl.nl', NULL, 'Parallelweg 5', '7391 JR', 'Twello', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(430, '448.0', 'Lotte Firet', '', '', 'lottefiret@hotmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(431, '449.0', 'Mw. I. Senden', '', '', '', NULL, 'Het Grasland 10', '6971 NB', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(432, '450.0', 'Stichting \'De Vrienden van Reurle\'', 'Anouk', '', 'anoukmeerbeek@reurpop.nl', NULL, 'Postbus 89', '7260 AB', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(433, '451.0', 'T. Platerink', '', '', 'thijsplaterink_2@hotmail.com', NULL, 'van Arkellaan 7', '7261 AJ', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(435, '453.0', 'ISL Footballtours', 'Mr. M. Ward', '', 'leonorekeijzer@gmail.com', NULL, '19 Harvey Street', 'BL18BH', 'Bolton', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(436, '454.0', 'Mw. Wennink', '', '', '', NULL, 'Amalia van Solmsplein', '7242 AC', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(438, '456.0', 'Vion Apeldoorn', 'afd. Shared Service Center', '', '', NULL, 'Postbus 1', '5880 AA', 'Boxtel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(439, '457.0', 'Vrouwen van nu', '', '', 'rikienorde@hotmail.com', NULL, 'Ruurloseweg 48', '7251 LM', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(440, '458', 'Fletcher Resort-Hotel Zutphen', 'Afd.', 'Administratie', 'info@hotelzutphen.nl', '', 'De Stoven 37', '7206 AZ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(441, '459.0', 'Siza', 'R. Schunemann/ kstpl: 4230', '', 'facturen@siza.nl', NULL, 'Postbus 532', '6800 AM', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(443, '461.0', 'TG via', 'Henri Tijhuis', '', 'h.tijhuis@tgvia.nl', NULL, 'Klavermaten 23', '7472DD', 'Goor', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(444, '462', 'Gemeente Zutphen tlv college', 'Mw. E. ', 'Dos Santes', 'factuurportal@zutphen.nl', '0515 140575', 'Postbus 41', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(446, '464', 'Broederenklooster', '.', '.', 'info@broederenklooster.nl', '0575 569995', 'Rozengracht 3', '7201 JL', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(447, '465', 'Brandweer Kazerne Gorssel', 'Gerben', 'Stoeten', 'g-stoeten@live.nl', '', 'Hoofdstraat 14A', '7213 CV', 'Gorssel', '', '2026-03-11 16:39:33', 0, 0, 1, 'BrandweerpostGorssel@vnog.nl', '', NULL),
(449, '467.0', 'Touringcarbedrijf Welgraven Travel', '', '', 'leonorekeijzer@gmail.com', NULL, 'De Eendrachtweg 57', '6974 AP', 'Leuvenheim', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(451, '469.0', 'Profez', 'A. Hultink', '', 'A.Hultink@profez.nl', NULL, 'Postbus 132', '7570 AC', 'Oldenzaal', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(453, '471', 'Buroned', '.', '.', 'info@buroned.nl', '', 'Postbus 4015', '7200 BA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(454, '472.0', 'Omroep Gelderland', '', '', 'factuur@gld.nl', NULL, 'Rosendaalseweg 704', '6824 KV', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(455, '473.0', 'Qarin BV', 'Unit 3.10', '', 'facturen@qarin.nl', NULL, 'Havenweg 4', '6603 AS', 'Wijchen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(456, '474.0', 'Stichting EAPN Nederland', '', '', 'jobothmer@hotmail.com', NULL, 'Postbus 92', '3940 AB', 'Doorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(457, '475', 'De Achtsprong', 'T.a.v. ', 'Femke', 'or@deachtsprong.skbg.nl', '', 'De Brink 126', '7206KD', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(458, '476', 'De Lunette', 'Yvonne', 'Cavadino', 'Yvonne.Cavadino@alliander.com', '0611299888', 'Coehoornsingel 3', '7201 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(459, '477.0', 'Van Egmond', 'Marlies Ruumpol', '', 'm.ruumpol@vanegmond.nl', NULL, 'Expeditieweg 4', '7007 CM', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(460, '485.0', 'Regie Bureau Midden', '', '', 'facturen-midden@monuta.nl', NULL, 'Veenhuizerweg 143', '7325 AK', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(461, '486.0', 'Karakter kinder- en jeugdpsychiatrie', 'T.a.v. Josephine Boots', '', 'managementondersteuninguc@karakter.com', NULL, 'Reinier Postlaan 12', '6525 GC', 'Nijmegen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(462, '487.0', 'Microtek BV', '', '', 'E.Collin.Franken@ecolab.com', NULL, 'Hekkehorst 24', '7207 BN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(463, '488', 'Bakkerij Jolink', 'Levi', 'Jolink', 'L.jolink@jolinkbanket.nl', '0683941669', 'Arnhemsestraat 4', '6971 AR', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(464, '489.0', 'THL Travel', 'Jade Boerdijk', '', 'j.boerdijk@uniglobethltravel.nl', NULL, 'Stadionweg 11', '1812 AZ', 'Alkmaar', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(465, '490', 'Brookhuis Busreizen', 'Rodney', 'Haarman', 'rodney.haarman@brookhuisgroep.nl', '088 460 70 50', 'Kelvinstraat 1b', '7575 AS', 'Oldenzaal', '', '2026-03-11 16:39:33', 0, 1, 1, 'facturenbusreizen@brookhuisgroep.nl', 'Afd. inkoop', NULL),
(466, '491', 'Bouw- en timmerfabriek De Haan', 'Dhr. R. ', 'Bossenbroek', 'info@dehaandeventer.nl', '0570-745073', 'Roermondstraat 11', '7418 CP', 'Deventer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(467, '492.0', 'Jeans Centre', 'T.a.v. afdeling finance', '', 'daisy.bouma@jeanscentre.nl', NULL, 'Van Hennaertweg 8', '2952 CA', 'Alblasserdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(468, '493.0', 'Stichting Bebon', '', '', 'k.pels@bebon.nl', NULL, 'Postbus 90', '7240 AB', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(469, '494.0', 'Melse & Wassink', '', '', 'r.krul@melsewassink.nl', NULL, 'Postbus 86', '6710 BB', 'Ede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(470, '495.0', 'Stichting Flexfeks', 't.a.v Gwenny Schraa', '', 'gschraa@flexfleks.nl', NULL, 'Lievenheersteeg 2b', '7202 CM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(471, '496.0', 'Jorn Slager', '', '', 'Jornslager@gmail.com', NULL, 'Eesveenseweg 161a', '8347 JJ', 'Eesveen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(473, '498.0', 'Mw. Post - Aalbers', '', '', 'y.post-aalbers@outlook.com', NULL, 'Praam 3', '8433 HA', 'Haulerwijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(475, '500.0', 'SAMM', 't.a.v. Laura Grijpsma', '', 'samm.commissies@gmail.com', NULL, 'M.H. Trompstraat 28', '7513 AB', 'Enschede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(476, '501.0', 'Holtslag Straal en Poedercoat Techniek B.V.', 't.a.v. Patrick Vriezekolk', '', 'info@holtslagpoedercoating.nl', NULL, 'Zweedsestraat 5', '7202 CK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(477, '502.0', 'JST Products International BV', '', '', 'jan@jstproducts.nl', NULL, 'Stellingmolenweg 16', '7383 XV', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(478, '503', 'Gelderesch Reizen B.V.', 'Roy ', 'te Brake', 'roy@gelderesch.nl', '', 'Industrieweg 13 a', '7141 DD', 'Groenlo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(479, '504.0', 'Mw. Comsa en Dhr. Ramos', '', '', 'info@otherwizeweddings.nl', NULL, 'Sparrendaal 143', '7544NP', 'Enschede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(480, '505.0', 'Otherwize Wedding', 'Marion van Zutphen', '', 'info@otherwizeweddings.nl', NULL, 'Lansinksweg 5', '7666 NH', 'Fleringen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(481, '506.0', 'SZP', '', '', 'mark@inzutphen.nl', NULL, 'Houtmarkt 75', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(482, '507.0', 'Meeuwis de Vries tuinen', 'Ellen Knol', '', 'ellen.knol@hetnet.nl', NULL, 'Soerense Zand Zuid 13', '6961 RA', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(483, '510.0', 'Sutfene', 'Sjoerd Luijer', '', 'facilitair@sutfene.nl', NULL, 'Postbus 283', '7200 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(484, '511', 'Gemeente Nunspeet', 'Marco ', 'Daudeij', 'm.daudeij@nunspeet.nl', '0341259470', 'Postbus 79', '8070 AB', 'Nunspeet', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(485, '512.0', 'Stichting De Passerel', 'Mw. Jiska Lugtenberg', '', '', NULL, 'Jean Monnetpark 4', '7336 BC', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(486, '513.0', 'Wil Jansen', '', '', '', NULL, 'Eganlantier 53', '6971 NA', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(487, '514.0', 'Philadelphia loc. Grasland', 'Crediteuren administratie', '', 'crediteuren@philadelphia.nl', NULL, 'Het Grasland 6', '6971 NB', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(489, '516', 'Compas Bewindvoering & Mentorschap', 'Afd.', 'Administratie', 'leander@compasbewind.nl', '', 'Hanzestraat 27', '7006RH', 'Doetinchem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(490, '517.0', 'SpIJs Warnsveld', 'Judith Ottema', '', 'judithottema@gmail.com', NULL, 'Dreiumme 39', '7232 CN', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(491, '518.0', 'Roy van Roemburg', '', '', 'roemieburg@hotmail.com', NULL, 'Emperweg 33a', '7399 AG', 'Empe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(492, '519.0', 'Het Rhedens', '', '', 'Pel@hetrhedens.nl', NULL, 'Doesburgsedijk 7', '6953 AK', 'Dieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(493, '520.0', 'Van Wijk', '', '', '', NULL, 'Peppelstraat 7', '7204 DK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(494, '521.0', 'Horus', '', '', 'info@d-vdv.nl', NULL, 'Postbus 405', '7400 AK', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(495, '522', 'De IJsselstroom', 'Jacqueline ', 'Amaddeo', 'verhuur@deijsselstroom.info', '0683551093', 'Vliegendijk 16', '7205 CJ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, 'facturen@deijsselstroom.info', '', NULL),
(496, '523', 'Biologische bakkerij ad van der Westen B.V.', 'Odin', '.', 'verkoop@zonnemaire.nl', '', 'Dwarsweg 12', '5165 NM', 'Waspik', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(497, '524.0', 'Philadelphia Radeland 10', 'Bas Geertsema', '', 'bas.geertsema@philadelphia.nl', NULL, 'Radeland 10', '6971 LV', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(498, '525.0', 'Suze Mosterd', 'Marlies Numan', '', 'marlies.numan@tempo-team.nl', NULL, 'Dr. Cartier van Disselweg 8', '7241 JP', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(499, '526', 'Coulisse B.V.', 'Afd.', 'Administratie', 'receptie@coulisse.com', '0547855555', 'Vonderweg 48', '7468DC', 'Enter', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(500, '527.0', 'Sport Science', 'Pascal Helmig', '', 'info@sports-science.nl', NULL, 'Hoeflingweg 20', '7241 CH', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(502, '529', 'Betuwe Express', 'Afd.', 'Administratie', 'admin.inkoop@betuwe-express.nl', '0488468686', 'Onderstalstraat 4', '6674 ME', 'Herveld', '', '2026-03-11 16:39:33', 0, 0, 1, 'admin.inkoop@betuwe-express.nl', '', NULL),
(503, '530', 'De Sportloods', 'Bert Ronk ', 'org. even. en uitjes SL', 'bertronk60@gmail.com', '0651608396', 'Zutphenseweg 53', '7211 EB', 'Eefde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(504, '531.0', 'Professionals in N.A.H.', 'Dhr. Jan Voortman', '', 'jan@nah.nl', NULL, 'Postbus 23', '7240 AA', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(506, '533.0', 'Onwise', 'Robin Buitink', '', 'robin@best4u-im.nl', NULL, 'Stationsplein 37a', '7201 MH', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(507, '534', 'By Chiel', 'Chiel', '.', 'info@bychiel.nl', 'info@bychiel.nl', '\'s Gravenhof 5', '7201 DN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(508, '535', 'BSO in het Wild', 'T.a.v. Mvr. Joyce ', 'Ruysink', 'joyceruysink@gmail.com', '', 'Hardsteestraat 14', '7227 NL', 'Toldijk', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(509, '536.0', 'Media Boekservice B.V.', '', '', 'katinka@mediaboek.nl', NULL, 'Coldenhovenseweg 100', '6961 EG', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(510, '537.0', 'Niels Bruggeman', '', '', 'niels.bruggeman@gmail.com', NULL, 'Papaverhof 19', '7211 DH', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(511, '538.0', 'Teleon Surgical B.v.', '', '', 'anita.debakker@teleon-surgical.com', NULL, 'Van Rensselaerweg 4b', '6956 AV', 'Spankeren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(512, '539', 'Dementienetwerk Zutphen', 'Afd.', 'Administratie', 'info@dementienetwerk.nl', '', 'Piet Heinstraat 11', '7204 JN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(514, '541.0', 'Jeugdberscherming', 'Jarka Zuijdervliet', '', 'j.zuijdervliet@jeugdbescherming.nl', NULL, 'Maassluisstraat', '1062 HB', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(515, '542.0', 'Monuta Uitvaartverzorging N.V.', '', '', 'BEndedijk@monuta.nl', NULL, 'Boeierweg 13', '8042PV', 'Zwolle', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(516, '543.0', 'Opslagbox Zutphen', '', '', 'aljan@opslagboxzutphen.nl', NULL, 'Industrieweg 65', '7202 CA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(517, '544.0', 'Philadelphia Trefhus Dagbesteding', 'Leny Brons', '', 'l.brons@philadelphia.nl', NULL, 'Radeland 2', '6971 LV', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(518, '545.0', 'Kangaro K.T.C. B.V.', 'Mvr. Lieke Pasman', '', '', NULL, 'Industrieweg 85', '7202CA', 'ZUTPHEN', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(519, '546.0', 'Internationaal Transportbedrijf van Opijnen B.V.', 'Dhr. Adrie van Opijnen', '', '', NULL, 'Bochumstraat 6', '7418EK', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(520, '547.0', 'Pactus bewindvoeder', '', '', 'factuur@pactus.nl', NULL, 'Postbus 18', '7000 AA', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(521, '548.0', 'Sensire Hackforterhof', '', '', '', NULL, 'de Delle 1', '7251 AJ', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(522, '549', 'Aan de Wiel Touringcar', 'Derk', 'aan de Wiel', 'derk@aandewiel.info', '0655895102', 'Van Rensselaerweg 9', '6956 AV', 'Spankeren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(523, '550', 'Graafschap College', 'José', 'Coppes', 'j.coppes@graafschapcollege.nl', '', 'J.F. Kennedylaan 49', '7001 EA', 'Doetinchem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(524, '551.0', 'Mw. L.M.Prins', '', '', 'mensink@mensinkenstout.nl', NULL, 'Burgermeester de Wijslaan 5', '6971 CC', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(526, '554', 'Uplift BV / Bookabus', 'Jego', 'Besseling', 'info@bookabus.nl', '0858883875', 'Willem de Zwijgerlaan 350, Unit 2R', '1055 RD', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(527, '555', 'Ensink Personenvervoer', 'Jan Willem ', 'Ensink', 'janwillem@ensinkpersonenvervoer.nl', '0575-564444', 'de Meente 27', '8121 EV', 'Olst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(528, '561.0', 'Lionsclub Zutphen', 'P/a Penningmeester Hans Jekel', '', 'jekel.jj@gmail.com', NULL, 'Wunderinklaan 16', '7211 AG', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(529, '562.0', 'Nationale Politie', 't.a.v. team Crediteuren', '', 'crediteuren@politie.nl', NULL, 'Postbus 33137', '3005 EC', 'Rotterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(531, '564.0', 'Vrouwen Reizen', 'Stefanie Schulz', '', 'Schulzvertaalservice@hotmail.com', NULL, 'Bronsbergen 25', '7207 AD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(532, '565.0', 'Villa 60', 't.n.v. Stichting Poolsterscholen', '', 'Poolster@obt.nl', NULL, 'Postbus 9', '7620 AA', 'Borne', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(533, '566.0', 'Izba Gospodarcza Gazwnictwa', 'Monika Sikorska', '', 'monika.sikorska@igg.pl', NULL, 'ul. Kasprzaka 25', '01-224', 'Warsaw', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(534, '569.0', 'Pouw vervoer B.V.', '', '', 'administratie@pouwvervoer.nl', NULL, 'Hagenweg 3c', '4131 LX', 'Vianen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(535, '570', 'De Onderwijsspecialisten', 't.a.v. ', 'Tanja van Manen', 'facturen@deonderwijsspecialisten.nl', '06 10634348', 'Postbus 821', '6800 AV', 'Arnhem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(537, '572.0', 'Tactus Piet Roorda Kliniek', 'T.a.v. Colette Omta Manager Bedrijfsvoering PRKZ', '', 'facturen@tactus.nl', NULL, 'Verlengde Ooyerhoekseweg 30', '7207 BJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(538, '573.0', 'Jellinek', 'T.a.v. Charrissa v. Sonsbeek', '', '', NULL, 'Noordse Bosje 43', '1211 BE', 'Hilversum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(539, '574', 'D&L Bewindvoering', 'Afd.', 'Administratie', 'info@denlbewindvoering.nl', '085-1300439', 'Postbus 252', '7400 AG', 'Deventer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(540, '575', 'Aksa Bewindvoering en Inkomensbeheer', 'Financiële', 'administratie', 'info@AksaBeheer.nl', '0575-214214', 'Postbus 4093', '7200 BB', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(542, '577', 'Gelre werkt', 'Esther', 'Hendriks', 'E.Hendriks@zutphen.nl', '0628038468', 'Postbus 1', '7200 GL', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(543, '578', 'Gemeente Apeldoorn', 'Suzanne ', 'Burgers', 's.burgers@apeldoorn.nl', '0555801735', 'Postbus 9033', '7300 ES', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(545, '580.0', 'Zozijn Bachstraat', 'Ria Wellenberg', '', 'r.wellenberg@zozijn.nl', NULL, 'Bachstraat 2', '7204 NT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(546, '581.0', 'Teenwork Trainingen', '', '', 'karenoosterink@teenwork.nl', NULL, 'p/a Brinckerinckstraat 9', '7412 DX', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(547, '582.0', 'Team voor Elkaar', '', '', 'w6@brummen.nl', NULL, 'Postbus 15', '6970 AA', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(548, '583', 'Aventus', 'T.a.v. ', 'Financiën', 'facturen@aventus.nl', '', 'Postbus 387', '7300 AJ', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(549, '584', 'Brandweer Eerbeek', 'Sander', 'de Vries', 'sander.de.vries@outlook.com', '0643976151', 'Smeestraat 4A', '6961 DH', 'Eerbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(550, '585.0', 'HR Budgetcoaching & Bewindvoering', 'J.A. Rouwenhorst', '', 'administratie@berkhoutreizen.nl', NULL, 'Postbus 89', '7240 AB', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(551, '586.0', 'Mw. Bongaards', '', '', '', NULL, 'Beukenlaan', '7223 KL', 'Baak', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(552, '587', 'Cityroos Football School', 'Ahmed ', 'Aydo?an', 'info@cityroos.com.au', '+31 6 11842288', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(553, '588.0', 'Stichting I.V.B', 'T.M.C. Nijmeijer', '', 'tmc.nijmeijer@stichtingivb.nl', NULL, 'Saturnusweg 4', '6971 GX', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(554, '589.0', 'Tournooi Voetbal', '', '', 'leonorekeijzer@gmail.com', NULL, 'het Stroo 10', '7251 VB', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(555, '590', 'St. Martinus Bussloo', 'Ilona Venema', 'administratie@stmartinus.skbg.nl', 'info@stmartinus.skbg.nl', '0571261510', 'Deventerweg 18', '7383 AB', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 0, '', NULL, NULL),
(556, '591.0', 'Wierdens Bewind', 'Els Snijder', '', 'wierdens.bewind@hetnet.nl', NULL, 'Postbus 205', '7640 AE', 'Wierden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(557, '592.0', 'Woonzorgcentrum Gudula', '', '', '', NULL, 'Oosterwal 18', '7241 AR', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(558, '593', '', 'Dhr. ', 'Haveman', 'mario.renate@gmail.com', '0653642841', 'Het Burkink 5', '7231 NM', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(559, '594.0', 'Mw. Meier', '', '', 'johannieuwenhuis@hotmail.com', NULL, 'Graaf van Limburg Stirumplein 23', '6971 CG', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(560, '595.0', 'Partou', '', '', 'facturen@partou.nl', NULL, 'van Dorenborchstraat 1A', '7203 CA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(561, '596', 'Dhr Glashouwer', 'Dhr. en Mw. ', 'Glashouwer', 'g.glashouwer@chello.nl', '0575561268', 'Braamkamp 340', '7206 HR', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(562, '597.0', 'VDK installatiegroep', '', '', 'c.aeilkema@vdkinstallatiegroep.nl', NULL, 'Prinsessegracht 19', '2514 AP', '\'s-Gravenhage', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(563, '598', 'De Regt Bewind', 'Afd.', 'Administratie', 'info@deregtbewind.nl', '055-8430055', 'Postbus 2777', '7301 EG', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(564, '599.0', 'Thebalux Badkamermeubelen', '', '', 'facturen@thebalux.nl', NULL, 'Hoge Balver 19', '7207 BR', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(565, '600.0', 'UWV', 'Divisie Sociaal-mediche zaken', '', 'jolanda.ruyter@uwv.nl', NULL, 'Postbus 73', '7300 AB', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(566, '601.0', 'MGR SDCG, Module WSP', 'T.a.v. Astrid Sloot, Factuur route 40009', '', 'facturen@mgrsdcg.nl', NULL, 'Postbus 2100', '6802 CC', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 1, 0, NULL, NULL, NULL),
(567, '602.0', 'Leger des Heils regio Oost', '', '', 'Fiston.Muzinga@legerdesheils.nl', NULL, 'Postbus 1198', '7301 BK', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(568, '603.0', 'Mensink & Stout', '', '', 'mensink@mensinkenstout.nl', NULL, 'Postbus 5', '7380 AA', 'Klarenbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(569, '604.0', 'Mevr. M. Bergh', '', '', 'mariadekortbergh@kpnmail.nl', NULL, 'Paulrodenkolaan 112', '7207 GJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(570, '605.0', 'Ministerie van Defensie', 't.a.v. FABK', '', 'FABK.Digitale.Facturen@mindef.nl', NULL, 'Postbus 90060', '3509 AB', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(571, '606.0', 'Mw. Simon', '', '', '', NULL, 'Hobbemakade 246', '7204 TB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(572, '607', 'GeldGezond', 'Afd.', 'Administratie', 'info@geldgezond.nl', '', 'Zweedsestraat 19C', '7418 BG', 'Deventer', 'Facturen voor de ritten van Rouwenhorst', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(573, '608.0', 'Zonwering Lochem', '', '', 'info@zonwering-lochem.nl', NULL, 'Albert Hahnweg 47', '7242 EA', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(574, '609.0', 'Kindcentrum Het Palet', '', '', 'c.brandwacht@varietas.nl', NULL, 'Spijkerpad 1', '7415 AR', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(575, '610.0', 'The Pickwick Players', '', '', 'hammink.Lucas@gmail.com', NULL, 'Borgelerdijk 3', '7415 ZN', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(576, '611', 'Dalton Kindcentrum Lea Dasberg', 'Werner', 'Linthorst', 'w.linthorst1@chello.nl', '', 'Markolle 3', '7207 PA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(577, '612', 'Basisschool St. Joseph', 'D.', 'Dijkgraaf', 'd.dijkgraaf@skbg.nl', '', 'Prins Frisolaan 12a', '7242 GZ', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(578, '613.0', 'Pactus Doetinchem Team 2', '', '', 'info@pactus.nl', NULL, 'Postbus 18', '7000 AA', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(579, '614', '', 'Hellen ', 'Deurloo', 'hellendeurloo@gmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(580, '615.0', 'Munckhof Reizen B.V.', '', '', 'info@munckhofreizen.nl', NULL, 'Jacob Merlostraat 15', '5961 AA', 'Horst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(581, '616', '', 'Caroline', 'van der Ree', 'carolinevanderree@gmail.com', '06 30492602', 'Burg. de Millylaan 1', '7231 DP', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(582, '617.0', 'ZOOV School', '', '', 'school@zoov.nl', NULL, 'Postbus 101', '7100 AC', 'Winterswijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(583, '618', 'Actief Zutphen', 'Erik', 'Combert', 'finance@actiefzutphen.nl', '', 'Laan naar Eme 101', '7204 LZ', 'Zutphen', 'Email voor de facturen: finance@actiefzutphen.nl', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(584, '619.0', 'SKBG Warnsveld', 't.a.v. Ginny van der Zee', '', 'g.vanderzee@skbg.nl', NULL, 'Rijksstraatweg 119A', '7231 AD', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(585, '620', 'Camping de Waterjuffer', 'Afd.', 'Administratie', 'info@campingdewaterjuffer.nl', '0573-431359', 'Jufferdijk 4', '7217 PG', 'Harfsen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(586, '621', 'Arca-Match', 'Mevr. Raffaela Wesselink - Gül', 'Wesselink - Gül', 'info@arca-match.nl', '', 'Hasselobrink 25', '7544 GB', 'Enschede', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(587, '622', 'De Financiële Hulpverlener BV', 'Afd.', 'Administratie', 'info@definancielehulpverlener.nl', '0575-625603', 'Postbus 601', '7400 AP', 'Deventer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(588, '625', 'Aviko BV', 'T.a.v. ', 'I. Hannink', 'invoicein@aviko.nl', '', 'Dr. Alfons Ariënsstraat 28', '7221 CD', 'Steenderen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(589, '626', '', 'Dhr. ', 'Roseval', 'info@definancielehulpverlener.nl', '', 'Aert van Nesstraat 50', '7204 JC', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(590, '627.0', 'Taxi Keizerstad Nijmegen', '', '', 'info@taxikeizerstad.nl', NULL, 'Dr. de Blécourtstraat 76', '6541 DK', 'Nijmegen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(591, '628', 'Constructiebedrijf Rexwinkel B.V.', 'Afd.', 'Administratie', 'factuur@rexwinkel-bv.nl', '', 'Hermesweg 19', '7202 BR', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(592, '629.0', 'Stichting Leergeld Zutphen', 'Bert Mooibroek', '', 'secretariaat@leergeldzutphen.nl', NULL, 'Henri Dunantweg 1', '7201 EV', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(593, '642.0', 'Muziekvereniging OLTO', '', '', 'w_schulenklopper@hotmail.com', NULL, 'Hoofdweg 5', '7371 AC', 'Loenen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(594, '643', 'De Bruin Process Equipment', 'Afd.', 'Administratie', 'invoice@dbpe.nl', '0577723136', 'Oostzeestraat 6', '7202 CM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(595, '644', 'Bon Beschermingsbewind Oost Nederland B.V.', 't.a.v. ', ' mevr. M. Stadnik', 'Stagiaire@bon-almelo.nl', '', 'Postbus 807', '7600 AV', 'Almelo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(596, '645', '', 'Frits', 'van Dijk', 'f.vandijk@monplaisircollegearuba.com', '', '', '', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(597, '646', '', 'H.M.', 'Berkhoff', 'happybus.nl@gmail.com', '', 'Kalmoesstraat 205', '7322 NP', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(598, '647.0', 'Research Coordinator Cardiologie', 'Linda Wesselink', '', 'linda.wesselink@gelre.nl', NULL, 'Den Elterweg 77', '7207 AE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(599, '648.0', 'Rooms Katholieke Basisschool Walter Gillijns', '', '', 'j.davelaar@skbg.nl', NULL, 'Rietbergstraat 2', '7201GJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(600, '649', 'Alwara Beschermingsbewind', 'T.a.v. ', 'Administratie', 's.hogenelst@alwara.nl', '', 'Postbus 6', '7130 AA', 'Lichtenvoorde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(601, '650', 'Westerborkproject Lochem', 'Gijs', 'Rijks', 'rijkspost@kickmail.nl', '0573421014', 'Rosmolenstraat 14', '7241 VR', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', NULL, NULL),
(602, '651.0', 'TCR Tours', '', '', 'leonorekeijzer@gmail.com', NULL, 'Spitsstraat 14', '8102 HW', 'Raalte', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(603, '652.0', 'Taxigwen.dza', '', '', 'taxigwen.dza@gmail.com', NULL, 'Boxtartstraat 7', '7204 GN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(604, '653.0', 'Humanitas DMH', '', '', 'maren.bosse@humanitas-dmh.nl', NULL, 'Newtonbaan 5', '3439 NK', 'Nieuwegein', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(605, '654.0', 'Smallsteps B.V. Locatie', 'T.a.v. Locatie Partou Lea Dasberg', '', 'activiteitencommissiezutphen@gmail.com', NULL, 'Sportlaan 1', '4131 NN', 'Vianen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(606, '655', 'Basisschool St. Joseph', 'Sanne', 'Beekman', 'or@stjoseph.skbg.nl', '0573252439', 'Prins Frisolaan 12a', '7242 GZ', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(607, '656.0', 'IRF-Oldies', '', '', 'bert.companjen@gmail.com', NULL, 'Heijenoordseweg 54', '6813 GB', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(608, '657', 'Gemeente NIjmegen', 'Afd.', 'Administratie', 'm.zoon@nijmegen.nl', '06-50003749', 'Postbus 9105', '6500 HG', 'Nijmegen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(609, '658.0', 'Lunette Sutfene', 'T.a.v. Dhr. Terink  afd: Bastion', '', '', NULL, 'Coehoornsingel 3', '7201 AA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(610, '659.0', 'Mw. Harkink', '', '', 'lagendijk4@gmail.com', NULL, 'Koppeldijk 32B', '7271 EZ', 'Borculo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(611, '660.0', 'Orange Veins', 'T.a.v. Nick van Meer', '', 'nvanmeer@orangeveins.com', NULL, 'Rat Verleghstraat 118C', '4815 PT', 'Breda', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(612, '661', 'Football Algarve', 'T.a.v. Daan ', 'Vlieger', 'daan@footballalgarve.com', '+31613784559', 'Stadhouderskade 57', '1072 AC', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(613, '662', 'Eligant Lyceum Het Stedelijk', 'Marian ', 'Dijk', 'Financien@eligant.nl', '0575590909', 'Isendoornstraat 3', '7201 NJ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, 'financien@eligant.nl', 'Team Financiën', NULL),
(614, '663', '', 'Dhr.', ' Eijsink', 'info@mostimpact.nl', '', 'De Bosrand 3', '7207 ME', 'Zutphen', 'Wil de factuur per post hebben, niet per mail !!', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(615, '664.0', 'Maren Bosse', '', '', 'maren.bosse@humanitas-dmh.nl', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(616, '665.0', 'Inzicht & Houvast', 'Tjarda de Wit', '', 'inzichtenhouvast@gmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(617, '667', '', 'Dhr.', ' Pijnappel', 'in@nn.nl', '06 15453135', 'Berkenlaan 313', '7204 EL', 'Zutphen', 'Heeft geen emailadres .', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(618, '668', 'De Letselschadehulpdiensten', 'T.a.v.', ' Lisette Bruins', 'help@deletselschadehulpdienst.nl', '088 2866700', 'Postbus 23212', '3001 KE', 'Rotterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(619, '669.0', 'HR Budgetcoaching en Bewindvoering', '', '', 'administratie@berkhoutreizen.nl', NULL, 'Postbus 89', '7240 AB', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(620, '670', 'HelloBus BV', 'Afd.', 'Administratie', 'sales@hellobus.nl', '', 'Westplein 8', '3016 BM', 'Rotterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(621, '671.0', 'Penningmeester Han Roebers', 'Karin Hainje', '', 'karinhainje@gmail.com', NULL, 'Laan van Neder Helbergen 14', '7206 DK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(622, '756', 'C.V. Circus Voorst', 'Afd.', 'Administratie', 'cvcircusvoorst@hotmail.com', '', '', '', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(623, '758.0', 'Zorgvervoercentrale Nederland B.V.', 'T.a.v. crediteurenadministratie', '', 'Crediteur-zcn@zcnvervoer.nl', NULL, 'Bahialaan 400', '3065 WC', 'Rotterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(624, '800.0', 'Sa-Net Woonzorg', '', '', 'factuur@sa-net.nl', NULL, 'Gasthuisstraat 13', '7001 AX', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(625, '801.0', 'Mw. Bleumink', '', '', 'beheer@contegobewind.nl', NULL, 'Hanzestraat 27', '7006 RH', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(626, '803', 'Basisschool Antonius de Vecht', 'Afd.', 'Administratie', 'info@antonius.skbg.nl', '055-3231369', 'Kerkstraat 36', '7396 PH', 'Terwolde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(627, '826', 'Gelre Ziekenhuis', 'T.a.v.', 'Facilitair Bedrijf \"Beter voor elkaar\"', 'h.verbeek@gelre.nl', '0621310820', 'Postbus 77', '7200 GL', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(628, '831.0', 'Munckhof Tours', '', '', 'leonorekeijzer@gmail.com', NULL, 'Handelstraat 15', '5961 PV', 'Horst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(629, '833.0', 'KME Netherlands B.V.', '', '', 'invoices.zutphen@kme.com', NULL, 'Oostzeestraat 1', '7202 CM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(631, '1000.0', 'F.M. Stravers', 'Fred', 'Stravers', 'freddystravers1975@live.nl', '', 'Tadamastraat 4', '7201 EP', 'Zutphen', '', '2026-03-11 16:39:33', 0, 1, 1, NULL, NULL, NULL),
(632, '1002', 'Hanzeborg', 'Financiële ', 'Administratie', 'A@A.nl', '', 'Postbus 300', '7200 AH', 'Zutphen', 'Geen mailadres!', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(633, '1005.0', 'Mevr. B. Muil', '', '', '', NULL, 'De Moesmate 31', '7206 AD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(634, '1006.0', 'PZN BV', 'In zake Mobility Services', '', '', NULL, 'Postbus 355', '5000 AJ', 'Tilburg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(635, '1202', 'Den Bouw Zorgcentrum', 'Afd.', 'Financiën', 'facturen@denbouw.net', '0575-522840', 'Abersonplein 9', '7231 CR', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(636, '1251.0', 'MS Centrum Nijmegen', 'Nelly Smeets', '', '', NULL, 'Heijweg 97', '6533 PA', 'Nijmegen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(637, '1253', 'Gemeente Zutphen', 'afd. B.V.R', 'M. Rommers ', 'info@zutphen.nl', '', 'Postbus 41', '7200 AA', 'ZUTPHEN', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(638, '1400', 'Aviko Holding B.V.', 'Financiële ', 'Administratie', 'invoicein@aviko.nl', '', 'Postbus 8', '7220 AA', 'Steenderen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(639, '1402.0', 'RTV Gelderland', 'Administratie', '', '', NULL, 'Postbus 747', '6800 AS', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(641, '1420.0', 'Transvision B.V.', 'Administratie', '', 'Administratie@transvision.nl', NULL, 'Postbus 402', '2900 AK', 'Capelle aan den IJssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(642, '1429.0', 'Taxi Jansen Brekveld B.V.', '', '', '', NULL, 'Boerenstraat 12a', '6961 KC', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(643, '1434', 'Hanzehof', 'Afd.', 'Crediteurenadministratie', 'administratie@hanzehof.nl', '', 'Coehoornsingel 1', '7201 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(644, '1452.0', 'St. Humanitas DMH', '', '', 'mlreerink@gmail.com', NULL, 'Het Horseler 42', '7232 GB', 'WARNSVELD', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(645, '1482.0', 'Mediveen Groep Nederland B.V.', 'Mediveen Administratie', '', '', NULL, 'Postbus 2429', '3500 GK', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(647, '1643.0', 'Reclassering Nederland', 'T.a.v. Maarten Vuylsteke', '', 'crediteuren@reclassering.nl', NULL, 'Postbus 8215', '3503 RE', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(648, '1721.0', 'Tactus', 'Crediteurenadministratie', '', 'facturen@tactus.nl', NULL, 'Piet Heinstraat 27', '7204 JN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(649, '2001.0', 'Van Den Berg Touring & Reizen', 'Administratie', '', 'leonorekeijzer@gmail.com', NULL, 'Voorsterweg 30', '7371 GC', 'Loenen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(650, '2002.0', 'Politieacademie', 'Locatie Warnsveld', '', 'facturen@politieacademie.nl', NULL, 'Postbus 834', '7301 BB', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(652, '2004.0', 'Rentray', 'Financiële Administratie', '', '', NULL, 'Postbus 94', '7200 AB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(654, '2006.0', 'TNO Quality B.V.', 'Anouk Emmerik', '', '', NULL, 'Postbus 541', '7300 AM', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(656, '2009.0', 'Sutfene  De Lunette', 'afd. Financiële Administratie', '', 'facturensutfene@sutfene.nl', NULL, 'Postbus 283', '7200 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(657, '2010', 'Hampshire Hotel Avenarius', 'Tim', 'Olde Olthof', 'tim@avenarius.nl', '05734511122', 'Dorpstraat 2', '7261 AW', 'Ruurlo', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(658, '2011.0', 'Taxi Samberg V.O.F', '', '', 'administratie@samberg.nl', NULL, 'Flierderweg 1', '7213 LT', 'Gorssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(659, '2013', 'Hampshire Hotel s\'Gravenhof Zutphen', 'T.a.v.', 'Fin. Administratie', 'facturen@hotelsgravenhof.nl', '0575596898', '\'s-Gravenhof 6', '7201 DN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(660, '2016.0', 'zvv Be Quick', 'Dhr. Marco Vriezenkolk', '', '', NULL, 'Bronsbergen 6A', '7207 AA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(661, '2017.0', 'Stichting Verdandi', 'H. Kaatman', '', '', NULL, 'Vrijenbergweg 24a', '7371 AA', 'Loenen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(663, '2020.0', 'Kasteel Engelenburg', '', '', 'info@engelenburg.com', NULL, 'Eerbeekseweg 6', '6971 LB', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(665, '2024.0', 'Taxi Monnereau', 'Administratie', '', '', NULL, 'Wijnbergseweg 40a-42', '7006 AJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(666, '2025.0', 'Uwv', 'I.R.', '', '', NULL, 'Postbus 111', '8000 AC', 'Zwolle', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(668, '2028.0', 'SCIO Consult', '', '', '', NULL, 'Keulenstraat 4-L', '7418 ET', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(669, '2030.0', 'UWV', 'afd. Voorzieningen', '', 'efacturen@uwv.nl', NULL, 'Postbus 111', '8000 AC', 'Zwolle', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(670, '2036.0', 'Mw. van de Linde', '', '', '', NULL, 'Hobbemakade 488', '7204 TH', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(671, '2038.0', 'Quadraam', 'afd. Service Organisatie', '', '', NULL, 'Groningersingel', '6835 HZ', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(672, '2039.0', 'Stichting Perspectief Zutphen', 'José Slijtermeilink', '', '', NULL, 'Postbus 418', '7200 AK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(673, '2071.0', 'Woonboulevard Eijerkamp B.V.', 'Crediteurenadministratie', '', 'b.bakker@eijerkamp.nl', NULL, 'Postbus 347', '7200 AH', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(674, '2091.0', 'TOTAL DE STOVEN', '', '', '', NULL, 'Harenbergweg 3', '7206 AA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(676, '2158.0', 'Taxi van Rosmalen', '', '', '', NULL, 'Marktstraat 28', '7161 DH', 'Neede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(677, '2159.0', 'Railion Nederland N.V.', 'Financiële Administratie', '', '', NULL, 'Postbus 2060', '3500 GB', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(678, '2178.0', 'Mevr.W.H. Goedhart', '', '', '', NULL, 'Emmerikseweg 252 - 513', '7206 DE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(679, '2180.0', 'Snackbar Kokkie', 'Wim Diks', '', '', NULL, 'Troelstralaan 25', '7204 LC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(680, '2181.0', 'Kinnarps Nederland BV', '', '', '', NULL, 'Litauensestraat 11/21', '7202 CN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(681, '2182.0', 'Twinform', 'Remco Groothuis', '', 'finbox@twinform.nl', NULL, 'Litauensestraat 11-21', '7200 AK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(682, '2191.0', 'Stichting I.V.B.', 'J.W. Kleiboer', '', 'jw.kleiboer@stichtingivb.nl', NULL, 'Postbus 1077', '7230 AB', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(683, '2192.0', 'Mw. Groeneveld-Luyten', '', '', '', NULL, 'De Waarden 219', '7206 GE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(684, '2197', 'Gelre Ziekenhuis', 'Afd. ', 'Patiëntenadministratie', 'crediteuren@Gelre.nl', '', 'Postbus 9020', '7200 GZ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(686, '2203.0', 'Zorggroep Sint Maarten', 'Woonzorgcentrum De Polbeek', '', '', NULL, 'Postbus 244', '7570 AE', 'Oldenzaal', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(687, '2215.0', 'Het Spijk', 'Mw. Zwanenburg-de Vries  Kamer 290', '', '', NULL, 'Zutphenseweg 202', '7211 EK', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(688, '2216', 'Havi Reizen', 'Afd.', 'Administratie', 'J.Emsbroek@havi-travel.com', '0850708790', 'Postbus 112', '7460 AC', 'Rijssen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(690, '2227', 'Hanzewonen', '.', '.', 'a@A.nl', '', 'Piet Heinstraat 25', '7204 JN', 'Zutphen', 'Geen Emailadres!', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(691, '2228.0', 'St. Vocaal Talent Nederland', '', '', '', NULL, 'Postbus 805', '3500 AV', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(692, '2234.0', 'V.H.O. Facilitair BV', '', '', '', NULL, 'Postbus 101', '7300 AC', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(694, '2499.0', 'Taxibedrijf Klein Brinke', '', '', '', NULL, 'Zutphenseweg 85', '7251 DJ', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(695, '3046.0', 'Studiecentrum Rechtspleging', 't.a.v.  Afdeling FEZ', '', 'fez@ssr.nl', NULL, 'Postbus 364', '7200 AJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(696, '3106.0', 'TCR Zutphen', 'Crediteuren', '', '', NULL, 'Postbus 4118', '7200 BC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(697, '3107.0', 'TCR Vervoer', 'Afd. financiele administratie', '', '', NULL, 'Dordrechtweg 11', '7418 CH', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(700, '3216.0', 'Menzis Zorg en Inkomen', 'Declaraties', '', '', NULL, 'Postbus 75000', '7500 KC', 'Enschede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(701, '3256.0', 'Taxi Rietman', '', '', '', NULL, 'Dordrechtweg 31011', '7418 CH', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(702, '3326.0', 'Sensire', '', '', 'crediteuren.sensire@sensire.nl', NULL, 'Ooyerhoekseweg 6', '7207 BA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(704, '3496.0', 'Tros', '', '', '', NULL, 'Lg Naarderweg 45-47', '1217 GN', 'Hilversum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(705, '4004.0', 'Humanitas DMH centraal Bureau', 'crediteuren adm.', '', 'financien@humanitas-dmh.nl', NULL, 'Postbus 7057', '3430 JB', 'Nieuwegein', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(706, '4006.0', 'Hilarius', 'Wilber de Heus', '', 'wilber@hilarius.nu', NULL, 'Rhienderstein 30', '6971 LX', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(707, '4007.0', 'Marcel Palsenberg', '', '', 'marcelpalsenbarg48@gmail.com', NULL, 'Torenlaan 1', '7231 CA', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(709, '9909.0', 'Facturen Busplanning', '', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 1, NULL, NULL, NULL),
(710, '9910.0', 'Diverse', '', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 1, NULL, NULL, NULL);
INSERT INTO `klanten` (`id`, `klantnummer`, `bedrijfsnaam`, `voornaam`, `achternaam`, `email`, `telefoon`, `adres`, `postcode`, `plaats`, `notities`, `aangemaakt_op`, `gearchiveerd`, `diesel_mail_gehad`, `is_gecontroleerd`, `email_factuur`, `naam_factuur`, `mobiel`) VALUES
(711, '9911', 'Bewindvoerderskantoor Kroezen', 'T.a.v. Dhr. ', 'A.P. Stevens', 't.stevens@kroezenbewind.nl', '0485-572422', 'Postbus 38', '5830 AA', 'Boxmeer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(712, '9912.0', 'Paul Hurenkamp Men & Women', '', '', 'paul@paulhurenkamp.nl', NULL, 'Ambachtstraat 40', '6971 BS', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(714, '9915.0', 'WSV Volleybal Warnsveld/Zutphen', 'Kjell Wismans', '', 'kjell1997@gmail.com', NULL, 'Bussenweide 3', '7231 NE', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(715, '9916.0', 'Theo Thijssen School', '', '', 'ilsebrands@archipelprimair.nl', NULL, 'Mulderskamp', '7205 CM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(717, '9918.0', 'Judith Klaasen', '', '', 'judith.klaasen@gmail.com', NULL, 'Korenbloem 14', '7381GA', 'Klarenbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(719, '9920.0', 'IJsselzangers', 'Dhr. I. Vredenberg', '', 'wilenlodewijk@gmail.com', NULL, 'Wildenborchseweg 19A', '7251 KB', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(720, '9921.0', 'Openbare Basisschool De Rietgors', 'Mevr. Marion Deneus/Nathalie Mannessen', '', 'Mariondeneus@archipelprimair.nl', NULL, 'Spankerenseweg 3', '6974 BA', 'Leuvenheim', NULL, '2026-03-11 16:39:33', 0, 1, 0, NULL, NULL, NULL),
(721, '9922.0', 'THALES UK', 'Emma Relf', '', 'LDP2019@uk.thalesgroup.com', NULL, '4th Floor Jupiter, Manor Royal', 'RH10 9HA', 'Crawley, West Sussex', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(722, '9923', '\'t Spult', 'Rick Verschure', 'Verschure', 'rick.verschure@live.nl', '', 'Laan naar Eme 95', '7204 LZ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(723, '9924.0', 'OBS  De Garve', 'Dhr. Rik Vaartjes', '', 'rik.vaartjes@kpnplanet.nl', NULL, 'Broekweg 9', '7234 SW', 'Wichmond', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(724, '9925.0', 'OBS De Spr@nkel', 'T.a.v. Sonja Scala', '', 'sonjascala@archipelprimair.nl', NULL, 'Beethovenstraat 18', '6961 BD', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(725, '9926.0', 'Lustrumcommissie L11orien', 'Diome Cazier', '', 'xlucolllorien@gmail.com', NULL, 'Sint Nicolaasstraat 64', '1012 NK', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(728, '9929.0', 'OBS de Waaier AZC loc. Voorsterallee', '', '', 'robelburg@archipelprimair.nl', NULL, 'Voorsterallee 1', '7203 DN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(729, '9930', '', 'Fred', 'Savrij Doste', 'savrijdroste@gmail.com', '', 'Boslaan 5', '7231 DG', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(730, '9931.0', 'Oecumenische Basisschool', 'Lieke Zuidwijk', '', 'Liekezuidwijk@hotmail.com', NULL, 'Meengatstraat 23', '6971 VD', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(731, '9932.0', 'Niek Weuring', 'Niek Weuring', '', 'niek_weuring@hotmail.com', NULL, 'Bolksbeekweg 7', '7241 PH', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(733, '9934', 'Businessclub ZVV de Hoven', 'Wilber', '.', 'wilber@hilarius.nu', '', 'Molenweg 32', '7205 BD', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(734, '9935.0', 'Nordin Reugebrink', '', '', 'lorenzolarsnordin@live.nl', NULL, '06-28062786', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(735, '9936.0', 'Job Glorie', '', '', 'jobglorie15@gmail.com', NULL, 'Geelvinckstraat 8', '1901 AH', 'Castricum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(736, '9937.0', 'Taxi Ensink Olst B.V.', 'afd. Administratie', '', 'info@ensinkpersonenvervoer.nl', NULL, 'De Meente 27', '8121 EV', 'Olst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(737, '9938.0', 'Jolanda Heckman', '', '', 'jolanda-plus@online.nl', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(738, '9939', 'HC\'03 Drempt', 'Jacqueline', 'Diseraad', 'j.bastiaannet@hotmail.com', '0652117076', 'Zomerweg 19A', '6996 DD', 'Drempt', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(739, '9940.0', 'Ici Paris xl', '', '', 's.kleineschaars@hotmail.com', NULL, 'Beukerstraat', '7201 LB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(740, '9941.0', 'S.V. Basteom', '', '', 'penningmeester@svbasteom.nl', NULL, 'Prins Bernhardlaan 7', '7221 BA', 'Steenderen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(744, '9945.0', 'Smits & Co Weegbruggen', 'Mw. A. Went', '', 'info@smitsenco.com', NULL, 'L.R. Beijnenlaan 8', '6971 LE', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(746, '9947', 'BV de Berkelbrug', 'Angelique', 'Bosvelt', 'Angeliquebosvelt@hotmail.com', '0650267105', 'Kazerneplein 6', '7211 BM', 'Eefde', '', '2026-03-11 16:39:33', 0, 0, 1, 'berkelbrug@hetnet.nl', '', NULL),
(748, '9949', 'Arriva Touring B.V', 'T.a.v. Crediteurenadministratie', 'Crediteurenadministratie', 'leonorekeijzer@gmail.com', '', 'Postbus 626', '8440 AP', 'Heerenveen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(749, '9950', '', 'Heloise ', 'Dersjant', 'heloisedersjant@gmail.com', '', 'Hooigracht 61', '2312 KP', 'Leiden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(750, '9951', 'De Vrije School Zutphen Loc. Dieserstraat', 'T.a.v. ', 'Karien Flinkert', 'kflinkert@vszutphen.nl', '0652173824', 'Dieserstraat 52', '7201 NG', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(751, '9952', 'BS Expeditie', 'T.a.v. ', 'Lieke Zuidwijk', 'Liekezuidwijk@hotmail.com', '', 'Meengatstraat 25', '6971 VD', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(752, '9953.0', 'HMC  Reservations.nl', 'R. de Winter', '', 'renee@reservations.nl', NULL, 'Vlielandstraat 3', '1181 HL', 'Amstelveen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(753, '9954.0', 'Mw. P. Weerkamp', '', '', 'pmmweerkamp@gmail.com', NULL, 'President Kennedylaan', '1079 NR', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(754, '9955.0', 'Timmerfabriek Hartman', '', '', 'h.hartman@timmerfabriekhartman.nl', NULL, 'Nijverheidsweg 10', '7251 JV', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(755, '9956.0', 'Imke van Lith', '', '', '', NULL, 'Lochemseweg 163a', '7217 RG', 'Harfsen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(759, '9960', 'Amcor Flexibles', 'T.a.v. ', 'Dhr. H. Masselink', 'invoice.zutphen@amcor.com', '06 53 940 738', 'Postbus 12', '7200 AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(760, '9961.0', 'R. v.d. Meer', '', '', '', NULL, 'Geweldigershoek 78', '7201 NC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(761, '9962', '', 'Harmen', 'Groenouwe', 'harmengroenouwe@hotmail.com', '0615114002', 'Gorsselseweg 41a', '7437 BE', 'Bathmen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(762, '9963.0', 'Woutersen - Schotpoort', '', '', '', NULL, 'Brunheim 12', '6971 ZM', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(763, '9964.0', 'Mw. Jansen', '', '', '', NULL, 'Emmerikseweg 252-214', '7206 DE', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(764, '9965.0', 'Mw. L. Kirkaldy', '', '', 'info@keukenvanhackfort.nl', NULL, 'Baakseweg 6', '7251 RH', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(765, '9966.0', 'Radema Business Taxi', '', '', 'dennis@radema.nl', NULL, 'Soltstede 17', '9481 HL', 'Vries', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(766, '9967.0', 'ReubeZorg', 'afd. Gezinswonen Stedendriehoek', '', 'Facturen-Imzorg@pluryn.nl', NULL, 'Ampsenseweg 26', '7241 NC', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(769, '9970', 'Gelre Ziekenhuis', 'T.a.v. ', 'Tjassensheiser', 'crediteuren@Gelre.nl', '', 'Den Elterweg 77', '7207 AE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(770, '9971.0', 'Mw. Stegeman', '', '', '', NULL, 'Haarskamp 58', '7261 ZD', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(771, '9972.0', 'Mw. Evers', '', '', '', NULL, 'Polsbroek 53', '7201 BX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(772, '9973.0', 'Mw. v.d. Pluin', '', '', '', NULL, 'Waterstraat 86', '7201 HN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(773, '9974', 'Gotink Totaalinstallateur', 'Afd.', 'Administratie', 'showroom@gotinkinstallatie.nl', '0575 465258', 'Molenenk 6', '7255 AX', 'Hengelo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(774, '9975', 'Gerda Gijsbertsen', 'Gerda', 'Gijsbertsen', 'gerda@gijsbertsen.net', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(775, '9976.0', 'Restaurant De Sluis', 'T.a.v. Hans en Margreet', '', 'info@restaurantdesluis.nl', NULL, 'Schoolstraat 70', '7211 BD', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(776, '9977', 'CBS De Akker', 'Gerlin', 'Pleysier', 'gerlin.pleysier@pcbo-rheden.nl', '', 'Admiraal Helfrichlaan 13E', '6952 GA', 'Dieren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(777, '9978.0', 'Optisport Warnsveld BV', '', '', 'jeroenmeijerhof@optisport.nl', NULL, 'Postbus 4174', '5004 JD', 'Tilburg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(778, '9979.0', 'Korenblik Trucks C', '', '', 'info@korenbliktrucks.nl', NULL, 'Sontstraat 5', '7202 CW', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(779, '9980.0', 'vrij', '', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(780, '9981.0', 'Van de Ploeg Dienstverlening', 'Mw. N. van der Ploeg', '', 'info@jvanderploeg.nl', NULL, 'Loohorst 12', '7207 BM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(782, '9983.0', 'Saskia Kalksma', 'saskiakalksma@hotmail.com', '', 'saskiakalksma@hotmail.com', NULL, '06-14640816', '', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(784, '9985.0', 'Stichting Zozijn', '', '', 'factuur@zozijn.nl', NULL, 'Walnotenhof 1', '7437 DN', 'Bathmen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(785, '9986.0', 'Windmill Film', 'Mw. A. van der Hell', '', 'annemiek@windmillfilm.com', NULL, 'Amstel 266', '1017 AM', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(786, '9987.0', 'OBS Sterrenbeek', '', '', 'tessahoevers@archipelprimair.nl', NULL, 'Illinckstraat 21', '6961 DM', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(787, '9988.0', 'Mahalia Wallenberg', '', '', 'mahaliajurgen@gmail.com', NULL, 'Havenstraat 54', '2652 BT', 'Berkel en Rodenrijs', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(788, '9989.0', 'Voetbalvereniging Vorden', 'Marc van der Linden', '', 'voorzitter@vvvorden.nl', NULL, 'Oude Zutphenseweg 11', '7251 JX', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(790, '9991.0', 'R.G.J. Slijkhuis - Nijland', '', '', '', NULL, 'Dovenkampweg 3', '7399 RB', 'Empe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(791, '9992.0', 'Klimaatbeheer Ten Hove', '', '', 'administratie@kbtenhove.nl', NULL, 'Stationsweg 75', '8166 KA', 'Emst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(792, '9993', '', 'Henri ', 'Bril', 'henri@hanzebv.nl', '0621182441', 'Schonenvaardersstraat 1', '7420 AE', 'Deventer', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(793, '9994.0', 'Segerink & Wolbers Zutphen B.V.', 'Thijs Gerritsen', '', 'Thijs.Gerritsen@segerink-wolbers.nl', NULL, 'De Stoven 16', '7206 AX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(794, '9995.0', 'Het Hietveld', 'Tim Beeking', '', 'tbeeking@pluryn.nl', NULL, 'Stoppelbergweg 10', '7361 TE', 'Beekbergen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(795, '9996', 'Bureau Overbruggend', 'Mw. G.A.M. ', 'Verbruggen', 'info@overbruggend.com', '0618505954', 'Postbus 176', '6660 AD', 'Elst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(798, '9999.0', 'Mw. Bult', '', '', '', NULL, 'Ensinkweg 6', '7211 CK', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(799, '10000.0', 'mw. Broeke', '', '', '', NULL, 'Broekstraat 7', '7241 PG', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(800, '10001.0', 'Mw. Kronenburg', '', '', '', NULL, 'Rietbergstraat 113', '7201 GD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(801, '10002', 'Bosgoed Diervoeders', '.', '.', 'info@bosgoeddiervoeders.nl', '', 'Zwarte Kolkstraat 96', '7384DE', 'Wilp', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(802, '10003.0', 'mw. C. Klein Wassink', '', '', 'c.kleinwassink@nah.nl', NULL, 'Postbus 23', '7240 AA', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(803, '10004.0', 'Kruisvaarders', 'T.a.v. De Penningmeester', '', '', NULL, 'Schoolstraat 4', '7384 AW', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(806, '10007.0', 'Peter Hodes', '', '', 'brandenbarg@gmx.com', NULL, 'Schoutenstraat 61', '2596 SK', '\'s-Gravenhage', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(807, '10008.0', 'Nick v. Wijhe', '', '', 'nickvanwijhe@hotmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(809, '10010.0', 'Politie', 'Crediteuren administratie', '', 'crediteuren@politie.nl', NULL, 'Postbus 618', '7300 AP', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(810, '10011.0', 'Maaskant reizen', 'Dhr. Jan-Willem van Santen', '', 'jwvsanten@maaskant.com', NULL, 'Meester van Coothstraat 14', '5397 AR', 'Lith', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(812, '10014', 'Hanze-stadsbrouwerij BV', 'Afd.', 'Adminnistratie', 'info@stadsbrouwerij.nl', '', 'Rodetorenstraat 21', '7201 DH', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(813, '10015.0', 'Sutfene', 'Afd. Technische Dienst / Marcel Veldhuis', '', 'bbruil@Sutfene.nl', NULL, 'Postbus 283', '7200 AG', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(815, '10017.0', 'Oosterberg Zutphen', 'T.a.v. Dhr. Mark Duits', '', 'mark.duits@oosterberg.nl', NULL, 'Pollaan', '7202 BX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(817, '10019.0', 'Nordin Reugebrink', '', '', 'lorenzolarsnordin@live.nl', NULL, 'Oranjehof 34', '7255 EC', 'Hengelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(818, '10020.0', 'IPSKAMP printing', 'T.a.v. Dhr. Lamberts', '', 'alamberts@ipskampprinting.nl', NULL, 'Koppelboerweg', '7574 PH', 'Oldenzaal', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(819, '10021.0', 'P.I. Zutphen', 'Afdeling Medische Dienst', '', 'm.zeijdner@dji.minjus.nl', NULL, 'Verlengde Ooyerhoekseweg 21', '7207 BJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(820, '10022.0', 'Kleijn Infra B.V.', 'Mark de Kleijn', '', 'mark@kleijninfra.nl', NULL, 'Nieuwstad 73', '7201 NM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(821, '10023.0', 'Stefanie Schulz', '', '', 'Rostocksteffi@hotmail.com', NULL, 'Bronsbergen 25-67', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(824, '10026.0', 'Leemans Speciaalwerken b.v.', '', '', 'invoice@leemansgroep.nl', NULL, 'Postbus 161', '7670 AD', 'Vriezenveen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(825, '10027.0', 'vandervalk+degroot', 't.a.v. Crediteurenadministratie', '', 'LauraHeine@valkdegroot.nl', NULL, 'ABC Westland 231', '2685 DC', 'Poeldijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(826, '10028.0', 'Junior Kamer Zutphen', 'T.a.v. R.J.W. Uit de Weerd', '', 'juniorkamerzutphen@gmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(827, '10029', 'Gemeentewerf Hengelo G.', 'Dhr. ', 'Tonnie Beeftink', 'T.beeftink@bronckhorst.nl', '06 86874700', 'Zelhemseweg 27', '7255 PT', 'Hengelo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(830, '10032.0', 'Rick Massink', '', '', 'rick90@msn.com', NULL, 'Emperweg 94', '7399 AJ', 'Empe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(831, '10033.0', 'Suzanne Elbers', '', '', 'slelbers@live.nl', NULL, 'Boslaan 10', '7231 DH', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(832, '10034.0', 'Nina Wolters', '', '', 'nina.wolters@hotmail.com', NULL, 'Aagje Dekenstraat 5', '7207 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(833, '10035.0', 'Sjoerd Douma', '', '', 's.douma@live.nl', NULL, 'Theodora Bouwmeesterstraat 23', '7207 HK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(834, '10036.0', 'Lotte Went', '', '', 'lottewent@hotmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(836, '10038.0', 'Mw. Narocci', '', '', '', NULL, 'Graaf Janlaan 6', '7242 BW', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(838, '10040.0', 'Mw. Spruijt', '', '', '', NULL, 'Mussendaal 1', '2914 KH', 'Nieuwerkerk aan den IJssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(839, '10041.0', 'Paddy\'s Food & Drinks', '', '', 'info@paddysdoetinchem.nl', NULL, 'Omdraai 1', '7001 BL', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(840, '10042.0', 'Salbo Dak & Installatietechniek', '', '', 'info@salbo.nl', NULL, 'Verlengde Ooyerhoekseweg 31', '7207 BJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(841, '10043.0', 'Metos B.V', 'Wytse van den Toren', '', 'Wytse.van.den.toren@metos.nl', NULL, 'Keizerstraat', '7411 HH', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(842, '10044.0', 'Stef Willemsen', '', '', 'stefwillemsen27@hotmail.com', NULL, 'Kerkdijk 4', '7437 AN', 'Bathmen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(843, '10045.0', 'Werk en Begeleiding Oost Nederland', 'Kostenplaats nummer 51400', '', 'crediteuren@philadelphia.nl', NULL, 'Radeland 3', '6971 LV', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(844, '10046.0', 'Polycomp', '', '', 'k.wolzak@polycomp.nl', NULL, 'Postbus 57', '7250 AB', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(845, '10047.0', 'Prins Clausschool OR', 'Ingrid Weenk', '', 'iweenk@yahoo.com', NULL, 'Het Zwanevlot 318', '7206 CS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(849, '10051', 'David Evekink Exploitatie Stichting', 'Afd.', 'Administratie', 'a.aartsen@stbog.nl', '', 'Berkelsingel 30', '7201 BL', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(850, '10052', 'Business Club Ruurlo', 'Henrike ', 'Lobbes', 'henrike@hampshire-hotels.com', '0650612430', 'Fürstenauerstraat 2', '7261 PE', 'Ruurlo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(851, '10053', 'Dutch Canadian Food Line B.V.', 'J.', 'Remmelink', 'j.remmelink@dcfl.nl', '0575512854', 'Zweedsestraat 3B', '7202 CK', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(852, '10054.0', 'Melanie Reinders', '', '', 'melanie.homolka@gmail.com', NULL, 'Huzarenlaan 25', '7214 EB', 'Epse', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(853, '10055.0', 'Rijkswaterstaat Water, Verkeer en Leefomgeving', '', '', 'chantal.iesberts@rws.nl', NULL, 'Postbus 2232', '3500 GE', 'Utrecht', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(854, '10056.0', 'Vrije School Zutphen', 'Jouri Bonsel', '', 'facturen@vszutphen.nl', NULL, 'Postbus 146', '7200 AC', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(855, '10057', 'Gemeente Zutphen', 'T.a.v. ', 'Crediteuren adm.', 'financien@zutphen.nl', '', '\'s Gravenhof 2', '7201 DN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(856, '10058.0', 'Remko Oosterwijk', '', '', 'remkooosterwijk1949@gmail.com', NULL, 'Annette Poelmanhoeve 2', '7207 GB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(858, '10060', '', 'H.M.', 'Brink', 'team1@goedbewind.nl', '', 'Pasteurstraat 40', '6951 CK', 'Dieren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(860, '10062.0', 'Leger des Heils', '', '', 'Mandy.Elschot@legerdesheils.nl', NULL, 'Gelderhorst 24', '7207 BH', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(861, '10063', 'Bert Analbers', 'Bert Analbers', 'Analbers', 'bertlex@xs4all.nl', '0628996614', 'Breegraven 27', '7231 JB', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(862, '10064.0', 'Salland Tours V.O.F.', '', '', 'leonorekeijzer@gmail.com', NULL, 'Industrieweg 1c', '8131 VZ', 'Wijhe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(863, '10065.0', 'W. Brock & Zn', '', '', 'info@wbrock.nl', NULL, 'Weg naar Laren 74', '7203 HN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(865, '10067.0', 'Karin Spaink', '', '', 'karin@spaink.net', NULL, 'Tweede Wittenburgerdwarsstraat 74', '1018 LP Am', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(867, '10069.0', 'Munckhof Taxi regie', '', '', 'administratie@munckhof.nl', NULL, 'Handelstraat 15', '5961 PV', 'Horst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(868, '10070.0', 'Warmteplan B.V.', '', '', 'jacqueline@warmteplan.nl', NULL, 'Mercuriusweg 5', '6971 GV', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(869, '10071.0', 'Kimberley Palte', '', '', 'kim_palte@hotmail.com', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(870, '10072.0', 'Mw. Dekker', '', '', '', NULL, 'Langeslag 25', '8181GN', 'Heerde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(871, '10073.0', 'Make-A-Wish Nederland', '', '', 'avanderplas@makeawishnederland.org', NULL, 'Postbus 13', '1200 AA', 'Hilversum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(872, '10074', 'Cafe de Spaan', 'Afd. ', 'Administratie', 'info@despaan.com', '0641239613', 'Nieuwstad 56', '7201 NS', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(873, '10075.0', 'Unico Bewindvoering', 'Mvr. Nori Veldmeijer', '', 'info@unicobewindvoering.nl', NULL, 'De Bouwkamp 1', '6576 JX', 'Ooij', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(874, '10076.0', 'NAB pordukties', 'Judith Nab', '', '', NULL, 'Paardenwal 39', '7201 BV', 'Zuthen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(876, '10078.0', 'Riwis Zorg & Welzijn Tolzicht', 't.a.v. dhr. Vlaswinkel', '', '', NULL, 'Burg. de Wijslaan 35', '6971 CC', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(877, '10079.0', 'WAZ B.V.', 'Frank Wissels', '', '', NULL, 'Ooyerhoekseweg 9', '7207 BJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(878, '10080', 'Connexxion Tours Pouw', 'Marc ', 'Brink', 'marc@pouwvervoer.nl', '0383034000', 'Hagenweg 3c', '44131 LX', 'Vianen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(880, '10082.0', 'Voetbalvereniging Witkampers', 'Secr. Iwan Bos', '', '', NULL, 'Holterweg 47', '7245 SB', 'Laren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(882, '10084', '', 'Dhr. ', 'Kiezebrink', 'steffenserik@yahoo.com', '', 'van Dorenborchstraat 117', '7203 CC', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(883, '10085.0', 'Mental Health Caribbean Nederland (MHC-NL)', 'Dhr. Erik Jansen', '', 'erik.jansen@mentalhealthcaribbean.com', NULL, 'Hogedwarsstraat 3', '5261 LX', 'Vught', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(884, '10086', 'Euro Planit Vorden', 'Dhr.', 'W. Siemes', 'w.siemes@europlanit.nl', '0653149117', 'Dienstenweg 1', '7251KP', 'Vorden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(886, '10088.0', 'Pactum Jeugd en Opvoeding', 'T.a.v. Crediteuren adm.', '', 'pactumcrediteuren@vigogroep.nl', NULL, 'Wageningsestraat 104', '6671 DH', 'Zetten', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(887, '123458.0', 'Panta Rhei', '', '', 'sennejuliamorris@gmail.com', NULL, 'Koningin Wilhelminalaan 9', '7415 KP', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(888, '123459.', 'Boat Bike Tours & Channel Cruises Holland', 'Afd.', 'Administratie', 'jan@boatbiketours.com', '0207235400', 'Aambeeldstraat 20', '1021 KB', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(889, '123460.0', 'KRO NCRV', 'Eugenie van der Weerd', '', 'Eugenie.vanderWeerd@kro-ncrv.nl', NULL, '\'s-Gravelandseweg 80', '1217 EW', 'Hilversum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(890, '123461.0', 'Wesbus', '', '', '', NULL, 'Vaalmanstraat 7', '6931EJ', 'Westervoort', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(891, '123462.0', 'V.O.F. H. van de Bunte', '', '', 'info@hvandebunte.com', NULL, 'Zuiderzeestraatweg West 158', '8085 AK', 'Doornspijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(894, '123465', 'Gelre Ziekenhuis', 'Afd.', 'Crediteurenadministratie', 'Crediteuren@Gelre.nl', '', 'Postbus 9014', '7300 DS', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(895, '123466', 'Hassink Letselschade-expertise B.V.', 'Afd.', 'Administratie', 'hassinkletsel@gmail.com', '', 'Ien Dalessingel 76', '7207LM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(896, '123472', 'Brownies en Downies', 'Ashley ', 'Spiegelenberg', 'Zutphen@browniesanddownies.nl', '0615558464', 'Beukerstraat 54', '7201 LG', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(897, '123473', 'Florusse & Busch duurzame installaties', 'Thomas ', 'Jaspers', 'Facturen@fbduurzaam.nl', '0683224912', 'Nieuwstraat', '7311HZ', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(898, '123474.0', 'Lasbedrijf A&T', '', '', 'info@aentlasbedrijf.nl', NULL, 'Kleine Belt 4', '7202 CS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(900, '123476', 'ForFarmers Nederland B.V.', 'Nikki', 'Vrielink', 'invoiceap@forfarmers.eu', '0880248010 / 0616080', 'Postbus 90', '7490 AB', 'Delden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(901, '9909', 'Adriaan van de Ende', 'Penningmeester ', 'Dhr. Ter mate', 'ov.AdriaanvdEnde@archipelprimair.nl', '0575522234', 'Klaprooslaan 2', '7231HJ', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(903, '587', 'Aydogan Football Management', 'Ahmed', 'Aydogan', 'ahmed@aydoganfootball.com', '+31 6 11842288', 'Postbus 15', '7200AA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(904, '9909', 'CFF Communications', 'Sherilyn', 'Augustuszoon', 'sherilyn.augustuszoon@cffcommunications.nl', '0648352260', 'James Wattstraat 100', '1097 DM', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(905, '123482.0', 'International Tennis Federation', 'Nicola Easton', '', 'Nicola.Easton@itftennis.com', NULL, 'Bank Lane', 'SW15 5XZ', 'Roehamton, London', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(906, '123483.0', 'KNLTB', '', '', 'j.dassen@knltb.nl', NULL, 'Bovenkerkerweg 81', '1187XC', 'Amstelveen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(907, '123484', 'De Einder', 'Anneke ', 'Freeke', 'Afreeke@de-einder.nl', '0618477503', 'Spionkopstraat 9', '2572 NK', '\'s-Gravenhage', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(908, '123485.0', 'Kindcentrum De Flief', '', '', '', NULL, 'Berkenlaan 106', '7204 ES', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(909, '123728.0', 'True North Aspire Inc.', 'Mahfoud Tahri', '', 'coach@tnsaspire.com', NULL, '1351 Bld . Sunnybrooke, Dollard-Des-Ormeaux', '', 'Quebec | H9B 3K9', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(910, '', 'Autohopper', 'Robbie ', 'Altena', 'robbie@autohopper.nl', '0640246118', 'Krommewetering 111', '3543 AN', 'Utrecht', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(911, '9909', '', 'Brahim ', 'Eljafoufi', 'b.eljafoufi@hotmail.com', '', 'Ericaplein 5', '6951 CP', 'Dieren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(912, NULL, 'Witkampers Laren v.v.', 'Carmen Wijnbergen', '', '', NULL, 'Holterweg 47', '7245 SB', 'Laren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(913, '', 'Reiscommissie NBvP afd. Voorst', 'Jenny Metzelaar', 'Metzelaar', 'metzelaarjenny@gmail.com', '0612048217', 'Voorsterklei 9', '7383 RW', 'Voorst', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(914, NULL, 'Uplift BV / Bookabus', 'Maurice Bouma', '', '', NULL, 'Willem de Zwijgerlaan 350-2N', '1055 RD', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(915, NULL, 'O.B.S. Hagewinde Wilp', 'Diane Berends-Schuurman', '', '', NULL, 'Kerkstraat 14', '7384 AS', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(916, '123834', '', 'Frank ', 'Kuijper', 'frank.kuijper@deltafiber.nl', '06-21898349', 'Hellenkamp 20', '7231 HH', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(917, NULL, 'Hof van Gelre', 'Marjet Houwers', '', '', NULL, 'Nieuweweg 38', '7241 EW', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(918, NULL, 'Walter Gillijn BS', 'Jessica Davelaar', '', '', NULL, 'Rietbergstraat 2', '7201 GJ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(919, NULL, 'V.V. Vorden', 'Ed Hiddink', '', '', NULL, 'Oude Zutphenseweg 11', '7251 JX', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(920, NULL, 'K.M.E.', 'Mark de man', '', '', NULL, 'Oostzeestraat 1', '7200 AA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(921, '9909', '', 'Hans ', 'van der Marck', 'j.vdmarck@gmail.com', '06-43101108', 'Marspoortstraat 7B', '7201 JA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(922, '9909', 'Dikkertje Dap Kinderopvang', 'Daan ', 'Borgonjen', 'daan@kinderopvangdikkertjedap.nl', '0857733752', 'Paulus Potterstraat 6', '7204 CV', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(923, NULL, 'Wibe Wissink', 'Wibe Wissink', '', '', NULL, '', '', 'Dieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(924, NULL, 'Petra Kip', 'Petra Kip', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(925, '596', 'Geert en Karen Glashouwer', 'Geert ', 'Glashouwer', 'Kg.glashouwer@gmail.com', '0575561268', 'Braamkamp 340', '7206 HR', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(926, NULL, 'Jan Ligthartschool', 'Femke Averink-van Meel', '', '', NULL, 'Leeuwerikstraat 1', '7203 JB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(927, NULL, 'Schotpoort Logistics', 'Lars Mol', '', '', NULL, 'Kollergang 6', '6961 LZ', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(928, NULL, 'Thebalux B.V.', 'Norbert Sanders', '', '', NULL, 'Hoge Balver 19', '7207 BR', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(929, '123525', '', 'Erwin ', 'Vrielink', 'erwinvrielink1@gmail.com', '0651777290', 'Reeverweg 18', '7217 TC', 'Harfsen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(930, '9909', 'Daan Wiegerinck', 'Daan ', 'Wiegerinck', 'daanwiegerinck@hotmail.com', '0618594903', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(931, NULL, 'WoonSubliem', 'Bert Wentink', '', '', NULL, 'Netwerkweg 5', '7251 KV', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(932, '417', '', 'Bram ', 'Uiterweerd', 'uiterweerdb@gmail.com', '06 83982561 ', 'Oude Eerbeekseweg 19', '6971 BL', 'Eerbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(933, '284', 'Bakker Vorden B.V.', 'Liesbeth ', 'Bakker', 'info@bakker.nl', '0575551312', 'Dorpstraat 24', '7251 BB', 'Vorden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(934, NULL, 'VSO De Zonnehoek', 'Thijs Everaars', '', '', NULL, 'Heemradelaan 102', '7329 BZ', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(935, NULL, 'Julia Walker', 'Julia Walker', '', '', NULL, 'Ruyschstraat 38h', '1091 CD', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(936, NULL, 'Jarno Brouwer', 'Jarno Brouwer', '', '', NULL, 'HH Wilkensstraat', '7383 CE', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(937, NULL, 'Joeri Groot Bronstvoort', 'Joeri Groot Bronsvoort', '', '', NULL, 'Lindebergsdijk 10', '7245 PD', 'Laren Gld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(938, NULL, 'Perplex', 'Mirjam Vastenholt-Ruizendaal', '', '', NULL, 'Willemsplein 46', '6811 KD', 'Arnhem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(939, '9909', 'Crop Belastingadviseurs', 'Leon ', 'Tjakkes', 'LTjakkes@crop.nl', '0263510228', 'Mr. E.N. Kieffensstraat 4', '6842 CV', 'Arnhem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(940, '413', 'B.G.V. afd. Oost Gelderland', 'Constant ', 'Barendsen', 'c.barendsen.sr@gmail.com', '0575472926', 'De Brink 278', '7206 KE', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(941, NULL, 'Kevin Hendriks', 'Kevin Hendriks', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(942, NULL, 'Lennart Elijzen', 'Lennart Elijzen', '', '', NULL, 'Zilversmidshoeve 15', '7316 RH', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(943, NULL, 'Kinkelder Personeelsvereniging', 'Ellen Bolder', '', '', NULL, 'Nijverheidsstraat 2', '6905 DL', 'Zevenaar', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(944, NULL, 'Wijkcentrum Waterkracht', 'Neeltje Joosten', '', '', NULL, 'Ruys de Beerenbrouckstrraat 106', '7204 MN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(945, NULL, 'Trajectum Groot Hungerink', 'Jori Slinkman', '', '', NULL, 'Meijerinkstraat 12', '7211 AE', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(946, NULL, 'Trajectum Eefde', 'Jori Slinkman', '', '', NULL, 'Almenseweg 6', '7211 ME', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(947, NULL, 'Servicepunt werkgevers Midden-Gelderland', 't.a.v. Astrid Sloot', '', '', NULL, 'Mr. D.U. Stikkerstraat 11', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(948, NULL, 'Maarten van der Schaaf', 'Maarten van der Schaaf', '', '', NULL, 'Capittenweg 57A', '1261 JL', 'Blaricum', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(949, '', 'Oranje vereniging Tonden', 'Anja ', 'Heitink', 'anja@ovtonden.nl', '0616340401', 'Hoevesteeg 6', '6975 AE', 'Tonden', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(950, '9909', 'V.V. Dieren', 'Remco ', 'Zegers', 'r_zegers@hotmail.com', '0613461327', 'Kolinieweg 7', '6952 GZ', 'Dieren', '', '2026-03-11 16:39:33', 0, 0, 0, '', '', NULL),
(951, '9909', '\'s Heerenloo', 'Willem ', 'Kloezeman', 'willem.kloezeman@sheerenloo.nl', '0610636365', 'Laan van Groot Schuylenburg 172', '7325 BD', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(952, NULL, 'Hester Paardekoper', 'Hester', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(953, NULL, 'Stichting Allegoeds', 'Fran van den Munkhof', '', '', NULL, 'Molenweg 49a', '6741 KK', 'Lunteren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(954, NULL, 'John Eggink', 'John Eggink', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(955, '365', 'Hartemink Touringcar', 'Mariska ', 'Winnemuller', 'touringcar@hartemink.nl', '0545472010', 'Kiefteweg 6', '7151 HT', 'Eibergen', '', '2026-03-11 16:39:33', 0, 0, 1, 'facturen@hartemink.nl', '', NULL),
(957, NULL, 'Isendoorn College 2', 'Dhr. van Wordragen', '', '', NULL, 'Lage Weide 1', '7231 NN', 'Warnsveld', NULL, '2026-03-11 16:39:33', 1, 0, 0, NULL, NULL, NULL),
(958, NULL, 'Kirsten Plant', 'Kirsten Plant', '', '', NULL, '', '', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(960, NULL, 'Jeroen Draaijer', 'Jeroen Draaijer', '', '', NULL, '', '', 'Eerbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(961, NULL, 'Qlip', 'Truus Staarman', '', '', NULL, 'Zweedsestraat 1a', '7202 CK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(962, '9909', '', 'Edwin ', 'Heuvelink', 'gertonbeld@gmail.com', '0627438661', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(963, NULL, 'Maaltijdservice IJsselvallei', 'Erik ter Hove', '', '', NULL, 'Hallsedijk 18', '6975 AK', 'Tonden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(965, '9909', 'Eijerkamp', 'Marijn ', 'Schotman', 'M.Schotman@eijerkamp.nl', '0641921356', 'Gerritsenweg 11', '7202 BP', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(966, NULL, 'Kompaan College', 'Manon Velthuijzen', '', '', NULL, 'Wijnhofstraat 1', '7203 DV', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(967, NULL, 'Ria Scholthof', 'Ria Scholthof', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(968, NULL, 'Marielle en Henk Beltman', 'Marielle en Henk', '', '', NULL, 'Haitsma Mulierlaan 2', '7241 GB', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(969, NULL, 'Partou BSO Markolle Zutphen', 'Anna Put', '', '', NULL, 'Markolle 5', '7207 PA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(970, '123904', 'De Zonnehoek', 'Petra ', 'Beekman', 'p.beekman@cso-dezonnehoek.nl', '055-5340741', 'Citroenvlinder 77', '7323 RC', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(971, '9909', '', 'Bernard ', 'van Dijk', 'bernardvandijk@hotmail.com', '0654290041', 'Gaanderij 8', '7211 GG', 'Eefde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(972, '9909', '', 'Bas ', 'Abbink', 'bas.abbink95@gmail.com', '', 'Enkweg 10', '7251 EW', 'Vorden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '0657170324', NULL),
(973, '123696', 'Club van 100 wsv apeldoorn', 'Jack ', 'Wegerif', 'or@deachtsprong.skbg.nl', '', 'De Voorwaarts 450', '7321 MG', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(974, '123865', 'Herberg De Gouden Leeuw', 'Lars ', 'van Bussel', 'lars@herbergdegoudenleeuw.com', '0636310798', 'Bovenstraat 2', '7226 LM', 'Bronkhorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(975, '9909', 'Fanclub Toptennis Daisy', 'Christiaan ', 'Wallet', 'cwallet@walletbeheer.nl', '0658398769', 'Callunalaan 7', '7313 GA', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(976, NULL, 'VOF Hetebrij', 'Wilfred Hetebrij', '', '', NULL, 'Ambachtstraat 1A', '8131 TW', 'Wijhe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(977, NULL, 'Wim Koster', 'Wim Koster', '', '', NULL, '', '', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(978, NULL, 'Huub Wijckmans', 'Huub Wijckmans', '', '', NULL, 'Spiegelstraat 3', '7201 KA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(979, '9909', 'Rotary Club Gorssel', 'Han Christiaan ', 'Brinkman', 'hancbrinkman@icloud.com', '', 'Zutphenseweg 1', '7213 GD', 'Gorssel', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(980, NULL, 'TSB-ICT', 'Niels Bonte', '', '', NULL, 'Lochemseweg 7', '7214 RB', 'Epse', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(981, NULL, 'Joji school', 'Joji Na', '', '', NULL, 'Vlijtseweg 140', '7317 AK', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(982, NULL, 'OBS de Fontein', 'Diny Massink', '', '', NULL, 'Karskamp 2', '7232 BD', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(983, '98', 'FC Zutphen 1', 'Frank ', 'de Vries', 'penningmeester@fczutphen.nl', '0545-272259', 'Meijerinkpad 1', '7207 AD', 'Zutphen', '', '2026-03-11 16:39:33', 0, 1, 1, 'penningmeester@fczutphen.nl', NULL, NULL),
(984, '9909', 'Gasterij de Hoek', 'Beau', 'Vrouwerff', 'eetcafe.dehoek@gmail.com', '0575492263', 'Joppelaan 5', '7213 AA', 'Gorssel', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(985, NULL, 'S-Reizen', 'Stefanie Schulz', '', '', NULL, 'Bronsbergen 25-67', '7207 AD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(986, NULL, 'Van Opijnen B.V.', 'Wilma Pasman', '', '', NULL, 'Bochumstraat 6', '7418 EK', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(987, '9909', 'Angelina van Bloemendaal', 'Daniëlle', 'Bernards', 'd.bernards@hotmail.com', '', 'Rijkstraat 107', '7383 AM', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(988, NULL, 'Tom Klein', 'Tom Klein', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(989, '9909', 'De Haan / Schippers', 'Jenne ', 'de Haan', 'dehaan@dehaanschippers.nl', '0573 258258', 'Larenseweg 53', '7241 CM', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(990, NULL, 'Spencer Stuart', 'Hauwert Leversteijn', '', '', NULL, 'Beethovenstraat 522', '1082 PR', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(991, NULL, 'Janne Gieling', 'Janne Gieling', '', '', NULL, '', '', 'Nieuw-Dijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(993, NULL, 'Patrick Velthuis', 'Patrick Velthuis', '', 'info@weddingplanningmetcarlijn.nl', NULL, 'Spittaalstraat 13', '7201 EA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(994, '294', 'Gebo', 'Peter', 'van den Brink', 'peter.vandenbrink@gebo.nl', '0652070169', 'Den Hulst 134', '7711 GT', 'Nieuwleusen', '', '2026-03-11 16:39:33', 0, 0, 1, 'facturen@gebo.nl', 'afd. inkoop', NULL),
(995, '9909', '', 'Dhr M.', 'Brummelman', 'mennobrummelman@hotmail.com', '06-13742672', 'Helena H. Wilkesstraat 12', '7383 CJ', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(996, NULL, 'Salland Tours', 'Jens van Gurp', '', 'leonorekeijzer@gmail.com', NULL, 'Industrieweg 1c', '8131 VZ', 'Wijhe', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(997, '292', 'Baderie Kraus', 'Maaike ', 'Vriezekolk', 'm.vriezekolk@baderiekraus.nl', '0575513931', 'Pollaan 46', '7202 BX', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(998, NULL, 'Stichting VDg. H.E.R.A.', 'Tymen Kloen', '', '', NULL, 'Nieuwezijns Kolk 31', '1012 PV', 'Amsterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1000, '9909', '', 'Gerard en Attie', 'Swienink', 'g.swienink5@upcmail.nl', '0630764822', 'Jan Rozeboomstraat 182', '7204 VA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1001, '9909', 'Flexmakers', 'Mayke ', 'Kettelerij', 'mayke@flexmakers.nl', '0573256380', 'Oosterbleek 59', '7241 DK', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1002, NULL, 'MGR SDCG Module WSP', 'Astrid Sloot, factuur via snelstart', '', '', NULL, 'apart facturenen', '', 'Postbus 2100', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1004, '9909', 'Auto Hillen b.v.', 'Ronnie ', 'Alduk', 'ronniebalduk@gmail.com', '0653727706', 'Bettinkhorst 2', '7207 BP', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1005, '9909', 'Gemeente Zutphen Beheer en onderhoud', 't.a.v. ', 'Michel Wever', 'm.wever@zutphen.nl', '0620820376', 'Verlengde Ooyerhoekseweg 15', '7207 BJ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1006, '9909', '', 'Ester', 'Voorburg', 'evoorburg@hotmail.com', '0657722945', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1007, NULL, 'Hilbert van Dam', 'Hilbert van Dam', '', 'FdeBot@transvision.nl', NULL, 'V.d. Capellenlaan 11', '7203 BL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1008, '9909', '', 'Frank ', 'Wolthuis', 'frank281194@gmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1009, '642', 'Muziekvereniging OLTO', 'Willem ', 'Schulenklopper', 'w_schulenklopper@hotmail.com', '0611538439', 'Hoofdweg 5', '7371 AC', 'Loenen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1010, NULL, 'Piet Zoomers', 'Richard van Roon', '', '', NULL, 'Rijksstraatweg 38', '7384 AE', 'Wilp', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1011, '9909', '', 'Camiel ', 'Alderlieste', 'camielalderlieste@gmail.com', '06 30257932', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1012, '9909', '', 'Gracia ', 'Beijer', 'gracia@kosprosign.nl', '0652042755', 'C. van Droshagenstraat 20', '1382 BP', 'Weesp', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1013, '9909', '', 'Harry ', 'van de Beek', 'harryenmieke.vanbeek@xs4all.nl', '0651335601', 'Groenenbogh 5', '5062 DC', 'Oisterwijk', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1014, NULL, 'Kunstbus', 'Germa ten Duis', '', '', NULL, 'De Boonk 7', '7251 BS', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1015, NULL, 'Rik ten Barge', 'Rik ten Barge', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1016, NULL, 'PCBO de Terebint', 'afd. fin. administratie', '', '', NULL, 'Rederijkershoeve 19', '7326 TH', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1017, '123670', 'De Waterval', 'Hedwig ', 'Oudbier - van Vliet.', 'h.oudbier@veluwseonderwijsgroep.nl', 'Hedwig Oudbier - van', 'Hoofdweg 53', '7371 AE', 'Loenen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1018, NULL, 'S.P. Zutphen', 'Mart de Ridder', '', '', NULL, 'Tadamasingel 90', '7201 EN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1019, '123661', 'PV Futura', 'Betty ', 'Pelamonia', 'bj.pelamonia-pelupessy@belastingdienst.nl', '', 'Laan van Westenenk 494 Oost', '7334 DS', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(1020, NULL, 'Jong Gelre Vorden/Warnsveld', 'Robert van Til', '', '', NULL, 'De Koppel 5', '7251 VN', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1021, NULL, 'Loterijclub Zutphen', 'Edwin Schaap', '', '', NULL, 'Zaadmarkt', '7201 KM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1022, NULL, 'KC De Rietgors', 'Marion Deneus', '', '', NULL, 'Spankerenseweg 3', '6974 BA', 'Leuvenheim', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1023, NULL, 'SO Dr. Herderscheeschool', 'Bas Hesselink', '', '', NULL, 'Schapendijk 3', '7608 LV', 'Almelo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1024, '9909', '', 'Erwin ', 'Zweers', 'erwin_z_@hotmail.com', '0612902682', 'Hawkinstraat 20', '7207 RM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1025, NULL, 'Lejanne Winters', 'Lejanne Winters', '', '', NULL, 'Kastanjelaan 32', '7221 GD', 'Steenderen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1026, NULL, 'Jumbo Zutphen/Brummen', 'Jelke Bouma', '', '', NULL, 'Rudolf Steinerlaan 47', '7207 PV', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1027, NULL, 'Wilco Zutphen B.V.', 'Danielle Koenen', '', '', NULL, 'Estlandsestraat 1', '7202 CP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1028, '9909', 'Gerben Schuppers', 'Gerben ', 'Schuppers', 'gerben.schuppers@hotmail.nl', '0625243511', '', '', 'Vorden', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1029, NULL, 'Thijs van Mourik', 'Thijs van Mourik', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1030, NULL, 'Martijn Scholten', 'Martijn Scholten', '', 'jslinkman@trajectum.info', NULL, 'Westermark 15', '7245 DA', 'Laren GLD', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1031, NULL, 'Stichting Toeristisch varen', 'Walter Stellaart', '', '', NULL, 'Rijkehage 1', '7201 LP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1032, '9909', 'Bello Bourgondisch Genieten', 'Givanni ', 'Bello', 'bellogivanni@gmail.com', '', 'Dorpsstraat 11', '6964 AA', 'Hall', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1033, NULL, 'K.V. de Deurdauwers', 'Bianca van Zon', '', '', NULL, 'Valkenlaan 24', '6951 JV', 'Dieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1034, '123885', 'FOR-WARD', 'Arne ', 'Heitmeijer', 'info@for-ward.nl', '0653206047', 'Stellingmolenlaan 14', '7241 VZ', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1035, NULL, 'Vincent Perk', 'Vincent Perk', '', '', NULL, 'Anna van Hogendorpland 8', '1705 JK', 'Heerhugowaard', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1036, NULL, 'Zorgmarktadvies/Zorgacademie', 'Marc Soeters', '', '', NULL, 'Stephensonstraat 23', '2561 XP', 'Den Haag', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1037, NULL, 'Paul Mol', 'Paul Mol', '', 'jacob.arends@outlook.com', NULL, '', '', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1038, '9909', 'Burton Car Company', 'Nico ', 'Wassenaar', 'nico@burtoncar.com', '0575 546055', 'Zweedsestraat 4', '7202 CK', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1039, NULL, 'Partou kinderopvang', 'Partou Kinderopvang', '', '', NULL, 'Du Tourweg 1', '7214 AJ', 'Epse', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1040, NULL, 'Warnsveldse Boys', 'Dennis Oplaat', '', '', NULL, 'Veldesebosweg 30', '7231 DW', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1041, '9909', 'Hartink Bouwwerk Voorst', 'Andre ', 'Hartink', 'Andre.hartink@gmail.com', '06 16279253', 'Rijksstraatweg 164', '7383 AX', 'Voorst', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1042, NULL, 'Ron Simmelink', 'Ron Simmelink', '', '', NULL, '', '', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1043, '208', 'Basisschool De Kleine Wereld', 'Geeske ', 'van Apseren', 'ordekleinewereld@gmail.com', '0571272284', 'Jachtlustplein 30 C', '7391 BW', 'Twello', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1045, NULL, 'Miranda Kok', 'Miranda Kok', '', '', NULL, 'Achterste Kerkweg 89', '7364 BV', 'Oosterhuizen (Lieren)', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1046, NULL, 'SOTOG', 'Frank de Vries', '', '', NULL, 'Schoollaan 3', '7271 NS', 'Borculo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1047, '123840', 'Garage Elshof', 'Rein-Jan ', 'Koop', 'reinjan@dorpsgarageelshof.nl', '0628203911', 'Coldenhovenseweg 27', '6961 EA', 'Eerbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1048, NULL, 'KTC b.v.', 'Lieke Pasman', '', '', NULL, 'Industrieweg 85', '7202 CA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL);
INSERT INTO `klanten` (`id`, `klantnummer`, `bedrijfsnaam`, `voornaam`, `achternaam`, `email`, `telefoon`, `adres`, `postcode`, `plaats`, `notities`, `aangemaakt_op`, `gearchiveerd`, `diesel_mail_gehad`, `is_gecontroleerd`, `email_factuur`, `naam_factuur`, `mobiel`) VALUES
(1049, NULL, 'Stadswandelingen Zutphen', 'Willeke Schuurman', '', '', NULL, 'Houtmarkt', '7201 KL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1050, NULL, 'Koen van Dulmen', 'Koen van Dulmen', '', '', NULL, '.', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1051, NULL, 'Het Vandermolenhuis', 'Isa te Braake', '', '', NULL, 'Zutphenseweg 6', '7241 KR', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1052, '', 'Auto Service Eefde', 'Angelique Kasteleijn', 'Kasteleijn', 'administratie@autoserviceeefde.nl', '0575540317', 'Zutphenseweg 73', '7211 EB', 'Eefde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1053, '68', 'Arriva Touring', 'Fokke-Jan', 'van der Zwet', 'fokkejan.bos@arriva.nl', '0503688176', 'Bornholmstraat 60', '9723 AZ', 'Groningen', '', '2026-03-11 16:39:33', 0, 0, 1, 'Digitaal.factuur@arriva.nl', 'T.a.v. Financiële administratie ', NULL),
(1055, '123681', 'ExcursieCie Triangulum', 'Pieter ', 'Zijlstra', 'pieter.zijlstra.pz@gmail.com', '0621822563', '.', '', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1056, '9924', 'Basisschool De Garve', 't.a.v. Guido Langenhof', 'Guido Langenhof', 'Firebird_guido@hotmail.com', '0654365724', 'Dorpsstraat 19', '7234 SM', 'Wichmond', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1057, '9929', 'AZS De Waaier', 'Esther Zwanepol', 'Zwanepol', 'estherzwanepol@archipelprimair.nl', '0575571267', 'Voorsterallee 1', '7203 DN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1058, NULL, 'Ingrid van Amerongen', 'Ingrid van Amerongen', '', 'hammink.lucas@gmail.com', NULL, 'Gasthuisstraat 8', '7201 MN', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1060, '123629', 'C.S. \'t Sleutelveugeltjen', 'Penningmeester Betsy Rikkers', 'Betsy Rikkers', 'jcrikkers61@hotmail.com', '0646501522', 'Het Kasteel 196 C', '7325 PP', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1061, NULL, 'KBO Zutphen / Warnsveld', 'Hanneke Geurtsen', '', '', NULL, 'Weerdslag 47', '7206 BS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1062, NULL, 'Jorn Kappert', 'Jorn Kappert', '', '', NULL, '.', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1063, '9909', '', 'Egbert ', 'Stormink', 'eppe1962@hotmail.com', '0622171127', 'Acaciaplein 11', '7213 WK', 'Gorssel', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1064, NULL, 'Stichting Moviera', 'Esm√© Aardema', '', '', NULL, 'Halstraat 56,', '7321 AH', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1065, NULL, 'Zonne-oord', 'Antoon Urgert', '', '', NULL, 'Het Zwanevlot 355', '7206 CT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1066, NULL, 'Jos Wesseldijk', 'Jos Wesseldijk', '', '', NULL, '.', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1067, NULL, 'W. de Boer', 'Wim de Boer', '', '', NULL, 'Zichtweg 45', '7335 AZ', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1068, NULL, 'Mark Leuvenink', 'Mark Leuvenink', '', '', NULL, 'nb', '', 'nb Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1069, NULL, 'Nadine Weltevreden & Bas', 'Henneman', '', '', NULL, 't.a.v. Nadine & Bas', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1070, '', 'KVZ', 'Kees ', 'Pieters', 'kees.pieters@knkv.nl', '0610059794', 'Laan naar Eme 103', '7204 LZ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(1071, '', 'Luuk Ter Hove', 'Luuk ', 'ter Hove', 'info@luukterhove.nl', '0681246305', 'Weg over \'t Hontsveld 22', '7399 RK', 'Empe', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(1072, '9909', '', 'Harry ', 'Kreulen', 'hkreulen@gmail.com', '0644192233', '', '', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1073, NULL, 'Raad en Daad evenementen', 'Jolanda Slee', '', '', NULL, 'Schiemond 20', '3024 EE', 'Rotterdam', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1074, NULL, 'HVZ', 'Bini Jansen', '', '', NULL, 'Spiegelstraat 13', '7201 KA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1075, '9909', 'Afd. Bestuur en Organisatie Lochem', 'Conny ', 'Renskers-Verink', 'j.tijhuis@lochem.nl', '0573289118', 'Hanzeweg 8', '7241 CR', 'Lochem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1076, '9909', '', 'Hans ', 'Roordink', 'hansroordink52@hotmail.com', '061111111111', 'Kloetschup 41', '7232 CJ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1077, '123508', '', 'Charel ', 'van Luttikhuizen', 'charel.esmee@hotmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1078, NULL, 'Ingmar Fokke', 'Ingmar Fokke', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1079, NULL, 'Rik Buurmeijer', 'Rik Buurmeijer', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1080, '9909', 'Ferrocal B.V.', 'Matthias ', 'Hoorn', 'matthias00@hotmail.nl', '06 22390162', 'Hazenberg 1', '6971 LC', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1082, '9909', 'Gidsen van Walburgiskerk en Librije', 'Nico ', 'Lubbers', 'nh.lubbers@gmail.com', '0612606361', 'Kerkhof 3', '7201 DM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1083, '123778', 'Cunera Basisschool', 'Anne ', 'de Ruijter', 'm.a.deruijter@tabijn.nl', '0251650860', 'Vondelstraat 25', '1901 HT', 'Castricum', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1084, NULL, 'Loes Straatman', 'Loes Straatman', '', '', NULL, '', '', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1085, NULL, 'IKL', 'Arne Heijmeijer', '', '', NULL, 'Stellingmolenlaan 14', '7241 VZ', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1086, NULL, 'Philadelphia Zutphen', 'Robin van den berg', '', '', NULL, 'Zwanevlot 355', '7206 CT', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1087, '123915', 'Amipox Kunstvloeren B.V.', 'Alwin ', 'Nieskens', 'info@amipox.nl', '0575 47 33 71', 'Loohorst 4', '7207 BM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1088, NULL, 'Het Lichtpunt', 'Eunice Niemeijer', '', '', NULL, 'Willem Dreesstraat 4', '7204 JK', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1089, NULL, 'Marcel en Peter', 'Marcel en Peter', '', 'brandenbarg@gmx.com', NULL, 'Noordhavenpoort 49', '2152 HC', 'Nieuwe-Vennep', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1090, NULL, 'Wouter Ebbink', 'Wouter Ebbink', '', '', NULL, '', '', '?Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1091, NULL, 'Het Nut', 'Penningmeester H.J. Blikman', '', '', NULL, 'Rembandtstraat 10', '7103 AG', 'Winterswijk', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1092, '69', 'Stichting GGnet Warnsveld', 'Robert ', 'Steenbergen', 'R.Steenbergen@ggnet.nl', '0889331595', 'Vordenseweg 12', '7230 GC', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, 'crediteurenadministratie@ggnet.nl', 'Afd. Crediteurenadministratie', NULL),
(1094, NULL, 'Servicepunt werkgevers Midden-', 'Gelderland', '', '', NULL, 't.a.v. Astrid Sloot', '', 'Mr. D.U. Stikkerstraat 11', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1095, NULL, 'Jeroen Wolterink', 'Jeroen Wolterink', '', '', NULL, 'NB', '', 'NB NB', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1096, NULL, 'Nicolette Vonkeman', 'Nicolette Vonkeman', '', '', NULL, '', '', 'Laren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1097, '123783', '', 'Eva ', 'Meijer', 'evameijer98@icloud.com', '0631089076', '', '', 'Eefde', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1098, NULL, 'Joyce Vreeswijk', 'Joyce Vreeswijk', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1099, '9909', 'De Vrijeschool Zutphen VO', 'Afd.', 'Administratie', 'info@vszutphen.nl', '0575538720', 'IJsselhank 1', '7206 DG', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1100, '', 'Amy van Spaendonk', 'Amy ', 'van Spaendonk', 'amyspaendonk@upcmail.nl', '0655181917', 'Dijkshornhof 15', '5043 HK', 'Tilburg', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1101, NULL, 'Prins Clausschool', 'Rutger Smits', '', '', NULL, 'Het Zwanevlot 318', '7206 CS', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1102, NULL, 'Technova College', 'Leen Berkhoff', '', '', NULL, 'Bovenbuurtweg 7', '6717 XA', 'Ede', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1104, '156', 'Headlam', 'Maureen ', 'Wardenaar', 'Maureen.Wardenaar@headlam.nl', '0640935917', 'Bettinkhorst 4', '7207 BP', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1106, NULL, 'Rosalie Siemens', 'Rosalie Siemens', '', '', NULL, '', '', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1107, '123511', '', 'Carolien ', 'Demmers-Ceelen', 'carolien@demmersonline.com', '0650951211', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1108, '123910', '', 'Fred ', 'Witeveen', 'fredwitteveen@kpnmail.nl', '0623950493', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1109, NULL, 'KHN', 'Cynthia Marras', '', '', NULL, 'Afd. Zutphen', '', 'Dreef 8 7202 AG', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1110, '', 'Basisschool de Leer', 'Sanne ', 'Beunk', 'sannebeunk@hotmail.com', '0622298620', 'Sint Michielsstraat 6', '7255 AP', 'Hengelo (G)', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1111, NULL, 'Wouter Loman', 'Wouter Loman', '', '', NULL, '', '', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1112, NULL, 'Vrouwen aktief', 'Maartje Derks', '', '', NULL, 'Mulderskamp 110', '7205 BX', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1113, NULL, 'Woman in Business', 'Dianne Temmink', '', '', NULL, 'Noorderhavenstraat 49', '7202 DD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1114, NULL, 'Wim Buitendijk', 'Wim Buitendijk', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1115, '123739', '', 'Gimley ', 'de Graaf', 'Gimleyy@hotmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1116, NULL, 'Veilinghuis Bouwman', 'David Bouwman', '', '', NULL, 'Saturnusweg 6a', '6971 GX', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1117, '161', 'Buurtvereniging Dorpskwartier', 'Hans ', 'van Geel', 'j.geel802@upcmail.nl', '0619885940', '\'t Spiker 33', '7231 JL', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1118, NULL, 'ZVV \'56', 'Wouter Van der pluijm', '', '', NULL, 'Schoonbroeksweg 8-A,', '7323 AN', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1119, NULL, 'Slagwerkgroep D.E.S.', 'Jan Willem Slijkhuis', '', '', NULL, 'Hallseweg 65', '6964 AK', 'Hall', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1120, NULL, 'swv Ijssel Berkel', 'Antoinette Ruisch', '', '', NULL, 'Houtwal 16 b', '7201 ES', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1121, NULL, 'Niels Mattijssen', 'Niels Mattijssen', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1123, '123690', '', 'Hans ', 'Den Bakker', 'hansdenbakker@icloud.com', '0615901168', '', '', 'Hoenderloo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1124, '9909', 'Alex Zuuk', 'Alex ', 'Zuuk', 'aj.v.zuuk@gmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1125, '', 'Bosrestaurant Joppe', 'Ton', ' ', 'ton@bosrestaurant.nl', '0575 494206', 'Joppelaan 100', '7215 AE', 'Joppe', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1126, NULL, 'Restaurant Efeze', 'Elisa Kramer', '', '', NULL, 'Houtmarkt 50', '7201 KM', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1128, '9909', 'De Tender School voor Praktijkonderwijs', 'Ralph', 'Hunholz', 'hur@hetrhedens.nl', '0313422765', 'Harderwijkerweg 1-B', '6952 AA', 'Dieren', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1129, NULL, 'Taxi Hoijtink', 'Martin Hoijtink', '', '', NULL, 'Kerkstraat 20', '7261 GG', 'Ruurlo', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1130, NULL, 'P.V. De Goede Woning', 'Erik Nengerman', '', '', NULL, 'Sleutelbloemstraat 26', '7322 AG', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1131, '250', 'Cafe Pierrot', 'Afd.', 'Administratie', 'info@grandcafepierrot.nl', '0575540100', 'Houtmarkt', '7201 KM', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1132, NULL, 'V.v. Gorssel', 'Frank Wouterse', '', '', NULL, 'Markeweg 10', '7213 GC', 'Gorssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1133, NULL, 'Jan Willem de Groot', 'Jan Willem de Groot', '', 'info@taxiarnhemabc.nl', NULL, 'Dennenweg 1', '7382 BW', 'Klarenbeek', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1134, '', 'Bonhof B.V.', 'Eric ', 'Bonhof', 'Eric@bonhof.com', '06 30 97 21 66 ', 'Zonennbergstraat 46A', '7384 DL', 'Wilp', '', '2026-03-11 16:39:33', 0, 0, 1, 'facturen@Bonhof.com', '', NULL),
(1135, '9941', 'S.V. Basteom Steenderen', 'Peter ', 'Onstenk', 'peteronstenk1@hotmail.com', '0575451547', 'Prins Bernhardlaan 7', '7221 BA', 'Steenderen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1137, NULL, 'Willeke Groenenberg', 'Willeke Groenenberg', '', '', NULL, 'Heintje Davidsplein 50', '7207 GL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1138, '9909', 'Glans b.v.', 'Mark ', 'Heuvelink', 'info@glans-bv.nl', '0657944700', 'Verlengde Ooyerhoekseweg 31', '7207 BJ', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1139, NULL, 'Remon Derksen', 'Remon Derksen', '', '', NULL, '', '', 'Duiven', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1140, '123557', 'De Vrije School De Zonnewende', 'Anja ', 'Mostert', 'administratie@vrijeschoolzutphen.nl', '0575–516380', 'Valckstraat 30', '7230 GC', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1141, NULL, 'Temmink', 'Cas Peters', '', '', NULL, 'Herfordstraat 14', '7418 EX', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1142, NULL, 'John F. Kennedyschool', 'Dennis Dijkgraaf', '', '', NULL, 'Leeuweriklaan 21', '7203 JD', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1143, NULL, 'Jan Dieperink', 'Jan Dieperink', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1144, NULL, 'Sportclub Deventer', 'Robert Berends', '', '', NULL, 'Sportveldenlaan 28', '7412 AZ', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1145, NULL, 'Winkels', 'Patty Wentink', '', '', NULL, 'Industrieweg 11', '7251 JT', 'Vorden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1146, NULL, 'W. Visser & D. Jaffari', 'W. Visser', '', '', NULL, 'Blauweberg 5005', '1625 NT', 'Hoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1147, NULL, 'Partou Warnsveld', 'Femke', '', '', NULL, 'Runneboom 4-6', '7232 CX', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1148, '9909', 'Bjorn Loman', 'Bjorn', 'Loman', 'bjorn232loman@hotmail.com', '0653100876', '', '', 'Harfsen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1149, '9909', 'Anne-Wendy Stolk & Marnix Keurhorst', 'Anne-Wendy ', 'Stolk', 'anne-wendystolk@hotmail.com', '', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1150, NULL, 'Klant Leen Apeldoorn', 'Klant Apeldoorn', '', '', NULL, '', '', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1151, NULL, 'Maulik', 'CK Maulik', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1152, NULL, 'Marloes Jansen', 'Marloes Jansen', '', '', NULL, 'Rudolf Steinerlaan 31', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1153, NULL, 'Roy Smallegoor', 'Roy Smallegoor', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1154, NULL, 'Karen Driedijk', 'Karen Driedijk', '', 'hallokaren@gmail.com.', NULL, '', '', 'Tilburg', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1155, NULL, 'Marcel van den Breemen', 'Marcel van den Breemen', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1156, '323', 'Ecolab Microtek', 'Patrizia ', 'Deriu', 'Patrizia.Deriu@ecolab.com', '0575599244', 'Hekkehorst 24', '7207 BN', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1157, '9909', 'Heijink Bouw', 'Henk ', 'Heijink', 'info@bouwmarkt-heijink.nl', '0651950166', 'Leestenseweg 10', '7207 EA', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1158, NULL, 'Kevin Frijlingh', 'Kevin Frijlingh', '', '', NULL, 'Keulenstraat 4', '7418 ET', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1159, NULL, 'Martijn Oostenrijk', 'Martijn Oostenrijk', '', '', NULL, 'Nb', '', 'nb nb', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1160, NULL, 'Walter Lammerse', 'Walter Lammerse', '', '', NULL, 'Sterrenlaan 19', '7314 KG', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1161, NULL, 'Toneelveren. Beekbergen/Lieren', 'Sandra Bogerman', '', '', NULL, '', '', 'Beekbergen/Lieren', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1162, NULL, 'Rutger Smits', 'Rutger Smits', '', '', NULL, 'Braamkamp 526', '7206 JB', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1163, NULL, 'Pannevogel school de', 'Hilde Eliesen', '', '', NULL, 'Prins Bernhardlaan 5a', '7221 BA', 'Steenderen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1164, '123533', '', 'Emile ', 'Jager', 'emilejager79@gmail.com', '0681489280', '', '', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1165, '9909', '', 'Charlotte en Marvin ', 'Buutveld', 'cevandolron@hotmail.com', '0645149371', 'Sloetstraat 33', '7203 GK', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1166, NULL, 'Inne Boer', 'Inne Boer', '', '', NULL, '', '', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1167, '398', 'C.V. de Gorsselnarren', 'Marten ', 'Klomp', 'cvdegorsselnarren@gmail.com', '0618642227', 'Hoofdstraat 25E', '7213 CN', 'Gorssel', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1168, NULL, 'Jan Duits', 'Jan Duits', '', 'info@manders.nl', NULL, '', '', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1169, NULL, 'Paddy,s', 'Jette Roenhorst', '', '', NULL, 'Omdraai 1', '7001 BL', 'Doetinchem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1170, '9909', '', 'Gerrit ', 'Weijenberg', 'gerritweijenberg@gmail.com', '0653205594', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1171, '9909', 'C.V. De Lollebroek', 'John ', 'Eggink', 'jmeb@upcmail.nl', '0653951241', 'Kievitsweg 5', '7384 SV', 'Wilp achterhoek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1172, NULL, 'Trix van Duijn', 'Trix van Duijn', '', '', NULL, 'Tusseler 182', '7241 KL', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1173, NULL, 'Leo Wolhoff', 'Leo wolhoff', '', '', NULL, 'Flora 33', '7422 LN', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1174, '9909', 'Dalton kindcentrum \'t Park', 'Elles ', 'Derksen', 'ellesderksen@archipelprimair.nl', '0610059460', 'Troelstralaan 45, 49A', '6971 CN', 'Brummen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1175, '9909', 'Bas bureau', 'H.J.W. Leisink', 'Leisink', 'hl@bureau-bas.nl', '0553011975', 'Klarenbeekseweg 99', '7381 BE', 'Klarenbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1177, NULL, 'OBS Het Web', 'Lidia Wijnbergen-Bouwmeester', '', '', NULL, 'Descartesstraat 10', '7323 HX', 'Apeldoorn', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1178, NULL, 'Lynn Blom', 'Lynn Blom', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1179, NULL, 'Rober Willemsen', 'Robert Willemsen', '', '', NULL, 'Goudzuring 17', '6971 MJ', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1180, NULL, 'Interdependent Power B.V.', 'Maarten Smits', '', '', NULL, 'Lariksweg 12', '7213 WH', 'Gorssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1181, NULL, 'Kirsten Katier', 'Kirsten Katier', '', '', NULL, 'Spoorstraat 1', '7491 CK', 'Delden', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1182, NULL, 'Mark Kappert', 'Mark Kappert', '', '', NULL, 'Lage Lochemseweg 5', '7231 PK', 'Warnsveld', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1183, NULL, 'Jansen Transport Drempt', 'Bart Jansen', '', '', NULL, 'H. Remmelinkweg 135', '6996 DH', 'Drempt', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1184, NULL, 'Willem Meijer', 'Willem Meijer', '', '', NULL, '', '', 'Gorssel', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1185, NULL, 'Sandy Huw & Renaldo van Buren', 'Jorien van der Hast Wedding', '', '', NULL, 'Edward Jennerhof 19', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1186, NULL, 'Kookclub Bychiel', 'Lieuwe Kool', '', '', NULL, '\'s Gravenhof', '7201 EP', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1187, '', 'Brandweer Julianadorp', 'Marit ', 'Melchers', 'm_melchers@hotmail.com', '06 15547594', 'Van Foreestweg 12', '1787 BL', 'Julianadorp', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1188, NULL, 'VTS Taxi & Torurs', 'Diana Den Herder', '', '', NULL, 'Marconiweg 35', '8071 TB', 'Nunspeet', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1190, NULL, 'Verhuur Happybus', 'Leen Berkhoff', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1191, NULL, 'Joke Mutsaars', 'Joke Mutsaars', '', '', NULL, 'Hobbemakade 90', '7204 TA', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1192, NULL, 'Wouter Mombarg', 'Wouter Mombarg', '', '', NULL, '', '', 'Harfsen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1193, NULL, 'Z.V.V. Be Quick', 'Gordon', '', '', NULL, 'Laan naar Eme 97', '7204 LZ', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1194, '310', 'De Nieuwe Sociëteit', 'Jaap ', 'Velhoen', 'jaapveldhoen64@gmail.com', '0624762224', 'Beukerstraat 13', '7201 LA', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1195, NULL, 'M. Buiten', 'M. Buiten', '', '', NULL, 'Barchemseweg 83', '7241 JC', 'Lochem', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1196, '123465', 'Dispuut Groningen', 'Noelle ', 'Nijhof', 'noelle.nijhof@gmail.com', '', 'Oude Ebbingestraat 49A', '9712 HC', 'Groningen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1197, '9909', '', 'Emiel ', 'Feld', 'efeld@fast4you.nl', '0642631543', 'De Waard 61', '1851 RB', 'Heiloo', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1198, NULL, 'Jorn Braakhekke', 'Jorn Braakhekke', '', '', NULL, '', '', 'Eefde', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1199, '123552', '', 'Floor ', 'Melgers', 'f.melgers@derabot.nl', '0615305744', '', '', 'Didam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1200, NULL, 'Lieke Pasman', 'Lieke Pasman', '', '', NULL, '', '', 'Voorst', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1201, '9909', '', 'Hanny ', 'Luimes', 'hanneke.koning@oosterberg.nl', '0626123237', '', '', '', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1202, '9909', '', 'Ghislaine', '.', 'ghislaineandmatt@gmail.com', '0657582232', 'Hembrugstraat 27-2', '1013 WV', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1203, '9909', 'Dweilorkest \'t Spult', 'Johan ', 'Nieuwenhuizen', 'jnieuwenhuizen@domest.nl', '0654237752', 'Houtwal 5', '7201 ES', 'Zutphen', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1204, '9909', 'Autimaat', 'Anne', 'Bos', 'a.bos@autimaat.nl', '06-33365599 ', 'Burg. van Nispenstraat 10', '7001 BS', 'Doetinchem', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1205, '123861', 'De Jongste Compagnie van Verre', 'Melissa ', 'Cheung', 'melissacheung050606@icloud.com', '', 'Keizersgracht 285', '1016 ED', 'Amsterdam', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1207, '123579', 'Bertus Bosvelt', 'Bertus ', 'Bosvelt', 'bertusbosvelt@live.nl', '0624647626', '', '', 'Warnsveld', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1208, NULL, 'Stichting Bog', 'Angelique Aartsen', '', '', NULL, 'Berkelsingel 30', '7201 BL', 'Zutphen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1209, NULL, 'Van Wijnen Deventer B.V.', 'Erna Rijksen-Arns', '', '', NULL, 'Visbystraat 5', '7418 BE', 'Deventer', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1210, NULL, 'Osiris', 'Jelmer Spuijbroek', '', '', NULL, '', '', '', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1211, '9909', 'Groen Grijs Achterhoek', 'Frans', 'Hummelink', 'hummelink41@gmail.com', '0618152462 ', 'Hofteweg 8', '7261 ND', 'Ruurlo', '', '2026-03-11 16:39:33', 0, 1, 1, '', '', NULL),
(1212, '9909', '', 'Erwin ', 'Reusken', 'e.reusken@sbpost.nl', '0622832499', 'Tullekenweg 17', '6961 EM', 'Eerbeek', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1213, NULL, 'Stichting Archipel, \'t Goor', 'Natasje Boerkamp', '', '', NULL, 'Troelstralaan 49', '6971 CN', 'Brummen', NULL, '2026-03-11 16:39:33', 0, 0, 0, NULL, NULL, NULL),
(1214, '123628', '', 'Ella ', 'Wiegers', 'ella.wiegers@icloud.com', '0642807550', '', '', 'Apeldoorn', '', '2026-03-11 16:39:33', 0, 0, 1, '', '', NULL),
(1215, '123901', 'OBS Prins Willem Alexander, ', 'Richard ', 'Liebregt', 'r.liebregt@obspwa.nl', '', 'Donge 1', '2911 CV', 'Nieuwerkerk aan den IJssel', '', '2026-03-17 09:33:33', 0, 1, 0, NULL, NULL, NULL),
(1216, NULL, '', 'Suus', 'Lodewijks', 'suuslk@hotmail.com', '0628125988', 'Houtmarkt 63', NULL, 'Zutphen', NULL, '2026-03-19 13:06:52', 0, 0, 0, NULL, NULL, NULL),
(1217, '9909', '2Switch', 'Else', 'Gortemoller', 'else@kringloopwinkelhelmond.nl', '0653721039', 'Pollaan 52', '7202 BX', 'Zutphen', '', '2026-03-19 14:36:19', 0, 0, 1, '', '', NULL),
(1218, '9909', '3Plogistics', 'Grada ', 'Hietbrink', 'aangrada@hotmail.com', '0616840238', 'Pollaan 1', '7202 BV', 'Zutphen', '', '2026-03-19 14:38:46', 0, 0, 1, NULL, NULL, NULL),
(1219, '', '', 'Alexander', 'Du Boisson', 'alexander@telego.nl', '0642116116', 'Grooterkamp 46', '7213 HB', 'Gorssel', '', '2026-03-19 14:42:13', 0, 0, 1, NULL, NULL, NULL),
(1220, '', '', 'Aafke ', 'Tomassen', 'mikepeters001@hotmail.com', '0617179704', '', '', '', '', '2026-03-19 14:44:31', 0, 0, 1, NULL, NULL, NULL),
(1221, '', '', 'Aaldert', 'Bakker', 'awbakker39@gmail.com', '0648855172', 'Deventerweg 39', '7213 ED', 'Gorssel', '', '2026-03-19 14:46:26', 0, 0, 1, NULL, NULL, NULL),
(1222, '9909', 'Achterhoek VO', 'Vanessa ', 'Maassen', 'vanessa.maassen@achterhoekvo.nl', '0640947110', '', '', '', '', '2026-03-19 14:55:08', 0, 0, 1, '', '', NULL),
(1223, '123923', '', 'Ad ', 'Wijhe', 'ad@wijne.nl', '', '', '', '', '', '2026-03-19 15:01:39', 0, 0, 1, NULL, NULL, NULL),
(1224, '9909', 'Airborne Malletband Oosterbeek', 'Linda ', 'Hartgers', 'linda_hartgers@hotmail.com', '0654660903', 'Reijmerweg 81', '6871 HC', 'Renkum', '', '2026-03-19 15:11:54', 0, 0, 1, '', '', NULL),
(1225, '9909', 'Aktief werkt', 'Rosalie', 'Scholten', 'Rosalie.Scholten@actiefwerkt.nl', '0623698926', 'Dorpsstraat 71', '8171 BM', 'Vaassen', '', '2026-03-19 15:14:39', 0, 0, 1, '', '', NULL),
(1226, '123737', '', 'Alex', 'van Bergen', 'alexvanbergen888@gmail.com', '', '', '', 'Zwolle', '', '2026-03-19 15:16:15', 0, 0, 1, NULL, NULL, NULL),
(1227, '', '', 'Alfred', 'Brockotter', 'appiez@live.nl', 'appiez@live.nl', '', '', '', '', '2026-03-19 15:20:20', 0, 0, 1, NULL, NULL, NULL),
(1228, '123762', 'Alpina ', 'Joyce ', 'Wentink', 'joyce.wentink@alpina.nl', '+31 (0)314 37 32 60 ', 'Edisonstraat 92', '7006 RE', 'Doetinchem', '', '2026-03-19 15:22:34', 0, 0, 1, NULL, NULL, NULL),
(1229, '9909', '', 'Amanda', 'Sims', 'amandachristinasims@gmail.com', '+44 07837795940', '', '', '', '', '2026-03-19 15:24:24', 0, 0, 1, NULL, NULL, NULL),
(1230, '123677', 'Anacon infra', 'Marloes ', 'Brandenberg', 'm.brandenbarg@anacon-infra.nl', '0545272275', 'Korenbree 34a', '7271 LH', 'Borculo', '', '2026-03-19 15:28:08', 0, 0, 1, NULL, NULL, NULL),
(1231, '9909', '', 'Andre', 'Kulman', 'andre@kulman.nl', '', '', '', '', '', '2026-03-19 15:29:14', 0, 0, 1, NULL, NULL, NULL),
(1232, '', '', 'Angelique', 'Keizer', 'angeliquekeizer@hotmail.com', '0623400111', 'Woudweg 7', '7395 DH', 'Teurge', '', '2026-03-19 15:32:30', 0, 0, 1, NULL, NULL, NULL),
(1233, '', '', 'Angie ', 'en Glenn', 'info@weddingplanningmetcarlijn.nl', '0618048294', 'Torenstraat 151', '3311 TR', 'Dordrecht', '', '2026-03-19 15:34:56', 0, 0, 1, NULL, NULL, NULL),
(1234, '', '', 'Anita', 'Lettink', 'anita@stajawapening.nl', '0612449902', '', '', 'Hengelo GLD', '', '2026-03-19 15:36:09', 0, 0, 1, NULL, NULL, NULL),
(1235, '123761', '', 'Anita', 'Schrijver', 'a.schrijver6@chello.nl', '', '', '', '', '', '2026-03-19 15:49:50', 0, 0, 1, NULL, NULL, NULL),
(1236, '123860', '', 'Anja', 'Heutink', 'huetinkanja@hotmail.com', '', 'Hoevesteeg 2', '6975 AE', 'Tonden', '', '2026-03-19 15:54:20', 0, 0, 1, NULL, NULL, NULL),
(1237, '', 'Anne Flokstraschool VSO', 'Marijne', 'Poppen', 'm.poppen@anneflokstraschool.nl', '0647214315', 'Emmalaan 2', '7204 AS', 'Zutphen', '', '2026-03-19 15:56:30', 0, 0, 1, NULL, NULL, NULL),
(1238, '9909', '', 'Anne Jan ', 'Dragt', 'annejan_dragt@hotmail.com', '0622181569', 'Frans Halslaan 46', '7204 CJ', 'Zutphen', '', '2026-03-19 15:59:50', 0, 0, 1, '', '', NULL),
(1239, '123822', '', 'Anne ', 'Kuiper-Flokstra', 'aflokstra82@hotmail.com', '', '', '', 'Zutphen', '', '2026-03-19 16:01:08', 0, 0, 1, NULL, NULL, NULL),
(1240, '', '', 'Annebet ', 'van Weerelt', 'annebetvanweerelt@gmail.com', '', 'Lortingstrasse 5', '53179', 'Bonn', '', '2026-03-19 16:05:22', 0, 0, 1, NULL, NULL, NULL),
(1241, '9909', '', 'Annelies ', 'Ooms', 'OomsAnnelies@outlook.com', '06 41808921', 'Jufferstraat 322', '3011 XM', 'Rotterdam', '', '2026-03-19 16:07:17', 0, 0, 1, NULL, NULL, NULL),
(1242, '', '', 'Annemarie ', 'Johannes', 'a.johannes@virtask.nl', '0651798956', '', '', 'Zutphen', '', '2026-03-19 16:08:36', 0, 0, 1, NULL, NULL, NULL),
(1243, '123490', '', 'Annemieke ', 'Kusters', 'samen@hetnet.nl', '', '', '', 'Warnsveld', '', '2026-03-19 16:10:16', 0, 0, 1, '', '', NULL),
(1244, '', '', 'Annemieke ', 'Willems', 'info@houseoftweeds.nl', '', '', '', 'Gorssel', '', '2026-03-19 16:11:29', 0, 0, 1, '', NULL, NULL),
(1245, '', '', 'Annie en Chris', 'De Vredenberg ', 'annielocht@msn.com', '0618576592  ', 'Beukenlaan 18', '7223 KL', 'Baak', '', '2026-03-19 16:13:31', 0, 0, 1, '', NULL, NULL),
(1246, '123534', '', 'Anouk', 'Scholten', 'anoukscholten88@gmail.com', '0642077739', '', '', '', '', '2026-03-19 16:14:52', 0, 0, 1, '', NULL, NULL),
(1247, '9909', '', 'Antoine Dielissen', 'Dielissen', 'a.dielissen6@chello.nl', '0625066663', '', '', 'Zutphen', '', '2026-03-19 16:16:06', 0, 0, 1, '', '', NULL),
(1248, '9910', 'MFE', 'Lukas', 'Buis', 'Buis@mfe.nl', '06460008762', 'Loubergweg 7', '6961 EJ', 'Eerbeek', '', '2026-03-20 11:53:19', 0, 0, 1, '', NULL, NULL),
(1249, '9909', 'Apotheek Zutphen', 'Kiem', 'Cornelisse', 'Kiem.cornelisse@gmail.com', '0621947555', 'Coehoornsingel 3-06', '7201 AA', 'Zutphen', '', '2026-03-21 11:47:36', 0, 0, 1, '', NULL, NULL),
(1250, '123631', '', 'Arjan', 'ter Borg', 'arjun.terborg@gmail.com', '', 'Leeuwerikstraat 16', '6971 ZD', 'Brummen', '', '2026-03-21 11:50:18', 0, 0, 1, '', NULL, NULL),
(1251, '123719', 'Arnhemse Broederschap O.L.V.', 'Clemens', 'Zewald', 'c.zewald@outlook.com', '', 'Da Costastraat 10', '6824 NV', 'Arnhem', '', '2026-03-21 11:58:16', 0, 0, 1, '', NULL, NULL),
(1252, '9909', '', 'Arnold ', 'Jansen', 'arnoldjansen@me.com', '0654226768', '', '', '', '', '2026-03-21 12:01:08', 0, 0, 1, '', NULL, NULL),
(1253, '9909', '', 'Arthur', 'de Wild', 'arthurdewild@hotmail.com', '', '', '', 'Zutphen', '', '2026-03-21 12:31:25', 0, 0, 1, '', '', NULL),
(1254, '9909', '', 'Astrid', 'Reuver', 'bdereuver@yahoo.co.uk', '06 50287129', 'Bosweg 20', '2202 NV', 'Noordwijk', '', '2026-03-21 12:36:10', 0, 0, 1, '', '', NULL),
(1255, '9909', '', 'At', 'Flierman', 'a.flierman@concepts.nl', '', '', '', '', '', '2026-03-21 12:37:23', 0, 0, 1, '', '', NULL),
(1256, '9909', 'Atlant Apeldoorn', 'Michelle ', 'van Ham', 'm.van.ham@atlant.nl', '0620175219', 'Koning Lodewijklaan 2', '7314 GD', 'Apeldoorn', '', '2026-03-21 12:39:43', 0, 0, 1, '', '', NULL),
(1257, '540', 'Atlant Beekbergen', 'Denise', 'Brugman', 'd.brugman@atlant.nl', '055 506 72 00', 'Kuiltjesweg 1', '7361 TC ', 'Beekbergen', '', '2026-03-21 12:44:08', 0, 0, 1, '', '', NULL),
(1258, '9910', '', 'August', 'van Engelen', 'zangeraugustvanengelen@gmail.com', '0640644510', 'Het Vlier 82', '7414 AV', 'Deventer', '', '2026-03-21 12:46:42', 0, 0, 1, '', '', NULL),
(1259, '123747', 'Avondvierdaagse Ugchelen', 'Liselore', 'Kaal', 'avondvierdaagse.ugchelen@gmail.com', '0614151953', 'Brouwersmolenweg 440', '7339 EE', 'Ugchelen', '', '2026-03-21 13:11:00', 0, 0, 1, '', '', NULL),
(1260, '9909', 'Bakker Bart Zutphen', 'Angelo ', 'Giezen', 'zutphen.houtmarkt@bakkerbart.nl', '0628264415', 'Houtmarkt 55', '7201 KJ', 'Zutphen', '', '2026-03-22 09:55:24', 0, 0, 1, '', '', NULL),
(1261, '540', 'Bark Verpakkingen B.V.', 'Heidi', 'Arends', 'h.arends@bark-verpakkingen.com', '0313679540', 'Coldenhovenseweg 79', '6961 EC', 'Eerbeek', '', '2026-03-22 09:58:22', 0, 0, 1, '', '', NULL),
(1262, '', '', 'Bart', 'Klunder', 'klunder_bart@hotmail.com', '0612473061', '', '', 'Deventer', '', '2026-03-22 09:59:42', 0, 0, 1, '', '', NULL),
(1263, '9909', '', 'Bart', 'ten Brinke', 'barttenbrinke@hotmail.com', '', '', '', 'Zutphen', '', '2026-03-22 10:00:55', 0, 0, 1, '', '', NULL),
(1264, '9909', '', 'Bas ', 'Addink', 'bas.abbink95@gmail.com', '0657170324', 'enkweg 10', '7251 EW', 'Vorden', '', '2026-03-22 10:02:55', 0, 0, 1, '', '', NULL),
(1265, '', 'Bas Jansen Groente en Fruit', 'Bas', 'Jansen', 'basjansen1965@gmail.com', '0610778283', 'Gravenstraat 2', '6971 AH', 'Brummen', '', '2026-03-22 10:15:03', 0, 0, 1, '', '', NULL),
(1266, '', '', 'Bas', 'Pasman', 'b.pasman@kangaro-ktc.nl', '0683973788', '', '', 'Voorst', '', '2026-03-22 10:16:24', 0, 0, 1, '', '', NULL),
(1267, '123546', '', 'Bas', 'van der Heijden', 'basvanderheijden93@gmail.com', '0653536191', '', '', '', '', '2026-03-22 10:17:51', 0, 0, 1, '', '', NULL),
(1268, '123591', 'Basisschool Antonius de Vecht', 'Ilona ', 'Venema', 'administratie@stmartinus.skbg.nl', '055-3231369', ' Kerkstraat 36', '7396 PH', 'Terwolde', '', '2026-03-22 10:22:17', 0, 0, 1, '', '', NULL),
(1269, '', 'Basisschool De Koperenmolen', 'Chantal', 'Gerritsen - van der Weerden', 'administratie@dekopermolen.skbg.nl', '0553011462', 'Kopermolenweg 8', '7382 BP', 'Klarenbeek', '', '2026-03-22 10:26:08', 0, 0, 1, '', '', NULL),
(1270, '123559', 'Basisschool Ibnisina', 'Mustafa ', 'Eroglu', 'm.eroglu@simonscholen.nl', '(026) 4428599', 'Agnietenstraat 227', '6822 JP', 'Arnhem', '', '2026-03-22 10:30:57', 0, 0, 1, '', '', NULL),
(1271, '655', 'Basisschool St. Joseph', 'Dennis', 'Dijkgraaf', 'd.dijkgraaf@skbg.nl', '0573252439', 'Prins Frisolaan 12A', '7242 GZ', 'Lochem', '', '2026-03-22 10:34:14', 0, 0, 1, '', '', NULL),
(1272, '9909', '', 'Bastiaan', 'Hutten', 'bastiaanhutten@tenkate-deventer.nl', '0570612273', 'Twelloseweg 8', '7419 BJ', 'Deventer', '', '2026-03-22 10:36:40', 0, 0, 1, '', '', NULL),
(1273, '123779', 'Bax advocaten en mediators', 'Annemieke', 'Wiltink', 'a.wiltink@baxadvocaten.nl', '0624539801', 'Edisonstraat 86', '7006 RE', 'Doetinchem', '', '2026-03-22 10:38:57', 0, 0, 1, '', '', NULL),
(1274, '123853', 'Beekpark Tandartsen', 'Mariska', 'Kuiper - Schuurs', 'mschuurs@hotmail.com', '0555224040', 'Roggestraat 158', '7311 CE', 'Apeldoorn', '', '2026-03-22 10:55:49', 0, 1, 1, '', '', NULL),
(1275, '9909', '', 'Bennie', 'Nijkamp', 'bnijkamp432@gmail.com', '', 'Zuidlooerweg 7', '7437 PS', 'Bathmen', '', '2026-03-22 10:59:06', 0, 0, 1, '', '0650855302', NULL),
(1276, '123523', '', 'Bernard', 'Oosterink', 'bernardoosterink@hotmail.com', '0628678231', '', '', 'Deventer', '', '2026-03-22 11:09:10', 0, 0, 1, '', '', NULL),
(1277, '123814', '', 'Bert ', 'Brunnekreef', 'brunnekreefejj@hotmail.com', '06 22203585', 'Leigraaf 51', '7381 BR', 'Klarenbeek', '', '2026-03-22 11:16:45', 0, 0, 1, '', '', NULL),
(1278, '', '', 'Bert', 'Klomphaar', 'bklomphaar@gmail.com', '0653515842', '', '', '', '', '2026-03-22 11:18:20', 0, 0, 1, '', '', NULL),
(1279, '9909', 'Bestuur Oranjever. Empe', 'Gert', 'te Kampe', 'gert@tekampe.com', '0651344553', 'Emperweg 54', '7399 AG', 'Empe', '', '2026-03-22 11:23:28', 0, 1, 1, '', '', NULL),
(1280, '123722', 'Bewonerscommissie Tuinpoort', 'Marten', 'Gils', 'martengits@hotmail.com', '0649910949 ', 'Tuinstraat 108', '6828 BE', 'Arnhem', '', '2026-03-22 12:42:47', 0, 0, 1, '', '', NULL),
(1281, '9909', '', 'Bianca', 'Penterman', 'dennis-bianca@hotmail.nl', '0627679055', '', '', 'Zutphen', '', '2026-03-22 12:45:03', 0, 0, 1, '', '', NULL),
(1282, '', 'Bike Totaal Wolters', 'Ingrid', 'Wolters', 'ingrid@wolterstweewielers.nl', '0553011266', 'Klarenbeekseweg 102', '7381 BG', 'Klarenbeek', '', '2026-03-22 12:50:15', 0, 0, 1, '', '', NULL),
(1283, '', 'Binnenstadsmanagement Zutphen', 'Remco', 'Feith', 'remco@inzutphen.nl', '', 'Houtmarkt 75', '7201 KL', 'Zutphen', '', '2026-03-22 12:53:03', 0, 0, 1, '', '', NULL),
(1284, '9909', 'Blankart & Bronkhorst Netwerk', 'Nanique', 'Fennebeumer', 'n.fennebeumer@bbnwn.nl', '0555063333', 'Wolterbeeklaan 3', '7361 ZD', 'Beekbergen', '', '2026-03-22 12:58:20', 0, 0, 1, '', '', NULL),
(1285, '123874', 'Bleumink Fietsen', 'Ellen', 'Olthof', 'e.olthof@bleuminkfietsen.nl', '', 'De Stoven 25', '7206 AZ', 'Zutphen', '', '2026-03-22 13:00:25', 0, 0, 1, '', '', NULL),
(1286, '123781', 'Bloezem Zorg ', 'Emiel', 'Koeslag', 'emiel.koeslag@bloezem.nl', '0638852699', 'Loolaan 59', '7314 AG', 'Apeldoorn', '', '2026-03-22 13:23:48', 0, 0, 1, '', '', NULL),
(1287, '', '', 'Bo', 'Schutte', 'boschutte@icloud.com', '0620469099', 'Weg naar Laren 72', '7203 HR', 'Zutphen', '', '2026-03-22 13:25:14', 0, 0, 1, '', '', NULL),
(1288, '123585', '', 'Bob', 'pepping', 'bobpepping@gmail.com', '0620215276', '', '', '', '', '2026-03-22 13:29:52', 0, 0, 1, '', '', NULL),
(1289, '', 'Boels Verhuur', 'Rutger', 'Olde Meule', 'Wesley.Wending@boels.nl', '0575 510041', 'Londenstraat 3', '7418 EE', 'Deventer', '', '2026-03-22 13:35:52', 0, 0, 1, '', '', NULL),
(1290, '123916', 'Boerkamp Vee', 'S.', 'Boerkamp', 'info@boerkampvee.nl', '', 'Oud Lochemseweg 40', '7384 DG', 'Wilp', '', '2026-03-22 13:37:24', 0, 0, 1, '', '', NULL),
(1291, '9909', 'Bordex Packaging', 'Bert ', 'Hengeveld', 'bhengeveld@bordex.nl', '06 20424344', 'Schumanpark 67', '7336 AS', 'Apeldoorn', '', '2026-03-22 13:41:51', 0, 0, 1, '', '', NULL),
(1292, '123680', '', 'Bram ', 'Flierman', 'bram83@hotmail.com', '', 'Heggerank 113', '7242 MH', 'Lochem', '', '2026-03-22 13:49:29', 0, 0, 1, '', '', NULL),
(1293, '9909', '', 'Bram ', 'Henekes', 'dekeetmusicenlight@outlook.com', '06 37277765', 'Hoevesteeg 6', '6975 AE', 'Tonden', '', '2026-03-22 13:52:51', 0, 0, 1, '', '', NULL),
(1294, '123811', '', 'Bram ', 'Maandag', 'brammaandag@gmail.com', '0654223742', 'Berkelaarlaan 12', '7004 JJ', 'Doetinchem', '', '2026-03-22 13:54:39', 0, 0, 1, '', '', NULL),
(1295, '123775', '', 'Bram ', 'Scheerder', 'bramscheerder35@gmail.com', '', '', '', 'Zutphen', '', '2026-03-22 13:55:44', 0, 0, 0, '', '', NULL),
(1296, '123845', 'BSO Het Speelkwartier', 'Afd.', 'Administratie', 'bsospeelkwartier@humankind.nl', '', 'Pastoor Bluemersplein 5', '7064 BK', 'Silvolde', '', '2026-03-22 14:05:11', 0, 0, 1, '', '', NULL),
(1297, '123723', 'Brandweerkazerne Garderen', 'Karin', 'Leeuwen', 'karinmulder92@live.nl', '06 25159326', 'Oud Milligenseweg 12', '3886 ME', 'Garderen', '', '2026-03-22 14:10:18', 0, 0, 1, '', '', NULL),
(1298, '123720', 'Brasserie De Zon', 'Lynn', 'van Gessel', 'lynn.van-gessel@sheerenloo.nl', '', 'Regenboogbrink 20', '7325 BA', 'Apeldoorn', '', '2026-03-22 14:12:36', 0, 0, 1, '', '', NULL),
(1299, '', 'Brondby IF Fodbold A/S', 'Kim', 'Vilfort', 'kv@brondby.com', '+4540801716', 'Brandby Stadion 30', 'DK-2605 ', 'Brondby', '', '2026-03-22 14:16:36', 0, 0, 1, '', '', NULL),
(1300, '9909', 'Bryansbrasserie', 'Britte', 'van den Berg', 'brittevandenberg@gmail.com', '', 'Beukerstraat 71', '7201 LC', 'Zutphen', '', '2026-03-22 14:21:35', 0, 0, 1, '', '', NULL),
(1301, '123849', 'BSO in het Wild', 'Joyce', 'Ruysink', 'joyceruysink@gmail.com', '0642569574', 'Boedelhofweg 108', '7211 BT', 'Eefde', '', '2026-03-22 14:34:16', 0, 0, 1, '', '', NULL),
(1302, '9909', 'Buddy to Buddy Bronckhorst', 'Marjolein', 'Jansen', 'marjolein.jansen@me.com', '06 57999627', '', '', '', '', '2026-03-22 14:35:41', 0, 0, 1, '', '', NULL),
(1303, '123780', 'Buitenhuis Recreatie Techniek', 'Jarno', 'Hanekamp', 'jarno@buitenhuisrecreatietechniek.nl', '055-5061492', 'Wilmersdorf 14', '7327 AC', 'Apeldoorn', '', '2026-03-22 14:37:16', 0, 0, 1, '', '', NULL),
(1304, '123524', 'Bus Industrial Tools', 'Anita', 'van de Worp', 'tonny.anita@ziggo.nl', '06-43470503', ' Bohemenstraat 17', '8028 SB', 'Zwolle', '', '2026-03-22 14:40:32', 0, 0, 1, '', '', NULL),
(1305, '123695', 'Buurtvereniging \'t Loar', 'Kelly', 'Overtoom', 'kelly@meering.nl', '0628833960', 'Kluinweideweg 6', '8171 PS', 'Vaassen', '', '2026-03-22 14:44:06', 0, 0, 1, '', '', NULL),
(1306, '9909', 'Café - Restaurant De Duif', 'Willem Paul', 'Bakker', 'Wpbakker@deduifruurlo.nl', '', 'Groenloseweg 57', '7261 RM', 'Ruurlo', '', '2026-03-27 09:04:17', 0, 0, 1, '', '', NULL),
(1307, '9909', 'Camelot Café & Terras', 'Marieke', 'van der Rhee', 'marieke@camelotzutphen.nl', '0610051028', 'Groenmarkt 32', '7201 HZ', 'Zutphen', '', '2026-03-27 09:09:09', 0, 0, 1, '', '', NULL),
(1308, '9909', 'Carara Kreuzfahrten', 'Carola ', 'Apel', 'carola.apel@carara.com', '+4901726644172', 'Neumarkt 14', '04109', 'Leipzig', '', '2026-03-27 09:16:23', 0, 0, 1, '', '', NULL),
(1309, '9909', '', 'Cas', 'Peters', 'caspeters1998@gmail.com', '0623109376', 'Canadasingel 82', '7207 RP', 'Zutphen', '', '2026-03-27 09:28:26', 0, 0, 1, '', '', NULL),
(1310, '9909', 'Catering Zutphen', 'Mirjam ', 'Berntssen', 'info@cateringzutphen.nl', '0575525477', 'Stokebrand 570', '7206 ET', 'Zutphen', '', '2026-03-27 09:35:34', 0, 0, 1, '', '', NULL),
(1311, '9909', '', 'Catriona ', 'Hands', 'catrionahands@gmail.com', '0629628466', 'Prinsen Bolwerk 52-RD', '2011 MC', 'Haarlem', '', '2026-03-27 09:38:41', 0, 0, 1, '', '', NULL),
(1312, '123637', 'CGPA AOCS Nieuw Miligen', 'Cees ', 'Middelkoop', 'famsmidt@kpnmail.nl', '', 'Amersfoortseweg 248', '3888 NS', 'Uddel', '', '2026-03-27 09:59:49', 0, 0, 1, '', '', NULL),
(1313, '566', 'Chamber of Commerce for the Gas Industry ', 'Monika ', 'Sikorska', 'monika.sikorska@igg.pl', '22 631 08 37', 'Kasprzaka 25', '01-224', 'Warsaw, Poland', '', '2026-03-27 14:24:17', 0, 0, 1, '', '', NULL),
(1314, '123907', '', 'Chanel ', 'EL Fouly', 'chanelelfouly@gmail.com', '', 'Savornin Lohmanstraat', '', 'Zutphen', '', '2026-03-27 14:28:20', 0, 0, 1, '', '', NULL),
(1315, '', '', 'Chantal', 'Sueters', 'chantallsueters@gmail.com', '0630984856', 'De Zicht 14', '6971 HR', 'Brummen', '', '2026-03-27 14:29:55', 0, 0, 1, '', '', NULL),
(1316, '123541', '', 'Christa ', 'Duits', 'christaduits@gmail.com', '', '', '', 'Zutphen', '', '2026-03-27 14:36:09', 0, 0, 1, '', '', NULL),
(1317, '123752', '', 'Christina', 'Garssen', 'famgarssen@gmail.com', '0650495954', 'Joppelaan 116', '7015 AJ', 'Joppe', '', '2026-03-27 14:39:33', 0, 0, 1, '', '', NULL),
(1318, '9909', '', 'Clay', 'Havenaar', 'varen@sloepverhuurzutphen.nl', '', '', '', '', '', '2026-03-27 14:51:54', 0, 0, 1, '', '', NULL),
(1319, '123749', 'D.V.S. ( Darts)', 'Jaap', 'Draaijer', 'secretariaat@stedendriehoekdarts.nl', '06-42884873', 'Willem de Merodestraat 22', '6961 GW', 'Eerbeek', '', '2026-03-27 15:30:59', 0, 0, 1, '', '', NULL),
(1320, '123701', '', 'Daan', 'ter Hoven', 'ejh.hoveter@gmail.com', '', '', '', 'Hall', '', '2026-03-27 15:37:23', 0, 0, 1, '', '', NULL),
(1321, '385', 'Daltonsschool de Morgenzon', 'Janneke ', 'Lenssen - Hietkamp', 'jantjehietje@hotmail.com', '06 45254507', 'Landweer 2', '7232 CT', 'Warnsveld', '', '2026-03-27 15:51:59', 0, 0, 1, '', '', NULL),
(1322, '123689', 'Daltonschool de Horst', 'Manon', 'Fiets', 'horst@leerplein055.nl', '0634644960', 'Glazeniershorst 402', '7328 TK', 'Apeldoorn', '', '2026-03-27 15:55:34', 0, 0, 1, '', '', NULL),
(1323, NULL, 'het wolt zeewolde', '', '', '', '', '', NULL, 'zee wolde', NULL, '2026-03-27 15:57:52', 0, 0, 0, NULL, NULL, NULL),
(1324, '123538', '', 'Dave', 'Berkelder', 'dave-crossen@hotmail.com', '0681008869', '', '', '', '', '2026-03-27 15:57:54', 0, 0, 1, '', '', NULL),
(1325, '9909', 'DBS Nettelhorst', 'Petra', 'van Nistelrooij', 'p.vannistelrooij@poolsterscholen.nl', '0573471210', 'Bolksbeekweg 4', '7241 PH', 'Lochem', '', '2026-03-27 16:01:22', 0, 0, 1, '', '', NULL),
(1326, '123856', 'Etty Hillesum Lyceum', 'Harry ', 'Derks', 'h.derks@ehl.nl', '0610706816', 'Het Vlier 1', '7414 AR', 'Deventer', '', '2026-03-27 16:03:05', 0, 0, 1, '', '', NULL),
(1327, '9909', '', 'Frank', 'Hiemstra', 'frank_hiemstra@hotmail.nl', '0652331105', 'Zutphensestraat 106', '6791 ES', 'Brummen', '', '2026-03-27 16:11:39', 0, 1, 1, '', '', NULL),
(1328, '9909', 'De Nassau', 'Arjette', 'Eijlders', 'a.eijlders@denassau.nl', '0620744393', 'De la Reijweg 136', '4818 BA', 'Breda', '', '2026-03-28 13:26:39', 0, 0, 1, '', '', NULL),
(1329, '123927', 'De Lokale Zonnebloem Twello', 'Bert', 'van de Zedde', 'bertvandezedde@gmail.com', '31653678562', '', '', '', '', '2026-03-28 13:52:35', 0, 0, 1, '', '', NULL),
(1330, '9909', 'De Zonnebloem afd. de Hoven', 'Jannie ', 'Veenendaal', 'jannie.veenendaal@planet.nl', '', 'Contrescarp 162', '7202 AE', 'Zutphen', '', '2026-03-28 13:55:53', 0, 0, 1, '', '', NULL),
(1331, '123666', 'De Zonnebloem Apeldoorn West Activiteiten', 'Herma ', 'in t Veld', 'zonnebloemw.activiteiten@gmail.com', '', 'Prins Willem Alexanderlaan 1419', '7312 GA', 'Apeldoorn', '', '2026-03-28 13:58:07', 0, 0, 1, '', '', NULL),
(1332, '123634', 'De Zonnebloem Warnsveld', 'Anita', 'Bombeld', 'zonnebloem@bombeld.nl', '06 20073079', '', '', 'Warnsveld', '', '2026-03-28 14:00:00', 0, 0, 1, '', '', NULL),
(1333, '9909', 'De Wondere Wereld', 'Esma ', 'Demirbas', 'esma@demirbas.nl', '0646990684', 'Zutphenseweg 12', '7251 DK', 'Vorden', '', '2026-03-28 14:29:40', 0, 0, 1, '', '', NULL),
(1334, '123897', 'De Zonnewijzerkring', 'Pieter', 'van den Berg', 'pieter-van-den-berg@xs4all.nl', '', 'Disselhof 66', '7335 AA', 'Apeldoorn', '', '2026-03-28 14:34:19', 0, 0, 1, '', '', NULL),
(1335, '9909', 'V.V. Dierensche Boys ', 'Jasper', 'Budel', 'j.budel3@gmail.com', '0651754789', 'Admiraal Helfrichlaan 95', '6952 GD', 'Dieren', '', '2026-03-28 15:44:10', 0, 0, 1, '', '', NULL),
(1336, '9909', 'Dijkman Bouw b.v.', 'Marjon  ', 'Bannink', 'm.bannink@dijkmanbouw.nl', '0575-522577', 'Lage Weide 29', '7231 NN', 'Warnsveld', '', '2026-03-28 16:12:14', 0, 0, 1, '', '', NULL),
(1337, '123882', 'Dorpsgarage Eerbeek', 'Rein-Jan', 'Koop', 'reinjan@dorpsgarageelshof.nl', '0628203911', 'Coldenhovenseweg 27', '6961 EA', 'Eerbeek', '', '2026-03-28 16:23:14', 0, 0, 1, '', '', NULL),
(1338, '9909', 'DWR Notarissen', 'Cecilia', 'van Schaik', 'vanschaik@dwrnotarissen.nl', '0575584584', 'Piet Heinstraat 1', '7204 JN', 'Zutphen', '', '2026-03-28 16:43:24', 0, 0, 1, '', '', NULL),
(1339, '123461', '', 'Eric', 'Stegeman', 'estegeman10@kpnmail.nl', '0630092776', 'Kerkstraat 3C', '7261 CE', 'Ruurlo', '', '2026-03-29 12:12:58', 0, 0, 1, '', '', NULL),
(1340, '123682', '', 'Edward', 'van Mierlo', 'evanmierlo@gmail.com', '0623247510', '', '', 'Steenderen', '', '2026-03-29 12:17:44', 0, 0, 1, '', '', NULL),
(1341, '9909', '', 'Edwin', 'Beunk', 'edwin_beunk@hotmail.nl', '0642748156', 'Obrechtsingel 16', '5262 HZ', 'Vught', '', '2026-03-29 12:19:31', 0, 0, 1, '', '', NULL),
(1342, '123641', 'Eerste Veluwse Montessori School', 'Nicolet', 'Groeneweg', 'n.groeneweg@stichtingproo.nl', '0643102110', 'Arthur Brietstraat 40', '8072 GZ', 'Nunspeet', '', '2026-03-29 12:23:06', 0, 0, 1, '', '', NULL),
(1343, '9909', 'Eijerkamp Retail Groep', 'Jochem', 'Wendt', 'J.Wendt@eijerkamp.nl', '0575-583600', 'Gerritsenweg 11', '7202 BP', 'Zutphen', '', '2026-03-29 12:36:37', 0, 0, 1, '', '', NULL),
(1344, '123757', 'Elektra B.V.', 'Mark', 'van Emmerik', 'mark@elektrabv.nl', '0578612538', 'Korte Veenteweg 4', '8161 PC', 'Epe', '', '2026-03-29 12:39:15', 0, 0, 1, '', '', NULL),
(1345, '123760', '', 'Ento', 'Lesterhuis', 'ecl83@hotmail.com', '0624241824', '', '', 'Zutphen', '', '2026-03-29 13:07:50', 0, 0, 1, '', '', NULL),
(1346, '123909', '', 'Eric', 'Oosterkamp', 'Eric.oosterkamp@cwend.nl', '0620583014', 'Zwarte Bergweg 5', '7361 AB', 'Lieren', '', '2026-03-29 13:09:52', 0, 0, 1, '', '', NULL),
(1347, '9909', '', 'Erik', 'Harberts', 'erikharberts@gmail.com', '06-41726268', 'Rietbergstraat 74', '7201 GK', 'Zutphen', '', '2026-03-29 13:13:16', 0, 0, 1, '', '', NULL),
(1348, '9909', '', 'Erik', 'Keppels', 'erik_keppels@hotmail.com', '', '', '', '', '', '2026-03-29 13:17:42', 0, 0, 1, '', '', NULL),
(1349, '123588', '', 'Erwin', 'Freriks', 'erwin_f@hotmail.com', '0653626250', '', '', '', '', '2026-03-29 13:20:37', 0, 0, 1, '', '', NULL),
(1350, '9909', 'Event Creators', 'Britt', 'Kienhuis', 'b.kienhuis@eventcreators.nl', '', 'Gunnerstraat 39', '7595 KD', 'Weerselo', '', '2026-03-29 13:46:59', 0, 0, 1, '', '', NULL),
(1351, '9909', 'F. vd Vooren', 'Linsey', 'Kleinbussink', 'l.kleinbussink@fvdvooren.nl', '0639288410', 'Solingenstraat 43', '7421 ZR', 'Deventer', '', '2026-03-29 14:18:53', 0, 0, 1, '', '', NULL),
(1352, '123717', '', 'Fam. ', 'Berger', 'tries.berger85@gmail.com', '0650824472', 'Mackaystraat 34', '7204 JT', 'Zutphen', '', '2026-03-29 14:32:37', 0, 0, 1, '', '', NULL),
(1353, '123777', '', 'Famke', 'Damen', 'famke-damen@hotmail.com', '0611817195', 'Lijsterbeslaan 26', '7121 BS', 'Aalten', '', '2026-03-29 14:34:47', 0, 0, 1, '', '', NULL);
INSERT INTO `klanten` (`id`, `klantnummer`, `bedrijfsnaam`, `voornaam`, `achternaam`, `email`, `telefoon`, `adres`, `postcode`, `plaats`, `notities`, `aangemaakt_op`, `gearchiveerd`, `diesel_mail_gehad`, `is_gecontroleerd`, `email_factuur`, `naam_factuur`, `mobiel`) VALUES
(1354, '123765', '', 'Femke', 'Klaasen', 'femke38b@hotmail.com', '0624190941', 'Berkenlaan', '7204 EC', 'Zutphen', '', '2026-03-29 14:46:00', 0, 0, 1, '', '', NULL),
(1355, '123544', '', 'Ferri', 'de Haan', 'ferri@live.nl', '', '', '', '', '', '2026-03-29 14:48:12', 0, 0, 1, '', '', NULL),
(1356, '9909', 'Flamco imz bv', 'Andre ', 'van der Meer', 'andre.vandermeer@aalberts-hfc.com', '0575595555', 'Nijverheidsweg 5', '8131 TX', 'Wijhe', '', '2026-03-31 10:30:27', 0, 0, 1, '', '', NULL),
(1357, NULL, 'Connecto', 'Afd.', 'Planning', '', '', 'connecto', NULL, '?', NULL, '2026-03-31 11:38:31', 0, 0, 0, NULL, NULL, NULL),
(1358, '9909', 'Flinker', 'Monique', 'Wiederhold', 'Monique.Wiederhold@Flinker.nl', '0881707034 / 0628239', 'Hanzelaan 351', '8017 JM', 'Zwolle', '', '2026-04-03 08:43:22', 0, 0, 1, '', '', NULL),
(1359, '9909', '', 'Floor ', 'Scheperkamp', 'floorscheperkamp@hotmail.com', '0647499009', ' Brederodestraat 17B', '1054 MP', 'Amsterdam', '', '2026-04-03 08:47:15', 0, 0, 1, '', '', NULL),
(1360, '123502', 'Fluent', 'Marcel', 'Grondman', 'velshoeve@gmail.com', '0610221988', '', '', '', '', '2026-04-03 08:48:52', 0, 0, 1, '', '', NULL),
(1361, '123825', 'Fonteyn Outdoor Living Mall', 'Don', 'de Ruyter', 'don@fonteyn.nl', '+31 577 456040', 'Meervelderweg 52', '3888 NK', 'Uddel', '', '2026-04-03 08:54:30', 0, 0, 1, '', '', NULL),
(1362, '', '', 'Frank', 'Looye', 'info@natuurlijkgraniet.nl', '0612550767', 'Eekteweg 5', '7448 PN', 'Haarle (gem. Hellendoorn)', '', '2026-04-03 09:14:05', 0, 0, 1, '', '', NULL),
(1363, '123667', '', 'Frans', 'Clignet', 'fghm.clignet@gmail.com', '', '', '', 'Vaassen', '', '2026-04-03 09:21:04', 0, 0, 1, '', '', NULL),
(1364, '9909', '', 'Frans', 'Velthuis', 'f.velthuis3@upcmail.nl', '0657271713', '', '', '', '', '2026-04-03 09:22:06', 0, 0, 1, '', '', NULL),
(1365, '9909', '', 'Fred', 'Haan', 'janniehaan@gmail.com', '0653215447', 'Breegrave 126', '7231 JJ', 'Warnsveld', '', '2026-04-03 09:24:10', 0, 0, 1, '', '', NULL),
(1366, '', '', 'Freddie', 'Baxter', 'f.baxter@live.nl', '0640892328', 'Nilantstraat 10', '7415 BE', 'Deventer', '', '2026-04-03 09:26:15', 0, 0, 1, '', '', NULL),
(1367, '123520', '', 'Friso', 'Krap', 'friso.krap@icloud.com', '0646422557', '', '', '', '', '2026-04-03 09:27:27', 0, 0, 1, '', '', NULL),
(1368, '9909', '', 'Gamze ', 'Ozcelik', 'gulgamzeozcelik@hotmail.com', '0652626739 ', 'Ruijs de Beerenbrouckstraat 7', '7331 LM', 'Apeldoorn', '', '2026-04-03 09:35:01', 0, 0, 1, '', '', NULL),
(1369, '123653', 'Gravo B.V', 'Richard', 'Pollmann', 'richard@garvo.nl', '0651883101', 'Molenweg 38', '6996 DN', 'Drempt', '', '2026-04-03 09:45:51', 0, 0, 1, '', '', NULL),
(1370, '9909', 'Gasservice Scheerder ', 'Peter', 'Scheerder', 'gasservicescheerder@gmail.com', '0645356176', 'Jo Spierlaan 9', '7207 CW', 'Zutphen', '', '2026-04-03 09:48:56', 0, 0, 1, '', '', NULL),
(1371, '123844', 'Gelders Politie Mannenkoor', 'Rijk', 'van Ank', 'rjvanark@xs4all.nl', '0578615306 / 0653262', 'Eper Veste 14', '8161 AA', 'Epe', '', '2026-04-03 10:03:43', 0, 0, 1, '', '', NULL),
(1372, '9909', '', 'Geke', 'Veldwachter', 'gekeveldwachter@gmail.com', '0610362899', 'Dorpstraat 11', '7437 AJ', 'Bathmen', '', '2026-04-03 10:05:29', 0, 0, 1, '', '', NULL),
(1373, '9909', 'Gemril Pluimveeservice B.V. ', 'Sayit ', 'Yanik', 'nfo@gemril.nl', '0653341862', 'Leemansweg 17', '6827 BX', 'Arnhem', '', '2026-04-03 10:50:50', 0, 0, 1, '', '', NULL),
(1374, '123606', 'Gentiaan College', 'Brigitte', 'Brummel', 'b.brummel@gentiaancollege.nl', '055-3689580', 'Gentiaanstraat 804', '7322 CZ', 'Apeldoorn', '', '2026-04-03 10:54:25', 0, 0, 1, '', '', NULL),
(1375, '9909', '', 'George', 'van Aalst', 'g.vanaalst58@gmail.com', '0652639213', '', '', 'Steenderen', '', '2026-04-03 10:56:41', 0, 0, 1, '', '', NULL),
(1376, '9909', '', 'Gerard', 'Onstenk', 'gerardonstenk@hotmail.com', '0628855109', 'Buitensingel 28', '7204 HD', 'Zutphen', '', '2026-04-03 11:02:09', 0, 0, 1, '', '', NULL),
(1377, '9909', '', 'Gerard', 'Tempelman', 'tempelman59@hotmail.com', '0627304216', '', '', 'Warnsveld', '', '2026-04-03 11:03:54', 0, 0, 1, '', '', NULL),
(1378, '9909', '', 'Gerda ', 'Huiting', 'gerda.huiting@xs4all.nl', '0612051978', 'Rozenstraat 11', '7223 KA', 'Baak', '', '2026-04-03 11:06:49', 0, 0, 1, '', '', NULL),
(1379, '123549', '', 'Gerda', 'Nagtegaal', 'gnagtegaal7@gmail.com', '0648214340', '', '', 'Zutphen', '', '2026-04-03 11:10:16', 0, 0, 1, '', '', NULL),
(1380, '9909', '', 'Gerrit', 'Roeterdink', 'dhg.roeterdink@gmail.com', '0648921088', 'Ewoltstede 39', '7213 TC', 'Gorssel', '', '2026-04-03 11:11:56', 0, 0, 1, '', '', NULL),
(1381, '123928', '', 'Maud', 'Vlogtman', 'maudvlogtman@gmail.com', '0619501945', '', '', 'Vorden', '', '2026-04-03 20:15:10', 0, 1, 1, '', '', NULL),
(1382, '', 'Phlow', 'Mariska Bosch', 'Bosch', 'mariska.bosch@philadelphia.nl', '', 'Ketelboetershoek 56', '7328 JE', 'Apeldoorn', '', '2026-04-03 20:27:55', 0, 1, 1, '', '', NULL),
(1383, '123873', '', 'Marleen Wijnbergen', 'Wijnbergen', 'mleenw@icloud.com', '', '', '', '', '', '2026-04-03 20:34:34', 0, 0, 0, '', '', NULL),
(1384, '9909', '', 'Gerrita', 'Schutte', 'gerritaschutte@gmail.com', '0624231293', 'Van Nagelplein 6', '8011 EB', 'Zwolle', '', '2026-04-04 09:13:48', 0, 0, 1, '', '', NULL),
(1385, '123758', '', 'Gert', 'Post', 'Gwpost51@gmail.com', '', 'Valeriuslaan 59', '7333 EE', 'Apeldoorn', '', '2026-04-04 09:16:12', 0, 0, 1, '', '', NULL),
(1386, '9909', '', 'Gert-Jan', 'Overmeen', 'gjovermeen@gmail.com', '', '', '', 'Laren', '', '2026-04-04 09:17:52', 0, 0, 1, '', '', NULL),
(1387, '123731', '', 'Gertie', 'Tuiler', 'gertietuller@gmail.com', '0648125292', '', '', 'Eefde', '', '2026-04-04 09:19:56', 0, 0, 1, '', '', NULL),
(1388, '9909', 'Gilde Plus', 'Harriet', 'Vet', 'Jetvet@hotmail.com', '0628765684', 'Weissenbruchstraat 301', '2596 GH', 'Den Haag', '', '2026-04-04 09:26:02', 0, 0, 1, '', '', NULL),
(1389, '9909', 'Glaskunstbeurs', 'David Beilen &', 'Mark Waaijenberg', 'info@glaskunstbeurs-zutphen.nl', '0652222352', '', '', 'Zutphen', '', '2026-04-04 09:44:56', 0, 0, 1, '', '', NULL),
(1390, '9909', '', 'Goehwelke', 'Dam', 'goehwelke@hotmail.com', '', '', '', 'Zutphen', '', '2026-04-04 09:46:23', 0, 0, 1, '', '', NULL),
(1391, '123750', '', 'Guy', 'Severin', 'severeinguy@gmail.com', '0613842942', '', '', 'Zutphen', '', '2026-04-04 09:54:58', 0, 0, 1, '', '', NULL),
(1392, '9909', '', 'H.M.', 'Berendsen', 'hberendsen8@chello.nl', '0622865639', 'Da Costastraat 46', '7204 DN', 'Zutphen', '', '2026-04-04 10:00:42', 0, 0, 1, '', '', NULL),
(1393, '9909', 'H.S.T. Europe', 'Maicol', 'Gerritsen', 'maicol@kpnmail.nl', '0630596272', 'Edyweg 24-28', '6956 BB', 'Spankeren', '', '2026-04-04 10:02:15', 0, 0, 1, '', '', NULL),
(1394, '9909', 'Hakselfest', 'Esmee', 'Beltman', 'info@hakselfest.nl', '0683072916', '', '', '', '', '2026-04-04 10:15:10', 0, 0, 1, '', '', NULL),
(1395, '9909', 'Halsfest Laren', 'Bustickets', 'site', 'b@site.nl', '', '', '', 'Klarenbeek', '', '2026-04-04 10:18:25', 0, 0, 1, '', '', NULL),
(1396, '123830', 'Handbal Brummen', 'Nathalie', 'Ellens', 'ledenadministratie@handbal-brummen.nl', '0643562806', 'L.R. Beijenlaan 20', '6971 LE Br', '', '', '2026-04-04 10:21:19', 0, 0, 1, '', '', NULL),
(1397, '9909', '', 'Hanneke', 'Koning', 'hanneke.koning@oosterberg.nl', '0626123237', '', '', '', '', '2026-04-04 10:22:38', 0, 0, 1, '', '', NULL),
(1398, '9909', '', 'Hans', 'Adema', 'hans.adema@xs4all.nl', '06 51342421', 'Halvemaanstraat 4', '7201 BS', 'Zutphen', '', '2026-04-04 10:26:21', 0, 0, 1, '', '', NULL),
(1399, '9909', '', 'Hans', 'Cornelisse', 'kiem.cornelisse@gmail.com', '0623291476', 'Gerard Doustraat 105', '7204 EW', 'Zutphen', '', '2026-04-04 10:29:53', 0, 0, 1, '', '', NULL),
(1400, '9909', '', 'Hans en Marinka', 'van de Kamp', 'marinkie_1@msn.com', '06-29170797', 'Rebergerhof 3', '3907 JV', 'Veenendaal', '', '2026-04-04 10:35:19', 0, 0, 1, '', '', NULL),
(1401, '9909', '', 'Hans Kamperman en', 'Jeannette Christiaanse', 'j.m.t.christiaanse@xs4all.nl', '0622697860', 'Koolwitjesstraat 7', '2805 KN', 'Gouda', '', '2026-04-04 10:38:57', 0, 0, 1, '', '', NULL),
(1402, '123692', 'Happy Horizon', 'Shanna', 'Drost', 'shannadrost@happyhorizon.com', '055 - 538 04 60', 'Nieuwe Stationsstraat 20', '6811 KS', 'Arnhem', '', '2026-04-04 12:11:27', 0, 0, 1, '', '', NULL),
(1403, '9909', '', 'Harm', 'Borninkhof', 'h.borninkhof@gmail.com', '', 'Mons. Lelystraat 8', '7204 LJ', 'Zutphen', '', '2026-04-04 12:13:17', 0, 0, 1, '', '', NULL),
(1404, '9909', 'Harmonie Onderling Genoegen', 'Theo', 'Ketelaar', 'THJKetelaar1952@kpnmail.nl', '0613016049', 'Dorpsstraat 3', '7075 AC', 'Etten', '', '2026-04-04 12:15:01', 0, 0, 1, '', '', NULL),
(1405, '9909', '', 'Harry', '(Viking Noorwegen)', 'post@fjellstova.no', '', '', '', '', '', '2026-04-04 12:17:38', 0, 0, 1, '', '', NULL),
(1406, '9909', '', 'Harry', 'Massink', 'harry@kleinhekkelder.nl', '0620336857', 'Emperweg 94', '7399 AJ', 'Empe', '', '2026-04-04 12:21:32', 0, 0, 1, '', '', NULL),
(1407, '123917', '', 'Hein', 'Hoefsloot', 'heinhoefsloot@hotmail.com', '0621103936', '', '', '', '', '2026-04-04 12:40:55', 0, 0, 1, '', '', NULL),
(1408, '9909', '', 'Hein', 'Kokke', 'h.kokke@live.nl', '06-28128227', '', '', '', '', '2026-04-04 12:41:51', 0, 0, 1, '', '', NULL),
(1409, '9909', '', 'Henk W.', 'Pleijsier', 'hw.pleijsier@bre.nl', '06-33774353', 'Westerkaai 2', '8281 BG', 'Genemuiden', '', '2026-04-04 12:45:48', 0, 0, 1, '', '', NULL),
(1410, '123626', '', 'Hennie', 'Bosveld', 'hennieboesveld@gmail.com', '0629169756', 'Schurinklaan 39', '7211 DD', 'Eefde', '', '2026-04-04 12:50:06', 0, 0, 1, '', '', NULL),
(1411, '123598', 'Herber Het Oude Loo', 'Luc', '.', 'Luc@herberghetoudeloo.com', '0624461049 ', 'Piet Joubertstraat 14', '7315 AV', 'Apeldoorn', '', '2026-04-04 13:00:05', 0, 0, 1, '', '', NULL),
(1412, '10084', 'Kiezebrink', 'Jaap', 'Kiezebrink', 'info@taxiberkout.nl', '', 'Van Dorenborchstraat 117', '7203', 'Zutphen', '', '2026-04-09 22:05:21', 0, 0, 0, '', '', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klant_afdelingen`
--

CREATE TABLE `klant_afdelingen` (
  `id` int(11) NOT NULL,
  `klant_id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klant_afdelingen`
--

INSERT INTO `klant_afdelingen` (`id`, `klant_id`, `naam`) VALUES
(1, 90, 'Elftal 1'),
(2, 90, 'Elftal 2'),
(4, 94, 'AZC 1');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `klant_contactpersonen`
--

CREATE TABLE `klant_contactpersonen` (
  `id` int(11) NOT NULL,
  `klant_id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefoon` varchar(50) DEFAULT NULL,
  `functie` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `klant_contactpersonen`
--

INSERT INTO `klant_contactpersonen` (`id`, `klant_id`, `naam`, `email`, `telefoon`, `functie`) VALUES
(3, 5, 'S.A.M.Boerkamp', 'info@boerkampvee.nl', '', NULL),
(5, 6, 'Jantje', 'info@taxiberkhout.nl', '069939839028', NULL),
(6, 2, 'Fred', '', '', NULL),
(7, 4, 'Fred', 'info@taxiberkhout.nl', 'Stravi', NULL),
(16, 167, 'Marleen Klein Rensink', 'marleenkleinrensink@archipelprimair.nl', '0622775814', NULL),
(19, 1215, 'Richard Liebregt', 'r.liebregt@obspwa.nl', '', NULL),
(29, 1233, 'Carlijn van Broekhoven', 'info@weddingplanningmetcarlijn.nl', '0618048294', NULL),
(54, 211, 'Karin ', 'soe@isendoorn.nl', 'Soeteman', NULL),
(55, 211, 'Laura Venhorst', 'vnl@isendoorn.nl', '', NULL),
(56, 211, 'Alexandra van der Velden', 'ab@isendoorn.nl', '0628047407', NULL),
(57, 211, 'Esther Cornelissen', 'esthercornelissen@hotmail.com', '0628185156', NULL),
(58, 211, 'Dhr. Leisink', 'lei@isendoorn.nl', '', NULL),
(59, 211, 'Mvr. Leuvenink', 'lvi@isendoorn.nl', '', NULL),
(60, 211, 'Dhr. Huiskamp', 'hsp@isendoorn.nl', '06-10404100', NULL),
(61, 211, 'Siebe Hendriksen', 'hns@isendoorn.nl', '06-37 45 22 94', NULL),
(62, 211, 'Caroline Klaren', 'klr@isendoorn.nl', '0615536324', NULL),
(63, 211, 'Marjolein Biesterbos', 'bbm@isendoorn.nl', '0644124970', NULL),
(64, 211, 'Ivo Langes', 'bkt@isendoorn.nl', '', NULL),
(65, 211, 'Dhr. van Wordragen', 'wrw@isendoorn.nl', '0645108035', NULL),
(66, 211, 'Sophie Thomeas', 'tms@isendoorn.nl', '', NULL),
(67, 211, 'Erik Willemse', 'wle@isendoorn.nl', '', NULL),
(68, 211, 'Dhr. Maas', 'mas@isendoorn.nl', '', NULL),
(69, 211, 'Jurgen jeninga', 'jnj@isendoorn.nl', '', NULL),
(70, 555, 'Rita Bettink', 'rita.bettink@gmail.com', '0625455913', NULL),
(71, 555, 'Annemiek Bakker', 'a.bakker@skbg.nl', '', NULL),
(72, 555, 'Esther Hendriksen', 'E.Hendriksen@skbg.nl', '', NULL),
(73, 555, 'Heleen Doorn', 'h.doorn@antonius.skbg.nl', '0553231369', NULL),
(74, 555, 'Jasmijn Gerrits', 'J.Gerrits@skbg.nl', '', NULL),
(75, 601, 'Gijs ', 'rijkspost@kickmail.nl', 'Rijks', NULL),
(76, 983, 'Frank de Vries', 'f.devries@sotog.nl', '0651424329', NULL),
(77, 1248, 'Lukas Buis', 'Buis@mfe.nl', '06460008762', NULL),
(78, 1248, 'Rob Heersema', 'heersema@mfe.nl', '0646008752', NULL),
(95, 1053, 'Harry de Jong', 'harry.dejong@arriva.nl', '0623368246', NULL),
(96, 1053, 'Fokke-Jan Bos', 'fokkejan.bos@arriva.nl', '0614555589', NULL),
(97, 1053, 'Eddy Menkveld', 'eddy.menkveld@arriva.nl', '06 528 23 711', NULL),
(98, 1053, 'Nicole van der Zwet', 'nicole.vanderzwet@arriva.nl', '0654747276', NULL),
(101, 548, 'Ria Engel', 'r.engel@aventus.nl', '0634512718', NULL),
(102, 548, 'Theo Jansen', 'thjansen@planet.nl', '0625543801', NULL),
(113, 522, 'Esmaralda', 'derk@aandewiel.info', '0646054821', NULL),
(114, 583, 'Michiel v.d. Beek', 'm.vanderbeek@actiefzutphen.nl', '0657882387', NULL),
(115, 583, 'Stijn Koolschijn', 's.koolschijn@actiefzutphen.nl', '0644442328', NULL),
(116, 901, 'Edwin Verhaagen', 'erverhaagen@gmail.com', '0618477022', NULL),
(117, 901, 'Danielle Smeerdijk', 'danielle.smeerdijk@hotmail.com', '0647380126', NULL),
(118, 901, 'Ilse Dijks', 'ilsedijks@hotmail.com', '0613256230', NULL),
(119, 228, 'Rens van Velden', 'rens@ahorn-bouwsystemen.nl', '0653748426', NULL),
(120, 228, 'Jeroen van Zeijts', 'jeroen@ahorn-bouwsystemen.nl', '0575544941', NULL),
(121, 987, 'EvB', 'e.v.b@chello.nl', '0645620608', NULL),
(122, 1057, 'Nienke Klooster', 'nienkeklooster@archipelprimair.nl', '0575571267', NULL),
(123, 940, 'Constant Barendsen', 'c.barendsen.sr@gmail.com', '0626370545', NULL),
(124, 933, 'Liesbeth Bakker', 'liesbeth@bakker.nl', '0653170043', NULL),
(125, 933, 'Jeroen Geubels', 'jeroen@bakker.nl', '', NULL),
(126, 933, 'Aniek Sevink', 'anieksevink@gmail.com', '0613212857', NULL),
(127, 463, 'Jordy Jolink', 'j.jolink@jolinkbanket.nl', '0683941663', NULL),
(128, 463, 'Eva Verbeek', 'eva.verbeek85@gmail.com', '', NULL),
(129, 1056, 'Rik Vaartjes', 'rik.vaartjes@kpnplanet.nl', '', NULL),
(131, 1269, 'Roos Achterkamp', 'r.achterkamp@skbg.nl', '', NULL),
(132, 1271, ' Sanne Beekman', 'or@stjoseph.skbg.nl', '', NULL),
(133, 248, 'Tristan Wesselink', 'info@bedshop.nl', '0614340506', NULL),
(135, 1279, 'Annemiek Hemmink', 'hulshofannemiek@hotmail.com', '', NULL),
(136, 502, 'Ilse Winnemuller', 'i.winnemuller@betuwe-express.nl', '', NULL),
(137, 502, 'Tom te Brake', 'T.teBrake@betuwe-express.nl', '0488468610', NULL),
(138, 1282, 'Ingrid Wolters', 'ingrid@wolterstweewielers.nl', '0612992662', NULL),
(139, 1283, 'Martijn Droog', 'info@notenenzo.nl', '0624909164', NULL),
(140, 888, 'Jan Timmermans', 'jan@boatbiketours.com', '0207235469', NULL),
(141, 1125, 'Gerdie Wendt - Oostenenk', 'jeroenengerdie@chello.nl', '0575 494206', NULL),
(142, 199, 'Geeske van Apseren', 'gvanasperen@gmail.com', '06 44503177', NULL),
(143, 199, 'Geeske van Apseren', 'ordekleinewereld@gmail.com', '06 44503177', NULL),
(144, 199, 'Joyce Vreulink', 'joycevreulink91@hotmail.com', '06 14860583', NULL),
(145, 465, 'Afd. inkoop', 'facturenbusreizen@brookhuisgroep.nl', '', NULL),
(146, 1043, 'Geeske van Apseren', 'gvanasperen@gmail.com', '0644503177', NULL),
(147, 1043, 'Joyce Vreulink', 'joycevreulink91@hotmail.com', '06-14860583', NULL),
(148, 152, 'Marty Foget', 'martyfoget@gmail.com', '', NULL),
(149, 1117, 'Marty Foget', 'martyfoget@gmail.com', '', NULL),
(150, 606, 'Dennis Dijkgraaf', 'd.dijkgraaf@skbg.nl', '0573252439', NULL),
(151, 1135, 'Peter Onstenk', 'peteronstenk1@hotmail.com', '0622687351', NULL),
(152, 1135, 'Jurgen van Aalst', 'jurgenvanaalst@hotmail.nl', '0651624999', NULL),
(153, 1135, 'Menno Brummelman', 'mennobrummelman@hotmail.com', '', NULL),
(154, 190, 'Ellen Barink', 'e.barink@skbg.nl', '0648761713', NULL),
(155, 526, 'Laura Westerink', 'info@bookabus.nl', '', NULL),
(156, 626, 'Esther Hendriksen', 'e.hendriksen@skbg.nl', '', NULL),
(157, 626, 'Ilona Venema', 'administratie@stmartinus.skbg.nl', '055-3231369', NULL),
(158, 1268, '	 Esther Hendriksen', 'e.hendriksen@skbg.nl', '', NULL),
(159, 746, 'Jeanette Damen-Meijerink', 'berkelbrug@hetnet.nl', '', NULL),
(162, 1167, 'Minke Obdeijn', 'cvdegorsselnarren@gmail.com', '', NULL),
(163, 1131, 'Renske Jurriens', 'renskejurriens@gmail.com', '0575540100', NULL),
(164, 1131, 'Pascal', 'info@grandcafepierrot.nl', '', NULL),
(165, 872, 'Willem Jansen', 'info@despaan.com', '0641239613', NULL),
(166, 1310, 'Mirjam Berntssen', 'info@cateringzutphen.nl', '0630930805', NULL),
(170, 776, 'Gerlin Pleysier', 'gerlin.pleysier@pcbo-rheden.nl', '', NULL),
(171, 776, 'Tamara Budel', 'Tamara844@msn.com', '0648379077', NULL),
(172, 776, 'Mirjam Cuijpers', 'cuijpers_47@hotmail.com', '', NULL),
(173, 173, 'Monique de Blieck', 'M.deBlieck@combigrohelmink.nl', '0616479492', NULL),
(174, 173, 'Anton van der Kamp', 'anton@combigro.nl', '0655190570', NULL),
(175, 878, 'Marc Brink', 'marc@pouwvervoer.nl', '0614311117', NULL),
(176, 499, 'Marga IJsselstein', 'receptie@coulisse.com', '0547855555', NULL),
(177, 1174, 'Vera Koning', 'verakoning@archipelprimair.nl', '0610059460', NULL),
(178, 1174, 'Erna Kaatman', 'ernakaatman@archipelprimair.nl', '', NULL),
(179, 576, 'Wouter Zomer', 'wouterzomer@archipelprimair.nl', '0681723027', NULL),
(180, 576, 'Mariska Oosterhof', 'or.leadasberg@archipelprimair.nl', '', NULL),
(181, 1321, 'Kim Rutten', 'Kimrutten@chello.nl', '0628424439', NULL),
(182, 1322, 'Femke van Kuilenburg ', 'fvankuilenburg@leerplein055.nl', '', NULL),
(183, 380, 'Celly Luijkman', 'Celly.Luijkman@CadacEurope.com', '0263197740', NULL),
(186, 1019, 'Ellen Gouderjaan', 'ej.gouderjaan@belastingdienst.nl', '0646383794', NULL),
(187, 1019, 'Betty Pelamonia', 'bj.pelamonia-pelupessy@belastingdienst.nl', '', NULL),
(190, 594, 'Natalie', 'natalie.cassee@dbpe.nl', '0577723136', NULL),
(191, 383, 'Marten Klomp', 'cvdegorsselnarren@gmail.com', '0618642227', NULL),
(192, 383, 'Minke Obdeijn', 'cvdegorsselnarren@gmail.com', '', NULL),
(195, 1194, 'Henk Vleems', 'famvleems12@hetnet.nl', '', NULL),
(196, 1194, 'Hans Adema', 'ademahans@gmail.com', '', NULL),
(197, 750, 'Henk Assinck', 'hassinck@vszutphen.nl', '0648085548', NULL),
(198, 255, 'Maartje Hanhart', 'maartjehanhart@gmail.com', '', NULL),
(199, 255, 'branko van der gugten', 'bvdgugten@vszutphen.nl', '0625105167', NULL),
(200, 1099, 'Erik Gorter', 'egorter@vszutphen.nl', '0638331622', NULL),
(201, 1099, 'Veron v Unen', 'vvunen@vszutphen.nl', '', NULL),
(202, 1099, 'Merel Bunnekreef', 'mbrunnekreef@vszutphen.nl', '0620994322', NULL),
(203, 1099, 'Steven Csupor', 'scsupor@vszutphen.nl', '', NULL),
(204, 1099, 'Joeri Bonsel', 'jbonsel@vszutphen.nl', '0647957322', NULL),
(205, 1099, 'Joep van den Dool', 'jvddool@vszutphen.nl', '0651363365', NULL),
(206, 1099, 'Marijn Scholte', 'mscholte@vszutphen.nl', '0648084941', NULL),
(207, 1099, 'Pim Gerritsen', 'pgerritsen@vszutphen.nl', '', NULL),
(208, 1099, 'Maaike Ter Harmsel', 'mterharmsel@vszutphen.nl', '0622119663', NULL),
(209, 1099, 'Bart Pinkster', 'bpinkster@vszutphen.nl', '', NULL),
(210, 512, 'Monique Wijgman', 'm.wijgman@sensire.nl', '', NULL),
(211, 635, 'Arina Jansen', 'denajansen373@gmail.com', '', NULL),
(212, 635, 'Rachel Verhaegh', 'rachelverhaegh96@gmail.com', '', NULL),
(213, 635, 'Marinka Heijink', 'marinka.heijink@denbouw.net', '', NULL),
(214, 1335, 'Remco Zegers', 'r_zegers@hotmail.com', '0613461327', NULL),
(215, 922, 'Rene Hieselaar', 'g.behet@kinderopvangdikkertjedap.nl', '0616459367', NULL),
(216, 922, 'Linsey Abbink', 'linsey@kinderopvangdikkertjedap.nl', '0857733752 ', NULL),
(217, 922, 'Daan Borgonjen', 'daan@kinderopvangdikkertjedap.nl', '0857733752', NULL),
(218, 1196, 'Sophie ten Voorde', 'sophietenvoorde@hotmail.com', '0636531734', NULL),
(219, 851, 'J. Remmelink', 'j.remmelink@dcfl.nl', '0651527107', NULL),
(220, 1156, 'Dorothe Wessel', 'Dorothe.Wessel@ecolab.com', '0620226331', NULL),
(221, 1156, 'Patrizia Deriu', 'Patrizia.Deriu@ecolab.com', '0610635664', NULL),
(223, 965, 'Esther Baart', 'e.walle74@gmail.com', '', NULL),
(224, 613, 'Leny Boekholt', 'leny.boekholt@achterhoekvo.nl', '0575590940', NULL),
(225, 90, ' Evert Koster-Jos FC Zutphen', 'daneve@kpnmail.nl', ' 0650211621 - 0629494273', NULL),
(226, 90, 'Hans Nijland', 'Hans.Nijland@fczutphen.nl', '', NULL),
(227, 90, 'Gerard Greven', 'gerard@toernooivoetbal.nl', '0651171413', NULL),
(228, 90, 'G. Wullink', 'Gerrit.Wullink@dana.com', '0617240894', NULL),
(229, 90, 'Daniella Vermeer', 'daniella.vermeer@fczutphen.nl', '06-51384298', NULL),
(230, 90, 'Frank de Vries', 'f.devries@sotog.nl', '0545-272259 - 0651424329', NULL),
(231, 1080, 'Linda Klaasen', 'lklaasen@ferrocal.nl', '06-16410857', NULL),
(232, 8, 'Fred', 'info@berkhoutreizen.nl', 'Stravers', NULL),
(233, 1356, 'Andre van der Meer', 'andre.vandermeer@aalberts-hfc.com', ' 0655261194', NULL),
(234, 984, 'Willem', 'eetcafe.dehoek@gmail.com', '0620534640', NULL),
(235, 984, 'Mike Ankersmit', 'mike.gasterijdehoek@gmail.com', '0628231101', NULL),
(236, 191, 'Arne Heitmeijer', 'info@for-ward.nl', '', NULL),
(237, 543, 'T.a.v. FactorWerk, mevr. K. Ünlütürk', 'facturen@apeldoorn.nl', '', NULL),
(238, 230, 'Quincy Denkers', 'q.denkers@bronckhorst.nl', '0575750250', NULL),
(239, 855, 'Vera dos Santos Costa Lima', 'V.dosSantos@zutphen.nl', '', NULL),
(240, 855, 'Willie Jebbink', 'w.jebbink@zutphen.nl', '0651163842', NULL),
(241, 855, 'Janet Lier', 'j.lier@zutphen.nl', '0681206564', NULL),
(242, 855, 'Willemien Verlaan ', 'W.Verlaan@zutphen.nl', '0681426100', NULL),
(243, 855, 'Nancy Hamer ', 'FijnZutphen@zutphen.nl', '0628290867', NULL),
(244, 1374, ' Renske Breuer', 'r.breuer@gentiaancollege.nl', '055 368 95 80', NULL),
(245, 1374, 'Juanita Mulder', 'j.mulder@gentiaancollege.nl', '0618126518', NULL),
(246, 949, 'Roy de Bruin', 'roybruin536@gmail.com', '', NULL),
(247, 949, 'Anja Heitink', 'roybruin536@gmail.com', '', NULL),
(248, 979, 'Han Christiaan Brinkman', 'hancbrinkman@icloud.com', '0635312881', NULL),
(249, 979, 'Wico Mulder', 'wicomulder@me.com', '0624600267', NULL),
(250, 1382, 'Mariska Bosch', 'mariska.bosch@philadelphia.nl', '(06) 46893447 ', NULL),
(251, 1382, 'Mariska Bosch', '', '(06) 25294824', NULL),
(252, 1383, 'Marleen Wijnbergen', 'mleenw@icloud.com', '', NULL),
(254, 1092, 'M. Engberts', 'm.engberts@ggnet.nl', '', NULL),
(255, 1138, 'Mark Heuvelink', 'm.heuvelink@glans-bv.nl', '', NULL),
(256, 1138, 'Tonny Hulleman', 'object @glans-bv.nl', '', NULL),
(257, 1138, 'Rob Peppelenbos', 'rob@glans.nl', '0611276600', NULL),
(258, 523, 'Monique Spekking', 'm.spekking@graafschapcollege.nl', '', NULL),
(259, 1211, 'Henk van den Berg', 'henk.vdberg@hetnet.nl', '0653454440', NULL),
(260, 1211, 'Frans Hummelink', 'hummelink41@gmail.com', '0618152462 ', NULL),
(261, 237, 'Renske Jurriens', 'renskejurriens@gmail.com', '0575540100', NULL),
(262, 659, 'Mvr. Paalman', 'facturen@hotelsgravenhof.nl', ' 0575596898', NULL),
(263, 659, 'Bob Olde Olthof', 'bob@hampshire-hotels.com', '0885220201', NULL),
(264, 659, 'Marjolein Brinkman', 'marjolein@hotelsgravenhof.nl', '+31 (0575) 59 68 68', NULL),
(265, 659, 'Tim Olde Olthof', 'tim@avenarius.nl', '0573451122', NULL),
(266, 657, 'Arnold Labohm', 'Arnold@avenarius.nl', '0629011074', NULL),
(267, 1398, '	 Bart Adema', 'info@adema-sleutelspecialist.nl', '06 51342421', NULL),
(268, 1401, 'Jeannette', '', '0646386704', NULL),
(269, 1401, 'Marleen Kamerman ceremoniemeester', '', '0633136626', NULL),
(270, 333, 'Roy Tazelaar ', 'roytazelaar67@outlook.com', '0651318511', NULL),
(271, 955, 'Marjolein Duenk', 'touringcar@hartemink.nl', '', NULL),
(272, 955, 'Patrick Hartemink', 'touringcar@hartemink.nl', '', NULL),
(273, 955, 'Mariska Winnemuller', 'touringcar@hartemink.nl', '', NULL),
(274, 955, 'Robert Oplaat', 'Robert@hartemink.nl', '0630302062', NULL),
(275, 1054, 'Frans Hummelink', 'hummelink41@gmail.com', '0618152462 ', NULL),
(285, 94, 'Gerton Damen', 'penningmeester@azczutphen.nl', '06-44564410', NULL),
(286, 94, 'Anno Waterlander', 'a.waterlander@zonnet.nl', '06-51614000', NULL),
(287, 94, 'Patrick Govaers', 'voorzitter@azczutphen.nl', '', NULL),
(288, 94, 'Ineke Zondervan', 'f.zondervan@kpnmail.nl', '06-17423569', NULL),
(289, 94, 'Jacoo Verstraten', 'jaccoverstraten@live.nl', '0643282613', NULL),
(290, 94, 'Mirjam van der Kwast-Deksen', 'mirjam.vdkwast@gmail.com', '0613373720', NULL),
(291, 94, 'Antoine van der Helm', 'antoine@helmsdeep.nl', '0655783147', NULL),
(292, 94, 'Arthur de Wild', 'arthurdewild@hotmail.com', '', NULL),
(293, 94, 'Jimmy Joseph', 'joseph_kids@hotmail.com', '06 21 35 16 54', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `loon_uren`
--

CREATE TABLE `loon_uren` (
  `id` int(11) NOT NULL,
  `chauffeur_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `type_vervoer` enum('Groepsvervoer','OV','Onbekend') DEFAULT 'Onbekend',
  `van_a` varchar(5) DEFAULT NULL,
  `tot_a` varchar(5) DEFAULT NULL,
  `van_b` varchar(5) DEFAULT NULL,
  `tot_b` varchar(5) DEFAULT NULL,
  `van_c` varchar(5) DEFAULT NULL,
  `tot_c` varchar(5) DEFAULT NULL,
  `uren_basis` decimal(5,2) DEFAULT 0.00,
  `toeslag_avond` decimal(5,2) DEFAULT 0.00,
  `toeslag_weekend` decimal(5,2) DEFAULT 0.00,
  `toeslag_zon_feest` decimal(5,2) DEFAULT 0.00,
  `toeslag_ov_avond_nacht` decimal(5,2) DEFAULT 0.00,
  `toeslag_ov_zaterdag` decimal(5,2) DEFAULT 0.00,
  `toeslag_ov_zondag` decimal(5,2) DEFAULT 0.00,
  `onderbreking_aantal` int(11) DEFAULT 0,
  `notities` text DEFAULT NULL,
  `status` enum('Concept','Goedgekeurd','Verwerkt') DEFAULT 'Concept',
  `toegevoegd_op` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `loon_uren`
--

INSERT INTO `loon_uren` (`id`, `chauffeur_id`, `datum`, `type_vervoer`, `van_a`, `tot_a`, `van_b`, `tot_b`, `van_c`, `tot_c`, `uren_basis`, `toeslag_avond`, `toeslag_weekend`, `toeslag_zon_feest`, `toeslag_ov_avond_nacht`, `toeslag_ov_zaterdag`, `toeslag_ov_zondag`, `onderbreking_aantal`, `notities`, `status`, `toegevoegd_op`) VALUES
(1, 3, '2025-12-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(2, 3, '2025-12-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(3, 3, '2025-12-03', 'OV', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(4, 3, '2025-12-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(5, 3, '2025-12-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(6, 3, '2025-12-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(7, 3, '2025-12-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(8, 3, '2025-12-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(9, 3, '2025-12-11', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(10, 3, '2025-12-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(11, 3, '2025-12-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(12, 3, '2025-12-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(13, 3, '2025-12-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(14, 3, '2025-12-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(15, 3, '2025-12-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 2.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(16, 3, '2025-12-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(17, 3, '2025-12-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 11.75, 2.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(18, 3, '2025-12-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(19, 3, '2025-12-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(20, 3, '2025-12-29', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(21, 3, '2025-12-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(22, 3, '2025-12-31', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(23, 4, '2025-12-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.50, 0.00, 7.50, 2.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(24, 5, '2025-12-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(25, 5, '2025-12-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(26, 5, '2025-12-07', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 8.25, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(27, 5, '2025-12-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(28, 5, '2025-12-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(29, 5, '2025-12-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(30, 6, '2025-12-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(31, 6, '2025-12-11', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(32, 6, '2025-12-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 14.25, 0.00, 0.00, 14.25, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(33, 7, '2025-12-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(34, 7, '2025-12-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(35, 7, '2025-12-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(36, 8, '2025-12-13', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 12.50, 0.00, 12.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(37, 8, '2025-12-21', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 2.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(38, 9, '2025-12-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(39, 9, '2025-12-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(40, 9, '2025-12-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(41, 9, '2025-12-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(42, 9, '2025-12-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(43, 9, '2025-12-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(44, 9, '2025-12-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(45, 9, '2025-12-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(46, 9, '2025-12-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(47, 9, '2025-12-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(48, 9, '2025-12-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(49, 9, '2025-12-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(50, 9, '2025-12-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:17:11'),
(51, 9, '2025-12-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(52, 9, '2025-12-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(53, 10, '2025-12-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.75, 3.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(54, 10, '2025-12-29', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:17:11'),
(55, 3, '2026-01-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(56, 3, '2026-01-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(57, 3, '2026-01-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(58, 3, '2026-01-07', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(59, 3, '2026-01-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(60, 3, '2026-01-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(61, 3, '2026-01-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(62, 3, '2026-01-13', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(63, 3, '2026-01-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(64, 3, '2026-01-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(65, 3, '2026-01-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(66, 3, '2026-01-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(67, 3, '2026-01-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(68, 3, '2026-01-21', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(69, 3, '2026-01-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(70, 3, '2026-01-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(71, 3, '2026-01-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(72, 3, '2026-01-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(73, 3, '2026-01-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(74, 3, '2026-01-29', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(75, 3, '2026-01-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(76, 4, '2026-01-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(77, 4, '2026-01-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(78, 4, '2026-01-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 9.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(79, 5, '2026-01-07', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-02-24 13:43:48'),
(80, 5, '2026-01-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(81, 5, '2026-01-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(82, 5, '2026-01-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(83, 5, '2026-01-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(84, 5, '2026-01-21', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(85, 5, '2026-01-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(86, 5, '2026-01-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(87, 6, '2026-01-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(88, 6, '2026-01-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 8.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(89, 6, '2026-01-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(90, 6, '2026-01-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 13.25, 0.00, 0.00, 13.25, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(91, 6, '2026-01-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(92, 6, '2026-01-29', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(93, 6, '2026-01-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(94, 6, '2026-01-31', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(95, 7, '2026-01-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(96, 7, '2026-01-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:43:48'),
(97, 7, '2026-01-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-02-24 13:43:48'),
(141, 11, '2026-01-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 11.75, 0.00, 0.00, 11.75, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:51:26'),
(142, 11, '2026-01-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:51:26'),
(143, 12, '2026-01-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:51:26'),
(144, 12, '2026-01-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 8.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:51:26'),
(145, 12, '2026-01-31', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-02-24 13:51:26'),
(241, 9, '2026-01-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Hele maand ziek', 'Concept', '2026-02-24 15:05:56'),
(247, 14, '2026-01-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-02-24 16:34:06'),
(248, 14, '2026-01-07', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-02-24 16:34:52'),
(249, 14, '2026-01-08', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'reiskosten € 115', 'Concept', '2026-02-24 16:35:06'),
(250, 15, '2026-01-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-02-24 16:36:18'),
(251, 15, '2026-01-13', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Taxi', 'Concept', '2026-02-24 16:36:33'),
(252, 15, '2026-01-14', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Taxi', 'Concept', '2026-02-24 16:36:49'),
(253, 15, '2026-01-15', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Taxi', 'Concept', '2026-02-24 16:37:10'),
(254, 15, '2026-01-16', 'Onbekend', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Taxi', 'Concept', '2026-02-24 16:37:24'),
(255, 3, '2026-02-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(256, 3, '2026-02-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(257, 3, '2026-02-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(258, 3, '2026-02-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(259, 3, '2026-02-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(260, 3, '2026-02-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(261, 3, '2026-02-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(262, 3, '2026-02-11', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(263, 3, '2026-02-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 5.50, 1.50, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(264, 3, '2026-02-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.50, 0.00, 0.00, 3.50, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(265, 3, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(266, 3, '2026-02-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(267, 3, '2026-02-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(268, 3, '2026-02-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(269, 3, '2026-02-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(270, 3, '2026-02-24', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 14.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(271, 3, '2026-02-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(272, 3, '2026-02-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(273, 3, '2026-02-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(274, 4, '2026-02-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 6.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(275, 5, '2026-02-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(276, 5, '2026-02-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(277, 5, '2026-02-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(278, 5, '2026-02-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(279, 5, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(280, 5, '2026-02-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(281, 5, '2026-02-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(282, 5, '2026-02-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(283, 5, '2026-02-24', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(284, 5, '2026-02-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(285, 5, '2026-02-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.25, 0.00, 4.25, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(286, 6, '2026-02-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(287, 6, '2026-02-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(288, 6, '2026-02-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(289, 6, '2026-02-13', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(290, 6, '2026-02-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(291, 6, '2026-02-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.50, 0.00, 0.00, 4.50, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(292, 6, '2026-02-24', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(293, 7, '2026-02-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(294, 7, '2026-02-13', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(295, 7, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(296, 7, '2026-02-24', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(297, 16, '2026-02-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 6.75, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(298, 10, '2026-02-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(299, 10, '2026-02-15', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 2.50, 0.00, 4.50, 0.00, 0.00, 0.00, 2, NULL, 'Concept', '2026-03-03 13:54:17'),
(300, 10, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(301, 11, '2026-02-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.75, 0.00, 0.00, 9.75, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(302, 11, '2026-02-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(303, 11, '2026-02-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 10.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(304, 12, '2026-02-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(305, 19, '2026-02-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.50, 0.00, 4.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(306, 15, '2026-02-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(307, 15, '2026-02-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(308, 15, '2026-02-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(309, 15, '2026-02-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(310, 15, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 13:54:17'),
(311, 15, '2026-02-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(312, 15, '2026-02-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-03 13:54:17'),
(538, 13, '2026-02-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:06:07'),
(539, 13, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:06:07'),
(540, 13, '2026-02-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:06:07'),
(541, 13, '2026-02-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:06:07'),
(906, 14, '2026-02-03', '', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Reiskostenvergoeding € 115,-', 'Concept', '2026-03-03 14:41:25'),
(907, 14, '2026-02-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:41:25'),
(908, 14, '2026-02-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:41:25'),
(909, 14, '2026-02-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-03 14:41:25'),
(917, 10, '2026-02-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 3.00, 3.00, 0.00, 0.00, 0.00, 1, '', 'Concept', '2026-03-09 13:34:02'),
(920, 3, '2026-03-18', 'Groepsvervoer', '07:45', '10:30', '11:15', '16:30', '', '', 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-18 09:02:28'),
(924, 3, '2026-03-19', 'Groepsvervoer', '07:45', '13:30', '15:00', '16:45', '', '', 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-03-19 15:55:21'),
(925, 7, '2026-03-20', 'Groepsvervoer', '21:15', '00:00', '', '', '', '', 2.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-21 13:59:38'),
(926, 3, '2026-03-23', 'Groepsvervoer', '07:45', '11:15', '12:30', '13:00', '14:00', '16:45', 9.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-03-23 15:56:30'),
(927, 3, '2026-03-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(928, 3, '2026-03-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(929, 3, '2026-03-05', 'OV', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(930, 3, '2026-03-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.75, 0.00, 1.75, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(931, 3, '2026-03-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(932, 3, '2026-03-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(933, 3, '2026-03-11', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(934, 3, '2026-03-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 11.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(935, 3, '2026-03-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 0.00, 4.75, 0.25, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(936, 3, '2026-03-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(937, 3, '2026-03-17', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(941, 3, '2026-03-24', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(942, 3, '2026-03-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(943, 3, '2026-03-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(944, 3, '2026-03-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(945, 3, '2026-03-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(946, 3, '2026-03-31', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(947, 4, '2026-03-21', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 8.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(948, 5, '2026-03-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(949, 5, '2026-03-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 9.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(950, 5, '2026-03-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(951, 5, '2026-03-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(952, 5, '2026-03-10', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(953, 5, '2026-03-11', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(954, 5, '2026-03-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(955, 5, '2026-03-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(956, 5, '2026-03-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(957, 5, '2026-03-20', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(958, 5, '2026-03-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(959, 5, '2026-03-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(960, 5, '2026-03-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 10.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(961, 6, '2026-03-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(962, 6, '2026-03-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(963, 6, '2026-03-12', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 5.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-04-20 11:57:18'),
(964, 7, '2026-03-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(965, 7, '2026-03-13', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(966, 7, '2026-03-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(968, 7, '2026-03-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(969, 10, '2026-03-08', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.50, 0.00, 0.00, 3.50, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(970, 10, '2026-03-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 15.50, 0.00, 14.00, 1.50, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(971, 10, '2026-03-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 7.75, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(972, 11, '2026-03-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(973, 11, '2026-03-22', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 8.50, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(974, 11, '2026-03-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 11.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(975, 11, '2026-03-28', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.00, 0.00, 8.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(976, 11, '2026-03-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(977, 12, '2026-03-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 7.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(978, 12, '2026-03-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 7.50, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(979, 12, '2026-03-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(980, 19, '2026-03-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 4.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(981, 19, '2026-03-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(982, 19, '2026-03-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.75, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(983, 19, '2026-03-05', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.50, 1.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(984, 19, '2026-03-06', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(985, 19, '2026-03-09', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(986, 19, '2026-03-14', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 0.00, 5.00, 0.00, 0.00, 0.00, 0.00, 1, NULL, 'Concept', '2026-04-20 11:57:18'),
(987, 14, '2026-03-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(988, 14, '2026-03-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(989, 14, '2026-03-18', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 3.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(990, 14, '2026-03-19', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 8.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(991, 14, '2026-03-25', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(992, 14, '2026-03-26', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(993, 14, '2026-03-27', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(994, 14, '2026-03-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 5.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(995, 14, '2026-03-31', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 7.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-04-20 11:57:18'),
(996, 15, '2026-03-02', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(997, 15, '2026-03-03', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 1.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(998, 15, '2026-03-04', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(999, 15, '2026-03-16', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-04-20 11:57:18'),
(1000, 15, '2026-03-23', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 4.25, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, '', 'Concept', '2026-04-20 11:57:18'),
(1001, 15, '2026-03-30', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, 'Concept', '2026-04-20 11:57:18'),
(1003, 9, '2026-03-01', 'Groepsvervoer', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Hele maand ziek 18 dagen', 'Concept', '2026-04-20 11:59:07');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `mail_sjablonen`
--

CREATE TABLE `mail_sjablonen` (
  `id` int(11) NOT NULL,
  `titel` varchar(100) NOT NULL,
  `onderwerp` varchar(150) NOT NULL,
  `bericht` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `mail_sjablonen`
--

INSERT INTO `mail_sjablonen` (`id`, `titel`, `onderwerp`, `bericht`) VALUES
(1, 'Standaard Dieseltoeslag (Versie 2)', 'Belangrijke informatie over uw geplande rit (Dieseltoeslag)', 'Beste [NAAM] ,\r\n\r\nWij kijken ernaar uit om binnenkort uw geplande rit te verzorgen. Vanwege de extreem gestegen brandstofprijzen ontkomen wij er echter niet aan om een dieseltoeslag te hanteren.\r\n\r\nOm dit 100% transparant te doen, werken wij niet met vaste percentages, maar met een eerlijke berekening op basis van de feiten:\r\n(B.v. : Gereden kilometers / verbruik 1 op 3) x het prijsverschil t.o.v. de gecalculeerde prijs = uw netto toeslag. U betaalt dus uitsluitend de pure brandstofstijging voor uw specifieke rit. Wel zo eerlijk!\r\n\r\nOmdat dit onvoorziene kosten zijn, bieden we u de mogelijkheid om de rit kosteloos te annuleren. Wilt u hiervan gebruikmaken? Laat het ons dan zo spoedig mogelijk weten als reactie op deze e-mail.\r\n\r\nGaat u akkoord met deze kleine wijziging? Dan hoeft u verder niets te doen. Wij gaan er dan vanuit dat de rit gewoon doorgaat en brengen u op de afgesproken dag met veel plezier naar uw bestemming');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `klant_naam` varchar(100) DEFAULT NULL,
  `klant_email` varchar(100) DEFAULT NULL,
  `klant_tel` varchar(20) DEFAULT NULL,
  `totaal_bedrag` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open',
  `betaald_op` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `event_id` int(11) DEFAULT 0,
  `email` varchar(100) NOT NULL,
  `tel` varchar(20) NOT NULL,
  `datum` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `orders`
--

INSERT INTO `orders` (`id`, `klant_naam`, `klant_email`, `klant_tel`, `totaal_bedrag`, `status`, `betaald_op`, `created_at`, `event_id`, `email`, `tel`, `datum`) VALUES
(14, 'fred', 'info@taxiberkhout.nl', '06243537', 24.00, 'betaald', '2026-02-08 19:49:49', '2026-02-08 19:49:40', 0, '', '', '2026-02-10 19:22:25'),
(15, 'Jan Jolink', 'info@taxiberkhout.nl', '0641255791', 12.00, 'betaald', '2026-02-08 20:07:17', '2026-02-08 20:06:56', 0, '', '', '2026-02-10 19:22:25'),
(16, 'Fred Stravers', 'info@taxiberkhout.nl', '0641255791', 12.00, 'betaald', NULL, '2026-02-08 21:26:16', 0, '', '', '2026-02-10 19:22:25'),
(69, 'Fred', 'info@taxiberkhout.nl', '0641255791', 12.00, 'betaald', '2026-02-12 15:01:02', '2026-02-12 14:52:19', 0, '', '', '2026-02-12 14:52:19'),
(70, 'Fred', 'info@taxiberkhout.nl', '0641255791', 12.00, 'betaald', '2026-02-12 15:04:22', '2026-02-12 15:03:55', 0, '', '', '2026-02-12 15:03:55'),
(71, 'Fred Stravers', 'Info@taxiberkhout.nl', '0641255791', 26.00, 'open', NULL, '2026-02-12 15:50:40', 0, '', '', '2026-02-12 15:50:40'),
(72, 'Fred Stravers', 'info@taxiberkhout.nl', '0641255791', 1.00, 'betaald', '2026-02-12 15:56:07', '2026-02-12 15:55:32', 0, '', '', '2026-02-12 15:55:32'),
(73, 'Lieuwe', 'lieuwelichtenberg9@gmail.com', '0621317873', 12.00, 'betaald', '2026-02-15 14:39:26', '2026-02-15 14:39:11', 0, '', '', '2026-02-15 14:39:11'),
(109, 'Jan', 'info@taxiberkhout.nl', '0641255791', 20.00, 'open', NULL, '2026-02-20 15:55:18', 0, '', '', '2026-02-20 15:55:18'),
(112, 'tom rooiman', 'tom.rooiman@student.graafschapcollege.nl', '0610998264', 10.00, 'betaald', NULL, '2026-02-26 08:18:04', 3, '', '', '2026-02-26 08:18:04'),
(113, 'Fred', 'info@taxiberkhout.nl', '0641255791', 20.00, 'open', NULL, '2026-02-27 15:15:34', 0, '', '', '2026-02-27 15:15:34'),
(114, 'Hazel Frijlink', 'frijlinkhazel@outlook.com', '+31629311489', 20.00, 'betaald', '2026-02-27 15:17:55', '2026-02-27 15:17:20', 8, '', '', '2026-02-27 15:17:20'),
(115, 'Keano Barink', 'keanobarink2007+@gmail.com', '0614069016', 40.00, 'betaald', '2026-02-27 15:17:35', '2026-02-27 15:17:23', 8, '', '', '2026-02-27 15:17:23'),
(116, 'donna van den bogaard', 'donnavandenbogaard@gmail.com', '+31628724003', 20.00, 'betaald', '2026-02-27 15:19:12', '2026-02-27 15:18:37', 8, '', '', '2026-02-27 15:18:37'),
(117, 'Robin Wijma', 'RobinWijm07@gmail.con', '0649244439', 20.00, 'betaald', '2026-02-27 15:20:00', '2026-02-27 15:19:45', 8, '', '', '2026-02-27 15:19:45'),
(118, 'Dico Bosman', 'dicobosman2005@gmail.com', '0686615380', 20.00, 'open', NULL, '2026-02-27 15:19:49', 0, '', '', '2026-02-27 15:19:49'),
(119, 'Aaron Gerritsen', 'aarongerritsen2@gmail.com', '0641589869', 20.00, 'betaald', '2026-02-27 15:20:26', '2026-02-27 15:20:07', 8, '', '', '2026-02-27 15:20:07'),
(120, 'Rick Reichert', 'reichertrick17@gmail.com', '0614921772', 20.00, 'betaald', '2026-02-27 15:21:02', '2026-02-27 15:20:40', 8, '', '', '2026-02-27 15:20:40'),
(121, 'Dico Bosman', 'dicobosman2005@gmail.com', '0686615380', 20.00, 'betaald', '2026-02-27 15:21:17', '2026-02-27 15:20:59', 8, '', '', '2026-02-27 15:20:59'),
(122, 'Eef straten ', 'straten.eeffee@gmail.com', '0658988844', 20.00, 'betaald', '2026-02-27 15:24:22', '2026-02-27 15:24:07', 8, '', '', '2026-02-27 15:24:07'),
(123, 'Romano Sloot', 'romanosloot@gmail.com', '0633407160', 20.00, 'betaald', '2026-02-27 15:44:01', '2026-02-27 15:43:47', 8, '', '', '2026-02-27 15:43:47'),
(124, 'Giel Vesterink ', 'giel.vesterink@icloud.com', '+31612399108', 20.00, 'betaald', '2026-02-27 15:44:32', '2026-02-27 15:44:19', 8, '', '', '2026-02-27 15:44:19'),
(125, 'Chanel El Fouly', 'chanelelfouly@gmail.com', '0620350961', 375.00, 'betaald', '2026-02-27 17:02:31', '2026-02-27 17:02:16', 4, '', '', '2026-02-27 17:02:16'),
(126, 'Donny lijnsvelt ', 'donnylijnsvelt1608@gmail.com', '+31624438795', 20.00, 'betaald', '2026-02-27 17:07:02', '2026-02-27 17:06:48', 8, '', '', '2026-02-27 17:06:48'),
(127, 'Luuk van der Linden', 'luuk15032006@icloud.com', '0617477065', 40.00, 'betaald', '2026-02-27 17:10:17', '2026-02-27 17:10:05', 8, '', '', '2026-02-27 17:10:05'),
(128, 'Lisa van Ree', 'lisavanree1@gmail.com', '+31641851396', 20.00, 'open', NULL, '2026-02-27 17:14:27', 0, '', '', '2026-02-27 17:14:27'),
(129, 'Robin de Boer', 'robinwout07@ziggo.nl', '0652825131', 20.00, 'betaald', NULL, '2026-02-27 18:04:05', 8, '', '', '2026-02-27 18:04:05'),
(130, 'Lisa van Ree', 'lisavanree1@gmail.com', '0641851396', 20.00, 'betaald', NULL, '2026-02-27 18:52:06', 8, '', '', '2026-02-27 18:52:06'),
(131, 'Fred', 'info@taxiberkhout.nl', '0689898989', 20.00, 'betaald', NULL, '2026-02-27 19:36:48', 0, '', '', '2026-02-27 19:36:48'),
(132, 'Fred', 'info@taxiberkhout.nl', '0641255791', 20.00, 'betaald', NULL, '2026-02-27 19:57:48', 8, '', '', '2026-02-27 19:57:48'),
(134, 'Yana Smaak', 'yanasmaak@gmail.com', '0621944232', 20.00, 'betaald', NULL, '2026-02-27 20:50:45', 8, '', '', '2026-02-27 20:50:45'),
(135, 'Maran Maathuis', 'maran.maathuis@icloud.com', '0613302830', 40.00, 'open', NULL, '2026-02-28 00:16:02', 8, '', '', '2026-02-28 00:16:02'),
(136, 'Thijs Janssen ', 'janssenthijs2008@gmail.com', '+31616476130', 20.00, 'betaald', NULL, '2026-02-28 14:00:51', 8, '', '', '2026-02-28 14:00:51'),
(137, 'Koen de greeff', 'k.greeff40@gmail.com', '0657088279', 20.00, 'betaald', NULL, '2026-02-28 15:37:19', 8, '', '', '2026-02-28 15:37:19'),
(138, 'Yuri Schieven', 'yurischieven@gmail.com', '+31657694442', 20.00, 'betaald', NULL, '2026-02-28 15:37:19', 8, '', '', '2026-02-28 15:37:19'),
(140, 'Mees Nijhuis', 'mees.n@upcmail.nl', '0624973015', 20.00, 'betaald', NULL, '2026-02-28 15:41:27', 8, '', '', '2026-02-28 15:41:27'),
(141, 'Tatum Vriezekolk', 't.vriezekolk@gmail.com', '0619992508', 60.00, 'betaald', NULL, '2026-02-28 22:24:28', 8, '', '', '2026-02-28 22:24:28'),
(142, 'Jack Gerritsen ', 'jackgerritsen6@gmail.com', '0615920413', 20.00, 'betaald', NULL, '2026-03-01 00:34:23', 8, '', '', '2026-03-01 00:34:23'),
(143, 'Mats Schieven ', 'matsschieven@gmail.com', '0628696027', 20.00, 'betaald', NULL, '2026-03-01 00:34:25', 8, '', '', '2026-03-01 00:34:25'),
(144, 'Koen', 'koen.janssen.2005@gmail.com', '0621991399', 20.00, 'betaald', NULL, '2026-03-02 13:22:50', 8, '', '', '2026-03-02 13:22:50'),
(145, 'Quin Trepadus', 'quintrepadus@hotmail.com', '+31617149691', 20.00, 'betaald', NULL, '2026-03-02 13:27:13', 8, '', '', '2026-03-02 13:27:13'),
(146, 'Xavier Delger-Nelson', 'xdelgernelson@gmail.com', '+31615237128', 20.00, 'betaald', NULL, '2026-03-02 18:57:49', 8, '', '', '2026-03-02 18:57:49'),
(147, 'Daauud siyad', 'daauudabu123@gmail.com', '0640622207', 20.00, 'betaald', NULL, '2026-03-02 18:59:04', 8, '', '', '2026-03-02 18:59:04'),
(148, 'Sam van der hoeven ', 'svdhoeven8@gmail.com', '0639709743', 20.00, 'betaald', NULL, '2026-03-02 19:20:05', 8, '', '', '2026-03-02 19:20:05'),
(149, 'Floris van Dalen', 'fvdalen2008@gmail.com', '0643860155', 20.00, 'betaald', NULL, '2026-03-03 07:35:51', 8, '', '', '2026-03-03 07:35:51'),
(150, 'Wietse Berenpas', 'wietseberenpas@gmail.com', '+31645562660', 20.00, 'betaald', NULL, '2026-03-03 08:36:53', 8, '', '', '2026-03-03 08:36:53'),
(153, 'Jesper de Boer', 'ljdeboer06@gmail.com', '0615503699', 20.00, 'betaald', NULL, '2026-03-03 08:43:32', 8, '', '', '2026-03-03 08:43:32'),
(154, 'Thomas Jansen', 'jansenthomas911@gmail.com', '0628257993', 20.00, 'betaald', NULL, '2026-03-03 08:44:45', 8, '', '', '2026-03-03 08:44:45'),
(155, 'Morris jansen', 'morrisjansen2008@gmail.com', '0683237775', 20.00, 'betaald', NULL, '2026-03-03 10:29:45', 8, '', '', '2026-03-03 10:29:45'),
(156, 'Sem Klaasen', 'semklaasen@gmail.com', '0614903095', 20.00, 'open', NULL, '2026-03-03 10:59:02', 8, '', '', '2026-03-03 10:59:02'),
(157, 'Jorn Sangers', 'jornsangers29@gmail.com', '0612386542', 20.00, 'betaald', NULL, '2026-03-03 17:24:07', 8, '', '', '2026-03-03 17:24:07'),
(158, 'Daan Haagen', 'Haagendaan@gmail.com', '0625720103', 20.00, 'betaald', NULL, '2026-03-03 17:28:30', 8, '', '', '2026-03-03 17:28:30'),
(159, 'Lars Michgels', 'lars.michgels@icloud.com', '+31610198156', 20.00, 'betaald', NULL, '2026-03-03 17:32:40', 8, '', '', '2026-03-03 17:32:40'),
(161, 'Ruben Groot Roessink', 'ruben.groot.roessink2007@gmail.com', '0629206459', 20.00, 'betaald', NULL, '2026-03-03 20:48:58', 8, '', '', '2026-03-03 20:48:58'),
(163, 'Kick Arne Gijsbert scharrenberg', 'kickschar@gmail.com', '0613172215', 20.00, 'betaald', NULL, '2026-03-03 20:51:10', 8, '', '', '2026-03-03 20:51:10'),
(164, 'Sven Michgels ', 'sven.michgels@icloud.com', '+31682496040', 20.00, 'betaald', NULL, '2026-03-04 15:30:50', 8, '', '', '2026-03-04 15:30:50'),
(165, 'Nina de Groot', 'nina.degroot1@upcmail.nl', '0611477405', 20.00, 'open', NULL, '2026-03-04 16:36:00', 8, '', '', '2026-03-04 16:36:00'),
(166, 'Dani Schuppers ', 'dani.schuppers@gmail.com', '0638414600', 20.00, 'betaald', NULL, '2026-03-04 18:30:31', 8, '', '', '2026-03-04 18:30:31'),
(167, 'Daan Jongbloets', 'daanjongbloets@hotmail.com', '0641816955', 20.00, 'betaald', NULL, '2026-03-04 18:33:00', 8, '', '', '2026-03-04 18:33:00'),
(169, 'Sam Korenblek', 'sam18juni@gmail.com', '0625122505', 20.00, 'betaald', NULL, '2026-03-04 18:34:10', 8, '', '', '2026-03-04 18:34:10'),
(170, 'Julius DJ Wesselink', 'jdj.wesselink@gmail.com', '0644371634', 20.00, 'betaald', NULL, '2026-03-04 18:44:08', 8, '', '', '2026-03-04 18:44:08'),
(171, 'Sem Klaasen', 'semklaasen@gmail.com', '0614903095', 20.00, 'betaald', NULL, '2026-03-04 19:32:24', 8, '', '', '2026-03-04 19:32:24'),
(172, 'Yurre wammes', 'yurrewammes@hotmail.com', '0683954427', 20.00, 'betaald', NULL, '2026-03-04 19:32:31', 8, '', '', '2026-03-04 19:32:31'),
(173, 'Keano Barink', 'keanobarink2007@gmail.com', '0614069016', 20.00, 'open', NULL, '2026-03-04 21:15:44', 8, '', '', '2026-03-04 21:15:44'),
(175, 'Jelmer van Zeeburg', 'jelletsbc@gmail.com', '0625115006', 20.00, 'betaald', NULL, '2026-03-06 06:02:15', 8, '', '', '2026-03-06 06:02:15'),
(176, 'Luka Sossdorf ', 'lukasossdorf@gmail.com', '0621809302', 20.00, 'betaald', NULL, '2026-03-06 09:39:07', 8, '', '', '2026-03-06 09:39:07'),
(178, 'Rick Loman', 'rickloman15@gmail.com', '0614210348', 40.00, 'betaald', NULL, '2026-03-06 20:27:16', 8, '', '', '2026-03-06 20:27:16'),
(179, 'Harm Lammes ', 'harmlammes2007@gmail.com', '0629451937', 20.00, 'betaald', NULL, '2026-03-06 23:26:40', 8, '', '', '2026-03-06 23:26:40'),
(180, 'Jayden Krijnen', 'sn1wz6@gmail.com', '0683261726', 20.00, 'open', NULL, '2026-03-07 00:01:34', 8, '', '', '2026-03-07 00:01:34'),
(181, 'Jayden Krijnen', 'sn1wz6@gmail.com', '0683261726', 20.00, 'open', NULL, '2026-03-07 00:03:15', 8, '', '', '2026-03-07 00:03:15'),
(182, 'Jesse Assink', 'jesseassink2008@gmail.com', '0625153941', 20.00, 'betaald', NULL, '2026-03-07 09:56:31', 8, '', '', '2026-03-07 09:56:31'),
(183, 'Jordi Derksen', 'jordihuts@gmail.com', '0613286831', 40.00, 'open', NULL, '2026-03-07 10:00:57', 8, '', '', '2026-03-07 10:00:57'),
(184, 'Jordi Derksen ', 'jordihuts@gmail.com', '0613286831', 40.00, 'open', NULL, '2026-03-07 10:47:50', 8, '', '', '2026-03-07 10:47:50'),
(185, 'Jordi Derksen', 'jordihuts@gmail.com', '0613286831', 20.00, 'betaald', NULL, '2026-03-07 10:48:29', 8, '', '', '2026-03-07 10:48:29'),
(186, 'Mats Roelofs', 'jordihuts@gmail.com', '0613286831', 20.00, 'betaald', NULL, '2026-03-07 10:49:22', 8, '', '', '2026-03-07 10:49:22'),
(187, 'Giel vesterink', 'giel.vesterink@icloud.com', '0612399108', 20.00, 'betaald', NULL, '2026-03-07 19:23:18', 8, '', '', '2026-03-07 19:23:18'),
(188, 'marijn lichtenberg', 'marijnlichtenberg@gmail.com', '0641092728', 40.00, 'betaald', NULL, '2026-03-07 21:58:53', 8, '', '', '2026-03-07 21:58:53'),
(189, 'Phillip Rosenberger', 'phillip.rosenberger@student.graafschapcollege.nl', '0657808134', 10.00, 'betaald', NULL, '2026-03-08 19:48:39', 3, '', '', '2026-03-08 19:48:39'),
(190, 'Lotte Peters', 'lotte.peters@student.graafschapcollege.nl', '0639493301', 10.00, 'betaald', NULL, '2026-03-09 15:56:30', 3, '', '', '2026-03-09 15:56:30'),
(191, 'Milan Menkehorst', 'milan.menkehorst@student.graafschapcollege.nl', '0683159354', 10.00, 'betaald', NULL, '2026-03-10 07:11:38', 3, '', '', '2026-03-10 07:11:38'),
(192, 'Sterre ten Have', 'sterre.have@student.graafschapcollege.nl', '0614271408', 12.00, 'betaald', NULL, '2026-03-10 10:00:55', 3, '', '', '2026-03-10 10:00:55'),
(193, 'Chiel Tenbült', 'chiel.tenbult@student.graafschapcollege.nl', '0657933816', 10.00, 'betaald', NULL, '2026-03-10 13:20:12', 3, '', '', '2026-03-10 13:20:12'),
(194, 'Luuk jacobsen', 'Luukjacobsen12@gmail.com', '0640787774', 60.00, 'betaald', NULL, '2026-03-16 16:35:15', 9, '', '', '2026-03-16 16:35:15'),
(196, 'Ruud van de kamp', 'Ruudvandekamp2006@gmail.com', '0683311133', 30.00, 'open', NULL, '2026-03-16 16:42:07', 9, '', '', '2026-03-16 16:42:07'),
(197, 'Ivar Bloem ', 'bloemivar@gmail.com', '0612231637', 30.00, 'betaald', NULL, '2026-03-16 16:42:30', 9, '', '', '2026-03-16 16:42:30'),
(198, 'Luuk Hendriks', 'luukhendriks03@gmail.com', '0622335830', 60.00, 'betaald', NULL, '2026-03-16 16:44:21', 9, '', '', '2026-03-16 16:44:21'),
(199, 'Ruud van de kamp', 'Ruudvandekamp2006@gmail.com', '0683311133', 30.00, 'betaald', NULL, '2026-03-16 16:44:27', 9, '', '', '2026-03-16 16:44:27'),
(200, 'Thijs Spijkerbosch ', 'Thijsspijkerbosch2004@gmail.com', '0612847733', 30.00, 'betaald', NULL, '2026-03-16 16:46:23', 9, '', '', '2026-03-16 16:46:23'),
(201, 'Tom snippe ', 'tomsnippenb@gmail.com', '0638713063', 30.00, 'open', NULL, '2026-03-16 16:55:45', 9, '', '', '2026-03-16 16:55:45'),
(202, 'Tom snippe ', 'tomsnippenb@gmail.com', '0638713063', 30.00, 'betaald', NULL, '2026-03-16 16:55:45', 9, '', '', '2026-03-16 16:55:45'),
(203, 'Jelte bosgoed', 'Jeltebosgoed@gmail.com', '0629104401', 30.00, 'betaald', NULL, '2026-03-16 17:02:55', 9, '', '', '2026-03-16 17:02:55'),
(204, 'Marcel Berghuis ', 'berghuismarcel@gmail.com', '0637283943', 30.00, 'betaald', NULL, '2026-03-16 17:06:31', 9, '', '', '2026-03-16 17:06:31'),
(205, 'Chiel Straatman', 'chiel.straatman3@gmail.com', '0641381277', 30.00, 'betaald', NULL, '2026-03-16 17:09:29', 9, '', '', '2026-03-16 17:09:29'),
(206, 'Roan Bloem', 'roanbloem2004@gmail.com', '0622424937', 30.00, 'betaald', NULL, '2026-03-16 17:26:20', 9, '', '', '2026-03-16 17:26:20'),
(207, 'Rens ', 'rc.wonink@outlook.com', '0657763438', 30.00, 'betaald', NULL, '2026-03-16 19:30:37', 9, '', '', '2026-03-16 19:30:37'),
(208, 'Elwin Van de Steeg', 'elwinvandesteeg@gmail.com', '0619630013', 60.00, 'betaald', NULL, '2026-03-16 20:12:10', 9, '', '', '2026-03-16 20:12:10'),
(209, 'Sander de Haan', 'sanderhaan11@gmail.com', '0614901632', 30.00, 'betaald', NULL, '2026-03-16 21:13:28', 9, '', '', '2026-03-16 21:13:28'),
(210, 'Honzi Hermanek', 'honzi.hermanek@student.graafschapcollege.nl', '0681250087', 10.00, 'betaald', NULL, '2026-03-17 15:46:40', 3, '', '', '2026-03-17 15:46:40'),
(211, 'Demi Berndsen ', 'demiberndsen06@gmail.com', '0611972429', 30.00, 'betaald', NULL, '2026-03-17 17:35:54', 9, '', '', '2026-03-17 17:35:54'),
(212, 'Jarne Uenk', 'jarneuenk2002@gmail.com', '0638364667', 30.00, 'open', NULL, '2026-03-17 20:09:13', 9, '', '', '2026-03-17 20:09:13'),
(213, 'Jarne Uenk', 'jarneuenk2002@gmail.com', '0638364667', 30.00, 'betaald', NULL, '2026-03-17 20:11:19', 9, '', '', '2026-03-17 20:11:19'),
(214, 'Wouter Kuttschreutter ', 'wouter1050@gmail.com', '0620697099', 60.00, 'betaald', NULL, '2026-03-18 06:49:39', 9, '', '', '2026-03-18 06:49:39'),
(215, 'Sem Woudstra', 'sem.woudstra@student.graafschapcollege.nl', '0627593110', 10.00, 'betaald', NULL, '2026-03-19 10:15:00', 3, '', '', '2026-03-19 10:15:00'),
(216, 'Ilan Simons', 'ilansimons05@gmail.com', '0637793151', 30.00, 'open', NULL, '2026-03-20 09:44:39', 9, '', '', '2026-03-20 09:44:39'),
(217, 'Thom de haan', 'thomdehaan@outlook.com', '0657008613', 30.00, 'betaald', NULL, '2026-03-20 09:45:07', 9, '', '', '2026-03-20 09:45:07'),
(218, 'Ilan Simons', 'ilansimons05@gmail.com', '0637793151', 30.00, 'betaald', NULL, '2026-03-20 09:46:29', 9, '', '', '2026-03-20 09:46:29'),
(219, 'Roy Keurhorst', 'roykeurhorst@gmail.com', '0638398526', 30.00, 'betaald', NULL, '2026-03-20 11:48:32', 9, '', '', '2026-03-20 11:48:32'),
(220, 'Sil Poortinga', 'silpoortinga111@gmail.com', '0622845323', 30.00, 'betaald', NULL, '2026-03-20 12:11:19', 9, '', '', '2026-03-20 12:11:19'),
(221, 'Iris Lankhuijzen', 'iris.lankhuijzen@student.graafschapcollege.nl', '0657763999', 10.00, 'betaald', NULL, '2026-03-20 18:34:05', 3, '', '', '2026-03-20 18:34:05'),
(222, 'Amber Vogel', 'amber.vogel@student.graafschapcollege.nl', '0627523527', 10.00, 'betaald', NULL, '2026-03-20 18:39:40', 3, '', '', '2026-03-20 18:39:40'),
(223, 'Karlijn Heutinck', 'karlijn.heutinck@student.graafschapcollege.nl', '0626628468', 10.00, 'betaald', NULL, '2026-03-20 20:04:27', 3, '', '', '2026-03-20 20:04:27'),
(224, 'Twan De haan', 'twandehaan08@gmail.com', '0612125350', 30.00, 'betaald', NULL, '2026-03-21 17:49:06', 9, '', '', '2026-03-21 17:49:06'),
(225, 'Bas Hoetink ', 'bashoetink0@gmail.com', '0637488976', 30.00, 'betaald', NULL, '2026-03-21 18:43:50', 9, '', '', '2026-03-21 18:43:50'),
(226, 'Fenne Koers', 'fenne.koers@student.graafschapcollege.nl', '0648210197', 10.00, 'betaald', NULL, '2026-03-23 07:47:52', 3, '', '', '2026-03-23 07:47:52'),
(229, 'Vinciano Rensink', 'vinciano.rensink@student.graafschapcollege.nl', '0639461156', 10.00, 'betaald', NULL, '2026-03-25 09:27:22', 3, '', '', '2026-03-25 09:27:22'),
(230, 'Rens Naves', 'rens.naves@student.graafschapcollege.nl', '0629930301', 10.00, 'betaald', NULL, '2026-03-25 09:37:38', 3, '', '', '2026-03-25 09:37:38'),
(231, 'Louis Peters', 'louis.peters@student.graafschapcollege.nl', '0616177457', 10.00, 'betaald', NULL, '2026-03-25 11:06:59', 3, '', '', '2026-03-25 11:06:59'),
(232, 'Merel Schel', 'merel.schel@student.graafschapcollege.nl', '0657040437', 10.00, 'betaald', NULL, '2026-03-25 17:53:37', 3, '', '', '2026-03-25 17:53:37'),
(233, 'Esmee Wentink', 'esmee.wentink@student.graafschapcollege.nl', '0612455879', 10.00, 'betaald', NULL, '2026-03-25 17:59:52', 3, '', '', '2026-03-25 17:59:52'),
(235, 'Bram Ditters', 'bram.ditters@student.graafschapcollege.nl', '0634825834', 10.00, 'betaald', NULL, '2026-03-26 11:17:10', 3, '', '', '2026-03-26 11:17:10'),
(236, 'Maud ten Pas', 'maud.pas@student.graafschapcollege.nl', '0683034462', 10.00, 'betaald', NULL, '2026-03-26 18:31:07', 3, '', '', '2026-03-26 18:31:07'),
(237, 'Iggy Peters', 'iggy.peters@student.graafschapcollege.nl', '0619322246', 10.00, 'betaald', NULL, '2026-03-27 06:25:33', 3, '', '', '2026-03-27 06:25:33'),
(238, 'Luna Havekes', 'luna.havekes@student.graafschapcollege.nl', '0619694978', 10.00, 'betaald', NULL, '2026-03-27 13:01:55', 3, '', '', '2026-03-27 13:01:55'),
(239, 'Maud Prinsen', 'maud.prinsen@student.graafschapcollege.nl', '0610306664', 10.00, 'betaald', NULL, '2026-03-27 13:02:41', 3, '', '', '2026-03-27 13:02:41'),
(240, 'Fleur Vruggink', 'fleur.vruggink@student.graafschapcollege.nl', '0630957445', 10.00, 'betaald', NULL, '2026-03-27 14:02:04', 3, '', '', '2026-03-27 14:02:04'),
(241, 'Denise Leemrijse', 'denise.leemrijse@student.graafschapcollege.nl', '0614534106', 10.00, 'betaald', NULL, '2026-03-27 14:03:05', 3, '', '', '2026-03-27 14:03:05'),
(242, 'mirre wevers', 'mirre.wevers@student.graafschapcollege.nl', '0628887810', 10.00, 'betaald', NULL, '2026-03-27 14:05:00', 3, '', '', '2026-03-27 14:05:00'),
(243, 'Merel Lieverdink', 'merel.lieverdink@student.graafschapcollege.nl', '0613181382', 10.00, 'betaald', NULL, '2026-03-27 14:13:31', 3, '', '', '2026-03-27 14:13:31'),
(244, 'Niek Vrieze', 'niek.vrieze@student.graafschapcollege.nl', '0651785497', 10.00, 'betaald', NULL, '2026-03-27 20:46:07', 3, '', '', '2026-03-27 20:46:07'),
(245, 'Rinus Pelgrum', 'rinuspelgrum@hotmail.com', '0627169808', 55.00, 'betaald', NULL, '2026-03-27 20:59:36', 12, '', '', '2026-03-27 20:59:36'),
(246, 'Damian Stinissen', 'damian.stinissen@student.graafschapcollege.nl', '0621300600', 10.00, 'betaald', NULL, '2026-03-27 21:20:34', 3, '', '', '2026-03-27 21:20:34'),
(247, 'Myrthe Meinen', 'myrthe.meinen@student.graafschapcollege.nl', '0642614899', 10.00, 'betaald', NULL, '2026-03-27 21:39:17', 3, '', '', '2026-03-27 21:39:17'),
(248, 'Sterre ten Have', NULL, NULL, 10.00, 'betaald', NULL, '2026-03-27 23:10:41', 3, 'sterre.have@student.graafschapcollege.nl', '0614271408', '2026-03-27 23:10:41'),
(249, 'Kirsten Wassink', 'kirsten.wassink@student.graafschapcollege.nl', '0640194816', 10.00, 'betaald', NULL, '2026-03-28 15:22:36', 3, '', '', '2026-03-28 15:22:36'),
(250, 'Twan Dieker', 'twan.dieker@student.graafschapcollege.nl', '0647569010', 10.00, 'betaald', NULL, '2026-03-28 16:17:37', 3, '', '', '2026-03-28 16:17:37'),
(251, 'Hidde Westerveld', 'hidde.westerveld@student.graafschapcollege.nl', '0683407816', 10.00, 'betaald', NULL, '2026-03-28 17:17:32', 3, '', '', '2026-03-28 17:17:32'),
(252, 'stijn peerik', 'stijn.peerik@student.graafschapcollege.nl', '0629466409', 10.00, 'betaald', NULL, '2026-03-29 10:32:18', 3, '', '', '2026-03-29 10:32:18'),
(253, 'Tess ten Elshof', 'tess.elshof@student.graafschapcollege.nl', '0625386602', 10.00, 'betaald', NULL, '2026-03-29 10:57:32', 3, '', '', '2026-03-29 10:57:32'),
(254, 'Sara Olthuis', 'sara.olthuis@student.graafschapcollege.nl', '0612988308', 10.00, 'betaald', NULL, '2026-03-29 12:48:12', 3, '', '', '2026-03-29 12:48:12'),
(255, 'Lotte Wiggers', 'lotte.wiggers@student.graafschapcollege.nl', '0614347033', 10.00, 'betaald', NULL, '2026-03-29 13:35:12', 3, '', '', '2026-03-29 13:35:12'),
(256, 'Rael van Velsen', 'rael.velsen@student.graafschapcollege.nl', '0649282830', 10.00, 'betaald', NULL, '2026-03-29 15:09:12', 3, '', '', '2026-03-29 15:09:12'),
(257, 'Tygo ter Woerds', 'tygo.woerds@student.graafschapcollege.nl', '0639531461', 10.00, 'betaald', NULL, '2026-03-29 16:03:12', 3, '', '', '2026-03-29 16:03:12'),
(258, 'Sien Roerdink', 'sien.roerdink@student.graafschapcollege.nl', '0682553776', 10.00, 'betaald', NULL, '2026-03-29 17:25:54', 3, '', '', '2026-03-29 17:25:54'),
(259, 'Lot Rumathe', 'lot.rumathe@student.graafschapcollege.nl', '0630167821', 10.00, 'betaald', NULL, '2026-03-29 17:27:21', 3, '', '', '2026-03-29 17:27:21'),
(260, 'Sanne Hulshof', 'sanne.hulshof1@student.graafschapcollege.nl', '0642129293', 10.00, 'betaald', NULL, '2026-03-29 17:32:50', 3, '', '', '2026-03-29 17:32:50'),
(261, 'Ise Pasman', 'ise.pasman@student.graafschapcollege.nl', '0642158535', 10.00, 'betaald', NULL, '2026-03-29 17:33:01', 3, '', '', '2026-03-29 17:33:01'),
(262, 'Liz Rumathé', 'liz.rumathe@student.graafschapcollege.nl', '0613902133', 10.00, 'betaald', NULL, '2026-03-29 17:33:30', 3, '', '', '2026-03-29 17:33:30'),
(263, 'eva gelinck', 'eva.gelinck@student.graafschapcollege.nl', '0636053253', 10.00, 'betaald', NULL, '2026-03-29 17:53:11', 3, '', '', '2026-03-29 17:53:11'),
(264, 'Fem Wellink', 'fem.wellink@student.graafschapcollege.nl', '0613499576', 10.00, 'betaald', NULL, '2026-03-29 17:55:47', 3, '', '', '2026-03-29 17:55:47'),
(265, 'Eva Ponds', 'eva.ponds@student.graafschapcollege.nl', '0630889512', 10.00, 'betaald', NULL, '2026-03-29 17:58:26', 3, '', '', '2026-03-29 17:58:26'),
(266, 'Famke Blanken', 'famke.blanken@student.graafschapcollege.nl', '0612355195', 10.00, 'betaald', NULL, '2026-03-29 17:59:32', 3, '', '', '2026-03-29 17:59:32'),
(268, 'Gijs Everink', 'gijs.everink@student.graafschapcollege.nl', '0651324245', 10.00, 'betaald', NULL, '2026-03-29 18:13:51', 3, '', '', '2026-03-29 18:13:51'),
(269, 'Dione Giesen', 'dione.giesen@student.graafschapcollege.nl', '0639659180', 10.00, 'betaald', NULL, '2026-03-29 18:22:08', 3, '', '', '2026-03-29 18:22:08'),
(270, 'lotte slotman', 'lotte.slotman@student.graafschapcollege.nl', '0610219282', 10.00, 'betaald', NULL, '2026-03-29 19:14:47', 3, '', '', '2026-03-29 19:14:47'),
(271, 'Lauri Bonkink', 'lauri.bonkink@student.graafschapcollege.nl', '0644657091', 10.00, 'betaald', NULL, '2026-03-29 19:16:14', 3, '', '', '2026-03-29 19:16:14'),
(272, 'Mieke Nijhof', 'mieke.nijhof@student.graafschapcollege.nl', '0621368290', 10.00, 'betaald', NULL, '2026-03-29 19:18:38', 3, '', '', '2026-03-29 19:18:38'),
(274, 'Loes Ottenschot', 'loes.ottenschot@student.graafschapcollege.nl', '0630552746', 10.00, 'betaald', NULL, '2026-03-29 19:23:37', 3, '', '', '2026-03-29 19:23:37'),
(275, 'manouk boomkamp', 'manouk.boomkamp@student.graafschapcollege.nl', '0630009467', 10.00, 'betaald', NULL, '2026-03-29 19:35:43', 3, '', '', '2026-03-29 19:35:43'),
(276, 'Milan Hazeveld', 'milan.hazeveld@student.graafschapcollege.nl', '0638039713', 10.00, 'betaald', NULL, '2026-03-29 19:36:06', 3, '', '', '2026-03-29 19:36:06'),
(277, 'Chelsey Wittebroek', 'chelsey.wittebroek@student.graafschapcollege.nl', '0621940358', 10.00, 'betaald', NULL, '2026-03-29 19:37:55', 3, '', '', '2026-03-29 19:37:55'),
(279, 'Ruben Wissenburg', 'ruben.wissenburg@student.graafschapcollege.nl', '0616638305', 10.00, 'betaald', NULL, '2026-03-29 19:58:10', 3, '', '', '2026-03-29 19:58:10'),
(280, 'Tijs Lenderink', 'tijs.lenderink@student.graafschapcollege.nl', '0622634275', 10.00, 'betaald', NULL, '2026-03-29 20:00:33', 3, '', '', '2026-03-29 20:00:33'),
(281, 'Fabiënne Elting', 'fabienne.elting@student.graafschapcollege.nl', '0648439499', 10.00, 'betaald', NULL, '2026-03-29 20:29:56', 3, '', '', '2026-03-29 20:29:56'),
(282, 'Teun Liebrand', 'teun.liebrand@student.graafschapcollege.nl', '0623169981', 10.00, 'betaald', NULL, '2026-03-29 21:36:08', 3, '', '', '2026-03-29 21:36:08'),
(284, 'Hugo kaspers', 'hugo.kaspers@student.graafschapcollege.nl', '0639658831', 10.00, 'betaald', NULL, '2026-03-30 08:32:18', 3, '', '', '2026-03-30 08:32:18'),
(285, 'Benjamin Bomer', 'benjamin.bomer@student.graafschapcollege.nl', '0658950365', 10.00, 'betaald', NULL, '2026-03-30 08:52:57', 3, '', '', '2026-03-30 08:52:57'),
(287, 'Isa Engelbarts', 'isa.engelbarts@student.graafschapcollege.nl', '0634171770', 10.00, 'betaald', NULL, '2026-03-30 11:08:21', 3, '', '', '2026-03-30 11:08:21'),
(289, 'Laurien Kremer', 'laurien.kremer@student.graafschapcollege.nl', '0612625783', 10.00, 'betaald', NULL, '2026-03-30 11:11:47', 3, '', '', '2026-03-30 11:11:47'),
(290, 'Milou Zeijseink', 'milou.zeijseink@student.graafschapcollege.nl', '0644798545', 10.00, 'betaald', NULL, '2026-03-30 16:12:45', 3, '', '', '2026-03-30 16:12:45'),
(291, 'Kiki Gerritzen', 'kiki.gerritzen1@student.graafschapcollege.nl', '0682879680', 10.00, 'betaald', NULL, '2026-03-30 17:10:52', 3, '', '', '2026-03-30 17:10:52'),
(292, 'Noah Jagtenberg', 'noah.jagtenberg@student.graafschapcollege.nl', '0610319097', 10.00, 'betaald', NULL, '2026-03-30 17:15:22', 3, '', '', '2026-03-30 17:15:22'),
(293, 'iris vaartjes', 'iris.vaartjes@student.graafschapcollege.nl', '0613862737', 10.00, 'betaald', NULL, '2026-03-31 07:50:56', 3, '', '', '2026-03-31 07:50:56'),
(294, 'Lynn Verhaegh', 'lynn.verhaegh@student.graafschapcollege.nl', '0615615085', 10.00, 'betaald', NULL, '2026-03-31 14:19:49', 3, '', '', '2026-03-31 14:19:49'),
(295, 'Gydo Evers', 'gydo.evers@student.graafschapcollege.nl', '0657906205', 10.00, 'betaald', NULL, '2026-03-31 21:11:42', 3, '', '', '2026-03-31 21:11:42'),
(296, 'Lysan Westrum', 'lysan.westrum@student.graafschapcollege.nl', '0682113014', 10.00, 'betaald', NULL, '2026-03-31 21:18:30', 3, '', '', '2026-03-31 21:18:30'),
(297, 'morris putman', 'morris.putman@student.graafschapcollege.nl', '0683610884', 10.00, 'betaald', NULL, '2026-04-01 08:00:18', 3, '', '', '2026-04-01 08:00:18'),
(298, 'Tess Eijsink', 'tess.eijsink@student.graafschapcollege.nl', '0682396185', 10.00, 'betaald', NULL, '2026-04-01 12:09:13', 3, '', '', '2026-04-01 12:09:13'),
(299, 'Sascha Heijting', 'sascha.heijting@student.graafschapcollege.nl', '0642640210', 10.00, 'betaald', NULL, '2026-04-01 12:10:12', 3, '', '', '2026-04-01 12:10:12'),
(300, 'Shelsey Van soest', 'shelsey.soest@student.graafschapcollege.nl', '0610043869', 10.00, 'betaald', NULL, '2026-04-01 15:47:20', 3, '', '', '2026-04-01 15:47:20'),
(301, 'Roos Nijkamp', 'roos.nijkamp@student.graafschapcollege.nl', '0683298002', 10.00, 'betaald', NULL, '2026-04-02 06:17:31', 3, '', '', '2026-04-02 06:17:31'),
(302, 'Bart Aalbers', 'bart.aalbers@student.graafschapcollege.nl', '0619553029', 10.00, 'betaald', NULL, '2026-04-02 11:07:08', 3, '', '', '2026-04-02 11:07:08'),
(303, 'Daan van Dijk', 'daan.dijk@student.graafschapcollege.nl', '0622681903', 10.00, 'betaald', NULL, '2026-04-02 11:07:43', 3, '', '', '2026-04-02 11:07:43'),
(304, 'gido wikkerink', 'gido.wikkerink@student.graafschapcollege.nl', '0638053551', 10.00, 'betaald', NULL, '2026-04-02 11:08:22', 3, '', '', '2026-04-02 11:08:22'),
(305, 'Syb Boumeester', 'sam.chevalking@student.graafschapcollege.nl', '0683896546', 10.00, 'betaald', NULL, '2026-04-02 12:27:22', 3, '', '', '2026-04-02 12:27:22'),
(307, 'Fouad Etber', 'fouad.etber@student.graafschapcollege.nl', '0683915214', 10.00, 'betaald', NULL, '2026-04-02 13:25:52', 3, '', '', '2026-04-02 13:25:52'),
(308, 'Xander Nijhof', 'xander.nijhof@student.graafschapcollege.nl', '0647297259', 10.00, 'betaald', NULL, '2026-04-02 16:16:02', 3, '', '', '2026-04-02 16:16:02'),
(310, 'Joost Sloetjes', 'joost.sloetjes@student.graafschapcollege.nl', '0642645492', 10.00, 'betaald', NULL, '2026-04-02 18:01:32', 3, '', '', '2026-04-02 18:01:32'),
(311, 'Dick pans ', 'Dickpans@hotmail.com', '0642303666', 27.50, 'betaald', NULL, '2026-04-06 15:18:08', 12, '', '', '2026-04-06 15:18:08');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `party_events`
--

CREATE TABLE `party_events` (
  `id` int(6) UNSIGNED NOT NULL,
  `naam` varchar(50) NOT NULL,
  `datum` date NOT NULL,
  `status` varchar(20) DEFAULT 'actief',
  `vertrektijd` time DEFAULT '00:00:00',
  `locatie` varchar(255) DEFAULT 'Onbekend',
  `prijs` decimal(10,2) DEFAULT 0.00,
  `max_tickets` int(11) DEFAULT 100,
  `ticket_info` text DEFAULT NULL,
  `reis_type` varchar(20) DEFAULT 'retour',
  `is_active` tinyint(1) DEFAULT 1,
  `afbeelding` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `party_events`
--

INSERT INTO `party_events` (`id`, `naam`, `datum`, `status`, `vertrektijd`, `locatie`, `prijs`, `max_tickets`, `ticket_info`, `reis_type`, `is_active`, `afbeelding`, `is_archived`) VALUES
(1, 'City Lido 2026', '2026-02-16', 'inactief', '02:00:00', 'City Lido, Groenlo', 12.00, 60, 'Deze ticket is voor een enkele reis van Groenlo, City Lido naar Vorden en Zutphen. Het gaat om de nacht van 15-2 op 16-2-2026. Wees op tijd, de bus vertrekt om 02:00 uur. ', 'enkel_terug', 0, NULL, 1),
(3, 'Feest Graafschap College 2026', '2026-04-03', 'actief', '02:15:00', 'De Radstake', 10.00, 200, '', '0', 0, NULL, 1),
(4, 'Snollebollekes Gelre Dome 2026', '2026-03-21', 'actief', '23:30:00', 'Gelre Dome Arnhem', 25.00, 19, 'Bende gij weer klaar voor hét meespring feest van ’t jaar?! De grote, gekke en onverantwoord gezellige show? Kom dan op 21 maart naar Snollebollekes Live in Concert in het GelreDome! Al jouw favoriete feestartiesten bij elkaar onder leiding van Snollebollekes.\r\n\r\nVervoer per touringcar vanaf diverse plekken rondom Zutphen naar Gelredome en terug. Planning is om iedereen om 19:00 uur bij Gelredome te hebben, vertrek is een half uur na het concert, dit is ongeveer 23:30 uur.', '1', 1, NULL, 1),
(6, 'Dreamfields 2026', '2026-07-11', 'actief', '00:30:00', 'Dreamfields, Rhederlaag, De Muggenwaard, 6988 BX Lathum', 25.00, 150, 'Busticket vervoer per touringcar naar Dreamfields Festival Lathum 11-7-2026 v.v. Retour is om 0:30 uur', '1', 1, '1771597919_Samen naar Dreamfields!.png', 0),
(8, 'Dieka ', '2026-03-07', 'actief', '02:30:00', 'Dieka ', 20.00, 60, 'Bustickets voor vervoer per touringcar naar Dieka heen en terug. Retour is exact 02:30 uur, zorg dat je op tijd bent!  \r\n\r\n', '1', 1, NULL, 1),
(9, 'Kole Kermis Broekland ', '2026-03-21', 'actief', '02:00:00', 'Kole Kermse, Pereland 9, 8107 BM Broekland', 30.00, 20, '', '1', 1, NULL, 1),
(11, 'Zwarte Cross Vrijdag', '2026-07-17', 'actief', '00:30:00', 'Zwarte Cross', 27.50, 100, 'Busticket vervoer per touringcar naar Zwarte Cross Vrijdag editie 17-07-2026 v.v. Retour is om 0:30 uur', '1', 1, '1774274918_ZC_2026.jpg', 0),
(12, 'Zwarte Cross Zaterdag', '2026-07-18', 'actief', '00:30:00', 'Zwarte Cross', 27.50, 100, 'Busticket vervoer per touringcar naar Zwarte Cross zaterdag editie 18-07-2026 v.v. Retour is om 0:30 uur', '1', 1, '1774275263_ZC_2026.jpg', 0),
(13, 'Zwarte Cross Zondag', '2026-07-19', 'actief', '00:30:00', 'Zwarte Cross', 27.50, 50, 'Busticket vervoer per touringcar naar Zwarte Cross zaterdag editie 19-07-2026 v.v. Retour is om 0:30 uur', '1', 1, '1774275507_ZC_2026.jpg', 0);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `party_haltes_bibliotheek`
--

CREATE TABLE `party_haltes_bibliotheek` (
  `id` int(6) UNSIGNED NOT NULL,
  `naam` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `party_haltes_bibliotheek`
--

INSERT INTO `party_haltes_bibliotheek` (`id`, `naam`) VALUES
(1, 'Vorden, Halte Horsterkamp'),
(3, 'Brummen, Marktplein'),
(4, 'Klarenbeek, Centrum'),
(6, 'Witkmap Laren'),
(7, 'Voorst, DCO'),
(8, 'Argos Emmerikseweg Zutphen'),
(9, 'Dieren, Station'),
(11, 'Wilp, Ardeweg 1 Watertap'),
(12, 'Terwolde, Kadijk 8'),
(15, 'Ik wil alleen terug'),
(16, 'Zutphen, busstation'),
(17, 'Klarenbeek, Plus'),
(18, 'Warnsveld, 13:15 uur Kerkhofweg');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `party_locaties`
--

CREATE TABLE `party_locaties` (
  `id` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `adres` varchar(255) DEFAULT NULL,
  `maps_link` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `party_locaties`
--

INSERT INTO `party_locaties` (`id`, `naam`, `adres`, `maps_link`) VALUES
(1, 'Dieka ', 'Kruusweg 1 8051PC  , Markelo', ''),
(2, 'Zwarte Cross', 'Hamelandweg, Lichtenvoorde', '');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `party_opstap_locaties`
--

CREATE TABLE `party_opstap_locaties` (
  `id` int(6) UNSIGNED NOT NULL,
  `event_id` int(6) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `tijd` time NOT NULL,
  `prijs` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `party_opstap_locaties`
--

INSERT INTO `party_opstap_locaties` (`id`, `event_id`, `naam`, `tijd`, `prijs`) VALUES
(7, 1, 'Vorden, Halte Horsterkamp', '00:00:00', 12.00),
(8, 1, 'Zutphen, Station', '00:00:00', 12.00),
(9, 2, 'Brummen, Marktplein', '12:30:00', 26.00),
(10, 2, 'Zutphen, Station', '13:00:00', 26.00),
(12, 3, 'Aalten (NS station)', '02:15:00', 10.00),
(13, 3, 'Baak (Bushalte Dorp)', '02:15:00', 10.00),
(14, 3, 'Beek (Tankstation Firezone)', '02:15:00', 10.00),
(15, 3, 'Beltrum (Cafe Dute)', '02:15:00', 10.00),
(16, 3, 'Didam (NS Station)', '02:15:00', 10.00),
(17, 3, 'Drempt', '02:15:00', 10.00),
(18, 3, 'Doesburg (Kraakselaan)', '02:15:00', 10.00),
(19, 3, 'Doetinchem (NS CS station)', '02:15:00', 10.00),
(20, 3, 'Duiven (NS Station)', '02:15:00', 10.00),
(21, 3, 'Eibergen (Bushalte Viersprong (bij Het Assink))', '02:15:00', 10.00),
(22, 3, 'Etten Gld. (Cafe Tiemessen)', '02:15:00', 10.00),
(23, 3, 'Gaanderen (Cafe Harttjes)', '02:15:00', 10.00),
(24, 3, 'Gendringen (Kerkplein)', '02:15:00', 10.00),
(25, 3, 'Groenlo (Busstation)', '02:15:00', 10.00),
(26, 3, 'Haaksbergen (Busstation)', '02:15:00', 10.00),
(27, 3, 'Hummelo (Bushalte Brede School)', '02:15:00', 10.00),
(28, 3, 'Lichtenvoorde (Bushalte Twente route)', '02:15:00', 10.00),
(29, 3, 'Neede (Busstation)', '02:15:00', 10.00),
(30, 3, 'Ruurlo (NS station)', '02:15:00', 10.00),
(31, 3, '\'s-Heerenberg (Autobedrijf Arendsen)', '02:15:00', 10.00),
(32, 3, 'Silvolde (Bushalte Berkenlaan)', '02:15:00', 10.00),
(33, 3, 'Steenderen (Bushalte Dorp)', '02:15:00', 10.00),
(34, 3, 'Ulft (Bushalte DRU)', '02:15:00', 10.00),
(35, 3, 'Vorden (Bushalte De Horsterkamp Zuid)', '02:15:00', 10.00),
(36, 3, 'Wehl (NS station)', '02:15:00', 10.00),
(37, 3, 'Zevenaar (NS)', '02:15:00', 10.00),
(38, 3, 'Winterswijk (NS station)', '02:15:00', 10.00),
(39, 3, 'Zutphen (NS)', '02:15:00', 10.00),
(40, 4, 'Zutphen, Station', '18:00:00', 25.00),
(41, 4, 'Brummen, Marktplein', '18:30:00', 25.00),
(42, 5, 'Witkmap Laren', '21:30:00', 17.50),
(47, 7, 'Witkmap Laren', '21:00:00', 17.50),
(49, 4, 'Argos Emmerikseweg Zutphen', '18:15:00', 25.00),
(50, 8, 'Dieren, Station', '20:30:00', 20.00),
(51, 8, 'Zutphen, Station', '21:00:00', 20.00),
(52, 8, 'Vorden, Halte Horsterkamp', '21:30:00', 20.00),
(53, 9, 'Wilp, Ardeweg 1 Watertap', '21:00:00', 30.00),
(54, 9, 'Terwolde, Kadijk 8', '21:15:00', 30.00),
(55, 6, 'Zutphen, Station', '13:00:00', 27.50),
(56, 6, 'Voorst, DCO', '13:15:00', 27.50),
(57, 6, 'Brummen, Marktplein', '13:30:00', 27.50),
(62, 6, 'Ik wil alleen terug', '00:30:00', 27.50),
(64, 11, 'Zutphen, busstation', '13:00:00', 27.50),
(65, 11, 'Klarenbeek, Plus', '13:30:00', 27.50),
(66, 11, 'Vorden, Halte Horsterkamp', '13:45:00', 27.50),
(67, 11, 'Ik wil alleen terug', '00:30:00', 27.50),
(68, 12, 'Zutphen, busstation', '13:00:00', 27.50),
(69, 12, 'Warnsveld, 13:15 uur Kerkhofweg', '13:15:00', 27.50),
(70, 12, 'Vorden, Halte Horsterkamp', '13:30:00', 27.50),
(71, 12, 'Ik wil alleen terug', '00:30:00', 27.50),
(72, 13, 'Klarenbeek, Plus', '12:45:00', 27.50),
(73, 13, 'Zutphen, busstation', '13:00:00', 27.50);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ritgegevens`
--

CREATE TABLE `ritgegevens` (
  `id` int(11) NOT NULL,
  `chauffeur_naam` varchar(100) DEFAULT NULL,
  `voertuig_nummer` varchar(50) DEFAULT NULL,
  `datum` date NOT NULL,
  `type_dienst` varchar(50) NOT NULL,
  `opmerkingen` text DEFAULT NULL,
  `ingediend_op` datetime DEFAULT current_timestamp(),
  `totaal_km` int(11) DEFAULT 0,
  `status` enum('nieuw','verwerkt') DEFAULT 'nieuw',
  `bron_type` varchar(50) DEFAULT NULL,
  `bron_id` int(11) DEFAULT NULL,
  `adhoc_klant` varchar(255) DEFAULT NULL,
  `adhoc_route` varchar(255) DEFAULT NULL,
  `adhoc_prijs` decimal(10,2) DEFAULT NULL,
  `is_gefactureerd` tinyint(1) DEFAULT 0,
  `factuur_datum` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `ritgegevens`
--

INSERT INTO `ritgegevens` (`id`, `chauffeur_naam`, `voertuig_nummer`, `datum`, `type_dienst`, `opmerkingen`, `ingediend_op`, `totaal_km`, `status`, `bron_type`, `bron_id`, `adhoc_klant`, `adhoc_route`, `adhoc_prijs`, `is_gefactureerd`, `factuur_datum`) VALUES
(1, 'Fred Stravers', '60', '2026-02-09', 'Breng en haal', 'Bus was goed', '2026-02-07 23:43:44', 330, 'verwerkt', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(2, 'Gerard Hellewegen', '50', '2026-02-08', 'Enkele rit', 'Goed gegaan', '2026-02-07 23:46:18', 150, 'verwerkt', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(3, 'Fred Stravers', '60', '2026-02-08', 'Enkele rit', 'Ging goed', '2026-02-08 15:36:19', 60, 'verwerkt', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(4, 'Freddy', '18', '2026-03-09', 'Straattaxi', '', '2026-03-09 23:24:15', 0, 'verwerkt', 'adhoc', NULL, 'Pieterse', 'Zutphen | brummen | Contant', 25.00, 0, NULL),
(5, 'Angelique Everts', '17', '2026-03-12', 'Straattaxi', '', '2026-03-12 10:33:18', 0, 'verwerkt', 'adhoc', NULL, 'janus', 'polbeek | station | Contant', 20.00, 0, NULL),
(6, 'Freddy', '17', '2026-03-18', 'Straattaxi', NULL, '2026-03-18 14:37:59', 0, 'nieuw', 'adhoc', NULL, 'Tactus wd', 'Gelre naar Willem dreesstraat (Betaling: Op Rekening)', 11.55, 0, NULL),
(7, 'Jan ', '23', '2026-03-18', 'Dagbesteding', NULL, '2026-03-18 15:34:43', 0, 'nieuw', 'adhoc', NULL, 'Radeland', 'Route Radeland 2 (Betaling: Op Rekening)', 182.50, 0, NULL),
(8, 'Jan ', '23', '2026-03-18', 'Dagbesteding', NULL, '2026-03-18 15:35:40', 0, 'nieuw', 'adhoc', NULL, 'Radeland 2', 'Radeland 2 (Betaling: Op Rekening)', 182.50, 0, NULL),
(9, 'Jan ', '18', '2026-03-18', 'Straattaxi', NULL, '2026-03-18 15:36:38', 0, 'nieuw', 'adhoc', NULL, 'Tactus', 'Apeldoorn-IBK (Betaling: Op Rekening)', 91.95, 0, NULL),
(10, 'Jan ', '18', '2026-03-18', 'Straattaxi', NULL, '2026-03-18 15:37:59', 0, 'nieuw', 'adhoc', NULL, 'Mw.', 'Lunette5-zh (Betaling: PIN)', 22.20, 0, NULL),
(11, 'Jan ', '18', '2026-03-18', 'Straattaxi', NULL, '2026-03-18 15:39:08', 0, 'nieuw', 'adhoc', NULL, 'IBK Mw.Isa', 'IBK-ZH (Betaling: Op Rekening)', 12.75, 0, NULL),
(12, 'Jan ', '18', '2026-03-18', 'Straattaxi', NULL, '2026-03-18 15:39:48', 0, 'nieuw', 'adhoc', NULL, 'Mw.', 'Gelre zh (Betaling: PIN)', 22.00, 0, NULL),
(13, 'Jan ', '23', '2026-03-19', 'Dagbesteding', NULL, '2026-03-19 08:04:22', 0, 'nieuw', 'adhoc', NULL, 'Radeland', 'Radeland 2 + Elzebos (Betaling: Op Rekening)', 182.50, 0, NULL),
(14, 'Jan ', '23', '2026-03-19', 'Straattaxi', NULL, '2026-03-19 15:09:11', 0, 'nieuw', 'adhoc', NULL, '3 p', 'Ns-Boggelaar (Betaling: Contant)', 27.70, 0, NULL),
(15, 'Jan ', '23', '2026-03-19', 'Dagbesteding', NULL, '2026-03-19 15:09:48', 0, 'nieuw', 'adhoc', NULL, 'Radeland', 'Radeland 2 (Betaling: Op Rekening)', 182.50, 0, NULL),
(16, 'Freddy', '17', '2026-03-19', 'Straattaxi', NULL, '2026-03-19 15:58:19', 0, 'nieuw', 'adhoc', NULL, 'Tactus', 'Wilgestraat 13, Zutphen, Nederland ➡️ Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland (Betaling: Contant)', 15.00, 0, NULL),
(17, 'Freddy', '17', '2026-03-19', 'Straattaxi', NULL, '2026-03-19 16:23:26', 0, 'nieuw', 'adhoc', NULL, 'Tactus', '[17:00] Jan Vermeerstraat 13, Zutphen, Nederland ➡️ GelreDome, Batavierenweg, Arnhem, Nederland (Betaling: Op Rekening)', 50.00, 0, NULL),
(18, 'Freddy', '17', '2026-03-20', 'Straattaxi', NULL, '2026-03-20 12:53:16', 0, 'nieuw', 'adhoc', NULL, '?', '[12:52] Station Zutphen, Zutphen, Nederland ➡️ Crematorium De Omarming, Voorsterallee, Zutphen, Nederland (Betaling: Contant)', 18.60, 0, NULL),
(19, 'Freddy', '17', '2026-03-20', 'Straattaxi', NULL, '2026-03-20 13:18:14', 0, 'nieuw', 'adhoc', NULL, '?', '[13:17] Station Zutphen, Zutphen, Nederland ➡️ Algemene Begraafplaats, Baakseweg, Vorden, Nederland (Betaling: PIN)', 28.45, 0, NULL),
(20, 'Freddy', '17', '2026-03-20', 'Straattaxi', NULL, '2026-03-20 13:19:11', 0, 'nieuw', 'adhoc', NULL, '?', '[13:18] Algemene Begraafplaats, Baakseweg, Vorden, Nederland ➡️ Station Zutphen, Zutphen, Nederland (Betaling: PIN)', 28.45, 0, NULL),
(21, 'Freddy', '17', '2026-03-20', 'Straattaxi', NULL, '2026-03-20 15:46:53', 0, 'nieuw', 'adhoc', NULL, '.', '[15:46] Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland ➡️ Steenderen, Nederland (Betaling: Contant)', 29.05, 0, NULL),
(22, 'Jan ', '23', '2026-03-23', 'Dagbesteding', NULL, '2026-03-23 07:16:31', 0, 'nieuw', 'adhoc', NULL, 'Radeland2', '[08:15] Loenen, Nederland ➡️ Brummen, Nederland (Betaling: Op Rekening)', 182.50, 0, NULL),
(23, 'Jan ', '18', '2026-03-23', 'Straattaxi', NULL, '2026-03-23 11:27:13', 0, 'nieuw', 'adhoc', NULL, 'COA school azc', '[11:25] Voorsterallee, Zutphen, Nederland ➡️ De Betteld, Aaltenseweg, Zelhem, Nederland (Betaling: Op Rekening)', 97.30, 0, NULL),
(24, 'Jan ', '18', '2026-03-23', 'Straattaxi', NULL, '2026-03-23 11:54:58', 0, 'nieuw', 'adhoc', NULL, '2 p.', '[11:53] Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland ➡️ Bagijnenland, Zutphen, Nederland (Betaling: PIN)', 10.85, 0, NULL),
(25, 'Jan ', '18', '2026-03-23', 'Straattaxi', NULL, '2026-03-23 13:33:43', 0, 'nieuw', 'adhoc', NULL, '2 p.', '[14:15] Martinetsingel, Zutphen, Nederland ➡️ Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland (Betaling: PIN)', 14.65, 0, NULL),
(26, 'Jan ', '23', '2026-03-23', 'Dagbesteding', '', '2026-03-23 14:28:03', 0, 'verwerkt', 'adhoc', NULL, 'Radeland2', '[15:30] Brummen, Nederland ➡️ Eerbeek, Nederland (Betaling: Op Rekening)', 182.50, 0, NULL),
(27, 'Jan ', '23', '2026-03-23', 'Straattaxi', NULL, '2026-03-23 15:44:06', 0, 'nieuw', 'adhoc', NULL, '2p.', '[16:30] Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland ➡️ Martinetsingel, Zutphen, Nederland (Betaling: PIN)', 14.65, 0, NULL),
(28, 'Freddy', '17', '2026-03-24', 'Straattaxi', NULL, '2026-03-24 12:55:18', 0, 'nieuw', 'adhoc', NULL, 'Tactus', '[12:54] Willem Dreesstraat, Zutphen, Nederland ➡️ Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland (Betaling: Contant)', 50.00, 0, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ritregels`
--

CREATE TABLE `ritregels` (
  `id` int(11) NOT NULL,
  `rit_id` int(11) NOT NULL,
  `tijd` varchar(10) DEFAULT NULL,
  `omschrijving` varchar(100) DEFAULT NULL,
  `van_adres` varchar(255) DEFAULT NULL,
  `naar_adres` varchar(255) DEFAULT NULL,
  `km_stand` int(11) DEFAULT NULL,
  `bedrag` decimal(10,2) DEFAULT 0.00,
  `betaalwijze` varchar(20) DEFAULT NULL,
  `klant_naam` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `ritregels`
--

INSERT INTO `ritregels` (`id`, `rit_id`, `tijd`, `omschrijving`, `van_adres`, `naar_adres`, `km_stand`, `bedrag`, `betaalwijze`, `klant_naam`) VALUES
(1, 1, '08:00', 'Vertrek garage (Heen)', NULL, NULL, 1020, 0.00, NULL, NULL),
(2, 1, '08:30', 'Vertrek klant', NULL, NULL, 1050, 0.00, NULL, NULL),
(3, 1, '10:00', 'Aankomst bestemming', NULL, NULL, 1150, 0.00, NULL, NULL),
(4, 1, '11:15', 'Retour garage', NULL, NULL, 1200, 0.00, NULL, NULL),
(5, 1, '12:00', 'Vertrek garage (Terug)', NULL, NULL, 1225, 0.00, NULL, NULL),
(6, 1, '13:15', 'Vertrek bestemming', NULL, NULL, 1250, 0.00, NULL, NULL),
(7, 1, '14:45', 'Retour klant', NULL, NULL, 1300, 0.00, NULL, NULL),
(8, 1, '16:15', 'Retour garage', NULL, NULL, 1350, 0.00, NULL, NULL),
(9, 2, '08:00', 'Vertrek garage', NULL, NULL, 2000, 0.00, NULL, NULL),
(10, 2, '08:30', 'Vertrek klant', NULL, NULL, 2050, 0.00, NULL, NULL),
(11, 2, '09:15', 'Aankomst bestemming', NULL, NULL, 2100, 0.00, NULL, NULL),
(12, 2, '09:45', 'Retour garage', NULL, NULL, 2150, 0.00, NULL, NULL),
(13, 3, '08:00', 'Vertrek garage', NULL, NULL, 1000, 0.00, NULL, NULL),
(14, 3, '08:15', 'Vertrek klant', NULL, NULL, 1020, 0.00, NULL, NULL),
(15, 3, '09:15', 'Aankomst bestemming', NULL, NULL, 1040, 0.00, NULL, NULL),
(16, 3, '10:00', 'Retour garage', NULL, NULL, 1060, 0.00, NULL, NULL),
(17, 5, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(19, 7, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(20, 8, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(21, 9, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(22, 10, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(23, 11, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(24, 12, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(25, 13, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(26, 14, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(27, 15, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(28, 16, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(29, 17, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(30, 18, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(31, 19, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(32, 20, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(33, 21, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(34, 22, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(35, 23, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(36, 24, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(37, 25, '08:00:00', 'Radeland 1', 'Lochem, Zutphen', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(38, 26, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(39, 27, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(40, 28, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(41, 29, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(42, 30, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(43, 31, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(44, 32, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(45, 33, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(46, 34, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(47, 35, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(48, 36, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(49, 37, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(50, 38, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(51, 39, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(52, 40, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(53, 41, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(54, 42, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(55, 43, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(56, 44, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(57, 45, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(58, 46, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(59, 47, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(60, 48, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(61, 49, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(62, 50, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(63, 51, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(64, 52, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(65, 53, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(66, 54, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(67, 55, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(68, 56, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(69, 57, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(70, 58, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(71, 59, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(72, 60, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(73, 61, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(74, 62, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(75, 63, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(76, 64, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(77, 65, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(78, 66, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(79, 67, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(80, 68, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(81, 69, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(82, 70, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(83, 71, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(84, 72, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(85, 73, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(86, 74, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(87, 75, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(88, 76, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(89, 77, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(90, 78, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(91, 79, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(92, 80, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(93, 81, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(94, 82, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(95, 83, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(96, 84, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(97, 85, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(98, 86, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(99, 87, '16:30:00', 'Kiezelbrink', 'Reubezorg', 'Polbeek', NULL, 0.00, NULL, NULL),
(100, 88, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(101, 89, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(102, 90, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(103, 91, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(104, 92, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(105, 93, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(106, 94, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(107, 95, '07:45:00', 'Radeland 2', 'Eerbeek', 'Radeland 3 Brummen', NULL, 0.00, NULL, NULL),
(108, 96, '09:00:00', 'Broekland', 'De Bosrand 3 7207 ME Zutphen', 'De Horsthoeve, Horstweg 15, 8107 AA Broekland', NULL, 0.00, NULL, NULL),
(109, 97, '09:00:00', 'Broekland', 'De Bosrand 3 7207 ME Zutphen', 'De Horsthoeve, Horstweg 15, 8107 AA Broekland', NULL, 0.00, NULL, NULL),
(110, 98, '09:00:00', 'Broekland', 'De Bosrand 3 7207 ME Zutphen', 'De Horsthoeve, Horstweg 15, 8107 AA Broekland', NULL, 0.00, NULL, NULL),
(111, 99, '09:00:00', 'Broekland', 'De Bosrand 3 7207 ME Zutphen', 'De Horsthoeve, Horstweg 15, 8107 AA Broekland', NULL, 0.00, NULL, NULL),
(112, 100, '11:15:00', 'Martinus Bussloo', 'Deventerweg 18 Voorst', 'Kerkstraat 18 Wilp', NULL, 0.00, NULL, NULL),
(113, 101, '11:15:00', 'Martinus Bussloo', 'Deventerweg 18 Voorst', 'Kerkstraat 18 Wilp', NULL, 0.00, NULL, NULL),
(114, 102, '08:30:00', 'AZC Zwemmen', 'AZC Voorsterallee', 'Zwembad Zutphen', NULL, 0.00, NULL, NULL),
(115, 103, '08:30:00', 'AZC Zwemmen', 'AZC Voorsterallee', 'Zwembad Zutphen', NULL, 0.00, NULL, NULL),
(116, 106, NULL, 'Taxirit (Jansen)', 'Wilgestraat, Zutphen, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(117, 107, NULL, 'Taxirit (Tactus Ibk Julian)', 'Willem Dreesstraat, Zutphen, Nederland', 'Rekken, Nederland', NULL, 0.00, NULL, NULL),
(118, 108, NULL, 'Taxirit (Radeland 2)', 'Brummen, Nederland', 'Eerbeek, Nederland', NULL, 0.00, NULL, NULL),
(119, 109, NULL, 'Touringcarrit (De Waaier)', 'Zutphen, Nederland', 'Apeldoorn, Nederland', NULL, 0.00, NULL, NULL),
(120, 110, NULL, 'Taxirit (.)', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', 'Coehoornsingel, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(121, 111, NULL, 'Taxirit (A)', 'Sensire - De Lunette, Coehoornsingel, Zutphen, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(122, 112, NULL, 'Taxirit (Radeland 2)', 'Brummen, Nederland', 'Eerbeek, Nederland', NULL, 0.00, NULL, NULL),
(123, 113, NULL, 'Taxirit (Suze)', 'De Waarden, Zutphen, Nederland', 'Veenweg 6, Harreveld, Nederland', NULL, 0.00, NULL, NULL),
(124, 114, NULL, 'Taxirit (?)', 'Pelikaanstraat, Zutphen, Nederland', 'Streekziekenhuis Koningin Beatrix Winterswijk, Beatrixpark, Winterswijk, Nederland', NULL, 0.00, NULL, NULL),
(125, 115, NULL, 'Extra Rit (Handmatig)', 'Wilgestraat 13', 'Baak', NULL, 0.00, NULL, NULL),
(127, 117, NULL, 'Incidentele Rit', 'Baak, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(128, 118, NULL, 'Retour: Incidentele Rit', 'Zutphen, Nederland', 'Baak, Nederland', NULL, 0.00, NULL, NULL),
(129, 119, NULL, 'Incidentele Rit', 'Baak, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(130, 120, NULL, 'Retour: Incidentele Rit', 'Zutphen, Nederland', 'Baak, Nederland', NULL, 0.00, NULL, NULL),
(131, 121, NULL, 'Incidentele Rit', 'Holten, Nederland', 'Gaanderen, Nederland', NULL, 0.00, NULL, NULL),
(132, 122, NULL, 'Retour: Incidentele Rit', 'Gaanderen, Nederland', 'Holten, Nederland', NULL, 0.00, NULL, NULL),
(133, 123, NULL, 'Taxirit (Van de Ree)', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', 'Coehoornsingel 3, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(134, 124, NULL, 'Taxirit (?)', 'Coehoornsingel, Zutphen, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(135, 125, NULL, 'Touringcarrit (De Waaier)', 'Rijssen, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(136, 126, NULL, 'Touringcarrit (Radeland 2)', 'Brummen, Nederland', 'Eerbeek, Nederland', NULL, 0.00, NULL, NULL),
(137, 127, NULL, 'Incidentele Rit', 'Holten, Nederland', 'Gaanderen, Nederland', NULL, 0.00, NULL, NULL),
(138, 128, NULL, 'Retour: Incidentele Rit', 'Gaanderen, Nederland', 'Holten, Nederland', NULL, 0.00, NULL, NULL),
(139, 129, NULL, 'Incidentele Rit', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', 'Tadamastraat, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(140, 130, NULL, 'Touringcarrit (Radeland 1)', 'Lochem, Nederland', 'Brummen, Nederland', NULL, 0.00, NULL, NULL),
(141, 131, NULL, 'Taxirit (A)', 'Station Zutphen, Zutphen, Nederland', 'ForFarmers Group, Kwinkweerd, Lochem, Nederland', NULL, 0.00, NULL, NULL),
(142, 132, NULL, 'Taxirit (Radeland 1)', 'Brummen, Nederland', 'Loch Ness, Verenigd Koninkrijk', NULL, 0.00, NULL, NULL),
(143, 133, NULL, 'Taxirit (Kiezebrink)', 'Lochem, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(144, 134, NULL, 'Touringcarrit (MFE)', 'Loubergweg, Eerbeek, Nederland', 'Preston Palace, Laan van Iserlohn, Almelo, Nederland', NULL, 0.00, NULL, NULL),
(145, 135, NULL, 'Taxirit (?)', 'Boggelderenk, Zutphen, Nederland', 'Amersfoort, Nederland', NULL, 0.00, NULL, NULL),
(146, 136, NULL, 'Taxirit (l)', 'Warnsveld, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(147, 137, NULL, 'Taxirit (.)', 'Warnsveld, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(148, 138, NULL, 'Taxirit (Losse Instapper)', 'Baak, Nederland', 'Vorden, Nederland', NULL, 0.00, NULL, NULL),
(149, 139, NULL, 'Taxirit (Losse Instapper)', 'Markelo, Nederland', 'Markelo, Nederland', NULL, 0.00, NULL, NULL),
(150, 140, NULL, 'Taxirit (Fred Stravers)', 'baak', 'Vorden, Nederland', NULL, 0.00, NULL, NULL),
(151, 141, NULL, 'Taxirit (Fred Stravers)', 'baak', 'Doetinchem, Nederland', NULL, 0.00, NULL, NULL),
(152, 142, NULL, 'rolstoeltaxi', 'Siza De Lunette, Coehoornsingel, Zutphen, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(153, 143, NULL, 'Incidentele Rit', 'Veluwse Poort | groepsuitjes - activiteiten - vergaderen, Admiraal Helfrichlaan, Dieren, Nederland', 'Taxi Berkhout, Industrieweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(154, 145, NULL, 'Celesta Dozeman', 'Piet Roordakliniek, Verlengde Ooyerhoekseweg, Zutphen, Nederland', 'Ooiweg 38, Apeldoorn, Nederland', NULL, 0.00, NULL, NULL),
(155, 146, NULL, 'Touringcarrit (Werk en begeleiding)', 'Lochem, Nederland', 'Radeland, Brummen, Nederland', NULL, 0.00, NULL, NULL),
(156, 147, NULL, 'Incidentele Rit', 'Wilgestraat 13, Zutphen, Nederland', 'Gelre ziekenhuizen Zutphen, Den Elterweg, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(157, 148, NULL, 'Incidentele Rit', '\'t Stationskoffiehuis cafe Radstaake, Rijksstraatweg, Voorst Gem Voorst, Nederland', 'Kadijk, Terwolde, Nederland', NULL, 0.00, NULL, NULL),
(158, 149, NULL, 'Retour: Incidentele Rit', 'Kadijk, Terwolde, Nederland', '\'t Stationskoffiehuis cafe Radstaake, Rijksstraatweg, Voorst Gem Voorst, Nederland', NULL, 0.00, NULL, NULL),
(159, 151, NULL, 'Fred  Stravers', 'Baak, Nederland', 'Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(160, 152, NULL, NULL, 'Industrieweg 95, Zutphen', 'CVV Germanicus, Sportlaan, Coevorden, Nederland', NULL, 0.00, NULL, NULL),
(161, 153, NULL, 'Touringcarrit (Werk en begeleiding)', 'Lochem, Nederland', 'Radeland, Brummen, Nederland', NULL, 0.00, NULL, NULL),
(162, 154, NULL, 'Taxirit (Munckhof)', 'De IJsselslag, Laan naar Eme, Zutphen, Nederland', 'Asielzoekerscentrum (AZC) Zutphen, Voorsterallee, Zutphen, Nederland', NULL, 0.00, NULL, NULL),
(163, 155, NULL, 'Touringcarrit (Werk en begeleiding)', 'Radeland 3, Brummen, Nederland', 'Lochem, Nederland', NULL, 0.00, NULL, NULL),
(164, 157, NULL, NULL, 'Industrieweg 95, Zutphen', 'Baak, Nederland', NULL, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `ritten`
--

CREATE TABLE `ritten` (
  `id` int(11) NOT NULL,
  `calculatie_id` int(11) DEFAULT NULL,
  `klant_id` int(11) DEFAULT NULL,
  `voertuig_type` varchar(50) DEFAULT NULL,
  `chauffeur_id` int(11) DEFAULT NULL,
  `dienst_id` int(11) DEFAULT NULL,
  `voertuig_id` int(11) DEFAULT NULL,
  `voertuig_categorie_wens` int(11) DEFAULT NULL,
  `paraplu_volgnummer` int(11) DEFAULT 1,
  `is_hoofdrit` tinyint(1) NOT NULL DEFAULT 0,
  `geschatte_pax` int(11) DEFAULT NULL,
  `datum_start` datetime NOT NULL,
  `datum_eind` datetime DEFAULT NULL,
  `instructies` text DEFAULT NULL,
  `status` enum('gepland','bezig','voltooid') DEFAULT 'gepland',
  `werk_start` varchar(10) DEFAULT NULL,
  `werk_eind` varchar(10) DEFAULT NULL,
  `werk_pauze` int(11) DEFAULT 0,
  `km_start` int(11) DEFAULT NULL,
  `km_eind` int(11) DEFAULT NULL,
  `werkelijke_km` int(11) DEFAULT NULL,
  `betaalwijze` varchar(50) DEFAULT 'Contant',
  `betaald_bedrag` decimal(10,2) DEFAULT NULL,
  `prijsafspraak` decimal(10,2) DEFAULT NULL,
  `werk_notities` text DEFAULT NULL,
  `is_gefactureerd` tinyint(1) NOT NULL DEFAULT 0,
  `pin_status` enum('Te controleren','Akkoord') NOT NULL DEFAULT 'Te controleren',
  `factuur_status` enum('Te factureren','Gefactureerd') NOT NULL DEFAULT 'Te factureren',
  `factuurnummer` varchar(50) DEFAULT NULL,
  `factuur_datum` datetime DEFAULT NULL,
  `geaccepteerd_tijdstip` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `ritten`
--

INSERT INTO `ritten` (`id`, `calculatie_id`, `klant_id`, `voertuig_type`, `chauffeur_id`, `dienst_id`, `voertuig_id`, `voertuig_categorie_wens`, `paraplu_volgnummer`, `is_hoofdrit`, `geschatte_pax`, `datum_start`, `datum_eind`, `instructies`, `status`, `werk_start`, `werk_eind`, `werk_pauze`, `km_start`, `km_eind`, `werkelijke_km`, `betaalwijze`, `betaald_bedrag`, `prijsafspraak`, `werk_notities`, `is_gefactureerd`, `pin_status`, `factuur_status`, `factuurnummer`, `factuur_datum`, `geaccepteerd_tijdstip`) VALUES
(5, NULL, 843, NULL, 1, 37, 5, NULL, 1, 0, NULL, '2026-03-02 08:00:00', '2026-03-02 09:30:00', '', '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 217.50, NULL, '', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(7, NULL, NULL, NULL, 5, NULL, 5, NULL, 1, 0, NULL, '2026-03-05 08:00:00', '2026-03-05 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(8, NULL, NULL, NULL, 20, NULL, 5, NULL, 1, 0, NULL, '2026-03-06 08:00:00', '2026-03-06 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(9, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-09 08:00:00', '2026-03-09 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(10, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-10 08:00:00', '2026-03-10 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(11, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-11 08:00:00', '2026-03-11 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(12, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-12 08:00:00', '2026-03-12 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(13, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-13 08:00:00', '2026-03-13 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(14, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-16 08:00:00', '2026-03-16 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(15, NULL, NULL, NULL, NULL, NULL, 5, NULL, 1, 0, NULL, '2026-03-17 08:00:00', '2026-03-17 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(16, NULL, NULL, NULL, 3, NULL, 6, NULL, 1, 0, NULL, '2026-03-18 08:00:00', '2026-03-18 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(17, NULL, NULL, NULL, 14, NULL, 5, NULL, 1, 0, NULL, '2026-03-19 08:00:00', '2026-03-19 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(18, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-20 08:00:00', '2026-03-20 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(19, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-23 08:00:00', '2026-03-23 09:30:00', '', 'voltooid', '23:19', '23:19', 0, NULL, 550009, NULL, 'Rekening', NULL, NULL, '✅ Vaste rit zonder bijzonderheden afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(20, NULL, 843, NULL, 1, 1, 5, NULL, 1, 0, NULL, '2026-03-24 08:00:00', '2026-03-24 09:30:00', '', '', '11:50', '11:50', 0, NULL, 50000, NULL, 'Op Rekening', 217.50, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(21, NULL, NULL, NULL, 1, 5, 5, NULL, 1, 0, NULL, '2026-03-25 08:00:00', '2026-03-25 09:30:00', '', '', NULL, NULL, 0, NULL, 246564, NULL, 'Op Rekening', 217.50, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(22, NULL, 843, NULL, 1, 8, 5, NULL, 1, 0, NULL, '2026-03-26 08:00:00', '2026-03-26 09:30:00', '', '', NULL, NULL, 0, NULL, 246680, NULL, 'Op Rekening', 217.50, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(23, NULL, NULL, NULL, 5, NULL, 5, NULL, 1, 0, NULL, '2026-03-27 08:00:00', '2026-03-27 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(24, NULL, NULL, NULL, 1, NULL, 7, NULL, 1, 0, NULL, '2026-03-30 08:00:00', '2026-03-30 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-29 13:09:18'),
(25, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-31 08:00:00', '2026-03-31 09:30:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(26, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-16 16:30:00', '2026-03-16 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(27, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-17 16:30:00', '2026-03-17 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(28, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-19 16:30:00', '2026-03-19 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(29, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-20 16:30:00', '2026-03-20 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(30, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-23 16:30:00', '2026-03-23 17:00:00', '', 'voltooid', '23:13', '23:13', 0, NULL, 56000, NULL, 'Rekening', NULL, NULL, '✅ Vaste rit zonder bijzonderheden afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(31, NULL, 8, NULL, 1, 3, 5, NULL, 1, 0, NULL, '2026-03-24 16:30:00', '2026-03-24 17:00:00', '', '', NULL, NULL, 0, NULL, 50000, NULL, 'Op Rekening', 55.00, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Gefactureerd', '2026003', '2026-04-07 21:14:10', NULL),
(32, NULL, NULL, NULL, 6, NULL, 5, NULL, 1, 0, NULL, '2026-03-26 16:30:00', '2026-03-26 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(33, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-27 16:30:00', '2026-03-27 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(34, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-03-30 16:30:00', '2026-03-30 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(35, NULL, NULL, NULL, 1, NULL, 4, NULL, 1, 0, NULL, '2026-03-31 16:30:00', '2026-03-31 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-31 15:10:54'),
(36, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-02 16:30:00', '2026-04-02 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(37, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-03 16:30:00', '2026-04-03 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(38, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-06 16:30:00', '2026-04-06 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(39, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-07 16:30:00', '2026-04-07 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(40, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-09 16:30:00', '2026-04-09 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(41, NULL, NULL, NULL, 12, NULL, 7, NULL, 1, 0, NULL, '2026-04-10 16:30:00', '2026-04-10 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(42, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-13 16:30:00', '2026-04-13 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(43, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-14 16:30:00', '2026-04-14 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(44, NULL, NULL, NULL, 1, 36, 5, NULL, 1, 0, NULL, '2026-04-16 16:30:00', '2026-04-16 17:00:00', '', 'voltooid', NULL, NULL, 0, NULL, 250015, NULL, 'Rekening', NULL, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(45, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-17 16:30:00', '2026-04-17 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(46, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-20 16:30:00', '2026-04-20 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(47, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-21 16:30:00', '2026-04-21 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(48, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-23 16:30:00', '2026-04-23 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(49, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-24 16:30:00', '2026-04-24 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(50, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-27 16:30:00', '2026-04-27 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(51, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-28 16:30:00', '2026-04-28 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(52, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-30 16:30:00', '2026-04-30 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(53, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-01 16:30:00', '2026-05-01 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(54, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-04 16:30:00', '2026-05-04 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(55, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-05 16:30:00', '2026-05-05 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(56, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-07 16:30:00', '2026-05-07 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(57, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-08 16:30:00', '2026-05-08 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(58, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-11 16:30:00', '2026-05-11 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(59, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-12 16:30:00', '2026-05-12 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(60, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-14 16:30:00', '2026-05-14 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(61, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-15 16:30:00', '2026-05-15 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(62, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-18 16:30:00', '2026-05-18 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(63, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-19 16:30:00', '2026-05-19 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(64, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-21 16:30:00', '2026-05-21 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(65, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-22 16:30:00', '2026-05-22 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(66, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-25 16:30:00', '2026-05-25 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(67, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-26 16:30:00', '2026-05-26 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(68, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-28 16:30:00', '2026-05-28 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(69, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-05-29 16:30:00', '2026-05-29 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(70, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-01 16:30:00', '2026-06-01 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(71, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-02 16:30:00', '2026-06-02 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(72, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-04 16:30:00', '2026-06-04 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(73, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-05 16:30:00', '2026-06-05 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(74, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-08 16:30:00', '2026-06-08 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(75, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-09 16:30:00', '2026-06-09 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(76, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-11 16:30:00', '2026-06-11 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(77, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-12 16:30:00', '2026-06-12 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(78, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-15 16:30:00', '2026-06-15 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(79, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-16 16:30:00', '2026-06-16 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(80, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-18 16:30:00', '2026-06-18 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(81, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-19 16:30:00', '2026-06-19 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(82, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-22 16:30:00', '2026-06-22 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(83, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-23 16:30:00', '2026-06-23 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(84, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-25 16:30:00', '2026-06-25 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(85, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-26 16:30:00', '2026-06-26 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(86, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-29 16:30:00', '2026-06-29 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(87, NULL, NULL, NULL, 1, NULL, 5, NULL, 1, 0, NULL, '2026-06-30 16:30:00', '2026-06-30 17:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(88, NULL, NULL, NULL, 3, NULL, 6, NULL, 1, 0, NULL, '2026-03-23 07:45:00', '2026-03-23 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(89, NULL, 843, NULL, 3, 2, 6, NULL, 1, 0, NULL, '2026-03-24 07:45:00', '2026-03-24 09:45:00', '', '', NULL, NULL, 0, NULL, 107682, NULL, 'Op Rekening', 185.00, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(90, NULL, 843, NULL, 3, 6, 6, NULL, 1, 0, NULL, '2026-03-25 07:45:00', '2026-03-25 09:45:00', '', '', NULL, NULL, 0, NULL, 107744, NULL, 'Op Rekening', 187.50, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(91, NULL, NULL, NULL, 21, NULL, 6, NULL, 1, 0, NULL, '2026-03-26 07:45:00', '2026-03-26 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(92, NULL, NULL, NULL, 3, NULL, 6, NULL, 1, 0, NULL, '2026-03-30 07:45:00', '2026-03-30 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(93, NULL, NULL, NULL, 3, NULL, 6, NULL, 1, 0, NULL, '2026-03-31 07:45:00', '2026-03-31 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(94, NULL, NULL, NULL, 3, NULL, 6, NULL, 1, 0, NULL, '2026-04-01 07:45:00', '2026-04-01 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(95, NULL, NULL, NULL, 7, NULL, 5, NULL, 1, 0, NULL, '2026-04-02 07:45:00', '2026-04-02 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-30 14:03:56'),
(96, NULL, NULL, NULL, 20, NULL, 8, NULL, 1, 0, NULL, '2026-03-23 09:00:00', '2026-03-23 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(97, NULL, NULL, NULL, 9, NULL, 8, NULL, 1, 0, NULL, '2026-03-27 09:00:00', '2026-03-27 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(98, NULL, NULL, NULL, 20, NULL, 8, NULL, 1, 0, NULL, '2026-03-30 09:00:00', '2026-03-30 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(99, NULL, NULL, NULL, 20, NULL, 8, NULL, 1, 0, NULL, '2026-04-03 09:00:00', '2026-04-03 09:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(100, NULL, NULL, NULL, 5, NULL, 1, NULL, 1, 0, NULL, '2026-03-25 11:15:00', '2026-03-25 15:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(101, NULL, NULL, NULL, 1, NULL, 4, NULL, 1, 0, NULL, '2026-04-01 11:15:00', '2026-04-01 15:00:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-04-01 10:49:32'),
(102, NULL, NULL, NULL, 3, 7, 1, NULL, 1, 0, NULL, '2026-03-26 08:30:00', '2026-03-26 10:45:00', '', 'voltooid', NULL, NULL, 0, NULL, 279571, NULL, 'Rekening', NULL, NULL, 'Gereden met bus 50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(103, NULL, NULL, NULL, 1, NULL, 1, NULL, 1, 0, NULL, '2026-04-02 08:30:00', '2026-04-02 10:45:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-29 13:12:07'),
(104, 13, NULL, NULL, 1, NULL, 4, 8, 1, 1, 40, '2026-03-26 16:00:00', '2026-03-26 22:36:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(105, 13, NULL, NULL, 16, NULL, 1, 8, 2, 0, 40, '2026-03-26 16:00:00', '2026-03-26 22:36:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(106, NULL, NULL, NULL, 1, 1, 8, NULL, 1, 0, NULL, '2026-03-24 13:12:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', 15.00, NULL, '', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(107, NULL, 648, NULL, 3, 2, 8, NULL, 1, 0, NULL, '2026-03-24 10:00:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, 493774, NULL, 'Op Rekening', 200.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: Tactus Ibk JulianIngevoerde prijs: € 289,30 + 30 min.wachten.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(108, NULL, 843, NULL, 3, 2, 6, NULL, 1, 0, NULL, '2026-03-24 15:30:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 185.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: Radeland 2Ingevoerde prijs: € 182,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(109, NULL, 8, NULL, 3, 6, 2, NULL, 1, 0, NULL, '2026-03-25 09:25:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, 103856, NULL, 'Op Rekening', 50.00, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Gefactureerd', NULL, NULL, NULL),
(110, NULL, NULL, NULL, 1, 5, 8, NULL, 1, 0, NULL, '2026-03-25 10:00:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', 35.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: .Ingevoerde prijs: € 35,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(111, NULL, NULL, NULL, 1, 5, 8, NULL, 1, 0, NULL, '2026-03-25 10:50:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'PIN', 21.60, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: AIngevoerde prijs: € 21,60', 0, 'Akkoord', 'Te factureren', NULL, NULL, NULL),
(112, NULL, 843, NULL, 3, 6, 6, NULL, 1, 0, NULL, '2026-03-25 15:30:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, 10, NULL, 'Op Rekening', 187.50, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: Radeland 2Ingevoerde prijs: € 182,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(113, NULL, 498, NULL, 1, 5, 8, NULL, 1, 0, NULL, '2026-03-25 16:50:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 160.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: SuzeIngevoerde prijs: € 150,00', 0, 'Te controleren', 'Gefactureerd', NULL, NULL, NULL),
(114, NULL, NULL, NULL, 1, 5, 8, NULL, 1, 0, NULL, '2026-03-25 13:52:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'PIN', 125.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: ?Ingevoerde prijs: € 125,00', 0, 'Akkoord', 'Te factureren', NULL, NULL, NULL),
(115, NULL, 8, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-27 12:00:00', '0000-00-00 00:00:00', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Gefactureerd', '2026001', '2026-04-07 21:01:22', NULL),
(117, NULL, NULL, 'Taxi', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-27 12:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 50.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(118, NULL, NULL, 'Taxi', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-27 14:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 50.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(119, NULL, NULL, 'Taxi', 1, NULL, 8, NULL, 1, 0, NULL, '2026-03-27 12:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 50.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(120, NULL, NULL, 'Taxi', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-27 14:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 50.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(121, NULL, NULL, 'Rolstoelbus', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-26 12:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 40.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(122, NULL, NULL, 'Rolstoelbus', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-26 14:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 40.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(123, NULL, NULL, NULL, 1, 8, 8, NULL, 1, 0, NULL, '2026-03-26 09:24:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'PIN', 40.00, NULL, '', 0, 'Akkoord', 'Te factureren', NULL, NULL, NULL),
(124, NULL, NULL, NULL, 1, 8, 8, NULL, 1, 0, NULL, '2026-03-26 10:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', 22.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: ?', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(125, NULL, NULL, NULL, 3, 7, 4, NULL, 1, 0, NULL, '2026-03-26 12:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Touringcarrit\nKlant: De Waaier\nIngevoerde prijs: € 1,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(126, NULL, NULL, NULL, 3, 7, 6, NULL, 1, 0, NULL, '2026-03-26 15:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Touringcarrit\nKlant: Radeland 2\nIngevoerde prijs: € 182,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(127, NULL, NULL, 'Rolstoelbus', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-26 12:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 40.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(128, NULL, NULL, 'Rolstoelbus', NULL, NULL, NULL, NULL, 1, 0, NULL, '2026-03-26 14:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 40.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(129, NULL, NULL, 'Taxi', 1, NULL, 8, NULL, 1, 0, NULL, '2026-03-26 23:45:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Pin', NULL, 20.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(130, NULL, NULL, NULL, 3, 10, 5, NULL, 1, 0, NULL, '2026-03-27 08:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Touringcarrit\nKlant: Radeland 1\nIngevoerde prijs: € 217,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(131, NULL, 8, NULL, 1, 9, 7, NULL, 1, 0, NULL, '2026-03-27 11:28:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 54.25, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: AIngevoerde prijs: € 54,25', 0, 'Te controleren', 'Gefactureerd', '2026002', '2026-04-07 21:06:15', NULL),
(132, NULL, NULL, NULL, 3, 10, 5, NULL, 1, 0, NULL, '2026-03-27 15:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Radeland 1\nIngevoerde prijs: € 217,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(133, NULL, NULL, NULL, 3, 10, 5, NULL, 1, 0, NULL, '2026-03-27 16:40:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Kiezebrink\nIngevoerde prijs: € 49,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(134, NULL, NULL, NULL, 1, 11, 1, NULL, 1, 0, NULL, '2026-03-28 11:19:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 50000, NULL, 'Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\r\nSoort: Touringcarrit\r\nKlant: MFE\r\nIngevoerde prijs: € 0,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(135, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 15:41:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'PIN', 275.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: ?\nIngevoerde prijs: € 275,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(136, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 21:04:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'PIN', 1.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: l\nIngevoerde prijs: € 1,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(137, NULL, 8, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 21:10:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 1.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEURSoort: TaxiritKlant: .', 0, 'Te controleren', 'Gefactureerd', '2026005', '2026-04-17 23:50:48', NULL),
(138, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 21:19:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 50000, NULL, 'Rekening', 10.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\r\nSoort: Taxirit\r\nKlant: Losse Instapper\r\nIngevoerde prijs: € 10,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(139, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 21:20:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, NULL, NULL, 'iDEAL', 5.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Losse Instapper\nIngevoerde prijs: € 5,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(140, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 22:42:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 5000, NULL, 'Contant', 5.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Fred Stravers\n\n✉️ BONNETJE GEWENST OP: freddystravers1975@live.nl\nIngevoerde prijs: € 5,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(141, NULL, NULL, NULL, 1, 11, 8, NULL, 1, 0, NULL, '2026-03-28 22:50:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 5000, NULL, 'Contant', 5.00, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Fred Stravers\n\n✉️ BONNETJE GEWENST OP: freddystravers1975@live.nl\nIngevoerde prijs: € 5,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(142, NULL, NULL, 'Taxi', 1, NULL, 7, NULL, 1, 0, NULL, '2026-03-30 10:15:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', NULL, 25.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-29 13:02:47'),
(143, NULL, NULL, 'Taxi', 1, NULL, 4, NULL, 1, 0, NULL, '2026-03-30 15:30:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-03-30 10:59:07'),
(144, 15, NULL, NULL, 8, NULL, 1, 8, 1, 1, 50, '2026-03-30 12:00:00', '2026-03-30 17:34:00', 'WC Open', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(145, NULL, 8, 'Taxi', 15, 16, 8, NULL, 1, 0, NULL, '2026-04-02 20:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, 494810, NULL, 'Op Rekening', 100.00, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Gefactureerd', '2026001', '2026-04-07 21:01:22', '2026-04-02 13:17:44'),
(146, NULL, NULL, NULL, 1, 20, 5, NULL, 1, 0, NULL, '2026-04-07 08:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 218116, NULL, 'Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\r\nSoort: Touringcarrit\r\nKlant: Werk en begeleiding\r\nIngevoerde prijs: € 217,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(147, NULL, 8, 'Taxi', 1, 20, 8, NULL, 1, 0, NULL, '2026-04-07 10:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, 5000, NULL, 'Op Rekening', 55.00, 55.00, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Gefactureerd', '2026004', '2026-04-07 21:21:43', '2026-04-07 21:20:04'),
(148, NULL, 1071, 'Touringcar', 1, NULL, 5, NULL, 1, 0, NULL, '2026-04-10 20:45:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Rekening', NULL, 450.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(149, NULL, 1071, 'Touringcar', 1, 33, 5, NULL, 1, 0, NULL, '2026-04-11 01:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 248670, NULL, 'Rekening', NULL, 450.00, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(150, 2, NULL, NULL, NULL, NULL, NULL, 4, 1, 1, 45, '2026-03-23 08:15:00', '2026-03-23 09:45:00', 'contactpersoon Tabois tel. 0614848323', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(151, NULL, 8, 'Rolstoelbus', NULL, NULL, NULL, NULL, 1, 0, 6, '2026-04-11 12:00:00', NULL, NULL, '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening Vast', NULL, 20.00, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(152, 18, 94, NULL, 1, 35, 3, 8, 1, 1, 50, '2026-04-12 11:45:00', '2026-04-12 18:35:00', 'Prijs is incl. dieseltoeslag', '', NULL, NULL, 0, NULL, NULL, NULL, 'Op Rekening', 750.00, NULL, '', 0, 'Te controleren', 'Te factureren', NULL, NULL, '2026-04-14 15:24:44'),
(153, NULL, NULL, NULL, 1, 36, 5, NULL, 1, 0, NULL, '2026-04-16 08:25:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 249930, NULL, 'Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Touringcarrit\nKlant: Werk en begeleiding\nIngevoerde prijs: € 217,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(154, NULL, NULL, NULL, 1, 36, 8, NULL, 1, 0, NULL, '2026-04-16 10:15:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 249950, NULL, 'Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Taxirit\nKlant: Munckhof\nIngevoerde prijs: € 200,00', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(155, NULL, NULL, NULL, 1, 36, 5, NULL, 1, 0, NULL, '2026-04-16 15:30:00', NULL, NULL, 'voltooid', NULL, NULL, 0, NULL, 249090, NULL, 'Rekening', NULL, NULL, '⚡ EXTRA RIT INGEVOERD DOOR CHAUFFEUR\nSoort: Touringcarrit\nKlant: Werk en begeleiding\nIngevoerde prijs: € 217,50', 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(156, 19, NULL, NULL, 17, NULL, NULL, 8, 1, 1, 50, '2026-04-17 12:00:00', '2026-04-17 16:16:00', '', 'gepland', NULL, NULL, 0, NULL, NULL, NULL, 'Contant', NULL, NULL, NULL, 0, 'Te controleren', 'Te factureren', NULL, NULL, NULL),
(157, 20, 8, NULL, 1, 38, NULL, 8, 1, 1, 50, '2026-04-17 13:00:00', '2026-04-17 15:16:00', '', '', NULL, NULL, 0, NULL, 550000, NULL, 'Op Rekening', 200.00, NULL, '✅ Rit volgens plan afgerond.', 0, 'Te controleren', 'Gefactureerd', '2026005', '2026-04-17 23:50:48', '2026-04-17 23:48:57');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `bestemming` varchar(255) DEFAULT NULL,
  `unieke_code` varchar(100) DEFAULT NULL,
  `is_gescand` tinyint(1) DEFAULT 0,
  `gescand_op` datetime DEFAULT NULL,
  `prijs` decimal(10,2) NOT NULL,
  `event_id` int(11) NOT NULL,
  `locatie_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'nieuw'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `tickets`
--

INSERT INTO `tickets` (`id`, `order_id`, `bestemming`, `unieke_code`, `is_gescand`, `gescand_op`, `prijs`, `event_id`, `locatie_id`, `status`) VALUES
(9, 14, 'Zutphen', 'C60F2C56', 1, '2026-02-08 19:50:13', 0.00, 0, 0, 'nieuw'),
(10, 15, 'Vorden', '919A2E18', 1, '2026-02-08 20:57:30', 0.00, 0, 0, 'nieuw'),
(12, 14, 'Vorden', '673EA1A5', 1, '2026-02-08 21:21:08', 0.00, 0, 0, 'nieuw'),
(13, 16, 'Zutphen', '50F40F62', 1, '2026-02-08 21:27:16', 0.00, 0, 0, 'nieuw'),
(69, 69, 'Vorden', '0A87DEC8', 0, NULL, 0.00, 0, 0, 'nieuw'),
(70, 70, 'Zutphen', '7980AC1B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(71, 71, 'Dreamfields 2026 - Zutphen, Station (13:00)', '0D931561', 0, NULL, 0.00, 0, 0, 'nieuw'),
(72, 72, 'Dreamfields 2026 - Brummen, Marktplein (12:30)', 'CA7B258F', 0, NULL, 0.00, 0, 0, 'nieuw'),
(73, 73, 'Vorden', 'E26976C8', 0, NULL, 0.00, 0, 0, 'nieuw'),
(74, 74, 'Feest Graafschap College 2026 - Zutphen (NS) (02:1', 'C759F5C3', 0, NULL, 0.00, 0, 0, 'nieuw'),
(75, 75, 'Feest Graafschap College 2026 - Zutphen (NS) (02:1', '3655FD19', 0, NULL, 0.00, 0, 0, 'nieuw'),
(76, 76, 'Feest Graafschap College 2026 - Zutphen (NS) (02:1', 'BC61042C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(77, 81, 'Feest Graafschap College 2026 - Zutphen (NS) (02:1', 'F140C281', 0, NULL, 0.00, 0, 0, 'nieuw'),
(78, 82, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:', 'B0FAB4DF', 0, NULL, 0.00, 0, 0, 'nieuw'),
(79, 83, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:', 'A97CAAEC', 0, NULL, 0.00, 0, 0, 'nieuw'),
(80, 84, 'Feest Graafschap College 2026 - Aalten (NS station', 'C9459E34', 1, '2026-02-19 17:19:02', 0.00, 0, 0, 'nieuw'),
(81, 85, 'Feest Graafschap College 2026 - Baak (Bushalte Dorp) (02:15)', '56F0AD8B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(82, 86, 'Feest Graafschap College 2026 - Beek (Tankstation Firezone) (02:15)', '64AF991B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(83, 87, 'Feest Graafschap College 2026 - Baak (Bushalte Dorp) (02:15)', 'E0B6F1E7', 0, NULL, 0.00, 0, 0, 'nieuw'),
(84, 88, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', 'A279B801', 0, NULL, 0.00, 0, 0, 'nieuw'),
(85, 89, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '743B80A1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(86, 90, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', 'C0C0CF48', 0, NULL, 0.00, 0, 0, 'nieuw'),
(87, 91, 'Feest Graafschap College 2026 - Neede (Busstation) (02:15)', 'D4AA0FD3', 0, NULL, 0.00, 0, 0, 'nieuw'),
(88, 92, 'Feest Graafschap College 2026 - Beek (Tankstation Firezone) (02:15)', '482D7427', 0, NULL, 0.00, 0, 0, 'nieuw'),
(89, 93, 'Feest Graafschap College 2026 - Drempt (02:15)', '08D443AE', 0, NULL, 0.00, 0, 0, 'nieuw'),
(90, 94, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '56EAFD33', 0, NULL, 0.00, 0, 0, 'nieuw'),
(91, 95, 'Feest Graafschap College 2026 - Beltrum (Cafe Dute) (02:15)', '9B51A85C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(92, 96, 'Feest Graafschap College 2026 - Baak (Bushalte Dorp) (02:15)', 'D92217B2', 0, NULL, 0.00, 0, 0, 'nieuw'),
(93, 97, 'Feest Graafschap College 2026 - Beltrum (Cafe Dute) (02:15)', 'FC190027', 0, NULL, 0.00, 0, 0, 'nieuw'),
(94, 98, 'Feest Graafschap College 2026 - Zutphen (NS) (02:15)', '6AC43F74', 0, NULL, 0.00, 0, 0, 'nieuw'),
(95, 99, 'Feest Graafschap College 2026 - Beek (Tankstation Firezone) (02:15)', '997437E9', 0, NULL, 0.00, 0, 0, 'nieuw'),
(96, 100, 'Feest Graafschap College 2026 - Baak (Bushalte Dorp) (02:15)', 'E0C17F93', 0, NULL, 0.00, 0, 0, 'nieuw'),
(97, 101, 'Feest Graafschap College 2026 - Baak (Bushalte Dorp) (02:15)', 'C9C5FFEC', 1, '2026-02-19 20:22:46', 0.00, 0, 0, 'nieuw'),
(105, 109, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', 'BCDA3487', 0, NULL, 0.00, 0, 0, 'nieuw'),
(106, 109, 'Feest Graafschap College 2026 - Etten Gld. (Cafe Tiemessen) (02:15)', '3E5CFAC6', 0, NULL, 0.00, 0, 0, 'nieuw'),
(109, 112, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'C7F9B91B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(110, 113, 'Dieka  - Zutphen, Station (21:00)', '7CCB295D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(111, 114, 'Dieka  - Dieren, Station (20:30)', 'CDF7DB82', 1, '2026-03-07 19:23:22', 0.00, 8, 0, 'betaald'),
(112, 115, 'Dieka  - Dieren, Station (20:30)', '44FD7AAB', 1, '2026-03-07 19:22:31', 0.00, 8, 0, 'betaald'),
(113, 115, 'Dieka  - Dieren, Station (20:30)', 'CC3AFE61', 1, '2026-03-07 19:22:40', 0.00, 8, 0, 'betaald'),
(114, 116, 'Dieka  - Dieren, Station (20:30)', 'DF2806CD', 1, '2026-03-07 19:23:43', 0.00, 8, 0, 'betaald'),
(115, 117, 'Dieka  - Dieren, Station (20:30)', '6121F3A0', 1, '2026-03-07 19:22:46', 0.00, 8, 0, 'betaald'),
(116, 118, 'Dieka  - Dieren, Station (20:30)', '112C8183', 0, NULL, 0.00, 0, 0, 'nieuw'),
(117, 119, 'Dieka  - Dieren, Station (20:30)', '46F54029', 1, '2026-03-07 19:22:50', 0.00, 8, 0, 'betaald'),
(118, 120, 'Dieka  - Dieren, Station (20:30)', 'AD13DE94', 1, '2026-03-07 19:22:57', 0.00, 8, 0, 'betaald'),
(119, 121, 'Dieka  - Dieren, Station (20:30)', '3245AADB', 1, '2026-03-07 19:23:33', 0.00, 8, 0, 'betaald'),
(120, 122, 'Dieka  - Dieren, Station (20:30)', '745F9684', 1, '2026-03-07 19:23:48', 0.00, 8, 0, 'betaald'),
(121, 123, 'Dieka  - Dieren, Station (20:30)', 'EA6BF4FD', 1, '2026-03-07 19:24:35', 0.00, 8, 0, 'betaald'),
(122, 124, 'Dieka  - Dieren, Station (20:30)', '96293C4B', 0, NULL, 0.00, 8, 0, 'betaald'),
(123, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '1E2EABE1', 0, NULL, 0.00, 4, 0, 'betaald'),
(124, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', 'CF65A0B3', 0, NULL, 0.00, 4, 0, 'betaald'),
(125, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '70331B8B', 0, NULL, 0.00, 4, 0, 'betaald'),
(126, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', 'F36210B5', 0, NULL, 0.00, 4, 0, 'betaald'),
(127, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', 'ACEDC684', 0, NULL, 0.00, 4, 0, 'betaald'),
(128, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '216006E6', 0, NULL, 0.00, 4, 0, 'betaald'),
(129, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '2A9F537F', 0, NULL, 0.00, 4, 0, 'betaald'),
(130, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '68738C05', 0, NULL, 0.00, 4, 0, 'betaald'),
(131, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', 'D46D1F6B', 0, NULL, 0.00, 4, 0, 'betaald'),
(132, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', 'F0B4F427', 0, NULL, 0.00, 4, 0, 'betaald'),
(133, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '757C3355', 0, NULL, 0.00, 4, 0, 'betaald'),
(134, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '3873801F', 0, NULL, 0.00, 4, 0, 'betaald'),
(135, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '5E413F84', 0, NULL, 0.00, 4, 0, 'betaald'),
(136, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '38E02E62', 0, NULL, 0.00, 4, 0, 'betaald'),
(137, 125, 'Snollebollekes Gelre Dome 2026 - Argos Emmerikseweg Zutphen (18:15)', '30F129EF', 0, NULL, 0.00, 4, 0, 'betaald'),
(138, 126, 'Dieka  - Dieren, Station (20:30)', '9D5A2701', 1, '2026-03-07 19:23:18', 0.00, 8, 0, 'betaald'),
(139, 127, 'Dieka  - Dieren, Station (20:30)', 'C2820A20', 1, '2026-03-07 19:23:10', 0.00, 8, 0, 'betaald'),
(140, 127, 'Dieka  - Dieren, Station (20:30)', 'A56BDC99', 1, '2026-03-07 19:23:14', 0.00, 8, 0, 'betaald'),
(141, 128, 'Dieka  - Dieren, Station (20:30)', '4A3FC739', 0, NULL, 0.00, 0, 0, 'nieuw'),
(142, 129, 'Dieka  - Dieren, Station (20:30)', 'E49D82CD', 1, '2026-03-07 19:25:14', 0.00, 8, 0, 'betaald'),
(143, 130, 'Dieka  - Dieren, Station (20:30)', 'BC511B89', 1, '2026-03-07 19:26:08', 0.00, 8, 0, 'betaald'),
(144, 131, 'Dieka  - Zutphen, Station (21:00)', 'CAFB1FB6', 0, NULL, 0.00, 0, 0, 'nieuw'),
(145, 132, 'Dieka  - Zutphen, Station (21:00)', '649B8ABD', 0, NULL, 0.00, 8, 0, 'nieuw'),
(147, 134, 'Dieka  - Dieren, Station (20:30)', 'B7825C72', 1, '2026-03-07 19:25:59', 0.00, 8, 0, 'nieuw'),
(148, 135, 'Dieka  - Zutphen, Station (21:00)', '7DCBFB80', 0, NULL, 0.00, 8, 0, 'nieuw'),
(149, 135, 'Dieka  - Zutphen, Station (21:00)', '15110E03', 0, NULL, 0.00, 8, 0, 'nieuw'),
(150, 136, 'Dieka  - Dieren, Station (20:30)', '3B811A1D', 1, '2026-03-07 19:23:39', 0.00, 8, 0, 'nieuw'),
(151, 137, 'Dieka  - Dieren, Station (20:30)', '6DAFF948', 1, '2026-03-07 19:25:18', 0.00, 8, 0, 'nieuw'),
(152, 138, 'Dieka  - Dieren, Station (20:30)', '897C9A46', 1, '2026-03-07 19:23:01', 0.00, 8, 0, 'nieuw'),
(154, 140, 'Dieka  - Dieren, Station (20:30)', 'BE295F2A', 1, '2026-03-07 19:24:23', 0.00, 8, 0, 'nieuw'),
(155, 141, 'Dieka  - Zutphen, Station (21:00)', 'B2691E12', 1, '2026-03-07 19:59:44', 0.00, 8, 0, 'nieuw'),
(156, 141, 'Dieka  - Zutphen, Station (21:00)', '445392C6', 1, '2026-03-07 19:59:48', 0.00, 8, 0, 'nieuw'),
(157, 141, 'Dieka  - Zutphen, Station (21:00)', 'A645640A', 1, '2026-03-07 19:59:51', 0.00, 8, 0, 'nieuw'),
(158, 142, 'Dieka  - Dieren, Station (20:30)', '085FE5A7', 1, '2026-03-07 19:24:16', 0.00, 8, 0, 'nieuw'),
(159, 143, 'Dieka  - Dieren, Station (20:30)', '210035B3', 1, '2026-03-07 19:24:10', 0.00, 8, 0, 'nieuw'),
(160, 144, 'Dieka  - Dieren, Station (20:30)', '6CDBCFF2', 1, '2026-03-07 19:24:41', 0.00, 8, 0, 'nieuw'),
(161, 145, 'Dieka  - Dieren, Station (20:30)', '31249465', 1, '2026-03-07 19:25:42', 0.00, 8, 0, 'nieuw'),
(162, 146, 'Dieka  - Dieren, Station (20:30)', 'E09B5968', 1, '2026-03-07 19:25:46', 0.00, 8, 0, 'nieuw'),
(163, 147, 'Dieka  - Dieren, Station (20:30)', '800FD715', 1, '2026-03-07 19:25:25', 0.00, 8, 0, 'nieuw'),
(164, 148, 'Dieka  - Dieren, Station (20:30)', '8BC4ED42', 1, '2026-03-07 19:24:46', 0.00, 8, 0, 'nieuw'),
(165, 149, 'Dieka  - Zutphen, Station (21:00)', 'A97BAEDD', 1, '2026-03-07 19:55:34', 0.00, 8, 0, 'nieuw'),
(166, 150, 'Dieka  - Zutphen, Station (21:00)', '130EC7B9', 1, '2026-03-07 19:56:46', 0.00, 8, 0, 'nieuw'),
(169, 153, 'Dieka  - Zutphen, Station (21:00)', '63FD3627', 1, '2026-03-07 19:55:48', 0.00, 8, 0, 'nieuw'),
(170, 154, 'Dieka  - Zutphen, Station (21:00)', 'C64F9CEE', 1, '2026-03-07 19:57:22', 0.00, 8, 0, 'nieuw'),
(171, 155, 'Dieka  - Zutphen, Station (21:00)', '9761E7FB', 1, '2026-03-07 19:57:18', 0.00, 8, 0, 'nieuw'),
(172, 156, 'Dieka  - Zutphen, Station (21:00)', 'A1639A49', 0, NULL, 0.00, 8, 0, 'nieuw'),
(173, 157, 'Dieka  - Zutphen, Station (21:00)', '0CC7FCF3', 1, '2026-03-07 19:55:45', 0.00, 8, 0, 'nieuw'),
(174, 158, 'Dieka  - Zutphen, Station (21:00)', '00989475', 1, '2026-03-07 19:56:04', 0.00, 8, 0, 'nieuw'),
(175, 159, 'Dieka  - Zutphen, Station (21:00)', '1E171E87', 1, '2026-03-07 19:55:53', 0.00, 8, 0, 'nieuw'),
(177, 161, 'Dieka  - Zutphen, Station (21:00)', '148F7FEF', 1, '2026-03-07 19:56:10', 0.00, 8, 0, 'nieuw'),
(179, 163, 'Dieka  - Zutphen, Station (21:00)', '08583538', 1, '2026-03-07 19:56:50', 0.00, 8, 0, 'nieuw'),
(180, 164, 'Dieka  - Zutphen, Station (21:00)', '105911D7', 1, '2026-03-07 19:55:39', 0.00, 8, 0, 'nieuw'),
(181, 165, 'Dieka  - Zutphen, Station (21:00)', '3C700E7C', 0, NULL, 0.00, 8, 0, 'nieuw'),
(182, 166, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', '258B021A', 1, '2026-03-07 20:26:10', 0.00, 8, 0, 'nieuw'),
(183, 167, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', '3902A7AA', 0, NULL, 0.00, 8, 0, 'nieuw'),
(185, 169, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', 'E7AC8906', 1, '2026-03-07 20:25:52', 0.00, 8, 0, 'nieuw'),
(186, 170, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', 'D44407E7', 1, '2026-03-07 20:23:16', 0.00, 8, 0, 'nieuw'),
(187, 171, 'Dieka  - Zutphen, Station (21:00)', '9286D47E', 1, '2026-03-07 19:56:16', 0.00, 8, 0, 'nieuw'),
(188, 172, 'Dieka  - Zutphen, Station (21:00)', '0575EF81', 1, '2026-03-07 19:56:20', 0.00, 8, 0, 'nieuw'),
(189, 173, 'Dieka  - Dieren, Station (20:30)', '447A17B7', 0, NULL, 0.00, 8, 0, 'nieuw'),
(191, 175, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', '083AA0B5', 1, '2026-03-07 20:23:22', 0.00, 8, 0, 'nieuw'),
(192, 176, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', 'C4ED3B95', 0, NULL, 0.00, 8, 0, 'nieuw'),
(194, 178, 'Dieka  - Zutphen, Station (21:00)', 'A0167CA2', 1, '2026-03-07 19:56:29', 0.00, 8, 0, 'nieuw'),
(195, 178, 'Dieka  - Zutphen, Station (21:00)', 'B14BA313', 1, '2026-03-07 19:56:36', 0.00, 8, 0, 'nieuw'),
(196, 179, 'Dieka  - Zutphen, Station (21:00)', '611D917C', 0, NULL, 0.00, 8, 0, 'nieuw'),
(197, 180, 'Dieka  - Zutphen, Station (21:00)', 'A4ED3434', 0, NULL, 0.00, 8, 0, 'nieuw'),
(198, 181, 'Dieka  - Zutphen, Station (21:00)', '6A3D90F3', 0, NULL, 0.00, 8, 0, 'nieuw'),
(199, 182, 'Dieka  - Dieren, Station (20:30)', '7B1D00FF', 1, '2026-03-07 19:24:04', 0.00, 8, 0, 'nieuw'),
(200, 183, 'Dieka  - Dieren, Station (20:30)', '0D4CF7EC', 0, NULL, 0.00, 8, 0, 'nieuw'),
(201, 183, 'Dieka  - Dieren, Station (20:30)', '4D126268', 0, NULL, 0.00, 8, 0, 'nieuw'),
(202, 184, 'Dieka  - Dieren, Station (20:30)', 'D81BE0AC', 0, NULL, 0.00, 8, 0, 'nieuw'),
(203, 184, 'Dieka  - Dieren, Station (20:30)', 'F9F17B1D', 0, NULL, 0.00, 8, 0, 'nieuw'),
(204, 185, 'Dieka  - Dieren, Station (20:30)', '585CB0E2', 1, '2026-03-07 19:24:30', 0.00, 8, 0, 'nieuw'),
(205, 186, 'Dieka  - Dieren, Station (20:30)', '613CF35C', 1, '2026-03-07 19:23:57', 0.00, 8, 0, 'nieuw'),
(206, 187, 'Dieka  - Dieren, Station (20:30)', 'E76197C0', 0, NULL, 0.00, 8, 0, 'nieuw'),
(207, 188, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', '1D7D01C5', 0, NULL, 0.00, 8, 0, 'nieuw'),
(208, 188, 'Dieka  - Vorden, Halte Horsterkamp (21:30)', '841F2FCC', 0, NULL, 0.00, 8, 0, 'nieuw'),
(209, 189, 'Feest Graafschap College 2026 - Gendringen (Kerkplein) (02:15)', 'D32E7B84', 0, NULL, 0.00, 0, 0, 'nieuw'),
(210, 190, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', 'D829DFE8', 0, NULL, 0.00, 0, 0, 'nieuw'),
(211, 191, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', 'BE370DC5', 0, NULL, 0.00, 0, 0, 'nieuw'),
(212, 192, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'ED23C31D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(213, 193, 'Feest Graafschap College 2026 - Gendringen (Kerkplein) (02:15)', '56F057D1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(214, 194, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '406FED3F', 1, '2026-03-21 20:02:29', 0.00, 9, 0, 'nieuw'),
(215, 194, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '2EFC1EEB', 1, '2026-03-21 20:02:22', 0.00, 9, 0, 'nieuw'),
(217, 196, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'CE8FE7DB', 0, NULL, 0.00, 9, 0, 'nieuw'),
(218, 197, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'D928C2B1', 1, '2026-03-21 20:04:21', 0.00, 9, 0, 'nieuw'),
(219, 198, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', '5BA69612', 1, '2026-03-21 20:22:16', 0.00, 9, 0, 'nieuw'),
(220, 198, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', 'D7B92573', 1, '2026-03-21 20:22:22', 0.00, 9, 0, 'nieuw'),
(221, 199, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '4CBFE113', 1, '2026-03-21 20:05:02', 0.00, 9, 0, 'nieuw'),
(222, 200, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'D6AD53B7', 1, '2026-03-21 20:04:38', 0.00, 9, 0, 'nieuw'),
(223, 201, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '01641305', 0, NULL, 0.00, 9, 0, 'nieuw'),
(224, 202, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'ACF870A5', 1, '2026-03-21 20:03:16', 0.00, 9, 0, 'nieuw'),
(225, 203, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '02488876', 0, NULL, 0.00, 9, 0, 'nieuw'),
(226, 204, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', 'DDADB168', 1, '2026-03-21 20:28:56', 0.00, 9, 0, 'nieuw'),
(227, 205, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'CD04C814', 1, '2026-03-21 20:04:00', 0.00, 9, 0, 'nieuw'),
(228, 206, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '0BD07AEB', 1, '2026-03-21 20:04:14', 0.00, 9, 0, 'nieuw'),
(229, 207, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '88611646', 1, '2026-03-21 20:03:52', 0.00, 9, 0, 'nieuw'),
(230, 208, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '6B429054', 1, '2026-03-21 20:02:37', 0.00, 9, 0, 'nieuw'),
(231, 208, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '5D0C407F', 1, '2026-03-21 20:02:42', 0.00, 9, 0, 'nieuw'),
(232, 209, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '5C8D3135', 1, '2026-03-21 20:03:11', 0.00, 9, 0, 'nieuw'),
(233, 210, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', 'D6B40AEB', 0, NULL, 0.00, 0, 0, 'nieuw'),
(234, 211, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', '1A3E4C03', 1, '2026-03-21 20:22:30', 0.00, 9, 0, 'nieuw'),
(235, 212, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', 'E71EEBC3', 0, NULL, 0.00, 9, 0, 'nieuw'),
(236, 213, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', 'ADB04FD4', 1, '2026-03-21 20:22:48', 0.00, 9, 0, 'nieuw'),
(237, 214, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', 'EB934509', 1, '2026-03-21 20:29:50', 0.00, 9, 0, 'nieuw'),
(238, 214, 'Kole Kermis Broekland  - Terwolde, Kadijk 8 (21:15)', '4540421A', 1, '2026-03-21 20:29:55', 0.00, 9, 0, 'nieuw'),
(239, 215, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '74D82E67', 0, NULL, 0.00, 0, 0, 'nieuw'),
(240, 216, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '05996FA0', 0, NULL, 0.00, 9, 0, 'nieuw'),
(241, 217, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '37199653', 1, '2026-03-21 20:04:57', 0.00, 9, 0, 'nieuw'),
(242, 218, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'B3C58CC3', 0, NULL, 0.00, 9, 0, 'nieuw'),
(243, 219, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'EB07E441', 0, NULL, 0.00, 9, 0, 'nieuw'),
(244, 220, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '38421E1D', 1, '2026-03-21 20:03:28', 0.00, 9, 0, 'nieuw'),
(245, 221, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'EB8A8CA9', 0, NULL, 0.00, 0, 0, 'nieuw'),
(246, 222, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '3FA95507', 0, NULL, 0.00, 0, 0, 'nieuw'),
(247, 223, 'Feest Graafschap College 2026 - Beltrum (Cafe Dute) (02:15)', '4F1A84D3', 0, NULL, 0.00, 0, 0, 'nieuw'),
(248, 224, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', 'FF46D467', 1, '2026-03-21 20:04:43', 0.00, 9, 0, 'nieuw'),
(249, 225, 'Kole Kermis Broekland  - Wilp, Ardeweg 1 Watertap (21:00)', '28D0437E', 1, '2026-03-21 20:04:52', 0.00, 9, 0, 'nieuw'),
(250, 226, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '11318F8B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(253, 229, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '28CACBB6', 0, NULL, 0.00, 0, 0, 'nieuw'),
(254, 230, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '22B8B59C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(255, 231, 'Feest Graafschap College 2026 - Drempt (02:15)', 'F21CE81E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(256, 232, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '7EC80347', 0, NULL, 0.00, 0, 0, 'nieuw'),
(257, 233, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'D93A6B21', 0, NULL, 0.00, 0, 0, 'nieuw'),
(259, 235, 'Feest Graafschap College 2026 - Ulft (Bushalte DRU) (02:15)', '78A2D963', 0, NULL, 0.00, 0, 0, 'nieuw'),
(260, 236, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'A1A95305', 0, NULL, 0.00, 0, 0, 'nieuw'),
(261, 237, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '096F3B47', 0, NULL, 0.00, 0, 0, 'nieuw'),
(262, 238, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'BF9BB93A', 0, NULL, 0.00, 0, 0, 'nieuw'),
(263, 239, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'F25B28E2', 0, NULL, 0.00, 0, 0, 'nieuw'),
(264, 240, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '1B0922F3', 0, NULL, 0.00, 0, 0, 'nieuw'),
(265, 241, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'A30F2851', 0, NULL, 0.00, 0, 0, 'nieuw'),
(266, 242, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '6DBEF130', 0, NULL, 0.00, 0, 0, 'nieuw'),
(267, 243, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'F3682DC1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(268, 244, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'EC03A41E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(269, 245, 'Zwarte Cross Zaterdag - Warnsveld, 13:15 uur Kerkhofweg (13:15)', 'D1F71BF1', 0, NULL, 0.00, 12, 0, 'nieuw'),
(270, 245, 'Zwarte Cross Zaterdag - Warnsveld, 13:15 uur Kerkhofweg (13:15)', '1AD3256E', 0, NULL, 0.00, 12, 0, 'nieuw'),
(271, 246, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', '0B573A93', 0, NULL, 0.00, 0, 0, 'nieuw'),
(272, 247, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', '76942FF2', 0, NULL, 0.00, 0, 0, 'nieuw'),
(273, 248, 'Doetinchem (NS CS station)', '80C8249DF4', 0, NULL, 10.00, 3, 19, 'betaald'),
(274, 249, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', '28E00885', 0, NULL, 0.00, 0, 0, 'nieuw'),
(275, 250, 'Feest Graafschap College 2026 - Gaanderen (Cafe Harttjes) (02:15)', '1FFF435D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(276, 251, 'Feest Graafschap College 2026 - Gaanderen (Cafe Harttjes) (02:15)', '9427FB61', 0, NULL, 0.00, 0, 0, 'nieuw'),
(277, 252, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', '92DEE169', 0, NULL, 0.00, 0, 0, 'nieuw'),
(278, 253, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', 'F5C9A019', 0, NULL, 0.00, 0, 0, 'nieuw'),
(279, 254, 'Feest Graafschap College 2026 - Vorden (Bushalte De Horsterkamp Zuid) (02:15)', '773E0192', 0, NULL, 0.00, 0, 0, 'nieuw'),
(280, 255, 'Feest Graafschap College 2026 - Vorden (Bushalte De Horsterkamp Zuid) (02:15)', 'F2023921', 0, NULL, 0.00, 0, 0, 'nieuw'),
(281, 256, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', 'E89E4A18', 0, NULL, 0.00, 0, 0, 'nieuw'),
(282, 257, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'DB9B8849', 0, NULL, 0.00, 0, 0, 'nieuw'),
(283, 258, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '65FDD944', 0, NULL, 0.00, 0, 0, 'nieuw'),
(284, 259, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '03963D13', 0, NULL, 0.00, 0, 0, 'nieuw'),
(285, 260, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '05CB071F', 0, NULL, 0.00, 0, 0, 'nieuw'),
(286, 261, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '939E7356', 0, NULL, 0.00, 0, 0, 'nieuw'),
(287, 262, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '1CE555A1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(288, 263, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '692745A1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(289, 264, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '4D82C9BB', 0, NULL, 0.00, 0, 0, 'nieuw'),
(290, 265, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', 'D23923B8', 0, NULL, 0.00, 0, 0, 'nieuw'),
(291, 266, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', 'EB9F7094', 0, NULL, 0.00, 0, 0, 'nieuw'),
(293, 268, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '10390FB2', 0, NULL, 0.00, 0, 0, 'nieuw'),
(294, 269, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', 'F96992D2', 0, NULL, 0.00, 0, 0, 'nieuw'),
(295, 270, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', '7C9407B6', 0, NULL, 0.00, 0, 0, 'nieuw'),
(296, 271, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', '8B856579', 0, NULL, 0.00, 0, 0, 'nieuw'),
(297, 272, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', '98CD38DF', 0, NULL, 0.00, 0, 0, 'nieuw'),
(299, 274, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', 'E4BA3F1E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(300, 275, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', '3127912E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(301, 276, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '8508A7DB', 0, NULL, 0.00, 0, 0, 'nieuw'),
(302, 277, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', 'BADF3D96', 0, NULL, 0.00, 0, 0, 'nieuw'),
(304, 279, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '45AA214D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(305, 280, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '1BD715F3', 0, NULL, 0.00, 0, 0, 'nieuw'),
(306, 281, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', '2E83A647', 0, NULL, 0.00, 0, 0, 'nieuw'),
(307, 282, 'Feest Graafschap College 2026 - \'s-Heerenberg (Autobedrijf Arendsen) (02:15)', '5FDF9E1C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(309, 284, 'Feest Graafschap College 2026 - Neede (Busstation) (02:15)', '491F234D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(310, 285, 'Feest Graafschap College 2026 - Neede (Busstation) (02:15)', '47AD0C9B', 0, NULL, 0.00, 0, 0, 'nieuw'),
(312, 287, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', '57B6584E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(314, 289, 'Feest Graafschap College 2026 - Eibergen (Bushalte Viersprong (bij Het Assink)) (02:15)', '89914807', 0, NULL, 0.00, 0, 0, 'nieuw'),
(315, 290, 'Feest Graafschap College 2026 - Beltrum (Cafe Dute) (02:15)', 'D40F48FF', 0, NULL, 0.00, 0, 0, 'nieuw'),
(316, 291, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', 'CE05C908', 0, NULL, 0.00, 0, 0, 'nieuw'),
(317, 292, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', '73463245', 0, NULL, 0.00, 0, 0, 'nieuw'),
(318, 293, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', 'C888E56A', 0, NULL, 0.00, 0, 0, 'nieuw'),
(319, 294, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', '1F18E9E1', 0, NULL, 0.00, 0, 0, 'nieuw'),
(320, 295, 'Feest Graafschap College 2026 - Gendringen (Kerkplein) (02:15)', 'B39B73FA', 0, NULL, 0.00, 0, 0, 'nieuw'),
(321, 296, 'Feest Graafschap College 2026 - Winterswijk (NS station) (02:15)', 'FA2FAE8A', 0, NULL, 0.00, 0, 0, 'nieuw'),
(322, 297, 'Feest Graafschap College 2026 - Ulft (Bushalte DRU) (02:15)', '6611D21C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(323, 298, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', 'FEF10F5C', 0, NULL, 0.00, 0, 0, 'nieuw'),
(324, 299, 'Feest Graafschap College 2026 - Haaksbergen (Busstation) (02:15)', '009A818D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(325, 300, 'Feest Graafschap College 2026 - Zevenaar (NS) (02:15)', 'BBB10A67', 0, NULL, 0.00, 0, 0, 'nieuw'),
(326, 301, 'Feest Graafschap College 2026 - Groenlo (Busstation) (02:15)', '63848E84', 0, NULL, 0.00, 0, 0, 'nieuw'),
(327, 302, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '80816DF0', 0, NULL, 0.00, 0, 0, 'nieuw'),
(328, 303, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '002C333D', 0, NULL, 0.00, 0, 0, 'nieuw'),
(329, 304, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', '65CB1013', 0, NULL, 0.00, 0, 0, 'nieuw'),
(330, 305, 'Feest Graafschap College 2026 - Drempt (02:15)', 'A7AF8525', 0, NULL, 0.00, 0, 0, 'nieuw'),
(332, 307, 'Feest Graafschap College 2026 - Doetinchem (NS CS station) (02:15)', '9C7E40E6', 0, NULL, 0.00, 0, 0, 'nieuw'),
(333, 308, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', 'C6E2415E', 0, NULL, 0.00, 0, 0, 'nieuw'),
(335, 310, 'Feest Graafschap College 2026 - Aalten (NS station) (02:15)', 'A290BFE8', 0, NULL, 0.00, 0, 0, 'nieuw'),
(336, 311, 'Zwarte Cross Zaterdag - Warnsveld, 13:15 uur Kerkhofweg (13:15)', '3FC4D58A', 0, NULL, 0.00, 12, 0, 'nieuw');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vaste_ritten`
--

CREATE TABLE `vaste_ritten` (
  `id` int(11) NOT NULL,
  `naam` varchar(255) NOT NULL,
  `type_planning` varchar(20) NOT NULL DEFAULT 'periode',
  `klant_id` int(11) DEFAULT NULL,
  `prijs` decimal(10,2) NOT NULL DEFAULT 0.00,
  `voertuig_id` int(11) DEFAULT NULL,
  `chauffeur_id` int(11) DEFAULT NULL,
  `type_vervoer` varchar(50) DEFAULT 'Dagbesteding',
  `startdatum` date NOT NULL,
  `einddatum` date NOT NULL,
  `vertrektijd` time NOT NULL,
  `aankomsttijd` time NOT NULL,
  `retour_vertrektijd` time DEFAULT NULL,
  `ophaaladres` varchar(255) NOT NULL,
  `bestemming` varchar(255) NOT NULL,
  `rijdt_ma` tinyint(1) DEFAULT 0,
  `rijdt_di` tinyint(1) DEFAULT 0,
  `rijdt_wo` tinyint(1) DEFAULT 0,
  `rijdt_do` tinyint(1) DEFAULT 0,
  `rijdt_vr` tinyint(1) DEFAULT 0,
  `rijdt_za` tinyint(1) DEFAULT 0,
  `rijdt_zo` tinyint(1) DEFAULT 0,
  `uitzondering_datums` text DEFAULT NULL,
  `specifieke_datums` text DEFAULT NULL,
  `notities` text DEFAULT NULL,
  `actief` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vaste_ritten`
--

INSERT INTO `vaste_ritten` (`id`, `naam`, `type_planning`, `klant_id`, `prijs`, `voertuig_id`, `chauffeur_id`, `type_vervoer`, `startdatum`, `einddatum`, `vertrektijd`, `aankomsttijd`, `retour_vertrektijd`, `ophaaladres`, `bestemming`, `rijdt_ma`, `rijdt_di`, `rijdt_wo`, `rijdt_do`, `rijdt_vr`, `rijdt_za`, `rijdt_zo`, `uitzondering_datums`, `specifieke_datums`, `notities`, `actief`) VALUES
(1, 'Kiezelbrink', 'periode', NULL, 0.00, 5, NULL, 'Dagbesteding', '2026-03-16', '2026-06-30', '16:30:00', '17:00:00', NULL, 'Reubezorg', 'Polbeek', 1, 1, 0, 1, 1, 0, 0, '', NULL, '', 1),
(2, 'Radeland 2', 'periode', NULL, 0.00, 6, 3, 'Dagbesteding', '2026-03-23', '2026-04-05', '07:45:00', '09:45:00', NULL, 'Eerbeek', 'Radeland 3 Brummen', 1, 1, 1, 1, 0, 0, 0, '', NULL, '', 1),
(3, 'Broekland', 'periode', NULL, 0.00, 8, NULL, 'Dagbesteding', '2026-03-23', '2026-04-05', '09:00:00', '09:45:00', NULL, 'De Bosrand 3 7207 ME Zutphen', 'De Horsthoeve, Horstweg 15, 8107 AA Broekland', 1, 0, 0, 0, 1, 0, 0, '', NULL, '', 1),
(4, 'Martinus Bussloo', 'periode', NULL, 0.00, NULL, NULL, 'Dagbesteding', '2026-03-23', '2026-04-05', '11:15:00', '15:00:00', NULL, 'Deventerweg 18 Voorst', 'Kerkstraat 18 Wilp', 0, 0, 1, 0, 0, 0, 0, '', NULL, '', 1),
(5, 'AZC Zwemmen', 'periode', NULL, 0.00, NULL, NULL, 'Dagbesteding', '2026-03-23', '2026-04-05', '08:30:00', '10:45:00', NULL, 'AZC Voorsterallee', 'Zwembad Zutphen', 0, 0, 0, 1, 0, 0, 0, '', NULL, '', 1),
(6, 'Kiezebrink', 'periode', 1412, 49.50, 5, NULL, 'Dagbesteding', '2026-06-01', '2026-06-05', '16:30:00', '16:50:00', NULL, 'Van Dorenborchstraat 117, Zutphen, Nederland', 'ReubeZorg, Ampsenseweg, Lochem, Nederland', 1, 1, 0, 1, 1, 0, 0, '', NULL, '', 0);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `voertuigen`
--

CREATE TABLE `voertuigen` (
  `id` int(11) NOT NULL,
  `kenteken` varchar(20) NOT NULL,
  `naam` varchar(50) NOT NULL COMMENT 'Bijv. Bus 12',
  `zitplaatsen` int(11) NOT NULL DEFAULT 0,
  `status` enum('beschikbaar','onderhoud','stuk') DEFAULT 'beschikbaar',
  `euroklasse` varchar(50) DEFAULT NULL,
  `apk_datum` date DEFAULT NULL,
  `tacho_datum` date DEFAULT NULL,
  `brandblusser_datum` date DEFAULT NULL,
  `km_kostprijs` decimal(10,2) DEFAULT NULL,
  `onderhoud_notities` text DEFAULT NULL,
  `archief` tinyint(1) NOT NULL DEFAULT 0,
  `type` varchar(100) DEFAULT NULL,
  `voertuig_nummer` varchar(50) DEFAULT NULL,
  `chassisnummer` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `voertuigen`
--

INSERT INTO `voertuigen` (`id`, `kenteken`, `naam`, `zitplaatsen`, `status`, `euroklasse`, `apk_datum`, `tacho_datum`, `brandblusser_datum`, `km_kostprijs`, `onderhoud_notities`, `archief`, `type`, `voertuig_nummer`, `chassisnummer`) VALUES
(1, 'BZ-TX-98', 'VDL', 53, 'beschikbaar', '5', '2027-05-11', '2026-11-27', NULL, 1.00, NULL, 0, 'Futura FHD2 129-410', '55', NULL),
(2, '56-BTB-3', 'Van Hool', 60, 'beschikbaar', '6', '2027-04-03', '2026-11-20', NULL, 1.20, NULL, 0, 'EX17', '60', NULL),
(3, 'BB-616-B', 'Van Hool', 62, 'beschikbaar', '6', '2027-03-04', '2027-03-11', NULL, NULL, NULL, 0, 'EX17', '62', 'YE2E17SS368D72060'),
(4, '00-BJD-1', 'MAN', 50, 'beschikbaar', '6', '2027-03-09', '2026-11-20', NULL, NULL, NULL, 0, 'LION\'S COACH', '50', 'WMAR07ZZ3HTO25277'),
(5, '34-BPH-2', 'Mercedes Benz', 19, 'beschikbaar', '6', '2027-01-11', '2026-11-27', NULL, NULL, NULL, 0, 'Sprinter MID EURO', '19', 'WDB9066571P289207'),
(6, '04-BXZ-6', 'Mercedes Benz', 19, 'beschikbaar', NULL, '2026-09-30', '2026-07-03', NULL, NULL, NULL, 0, 'AROBUS SPRINTER', '23', NULL),
(7, '55-RFZ-1', 'Mercedes Benz', 8, 'beschikbaar', '5', '2026-06-05', NULL, NULL, NULL, NULL, 0, 'Sprinter Rolstoelbus', '18', NULL),
(8, '86-SJV-5', 'Mercedes Benz', 8, 'beschikbaar', '5', '2027-02-13', NULL, NULL, NULL, NULL, 0, 'Sprinter Rolstoelbus', '17', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vr_mutaties`
--

CREATE TABLE `vr_mutaties` (
  `id` int(11) NOT NULL,
  `passagier_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `rit_type` enum('heen','terug') NOT NULL,
  `status` enum('afwezig','extra_aanwezig') NOT NULL DEFAULT 'afwezig'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vr_passagiers`
--

CREATE TABLE `vr_passagiers` (
  `id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `naam` varchar(100) NOT NULL,
  `opstap_plek` varchar(100) DEFAULT NULL,
  `ma_h` tinyint(1) DEFAULT 1,
  `ma_t` tinyint(1) DEFAULT 1,
  `di_h` tinyint(1) DEFAULT 1,
  `di_t` tinyint(1) DEFAULT 1,
  `wo_h` tinyint(1) DEFAULT 1,
  `wo_t` tinyint(1) DEFAULT 1,
  `do_h` tinyint(1) DEFAULT 1,
  `do_t` tinyint(1) DEFAULT 1,
  `vr_h` tinyint(1) DEFAULT 1,
  `vr_t` tinyint(1) DEFAULT 1,
  `sorteer_volgorde` int(11) DEFAULT 0,
  `actief` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vr_passagiers`
--

INSERT INTO `vr_passagiers` (`id`, `route_id`, `naam`, `opstap_plek`, `ma_h`, `ma_t`, `di_h`, `di_t`, `wo_h`, `wo_t`, `do_h`, `do_t`, `vr_h`, `vr_t`, `sorteer_volgorde`, `actief`) VALUES
(1, 1, 'Rick Bouwhuis', 'Pluryn', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(2, 1, 'Rick Bouwhuis', 'Pluryn', 1, 0, 1, 0, 1, 1, 1, 1, 1, 1, 0, 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `vr_routes`
--

CREATE TABLE `vr_routes` (
  `id` int(11) NOT NULL,
  `klant_id` int(11) DEFAULT NULL,
  `naam` varchar(100) NOT NULL,
  `actief` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Gegevens worden geëxporteerd voor tabel `vr_routes`
--

INSERT INTO `vr_routes` (`id`, `klant_id`, `naam`, `actief`) VALUES
(1, NULL, 'Route Radeland Lochem', 1),
(2, NULL, 'Route Eerbeek', 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `week_notities`
--

CREATE TABLE `week_notities` (
  `id` int(11) NOT NULL,
  `jaar` int(4) NOT NULL,
  `week` int(2) NOT NULL,
  `notitie` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `afwezigheid`
--
ALTER TABLE `afwezigheid`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chauffeur_id` (`chauffeur_id`);

--
-- Indexen voor tabel `calculaties`
--
ALTER TABLE `calculaties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klant_id` (`klant_id`);

--
-- Indexen voor tabel `calculatie_instellingen`
--
ALTER TABLE `calculatie_instellingen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `calculatie_regels`
--
ALTER TABLE `calculatie_regels`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `calculatie_rittypes`
--
ALTER TABLE `calculatie_rittypes`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `calculatie_voertuigen`
--
ALTER TABLE `calculatie_voertuigen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `chauffeurs`
--
ALTER TABLE `chauffeurs`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `diensten`
--
ALTER TABLE `diensten`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `dienst_pauzes`
--
ALTER TABLE `dienst_pauzes`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `klanten`
--
ALTER TABLE `klanten`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `klant_afdelingen`
--
ALTER TABLE `klant_afdelingen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klant_id` (`klant_id`);

--
-- Indexen voor tabel `klant_contactpersonen`
--
ALTER TABLE `klant_contactpersonen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klant_id` (`klant_id`);

--
-- Indexen voor tabel `loon_uren`
--
ALTER TABLE `loon_uren`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chauffeur_datum` (`chauffeur_id`,`datum`);

--
-- Indexen voor tabel `mail_sjablonen`
--
ALTER TABLE `mail_sjablonen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `party_events`
--
ALTER TABLE `party_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `party_haltes_bibliotheek`
--
ALTER TABLE `party_haltes_bibliotheek`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `party_locaties`
--
ALTER TABLE `party_locaties`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `party_opstap_locaties`
--
ALTER TABLE `party_opstap_locaties`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `ritgegevens`
--
ALTER TABLE `ritgegevens`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `ritregels`
--
ALTER TABLE `ritregels`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `ritten`
--
ALTER TABLE `ritten`
  ADD PRIMARY KEY (`id`),
  ADD KEY `calculatie_id` (`calculatie_id`),
  ADD KEY `chauffeur_id` (`chauffeur_id`),
  ADD KEY `voertuig_id` (`voertuig_id`);

--
-- Indexen voor tabel `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unieke_code` (`unieke_code`);

--
-- Indexen voor tabel `vaste_ritten`
--
ALTER TABLE `vaste_ritten`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `voertuigen`
--
ALTER TABLE `voertuigen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `vr_mutaties`
--
ALTER TABLE `vr_mutaties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unieke_mutatie` (`passagier_id`,`datum`,`rit_type`);

--
-- Indexen voor tabel `vr_passagiers`
--
ALTER TABLE `vr_passagiers`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `vr_routes`
--
ALTER TABLE `vr_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `week_notities`
--
ALTER TABLE `week_notities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unieke_week` (`jaar`,`week`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `afwezigheid`
--
ALTER TABLE `afwezigheid`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT voor een tabel `calculaties`
--
ALTER TABLE `calculaties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT voor een tabel `calculatie_instellingen`
--
ALTER TABLE `calculatie_instellingen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `calculatie_regels`
--
ALTER TABLE `calculatie_regels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=953;

--
-- AUTO_INCREMENT voor een tabel `calculatie_rittypes`
--
ALTER TABLE `calculatie_rittypes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `calculatie_voertuigen`
--
ALTER TABLE `calculatie_voertuigen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT voor een tabel `chauffeurs`
--
ALTER TABLE `chauffeurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT voor een tabel `diensten`
--
ALTER TABLE `diensten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT voor een tabel `dienst_pauzes`
--
ALTER TABLE `dienst_pauzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT voor een tabel `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `klanten`
--
ALTER TABLE `klanten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1413;

--
-- AUTO_INCREMENT voor een tabel `klant_afdelingen`
--
ALTER TABLE `klant_afdelingen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT voor een tabel `klant_contactpersonen`
--
ALTER TABLE `klant_contactpersonen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294;

--
-- AUTO_INCREMENT voor een tabel `loon_uren`
--
ALTER TABLE `loon_uren`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1004;

--
-- AUTO_INCREMENT voor een tabel `mail_sjablonen`
--
ALTER TABLE `mail_sjablonen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT voor een tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=312;

--
-- AUTO_INCREMENT voor een tabel `party_events`
--
ALTER TABLE `party_events`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT voor een tabel `party_haltes_bibliotheek`
--
ALTER TABLE `party_haltes_bibliotheek`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT voor een tabel `party_locaties`
--
ALTER TABLE `party_locaties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `party_opstap_locaties`
--
ALTER TABLE `party_opstap_locaties`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT voor een tabel `ritgegevens`
--
ALTER TABLE `ritgegevens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT voor een tabel `ritregels`
--
ALTER TABLE `ritregels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT voor een tabel `ritten`
--
ALTER TABLE `ritten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;

--
-- AUTO_INCREMENT voor een tabel `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=337;

--
-- AUTO_INCREMENT voor een tabel `vaste_ritten`
--
ALTER TABLE `vaste_ritten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT voor een tabel `voertuigen`
--
ALTER TABLE `voertuigen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT voor een tabel `vr_mutaties`
--
ALTER TABLE `vr_mutaties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `vr_passagiers`
--
ALTER TABLE `vr_passagiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `vr_routes`
--
ALTER TABLE `vr_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT voor een tabel `week_notities`
--
ALTER TABLE `week_notities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `afwezigheid`
--
ALTER TABLE `afwezigheid`
  ADD CONSTRAINT `afwezigheid_ibfk_1` FOREIGN KEY (`chauffeur_id`) REFERENCES `chauffeurs` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `calculaties`
--
ALTER TABLE `calculaties`
  ADD CONSTRAINT `calculaties_ibfk_1` FOREIGN KEY (`klant_id`) REFERENCES `klanten` (`id`);

--
-- Beperkingen voor tabel `klant_afdelingen`
--
ALTER TABLE `klant_afdelingen`
  ADD CONSTRAINT `klant_afdelingen_ibfk_1` FOREIGN KEY (`klant_id`) REFERENCES `klanten` (`id`) ON DELETE CASCADE;

--
-- Beperkingen voor tabel `ritten`
--
ALTER TABLE `ritten`
  ADD CONSTRAINT `ritten_ibfk_1` FOREIGN KEY (`calculatie_id`) REFERENCES `calculaties` (`id`),
  ADD CONSTRAINT `ritten_ibfk_2` FOREIGN KEY (`chauffeur_id`) REFERENCES `chauffeurs` (`id`),
  ADD CONSTRAINT `ritten_ibfk_3` FOREIGN KEY (`voertuig_id`) REFERENCES `voertuigen` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
