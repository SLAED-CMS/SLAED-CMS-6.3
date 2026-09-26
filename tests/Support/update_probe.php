<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the points, ratings and fields units of the 6.3 data update in setup/index.php, whose contracts are docs/node/12-migration.md and docs/node/ratings.md
# The second argument names the unit, points when it is left out; all three share the schema, the lifted installer code and the scratch site
# The installer cannot be required from CLI: it is a request handler that acts on load, so its functions are lifted out of the shipped source by name
# BASE_DIR and CONFIG_DIR point into scratch, so the manifest, the snapshot, the mark and every configuration file the unit writes stay away from the site
# Nothing touches the site database either: the probe creates its own schema, works only in it, and drops it again
$probework = str_replace('\\', '/', (string)($argv[1] ?? ''));
if ($probework === '') $probework = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_probe';
define('BASE_DIR', $probework.'/site');
define('CONFIG_DIR', BASE_DIR.'/config');
require_once __DIR__.'/probe_boot.php';

# The tree the shipped sources are read from
const PROBEROOT = __DIR__.'/../..';

# The table prefix of the disposable schema
const PROBEPREF = 'probe';

# The balances the snapshot has to carry, the top of the unsigned column among them
const PROBEBAL = [2 => 10, 3 => 0, 4 => 4294967295];

# The rules of a 6.2 site: period, switch and place of three remaining scopes, a zero period among them, and one scope of a module that left the release
const PROBEOLD = ['account' => '2592000|1|0', 'forum' => '0|0|1', 'shop' => '86400|1|1', 'news' => '2592000|1|0'];

# The rows of the old shared table as id, target, module, time, account and address: two addresses of one account, a guest, two spellings of one IPv6 address,
# a poll, two other events, a missing product, a forum reply that is no target, and an account without an address
const PROBEROWS = [
    [1, 2, 'account', '1000000000', 9, '1.1.1.1'], [2, 2, 'account', '1700000000', 9, '2.2.2.2'], [3, 2, 'account', '1700000100', 0, '3.3.3.3'],
    [4, 5, 'forum', '1700000200', 0, '2001:DB8:0:0:0:0:0:1'], [5, 5, 'forum', '1700000205', 0, '2001:db8::1'], [6, 35, 'voting', '1700000300', 9, '4.4.4.4'],
    [7, 1, 'download', '1700000300', 0, '4.4.4.4'], [8, 1, 'news', '1700000300', 0, '4.4.4.4'], [9, 999, 'shop', '1700000300', 0, '4.4.4.4'],
    [10, 7, 'forum', '1700000300', 0, '4.4.4.4'], [11, 8, 'shop', '1700000400', 9, ''],
];

# The installer defines the same guard before it loads the database facade on its own
if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
require_once PROBEROOT.'/core/classes/pdo.php';
require_once PROBEROOT.'/core/classes/field.php';
require_once PROBEROOT.'/core/classes/filemanager.php';
foreach (['_TABLE' => 'Table', '_OK' => 'probe-ok', '_ERROR' => 'probe-error'] as $name => $text) define($name, $text);

# A database facade that can name another server version, which is the one fact of the preflight a real server cannot be asked to change
final class ProbeBase extends Database {
    public string $fake = '';
    private bool $asked = false;

    # Remember that the version was asked for, and let the real statement run so the facade stays a working connection
    function getSqlQuery(string $query = '', array $params = []): PDOStatement|false {
        $this->asked = $this->fake !== '' && $query === 'SELECT VERSION()';
        return parent::getSqlQuery($query, $params);
    }

    # Answer the named version in place of the real one
    function getSqlRow(PDOStatement|int $query_id = 0): array|false {
        if (!$this->asked) return parent::getSqlRow($query_id);
        $this->asked = false;
        return [$this->fake];
    }
}

$GLOBALS['pname'] = 'slaed_up_'.bin2hex(random_bytes(4));
$GLOBALS['pcred'] = (require PROBEROOT.'/config/db.php')['db'];
$GLOBALS['pdb'] = null;

