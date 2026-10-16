<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the portion mode of Backup: it never loads the core, only the class and config/db.php, the way core/rescue.php will
# The parent seeds a disposable schema, then runs every portion of a dump and of a restore in a child process of its own, as one request per portion
# Nothing touches the site database: two disposable schemas and one probe account are created and dropped again
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$GLOBALS['dbconf'] = (require BASE_DIR.'/config/db.php')['db'];
require BASE_DIR.'/core/classes/backup.php';

# Open a connection the way a caller of the portion mode does: exceptions on, native prepares like Database or the driver default like the rescuer
function getProbeLink(string $base, bool $native = false, string $user = '', string $pass = ''): PDO {
    $conf = $GLOBALS['dbconf'];
    $dsn = 'mysql:host='.$conf['host'].($base !== '' ? ';dbname='.$base : '').';charset=utf8mb4';
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if ($native) $opts[PDO::ATTR_EMULATE_PREPARES] = false;
    return new PDO($dsn, ($user !== '') ? $user : $conf['uname'], ($user !== '') ? $pass : $conf['pass'], $opts);
}

# Read one JSON file of the work directory, or an empty array when it is not there yet
function getProbeFile(string $work, string $name): array {
    $text = is_file($work.'/'.$name) ? file_get_contents($work.'/'.$name) : '';
    $data = json_decode((string)$text, true);
    return is_array($data) ? $data : [];
}

