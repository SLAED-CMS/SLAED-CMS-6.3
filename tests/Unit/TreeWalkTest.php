<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The source gates share one walk of the tree, which never enters runtime output or a scratch theme another process is building or removing
final class TreeWalkTest extends TestCase
{
    private static string $root = '';

    private static string $path = '';

    # Place a scratch theme of this test beside the real themes, as ThemeCreationTest and the screenshot runner do
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/tools/ui-audit.php';
        self::$root = str_replace('\\', '/', dirname(__DIR__, 2));
        self::$path = self::$root.'/public/templates/scratch-'.substr(sha1('walk'.getmypid().microtime(true)), 0, 8);
        mkdir(self::$path.'/fragments', 0777, true);
        file_put_contents(self::$path.'/fragments/walk.html', '<div class="sl-walk-probe"></div>');
    }

    # Remove the scratch theme whether or not a test already did
    public static function tearDownAfterClass(): void
    {
        if (is_file(self::$path.'/fragments/walk.html')) unlink(self::$path.'/fragments/walk.html');
        if (is_dir(self::$path.'/fragments')) rmdir(self::$path.'/fragments');
        if (is_dir(self::$path)) rmdir(self::$path);
    }

    # The walk of the whole tree, of templates/ and the file list of the UI audit all pass the scratch theme and storage by
    #[Test]
    public function aScratchThemeAndStorageAreNeverWalked(): void
    {
        $seen = [];
        foreach ([self::$root, self::$root.'/public/templates'] as $dir) {
            foreach (getTreeFiles($dir) as $item) $seen[] = str_replace('\\', '/', $item->getPathname());
        }
        $this->assertContains(self::$root.'/public/templates/lite/assets/css/base.css', $seen, 'The walk lost a real theme');
        $this->assertSame([], preg_grep('#/templates/scratch-[0-9a-f]{8}/#', $seen), 'The walk entered a scratch theme');
        $this->assertSame([], preg_grep('#^'.preg_quote(self::$root, '#').'/storage/#', $seen), 'The walk entered storage');
        $this->assertSame([], preg_grep('#^public/templates/scratch-#', getRepoFiles(['html'], ['public/templates'])), 'The UI audit lists a scratch theme');
    }

    # A scratch theme removed while the walk is under way is no error, because the walk never held it
    #[Test]
    public function aScratchThemeRemovedMidWalkIsNoError(): void
    {
        $seen = 0;
        foreach (getTreeFiles(self::$root.'/public/templates') as $item) {
            if ($seen++ === 0) self::tearDownAfterClass();
        }
        $this->assertGreaterThan(1, $seen, 'The walk stopped after the removal');
        $this->assertDirectoryDoesNotExist(self::$path);
    }

    # A theme listing asks the same question of a directory name, so a half-built copy is no theme of the tree
    #[Test]
    public function aThemeListingSkipsTheScratchTheme(): void
    {
        $this->assertTrue(isTreeSkipped(self::$root.'/public/templates/scratch-0123abcd'));
        $this->assertTrue(isTreeSkipped(str_replace('/', '\\', self::$root).'\\storage\\cache'));
        $this->assertFalse(isTreeSkipped(self::$root.'/public/templates/lite'));
        $this->assertFalse(isTreeSkipped(self::$root.'/public/templates/scratch-notours'));
    }
}
