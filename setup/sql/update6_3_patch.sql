# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
#
# Point update for an installation that ALREADY RUNS 6.3.
#
# HOW TO RUN
#   Administrator panel -> Database -> Inquiry  (admin.php?name=database&op=dump)
#   Paste this file, press the parse action first to read the statements, then execute.
#   {prefix} is substituted by that page from the installation configuration, so the
#   same file runs on any installation whatever its table prefix.
#
# WHO NEEDS IT
#   An installation set up or upgraded before this release. A 6.2 -> 6.3 upgrade run
#   from setup/ after this release already contains everything below, and re-running
#   the whole setup/sql/table_update6_3.sql would work too - it is idempotent - but
#   this file is the small part of it that is actually new.
#
# WHAT IT CHANGES
#   1. _comment gains pid and path with their two indexes, and every stored comment
#      becomes a root of its own. This is where the reply threads live.
#   2. _admins gets the column types the fresh schema declares. The upgrade used to
#      declare editor as BOOLEAN, which fails on any installation whose administrators
#      carry an editor name, and because an ALTER is all or nothing the twelve other
#      columns of that statement were discarded with it.
#   3. _users.points becomes NOT NULL DEFAULT 0. The upgrade declared that column
#      twice and the second, nullable declaration silently won.
#   4. The comments column of the eight target tables is brought in line with the
#      comments really published under them. It is denormalised and read by the
#      frontend without ever touching the comment table, so nothing noticed when the
#      request-supplied module of an earlier release moved the wrong targets counter.
#   5. _privat trades its single status column for the four independent states the private
#      messages run on: viewed and saved belong to the recipient, delin and delout to the
#      two mailbox sides. The saved messages are backfilled out of status before the column
#      is renamed, and the three single-column keys give way to the composites the mailbox
#      queries are read through.
#
# WHAT IT NEVER DOES
#   No row is deleted, no comment is touched and no user point is recalculated. Sections 1
#   to 4 add columns and indexes only when they are absent, and every counter statement
#   writes only the rows that disagree. Section 5 is the one section that converts
#   something: it renames a column, rewrites the private-message state it carried and drops
#   the three keys the old column set was indexed under. It reads status only while status
#   is still there, so it converges from whatever point an interrupted run stopped at and a
#   second run changes nothing either.
#
# NOTHING LEFT AFTER IT
#   No job has to be scheduled. Every comment write recomputes the counter of its own
#   target, so section 4 repairs what drifted before and the code keeps it in line
#   afterwards. A target nobody comments on again is reported by the first tab of the
#   comments section, and tools/comment-recount.php report|fix does the same from the
#   shell.

DELIMITER $$

DROP PROCEDURE IF EXISTS addcol$$
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

DROP PROCEDURE IF EXISTS rencol$$
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

DROP PROCEDURE IF EXISTS modcol$$
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

DROP PROCEDURE IF EXISTS runifcol$$
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

DROP PROCEDURE IF EXISTS delidx$$
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

DROP PROCEDURE IF EXISTS addidx$$
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

DELIMITER ;

# =============================================================================
# 1. Reply threads
# =============================================================================
#
# path is ascii with a binary collation because it is a sort key, and a collated column
# would order it by collation rules. Each segment is the comment id padded to ten digits,
# which is what keeps 10 after 9 instead of before it. Nothing is ever re-parented from
# the old reply convention: that text was a habit, not a structure.
# The backfill runs before the indexes, so they are built once over final values.

CALL addcol('{prefix}_comment', 'pid',  'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_comment', 'path', 'VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'');

UPDATE `{prefix}_comment` SET `path` = LPAD(`id`, 10, '0') WHERE `path` = '';

CALL addidx('{prefix}_comment', 'modul_cid_pid_time', '`modul`, `cid`, `pid`, `time`, `id`', 0);
CALL addidx('{prefix}_comment', 'modul_cid_path',     '`modul`, `cid`, `path`',             0);

# =============================================================================
# 2. Administrator column types
# =============================================================================
#
# The editor column names the editor plugin an administrator writes with, so a legacy
# boolean value is normalised before the column is widened.

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

# =============================================================================
# 3. User points
# =============================================================================

ALTER TABLE `{prefix}_users` MODIFY `points` INT UNSIGNED NOT NULL DEFAULT 0;

# =============================================================================
# 4. Comment counters
# =============================================================================
#
# The live number is the public one: published and not deleted. Every statement writes
# only the rows that disagree, which is what makes this section repeatable, and each one
# recomputes the number itself rather than trusting a figure from anywhere else.
# The shop module stores its targets in _products, the one module name that does not
# match its own table. Every other one below is named after the module itself.

UPDATE `{prefix}_faq` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'faq' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'faq' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_files` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'files' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'files' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_links` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'links' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'links' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_media` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'media' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'media' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_news` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'news' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'news' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_pages` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'pages' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'pages' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_products` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'shop' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'shop' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

UPDATE `{prefix}_voting` AS x SET x.comments = (
    SELECT COUNT(*) FROM `{prefix}_comment` AS c
     WHERE c.modul = 'voting' AND c.cid = x.id AND c.status = 1 AND c.deleted IS NULL
) WHERE x.comments <> (
    SELECT COUNT(*) FROM `{prefix}_comment` AS d
     WHERE d.modul = 'voting' AND d.cid = x.id AND d.status = 1 AND d.deleted IS NULL
);

# =============================================================================
# 5. Private message states
# =============================================================================
#
# One status column carried unread, read and saved for both sides at once, so a save hid the message
# from the sender and a delete by one participant destroyed the other participant's copy. It becomes
# four independent columns, one per state, and the backfill has to read status before the rename
# consumes it - which is what runifcol() runs it under, and what makes the step disappear by itself
# once the rename has happened.
# rencol() keeps the BOOLEAN the old column was declared as, so modcol() forces viewed onto the
# definition the fresh schema gives it and leaves the table untouched when it already carries it.
# The composites are added last and time keeps its place in front of them, which is the order
# setup/sql/table.sql declares, so a patched table and a freshly installed one are the same table.
# out_new and flood answer the two reads out_box cannot: the outgoing unread counter of the sidebar block,
# which filters a column out_box does not carry, and the send interval, which filters no state at all and
# orders by time - measured on real data, the first was a full table scan and the second a filesort.

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

DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS modcol;
DROP PROCEDURE IF EXISTS runifcol;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
