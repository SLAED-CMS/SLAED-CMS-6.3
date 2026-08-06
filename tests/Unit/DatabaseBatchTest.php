<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The SQL splitter shared by the Inquiry tab of the database module and the module installer. Both run
 * scripts written by hand against a live installation, so what they hand to the driver has to be what the
 * file said, byte for byte, and what the report shows has to be the statement rather than the comment in
 * front of it.
 * The three functions are pure and are lifted out of core/admin.php by name: the file guards itself with
 * ADMIN_FILE and the rest of it needs a request, so it cannot be required from CLI, and copying the bodies
 * into the test would let the shipped code drift away from what is asserted here.
 */
final class DatabaseBatchTest extends TestCase
{
    # Load the pure helpers of the admin module into this process once, straight from the shipped source
    public static function setUpBeforeClass(): void
    {
        if (function_exists('getSqlbatch')) return;
        if (!defined('PREFIX_DB')) define('PREFIX_DB', 'test');
        $GLOBALS['conf']['db'] = ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'] + (array)($GLOBALS['conf']['db'] ?? []);
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/admin.php');
        foreach (['getSqlbatch', 'getSqlclean', 'getSqlinfo', 'getSqlFilled', 'getSqlFileTables'] as $name) {
            $from = strpos($code, 'function '.$name.'(');
            $to = ($from === false) ? false : strpos($code, "\n}\n", $from);
            if ($from === false || $to === false) throw new \RuntimeException($name.'() is gone from core/admin.php');
            try {
                eval(substr($code, $from, $to - $from + 3));
            } catch (\ParseError $error) {
                throw new \RuntimeException($name.'() could not be lifted out of core/admin.php: '.$error->getMessage());
            }
        }
    }

    # A backslash escape inside a string literal is part of the statement and must reach the driver untouched
    # This is the regression: the handler ran stripslashes() over the input, which turned DEFAULT \'\' into DEFAULT '' and made the column definition unparsable
    #[Test]
    public function everyEscapeSurvivesTheSplit(): void
    {
        $sql = "CALL addcol('t', 'path', 'VARCHAR(255) NOT NULL DEFAULT \\'\\'');\n";
        $out = getSqlbatch($sql);
        $this->assertSame('', $out['error']);
        $this->assertCount(1, $out['statements']);
        $this->assertSame(rtrim(trim($sql), ';'), $out['statements'][0], 'The splitter changed the bytes of the statement');
        $back = getSqlbatch("SELECT 'a\\\\b', \"c\\\"d\", 'e\\'f';");
        $this->assertStringContainsString('\\\\', $back['statements'][0], 'A doubled backslash did not survive');
        $this->assertStringContainsString("\\'", $back['statements'][0], 'An escaped quote did not survive');
    }

    # A comment in front of a statement is not the statement: it is dropped so the report names the query and not the file header
    #[Test]
    public function aLeadingCommentIsNotPartOfTheStatement(): void
    {
        $out = getSqlbatch("# header\n# more\nSELECT 1;\n-- line\nALTER TABLE `x` MODIFY `a` INT;\n/* block */\nUPDATE `y` SET a = 1;");
        $this->assertSame(['SELECT 1', 'ALTER TABLE `x` MODIFY `a` INT', 'UPDATE `y` SET a = 1'], $out['statements']);
    }

    # A block that is nothing but comments is dropped rather than sent as a query with no statement in it
    #[Test]
    public function aCommentOnlyBlockIsDropped(): void
    {
        $this->assertSame(['SELECT 1'], getSqlbatch("SELECT 1;\n# trailing\n")['statements']);
        $this->assertSame(['SELECT 1'], getSqlbatch("SELECT 1;\n/* tail */\n")['statements']);
        $this->assertSame([], getSqlbatch("# nothing but a comment\n")['statements']);
        $this->assertSame([], getSqlbatch("/* nothing */\n")['statements']);
    }

    # A comment inside a statement stays, because removing it would change what the driver parses
    #[Test]
    public function anInnerCommentIsKept(): void
    {
        $out = getSqlbatch("SELECT 1 # tail comment\n, 2;");
        $this->assertCount(1, $out['statements']);
        $this->assertStringContainsString('# tail comment', $out['statements'][0]);
    }