# Open one connection to the server, with the disposable schema selected once it exists
function getProbeSide(bool $root = false): PDO {
    $cred = $GLOBALS['pcred'];
    $dsn = 'mysql:host='.$cred['host'].($root ? '' : ';dbname='.$GLOBALS['pname']).';charset=utf8mb4';
    return new PDO($dsn, $cred['uname'], $cred['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# One shipped CREATE TABLE out of the fresh schema, filled for the disposable database
function getProbeTable(string $name, string $engine = 'InnoDB'): string {
    $text = (string)file_get_contents(PROBEROOT.'/setup/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PROBEPREF, $engine, 'utf8mb4', 'utf8mb4_unicode_ci'], $hit[0]);
}

# Lift one function out of the shipped installer into this process
function addProbeCode(string $name): void {
    $code = (string)file_get_contents(PROBEROOT.'/setup/index.php');
    $from = strpos($code, 'function '.$name.'(');
    $to = $from === false ? false : strpos($code, "\n}\n", $from);
    if ($from === false || $to === false) throw new RuntimeException($name.'() is gone from setup/index.php');
    eval(substr($code, $from, $to - $from + 3));
}

# Create the disposable schema with the account table, the journal and three accounts, and connect the project facade to it
function addProbeSchema(): void {
    $root = getProbeSide(true);
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    foreach (['users', 'admins', 'points', 'forum', 'order', 'products', 'rating', 'rating_targets', 'rating_actors', 'rating_votes'] as $name) $root->exec(getProbeTable($name));
    foreach (PROBEBAL as $id => $sum) {
        $root->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `block`, `warnings`, `field`, `points`)'
            .' VALUES ('.$id.', \'user'.$id.'\', \'user'.$id.'@probe.test\', \'x\', \'\', \'\', \'\', '.$sum.')');
    }
    $cred = $GLOBALS['pcred'];
    $GLOBALS['pdb'] = new ProbeBase($cred['host'], $cred['uname'], $cred['pass'], $GLOBALS['pname']);
}

# Drop the disposable schema again and report whether the server is left without it
function deleteProbeSchema(): bool {
    try {
        getProbeSide(true)->exec('DROP DATABASE IF EXISTS `'.$GLOBALS['pname'].'`');
    } catch (Throwable) {
        return false;
    }
    return true;
}

# Remove one scratch directory with everything below it
function deleteProbeTree(string $path): void {
    if (!is_dir($path)) return;
    foreach (array_diff(scandir($path), ['.', '..']) as $name) is_dir($path.'/'.$name) ? deleteProbeTree($path.'/'.$name) : unlink($path.'/'.$name);
    rmdir($path);
}

# Put the scratch site back to a 6.2 installation: the shipped points scope, an account scope that still carries the positional rules, no backup, no mark, an empty journal
function setProbeSite(string $point = '0'): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    copy(PROBEROOT.'/config/points.php', CONFIG_DIR.'/points.php');
    $users = (require PROBEROOT.'/config/users.php')['users'];
    setConfigFile('users.php', ['point' => $point, 'points' => '1,2,3'] + $users);
    getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_points`');
    foreach (PROBEBAL as $id => $sum) getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET points = '.$sum.' WHERE id = '.$id);
}

# Everything the unit leaves behind, read fresh from disk: its answer, the manifest, the snapshot, the mark and the two scopes it carries over
function getProbeState(string $html): array {
    $dir = BASE_DIR.'/storage/backup/update/points';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $snap = is_file($dir.'/balances.json') ? (string)file_get_contents($dir.'/balances.json') : null;
    clearstatcache();
    $users = (include CONFIG_DIR.'/users.php')['users'];
    $hash = fn(string $name): string => (string)hash_file('sha256', CONFIG_DIR.'/'.$name);
    return [
        'done' => str_contains($html, _OK) && !str_contains($html, _ERROR),
        'text' => trim(strip_tags($html)),
        'state' => $info['state'] ?? null,
        'count' => $info['count'] ?? null,
        'source' => $snap !== null && ($info['source']['balances.json'] ?? '') === hash('sha256', $snap),
        'target' => is_array($info) && $info['target'] === ['points.php' => $hash('points.php'), 'users.php' => $hash('users.php')],
        'snap' => $snap === null ? null : json_decode($snap, true),
        'mark' => is_file(CONFIG_DIR.'/update.php') ? (include CONFIG_DIR.'/update.php')['update'] : null,
        'stale' => isset($users['point']) || isset($users['points']),
        'active' => (include CONFIG_DIR.'/points.php')['points']['active'],
        'rules' => (include CONFIG_DIR.'/points.php')['points']['actions'] === (require PROBEROOT.'/config/points.php')['points']['actions'],
    ];
}

# Run the unit once and read what it left
function getProbeRun(): array {
    return getProbeState(setUpdatePoints($GLOBALS['pdb'], PROBEPREF));
}

# Rewrite the state of the manifest the way an interrupted run would have left it
function setProbeStage(string $state): void {
    $file = BASE_DIR.'/storage/backup/update/points/manifest.json';
    $info = json_decode((string)file_get_contents($file), true);
    $info['state'] = $state;
    $info['target'] = [];
    file_put_contents($file, json_encode($info));
}

# The clean path and its repeats: the snapshot is taken once, a later balance is never taken for a starting one, and a lost mark is written again
function getProbeClean(): array {
    setProbeSite('0');
    $out = ['first' => getProbeRun()];
    $dir = BASE_DIR.'/storage/backup/update/points';
    $kept = [file_get_contents($dir.'/manifest.json'), file_get_contents($dir.'/balances.json')];
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET points = 77 WHERE id = 2');
    $out['again'] = getProbeRun();
    unlink(CONFIG_DIR.'/update.php');
    $out['nomark'] = getProbeRun();
    $out['same'] = $kept === [file_get_contents($dir.'/manifest.json'), file_get_contents($dir.'/balances.json')];
    $out['users'] = array_map('intval', getProbeSide()->query('SELECT id, points FROM `'.PROBEPREF.'_users` ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR));
    setProbeSite('1');
    $out['on'] = getProbeRun();
    return $out;
}

# The interrupted run: a manifest left at prepared or at applying continues over the old snapshot and carries the scopes over
function getProbeResume(): array {
    $out = [];
    foreach (['prepared', 'applying'] as $state) {
        setProbeSite('0');
        getProbeRun();
        $snap = BASE_DIR.'/storage/backup/update/points';
        $keep = [file_get_contents($snap.'/manifest.json'), file_get_contents($snap.'/balances.json')];
        setProbeSite('0');
        mkdir($snap, 0777, true);
        file_put_contents($snap.'/manifest.json', $keep[0]);
        file_put_contents($snap.'/balances.json', $keep[1]);
        setProbeStage($state);
        getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET points = 77 WHERE id = 2');
        $out[$state] = getProbeRun();
    }
    return $out;
}

# The stops: journal rows or a mark without a manifest, a snapshot that no longer matches its manifest, and a points scope that is not the shipped shape
function getProbeStop(): array {
    $out = [];
    setProbeSite('0');
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_points` (uid, action, scope, source, points) VALUES (2, \'login\', \'account\', \'day:20260921\', 1)');
    $out['rows'] = getProbeRun();
    setProbeSite('0');
    setConfigFile('update.php', ['points' => '6.3.0']);
    $out['mark'] = getProbeRun();
    setProbeSite('0');
    getProbeRun();
    setProbeStage('applying');
    unlink(CONFIG_DIR.'/update.php');
    file_put_contents(BASE_DIR.'/storage/backup/update/points/balances.json', '{"2":999999}');
    $out['forged'] = getProbeRun();
    setProbeStage('verified');
    unlink(BASE_DIR.'/storage/backup/update/points/balances.json');
    $out['pruned'] = getProbeRun();
    setProbeSite('0');
    $point = (require PROBEROOT.'/config/points.php')['points'];
    unset($point['actions']['login']);
    setConfigFile('points.php', $point);
    $out['scope'] = getProbeRun();
    return $out;
}

# The preflight: the stand server, the oldest accepted and the newest refused version of both servers, and a table of the points transactions on another engine
function getProbeFlight(): array {
    $pdb = $GLOBALS['pdb'];
    $out = ['real' => checkUpdateBase($pdb, PROBEPREF)];
    foreach (['10.4.34-MariaDB', '10.5.1-MariaDB', '10.5.2-MariaDB', '11.7.2-MariaDB-log', '8.0.15', '8.0.16', '5.7.44-log'] as $ver) {
        $pdb->fake = $ver;
        $out['server'][$ver] = checkUpdateBase($pdb, PROBEPREF);
    }
    $pdb->fake = '';
    getProbeSide()->exec('CREATE TABLE `'.PROBEPREF.'_favorites` (`id` INT) ENGINE=MyISAM');
    getProbeSide()->exec('CREATE TABLE `other_forum` (`id` INT) ENGINE=MyISAM');
    $out['engine'] = checkUpdateBase($pdb, PROBEPREF);
    getProbeSide()->exec('DROP TABLE `'.PROBEPREF.'_favorites`, `other_forum`');
    foreach (['categories', 'voting'] as $name) {
        getProbeSide()->exec('CREATE TABLE `'.PROBEPREF.'_'.$name.'` (`id` INT) ENGINE=MyISAM');
        $out['node'][$name] = checkUpdateBase($pdb, PROBEPREF);
        getProbeSide()->exec('DROP TABLE `'.PROBEPREF.'_'.$name.'`');
    }
    return $out;
}

# Put the scratch site and the disposable schema back to a 6.2 installation with ratings: the old rules, the points mark of the prior unit, the aggregates and the old rows
# The twelve hundred extra accounts make the unit write its targets in more than one batch; every third of them carries an aggregate
function setRateSite(array $rules = PROBEOLD, array $mark = ['points' => '6.3.0'], array $rows = PROBEROWS): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    setConfigFile('ratings.php', $rules);
    if ($mark) setConfigFile('update.php', $mark);
    $side = getProbeSide();
    foreach (['rating_targets', 'rating_actors', 'rating_votes', 'rating', 'forum', 'products'] as $name) $side->exec('DELETE FROM `'.PROBEPREF.'_'.$name.'`');
    $side->exec('DELETE FROM `'.PROBEPREF.'_users` WHERE id >= 100');
    $bulk = [];
    for ($i = 100; $i < 1300; $i++) $bulk[] = '('.$i.', \'bulk'.$i.'\', \'bulk'.$i.'@probe.test\', \'x\', \'\', \'\', \'\', '.($i % 3).', '.(($i % 3) * 4).')';
    $side->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `block`, `warnings`, `field`, `votes`, `tvotes`) VALUES '.implode(', ', $bulk));
    foreach ([2 => [10, 37], 3 => [0, 0], 4 => [1, 5]] as $id => [$num, $sum]) $side->exec('UPDATE `'.PROBEPREF.'_users` SET votes = '.$num.', tvotes = '.$sum.' WHERE id = '.$id);
    $side->exec('INSERT INTO `'.PROBEPREF.'_forum` (`id`, `pid`, `uid`, `name`, `title`, `field`, `score`, `ratings`, `status`) VALUES'
        .' (5, 0, 2, \'user2\', \'topic\', \'\', 6, 2, 2), (7, 5, 3, \'user3\', \'reply\', \'\', 5, 1, 2)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_products` (`id`, `title`, `intro`, `body`, `assoc`, `votes`, `tvotes`, `status`) VALUES (8, \'product\', \'\', \'\', \'\', 3, 15, 1)');
    $add = $side->prepare('INSERT INTO `'.PROBEPREF.'_rating` (`id`, `mid`, `modul`, `time`, `uid`, `ip`) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $row) $add->execute($row);
}

