<?php
/**
 * Validates that update SQL references only tables present in table.sql.
 */

use PHPUnit\Framework\TestCase;

class SchemaUpdateValidationTest extends TestCase
{
    private static string $basePath;
    private static array $tables = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::loadSchemaTables();
    }

    private static function loadSchemaTables(): void
    {
        $schemaFile = self::$basePath.'/setup/sql/table.sql';
        if (!file_exists($schemaFile)) {
            return;
        }

        $content = file_get_contents($schemaFile);
        preg_match_all('/CREATE\s+TABLE\s+[`\'"]?\{prefix\}_([a-z0-9_]+)[`\'"]?/i', $content, $matches);

        foreach ($matches[1] as $table) {
            self::$tables[strtolower($table)] = true;
        }

        // 'modules' table is deprecated and not part of schema anymore.
    }

    public function testUpdateSqlTablesExistInSchema(): void
    {
        $updateFile = self::$basePath.'/setup/sql/table_update6_3.sql';
        if (!file_exists($updateFile)) {
            $this->markTestSkipped('table_update6_3.sql not found');
            return;
        }

        if (empty(self::$tables)) {
            $this->markTestSkipped('table.sql not found or contains no tables');
            return;
        }

        $content = file_get_contents($updateFile);
        preg_match_all('/\{prefix\}_([a-z0-9_]+)/i', $content, $matches, PREG_OFFSET_CAPTURE);

        $skipTables = [
            'modules' => true,
        ];

        $errors = [];
        foreach ($matches[1] as $match) {
            $table = strtolower($match[0]);
            $offset = $match[1];

            if (isset($skipTables[$table])) {
                continue;
            }

            if (!isset(self::$tables[$table])) {
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                $errors[] = sprintf(
                    "setup/sql/table_update6_3.sql:%d - table '%s' not found in table.sql",
                    $line,
                    $table
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Update SQL references missing tables:\n".implode("\n", $errors)
        );
    }
}
