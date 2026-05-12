-- Official parce storage in OpenSimulator

/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `land` (
    -- Needed for helpers features
  `UUID` varchar(255) NOT NULL,             -- aka parcelUUID
  `Name` varchar(255) DEFAULT NULL,         -- aka parcelName
  `RegionUUID` varchar(255) DEFAULT NULL,
  `SnapshotUUID` varchar(255) DEFAULT NULL, -- aka imageUUID
  `Description` varchar(255) DEFAULT NULL,
  `Area` int(11) DEFAULT NULL,              -- aka parcelAra
  `Dwell` int(11) NOT NULL DEFAULT 0,
  `LandFlags` int(10) unsigned DEFAULT NULL, -- bitwise, includes ForSale(4), MaturePublish(2^18), DenyAgeUnverified(2^31)...
  `Bitmap` longblob DEFAULT NULL,           -- map of region pieces belonging to the parcel
  `Category` int(11) DEFAULT NULL,          -- aka searchCategory, -1 = all, 0 = not set, >0 = category. OS seems to allow only 1 category or all, bitwise used for search
  `LandingType` int(11) DEFAULT NULL,       -- 0 = blocked, 1 = landing poit set, 2 = landing allowed everywhere
  `UserLocationX` float DEFAULT NULL,           -- aka landingPoint[x]
  `UserLocationY` float DEFAULT NULL,           -- aka landingPoint[y]
  `UserLocationZ` float DEFAULT NULL,           -- aka landingPoint[z]
  `SalePrice` int(11) DEFAULT NULL,

  -- Not yet needed for helpers features, but relevant
  `LocalLandID` int(11) DEFAULT NULL,   -- index of the parcel in the region
  `OwnerUUID` varchar(255) DEFAULT NULL,
  `GroupUUID` varchar(255) DEFAULT NULL,
  `IsGroupOwned` int(11) DEFAULT NULL,
  `LandStatus` int(11) DEFAULT NULL,    -- -1 = None, 0 = Leased, 1 = LeasePending, 2 = Abandonned

  -- Not needed
  `AuctionID` int(11) DEFAULT NULL,
  `ClaimDate` int(11) DEFAULT NULL,
  `ClaimPrice` int(11) DEFAULT NULL,
  `MediaAutoScale` int(11) DEFAULT NULL,
  `MediaTextureUUID` varchar(255) DEFAULT NULL,
  `MediaURL` varchar(255) DEFAULT NULL,
  `MusicURL` varchar(255) DEFAULT NULL,
  `PassHours` float DEFAULT NULL,
  `PassPrice` int(11) DEFAULT NULL,
  `UserLookAtX` float DEFAULT NULL,
  `UserLookAtY` float DEFAULT NULL,
  `UserLookAtZ` float DEFAULT NULL,
  `AuthbuyerID` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `OtherCleanTime` int(11) NOT NULL DEFAULT 0,
  `MediaType` varchar(32) NOT NULL DEFAULT 'none/none',
  `MediaDescription` varchar(255) NOT NULL DEFAULT '',
  `MediaSize` varchar(16) NOT NULL DEFAULT '0,0',
  `MediaLoop` tinyint(1) NOT NULL DEFAULT 0,
  `ObscureMusic` tinyint(1) NOT NULL DEFAULT 0,
  `ObscureMedia` tinyint(1) NOT NULL DEFAULT 0,
  `SeeAVs` tinyint(4) NOT NULL DEFAULT 1,       -- Undocumented
  `AnyAVSounds` tinyint(4) NOT NULL DEFAULT 1,
  `GroupAVSounds` tinyint(4) NOT NULL DEFAULT 1,
  `environment` mediumtext DEFAULT NULL,
  PRIMARY KEY (`UUID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- OSSearch parcel storage

CREATE TABLE IF NOT EXISTS `parcels` (
  `parcelUUID` char(36) NOT NULL,           -- opensim.land=>UUID
  `parcelName` varchar(255) NOT NULL,       -- opensim.land=>name
  `regionUUID` char(36) NOT NULL,           -- opensim.land=>regionUUID
  `imageUUID` char(36),                     -- opensim.land=>SnapshotUUID
  `description` varchar(255) NOT NULL,      -- opensim.land=>description
  `parcelArea` int(6) NOT NULL,             -- opensim.land=>area int(11)
  `dwell` float NOT NULL default '0',       -- land=>dwell
  `landFlags` int(10) unsigned DEFAULT NULL, -- bitwise, includes ForSale(4), MaturePublish(2^18), DenyAgeUnverified(2^31)...
  `bitmap` longblob DEFAULT NULL,           -- opensim.land=>Bitmap - map of region pieces belonging to the parcel
  `searchCategory` varchar(50) NOT NULL,    -- land=>category int(11) (bitwise), -1 = all, 0 = not set, >0 = category. OS seems to allow only 1 category or all, bitwise used for search
  `landingType` int(11) DEFAULT NULL,       -- 0 = blocked, 1 = landing point set, 2 = landing allowed everywhere
  `salePrice` int(11) NOT NULL,                 -- move from table, land=>SalePrice
  `ownerUUID` varchar(255) DEFAULT NULL,    -- longer than usual varchar(36), allows including grid for foreing users
  `groupUUID` varchar(255) DEFAULT NULL,    -- longer than usual varchar(36), allows including grid for foreign groups

  -- Not yet needed for helpers features, but relevant
  `localLandID` int(11) DEFAULT NULL,       -- parcel index in the region
  `isGroupOwned` int(11) DEFAULT NULL,      -- actual owner is groupUUID instead of ownerUUID
  `landStatus` int(11) DEFAULT NULL,        -- -1 = None, 0 = Leased, 1 = LeasePending, 2 = Abandonned

  -- Non canonical, construct from canonical data or import process
  `landingPoint` varchar(255) NOT NULL,     -- opensim.land=>UserLocation[X|Y|Z] -> "x/y/z (float)"
  `infoUUID` varchar(36) NOT NULL default '', -- search/hypergrid internals, fake parcelid resolving to region handle, see helpers functions.php
  `mature` varchar(10) NOT NULL default 'PG', -- derived from bitwise land=>landFlags 0=PG 2^18=M, 2^31=A
  `gatekeeperURL` varchar(255),             -- search/2do internals
  `has_picture` tinyint(1) NOT NULL,        -- boolean (imageUUID != NULL_KEY)
  `forSale`   tinyint(1) NOT NULL,        -- boolean (landFlags & {ForSale|4})
  `build` enum('true','false') NOT NULL,    --  land->landFlags & {CreateObjects|64}
  `script` enum('true','false') NOT NULL,   -- land->landFlags & {AllowOtherScripts|2}
  `public` enum('true','false') NOT NULL,   -- land->landingType? (to verify)
  `parentEstate` int(11) NOT NULL default '1',  -- Deprecated, never used, kept only for table backwards compatibility
  PRIMARY KEY  (`regionUUID`,`parcelUUID`),
  KEY `name` (`parcelname`),
  KEY `description` (`description`),
  KEY `searchcategory` (`searchcategory`),
  KEY `dwell` (`dwell`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Totaly redundant, same data as parcels
-- Instead, use LandFlags & {ShowDirectory|2^12) to filter parcels available for search
--
-- CREATE TABLE IF NOT EXISTS `allparcels` (
-- ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- DEPRECATED, use unified parcels instead, with landFlags & {ForSale|4} filtering
CREATE TABLE IF NOT EXISTS `parcelsales` (
    -- Could be ignored by setting ForSale and SalePrice values in Parcel model
  `parcelUUID` char(36) NOT NULL,
  `regionUUID` char(36) NOT NULL,
  `parcelname` varchar(255) NOT NULL,
  `landingpoint` varchar(255) NOT NULL,
  `mature` varchar(10) NOT NULL default 'PG',
  `dwell` int(11) NOT NULL,
  `infoUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
  `area` int(6) NOT NULL,

  `gatekeeperURL` varchar(255),

  `saleprice` int(11) NOT NULL,                 -- move in parcels table, land=>SalePrice
  `parentestate` int(11) NOT NULL default '1',  -- ignore, unused data received by the parser during crawl, no need to store
  PRIMARY KEY  (`regionUUID`,`parcelUUID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `popularplaces` (
  `parcelUUID` char(36) NOT NULL,
  `name` varchar(255) NOT NULL, -- parels: parcelName
  `dwell` float NOT NULL,
  `infoUUID` char(36) NOT NULL,
  `mature` varchar(10) COLLATE utf8_unicode_ci NOT NULL,

  `gatekeeperURL` varchar(255),

  `has_picture` tinyint(1) NOT NULL,        -- derived from parcel=>imageUUID != NULL_KEY
  PRIMARY KEY  (`parcelUUID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `classifieds` (
  `classifieduuid` char(36) NOT NULL,
  `parceluuid` char(36) NOT NULL,
  `simname` varchar(255) NOT NULL, -- actually region->uri "host:port/Region+Name"

  `creatoruuid` char(36) NOT NULL,
  `creationdate` int(20) NOT NULL,
  `expirationdate` int(20) NOT NULL,
  `category` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `parentestate` int(11) NOT NULL,
  `snapshotuuid` char(36) NOT NULL,
  `posglobal` varchar(255) NOT NULL, -- region->globalPos
  `parcelname` varchar(255) NOT NULL,
  `classifiedflags` int(8) NOT NULL,
  `priceforlisting` int(5) NOT NULL,
  PRIMARY KEY  (`classifieduuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `eventid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parcelUUID` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,

  `owneruuid` char(36) NOT NULL,
  `creatoruuid` char(36) NOT NULL,

  `gatekeeperURL` varchar(255),

  `category` int(2) NOT NULL,
  `description` text NOT NULL,
  `dateUTC` int(10) NOT NULL,
  `duration` int(10) NOT NULL,
  `covercharge` tinyint(1) NOT NULL,
  `coveramount` int(10) NOT NULL,
  `simname` varchar(255) NOT NULL,
  `globalPos` varchar(255) NOT NULL,
  `eventflags` int(1) NOT NULL,
  `landingpoint` varchar(35) DEFAULT NULL, -- JOpenSim compatibility
  `mature` enum('true','false') NOT NULL, -- JOpenSim compatibility
  `parcelName` varchar(255) DEFAULT NULL, -- JOpenSim compatibility
  PRIMARY KEY (`eventid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `hostsregister` (
  `host` varchar(255) NOT NULL,
  `port` int(5) NOT NULL,
  `register` int(10) NOT NULL,
  `nextcheck` int(10) NOT NULL,
  `checked` tinyint(1) NOT NULL,
  `failcounter` int(10) NOT NULL,
  `gatekeeperURL` varchar(255),
  PRIMARY KEY (`host`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `objects` (
  `objectuuid` char(36) NOT NULL,
  `parceluuid` char(36) NOT NULL,
  `location` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `regionuuid` char(36) NOT NULL default '',
  `gatekeeperURL` varchar(255),
  PRIMARY KEY  (`objectuuid`,`parceluuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `regions` (
  `regionname` varchar(255) NOT NULL,
  `regionUUID` char(36) NOT NULL,
  `regionhandle` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `owneruuid` char(36) NOT NULL,
  `gatekeeperURL` varchar(255),
  PRIMARY KEY  (`regionUUID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS oshelpers_cache (
  `cache_key` VARCHAR(255) NOT NULL,
  `cache_value` LONGBLOB,
  `cache_expires` int(10) default 0,
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB;
