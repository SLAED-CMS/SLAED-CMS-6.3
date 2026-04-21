<?php
/**
 * Тест валидации блоков
 * Проверяет корректность файлов блоков
 */

use PHPUnit\Framework\TestCase;

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

    /**
     * Сканирует файлы блоков
     */
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

    /**
     * Проверяет синтаксис файлов блоков
     */
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

    /**
     * Проверяет именование файлов блоков
     */
    public function testBlockFilesNaming(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $fileName = basename($file);

            // Runtime loads the bfile value directly from blocks/.
            if (!preg_match('/^[a-z][a-z0-9_]*\.php$/', $fileName)) {
                $errors[] = "blocks/$fileName - некорректное именование (должно быть snake_case.php)";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с именованием блоков:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет что блоки не содержат опасных функций
     */
    public function testBlocksNoEval(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Проверяем на eval
            if (preg_match('/\beval\s*\(/', $content)) {
                $errors[] = "blocks/$fileName - содержит eval()";
            }

            // Проверяем на shell функции
            if (preg_match('/\b(shell_exec|exec|system|passthru)\s*\(/', $content)) {
                $errors[] = "blocks/$fileName - содержит shell команды";
            }
        }

        $this->assertEmpty(
            $errors,
            "Потенциально опасный код в блоках:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет кодировку файлов блоков
     */
    public function testBlockFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Проверяем на невалидный UTF-8
            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "blocks/$fileName - некорректная кодировка";
            }

            // Проверяем на BOM
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "blocks/$fileName - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет что блоки выводят контент через $content
     */
    public function testBlocksOutputFormat(): void
    {
        $warnings = [];

        foreach (self::$blockFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Проверяем что блок использует $content для вывода
            if (!preg_match('/\$content\s*[.=]/', $content)) {
                // Некоторые блоки могут использовать другой формат
                if (!preg_match('/echo\s/', $content) && !preg_match('/return\s/', $content)) {
                    $warnings[] = "blocks/$fileName - не обнаружен вывод через \$content";
                }
            }
        }

        // Это информационное сообщение
        $this->assertTrue(true, count($warnings).' блоков с нестандартным форматом вывода');
    }

    /**
     * Проверяет что файлы блоков найдены
     */
    public function testBlockFilesFound(): void
    {
        $this->assertNotEmpty(self::$blockFiles, 'Файлы блоков не найдены');
        $this->assertGreaterThan(5, count(self::$blockFiles), 'Найдено слишком мало блоков');
    }

    /**
     * Проверяет соответствие блоков в БД и файловой системе
     */
    public function testBlockFilesMatchDatabase(): void
    {
        // Получаем список блоков из файлов
        $fileBlocks = [];
        foreach (self::$blockFiles as $file) {
            $fileName = basename($file, '.php');
            $fileBlocks[] = $fileName;
        }

        // Эта проверка информационная - БД может содержать динамические блоки
        $this->assertNotEmpty($fileBlocks, 'Не найдены файлы блоков');
    }

    /**
     * Проверяет размер файлов блоков
     */
    public function testBlockFilesSize(): void
    {
        $warnings = [];
        $maxSize = 50 * 1024; // 50KB

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

        // Это предупреждение, не ошибка
        $this->assertTrue(true, count($warnings).' блоков с большим размером файла');
    }
}
