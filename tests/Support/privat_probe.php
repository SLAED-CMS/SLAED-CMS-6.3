<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the private-message schema migration of docs/PRIVAT-2026.md
# Every scenario builds one documented pre-migration shape in a disposable schema, runs one update channel against it,
# and reports whether the table converged on the fresh schema, whether the mailboxes still hold what they held, and
# whether a second run of the same channel changes anything at all
# States a to d are the four shapes the state-column conversion has to converge from: two still carrying the legacy status
# column, one already converted and one carrying the renamed column with the definition a bare rename leaves behind
# The statements are read out of the shipped files through getSqlbatch(), the splitter the Inquiry tab and the installer
# already run these files with, so what the probe executes is what an installation executes
# Nothing touches the site database: the probe creates its own schema, works only in it, and drops it again
error_reporting(0);
ini_set('display_errors', '0');
if (!defined('SETUP_FILE')) define('SETUP_FILE', true);
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
require_once BASE_DIR.'/core/admin.php';

# The fixture mailbox as [recipient, sender, legacy status], with unread, read and saved messages in both directions
const PROBEROWS = [[2, 3, 0], [2, 3, 1], [2, 3, 2], [3, 2, 0], [3, 2, 2], [2, 4, 1], [4, 2, 0]];

# The states whose table already carries the four state columns, so their fixture is written in those columns and not in status
const PROBENEW = ['c', 'd'];

# The table an installation carries before the migration: one status column for both sides and four single-column keys
const PROBEOLD = 'CREATE TABLE `{prefix}_privat` ('
    .' `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
    .' `uidin` INT UNSIGNED NOT NULL DEFAULT 0,'
    .' `uidout` INT UNSIGNED NOT NULL DEFAULT 0,'
    .' `title` VARCHAR(100) NOT NULL,'
    .' `body` TEXT NOT NULL,'
    .' `time` DATETIME DEFAULT NULL,'
    .' `ip` VARCHAR(45) NOT NULL DEFAULT \'\','
    .' `status` BOOLEAN NOT NULL DEFAULT 0,'
    .' PRIMARY KEY (`id`),'
    .' KEY `uidin` (`uidin`),'
    .' KEY `uidout` (`uidout`),'
    .' KEY `status` (`status`),'
    .' KEY `time` (`time`)'
    .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

