<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The 6.3 update of update.php carries a real 6.2 site over (docs/NODE.md, The 6.3 update)
final class UpdateSiteTest extends TestCase
{
    private static array $probe = [];

    # The fixture tests/Fixtures/update62 holds the CREATE TABLE statements of all 33 tables a real 6.2 site dumped, a small invented seed and the 6.2 configuration files
    # The probe tests/Support/install_probe.php in its update mode loads it into a disposable MariaDB database and serves a copy of the release around it
    # Over real HTTP update.php opens without a login, its run is refused by the preflight, stopped by a broken schema file, then runs three times
    # The result is compared with a clean installation, two guests vote in one poll, the panel and Node are walked, and a last run follows a deleted material
    # The fixture tests/Fixtures/update62early is an earlier 6.2 site with narrower and signed columns the same update brings to the same clean schema
    # Run the update probe once per 6.2 fixture and memoize its update report; both use the configuration of update62, a probe that fails is a failure, not a skip
    private function getRun(string $name = 'update62'): array
    {
        if (!isset(self::$probe[$name])) {
            $script = dirname(__DIR__).'/Support/install_probe.php';
            $site = dirname(__DIR__).'/Fixtures/';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_site_'.$name;
            $args = [$work, 'update', $site.$name.'/site.sql', $site.'update62/config', 'old'];
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            self::$probe[$name] = $data['runs']['update'] + ['logs' => $data['runs']['logs']];
        }
        return self::$probe[$name];
    }

    # The first schema run of a fixture fails on the statement the probe broke and on nothing else, so no statement of the file depends on a later one
    private function checkBrokenRun(array $run): void
    {
        $fail = array_values(array_filter($run['broken']['failed'], fn(string $v): bool => !str_starts_with($v, 'the update stopped')));
        $this->assertSame(['old_probe_missing'], $fail, 'The first schema run failed on more than the broken statement');
    }

    # The fixture loads, the page of the first stage offers the run and writes nothing, the preflight refuses a MyISAM table without touching a file
    # A broken schema file stops before any unit
    # A registry stop before the mark modules leaves config/modules.php alone, and the next run, over a broken schema file, reads the 6.2 table as the first and writes the mark
    #[Test]
    public function theUpdateRefusesBeforeItWritesAnything(): void
    {
        $run = $this->getRun();
        $this->assertSame([0, ''], $run['dump']);
        $this->assertSame([3, 35], $run['before']);
        $this->assertSame([200, true, [], true], $run['page'], 'The page of the first stage does not offer the run, shows a report or writes a file');
        $this->assertSame([true, true], $run['refuse']);
        $this->assertSame([200, true, '0'], $run['guest']);
        $this->assertStringContainsString('module registry: old_modules could not be read', $run['nomods'][0]);
        $this->assertSame([[], true, 0], array_slice($run['nomods'], 1), 'A registry that could not be read left the mark modules or wrote the file');
        $this->checkBrokenRun($run);
        $this->assertSame(['modules' => '6.3.0'], $run['broken']['marks']);
        $this->assertSame(['points' => 'missing', 'ratings' => 'missing', 'fields' => 'missing'], $run['broken']['manifest']);
        $this->assertSame(503, $run['broken']['guest']);
        $this->assertSame(1, $run['broken']['dupes'], 'The schema file did not remove the later poll vote of one address');
    }

    # The schema file runs on the tables of a real 6.2 site without a failed statement on the first run, every unit finishes, and the repeats change nothing
    # The attachment unit turns the forum address of a file the folder holds and leaves a missing one in the post and in the message
    #[Test]
    public function aRealSiteFinishesOnTheFirstRun(): void
    {
        $run = $this->getRun();
        $marks = ['fields' => '6.3.0', 'modules' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0'];
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([], $run[$name]['failed'], $name);
            $this->assertSame($marks, $run[$name]['marks'], $name);
            $this->assertSame(['points' => 'verified', 'ratings' => 'verified', 'fields' => 'verified'], $run[$name]['manifest'], $name);
            $this->assertSame('1', $run[$name]['close'], $name);
            $this->assertSame([3, 35], $run[$name]['users'], $name);
            $this->assertSame(['targets' => 4, 'votes' => 0], $run[$name]['rating'], $name);
            $this->assertSame(['old' => 300, 'next' => 301], $run[$name]['ids'], $name);
            $this->assertSame(0, $run[$name]['dupes'], $name.': a repeat removed a vote');
        }
        $this->assertSame(['account', 'forum'], array_keys($run['first']['ratings']));
        $this->assertTrue($run['third']['same']);
        $want = ['[attach=slaed_cms_2026-07-13_22-01-38.png align=none title=title size=full] [img]uploads/forum/gone.png[/img]', '[url=uploads/account/gone.zip]Gone[/url]'];
        $this->assertSame($want, $run['first']['attach'], 'The attachment unit did not turn the address of a file its folder holds, or turned a missing one');
    }

