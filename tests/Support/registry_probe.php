<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the registry of addresses of the Address Boundary of docs/ARCHITECTURE.md: the name filterSlug() makes and the class Route over _routes
# It boots the real core with the configuration copied into scratch, then points the database facade at a disposable schema holding the shipped _routes
# The mode race is the second writer of a race: a child this probe starts, which takes a path of the same name or moves the same entity while the parent holds its write
$probework = (string)($argv[1] ?? '');
$mode = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
if (!is_dir($probework.'/config')) {
    mkdir($probework.'/config', 0777, true);
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') copy($file, $probework.'/config/'.basename($file));
    }
}
define('CONFIG_DIR', $probework.'/config');
define('CACHE_DIR', $probework.'/cache');
require_once BASE_DIR.'/core/system.php';

$GLOBALS['pname'] = ($mode === 'race') ? (string)($argv[3] ?? '') : 'slaed_rt_'.bin2hex(random_bytes(4));

# The database server of the probe: the one of the site configuration, or another server SLAED_PROBE_DB names as host|user|password, as install_probe.php reads it
function getProbeCred(): array {
    global $conf;
    $dbc = $conf['db'];
    $env = (string)getenv('SLAED_PROBE_DB');
    if ($env !== '') [$dbc['host'], $dbc['uname'], $dbc['pass']] = array_pad(explode('|', $env, 3), 3, '');
    return $dbc;
}

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    $dbc = getProbeCred();
    return new PDO('mysql:host='.$dbc['host'].';charset=utf8mb4', $dbc['uname'], $dbc['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Point the facade at the disposable schema
function setProbeBase(): void {
    $dbc = getProbeCred();
    $GLOBALS['db'] = new Database($dbc['host'], $dbc['uname'], $dbc['pass'], $GLOBALS['pname'], $dbc['collate']);
}

# Create the disposable schema with the shipped _routes alone
function addProbeSchema(): void {
    global $conf;
    $text = (string)file_get_contents(BASE_DIR.'/storage/update/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_routes`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no routes table');
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE '.$conf['db']['collate']);
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    $root->exec(str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', $conf['db']['collate']], $hit[0]));
    setProbeBase();
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

# Every row of one material in the order it was written: path, state and the generated cur
function getProbeRows(int $oid): array {
    global $db;
    $res = $db->getSqlQuery('SELECT path, state, cur FROM '.PREFIX_DB.'_routes WHERE kind = \'node\' AND oid = :oid ORDER BY id', ['oid' => $oid]);
    $out = [];
    while ($row = $db->getSqlRow($res)) $out[] = [$row['path'], intval($row['state']), ($row['cur'] === null) ? null : intval($row['cur'])];
    return $out;
}

# Whether a raw statement passes, and the unique key it hit when it fails
function getProbeRaw(string $sql): array {
    global $db;
    $done = $db->getSqlQuery($sql) !== false;
    return [$done, $done ? '' : $db->getSqlTwin()];
}

# The names of the six locales, a title of symbols only, the cut at a hyphen, the hard cut of one long word and a hyphen right at the limit
function getProbeSlug(): array {
    $cases = [
        'ru' => 'Адаптивный шаблон Pure',
        'uk' => 'Їжак і ґанок: є п’ять',
        'de' => 'Größe der Äpfel über Übung',
        'pl' => 'Zażółć gęślą jaźń',
        'fr' => 'L’été où ça a commencé, cœur Noël',
        'en' => 'Hello, World! 2026',
        'sym' => '!!! ??? ...',
        'html' => 'Доработка модуля &quot;shop&quot; 5-&gt;6, l&#039;été &amp; &lt;title&gt;',
        'cut' => implode(' ', array_fill(0, 20, 'Слово')),
        'word' => str_repeat('a', 100),
        'edge' => str_repeat('b', 80).' c',
    ];
    return array_map('filterSlug', $cases);
}

# First paths, suffixes after a current, a former and a gone path, moves without a chain, the keys and the generated column, gone entities, the set lookup
function getProbeRegistry(): array {
    global $db;
    $rte = new Route($db);
    $out = [];
    $out['first'] = $rte->addRoute('node', 1, 'files', 'Адаптивный шаблон Pure');
    $out['again'] = $rte->addRoute('node', 1, 'files', 'Another title');
    $out['twin'] = $rte->addRoute('node', 2, 'files', 'Адаптивный шаблон Pure');
    $out['cat'] = $rte->addRoute('cat', 1, 'files', 'Модули');
    $out['catnode'] = $rte->addRoute('node', 3, 'files', 'Модули');
    $out['forum'] = $rte->addRoute('cat', 2, 'forum', 'Модули');
    $out['empty'] = $rte->addRoute('node', 4, 'files', '!!!');
    $rte->addRoute('node', 5, 'files', 'Old name');
    $out['moved'] = $rte->setRoute('node', 5, 'files', 'New name');
    $out['former'] = $rte->addRoute('node', 6, 'files', 'Old name');
    $rte->addRoute('node', 7, 'files', 'Gone');
    $out['deleted'] = $rte->deleteRoute('node', 7);
    $out['gone'] = $rte->addRoute('node', 8, 'files', 'Gone');
    $out['third'] = $rte->setRoute('node', 5, 'files', 'Third name');
    $out['nochain'] = [$rte->getRoute('files/old-name'), $rte->getRoute('files/new-name'), $rte->getRoute('files/third-name')];
    $out['back'] = $rte->setRoute('node', 5, 'files', 'Old name');
    $out['same'] = $rte->setRoute('node', 5, 'files', 'Old name');
    $out['rows'] = getProbeRows(5);
    $out['owner'] = getProbeRaw('INSERT INTO '.PREFIX_DB.'_routes (path, kind, oid) VALUES (\'files/other\', \'node\', 5)');
    $out['cur'] = getProbeRaw('INSERT INTO '.PREFIX_DB.'_routes (path, kind, oid, cur) VALUES (\'files/x\', \'node\', 99, 1)');
    $out['check'] = getProbeRaw('INSERT INTO '.PREFIX_DB.'_routes (path, kind, oid, state) VALUES (\'files/y\', \'node\', 98, 3)');
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_routes SET state = 0 WHERE kind = \'node\' AND oid = 1');
    $out['hand'] = getProbeRows(1);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_routes SET state = 1 WHERE kind = \'node\' AND oid = 1');
    $rte->deleteRoute('node', 5);
    $out['allgone'] = getProbeRows(5);
    $out['gonepath'] = $rte->getRoute('files/new-name');
    $out['noreuse'] = $rte->addRoute('node', 9, 'files', 'Old name');
    $out['nomove'] = $rte->setRoute('node', 9, 'files', 'New name');
    $num = $db->qnum;
    $out['list'] = $rte->getRouteList('node', [1, 2, 5, 999, 2]);
    $out['listq'] = $db->qnum - $num;
    $out['none'] = [$rte->getRoute('files/never'), $rte->getRoute('Files/Bad'), $rte->getRoute('files/a/b')];
    $out['refuse'] = [$rte->addRoute('page', 1, 'files', 'x'), $rte->addRoute('node', 0, 'files', 'x'), $rte->addRoute('node', 1, 'Files', 'x'),
        $rte->addRoute('type', 1, 'files', 'x'), $rte->setRoute('type', 1, '', 'x')];
    $db->setSqlBegin();
    $out['inside'] = $rte->addRoute('node', 10, 'files', 'Inside');
    $out['open'] = $db->checkSqlActive();
    $db->setSqlRollback();
    $out['undone'] = $rte->getRoute('files/inside');
    [$out['apart']] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_routes WHERE (state = 1) <> (cur <=> 1)'));
    return $out;
}

# The operations of a module and of a type take a suffix under their prefix; a type takes its name or nothing, never the name of a module, an entry of public/ or a gone type
function getProbeReserved(): array {
    global $db;
    $rte = new Route($db);
    $ops = [
        $rte->addRoute('node', 20, 'files', 'Add'),
        $rte->addRoute('node', 21, 'files', 'View'),
        $rte->addRoute('node', 22, 'files', 'Mode'),
        $rte->addRoute('cat', 23, 'forum', 'Move'),
        $rte->addRoute('topic', 24, 'forum', 'Send'),
        $rte->addRoute('user', 25, 'users', 'Rules'),
        $rte->addRoute('user', 26, 'users', 'Newlang'),
        $rte->addRoute('poll', 27, 'voting', 'View'),
        $rte->addRoute('user', 28, 'users', 'View'),
    ];
    $type = [$rte->addRoute('type', 1, '', 'files'), $rte->addRoute('type', 2, '', 'files'), $rte->addRoute('type', 3, '', 'recipes')];
    $rte->deleteRoute('type', 3);
    $names = [];
    foreach (['recipes', 'books', 'files', 'uploads', 'api', 'demo', 'phoenix', 'templates', 'forum', 'account', 'node', 'admin', 'Books', '1abc'] as $name) {
        $names[$name] = $rte->checkTypeName($name);
    }
    $type[] = $rte->addRoute('type', 4, '', 'uploads');
    return ['ops' => $ops, 'type' => $type, 'names' => $names];
}

# The second writer of a race in its own process: it asks for the path the parent holds uncommitted, or moves the entity the parent is moving, and waits on its lock
function getProbeChild(string $race): array {
    global $db;
    $rte = new Route($db);
    return ['path' => ($race === 'move') ? $rte->setRoute('node', 41, 'files', 'Moved B') : $rte->addRoute('node', 31, 'files', 'Race')];
}

# Two writers race: the parent writes inside an open transaction, starts the child, waits until the child is blocked on the statement it names, then commits
function getProbeRace(string $race): array {
    global $db;
    $rte = new Route($db);
    if ($race === 'move') $rte->addRoute('node', 41, 'files', 'Moved');
    $db->setSqlBegin();
    $first = ($race === 'move') ? $rte->setRoute('node', 41, 'files', 'Moved A') : $rte->addRoute('node', 30, 'files', 'Race');
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['probework']).' race '.escapeshellarg($GLOBALS['pname']).' '.$race;
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('The child writer did not start');
    $root = getProbeRoot();
    $ask = $root->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = :db AND INFO LIKE :info');
    $info = ($race === 'move') ? 'UPDATE%_routes%' : 'INSERT INTO%_routes%';
    $wait = false;
    for ($i = 0; $i < 150 && !$wait; $i++) {
        usleep(100000);
        $ask->execute(['db' => $GLOBALS['pname'], 'info' => $info]);
        $wait = intval($ask->fetchColumn()) > 0;
    }
    $db->setSqlCommit();
    $text = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $data = json_decode($text, true);
    $rows = ($race === 'move') ? [getProbeRows(41)] : [getProbeRows(30), getProbeRows(31)];
    return ['first' => $first, 'second' => is_array($data) ? ($data['path'] ?? '') : $text, 'waited' => $wait, 'rows' => $rows];
}

if ($mode === 'race') {
    setProbeBase();
    echo json_encode(getProbeChild((string)($argv[4] ?? '')));
    exit;
}
$report = ['error' => '', 'clean' => false, 'runs' => []];
try {
    addProbeSchema();
    $report['runs'] = ['slug' => getProbeSlug(), 'registry' => getProbeRegistry(), 'reserved' => getProbeReserved(), 'race' => getProbeRace('path'),
        'move' => getProbeRace('move')];
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}
$report['clean'] = deleteProbeSchema();
echo json_encode($report);
