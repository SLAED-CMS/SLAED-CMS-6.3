# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
#
# ONE-TIME text repair for comments and private messages. RUN IT ONCE.
#
# THIS FILE IS NOT IDEMPOTENT AND MUST NOT BE RUN TWICE.
#   Every other SQL file of this release converges: it reads what is there and changes only what
#   disagrees, so a second run is a no-op. This one decodes text, and decoding is not a state that
#   can be recognised afterwards. A second run turns an authored "&amp;lt;" into a live "&lt;",
#   which is exactly the corruption no restore short of the dump can undo. Take the dump first.
#
# HOW TO RUN
#   Administrator panel -> Database -> Inquiry  (admin.php?name=database&op=dump)
#   Paste this file, press the parse action to read the blocks, then execute.
#   {prefix} is substituted by that page, so the same file runs on any installation.
#   Run it AFTER setup/sql/update6_3_patch.sql and INSIDE the same maintenance window, with the
#   site closed. The window is what makes this file correct at all - see WHY IT NEEDS NO VERDICT.
#
# WHY THIS EXISTS
#   Until this release a comment and a private message were stored the way they were displayed:
#   the writer ran htmlspecialchars over the text, turned "$" and "\" into their numeric entities
#   and inserted a break tag at every line ending. From this release both are stored as the source
#   their author wrote and are escaped when they are read. The two models disagree on the same
#   bytes, so a body written by the old writer now shows its own markup as text - "&lt;br /&gt;"
#   instead of a line break, "&#034;" instead of a quote.
#   Nothing else on the installation is affected. Every other module still renders trusted, so its
#   stored markup still displays as markup; the forum alone carries 8453 such rows and must not be
#   touched by this file. Only these two channels changed how they read what they store.
#
# WHY IT NEEDS NO VERDICT PER ROW
#   The tooling this replaces had to decide, row by row, whether a body was already source or still
#   the writer's output, and that verdict was the dangerous part. Here there is nothing to decide:
#   the deployment runs the old writer until the moment the files of this release are uploaded, so
#   every stored row is the writer's output and the whole table converts the same way. That holds
#   only while the site is closed and no message has been written on the new code. It is the one
#   precondition of this file.
#
# WHAT IT TOUCHES
#   {prefix}_comment.body, {prefix}_privat.body, {prefix}_privat.title - three fields, nothing else.
#   No row is deleted, no row is added, no column and no index is changed.
#
# TWO KINDS OF BREAK TAG, AND WHY THEY ARE NOT TREATED ALIKE
#   A tag the writer inserted sits directly in front of the line ending it was made from, so the
#   line ending is still there and the tag is dropped: the renderer of this release breaks on that
#   line ending by itself. A tag with no line ending behind it comes from an older writer that
#   replaced the line ending instead of preceding it, so there the tag becomes the line ending it
#   consumed. Measured on the reference dump: 2735 rows of the first kind, 3 of the second, and
#   dropping the second kind blindly would have joined two sentences into one.
#
# THE ORDER OF THE ENTITY DECODE IS LOAD-BEARING
#   "&amp;" is decoded last and never earlier. The writer escaped the ampersand first, so an author
#   who typed "&lt;" had it stored as "&amp;lt;". Decoding the ampersand before the angle bracket
#   turns that text into a live tag - the one thing this repair must never do.
#
# THE BLOCK ORDER IS THE PROOF, AND IT IS WHY EVERY BREAK COMES BEFORE EVERY ENTITY
#   While the entities are still encoded, an author who typed a break tag has it stored as
#   "&lt;br&gt;" and only the writer's own tag is a real one. That is the single moment at which
#   the two can be told apart, so all break blocks run first and every remaining tag at that point
#   is the writer's. The entity blocks then restore the author's tag as the text it always was, and
#   the renderer of this release escapes it again on the way out.
#   Reversing the two would delete the author's own tag along with the writer's.
#
# HOW TO CHECK IT WORKED
#   Halfway, after block 2 and before block 3, no break tag may be left anywhere:
#     SELECT SUM(body LIKE '%<br%') FROM {prefix}_comment;   -- must be 0
#     SELECT SUM(body LIKE '%<br%') FROM {prefix}_privat;    -- must be 0
#   At the end the row counts must be unchanged and a few rows carry a break tag or an entity
#   again. That is expected and is not a miss: it is the author's own text, restored. On the
#   reference dump of 2026-08-06 the run ended on 7354 comments of which 2 carry a tag and 1 an
#   entity, and 1472 messages of which 9 carry a tag, 1 an entity and no title anything.
#   Every one of them was verified to be authored text - a tag inside a [code] block, a quoted
#   HTML sample, an "&amp;" an author typed into a URL.
#     SELECT COUNT(*) FROM {prefix}_comment;                 -- must equal the count from before
#     SELECT COUNT(*) FROM {prefix}_privat;                  -- must equal the count from before

