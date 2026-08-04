<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the Backup integration tests
# boots the real core like index.php, one scenario per process, and drives the class against a disposable database it creates and drops again
# Nothing touches the site database or storage/backup - every scenario works in its own schema and in a scratch backup root, and the report says whether both were left clean
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/backup.php';

# The disposable schema, the scratch backup root and the connection every scenario works through
$GLOBALS['pname'] = 'slaed_bkp_'.bin2hex(random_bytes(4));
$GLOBALS['proot'] = $probework.'/root';
$GLOBALS['pdb'] = null;

# Open one raw connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    $dsn = 'mysql:host='.$conf['db']['host'].';charset=utf8mb4';
    return new PDO($dsn, $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the disposable schema with the fixtures every scenario shares and connect the project database facade to it
function addProbeSchema(): Database {
    global $conf;
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4');
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    $root->exec('CREATE TABLE `p_keyed` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(190) NOT NULL, `num` INT NULL,'
        .' `cost` DECIMAL(10,2) NOT NULL DEFAULT 0, `data` BLOB NULL, `note` TEXT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $root->exec('CREATE TABLE `p_nokey` (`tag` VARCHAR(50) NOT NULL, `num` INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $root->exec('CREATE TABLE `p_gen` (`id` INT NOT NULL, `price` INT NOT NULL, `total` INT AS (`price` * 2) STORED, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $root->exec('CREATE TABLE `p_multi` (`one` INT NOT NULL, `two` INT NOT NULL, `txt` VARCHAR(20) NOT NULL, PRIMARY KEY (`one`, `two`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $stmt = $root->prepare('INSERT INTO `p_keyed` (`title`, `num`, `cost`, `data`, `note`) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['O\'Neil "quoted"', 7, '19.90', "\x00\x01\xff\xfe", 'plain']);
    $stmt->execute(['Ünicode Ру Текст', null, '-3.50', '', null]);
    $stmt->execute(['back\\slash and ; semicolon', -42, '0.00', null, "line\nbreak\ttab"]);
    $root->exec('INSERT INTO `p_nokey` (`tag`, `num`) VALUES (\'dup\', 1), (\'dup\', 1), (\'other\', 2)');
    $root->exec('INSERT INTO `p_gen` (`id`, `price`) VALUES (1, 10), (2, 25)');
    $root->exec('INSERT INTO `p_multi` (`one`, `two`, `txt`) VALUES (2, 1, \'b\'), (1, 2, \'a\'), (1, 1, \'c\')');
    addProbeRoot();
    $GLOBALS['pdb'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $GLOBALS['pname']);
    return $GLOBALS['pdb'];
}

# Start every scenario from an empty scratch backup root, so a previous run cannot be counted as this run's output
function addProbeRoot(): void {
    if (!is_dir($GLOBALS['proot'])) {
        mkdir($GLOBALS['proot'], 0777, true);
        return;
    }
    foreach (scandir($GLOBALS['proot']) ?: [] as $file) {
        $path = $GLOBALS['proot'].'/'.$file;
        if ($file === '.' || $file === '..') continue;
        if (is_file($path)) unlink($path);
        if (is_dir($path)) deleteDir($path);
    }
}

# Drop the disposable schema and report whether the scratch backup root was left without staging leftovers
function deleteProbeSchema(): bool {
    $left = getProbeStage();
    try {
        getProbeRoot()->exec('DROP DATABASE IF EXISTS `'.$GLOBALS['pname'].'`');
    } catch (Throwable) {
        return false;
    }
    return $left === [];
}

# Build one exporter over the disposable schema with the given settings
function getProbeBackup(array $sett = [], ?array $caps = null): Backup {
    $sett += ['include' => '*', 'exclude' => '', 'schemaonly' => '', 'compress' => 'none', 'keep' => '0', 'allow_incomplete' => '0'];
    return new Backup($GLOBALS['pdb'], ['name' => $GLOBALS['pname']], $sett, $GLOBALS['proot'], $caps);
}

# List the staging directories left in the scratch backup root, which must be empty after every run
function getProbeStage(): array {
    $out = [];
    foreach (scandir($GLOBALS['proot']) ?: [] as $file) {
        if (str_starts_with($file, '.stage-') || str_starts_with($file, '.probe-')) $out[] = $file;
    }
    return $out;
}

# List the published artifacts in the scratch backup root
function getProbeFiles(): array {
    $out = [];
    foreach (scandir($GLOBALS['proot']) ?: [] as $file) {
        if ($file !== '.' && $file !== '..' && is_file($GLOBALS['proot'].'/'.$file)) $out[] = $file;
    }
    sort($out);
    return $out;
}

# Read the session variables the export overwrites, so a scenario can prove the connection was handed back unchanged
function getProbeSession(): array {
    $pdo = $GLOBALS['pdb']->sqlconnid;
    $sql = 'SELECT @@session.sql_mode AS mode, @@session.time_zone AS zone, @@session.character_set_client AS cset,'
        .' @@session.character_set_results AS rset, @@session.collation_connection AS coll';
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    $row['buffered'] = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    try {
        $row['intrx'] = (int)$pdo->query('SELECT @@in_transaction')->fetchColumn();
    } catch (Throwable) {
        $row['intrx'] = 0;
    }
    return $row;
}

# Call one private method of the exporter, which is how the scenarios reach the pieces that need real filesystem or server semantics
function getProbeCall(Backup $back, string $name, array $args = []): mixed {
    return (new ReflectionMethod($back, $name))->invokeArgs($back, $args);
}

# Read the published artifact back as plain SQL, whatever format it was published in
function getProbeDump(string $path, string $ext): string {
    if ($ext === 'sql') return (string)file_get_contents($path);
    if ($ext === 'gz') return (string)gzdecode((string)file_get_contents($path));
    if ($ext === 'bz2') return (string)bzdecompress((string)file_get_contents($path));
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $text = (string)$zip->getFromName('dump.sql');
    $zip->close();
    return $text;
}

# A full uncompressed run: what the result says, what the artifact contains, and whether the shared connection came back exactly as it was found
function getProbeExport(): array {
    $back = getProbeBackup(['compress' => 'none']);
    $head = getProbeSession();
    $res = $back->addDatabaseBackup();
    $tail = getProbeSession();
    $out = ['status' => $res['status'], 'message' => $res['message'], 'extra' => $res['extra'] ?? [], 'stage' => getProbeStage(), 'files' => getProbeFiles()];
    $path = $res['extra']['backup_path'] ?? '';
    $out['exists'] = is_file($path);
    $out['same'] = $out['exists'] && hash_file('sha256', $path) === ($res['extra']['backup_hash'] ?? '');
    $out['dumphash'] = $out['exists'] && hash_file('sha256', $path) === ($res['extra']['dump_hash'] ?? '');
    $out['perms'] = $out['exists'] ? substr(sprintf('%o', fileperms($path)), -3) : '';
    $out['session'] = $head === $tail;
    $out['before'] = $head;
    $out['after'] = $tail;
    $text = $out['exists'] ? (string)file_get_contents($path) : '';
    $out['sql'] = [
        'drop' => substr_count($text, 'DROP TABLE IF EXISTS'),
        'insert' => substr_count($text, 'INSERT INTO'),
        'gencol' => str_contains($text, '`total`') && preg_match('/INSERT INTO `p_gen` \(([^)]*)\)/', $text, $mat) ? $mat[1] : '',
        'keyorder' => preg_match('/INSERT INTO `p_multi`[^;]*;/s', $text, $mat) ? preg_replace('/\s+/', '', $mat[0]) : '',
        'binary' => str_contains($text, '0x0001fffe'),
        'emptybin' => str_contains($text, ',\'\','),
        'quoted' => preg_match('/O(\\\\\'|\'\')Neil/', $text) === 1,
        'semicolon' => str_contains($text, 'and ; semicolon'),
        'negative' => str_contains($text, '-42'),
        'null' => str_contains($text, 'NULL'),
        'utc' => str_contains($text, 'SET SESSION time_zone = \'+00:00\''),
        'prolog' => str_contains($text, 'SET SESSION foreign_key_checks = 0'),
        'epilog' => str_contains($text, 'SET SESSION foreign_key_checks = @SLAED_FKEY'),
        'modeset' => str_contains($text, 'SET SESSION sql_mode = \'NO_AUTO_VALUE_ON_ZERO\''),
    ];
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Every compressor, verified from outside the class as well: the artifact must decompress to exactly the SQL the result reports
function getProbeCompress(): array {
    $out = ['caps' => checkCompress(), 'runs' => []];
    foreach (['zip', 'gz', 'bz2'] as $algo) {
        if (empty($out['caps'][$algo])) continue;
        $back = getProbeBackup(['compress' => $algo]);
        $res = $back->addDatabaseBackup();
        $path = $res['extra']['backup_path'] ?? '';
        $text = is_file($path) ? getProbeDump($path, $algo) : '';
        $out['runs'][$algo] = [
            'status' => $res['status'],
            'message' => $res['message'],
            'format' => $res['extra']['backup_format'] ?? '',
            'name' => basename($path),
            'plain' => $text !== '' && hash('sha256', $text) === ($res['extra']['dump_hash'] ?? ''),
            'smaller' => is_file($path) && filesize($path) > 0,
            'stage' => getProbeStage(),
        ];
    }
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Publication: two runs never collide, an existing name is never replaced, and a publication that cannot link fails the whole run instead of producing something
# The run closes on a candidate that exists but cannot be linked anywhere, where the publisher has to give up after its bounded number of attempts
function getProbePublish(): array {
    $out = [];
    $one = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    $first = $one['extra']['backup_path'] ?? '';
    $mark = is_file($first) ? hash_file('sha256', $first) : '';
    $two = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    $second = $two['extra']['backup_path'] ?? '';
    $out['names'] = [basename($first), basename($second)];
    $out['distinct'] = $first !== '' && $first !== $second;
    $out['kept'] = is_file($first) && hash_file('sha256', $first) === $mark;
    $out['files'] = count(getProbeFiles());
    $src = $GLOBALS['proot'].'/link_src.tmp';
    $dst = $GLOBALS['proot'].'/link_dst.tmp';
    file_put_contents($src, 'source');
    file_put_contents($dst, 'destination');
    $out['linkrefused'] = !link($src, $dst);
    $out['targetkept'] = file_get_contents($dst) === 'destination';
    unlink($src);
    unlink($dst);
    $back = getProbeBackup(['compress' => 'none']);
    getProbeCall($back, 'checkSettingsInput', [['compress' => 'none']]);
    $cand =$GLOBALS['proot'].'/candidate.sql';
    file_put_contents($cand, 'dump');
    (new ReflectionProperty($back, 'dir'))->setValue($back, $GLOBALS['proot'].'/no-such-directory');
    try {
        getProbeCall($back, 'addArtifactFile', [$cand, '']);
        $out['retry'] = 'no exception';
    } catch (Throwable $error) {
        $out['retry'] = $error->getMessage();
    }
    $out['candkept'] = is_file($cand);
    $out['candmode'] = substr(sprintf('%o', fileperms($cand)), -3);
    unlink($cand);
    try {
        getProbeCall($back, 'addArtifactFile', [$GLOBALS['proot'].'/does_not_exist.sql', '']);
        $out['missing'] = 'no exception';
    } catch (Throwable $error) {
        $out['missing'] = $error->getMessage();
    }
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# The fingerprint must see a schema change and must not see concurrent writes, and a real concurrent ALTER during an export must fail the run
function getProbeDrift(): array {
    $back = getProbeBackup(['compress' => 'none']);
    getProbeCall($back, 'checkSettingsInput', [['compress' => 'none']]);
    getProbeCall($back, 'getServerInfo');
    $tabs = getProbeCall($back, 'getScopeTables');
    $mark = getProbeCall($back, 'getTablePrint', [getProbeCall($back, 'getScopeMeta', [$tabs])]);
    $GLOBALS['pdb']->getSqlQuery('INSERT INTO `p_keyed` (`title`, `cost`) VALUES (\'written during export\', 1)');
    $GLOBALS['pdb']->getSqlQuery('DELETE FROM `p_nokey` WHERE `tag` = \'other\'');
    $write = getProbeCall($back, 'getTablePrint', [getProbeCall($back, 'getScopeMeta', [$tabs])]);
    $GLOBALS['pdb']->getSqlQuery('ALTER TABLE `p_keyed` ADD COLUMN `extra` INT NULL');
    $alter = getProbeCall($back, 'getTablePrint', [getProbeCall($back, 'getScopeMeta', [$tabs])]);
    $out = ['dml' => $mark === $write, 'ddl' => $mark !== $alter];
    $out['race'] = getProbeRace();
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Run one export against a table large enough to take time while a second process alters it, and report whether the race actually happened
function getProbeRace(): array {
    $pdo = $GLOBALS['pdb']->sqlconnid;
    $pdo->exec('CREATE TABLE `p_bulk` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `txt` VARCHAR(190) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('INSERT INTO `p_bulk` (`txt`) VALUES (\''.str_repeat('x', 180).'\')');
    for ($i = 0; $i < 16; $i++) $pdo->exec('INSERT INTO `p_bulk` (`txt`) SELECT `txt` FROM `p_bulk`');
    $file = LOGS_DIR.'/alter.php';
    $code = '<?php usleep(250000); $p = new PDO('.var_export('mysql:host='.$GLOBALS['phost'].';dbname='.$GLOBALS['pname'], true).', '.var_export($GLOBALS['puser'],
        true).', '.var_export($GLOBALS['ppass'], true).'); $p->exec(\'ALTER TABLE `p_bulk` ADD COLUMN `late` INT NULL\');';
    file_put_contents($file, $code);
    $proc = proc_open(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
    $res = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    if (is_resource($proc)) {
        foreach ($pipe as $one) fclose($one);
        proc_close($proc);
    }
    unlink($file);
    $late = $pdo->query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'p_bulk\' AND COLUMN_NAME = \'late\'')->fetchColumn();
    $path = $res['extra']['backup_path'] ?? '';
    $text = is_file($path) ? (string)file_get_contents($path) : '';
    $mark = preg_match('/CREATE TABLE `p_bulk`.*?;/s', $text, $mat) ? $mat[0] : '';
    return ['altered' => (int)$late === 1, 'status' => $res['status'], 'message' => $res['message'], 'stage' => getProbeStage(),
        'snapshot' => $mark !== '' && !str_contains($mark, '`late`'), 'rows' => $res['extra']['row_count'] ?? 0];
}

# A failure inside the export must roll back, restore the session and remove staging, and it must never publish anything
function getProbeFail(): array {
    $GLOBALS['pdb']->getSqlQuery('CREATE TABLE `p_memory` (`id` INT NOT NULL, PRIMARY KEY (`id`)) ENGINE=MEMORY');
    $head = getProbeSession();
    $res = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    $tail = getProbeSession();
    $out = ['status' => $res['status'], 'message' => $res['message'], 'stage' => getProbeStage(), 'files' => getProbeFiles(), 'session' => $head === $tail,
        'intrx' => $tail['intrx']];
    $keep = getProbeBackup(['compress' => 'none', 'schemaonly' => 'MEMORY'])->addDatabaseBackup();
    $path = $keep['extra']['backup_path'] ?? '';
    $text = is_file($path) ? (string)file_get_contents($path) : '';
    $out['schemaonly'] = ['status' => $keep['status'], 'created' => str_contains($text, 'CREATE TABLE `p_memory`'), 'inserted' => str_contains($text, 'INSERT INTO `p_memory`')];
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# The unsupported object classes against a live catalog: strict mode refuses the run, the opt-in records what was skipped and keeps going
function getProbeScope(): array {
    $pdo = $GLOBALS['pdb']->sqlconnid;
    $pdo->exec('CREATE PROCEDURE `p_helper`() BEGIN SELECT 1; END');
    $pdo->exec('CREATE VIEW `p_view` AS SELECT `id`, `title` FROM `p_keyed`');
    $pdo->exec('CREATE TRIGGER `p_trg` BEFORE INSERT ON `p_keyed` FOR EACH ROW SET NEW.`cost` = NEW.`cost`');
    $strict = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    $wrote = getProbeFiles();
    $open = getProbeBackup(['compress' => 'none', 'allow_incomplete' => '1'])->addDatabaseBackup();
    $path = $open['extra']['backup_path'] ?? '';
    $text = is_file($path) ? (string)file_get_contents($path) : '';
    $out = [
        'strict' => ['status' => $strict['status'], 'message' => $strict['message'], 'files' => $wrote],
        'open' => ['status' => $open['status'], 'complete' => $open['extra']['complete'] ?? null, 'unsupported' => $open['extra']['unsupported'] ?? [],
            'message' => $open['message']],
        'viewout' => str_contains($text, '`p_view`'),
        'unordered' => $open['extra']['unordered'] ?? [],
        'stage' => getProbeStage(),
    ];
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# The privilege contract against a live server: an account that cannot see a class must fail preflight instead of reading an empty catalog as proof of absence
function getProbeGrant(): array {
    $root = getProbeRoot();
    $pdo = $GLOBALS['pdb']->sqlconnid;
    $pdo->exec('CREATE PROCEDURE `p_mine`() BEGIN SELECT 1; END');
    $pdo->exec('CREATE TRIGGER `p_trg` BEFORE INSERT ON `p_keyed` FOR EACH ROW SET NEW.`cost` = NEW.`cost`');
    $user = 'p_'.bin2hex(random_bytes(3));
    $role = 'r_'.bin2hex(random_bytes(3));
    $kid = 'k_'.bin2hex(random_bytes(3));
    $out = ['vendor' => getProbeVendor()];
    try {
        $root->exec('CREATE USER \''.$user.'\'@\'%\' IDENTIFIED BY \'probe\'');
        $root->exec('GRANT SELECT ON `'.$GLOBALS['pname'].'`.* TO \''.$user.'\'@\'%\'');
        $out['select'] = getProbeGrantRun($user);
        $root->exec('CREATE ROLE `'.$kid.'`');
        $root->exec('GRANT TRIGGER, EVENT ON *.* TO `'.$kid.'`');
        $root->exec('CREATE ROLE `'.$role.'`');
        $root->exec('GRANT `'.$kid.'` TO `'.$role.'`');
        $root->exec('GRANT `'.$role.'` TO \''.$user.'\'@\'%\'');
        $out['inactive'] = getProbeGrantRun($user);
        $out['active'] = getProbeGrantRun($user, $role);
        $root->exec('GRANT SELECT, TRIGGER, EVENT ON *.* TO \''.$user.'\'@\'%\'');
        $out['direct'] = getProbeGrantRun($user);
    } catch (Throwable $error) {
        $out['error'] = $error->getMessage();
    }
    try {
        $root->exec('DROP USER IF EXISTS \''.$user.'\'@\'%\'');
        $root->exec('DROP ROLE IF EXISTS `'.$role.'`');
        $root->exec('DROP ROLE IF EXISTS `'.$kid.'`');
    } catch (Throwable) {
    }
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Run preflight as the given account, optionally with one role activated, and report what the privilege contract decided
# The connection is proven with a raw handle first, because the project facade turns a failed connect into a rendered error page and would end this process
function getProbeGrantRun(string $user, string $role = ''): array {
    global $conf;
    try {
        new PDO('mysql:host='.$conf['db']['host'].';dbname='.$GLOBALS['pname'].';charset=utf8mb4', $user, 'probe', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $error) {
        return ['status' => 'no connection', 'message' => $error->getMessage()];
    }
    $db = new Database($conf['db']['host'], $user, 'probe', $GLOBALS['pname']);
    if (!$db->sqlconnid instanceof PDO) return ['status' => 'no connection'];
    if ($role !== '') $db->sqlconnid->exec('SET ROLE `'.$role.'`');
    $back = new Backup($db, ['name' => $GLOBALS['pname']], ['include' => '*', 'compress' => 'none'], $GLOBALS['proot'], ['zip' => false, 'gz' => false, 'bz2' => false]);
    $res = $back->addDatabaseBackup();
    $seen = [];
    try {
        $seen['routines'] = (int)$db->sqlconnid->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetchColumn();
        $seen['triggers'] = (int)$db->sqlconnid->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetchColumn();
    } catch (Throwable) {
        $seen = [];
    }
    $db->getSqlClose();
    return ['status' => $res['status'], 'message' => $res['message'], 'catalog' => $seen, 'stage' => getProbeStage()];
}

# Report the vendor the local server is, because one privilege branch cannot be exercised on the other vendor
function getProbeVendor(): string {
    $vers = (string)$GLOBALS['pdb']->sqlconnid->query('SELECT VERSION()')->fetchColumn();
    return (stripos($vers, 'mariadb') !== false ? 'mariadb ' : 'mysql ').$vers;
}

# Apply the produced artifact to an empty database and compare what came back
# ordered checksums for keyed tables and a hash multiset for the unordered one, so duplicate rows are verified rather than collapsed
function getProbeRestore(): array {
    global $conf;
    $res = getProbeBackup(['compress' => 'none'])->addDatabaseBackup();
    $path = $res['extra']['backup_path'] ?? '';
    if (!is_file($path)) return ['status' => $res['status'], 'message' => $res['message'], 'restored' => false, 'clean' => deleteProbeSchema()];
    $dest = $GLOBALS['pname'].'_r';
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$dest.'` DEFAULT CHARACTER SET utf8mb4');
    $out = ['status' => $res['status'], 'restored' => false, 'error' => ''];
    try {
        $pdo = new PDO('mysql:host='.$conf['db']['host'].';dbname='.$dest.';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query((string)file_get_contents($path));
        while ($stmt->nextRowset()) {
            continue;
        }
        $out['restored'] = true;
        $out['source'] = getProbeTableState($GLOBALS['pdb']->sqlconnid);
        $out['target'] = getProbeTableState($pdo);
        $out['identical'] = $out['source'] === $out['target'];
        $out['mode'] = (string)$pdo->query('SELECT @@session.sql_mode')->fetchColumn();
    } catch (Throwable $error) {
        $out['error'] = $error->getMessage();
    }
    $root->exec('DROP DATABASE IF EXISTS `'.$dest.'`');
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Describe every base table of one schema by row count and canonical row hashes, which is what two databases have to agree on
function getProbeTableState(PDO $pdo): array {
    $out = [];
    $list = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME';
    foreach ($pdo->query($list)->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $rows = $pdo->query('SELECT * FROM `'.$name.'`')->fetchAll(PDO::FETCH_ASSOC);
        $mset = [];
        foreach ($rows as $row) {
            ksort($row);
            $mark = hash('sha256', json_encode(array_map(static fn(mixed $val): string => $val === null ? "\0null" : bin2hex((string)$val), $row)));
            $mset[$mark] = ($mset[$mark] ?? 0) + 1;
        }
        ksort($mset);
        $out[$name] = ['rows' => count($rows), 'mset' => $mset];
    }
    return $out;
}

# The release gate: take the newest artifact the site itself produced, restore it into an empty database and prove the result is the database it was made from
# The site database is only ever read here; everything written goes into the disposable schema, which is dropped again
function getProbeGate(): array {
    global $conf;
    $live = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name']);
    $made = (new Backup($live, $conf['db'], ['include' => '*', 'compress' => 'none'], $GLOBALS['proot']))->addDatabaseBackup();
    if (($made['status'] ?? '') !== 'success') return ['error' => 'The site backup failed: '.($made['message'] ?? '')];
    $best = ['file' => (string)$made['extra']['backup_file']];
    $text = (string)file_get_contents((string)$made['extra']['backup_path']);
    if ($text === '') return ['error' => 'The artifact '.$best['file'].' could not be read back'];
    $dest = 'slaed_gate_'.bin2hex(random_bytes(4));
    $seed = getProbeRoot();
    $seed->exec('CREATE DATABASE `'.$dest.'` DEFAULT CHARACTER SET utf8mb4');
    $out = ['artifact' => $best['file'], 'bytes' => strlen($text), 'restored' => false, 'error' => '', 'tally' => (int)$made['extra']['row_count']];
    try {
        $seen = new PDO('mysql:host='.$conf['db']['host'].';dbname='.$conf['db']['name'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $copy = new PDO('mysql:host='.$conf['db']['host'].';dbname='.$dest.';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $out['statements'] = addProbeRestore($copy, $text);
        $out['restored'] = true;
        $one = getProbeSchemaState($seen);
        $two = getProbeSchemaState($copy);
        $out['tables'] = ['source' => count($one), 'target' => count($two)];
        $out['names'] = array_keys($one) === array_keys($two);
        $out['rows'] = [];
        $out['ddl'] = [];
        foreach ($one as $name => $info) {
            if (($two[$name]['rows'] ?? -1) !== $info['rows']) $out['rows'][] = $name;
            if (($two[$name]['ddl'] ?? '') !== $info['ddl']) $out['ddl'][] = $name;
        }
        $out['data'] = getProbeDataDiff($seen, $copy, array_keys($one));
        $out['smoke'] = getProbeSmoke($copy);
    } catch (Throwable $error) {
        $out['error'] = $error->getMessage();
    }
    $seed->exec('DROP DATABASE IF EXISTS `'.$dest.'`');
    $out['clean'] = deleteProbeSchema();
    return $out;
}

# Apply one dump statement by statement, the way a client reading the file line by line does
# The whole file cannot be sent as one packet: a real dump exceeds max_allowed_packet long before any single statement does
function addProbeRestore(PDO $pdo, string $text): int {
    $num = 0;
    foreach (explode(';'."\n", $text) as $one) {
        $sql = trim(preg_replace('/^#[^\n]*$/m', '', $one));
        if ($sql === '') continue;
        $pdo->exec($sql);
        $num++;
    }
    return $num;
}

# Describe every base table of one schema by row count and normalized definition, with the volatile auto-increment counter removed
function getProbeSchemaState(PDO $pdo): array {
    $out = [];
    $list = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME';
    foreach ($pdo->query($list)->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $ddl = (string)($pdo->query('SHOW CREATE TABLE `'.$name.'`')->fetch(PDO::FETCH_NUM)[1] ?? '');
        $out[$name] = [
            'rows' => (int)$pdo->query('SELECT COUNT(*) FROM `'.$name.'`')->fetchColumn(),
            'ddl' => preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $ddl),
        ];
    }
    return $out;
}

# Compare the actual rows of the smaller tables as canonical hash multisets, so duplicates and values are verified rather than counted
# Nothing is excluded here: the live site keeps writing while this runs, so the differing tables are reported and the caller decides which of them a running site fully explains
function getProbeDataDiff(PDO $one, PDO $two, array $names): array {
    $bad = [];
    $seen = 0;
    foreach ($names as $name) {
        $num = (int)$one->query('SELECT COUNT(*) FROM `'.$name.'`')->fetchColumn();
        if ($num > 4000) continue;
        $seen++;
        if (getProbeRowSet($one, $name) !== getProbeRowSet($two, $name)) $bad[] = $name;
    }
    return ['compared' => $seen, 'differing' => $bad];
}

# Build the canonical hash multiset of one table
function getProbeRowSet(PDO $pdo, string $name): array {
    $out = [];
    foreach ($pdo->query('SELECT * FROM `'.$name.'`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        ksort($row);
        $mark = hash('sha256', json_encode(array_map(static fn(mixed $val): string => $val === null ? "\0null" : bin2hex((string)$val), $row)));
        $out[$mark] = ($out[$mark] ?? 0) + 1;
    }
    ksort($out);
    return $out;
}

# A minimal application smoke test against the restored database: the queries the core actually issues on a page view must work and return the same shape
function getProbeSmoke(PDO $pdo): array {
    $pre = (string)$GLOBALS['conf']['db']['prefix'];
    $out = [];
    $test = [
        'blocks' => 'SELECT id, bkey, title, content, url, bfile, view, expire, action, bpos, which FROM `'.$pre.'_blocks` WHERE status = \'1\' ORDER BY weight ASC',
        'users' => 'SELECT id, name, email, regdate FROM `'.$pre.'_users` ORDER BY id LIMIT 5',
        'mail' => 'SELECT * FROM `'.$pre.'_mail` ORDER BY id DESC LIMIT 3',
        'session' => 'SELECT COUNT(*) FROM `'.$pre.'_session`',
    ];
    foreach ($test as $key => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $out[$key] = is_object($stmt) ? count($stmt->fetchAll()) : -1;
        } catch (Throwable $error) {
            $out[$key] = 'error: '.substr($error->getMessage(), 0, 90);
        }
    }
    return $out;
}

# Durable persistence against a real file, and against one that cannot be opened
function getProbeSync(): array {
    $back = getProbeBackup(['compress' => 'none']);
    $file = $GLOBALS['proot'].'/sync.tmp';
    file_put_contents($file, 'durable');
    $out = ['ok' => 'thrown'];
    try {
        getProbeCall($back, 'addFileSync', [$file]);
        $out['ok'] = 'done';
    } catch (Throwable $error) {
        $out['ok'] = $error->getMessage();
    }
    try {
        getProbeCall($back, 'addFileSync', [$GLOBALS['proot'].'/missing.tmp']);
        $out['missing'] = 'no exception';
    } catch (Throwable $error) {
        $out['missing'] = $error->getMessage();
    }
    unlink($file);
    $out['clean'] = deleteProbeSchema();
    return $out;
}

$GLOBALS['phost'] = $GLOBALS['conf']['db']['host'];
$GLOBALS['puser'] = $GLOBALS['conf']['db']['uname'];
$GLOBALS['ppass'] = $GLOBALS['conf']['db']['pass'];
$mode = (string)($argv[1] ?? '');
$out = [];
try {
    addProbeSchema();
    $out = match ($mode) {
        'export' => getProbeExport(),
        'compress' => getProbeCompress(),
        'publish' => getProbePublish(),
        'drift' => getProbeDrift(),
        'fail' => getProbeFail(),
        'scope' => getProbeScope(),
        'grant' => getProbeGrant(),
        'restore' => getProbeRestore(),
        'gate' => getProbeGate(),
        'sync' => getProbeSync(),
        default => ['error' => 'unknown scenario '.$mode],
    };
} catch (Throwable $error) {
    deleteProbeSchema();
    $out = ['error' => $error->getMessage()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
