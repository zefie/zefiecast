-- phpMyAdmin SQL Dump
-- version 4.3.12
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 27, 2015 at 05:46 PM
-- Server version: 10.0.17-MariaDB-log
-- PHP Version: 5.6.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `zcast`
--

-- --------------------------------------------------------

--
-- Table structure for table `config`
--

CREATE TABLE IF NOT EXISTS `config` (
  `skip` enum('0','1') NOT NULL DEFAULT '0',
  `artistplay` int(11) NOT NULL DEFAULT '0',
  `titleplay` int(11) NOT NULL DEFAULT '0',
  `albumplay` int(11) NOT NULL DEFAULT '0',
  `queuesongs` int(11) NOT NULL DEFAULT '0',
  `enablereq` enum('0','1') NOT NULL DEFAULT '0',
  `artistreq` int(11) NOT NULL DEFAULT '0',
  `titlereq` int(11) NOT NULL DEFAULT '0',
  `albumreq` int(11) NOT NULL DEFAULT '0',
  `reqtime` int(11) NOT NULL DEFAULT '0',
  `reqlimit` int(11) NOT NULL DEFAULT '0',
  `filters` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`skip`, `artistplay`, `titleplay`, `albumplay`, `queuesongs`, `enablereq`, `artistreq`, `titlereq`, `albumreq`, `reqtime`, `reqlimit`, `filters`) VALUES
('0', 60, 120, 30, 3, '1', 60, 120, 30, 60, 2, 'volnorm=1');

-- --------------------------------------------------------

--
-- Table structure for table `historylist`
--

CREATE TABLE IF NOT EXISTS `historylist` (
  `ID` int(11) unsigned NOT NULL,
  `songid` int(11) NOT NULL DEFAULT '0',
  `played` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `queuelist`
--

CREATE TABLE IF NOT EXISTS `queuelist` (
  `songid` int(11) NOT NULL DEFAULT '0',
  `sortid` int(11) NOT NULL DEFAULT '0',
  `queued` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `requestlist`
--

CREATE TABLE IF NOT EXISTS `requestlist` (
  `ID` int(11) unsigned NOT NULL,
  `songid` int(11) NOT NULL DEFAULT '0',
  `address` varchar(15) NOT NULL DEFAULT '',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `songlist`
--

CREATE TABLE IF NOT EXISTS `songlist` (
  `ID` int(11) unsigned NOT NULL,
  `filetype` varchar(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'mp3',
  `filename` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `artist` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `album` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `track` int(2) DEFAULT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastplayed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `artistlastplayed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `titlelastplayed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `albumlastplayed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `length` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `historylist`
--
ALTER TABLE `historylist`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `queuelist`
--
ALTER TABLE `queuelist`
  ADD PRIMARY KEY (`sortid`);

--
-- Indexes for table `requestlist`
--
ALTER TABLE `requestlist`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `songlist`
--
ALTER TABLE `songlist`
  ADD PRIMARY KEY (`ID`), ADD UNIQUE KEY `filename` (`filename`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `historylist`
--
ALTER TABLE `historylist`
  MODIFY `ID` int(11) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `requestlist`
--
ALTER TABLE `requestlist`
  MODIFY `ID` int(11) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `songlist`
--
ALTER TABLE `songlist`
  MODIFY `ID` int(11) unsigned NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
