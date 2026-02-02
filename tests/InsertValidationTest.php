<?php
/**
 * Тест проверки INSERT запросов на соответствие структуре таблиц
 * Выявляет отсутствующие NOT NULL поля без DEFAULT значений
 */

use PHPUnit\Framework\TestCase;

class InsertValidationTest extends TestCase
{
    private static string $basePath;
    private static array $tableSchema = [];
    private static array $inserts = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::parseTableSchema();
        self::scanInsertQueries();
    }

    /**
     * Парсит table.sql и извлекает обязательные поля (NOT NULL без DEFAULT)
     */
    private static function parseTableSchema(): void
    {
        $sqlFile = self::$basePath . '/setup/sql/table.sql';
        $content = file_get_contents($sqlFile);

        preg_match_all('/CREATE TABLE [`\']?\{prefix\}_(\w+)[`\']?\s*\((.*?)\)\s*ENGINE/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tableName = $match[1];
            $columns = $match[2];

            self::$tableSchema[$tableName] = [
                'required' => [],
                'all' => []
            ];

            $lines = explode("\n", $columns);
            foreach ($lines as $line) {
                $line = trim($line);

                if (preg_match('/^(PRIMARY|KEY|UNIQUE|INDEX|CONSTRAINT)/i', $line)) {
                    continue;
                }

                if (preg_match('/^[`\']?(\w+)[`\']?\s+(\w+)/i', $line, $colMatch)) {
                    $colName = $colMatch[1];

                    self::$tableSchema[$tableName]['all'][] = $colName;

                    $isNotNull = stripos($line, 'NOT NULL') !== false;
                    $hasDefault = stripos($line, 'DEFAULT') !== false;
                    $isAutoIncrement = stripos($line, 'AUTO_INCREMENT') !== false;

                    if ($isNotNull && !$hasDefault && !$isAutoIncrement) {
                        self::$tableSchema[$tableName]['required'][] = $colName;
                    }
                }
            }
        }
    }

    /**
     * Сканирует PHP файлы и находит INSERT запросы
     */
    private static function scanInsertQueries(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (strpos($path, 'vendor') !== false || strpos($path, 'tests') !== false) {
                continue;
            }

            $content = file_get_contents($path);

            preg_match_all('/INSERT\s+INTO\s+["\'\s\.\$\w]*_(\w+)["\'\s]*\(([^)]+)\)\s*VALUES/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $tableName = $match[1][0];
                $columnsStr = $match[2][0];
                $offset = $match[0][1];

                $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                $columnsStr = preg_replace('/[\$\w]+\./', '', $columnsStr);
                $columnsStr = preg_replace('/[`\'"]/', '', $columnsStr);
                $columns = array_map('trim', explode(',', $columnsStr));
                $columns = array_filter($columns);

                $columns = array_map(function($col) {
                    return preg_replace('/^:/', '', trim($col));
                }, $columns);

                self::$inserts[] = [
                    'file' => str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $path),
                    'line' => $lineNumber,
                    'table' => $tableName,
                    'columns' => $columns
                ];
            }
        }
    }

    /**
     * Проверяет все INSERT запросы на наличие обязательных полей
     */
    public function testAllInsertQueriesHaveRequiredFields(): void
    {
        $errors = [];

        foreach (self::$inserts as $insert) {
            $table = $insert['table'];

            if (!isset(self::$tableSchema[$table])) {
                continue;
            }

            $required = self::$tableSchema[$table]['required'];
            $provided = $insert['columns'];
            $missing = array_diff($required, $provided);

            if (!empty($missing)) {
                $errors[] = sprintf(
                    "%s:%d - таблица '%s' - отсутствуют: %s",
                    $insert['file'],
                    $insert['line'],
                    $table,
                    implode(', ', $missing)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены INSERT запросы без обязательных полей:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет, что table.sql существует и содержит таблицы
     */
    public function testTableSchemaExists(): void
    {
        $sqlFile = self::$basePath . '/setup/sql/table.sql';
        $this->assertFileExists($sqlFile, 'Файл table.sql не найден');
        $this->assertNotEmpty(self::$tableSchema, 'Схема таблиц пуста');
    }

    /**
     * Проверяет, что найдены INSERT запросы для анализа
     */
    public function testInsertQueriesFound(): void
    {
        $this->assertNotEmpty(self::$inserts, 'INSERT запросы не найдены');
    }
}
