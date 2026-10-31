<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The data of the home cockpit, docs/ARCHITECTURE.md "Admin Shell Boundary": fourteen signals by the thresholds of the owner, the snapshot and the one store of the monitor
final class CockpitTest extends TestCase
{
    private const KEYS = ['attack', 'backup', 'cron', 'dirs', 'disk', 'https', 'login', 'mail', 'opcache', 'php', 'phpver', 'setup', 'sql', 'update'];

    private static ?array $probe = null;

    private static string $work = '';

    # Run tests/Support/cockpit_probe.php once in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_cockpit_'.bin2hex(random_bytes(4));
        $script = dirname(__DIR__).'/Support/cockpit_probe.php';
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

    # Every check is there once, the failures come first, and a check in order says nothing as its fact
    #[Test]
    public function fourteenSignalsFailuresFirst(): void
    {
        $data = $this->getProbe();
        $keys = $data['order'];
        sort($keys);
        $this->assertSame(self::KEYS, $keys);
        $rank = array_map(fn(string $v): int => ['danger' => 0, 'warn' => 1, 'ok' => 2][$v], $data['levels']);
        $sort = $rank;
        sort($sort);
        $this->assertSame($sort, $rank, 'A signal in order stands before a failure');
        foreach ($data['closed'] as $key => [$level, $fact]) if ($level === 'ok') $this->assertSame('', $fact, 'The check '.$key.' in order still names a fact');
    }

    # PHP errors warn from one and fail from a hundred in a day, database errors warn from one and fail from ten; no journal is no signal
    #[Test]
    public function journalsCountByTheThresholds(): void
    {
        $data = $this->getProbe();
        $day = fn(int $num): string => sprintf($data['text']['day'], $num);
        $this->assertSame(['ok', ''], $data['calm']['php']);
        $this->assertSame(['ok', ''], $data['calm']['sql']);
        $this->assertSame(['warn', $day(3)], $data['few']['php']);
        $this->assertSame(['warn', $day(1)], $data['few']['sql']);
        $this->assertSame(['danger', $day(100)], $data['many']['php']);
        $this->assertSame(['danger', $day(10)], $data['many']['sql']);
    }

    # An open root fails, no verdict within a day warns, every root closed is in order; the setup file warns while it lies in the root
    #[Test]
    public function selfCheckAndSetupFile(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['warn', $data['text']['none']], $data['calm']['dirs']);
        $this->assertSame(['danger', $data['text']['open']], $data['open']['dirs']);
        $this->assertSame('ok', $data['closed']['dirs'][0]);
        $this->assertSame($data['setup'] ? ['warn', $data['text']['root']] : ['ok', ''], $data['calm']['setup']);
    }

    # No backup at all fails, one older than dump_age warns with its short date, a fresh one is in order, backups switched off warn
    #[Test]
    public function backupAgeAndSwitch(): void
    {
        $data = $this->getProbe();
        $this->assertSame('danger', $data['calm']['backup'][0]);
        $this->assertSame(['warn', date('d.m', time() - 8 * 86400)], $data['open']['backup']);
        $this->assertSame(['ok', ''], $data['closed']['backup']);
        $this->assertSame(['warn', $data['text']['off']], $data['nobackup']['backup']);
    }

    # A job overdue by more than an hour warns, a release fixing security fails with its version, twenty refused logins and a letter waiting two hours warn
    #[Test]
    public function scheduleUpdateLoginsAndMail(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['ok', ''], $data['closed']['cron']);
        $this->assertSame(['warn', sprintf($data['text']['late'], 1)], $data['late']['cron']);
        $this->assertSame(['danger', '9.9.9'], $data['late']['update']);
        $this->assertSame('warn', $data['queue']['login'][0]);
        $this->assertSame(['warn', sprintf($data['text']['wait'], 1)], $data['queue']['mail']);
        $this->assertSame($data['late']['login'], $data['after']['login'], 'The rolled back logins still count');
        $this->assertSame($data['late']['mail'], $data['after']['mail'], 'The rolled back letter still counts');
    }

    # HTTPS is judged by the address of the site, so a site behind a proxy that ends TLS is not reported, and an http address warns
    #[Test]
    public function httpsFollowsTheSiteAddress(): void
    {
        $data = $this->getProbe();
        $this->assertSame([['ok', ''], ['warn', $data['text']['off']]], $data['https']);
    }

    # The store keeps a value for its time in the request and in a file of Cache, and a time of zero computes every call
    #[Test]
    public function theMonitorStoreKeepsAndRefreshes(): void
    {
        $data = $this->getProbe();
        $this->assertSame([['num' => 1], ['num' => 1], ['num' => 2], 2], $data['store']);
        $this->assertSame(['num' => 1], $data['file']);
    }

    # The strip shows the average page of the running hour from the load fields of statistic.log, and a dash while the hour has none
    #[Test]
    public function theStripAveragesThePages(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['—', '—', '—'], [$data['bare']['gen']['value'], $data['bare']['sql']['value'], $data['bare']['mem']['value']]);
        $strip = $data['cockpit']['strip'];
        $this->assertSame([50, 7.5, 3], [$strip['gen']['value'], $strip['sql']['value'], $strip['mem']['value']]);
        $this->assertSame('50', $strip['gen']['text']);
        $this->assertMatchesRegularExpression('/^7[.,]5$/', $strip['sql']['text'], 'The figure of the strip lost the text the language writes');
    }

    # The snapshot carries four shares with their panel rows, the strip of the average page and the signals, toned by the worst signal or a lull
    #[Test]
    public function theSnapshotHasEveryPart(): void
    {
        $data = $this->getProbe()['cockpit'];
        $this->assertSame(['cpu', 'ram', 'disk', 'opcache'], array_keys($data['gauges']));
        foreach ($data['gauges'] as $key => $ring) {
            $this->assertContains($ring['tone'], ['ok', 'info', 'warn', 'danger'], $key);
            $this->assertGreaterThanOrEqual(0, $ring['value'], $key);
            $this->assertLessThanOrEqual(100, $ring['value'], $key);
            $this->assertNotEmpty($ring['rows'], $key);
        }
        $this->assertSame(['gen', 'sql', 'mem', 'online'], array_keys($data['strip']));
        $lvls = array_column($data['signals'], 'level');
        $tone = in_array('danger', $lvls, true) ? 'danger' : (in_array('warn', $lvls, true) ? 'warn' : 'info');
        $this->assertSame($tone, $data['tone']);
        $this->assertCount(14, $data['signals']);
    }

    # With every check passed the lamp rests in a lull while the hour counts under twenty pages; a loud signal and a stale counter never make one
    #[Test]
    public function theLampRestsInALull(): void
    {
        $this->assertSame(['lull' => 'info', 'warn' => 'warn', 'busy' => 'ok', 'stale' => 'ok'], $this->getProbe()['tone']);
    }
}
