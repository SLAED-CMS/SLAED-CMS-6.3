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
#
# WHAT IT NEVER DOES
#   Nothing is dropped, no row is deleted, no comment is touched, no user point is
#   recalculated. Columns and indexes are added only when absent and every counter
#   statement writes only the rows that disagree, so running this file twice changes
#   nothing the second time.
#
# ONE THING LEFT AFTER IT
#   Check that the scheduler lists the system job commentsync, daily. A fresh checkout
#   carries it in config/scheduler.php; an installation whose config is its own needs
#   it added once in the panel. It then keeps the counters of section 4 in line by
#   itself, and tools/comment-recount.php report|fix does the same from the shell.

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

DROP PROCEDURE IF EXISTS addcol;
DROP PROCEDURE IF EXISTS addidx;
