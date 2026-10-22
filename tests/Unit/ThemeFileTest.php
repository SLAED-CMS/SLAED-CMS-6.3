<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The files of a theme are reached from its root: getThemeFileUrl() and getThemeFilePath() answer for images/ and sound/ alike
final class ThemeFileTest extends TestCase
{
    private static array $said = [];

    # Ask the real core, in its own process, for the files of the theme it serves a visitor
    public static function setUpBeforeClass(): void
    {
        $work = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()).'/slaed_theme_'.bin2hex(random_bytes(6));
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Support/theme_probe.php').' file '.escapeshellarg($work);
        self::$said = (array)json_decode((string)shell_exec($cmd.' 2>&1'), true);
    }

    # The address is the root of the site, the folder of the theme and the file named from the root of the theme
    #[Test]
    public function theAddressStartsAtTheRootOfTheTheme(): void
    {
        $this->assertNotSame('', (string)(self::$said['theme'] ?? ''), 'the theme probe did not answer');
        $this->assertSame(self::$said['home'].'templates/'.self::$said['theme'].'/sound/', self::$said['url']);
    }

    # The path lies below the document root and names a file that is there
    #[Test]
    public function thePathNamesTheFileBelowThePublicRoot(): void
    {
        $this->assertSame('templates/'.self::$said['theme'].'/images/favicon.svg', self::$said['path']);
        $this->assertFileExists(dirname(__DIR__, 2).'/public/'.self::$said['path']);
    }

    # A theme with a sound folder gets the engine in its head, and both shipped themes carry the voices they play
    #[Test]
    public function aThemeWithSoundsLoadsTheEngine(): void
    {
        $root = dirname(__DIR__, 2).'/public/templates/';
        $this->assertStringContainsString('plugins/system/sound.js', (string)self::$said['script']);
        foreach (['admin' => ['success', 'error', 'warn', 'queue', 'win'], 'lite' => ['success', 'error', 'warn', 'queue', 'win', 'inbox', 'send']] as $theme => $list) {
            foreach ($list as $voice) $this->assertFileExists($root.$theme.'/sound/'.$voice.'.mp3');
        }
    }
}
