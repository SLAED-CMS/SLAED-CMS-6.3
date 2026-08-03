<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the mail queue and drain tests: boots the real core like index.php, one scenario per process, with LOGS_DIR in scratch so nothing writes into the site logs
# Everything it stores is marked with its own kind and removed again, and the report says whether the table was left as it was found
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# Every scenario runs against a disposable schema, because claiming a batch takes whatever is pending in the queue
# On the live database that would mark mail the site is waiting to send, so the tables the mail path reads are copied here and dropped again at the end
# users and groups are copied with their rows, since a campaign selects its audience from them and the expected counts come from real data
$GLOBALS['mailschema'] = 'slaed_mailp_'.bin2hex(random_bytes(4));
$site = (string)$conf['db']['name'];
$seed = new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$seed->exec('CREATE DATABASE `'.$GLOBALS['mailschema'].'` DEFAULT CHARACTER SET utf8mb4');
foreach (['mail' => false, 'maildead' => false, 'newsletter' => false, 'users' => true, 'groups' => true] as $one => $withrows) {
    $from = '`'.$site.'`.`'.PREFIX_DB.'_'.$one.'`';
    $to = '`'.$GLOBALS['mailschema'].'`.`'.PREFIX_DB.'_'.$one.'`';
    $seed->exec('CREATE TABLE '.$to.' LIKE '.$from);
    if ($withrows) $seed->exec('INSERT INTO '.$to.' SELECT * FROM '.$from);
}
$db = $GLOBALS['db'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $GLOBALS['mailschema']);
register_shutdown_function(static function (): void {
    global $conf;
    try {
        $gone = new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $gone->exec('DROP DATABASE IF EXISTS `'.$GLOBALS['mailschema'].'`');
    } catch (Throwable) {
    }
});

# The kind every row this probe stores carries, so its own rows can be found and removed without touching anything the installation queued
const PROBEKIND = 'probe';

# Build one mail service over the site config with the given sections merged in, which is how a scenario chooses a transport and a campaign policy without writing a config file
function getProbeMail(array $sect = [], array $rule = []): Mail {
    global $db, $conf;
    return new Mail($db, array_replace_recursive($conf, ['mail' => $sect, 'newsletter' => $rule]));
}

# Read one queue row as a plain array, or an empty array when it is gone
function getProbeRow(int $id): array {
    global $db;
    $sql = 'SELECT id, kind, sender, email, title, body, ref, prio, tries, status, hold, camp, lockid, error, phase, code, ntime, time FROM '.PREFIX_DB.'_mail WHERE id = :id';
    $row = $db->getSqlRow($db->getSqlQuery($sql, ['id' => $id]));
    return $row ? array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY) : [];
}

# Store one row directly, for the states a scenario needs to start from rather than reach through the public path
function addProbeRow(array $data): int {
    global $db;
    $data += ['kind' => PROBEKIND, 'sender' => 'info@slaed.net', 'email' => 'probe@slaed.net', 'title' => 'probe', 'body' => 'probe body', 'ref' => 0, 'prio' => 3];
    $data += ['tries' => 0, 'status' => 0, 'age' => 0];
    $sql = 'INSERT INTO '.PREFIX_DB.'_mail (kind, sender, email, title, body, ref, prio, tries, status, time, ntime)'
        .' VALUES (:kind, :sender, :email, :title, :body, :ref, :prio, :tries, :status, DATE_SUB(NOW(), INTERVAL :age DAY), NOW())';
    $db->getSqlQuery($sql, $data);
    return intval($db->getSqlLastId());
}

# Remove everything this probe stored and report whether the table is back to the row count it had
# The rows a drain scenario writes carry the kind their resolution needs rather than the probe kind, so they are found by their addresses instead
function deleteProbeRows(int $ntime): bool {
    global $db;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_mail WHERE kind = :kind OR email LIKE :mail', ['kind' => PROBEKIND, 'mail' => '%@slaed.net']);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_newsletter WHERE title = :title', ['title' => 'probe mailing']);
    $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_mail'));
    return intval($row['num'] ?? 0) === $ntime;
}

