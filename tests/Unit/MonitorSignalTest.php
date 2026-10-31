<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The lamp of the monitor: thirteen signals of the server with their thresholds, their order and their count, the pulse, and the extensions by their weight for SLAED
final class MonitorSignalTest extends TestCase
{
    private const KEYS = ['conn', 'cpu', 'disk', 'ext', 'gen', 'innodb', 'opcache', 'php', 'phpmem', 'proto', 'ram', 'soft', 'uptime'];

    private static ?array $probe = null;

    private static string $work = '';

    # Run tests/Support/monitor_probe.php once in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_monitor_'.bin2hex(random_bytes(4));
        $script = dirname(__DIR__).'/Support/monitor_probe.php';
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

    #[Test]
    public function thirteenSignalsAndACalmServerPassesThemAll(): void
    {
        $calm = $this->getProbe()['calm'];
        $keys = array_column($calm, 'key');
        sort($keys);
        $this->assertSame(self::KEYS, $keys);
        $this->assertSame(array_fill(0, 13, 'ok'), array_column($calm, 'level'));
        foreach ($calm as $one) $this->assertNotSame('', $one['fact'], 'The signal '.$one['key'].' says nothing');
    }

    # The failures stand first and the warnings next, the rest keeping the order of the list
    #[Test]
    public function failuresComeFirst(): void
    {
        $moved = $this->getProbe()['moved'];
        $this->assertSame(['cpu', 'ram', 'proto'], array_slice(array_column($moved, 'key'), 0, 3));
        $this->assertSame(['danger', 'warn', 'warn', 'ok'], array_slice(array_column($moved, 'level'), 0, 4));
    }

