# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10.5+
#
# table_update6_3.sql — public update from SLAED 6.2 to SLAED 6.3 Phoenix
#
# Purpose:
# - migrate a live SLAED 6.2 database to the normalized schema used by 6.3
# - survive partial/manual refactors without crashing on already-renamed objects
# - skip missing tables silently
#
# Usage:
# - executed by setup/index.php during the public 6.2 -> 6.3 update path
# - back up the database before running the updater
# - safe to re-run for idempotent rename/index batches below
#
# Important:
# - this script covers column/index migration first
# - then it performs minimal table-level reconciliation required by current code
# - legacy source tables are not dropped automatically

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS poscol;
DROP PROCEDURE IF EXISTS setcoll;
DROP PROCEDURE IF EXISTS stopcol;
DROP PROCEDURE IF EXISTS stopnull;
DROP PROCEDURE IF EXISTS delcol;
DROP PROCEDURE IF EXISTS modcol;
DROP PROCEDURE IF EXISTS runifcol;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS mkuseruniq;
DROP PROCEDURE IF EXISTS mksessuniq;
DROP PROCEDURE IF EXISTS fixgrppk;
DROP PROCEDURE IF EXISTS finalize_user_names;

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

CREATE PROCEDURE addcol(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN pdef TEXT)
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*)
          INTO ccol
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pcol;

        IF ccol = 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'ADD COLUMN `', pcol, '` ', pdef
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE poscol(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN pdef TEXT, IN pafter VARCHAR(128))
BEGIN
    DECLARE ccol INT DEFAULT 0;
    DECLARE cpos INT DEFAULT 0;
    DECLARE apos INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND column_name = pcol;

    IF ccol > 0 THEN
        SELECT ordinal_position
          INTO cpos
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pcol;

        SELECT COUNT(ordinal_position), COALESCE(MAX(ordinal_position), 0)
          INTO ccol, apos
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pafter;

        IF ccol > 0 AND cpos <> apos + 1 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'MODIFY `', pcol, '` ', pdef, ' AFTER `', pafter, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE setcoll(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN pdef TEXT, IN pcoll VARCHAR(64))
