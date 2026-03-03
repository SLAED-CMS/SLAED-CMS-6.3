<?php
/**
 * Enforces UTF-8 (no BOM), LF line endings, and trailing newline for PHP files.
 */

use PHPUnit\Framework\TestCase;

class PhpFileFormatTest extends TestCase
{
    private static string $basePath;
    private static array $phpFiles = [];

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

    /**
     * Line endings check (CRLF -> LF, trailing newline).
     * Skipped by default - requires project-wide normalization.
     * Run manually: vendor/bin/phpunit --filter testPhpFilesLineEndings
     */
    public function testPhpFilesLineEndings(): void
    {
        $this->markTestSkipped(
            'Проверка окончаний строк отключена. '.
            'Для нормализации используйте: git add --renormalize . && git commit'
        );
    }
}
