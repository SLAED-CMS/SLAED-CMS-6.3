<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the Rating class of docs/node/ratings.md and for the write-guard protocol of the page cache it depends on
# It boots the real core the way index.php does, then drives the class through trusted test adapters against a disposable schema built from the shipped DDL
# The cache directory, the generation counter and the logs are redirected into scratch, so no marker, no bump and no line ever reaches the stand
# Every scenario reseeds its tables, every persistent result is read by a connection of its own, and concurrency is made of real processes
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
define('CACHE_DIR', $probework.'/cache');
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/rating.php';

# The accounts the votes are placed by and, in the account scope, placed on
const PROBEUSER = [2 => 'anna', 3 => 'boris', 4 => 'clara', 5 => 'dmitri', 6 => 'elena'];

# The top of the unsigned columns the test adapters write the aggregate into
const PROBEMAX = 4294967295;

# The closed map of the test adapters: the table behind a scope and whether its rows are materials without a known author
const PROBEMAP = ['account' => ['_users', false], 'forum' => ['_products', true], 'shop' => ['_products', true], 'node.probe' => ['_products', true]];

# The rule a new scope starts with, as the strings the configuration stores
const PROBERULE = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];

# A database facade that can fail one chosen statement and whose commit can be made to answer unknown, the two outcomes a real server cannot be asked to produce on demand
final class ProbeBase extends Database {
    public int $deny = 0;
    public int $fail = 0;

    # Refuse the statement the countdown points at without running it, and run every other one
    function getSqlQuery(string $query = '', array $params = []): PDOStatement|false {
        if ($this->fail > 0 && --$this->fail === 0) return false;
        return parent::getSqlQuery($query, $params);
    }

    # Answer unknown while the switch is on: mode 1 takes the transaction back first, mode 2 really commits it and lies about it
    function setSqlCommit(): bool {
        if (!$this->deny) return parent::setSqlCommit();
        if ($this->deny === 2) parent::setSqlCommit();
        else parent::setSqlRollback();
        return false;
    }
}

$GLOBALS['pname'] = 'slaed_rt_'.bin2hex(random_bytes(4));
$GLOBALS['pdb'] = null;
$GLOBALS['pcalls'] = [];
$GLOBALS['pwrite'] = true;

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    return new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# One shipped CREATE TABLE out of the fresh schema, filled for the disposable database, so the class is driven against the tables an installation really carries
function getProbeTable(string $name): string {
    $text = (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $hit[0]);
}

