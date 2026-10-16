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
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/version.php';
    }

    # The marks of config/update.php with the step marks reduced to their versions, since each carries the moment of its run
    private function getMarks(array $marks): array
    {
        if (isset($marks['steps'])) $marks['steps'] = array_keys($marks['steps']);
        return $marks;
    }

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

    # The connection of update.php runs in the zone of the 6.2 site: set to +13, far from the server, the newsletter replay writes NOW() inside the window of the run
    #[Test]
    public function theUpdateWritesInTheZoneOfTheSite(): void
    {
        [$from, $stamp, $to] = $this->getRun()['first']['zone'];
        $this->assertNotSame('', $stamp, 'The first run queued no newsletter mail to read its moment from');
        $this->assertGreaterThanOrEqual($from, $stamp, 'update.php wrote NOW() before the run began in the zone of the site');
        $this->assertLessThanOrEqual($to, $stamp, 'update.php wrote NOW() after the run ended in the zone of the site');
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
        $this->assertSame(['modules' => '6.3.0'], $run['broken']['marks'], 'A run stopped at the schema file left a step mark of the code');
        $this->assertSame(['points' => 'missing', 'ratings' => 'missing', 'fields' => 'missing'], $run['broken']['manifest']);
        $this->assertSame(503, $run['broken']['guest']);
        $this->assertSame(1, $run['broken']['dupes'], 'The schema file did not remove the later poll vote of one address');
        $this->assertSame([2, true, true, true], $run['twins'], 'An address held by two accounts did not stop the run before the schema file with both accounts named');
    }

    # The schema file runs on the tables of a real 6.2 site without a failed statement on the first run, every unit finishes, and the repeats change nothing
    # The attachment unit turns the forum address of a file the folder holds and leaves a missing one in the post and in the message
    #[Test]
    public function aRealSiteFinishesOnTheFirstRun(): void
    {
        $run = $this->getRun();
        $marks = ['fields' => '6.3.0', 'modules' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0', 'steps' => [SL_VERSION]];
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([], $run[$name]['failed'], $name);
            $this->assertSame($marks, $this->getMarks($run[$name]['marks']), $name);
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

    # The times of 6.2 take their forms: a refresh stamp is a number the feed unit sets to 0, a unix expiry of a block or a message is a moment, none is NULL, repeats keep them
    # The first schema run converts in the release zone of a site without its own, Europe/Berlin, at the offset its connection carries today
    #[Test]
    public function theTimesOfASiteTakeTheirForms(): void
    {
        $run = $this->getRun();
        $zone = new \DateTimeZone((new \DateTime('now', new \DateTimeZone('Europe/Berlin')))->format('P'));
        $at = fn(int $v): string => (new \DateTime('@'.$v))->setTimezone($zone)->format('Y-m-d H:i:s');
        $want = [[0, 0, 0], [null, null, $at(1700003600)], [$at(1700007200), null]];
        foreach (['first', 'second', 'third'] as $name) $this->assertSame($want, $run[$name]['times'], $name);
    }

    # The empty values and the language names of 6.2 take their domains in every table, a false birthday is none, an unserved language is the site one
    #[Test]
    public function theEmptyValuesOfASiteTakeTheirDomains(): void
    {
        $run = $this->getRun();
        $users = [['ru', 0, null, '', '', ''], ['en', 1, '1990-05-17', '', '', ''], ['', 0, null, '', '', '']];
        $want = [$users, ['', 0, 'de'], ['', 'uk'], 'ru'];
        foreach (['first', 'second', 'third'] as $name) $this->assertSame($want, $run[$name]['nulls'], $name);
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

    # The second stage carries the 6.2 tables of the removed modules into Node: the schema file gave them the 6.3 names the migration reads
    # The news and the file of the fixture and the rows the probe adds before the stage - three news and a help request - become materials of their types
    #[Test]
    public function theSecondStageCarriesTheModulesOfASite(): void
    {
        $run = $this->getRun()['migrate'];
        $this->assertSame([200, true, 200], array_slice($run, 0, 3), 'The page of the second stage offers no run to the main administrator');
        $want = [['files', 'A file', 'Description of the file', ''], ['help', 'A request', '', 'Help'], ['news', 'First news', 'Intro of the first news', ''],
            ['news', 'General', 'Intro of General', ''], ['news', 'Later', 'Intro of Later', ''],
            ['news', 'Second news', 'Intro of the second news', 'Body of the second news'], ['news', 'Waiting', 'Intro of Waiting', '']];
        $this->assertSame($want, $run[3], 'The migration did not carry the rows of a clean 6.2 site');
    }

    # The second stage gives every entity its first address in the fixed order of the migration, hubs before pages and each group by ascending id
    # The category General takes the clean name before the news of the same title, and the forum section A topic before the topic
    # A news waiting for approval, one dated in the future and the request of the private type help get none; a repeat adds nothing
    #[Test]
    public function theSecondStageFillsTheRegistry(): void
    {
        [$rows, $code, $same] = $this->getRun()['routes'];
        $want = [['type', 'docs', 'docs'], ['type', 'content', 'content'], ['type', 'news', 'news'], ['type', 'faq', 'faq'], ['type', 'help', 'help'],
            ['type', 'links', 'links'], ['type', 'files', 'files'], ['cat', 'news/general', 'General'], ['node', 'news/first-news', 'First news'],
            ['node', 'news/second-news', 'Second news'], ['node', 'news/general-2', 'General'], ['node', 'files/a-file', 'A file'], ['cat', 'forum/a-topic', 'A topic'],
            ['topic', 'forum/a-topic-2', 'A topic'], ['user', 'users/alpha', 'alpha'], ['user', 'users/beta', 'beta'], ['user', 'users/gamma', 'gamma'],
            ['poll', 'voting/which-one', 'Which one']];
        $this->assertSame($want, array_map(fn(array $v): array => [$v[0], $v[1], $v[3]], $rows), 'The registry after the migration is not the one of the fixed order');
        $this->assertSame(array_fill(0, count($want), 1), array_map('intval', array_column($rows, 2)), 'A path of the migration is not current');
        $this->assertSame([200, true], [$code, $same], 'A repeat of the second stage changed the registry');
        $runs = substr_count(implode("\n", $this->getRun()['logs']['error_site']), 'the migration of the old modules ran');
        $this->assertSame(2, $runs, 'The second stage did not run twice, so the repeat proves nothing');
    }

    # The panel stays myadm.php, now the shipped loader in place of the one of 6.2
    # The language and the address of the 6.2 site survive an update asked on another host; config/db.php takes the collation of the release and no dead key of 6.2
    # The blocks of removed modules are switched off with their names while a block of the owner stays, and the switches of the owner survive the repeat
    #[Test]
    public function theSiteKeepsWhatItOwns(): void
    {
        $run = $this->getRun();
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([false, true, 'myadm', true], $run[$name]['panel'], $name);
            $this->assertSame(['ru', $run['guard']], $run[$name]['site'], $name);
            $this->assertSame(['utf8mb4_unicode_520_ci', []], $run[$name]['base'], $name.': config/db.php lacks the collation of the release or keeps a key of 6.2');
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
        $marks = ['fields' => '6.3.0', 'modules' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0', 'steps' => [SL_VERSION]];
        foreach (['first', 'second', 'third'] as $name) {
            $this->assertSame([], $run[$name]['failed'], $name);
            $this->assertSame($marks, $this->getMarks($run[$name]['marks']), $name);
            $this->assertSame(['points' => 'verified', 'ratings' => 'verified', 'fields' => 'verified'], $run[$name]['manifest'], $name);
            $this->assertSame([[4, 35], 0], [$run[$name]['users'], $run[$name]['negative']], $name);
        }
        $this->assertSame([], $run['schema'], 'The update leaves a column, an index, a key or a check of the earlier schema apart from a clean installation');
        $this->assertSame([[], []], [$run['logs']['error_php'], $run['logs']['error_sql']]);
    }
}