BEGIN
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND column_name = pcol
       AND collation_name <> pcoll;

    IF ccol > 0 THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', ptab, '` ',
            'MODIFY `', pcol, '` ', pdef
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE stopcol(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN ptype VARCHAR(64), IN pmsg VARCHAR(128))
BEGIN
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND column_name = pcol
       AND UPPER(data_type) = UPPER(ptype);

    IF ccol > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = pmsg;
    END IF;
END$$

CREATE PROCEDURE stopnull(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN pmsg VARCHAR(128))
BEGIN
    DECLARE ccol INT DEFAULT 0;
    DECLARE crow INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND column_name = pcol
       AND is_nullable = 'YES';

    IF ccol > 0 THEN
        SET @sql = CONCAT('SELECT COUNT(*) INTO @crow FROM `', ptab, '` WHERE `', pcol, '` IS NULL');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SET crow = @crow;

        IF crow > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = pmsg;
        END IF;
    END IF;
END$$

CREATE PROCEDURE delcol(IN ptab VARCHAR(128), IN pcol VARCHAR(128))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*)
          INTO ccol
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pcol;

        IF ccol > 0 THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'DROP COLUMN `', pcol, '`'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE modcol(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN pdef TEXT)
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE ccol INT DEFAULT 0;
    DECLARE ctype TEXT DEFAULT '';
    DECLARE cdata VARCHAR(64) DEFAULT '';
    DECLARE cnull VARCHAR(3) DEFAULT '';
    DECLARE cdflt TEXT DEFAULT NULL;
    DECLARE cextr TEXT DEFAULT '';
    DECLARE wtype VARCHAR(64) DEFAULT '';
    DECLARE have TEXT DEFAULT '';
    DECLARE want TEXT DEFAULT '';

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(*)
          INTO ccol
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pcol;
    END IF;

    IF ccol > 0 THEN
        SELECT column_type, data_type, is_nullable, column_default, extra
          INTO ctype, cdata, cnull, cdflt, cextr
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND column_name = pcol;

        IF UPPER(cdata) IN ('TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT') AND LOCATE('(', ctype) > 0 THEN
            SET ctype = CONCAT(SUBSTRING_INDEX(ctype, '(', 1), SUBSTRING(ctype, LOCATE(')', ctype) + 1));
        END IF;

        SET have = UPPER(CONCAT(
            ctype,
            IF(cnull = 'NO', ' NOT NULL', ' NULL'),
            IF(cdflt IS NULL, '', CONCAT(' DEFAULT ', cdflt)),
            IF(cextr IS NULL OR cextr = '', '', CONCAT(' ', cextr))
        ));

        SET want = UPPER(TRIM(pdef));

        WHILE LOCATE('  ', want) > 0 DO
            SET want = REPLACE(want, '  ', ' ');
        END WHILE;

        SET wtype = SUBSTRING_INDEX(SUBSTRING_INDEX(want, ' ', 1), '(', 1);

        IF wtype IN ('TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT') AND LOCATE('(', want) > 0 THEN
            SET want = CONCAT(SUBSTRING_INDEX(want, '(', 1), SUBSTRING(want, LOCATE(')', want) + 1));
        END IF;

        IF LOCATE('NOT NULL', want) = 0 AND LOCATE(' NULL', want) = 0 THEN
            IF LOCATE(' DEFAULT ', want) > 0 THEN
                SET want = CONCAT(SUBSTRING_INDEX(want, ' DEFAULT ', 1), ' NULL DEFAULT ', SUBSTRING(want, LOCATE(' DEFAULT ', want) + 9));
            ELSE
                SET want = CONCAT(want, ' NULL');
            END IF;
        END IF;

        IF have <> want THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                'MODIFY `', pcol, '` ', pdef
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE runifcol(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN psql TEXT)
BEGIN
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND column_name = pcol;

    IF ccol > 0 THEN
        SET @sql = psql;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
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

CREATE PROCEDURE mkuseruniq(IN ptab VARCHAR(128))
BEGIN
    DECLARE ctab     INT DEFAULT 0;
    DECLARE has_key  INT DEFAULT 0;
    DECLARE has_uniq INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO has_uniq
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = 'name'
           AND non_unique = 0;

        IF has_uniq = 0 THEN
            SET @dups = 0;
            SET @sql  = CONCAT(
                'SELECT COUNT(*) INTO @dups FROM (',
                'SELECT `name` FROM `', ptab, '` GROUP BY `name` HAVING COUNT(*) > 1) _d'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            IF @dups = 0 THEN
                SELECT COUNT(DISTINCT index_name)
                  INTO has_key
                  FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ptab
                   AND index_name = 'name';

                IF has_key > 0 THEN
                    SET @sql = CONCAT('ALTER TABLE `', ptab, '` DROP INDEX `name`');
                    PREPARE stmt FROM @sql;
                    EXECUTE stmt;
                    DEALLOCATE PREPARE stmt;
                END IF;

                SET @sql = CONCAT('ALTER TABLE `', ptab, '` ADD UNIQUE KEY `name` (`name`)');
                PREPARE stmt FROM @sql;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;
            END IF;
        END IF;
    END IF;
END$$

CREATE PROCEDURE mksessuniq(IN ptab VARCHAR(128))
BEGIN
    DECLARE ctab     INT DEFAULT 0;
    DECLARE has_key  INT DEFAULT 0;
    DECLARE has_uniq INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO has_uniq
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = 'uname'
           AND non_unique = 0;

        IF has_uniq = 0 THEN
            SET @sql = CONCAT(
                'DELETE s FROM `', ptab, '` s JOIN `', ptab, '` t ',
                'ON s.`uname` = t.`uname` AND (s.`time` < t.`time` OR (s.`time` = t.`time` AND s.`id` < t.`id`))'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

            SELECT COUNT(DISTINCT index_name)
              INTO has_key
              FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ptab
               AND index_name = 'uname';

            IF has_key > 0 THEN
                SET @sql = CONCAT('ALTER TABLE `', ptab, '` DROP INDEX `uname`');
                PREPARE stmt FROM @sql;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;
            END IF;

            SET @sql = CONCAT('ALTER TABLE `', ptab, '` ADD UNIQUE KEY `uname` (`uname`)');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE fixgrppk(IN ptab VARCHAR(128))
BEGIN
    DECLARE ctab     INT DEFAULT 0;
    DECLARE has_pk   INT DEFAULT 0;
    DECLARE has_dupk INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT COUNT(DISTINCT index_name)
          INTO has_pk
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = 'PRIMARY';

        SELECT COUNT(DISTINCT index_name)
          INTO has_dupk
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = 'id';

        IF has_dupk > 0 AND has_pk > 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` DROP INDEX `id`');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ELSEIF has_dupk > 0 AND has_pk = 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` DROP INDEX `id`, ADD PRIMARY KEY (`id`)');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ELSEIF has_dupk = 0 AND has_pk = 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', ptab, '` ADD PRIMARY KEY (`id`)');
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
    END IF;
END$$

CREATE PROCEDURE finalize_user_names(IN ptab VARCHAR(128))
BEGIN
    DECLARE ctab     INT DEFAULT 0;
    DECLARE ccol_id  INT DEFAULT 0;
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

# =============================================================================
# Batch A — _admins
# =============================================================================

CALL rencol('{prefix}_admins', 'lastvisit', 'lvisit');
CALL renidx('{prefix}_admins', 'lastvisit', 'lvisit');
CALL addidx('{prefix}_admins', 'name', '`name`', 0);
CALL mkuseruniq('{prefix}_admins');
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
UPDATE `{prefix}_blocks` SET bfile = REPLACE(bfile, 'block-', '') WHERE bfile LIKE 'block-%';

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
CALL addidx('{prefix}_comment', 'uid', '`uid`', 0);
CALL addidx('{prefix}_comment', 'time', '`time`', 0);
# cid and modul_status are not created here: batch Y below adds the composites that supersede them and drops both

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
# Batch G — _favorites
# =============================================================================

CALL addidx('{prefix}_favorites', 'uid',          '`uid`',                     0);
CALL addidx('{prefix}_favorites', 'fid',          '`fid`',                     0);
CALL addidx('{prefix}_favorites', 'modul',        '`modul`',                   0);
CALL addidx('{prefix}_favorites', 'uid_fid_modul','`uid`, `fid`, `modul`',     1);

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
  `email` VARCHAR(255) NOT NULL,
  `info` TEXT NOT NULL,
  `note` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL rencol('{prefix}_order', 'date',  'time');
CALL rencol('{prefix}_order', 'mail',  'email');
CALL rencol('{prefix}_order', 'com',   'note');
CALL renidx('{prefix}_order', 'date',  'time');
CALL addidx('{prefix}_order', 'status', '`status`', 0);
CALL addidx('{prefix}_order', 'time',   '`time`',   0);

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
#
# The single status column becomes four independent states: viewed and saved belong to the recipient,
# delin and delout to the two mailbox sides, so what one participant deletes or saves no longer rewrites
# what the other one sees.
# The order is load-bearing. The backfill has to read status before the rename consumes it, which is what
# runifcol() executes it under: once the rename has happened the column is gone and the step can never run
# a second time. rencol() keeps the BOOLEAN the old column was declared as, so modcol() forces viewed onto
# the definition the fresh schema gives it and leaves the table alone when it already carries it.
# The composites are added last and time keeps its place in front of them, which is the order
# setup/sql/table.sql declares, so an upgraded table and a fresh one are the same table.
# No index on status is created here any more: this file used to add one and now drops it, on a column
# that does not survive the batch.
# out_new and flood answer the two reads out_box cannot: the outgoing unread counter of the sidebar block,
# which filters a column out_box does not carry, and the send interval, which filters no state at all and
# orders by time - measured on real data, the first was a full table scan and the second a filesort.
#
# A body is stored as the source its author wrote and is rendered through one contract, so no column names
# a storage format: plain and the editors are input interfaces, and rendering never branches on them.

CALL rencol('{prefix}_privat', 'date', 'time');
CALL rencol('{prefix}_privat', 'ip_sender', 'ip');
CALL renidx('{prefix}_privat', 'date', 'time');
CALL delcol('{prefix}_privat', 'format');
CALL addcol('{prefix}_privat', 'saved', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_privat', 'delin', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_privat', 'delout', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL runifcol('{prefix}_privat', 'status', 'UPDATE `{prefix}_privat` SET `saved` = 1 WHERE `status` = 2');
CALL rencol('{prefix}_privat', 'status', 'viewed');
CALL modcol('{prefix}_privat', 'viewed', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL runifcol('{prefix}_privat', 'viewed', 'UPDATE `{prefix}_privat` SET `viewed` = 1 WHERE `viewed` > 1');
CALL delidx('{prefix}_privat', 'uidin');
CALL delidx('{prefix}_privat', 'uidout');
CALL delidx('{prefix}_privat', 'status');
CALL addidx('{prefix}_privat', 'time', '`time`', 0);
CALL addidx('{prefix}_privat', 'in_box', '`uidin`, `delin`, `saved`, `time`', 0);
CALL addidx('{prefix}_privat', 'in_new', '`uidin`, `delin`, `viewed`', 0);
CALL addidx('{prefix}_privat', 'out_box', '`uidout`, `delout`, `time`', 0);
CALL addidx('{prefix}_privat', 'out_new', '`uidout`, `delout`, `viewed`', 0);
CALL addidx('{prefix}_privat', 'flood', '`uidout`, `time`', 0);
CALL stopnull('{prefix}_privat', 'time', 'privat.time holds NULL rows: repair them before this upgrade');
CALL modcol('{prefix}_privat', 'time', 'DATETIME NOT NULL');

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
CALL mksessuniq('{prefix}_session');
CALL addidx('{prefix}_session', 'time', '`time`', 0);
CALL addidx('{prefix}_session', 'ip', '`ip`', 0);

# =============================================================================
# Batch K — _voting
# =============================================================================

CALL rencol('{prefix}_voting', 'date', 'time');
CALL addidx('{prefix}_voting', 'modul', '`modul`', 0);
CALL addidx('{prefix}_voting', 'status', '`status`', 0);

# =============================================================================
# Batch M — missing target tables and legacy side tables
# =============================================================================

CREATE TABLE IF NOT EXISTS `{prefix}_jokes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `name` VARCHAR(25) NOT NULL,
  `time` DATETIME DEFAULT NULL,
  `title` VARCHAR(100) NOT NULL,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `body` TEXT NOT NULL,
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
  `intro` TEXT NOT NULL,
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
  `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL addidx('{prefix}_media', 'cid', '`cid`', 0);
CALL addidx('{prefix}_media', 'title', '`title`', 0);
CALL addidx('{prefix}_media', 'uid', '`uid`', 0);
CALL addidx('{prefix}_media', 'status', '`status`', 0);

CREATE TABLE IF NOT EXISTS `{prefix}_clients_down` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}_money` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sum` INT UNSIGNED NOT NULL DEFAULT 0,
  `email` VARCHAR(255) NOT NULL,
  `intro` TEXT NOT NULL,
  `note` TEXT NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME DEFAULT NULL,
  `status` BOOLEAN NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `email` (`email`(191)),
  KEY `status` (`status`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
CALL rencol('{prefix}_users', 'user_last_ip', 'ip');
CALL rencol('{prefix}_users', 'lastip', 'ip');
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
# add non-unique key first; mkuseruniq below upgrades to UNIQUE if no duplicates exist
CALL addidx('{prefix}_users', 'name', '`name`', 0);
CALL addidx('{prefix}_users', 'email', '`email`(191)', 0);
CALL addidx('{prefix}_users', 'grp', '`grp`', 0);
CALL addidx('{prefix}_users', 'points', '`points`', 0);
CALL addidx('{prefix}_users', 'lastvis', '`lastvis`', 0);
# resolve duplicate usernames before attempting UNIQUE constraint
CALL finalize_user_names('{prefix}_users');
# upgrade KEY name → UNIQUE KEY name; skipped silently if duplicate usernames exist
CALL mkuseruniq('{prefix}_users');

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
CALL addidx('{prefix}_users_temp', 'name', '`name`', 0);
CALL mkuseruniq('{prefix}_users_temp');
CALL addidx('{prefix}_users_temp', 'code', '`code`', 0);

# =============================================================================
# Batch N — structural fixes not covered in Batches A–L
# Run AFTER Batches A–L; safe to re-run (idempotent via IF EXISTS checks)
# =============================================================================

# _groups: restore PRIMARY KEY lost during column migration on some engines
# fixgrppk handles all 4 states via information_schema (MySQL 8.0+ & MariaDB 10+ compatible)
CALL fixgrppk('{prefix}_groups');
CALL addidx('{prefix}_groups', 'name', '`name`(191)', 0);

# _whois: rename legacy status columns before type normalization
# Must run here (Batch N) because the MODIFY below references sdomain/shost/sdc
CALL rencol('{prefix}_whois', 'st_domain', 'sdomain');
CALL rencol('{prefix}_whois', 'st_host',   'shost');
CALL rencol('{prefix}_whois', 'st_dc',     'sdc');

# _whois: normalize types from legacy schema to match table.sql
UPDATE `{prefix}_whois` SET `name`   = '' WHERE `name`   IS NULL;
UPDATE `{prefix}_whois` SET `ip`     = '' WHERE `ip`     IS NULL;
UPDATE `{prefix}_whois` SET `domain` = '' WHERE `domain` IS NULL;
UPDATE `{prefix}_whois` SET `host`   = '' WHERE `host`   IS NULL;
UPDATE `{prefix}_whois` SET `dc`     = '' WHERE `dc`     IS NULL;
ALTER TABLE `{prefix}_whois`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `uid`     INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `name`    VARCHAR(25) NOT NULL DEFAULT '',
  MODIFY `ip`      VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `time`    DATETIME DEFAULT NULL,
  MODIFY `domain`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `host`    VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `dc`      VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `body`    MEDIUMTEXT,
  MODIFY `sdomain` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `shost`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `sdc`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status`  TINYINT UNSIGNED NOT NULL DEFAULT 0;

