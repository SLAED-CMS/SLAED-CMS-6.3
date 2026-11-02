<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# One settings file, three forms: security, logs and the jobs of the scheduler, over real requests, driven by tests/Support/route_probe.php with the argument forms
final class SecurityFormTest extends TestCase
{
    # The raw answer of each probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static array $probe = [];

    private static string $work = '';

    # Run the probe once and memoize its run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_forms_'.bin2hex(random_bytes(4));
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $out = self::$outs[__FUNCTION__] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' forms 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs']);
            self::$probe = $data['runs']['forms'];
        }
        return self::$probe;
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

    # Each field stands on the one form it belongs to, a job shows only its own, and the two intervals no code read are gone everywhere
    #[Test]
    public function everyFieldStandsOnItsOwnForm(): void
    {
        $this->assertSame([
            'dbbackup' => ['log_b', 'dump_age'],
            'filescan' => ['log_d', 'dump_skip', 'mail_d'],
            'sitemap' => [],
            'logs' => ['log', 'error_log', 'write_h', 'write_w', 'log_a', 'log_u', 'log_size'],
            'security' => [],
        ], $this->getRun()['fields']);
    }

    # The journals open under their own section, and the first tab of the security section is the ban list with the verdict of the self-check over it
    #[Test]
    public function theJournalsOpenInTheirOwnSection(): void
    {
        $run = $this->getRun();
        $this->assertSame(array_fill_keys(['name=logs', 'name=logs&op=logins', 'name=logs&op=info', 'name=security', 'name=security&op=banlist'], 200), $run['pages']);
        $this->assertSame([true, false, true], $run['alert'], 'The verdict of the self-check left the security section or reached the logs section, or the list is empty');
    }

    # A save without its token writes nothing; each form then writes its own fields, and the keys of the other forms, the proxy list and the master stay
    #[Test]
    public function eachFormWritesItsFieldsAndKeepsTheRest(): void
    {
        $run = $this->getRun();
        $this->assertSame(303, $run['cold'][0]);
        $this->assertFalse($run['cold'][1], 'A save without the token was written');
        $this->assertSame([303, 303, 303, 303], $run['codes']);
        $this->assertSame(array_fill(0, 17, true), $run['saved'], 'A field was not saved or a save lost the key of another form');
        $this->assertSame(64, $run['secret'], 'A save lost the master');
    }

    # The list of the jobs warns only once a job is switched off in its own form, and each warning leads to that form
    #[Test]
    public function theWarningOfAJobLeadsToItsForm(): void
    {
        $this->assertSame([[], ['dbbackup', 'filescan']], $this->getRun()['warn']);
    }
}
