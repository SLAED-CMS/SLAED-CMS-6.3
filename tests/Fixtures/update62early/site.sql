# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# An earlier SLAED 6.2 site for the update probe of tests/Support/install_probe.php (mode update, prefix old): the CREATE TABLE statements of the
# same 33 tables exactly as a real 6.2 site dumped them on 2025-11-04, only the prefix renamed, the counters dropped and MyISAM converted as the
# preflight asks; its schema predates the one of tests/Fixtures/update62: signed integers, ip columns of 15 characters, nullable columns,
# zero dates as defaults and a session table of its own widths. The seed is the one of tests/Fixtures/update62 plus what only such a site holds:
# a negative point balance, an account without an address, a comment without an author and an online row with a long module name
# 6.2 wrote in the relaxed mode of its time, where a column without a default takes the implicit one, so the seed is loaded in that mode

CREATE TABLE `old_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `title` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `url` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `email` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `pwd` varchar(32) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `super` tinyint(1) DEFAULT NULL,
  `editor` tinyint(1) DEFAULT NULL,
  `smail` tinyint(1) DEFAULT NULL,
  `modules` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `lang` varchar(30) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `ip` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `regdate` datetime NOT NULL,
  `lastvisit` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_auto_links` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sitename` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `description` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `link` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `mail` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 0,
  `outs` int(11) NOT NULL DEFAULT 0,
  `added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_blocks` (
  `bid` int(10) NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `title` varchar(60) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `content` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(200) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `bposition` char(1) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `weight` int(10) NOT NULL DEFAULT 1,
  `active` int(1) NOT NULL DEFAULT 1,
  `refresh` int(10) NOT NULL DEFAULT 0,
  `time` varchar(14) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '0',
  `blanguage` varchar(30) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `blockfile` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `view` int(1) NOT NULL DEFAULT 0,
  `expire` varchar(14) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '0',
  `action` char(1) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `which` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  PRIMARY KEY (`bid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `description` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `img` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `language` varchar(30) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `parentid` int(11) NOT NULL DEFAULT 0,
  `cstatus` int(1) NOT NULL DEFAULT 0,
  `ordern` int(11) NOT NULL DEFAULT 0,
  `topics` int(11) NOT NULL DEFAULT 0,
  `posts` int(11) NOT NULL DEFAULT 0,
  `lpost_id` int(11) NOT NULL DEFAULT 0,
  `auth_view` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_read` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_post` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_reply` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_edit` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_delete` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `auth_mod` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `parentid` (`parentid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `id_product` int(11) NOT NULL DEFAULT 0,
  `id_partner` int(11) NOT NULL DEFAULT 0,
  `partner_proz` int(3) NOT NULL DEFAULT 0,
  `name` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `adres` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `phone` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `email` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `website` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `regdate` int(10) NOT NULL DEFAULT 0,
  `enddate` int(10) NOT NULL DEFAULT 0,
  `info` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_clients_down` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `infotext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `num` varchar(10) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `code` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `hits` int(11) NOT NULL DEFAULT 0,
  `prod_id` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(60) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `uid` int(11) DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `host_name` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `comment` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `text` longtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `field` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(200) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `time` datetime DEFAULT NULL,
  `refresh` int(10) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_faq` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `comments` int(11) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0,
  `fid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `fid` (`fid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_files` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `description` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `bodytext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `filesize` int(11) NOT NULL DEFAULT 0,
  `version` varchar(10) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `email` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `homepage` varchar(200) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcomments` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_forum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `field` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_send` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `l_uid` int(11) NOT NULL DEFAULT 0,
  `l_name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `l_id` int(11) NOT NULL DEFAULT 0,
  `l_time` datetime DEFAULT NULL,
  `e_uid` int(11) NOT NULL DEFAULT 0,
  `e_ip_send` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `e_time` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_groups` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `description` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `points` int(10) NOT NULL DEFAULT 0,
  `extra` int(1) NOT NULL DEFAULT 0,
  `rank` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `color` varchar(7) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '0',
  KEY `id` (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_help` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `aid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `field` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_links` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `description` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `bodytext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `email` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcomments` int(11) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_message` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `content` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `expire` int(7) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 1,
  `view` int(1) NOT NULL DEFAULT 1,
  `mlanguage` varchar(30) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_modules` (
  `mid` int(10) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `active` int(1) NOT NULL DEFAULT 0,
  `view` int(1) NOT NULL DEFAULT 0,
  `inmenu` tinyint(1) NOT NULL DEFAULT 1,
  `mod_group` int(10) DEFAULT 0,
  `blocks` int(1) NOT NULL DEFAULT 0,
  `blocks_c` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`mid`),
  KEY `title` (`title`(250))
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_money` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sum` int(11) NOT NULL DEFAULT 0,
  `mail` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `info` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `com` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `ip` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `agent` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_news` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `bodytext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `field` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `vote` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `associated` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `fix` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `content` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `mails` longtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `send` int(10) NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mail` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `info` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `com` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `ip` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `agent` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `date` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_pages` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `bodytext` longtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `comments` int(11) NOT NULL DEFAULT 0,
  `counter` int(11) NOT NULL DEFAULT 0,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_partners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `adres` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `phone` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `email` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `website` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `webmoney` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `paypal` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `regdate` int(10) NOT NULL DEFAULT 0,
  `rest` int(10) NOT NULL DEFAULT 0,
  `bek` int(10) NOT NULL DEFAULT 0,
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_privat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uidin` int(11) NOT NULL DEFAULT 0,
  `uidout` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `content` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `date` datetime DEFAULT NULL,
  `ip_sender` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `title` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `text` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `bodytext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `preis` int(10) NOT NULL DEFAULT 0,
  `vote` int(1) NOT NULL DEFAULT 0,
  `assoc` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `ihome` int(1) NOT NULL DEFAULT 0,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `com` int(11) NOT NULL DEFAULT 0,
  `count` int(11) NOT NULL DEFAULT 0,
  `votes` int(11) NOT NULL DEFAULT 0,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `fix` int(1) NOT NULL DEFAULT 0,
  `active` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_rating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mid` int(11) NOT NULL DEFAULT 0,
  `modul` varchar(60) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `time` varchar(14) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT 0,
  `host` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mid` (`mid`),
  KEY `modul` (`modul`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_referer` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `ip` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `referer` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `link` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `lid` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_search` (
  `sl_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sl_word` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `sl_modul` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `sl_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sl_score` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sl_id`),
  KEY `sl_word` (`sl_word`),
  KEY `sl_modul` (`sl_modul`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_session` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uname` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `time` int(10) NOT NULL,
  `host_addr` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `guest` int(1) NOT NULL DEFAULT 0,
  `module` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `url` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uname` (`uname`),
  KEY `time` (`time`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_rank` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_email` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_website` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_avatar` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_regdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_occ` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `user_from` varchar(100) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `user_interests` varchar(150) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_sig` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `user_viewemail` int(1) DEFAULT NULL,
  `user_password` varchar(32) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_storynum` tinyint(4) NOT NULL DEFAULT 10,
  `user_blockon` tinyint(1) NOT NULL DEFAULT 0,
  `user_block` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_theme` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_newsletter` int(10) NOT NULL DEFAULT 0,
  `user_fsmail` int(10) NOT NULL DEFAULT 1,
  `user_psmail` int(10) NOT NULL DEFAULT 1,
  `user_lastvisit` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_lang` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT 'russian',
  `user_points` int(10) DEFAULT 0,
  `user_last_ip` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `user_warnings` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_acess` int(10) NOT NULL DEFAULT 0,
  `user_group` int(10) NOT NULL DEFAULT 0,
  `user_birthday` date DEFAULT NULL,
  `user_gender` int(10) NOT NULL DEFAULT 0,
  `user_votes` int(11) NOT NULL DEFAULT 0,
  `user_totalvotes` int(11) NOT NULL DEFAULT 0,
  `user_field` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_agent` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_network` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_users_temp` (
  `user_id` int(10) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_email` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `user_password` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `user_regdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `check_num` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `time` varchar(14) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_voting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `title` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `questions` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `answer` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `date` datetime DEFAULT NULL,
  `enddate` datetime DEFAULT NULL,
  `multi` int(1) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `language` varchar(30) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `acomm` int(1) NOT NULL DEFAULT 0,
  `ip` varchar(15) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL,
  `typ` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

CREATE TABLE `old_whois` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `ip` varchar(60) /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `domain` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `host` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `dc` varchar(255) /*!40101 COLLATE utf8mb4_unicode_ci */ NOT NULL DEFAULT '',
  `hometext` mediumtext /*!40101 COLLATE utf8mb4_unicode_ci */ DEFAULT NULL,
  `st_domain` int(1) NOT NULL DEFAULT 0,
  `st_host` int(1) NOT NULL DEFAULT 0,
  `st_dc` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB /*!40101 DEFAULT CHARSET=utf8mb4 */ /*!40101 COLLATE=utf8mb4_unicode_ci */;

SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

# Seed: invented rows, no data of a real site

INSERT INTO `old_admins` (`id`, `name`, `title`, `url`, `email`, `pwd`, `super`, `editor`, `smail`, `modules`, `lang`, `ip`, `regdate`, `lastvisit`) VALUES
(1, 'admin', 'Administrator', '', 'admin@site62.test', 'c4ca4238a0b923820dcc509a6f75849b', 1, 0, 1, '', '', '127.0.0.1', '2010-01-01 10:00:00', '2025-11-20 10:00:00'),
(2, 'editor', 'Editor', '', 'editor@site62.test', 'c81e728d9d4c2f636f067f89cc14862c', 0, NULL, 0, '10,104', '', '127.0.0.1', '2012-01-01 10:00:00', '2025-11-20 10:00:00');

INSERT INTO `old_modules` (`mid`, `title`, `active`, `view`, `inmenu`, `mod_group`, `blocks`, `blocks_c`) VALUES
(10, 'news', 1, 0, 1, 0, 0, 0), (23, 'voting', 1, 0, 1, 0, 0, 0), (26, 'faq', 1, 0, 1, 0, 0, 0), (36, 'files', 1, 0, 1, 0, 0, 0),
(57, 'account', 1, 0, 1, 0, 2, 0), (81, 'shop', 1, 0, 1, 0, 0, 0), (104, 'forum', 1, 0, 1, 0, 2, 0), (109, 'auto_links', 0, 0, 1, 0, 0, 0),
(110, 'jokes', 0, 0, 1, 0, 0, 0);

INSERT INTO `old_users` (`user_id`, `user_name`, `user_rank`, `user_email`, `user_avatar`, `user_regdate`, `user_password`, `user_block`, `user_lastvisit`,
  `user_points`, `user_last_ip`, `user_warnings`, `user_field`, `user_network`) VALUES
(1, 'alpha', '', 'alpha@site62.test', 'default/05.gif', '2015-03-01 12:00:00', 'c4ca4238a0b923820dcc509a6f75849b', '', '2025-11-01 12:00:00', 10, '10.0.0.1', '', '', ''),
(2, 'beta', '', 'beta@site62.test', '', '2016-03-01 12:00:00', 'c81e728d9d4c2f636f067f89cc14862c', '', '2025-11-02 12:00:00', 0, '10.0.0.2', '', '', ''),
(3, 'gamma', '', 'gamma@site62.test', 'default/99.gif', '2017-03-01 12:00:00', 'eccbc87e4b5ce2fe28308fd9f2a7baf3', '', '2025-11-03 12:00:00', 25, '10.0.0.3', '', '', '');

INSERT INTO `old_categories` (`id`, `modul`, `title`, `description`, `cstatus`) VALUES
(1, 'news', 'General', 'News of the site', 1);

INSERT INTO `old_news` (`sid`, `catid`, `uid`, `name`, `title`, `time`, `hometext`, `bodytext`, `field`, `associated`, `ihome`, `status`) VALUES
(1, 1, 1, 'alpha', 'First news', '2020-05-01 10:00:00', 'Intro of the first news', '', '', '', 1, 1),
(2, 1, 1, 'alpha', 'Second news', '2021-05-01 10:00:00', 'Intro of the second news', 'Body of the second news', '', '', 1, 1);

INSERT INTO `old_files` (`lid`, `cid`, `uid`, `name`, `title`, `description`, `bodytext`, `url`, `date`, `status`) VALUES
(300, 0, 1, 'alpha', 'A file', 'Description of the file', '', 'https://site62.test/file.zip', '2019-05-01 10:00:00', 1);

INSERT INTO `old_blocks` (`bid`, `title`, `content`, `url`, `bposition`, `weight`, `active`, `refresh`, `time`, `blockfile`, `which`) VALUES
(1, 'News', '', '', 'l', 1, 1, 0, '0', 'block-news.php', 'all'),
(2, 'Poll', '', '', 'r', 1, 1, 0, '0', 'block-voting.php', 'all'),
(3, 'Feed', '<ul><li>cached item</li></ul>', 'https://feed.site62.test/rss.xml', 'r', 2, 1, 3600, '1700000000', '', 'all');

INSERT INTO `old_voting` (`id`, `modul`, `title`, `questions`, `answer`, `date`, `multi`, `language`, `typ`, `status`) VALUES
(1, '', 'Which one', 'Yes|No', '1|0', '2025-01-01 10:00:00', 0, '', 0, 1);

INSERT INTO `old_rating` (`id`, `mid`, `modul`, `time`, `uid`, `host`) VALUES
(1, 1, 'voting', '1700000000', 0, '10.0.0.9'),
(2, 1, 'account', '1700000000', 2, '10.0.0.2'),
(3, 2, 'news', '1700000000', 1, '10.0.0.1'),
(4, 1, 'voting', '1700000100', 3, '10.0.0.9');

INSERT INTO `old_newsletter` (`id`, `title`, `content`, `send`, `time`) VALUES
(1, 'Letter one', 'Text one', 0, '2024-01-01 10:00:00'),
(2, 'Letter two', 'Text two', 0, '2024-02-01 10:00:00');

INSERT INTO `old_whois` (`id`, `uid`, `name`, `ip`, `time`, `domain`, `host`, `dc`, `hometext`, `st_domain`, `st_host`, `st_dc`, `status`) VALUES
(1, 1, 'alpha', NULL, '2020-01-01 10:00:00', 'site62.test', 'host.test', 'dc.test', 'Legacy whois text', 1, 0, 1, 1);

# Seed of the earlier schema only
INSERT INTO `old_users` (`user_id`, `user_name`, `user_rank`, `user_email`, `user_avatar`, `user_regdate`, `user_password`, `user_block`, `user_lastvisit`,
  `user_points`, `user_last_ip`, `user_warnings`, `user_field`, `user_network`) VALUES
(4, 'delta', '', 'delta@site62.test', '', '2018-03-01 12:00:00', 'a87ff679a2f3e71d9181a67b7542122c', '', '2025-11-04 12:00:00', -5, NULL, '', '', '');

INSERT INTO `old_comment` (`id`, `cid`, `modul`, `date`, `uid`, `name`, `host_name`, `comment`, `status`) VALUES
(1, 1, 'voting', '2025-01-02 10:00:00', NULL, 'guest', '10.0.0.9', 'A guest comment on the poll', 1);

INSERT INTO `old_session` (`id`, `uname`, `time`, `host_addr`, `guest`, `module`, `url`) VALUES
(1, '10.0.0.9', 1700000000, '10.0.0.9', 1, 'a-module-name-longer-than-twenty-five', '/index.php');