# Open a second connection to the disposable schema: the independent reader of every persistent result
function getProbeSide(): PDO {
    global $conf;
    $dsn = 'mysql:host='.$conf['db']['host'].';dbname='.$GLOBALS['pname'].';charset=utf8mb4';
    return new PDO($dsn, $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Open one more project facade on the disposable schema
function getProbeBase(): ProbeBase {
    global $conf;
    return new ProbeBase($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $GLOBALS['pname']);
}

# Create the disposable schema with the three shipped rating tables and the two shipped tables the test adapters rate, and connect the project facade to it
function addProbeSchema(): void {
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    foreach (['users', 'products', 'rating_actors', 'rating_targets', 'rating_votes'] as $name) $root->exec(getProbeTable($name));
    foreach (PROBEUSER as $id => $name) {
        $root->exec('INSERT INTO `'.PREFIX_DB.'_users` (`id`, `name`, `email`, `password`, `block`, `warnings`, `field`)'
            .' VALUES ('.$id.', \''.$name.'\', \''.$name.'@probe.test\', \'x\', \'\', \'\', \'\')');
    }
    $GLOBALS['pdb'] = getProbeBase();
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

# Remove one scratch directory with everything below it
function deleteProbeTree(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        if (is_dir($dir.'/'.$name)) deleteProbeTree($dir.'/'.$name);
        else unlink($dir.'/'.$name);
    }
    rmdir($dir);
}

# Reset the three rating tables, the aggregates of the accounts and the four rated materials: visible, enabled and without a vote
function setProbeSeed(): void {
    $pdb = $GLOBALS['pdb'];
    foreach (['rating_votes', 'rating_actors', 'rating_targets', 'products'] as $name) $pdb->getSqlQuery('DELETE FROM '.PREFIX_DB.'_'.$name);
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET votes = 0, tvotes = 0, points = 0');
    for ($i = 1; $i <= 4; $i++) {
        $pdb->getSqlQuery('INSERT INTO '.PREFIX_DB.'_products (id, title, intro, body, assoc, ihome, status) VALUES (:id, \'probe\', \'\', \'\', \'\', 1, 1)', ['id' => $i]);
    }
    $GLOBALS['pcalls'] = [];
    $GLOBALS['pwrite'] = true;
}

# The trusted read adapter of the probe: a closed map from scope to table, the rights of the actor repeated on every call, and the row of the owner locked on demand
# A material is reachable while it is published, and for the main administrator also while it is not; its rating is enabled by a flag of its own, and an account owns itself
function getProbeRead(Database $pdb): Closure {
    return static function (string $scope, int $id, array $actor, bool $lock) use ($pdb): ?array {
        $GLOBALS['pcalls'][] = ['read', $scope, $lock, $pdb->checkSqlActive()];
        if (!isset(PROBEMAP[$scope])) return null;
        [$tab, $free] = PROBEMAP[$scope];
        $sql = 'SELECT votes, tvotes'.($free ? ', ihome, status' : '').' FROM '.PREFIX_DB.$tab.' WHERE id = :id'.($lock ? ' FOR UPDATE' : '');
        $res = $pdb->getSqlQuery($sql, ['id' => $id]);
        if ($res === false) throw new RuntimeException('the probe adapter could not read its target');
        $row = $pdb->getSqlRow($res);
        if (!$row || ($free && !intval($row['status']) && !$actor['super'])) return null;
        return ['owner' => $free ? 0 : $id, 'score' => intval($row['tvotes']), 'ratings' => intval($row['votes']), 'enabled' => !$free || intval($row['ihome']) === 1];
    };
}

# The trusted write adapter of the probe: it stores the checked aggregate into the row the read adapter locked, refuses what its columns cannot hold, and never commits
function getProbeWrite(Database $pdb): Closure {
    return static function (string $scope, int $id, int $score, int $num) use ($pdb): bool {
        $GLOBALS['pcalls'][] = ['write', $scope, $score, $num];
        if (!$GLOBALS['pwrite'] || $score > PROBEMAX || $num > PROBEMAX) return false;
        $sql = 'UPDATE '.PREFIX_DB.PROBEMAP[$scope][0].' SET votes = :num, tvotes = :score WHERE id = :id';
        return $pdb->getSqlQuery($sql, ['num' => $num, 'score' => $score, 'id' => $id]) !== false;
    };
}

# The ratings scope of the probe with single rules replaced key by key, which is how every scenario states the one setting it is about
function getProbeConf(array $over = []): array {
    $out = array_fill_keys(array_keys(PROBEMAP), PROBERULE);
    foreach ($over as $name => $rule) $out[$name] = $rule + ($out[$name] ?? PROBERULE);
    return $out;
}

# Build the class for one trusted actor over the disposable schema, or over another facade or another whole scope one scenario brought along
function getProbeRating(array $actor, array $over = [], ?Database $pdb = null, ?array $conf = null): Rating {
    $pdb ??= $GLOBALS['pdb'];
    return new Rating($pdb, $conf ?? getProbeConf($over), $actor, getProbeRead($pdb), getProbeWrite($pdb));
}

# The trusted actors a server adapter would build from the session: an account, a guest, and an administrator who is or is not the main one
function getProbeUser(int $uid, string $ip = '10.0.0.1'): array {
    return ['uid' => $uid, 'ip' => $ip, 'aid' => 0, 'super' => false];
}

# A guest is nothing but an address
function getProbeGuest(string $ip = '10.0.0.1'): array {
    return ['uid' => 0, 'ip' => $ip, 'aid' => 0, 'super' => false];
}

# An administrator without an account of its own
function getProbeAdmin(bool $super, string $ip = '10.0.0.9'): array {
    return ['uid' => 0, 'ip' => $ip, 'aid' => 1, 'super' => $super];
}

# One delivery key, the same for the same number
function getProbeKey(int $num): string {
    return md5('probe'.$num);
}

# Every row of one rating table in the order of its key, read through a connection of its own so only what was really committed is seen
function getProbeRows(string $name): array {
    $sql = [
        'votes' => 'SELECT id, scope, mid, actor, uid, value, request, created, annulled, aid, reason FROM '.PREFIX_DB.'_rating_votes ORDER BY id',
        'actors' => 'SELECT scope, mid, actor, last FROM '.PREFIX_DB.'_rating_actors ORDER BY scope, mid, actor',
        'targets' => 'SELECT scope, mid, base, votes FROM '.PREFIX_DB.'_rating_targets ORDER BY scope, mid',
    ][$name];
    $out = [];
    foreach (getProbeSide()->query($sql)->fetchAll(PDO::FETCH_NUM) as $row) $out[] = array_map(static fn($v) => is_numeric($v) ? intval($v) : $v, $row);
    return $out;
}

# The committed aggregate of one rated row as the pair of sum and count, read through a connection of its own
function getProbeSum(string $scope, int $id): array {
    $row = getProbeSide()->query('SELECT tvotes, votes FROM '.PREFIX_DB.PROBEMAP[$scope][0].' WHERE id = '.$id)->fetch(PDO::FETCH_NUM);
    return $row ? [intval($row[0]), intval($row[1])] : [];
}

# The short form of one result most scenarios compare: code, vote as a flag, sum, count, average, duplicate and canvote
function getProbeBrief(array $res): array {
    return [$res['code'], $res['vote'] > 0, $res['score'], $res['ratings'], $res['average'], $res['duplicate'], $res['canvote']];
}

# The markers of the guard journal that exist right now
function getProbeMarks(): array {
    return array_values(preg_grep('/^[a-f0-9]{32}\.lock$/', is_dir(CACHE_DIR.'/guards') ? scandir(CACHE_DIR.'/guards') : []));
}

# Whether the site log of this run carries a line with the given text
function checkProbeLog(string $text): bool {
    $file = LOGS_DIR.'/error_site.log';
    return is_file($file) && str_contains((string)file_get_contents($file), $text);
}

# Run this probe again as a process of its own in one of its child modes and answer what it reported
function getProbeText(string $mode, array $args = []): string {
    $cmd = escapeshellarg(PHP_BINARY);
    foreach (array_merge([__FILE__, $GLOBALS['probework'], $mode, $GLOBALS['pname']], $args) as $one) $cmd .= ' '.escapeshellarg((string)$one);
    return (string)shell_exec($cmd);
}

# Run one child mode that reports JSON and answer the decoded report, or the head of whatever it printed instead
function getProbeChild(string $mode, array $args = []): mixed {
    $text = getProbeText($mode, $args);
    $data = json_decode($text, true);
    return is_array($data) ? $data : 'no answer: '.substr($text, 0, 200);
}

# The write-guard protocol of the page cache on its own: the forced bump, the marker and its lock, the sweep that spares the journal, the refusals, and the recovery after a death
function getProbeCache(): array {
    $was = Cache::getEpoch();
    $out = ['bump' => [Cache::addEpoch(), Cache::getEpoch() - $was, Cache::addEpoch(), Cache::getEpoch() - $was, Cache::addEpoch(true), Cache::getEpoch() - $was]];
    $out['idle'] = [Cache::checkWriteGuard(), getProbeMarks()];
    $guard = Cache::getWriteGuard();
    $out['open'] = [is_resource($guard), count(getProbeMarks()), is_file(CACHE_DIR.'/guards.lock'), Cache::checkWriteGuard()];
    $was = Cache::getEpoch();
    $out['peek'] = [getProbeChild('peek'), count(getProbeMarks()), Cache::getEpoch() - $was];
    Cache::setBody(CACHE_DIR.'/pages/html/'.str_repeat('a', 40).'.html', 'page');
    Cache::setBody(CACHE_DIR.'/templates/probe.php', 'tpl');
    touch(CACHE_DIR.'/guards.lock', time() - 100000);
    $out['sweep'] = [Cache::deleteAll(), Cache::deleteStaleTree(CACHE_DIR, 1), count(getProbeMarks())];
    $out['spared'] = [is_file(CACHE_DIR.'/guards.lock'), is_file(CACHE_DIR.'/templates/probe.php')];
    $alien = fopen(CACHE_DIR.'/alien.lock', 'c');
    $out['deny'] = [Cache::deleteWriteGuard('guard'), Cache::deleteWriteGuard(null), Cache::deleteWriteGuard($alien), count(getProbeMarks())];
    fclose($alien);
    $out['close'] = [Cache::deleteWriteGuard($guard), count(getProbeMarks()), Cache::deleteWriteGuard($guard), Cache::checkWriteGuard()];
    $good = (string)file_get_contents(COUNTER_DIR.'/cache.log');
    file_put_contents(COUNTER_DIR.'/cache.log', 'x');
    $out['garbled'] = [Cache::checkWriteGuard(), Cache::getEpoch()];
    file_put_contents(COUNTER_DIR.'/cache.log', $good);
    $out['healed'] = [Cache::checkWriteGuard(), (string)Cache::getEpoch() === $good];
    $out['died'] = [getProbeChild('hold'), count(getProbeMarks())];
    $was = Cache::getEpoch();
    $out['recover'] = [Cache::checkWriteGuard(), count(getProbeMarks()), Cache::getEpoch() - $was];
    return $out;
}

# Whether one ratings scope lets the shop be read: a usable rule answers ok, a blocked one answers unavailable
function getProbeCode(array $conf, string $scope = 'shop'): string {
    return getProbeRating(getProbeUser(2), [], null, $conf)->getRating($scope, 1)['code'];
}

# The strict configuration: every stored form of every key, every bound of the period with its neighbour, and a broken rule that blocks its own scope and no other
function getProbeConfig(): array {
    setProbeSeed();
    $top = intdiv(PHP_INT_MAX, 86400) * 86400;
    $list = [
        'shipped' => [],
        'off' => ['active' => '0'],
        'nodetail' => ['detail' => '0'],
        'noguests' => ['guests' => '0'],
        'zero' => ['period' => '0'],
        'top' => ['period' => (string)$top],
        'activeint' => ['active' => 1],
        'activebool' => ['active' => true],
        'activetwo' => ['active' => '2'],
        'activepad' => ['active' => ' 1'],
        'detailint' => ['detail' => 0],
        'guestsvoid' => ['guests' => ''],
        'perint' => ['period' => 86400],
        'persign' => ['period' => '-1'],
        'perplus' => ['period' => '+1'],
        'perlead' => ['period' => '01'],
        'perpad' => ['period' => '1 '],
        'perline' => ['period' => "1\n"],
        'perfloat' => ['period' => '1.0'],
        'pervoid' => ['period' => ''],
        'perover' => ['period' => $top.'0'],
        'pernext' => ['period' => substr($top, 0, -1).(intval(substr($top, -1)) + 1)],
        'perhuge' => ['period' => '99999999999999999999'],
        'wide' => ['bonus' => '1'],
    ];
    $out = ['valid' => []];
    foreach ($list as $name => $rule) $out['valid'][$name] = getProbeCode(getProbeConf(['shop' => $rule]));
    $thin = getProbeConf();
    unset($thin['shop']['guests']);
    $flat = ['shop' => '1'] + getProbeConf();
    $less = getProbeConf();
    unset($less['account']);
    $name = ['Node.Probe' => PROBERULE, 'gallery' => PROBERULE] + getProbeConf();
    $out['valid']['thin'] = getProbeCode($thin);
    $out['valid']['flat'] = getProbeCode($flat);
    $out['alone'] = [getProbeCode(getProbeConf(['forum' => ['active' => '2']])), getProbeCode(getProbeConf(['forum' => ['active' => '2']]), 'forum')];
    $out['less'] = [getProbeCode($less), getProbeCode($less, 'account')];
    $out['name'] = [getProbeCode($name), getProbeCode($name, 'node.probe')];
    $out['ghost'] = getProbeCode(getProbeConf(), 'node.ghost');
    $off = getProbeRating(getProbeUser(2), ['shop' => ['active' => '0']]);
    $out['switch'] = [getProbeBrief($off->getRating('shop', 1)), getProbeBrief($off->addRating('shop', 1, 5, getProbeKey(1)))];
    $dim = getProbeRating(getProbeUser(2), ['shop' => ['detail' => '0']]);
    $out['detail'] = [getProbeBrief($dim->getRating('shop', 1)), getProbeBrief($dim->addRating('shop', 1, 5, getProbeKey(2)))];
    $out['log'] = [checkProbeLog('the rule of a scope is invalid'), checkProbeLog('the rule of a scope is missing'), checkProbeLog('Node.Probe')];
    return $out;
}

# The trusted actor is taken whole or not at all, and a guest without a usable address sees every rating and places no vote
function getProbeActor(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $list = [
        'less' => ['uid' => 2, 'ip' => '10.0.0.1', 'aid' => 0],
        'more' => getProbeUser(2) + ['name' => 'anna'],
        'uidtext' => ['uid' => '2'] + getProbeUser(2),
        'uidsign' => ['uid' => -1] + getProbeUser(2),
        'uidover' => ['uid' => PROBEMAX + 1] + getProbeUser(2),
        'iplist' => ['ip' => ['10.0.0.1']] + getProbeUser(2),
        'aidtext' => ['aid' => '1'] + getProbeAdmin(false),
        'superint' => ['super' => 1] + getProbeAdmin(false),
        'superbare' => ['aid' => 0] + getProbeAdmin(true),
    ];
    $num = $pdb->qnum;
    $out = ['deny' => []];
    foreach ($list as $name => $actor) {
        $rat = getProbeRating($actor);
        $out['deny'][$name] = [$rat->getRating('shop', 1)['code'], $rat->addRating('shop', 1, 5, getProbeKey(1))['code']];
        $out['deny'][$name] = array_merge($out['deny'][$name], [$rat->deleteRating(1, 'x')['code'], $rat->getRatingList()['code']]);
    }
    $out['cost'] = $pdb->qnum - $num;
    $out['noip'] = [];
    foreach (['void' => '', 'zero' => '0.0.0.0', 'none' => '::', 'junk' => 'not an address', 'port' => '10.0.0.1:80'] as $name => $ip) {
        $rat = getProbeRating(getProbeGuest($ip));
        $out['noip'][$name] = [getProbeBrief($rat->getRating('shop', 1)), $rat->addRating('shop', 1, 5, getProbeKey(1))['code']];
    }
    $out['norm'] = [
        getProbeRating(getProbeGuest('2001:DB8::1'))->addRating('shop', 1, 4, getProbeKey(2))['code'],
        getProbeRating(getProbeGuest('2001:db8:0:0:0:0:0:1'))->addRating('shop', 1, 4, getProbeKey(3))['code'],
        array_column(getProbeRows('actors'), 2),
    ];
    $out['log'] = checkProbeLog('the actor is invalid');
    return $out;
}

# The closed input: everything refused as a wrong form costs no statement, and what cannot be reached hides its counters
function getProbeInput(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET status = 0, votes = 3, tvotes = 12 WHERE id = 4');
    $rat = getProbeRating(getProbeUser(2));
    $key = getProbeKey(1);
    $num = $pdb->qnum;
    $out = ['deny' => [
        'scopecase' => $rat->addRating('Shop', 1, 5, $key)['code'],
        'scopevoid' => $rat->addRating('', 1, 5, $key)['code'],
        'scopeold' => $rat->addRating('news', 1, 5, $key)['code'],
        'scopetable' => $rat->addRating('products', 1, 5, $key)['code'],
        'scopeline' => $rat->addRating("shop\n", 1, 5, $key)['code'],
        'scopenode' => $rat->addRating('node.', 1, 5, $key)['code'],
        'scopelong' => $rat->addRating('node.'.str_repeat('a', 21), 1, 5, $key)['code'],
        'idzero' => $rat->addRating('shop', 0, 5, $key)['code'],
        'idsign' => $rat->addRating('shop', -1, 5, $key)['code'],
        'idover' => $rat->addRating('shop', PROBEMAX + 1, 5, $key)['code'],
        'valzero' => $rat->addRating('shop', 1, 0, $key)['code'],
        'valsix' => $rat->addRating('shop', 1, 6, $key)['code'],
        'valsign' => $rat->addRating('shop', 1, -5, $key)['code'],
        'keyvoid' => $rat->addRating('shop', 1, 5, '')['code'],
        'keyshort' => $rat->addRating('shop', 1, 5, substr($key, 1))['code'],
        'keylong' => $rat->addRating('shop', 1, 5, $key.'a')['code'],
        'keycase' => $rat->addRating('shop', 1, 5, strtoupper($key))['code'],
        'keyline' => $rat->addRating('shop', 1, 5, substr($key, 1)."\n")['code'],
        'readscope' => $rat->getRating('products', 1)['code'],
        'readid' => $rat->getRating('shop', 0)['code'],
    ]];
    $out['cost'] = $pdb->qnum - $num;
    $out['gone'] = [$rat->getRating('shop', 99), $rat->addRating('shop', 99, 5, $key)];
    $out['hidden'] = [$rat->getRating('shop', 4), $rat->addRating('shop', 4, 5, $key)];
    $out['rows'] = [getProbeRows('votes'), getProbeRows('targets'), getProbeMarks()];
    return $out;
}

# The scale and the average: whole sums and counts, a derived string of six digits, the rows behind one vote, and the invariant of base plus active votes
function getProbeScale(): array {
    setProbeSeed();
    $out = ['empty' => getProbeRating(getProbeUser(2))->getRating('shop', 1)];
    $out['first'] = getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1));
    $out['calls'] = $GLOBALS['pcalls'];
    $out['second'] = getProbeRating(getProbeUser(3))->addRating('shop', 1, 1, getProbeKey(2));
    $out['third'] = getProbeRating(getProbeUser(4))->addRating('shop', 1, 5, getProbeKey(3));
    $out['read'] = getProbeRating(getProbeUser(5))->getRating('shop', 1);
    $out['stored'] = [getProbeSum('shop', 1), getProbeRows('targets'), getProbeRows('actors'), getProbeRows('votes')];
    $out['marks'] = getProbeMarks();
    $out['open'] = $GLOBALS['pdb']->checkSqlActive();
    return $out;
}

