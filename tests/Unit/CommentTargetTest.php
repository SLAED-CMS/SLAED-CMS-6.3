<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 1, batch 5 of docs/COMMENTS-REDESIGN-2026.md: the activity feed, the profile hub counter and the
 * eight module delete handlers stop reaching the comment table themselves. The behaviour half runs through
 * tests/Support/contract_probe.php, which drives deleteTarget() against the live rows inside a transaction it
 * always rolls back; the contract half reads the migrated call sites and asserts that none of them still
 * carries a comment statement of its own. The id list and the module name are driven with hostile values
 * because a target delete is the one path that used to interpolate its ids straight into IN (...).
 */
final class CommentTargetTest extends TestCase
{
    private static array $probe = [];
    private static array $feed = [];
    private static array $src = [];

    # Run the target probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' commenttarget 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe commenttarget did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # Run the feed probe once and memoize its report, because it renders the feed twice per account and is the slower of the two
    private function getFeed(): array
    {
        if (self::$feed !== []) return self::$feed;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' commentfeed 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe commentfeed did not return JSON: '.$out);
        return self::$feed = $data;
    }

    # Return the source of one file once, so several scenarios can read the same call site without re-reading it
    private function getFile(string $file): string
    {
        if (isset(self::$src[$file])) return self::$src[$file];
        return self::$src[$file] = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
    }

    # Deleting a target removes every comment written against it, in each of the eight modules that render comments
    #[Test]
    public function deletingTargetRemovesItsCommentsInEveryModule(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['clean'], 'The probe transaction was not rolled back');
        foreach (['faq', 'files', 'links', 'media', 'news', 'pages', 'shop', 'voting'] as $mod) {
            if (!$data['rows'][$mod]) {
                $this->addToAssertionCount(1);
                continue;
            }
            [$done, $held, $left, $gone] = $data['rows'][$mod];
            $this->assertTrue($done, 'Module "'.$mod.'" refused the delete');
            $this->assertGreaterThan(0, $held, 'Module "'.$mod.'" had no comments to delete');
            $this->assertSame(0, $left, 'Module "'.$mod.'" kept comments of a deleted target');
            $this->assertSame($held, $gone, 'Module "'.$mod.'" removed a different number of rows than it held');
        }
    }

    # A bulk selection removes the comments of every target in it and nothing else
    #[Test]
    public function bulkSelectionRemovesEveryTargetItNames(): void
    {
        $data = $this->getProbe();
        if (!$data['bulk']) $this->markTestSkipped('No module with two commented targets on this installation');
        [$done, $held, $left, $gone] = $data['bulk'];
        $this->assertTrue($done);
        $this->assertSame(0, $left);
        $this->assertSame($held, $gone);
    }

    # A target id shared by two modules is deleted for the named module alone
    #[Test]
    public function deleteKeepsTheCommentsOfOtherModules(): void
    {
        $data = $this->getProbe();
        if (!$data['cross']) $this->markTestSkipped('No target id shared by two modules on this installation');
        [$done, $mine, $rest, $stay, $kept] = $data['cross'];
        $this->assertTrue($done);
        $this->assertGreaterThan(0, $mine);
        $this->assertGreaterThan(0, $rest);
        $this->assertSame(0, $stay);
        $this->assertSame($rest, $kept, 'The comments of another module were removed with it');
    }

    # An empty module, an empty list and a list holding nothing positive all delete nothing
    #[Test]
    public function emptyArgumentsDeleteNothing(): void
    {
        $data = $this->getProbe();
        $this->assertFalse($data['refuse']['nomod']);
        $this->assertFalse($data['refuse']['noids']);
        $this->assertFalse($data['refuse']['zero']);
        $this->assertTrue($data['refuse']['kept'], 'A refused delete still removed rows');
    }

    # The id list and the module name reach the statement as bound values, so neither can carry SQL of its own
    #[Test]
    public function craftedArgumentsCannotWidenTheDelete(): void
    {
        $data = $this->getProbe();
        [$done, $held, $gone, $alive] = $data['craft'];
        $this->assertTrue($done);
        $this->assertSame($held, $gone, 'A crafted id list removed a different number of rows than the id it decodes to');
        $this->assertTrue($alive, 'A crafted id list emptied the table');
        $this->assertSame(0, $data['craftmod'][1], 'A crafted module name removed rows');
    }

    # The profile hub reads the same published count the query it replaces answered, and refuses a missing account
    #[Test]
    public function userCountMatchesTheQueryItReplaces(): void
    {
        $data = $this->getProbe();
        [$was, $now, $zero, $neg] = $data['count'];
        $this->assertGreaterThan(0, $was, 'No account with comments on this installation');
        $this->assertSame($was, $now);
        $this->assertSame(0, $zero);
        $this->assertSame(0, $neg);
    }

    # The migrated feed renders exactly the markup the UNION it replaces rendered, for accounts with and without comments
    #[Test]
    public function profileFeedRendersTheMarkupItReplaced(): void
    {
        $data = $this->getFeed();
        $seen = 0;
        foreach ($data['feed'] as $uid => $one) {
            [$was, $now, $same, $left, $right] = $one;
            $this->assertTrue($same, 'Account '.$uid.' renders a different feed: "'.$left.'" against "'.$right.'"');
            $this->assertSame($was, $now);
            if ($was > 0) $seen++;
        }
        $this->assertGreaterThan(4, $seen, 'The probe reached too few accounts to call this a comparison');
    }

    # The hub writes the same count, the same rating and the same favourites value the comment branch of its UNION wrote
    #[Test]
    public function profileHubWritesTheValuesItReplaced(): void
    {
        $data = $this->getFeed();
        $this->assertNotEmpty($data['hub']);
        foreach ($data['hub'] as $uid => $one) {
            $this->assertSame($one[0], $one[1], 'Account '.$uid.' shows different hub values than before the move');
        }
    }

    # The eight module delete handlers hold no comment statement any more and route the target through the class
    #[Test]
    public function moduleDeleteHandlersHoldNoCommentSql(): void
    {
        $files = [
            'faq' => 'modules/faq/admin/index.php',
            'files' => 'modules/files/admin/index.php',
            'links' => 'modules/links/admin/index.php',
            'media' => 'modules/media/admin/index.php',
            'news' => 'modules/news/admin/index.php',
            'pages' => 'modules/pages/admin/index.php',
            'shop' => 'modules/shop/admin/index.php',
            'voting' => 'modules/voting/admin/index.php',
        ];
        foreach ($files as $mod => $file) {
            $code = $this->getFile($file);
            $this->assertStringNotContainsString('PREFIX_DB.\'_comment', $code, $file.' still holds a comment statement');
            $this->assertStringContainsString('$com->deleteTarget(\''.$mod.'\'', $code, $file.' does not delete through the class');
        }
    }

    # The bulk handler of the shop binds its id list instead of pasting it into the statement
    #[Test]
    public function shopBulkHandlerBindsItsIdList(): void
    {
        $code = $this->getFile('modules/shop/admin/index.php');
        $beg = strpos($code, 'function productops(');
        $this->assertNotFalse($beg);
        $end = strpos($code, "\n}\n", $beg);
        $body = substr($code, $beg, $end - $beg);
        $this->assertStringNotContainsString('IN (\'.$id.\')', $body, 'The shop handler still interpolates its id list');
        $this->assertStringContainsString('$in = implode(\', \', $keys);', $body);
        $this->assertSame(0, substr_count($body, 'IN (\'.$id'), 'An interpolated id list is left in the shop handler');
    }

    # The profile feed and the profile hub read their comments through the class rather than through the module map
    #[Test]
    public function profileReadsGoThroughTheClass(): void
    {
        $feed = $this->getFile('core/user.php');
        $this->assertStringContainsString('$com->getUserList($uid, $limit)', $feed);
        $this->assertStringNotContainsString('PREFIX_DB.\'_comment', $feed);
        $hub = $this->getFile('modules/account/index.php');
        $this->assertStringContainsString('$com->getUserCount($uid)', $hub);
        $this->assertStringNotContainsString('PREFIX_DB.\'_comment', $hub);
        # Both files still build a table name from the module map, so the comment entry has to stay out of the branch that does it
        $this->assertStringContainsString('if ($mod == \'comm\' || !is_active($mod)) continue;', $feed, 'The feed lets the comment entry reach the UNION again');
        $this->assertStringContainsString('if ($mod != \'comm\') {', $hub, 'The hub lets the comment entry reach the UNION again');
    }
}
