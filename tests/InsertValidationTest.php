<?php

use PHPUnit\Framework\TestCase;

# Checks INSERT queries against the table schema and finds missing NOT NULL columns that have no DEFAULT
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

    # Parses table.sql and collects the required columns of each table (NOT NULL without DEFAULT)
    private static function parseTableSchema(): void
    {
        $sqlFile = self::$basePath.'/storage/update/sql/table.sql';
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

    # Scans the PHP files and collects their INSERT queries with file, line, table and column list
    private static function scanInsertQueries(): void
    {
        $iterator = getTreeFiles(self::$basePath);

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (strpos($path, 'vendor') !== false || strpos($path, 'tests') !== false) {
                continue;
            }

            $content = file_get_contents($path);

            preg_match_all('/INSERT\s+INTO\s+["\'\s\.\$\w]*(?<!\w)_(\w+)["\'\s]*\(([^)]+)\)\s*VALUES/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

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
                    'file' => str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $path),
                    'line' => $lineNumber,
                    'table' => $tableName,
                    'columns' => $columns
                ];
            }
        }
    }

    # Checks that every INSERT query supplies all required columns of its table
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
            "Найдены INSERT запросы без обязательных полей:\n".implode("\n", $errors)
        );
    }

    # Checks that table.sql exists and declares tables
    public function testTableSchemaExists(): void
    {
        $sqlFile = self::$basePath.'/storage/update/sql/table.sql';
        $this->assertFileExists($sqlFile, 'Файл table.sql не найден');
        $this->assertNotEmpty(self::$tableSchema, 'Схема таблиц пуста');
    }

    # Checks that the scan found INSERT queries to analyse
    public function testInsertQueriesFound(): void
    {
        $this->assertNotEmpty(self::$inserts, 'INSERT запросы не найдены');
    }
}
