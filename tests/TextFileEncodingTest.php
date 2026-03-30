<?php
/**
 * Enforces UTF-8 (no BOM) and catches mojibake in project-owned text files.
 */

use PHPUnit\Framework\TestCase;

class TextFileEncodingTest extends TestCase
{
    private static string $basePath;
    private static array $textFiles = [];
    private const EXT = ['html', 'css', 'js', 'md'];
    private const ROOT_FILES = [
        'README.md',
        'CONTRIBUTING.md',
        'SECURITY.md',
        'UPGRADING.md',
        'CODE_OF_CONDUCT.md',
    ];
    private const SCAN_DIRS = [
        'admin',
        'blocks',
        'config',
        'core',
        'docs',
        'lang',
        'modules',
        'templates',
        'tests',
        '.prompts',
    ];
    private const MOJIBAKE = [
        "\xC3\x83\xC2\x90", "\xC3\x83\xE2\x80\x98", "\xC3\x82\xC2\xA9", "\xC3\x82\xC2\xA7",
        "\xC3\x82\xC2\xAE", "\xC3\x82\xC2\xB7", "\xC3\x82\xC2\xB6", "\xC3\xA2\xE2\x82\xAC",
        "\xC3\xA2\xE2\x80\x9E\xE2\x80\x93", "\xC3\x90", "\xC3\x91",
    ];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanTextFiles();
    }

    private static function scanTextFiles(): void
    {
        foreach (self::ROOT_FILES as $file) {
            $path = self::$basePath.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) self::$textFiles[] = $path;
        }

        foreach (self::SCAN_DIRS as $dir) {
            $path = self::$basePath.DIRECTORY_SEPARATOR.$dir;
            if (!is_dir($path)) continue;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                if (!in_array(strtolower($file->getExtension()), self::EXT, true)) continue;

                $full = $file->getPathname();

                if (preg_match('#[/\\\\](vendor|storage|uploads|plugins)[/\\\\]#', $full)) continue;
                if (preg_match('#[/\\\\]\.prompts[/\\\\]old[/\\\\]#', $full)) continue;

                self::$textFiles[] = $full;
            }
        }

        self::$textFiles = array_values(array_unique(self::$textFiles));
    }

    public function testTextFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$textFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relative - некорректная кодировка (не UTF-8)";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relative - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой text files:\n".implode("\n", $errors)
        );
    }

    public function testTextFilesMojibake(): void
    {
        $errors = [];

        foreach (self::$textFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            foreach (self::MOJIBAKE as $frag) {
                if (!str_contains($content, $frag)) continue;
                $errors[] = "$relative - содержит крякозябры";
                break;
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с крякозябрами text files:\n".implode("\n", $errors)
        );
    }
}
