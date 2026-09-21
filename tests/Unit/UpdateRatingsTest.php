<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S05 of docs/node: the ratings unit of the 6.3 data update in setup/index.php, whose contract is the carry-over
 * of the accumulated ratings in docs/node/ratings.md. tests/Support/update_probe.php lifts the shipped functions out
 * of the installer by name and drives them in an isolated CLI process against a disposable schema and a scratch site,
 * so the manifest, the snapshots, the mark, the rules and the aggregates of the stand are never touched.
 */
final class UpdateRatingsTest extends TestCase
{
    private const TARGETS = [['account', 2, 37, 10], ['account', 3, 0, 0], ['account', 4, 5, 1], ['forum', 5, 6, 2], ['shop', 8, 15, 3]];

    private const ACTORS = [
        ['account', 2, 'g:3.3.3.3', 1700000100],
        ['account', 2, 'u:9', 1700000000],
        ['forum', 5, 'g:2001:db8::1', 1700000205],
        ['shop', 8, 'u:9', 1700000400],
    ];

    private const RULES = [
        'account' => ['active' => '1', 'period' => '2592000', 'detail' => '0', 'guests' => '1'],
        'forum' => ['active' => '0', 'period' => '0', 'detail' => '1', 'guests' => '1'],
        'shop' => ['active' => '1', 'period' => '86400', 'detail' => '1', 'guests' => '1'],
    ];

    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getRun(string $name): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_probe_ratings';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' ratings 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'][$name];
    }

    # What a finished unit leaves behind, whichever way it got there: every target with its starting balance, the real last times, no vote, and a sealed manifest
    private function checkFinished(array $run, string $text, int $votes = 0): void
    {
        $this->assertTrue($run['done'], $text.': '.$run['text']);
        $this->assertSame(['verified', ['targets' => 1205, 'terms' => 4]], [$run['state'], $run['cursor']], $text.': the manifest did not reach its end');
        $this->assertTrue($run['files'] && $run['sealed'] && $run['moment'], $text.': the manifest does not seal its snapshots, the rules and the moment of the carry-over');
        $this->assertSame([self::TARGETS, 1205], [$run['targets'], $run['total']], $text.': a starting balance is not the aggregate of its owner');
        $this->assertSame(self::ACTORS, $run['actors'], $text.': the kept last times are not the newest real ones per account and per normalized address');
        $this->assertSame([$votes, 11], [$run['votes'], $run['old']], $text.': a vote was invented or the old table was touched');
    }

    # The clean path keeps sum and count of every remaining target, restores the real terms, leaves polls and other events alone and converts the rules
    #[Test]
    public function theCleanRunKeepsEveryAggregateAsItsStartingBalance(): void
    {
        $run = $this->getRun('clean')['first'];
        $this->checkFinished($run, 'first run');
        $this->assertSame([[2, 10, 37], [3, 0, 0], [4, 1, 5]], $run['owners'], 'The unit changed the aggregate of an owner');
        $stat = ['targets' => 1205, 'terms' => 4, 'voting' => 1, 'foreign' => 2, 'orphan' => 2, 'dropped' => 1];
        $this->assertSame($stat, $run['count'], 'The report does not count the rows it left alone');
        $this->assertSame(self::RULES, $run['rules'], 'The old rules were not carried into four keys with guests allowed and a zero period kept');
        $this->assertSame(['points' => '6.3.0', 'ratings' => '6.3.0'], $run['mark'], 'The mark of the unit is missing or the points mark was lost');
    }

    # A vote that arrived after the site opened is never taken for a starting balance, a lost mark is written again, and no file of the backup is rewritten
    #[Test]
    public function aRepeatNeverTakesACurrentAggregateForTheStartingOne(): void
    {
        $run = $this->getRun('clean');
        $this->checkFinished($run['again'], 'repeat', 1);
        $this->assertSame([2, 11, 42], $run['again']['owners'][0], 'The repeat changed the owner that took a new vote');
        $this->checkFinished($run['nomark'], 'verified without a mark', 1);
        $this->assertSame(['ratings' => '6.3.0'], $run['nomark']['mark']);
        $this->assertTrue($run['same'], 'A repeat rewrote the manifest or a snapshot');
    }

    # Rules that are already in the four-key form keep an explicit refusal of guests, and the rule of a registered node type stays
    #[Test]
    public function convertedRulesKeepTheirGuestSwitch(): void
    {
        $run = $this->getRun('clean')['ready'];
        $this->checkFinished($run, 'converted rules');
        $rule = ['active' => '1', 'period' => '86400', 'detail' => '0', 'guests' => '0'];
        $this->assertSame(['account' => $rule, 'forum' => $rule, 'node.news' => $rule, 'shop' => $rule], $run['rules']);
        $this->assertSame(0, $run['count']['dropped']);
    }

    # A manifest left at prepared or at applying continues over its snapshot: stored rows are compared, missing ones are written, nothing is added twice
    #[Test]
    public function anInterruptedRunContinuesOverItsSnapshot(): void
    {
        $run = $this->getRun('resume');
        $this->checkFinished($run['prepared'], 'prepared');
        $this->checkFinished($run['applying'], 'applying');
    }

    # Rows of the new tables or a mark without a manifest stop the unit before it writes anything
    #[Test]
    public function targetDataWithoutAManifestStopsTheUnit(): void
    {
        $run = $this->getRun('stop');
        foreach (['rows' => ['points' => '6.3.0'], 'mark' => ['points' => '6.3.0', 'ratings' => '6.3.0']] as $name => $mark) {
            $this->assertFalse($run[$name]['done'], $name);
            $this->assertStringContainsString('no manifest exists', $run[$name]['text']);
            $left = [$run[$name]['dir'], $run[$name]['state'], $run[$name]['mark'], $run[$name]['actors'], $run[$name]['rules']['account']];
            $this->assertSame([false, null, $mark, [], '2592000|1|0'], $left, $name.': the stopped unit wrote something');
        }
    }

    # A forged snapshot, a stored row that left the snapshot and an owner whose aggregate moved all stop the unit without a mark and before the rules are published
    #[Test]
    public function aStateThatLeftTheManifestLeavesNoMark(): void
    {
        $run = $this->getRun('stop');
        $texts = ['forged' => 'does not match its manifest', 'differ' => 'differs from the snapshot', 'owner' => 'do not match the manifest'];
        foreach ($texts as $name => $text) {
            $this->assertSame([false, null], [$run[$name]['done'], $run[$name]['mark']], $name);
            $this->assertStringContainsString($text, $run[$name]['text'], $name);
        }
        $this->assertSame(['applying', 'applying'], [$run['differ']['state'], $run['owner']['state']]);
        $this->assertSame(['account', 2, 38, 10], $run['differ']['targets'][0], 'The refused row was overwritten from the snapshot');
    }

    # The preflight names every broken source by table and id in one report and writes nothing: no directory, no row, no rule, no mark
    #[Test]
    public function thePreflightNamesBrokenDataAndWritesNothing(): void
    {
        $run = $this->getRun('stop')['broken'];
        $this->assertFalse($run['done']);
        $names = ['config/ratings.php account', 'config/ratings.php forum', 'config/ratings.php shop (missing)', 'probe_users 3', 'probe_forum 5', 'probe_products 8'];
        foreach (array_merge($names, ['probe_rating 20', 'probe_rating 21', 'probe_rating 22', 'probe_rating 23']) as $name) {
            $this->assertStringContainsString($name, $run['text'], 'The preflight does not name '.$name);
        }
        $left = [$run['dir'], $run['state'], $run['total'], $run['actors'], $run['votes'], $run['mark'], $run['rules']];
        $this->assertSame([false, null, 0, [], 0, ['points' => '6.3.0'], ['account' => '100|1|0', 'forum' => '0|2|1']], $left, 'The stopped preflight wrote something');
    }

    # The branch runs the ratings unit after the points unit, and its preflight names a table of the ratings transactions on another engine
    #[Test]
    public function theBranchRunsTheUnitAfterPoints(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/setup/index.php');
        $this->assertMatchesRegularExpression('/setUpdatePoints\(\$db, \$xprefix\);\s+\$bodytext \.= setUpdateRatings\(\$db, \$xprefix\);/', $code);
        $this->assertStringContainsString("['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0']", $code, 'A fresh installation does not leave the ratings mark');
        $this->assertStringEndsWith('start the update again: ALTER TABLE `probe_products` ENGINE=InnoDB;', $this->getRun('flight')['engine']);
        $sql = (string)file_get_contents(dirname(__DIR__, 2).'/setup/sql/table_update6_3.sql');
        foreach (['rating_targets', 'rating_actors', 'rating_votes'] as $name) $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `{prefix}_'.$name.'`', $sql);
    }
}
