<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S04 of docs/node: the points unit of the 6.3 data update in setup/index.php, whose contract is the section
 * about the regular 6.3 data update in docs/node/12-migration.md. tests/Support/update_probe.php lifts the shipped
 * functions out of the installer by name and drives them in an isolated CLI process against a disposable schema and
 * a scratch site, so the manifest, the snapshot, the mark and the configuration of the stand are never touched.
 */
final class UpdatePointsTest extends TestCase
{
    private const SNAP = ['2' => 10, '3' => 0, '4' => 4294967295];

    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getRun(string $name): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_probe';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'][$name];
    }

    # What a finished unit leaves behind, whichever way it got there
    private function checkFinished(array $run, string $active, string $text): void
    {
        $this->assertTrue($run['done'], $text.': '.$run['text']);
        $this->assertSame(['verified', 3, self::SNAP], [$run['state'], $run['count'], $run['snap']], $text.': the snapshot is not the starting balances');
        $this->assertTrue($run['source'] && $run['target'], $text.': the manifest does not carry the hashes of its files');
        $this->assertSame(['points' => '6.3.0'], $run['mark'], $text.': the mark is missing');
        $this->assertSame([false, $active, true], [$run['stale'], $run['active'], $run['rules']], $text.': the scopes were not carried over');
    }

    # The clean path keeps the balances, carries users.point into points.active, drops the positional rules and leaves the shipped rules alone
    #[Test]
    public function theCleanRunKeepsTheStartingBalances(): void
    {
        $run = $this->getRun('clean');
        $this->checkFinished($run['first'], '0', 'first run');
        $this->checkFinished($run['on'], '1', 'a site with points switched on');
    }

    # A repeat never takes a current balance for a starting one, rewrites neither file of the backup, writes a lost mark again and changes no account
    #[Test]
    public function aRepeatNeverTakesTheSnapshotAgain(): void
    {
        $run = $this->getRun('clean');
        $this->checkFinished($run['again'], '0', 'repeat');
        $this->checkFinished($run['nomark'], '0', 'verified without a mark');
        $this->assertTrue($run['same'], 'A repeat rewrote the manifest or the snapshot');
        $this->assertSame([2 => 77, 3 => 0, 4 => 4294967295], $run['users'], 'The unit changed an account');
    }

    # A manifest left at prepared or at applying continues over the snapshot it already has
    #[Test]
    public function anInterruptedRunContinuesOverItsSnapshot(): void
    {
        $run = $this->getRun('resume');
        $this->checkFinished($run['prepared'], '0', 'prepared');
        $this->checkFinished($run['applying'], '0', 'applying');
    }

    # Journal rows or a mark without a manifest stop the unit before it writes anything
    #[Test]
    public function targetDataWithoutAManifestStopsTheUnit(): void
    {
        $run = $this->getRun('stop');
        foreach (['rows' => null, 'mark' => ['points' => '6.3.0']] as $name => $mark) {
            $this->assertFalse($run[$name]['done'], $name);
            $this->assertStringContainsString('no manifest exists', $run[$name]['text']);
            $left = [$run[$name]['state'], $run[$name]['snap'], $run[$name]['mark'], $run[$name]['stale']];
            $this->assertSame([null, null, $mark, true], $left, $name.': the stopped unit wrote something');
        }
    }

    # A snapshot that no longer matches its manifest before the unit is verified and a points scope of another shape both stop the unit without a mark,
    # while a verified unit whose snapshot the clean finish deleted only writes its lost mark
    #[Test]
    public function aBrokenSourceLeavesNoMark(): void
    {
        $run = $this->getRun('stop');
        $this->assertSame([false, null], [$run['forged']['done'], $run['forged']['mark']]);
        $this->assertStringContainsString('does not match its manifest', $run['forged']['text']);
        $this->assertSame([true, 'verified', null, ['points' => '6.3.0']], [$run['pruned']['done'], $run['pruned']['state'], $run['pruned']['snap'], $run['pruned']['mark']]);
        $this->assertSame([false, null, 'applying', true], [$run['scope']['done'], $run['scope']['mark'], $run['scope']['state'], $run['scope']['stale']]);
        $this->assertStringContainsString('not a valid points scope', $run['scope']['text']);
    }

    # The preflight refuses a server that lacks CHECK or the RENAME COLUMN and RENAME INDEX of the schema file, and a table of a points, ratings or Node transaction
    # on another engine; it runs before the installer writes a single file or renames the panel, and the branch closes the site right after it
    #[Test]
    public function thePreflightRefusesBeforeAnythingChanges(): void
    {
        $run = $this->getRun('flight');
        $this->assertSame('', $run['real'], 'The stand server was refused');
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/setup/index.php');
        $save = strpos($code, 'function save(): void {');
        $from = strpos($code, '$stop = checkUpdateBase($db, $xprefix);');
        $this->assertNotFalse($save, 'The installer lost its save handler');
        $this->assertNotFalse($from, 'The update branch lost its preflight');
        $head = substr($code, $save, $from - $save);
        $this->assertSame([0, 0], [substr_count($head, 'setConfigFile('), substr_count($head, 'rename(')], 'A file is written before the preflight');
        $next = substr($code, $from, strpos($code, 'getSqlFile(', $from) - $from);
        $shut = "setConfigFile('global.php', array_diff_key(\$conf, ['security' => '', 'db' => '']), ['close' => '1']);";
        $this->assertStringContainsString($shut, $next, 'The branch does not close the site between the preflight and the DDL');
        $this->assertLessThan(strpos($next, 'setUpdateConfig()'), strpos($next, $shut), 'The 6.2 settings are carried before the site is closed');
        $pass = array_keys(array_filter($run['server'], fn($v) => $v === ''));
        $this->assertSame(['10.5.2-MariaDB', '11.7.2-MariaDB-log', '8.0.16'], $pass, 'The version bound moved');
        foreach (['10.4.34-MariaDB', '10.5.1-MariaDB'] as $ver) $this->assertStringContainsString('older than 10.5.2', $run['server'][$ver]);
        $this->assertStringContainsString('older than 8.0.16', $run['server']['8.0.15']);
        $this->assertStringEndsWith('start the update again: ALTER TABLE `probe_favorites` ENGINE=InnoDB;', $run['engine'], 'The engine check names a wrong set of tables');
        foreach (['categories', 'voting'] as $name) {
            $this->assertStringEndsWith('ALTER TABLE `probe_'.$name.'` ENGINE=InnoDB;', $run['node'][$name], 'The engine check misses a table of the Node transactions');
        }
    }
}
