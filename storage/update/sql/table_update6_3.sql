# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0.16+ & MariaDB 10.5.2+
#
# table_update6_3.sql — update from SLAED 6.2 to SLAED 6.3 Phoenix
#
# Purpose:
# - migrate a live SLAED 6.2 database to the normalized schema used by 6.3
# - survive partial/manual refactors without crashing on already-renamed objects
# - the procedures skip a missing table silently; a plain statement (ALTER ... MODIFY, UPDATE, DELETE) needs its table and fails its report row
#
# Usage:
# - executed by update.php, the internal update tool of the maintainer that the release does not ship
# - back up the database before running the updater
# - safe to re-run for idempotent rename/index batches below
#
# Important:
# - this script covers column/index migration first
# - then it performs minimal table-level reconciliation required by current code
# - legacy source tables are not dropped automatically

# MySQL 8 refuses by default every ALTER of a 6.2 table whose datetime columns default to a zero date (NO_ZERO_DATE, NO_ZERO_IN_DATE)
# The file runs without both, as MariaDB does by default, and gives the session its own mode back at the end
SET @SLAED_MODE = @@SESSION.sql_mode;
SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(REPLACE(REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', ''), ',,', ','), ',,', ','));

DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS poscol;
DROP PROCEDURE IF EXISTS setcoll;
DROP PROCEDURE IF EXISTS stopcol;
DROP PROCEDURE IF EXISTS stopnull;
DROP PROCEDURE IF EXISTS delcol;
DROP PROCEDURE IF EXISTS modcol;
DROP PROCEDURE IF EXISTS runifcol;
DROP PROCEDURE IF EXISTS runiftype;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS setidx;
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

# The default a moment reads back is current_timestamp() on MariaDB and CURRENT_TIMESTAMP with DEFAULT_GENERATED on MySQL; modcol reads both as the definition
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

        SET cextr = TRIM(REPLACE(IFNULL(cextr, ''), 'DEFAULT_GENERATED', ''));
        SET have = UPPER(CONCAT(
            ctype,
            IF(cnull = 'NO', ' NOT NULL', ' NULL'),
            IF(cdflt IS NULL, '', CONCAT(' DEFAULT ', REPLACE(UPPER(cdflt), 'CURRENT_TIMESTAMP()', 'CURRENT_TIMESTAMP'))),
            IF(cextr = '', '', CONCAT(' ', cextr))
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

CREATE PROCEDURE runiftype(IN ptab VARCHAR(128), IN pcol VARCHAR(128), IN ptype VARCHAR(64), IN psql TEXT)
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

CREATE PROCEDURE setidx(IN ptab VARCHAR(128), IN pidx VARCHAR(128), IN pexp TEXT, IN puni TINYINT)
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE cdef TEXT DEFAULT NULL;
    DECLARE cuni INT DEFAULT 0;

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab;

    IF ctab > 0 THEN
        SELECT GROUP_CONCAT(CONCAT('`', column_name, '`', IFNULL(CONCAT('(', sub_part, ')'), ''), IF(collation = 'D', ' DESC', ''))
                   ORDER BY seq_in_index SEPARATOR ', '),
               MIN(non_unique)
          INTO cdef, cuni
          FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = ptab
           AND index_name = pidx;

        IF cdef IS NULL OR BINARY cdef <> BINARY pexp OR cuni <> 1 - puni THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', ptab, '` ',
                IF(cdef IS NULL, '', CONCAT('DROP INDEX `', pidx, '`, ')),
                IF(puni = 1, 'ADD UNIQUE KEY', 'ADD KEY'), ' `', pidx, '` (', pexp, ')'
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
    DECLARE ccoll VARCHAR(64) DEFAULT '';

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

            # The names take the collation of the column, whatever the default of the database is, so the join below compares like with like
            SELECT collation_name
              INTO ccoll
              FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ptab
               AND column_name = 'name';

            SET @sql = CONCAT(
                'CREATE TEMPORARY TABLE tmp_user_name_fix (id INT UNSIGNED NOT NULL PRIMARY KEY, ',
                'old_name VARCHAR(25) COLLATE ', ccoll, ' NOT NULL, new_name VARCHAR(25) COLLATE ', ccoll, ' NOT NULL) ENGINE=MEMORY'
            );
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;

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
UPDATE `{prefix}_blocks` SET bfile = REPLACE(bfile, 'block-', '') WHERE bfile LIKE 'block-%';
CALL addcol('{prefix}_blocks', 'param', 'VARCHAR(255) NOT NULL DEFAULT \'\'');

# The banner positions b and f are gone: their rows stand at the end of the centre below, those of b first, each in its order
SET @SLAED_DOWN = (SELECT COALESCE(MAX(weight), 0) FROM `{prefix}_blocks` WHERE bpos = 'd');
UPDATE `{prefix}_blocks` b JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY bpos = 'f', weight, id) AS num FROM `{prefix}_blocks` WHERE bpos IN ('b', 'f')) n ON n.id = b.id
SET b.bpos = 'd', b.weight = @SLAED_DOWN + n.num;

# =============================================================================
# Batch D — _categories
# =============================================================================

CALL rencol('{prefix}_categories', 'parentid', 'parent');
CALL rencol('{prefix}_categories', 'cstatus', 'status');
CALL rencol('{prefix}_categories', 'lpost_id', 'lpost');
CALL renidx('{prefix}_categories', 'parentid', 'parent');
CALL addidx('{prefix}_categories', 'parent', '`parent`', 0);
CALL addidx('{prefix}_categories', 'ordern', '`ordern`', 0);

# =============================================================================
# Batch F — _comment
# =============================================================================

CALL rencol('{prefix}_comment', 'date', 'time');
CALL rencol('{prefix}_comment', 'host_name', 'ip');
CALL renidx('{prefix}_comment', 'date', 'time');
# cid and modul_status are not created here: batch Y below adds the composites that supersede them and drops both

# =============================================================================
# Batch G — _favorites
# =============================================================================

CALL addidx('{prefix}_favorites', 'fid',          '`fid`',                     0);
CALL addidx('{prefix}_favorites', 'modul',        '`modul`',                   0);
CALL addidx('{prefix}_favorites', 'uid_fid_modul','`uid`, `fid`, `modul`',     1);
CALL addcol('{prefix}_favorites', 'time',         'DATETIME DEFAULT NULL');

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
CALL addidx('{prefix}_forum', 'status', '`status`', 0);
CALL addidx('{prefix}_forum', 'time', '`time`', 0);

# =============================================================================
# Batch J — _message
# =============================================================================

CALL rencol('{prefix}_message', 'mid', 'id');
CALL rencol('{prefix}_message', 'active', 'status');
CALL rencol('{prefix}_message', 'mlanguage', 'lang');
CALL renidx('{prefix}_message', 'active', 'status');
CALL renidx('{prefix}_message', 'mlanguage', 'lang');
CALL addidx('{prefix}_message', 'status', '`status`', 0);

# =============================================================================
# Batch J — _points
# =============================================================================

# The journal of the points subsystem; the balance stays in _users.points and no historic row is invented
CREATE TABLE IF NOT EXISTS `{prefix}_points` (
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
  CONSTRAINT `{prefix}_fk_points_rid` FOREIGN KEY (`rid`) REFERENCES `{prefix}_points` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `{prefix}_chk_points_uid` CHECK (`uid` > 0)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# =============================================================================
# Batch K — _privat
# =============================================================================
#
# The single status column becomes four independent states: viewed and saved belong to the recipient,
# delin and delout to the two mailbox sides, so what one participant deletes or saves no longer rewrites
# what the other one sees
# The order is load-bearing. The backfill has to read status before the rename consumes it, which is what
# runifcol() executes it under: once the rename has happened the column is gone and the step can never run
# a second time. rencol() keeps the BOOLEAN the old column was declared as, so modcol() forces viewed onto
# the definition the fresh schema gives it and leaves the table alone when it already carries it
# The composites are added last and time keeps its place in front of them, which is the order
# storage/update/sql/table.sql declares, so an upgraded table and a fresh one are the same table
# No index on status is created here any more: this file used to add one and now drops it, on a column
# that does not survive the batch
# out_new and flood answer the two reads out_box cannot: the outgoing unread counter of the sidebar block,
# which filters a column out_box does not carry, and the send interval, which filters no state at all and
# orders by time - measured on real data, the first was a full table scan and the second a filesort
#
# A body is stored as the source its author wrote and is rendered through one contract, so no column names
# a storage format: plain and the editors are input interfaces, and rendering never branches on them

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
CALL modcol('{prefix}_privat', 'time', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

# =============================================================================
# Batch K — _rating
# =============================================================================

# 6.2 kept one row per account and target in UNIQUE mid_modul_uid, which let a single guest vote per poll;
# 6.3 keeps one row per address and target, as the guards of 6.2 already did in code
# Two accounts of one address that voted for one target collide on that key: the earliest vote stays, and the installer names the count of removed rows
CALL rencol('{prefix}_rating', 'host', 'ip');
DELETE FROM `{prefix}_rating` WHERE `id` IN (
  SELECT `id` FROM (
    SELECT r.`id` FROM `{prefix}_rating` r
    JOIN `{prefix}_rating` k ON k.`mid` = r.`mid` AND k.`modul` = r.`modul` AND k.`ip` = r.`ip` AND k.`id` < r.`id`
  ) AS d
);
CALL renidx('{prefix}_rating', 'mid_modul_host', 'mid_modul_ip');
CALL delidx('{prefix}_rating', 'mid_modul_uid');
CALL addidx('{prefix}_rating', 'modul', '`modul`', 0);
CALL addidx('{prefix}_rating', 'mid_modul_ip', '`mid`, `modul`, `ip`', 1);

# The three tables of the rating subsystem; the ratings unit of the data update fills the targets and the actors, and no historic vote is invented
CREATE TABLE IF NOT EXISTS `{prefix}_rating_actors` (
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `actor` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `last` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`scope`, `mid`, `actor`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_rating_targets` (
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `base` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `votes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`scope`, `mid`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_rating_votes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL,
  `actor` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `value` TINYINT UNSIGNED NOT NULL,
  `request` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `annulled` DATETIME DEFAULT NULL,
  `aid` INT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `request` (`scope`, `mid`, `actor`, `request`),
  KEY `target` (`scope`, `mid`, `id`),
  KEY `scope` (`scope`, `id`),
  CONSTRAINT `{prefix}_chk_rating_votes_value` CHECK (`value` BETWEEN 1 AND 5)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# A build of early October created the three tables with timers for moments: each passes a text column, takes its moment and returns to DATETIME
# A timer 0 is no moment: a creation takes the time of the run, an annulment none; a second run finds DATETIME and changes nothing
CALL modcol('{prefix}_rating_actors', 'last', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
CALL runiftype('{prefix}_rating_targets', 'created', 'bigint', 'ALTER TABLE `{prefix}_rating_targets` MODIFY `created` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_rating_targets', 'created', 'varchar', 'UPDATE `{prefix}_rating_targets` SET `created` = CASE
    WHEN `created` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`created`), \'%Y-%m-%d %H:%i:%s\')
    WHEN `created` REGEXP \'^[0-9]{4}-\' THEN `created`
    ELSE DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')
    END');
CALL modcol('{prefix}_rating_targets', 'created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL runiftype('{prefix}_rating_votes', 'created', 'bigint', 'ALTER TABLE `{prefix}_rating_votes` MODIFY `created` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_rating_votes', 'created', 'varchar', 'UPDATE `{prefix}_rating_votes` SET `created` = CASE
    WHEN `created` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`created`), \'%Y-%m-%d %H:%i:%s\')
    WHEN `created` REGEXP \'^[0-9]{4}-\' THEN `created`
    ELSE DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')
    END');
CALL modcol('{prefix}_rating_votes', 'created', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL runiftype('{prefix}_rating_votes', 'annulled', 'bigint', 'ALTER TABLE `{prefix}_rating_votes` MODIFY `annulled` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_rating_votes', 'annulled', 'varchar', 'UPDATE `{prefix}_rating_votes` SET `annulled` = CASE
    WHEN `annulled` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`annulled`), \'%Y-%m-%d %H:%i:%s\')
    WHEN `annulled` REGEXP \'^[0-9]{4}-\' THEN `annulled`
    END');
CALL modcol('{prefix}_rating_votes', 'annulled', 'DATETIME DEFAULT NULL');

# =============================================================================
# Batch K — _referer
# =============================================================================

DROP TABLE IF EXISTS `{prefix}_referer`;

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
CALL addidx('{prefix}_search', 'time', '`time`', 0);

# =============================================================================
# Batch K — _session
# =============================================================================

CALL rencol('{prefix}_session', 'host_addr', 'ip');
CALL renidx('{prefix}_session', 'host_addr', 'ip');
CALL mksessuniq('{prefix}_session');
CALL addidx('{prefix}_session', 'time', '`time`', 0);

# =============================================================================
# Batch K — _voting
# =============================================================================

CALL rencol('{prefix}_voting', 'date', 'time');
CALL addidx('{prefix}_voting', 'modul', '`modul`', 0);

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

# =============================================================================
# Batch N — structural fixes not covered in Batches A–L
# Run AFTER Batches A–L; safe to re-run (idempotent via IF EXISTS checks)
# =============================================================================

# _groups: restore PRIMARY KEY lost during column migration on some engines
# fixgrppk handles all 4 states via information_schema (MySQL 8.0+ & MariaDB 10+ compatible)
CALL fixgrppk('{prefix}_groups');

# _users: normalize legacy zero-dates before changing column defaults
UPDATE `{prefix}_users` SET `regdate` = '1970-01-01 00:00:01' WHERE `regdate` = '0000-00-00 00:00:00';
UPDATE `{prefix}_users` SET `lastvis` = '1970-01-01 00:00:01' WHERE `lastvis` = '0000-00-00 00:00:00';
# A 6.2 site may hold NULL where the statement below demands NOT NULL, which strict mode refuses: every NULL takes the default of its column
UPDATE `{prefix}_users` SET
  `password` = COALESCE(`password`, ''), `ip` = COALESCE(`ip`, ''), `regdate` = COALESCE(`regdate`, '1970-01-01 00:00:01'),
  `newslet` = COALESCE(`newslet`, 1), `fsmail` = COALESCE(`fsmail`, 1),
  `psmail` = COALESCE(`psmail`, 1), `points` = COALESCE(`points`, 0), `votes` = COALESCE(`votes`, 0), `tvotes` = COALESCE(`tvotes`, 0),
  `storynum` = COALESCE(`storynum`, 10), `grp` = COALESCE(`grp`, 0), `rank` = COALESCE(`rank`, '')
WHERE `password` IS NULL OR `ip` IS NULL OR `regdate` IS NULL OR `newslet` IS NULL OR `fsmail` IS NULL OR `psmail` IS NULL
  OR `points` IS NULL OR `votes` IS NULL OR `tvotes` IS NULL OR `storynum` IS NULL OR `grp` IS NULL OR `rank` IS NULL;
# A group below zero names none, as 0 does, before the column turns unsigned
UPDATE `{prefix}_users` SET `grp` = 0 WHERE `grp` < 0;

# _users: widen critical columns and fix types / defaults
# network is deliberately absent here: the OAuth block at the end of this file archives it and drops it,
# so touching it would make this statement - and with it the thirteen columns below - fail on every re-run
ALTER TABLE `{prefix}_users`
  MODIFY `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `password`  VARCHAR(255) NOT NULL,
  MODIFY `ip`        VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `regdate`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `lastvis`   DATETIME DEFAULT NULL,
  MODIFY `newslet`   TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `fsmail`    TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `psmail`    TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY `points`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `votes`     INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `tvotes`    INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `storynum`  TINYINT UNSIGNED NOT NULL DEFAULT 10,
  MODIFY `grp`       INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `rank`      VARCHAR(25) NOT NULL DEFAULT '';

# A user who never came back is NULL, not the sentinel the zero date of 6.2 was carried through above
UPDATE `{prefix}_users` SET `lastvis` = NULL WHERE `lastvis` = '1970-01-01 00:00:01';

# =============================================================================
# Batch O: field naming unification — step 1
# =============================================================================

CALL rencol('{prefix}_admins',   'pwd',    'password');
CALL rencol('{prefix}_admins',   'lvisit', 'lastvis');
CALL renidx('{prefix}_admins',   'lvisit', 'lastvis');
CALL rencol('{prefix}_session',  'module', 'modul');

# =============================================================================
# Batch P: field naming unification — step 2 (language fields)
# =============================================================================

CALL rencol('{prefix}_blocks',     'blang',    'lang');
CALL rencol('{prefix}_categories', 'language', 'lang');
CALL rencol('{prefix}_voting',     'language', 'lang');
CALL renidx('{prefix}_blocks',     'blang',    'lang');
CALL renidx('{prefix}_voting',     'language', 'lang');

# =============================================================================
# Batch S: content column renames — step 5
#   _forum:    hometext → body
#   _comment:  comment  → body
# =============================================================================

CALL rencol('{prefix}_forum',    'hometext',    'body');
CALL rencol('{prefix}_comment',  'comment',     'body');

# =============================================================================
# Batch T: content column renames — remaining tables
#   _categories: description → intro
#   _groups:     description → intro
# =============================================================================

CALL rencol('{prefix}_categories', 'description', 'intro');
CALL rencol('{prefix}_groups',     'description', 'intro');

# =============================================================================
# Batch V: naming consistency — url, email, website, pview/pread/... columns
# =============================================================================

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
#   _message:      content    → body
#   _newsletter:   content    → body
#   _privat:       content    → body
#   _voting:       questions  → body
# =============================================================================

CALL rencol('{prefix}_message',      'content',    'body');
CALL rencol('{prefix}_newsletter',   'content',    'body');
CALL rencol('{prefix}_privat',       'content',    'body');
CALL rencol('{prefix}_voting',       'questions',  'body');

# =============================================================================
# Batch X: the source tables of Node
#   The migration of update.php reads news, pages, faq, help, links, files, content, jokes and media in the 6.3 shape
#   A clean 6.2 site still carries the 6.2 names; a table already in the 6.3 shape is left as it is
# =============================================================================

CALL rencol('{prefix}_news',     'sid',           'id');
CALL rencol('{prefix}_news',     'catid',         'cid');
CALL rencol('{prefix}_news',     'hometext',      'intro');
CALL rencol('{prefix}_news',     'bodytext',      'body');
CALL rencol('{prefix}_news',     'associated',    'assoc');
CALL rencol('{prefix}_news',     'ip_sender',     'ip');

CALL rencol('{prefix}_pages',    'pid',           'id');
CALL rencol('{prefix}_pages',    'catid',         'cid');
CALL rencol('{prefix}_pages',    'hometext',      'intro');
CALL rencol('{prefix}_pages',    'bodytext',      'body');
CALL rencol('{prefix}_pages',    'ip_sender',     'ip');

CALL rencol('{prefix}_faq',      'fid',           'id');
CALL rencol('{prefix}_faq',      'catid',         'cid');
CALL rencol('{prefix}_faq',      'hometext',      'body');
CALL rencol('{prefix}_faq',      'ip_sender',     'ip');

CALL rencol('{prefix}_help',     'sid',           'id');
CALL rencol('{prefix}_help',     'catid',         'cid');
CALL rencol('{prefix}_help',     'hometext',      'body');
CALL rencol('{prefix}_help',     'ip_sender',     'ip');

CALL rencol('{prefix}_links',    'lid',           'id');
CALL rencol('{prefix}_links',    'description',   'intro');
CALL rencol('{prefix}_links',    'bodytext',      'body');
CALL rencol('{prefix}_links',    'date',          'time');
CALL rencol('{prefix}_links',    'ip_sender',     'ip');
CALL rencol('{prefix}_links',    'totalvotes',    'tvotes');
CALL rencol('{prefix}_links',    'totalcomments', 'comments');

CALL rencol('{prefix}_files',    'lid',           'id');
CALL rencol('{prefix}_files',    'description',   'intro');
CALL rencol('{prefix}_files',    'bodytext',      'body');
CALL rencol('{prefix}_files',    'date',          'time');
CALL rencol('{prefix}_files',    'homepage',      'website');
CALL rencol('{prefix}_files',    'ip_sender',     'ip');
CALL rencol('{prefix}_files',    'totalvotes',    'tvotes');
CALL rencol('{prefix}_files',    'totalcomments', 'comments');

CALL rencol('{prefix}_content',  'text',          'body');

CALL rencol('{prefix}_jokes',    'jokeid',        'id');
CALL rencol('{prefix}_jokes',    'date',          'time');
CALL rencol('{prefix}_jokes',    'cat',           'cid');
CALL rencol('{prefix}_jokes',    'joke',          'body');
CALL rencol('{prefix}_jokes',    'ratingtot',     'ratetot');
CALL rencol('{prefix}_jokes',    'ip_sender',     'ip');

CALL rencol('{prefix}_media',    'description',   'intro');
CALL rencol('{prefix}_media',    'createdby',     'author');
CALL rencol('{prefix}_media',    'date',          'time');
CALL rencol('{prefix}_media',    'totalvotes',    'tvotes');
CALL rencol('{prefix}_media',    'totalcom',      'comments');
CALL rencol('{prefix}_media',    'ip_sender',     'ip');

# =============================================================================
# Final type alignment to storage/update/sql/table.sql
# =============================================================================

# _admins.editor names the editor plugin an administrator writes with and has done since the editor
# rework; it is not a flag. A legacy installation still carries the boolean it was: a NULL would refuse
# the NOT NULL under strict mode, so it becomes 0 before the column is widened, which the TINYINT of 6.2
# accepts where it refuses a word; after the widening the 0 or 1 of the old shape, which names no plugin
# and which getEditorKey() resolves back to plain anyway, is written as plain
UPDATE `{prefix}_admins` SET `editor` = 0 WHERE `editor` IS NULL;
# NULL in the title, the password and the two flags of 6.2 says nothing the empty value does not: the columns turn NOT NULL
UPDATE `{prefix}_admins` SET `title` = COALESCE(`title`, ''), `password` = COALESCE(`password`, ''), `super` = COALESCE(`super`, 0), `smail` = COALESCE(`smail`, 0)
WHERE `title` IS NULL OR `password` IS NULL OR `super` IS NULL OR `smail` IS NULL;

ALTER TABLE `{prefix}_admins`
  MODIFY `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`     VARCHAR(25) NOT NULL,
  MODIFY `title`    VARCHAR(50) NOT NULL DEFAULT '',
  MODIFY `url`      VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `email`    VARCHAR(255) DEFAULT NULL,
  MODIFY `password` VARCHAR(255) NOT NULL,
  MODIFY `super`    BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `editor`   VARCHAR(32) NOT NULL DEFAULT 'plain',
  MODIFY `smail`    BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `modules`  TEXT NOT NULL,
  MODIFY `lang`     VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `ip`       VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `regdate`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `lastvis`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE `{prefix}_admins` SET `editor` = 'plain' WHERE `editor` IN ('', '0', '1');

ALTER TABLE `{prefix}_blocks`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(60) NOT NULL,
  MODIFY `content` TEXT NOT NULL,
  MODIFY `url` VARCHAR(200) NOT NULL DEFAULT '',
  MODIFY `bpos` CHAR(1) NOT NULL DEFAULT '',
  MODIFY `weight` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `refresh` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `lang` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `bfile` VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `view` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `action` CHAR(1) NOT NULL DEFAULT '',
  MODIFY `which` TEXT NOT NULL;

# The refresh stamp of a feed block is unix seconds: an empty or a zero text of 6.2 becomes 0 before the column turns numeric
CALL runiftype('{prefix}_blocks', 'time', 'varchar', 'UPDATE `{prefix}_blocks` SET `time` = \'0\' WHERE `time` NOT REGEXP \'^[0-9]{1,19}$\'');
CALL modcol('{prefix}_blocks', 'time', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

# The expiry of a block becomes the moment it ends, NULL for none: the unix text passes through its digits in the zone of the site, a second run finds a DATETIME
CALL runiftype('{prefix}_blocks', 'expire', 'varchar', 'ALTER TABLE `{prefix}_blocks` MODIFY `expire` VARCHAR(14) NULL DEFAULT NULL');
CALL runiftype('{prefix}_blocks', 'expire', 'varchar', 'UPDATE `{prefix}_blocks` SET `expire` = CASE WHEN `expire` REGEXP \'^[0-9]{14}$\' THEN `expire` WHEN `expire` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`expire`), \'%Y%m%d%H%i%s\') END');
CALL modcol('{prefix}_blocks', 'expire', 'DATETIME DEFAULT NULL');

ALTER TABLE `{prefix}_categories`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `intro` TEXT NOT NULL,
  MODIFY `img` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `lang` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `parent` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `ordern` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `topics` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `posts` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `lpost` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `pview` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `pread` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `ppost` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `preply` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `pedit` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `pdelete` VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `pmod` VARCHAR(100) NOT NULL DEFAULT '';

# An older 6.2 schema lets a comment author be NULL, which the unsigned NOT NULL below refuses under strict mode
UPDATE `{prefix}_comment` SET `uid` = 0 WHERE `uid` IS NULL;

# Every column widened to MEDIUMTEXT in this section is written through the rich editor, and the
# editor can embed an image into the text instead of storing a file: EMBEDMAX caps a data URI at
# 65536 bytes of binary, which is 87384 characters of base64 before the prefix and the markdown,
# while TEXT holds 65535. With STRICT_TRANS_TABLES that is a lost post, not a lost image. The
# summary columns (`intro`, and `users.block`) stay TEXT on purpose: a list query draws twenty of
# them onto one page, and an image referenced by address or uploaded to the server still fits
# there. users.sig widens only to TEXT and for a reason of its own: a
# VARCHAR(255) behind a rich editor holds about 127 Cyrillic characters, which is one sentence
ALTER TABLE `{prefix}_comment`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_favorites`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `fid` INT UNSIGNED NOT NULL DEFAULT 0;

CALL stopnull('{prefix}_forum', 'time', 'forum.time holds NULL rows: repair them before this upgrade');
UPDATE `{prefix}_forum` SET `ltime` = `time` WHERE `ltime` IS NULL;
UPDATE `{prefix}_forum` SET `body` = COALESCE(`body`, ''), `comments` = COALESCE(`comments`, 0) WHERE `body` IS NULL OR `comments` IS NULL;

ALTER TABLE `{prefix}_forum`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `ltime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `name` VARCHAR(25) NOT NULL,
  MODIFY `title` VARCHAR(100) NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `field` MEDIUMTEXT NOT NULL,
  MODIFY `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `counter` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `score` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ratings` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `luid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `lpost` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `euid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `eip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

ALTER TABLE `{prefix}_groups`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`   VARCHAR(255) NOT NULL,
  MODIFY `intro`  TEXT NOT NULL,
  MODIFY `points` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `extra`  BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `rank`   VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `color`  VARCHAR(7) NOT NULL DEFAULT '';

ALTER TABLE `{prefix}_message`
  MODIFY `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title`  VARCHAR(100) NOT NULL,
  MODIFY `body`   MEDIUMTEXT NOT NULL,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `view`   BOOLEAN NOT NULL DEFAULT 1,
  MODIFY `lang`   VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '';

# The expiry of a message becomes the moment it ends, NULL for none, by the same path as the expiry of a block
CALL runiftype('{prefix}_message', 'expire', 'int', 'ALTER TABLE `{prefix}_message` MODIFY `expire` VARCHAR(14) NULL DEFAULT NULL');
CALL runiftype('{prefix}_message', 'expire', 'varchar', 'UPDATE `{prefix}_message` SET `expire` = CASE WHEN `expire` REGEXP \'^[0-9]{14}$\' THEN `expire` WHEN `expire` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`expire`), \'%Y%m%d%H%i%s\') END');
CALL modcol('{prefix}_message', 'expire', 'DATETIME DEFAULT NULL');

CALL stopnull('{prefix}_newsletter', 'time', 'newsletter.time holds NULL rows: repair them before this upgrade');
UPDATE `{prefix}_newsletter` SET `body` = '' WHERE `body` IS NULL;

ALTER TABLE `{prefix}_newsletter`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `title` VARCHAR(50) NOT NULL,
  MODIFY `body` MEDIUMTEXT NOT NULL,
  MODIFY `send` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

# The message table is reconciled column by column: a bare MODIFY rebuilds the table even when nothing changes, and an installation already on 6.3 must not be rewritten
CALL modcol('{prefix}_privat', 'id',    'INT UNSIGNED NOT NULL AUTO_INCREMENT');
CALL modcol('{prefix}_privat', 'title', 'VARCHAR(100) NOT NULL');
CALL modcol('{prefix}_privat', 'body',  'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_privat', 'uidin', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL modcol('{prefix}_privat', 'uidout', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL setcoll('{prefix}_privat', 'ip', 'VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'', 'ascii_bin');

# The time of a poll vote is unix seconds: a text that is no number becomes 0, which the ratings unit of update.php reports as broken like before
CALL runiftype('{prefix}_rating', 'time', 'varchar', 'UPDATE `{prefix}_rating` SET `time` = \'0\' WHERE `time` NOT REGEXP \'^[0-9]{1,19}$\'');
CALL modcol('{prefix}_rating', 'time', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

ALTER TABLE `{prefix}_rating`
  MODIFY `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `mid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '';

UPDATE `{prefix}_session` SET `url` = '' WHERE `url` IS NULL;

ALTER TABLE `{prefix}_search`
  MODIFY `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `score` INT UNSIGNED NOT NULL DEFAULT 0;

# An older 6.2 schema holds the module of an online visitor in 100 characters, the release in 50; a longer name of a transient row is cut
UPDATE `{prefix}_session` SET `modul` = LEFT(`modul`, 50) WHERE CHAR_LENGTH(`modul`) > 50;

ALTER TABLE `{prefix}_session`
  MODIFY `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `url` VARCHAR(2048) NOT NULL DEFAULT '',
  MODIFY `uname` VARCHAR(45) NOT NULL,
  MODIFY `time` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `guest` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

# NULL-safety before widening _users columns (may be NULL in legacy schema)
UPDATE `{prefix}_users` SET `website`  = '' WHERE `website`  IS NULL;
UPDATE `{prefix}_users` SET `avatar`   = '' WHERE `avatar`   IS NULL;
UPDATE `{prefix}_users` SET `block`    = '' WHERE `block`    IS NULL;
UPDATE `{prefix}_users` SET `warnings` = '' WHERE `warnings` IS NULL;
UPDATE `{prefix}_users` SET `field`    = '' WHERE `field`    IS NULL;
UPDATE `{prefix}_users` SET `interest` = '' WHERE `interest` IS NULL;
UPDATE `{prefix}_users` SET `theme`    = '' WHERE `theme`    IS NULL;
UPDATE `{prefix}_users` SET `agent`    = '' WHERE `agent`    IS NULL;
UPDATE `{prefix}_users` SET `occ`      = '' WHERE `occ`      IS NULL;
UPDATE `{prefix}_users` SET `origin`   = '' WHERE `origin`   IS NULL;
UPDATE `{prefix}_users` SET `sig`      = '' WHERE `sig`      IS NULL;
# The public profile hides the address of a NULL row, so NULL is the 0 of a hidden address
UPDATE `{prefix}_users` SET `viewmail` = 0  WHERE `viewmail` IS NULL;
# A language is a code of lang/ or, before update.php turns the 6.2 names into codes, a name of 6.2; anything else is no choice and cannot narrow to ASCII
UPDATE `{prefix}_users` SET `lang`     = '' WHERE `lang` NOT REGEXP '^[A-Za-z]{2,10}$';

ALTER TABLE `{prefix}_users`
  MODIFY `name`     VARCHAR(25) NOT NULL,
  MODIFY `email`    VARCHAR(255) DEFAULT NULL,
  MODIFY `website`  VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `avatar`   VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `occ`      VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `origin`   VARCHAR(100) NOT NULL DEFAULT '',
  MODIFY `interest` VARCHAR(150) NOT NULL DEFAULT '',
  MODIFY `sig`      TEXT NOT NULL,
  MODIFY `viewmail` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `blockon`  BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `block`    TEXT NOT NULL,
  MODIFY `theme`    VARCHAR(255) NOT NULL DEFAULT '',
  MODIFY `lang`     VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `access`   BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `birthday` DATE DEFAULT NULL,
  MODIFY `gender`   BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `warnings` TEXT NOT NULL,
  MODIFY `field`    MEDIUMTEXT NOT NULL,
  MODIFY `agent`    VARCHAR(255) NOT NULL DEFAULT '';

# The date filter wrote today into an empty birthday field, so a birthday on the day of the registration is none
UPDATE `{prefix}_users` SET `birthday` = NULL WHERE `birthday` = DATE(`regdate`);

# A missing address is NULL, which a unique key takes many times, an empty string only once
UPDATE `{prefix}_admins` SET `email` = NULL WHERE `email` = '';
UPDATE `{prefix}_users` SET `email` = NULL WHERE `email` = '';

UPDATE `{prefix}_users_temp` SET `regdate` = '1970-01-01 00:00:01' WHERE `regdate` = '0000-00-00 00:00:00';

ALTER TABLE `{prefix}_users_temp`
  MODIFY `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `name`    VARCHAR(25) NOT NULL,
  MODIFY `email`   VARCHAR(255) DEFAULT NULL,
  MODIFY `password` VARCHAR(255) NOT NULL,
  MODIFY `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `code`    VARCHAR(50) NOT NULL;
UPDATE `{prefix}_users_temp` SET `email` = NULL WHERE `email` = '';

# The unix time of a registration repeats its regdate, which the cleanup of the activation reads instead
CALL delcol('{prefix}_users_temp', 'time');

CALL stopnull('{prefix}_voting', 'time', 'voting.time holds NULL rows: repair them before this upgrade');
UPDATE `{prefix}_voting` SET `enddate` = `time` WHERE `enddate` IS NULL;

ALTER TABLE `{prefix}_voting`
  MODIFY `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY `enddate` DATETIME NOT NULL,
  MODIFY `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY `body` TEXT NOT NULL,
  MODIFY `answer` TEXT NOT NULL,
  MODIFY `multi` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `comments` INT UNSIGNED NOT NULL DEFAULT 0,
  MODIFY `lang` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `acomm` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  MODIFY `typ` BOOLEAN NOT NULL DEFAULT 0,
  MODIFY `status` BOOLEAN NOT NULL DEFAULT 0;

# =============================================================================
# Batch X — _mail, the outgoing mail queue
# =============================================================================
#
# The table is new in 6.3 and has no legacy source, so the create carries the
# primary key alone and every secondary index is added through addidx. An
# installation whose upgrade stopped midway therefore gains only the indexes it
# is still missing instead of failing on the ones it already has

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
  `ntime` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `tries` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `camp` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `hold` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked` BIGINT UNSIGNED NOT NULL DEFAULT 0,
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

# A build of early October kept both timers of the queue as DATETIME: each passes a text column, takes its timer and returns to BIGINT; no moment is a timer 0
CALL runiftype('{prefix}_mail', 'ntime', 'datetime', 'ALTER TABLE `{prefix}_mail` MODIFY `ntime` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_mail', 'ntime', 'varchar', 'UPDATE `{prefix}_mail` SET `ntime` = CASE
    WHEN `ntime` REGEXP \'^[0-9]{4}-\' THEN UNIX_TIMESTAMP(CAST(`ntime` AS DATETIME))
    WHEN `ntime` REGEXP \'^[0-9]{1,19}$\' THEN `ntime`
    ELSE \'0\'
    END');
CALL modcol('{prefix}_mail', 'ntime', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
CALL runiftype('{prefix}_mail', 'locked', 'datetime', 'ALTER TABLE `{prefix}_mail` MODIFY `locked` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_mail', 'locked', 'varchar', 'UPDATE `{prefix}_mail` SET `locked` = CASE
    WHEN `locked` REGEXP \'^[0-9]{4}-\' THEN UNIX_TIMESTAMP(CAST(`locked` AS DATETIME))
    WHEN `locked` REGEXP \'^[0-9]{1,19}$\' THEN `locked`
    ELSE \'0\'
    END');
CALL modcol('{prefix}_mail', 'locked', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

# =============================================================================
# Batch Y — _comment, the reply tree, the soft delete and the write keys
# =============================================================================
#
# Five columns are added, two superseded indexes are dropped and KEY time is kept,
# because the unfiltered moderation list orders by time alone
#
# pid is the only stored parent relation. Every existing comment stays a root:
# pid is 0 and nothing is ever re-parented from the old "[b]name[/b]," reply
# convention - that text is a naming habit and not a structure, and guessing at
# it would invent threads nobody wrote. Replies, branch paging and tombstones are
# read with recursive CTEs over pid, so no materialised path is stored
#
# reqkey is the idempotency key of a write and is stored as raw bytes, never as
# hex text: BINARY(16) under one unique index, and absence is NULL rather than a
# shared empty string, because a unique index counts every NULL as distinct. No
# key is minted here for an existing row - a comment written before this release
# was never replayed and needs no key
#
# shown is the moment of the first publication and is never cleared, so hiding and
# publishing again is not a new reply to whoever follows the discussion. A comment
# that is published when the upgrade runs takes its own time, copied once time is
# proven to hold no NULL; a hidden one was never proven public and stays NULL until
# a moderator publishes it
#
# ip carries an address and nothing else, so it is compared and sorted byte by
# byte; (ip, time, id) answers the best-effort flood interval on its own and
# replaces the hash column and its rate table alike
#
# The two stops are deliberate. A reqkey left as hex text means this table was
# already carried through a transitional release, and converting it belongs to
# the dev tooling, not to an upgrade run. A NULL time means a row nothing can
# order; both are reported instead of guessed at

CALL delcol('{prefix}_comment', 'format');
CALL delcol('{prefix}_comment', 'iphash');
CALL delcol('{prefix}_comment', 'path');

CALL addcol('{prefix}_comment', 'pid',     'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_comment', 'shown',   'DATETIME DEFAULT NULL');
CALL poscol('{prefix}_comment', 'shown', 'DATETIME DEFAULT NULL', 'status');
CALL addcol('{prefix}_comment', 'edited',  'DATETIME DEFAULT NULL');
CALL addcol('{prefix}_comment', 'deleted', 'DATETIME DEFAULT NULL');

CALL stopcol('{prefix}_comment', 'reqkey', 'char', 'comment.reqkey is still hex text: convert it with the dev tooling before this upgrade');
CALL addcol('{prefix}_comment', 'reqkey',  'BINARY(16) DEFAULT NULL');

CALL stopnull('{prefix}_comment', 'time', 'comment.time holds NULL rows: repair them before this upgrade');
CALL modcol('{prefix}_comment', 'time', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
UPDATE `{prefix}_comment` SET `shown` = `time` WHERE `status` = 1 AND `shown` IS NULL;
CALL setcoll('{prefix}_comment', 'ip', 'VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'', 'ascii_bin');
CALL poscol('{prefix}_comment', 'pid', 'INT UNSIGNED NOT NULL DEFAULT 0', 'id');

CALL delidx('{prefix}_comment', 'cid');
CALL delidx('{prefix}_comment', 'modul_status');
CALL delidx('{prefix}_comment', 'iphash_time');
CALL delidx('{prefix}_comment', 'modul_cid_path');

CALL addidx('{prefix}_comment', 'reqkey',                   '`reqkey`',                                              1);
CALL addidx('{prefix}_comment', 'modul_cid_status_deleted', '`modul`, `cid`, `status`, `deleted`, `time`, `id`',     0);
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
# exists without it could already hold two spellings of one mailbox
#
# The email column carries a binary collation on purpose. The normaliser
# lowercases the domain and leaves the local part as it came, and a
# case-insensitive column would fold exactly the distinction it keeps
#
# _newsletter gains the campaign state machine. The mails column is dropped last
# and only here: update.php reads whatever is still pending out of it before
# this file runs and writes those addresses into the queue afterwards, so the
# drop can never take a mailing with it

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
# Batch Node — the content tables of 6.3
# =============================================================================

# The tables are new in 6.3 and have no legacy source: the nine removed modules are not imported, and the creates carry the fresh definitions verbatim
# They follow their foreign keys, types first and materials second, so the update runs with the foreign key checks left on exactly like a fresh install

CREATE TABLE IF NOT EXISTS `{prefix}_node_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `intro` TEXT NOT NULL,
  `ext` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `active` BOOLEAN NOT NULL DEFAULT 0,
  `sort` INT UNSIGNED NOT NULL DEFAULT 0,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_nodes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tid` INT UNSIGNED NOT NULL,
  `cid` INT UNSIGNED NOT NULL DEFAULT 0,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `aname` VARCHAR(25) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `title` VARCHAR(100) NOT NULL,
  `intro` TEXT NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `field` MEDIUMTEXT NOT NULL,
  `poll` INT UNSIGNED NOT NULL DEFAULT 0,
  `home` BOOLEAN NOT NULL DEFAULT 0,
  `comon` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `pinned` BOOLEAN NOT NULL DEFAULT 0,
  `comnum` INT UNSIGNED NOT NULL DEFAULT 0,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `score` INT UNSIGNED NOT NULL DEFAULT 0,
  `ratings` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published` DATETIME DEFAULT NULL,
  `expires` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pub` (`tid`, `status`, `pinned`, `published`, `id`),
  KEY `cat` (`tid`, `cid`, `status`, `pinned`, `published`, `id`),
  KEY `home` (`tid`, `home`, `status`, `pinned`, `published`, `id`),
  KEY `updated` (`tid`, `status`, `pinned`, `updated`, `id`),
  KEY `views` (`tid`, `status`, `pinned`, `views`, `published`, `id`),
  KEY `title` (`tid`, `status`, `pinned`, `title`, `id`),
  KEY `tree` (`tid`, `status`, `id`),
  KEY `pinned` (`tid`, `pinned`, `published`, `id`),
  KEY `author` (`uid`, `status`, `published`, `id`),
  KEY `ip` (`ip`, `created`, `id`),
  KEY `poll` (`poll`),
  CONSTRAINT `{prefix}_fk_nodes_type` FOREIGN KEY (`tid`) REFERENCES `{prefix}_node_types` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_assets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nid` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `role` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `src` VARCHAR(2048) NOT NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `title` VARCHAR(100) NOT NULL DEFAULT '',
  `intro` TEXT NOT NULL,
  `mime` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `size` BIGINT UNSIGNED NULL DEFAULT NULL,
  `width` INT UNSIGNED NULL DEFAULT NULL,
  `height` INT UNSIGNED NULL DEFAULT NULL,
  `duration` INT UNSIGNED NULL DEFAULT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `reported` DATETIME DEFAULT NULL,
  `ruid` INT UNSIGNED NOT NULL DEFAULT 0,
  `sort` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `node` (`nid`, `role`, `sort`, `id`),
  KEY `src` (`src`(191)),
  CONSTRAINT `{prefix}_fk_node_assets_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
  CONSTRAINT `{prefix}_chk_node_assets_kind` CHECK (`kind` IN ('file', 'image', 'audio', 'video')),
  CONSTRAINT `{prefix}_chk_node_assets_role` CHECK (`role` <> ''),
  CONSTRAINT `{prefix}_chk_node_assets_src` CHECK (`src` <> '')
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_categories` (
  `nid` INT UNSIGNED NOT NULL,
  `cid` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`nid`, `cid`),
  KEY `cat` (`cid`, `nid`),
  CONSTRAINT `{prefix}_fk_node_categories_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_legacy` (
  `modul` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `oid` INT UNSIGNED NOT NULL,
  `nid` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`modul`, `oid`),
  KEY `node` (`nid`),
  CONSTRAINT `{prefix}_fk_node_legacy_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_publish` (
  `nid` INT UNSIGNED NOT NULL,
  `published` DATETIME NOT NULL,
  `due` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`nid`),
  KEY `queue` (`due`, `nid`),
  CONSTRAINT `{prefix}_fk_node_publish_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# A build of early October kept the due moment as DATETIME: it passes a text column, takes its timer and returns to BIGINT
CALL runiftype('{prefix}_node_publish', 'due', 'datetime', 'ALTER TABLE `{prefix}_node_publish` MODIFY `due` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_node_publish', 'due', 'varchar', 'UPDATE `{prefix}_node_publish` SET `due` = CASE
    WHEN `due` REGEXP \'^[0-9]{4}-\' THEN UNIX_TIMESTAMP(CAST(`due` AS DATETIME))
    WHEN `due` REGEXP \'^[0-9]{1,19}$\' THEN `due`
    ELSE \'0\'
    END');
CALL modcol('{prefix}_node_publish', 'due', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');

CREATE TABLE IF NOT EXISTS `{prefix}_node_relations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nid` INT UNSIGNED NOT NULL,
  `rid` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `sort` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `edge` (`nid`, `type`, `rid`),
  KEY `source` (`nid`, `type`, `sort`, `rid`),
  KEY `target` (`rid`, `type`, `sort`, `nid`),
  CONSTRAINT `{prefix}_fk_node_relations_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
  CONSTRAINT `{prefix}_fk_node_relations_related` FOREIGN KEY (`rid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
  CONSTRAINT `{prefix}_chk_node_relations_self` CHECK (`nid` <> `rid`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_support` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nid` INT UNSIGNED NOT NULL,
  `aid` INT UNSIGNED NOT NULL DEFAULT 0,
  `state` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `prio` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `node` (`nid`),
  KEY `queue` (`state`, `prio` DESC, `activity`, `id`),
  KEY `admin` (`aid`, `state`, `prio` DESC, `activity`, `id`),
  CONSTRAINT `{prefix}_fk_node_support_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
  CONSTRAINT `{prefix}_chk_node_support_state` CHECK (`state` <= 2),
  CONSTRAINT `{prefix}_chk_node_support_prio` CHECK (`prio` <= 3)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

CREATE TABLE IF NOT EXISTS `{prefix}_node_sync` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nid` INT UNSIGNED NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `refresh` INT UNSIGNED NOT NULL DEFAULT 3600,
  `due` DATETIME DEFAULT NULL,
  `checked` DATETIME DEFAULT NULL,
  `synced` DATETIME DEFAULT NULL,
  `etag` VARCHAR(255) NOT NULL DEFAULT '',
  `modified` VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `fails` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `error` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `node` (`nid`),
  KEY `due` (`due`, `id`),
  CONSTRAINT `{prefix}_fk_node_sync_node` FOREIGN KEY (`nid`) REFERENCES `{prefix}_nodes` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE,
  CONSTRAINT `{prefix}_chk_node_sync_refresh` CHECK (`refresh` = 0 OR `refresh` BETWEEN 300 AND 31536000)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# =============================================================================
# Manual follow-up after this script
# =============================================================================
#
# 1. Legacy side tables handled by this script:
#    - `{prefix}_modules` is outside this migration
#
# 2. Password hashing migration (PHP side — already done in current codebase):
#    - passwords are now stored as bcrypt via getPassHash() / password_hash()
#    - existing md5_salt hashes are upgraded transparently on next user login
#    - no bulk SQL re-hash needed

# =============================================================================
# Avatars moved into the themes (templates/<theme>/images/avatars/)
# Old stored values `default/NN.<ext>` map to theme presets `presets/NN.svg`;
# legacy defaults without a matching preset are cleared to the user.svg fallback
# Both statements are idempotent: a re-run affects zero rows
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
  `linked` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastlog` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_puid` (`provider`, `puid`),
  UNIQUE KEY `uid_provider` (`uid`, `provider`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# A build of early October kept both moments of a link as timers: each passes a text column, takes its moment and returns to DATETIME
# A timer 0 is no moment: the link takes the time of the run, the last login none; a second run finds DATETIME and changes nothing
CALL runiftype('{prefix}_user_oauth', 'linked', 'bigint', 'ALTER TABLE `{prefix}_user_oauth` MODIFY `linked` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_user_oauth', 'linked', 'varchar', 'UPDATE `{prefix}_user_oauth` SET `linked` = CASE
    WHEN `linked` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`linked`), \'%Y-%m-%d %H:%i:%s\')
    WHEN `linked` REGEXP \'^[0-9]{4}-\' THEN `linked`
    ELSE DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\')
    END');
CALL modcol('{prefix}_user_oauth', 'linked', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL runiftype('{prefix}_user_oauth', 'lastlog', 'bigint', 'ALTER TABLE `{prefix}_user_oauth` MODIFY `lastlog` VARCHAR(20) NULL DEFAULT NULL');
CALL runiftype('{prefix}_user_oauth', 'lastlog', 'varchar', 'UPDATE `{prefix}_user_oauth` SET `lastlog` = CASE
    WHEN `lastlog` REGEXP \'^[1-9][0-9]{0,10}$\' THEN DATE_FORMAT(FROM_UNIXTIME(`lastlog`), \'%Y-%m-%d %H:%i:%s\')
    WHEN `lastlog` REGEXP \'^[0-9]{4}-\' THEN `lastlog`
    END');
CALL modcol('{prefix}_user_oauth', 'lastlog', 'DATETIME DEFAULT NULL');

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
            'SELECT `id`, ''ulogin'', `network`, COALESCE(`email`, ''''), NOW() ',
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

# _groups.rank: a picture of the theme becomes the icon rank icon:lit:total:#rrggbb in tones darkened to 3:1 on both surfaces
# Only a value ending in a picture extension changes, so a second run changes nothing; a site's own picture keeps no lit mark
UPDATE `{prefix}_groups` SET `rank` = CASE `rank`
    WHEN 'rank_0.png' THEN 'star-fill:0:5:#8297a2'
    WHEN 'rank_1.png' THEN 'star-fill:1:5:#8297a2'
    WHEN 'rank_2.png' THEN 'star-fill:2:5:#8297a2'
    WHEN 'rank_3.png' THEN 'star-fill:3:5:#8297a2'
    WHEN 'rank_4.png' THEN 'star-fill:4:5:#8297a2'
    WHEN 'rank_5.png' THEN 'star-fill:5:5:#8297a2'
    WHEN 'admin.png' THEN 'star-fill:5:5:#c68649'
    WHEN 'moderator.png' THEN 'star-fill:5:5:#b0904d'
    WHEN 'developer.png' THEN 'star-fill:5:5:#629abf'
    WHEN 'client.png' THEN 'record-circle-fill:5:5:#629abf'
    WHEN 'hosting.png' THEN 'gear-fill:5:5:#629abf'
    WHEN 'partner_silver.png' THEN 'gear-fill:5:5:#8297a2'
    WHEN 'partner_bronze.png' THEN 'sun-fill:5:5:#c68649'
    WHEN 'partner_gold.png' THEN 'sun-fill:5:5:#b0904d'
    ELSE CONCAT('star-fill:0:5:', IF(`color` REGEXP '^#[0-9a-f]{6}$', LOWER(`color`), '#8297a2'))
END
WHERE `rank` REGEXP '[.](png|gif|jpe?g|webp|svg)$';

# =============================================================================
# _login_log
# =============================================================================

# Every login of the site and of the panel, read by the profile and by the security page of the panel; a 6.2 site kept none, so nothing is carried over
CREATE TABLE IF NOT EXISTS `{prefix}_login_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL DEFAULT 0,
  `admin` BOOLEAN NOT NULL DEFAULT 0,
  `result` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `way` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'login',
  `ip` VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `agent` VARCHAR(255) NOT NULL DEFAULT '',
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uid_time` (`admin`, `uid`, `time`, `id`),
  KEY `time` (`time`)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# =============================================================================
# _routes
# =============================================================================

# The registry of public addresses: every path an entity was ever given, current, former or gone; cur is 1 on the current row only, so the owner key
# allows one current path per entity beside any number of NULL ones. A 6.2 site had no such addresses, so the table starts empty
CREATE TABLE IF NOT EXISTS `{prefix}_routes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `kind` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `oid` INT UNSIGNED NOT NULL,
  `state` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `cur` TINYINT UNSIGNED AS (IF(`state` = 1, 1, NULL)) STORED,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`),
  UNIQUE KEY `owner` (`kind`, `oid`, `cur`),
  CONSTRAINT `{prefix}_chk_routes_state` CHECK (`state` IN (0, 1, 2))
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};

# =============================================================================
# The collation of the release, column by column
# =============================================================================
#
# CONVERT TO CHARACTER SET would also turn the ascii_bin and utf8mb4_bin columns, and MariaDB lets it win over a MODIFY
# of the same statement, so a case-only twin in _maildead breaks its unique key. setconv sets the table default and
# modifies only the text columns of another utf8 collation, keeping type, NULL and default; a table on the collation
# is left alone. The old module tables are not named here: they are the input of the migration and keep their own

DROP PROCEDURE IF EXISTS setconv;

DELIMITER $$

CREATE PROCEDURE setconv(IN ptab VARCHAR(128), IN pcoll VARCHAR(64))
BEGIN
    DECLARE ctab INT DEFAULT 0;
    DECLARE maria INT DEFAULT 0;
    DECLARE mods TEXT DEFAULT NULL;

    SET SESSION group_concat_max_len = 1048576;
    SET maria = VERSION() LIKE '%MariaDB%';

    SELECT COUNT(*)
      INTO ctab
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND table_collation <> pcoll;

    SELECT GROUP_CONCAT(CONCAT(
               'MODIFY `', column_name, '` ', column_type, ' CHARACTER SET utf8mb4 COLLATE ', pcoll,
               IF(is_nullable = 'NO', ' NOT NULL', ' NULL'),
               IF(column_default IS NULL, '', CONCAT(' DEFAULT ', IF(maria, column_default, QUOTE(column_default))))
           ) ORDER BY ordinal_position SEPARATOR ', ')
      INTO mods
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = ptab
       AND character_set_name LIKE 'utf8%'
       AND RIGHT(collation_name, 4) <> '_bin'
       AND collation_name <> pcoll;

    IF ctab > 0 OR mods IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE `', ptab, '` DEFAULT CHARACTER SET utf8mb4 COLLATE ', pcoll, IFNULL(CONCAT(', ', mods), ''));
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL setconv('{prefix}_admins', '{collate}');
CALL setconv('{prefix}_blocks', '{collate}');
CALL setconv('{prefix}_categories', '{collate}');
CALL setconv('{prefix}_comment', '{collate}');
CALL setconv('{prefix}_favorites', '{collate}');
CALL setconv('{prefix}_forum', '{collate}');
CALL setconv('{prefix}_groups', '{collate}');
CALL setconv('{prefix}_login_log', '{collate}');
CALL setconv('{prefix}_mail', '{collate}');
CALL setconv('{prefix}_maildead', '{collate}');
CALL setconv('{prefix}_message', '{collate}');
CALL setconv('{prefix}_newsletter', '{collate}');
CALL setconv('{prefix}_node_types', '{collate}');
CALL setconv('{prefix}_nodes', '{collate}');
CALL setconv('{prefix}_node_assets', '{collate}');
CALL setconv('{prefix}_node_categories', '{collate}');
CALL setconv('{prefix}_node_legacy', '{collate}');
CALL setconv('{prefix}_node_publish', '{collate}');
CALL setconv('{prefix}_node_relations', '{collate}');
CALL setconv('{prefix}_node_support', '{collate}');
CALL setconv('{prefix}_node_sync', '{collate}');
CALL setconv('{prefix}_oauth_temp', '{collate}');
CALL setconv('{prefix}_points', '{collate}');
CALL setconv('{prefix}_privat', '{collate}');
CALL setconv('{prefix}_rating', '{collate}');
CALL setconv('{prefix}_rating_actors', '{collate}');
CALL setconv('{prefix}_rating_targets', '{collate}');
CALL setconv('{prefix}_rating_votes', '{collate}');
CALL setconv('{prefix}_routes', '{collate}');
CALL setconv('{prefix}_search', '{collate}');
CALL setconv('{prefix}_session', '{collate}');
CALL setconv('{prefix}_user_oauth', '{collate}');
CALL setconv('{prefix}_users', '{collate}');
CALL setconv('{prefix}_users_temp', '{collate}');
CALL setconv('{prefix}_voting', '{collate}');

DROP PROCEDURE IF EXISTS setconv;

# =============================================================================
# The keys of the release
# =============================================================================
#
# Every key the release drops or reshapes, after the collation: the unique addresses are built in the collation
# update.php searched their duplicates in, before this file ran. setidx() replaces a key only while its definition
# differs, so a second run changes nothing; a server that ignores a descending part (MariaDB before 10.8) rebuilds
# the two keys of _node_support on every run

CALL delidx('{prefix}_admins', 'lastvis');
CALL delidx('{prefix}_blocks', 'title');
CALL delidx('{prefix}_blocks', 'lang');
CALL delidx('{prefix}_blocks', 'status_position');
CALL delidx('{prefix}_categories', 'modul');
CALL delidx('{prefix}_categories', 'modul_lang_status');
CALL delidx('{prefix}_comment', 'uid');
CALL delidx('{prefix}_comment', 'time');
CALL delidx('{prefix}_comment', 'modul_cid_deleted');
CALL delidx('{prefix}_favorites', 'uid');
CALL delidx('{prefix}_forum', 'pid');
CALL delidx('{prefix}_forum', 'cid');
CALL delidx('{prefix}_forum', 'counter');
CALL delidx('{prefix}_forum', 'uid');
CALL delidx('{prefix}_forum', 'cid_status');
CALL delidx('{prefix}_groups', 'name');
CALL delidx('{prefix}_message', 'lang');
CALL delidx('{prefix}_newsletter', 'time');
CALL delidx('{prefix}_node_types', 'active');
CALL delidx('{prefix}_nodes', 'queue');
CALL delidx('{prefix}_nodes', 'expires');
CALL delidx('{prefix}_node_assets', 'report');
CALL delidx('{prefix}_points', 'target');
CALL delidx('{prefix}_rating', 'mid');
CALL delidx('{prefix}_rating', 'uid');
CALL delidx('{prefix}_search', 'word');
CALL delidx('{prefix}_search', 'modul');
CALL delidx('{prefix}_session', 'ip');
CALL delidx('{prefix}_users_temp', 'code');
CALL delidx('{prefix}_voting', 'status');
CALL delidx('{prefix}_voting', 'lang');

# The link of a material and a category is its own key: the surrogate id no code read leaves, and the primary key takes over the unique pair
CALL runifcol('{prefix}_node_categories', 'id', 'ALTER TABLE `{prefix}_node_categories` DROP PRIMARY KEY, DROP COLUMN `id`, ADD PRIMARY KEY (`nid`, `cid`), DROP INDEX `node`');

CALL setidx('{prefix}_admins', 'email', '`email`', 1);
CALL setidx('{prefix}_blocks', 'status_weight', '`status`, `weight`', 0);
CALL setidx('{prefix}_categories', 'modul_ordern', '`modul`, `ordern`', 0);
CALL setidx('{prefix}_comment', 'uid_status_deleted', '`uid`, `status`, `deleted`, `modul`, `cid`', 0);
CALL setidx('{prefix}_comment', 'modul_status_deleted', '`modul`, `status`, `deleted`, `time`, `id`', 0);
CALL setidx('{prefix}_forum', 'pid_ltime', '`pid`, `ltime`', 0);
CALL setidx('{prefix}_forum', 'pid_time', '`pid`, `time`', 0);
CALL setidx('{prefix}_forum', 'cid_pid_status_ltime', '`cid`, `pid`, `status`, `ltime`', 0);
CALL setidx('{prefix}_forum', 'uid_pid', '`uid`, `pid`', 0);
CALL setidx('{prefix}_nodes', 'pinned', '`tid`, `pinned`, `published`, `id`', 0);
CALL setidx('{prefix}_node_support', 'queue', '`state`, `prio` DESC, `activity`, `id`', 0);
CALL setidx('{prefix}_node_support', 'admin', '`aid`, `state`, `prio` DESC, `activity`, `id`', 0);
CALL setidx('{prefix}_search', 'word_modul', '`word`, `modul`', 0);
CALL setidx('{prefix}_search', 'modul_time', '`modul`, `time`', 0);
CALL setidx('{prefix}_user_oauth', 'provider_puid', '`provider`, `puid`', 1);
CALL setidx('{prefix}_users', 'email', '`email`', 1);
CALL setidx('{prefix}_users', 'newslet', '`newslet`', 0);
CALL setidx('{prefix}_users_temp', 'email', '`email`', 1);

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
DROP PROCEDURE IF EXISTS runiftype;
DROP PROCEDURE IF EXISTS renidx;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
DROP PROCEDURE IF EXISTS setidx;
DROP PROCEDURE IF EXISTS mkuseruniq;
DROP PROCEDURE IF EXISTS mksessuniq;
DROP PROCEDURE IF EXISTS fixgrppk;
DROP PROCEDURE IF EXISTS finalize_user_names;

SET SESSION sql_mode = @SLAED_MODE;