# Report what the queue write actually stores, and what the claim, the backoff, the attempt cap and the retention window do against the real engine
function getProbeQueue(): array {
    global $db;
    $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_mail'));
    $ntime = intval($row['num'] ?? 0);
    $out = ['rows' => $ntime];
    $mailer = getProbeMail(['transport' => 'smtp', 'host' => '', 'tries' => '3', 'backoff' => '300', 'keep' => '30', 'keepbulk' => '3']);
    $subj = str_repeat("\u{041F}", 255);
    $mesg = ['kind' => PROBEKIND, 'email' => 'probe@slaed.net', 'sender' => 'info@slaed.net', 'title' => $subj, 'body' => 'probe body', 'prio' => 1, 'client' => true];
    $done = $mailer->addQueue($mesg);
    $one = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_mail WHERE kind = :kind ORDER BY id DESC LIMIT 1', ['kind' => PROBEKIND]));
    $qid = intval($one['id'] ?? 0);
    $row = getProbeRow($qid);
    $out['stored'] = [
        'done' => $done,
        'kind' => $row['kind'] ?? '',
        'prio' => intval($row['prio'] ?? 0),
        'status' => intval($row['status'] ?? -1),
        'tries' => intval($row['tries'] ?? -1),
        'subject' => ($row['title'] ?? '') === $subj,
        'client' => str_contains($row['body'] ?? '', '127.0.0.1'),
        'raw' => !str_contains($row['title'] ?? '', '=?'),
    ];
    $mine = getProbeMail();
    $them = getProbeMail();
    $take = $mine->getBatch(50);
    $out['claim'] = ['mine' => count($take), 'them' => count($them->getBatch(50))];
    $held = getProbeRow($qid);
    $out['claim']['marked'] = strlen($held['lockid'] ?? '') === 32;
    $sql = 'UPDATE '.PREFIX_DB.'_mail SET locked = DATE_SUB(NOW(), INTERVAL 2 HOUR), ntime = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = :id';
    $db->getSqlQuery($sql, ['id' => $qid]);
    $out['claim']['stale'] = count(getProbeMail()->getBatch(50));
    $mailer->setResult($qid, false, "550 5.7.1 refused\r\nby policy");
    $row = getProbeRow($qid);
    $wait = $db->getSqlRow($db->getSqlQuery('SELECT TIMESTAMPDIFF(SECOND, NOW(), ntime) AS num FROM '.PREFIX_DB.'_mail WHERE id = :id', ['id' => $qid]));
    $out['retry'] = [
        'status' => intval($row['status'] ?? -1),
        'tries' => intval($row['tries'] ?? -1),
        'error' => $row['error'] ?? '',
        'lock' => $row['lockid'] ?? 'x',
        'wait' => intval($wait['num'] ?? 0),
    ];
    $out['claim']['waiting'] = count(getProbeMail()->getBatch(50));
    $mailer->setResult($qid, false, 'refused again');
    $mailer->setResult($qid, false, 'refused once more');
    $out['cap'] = ['status' => intval(getProbeRow($qid)['status'] ?? -1), 'tries' => intval(getProbeRow($qid)['tries'] ?? -1)];
    $hard = addProbeRow([]);
    $mailer->setResult($hard, false, 'the shared body this message refers to no longer exists', true);
    $out['hard'] = ['status' => intval(getProbeRow($hard)['status'] ?? -1), 'tries' => intval(getProbeRow($hard)['tries'] ?? -1)];
    $done = $mailer->setResult(addProbeRow([]), true);
    $out['accept'] = $done;
    $keep = [
        'oldbulk' => addProbeRow(['kind' => PROBEKIND, 'status' => 1, 'age' => 10]),
        'newbulk' => addProbeRow(['kind' => PROBEKIND, 'status' => 1, 'age' => 1]),
        'oldmail' => addProbeRow(['kind' => PROBEKIND, 'status' => 1, 'age' => 40]),
        'failold' => addProbeRow(['kind' => PROBEKIND, 'status' => 2, 'age' => 40]),
    ];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_mail SET kind = \'newsletter\' WHERE id IN ('.intval($keep['oldbulk']).', '.intval($keep['newbulk']).')');
    $out['prune'] = ['removed' => $mailer->deleteQueue()];
    foreach ($keep as $name => $one) $out['prune'][$name] = getProbeRow($one) !== [];
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_mail WHERE id IN ('.intval($keep['oldbulk']).', '.intval($keep['newbulk']).')');
    $out['clean'] = deleteProbeRows($ntime);
    return $out;
}

