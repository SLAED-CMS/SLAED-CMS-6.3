# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0.16+ & MariaDB 10.5.2+

CREATE TABLE `{prefix}_master_site` (
  `sid` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `ver` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `chan` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'stable',
  `kseq` INT UNSIGNED NOT NULL DEFAULT 0,
  `php` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `db` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `dbver` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `ext` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `ep` INT UNSIGNED NOT NULL DEFAULT 0,
  `es` INT UNSIGNED NOT NULL DEFAULT 0,
  `failver` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `failstep` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `failed` DATETIME DEFAULT NULL,
  `added` DATETIME NOT NULL,
  `seen` DATETIME NOT NULL,
  PRIMARY KEY (`sid`),
  KEY `seen` (`seen`),
  KEY `ver` (`ver`),
  KEY `failver` (`failver`, `failed`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_master_stat` (
  `day` DATE NOT NULL,
  `chan` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ver` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `php` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `db` VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `sites` INT UNSIGNED NOT NULL DEFAULT 0,
  `nosod` INT UNSIGNED NOT NULL DEFAULT 0,
  `nozip` INT UNSIGNED NOT NULL DEFAULT 0,
  `errs` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `fails` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`, `chan`, `ver`, `php`, `db`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_master_release` (
  `ver` VARCHAR(17) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `state` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'wait',
  `pauseby` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `pfail` INT UNSIGNED NOT NULL DEFAULT 0,
  `pok` INT UNSIGNED NOT NULL DEFAULT 0,
  `share` TINYINT UNSIGNED NOT NULL DEFAULT 100,
  `accepted` DATETIME NOT NULL,
  `changed` DATETIME NOT NULL,
  PRIMARY KEY (`ver`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE `{prefix}_master_note` (
  `id` BIGINT UNSIGNED NOT NULL,
  `sid` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `ver` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `level` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `until` DATETIME DEFAULT NULL,
  `active` BOOLEAN NOT NULL DEFAULT 1,
  `body` BLOB NOT NULL,
  `sig` VARCHAR(88) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `added` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};
