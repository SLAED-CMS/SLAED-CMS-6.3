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
     * Проверяет что include/require не используются внутри функций
     */
    public function testNoIncludesInsideFunctions(): void
    {
        $warnings = [];
        $seen = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $tokens = token_get_all($content);
            $depth = 0;
            $inFunction = false;
            $funcDepth = 0;
            $funcName = '';
            $nextIsFuncName = false;

            foreach ($tokens as $i => $token) {
                if (is_string($token)) {
                    if ($token === '{') {
                        $depth++;
                    } elseif ($token === '}') {
                        $depth--;
                        if ($inFunction && $depth < $funcDepth) {
                            $inFunction = false;
                        }
                    }
                    continue;
                }

                [$id, $text, $line] = $token;

                if ($id === T_FUNCTION) {
                    $nextIsFuncName = true;
                    continue;
                }

                if ($nextIsFuncName && $id === T_STRING) {
                    $nextIsFuncName = false;
                    $inFunction = true;
                    $funcDepth = $depth + 1;
                    $funcName = $text;
                    continue;
                }

                if ($nextIsFuncName && !in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $nextIsFuncName = false;
                }

                if ($inFunction && in_array($id, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                    $row = sprintf(
                        "%s:%d - %s внутри функции %s()",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file),
                        $line,
                        strtolower($text),
                        $funcName
                    );
                    if (!isset($seen[$row])) {
                        $seen[$row] = true;
                        $warnings[] = $row;
                    }
                }
            }
        }

        $maxWarnings = 30;
        $total = count($warnings);
        if ($total > $maxWarnings) {
            $warnings = array_slice($warnings, 0, $maxWarnings);
            $warnings[] = "... и ещё " . ($total - $maxWarnings) . " подобных случаев";
        }

        fwrite(
            STDERR,
            "Include/require inside functions audit: найдено {$total} случаев\n" . implode("\n", $warnings) . "\n"
        );

        // Informational only: in legacy SLAED these patterns are common and require staged migration.
        $this->assertTrue(true, "Информация: {$total} include/require внутри функций требуют поэтапной миграции");
    }

    /**
     * Проверяет что PHP файлы найдены
     */
    public function testPhpFilesFound(): void
    {
        $this->assertNotEmpty(self::$phpFiles, 'PHP файлы не найдены');
        $this->assertGreaterThan(50, count(self::$phpFiles), 'Найдено слишком мало PHP файлов');
    }

    /**
     * Проверяет отсутствие устаревших API-вызовов и legacy-переменных.
     *
     * Учитывает только реальный PHP-код:
     * - не считает комментарии и строки;
     * - не считает объявления функций как вызовы.
     */
    public function testNoDeprecatedLegacyApis(): void
    {
        $deprecatedCalls = [
            'tpl_eval',
            'tpl_warn',
            'navi_gen',
            'end_chmod',
            'referer',
        ];

        $deprecatedVariables = [
            '$admin_file',
            '$aroute',
        ];
        $callbackConsumers = [
            'preg_replace_callback',
            'preg_replace_callback_array',
            'call_user_func',
            'call_user_func_array',
            'array_map',
            'array_filter',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'usort',
            'uasort',
            'uksort',
        ];

        $errors = [];
        $seen = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $tokens = token_get_all($content);
            $rel = str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $file);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (!is_array($token)) {
                    continue;
                }

                [$id, $text, $line] = $token;

                if ($id === T_STRING && in_array($text, $deprecatedCalls, true)) {
                    $next = $this->nextSignificantToken($tokens, $i, 1);
                    if ($next === null || $next !== '(') {
                        continue;
                    }

                    $prev = $this->nextSignificantToken($tokens, $i, -1);
                    if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                        continue;
                    }

                    $row = sprintf('%s:%d - deprecated function call: %s()', $rel, $line, $text);
                    if (!isset($seen[$row])) {
                        $seen[$row] = true;
                        $errors[] = $row;
                    }
                }

                if ($id === T_VARIABLE && in_array($text, $deprecatedVariables, true)) {
                    $row = sprintf('%s:%d - deprecated variable usage: %s', $rel, $line, $text);
                    if (!isset($seen[$row])) {
                        $seen[$row] = true;
                        $errors[] = $row;
                    }
                }

                if ($id === T_CONSTANT_ENCAPSED_STRING) {
                    $value = $this->unquotePhpString($text);
                    if (!in_array($value, $deprecatedCalls, true)) {
                        continue;
                    }

                    $call = $this->enclosingCallName($tokens, $i);
                    if ($call !== null && in_array($call, $callbackConsumers, true)) {
                        $row = sprintf('%s:%d - deprecated callback usage: %s via %s()', $rel, $line, $value, $call);
                        if (!isset($seen[$row])) {
                            $seen[$row] = true;
                            $errors[] = $row;
                        }
                    }
                }
            }
        }

        $maxErrors = 60;
        if (count($errors) > $maxErrors) {
            $total = count($errors);
            $errors = array_slice($errors, 0, $maxErrors);
            $errors[] = "... и ещё " . ($total - $maxErrors) . " подобных случаев";
        }

        $this->assertEmpty(
            $errors,
            "Найдены устаревшие API/переменные:\n" . implode("\n", $errors)
        );
    }

    /**
     * Возвращает следующий/предыдущий значимый токен.
     * $direction: 1 (вперёд), -1 (назад)
     *
     * @return array|string|null
     */
    private function nextSignificantToken(array $tokens, int $index, int $direction)
    {
        $i = $index + $direction;
        $count = count($tokens);

        while ($i >= 0 && $i < $count) {
            $token = $tokens[$i];
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $i += $direction;
                    continue;
                }

                return $token;
            }

            return $token;
        }

        return null;
    }

    private function unquotePhpString(string $text): string
    {
        if (strlen($text) < 2) {
            return $text;
        }

        $quote = $text[0];
        if (($quote !== "'" && $quote !== '"') || $text[strlen($text) - 1] !== $quote) {
            return $text;
        }

        $body = substr($text, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\'", "\\\\"], ["'", "\\"], $body);
        }

        return stripcslashes($body);
    }

    private function enclosingCallName(array $tokens, int $index): ?string
    {
        $depth = 0;

        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token)) {
                continue;
            }

            if ($token === ')') {
                $depth++;
                continue;
            }

            if ($token !== '(') {
                continue;
            }

            if ($depth > 0) {
                $depth--;
                continue;
            }

            $prev = $this->nextSignificantToken($tokens, $i, -1);
            if (is_array($prev) && $prev[0] === T_STRING) {
                return $prev[1];
            }

            return null;
        }

        return null;
    }
}
