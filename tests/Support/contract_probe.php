<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the page-cache, statistics, and GeoIP contract tests: boots the real core like index.php, one scenario per process, with LOGS_DIR and COUNTER_DIR redirected to scratch
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$scratch = str_replace('\\', '/', (string)($argv[2] ?? ''));
define('COUNTER_DIR', ($scratch !== '') ? $scratch : str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe_counter');
define('LOGS_DIR', ($scratch !== '') ? $scratch.'/logs' : str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe_logs');
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0777, true);
if ($scratch === '' && is_file(LOGS_DIR.'/error_file.log')) unlink(LOGS_DIR.'/error_file.log');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) probe';
$_SERVER['HTTPS'] = 'on';
# The moderator half of the comment class needs a signed-in administrator, and isAdmin() memoizes its verdict on the first call the boot itself makes
# So the session is opened and filled here, before core/security.php reads it: the boot then skips its own session_start() and finds the administrator already there
if (in_array($argv[1] ?? '', ['commentstage', 'commentthread'], true)) {
    $acon = (require BASE_DIR.'/config/db.php')['db'];
    $akey = (string)((require BASE_DIR.'/config/global.php')['admin_c'] ?? 'panel');
    $apdo = new PDO('mysql:host='.$acon['host'].';dbname='.$acon['name'].';charset=utf8mb4', $acon['uname'], $acon['pass']);
    $arow = $apdo->query('SELECT id, name, password, ip FROM '.$acon['prefix'].'_admins WHERE super = 1 AND ip != \'\' ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($arow) {
        $_SERVER['REMOTE_ADDR'] = (string)$arow['ip'];
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION[$akey] = base64_encode($arow['id'].':'.$arow['name'].':'.$arow['password']);
    }
    $apdo = null;
}
require_once BASE_DIR.'/core/system.php';

# Build one signed stats cookie value from raw fields using the real purpose-scoped secret
function getProbeCookie(array $part): string {
    $body = implode('|', $part);
    return rtrim(strtr(base64_encode($body.'|'.hash_hmac('sha256', $body, getSecret('stats'))), '+/', '-_'), '=');
}

# Prepare one cacheable-route request context and report the real contract decision and identity
function getProbeRoute(string $uri, array $get, string $host): array {
    global $name, $op, $home, $theme;
    putenv('HTTP_HOST='.$host);
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    $name = 'news';
    $op = '';
    $home = 0;
    $theme = $theme ?? getTheme();
    $vars = getCacheRouteVars();
    return ['vars' => $vars, 'cache' => checkPageCache(), 'hash' => ($vars !== null) ? getPageHash() : ''];
}

# Report the scratch counter state after one statistics hit so the test can assert files instead of return values
function getProbeCounter(): array {
    $read = static fn(string $file): string => is_file(COUNTER_DIR.'/'.$file) ? (string)file_get_contents(COUNTER_DIR.'/'.$file) : '';
    $arch = glob(COUNTER_DIR.'/statistic/statistic_*.log') ?: [];
    return [
        'stat' => trim($read('statistic.log')),
        'ips' => $read('ips.log'),
        'users' => $read('user.log'),
        'days' => $read('days.log'),
        'arch' => array_map('basename', $arch),
        'log' => is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '',
    ];
}

# Report how the comment trust boundary resolves a target while the request carries hostile cid and mod values
function getProbeComment(): array {
    global $db, $com;
    $_GET = ['id' => '1', 'cid' => '2', 'mod' => 'gallery'];
    $_POST = ['cid' => '2', 'mod' => 'account'];
    $_REQUEST = $_GET + $_POST;
    $pick = static function(string $tab, string $where) use ($db): array {
        $row = $db->getSqlRow($db->getSqlQuery('SELECT id, acomm FROM '.PREFIX_DB.'_'.$tab.' WHERE '.$where.' ORDER BY id DESC LIMIT 1'));
        return $row ? ['id' => intval($row['id']), 'acomm' => intval($row['acomm'])] : [];
    };
    $open = $pick('news', 'acomm != 0 AND time <= NOW() AND status != \'0\'');
    $off = $pick('news', 'acomm = 0 AND time <= NOW() AND status != \'0\'');
    $hide = $pick('news', 'acomm != 0 AND (status = \'0\' OR time > NOW())');
    $vote = $pick('voting', 'acomm != 0 AND modul = \'\' AND time <= NOW() AND (enddate >= NOW() AND status = \'0\' OR status = \'1\')');
    $out = [
        'open' => $open ? [$open['acomm'], $com->getTargetMode('news', $open['id'])->value] : [],
        'off' => $off ? [0, $com->getTargetMode('news', $off['id'])->value] : [],
        'hide' => $hide ? [0, $com->getTargetMode('news', $hide['id'])->value] : [],
        'vote' => $vote ? [$vote['acomm'], $com->getTargetMode('voting', $vote['id'])->value] : [],
        'missing' => $com->getTargetMode('news', 999999999)->value,
        'zero' => $open ? $com->getTargetMode('news', 0)->value : 0,
    ];
    $out['unknown'] = [];
    foreach (['gallery', 'account', 'members', 'multimedia', 'forum', '', 'news_', 'news; DROP'] as $mod) {
        $out['unknown'][$mod] = $open ? $com->getTargetMode($mod, $open['id'])->value : -1;
    }
    return $out;
}