# A target that carries a starting balance: a new vote lands on top of it, its annulment takes exactly that vote back, and an aggregate nobody carried over takes no vote
function getProbeCarry(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET votes = 10, tvotes = 37 WHERE id IN (3, 4)');
    $pdb->getSqlQuery('INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (\'account\', 3, 37, 10, UNIX_TIMESTAMP())');
    $rat = getProbeRating(getProbeUser(2));
    $out = ['before' => getProbeBrief($rat->getRating('account', 3))];
    $new = $rat->addRating('account', 3, 5, getProbeKey(1));
    $out['added'] = [getProbeBrief($new), getProbeSum('account', 3)];
    $out['undone'] = [getProbeBrief(getProbeRating(getProbeAdmin(true))->deleteRating($new['vote'], 'a test')), getProbeSum('account', 3), getProbeRows('targets')];
    $out['loose'] = [getProbeBrief($rat->getRating('account', 4)), getProbeBrief($rat->addRating('account', 4, 5, getProbeKey(2)))];
    $out['loose'] = array_merge($out['loose'], [getProbeSum('account', 4), count(getProbeRows('votes'))]);
    $out['log'] = checkProbeLog('was never carried over');
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET votes = :num, tvotes = :sum WHERE id = 2', ['num' => PROBEMAX, 'sum' => PROBEMAX]);
    $sql = 'INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (\'shop\', 2, :sum, :num, UNIX_TIMESTAMP())';
    $pdb->getSqlQuery($sql, ['num' => PROBEMAX, 'sum' => PROBEMAX]);
    $out['full'] = [$rat->addRating('shop', 2, 1, getProbeKey(3))['code'], getProbeSum('shop', 2)[1] === PROBEMAX, count(getProbeRows('votes'))];
    return $out;
}

