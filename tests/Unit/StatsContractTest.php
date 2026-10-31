<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Regression tests of the 2026 exact-statistics contracts: every scenario drives the real updateStatsTrack() on real counter files instead of mocks
final class StatsContractTest extends TestCase
{
    private string $dir = '';

    private string $zone = '';

    # The scenarios run tests/Support/contract_probe.php in isolated CLI processes with COUNTER_DIR and LOGS_DIR redirected to a scratch directory
    # Parallel hits, day and month rollover, archive conflicts, damaged day logs and injected lock failures are asserted on the real counter files
    # The expected dates follow the time zone of the site the probe runs in, not the zone of the CLI, so a day boundary between the two cannot split them
    protected function setUp(): void
    {
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_stats_'.bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
        $this->zone = date_default_timezone_get();
        date_default_timezone_set((require dirname(__DIR__, 2).'/config/global.php')['gtime']);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
        $this->deleteTree($this->dir);
    }

    # Remove one scratch tree created by a scenario
    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $dir.'/'.$name;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    # Run one probe process performing $rep statistics hits for one IP, as a guest or as a signed-in user, and return the resulting counter state
    private function getHit(string $ip, int $rep = 1, bool $usr = false): array
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' stathit '.escapeshellarg($this->dir).' '.escapeshellarg($ip).' '.$rep.($usr ? ' user' : '');
        $out = (string)shell_exec($cmd.' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe did not return JSON: '.$out);
        return $data;
    }

    # Run the stats cookie scenario of the probe once in a fresh PHP process and decode its JSON report
    private function getCookieProbe(): array
    {
        static $data = null;
        if ($data !== null) return $data;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' statscookie 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe did not return JSON: '.$out);
        return $data;
    }

    # Start one probe process per IP at once so their locked counter updates really overlap, then wait for all of them
    private function setParallelHits(array $ips, int $rep): void
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $jobs = [];
        foreach ($ips as $ip) {
            $pipes = [];
            $proc = proc_open([PHP_BINARY, $script, 'stathit', $this->dir, $ip, (string)$rep], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($proc, 'probe process could not start');
            $jobs[] = [$proc, $pipes];
        }
        foreach ($jobs as [$proc, $pipes]) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
    }

    # Read one scratch counter file
    private function getFile(string $name): string
    {
        $path = $this->dir.'/'.$name;
        return is_file($path) ? (string)file_get_contents($path) : '';
    }

    # Split the current statistic.log into its counter fields
    private function getFields(): array
    {
        return explode('|', trim($this->getFile('statistic.log')));
    }

    # Build one seed day line for a given date
    private function getSeedLine(string $date): string
    {
        return $date.'|5|20|100|1|2|3|4';
    }

    # Return a past date inside the current month so only the day changes
    private function getSameMonthDate(): string
    {
        return (date('d') !== '01' ? '01.' : '02.').date('m.Y');
    }

    # The sources count the entry of a session once: a click inside the site and a hit without a session of the counter add nothing, though every hit still counts
    #[Test]
    public function sourcesCountOnlyTheEntryOfASession(): void
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' statsource '.escapeshellarg($this->dir).' 2>&1');
        $this->assertIsArray(json_decode($out, true), 'Probe did not return JSON: '.$out);
        $part = $this->getFields();
        $this->assertSame('7', $part[2], 'every hit must be counted');
        $this->assertSame('search:1,direct:1', $part[12], 'a source is counted by the first hit of a session only');
        $this->assertSame(['1', '1'], [$part[4], $part[5]], 'a robot with a referer is a crawler only, so the audience takes it off once');
        $this->assertSame('google.com:1', $part[20], 'the host of an entry from another site');
        $this->assertSame('/:1', $part[21], 'the page of an entry from another site');
        $this->assertSame('OpenAI GPTBot:2,FreshScoutBot:1', $part[22], 'every hit of a robot counts by its name, and a person adds none');
        $this->assertSame('ai:2,other:1', $part[23], 'every hit of a robot counts by its type, one outside the lists as other');
    }

    # Run the rules of the counter once in a fresh PHP process: who is a robot, the source and host of a referer, the key of a page, the limit of a field
    private function getRulesProbe(): array
    {
        static $data = null;
        if ($data !== null) return $data;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' statrules '.escapeshellarg($this->dir).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe did not return JSON: '.$out);
        return $data;
    }

    # A person gets no name and a robot one, by the list or by a word of its agent, and the two callers of the detector agree
    #[Test]
    public function aRobotIsNamedAndAPersonIsNot(): void
    {
        $data = $this->getRulesProbe();
        $this->assertSame([
            'chrome' => '', 'cubot' => '', 'firephp' => '', 'google' => 'Google Bot', 'bing' => 'Bing Bot', 'yandex' => 'Yandex Bot', 'chatgpt' => 'ChatGPT User',
            'fresh' => 'FreshScoutBot', 'curl' => 'curl', 'none' => 'Unknown',
        ], $data['bots']);
        $this->assertSame(['desktop', 'bot'], $data['info']);
        $this->assertSame(['ai', 'search', 'seo', 'social', 'other', ''], $data['types']);
        $this->assertSame(['name' => 'No Accept-Language', 'type' => 'other'], $data['dressed'], 'A script dressed as a browser asks for no language');
        $this->assertSame(['name' => '', 'type' => ''], $data['browser']);
        $this->assertSame([true, false, false, false, true], $data['referers'], 'The own site is told by the domain, not by its name in a foreign query');
        $this->assertSame([], $data['strays'], 'A mask of the settings is answered by a mask of another type');
    }

    # The lists match whole parts of a domain or its end, never a piece of a word, and a host the counter could not hold counts as direct
    #[Test]
    public function aSourceFollowsTheListsByDomain(): void
    {
        $data = $this->getRulesProbe();
        $this->assertSame([
            'none' => 'direct', 'france' => 'search', 'china' => 'search', 'ya' => 'search', 'bare' => 'search', 'maya' => 'referrer', 'micro' => 'referrer',
            'tco' => 'social', 'mobile' => 'social', 'broken' => 'direct', 'chatgpt' => 'ai', 'gemini' => 'ai',
        ], $data['cats']);
        $this->assertSame(['google.com', 'baidu.com', 'bing.com', '', '', ''], $data['hosts']);
    }

    # A page loses its marks of an ad and of a highlighted word, and what would break the line of the counter becomes the overflow
    #[Test]
    public function aPageKeyCannotBreakTheCounter(): void
    {
        $this->assertSame(['/', '/', '/news/one', '/index.php?name=files&op=view&id=12', 'Other', 'Other', 'Other', 'Other'], $this->getRulesProbe()['pages']);
    }

    # A full field gives way to a new key at its rarest, so a burst of addresses early in a day cannot keep a real page out
    #[Test]
    public function aFullFieldLetsAFrequentKeyIn(): void
    {
        $data = $this->getRulesProbe();
        $this->assertSame('a:5,c:1,Other:1', $data['evict']);
        $this->assertSame('a:5,Other:3', $data['other']);
        $this->assertSame('a:5', $data['same']);
        $this->assertSame(3, $data['flood']['/real'] ?? 0, 'a page entered three times stands in the field after the burst');
        $this->assertSame(43, array_sum($data['flood']), 'no hit is lost to the limit');
        $this->assertSame(['hosts' => ['google.com' => 3, 'Other' => 1], 'pages' => ['/' => 2], 'bots' => ['OpenAI GPTBot' => 4], 'bottype' => ['ai' => 4]], $data['row']);
    }

    # Parallel first hits from one IP count the host once and never lose a single hit
    #[Test]
    public function parallelHitsFromOneIpCountTheHostOnce(): void
    {
        $this->setParallelHits(array_fill(0, 6, '203.0.113.10'), 5);
        $part = $this->getFields();
        $this->assertSame(date('d.m.Y'), $part[0]);
        $this->assertSame('1', $part[1], 'one IP must count as one host');
        $this->assertSame('30', $part[2], 'every parallel hit must be counted');
        $this->assertSame('203.0.113.10,', $this->getFile('ips.log'));
    }

    # Parallel first hits from distinct IPs count every host exactly once
    #[Test]
    public function parallelHitsFromDistinctIpsCountEveryHost(): void
    {
        $ips = ['203.0.113.21', '203.0.113.22', '203.0.113.23', '203.0.113.24', '203.0.113.25', '203.0.113.26'];
        $this->setParallelHits($ips, 5);
        $part = $this->getFields();
        $this->assertSame('6', $part[1], 'every distinct IP must count as one host');
        $this->assertSame('30', $part[2]);
        foreach ($ips as $ip) {
            $this->assertStringContainsString($ip.',', $this->getFile('ips.log'));
        }
    }

    # A day change archives the previous line into days.log, counts the new day, and resets the unique sets
    #[Test]
    public function dayRolloverKeepsThePreviousDayAndResetsSets(): void
    {
        $seed = $this->getSeedLine($this->getSameMonthDate());
        file_put_contents($this->dir.'/statistic.log', $seed);
        file_put_contents($this->dir.'/ips.log', '198.51.100.1,');
        $this->getHit('203.0.113.30');
        $this->assertSame($seed."\n", $this->getFile('days.log'), 'the previous day must be preserved');
        $part = $this->getFields();
        $this->assertSame(date('d.m.Y'), $part[0]);
        $this->assertSame('1', $part[1]);
        $this->assertSame('1', $part[2]);
        $this->assertSame('101', $part[3], 'total hits must continue across the day change');
        $this->assertSame('203.0.113.30,', $this->getFile('ips.log'), 'unique sets must restart with the new day');
    }

    # A month change moves days.log into the archive named after the stored day, not after today
    #[Test]
    public function monthRolloverArchivesDaysLogUnderTheStoredMonth(): void
    {
        $old = date('m.Y', (int)strtotime('first day of last month'));
        $seed = $this->getSeedLine('15.'.$old);
        file_put_contents($this->dir.'/statistic.log', $seed);
        file_put_contents($this->dir.'/days.log', $this->getSeedLine('14.'.$old)."\n");
        $this->getHit('203.0.113.31');
        $name = 'statistic_'.substr($old, 3).'-'.substr($old, 0, 2).'.log';
        $this->assertFileExists($this->dir.'/statistic/'.$name);
        $this->assertStringContainsString($seed, (string)file_get_contents($this->dir.'/statistic/'.$name));
        $this->assertFileDoesNotExist($this->dir.'/days.log', 'a successful archive move consumes days.log');
        $this->assertSame(date('d.m.Y'), $this->getFields()[0]);
    }

    # A conflicting day line already present in days.log aborts the transition instead of destroying state
    #[Test]
    public function conflictingDayLineAbortsWithoutDataLoss(): void
    {
        $date = $this->getSameMonthDate();
        $seed = $this->getSeedLine($date);
        $rival = $date.'|9|9|9|9|9|9|9'."\n";
        file_put_contents($this->dir.'/statistic.log', $seed);
        file_put_contents($this->dir.'/days.log', $rival);
        $data = $this->getHit('203.0.113.32');
        $this->assertSame($rival, $this->getFile('days.log'), 'the existing day log must stay untouched');
        $this->assertSame($seed, trim($this->getFile('statistic.log')), 'the pending day must stay untouched');
        $this->assertStringContainsString('days.log day conflict for '.$date, $data['log']);
    }

    # An unterminated tail from an earlier partial append is repaired before the day line is stored
    #[Test]
    public function damagedDaysLogTailIsRepairedBeforeAppend(): void
    {
        $seed = $this->getSeedLine($this->getSameMonthDate());
        file_put_contents($this->dir.'/statistic.log', $seed);
        file_put_contents($this->dir.'/days.log', '01.01.2020|1|1|1');
        $this->getHit('203.0.113.33');
        $this->assertSame($seed."\n", $this->getFile('days.log'), 'the truncated fragment must be dropped');
    }

    # A failed lock acquisition leaves every counter file untouched instead of writing a partial state
    #[Test]
    public function failedLockLeavesCountersUntouched(): void
    {
        $seed = date('d.m.Y').'|1|7|70|0|0|0|0';
        file_put_contents($this->dir.'/statistic.log', $seed);
        file_put_contents($this->dir.'/ips.log', '198.51.100.2,');
        mkdir($this->dir.'/statistic.lock');
        $this->getHit('203.0.113.34');
        $this->assertSame($seed, trim($this->getFile('statistic.log')), 'no counter may move without the lock');
        $this->assertSame('198.51.100.2,', $this->getFile('ips.log'));
        rmdir($this->dir.'/statistic.lock');
        $this->getHit('203.0.113.34');
        $part = $this->getFields();
        $this->assertSame('2', $part[1], 'the next successful hit still records the entity');
        $this->assertSame('8', $part[2]);
    }

    # A stale host counter is corrected from the unique set instead of being incremented blindly
    #[Test]
    public function staleHostCounterSelfHealsFromTheSet(): void
    {
        file_put_contents($this->dir.'/statistic.log', date('d.m.Y').'|0|4|40|0|0|0|0');
        file_put_contents($this->dir.'/ips.log', '198.51.100.3,');
        $this->getHit('203.0.113.35');
        $part = $this->getFields();
        $this->assertSame('2', $part[1], 'hosts must equal the set size, not the stale counter');
        $this->assertSame('5', $part[2]);
    }

    # An append that cannot be written is reported as an error while a working append stores exactly the given record
    #[Test]
    public function unwritableAppendIsReportedAndWorkingAppendStaysExact(): void
    {
        mkdir($this->dir.'/blocked');
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' appendfail '.escapeshellarg($this->dir);
        $out = (string)shell_exec($cmd.' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe did not return JSON: '.$out);
        $this->assertFalse($data['blocked'], 'a failed append must report failure');
        $this->assertStringContainsString('blocked', $data['log'], 'a failed append must be logged');
        $this->assertTrue($data['plain']);
        $this->assertTrue($data['again']);
        $this->assertSame('first,second,', $data['body'], 'successful appends must store exactly their records');
        $this->assertTrue($data['named']);
        $this->assertSame($data['namedpath'], $data['namedbody'], 'data that names an existing file was replaced by the content of that file');
        $this->assertTrue($data['addr']);
        $this->assertSame([], $data['warn'], 'an appended address was probed as a path, which open_basedir turns into a warning');
        $this->assertSame('141.148.184.29,', $data['addrbody']);
    }

    # A user hit records the visitor name once and derives the user counter from the set
    #[Test]
    public function repeatedHitsNeverDoubleCountOneHost(): void
    {
        $this->getHit('203.0.113.36', 3);
        $part = $this->getFields();
        $this->assertSame('1', $part[1]);
        $this->assertSame('3', $part[2]);
        $this->assertSame('203.0.113.36,', $this->getFile('ips.log'));
        $this->assertSame('', $this->getFile('user.log'), 'a guest never enters the user set');
    }

    # Repeated hits of one signed-in user enter the user set once and the user counter follows the set
    #[Test]
    public function repeatedUserHitsCountTheUserOnce(): void
    {
        $data = $this->getHit('203.0.113.37', 3, true);
        $this->assertSame(1, $data['isuser'], 'the probe found no account to sign in with');
        $part = $this->getFields();
        $this->assertSame('1', $part[7]);
        $this->assertSame(1, substr_count($this->getFile('user.log'), ','), 'one user entered the set more than once');
        $this->assertNotSame(',', $this->getFile('user.log'));
    }

    # The real updateStatsCookie() continues a v2 session, keeps the country cache, and exposes no uniqueness state
    #[Test]
    public function statsCookieVtwoRoundTripsWithoutUniquenessState(): void
    {
        $data = $this->getCookieProbe();
        $this->assertSame(['sess', 'country'], $data['v2_keys']);
        $this->assertSame(5, $data['v2_depth']);
        $this->assertFalse($data['v2_isnew']);
        $this->assertSame('DE', $data['v2_country']);
    }

    # The real updateStatsCookie() discards legacy v1 cookies and starts a fresh session
    #[Test]
    public function statsCookieVoneIsDiscarded(): void
    {
        $data = $this->getCookieProbe();
        $this->assertTrue($data['v1_isnew']);
        $this->assertSame(1, $data['v1_depth']);
    }
}