# Report whether the read methods of the Comment class answer exactly what the queries they replace answer, against the live rows of this installation
function getProbeCommentRead(): array {
    global $db, $com, $conf;
    $sort = !empty($conf['comments']['sort']) ? 'ASC' : 'DESC';
    $lnum = intval($conf['comments']['num'] ?? 15);
    $anum = intval($conf['comments']['anum'] ?? 25);
    $out = ['wired' => ($com instanceof Comment)];
    $top = $db->getSqlRow($db->getSqlQuery('SELECT modul, cid, COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE status = 1 GROUP BY modul, cid ORDER BY num DESC LIMIT 1'));
    $mod = $top ? (string)$top['modul'] : '';
    $cid = $top ? intval($top['cid']) : 0;
    $out['target'] = [$mod, $cid];
    if ($mod === '') return $out;
    $pars = ['cid' => $cid, 'mod' => $mod, 'status' => 0];
    $cnt = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(cid) AS num FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND modul = :mod AND status != :status', $pars));
    $tot = intval($cnt['num']);
    $out['count'] = [$tot, intval($db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND modul = :mod AND status = 1 AND deleted IS NULL', ['cid' => $cid, 'mod' => $mod]))['num'])];
    $pgs = (int)ceil($tot / $lnum);
    $out['list'] = [];
    foreach (array_unique([1, $pgs]) as $page) {
        $off = ($page - 1) * $lnum;
        $sql = 'SELECT id, cid, modul, time, uid, name, ip, body, status FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND modul = :mod AND status != :status'
            .' ORDER BY time '.$sort.' LIMIT '.$off.', '.$lnum;
        $was = [];
        foreach ($db->getSqlRows($db->getSqlQuery($sql, $pars)) ?: [] as $one) $was[] = intval($one['id']);
        $now = $com->getList($mod, $cid, $page);
        $first = ($sort === 'ASC') ? $off + 1 : (($tot > $off) ? $tot - $off : $tot);
        $out['list'][] = [$was, array_column($now['rows'], 'id'), [$off, $pgs, $first], [$now['offset'], $now['pages'], $now['first']]];
    }
    $urow = $db->getSqlRow($db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.'_comment WHERE uid > 0 AND status = 1 GROUP BY uid ORDER BY COUNT(*) DESC LIMIT 1'));
    $uid = $urow ? intval($urow['uid']) : 0;
    $out['author'] = [];
    if ($uid) {
        $sql = 'SELECT u.id, u.name, u.rank, u.points, g.name AS gnam, g.rank AS grnk, g.color AS gclr FROM '.PREFIX_DB.'_users AS u'
            .' LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points))'
            .' WHERE u.id IN (:uid) ORDER BY g.extra ASC, g.points ASC';
        $was = [];
        foreach ($db->getSqlRows($db->getSqlQuery($sql, ['uid' => $uid])) ?: [] as $one) {
            $was = [(string)$one['name'], (string)$one['rank'], intval($one['points'])];
            $was = array_merge($was, [(string)($one['gnam'] ?? ''), (string)($one['grnk'] ?? ''), (string)($one['gclr'] ?? '')]);
        }
        $urow = $db->getSqlRow($db->getSqlQuery('SELECT modul, cid FROM '.PREFIX_DB.'_comment WHERE uid = :uid AND status = 1 ORDER BY id DESC LIMIT 1', ['uid' => $uid]));
        $now = $com->getList((string)$urow['modul'], intval($urow['cid']), 1);
        $has = [];
        foreach ($now['rows'] as $one) {
            if ($one['uid'] !== $uid || !$one['user']) continue;
            $has = [$one['user']['name'], $one['user']['rank'], $one['user']['points']];
            $has = array_merge($has, [$one['user']['gname'], $one['user']['grank'], $one['user']['gcolor']]);
        }
        $out['author'] = [$was, $has];
    }
    $sql = 'SELECT id, cid, modul, body, time FROM '.PREFIX_DB.'_comment WHERE uid = :uid AND status != \'0\' ORDER BY id DESC LIMIT 0,25';
    $was = [];
    foreach ($db->getSqlRows($db->getSqlQuery($sql, ['uid' => $uid])) ?: [] as $one) $was[] = intval($one['id']);
    $out['feed'] = [$was, array_column($com->getUserList($uid, 25), 'id')];
    $was = [];
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_comment ORDER BY modul ASC')) ?: [] as $one) {
        if ($one['modul'] !== '') $was[] = (string)$one['modul'];
    }
    $out['mods'] = [$was, $com->getModuleList()];
    $join = PREFIX_DB.'_comment AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) ';
    $sel = 'SELECT s.id, s.cid, s.modul, s.time, s.uid, s.name, s.ip, s.body, s.status, u.name AS unam FROM '.$join;
    $eid = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_comment ORDER BY id DESC LIMIT 1'));
    $eid = $eid ? intval($eid['id']) : 0;
    $one = $db->getSqlRow($db->getSqlQuery($sel.'WHERE s.id = :id', ['id' => $eid]));
    $now = $com->getComment($eid);
    $out['single'] = [
        [intval($one['id']), (string)$one['modul'], intval($one['cid']), (string)$one['body'], intval($one['status']), (string)($one['unam'] ?? '')],
        [$now['id'] ?? 0, $now['modul'] ?? '', $now['cid'] ?? 0, $now['body'] ?? '', $now['status'] ?? -1, $now['nick'] ?? ''],
    ];
    $out['missing'] = $com->getComment(999999999);
    $aord = !empty($conf['comments']['sort']) ? 'ASC' : 'DESC';
    $out['admin'] = [];
    $cases = [
        ['status != :status', ['status' => 0], CommentStatus::Published, '', 2, ''],
        ['status = :status', ['status' => 0], CommentStatus::Pending, '', 2, ''],
        ['status != :status AND s.modul LIKE :find', ['status' => 0, 'find' => '%news%'], CommentStatus::Published, '', 4, 'news'],
        ['status != :status AND (s.name LIKE :fnam OR u.name LIKE :fusr)', ['status' => 0, 'fnam' => '%a%', 'fusr' => '%a%'], CommentStatus::Published, '', 3, 'a'],
        ['status != :status AND s.modul = :mod', ['status' => 0, 'mod' => $mod], CommentStatus::Published, $mod, 2, ''],
    ];
    foreach ($cases as $case) {
        [$where, $args, $stat, $amod, $find, $term] = $case;
        $cnt = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.$join.'WHERE '.$where, $args));
        $was = [];
        foreach ($db->getSqlRows($db->getSqlQuery($sel.'WHERE '.$where.' ORDER BY s.time '.$aord.' LIMIT 0, '.$anum, $args)) ?: [] as $one) $was[] = intval($one['id']);
        $now = $com->getAdminList($stat, $amod, $find, $term, 1);
        $out['admin'][] = [$was, array_column($now['rows'], 'id'), intval($cnt['num']), $now['total']];
    }
    return $out;
}