# Everything the ratings unit leaves behind, read fresh from disk and schema: answer, manifest, snapshots, the three new tables, the owners, the old table, the rules and the mark
function getRateState(string $html): array {
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $list = fn(string $sql): array => array_map(fn($row) => array_map(fn($v) => is_numeric($v) ? intval($v) : $v, $row), $side->query($sql)->fetchAll(PDO::FETCH_NUM));
    $pref = '`'.PROBEPREF.'_';
    clearstatcache();
    $files = is_array($info);
    foreach ($files ? $info['source'] : [] as $name => $hash) $files = $files && is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $hash;
    return [
        'done' => str_contains($html, _OK) && !str_contains($html, _ERROR),
        'text' => trim(strip_tags($html)),
        'dir' => is_dir($dir),
        'state' => $info['state'] ?? null,
        'cursor' => $info['cursor'] ?? null,
        'count' => $info['count'] ?? null,
        'files' => $files,
        'sealed' => is_array($info) && count($info['target']) === 3 && ($info['target']['ratings.php'] ?? '') === hash_file('sha256', CONFIG_DIR.'/ratings.php'),
        'targets' => $list('SELECT scope, mid, base, votes FROM '.$pref.'rating_targets` WHERE mid < 100 ORDER BY scope, mid'),
        'total' => intval($list('SELECT COUNT(*) FROM '.$pref.'rating_targets`')[0][0]),
        'moment' => $list('SELECT DISTINCT created FROM '.$pref.'rating_targets`') === (is_array($info) ? [[$info['moment']]] : []),
        'actors' => $list('SELECT scope, mid, actor, last FROM '.$pref.'rating_actors` ORDER BY scope, mid, actor'),
        'votes' => intval($list('SELECT COUNT(*) FROM '.$pref.'rating_votes`')[0][0]),
        'owners' => $list('SELECT id, votes, tvotes FROM '.$pref.'users` WHERE id < 100 ORDER BY id'),
        'old' => intval($list('SELECT COUNT(*) FROM '.$pref.'rating`')[0][0]),
        'rules' => (include CONFIG_DIR.'/ratings.php')['ratings'],
        'mark' => is_file(CONFIG_DIR.'/update.php') ? (include CONFIG_DIR.'/update.php')['update'] : null,
    ];
}

