<?php
use PHPUnit\Framework\TestCase;

# Holds the column domains of storage/update/sql/table.sql, so a later table or column cannot leave the conventions of the release unnoticed
class SchemaConventionTest extends TestCase
{
    private const IP = "VARCHAR(45) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL DEFAULT ''";
    private const MODUL = 'VARCHAR(50) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL';
    private const LANG = "VARCHAR(10) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL DEFAULT ''";
    private const TIMER = 'BIGINT UNSIGNED NOT NULL DEFAULT 0';
    private const NULLS = [
        'comment.reqkey' => 'BINARY(16) DEFAULT NULL',
        'node_assets.mime' => 'VARCHAR(100) CHARACTER SET ASCII COLLATE ASCII_BIN NULL DEFAULT NULL',
        'node_assets.size' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
        'node_assets.width' => 'INT UNSIGNED NULL DEFAULT NULL',
        'node_assets.height' => 'INT UNSIGNED NULL DEFAULT NULL',
        'node_assets.duration' => 'INT UNSIGNED NULL DEFAULT NULL',
        'admins.email' => 'VARCHAR(255) DEFAULT NULL',
        'points.rid' => 'BIGINT UNSIGNED DEFAULT NULL',
        'routes.cur' => 'TINYINT UNSIGNED AS (IF(`STATE` = 1, 1, NULL)) STORED',
        'users.birthday' => 'DATE DEFAULT NULL',
        'users.email' => 'VARCHAR(255) DEFAULT NULL',
        'users_temp.email' => 'VARCHAR(255) DEFAULT NULL',
    ];
    private const MOMENT = ['DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT NULL', 'DATETIME NOT NULL'];
    private const TIMES = ['time', 'ltime', 'etime', 'ntime', 'endtime', 'enddate', 'expire', 'expires', 'created', 'updated', 'published', 'annulled', 'linked',
        'lastlog', 'lastvis', 'regdate', 'due', 'locked', 'last', 'shown', 'edited', 'deleted', 'checked', 'synced', 'reported', 'activity', 'modified', 'birthday'];
    private const OWNTIME = [
        'node_sync.modified' => "VARCHAR(100) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL DEFAULT ''",
        'users.birthday' => 'DATE DEFAULT NULL',
    ];

    private static array $columns = [];
    private static array $keys = [];

    # Read every column of the fresh schema as table => column => definition in one case with collapsed whitespace, and every key as table => name => kind and parts
    public static function setUpBeforeClass(): void
    {
        $text = (string)file_get_contents(dirname(__DIR__).'/storage/update/sql/table.sql');
        preg_match_all('/CREATE\s+TABLE\s+`\{prefix\}_([a-z0-9_]+)`\s*\((.*?)\n\)\s*ENGINE/is', $text, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            foreach (explode("\n", $block[2]) as $line) {
                if (preg_match('/^`([a-z0-9_]+)`\s+(.+?),?$/i', trim($line), $col)) {
                    self::$columns[$block[1]][$col[1]] = strtoupper((string)preg_replace('/\s+/', ' ', $col[2]));
                }
                if (preg_match('/^(PRIMARY|UNIQUE KEY|KEY)\s*(?:KEY\s*)?(?:`([a-z0-9_]+)`)?\s*\((.+)\),?$/i', trim($line), $key)) {
                    self::$keys[$block[1]][$key[2] ?: 'PRIMARY'] = ['unique' => strtoupper($key[1]) !== 'KEY', 'parts' => explode(', ', $key[3])];
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
                    'lang' => self::LANG,
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

    # NULL means none or not recorded: a moment not yet reached, a request key, a measure of an outside file, a reversal, a calendar day; a flag, a counter or a text is never NULL
    public function testNullMeansNoneOrNotRecorded(): void
    {
        $bad = [];
        foreach (self::$columns as $tab => $cols) {
            foreach ($cols as $col => $def) {
                $key = $tab.'.'.$col;
                if (str_contains($def, 'NOT NULL') || $def === 'DATETIME DEFAULT NULL' || (self::NULLS[$key] ?? '') === $def) continue;
                $bad[] = $key.': '.$def;
            }
        }
        $this->assertSame([], $bad, 'A column is nullable without a meaning of NULL');
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

    # A key holds its column whole wherever the 3072 bytes of an InnoDB key allow it, so uniqueness and lookups cover the full value; only a longer column keeps a prefix
    public function testKeysHoldWholeColumns(): void
    {
        $this->assertNotEmpty(self::$keys, 'table.sql declares no key');
        $bad = [];
        foreach (self::$keys as $tab => $keys) {
            foreach ($keys as $name => $key) {
                foreach ($key['parts'] as $part) {
                    if (!preg_match('/^`([a-z0-9_]+)`\((\d+)\)/', $part, $hit)) continue;
                    $def = self::$columns[$tab][$hit[1]] ?? '';
                    $size = preg_match('/^VARCHAR\((\d+)\)/', $def, $len) ? intval($len[1]) * (str_contains($def, 'ASCII') ? 1 : 4) : PHP_INT_MAX;
                    if ($size <= 3072) $bad[] = $tab.'.'.$name.': '.$part;
                }
            }
        }
        $this->assertSame([], $bad, 'A key cuts a column it could hold whole');
    }

    # A plain key that is the left part of another key of its table serves no query the longer one does not, and every write pays for it
    public function testNoKeyIsCoveredByAnother(): void
    {
        $bad = [];
        foreach (self::$keys as $tab => $keys) {
            foreach ($keys as $name => $key) {
                if ($key['unique']) continue;
                foreach ($keys as $other => $more) {
                    if ($other !== $name && count($more['parts']) >= count($key['parts']) && array_slice($more['parts'], 0, count($key['parts'])) === $key['parts']) {
                        $bad[] = $tab.'.'.$name.' within '.$other;
                    }
                }
            }
        }
        $this->assertSame([], $bad, 'A key is covered by the left part of another');
    }
}
