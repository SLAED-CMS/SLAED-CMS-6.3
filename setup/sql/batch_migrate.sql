# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10.5+
#
# batch_migrate.sql — idempotent column & index migration for legacy SLAED DBs
#
# Purpose:
# - migrate a live legacy database to the normalized column names used by current code
# - survive partial/manual refactors without crashing on already-renamed objects
# - skip missing tables silently
#
# Usage:
# 1. Back up the database
# 2. Replace {prefix} with your real table prefix
# 3. Run once; re-run is safe for rename/index batches below
#
# Important:
# - this script covers column/index migration first
# - then it performs minimal table-level reconciliation required by current code
# - legacy source tables are not dropped automatically

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS copymoney;

DELIMITER $$

CREATE PROCEDURE rencol(IN ptab VARCHAR(128), IN pold VARCHAR(128), IN pnew VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cold INT DEFAULT 0;
    DECLARE cnew INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*)
          INTO cold
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pold;

        SELECT COUNT(*)
          INTO cnew
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pnew;

        IF cold > 0 AND cnew = 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'RENAME COLUMN `', pold, '` TO `', pnew, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE renidx(IN ptab VARCHAR(128), IN pold VARCHAR(128), IN pnew VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cold INT DEFAULT 0;
    DECLARE cnew INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO cold
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pold;

        SELECT COUNT(DISTINCT index_name)
          INTO cnew
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pnew;

        IF cold > 0 AND cnew = 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'RENAME INDEX `', pold, '` TO `', pnew, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE delidx(IN ptab VARCHAR(128), IN pidx VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cidx INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO cidx
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pidx;

        IF cidx > 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'DROP INDEX `', pidx, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE addidx(IN ptab VARCHAR(128), IN pidx VARCHAR(128), IN pexp TEXT, IN puni TINYINT)
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cidx INT DEFAULT 0;
    DECLARE kind VARCHAR(16) DEFAULT '';

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO cidx
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pidx;

        IF cidx = 0 THEN
            SET kind = IF(puni = 1, 'ADD UNIQUE KEY', 'ADD KEY');
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                kind, ' `', pidx, '` (', pexp, ')'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE copymoney(IN pmoney VARCHAR(128), IN porder VARCHAR(128))
BEGIN
    DECLARE cmoney INT DEFAULT 0;
    DECLARE corder INT DEFAULT 0;
    DECLARE smoney BIGINT DEFAULT 0;
    DECLARE sorder BIGINT DEFAULT 0;

    SELECT COUNT(*)
      INTO cmoney
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = pmoney;

    SELECT COUNT(*)
      INTO corder
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = porder;

    IF cmoney > 0 AND corder > 0 THEN
        SET @cnt = 0;
        SET @sql = CONCAT('SELECT COUNT(*) INTO @cnt FROM `', pmoney, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SET smoney = COALESCE(@cnt, 0);

        SET @cnt = 0;
        SET @sql = CONCAT('SELECT COUNT(*) INTO @cnt FROM `', porder, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SET sorder = COALESCE(@cnt, 0);

        IF smoney > 0 AND sorder = 0 THEN
            SET @sql = CONCAT(
                'INSERT INTO `', porder, '` (`mail`, `info`, `com`, `ip`, `agent`, `time`, `status`) ',
                'SELECT ',
                'COALESCE(`mail`, ''''), ',
                'CASE ',
                'WHEN COALESCE(`sum`, 0) <> 0 AND COALESCE(`info`, '''') <> '''' THEN CONCAT(''[legacy money sum='', `sum`, ''] '', `info`) ',
                'WHEN COALESCE(`sum`, 0) <> 0 THEN CONCAT(''[legacy money sum='', `sum`, '']'') ',
                'ELSE COALESCE(`info`, '''') ',
                'END, ',
                'COALESCE(`com`, ''''), ',
                'COALESCE(`ip`, ''''), ',
                'COALESCE(`agent`, ''''), ',
                'COALESCE(`date`, NOW()), ',
                'COALESCE(`status`, 0) ',
                'FROM `', pmoney, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

DELIMITER ;

# =============================================================================
# Batch A — _admins
# =============================================================================

CALL rencol('{prefix}_admins', 'lastvisit', 'lvisit');
CALL renidx('{prefix}_admins', 'lastvisit', 'lvisit');
CALL addidx('{prefix}_admins', 'name', '`name`', 1);
CALL addidx('{prefix}_admins', 'email', '`email`(191)', 0);

# =============================================================================
# Batch B — _auto_links
# =============================================================================

CALL rencol('{prefix}_auto_links', 'sitename', 'title');
CALL addidx('{prefix}_auto_links', 'added', '`added`', 0);
CALL addidx('{prefix}_auto_links', 'hits', '`hits`', 0);

# =============================================================================
# Batch C — _blocks
# =============================================================================

CALL rencol('{prefix}_blocks', 'bid', 'id');
CALL rencol('{prefix}_blocks', 'bposition', 'bpos');
CALL rencol('{prefix}_blocks', 'active', 'status');
CALL rencol('{prefix}_blocks', 'blanguage', 'blang');
CALL rencol('{prefix}_blocks', 'blockfile', 'bfile');
CALL renidx('{prefix}_blocks', 'active_position', 'status_position');
CALL renidx('{prefix}_blocks', 'blanguage', 'blang');
CALL addidx('{prefix}_blocks', 'title', '`title`', 0);
CALL addidx('{prefix}_blocks', 'status_position', '`status`, `bpos`', 0);

# =============================================================================
# Batch D — _categories
# =============================================================================

CALL rencol('{prefix}_categories', 'parentid', 'parent');
CALL rencol('{prefix}_categories', 'cstatus', 'status');
CALL rencol('{prefix}_categories', 'lpost_id', 'lpost');
CALL renidx('{prefix}_categories', 'parentid', 'parent');
CALL addidx('{prefix}_categories', 'modul', '`modul`', 0);
CALL addidx('{prefix}_categories', 'parent', '`parent`', 0);
CALL addidx('{prefix}_categories', 'modul_lang_status', '`modul`, `lang`, `status`', 0);
CALL addidx('{prefix}_categories', 'ordern', '`ordern`', 0);

# =============================================================================
# Batch E — _clients
# =============================================================================

CALL rencol('{prefix}_clients', 'id_user', 'uid');
CALL rencol('{prefix}_clients', 'id_product', 'prod');
CALL rencol('{prefix}_clients', 'id_partner', 'part');
CALL rencol('{prefix}_clients', 'partner_proz', 'proz');
CALL rencol('{prefix}_clients', 'adres', 'addr');
CALL rencol('{prefix}_clients', 'active', 'status');
CALL renidx('{prefix}_clients', 'id_user', 'uid');
CALL renidx('{prefix}_clients', 'id_product', 'prod');
CALL renidx('{prefix}_clients', 'id_partner', 'part');
CALL renidx('{prefix}_clients', 'active', 'status');
CALL addidx('{prefix}_clients', 'uid', '`uid`', 0);
CALL addidx('{prefix}_clients', 'prod', '`prod`', 0);
CALL addidx('{prefix}_clients', 'part', '`part`', 0);
CALL addidx('{prefix}_clients', 'status', '`status`', 0);
CALL addidx('{prefix}_clients', 'email', '`email`(191)', 0);

# =============================================================================
# Batch F — _comment
# =============================================================================

CALL rencol('{prefix}_comment', 'date', 'time');
CALL rencol('{prefix}_comment', 'host_name', 'ip');
CALL renidx('{prefix}_comment', 'date', 'time');
CALL addidx('{prefix}_comment', 'cid', '`cid`', 0);
CALL addidx('{prefix}_comment', 'uid', '`uid`', 0);
CALL addidx('{prefix}_comment', 'modul_status', '`modul`, `status`', 0);
CALL addidx('{prefix}_comment', 'time', '`time`', 0);

# =============================================================================
# Batch G — _faq
# =============================================================================

CALL rencol('{prefix}_faq', 'fid', 'id');
CALL rencol('{prefix}_faq', 'catid', 'cid');
CALL rencol('{prefix}_faq', 'ip_sender', 'ip');
CALL renidx('{prefix}_faq', 'catid', 'cid');
CALL addidx('{prefix}_faq', 'cid', '`cid`', 0);
CALL addidx('{prefix}_faq', 'counter', '`counter`', 0);
CALL addidx('{prefix}_faq', 'uid', '`uid`', 0);
CALL addidx('{prefix}_faq', 'status', '`status`', 0);
CALL addidx('{prefix}_faq', 'ihome', '`ihome`', 0);

# =============================================================================
# Batch G — _files
# =============================================================================

CALL rencol('{prefix}_files', 'lid', 'id');
CALL rencol('{prefix}_files', 'date', 'time');
CALL rencol('{prefix}_files', 'ip_sender', 'ip');
CALL rencol('{prefix}_files', 'totalvotes', 'tvotes');
CALL rencol('{prefix}_files', 'totalcomments', 'tcom');
CALL addidx('{prefix}_files', 'cid', '`cid`', 0);
CALL addidx('{prefix}_files', 'title', '`title`', 0);
CALL addidx('{prefix}_files', 'uid', '`uid`', 0);
CALL addidx('{prefix}_files', 'status', '`status`', 0);
CALL addidx('{prefix}_files', 'ihome', '`ihome`', 0);
CALL addidx('{prefix}_files', 'counter', '`counter`', 0);

# =============================================================================
# Batch G — _forum
# =============================================================================

CALL rencol('{prefix}_forum', 'catid', 'cid');
CALL rencol('{prefix}_forum', 'ip_send', 'ip');
CALL rencol('{prefix}_forum', 'l_uid', 'luid');
CALL rencol('{prefix}_forum', 'l_name', 'lname');
CALL rencol('{prefix}_forum', 'l_id', 'lpost');
CALL rencol('{prefix}_forum', 'l_time', 'ltime');
CALL rencol('{prefix}_forum', 'e_uid', 'euid');
CALL rencol('{prefix}_forum', 'e_ip_send', 'eip');
CALL rencol('{prefix}_forum', 'e_time', 'etime');
CALL renidx('{prefix}_forum', 'catid', 'cid');
CALL renidx('{prefix}_forum', 'catid_status', 'cid_status');
CALL addidx('{prefix}_forum', 'pid', '`pid`', 0);
CALL addidx('{prefix}_forum', 'cid', '`cid`', 0);
CALL addidx('{prefix}_forum', 'counter', '`counter`', 0);
CALL addidx('{prefix}_forum', 'uid', '`uid`', 0);
CALL addidx('{prefix}_forum', 'status', '`status`', 0);
CALL addidx('{prefix}_forum', 'cid_status', '`cid`, `status`', 0);
CALL addidx('{prefix}_forum', 'time', '`time`', 0);

# =============================================================================
# Batch H — _help
# =============================================================================

CALL rencol('{prefix}_help', 'sid', 'id');
CALL rencol('{prefix}_help', 'catid', 'cid');
CALL rencol('{prefix}_help', 'ip_sender', 'ip');
CALL renidx('{prefix}_help', 'catid', 'cid');
CALL addidx('{prefix}_help', 'pid', '`pid`', 0);
CALL addidx('{prefix}_help', 'cid', '`cid`', 0);
CALL addidx('{prefix}_help', 'counter', '`counter`', 0);
CALL addidx('{prefix}_help', 'uid', '`uid`', 0);
CALL addidx('{prefix}_help', 'status', '`status`', 0);

# =============================================================================
# Batch H — _jokes
# =============================================================================

CALL rencol('{prefix}_jokes', 'jokeid', 'id');
CALL rencol('{prefix}_jokes', 'date', 'time');
CALL rencol('{prefix}_jokes', 'cat', 'cid');
CALL rencol('{prefix}_jokes', 'joke', 'hometext');
CALL rencol('{prefix}_jokes', 'ratingtot', 'ratetot');
CALL rencol('{prefix}_jokes', 'ip_sender', 'ip');
CALL renidx('{prefix}_jokes', 'cat', 'cid');
CALL addidx('{prefix}_jokes', 'cid', '`cid`', 0);
CALL addidx('{prefix}_jokes', 'uid', '`uid`', 0);
CALL addidx('{prefix}_jokes', 'status', '`status`', 0);

# =============================================================================
# Batch I — _links
# =============================================================================

CALL rencol('{prefix}_links', 'lid', 'id');
CALL rencol('{prefix}_links', 'date', 'time');
CALL rencol('{prefix}_links', 'ip_sender', 'ip');
CALL rencol('{prefix}_links', 'totalvotes', 'tvotes');
CALL rencol('{prefix}_links', 'totalcomments', 'tcom');
CALL addidx('{prefix}_links', 'cid', '`cid`', 0);
CALL addidx('{prefix}_links', 'title', '`title`', 0);
CALL addidx('{prefix}_links', 'uid', '`uid`', 0);
CALL addidx('{prefix}_links', 'status', '`status`', 0);
CALL addidx('{prefix}_links', 'ihome', '`ihome`', 0);

# =============================================================================
# Batch J — _media
# =============================================================================

CALL rencol('{prefix}_media', 'createdby', 'author');
CALL rencol('{prefix}_media', 'date', 'time');
CALL rencol('{prefix}_media', 'totalvotes', 'tvotes');
CALL rencol('{prefix}_media', 'totalcom', 'tcom');
CALL rencol('{prefix}_media', 'ip_sender', 'ip');
CALL addidx('{prefix}_media', 'cid', '`cid`', 0);
CALL addidx('{prefix}_media', 'title', '`title`', 0);
CALL addidx('{prefix}_media', 'uid', '`uid`', 0);
CALL addidx('{prefix}_media', 'status', '`status`', 0);

# =============================================================================
# Batch J — _message
# =============================================================================

CALL rencol('{prefix}_message', 'mid', 'id');
CALL rencol('{prefix}_message', 'active', 'status');
CALL rencol('{prefix}_message', 'mlanguage', 'lang');
CALL renidx('{prefix}_message', 'active', 'status');
CALL renidx('{prefix}_message', 'mlanguage', 'lang');
CALL addidx('{prefix}_message', 'status', '`status`', 0);
CALL addidx('{prefix}_message', 'lang', '`lang`', 0);

# =============================================================================
# Batch J — _news
# =============================================================================

CALL rencol('{prefix}_news', 'sid', 'id');
CALL rencol('{prefix}_news', 'catid', 'cid');
CALL rencol('{prefix}_news', 'ip_sender', 'ip');
CALL renidx('{prefix}_news', 'catid', 'cid');
CALL addidx('{prefix}_news', 'cid', '`cid`', 0);
CALL addidx('{prefix}_news', 'counter', '`counter`', 0);
CALL addidx('{prefix}_news', 'uid', '`uid`', 0);
CALL addidx('{prefix}_news', 'status', '`status`', 0);
CALL addidx('{prefix}_news', 'ihome', '`ihome`', 0);
CALL addidx('{prefix}_news', 'time', '`time`', 0);

# =============================================================================
# Batch J — _order
# =============================================================================

CREATE TABLE IF NOT EXISTS `{prefix}_order` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mail` VARCHAR(255) NOT NULL,
  `info` TEXT NOT NULL,
  `com` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL rencol('{prefix}_order', 'date', 'time');
CALL renidx('{prefix}_order', 'date', 'time');
CALL addidx('{prefix}_order', 'status', '`status`', 0);
CALL addidx('{prefix}_order', 'time', '`time`', 0);

# =============================================================================
# Batch K — _pages
# =============================================================================

CALL rencol('{prefix}_pages', 'pid', 'id');
CALL rencol('{prefix}_pages', 'catid', 'cid');
CALL rencol('{prefix}_pages', 'ip_sender', 'ip');
CALL renidx('{prefix}_pages', 'catid', 'cid');
CALL addidx('{prefix}_pages', 'cid', '`cid`', 0);
CALL addidx('{prefix}_pages', 'uid', '`uid`', 0);
CALL addidx('{prefix}_pages', 'status', '`status`', 0);
CALL addidx('{prefix}_pages', 'ihome', '`ihome`', 0);
CALL delidx('{prefix}_pages', 'counter');

# =============================================================================
# Batch K — _partners
# =============================================================================

CALL rencol('{prefix}_partners', 'id_user', 'uid');
CALL rencol('{prefix}_partners', 'adres', 'addr');
CALL rencol('{prefix}_partners', 'active', 'status');
CALL renidx('{prefix}_partners', 'id_user', 'uid');
CALL renidx('{prefix}_partners', 'active', 'status');
CALL addidx('{prefix}_partners', 'uid', '`uid`', 0);
CALL addidx('{prefix}_partners', 'status', '`status`', 0);
CALL addidx('{prefix}_partners', 'email', '`email`(191)', 0);

# =============================================================================
# Batch K — _privat
# =============================================================================

CALL rencol('{prefix}_privat', 'date', 'time');
CALL rencol('{prefix}_privat', 'ip_sender', 'ip');
CALL renidx('{prefix}_privat', 'date', 'time');
CALL addidx('{prefix}_privat', 'uidin', '`uidin`', 0);
CALL addidx('{prefix}_privat', 'uidout', '`uidout`', 0);
CALL addidx('{prefix}_privat', 'status', '`status`', 0);
CALL addidx('{prefix}_privat', 'time', '`time`', 0);

# =============================================================================
# Batch K — _products
# =============================================================================

CALL rencol('{prefix}_products', 'preis', 'price');
CALL rencol('{prefix}_products', 'totalvotes', 'tvotes');
CALL rencol('{prefix}_products', 'active', 'status');
CALL renidx('{prefix}_products', 'active', 'status');
CALL addidx('{prefix}_products', 'cid', '`cid`', 0);
CALL addidx('{prefix}_products', 'status', '`status`', 0);
CALL addidx('{prefix}_products', 'ihome', '`ihome`', 0);

# =============================================================================
# Batch K — _rating
# =============================================================================

CALL rencol('{prefix}_rating', 'host', 'ip');
CALL renidx('{prefix}_rating', 'mid_modul_host', 'mid_modul_ip');
CALL addidx('{prefix}_rating', 'mid', '`mid`', 0);
CALL addidx('{prefix}_rating', 'modul', '`modul`', 0);
CALL addidx('{prefix}_rating', 'uid', '`uid`', 0);
CALL addidx('{prefix}_rating', 'mid_modul_ip', '`mid`, `modul`, `ip`', 1);

# =============================================================================
# Batch K — _referer
# =============================================================================

CALL rencol('{prefix}_referer', 'date', 'time');
CALL renidx('{prefix}_referer', 'date', 'time');
CALL addidx('{prefix}_referer', 'uid', '`uid`', 0);
CALL addidx('{prefix}_referer', 'time', '`time`', 0);
CALL addidx('{prefix}_referer', 'ip', '`ip`', 0);

# =============================================================================
# Batch K — _search
# =============================================================================

CALL rencol('{prefix}_search', 'sl_id', 'id');
CALL rencol('{prefix}_search', 'sl_word', 'word');
CALL rencol('{prefix}_search', 'sl_modul', 'modul');
CALL rencol('{prefix}_search', 'sl_time', 'time');
CALL rencol('{prefix}_search', 'sl_score', 'score');
CALL renidx('{prefix}_search', 'sl_word', 'word');
CALL renidx('{prefix}_search', 'sl_modul', 'modul');
CALL renidx('{prefix}_search', 'sl_time', 'time');
CALL renidx('{prefix}_search', 'sl_word_modul', 'word_modul');
CALL addidx('{prefix}_search', 'word', '`word`(191)', 0);
CALL addidx('{prefix}_search', 'modul', '`modul`', 0);
CALL addidx('{prefix}_search', 'time', '`time`', 0);
CALL addidx('{prefix}_search', 'word_modul', '`word`(191), `modul`', 0);

# =============================================================================
# Batch K — _session
# =============================================================================

CALL rencol('{prefix}_session', 'host_addr', 'ip');
CALL renidx('{prefix}_session', 'host_addr', 'ip');
CALL addidx('{prefix}_session', 'uname', '`uname`', 0);
CALL addidx('{prefix}_session', 'time', '`time`', 0);
CALL addidx('{prefix}_session', 'ip', '`ip`', 0);

# =============================================================================
# Batch K — _voting
# =============================================================================

CALL rencol('{prefix}_voting', 'date', 'time');
CALL addidx('{prefix}_voting', 'modul', '`modul`', 0);
CALL addidx('{prefix}_voting', 'status', '`status`', 0);

# =============================================================================
# Batch M — missing target tables and legacy data bridge
# =============================================================================

CREATE TABLE IF NOT EXISTS `{prefix}_jokes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL,
  `time` DATETIME DEFAULT NULL,
  `title` VARCHAR(100) NOT NULL,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `hometext` TEXT NOT NULL,
  `rating` VARCHAR(100) NOT NULL DEFAULT '0',
  `ratetot` VARCHAR(100) NOT NULL DEFAULT '0',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL addidx('{prefix}_jokes', 'cid', '`cid`', 0);
CALL addidx('{prefix}_jokes', 'uid', '`uid`', 0);
CALL addidx('{prefix}_jokes', 'status', '`status`', 0);

CREATE TABLE IF NOT EXISTS `{prefix}_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `subtitle` VARCHAR(100) NOT NULL DEFAULT '',
  `year` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `director` VARCHAR(100) NOT NULL DEFAULT '',
  `roles` VARCHAR(255) NOT NULL DEFAULT '',
  `description` TEXT NOT NULL,
  `author` VARCHAR(100) NOT NULL DEFAULT '',
  `duration` VARCHAR(100) NOT NULL DEFAULT '',
  `lang` VARCHAR(100) NOT NULL DEFAULT '',
  `note` TEXT NOT NULL,
  `format` VARCHAR(100) NOT NULL DEFAULT '',
  `quality` VARCHAR(100) NOT NULL DEFAULT '',
  `size` VARCHAR(100) NOT NULL DEFAULT '',
  `released` VARCHAR(100) NOT NULL DEFAULT '',
  `links` TEXT NOT NULL,
  `time` DATETIME DEFAULT NULL,
  `ihome` BOOLEAN NOT NULL DEFAULT 0,
  `acomm` BOOLEAN NOT NULL DEFAULT 0,
  `votes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tvotes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tcom` INT UNSIGNED NOT NULL DEFAULT 0,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL addidx('{prefix}_media', 'cid', '`cid`', 0);
CALL addidx('{prefix}_media', 'title', '`title`', 0);
CALL addidx('{prefix}_media', 'uid', '`uid`', 0);
CALL addidx('{prefix}_media', 'status', '`status`', 0);

CALL copymoney('{prefix}_money', '{prefix}_order');

# =============================================================================
# Batch L — _users
# =============================================================================

CALL rencol('{prefix}_users', 'user_id', 'id');
CALL rencol('{prefix}_users', 'user_name', 'name');
CALL rencol('{prefix}_users', 'user_rank', 'rank');
CALL rencol('{prefix}_users', 'user_email', 'email');
CALL rencol('{prefix}_users', 'user_website', 'website');
CALL rencol('{prefix}_users', 'user_avatar', 'avatar');
CALL rencol('{prefix}_users', 'user_regdate', 'regdate');
CALL rencol('{prefix}_users', 'user_occ', 'occ');
CALL rencol('{prefix}_users', 'user_from', 'origin');
CALL rencol('{prefix}_users', 'user_interests', 'interest');
CALL rencol('{prefix}_users', 'user_sig', 'sig');
CALL rencol('{prefix}_users', 'user_viewemail', 'viewmail');
CALL rencol('{prefix}_users', 'user_password', 'password');
CALL rencol('{prefix}_users', 'user_storynum', 'storynum');
CALL rencol('{prefix}_users', 'user_blockon', 'blockon');
CALL rencol('{prefix}_users', 'user_block', 'block');
CALL rencol('{prefix}_users', 'user_theme', 'theme');
CALL rencol('{prefix}_users', 'user_newsletter', 'newslet');
CALL rencol('{prefix}_users', 'user_fsmail', 'fsmail');
CALL rencol('{prefix}_users', 'user_psmail', 'psmail');
CALL rencol('{prefix}_users', 'user_lastvisit', 'lastvis');
CALL rencol('{prefix}_users', 'user_lang', 'lang');
CALL rencol('{prefix}_users', 'user_points', 'points');
CALL rencol('{prefix}_users', 'user_last_ip', 'lastip');
CALL rencol('{prefix}_users', 'user_warnings', 'warnings');
CALL rencol('{prefix}_users', 'user_acess', 'access');
CALL rencol('{prefix}_users', 'user_group', 'grp');
CALL rencol('{prefix}_users', 'user_birthday', 'birthday');
CALL rencol('{prefix}_users', 'user_gender', 'gender');
CALL rencol('{prefix}_users', 'user_votes', 'votes');
CALL rencol('{prefix}_users', 'user_totalvotes', 'tvotes');
CALL rencol('{prefix}_users', 'user_field', 'field');
CALL rencol('{prefix}_users', 'user_agent', 'agent');
CALL rencol('{prefix}_users', 'user_network', 'network');
CALL renidx('{prefix}_users', 'user_name', 'name');
CALL renidx('{prefix}_users', 'user_email', 'email');
CALL renidx('{prefix}_users', 'user_group', 'grp');
CALL renidx('{prefix}_users', 'user_points', 'points');
CALL renidx('{prefix}_users', 'user_lastvisit', 'lastvis');
# live legacy DBs may contain duplicate usernames; keep search/admin performance
# but do not force a UNIQUE constraint during structural migration
CALL addidx('{prefix}_users', 'name', '`name`', 0);
CALL addidx('{prefix}_users', 'email', '`email`(191)', 0);
CALL addidx('{prefix}_users', 'grp', '`grp`', 0);
CALL addidx('{prefix}_users', 'points', '`points`', 0);
CALL addidx('{prefix}_users', 'lastvis', '`lastvis`', 0);

# =============================================================================
# Batch L — _users_temp
# =============================================================================

CALL rencol('{prefix}_users_temp', 'user_id', 'id');
CALL rencol('{prefix}_users_temp', 'user_name', 'name');
CALL rencol('{prefix}_users_temp', 'user_email', 'email');
CALL rencol('{prefix}_users_temp', 'user_password', 'password');
CALL rencol('{prefix}_users_temp', 'user_regdate', 'regdate');
CALL rencol('{prefix}_users_temp', 'check_num', 'code');
CALL renidx('{prefix}_users_temp', 'user_name', 'name');
CALL renidx('{prefix}_users_temp', 'check_num', 'code');
CALL addidx('{prefix}_users_temp', 'name', '`name`', 1);
CALL addidx('{prefix}_users_temp', 'code', '`code`', 0);

# =============================================================================
# Batch N — structural fixes not covered in Batches A–L
# Run AFTER Batches A–L; safe to re-run (idempotent via IF EXISTS checks)
# =============================================================================

# _groups: restore PRIMARY KEY lost during column migration on some engines
# The DROP + ADD must be a single ALTER to satisfy AUTO_INCREMENT constraint
ALTER TABLE `{prefix}_groups`
  DROP KEY IF EXISTS `id`,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

# _whois: normalize types from legacy schema to match table.sql
UPDATE `{prefix}_whois` SET `ip` = '' WHERE `ip` IS NULL;
ALTER TABLE `{prefix}_whois`
  MODIFY `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `uid`       INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ip`        VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `sdomain`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `shost`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `sdc`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status`    TINYINT UNSIGNED NOT NULL DEFAULT 0;

# _users: normalize legacy zero-dates before changing column defaults
UPDATE `{prefix}_users` SET `regdate` = '1970-01-01 00:00:01' WHERE `regdate` = '0000-00-00 00:00:00';
UPDATE `{prefix}_users` SET `lastvis` = '1970-01-01 00:00:01' WHERE `lastvis` = '0000-00-00 00:00:00';
UPDATE `{prefix}_users` SET `network` = '' WHERE `network` IS NULL;

# _users: widen critical columns and fix types / defaults
ALTER TABLE `{prefix}_users`
  MODIFY `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `password`  VARCHAR(255) NOT NULL,
  MODIFY `lastip`    VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `regdate`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `lastvis`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `newslet`   TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `fsmail`    TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `psmail`    TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `points`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `votes`     INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `tvotes`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `storynum`  TINYINT UNSIGNED NOT NULL DEFAULT 10,
  MODIFY `grp`       INT NOT NULL DEFAULT 0,
  MODIFY `rank`      VARCHAR(25) NOT NULL DEFAULT '',
  MODIFY `network`   VARCHAR(255) NOT NULL DEFAULT '';

# =============================================================================
# Batch O: field naming unification — step 1
# =============================================================================

CALL rencol('{prefix}_admins',   'pwd',    'password');
CALL rencol('{prefix}_admins',   'lvisit', 'lastvis');
CALL renidx('{prefix}_admins',   'lvisit', 'lastvis');
CALL addidx('{prefix}_admins',   'lastvis', '`lastvis`', 0);
CALL rencol('{prefix}_products', 'count',  'counter');
CALL rencol('{prefix}_order',    'com',    'note');
CALL rencol('{prefix}_session',  'module', 'modul');

# =============================================================================
# Batch P: field naming unification — step 2 (language fields)
# =============================================================================

CALL rencol('{prefix}_blocks',     'blang',    'lang');
CALL rencol('{prefix}_categories', 'language', 'lang');
CALL rencol('{prefix}_voting',     'language', 'lang');
CALL renidx('{prefix}_blocks',     'blang',    'lang');
CALL renidx('{prefix}_voting',     'language', 'lang');
CALL addidx('{prefix}_blocks',     'lang', '`lang`', 0);
CALL addidx('{prefix}_voting',     'lang', '`lang`', 0);

# =============================================================================
# Batch Q: field naming unification — step 3 (comment counters)
# =============================================================================

CALL rencol('{prefix}_files',    'tcom', 'comments');
CALL rencol('{prefix}_links',    'tcom', 'comments');
CALL rencol('{prefix}_media',    'tcom', 'comments');
CALL rencol('{prefix}_products', 'com',  'comments');

# =============================================================================
# Batch R: field naming unification — step 4 (associated → assoc)
# =============================================================================

CALL rencol('{prefix}_news', 'associated', 'assoc');

# =============================================================================
# Batch S: content column renames — step 5
#   _news:     hometext → intro,  bodytext → body
#   _pages:    hometext → intro,  bodytext → body
#   _forum:    hometext → body
#   _faq:      hometext → body
#   _help:     hometext → body
#   _jokes:    hometext → body
#   _products: text     → intro,  bodytext → body
#   _files:    description → intro, bodytext → body
#   _links:    description → intro, bodytext → body
#   _media:    description → intro
#   _comment:  comment  → body
# =============================================================================

CALL rencol('{prefix}_news',     'hometext',    'intro');
CALL rencol('{prefix}_news',     'bodytext',    'body');
CALL rencol('{prefix}_pages',    'hometext',    'intro');
CALL rencol('{prefix}_pages',    'bodytext',    'body');
CALL rencol('{prefix}_forum',    'hometext',    'body');
CALL rencol('{prefix}_faq',      'hometext',    'body');
CALL rencol('{prefix}_help',     'hometext',    'body');
CALL rencol('{prefix}_jokes',    'hometext',    'body');
CALL rencol('{prefix}_products', 'text',        'intro');
CALL rencol('{prefix}_products', 'bodytext',    'body');
CALL rencol('{prefix}_files',    'description', 'intro');
CALL rencol('{prefix}_files',    'bodytext',    'body');
CALL rencol('{prefix}_links',    'description', 'intro');
CALL rencol('{prefix}_links',    'bodytext',    'body');
CALL rencol('{prefix}_media',    'description', 'intro');
CALL rencol('{prefix}_comment',  'comment',     'body');

# =============================================================================
# Batch T: content column renames — remaining tables
#   _whois:      hometext    → body
#   _categories: description → intro
#   _groups:     description → intro
#   _auto_links: description → intro
# =============================================================================

CALL rencol('{prefix}_whois',      'hometext',    'body');
CALL rencol('{prefix}_whois',      'st_domain',   'sdomain');
CALL rencol('{prefix}_whois',      'st_host',     'shost');
CALL rencol('{prefix}_whois',      'st_dc',       'sdc');
CALL rencol('{prefix}_categories', 'description', 'intro');
CALL rencol('{prefix}_groups',     'description', 'intro');
CALL rencol('{prefix}_auto_links', 'description', 'intro');
CALL addidx('{prefix}_whois',      'uid',  '`uid`',  0);
CALL addidx('{prefix}_whois',      'time', '`time`', 0);

# =============================================================================
# Batch V: naming consistency — url, email, website, pview/pread/... columns
# =============================================================================

# _auto_links: link → url, mail → email
CALL rencol('{prefix}_auto_links', 'link',        'url');
CALL rencol('{prefix}_auto_links', 'mail',        'email');

# _files: homepage → website
CALL rencol('{prefix}_files',      'homepage',    'website');

# _referer: link → url
CALL rencol('{prefix}_referer',    'link',        'url');

# _order: mail → email
CALL rencol('{prefix}_order',      'mail',        'email');

# _categories: auth_* → p* (permission columns)
CALL rencol('{prefix}_categories', 'auth_view',   'pview');
CALL rencol('{prefix}_categories', 'auth_read',   'pread');
CALL rencol('{prefix}_categories', 'auth_post',   'ppost');
CALL rencol('{prefix}_categories', 'auth_reply',  'preply');
CALL rencol('{prefix}_categories', 'auth_edit',   'pedit');
CALL rencol('{prefix}_categories', 'auth_delete', 'pdelete');
CALL rencol('{prefix}_categories', 'auth_mod',    'pmod');

# =============================================================================
# Cleanup
# =============================================================================

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS copymoney;

# =============================================================================
# Manual follow-up after this script
# =============================================================================
#
# 1. Review legacy tables that remain as sources or side-modules:
#    - `{prefix}_money` remains untouched after copy to `{prefix}_order`
#    - `{prefix}_clients_down`, `{prefix}_modules` are outside this migration
#
# 2. Password hashing migration (PHP side — already done in current codebase):
#    - passwords are now stored as bcrypt via getPassHash() / password_hash()
#    - existing md5_salt hashes are upgraded transparently on next user login
#    - no bulk SQL re-hash needed