# =============================================================================
# 1. Comment bodies - the break tags
# =============================================================================

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(`body`, CONCAT('<br />', CHAR(13), CHAR(10)), CONCAT(CHAR(13), CHAR(10)))
 WHERE `body` LIKE CONCAT('%<br />', CHAR(13), CHAR(10), '%');

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(`body`, CONCAT('<br />', CHAR(10)), CHAR(10))
 WHERE `body` LIKE CONCAT('%<br />', CHAR(10), '%');

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(`body`, CONCAT('<br>', CHAR(13), CHAR(10)), CONCAT(CHAR(13), CHAR(10)))
 WHERE `body` LIKE CONCAT('%<br>', CHAR(13), CHAR(10), '%');

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(`body`, CONCAT('<br>', CHAR(10)), CHAR(10))
 WHERE `body` LIKE CONCAT('%<br>', CHAR(10), '%');

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(REPLACE(`body`, '<br />', CHAR(10)), '<br>', CHAR(10))
 WHERE `body` LIKE '%<br%';

# =============================================================================
# 2. Private message bodies - the break tags
# =============================================================================

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(`body`, CONCAT('<br />', CHAR(13), CHAR(10)), CONCAT(CHAR(13), CHAR(10)))
 WHERE `body` LIKE CONCAT('%<br />', CHAR(13), CHAR(10), '%');

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(`body`, CONCAT('<br />', CHAR(10)), CHAR(10))
 WHERE `body` LIKE CONCAT('%<br />', CHAR(10), '%');

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(`body`, CONCAT('<br>', CHAR(13), CHAR(10)), CONCAT(CHAR(13), CHAR(10)))
 WHERE `body` LIKE CONCAT('%<br>', CHAR(13), CHAR(10), '%');

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(`body`, CONCAT('<br>', CHAR(10)), CHAR(10))
 WHERE `body` LIKE CONCAT('%<br>', CHAR(10), '%');

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(REPLACE(`body`, '<br />', CHAR(10)), '<br>', CHAR(10))
 WHERE `body` LIKE '%<br%';

# =============================================================================
# 3. Comment bodies - the writer entities
# =============================================================================

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                `body`, '&lt;', CHAR(60)), '&gt;', CHAR(62)),
                '&quot;', CHAR(34)), '&#034;', CHAR(34)),
                '&#039;', CHAR(39)),
                '&#036;', CHAR(36)), '&#092;', CHAR(92))
 WHERE `body` REGEXP '&(lt|gt|quot|#034|#039|#036|#092);';

UPDATE `{prefix}_comment`
   SET `body` = REPLACE(`body`, '&amp;', CHAR(38))
 WHERE `body` LIKE '%&amp;%';

# =============================================================================
# 4. Private message bodies - the writer entities
# =============================================================================

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                `body`, '&lt;', CHAR(60)), '&gt;', CHAR(62)),
                '&quot;', CHAR(34)), '&#034;', CHAR(34)),
                '&#039;', CHAR(39)),
                '&#036;', CHAR(36)), '&#092;', CHAR(92))
 WHERE `body` REGEXP '&(lt|gt|quot|#034|#039|#036|#092);';

UPDATE `{prefix}_privat`
   SET `body` = REPLACE(`body`, '&amp;', CHAR(38))
 WHERE `body` LIKE '%&amp;%';

# =============================================================================
# 5. Private message titles
# =============================================================================
#
# A title carries no break tag on the reference dump and never did: the writer ran the line-ending
# pass over the body alone. It is decoded like a body and printed as plain text by the template.

UPDATE `{prefix}_privat`
   SET `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                 `title`, '&lt;', CHAR(60)), '&gt;', CHAR(62)),
                 '&quot;', CHAR(34)), '&#034;', CHAR(34)),
                 '&#039;', CHAR(39)),
                 '&#036;', CHAR(36)), '&#092;', CHAR(92))
 WHERE `title` REGEXP '&(lt|gt|quot|#034|#039|#036|#092);';

UPDATE `{prefix}_privat`
   SET `title` = REPLACE(`title`, '&amp;', CHAR(38))
 WHERE `title` LIKE '%&amp;%';
