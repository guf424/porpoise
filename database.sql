-- MySQL dump 10.11
--
-- Host: localhost    Database: eduroamtest
-- ------------------------------------------------------
-- Server version	5.0.67-0ubuntu6.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Action`
--

DROP TABLE IF EXISTS `Action`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Action` (
  `id` int(11) NOT NULL auto_increment,
  `uri` varchar(1024) default NULL,
  `label` varchar(255) default NULL,
  `poiId` int(11) default NULL,
  `contentType` varchar(255) default NULL,
  `method` varchar(50) default NULL,
  `activityType` int(11) default NULL,
  `params` varchar(1024) default NULL,
  `closeBiw` tinyint(1) default '0',
  `showActivity` tinyint(1) default '1',
  `activityMessage` varchar(255) default NULL,
  `autoTriggerRange` int(11) default NULL,
  `autoTriggerOnly` tinyint(1) default '0',
  PRIMARY KEY  (`id`),
  KEY `poiId` (`poiId`),
  CONSTRAINT `Action_ibfk_1` FOREIGN KEY (`poiId`) REFERENCES `POI` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Animation`
--

DROP TABLE IF EXISTS `Animation`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Animation` (
  `id` int(11) NOT NULL auto_increment,
  `poiId` int(11) default NULL,
	`event` varchar(50) NOT NULL,
	`type` varchar(50) NOT NULL,
	`length` int(11) NOT NULL,
	`delay` int(11) default NULL,
	`interpolation` varchar(50) default NULL,
	`interpolationParam` double precision default NULL,
	`persist` tinyint(1) default 0,
	`repeat` tinyint(1) default 0,
	`from` double precision default NULL,
	`to` double precision default NULL,
	`axis` varchar(50) default NULL,
  PRIMARY KEY  (`id`),
  KEY `poiId` (`poiId`),
  CONSTRAINT `Animation_ibfk_1` FOREIGN KEY (`poiId`) REFERENCES `POI` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Layer`
--

DROP TABLE IF EXISTS `Layer`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Layer` (
  `layer` varchar(255) default NULL,
  `refreshInterval` int(11) default '300',
  `refreshDistance` int(11) default '100',
  `fullRefresh` tinyint(1) default '1',
  `showMessage` varchar(1024) default NULL,
  `id` int(11) NOT NULL auto_increment,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Object`
--

DROP TABLE IF EXISTS `Object`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Object` (
  `poiID` int(11) NOT NULL,
  `baseURL` varchar(1000) default NULL,
  `full` varchar(255) default NULL,
  `reduced` varchar(255) default NULL,
  `icon` varchar(255) default NULL,
  `size` int(11) default NULL,
  PRIMARY KEY  (`poiID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `POI`
--

DROP TABLE IF EXISTS `POI`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `POI` (
  `id` int(11) NOT NULL auto_increment,
  `attribution` varchar(255) default NULL,
  `imageURL` varchar(1024) default NULL,
  `lat` double default NULL,
  `lon` double default NULL,
  `line2` varchar(255) default NULL,
  `line3` varchar(255) default NULL,
  `line4` varchar(255) default NULL,
  `title` varchar(255) default NULL,
  `type` varchar(255) default NULL,
  `doNotIndex` tinyint(1) default '0',
  `showSmallBiw` tinyint(1) default '1',
  `showBiwOnClick` tinyint(1) default '1',
  `layerID` int(11) default NULL,
  `dimension` int(11) default NULL,
  `alt` int(11) default NULL,
  `relativeAlt` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=971468 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `Transform`
--

DROP TABLE IF EXISTS `Transform`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `Transform` (
  `angle` int(11) default NULL,
  `rel` tinyint(1) default NULL,
  `scale` float default NULL,
  `poiID` int(11) NOT NULL,
  PRIMARY KEY  (`poiID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-08-24 19:15:29
