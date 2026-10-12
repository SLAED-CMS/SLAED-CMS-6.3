<?php
use PHPUnit\Framework\TestCase;

# Holds the column domains of storage/update/sql/table.sql, so a later table or column cannot leave the conventions of the release unnoticed
class SchemaConventionTest extends TestCase
{
    private const IP = "VARCHAR(45) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL DEFAULT ''";
    private const MODUL = 'VARCHAR(50) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL';
    private const TIMER = 'BIGINT UNSIGNED NOT NULL DEFAULT 0';
    private const MOMENT = ['DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT NULL', 'DATETIME NOT NULL'];
    private const TIMES = ['time', 'ltime', 'etime', 'ntime', 'endtime', 'enddate', 'expire', 'expires', 'created', 'updated', 'published', 'annulled', 'linked',
        'lastlog', 'lastvis', 'regdate', 'due', 'locked', 'last', 'shown', 'edited', 'deleted', 'checked', 'synced', 'reported', 'activity', 'modified', 'birthday'];
    private const OWNTIME = [
        'node_sync.modified' => "VARCHAR(100) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL DEFAULT ''",
        'users.birthday' => 'DATE DEFAULT NULL',
    ];

    private static array $columns = [];

    # Read every column of the fresh schema as table => column => definition in one case with collapsed whitespace
    public static function setUpBeforeClass(): void
    {
        $text = (string)file_get_contents(dirname(__DIR__).'/storage/update/sql/table.sql');
        preg_match_all('/CREATE\s+TABLE\s+`\{prefix\}_([a-z0-9_]+)`\s*\((.*?)\n\)\s*ENGINE/is', $text, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            foreach (explode("\n", $block[2]) as $line) {
                if (preg_match('/^`([a-z0-9_]+)`\s+(.+?),?$/i', trim($line), $col)) {
                    self::$columns[$block[1]][$col[1]] = strtoupper((string)preg_replace('/\s+/', ' ', $col[2]));
                }
            }
        }
    }

    # An address compares byte by byte in ASCII, a module or scope is a binary ASCII name of 50, so a request value of another case never meets a stored row
    public function testAddressAndModuleColumnsKeepTheirDomain(): void
    {
        $this->assertNotEmpty(self::$columns, 'table.sql declares no table');
        $bad = [];
        foreach (self::$columns as $tab => $cols) {
            foreach ($cols as $col => $def) {
                $want = match ($col) {
                    'ip', 'eip' => self::IP,
                    'modul', 'scope' => self::MODUL,
                    default => null,
                };
                if ($want !== null && $def !== $want) $bad[] = $tab.'.'.$col.': '.$def;
            }
        }
        $this->assertSame([], $bad, 'A column leaves the domain of its kind');
    }

    # A time is a moment in DATETIME or a machine timer in unix seconds, never text; the HTTP validator of a feed and a calendar day keep their own form
    public function testTimeColumnsAreMomentsOrTimers(): void
    {
        $bad = [];
        foreach (self::$columns as $tab => $cols) {
            foreach ($cols as $col => $def) {
                $key = $tab.'.'.$col;
                if (!in_array($col, self::TIMES, true) && !preg_match('/^(DATE|DATETIME|TIMESTAMP|TIME|YEAR)\b/', $def)) continue;
                $good = isset(self::OWNTIME[$key]) ? $def === self::OWNTIME[$key] : ($def === self::TIMER || in_array($def, self::MOMENT, true));
                if (!$good) $bad[] = $key.': '.$def;
            }
        }
        $this->assertSame([], $bad, 'A time column leaves the form of a moment or a timer');
    }

    # The collation of text comes from {collate} of config/db.php alone; the one column that spells utf8mb4 is the case-sensitive address list of _maildead
    public function testNoColumnSpellsAUnicodeCollation(): void
    {
        $bad = [];
        foreach (self::$columns as $tab => $cols) {
            foreach ($cols as $col => $def) {
                if (str_contains($def, 'COLLATE UTF8MB4') && $tab.'.'.$col !== 'maildead.email') $bad[] = $tab.'.'.$col.': '.$def;
            }
        }
        $this->assertSame([], $bad, 'A column spells a collation of its own');
        $this->assertStringContainsString('COLLATE UTF8MB4_BIN', self::$columns['maildead']['email'] ?? '', '_maildead.email is no longer case-sensitive');
    }
}
