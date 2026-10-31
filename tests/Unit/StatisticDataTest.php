<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The one reading of the counter behind the statistic page, on a sample counter: a month and a day against their fields, the archive with its gap and kept sums, the pace
final class StatisticDataTest extends TestCase
{
    private static ?array $probe = null;

    private static string $work = '';

    # Run tests/Support/statistic_probe.php once in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_statistic_'.bin2hex(random_bytes(4));
        $script = dirname(__DIR__).'/Support/statistic_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # Remove the scratch tree of the probe
    public static function tearDownAfterClass(): void
    {
        $drop = static function (string $dir) use (&$drop): void {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') continue;
                is_dir($dir.'/'.$name) ? $drop($dir.'/'.$name) : unlink($dir.'/'.$name);
            }
            rmdir($dir);
        };
        if (self::$work !== '') $drop(self::$work);
    }

    # The running month joins days.log and statistic.log, and its sums are the sums of the fields of those three lines
    #[Test]
    public function theMonthAddsUpItsFields(): void
    {
        $data = $this->getProbe()['month'];
        $this->assertSame('2026-10', $data['key']);
        $this->assertTrue($data['is_run']);
        $this->assertSame(['02.10.2026', '03.10.2026', '04.10.2026'], array_column($data['rows'], 'date'));
        $sum = $data['sum'];
        $this->assertSame(600, $sum['u']);
        $this->assertSame(3000, $sum['h']);
        $this->assertSame(1200, $sum['home']);
        $this->assertSame(120, $sum['ref']);
        $this->assertSame(60, $sum['bot']);
        $this->assertSame(420, $sum['aud']);
        $this->assertSame(6, $sum['usr']);
        $this->assertSame(['Chrome 1' => 90, 'Other' => 30], $sum['dims']['browsers']);
        $this->assertSame(['desktop' => 60, 'mobile' => 45, 'bot' => 15], $sum['dims']['devices']);
    }

    # The average page divides the load by the pages that carried one, and an archive of the first eight fields has none
    #[Test]
    public function theLoadIsThatOfTheAveragePage(): void
    {
        $sum = $this->getProbe()['month']['sum'];
        $this->assertSame(180, $sum['pages']);
        $this->assertEquals(70, $sum['avg_gen']);
        $this->assertEquals(20, $sum['avg_sql']);
        $this->assertEquals(2, $sum['avg_mem']);
        $old = $this->getProbe()['old']['sum'];
        $this->assertSame(0, $old['pages']);
        $this->assertNull($old['avg_gen']);
    }

    # The four shares of the rings: people of all devices, phones of people, the returned of the sessions, search of the sources
    #[Test]
    public function theSharesAreTheOnesOfTheRings(): void
    {
        $this->assertSame(['human' => 88, 'mobile' => 43, 'ret' => 25, 'search' => 30], $this->getProbe()['month']['sum']['shares']);
        $this->assertSame(['human' => 0, 'mobile' => 0, 'ret' => 0, 'search' => 0], $this->getProbe()['old']['sum']['shares']);
    }

    # The day is the running one, the last whole one on the switch, the picked one by its place, and the last of a closed month
    #[Test]
    public function theDayFollowsItsScope(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(['04.10.2026', 2, true], [$probe['month']['day']['date'], $probe['month']['at'], $probe['month']['is_live']]);
        $this->assertSame(['03.10.2026', 1], [$probe['yesterday']['day']['date'], $probe['yesterday']['at']]);
        $this->assertSame(['02.10.2026', 0], [$probe['picked']['day']['date'], $probe['picked']['at']]);
        $this->assertSame(['27.09.2026', false, false], [$probe['closed']['day']['date'], $probe['closed']['is_live'], $probe['closed']['is_run']]);
        $this->assertSame(100, $probe['month']['day']['u']);
        $this->assertSame(500, $probe['month']['day']['h']);
        $this->assertSame('03.10.2026', $probe['month']['past']['date']);
        $this->assertSame('27.09.2026', $probe['picked']['past']['date'], 'The day before the first day lives in the month before');
    }

    # A month names its neighbours among the months that exist, so a step goes over a gap, and an address that is no month opens the running one
    #[Test]
    public function theStepsGoOverAGap(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(['2026-09', ''], [$probe['month']['prev'], $probe['month']['next']]);
        $this->assertSame(['2026-07', '2026-10'], [$probe['closed']['prev'], $probe['closed']['next']]);
        $this->assertSame(['', '2026-09'], [$probe['old']['prev'], $probe['old']['next']]);
        $this->assertSame('2026-10', $probe['wrong']['key']);
    }

    # The change is measured against the same days of the month before; a month with none before it has nothing to compare
    #[Test]
    public function theChangeTakesTheSameDays(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(100, $probe['month']['then']['u'], 'Of September only the third falls between the second and the fourth');
        $this->assertSame(1, $probe['month']['then']['days']);
        $this->assertSame(800, $probe['old']['sum']['u']);
        $this->assertNull($probe['old']['then']);
    }

    # The rhythm reaches back into the month before, ends on the day of the scope and stops at a gap, so its axis holds no hole
    #[Test]
    public function theRunEndsOnTheDay(): void
    {
        $probe = $this->getProbe();
        $this->assertSame('04.10.2026', array_column($probe['month']['run'], 'date')[count($probe['month']['run']) - 1]);
        $this->assertSame('02.10.2026', array_column($probe['picked']['run'], 'date')[count($probe['picked']['run']) - 1]);
        $this->assertContains('27.09.2026', array_column($probe['month']['run'], 'date'));
        $this->assertNotContains('31.07.2026', array_column($probe['month']['run'], 'date'));
    }

    # The week averages each weekday and leaves the running day out, so an hour that has not run does not pull it down
    #[Test]
    public function theWeekLeavesTheRunningDayOut(): void
    {
        $week = $this->getProbe()['month']['week'];
        $this->assertEquals(10, $week[4][9], 'The Friday of the second');
        $this->assertEquals(20, $week[5][9], 'The Saturday of the third');
        $this->assertEquals(0, $week[6][14], 'The running Sunday');
    }

    # The archive holds a month per file and no row for the missing one
    #[Test]
    public function theArchiveHasItsGap(): void
    {
        $arc = $this->getProbe()['archive'];
        $this->assertSame(['2026-07', '2026-09'], array_keys($arc));
        $this->assertSame([2, 800, 4000], [$arc['2026-07']['days'], $arc['2026-07']['u'], $arc['2026-07']['h']]);
        $this->assertSame([6, 800, 4000], [$arc['2026-09']['days'], $arc['2026-09']['u'], $arc['2026-09']['h']]);
    }

    # A closed month is read once: its sums stand until the stamp of its file changes, and then they follow the file
    #[Test]
    public function theSumsOfAClosedMonthAreKept(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(800, $probe['kept']['u']);
        $this->assertSame(1400, $probe['fresh']['u']);
    }

    # The rate is the pages of the last sixty minutes: half an hour past counts half of the hour before, and in the first hour the running one is scaled up
    #[Test]
    public function theRateCoversSixtyMinutes(): void
    {
        $rate = $this->getProbe()['rate'];
        $this->assertEquals(110, $rate[0]);
        $this->assertEquals(20, $rate[1]);
    }

    # The steps of the pace against the norm of that hour; under twenty pages a lull, and a norm under twenty is noise that reads as an ordinary day
    #[Test]
    public function thePaceStepsOverTheNorm(): void
    {
        $pace = $this->getProbe()['pace'];
        foreach (['lull', 'calm', 'day', 'rise', 'rush'] as $step) $this->assertSame($step, $pace[$step]['pace']);
        $this->assertSame('day', $pace['noise']['pace']);
        $this->assertEquals(1, $pace['noise']['share']);
        $this->assertEquals(100, $pace['rush']['norm']);
        $this->assertEquals(3, $pace['rush']['share']);
    }

    # The norm of the running day takes the same weekday of the four weeks before, whichever month holds them, and no other day
    #[Test]
    public function theNormTakesTheSameWeekday(): void
    {
        $pace = $this->getProbe()['month']['pace'];
        $this->assertEquals(55, $pace['norm']);
        $this->assertSame(6, $pace['week']);
        $this->assertSame(15, $pace['hour']);
        $this->assertNull($this->getProbe()['closed']['pace']);
    }

    # A site that stopped counting keeps its last month in days.log with no file for it: the month stays in the list until the month itself is the running one
    #[Test]
    public function aMonthNeverRolledOverStaysOpen(): void
    {
        $probe = $this->getProbe();
        $this->assertContains('2026-08', $probe['staleset']);
        $this->assertNotContains('2026-08', $probe['stalenow']);
        $this->assertSame('2026-08', $probe['stale']['key']);
        $this->assertSame(['15.08.2026'], array_column($probe['stale']['rows'], 'date'));
    }

    # A list at its limit keeps every row; past it the tail joins the overflow, which takes a place of its own
    #[Test]
    public function aListJoinsItsTailOnlyPastItsLimit(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $probe['six']);
        $this->assertSame([70, 60, 50, 40, 30, 30], $probe['seven']);
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'Other'], $probe['spare']);
    }

    # Seven days are compared with the seven before them and with nothing when fewer stand there, as the two weeks must not overlap
    #[Test]
    public function aWeekIsComparedWithAWholeWeek(): void
    {
        $probe = $this->getProbe();
        $this->assertSame('', $probe['week10']);
        $this->assertSame('+0%', $probe['week14']);
    }

    # Before the first hit of a month the counter still holds the last day of the old one: the page opens the new month empty, and the old one has all its days
    #[Test]
    public function aMonthNotYetRolledStandsApart(): void
    {
        $probe = $this->getProbe();
        $this->assertSame('2026-10', $probe['rollover']['key']);
        $this->assertSame([], $probe['rollover']['rows']);
        $this->assertNull($probe['rollover']['day']);
        $this->assertSame('30.09.2026', $probe['before']['rows'][count($probe['before']['rows']) - 1]['date']);
    }
}