# Who an actor is: an account from any address, another account from the same address, and a guest who stays apart from every account before and after a login
function getProbeWho(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $out = [
        'home' => getProbeRating(getProbeUser(2, '10.0.0.1'))->addRating('shop', 1, 5, getProbeKey(1))['code'],
        'away' => getProbeRating(getProbeUser(2, '10.9.9.9'))->addRating('shop', 1, 5, getProbeKey(2))['code'],
        'mate' => getProbeRating(getProbeUser(3, '10.0.0.1'))->addRating('shop', 1, 4, getProbeKey(3))['code'],
        'guest' => getProbeRating(getProbeGuest('10.0.0.1'))->addRating('shop', 1, 3, getProbeKey(4))['code'],
        'again' => getProbeRating(getProbeGuest('10.0.0.1'))->addRating('shop', 1, 3, getProbeKey(5))['code'],
        'login' => getProbeRating(getProbeUser(4, '10.0.0.1'))->addRating('shop', 1, 2, getProbeKey(6))['code'],
        'logout' => getProbeRating(getProbeGuest('10.0.0.1'))->addRating('shop', 1, 3, getProbeKey(7))['code'],
        'other' => getProbeRating(getProbeGuest('10.0.0.2'))->addRating('shop', 1, 1, getProbeKey(8))['code'],
    ];
    $out['rows'] = array_map(static fn(array $row): array => [$row[3], $row[4], $row[5]], getProbeRows('votes'));
    $out['sum'] = getProbeSum('shop', 1);
    $shut = getProbeRating(getProbeGuest('10.0.0.3'), ['shop' => ['guests' => '0']]);
    $num = $pdb->qnum;
    $out['shut'] = [$shut->addRating('shop', 1, 5, getProbeKey(9))['code'], $pdb->qnum - $num, getProbeBrief($shut->getRating('shop', 1))];
    $out['member'] = getProbeRating(getProbeUser(5), ['shop' => ['guests' => '0']])->addRating('shop', 1, 5, getProbeKey(10))['code'];
    return $out;
}