# _users: normalize legacy zero-dates before changing column defaults
UPDATE `{prefix}_users` SET `regdate` = '1970-01-01 00:00:01' WHERE `regdate` = '0000-00-00 00:00:00';
UPDATE `{prefix}_users` SET `lastvis` = '1970-01-01 00:00:01' WHERE `lastvis` = '0000-00-00 00:00:00';

# _users: widen critical columns and fix types / defaults
# network is deliberately absent here: the OAuth block at the end of this file archives it and drops it,
# so touching it would make this statement - and with it the thirteen columns below - fail on every re-run
ALTER TABLE `{prefix}_users`
  MODIFY `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `password`  VARCHAR(255) NOT NULL,
  MODIFY `ip`        VARCHAR(45) NOT NULL DEFAULT '',
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
  MODIFY `rank`      VARCHAR(25) NOT NULL DEFAULT '';

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
# Batch W: body column unification
#   _clients_down: infotext   → body,  prod_id   → pid
#   _content:      text       → body
#   _message:      content    → body
#   _newsletter:   content    → body
#   _privat:       content    → body
#   _voting:       questions  → body
# =============================================================================

CALL rencol('{prefix}_clients_down', 'infotext',   'body');
CALL rencol('{prefix}_clients_down', 'prod_id',    'pid');
CALL addidx('{prefix}_clients_down', 'pid',    '`pid`',    0);
CALL addidx('{prefix}_clients_down', 'status', '`status`', 0);
CALL rencol('{prefix}_content',      'text',       'body');
CALL addidx('{prefix}_content',      'counter', '`counter`',   0);
CALL addidx('{prefix}_content',      'url',     '`url`(191)',  0);
CALL rencol('{prefix}_message',      'content',    'body');
CALL rencol('{prefix}_newsletter',   'content',    'body');
CALL addidx('{prefix}_newsletter',   'time',    '`time`',      0);
CALL rencol('{prefix}_privat',       'content',    'body');
CALL rencol('{prefix}_voting',       'questions',  'body');

# _money: rename legacy columns to the normalized 6.3 schema
CALL rencol('{prefix}_money', 'mail', 'email');
CALL rencol('{prefix}_money', 'info', 'intro');
CALL rencol('{prefix}_money', 'com',  'note');
CALL rencol('{prefix}_money', 'date', 'time');
CALL renidx('{prefix}_money', 'date', 'time');
CALL addidx('{prefix}_money', 'email',  '`email`(191)', 0);
CALL addidx('{prefix}_money', 'status', '`status`',     0);
CALL addidx('{prefix}_money', 'time',   '`time`',       0);

# =============================================================================
# Final type alignment to setup/sql/table.sql
# =============================================================================

# _admins.editor names the editor plugin an administrator writes with and has done since the editor
# rework; it is not a flag. A legacy installation still carries the boolean it was, so the value is
# normalized before the column is widened - a NULL would refuse the NOT NULL under strict mode, and
# the 0 or 1 of the old shape names no plugin, which getEditorKey() resolves back to plain anyway.
UPDATE `{prefix}_admins` SET `editor` = 'plain' WHERE `editor` IS NULL OR `editor` IN ('', '0', '1');

ALTER TABLE `{prefix}_admins`
  MODIFY `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`     VARCHAR(25) NOT NULL,
  MODIFY `title`    VARCHAR(50) DEFAULT NULL,
  MODIFY `url`      VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `email`    VARCHAR(255) NOT NULL,
  MODIFY `password` VARCHAR(255) DEFAULT NULL,
  MODIFY `super`    BOOLEAN DEFAULT NULL,
  MODIFY `editor`   VARCHAR(32) NOT NULL DEFAULT 'plain',
  MODIFY `smail`    BOOLEAN DEFAULT NULL,
  MODIFY `modules`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `lang`     VARCHAR(30) NOT NULL DEFAULT '',
  MODIFY `ip`       VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `regdate`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `lastvis`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `{prefix}_auto_links`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `url` VARCHAR(100) NOT NULL,
  MODIFY `email` VARCHAR(100) NOT NULL,
  MODIFY `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `outs` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `{prefix}_blocks`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(60) NOT NULL,
  MODIFY `content` TEXT NOT NULL,
  MODIFY `url` VARCHAR(200) NOT NULL DEFAULT '',
  MODIFY `bpos` CHAR(1) NOT NULL DEFAULT '',
  MODIFY `weight` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `refresh` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `time` VARCHAR(14) NOT NULL DEFAULT '0',
  MODIFY `lang` VARCHAR(30) NOT NULL DEFAULT '',
  MODIFY `bfile` VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `view` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `expire` VARCHAR(14) NOT NULL DEFAULT '0',
  MODIFY `action` CHAR(1) NOT NULL DEFAULT '',
  MODIFY `which` TEXT NOT NULL;

