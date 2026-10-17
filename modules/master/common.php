<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

require_once BASE_DIR.'/core/classes/update.php';

# The folder of the master: inbox/ that the owner fills by SFTP, and one folder per accepted release
if (!defined('MASTER_DIR')) define('MASTER_DIR', UPDATE_DIR.'/master');

# The fewest failed installations that may pause a release, so a small sample never stops a rollout
const MASTER_FAILS = 5;

# Record the state a site reports and answer with the manifest it is offered, the key statements it lacks and the notes meant for it
function getMasterCheck(array $in, ApiClient $cli): array {
    setMasterSite($in);
    $keys = getMasterKeys();
    $list = [];
    foreach ($keys['list'] as $one) {
        if ($one['kseq'] > $in['kseq']) $list[] = ['kseq' => $one['kseq'], 'body' => base64_encode($one['body']), 'sig' => $one['sig'], 'psig' => $one['psig']];
    }
    return ['manifest' => getMasterOffer($in['sid'], $in['ch'], $in['v']), 'keys' => $list, 'notes' => getMasterNotes($in['sid'], $in['v'])];
}

# The archive of a served release for the mode file; a release that is waiting, paused or unknown and a kind it lacks are 404
function getMasterFile(array $in, ApiClient $cli): array {
    global $db;
    $res = $db->getSqlQuery('SELECT state FROM '.PREFIX_DB.'_master_release WHERE ver = :ver', ['ver' => $in['v']]);
    $row = $res ? $db->getSqlRow($res) : false;
    if (!$row || !in_array($row['state'], ['beta', 'stable'], true)) throw new ApiError('no_route');
    $data = getMasterManifest($in['v'], $row['state'])['data'] ?? [];
    $from = (string)($data['delta']['from'] ?? '');
    if ($in['kind'] === 'delta' && $from === '') throw new ApiError('no_route');
    $name = ($in['kind'] === 'full') ? 'slaed-'.$in['v'].'.zip' : 'slaed-'.$in['v'].'-from-'.$from.'.zip';
    return ['path' => MASTER_DIR.'/'.$in['v'].'/'.$name, 'name' => $name, 'mime' => 'application/zip'];
}

# Write the row of a site: the last state it reported, the domain erased when it sends none, a failed installation kept until the next one
function setMasterSite(array $in): void {
    global $db;
    $fail = $in['fv'] !== null;
    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_master_site (sid, host, ver, chan, kseq, php, db, dbver, ext, ep, es, failver, failstep, failed, added, seen)'
        .' VALUES (:sid, :host, :ver, :chan, :kseq, :php, :db, :dbver, :ext, :ep, :es, :fver, :fstep, :fdate, NOW(), NOW())'
        .' ON DUPLICATE KEY UPDATE host = VALUES(host), ver = VALUES(ver), chan = VALUES(chan), kseq = VALUES(kseq), php = VALUES(php), db = VALUES(db),'
        .' dbver = VALUES(dbver), ext = VALUES(ext), ep = VALUES(ep), es = VALUES(es), failstep = IF(VALUES(failver) = \'\', failstep, VALUES(failstep)),'
        .' failed = IF(VALUES(failver) = \'\', failed, VALUES(failed)), failver = IF(VALUES(failver) = \'\', failver, VALUES(failver)), seen = NOW()',
        ['sid' => $in['sid'], 'host' => $in['host'], 'ver' => $in['v'], 'chan' => $in['ch'], 'kseq' => $in['kseq'], 'php' => $in['php'] ?? '', 'db' => $in['db'] ?? '',
            'dbver' => $in['dbv'] ?? '', 'ext' => $in['ext'], 'ep' => $in['ep'], 'es' => $in['es'], 'fver' => $fail ? $in['fv'] : '', 'fstep' => $fail ? $in['fs'] : 0,
            'fdate' => $fail ? date('Y-m-d H:i:s') : null]);
}

# Whether a site belongs to the first share of a release: its own cohort per release, so the same sites are not always the first
function isMasterCohort(string $sid, string $ver, int $share): bool {
    return crc32($sid.'|'.$ver) % 100 < $share;
}