# Report what the write path of the Comment class stores, counts and awards for each of the eight modules, inside a transaction that is always rolled back
# The registered run needs an account whose stored hash is_user() accepts, because the moderation state, the counter and the points slot of a published add all depend on it
function getProbeCommentWrite(bool $guest): array {
    global $db, $com, $conf, $user;
    $map = ['faq' => '_faq', 'files' => '_files', 'links' => '_links', 'media' => '_media', 'news' => '_news', 'pages' => '_pages', 'shop' => '_products', 'voting' => '_voting'];
    $slot = ['faq' => 7, 'files' => 10, 'links' => 22, 'media' => 26, 'news' => 32, 'pages' => 36, 'shop' => 40, 'voting' => 43];
    $pts = explode(',', (string)$conf['users']['points']);
    $out = ['guest' => $guest, 'user' => true, 'rows' => [], 'refuse' => [], 'clean' => false];
    $uid = 0;
    if (!$guest) {
        $sql = 'SELECT id, name, password FROM '.PREFIX_DB.'_users WHERE access = 0 AND password != \'\' AND name REGEXP \'^[A-Za-z0-9_.-]+$\' ORDER BY id ASC LIMIT 1';
        $one = $db->getSqlRow($db->getSqlQuery($sql));
        if (!$one) return ['guest' => $guest, 'user' => false, 'rows' => [], 'refuse' => [], 'clean' => true];
        $uid = intval($one['id']);
        $user = [(string)$one['id'], (string)$one['name'], (string)$one['password']];
    }
    $out['uid'] = [$uid, is_user() ? $uid : 0];
    $ip = getIp();
    $stamp = $db->getSqlRow($db->getSqlQuery('SELECT NOW() AS now'));
    $out['skew'] = strtotime((string)$stamp['now']) - time();
    $was = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment'));
    $db->setSqlBegin();
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE ip = :ip', ['ip' => $ip]);
    $open = 0;
    foreach ($map as $mod => $tab) {
        $tid = 0;
        foreach ($db->getSqlRows($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.$tab.' ORDER BY id DESC LIMIT 25')) ?: [] as $one) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.$tab.' SET acomm = 2 WHERE id = :id', ['id' => intval($one['id'])]);
            if ($com->getTargetMode($mod, intval($one['id'])) === CommentMode::Open) {
                $tid = intval($one['id']);
                break;
            }
        }
        if (!$tid) {
            $out['rows'][$mod] = ['target' => 0];
            continue;
        }
        if ($mod === 'news') $open = $tid;
        $cnt = $db->getSqlRow($db->getSqlQuery('SELECT comments FROM '.PREFIX_DB.$tab.' WHERE id = :id', ['id' => $tid]));
        $pnt = $uid ? $db->getSqlRow($db->getSqlQuery('SELECT points FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid])) : [];
        $new = $com->addComment($mod, $tid, 'probe body for '.$mod, 'Probe');
        $row = $new['id'] ? $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, name, ip, body, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $new['id']])) : [];
        $cnn = $db->getSqlRow($db->getSqlQuery('SELECT comments FROM '.PREFIX_DB.$tab.' WHERE id = :id', ['id' => $tid]));
        $pnn = $uid ? $db->getSqlRow($db->getSqlQuery('SELECT points FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid])) : [];
        $gain = ($uid && $conf['users']['point'] == 1) ? intval($pts[$slot[$mod] - 1] ?? 0) : 0;
        $out['rows'][$mod] = [
            'target' => $tid,
            'error' => $new['error'],
            'stored' => $row ? [intval($row['cid']), (string)$row['modul'], intval($row['uid']), (string)$row['ip'], intval($row['status'])] : [],
            'want' => [$tid, $mod, $uid, $ip, $guest ? 0 : 1],
            'delta' => [intval($cnn['comments']) - intval($cnt['comments']), intval($pnn['points'] ?? 0) - intval($pnt['points'] ?? 0)],
            'wantdelta' => [$guest ? 0 : 1, $guest ? 0 : $gain],
        ];
        if ($new['id']) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = :id', ['id' => $new['id']]);
    }
    $out['edit'] = [];
    if ($open) {
        $new = $com->addComment('news', $open, 'probe body for the edit window', 'Probe');
        $eid = intval($new['id']);
        $out['edit']['skewed'] = $eid ? $com->updateComment($eid, 'probe body edited')['allow'] : null;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = :time WHERE id = :id', ['time' => date('Y-m-d H:i:s'), 'id' => $eid]);
        $edit = $com->updateComment($eid, 'probe body edited');
        $row = $db->getSqlRow($db->getSqlQuery('SELECT body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $eid]));
        $out['edit']['fresh'] = [$edit['allow'], $edit['saved'], $edit['mod'], $edit['body'], (string)($row['body'] ?? '')];
        $past = date('Y-m-d H:i:s', time() - intval($conf['comments']['edit']) - 60);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = :time WHERE id = :id', ['time' => $past, 'id' => $eid]);
        $out['edit']['stale'] = $com->updateComment($eid, 'probe body edited again')['allow'];
        $out['edit']['status'] = $com->setStatus($eid, true);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = :id', ['id' => $eid]);
    }
    if ($open) {
        $off = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_news WHERE acomm = 0 ORDER BY id DESC LIMIT 1'));
        $out['refuse'] = [
            'empty' => [$com->addComment('news', $open, '', 'Probe')['error'], _CERROR1],
            'long' => [$com->addComment('news', $open, str_repeat('a', intval($conf['comments']['letter']) + 1), 'Probe')['error'], _CERROR2],
            'unknown' => [$com->addComment('gallery', $open, 'probe body', 'Probe')['error'], _ERROR],
            'missing' => [$com->addComment('news', 999999999, 'probe body', 'Probe')['error'], _ERROR],
            'off' => [$off ? $com->addComment('news', intval($off['id']), 'probe body', 'Probe')['error'] : _ERROR, _ERROR],
            'noname' => [$guest ? $com->addComment('news', $open, 'probe body', '')['error'] : _CERROR3, _CERROR3],
        ];
        $com->addComment('news', $open, 'probe body for the flood window', 'Probe');
        $out['refuse']['flood'] = [$com->addComment('news', $open, 'probe body again', 'Probe')['error'], sprintf(_CERROR5, $conf['comments']['send'])];
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE ip = :ip', ['ip' => $ip]);
        $word = trim(explode(',', (string)$conf['censor_l'])[0]);
        $text = '[usehtml]<b>x</b>[/usehtml] '.$word.' cost $5 back\\slash <i>kept</i>';
        $new = $com->addComment('news', $open, $text, 'Probe');
        $row = $new['id'] ? $db->getSqlRow($db->getSqlQuery('SELECT body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $new['id']])) : [];
        $out['norm'] = [$new['error'], (string)($row['body'] ?? ''), $word, (bool)$conf['censor'], (string)$conf['censor_r']];
    }
    $db->setSqlRollback();
    $now = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment'));
    $out['clean'] = (intval($was['num']) === intval($now['num']));
    return $out;
}

# Report what stage 2 promises about a comment write: one rule set, a stable sort, a conditional state change, a soft delete and a stored format
# The run signs in as the administrator whose stored address this process reports, because the moderator half of the class cannot be reached otherwise from a CLI probe
function getProbeCommentStage2(): array {
    global $db, $com, $conf, $user;
    $out = ['admin' => isAdmin(true), 'moder' => (bool)is_moder('news'), 'clean' => false];
    if (!$out['admin']) return $out;
    $sql = 'SELECT id, name, password FROM '.PREFIX_DB.'_users WHERE access = 0 AND password != \'\' AND name REGEXP \'^[A-Za-z0-9_.-]+$\' ORDER BY id ASC LIMIT 1';
    $one = $db->getSqlRow($db->getSqlQuery($sql));
    $uid = $one ? intval($one['id']) : 0;
    if ($one) $user = [(string)$one['id'], (string)$one['name'], (string)$one['password']];
    $out['user'] = [$uid, is_user() ? $uid : 0];
    $pts = explode(',', (string)$conf['users']['points']);
    $gain = ($conf['users']['point'] == 1) ? intval($pts[31] ?? 0) : 0;
    $long = intval($conf['comments']['letter']);
    $word = str_repeat('a', $long + 1);
    $out['rules'] = [
        'longest' => $com->checkRules('news', $word.' tail', 'Probe', '', false),
        'last' => $com->checkRules('news', 'tail '.$word, 'Probe', '', false),
        'chars' => $com->checkRules('news', str_repeat('я', $long), 'Probe', '', false),
        'bytes' => strlen(str_repeat('я', $long)),
        'limit' => $long,
        'empty' => $com->checkRules('news', '', 'Probe', '', false),
    ];
    $all = getProbeCommentCount();
    $db->setSqlBegin();
    $hash = hash_hmac('sha256', strtolower(getIp()), getSecret('commentip'));
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE iphash = :hash', ['hash' => $hash]);
    $tid = 0;
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_news ORDER BY id DESC LIMIT 25')) ?: [] as $row) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET acomm = 2 WHERE id = :id', ['id' => intval($row['id'])]);
        if ($com->getTargetMode('news', intval($row['id'])) === CommentMode::Open) {
            $tid = intval($row['id']);
            break;
        }
    }
    if (!$tid) {
        $db->setSqlRollback();
        return $out + ['target' => 0];
    }
    $read = static function () use ($db, $tid, $uid): array {
        $cnt = $db->getSqlRow($db->getSqlQuery('SELECT comments FROM '.PREFIX_DB.'_news WHERE id = :id', ['id' => $tid]));
        $pnt = $db->getSqlRow($db->getSqlQuery('SELECT points FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid]));
        return [intval($cnt['comments'] ?? 0), intval($pnt['points'] ?? 0)];
    };
    $out['target'] = $tid;
    $new = $com->addComment('news', $tid, 'stage two probe body', 'Probe', 'abcdef0123456789abcdef0123456789');
    $cid = intval($new['id']);
    $row = $db->getSqlRow($db->getSqlQuery('SELECT format, reqkey, iphash, status, deleted, edited FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $cid]));
    $out['stored'] = [
        'error' => $new['error'],
        'format' => (string)($row['format'] ?? ''),
        'reqkey' => (string)($row['reqkey'] ?? ''),
        'iphash' => strlen((string)($row['iphash'] ?? '')),
        'status' => intval($row['status'] ?? -1),
        'deleted' => $row['deleted'] ?? null,
        'edited' => $row['edited'] ?? null,
    ];
    $out['rules']['flood'] = [$com->checkRules('news', 'body', 'Probe', $hash, true), $com->checkRules('news', 'body', 'Probe', $hash, false)];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = :id', ['id' => $cid]);
    $again = $com->addComment('news', $tid, 'stage two probe replay', 'Probe', 'abcdef0123456789abcdef0123456789');
    $out['replay'] = [$cid, intval($again['id']), $again['error'], getProbeCommentCount('reqkey = :key', ['key' => 'abcdef0123456789abcdef0123456789'])];
    $was = $read();
    $out['hide'] = [$com->setStatus($cid, false), $read()];
    $out['hideagain'] = [$com->setStatus($cid, false), $read()];
    $out['show'] = [$com->setStatus($cid, true), $read()];
    $out['showagain'] = [$com->setStatus($cid, true), $read()];
    $out['counters'] = [$was, $read(), $gain];
    $edit = $com->updateComment($cid, 'stage two probe edited');
    $row = $db->getSqlRow($db->getSqlQuery('SELECT body, edited FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $cid]));
    $out['edit'] = [$edit['allow'], $edit['saved'], (string)($row['body'] ?? ''), $row['edited'] !== null];
    $out['before'] = $read();
    $out['delete'] = $com->deleteComment($cid);
    $row = $db->getSqlRow($db->getSqlQuery('SELECT deleted FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $cid]));
    $out['deleted'] = [(string)($row['deleted'] ?? ''), $read()];
    $out['deleteagain'] = $com->deleteComment($cid);
    $row = $db->getSqlRow($db->getSqlQuery('SELECT deleted FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $cid]));
    $out['deletedagain'] = [(string)($row['deleted'] ?? ''), $read()];
    $out['gone'] = [
        'single' => $com->getComment($cid),
        'list' => in_array($cid, array_column($com->getList('news', $tid, 1)['rows'], 'id'), true),
        'admin' => in_array($cid, array_column($com->getAdminList(CommentStatus::Published, 'news', 2, '', 1)['rows'], 'id'), true),
        'edit' => $com->updateComment($cid, 'after the delete')['saved'],
        'status' => $com->setStatus($cid, false),
    ];
    $pend = $com->addComment('news', $tid, 'stage two pending probe', 'Probe', '');
    $pid = intval($pend['id']);
    $com->setStatus($pid, false);
    $was = $read();
    $out['pending'] = [$com->deleteComment($pid), $was, $read()];
    $out['round'] = [];
    foreach (['plain', 'markdown'] as $fmt) {
        $pick = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_comment WHERE format = :fmt AND deleted IS NULL ORDER BY id DESC LIMIT 1', ['fmt' => $fmt]));
        if (!$pick) continue;
        $one = intval($pick['id']);
        $was = $com->getComment($one)['body'] ?? '';
        $com->updateBody($one, $was);
        $now = $com->getComment($one)['body'] ?? '';
        $edit = $com->updateComment($one, $was);
        $out['round'][$fmt] = [$was === $now, $edit['saved'], $was === ($com->getComment($one)['body'] ?? ''), mb_substr($was, 0, 60)];
    }
    $out['sort'] = [];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = \'2020-01-01 00:00:00\' WHERE cid = :cid AND modul = \'news\'', ['cid' => $tid]);
    for ($i = 0; $i < 2; $i++) {
        $out['sort'][] = array_column($com->getList('news', $tid, 1)['rows'], 'id');
    }
    $db->setSqlRollback();
    $out['clean'] = ($all === getProbeCommentCount());
    return $out;
}

# Report how one stored body renders through the format the row carries, so a format is proven to be an input to rendering rather than a column nobody reads
function getProbeCommentFormat(): array {
    global $prs;
    $body = "**bold** *it* [b]bb[/b]\nsecond # not a heading\n\n- item";
    return [
        'plain' => $prs->filterContent($body, true, 'news', 0, 'plain'),
        'markdown' => $prs->filterContent($body, true, 'news', 0, 'markdown'),
        'empty' => $prs->filterContent($body, true, 'news', 0, ''),
        'html' => $prs->filterContent('<b>x</b> <img src=x onerror=alert(1)>', true, 'news', 0, 'markdown'),
        'htmlplain' => $prs->filterContent('<b>x</b> <img src=x onerror=alert(1)>', true, 'news', 0, 'plain'),
        'url' => $prs->filterContent('[url=javascript:alert(1)]click[/url]', true, 'news', 0, 'plain'),
        'usehtml' => $prs->filterContent('[usehtml]<b>raw</b>[/usehtml]', true, 'news', 0, 'markdown'),
    ];
}

# Report what stage 5 promises: replies carry a parent and a sortable path, a crafted parent is refused, the page counts roots, and a removed parent with a live reply stays as a tombstone
# Every write runs inside one transaction that is rolled back at the end, so the tree is measured against the live table without a row of it surviving the run
function getProbeCommentThread(): array {
    global $db, $com, $conf;
    $out = ['admin' => isAdmin(true), 'moder' => (bool)is_moder('news'), 'clean' => false];
    if (!$out['admin']) return $out;
    $all = getProbeCommentCount();
    $db->setSqlBegin();
    $hash = hash_hmac('sha256', strtolower(getIp()), getSecret('commentip'));
    $free = static fn(): mixed => $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE iphash = :hash', ['hash' => $hash]);
    $row = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_news WHERE time <= NOW() AND status != \'0\' ORDER BY id DESC LIMIT 1'));
    $tid = $row ? intval($row['id']) : 0;
    $out['target'] = $tid;
    if (!$tid) {
        $db->setSqlRollback();
        return $out;
    }
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET acomm = 2 WHERE id = :id', ['id' => $tid]);
    $out['legacy'] = [
        intval($db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE path = \'\''))['num']),
        intval($db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE pid != 0 AND path NOT LIKE \'%/%\''))['num']),
    ];
    $free();
    $root = $com->addComment('news', $tid, 'thread probe root', 'Probe');
    $free();
    $kid = $com->addComment('news', $tid, 'thread probe reply', 'Probe', '', intval($root['id']));
    $free();
    $sub = $com->addComment('news', $tid, 'thread probe sub reply', 'Probe', '', intval($kid['id']));
    $rows = [];
    foreach ([$root, $kid, $sub] as $one) {
        $got = $com->getComment(intval($one['id']));
        $rows[] = [intval($one['id']), $one['error'], intval($got['pid'] ?? -1), (string)($got['path'] ?? ''), intval($got['depth'] ?? -1)];
    }
    $out['chain'] = $rows;
    $out['nested'] = ($rows[2][3] === $rows[0][3].'/'.str_pad((string)$rows[1][0], 10, '0', STR_PAD_LEFT).'/'.str_pad((string)$rows[2][0], 10, '0', STR_PAD_LEFT));
    $free();
    $other = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_news WHERE id != :id AND time <= NOW() AND status != \'0\' ORDER BY id DESC LIMIT 1', ['id' => $tid]));
    $out['refuse'] = ['wrong' => '', 'zero' => '', 'foreign' => ''];
    $out['refuse']['wrong'] = $com->addComment('news', $tid, 'thread probe crafted', 'Probe', '', 999999999)['error'];
    $free();
    if ($other) $out['refuse']['foreign'] = $com->addComment('news', intval($other['id']), 'thread probe foreign parent', 'Probe', '', intval($root['id']))['error'];
    $free();
    $out['refuse']['zero'] = $com->addComment('news', $tid, 'thread probe root two', 'Probe', '', 0)['error'];
    $deep = intval($sub['id']);
    $made = [intval($root['id']), intval($kid['id']), $deep];
    for ($i = 4; $i <= 21; $i++) {
        $free();
        $one = $com->addComment('news', $tid, 'thread probe depth '.$i, 'Probe', '', $deep);
        $out['depth'][$i] = [$one['error'], intval($com->getComment(intval($one['id']))['depth'] ?? -1)];
        if ($one['error'] !== '') break;
        $deep = intval($one['id']);
        $made[] = $deep;
    }
    $page = $com->getList('news', $tid, 1);
    $out['page'] = [
        'total' => $page['total'],
        'roots' => intval($db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE modul = \'news\' AND cid = :cid AND pid = 0 AND status = 1 AND deleted IS NULL', ['cid' => $tid]))['num']),
        'rows' => count($page['rows']),
        'order' => array_slice(array_column($page['rows'], 'id'), 0, 4),
        'depths' => array_slice(array_column($page['rows'], 'depth'), 0, 4),
    ];
    $branch = $com->getBranch(intval($root['id']), 3);
    $out['branch'] = [count($branch['rows']), array_column($branch['rows'], 'id'), count($com->getBranch(intval($root['id']), 1)['rows'])];
    $out['slice'] = [
        'total' => $branch['total'],
        'left' => $branch['left'],
        'skip' => count($com->getBranch(intval($root['id']), 3, 3)['rows']),
        'past' => count($com->getBranch(intval($root['id']), 3, 9999)['rows']),
    ];
    $cap = $com->getList('news', $tid, 1);
    $wide = [];
    foreach ($cap['rows'] as $one) {
        if (!$one['depth']) $wide[] = [$one['kids'], $one['shown']];
    }
    $out['cap'] = ['reps' => max(1, intval($conf['comments']['reps'] ?? 5)), 'roots' => $wide];
    $com->deleteComment(intval($kid['id']));
    $tomb = $com->getList('news', $tid, 1);
    $ids = array_column($tomb['rows'], 'id');
    $out['tomb'] = [
        'kept' => in_array(intval($kid['id']), $ids, true),
        'child' => in_array(intval($sub['id']), $ids, true),
        'marked' => (string)($db->getSqlRow($db->getSqlQuery('SELECT deleted FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => intval($kid['id'])]))['deleted'] ?? ''),
        'reply' => $com->addComment('news', $tid, 'thread probe under a tombstone', 'Probe', '', intval($kid['id']))['error'],
    ];
    foreach (array_reverse(array_slice($made, 2)) as $one) $com->deleteComment($one);
    $bare = $com->getList('news', $tid, 1);
    $out['tomb']['gone'] = !in_array(intval($kid['id']), array_column($bare['rows'], 'id'), true);
    $count = static function (string $where, array $pars = []) use ($db): int {
        $row = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE '.$where, $pars));
        return $row ? intval($row['num']) : 0;
    };
    $auth = $db->getSqlRow($db->getSqlQuery('SELECT uid, COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE uid > 0 GROUP BY uid ORDER BY num DESC LIMIT 1'));
    $auid = $auth ? intval($auth['uid']) : 0;
    $out['user'] = ['uid' => $auid, 'mine' => intval($auth['num'] ?? 0)];
    if ($auid) {
        $seen = [getProbeCommentCount(), $count('pid != 0'), $count('uid = 0 AND name = \'\'')];
        $out['user']['done'] = $com->deleteUser($auid);
        $out['user']['left'] = $count('uid = :uid', ['uid' => $auid]);
        $out['user']['rows'] = (getProbeCommentCount() === $seen[0]);
        $out['user']['kids'] = ($count('pid != 0') === $seen[1]);
        $out['user']['anon'] = ($count('uid = 0 AND name = \'\'') - $seen[2]);
        $out['user']['zero'] = $com->deleteUser(0);
    }
    # The sweep is measured against drift this probe creates rather than against whatever the installation happens
    # to carry, so the case proves the mechanism instead of the history of one stand
    $out['drift'] = ['seeded' => 0, 'found' => 0, 'fixed' => 0, 'left' => 0, 'bad' => 0, 'clean' => false];
    $pick = $db->getSqlRows($db->getSqlQuery(
        'SELECT cid FROM '.PREFIX_DB.'_comment WHERE modul = \'news\' AND status = 1 AND deleted IS NULL GROUP BY cid ORDER BY COUNT(*) DESC LIMIT 2'
    )) ?: [];
    $seed = [];
    foreach ($pick as $key => $one) {
        $tid = intval($one['cid']);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET comments = comments + :delta WHERE id = :id', ['delta' => ($key === 0) ? 7 : -1, 'id' => $tid]);
        $seed[] = ['modul' => 'news', 'cid' => $tid];
    }
    $out['drift']['seeded'] = count($seed);
    if ($seed) {
        $found = $com->getCountDrift('news');
        $mine = array_values(array_filter($found, static fn(array $one): bool => in_array(['modul' => $one['modul'], 'cid' => $one['cid']], $seed, true)));
        $out['drift']['found'] = count($mine);
        $out['drift']['whole'] = count($com->getCountDrift()) >= count($found);
        $out['drift']['fixed'] = $com->updateCountDrift($mine);
        $rest = $com->getCountDrift('news');
        $out['drift']['left'] = count(array_filter($rest, static fn(array $one): bool => in_array(['modul' => $one['modul'], 'cid' => $one['cid']], $seed, true)));
        $out['drift']['bad'] = $com->updateCountDrift([['modul' => 'gallery', 'cid' => 1], ['modul' => 'news', 'cid' => 0], []]);
        $out['drift']['clean'] = ($com->updateCountDrift($mine) === 0);
    }
    $db->setSqlRollback();
    $out['clean'] = (getProbeCommentCount() === $all);
    $out['count'] = [$all, getProbeCommentCount()];
    return $out;
}

# Report what stage 3 promises: the queue row is written inside the transaction the comment is written in, once per stored comment, and nothing is delivered inside the request
# The two writes of the handler run in its order inside one rolled back transaction; the handler itself cannot run here, because getVar() reads a scalar through filter_input()
# Every scenario is measured as a difference between two counts of both tables, so a job without a comment or a comment without its job is a number rather than an argument
function getProbeCommentNotify(): array {
    global $db, $conf, $com, $tpl, $mailer, $locale;
    $out = ['addmail' => (string)($conf['comments']['addmail'] ?? ''), 'subs' => 0, 'target' => 0, 'clean' => false];
    $mails = static function (string $where = '', array $pars = []) use ($db): int {
        $sql = 'SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_mail'.(($where !== '') ? ' WHERE '.$where : '');
        $row = $db->getSqlRow($db->getSqlQuery($sql, $pars));
        return $row ? intval($row['num']) : 0;
    };
    $post = static function (int $tid, string $body, string $key) use ($com, $conf, $tpl): array {
        $new = $com->addComment('news', $tid, $body, 'Probe', $key);
        if ($new['error'] === '' && $new['new']) {
            $link = $conf['homeurl'].'/index.php?name=news&op=view&id='.$tid.'#'.$new['id'];
            $clink = $tpl->getHtmlFrag('link', ['href' => $link, 'title' => '', 'label_html' => $link]);
            addAdminMail((bool)$conf['comments']['addmail'], 'news', $new['name'], getModuleName('news'), 1, $clink);
        }
        return $new;
    };
    $scope = $conf['multilingual'] ? ' AND (lang = :lang OR lang = \'\')' : '';
    $langs = $conf['multilingual'] ? ['lang' => $locale] : [];
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT super, modules FROM '.PREFIX_DB.'_admins WHERE smail = \'1\''.$scope, $langs)) ?: [] as $one) {
        if ($one['super'] || in_array('news', getAdminModuleNames((string)$one['modules']), true)) $out['subs']++;
    }
    $hash = hash_hmac('sha256', strtolower(getIp()), getSecret('commentip'));
    $free = static fn(): mixed => $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE iphash = :hash', ['hash' => $hash]);
    $all = getProbeCommentCount();
    $sent = $mails();
    $db->setSqlBegin();
    $free();
    $tid = 0;
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_news ORDER BY id DESC LIMIT 25')) ?: [] as $one) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET acomm = 2 WHERE id = :id', ['id' => intval($one['id'])]);
        if ($com->getTargetMode('news', intval($one['id'])) === CommentMode::Open) {
            $tid = intval($one['id']);
            break;
        }
    }
    if (!$tid) {
        $db->setSqlRollback();
        return $out;
    }
    $out['target'] = $tid;
    $key = '00112233445566778899aabbccddeeff';
    $was = [getProbeCommentCount(), $mails()];
    $stop = $post($tid, '', '');
    $out['refuse'] = [$stop['error'] !== '', $stop['new'], getProbeCommentCount() - $was[0], $mails() - $was[1]];
    $free();
    $was = [getProbeCommentCount(), $mails()];
    $time = hrtime(true);
    $new = $post($tid, 'stage three probe body', $key);
    $out['msec'] = round((hrtime(true) - $time) / 1000000, 1);
    $cid = intval($new['id']);
    $out['add'] = [$new['error'], $new['new'], getProbeCommentCount() - $was[0], $mails() - $was[1], getProbeCommentCount('reqkey = :key', ['key' => $key])];
    $sql = 'SELECT kind, status, tries, prio, body, ref FROM '.PREFIX_DB.'_mail ORDER BY id DESC LIMIT '.max(1, $mails() - $was[1]);
    $out['queued'] = [];
    foreach ($db->getSqlRows($db->getSqlQuery($sql)) ?: [] as $one) {
        $link = str_contains((string)$one['body'], '#'.$cid);
        $out['queued'][] = [(string)$one['kind'], intval($one['status']), intval($one['tries']), intval($one['prio']), intval($one['ref']), $link];
    }
    $free();
    $was = [getProbeCommentCount(), $mails()];
    $again = $post($tid, 'stage three probe replay', $key);
    $out['replay'] = [$again['error'], $again['new'], $cid === intval($again['id']), getProbeCommentCount() - $was[0], $mails() - $was[1]];
    $bad = $mailer->addQueue(['kind' => 'comment', 'email' => 'not an address', 'title' => 'probe', 'body' => 'probe', 'sender' => $conf['adminmail'], 'prio' => 1]);
    $out['fail'] = [$bad, $db->checkSqlActive(), getProbeCommentCount('id = :id', ['id' => $cid]), $mailer->getError()];
    $time = hrtime(true);
    addAdminMail((bool)$conf['comments']['addmail'], 'news', 'Probe', getModuleName('news'), 1, 'probe link');
    $out['notify'] = round((hrtime(true) - $time) / 1000000, 1);
    $db->setSqlRollback();
    $out['gone'] = [getProbeCommentCount() - $all, $mails() - $sent];
    $out['clean'] = ($all === getProbeCommentCount() && $sent === $mails());
    return $out;
}

