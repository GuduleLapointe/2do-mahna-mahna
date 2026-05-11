/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: osrobust
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0+deb12u2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agentprefs`
--

DROP TABLE IF EXISTS `agentprefs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `agentprefs` (
  `PrincipalID` char(36) NOT NULL,
  `AccessPrefs` char(2) NOT NULL DEFAULT 'M',
  `HoverHeight` double(30,27) NOT NULL DEFAULT 0.000000000000000000000000000,
  `Language` char(5) NOT NULL DEFAULT 'en-us',
  `LanguageIsPublic` tinyint(1) NOT NULL DEFAULT 1,
  `PermEveryone` int(6) NOT NULL DEFAULT 0,
  `PermGroup` int(6) NOT NULL DEFAULT 0,
  `PermNextOwner` int(6) NOT NULL DEFAULT 532480,
  PRIMARY KEY (`PrincipalID`),
  UNIQUE KEY `PrincipalID` (`PrincipalID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `name` varchar(64) NOT NULL,
  `description` varchar(64) NOT NULL,
  `assetType` tinyint(4) NOT NULL,
  `local` tinyint(1) NOT NULL,
  `temporary` tinyint(1) NOT NULL,
  `data` longblob NOT NULL,
  `id` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `create_time` int(11) DEFAULT 0,
  `access_time` int(11) DEFAULT 0,
  `asset_flags` int(11) NOT NULL DEFAULT 0,
  `CreatorID` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `CreatorIDidx` (`CreatorID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev. 1';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth`
--

DROP TABLE IF EXISTS `auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth` (
  `UUID` char(36) NOT NULL,
  `passwordHash` char(32) NOT NULL DEFAULT '',
  `passwordSalt` char(32) NOT NULL DEFAULT '',
  `webLoginKey` varchar(255) NOT NULL DEFAULT '',
  `accountType` varchar(32) NOT NULL DEFAULT 'UserAccount',
  PRIMARY KEY (`UUID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `avatars`
--

DROP TABLE IF EXISTS `avatars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `avatars` (
  `PrincipalID` char(36) NOT NULL,
  `Name` varchar(32) NOT NULL,
  `Value` mediumtext DEFAULT NULL,
  PRIMARY KEY (`PrincipalID`,`Name`),
  KEY `PrincipalID` (`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `balances`
--

DROP TABLE IF EXISTS `balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `balances` (
  `user` varchar(128) NOT NULL,
  `balance` int(10) NOT NULL,
  `status` tinyint(2) DEFAULT NULL,
  PRIMARY KEY (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.2';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cacheusers`
--

DROP TABLE IF EXISTS `cacheusers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cacheusers` (
  `userid` char(36) NOT NULL DEFAULT '',
  `avatar` varchar(128) DEFAULT NULL,
  `grid` varchar(255) DEFAULT NULL,
  `login` int(11) DEFAULT NULL,
  PRIMARY KEY (`userid`),
  KEY `login` (`login`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `classifieds`
--

DROP TABLE IF EXISTS `classifieds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `classifieds` (
  `classifieduuid` char(36) NOT NULL,
  `creatoruuid` char(36) NOT NULL,
  `creationdate` int(20) NOT NULL,
  `expirationdate` int(20) NOT NULL,
  `category` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext NOT NULL,
  `parceluuid` char(36) NOT NULL,
  `parentestate` int(11) NOT NULL,
  `snapshotuuid` char(36) NOT NULL,
  `simname` varchar(255) NOT NULL,
  `posglobal` varchar(255) NOT NULL,
  `parcelname` varchar(255) NOT NULL,
  `classifiedflags` int(8) NOT NULL,
  `priceforlisting` int(5) NOT NULL,
  PRIMARY KEY (`classifieduuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estate_groups`
--

DROP TABLE IF EXISTS `estate_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_groups` (
  `EstateID` int(10) unsigned NOT NULL,
  `uuid` char(36) NOT NULL,
  KEY `EstateID` (`EstateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estate_managers`
--

DROP TABLE IF EXISTS `estate_managers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_managers` (
  `EstateID` int(10) unsigned NOT NULL,
  `uuid` char(36) NOT NULL,
  KEY `EstateID` (`EstateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estate_map`
--

DROP TABLE IF EXISTS `estate_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_map` (
  `RegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `EstateID` int(11) NOT NULL,
  PRIMARY KEY (`RegionID`),
  KEY `EstateID` (`EstateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estate_settings`
--

DROP TABLE IF EXISTS `estate_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_settings` (
  `EstateID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `EstateName` varchar(64) DEFAULT NULL,
  `AbuseEmailToEstateOwner` tinyint(4) NOT NULL,
  `DenyAnonymous` tinyint(4) NOT NULL,
  `ResetHomeOnTeleport` tinyint(4) NOT NULL,
  `FixedSun` tinyint(4) NOT NULL,
  `DenyTransacted` tinyint(4) NOT NULL,
  `BlockDwell` tinyint(4) NOT NULL,
  `DenyIdentified` tinyint(4) NOT NULL,
  `AllowVoice` tinyint(4) NOT NULL,
  `UseGlobalTime` tinyint(4) NOT NULL,
  `PricePerMeter` int(11) NOT NULL,
  `TaxFree` tinyint(4) NOT NULL,
  `AllowDirectTeleport` tinyint(4) NOT NULL,
  `RedirectGridX` int(11) NOT NULL,
  `RedirectGridY` int(11) NOT NULL,
  `ParentEstateID` int(10) unsigned NOT NULL,
  `SunPosition` double NOT NULL,
  `EstateSkipScripts` tinyint(4) NOT NULL,
  `BillableFactor` float NOT NULL,
  `PublicAccess` tinyint(4) NOT NULL,
  `AbuseEmail` varchar(255) NOT NULL,
  `EstateOwner` varchar(36) NOT NULL,
  `DenyMinors` tinyint(4) NOT NULL,
  `AllowLandmark` tinyint(4) NOT NULL DEFAULT 1,
  `AllowParcelChanges` tinyint(4) NOT NULL DEFAULT 1,
  `AllowSetHome` tinyint(4) NOT NULL DEFAULT 1,
  `AllowEnviromentOverride` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`EstateID`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estate_users`
--

DROP TABLE IF EXISTS `estate_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estate_users` (
  `EstateID` int(10) unsigned NOT NULL,
  `uuid` char(36) NOT NULL,
  KEY `EstateID` (`EstateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estateban`
--

DROP TABLE IF EXISTS `estateban`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estateban` (
  `EstateID` int(10) unsigned NOT NULL,
  `bannedUUID` varchar(36) NOT NULL,
  `bannedIp` varchar(16) NOT NULL,
  `bannedIpHostMask` varchar(16) NOT NULL,
  `bannedNameMask` varchar(64) DEFAULT NULL,
  `banningUUID` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `banTime` int(11) NOT NULL DEFAULT 0,
  KEY `estateban_EstateID` (`EstateID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `friends`
--

DROP TABLE IF EXISTS `friends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `friends` (
  `PrincipalID` varchar(255) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `Friend` varchar(255) NOT NULL,
  `Flags` varchar(16) NOT NULL DEFAULT '0',
  `Offered` varchar(32) NOT NULL DEFAULT '0',
  PRIMARY KEY (`PrincipalID`(36),`Friend`(36)),
  KEY `PrincipalID` (`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fsassets`
--

DROP TABLE IF EXISTS `fsassets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fsassets` (
  `id` char(36) NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT '',
  `description` varchar(64) NOT NULL DEFAULT '',
  `type` int(11) NOT NULL,
  `hash` char(80) NOT NULL,
  `create_time` int(11) NOT NULL DEFAULT 0,
  `access_time` int(11) NOT NULL DEFAULT 0,
  `asset_flags` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `griduser`
--

DROP TABLE IF EXISTS `griduser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `griduser` (
  `UserID` varchar(255) NOT NULL,
  `HomeRegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `HomePosition` char(64) NOT NULL DEFAULT '<0,0,0>',
  `HomeLookAt` char(64) NOT NULL DEFAULT '<0,0,0>',
  `LastRegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `LastPosition` char(64) NOT NULL DEFAULT '<0,0,0>',
  `LastLookAt` char(64) NOT NULL DEFAULT '<0,0,0>',
  `Online` char(5) NOT NULL DEFAULT 'false',
  `Login` char(16) NOT NULL DEFAULT '0',
  `Logout` char(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`UserID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hg_traveling_data`
--

DROP TABLE IF EXISTS `hg_traveling_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hg_traveling_data` (
  `SessionID` varchar(36) NOT NULL,
  `UserID` varchar(36) NOT NULL,
  `GridExternalName` varchar(255) NOT NULL DEFAULT '',
  `ServiceToken` varchar(255) NOT NULL DEFAULT '',
  `ClientIPAddress` varchar(16) NOT NULL DEFAULT '',
  `MyIPAddress` varchar(16) NOT NULL DEFAULT '',
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`SessionID`),
  KEY `UserID` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `im_offline`
--

DROP TABLE IF EXISTS `im_offline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `im_offline` (
  `ID` mediumint(9) NOT NULL AUTO_INCREMENT,
  `PrincipalID` char(36) NOT NULL DEFAULT '',
  `FromID` char(36) NOT NULL DEFAULT '',
  `Message` mediumtext NOT NULL,
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `PrincipalID` (`PrincipalID`),
  KEY `FromID` (`FromID`)
) ENGINE=MyISAM AUTO_INCREMENT=7007 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventoryfolders`
--

DROP TABLE IF EXISTS `inventoryfolders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventoryfolders` (
  `folderName` varchar(64) DEFAULT NULL,
  `type` smallint(6) NOT NULL DEFAULT 0,
  `version` int(11) NOT NULL DEFAULT 0,
  `folderID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `agentID` char(36) DEFAULT NULL,
  `parentFolderID` char(36) DEFAULT NULL,
  PRIMARY KEY (`folderID`),
  KEY `inventoryfolders_agentid` (`agentID`),
  KEY `inventoryfolders_parentFolderid` (`parentFolderID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventoryitems`
--

DROP TABLE IF EXISTS `inventoryitems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventoryitems` (
  `assetID` varchar(36) DEFAULT NULL,
  `assetType` int(11) DEFAULT NULL,
  `inventoryName` varchar(64) DEFAULT NULL,
  `inventoryDescription` varchar(128) DEFAULT NULL,
  `inventoryNextPermissions` int(10) unsigned DEFAULT NULL,
  `inventoryCurrentPermissions` int(10) unsigned DEFAULT NULL,
  `invType` int(11) DEFAULT NULL,
  `creatorID` varchar(255) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `inventoryBasePermissions` int(10) unsigned NOT NULL DEFAULT 0,
  `inventoryEveryOnePermissions` int(10) unsigned NOT NULL DEFAULT 0,
  `salePrice` int(11) NOT NULL DEFAULT 0,
  `saleType` tinyint(4) NOT NULL DEFAULT 0,
  `creationDate` int(11) NOT NULL DEFAULT 0,
  `groupID` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `groupOwned` tinyint(4) NOT NULL DEFAULT 0,
  `flags` int(11) unsigned NOT NULL DEFAULT 0,
  `inventoryID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `avatarID` char(36) DEFAULT NULL,
  `parentFolderID` char(36) DEFAULT NULL,
  `inventoryGroupPermissions` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`inventoryID`),
  KEY `inventoryitems_avatarid` (`avatarID`),
  KEY `inventoryitems_parentFolderid` (`parentFolderID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `name` varchar(100) DEFAULT NULL,
  `version` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mutelist`
--

DROP TABLE IF EXISTS `mutelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mutelist` (
  `AgentID` char(36) NOT NULL,
  `MuteID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `MuteName` varchar(64) NOT NULL DEFAULT '',
  `MuteType` int(11) NOT NULL DEFAULT 1,
  `MuteFlags` int(11) NOT NULL DEFAULT 0,
  `Stamp` int(11) NOT NULL,
  UNIQUE KEY `AgentID_2` (`AgentID`,`MuteID`,`MuteName`),
  KEY `AgentID` (`AgentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offline`
--

DROP TABLE IF EXISTS `offline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline` (
  `uuid` varchar(36) NOT NULL,
  `message` mediumtext NOT NULL,
  KEY `uuid` (`uuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offline_message`
--

DROP TABLE IF EXISTS `offline_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline_message` (
  `to_uuid` varchar(36) NOT NULL,
  `from_uuid` varchar(36) NOT NULL,
  `message` mediumtext NOT NULL,
  KEY `to_uuid` (`to_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `openid_usrstore`
--

DROP TABLE IF EXISTS `openid_usrstore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `openid_usrstore` (
  `uid` varchar(128) NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `identity` varchar(128) DEFAULT NULL,
  `data` mediumtext DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `uuid` (`uuid`),
  KEY `identity` (`identity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_groups`
--

DROP TABLE IF EXISTS `os_groups_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_groups` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `Location` varchar(255) NOT NULL DEFAULT '',
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Charter` mediumtext NOT NULL,
  `InsigniaID` char(36) NOT NULL DEFAULT '',
  `FounderID` char(36) NOT NULL DEFAULT '',
  `MembershipFee` int(11) NOT NULL DEFAULT 0,
  `OpenEnrollment` varchar(255) NOT NULL DEFAULT '',
  `ShowInList` int(4) NOT NULL DEFAULT 0,
  `AllowPublish` int(4) NOT NULL DEFAULT 0,
  `MaturePublish` int(4) NOT NULL DEFAULT 0,
  `OwnerRoleID` char(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`GroupID`),
  UNIQUE KEY `Name` (`Name`),
  FULLTEXT KEY `Name_2` (`Name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_invites`
--

DROP TABLE IF EXISTS `os_groups_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_invites` (
  `InviteID` char(36) NOT NULL DEFAULT '',
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`InviteID`),
  UNIQUE KEY `PrincipalGroup` (`GroupID`,`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_membership`
--

DROP TABLE IF EXISTS `os_groups_membership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_membership` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `SelectedRoleID` char(36) NOT NULL DEFAULT '',
  `Contribution` int(11) NOT NULL DEFAULT 0,
  `ListInProfile` int(4) NOT NULL DEFAULT 1,
  `AcceptNotices` int(4) NOT NULL DEFAULT 1,
  `AccessToken` char(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`GroupID`,`PrincipalID`),
  KEY `PrincipalID` (`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_notices`
--

DROP TABLE IF EXISTS `os_groups_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_notices` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `NoticeID` char(36) NOT NULL DEFAULT '',
  `TMStamp` int(10) unsigned NOT NULL DEFAULT 0,
  `FromName` varchar(255) NOT NULL DEFAULT '',
  `Subject` varchar(255) NOT NULL DEFAULT '',
  `Message` mediumtext NOT NULL,
  `HasAttachment` int(4) NOT NULL DEFAULT 0,
  `AttachmentType` int(4) NOT NULL DEFAULT 0,
  `AttachmentName` varchar(128) NOT NULL DEFAULT '',
  `AttachmentItemID` char(36) NOT NULL DEFAULT '',
  `AttachmentOwnerID` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`NoticeID`),
  KEY `GroupID` (`GroupID`),
  KEY `TMStamp` (`TMStamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_principals`
--

DROP TABLE IF EXISTS `os_groups_principals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_principals` (
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `ActiveGroupID` char(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_rolemembership`
--

DROP TABLE IF EXISTS `os_groups_rolemembership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_rolemembership` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`GroupID`,`RoleID`,`PrincipalID`),
  KEY `PrincipalID` (`PrincipalID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `os_groups_roles`
--

DROP TABLE IF EXISTS `os_groups_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_groups_roles` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Description` varchar(255) NOT NULL DEFAULT '',
  `Title` varchar(255) NOT NULL DEFAULT '',
  `Powers` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`GroupID`,`RoleID`),
  KEY `GroupID` (`GroupID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osagent`
--

DROP TABLE IF EXISTS `osagent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osagent` (
  `AgentID` varchar(128) NOT NULL DEFAULT '',
  `ActiveGroupID` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`AgentID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osgroup`
--

DROP TABLE IF EXISTS `osgroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osgroup` (
  `GroupID` varchar(128) NOT NULL DEFAULT '',
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Charter` mediumtext NOT NULL,
  `InsigniaID` varchar(128) NOT NULL DEFAULT '',
  `FounderID` varchar(128) NOT NULL DEFAULT '',
  `MembershipFee` int(11) NOT NULL DEFAULT 0,
  `OpenEnrollment` varchar(255) NOT NULL DEFAULT '',
  `ShowInList` tinyint(1) NOT NULL DEFAULT 0,
  `AllowPublish` tinyint(1) NOT NULL DEFAULT 0,
  `MaturePublish` tinyint(1) NOT NULL DEFAULT 0,
  `OwnerRoleID` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`GroupID`),
  UNIQUE KEY `Name` (`Name`),
  FULLTEXT KEY `Name_2` (`Name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osgroupinvite`
--

DROP TABLE IF EXISTS `osgroupinvite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osgroupinvite` (
  `InviteID` varchar(128) NOT NULL DEFAULT '',
  `GroupID` varchar(128) NOT NULL DEFAULT '',
  `RoleID` varchar(128) NOT NULL DEFAULT '',
  `AgentID` varchar(128) NOT NULL DEFAULT '',
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`InviteID`),
  UNIQUE KEY `GroupID` (`GroupID`,`RoleID`,`AgentID`) USING HASH
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osgroupmembership`
--

DROP TABLE IF EXISTS `osgroupmembership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osgroupmembership` (
  `GroupID` varchar(128) NOT NULL DEFAULT '',
  `AgentID` varchar(128) NOT NULL DEFAULT '',
  `SelectedRoleID` varchar(128) NOT NULL DEFAULT '',
  `Contribution` int(11) NOT NULL DEFAULT 0,
  `ListInProfile` int(11) NOT NULL DEFAULT 1,
  `AcceptNotices` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`GroupID`,`AgentID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osgroupnotice`
--

DROP TABLE IF EXISTS `osgroupnotice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osgroupnotice` (
  `GroupID` varchar(128) NOT NULL DEFAULT '',
  `NoticeID` varchar(128) NOT NULL DEFAULT '',
  `Timestamp` int(10) unsigned NOT NULL DEFAULT 0,
  `FromName` varchar(255) NOT NULL DEFAULT '',
  `Subject` varchar(255) NOT NULL DEFAULT '',
  `Message` mediumtext NOT NULL,
  `BinaryBucket` mediumtext NOT NULL,
  PRIMARY KEY (`GroupID`,`NoticeID`),
  KEY `Timestamp` (`Timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osgrouprolemembership`
--

DROP TABLE IF EXISTS `osgrouprolemembership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osgrouprolemembership` (
  `GroupID` varchar(36) NOT NULL DEFAULT '',
  `RoleID` varchar(36) NOT NULL DEFAULT '',
  `AgentID` varchar(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`GroupID`,`RoleID`,`AgentID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `osrole`
--

DROP TABLE IF EXISTS `osrole`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `osrole` (
  `GroupID` varchar(128) NOT NULL DEFAULT '',
  `RoleID` varchar(128) NOT NULL DEFAULT '',
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Description` varchar(255) NOT NULL DEFAULT '',
  `Title` varchar(255) NOT NULL DEFAULT '',
  `Powers` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`GroupID`,`RoleID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `presence`
--

DROP TABLE IF EXISTS `presence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `presence` (
  `UserID` varchar(255) NOT NULL,
  `RegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `SessionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `SecureSessionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `LastSeen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY `SessionID` (`SessionID`),
  KEY `UserID` (`UserID`),
  KEY `RegionID` (`RegionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `regions`
--

DROP TABLE IF EXISTS `regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regions` (
  `uuid` varchar(36) NOT NULL,
  `regionHandle` bigint(20) unsigned NOT NULL,
  `regionName` varchar(128) DEFAULT NULL,
  `regionRecvKey` varchar(128) DEFAULT NULL,
  `regionSendKey` varchar(128) DEFAULT NULL,
  `regionSecret` varchar(128) DEFAULT NULL,
  `regionDataURI` varchar(255) DEFAULT NULL,
  `serverIP` varchar(64) DEFAULT NULL,
  `serverPort` int(10) unsigned DEFAULT NULL,
  `serverURI` varchar(255) DEFAULT NULL,
  `locX` int(10) unsigned DEFAULT NULL,
  `locY` int(10) unsigned DEFAULT NULL,
  `locZ` int(10) unsigned DEFAULT NULL,
  `eastOverrideHandle` bigint(20) unsigned DEFAULT NULL,
  `westOverrideHandle` bigint(20) unsigned DEFAULT NULL,
  `southOverrideHandle` bigint(20) unsigned DEFAULT NULL,
  `northOverrideHandle` bigint(20) unsigned DEFAULT NULL,
  `regionAssetURI` varchar(255) DEFAULT NULL,
  `regionAssetRecvKey` varchar(128) DEFAULT NULL,
  `regionAssetSendKey` varchar(128) DEFAULT NULL,
  `regionUserURI` varchar(255) DEFAULT NULL,
  `regionUserRecvKey` varchar(128) DEFAULT NULL,
  `regionUserSendKey` varchar(128) DEFAULT NULL,
  `regionMapTexture` varchar(36) DEFAULT NULL,
  `serverHttpPort` int(10) DEFAULT NULL,
  `serverRemotingPort` int(10) DEFAULT NULL,
  `owner_uuid` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `originUUID` varchar(36) DEFAULT NULL,
  `access` int(10) unsigned DEFAULT 1,
  `ScopeID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `sizeX` int(11) NOT NULL DEFAULT 0,
  `sizeY` int(11) NOT NULL DEFAULT 0,
  `flags` int(11) NOT NULL DEFAULT 0,
  `last_seen` int(11) NOT NULL DEFAULT 0,
  `PrincipalID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `Token` varchar(255) NOT NULL,
  `parcelMapTexture` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `regionName` (`regionName`),
  KEY `regionHandle` (`regionHandle`),
  KEY `overrideHandles` (`eastOverrideHandle`,`westOverrideHandle`,`southOverrideHandle`,`northOverrideHandle`),
  KEY `ScopeID` (`ScopeID`),
  KEY `flags` (`flags`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=FIXED COMMENT='Rev. 3';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tokens` (
  `UUID` char(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `validity` datetime NOT NULL,
  UNIQUE KEY `uuid_token` (`UUID`,`token`),
  KEY `UUID` (`UUID`),
  KEY `token` (`token`),
  KEY `validity` (`validity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `UUID` varchar(36) NOT NULL,
  `sender` varchar(128) NOT NULL,
  `receiver` varchar(128) NOT NULL,
  `amount` int(10) NOT NULL,
  `objectUUID` varchar(36) DEFAULT NULL,
  `regionHandle` varchar(36) NOT NULL,
  `type` int(10) NOT NULL,
  `time` int(11) NOT NULL,
  `secure` varchar(36) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `commonName` varchar(128) NOT NULL,
  PRIMARY KEY (`UUID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.7';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `useraccounts`
--

DROP TABLE IF EXISTS `useraccounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `useraccounts` (
  `PrincipalID` char(36) NOT NULL,
  `ScopeID` char(36) NOT NULL,
  `FirstName` varchar(64) NOT NULL,
  `LastName` varchar(64) NOT NULL,
  `Email` varchar(64) DEFAULT NULL,
  `ServiceURLs` text DEFAULT NULL,
  `Created` int(11) DEFAULT NULL,
  `UserLevel` int(11) NOT NULL DEFAULT 0,
  `UserFlags` int(11) NOT NULL DEFAULT 0,
  `UserTitle` varchar(64) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 1,
  UNIQUE KEY `PrincipalID` (`PrincipalID`),
  KEY `Email` (`Email`),
  KEY `FirstName` (`FirstName`),
  KEY `LastName` (`LastName`),
  KEY `Name` (`FirstName`,`LastName`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userdata`
--

DROP TABLE IF EXISTS `userdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userdata` (
  `UserId` char(36) NOT NULL,
  `TagId` varchar(64) NOT NULL,
  `DataKey` varchar(255) DEFAULT NULL,
  `DataVal` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`UserId`,`TagId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userinfo`
--

DROP TABLE IF EXISTS `userinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userinfo` (
  `user` varchar(128) NOT NULL,
  `simip` varchar(64) NOT NULL,
  `avatar` varchar(50) NOT NULL,
  `pass` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.2';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usernotes`
--

DROP TABLE IF EXISTS `usernotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usernotes` (
  `useruuid` varchar(36) NOT NULL,
  `targetuuid` varchar(36) NOT NULL,
  `notes` mediumtext NOT NULL,
  UNIQUE KEY `useruuid` (`useruuid`,`targetuuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userpicks`
--

DROP TABLE IF EXISTS `userpicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userpicks` (
  `pickuuid` varchar(36) NOT NULL,
  `creatoruuid` varchar(36) NOT NULL,
  `toppick` enum('true','false') NOT NULL,
  `parceluuid` varchar(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext NOT NULL,
  `snapshotuuid` varchar(36) NOT NULL,
  `user` varchar(255) NOT NULL,
  `originalname` varchar(255) NOT NULL,
  `simname` varchar(255) NOT NULL,
  `posglobal` varchar(255) NOT NULL,
  `sortorder` int(2) NOT NULL,
  `enabled` enum('true','false') NOT NULL,
  `gatekeeper` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`pickuuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userprofile`
--

DROP TABLE IF EXISTS `userprofile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `userprofile` (
  `useruuid` varchar(36) NOT NULL,
  `profilePartner` varchar(36) NOT NULL,
  `profileAllowPublish` binary(1) NOT NULL,
  `profileMaturePublish` binary(1) NOT NULL,
  `profileURL` varchar(255) NOT NULL,
  `profileWantToMask` int(3) NOT NULL,
  `profileWantToText` mediumtext NOT NULL,
  `profileSkillsMask` int(3) NOT NULL,
  `profileSkillsText` mediumtext NOT NULL,
  `profileLanguages` mediumtext NOT NULL,
  `profileImage` varchar(36) NOT NULL,
  `profileAboutText` mediumtext NOT NULL,
  `profileFirstImage` varchar(36) NOT NULL,
  `profileFirstText` mediumtext NOT NULL,
  PRIMARY KEY (`useruuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usersettings`
--

DROP TABLE IF EXISTS `usersettings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usersettings` (
  `useruuid` varchar(36) NOT NULL,
  `imviaemail` enum('true','false') NOT NULL,
  `visible` enum('true','false') NOT NULL,
  `email` varchar(254) NOT NULL,
  PRIMARY KEY (`useruuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-11  5:38:54