ALTER TABLE `{prefix}_categories`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `img` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `lang` VARCHAR(30) NOT NULL DEFAULT '',
  MODIFY `parent` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `ordern` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_clients`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name` VARCHAR(255) NOT NULL,
  MODIFY `addr` VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `phone` VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `email` VARCHAR(255) NOT NULL,
  MODIFY `website` VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `info` VARCHAR(255) NOT NULL DEFAULT '';

UPDATE `{prefix}_clients_down` SET `body` = '' WHERE `body` IS NULL;
UPDATE `{prefix}_clients_down` SET `url`  = '' WHERE `url`  IS NULL;
UPDATE `{prefix}_clients_down` SET `num`  = '' WHERE `num`  IS NULL;
UPDATE `{prefix}_clients_down` SET `code` = '' WHERE `code` IS NULL;

ALTER TABLE `{prefix}_clients_down`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title`  VARCHAR(100) NOT NULL,
  MODIFY `body`   MEDIUMTEXT NOT NULL,
  MODIFY `url`    VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `num`    VARCHAR(10) NOT NULL DEFAULT '',
  MODIFY `code`   VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `hits`   INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `pid`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

# Every column widened to MEDIUMTEXT in this section is written through the rich editor, and the
# editor can embed an image into the text instead of storing a file: EMBEDMAX caps a data URI at
# 65536 bytes of binary, which is 87384 characters of base64 before the prefix and the markdown,
# while TEXT holds 65535. With STRICT_TRANS_TABLES that is a lost post, not a lost image. The
# summary columns (`intro`, and `users.block`) stay TEXT on purpose: a list query draws twenty of
# them onto one page, and an image referenced by address or uploaded to the server still fits
# there. auto_links.intro and users.sig widen only to TEXT and for a reason of their own: a
# VARCHAR(255) behind a rich editor holds about 127 Cyrillic characters, which is one sentence.
ALTER TABLE `{prefix}_comment`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(60) NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL;

ALTER TABLE `{prefix}_content`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title`   VARCHAR(100) DEFAULT NULL,
  MODIFY `body`    MEDIUMTEXT NOT NULL,
  MODIFY `field`   TEXT NOT NULL,
  MODIFY `url`     VARCHAR(200) NOT NULL,
  MODIFY `time`    DATETIME DEFAULT NULL,
  MODIFY `refresh` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `counter` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_favorites`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) NOT NULL;

ALTER TABLE `{prefix}_faq`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `body` MEDIUMTEXT;

ALTER TABLE `{prefix}_files`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `url` VARCHAR(100) NOT NULL;

ALTER TABLE `{prefix}_forum`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name` VARCHAR(25) NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `body` MEDIUMTEXT,
  MODIFY `field` TEXT NOT NULL;

