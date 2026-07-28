<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 1, batch 1 of docs/COMMENTS-REDESIGN-2026.md: the read methods of the Comment class have to answer
 * exactly what the queries they replace answer, or the byte parity the stage promises is lost at the batch
 * that migrates the call sites instead of here. Every scenario runs through tests/Support/contract_probe.php,
 * which boots the real core in an isolated CLI process and puts the legacy statement and the class method
 * side by side against the live rows of this installation.
 */
final class CommentReadTest extends TestCase
{
    private static array $probe = [];

    # Run the read probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' commentread 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe commentread did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # The class is loaded by the bootstrap and reachable as the request-scoped service
    #[Test]
    public function serviceIsWiredIntoTheRequest(): void
    {
        $this->assertTrue($this->getProbe()['wired']);
    }

    # The visible count of a target is the count the current list query produces
    #[Test]
    public function countMatchesTheQueryItReplaces(): void
    {
        $data = $this->getProbe();
        if ($data['target'][0] === '') $this->markTestSkipped('No published comment on this installation');
        $this->assertSame($data['count'][0], $data['count'][1]);
        $this->assertGreaterThan(0, $data['count'][1]);
    }

    # The first and the last page carry the same rows in the same order, at the same offset and running number
    #[Test]
    public function listPagesMatchTheQueryTheyReplace(): void
    {
        $data = $this->getProbe();
        if ($data['target'][0] === '') $this->markTestSkipped('No published comment on this installation');
        $this->assertNotEmpty($data['list']);
        foreach ($data['list'] as $page) {
            $this->assertSame($page[0], $page[1]);
            $this->assertNotEmpty($page[1]);
            $this->assertSame($page[2], $page[3]);
        }
    }

    # The author record of a registered commenter is the record the group join already resolves for the list
    #[Test]
    public function authorRecordMatchesTheGroupJoin(): void
    {
        $data = $this->getProbe();
        if (!$data['author']) $this->markTestSkipped('No registered commenter on this installation');
        $this->assertNotEmpty($data['author'][1]);
        $this->assertSame($data['author'][0], $data['author'][1]);
    }

    # The activity feed of an account sees the same published comments in the same order
    #[Test]
    public function userListMatchesTheFeedBranch(): void
    {
        $data = $this->getProbe();
        $this->assertSame($data['feed'][0], $data['feed'][1]);
    }

    # The module selector of the moderation list is offered the same module names
    #[Test]
    public function moduleListMatchesTheDistinctQuery(): void
    {
        $data = $this->getProbe();
        $this->assertSame($data['mods'][0], $data['mods'][1]);
        $this->assertNotEmpty($data['mods'][1]);
    }

    # A single comment is read with the same fields, and a missing id answers with nothing instead of a broken row
    #[Test]
    public function singleReadMatchesTheAdminQuery(): void
    {
        $data = $this->getProbe();
        $this->assertSame($data['single'][0], $data['single'][1]);
        $this->assertSame([], $data['missing']);
    }

    # Every moderation filter — state, module and the search fields — lists and counts what the admin module lists and counts today
    #[Test]
    public function adminFiltersMatchTheModuleQueries(): void
    {
        $data = $this->getProbe();
        $this->assertCount(5, $data['admin']);
        foreach ($data['admin'] as $case) {
            $this->assertSame($case[0], $case[1]);
            $this->assertSame($case[2], $case[3]);
        }
    }
}