# Run the ratings unit once and read what it left
function getRateRun(): array {
    return getRateState(setUpdateRatings($GLOBALS['pdb'], PROBEPREF));
}

# Rewrite the manifest the way an interrupted run would have left it, with the cursors back at the start, and take the mark away
function setRateStage(string $state): void {
    $file = BASE_DIR.'/storage/backup/update/ratings/manifest.json';
    $info = json_decode((string)file_get_contents($file), true);
    $info = ['state' => $state, 'cursor' => ['targets' => 0, 'terms' => 0], 'target' => []] + $info;
    file_put_contents($file, json_encode($info));
    if (is_file(CONFIG_DIR.'/update.php')) unlink(CONFIG_DIR.'/update.php');
}

# The clean path and its repeats: a vote made after the site opened is never a starting balance, a lost mark is rewritten, and converted rules keep a closed guest switch
function getRateClean(): array {
    setRateSite();
    $out = ['first' => getRateRun()];
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $names = ['manifest.json', 'targets.json', 'terms.json', 'rules.json'];
    $kept = array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET votes = 11, tvotes = 42 WHERE id = 2');
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_rating_votes` (scope, mid, actor, uid, value, request, created)'
        .' VALUES (\'account\', 2, \'u:3\', 3, 5, \''.str_repeat('a', 32).'\', 1700000500)');
    $out['again'] = getRateRun();
    unlink(CONFIG_DIR.'/update.php');
    $out['nomark'] = getRateRun();
    $out['same'] = $kept === array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    $rule = ['active' => '1', 'period' => '86400', 'detail' => '0', 'guests' => '0'];
    setRateSite(['account' => $rule, 'forum' => $rule, 'shop' => $rule, 'node.news' => $rule]);
    $out['ready'] = getRateRun();
    return $out;
}

# The interrupted run: a manifest left at prepared starts over its snapshot, and one left at applying meets rows that are already stored and rows that are not
function getRateResume(): array {
    $out = [];
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    foreach (['prepared', 'applying'] as $state) {
        setRateSite();
        getRateRun();
        $keep = [];
        foreach (array_diff(scandir($dir), ['.', '..']) as $name) $keep[$name] = file_get_contents($dir.'/'.$name);
        if ($state === 'prepared') setRateSite();
        if ($state === 'prepared') mkdir($dir, 0777, true);
        foreach ($keep as $name => $body) file_put_contents($dir.'/'.$name, $body);
        setRateStage($state);
        getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_rating_targets` WHERE mid BETWEEN 600 AND 700');
        getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_rating_actors` WHERE scope = \'forum\'');
        $out[$state] = getRateRun();
    }
    return $out;
}

# The stops: target rows or a mark without a manifest, a forged snapshot, a stored row and an owner that left the snapshot, and the broken sources the preflight names together
function getRateStop(): array {
    $out = [];
    setRateSite();
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_rating_targets` (scope, mid, base, votes, created) VALUES (\'account\', 2, 42, 11, 1700000500)');
    $out['rows'] = getRateRun();
    setRateSite(PROBEOLD, ['points' => '6.3.0', 'ratings' => '6.3.0']);
    $out['mark'] = getRateRun();
    setRateSite();
    getRateRun();
    setRateStage('applying');
    file_put_contents(BASE_DIR.'/storage/backup/update/ratings/targets.json', '[["account",2,50,10]]');
    $out['forged'] = getRateRun();
    setRateSite();
    getRateRun();
    setRateStage('applying');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_rating_targets` SET base = 38 WHERE scope = \'account\' AND mid = 2');
    $out['differ'] = getRateRun();
    setRateSite();
    getRateRun();
    setRateStage('applying');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET tvotes = 38 WHERE id = 2');
    $out['owner'] = getRateRun();
    $rows = [
        [20, 2, 'account', 'abc', 9, '5.5.5.5'], [21, 2, 'account', '9999999999', 9, '6.6.6.6'],
        [22, 2, 'account', '1700000000', 0, '0.0.0.0'], [23, 2, 'account', '1700000000', 0, 'x'],
    ];
    setRateSite(['account' => '100|1|0', 'forum' => '0|2|1'], ['points' => '6.3.0'], array_merge(PROBEROWS, $rows));
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET votes = 0, tvotes = 4 WHERE id = 3');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_products` SET votes = 2, tvotes = 11 WHERE id = 8');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_forum` SET ratings = 3, score = 2 WHERE id = 5');
    $out['broken'] = getRateRun();
    return $out;
}

# The preflight of the branch names a table of the ratings transactions that is on another engine
function getRateFlight(): array {
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_products` ENGINE=MyISAM');
    $out = ['engine' => checkUpdateBase($GLOBALS['pdb'], PROBEPREF)];
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_products` ENGINE=InnoDB');
    return $out;
}

# The definitions of a 6.2 site: a select, a text with a default, a switched off position, a date, a moment and a textarea, with the duty slot as 1, 2, 0 and empty
# An empty slot is written as 0 the way the 6.2 form did, because two pipes in a row already part two positions; only the very last slot of the string can be empty
# The forum keeps a switched off position between two selects, so a row can follow the full or the short layout; the order has two texts around a switched off position
const PROBEDEFS = [
    'account' => 'Version|A,B,C|3|1||Name|John|1|2||0|0|1|1||Born|0|5|2||Seen|0|4|0||Notes|0|2|',
    'forum' => 'Sys|X,Y|3|2||0|0|1|1||Host|L,R|3|2',
    'order' => 'Wallet|0|1|1||0|0|1|1||First|0|1|2',
];

# The value rows of that site: every type filled, placeholders beside an empty text and a text zero, an empty row, the full and short forum layout, and a gap in an order
const PROBEVALS = [
    'users' => [2 => 'B|Änn "Q"||2001-02-03|2020-05-06 07:08|one', 3 => '0|||0|0|0', 4 => ''],
    'forum' => [5 => 'X||R', 7 => 'Y|L', 9 => '0|0|0'],
    'order' => [1 => 'Z1||Bob'],
];

