<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The time forms of the schema over real requests, driven by tests/Support/route_probe.php time: a moment is DATETIME, a machine timer unix seconds, never text
final class SchemaTimeTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its time mode and memoize the run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_time_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' time 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The requests wrote SQL errors');
            self::$probe = $data['runs']['time'];
        }
        return self::$probe;
    }

    # The activation sweeps the registrations older than a day by their regdate, and the poll view sweeps the votes outside the window by their unix time
    #[Test]
    public function theCleanupsReadTheirOwnClocks(): void
    {
        $run = $this->getRun();
        $this->assertSame(['fresh'], $run['temp'], 'The activation kept a stale registration or swept a fresh one');
        $this->assertSame([200, ['10.0.0.2']], $run['rating'], 'The poll view kept a vote outside its window or swept one inside it');
    }

    # A feed block whose stamp is due is refreshed and keeps its text when the feed fails, a fresh one is left alone, and an expired block switches off with no expiry left
    #[Test]
    public function aBlockRefreshesAndExpires(): void
    {
        [$code, $stale, $fresh, $gone, $kept] = $this->getRun()['blocks'];
        $this->assertSame(200, $code);
        $this->assertSame([1, 'kept feed'], $stale, 'A due feed block was not stamped or lost its text');
        $this->assertSame([1], $fresh, 'A fresh feed block was refreshed');
        $this->assertSame([0, null], $gone, 'An expired block stayed on or kept its expiry');
        $this->assertSame([1, 1], $kept, 'A block that has not expired was touched');
    }

    # An expired message switches off with no expiry left, a live one stays and is shown on the next request
    #[Test]
    public function aMessageExpires(): void
    {
        $this->assertSame([[0, null], [1, 1], true], $this->getRun()['message']);
    }

    # The session sweep removes the visitors whose timer lies behind the session time and keeps the others
    #[Test]
    public function theSessionSweepReadsTheTimer(): void
    {
        $this->assertSame(['newbot'], $this->getRun()['session']);
    }

    # The panel sets the expiry of a block and of a message from days as a moment that far ahead
    #[Test]
    public function thePanelSetsAnExpiryFromDays(): void
    {
        $this->assertSame([2, 3], array_map('intval', $this->getRun()['panel']));
    }

    # A topic newer than the last visit is new and an older one is not; an account that never came back and a guest see both as new, and the visit is recorded from never
    #[Test]
    public function theForumMarksWhatCameAfterTheLastVisit(): void
    {
        $run = $this->getRun();
        $this->assertSame(['clara' => ['old', 'new'], 'boris' => ['new', 'new'], 'guest' => ['new', 'new']], $run['forum']);
        $this->assertSame([1, 1], $run['visit'], 'The visit of an account that never came back or of a returning one was not recorded');
    }
}