# Render the profile feed the way it was rendered before batch 5, from the single UNION that still carried the comment branch
# It is a verbatim copy of the pre-move function and exists only so the migrated one can be compared against it byte for byte on the live rows
function getProbeFeedLegacy(int $uid): string {
    global $db, $conf, $tpl, $prs;
    if ($uid < 1 || ($conf['users']['prof'] == 1 && !is_user() && !isAdmin())) return '';
    $limit = intval(getUserNews(25));
    $parts = [];
    $params = [];
    $lists = [];
    foreach (getProfileModules() as $mod => $inf) {
        if ($mod != 'comm' && !is_active($mod)) continue;
        if ($mod == 'comm') {
            $from = PREFIX_DB.'_comment WHERE uid = :ucomm AND status != \'0\' AND deleted IS NULL';
            $parts[] = "(SELECT 'comm' AS mkey, id, cid AS ref, modul AS sub, body AS title, time, format AS rc, 0 AS rt FROM ".$from.' ORDER BY id DESC LIMIT 0,'.$limit.')';
        } else {
            $ron = !empty(explode('|', (string)($conf['ratings'][$mod] ?? ''))[1]);
            $rsel = ($ron && $inf['rate']) ? $inf['rate'][0].' AS rc, '.$inf['rate'][1].' AS rt' : '0 AS rc, 0 AS rt';
            $from = PREFIX_DB.'_'.$inf['table'].' WHERE '.str_replace(':uid', ':u'.$mod, $inf['where']);
            $parts[] = "(SELECT '".$mod."' AS mkey, id, 0 AS ref, '' AS sub, title, time, ".$rsel.' FROM '.$from.' ORDER BY id DESC LIMIT 0,'.$limit.')';
        }
        $params['u'.$mod] = $uid;
        $lists[$mod] = [];
    }
    if (!$parts) return '';
    $result = $db->getSqlQuery(implode(' UNION ALL ', $parts), $params);
    while ([$key, $id, $cid, $cmod, $label, $time, $cnt, $tot] = $db->getSqlRow($result)) {
        if ($key == 'comm') {
            $label = cutstr(str_replace([_QUOTE, _CODE], '', filterText($prs->filterContent($label, true, $conf['name'], 0, (string)$cnt))), 70);
            $cnt = 0;
            $href = getSeoUrl(['name' => $cmod, 'op' => 'view', 'id' => $cid]).'#'.$id;
        } elseif ($key == 'jokes') {
            $href = getSeoUrl(['name' => 'jokes']).'#'.$id;
        } elseif ($key == 'forum') {
            $href = getSeoUrl(['name' => $key, 'op' => 'view', 'id' => $id, 'title' => $label]);
        } else {
            $href = getSeoUrl(['name' => $key, 'op' => 'view', 'id' => $id, 'title' => $label]).'#'.$id;
        }
        $lists[$key][] = [
            'datehtml' => $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => format_time($time, _TIMESTRING), 'text' => format_time($time)]),
            'href' => $href,
            'label' => $label,
            'rating' => ($cnt > 0) ? number_format($tot / $cnt, 2) : '',
        ];
    }
    $tabs = [];
    $texts = [];
    foreach (getProfileModules() as $mod => $inf) {
        if (!isset($lists[$mod])) continue;
        $tabs[] = $inf['title'];
        $texts[] = $tpl->getHtmlPart('account-profile-feed-list', ['entries' => $lists[$mod], 'icon_name' => $inf['icon'], 'empty_text' => _NO_INFO]);
    }
    return $tpl->getHtmlPart('account-profile-feed', ['title' => _LASTACTIVITY, 'tabs_html' => getNaviTabs(0, 'profeed', $tabs, $texts)]);
}