# Put the scratch site and the disposable schema back to a 6.2 installation with extra fields: positional definitions, the marks of the two units before, and positional value rows
# The twelve hundred extra accounts make the unit write its rows in three batches; $bulk = false leaves them without a value
function setFieldSite(array $defs = PROBEDEFS, array $mark = ['points' => '6.3.0', 'ratings' => '6.3.0'], array $rows = PROBEVALS, bool $bulk = true): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    setConfigFile('fields.php', $defs);
    if ($mark) setConfigFile('update.php', $mark);
    $side = getProbeSide();
    foreach (['forum', 'order'] as $name) $side->exec('DELETE FROM `'.PROBEPREF.'_'.$name.'`');
    $side->exec('DELETE FROM `'.PROBEPREF.'_users` WHERE id >= 100');
    $list = [];
    for ($i = 100; $i < 1300; $i++) $list[] = '('.$i.', \'bulk'.$i.'\', \'bulk'.$i.'@probe.test\', \'x\', \'\', \'\', \''.($bulk ? 'A|user '.$i : '').'\')';
    $side->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `block`, `warnings`, `field`) VALUES '.implode(', ', $list));
    $set = $side->prepare('UPDATE `'.PROBEPREF.'_users` SET field = ? WHERE id = ?');
    foreach ($rows['users'] as $id => $text) $set->execute([$text, $id]);
    $add = $side->prepare('INSERT INTO `'.PROBEPREF.'_forum` (`id`, `pid`, `uid`, `name`, `title`, `field`, `status`) VALUES (?, 0, 2, \'user2\', \'topic\', ?, 2)');
    foreach ($rows['forum'] as $id => $text) $add->execute([$id, $text]);
    $add = $side->prepare('INSERT INTO `'.PROBEPREF.'_order` (`id`, `email`, `info`, `note`) VALUES (?, \'order@probe.test\', ?, \'\')');
    foreach ($rows['order'] as $id => $text) $add->execute([$id, $text]);
}