    #[Test]
    public function sharesStopAtTheirThresholds(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['ok', 'warn', 'warn', 'danger'], $data['cpu']);
        $this->assertSame(['ok', 'warn', 'warn', 'danger'], $data['ram']);
        $this->assertSame(['ok', 'warn', 'warn', 'danger'], $data['disk'], 'The disk has thresholds of its own, 80 and 95');
    }

    # OPcache warns under 80% of hits and when off, as on the lamp of the home
    #[Test]
    public function opcacheWarnsOnlyEvenWhenOff(): void
    {
        [$low, $fair, $off] = $this->getProbe()['opcache'];
        $this->assertSame(['warn', 'ok'], [$low, $fair]);
        $this->assertSame('warn', $off[0]);
    }

    # A reading that failed never raises an alarm
    #[Test]
    public function limitsOfTheDatabaseAndOfPhp(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['ok', 'warn', 'danger'], array_slice($data['conn'], 0, 3));
        $this->assertSame('ok', $data['conn'][3][0]);
        $this->assertSame(['ok', 'warn'], array_slice($data['innodb'], 0, 2), 'A buffer smaller than the base warns');
        $this->assertSame('ok', $data['innodb'][2][0]);
        $this->assertSame(['warn', 'danger'], array_slice($data['phpmem'], 0, 2));
        $this->assertSame('ok', $data['phpmem'][2][0], 'A memory_limit of -1 is no limit to come near');
        $this->assertSame(['ok', 'warn', 'warn', 'danger', ['ok', '—']], $data['gen']);
    }

    # A branch past its end warns, a branch outside the table is neutral and says only its version
    #[Test]
    public function supportOfThePhpBranch(): void
    {
        [$now, $old, $new] = $this->getProbe()['php'];
        $this->assertSame('ok', $now[0]);
        $this->assertStringContainsString('2028', $now[1]);
        $this->assertSame('warn', $old[0]);
        $this->assertStringContainsString('2025', $old[1]);
        $this->assertSame(['ok', '9.0.1'], $new);
    }

    # The product comes from VERSION() and its suffix never shows; the web server is shown with no verdict, an old number included
    #[Test]
    public function supportOfTheDatabaseAndNoVerdictOnTheWebServer(): void
    {
        [$maria, $late, $mysql, $unknown, $ended] = $this->getProbe()['soft'];
        $this->assertSame('ok', $maria[0]);
        $this->assertStringContainsString('MariaDB 11.4.5', $maria[1]);
        $this->assertStringNotContainsString('ubu', $maria[1]);
        $this->assertStringContainsString('Nginx 1.18.0', $maria[1]);
        $this->assertSame('warn', $late[0]);
        $this->assertSame('warn', $mysql[0], 'MySQL 8.0 is past its end on the clock of the probe');
        $this->assertStringContainsString('MySQL 8.0.36', $mysql[1]);
        $this->assertSame('ok', $unknown[0]);
        $this->assertStringStartsWith('MariaDB 11.7.2 · ', $unknown[1]);
        $this->assertSame('warn', $ended[0]);
    }

    #[Test]
    public function protocolAsksForHttpsAndHttp2(): void
    {
        [$three, $one, $plain, $both] = $this->getProbe()['proto'];
        $this->assertSame('ok', $three);
        $this->assertSame('warn', $one[0]);
        $this->assertStringContainsString('HTTP/2', $one[1]);
        $this->assertStringContainsString('HTTPS', $plain[1]);
        $this->assertStringNotContainsString('HTTP/2', $plain[1]);
        $this->assertStringContainsString('HTTPS, HTTP/2', $both[1]);
    }

    # A recommended one lost warns, a required one lost fails, and the fact names them
    #[Test]
    public function extensionsDecideByTheirWeight(): void
    {
        [$all, $tip, $must] = $this->getProbe()['ext'];
        $this->assertSame('ok', $all[0]);
        $this->assertSame('warn', $tip[0]);
        $this->assertStringContainsString('sodium', $tip[1]);
        $this->assertSame('danger', $must[0]);
        $this->assertStringContainsString('gd, sodium', $must[1]);
    }

    # The two weights hold the lists of the owner, Zend OPcache answers to OPcache, and APCu stands among the others
    #[Test]
    public function theListOfExtensionsByWeight(): void
    {
        $data = $this->getProbe()['weight'];
        $this->assertSame(['ctype', 'curl', 'filter', 'gd', 'json', 'mbstring', 'PDO', 'pdo_mysql', 'session'], $data['must']);
        $this->assertSame(['dom', 'fileinfo', 'intl', 'OPcache', 'openssl', 'Phar', 'sodium', 'zip', 'zlib'], $data['tip']);
        $this->assertSame(['gd'], $data['must_lost']);
        $this->assertSame(['zip'], $data['tip_lost']);
        $this->assertSame(['apcu', 'Core'], $data['other']);
    }

    #[Test]
    public function aRebootWithinTheHourWarns(): void
    {
        [$fresh, $hour, $none] = $this->getProbe()['uptime'];
        $this->assertSame('warn', $fresh[0]);
        $this->assertStringContainsString('59', $fresh[1]);
        $this->assertSame('ok', $hour);
        $this->assertSame('ok', $none[0]);
        $this->assertSame([17123, null], $this->getProbe()['minutes']);
    }

    # The count comes from the one list; the tone decides first, so warning and failure beat at their speed with no page at all
    #[Test]
    public function thePulseFollowsTheToneThenThePages(): void
    {
        $lamp = $this->getProbe()['lamp'];
        $this->assertSame(['ok', 'ok', 72, 72], [$lamp['busy']['mode'], $lamp['busy']['tone'], $lamp['busy']['base'], $lamp['busy']['bpm']]);
        $this->assertSame(['lull', 'info', 72], [$lamp['lull']['mode'], $lamp['lull']['tone'], $lamp['lull']['base']]);
        $this->assertSame(['flat', 'info', 0, 0], [$lamp['flat']['mode'], $lamp['flat']['tone'], $lamp['flat']['base'], $lamp['flat']['bpm']]);
        $this->assertSame('flat', $lamp['tiny']['mode'], 'Under one page in the hour is none');
        $this->assertSame(['ok', 'ok'], [$lamp['none']['mode'], $lamp['none']['tone']], 'A counter that does not hold today says nothing of the hour');
        $this->assertSame(['warn', 'warn', 96], [$lamp['warn']['mode'], $lamp['warn']['tone'], $lamp['warn']['bpm']]);
        $this->assertSame(['danger', 'danger', 168, 235], [$lamp['fail']['mode'], $lamp['fail']['tone'], $lamp['fail']['base'], $lamp['fail']['bpm']]);
        $this->assertSame([13, 0, 13], [$lamp['busy']['pass'], $lamp['busy']['loud'], $lamp['busy']['total']]);
        $this->assertSame([11, 2, 13], [$lamp['fail']['pass'], $lamp['fail']['loud'], $lamp['fail']['total']]);
    }
}