    # The report reads the leading keyword of the statement and the table that keyword introduces, never the first backtick it can find
    #[Test]
    public function theReportNamesTheRealTypeAndTable(): void
    {
        $cases = [
            ["# banner\nCALL addidx('t_comment', 'idx', '`modul`, `cid`', 0)", 'CALL', ''],
            ['CREATE PROCEDURE addcol(IN ptab VARCHAR(128)) BEGIN SET @s = CONCAT(\'ALTER TABLE `\', ptab, \'`\'); END', 'CREATE', ''],
            ['DROP PROCEDURE IF EXISTS addcol', 'DROP', ''],
            ['UPDATE `t_files` AS x SET x.a = (SELECT COUNT(*) FROM `t_comment`)', 'UPDATE', 't_files'],
            ['ALTER TABLE `t_users` MODIFY `points` INT UNSIGNED NOT NULL DEFAULT 0', 'ALTER', 't_users'],
            ['DROP TABLE IF EXISTS `t_old`', 'DROP', 't_old'],
            ['INSERT INTO `t_log` (a) VALUES (1)', 'INSERT', 't_log'],
            ['DELETE FROM t_log WHERE a = 1', 'DELETE', 't_log'],
            ['SELECT a FROM `t_news` WHERE id = 1', 'SELECT', 't_news'],
        ];
        foreach ($cases as [$sql, $type, $table]) {
            $info = getSqlinfo(getSqlclean($sql));
            $this->assertSame($type, $info['type'], 'Wrong type for: '.$sql);
            $this->assertSame($table, $info['table'], 'Wrong table for: '.$sql);
        }
    }

    # The delimiter directive still switches the terminator, which is what keeps a stored procedure in one block
    #[Test]
    public function theDelimiterDirectiveStillHolds(): void
    {
        $out = getSqlbatch("DELIMITER $$\nCREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END$$\nDELIMITER ;\nSELECT 3;");
        $this->assertSame('', $out['error']);
        $this->assertCount(2, $out['statements'], 'The procedure body was split on its inner semicolons');
        $this->assertStringStartsWith('CREATE PROCEDURE p()', $out['statements'][0]);
        $this->assertSame('SELECT 3', $out['statements'][1]);
    }

    # Input that cannot be split at all is reported rather than half executed
    #[Test]
    public function unterminatedInputIsRefused(): void
    {
        $this->assertSame('Unclosed quoted string in SQL input', getSqlbatch("SELECT 'open;")['error']);
        $this->assertSame('Unclosed block comment in SQL input', getSqlbatch('SELECT 1; /* open')['error']);
    }

    # A script reaches the driver with every installation placeholder filled, not only the table prefix
    # A module table.sql declares its engine and collation through the other three, so a partial substitution hands MySQL a statement it cannot parse
    #[Test]
    public function everyPlaceholderIsFilledBeforeTheDriverSeesIt(): void
    {
        $out = getSqlFilled('CREATE TABLE `{prefix}_x` (a INT) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};');
        $this->assertStringContainsString('`'.PREFIX_DB.'_x`', $out);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', $out, 'A placeholder reached the driver unfilled');
        foreach (glob(dirname(__DIR__, 2).'/modules/*/sql/*.sql') ?: [] as $file) {
            $name = basename(dirname($file, 2)).'/'.basename($file);
            $filled = getSqlFilled((string)file_get_contents($file));
            $this->assertDoesNotMatchRegularExpression('/\{[a-z]+\}/', $filled, $name.' carries a placeholder nothing fills');
        }
    }

    # The installer asks the script which tables it owns, so a module list can tell installed from not and an uninstall drops what the script created and nothing else
    #[Test]
    public function theInstallerReadsTheTablesAScriptOwns(): void
    {
        $sql = "CREATE TABLE `{prefix}_one` (a INT);\nINSERT INTO `{prefix}_two` (a) VALUES (1);\nCREATE TABLE `{prefix}_one` (b INT);";
        $this->assertSame([PREFIX_DB.'_one', PREFIX_DB.'_two'], getSqlFileTables($sql), 'The full table set is wrong');
        $this->assertSame([PREFIX_DB.'_one'], getSqlFileTables($sql, 'CREATE'), 'An uninstall would touch a table the script never created');
        $file = dirname(__DIR__, 2).'/modules/news/sql/table.sql';
        if (!is_file($file)) $this->markTestSkipped('The news module carries no table.sql on this installation');
        $this->assertSame([PREFIX_DB.'_news'], getSqlFileTables((string)file_get_contents($file), 'CREATE'));
    }

