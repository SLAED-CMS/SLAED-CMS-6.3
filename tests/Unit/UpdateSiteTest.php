<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The 6.3 update of setup/index.php carries a real 6.2 site over (docs/NODE.md, The 6.3 update). tests/Fixtures/update62 is that site: the CREATE TABLE
 * statements of all 33 tables a real 6.2 site dumped, a small invented seed and the configuration files 6.2 wrote. tests/Support/install_probe.php in its
 * update mode loads it into a disposable MariaDB database, serves a copy of the release around it and asks the installer over real HTTP: refused without
 * the key, refused by the preflight, stopped by a broken schema file, then a first run, a repeat after the owner changed switches and a third run; the result
 * is compared with a clean installation of the same schema, two guests vote in one poll, the panel and Node are walked, and a last run follows a deleted material.
 */
final class UpdateSiteTest extends TestCase
{
    private static array $probe = [];

    # Run the update probe once on the 6.2 fixture and memoize the update report; a probe that fails is a failure, not a skip
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/install_probe.php';
            $site = dirname(__DIR__).'/Fixtures/update62';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_site';
            $args = [$work, 'update', $site.'/site.sql', $site.'/config', 'old'];
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            self::$probe = $data['runs']['update'] + ['logs' => $data['runs']['logs']];
        }
        return self::$probe;
    }

    # The fixture loads, the installer refuses without the key and on a MyISAM table without touching a file, and a broken schema file stops before any unit
    #[Test]
    public function theUpdateRefusesBeforeItWritesAnything(): void
    {
        $run = $this->getRun();
        $this->assertSame([0, ''], $run['dump']);
        $this->assertSame([3, 35], $run['before']);
        $this->assertSame([true, true], $run['lock']);
        $this->assertSame([true, true, true], $run['refuse']);
        $this->assertSame([200, true, '0'], $run['guest']);
        $this->assertContains('old_probe_missing', $run['broken']['failed']);
        $this->assertSame(['modules' => '6.3.0'], $run['broken']['marks']);
        $this->assertSame(['points' => 'missing', 'ratings' => 'missing', 'fields' => 'missing'], $run['broken']['manifest']);
        $this->assertSame(503, $run['broken']['guest']);
    }

    # The schema file runs on the tables of a real 6.2 site without a failed statement on the first run, every unit finishes, and the repeats change nothing
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
            $this->assertSame(['targets' => 3, 'votes' => 0], $run[$name]['rating'], $name);
            $this->assertSame(['old' => 300, 'next' => 301], $run[$name]['ids'], $name);
        }
        $this->assertSame(['account', 'forum', 'shop'], array_keys($run['first']['ratings']));
        $this->assertTrue($run['third']['same']);
        $this->assertFalse($run['unlock']);
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

    # The panel stays myadm.php, the language and the address of the 6.2 site survive an installer asked in German on another host,
    # the blocks of removed modules are switched off with their names while a block of the owner stays, and the switches of the owner survive the repeat
    #[Test]
    public function theSiteKeepsWhatItOwns(): void
    {
        $run = $this->getRun();
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([false, true, 'myadm'], $run[$name]['panel'], $name);
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
}
