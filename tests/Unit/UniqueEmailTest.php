<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The unique address over real requests, driven by tests/Support/route_probe.php email: a taken address is refused as taken, a missing one is NULL and taken by none
final class UniqueEmailTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its email mode and memoize the run; the only SQL errors are the two duplicate keys the requests answer as a taken address
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_email_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' email 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $twin = "/1062: Duplicate entry '([^']+)' for key '(?:[^']*\\.)?email'/";
            $sql = array_map(fn($v) => preg_match($twin, (string)$v, $hit) ? $hit[1] : $v, $data['logs']['error_sql.log']);
            $this->assertSame(['race@probe.test', 'late@probe.test'], $sql, 'The requests wrote SQL errors other than the two refused addresses');
            self::$probe = $data['runs']['email'];
        }
        return self::$probe;
    }

    # A settings save with the address of another account in another case is refused as taken and keeps the stored address
    #[Test]
    public function aSettingsSaveWithATakenAddressIsRefused(): void
    {
        $this->assertSame([200, true, 'clara@probe.test'], $this->getRun()['save']);
    }

    # Both panels refuse to give an existing account or administrator the address of another one in another case and say why
    #[Test]
    public function thePanelsRefuseATakenAddress(): void
    {
        $this->assertSame([200, true, 'clara@probe.test', 200, true, 'moder@probe.test'], $this->getRun()['panel']);
    }

    # A registration racing a parallel one of the same address waits for its commit, answers the address taken and leaves one pending registration
    #[Test]
    public function aParallelDoubleRegistrationEndsInOne(): void
    {
        $this->assertSame([true, 'held', true, 1, 0], $this->getRun()['race']);
    }

    # An activation whose address another account took meanwhile answers the address taken, keeps the pending row and creates no account
    #[Test]
    public function anActivationOfATakenAddressKeepsItsRow(): void
    {
        $this->assertSame([true, 1, 0], $this->getRun()['activate']);
    }

    # Two OAuth registrations without an address both create their account, each with a NULL address and a link without one
    #[Test]
    public function twoOauthUsersWithoutAnAddressBothRegister(): void
    {
        $this->assertSame([[303, 303], 2, 2], $this->getRun()['oauth']);
    }
}
