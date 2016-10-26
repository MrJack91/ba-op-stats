-- phpMyAdmin SQL Dump
-- version 4.5.5
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Erstellungszeit: 01. Aug 2016 um 18:05
-- Server-Version: 5.5.42
-- PHP-Version: 5.6.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `ba_op_stats`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Operation`
--

CREATE TABLE `Operation` (
  `ops_id` int(11) UNSIGNED NOT NULL,
  `PID` int(11) UNSIGNED NOT NULL,
  `OPDatum` date NOT NULL,
  `Wochentag` tinyint(4) UNSIGNED NOT NULL,
  `OPSaal` tinyint(4) UNSIGNED NOT NULL,
  `Reihe` tinyint(4) UNSIGNED NOT NULL,
  `FolgeOP` tinyint(4) UNSIGNED NOT NULL,
  `hasAnesthesia` tinyint(4) UNSIGNED NOT NULL,
  `Dringlichkeit` tinyint(4) UNSIGNED NOT NULL,
  `Zeitprognose` int(11) UNSIGNED NOT NULL,
  `Bestellzeit` datetime DEFAULT NULL,
  `ANAStart` datetime DEFAULT NULL,
  `SaalStart` datetime DEFAULT NULL,
  `ANABereit` datetime DEFAULT NULL,
  `OPStart` datetime DEFAULT NULL,
  `OPEnde` datetime DEFAULT NULL,
  `PatFreigabe` datetime DEFAULT NULL,
  `SaalEnde` datetime DEFAULT NULL,
  `ANAEnde` datetime DEFAULT NULL,
  `Hauptoperateur` varchar(10) DEFAULT NULL,
  `ANAOA` varchar(10) DEFAULT NULL,
  `ANAArt` tinyint(4) UNSIGNED DEFAULT NULL,
  `Zeitverzoegerung` varchar(255) DEFAULT NULL,
  `Urteil` tinyint(4) UNSIGNED DEFAULT NULL,
  `Klinik` varchar(10) NOT NULL,
  `SGARCode1` varchar(10) NOT NULL,
  `SGARCode2` varchar(10) DEFAULT NULL,
  `SGARCode3` varchar(10) DEFAULT NULL,
  `Verlegungsort` varchar(10) DEFAULT NULL,
  `OperateurLevel` tinyint(4) UNSIGNED DEFAULT NULL,
  `AnaesthLevel` tinyint(4) UNSIGNED DEFAULT NULL,
  `AllgANA` varchar(10) NOT NULL,
  `RegANA` varchar(10) NOT NULL,
  `Modus` varchar(10) NOT NULL,
  `PatGeb` date NOT NULL,
  `PatGender` tinyint(4) UNSIGNED NOT NULL,
  `ASARisk` tinyint(4) NOT NULL,
  `Gewicht` smallint(5) UNSIGNED NOT NULL,
  `Groesse` smallint(5) UNSIGNED NOT NULL,
  `HT` tinyint(11) UNSIGNED DEFAULT NULL,
  `Raucher` tinyint(11) UNSIGNED DEFAULT NULL,
  `NI` tinyint(11) UNSIGNED DEFAULT NULL,
  `rel_anamie` tinyint(3) UNSIGNED NOT NULL,
  `rel_diabetes` tinyint(3) UNSIGNED NOT NULL,
  `rel_adipositas` tinyint(3) UNSIGNED NOT NULL,
  `rel_gerinnungsstoerung` tinyint(3) UNSIGNED NOT NULL,
  `rel_allergie` tinyint(3) UNSIGNED NOT NULL,
  `rel_immunsuppression` tinyint(3) UNSIGNED NOT NULL,
  `rel_medikamente` tinyint(3) UNSIGNED NOT NULL,
  `rel_malignom` tinyint(3) UNSIGNED NOT NULL,
  `rel_schwangerschaft` tinyint(3) UNSIGNED NOT NULL,
  `Freitext` varchar(1000) NOT NULL,
  `csvLinePos` int(11) NOT NULL,
  `csvData` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `Operation`
--
ALTER TABLE `Operation`
  ADD PRIMARY KEY (`ops_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `Operation`
--
ALTER TABLE `Operation`
  MODIFY `ops_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
