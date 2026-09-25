<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for stage S17 of docs/node: the clean installation of the release creates the ten shipped Node profiles, answered over real HTTP
# It copies the tracked tree without docs, tests and tools into scratch, creates one disposable MariaDB database, and serves the copy with two built-in servers:
# the installer and the panel run on the first, the address the installer records points at the second, so the refusal of a type directory the panel asks for
# while it activates a type is answered by a server that is free; every exchange is a real request to the real setup.php, admin.php and index.php of the copy
# The report answers what each exchange returned and what the database, the configuration and the files of the copy hold afterwards; the site is never touched
# The second argument keep leaves the copy served on the first port after the checks until the file stop appears in the scratch root, for a browser to walk it;
# the argument fail leaves a user file in uploads/jokes of the copy before the first administrator is created and checks only that installation;
# the arguments update <dump> <revision> <prefix> [<snapshot>] upgrade a real 6.2 site instead: its dump, its configuration as the revision tracked it,
# the field definitions and corrected sources from the snapshot directory of its own field update, and the checks of stage S18
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$iwork = str_replace('\\', '/', (string)($argv[1] ?? sys_get_temp_dir().'/slaed_node_install'));
$ikeep = ($argv[2] ?? '') === 'keep';
$ifail = ($argv[2] ?? '') === 'fail';
$iupdate = ($argv[2] ?? '') === 'update';
$isite = $iwork.'/site';

# The table prefix of the disposable database: the one of the dump an update loads, probe for a clean installation; and the password of the accounts the checks act as
define('IPREF', (($argv[2] ?? '') === 'update') ? (string)($argv[5] ?? '') : 'probe');
const IPASS = 'Probe-secret-17';

# The ten shipped profiles in the order of their sort
const IPROFS = ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'docs'];