# Move the last participation of one actor of the shop to a moment relative to the clock of the database
function setProbeLast(string $actor, int $back): void {
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_rating_actors SET last = UNIX_TIMESTAMP() - :back WHERE actor = :actor', ['back' => $back, 'actor' => $actor]);
}

# The last participation of one actor of the shop as it is committed
function getProbeLast(string $actor): int {
    foreach (getProbeRows('actors') as $row) {
        if ($row[2] === $actor) return $row[3];
    }
    return 0;
}

# The interval: its border, a refusal that does not extend it, a period that was shortened, lengthened or switched off, and a last participation from the future
function getProbePeriod(): array {
    setProbeSeed();
    $rat = static fn(string $per): Rating => getProbeRating(getProbeUser(2), ['shop' => ['period' => $per]]);
    $out = ['first' => $rat('100')->addRating('shop', 1, 5, getProbeKey(1))['code']];
    $now = $rat('100')->addRating('shop', 1, 5, getProbeKey(2));
    $out['fresh'] = [$now['code'], $now['wait'] >= 99 && $now['wait'] <= 100, $now['canvote'], $now['score']];
    setProbeLast('u:2', 90);
    $was = getProbeLast('u:2');
    $now = $rat('100')->addRating('shop', 1, 5, getProbeKey(3));
    $see = $rat('100')->getRating('shop', 1);
    $out['inside'] = [$now['code'], $now['wait'] >= 8 && $now['wait'] <= 10, $see['code'], $see['wait'] >= 8 && $see['wait'] <= 10, $see['canvote'], getProbeLast('u:2') === $was];
    setProbeLast('u:2', 100);
    $see = $rat('100')->getRating('shop', 1);
    $now = $rat('100')->addRating('shop', 1, 4, getProbeKey(4));
    $out['border'] = [$see['wait'], $see['canvote'], $now['code'], $now['wait'] >= 99, $now['canvote'], getProbeLast('u:2') > $was];
    setProbeLast('u:2', 50);
    $out['shorter'] = [$rat('100')->addRating('shop', 1, 3, getProbeKey(5))['code'], $rat('40')->addRating('shop', 1, 3, getProbeKey(5))['code']];
    $out['longer'] = $rat('0')->getRating('shop', 1)['canvote'] ? $rat('1000')->addRating('shop', 1, 3, getProbeKey(6))['code'] : 'closed';
    $free = [$rat('0')->addRating('shop', 1, 2, getProbeKey(7)), $rat('0')->addRating('shop', 1, 2, getProbeKey(8))];
    $out['free'] = [$free[0]['code'], $free[0]['canvote'], $free[0]['wait'], $free[1]['code'], $free[1]['vote'] > $free[0]['vote']];
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_rating_actors SET last = UNIX_TIMESTAMP() + 1000 WHERE actor = \'u:2\'');
    $see = $rat('100')->getRating('shop', 1);
    $out['future'] = [$rat('100')->addRating('shop', 1, 1, getProbeKey(9))['code'], $rat('0')->addRating('shop', 1, 1, getProbeKey(10))['code']];
    $out['future'] = array_merge($out['future'], [$see['code'], $see['wait'], $see['canvote']]);
    $out['log'] = checkProbeLog('lies in the future');
    $out['sum'] = [getProbeSum('shop', 1), count(getProbeRows('votes'))];
    return $out;
}

# The delivery key: a repeat answers the stored vote, another value under the same key is a conflict, and the key is unique per actor and target only
function getProbeRequest(): array {
    setProbeSeed();
    $rat = getProbeRating(getProbeUser(2));
    $key = getProbeKey(1);
    $new = $rat->addRating('shop', 1, 4, $key);
    $rep = $rat->addRating('shop', 1, 4, $key);
    $out = ['new' => getProbeBrief($new), 'repeat' => [getProbeBrief($rep), $rep['vote'] === $new['vote'], $rep['wait'] > 0]];
    $out['clash'] = [getProbeBrief($rat->addRating('shop', 1, 5, $key)), getProbeSum('shop', 1), count(getProbeRows('votes'))];
    $out['mate'] = getProbeRating(getProbeUser(3))->addRating('shop', 1, 2, $key)['code'];
    $out['next'] = $rat->addRating('shop', 2, 3, $key)['code'];
    $out['scope'] = $rat->addRating('node.probe', 3, 3, $key)['code'];
    $out['later'] = getProbeRating(getProbeUser(2), ['shop' => ['period' => '0']])->addRating('shop', 1, 4, $key)['duplicate'];
    $out['rows'] = count(getProbeRows('votes'));
    return $out;
}

# The own vote and the switched off target: both are seen with their aggregate and take no vote of that actor, while a material without a known author is nobody's own
function getProbeOwn(): array {
    setProbeSeed();
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET ihome = 0 WHERE id = 3');
    $out = ['mate' => getProbeBrief(getProbeRating(getProbeUser(3))->addRating('account', 2, 4, getProbeKey(1)))];
    $own = getProbeRating(getProbeUser(2));
    $out['self'] = [getProbeBrief($own->getRating('account', 2)), getProbeBrief($own->addRating('account', 2, 5, getProbeKey(2))), getProbeSum('account', 2)];
    $out['guest'] = getProbeRating(getProbeGuest())->addRating('account', 2, 5, getProbeKey(3))['code'];
    $out['free'] = getProbeRating(getProbeGuest())->addRating('shop', 1, 5, getProbeKey(4))['code'];
    $out['closed'] = [getProbeBrief($own->getRating('shop', 3)), getProbeBrief($own->addRating('shop', 3, 5, getProbeKey(5))), getProbeRows('targets')];
    return $out;
}

