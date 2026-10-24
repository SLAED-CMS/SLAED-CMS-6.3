<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# "Pick all N" walks the scope of its own list: the portions of setListPortion() reach exactly the rows the list counts, up to the bound it draws
final class ListPortionTest extends TestCase
{
    # Run the read-only parity scenario of tests/Support/contract_probe.php, signed in as a super administrator over the stand database
    private function getParity(): array
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_parity_'.getmypid();
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 '.escapeshellarg($script).' listparity '.escapeshellarg($work).' 2>&1');
        foreach (array_merge(glob($work.'/logs/*') ?: [], glob($work.'/*.php') ?: []) as $file) unlink($file);
        foreach ([$work.'/logs', $work] as $dir) if (is_dir($dir)) rmdir($dir);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The parity probe did not return JSON: '.substr($out, 0, 300));
        if (!$data['admin']) $this->markTestSkipped('No super administrator with a stored address on this installation');
        return $data['lists'];
    }

    # Every list counts what its sweep visits and draws the bound its last portion ends on, the filtered lists, the word top and the Node union included
    #[Test]
    public function aSweepVisitsWhatItsListCounts(): void
    {
        $lists = $this->getParity();
        foreach (['comments 1', 'comments 0', 'favorites', 'favorites found', 'privat', 'privat found', 'voting', 'voting found', 'queue', 'dead', 'dead found', 'account users',
            'account found', 'account newuser', 'account oauthlist', 'search', 'search top'] as $name) $this->assertArrayHasKey($name, $lists, 'The probe did not measure '.$name);
        foreach ($lists as $name => [$count, $upto, $seen, $last]) {
            $this->assertSame($count, $seen, 'The sweep of '.$name.' visits another number of rows than the list counts');
            $this->assertSame($upto, $last, 'The last portion of '.$name.' does not end on the bound of the list');
        }
    }
}
