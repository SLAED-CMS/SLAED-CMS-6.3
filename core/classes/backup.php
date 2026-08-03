<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Creates one verified, restorable dump of the selected base tables
# Views, triggers, events and routines are never serialized, so the artifact restores the declared scope and nothing else (docs/BACKUP-2026.md)
class Backup {
    private const CHUNK = 1048576;
    private const BUFFER = 262144;
    private const TRIES = 5;
    private const MINMYSQL = 80000;
    private const MINMARIA = 100400;
    private const ROUTVER = 80020;
    private const DEFAULTS = ['include' => '*', 'exclude' => '', 'schemaonly' => '', 'compress' => 'auto', 'keep' => '0', 'allow_incomplete' => '0'];
    private const MODES = ['auto', 'zip', 'gz', 'bz2', 'none'];
    private const NUMTYPE = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'dec', 'numeric', 'float', 'double', 'real', 'year'];
    private const BINTYPE = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit', 'geometry', 'point', 'linestring',
        'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'];

    private Database $db;
    private array $conf;
    private array $sett;
    private array $caps;
    private string $dir;
    private string $stem;
    private string $stage = '';
    private string $algo = 'none';
    private string $vend = '';
    private string $vers = '';
    private int $srvn = 0;
    private int $tick = 0;
    private int $tnum = 0;
    private int $rnum = 0;
    private bool $fold = false;
    private bool $full = true;
    private array $incl = [];
    private array $excl = [];
    private array $only = [];
    private array $unsup = [];
    private array $unord = [];
    private array $warns = [];

    # Builds the exporter; $caps is the compressor map from checkCompress() and the only injected capability, so tests can drive every branch
    public function __construct(Database $db, array $dbconf, array $settings, string $dir, ?array $caps = null) {
        $this->db = $db;
        $this->conf = $dbconf;
        $this->sett = $settings;
        $this->caps = is_array($caps) ? $caps : checkCompress();
        $this->dir = rtrim(str_replace('\\', '/', $dir), '/');
        $this->stem = self::getArtifactStem($this->getDbName());
        $this->tick = time();
    }

    # Runs the whole backup; a failure before publication removes staging and returns failed, a failure after it leaves the verified artifact in place (D9)
    public function addDatabaseBackup(): array {
        try {
            $this->setPreflight();
            return $this->addBackupRun();
        } catch (Throwable $error) {
            $this->deleteStageRoot();
            return ['status' => 'failed', 'message' => $error->getMessage()];
        }
    }

    # Preflight in the order of D8: each step depends on the previous one, and whatever can fail without the database fails before the transaction opens
    private function setPreflight(): void {
        $this->sett = $this->checkSettingsInput($this->sett);
        $this->setCompressor();
        $this->checkBackupRoot();
        $this->addStageRoot();
        $this->checkLinkProbe();
        $this->checkPrivileges();
    }

    # Validates every setting and compiles the scope patterns; a missing key falls back to its default, a present but invalid value fails the run before any output exists
    private function checkSettingsInput(array $sett): array {
        $rest = array_diff(array_keys($sett), array_keys(self::DEFAULTS));
        if ($rest) throw new RuntimeException('Backup settings contain unknown keys: '.implode(', ', $rest));
        $out = [];
        foreach (self::DEFAULTS as $key => $def) {
            $val = $sett[$key] ?? $def;
            if (!is_string($val) && !is_int($val)) throw new RuntimeException('Backup setting '.$key.' must be a scalar value');
            $out[$key] = trim((string)$val);
        }
        if (!in_array($out['compress'], self::MODES, true)) throw new RuntimeException('Backup setting compress must be one of '.implode(', ', self::MODES));
        if (!preg_match('/^\d+$/', $out['keep'])) throw new RuntimeException('Backup setting keep must be a non-negative integer');
        if ($out['allow_incomplete'] !== '0' && $out['allow_incomplete'] !== '1') throw new RuntimeException('Backup setting allow_incomplete must be exactly 0 or 1');
        $this->incl = $this->getGlobList($out['include'], 'include');
        $this->excl = $this->getGlobList($out['exclude'], 'exclude');
        $this->only = $this->getEngineList($out['schemaonly']);
        return $out;
    }

    # Compiles a comma-separated glob list into anchored expressions
    # The legacy caret form is rejected rather than reinterpreted: it used to switch the whole list between include and exclude mode
    private function getGlobList(string $text, string $key): array {
        $out = [];
        foreach (explode(',', $text) as $one) {
            $glob = trim($one);
            if ($glob === '') continue;
            $legacy = 'Backup setting '.$key.' rejects the legacy caret pattern '.$glob.'; exclusions belong in the exclude setting';
            if (str_contains($glob, '^')) throw new RuntimeException($legacy);
            if (!preg_match('/^[A-Za-z0-9_$\-*?]+$/', $glob)) throw new RuntimeException('Backup setting '.$key.' contains an invalid table pattern: '.$glob);
            $out[] = str_replace(['\*', '\?'], ['.*', '.'], preg_quote($glob, '#'));
        }
        if ($key === 'include' && !$out) throw new RuntimeException('Backup setting include must name at least one table pattern');
        return $out;
    }

    # Compiles the schemaonly engine list; those engines are exported as structure only, by explicit operator choice
    private function getEngineList(string $text): array {
        $out = [];
        foreach (explode(',', $text) as $one) {
            $name = strtoupper(trim($one));
            if ($name === '') continue;
            if (!preg_match('/^[A-Z0-9_]+$/', $name)) throw new RuntimeException('Backup setting schemaonly contains an invalid engine name: '.$name);
            $out[] = $name;
        }
        return $out;
    }

    # Decides whether a table belongs to the selected scope; exclude always wins over include and both are full-name matches, following the server table-name case rules
    private function checkTableScope(string $name): bool {
        $flag = $this->fold ? 'i' : '';
        foreach ($this->excl as $patt) if (preg_match('#^'.$patt.'$#'.$flag, $name)) return false;
        foreach ($this->incl as $patt) if (preg_match('#^'.$patt.'$#'.$flag, $name)) return true;
        return false;
    }

    # Resolves exactly one concrete compressor, because addCompress() treats auto without any compressor as a hard error instead of falling back (D4)
    private function setCompressor(): void {
        $mode = $this->sett['compress'];
        if ($mode === 'auto') {
            $this->algo = !empty($this->caps['zip']) ? 'zip' : (!empty($this->caps['gz']) ? 'gz' : (!empty($this->caps['bz2']) ? 'bz2' : 'none'));
            return;
        }
        if ($mode !== 'none' && empty($this->caps[$mode])) throw new RuntimeException('Backup compressor '.$mode.' is not available on this server');
        $this->algo = $mode;
    }

    # Makes sure the backup root exists and can be written before anything is produced
    private function checkBackupRoot(): void {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true)) throw new RuntimeException('Backup directory cannot be created: '.$this->dir);
        if (!is_dir($this->dir) || !is_writable($this->dir)) throw new RuntimeException('Backup directory is missing or not writable: '.$this->dir);
    }

    # Creates the private staging directory below the backup root, so that publication later stays inside one filesystem
    private function addStageRoot(): void {
        $stage = $this->dir.'/.stage-'.bin2hex(random_bytes(6));
        if (!mkdir($stage, 0700)) throw new RuntimeException('Backup staging directory cannot be created: '.$stage);
        $this->stage = $stage;
    }

    # Probes hard linking the way publication will use it, from staging into the backup root: a pair inside staging proves nothing about the directory that matters (D6)
    private function checkLinkProbe(): void {
        $src = $this->stage.'/probe.tmp';
        $dst = $this->dir.'/.probe-'.bin2hex(random_bytes(6));
        $fh = fopen($src, 'xb');
        if ($fh === false || !fclose($fh)) throw new RuntimeException('Backup cannot write into its staging directory: '.$this->stage);
        if (!link($src, $dst)) {
            unlink($src);
            throw new RuntimeException('Backup cannot publish atomically: hard links are unsupported in '.$this->dir);
        }
        if (!unlink($dst)) $this->addRunWarning('Backup could not remove its link probe: '.$dst);
        unlink($src);
    }

    # Reads vendor, version and the table-name case rule, and refuses a server this class cannot evaluate correctly instead of returning an answer it has no basis for (D2)
    private function getServerInfo(): array {
        $stmt = $this->getSqlRun('SELECT VERSION() AS vers, @@lower_case_table_names AS fold');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!is_array($row)) throw new RuntimeException('Backup could not read the server version');
        $text = (string)($row['vers'] ?? '');
        $this->fold = ((int)($row['fold'] ?? 0)) > 0;
        $this->vend = stripos($text, 'mariadb') !== false ? 'mariadb' : 'mysql';
        $this->vers = $text;
        $plain = str_starts_with($text, '5.5.5-') ? substr($text, 6) : $text;
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $plain, $mat)) throw new RuntimeException('Backup could not parse the server version: '.$text);
        $this->srvn = (int)sprintf('%d%02d%02d', $mat[1], $mat[2], $mat[3]);
        $least = $this->vend === 'mariadb' ? self::MINMARIA : self::MINMYSQL;
        if ($this->srvn < $least) throw new RuntimeException('Backup supports MySQL 8.0 and MariaDB 10.4 or newer, this server reports '.$text);
        return ['vend' => $this->vend, 'vers' => $this->vers, 'srvn' => $this->srvn];
    }

    # Establishes visibility per object class before any catalog query is trusted, because an empty catalog cannot tell absence from invisibility
    private function checkPrivileges(): void {
        $this->getServerInfo();
        $miss = $this->checkGrantAccess($this->getGrantMatrix($this->getGrantLines()), $this->getDbName());
        if ($miss) throw new RuntimeException('Backup cannot prove object visibility: '.implode('; ', $miss));
    }

    # Collects the grant lines of the session, per vendor branch; MariaDB has no USING clause at all and expands nested roles by itself, so one implementation cannot serve both
    private function getGrantLines(): array {
        $out = $this->getGrantRows('SHOW GRANTS FOR CURRENT_USER()');
        $stmt = $this->getSqlRun('SELECT CURRENT_ROLE() AS role');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $role = is_array($row) && is_string($row['role'] ?? null) ? trim($row['role']) : '';
        if ($role === '' || strcasecmp($role, 'NONE') === 0 || strcasecmp($role, 'NULL') === 0) return $out;
        if ($this->vend === 'mysql') return array_merge($out, $this->getGrantRows('SHOW GRANTS FOR CURRENT_USER() USING '.$role));
        return array_merge($out, $this->getGrantRows('SHOW GRANTS FOR '.$this->db->getSqlValue($role)));
    }

    # Reads one grant statement into plain lines
    # The output may address several grantees, so it is never filtered by the requested name, and never written anywhere: MariaDB embeds password hashes in it
    private function getGrantRows(string $sql): array {
        $stmt = $this->getSqlRun($sql);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if (isset($row[0]) && is_string($row[0])) $out[] = $row[0];
        }
        $stmt->closeCursor();
        return $out;
    }

    # Builds the effective privilege set by scope
    # A standalone REVOKE becomes a denial rather than a removal: a MySQL partial revoke narrows a global grant that stays listed
    private function getGrantMatrix(array $lines): array {
        $out = ['grant' => [], 'deny' => []];
        foreach ($lines as $line) {
            $text = trim($line);
            $cut = stripos($text, ' IDENTIFIED BY');
            if ($cut !== false) $text = substr($text, 0, $cut);
            if (!preg_match('/^(GRANT|REVOKE)\s+(.+?)\s+ON\s+(.+?)\s+(?:TO|FROM)\s+/i', $text, $mat)) continue;
            $scope = $this->getScopeKey($mat[3]);
            if ($scope === '') continue;
            $kind = strcasecmp($mat[1], 'GRANT') === 0 ? 'grant' : 'deny';
            foreach ($this->getPrivList($mat[2]) as $priv) $out[$kind][$scope][$priv] = true;
        }
        return $out;
    }

    # Normalizes the privilege part of one grant line; column lists are dropped because a column grant never proves scope-wide visibility
    private function getPrivList(string $text): array {
        $out = [];
        foreach (explode(',', preg_replace('/\([^)]*\)/', '', $text)) as $one) {
            $priv = preg_replace('/\s+/', ' ', strtoupper(trim($one)));
            if ($priv === '') continue;
            $out[] = $priv === 'ALL PRIVILEGES' ? 'ALL' : $priv;
        }
        return $out;
    }

    # Normalizes the target of one grant line into a scope key: * for global, the schema name for a schema grant, and an obj: prefix for anything narrower
    private function getScopeKey(string $text): string {
        $name = preg_replace('/^(?:TABLE|FUNCTION|PROCEDURE)\s+/i', '', trim($text));
        if ($name === '*.*') return '*';
        if (preg_match('/^`?([^`.]+)`?\.\*$/', $name, $mat)) return $mat[1];
        if (preg_match('/^`?([^`.]+)`?\.`?([^`.]+)`?$/', $name, $mat)) return 'obj:'.$mat[1].'.'.$mat[2];
        return '';
    }

    # Evaluates the required visibility per object class against the effective set
    # Every row is satisfied by any of its alternatives, a schema denial beats a global grant, and an object-level grant never satisfies a class-wide claim
    private function checkGrantAccess(array $mtrx, string $schema): array {
        $glob = $mtrx['grant']['*'] ?? [];
        $part = $mtrx['grant'][$schema] ?? [];
        $dglob = $mtrx['deny']['*'] ?? [];
        $dpart = $mtrx['deny'][$schema] ?? [];
        $deny = static fn(string $priv): bool => isset($dglob[$priv]) || isset($dglob['ALL']) || isset($dpart[$priv]) || isset($dpart['ALL']);
        $all = static fn(string $priv): bool => (isset($glob[$priv]) || isset($glob['ALL'])) && !$deny($priv);
        $any = static fn(string $priv): bool => $all($priv) || ((isset($part[$priv]) || isset($part['ALL'])) && !$deny($priv));
        $miss = [];
        if (!$any('SELECT')) $miss['tables'] = 'base tables, columns, indexes and views need SELECT on *.* or on the schema';
        if (!$any('TRIGGER')) $miss['triggers'] = 'triggers need TRIGGER on *.* or on the schema';
        if (!$any('EVENT')) $miss['events'] = 'events need EVENT on *.* or on the schema';
        $rout = $all('SELECT') || $any('CREATE ROUTINE') || $any('ALTER ROUTINE') || $any('EXECUTE');
        if (!$rout && $this->vend === 'mysql' && $this->srvn >= self::ROUTVER) $rout = $all('SHOW_ROUTINE');
        if (!$rout) $miss['routines'] = 'routines need SELECT on *.*, SHOW_ROUTINE on *.*, or CREATE ROUTINE, ALTER ROUTINE or EXECUTE in scope';
        return $miss;
    }

    # Exports the selected scope into staging, verifies it, publishes it, and only then applies retention and cleanup
    private function addBackupRun(): array {
        $pdo = $this->db->sqlconnid;
        if (!$pdo instanceof PDO) throw new RuntimeException('Backup has no database connection');
        if ($pdo->inTransaction()) throw new RuntimeException('Backup refuses to run inside a caller-owned transaction');
        $buff = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $save = $this->getSessionState();
        $open = false;
        try {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $this->setExportState();
            $this->getSqlRun('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->getSqlRun('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            $open = true;
            $tabs = $this->getScopeTables();
            $this->checkForeignKeys($tabs);
            $this->checkScopeObjects($tabs);
            $meta = $this->getScopeMeta($tabs);
            $mark = $this->getTablePrint($meta);
            $dump = $this->addDumpFile($meta);
            $drift = $mark !== $this->getTablePrint($this->getScopeMeta($tabs));
            if ($drift) throw new RuntimeException('Backup aborted: the schema of the selected scope changed during the export');
            $this->getSqlRun('COMMIT');
            $open = false;
        } finally {
            if ($open) $this->db->getSqlQuery('ROLLBACK');
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, is_bool($buff) ? $buff : true);
            $this->setSessionState($save);
        }
        return $this->addArtifact($dump);
    }

    # Reads the session variables the export overwrites, so the shared connection is handed back exactly as it was found
    private function getSessionState(): array {
        $sql = 'SELECT @@session.sql_mode AS mode, @@session.time_zone AS zone, @@session.character_set_client AS cset,'
            .' @@session.character_set_results AS rset, @@session.collation_connection AS coll';
        $stmt = $this->getSqlRun($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!is_array($row)) throw new RuntimeException('Backup could not read the session state');
        return $row;
    }

    # Restores the saved session state; this runs in a finally block, so it must never throw over the error that brought it here
    private function setSessionState(array $save): void {
        $sql = 'SET SESSION sql_mode = '.$this->db->getSqlValue($save['mode'] ?? '')
            .', SESSION time_zone = '.$this->db->getSqlValue($save['zone'] ?? 'SYSTEM')
            .', SESSION character_set_client = '.$this->db->getSqlValue($save['cset'] ?? 'utf8mb4')
            .', SESSION character_set_results = '.$this->db->getSqlValue($save['rset'] ?? 'utf8mb4')
            .', SESSION collation_connection = '.$this->db->getSqlValue($save['coll'] ?? 'utf8mb4_general_ci');
        $this->db->getSqlQuery($sql);
    }

    # Puts the session into the deterministic export state: UTC, a fixed SQL mode and a single connection charset
    private function setExportState(): void {
        $this->getSqlRun('SET SESSION sql_mode = \'NO_AUTO_VALUE_ON_ZERO\'');
        $this->getSqlRun('SET SESSION time_zone = \'+00:00\'');
        $this->getSqlRun('SET NAMES utf8mb4');
    }

    # Collects the base tables of the selected scope with their engines; a non-InnoDB engine outside schemaonly fails, because the consistent snapshot covers InnoDB only
    private function getScopeTables(): array {
        $sql = 'SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES'
            .' WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME';
        $stmt = $this->getSqlRun($sql, ['db' => $this->getDbName()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $out = [];
        foreach ($rows as $row) {
            $name = (string)$row['name'];
            if (!$this->checkTableScope($name)) continue;
            $eng = strtoupper((string)($row['engine'] ?? ''));
            $only = in_array($eng, $this->only, true);
            $wrong = 'Backup cannot snapshot table '.$name.' on engine '.$eng.' consistently; list the engine in schemaonly to export its structure only';
            if (!$only && $eng !== 'INNODB') throw new RuntimeException($wrong);
            $out[$name] = ['engine' => $eng, 'skip' => $only];
        }
        if (!$out) throw new RuntimeException('Backup found no table matching the configured scope');
        return $out;
    }

    # Fails when a foreign key of the selected scope points outside it, because such an artifact cannot be restored into a working database
    private function checkForeignKeys(array $tabs): void {
        $sql = 'SELECT TABLE_NAME AS tabl, REFERENCED_TABLE_NAME AS ref, REFERENCED_TABLE_SCHEMA AS refdb FROM information_schema.KEY_COLUMN_USAGE'
            .' WHERE TABLE_SCHEMA = :db AND REFERENCED_TABLE_NAME IS NOT NULL';
        $stmt = $this->getSqlRun($sql, ['db' => $this->getDbName()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        foreach ($rows as $row) {
            $name = (string)$row['tabl'];
            $ref = (string)$row['ref'];
            if (!isset($tabs[$name])) continue;
            if (isset($tabs[$ref]) && (string)$row['refdb'] === $this->getDbName()) continue;
            throw new RuntimeException('Backup scope is not self-contained: table '.$name.' references '.$ref.' outside the selected scope');
        }
    }

    # Classifies the object classes this class does not serialize and applies D1 to them
    # Views are counted through information_schema.TABLES, not information_schema.VIEWS
    # The latter additionally needs SHOW VIEW on MySQL, and without it an empty list would read as proof that no view exists
    private function checkScopeObjects(array $tabs): void {
        $objs = [];
        foreach ($this->getCatalogRows('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = \'VIEW\' ORDER BY TABLE_NAME') as $row) {
            if ($this->checkTableScope((string)$row['name'])) $objs['views'][] = (string)$row['name'];
        }
        $trig = 'SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS tabl FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = :db ORDER BY TRIGGER_NAME';
        foreach ($this->getCatalogRows($trig) as $row) {
            if (isset($tabs[(string)$row['tabl']])) $objs['triggers'][] = (string)$row['name'];
        }
        foreach ($this->getCatalogRows('SELECT EVENT_NAME AS name FROM information_schema.EVENTS WHERE EVENT_SCHEMA = :db ORDER BY EVENT_NAME') as $row) {
            $objs['events'][] = (string)$row['name'];
        }
        foreach ($this->getCatalogRows('SELECT ROUTINE_NAME AS name FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :db ORDER BY ROUTINE_NAME') as $row) {
            $objs['routines'][] = (string)$row['name'];
        }
        $this->checkScopeLimit($objs);
    }

    # Reads one catalog query of the current schema into rows; the schema is always bound, and a caller may bind more
    private function getCatalogRows(string $sql, array $pars = []): array {
        $stmt = $this->getSqlRun($sql, $pars + ['db' => $this->getDbName()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return is_array($rows) ? $rows : [];
    }

    # Applies D1: an unsupported object in the selected scope fails the run unless the operator accepted an incomplete artifact, and that acceptance is recorded in the result
    private function checkScopeLimit(array $objs): void {
        $objs = array_filter($objs);
        if (!$objs) return;
        $mesg = 'Backup scope contains objects this class does not serialize: '.$this->getObjectText($objs);
        if ($this->sett['allow_incomplete'] !== '1') throw new RuntimeException($mesg.'; set allow_incomplete to 1 to accept an incomplete artifact');
        $this->unsup = $objs;
        $this->full = false;
    }

    # Renders the unsupported object map as one readable list for a message
    private function getObjectText(array $objs): string {
        $out = [];
        foreach ($objs as $kind => $list) $out[] = $kind.' ('.implode(', ', $list).')';
        return implode(', ', $out);
    }

    # Collects the definition, the columns and the primary key of every selected table, and records the tables that have no primary key
    private function getScopeMeta(array $tabs): array {
        $out = [];
        foreach ($tabs as $name => $info) {
            $stmt = $this->getSqlRun('SHOW CREATE TABLE '.$this->getSqlName($name));
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $stmt->closeCursor();
            if (!is_array($row) || !isset($row[1])) throw new RuntimeException('Backup could not read the definition of table '.$name);
            $pkey = $this->getTableKeys($name);
            if (!$pkey && !in_array($name, $this->unord, true)) $this->unord[] = $name;
            $out[$name] = ['create' => (string)$row[1], 'skip' => $info['skip'], 'cols' => $this->getTableCols($name), 'pkey' => $pkey];
        }
        return $out;
    }

    # Reads the column metadata that drives serialization: the storage class decides how a value is written, and a generated column is never inserted
    private function getTableCols(string $name): array {
        $sql = 'SELECT COLUMN_NAME AS name, DATA_TYPE AS type, EXTRA AS extr FROM information_schema.COLUMNS'
            .' WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tab ORDER BY ORDINAL_POSITION';
        $rows = $this->getCatalogRows($sql, ['tab' => $name]);
        $out = [];
        foreach ($rows as $row) {
            $type = strtolower((string)$row['type']);
            $extr = strtoupper((string)($row['extr'] ?? ''));
            $out[] = ['name' => (string)$row['name'], 'type' => $type, 'extr' => $extr, 'num' => in_array($type, self::NUMTYPE, true),
                'bin' => in_array($type, self::BINTYPE, true), 'gen' => str_contains($extr, 'GENERATED')];
        }
        if (!$out) throw new RuntimeException('Backup could not read the columns of table '.$name);
        return $out;
    }

    # Reads the primary key columns of one table in key order; an empty result means the table is exported unordered (D3)
    private function getTableKeys(string $name): array {
        $sql = 'SELECT COLUMN_NAME AS name FROM information_schema.STATISTICS'
            .' WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tab AND INDEX_NAME = \'PRIMARY\' ORDER BY SEQ_IN_INDEX';
        $rows = $this->getCatalogRows($sql, ['tab' => $name]);
        $out = [];
        foreach ($rows as $row) $out[] = (string)$row['name'];
        return $out;
    }

    # Fingerprints the canonical definition of the selected scope; volatile auto-increment counters are excluded so that concurrent writes do not look like schema drift
    private function getTablePrint(array $meta): string {
        $out = [];
        foreach ($meta as $name => $one) {
            $cols = [];
            foreach ($one['cols'] as $col) $cols[] = $col['name'].':'.$col['type'].':'.$col['extr'];
            $out[] = $name.'|'.preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $one['create']).'|'.implode(',', $cols).'|'.implode(',', $one['pkey']);
        }
        return hash('sha256', implode("\n", $out));
    }

    # Streams the whole dump into staging and persists it durably; every write is checked, because a short write would produce a truncated artifact that still looks complete
    private function addDumpFile(array $meta): string {
        $path = $this->stage.'/dump.sql';
        $fh = fopen($path, 'xb');
        if ($fh === false) throw new RuntimeException('Backup could not create the staging dump file');
        chmod($path, 0600);
        try {
            $this->addStreamData($fh, $this->getDumpProlog());
            foreach ($meta as $name => $one) {
                $this->rnum += $this->addTableDump($fh, $name, $one);
                $this->tnum++;
            }
            $this->addStreamData($fh, $this->getDumpEpilog());
            if (!fflush($fh) || !fsync($fh)) throw new RuntimeException('Backup could not durably persist the staging dump file');
        } catch (Throwable $error) {
            fclose($fh);
            throw $error;
        }
        if (!fclose($fh)) throw new RuntimeException('Backup could not close the staging dump file');
        return $path;
    }

    # Writes the dump prologue, which saves the session state of the restoring client and switches it into the state the dump was produced in
    private function getDumpProlog(): string {
        return '# SLAED database backup'."\n"
            .'# Database: '.$this->getDbName()."\n"
            .'# Server: '.$this->vers."\n"
            .'# Created: '.gmdate('Y-m-d H:i:s', $this->tick).' UTC'."\n\n"
            .'SET @SLAED_FKEY = @@SESSION.foreign_key_checks;'."\n"
            .'SET @SLAED_UNIQ = @@SESSION.unique_checks;'."\n"
            .'SET @SLAED_MODE = @@SESSION.sql_mode;'."\n"
            .'SET @SLAED_ZONE = @@SESSION.time_zone;'."\n"
            .'SET @SLAED_CSET = @@SESSION.character_set_client;'."\n"
            .'SET @SLAED_RSET = @@SESSION.character_set_results;'."\n"
            .'SET @SLAED_COLL = @@SESSION.collation_connection;'."\n"
            .'SET SESSION foreign_key_checks = 0;'."\n"
            .'SET SESSION unique_checks = 0;'."\n"
            .'SET SESSION sql_mode = \'NO_AUTO_VALUE_ON_ZERO\';'."\n"
            .'SET SESSION time_zone = \'+00:00\';'."\n"
            .'SET NAMES utf8mb4;'."\n\n";
    }

    # Writes the dump epilogue, which hands the restoring session back exactly as the dump found it
    private function getDumpEpilog(): string {
        return 'SET SESSION character_set_client = @SLAED_CSET;'."\n"
            .'SET SESSION character_set_results = @SLAED_RSET;'."\n"
            .'SET SESSION collation_connection = @SLAED_COLL;'."\n"
            .'SET SESSION time_zone = @SLAED_ZONE;'."\n"
            .'SET SESSION sql_mode = @SLAED_MODE;'."\n"
            .'SET SESSION unique_checks = @SLAED_UNIQ;'."\n"
            .'SET SESSION foreign_key_checks = @SLAED_FKEY;'."\n";
    }

    # Writes one table: the definition always, the rows unless the engine was declared schemaonly, read forward-only and ordered by the full primary key where one exists
    private function addTableDump($fh, string $name, array $meta): int {
        $this->addStreamData($fh, 'DROP TABLE IF EXISTS '.$this->getSqlName($name).';'."\n".$meta['create'].';'."\n\n");
        if ($meta['skip']) return 0;
        $cols = $this->filterDumpCols($meta['cols']);
        if (!$cols) return 0;
        $list = implode(',', array_map(fn(array $col): string => $this->getSqlName($col['name']), $cols));
        $head = 'INSERT INTO '.$this->getSqlName($name).' ('.$list.') VALUES'."\n";
        $sql = 'SELECT '.$list.' FROM '.$this->getSqlName($name);
        if ($meta['pkey']) $sql .= ' ORDER BY '.implode(',', array_map(fn(string $col): string => $this->getSqlName($col), $meta['pkey']));
        $stmt = $this->getSqlRun($sql);
        $text = '';
        $rows = 0;
        $open = false;
        try {
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $vals = [];
                foreach ($cols as $pos => $col) $vals[] = $this->getRowLiteral($row[$pos] ?? null, $col);
                $text .= ($open ? ','."\n" : $head).'('.implode(',', $vals).')';
                $open = true;
                $rows++;
                if (strlen($text) < self::CHUNK) continue;
                $this->addStreamData($fh, $text.';'."\n");
                $text = '';
                $open = false;
            }
        } finally {
            $stmt->closeCursor();
        }
        if ($open) $this->addStreamData($fh, $text.';'."\n");
        if ($rows) $this->addStreamData($fh, "\n");
        return $rows;
    }

    # Selects the columns an INSERT may carry; a generated column is computed by the server on restore and must never be written
    private function filterDumpCols(array $cols): array {
        return array_values(array_filter($cols, static fn(array $col): bool => empty($col['gen'])));
    }

    # Serializes one value: binary and bit values become hex, everything else is quoted by the driver
    # A numeric literal is emitted only when both the column metadata and the value prove it is numeric
    private function getRowLiteral(mixed $val, array $col): string {
        if ($val === null) return 'NULL';
        if ($col['bin']) return $val === '' ? '\'\'' : '0x'.bin2hex((string)$val);
        if ($col['num'] && is_numeric($val)) return (string)$val;
        return $this->db->getSqlValue($val);
    }

    # Compresses, verifies, publishes and then cleans up; from the successful link onwards the artifact is valid and no later failure may discard it (D9)
    private function addArtifact(string $dump): array {
        $hash = hash_file('sha256', $dump);
        if ($hash === false) throw new RuntimeException('Backup could not checksum the staging dump file');
        $cand = $dump;
        $ext = '';
        if ($this->algo !== 'none') {
            if (!addCompress($this->stage, $dump, 'dump.part', $this->algo, false, false)) throw new RuntimeException('Backup could not compress the dump with '.$this->algo);
            $cand = $this->stage.'/dump.part.'.$this->algo;
            $this->checkArchiveHash($cand, $hash);
            $this->addFileSync($cand);
            $ext = $this->algo;
        }
        $path = $this->addArtifactFile($cand, $ext);
        $gone = $this->deleteOldFiles();
        $this->deleteStageRoot();
        return $this->getRunResult($path, $hash, $ext, $gone);
    }

    # Verifies the compressed candidate by decompressing it again, which also covers addCompress() not checking its own close result
    private function checkArchiveHash(string $cand, string $hash): void {
        $ctx = hash_init('sha256');
        if ($this->algo === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($cand) !== true) throw new RuntimeException('Backup could not reopen the compressed candidate');
            $fh = $zip->getStream('dump.sql');
            if ($fh === false) throw new RuntimeException('Backup could not read the dump entry from the compressed candidate');
            while (!feof($fh)) hash_update($ctx, (string)fread($fh, self::BUFFER));
            fclose($fh);
            $zip->close();
        } elseif ($this->algo === 'gz') {
            $fh = gzopen($cand, 'rb');
            if ($fh === false) throw new RuntimeException('Backup could not reopen the compressed candidate');
            while (!gzeof($fh)) hash_update($ctx, (string)gzread($fh, self::BUFFER));
            gzclose($fh);
        } else {
            $fh = bzopen($cand, 'r');
            if ($fh === false) throw new RuntimeException('Backup could not reopen the compressed candidate');
            while (true) {
                $part = bzread($fh, self::BUFFER);
                if ($part === false || $part === '') break;
                hash_update($ctx, $part);
            }
            bzclose($fh);
        }
        if (hash_final($ctx) !== $hash) throw new RuntimeException('Backup archive does not decompress to the dump it was made from');
    }

    # Persists the compressed candidate durably before it can become an artifact, because addCompress() owns and closes its own handle
    private function addFileSync(string $path): void {
        $fh = fopen($path, 'rb+');
        if ($fh === false) throw new RuntimeException('Backup could not reopen the compressed candidate for fsync');
        $done = fsync($fh);
        if (!fclose($fh) || !$done) throw new RuntimeException('Backup could not durably persist the compressed candidate');
    }

    # Publishes with link(), which fails rather than replacing when the name exists; a collision regenerates the suffix instead of overwriting an existing artifact (D6)
    # The mode is set on the candidate before linking, because a hard link shares the inode: setting it after would leave the artifact readable under its public name in between
    private function addArtifactFile(string $cand, string $ext): string {
        if (!chmod($cand, 0600)) throw new RuntimeException('Backup could not restrict the artifact before publishing it');
        for ($i = 0; $i < self::TRIES; $i++) {
            $path = $this->dir.'/'.$this->getArtifactName($ext);
            if (!link($cand, $path)) continue;
            if (!unlink($cand)) $this->addRunWarning('Backup could not remove the staged candidate: '.$cand);
            return $path;
        }
        throw new RuntimeException('Backup could not publish the artifact after '.self::TRIES.' attempts');
    }

    # Builds one canonical artifact name; .sql is always present, including for zip, so a single suffix rule covers every format (D5)
    private function getArtifactName(string $ext): string {
        $name = $this->stem.'_'.date('Y-m-d_H-i-s', $this->tick).'_'.bin2hex(random_bytes(4)).'.sql';
        return $ext === '' ? $name : $name.'.'.$ext;
    }

    # Applies retention over the backup root; both naming schemes age together and anything that does not match is invisible
    private function deleteOldFiles(): int {
        $keep = (int)$this->sett['keep'];
        if ($keep <= 0) return 0;
        $list = [];
        foreach (scandir($this->dir) ?: [] as $file) {
            $path = $this->dir.'/'.$file;
            if ($file === '.' || $file === '..' || !is_file($path)) continue;
            $list[$file] = (int)filemtime($path);
        }
        $gone = 0;
        foreach ($this->filterRetentionList($list, $keep) as $file) {
            if (unlink($this->dir.'/'.$file)) {
                $gone++;
                continue;
            }
            $this->addRunWarning('Backup could not delete the expired artifact: '.$file);
        }
        return $gone;
    }

    # Returns the artifact name stem of one database, which is the same sanitized form the legacy routine produced
    public static function getArtifactStem(string $dbname): string {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $dbname);
    }

    # Recognizes one artifact of the given database and returns the timestamp its name carries, or an empty string when the file is not an artifact at all
    # Both naming schemes are answered here, and this is the only place that knows them, so retention and the monitor cannot drift apart
    public static function getArtifactMark(string $file, string $stem): string {
        $stem = preg_quote($stem, '#');
        $curr = '#^'.$stem.'_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_[0-9a-f]{8}\.sql(?:\.(?:zip|gz|bz2))?$#';
        $prev = '#^'.$stem.'_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.(?:sql|zip|gz|bz2)$#';
        if (preg_match($curr, $file, $mat) || preg_match($prev, $file, $mat)) return $mat[1];
        return '';
    }

    # Selects the artifacts that retention removes; ordering is by the timestamp parsed out of the name, which survives copying and restoring, with mtime only as a tie-break
    private function filterRetentionList(array $files, int $keep): array {
        if ($keep <= 0) return [];
        $list = [];
        foreach ($files as $file => $mtime) {
            $name = (string)$file;
            $mark = self::getArtifactMark($name, $this->stem);
            if ($mark === '') continue;
            $list[] = ['name' => $name, 'mark' => $mark, 'time' => (int)$mtime];
        }
        usort($list, static function (array $one, array $two): int {
            $rank = strcmp($two['mark'], $one['mark']);
            return $rank !== 0 ? $rank : ($two['time'] <=> $one['time']);
        });
        return array_column(array_slice($list, $keep), 'name');
    }

    # Removes the staging directory and its contents; a failure here never invalidates an artifact that is already published
    private function deleteStageRoot(): void {
        if ($this->stage === '' || !is_dir($this->stage)) return;
        foreach (scandir($this->stage) ?: [] as $file) {
            $path = $this->stage.'/'.$file;
            if ($file === '.' || $file === '..') continue;
            if (!is_file($path) || unlink($path)) continue;
            $this->addRunWarning('Backup could not remove the staged file: '.$path);
        }
        if (!rmdir($this->stage)) $this->addRunWarning('Backup could not remove the staging directory: '.$this->stage);
    }

    # Records a post-publication failure without touching the run status, because a verified backup is never discarded over a cleanup step
    private function addRunWarning(string $text): void {
        $this->warns[] = $text;
    }

    # Builds the scheduler result; the artifact is identified exactly here, so no consumer ever has to derive its name
    private function getRunResult(string $path, string $hash, string $ext, int $gone): array {
        $size = filesize($path);
        $full = hash_file('sha256', $path);
        $mesg = 'Database backup completed';
        if (!$this->full) $mesg .= '; incomplete by operator choice, skipped '.$this->getObjectText($this->unsup);
        if ($this->warns) $mesg .= '; '.implode('; ', $this->warns);
        return [
            'status' => 'success',
            'message' => $mesg,
            'extra' => [
                'backup_file' => basename($path),
                'backup_path' => $path,
                'backup_size' => $size === false ? 0 : $size,
                'backup_format' => $ext === '' ? 'sql' : $ext,
                'backup_hash' => $full === false ? '' : $full,
                'dump_hash' => $hash,
                'table_count' => $this->tnum,
                'row_count' => $this->rnum,
                'complete' => $this->full,
                'unsupported' => $this->unsup,
                'unordered' => $this->unord,
                'warnings' => $this->warns,
                'removed' => $gone,
            ],
        ];
    }

    # Runs one statement and fails the run when it does not execute, so no unchecked database call can reach the artifact
    private function getSqlRun(string $sql, array $pars = []): PDOStatement {
        $stmt = $this->db->getSqlQuery($sql, $pars);
        if (!$stmt instanceof PDOStatement) throw new RuntimeException('Backup database call failed: '.substr($sql, 0, 120));
        return $stmt;
    }

    # Writes one chunk and fails on a short write, because a partial write would silently truncate the dump
    private function addStreamData($fh, string $text): void {
        if ($text === '') return;
        $done = fwrite($fh, $text);
        if ($done !== strlen($text)) throw new RuntimeException('Backup could not write the staging dump file completely');
    }

    # Quotes one identifier for the dump and for every statement this class builds
    private function getSqlName(string $name): string {
        return '`'.str_replace('`', '``', $name).'`';
    }

    # Returns the name of the database being exported
    private function getDbName(): string {
        return (string)($this->conf['name'] ?? '');
    }
}