# The annulment and the journal: the main administrator alone, a reason, exactly the stored value, an untouched last participation, a repeat that changes nothing
function getProbeAnnul(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $ids = [];
    foreach ([[2, 'shop', 1, 5], [3, 'shop', 1, 2], [4, 'shop', 2, 4], [2, 'account', 3, 3], [3, 'node.probe', 4, 1]] as $key => [$uid, $scope, $mid, $val]) {
        $ids[] = getProbeRating(getProbeUser($uid))->addRating($scope, $mid, $val, getProbeKey($key))['vote'];
    }
    $sup = getProbeRating(getProbeAdmin(true));
    $out = ['ids' => $ids === array_filter($ids), 'deny' => [
        'user' => [getProbeRating(getProbeUser(2))->deleteRating($ids[0], 'x')['code'], getProbeRating(getProbeUser(2))->getRatingList()['code']],
        'guest' => [getProbeRating(getProbeGuest())->deleteRating($ids[0], 'x')['code'], getProbeRating(getProbeGuest())->getRatingList()['code']],
        'admin' => [getProbeRating(getProbeAdmin(false))->deleteRating($ids[0], 'x')['code'], getProbeRating(getProbeAdmin(false))->getRatingList()],
    ]];
    $out['form'] = [
        'zero' => $sup->deleteRating(0, 'x')['code'],
        'sign' => $sup->deleteRating(-1, 'x')['code'],
        'void' => $sup->deleteRating($ids[0], '')['code'],
        'blank' => $sup->deleteRating($ids[0], " \t")['code'],
        'long' => $sup->deleteRating($ids[0], str_repeat('я', 256))['code'],
        'html' => $sup->deleteRating($ids[0], 'a <b>bold</b> reason')['code'],
        'line' => $sup->deleteRating($ids[0], "two\nlines")['code'],
        'none' => $sup->deleteRating(999, 'x')['code'],
    ];
    $last = getProbeLast('u:3');
    $GLOBALS['pcalls'] = [];
    $res = $sup->deleteRating($ids[1], str_repeat('я', 255));
    $out['done'] = [getProbeBrief($res), $res['vote'] === $ids[1], getProbeSum('shop', 1), $GLOBALS['pcalls'], getProbeLast('u:3') === $last];
    $row = getProbeRows('votes')[1];
    $out['row'] = [$row[8] > 0, $row[9], mb_strlen($row[10]), $row[5]];
    $res = $sup->deleteRating($ids[1], 'once more');
    $out['twice'] = [getProbeBrief($res), getProbeSum('shop', 1), mb_strlen(getProbeRows('votes')[1][10])];
    $out['still'] = getProbeRating(getProbeUser(3))->addRating('shop', 1, 5, getProbeKey(20))['code'];
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET status = 0 WHERE id = 2');
    $out['hidden'] = [getProbeRating(getProbeAdmin(true), ['shop' => ['active' => '0', 'guests' => '0']])->deleteRating($ids[2], 'hidden and off')['code'], getProbeSum('shop', 2)];
    $less = getProbeConf();
    unset($less['account']);
    $out['norule'] = [getProbeRating(getProbeAdmin(true), [], null, $less)->deleteRating($ids[3], 'no rule')['code'], getProbeSum('account', 3)];
    $pdb->getSqlQuery('DELETE FROM '.PREFIX_DB.'_products WHERE id = 4');
    $out['gone'] = [getProbeBrief($sup->deleteRating($ids[4], 'gone')), getProbeRows('votes')[4][8]];
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET votes = 0, tvotes = 0 WHERE id = 1');
    $out['broken'] = [$sup->deleteRating($ids[0], 'broken')['code'], getProbeRows('votes')[0][8], checkProbeLog('would turn the aggregate negative')];
    $all = $sup->getRatingList();
    $out['list'] = [
        'all' => [$all['ok'], $all['code'], array_column($all['rows'], 'id') === $ids, $all['next'] === $ids[4], array_keys($all['rows'][0] ?? [])],
        'first' => $all['rows'][0] ?? [],
        'scope' => array_column($sup->getRatingList('shop')['rows'], 'mid'),
        'target' => array_column($sup->getRatingList('shop', 1)['rows'], 'uid'),
        'after' => array_column($sup->getRatingList('', 0, $ids[2])['rows'], 'scope'),
        'page' => [count($sup->getRatingList('', 0, 0, 2)['rows']), $sup->getRatingList('', 0, 0, 2)['next'] === $ids[1]],
        'past' => $sup->getRatingList('', 0, $ids[4]),
        'oldtype' => $sup->getRatingList('node.ghost')['code'],
    ];
    $out['badlist'] = [
        'idbare' => $sup->getRatingList('', 1)['code'],
        'scope' => $sup->getRatingList('products')['code'],
        'idsign' => $sup->getRatingList('shop', -1)['code'],
        'after' => $sup->getRatingList('', 0, -1)['code'],
        'limzero' => $sup->getRatingList('', 0, 0, 0)['code'],
        'limover' => $sup->getRatingList('', 0, 0, 101)['code'],
        'limtop' => $sup->getRatingList('', 0, 0, 100)['code'],
    ];
    return $out;
}

# A write inside a transaction somebody else opened is refused before anything changes, and that transaction is left exactly as it was
function getProbeForeign(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $vote = getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1))['vote'];
    $pdb->setSqlBegin();
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET `rank` = \'main\' WHERE id = 2');
    $GLOBALS['pcalls'] = [];
    $out = [
        'add' => getProbeRating(getProbeUser(3))->addRating('shop', 1, 5, getProbeKey(2))['code'],
        'delete' => getProbeRating(getProbeAdmin(true))->deleteRating($vote, 'inside')['code'],
        'calls' => $GLOBALS['pcalls'],
        'open' => $pdb->checkSqlActive(),
        'marks' => getProbeMarks(),
    ];
    $pdb->setSqlCommit();
    $row = getProbeSide()->query('SELECT `rank` FROM '.PREFIX_DB.'_users WHERE id = 2')->fetchColumn();
    $out['kept'] = [$row, getProbeSum('shop', 1), count(getProbeRows('votes')), checkProbeLog('inside a foreign open transaction')];
    return $out;
}

