<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The OAuth keys and the GitHub token belong to the super administrator, over real requests, driven by tests/Support/route_probe.php with the argument secrets
final class AdminSecretTest extends TestCase
{
    private static array $probe = [];

    private static string $work = '';

    # Run the probe once and memoize its run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_secret_'.bin2hex(random_bytes(4));
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' secrets 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs']);
            self::$probe = $data['runs']['secrets'];
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

    # The pages open to a delegated administrator draw neither a key nor the token, nor a field for them, and the tab of the keys stays shut
    #[Test]
    public function aDelegatedAdministratorSeesNoSecret(): void
    {
        $run = $this->getRun()['boss'];
        $none = array_fill(0, 8, false);
        $this->assertSame([200, $none], $run['account']);
        $this->assertSame([200, $none], $run['changelog']);
        $this->assertSame($none, $run['security']);
    }

    # A save by that administrator goes through for the other settings, while a posted key, token, source, owner or repository changes nothing
    #[Test]
    public function aDelegatedAdministratorWritesNoSecret(): void
    {
        $run = $this->getRun()['write'];
        $this->assertSame([303, 303], $run['codes']);
        $this->assertTrue($run['users'], 'the settings of the section were not saved');
        $this->assertFalse($run['denied'], 'the tab of the keys answered a delegated administrator');
        $this->assertSame([true, false], $run['oauth'], 'config/oauth.php changed');
        $this->assertSame([true, false, true], $run['token'], 'the token changed or the other settings were not saved');
        $this->assertSame([true, false], $run['place'], 'the source, the owner or the repository changed');
    }

    # A super-administrator reads and writes both; a save without the token writes nothing and the keys of a provider stay beside the endpoints the form does not carry
    #[Test]
    public function aSuperAdministratorKeepsTheSecrets(): void
    {
        $run = $this->getRun()['root'];
        $this->assertSame([[true, true, true, false, false, false, false, false], [false, false, false, true, true, true, true, true]], $run['seen']);
        $this->assertSame([303, 303, 303], $run['codes']);
        $this->assertFalse($run['cold'], 'a save without the token was written');
        $this->assertSame([true, true, true], $run['oauth']);
        $this->assertSame([true, false, true], $run['token']);
    }

    # The request journal names the fields of the form and no journal holds a value
    #[Test]
    public function noJournalHoldsASecret(): void
    {
        $run = $this->getRun()['logs'];
        $this->assertTrue($run['request'], 'the request journal did not record the form, so the check proves nothing');
        $this->assertSame([], $run['held']);
    }
}