$GLOBALS['pconf'] = (require BASE_DIR.'/config/db.php')['db'];
$GLOBALS['pname'] = 'slaed_pv_'.bin2hex(random_bytes(4));
$GLOBALS['ppre'] = 't';

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    $conf = $GLOBALS['pconf'];
    return new PDO('mysql:host='.$conf['host'].';charset=utf8mb4', $conf['uname'], $conf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the disposable schema and return a connection bound to it
function addProbeSchema(): PDO {
    $conf = $GLOBALS['pconf'];
    getProbeRoot()->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    return new PDO('mysql:host='.$conf['host'].';dbname='.$GLOBALS['pname'].';charset=utf8mb4', $conf['uname'], $conf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Drop the disposable schema again and report whether the server is left without it
function deleteProbeSchema(): bool {
    try {
        getProbeRoot()->exec('DROP DATABASE IF EXISTS `'.$GLOBALS['pname'].'`');
    } catch (Throwable) {
        return false;
    }
    return true;
}

# Fill the installation placeholders of a shipped script the way the installer and the Inquiry tab fill them
function getProbeFilled(string $text): string {
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$GLOBALS['ppre'], 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $text);
}

# Read one update channel and keep what a private-message migration consists of: every procedure and every statement naming the table
function getProbeSteps(string $file): array {
    $out = getSqlbatch(getProbeFilled((string)file_get_contents($file)));
    if ($out['error'] !== '') throw new RuntimeException(basename($file).': '.$out['error']);
    $keep = [];
    foreach ($out['statements'] as $one) {
        $head = strtoupper(substr($one, 0, 16));
        if (str_starts_with($head, 'CREATE PROCEDURE') || str_starts_with($head, 'DROP PROCEDURE')) $keep[] = $one;
        elseif (str_contains($one, $GLOBALS['ppre'].'_privat')) $keep[] = $one;
    }
    return $keep;
}

# Execute one channel in file order, which is the order an installation executes it in
function setProbeSteps(PDO $pdo, array $step): void {
    foreach ($step as $one) $pdo->exec($one);
}

# Read the first column of every row of one read against the probe table
function getProbeCol(PDO $pdo, string $sql, array $args = []): array {
    $stmt = $pdo->prepare(str_replace('{t}', '`'.$GLOBALS['ppre'].'_privat`', $sql));
    $stmt->execute($args);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

# Read one number out of the probe table
function getProbeNum(PDO $pdo, string $sql, array $args = []): int {
    return getProbeCol($pdo, $sql, $args)[0] ?? 0;
}

# The users the fixture mailbox belongs to, in ascending order
function getProbeUsers(): array {
    $out = [];
    foreach (PROBEROWS as $row) {
        $out[$row[0]] = $row[0];
        $out[$row[1]] = $row[1];
    }
    sort($out);
    return $out;
}

# The table definition, with the auto-increment counter removed so a filled table compares against an empty one
function getProbeDef(PDO $pdo): string {
    $row = $pdo->query('SHOW CREATE TABLE `'.$GLOBALS['ppre'].'_privat`')->fetch(PDO::FETCH_NUM);
    return (string)preg_replace('/ AUTO_INCREMENT=\d+/', '', (string)($row[1] ?? ''));
}

# The creation stamp of the table, which the server moves whenever an ALTER rebuilds it and leaves alone when nothing runs
function getProbeBirth(PDO $pdo): string {
    $stmt = $pdo->prepare('SELECT create_time FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tab');
    $stmt->execute(['tab' => $GLOBALS['ppre'].'_privat']);
    return (string)$stmt->fetchColumn();
}

# Every state of every message, which is what proves a converted table is not written to a second time
function getProbeRows(PDO $pdo): array {
    $stmt = $pdo->query('SELECT id, uidin, uidout, viewed, saved, delin, delout FROM `'.$GLOBALS['ppre'].'_privat` ORDER BY id');
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

# Build the pre-migration table for one documented state, from the fresh schema for the three converted ones
function addProbeTable(PDO $pdo, string $stat, string $make): void {
    $tab = '`'.$GLOBALS['ppre'].'_privat`';
    $pdo->exec('DROP TABLE IF EXISTS '.$tab);
    if (in_array($stat, PROBENEW, true)) {
        $pdo->exec($make);
        if ($stat === 'd') $pdo->exec('ALTER TABLE '.$tab.' MODIFY `viewed` TINYINT(1) NOT NULL DEFAULT 0');
        return;
    }
    $pdo->exec(getProbeFilled(PROBEOLD));
    if ($stat === 'b') $pdo->exec('ALTER TABLE '.$tab.' ADD COLUMN `saved` TINYINT UNSIGNED NOT NULL DEFAULT 0');
}

# Fill the fixture mailbox, written in the legacy status for the two unconverted states and in the four states for the others
function addProbeRows(PDO $pdo, string $stat): void {
    $tab = '`'.$GLOBALS['ppre'].'_privat`';
    $new = in_array($stat, PROBENEW, true);
    $sql = $new
        ? 'INSERT INTO '.$tab.' (uidin, uidout, title, body, time, ip, viewed, saved) VALUES (:uin, :uout, :tit, :txt, :tim, :ip, :seen, :keep)'
        : 'INSERT INTO '.$tab.' (uidin, uidout, title, body, time, ip, status) VALUES (:uin, :uout, :tit, :txt, :tim, :ip, :stat)';
    $stmt = $pdo->prepare($sql);
    $num = 0;
    foreach (PROBEROWS as $row) {
        $num++;
        $args = ['uin' => $row[0], 'uout' => $row[1], 'tit' => 'probe title '.$num, 'txt' => 'probe body '.$num, 'tim' => '2026-01-01 12:00:00', 'ip' => '127.0.0.1'];
        $args += $new ? ['seen' => ($row[2] >= 1) ? 1 : 0, 'keep' => ($row[2] === 2) ? 1 : 0] : ['stat' => $row[2]];
        $stmt->execute($args);
    }
}

# The legacy mailbox of every user, read before the migration, and empty for a state that no longer carries the status column
function getProbeLegacy(PDO $pdo, string $stat): array {
    if (in_array($stat, PROBENEW, true)) return [];
    $out = ['inbox' => [], 'saved' => [], 'out' => [], 'rows' => 0, 'sav' => []];
    foreach (getProbeUsers() as $uid) {
        $out['inbox'][$uid] = getProbeNum($pdo, 'SELECT COUNT(id) FROM {t} WHERE uidin = :uid AND status <= 1', ['uid' => $uid]);
        $out['saved'][$uid] = getProbeNum($pdo, 'SELECT COUNT(id) FROM {t} WHERE uidin = :uid AND status = 2', ['uid' => $uid]);
        $out['out'][$uid] = getProbeCol($pdo, 'SELECT id FROM {t} WHERE uidout = :uid AND status <= 1 ORDER BY id', ['uid' => $uid]);
    }
    $out['rows'] = getProbeNum($pdo, 'SELECT COUNT(id) FROM {t}');
    $out['sav'] = getProbeCol($pdo, 'SELECT id FROM {t} WHERE status = 2 ORDER BY id');
    return $out;
}

# Compare the migrated mailboxes against the legacy snapshot, one flag per parity rule of the plan
function getProbeParity(PDO $pdo, array $old): array {
    if ($old === []) return [];
    $out = ['inbox' => true, 'saved' => true, 'subset' => true, 'more' => false];
    $wide = 0;
    $tight = 0;
    foreach (getProbeUsers() as $uid) {
        $box = getProbeCol($pdo, 'SELECT id FROM {t} WHERE uidout = :uid AND delout = 0 ORDER BY id', ['uid' => $uid]);
        if (getProbeNum($pdo, 'SELECT COUNT(id) FROM {t} WHERE uidin = :uid AND delin = 0 AND saved = 0', ['uid' => $uid]) !== $old['inbox'][$uid]) $out['inbox'] = false;
        if (getProbeNum($pdo, 'SELECT COUNT(id) FROM {t} WHERE uidin = :uid AND delin = 0 AND saved = 1', ['uid' => $uid]) !== $old['saved'][$uid]) $out['saved'] = false;
        if (array_diff($old['out'][$uid], $box) !== []) $out['subset'] = false;
        $wide += count($box);
        $tight += count($old['out'][$uid]);
    }
    $out['more'] = ($wide > $tight);
    $out['rows'] = (getProbeNum($pdo, 'SELECT COUNT(id) FROM {t}') === $old['rows']);
    $out['read'] = ($old['sav'] === [] || getProbeCol($pdo, 'SELECT id FROM {t} WHERE viewed = 1 AND id IN ('.implode(',', $old['sav']).') ORDER BY id') === $old['sav']);
    $out['flat'] = (getProbeNum($pdo, 'SELECT COUNT(id) FROM {t} WHERE viewed > 1 OR saved > 1 OR delin > 0 OR delout > 0') === 0);
    return $out;
}

# One scenario: build the state, run the channel, then run it again
# The already-converted state waits out the second the server stamps a creation time in, so a rebuild it must not do would be visible
function getProbeCase(PDO $pdo, string $stat, array $step, string $make, string $fresh): array {
    addProbeTable($pdo, $stat, $make);
    addProbeRows($pdo, $stat);
    $head = getProbeDef($pdo);
    $old = getProbeLegacy($pdo, $stat);
    $seed = in_array($stat, PROBENEW, true) ? getProbeRows($pdo) : [];
    if ($stat === 'c') usleep(1100000);
    $born = getProbeBirth($pdo);
    setProbeSteps($pdo, $step);
    $rows = getProbeRows($pdo);
    $out = [
        'text' => getProbeDef($pdo),
        'def' => (getProbeDef($pdo) === $fresh),
        'kept' => ($seed === [] || $seed === $rows),
        'quiet' => ($stat !== 'c' || (getProbeDef($pdo) === $head && getProbeBirth($pdo) === $born)),
        'moved' => ($stat === 'c' || $head !== $fresh),
        'parity' => getProbeParity($pdo, $old),
    ];
    setProbeSteps($pdo, $step);
    $out['stable'] = (getProbeDef($pdo) === $fresh && getProbeRows($pdo) === $rows);
    return $out;
}

$report = ['error' => '', 'clean' => false, 'fresh' => '', 'runs' => []];

try {
    $pdo = addProbeSchema();
    $make = getProbeSteps(BASE_DIR.'/setup/sql/table.sql')[0] ?? '';
    if ($make === '') throw new RuntimeException('table.sql carries no privat table');
    $pdo->exec($make);
    $fresh = getProbeDef($pdo);
    $report['fresh'] = $fresh;
    $chan = ['up62' => getProbeSteps(BASE_DIR.'/setup/sql/table_update6_3.sql')];
    foreach ($chan as $name => $step) {
        foreach (['a', 'b', 'c', 'd'] as $stat) {
            $report['runs'][$name.':'.$stat] = getProbeCase($pdo, $stat, $step, $make, $fresh);
        }
    }
} catch (Throwable $err) {
    $report['error'] = $err->getMessage();
}

$report['clean'] = deleteProbeSchema();

echo json_encode($report);
