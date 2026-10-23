<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The group limit of a module as isModGroup() answers it for the entry of a module, the files of the forum and the files of comments
final class ModuleGroupTest extends TestCase
{
    # Run one scenario of tests/Support/modgroup_probe.php, which lifts isModGroup() over stubs, and answer whether the module opened
    private function getOpen(array $case): int
    {
        $script = dirname(__DIR__).'/Support/modgroup_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_modgroup_'.md5((string)json_encode($case)).'_'.getmypid();
        $cmd = escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 '.escapeshellarg($script).' '.escapeshellarg($work)
            .' '.escapeshellarg(base64_encode((string)json_encode($case)));
        $out = (string)shell_exec($cmd.' 2>&1');
        foreach (glob($work.'/*') ?: [] as $file) unlink($file);
        if (is_dir($work)) rmdir($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The probe failed');
        return $data['data']['open'];
    }

    # A guest never passes, and a module without a group opens to every signed-in user whatever the account holds
    #[Test]
    public function aModuleWithoutGroupOpensToMembers(): void
    {
        $this->assertSame(0, $this->getOpen(['user' => false, 'limit' => 0]));
        $this->assertSame(1, $this->getOpen(['user' => true, 'limit' => 0, 'account' => ['points' => 0, 'grp' => 0]]));
    }

    # The limit is read from the module config: a point group keeps out an account below its threshold, which the lost config read let through
    #[Test]
    public function aPointGroupNeedsItsPoints(): void
    {
        $group = ['points' => 500, 'extra' => 0];
        $this->assertSame(0, $this->getOpen(['user' => true, 'limit' => 3, 'account' => ['points' => 100, 'grp' => 0], 'group' => $group]));
        $this->assertSame(1, $this->getOpen(['user' => true, 'limit' => 3, 'account' => ['points' => 500, 'grp' => 0], 'group' => $group]));
        $this->assertSame(0, $this->getOpen(['user' => true, 'limit' => 3, 'account' => ['points' => 0, 'grp' => 0], 'group' => ['points' => 0, 'extra' => 0]]));
    }

    # A special group opens by membership alone and its flag arrives as an integer, so points never stand in for it and a member is never refused
    #[Test]
    public function aSpecialGroupOpensByMembership(): void
    {
        $group = ['points' => 0, 'extra' => 1];
        $this->assertSame(1, $this->getOpen(['user' => true, 'limit' => 4, 'account' => ['points' => 0, 'grp' => 4], 'group' => $group]));
        $this->assertSame(0, $this->getOpen(['user' => true, 'limit' => 4, 'account' => ['points' => 90000, 'grp' => 2], 'group' => $group]));
    }

    # A group removed after it was set on the module falls back to the point rule at threshold zero, as the shipped behaviour always did
    #[Test]
    public function aMissingGroupFallsBackToPoints(): void
    {
        $this->assertSame(1, $this->getOpen(['user' => true, 'limit' => 9, 'account' => ['points' => 1, 'grp' => 0]]));
        $this->assertSame(0, $this->getOpen(['user' => true, 'limit' => 9, 'account' => ['points' => 0, 'grp' => 0]]));
    }
}
