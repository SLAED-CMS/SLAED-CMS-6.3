<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the 2026 exact-statistics contracts running against production code:
 * every scenario drives the real updateStatsTrack() through tests/Support/contract_probe.php in
 * isolated CLI processes with COUNTER_DIR and LOGS_DIR redirected to a scratch directory, so
 * parallel hits, day and month rollover, archive conflicts, damaged day logs, and injected lock
 * failures are asserted on the real counter files instead of mocks.
 */
final class StatsContractTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_stats_'.bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
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

    # Run one probe process performing $rep statistics hits for one IP and return the resulting counter state
    private function getHit(string $ip, int $rep = 1): array
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' stathit '.escapeshellarg($this->dir).' '.escapeshellarg($ip).' '.$rep;
        $out = (string)shell_exec($cmd.' 2>&1');
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
        $data = json_decode((string)shell_exec($cmd.' 2>&1'), true);
        $this->assertIsArray($data);
        $this->assertSame(2, $data['blocked'], 'a failed append must report the write error code');
        $this->assertStringContainsString('blocked', $data['log'], 'a failed append must be logged');
        $this->assertSame(0, $data['plain']);
        $this->assertSame(0, $data['again']);
        $this->assertSame('first,second,', $data['body'], 'successful appends must store exactly their records');
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
}
