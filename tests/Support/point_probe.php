<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the Point class of docs/node/points.md
# It boots the real core the way index.php does, then drives the class against a disposable schema that carries the shipped account table and the shipped points journal
# Every scenario reseeds the journal and the balances, so the order the scenarios run in cannot decide what any of them sees
# Nothing touches the site database: the probe creates its own schema, works only in it, and drops it again
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/point.php';

# The accounts the journal is written for
const PROBEUSER = [2 => 'anna', 3 => 'boris'];

# The top of the unsigned balance column
const PROBEMAX = 4294967295;

# A database facade whose commit can be made to answer unknown, which is the one outcome a real server cannot be asked to produce on demand
final class ProbeBase extends Database {
    public bool $deny = false;

    # Refuse the commit while the switch is on, after taking the transaction back so the connection stays usable for the next scenario
    function setSqlCommit(): bool {
        if (!$this->deny) return parent::setSqlCommit();
        parent::setSqlRollback();
        return false;
    }
}

$GLOBALS['pname'] = 'slaed_pt_'.bin2hex(random_bytes(4));
$GLOBALS['pconf'] = (require BASE_DIR.'/config/points.php')['points'];
$GLOBALS['pdb'] = null;

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    return new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# One shipped CREATE TABLE out of the fresh schema, filled for the disposable database, so the class is driven against the table an installation really carries
function getProbeTable(string $name): string {
    $text = (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $hit[0]);
}

# Open a second connection to the disposable schema: the concurrent session of the lock scenarios and the independent reader of every persistent result
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

# Create the disposable schema with the two shipped tables the class talks to and connect the project database facade to it
function addProbeSchema(): void {
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    $root->exec(getProbeTable('users'));
    $root->exec(getProbeTable('points'));
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

# Reset the journal, the balances and the marker column of the main action, compensations first because the journal refuses to lose a referenced origin
function setProbeSeed(int $bal = 0): void {
    $pdb = $GLOBALS['pdb'];
    $pdb->getSqlQuery('DELETE FROM '.PREFIX_DB.'_points ORDER BY id DESC');
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET points = :bal, `rank` = \'\'', ['bal' => $bal]);
}

# The shipped points scope with single rules replaced key by key, which is how every scenario states the one setting it is about
function getProbeConf(array $over = [], string $active = '1'): array {
    $out = $GLOBALS['pconf'];
    $out['active'] = $active;
    foreach ($over as $name => $rule) $out['actions'][$name] = $rule + $out['actions'][$name];
    return $out;
}

# Build the class over the disposable schema, or over another facade one scenario brought along
function getProbePoint(array $over = [], string $active = '1', ?Database $pdb = null): Point {
    return new Point($pdb ?? $GLOBALS['pdb'], getProbeConf($over, $active));
}

# Every journal row in the order it was written, read through a connection of its own so only what was really committed is seen
function getProbeRows(): array {
    $sql = 'SELECT uid, aid, action, scope, mid, source, points, rid, note FROM '.PREFIX_DB.'_points ORDER BY id';
    $out = [];
    foreach (getProbeSide()->query($sql)->fetchAll(PDO::FETCH_NUM) as $row) {
        $out[] = [intval($row[0]), intval($row[1]), $row[2], $row[3], intval($row[4]), $row[5], intval($row[6]), $row[7] === null ? null : intval($row[7]), $row[8]];
    }
    return $out;
}

# The points column of every journal row, which is all most scenarios need to read
function getProbeSums(): array {
    return array_column(getProbeRows(), 6);
}

# The committed balance of one account, read through a connection of its own
function getProbeBal(int $uid = 2): int {
    return intval(getProbeSide()->query('SELECT points FROM '.PREFIX_DB.'_users WHERE id = '.$uid)->fetchColumn());
}

# The committed marker of the main action an owner wrote before it called the class
function getProbeMark(int $uid = 3): string {
    return (string)getProbeSide()->query('SELECT `rank` FROM '.PREFIX_DB.'_users WHERE id = '.$uid)->fetchColumn();
}

# Write the main action of an owner inside its open transaction
function setProbeMark(Database $pdb, string $mark): void {
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET `rank` = :mark WHERE id = 3', ['mark' => $mark]);
}

