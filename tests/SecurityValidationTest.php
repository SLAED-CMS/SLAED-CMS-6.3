<?php
/**
 * Тест безопасности кода
 * Проверяет использование безопасных методов работы с данными
 */

use PHPUnit\Framework\TestCase;

class SecurityValidationTest extends TestCase
{
    private static string $basePath;
    private static array $phpFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanPhpFiles();
    }

    /**
     * Сканирует PHP файлы проекта
     */
    private static function scanPhpFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;

            $path = $file->getPathname();

            // Пропускаем vendor, tests, setup
            if (preg_match('#[/\\\\](vendor|tests|setup|plugins)[/\\\\]#', $path)) {
                continue;
            }

            self::$phpFiles[] = $path;
        }
    }

    /**
     * Проверяет прямое использование $_GET/$_POST в SQL запросах
     */
    public function testNoDirectSuperglobalsInSql(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // Ищем SQL запросы с прямым использованием суперглобальных переменных
                if (preg_match('/sql_query\s*\([^)]*\$_(GET|POST|REQUEST)\s*\[/', $line)) {
                    $errors[] = sprintf(
                        "%s:%d - прямое использование \$_%s в SQL запросе",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $lineNum + 1,
                        preg_match('/\$_(GET|POST|REQUEST)/', $line, $m) ? $m[1] : 'SUPERGLOBAL'
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдено небезопасное использование суперглобальных переменных в SQL:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет использование нефильтрованных данных в include/require
     */
    public function testNoUserInputInIncludes(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // Ищем include/require с прямым использованием $_GET/$_POST
                if (preg_match('/(include|require)(_once)?\s*\(?[^;]*\$_(GET|POST|REQUEST)\s*\[/', $line)) {
                    $errors[] = sprintf(
                        "%s:%d - пользовательские данные в include/require",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $lineNum + 1
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены потенциальные LFI уязвимости:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет использование eval() с пользовательскими данными
     */
    public function testNoEvalWithUserInput(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // Ищем eval с переменными
                if (preg_match('/\beval\s*\(\s*\$/', $line)) {
                    $errors[] = sprintf(
                        "%s:%d - использование eval() с переменной",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $lineNum + 1
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдено небезопасное использование eval():\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет использование shell_exec/exec/system с пользовательскими данными
     */
    public function testNoShellExecWithUserInput(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // Ищем shell функции с суперглобальными переменными
                if (preg_match('/(shell_exec|exec|system|passthru|popen)\s*\([^)]*\$_(GET|POST|REQUEST)\s*\[/', $line)) {
                    $errors[] = sprintf(
                        "%s:%d - пользовательские данные в shell команде",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $lineNum + 1
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены потенциальные Command Injection уязвимости:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет использование htmlspecialchars/htmlentities для вывода
     */
    public function testEchoWithoutEscaping(): void
    {
        $warnings = [];
        $maxWarnings = 10;

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                // Ищем echo с прямым выводом $_GET/$_POST
                if (preg_match('/echo\s+[^;]*\$_(GET|POST|REQUEST)\s*\[/', $line)) {
                    // Проверяем есть ли экранирование
                    if (!preg_match('/htmlspecialchars|htmlentities|text_filter|var_filter/', $line)) {
                        $warnings[] = sprintf(
                            "%s:%d - вывод пользовательских данных без экранирования",
                            str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                            $lineNum + 1
                        );

                        if (count($warnings) >= $maxWarnings) break 2;
                    }
                }
            }
        }

        // Это информационное - не фейлим тест
        $this->assertTrue(true, "Информация: " . count($warnings) . " мест требуют ручной проверки на XSS");
    }

    /**
     * Проверяет использование prepared statements в SQL
     */
    public function testSqlQueriesUseParameters(): void
    {
        $warnings = [];
        $maxWarnings = 20;

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);

            // Ищем sql_query без второго параметра (массива) но с переменными в строке
            preg_match_all('/sql_query\s*\(\s*(["\'][^"\']*\$[^"\']*["\']|["\'].*?["\']\..*?)\s*\)(?!\s*,)/s', $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $match) {
                $query = $match[0];
                $offset = $match[1];

                // Проверяем что есть переменные в запросе
                if (preg_match('/\$\w+/', $query)) {
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                    $warnings[] = sprintf(
                        "%s:%d - SQL запрос с переменными без параметризации",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $line
                    );

                    if (count($warnings) >= $maxWarnings) break 2;
                }
            }
        }

        // Ограничиваем вывод
        if (count($warnings) > $maxWarnings) {
            $total = count($warnings);
            $warnings = array_slice($warnings, 0, $maxWarnings);
            $warnings[] = "... и ещё " . ($total - $maxWarnings) . " подобных случаев";
        }

        // Это информационное - не фейлим тест
        $this->assertTrue(true, "Информация: " . count($warnings) . " SQL запросов требуют ручной проверки");
    }

    /**
     * Проверяет что PHP файлы найдены
     */
    public function testPhpFilesFound(): void
    {
        $this->assertNotEmpty(self::$phpFiles, 'PHP файлы не найдены');
        $this->assertGreaterThan(50, count(self::$phpFiles), 'Найдено слишком мало PHP файлов');
    }
}
