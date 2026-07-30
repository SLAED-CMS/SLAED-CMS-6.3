<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 5 of docs/COMMENTS-REDESIGN-2026.md: a comment may answer another one, the answer is stored as a
 * parent id and a sortable path, the page counts and paginates root comments while every root arrives with
 * its branch, and a removed comment that still carries a live reply stays as a tombstone so the branch below
 * it does not break. The behaviour half runs through tests/Support/contract_probe.php, which signs in as an
 * administrator before the core boots and drives the class against the live table inside a transaction it
 * rolls back; the contract half reads the schema, the upgrade and the class.
 */
final class CommentThreadTest extends TestCase
{
    private static array $probe = [];
    private static array $src = [];

    # Run the thread probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' commentthread 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe commentthread did not return JSON: '.$out);
        if (empty($data['admin'])) $this->markTestSkipped('No super administrator with a stored address on this installation');
        if (empty($data['target'])) $this->markTestSkipped('No published news target on this installation');
        return self::$probe = $data;
    }

    # Return the source of one function or method, from its signature to its closing brace at the given indentation
    private function getSource(string $file, string $name, string $pad = ''): string
    {
        $key = $file.'::'.$name;
        if (isset(self::$src[$key])) return self::$src[$key];
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
        $beg = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($beg, $name.'() not found in '.$file);
        $end = strpos($code, "\n".$pad."}\n", $beg);
        $this->assertNotFalse($end, $name.'() has no closing brace in '.$file);
        return self::$src[$key] = substr($code, $beg, $end - $beg);
    }

    # Return one repository file as text
    private function getFile(string $file): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
    }

    # The fresh schema carries the two columns and the two indexes the tree reads, and the path column sorts by bytes rather than by collation
    #[Test]
    public function theSchemaCarriesTheTreeColumns(): void
    {
        $code = $this->getFile('setup/sql/table.sql');
        $beg = strpos($code, 'CREATE TABLE `{prefix}_comment`');
        $this->assertNotFalse($beg);
        $table = substr($code, $beg, strpos($code, ';', $beg) - $beg);
        $this->assertStringContainsString('`pid` INT UNSIGNED NOT NULL DEFAULT 0', $table);
        $this->assertStringContainsString('`path` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\'', $table);
        $this->assertStringContainsString('KEY `modul_cid_pid_time` (`modul`, `cid`, `pid`, `time`, `id`)', $table);
        $this->assertStringContainsString('KEY `modul_cid_path` (`modul`, `cid`, `path`)', $table);
    }

    # The upgrade adds the same two columns, makes every stored comment a root of its own and only then builds the indexes over final values
    #[Test]
    public function theUpgradeBackfillsBeforeItIndexes(): void
    {
        $code = $this->getFile('setup/sql/table_update6_3.sql');
        $col = strpos($code, "CALL addcol('{prefix}_comment', 'path'");
        $fill = strpos($code, "UPDATE `{prefix}_comment` SET `path` = LPAD(`id`, 10, '0') WHERE `path` = ''");
        $idx = strpos($code, "CALL addidx('{prefix}_comment', 'modul_cid_path'");
        $this->assertNotFalse($col, 'The upgrade does not add the path column');
        $this->assertNotFalse($fill, 'The upgrade does not backfill path');
        $this->assertNotFalse($idx, 'The upgrade does not index path');
        $this->assertLessThan($fill, $col, 'The backfill runs before the column exists');
        $this->assertLessThan($idx, $fill, 'The index is built before the values are final');
        $this->assertStringContainsString("CALL addcol('{prefix}_comment', 'pid',  'INT UNSIGNED NOT NULL DEFAULT 0')", $code);
        $this->assertStringContainsString("CALL addidx('{prefix}_comment', 'modul_cid_pid_time'", $code);
    }

    # No stored comment is left without a path, and no reply carries a path that does not descend from one
    #[Test]
    public function everyStoredCommentCarriesItsPath(): void
    {
        $data = $this->getProbe();
        $this->assertSame([0, 0], $data['legacy'], 'A stored comment is missing its path or carries a parent without one');
    }

    # A reply is stored under its parent and its path grows by exactly the segment of its own id
    #[Test]
    public function aReplyIsStoredUnderItsParent(): void
    {
        $data = $this->getProbe();
        [$root, $kid, $sub] = $data['chain'];
        $this->assertSame(['', '', ''], [$root[1], $kid[1], $sub[1]], 'The probe chain was refused');
        $this->assertSame([0, $root[0], $kid[0]], [$root[2], $kid[2], $sub[2]], 'A reply does not point at the comment it answers');
        $this->assertSame([0, 1, 2], [$root[4], $kid[4], $sub[4]], 'The depth of a reply does not follow its path');
        $this->assertSame(str_pad((string)$root[0], 10, '0', STR_PAD_LEFT), $root[3], 'A root path is not the padded id');
        $this->assertTrue($data['nested'], 'A nested reply does not carry the whole path of its parent');
    }

    # A parent that does not exist, one that belongs to another target and one that has been removed are all refused
    #[Test]
    public function aCraftedParentIsRefused(): void
    {
        $data = $this->getProbe();
        $this->assertNotSame('', $data['refuse']['wrong'], 'A parent id that matches no row was accepted');
        $this->assertNotSame('', $data['refuse']['foreign'], 'A parent of another target was accepted');
        $this->assertSame('', $data['refuse']['zero'], 'A root comment was refused');
        $this->assertNotSame('', $data['tomb']['reply'], 'A reply was attached to a removed comment');
    }

    # A thread stops at twenty segments: the twentieth reply is stored and the twenty-first is refused
    #[Test]
    public function theThreadStopsAtTwentySegments(): void
    {
        $data = $this->getProbe();
        $this->assertSame('', $data['depth'][20][0], 'The twentieth segment was refused');
        $this->assertSame(19, $data['depth'][20][1], 'The twentieth segment is not stored at depth nineteen');
        $this->assertNotSame('', $data['depth'][21][0], 'The twenty-first segment was accepted');
        $this->assertStringContainsString('MAXDEPTH = 20', $this->getFile('core/classes/comment.php'));
    }

    # The page counts root comments and answers each of them with its whole branch behind it, in the order the replies were written
    #[Test]
    public function thePageCountsRootsAndCarriesTheirBranches(): void
    {
        $data = $this->getProbe();
        $this->assertSame($data['page']['roots'], $data['page']['total'], 'The page total is not the number of root comments');
        $this->assertGreaterThan($data['page']['total'], $data['page']['rows'], 'The page carries no replies beside its roots');
        $this->assertSame([0, 0, 1, 2], $data['page']['depths'], 'A branch does not follow the root it belongs to');
        $code = $this->getSource('core/classes/comment.php', 'getList', '    ');
        $this->assertStringContainsString("\$root = \$where.' AND pid = 0'", $code);
        $this->assertStringContainsString('$this->getTreeRows(', $code);
    }

    # A branch can be fetched on its own and never answers more rows than the caller asked for
    #[Test]
    public function aBranchIsFetchedWithAnExplicitLimit(): void
    {
        $data = $this->getProbe();
        $this->assertSame(3, $data['branch'][0], 'The branch did not answer the replies stored under its comment');
        $this->assertSame(1, $data['branch'][2], 'The branch ignored the limit it was given');
        $sorted = $data['branch'][1];
        $copy = $sorted;
        sort($copy);
        $this->assertSame($copy, $sorted, 'A branch does not come back in the order it was written');
    }

    # A branch is walked in slices: the answer says how many replies it holds and how many are left behind the offset
    #[Test]
    public function aBranchReportsWhatIsLeftBehindTheOffset(): void
    {
        $data = $this->getProbe();
        $this->assertSame(19, $data['slice']['total'], 'The branch did not report the replies it holds');
        $this->assertSame(16, $data['slice']['left'], 'The branch did not report what is left after the first slice');
        $this->assertSame(3, $data['slice']['skip'], 'A second slice did not answer the rows behind the offset');
        $this->assertSame(0, $data['slice']['past'], 'An offset past the branch answered rows anyway');
    }

    # A page shows at most the configured number of replies under one root and says how many that root really holds
    #[Test]
    public function aPageCapsTheRepliesOfOneRoot(): void
    {
        $data = $this->getProbe();
        $reps = $data['cap']['reps'];
        $this->assertGreaterThan(0, $reps);
        $deep = 0;
        foreach ($data['cap']['roots'] as [$kids, $shown]) {
            $this->assertLessThanOrEqual($reps, $shown, 'A root put more replies on the page than the setting allows');
            $this->assertSame(min($kids, $reps), $shown, 'A root does not show what it is allowed to show');
            if ($kids > $reps) $deep++;
        }
        $this->assertGreaterThan(0, $deep, 'No root on the page held more replies than the cap, so the cap was not exercised');
        $code = $this->getSource('core/classes/comment.php', 'getTreeRows', '    ');
        $this->assertStringContainsString("intval(\$this->conf['reps'] ?? 5)", $code);
        $this->assertStringContainsString('LIMIT 0, ', $code, 'The reply query is not capped at all');
        $this->assertStringNotContainsString('ROW_NUMBER', $code, 'The cap uses a window function this distribution cannot rely on');
    }

    # The counter every module reads is swept against the comments that are really published, and only the drifted rows are written
    #[Test]
    public function theTargetCounterIsSweptAndRepaired(): void
    {
        $data = $this->getProbe();
        if ($data['drift']['seeded'] < 1) $this->markTestSkipped('No commented news target on this installation');
        $this->assertSame($data['drift']['seeded'], $data['drift']['found'], 'The sweep did not find the drift the probe created');
        $this->assertSame($data['drift']['seeded'], $data['drift']['fixed'], 'The repair did not write the rows it was handed');
        $this->assertSame(0, $data['drift']['left'], 'The repair left drift it had just been told about');
        $this->assertSame(0, $data['drift']['bad'], 'An unknown module or a zero target was written anyway');
        $this->assertTrue($data['drift']['clean'], 'Repairing an already correct counter wrote to it again');
        $this->assertTrue($data['drift']['whole'], 'The whole sweep answered less than the one-module sweep');
        $code = $this->getSource('core/classes/comment.php', 'updateCountDrift', '    ');
        $this->assertStringContainsString('SELECT COUNT(*) FROM', $code, 'The repair trusts the figure from the report instead of recomputing it');
        $this->assertStringNotContainsString('getCount(', $code, 'The repair counts through the viewer scope rather than the public one');
    }

    # The sweep runs unattended as a scheduler job and by hand through a tool, and neither of them owns a second copy of the module map
    #[Test]
    public function theSweepIsReachableWithoutAnyone(): void
    {
        $task = $this->getSource('core/system.php', 'addCommentTask');
        $this->assertStringContainsString('$com->getCountDrift()', $task);
        $this->assertStringContainsString('$com->updateCountDrift($drift)', $task);
        $core = $this->getFile('core/system.php');
        $this->assertStringContainsString("'commentsync' => addCommentTask()", $core, 'The job is not dispatched');
        $this->assertStringContainsString("'commentsync' => 'commentsync'", $core, 'The job is not a known system job');
        $tool = $this->getFile('tools/comment-recount.php');
        $this->assertStringContainsString('$com->getCountDrift($only)', $tool);
        $this->assertStringContainsString('$com->updateCountDrift($drift)', $tool);
        $this->assertDoesNotMatchRegularExpression('#_faq|_products|_voting#', $tool, 'The tool carries its own copy of the module map');
    }

    # A removed comment that still carries a live reply stays in the page as a tombstone, and leaves it once the last reply is gone
    #[Test]
    public function aRemovedParentWithRepliesStaysAsATombstone(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['tomb']['kept'], 'A removed comment with a live reply left the page and took the branch with it');
        $this->assertTrue($data['tomb']['child'], 'The reply of a removed comment left the page');
        $this->assertNotSame('', $data['tomb']['marked'], 'The tombstone is not a removed row at all');
        $this->assertTrue($data['tomb']['gone'], 'The tombstone stayed after its last reply was removed');
        $this->assertStringContainsString('is_gone', $this->getFile('templates/lite/fragments/comment.html'));
        $this->assertStringContainsString('_COMMENTS_GONE', $this->getSource('core/user.php', 'getCommentView'));
    }

    # Removing an account keeps its comments and drops only the reference to it, so no discussion loses a row and no branch loses a parent
    #[Test]
    public function removingAnAccountKeepsItsCommentsAndTheirBranches(): void
    {
        $data = $this->getProbe();
        if (empty($data['user']['uid'])) $this->markTestSkipped('No account with stored comments on this installation');
        $this->assertTrue($data['user']['done'], 'The anonymisation was refused');
        $this->assertSame(0, $data['user']['left'], 'A comment still points at the removed account');
        $this->assertTrue($data['user']['rows'], 'A comment was removed together with its author');
        $this->assertTrue($data['user']['kids'], 'A reply lost its parent when its author was removed');
        $this->assertSame($data['user']['mine'], $data['user']['anon'], 'The comments of the account were not all anonymised');
        $this->assertFalse($data['user']['zero'], 'An account id of zero was accepted');
    }

    # The user delete handler reaches the comment subsystem instead of holding a statement of its own, which is what the dead line it replaces used to be
    #[Test]
    public function theUserDeleteHandlerReachesTheClass(): void
    {
        $call = $this->getSource('modules/account/admin/index.php', 'delete');
        $this->assertStringContainsString('$com->deleteUser($id)', $call, 'Deleting a user does not reach the comment subsystem');
        $this->assertStringNotContainsString('_comment', $call, 'The user delete handler holds comment SQL again');
        $code = $this->getSource('core/classes/comment.php', 'deleteUser', '    ');
        $this->assertStringContainsString('SET uid = 0, name =', $code, 'The comments of a removed account keep a reference to it');
        $this->assertStringContainsString('WHERE uid = :uid', $code, 'The anonymisation is not scoped to the account being removed');
        $this->assertStringNotContainsString('DELETE', $code, 'The comments of a removed account are erased rather than anonymised');
        $this->assertStringNotContainsString('updateTargetCount', $code, 'Removing an account moves a target counter');
    }

    # The probe leaves the table exactly as it found it, so everything above was measured against live rows rather than against a fixture
    #[Test]
    public function theProbeRunLeavesTheTableUntouched(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['clean'], 'The thread probe left rows behind');
        $this->assertSame($data['count'][0], $data['count'][1]);
    }
}