# Everything the fields unit leaves behind, read fresh from disk and schema: its answer, the manifest, the snapshots, the three columns, the published definitions and the mark
function getFieldState(string $html): array {
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $pref = '`'.PROBEPREF.'_';
    clearstatcache();
    $files = is_array($info);
    foreach ($files ? $info['source'] : [] as $name => $hash) $files = $files && is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $hash;
    $like = 'SELECT field LIKE \'{"field1":"option1","field2":"user %"}\', COUNT(*) FROM '.$pref.'users` WHERE id >= 100 GROUP BY 1';
    return [
        'done' => str_contains($html, _OK) && !str_contains($html, _ERROR),
        'text' => trim(strip_tags($html)),
        'dir' => is_dir($dir),
        'state' => $info['state'] ?? null,
        'cursor' => $info['cursor'] ?? null,
        'count' => $info['count'] ?? null,
        'files' => $files,
        'sealed' => is_array($info) && count($info['target']) === 4 && ($info['target']['fields.php'] ?? '') === hash_file('sha256', CONFIG_DIR.'/fields.php'),
        'users' => $side->query('SELECT id, field FROM '.$pref.'users` WHERE id < 100 ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
        'bulk' => array_map('intval', $side->query($like)->fetchAll(PDO::FETCH_KEY_PAIR)),
        'forum' => $side->query('SELECT id, field FROM '.$pref.'forum` ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
        'order' => $side->query('SELECT id, info FROM '.$pref.'order` ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
        'rules' => (include CONFIG_DIR.'/fields.php')['fields'],
        'mark' => is_file(CONFIG_DIR.'/update.php') ? (include CONFIG_DIR.'/update.php')['update'] : null,
    ];
}

# Run the fields unit once and read what it left
function getFieldRun(): array {
    return getFieldState(setUpdateFields($GLOBALS['pdb'], PROBEPREF));
}

# The clean path and its repeats: a value saved after the site opened is left alone, a lost mark is written again, and nothing of the backup changes
# A site whose definitions are already named and whose columns are empty has nothing to carry over and gets its mark
function getFieldClean(): array {
    setFieldSite();
    $out = ['first' => getFieldRun()];
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $names = ['manifest.json', 'definitions.json', 'account.json', 'forum.json', 'order.json'];
    $kept = array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET field = \'{"field2":"later"}\' WHERE id = 4');
    $out['again'] = getFieldRun();
    unlink(CONFIG_DIR.'/update.php');
    $out['nomark'] = getFieldRun();
    $out['same'] = $kept === array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    $named = $out['first']['rules'];
    setFieldSite(PROBEDEFS, ['points' => '6.3.0'], ['users' => [2 => '', 3 => '', 4 => ''], 'forum' => [], 'order' => []], false);
    setConfigFile('fields.php', $named, [], true);
    $out['named'] = getFieldRun();
    return $out;
}

# The interrupted run: the manifest is put back with the cursor after none, one and two batches of accounts, and the rows behind the cursor are positional again
function getFieldResume(): array {
    $out = [];
    $dir = BASE_DIR.'/storage/backup/update/fields';
    foreach ([0, 500, 1000] as $stop) {
        setFieldSite();
        getFieldRun();
        $keep = (string)file_get_contents($dir.'/manifest.json');
        $list = json_decode((string)file_get_contents($dir.'/account.json'), true);
        $set = getProbeSide()->prepare('UPDATE `'.PROBEPREF.'_users` SET field = ? WHERE id = ?');
        foreach (array_slice($list, $stop) as [$id, $from]) $set->execute([$from, $id]);
        $info = ['state' => $stop ? 'applying' : 'prepared', 'cursor' => ['account' => $stop, 'forum' => 0, 'order' => 0], 'target' => []] + json_decode($keep, true);
        file_put_contents($dir.'/manifest.json', json_encode($info));
        unlink(CONFIG_DIR.'/update.php');
        setConfigFile('fields.php', PROBEDEFS);
        $out['cursor'.$stop] = getFieldRun();
    }
    return $out;
}

# The stops: a mark without a manifest, named definitions over positional rows, a forged snapshot, a stored row that is neither source nor target,
# and every broken row and every broken definition the preflight has to name together without writing anything
function getFieldStop(): array {
    $out = [];
    setFieldSite(PROBEDEFS, ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0']);
    $out['mark'] = getFieldRun();
    setFieldSite();
    $named = getFieldRun()['rules'];
    setFieldSite();
    setConfigFile('fields.php', $named, [], true);
    $out['named'] = getFieldRun();
    setFieldSite();
    getFieldRun();
    $file = BASE_DIR.'/storage/backup/update/fields/manifest.json';
    file_put_contents($file, json_encode(['state' => 'applying'] + json_decode((string)file_get_contents($file), true)));
    unlink(CONFIG_DIR.'/update.php');
    file_put_contents(BASE_DIR.'/storage/backup/update/fields/order.json', '[[1,"Z1||Bob","{\"field1\":\"forged\"}"]]');
    $out['forged'] = getFieldRun();
    setFieldSite();
    getFieldRun();
    $info = ['state' => 'applying', 'cursor' => ['account' => 0, 'forum' => 0, 'order' => 0], 'target' => []] + json_decode((string)file_get_contents($file), true);
    file_put_contents($file, json_encode($info));
    unlink(CONFIG_DIR.'/update.php');
    setConfigFile('fields.php', PROBEDEFS);
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_forum` SET field = \'{"field1":"foreign"}\' WHERE id = 7');
    $out['foreign'] = getFieldRun();
    $rows = ['users' => [2 => 'D|x', 3 => 'A|n||03.02.2001', 4 => 'A|n||||t|extra'], 'forum' => [5 => 'X|L|R', 7 => 'Y'], 'order' => [1 => 'a|0', 2 => "two\nlines"]];
    setFieldSite(PROBEDEFS, ['points' => '6.3.0', 'ratings' => '6.3.0'], $rows);
    $out['rows'] = getFieldRun();
    $defs = ['account' => 'Kind|A,B,A|3|1||Wide|x|7|1||Five|a|b|1|2', 'forum' => 'Born|31.12.2000|5|2', 'order' => PROBEDEFS['order']];
    setFieldSite($defs, ['points' => '6.3.0', 'ratings' => '6.3.0'], ['users' => [2 => '', 3 => '', 4 => ''], 'forum' => [], 'order' => [1 => 'a||b']], false);
    $out['defs'] = getFieldRun();
    return $out;
}

# The sources of a 6.2 site as the configuration step meets them next to the release: each keeps its values in a variable of its own, written as one array or key by key,
# seo carries SEO keys the global of 6.2 also had, header is code that prints, core is code without settings, and db, news and templ have no successor
const CONFOLD = [
    'global' => "\$conf = array (\n  'sitename' => 'Old site',\n  'version' => '6.2.0 Pro',\n  'close' => '0',\n  'language' => 'russian',\n  'module' => 'news,forum',\n"
        ."  'amod' => 'news',\n  'css_f' => 'plugins/jquery/ui/',\n  'sep' => '/',\n  'oldkey' => 'kept',\n"
        ."  'theme' => 'default',\n  'site_logo' => 'mark.svg',\n);",
    'seo' => "\$confse = array();\n\$confse['sep'] = \"-\";\n\$confse['tsep'] = \"_\";",
    'users' => "\$confu = array();\n\$confu['point'] = \"1\";\n\$confu['points'] = \"1,2,3\";\n\$confu['anum'] = \"77\";",
    'stat' => "\$confst = array();\n\$confst['stat'] = \"0\";",
    'uploads' => "\$confup = array();\n\$confup['forum'] = \"gif,png|104857600|1048576|500|500|10|250|10|200|100|1|0\";\n\$confup['typ'] = \"gif,png\";",
    'security' => "\$confs = array('blocker_ip' => '10.1.2.3|3|abc|1784035343|Hack||10.9.9.9|4|0|1784035344|Spam||::1|4|0|1|Odd||', 'blocker_user' => 'bob|1784035343|Spam||');",
    'lang' => "\$confla = array('lang' => 'german', 'key' => 'k1');",
    'fields' => "\$conffi = array();\n\$conffi['account'] = \"Kind|A,B|3|1\";",
    'header' => "echo '<script>probe</script>';",
    'core' => '',
    'db' => "\$confdb = array('name' => 'x');",
    'news' => "\$confn = array('x' => '1');",
    'templ' => "\$conftp = array('gif' => '<img>');",
];

# The shipped sources the configuration step writes over, as the release tracks them
const CONFSHIP = ['global', 'security', 'users', 'fields', 'lang', 'statistic', 'uploads'];

# Put the scratch site back to a 6.2 site copied under the release: the tracked sources of the release, the 6.2 sources beside them, a 6.2 db.php,
# one public module, one panel module and one logo of the shipped theme in the tree, while the theme default of 6.2 is gone
function setConfSite(): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    mkdir(BASE_DIR.'/modules/forum', 0777, true);
    mkdir(BASE_DIR.'/admin/modules', 0777, true);
    touch(BASE_DIR.'/admin/modules/config.php');
    mkdir(BASE_DIR.'/templates/lite/images/logos', 0777, true);
    touch(BASE_DIR.'/templates/lite/images/logos/mark.svg');
    foreach (CONFSHIP as $name) file_put_contents(CONFIG_DIR.'/'.$name.'.php', getConfShip($name));
    $head = "<?php\nif (!defined('FUNC_FILE')) die('Illegal file access');\n\n";
    foreach (CONFOLD as $name => $body) file_put_contents(CONFIG_DIR.'/config_'.$name.'.php', $head.$body."\n\n?>\n");
    file_put_contents(CONFIG_DIR.'/db.php', $head."\$confdb = array (\n  'host' => 'h',\n  'name' => 'olddb',\n  'prefix' => 'sport',\n  'mode' => '1',\n);\n?>");
}

# One source of the release as the current commit tracks it, never the working copy the stand writes into
function getConfShip(string $name): string {
    return (string)shell_exec('git -C '.escapeshellarg(PROBEROOT).' show '.escapeshellarg('HEAD:config/'.$name.'.php').' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
}

# One source of the scratch site read fresh, the global area as it is and every other one by its name
function getConfRead(string $name): mixed {
    $data = (include CONFIG_DIR.'/'.$name.'.php');
    return ($name === 'global') ? $data : ($data[$name] ?? null);
}

# Everything the configuration step leaves behind: its answer, the six sources, what is left of the 6.2 files in config/, what the backup holds and the hashes of config/
function getConfState(string $html): array {
    clearstatcache();
    $hash = [];
    foreach (glob(CONFIG_DIR.'/*.php') ?: [] as $file) $hash[basename($file)] = hash_file('sha256', $file);
    $back = BASE_DIR.'/storage/backup/update/config';
    $ship = [];
    foreach (CONFSHIP as $name) $ship[$name] = eval('?>'.getConfShip($name));
    return [
        'done' => str_contains($html, _OK) && !str_contains($html, _ERROR),
        'text' => trim(strip_tags($html)),
        'leak' => str_contains($html, 'probe</script>'),
        'global' => getConfRead('global'),
        'users' => getConfRead('users'),
        'lang' => getConfRead('lang'),
        'statistic' => getConfRead('statistic'),
        'fields' => getConfRead('fields'),
        'uploads' => getConfRead('uploads'),
        'bans' => array_intersect_key((array)getConfRead('security'), ['blocker_ip' => 0, 'blocker_user' => 0]),
        'ship' => ['version' => $ship['global']['version'], 'css_f' => $ship['global']['css_f'], 'amod' => $ship['global']['amod'], 'theme' => $ship['global']['theme'],
            'users' => array_keys($ship['users']['users']), 'statistic' => $ship['statistic']['statistic']],
        'old' => array_map('basename', glob(CONFIG_DIR.'/config_*.php') ?: []),
        'back' => array_map('basename', glob($back.'/*') ?: []),
        'hash' => $hash,
    ];
}

# The 6.2 configuration of a site goes over the release once: the first run, a repeat with nothing left to carry, a run that meets one source again after a break,
# and the connection settings of the 6.2 db.php the installer reads for its lock and its form
function getConfClean(): array {
    setConfSite();
    $out = ['base' => getSetupBase()];
    $out['first'] = getConfState(setUpdateConfig());
    $out['again'] = getConfState(setUpdateConfig());
    copy(BASE_DIR.'/storage/backup/update/config/config_global.php', CONFIG_DIR.'/config_global.php');
    copy(BASE_DIR.'/storage/backup/update/config/config_seo.php', CONFIG_DIR.'/config_seo.php');
    $out['broken'] = getConfState(setUpdateConfig());
    return $out;
}

# Put the disposable schema back to a 6.2 newsletter: the 6.3 tables with the mails column 6.2 still has, three campaigns, and the mail queue empty
# The first campaign repeats an address and carries one that is none, the third has nobody pending
function setMailSite(): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    $side = getProbeSide();
    foreach (['newsletter', 'mail'] as $name) {
        $side->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_'.$name.'`');
        $side->exec(getProbeTable($name));
    }
    $side->exec('ALTER TABLE `'.PROBEPREF.'_newsletter` ADD `mails` TEXT');
    $side->exec('INSERT INTO `'.PROBEPREF.'_newsletter` (id, title, body, mails) VALUES (1, \'First\', \'\', \'a@probe.test, b@probe.test,a@probe.test,none\'),'
        .' (2, \'Second\', \'\', \'c@probe.test\'), (3, \'Third\', \'\', \'\')');
}

# The schema file of the update drops the mails column; the probe does it in its place
function setMailDrop(): void {
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_newsletter` DROP COLUMN `mails`');
}

# Everything the newsletter step leaves behind: its answer, the manifest, whether the snapshot is there, the queued rows and the state of the three campaigns
function getMailState(string $html): array {
    $dir = BASE_DIR.'/storage/backup/update/newsletter';
    clearstatcache();
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $rows = $side->query('SELECT ref, email, sender, title, kind FROM `'.PROBEPREF.'_mail` ORDER BY ref, email')->fetchAll(PDO::FETCH_NUM);
    return [
        'done' => !str_contains($html, _ERROR),
        'text' => trim(strip_tags($html)),
        'state' => $info['state'] ?? null,
        'count' => $info['count'] ?? null,
        'snap' => is_file($dir.'/recipients.json'),
        'rows' => array_map(fn($v) => [intval($v[0]), $v[1], $v[2], $v[3], $v[4]], $rows),
        'camps' => array_map(fn($v) => array_map('intval', $v), $side->query('SELECT id, status, expect, total FROM `'.PROBEPREF.'_newsletter` ORDER BY id')
            ->fetchAll(PDO::FETCH_NUM)),
    ];
}

# One pass of the newsletter step, before the schema file or after it
function getMailRun(bool $move): array {
    return getMailState(setUpdateMails($GLOBALS['pdb'], PROBEPREF, 'admin@probe.test', $move));
}

# The recipients survive the schema file: the snapshot before it, a break after it and the queue written once, a repeat that writes nothing,
# a break in the middle of the queue that neither loses nor doubles an address, a forged snapshot that stops the step, and a site with nothing pending
function getMailClean(): array {
    setMailSite();
    $out = ['kept' => getMailRun(false)];
    setMailDrop();
    $out['again'] = getMailRun(false);
    $out['moved'] = getMailRun(true);
    $out['repeat'] = getMailRun(true);
    $file = BASE_DIR.'/storage/backup/update/newsletter/manifest.json';
    $info = json_decode((string)file_get_contents($file), true);
    file_put_contents($file, json_encode(['state' => 'prepared'] + $info));
    getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_mail` WHERE email != \'a@probe.test\'');
    $out['half'] = getMailRun(true);
    file_put_contents($file, json_encode(['state' => 'prepared'] + $info));
    file_put_contents(BASE_DIR.'/storage/backup/update/newsletter/recipients.json', '[[1,"First",["x@probe.test"]]]');
    $out['forged'] = getMailRun(true);
    setMailSite();
    setMailDrop();
    $out['none'] = [getMailRun(false), getMailRun(true)];
    return $out;
}

# Put the scratch site back to a 6.2 site copied under the release for the module registry: one panel module, three public ones with node among them,
# the shipped config/modules.php with a record of a module that left the tree, and, when $table is set, the _modules table of 6.2 with an administrator whose rights are numbers
function setModSite(bool $table): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    mkdir(BASE_DIR.'/admin/modules', 0777, true);
    touch(BASE_DIR.'/admin/modules/config.php');
    foreach (['forum', 'shop', 'node', 'extra'] as $name) {
        mkdir(BASE_DIR.'/modules/'.$name, 0777, true);
        touch(BASE_DIR.'/modules/'.$name.'/index.php');
    }
    file_put_contents(CONFIG_DIR.'/modules.php', getConfShip('modules'));
    $mods = (require CONFIG_DIR.'/modules.php')['modules'];
    $mods['news'] = $mods['forum'];
    unset($mods['extra']);
    setConfigFile('modules.php', $mods);
    getProbeSide()->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_modules`');
    getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_admins`');
    if (!$table) return;
    getProbeSide()->exec('CREATE TABLE `'.PROBEPREF.'_modules` (`mid` INT NOT NULL PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `active` TINYINT NOT NULL, `view` TINYINT NOT NULL,'
        .' `inmenu` TINYINT NOT NULL, `mod_group` INT NOT NULL, `blocks` TINYINT NOT NULL, `blocks_c` TINYINT NOT NULL) ENGINE=InnoDB');
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF."_modules` VALUES (1, 'forum', 0, 1, 0, 3, 1, 1), (2, 'shop', 1, 2, 1, 0, 2, 0), (3, 'news', 1, 0, 1, 0, 0, 0)");
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF."_admins` (`id`, `name`, `email`, `modules`) VALUES (1, 'probe', 'probe@probe.test', '1,3,shop,9')");
}