# Remove one scratch tree
function deleteInstallTree(string $dir): void {
    if (is_link($dir) || is_file($dir)) {
        unlink($dir);
        return;
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteInstallTree($dir.'/'.$one);
    rmdir($dir);
}

# Copy the release into scratch: every tracked or new file git does not ignore, without the documentation, the tests and the tools, and the empty storage
# A tracked configuration source is taken as committed, because the stand writes its own types and settings into the working copies and a release ships none of them;
# config/db.php is ignored by git and so absent, as in a release, and the installer has to create it
function addInstallTree(string $site): int {
    $list = explode("\0", (string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' ls-files -co --exclude-standard -z'));
    $num = 0;
    foreach ($list as $one) {
        if ($one === '' || preg_match('#^(?:docs|tests|tools|\.github)/#', $one) || !is_file(BASE_DIR.'/'.$one)) continue;
        $dest = $site.'/'.$one;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
        $quiet = ' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $ship = str_starts_with($one, 'config/') ? shell_exec('git -C '.escapeshellarg(BASE_DIR).' show '.escapeshellarg('HEAD:'.$one).$quiet) : null;
        if (is_string($ship) && $ship !== '') file_put_contents($dest, $ship);
        else copy(BASE_DIR.'/'.$one, $dest);
        $num++;
    }
    foreach (['backup', 'cache', 'counter', 'logs', 'sitemap', 'captcha'] as $one) if (!is_dir($site.'/storage/'.$one)) mkdir($site.'/storage/'.$one, 0777, true);
    return $num;
}

# The router of both servers: the directory of a type with its guard refused as the shared web server rule does, static files served as they are,
# and the entry points of the copy required with the account the checks name in X-Probe-User or X-Probe-Admin put where the core reads it
function setInstallRouter(string $site): void {
    $code = <<<'PHP'
<?php
$ipath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (preg_match('#^/uploads/([a-z0-9_]+)/#', $ipath, $ihit) && is_file(__DIR__.'/uploads/'.$ihit[1].'/.htaccess')) {
    http_response_code(403);
    exit;
}
if (!in_array($ipath, ['/', '/index.php', '/admin.php', '/setup.php'], true)) return false;
foreach (['HTTP_HOST', 'REQUEST_URI', 'HTTP_REFERER', 'HTTP_USER_AGENT', 'REMOTE_ADDR'] as $ikey) if (isset($_SERVER[$ikey])) putenv($ikey.'='.$_SERVER[$ikey]);
$iglob = require __DIR__.'/config/global.php';
if (isset($_SERVER['HTTP_X_PROBE_USER'])) $_COOKIE[$iglob['user_c'].'-account'] = $_SERVER['HTTP_X_PROBE_USER'];
session_start();
if (isset($_SERVER['HTTP_X_PROBE_ADMIN'])) $_SESSION[$iglob['admin_c']] = $_SERVER['HTTP_X_PROBE_ADMIN'];
chdir(__DIR__);
require __DIR__.(($ipath === '/') ? '/index.php' : $ipath);
PHP;
    file_put_contents($site.'/probe_router.php', $code."\n");
}

# The database server the probe works on: the one of the site configuration, or another server the environment names in SLAED_PROBE_DB as host|user|password,
# which is how the release is installed on a second server kind; the name of the site database is kept, so it can never be the disposable one
function getInstallCred(): array {
    $dbc = (require BASE_DIR.'/config/db.php')['db'];
    $env = (string)getenv('SLAED_PROBE_DB');
    if ($env !== '') [$dbc['host'], $dbc['uname'], $dbc['pass']] = array_pad(explode('|', $env, 3), 3, '');
    return $dbc;
}

# One connection to the database server of the probe, with a database selected when one is named
function getInstallPdo(string $name = ''): PDO {
    $dbc = getInstallCred();
    $dsn = 'mysql:host='.$dbc['host'].($name === '' ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, $dbc['uname'], $dbc['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the empty disposable database the installer fills; the site database can never be the one created
function addInstallBase(): string {
    $dbc = getInstallCred();
    $name = 'slaed_inst_'.bin2hex(random_bytes(4));
    if ($name === $dbc['name']) throw new RuntimeException('The disposable name collides with the site database');
    getInstallPdo()->exec('CREATE DATABASE `'.$name.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $name;
}

# Drop the disposable database again
function deleteInstallBase(string $name): bool {
    if (!str_starts_with($name, 'slaed_inst_')) return false;
    try {
        getInstallPdo()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
        return true;
    } catch (Throwable) {
        return false;
    }
}

# One free local port
function getInstallPort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    return $port;
}

# Start one built-in server over the copy with its router and wait until it answers
function addInstallServer(string $site, int $port): mixed {
    $log = ['file', dirname($site).'/server-'.$port.'.log', 'a'];
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $site, $site.'/probe_router.php'], [1 => $log, 2 => $log], $pipes, $site);
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) throw new RuntimeException('The install server did not start');
    fclose($test);
    return $proc;
}

# Stop one built-in server with the processes it started
function deleteInstallServer(mixed $proc): void {
    if (!is_resource($proc)) return;
    $pid = (int)(proc_get_status($proc)['pid'] ?? 0);
    if ($pid > 0 && PHP_OS_FAMILY === 'Windows') exec('taskkill /F /T /PID '.$pid.' 2>NUL');
    proc_terminate($proc);
    proc_close($proc);
}

# One real exchange on the first server as guest, the administrator or a site account with the cookies of that identity: the status, the headers by lower-case name and the body
function getInstallReply(array $who, string $method, string $path, array $post = [], array $head = []): array {
    global $iport, $iwork;
    $curl = curl_init('http://127.0.0.1:'.$iport.'/'.ltrim($path, '/'));
    $jar = $iwork.'/jar-'.md5((string)json_encode($who)).'.txt';
    curl_setopt_array($curl, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $send = $head;
    if (isset($who['admin'])) $send[] = 'X-Probe-Admin: '.$who['admin'];
    if (isset($who['user'])) $send[] = 'X-Probe-User: '.$who['user'];
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $send, CURLOPT_TIMEOUT => 300,
        CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    $raw = (string)curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $size = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $heads = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (str_contains($line, ':')) {
            [$key, $val] = explode(':', $line, 2);
            $heads[strtolower(trim($key))] = trim($val);
        }
    }
    return ['code' => $code, 'head' => $heads, 'body' => substr($raw, $size)];
}

# The hidden token of the first form of a page that carries the given op value or mark
function getInstallToken(string $html, string $mark): string {
    preg_match_all('#<form\b.*?</form>#s', $html, $all);
    foreach ($all[0] as $form) {
        $flat = (string)preg_replace('/\s+/', ' ', $form);
        $good = preg_match('/^[a-z]+$/D', $mark) ? str_contains($flat, 'name="op" value="'.$mark.'"') : str_contains($flat, $mark);
        if ($good && preg_match('#name="token"\s+value="([a-f0-9]{64})"#', $form, $hit)) return $hit[1];
    }
    return '';
}

# One configuration source of the copy as the core reads it
function getInstallConf(string $name): array {
    global $isite;
    $file = $isite.'/config/'.$name.'.php';
    return is_file($file) ? (array)(static fn(string $path): mixed => require $path)($file) : [];
}

# The lines the three error logs of the copy hold
function getInstallLogs(): array {
    global $isite;
    $out = [];
    foreach (['error_php', 'error_sql', 'error_site'] as $one) {
        $file = $isite.'/storage/logs/'.$one.'.log';
        $out[$one] = is_file($file) ? array_values(array_filter(explode("\n", (string)file_get_contents($file)), 'strlen')) : [];
    }
    return $out;
}

# The shipped profile of a name, decoded
function getInstallProfile(string $name): array {
    return (array)json_decode((string)file_get_contents(BASE_DIR.'/modules/node/profiles/'.$name.'.json'), true);
}

# The installer and the first administrator: the mark the installer leaves, the ten types, their four shared areas, their directories, the starter material,
# the mark gone afterwards, the notice of the next page, and a second first-administrator request that creates nothing; fail leaves a user file in uploads/jokes first
function getInstallSetup(PDO $pdo, string $base): array {
    global $iguard, $isite, $ifail;
    $dbc = getInstallCred();
    $glob = getInstallConf('global');
    $form = ['op' => 'save', 'setup' => 'new', 'xhost' => $dbc['host'], 'xuname' => $dbc['uname'], 'xpass' => $dbc['pass'], 'xname' => $base, 'xprefix' => IPREF,
        'xsync' => '1', 'xafile' => 'admin'];
    $cook = 'Cookie: '.$glob['user_c'].'-lang=ru';
    $page = getInstallReply(['jar' => 'form'], 'GET', 'setup.php?op=config', [], [$cook]);
    $none = !is_file($isite.'/config/db.php');
    $plant = ['node' => fn($v) => ['types' => ['news' => ['version' => 1], 'stale' => ['version' => 3]]] + $v,
        'fields' => fn($v) => $v + ['node' => ['stale' => ['field1' => ['title' => 'Stale']]]],
        'uploads' => fn($v) => ['news' => 'x', 'stale' => 'x'] + $v,
        'ratings' => fn($v) => ['node.news' => [], 'node.stale' => []] + $v];
    foreach ($plant as $name => $edit) {
        $data = getInstallConf($name);
        $data[$name] = $edit($data[$name]);
        file_put_contents($isite.'/config/'.$name.'.php', "<?php\nreturn ".var_export($data, true).";\n");
    }
    $done = getInstallReply([], 'POST', 'setup.php', $form, ['Host: 127.0.0.1:'.$iguard, $cook]);
    $ghost = str_contains($done['body'], 'types of an earlier installation removed with their fields, upload and rating rules: news, stale');
    $mark = getInstallConf('update')['update'] ?? [];
    $out = ['setup' => [$done['code'], $mark['node'] ?? '', (getInstallConf('global')['homeurl'] ?? '') === 'http://127.0.0.1:'.$iguard]];
    $out['dbfile'] = [$none, $page['code'], str_contains($page['body'], 'name="xhost"'), (getInstallConf('db')['db']['prefix'] ?? '') === IPREF];
    $hash = fn(): array => array_map(fn($v) => sha1_file($isite.'/config/'.$v.'.php'), ['db', 'global', 'security']);
    $was = $hash();
    $page = getInstallReply([], 'GET', 'setup.php?op=config', [], [$cook]);
    $done = getInstallReply([], 'POST', 'setup.php', ['xname' => 'other', 'xafile' => 'moved'] + $form, ['Host: 127.0.0.1:'.$iguard, $cook]);
    $out['lock'] = [str_contains($page['body'], 'config/setup.unlock'), str_contains($page['body'].$done['body'], 'name="xhost"'),
        $dbc['pass'] !== '' && str_contains($page['body'].$done['body'], $dbc['pass']), $hash() === $was, is_file($isite.'/admin.php')];
    touch($isite.'/config/setup.unlock');
    $out['unlock'] = str_contains(getInstallReply([], 'GET', 'setup.php?op=config', [], [$cook])['body'], 'name="xhost"');
    unlink($isite.'/config/setup.unlock');
    $out['before'] = (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_node_types')->fetchColumn();
    if ($ifail) {
        mkdir($isite.'/uploads/jokes', 0777, true);
        file_put_contents($isite.'/uploads/jokes/stray.txt', 'a file of a user');
    }
    $time = microtime(true);
    $first = getInstallReply([], 'POST', 'admin.php', ['op' => 'add_admin', 'aname' => 'Probe', 'aemail' => 'probe@probe.test', 'apwd' => IPASS, 'apwd2' => IPASS,
        'auser_new' => '1'], [$cook]);
    $out['admin'] = [$first['code'], round(microtime(true) - $time, 1)];
    $page = getInstallReply([], 'GET', 'admin.php')['body'];
    $out['notice'] = [str_contains($page, 'data-sl-autohide'), str_contains($page, 'Node: jokes')];
    $rows = $pdo->query('SELECT name, title, ext, active, sort, version FROM '.IPREF.'_node_types ORDER BY sort')->fetchAll(PDO::FETCH_ASSOC);
    $out['types'] = array_map(fn($v) => [$v['name'], $v['title'], $v['ext'], (int)$v['active'], (int)$v['sort'], (int)$v['version']], $rows);
    $node = getInstallConf('node')['node'] ?? [];
    $out['node'] = array_map(fn($v) => $v['version'] ?? null, $node['types'] ?? []);
    $up = getInstallConf('uploads')['uploads'] ?? [];
    $rate = getInstallConf('ratings')['ratings'] ?? [];
    $out['areas'] = [
        'uploads' => array_values(array_filter(IPROFS, fn($v) => ($up[$v] ?? null) === ($up['all'] ?? false))),
        'ratings' => array_values(array_filter(IPROFS, fn($v) => ($rate['node.'.$v] ?? null) === ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'])),
        'fields' => array_keys(getInstallConf('fields')['fields']['node'] ?? []),
    ];
    $out['ghost'] = [$ghost, isset($node['types']['stale']), isset(getInstallConf('fields')['fields']['node']['stale']), isset($up['stale']), isset($rate['node.stale'])];
    $out['dirs'] = array_values(array_filter(IPROFS, fn($v) => is_file($isite.'/uploads/'.$v.'/.htaccess') && is_file($isite.'/uploads/'.$v.'/index.html')));
    $out['mark'] = array_key_exists('node', getInstallConf('update')['update'] ?? []);
    $row = $pdo->query('SELECT t.name, n.cid, n.uid, n.aname, n.title, n.status, n.home, n.comon FROM '.IPREF.'_nodes AS n INNER JOIN '.IPREF.'_node_types AS t ON t.id = n.tid')
        ->fetchAll(PDO::FETCH_ASSOC);
    $out['starter'] = $row;
    $again = getInstallReply([], 'POST', 'admin.php', ['op' => 'add_admin', 'aname' => 'Other', 'aemail' => 'x@probe.test', 'apwd' => IPASS, 'apwd2' => IPASS], [$cook]);
    $out['again'] = [$again['code'], (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_admins')->fetchColumn(),
        (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_node_types')->fetchColumn()];
    return $out;
}

# The identities of the checks: the first administrator with its session value, the site account the panel created with it and a second site account
function getInstallWho(PDO $pdo): array {
    $adm = $pdo->query('SELECT id, name, password FROM '.IPREF.'_admins ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $usr = $pdo->query('SELECT id, name, password FROM '.IPREF.'_users WHERE name = \'Probe\'')->fetch(PDO::FETCH_ASSOC);
    $pdo->exec('INSERT INTO '.IPREF.'_users (name, email, password, block, warnings, field, grp, points, ip) VALUES (\'Stranger\', \'stranger@probe.test\','
        .' \'hash-stranger\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\')');
    $sid = (int)$pdo->lastInsertId();
    return [
        'guest' => [],
        'admin' => ['admin' => base64_encode($adm['id'].':'.$adm['name'].':'.$adm['password'])],
        'user' => is_array($usr) ? ['user' => base64_encode($usr['id'].':'.$usr['name'].':'.$usr['password'])] : [],
        'other' => ['user' => base64_encode($sid.':Stranger:hash-stranger')],
    ];
}

# Every created type exports exactly what its profile ships: the same name, title, extension, sort, settings and fields, with the rules of the site in place of the empty ones
function getInstallExports(array $who): array {
    $out = [];
    foreach (IPROFS as $name) {
        $page = getInstallReply($who['admin'], 'GET', 'admin.php?name=node&op=export&type='.$name);
        $data = json_decode($page['body'], true);
        $prof = getInstallProfile($name);
        $same = is_array($data);
        foreach (['name', 'title', 'intro', 'ext', 'sort', 'settings', 'fields'] as $key) $same = $same && $data['type'][$key] === $prof['type'][$key];
        $out[$name] = [$page['code'], $same, is_array($data) && count($data['type']['uploads'] ?? []) === 12, is_array($data) && count($data['type']['rating'] ?? []) === 4];
    }
    return $out;
}

# The fields the type form of the panel posts for the settings of a profile, the way a browser sends the form the profile fills
function getInstallTypeForm(array $prof, string $name, string $tok): array {
    $set = $prof['type']['settings'];
    $flow = $set['workflow'];
    $form = ['name' => 'node', 'op' => 'type', 'profile' => $prof['type']['name'], 'token' => $tok, 'tname' => $name, 'title' => $prof['type']['title'],
        'intro' => $prof['type']['intro'], 'ext' => $prof['type']['ext'], 'sort' => (string)$prof['type']['sort'], 'orders' => $set['list']['orders'],
        'order' => $set['list']['order'], 'dir' => $set['list']['dir'], 'limit' => (string)$set['list']['limit'], 'show' => $set['list']['show'],
        'mode' => $set['view']['mode'], 'access' => [['all' => '0|0', 'user' => '1|0'][$flow['access']]], 'seo' => $set['integrations']['seo']];
    if ($set['list']['alpha']) $form['alpha'] = '1';
    foreach ($flow['notify'] as $key => $on) if ($on) $form['notify_'.$key] = '1';
    foreach ($set['features'] as $key => $on) if ($on) $form['feat_'.$key] = '1';
    foreach (['search', 'rss', 'sitemap', 'blocks'] as $key) if ($set['integrations'][$key]) $form['integ_'.$key] = '1';
    foreach (array_values(array_keys($set['assets'])) as $i => $role) {
        $def = $set['assets'][$role];
        $form['role'][$i] = ['name' => $role, 'title' => $def['title'], 'intro' => $def['intro'], 'mode' => $def['mode'], 'kinds' => $def['kinds'],
            'extensions' => implode(',', $def['extensions']), 'maxbytes' => '', 'min' => (string)$def['min'], 'max' => (string)$def['max'], 'sort' => (string)$def['sort']]
            + array_filter(['canlink' => $def['canlink'] ? '1' : '', 'report' => $def['report'] ? '1' : '', 'active' => $def['active'] ? '1' : '']);
    }
    return $form;
}

# The constructor of the panel offers every shipped profile, and a type made from a profile under a new name keeps the roles, fields and extension settings of the profile
function getInstallBuilder(PDO $pdo, array $who): array {
    $page = getInstallReply($who['admin'], 'GET', 'admin.php?name=node&op=type');
    preg_match_all('#op=type&amp;profile=([a-z]+)"#', $page['body'], $hit);
    $out = ['offer' => [$page['code'], $hit[1]]];
    foreach (['help' => 'desk', 'files' => 'soft'] as $prof => $name) {
        $form = getInstallReply($who['admin'], 'GET', 'admin.php?name=node&op=type&profile='.$prof);
        $done = getInstallReply($who['admin'], 'POST', 'admin.php', getInstallTypeForm(getInstallProfile($prof), $name, getInstallToken($form['body'], 'type')));
        $row = $pdo->query('SELECT ext, active FROM '.IPREF.'_node_types WHERE name = \''.$name.'\'')->fetch(PDO::FETCH_ASSOC) ?: [];
        $node = getInstallConf('node')['node']['types'][$name] ?? [];
        $out[$name] = [$form['code'], $done['code'], $row['ext'] ?? null, (int)($row['active'] ?? -1), $node['ext'] ?? null, array_keys($node['assets'] ?? []),
            array_keys(getInstallConf('fields')['fields']['node'][$name] ?? [])];
    }
    return $out;
}

# The administrative write of one material through the panel form of its type; the answer is the status, the id the redirect names and the text of a refusal
function addInstallNode(array $who, string $type, array $form): array {
    $page = getInstallReply($who, 'GET', 'admin.php?name=node&op=add&type='.$type);
    $tok = getInstallToken($page['body'], 'add');
    $done = getInstallReply($who, 'POST', 'admin.php', ['name' => 'node', 'op' => 'add', 'type' => $type, 'token' => $tok] + $form);
    $id = preg_match('#[?&]id=(\d+)#', $done['head']['location'] ?? '', $hit) ? (int)$hit[1] : 0;
    $note = preg_match('#<div[^>]*sl-alert[^>]*>(.*?)</div>#s', $done['body'], $hit) ? trim(strip_tags($hit[1])) : '';
    return [$page['code'], $done['code'], $id, $note];
}

# Each type but help: the public list, a published material written through the panel with what its roles and fields demand, the stored row, its public page,
# and a draft that the guest does not reach; content stores its source instead of a body
function getInstallTypes(PDO $pdo, array $who): array {
    $more = [
        'content' => ['source' => 'http://127.0.0.1/feed.xml', 'refresh' => '300'],
        'links' => ['asset' => [['role' => 'link', 'id' => '', 'title' => 'Probe site']], 'aurl0' => 'https://example.com/probe-links'],
        'files' => ['asset' => [['role' => 'download', 'id' => '', 'title' => 'Probe archive']], 'aurl0' => 'https://example.com/probe.zip',
            'field' => ['release' => '1.0.2', 'site' => 'https://example.com/']],
        'media' => ['asset' => [['role' => 'source', 'id' => '', 'title' => 'Probe clip']], 'aurl0' => 'https://example.com/probe.mp4',
            'field' => ['year' => '2024', 'director' => 'Probe Director']],
    ];
    $out = [];
    foreach (IPROFS as $name) {
        if ($name === 'help') continue;
        $list = getInstallReply($who['guest'], 'GET', 'index.php?name='.$name);
        $base = ['title' => 'Probe '.$name, 'intro' => 'Intro of '.$name, 'body' => 'Body of '.$name, 'comon' => '2'] + ($more[$name] ?? []);
        [$form, $code, $id, $note] = addInstallNode($who['admin'], $name, $base + ['status' => '2']);
        $row = $pdo->query('SELECT n.status, n.title, n.field, n.body, t.name FROM '.IPREF.'_nodes AS n INNER JOIN '.IPREF.'_node_types AS t ON t.id = n.tid WHERE n.id = '.$id)
            ->fetch(PDO::FETCH_ASSOC) ?: [];
        $assets = $pdo->query('SELECT role, kind, src FROM '.IPREF.'_node_assets WHERE nid = '.$id)->fetchAll(PDO::FETCH_NUM);
        $view = getInstallReply($who['guest'], 'GET', 'index.php?name='.$name.'&op=view&id='.$id);
        $again = getInstallReply($who['guest'], 'GET', 'index.php?name='.$name);
        $alt = isset($base['aurl0']) ? ['aurl0' => $base['aurl0'].'-draft'] : [];
        [, $dcode, $draft, $dnote] = addInstallNode($who['admin'], $name, array_replace($base, ['title' => 'Draft '.$name, 'status' => '0'] + $alt));
        $out[$name] = [
            'list' => [$list['code'], $again['code'], str_contains($again['body'], 'Probe '.$name)],
            'write' => [$form, $code, $id > 0, (int)($row['status'] ?? -1), $row['name'] ?? '', (string)($row['field'] ?? ''), $assets, $note],
            'view' => [$view['code'], str_contains($view['body'], 'Probe '.$name)],
            'draft' => [$dcode, $dnote, getInstallReply($who['guest'], 'GET', 'index.php?name='.$name.'&op=view&id='.$draft)['code'],
                getInstallReply($who['admin'], 'GET', 'admin.php?name=node&op=edit&id='.$draft.'&type='.$name)['code']],
            'body' => ($name === 'content') ? (string)($row['body'] ?? '') : '',
            'id' => $id,
        ];
    }
    return $out;
}

# The private support of help: the guest is refused, the site account opens a request through the public form and reads it, the stranger does not,
# and the queue of the panel carries it
function getInstallSupport(PDO $pdo, array $who): array {
    $form = getInstallReply($who['user'], 'GET', 'index.php?name=help&op=add');
    $tok = getInstallToken($form['body'], 'name="action"');
    $done = getInstallReply($who['user'], 'POST', 'index.php?name=help&op=add', ['title' => 'Probe request', 'intro' => 'Intro of the request', 'body' => 'Body of the request',
        'action' => 'submit', 'token' => $tok]);
    $row = $pdo->query('SELECT n.id, n.status, n.uid, n.comon, s.state FROM '.IPREF.'_nodes AS n LEFT JOIN '.IPREF.'_node_support AS s ON s.nid = n.id'
        .' WHERE n.title = \'Probe request\'')->fetch(PDO::FETCH_ASSOC) ?: [];
    $id = (int)($row['id'] ?? 0);
    $path = 'index.php?name=help&op=view&id='.$id;
    $queue = getInstallReply($who['admin'], 'GET', 'admin.php?name=node&type=help');
    return [
        'list' => [getInstallReply($who['guest'], 'GET', 'index.php?name=help')['code'], getInstallReply($who['user'], 'GET', 'index.php?name=help')['code']],
        'write' => [$form['code'], $tok !== '', $done['code'], array_map('intval', array_diff_key($row, ['state' => 0])), $row['state'] ?? null],
        'read' => array_map(fn($v) => getInstallReply($who[$v], 'GET', $path)['code'], ['user', 'other', 'guest']),
        'queue' => [$queue['code'], str_contains($queue['body'], 'Probe request')],
    ];
}

# The canonical address a page names
function getInstallCanon(string $html): string {
    return preg_match('#<link[^>]+rel="canonical"[^>]+href="([^"]+)"#', $html, $hit) ? html_entity_decode($hit[1]) : '';
}

# The showcase of news for the guest: a category made on the category screen of the panel, eleven materials in it written through the panel form,
# the second page of the list, the category list, the page past the end, and the canonical address of the list, the category and a material
function getInstallShowcase(PDO $pdo, array $who): array {
    $page = getInstallReply($who['admin'], 'GET', 'admin.php?name=categories&op=add&modul=news');
    $made = getInstallReply($who['admin'], 'POST', 'admin.php', ['name' => 'categories', 'op' => 'addsave', 'modul' => 'news', 'title' => 'Probe category',
        'description' => 'Category of the probe', 'status' => '1', 'token' => getInstallToken($page['body'], 'addsave')]);
    $cid = (int)$pdo->query('SELECT id FROM '.IPREF.'_categories WHERE modul = \'news\' AND title = \'Probe category\'')->fetchColumn();
    $ids = [];
    for ($i = 1; $i <= 11; $i++) {
        $ids[] = addInstallNode($who['admin'], 'news', ['title' => 'Showcase '.$i, 'intro' => 'Intro '.$i, 'body' => 'Body '.$i, 'comon' => '2', 'cid' => (string)$cid,
            'status' => '2'])[2];
    }
    $one = getInstallReply($who['guest'], 'GET', 'index.php?name=news');
    $two = getInstallReply($who['guest'], 'GET', 'index.php?name=news&num=2');
    $cat = getInstallReply($who['guest'], 'GET', 'index.php?name=news&cat='.$cid);
    $item = getInstallReply($who['guest'], 'GET', 'index.php?name=news&op=view&id='.$ids[0]);
    return [
        'made' => [$made['code'], $cid > 0, count(array_filter($ids))],
        'pages' => [$one['code'], str_contains($one['body'], 'num=2'), $two['code'], getInstallReply($who['guest'], 'GET', 'index.php?name=news&num=9')['code']],
        'cat' => [$cat['code'], str_contains($cat['body'], 'Probe category'), preg_match_all('#<article id="node-\d+"#', $cat['body'])],
        'canon' => [getInstallCanon($one['body']), getInstallCanon($two['body']), getInstallCanon($cat['body']), getInstallCanon($item['body'])],
        'ids' => [$cid, $ids[0]],
    ];
}

# The external material of content: the stored source, the manual check through the panel refused by the transport for a local address with the failure counted,
# and the scheduled job of the panel picking the due source up and counting the next failure
function getInstallSync(PDO $pdo, array $who, int $id): array {
    $src = fn(): array => array_map('strval', $pdo->query('SELECT url, refresh, fails, error FROM '.IPREF.'_node_sync WHERE nid = '.$id)->fetch(PDO::FETCH_ASSOC) ?: []);
    $out = ['stored' => $src()];
    $page = getInstallReply($who['admin'], 'GET', 'admin.php?name=node&op=edit&id='.$id.'&type=content');
    $tok = getInstallToken($page['body'], 'sync');
    $hand = getInstallReply($who['admin'], 'POST', 'admin.php', ['name' => 'node', 'op' => 'sync', 'id' => (string)$id, 'type' => 'content', 'token' => $tok]);
    $out['manual'] = [$page['code'], $tok !== '', $hand['code'], $src()];
    $pdo->exec('UPDATE '.IPREF.'_node_sync SET due = \'2000-01-01 00:00:00\' WHERE nid = '.$id);
    $sched = getInstallReply($who['admin'], 'GET', 'admin.php?name=scheduler');
    $run = getInstallReply($who['admin'], 'POST', 'admin.php', ['name' => 'scheduler', 'op' => 'run', 'job' => 'nodesync', 'token' => getInstallToken($sched['body'], 'run')]);
    $out['planned'] = [$sched['code'], $run['code'], $src()];
    return $out;
}

# Put the configuration of a 6.2 site over the copy: every configuration source the given revision tracked, as it was committed there,
# while the sources that revision did not have stay as the release ships them, the way an upgraded site keeps its own config/ beside the new files
# A directory in place of the revision is the config/ of a real 6.2 site: its config_<name>.php sources go beside the release, and db.php is written
# the way 6.2 wrote it, as the variable $confdb, with the disposable database in it
function setInstallOld(string $site, string $rev, string $base = ''): int {
    if (is_dir($rev)) {
        $num = 0;
        foreach (glob(rtrim($rev, '/').'/config_*.php') ?: [] as $one) $num += (int)copy($one, $site.'/config/'.basename($one));
        $dbc = ['host' => getInstallCred()['host'], 'uname' => getInstallCred()['uname'], 'pass' => getInstallCred()['pass'], 'name' => $base, 'prefix' => IPREF,
            'engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci', 'mode' => '1', 'sync' => '1', 'type' => 'mysqli'];
        file_put_contents($site.'/config/db.php', "<?php\nif (!defined('FUNC_FILE')) die('Illegal file access');\n\n\$confdb = ".var_export($dbc, true).";\n\n?>");
        return $num;
    }
    $quiet = ' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
    $list = explode("\n", trim((string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' ls-tree -r --name-only '.escapeshellarg($rev).' config/'.$quiet)));
    $num = 0;
    foreach ($list as $one) {
        if (!str_ends_with($one, '.php')) continue;
        file_put_contents($site.'/'.$one, (string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' show '.escapeshellarg($rev.':'.$one).$quiet));
        $num++;
    }
    return $num;
}

# Load a dump of a real site into the disposable database with the command line client of the configured server; the answer is the exit code and the error output
function addInstallDump(string $base, string $dump): array {
    $dbc = getInstallCred();
    $dir = rtrim(str_replace('\\', '/', (string)getInstallPdo()->query('SELECT @@basedir')->fetchColumn()), '/').'/bin/';
    $bin = '';
    foreach (['mariadb.exe', 'mysql.exe', 'mariadb', 'mysql'] as $one) if ($bin === '' && is_file($dir.$one)) $bin = $dir.$one;
    if ($bin === '') throw new RuntimeException('No command line client next to the database server');
    $cmd = [$bin, '--host='.$dbc['host'], '--user='.$dbc['uname'], '--default-character-set=utf8mb4', $base];
    if ($dbc['pass'] !== '') array_splice($cmd, 3, 0, ['--password='.$dbc['pass']]);
    $proc = proc_open($cmd, [0 => ['file', $dump, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]);
    $err = trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), $err];
}

# The table names the installer creates, without the prefix, in the order of setup/sql/table.sql
function getInstallTables(): array {
    preg_match_all('/CREATE TABLE `\{prefix\}_([a-z0-9_]+)`/', (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql'), $hit);
    return $hit[1];
}

# Run the shipped schema into an empty database under a prefix, statement by statement; the answer is the number of tables it holds afterwards
function addInstallFresh(PDO $pdo, string $pref): int {
    $text = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$pref, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'],
        (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql'));
    foreach (preg_split('/;\s*\n/', $text) ?: [] as $sql) if (trim($sql) !== '' && !str_starts_with(trim($sql), '--')) $pdo->exec($sql);
    return count($pdo->query('SHOW TABLES')->fetchAll());
}

# The structure of the installer tables in one database: engine and collation, columns by name, indexes with their columns, foreign keys and checks
function getInstallShape(PDO $pdo, string $base, string $pref): array {
    $out = [];
    $args = ['db' => $base];
    $tabs = array_map(fn($v) => $pref.'_'.$v, getInstallTables());
    $keep = fn(string $name): bool => in_array($name, $tabs, true);
    $query = function (string $sql) use ($pdo, $args): array {
        $stm = $pdo->prepare($sql);
        $stm->execute($args);
        return $stm->fetchAll(PDO::FETCH_NUM);
    };
    foreach ($query('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db') as [$tab, $eng, $col]) {
        if ($keep($tab)) $out[$tab]['table'] = $eng.' '.$col;
    }
    $sql = 'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS'
        .' WHERE TABLE_SCHEMA = :db';
    foreach ($query($sql) as $row) if ($keep($row[0])) $out[$row[0]]['column '.$row[1]] = implode(' ', array_map(fn($v) => $v ?? 'NULL', array_slice($row, 2)));
    $sql = 'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db'
        .' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX';
    foreach ($query($sql) as [$tab, $key, $uni, $col, $sub]) {
        if ($keep($tab)) $out[$tab]['index '.$key] = ($out[$tab]['index '.$key] ?? ($uni ? 'key' : 'unique')).' '.$col.($sub ? '('.$sub.')' : '');
    }
    $sql = 'SELECT r.TABLE_NAME, r.CONSTRAINT_NAME, r.REFERENCED_TABLE_NAME, r.UPDATE_RULE, r.DELETE_RULE, GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION)'
        .' FROM information_schema.REFERENTIAL_CONSTRAINTS AS r INNER JOIN information_schema.KEY_COLUMN_USAGE AS k ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA'
        .' AND k.TABLE_NAME = r.TABLE_NAME AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME WHERE r.CONSTRAINT_SCHEMA = :db GROUP BY 1, 2, 3, 4, 5';
    foreach ($query($sql) as $row) if ($keep($row[0])) $out[$row[0]]['foreign '.$row[1]] = implode(' ', array_slice($row, 2));
    $sql = 'SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :db';
    foreach ($query($sql) as [$tab, $key, $text]) if ($keep($tab)) $out[$tab]['check '.$key] = $text;
    ksort($out);
    return $out;
}

# Every difference between the structure of a clean installation and of an upgraded site, one line each, the missing side named
function getInstallDiffs(array $fresh, array $done): array {
    $out = [];
    foreach (array_unique(array_merge(array_keys($fresh), array_keys($done))) as $tab) {
        $one = $fresh[$tab] ?? [];
        $two = $done[$tab] ?? [];
        foreach (array_unique(array_merge(array_keys($one), array_keys($two))) as $key) {
            if (($one[$key] ?? null) !== ($two[$key] ?? null)) $out[] = $tab.' '.$key.': '.($one[$key] ?? 'missing').' | '.($two[$key] ?? 'missing');
        }
    }
    return $out;
}

# What one run of the update left: the rows the installer reported as failed, the closed site, the marks, the reconciled registry, the converted configuration
# sources, the manifests of the three units, the balances and the Node tables, and whether config/local.php is gone
function getInstallAfter(PDO $pdo, array $page): array {
    global $isite;
    preg_match_all('#<tr><td>[^<]*</td><td>([^<]*)(?:</td>)?<td><span class="sl_red">#', $page['body'], $bad);
    preg_match('#records of removed modules dropped: ([a-z_, ]+)#', $page['body'], $gone);
    $mods = getInstallConf('modules')['modules'] ?? [];
    $rss = getInstallConf('rss')['rss'] ?? [];
    $jobs = getInstallConf('scheduler')['scheduler']['jobs'] ?? [];
    $fields = getInstallConf('fields')['fields'] ?? [];
    $ship = eval('?>'.shell_exec('git -C '.escapeshellarg(BASE_DIR).' show HEAD:config/scheduler.php'));
    $olds = array_map(fn($v) => IPREF.'_'.$v, array_diff(IPROFS, ['docs']));
    $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (\''.implode('\', \'', $olds).'\')';
    $top = 0;
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $one) $top = max($top, (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM `'.$one.'`')->fetchColumn());
    $next = (int)($pdo->query('SHOW TABLE STATUS LIKE \''.IPREF.'_nodes\'')->fetch(PDO::FETCH_ASSOC)['Auto_increment'] ?? 0);
    $mani = [];
    foreach (['points', 'ratings', 'fields'] as $one) {
        $file = $isite.'/storage/backup/update/'.$one.'/manifest.json';
        $mani[$one] = is_file($file) ? (json_decode((string)file_get_contents($file), true)['state'] ?? '') : 'missing';
    }
    return [
        'code' => $page['code'],
        'failed' => array_map('trim', $bad[1]),
        'green' => substr_count($page['body'], 'sl_green'),
        'close' => getInstallConf('global')['close'] ?? null,
        'marks' => getInstallConf('update')['update'] ?? [],
        'modules' => ['node' => $mods['node'] ?? null, 'old' => array_values(array_intersect(IPROFS, array_keys($mods))), 'gone' => $gone[1] ?? '',
            'img' => count(array_filter($mods, fn($v) => isset($v['img'])))],
        'ratings' => getInstallConf('ratings')['ratings'] ?? [],
        'uploads' => array_values(array_intersect(IPROFS, array_keys(getInstallConf('uploads')['uploads'] ?? []))),
        'fields' => array_map(fn($v) => is_array($v) ? count($v) : gettype($v), $fields),
        'rss' => [isset($rss['temp']), $rss['bytes'] ?? null, $rss['redirects'] ?? null, $rss['timeout'] ?? null],
        'jobs' => array_map(fn($v) => isset($jobs[$v]) ? [$jobs[$v]['active'], $jobs[$v]['schedule'], $jobs[$v]['priority'], $jobs[$v]['settings']] : null,
            ['nodepublish' => 'nodepublish', 'nodesync' => 'nodesync', 'maildrain' => 'maildrain']),
        'manifest' => $mani,
        'users' => array_map('intval', $pdo->query('SELECT COUNT(*), COALESCE(SUM(points), 0) FROM '.IPREF.'_users')->fetch(PDO::FETCH_NUM)),
        'journal' => (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_points')->fetchColumn(),
        'values' => [(int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_users WHERE field LIKE \'{%\'')->fetchColumn(),
            (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_users WHERE field != \'\' AND field NOT LIKE \'{%\'')->fetchColumn()],
        'rating' => array_map(fn($v) => (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_rating_'.$v)->fetchColumn(), ['targets' => 'targets', 'votes' => 'votes']),
        'node' => (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_node_types')->fetchColumn(),
        'local' => is_file($isite.'/config/local.php'),
        'old' => count(glob($isite.'/config/config_*.php') ?: []),
        'snaps' => array_values(array_filter(array_map(fn($v) => substr($v, strlen($isite.'/storage/backup/update/')), glob($isite.'/storage/backup/update/*/*') ?: []),
            fn($v) => !str_ends_with($v, '/manifest.json'))),
        'letter' => is_file($isite.'/storage/backup/update/newsletter/manifest.json')
            ? (json_decode((string)file_get_contents($isite.'/storage/backup/update/newsletter/manifest.json'), true)['state'] ?? '') : 'missing',
        'queued' => (int)$pdo->query('SELECT COUNT(*) FROM '.IPREF.'_mail WHERE kind = \'newsletter\'')->fetchColumn(),
        'abort' => getInstallConf('newsletter')['newsletter']['abort'] ?? null,
        'global' => array_intersect_key(getInstallConf('global'), array_flip(['language', 'version', 'module', 'amod', 'sitename', 'css_f', 'sep'])),
        'presentation' => isset($mods['presentation']),
        'maildrain' => ($jobs['maildrain'] ?? null) === ($ship['scheduler']['jobs']['maildrain'] ?? false),
        'ids' => ['old' => $top, 'next' => $next],
    ];
}

# The files of config/ with their hashes, the panel entry and nothing else, to prove a refused run left the site as it was
function getInstallFiles(): array {
    global $isite;
    $out = ['admin.php' => is_file($isite.'/admin.php')];
    foreach (glob($isite.'/config/*') ?: [] as $one) $out[basename($one)] = sha1_file($one);
    return $out;
}

# The field definitions and the value sources a site recorded in the snapshot of its own field update: the definitions go over config/fields.php of the copy
# while the areas they do not name stay as the revision had them
function setInstallDefs(string $snap): bool {
    global $isite;
    $defs = json_decode((string)file_get_contents($snap.'/definitions.json'), true)['source'] ?? null;
    if (!is_array($defs)) return false;
    $old = $isite.'/config/config_fields.php';
    if (is_file($old)) {
        if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
        $all = $defs + (array)(static function (string $path): array {
            include $path;
            return $conffi ?? [];
        })($old);
        return (bool)file_put_contents($old, "<?php\nif (!defined('FUNC_FILE')) die('Illegal file access');\n\n\$conffi = ".var_export($all, true).";\n\n?>");
    }
    $all = $defs + (getInstallConf('fields')['fields'] ?? []);
    ksort($all);
    return (bool)file_put_contents($isite.'/config/fields.php', "<?php\nreturn ".var_export(['fields' => $all], true).";\n");
}

# Put back the value sources the site corrected by hand after its preflight refused them, as its snapshot recorded them; the answer is the rows changed per area
function setInstallSources(PDO $pdo, string $snap): array {
    $out = [];
    foreach (['account' => ['users', 'field'], 'forum' => ['forum', 'field'], 'order' => ['order', 'info']] as $area => [$tab, $col]) {
        $stm = $pdo->prepare('UPDATE '.IPREF.'_'.$tab.' SET '.$col.' = :src WHERE id = :id AND '.$col.' != :old');
        $out[$area] = 0;
        foreach ((array)json_decode((string)file_get_contents($snap.'/'.$area.'.json'), true) as [$id, $src]) {
            $stm->execute(['src' => $src, 'id' => $id, 'old' => $src]);
            $out[$area] += $stm->rowCount();
        }
    }
    return $out;
}

# The standard update of a real 6.2 site: its dump in a disposable database under its own prefix, its configuration from the revision given, the release code around it
# and, from the snapshot of its own field update, the definitions it never committed; the installer is asked over HTTP until the preflight refuses,
# the sources the site corrected are put back, then asked to finish and asked once more for the repeat; the structure is compared with a clean installation
# of the same schema, and the panel and the public side of Node are walked afterwards
function getInstallUpgrade(PDO $pdo, string $base, string $dump, string $rev, string $snap): array {
    global $iguard, $isite;
    $out = ['config' => setInstallOld($isite, $rev, $base), 'dump' => addInstallDump($base, $dump)];
    if ($snap !== '') $out['fields'] = setInstallDefs($snap);
    $out['before'] = array_map('intval', $pdo->query('SELECT COUNT(*), COALESCE(SUM(points), 0) FROM '.IPREF.'_users')->fetch(PDO::FETCH_NUM));
    $dbc = getInstallCred();
    $glob = getInstallConf('global');
    $form = ['op' => 'save', 'setup' => 'update6_3', 'xhost' => $dbc['host'], 'xuname' => $dbc['uname'], 'xpass' => $dbc['pass'], 'xname' => $base, 'xprefix' => IPREF,
        'xsync' => '1', 'xafile' => 'admin'];
    $head = ['Host: 127.0.0.1:'.$iguard, 'Cookie: '.$glob['user_c'].'-lang=ru'];
    if (!$pdo->query('SHOW COLUMNS FROM '.IPREF.'_newsletter LIKE \'mails\'')->fetch()) $pdo->exec('ALTER TABLE '.IPREF.'_newsletter ADD `mails` TEXT');
    $pdo->exec('UPDATE '.IPREF.'_newsletter SET mails = \'one@probe.test, two@probe.test,one@probe.test\' ORDER BY id LIMIT 2');
    $news = getInstallConf('newsletter');
    $news['newsletter']['abort'] = '7';
    file_put_contents($isite.'/config/newsletter.php', "<?php\nreturn ".var_export($news, true).";\n");
    $was = getInstallFiles();
    if (is_file($isite.'/config/db.php')) {
        $page = getInstallReply([], 'POST', 'setup.php', $form, $head);
        $out['lock'] = [str_contains($page['body'], 'config/setup.unlock'), getInstallFiles() === $was];
    }
    touch($isite.'/config/setup.unlock');
    $was = getInstallFiles();
    $pdo->exec('ALTER TABLE '.IPREF.'_voting ENGINE=MyISAM');
    $page = getInstallReply([], 'POST', 'setup.php', $form, $head);
    $pdo->exec('ALTER TABLE '.IPREF.'_voting ENGINE=InnoDB');
    $out['refuse'] = [str_contains($page['body'], 'ALTER TABLE `'.IPREF.'_voting` ENGINE=InnoDB;'), getInstallFiles() === $was];
    $out['guest'] = [getInstallReply([], 'GET', '/')['code'], is_file($isite.'/config/local.php'), (getInstallConf('global')['close'] ?? null)];
    $ddl = $isite.'/setup/sql/table_update6_3.sql';
    $keep = (string)file_get_contents($ddl);
    file_put_contents($ddl, "ALTER TABLE `{prefix}_probe_missing` ADD `x` INT;\n".$keep);
    $out['broken'] = getInstallAfter($pdo, getInstallReply([], 'POST', 'setup.php', $form, $head));
    $out['broken']['guest'] = getInstallReply([], 'GET', '/')['code'];
    file_put_contents($ddl, $keep);
    $time = microtime(true);
    $out['first'] = getInstallAfter($pdo, getInstallReply([], 'POST', 'setup.php', $form, $head)) + ['time' => round(microtime(true) - $time, 1)];
    if ($snap !== '') $out['sources'] = setInstallSources($pdo, $snap);
    touch($isite.'/config/setup.unlock');
    $time = microtime(true);
    $out['second'] = getInstallAfter($pdo, getInstallReply([], 'POST', 'setup.php', $form, $head)) + ['time' => round(microtime(true) - $time, 1)];
    $mani = fn(): array => array_map('sha1_file', glob($isite.'/storage/backup/update/*/manifest.json') ?: []);
    $was = $mani();
    touch($isite.'/config/setup.unlock');
    $out['third'] = getInstallAfter($pdo, getInstallReply([], 'POST', 'setup.php', $form, $head));
    $out['third']['same'] = $mani() === $was;
    $out['unlock'] = is_file($isite.'/config/setup.unlock');
    $fresh = 'slaed_inst_'.bin2hex(random_bytes(4));
    $pdo->exec('CREATE DATABASE `'.$fresh.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $out['fresh'] = [count(getInstallTables()), addInstallFresh(getInstallPdo($fresh), IPREF)];
        $out['schema'] = getInstallDiffs(getInstallShape($pdo, $fresh, IPREF), getInstallShape($pdo, $base, IPREF));
    } finally {
        deleteInstallBase($fresh);
    }
    $adm = $pdo->query('SELECT id, name, password FROM '.IPREF.'_admins WHERE super = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $who = ['admin' => base64_encode($adm['id'].':'.$adm['name'].':'.$adm['password'])];
    $out['panel'] = [getInstallReply($who, 'GET', 'admin.php')['code'], getInstallReply($who, 'GET', 'admin.php?name=node')['code'],
        getInstallReply($who, 'GET', 'admin.php?name=modules')['code']];
    foreach (['news', 'docs'] as $name) {
        $form = getInstallReply($who, 'GET', 'admin.php?name=node&op=type&profile='.$name);
        $done = getInstallReply($who, 'POST', 'admin.php', getInstallTypeForm(getInstallProfile($name), $name, getInstallToken($form['body'], 'type')));
        $note = preg_match('#<div[^>]*sl-alert[^>]*>(.*?)</div>#s', $done['body'], $hit) ? trim(strip_tags($hit[1])) : '';
        $row = $pdo->query('SELECT active, version FROM '.IPREF.'_node_types WHERE name = \''.$name.'\'')->fetch(PDO::FETCH_NUM) ?: [];
        $out['type'][$name] = [$form['code'], $done['code'], $note, array_map('intval', $row)];
    }
    $made = $out['type']['docs'][3] ?? [];
    if (($made[0] ?? 1) === 0) {
        $list = getInstallReply($who, 'GET', 'admin.php?name=node&op=types');
        $tok = getInstallToken($list['body'], 'typestatus');
        $done = getInstallReply($who, 'POST', 'admin.php', ['name' => 'node', 'op' => 'typestatus', 'type' => 'docs', 'version' => (string)$made[1], 'active' => '1',
            'token' => $tok]);
        $out['status'] = [$list['code'], $tok !== '', $done['code'], (int)$pdo->query('SELECT active FROM '.IPREF.'_node_types WHERE name = \'docs\'')->fetchColumn()];
    }
    $out['public'] = ['guest' => getInstallReply([], 'GET', 'index.php?name=docs')['code'], 'admin' => getInstallReply($who, 'GET', 'index.php?name=docs')['code']];
    return $out;
}

$ibase = '';
$iprocs = [];
$ireport = ['error' => '', 'clean' => false, 'runs' => []];
try {
    if ($iupdate && (!is_file((string)($argv[3] ?? '')) || ($argv[4] ?? '') === '' || !preg_match('/^[a-z][a-z0-9]*$/D', IPREF))) {
        throw new InvalidArgumentException('update needs a dump file, a revision and the table prefix of the dump');
    }
    deleteInstallTree($iwork);
    mkdir($isite, 0777, true);
    $ireport['runs']['files'] = addInstallTree($isite);
    setInstallRouter($isite);
    $ibase = addInstallBase();
    $iport = getInstallPort();
    $iguard = getInstallPort();
    $iprocs[] = addInstallServer($isite, $iport);
    $iprocs[] = addInstallServer($isite, $iguard);
    $ipdo = getInstallPdo($ibase);
    if ($iupdate) $ireport['runs']['update'] = getInstallUpgrade($ipdo, $ibase, (string)($argv[3] ?? ''), (string)($argv[4] ?? ''), (string)($argv[6] ?? ''));
    else $ireport['runs']['setup'] = getInstallSetup($ipdo, $ibase);
    if (!$ifail && !$iupdate) {
        $iwho = getInstallWho($ipdo);
        $ireport['runs']['exports'] = getInstallExports($iwho);
        $ireport['runs']['types'] = getInstallTypes($ipdo, $iwho);
        $ireport['runs']['support'] = getInstallSupport($ipdo, $iwho);
        $ireport['runs']['sync'] = getInstallSync($ipdo, $iwho, (int)($ireport['runs']['types']['content']['id'] ?? 0));
        $ireport['runs']['builder'] = getInstallBuilder($ipdo, $iwho);
        $ireport['runs']['showcase'] = getInstallShowcase($ipdo, $iwho);
    }
    $ireport['runs']['logs'] = getInstallLogs();
    if ($ikeep) {
        file_put_contents($iwork.'/state.json', json_encode(['url' => 'http://127.0.0.1:'.$iport, 'who' => $iwho, 'base' => $ibase]));
        for ($i = 0; $i < 3600 && !is_file($iwork.'/stop'); $i++) sleep(1);
    }
} catch (Throwable $err) {
    $ireport['error'] = get_class($err).': '.$err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
} finally {
    foreach ($iprocs as $one) deleteInstallServer($one);
    $ireport['clean'] = ($ibase === '') || deleteInstallBase($ibase);
    if (!$ikeep) deleteInstallTree($iwork);
}
echo json_encode($ireport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
