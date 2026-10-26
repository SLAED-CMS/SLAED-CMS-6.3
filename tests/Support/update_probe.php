<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the units of the 6.3 data update in update.php, whose contracts are docs/NODE.md (The 6.3 update) and docs/RATINGS.md
# The second argument names the unit, points when it is left out; every unit shares the schema, the lifted update code and the scratch site
# The update cannot be required from CLI: it is a request handler that acts on load, so its functions are lifted out of the source by name
# BASE_DIR and CONFIG_DIR point into scratch, so the manifest, the snapshot, the mark and every configuration file the unit writes stay away from the site
# Nothing touches the site database either: the probe creates its own schema, works only in it, and drops it again
$probework = str_replace('\\', '/', (string)($argv[1] ?? ''));
if ($probework === '') $probework = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_probe';
define('BASE_DIR', $probework.'/site');
define('CONFIG_DIR', BASE_DIR.'/config');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('BACKUP_DIR', BASE_DIR.'/storage/backup');
define('UPLOADS_DIR', BASE_DIR.'/uploads');
require_once __DIR__.'/probe_boot.php';

# The tree the shipped sources are read from
const PROBEROOT = __DIR__.'/../..';

# The table prefix of the disposable schema
const PROBEPREF = 'probe';

# The balances the snapshot has to carry, the top of the unsigned column among them
const PROBEBAL = [2 => 10, 3 => 0, 4 => 4294967295];

# The rules of a 6.2 site: period, switch and place of two remaining scopes, a zero period among them, and two scopes of modules that left the release
const PROBEOLD = ['account' => '2592000|1|0', 'forum' => '0|0|1', 'shop' => '86400|1|1', 'news' => '2592000|1|0'];

# The rows of the old shared table as id, target, module, time, account and address: two addresses of one account, a guest, two spellings of one IPv6 address
# The rest are a poll, two other events, a row of the shop that left the release, a forum reply that is no target, and an account without an address
const PROBEROWS = [
    [1, 2, 'account', '1000000000', 9, '1.1.1.1'], [2, 2, 'account', '1700000000', 9, '2.2.2.2'], [3, 2, 'account', '1700000100', 0, '3.3.3.3'],
    [4, 5, 'forum', '1700000200', 0, '2001:DB8:0:0:0:0:0:1'], [5, 5, 'forum', '1700000205', 0, '2001:db8::1'], [6, 35, 'voting', '1700000300', 9, '4.4.4.4'],
    [7, 1, 'download', '1700000300', 0, '4.4.4.4'], [8, 1, 'news', '1700000300', 0, '4.4.4.4'], [9, 999, 'shop', '1700000300', 0, '4.4.4.4'],
    [10, 7, 'forum', '1700000300', 0, '4.4.4.4'], [11, 5, 'forum', '1700000400', 9, ''],
];

# The first stage of the update defines the same guard before it loads the database facade on its own
if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
require_once PROBEROOT.'/core/classes/pdo.php';
require_once PROBEROOT.'/core/classes/field.php';
require_once PROBEROOT.'/core/classes/filemanager.php';

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
    function getSqlRow(PDOStatement|int $query_id = 0): ?array {
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
    $text = (string)file_get_contents(PROBEROOT.'/storage/update/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PROBEPREF, $engine, 'utf8mb4', $GLOBALS['pcred']['collate']], $hit[0]);
}

# Lift one function out of public/update.php, or out of public/setup.php for the preflight of a clean installation, into this process; $swap replaces text of its source first
function addProbeCode(string $name, string $file = 'public/update.php', array $swap = []): void {
    $code = (string)file_get_contents(PROBEROOT.'/'.$file);
    $from = strpos($code, 'function '.$name.'(');
    $to = $from === false ? false : strpos($code, "\n}\n", $from);
    if ($from === false || $to === false) throw new RuntimeException($name.'() is gone from '.$file);
    eval(strtr(substr($code, $from, $to - $from + 3), $swap));
}

# The facade the preflight lifted from setup.php connects with, answering the server version $GLOBALS['pfake'] names; the last one stays in $GLOBALS['plast']
function getProbeBase(string $host, string $user, string $pass, string $name, string $collate): ProbeBase {
    $base = $GLOBALS['plast'] = new ProbeBase($host, $user, $pass, $name, $collate);
    $base->fake = (string)($GLOBALS['pfake'] ?? '');
    return $base;
}

# The text of the report rows a unit answered, joined in their order, empty for a unit that answered none
function getProbeText(array $rows): string {
    return implode(' ', array_column($rows, 'text'));
}

# Whether a unit answered at least one row and every row it answered passed
function checkProbeDone(array $rows): bool {
    return $rows !== [] && !checkUpdateFail($rows);
}

# Create the disposable schema with the account table, the journal and three accounts, and connect the project facade to it
function addProbeSchema(): void {
    $root = getProbeSide(true);
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE '.$GLOBALS['pcred']['collate']);
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    foreach (['users', 'admins', 'points', 'forum', 'rating', 'rating_targets', 'rating_actors', 'rating_votes'] as $name) $root->exec(getProbeTable($name));
    foreach (PROBEBAL as $id => $sum) {
        $root->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `sig`, `block`, `warnings`, `field`, `points`)'
            .' VALUES ('.$id.', \'user'.$id.'\', \'user'.$id.'@probe.test\', \'x\', \'\', \'\', \'\', \'\', '.$sum.')');
    }
    $cred = $GLOBALS['pcred'];
    $GLOBALS['pdb'] = new ProbeBase($cred['host'], $cred['uname'], $cred['pass'], $GLOBALS['pname'], $cred['collate']);
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
    setUpdateFile('users.php', ['point' => $point, 'points' => '1,2,3'] + $users);
    getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_points`');
    foreach (PROBEBAL as $id => $sum) getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET points = '.$sum.' WHERE id = '.$id);
}

# Everything the unit leaves behind, read fresh from disk: its answer, the manifest, the snapshot, the mark and the two scopes it carries over
function getProbeState(array $rows): array {
    $dir = BASE_DIR.'/storage/backup/update/points';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $snap = is_file($dir.'/balances.json') ? (string)file_get_contents($dir.'/balances.json') : null;
    clearstatcache();
    $users = (include CONFIG_DIR.'/users.php')['users'];
    $hash = fn(string $name): string => (string)hash_file('sha256', CONFIG_DIR.'/'.$name);
    return [
        'done' => checkProbeDone($rows),
        'text' => getProbeText($rows),
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

# The stops: journal rows without a manifest, a snapshot that left its manifest, a points scope of another shape; a mark without a manifest is done
function getProbeStop(): array {
    $out = [];
    setProbeSite('0');
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_points` (uid, action, scope, source, points) VALUES (2, \'login\', \'account\', \'day:20260921\', 1)');
    $out['rows'] = getProbeRun();
    setProbeSite('0');
    setUpdateFile('update.php', ['points' => '6.3.0']);
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
    setUpdateFile('points.php', $point);
    $out['scope'] = getProbeRun();
    return $out;
}

