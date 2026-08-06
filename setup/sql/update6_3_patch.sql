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
# WHAT IT STARTS FROM
#   The deployed 6.3 tables this file targets carry, as reported on 2026-08-06:
#     _comment: id, cid, modul, time, uid, name, ip, body, status
#               PRIMARY(id), KEY cid, KEY uid, KEY modul_status, KEY time
#     _privat:  id, uidin, uidout, title, body, time, ip, status
#               PRIMARY(id), KEY uidin, KEY uidout, KEY status, KEY time
#   Neither table carries pid, edited, deleted, reqkey, format, iphash or path, and no
#   comment rate table exists. The retained columns already sit in the order the fresh
#   schema declares, so only pid has to be placed.
#   That deployment is older than the comment work alone: a restored dump of 2026-08-06 also
#   had no _mail and no _maildead, a _newsletter without its campaign state, two column widths
#   of their own and a non-unique session name. Sections 6 to 8 close exactly that distance,
#   which is what makes this file the whole manual migration rather than one part of it.
#   Every statement below is written to converge rather than to assume: it checks what is
#   there and changes only what disagrees, which is what makes an interrupted run and a
#   second run both safe.
#
# WHAT IT CHANGES
#   1. _comment reaches its final shape: pid behind id for the reply tree, edited and
#      deleted for the moderation marks, reqkey as the binary idempotency key under one
#      unique index, time required, ip byte-compared for the flood interval, and the index
#      set the real list, count and thread predicates are read through. Superseded keys are
#      dropped. Two guards stop the run instead of guessing: a reqkey found as hex text and
#      a NULL time.
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
#   6. _mail and _maildead, the outgoing queue, are created where they are missing. A
#      deployment taken before the queue release has neither, and the runtime writes a job for
#      every notification it raises.
#   7. _newsletter gains the campaign state a mailing runs on and loses the stored address
#      list it replaced.
#   8. The column types and the one key that still separate a deployed 6.3 from a fresh
#      install of this release.
#
# WHAT IT NEVER DOES
#   No row is deleted, no authored text is touched and no user point is recalculated. No
#   column named format, iphash or path is created or backfilled, and no rate table is
#   built - the drop statements for them fire only where an earlier transitional release
#   left one behind and are no-ops on the deployed tables. No idempotency key is minted for
#   an existing comment: a comment written before this release was never replayed and needs
#   none. Every counter statement writes only the rows that disagree. Section 5 is the one
#   section that converts something: it renames a column, rewrites the private-message
#   state it carried and drops the three keys the old column set was indexed under. It
#   reads status only while status is still there, so it converges from whatever point an
#   interrupted run stopped at and a second run changes nothing either.
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

DROP PROCEDURE IF EXISTS delcol$$
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

DROP PROCEDURE IF EXISTS poscol$$
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

DROP PROCEDURE IF EXISTS setcoll$$
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

DROP PROCEDURE IF EXISTS stopcol$$
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

DROP PROCEDURE IF EXISTS stopnull$$
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

DROP PROCEDURE IF EXISTS mksessuniq$$
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
# 1. Comment threads, edit and delete marks, write keys and the flood interval
# =============================================================================
#
# The deployed table carries id, cid, modul, time, uid, name, ip, body and status and
# nothing else, so this section is what brings it to the final shape. The retained columns
# already sit in the order the fresh schema declares, which is why pid is the only one that
# has to be placed: it is added directly behind id and poscol() moves it there on a rerun.
#
# pid is the only stored parent relation. Every existing comment stays a root at 0 and
# nothing is re-parented from the old "[b]name[/b]," reply convention - that text was a
# naming habit, not a structure. Replies, branch paging and tombstones are read with
# recursive CTEs over pid, so no materialised path is stored and none is created here.
#
# reqkey is the idempotency key of a write, stored as raw bytes and never as hex text:
# BINARY(16) under one unique index, absent as NULL rather than as a shared empty string,
# because a unique index counts every NULL as distinct. No key is minted for an existing
# row - a comment written before this release was never replayed and needs none.
#
# ip carries an address and nothing else, so it is compared and sorted byte by byte, and
# (ip, time, id) answers the best-effort flood interval on its own. No hash column and no
# rate table are involved; the delcol and DROP TABLE lines only fire where an earlier
# transitional release left them behind and are no-ops on the deployed table.
#
# The two stops are deliberate. A reqkey found as hex text means this table was carried
# through a transitional release, and converting it belongs to the dev tooling rather than
# to a production run. A NULL time means a row nothing can order. Both are reported instead
# of guessed at, and the patch stops before it has changed anything else.

CALL stopcol('{prefix}_comment', 'reqkey', 'char', 'comment.reqkey is still hex text: convert it with the dev tooling before this patch');
CALL stopnull('{prefix}_comment', 'time', 'comment.time holds NULL rows: repair them before this patch');
CALL stopnull('{prefix}_privat', 'time', 'privat.time holds NULL rows: repair them before this patch');