# The manifest a site is served: the highest release above its version that its channel follows, its cohort includes and its version may reach, or null
function getMasterOffer(string $sid, string $chan, string $ver): ?array {
    global $db;
    $res = $db->getSqlQuery('SELECT ver, state, share FROM '.PREFIX_DB.'_master_release WHERE state IN (\'beta\', \'stable\')');
    $rows = $res ? ($db->getSqlRows($res) ?: []) : [];
    usort($rows, static fn(array $one, array $two): int => version_compare($two['ver'], $one['ver']));
    foreach ($rows as $row) {
        if (($chan === 'stable' && $row['state'] !== 'stable') || !version_compare($row['ver'], $ver, '>')) continue;
        if (!isMasterCohort($sid, $row['ver'], intval($row['share']))) continue;
        $man = getMasterManifest($row['ver'], $row['state']);
        if (!$man || version_compare($ver, (string)($man['data']['min'] ?? ''), '<')) continue;
        return ['body' => base64_encode($man['body']), 'sig' => $man['sig']];
    }
    return null;
}

# The signed manifest of an accepted release for one channel: its bytes, its base64 signature and its decoded data, or [] when the folder lacks it
function getMasterManifest(string $ver, string $chan): array {
    $file = MASTER_DIR.'/'.$ver.'/manifest-'.$chan.'.json';
    if (!Update::isVersion($ver) || !is_file($file) || !is_file($file.'.sig')) return [];
    $body = (string)file_get_contents($file);
    $data = json_decode($body, true);
    return is_array($data) ? ['body' => $body, 'sig' => trim((string)file_get_contents($file.'.sig')), 'data' => $data] : [];
}

# The keys in force on the master: the factory keys after the statements of inbox/keys/ in their order, up to the first that fails; a signature needs no acceptance
function getMasterKeys(): array {
    $keys = Update::getFactoryKeys();
    $list = [];
    $why = '';
    foreach (Update::getKeyList(MASTER_DIR.'/inbox/keys') as $one) {
        $res = Update::getKeyChain($keys, [$one]);
        if (!$res['ok']) {
            $why = $res['error'];
            break;
        }
        $keys = $res['keys'];
        $list[] = $one + ['kseq' => $keys['kseq'], 'time' => intval(json_decode($one['body'], true)['time'] ?? 0)];
    }
    return ['keys' => $keys, 'list' => $list, 'error' => $why];
}

# The accepted notes in force for a site: general ones of its version range and its own personal ones, as their signed bytes in base64 and their signatures
function getMasterNotes(string $sid, string $ver): array {
    global $db;
    $res = $db->getSqlQuery('SELECT id, ver, body, sig FROM '.PREFIX_DB.'_master_note WHERE active = 1 AND sid IN (\'\', :sid) AND (until IS NULL OR until > NOW()) ORDER BY id',
        ['sid' => $sid]);
    $out = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        if (Update::isVersionIn($ver, $row['ver'])) $out[] = ['id' => intval($row['id']), 'body' => base64_encode($row['body']), 'sig' => $row['sig']];
    }
    return $out;
}

# What waits in inbox/: the versions of release folders and the ids of signed notes
function getMasterInbox(): array {
    $vers = array_filter(array_map('basename', glob(MASTER_DIR.'/inbox/*', GLOB_ONLYDIR) ?: []), [Update::class, 'isVersion']);
    usort($vers, 'version_compare');
    $ids = array_filter(array_map(static fn(string $v): string => basename($v, '.json'), glob(MASTER_DIR.'/inbox/notes/*.json') ?: []), 'ctype_digit');
    sort($ids);
    return ['releases' => array_values($vers), 'notes' => array_map('intval', $ids)];
}

