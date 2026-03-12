# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10.5+
#
# WARNING:
# - this is a local one-off finalizer for an already partially migrated database
# - do not use it as the public 6.2 -> 6.3 update script
# - public update path remains in setup/sql/table_update6_3.sql

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS ensure_unique_idx;
DROP PROCEDURE IF EXISTS finalize_user_names;

DELIMITER $$

CREATE PROCEDURE rencol(IN ptab VARCHAR(128), IN pold VARCHAR(128), IN pnew VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cold INT DEFAULT 0;
    DECLARE cnew INT DEFAULT 0;

    SELECT COUNT(*) INTO ctab FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*) INTO cold FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ptab AND column_name = pold;

        SELECT COUNT(*) INTO cnew FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ptab AND column_name = pnew;

        IF cold > 0 AND cnew = 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` RENAME COLUMN `', pold, '` TO `', pnew, '`');
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
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` DROP INDEX `', pidx, '`');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE ensure_unique_idx(IN ptab VARCHAR(128), IN pidx VARCHAR(128), IN pexp TEXT)
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cidx INT DEFAULT 0;
    DECLARE cuni INT DEFAULT 1;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name), COALESCE(MIN(non_unique), 1)
          INTO cidx, cuni
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pidx;

        IF cidx = 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` ADD UNIQUE KEY `', pidx, '` (', pexp, ')');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ELSEIF cuni <> 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` DROP INDEX `', pidx, '`, ADD UNIQUE KEY `', pidx, '` (', pexp, ')'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE finalize_user_names(IN ptab VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE ccol_id INT DEFAULT 0;
    DECLARE ccol_name INT DEFAULT 0;
    DECLARE cconflict INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*) INTO ccol_id
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = 'id';

        SELECT COUNT(*) INTO ccol_name
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = 'name';

        IF ccol_id > 0 AND ccol_name > 0 THEN
            DROP TEMPORARY TABLE IF EXISTS tmp_user_name_fix;

            CREATE TEMPORARY TABLE tmp_user_name_fix (
                id INT UNSIGNED NOT NULL PRIMARY KEY,
                old_name VARCHAR(25) NOT NULL,
                new_name VARCHAR(25) NOT NULL
            ) ENGINE=MEMORY;

            SET @sql = CONCAT(
                'INSERT INTO tmp_user_name_fix (`id`, `old_name`, `new_name`) ',
                'SELECT src.`id`, src.`name`, ',
                'CONCAT(LEFT(src.`name`, GREATEST(1, 25 - 1 - CHAR_LENGTH(src.`id`))), ''_'', src.`id`) ',
                'FROM (SELECT `id`, `name`, ROW_NUMBER() OVER (PARTITION BY `name` ORDER BY `id`) AS rn FROM `', ptab, '`) src ',
                'WHERE src.rn > 1'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SELECT COUNT(*)
              INTO cconflict
              FROM (
                    SELECT new_name
                      FROM tmp_user_name_fix
                     GROUP BY new_name
                    HAVING COUNT(*) > 1
                   ) dupnames;

            IF cconflict > 0 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Duplicate username auto-rename produced conflicting new_name values';
            END IF;

            SET @cnt = 0;
            SET @sql = CONCAT(
                'SELECT COUNT(*) INTO @cnt ',
                'FROM tmp_user_name_fix f JOIN `', ptab, '` u ON u.`name` = f.`new_name` AND u.`id` <> f.`id`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            SET cconflict = COALESCE(@cnt, 0);

            IF cconflict > 0 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Duplicate username auto-rename collides with existing usernames';
            END IF;

            SET @sql = CONCAT(
                'UPDATE `', ptab, '` u JOIN tmp_user_name_fix f ON f.`id` = u.`id` SET u.`name` = f.`new_name`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            DROP TEMPORARY TABLE IF EXISTS tmp_user_name_fix;
        END IF;
    END IF;
END$$

DELIMITER ;

CALL finalize_user_names('{prefix}_users');

ALTER TABLE `{prefix}_users`
  MODIFY `access` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `gender` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `block` TEXT NOT NULL,
  MODIFY `warnings` TEXT NOT NULL,
  MODIFY `field` TEXT NOT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL,
  MODIFY `points` INT UNSIGNED DEFAULT 0;

CALL ensure_unique_idx('{prefix}_users', 'name', '`name`');
CALL delidx('{prefix}_pages', 'counter');

ALTER TABLE `{prefix}_admins`
  MODIFY `name` VARCHAR(25) NOT NULL,
  MODIFY `title` VARCHAR(50) DEFAULT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL,
  MODIFY `pwd` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `{prefix}_blocks`
  MODIFY `title` VARCHAR(60) NOT NULL,
  MODIFY `content` TEXT NOT NULL,
  MODIFY `which` TEXT NOT NULL;

ALTER TABLE `{prefix}_categories`
  MODIFY `modul` VARCHAR(50) NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `description` TEXT NOT NULL;

ALTER TABLE `{prefix}_clients`
  MODIFY `name` VARCHAR(255) NOT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL;

ALTER TABLE `{prefix}_comment`
  MODIFY `modul` VARCHAR(60) NOT NULL,
  MODIFY `comment` TEXT NOT NULL;

CALL rencol('{prefix}_content', 'text', 'body');
ALTER TABLE `{prefix}_content`
  MODIFY `body`  MEDIUMTEXT NOT NULL,
  MODIFY `field` TEXT NOT NULL,
  MODIFY `url`   VARCHAR(200) NOT NULL;

ALTER TABLE `{prefix}_faq`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `hometext` TEXT;

ALTER TABLE `{prefix}_files`
  MODIFY `description` TEXT NOT NULL,
  MODIFY `bodytext` TEXT NOT NULL,
  MODIFY `url` VARCHAR(100) NOT NULL;

ALTER TABLE `{prefix}_forum`
  MODIFY `name` VARCHAR(25) NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `hometext` TEXT,
  MODIFY `field` TEXT NOT NULL;

ALTER TABLE `{prefix}_groups`
  MODIFY `name` VARCHAR(255) NOT NULL,
  MODIFY `description` TEXT NOT NULL;

ALTER TABLE `{prefix}_help`
  MODIFY `hometext` TEXT,
  MODIFY `field` TEXT NOT NULL;

ALTER TABLE `{prefix}_links`
  MODIFY `description` TEXT NOT NULL,
  MODIFY `bodytext` TEXT NOT NULL,
  MODIFY `url` VARCHAR(100) NOT NULL;

CALL rencol('{prefix}_message', 'content', 'body');
ALTER TABLE `{prefix}_message`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `body`  TEXT NOT NULL;

ALTER TABLE `{prefix}_news`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `hometext` TEXT,
  MODIFY `bodytext` TEXT NOT NULL,
  MODIFY `field` TEXT NOT NULL,
  MODIFY `associated` TEXT NOT NULL;

CALL rencol('{prefix}_newsletter', 'content', 'body');
ALTER TABLE `{prefix}_newsletter`
  MODIFY `title` VARCHAR(50) NOT NULL,
  MODIFY `body`  TEXT,
  MODIFY `mails` MEDIUMTEXT;

ALTER TABLE `{prefix}_order`
  MODIFY `info` TEXT NOT NULL,
  MODIFY `com` TEXT NOT NULL;

ALTER TABLE `{prefix}_pages`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `hometext` TEXT,
  MODIFY `bodytext` MEDIUMTEXT NOT NULL;

ALTER TABLE `{prefix}_partners`
  MODIFY `name` VARCHAR(255) NOT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL;

CALL rencol('{prefix}_privat', 'content', 'body');
ALTER TABLE `{prefix}_privat`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `body`  TEXT NOT NULL;

ALTER TABLE `{prefix}_products`
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `text` TEXT NOT NULL,
  MODIFY `bodytext` TEXT NOT NULL,
  MODIFY `assoc` TEXT NOT NULL;

ALTER TABLE `{prefix}_rating`
  MODIFY `modul` VARCHAR(50) NOT NULL,
  MODIFY `time` VARCHAR(14) NOT NULL;

ALTER TABLE `{prefix}_users_temp`
  MODIFY `name` VARCHAR(25) NOT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL,
  MODIFY `code` VARCHAR(50) NOT NULL,
  MODIFY `time` VARCHAR(14) NOT NULL;

CALL rencol('{prefix}_voting', 'questions', 'body');
ALTER TABLE `{prefix}_voting`
  MODIFY `body`   TEXT NOT NULL,
  MODIFY `answer` TEXT NOT NULL;

CALL rencol('{prefix}_clients_down', 'infotext', 'body');
CALL rencol('{prefix}_clients_down', 'prod_id',  'pid');

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS ensure_unique_idx;
DROP PROCEDURE IF EXISTS finalize_user_names;