# Every statement of a vote and of an annulment fails once: each time the answer is storage, nothing at all is stored, no transaction and no marker is left behind
function getProbeFail(): array {
    $pdb = $GLOBALS['pdb'];
    $out = ['add' => [], 'delete' => []];
    for ($i = 1; $i <= 11; $i++) {
        setProbeSeed();
        $rat = getProbeRating(getProbeUser(2));
        $pdb->fail = $i;
        $res = $rat->addRating('shop', 1, 5, getProbeKey(1));
        $pdb->fail = 0;
        $rows = count(getProbeRows('votes')) + count(getProbeRows('actors')) + count(getProbeRows('targets'));
        $out['add'][$i] = [$res['code'], $res['score'], $rows, getProbeSum('shop', 1)[0], $pdb->checkSqlActive(), count(getProbeMarks())];
    }
    for ($i = 1; $i <= 11; $i++) {
        setProbeSeed();
        $vote = getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1))['vote'];
        $sup = getProbeRating(getProbeAdmin(true));
        $pdb->fail = $i;
        $res = $sup->deleteRating($vote, 'fail');
        $pdb->fail = 0;
        $out['delete'][$i] = [$res['code'], getProbeRows('votes')[0][8] > 0, getProbeSum('shop', 1)[0], $pdb->checkSqlActive(), count(getProbeMarks())];
    }
    setProbeSeed();
    $GLOBALS['pwrite'] = false;
    $code = getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1))['code'];
    $out['write'] = [$code, count(getProbeRows('votes')), $pdb->checkSqlActive(), count(getProbeMarks())];
    $GLOBALS['pwrite'] = true;
    $pdb->getSqlQuery('CREATE TRIGGER probestop BEFORE INSERT ON '.PREFIX_DB.'_rating_actors FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe\'');
    $code = getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1))['code'];
    $out['real'] = [$code, count(getProbeRows('votes')), getProbeSum('shop', 1), $pdb->checkSqlActive(), count(getProbeMarks())];
    $pdb->getSqlQuery('DROP TRIGGER probestop');
    $hard = 'SIGNAL SQLSTATE \'40001\' SET MESSAGE_TEXT = \'probe deadlock\', MYSQL_ERRNO = 1213';
    $pdb->getSqlQuery('CREATE TRIGGER probehard BEFORE INSERT ON '.PREFIX_DB.'_rating_votes FOR EACH ROW '.$hard);
    $out['hard'] = [getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1))['code'], count(getProbeRows('votes')), $pdb->checkSqlActive(), count(getProbeMarks())];
    $pdb->getSqlQuery('DROP TRIGGER probehard');
    $sql = 'INSERT INTO '.PREFIX_DB.'_rating_votes (scope, mid, actor, value, request, created) VALUES (\'shop\', 1, \'u:2\', 6, \''.getProbeKey(1).'\', 1)';
    $out['value'] = getProbeThrow(static fn(): bool => getProbeSide()->exec($sql) > 0);
    $out['healed'] = getProbeBrief(getProbeRating(getProbeUser(2))->addRating('shop', 1, 5, getProbeKey(1)));
    $out['log'] = checkProbeLog('the write was not stored') && checkProbeLog('the write threw');
    return $out;
}

# Run one call that is expected to throw and answer the class of what it threw, or the value it returned instead
function getProbeThrow(callable $call): mixed {
    try {
        return $call();
    } catch (Throwable $err) {
        return get_class($err);
    }
}

# A commit whose outcome is unknown, in a process of its own because its marker has to outlive the writer: storage, a kept marker, and a repeat that finds out what happened
function getProbeLost(string $base): array {
    $GLOBALS['pname'] = $base;
    $pdb = $GLOBALS['pdb'] = getProbeBase();
    $rat = getProbeRating(getProbeUser(2));
    $pdb->deny = 1;
    $out = ['back' => [$rat->addRating('shop', 1, 5, getProbeKey(1))['code'], count(getProbeRows('votes')), count(getProbeMarks()), Cache::checkWriteGuard()]];
    $pdb->deny = 2;
    $out['kept'] = [$rat->addRating('shop', 1, 5, getProbeKey(2))['code'], count(getProbeRows('votes')), count(getProbeMarks())];
    $pdb->deny = 0;
    $out['again'] = getProbeBrief($rat->addRating('shop', 1, 5, getProbeKey(2)));
    $out['end'] = [getProbeSum('shop', 1), count(getProbeRows('votes')), count(getProbeMarks()), $pdb->checkSqlActive()];
    return $out;
}

# The unknown commit seen from outside: while the writer lives its markers stand, and once it is gone the next reader bumps the generation and clears them
function getProbeCommit(): array {
    setProbeSeed();
    $out = ['child' => getProbeChild('lost'), 'left' => count(getProbeMarks())];
    $was = Cache::getEpoch();
    $out['recover'] = [Cache::checkWriteGuard(), count(getProbeMarks()), Cache::getEpoch() - $was];
    $out['log'] = checkProbeLog('the outcome of the commit is unknown');
    return $out;
}

# One competing vote, run by a process of its own against the schema of the main run, held back until the moment every rival was given
function getProbeRival(string $base, float $when, int $uid, string $request, int $value): array {
    $GLOBALS['pname'] = $base;
    $GLOBALS['pdb'] = getProbeBase();
    $rat = getProbeRating(getProbeUser($uid));
    while (microtime(true) < $when) usleep(200);
    $res = $rat->addRating('shop', 1, $value, $request);
    return [$res['code'], $res['duplicate'], $res['vote']];
}

# Start one rival per entry at one shared moment and collect what each of them answered
function getProbeRivals(array $list): array {
    $when = microtime(true) + 3;
    $open = [];
    foreach ($list as [$uid, $request, $value]) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg((string)$GLOBALS['probework']).' rival '.escapeshellarg($GLOBALS['pname'])
            .' '.escapeshellarg((string)$when).' '.$uid.' '.escapeshellarg($request).' '.$value;
        $pipe = [];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
        if (is_resource($proc)) $open[] = [$proc, $pipe];
    }
    $runs = [];
    foreach ($open as [$proc, $pipe]) {
        $text = (string)stream_get_contents($pipe[1]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        proc_close($proc);
        $data = json_decode($text, true);
        $runs[] = is_array($data) ? $data : ['no answer: '.substr($text, 0, 120), false, 0];
    }
    return $runs;
}