# The preflight: the stand server, the oldest accepted and the newest refused version of both servers, and a points, Node or private message table on another engine
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
    foreach (['categories', 'voting', 'privat'] as $name) {
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
    setUpdateFile('ratings.php', $rules);
    if ($mark) setUpdateFile('update.php', $mark);
    $side = getProbeSide();
    foreach (['rating_targets', 'rating_actors', 'rating_votes', 'rating', 'forum'] as $name) $side->exec('DELETE FROM `'.PROBEPREF.'_'.$name.'`');
    $side->exec('DELETE FROM `'.PROBEPREF.'_users` WHERE id >= 100');
    $bulk = [];
    for ($i = 100; $i < 1300; $i++) $bulk[] = '('.$i.', \'bulk'.$i.'\', \'bulk'.$i.'@probe.test\', \'x\', \'\', \'\', \'\', \'\', '.($i % 3).', '.(($i % 3) * 4).')';
    $side->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `sig`, `block`, `warnings`, `field`, `votes`, `tvotes`) VALUES '.implode(', ', $bulk));
    foreach ([2 => [10, 37], 3 => [0, 0], 4 => [1, 5]] as $id => [$num, $sum]) $side->exec('UPDATE `'.PROBEPREF.'_users` SET votes = '.$num.', tvotes = '.$sum.' WHERE id = '.$id);
    $side->exec('INSERT INTO `'.PROBEPREF.'_forum` (`id`, `pid`, `uid`, `name`, `title`, `body`, `field`, `score`, `ratings`, `status`) VALUES'
        .' (5, 0, 2, \'user2\', \'topic\', \'\', \'\', 6, 2, 2), (7, 5, 3, \'user3\', \'reply\', \'\', \'\', 5, 1, 2)');
    $add = $side->prepare('INSERT INTO `'.PROBEPREF.'_rating` (`id`, `mid`, `modul`, `time`, `uid`, `ip`) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $row) $add->execute($row);
}

# Everything the ratings unit leaves behind, read fresh from disk and schema: answer, manifest, snapshots, the three new tables, the owners, the old table, the rules and the mark
function getRateState(array $rows): array {
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $list = fn(string $sql): array => array_map(fn($row) => array_map(fn($v) => is_numeric($v) ? intval($v) : $v, $row), $side->query($sql)->fetchAll(PDO::FETCH_NUM));
    $pref = '`'.PROBEPREF.'_';
    clearstatcache();
    $files = is_array($info);
    foreach ($files ? $info['source'] : [] as $name => $hash) $files = $files && is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $hash;
    return [
        'done' => checkProbeDone($rows),
        'text' => getProbeText($rows),
        'dir' => is_dir($dir),
        'state' => $info['state'] ?? null,
        'cursor' => $info['cursor'] ?? null,
        'count' => $info['count'] ?? null,
        'files' => $files,
        'sealed' => is_array($info) && count($info['target']) === 3 && ($info['target']['ratings.php'] ?? '') === hash_file('sha256', CONFIG_DIR.'/ratings.php'),
        'targets' => $list('SELECT scope, mid, base, votes FROM '.$pref.'rating_targets` WHERE mid < 100 ORDER BY scope, mid'),
        'total' => intval($list('SELECT COUNT(*) FROM '.$pref.'rating_targets`')[0][0]),
        'moment' => $list('SELECT DISTINCT created FROM '.$pref.'rating_targets`') === (is_array($info) ? [[date('Y-m-d H:i:s', $info['moment'])]] : []),
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
        .' VALUES (\'account\', 2, \'u:3\', 3, 5, \''.str_repeat('a', 32).'\', NOW())');
    $out['again'] = getRateRun();
    unlink(CONFIG_DIR.'/update.php');
    $out['nomark'] = getRateRun();
    $out['same'] = $kept === array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    $rule = ['active' => '1', 'period' => '86400', 'detail' => '0', 'guests' => '0'];
    setRateSite(['account' => $rule, 'forum' => $rule, 'node.news' => $rule]);
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

# The stops: target rows without a manifest, a forged snapshot, a stored row and an owner that left the snapshot, the broken sources; a mark alone is done
function getRateStop(): array {
    $out = [];
    setRateSite();
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_rating_targets` (scope, mid, base, votes, created) VALUES (\'account\', 2, 42, 11, NOW())');
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
        [20, 2, 'account', '0', 9, '5.5.5.5'], [21, 2, 'account', '9999999999', 9, '6.6.6.6'],
        [22, 2, 'account', '1700000000', 0, '0.0.0.0'], [23, 2, 'account', '1700000000', 0, 'x'],
    ];
    setRateSite(['account' => '100|1|0', 'forum' => '0|2|1'], ['points' => '6.3.0'], array_merge(PROBEROWS, $rows));
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET votes = 0, tvotes = 4 WHERE id = 3');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_forum` SET ratings = 3, score = 2 WHERE id = 5');
    $out['broken'] = getRateRun();
    return $out;
}

# The preflight of the branch names a table of the ratings transactions that is on another engine
function getRateFlight(): array {
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_forum` ENGINE=MyISAM');
    $out = ['engine' => checkUpdateBase($GLOBALS['pdb'], PROBEPREF)];
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_forum` ENGINE=InnoDB');
    return $out;
}

# The definitions of a 6.2 site: a select, a text with a default, a switched off position, a date, a moment and a textarea, with the duty slot as 1, 2, 0 and empty
# An empty slot is written as 0 the way the 6.2 form did, because two pipes in a row already part two positions; only the very last slot of the string can be empty
# The forum keeps a switched off position between two selects, so a row can follow the full or the short layout; the order area belongs to a module that left the release
const PROBEDEFS = [
    'account' => 'Version|A,B,C|3|1||Name|John|1|2||0|0|1|1||Born|0|5|2||Seen|0|4|0||Notes|0|2|',
    'forum' => 'Sys|X,Y|3|2||0|0|1|1||Host|L,R|3|2',
    'order' => 'Wallet|0|1|1||0|0|1|1||First|0|1|2',
];

# The value rows of that site: every type filled, placeholders beside an empty text and a text zero, an empty row, and the full and short forum layout
const PROBEVALS = [
    'users' => [2 => 'B|Änn "Q"||2001-02-03|2020-05-06 07:08|one', 3 => '0|||0|0|0', 4 => ''],
    'forum' => [5 => 'X||R', 7 => 'Y|L', 9 => '0|0|0'],
];

# Put the scratch site and the disposable schema back to a 6.2 installation with extra fields: positional definitions, the marks of the two units before, and positional value rows
# The twelve hundred extra accounts make the unit write its rows in three batches; $bulk = false leaves them without a value
function setFieldSite(array $defs = PROBEDEFS, array $mark = ['points' => '6.3.0', 'ratings' => '6.3.0'], array $rows = PROBEVALS, bool $bulk = true): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    setUpdateFile('fields.php', $defs);
    if ($mark) setUpdateFile('update.php', $mark);
    $side = getProbeSide();
    $side->exec('DELETE FROM `'.PROBEPREF.'_forum`');
    $side->exec('DELETE FROM `'.PROBEPREF.'_users` WHERE id >= 100');
    $list = [];
    for ($i = 100; $i < 1300; $i++) $list[] = '('.$i.', \'bulk'.$i.'\', \'bulk'.$i.'@probe.test\', \'x\', \'\', \'\', \'\', \''.($bulk ? 'A|user '.$i : '').'\')';
    $side->exec('INSERT INTO `'.PROBEPREF.'_users` (`id`, `name`, `email`, `password`, `sig`, `block`, `warnings`, `field`) VALUES '.implode(', ', $list));
    $set = $side->prepare('UPDATE `'.PROBEPREF.'_users` SET field = ? WHERE id = ?');
    foreach ($rows['users'] as $id => $text) $set->execute([$text, $id]);
    $add = $side->prepare('INSERT INTO `'.PROBEPREF.'_forum` (`id`, `pid`, `uid`, `name`, `title`, `body`, `field`, `status`) VALUES (?, 0, 2, \'user2\', \'topic\', \'\', ?, 2)');
    foreach ($rows['forum'] as $id => $text) $add->execute([$id, $text]);
}