# Report what a whole drain run does against a relay that answers: what is delivered, over how many connections, what the message carried and what the rate cap leaves behind
function getProbeDrain(): array {
    global $db;
    $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_mail'));
    $ntime = intval($row['num'] ?? 0);
    $out = ['rows' => $ntime];
    $port = getProbePort();
    $file = LOGS_DIR.'/relay.json';
    $proc = proc_open([PHP_BINARY, BASE_DIR.'/tests/Support/mail_relay.php', (string)$port, $file, '30'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipe);
    if (!is_resource($proc)) return $out + ['error' => 'the probe relay could not be started'];
    usleep(400000);
    $sect = ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => (string)$port, 'secure' => 'none', 'auth' => '0', 'timeout' => '5'];
    $sect += ['batch' => '5', 'rate' => '60', 'tries' => '3'];
    $mailer = getProbeMail($sect);
    $subj = 'Пароль '.str_repeat('и', 60);
    $mailer->addQueue(['kind' => PROBEKIND, 'email' => 'one@slaed.net', 'sender' => 'info@slaed.net', 'title' => $subj, 'body' => '<p>first</p>', 'prio' => 1, 'client' => true]);
    $mailer->addQueue(['kind' => PROBEKIND, 'email' => 'two@slaed.net', 'sender' => 'info@slaed.net', 'title' => 'second', 'body' => '<p>second</p>', 'prio' => 3]);
    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_newsletter (title, body, send, time) VALUES (\'probe mailing\', \'shared probe body\', 0, NOW())');
    $nid = intval($db->getSqlLastId());
    $mailer->addQueue(['kind' => 'newsletter', 'email' => 'three@slaed.net', 'sender' => 'info@slaed.net', 'title' => 'third', 'ref' => $nid]);
    $gone = $mailer->addQueue(['kind' => 'newsletter', 'email' => 'dead@slaed.net', 'sender' => 'info@slaed.net', 'title' => 'fourth', 'ref' => 999999999]);
    $out['run'] = $mailer->updateQueue();
    $sql = 'SELECT email, status, tries, error FROM '.PREFIX_DB.'_mail WHERE email LIKE :mail ORDER BY id ASC';
    $rows = $db->getSqlRows($db->getSqlQuery($sql, ['mail' => '%@slaed.net'])) ?: [];
    $out['state'] = ['queued' => $gone];
    foreach ($rows as $one) $out['state'][$one['email']] = [intval($one['status']), intval($one['tries']), (string)$one['error']];
    $data = json_decode((string)file_get_contents($file), true);
    $out['relay'] = ['links' => intval($data['links'] ?? 0), 'mails' => count($data['mails'] ?? [])];
    $out['sent'] = [];
    foreach ($data['mails'] ?? [] as $mesg) $out['sent'][] = getProbeMessage((string)$mesg);
    $out['refbody'] = false;
    foreach ($out['sent'] as $mesg) {
        if (str_contains($mesg['body'], 'shared probe body')) $out['refbody'] = true;
    }
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_mail WHERE email LIKE :mail', ['mail' => '%@slaed.net']);
    if (is_file(LOGS_DIR.'/scheduler/maildrain.json')) unlink(LOGS_DIR.'/scheduler/maildrain.json');
    $mailer = getProbeMail(array_replace($sect, ['rate' => '1']));
    foreach (['four@slaed.net', 'five@slaed.net', 'six@slaed.net'] as $mail) {
        $mailer->addQueue(['kind' => PROBEKIND, 'email' => $mail, 'sender' => 'info@slaed.net', 'title' => 'rate', 'body' => 'rate']);
    }
    $out['rate'] = $mailer->updateQueue();
    $left = $db->getSqlRows($db->getSqlQuery('SELECT status, lockid FROM '.PREFIX_DB.'_mail WHERE status = 0 AND email LIKE :mail', ['mail' => '%@slaed.net'])) ?: [];
    $out['rate']['free'] = count(array_filter($left, static fn(array $one): bool => $one['lockid'] === ''));
    $out['again'] = getProbeMail(array_replace($sect, ['rate' => '1']))->updateQueue();
    proc_terminate($proc);
    proc_close($proc);
    $out['clean'] = deleteProbeRows($ntime);
    return $out;
}