# What the registry step leaves behind: its answer, the records of config/modules.php read fresh, and the rights of the administrator
function getModState(string $html): array {
    clearstatcache();
    $mods = (include CONFIG_DIR.'/modules.php')['modules'] ?? [];
    $keys = ['active', 'view', 'menu', 'group', 'side', 'top'];
    $out = ['text' => $html, 'names' => array_keys($mods), 'ship' => (include PROBEROOT.'/config/modules.php')['modules']];
    foreach ($mods as $name => $row) $out['mods'][$name] = array_map('intval', array_intersect_key($row, array_flip($keys))) + ['lang' => $row['lang'], 'icon' => $row['icon']];
    $out['rights'] = (string)getProbeSide()->query('SELECT modules FROM `'.PROBEPREF.'_admins` WHERE id = 1')->fetchColumn();
    return $out;
}

# The module registry of a 6.2 site with its _modules table, a repeat, and a site without the table; then the preflight of a clean installation,
# which needs a server as new as the update does and no table of its prefix, and the preflight of the update, which needs the users and admins tables of its prefix
function getSetupClean(): array {
    $pdb = $GLOBALS['pdb'];
    setModSite(true);
    $out = ['site' => getModState(setUpdateModules($pdb, PROBEPREF))];
    $out['again'] = getModState(setUpdateModules($pdb, PROBEPREF));
    setModSite(false);
    $out['plain'] = getModState(setUpdateModules($pdb, PROBEPREF));
    $out['fresh'] = ['taken' => checkUpdateBase($pdb, PROBEPREF, true), 'free' => checkUpdateBase($pdb, 'free', true), 'near' => checkUpdateBase($pdb, 'prob', true),
        'wild' => checkUpdateBase($pdb, 'prob_', true)];
    $pdb->fake = '10.5.1-MariaDB';
    $out['fresh']['old'] = checkUpdateBase($pdb, 'free', true);
    $pdb->fake = '';
    $out['update'] = ['real' => checkUpdateBase($pdb, PROBEPREF), 'none' => checkUpdateBase($pdb, 'free')];
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins` TO `'.PROBEPREF.'_admins_off`');
    $out['update']['half'] = checkUpdateBase($pdb, PROBEPREF);
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins_off` TO `'.PROBEPREF.'_admins`');
    return $out;
}