# Whether the site log of this run carries a line with the given text
function checkProbeLog(string $text): bool {
    $file = LOGS_DIR.'/error_site.log';
    return is_file($file) && str_contains((string)file_get_contents($file), $text);
}

# Whether one points scope is accepted: a zero reward of a valid scope succeeds without a statement, and a switched off class refuses everything
function checkProbeConf(array $conf): bool {
    return (new Point($GLOBALS['pdb'], $conf))->addEvent('view', 'probe', 'conf:1', 2);
}

# The strict configuration: the shipped scope, every bound with its neighbour, every foreign or missing key, and what a broken scope does to the class
function getProbeConfig(): array {
    $good = getProbeConf();
    $less = $good;
    unset($less['actions']['login']);
    $more = $good;
    $more['actions']['rate'] = ['points' => '1', 'period' => '0', 'limit' => '0'];
    $swap = $less;
    $swap['actions']['rate'] = ['points' => '1', 'period' => '0', 'limit' => '0'];
    $thin = $good;
    unset($thin['actions']['publish']['limit']);
    $list = [
        'shipped' => $good,
        'off' => getProbeConf([], '0'),
        'activeint' => ['active' => 1] + $good,
        'activebool' => ['active' => true] + $good,
        'activetwo' => ['active' => '2'] + $good,
        'activepad' => ['active' => ' 1'] + $good,
        'rootmore' => $good + ['extra' => '1'],
        'rootless' => ['active' => '1'],
        'flat' => ['active' => '1', 'actions' => '1'],
        'less' => $less,
        'more' => $more,
        'swap' => $swap,
        'thin' => $thin,
        'wide' => getProbeConf(['publish' => ['bonus' => '1']]),
        'sumtop' => getProbeConf(['publish' => ['points' => '1000']]),
        'sumover' => getProbeConf(['publish' => ['points' => '1001']]),
        'sumint' => getProbeConf(['publish' => ['points' => 10]]),
        'sumsign' => getProbeConf(['publish' => ['points' => '-1']]),
        'sumplus' => getProbeConf(['publish' => ['points' => '+1']]),
        'sumzero' => getProbeConf(['publish' => ['points' => '01']]),
        'sumpad' => getProbeConf(['publish' => ['points' => '1 ']]),
        'sumline' => getProbeConf(['publish' => ['points' => "1\n"]]),
        'sumfloat' => getProbeConf(['publish' => ['points' => '1.0']]),
        'sumvoid' => getProbeConf(['publish' => ['points' => '']]),
        'perlow' => getProbeConf(['publish' => ['period' => '60', 'limit' => '1']]),
        'perunder' => getProbeConf(['publish' => ['period' => '59', 'limit' => '1']]),
        'pertop' => getProbeConf(['publish' => ['period' => '31536000', 'limit' => '1']]),
        'perover' => getProbeConf(['publish' => ['period' => '31536001', 'limit' => '1']]),
        'limtop' => getProbeConf(['publish' => ['period' => '60', 'limit' => '10000']]),
        'limover' => getProbeConf(['publish' => ['period' => '60', 'limit' => '10001']]),
        'free' => getProbeConf(['publish' => ['period' => '0', 'limit' => '0']]),
        'peronly' => getProbeConf(['publish' => ['period' => '60', 'limit' => '0']]),
        'limonly' => getProbeConf(['publish' => ['period' => '0', 'limit' => '1']]),
        'adjustsum' => getProbeConf(['adjust' => ['points' => '1']]),
        'adjustlim' => getProbeConf(['adjust' => ['period' => '60', 'limit' => '1']]),
    ];
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $dead = new Point($pdb, $list['sumover']);
    $pdb->setSqlBegin();
    $adm = ['aid' => 1, 'note' => 'x', 'points' => 5];
    $gone = [$dead->addEvent('publish', 'probe', 'node:1', 2), $dead->getEventId('publish', 'probe', 'node:1', 2), $dead->addEvent('adjust', 'probe', 'adm:1', 2, $adm)];
    $pdb->setSqlRollback();
    return ['valid' => array_map('checkProbeConf', $list), 'dead' => $gone, 'rows' => getProbeRows(), 'log' => checkProbeLog('the points configuration is invalid')];
}