# Report whether the migrated profile feed renders the same markup as the UNION it replaces, and whether the hub writes the three fields the comment branch of its own UNION wrote
function getProbeCommentFeed(): array {
    global $db, $com;
    $GLOBALS['conf']['name'] = 'account';
    $out = ['feed' => [], 'hub' => []];
    $sql = 'SELECT uid, COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE uid > 0 GROUP BY uid ORDER BY num DESC LIMIT 6';
    $uids = array_map(static fn(array $one): int => intval($one['uid']), $db->getSqlRows($db->getSqlQuery($sql)) ?: []);
    $free = 'SELECT id FROM '.PREFIX_DB.'_users WHERE id NOT IN (SELECT DISTINCT uid FROM '.PREFIX_DB.'_comment WHERE uid > 0) ORDER BY id ASC LIMIT 1';
    $row = $db->getSqlRow($db->getSqlQuery($free));
    if ($row) $uids[] = intval($row['id']);
    foreach (array_merge($uids, [999999999, 0, -4]) as $uid) {
        $was = preg_replace('#profeed0-\d+#', 'profeed0-N', getProbeFeedLegacy($uid));
        $now = preg_replace('#profeed0-\d+#', 'profeed0-N', getProfileLastView($uid));
        $at = 0;
        while ($at < strlen($was) && $at < strlen($now) && $was[$at] === $now[$at]) $at++;
        $seen = ($was === $now);
        $cut = static fn(string $one): string => $seen ? '' : mb_substr(substr($one, $at), 0, 90, 'UTF-8');
        $out['feed'][$uid] = [strlen($was), strlen($now), $seen, $cut($was), $cut($now)];
    }
    foreach ($uids as $uid) {
        $one = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_comment WHERE uid = :uid AND status != \'0\'', ['uid' => $uid]));
        $was = [(string)intval($one['num']), '', '0'];
        $out['hub'][$uid] = [$was, [(string)$com->getUserCount($uid), '', '0']];
    }
    return $out;
}