    # The structure after three runs over the 6.2 schema equals a clean installation of the release, table for table, column for column and index for index
    #[Test]
    public function theUpdatedSchemaEqualsACleanInstallation(): void
    {
        $run = $this->getRun();
        $this->assertSame($run['fresh'][0], $run['fresh'][1]);
        $this->assertSame([], $run['schema']);
    }

    # Two guests vote in one poll with the statement of core/system.php: the unique key of 6.2 on target and account is gone, the one on target and address stays
    #[Test]
    public function twoGuestsVoteInOnePoll(): void
    {
        $this->assertSame([true, true], $this->getRun()['votes']);
    }

    # A material created and deleted after the update leaves the counter of _nodes above it, and one more run of the update does not bring it back down
    #[Test]
    public function aRepeatKeepsTheCounterOfNodes(): void
    {
        $last = $this->getRun()['last'];
        $this->assertSame([], $last['failed']);
        $this->assertSame(300, $last['old']);
        $this->assertGreaterThan($last['old'], $last['id']);
        $this->assertSame($last['id'] + 1, $last['before']);
        $this->assertSame($last['before'], $last['after']);
    }

    # The panel stays myadm.php, now the shipped loader in place of the one of 6.2
    # The language and the address of the 6.2 site survive an update asked on another host
    # The blocks of removed modules are switched off with their names while a block of the owner stays, and the switches of the owner survive the repeat
    #[Test]
    public function theSiteKeepsWhatItOwns(): void
    {
        $run = $this->getRun();
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([false, true, 'myadm', true], $run[$name]['panel'], $name);
            $this->assertSame(['ru', $run['guard']], $run[$name]['site'], $name);
        }
        $this->assertSame([200, 200, 200, 404], $run['panel']);
        $this->assertSame(['news.php', 0, 0], [trim($run['first']['blocks'][0]), $run['first']['blocks'][1], $run['first']['blocks'][2]]);
        $this->assertSame(['jokes.php, news.php', 0, 1], [trim($run['second']['blocks'][0]), $run['second']['blocks'][1], $run['second']['blocks'][2]]);
        $this->assertSame(['1', '1', true], $run['first']['owner']);
        $this->assertSame(['0', '0', true], $run['second']['owner']);
        $this->assertSame([], $run['first']['modules']['old']);
    }

    # After the update the panel creates types: news is refused by the category the 6.2 site kept, docs and content are created and docs is switched on
    #[Test]
    public function thePanelCreatesTypesOnTheUpdatedSite(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 422], array_slice($run['type']['news'], 0, 2));
        $this->assertStringContainsString('(categories)', $run['type']['news'][2]);
        $this->assertSame([200, 303, '', [0, 1]], $run['type']['docs']);
        $this->assertSame([200, 303, '', [0, 1]], $run['type']['content']);
        $this->assertSame([200, true, 303, 1, ''], $run['status']);
        $this->assertSame(['guest' => 503, 'admin' => 200], $run['public']);
    }

    # The copy logs no PHP or SQL error; the site log holds only the 404 of the old panel file and the type operations the panel published
    #[Test]
    public function theLogsStayClean(): void
    {
        $logs = $this->getRun()['logs'];
        $this->assertSame([[], []], [$logs['error_php'], $logs['error_sql']]);
        foreach ($logs['error_site'] as $line) {
            $row = json_decode($line, true);
            $this->assertTrue(($row['level'] ?? '') === 'info' || [$row['http_code'] ?? 0, $row['url'] ?? ''] === [404, '/admin.php'], $line);
        }
    }

    # The earlier schema reaches the clean one: its negative balance becomes 0 and is counted once, before the schema file of the first run that stops
    # An account without an address, a comment without an author and a long module name pass strict mode, every unit finishes and the repeats change nothing
    #[Test]
    public function anEarlierSiteReachesTheCleanSchema(): void
    {
        $run = $this->getRun('update62early');
        $this->assertSame([[0, ''], [4, 30]], [$run['dump'], $run['before']]);
        $this->assertSame([[], true, 1], array_slice($run['nomods'], 1), 'The negative balance was not set to 0 and counted before the schema file');
        $this->checkBrokenRun($run);
        $marks = ['fields' => '6.3.0', 'modules' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0'];
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([], $run[$name]['failed'], $name);
            $this->assertSame($marks, $run[$name]['marks'], $name);
            $this->assertSame(['points' => 'verified', 'ratings' => 'verified', 'fields' => 'verified'], $run[$name]['manifest'], $name);
            $this->assertSame([[4, 35], 0], [$run[$name]['users'], $run[$name]['negative']], $name);
        }
        $this->assertSame([], $run['schema'], 'The update leaves a column, an index, a key or a check of the earlier schema apart from a clean installation');
        $this->assertSame([[], []], [$run['logs']['error_php'], $run['logs']['error_sql']]);
    }
}
