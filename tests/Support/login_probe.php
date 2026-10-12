<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for _login_log of docs/ARCHITECTURE.md, "Journal Boundary": its writer addLoginLog(), its reader getLoginLog(), the monitor count and the cleanup of cachegc
# It boots the real core with the configuration copied into scratch and both login switches off there, then points the database facade at a disposable schema
# That schema carries the shipped tables of the administrators, the accounts and the logins; the site database is never written and the schema is dropped again
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
if (!is_dir($probework.'/config')) {
    mkdir($probework.'/config', 0777, true);
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') copy($file, $probework.'/config/'.basename($file));
    }
}
$psec = require $probework.'/config/security.php';
$psec['security'] = array_merge($psec['security'], ['log_a' => '0', 'log_u' => '0']);
file_put_contents($probework.'/config/security.php', "<?php\nreturn ".var_export($psec, true).";\n");
if (is_file($probework.'/config/local.php')) unlink($probework.'/config/local.php');
define('CONFIG_DIR', $probework.'/config');
define('CACHE_DIR', $probework.'/cache');
require_once BASE_DIR.'/core/system.php';

$GLOBALS['pname'] = 'slaed_ll_'.bin2hex(random_bytes(4));

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    return new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# One shipped CREATE TABLE out of the fresh schema, filled for the disposable database
function getProbeTable(string $name): string {
    $text = (string)file_get_contents(BASE_DIR.'/storage/update/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', $GLOBALS['conf']['db']['collate']], $hit[0]);
}

# Create the disposable schema with an administrator and two accounts, the administrator sharing the id of the first account, and point the facade at it
function addProbeSchema(): void {
    global $conf;
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE '.$conf['db']['collate']);
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    foreach (['admins', 'users', 'login_log'] as $name) $root->exec(getProbeTable($name));
    $root->exec('INSERT INTO `'.PREFIX_DB.'_admins` (`id`, `name`, `email`, `password`, `modules`) VALUES (2, \'root\', \'root@probe.test\', \'x\', \'\')');
    foreach ([2 => 'anna', 3 => 'boris'] as $id => $name) {
        $root->exec('INSERT INTO `'.PREFIX_DB.'_users` (`id`, `name`, `email`, `password`, `sig`, `block`, `warnings`, `field`)'
            .' VALUES ('.$id.', \''.$name.'\', \''.$name.'@probe.test\', \'x\', \'\', \'\', \'\', \'\')');
    }
    $GLOBALS['db'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $GLOBALS['pname'], $conf['db']['collate']);
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

# Every stored login in the order it was written
function getProbeRows(): array {
    global $db;
    $out = [];
    $result = $db->getSqlQuery('SELECT uid, admin, result, way, ip, agent FROM '.PREFIX_DB.'_login_log ORDER BY id');
    while ($row = $db->getSqlRow($result)) $out[] = [intval($row['uid']), intval($row['admin']), $row['result'], $row['way'], $row['ip'] !== '', $row['agent'] !== ''];
    return $out;
}

# The uid, panel flag and result of every row a read returned, newest first, with its total
function getProbeRead(array $filt, int $page = 1, int $num = 50): array {
    $log = getLoginLog($filt, $page, $num);
    $rows = array_map(static fn(array $v): array => [intval($v['uid']), intval($v['admin']), $v['result'], $v['name']], $log['rows']);
    return ['rows' => $rows, 'total' => $log['total']];
}

# Six attempts with both switches off: the site and the panel, accepted and refused, a name of no account, an OAuth login, two of them within one second
function getProbeWrite(): array {
    addLoginLog(2, false, 'ok', 'anna');
    addLoginLog(2, false, 'password', 'anna');
    addLoginLog(2, true, 'ok', 'root');
    addLoginLog(2, true, 'blocked', 'root');
    addLoginLog(0, false, 'password', 'ghost');
    addLoginLog(3, false, 'ok', 'boris', 'oauth');
    return ['rows' => getProbeRows(), 'files' => array_values(array_diff(scandir(LOGS_DIR) ?: [], ['.', '..']))];
}

# The profile of an account, the filters of the panel and the pages of a read
function getProbeFilter(): array {
    global $db;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_login_log SET time = NOW() - INTERVAL 10 DAY WHERE uid = 3');
    $day = date('Y-m-d', time() - 2 * 86400).' 00:00:00';
    return [
        'anna' => getProbeRead(['uid' => 2, 'admin' => 0]),
        'ghost' => getProbeRead(['uid' => 0, 'admin' => 0]),
        'panel' => getProbeRead(['admin' => 1]),
        'blocked' => getProbeRead(['result' => 'blocked']),
        'oauth' => getProbeRead(['way' => 'oauth']),
        'recent' => getProbeRead(['from' => $day])['total'],
        'older' => getProbeRead(['to' => $day])['total'],
        'count' => getProbeRead(['admin' => 0], 1, 0),
        'page' => getProbeRead([], 3, 2),
    ];
}

# The refused logins of the panel in the last day: one now, one two days old, refused logins of the site beside them
function getProbeMonitor(): array {
    global $db;
    addLoginLog(2, true, 'password', 'root');
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_login_log SET time = NOW() - INTERVAL 2 DAY WHERE id = :id', ['id' => $db->getSqlLastId()]);
    return [getFailedLoginCountHours(24), getFailedLoginCountHours(72)];
}

# The daily job removes the logins older than 90 days and keeps the younger ones
function getProbeClean(): array {
    global $db;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_login_log');
    foreach ([91, 89, 0] as $age) $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_login_log (uid, result, time) VALUES (:uid, \'ok\', NOW() - INTERVAL '.$age.' DAY)', ['uid' => $age]);
    $job = addCacheGcTask();
    $left = [];
    $result = $db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.'_login_log ORDER BY uid');
    while ($row = $db->getSqlRow($result)) $left[] = intval($row['uid']);
    return ['left' => $left, 'message' => $job['message']];
}

$report = ['error' => '', 'clean' => false, 'runs' => []];
try {
    addProbeSchema();
    $report['runs'] = ['write' => getProbeWrite(), 'filter' => getProbeFilter(), 'monitor' => getProbeMonitor(), 'clean' => getProbeClean()];
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}
$report['clean'] = deleteProbeSchema();
echo json_encode($report);
