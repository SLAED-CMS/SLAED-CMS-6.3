<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The token rules of core/security.php over tests/Support/token_probe.php, and the promise that no address of the site carries a token
final class TokenScopeTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_token_'.getmypid();
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Support/token_probe.php').' '.escapeshellarg($work).' 2>&1');
        foreach (glob($work.'/*') ?: [] as $one) unlink($one);
        if (is_dir($work)) rmdir($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 500));
        $this->assertSame('', $data['error'], 'The probe failed');
        return self::$probe = $data['data'];
    }

    # A scope takes its own token alone: the ajax token of a poll opens no handler of the panel, whatever a log shows of it
    #[Test]
    public function aScopeTakesItsOwnTokenAlone(): void
    {
        $want = ['own' => true, 'ajax_for_scope' => false, 'scope_for_ajax' => false, 'other_scope' => false, 'plain_ajax' => true, 'empty' => false];
        $this->assertSame($want, $this->getProbe()['scope']);
    }

    # A token of the address counts for nothing, as an address lands in logs and history; the header does count
    #[Test]
    public function aTokenOfTheAddressCountsForNothing(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['', false], $data['address']);
        $this->assertTrue($data['header']);
        $this->assertFalse($data['header_for_scope']);
    }

    # No code builds a token into an address again; the pseudo-cron posts its token in the body of a fetch, which is the one match the search leaves out
    #[Test]
    public function noCodeBuildsATokenIntoAnAddress(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['core', 'admin', 'modules', 'blocks'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php') continue;
                foreach (preg_split('/\R/', (string)file_get_contents((string)$file)) ?: [] as $line) {
                    if (str_contains($line, '&token=') && !str_contains($line, 'trigger=pseudo')) $this->fail((string)$file.' builds a token into an address: '.trim($line));
                }
            }
        }
        foreach (['slaed.js', 'dock.js', 'global-func.js', 'filemanager.js'] as $name) {
            $this->assertStringNotContainsString('&token=', (string)file_get_contents($root.'/public/plugins/system/'.$name), $name.' puts a token into an address');
        }
    }

    # The two reads a browser opens by itself, an image and a download, go without a token, and each answers for its rights; the two order handlers hold their own scope
    #[Test]
    public function theGateLeavesOutOnlyWhatChecksItself(): void
    {
        $gate = (string)file_get_contents(dirname(__DIR__, 2).'/public/index.php');
        $this->assertStringContainsString("in_array(\$op, ['updateAdminCategoryOrder', 'updateAdminBlockOrder', 'getAdminFilePreview', 'getAdminFileDownload'], true)", $gate);
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/admin.php');
        $this->assertStringContainsString("getSiteToken('categories').'\" data-sl-drag-target=\"repajax_cat\"", $code);
        $this->assertStringContainsString("getSiteToken('blocks').'\" data-sl-drag-target=\"repajax_block\"", $code);
        $this->assertSame(2, substr_count($code, 'if (!isAdmin(true) || $one === []'), 'A read that goes without a token no longer answers for its rights');
    }
}