ALTER TABLE `{prefix}_groups`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`   VARCHAR(255) NOT NULL,
  MODIFY `intro`  TEXT NOT NULL,
  MODIFY `points` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `extra`  BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `rank`   VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `color`  VARCHAR(7) NOT NULL DEFAULT '';

ALTER TABLE `{prefix}_help`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `body` MEDIUMTEXT,
  MODIFY `field` TEXT NOT NULL;

ALTER TABLE `{prefix}_jokes`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`    VARCHAR(25) NOT NULL,
  MODIFY `title`   VARCHAR(100) NOT NULL,
  MODIFY `body`    MEDIUMTEXT NOT NULL,
  MODIFY `rating`  VARCHAR(100) NOT NULL DEFAULT '0',
  MODIFY `ratetot` VARCHAR(100) NOT NULL DEFAULT '0',
  MODIFY `ip`      VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `status`  BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_links`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `url` VARCHAR(100) NOT NULL;

UPDATE `{prefix}_media` SET `name`     = '' WHERE `name`     IS NULL;
UPDATE `{prefix}_media` SET `title`    = '' WHERE `title`    IS NULL;
UPDATE `{prefix}_media` SET `subtitle` = '' WHERE `subtitle` IS NULL;
UPDATE `{prefix}_media` SET `director` = '' WHERE `director` IS NULL;
UPDATE `{prefix}_media` SET `roles`    = '' WHERE `roles`    IS NULL;
UPDATE `{prefix}_media` SET `intro`    = '' WHERE `intro`    IS NULL;
UPDATE `{prefix}_media` SET `author`   = '' WHERE `author`   IS NULL;
UPDATE `{prefix}_media` SET `duration` = '' WHERE `duration` IS NULL;
UPDATE `{prefix}_media` SET `lang`     = '' WHERE `lang`     IS NULL;
UPDATE `{prefix}_media` SET `note`     = '' WHERE `note`     IS NULL;
UPDATE `{prefix}_media` SET `format`   = '' WHERE `format`   IS NULL;
UPDATE `{prefix}_media` SET `quality`  = '' WHERE `quality`  IS NULL;
UPDATE `{prefix}_media` SET `size`     = '' WHERE `size`     IS NULL;
UPDATE `{prefix}_media` SET `released` = '' WHERE `released` IS NULL;
UPDATE `{prefix}_media` SET `links`    = '' WHERE `links`    IS NULL;
UPDATE `{prefix}_media` SET `ip`       = '' WHERE `ip`       IS NULL;

ALTER TABLE `{prefix}_media`
  MODIFY `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `cid`      INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `uid`      INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `name`     VARCHAR(25) NOT NULL,
  MODIFY `title`    VARCHAR(100) NOT NULL,
  MODIFY `subtitle` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `year`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `director` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `roles`    VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `intro`    TEXT NOT NULL,
  MODIFY `author`   VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `duration` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `lang`     VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `note`     MEDIUMTEXT NOT NULL,
  MODIFY `format`   VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `quality`  VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `size`     VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `released` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `links`    TEXT NOT NULL,
  MODIFY `time`     DATETIME DEFAULT NULL,
  MODIFY `ihome`    BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `acomm`    BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `votes`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `tvotes`   INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `hits`     INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ip`       VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `status`   BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_message`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title`  VARCHAR(100) NOT NULL,
  MODIFY `body`   MEDIUMTEXT NOT NULL,
  MODIFY `expire` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `view`   BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `lang`   VARCHAR(30) NOT NULL DEFAULT '';

UPDATE `{prefix}_money` SET `email` = '' WHERE `email` IS NULL;
UPDATE `{prefix}_money` SET `intro` = '' WHERE `intro` IS NULL;
UPDATE `{prefix}_money` SET `note`  = '' WHERE `note`  IS NULL;
UPDATE `{prefix}_money` SET `ip`    = '' WHERE `ip`    IS NULL;
UPDATE `{prefix}_money` SET `agent` = '' WHERE `agent` IS NULL;

ALTER TABLE `{prefix}_money`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `sum`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `email`  VARCHAR(255) NOT NULL,
  MODIFY `intro`  TEXT NOT NULL,
  MODIFY `note`   MEDIUMTEXT NOT NULL,
  MODIFY `ip`     VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `agent`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `time`   DATETIME DEFAULT NULL,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_news`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `field` TEXT NOT NULL,
  MODIFY `assoc` TEXT NOT NULL;