# Report what a campaign does end to end: a criterion expanded into held rows, the sample drawn and delivered, the campaign parking, an operator releasing it and the rest going out
# The audience is a group this scenario creates for itself, so the expansion can be driven against the real tables without a single real subscriber entering the queue
# The rows it writes into _users are removed by the identifiers it was given, never by a pattern over an address, because a pattern also matches accounts the installation owns
function getProbeCamp(): array {
    global $db, $conf;
    $out = [];
    $base = [];
    foreach (['mail', 'users', 'groups', 'maildead', 'newsletter'] as $tabl) {
        $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_'.$tabl));
        $base[$tabl] = intval($row['num'] ?? 0);
    }
    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_groups (name, intro, points, extra, rank, color) VALUES (\'probe group\', \'\', 0, 1, \'\', \'\')');
    $gid = intval($db->getSqlLastId());
    $mail = ['camper@slaed.net', 'campertwo@slaed.net', 'camperthree@slaed.net', 'camperdead@slaed.net'];
    $sql = 'INSERT INTO '.PREFIX_DB.'_users (name, email, password, block, warnings, field, grp, newslet, regdate, lastvis)'
        .' VALUES (:name, :mail, \'x\', \'\', \'\', \'\', :grp, 1, NOW(), NOW())';
    $ours = [];
    foreach ($mail as $idx => $addr) {
        $db->getSqlQuery($sql, ['name' => 'probeuser'.chr(97 + $idx), 'mail' => $addr, 'grp' => $gid]);
        $ours[] = intval($db->getSqlLastId());
    }
    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_maildead (email, fails, phase, code, time) VALUES (:mail, 5, \'rcpt\', \'550\', NOW())', ['mail' => 'camperdead@slaed.net']);
    $sql = 'INSERT INTO '.PREFIX_DB.'_newsletter (title, body, audit, apar, expect, status, time) VALUES (\'probe mailing\', \'shared probe body\', \'group\', :gid, 3, 1, NOW())';
    $db->getSqlQuery($sql, ['gid' => (string)$gid]);
    $nid = intval($db->getSqlLastId());
    $port = getProbePort();
    $file = LOGS_DIR.'/relay.json';
    $proc = proc_open([PHP_BINARY, BASE_DIR.'/tests/Support/mail_relay.php', (string)$port, $file, '30'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipe);
    if (!is_resource($proc)) return $out + ['error' => 'the probe relay could not be started'];
    usleep(400000);
    $sect = ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => (string)$port, 'secure' => 'none', 'auth' => '0', 'timeout' => '5'];
    $sect += ['verify' => '0', 'batch' => '10', 'rate' => '0'];
    $rule = ['canary' => '2', 'canarymin' => '1', 'breakwin' => '100', 'abort' => '10', 'bouncemax' => '2'];
    $GLOBALS['mailer'] = getProbeMail($sect, $rule);
    $out['expand'] = updateNewsletter();
    $out['state'] = getProbeCampRow($nid);
    $out['slice'] = getProbeCampSlice($nid);
    $out['dead'] = intval($db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_mail WHERE email = \'camperdead@slaed.net\''))['num'] ?? 0);
    $out['canary'] = $GLOBALS['mailer']->updateQueue();
    $out['held'] = getProbeCampRow($nid);
    $out['blocked'] = $GLOBALS['mailer']->updateQueue();
    $GLOBALS['mailer']->setCampFree($nid);
    $out['freed'] = getProbeCampSlice($nid);
    $out['rest'] = $GLOBALS['mailer']->updateQueue();
    $out['done'] = getProbeCampRow($nid);
    $out['left'] = $GLOBALS['mailer']->getCampLeft($nid);
    $data = json_decode((string)file_get_contents($file), true);
    $out['relay'] = ['links' => intval($data['links'] ?? 0), 'mails' => count($data['mails'] ?? [])];
    $out['shared'] = 0;
    foreach ($data['mails'] ?? [] as $mesg) {
        if (str_contains(getProbeMessage((string)$mesg)['body'], 'shared probe body')) $out['shared']++;
    }
    proc_terminate($proc);
    proc_close($proc);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_maildead WHERE email = :mail', ['mail' => 'camperdead@slaed.net']);
    if ($ours) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE id IN ('.implode(', ', $ours).')');
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $gid]);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $nid]);
    $out['clean'] = deleteProbeRows($base['mail']);
    foreach (['users', 'groups', 'maildead', 'newsletter'] as $tabl) {
        $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_'.$tabl));
        if (intval($row['num'] ?? 0) !== $base[$tabl]) $out['clean'] = false;
    }
    return $out;
}