# Real processes at one moment: two intentions of one actor leave one vote, one delivery sent four times leaves one vote, and four actors lose no update between them
function getProbeRace(): array {
    setProbeSeed();
    $out = ['intent' => getProbeRivals([[2, getProbeKey(1), 5], [2, getProbeKey(2), 5], [2, getProbeKey(3), 5], [2, getProbeKey(4), 5]])];
    $out['once'] = [getProbeSum('shop', 1), count(getProbeRows('votes')), count(getProbeRows('actors'))];
    setProbeSeed();
    $out['resend'] = getProbeRivals(array_fill(0, 4, [2, getProbeKey(1), 4]));
    $out['same'] = [getProbeSum('shop', 1), count(getProbeRows('votes'))];
    setProbeSeed();
    $out['crowd'] = getProbeRivals([[2, getProbeKey(1), 5], [3, getProbeKey(2), 4], [4, getProbeKey(3), 3], [5, getProbeKey(4), 2], [6, getProbeKey(5), 1]]);
    $out['all'] = [getProbeSum('shop', 1), count(getProbeRows('votes')), count(getProbeRows('targets'))];
    $out['marks'] = [count(getProbeMarks()), Cache::checkWriteGuard()];
    return $out;
}

# The decision of the real page cache for a cacheable route, which has to follow the guard journal
function getProbeRoute(): array {
    global $name, $op, $home, $theme, $conf;
    $conf['cache'] = 1;
    putenv('HTTP_HOST='.strtolower((string)parse_url((string)$conf['homeurl'], PHP_URL_HOST)));
    $_SERVER['REQUEST_URI'] = '/index.php?name=news';
    $_GET = ['name' => 'news'];
    $name = 'news';
    $op = '';
    $home = 0;
    $theme = $theme ?? getTheme();
    $first = checkPageCache();
    $hash = getPageHash();
    Cache::addEpoch(true);
    return ['cache' => $first, 'vars' => getCacheRouteVars(), 'kept' => getPageHash() === $hash, 'moved' => getPageHash(true) !== $hash, 'memo' => checkPageCache() === $first];
}

# Render one cacheable page through the real head and foot of the core, which is where the page cache is read and filled
# The session, referer and statistics writers are switched off, so the run reads the stand and writes nothing but the scratch cache
function getProbeRender(string $case): void {
    global $name, $op, $home, $theme, $conf;
    $conf['cache'] = 1;
    $conf['session'] = 0;
    $conf['referers']['refer'] = 0;
    $conf['statistic']['stat'] = 0;
    $conf['name'] = 'news';
    putenv('HTTP_HOST='.strtolower((string)parse_url((string)$conf['homeurl'], PHP_URL_HOST)));
    $_SERVER['REQUEST_URI'] = '/index.php?name=news';
    $_GET = ['name' => 'news'];
    $name = 'news';
    $op = '';
    $home = 0;
    $theme = $theme ?? getTheme();
    setHead(['title' => 'probe']);
    echo 'probe body '.$case;
    if ($case === 'moved') Cache::addEpoch(true);
    setFoot();
}

# The stored pages of the scratch cache
function getProbePages(): array {
    return array_values(preg_grep('/\.html$/', is_dir(CACHE_DIR.'/pages/html') ? scandir(CACHE_DIR.'/pages/html') : []));
}

# The page cache and the journal together: a cacheable route is cacheable while no marker stands, stops being one while a writer holds its guard, and is one again afterwards
function getProbePage(): array {
    $out = ['free' => getProbeChild('route')];
    $guard = Cache::getWriteGuard();
    $out['held'] = getProbeChild('route');
    Cache::deleteWriteGuard($guard);
    $out['after'] = getProbeChild('route');
    return $out;
}

# One real render of the cacheable page in a process of its own: which body the visitor was given, and how many pages the cache holds afterwards
function getProbeShow(string $case): array {
    $html = getProbeText('render', [$case]);
    return [preg_match('/probe body ([a-z]+)/', $html, $hit) ? $hit[1] : '', count(getProbePages())];
}

# The fill and the read of the page cache through the real head and foot: a generation that moved during the render and an open guard both keep the page out of the cache
# A stored page is served again as it is, is not served while a marker stands, and gives way to a new render once the generation moved on
function getProbeFill(): array {
    deleteProbeTree(CACHE_DIR.'/pages');
    $out = ['moved' => getProbeShow('moved')];
    $guard = Cache::getWriteGuard();
    $out['held'] = getProbeShow('held');
    Cache::deleteWriteGuard($guard);
    $out['plain'] = getProbeShow('plain');
    $out['again'] = getProbeShow('again');
    $guard = Cache::getWriteGuard();
    $out['fresh'] = getProbeShow('fresh');
    Cache::deleteWriteGuard($guard);
    $out['cached'] = getProbeShow('cached');
    Cache::addEpoch(true);
    $out['later'] = getProbeShow('later');
    return $out;
}

$mode = (string)($argv[2] ?? '');
if ($mode === 'rival') {
    echo json_encode(getProbeRival((string)$argv[3], (float)$argv[4], (int)$argv[5], (string)$argv[6], (int)$argv[7]));
    exit;
}
if ($mode === 'lost') {
    echo json_encode(getProbeLost((string)$argv[3]));
    exit;
}
if ($mode === 'peek') {
    echo json_encode([Cache::checkWriteGuard()]);
    exit;
}
if ($mode === 'hold') {
    echo json_encode([is_resource(Cache::getWriteGuard())]);
    exit;
}
if ($mode === 'render') {
    getProbeRender((string)($argv[4] ?? ''));
    exit;
}
if ($mode === 'route') {
    echo json_encode(getProbeRoute());
    exit;
}

$report = ['error' => '', 'clean' => false, 'runs' => []];

try {
    deleteProbeTree(CACHE_DIR);
    foreach ([LOGS_DIR.'/error_site.log', COUNTER_DIR.'/cache.log'] as $file) {
        if (is_file($file)) unlink($file);
    }
    addProbeSchema();
    $report['runs'] = [
        'cache' => getProbeCache(),
        'config' => getProbeConfig(),
        'actor' => getProbeActor(),
        'input' => getProbeInput(),
        'scale' => getProbeScale(),
        'carry' => getProbeCarry(),
        'who' => getProbeWho(),
        'period' => getProbePeriod(),
        'request' => getProbeRequest(),
        'own' => getProbeOwn(),
        'annul' => getProbeAnnul(),
        'foreign' => getProbeForeign(),
        'fail' => getProbeFail(),
        'commit' => getProbeCommit(),
        'race' => getProbeRace(),
        'page' => getProbePage(),
        'fill' => getProbeFill(),
    ];
    $report['point'] = [class_exists('Point', false), array_sum(array_map('intval', getProbeSide()->query('SELECT points FROM '.PREFIX_DB.'_users')->fetchAll(PDO::FETCH_COLUMN)))];
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}

$report['clean'] = deleteProbeSchema();

echo json_encode($report, JSON_INVALID_UTF8_SUBSTITUTE);