ALTER TABLE `{prefix}_newsletter`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(50) NOT NULL,
  MODIFY `body` MEDIUMTEXT;

UPDATE `{prefix}_order` SET `ip`    = '' WHERE `ip`    IS NULL;
UPDATE `{prefix}_order` SET `agent` = '' WHERE `agent` IS NULL;

ALTER TABLE `{prefix}_order`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `email`  VARCHAR(255) NOT NULL,
  MODIFY `info`   TEXT NOT NULL,
  MODIFY `note`   MEDIUMTEXT NOT NULL,
  MODIFY `ip`     VARCHAR(45) NOT NULL DEFAULT '',
  MODIFY `agent`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `time`   DATETIME DEFAULT NULL,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_pages`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT,
  MODIFY `body` MEDIUMTEXT NOT NULL;

ALTER TABLE `{prefix}_partners`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name` VARCHAR(255) NOT NULL,
  MODIFY `email` VARCHAR(255) NOT NULL;

# The message table is reconciled column by column: a bare MODIFY rebuilds the table even when nothing changes, and an installation already on 6.3 must not be rewritten
CALL modcol('{prefix}_privat', 'id',    'INT UNSIGNED NOT NULL AUTO_INCREMENT');
CALL modcol('{prefix}_privat', 'title', 'VARCHAR(100) NOT NULL');
CALL modcol('{prefix}_privat', 'body',  'MEDIUMTEXT NOT NULL');

ALTER TABLE `{prefix}_products`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `assoc` TEXT NOT NULL;

ALTER TABLE `{prefix}_rating`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) NOT NULL,
  MODIFY `time` VARCHAR(14) NOT NULL;

UPDATE `{prefix}_referer` SET `referer` = '' WHERE `referer` IS NULL;
UPDATE `{prefix}_referer` SET `url`     = '' WHERE `url`     IS NULL;

ALTER TABLE `{prefix}_referer`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `referer` VARCHAR(2048) NOT NULL DEFAULT '',
  MODIFY `url`     VARCHAR(2048) NOT NULL DEFAULT '';

UPDATE `{prefix}_session` SET `url` = '' WHERE `url` IS NULL;

ALTER TABLE `{prefix}_search`
  MODIFY `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `{prefix}_session`
  MODIFY `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `url` VARCHAR(2048) NOT NULL DEFAULT '';

# NULL-safety before widening _users columns (may be NULL in legacy schema)
UPDATE `{prefix}_users` SET `email`    = '' WHERE `email`    IS NULL;
UPDATE `{prefix}_users` SET `website`  = '' WHERE `website`  IS NULL;
UPDATE `{prefix}_users` SET `avatar`   = '' WHERE `avatar`   IS NULL;
UPDATE `{prefix}_users` SET `block`    = '' WHERE `block`    IS NULL;
UPDATE `{prefix}_users` SET `warnings` = '' WHERE `warnings` IS NULL;
UPDATE `{prefix}_users` SET `field`    = '' WHERE `field`    IS NULL;
UPDATE `{prefix}_users` SET `interest` = '' WHERE `interest` IS NULL;
UPDATE `{prefix}_users` SET `theme`    = '' WHERE `theme`    IS NULL;
UPDATE `{prefix}_users` SET `agent`    = '' WHERE `agent`    IS NULL;