# Write one JSON file of the work directory
function setProbeFile(string $work, string $name, array $data): void {
    file_put_contents($work.'/'.$name, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

# What a child loaded: the proof that the portion mode needs neither the core nor the Database facade
function getProbeLoad(): array {
    return ['files' => array_map(static fn(string $v): string => basename($v), get_included_files()), 'core' => function_exists('getVar') || class_exists('Database', false)];
}

# One child step against the state files of the work directory; every library call of the portion mode runs here and nowhere else
function getProbeChild(string $oper, string $work): array {
    $seed = getProbeFile($work, 'probe.json');
    if ($oper === 'fix') {
        $pdo = getProbeLink($seed['base']);
        $tabs = Backup::getSiteTables($pdo, 'ab');
        $miss = Backup::checkDumpAccess($pdo, $tabs);
        setProbeFile($work, 'state.json', Backup::getDumpState($work.'/dump', 'ab', $tabs));
        return ['tabs' => $tabs, 'miss' => $miss];
    }
    if ($oper === 'dump') {
        $state = Backup::addDumpPart(getProbeLink($seed['base'], true), getProbeFile($work, 'state.json'));
        setProbeFile($work, 'state.json', $state);
        return ['done' => $state['done'], 'parts' => count($state['parts'])];
    }
    if ($oper === 'restore' || $oper === 'empty') {
        $file = $oper.'.json';
        $state = getProbeFile($work, $file) ?: Backup::getRestoreState($work.'/dump');
        $state = Backup::addRestorePart(getProbeLink(($oper === 'empty') ? $seed['copy'] : $seed['base']), $state);
        setProbeFile($work, $file, $state);
        return ['done' => $state['done'], 'part' => $state['part'], 'head' => $state['head']];
    }
    if ($oper === 'access') {
        $pdo = getProbeLink($seed['base'], false, $seed['user'], 'probe');
        return ['miss' => Backup::checkDumpAccess($pdo, Backup::getSiteTables($pdo, 'ab'))];
    }
    if ($oper === 'refuse') return getProbeRefuse($work, $seed);
    return ['error' => 'unknown step '.$oper];
}

# Every refusal the contract promises, each caught and reported by its message; the dump is copied first, so the real one stays intact
function getProbeRefuse(string $work, array $seed): array {
    $copy = $work.'/bad';
    if (!is_dir($copy)) mkdir($copy);
    foreach (scandir($work.'/dump') ?: [] as $file) {
        if (is_file($work.'/dump/'.$file)) copy($work.'/dump/'.$file, $copy.'/'.$file);
    }
    $pdo = getProbeLink($seed['copy']);
    $out = [];
    $try = static function (callable $call): string {
        try {
            $call();
            return 'accepted';
        } catch (Throwable $error) {
            return $error->getMessage();
        }
    };
    $index = json_decode((string)file_get_contents($copy.'/dump.json'), true);
    $first = $copy.'/'.$index['parts'][0]['file'];
    file_put_contents($first, 'x', FILE_APPEND);
    $out['damaged'] = $try(static fn() => Backup::addRestorePart($pdo, Backup::getRestoreState($copy)));
    $gap = $index;
    $gap['parts'] = array_values(array_filter($gap['parts'], static fn(array $v): bool => $v['tab'] !== 'ab_myisam'));
    file_put_contents($copy.'/dump.json', json_encode($gap));
    $out['gap'] = $try(static fn() => Backup::getRestoreState($copy));
    $index['ver'] = 2;
    file_put_contents($copy.'/dump.json', json_encode($index));
    $out['format'] = $try(static fn() => Backup::getRestoreState($copy));
    $state = getProbeFile($work, 'state.json');
    $state['ver'] = 2;
    $out['dumpver'] = $try(static fn() => Backup::addDumpPart($pdo, $state));
    $out['restver'] = $try(static fn() => Backup::addRestorePart($pdo, ['ver' => 2, 'dir' => $work.'/dump', 'part' => 0, 'head' => 0, 'done' => false]));
    $out['finished'] = $try(static fn() => Backup::getDumpState($work.'/dump', 'ab', ['ab_admins']));
    $out['silent'] = $try(static function () use ($seed): void {
        $soft = getProbeLink($seed['base']);
        $soft->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        Backup::getSiteTables($soft, 'ab');
    });
    $out['prefix'] = $try(static fn() => Backup::getSiteTables($pdo, 'ab%'));
    return $out;
}

# Run one child step in a fresh process and decode its report
function getProbeRun(string $oper, string $work): array {
    $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' part '.escapeshellarg($oper).' '.escapeshellarg($work).' 2>&1');
    $data = json_decode($out, true);
    return is_array($data) ? $data : ['error' => 'child '.$oper.' answered: '.substr($out, 0, 400)];
}

# Create the source schema: a site prefix ab with every shape the mode must carry, a longer prefix ab_x with its own admins, and two strangers
function addProbeSeed(PDO $root, string $base): void {
    $root->exec('CREATE DATABASE `'.$base.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
    $root->exec('USE `'.$base.'`');
    $root->exec('SET SESSION sql_mode = \'NO_AUTO_VALUE_ON_ZERO\', SESSION time_zone = \'+03:00\'');
    $root->exec('CREATE TABLE `ab_admins` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(25) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_keyed` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(190) NOT NULL, `body` MEDIUMTEXT NULL, `cost` DECIMAL(10,2) NOT NULL'
        .' DEFAULT 0, `data` BLOB NULL, `made` TIMESTAMP NULL, `num` INT NULL, `ratio` DOUBLE NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_multi` (`one` INT NOT NULL, `two` INT NOT NULL, `txt` VARCHAR(1000) NOT NULL, PRIMARY KEY (`one`, `two`)) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_nokey` (`tag` VARCHAR(20) NOT NULL, `body` TEXT NOT NULL) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_myisam` (`id` INT NOT NULL, `txt` VARCHAR(50) NOT NULL, PRIMARY KEY (`id`)) ENGINE=MyISAM');
    $root->exec('CREATE TABLE `ab_gen` (`id` INT NOT NULL, `price` INT NOT NULL, `total` INT AS (`price` * 2) STORED, PRIMARY KEY (`id`)) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_child` (`id` INT NOT NULL, `kid` INT UNSIGNED NOT NULL, PRIMARY KEY (`id`), KEY `kid` (`kid`),'
        .' CONSTRAINT `ab_fk_child` FOREIGN KEY (`kid`) REFERENCES `ab_keyed` (`id`)) ENGINE=InnoDB');
    $root->exec('CREATE TABLE `ab_empty` (`id` INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB');
    foreach (['ab_x_admins', 'ab_x_users', 'abc_admins', 'other_t'] as $name) {
        $root->exec('CREATE TABLE `'.$name.'` (`id` INT NOT NULL, `txt` VARCHAR(50) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');
        $root->exec('INSERT INTO `'.$name.'` VALUES (1, \'stranger\'), (2, \''.$name.'\')');
    }
    $root->exec('INSERT INTO `ab_admins` (`name`) VALUES (\'admin\'), (\'editor\')');
    $stmt = $root->prepare('INSERT INTO `ab_keyed` (`id`, `title`, `body`, `cost`, `data`, `made`, `num`) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([0, 'zero key', 'kept as 0', '1.00', null, null, 0]);
    $stmt->execute([1, 'O\'Neil "quoted" \\ back', 'line;'."\n".'break; and ;'."\r\n".'more', '-3.50', "\x00\x01\xff\xfe", '2026-01-01 12:00:00', -42]);
    $stmt->execute([2, 'Ünicode Ру Текст 😀', '', '0.00', '', '2026-07-01 23:30:00', null]);
    addProbeRows($root, 'ab_keyed', ['title', 'body', 'cost', 'num'], 6000, static fn(int $i): array => ['row '.$i, getProbeText($i, 800), ($i % 997).'.25', $i * 3]);
    $root->exec('UPDATE `ab_keyed` SET `ratio` = `id` / 7e0 WHERE `id` > 2');
    addProbeRows($root, 'ab_multi', ['one', 'two', 'txt'], 5000, static fn(int $i): array => [intdiv($i, 7), $i % 7, getProbeText($i, 900)]);
    addProbeRows($root, 'ab_nokey', ['tag', 'body'], 5000, static fn(int $i): array => ['t'.($i % 40), getProbeText($i % 4000, 900)]);
    addProbeRows($root, 'ab_myisam', ['id', 'txt'], 50, static fn(int $i): array => [$i, 'myisam '.$i]);
    addProbeRows($root, 'ab_gen', ['id', 'price'], 20, static fn(int $i): array => [$i, $i * 5]);
    addProbeRows($root, 'ab_child', ['id', 'kid'], 30, static fn(int $i): array => [$i, $i + 3]);
}

# Insert generated rows in multi-row statements; rows of the generator start at 1, the key 0 and the special values come first
function addProbeRows(PDO $pdo, string $name, array $cols, int $count, callable $make): void {
    $marks = '('.implode(',', array_fill(0, count($cols), '?')).')';
    for ($from = 1; $from <= $count; $from += 200) {
        $last = min($count, $from + 199);
        $vals = [];
        for ($i = $from; $i <= $last; $i++) array_push($vals, ...$make($i + (($name === 'ab_keyed') ? 2 : 0)));
        $sql = 'INSERT INTO `'.$name.'` (`'.implode('`,`', $cols).'`) VALUES '.implode(',', array_fill(0, $last - $from + 1, $marks));
        $pdo->prepare($sql)->execute($vals);
    }
}

# A deterministic text of the given length that does not compress to nothing, with a quote and a backslash in it
function getProbeText(int $seed, int $size): string {
    $text = '';
    for ($i = 0; strlen($text) < $size; $i++) $text .= md5($seed.':'.$i).' \' \\ ';
    return substr($text, 0, $size);
}

# Describe tables by CHECKSUM TABLE, row multiset, definition and engine; a stored generated column is marked, MariaDB 11.8 checksums it unstably
function getProbeStamp(PDO $root, string $base, array $names): array {
    $out = [];
    foreach ($names as $name) {
        $have = $root->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $have->execute([$base, $name]);
        $eng = $have->fetchColumn();
        if ($eng === false) {
            $out[$name] = 'missing';
            continue;
        }
        $gen = $root->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA LIKE \'%GENERATED%\'');
        $gen->execute([$base, $name]);
        $sum = $root->query('CHECKSUM TABLE `'.$base.'`.`'.$name.'` EXTENDED')->fetch(PDO::FETCH_NUM)[1];
        $ddl = $root->query('SHOW CREATE TABLE `'.$base.'`.`'.$name.'`')->fetch(PDO::FETCH_NUM)[1];
        $rows = [];
        foreach ($root->query('SELECT * FROM `'.$base.'`.`'.$name.'`')->fetchAll(PDO::FETCH_NUM) as $row) {
            $vals = array_map(static fn(mixed $v): string => ($v === null) ? "\0null" : bin2hex(is_float($v) ? var_export($v, true) : (string)$v), $row);
            $rows[] = hash('sha256', json_encode($vals));
        }
        sort($rows);
        $out[$name] = ['sum' => (string)$sum, 'gen' => (int)$gen->fetchColumn() > 0, 'rows' => hash('sha256', implode(',', $rows)), 'count' => count($rows),
            'ddl' => $ddl, 'engine' => $eng];
    }
    return $out;
}

# Break the source after the dump the way a failed update step would: a new table, lost and changed rows, a dropped and an altered table, and strangers touched
function addProbeWreck(PDO $root, string $base): void {
    $root->exec('USE `'.$base.'`');
    $root->exec('CREATE TABLE `ab_new` (`id` INT NOT NULL, `kid` INT UNSIGNED NOT NULL, PRIMARY KEY (`id`), CONSTRAINT `ab_fk_new` FOREIGN KEY (`kid`)'
        .' REFERENCES `ab_keyed` (`id`)) ENGINE=InnoDB');
    $root->exec('INSERT INTO `ab_new` VALUES (1, 5)');
    $root->exec('DELETE FROM `ab_child` WHERE `id` > 10');
    $root->exec('DELETE FROM `ab_keyed` WHERE `id` > 3000');
    $root->exec('UPDATE `ab_multi` SET `txt` = \'changed\' WHERE `two` = 3');
    $root->exec('DELETE FROM `ab_nokey` WHERE `tag` = \'t7\'');
    $root->exec('DROP TABLE `ab_myisam`');
    $root->exec('ALTER TABLE `ab_gen` ADD COLUMN `extra` INT NULL');
    $root->exec('INSERT INTO `ab_empty` VALUES (7)');
    $root->exec('INSERT INTO `ab_x_users` VALUES (3, \'after the dump\')');
    $root->exec('UPDATE `other_t` SET `txt` = \'after the dump\' WHERE `id` = 1');
}

# Run child steps until the state says done, with an optional hook between steps, and collect what every child loaded
function addProbeLoop(string $oper, string $work, ?callable $step = null): array {
    $out = ['steps' => 0, 'done' => false, 'loads' => [], 'error' => ''];
    for ($i = 0; $i < 400 && !$out['done']; $i++) {
        $data = getProbeRun($oper, $work);
        if (!empty($data['error'])) {
            $out['error'] = $data['error'];
            break;
        }
        $out['steps']++;
        $out['done'] = $data['done'];
        $out['loads'][] = $data['load'];
        if ($step) $step($data);
    }
    $out['loads'] = array_values(array_unique(array_map('json_encode', $out['loads'])));
    return $out;
}

# The whole cycle: fix the scope, dump portion by portion, wreck the source, restore it in place with one uncertain step, then restore into an empty schema
function getProbeCycle(string $work): array {
    $base = 'slaed_part_'.bin2hex(random_bytes(4));
    $seed = ['base' => $base, 'copy' => $base.'_e', 'user' => 'p_'.bin2hex(random_bytes(3))];
    setProbeFile($work, 'probe.json', $seed);
    try {
        $root = getProbeLink('');
    } catch (Throwable $error) {
        return ['skip' => $error->getMessage(), 'error' => ''];
    }
    $out = ['error' => ''];
    try {
        addProbeSeed($root, $seed['base']);
        $root->exec('CREATE DATABASE `'.$seed['copy'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $site = ['ab_admins', 'ab_child', 'ab_empty', 'ab_gen', 'ab_keyed', 'ab_multi', 'ab_myisam', 'ab_nokey'];
        $alien = ['ab_x_admins', 'ab_x_users', 'abc_admins', 'other_t'];
        $out['fix'] = getProbeRun('fix', $work);
        $out['source'] = getProbeStamp($root, $seed['base'], $site);
        $out['dump'] = addProbeLoop('dump', $work);
        $index = getProbeFile($work.'/dump', 'dump.json');
        $out['index'] = ['tabs' => $index['tabs'] ?? [], 'parts' => array_count_values(array_column($index['parts'] ?? [], 'tab')), 'pack' => $index['pack'] ?? '',
            'rows' => $index['rows'] ?? 0, 'files' => array_values(array_diff(scandir($work.'/dump') ?: [], ['.', '..']))];
        addProbeWreck($root, $seed['base']);
        $out['alien'] = getProbeStamp($root, $seed['base'], $alien);
        $out['retry'] = '';
        $prev = Backup::getRestoreState($work.'/dump');
        $out['restore'] = addProbeLoop('restore', $work, static function (array $data) use ($work, &$out, &$prev): void {
            if ($out['retry'] === '' && !$data['done'] && $prev['part'] > $prev['head']) {
                $out['retry'] = 'part '.$prev['part'].' ran, its result was lost, resumed at '.$prev['head'];
                $prev['part'] = $prev['head'];
                setProbeFile($work, 'restore.json', $prev);
                return;
            }
            $prev = getProbeFile($work, 'restore.json');
        });
        $out['inplace'] = getProbeStamp($root, $seed['base'], array_merge($site, ['ab_new']));
        $out['after'] = getProbeStamp($root, $seed['base'], $alien);
        $out['empty'] = addProbeLoop('empty', $work);
        $out['copied'] = getProbeStamp($root, $seed['copy'], $site);
        $out['strays'] = $root->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$root->quote($seed['copy']))->fetchColumn();
        $out['refuse'] = getProbeRun('refuse', $work);
        $out['grant'] = getProbeGrant($root, $seed);
    } catch (Throwable $error) {
        $out['error'] = $error->getMessage();
    }
    $out['clean'] = deleteProbeCycle($root, $seed);
    return $out;
}

# The preflight against real accounts: SELECT alone lacks the rights to recreate, and a hosting grant on an escaped name pattern is proof enough
function getProbeGrant(PDO $root, array $seed): array {
    $user = '\''.$seed['user'].'\'@\'%\'';
    $root->exec('CREATE USER '.$user.' IDENTIFIED BY \'probe\'');
    $root->exec('GRANT SELECT ON `'.$seed['base'].'`.* TO '.$user);
    $out = ['select' => getProbeRun('access', (string)$GLOBALS['probework'])];
    $root->exec('GRANT ALL PRIVILEGES ON `'.str_replace('_', '\_', substr($seed['base'], 0, -2)).'%`.* TO '.$user);
    $out['pattern'] = getProbeRun('access', (string)$GLOBALS['probework']);
    return $out;
}

# Drop both schemas, the probe account and the work directory, and report whether all of it is gone
function deleteProbeCycle(PDO $root, array $seed): bool {
    try {
        $root->exec('DROP DATABASE IF EXISTS `'.$seed['base'].'`');
        $root->exec('DROP DATABASE IF EXISTS `'.$seed['copy'].'`');
        $root->exec('DROP USER IF EXISTS \''.$seed['user'].'\'@\'%\'');
        $left = (int)$root->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('.$root->quote($seed['base']).', '.$root->quote($seed['copy']).')')
            ->fetchColumn();
    } catch (Throwable) {
        return false;
    }
    return $left === 0;
}

$mode = (string)($argv[1] ?? '');
if ($mode === 'part') {
    $GLOBALS['probework'] = (string)($argv[3] ?? '');
    try {
        $out = getProbeChild((string)($argv[2] ?? ''), $GLOBALS['probework']);
    } catch (Throwable $error) {
        $out = ['error' => $error->getMessage()];
    }
    $out['load'] = getProbeLoad();
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
$GLOBALS['probework'] = str_replace('\\', '/', (string)($argv[2] ?? ''));
$out = ['error' => 'unknown scenario '.$mode];
if ($mode === 'cycle' && $GLOBALS['probework'] !== '') {
    if (!is_dir($GLOBALS['probework'])) mkdir($GLOBALS['probework'], 0777, true);
    $out = getProbeCycle($GLOBALS['probework']);
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