# The closed input: every refused key and every refused data value, and the statements all of them together and both kinds of empty reward cost
function getProbeInput(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $adm = ['aid' => 1, 'note' => 'reason', 'points' => 5];
    $num = $pdb->qnum;
    $deny = [
        'rate' => $pnt->addEvent('rate', 'probe', 'node:1', 2),
        'void' => $pnt->addEvent('', 'probe', 'node:1', 2),
        'uidzero' => $pnt->addEvent('publish', 'probe', 'node:1', 0),
        'uidsign' => $pnt->addEvent('publish', 'probe', 'node:1', -1),
        'uidover' => $pnt->addEvent('publish', 'probe', 'node:1', PROBEMAX + 1),
        'scopecase' => $pnt->addEvent('publish', 'Node', 'node:1', 2),
        'scopevoid' => $pnt->addEvent('publish', '', 'node:1', 2),
        'scopelong' => $pnt->addEvent('publish', str_repeat('a', 51), 'node:1', 2),
        'scopeline' => $pnt->addEvent('publish', "node\n", 'node:1', 2),
        'scopeword' => $pnt->addEvent('publish', 'node news', 'node:1', 2),
        'srcvoid' => $pnt->addEvent('publish', 'probe', '', 2),
        'srclong' => $pnt->addEvent('publish', 'probe', str_repeat('a', 65), 2),
        'srchead' => $pnt->addEvent('publish', 'probe', ':1', 2),
        'srcquote' => $pnt->addEvent('publish', 'probe', 'node:1\'', 2),
        'srcutf' => $pnt->addEvent('publish', 'probe', 'нода:1', 2),
        'datakey' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['ip' => '127.0.0.1']),
        'datalist' => $pnt->addEvent('publish', 'probe', 'node:1', 2, [5]),
        'midsign' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['mid' => -1]),
        'midtext' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['mid' => '5']),
        'midover' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['mid' => PROBEMAX + 1]),
        'aidsign' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['aid' => -1]),
        'ridtext' => $pnt->addEvent('publish', 'probe', 'reverse:1', 2, ['rid' => '1']),
        'ridsource' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['rid' => 1]),
        'ridother' => $pnt->addEvent('publish', 'probe', 'reverse:2', 2, ['rid' => 1]),
        'srcreverse' => $pnt->addEvent('publish', 'probe', 'reverse:1', 2),
        'adjustsrc' => $pnt->addEvent('adjust', 'probe', 'reverse:1', 2, $adm),
        'notelong' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['note' => str_repeat('я', 256)]),
        'notehtml' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['note' => 'a <b>bold</b> note']),
        'noteline' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['note' => "two\nlines"]),
        'notelist' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['note' => ['x']]),
        'sumplain' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['points' => 50]),
        'sumnone' => $pnt->addEvent('publish', 'probe', 'node:1', 2, ['points' => 0]),
        'adjustaid' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['aid' => 0] + $adm),
        'adjustnote' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['note' => ' '] + $adm),
        'adjustzero' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['points' => 0] + $adm),
        'adjustbare' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['aid' => 1, 'note' => 'reason']),
        'adjusttext' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['points' => '5'] + $adm),
        'adjustover' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['points' => 1000001] + $adm),
        'adjustunder' => $pnt->addEvent('adjust', 'probe', 'adm:1', 2, ['points' => -1000001] + $adm),
        'adjustrid' => $pnt->addEvent('adjust', 'probe', 'reverse:1', 2, ['rid' => 1] + $adm),
    ];
    $cost = $pdb->qnum - $num;
    $num = $pdb->qnum;
    $free = [
        'zero' => $pnt->addEvent('view', 'node.news', 'node:1', 2, ['mid' => 1]),
        'off' => getProbePoint([], '0')->addEvent('publish', 'node.news', 'node:1', 2),
        'ghost' => $pnt->addEvent('view', 'node.news', 'node:1', 99),
        'badzero' => $pnt->addEvent('view', 'Node', 'node:1', 2),
        'badoff' => getProbePoint([], '0')->addEvent('publish', 'probe', '', 2),
    ];
    return ['deny' => $deny, 'cost' => $cost, 'free' => $free, 'freecost' => $pdb->qnum - $num, 'rows' => getProbeRows()];
}