ALTER TABLE `{prefix}_users`
  MODIFY `name`     VARCHAR(25) NOT NULL,
  MODIFY `email`    VARCHAR(255) NOT NULL,
  MODIFY `website`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `avatar`   VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `occ`      VARCHAR(100) DEFAULT NULL,
  MODIFY `origin`   VARCHAR(100) DEFAULT NULL,
  MODIFY `interest` VARCHAR(150) NOT NULL DEFAULT '',
  MODIFY `sig`      TEXT,
  MODIFY `viewmail` BOOLEAN DEFAULT NULL,
  MODIFY `blockon`  BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `block`    TEXT NOT NULL,
  MODIFY `theme`    VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `lang`     VARCHAR(255) NOT NULL DEFAULT 'russian',
  MODIFY `access`   BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `birthday` DATE DEFAULT NULL,
  MODIFY `gender`   BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `warnings` TEXT NOT NULL,
  MODIFY `field`    TEXT NOT NULL,
  MODIFY `agent`    VARCHAR(255) NOT NULL DEFAULT '';

UPDATE `{prefix}_users_temp` SET `regdate` = '1970-01-01 00:00:01' WHERE `regdate` = '0000-00-00 00:00:00';

ALTER TABLE `{prefix}_users_temp`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`    VARCHAR(25) NOT NULL,
  MODIFY `email`   VARCHAR(255) NOT NULL,
  MODIFY `password` VARCHAR(255) NOT NULL,
  MODIFY `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `code`    VARCHAR(50) NOT NULL,
  MODIFY `time`    VARCHAR(14) NOT NULL;

ALTER TABLE `{prefix}_voting`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `body` TEXT NOT NULL,
  MODIFY `answer` TEXT NOT NULL;

# =============================================================================
# Batch X — _mail, the outgoing mail queue
# =============================================================================
#
# The table is new in 6.3 and has no legacy source, so the create carries the
# primary key alone and every secondary index is added through addidx. An
# installation whose upgrade stopped midway therefore gains only the indexes it
# is still missing instead of failing on the ones it already has.

