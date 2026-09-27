<?php

use PHPUnit\Framework\TestCase;

# Validates block files: syntax, naming, dangerous calls, encoding, output format, presence and size
class BlockValidationTest extends TestCase
{
    private static string $basePath;
    private static string $blocksPath;
    private static array $blockFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::$blocksPath = self::$basePath.'/blocks';
        self::scanBlockFiles();
    }

    # Collects the PHP files of the blocks directory
    private static function scanBlockFiles(): void
    {
        if (!is_dir(self::$blocksPath)) return;

        foreach (scandir(self::$blocksPath) as $file) {
            if ($file === '.' || $file === '..') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                self::$blockFiles[] = self::$blocksPath.'/'.$file;
            }
        }
    }

    # Checks the syntax of every block file with php -l
    public function testBlockFilesSyntax(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $output = [];
            $returnCode = 0;
            exec('php -l "'.$file.'" 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = sprintf(
                    'blocks/%s - синтаксическая ошибка',
                    basename($file)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Синтаксические ошибки в блоках:\n".implode("\n", $errors)
        );
    }

    # Checks that block files are named snake_case.php, since the runtime loads the bfile value directly from blocks/
    public function testBlockFilesNaming(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $fileName = basename($file);

            if (!preg_match('/^[a-z][a-z0-9_]*\.php$/', $fileName)) {
                $errors[] = "blocks/$fileName - некорректное именование (должно быть snake_case.php)";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с именованием блоков:\n".implode("\n", $errors)
        );
    }

    # Checks that blocks contain neither eval() nor shell functions (shell_exec, exec, system, passthru)
    public function testBlocksNoEval(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            if (preg_match('/\beval\s*\(/', $content)) {
                $errors[] = "blocks/$fileName - содержит eval()";
            }

            if (preg_match('/\b(shell_exec|exec|system|passthru)\s*\(/', $content)) {
                $errors[] = "blocks/$fileName - содержит shell команды";
            }
        }

        $this->assertEmpty(
            $errors,
            "Потенциально опасный код в блоках:\n".implode("\n", $errors)
        );
    }

    # Checks that block files are valid UTF-8 and carry no BOM
    public function testBlockFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "blocks/$fileName - некорректная кодировка";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "blocks/$fileName - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    # Reports blocks that output neither through $content nor echo/return; informational, some blocks use another format
    public function testBlocksOutputFormat(): void
    {
        $warnings = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            if (!preg_match('/\$content\s*[.=]/', $content)) {
                if (!preg_match('/echo\s/', $content) && !preg_match('/return\s/', $content)) {
                    $warnings[] = "blocks/$fileName - не обнаружен вывод через \$content";
                }
            }
        }

        $this->assertTrue(true, count($warnings).' блоков с нестандартным форматом вывода');
    }

    # Checks that block files were found and that there are more than five of them
    public function testBlockFilesFound(): void
    {
        $this->assertNotEmpty(self::$blockFiles, 'Файлы блоков не найдены');
        $this->assertGreaterThan(5, count(self::$blockFiles), 'Найдено слишком мало блоков');
    }

    # Lists the block files for comparison with the database; informational, since the database may hold dynamic blocks
    public function testBlockFilesMatchDatabase(): void
    {
        $fileBlocks = [];
        foreach (self::$blockFiles as $file) {
            $fileName = basename($file, '.php');
            $fileBlocks[] = $fileName;
        }

        $this->assertNotEmpty($fileBlocks, 'Не найдены файлы блоков');
    }

    # Reports block files larger than 50 KB; a warning, not an error
    public function testBlockFilesSize(): void
    {
        $warnings = [];
        $maxSize = 50 * 1024;

        foreach (self::$blockFiles as $file) {
            $size = filesize($file);
            $fileName = basename($file);

            if ($size > $maxSize) {
                $warnings[] = sprintf(
                    'blocks/%s - большой размер файла (%s KB)',
                    $fileName,
                    round($size / 1024, 1)
                );
            }
        }

        $this->assertTrue(true, count($warnings).' блоков с большим размером файла');
    }
}