# Everything the fields unit leaves behind, read fresh from disk and schema: its answer, the manifest, the snapshots, the two columns, the published definitions and the mark
function getFieldState(array $rows): array {
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $pref = '`'.PROBEPREF.'_';
    clearstatcache();
    $files = is_array($info);
    foreach ($files ? $info['source'] : [] as $name => $hash) $files = $files && is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $hash;
    $like = 'SELECT field LIKE \'{"field1":"option1","field2":"user %"}\', COUNT(*) FROM '.$pref.'users` WHERE id >= 100 GROUP BY 1';
    return [
        'done' => checkProbeDone($rows),
        'text' => getProbeText($rows),
        'dir' => is_dir($dir),
        'state' => $info['state'] ?? null,
        'cursor' => $info['cursor'] ?? null,
        'count' => $info['count'] ?? null,
        'files' => $files,
        'sealed' => is_array($info) && count($info['target']) === 3 && ($info['target']['fields.php'] ?? '') === hash_file('sha256', CONFIG_DIR.'/fields.php'),
        'users' => $side->query('SELECT id, field FROM '.$pref.'users` WHERE id < 100 ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
        'bulk' => array_map('intval', $side->query($like)->fetchAll(PDO::FETCH_KEY_PAIR)),
        'forum' => $side->query('SELECT id, field FROM '.$pref.'forum` ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
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
# A site whose rows need more than its definitions hold grows them: padded captions trimmed, an unknown caption a disabled option, a switched off position an inactive field
function getFieldClean(): array {
    setFieldSite();
    $out = ['first' => getFieldRun()];
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $names = ['manifest.json', 'definitions.json', 'account.json', 'forum.json'];
    $kept = array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_users` SET field = \'{"field2":"later"}\' WHERE id = 4');
    $out['again'] = getFieldRun();
    unlink(CONFIG_DIR.'/update.php');
    $out['nomark'] = getFieldRun();
    $out['same'] = $kept === array_map(fn($v) => file_get_contents($dir.'/'.$v), $names);
    $named = $out['first']['rules'];
    setFieldSite(PROBEDEFS, ['points' => '6.3.0'], ['users' => [2 => '', 3 => '', 4 => ''], 'forum' => []], false);
    setUpdateFile('fields.php', $named, [], true);
    $out['named'] = getFieldRun();
    $defs = ['account' => 'Version | A , B |3|1||0|0|1|1||Note|0|1|2', 'forum' => PROBEDEFS['forum'], 'order' => PROBEDEFS['order']];
    $rows = ['users' => [2 => 'C|hidden|n', 3 => ' B ', 4 => 'C|0|m'], 'forum' => [5 => 'X|L|R', 7 => 'Y|L']];
    setFieldSite($defs, ['points' => '6.3.0', 'ratings' => '6.3.0'], $rows, false);
    $out['grow'] = getFieldRun();
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
        $info = ['state' => $stop ? 'applying' : 'prepared', 'cursor' => ['account' => $stop, 'forum' => 0], 'target' => []] + json_decode($keep, true);
        file_put_contents($dir.'/manifest.json', json_encode($info));
        unlink(CONFIG_DIR.'/update.php');
        setUpdateFile('fields.php', PROBEDEFS);
        $out['cursor'.$stop] = getFieldRun();
    }
    return $out;
}

# A mark without a manifest, which is done, and the stops: named definitions over positional rows, a forged snapshot, a stored row that is neither source nor target
# Every broken row and every broken definition is one the preflight has to name together without writing anything
function getFieldStop(): array {
    $out = [];
    setFieldSite(PROBEDEFS, ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0']);
    $out['mark'] = getFieldRun();
    setFieldSite();
    $named = getFieldRun()['rules'];
    setFieldSite();
    setUpdateFile('fields.php', $named, [], true);
    $out['named'] = getFieldRun();
    setFieldSite();
    getFieldRun();
    $file = BASE_DIR.'/storage/backup/update/fields/manifest.json';
    file_put_contents($file, json_encode(['state' => 'applying'] + json_decode((string)file_get_contents($file), true)));
    unlink(CONFIG_DIR.'/update.php');
    file_put_contents(BASE_DIR.'/storage/backup/update/fields/forum.json', '[[7,"Y|L","{\"field1\":\"forged\"}"]]');
    $out['forged'] = getFieldRun();
    setFieldSite();
    getFieldRun();
    $info = ['state' => 'applying', 'cursor' => ['account' => 0, 'forum' => 0], 'target' => []] + json_decode((string)file_get_contents($file), true);
    file_put_contents($file, json_encode($info));
    unlink(CONFIG_DIR.'/update.php');
    setUpdateFile('fields.php', PROBEDEFS);
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_forum` SET field = \'{"field1":"foreign"}\' WHERE id = 7');
    $out['foreign'] = getFieldRun();
    $rows = ['users' => [2 => 'D|x', 3 => 'A|n||03.02.2001', 4 => 'A|n||||t|extra'], 'forum' => [5 => 'X|L|R', 7 => 'Y', 8 => 'a|0', 9 => "two\nlines"]];
    setFieldSite(['forum' => 'Wallet|0|1|1||0|0|1|1||First|0|1|2'] + PROBEDEFS, ['points' => '6.3.0', 'ratings' => '6.3.0'], $rows);
    $out['rows'] = getFieldRun();
    $defs = ['account' => 'Kind|A,B,A|3|1||Wide|x|7|1||Five|a|b|1|2', 'forum' => 'Born|31.12.2000|5|2', 'order' => PROBEDEFS['order']];
    setFieldSite($defs, ['points' => '6.3.0', 'ratings' => '6.3.0'], ['users' => [2 => '', 3 => '', 4 => ''], 'forum' => [5 => 'a||b']], false);
    $out['defs'] = getFieldRun();
    return $out;
}

# The sources of a 6.2 site as the configuration step meets them next to the release: each keeps its values in its own variable, as one array or key by key
# The source seo carries SEO keys the global of 6.2 also had, header is code that prints, core is code without settings, and db, news and templ have no successor
const CONFOLD = [
    'global' => "\$conf = array (\n  'sitename' => 'Old site',\n  'version' => '6.2.0 Pro',\n  'close' => '0',\n  'language' => 'russian',\n  'module' => 'news,forum',\n"
        ."  'amod' => 'news',\n  'css_f' => 'plugins/jquery/ui/',\n  'script_a' => '1',\n  'script_b' => '1',\n"
        ."  'sep' => '/',\n  'rewrite' => '0',\n  'title' => '0',\n  'ltitle' => '0',\n"
        ."  'oldkey' => 'kept',\n  'clickable' => '1',\n  'forum' => '0',\n"
        ."  'theme' => 'default',\n  'site_logo' => 'mark.svg',\n);",
    'seo' => "\$confse = array();\n\$confse['sep'] = \"-\";\n\$confse['tsep'] = \"_\";\n\$confse['rewrite'] = \"1\";\n\$confse['ctitle'] = \"0\";\n\$confse['ltitle'] = \"1\";",
    'comments' => "\$confcom = array('alink' => '1', 'link' => '2');",
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

# The shipped sources the configuration step writes over, and forum, whose area a key of the 6.2 global names, as the release tracks them
const CONFSHIP = ['global', 'security', 'users', 'fields', 'lang', 'statistic', 'uploads', 'forum', 'comments'];

# Put the scratch site back to a 6.2 site copied under the release: the tracked sources of the release, the 6.2 sources beside them and a 6.2 db.php
# The tree holds one public module, one panel module and one logo of the shipped theme, while the theme default of 6.2 is gone
function setConfSite(): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    mkdir(BASE_DIR.'/modules/forum', 0777, true);
    mkdir(BASE_DIR.'/admin/modules', 0777, true);
    touch(BASE_DIR.'/admin/modules/config.php');
    mkdir(PUBLIC_DIR.'/templates/lite/images/logos', 0777, true);
    touch(PUBLIC_DIR.'/templates/lite/images/logos/mark.svg');
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
function getConfState(array $rows): array {
    clearstatcache();
    $hash = [];
    foreach (glob(CONFIG_DIR.'/*.php') ?: [] as $file) $hash[basename($file)] = hash_file('sha256', $file);
    $back = BASE_DIR.'/storage/backup/update/config';
    $ship = [];
    foreach (CONFSHIP as $name) $ship[$name] = eval('?>'.getConfShip($name));
    return [
        'done' => checkProbeDone($rows),
        'text' => getProbeText($rows),
        'leak' => str_contains(getProbeText($rows), 'probe</script>'),
        'global' => getConfRead('global'),
        'users' => getConfRead('users'),
        'comments' => getConfRead('comments'),
        'lang' => getConfRead('lang'),
        'statistic' => getConfRead('statistic'),
        'fields' => getConfRead('fields'),
        'uploads' => getConfRead('uploads'),
        'bans' => array_intersect_key((array)getConfRead('security'), ['blocker_ip' => 0, 'blocker_user' => 0]),
        'ship' => ['version' => $ship['global']['version'], 'css_f' => $ship['global']['css_f'], 'amod' => $ship['global']['amod'] ?? null, 'theme' => $ship['global']['theme'],
            'users' => array_keys($ship['users']['users']), 'statistic' => $ship['statistic']['statistic']],
        'old' => array_map('basename', glob(CONFIG_DIR.'/config_*.php') ?: []),
        'back' => array_map('basename', glob($back.'/*') ?: []),
        'hash' => $hash,
    ];
}

# The 6.2 configuration of a site goes over the release once: the first run, a repeat with nothing left to carry, a run that meets one source again after a break
# It also reports the connection settings of the 6.2 db.php the first stage of the update reads for its run
function getConfClean(): array {
    setConfSite();
    $out = ['base' => getUpdateCred()];
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
function getMailState(array $said): array {
    $dir = BASE_DIR.'/storage/backup/update/newsletter';
    clearstatcache();
    $info = is_file($dir.'/manifest.json') ? json_decode((string)file_get_contents($dir.'/manifest.json'), true) : null;
    $side = getProbeSide();
    $rows = $side->query('SELECT ref, email, sender, title, kind FROM `'.PROBEPREF.'_mail` ORDER BY ref, email')->fetchAll(PDO::FETCH_NUM);
    return [
        'done' => !checkUpdateFail($said),
        'text' => getProbeText($said),
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

# The recipients survive the schema file: the snapshot before it, a break after it and the queue written once, a repeat that writes nothing
# A break in the middle of the queue neither loses nor doubles an address, a forged snapshot stops the step, and a site with nothing pending is covered too
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

# Put the scratch site back to a 6.2 site copied under the release for the module registry: one panel module, three public ones with node among them
# The shipped config/modules.php keeps a record of a module that left the tree, and $table adds the _modules table of 6.2 with an administrator whose rights are numbers
function setModSite(bool $table): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    mkdir(BASE_DIR.'/admin/modules', 0777, true);
    touch(BASE_DIR.'/admin/modules/config.php');
    foreach (['forum', 'voting', 'node', 'extra'] as $name) {
        mkdir(BASE_DIR.'/modules/'.$name, 0777, true);
        touch(BASE_DIR.'/modules/'.$name.'/index.php');
    }
    file_put_contents(CONFIG_DIR.'/modules.php', getConfShip('modules'));
    $mods = (require CONFIG_DIR.'/modules.php')['modules'];
    $mods['news'] = $mods['forum'];
    unset($mods['extra']);
    setUpdateFile('modules.php', $mods);
    getProbeSide()->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_modules`');
    getProbeSide()->exec('DELETE FROM `'.PROBEPREF.'_admins`');
    if (!$table) return;
    getProbeSide()->exec('CREATE TABLE `'.PROBEPREF.'_modules` (`mid` INT NOT NULL PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `active` TINYINT NOT NULL, `view` TINYINT NOT NULL,'
        .' `inmenu` TINYINT NOT NULL, `mod_group` INT NOT NULL, `blocks` TINYINT NOT NULL, `blocks_c` TINYINT NOT NULL) ENGINE=InnoDB');
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF."_modules` VALUES (1, 'forum', 0, 1, 0, 3, 1, 1), (2, 'voting', 1, 2, 1, 0, 2, 0), (3, 'news', 1, 0, 1, 0, 0, 0)");
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF."_admins` (`id`, `name`, `email`, `password`, `modules`) VALUES (1, 'probe', 'probe@probe.test', 'x', '1,3,voting,9')");
}

# What the registry step leaves behind: its answer, the records of config/modules.php read fresh, and the rights of the administrator
function getModState(array $rows): array {
    clearstatcache();
    $mods = (include CONFIG_DIR.'/modules.php')['modules'] ?? [];
    $keys = ['active', 'view', 'menu', 'group', 'side', 'top'];
    $out = ['text' => getProbeText($rows), 'names' => array_keys($mods), 'ship' => (include PROBEROOT.'/config/modules.php')['modules']];
    foreach ($mods as $name => $row) $out['mods'][$name] = array_map('intval', array_intersect_key($row, array_flip($keys))) + ['lang' => $row['lang'], 'icon' => $row['icon']];
    $out['rights'] = (string)getProbeSide()->query('SELECT modules FROM `'.PROBEPREF.'_admins` WHERE id = 1')->fetchColumn();
    return $out;
}

# The preflight of a clean installation, lifted from setup.php: a taken prefix, a free one, one that only starts like a taken one and one holding the _ LIKE takes for any character
# Then the oldest accepted and the newest refused version of both servers, answers without a database name, and its session at +13 and at the +14 MariaDB refuses as an offset
function getFreshFlight(): array {
    $cred = $GLOBALS['pcred'];
    $base = ['host' => $cred['host'], 'uname' => $cred['uname'], 'pass' => $cred['pass'], 'name' => $GLOBALS['pname'], 'collate' => $cred['collate']];
    $out = [];
    foreach (['taken' => PROBEPREF, 'free' => 'free', 'near' => 'prob', 'wild' => 'prob_'] as $key => $pref) $out[$key] = getSetupProbe(['prefix' => $pref] + $base)['fail'];
    foreach (['10.5.1-MariaDB', '10.5.2-MariaDB', '8.0.15', '8.0.16'] as $ver) {
        $GLOBALS['pfake'] = $ver;
        $out['server'][$ver] = getSetupProbe(['prefix' => 'free'] + $base)['fail'];
    }
    $GLOBALS['pfake'] = '';
    $out['none'] = getSetupProbe(['name' => '', 'prefix' => 'free'] + $base)['fail'];
    $zone = date_default_timezone_get();
    foreach (['Pacific/Tongatapu', 'Pacific/Kiritimati'] as $name) {
        date_default_timezone_set($name);
        getSetupProbe(['prefix' => 'free'] + $base);
        $row = $GLOBALS['plast']->getSqlRow($GLOBALS['plast']->getSqlQuery('SELECT NOW(), @@session.sql_mode, @@session.collation_connection'));
        $out['link'][$name] = ['skew' => abs(strtotime((string)$row[0]) - time()), 'mode' => explode(',', (string)$row[1]), 'coll' => $row[2], 'want' => $cred['collate']];
    }
    date_default_timezone_set($zone);
    return $out;
}

# The shipped users and maildead tables in the collation setup.php writes: two emoji names and two addresses apart only in case, each stored and found once
function getTextFlight(): array {
    preg_match("#'collate' => '(utf8mb4_[a-z0-9_]+)'#", (string)file_get_contents(PROBEROOT.'/public/setup.php'), $hit);
    $pdo = getProbeSide();
    foreach (['users', 'maildead'] as $name) $pdo->exec(str_replace([PROBEPREF.'_', 'COLLATE='.$GLOBALS['pcred']['collate']], ['txt_', 'COLLATE='.$hit[1]], getProbeTable($name)));
    $add = function (string $sql) use ($pdo): bool {
        try {
            return $pdo->exec($sql) === 1;
        } catch (PDOException) {
            return false;
        }
    };
    $user = 'INSERT INTO txt_users (name, email, password, sig, block, warnings, field) VALUES (%s, NULL, \'x\', \'\', \'\', \'\', \'\')';
    $out = ['coll' => $hit[1], 'names' => [], 'found' => [], 'mails' => []];
    foreach (['😀', '😃'] as $name) {
        $out['names'][] = $add(sprintf($user, $pdo->quote($name)));
        $out['found'][] = (int)$pdo->query('SELECT COUNT(*) FROM txt_users WHERE name = '.$pdo->quote($name))->fetchColumn();
    }
    foreach (['Probe@Mail.test', 'probe@mail.test'] as $mail) $out['mails'][] = $add('INSERT INTO txt_maildead (email) VALUES ('.$pdo->quote($mail).')');
    $pdo->exec('DROP TABLE txt_users, txt_maildead');
    return $out;
}

# The module registry of a 6.2 site with its _modules table, a repeat, a repeat after the mark whose owner switched forum on in between, and a site without the table
# The types of config/node.php the update takes out are checked while it keeps the registered ones
# The preflight of the update needs the users and admins tables of its prefix and refuses an admins or newsletter table outside InnoDB; the one of setup.php is checked beside it
# The faults of the update come last: an admins table the registry cannot read and an uploads.php the type step cannot write
# A read-only ratings.php keeps the ratings unit from its manifest seal and its mark until the permissions are fixed
function getSetupClean(): array {
    $pdb = $GLOBALS['pdb'];
    setModSite(true);
    $out = ['site' => getModState(setUpdateModules($pdb, PROBEPREF))];
    $out['again'] = getModState(setUpdateModules($pdb, PROBEPREF));
    $mods = (include CONFIG_DIR.'/modules.php')['modules'];
    $mods['forum']['active'] = '1';
    setUpdateFile('modules.php', $mods);
    $out['owned'] = getModState(setUpdateModules($pdb, PROBEPREF, false));
    $pack = ['node' => ['types' => ['content' => ['version' => 2], 'docs' => ['version' => 2], 'news' => ['version' => 1]]],
        'fields' => ['account' => [], 'node' => ['content' => ['f' => []], 'docs' => ['f' => []]]], 'uploads' => ['all' => 'x', 'content' => 'x', 'docs' => 'x', 'news' => 'x'],
        'ratings' => ['account' => [], 'node.content' => [], 'node.docs' => [], 'node.news' => []]];
    foreach ($pack as $name => $data) setUpdateFile($name.'.php', $data, [], true);
    $gone = deleteUpdateTypes(['docs', 'other']);
    $read = fn(string $name): array => array_keys(getUpdateSource(CONFIG_DIR.'/'.$name.'.php')[$name] ?? []);
    $out['types'] = ['gone' => $gone, 'node' => array_keys(getUpdateSource(CONFIG_DIR.'/node.php')['node']['types'] ?? []), 'fields' => $read('fields'),
        'node.fields' => array_keys(getUpdateSource(CONFIG_DIR.'/fields.php')['fields']['node'] ?? []), 'uploads' => $read('uploads'), 'ratings' => $read('ratings'),
        'again' => deleteUpdateTypes(['docs'])];
    setModSite(false);
    $out['plain'] = getModState(setUpdateModules($pdb, PROBEPREF));
    $out['fresh'] = getFreshFlight();
    $out['text'] = getTextFlight();
    $out['update'] = ['real' => checkUpdateBase($pdb, PROBEPREF), 'none' => checkUpdateBase($pdb, 'free')];
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins` TO `'.PROBEPREF.'_admins_off`');
    $out['update']['half'] = checkUpdateBase($pdb, PROBEPREF);
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins_off` TO `'.PROBEPREF.'_admins`');
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_admins` ENGINE=MyISAM');
    getProbeSide()->exec('CREATE TABLE `'.PROBEPREF.'_newsletter` (`id` INT NOT NULL PRIMARY KEY) ENGINE=MyISAM');
    $out['update']['engine'] = checkUpdateBase($pdb, PROBEPREF);
    getProbeSide()->exec('DROP TABLE `'.PROBEPREF.'_newsletter`');
    getProbeSide()->exec('ALTER TABLE `'.PROBEPREF.'_admins` ENGINE=InnoDB');
    setModSite(true);
    $was = sha1_file(CONFIG_DIR.'/modules.php');
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins` TO `'.PROBEPREF.'_admins_off`');
    $rows = setUpdateModules($pdb, PROBEPREF);
    getProbeSide()->exec('RENAME TABLE `'.PROBEPREF.'_admins_off` TO `'.PROBEPREF.'_admins`');
    $out['fault'] = ['text' => getProbeText($rows), 'red' => checkUpdateFail($rows), 'same' => sha1_file(CONFIG_DIR.'/modules.php') === $was];
    foreach ($pack as $name => $data) setUpdateFile($name.'.php', $data, [], true);
    chmod(CONFIG_DIR.'/uploads.php', 0444);
    $gone = deleteUpdateTypes(['docs']);
    chmod(CONFIG_DIR.'/uploads.php', 0644);
    $out['types']['locked'] = [$gone, array_keys(getUpdateSource(CONFIG_DIR.'/node.php')['node']['types'] ?? []), deleteUpdateTypes(['docs'])];
    setRateSite();
    chmod(CONFIG_DIR.'/ratings.php', 0444);
    $out['locked'] = ['first' => getRateRun()];
    chmod(CONFIG_DIR.'/ratings.php', 0644);
    $out['locked']['again'] = getRateRun();
    return $out;
}

# The files of the catch-up site: the archive and the old folder of news, the archive of pages, and one name the docs folder already holds
const ARCHFILE = ['archive/news/a.gif', 'archive/news/e.gif', 'news/b.png', 'archive/pages/c.jpg', 'node/docs/d.png'];

# The texts of the catch-up site as table, id and text: news 1 and docs 2 are published, news 3 a draft; comment 2 belongs to a material that is gone
const ARCHTEXT = [
    ['nodes', 1, 'intro', '[img=left alt=A]./uploads/archive/news/a.gif[/img]'],
    ['nodes', 1, 'body', '[url]http://www.slaed.net/uploads/news/b.png[/url] [img]./uploads/archive/news/gone.gif[/img] [usehtml]<img src="uploads/archive/news/a.gif">[/usehtml]'],
    ['nodes', 2, 'body', '![C](uploads/archive/pages/c.jpg) [img]/uploads/docs/d.png[/img]'],
    ['nodes', 3, 'intro', '[img]./uploads/archive/news/e.gif[/img]'],
    ['comment', 1, 'body', '[img=center alt=title]./uploads/archive/news/a.gif[/img]'],
    ['comment', 2, 'body', '[img]./uploads/archive/news/a.gif[/img]'],
    ['forum', 1, 'body', '[img=center alt=T]http://www.slaed.net/uploads/archive/news/a.gif[/img]'],
    ['forum', 2, 'body', '[img]http://other.net/uploads/archive/news/a.gif[/img] [code]uploads/archive/news/a.gif[/code]'],
    ['privat', 1, 'body', '[url=./uploads/archive/pages/c.jpg]C[/url]'],
    ['message', 1, 'body', '[img]./uploads/archive/news/e.gif[/img]'],
];

# Put the scratch site back to a 6.3 site of early October: the files of ARCHFILE, the two types, the texts of ARCHTEXT and no manifest of the unit
# The tables of blocks and polls are left out, so the unit meets a site without them
function setArchSite(): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    foreach (ARCHFILE as $rel) {
        if (!is_dir(dirname(UPLOADS_DIR.'/'.$rel))) mkdir(dirname(UPLOADS_DIR.'/'.$rel), 0777, true);
        file_put_contents(UPLOADS_DIR.'/'.$rel, 'x');
    }
    $side = getProbeSide();
    foreach (['comment', 'nodes', 'node_types', 'privat', 'message', 'newsletter'] as $name) $side->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_'.$name.'`');
    foreach (['node_types', 'nodes', 'comment', 'privat', 'message', 'newsletter'] as $name) $side->exec(getProbeTable($name));
    $side->exec('DELETE FROM `'.PROBEPREF.'_forum`');
    $side->exec('INSERT INTO `'.PROBEPREF.'_node_types` (id, name, title, intro) VALUES (1, \'news\', \'News\', \'\'), (2, \'docs\', \'Docs\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_nodes` (id, tid, title, intro, body, field, status) VALUES (1, 1, \'A\', \'\', \'\', \'\', 2), (2, 2, \'B\', \'\', \'\', \'\', 2),'
        .' (3, 1, \'C\', \'\', \'\', \'\', 0)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_comment` (id, modul, cid, name, body, status) VALUES (1, \'news\', 1, \'u\', \'\', 1), (2, \'oldnews\', 9, \'u\', \'\', 1)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_forum` (id, name, title, body, field) VALUES (1, \'u\', \'T\', \'\', \'\'), (2, \'u\', \'T\', \'\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_privat` (id, title, body) VALUES (1, \'P\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_message` (id, title, body) VALUES (1, \'M\', \'\')');
    foreach (ARCHTEXT as [$tab, $id, $col, $text]) {
        $side->prepare('UPDATE `'.PROBEPREF.'_'.$tab.'` SET `'.$col.'` = :text WHERE id = :id')->execute(['text' => $text, 'id' => $id]);
    }
}

# Everything the catch-up leaves behind: its answer, the manifest and every text of ARCHTEXT read back
function getArchState(array $rows): array {
    clearstatcache();
    $file = BACKUP_DIR.'/update/archive/manifest.json';
    $texts = [];
    foreach (ARCHTEXT as [$tab, $id, $col]) {
        $texts[$tab.'.'.$col.' #'.$id] = (string)getProbeSide()->query('SELECT `'.$col.'` FROM `'.PROBEPREF.'_'.$tab.'` WHERE id = '.$id)->fetchColumn();
    }
    return ['done' => checkProbeDone($rows), 'text' => getProbeText($rows), 'info' => is_file($file) ? json_decode((string)file_get_contents($file), true) : null,
        'texts' => $texts];
}

# One pass of the catch-up as the first stage runs it, a dry run or a write run
function getArchRun(bool $dry): array {
    return getArchState(setUpdateArchive($GLOBALS['pdb'], PROBEPREF, $dry));
}

# The catch-up counts without writing, rewrites once, skips a verified repeat and continues after the cursor of a run that stopped
function getArchClean(): array {
    $GLOBALS['conf']['homeurl'] = 'https://slaed.net';
    setArchSite();
    $out = ['dry' => getArchRun(true), 'write' => getArchRun(false), 'repeat' => getArchRun(false)];
    $file = BACKUP_DIR.'/update/archive/manifest.json';
    $info = json_decode((string)file_get_contents($file), true);
    $info['state'] = 'applying';
    $info['cursor'] = ['nodes' => 1];
    file_put_contents($file, json_encode($info));
    foreach (ARCHTEXT as [$tab, $id, $col, $text]) {
        if ($tab === 'nodes' && $id === 1) continue;
        getProbeSide()->prepare('UPDATE `'.PROBEPREF.'_'.$tab.'` SET `'.$col.'` = :text WHERE id = :id')->execute(['text' => $text, 'id' => $id]);
    }
    $out['resume'] = getArchRun(false);
    $info = json_decode((string)file_get_contents($file), true);
    $info['state'] = 'applying';
    $info['cursor'] = ['nodes' => 99, 'comment' => 99, 'forum' => 99, 'privat' => 99, 'message' => 99, 'newsletter' => 99];
    file_put_contents($file, json_encode($info));
    foreach (ARCHTEXT as [$tab, $id, $col, $text]) {
        getProbeSide()->prepare('UPDATE `'.PROBEPREF.'_'.$tab.'` SET `'.$col.'` = :text WHERE id = :id')->execute(['text' => $text, 'id' => $id]);
    }
    $out['stuck'] = getArchRun(false);
    $out['unstuck'] = getArchRun(false);
    return $out;
}

# Put the scratch site back to a 6.3 site with keys no code reads and a shipped config/ in storage/backup/release that has options the site lacks
function setMergeSite(bool $pack): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    setUpdateFile('global.php', ['homeurl' => 'https://slaed.net', 'rewrite' => '1', 'css_f' => 'own.css', 'css_c' => '1', 'script_f' => 'own.js', 'script_a' => '1',
        'cache_b' => '1', 'version' => '6.3.0', 'clickable' => '1']);
    setUpdateFile('users.php', ['anonym' => 'Guest', 'point' => '1', 'keep' => 'site']);
    setUpdateFile('comments.php', ['alink' => '2', 'link' => '1']);
    setUpdateFile('node.php', ['types' => ['news' => ['own' => 1]], 'limits' => ['edit' => 600]], [], true);
    if (!$pack) return;
    $ship = BACKUP_DIR.'/release/config';
    mkdir($ship, 0777, true);
    $put = fn(string $name, array $data): int|false => file_put_contents($ship.'/'.$name.'.php', '<?php'."\n".'return '.var_export($data, true).';'."\n");
    $put('global', ['homeurl' => '', 'css_f' => '', 'script_f' => 'ship.js', 'webroot' => '']);
    $put('users', ['users' => ['keep' => 'ship', 'fresh' => '1']]);
    $put('node', ['node' => ['types' => ['news' => ['own' => 2, 'more' => 3], 'faq' => ['own' => 1]], 'defaults' => ['list' => ['views' => ['list']]]]]);
    $put('oauth', ['oauth' => ['google' => ['client' => '']]]);
    $put('system', ['system' => ['skip' => '1']]);
}

# The settings step without the shipped folder, the merge of a 6.3 site and a repeat that adds nothing
function getMergeClean(): array {
    setMergeSite(false);
    $out = ['none' => getProbeText(setUpdateMerge()), 'nonered' => checkUpdateFail(setUpdateMerge())];
    setMergeSite(true);
    $rows = setUpdateMerge();
    $read = fn(string $name): array => (array)(($name === 'global') ? getUpdateSource(CONFIG_DIR.'/global.php') : (getUpdateSource(CONFIG_DIR.'/'.$name.'.php')[$name] ?? []));
    $out['first'] = ['done' => checkProbeDone($rows), 'text' => getProbeText($rows), 'global' => $read('global'), 'users' => $read('users'), 'node' => $read('node'),
        'oauth' => $read('oauth'), 'comments' => $read('comments'), 'system' => is_file(CONFIG_DIR.'/system.php')];
    $out['again'] = getProbeText(setUpdateMerge());
    return $out;
}

# Put the schema back to a 6.3 site of early October with its source tables: news carried, its pending row in a cp1251 table matched by title and time
# Help has a reply, faq a row no address names, files a pending row whose material has another title, pages is empty, and two tables of gone modules follow
function setTableSite(): void {
    $side = getProbeSide();
    foreach (['node_legacy', 'nodes', 'node_types', 'news', 'help', 'faq', 'files', 'pages', 'order', 'whois'] as $name) {
        $side->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_'.$name.'`');
    }
    foreach (['node_types', 'nodes', 'node_legacy'] as $name) $side->exec(getProbeTable($name));
    $side->exec('CREATE TABLE `'.PROBEPREF.'_news` (id INT UNSIGNED NOT NULL PRIMARY KEY, title VARCHAR(100) NOT NULL, time DATETIME, status TINYINT NOT NULL)'
        .' DEFAULT CHARSET=cp1251');
    $side->exec('CREATE TABLE `'.PROBEPREF.'_help` (id INT UNSIGNED NOT NULL PRIMARY KEY, pid INT UNSIGNED NOT NULL, title VARCHAR(100) NOT NULL, time DATETIME,'
        .' status TINYINT NOT NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach (['faq', 'files', 'pages'] as $name) {
        $side->exec('CREATE TABLE `'.PROBEPREF.'_'.$name.'` (id INT UNSIGNED NOT NULL PRIMARY KEY, title VARCHAR(100) NOT NULL, time DATETIME, status TINYINT NOT NULL)'
            .' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    foreach (['order', 'whois'] as $name) $side->exec('CREATE TABLE `'.PROBEPREF.'_'.$name.'` (id INT UNSIGNED NOT NULL PRIMARY KEY)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_order` (id) VALUES (1)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_node_types` (id, name, title, intro) VALUES (1, \'news\', \'News\', \'\'), (2, \'help\', \'Help\', \'\'),'
        .' (3, \'files\', \'Files\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_nodes` (id, tid, title, intro, body, field, status, created)'
        .' VALUES (1, 1, \'Опубликовано\', \'\', \'\', \'\', 2, \'2014-07-01 10:00:00\'), (2, 1, \'Ждёт\', \'\', \'\', \'\', 1, \'2014-07-05 14:31:45\'),'
        .' (3, 2, \'Q\', \'\', \'\', \'\', 2, \'2015-01-01 10:00:00\'), (4, 3, \'Another\', \'\', \'\', \'\', 1, \'2016-01-01 10:00:00\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_node_legacy` (modul, oid, nid) VALUES (\'news\', 10, 1), (\'help\', 30, 3)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_news` (id, title, time, status) VALUES (10, \'Опубликовано\', \'2014-07-01 10:00:00\', 1),'
        .' (11, \'Ждёт \', \'2014-07-05 14:31:45\', 0)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_help` (id, pid, title, time, status) VALUES (30, 0, \'Q\', \'2015-01-01 10:00:00\', 0), (31, 30, \'A\', \'2015-01-02 10:00:00\', 1)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_faq` (id, title, time, status) VALUES (20, \'F\', \'2015-01-01 10:00:00\', 1)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_files` (id, title, time, status) VALUES (40, \'A file\', \'2016-01-01 10:00:00\', 0)');
}

# The tables of the catch-up site the drop leaves, without the prefix
function getTableLeft(): array {
    $list = getProbeSide()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $list = array_map(fn(string $v): string => substr($v, strlen(PROBEPREF) + 1), $list);
    $out = array_values(array_intersect($list, ['news', 'help', 'faq', 'files', 'pages', 'order', 'whois']));
    sort($out);
    return $out;
}

# The drop of the first stage on the catch-up site, its repeat, and a run after the rows that kept two tables are in Node
function getTableClean(): array {
    setTableSite();
    $out = ['first' => ['rows' => setUpdateTables($GLOBALS['pdb'], PROBEPREF), 'left' => getTableLeft()]];
    $out['again'] = ['rows' => setUpdateTables($GLOBALS['pdb'], PROBEPREF), 'left' => getTableLeft()];
    getProbeSide()->exec('INSERT INTO `'.PROBEPREF.'_node_legacy` (modul, oid, nid) VALUES (\'faq\', 20, 1)');
    getProbeSide()->exec('UPDATE `'.PROBEPREF.'_nodes` SET title = \'A file\' WHERE id = 4');
    $out['last'] = ['rows' => setUpdateTables($GLOBALS['pdb'], PROBEPREF), 'left' => getTableLeft()];
    return $out;
}

# The texts of the text site as table, id, column and stored text: node 1 and the help reply comment 2 are Markdown by origin, forum 2 is a post of 2026
# Forum 1 carries a textarea field, the own block of account 3 is too long for its column once escaped
const WORDROWS = [
    ['nodes', 1, 'body', "[b]Bold[/b] text\n- item *05"],
    ['comment', 1, 'body', "[quote]Q[/quote]<br />\n# not a heading *05"],
    ['comment', 2, 'body', '**md** [b]x[/b]'],
    ['forum', 1, 'body', "[url=http://a.test/c_d]a_b[/url]<br />\nnext &amp; 2*3"],
    ['forum', 1, 'field', '{"note":"[b]n[/b]","size":"[b]s[/b]"}'],
    ['forum', 2, 'body', '**Markdown** of 2026 [b]x[/b]'],
    ['privat', 1, 'body', '[code]<b>&lt;x&gt;</b>[/code]'],
    ['message', 1, 'body', '[center]C[/center] 2*3'],
    ['newsletter', 1, 'body', '[mail]a@b.test[/mail]'],
    ['blocks', 1, 'content', '<a href="page.html">Page</a> [b]B[/b]<table><tr><td>t</td></tr></table>'],
    ['users', 2, 'sig', '[i]sig[/i]'],
];

# Put the schema back to a 6.3 site whose stored texts are BB: the rows of WORDROWS, a textarea field of the forum, a BB contact text and no manifest of the unit
function setWordSite(): void {
    deleteProbeTree(BASE_DIR);
    mkdir(CONFIG_DIR, 0777, true);
    setUpdateFile('fields.php', ['forum' => ['note' => ['type' => 'textarea'], 'size' => ['type' => 'text']]], [], true);
    setUpdateFile('contact.php', ['admins' => '1', 'info' => '[b]Наши координаты[/b]']);
    $side = getProbeSide();
    foreach (['comment', 'nodes', 'node_types', 'privat', 'message', 'newsletter', 'blocks'] as $name) $side->exec('DROP TABLE IF EXISTS `'.PROBEPREF.'_'.$name.'`');
    foreach (['node_types', 'nodes', 'comment', 'privat', 'message', 'newsletter', 'blocks'] as $name) $side->exec(getProbeTable($name));
    $side->exec('DELETE FROM `'.PROBEPREF.'_forum`');
    $side->exec('INSERT INTO `'.PROBEPREF.'_node_types` (id, name, title, intro) VALUES (1, \'news\', \'News\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_nodes` (id, tid, title, intro, body, field, status) VALUES (1, 1, \'A\', \'\', \'\', \'\', 2)');
    $side->exec('INSERT INTO `'.PROBEPREF.'_comment` (id, modul, cid, name, body, status, time) VALUES (1, \'news\', 1, \'u\', \'\', 1, \'2010-01-01 10:00:00\'),'
        .' (2, \'help\', 1, \'u\', \'\', 1, \'2011-01-01 10:00:00\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_forum` (id, name, title, body, field, time) VALUES (1, \'u\', \'T\', \'\', \'\', \'2008-01-01 10:00:00\'),'
        .' (2, \'u\', \'T\', \'\', \'\', \'2026-08-01 10:00:00\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_privat` (id, title, body) VALUES (1, \'P\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_message` (id, title, body) VALUES (1, \'M\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_newsletter` (id, title, body) VALUES (1, \'N\', \'\')');
    $side->exec('INSERT INTO `'.PROBEPREF.'_blocks` (id, title, content, url, which) VALUES (1, \'B\', \'\', \'\', \'\'),'
        .' (2, \'R\', \'[b]feed[/b]\', \'https://feed.test/\', \'\')');
    $side->exec('UPDATE `'.PROBEPREF.'_users` SET sig = \'\', block = \'\'');
    $side->exec('UPDATE `'.PROBEPREF.'_users` SET block = \''.str_repeat('*', 40000).'\' WHERE id = 3');
    foreach (WORDROWS as [$tab, $id, $col, $text]) {
        $side->prepare('UPDATE `'.PROBEPREF.'_'.$tab.'` SET `'.$col.'` = :text WHERE id = :id')->execute(['text' => $text, 'id' => $id]);
    }
}

# Everything the text unit leaves behind: its answer, the manifest, every text of WORDROWS, the feed block, the long block and the contact text
function getWordState(array $rows): array {
    clearstatcache();
    $file = BACKUP_DIR.'/update/texts/manifest.json';
    $texts = [];
    foreach (WORDROWS as [$tab, $id, $col]) {
        $texts[$tab.'.'.$col.' #'.$id] = (string)getProbeSide()->query('SELECT `'.$col.'` FROM `'.PROBEPREF.'_'.$tab.'` WHERE id = '.$id)->fetchColumn();
    }
    $side = getProbeSide();
    return ['done' => checkProbeDone($rows), 'text' => getProbeText($rows), 'info' => is_file($file) ? json_decode((string)file_get_contents($file), true) : null,
        'texts' => $texts, 'feed' => (string)$side->query('SELECT content FROM `'.PROBEPREF.'_blocks` WHERE id = 2')->fetchColumn(),
        'long' => strlen((string)$side->query('SELECT block FROM `'.PROBEPREF.'_users` WHERE id = 3')->fetchColumn()),
        'contact' => (string)(getUpdateSource(CONFIG_DIR.'/contact.php')['contact']['info'] ?? '')];
}

# One pass of the text unit as the first stage of a 6.3 site runs it, a dry run or a write run
function getWordRun(bool $dry): array {
    return getWordState(setUpdateTexts($GLOBALS['pdb'], PROBEPREF, $dry, false));
}

# Stop a finished run inside the batch of forum post 1, its transaction committed or not: the manifest names the batch, every later text is BB again
function setWordBatch(array $done, bool $commit): void {
    $file = BACKUP_DIR.'/update/texts/manifest.json';
    $info = json_decode((string)file_get_contents($file), true);
    $info['state'] = 'applying';
    $info['cursor'] = ['nodes' => 1, 'comment' => 2];
    $info['done'] = ['nodes.body' => 1, 'comment.body' => 2];
    $rows = [1 => ['c' => ['body', 'field'], 'h' => sha1((string)json_encode([$done['forum.body #1'], $done['forum.field #1']]))]];
    $info['batch'] = ['tab' => 'forum', 'last' => 1, 'count' => ['forum.body' => 1, 'forum.field' => 1], 'rows' => $rows];
    file_put_contents($file, json_encode($info));
    $side = getProbeSide();
    foreach (WORDROWS as [$tab, $id, $col, $text]) {
        if (in_array($tab, ['nodes', 'comment'], true)) continue;
        $val = ($commit && $tab === 'forum' && $id === 1) ? $done['forum.'.$col.' #1'] : $text;
        $side->prepare('UPDATE `'.PROBEPREF.'_'.$tab.'` SET `'.$col.'` = :text WHERE id = :id')->execute(['text' => $val, 'id' => $id]);
    }
}

# The text unit counts without writing, converts once, skips a verified repeat, and after a stop never converts a committed batch again nor loses one that did not commit
function getWordClean(): array {
    setWordSite();
    $out = ['dry' => getWordRun(true), 'write' => getWordRun(false), 'repeat' => getWordRun(false), 'again' => getWordRun(true)];
    $done = $out['write']['texts'];
    setWordBatch($done, true);
    $out['committed'] = getWordRun(false);
    setWordBatch($done, false);
    $out['rolled'] = getWordRun(false);
    return $out;
}

$report = ['error' => '', 'clean' => false, 'runs' => []];

try {
    $units = ['setUpdateFile', 'getUpdateSource', 'getUpdateCred', 'getUpdateRow', 'checkUpdateFail', 'checkUpdateBase', 'setUpdateBackup', 'setUpdatePoints',
        'setUpdateRatings', 'getUpdateRules', 'getUpdateValue', 'setUpdateFields', 'getUpdateLangs', 'setUpdateConfig', 'setUpdateMails', 'setUpdateModules', 'deleteUpdateTypes'];
    foreach ($units as $name) addProbeCode($name);
    $lift = ['getMigrateMap', 'getMigrateAttach', 'getMigrateForeign', 'getMigrateBlocks', 'getMigrateHtml', 'getMigrateMarkdown', 'getMigrateLocal', 'setUpdateArchive',
        'setUpdateTexts', 'setUpdateMerge', 'setUpdateTables'];
    foreach ($lift as $name) {
        addProbeCode($name, 'public/update.php', ["BASE_DIR.'/core/" => "PROBEROOT.'/core/"]);
    }
    if (($argv[2] ?? '') === 'setup') {
        require_once PROBEROOT.'/lang/en.php';
        require_once PROBEROOT.'/admin/lang/en.php';
        addProbeCode('getSetupProbe', 'public/setup.php', ['new Database(' => 'getProbeBase(']);
    }
    addProbeSchema();
    $report['runs'] = match ($argv[2] ?? 'points') {
        'ratings' => ['clean' => getRateClean(), 'resume' => getRateResume(), 'stop' => getRateStop(), 'flight' => getRateFlight()],
        'fields' => ['clean' => getFieldClean(), 'resume' => getFieldResume(), 'stop' => getFieldStop()],
        'config' => ['clean' => getConfClean()],
        'mails' => ['clean' => getMailClean()],
        'archive' => ['clean' => getArchClean(), 'merge' => getMergeClean(), 'tables' => getTableClean()],
        'texts' => ['clean' => getWordClean()],
        'setup' => ['clean' => getSetupClean()],
        default => ['clean' => getProbeClean(), 'resume' => getProbeResume(), 'stop' => getProbeStop(), 'flight' => getProbeFlight()],
    };
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}

$report['clean'] = deleteProbeSchema();
deleteProbeTree(BASE_DIR);

echo json_encode($report);
