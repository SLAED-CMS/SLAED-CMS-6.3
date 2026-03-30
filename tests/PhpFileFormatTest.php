<?php
/**
 * Enforces UTF-8 (no BOM), LF line endings, and trailing newline for PHP files.
 */

use PHPUnit\Framework\TestCase;

class PhpFileFormatTest extends TestCase
{
    private static string $basePath;
    private static array $phpFiles = [];
    private const MOJIBAKE = [
        "\xC3\x83\xC2\x90", "\xC3\x83\xE2\x80\x98", "\xC3\x82\xC2\xA9", "\xC3\x82\xC2\xA7",
        "\xC3\x82\xC2\xAE", "\xC3\x82\xC2\xB7", "\xC3\x82\xC2\xB6", "\xC3\xA2\xE2\x82\xAC",
        "\xC3\xA2\xE2\x80\x9E\xE2\x80\x93", "\xC3\x90", "\xC3\x91",
    ];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanPhpFiles();
    }

    private static function scanPhpFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Skip third-party and generated content.
            if (preg_match('#[/\\\\](vendor|storage|uploads|plugins)[/\\\\]#', $path)) {
                continue;
            }

            self::$phpFiles[] = $path;
        }
    }

    public function testPhpFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
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
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    public function testPhpFilesMojibake(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            foreach (self::MOJIBAKE as $frag) {
                if (!str_contains($content, $frag)) continue;
                $errors[] = "$relative - содержит крякозябры: ".$frag;
                break;
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с крякозябрами:\n".implode("\n", $errors)
        );
    }

    public function testPhpFilesLineEndings(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            if (str_contains($content, "\r\n")) {
                $errors[] = "$relative - содержит CRLF, нужен LF";
            }

            if ($content !== '' && !str_ends_with($content, "\n")) {
                $errors[] = "$relative - отсутствует финальный LF";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с окончаниями строк:\n".implode("\n", $errors)
        );
    }
}