# Accept a release from inbox/<version>/: both manifests verify under the keys in force and name this version, every archive has its signed size and SHA-256; then it moves
# A release accepted before takes its refreshed manifests and keeps its state; the answer is an empty string or the reason of the refusal
function addMasterRelease(string $ver): string {
    global $db;
    $dir = MASTER_DIR.'/inbox/'.$ver;
    if (!Update::isVersion($ver) || !is_dir($dir)) return 'missing';
    $keys = getMasterKeys()['keys'];
    $packs = [];
    foreach (['beta', 'stable'] as $chan) {
        $file = $dir.'/manifest-'.$chan.'.json';
        if (!is_file($file) || !is_file($file.'.sig')) return 'manifest';
        $res = Update::getManifest((string)file_get_contents($file), trim((string)file_get_contents($file.'.sig')), $keys, ['version' => '0.0.0', 'channel' => 'beta']);
        if (!$res['ok']) return $res['error'];
        if ($res['data']['version'] !== $ver || $res['data']['channel'] !== $chan) return 'format';
        $delta = $res['data']['delta'];
        $one = ['slaed-'.$ver.'.zip' => $res['data']['full']] + ($delta ? ['slaed-'.$ver.'-from-'.$delta['from'].'.zip' => $delta] : []);
        if ($packs !== [] && $packs !== $one) return 'format';
        $packs = $one;
    }
    foreach ($packs as $name => $one) {
        if (!is_file($dir.'/'.$name) || filesize($dir.'/'.$name) !== $one['size'] || hash_file('sha256', $dir.'/'.$name) !== $one['sha256']) return 'archive';
    }
    $dest = MASTER_DIR.'/'.$ver;
    if (!is_dir($dest) && !mkdir($dest, 0755, true)) return 'write';
    $names = array_merge(['manifest-beta.json', 'manifest-beta.json.sig', 'manifest-stable.json', 'manifest-stable.json.sig'], array_keys($packs));
    foreach ($names as $name) {
        if (is_file($dest.'/'.$name)) unlink($dest.'/'.$name);
        if (!rename($dir.'/'.$name, $dest.'/'.$name)) return 'write';
    }
    if (count(scandir($dir) ?: []) === 2) rmdir($dir);
    $done = $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_master_release (ver, state, accepted, changed) VALUES (:ver, \'wait\', NOW(), NOW())'
        .' ON DUPLICATE KEY UPDATE changed = NOW()', ['ver' => $ver]);
    return $done ? '' : 'write';
}

# Accept a note from inbox/notes/: its signature under the primary key in force and its format; the row keeps the signed bytes as they came, then the files go
function addMasterNote(int $id): string {
    global $db;
    $base = MASTER_DIR.'/inbox/notes/'.$id;
    if (!is_file($base.'.json') || !is_file($base.'.sig')) return 'missing';
    $body = (string)file_get_contents($base.'.json');
    $sig = trim((string)file_get_contents($base.'.sig'));
    $key = Update::getRawBytes(getMasterKeys()['keys']['primary'], 32);
    if (!Update::isSignValid(Update::NOTE.$body, Update::getRawBytes($sig, 64), $key)) return 'sign';
    $data = json_decode($body, true);
    if (!Update::isNote($data) || $data['id'] !== $id) return 'format';
    $done = $db->getSqlQuery('REPLACE INTO '.PREFIX_DB.'_master_note (id, sid, ver, level, until, active, body, sig, added)'
        .' VALUES (:id, :sid, :ver, :level, :until, 1, :body, :sig, NOW())',
        ['id' => $id, 'sid' => $data['sid'], 'ver' => $data['ver'], 'level' => $data['level'], 'until' => $data['until'] > 0 ? date('Y-m-d H:i:s', $data['until']) : null,
            'body' => $body, 'sig' => $sig]);
    if (!$done) return 'write';
    unlink($base.'.json');
    unlink($base.'.sig');
    return '';
}

# Move a release into a state by the owner: beta or stable serves it, pause withdraws it and marks the owner; an unknown release or state changes nothing
function setMasterState(string $ver, string $state): bool {
    global $db;
    if (!in_array($state, ['beta', 'stable', 'pause'], true) || ($state !== 'pause' && !getMasterManifest($ver, $state))) return false;
    $res = $db->getSqlQuery('UPDATE '.PREFIX_DB.'_master_release SET state = :state, pauseby = :by, pfail = 0, pok = 0, changed = NOW() WHERE ver = :ver',
        ['state' => $state, 'by' => ($state === 'pause') ? 'owner' : '', 'ver' => $ver]);
    return $res !== false && $db->getSqlRowCount($res) > 0;
}

# The scheduler job masterclean: today's totals by channel, version, PHP and database from the sites seen in a day, then sites and totals older than their terms go
function addMasterClean(): array {
    global $conf, $db;
    $silent = max(1, intval($conf['master']['silent'] ?? 180));
    $keep = max(1, intval($conf['master']['keep'] ?? 730));
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_master_stat WHERE day = CURDATE()');
    $sum = $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_master_stat (day, chan, ver, php, db, sites, nosod, nozip, errs, fails)'
        .' SELECT CURDATE(), chan, ver, SUBSTRING_INDEX(php, \'.\', 2) AS phpv, TRIM(CONCAT(db, \' \', SUBSTRING_INDEX(dbver, \'.\', 2))) AS dbv, COUNT(*),'
        .' SUM((ext & 1) = 0), SUM((ext & 2) = 0), SUM(ep + es), SUM(failed IS NOT NULL AND failed >= NOW() - INTERVAL 1 DAY)'
        .' FROM '.PREFIX_DB.'_master_site WHERE seen >= NOW() - INTERVAL 1 DAY GROUP BY chan, ver, phpv, dbv');
    if (!$sum) return ['status' => 'failed', 'message' => 'The daily totals of the master could not be written'];
    $gone = $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_master_site WHERE seen < NOW() - INTERVAL :days DAY', ['days' => $silent]);
    $old = $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_master_stat WHERE day < CURDATE() - INTERVAL :days DAY', ['days' => $keep]);
    return ['status' => 'success', 'message' => sprintf('Totals written, %d silent sites and %d old totals removed', $gone ? $db->getSqlRowCount($gone) : 0,
        $old ? $db->getSqlRowCount($old) : 0)];
}