# An ordinary award: the row it writes, the balance it moves, the permanent uniqueness of its source, and the account that does not exist
function getProbeAward(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $out = ['first' => $pnt->addEvent('publish', 'node.news', 'node:1050', 2, ['mid' => 1050]), 'open' => $pdb->checkSqlActive()];
    $out['again'] = $pnt->addEvent('publish', 'node.news', 'node:1050', 2, ['mid' => 1050]);
    $out['once'] = [getProbeRows(), getProbeBal()];
    $out['next'] = $pnt->addEvent('publish', 'node.news', 'node:1051', 2);
    $out['scope'] = $pnt->addEvent('publish', 'node.pages', 'node:1050', 2);
    $out['mate'] = $pnt->addEvent('publish', 'node.news', 'node:1050', 3);
    $out['ghost'] = $pnt->addEvent('publish', 'node.news', 'node:1050', 99);
    $out['sums'] = getProbeSums();
    $out['bals'] = [getProbeBal(2), getProbeBal(3)];
    $full = ['mid' => 7, 'aid' => 4, 'note' => str_repeat('я', 255)];
    $out['full'] = [$pnt->addEvent('comment', 'forum.topic', 'post:9', 3, $full), array_slice(getProbeRows()[4] ?? [], 0, 8), mb_strlen(getProbeRows()[4][8] ?? '')];
    return $out;
}

# The limit of one action per recipient over a sliding period: shared by every scope, counted on origin awards alone, and measured by the clock of the database
function getProbeLimit(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint(['comment' => ['points' => '5', 'period' => '60', 'limit' => '2'], 'publish' => ['period' => '0', 'limit' => '0']]);
    $out = ['runs' => []];
    foreach ([['account', 'comment:1'], ['shop', 'comment:2'], ['voting', 'comment:3'], ['account', 'comment:4']] as [$scope, $source]) {
        $out['runs'][] = $pnt->addEvent('comment', $scope, $source, 2);
    }
    $out['held'] = [getProbeSums(), getProbeBal()];
    $out['mate'] = [$pnt->addEvent('comment', 'account', 'comment:5', 3), getProbeBal(3)];
    $out['other'] = [$pnt->addEvent('publish', 'node.news', 'node:1', 2), getProbeBal()];
    $pdb->setSqlBegin();
    $rid = $pnt->getEventId('comment', 'account', 'comment:1', 2);
    $out['undo'] = $pnt->addEvent('comment', 'account', 'reverse:'.$rid, 2, ['rid' => intval($rid)]);
    $pdb->setSqlCommit();
    $out['still'] = [$pnt->addEvent('comment', 'account', 'comment:6', 2), getProbeBal()];
    $pdb->getSqlQuery('UPDATE '.PREFIX_DB.'_points SET created = NOW() - INTERVAL 61 SECOND WHERE source = :source', ['source' => 'comment:1']);
    $out['slid'] = [$pnt->addEvent('comment', 'account', 'comment:7', 2), getProbeBal()];
    $out['late'] = [$pnt->addEvent('comment', 'account', 'comment:4', 2), $pnt->addEvent('comment', 'account', 'comment:8', 2), getProbeBal()];
    $loose = getProbePoint(['comment' => ['points' => '5', 'period' => '0', 'limit' => '0']]);
    $out['loose'] = [$loose->addEvent('comment', 'account', 'comment:9', 2), $loose->addEvent('comment', 'account', 'comment:10', 2), getProbeBal()];
    return $out;
}

# A balance at the top of its column refuses what would overflow it, writes nothing, and says so in the site log
function getProbeOver(): array {
    setProbeSeed(PROBEMAX - 5);
    $pnt = getProbePoint();
    $out = ['award' => $pnt->addEvent('publish', 'node.news', 'node:1', 2), 'open' => $GLOBALS['pdb']->checkSqlActive()];
    $out['adjust'] = $pnt->addEvent('adjust', 'account', 'adm:1', 2, ['aid' => 1, 'note' => 'gift', 'points' => 6]);
    $out['held'] = [getProbeRows(), getProbeBal()];
    $out['fits'] = [$pnt->addEvent('adjust', 'account', 'adm:2', 2, ['aid' => 1, 'note' => 'gift', 'points' => 5]), getProbeBal() === PROBEMAX];
    $out['log'] = checkProbeLog('would overflow the balance');
    return $out;
}