CREATE TABLE IF NOT EXISTS `{prefix}_mail` (
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
  PRIMARY KEY (`id`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CALL addidx('{prefix}_mail', 'hold_status_prio_ntime', '`hold`, `status`, `prio`, `ntime`, `id`', 0);
CALL addidx('{prefix}_mail', 'kind_status_time',       '`kind`, `status`, `time`',                0);
CALL addidx('{prefix}_mail', 'kind_ref_status',        '`kind`, `ref`, `status`',                 0);
CALL addidx('{prefix}_mail', 'lockid',                 '`lockid`',                                0);
CALL addidx('{prefix}_mail', 'locked',                 '`locked`',                                0);

# =============================================================================
# Batch Y — _comment, the reply tree, the soft delete and the write keys
# =============================================================================
#
# Four columns are added, two superseded indexes are dropped and KEY time is kept,
# because the unfiltered moderation list orders by time alone.
#
# pid is the only stored parent relation. Every existing comment stays a root:
# pid is 0 and nothing is ever re-parented from the old "[b]name[/b]," reply
# convention - that text is a naming habit and not a structure, and guessing at
# it would invent threads nobody wrote. Replies, branch paging and tombstones are
# read with recursive CTEs over pid, so no materialised path is stored.
#
# reqkey is the idempotency key of a write and is stored as raw bytes, never as
# hex text: BINARY(16) under one unique index, and absence is NULL rather than a
# shared empty string, because a unique index counts every NULL as distinct. No
# key is minted here for an existing row - a comment written before this release
# was never replayed and needs no key.
#
# ip carries an address and nothing else, so it is compared and sorted byte by
# byte; (ip, time, id) answers the best-effort flood interval on its own and
# replaces the hash column and its rate table alike.
#
# The two stops are deliberate. A reqkey left as hex text means this table was
# already carried through a transitional release, and converting it belongs to
# the dev tooling, not to an upgrade run. A NULL time means a row nothing can
# order; both are reported instead of guessed at.

CALL delcol('{prefix}_comment', 'format');
CALL delcol('{prefix}_comment', 'iphash');
CALL delcol('{prefix}_comment', 'path');

CALL addcol('{prefix}_comment', 'pid',     'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_comment', 'edited',  'DATETIME DEFAULT NULL');
CALL addcol('{prefix}_comment', 'deleted', 'DATETIME DEFAULT NULL');

CALL stopcol('{prefix}_comment', 'reqkey', 'char', 'comment.reqkey is still hex text: convert it with the dev tooling before this upgrade');
CALL addcol('{prefix}_comment', 'reqkey',  'BINARY(16) DEFAULT NULL');

CALL stopnull('{prefix}_comment', 'time', 'comment.time holds NULL rows: repair them before this upgrade');
CALL modcol('{prefix}_comment', 'time', 'DATETIME NOT NULL');
CALL setcoll('{prefix}_comment', 'ip', 'VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'', 'ascii_bin');
CALL poscol('{prefix}_comment', 'pid', 'INT UNSIGNED NOT NULL DEFAULT 0', 'id');

CALL delidx('{prefix}_comment', 'cid');
CALL delidx('{prefix}_comment', 'modul_status');
CALL delidx('{prefix}_comment', 'iphash_time');
CALL delidx('{prefix}_comment', 'modul_cid_path');

CALL addidx('{prefix}_comment', 'reqkey',                   '`reqkey`',                                              1);
CALL addidx('{prefix}_comment', 'modul_cid_status_deleted', '`modul`, `cid`, `status`, `deleted`, `time`, `id`',     0);
CALL addidx('{prefix}_comment', 'modul_cid_deleted',        '`modul`, `cid`, `deleted`, `time`, `id`',               0);
CALL addidx('{prefix}_comment', 'status_deleted_time',      '`status`, `deleted`, `time`, `id`',                     0);
CALL addidx('{prefix}_comment', 'modul_cid_pid_time',       '`modul`, `cid`, `pid`, `time`, `id`',                   0);
CALL addidx('{prefix}_comment', 'ip_time',                  '`ip`, `time`, `id`',                                    0);

DROP TABLE IF EXISTS `{prefix}_comment_rate`;

# =============================================================================
# Batch Z — _maildead and the newsletter campaign state
# =============================================================================
#
# The suppression registry is new in 6.3 and keyed by the normalised address, so
# its unique index is part of the create rather than an addidx: a table that
# exists without it could already hold two spellings of one mailbox.
#
# The email column carries a binary collation on purpose. The normaliser
# lowercases the domain and leaves the local part as it came, and a
# case-insensitive column would fold exactly the distinction it keeps.
#
# _newsletter gains the campaign state machine. The mails column is dropped last
# and only here: setup/index.php reads whatever is still pending out of it before
# this file runs and writes those addresses into the queue afterwards, so the
# drop can never take a mailing with it.

CREATE TABLE IF NOT EXISTS `{prefix}_maildead` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `fails` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `phase` VARCHAR(10) NOT NULL DEFAULT '',
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CALL addcol('{prefix}_newsletter', 'fails',  'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'status', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'audit',  'VARCHAR(100) NOT NULL DEFAULT \'\'');
CALL addcol('{prefix}_newsletter', 'apar',   'VARCHAR(100) NOT NULL DEFAULT \'\'');
CALL addcol('{prefix}_newsletter', 'cursor', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'expect', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'total',  'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'note',   'VARCHAR(255) NOT NULL DEFAULT \'\'');

UPDATE `{prefix}_newsletter` SET `status` = 6 WHERE `status` = 0 AND `send` > 0;

CALL delcol('{prefix}_newsletter', 'mails');

# =============================================================================
# Cleanup
# =============================================================================

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS poscol;
DROP PROCEDURE IF EXISTS setcoll;
DROP PROCEDURE IF EXISTS stopcol;
DROP PROCEDURE IF EXISTS stopnull;
DROP PROCEDURE IF EXISTS delcol;
DROP PROCEDURE IF EXISTS modcol;
DROP PROCEDURE IF EXISTS runifcol;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS mkuseruniq;
DROP PROCEDURE IF EXISTS mksessuniq;
DROP PROCEDURE IF EXISTS fixgrppk;
DROP PROCEDURE IF EXISTS finalize_user_names;

# =============================================================================
# Manual follow-up after this script
# =============================================================================
#
# 1. Legacy side tables handled by this script:
#    - `{prefix}_money`        — columns renamed, types aligned, indexes added
#    - `{prefix}_clients_down` — columns renamed, types aligned, indexes added
#    - `{prefix}_modules` is outside this migration
#
# 2. Password hashing migration (PHP side — already done in current codebase):
#    - passwords are now stored as bcrypt via getPassHash() / password_hash()
#    - existing md5_salt hashes are upgraded transparently on next user login
#    - no bulk SQL re-hash needed

# =============================================================================
# Avatars moved into the themes (templates/<theme>/images/avatars/)
# Old stored values `default/NN.<ext>` map to theme presets `presets/NN.svg`;
# legacy defaults without a matching preset are cleared to the user.svg fallback.
# Both statements are idempotent: a re-run affects zero rows.
# =============================================================================

UPDATE `{prefix}_users` SET `avatar` = CONCAT('presets/', SUBSTRING(`avatar`, 9, 2), '.svg') WHERE `avatar` REGEXP '^default/[0-9]{2}[.](gif|png|jpg|jpeg|svg)$' AND SUBSTRING(`avatar`, 9, 2) BETWEEN '01' AND '56';

UPDATE `{prefix}_users` SET `avatar` = '' WHERE `avatar` LIKE 'default/%';

# =============================================================================
# OAuth2/OIDC login replaces the removed uLogin integration
# - two new tables: {prefix}_oauth_temp (one-time state/pending records with
#   TTL cleanup on access) and {prefix}_user_oauth (permanent provider links)
# - legacy uLogin identities are archived as provider 'ulogin' rows in
#   {prefix}_user_oauth, then the obsolete users.network column is dropped;
#   affected accounts regain access through the standard password recovery
# - the whole block is idempotent: a re-run affects zero rows
# =============================================================================

CREATE TABLE IF NOT EXISTS `{prefix}_oauth_temp` (
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

CREATE TABLE IF NOT EXISTS `{prefix}_user_oauth` (
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

DROP PROCEDURE IF EXISTS movenet;

DELIMITER $$

CREATE PROCEDURE movenet(IN pusers VARCHAR(128), IN plinks VARCHAR(128))
BEGIN
    DECLARE ccol INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ccol
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = pusers
       AND column_name = 'network';

    IF ccol > 0 THEN
        SET @sql = CONCAT(
            'INSERT IGNORE INTO `', plinks, '` (`uid`, `provider`, `puid`, `email`, `linked`) ',
            'SELECT `id`, ''ulogin'', `network`, `email`, UNIX_TIMESTAMP() ',
            'FROM `', pusers, '` WHERE `network` != '''''
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET @sql = CONCAT('ALTER TABLE `', pusers, '` DROP COLUMN `network`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL movenet('{prefix}_users', '{prefix}_user_oauth');

DROP PROCEDURE IF EXISTS movenet;