# The failed and the successful installations of a release since it was accepted; a site that failed and then installed it counts only as a success
function getMasterTally(string $ver, string $since): array {
    global $db;
    $res = $db->getSqlQuery('SELECT ver, failstep FROM '.PREFIX_DB.'_master_site WHERE failver = :ver AND failed >= :since', ['ver' => $ver, 'since' => $since]);
    $steps = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        if (version_compare($row['ver'], $ver, '<')) $steps[] = intval($row['failstep']);
    }
    $res = $db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_master_site WHERE ver = :ver', ['ver' => $ver]);
    $row = $res ? $db->getSqlRow($res) : false;
    $tops = array_count_values($steps);
    arsort($tops);
    return ['fails' => count($steps), 'oks' => intval($row[0] ?? 0), 'steps' => $tops];
}

# The scheduler job masterwatch: a served release with at least five failed installations above the threshold share goes to the automatic pause, with a mail and a journal line
# Only the owner lifts a pause; the job never serves a release again
function addMasterWatch(): array {
    global $conf, $db;
    $limit = max(1, min(100, intval($conf['master']['limit'] ?? 5)));
    $res = $db->getSqlQuery('SELECT ver, accepted FROM '.PREFIX_DB.'_master_release WHERE state IN (\'beta\', \'stable\')');
    $done = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        $sum = getMasterTally($row['ver'], $row['accepted']);
        $all = $sum['fails'] + $sum['oks'];
        if ($sum['fails'] < MASTER_FAILS || $sum['fails'] * 100 <= $limit * $all) continue;
        $upd = $db->getSqlQuery('UPDATE '.PREFIX_DB.'_master_release SET state = \'pause\', pauseby = \'auto\', pfail = :fails, pok = :oks, changed = NOW()'
            .' WHERE ver = :ver AND state IN (\'beta\', \'stable\')', ['fails' => $sum['fails'], 'oks' => $sum['oks'], 'ver' => $row['ver']]);
        if (!$upd || $db->getSqlRowCount($upd) === 0) continue;
        $done[] = $row['ver'];
        Logger::addAdmin('warning', 'master autopause', ['event' => 'action', 'aid' => 0, 'aname' => 'scheduler', 'modul' => 'master', 'op' => 'autopause',
            'target' => 0, 'num' => 1, 'result' => 'done', 'ver' => $row['ver'], 'fails' => $sum['fails'], 'oks' => $sum['oks']]);
        setMasterMail($row['ver'], $sum, $limit);
    }
    return ['status' => 'success', 'message' => $done ? 'Paused: '.implode(', ', $done) : 'No release paused'];
}

# Tell the owner of slaed.net by mail that a release was paused: the version, the counts and the most frequent failed steps
function setMasterMail(string $ver, array $sum, int $limit): void {
    global $conf, $mailer;
    if (!$mailer instanceof Mail || (string)($conf['adminmail'] ?? '') === '') return;
    $steps = [];
    foreach (array_slice($sum['steps'], 0, 3, true) as $step => $num) $steps[] = $step.': '.$num;
    $text = sprintf("Release %s was paused automatically.\nFailed installations: %d, successful: %d, threshold %d %%.\nMost frequent failed steps: %s.\n"
        .'The pause stays until the owner moves the release to beta or stable.', $ver, $sum['fails'], $sum['oks'], $limit, implode(', ', $steps));
    $mailer->addQueue(['kind' => 'master', 'email' => $conf['adminmail'], 'title' => $conf['sitename'].' - '.$ver.' paused', 'sender' => $conf['adminmail'], 'prio' => 1,
        'body' => getMailFrame(nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false))]);
}
