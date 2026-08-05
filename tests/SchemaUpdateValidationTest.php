<?php
/**
 * Validates that update SQL references only tables present in table.sql, and that every column the
 * update declares carries the definition the fresh schema gives it. A fresh install and an upgraded
 * one must end on the same table: where the two files disagree, one of the two installations is
 * wrong and nothing at runtime can tell which.
 */

use PHPUnit\Framework\TestCase;

class SchemaUpdateValidationTest extends TestCase
{
    private static string $basePath;
    private static array $tables = [];
    private static array $columns = [];

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

        preg_match_all('/CREATE\s+TABLE\s+`\{prefix\}_([a-z0-9_]+)`\s*\((.*?)\n\)\s*ENGINE/is', $content, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            foreach (explode("\n", $block[2]) as $line) {
                $line = rtrim(trim($line), ',');
                if (!preg_match('/^`([a-z0-9_]+)`\s+(.+)$/i', $line, $column)) {
                    continue;
                }
                self::$columns[strtolower($block[1])][strtolower($column[1])] = self::normalizeType($column[2]);
            }
        }

        // 'modules' table is deprecated and not part of schema anymore.
    }

    /**
     * Reduce one column definition to the form two files can be compared in: collapsed whitespace,
     * one case, and BOOLEAN spelled as the TINYINT(1) the server stores it as either way.
     */
    private static function normalizeType(string $type): string
    {
        $type = strtoupper(trim(preg_replace('/\s+/', ' ', $type)));
        return preg_replace('/\bBOOLEAN\b/', 'TINYINT(1)', $type);
    }

    /**
     * The update channels this test reads, as relative path => absolute path. Both of them declare
     * columns of the same tables, and an installation runs one or the other, never both.
     */
    private static function getUpdateFiles(): array
    {
        $out = [];
        foreach (['setup/sql/table_update6_3.sql', 'setup/sql/update6_3_patch.sql'] as $name) {
            if (file_exists(self::$basePath.'/'.$name)) {
                $out[$name] = self::$basePath.'/'.$name;
            }
        }

        return $out;
    }

    /**
     * Every column definition the update file declares, as [table, column, definition, line].
     * Three shapes count: the MODIFY of an ALTER, and the definition handed to the addcol or the
     * modcol procedure. modcol is the conditional MODIFY, so what it forces a column onto is a
     * declaration of that column exactly like the other two.
     */
    private static function getUpdateColumns(string $content): array
    {
        $out = [];
        preg_match_all('/ALTER\s+TABLE\s+`\{prefix\}_([a-z0-9_]+)`(.*?);/is', $content, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($blocks as $block) {
            $at = substr_count($content, "\n", 0, $block[0][1]) + 1;
            foreach (explode("\n", $block[2][0]) as $step => $line) {
                $line = rtrim(trim($line), ',');
                if (!preg_match('/^MODIFY\s+`([a-z0-9_]+)`\s+(.+)$/i', $line, $column)) {
                    continue;
                }
                $out[] = [strtolower($block[1][0]), strtolower($column[1]), self::normalizeType($column[2]), $at + $step];
            }
        }

        preg_match_all("/CALL\s+(?:addcol|modcol)\('\{prefix\}_([a-z0-9_]+)',\s*'([a-z0-9_]+)',\s*'(.*?)'\);/i", $content, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($calls as $call) {
            $at = substr_count($content, "\n", 0, $call[0][1]) + 1;
            $out[] = [strtolower($call[1][0]), strtolower($call[2][0]), self::normalizeType(str_replace("\\'", "'", $call[3][0])), $at];
        }

        return $out;
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

    public function testUpdateSqlColumnsMatchSchema(): void
    {
        $files = self::getUpdateFiles();
        if (empty($files) || empty(self::$columns)) {
            $this->markTestSkipped('table.sql or no update channel available');
            return;
        }

        $errors = [];
        foreach ($files as $name => $file) {
            foreach (self::getUpdateColumns(file_get_contents($file)) as [$table, $column, $type, $line]) {
                $want = self::$columns[$table][$column] ?? null;

                if ($want === null) {
                    $errors[] = sprintf(
                        '%s:%d - %s.%s is declared here but not by table.sql',
                        $name,
                        $line,
                        $table,
                        $column
                    );
                    continue;
                }

                if ($want !== $type) {
                    $errors[] = sprintf(
                        "%s:%d - %s.%s disagrees with table.sql\n    fresh: %s\n    upgrade: %s",
                        $name,
                        $line,
                        $table,
                        $column,
                        $want,
                        $type
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "A fresh install and an upgrade would not produce the same table:\n".implode("\n", $errors)
        );
    }

    public function testUpdateSqlDeclaresEachColumnOnce(): void
    {
        $files = self::getUpdateFiles();
        if (empty($files)) {
            $this->markTestSkipped('no update channel found');
            return;
        }

        $errors = [];
        foreach ($files as $name => $file) {
            $seen = [];
            foreach (self::getUpdateColumns(file_get_contents($file)) as [$table, $column, $type, $line]) {
                $seen[$table.'.'.$column][] = [$type, $line];
            }

            foreach ($seen as $column => $list) {
                if (count($list) < 2 || count(array_unique(array_column($list, 0))) < 2) {
                    continue;
                }
                $errors[] = sprintf(
                    '%s - %s is declared %d times and the definitions differ (lines %s) - the last one silently wins',
                    $name,
                    $column,
                    count($list),
                    implode(', ', array_column($list, 1))
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "An update channel contradicts itself:\n".implode("\n", $errors)
        );
    }
}
