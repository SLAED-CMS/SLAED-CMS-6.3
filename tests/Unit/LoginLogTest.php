<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The login table _login_log of docs/9-ADMIN-LOG-2026.md: one writer, one reader, the count of the monitor and the cleanup of the daily job
final class LoginLogTest extends TestCase
{
    private static array $probe = [];

    private static string $work = '';

    # The probe tests/Support/login_probe.php boots the real core with both login switches off and works in a disposable schema it drops again
    # Run the probe once and memoize its report for every test in this class
    private function getRun(string $name): array
    {
        if (self::$probe === []) {
            self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_login_'.bin2hex(random_bytes(4));
            $script = dirname(__DIR__).'/Support/login_probe.php';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'][$name];
    }

    # Remove the scratch tree of the probe, configuration copy included
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

    # Every attempt is one row whatever the switches say, two within one second are two, a name of no account is uid 0, OAuth is its way, and no file journal is written
    #[Test]
    public function everyAttemptIsOneRow(): void
    {
        $run = $this->getRun('write');
        $want = [
            [2, 0, 'ok', 'login', true, true],
            [2, 0, 'password', 'login', true, true],
            [2, 1, 'ok', 'login', true, true],
            [2, 1, 'blocked', 'login', true, true],
            [0, 0, 'password', 'login', true, true],
            [3, 0, 'ok', 'oauth', true, true],
        ];
        $this->assertSame($want, $run['rows']);
        $this->assertNotContains('user.log', $run['files'], 'log_u is off and user.log was written');
        $this->assertNotContains('admin.log', $run['files'], 'log_a is off and admin.log was written');
    }

    # A profile reads only the rows of its own account: not the administrator of the same id and not a name that matched none
    #[Test]
    public function aProfileReadsOnlyItsOwnRows(): void
    {
        $run = $this->getRun('filter');
        $this->assertSame(['rows' => [[2, 0, 'password', 'anna'], [2, 0, 'ok', 'anna']], 'total' => 2], $run['anna']);
        $this->assertSame([[0, 0, 'password', '']], $run['ghost']['rows'], 'a name of no account is not a row of uid 0 alone');
    }

    # The panel filters by side, result, way and period, a read of no rows counts alone, and the pages run newest first
    #[Test]
    public function thePanelFilters(): void
    {
        $run = $this->getRun('filter');
        $this->assertSame([[2, 1, 'blocked', 'root'], [2, 1, 'ok', 'root']], $run['panel']['rows']);
        $this->assertSame([[2, 1, 'blocked', 'root']], $run['blocked']['rows']);
        $this->assertSame([[3, 0, 'ok', 'boris']], $run['oauth']['rows']);
        $this->assertSame([5, 1], [$run['recent'], $run['older']], 'the period splits the rows of today from the one ten days old');
        $this->assertSame(['rows' => [], 'total' => 4], $run['count']);
        $this->assertSame(['rows' => [[2, 0, 'ok', 'anna'], [3, 0, 'ok', 'boris']], 'total' => 6], $run['page']);
    }

    # The monitor counts the refused logins of the panel within its hours and none of the site
    #[Test]
    public function theMonitorCountsRefusedPanelLogins(): void
    {
        $this->assertSame([1, 2], $this->getRun('monitor'));
    }

    # The daily job removes the rows older than 90 days and keeps the younger ones
    #[Test]
    public function theDailyJobRemovesOldRows(): void
    {
        $run = $this->getRun('clean');
        $this->assertSame([0, 89], $run['left']);
        $this->assertStringEndsWith('and 1 logins', $run['message']);
    }

    # Every caller passes the account it found, the result and the way, and an OAuth login reaches the table as oauth
    #[Test]
    public function theCallersNameAccountResultAndWay(): void
    {
        $root = dirname(__DIR__, 2);
        $panel = (string)file_get_contents($root.'/admin/index.php');
        $site = (string)file_get_contents($root.'/modules/account/index.php');
        $this->assertStringContainsString("addLoginLog(\$lid, true, 'ok', \$name);", $panel);
        $this->assertStringContainsString("addLoginLog(\$lid, true, \$block ? 'blocked' : 'password', \$name);", $panel);
        $this->assertStringContainsString("addLoginLog(\$uid, false, 'ok', \$name, \$way);", $site);
        $this->assertMatchesRegularExpression("/function oauthlogin\\(.*?setUserLogin\\([^;]*, 'oauth'\\);/s", $site);
        $this->assertSame(0, preg_match('/addLoginReport\\(/', $panel.$site), 'the old writer is still called');
    }
}