# The manual correction: signed, bounded, attributed to an administrator with a reason, never below zero, and unique by its source like every event
function getProbeAdjust(): array {
    setProbeSeed();
    $pnt = getProbePoint([], '0');
    $out = ['gift' => $pnt->addEvent('adjust', 'account', 'adm:1', 2, ['aid' => 1, 'note' => 'a gift', 'points' => 50])];
    $out['again'] = $pnt->addEvent('adjust', 'account', 'adm:1', 2, ['aid' => 1, 'note' => 'a gift', 'points' => 50]);
    $out['row'] = [getProbeRows(), getProbeBal()];
    $out['take'] = [$pnt->addEvent('adjust', 'account', 'adm:2', 2, ['aid' => 1, 'note' => 'a fine', 'points' => -80]), getProbeSums(), getProbeBal()];
    $out['empty'] = [$pnt->addEvent('adjust', 'account', 'adm:3', 2, ['aid' => 1, 'note' => 'a fine', 'points' => -5]), getProbeSums(), getProbeBal()];
    $out['top'] = [$pnt->addEvent('adjust', 'account', 'adm:4', 2, ['aid' => 1, 'note' => 'top', 'points' => 1000000]), getProbeBal()];
    $out['bottom'] = [$pnt->addEvent('adjust', 'account', 'adm:5', 2, ['aid' => 1, 'note' => 'bottom', 'points' => -1000000]), getProbeBal()];
    $out['ghost'] = $pnt->addEvent('adjust', 'account', 'adm:6', 99, ['aid' => 1, 'note' => 'nobody', 'points' => 5]);
    return $out;
}

# The three answers of the origin lookup, told apart: an id, a confirmed nothing, and a refusal, with rewards switched off because a compensation must not care
function getProbeOrigin(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    getProbePoint()->addEvent('publish', 'node.news', 'node:1', 2);
    getProbePoint()->addEvent('adjust', 'account', 'adm:1', 2, ['aid' => 1, 'note' => 'a gift', 'points' => 50]);
    $pnt = getProbePoint([], '0');
    $out = ['bare' => $pnt->getEventId('publish', 'node.news', 'node:1', 2)];
    $pdb->setSqlBegin();
    $out['found'] = $pnt->getEventId('publish', 'node.news', 'node:1', 2);
    $out['none'] = $pnt->getEventId('publish', 'node.news', 'node:2', 2);
    $out['scope'] = $pnt->getEventId('publish', 'node.pages', 'node:1', 2);
    $out['mate'] = $pnt->getEventId('publish', 'node.news', 'node:1', 3);
    $out['ghost'] = $pnt->getEventId('publish', 'node.news', 'node:1', 99);
    $out['adjust'] = $pnt->getEventId('adjust', 'account', 'adm:1', 2);
    $out['rate'] = $pnt->getEventId('rate', 'node.news', 'node:1', 2);
    $out['badscope'] = $pnt->getEventId('publish', 'Node', 'node:1', 2);
    $out['badsource'] = $pnt->getEventId('publish', 'node.news', '', 2);
    $out['baduid'] = $pnt->getEventId('publish', 'node.news', 'node:1', 0);
    $out['open'] = $pdb->checkSqlActive();
    $rid = intval($out['found']);
    $out['undo'] = $pnt->addEvent('publish', 'node.news', 'reverse:'.$rid, 2, ['rid' => $rid]);
    $out['spent'] = $pnt->getEventId('publish', 'node.news', 'reverse:'.$rid, 2);
    $pdb->setSqlCommit();
    return $out;
}

