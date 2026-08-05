<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the Privat class of docs/PRIVAT-2026.md
# It boots the real core the way index.php does, then drives the class against a disposable schema that carries the two shipped tables it talks to
# The fixture mailbox is written state by state rather than through the class, so every predicate of the plan is read against rows whose expected answer is known in advance
# Every scenario reseeds that mailbox, so the order the scenarios run in cannot decide what any of them sees
# Nothing touches the site database: the probe creates its own schema, works only in it, and drops it again
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The accounts the fixture mailbox belongs to
const PROBEUSER = [2 => 'anna', 3 => 'boris', 4 => 'clara'];

# The fixture mailbox as id => [uidin, uidout, viewed, saved, delin, delout], one row per state the predicates have to tell apart
const PROBEMAIL = [
    1 => [2, 3, 0, 0, 0, 0],
    2 => [2, 3, 1, 0, 0, 0],
    3 => [2, 3, 1, 1, 0, 0],
    4 => [2, 3, 0, 1, 0, 0],
    5 => [3, 2, 0, 0, 0, 0],
    6 => [2, 4, 0, 0, 1, 0],
    7 => [4, 2, 0, 0, 0, 1],
    8 => [4, 3, 0, 0, 0, 0],
];

$GLOBALS['pname'] = 'slaed_pm_'.bin2hex(random_bytes(4));
$GLOBALS['pdb'] = null;

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    return new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# One shipped CREATE TABLE out of the fresh schema, filled for the disposable database
# The class must be driven against the table the installation really carries, so the definition is read from setup/sql/table.sql and never written out here
function getProbeTable(string $name): string {
    $text = (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_'.$name.'`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no '.$name.' table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $hit[0]);
}

# Open a second connection to the disposable schema, which is the concurrent session the lock protocol has to be proved against
function getProbeSide(): PDO {
    global $conf;
    $dsn = 'mysql:host='.$conf['db']['host'].';dbname='.$GLOBALS['pname'].';charset=utf8mb4';
    return new PDO($dsn, $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the disposable schema with the two tables the class reads and connect the project database facade to it
function addProbeSchema(): Database {
    global $conf;
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `'.$GLOBALS['pname'].'`');
    $root->exec(getProbeTable('privat'));
    $root->exec(getProbeTable('users'));
    foreach (PROBEUSER as $id => $name) {
        $root->exec('INSERT INTO `'.PREFIX_DB.'_users` (`id`, `name`, `email`, `password`, `block`, `warnings`, `field`, `avatar`)'
            .' VALUES ('.intval($id).', \''.$name.'\', \''.$name.'@probe.test\', \'x\', \'\', \'\', \'\', \''.$name.'.png\')');
    }
    $GLOBALS['pdb'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $GLOBALS['pname']);
    return $GLOBALS['pdb'];
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

# Reset the fixture mailbox to its documented starting state, so no scenario inherits what another one wrote
function setProbeSeed(): void {
    $pdb = $GLOBALS['pdb'];
    $pdb->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat');
    $sql = 'INSERT INTO '.PREFIX_DB.'_privat (id, uidin, uidout, title, body, time, ip, viewed, saved, delin, delout)'
        .' VALUES (:id, :uin, :uout, :tit, :txt, :tim, :ip, :seen, :keep, :din, :dout)';
    foreach (PROBEMAIL as $id => $one) {
        $pdb->getSqlQuery($sql, [
            'id' => $id, 'uin' => $one[0], 'uout' => $one[1], 'tit' => 'probe title '.$id, 'txt' => 'probe body '.$id,
            'tim' => date('Y-m-d H:i:s', strtotime('2026-01-01 12:00:00') + $id * 60), 'ip' => '127.0.0.1',
            'seen' => $one[2], 'keep' => $one[3], 'din' => $one[4], 'dout' => $one[5],
        ]);
    }
}

# Build the class over the disposable schema, with the module settings one scenario needs put in front of the shipped ones
function getProbeMail(array $sett = []): Privat {
    global $conf;
    return new Privat($GLOBALS['pdb'], ['privat' => $sett + $conf['privat']]);
}

# The four state columns of one stored row, or an empty array when the row is gone
function getProbeRow(int $id): array {
    $pdb = $GLOBALS['pdb'];
    $row = $pdb->getSqlRow($pdb->getSqlQuery('SELECT viewed, saved, delin, delout FROM '.PREFIX_DB.'_privat WHERE id = :id', ['id' => $id]));
    return $row ? [intval($row['viewed']), intval($row['saved']), intval($row['delin']), intval($row['delout'])] : [];
}

# The ids one list answers, in the order it answers them
function getProbeIds(array $out): array {
    return array_map('intval', array_column($out['rows'], 'id'));
}

# Every mailbox read of the plan against the fixture: the three boxes, both counters, the preview, the pager and the guest
function getProbeBoxes(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $page = getProbeMail(['num' => 1]);
    $none = $prv->getMessageList(0, PrivatBox::Inbox);
    $one = $page->getMessageList(2, PrivatBox::Inbox);
    return [
        'inbox' => getProbeIds($prv->getMessageList(2, PrivatBox::Inbox)),
        'saved' => getProbeIds($prv->getMessageList(2, PrivatBox::Saved)),
        'outbox' => getProbeIds($prv->getMessageList(2, PrivatBox::Outbox)),
        'clara' => getProbeIds($prv->getMessageList(4, PrivatBox::Inbox)),
        'count' => [$prv->getMessageCount(2, PrivatBox::Inbox), $prv->getMessageCount(2, PrivatBox::Saved), $prv->getMessageCount(2, PrivatBox::Outbox)],
        'badge' => $prv->getUnreadCount(2),
        'badgeout' => [$prv->getUnreadOutCount(2), $prv->getUnreadOutCount(3), $prv->getUnreadOutCount(0)],
        'recent' => array_map('intval', array_column($prv->getRecentList(2), 'id')),
        'mate' => array_column($prv->getMessageList(2, PrivatBox::Inbox)['rows'], 'name'),
        'guest' => [$prv->getMessageCount(0, PrivatBox::Inbox), $prv->getUnreadCount(0), count($prv->getRecentList(0)), count(getProbeIds($none))],
        'pages' => [$one['pages'], getProbeIds($page->getMessageList(2, PrivatBox::Inbox, 1)), getProbeIds($page->getMessageList(2, PrivatBox::Inbox, 2))],
    ];
}

# One message as each of its two participants reads it, and every side that must answer nothing at all
function getProbeViews(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $own = $prv->getMessageView(2, 1, PrivatBox::Inbox);
    $sent = $prv->getMessageView(2, 5, PrivatBox::Outbox);
    return [
        'own' => [intval($own['partner'] ?? 0), (string)($own['name'] ?? ''), (string)($own['body'] ?? '')],
        'sent' => [intval($sent['partner'] ?? 0), (string)($sent['name'] ?? '')],
        'gone' => $prv->getMessageView(2, 6, PrivatBox::Inbox),
        'sentgone' => $prv->getMessageView(2, 7, PrivatBox::Outbox),
        'wrongside' => $prv->getMessageView(2, 5, PrivatBox::Inbox),
        'foreign' => $prv->getMessageView(2, 8, PrivatBox::Inbox),
        'guest' => $prv->getMessageView(0, 1, PrivatBox::Inbox),
    ];
}

# Every refusal of the send path and the one accepted send, whose id is checked against the row that was really written
function getProbeSend(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $out = [];
    $out['nolog'] = $prv->addMessage(0, 'anna', 'title', 'body', '127.0.0.1')['error'];
    $out['noname'] = $prv->addMessage(3, '  ', 'title', 'body', '127.0.0.1')['error'];
    $out['unknown'] = $prv->addMessage(3, 'nobody', 'title', 'body', '127.0.0.1')['error'];
    $out['notitle'] = $prv->addMessage(3, 'anna', '   ', 'body', '127.0.0.1')['error'];
    $out['nobody'] = $prv->addMessage(3, 'anna', 'title', '   ', '127.0.0.1')['error'];
    $out['long'] = $prv->addMessage(3, 'anna', 'title', 'word '.str_repeat('a', 101), '127.0.0.1')['error'];
    $out['utf'] = getProbeMail(['letter' => 100])->addMessage(3, 'anna', 'title', str_repeat('я', 60), '127.0.0.1')['error'];
    setProbeSeed();
    $out['self'] = getProbeMail(['himself' => 1])->addMessage(2, 'anna', 'title', 'body', '127.0.0.1')['error'];
    $out['selfok'] = getProbeMail(['himself' => 0])->addMessage(2, 'anna', 'title', 'body', '127.0.0.1')['error'];
    setProbeSeed();
    $out['quota'] = getProbeMail(['messin' => 2])->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'];
    $out['keptquota'] = getProbeMail(['messsav' => 2])->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'];
    setProbeSeed();
    $new = $prv->addMessage(3, 'anna', 'probe subject', 'probe text', '10.0.0.7');
    $out['sent'] = [$new['error'], $new['id'] > 8];
    $pdb = $GLOBALS['pdb'];
    $row = $pdb->getSqlRow($pdb->getSqlQuery('SELECT uidin, uidout, title, body, ip, viewed, saved, delin, delout FROM '.PREFIX_DB.'_privat WHERE id = :id', ['id' => $new['id']]));
    $out['stored'] = $row ? [intval($row['uidin']), intval($row['uidout']), (string)$row['title'], (string)$row['body'], (string)$row['ip'], intval($row['viewed'])] : [];
    $out['flood'] = $prv->addMessage(3, 'anna', 'probe subject', 'probe text', '10.0.0.7')['error'];
    $out['nowait'] = getProbeMail(['send' => 0])->addMessage(3, 'anna', 'probe subject', 'probe text', '10.0.0.7')['error'];
    return $out;
}

# Read, unread and save as three independent states, and every batch that has to be refused whole
function getProbeFlags(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $out = [];
    $out['read'] = [$prv->setMessageRead(2, [1], true), getProbeRow(1)];
    $out['unread'] = [$prv->setMessageRead(2, [1], false), getProbeRow(1)];
    $out['keep'] = [$prv->setMessageSaved(2, [2], true), getProbeRow(2)];
    $out['drop'] = [$prv->setMessageSaved(2, [2], false), getProbeRow(2)];
    $out['bulk'] = [$prv->setMessageRead(2, [1, 2], true), getProbeRow(1), getProbeRow(2)];
    setProbeSeed();
    $out['sender'] = [$prv->setMessageRead(2, [5], true), getProbeRow(5)];
    $out['mixed'] = [$prv->setMessageRead(2, [1, 5], true), getProbeRow(1)];
    $out['unknown'] = [$prv->setMessageRead(2, [1, 999], true), getProbeRow(1)];
    $out['deleted'] = [$prv->setMessageRead(2, [6], true), getProbeRow(6)];
    $out['empty'] = $prv->setMessageRead(2, [], true);
    $out['zero'] = $prv->setMessageRead(2, [0], true);
    $out['huge'] = $prv->setMessageRead(2, range(1, 101), true);
    $out['guest'] = $prv->setMessageRead(0, [1], true);
    return $out;
}

# The saved quota at its boundary, over what a batch would really add rather than over its size
function getProbeQuota(): array {
    setProbeSeed();
    $out = [];
    $out['full'] = [getProbeMail(['messsav' => 2])->setMessageSaved(2, [1], true), getProbeRow(1)];
    $out['again'] = [getProbeMail(['messsav' => 2])->setMessageSaved(2, [3], true), getProbeRow(3)];
    $out['room'] = [getProbeMail(['messsav' => 3])->setMessageSaved(2, [1], true), getProbeRow(1)];
    return $out;
}

# One participant deleting their copy, which must leave the other participant's copy exactly as it was
function getProbeDelete(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $out = [];
    $gone = $prv->deleteMessage(2, [1], PrivatBox::Inbox);
    $out['recipient'] = [$gone, getProbeRow(1), getProbeIds($prv->getMessageList(2, PrivatBox::Inbox)), getProbeIds($prv->getMessageList(3, PrivatBox::Outbox))];
    $out['both'] = [$prv->deleteMessage(3, [1], PrivatBox::Outbox), getProbeRow(1)];
    setProbeSeed();
    $out['wasread'] = [$prv->deleteMessage(3, [2], PrivatBox::Outbox), getProbeRow(2)];
    $out['wassaved'] = getProbeIds($prv->getMessageList(3, PrivatBox::Outbox));
    setProbeSeed();
    $out['foreign'] = [$prv->deleteMessage(4, [1], PrivatBox::Inbox), getProbeRow(1)];
    $out['wrongside'] = [$prv->deleteMessage(2, [1], PrivatBox::Outbox), getProbeRow(1)];
    $out['saved'] = [$prv->deleteMessage(2, [3], PrivatBox::Saved), getProbeRow(3), getProbeIds($prv->getMessageList(2, PrivatBox::Saved))];
    return $out;
}

# The mailbox cleanup of a deleted account: both of its own sides go, the counterpart keeps a readable copy
function getProbeUser(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $out = ['done' => $prv->deleteUser(4)];
    $out['erased'] = [getProbeRow(6), getProbeRow(7)];
    $out['kept'] = getProbeRow(8);
    $out['mate'] = getProbeIds($prv->getMessageList(3, PrivatBox::Outbox));
    $out['other'] = [getProbeIds($prv->getMessageList(2, PrivatBox::Inbox)), getProbeIds($prv->getMessageList(2, PrivatBox::Saved))];
    $out['guest'] = $prv->deleteUser(0);
    return $out;
}

# The administrator list, which filters no state, and the one deletion that answers to none either
function getProbeAdmin(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $list = $prv->getAdminList(1);
    $out = ['total' => $list['total'], 'ids' => getProbeIds($list)];
    $out['state'] = [];
    foreach ($list['rows'] as $row) {
        $out['state'][$row['id']] = $row['state'];
    }
    $out['names'] = [(string)($list['rows'][0]['namein'] ?? ''), (string)($list['rows'][0]['nameout'] ?? '')];
    $out['erased'] = [$prv->deleteAdminMessage([6]), getProbeRow(6)];
    $out['zero'] = $prv->deleteAdminMessage([0]);
    return $out;
}

# A statement the server refuses has to take its own transaction with it and leave the connection usable for the next caller
# A trigger that signals on every write is what makes that failure happen at a known moment instead of being waited for
function getProbeFail(): array {
    setProbeSeed();
    $prv = getProbeMail(['send' => 0]);
    $pdb = $GLOBALS['pdb'];
    $tab = PREFIX_DB.'_privat';
    $out = [];
    $pdb->getSqlQuery('CREATE TRIGGER probestopw BEFORE UPDATE ON '.$tab.' FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe\'');
    $out['read'] = [$prv->setMessageRead(2, [1], true), getProbeRow(1), $pdb->checkSqlActive()];
    $out['keep'] = [$prv->setMessageSaved(2, [1], true), getProbeRow(1), $pdb->checkSqlActive()];
    $out['drop'] = [$prv->deleteMessage(2, [1], PrivatBox::Inbox), getProbeRow(1), $pdb->checkSqlActive()];
    $out['user'] = [$prv->deleteUser(3), getProbeRow(5), $pdb->checkSqlActive()];
    $pdb->getSqlQuery('DROP TRIGGER probestopw');
    $pdb->getSqlQuery('CREATE TRIGGER probestopi BEFORE INSERT ON '.$tab.' FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe\'');
    $out['send'] = [$prv->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'], $pdb->checkSqlActive()];
    $pdb->getSqlQuery('DROP TRIGGER probestopi');
    $out['after'] = [$prv->setMessageRead(2, [1], true), getProbeRow(1)];
    return $out;
}

# Count the attempts of the send path in a session variable, which is the one counter a rolled back attempt cannot take back with it
function setProbeTry(): void {
    $GLOBALS['pdb']->getSqlQuery('SET @probetry = 0');
}

# How many times the insert of the send path has been reached since the counter was last reset
function getProbeTry(): int {
    $pdb = $GLOBALS['pdb'];
    $row = $pdb->getSqlRow($pdb->getSqlQuery('SELECT @probetry AS num'));
    return $row ? intval($row['num']) : -1;
}

# Arm the insert of the message table with a body that counts every attempt and fails the ones the scenario wants failed
function addProbeTrigger(string $body): void {
    setProbeTry();
    $GLOBALS['pdb']->getSqlQuery('CREATE TRIGGER probetry BEFORE INSERT ON '.PREFIX_DB.'_privat FOR EACH ROW BEGIN SET @probetry = @probetry + 1; '.$body.' END');
}

# Disarm the insert again, which must happen outside a transaction because the server commits one before it changes a schema
function deleteProbeTrigger(): void {
    $GLOBALS['pdb']->getSqlQuery('DROP TRIGGER probetry');
}

# The lock protocol against a second session that holds the account row: a send and a save that reads a quota both have to wait for it, and every write that reads none does not
# The wait is bounded to a second on both sides, so a lock the class fails to take shows up as a refusal instead of as a hanging probe
function getProbeLock(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $prv = getProbeMail(['send' => 0]);
    $side = getProbeSide();
    $row = $pdb->getSqlRow($pdb->getSqlQuery('SELECT @@innodb_lock_wait_timeout AS num'));
    $wait = $row ? intval($row['num']) : 50;
    $pdb->getSqlQuery('SET SESSION innodb_lock_wait_timeout = 1');
    $side->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $side->beginTransaction();
    $side->query('SELECT id FROM '.PREFIX_DB.'_users WHERE id = 2 FOR UPDATE')->fetchAll();
    $out = [];
    $out['send'] = [$prv->addMessage(3, 'anna', 'locked title', 'locked body', '127.0.0.1')['error'], $pdb->checkSqlActive()];
    $out['keep'] = [$prv->setMessageSaved(2, [1], true), getProbeRow(1), $pdb->checkSqlActive()];
    $out['free'] = [$prv->setMessageSaved(2, [1], false), getProbeRow(1)];
    $out['held'] = $prv->getMessageCount(2, PrivatBox::Inbox);
    $side->rollBack();
    $out['after'] = $prv->addMessage(3, 'anna', 'free title', 'free body', '127.0.0.1')['error'];
    $out['count'] = $prv->getMessageCount(2, PrivatBox::Inbox);
    $pdb->getSqlQuery('SET SESSION innodb_lock_wait_timeout = '.$wait);
    return $out;
}

# The one retry the plan grants a send: a first attempt the server broke off is repeated, a refusal that is not a deadlock is not, and a joined transaction is never retried at all
function getProbeRetry(): array {
    setProbeSeed();
    $pdb = $GLOBALS['pdb'];
    $prv = getProbeMail(['send' => 0]);
    $hard = 'SIGNAL SQLSTATE \'40001\' SET MESSAGE_TEXT = \'probe deadlock\', MYSQL_ERRNO = 1213;';
    $out = [];
    addProbeTrigger('IF @probetry = 1 THEN '.$hard.' END IF;');
    $new = $prv->addMessage(3, 'anna', 'retry title', 'retry body', '127.0.0.1');
    $out['won'] = [$new['error'], $new['id'] > 8, getProbeTry(), $pdb->checkSqlActive()];
    deleteProbeTrigger();
    addProbeTrigger($hard);
    $out['lost'] = [$prv->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'], getProbeTry(), $pdb->checkSqlActive()];
    setProbeTry();
    $pdb->setSqlBegin();
    $out['joined'] = [$prv->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'], getProbeTry(), $pdb->checkSqlActive()];
    $pdb->setSqlRollback();
    deleteProbeTrigger();
    addProbeTrigger('SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe\';');
    $out['once'] = [$prv->addMessage(3, 'anna', 'title', 'body', '127.0.0.1')['error'], getProbeTry()];
    deleteProbeTrigger();
    $out['count'] = $prv->getMessageCount(2, PrivatBox::Inbox);
    return $out;
}

# One competing send, run by a process of its own against the schema of the main run, held back until the moment every rival was given
# Two sends reaching the last free place of one mailbox at once is the only way to read what a quota recheck answers, so it takes real processes
function getProbeRival(string $base, float $when, int $uid, int $room): array {
    global $conf;
    $GLOBALS['pname'] = $base;
    $GLOBALS['pdb'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $base);
    $prv = getProbeMail(['send' => 0, 'messin' => $room, 'messsav' => 250]);
    while (microtime(true) < $when) usleep(200);
    return $prv->addMessage($uid, 'anna', 'rival title', 'rival body', '127.0.0.1');
}

# Two accounts sending into the one free place of the same mailbox at the same moment: exactly one of them may be accepted
# The mailbox is left with the place it had, so a quota that both sends passed shows up as a mailbox holding one message more than it can
function getProbeRace(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $room = $prv->getMessageCount(2, PrivatBox::Inbox) + 1;
    $when = microtime(true) + 3;
    $runs = [];
    $open = [];
    foreach ([3, 4] as $one) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg((string)$GLOBALS['probework']).' rival '
            .escapeshellarg($GLOBALS['pname']).' '.escapeshellarg((string)$when).' '.escapeshellarg((string)$one).' '.escapeshellarg((string)$room);
        $pipe = [];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
        if (is_resource($proc)) $open[] = [$proc, $pipe];
    }
    foreach ($open as [$proc, $pipe]) {
        $text = (string)stream_get_contents($pipe[1]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        proc_close($proc);
        $data = json_decode($text, true);
        $runs[] = is_array($data) ? (string)($data['error'] ?? '') : 'no answer: '.substr($text, 0, 120);
    }
    sort($runs);
    return ['runs' => $runs, 'room' => $room, 'held' => $prv->getMessageCount(2, PrivatBox::Inbox)];
}

# A write that joins a transaction it did not open must leave the commit to whoever did open it
function getProbeTrx(): array {
    setProbeSeed();
    $prv = getProbeMail();
    $pdb = $GLOBALS['pdb'];
    $pdb->setSqlBegin();
    $out = ['done' => $prv->setMessageRead(2, [1], true), 'inside' => getProbeRow(1), 'open' => $pdb->checkSqlActive()];
    $pdb->setSqlRollback();
    $out['after'] = getProbeRow(1);
    return $out;
}

if ((string)($argv[2] ?? '') === 'rival') {
    echo json_encode(getProbeRival((string)$argv[3], (float)$argv[4], intval($argv[5]), intval($argv[6])));
    exit;
}

$report = ['error' => '', 'clean' => false, 'wired' => false, 'runs' => []];

try {
    $report['wired'] = isset($GLOBALS['prv']) && $GLOBALS['prv'] instanceof Privat;
    addProbeSchema();
    $report['runs'] = [
        'boxes' => getProbeBoxes(),
        'views' => getProbeViews(),
        'send' => getProbeSend(),
        'flags' => getProbeFlags(),
        'quota' => getProbeQuota(),
        'delete' => getProbeDelete(),
        'user' => getProbeUser(),
        'admin' => getProbeAdmin(),
        'trx' => getProbeTrx(),
        'fail' => getProbeFail(),
        'lock' => getProbeLock(),
        'retry' => getProbeRetry(),
        'race' => getProbeRace(),
    ];
} catch (Throwable $err) {
    $report['error'] = $err->getMessage();
}

$report['clean'] = deleteProbeSchema();

echo json_encode($report);
