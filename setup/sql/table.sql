# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0.16+ & MariaDB 10.2.1+

CREATE TABLE `{prefix}_admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(25) NOT NULL,
  `title` VARCHAR(50) DEFAULT NULL,
  `url` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `super` BOOLEAN DEFAULT NULL,
  `editor` VARCHAR(32) NOT NULL DEFAULT 'plain',
  `smail` BOOLEAN DEFAULT NULL,
  `modules` VARCHAR(255) NOT NULL DEFAULT '',
  `lang` VARCHAR(30) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastvis` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `email` (`email`(191)),
  KEY `lastvis` (`lastvis`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_auto_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `intro` TEXT NOT NULL,
  `url` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `outs` INT UNSIGNED NOT NULL DEFAULT 0,
  `added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `added` (`added`),
  KEY `hits` (`hits`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bkey` VARCHAR(15) NOT NULL DEFAULT '',
  `title` VARCHAR(60) NOT NULL,
  `content` TEXT NOT NULL,
  `url` VARCHAR(200) NOT NULL DEFAULT '',
  `bpos` CHAR(1) NOT NULL DEFAULT '',
  `weight` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status` BOOLEAN NOT NULL DEFAULT 1,
  `refresh` INT UNSIGNED NOT NULL DEFAULT 0,
  `time` VARCHAR(14) NOT NULL DEFAULT '0',
  `lang` VARCHAR(30) NOT NULL DEFAULT '',
  `bfile` VARCHAR(255) NOT NULL DEFAULT '',
  `view` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expire` VARCHAR(14) NOT NULL DEFAULT '0',
  `action` CHAR(1) NOT NULL DEFAULT '',
  `which` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `status_position` (`status`, `bpos`),
  KEY `lang` (`lang`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modul` VARCHAR(50) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `intro` TEXT NOT NULL,
  `img` VARCHAR(100) NOT NULL DEFAULT '',
  `lang` VARCHAR(30) NOT NULL DEFAULT '',
  `parent` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  `ordern` INT UNSIGNED NOT NULL DEFAULT 0,
  `topics` INT UNSIGNED NOT NULL DEFAULT 0,
  `posts` INT UNSIGNED NOT NULL DEFAULT 0,
  `lpost` INT UNSIGNED NOT NULL DEFAULT 0,
  `pview` VARCHAR(100) NOT NULL DEFAULT '',
  `pread` VARCHAR(100) NOT NULL DEFAULT '',
  `ppost` VARCHAR(100) NOT NULL DEFAULT '',
  `preply` VARCHAR(100) NOT NULL DEFAULT '',
  `pedit` VARCHAR(100) NOT NULL DEFAULT '',
  `pdelete` VARCHAR(100) NOT NULL DEFAULT '',
  `pmod` VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `parent` (`parent`),
  KEY `modul_lang_status` (`modul`, `lang`, `status`),
  KEY `ordern` (`ordern`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_clients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `prod` INT UNSIGNED NOT NULL DEFAULT 0,
  `part` INT UNSIGNED NOT NULL DEFAULT 0,
  `proz` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL,
  `addr` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255) NOT NULL DEFAULT '',
  `regdate` INT UNSIGNED NOT NULL DEFAULT 0,
  `enddate` INT UNSIGNED NOT NULL DEFAULT 0,
  `info` VARCHAR(255) NOT NULL DEFAULT '',
  `status` BOOLEAN DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `prod` (`prod`),
  KEY `part` (`part`),
  KEY `status` (`status`),
  KEY `email` (`email`(191))
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_clients_down` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `url` VARCHAR(100) NOT NULL DEFAULT '',
  `num` VARCHAR(10) NOT NULL DEFAULT '',
  `code` VARCHAR(100) NOT NULL DEFAULT '',
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `status` (`status`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_comment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `modul` VARCHAR(60) NOT NULL,
  `time` DATETIME NOT NULL,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL,
  `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NOT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  `edited` DATETIME DEFAULT NULL,
  `deleted` DATETIME DEFAULT NULL,
  `reqkey` BINARY(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reqkey` (`reqkey`),
  KEY `uid` (`uid`),
  KEY `time` (`time`),
  KEY `modul_cid_status_deleted` (`modul`, `cid`, `status`, `deleted`, `time`, `id`),
  KEY `modul_cid_deleted` (`modul`, `cid`, `deleted`, `time`, `id`),
  KEY `status_deleted_time` (`status`, `deleted`, `time`, `id`),
  KEY `modul_cid_pid_time` (`modul`, `cid`, `pid`, `time`, `id`),
  KEY `ip_time` (`ip`, `time`, `id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_favorites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `fid` INT UNSIGNED NOT NULL DEFAULT 0,
  `modul` VARCHAR(50) NOT NULL,
  `time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `fid` (`fid`),
  KEY `modul` (`modul`),
  UNIQUE KEY `uid_fid_modul` (`uid`, `fid`, `modul`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_forum` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `time` DATETIME DEFAULT NULL,
  `body` MEDIUMTEXT,
  `field` MEDIUMTEXT NOT NULL,
  `comments` INT UNSIGNED DEFAULT 0,
  `counter` INT UNSIGNED NOT NULL DEFAULT 0,
  `score` INT UNSIGNED NOT NULL DEFAULT 0,
  `ratings` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `luid` INT UNSIGNED NOT NULL DEFAULT 0,
  `lname` VARCHAR(25) NOT NULL DEFAULT '',
  `lpost` INT UNSIGNED NOT NULL DEFAULT 0,
  `ltime` DATETIME DEFAULT NULL,
  `euid` INT UNSIGNED NOT NULL DEFAULT 0,
  `eip` VARCHAR(45) NOT NULL DEFAULT '',
  `etime` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`),
  KEY `cid` (`cid`),
  KEY `counter` (`counter`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `cid_status` (`cid`, `status`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `intro` TEXT NOT NULL,
  `points` INT UNSIGNED NOT NULL DEFAULT 0,
  `extra` BOOLEAN NOT NULL DEFAULT 0,
  `rank` VARCHAR(255) NOT NULL DEFAULT '',
  `color` VARCHAR(7) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`(191))
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_mail` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(20) NOT NULL DEFAULT '',
  `sender` VARCHAR(100) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NOT NULL,
  `ref` INT UNSIGNED NOT NULL DEFAULT 0,
  `prio` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ntime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tries` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `camp` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `hold` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked` DATETIME DEFAULT NULL,
  `lockid` CHAR(32) NOT NULL DEFAULT '',
  `phase` VARCHAR(10) NOT NULL DEFAULT '',
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `error` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `hold_status_prio_ntime` (`hold`, `status`, `prio`, `ntime`, `id`),
  KEY `kind_status_time` (`kind`, `status`, `time`),
  KEY `kind_ref_status` (`kind`, `ref`, `status`),
  KEY `lockid` (`lockid`),
  KEY `locked` (`locked`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_maildead` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `fails` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `phase` VARCHAR(10) NOT NULL DEFAULT '',
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_message` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `expire` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` BOOLEAN NOT NULL DEFAULT 1,
  `view` BOOLEAN NOT NULL DEFAULT 1,
  `lang` VARCHAR(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `lang` (`lang`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_money` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sum` INT UNSIGNED NOT NULL DEFAULT 0,
  `email` VARCHAR(255) NOT NULL,
  `intro` TEXT NOT NULL,
  `note` MEDIUMTEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `email` (`email`(191)),
  KEY `status` (`status`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_newsletter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(50) NOT NULL,
  `body` MEDIUMTEXT,
  `send` INT UNSIGNED NOT NULL DEFAULT 0,
  `time` DATETIME DEFAULT NULL,
  `endtime` DATETIME DEFAULT NULL,
  `fails` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `audit` VARCHAR(100) NOT NULL DEFAULT '',
  `apar` VARCHAR(100) NOT NULL DEFAULT '',
  `cursor` INT UNSIGNED NOT NULL DEFAULT 0,
  `expect` INT UNSIGNED NOT NULL DEFAULT 0,
  `total` INT UNSIGNED NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_oauth_temp` (
  `token` CHAR(64) NOT NULL,
  `kind` VARCHAR(10) NOT NULL DEFAULT '',
  `provider` VARCHAR(32) NOT NULL DEFAULT '',
  `nonce` CHAR(64) NOT NULL DEFAULT '',
  `verifier` VARCHAR(128) NOT NULL DEFAULT '',
  `uid` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  `uname` VARCHAR(128) NOT NULL DEFAULT '',
  `redirect` VARCHAR(512) NOT NULL DEFAULT '',
  `time` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`token`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_order` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `email` VARCHAR(255) NOT NULL,
  `info` MEDIUMTEXT NOT NULL,
  `note` MEDIUMTEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_partners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL,
  `addr` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255) NOT NULL DEFAULT '',
  `webmoney` VARCHAR(255) NOT NULL DEFAULT '',
  `paypal` VARCHAR(255) NOT NULL DEFAULT '',
  `regdate` INT UNSIGNED NOT NULL DEFAULT 0,
  `rest` INT UNSIGNED NOT NULL DEFAULT 0,
  `bek` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` BOOLEAN DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `status` (`status`),
  KEY `email` (`email`(191))
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_points` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL,
  `aid` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL DEFAULT 0,
  `source` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `points` INT NOT NULL,
  `rid` BIGINT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event` (`uid`, `action`, `scope`, `source`),
  UNIQUE KEY `rid` (`rid`),
  KEY `uid_created` (`uid`, `created`),
  KEY `uid_action_created` (`uid`, `action`, `created`),
  KEY `action_created` (`action`, `created`),
  KEY `created` (`created`),
  KEY `target` (`scope`, `mid`),
  CONSTRAINT `{prefix}_fk_points_rid` FOREIGN KEY (`rid`) REFERENCES `{prefix}_points` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `{prefix}_chk_points_uid` CHECK (`uid` > 0)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_privat` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uidin` INT UNSIGNED NOT NULL DEFAULT 0,
  `uidout` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(100) NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `time` DATETIME NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `viewed` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `saved` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `delin` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `delout` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `in_box` (`uidin`, `delin`, `saved`, `time`),
  KEY `in_new` (`uidin`, `delin`, `viewed`),
  KEY `out_box` (`uidout`, `delout`, `time`),
  KEY `out_new` (`uidout`, `delout`, `viewed`),
  KEY `flood` (`uidout`, `time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `time` DATETIME DEFAULT NULL,
  `title` VARCHAR(100) NOT NULL,
  `intro` TEXT NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `price` INT UNSIGNED NOT NULL DEFAULT 0,
  `vote` INT UNSIGNED NOT NULL DEFAULT 0,
  `assoc` TEXT NOT NULL,
  `ihome` BOOLEAN NOT NULL DEFAULT 0,
  `acomm` BOOLEAN NOT NULL DEFAULT 0,
  `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  `counter` INT UNSIGNED NOT NULL DEFAULT 0,
  `votes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tvotes` INT UNSIGNED NOT NULL DEFAULT 0,
  `fix` BOOLEAN NOT NULL DEFAULT 0,
  `status` BOOLEAN DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cid` (`cid`),
  KEY `status` (`status`),
  KEY `ihome` (`ihome`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_rating` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mid` INT UNSIGNED NOT NULL DEFAULT 0,
  `modul` VARCHAR(50) NOT NULL,
  `time` VARCHAR(14) NOT NULL,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `mid` (`mid`),
  KEY `modul` (`modul`),
  KEY `uid` (`uid`),
  UNIQUE KEY `mid_modul_ip` (`mid`, `modul`, `ip`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_rating_actors` (
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `actor` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `last` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`scope`, `mid`, `actor`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_rating_targets` (
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `base` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `votes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`scope`, `mid`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_rating_votes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `actor` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `value` TINYINT UNSIGNED NOT NULL,
  `request` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created` BIGINT UNSIGNED NOT NULL,
  `annulled` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `aid` INT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `request` (`scope`, `mid`, `actor`, `request`),
  KEY `target` (`scope`, `mid`, `id`),
  KEY `scope` (`scope`, `id`),
  CONSTRAINT `{prefix}_chk_rating_votes_value` CHECK (`value` BETWEEN 1 AND 5)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_referer` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL,
  `name` VARCHAR(40) NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `referer` VARCHAR(2048) NOT NULL DEFAULT '',
  `url` VARCHAR(2048) NOT NULL DEFAULT '',
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lid` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `time` (`time`),
  KEY `ip` (`ip`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_search` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `word` VARCHAR(255) NOT NULL,
  `modul` VARCHAR(50) NOT NULL,
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `score` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `word` (`word`(191)),
  KEY `modul` (`modul`),
  KEY `time` (`time`),
  KEY `word_modul` (`word`(191), `modul`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_session` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uname` VARCHAR(40) NOT NULL,
  `time` BIGINT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `guest` BOOLEAN NOT NULL DEFAULT 0,
  `modul` VARCHAR(25) NOT NULL DEFAULT '',
  `url` VARCHAR(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uname` (`uname`),
  KEY `time` (`time`),
  KEY `ip` (`ip`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_user_oauth` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `provider` VARCHAR(32) NOT NULL DEFAULT '',
  `puid` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL DEFAULT '',
  `linked` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `lastlog` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_puid` (`provider`, `puid`(191)),
  UNIQUE KEY `uid_provider` (`uid`, `provider`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(25) NOT NULL,
  `rank` VARCHAR(25) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `website` VARCHAR(255) NOT NULL DEFAULT '',
  `avatar` VARCHAR(255) NOT NULL DEFAULT '',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `occ` VARCHAR(100) DEFAULT NULL,
  `origin` VARCHAR(100) DEFAULT NULL,
  `interest` VARCHAR(150) NOT NULL DEFAULT '',
  `sig` TEXT,
  `viewmail` BOOLEAN DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `storynum` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `blockon` BOOLEAN NOT NULL DEFAULT 0,
  `block` TEXT NOT NULL,
  `theme` VARCHAR(255) NOT NULL DEFAULT '',
  `newslet` BOOLEAN NOT NULL DEFAULT 1,
  `fsmail` BOOLEAN NOT NULL DEFAULT 1,
  `psmail` BOOLEAN NOT NULL DEFAULT 1,
  `lastvis` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lang` VARCHAR(255) NOT NULL DEFAULT 'russian',
  `points` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `warnings` TEXT NOT NULL,
  `access` BOOLEAN NOT NULL DEFAULT 0,
  `grp` INT NOT NULL DEFAULT 0,
  `birthday` DATE DEFAULT NULL,
  `gender` BOOLEAN NOT NULL DEFAULT 0,
  `votes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tvotes` INT UNSIGNED NOT NULL DEFAULT 0,
  `field` MEDIUMTEXT NOT NULL,
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `email` (`email`(191)),
  KEY `grp` (`grp`),
  KEY `points` (`points`),
  KEY `lastvis` (`lastvis`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_users_temp` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(25) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `code` VARCHAR(50) NOT NULL,
  `time` VARCHAR(14) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `code` (`code`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_voting` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modul` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `time` DATETIME DEFAULT NULL,
  `enddate` DATETIME DEFAULT NULL,
  `multi` BOOLEAN NOT NULL DEFAULT 0,
  `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  `lang` VARCHAR(30) NOT NULL DEFAULT '',
  `acomm` BOOLEAN NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `typ` BOOLEAN NOT NULL DEFAULT 0,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `modul` (`modul`),
  KEY `status` (`status`),
  KEY `lang` (`lang`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_whois` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `domain` VARCHAR(255) NOT NULL DEFAULT '',
  `host` VARCHAR(255) NOT NULL DEFAULT '',
  `dc` VARCHAR(255) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT,
  `sdomain` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `shost`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `sdc`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};