# Count the comment rows a probe scenario left behind, so a delete is measured as a difference between two reads instead of being claimed by its return value
function getProbeCommentCount(string $where = '', array $pars = []): int {
    global $db;
    $sql = 'SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_comment'.(($where !== '') ? ' WHERE '.$where : '');
    $row = $db->getSqlRow($db->getSqlQuery($sql, $pars));
    return $row ? intval($row['num']) : 0;
}

# Report whether deleteTarget() removes exactly the rows the eight module delete handlers removed before the move, inside a transaction that is always rolled back
# The crafted arguments matter because the target id list is the one place a delete handler used to interpolate, so both the list and the module name are driven with hostile values
function getProbeCommentTarget(): array {
    global $db, $com;
    $mods = ['faq', 'files', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $out = ['rows' => [], 'bulk' => [], 'cross' => [], 'refuse' => [], 'craft' => [], 'count' => [], 'clean' => false];
    $urow = $db->getSqlRow($db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.'_comment WHERE uid > 0 GROUP BY uid ORDER BY COUNT(*) DESC LIMIT 1'));
    $uid = $urow ? intval($urow['uid']) : 0;
    $was = getProbeCommentCount('uid = :uid AND status != \'0\'', ['uid' => $uid]);
    $out['count'] = [$was, $com->getUserCount($uid), $com->getUserCount(0), $com->getUserCount(-5)];
    $all = getProbeCommentCount();
    $db->setSqlBegin();
    foreach ($mods as $mod) {
        $sql = 'SELECT cid, COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE modul = :mod GROUP BY cid ORDER BY num DESC LIMIT 1';
        $pick = $db->getSqlRow($db->getSqlQuery($sql, ['mod' => $mod]));
        if (!$pick) {
            $out['rows'][$mod] = [];
            continue;
        }
        $cid = intval($pick['cid']);
        $tot = getProbeCommentCount();
        $done = $com->deleteTarget($mod, [$cid]);
        $left = getProbeCommentCount('modul = :mod AND cid = :cid', ['mod' => $mod, 'cid' => $cid]);
        $out['rows'][$mod] = [$done, intval($pick['num']), $left, $tot - getProbeCommentCount()];
    }
    $two = [];
    $sql = 'SELECT cid, COUNT(*) AS num FROM '.PREFIX_DB.'_comment WHERE modul = \'news\' GROUP BY cid ORDER BY num DESC LIMIT 2';
    foreach ($db->getSqlRows($db->getSqlQuery($sql)) ?: [] as $row) $two[intval($row['cid'])] = intval($row['num']);
    if (count($two) === 2) {
        $tot = getProbeCommentCount();
        $done = $com->deleteTarget('news', array_keys($two));
        $left = getProbeCommentCount('modul = \'news\' AND cid IN ('.implode(', ', array_keys($two)).')');
        $out['bulk'] = [$done, array_sum($two), $left, $tot - getProbeCommentCount()];
    }
    $sql = 'SELECT cid FROM '.PREFIX_DB.'_comment GROUP BY cid HAVING COUNT(DISTINCT modul) > 1 ORDER BY COUNT(DISTINCT modul) DESC, cid ASC LIMIT 1';
    $row = $db->getSqlRow($db->getSqlQuery($sql));
    if ($row) {
        $cid = intval($row['cid']);
        $one = $db->getSqlRow($db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_comment WHERE cid = :cid ORDER BY modul ASC LIMIT 1', ['cid' => $cid]));
        $mod = (string)$one['modul'];
        $pars = ['cid' => $cid, 'mod' => $mod];
        $mine = getProbeCommentCount('cid = :cid AND modul = :mod', $pars);
        $rest = getProbeCommentCount('cid = :cid AND modul != :mod', $pars);
        $done = $com->deleteTarget($mod, [$cid]);
        $out['cross'] = [$done, $mine, $rest, getProbeCommentCount('cid = :cid AND modul = :mod', $pars), getProbeCommentCount('cid = :cid AND modul != :mod', $pars)];
    }
    $tot = getProbeCommentCount();
    $out['refuse'] = [
        'nomod' => $com->deleteTarget('', [1]),
        'noids' => $com->deleteTarget('news', []),
        'zero' => $com->deleteTarget('news', [0, -3, 'abc']),
        'unknown' => $com->deleteTarget('gallery', [1]),
        'kept' => ($tot === getProbeCommentCount()),
    ];
    $mine = getProbeCommentCount('modul = \'news\' AND cid = 1');
    $tot = getProbeCommentCount();
    $done = $com->deleteTarget('news', ['1) OR 1=1 -- ', '1; DROP TABLE x']);
    $out['craft'] = [$done, $mine, $tot - getProbeCommentCount(), getProbeCommentCount() > 0];
    $tot = getProbeCommentCount();
    $out['craftmod'] = [$com->deleteTarget('news\' OR modul != \'', [1]), $tot - getProbeCommentCount()];
    $db->setSqlRollback();
    $out['clean'] = ($all === getProbeCommentCount());
    return $out;
}

$mode = $argv[1] ?? 'core';
$conf = $GLOBALS['conf'];
$chost = strtolower((string)parse_url((string)$conf['homeurl'], PHP_URL_HOST));
$out = [];
if ($mode === 'core') {
    $out['valid'] = [
        'token_ajax' => checkDynamicMark('token', 'ajax'),
        'token_account' => checkDynamicMark('token', 'account'),
        'token_scheduler' => checkDynamicMark('token', 'scheduler'),
        'captcha_login' => checkDynamicMark('captcha', 'login'),
        'captcha_register' => checkDynamicMark('captcha', 'register'),
        'captcha_contact' => checkDynamicMark('captcha', 'contact'),
        'captcha_admin' => checkDynamicMark('captcha', 'adminlogin'),
        'captcha_empty' => checkDynamicMark('captcha', ''),
        'voting_id' => checkDynamicMark('voting', '17'),
        'token_empty' => checkDynamicMark('token', ''),
        'token_admin' => checkDynamicMark('token', 'admin'),
        'captcha_comment' => checkDynamicMark('captcha', 'comment'),
        'voting_zero' => checkDynamicMark('voting', '0'),
        'voting_neg' => checkDynamicMark('voting', '-5'),
        'voting_huge' => checkDynamicMark('voting', '1234567890'),
        'voting_inject' => checkDynamicMark('voting', '1;drop'),
        'shell' => checkDynamicMark('shell', 'id'),
    ];
    $mark = getDynamicMark('token', 'ajax');
    $sub = setDynamicRegions('A '.$mark.' B');
    $out['mark_shape'] = (bool)preg_match('#^\[\[sldyn:token:ajax:[a-f0-9]{16}\]\]$#', $mark);
    $out['sub_token'] = (bool)preg_match('#^A [a-f0-9]{64} B$#', $sub);
    $forged = '[[sldyn:token:ajax:'.str_repeat('0', 16).']]';
    $out['forged_literal'] = (setDynamicRegions($forged) === $forged);
    $junk = '[[sldyn:shell:id:'.substr(hash_hmac('sha256', 'shell:id', getSecret('dynreg')), 0, 16).']]';
    $out['junk_empty'] = (setDynamicRegions($junk) === '');
    $now = time();
    $iph = substr(hash_hmac('sha256', getIp(), getSecret('stats')), 0, 16);
    $sid = str_repeat('ab', 8);
    $_COOKIE[$conf['user_c'].'-stats'] = getProbeCookie(['v2', $sid, $now - 100, $now - 50, 4, 'DE', $now - 10, $iph]);
    $state = updateStatsCookie(0);
    $out['v2_keys'] = array_keys($state);
    $out['v2_depth'] = $state['sess']['depth'] ?? 0;
    $out['v2_isnew'] = $state['sess']['is_new'] ?? true;
    $out['v2_country'] = $state['country'];
    $_COOKIE[$conf['user_c'].'-stats'] = getProbeCookie(['v1', $sid, $now - 100, $now - 50, 4, 'DE', $now - 10, date('d.m.Y'), $iph]);
    $vone = updateStatsCookie(0);
    $out['v1_isnew'] = $vone['sess']['is_new'] ?? false;
    $out['v1_depth'] = $vone['sess']['depth'] ?? 0;
    $out['poison_before'] = checkCachePoison();
    $out['reject_empty'] = (getDynamicMark('shell', 'id') === '');
    $out['poison_after'] = checkCachePoison();
    $rlog = is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '';
    $out['reject_logged'] = str_contains($rlog, 'Rejected dynamic-region marker: shell');
} elseif ($mode === 'route') {
    $out = getProbeRoute('/index.php?name=news&num=1', ['name' => 'news', 'num' => '1'], $chost);
} elseif ($mode === 'routenum') {
    $out = getProbeRoute('/index.php?name=news', ['name' => 'news'], $chost);
} elseif ($mode === 'routebad') {
    $out = getProbeRoute('/index.php?name=news&foo=1', ['name' => 'news', 'foo' => '1'], $chost);
} elseif ($mode === 'routehost') {
    $out = getProbeRoute('/index.php?name=news', ['name' => 'news'], 'evil.example');
} elseif ($mode === 'stathit') {
    $_SERVER['REMOTE_ADDR'] = $argv[3] ?? '127.0.0.1';
    $rep = max(1, (int)($argv[4] ?? 1));
    for ($num = 0; $num < $rep; $num++) {
        updateStatsTrack('/index.php?name=news', 0, ['sess' => null, 'country' => 'DE']);
    }
    $out = getProbeCounter();
} elseif ($mode === 'appendfail') {
    $out['blocked'] = addFile(COUNTER_DIR.'/blocked', 'payload,', 'none', false, 'a');
    $out['plain'] = addFile(COUNTER_DIR.'/plain.log', 'first,', 'none', false, 'a');
    $out['again'] = addFile(COUNTER_DIR.'/plain.log', 'second,', 'none', false, 'a');
    $out['body'] = is_file(COUNTER_DIR.'/plain.log') ? (string)file_get_contents(COUNTER_DIR.'/plain.log') : '';
    $out['log'] = is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '';
} elseif ($mode === 'filters') {
    $out['num'] = [filterNum('123'), filterNum('abc123def'), filterNum('abc'), filterNum(''), filterNum('-5'), filterNum('999999999')];
    $out['word'] = [filterWord('hello123'), filterWord('a%b&c/d'), filterWord('test<script>alert</script>'), filterWord('Привет'), filterWord('say "hi" \'now\''), filterWord('hello world')];
    $out['var'] = [filterVar('hello-world_123'), filterVar('hello world'), filterVar('test<script>'), filterVar('test\'injection')];
    $out['vararr'] = [filterVar(['one', 'two-three']), filterVar(['ok', 'bad value'])];
    $out['text'] = [filterText('<b>bold</b>'), filterText('say "hi"'), filterText('<b>tag</b>', 2), filterText('[usephp]echo 1;[/usephp]normal text'), filterText('  hello  ')];
    $out['url'] = [filterWebUrl('example.com'), filterWebUrl('https://example.com'), filterWebUrl(''), filterWebUrl('http://')];
    $out['html'] = [filterHtml('cost $5'), filterHtml('back\\slash'), filterHtml('say "hi" and \'bye\''), filterHtml('')];
    $out['fields'] = [filterFields(['a' => ' one ', 'b' => 'two']), filterFields([]), filterFields('plain')];
} elseif ($mode === 'getvar') {
    $_POST = [
        'flat' => ['red', '', 'blue'],
        'rows' => ['0' => 'one', '1' => '', '2' => 'three'],
        'lng' => ['ru' => ['_A' => 'Привет', 'plain' => ''], 'de' => ['_A' => 'Hallo']],
        'deep' => ['a' => ['b' => ['c' => 'found']]],
        'name' => 'scalar value',
        'digits' => 'a12b34',
    ];
    $_GET = ['flat' => ['get-one'], 'lng' => ['ru' => ['_A' => 'from get']], 'name' => 'get scalar'];
    $out['all_raw'] = getVar('post', 'flat[]', '');
    $out['all_typed'] = getVar('post', 'flat[]', 'var');
    $out['idx_one'] = getVar('post', 'flat[2]', '');
    $out['idx_empty'] = getVar('post', 'flat[1]', '', 'fallback');
    $out['idx_missing'] = getVar('post', 'flat[9]', '', 'fallback');
    $out['nest_all'] = getVar('post', 'lng[ru][]', '');
    $out['nest_named'] = getVar('post', 'lng[ru][_A]', '');
    $out['nest_other'] = getVar('post', 'lng[de][_A]', '');
    $out['nest_missing'] = getVar('post', 'lng[fr][]', '');
    $out['nest_deep'] = getVar('post', 'deep[a][b][c]', '');
    $out['nest_deep_all'] = getVar('post', 'deep[a][b][]', '');
    $out['nest_get'] = getVar('get', 'lng[ru][_A]', '');
    $out['nest_req'] = getVar('req', 'flat[]', '');
    $out['scalar_key_on_array'] = getVar('post', 'flat', 'raw', 'fallback');
    $out['array_default_scalar_filter'] = getVar('post', 'missing', 'num', []);
    $out['branch_untouched'] = getVar('post', 'rows[]', 'raw');
} elseif ($mode === 'comment') {
    $out = getProbeComment();
} elseif ($mode === 'commentread') {
    $out = getProbeCommentRead();
} elseif ($mode === 'commentwrite') {
    $out = getProbeCommentWrite(false);
} elseif ($mode === 'commentguest') {
    $out = getProbeCommentWrite(true);
} elseif ($mode === 'commenttarget') {
    $out = getProbeCommentTarget();
} elseif ($mode === 'commentstage') {
    $out = getProbeCommentStage2();
} elseif ($mode === 'commentthread') {
    $out = getProbeCommentThread();
} elseif ($mode === 'commentnotify') {
    $out = getProbeCommentNotify();
} elseif ($mode === 'commentformat') {
    $out = getProbeCommentFormat();
} elseif ($mode === 'commentfeed') {
    $out = getProbeCommentFeed();
} elseif ($mode === 'geoip') {
    $peak = memory_get_peak_usage(true);
    $out['country'] = Geoip::getCountry((string)($conf['geoip_test'] ?? '217.50.80.228'));
    $out['stable'] = ($out['country'] === Geoip::getCountry((string)($conf['geoip_test'] ?? '217.50.80.228')));
    $out['sixte'] = Geoip::getCountry('2001:4860:4860::8888');
    $out['four'] = Geoip::getCountry('8.8.8.8');
    $out['bad'] = Geoip::getCountry('not-an-ip');
    $out['grow'] = memory_get_peak_usage(true) - $peak;
    $out['size'] = is_file(BASE_DIR.'/'.$conf['geoip_country']) ? (int)filesize(BASE_DIR.'/'.$conf['geoip_country']) : 0;
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
