<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The empty values of the schema over real requests, driven by tests/Support/route_probe.php null: a flag, a text or a choice is never NULL, a missing day is
final class SchemaNullTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its null mode and memoize the run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_null_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' null 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The requests wrote SQL errors');
            self::$probe = $data['runs']['null'];
        }
        return self::$probe;
    }

    # An account inserted without the flag, the signature and the two texts holds a hidden address and empty texts, not NULL
    #[Test]
    public function anAccountStartsWithEmptyValues(): void
    {
        $this->assertSame([0, null, '', '', ''], $this->getRun()['stored']);
    }

    # The profile names the language of a stored code and keeps the address of an account that never chose to show it
    #[Test]
    public function theProfileReadsTheCodeAndTheFlag(): void
    {
        $this->assertSame([true, false], $this->getRun()['profile'], 'The profile shows the raw code or the hidden address');
    }

    # The settings check No for the address, say hidden in the lamp, offer an empty birthday, and a save left untouched keeps both as they were
    #[Test]
    public function theSettingsKeepTheAddressHidden(): void
    {
        $run = $this->getRun();
        $this->assertSame(['0', '', true, false], $run['form'], 'The switch, the birthday or the lamp of a hidden address');
        $this->assertSame([303, [0, null]], $run['save'], 'An untouched save published the address or wrote a birthday');
    }

    # A birthday cleared in the settings is stored as none, not as the day of the save
    #[Test]
    public function aClearedBirthdayIsNone(): void
    {
        $this->assertSame(['1990-05-17', 303, [null]], $this->getRun()['clear']);
    }

    # The account form of the panel checks No for a hidden address and offers no birthday where none is stored
    #[Test]
    public function thePanelFormReadsTheFlag(): void
    {
        $this->assertSame(['0', ''], $this->getRun()['panel']);
    }
}
