-- phpMyAdmin SQL Dump
-- version 3.4.0
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 14, 2011 at 11:37 AM
-- Server version: 5.5.12
-- PHP Version: 5.3.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


--
-- Database: `porpoise`
--

-- --------------------------------------------------------

--
-- Table structure for table `action`
--

CREATE TABLE IF NOT EXISTS `Action` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `uri` varchar(1024) DEFAULT NULL,
 `label` varchar(255) DEFAULT NULL,
 `poiId` int(11) NOT NULL,
 `contentType` varchar(255) DEFAULT NULL,
 `method` varchar(50) DEFAULT NULL,
 `activityType` int(11) DEFAULT NULL,
 `params` varchar(1024) DEFAULT NULL,
 `closeBiw` tinyint(1) DEFAULT '0',
 `showActivity` tinyint(1) DEFAULT '1',
 `activityMessage` varchar(255) DEFAULT NULL,
 `autoTrigger` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'Can
ONLY be used with feature tracked images',
 `autoTriggerRange` int(11) DEFAULT NULL,
 `autoTriggerOnly` tinyint(1) DEFAULT '0',
 PRIMARY KEY (`id`),
 KEY `poiId` (`poiId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `animation`
--


CREATE TABLE IF NOT EXISTS `Animation` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `poiId` int(11) NOT NULL,
 `event` varchar(50) NOT NULL,
 `type` varchar(50) NOT NULL,
 `length` int(11) NOT NULL,
 `delay` int(11) DEFAULT NULL,
 `interpolation` varchar(50) DEFAULT NULL,
 `interpolationParam` double DEFAULT NULL,
 `persist` tinyint(1) DEFAULT '0',
 `repeat` tinyint(1) DEFAULT '0',
 `from` double DEFAULT NULL,
 `to` double DEFAULT NULL,
 `axis` varchar(50) DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `poiId` (`poiId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `layer`
--

CREATE TABLE IF NOT EXISTS `Layer` (
 `layer` varchar(255) DEFAULT NULL,
 `refreshInterval` int(11) DEFAULT '300',
 `refreshDistance` int(11) DEFAULT '100',
 `fullRefresh` tinyint(1) DEFAULT '1',
 `showMessage` varchar(1024) DEFAULT NULL,
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `biwStyle` varchar(9) DEFAULT NULL,
 `disableClueMenu` tinyint(1) unsigned NOT NULL DEFAULT '0',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `poi`
--

CREATE TABLE IF NOT EXISTS `POI` (
 `biwStyle` varchar(9) DEFAULT NULL,
 `doNotIndex` tinyint(1) unsigned DEFAULT NULL,
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `imageURL` varchar(1024) DEFAULT NULL,
 `layerID` int(11) DEFAULT NULL,
 `showBiwOnClick` tinyint(1) unsigned DEFAULT '1',
 `showSmallBiw` tinyint(1) unsigned DEFAULT '1',
 `anchor_geolocation_alt` float DEFAULT NULL,
 `anchor_geolocation_lat` double DEFAULT NULL,
 `anchor_geolocation_lon` double DEFAULT NULL,
 `anchor_referenceImage` varchar(64) CHARACTER SET ascii DEFAULT
NULL,
 `icon_type` int(4) DEFAULT NULL,
 `icon_url` varchar(128) DEFAULT NULL COMMENT 'Use either an
icon_type or an icon_url. Both makes no sense',
 `object_contentType` varchar(64) DEFAULT NULL,
 `object_reducedURL` varchar(255) DEFAULT NULL,
 `object_size` float DEFAULT NULL,
 `object_url` varchar(255) CHARACTER SET ascii DEFAULT NULL,
 `text_description` text CHARACTER SET utf8,
 `text_footnote` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
 `text_title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
 `transform_rotate_angle` int(11) DEFAULT NULL,
 `transform_rotate_axis_x` float unsigned DEFAULT '0',
 `transform_rotate_axis_y` float unsigned DEFAULT '0',
 `transform_rotate_axis_z` float unsigned DEFAULT '0',
 `transform_rotate_rel` tinyint(1) DEFAULT NULL,
 `transform_scale` float DEFAULT NULL,
 `transform_translate_x` float unsigned DEFAULT '0',
 `transform_translate_y` float unsigned DEFAULT '0',
 `transform_translate_z` float unsigned DEFAULT '0',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `action`
--

ALTER TABLE `Action`
 ADD CONSTRAINT `Action_ibfk_1` FOREIGN KEY (`poiId`) REFERENCES
`POI` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `animation`
--

ALTER TABLE `Animation`
 ADD CONSTRAINT `Animation_ibfk_1` FOREIGN KEY (`poiId`) REFERENCES
`POI` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