$report = ['error' => '', 'clean' => false, 'runs' => []];

try {
    $units = ['setConfigFile', 'getSetupConfig', 'getSetupBase', 'getInfo', 'checkUpdateBase', 'setUpdateBackup', 'setUpdatePoints', 'setUpdateRatings', 'getUpdateRules',
        'getUpdateValue', 'setUpdateFields', 'setUpdateConfig', 'setUpdateMails', 'setUpdateModules'];
    foreach ($units as $name) addProbeCode($name);
    addProbeSchema();
    $report['runs'] = match ($argv[2] ?? 'points') {
        'ratings' => ['clean' => getRateClean(), 'resume' => getRateResume(), 'stop' => getRateStop(), 'flight' => getRateFlight()],
        'fields' => ['clean' => getFieldClean(), 'resume' => getFieldResume(), 'stop' => getFieldStop()],
        'config' => ['clean' => getConfClean()],
        'mails' => ['clean' => getMailClean()],
        'setup' => ['clean' => getSetupClean()],
        default => ['clean' => getProbeClean(), 'resume' => getProbeResume(), 'stop' => getProbeStop(), 'flight' => getProbeFlight()],
    };
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}

$report['clean'] = deleteProbeSchema();
deleteProbeTree(BASE_DIR);

echo json_encode($report);