    # All three callers split with the same code: the Inquiry tab, the module installer and the system installer
    # core/admin.php defines functions only, so setup/index.php borrows it before the rest of the system exists and its guard admits the installer by name
    # A splitter of its own is what this guards against: the one the installer used to carry cut on line endings and would have cut a literal spanning two lines in half
    #[Test]
    public function theInstallerSplitsWithTheSharedCode(): void
    {
        $setup = (string)file_get_contents(dirname(__DIR__, 2).'/setup/index.php');
        $admin = (string)file_get_contents(dirname(__DIR__, 2).'/core/admin.php');
        $this->assertStringContainsString("require_once BASE_DIR.'/core/admin.php'", $setup, 'The installer no longer loads the shared splitter');
        $this->assertStringNotContainsString('function getSqlStatements(', $setup, 'The installer carries a splitter of its own again');
        $this->assertStringContainsString("!defined('ADMIN_FILE') && !defined('SETUP_FILE')", $admin, 'The shared file refuses the installer that loads it');
        $from = strpos($setup, 'function getSqlFile(');
        $this->assertNotFalse($from, 'getSqlFile() is gone from setup/index.php');
        $body = substr($setup, $from, (int)strpos($setup, "\n}\n", $from) - $from);
        $this->assertStringContainsString('getSqlbatch(', $body, 'The installer does not split through the shared code');
        $this->assertStringContainsString('getSqlinfo(', $body, 'The installer still guesses the table name of a statement on its own');
    }

    # The shipped migration file is the case this was found on: every block carries real SQL and the escaped default reaches the driver as written
    #[Test]
    public function theShippedPatchSplitsIntoRealStatements(): void
    {
        $file = dirname(__DIR__, 2).'/setup/sql/update6_3_patch.sql';
        $this->assertFileExists($file);
        $out = getSqlbatch((string)file_get_contents($file));
        $this->assertSame('', $out['error']);
        $this->assertNotEmpty($out['statements']);
        foreach ($out['statements'] as $num => $one) {
            $this->assertSame($one, getSqlclean($one), 'Block '.($num + 1).' still carries a leading comment');
            $this->assertNotSame('SQL', getSqlinfo($one)['type'], 'Block '.($num + 1).' has no readable statement type');
        }
        $hit = array_values(array_filter($out['statements'], static fn(string $one): bool => str_contains($one, "'ascii_bin'")));
        $this->assertNotEmpty($hit, 'The address collation block is gone from the patch');
        $this->assertStringContainsString("DEFAULT \\'\\''", $hit[0], 'The escaped empty default did not survive the split');
    }

    # The one-time text repair goes through the same page, so it has to split the same way, and it may only ever touch the three fields it names
    # Its blocks run in one order for a reason: every break block precedes every entity block, because that is the only moment a writer tag can be told from an authored one
    #[Test]
    public function theOneTimeTextRepairSplitsAndStaysInScope(): void
    {
        $file = dirname(__DIR__, 2).'/setup/sql/update6_3_text_once.sql';
        $this->assertFileExists($file);
        $out = getSqlbatch((string)file_get_contents($file));
        $this->assertSame('', $out['error']);
        $this->assertNotEmpty($out['statements']);
        $last = -1;
        foreach ($out['statements'] as $num => $one) {
            $this->assertSame($one, getSqlclean($one), 'Block '.($num + 1).' still carries a leading comment');
            $this->assertSame('UPDATE', getSqlinfo($one)['type'], 'Block '.($num + 1).' is not an update, so this file does more than repair text');
            $this->assertMatchesRegularExpression('#`\{prefix\}_(comment|privat)`#', $one, 'Block '.($num + 1).' names a table this repair may not touch');
            if (str_contains($one, '<br')) $last = $num;
        }
        $first = null;
        foreach ($out['statements'] as $num => $one) {
            if ($first === null && str_contains($one, '&amp;')) $first = $num;
        }
        $this->assertNotNull($first, 'The ampersand is never decoded, so the repair leaves the escape it was written for');
        $this->assertGreaterThan($last, $first, 'An entity block runs before a break block, which would delete the break tags an author typed');
    }
}