# The compensation: one per origin, only of a positive award of the same recipient, action and scope, for what the balance still holds, recorded even when that is nothing
function getProbeReverse(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    getProbePoint()->addEvent('publish', 'node.news', 'node:1', 2);
    getProbePoint()->addEvent('publish', 'node.news', 'node:2', 2);
    getProbePoint()->addEvent('publish', 'node.news', 'node:3', 2);
    $pnt = getProbePoint([], '0');
    $pnt->addEvent('adjust', 'account', 'adm:1', 2, ['aid' => 1, 'note' => 'a fine', 'points' => -13]);
    $ids = [];
    foreach ($pdb->getSqlRows($pdb->getSqlQuery('SELECT id, source FROM '.PREFIX_DB.'_points')) as $row) $ids[$row['source']] = intval($row['id']);
    $undo = static fn(int $rid, string $action = 'publish', string $scope = 'node.news', int $uid = 2): bool
        => $pnt->addEvent($action, $scope, 'reverse:'.$rid, $uid, ['rid' => $rid, 'aid' => 1]);
    $out = ['start' => getProbeBal()];
    $out['action'] = $undo($ids['node:1'], 'comment');
    $out['scope'] = $undo($ids['node:1'], 'publish', 'node.pages');
    $out['mate'] = $undo($ids['node:1'], 'publish', 'node.news', 3);
    $out['adjust'] = $undo($ids['adm:1'], 'adjust', 'account');
    $out['fine'] = $undo($ids['adm:1'], 'publish', 'account');
    $out['none'] = $undo(999);
    $out['held'] = [count(getProbeRows()), getProbeBal()];
    $out['whole'] = [$undo($ids['node:1']), getProbeBal()];
    $out['twice'] = [$undo($ids['node:1']), getProbeBal()];
    $out['part'] = [$undo($ids['node:2']), getProbeBal()];
    $out['zero'] = [$undo($ids['node:3']), getProbeBal()];
    $out['rows'] = array_map(static fn(array $row): array => [$row[1], $row[6], array_search($row[7], $ids, true)], array_slice(getProbeRows(), 4));
    $last = intval($pdb->getSqlRow($pdb->getSqlQuery('SELECT MAX(id) AS id FROM '.PREFIX_DB.'_points'))['id']);
    $out['chain'] = $undo($last);
    $pnt->addEvent('adjust', 'account', 'adm:2', 2, ['aid' => 1, 'note' => 'a gift', 'points' => 50]);
    $out['refill'] = [$undo($ids['node:3']), getProbeBal()];
    $out['reaward'] = [getProbePoint()->addEvent('publish', 'node.news', 'node:1', 2), getProbeBal(), count(getProbeRows())];
    $out['open'] = $pdb->checkSqlActive();
    return $out;
}

# Inside a transaction of its owner the class neither commits nor rolls back: the award follows whatever the owner decides for the main action
function getProbeJoin(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'dropped');
    $out = ['done' => $pnt->addEvent('publish', 'node.news', 'node:1', 2), 'open' => $pdb->checkSqlActive(), 'unseen' => [getProbeSums(), getProbeBal()]];
    $pdb->setSqlRollback();
    $out['dropped'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'kept');
    $out['redo'] = [$pnt->addEvent('publish', 'node.news', 'node:1', 2), $pnt->addEvent('publish', 'node.news', 'node:1', 2), $pdb->checkSqlActive()];
    $pdb->setSqlCommit();
    $out['kept'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    return $out;
}

# A recoverable failure in the middle of the unit: the journal row is already written when the balance update is refused, so the savepoint has to take the row back
# The main action of the owner survives it and commits, and the same failure without an owner rolls back the transaction the class opened itself
function getProbeFail(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $stop = 'IF NEW.points <> OLD.points THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe\'; END IF';
    $pdb->getSqlQuery('CREATE TRIGGER probestop BEFORE UPDATE ON '.PREFIX_DB.'_users FOR EACH ROW '.$stop);
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'main');
    $out = ['joined' => [$pnt->addEvent('publish', 'node.news', 'node:1', 2), $pdb->checkSqlActive(), $pdb->setSqlCommit()]];
    $out['after'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    $out['own'] = [$pnt->addEvent('publish', 'node.news', 'node:2', 2), $pdb->checkSqlActive(), getProbeSums(), getProbeBal()];
    $pdb->getSqlQuery('DROP TRIGGER probestop');
    $out['healed'] = [$pnt->addEvent('publish', 'node.news', 'node:1', 2), getProbeSums(), getProbeBal()];
    $out['log'] = checkProbeLog('the event was not stored') && checkProbeLog('node:2');
    return $out;
}

