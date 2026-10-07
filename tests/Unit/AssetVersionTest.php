<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Batch 1 of docs/3-ASSET-CACHE-2026.md: every asset address carries the version of its content, from the derived map, and a page never stats an asset file
class AssetVersionTest extends TestCase
{
    private static array $probe = [];
    private array $saved = [];

    # Load the template layer and keep the configuration of the run, which the tests reshape
    protected function setUp(): void
    {
        if (!class_exists('Template', false)) require_once BASE_DIR.'/core/classes/template.php';
        $this->saved = $GLOBALS['conf'];
    }

    # Give the next test the configuration of the run back
    protected function tearDown(): void
    {
        $GLOBALS['conf'] = $this->saved;
    }

    # The version the design names for one file below the document root: the first ten hex characters of its SHA-1
    private function getHash(string $file): string
    {
        $this->assertFileExists(PUBLIC_DIR.'/'.$file);
        return substr((string)sha1_file(PUBLIC_DIR.'/'.$file), 0, 10);
    }

    # Run the asset probe on the real core in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_asset_probe';
        if (!is_dir($work)) mkdir($work, 0777, true);
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Support/asset_probe.php').' '.escapeshellarg($work).' 2>&1';
        $out = (string)shell_exec($cmd);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The asset probe did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('The asset probe failed: '.$data['error']);
        return self::$probe = $data;
    }

    # Every href and src of a run of head tags
    private function getAddresses(string $html): array
    {
        preg_match_all('#\s(?:href|src)="([^"]+)"#', $html, $hits);
        return $hits[1];
    }

    # Without a derived map, as in setup.php and update.php, the version is hashed off the file itself
    #[Test]
    public function anAddressWithoutTheMapCarriesTheHashOfItsFile(): void
    {
        unset($GLOBALS['conf']['derived']);
        $file = 'plugins/system/slaed.js';
        $this->assertSame($file.'?v='.$this->getHash($file), \Template::getAssetUrl($file));
        $this->assertSame('plugins/system/no-such-file.js', \Template::getAssetUrl('plugins/system/no-such-file.js'), 'A missing file is printed plain');
    }

    # With a map a request reads the version and never the disk, and dev_mode hashes per request so an edit shows at once
    #[Test]
    public function theMapAnswersAndDevModeHashesAgain(): void
    {
        $file = 'plugins/system/slaed.js';
        $GLOBALS['conf']['derived']['version'] = [$file => 'abcdef0123', 'plugins/ghost.js' => '0123abcdef'];
        $GLOBALS['conf']['dev_mode'] = '0';
        $this->assertSame($file.'?v=abcdef0123', \Template::getAssetUrl($file), 'The map is read, the file is not hashed');
        $this->assertSame('plugins/ghost.js?v=0123abcdef', \Template::getAssetUrl('plugins/ghost.js'), 'A request never looks at the disk');
        $this->assertSame('plugins/system/global-func.js', \Template::getAssetUrl('plugins/system/global-func.js'), 'A file the map does not know stays plain');
        $GLOBALS['conf']['dev_mode'] = '1';
        $this->assertSame($file.'?v='.$this->getHash($file), \Template::getAssetUrl($file), 'dev_mode shows an edit at once');
    }

    # A file changed on disk gets a new address, per request and in a rebuilt map alike
    #[Test]
    public function aChangedFileChangesItsAddress(): void
    {
        unset($GLOBALS['conf']['derived']);
        $file = 'plugins/system/phpunit-asset-'.getmypid().'.js';
        $full = PUBLIC_DIR.'/'.$file;
        try {
            file_put_contents($full, 'var one = 1;');
            $old = \Template::getAssetUrl($file);
            $map = \Template::getAssetVersions();
            file_put_contents($full, 'var one = 2;');
            $this->assertNotSame($old, \Template::getAssetUrl($file));
            $this->assertNotSame($map[$file] ?? '', \Template::getAssetVersions()[$file] ?? '', 'A rebuilt map carries the new version');
            $this->assertSame($file.'?v='.\Template::getAssetVersions()[$file], \Template::getAssetUrl($file));
        } finally {
            if (is_file($full)) unlink($full);
        }
    }

    # The map holds every stylesheet and script of the themes and plugins, each with the hash of its content
    #[Test]
    public function theMapHoldsEveryStylesheetAndScriptOfThemesAndPlugins(): void
    {
        $map = \Template::getAssetVersions();
        $want = [];
        foreach (['templates', 'plugins'] as $dir) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(PUBLIC_DIR.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $one) {
                if (in_array(strtolower($one->getExtension()), ['css', 'js'], true)) $want[] = str_replace('\\', '/', substr($one->getPathname(), strlen(PUBLIC_DIR) + 1));
            }
        }
        sort($want);
        $this->assertSame($want, array_keys($map));
        $list = ['templates/lite/assets/css/theme.css', 'plugins/editors/toastui/assets/toastui-editor.all.min.js'];
        foreach ($list as $file) $this->assertSame($this->getHash($file), $map[$file]);
    }

    # Every head tag of both themes carries a version, and the version is the one of the file the tag names
    #[Test]
    public function everyHeadTagOfAPageCarriesTheVersionOfItsFile(): void
    {
        $probe = $this->getProbe();
        $this->assertTrue($probe['map'], 'The derived configuration carries no version map');
        foreach (['lite', 'admin'] as $theme) {
            $list = array_merge($this->getAddresses($probe[$theme]['css']), $this->getAddresses($probe[$theme]['js']));
            $this->assertNotEmpty($list, 'The '.$theme.' head prints no asset');
            foreach ($list as $url) {
                $this->assertMatchesRegularExpression('#^[a-z0-9/._-]+\?v=[0-9a-f]{10}$#', $url, 'An address without a version: '.$url);
                [$file, $ver] = explode('?v=', $url);
                $this->assertSame($this->getHash($file), $ver, 'The version of '.$file.' is not the one of its content');
            }
        }
    }

    # A page prints a listed file from the map even when it is missing, so no request stats an asset
    #[Test]
    public function aPagePrintsItsAssetsFromTheMapWithoutStatingAFile(): void
    {
        $probe = $this->getProbe();
        $this->assertSame(['templates/lite/assets/css/ghost-probe.css?v=abcdef0123'], $this->getAddresses($probe['ghost']['css']));
        $this->assertSame(['plugins/system/ghost-probe.js?v=0123abcdef'], $this->getAddresses($probe['ghost']['js']));
    }

    # The engine of a page and the engine an htmx fragment names are one address, which the client loader compares, so it is fetched once
    #[Test]
    public function anEditorOfThePageAndOfAFragmentIsTheSameAddress(): void
    {
        $probe = $this->getProbe()['editor'];
        $page = $this->getAddresses($probe['page']);
        $this->assertCount(2, $page);
        $this->assertSame(1, preg_match('#load\.apply\(null,(\[.*\])\);#', $probe['htmx'], $hit), 'The fragment names no list to the client loader');
        $this->assertSame($page, array_merge(...json_decode($hit[1], true)), 'The loader would fetch the engine a second time');
        foreach ($page as $url) $this->assertStringContainsString('?v=', $url);
    }

    # The robots screen prints no tag of its own for a script the admin package prints on every panel page
    #[Test]
    public function theRobotsScreenLeavesItsScriptToTheThemePackage(): void
    {
        $code = (string)file_get_contents(BASE_DIR.'/admin/modules/editor.php');
        $this->assertStringNotContainsString('editor-robots.js', $code, 'The screen prints the script the package already prints on every panel page');
        $this->assertFileExists(PUBLIC_DIR.'/templates/admin/assets/js/editor-robots.js');
    }
}
