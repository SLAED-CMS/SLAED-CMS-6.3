# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# A SLAED 6.2 site for the update probe of tests/Support/install_probe.php (mode update, prefix old): the CREATE TABLE statements of all 33 tables
# exactly as a real 6.2 site dumped them on 2025-11-25, only the prefix renamed and the counters dropped, and a small invented seed below them
# The seed carries what the 6.3 update has to meet on a real site: a boolean editor and numeric rights of administrators, the module table,
# balances, sections with the highest old id in files, a category that keeps the news type from being created, blocks of removed modules,
# a cached RSS block, a poll with a guest vote, polls of newsletter, and a whois row with its legacy text and status columns

CREATE TABLE `old_admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL DEFAULT '',
  `title` varchar(50) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `pwd` varchar(255) DEFAULT NULL,
  `super` tinyint(1) DEFAULT NULL,
  `editor` tinyint(1) DEFAULT NULL,
  `smail` tinyint(1) DEFAULT NULL,
  `modules` varchar(255) NOT NULL DEFAULT '',
  `lang` varchar(30) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `regdate` datetime NOT NULL DEFAULT current_timestamp(),
  `lastvisit` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `email` (`email`(191)),
  KEY `lastvisit` (`lastvisit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_auto_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sitename` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(100) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `outs` int(10) unsigned NOT NULL DEFAULT 0,
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `added` (`added`),
  KEY `hits` (`hits`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_blocks` (
  `bid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) NOT NULL DEFAULT '',
  `title` varchar(60) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `url` varchar(200) NOT NULL DEFAULT '',
  `bposition` char(1) NOT NULL DEFAULT '',
  `weight` smallint(5) unsigned NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `refresh` int(10) unsigned NOT NULL DEFAULT 0,
  `time` varchar(14) NOT NULL DEFAULT '0',
  `blanguage` varchar(30) NOT NULL DEFAULT '',
  `blockfile` varchar(255) NOT NULL DEFAULT '',
  `view` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expire` varchar(14) NOT NULL DEFAULT '0',
  `action` char(1) NOT NULL DEFAULT '',
  `which` mediumtext NOT NULL,
  PRIMARY KEY (`bid`),
  KEY `title` (`title`),
  KEY `active_position` (`active`,`bposition`),
  KEY `blanguage` (`blanguage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `img` varchar(100) NOT NULL DEFAULT '',
  `language` varchar(30) NOT NULL DEFAULT '',
  `parentid` int(10) unsigned NOT NULL DEFAULT 0,
  `cstatus` tinyint(1) NOT NULL DEFAULT 0,
  `ordern` int(10) unsigned NOT NULL DEFAULT 0,
  `topics` int(10) unsigned NOT NULL DEFAULT 0,
  `posts` int(10) unsigned NOT NULL DEFAULT 0,
  `lpost_id` int(10) unsigned NOT NULL DEFAULT 0,
  `auth_view` varchar(100) NOT NULL DEFAULT '',
  `auth_read` varchar(100) NOT NULL DEFAULT '',
  `auth_post` varchar(100) NOT NULL DEFAULT '',
  `auth_reply` varchar(100) NOT NULL DEFAULT '',
  `auth_edit` varchar(100) NOT NULL DEFAULT '',
  `auth_delete` varchar(100) NOT NULL DEFAULT '',
  `auth_mod` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `parentid` (`parentid`),
  KEY `modul_lang_status` (`modul`,`language`,`cstatus`),
  KEY `ordern` (`ordern`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int(10) unsigned NOT NULL DEFAULT 0,
  `id_product` int(10) unsigned NOT NULL DEFAULT 0,
  `id_partner` int(10) unsigned NOT NULL DEFAULT 0,
  `partner_proz` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `adres` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `website` varchar(255) NOT NULL DEFAULT '',
  `regdate` int(10) unsigned NOT NULL DEFAULT 0,
  `enddate` int(10) unsigned NOT NULL DEFAULT 0,
  `info` varchar(255) NOT NULL DEFAULT '',
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `id_product` (`id_product`),
  KEY `id_partner` (`id_partner`),
  KEY `active` (`active`),
  KEY `email` (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_clients_down` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '',
  `infotext` mediumtext NOT NULL,
  `url` varchar(100) NOT NULL DEFAULT '',
  `num` varchar(10) NOT NULL DEFAULT '',
  `code` varchar(100) NOT NULL DEFAULT '',
  `hits` int(11) NOT NULL DEFAULT 0,
  `prod_id` int(11) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned NOT NULL DEFAULT 0,
  `modul` varchar(60) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `host_name` varchar(45) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `modul_status` (`modul`,`status`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `text` longtext NOT NULL,
  `field` mediumtext NOT NULL,
  `url` varchar(200) NOT NULL DEFAULT '',
  `time` datetime DEFAULT NULL,
  `refresh` int(10) unsigned NOT NULL DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `counter` (`counter`),
  KEY `url` (`url`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_faq` (
  `fid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `catid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `comments` int(10) unsigned NOT NULL DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `ratings` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`fid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `fid` int(10) unsigned NOT NULL DEFAULT 0,
  `modul` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_fid_modul` (`uid`,`fid`,`modul`),
  KEY `uid` (`uid`),
  KEY `fid` (`fid`),
  KEY `modul` (`modul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_files` (
  `lid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` mediumtext NOT NULL,
  `bodytext` mediumtext NOT NULL,
  `url` varchar(100) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `filesize` int(10) unsigned NOT NULL DEFAULT 0,
  `version` varchar(10) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `homepage` varchar(200) NOT NULL DEFAULT '',
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `totalvotes` int(10) unsigned NOT NULL DEFAULT 0,
  `totalcomments` int(10) unsigned NOT NULL DEFAULT 0,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`),
  KEY `counter` (`counter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_forum` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `catid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL DEFAULT '',
  `title` varchar(100) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `field` mediumtext NOT NULL,
  `comments` int(10) unsigned DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `ratings` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_send` varchar(45) NOT NULL DEFAULT '',
  `l_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `l_name` varchar(25) NOT NULL DEFAULT '',
  `l_id` int(10) unsigned NOT NULL DEFAULT 0,
  `l_time` datetime DEFAULT NULL,
  `e_uid` int(10) unsigned NOT NULL DEFAULT 0,
  `e_ip_send` varchar(45) NOT NULL DEFAULT '',
  `e_time` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `catid_status` (`catid`,`status`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `points` int(10) unsigned NOT NULL DEFAULT 0,
  `extra` tinyint(1) NOT NULL DEFAULT 0,
  `rank` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(7) NOT NULL DEFAULT '',
  KEY `id` (`id`),
  KEY `name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_help` (
  `sid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `catid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `aid` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `field` mediumtext NOT NULL,
  `comments` int(10) unsigned DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `ratings` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `pid` (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_links` (
  `lid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` mediumtext NOT NULL,
  `bodytext` mediumtext NOT NULL,
  `url` varchar(100) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `email` varchar(100) NOT NULL DEFAULT '',
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `totalvotes` int(10) unsigned NOT NULL DEFAULT 0,
  `totalcomments` int(10) unsigned NOT NULL DEFAULT 0,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `title` (`title`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_message` (
  `mid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `expire` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `view` tinyint(1) NOT NULL DEFAULT 1,
  `mlanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`),
  KEY `active` (`active`),
  KEY `mlanguage` (`mlanguage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_modules` (
  `mid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `view` tinyint(1) NOT NULL DEFAULT 0,
  `inmenu` tinyint(1) NOT NULL DEFAULT 1,
  `mod_group` int(10) unsigned DEFAULT 0,
  `blocks` tinyint(1) NOT NULL DEFAULT 0,
  `blocks_c` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`mid`),
  KEY `title` (`title`(191)),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_money` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sum` int(11) NOT NULL DEFAULT 0,
  `mail` varchar(255) NOT NULL DEFAULT '',
  `info` mediumtext NOT NULL,
  `com` mediumtext NOT NULL,
  `ip` varchar(15) NOT NULL,
  `agent` varchar(255) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_news` (
  `sid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `catid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `bodytext` mediumtext NOT NULL,
  `field` mediumtext NOT NULL,
  `vote` int(10) unsigned NOT NULL DEFAULT 0,
  `comments` int(10) unsigned DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `ratings` int(10) unsigned NOT NULL DEFAULT 0,
  `associated` mediumtext NOT NULL,
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `fix` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_newsletter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL DEFAULT '',
  `content` mediumtext DEFAULT NULL,
  `mails` longtext DEFAULT NULL,
  `send` int(10) unsigned NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mail` varchar(255) NOT NULL,
  `info` mediumtext NOT NULL,
  `com` mediumtext NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `agent` varchar(255) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_pages` (
  `pid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `catid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `bodytext` longtext NOT NULL,
  `comments` int(10) unsigned NOT NULL DEFAULT 0,
  `counter` int(10) unsigned NOT NULL DEFAULT 0,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(10) unsigned NOT NULL DEFAULT 0,
  `ratings` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `adres` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `website` varchar(255) NOT NULL DEFAULT '',
  `webmoney` varchar(255) NOT NULL DEFAULT '',
  `paypal` varchar(255) NOT NULL DEFAULT '',
  `regdate` int(10) unsigned NOT NULL DEFAULT 0,
  `rest` int(10) unsigned NOT NULL DEFAULT 0,
  `bek` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `active` (`active`),
  KEY `email` (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_privat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uidin` int(10) unsigned NOT NULL DEFAULT 0,
  `uidout` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `date` datetime DEFAULT NULL,
  `ip_sender` varchar(45) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uidin` (`uidin`),
  KEY `uidout` (`uidout`),
  KEY `status` (`status`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cid` int(10) unsigned NOT NULL DEFAULT 0,
  `time` datetime DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `text` mediumtext NOT NULL,
  `bodytext` mediumtext NOT NULL,
  `preis` int(10) unsigned NOT NULL DEFAULT 0,
  `vote` int(10) unsigned NOT NULL DEFAULT 0,
  `assoc` mediumtext NOT NULL,
  `ihome` tinyint(1) NOT NULL DEFAULT 0,
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `com` int(10) unsigned NOT NULL DEFAULT 0,
  `count` int(10) unsigned NOT NULL DEFAULT 0,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `totalvotes` int(10) unsigned NOT NULL DEFAULT 0,
  `fix` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `active` (`active`),
  KEY `ihome` (`ihome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_rating` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mid` int(10) unsigned NOT NULL DEFAULT 0,
  `modul` varchar(60) NOT NULL DEFAULT '',
  `time` varchar(14) NOT NULL DEFAULT '',
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `host` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mid_modul_uid` (`mid`,`modul`,`uid`),
  KEY `mid` (`mid`),
  KEY `modul` (`modul`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_referer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `name` varchar(40) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `referer` varchar(2048) NOT NULL DEFAULT '',
  `link` varchar(2048) NOT NULL DEFAULT '',
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `lid` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `date` (`date`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_search` (
  `sl_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sl_word` varchar(255) NOT NULL,
  `sl_modul` varchar(50) NOT NULL,
  `sl_time` datetime NOT NULL DEFAULT current_timestamp(),
  `sl_score` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`sl_id`),
  KEY `sl_modul` (`sl_modul`),
  KEY `sl_word` (`sl_word`(191)),
  KEY `sl_time` (`sl_time`),
  KEY `sl_word_modul` (`sl_word`(191),`sl_modul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uname` varchar(40) NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  `host_addr` varchar(45) NOT NULL DEFAULT '',
  `guest` tinyint(1) NOT NULL DEFAULT 0,
  `module` varchar(25) NOT NULL DEFAULT '',
  `url` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `uname` (`uname`),
  KEY `time` (`time`),
  KEY `host_addr` (`host_addr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) NOT NULL,
  `user_rank` varchar(25) NOT NULL,
  `user_email` varchar(255) NOT NULL DEFAULT '',
  `user_website` varchar(255) NOT NULL DEFAULT '',
  `user_avatar` varchar(255) NOT NULL DEFAULT '',
  `user_regdate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_occ` varchar(100) DEFAULT NULL,
  `user_from` varchar(100) DEFAULT NULL,
  `user_interests` varchar(150) NOT NULL DEFAULT '',
  `user_sig` varchar(255) DEFAULT NULL,
  `user_viewemail` tinyint(1) DEFAULT NULL,
  `user_password` varchar(32) NOT NULL,
  `user_storynum` tinyint(4) NOT NULL DEFAULT 10,
  `user_blockon` tinyint(1) NOT NULL DEFAULT 0,
  `user_block` mediumtext NOT NULL,
  `user_theme` varchar(255) NOT NULL DEFAULT '',
  `user_newsletter` int(1) NOT NULL DEFAULT 0,
  `user_fsmail` int(1) NOT NULL DEFAULT 1,
  `user_psmail` int(1) NOT NULL DEFAULT 1,
  `user_lastvisit` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_lang` varchar(255) NOT NULL DEFAULT 'russian',
  `user_points` int(10) DEFAULT 0,
  `user_last_ip` varchar(15) NOT NULL,
  `user_warnings` mediumtext NOT NULL,
  `user_acess` int(1) NOT NULL DEFAULT 0,
  `user_group` int(1) NOT NULL DEFAULT 0,
  `user_birthday` date DEFAULT NULL,
  `user_gender` int(1) NOT NULL DEFAULT 0,
  `user_votes` int(11) NOT NULL DEFAULT 0,
  `user_totalvotes` int(11) NOT NULL DEFAULT 0,
  `user_field` mediumtext NOT NULL,
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `user_network` varchar(255) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_users_temp` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) NOT NULL DEFAULT '',
  `user_email` varchar(255) NOT NULL DEFAULT '',
  `user_password` varchar(255) NOT NULL,
  `user_regdate` datetime NOT NULL DEFAULT current_timestamp(),
  `check_num` varchar(50) NOT NULL DEFAULT '',
  `time` varchar(14) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`),
  KEY `check_num` (`check_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_voting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `modul` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `questions` mediumtext NOT NULL,
  `answer` mediumtext NOT NULL,
  `date` datetime DEFAULT NULL,
  `enddate` datetime DEFAULT NULL,
  `multi` tinyint(1) NOT NULL DEFAULT 0,
  `comments` int(10) unsigned NOT NULL DEFAULT 0,
  `language` varchar(30) NOT NULL DEFAULT '',
  `acomm` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `typ` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `status` (`status`),
  KEY `language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `old_whois` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(25) NOT NULL DEFAULT '',
  `ip` varchar(60) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `domain` varchar(255) NOT NULL DEFAULT '',
  `host` varchar(255) NOT NULL DEFAULT '',
  `dc` varchar(255) NOT NULL DEFAULT '',
  `hometext` mediumtext DEFAULT NULL,
  `st_domain` int(1) NOT NULL DEFAULT 0,
  `st_host` int(1) NOT NULL DEFAULT 0,
  `st_dc` int(1) NOT NULL DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