# A second session holds the account row: the class gives up as a recoverable refusal, never reaches for the origin row before the account, and works again once the row is free
function getProbeLock(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $pnt->addEvent('publish', 'node.news', 'node:1', 2);
    $row = $pdb->getSqlRow($pdb->getSqlQuery('SELECT @@innodb_lock_wait_timeout AS num'));
    $wait = $row ? intval($row['num']) : 50;
    $pdb->getSqlQuery('SET SESSION innodb_lock_wait_timeout = 1');
    $side = getProbeSide();
    $side->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $side->beginTransaction();
    $side->query('SELECT id FROM '.PREFIX_DB.'_users WHERE id = 2 FOR UPDATE')->fetchAll();
    $out = ['own' => [$pnt->addEvent('publish', 'node.news', 'node:2', 2), $pdb->checkSqlActive()]];
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'main');
    $out['joined'] = [$pnt->addEvent('publish', 'node.news', 'node:3', 2), $pdb->checkSqlActive()];
    $out['origin'] = [$pnt->getEventId('publish', 'node.news', 'node:1', 2), $pdb->checkSqlActive()];
    try {
        $side->query('SELECT id FROM '.PREFIX_DB.'_points WHERE source = \'node:1\' FOR UPDATE')->fetchAll();
        $out['order'] = true;
    } catch (Throwable) {
        $out['order'] = false;
    }
    $side->rollBack();
    $out['freed'] = [$pnt->getEventId('publish', 'node.news', 'node:1', 2) > 0, $pnt->addEvent('publish', 'node.news', 'node:3', 2), $pdb->setSqlCommit()];
    $out['after'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    $pdb->getSqlQuery('SET SESSION innodb_lock_wait_timeout = '.$wait);
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

# Everything the class cannot prove: a deadlock, an unknown commit, and a connection that died under an owner whose main action was already written
# Each of them throws instead of answering false, and the persistent result is read by an independent connection afterwards
function getProbeLost(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $pnt = getProbePoint();
    $hard = 'SIGNAL SQLSTATE \'40001\' SET MESSAGE_TEXT = \'probe deadlock\', MYSQL_ERRNO = 1213';
    $pdb->getSqlQuery('CREATE TRIGGER probehard BEFORE INSERT ON '.PREFIX_DB.'_points FOR EACH ROW '.$hard);
    $out = ['hardown' => [getProbeThrow(static fn(): bool => $pnt->addEvent('publish', 'node.news', 'node:1', 2)), $pdb->checkSqlActive()]];
    $pdb->setSqlBegin();
    $out['hardjoin'] = [getProbeThrow(static fn(): bool => $pnt->addEvent('publish', 'node.news', 'node:1', 2)), $pdb->checkSqlActive()];
    $pdb->setSqlRollback();
    $pdb->getSqlQuery('DROP TRIGGER probehard');
    $pdb->deny = true;
    $out['commit'] = [getProbeThrow(static fn(): bool => $pnt->addEvent('publish', 'node.news', 'node:1', 2)), $pdb->checkSqlActive()];
    $pdb->deny = false;
    $out['held'] = [getProbeSums(), getProbeBal()];
    $lone = getProbeBase();
    $ptl = getProbePoint([], '1', $lone);
    $lone->setSqlBegin();
    setProbeMark($lone, 'main');
    $cid = intval($lone->getSqlRow($lone->getSqlQuery('SELECT CONNECTION_ID() AS id'))['id']);
    getProbeSide()->exec('KILL '.$cid);
    $out['killed'] = getProbeThrow(static fn(): bool => $ptl->addEvent('publish', 'node.news', 'node:1', 2));
    $out['origin'] = getProbeThrow(static fn(): int|false => $ptl->getEventId('publish', 'node.news', 'node:1', 2));
    $out['after'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    $out['alive'] = [$pnt->addEvent('publish', 'node.news', 'node:1', 2), getProbeSums(), getProbeBal()];
    return $out;
}

# An owner whose transaction took its snapshot before a rival session committed an award of the same recipient, under one of the two ways a server treats that
# With snapshot isolation the locking read of the account fails and the server drops the whole transaction of the owner, so the class has to throw and the owner repeats everything
# Without it the snapshot hides the rival row from the repeat check, the insert meets the unique key, and the repeat answers its empty success while the owner keeps its transaction
# The limit is counted from that same snapshot by decision, so a limit of one lets the hidden rival award be followed by one more; a locking count would deadlock neighbours instead
function getProbeSnap(int $mode): array {
    setProbeSeed();
    $pdb = getProbeBase();
    if ($pdb->getSqlQuery('SET SESSION innodb_snapshot_isolation = '.$mode) === false && $mode) return ['skip' => true];
    $rule = ['comment' => ['points' => '5', 'period' => '0', 'limit' => '0']];
    $pnt = getProbePoint($rule, '1', $pdb);
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'main');
    $pdb->getSqlRows($pdb->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_points'));
    $out = ['skip' => false, 'rival' => getProbePoint($rule)->addEvent('comment', 'account', 'comment:1', 2)];
    $out['same'] = getProbeThrow(static fn(): bool => $pnt->addEvent('comment', 'account', 'comment:1', 2));
    $out['alive'] = $pdb->getSqlQuery('DO 1') !== false && $pdb->checkSqlActive();
    $tight = getProbePoint(['comment' => ['points' => '5', 'period' => '3600', 'limit' => '1']], '1', $pdb);
    $out['bound'] = $out['alive'] ? $tight->addEvent('comment', 'account', 'comment:3', 2) : null;
    $out['other'] = $out['alive'] ? $pnt->addEvent('comment', 'account', 'comment:2', 2) : null;
    $out['end'] = $out['alive'] ? $pdb->setSqlCommit() : $pdb->setSqlRollback();
    $out['held'] = [getProbeSums(), getProbeBal(), getProbeMark()];
    $pdb->setSqlBegin();
    setProbeMark($pdb, 'again');
    $out['again'] = [$pnt->addEvent('comment', 'account', 'comment:1', 2), $pdb->setSqlCommit(), getProbeSums()[0] ?? 0, getProbeMark()];
    return $out;
}

# The stale snapshot of an owner under both server behaviours, each on a connection of its own; a server without the switch behaves the second way and skips only the first
function getProbeStale(): array {
    return ['on' => getProbeSnap(1), 'off' => getProbeSnap(0)];
}

# One competing event, run by a process of its own against the schema of the main run, held back until the moment every rival was given
function getProbeRival(string $base, float $when, string $source): array {
    $GLOBALS['pname'] = $base;
    $GLOBALS['pdb'] = getProbeBase();
    $pnt = getProbePoint(['comment' => ['points' => '5', 'period' => '3600', 'limit' => '2']]);
    while (microtime(true) < $when) usleep(200);
    return ['done' => getProbeThrow(static fn(): bool => $pnt->addEvent('comment', 'account', $source, 2))];
}

# Start one rival per source at one shared moment and collect what each of them answered
function getProbeRivals(array $list): array {
    $when = microtime(true) + 3;
    $open = [];
    foreach ($list as $source) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg((string)$GLOBALS['probework']).' rival '
            .escapeshellarg($GLOBALS['pname']).' '.escapeshellarg((string)$when).' '.escapeshellarg($source);
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
        $runs[] = is_array($data) ? $data['done'] : 'no answer: '.substr($text, 0, 120);
    }
    return $runs;
}

# Real processes at one moment: four writers of one source leave one row, and four writers of four sources cannot pass a limit of two between them
function getProbeRace(): array {
    setProbeSeed();
    $out = ['same' => getProbeRivals(array_fill(0, 4, 'comment:1'))];
    $out['once'] = [getProbeSums(), getProbeBal()];
    setProbeSeed();
    $out['many'] = getProbeRivals(['comment:1', 'comment:2', 'comment:3', 'comment:4']);
    $out['bound'] = [getProbeSums(), getProbeBal()];
    return $out;
}

if ((string)($argv[2] ?? '') === 'rival') {
    echo json_encode(getProbeRival((string)$argv[3], (float)$argv[4], (string)$argv[5]));
    exit;
}

$report = ['error' => '', 'clean' => false, 'runs' => []];

try {
    if (is_file(LOGS_DIR.'/error_site.log')) unlink(LOGS_DIR.'/error_site.log');
    addProbeSchema();
    $report['runs'] = [
        'config' => getProbeConfig(),
        'input' => getProbeInput(),
        'award' => getProbeAward(),
        'limit' => getProbeLimit(),
        'over' => getProbeOver(),
        'adjust' => getProbeAdjust(),
        'origin' => getProbeOrigin(),
        'reverse' => getProbeReverse(),
        'join' => getProbeJoin(),
        'fail' => getProbeFail(),
        'lock' => getProbeLock(),
        'lost' => getProbeLost(),
        'stale' => getProbeStale(),
        'race' => getProbeRace(),
    ];
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}

$report['clean'] = deleteProbeSchema();

echo json_encode($report);
