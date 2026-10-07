<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Self-check of docs/ARCHITECTURE.md, "Private Data Boundary": the installation proves its private part is private, by the body of an answer and in three states
final class SelfCheckTest extends TestCase
{
    private static ?array $probe = null;

    private static string $work = '';

    # Run tests/Support/check_probe.php once in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_check_'.bin2hex(random_bytes(4));
        $script = dirname(__DIR__).'/Support/check_probe.php';
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

    # The project and the four folders of the plan are watched, each asked once at the address it would have under the site address, with a random marker of its own
    #[Test]
    public function everyPrivateRootIsAskedAtItsOwnAddress(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['', 'storage', 'config', 'uploads', 'admin/info'], $data['roots']);
        $this->assertSame([true, true, true], $data['shipped'], 'The project, the configuration or the upload root is not watched where the constants put it');
        $urls = array_map(fn(string $v): string => 'https://probe.test/site/'.ltrim($v.'/check.txt', '/'), $data['roots']);
        $this->assertSame($urls, $data['asked']);
        $this->assertSame($urls, array_values(array_column($data['open'], 'url')));
        foreach ($data['marks'] as $root => $mark) $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $mark, $root);
        $this->assertCount(5, array_unique($data['marks']), 'Two roots share a marker');
        $ignore = (string)file_get_contents(dirname(__DIR__, 2).'/.gitignore');
        foreach ($data['roots'] as $root) $this->assertStringContainsString("\n/".ltrim($root.'/check.txt', '/')."\n", $ignore, 'The marker of /'.$root.' could be committed');
    }

    # One verdict per state: the marker in the body is open, any other answer is closed, no answer is unknown, and each names its address
    #[Test]
    public function theVerdictHasThreeStates(): void
    {
        $data = $this->getProbe();
        foreach (['open' => '', 'closed' => '', 'unknown' => 'Connection refused'] as $state => $error) {
            foreach ($data[$state] as $root => $one) {
                $this->assertSame(['url' => 'https://probe.test/site/'.ltrim($root.'/check.txt', '/'), 'state' => $state, 'error' => $error], $one, $state.' '.$root);
            }
        }
    }

    # A status decides nothing: the marker served with 404 is open, a CMS error page served with 200 is closed
    #[Test]
    public function theBodyDecidesNotTheStatus(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['' => 'open', 'storage' => 'open', 'config' => 'open', 'uploads' => 'open', 'admin/info' => 'open'], $data['hidden']);
        $this->assertSame(['' => 'closed', 'storage' => 'closed', 'config' => 'closed', 'uploads' => 'closed', 'admin/info' => 'closed'], $data['page']);
    }

    # A marker is kept between runs, a removed one is written again with a new body before the check asks, and without an http(s) address nothing is asked or written
    #[Test]
    public function aMissingMarkerIsWrittenAgain(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['kept'], 'A run replaced a valid marker');
        $this->assertSame(['uploads' => 'open'], $data['again'], 'The rewritten marker was not the one the check compared');
        $this->assertSame([true, true], $data['rewritten']);
        $this->assertSame('unknown', $data['noweb']['uploads']['state']);
        $this->assertSame([], $data['noweb_asked']);
        $this->assertFalse($data['noweb_mark']);
    }

    # The scheduler job stores the verdict of every root in its state, fails on a root that is not closed, and the panel shows what was stored
    #[Test]
    public function theSchedulerJobStoresTheVerdictForThePanel(): void
    {
        $data = $this->getProbe();
        $this->assertStringContainsString($data['text']['none'], $data['none'], 'No run stored: the panel does not say so');
        $this->assertSame('failed', $data['job']['status']);
        $this->assertSame('failed', $data['job']['stored']);
        $this->assertSame(['', 'storage', 'config', 'uploads', 'admin/info'], array_keys($data['job']['roots']));
        foreach ($data['job']['roots'] as $root => $one) {
            $url = 'probe.test/'.ltrim($root.'/check.txt', '/');
            $this->assertSame(['url' => $url, 'state' => 'unknown', 'error' => 'The site address is no http(s) URL'], $one);
            $this->assertStringContainsString('unknown: '.$url, $data['job']['message']);
            $this->assertStringContainsString('<li>'.$url.'</li>', $data['alert'][0]);
        }
        $this->assertStringContainsString($data['text']['unknown'], $data['alert'][0]);
        $this->assertStringNotContainsString($data['text']['open'], $data['alert'][0]);
        $this->assertSame($data['alert'][0], $data['alert'][1], 'The security section adds an all-clear to a problem');
        $this->assertSame('', $data['clear'][0], 'The home of the panel speaks of a closed site');
        $this->assertStringContainsString($data['text']['done'], $data['clear'][1]);
        $this->assertStringContainsString('sl-alert-info', $data['clear'][1]);
    }

    # A verdict older than a day is no verdict: a stopped scheduler leaves no old all-clear standing, the home of the panel and the security section both warn
    #[Test]
    public function aStaleVerdictCountsAsNone(): void
    {
        $data = $this->getProbe();
        foreach ($data['stale'] as $one) {
            $this->assertStringContainsString($data['text']['none'], $one);
            $this->assertStringNotContainsString($data['text']['done'], $one);
        }
    }
}