# Read the state of one campaign as the numbers an operator would be shown
function getProbeCampRow(int $nid): array {
    global $db;
    $sql = 'SELECT status, `cursor`, expect, total, send, fails, note, endtime FROM '.PREFIX_DB.'_newsletter WHERE id = :id';
    $row = $db->getSqlRow($db->getSqlQuery($sql, ['id' => $nid]));
    if (!$row) return [];
    return [
        'status' => intval($row['status']),
        'cursor' => intval($row['cursor']) > 0,
        'total' => intval($row['total']),
        'send' => intval($row['send']),
        'fails' => intval($row['fails']),
        'ended' => $row['endtime'] !== null,
    ];
}

# Count how the rows of one campaign are split between the sample, the audience and what is claimable right now
function getProbeCampSlice(int $nid): array {
    global $db;
    $sql = 'SELECT camp, hold, COUNT(id) AS num FROM '.PREFIX_DB.'_mail WHERE kind = \'newsletter\' AND ref = :id GROUP BY camp, hold ORDER BY camp, hold';
    $rows = $db->getSqlRows($db->getSqlQuery($sql, ['id' => $nid])) ?: [];
    $out = [];
    foreach ($rows as $row) $out[$row['camp'].'-'.$row['hold']] = intval($row['num']);
    return $out;
}

# Split one delivered message into its header block and its decoded body, which is what the transcript has to be read through to assert anything about it
# The block is kept as it arrived and read unfolded, because a folded header is one logical line and asserting the folding itself needs the wire form
function getProbeMessage(string $mesg): array {
    $part = explode("\r\n\r\n", $mesg, 2);
    $head = $part[0] ?? '';
    $body = base64_decode(str_replace("\r\n", '', $part[1] ?? ''), true);
    $subj = preg_match('/^Subject: ([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/m', $head, $mtch) ? $mtch[1] : '';
    $flat = trim(preg_replace('/\r\n[ \t]+/', ' ', $subj) ?? '');
    return ['head' => $head, 'subject' => $flat, 'plain' => ($subj !== '') ? mb_decode_mimeheader($subj) : '', 'body' => ($body === false) ? '' : $body];
}

# Take a loopback port the operating system reports as free, because a fixed one would collide with whatever else this machine happens to run
function getProbePort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0', $ecod, $etxt);
    if (!$sock) return 2525;
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    return intval(substr((string)$name, strrpos((string)$name, ':') + 1));
}

$mode = (string)($argv[1] ?? '');
$out = [];
if ($mode === 'mailqueue') {
    $out = getProbeQueue();
} elseif ($mode === 'maildrain') {
    $out = getProbeDrain();
} elseif ($mode === 'mailcamp') {
    $out = getProbeCamp();
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