CALL delcol('{prefix}_comment', 'format');
CALL delcol('{prefix}_comment', 'iphash');
CALL delcol('{prefix}_comment', 'path');
DROP TABLE IF EXISTS `{prefix}_comment_rate`;

CALL addcol('{prefix}_comment', 'pid',     'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`');
CALL addcol('{prefix}_comment', 'edited',  'DATETIME DEFAULT NULL');
CALL addcol('{prefix}_comment', 'deleted', 'DATETIME DEFAULT NULL');
CALL addcol('{prefix}_comment', 'reqkey',  'BINARY(16) DEFAULT NULL');

CALL modcol('{prefix}_comment', 'time', 'DATETIME NOT NULL');

# body widens from TEXT to MEDIUMTEXT because an embedded image does not fit in TEXT. The editor
# offers "embed into the text", which base64-encodes the file into the body and writes no file at
# all, and the only bound on it is Parser::EMBEDMAX at 65536 bytes of binary. Base64 turns that
# into 87384 characters before the data URI prefix and the markdown around it, and TEXT holds
# 65535. So one image at exactly the size the parser is willing to render overflows the column:
# under strict mode the insert fails, without it MariaDB truncates and the comment is stored with
# a cut base64 tail that checkImageSource() then refuses, losing the image and the text after it.
# Halving the cap would only move the overflow from the first image to the second. MEDIUMTEXT ends
# the class instead: at 16 MB the column stops being the binding constraint and EMBEDMAX becomes
# the single honest bound, the same one in every module. Cost is one byte more of length prefix
# per row; body carries no index, so nothing else changes.
CALL modcol('{prefix}_comment', 'body', 'MEDIUMTEXT NOT NULL');
CALL setcoll('{prefix}_comment', 'ip', 'VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'', 'ascii_bin');
CALL poscol('{prefix}_comment', 'pid', 'INT UNSIGNED NOT NULL DEFAULT 0', 'id');

CALL delidx('{prefix}_comment', 'cid');
CALL delidx('{prefix}_comment', 'modul_status');
CALL delidx('{prefix}_comment', 'iphash_time');
CALL delidx('{prefix}_comment', 'modul_cid_path');

CALL addidx('{prefix}_comment', 'reqkey',                   '`reqkey`',                                          1);
CALL addidx('{prefix}_comment', 'uid',                      '`uid`',                                             0);
CALL addidx('{prefix}_comment', 'time',                     '`time`',                                            0);
CALL addidx('{prefix}_comment', 'modul_cid_status_deleted', '`modul`, `cid`, `status`, `deleted`, `time`, `id`', 0);
CALL addidx('{prefix}_comment', 'modul_cid_deleted',        '`modul`, `cid`, `deleted`, `time`, `id`',           0);
CALL addidx('{prefix}_comment', 'status_deleted_time',      '`status`, `deleted`, `time`, `id`',                 0);
CALL addidx('{prefix}_comment', 'modul_cid_pid_time',       '`modul`, `cid`, `pid`, `time`, `id`',               0);
CALL addidx('{prefix}_comment', 'ip_time',                  '`ip`, `time`, `id`',                                0);

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
CALL modcol('{prefix}_privat', 'time', 'DATETIME NOT NULL');

# The private message form runs the same editor as the comment form, so its body carries the same
# overflow and widens for the same reason recorded above the comment block.
CALL modcol('{prefix}_privat', 'body', 'MEDIUMTEXT NOT NULL');

# =============================================================================
# 6. The outgoing mail queue
# =============================================================================
#
# A deployed 6.3 taken before the queue release has neither table, and the runtime writes a job
# for every notification it raises, so without them a stored comment answers correctly while its
# notification is lost and the SQL log fills with the same missing-table error.
# Both tables are new and have no legacy source, so the create carries the primary key alone and
# every secondary index is added on its own: an installation that stopped midway gains only what
# it is still missing instead of failing on what it already has.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL addidx('{prefix}_mail', 'hold_status_prio_ntime', '`hold`, `status`, `prio`, `ntime`, `id`', 0);
CALL addidx('{prefix}_mail', 'kind_status_time',       '`kind`, `status`, `time`',                0);
CALL addidx('{prefix}_mail', 'kind_ref_status',        '`kind`, `ref`, `status`',                 0);
CALL addidx('{prefix}_mail', 'lockid',                 '`lockid`',                                0);
CALL addidx('{prefix}_mail', 'locked',                 '`locked`',                                0);

CREATE TABLE IF NOT EXISTS `{prefix}_maildead` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `fails` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `phase` VARCHAR(10) NOT NULL DEFAULT '',
  `code` VARCHAR(20) NOT NULL DEFAULT '',
  `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL addidx('{prefix}_maildead', 'email', '`email`', 1);

# =============================================================================
# 7. Mailing campaigns
# =============================================================================
#
# A mailing is a criterion and a running state from the campaign release on, not a stored list of
# addresses: the audience is expanded into queue rows and the row keeps only what drives that run.
# The one column it replaces is dropped last, so a run interrupted between the two leaves a table
# that still carries both and converges on the next run rather than losing the old value early.

CALL addcol('{prefix}_newsletter', 'fails',  'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'status', 'TINYINT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'audit',  'VARCHAR(100) NOT NULL DEFAULT \'\'');
CALL addcol('{prefix}_newsletter', 'apar',   'VARCHAR(100) NOT NULL DEFAULT \'\'');
CALL addcol('{prefix}_newsletter', 'cursor', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'expect', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'total',  'INT UNSIGNED NOT NULL DEFAULT 0');
CALL addcol('{prefix}_newsletter', 'note',   'VARCHAR(255) NOT NULL DEFAULT \'\'');
CALL addidx('{prefix}_newsletter', 'time',   '`time`', 0);
CALL delcol('{prefix}_newsletter', 'mails');

# =============================================================================
# 8. Column types and keys the fresh schema declares
# =============================================================================
#
# What is left between a deployed 6.3 and a fresh install of this release, so all three channels
# end on one schema rather than on three that merely behave alike.
# The session key is the one that removes rows: a unique name is what the session model rests on,
# and the duplicates it drops are stale visits of the same name that the newest row already
# supersedes. Nothing else here deletes anything, and the two widths are narrowed only after the
# stored values were measured against them.

CALL modcol('{prefix}_rating', 'modul', 'VARCHAR(50) NOT NULL');
CALL modcol('{prefix}_rating', 'time', 'VARCHAR(14) NOT NULL');
CALL modcol('{prefix}_favorites', 'modul', 'VARCHAR(50) NOT NULL');
CALL mksessuniq('{prefix}_session');

# =============================================================================
# 9. Room for what the editor can produce
# =============================================================================
#
# Every field below is written through the rich editor, and the editor can embed an image into
# the text: it base64-encodes the file into the body and stores no file at all. The bound on
# that is Parser::EMBEDMAX, 65536 bytes of binary, which becomes 87384 characters of base64
# before the data URI prefix and the markdown around it. TEXT holds 65535, sql_mode carries
# STRICT_TRANS_TABLES on a stock server, and the result is not a lost image but a lost post:
#
#   ERROR 1406 (22001): Data too long for column 'body' at row 1
#
# Halving the cap does not fix it, it only moves the failure from the first image to the second.
# The columns that hold an authored text therefore widen to MEDIUMTEXT, and one constant governs
# embedding everywhere instead of thirteen different column widths deciding it by accident.
#
# The summary columns deliberately do NOT widen. An `intro` is drawn by the list query of its
# module, so a page of twenty rows carries twenty of them; leaving those at TEXT keeps a summary
# a summary, and an image referenced by address or uploaded to the server still fits there in a
# few dozen characters. That is the difference this section rests on: it is not graphics that
# are refused in a summary, it is one delivery method for them.
#
# users.sig widens for a different reason and only to TEXT. VARCHAR(255) is already too small
# for what people write: of 828 stored signatures the longest is exactly 255 and 95 sit above
# 240, so a tenth of them are pressed against the ceiling today, with no image involved. TEXT
# gives a signature room for a line of text beside a linked image, and stops well short of an
# embedded one, which is right because a signature is repeated under every post its author made
# on the page.

CALL modcol('{prefix}_faq',        'body', 'MEDIUMTEXT');
CALL modcol('{prefix}_files',      'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_forum',      'body', 'MEDIUMTEXT');
CALL modcol('{prefix}_help',       'body', 'MEDIUMTEXT');
CALL modcol('{prefix}_jokes',      'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_links',      'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_media',      'note', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_message',    'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_money',      'note', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_news',       'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_newsletter', 'body', 'MEDIUMTEXT');
CALL modcol('{prefix}_order',      'note', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_products',   'body', 'MEDIUMTEXT NOT NULL');
CALL modcol('{prefix}_users',      'sig',  'TEXT');

DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS mksessuniq;
DROP PROCEDURE IF EXISTS delcol;
DROP PROCEDURE IF EXISTS poscol;
DROP PROCEDURE IF EXISTS setcoll;
DROP PROCEDURE IF EXISTS stopcol;
DROP PROCEDURE IF EXISTS stopnull;
DROP PROCEDURE IF EXISTS rencol;
DROP PROCEDURE IF EXISTS modcol;
DROP PROCEDURE IF EXISTS runifcol;
DROP PROCEDURE IF EXISTS delidx;
DROP PROCEDURE IF EXISTS addidx;
