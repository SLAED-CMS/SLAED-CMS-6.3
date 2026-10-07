<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the public and administrative routes of Node answered by the real index.php and admin.php over real HTTP
# It builds one disposable MariaDB database from the shipped table.sql, a scratch configuration that registers four types and a scratch upload root without guards
# It serves the tree with the built-in server and tests/Support/route_web.php as router; every exchange is a real request with its own cookies
# The report answers what each exchange returned and what the database, the cache and the files hold afterwards; nothing touches the site database or directories
# The second argument support runs the comments of Node and the private requests of the support type instead of the base routes
# Its child modes comments and ext boot the core on the same scratch configuration and database as one visitor and ask the comment subsystem and NodeSupport directly
# The argument sync runs the external materials with two types of the extension sync; its child mode syncext asks NodeSync with a scripted transport
# The argument integ runs the integrations - rating, favorites, poll, search, RSS, blocks and their settings - and its child integext the sitemap
# The argument guard runs the closed routes on the same integrations, and its child guardext asks the service for the right of polls
# The argument intact runs the integrity checks on the same integrations: point corrections, the block save and vote annulments
# The argument secure runs the public form on the same integrations: the upload right and limits, the write window, the captcha and the title in search
# The argument tree runs the document tree on docs switched to the tree, and its child treeext counts the statements of the tree read of one view
# The argument modes runs the display modes on five types; serve modes keeps a server with the same types up until the file stop appears
# The argument seo runs the canonical routes, the head and the feeds, and its child seoext asks the core for the feeds
# The argument head runs the routes, the notices and the theme header
# The argument quick runs the quick edit of a comment over its two routes: methods, the header token, the closed forms, the codes and what each leaves in the row
# The argument files runs the files of the forum on the file route, the writers of a post that bind a name and the unused filter of the upload browser
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
$rwork = str_replace('\\', '/', (string)($argv[1] ?? sys_get_temp_dir().'/slaed_node_route'));
if (in_array($argv[2] ?? '', ['view', 'comments', 'ext', 'syncext', 'integext', 'guardext', 'treeext', 'seoext'], true)) {
    $probework = $rwork.'/child';
    foreach (['CONFIG_DIR' => 'config', 'BACKUP_DIR' => 'backup', 'CACHE_DIR' => 'cache', 'UPLOADS_DIR' => 'uploads'] as $rkey => $rdir) define($rkey, $rwork.'/'.$rdir);
    require_once __DIR__.'/probe_boot.php';
    if (($argv[2] ?? '') === 'comments') setRouteChild((string)($argv[3] ?? ''));
    require_once BASE_DIR.'/core/system.php';
    $rchild = ['view' => 'getRouteViewData', 'comments' => 'getRouteCommentData', 'ext' => 'getRouteExtData', 'syncext' => 'getRouteSyncData',
        'integext' => 'getRouteIntegData', 'guardext' => 'getRouteGuardData', 'treeext' => 'getRouteTreeData',
        'seoext' => 'getRouteSeoData'][$argv[2]];
    echo json_encode($rchild());
    exit;
}
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', BASE_DIR.'/public');

# The table prefix of the disposable database
const RPREF = 'probe';

# Remove one scratch tree
function deleteRouteTree(string $dir): void {
    if (is_link($dir) || is_file($dir)) {
        unlink($dir);
        return;
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteRouteTree($dir.'/'.$one);
    rmdir($dir);
}

# Write one scratch configuration source in the shape getConfig() reads
function setRouteFile(string $file, array $data): void {
    file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n");
}

# One connection to the database server, with a database selected when one is named
function getRoutePdo(string $name = ''): PDO {
    $dbc = (require BASE_DIR.'/config/db.php')['db'];
    $dsn = 'mysql:host='.$dbc['host'].($name === '' ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, $dbc['uname'], $dbc['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Lift the installer splitter and the statement cleaner it calls out of core/admin.php
function addRouteSplitter(): void {
    $code = (string)file_get_contents(BASE_DIR.'/core/admin.php');
    foreach (['getSqlbatch', 'getSqlclean'] as $name) {
        if (function_exists($name)) continue;
        $from = strpos($code, 'function '.$name.'(');
        $to = ($from === false) ? false : strpos($code, "\n}\n", $from);
        if ($from === false || $to === false) throw new RuntimeException($name.'() is gone from core/admin.php');
        eval(substr($code, $from, $to - $from + 3));
    }
}

# Create the disposable database with the whole shipped schema; the site database can never be the one created
function addRouteBase(): array {
    $dbc = (require BASE_DIR.'/config/db.php')['db'];
    $name = 'slaed_route_'.bin2hex(random_bytes(4));
    if ($name === $dbc['name']) throw new RuntimeException('The disposable name collides with the site database');
    getRoutePdo()->exec('CREATE DATABASE `'.$name.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = getRoutePdo($name);
    addRouteSplitter();
    $sql = getSqlbatch((string)file_get_contents(BASE_DIR.'/storage/update/sql/table.sql'));
    if ($sql['error'] !== '') throw new RuntimeException('table.sql does not split');
    foreach ($sql['statements'] as $one) $pdo->exec(str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [RPREF, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $one));
    return [$pdo, $name];
}

# Drop the disposable database again
function deleteRouteBase(string $name): bool {
    if ($name === '' || !str_starts_with($name, 'slaed_route_')) return false;
    try {
        getRoutePdo()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
        return true;
    } catch (Throwable) {
        return false;
    }
}

# One stored role of the assets section
function getRouteRole(string $title, string $mode, array $kinds, int $max, bool $link, bool $report, int $sort): array {
    return ['title' => $title, 'kinds' => $kinds, 'max' => $max, 'canlink' => $link, 'report' => $report, 'mode' => $mode, 'sort' => $sort];
}

# The switches of the features section with the named ones on
function getRouteFeatures(array $on): array {
    $out = [];
    foreach (['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related',
        'tree'] as $key) $out[$key] = in_array($key, $on, true);
    return $out;
}

# The scratch configuration: the sources of the stand with the disposable database, the address of the probe server, the page cache on, news as the start page
# It registers three types: news with categories, public submission under review and two roles, docs without submission, and the disabled off
function addRouteConfig(string $work, string $base, int $port): void {
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) if (basename($file) !== 'local.php') copy($file, $work.'/config/'.basename($file));
    $data = require BASE_DIR.'/config/db.php';
    $data['db']['name'] = $base;
    $data['db']['prefix'] = RPREF;
    setRouteFile($work.'/config/db.php', $data);
    $data = require BASE_DIR.'/config/global.php';
    $data = array_replace($data, ['homeurl' => 'http://127.0.0.1:'.$port, 'cache' => '1', 'close' => '0', 'module' => 'news', 'multilingual' => '0', 'rewrite' => '0']);
    setRouteFile($work.'/config/global.php', $data);
    $news = [
        'version' => 1,
        'list' => ['orders' => ['published', 'title', 'views'], 'limit' => 2, 'alpha' => true],
        'features' => getRouteFeatures(['categories', 'comments', 'submit', 'moderation', 'related']),
        'assets' => ['cover' => getRouteRole('Cover', 'image', ['image'], 1, false, false, 0), 'files' => getRouteRole('Files', 'download', [], 3, true, true, 1)],
    ];
    $help = ['version' => 1, 'list' => ['limit' => 20, 'show' => ['category', 'date']], 'view' => ['mode' => 'support'],
        'features' => getRouteFeatures(['categories', 'comments', 'submit']), 'workflow' => ['notify' => ['pending' => false, 'result' => false]], 'ext' => ['mail' => true]];
    $data = require BASE_DIR.'/config/node.php';
    $data['node']['types'] = ['docs' => ['version' => 1, 'features' => getRouteFeatures([])], 'help' => $help, 'news' => $news, 'off' => ['version' => 1,
        'features' => getRouteFeatures([])]];
    $data['node']['limits']['send'] = 0;
    setRouteFile($work.'/config/node.php', $data);
    $data = require BASE_DIR.'/config/comments.php';
    $data['comments'] = array_replace($data['comments'], ['send' => '0', 'anonpost' => '1', 'edit' => '600']);
    setRouteFile($work.'/config/comments.php', $data);
    $data = require BASE_DIR.'/config/uploads.php';
    $rate = require BASE_DIR.'/config/ratings.php';
    foreach (['news', 'docs', 'off', 'help'] as $name) {
        $data['uploads'][$name] = $data['uploads']['all'];
        $rate['ratings']['node.'.$name] = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];
    }
    setRouteFile($work.'/config/uploads.php', $data);
    setRouteFile($work.'/config/ratings.php', $rate);
}

# The rows of the disposable database: a group, four accounts, five administrators, two categories of news and one of help, the four types, their materials and resources
# The account helper is a site account and the subscribed administrator of help at once; root and moder are subscribed as well, so a notice to a wrong reader shows up
function addRouteRows(PDO $pdo): void {
    $pre = RPREF.'_';
    $pdo->exec('INSERT INTO '.$pre.'groups (id, name, intro, points, extra) VALUES (1, \'club\', \'\', 0, 1)');
    $pdo->exec('INSERT INTO '.$pre.'users (id, name, email, password, block, warnings, field, grp, points, ip) VALUES'
        .' (2, \'anna\', \'anna@probe.test\', \'hash-anna\', \'\', \'\', \'\', 1, 0, \'127.0.0.1\'),'
        .' (3, \'boris\', \'boris@probe.test\', \'hash-boris\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\'),'
        .' (4, \'clara\', \'clara@probe.test\', \'hash-clara\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\'),'
        .' (5, \'helper\', \'helper@probe.test\', \'hash-helper\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'admins (id, name, email, password, super, smail, modules, ip) VALUES'
        .' (1, \'root\', \'root@probe.test\', \'hash-root\', 1, 1, \'\', \'127.0.0.1\'),'
        .' (2, \'moder\', \'moder@probe.test\', \'hash-moder\', 0, 1, \'node-news\', \'127.0.0.1\'),'
        .' (3, \'boss\', \'boss@probe.test\', \'hash-boss\', 0, 0, \'node\', \'127.0.0.1\'),'
        .' (4, \'docsman\', \'docsman@probe.test\', \'hash-docsman\', 0, 0, \'node-docs\', \'127.0.0.1\'),'
        .' (5, \'helper\', \'helper@probe.test\', \'hash-helper\', 0, 1, \'node-help,comments\', \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pview, pread, ppost, lang) VALUES (1, \'news\', \'Open\', \'\', \'0|0\', \'0|0\', \'1|0\', \'\'),'
        .' (2, \'news\', \'Members\', \'\', \'1|0\', \'1|0\', \'1|0\', \'\'), (3, \'help\', \'Desk\', \'\', \'1|0\', \'1|0\', \'1|0\', \'\')');
    $pdo->exec('INSERT INTO '.$pre.'node_types (id, name, title, intro, ext, active, sort, version) VALUES (1, \'news\', \'News\', \'\', \'\', 1, 10, 1),'
        .' (2, \'docs\', \'Docs\', \'\', \'\', 1, 20, 1), (3, \'off\', \'Off\', \'\', \'\', 0, 30, 1), (4, \'help\', \'Help\', \'\', \'support\', 1, 40, 1)');
    $rows = [
        101 => [1, 1, 2, 'Alpha', 2, '2026-01-01 10:00:00', 'Body with [attach=att-aaaaaaaaaa.png align=left title=att]'],
        102 => [1, 0, 2, 'Beta', 2, '2026-01-02 10:00:00', 'Body of beta'],
        103 => [1, 2, 3, 'Members', 2, '2026-01-03 10:00:00', 'Body of members'],
        104 => [1, 0, 2, 'Pending one', 1, null, 'Body of pending'],
        105 => [1, 0, 3, 'Gamma', 2, '2026-01-05 10:00:00', 'Body of gamma'],
        201 => [2, 0, 3, 'Doc one', 2, '2026-01-06 10:00:00', 'Body of doc'],
        301 => [3, 0, 3, 'Off one', 2, '2026-01-07 10:00:00', 'Body of off'],
    ];
    $st = $pdo->prepare('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (?, ?, ?, ?, \'\', \'127.0.0.1\', ?, ?, ?, \'\', ?, ?)');
    foreach ($rows as $id => [$tid, $cid, $uid, $title, $state, $pub, $body]) $st->execute([$id, $tid, $cid, $uid, $title, 'intro of '.$id, $body, $state, $pub]);
    $pdo->exec('INSERT INTO '.$pre.'node_assets (id, nid, kind, role, src, name, intro, mime, size, width, height, hits, sort) VALUES'
        .' (1, 101, \'image\', \'cover\', \'cover-cccccccccc.png\', \'cover.png\', \'\', \'image/png\', 70, 1, 1, 0, 0),'
        .' (2, 101, \'file\', \'files\', \'manual-dddddddddd.pdf\', \'manual.pdf\', \'\', \'application/pdf\', 2000, NULL, NULL, 0, 0),'
        .' (3, 101, \'file\', \'files\', \'https://example.com/tool\', \'Tool\', \'\', NULL, NULL, NULL, NULL, 0, 1)');
}

# The scratch upload root with the directory of every type and of the forum, and the files the texts point at; no folder carries a guard, the light path refuses the private ones
function addRouteFiles(string $work): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    mkdir($work.'/uploads/node', 0777, true);
    foreach (['news', 'docs', 'off', 'help'] as $name) {
        mkdir($work.'/uploads/node/'.$name.'/thumb', 0777, true);
    }
    foreach (['cover-cccccccccc.png', 'att-aaaaaaaaaa.png', 'other-bbbbbbbbbb.png', 'thumb/att-aaaaaaaaaa.png'] as $one) file_put_contents($work.'/uploads/node/news/'.$one, $png);
    mkdir($work.'/uploads/forum/thumb', 0777, true);
    $forum = ['fa-aaaaaaaaaa.png', 'fb-aaaaaaaaaa.png', 'fc-aaaaaaaaaa.png', 'fd-aaaaaaaaaa.png', 'fe-aaaaaaaaaa.png', 'free-aaaaaaaaaa.png', 'mine-aaaaaaaaaa-2.png',
        'other-bbbbbbbbbb-3.png', 'thumb/fa-aaaaaaaaaa.png'];
    foreach ($forum as $one) file_put_contents($work.'/uploads/forum/'.$one, $png);
    foreach (['account', 'profile', 'voting'] as $dir) mkdir($work.'/uploads/'.$dir.'/thumb', 0777, true);
    $owns = ['account' => ['pa-aaaaaaaaaa-3.png', 'pb-aaaaaaaaaa-3.png', 'mine-aaaaaaaaaa-2.png', 'other-bbbbbbbbbb-4.png', 'thumb/pa-aaaaaaaaaa-3.png'],
        'profile' => ['sig-aaaaaaaaaa-3.png', 'own-aaaaaaaaaa-2.png', 'other-bbbbbbbbbb-4.png', 'thumb/sig-aaaaaaaaaa-3.png', 'pc-aaaaaaaaaa-4.png', 'pd-aaaaaaaaaa-2.png',
            'blk-aaaaaaaaaa-3.png'],
        'voting' => ['va-aaaaaaaaaa-3.png', 'vb-aaaaaaaaaa-2.png', 'vc-aaaaaaaaaa-3.png', 'mine-aaaaaaaaaa-2.png', 'other-bbbbbbbbbb-4.png', 'thumb/va-aaaaaaaaaa-3.png']];
    foreach ($owns as $dir => $list) foreach ($list as $one) file_put_contents($work.'/uploads/'.$dir.'/'.$one, $png);
    file_put_contents($work.'/uploads/account/brandbook.pdf', '%PDF-1.4'.str_repeat('0123456789', 199).'%%');
    file_put_contents($work.'/uploads/node/news/manual-dddddddddd.pdf', '%PDF-1.4'.str_repeat('0123456789', 199).'%%');
    file_put_contents($work.'/upload.png', $png);
}

# Start the built-in server with the route router on a free port and wait until it answers
# OPcache revalidates on every request: the probe rewrites the scratch configuration between requests, and a file cached for two seconds more would serve the old values
function addRouteServer(string $work, int $port): mixed {
    $env = getenv() + ['SLAED_ROUTE_ROOT' => $work];
    $log = ['file', $work.'/server.log', 'a'];
    $cmd = [PHP_BINARY, '-d', 'opcache.revalidate_freq=0', '-S', '127.0.0.1:'.$port, '-t', PUBLIC_DIR, BASE_DIR.'/tests/Support/route_web.php'];
    $proc = proc_open($cmd, [1 => $log, 2 => $log], $pipes, BASE_DIR, $env);
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) throw new RuntimeException('The route server did not start');
    fclose($test);
    return $proc;
}

# One free local port
function getRoutePort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    return $port;
}

# One real exchange as one visitor with the cookies of that visitor: the status, the headers by lower-case name and the body
function getRouteReply(string $who, string $method, string $path, array $post = [], array $head = []): array {
    global $rwork, $rport;
    $curl = curl_init('http://127.0.0.1:'.$rport.'/'.ltrim($path, '/'));
    $jar = $rwork.'/jar-'.($who === '' ? 'guest' : $who).'.txt';
    $send = array_merge(['X-Probe-Who: '.$who], $head);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => $send, CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD']);
    $multi = array_filter($post, fn($v) => $v instanceof CURLFile) !== [];
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $multi ? $post : http_build_query($post));
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

# The hidden token of the first form of a page that carries the given mark, the value of an op field or any other text of that form
function getRouteToken(string $html, string $mark): string {
    preg_match_all('#<form\b.*?</form>#s', $html, $all);
    foreach ($all[0] as $form) {
        $flat = (string)preg_replace('/\s+/', ' ', $form);
        $good = preg_match('/^[a-z]+$/D', $mark) ? str_contains($flat, 'name="op" value="'.$mark.'"') : str_contains($flat, $mark);
        if ($good && preg_match('#name="token"\s+value="([a-f0-9]{64})"#', $form, $hit)) return $hit[1];
    }
    return '';
}

# The value of a named hidden field of a page
function getRouteField(string $html, string $name): string {
    return preg_match('#name="'.preg_quote($name, '#').'"\s+value="([^"]*)"#', $html, $hit) ? html_entity_decode($hit[1]) : '';
}

# One column of one material row
function getRouteCol(PDO $pdo, int $id, string $col): mixed {
    $val = $pdo->query('SELECT '.$col.' FROM '.RPREF.'_nodes WHERE id = '.$id)->fetchColumn();
    return ($val === false) ? null : $val;
}

# The hits of one resource
function getRouteHits(PDO $pdo, int $id): int {
    return (int)$pdo->query('SELECT hits FROM '.RPREF.'_node_assets WHERE id = '.$id)->fetchColumn();
}

# The deferred counter of a view runs after the answer; wait for it a bounded time
function getRouteViews(PDO $pdo, int $id, int $want): int {
    for ($i = 0; $i < 30; $i++) {
        $num = (int)getRouteCol($pdo, $id, 'views');
        if ($num >= $want) return $num;
        usleep(100000);
    }
    return (int)getRouteCol($pdo, $id, 'views');
}

# The public list: status, clean parameters, paging, the category rights, the start page, a live render and a forged context
function getRouteLists(PDO $pdo): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $out = [];
    $one = getRouteReply('', 'GET', 'index.php?name=news');
    $out['list'] = [$one['code'], str_contains($one['body'], 'Gamma'), str_contains($one['body'], 'Beta'), str_contains($one['body'], 'Alpha'), str_contains($one['body'],
        'Members')];
    $out['canon'] = str_contains($one['body'], 'rel="canonical" href="http://127.0.0.1');
    $out['nostore'] = str_contains((string)($one['head']['cache-control'] ?? ''), 'no-store');
    $pdo->exec('UPDATE '.RPREF.'_nodes SET title = \'Gamma changed\' WHERE id = 105');
    $out['hit'] = str_contains(getRouteReply('', 'GET', 'index.php?name=news')['body'], 'Gamma changed');
    $out['user'] = str_contains(getRouteReply('anna', 'GET', 'index.php?name=news')['body'], 'Gamma changed');
    $pdo->exec('UPDATE '.RPREF.'_nodes SET title = \'Gamma\' WHERE id = 105');
    $two = getRouteReply('', 'GET', 'index.php?name=news&num=2');
    $out['page'] = [$two['code'], str_contains($two['body'], 'Alpha'), $code('', 'index.php?name=news&num=3')];
    $post = getRouteReply('', 'POST', 'index.php?name=news');
    $out['post'] = [$post['code'], $post['head']['allow'] ?? ''];
    $out['node'] = $code('', 'index.php?name=node');
    $out['bad'] = [$code('', 'index.php?name=news&cat=abc'), $code('', 'index.php?name=news&let=AB'), $code('', 'index.php?name=news&order=rating'),
        $code('', 'index.php?name=news&dir=up'), $code('', 'index.php?name=news&cat=99'), $code('', 'index.php?name=news&op=liste'), $code('', 'index.php?name=news&op=bogus')];
    $clean = getRouteReply('', 'GET', 'index.php?name=news&order=published&dir=desc');
    $out['clean'] = [$clean['code'], $clean['head']['location'] ?? ''];
    $sort = getRouteReply('', 'GET', 'index.php?name=news&order=title&dir=asc');
    $out['sort'] = [$sort['code'], str_contains($sort['body'], 'content="noindex, follow"'), $code('', 'index.php?name=news&let=A')];
    $file = $GLOBALS['rwork'].'/config/node.php';
    $keep = require $file;
    $node = $keep;
    $node['node']['types']['news']['list'] = ['orders' => ['title', 'views'], 'order' => 'title', 'dir' => 'asc'] + $node['node']['types']['news']['list'];
    setRouteFile($file, $node);
    if (is_file($GLOBALS['rwork'].'/config/local.php')) unlink($GLOBALS['rwork'].'/config/local.php');
    $out['published'] = [$code('', 'index.php?name=news&order=published&dir=desc'), $code('', 'index.php?name=news&order=rating')];
    setRouteFile($file, $keep);
    if (is_file($GLOBALS['rwork'].'/config/local.php')) unlink($GLOBALS['rwork'].'/config/local.php');
    $shut = getRouteReply('', 'GET', 'index.php?name=news&cat=2');
    $open = getRouteReply('anna', 'GET', 'index.php?name=news&cat=2');
    $out['cat'] = [$shut['code'], str_contains($shut['body'], 'Members'), $open['code'], str_contains($open['body'], '>Members<'), $code('', 'index.php?name=news&cat=1')];
    $out['off'] = [$code('', 'index.php?name=off'), $code('root', 'index.php?name=off'), $code('', 'index.php?name=off&super=1&mods=off&task=1&aid=1&manage=1')];
    $home = getRouteReply('', 'GET', '');
    $out['home'] = [$home['code'], str_contains($home['body'], 'Gamma')];
    return $out;
}

# One material: the route type, the rights, the views counted after the answer, never for HEAD, and the controlled address of its attachment
function getRouteViewRuns(PDO $pdo): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $one = getRouteReply('', 'GET', 'index.php?name=news&op=view&id=101');
    $out = ['view' => [$one['code'], str_contains($one['body'], '<h1 class="sl-title">Alpha</h1>'),
        str_contains($one['body'], 'go=file&amp;own=node&amp;id=101&amp;key=att-aaaaaaaaaa.png'), str_contains($one['body'], 'op=asset&amp;id=2'),
        str_contains($one['body'], 'uploads/node/news/')]];
    $out['views'] = getRouteViews($pdo, 101, 1);
    getRouteReply('', 'HEAD', 'index.php?name=news&op=view&id=101');
    usleep(500000);
    $out['head'] = (int)getRouteCol($pdo, 101, 'views');
    $ogimg = fn(string $html): string => preg_match('#property="og:image"\s+content="([^"]*)"#', $html, $hit) ? html_entity_decode($hit[1]) : '';
    $pdo->exec('INSERT INTO '.RPREF.'_node_assets (id, nid, kind, role, src, name, intro, hits, sort)'
        .' VALUES (40, 102, \'image\', \'cover\', \'https://cdn.example.com/p.png\', \'\', \'\', 0, 0)');
    $out['cover'] = [$ogimg($one['body']), $ogimg(getRouteReply('', 'GET', 'index.php?name=news&op=view&id=102')['body'])];
    $pdo->exec('DELETE FROM '.RPREF.'_node_assets WHERE id = 40');
    $out['rights'] = [$code('', 'index.php?name=news&op=view&id=103'), $code('anna', 'index.php?name=news&op=view&id=103'), $code('', 'index.php?name=news&op=view&id=104'),
        $code('moder', 'index.php?name=news&op=view&id=104'), $code('docsman', 'index.php?name=news&op=view&id=104'), $code('', 'index.php?name=news&op=view&id=201'),
        $code('', 'index.php?name=news&op=view&id=0'), $code('', 'index.php?name=news&op=view&id=abc')];
    return $out;
}

# The editor attachment of a stored material on the file route: its own name only, the thumb, and every crafted key or parameter refused with the same not found
function getRouteAttach(): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $own = 'index.php?go=file&own=node&id=101&key=att-aaaaaaaaaa.png';
    $one = getRouteReply('', 'GET', $own);
    $head = getRouteReply('', 'HEAD', $own);
    return [
        'file' => [$one['code'], $one['head']['content-type'] ?? '', $one['head']['cache-control'] ?? '', strlen($one['body'])],
        'head' => [$head['code'], strlen($head['body'])],
        'thumb' => $code('', $own.'&thumb=1'),
        'refused' => [
            $code('', 'index.php?go=file&own=node&id=101&key=other-bbbbbbbbbb.png'), $code('', 'index.php?go=file&own=node&id=101&key=att-aaaaaaaaaa.png%00.php'),
            $code('', $own.'&foo=1'), $code('', $own.'&preview=1'), $code('', $own.'&thumb=2'), $code('', 'index.php?go=file&own=node&id=103&key=att-aaaaaaaaaa.png'),
            $code('', 'index.php?go=file&own=forum&id=101&key=att-aaaaaaaaaa.png'), $code('', 'index.php?go=file&own=node&name=news&key=att-aaaaaaaaaa.png&preview=1'),
            $code('', $own.'&%6Bey=att-aaaaaaaaaa.png'), $code('', 'index.php?go=file&own=node&id=101&id=101&key=att-aaaaaaaaaa.png'), $code('', $own.'&amp;thumb=1'),
            $code('', $own.'&utm_source=x'), $code('', 'index.php?go=file&own=node&id=01&key=att-aaaaaaaaaa.png'), $code('', $own.'&=x'),
            $code('', 'index.php?go=file&own=node&name=news&id=101&key=att-aaaaaaaaaa.png'), $code('', 'index.php?go=file&own=node&key=att-aaaaaaaaaa.png&preview=1'),
            $code('', 'index.php?go=file&own=forum&name=news&key=att-aaaaaaaaaa.png&preview=1'), $code('', 'index.php?go=file&own=public&id=101&key=att-aaaaaaaaaa.png'),
            $code('', 'index.php?go=file&own=zz&id=101&key=att-aaaaaaaaaa.png'), $code('', 'index.php?go=file&id=101&key=att-aaaaaaaaaa.png'),
        ],
        'coded' => $code('', 'index.php?go=file&own=%6E%6F%64%65&id=101&%6Bey=att-aaaaaaaaaa.png'),
        'post' => $code('', $own, 'POST'),
        'retired' => $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png'),
        'direct' => $code('', 'uploads/node/news/att-aaaaaaaaaa.png'),
        'climb' => str_contains(getRouteReply('', 'GET', 'index.php?go=file&own=node&id=101&key=..%2Fconfig%2Fdb.php')['body'], "'pass'"),
    ];
}

# The structured resources: an image shown without counting, a download counted before a whole body or a range from zero only, an external visit counted before its redirect
# An external image address is shown by its page itself, with no redirect at all
function getRouteAssets(PDO $pdo): array {
    $img = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=1');
    $out = ['image' => [$img['code'], $img['head']['content-type'] ?? '', getRouteHits($pdo, 1)]];
    $get = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=2');
    $out['download'] = [$get['code'], $get['head']['content-type'] ?? '', $get['head']['content-disposition'] ?? '', strlen($get['body']), getRouteHits($pdo, 2)];
    getRouteReply('', 'HEAD', 'index.php?name=news&op=asset&id=2');
    $out['head'] = getRouteHits($pdo, 2);
    $zero = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=2', [], ['Range: bytes=0-9']);
    $mid = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=2', [], ['Range: bytes=10-19']);
    $out['range'] = [$zero['code'], $mid['code'], strlen($mid['body']), getRouteHits($pdo, 2)];
    $bad = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=2', [], ['Range: bytes=9999-']);
    $out['unmet'] = [$bad['code'], getRouteHits($pdo, 2)];
    $link = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=3');
    $out['link'] = [$link['code'], $link['head']['location'] ?? '', getRouteHits($pdo, 3)];
    $pdo->exec('INSERT INTO '.RPREF.'_node_assets (id, nid, kind, role, src, name, intro, hits, sort) VALUES (4, 101, \'image\', \'cover\', \'https://example.com/p.png\','
        .' \'\', \'\', 0, 1)');
    $shown = getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=4');
    $out['shown'] = [$shown['code'], $shown['head']['location'] ?? '', getRouteHits($pdo, 4)];
    $pdo->exec('DELETE FROM '.RPREF.'_node_assets WHERE id = 4');
    $out['foreign'] = [getRouteReply('', 'GET', 'index.php?name=docs&op=asset&id=2')['code'], getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=99')['code']];
    $out['direct'] = getRouteReply('', 'GET', 'uploads/node/news/manual-dddddddddd.pdf')['code'];
    return $out;
}

# The report of a resource: POST with the token of the page, stored once for a guest, and a second report of the same visitor within a minute refused
function getRouteReports(PDO $pdo): array {
    $page = getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=101');
    $tok = getRouteToken($page['body'], 'op=report');
    $out = ['get' => getRouteReply('boris', 'GET', 'index.php?name=news&op=report&id=2')['code']];
    $out['token'] = getRouteReply('boris', 'POST', 'index.php?name=news&op=report&id=2', ['refer' => '1'])['code'];
    $one = getRouteReply('boris', 'POST', 'index.php?name=news&op=report&id=2', ['token' => $tok, 'refer' => '1']);
    $row = $pdo->query('SELECT reported IS NOT NULL AS open, ruid FROM '.RPREF.'_node_assets WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
    $out['sent'] = [$one['code'], (int)$row['open'], (int)$row['ruid']];
    $out['back'] = $one['head']['location'] ?? '';
    $two = getRouteReply('boris', 'POST', 'index.php?name=news&op=report&id=2', ['token' => $tok, 'refer' => '1']);
    $out['again'] = [$two['code'], ($two['head']['retry-after'] ?? '') !== ''];
    $out['image'] = getRouteReply('clara', 'POST', 'index.php?name=news&op=report&id=1', ['token' => getRouteToken(getRouteReply('clara', 'GET',
        'index.php?name=news&op=view&id=101')['body'], 'op=report')])['code'];
    return $out;
}

# The public form: the workflow decides who may open it, a preview writes nothing, a submission needs its token and lands pending with its author
function getRouteForm(PDO $pdo): array {
    $count = fn(): int => (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_nodes')->fetchColumn();
    $out = ['guest' => getRouteReply('', 'GET', 'index.php?name=news&op=add')['code'], 'docs' => getRouteReply('anna', 'GET', 'index.php?name=docs&op=add')['code']];
    $form = getRouteReply('anna', 'GET', 'index.php?name=news&op=add');
    $tok = getRouteToken($form['body'], 'name="action"');
    $out['form'] = [$form['code'], $tok !== '', str_contains($form['body'], 'name="intro"'), str_contains($form['body'], 'data-sl-repeat')];
    $file = $GLOBALS['rwork'].'/config/node.php';
    $keep = require $file;
    $node = $keep;
    $node['node']['types']['news']['features']['moderation'] = false;
    setRouteFile($file, $node);
    if (is_file($GLOBALS['rwork'].'/config/local.php')) unlink($GLOBALS['rwork'].'/config/local.php');
    $open = getRouteReply('anna', 'GET', 'index.php?name=news&op=add')['body'];
    setRouteFile($file, $keep);
    if (is_file($GLOBALS['rwork'].'/config/local.php')) unlink($GLOBALS['rwork'].'/config/local.php');
    $out['link'] = [str_contains($form['body'], 'name="aurl'), str_contains($open, 'name="aurl'), str_contains($open, 'name="apath')];
    $base = ['title' => 'Probe submission', 'intro' => 'Intro of the submission', 'body' => 'Body of the submission', 'cid' => '1'];
    $was = $count();
    $prev = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', $base + ['action' => 'preview', 'token' => $tok]);
    $out['preview'] = [$prev['code'], str_contains($prev['body'], 'Intro of the submission'), $count() - $was];
    $out['notoken'] = [getRouteReply('anna', 'POST', 'index.php?name=news&op=add', $base + ['action' => 'submit'])['code'], $count() - $was];
    $out['badact'] = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', $base + ['action' => 'publish', 'token' => $tok])['code'];
    $done = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', $base + ['action' => 'submit', 'token' => $tok]);
    $row = $pdo->query('SELECT status, uid, cid FROM '.RPREF.'_nodes WHERE title = \'Probe submission\'')->fetch(PDO::FETCH_ASSOC) ?: [];
    $out['submit'] = [$done['code'], $done['head']['location'] ?? '', $count() - $was, array_map('intval', $row)];
    $bad = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', ['title' => '', 'action' => 'submit', 'token' => $tok]);
    $out['invalid'] = [$bad['code'], $count() - $was];
    $file = new CURLFile($GLOBALS['rwork'].'/upload.png', 'image/png', 'upload.png');
    $multi = ['title' => 'With cover', 'intro' => '', 'body' => '', 'action' => 'preview', 'token' => $tok, 'asset[0][role]' => 'cover', 'asset[0][id]' => '',
        'asset[0][title]' => 'My cover', 'afile0' => $file];
    $prev = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', $multi);
    $key = preg_match('#go=file&amp;own=node&amp;name=news&amp;key=([A-Za-z0-9_.-]+)&amp;preview=1#', $prev['body'], $hit) ? $hit[1] : '';
    $path = 'index.php?go=file&own=node&name=news&key='.$key.'&preview=1';
    $mine = getRouteReply('anna', 'GET', $path);
    $out['upload'] = [$prev['code'], $key !== '', str_contains($key, '-2.'), is_file($GLOBALS['rwork'].'/uploads/node/news/'.$key), $mine['code'],
        $mine['head']['cache-control'] ?? '', getRouteReply('boris', 'GET', $path)['code'], getRouteReply('', 'GET', $path)['code'], getRouteReply('moder', 'GET', $path)['code'],
        $count() - $was];
    $sub = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', ['title' => 'With cover', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $tok,
        'asset' => [['role' => 'cover', 'id' => '', 'title' => 'My cover']], 'apath0' => $key]);
    $row = $pdo->query('SELECT a.src, a.kind, a.mime FROM '.RPREF.'_node_assets AS a INNER JOIN '.RPREF.'_nodes AS n ON n.id = a.nid'
        .' WHERE n.title = \'With cover\'')->fetch(PDO::FETCH_ASSOC);
    $out['bound'] = [$sub['code'], $row ? array_values($row) : []];
    $steal = getRouteReply('boris', 'POST', 'index.php?name=news&op=add', ['title' => 'Stolen', 'intro' => '', 'body' => '[attach='.$key.' align=left title=x]',
        'action' => 'submit',
        'token' => getRouteToken(getRouteReply('boris', 'GET', 'index.php?name=news&op=add')['body'], 'name="action"')]);
    $out['steal'] = [$steal['code'], (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_nodes WHERE title = \'Stolen\'')->fetchColumn()];
    return $out;
}

# The administrative entry: who passes the gate, who sees which screen, the state move with its notice, the conflict of two editors and the deletion
function getRouteAdmin(PDO $pdo): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $out = [];
    $list = getRouteReply('root', 'GET', 'admin.php?name=node&status=1');
    $more = getRouteReply('root', 'GET', 'admin.php?name=node&status=1&num=2');
    $out['queue'] = [$list['code'], str_contains($list['body'], 'Probe submission'), str_contains($list['body'].$more['body'], 'Pending one')];
    $out['gate'] = [
        'moder' => [$code('moder', 'admin.php?name=node'), $code('moder', 'admin.php?name=node&op=types'), $code('moder', 'admin.php?name=node&op=edit&id=101'),
            $code('moder', 'admin.php?name=node&op=edit&id=201')],
        'boss' => [$code('boss', 'admin.php?name=node'), $code('boss', 'admin.php?name=node&op=types'), $code('boss', 'admin.php?name=node&op=edit&id=101')],
        'docsman' => [$code('docsman', 'admin.php?name=node'), $code('docsman', 'admin.php?name=node&op=edit&id=201'), $code('docsman', 'admin.php?name=node&op=edit&id=101')],
        'user' => str_contains(getRouteReply('clara', 'GET', 'admin.php?name=node')['body'], 'name="pwd"'),
        'bogus' => $code('root', 'admin.php?name=node&op=bogus'),
        'getmove' => $code('root', 'admin.php?name=node&op=status&id=104'),
        'info' => [$code('root', 'admin.php?name=node&op=info'), $code('moder', 'admin.php?name=node&op=info')],
        'queue' => [$code('moder', 'admin.php?name=node&type=docs'), str_contains(getRouteReply('moder', 'GET', 'admin.php?name=node')['body'], 'value="docs"'),
            str_contains(getRouteReply('root', 'GET', 'admin.php?name=node')['body'], 'value="docs"')],
    ];
    $page = getRouteReply('moder', 'GET', 'admin.php?name=node&status=1');
    $tok = getRouteToken($page['body'], 'status');
    $mails = (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_mail')->fetchColumn();
    $move = getRouteReply('moder', 'POST', 'admin.php', ['name' => 'node', 'op' => 'status', 'id' => '104', 'type' => 'news', 'status' => '2', 'version' => '1', 'token' => $tok]);
    $mail = $pdo->query('SELECT email, kind FROM '.RPREF.'_mail ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $out['move'] = [$move['code'], (int)getRouteCol($pdo, 104, 'status'), (int)getRouteCol($pdo, 104, 'version'),
        (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_mail')->fetchColumn() - $mails, $mail['email'] ?? '', $mail['kind'] ?? ''];
    $stale = getRouteReply('moder', 'POST', 'admin.php', ['name' => 'node', 'op' => 'status', 'id' => '104', 'type' => 'news', 'status' => '3', 'version' => '1', 'token' => $tok]);
    $out['stale'] = [$stale['code'], (int)getRouteCol($pdo, 104, 'status')];
    $one = getRouteReply('root', 'GET', 'admin.php?name=node&op=edit&id=102&type=news');
    $tok = getRouteToken($one['body'], 'edit');
    $ver = getRouteField($one['body'], 'version');
    $form = ['name' => 'node', 'op' => 'edit', 'id' => '102', 'type' => 'news', 'token' => $tok, 'version' => $ver, 'action' => 'save', 'intro' => 'intro of 102',
        'body' => 'Body of beta'];
    $first = getRouteReply('root', 'POST', 'admin.php', $form + ['title' => 'Beta first']);
    $second = getRouteReply('boss', 'GET', 'admin.php?name=node&op=edit&id=102');
    $clash = getRouteReply('root', 'POST', 'admin.php', $form + ['title' => 'Beta second']);
    $out['clash'] = [$first['code'], $clash['code'], (string)getRouteCol($pdo, 102, 'title'), str_contains($clash['body'], 'value="keep"'), str_contains($clash['body'],
        'Beta second'),
        getRouteField($clash['body'], 'version'), $second['code']];
    $keep = getRouteReply('root', 'POST', 'admin.php', array_replace($form, ['action' => 'keep', 'title' => 'Beta second']));
    $now = getRouteField($keep['body'], 'version');
    $out['keep'] = [$keep['code'], $now, (string)getRouteCol($pdo, 102, 'title'), str_contains($keep['body'], 'Beta second')];
    $last = getRouteReply('root', 'POST', 'admin.php', array_replace($form, ['version' => $now, 'title' => 'Beta second']));
    $out['save'] = [$last['code'], (string)getRouteCol($pdo, 102, 'title'), (int)getRouteCol($pdo, 102, 'version')];
    $page = getRouteReply('root', 'GET', 'admin.php?name=node');
    $tok = getRouteToken($page['body'], 'delete');
    $gone = getRouteReply('root', 'POST', 'admin.php', ['name' => 'node', 'op' => 'delete', 'id' => '105', 'type' => 'news', 'version' => '1', 'token' => $tok]);
    $out['delete'] = [$gone['code'], getRouteCol($pdo, 105, 'id')];
    return $out;
}

# The type screens of the manager: create, export, import under a new name, the limits and the deletion; switching a type off closes its public list
# A limit save that cannot write its source answers 500, and one refused by an unfinished operation of the journal answers 409; neither changes the stored limits
# The source is made unwritable by a directory in the place of its temporary file, and the one warning that failure is expected to leave is taken out of the PHP log
function getRouteTypes(PDO $pdo, string $work): array {
    $has = fn(string $name): int => (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_node_types WHERE name = '.$pdo->quote($name))->fetchColumn();
    $form = getRouteReply('boss', 'GET', 'admin.php?name=node&op=type');
    $tok = getRouteToken($form['body'], 'type');
    $post = ['name' => 'node', 'op' => 'type', 'token' => $tok, 'tname' => 'temp', 'title' => 'Temp', 'intro' => '', 'ext' => '', 'sort' => '5', 'orders' => ['published'],
        'order' => 'published', 'dir' => 'desc', 'limit' => '10', 'show' => ['date'], 'mode' => 'default', 'access' => ['1|0'], 'notify_pending' => '1', 'seo' => 'website'];
    $made = getRouteReply('boss', 'POST', 'admin.php', $post);
    $out = ['create' => [$made['code'], $has('temp'), (int)$pdo->query('SELECT active FROM '.RPREF.'_node_types WHERE name = \'temp\'')->fetchColumn(),
        is_dir($work.'/uploads/node/temp')]];
    $out['twice'] = [getRouteReply('boss', 'POST', 'admin.php', $post)['code'], $has('temp')];
    $roles = ['role' => [['name' => 'shot', 'title' => 'Shot', 'mode' => 'image', 'max' => '1', 'report' => '1', 'active' => '1'],
        ['name' => 'pack', 'title' => 'Pack', 'mode' => 'download', 'max' => '1', 'report' => '1', 'active' => '1']]];
    $rolled = getRouteReply('boss', 'POST', 'admin.php', array_replace($post, ['tname' => 'rolled'], $roles))['code'];
    $cfg = require $work.'/config/node.php';
    $set = $cfg['node']['types']['rolled']['assets'] ?? [];
    $out['roles'] = [$rolled, $set['shot']['report'] ?? false, $set['pack']['report'] ?? false, str_contains($form['body'], 'sl-role-report'),
        str_contains($form['body'], '<option value="article"')];
    $out['mode'] = [getRouteReply('boss', 'POST', 'admin.php', array_replace($post, ['tname' => 'badmode', 'mode' => 'gallery']))['code'], $has('badmode'),
        getRouteReply('boss', 'POST', 'admin.php', array_replace($post, ['tname' => 'supmode', 'mode' => 'support']))['code'], $has('supmode')];
    $json = getRouteReply('boss', 'GET', 'admin.php?name=node&op=export&type=temp');
    $out['export'] = [$json['code'], $json['head']['content-type'] ?? '', $json['head']['content-disposition'] ?? '', (json_decode($json['body'], true)['format'] ?? '')];
    $page = getRouteReply('boss', 'GET', 'admin.php?name=node&op=clone&type=temp');
    $copy = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'clone', 'type' => 'temp', 'tname' => 'copy', 'token' => getRouteToken($page['body'], 'clone')]);
    $out['clone'] = [$copy['code'], $has('copy')];
    $page = getRouteReply('boss', 'GET', 'admin.php?name=node&op=config');
    $tok = getRouteToken($page['body'], 'config');
    $low = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '100', 'maxlist' => '1', 'syncbatch' => '500',
        'send' => '0', 'edit' => '600']);
    $cfg = require $work.'/config/node.php';
    $out['limits'] = [$low['code'], $cfg['node']['limits']['maxlist']];
    $few = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '1', 'maxlist' => '100', 'syncbatch' => '500',
        'send' => '0', 'edit' => '600']);
    $cfg = require $work.'/config/node.php';
    $out['limits'][] = $few['code'];
    $out['limits'][] = $cfg['node']['limits']['maxassets'];
    $wide = ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '100', 'maxlist' => '150', 'syncbatch' => '500', 'send' => '0', 'edit' => '600'];
    $out['limits'][] = getRouteReply('boss', 'POST', 'admin.php', $wide)['code'];
    $cfg = require $work.'/config/node.php';
    $out['limits'][] = $cfg['node']['limits']['maxlist'];
    $out['send'] = [getRouteReply('boss', 'POST', 'admin.php', array_replace($wide, ['send' => '-5']))['code'], getRouteReply('boss', 'POST', 'admin.php',
        array_diff_key($wide, ['send' => '']))['code'], $cfg['node']['limits']['send'], (require $work.'/config/node.php')['node']['limits']['send']];
    mkdir($work.'/config/node.php.tmp');
    $out['limits'][] = getRouteReply('boss', 'POST', 'admin.php', array_replace($wide, ['maxassets' => '90']))['code'];
    rmdir($work.'/config/node.php.tmp');
    $plog = $work.'/logs/error_php.log';
    if (is_file($plog)) file_put_contents($plog, implode('', array_filter(file($plog) ?: [], fn($v) => !str_contains($v, 'config/node.php.tmp'))));
    if (!is_dir($work.'/backup/config')) mkdir($work.'/backup/config', 0777, true);
    file_put_contents($work.'/backup/config/marker.json', '{}');
    $out['limits'][] = getRouteReply('boss', 'POST', 'admin.php', array_replace($wide, ['maxassets' => '90']))['code'];
    unlink($work.'/backup/config/marker.json');
    $cfg = require $work.'/config/node.php';
    $out['limits'][] = $cfg['node']['limits']['maxassets'];
    $page = getRouteReply('boss', 'GET', 'admin.php?name=node&op=types');
    $del = ['name' => 'node', 'op' => 'typedelete', 'type' => 'copy', 'version' => '1', 'token' => getRouteToken($page['body'], 'typedelete')];
    $out['delete'] = [getRouteReply('boss', 'POST', 'admin.php', $del)['code'], $has('copy'), getRouteReply('moder', 'POST', 'admin.php', array_replace($del,
        ['type' => 'temp']))['code'], $has('temp')];
    $off = ['name' => 'node', 'op' => 'typestatus', 'type' => 'docs', 'active' => '0', 'version' => '1', 'token' => getRouteToken($page['body'], 'typestatus')];
    $out['off'] = [getRouteReply('boss', 'POST', 'admin.php', $off)['code'], getRouteReply('', 'GET', 'index.php?name=docs')['code'], getRouteReply('docsman', 'GET',
        'index.php?name=docs')['code']];
    return $out;
}

# The data contract of the view preparer on the materials of the probe, read in a child process that boots the core on the scratch configuration and database
# Every mode answers exactly the keys of docs/NODE.md (NodeView keys); a target answers them empty; the resources carry no source, report or reporter; foreign pairs are refused
function getRouteViewData(): array {
    global $db, $prs, $fld;
    $keys = ['id', 'type', 'mode', 'href', 'title', 'intro', 'intro_html', 'body_html', 'author', 'ahref', 'ctitle', 'chref', 'date', 'date_iso', 'mtime_iso', 'views', 'comnum',
        'rating', 'ratings', 'fields', 'assets'];
    $item = ['id', 'kind', 'role', 'href', 'rhref', 'name', 'title', 'intro', 'mime', 'size', 'stext', 'width', 'height', 'duration', 'hits', 'islink'];
    $ctx = new NodeContext(0, [], 0, [], false, false, '127.0.0.1', '');
    $query = new NodeQuery($db, $ctx, $fld);
    $news = $query->getNodeType('news');
    $docs = $query->getNodeType('docs');
    $node = $query->getNode(101, $news);
    $tgt = $query->getNodeTarget('news', 102);
    $view = new NodeView($prs, $fld);
    $out = ['keys' => [], 'refused' => []];
    foreach (['list', 'view', 'card'] as $mode) $out['keys'][$mode] = array_keys($view->getNodeView($news, $node, $mode)) === $keys;
    $out['keys']['tcard'] = array_keys($view->getNodeView($news, $tgt, 'card')) === $keys;
    $full = $view->getNodeView($news, $node, 'view');
    $light = $view->getNodeView($news, $tgt, 'card');
    $out['full'] = ['href' => $full['href'], 'title' => $full['title'], 'intro' => $full['intro'], 'body' => str_contains($full['body_html'], 'go=file&amp;own=node&amp;id=101'),
        'chref' => $full['chref'], 'ctitle' => $full['ctitle'], 'author' => $full['author'], 'ahref' => $full['ahref'], 'date' => $full['date'] !== '',
            'iso' => $full['date_iso'] !== '',
        'rating' => $full['rating'], 'roles' => array_keys($full['assets'])];
    $one = $full['assets']['files'][0] ?? [];
    $out['asset'] = ['keys' => array_keys($one) === $item, 'href' => $one['href'] ?? '', 'rhref' => $one['rhref'] ?? '', 'src' => str_contains(json_encode($full),
        'manual-dddddddddd'),
        'link' => $full['assets']['files'][1]['islink'] ?? null, 'cover' => $full['assets']['cover'][0]['href'] ?? ''];
    $out['light'] = ['title' => $light['title'], 'intro' => $light['intro'], 'body' => $light['body_html'], 'views' => $light['views'], 'fields' => $light['fields'],
        'assets' => $light['assets']];
    $out['list'] = $view->getNodeView($news, $node, 'list')['body_html'];
    $pairs = [[$news, $node, 'block'], [$news, $tgt, 'view'], [$news, $node, 'print'], [$docs, $node, 'view'], [$docs, $tgt, 'card'], [$news, $tgt, 'block'],
        [$news, $tgt, 'search']];
    foreach ($pairs as $i => [$type, $obj, $mode]) {
        try {
            $view->getNodeView($type, $obj, $mode);
            $out['refused'][$i] = 'ok';
        } catch (NodeException $err) {
            $out['refused'][$i] = $err->getCode();
        }
    }
    $rate = new Node(9, $news->id, 0, 0, '', null, 'T', 'I', null, null, 0, false, CommentMode::Disabled, false, 0, 0, 13, 3, NodeStatus::Published, 1, '', '', null, null, null,
        null, null, null, null);
    $out['average'] = [$view->getNodeView($news, $rate, 'card')['rating'], $full['rating']];
    $text = fn(string $intro): array => $view->getNodeView($news, new Node(8, $news->id, 0, 0, '', null, 'T', $intro, null, null, 0, false, CommentMode::Disabled, false, 0, 0, 0,
        0, NodeStatus::Published, 1, '', '', null, null, null, null, null, null, null), 'card');
    $mixed = $text('<b>x</b> [usehtml]<i>y</i><script>z()</script>[/usehtml] Plain');
    $out['trusted'] = [str_contains($text('<b>x</b>')['intro_html'], '<b>'), str_contains($mixed['intro_html'], '<b>x'), str_contains($mixed['intro_html'], '<i>y</i>'),
        str_contains($mixed['intro'], 'z()'), str_contains($mixed['intro'], 'Plain')];
    return $out;
}

# Stand in for one visitor of a child process before the core boots: the account cookie and the administrator session of the seeded accounts
function setRouteChild(string $who): void {
    $glob = require CONFIG_DIR.'/global.php';
    $users = ['anna' => '2:anna:hash-anna', 'boris' => '3:boris:hash-boris', 'helper' => '5:helper:hash-helper'];
    $admins = ['root' => '1:root:hash-root', 'docsman' => '4:docsman:hash-docsman', 'helper' => '5:helper:hash-helper'];
    if (isset($users[$who])) $_COOKIE[$glob['user_c'].'-account'] = base64_encode($users[$who]);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($admins[$who])) $_SESSION[$glob['admin_c']] = base64_encode($admins[$who]);
}

# The comment reads of one visitor in a child process: the moderation list and its module selector, and the profile feed of anna
function getRouteCommentData(): array {
    global $com;
    $ids = fn(array $rows): array => array_map(fn($v) => $v['modul'].':'.$v['cid'], $rows);
    return [
        'admin' => array_values(array_unique($ids($com->getAdminList(CommentStatus::Published, '', 2, '', 1)['rows']))),
        'mods' => $com->getModuleList(),
        'feed' => array_values(array_unique($ids($com->getUserList(2, 25)))),
    ];
}

# One call of the class NodeSupport answered as its result or the code and message of its refusal
function getRouteCall(Closure $work): array {
    try {
        return ['ok' => true, 'value' => $work()];
    } catch (NodeException $err) {
        return ['ok' => false, 'code' => $err->getCode(), 'msg' => $err->getMessage()];
    }
}

# The class NodeSupport asked directly with explicit contexts: the configuration it accepts, the maps it checks, the scope of each reader, the closed actions
# It also reports the refusals of its commands and a write of the core that its hooks take back as a whole
function getRouteExtData(): array {
    global $db, $conf, $fld, $pnt;
    $root = new NodeContext(0, [], 1, [], true, true, '127.0.0.1', '');
    $anna = new NodeContext(2, [1], 0, [], false, false, '127.0.0.1', '');
    $guest = new NodeContext(0, [], 0, [], false, false, '127.0.0.1', '');
    $task = new NodeContext(0, [], 0, [], false, false, '', '', true);
    $help = (new NodeQuery($db, $root, $fld))->getNodeType('help');
    $set = $help->settings;
    $ext = new NodeSupport($db, $root);
    $tryconf = fn(array $cfg, array $over = []): array => getRouteCall(fn() => $ext->filterNodeConfig($cfg, array_replace_recursive($set, $over), []));
    $out = ['config' => [
        'good' => $tryconf(['mail' => false]),
        'empty' => $tryconf([]),
        'number' => $tryconf(['mail' => 1]),
        'extra' => $tryconf(['mail' => true, 'copy' => true]),
        'rating' => $tryconf(['mail' => true], ['features' => ['rating' => true]]),
        'moderation' => $tryconf(['mail' => true], ['features' => ['moderation' => true]]),
        'search' => $tryconf(['mail' => true], ['integrations' => ['search' => true]]),
        'guests' => $tryconf(['mail' => true], ['workflow' => ['access' => 'all']]),
        'mode' => $tryconf(['mail' => true], ['view' => ['mode' => 'default']]),
    ]];
    $keep = $conf['node']['support'];
    $maps = ['swap' => ['state' => ['staff' => 0, 'author' => 1, 'closed' => 5]], 'order' => ['prio' => ['low' => 3, 'normal' => 1, 'high' => 2, 'urgent' => 0]],
        'lost' => ['state' => ['staff' => 0, 'author' => 1]], 'text' => ['prio' => ['low' => '0', 'normal' => 1, 'high' => 2, 'urgent' => 3]]];
    foreach ($maps as $key => $over) {
        $conf['node']['support'] = array_replace($keep, $over);
        $out['maps'][$key] = getRouteCall(fn() => $ext->filterNodeConfig(['mail' => true], $set, []));
    }
    $conf['node']['support'] = $keep;
    $out['scope'] = [(new NodeSupport($db, $root))->getNodeScope($help), (new NodeSupport($db, $anna))->getNodeScope($help), (new NodeSupport($db, $guest))->getNodeScope($help)];
    $tgt = (new NodeQuery($db, $root, $fld))->getNodeTarget('help', (int)$db->getSqlQuery('SELECT MIN(id) FROM '.PREFIX_DB.'_nodes WHERE tid = 4')->fetchColumn());
    foreach (['comment', 'rate', 'favorite', 'asset', 'report', 'vote', 'Comment'] as $one) $out['actions'][$one] = getRouteCall(fn() => $ext->checkNodeAction($help, $tgt, $one));
    $out['data'] = getRouteCall(fn() => $ext->filterNodeData($help, ['x' => 1]));
    $out['notrans'] = getRouteCall(fn() => $ext->updateNodeAction($help, $tgt, 'comment', $tgt->uid));
    $out['taskcard'] = getRouteCall(fn() => (new NodeSupport($db, $task))->updateNodeSupport($tgt->id, 0, 0, 1, 1));
    $out['ownerlist'] = getRouteCall(fn() => (new NodeSupport($db, $anna))->getNodeSupportList($help, 1, 10));
    $out['badlist'] = [getRouteCall(fn() => $ext->getNodeSupportList($help, 1, 1000)), getRouteCall(fn() => $ext->getNodeSupportList($help, 1, 10, 7)),
        getRouteCall(fn() => $ext->getNodeSupportList($help, 0, 10))];
    $count = fn(): int => (int)$db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes')->fetchColumn();
    $was = $count();
    $input = new NodeInput(3, [], '', 'Nobody owns it', 'intro', 'body', [], 0, false, CommentMode::Open, false, null, null, [], [], []);
    $out['noowner'] = [getRouteCall(fn() => (new NodeService($db, $root, $fld, $pnt, $ext))->addNode($help, $input, NodeStatus::Published)), $count() - $was];
    $one = (new NodeQuery($db, $root, $fld))->setNodeExtension($ext)->getNode($tgt->id, $help);
    $move = new NodeInput($one->cid, [], '', $one->title, $one->intro, (string)$one->body, [], 0, false, CommentMode::Moderated, false, $one->pubdate, null, [], [], []);
    $out['comon'] = [getRouteCall(fn() => (new NodeService($db, $root, $fld, $pnt, $ext))->updateNode($one->id, $move, $one->version)),
        (int)$db->getSqlQuery('SELECT comon FROM '.PREFIX_DB.'_nodes WHERE id = :id', ['id' => $one->id])->fetchColumn()];
    return $out;
}

# The comments of Node and the private requests of support over real HTTP: the guest refusal, the requests of two owners and their notices
# The replies of the owner and the staff move the waiting side and the counter; every reader keeps privacy, the owner closes and reopens, operators get a card and a queue
function getRouteSupport(PDO $pdo): array {
    global $rwork;
    $pre = RPREF.'_';
    $data = require $rwork.'/config/points.php';
    $data['points']['active'] = '1';
    $data['points']['actions']['moderate'] = ['points' => '2', 'period' => '0', 'limit' => '0'];
    setRouteFile($rwork.'/config/points.php', $data);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $mails = fn(): array => $pdo->query('SELECT email, title, body FROM '.$pre.'mail ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $card = fn(int $id): array => array_map('intval', $pdo->query('SELECT aid, state, prio, version FROM '.$pre.'node_support WHERE nid = '.$id)->fetch(PDO::FETCH_ASSOC) ?: []);
    $when = fn(int $id): string => (string)$pdo->query('SELECT activity FROM '.$pre.'node_support WHERE nid = '.$id)->fetchColumn();
    $rows = fn(int $id): array => array_map('intval', $pdo->query('SELECT status FROM '.$pre.'comment WHERE modul = \'help\' AND cid = '.$id.' ORDER BY id')
        ->fetchAll(PDO::FETCH_COLUMN));
    $since = function (int $was) use ($mails): array {
        $list = array_slice($mails(), $was);
        $to = array_column($list, 'email');
        sort($to);
        return ['to' => $to, 'text' => implode(' ', array_column($list, 'body'))];
    };
    $open = function (string $who, string $title) use ($pdo, $pre): array {
        $tok = getRouteToken(getRouteReply($who, 'GET', 'index.php?name=help&op=add')['body'], 'name="action"');
        $made = getRouteReply($who, 'POST', 'index.php?name=help&op=add', ['title' => $title, 'intro' => $title.' private intro', 'body' => $title.' private body', 'cid' => '3',
            'action' => 'submit', 'token' => $tok]);
        return [$made['code'], (int)$pdo->query('SELECT id FROM '.$pre.'nodes WHERE title = '.$pdo->quote($title))->fetchColumn()];
    };
    $ctok = fn(string $html): string => preg_match('#id="formcsave".*?name="token"\s+value="([a-f0-9]{64})"#s', $html, $hit) ? $hit[1] : '';
    $reply = function (string $who, int $id, string $text) use ($ctok) {
        $page = getRouteReply($who, 'GET', 'index.php?name=help&op=view&id='.$id);
        $tok = $ctok($page['body']) ?: getRouteToken($page['body'], 'op=support');
        return getRouteReply($who, 'POST', 'index.php?go=1&op=addComment&id='.$id.'&mod=help&com=1', ['text' => $text, 'name' => '', 'token' => $tok]);
    };
    $out = ['guest' => [$code('', 'index.php?name=help'), $code('', 'index.php?name=help&op=add'), $code('', 'index.php?name=help&op=view&id=1')]];
    $was = count($mails());
    [$made, $aid] = $open('anna', 'Anna request');
    $new = $since($was);
    $out['open'] = [$made, (int)getRouteCol($pdo, $aid, 'status'), (int)getRouteCol($pdo, $aid, 'comon'), $card($aid), $new['to'], str_contains($new['text'], 'Anna request'),
        str_contains($new['text'], 'private'), str_contains($new['text'], 'op=view&amp;id='.$aid)];
    [, $bid] = $open('boris', 'Boris request');
    $list = fn(string $who): string => getRouteReply($who, 'GET', 'index.php?name=help')['body'];
    $out['lists'] = [
        'anna' => [str_contains($list('anna'), 'Anna request'), str_contains($list('anna'), 'Boris request')],
        'boris' => [str_contains($list('boris'), 'Anna request'), str_contains($list('boris'), 'Boris request')],
        'helper' => [str_contains($list('helper'), 'Anna request'), str_contains($list('helper'), 'Boris request')],
        'robots' => str_contains($list('anna'), 'content="noindex, nofollow"'),
        'chip' => str_contains($list('anna'), 'bi-life-preserver'),
    ];
    $page = getRouteReply('anna', 'GET', 'index.php?name=help&op=view&id='.$aid);
    $out['view'] = [$page['code'], str_contains($page['body'], 'name="state" value="2"'), str_contains($page['body'], 'id="formcsave"'), str_contains($page['body'],
        'content="noindex, nofollow"'),
        $code('boris', 'index.php?name=help&op=view&id='.$aid), $code('moder', 'index.php?name=help&op=view&id='.$aid), $code('helper', 'index.php?name=help&op=view&id='.$aid),
        str_contains(getRouteReply('helper', 'GET', 'index.php?name=help&op=view&id='.$aid)['body'], 'name="state" value="2"')];
    $was = count($mails());
    $one = $reply('anna', $aid, 'Anna reply one');
    $new = $since($was);
    $cid = (int)$pdo->query('SELECT MAX(id) FROM '.$pre.'comment WHERE modul = \'help\'')->fetchColumn();
    $pts = $pdo->query('SELECT action, scope, source FROM '.$pre.'points WHERE uid = 2 AND action = \'comment\' ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $out['owner'] = [$one['code'], $rows($aid), (int)getRouteCol($pdo, $aid, 'comnum'), $card($aid), $new['to'], str_contains($new['text'], 'Anna reply one'), $pts];
    $was = count($mails());
    $two = $reply('helper', $aid, 'Helper reply one');
    $new = $since($was);
    $hid = (int)$pdo->query('SELECT MAX(id) FROM '.$pre.'comment WHERE modul = \'help\'')->fetchColumn();
    $out['staff'] = [$two['code'], $rows($aid), (int)getRouteCol($pdo, $aid, 'comnum'), $card($aid), $new['to'], str_contains($new['text'], 'Helper reply one')];
    $btok = $ctok(getRouteReply('boris', 'GET', 'index.php?name=help&op=view&id='.$bid)['body']);
    $steal = getRouteReply('boris', 'POST', 'index.php?go=1&op=addComment&id='.$aid.'&mod=help&com=1', ['text' => 'Boris intrudes', 'name' => '', 'token' => $btok]);
    $out['foreign'] = [$steal['code'], $rows($aid), (int)getRouteCol($pdo, $aid, 'comnum')];
    $atok = $ctok(getRouteReply('anna', 'GET', 'index.php?name=help&op=view&id='.$aid)['body']);
    $frag = fn(string $who, string $tok, string $op): string => getRouteReply($who, 'GET', 'index.php?go=1&op='.$op.'&id='.(($op === 'getCommentPage') ? $aid.'&mod=help&com=1'
        : $cid.'&skip=0').'&token='.$tok)['body'];
    $out['fragments'] = [str_contains($frag('anna', $atok, 'getCommentPage'), 'Anna reply one'), str_contains($frag('boris', $btok, 'getCommentPage'), 'Anna reply one'),
        str_contains($frag('boris', $btok, 'getCommentBranch'), 'reply'), str_contains($frag('boris', $btok, 'getCommentPage'), 'Helper reply one')];
    $prof = fn(string $who): string => getRouteReply($who, 'GET', 'index.php?name=account&op=view&uname=anna')['body'];
    $out['profile'] = [str_contains($prof('boris'), 'Anna reply one'), str_contains($prof('anna'), 'Anna reply one'), str_contains($prof('helper'), 'Anna reply one')];
    $page = getRouteReply('anna', 'GET', 'index.php?name=help&op=view&id='.$aid);
    $tok = getRouteToken($page['body'], 'op=support');
    $ver = getRouteField($page['body'], 'version');
    $before = $when($aid);
    sleep(1);
    $was = count($mails());
    $shut = getRouteReply('anna', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $tok, 'version' => $ver, 'state' => '2']);
    $out['close'] = [$shut['code'], $card($aid), $when($aid) !== $before, count($mails()) - $was];
    $out['closed'] = [
        getRouteReply('anna', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $tok, 'version' => $ver, 'state' => '2'])['code'],
        getRouteReply('anna', 'GET', 'index.php?name=help&op=support&id='.$aid)['code'],
        getRouteReply('anna', 'POST', 'index.php?name=help&op=support&id='.$aid, ['version' => (string)$card($aid)['version'], 'state' => '0'])['code'],
        getRouteReply('boris', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $btok, 'version' => (string)$card($aid)['version'], 'state' => '0'])['code'],
        getRouteReply('anna', 'POST', 'index.php?name=news&op=support&id=101', ['token' => $tok, 'version' => '1', 'state' => '0'])['code'],
    ];
    $late = $reply('anna', $aid, 'Anna after close');
    $shown = getRouteReply('anna', 'GET', 'index.php?name=help&op=view&id='.$aid)['body'];
    $out['locked'] = [$late['code'], $rows($aid), str_contains($shown, 'id="formcsave"'), str_contains($shown, 'Anna reply one'), str_contains($shown, 'name="state" value="0"')];
    $out['author'] = getRouteReply('anna', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $tok, 'version' => (string)$card($aid)['version'],
        'state' => '1'])['code'];
    $again = getRouteReply('anna', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $tok, 'version' => (string)$card($aid)['version'], 'state' => '0']);
    $out['reopen'] = [$again['code'], $card($aid)];
    $htok = $ctok(getRouteReply('helper', 'GET', 'index.php?name=help&op=view&id='.$aid)['body']);
    $staff = getRouteReply('helper', 'POST', 'index.php?name=help&op=support&id='.$aid, ['token' => $htok, 'version' => (string)$card($aid)['version'], 'state' => '1']);
    $out['staffswitch'] = [$staff['code'], $card($aid)];
    $desk = getRouteReply('helper', 'GET', 'admin.php?name=node&op=support&id='.$aid);
    $out['card'] = [$desk['code'], str_contains($desk['body'], 'name="aid"'), str_contains($desk['body'], 'Anna reply one'),
        str_contains($desk['body'], 'Anna request private body'), $code('moder', 'admin.php?name=node&op=support&id='.$aid),
        $code('docsman', 'admin.php?name=node&op=support&id='.$aid), $code('helper', 'admin.php?name=node&op=support&id=101')];
    $tok = getRouteToken($desk['body'], 'support');
    $ver = getRouteField($desk['body'], 'version');
    $before = $when($aid);
    $was = count($mails());
    $post = ['name' => 'node', 'op' => 'support', 'id' => (string)$aid, 'token' => $tok, 'version' => $ver, 'state' => '0', 'prio' => '3', 'aid' => '5'];
    $set = getRouteReply('helper', 'POST', 'admin.php', $post);
    $out['assign'] = [$set['code'], $card($aid), $when($aid) === $before, count($mails()) - $was];
    $out['refuse'] = [getRouteReply('helper', 'POST', 'admin.php', array_replace($post, ['version' => (string)$card($aid)['version'], 'aid' => '2']))['code'], $card($aid),
        getRouteReply('helper', 'POST', 'admin.php', $post)['code'], getRouteReply('helper', 'POST', 'admin.php', array_replace($post, ['token' => '']))['code'], $card($aid)];
    $queue = fn(string $query): string => getRouteReply('helper', 'GET', 'admin.php?name=node&type=help'.$query)['body'];
    $full = $queue('');
    $out['queue'] = [
        'both' => [str_contains($full, 'Anna request'), str_contains($full, 'Boris request'), strpos($full, 'Anna request') < strpos($full, 'Boris request')],
        'closed' => str_contains($queue('&state=2'), 'Anna request'),
        'mine' => [str_contains($queue('&state=all&aid=5'), 'Anna request'), str_contains($queue('&state=all&aid=5'), 'Boris request')],
        'free' => [str_contains($queue('&state=all&aid=0'), 'Anna request'), str_contains($queue('&state=all&aid=0'), 'Boris request')],
        'bad' => getRouteReply('helper', 'GET', 'admin.php?name=node&type=help&state=abc')['code'],
        'moder' => $code('moder', 'admin.php?name=node&type=help'),
        'single' => $code('helper', 'admin.php?name=node'),
    ];
    $was = count($mails());
    $reply('anna', $aid, 'Anna reply two');
    $out['assigned'] = [$since($was)['to'], $card($aid)];
    $admin = getRouteReply('root', 'GET', 'admin.php?name=comments');
    $htok = $ctok(getRouteReply('helper', 'GET', 'index.php?name=help&op=view&id='.$aid)['body']);
    $hide = getRouteReply('helper', 'POST', 'index.php?go=1&op=updateCommentStatus&id='.$cid.'&typ=0&numb=1', ['token' => $htok]);
    $out['hide'] = [$hide['code'], (int)getRouteCol($pdo, $aid, 'comnum'), $card($aid)['state']];
    $show = getRouteReply('helper', 'POST', 'index.php?go=1&op=updateCommentStatus&id='.$cid.'&typ=1&numb=1', ['token' => $htok]);
    $out['show'] = [$show['code'], (int)getRouteCol($pdo, $aid, 'comnum'), $card($aid)['state']];
    $pdo->exec('INSERT INTO '.$pre.'comment (cid, modul, time, uid, name, ip, body, status) VALUES ('.$bid.', \'help\', NOW(), 3, \'boris\', \'127.0.0.1\', \'Boris pending\', 0)');
    $pid = (int)$pdo->lastInsertId();
    $turn = fn(int $typ): int => getRouteReply('helper', 'POST', 'index.php?go=1&op=updateCommentStatus&id='.$pid.'&typ='.$typ.'&numb=1', ['token' => $htok])['code'];
    $first = $card($bid);
    $was = count($mails());
    $okay = $turn(1);
    $new = $since($was);
    $out['approve'] = [$okay, $first['state'], $card($bid)['state'], $card($bid)['version'] - $first['version'], $new['to'],
        (int)$pdo->query('SELECT shown IS NOT NULL FROM '.$pre.'comment WHERE id = '.$pid)->fetchColumn()];
    $mid = $card($bid);
    $was = count($mails());
    $out['reapprove'] = [$turn(0), $turn(1), count($mails()) - $was, $card($bid) === $mid];
    $pdo->exec('DELETE FROM '.$pre.'comment WHERE id = '.$pid);
    $gone = getRouteReply('helper', 'POST', 'index.php?go=1&op=deleteComment&id='.$cid, ['token' => $htok]);
    $pts = $pdo->query('SELECT COUNT(*) FROM '.$pre.'points WHERE uid = 2 AND action = \'comment\' AND rid > 0')->fetchColumn();
    $out['delete'] = [$gone['code'], (int)getRouteCol($pdo, $aid, 'comnum'), (int)$pts];
    $reply('helper', $aid, 'Helper reply two');
    $sql = 'SELECT uid, aid, scope, source, mid, rid FROM '.$pre.'points WHERE action = \'moderate\' ORDER BY id';
    $pick = fn(array $v): array => [(int)$v['uid'], (int)$v['aid'], $v['scope'], $v['source'], (int)$v['mid'], $v['rid'] === null];
    $out['moderate'] = [array_map($pick, $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC)), $hid, $pid, $aid, $bid];
    $out['adminlist'] = [$admin['code'], str_contains($admin['body'], 'Anna reply')];
    $out['children'] = [];
    foreach (['docsman', 'helper', 'boris', 'anna'] as $who) {
        $raw = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['rwork']).' comments '.$who.' 2>&1');
        $out['children'][$who] = json_decode($raw, true) ?? $raw;
    }
    $out['ext'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['rwork']).' ext 2>&1'), true);
    $out['ids'] = [$aid, $bid, $cid];
    return $out;
}

# The two types of the extension sync for its own run: content, active, and feeds, disabled, with their upload folders, rating rules and the scheduler switched on
function addRouteSyncTypes(PDO $pdo, string $work): void {
    $pdo->exec('INSERT INTO '.RPREF.'_node_types (id, name, title, intro, ext, active, sort, version) VALUES (5, \'content\', \'Content\', \'\', \'sync\', 1, 50, 1),'
        .' (6, \'feeds\', \'Feeds\', \'\', \'sync\', 0, 60, 1)');
    $data = require $work.'/config/node.php';
    foreach (['content', 'feeds'] as $name) $data['node']['types'][$name] = ['version' => 1, 'features' => getRouteFeatures([])];
    setRouteFile($work.'/config/node.php', $data);
    $data = require $work.'/config/uploads.php';
    $rate = require $work.'/config/ratings.php';
    foreach (['content', 'feeds'] as $name) {
        $data['uploads'][$name] = $data['uploads']['all'];
        $rate['ratings']['node.'.$name] = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];
        mkdir($work.'/uploads/node/'.$name.'/thumb', 0777, true);
    }
    setRouteFile($work.'/config/uploads.php', $data);
    setRouteFile($work.'/config/ratings.php', $rate);
    $data = require $work.'/config/scheduler.php';
    $data['scheduler']['active'] = '1';
    setRouteFile($work.'/config/scheduler.php', $data);
}

# One source row of the probe as whole numbers and texts; the due time is measured from the last check (gap) and from the last change of the material (lag)
# Both are written by the clock of the site, because the session of the probe may run in another time zone than the connection of the site
function getRouteSource(PDO $pdo, int $nid): array {
    $sql = 'SELECT s.url, s.refresh, s.etag, s.modified, s.fails, s.error, s.checked IS NOT NULL AS seen, s.synced IS NOT NULL AS done, s.due IS NULL AS never,'
        .' TIMESTAMPDIFF(SECOND, s.checked, s.due) AS gap, TIMESTAMPDIFF(SECOND, n.updated, s.due) AS lag FROM '.RPREF.'_node_sync AS s'
        .' INNER JOIN '.RPREF.'_nodes AS n ON n.id = s.nid WHERE s.nid = '.$nid;
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [];
    foreach (['refresh', 'fails', 'seen', 'done', 'never'] as $key) $row[$key] = (int)$row[$key];
    foreach (['gap', 'lag'] as $key) $row[$key] = ($row[$key] === null) ? null : (int)$row[$key];
    return $row;
}

# The external materials over real HTTP: the administrative form takes a source instead of a body, a new period keeps the validators and a new address drops them
# The manual check refuses what it must and keeps the text on a failure, the public page never shows the source, the scheduler carries and runs nodesync with its limit
# The child mode syncext then drives NodeSync with a scripted transport, and the page cache has to drop the old text of a material once the child changed it
function getRouteSync(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $count = fn(): int => (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.'nodes WHERE tid = 5')->fetchColumn();
    $idof = fn(string $title): int => (int)$pdo->query('SELECT id FROM '.$pre.'nodes WHERE title = '.$pdo->quote($title))->fetchColumn();
    $form = getRouteReply('root', 'GET', 'admin.php?name=node&op=add&type=content');
    $tok = getRouteToken($form['body'], 'add');
    $out = ['form' => [$form['code'], str_contains($form['body'], 'name="source"'), str_contains($form['body'], 'name="refresh"'), str_contains($form['body'], 'name="body"')]];
    $base = ['name' => 'node', 'op' => 'add', 'type' => 'content', 'token' => $tok, 'status' => '2', 'intro' => 'Own intro', 'body' => 'Injected body'];
    $made = getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Feed one', 'source' => ' https://Example.COM:443/feed.xml#top ', 'refresh' => '600']);
    $aid = $idof('Feed one');
    $out['add'] = [$made['code'], (string)getRouteCol($pdo, $aid, 'body'), (string)getRouteCol($pdo, $aid, 'intro'), (int)getRouteCol($pdo, $aid, 'status'),
        getRouteSource($pdo, $aid)];
    $was = $count();
    $late = getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Bad period', 'source' => 'https://example.com/a.xml', 'refresh' => '299']);
    $label = preg_match('#<label[^>]*for="f-refresh"[^>]*>\s*([^<]+?)\s*<#', $form['body'], $hit) ? html_entity_decode($hit[1]) : '';
    $out['named'] = [$label !== '', str_contains(html_entity_decode($late['body']), '('.$label.')')];
    $out['bad'] = [
        $late['code'],
        getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Bad scheme', 'source' => 'ftp://example.com/a.xml', 'refresh' => '600'])['code'],
        getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Bad user', 'source' => 'https://me:pw@example.com/a.xml', 'refresh' => '600'])['code'],
        getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'No source', 'refresh' => '600'])['code'],
        getRouteReply('moder', 'POST', 'admin.php', $base + ['title' => 'Foreign', 'source' => 'https://example.com/a.xml', 'refresh' => '600'])['code'],
        $count() - $was,
    ];
    getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Feed two', 'source' => 'http://127.0.0.1/two.xml', 'refresh' => '300']);
    getRouteReply('root', 'POST', 'admin.php', $base + ['title' => 'Feed manual', 'source' => 'https://example.com/manual.xml', 'refresh' => '0']);
    $bid = $idof('Feed two');
    $mid = $idof('Feed manual');
    $out['manual'] = getRouteSource($pdo, $mid);
    $pdo->exec('UPDATE '.$pre.'node_sync SET etag = \'"v1"\', modified = \'Mon, 01 Jan 2024 00:00:00 GMT\', checked = NOW() - INTERVAL 100 SECOND,'
        .' synced = NOW() - INTERVAL 100 SECOND WHERE nid = '.$aid);
    $page = getRouteReply('root', 'GET', 'admin.php?name=node&op=edit&id='.$aid.'&type=content');
    $flat = (string)preg_replace('/\s+/', ' ', $page['body']);
    $out['edit'] = [$page['code'], str_contains($flat, 'name="op" value="sync"'), str_contains($flat, 'value="https://example.com/feed.xml"'), str_contains($flat, 'name="body"')];
    $tok = getRouteToken($page['body'], 'edit');
    $post = ['name' => 'node', 'op' => 'edit', 'id' => (string)$aid, 'type' => 'content', 'token' => $tok, 'action' => 'save', 'title' => 'Feed one', 'intro' => 'Own intro',
        'body' => 'Hacked body', 'source' => 'https://example.com/feed.xml'];
    $one = getRouteReply('root', 'POST', 'admin.php', $post + ['version' => getRouteField($page['body'], 'version'), 'refresh' => '1200']);
    $out['period'] = [$one['code'], (string)getRouteCol($pdo, $aid, 'body'), getRouteSource($pdo, $aid)];
    $page = getRouteReply('root', 'GET', 'admin.php?name=node&op=edit&id='.$aid.'&type=content');
    $two = getRouteReply('root', 'POST', 'admin.php', array_replace($post, ['version' => getRouteField($page['body'], 'version'), 'refresh' => '1200',
        'source' => 'https://example.org/other.xml']));
    $out['address'] = [$two['code'], getRouteSource($pdo, $aid)];
    $page = getRouteReply('root', 'GET', 'admin.php?name=node&op=edit&id='.$aid.'&type=content');
    $stok = getRouteToken($page['body'], 'sync');
    $pdo->exec('UPDATE '.$pre.'node_sync SET url = \'https://127.0.0.1/feed.xml\' WHERE nid = '.$aid);
    $pdo->exec('UPDATE '.$pre.'nodes SET body = \'Kept body\' WHERE id = '.$aid);
    $sync = ['name' => 'node', 'op' => 'sync', 'id' => (string)$aid, 'type' => 'content', 'token' => $stok];
    $ver = (int)getRouteCol($pdo, $aid, 'version');
    $mtok = getRouteToken(getRouteReply('moder', 'GET', 'admin.php?name=node&status=2')['body'], 'status');
    $btok = getRouteToken(getRouteReply('boss', 'GET', 'admin.php?name=node&op=types')['body'], 'typestatus');
    $out['refuse'] = [$code('root', 'admin.php?name=node&op=sync&id='.$aid.'&type=content'),
        getRouteReply('root', 'POST', 'admin.php', array_replace($sync, ['token' => '']))['code'],
        getRouteReply('root', 'POST', 'admin.php', array_replace($sync, ['id' => '101', 'type' => 'news']))['code'],
        getRouteReply('moder', 'POST', 'admin.php', array_replace($sync, ['token' => $mtok]))['code'],
        getRouteReply('boss', 'POST', 'admin.php', array_replace($sync, ['token' => $btok]))['code'], $mtok !== '' && $btok !== '', getRouteSource($pdo, $aid)['fails']];
    $fail = getRouteReply('root', 'POST', 'admin.php', $sync + ['task' => '1', 'super' => '1']);
    $first = getRouteSource($pdo, $aid);
    $again = getRouteReply('root', 'POST', 'admin.php', $sync);
    $out['fail'] = [$fail['code'], str_contains($fail['body'], '(address)'), (string)getRouteCol($pdo, $aid, 'body'), (int)getRouteCol($pdo, $aid, 'version') - $ver, $first,
        $again['code'], getRouteSource($pdo, $aid)];
    $view = getRouteReply('', 'GET', 'index.php?name=content&op=view&id='.$aid);
    $out['public'] = [$view['code'], str_contains($view['body'], 'Kept body'), str_contains($view['body'], '127.0.0.1/feed')];
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published) VALUES'
        .' (501, 5, 0, 0, \'\', \'127.0.0.1\', \'Trashed feed\', \'\', \'\', \'\', 4, NOW()), (601, 6, 0, 0, \'\', \'127.0.0.1\', \'Off feed\', \'\', \'\', \'\', 2, NOW())');
    $pdo->exec('INSERT INTO '.$pre.'node_sync (nid, url, refresh, due) VALUES (501, \'http://127.0.0.1/trash.xml\', 300, NOW() - INTERVAL 1 HOUR),'
        .' (601, \'http://127.0.0.1/off.xml\', 300, NOW() - INTERVAL 1 HOUR)');
    $list = getRouteReply('root', 'GET', 'admin.php?name=scheduler');
    $job = getRouteReply('root', 'GET', 'admin.php?name=scheduler&op=add&job=nodesync');
    $pub = getRouteReply('root', 'GET', 'admin.php?name=scheduler&op=add&job=nodepublish');
    $jtok = getRouteToken($job['body'], 'save');
    $save = ['name' => 'scheduler', 'op' => 'save', 'job' => 'nodesync', 'type' => 'system', 'token' => $jtok, 'title' => 'Node sync', 'schedule' => '*/5 * * * *',
        'priority' => '7', 'lock_timeout' => '180', 'active' => '1', 'manual' => '1'];
    $high = getRouteReply('root', 'POST', 'admin.php', $save + ['limit' => '51']);
    $cfg = require $work.'/config/scheduler.php';
    $keep = $cfg['scheduler']['jobs']['nodesync']['settings']['limit'] ?? '';
    $good = getRouteReply('root', 'POST', 'admin.php', $save + ['limit' => '20']);
    $cfg = require $work.'/config/scheduler.php';
    $out['scheduler'] = [$list['code'], str_contains($list['body'], 'Node sync'), str_contains($job['body'], 'name="limit"'), str_contains($job['body'], 'max="50"'),
        str_contains($pub['body'], 'max="500"'), $high['code'], $keep, $good['code'], $cfg['scheduler']['jobs']['nodesync']['settings']['limit'] ?? '',
        $cfg['scheduler']['jobs']['nodesync']['system'] ?? ''];
    $rtok = getRouteToken($list['body'], 'run');
    $run = getRouteReply('root', 'POST', 'admin.php', ['name' => 'scheduler', 'op' => 'run', 'job' => 'nodesync', 'token' => $rtok]);
    $file = $work.'/logs/scheduler/nodesync.json';
    $state = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $out['run'] = [$run['code'], getRouteSource($pdo, $bid), getRouteSource($pdo, $aid)['fails'], getRouteSource($pdo, 501)['fails'], getRouteSource($pdo, 601)['fails'],
        getRouteSource($pdo, $mid)['fails'], $state['last_status'] ?? '', $state['last_message'] ?? ''];
    $pdo->exec('UPDATE '.$pre.'node_sync SET url = \'https://example.com/feed.xml\', etag = \'\', modified = \'\', fails = 0, error = \'\' WHERE nid = '.$aid);
    $before = getRouteReply('', 'GET', 'index.php?name=content');
    $pdo->exec('UPDATE '.$pre.'nodes SET intro = '.$pdo->quote('Quiet intro').' WHERE id = '.$aid);
    $after = getRouteReply('', 'GET', 'index.php?name=content');
    $out['ext'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($work).' syncext 2>&1'), true);
    $out['live'] = [str_contains($before['body'], 'Own intro'), str_contains($after['body'], 'Quiet intro'),
        str_contains(getRouteReply('', 'GET', 'index.php?name=content&op=view&id='.$aid)['body'], 'Hello feed')];
    $out['ids'] = [$aid, $bid, $mid];
    return $out;
}

# NodeSync asked directly in a child process with explicit contexts and a scripted transport of Feed: the input it accepts, what each reader gets, the background queue
# The results of a fetch are new text, 304, the same text, an error, a concurrent change of either side and a text the column cannot hold
function getRouteSyncData(): array {
    global $db, $conf, $fld, $pnt;
    require_once BASE_DIR.'/core/classes/node/ext/load.php';
    $root = new NodeContext(0, [], 1, [], true, true, '127.0.0.1', '');
    $moder = new NodeContext(0, [], 2, ['news'], false, false, '127.0.0.1', '');
    $anna = new NodeContext(2, [1], 0, [], false, false, '127.0.0.1', '');
    $task = new NodeContext(0, [], 0, [], false, false, '', '', true);
    $col = fn(int $id, string $name): string => (string)$db->getSqlQuery('SELECT '.$name.' FROM '.PREFIX_DB.'_nodes WHERE id = :id', ['id' => $id])->fetchColumn();
    $src = fn(int $id): array => $db->getSqlQuery('SELECT url, etag, modified, fails, error, checked IS NOT NULL AS seen, synced IS NOT NULL AS done,'
        .' TIMESTAMPDIFF(SECOND, checked, due) AS gap FROM '.PREFIX_DB.'_node_sync WHERE nid = :id', ['id' => $id])->fetch(PDO::FETCH_ASSOC) ?: [];
    $aid = (int)$db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_nodes WHERE title = \'Feed one\'')->fetchColumn();
    $bid = (int)$db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_nodes WHERE title = \'Feed two\'')->fetchColumn();
    $mid = (int)$db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_nodes WHERE title = \'Feed manual\'')->fetchColumn();
    $type = (new NodeQuery($db, $root, $fld))->getNodeType('content');
    $gets = [];
    $replies = [];
    $send = function (string $op, array $req) use (&$gets, &$replies): array {
        if ($op === 'resolve') return ['addresses' => ['93.184.216.34']];
        $gets[] = $req;
        $one = array_shift($replies) ?? ['code' => 500, 'headers' => [], 'body' => ''];
        return ($one instanceof Closure) ? $one($req) : $one;
    };
    $rss = fn(string $title, array $head = []): array => ['code' => 200, 'headers' => $head + ['Content-Type' => ['application/rss+xml']], 'body' => '<?xml version="1.0"?>'
        .'<rss version="2.0"><channel><title>C</title><item><title>'.$title.'</title><link>https://example.com/1</link></item></channel></rss>'];
    $ext = new NodeSync($db, $root, new Feed($conf['rss'], $send));
    $out = ['config' => [getRouteCall(fn() => $ext->filterNodeConfig([], $type->settings, [])), getRouteCall(fn() => $ext->filterNodeConfig(['x' => 1], $type->settings, [])),
        getRouteCall(fn() => $ext->filterNodeConfig([], array_replace_recursive($type->settings, ['features' => ['submit' => true]]), []))]];
    $data = fn(array $in): array => getRouteCall(fn() => $ext->filterNodeData($type, $in));
    $out['input'] = [
        'good' => $data(['refresh' => 0, 'url' => ' https://EXAMPLE.com:443/a|b?q=1#top ']),
        'intl' => $data(['url' => 'https://example.com/feed', 'refresh' => 31536000]),
        'low' => $data(['url' => 'https://example.com/feed', 'refresh' => 299]),
        'high' => $data(['url' => 'https://example.com/feed', 'refresh' => 31536001]),
        'text' => $data(['url' => 'https://example.com/feed', 'refresh' => '600']),
        'lost' => $data(['url' => 'https://example.com/feed']),
        'extra' => $data(['url' => 'https://example.com/feed', 'refresh' => 600, 'headers' => []]),
        'port' => $data(['url' => 'https://example.com:8443/feed', 'refresh' => 600]),
        'long' => $data(['url' => 'https://example.com/'.str_repeat('a', 2100), 'refresh' => 600]),
        'anna' => getRouteCall(fn() => (new NodeSync($db, $anna, new Feed($conf['rss'], $send)))->filterNodeData($type, ['url' => 'https://example.com/f', 'refresh' => 600])),
    ];
    $out['scope'] = [$ext->getNodeScope($type), getRouteCall(fn() => $ext->checkNodeAction($type, (new NodeQuery($db, $root, $fld))->getNodeTarget('content', $aid), 'comment')),
        getRouteCall(fn() => $ext->checkNodeAction($type, (new NodeQuery($db, $root, $fld))->getNodeTarget('content', $aid), 'vote'))];
    $node = (new NodeQuery($db, $root, $fld))->setNodeExtension($ext)->getNode($aid, $type);
    $out['data'] = [array_keys($ext->getNodeData($type, [$node], 'admin')[$aid] ?? []), $ext->getNodeData($type, [$node], 'view'),
        (new NodeSync($db, $moder, new Feed($conf['rss'], $send)))->getNodeData($type, [$node], 'admin')];
    $ver = (int)$col($aid, 'version');
    $replies = [$rss('Hello feed', ['ETag' => ['"e1"'], 'Last-Modified' => ['Tue, 02 Jan 2024 00:00:00 GMT']])];
    $res = $ext->updateNodeSync($aid);
    $out['new'] = [$res, str_contains($col($aid, 'body'), '## Hello feed'), (int)$col($aid, 'version') - $ver, $src($aid),
        isset($gets[0]['headers']['If-None-Match'])];
    $body = $col($aid, 'body');
    $ver = (int)$col($aid, 'version');
    $replies = [['code' => 304, 'headers' => [], 'body' => '']];
    $gets = [];
    $out['same'] = [$ext->updateNodeSync($aid), $gets[0]['headers']['If-None-Match'] ?? '', $gets[0]['headers']['If-Modified-Since'] ?? '', $col($aid, 'body') === $body,
        (int)$col($aid, 'version') - $ver, $src($aid)['etag']];
    $replies = [$rss('Hello feed', ['ETag' => ['"e2"']])];
    $out['equal'] = [$ext->updateNodeSync($aid), (int)$col($aid, 'version') - $ver, $src($aid)['etag']];
    $replies = [['code' => 500, 'headers' => [], 'body' => 'oops'], ['code' => 200, 'headers' => ['Content-Type' => ['application/rss+xml']], 'body' => '<rss><broken']];
    $one = $ext->updateNodeSync($aid);
    $first = $src($aid);
    $two = $ext->updateNodeSync($aid);
    $out['error'] = [$one, $first, $two, $src($aid), $col($aid, 'body') === $body, (int)$col($aid, 'version') - $ver];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_node_sync SET fails = 0, error = \'\' WHERE nid = :id', ['id' => $aid]);
    $replies = [function (array $req) use ($db, $aid, $rss): array {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_nodes SET version = version + 1 WHERE id = :id', ['id' => $aid]);
        return $rss('Stale text');
    }, function (array $req) use ($db, $aid, $rss): array {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_node_sync SET url = \'https://example.net/moved.xml\' WHERE nid = :id', ['id' => $aid]);
        return ['code' => 500, 'headers' => [], 'body' => ''];
    }];
    $out['race'] = [$ext->updateNodeSync($aid), str_contains($col($aid, 'body'), 'Stale text'), $ext->updateNodeSync($aid), $src($aid)];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_node_sync SET url = \'https://example.com/feed.xml\' WHERE nid = :id', ['id' => $aid]);
    $snap = ['nid' => $aid, 'url' => 'https://example.com/feed.xml', 'etag' => '', 'modified' => '', 'body' => $col($aid, 'body'), 'version' => (int)$col($aid, 'version'),
        'status' => 2, 'name' => 'content', 'ext' => 'sync'];
    $huge = ['ok' => true, 'changed' => true, 'code' => 200, 'body' => str_repeat('a', 16777216), 'etag' => '', 'modified' => '', 'error' => ''];
    $call = (new ReflectionMethod(NodeSync::class, 'setSourceResult'))->invoke($ext, $snap, $huge);
    $out['huge'] = [$call, $col($aid, 'body') === $body];
    $lost = new class($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name']) extends Database {
        # Refuse the write of the source row that follows the new text, so the result fails inside its open transaction
        public function getSqlQuery(string $query = '', array $params = []): PDOStatement|false {
            return str_starts_with($query, 'UPDATE '.PREFIX_DB.'_node_sync') ? false : parent::getSqlQuery($query, $params);
        }
        # Roll back for real and answer as a connection whose rollback is not proven would, which leaves the outcome unknown
        public function setSqlRollback(): bool {
            parent::setSqlRollback();
            return false;
        }
    };
    $new = ['ok' => true, 'changed' => true, 'code' => 200, 'body' => 'Lost text', 'etag' => '', 'modified' => '', 'error' => ''];
    $call = (new ReflectionMethod(NodeSync::class, 'setSourceResult'))->invoke(new NodeSync($lost, $root, new Feed($conf['rss'], $send)), $snap, $new);
    $out['undo'] = [$call, $col($aid, 'body') === $body];
    $out['refused'] = [getRouteCall(fn() => (new NodeSync($db, $task, new Feed($conf['rss'], $send)))->updateNodeSync($aid)),
        getRouteCall(fn() => (new NodeSync($db, $anna, new Feed($conf['rss'], $send)))->updateNodeSync($aid)), getRouteCall(fn() => $ext->updateNodeSync(101)),
        getRouteCall(fn() => $ext->updateNodeSync(999999)), getRouteCall(fn() => $ext->updateNodeSyncList(10))];
    $queue = new NodeSync($db, $task, new Feed($conf['rss'], $send));
    $out['limits'] = [getRouteCall(fn() => $queue->updateNodeSyncList(0)), getRouteCall(fn() => $queue->updateNodeSyncList(51))];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_node_sync SET due = NOW() - INTERVAL 2 HOUR WHERE nid IN (:a, :b, :c, :d)', ['a' => $bid, 'b' => 501, 'c' => 601, 'd' => $aid]);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_node_sync SET due = NOW() - INTERVAL 3 HOUR, url = \'https://example.com/two.xml\' WHERE nid = :b', ['b' => $bid]);
    $gets = [];
    $replies = [$rss('Two text'), $rss('Hello feed')];
    $list = $queue->updateNodeSyncList(50);
    $out['queue'] = [$list, array_column($gets, 'url'), str_contains($col($bid, 'body'), 'Two text'), $src($mid)['seen'], $src(501)['seen'], $src(601)['seen']];
    $svc = new NodeService($db, $root, $fld, $pnt, $ext);
    $count = fn(): int => (int)$db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_node_sync')->fetchColumn();
    $was = $count();
    $input = new NodeInput(0, [], '', 'With body', '', 'Own body', [], 0, false, CommentMode::Disabled, false, null, null, [], [],
        ['url' => 'https://example.com/w', 'refresh' => 600]);
    $out['hooks'] = [getRouteCall(fn() => $svc->addNode($type, $input, NodeStatus::Published)), $count() - $was];
    $one = (new NodeQuery($db, $root, $fld))->setNodeExtension($ext)->getNode($aid, $type);
    $move = new NodeInput(0, [], '', $one->title, $one->intro, 'Changed body', [], 0, false, CommentMode::Disabled, false, $one->pubdate, null, [], [],
        ['url' => 'https://example.com/other.xml', 'refresh' => 600]);
    $out['hooks'][] = getRouteCall(fn() => $svc->updateNode($aid, $move, $one->version));
    $out['hooks'][] = [$src($aid)['url'], $col($aid, 'body') === $one->body];
    return $out;
}

# A type an unfinished configuration operation holds is closed with 503 before any query, an unknown op or method included, and opens again once the marker is gone
function getRouteHold(string $work): array {
    if (!is_dir($work.'/backup/config')) mkdir($work.'/backup/config', 0777, true);
    file_put_contents($work.'/backup/config/marker.json', json_encode(['op' => 'probe-op', 'types' => ['news'], 'files' => []]));
    $one = getRouteReply('', 'GET', 'index.php?name=news');
    $early = [getRouteReply('', 'GET', 'index.php?name=news&op=bogus')['code'], getRouteReply('', 'POST', 'index.php?name=news')['code']];
    unlink($work.'/backup/config/marker.json');
    return [$one['code'], $one['head']['retry-after'] ?? '', str_contains((string)($one['head']['cache-control'] ?? ''), 'no-store'), getRouteReply('', 'GET',
        'index.php?name=news')['code'], $early];
}

# The integrations on two types: news rates, keeps favorites, links a poll, marks home materials and feeds search, RSS, sitemap and blocks
# The type docs feeds search, sitemap and blocks without RSS, rating or favorites; a poll, a home mark and three instances of the file block node.php are seeded
function addRouteIntegTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['types']['news']['features'] = getRouteFeatures(['categories', 'comments', 'submit', 'moderation', 'related', 'rating', 'favorites', 'poll', 'home']);
    $data['node']['types']['news']['integrations'] = ['search' => true, 'rss' => true, 'sitemap' => true, 'blocks' => true, 'seo' => 'news'];
    $data['node']['types']['docs']['integrations'] = ['search' => true, 'rss' => false, 'sitemap' => true, 'blocks' => true, 'seo' => 'article'];
    setRouteFile($work.'/config/node.php', $data);
    $pdo->exec('INSERT INTO '.$pre.'voting (id, modul, title, body, answer, time, enddate, lang, typ, status) VALUES'
        .' (7, \'\', \'Probe poll\', \'Yes|No\', \'0|0\', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 30 DAY, \'\', 1, 1)');
    $pdo->exec('UPDATE '.$pre.'nodes SET poll = 7 WHERE id = 102');
    $pdo->exec('UPDATE '.$pre.'nodes SET home = 1 WHERE id IN (101, 105)');
    $st = $pdo->prepare('INSERT INTO '.$pre.'blocks (id, bkey, title, content, url, bpos, weight, status, refresh, time, lang, bfile, view, expire, action, which, param)'
        .' VALUES (?, \'\', ?, \'\', \'\', \'c\', ?, 1, 0, \'\', \'\', \'node.php\', 0, \'0\', \'d\', \'rss\', ?)');
    $st->execute([50, 'Node home block', 1, '{"type":"news","mode":"home","limit":5}']);
    $st->execute([51, 'Node mixed block', 2, '{"type":"","mode":"last","limit":5}']);
    $st->execute([52, 'Node broken block', 3, '{"type":"off","mode":"last","limit":2}']);
}

# The aggregate of one material and the stored votes and actors of its rating target
function getRouteRate(PDO $pdo, int $id): array {
    $pre = RPREF.'_';
    $cnt = fn(string $tab): int => (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.$tab.' WHERE scope = \'node.news\' AND mid = '.$id)->fetchColumn();
    return [(int)getRouteCol($pdo, $id, 'score'), (int)getRouteCol($pdo, $id, 'ratings'), $cnt('rating_votes'), $cnt('rating_actors')];
}

# The live token a page hands its rating widget, the address of the favorite switch it offers and the header token the switch posts with
function getRoutePageBits(string $html): array {
    $tok = preg_match('#"token": "([A-Za-z0-9]+)"#', $html, $hit) ? $hit[1] : '';
    $fav = preg_match('#hx-post="(index\.php\?go=1&amp;op=addFavorite[^"]+)"#', $html, $hit) ? html_entity_decode($hit[1]) : '';
    $ftok = preg_match('#op=addFavorite[^"]+"[^>]*"X-CSRF-TOKEN": "([A-Za-z0-9]+)"#', $html, $hit) ? $hit[1] : '';
    return [$tok, $fav, $ftok];
}

# Post the favorite switch the way the star does: an empty body and the token in the header
function getRouteFavPost(string $who, string $path, string $tok): array {
    return getRouteReply($who, 'POST', $path, [], ['X-CSRF-TOKEN: '.$tok]);
}

# The shared rating, the favorites, the poll, search, RSS, the blocks and the settings of the integrations over real HTTP, the sitemap in a child
function getRouteInteg(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $out = [];
    $vote = fn(string $who, string $mod, int $id, string $req, string $tok): int => getRouteReply($who, 'POST', 'index.php?go=1&op=getRatingView',
        ['mod' => $mod, 'id' => (string)$id, 'rate' => '4', 'request' => $req, 'typ' => 'stars', 'token' => $tok])['code'];
    $page = getRouteReply('', 'GET', 'index.php?name=news&op=view&id=102');
    [$tok] = getRoutePageBits($page['body']);
    $out['widgets'] = [$page['code'], str_contains($page['body'], 'data-sl-rate'), str_contains($page['body'], 'Probe poll'), str_contains($page['body'], 'addFavorite'),
        $tok !== ''];
    $docs = getRouteReply('', 'GET', 'index.php?name=docs&op=view&id=201')['body'];
    $out['docsview'] = [str_contains($docs, 'data-sl-rate'), str_contains($docs, 'Probe poll')];
    $out['vote'] = [$vote('', 'node.news', 102, str_repeat('a', 32), $tok), getRouteRate($pdo, 102), $vote('', 'node.news', 102, str_repeat('a', 32), $tok),
        getRouteRate($pdo, 102), $vote('', 'node.news', 102, str_repeat('b', 32), $tok), getRouteRate($pdo, 102), (int)getRouteCol($pdo, 102, 'version')];
    $out['refuse'] = [$vote('', 'node.news', 103, str_repeat('c', 32), $tok), $vote('', 'node.news', 104, str_repeat('c', 32), $tok),
        $vote('', 'node.off', 301, str_repeat('c', 32), $tok), $vote('', 'node.docs', 201, str_repeat('c', 32), $tok), $vote('', 'node.nope', 102, str_repeat('c', 32), $tok),
        $vote('', 'node.news', 102, str_repeat('d', 32), 'bad'), getRouteReply('', 'GET', 'index.php?go=1&op=getRatingView')['code']];
    [$atok] = getRoutePageBits(getRouteReply('anna', 'GET', 'index.php?name=news&op=view&id=101')['body']);
    $out['own'] = [$vote('anna', 'node.news', 101, str_repeat('e', 32), $atok), getRouteRate($pdo, 101), $vote('anna', 'node.news', 103, str_repeat('e', 32), $atok),
        getRouteRate($pdo, 103)];
    $pdo->exec('CREATE TRIGGER '.$pre.'probe_rate BEFORE UPDATE ON '.$pre.'nodes FOR EACH ROW BEGIN IF NEW.id = 105 AND NEW.score <> OLD.score THEN'
        .' SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe refuses the aggregate\'; END IF; END');
    $out['rollback'] = [$vote('', 'node.news', 105, str_repeat('f', 32), $tok), getRouteRate($pdo, 105)];
    $pdo->exec('DROP TRIGGER '.$pre.'probe_rate');
    $out['after'] = [$vote('', 'node.news', 105, str_repeat('f', 32), $tok), getRouteRate($pdo, 105)];
    $live = getRouteReply('', 'POST', 'index.php?go=1&op=getRatingView', ['mod' => 'node.news', 'id' => '101', 'rate' => '5', 'request' => str_repeat('9', 32), 'typ' => 'stars',
        'token' => $tok]);
    $out['average'] = [$live['code'], preg_match('#class="sl-urating"[^>]*#', $live['body']) ? '' : 'none',
        preg_match('#<div title="([^"]*)" class="sl-urating"#', $live['body'], $hit) ? $hit[1] : ''];
    $favs = fn(int $uid, int $fid): int => (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.'favorites WHERE uid = '.$uid.' AND fid = '.$fid)->fetchColumn();
    [, $afav, $aftok] = getRoutePageBits(getRouteReply('anna', 'GET', 'index.php?name=news&op=view&id=102')['body']);
    $visit = getRouteReply('anna', 'GET', $afav.'&token='.$aftok);
    $plain = $favs(2, 102);
    $one = getRouteFavPost('anna', $afav, $aftok);
    $docfav = str_replace(['id=102', 'mod=news'], ['id=201', 'mod=docs'], $afav);
    getRouteFavPost('anna', $docfav, $aftok);
    getRouteFavPost('anna', str_replace('id=102', 'id=105', $afav), $aftok);
    [, $bfav, $bftok] = getRoutePageBits(getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=102')['body']);
    getRouteFavPost('boris', str_replace('id=102', 'id=104', $bfav), $bftok);
    $out['fav'] = [$afav !== '', $one['code'], str_contains($one['body'], 'sl-fav-on'), $favs(2, 102), $favs(2, 201), $favs(2, 105), $favs(3, 104), $plain, $visit['code'],
        $visit['head']['allow'] ?? ''];
    $list = fn(): string => getRouteReply('anna', 'GET', 'index.php?name=account&op=favorites')['body'];
    $shown = str_contains($list(), 'Beta');
    $pdo->exec('UPDATE '.$pre.'nodes SET status = 0 WHERE id = 102');
    $hidden = str_contains($list(), 'Beta');
    $pdo->exec('UPDATE '.$pre.'nodes SET status = 2 WHERE id = 102');
    $bare = getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=102')['body'].$list();
    $out['favlist'] = [$shown, $hidden, str_contains(getRouteReply('root', 'GET', 'admin.php?name=favorites')['body'], 'Beta'),
        str_contains($bare, 'op=deleteFavorite') && str_contains($bare, 'op=addFavorite'), (bool)preg_match('#href="[^"]*op=(?:add|delete)Favorite#', $bare)];
    $page = getRouteReply('root', 'GET', 'admin.php?name=node');
    $gone = getRouteReply('root', 'POST', 'admin.php', ['name' => 'node', 'op' => 'delete', 'id' => '105', 'type' => 'news', 'version' => '1',
        'token' => getRouteToken($page['body'], 'delete')]);
    $out['delete'] = [$gone['code'], getRouteCol($pdo, 105, 'id'), $favs(2, 105)];
    $was = (int)getRouteCol($pdo, 102, 'version');
    $page = getRouteReply('root', 'GET', 'admin.php?name=voting');
    $drop = getRouteReply('root', 'POST', 'admin.php', ['name' => 'voting', 'op' => 'delete', 'id' => '7', 'token' => getRouteToken($page['body'], 'delete')]);
    $out['poll'] = [$drop['code'], (int)getRouteCol($pdo, 102, 'poll'), (int)getRouteCol($pdo, 102, 'version') - $was,
        (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.'voting WHERE id = 7')->fetchColumn(),
        str_contains(getRouteReply('', 'GET', 'index.php?name=news&op=view&id=102')['body'], 'Probe poll')];
    $find = fn(string $who, string $query): string => getRouteReply($who, 'GET', 'index.php?name=search&'.$query)['body'];
    $all = $find('', 'word=intro+of');
    $form = $find('', '');
    $out['search'] = [str_contains($all, 'Beta'), str_contains($all, 'Doc one'), str_contains($all, 'Off one'), str_contains($all, 'Members'), str_contains($all, 'Pending one'),
        str_contains($find('anna', 'word=intro+of'), 'Members'), str_contains($find('', 'mod=docs&word=intro+of'), 'Beta'), str_contains($find('', 'word=_ne'), 'Doc one'),
        str_contains($form, 'value="news"'), str_contains($form, 'value="docs"'), str_contains($form, 'value="off"'), str_contains($form, 'value="help"')];
    $rss = getRouteReply('', 'GET', 'index.php?go=rss&name=news');
    $none = getRouteReply('', 'GET', 'index.php?go=rss&name=docs')['body'];
    $pick = getRouteReply('', 'GET', 'index.php?name=rss')['body'];
    $out['rss'] = [$rss['code'], str_contains($rss['body'], '<title>Beta</title>'), str_contains($rss['body'], 'Members'), str_contains($rss['body'], 'Pending one'),
        str_contains($rss['body'], 'op=view&amp;id=102'), str_contains($none, '<item>'), str_contains($pick, 'value="news"'), str_contains($pick, 'value="docs"')];
    $blk = getRouteReply('', 'GET', 'index.php?name=rss')['body'];
    $root = getRouteReply('root', 'GET', 'index.php?name=rss')['body'];
    $cut = fn(string $html, string $title): string => (string)strstr((string)strstr($html, $title), '</ol>', true);
    $out['blocks'] = [str_contains($blk, 'Node home block'), str_contains($cut($blk, 'Node home block'), 'Alpha'), str_contains($cut($blk, 'Node home block'), 'Beta'),
        str_contains($cut($blk, 'Node mixed block'), 'Doc one'), str_contains($cut($blk, 'Node mixed block'), 'Beta'), str_contains($blk, 'Node broken block'),
        str_contains($root, 'Node broken block')];
    $edit = getRouteReply('root', 'GET', 'admin.php?name=blocks&op=edit&id=51');
    $post = ['name' => 'blocks', 'op' => 'editsave', 'token' => getRouteToken($edit['body'], 'editsave'), 'bid' => '51', 'bkey' => '', 'title' => 'Node mixed block',
        'bfile' => 'node.php', 'bpos' => 'c', 'oldposition' => 'c', 'weight' => '2', 'status' => '1', 'view' => '0', 'newexpire' => '1', 'expire' => '0', 'action' => 'd',
        'blockwhere[]' => 'rss'];
    $param = fn(): string => (string)$pdo->query('SELECT param FROM '.$pre.'blocks WHERE id = 51')->fetchColumn();
    $bad = getRouteReply('root', 'POST', 'admin.php', $post + ['ntype' => 'off', 'nmode' => 'last', 'nlimit' => '3']);
    $kept = $param();
    $good = getRouteReply('root', 'POST', 'admin.php', $post + ['ntype' => 'docs', 'nmode' => 'last', 'nlimit' => '3']);
    $out['editor'] = [$edit['code'], str_contains($edit['body'], 'name="ntype"'), str_contains($edit['body'], 'name="nlimit"'), $bad['code'], $kept, $good['code'], $param()];
    $conf = getRouteReply('root', 'GET', 'admin.php?name=search&op=config');
    $save = getRouteReply('root', 'POST', 'admin.php?name=search&op=save', ['token' => getRouteToken($conf['body'], 'ver[docs]'), 'asearch' => '1', 'search[]' => 'forum',
        'ntype[]' => 'news', 'ver[news]' => '1', 'ver[docs]' => '1', 'slet' => '3', 'slimit' => '500', 'snum' => '1', 'snump' => '5', 'anum' => '50', 'anump' => '10']);
    $node = require $work.'/config/node.php';
    $out['toggle'] = [(bool)preg_match('#name="ntype\[\]"\s+value="news"#', $conf['body']), $save['code'], $node['node']['types']['docs']['integrations']['search'] ?? null,
        (int)$pdo->query('SELECT version FROM '.$pre.'node_types WHERE name = \'docs\'')->fetchColumn(), str_contains($find('', 'word=intro+of'), 'Doc one')];
    [$one, $two] = [$find('', 'word=intro+of&num=1'), $find('', 'word=intro+of&num=2')];
    $out['paged'] = [str_contains($one, 'Beta'), str_contains($one, 'Alpha'), str_contains($two, 'Alpha'), str_contains($two, 'Beta'), str_contains($one, 'num=2')];
    $pdo->exec('INSERT INTO '.$pre.'node_relations (nid, rid, type, sort) VALUES (102, 101, \'related\', 0)');
    $rel = function (string $html): array {
        $part = preg_match('#class="sl-related-title".*?</section>#s', $html, $hit) ? $hit[0] : '';
        return [$part !== '', str_contains($part, '>Alpha</a>'), str_contains($part, 'sl-card-reads')];
    };
    $pdo->exec('INSERT INTO '.$pre.'voting (id, modul, title, body, answer, time, enddate, lang, typ, status) VALUES'
        .' (8, \'\', \'Render poll\', \'Yes|No\', \'0|0\', NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 30 DAY, \'\', 1, 1)');
    $pdo->exec('UPDATE '.$pre.'nodes SET poll = 8 WHERE id = 102');
    $plain = getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=102')['body'];
    $node = require $work.'/config/node.php';
    $keep = $node;
    $node['node']['types']['news']['view'] = ['mode' => 'support'];
    setRouteFile($work.'/config/node.php', $node);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $sup = getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=102');
    setRouteFile($work.'/config/node.php', $keep);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $out['render'] = [$rel($plain), $sup['code'], str_contains($plain, 'data-sl-rate'), str_contains($plain, 'Render poll')];
    $st = $pdo->prepare('INSERT INTO '.$pre.'nodes (tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (2, 0, 3, \'\', \'127.0.0.1\', ?, \'\', \'\', \'\', 2, \'2026-01-06 10:00:00\')');
    for ($i = 1; $i <= 600; $i++) $st->execute(['Bulk '.$i]);
    $out['sitemap'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($work).' integext 2>&1'), true);
    return $out;
}

# The sitemap of the child: the generator runs against the files of the tree, so the map and the HTML map of the stand are kept byte for byte and put back afterwards
# Two warm runs, at the configured limits.syncbatch and at 100, answer how many statements more the smaller cursor batches of Node cost
# A multilingual map lists a category of the site language and leaves out one of another language, which a visitor without a language cookie gets as not found
function getRouteIntegData(): array {
    global $db, $conf;
    $keep = [];
    foreach ([PUBLIC_DIR.'/sitemap.xml', SITEMAP_DIR.'/sitemap.txt'] as $file) $keep[$file] = is_file($file) ? [file_get_contents($file), filemtime($file)] : null;
    try {
        $res = addSitemapTask(true);
        $xml = (string)file_get_contents(PUBLIC_DIR.'/sitemap.xml');
        $txt = (string)file_get_contents(SITEMAP_DIR.'/sitemap.txt');
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $all);
        $locs = array_map('html_entity_decode', $all[1]);
        $has = fn(string $tail): bool => in_array($GLOBALS['conf']['homeurl'].'/index.php?'.$tail, $locs, true);
        $out = ['status' => $res['status'], 'count' => $res['extra']['last_url_count'] ?? 0, 'valid' => simplexml_load_string($xml) !== false,
            'raw' => str_contains($xml, '&op='), 'list' => [$has('name=news'), $has('name=docs'), $has('name=off')], 'cats' => [$has('name=news&cat=1'), $has('name=news&cat=2')],
            'items' => [$has('name=news&op=view&id=101'), $has('name=news&op=view&id=102'), $has('name=news&op=view&id=103'), $has('name=news&op=view&id=104'),
                $has('name=docs&op=view&id=201'), $has('name=off&op=view&id=301')],
            'bulk' => count(array_filter($locs, fn($v) => str_contains($v, 'name=docs&op=view'))),
            'txt' => [(bool)preg_match('#>\s*Open\s*<#', $txt), str_contains($txt, 'Members'),
                str_contains($txt, 'Alpha')], 'parts' => count(glob(BASE_DIR.'/sitemap-*.xml*') ?: [])];
        $num = $db->qnum;
        addSitemapTask(true);
        $wide = $db->qnum - $num;
        $conf['node']['limits']['syncbatch'] = 100;
        $num = $db->qnum;
        $out['batch'] = [addSitemapTask(true)['status'], $db->qnum - $num - $wide];
        $conf['multilingual'] = 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET lang = :lang WHERE id = 1', ['lang' => 'zz']);
        addSitemapTask(true);
        $out['lang'] = str_contains(html_entity_decode((string)file_get_contents(PUBLIC_DIR.'/sitemap.xml')), 'index.php?name=news&cat=1<');
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET lang = :lang WHERE id = 1', ['lang' => $conf['language']]);
        addSitemapTask(true);
        $out['lang'] = [$out['lang'], str_contains(html_entity_decode((string)file_get_contents(PUBLIC_DIR.'/sitemap.xml')), 'index.php?name=news&cat=1<')];
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET lang = \'\' WHERE id = 1');
        $conf['multilingual'] = 0;
        return $out;
    } finally {
        foreach ($keep as $file => $old) {
            if ($old === null) {
                if (is_file($file)) unlink($file);
                continue;
            }
            file_put_contents($file, $old[0]);
            touch($file, $old[1]);
        }
    }
}

# The seed of the guards on top of the integrations: the marks of the data update, a select field of accounts, a favorite worth points and a limit of two
# It adds a hostile Node title, a script in an intro, three forum topics on a shelf of the site language, the poll screen for docsman and forum shelves of two languages
function addRouteGuardRows(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    setRouteFile($work.'/config/update.php', ['update' => ['fields' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0']]);
    $item = fn(string $title, int $sort): array => ['title' => $title, 'active' => true, 'sort' => $sort];
    $data = require $work.'/config/fields.php';
    $data['fields']['account'] = ['city' => ['title' => 'City', 'intro' => '', 'type' => 'select', 'default' => '',
        'options' => ['items' => ['north' => $item('North', 10), 'south' => $item('South', 20)]], 'req' => false, 'multi' => false, 'active' => true, 'sort' => 10]];
    setRouteFile($work.'/config/fields.php', $data);
    $data = require $work.'/config/points.php';
    $data['points']['active'] = '1';
    $data['points']['actions']['favorite'] = ['points' => '5', 'period' => '0', 'limit' => '0'];
    setRouteFile($work.'/config/points.php', $data);
    $data = require $work.'/config/favorites.php';
    $data['favorites'] = array_replace($data['favorites'], ['favact' => '1', 'favorites' => '2']);
    setRouteFile($work.'/config/favorites.php', $data);
    $pdo->exec('UPDATE '.$pre.'nodes SET title = \'<img src=x onerror=alert(1)>Beta\', intro = \'[usehtml]<script>noise(3)</script><style>.noise{}</style>[/usehtml]Beta plumage\''
        .' WHERE id = 102');
    $pdo->exec('UPDATE '.$pre.'nodes SET intro = \'<script>alert(2)</script>Gamma\' WHERE id = 105');
    $topic = fn(int $id, string $title, int $stat): string => '('.$id.', 0, 60, 3, \'boris\', \''.$title.'\', NOW() - INTERVAL 1 DAY, \'\', \'\', '
        .'3, \'boris\', NOW() - INTERVAL 1 DAY, '.$stat.')';
    $pdo->exec('INSERT INTO '.$pre.'forum (id, pid, cid, uid, name, title, time, body, field, luid, lname, ltime, status) VALUES '
        .$topic(7, 'Lamp', 2).', '.$topic(8, 'Desk', 2).', '.$topic(9, 'Hidden', 0));
    $pdo->exec('UPDATE '.$pre.'admins SET modules = \'node-docs,voting\' WHERE id = 4');
    $lang = (string)(require $work.'/config/global.php')['language'];
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pview, pread, ppost, lang) VALUES (60, \'forum\', \'Own shelf\', \'\', \'0|0\', \'0|0\', \'0|0\', '
        .$pdo->quote($lang).'), (61, \'forum\', \'Alien shelf\', \'\', \'0|0\', \'0|0\', \'0|0\', '.$pdo->quote($lang === 'en' ? 'de' : 'en').')');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, topics, posts, lang) VALUES (70, \'forum\', \'Drifted forum\', \'\', 5, 7, \'\')');
}

# Every control of the first form of a page that posts the given op as the browser sends it, in document order: inputs, checked boxes and the selected option
function getRouteInputs(string $html, string $op): array {
    preg_match_all('#<form\b.*?</form>#s', $html, $all);
    foreach ($all[0] as $form) {
        if (!preg_match('#name="op"\s+value="'.preg_quote($op, '#').'"#', $form)) continue;
        $out = [];
        preg_match_all('#<input\b([^>]*)>|<select\b([^>]*)>(.*?)</select>#s', $form, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            $attr = ($tag[1] ?? '') !== '' ? $tag[1] : ($tag[2] ?? '');
            $name = preg_match('#name="([^"]*)"#', $attr, $hit) ? html_entity_decode($hit[1], ENT_QUOTES) : '';
            if ($name === '') continue;
            if (isset($tag[3])) {
                $pick = preg_match('#<option\b[^>]*value="([^"]*)"[^>]*\bselected\b#', $tag[3], $hit) || preg_match('#<option\b[^>]*value="([^"]*)"#', $tag[3], $hit);
                if ($pick) $out[] = [$name, html_entity_decode($hit[1], ENT_QUOTES)];
                continue;
            }
            if (preg_match('#type="(?:checkbox|radio)"#', $attr) && !preg_match('#\schecked\b#', $attr)) continue;
            $out[] = [$name, preg_match('#value="([^"]*)"#', $attr, $hit) ? html_entity_decode($hit[1], ENT_QUOTES) : ''];
        }
        return $out;
    }
    return [];
}

# The pairs of a form as the nested array PHP makes of their body, with named controls replaced and the pairs from the first one carrying the cut prefix dropped
function getRoutePairs(array $pairs, array $set = [], string $cut = ''): array {
    $raw = [];
    foreach ($pairs as [$name, $val]) {
        if ($cut !== '' && str_starts_with($name, $cut)) break;
        $raw[] = rawurlencode($name).'='.rawurlencode(array_key_exists($name, $set) ? $set[$name] : $val);
    }
    parse_str(implode('&', $raw), $out);
    return $out;
}

# The fingerprint of the configuration files a fields save writes
function getRouteConf(string $work): array {
    return array_map(fn($v) => sha1_file($work.'/config/'.$v.'.php'), ['fields', 'node']);
}

# The guards over real HTTP: search output, the fields screen, the type of a Node deletion, favorites, Node categories, the head category language
function getRouteGuard(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $out = [];
    $count = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $find = getRouteReply('', 'GET', 'index.php?name=search&word=Beta')['body'];
    $gamma = getRouteReply('', 'GET', 'index.php?name=search&word=Gamma')['body'];
    $out['search'] = [str_contains($find, '&lt;img src=x onerror=alert(1)&gt;'), str_contains($find, '<img src=x'), str_contains($gamma, '&lt;script&gt;alert(2)'),
        str_contains($gamma, '<script>alert(2)'), str_contains($find, 'plumage'), str_contains($find, 'noise')];
    $page = getRouteReply('root', 'GET', 'admin.php?name=fields')['body'];
    $pairs = getRouteInputs($page, 'save');
    $tok = getRouteToken($page, 'save');
    $post = fn(array $set = [], string $cut = ''): array => getRouteReply('root', 'POST', 'admin.php', getRoutePairs($pairs, $set, $cut));
    $was = getRouteConf($work);
    $get = getRouteReply('root', 'GET', 'admin.php?name=fields&op=save&token='.$tok);
    $out['fields']['get'] = [$tok !== '', $get['code'], getRouteConf($work) === $was];
    $cut = $post(['def[account][0][title]' => 'Town'], 'def[node.');
    $out['fields']['cut'] = [$cut['code'], (bool)preg_match('#node\.[a-z0-9]+: form#', $cut['body']), getRouteConf($work) === $was];
    $name = $post(['def[account][0][name]' => 'town']);
    $out['fields']['name'] = [str_contains($name['body'], 'account: city.name'), getRouteConf($work) === $was];
    $key = $post(['def[account][0][items][0][key]' => '', 'def[account][0][items][0][title]' => '']);
    $out['fields']['key'] = [str_contains($key['body'], 'account: city.options.items.north'), getRouteConf($work) === $was];
    $word = $post(['def[account][0][title]' => '_FIELDS_BAD']);
    $out['fields']['word'] = [str_contains($word['body'], 'account: city.title'), getRouteConf($work) === $was];
    $site = $post(['def[account][0][title]' => '_ACCOUNT']);
    $conf = require $work.'/config/fields.php';
    $out['fields']['site'] = [$site['code'], $conf['fields']['account']['city']['title'] ?? ''];
    $page = getRouteReply('root', 'GET', 'admin.php?name=fields')['body'];
    $pairs = getRouteInputs($page, 'save');
    $slot = '';
    foreach ($pairs as [$one]) if (preg_match('#^(def\[node\.docs\]\[\d+\])\[name\]$#', $one, $hit)) $slot = $hit[1];
    $size = $post([$slot.'[name]' => 'size', $slot.'[title]' => 'Size', $slot.'[type]' => 'int', $slot.'[default]' => '9223372036854775807']);
    $conf = require $work.'/config/fields.php';
    $journal = [];
    foreach (is_file($work.'/logs/error_site.log') ? file($work.'/logs/error_site.log', FILE_IGNORE_NEW_LINES) : [] as $line) {
        $one = json_decode($line, true);
        if (($one['msg'] ?? '') === 'Node: a type operation was published') $journal[] = [$one['level'], $one['kind'], $one['name'], $one['old'], $one['new'], $one['aid']];
    }
    $out['fields']['int'] = [$slot !== '', $size['code'], $conf['fields']['node']['docs']['size']['default'] ?? null, $journal];
    $page = getRouteReply('root', 'GET', 'admin.php?name=node')['body'];
    $drop = getRouteReply('root', 'POST', 'admin.php', ['name' => 'node', 'op' => 'delete', 'id' => '201', 'type' => 'news', 'version' => '1',
        'token' => getRouteToken($page, 'delete')]);
    $out['delete'] = [$drop['code'], $count('SELECT COUNT(*) FROM '.$pre.'nodes WHERE id = 201')];
    [, $fav, $ftok] = getRoutePageBits(getRouteReply('anna', 'GET', 'index.php?name=news&op=view&id=102')['body']);
    $try = fn(string $mod, int $id): int => getRouteFavPost('anna', str_replace(['id=102', 'mod=news'], ['id='.$id, 'mod='.$mod], $fav), $ftok)['code'];
    $favs = fn(string $mod): int => $count('SELECT COUNT(*) FROM '.$pre.'favorites WHERE uid = 2 AND modul = \''.$mod.'\'');
    $out['fav'] = [$fav !== '', $try('bogus', 5), $favs('bogus'), $try('forum', 999), $favs('forum'), $try('forum', 9), $try('forum', 7), $try('news', 102), $try('forum', 8),
        $favs('forum'), $favs('news'), $count('SELECT COUNT(*) FROM '.$pre.'points WHERE uid = 2 AND action = \'favorite\''),
        $pdo->query('SELECT source FROM '.$pre.'points WHERE uid = 2 AND action = \'favorite\' ORDER BY source')->fetchAll(PDO::FETCH_COLUMN)];
    $cats = fn(): int => $count('SELECT COUNT(*) FROM '.$pre.'categories');
    $add = fn(string $who, array $row): int => getRouteReply($who, 'POST', 'admin.php', $row + ['name' => 'categories', 'op' => 'addsave', 'title' => 'Probe cat',
        'description' => '', 'imgcat' => '', 'lang' => '', 'status' => '1', 'token' => getRouteToken(getRouteReply($who, 'GET', 'admin.php?name=categories&op=add')['body'],
        'addsave')])['code'];
    $num = $cats();
    $out['cats']['parent'] = [$add('root', ['modul' => 'news', 'cid' => '3']), $cats() - $num];
    $add('root', ['modul' => 'news', 'cid' => '1', 'pview' => ['2|1'], 'pedit' => ['2|1', '2|0'], 'pmod' => ['1|0']]);
    $made = $pdo->query('SELECT parent, pview, pread, pedit, pmod FROM '.$pre.'categories ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_NUM);
    $out['cats']['rights'] = [$cats() - $num, $made];
    $edit = getRouteReply('root', 'GET', 'admin.php?name=categories&op=edit&cid=1')['body'];
    getRouteReply('root', 'POST', 'admin.php', ['name' => 'categories', 'op' => 'save', 'id' => '1', 'modul' => 'news', 'title' => 'Open', 'description' => '',
        'imgcat' => '', 'lang' => '', 'parent' => '0', 'status' => '1', 'ppost' => ['2|1'], 'pread' => ['0|0'], 'token' => getRouteToken($edit, 'save')]);
    $out['cats']['save'] = $pdo->query('SELECT ppost, pread FROM '.$pre.'categories WHERE id = 1')->fetch(PDO::FETCH_NUM);
    $vote = getRouteReply('docsman', 'GET', 'admin.php?name=voting')['body'];
    $gone = getRouteReply('docsman', 'POST', 'admin.php', ['name' => 'voting', 'op' => 'delete', 'id' => '7', 'token' => getRouteToken($vote, 'delete')]);
    $out['poll'] = [$gone['code'], (int)getRouteCol($pdo, 102, 'poll'), $count('SELECT COUNT(*) FROM '.$pre.'voting WHERE id = 7')];
    $forum = getRouteReply('root', 'GET', 'admin.php?name=forum')['body'];
    $drift = $count('SELECT topics + posts FROM '.$pre.'categories WHERE id = 70');
    $synced = getRouteReply('root', 'POST', 'admin.php', ['name' => 'forum', 'op' => 'sync', 'token' => getRouteToken($forum, 'sync')]);
    $out['forum'] = [$drift, $synced['code'], $count('SELECT topics + posts FROM '.$pre.'categories WHERE id = 70')];
    $data = require $work.'/config/global.php';
    setRouteFile($work.'/config/global.php', array_replace($data, ['multilingual' => '1']));
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $trail = fn(int $cid): string => preg_match('#"@type":\s*"BreadcrumbList".*?\]#s', getRouteReply('', 'GET', 'index.php?name=forum&cat='.$cid)['body'], $hit) ? $hit[0] : '';
    $out['lang'] = [str_contains($trail(60), 'Own shelf'), str_contains($trail(61), 'Alien shelf')];
    setRouteFile($work.'/config/global.php', $data);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $out['child'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($work).' guardext 2>&1'), true);
    return $out;
}

# The integrity over real HTTP: a manual point correction applies once per form and is checked before the profile is written
# A block save that fails in its last statement takes its parameter back
# The main administrator annuls votes of a disabled material, a type without rating and a disabled type
function getRouteIntact(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $out = [];
    $data = require $work.'/config/update.php';
    setRouteFile($work.'/config/update.php', ['update' => array_replace($data['update'] ?? [], ['points' => '6.3.0'])]);
    $data = require $work.'/config/points.php';
    $data['points']['active'] = '1';
    setRouteFile($work.'/config/points.php', $data);
    $data = require $work.'/config/fields.php';
    $data['fields']['account'] = [];
    setRouteFile($work.'/config/fields.php', $data);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $count = fn(string $sql): mixed => $pdo->query($sql)->fetchColumn();
    $say = fn(array $res): string => preg_match('#<div class="sl-alert-body">(.*?)</div>#s', $res['body'], $hit) ? trim(strip_tags($hit[1])) : '';
    $page = getRouteReply('root', 'GET', 'admin.php?name=account&op=add&id=2')['body'];
    $pairs = getRouteInputs($page, 'addsave');
    $keys = array_column($pairs, 1, 0);
    $save = fn(array $set): array => getRouteReply('root', 'POST', 'admin.php', getRoutePairs($pairs, $set));
    $bal = fn(): array => [(int)$count('SELECT points FROM '.$pre.'users WHERE id = 2'), (int)$count('SELECT COUNT(*) FROM '.$pre.'points WHERE uid = 2 AND action = \'adjust\'')];
    $was = $bal();
    $one = $save(['pdiff' => '5', 'pnote' => 'Probe gift']);
    $two = $save(['pdiff' => '7', 'pnote' => 'Probe gift']);
    $pairs = getRouteInputs($two['body'], 'addsave');
    $fresh = array_column($pairs, 1, 0)['pkey'] ?? '';
    $save = fn(array $set): array => getRouteReply('root', 'POST', 'admin.php', getRoutePairs($pairs, $set));
    $out['adjust'] = [
        'key' => (bool)preg_match('/^[0-9a-f]{32}$/D', $keys['pkey'] ?? ''),
        'codes' => [$one['code'], $two['code']],
        'was' => $was,
        'now' => $bal(),
        'say' => [$say($one), preg_match_all('#<div class="sl-alert-body">(.*?)</div>#s', $two['body'], $hit) ? trim(strip_tags(implode(' ', $hit[1]))) : ''],
        'fresh' => (bool)preg_match('/^[0-9a-f]{32}$/D', $fresh) && $fresh !== ($keys['pkey'] ?? ''),
    ];
    $occ = (string)$count('SELECT occ FROM '.$pre.'users WHERE id = 2');
    $long = $save(['pdiff' => '3', 'pnote' => str_repeat('n', 256), 'occ' => 'Changed long']);
    $tags = $save(['pdiff' => '3', 'pnote' => '<b>bold</b>', 'occ' => 'Changed tags']);
    $out['note'] = ['codes' => [$long['code'], $tags['code']], 'occ' => (string)$count('SELECT occ FROM '.$pre.'users WHERE id = 2') === $occ, 'points' => $bal(),
        'kept' => str_contains($long['body'], 'value="'.($fresh ?: '-').'"'), 'say' => [$say($long), $say($tags)]];
    $page = getRouteReply('root', 'GET', 'admin.php?name=blocks&op=edit&id=50')['body'];
    $form = getRouteInputs($page, 'editsave');
    $param = fn(): string => (string)$count('SELECT param FROM '.$pre.'blocks WHERE id = 50');
    $before = $param();
    $pdo->exec('CREATE TRIGGER '.$pre.'probe_block BEFORE UPDATE ON '.$pre.'blocks FOR EACH ROW BEGIN IF NEW.title = \'Boom\' THEN'
        .' SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'probe refuses the block\'; END IF; END');
    $boom = getRouteReply('root', 'POST', 'admin.php', getRoutePairs($form, ['title' => 'Boom', 'nlimit' => '3']));
    $pdo->exec('DROP TRIGGER '.$pre.'probe_block');
    $out['block']['boom'] = [$form !== [], $boom['code'], $param() === $before, (string)$count('SELECT title FROM '.$pre.'blocks WHERE id = 50')];
    $fine = getRouteReply('root', 'POST', 'admin.php', getRoutePairs($form, ['title' => 'Fine', 'nlimit' => '3']));
    $out['block']['fine'] = [$fine['code'], json_decode($param(), true)['limit'] ?? null, (string)$count('SELECT title FROM '.$pre.'blocks WHERE id = 50')];
    $votes = [];
    foreach ([['', 102], ['boris', 102], ['', 101]] as $i => [$who, $mid]) {
        [$tok] = getRoutePageBits(getRouteReply($who, 'GET', 'index.php?name=news&op=view&id='.$mid)['body']);
        $votes[] = getRouteReply($who, 'POST', 'index.php?go=1&op=getRatingView', ['mod' => 'node.news', 'id' => (string)$mid, 'rate' => '4',
            'request' => str_repeat((string)($i + 1), 32), 'typ' => 'stars', 'token' => $tok])['code'];
    }
    $ids = $pdo->query('SELECT id FROM '.$pre.'rating_votes WHERE scope = \'node.news\' AND mid IN (101, 102) ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $out['votes'] = [$votes, count($ids), getRouteRate($pdo, 102), getRouteRate($pdo, 101)];
    $annul = function (int $vote, int $mid) use ($pdo, $pre): array {
        $page = getRouteReply('root', 'GET', 'admin.php?name=ratings&op=votes&vote='.$vote)['body'];
        $res = getRouteReply('root', 'POST', 'admin.php', ['name' => 'ratings', 'op' => 'annul', 'vote' => (string)$vote, 'reason' => 'probe',
            'token' => getRouteToken($page, 'annul')]);
        return [$res['code'], (int)$pdo->query('SELECT annulled FROM '.$pre.'rating_votes WHERE id = '.$vote)->fetchColumn() > 0, getRouteRate($pdo, $mid)];
    };
    $pdo->exec('UPDATE '.$pre.'nodes SET status = 0 WHERE id = 102');
    $out['annul']['material'] = $annul((int)($ids[0] ?? 0), 102);
    $pdo->exec('UPDATE '.$pre.'nodes SET status = 2 WHERE id = 102');
    $keep = (string)file_get_contents($work.'/config/node.php');
    $data = require $work.'/config/node.php';
    $data['node']['types']['news']['features']['rating'] = false;
    setRouteFile($work.'/config/node.php', $data);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $out['annul']['rating'] = $annul((int)($ids[1] ?? 0), 102);
    file_put_contents($work.'/config/node.php', $keep);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $pdo->exec('UPDATE '.$pre.'node_types SET active = 0 WHERE name = \'news\'');
    $out['annul']['type'] = $annul((int)($ids[2] ?? 0), 101);
    $pdo->exec('UPDATE '.$pre.'node_types SET active = 1 WHERE name = \'news\'');
    $out['annul']['again'] = $annul((int)($ids[0] ?? 0), 102);
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, lang) VALUES (90, \'docs\', \'Broken\', \'\', \'0|0\', \'\')');
    $pdo->exec('UPDATE '.$pre.'node_types SET version = version + 1 WHERE name = \'docs\'');
    $tok = getRouteToken(getRouteReply('root', 'GET', 'admin.php?name=categories&op=add')['body'], 'addsave');
    $gone = getRouteReply('root', 'POST', 'admin.php', ['name' => 'categories', 'op' => 'delete', 'id' => '90', 'modul' => 'docs', 'token' => $tok]);
    $pdo->exec('UPDATE '.$pre.'node_types SET version = version - 1 WHERE name = \'docs\'');
    $out['broken'] = [$tok !== '', $gone['code'], (int)$count('SELECT COUNT(*) FROM '.$pre.'categories WHERE id = 90')];
    $again = getRouteReply('root', 'POST', 'admin.php', ['name' => 'categories', 'op' => 'delete', 'id' => '90', 'modul' => 'docs', 'token' => $tok]);
    $out['broken'][] = [$again['code'], (int)$count('SELECT COUNT(*) FROM '.$pre.'categories WHERE id = 90')];
    $data = require $work.'/config/favorites.php';
    $data['favorites'] = array_replace($data['favorites'], ['favact' => '1', 'favorites' => '2']);
    setRouteFile($work.'/config/favorites.php', $data);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
    $pdo->exec('DELETE FROM '.$pre.'favorites WHERE uid = 2');
    $pdo->exec('INSERT INTO '.$pre.'favorites (uid, fid, modul, time) VALUES (2, 104, \'news\', NOW())');
    [, $fav, $ftok] = getRoutePageBits(getRouteReply('anna', 'GET', 'index.php?name=news&op=view&id=101')['body']);
    $hold = proc_open([PHP_BINARY, __FILE__, $work, 'favhold', $GLOBALS['rbase']], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $held = trim((string)fgets($pipes[1]));
    $star = getRouteFavPost('anna', $fav, $ftok);
    proc_close($hold);
    $out['favrace'] = [$fav !== '', $held, $star['code'], (int)$count('SELECT COUNT(*) FROM '.$pre.'favorites WHERE uid = 2'),
        (int)$count('SELECT COUNT(*) FROM '.$pre.'favorites WHERE uid = 2 AND fid = 101')];
    $ring = fn(int $id, int $up, int $num): string => '('.$id.', \'news\', \'Ring '.$id.'\', \'\', '.$up.', '.$num.', \'0|0\', \'0|0\', \'0|0\', \'\')';
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, parent, ordern, pview, pread, ppost, lang) VALUES '.$ring(95, 96, 0).', '.$ring(96, 95, 0).', '
        .$ring(97, 999, 5).', '.$ring(98, 97, 1));
    $pdo->exec('UPDATE '.$pre.'nodes SET cid = 95 WHERE id = 102');
    foreach (glob($work.'/cache/data/*') ?: [] as $one) if (is_file($one)) unlink($one);
    $start = microtime(true);
    $cat = getRouteReply('root', 'GET', 'admin.php?name=categories&op=edit&cid=95');
    $rss = getRouteReply('', 'GET', 'index.php?name=rss&mod=news');
    $sheet = getRouteReply('root', 'GET', 'admin.php?name=node&op=edit&id=102')['body'];
    $out['ring'] = [$cat['code'], str_contains($cat['body'], 'Ring 96'), $rss['code'], str_contains($rss['body'], 'Ring 96'),
        (bool)preg_match('#<option[^>]*value="95"[^>]*selected#', $sheet), microtime(true) - $start < 30,
        strpos($sheet, 'value="97"') < strpos($sheet, 'value="98"'), (bool)preg_match('#value="98"[^>]*>\x{00A0}+Ring 98#u', $sheet)];
    return $out;
}

# The service side in a child that boots the core, where only a main administrator reaches the category screen: a moderator of docs creates a category of docs
# The moderator is refused one of news without a row, and an administrator context without the right of polls is refused a poll before any statement
function getRouteGuardData(): array {
    global $db, $fld;
    $ctx = fn(array $mods): NodeContext => new NodeContext(0, [], 4, $mods, false, false, '127.0.0.1', '');
    $row = ['title' => 'Child cat', 'intro' => '', 'img' => '', 'lang' => '', 'parent' => 0, 'status' => 1, 'pview' => '0|0', 'pread' => '0|0', 'ppost' => '0|0',
        'preply' => '0|0', 'pedit' => '3|0', 'pdelete' => '3|0', 'pmod' => '3|0'];
    $cats = fn(): int => (int)$db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_categories')->fetchColumn();
    $num = $cats();
    $out = ['other' => getRouteCall(fn() => (new NodeService($db, $ctx(['docs']), $fld))->addNodeCategory(['modul' => 'news'] + $row))];
    $out['own'] = getRouteCall(fn() => (new NodeService($db, $ctx(['docs']), $fld))->addNodeCategory(['modul' => 'docs'] + $row) > 0);
    $out['rows'] = $cats() - $num;
    $db->setSqlBegin();
    $out['poll'] = getRouteCall(fn() => (new NodeService($db, $ctx(['docs']), $fld))->deleteNodePoll(7));
    $db->setSqlRollback();
    return $out;
}

# The public form on the integration types: news open to guests under review, an inactive and a link role beside its two, the write window of one minute
# News gets a rule without guest upload and with two files a request, the materials of the stand older than the window, and a published title with an ampersand
# For the categories and links of the form news gets the tree, a scheduled material 107, a members-only category Secret, a category Lobby for every writer and a shown Open
function addRouteSecureTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['limits']['send'] = 60;
    $data['node']['types']['news']['workflow'] = ['access' => 'all'];
    $data['node']['types']['news']['assets']['old'] = getRouteRole('Old', 'download', [], 1, false, false, 2) + ['active' => false];
    $data['node']['types']['news']['assets']['site'] = getRouteRole('Site', 'link', ['file'], 1, true, false, 3);
    $data['node']['types']['news']['features']['tree'] = true;
    setRouteFile($work.'/config/node.php', $data);
    $data = require $work.'/config/uploads.php';
    $rule = explode('|', $data['uploads']['news']);
    [$rule[5], $rule[9], $rule[10]] = ['2', '1', '0'];
    $data['uploads']['news'] = implode('|', $rule);
    setRouteFile($work.'/config/uploads.php', $data);
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (106, 1, 0, 2, \'\', \'127.0.0.1\', \'Q&A probe\', \'intro of 106\', \'Body of 106\', \'\', 2, \'2026-01-08 10:00:00\'),'
        .' (107, 1, 0, 2, \'\', \'127.0.0.1\', \'Later\', \'intro of 107\', \'Body of 107\', \'\', 2, NOW() + INTERVAL 1 DAY)');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pview, pread, ppost, lang) VALUES (4, \'news\', \'Secret\', \'\', \'1|0\', \'0|0\', \'0|0\', \'\'),'
        .' (5, \'news\', \'Lobby\', \'\', \'0|0\', \'0|0\', \'0|0\', \'\')');
    $pdo->exec('UPDATE '.$pre.'categories SET pview = \'0|0\' WHERE id = 1');
    $pdo->exec('UPDATE '.$pre.'nodes SET created = NOW() - INTERVAL 1 DAY');
}

# Switch the captcha of comments on or off in the scratch configuration and drop the merged cache, so the next request reads it
function setRouteCaptcha(string $work, bool $on): void {
    $data = require $work.'/config/security.php';
    $data['security']['captcha'] = array_replace($data['security']['captcha'], ['active' => $on ? '1' : '0', 'provider' => 'altcha', 'comments' => '1']);
    setRouteFile($work.'/config/security.php', $data);
    if (is_file($work.'/config/local.php')) unlink($work.'/config/local.php');
}

# Solve the challenge the captcha route hands one visitor, as the widget does: the number whose hash with the salt is the challenge, sent back signed
function getRouteAltcha(string $who): string {
    $task = json_decode(getRouteReply($who, 'GET', 'index.php?go=captcha&act=comment')['body'], true);
    if (!is_array($task)) return '';
    for ($num = 0; $num <= (int)$task['maxnumber']; $num++) {
        if (hash('sha256', $task['salt'].$num) !== $task['challenge']) continue;
        return base64_encode((string)json_encode(['algorithm' => $task['algorithm'], 'challenge' => $task['challenge'], 'number' => $num, 'salt' => $task['salt'],
            'signature' => $task['signature']]));
    }
    return '';
}

# The public form: the upload right, the role and request limits of a file, the write window, the captcha of a guest, the closed category and search
# Then come the categories the form offers, related and parent links to materials the writer may not read, the escaped title of the pending notice, the profile import
function getRouteSecure(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $count = fn(string $table): int => (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.$table)->fetchColumn();
    $files = fn(): int => count(glob($work.'/uploads/node/news/*-*.*') ?: []);
    $form = fn(string $who): string => getRouteReply($who, 'GET', 'index.php?name=news&op=add')['body'];
    $send = fn(string $who, array $post): int => getRouteReply($who, 'POST', 'index.php?name=news&op=add', $post)['code'];
    $file = fn(): CURLFile => new CURLFile($work.'/upload.png', 'image/png', 'upload.png');
    $row = fn(int $idx, string $role): array => ['asset['.$idx.'][role]' => $role, 'asset['.$idx.'][id]' => '', 'afile'.$idx => $file()];
    $out = [];
    $gtok = getRouteToken($form(''), 'name="action"');
    $atok = getRouteToken($form('anna'), 'name="action"');
    $base = ['title' => 'Secure one', 'intro' => 'Intro', 'body' => 'Body', 'action' => 'preview'];
    $try = function (string $who, string $tok, array $rows) use ($base, $send, $files): array {
        $was = $files();
        return [$send($who, $base + ['token' => $tok, 'aname' => 'Guest'] + $rows), $files() - $was];
    };
    $out['upload'] = [
        'guest' => $try('', $gtok, $row(0, 'cover')),
        'user' => $try('anna', $atok, $row(0, 'cover')),
        'role' => $try('anna', $atok, $row(0, 'cover') + $row(1, 'cover')),
        'request' => $try('anna', $atok, $row(0, 'cover') + $row(1, 'files') + $row(2, 'files')),
        'inactive' => $try('anna', $atok, $row(0, 'old')),
        'link' => $try('anna', $atok, $row(0, 'site')),
    ];
    $was = $count('nodes');
    $first = $send('anna', ['title' => 'Window one', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $atok]);
    $again = getRouteReply('anna', 'POST', 'index.php?name=news&op=add', ['title' => 'Window two', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $atok]);
    $mtok = getRouteToken($form('moder'), 'name="action"');
    $moder = $send('moder', ['title' => 'Window moder', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $mtok]);
    $out['window'] = [$first, $again['code'], $moder, $count('nodes') - $was];
    $pdo->exec('UPDATE '.$pre.'nodes SET created = NOW() - INTERVAL 1 DAY');
    setRouteCaptcha($work, true);
    $page = $form('');
    $gtok = getRouteToken($page, 'name="action"');
    $post = ['title' => 'Guest one', 'aname' => 'Guest', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $gtok, 'sl_ct' => getRouteField($page, 'sl_ct')];
    [$rows, $mails] = [$count('nodes'), $count('mail')];
    $bad = base64_encode('{"algorithm":"SHA-256","challenge":"00","number":1,"salt":"x","signature":"00"}');
    $out['captcha'] = [str_contains($page, 'altcha'), str_contains($form('anna'), 'altcha'), $send('', $post), $send('', $post + ['altcha' => $bad]),
        $count('nodes') - $rows, $count('mail') - $mails];
    sleep(2);
    $good = $send('', $post + ['altcha' => getRouteAltcha('')]);
    $out['captcha'][] = $good;
    $out['captcha'][] = $count('nodes') - $rows;
    $out['captcha'][] = $count('mail') > $mails;
    setRouteCaptcha($work, false);
    $find = getRouteReply('', 'GET', 'index.php?name=search&word=probe')['body'];
    $out['search'] = [str_contains($find, 'title="Q&amp;A probe"'), str_contains($find, 'Q&amp;amp;A')];
    $pick = fn(string $html): string => preg_match('#<select\b[^>]*name="cid".*?</select>#s', $html, $hit) ? $hit[0] : '';
    $has = fn(string $html, int $cid): bool => str_contains($pick($html), 'value="'.$cid.'"');
    [$gform, $aform] = [$form(''), $form('anna')];
    $out['cats'] = [$has($gform, 5), $has($gform, 4), $has($gform, 1), $has($aform, 4), $has($aform, 1)];
    $gtok = getRouteToken($gform, 'name="action"');
    $atok = getRouteToken($aform, 'name="action"');
    $look = fn(string $who, string $tok, string $key, int $rid): int => $send($who, $base + ['token' => $tok, 'aname' => 'Guest', $key => (string)$rid]);
    $out['refs'] = [
        'guest' => array_map(fn(int $rid): array => [$look('', $gtok, 'relrel', $rid), $look('', $gtok, 'relparent', $rid)], [999, 103, 107, 105]),
        'user' => [$look('anna', $atok, 'relrel', 103), $look('anna', $atok, 'relparent', 107)],
    ];
    $out['hidden'] = [$look('', $gtok, 'cid', 4), $look('anna', $atok, 'cid', 4)];
    $pdo->exec('UPDATE '.$pre.'nodes SET created = NOW() - INTERVAL 1 DAY');
    $evil = '<a href="x">t</a>';
    $made = $send('', ['title' => $evil, 'aname' => 'Guest', 'intro' => '', 'body' => '', 'action' => 'submit', 'token' => $gtok]);
    $st = $pdo->prepare('SELECT body FROM '.$pre.'mail WHERE title LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute(['%'.$evil]);
    $body = (string)$st->fetchColumn();
    $out['mail'] = [$made, $body !== '', str_contains($body, htmlspecialchars($evil, ENT_QUOTES)), str_contains($body, $evil)];
    $admin = getRouteReply('root', 'GET', 'admin.php?name=node&op=import')['body'];
    $prof = getRouteReply('root', 'GET', 'admin.php?name=node&op=export&type=docs')['body'];
    $types = fn(): int => $count('node_types');
    $load = function (string $ext, ?string $body = null) use ($work, $prof, $admin): array {
        file_put_contents($work.'/profile.'.$ext, $body ?? $prof);
        $got = getRouteReply('root', 'POST', 'admin.php', ['name' => 'node', 'op' => 'import', 'token' => getRouteToken($admin, 'import'), 'tname' => 'docsc'.$ext[0],
            'file' => new CURLFile($work.'/profile.'.$ext, 'application/json', 'profile.'.$ext)]);
        return [$got['code'], preg_match('#class="sl-alert-body">\s*(.*?)\s*</div>#s', $got['body'], $hit) ? $hit[1] : ''];
    };
    $was = $types();
    $txt = $load('txt');
    $big = $load('json', str_repeat(' ', 1048577));
    $bad = $load('json', '{');
    $out['importtext'] = [$txt, $big, $bad, $types() - $was];
    $out['import'] = [str_starts_with(ltrim($prof), '{'), $txt[0], $types() - $was, $load('json')[0], $types() - $was];
    return $out;
}

# The document tree: docs gets the tree and related links, two branches under Guide, a pending root Hidden with the published child Orphan
# The plain root Doc one of the shared rows stands beside them, with one related link of Config, so the view reads its related cards as well
function addRouteTreeTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['types']['docs']['features'] = getRouteFeatures(['related', 'tree']);
    setRouteFile($work.'/config/node.php', $data);
    $rows = [202 => ['Guide', 2], 203 => ['Install & run', 2], 204 => ['Config', 2], 205 => ['Advanced', 2], 206 => ['Hidden', 1], 207 => ['Orphan', 2]];
    $st = $pdo->prepare('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (?, 2, 0, 3, \'\', \'127.0.0.1\', ?, ?, ?, \'\', ?, ?)');
    foreach ($rows as $id => [$title, $state]) $st->execute([$id, $title, 'intro of '.$id, 'Body of '.$id, $state, ($state === 2) ? '2026-01-06 10:00:00' : null]);
    $pdo->exec('INSERT INTO '.$pre.'node_relations (nid, rid, type, sort) VALUES (203, 202, \'parent\', 0), (204, 202, \'parent\', 0), (205, 204, \'parent\', 0),'
        .' (207, 206, \'parent\', 0), (204, 201, \'related\', 0)');
}

# The branch block of one answered material read back from its markup: the trail, the level, the children of the current one, the current one and the neighbours
function getRouteTreeNav(string $html): array {
    if (!preg_match('#<nav class="sl-node-tree".*?</nav>#s', $html, $hit)) return [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>'.$hit[0]);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $text = fn(string $path): array => array_map(fn($v) => trim($v->textContent), iterator_to_array($xp->query($path)));
    return [
        'path' => $text('//p[@class="sl-category-nav"]/*[contains(@class, "sl-crumb") and not(contains(@class, "sl-crumb-sep"))]'),
        'items' => $text('//ol[@class="sl-list"]/li/*[1]'),
        'kids' => $text('//ol[@class="sl-sublist"]/li/a'),
        'cur' => $text('//*[@aria-current="page"]'),
        'prev' => $text('//a[@rel="prev"]'),
        'next' => $text('//a[@rel="next"]'),
        'more' => count($text('//p[@class="sl-node-tree-more"]')),
        'first' => implode('', $text('//ol[@class="sl-list"]/@style')),
    ];
}

# The document tree over real requests: the branch, the trail and the neighbours of the reading order, a pending parent that stays hidden
# It also reads the pending root itself for the moderator of docs, a type without the tree and one escaping of a title
# The child treeext counts the statements before and after the type outgrows one batch of getNodeTree()
function getRouteTree(PDO $pdo): array {
    global $rwork;
    $page = fn(string $who, int $id): array => getRouteReply($who, 'GET', 'index.php?name=docs&op=view&id='.$id);
    $count = fn(): array => json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($rwork).' treeext 2>&1'), true) ?? [];
    $out = [];
    foreach ([201, 204, 205, 207] as $id) {
        $one = $page('', $id);
        $out['nav'][$id] = [$one['code'], getRouteTreeNav($one['body'])];
    }
    $mid = $page('', 204)['body'];
    $out['escape'] = [str_contains($mid, '>Install &amp; run</a>'), str_contains($mid, '&amp;amp;')];
    $out['hidden'] = [str_contains($page('', 207)['body'], 'op=view&amp;id=206'), $page('', 206)['code']];
    $mod = $page('docsman', 206);
    $out['moder'] = [$mod['code'], getRouteTreeNav($mod['body'])];
    $news = getRouteReply('', 'GET', 'index.php?name=news&op=view&id=101');
    $out['plain'] = [$news['code'], str_contains($news['body'], 'sl-node-tree')];
    $out['sql'][] = $count();
    $pdo->exec('INSERT INTO '.RPREF.'_nodes (tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' SELECT 2, 0, 3, \'\', \'127.0.0.1\', CONCAT(\'Zulu \', seq), \'\', \'\', \'\', 2, \'2026-01-06 10:00:00\' FROM seq_1_to_600');
    $pdo->exec('INSERT INTO '.RPREF.'_node_relations (nid, rid, type, sort) SELECT id, 205, \'parent\', 0 FROM '.RPREF.'_nodes'
        .' WHERE tid = 2 AND title LIKE \'Zulu %\' AND CAST(SUBSTRING(title, 6) AS UNSIGNED) <= 25');
    $mid = (int)$pdo->query('SELECT id FROM '.RPREF.'_nodes WHERE title = \'Zulu 300\'')->fetchColumn();
    $wide = fn(int $id): array => array_map(fn($v) => is_array($v) ? count($v) : $v, getRouteTreeNav($page('', $id)['body']));
    $out['wide'] = ['edge' => $wide(207), 'middle' => $wide($mid), 'kids' => $wide(205), 'cur' => getRouteTreeNav($page('', $mid)['body'])['items'][10] ?? ''];
    $out['sql'][] = $count();
    return $out;
}

# The statements of one view of Config as a guest through the real helpers of the controller: the type, the material and its related cards, then the tree read apart
function getRouteTreeData(): array {
    global $db;
    define('ADMIN_FILE', true);
    require_once BASE_DIR.'/modules/node/index.php';
    $num = $db->qnum;
    $query = getNodeReader();
    $type = $query->getNodeType('docs');
    $node = $query->getNode(204, $type);
    $refs = [];
    foreach ($node->rels ?? [] as $rel) if ($rel->type === 'related') $refs[$rel->rid] = 'docs';
    if ($refs) $query->getNodeTargetList($refs);
    $view = $db->qnum - $num;
    $num = $db->qnum;
    $data = getNodeTreeData($query, $type, $node);
    return ['view' => $view, 'tree' => $db->qnum - $num, 'items' => array_column($data['items'] ?? [], 'title'), 'next' => $data['next_title'] ?? ''];
}

# The display modes: news on article, docs on docs, and three more types - faq, files with a cover and a download role, media with a poster
# Media also has a player source, a gallery and the field year; two materials each, one media material with a poster and one with a gallery image only
function addRouteModeTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['types']['news']['view'] = ['mode' => 'article'];
    $data['node']['types']['docs']['view'] = ['mode' => 'docs'];
    $data['node']['types']['faq'] = ['version' => 1, 'view' => ['mode' => 'faq'], 'features' => getRouteFeatures([])];
    $data['node']['types']['files'] = ['version' => 1, 'view' => ['mode' => 'files'], 'features' => getRouteFeatures([]), 'assets' => ['cover' => getRouteRole('Cover',
        'image', ['image'], 1, false, false, 0), 'download' => getRouteRole('Download', 'download', [], 3, true, true, 1)]];
    $data['node']['types']['media'] = ['version' => 1, 'view' => ['mode' => 'media'], 'features' => getRouteFeatures([]), 'assets' => ['poster' => getRouteRole('Poster',
        'none', ['image'], 1, false, false, 0), 'source' => getRouteRole('Source', 'player', ['video', 'audio'], 1, true, false, 1), 'gallery' => getRouteRole('Gallery',
        'gallery', ['image'], 5, false, false, 2)]];
    setRouteFile($work.'/config/node.php', $data);
    $data = require $work.'/config/fields.php';
    $data['fields']['node']['media'] = ['year' => ['title' => 'Year', 'intro' => '', 'type' => 'int', 'default' => null, 'options' => ['min' => 1, 'max' => 9999],
        'req' => false, 'multi' => false, 'active' => true, 'sort' => 10]];
    setRouteFile($work.'/config/fields.php', $data);
    $data = require $work.'/config/uploads.php';
    $rate = require $work.'/config/ratings.php';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    foreach (['faq', 'files', 'media'] as $name) {
        $data['uploads'][$name] = $data['uploads']['all'];
        $rate['ratings']['node.'.$name] = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];
        mkdir($work.'/uploads/node/'.$name.'/thumb', 0777, true);
    }
    setRouteFile($work.'/config/uploads.php', $data);
    setRouteFile($work.'/config/ratings.php', $rate);
    file_put_contents($work.'/uploads/node/files/pack-eeeeeeeeee.zip', str_repeat('0123456789', 200));
    foreach (['files/cover-hhhhhhhhhh.png', 'media/poster-ffffffffff.png', 'media/shot-iiiiiiiiii.png'] as $one) file_put_contents($work.'/uploads/node/'.$one, $png);
    file_put_contents($work.'/uploads/node/media/clip-gggggggggg.mp4', str_repeat("\0", 64));
    $pdo->exec('INSERT INTO '.$pre.'node_types (id, name, title, intro, ext, active, sort, version) VALUES (5, \'faq\', \'FAQ\', \'\', \'\', 1, 50, 1),'
        .' (6, \'files\', \'Files\', \'\', \'\', 1, 60, 1), (7, \'media\', \'Media\', \'\', \'\', 1, 70, 1)');
    $rows = [
        501 => [5, 'How to reset a password?', 'Open the profile and choose Security.', ''],
        502 => [5, 'Why no mail arrives?', 'Check the spam folder and the SMTP settings.', ''],
        601 => [6, 'Release pack', 'The archive of a clean installation.', ''],
        602 => [6, 'Patch notes', 'Only the notes, no file yet.', ''],
        701 => [7, 'Overview video', 'What is new in ten minutes.', '{"year":2026}'],
        702 => [7, 'Gallery only', 'Stills without a poster.', ''],
    ];
    $st = $pdo->prepare('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (?, ?, 0, 3, \'\', \'127.0.0.1\', ?, ?, ?, ?, 2, ?)');
    foreach ($rows as $id => [$tid, $title, $intro, $field]) $st->execute([$id, $tid, $title, $intro, 'Body of '.$id, $field, '2026-01-0'.($id % 10).' 10:00:00']);
    $pdo->exec('INSERT INTO '.$pre.'node_assets (id, nid, kind, role, src, name, intro, mime, size, width, height, hits, sort) VALUES'
        .' (11, 601, \'image\', \'cover\', \'cover-hhhhhhhhhh.png\', \'cover.png\', \'\', \'image/png\', 70, 1, 1, 0, 0),'
        .' (12, 601, \'file\', \'download\', \'pack-eeeeeeeeee.zip\', \'pack.zip\', \'\', \'application/zip\', 2000, NULL, NULL, 318, 1),'
        .' (13, 701, \'image\', \'poster\', \'poster-ffffffffff.png\', \'poster.png\', \'\', \'image/png\', 70, 1, 1, 0, 0),'
        .' (14, 701, \'video\', \'source\', \'clip-gggggggggg.mp4\', \'clip.mp4\', \'\', \'video/mp4\', 64, NULL, NULL, 0, 1),'
        .' (15, 701, \'image\', \'gallery\', \'shot-iiiiiiiiii.png\', \'shot.png\', \'\', \'image/png\', 70, 1, 1, 0, 2),'
        .' (16, 702, \'image\', \'gallery\', \'shot-iiiiiiiiii.png\', \'still.png\', \'\', \'image/png\', 70, 1, 1, 0, 0)');
}

# The markup of one mode read back from a page: the elements of the marked class, their tags, and the titles, chips, resource links, images and foot buttons inside them
function getRouteModeBits(string $html, string $mark): array {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $in = '//*[contains(concat(" ", @class, " "), " '.$mark.' ")]';
    $list = iterator_to_array($xp->query($in));
    $text = fn(string $path): array => array_map(fn($v) => trim($v->textContent), iterator_to_array($xp->query($in.$path)));
    return ['count' => count($list), 'tags' => array_values(array_unique(array_map(fn($v) => $v->nodeName, $list))), 'titles' => $text('//*[contains(@class, "sl-title")]'),
        'chips' => $text('//*[contains(@class, "sl-chip")]'), 'links' => $text('//a[contains(@href, "op=asset")]/@href'), 'images' => $text('//img/@src'),
        'buttons' => count($xp->query($in.'//*[contains(@class, "sl-meta-foot")]//a[contains(@class, "sl-but")]'))];
}

# The order of the marks inside one answered page, each by the offset of its first occurrence, missing marks last
function getRouteModeOrder(string $html, array $marks): array {
    $pos = [];
    foreach ($marks as $one) $pos[$one] = strpos($html, $one);
    $pos = array_filter($pos, fn($v) => $v !== false);
    asort($pos);
    return array_keys($pos);
}

# The display modes over real requests: every list and one view of each type, read back by the classes of its mode; article keeps the base set
# The related cards of a media material come in the grid of its mode as well
function getRouteModes(PDO $pdo): array {
    $pdo->exec('INSERT INTO '.RPREF.'_node_relations (nid, rid, type, sort) VALUES (701, 702, \'related\', 0)');
    $get = fn(string $path): array => getRouteReply('', 'GET', $path);
    $out = [];
    foreach (['news', 'docs', 'faq', 'files', 'media'] as $name) {
        $one = $get('index.php?name='.$name);
        $out['list'][$name] = [$one['code'], str_contains($one['body'], 'sl-node-'), substr_count($one['body'], 'class="sl-post sl-card"')];
    }
    $one = $get('index.php?name=news&op=view&id=101');
    $out['news'] = [$one['code'], str_contains($one['body'], 'sl-node-')];
    $out['docs'] = getRouteModeBits($get('index.php?name=docs')['body'], 'sl-node-toc');
    $one = $get('index.php?name=docs&op=view&id=201')['body'];
    $out['docsview'] = [str_contains($one, 'sl-views'), str_contains($one, 'sl-author'), str_contains($one, 'sl-date')];
    $out['faq'] = getRouteModeBits($get('index.php?name=faq')['body'], 'sl-node-faq');
    $one = $get('index.php?name=faq&op=view&id=501')['body'];
    $out['faqview'] = [str_contains($one, '<h1 class="sl-title">How to reset a password?</h1>'), str_contains($one, 'sl-views'), str_contains($one, 'sl-author')];
    $out['files'] = getRouteModeBits($get('index.php?name=files')['body'], 'sl-node-file');
    $out['filesview'] = getRouteModeOrder($get('index.php?name=files&op=view&id=601')['body'], ['sl-entry-content', 'bi-download']);
    $out['media'] = getRouteModeBits($get('index.php?name=media')['body'], 'sl-node-tile');
    $out['grid'] = substr_count($get('index.php?name=media')['body'], 'class="sl-node-tiles"');
    $one = $get('index.php?name=media&op=view&id=701')['body'];
    $out['mediaview'] = [getRouteModeOrder($one, ['sl-entry-content', '<video']), substr_count($one, 'class="sl-node-tiles"'),
        getRouteModeBits($one, 'sl-node-tile')['titles']];
    return $out;
}

# The types of the routes and feeds: docs sorted by title without the publication sort, in a feed and a block of its own, news in a feed with an attachment in an introduction
# News also has a material in a category named by a language constant, and the site has a slogan the start page must show
function addRouteSeoTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['types']['docs']['list'] = ['orders' => ['title', 'updated', 'views'], 'order' => 'title', 'dir' => 'asc'];
    $data['node']['types']['docs']['integrations'] = ['search' => false, 'rss' => true, 'sitemap' => false, 'blocks' => true, 'seo' => 'article'];
    $data['node']['types']['news']['integrations'] = ['search' => false, 'rss' => true, 'sitemap' => false, 'blocks' => false, 'seo' => 'news'];
    setRouteFile($work.'/config/node.php', $data);
    $data = require $work.'/config/global.php';
    $data['slogan'] = 'Probe slogan of the site';
    setRouteFile($work.'/config/global.php', $data);
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, ppost, lang) VALUES (4, \'news\', \'_CATEGORY\', \'\', \'0|0\', \'1|0\', \'\')');
    $pdo->exec('UPDATE '.$pre.'nodes SET cid = 4 WHERE id = 105');
    $pdo->exec('UPDATE '.$pre.'nodes SET intro = \'Intro [attach=att-aaaaaaaaaa.png align=left title=att]\' WHERE id = 105');
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status, published)'
        .' VALUES (202, 2, 0, 3, \'\', \'127.0.0.1\', \'Zebra doc\', \'intro of 202\', \'Body of zebra\', \'\', 2, \'2026-01-10 10:00:00\')');
    $pdo->exec('INSERT INTO '.$pre.'blocks (id, bkey, title, content, url, bpos, weight, status, refresh, time, lang, bfile, view, expire, action, which, param)'
        .' VALUES (60, \'\', \'Docs last block\', \'\', \'\', \'c\', 1, 1, 0, \'\', \'\', \'node.php\', 0, \'0\', \'d\', \'rss\', \'{"type":"docs","mode":"last","limit":5}\'),'
        .' (61, \'\', \'Stale seo block\', \'\', \'\', \'c\', 2, 1, 0, \'\', \'\', \'node.php\', 0, \'0\', \'d\', \'rss\', \'{"type":"off","mode":"last","limit":2}\')');
}

# The canonical route answers: canonical addresses beside foreign parameters, the description of the start page, a category named by a constant in view and feed
# Then the date order of the docs block and feed, a page past the end with the default sort, the feeds without rss and the alternate link, absolute description addresses
# Last the whole correspondence of a support card with twenty root messages and a branch of six replies
function getRouteSeo(PDO $pdo, string $work): array {
    global $rport;
    $pre = RPREF.'_';
    $site = 'http://127.0.0.1:'.$rport;
    $get = fn(string $path, string $who = ''): array => getRouteReply($who, 'GET', $path);
    $link = fn(string $html, string $rel): string => preg_match('#<link\s+rel="'.$rel.'"[^>]*href="([^"]*)"#s', $html, $hit) ? html_entity_decode($hit[1]) : '';
    $meta = fn(string $html): string => preg_match('#<meta\s+name="description"\s+content="([^"]*)"#', $html, $hit) ? html_entity_decode($hit[1]) : '';
    $out = [];
    $junk = $get('index.php?name=news&op=view&id=102&cat=5&num=3&let=Q&uname=zed')['body'];
    $out['canon'] = [$link($junk, 'canonical'), $link($get('index.php?name=news&op=view&id=102')['body'], 'canonical'),
        $link($get('index.php?name=news&cat=1&id=9&uname=zed')['body'], 'canonical'), $link($get('index.php?name=news&num=2&id=9')['body'], 'canonical'),
        $link($get('')['body'], 'canonical'), $link($get('index.php?id=5&uname=zed')['body'], 'canonical'), str_contains($junk, 'content="index, follow"')];
    $out['site'] = $site;
    $home = $get('')['body'];
    $out['desc'] = [$meta($home), str_contains($home, 'property="og:description" content="Probe slogan of the site"'), $meta($get('index.php?name=news')['body'])];
    $feed = $get('index.php?go=rss&name=news');
    $word = preg_match("#define\('_CATEGORY', *'([^']+)'\)#", (string)file_get_contents(BASE_DIR.'/lang/ru.php'), $hit) ? $hit[1] : '?';
    $gamma = $get('index.php?name=news&op=view&id=105')['body'];
    $head = preg_match('#<title>([^<]*)</title>#', $gamma, $hit) ? $hit[1] : '';
    $out['const'] = [str_contains($gamma, '_CATEGORY'), str_contains($head, $word), str_contains($get('index.php?name=news')['body'], '_CATEGORY'),
        str_contains($feed['body'], '_CATEGORY'), preg_match('#<title>Gamma</title>.*?<category>([^<]*)</category>#s', $feed['body'], $hit) ? $hit[1] === $word : false];
    $cut = fn(string $html, string $mark): string => (string)substr($html, (int)strpos($html, $mark), 3000);
    $page = $get('index.php?name=rss')['body'];
    $blk = $cut($page, 'Docs last block');
    $get('index.php?name=rss&num=2');
    $form = getRouteReply('anna', 'GET', 'index.php?name=news&op=add')['body'];
    $out['hint'] = [str_contains($form, 'id="f-relrel-hint"'), str_contains($form, 'aria-describedby="f-relrel-hint"'), str_contains($form, 'id=""')];
    $docs = $get('index.php?go=rss&name=docs')['body'];
    $out['order'] = [strpos($blk, 'Zebra doc') !== false && strpos($blk, 'Zebra doc') < (int)strpos($blk, 'Doc one'),
        strpos($docs, '<title>Zebra doc</title>') !== false && strpos($docs, '<title>Zebra doc</title>') < (int)strpos($docs, '<title>Doc one</title>'),
        getRouteModeOrder($get('index.php?name=docs')['body'], ['Doc one', 'Zebra doc'])];
    $far = $get('index.php?name=news&order=published&dir=desc&num=9');
    $near = $get('index.php?name=news&order=published&dir=desc&num=2');
    $out['bound'] = [$far['code'], $far['head']['location'] ?? '', $near['code'], $near['head']['location'] ?? ''];
    $move = function (string $path, string $who = ''): array {
        $one = getRouteReply($who, 'GET', $path);
        return [$one['code'], $one['head']['location'] ?? ''];
    };
    $wild = function (string $target) use ($site): array {
        $curl = curl_init($site.'/');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_REQUEST_TARGET => $target, CURLOPT_TIMEOUT => 60]);
        $raw = (string)curl_exec($curl);
        return [(int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), preg_match('#^Location:\s*(\S+)#mi', $raw, $hit) ? $hit[1] : ''];
    };
    $gone = $get('news.html');
    $out['retired'] = [$gone['code'], str_contains($gone['body'], 'content="noindex, follow"'), $get('faq-cat-39.html')['code'], $get('config/db.php')['code'],
        $move('index.php/index.php?name=sitemap'), $move('index.php/'), $move('index.php'), $move('admin.php/x', 'root'), $wild('//evil.example/index.php'),
        $wild('//evil.example/index.php/x'), $get('templates/lite/index.php?error=403')['code'], $get('uploads/presentation/none.png')['code']];
    $out['kept'] = [$get('')['code'], $get('index.php?name=news')['code'], getRouteReply('root', 'GET', 'admin.php')['code'], $get('error.html')['code'],
        str_contains($get('error.html')['body'], 'Service temporarily unavailable')];
    $plain = html_entity_decode($feed['body']);
    $out['rss'] = [$get('index.php?go=rss&name=help')['code'], $get('index.php?go=rss&name=nosuch')['code'], $get('index.php?go=rss&name=off')['code'],
        $get('index.php?go=rss')['code'], str_contains($get('index.php?go=rss')['body'], '<title>Beta</title>'),
        $link($get('index.php?name=docs&op=view&id=201')['body'], 'alternate'), $link($get('index.php?name=rss')['body'], 'alternate'),
        str_contains($plain, '="'.$site.'/index.php?go=file&amp;own=node&amp;id=105'), str_contains($plain, '="index.php?go=file')];
    $tok = getRouteToken(getRouteReply('anna', 'GET', 'index.php?name=help&op=add')['body'], 'name="action"');
    $made = getRouteReply('anna', 'POST', 'index.php?name=help&op=add', ['title' => 'Seo request', 'intro' => 'Seo request intro', 'body' => 'Seo request body', 'cid' => '3',
        'action' => 'submit', 'token' => $tok]);
    $aid = (int)$pdo->query('SELECT id FROM '.$pre.'nodes WHERE title = \'Seo request\'')->fetchColumn();
    $st = $pdo->prepare('INSERT INTO '.$pre.'comment (id, pid, cid, modul, time, uid, name, ip, body, status) VALUES (?, ?, ?, \'help\', ?, 2, \'anna\', \'127.0.0.1\', ?, 1)');
    for ($i = 1; $i <= 20; $i++) $st->execute([900 + $i, 0, $aid, sprintf('2026-02-01 10:%02d:00', $i), sprintf('Root message %02d', $i)]);
    for ($i = 1; $i <= 6; $i++) $st->execute([950 + $i, 901, $aid, sprintf('2026-02-02 10:%02d:00', $i), 'Reply message '.$i]);
    $desk = getRouteReply('root', 'GET', 'admin.php?name=node&op=support&id='.$aid);
    $out['support'] = [$made['code'], $aid > 0, $desk['code'], preg_match_all('/Root message \d\d/', $desk['body']), preg_match_all('/Reply message \d/', $desk['body'])];
    $out['data'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($work).' seoext 2>&1'), true);
    $log = is_file($work.'/logs/error_site.log') ? (string)file_get_contents($work.'/logs/error_site.log') : '';
    $out['stale'] = [str_contains($page, 'Stale seo block'), str_contains($log, 'Block node.php'), str_contains($log, '404 Not Found')];
    return $out;
}

# The setup of the head run before the server starts: docs is the faq type of the header marquee, a guest may submit news, anna keeps one favorite
# A mixed Node block of home materials on the feeds page has nothing to list, because no type has the home feature
function addRouteHeadTypes(PDO $pdo, string $work): void {
    $pre = RPREF.'_';
    $data = require $work.'/config/node.php';
    $data['node']['types']['docs']['view'] = ['mode' => 'faq'];
    $data['node']['types']['news']['workflow'] = ['access' => 'all'];
    setRouteFile($work.'/config/node.php', $data);
    $pdo->exec('INSERT INTO '.$pre.'favorites (uid, fid, modul, time) VALUES (2, 101, \'news\', NOW())');
    $pdo->exec('INSERT INTO '.$pre.'blocks (id, bkey, title, content, url, bpos, weight, status, refresh, time, lang, bfile, view, expire, action, which, param)'
        .' VALUES (70, \'\', \'Empty head block\', \'\', \'\', \'c\', 1, 1, 0, \'\', \'\', \'node.php\', 0, \'0\', \'d\', \'rss\', \'{"type":"","mode":"home","limit":5}\')');
}

# The route answers of notices and the page cache: a page past the reader bound, the notice after a submission once and the list cached after it, the notice after a report once
# The header marquee shows the latest faq material, a closed category stays out of the banner and the breadcrumb, and the start page keeps its canonical address
# Letters keep the category, an empty block is left out, and the favorites are read without a token in their address
function getRouteHead(): array {
    global $rport;
    $site = 'http://127.0.0.1:'.$rport;
    $get = fn(string $path, string $who = ''): array => getRouteReply($who, 'GET', $path);
    $link = fn(string $html): string => preg_match('#<link\s+rel="canonical"[^>]*href="([^"]*)"#s', $html, $hit) ? html_entity_decode($hit[1]) : '';
    $word = fn(string $file, string $key): string => preg_match("#define\('".$key."', *'([^']+)'\)#", (string)file_get_contents(BASE_DIR.'/'.$file), $hit) ? $hit[1] : '?';
    $crumb = fn(string $html): string => preg_match('#<div class="sl-breadcrumbs">(.*?)</div>#s', $html, $hit) ? $hit[1] : '';
    $trail = fn(string $html): string => preg_match('#"@type":\s*"BreadcrumbList".*?\]#s', $html, $hit) ? $hit[0] : '';
    $far = $get('index.php?name=news&num=999999999');
    $out = ['bound' => [$far['code'], $get('index.php?name=news&num=4294967295')['code'], $get('index.php?name=news&op=view&id=4294967295')['code']]];
    $mark = $get('index.php?name=news')['body'];
    $out['faq'] = preg_match('#sl-head-marquee">(.*?)</li>#s', $mark, $hit) ? trim(strip_tags($hit[1])) : '';
    preg_match_all('#(?:href|src)="((?:templates|plugins)/[^"?\#]+)#', $mark, $hits);
    $files = array_values(array_unique($hits[1]));
    $out['assets'] = [count($files), array_values(array_filter($files, fn(string $v): bool => $get(html_entity_decode($v))['code'] !== 200))];
    $form = $get('index.php?name=news&op=add')['body'];
    $post = ['title' => 'Head notice', 'aname' => 'Guest', 'intro' => 'Intro', 'body' => 'Body', 'action' => 'submit', 'token' => getRouteToken($form, 'name="action"')];
    $sent = getRouteReply('', 'POST', 'index.php?name=news&op=add', $post);
    $note = $word('modules/node/lang/ru.php', '_NODE_PENDING');
    $first = $get('index.php?name=news')['body'];
    $again = $get('index.php?name=news')['body'];
    $out['notice'] = [$sent['code'], $sent['head']['location'] ?? '', str_contains($first, $note), str_contains($again, $note)];
    $tok = getRouteToken($get('index.php?name=news&op=view&id=101')['body'], 'op=report');
    $told = getRouteReply('', 'POST', 'index.php?name=news&op=report&id=2', ['token' => $tok]);
    $said = $word('modules/node/lang/ru.php', '_NODE_REPORTED');
    $out['report'] = [$told['code'], $told['head']['location'] ?? '', str_contains($get('index.php?name=news&op=view&id=101')['body'], $said),
        str_contains($get('index.php?name=news&op=view&id=101')['body'], $said)];
    $guest = $get('index.php?name=news&op=add&cat=2')['body'];
    $anna = $get('index.php?name=news&op=add&cat=2', 'anna')['body'];
    $view = $get('index.php?name=news&op=view&id=102&cat=2')['body'];
    $own = $get('index.php?name=news&op=view&id=101&cat=2')['body'];
    $out['banner'] = [str_contains($crumb($guest), 'Members'), str_contains($trail($guest), 'Members'), str_contains($crumb($anna), 'Members'),
        str_contains($crumb($view), 'Members'), str_contains($crumb($own), 'Open') && !str_contains($crumb($own), 'Members')];
    $out['canon'] = [$link($get('index.php?cat=1')['body']), $link($get('index.php?num=2')['body']), $link($get('')['body'])];
    $out['site'] = $site;
    $list = $get('index.php?name=news&cat=1')['body'];
    $out['letters'] = [str_contains($list, 'index.php?name=news&amp;cat=1&amp;let='), str_contains($get('index.php?name=news')['body'], 'index.php?name=news&amp;let=')];
    $feeds = $get('index.php?name=rss')['body'];
    $out['block'] = [str_contains($feeds, 'Empty head block'), str_contains($feeds, $word('lang/ru.php', '_BLOCKPROBLEM2'))];
    $shelf = $get('index.php?name=account&op=favorites', 'anna')['body'];
    $part = $get('index.php?go=1&op=getFavoriteList&part=shelves', 'anna');
    $out['favorites'] = [str_contains($shelf, 'op=getFavoriteList'), str_contains($shelf, 'getFavoriteList&amp;part=shelves&amp;token='), $part['code'],
        str_contains($part['body'], 'id="favshelves"'), trim($get('index.php?go=1&op=getFavoriteList&part=shelves')['body'])];
    return $out;
}

# The child seoext of the seo run asks the core directly for the feeds of the site
function getRouteSeoData(): array {
    return ['feeds' => array_keys(getRssFeeds())];
}

# The quick edit of one comment of anna on an open material, driven over the two routes as anna, boris and the main administrator with the page token of each
# Every exchange reports its status, and every save what the row holds after it, so the order under the lock is measured rather than claimed
function getRouteQuick(PDO $pdo, string $work): array {
    $pre = RPREF.'_';
    $when = (new DateTime('now', new DateTimeZone((require $work.'/config/global.php')['gtime'])))->format('Y-m-d H:i:s');
    $pdo->exec('UPDATE '.$pre.'nodes SET comon = 2 WHERE id = 101');
    $pdo->prepare('INSERT INTO '.$pre.'comment (cid, modul, time, uid, name, ip, body, status, shown)'
        .' VALUES (101, \'news\', ?, 2, \'anna\', \'127.0.0.1\', \'Anna first text\', 1, ?)')->execute([$when, $when]);
    $cid = (int)$pdo->lastInsertId();
    $tok = fn(string $who): string => preg_match('#id="formcsave".*?name="token"\s+value="([a-f0-9]{64})"#s',
        getRouteReply($who, 'GET', 'index.php?name=news&op=view&id=101')['body'], $hit) ? $hit[1] : '';
    $toks = ['anna' => $tok('anna'), 'boris' => $tok('boris'), 'root' => $tok('root'), 'moder' => $tok('moder')];
    $head = fn(string $who): array => ['X-CSRF-TOKEN: '.$toks[$who]];
    $subj = 'kind=comment&id='.$cid.'&field=body';
    $get = fn(string $who, string $query = '', ?array $hdr = null): array => getRouteReply($who, 'GET', 'index.php?go=1&op=getQuickEdit&'.($query ?: $subj), [],
        $hdr ?? $head($who));
    $post = fn(string $who, array $form, ?array $hdr = null): array => getRouteReply($who, 'POST', 'index.php?go=1&op=updateQuickEdit',
        $form + ['kind' => 'comment', 'id' => (string)$cid, 'field' => 'body'], $hdr ?? $head($who));
    $row = fn(): array => $pdo->query('SELECT body, edited FROM '.$pre.'comment WHERE id = '.$cid)->fetch(PDO::FETCH_ASSOC);
    $stamp = fn(array $one): string => sha1($one['body']."\0".(string)$one['edited']);
    $out = ['ids' => [$cid], 'tokens' => array_map(fn($v) => $v !== '', $toks)];
    $open = $get('anna');
    $first = getRouteField($open['body'], 'stamp');
    $out['open'] = [$open['code'], str_contains($open['body'], 'data-sl-quick-form'), $first === $stamp($row()), str_contains($open['body'], 'id="quick-comment-'.$cid.'-body"'),
        str_contains($open['body'], 'Anna first text'), str_contains($open['body'], '"X-CSRF-TOKEN": "'.$toks['anna'].'"')];
    $wrong = getRouteReply('anna', 'POST', 'index.php?go=1&op=getQuickEdit&'.$subj, [], $head('anna'));
    $right = getRouteReply('anna', 'GET', 'index.php?go=1&op=updateQuickEdit&'.$subj, [], $head('anna'));
    $out['methods'] = [$wrong['code'], $wrong['head']['allow'] ?? '', $right['code'], $right['head']['allow'] ?? ''];
    $was = $row();
    $out['tokens'] += [
        'none' => $get('anna', '', [])['code'],
        'param' => $get('anna', $subj.'&token='.$toks['anna'], [])['code'],
        'postparam' => $post('anna', ['stamp' => $first, 'text' => 'Anna by parameter', 'token' => $toks['anna']], [])['code'],
        'stale' => $post('anna', ['stamp' => $first, 'text' => 'Anna by stale token'], ['X-CSRF-TOKEN: '.str_repeat('0', 64)])['code'],
        'kept' => $row() === $was,
    ];
    $out['forms'] = [
        $get('anna', 'kind=bogus&id='.$cid.'&field=body')['code'], $get('anna', 'kind=comment&id=0&field=body')['code'],
        $get('anna', 'kind=comment&id='.$cid.'&field=intro')['code'], $post('anna', ['stamp' => 'short', 'text' => 'Anna short stamp'])['code'],
        $post('anna', ['stamp' => $first, 'text' => 'Anna bad kind', 'kind' => 'node'])['code'], $row() === $was,
    ];
    $save = $post('anna', ['stamp' => $first, 'text' => 'Anna second text']);
    $saved = $row();
    $out['save'] = [$save['code'], str_contains($save['body'], 'Anna second text'), str_contains($save['body'], 'id="quick-mark-comment-'.$cid.'" hx-swap-oob="outerHTML"'),
        $saved['body'], $saved['edited'] !== null, str_contains($save['body'], 'data-sl-quick-form')];
    sleep(1);
    $again = $post('anna', ['stamp' => $first, 'text' => 'Anna second text']);
    $out['repeat'] = [$again['code'], $row() === $saved];
    $clash = $post('anna', ['stamp' => $first, 'text' => 'Anna third text']);
    $now = preg_match('#data-sl-quick-stamp="([a-f0-9]{40})"#', $clash['body'], $hit) ? $hit[1] : '';
    $out['conflict'] = [$clash['code'], $now === $stamp($row()), str_contains($clash['body'], 'Anna second text'), $row() === $saved];
    $fresh = $stamp($row());
    $out['rules'] = [$post('anna', ['stamp' => $fresh, 'text' => str_repeat('w', 101)])['code'], $post('anna', ['stamp' => $fresh, 'text' => '  '])['code'], $row() === $saved];
    $out['foreign'] = [$get('boris')['code'], $post('boris', ['stamp' => $fresh, 'text' => 'Boris took it'])['code'], $row() === $saved];
    $moder = $post('root', ['stamp' => $fresh, 'text' => 'Root fixed it']);
    $out['moder'] = [$get('root')['code'], $moder['code'], $row()['body']];
    $open = $get('anna');
    $pdo->exec('UPDATE '.$pre.'comment SET time = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = '.$cid);
    $kept = $row();
    $out['lost'] = [$open['code'], $post('anna', ['stamp' => getRouteField($open['body'], 'stamp'), 'text' => 'Anna too late'])['code'], $get('anna')['code'], $row() === $kept];
    $pdo->exec('UPDATE '.$pre.'comment SET deleted = NOW() WHERE id = '.$cid);
    $out['gone'] = [$get('root')['code'], $post('root', ['stamp' => $stamp($kept), 'text' => 'Root after the delete'])['code'], $row() === $kept];
    $out['forum'] = getRouteQuickForum($pdo, $head);
    $out['node'] = getRouteQuickNode($pdo, $when, $head);
    return $out;
}

# The quick edit of the published material 101 of anna: her own entry on the page, a save that sends it back to moderation with its note, the repeat and the conflict
# Then the moderator in the dial before the editor, a stranger, a field the kind does not carry, and the list whose cards offer no quick edit
function getRouteQuickNode(PDO $pdo, string $when, Closure $head): array {
    $pre = RPREF.'_';
    $pdo->prepare('UPDATE '.$pre.'nodes SET created = ? WHERE id = 101')->execute([$when]);
    $get = fn(string $who, string $field = 'body'): array => getRouteReply($who, 'GET', 'index.php?go=1&op=getQuickEdit&kind=node&id=101&field='.$field, [], $head($who));
    $post = fn(string $who, string $stamp, string $text, string $field = 'body'): array => getRouteReply($who, 'POST', 'index.php?go=1&op=updateQuickEdit',
        ['kind' => 'node', 'id' => '101', 'field' => $field, 'stamp' => $stamp, 'text' => $text], $head($who));
    $row = fn(): array => $pdo->query('SELECT status, version, body, intro FROM '.$pre.'nodes WHERE id = 101')->fetch(PDO::FETCH_ASSOC);
    $page = getRouteReply('anna', 'GET', 'index.php?name=news&op=view&id=101')['body'];
    $list = getRouteReply('moder', 'GET', 'index.php?name=news')['body'];
    $mod = getRouteReply('moder', 'GET', 'index.php?name=news&op=view&id=101')['body'];
    $out = ['page' => [str_contains($page, 'kind=node&amp;id=101&amp;field=body'), str_contains($page, 'id="repnode101body" data-sl-quick'),
        str_contains(getRouteReply('boris', 'GET', 'index.php?name=news&op=view&id=101')['body'], 'op=getQuickEdit'), str_contains($list, 'op=getQuickEdit'),
        strpos($mod, 'field=intro') !== false && strpos($mod, 'field=intro') < strpos($mod, 'op=edit&amp;id=101')]];
    $open = $get('anna');
    $was = $row();
    $out['open'] = [$open['code'], getRouteField($open['body'], 'stamp') === (string)$was['version'], str_contains($open['body'], 'id="quick-node-101-body"')];
    $save = $post('anna', (string)$was['version'], 'Body by anna quickly');
    $now = $row();
    $note = str_contains($save['head']['hx-trigger'] ?? '', 'sl-quick-note');
    $out['save'] = [$save['code'], str_contains($save['body'], 'Body by anna quickly'), $note,
        intval($now['status']), intval($now['version']) - intval($was['version'])];
    $out['repeat'] = [$post('anna', (string)$was['version'], 'Body by anna quickly')['code'], $row() === $now];
    $out['conflict'] = [$post('anna', (string)$was['version'], 'Body by anna again')['code'], $row() === $now];
    $out['foreign'] = [$get('boris')['code'], $post('boris', (string)$now['version'], 'Body by boris')['code'], $get('anna', 'title')['code']];
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, status) VALUES (106, 1, 0, 2, \'\', \'127.0.0.1\', \'Draft\','
        .' \'intro\', \'body\', \'\', 0)');
    $draft = getRouteReply('boris', 'POST', 'index.php?go=1&op=updateQuickEdit', ['kind' => 'node', 'id' => '106', 'field' => 'body', 'stamp' => '1', 'text' => 'x'],
        $head('boris'));
    $out['draft'] = [getRouteReply('boris', 'GET', 'index.php?go=1&op=getQuickEdit&kind=node&id=106&field=body', [], $head('boris'))['code'], $draft['code'],
        getRouteReply('boris', 'GET', 'index.php?go=1&op=getQuickEdit&kind=node&id=102&field=body', [], $head('boris'))['code']];
    $fix = $post('moder', (string)$now['version'], 'Intro by the moderator', 'intro');
    $out['moder'] = [$fix['code'], isset($fix['head']['hx-trigger']), $row()['intro'], intval($row()['status'])];
    return $out;
}

# The quick edit of a reply of anna in an open topic: a save, its repeat, a conflict, and the place read again under the lock when the topic closes or the post moves
function getRouteQuickForum(PDO $pdo, Closure $head): array {
    $pre = RPREF.'_';
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pview, pread, ppost, preply, pedit, pdelete, pmod, lang) VALUES'
        .' (4, \'forum\', \'Board\', \'\', \'0|0\', \'0|0\', \'1|0\', \'1|0\', \'1|0\', \'1|0\', \'\', \'\'),'
        .' (5, \'forum\', \'Locked\', \'\', \'0|0\', \'0|0\', \'1|0\', \'1|0\', \'\', \'\', \'\', \'\')');
    $pdo->exec('INSERT INTO '.$pre.'forum (id, pid, cid, uid, name, title, time, body, field, luid, lname, ltime, status) VALUES'
        .' (501, 0, 4, 2, \'anna\', \'Anna topic\', NOW(), \'Anna topic body\', \'\', 2, \'anna\', NOW(), 3),'
        .' (502, 501, 4, 2, \'anna\', \'Anna topic\', NOW(), \'Anna reply body\', \'\', 2, \'anna\', NOW(), 3)');
    $subj = 'kind=forum&id=502&field=body';
    $get = fn(string $who): array => getRouteReply($who, 'GET', 'index.php?go=1&op=getQuickEdit&'.$subj, [], $head($who));
    $post = fn(string $who, string $stamp, string $text): array => getRouteReply($who, 'POST', 'index.php?go=1&op=updateQuickEdit',
        ['kind' => 'forum', 'id' => '502', 'field' => 'body', 'stamp' => $stamp, 'text' => $text], $head($who));
    $row = fn(): array => $pdo->query('SELECT cid, body, etime FROM '.$pre.'forum WHERE id = 502')->fetch(PDO::FETCH_ASSOC);
    $open = $get('anna');
    $first = getRouteField($open['body'], 'stamp');
    $out = ['open' => [$open['code'], str_contains($open['body'], 'id="quick-forum-502-body"'), $first === sha1('Anna reply body'."\0")]];
    $save = $post('anna', $first, 'Anna reply edited');
    $saved = $row();
    $out['save'] = [$save['code'], str_contains($save['body'], 'Anna reply edited'), str_contains($save['body'], 'id="quick-mark-forum-502" hx-swap-oob="outerHTML"'),
        $saved['body'], $saved['etime'] !== null];
    sleep(1);
    $out['repeat'] = [$post('anna', $first, 'Anna reply edited')['code'], $row() === $saved];
    $out['conflict'] = [$post('anna', $first, 'Anna reply again')['code'], $row() === $saved];
    $out['foreign'] = [$get('boris')['code'], $post('boris', sha1($saved['body']."\0".$saved['etime']), 'Boris took it')['code'], $row() === $saved];
    $alien = $post('anna', sha1($saved['body']."\0".$saved['etime']), 'Anna takes [attach=other-bbbbbbbbbb-3.png align=left title=o]');
    $out['names'] = [$alien['code'], str_contains($alien['body'], ': other-bbbbbbbbbb-3.png'), $row() === $saved];
    $fresh = getRouteField($get('anna')['body'], 'stamp');
    $pdo->exec('UPDATE '.$pre.'forum SET status = 1 WHERE id = 501');
    $out['closed'] = [$post('anna', $fresh, 'Anna after the close')['code'], $get('anna')['code'], $row() === $saved];
    $pdo->exec('UPDATE '.$pre.'forum SET status = 3 WHERE id = 501');
    $fresh = getRouteField($get('anna')['body'], 'stamp');
    $pdo->exec('UPDATE '.$pre.'forum SET cid = 5 WHERE id IN (501, 502)');
    $out['moved'] = [$post('anna', $fresh, 'Anna after the move')['code'], $row()['body'] === $saved['body']];
    $out['moder'] = [$post('root', $fresh, 'Root fixed the reply')['code'], $row()['body']];
    return $out;
}

# The forum files on the file route for a guest, a member, the club and a moderator, the preview of an own upload, the topic page and the three writers of a reply
function getRouteFiles(PDO $pdo): array {
    $pre = RPREF.'_';
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pview, pread, ppost, preply, pedit, pdelete, pmod, lang) VALUES'
        .' (6, \'forum\', \'Open\', \'\', \'0|0\', \'0|0\', \'1|0\', \'1|0\', \'1|0\', \'1|0\', \'\', \'\'),'
        .' (7, \'forum\', \'Club\', \'\', \'1|1\', \'1|1\', \'1|1\', \'1|1\', \'1|1\', \'1|1\', \'\', \'\')');
    $rows = [[601, 0, 6, 'Open topic', 'fa', 3], [602, 601, 6, 'Open topic', 'fb', 3], [603, 601, 6, 'Open topic', 'fc', 0], [611, 0, 6, 'Hidden topic', 'fd', 1],
        [621, 0, 7, 'Club topic', 'fe', 3]];
    $st = $pdo->prepare('INSERT INTO '.$pre.'forum (id, pid, cid, uid, name, title, time, body, field, luid, lname, ltime, status)'
        .' VALUES (?, ?, ?, 3, \'boris\', ?, \'2026-01-01 10:00:00\', ?, \'\', 3, \'boris\', \'2026-01-01 10:00:00\', ?)');
    foreach ($rows as [$id, $pid, $cid, $title, $key, $stat]) $st->execute([$id, $pid, $cid, $title, 'Text [attach='.$key.'-aaaaaaaaaa.png align=left title=a]', $stat]);
    $code = fn(string $who, string $path): int => getRouteReply($who, 'GET', $path)['code'];
    $url = fn(int $id, string $key, string $tail = ''): string => 'index.php?go=file&own=forum&id='.$id.'&key='.$key.'-aaaaaaaaaa.png'.$tail;
    $view = fn(string $key): string => 'index.php?go=file&own=forum&name=forum&key='.$key.'&preview=1';
    $one = getRouteReply('', 'GET', $url(601, 'fa'));
    $out = ['file' => [$one['code'], $one['head']['content-type'] ?? '', $one['head']['cache-control'] ?? ''],
        'reader' => [$code('', $url(602, 'fb')), $code('', $url(601, 'fa', '&thumb=1')), $code('', $url(603, 'fc')), $code('', $url(611, 'fd')), $code('', $url(621, 'fe')),
            $code('', $url(601, 'fb')), $code('', $url(601, 'free')), $code('', $url(602, 'fb', '&thumb=1'))],
        'club' => [$code('anna', $url(621, 'fe')), $code('boris', $url(621, 'fe')), $code('anna', $url(611, 'fd'))],
        'moder' => [$code('root', $url(603, 'fc')), $code('root', $url(611, 'fd')), $code('root', $url(621, 'fe')), $code('moder', $url(611, 'fd'))],
        'direct' => [$code('', 'uploads/forum/fa-aaaaaaaaaa.png'), $code('root', 'uploads/forum/fa-aaaaaaaaaa.png')],
        'preview' => [$code('anna', $view('mine-aaaaaaaaaa-2.png')), $code('boris', $view('mine-aaaaaaaaaa-2.png')), $code('', $view('mine-aaaaaaaaaa-2.png')),
            $code('root', $view('mine-aaaaaaaaaa-2.png')), $code('anna', $view('other-bbbbbbbbbb-3.png')), $code('anna', $view('fa-aaaaaaaaaa.png')),
            $code('anna', 'index.php?go=file&own=forum&name=news&key=mine-aaaaaaaaaa-2.png&preview=1')]];
    $page = getRouteReply('', 'GET', 'index.php?name=forum&op=view&id=601')['body'];
    $out['page'] = [str_contains($page, 'go=file&amp;own=forum&amp;id=601&amp;key=fa-aaaaaaaaaa.png&amp;thumb=1'),
        str_contains($page, 'go=file&amp;own=forum&amp;id=602&amp;key=fb-aaaaaaaaaa.png'), str_contains($page, 'uploads/forum/')];
    $tok = getRouteToken(getRouteReply('anna', 'GET', 'index.php?name=forum&op=add&cat=6&pid=601')['body'], 'send');
    $send = fn(string $text): array => getRouteReply('anna', 'POST', 'index.php?name=forum', ['op' => 'send', 'token' => $tok, 'cat' => '6', 'pid' => '601', 'fid' => '0',
        'id' => '0', 'subject' => 'Open topic', 'hometext' => $text, 'posttype' => 'save']);
    $count = fn(): int => intval($pdo->query('SELECT COUNT(*) FROM '.$pre.'forum WHERE pid = 601')->fetchColumn());
    $was = $count();
    $bad = $send('Anna takes [attach=other-bbbbbbbbbb-3.png align=left title=o]');
    $out['send'] = [$tok !== '', $bad['code'], str_contains($bad['body'], ': other-bbbbbbbbbb-3.png'), $count() - $was];
    $out['quote'] = [$send('[quote]Text [attach=fb-aaaaaaaaaa.png align=left title=a][/quote] agreed')['code'], $count() - $was];
    $out['own'] = [$send('Anna shows [attach=mine-aaaaaaaaaa-2.png align=left title=m]')['code'], $count() - $was];
    $view = getRouteReply('anna', 'POST', 'index.php?name=forum', ['op' => 'send', 'token' => $tok, 'cat' => '6', 'pid' => '601', 'fid' => '0', 'id' => '0',
        'subject' => 'Open topic', 'hometext' => 'Preview [attach=mine-aaaaaaaaaa-2.png align=left title=m]', 'posttype' => 'preview'])['body'];
    $out['draft'] = [str_contains($view, 'go=file&amp;own=forum&amp;name=forum&amp;key=mine-aaaaaaaaaa-2.png&amp;preview=1'), $count() - $was];
    return $out;
}

# The private messages and the signature on the file route: both sides, a deleted side, a third account, a guest and a moderator, the previews and the writers that bind a name
function getRouteAccount(PDO $pdo): array {
    global $rwork;
    $pre = RPREF.'_';
    $conf = require $rwork.'/config/privat.php';
    $conf['privat'] = array_replace($conf['privat'], ['act' => '1', 'send' => '0', 'himself' => '0']);
    setRouteFile($rwork.'/config/privat.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $pdo->exec('INSERT INTO '.$pre.'privat (id, uidin, uidout, title, body, time, ip, viewed, saved, delin, delout) VALUES'
        .' (701, 2, 3, \'Kept\', \'Look [attach=pa-aaaaaaaaaa-3.png align=left title=a]\', NOW(), \'127.0.0.1\', 0, 0, 0, 0),'
        .' (702, 2, 3, \'Gone\', \'Look [attach=pb-aaaaaaaaaa-3.png align=left title=b]\', NOW(), \'127.0.0.1\', 0, 0, 1, 0),'
        .' (703, 2, 3, \'Book\', \'See [attach=brandbook.pdf align=none title=pdf file]\', NOW(), \'127.0.0.1\', 0, 0, 0, 0)');
    $pdo->exec('UPDATE '.$pre.'users SET sig = \'Boris [attach=sig-aaaaaaaaaa-3.png align=left title=s]\' WHERE id = 3');
    $code = fn(string $who, string $path): int => getRouteReply($who, 'GET', $path)['code'];
    $mail = fn(int $id, string $key, string $tail = ''): string => 'index.php?go=file&own=privat&id='.$id.'&key='.$key.$tail;
    $sign = fn(int $id, string $key): string => 'index.php?go=file&own=profile&id='.$id.'&key='.$key;
    $view = fn(string $own, string $name, string $key): string => 'index.php?go=file&own='.$own.'&name='.$name.'&key='.$key.'&preview=1';
    $out = ['privat' => [$code('anna', $mail(701, 'pa-aaaaaaaaaa-3.png')), $code('boris', $mail(701, 'pa-aaaaaaaaaa-3.png')),
        $code('anna', $mail(701, 'pa-aaaaaaaaaa-3.png', '&thumb=1')), $code('clara', $mail(701, 'pa-aaaaaaaaaa-3.png')), $code('', $mail(701, 'pa-aaaaaaaaaa-3.png')),
        $code('anna', $mail(702, 'pb-aaaaaaaaaa-3.png')), $code('boris', $mail(702, 'pb-aaaaaaaaaa-3.png')), $code('anna', $mail(701, 'mine-aaaaaaaaaa-2.png')),
        $code('root', $mail(702, 'pb-aaaaaaaaaa-3.png')), $code('moder', $mail(701, 'pa-aaaaaaaaaa-3.png'))]];
    $out['rule'] = [$code('anna', $mail(703, 'brandbook.pdf')), $code('clara', $mail(703, 'brandbook.pdf')),
        $code('anna', $view('privat', 'account', 'brandbook.pdf'))];
    $out['shut'] = [$code('anna', 'uploads/account/pa-aaaaaaaaaa-3.png'), $code('', 'uploads/profile/sig-aaaaaaaaaa-3.png')];
    $out['pview'] = [$code('anna', $view('privat', 'account', 'mine-aaaaaaaaaa-2.png')), $code('boris', $view('privat', 'account', 'mine-aaaaaaaaaa-2.png')),
        $code('', $view('privat', 'account', 'mine-aaaaaaaaaa-2.png')), $code('root', $view('privat', 'account', 'other-bbbbbbbbbb-4.png')),
        $code('anna', $view('privat', 'profile', 'mine-aaaaaaaaaa-2.png'))];
    $tok = getRouteToken(getRouteReply('anna', 'GET', 'index.php?name=account&op=privat&typ=4')['body'], 'op=addPrivateMessage');
    $page = getRouteReply('anna', 'POST', 'index.php?go=1&op=setPrivateMessageRead', ['token' => $tok, 'id' => '701', 'cid' => '1'])['body'];
    $out['mpage'] = [str_contains($page, 'go=file&amp;own=privat&amp;id=701&amp;key=pa-aaaaaaaaaa-3.png'),
        str_contains($page, 'go=file&amp;own=profile&amp;id=3&amp;key=sig-aaaaaaaaaa-3.png'), str_contains($page, 'uploads/account/')];
    $send = fn(string $text): array => getRouteReply('anna', 'POST', 'index.php?go=1&op=addPrivateMessage', ['token' => $tok, 'name' => 'clara',
        'title' => 'Note', 'text' => $text], ['HX-Request: true']);
    $count = fn(): int => intval($pdo->query('SELECT COUNT(*) FROM '.$pre.'privat WHERE uidout = 2')->fetchColumn());
    $bad = $send('Anna takes [attach=other-bbbbbbbbbb-4.png align=left title=o]');
    $out['msend'] = [$tok !== '', str_contains($bad['body'], ': other-bbbbbbbbbb-4.png'), $count()];
    $out['mgone'] = [str_contains($send('Anna takes [attach=pb-aaaaaaaaaa-3.png align=left title=b]')['body'], ': pb-aaaaaaaaaa-3.png'), $count()];
    $send('Forward [attach=pa-aaaaaaaaaa-3.png align=left title=a]');
    $fwd = intval($pdo->query('SELECT MAX(id) FROM '.$pre.'privat WHERE uidout = 2')->fetchColumn());
    $send('Mine [attach=mine-aaaaaaaaaa-2.png align=left title=m]');
    $out['mfwd'] = [$count(), $code('clara', $mail($fwd, 'pa-aaaaaaaaaa-3.png')), $code('boris', $mail($fwd, 'pa-aaaaaaaaaa-3.png'))];
    $out['sign'] = [$code('', $sign(3, 'sig-aaaaaaaaaa-3.png')), $code('clara', $sign(3, 'sig-aaaaaaaaaa-3.png')), $code('', $sign(2, 'sig-aaaaaaaaaa-3.png')),
        $code('', $sign(3, 'own-aaaaaaaaaa-2.png'))];
    $conf = require $rwork.'/config/users.php';
    $conf['users']['prof'] = '1';
    setRouteFile($rwork.'/config/users.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $out['closed'] = [$code('', $sign(3, 'sig-aaaaaaaaaa-3.png')), $code('anna', $sign(3, 'sig-aaaaaaaaaa-3.png'))];
    $conf['users']['prof'] = '0';
    setRouteFile($rwork.'/config/users.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $out['sview'] = [$code('anna', $view('profile', 'profile', 'own-aaaaaaaaaa-2.png')), $code('boris', $view('profile', 'profile', 'own-aaaaaaaaaa-2.png')),
        $code('anna', $view('profile', 'profile', 'other-bbbbbbbbbb-4.png'))];
    $page = getRouteReply('', 'GET', 'index.php?name=forum&op=view&id=601')['body'];
    $out['spage'] = [str_contains($page, 'go=file&amp;own=profile&amp;id=3&amp;key=sig-aaaaaaaaaa-3.png'), str_contains($page, 'uploads/account/')];
    $page = getRouteReply('', 'GET', 'index.php?name=account&op=view&uname=boris')['body'];
    $out['sprof'] = str_contains($page, 'go=file&amp;own=profile&amp;id=3&amp;key=sig-aaaaaaaaaa-3.png');
    $conf = require $rwork.'/config/fields.php';
    $conf['fields']['account'] = [];
    setRouteFile($rwork.'/config/fields.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $form = getRouteReply('anna', 'GET', 'index.php?name=account&op=edithome')['body'];
    $tok = getRouteToken($form, 'savehome');
    $save = fn(string $sig): array => getRouteReply('anna', 'POST', 'index.php?name=account', ['op' => 'savehome', 'token' => $tok, 'mail' => 'anna@probe.test',
        'sig' => $sig, 'block' => '']);
    $sig = fn(): string => (string)$pdo->query('SELECT sig FROM '.$pre.'users WHERE id = 2')->fetchColumn();
    $bad = $save('Anna [attach=other-bbbbbbbbbb-4.png align=left title=o]');
    $out['ssave'] = [$tok !== '', str_contains($bad['body'], ': other-bbbbbbbbbb-4.png'), $sig()];
    $save('Anna [attach=own-aaaaaaaaaa-2.png align=left title=m]');
    $out['sown'] = [$sig(), $code('', $sign(2, 'own-aaaaaaaaaa-2.png')), str_contains($form, 'place=profile.attach')];
    return $out;
}

# The unused filter of the upload browser in every covered folder: an old file no row names is listed, a named one of any state, its thumb and a fresh one are not
function getRouteUnused(PDO $pdo): array {
    global $rwork;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    mkdir($rwork.'/uploads/avatars', 0777, true);
    foreach (['ava-aaaaaaaaaa.png', 'lost-aaaaaaaaaa.png'] as $one) file_put_contents($rwork.'/uploads/avatars/'.$one, $png);
    file_put_contents($rwork.'/uploads/voting/lost-aaaaaaaaaa.png', $png);
    $pdo->exec('UPDATE '.RPREF.'_users SET avatar = \'ava-aaaaaaaaaa.png\' WHERE id = 2');
    $old = ['forum/fa-aaaaaaaaaa.png', 'forum/free-aaaaaaaaaa.png', 'forum/thumb/fa-aaaaaaaaaa.png', 'node/news/att-aaaaaaaaaa.png', 'node/news/cover-cccccccccc.png',
        'node/news/manual-dddddddddd.pdf', 'node/news/other-bbbbbbbbbb.png', 'avatars/ava-aaaaaaaaaa.png', 'avatars/lost-aaaaaaaaaa.png', 'account/pa-aaaaaaaaaa-3.png',
        'account/other-bbbbbbbbbb-4.png', 'profile/sig-aaaaaaaaaa-3.png', 'profile/pc-aaaaaaaaaa-4.png', 'profile/other-bbbbbbbbbb-4.png', 'voting/va-aaaaaaaaaa-3.png',
        'voting/other-bbbbbbbbbb-4.png', 'voting/vb-aaaaaaaaaa-2.png', 'voting/lost-aaaaaaaaaa.png'];
    foreach ($old as $one) touch($rwork.'/uploads/'.$one, time() - 2 * 86400);
    $list = function (string $dir, bool $lone): array {
        $body = getRouteReply('root', 'GET', 'admin.php?name=uploads&dir='.$dir.($lone ? '&unused=1' : ''))['body'];
        preg_match_all('#<tr data-sl-fm-file="([^"]+)"#', $body, $rows);
        $num = preg_match('#bi-funnel" aria-hidden="true"></i>&nbsp;[^<]*?(\d+)</a>#', $body, $hit) ? intval($hit[1]) : -1;
        return ['rows' => $rows[1], 'count' => $num, 'body' => $body];
    };
    $forum = $list('forum', true);
    $out = ['unused' => ['forum' => [$forum['rows'], $forum['count']], 'thumb' => $list('forum/thumb', true)['rows'], 'news' => $list('node/news', true)['rows'],
        'avatars' => $list('avatars', true)['rows'], 'account' => $list('account', true)['rows'], 'profile' => $list('profile', true)['rows'],
        'voting' => $list('voting', true)['rows'], 'plain' => $list('forum', false)['count'], 'node' => $list('node', false)['count']]];
    $tok = getRouteToken($forum['body'], 'sl-fm-ops');
    $gone = getRouteReply('root', 'POST', 'admin.php', ['name' => 'uploads', 'op' => 'fmdelete', 'ctx' => 'uploads', 'back' => 'forum', 'unused' => '1', 'token' => $tok,
        'mark' => ['forum/free-aaaaaaaaaa.png']]);
    $out['unused']['delete'] = [$gone['code'], str_ends_with($gone['head']['location'] ?? '', '&dir=forum&unused=1'), is_file($rwork.'/uploads/forum/free-aaaaaaaaaa.png'),
        $list('forum', true)['rows']];
    return $out;
}

# The comments of a poll and of a profile on the file route: published and pending, a hidden poll, a guest, the author, a moderator, the own block and the writers of a comment
function getRouteComment(PDO $pdo): array {
    global $rwork;
    $pre = RPREF.'_';
    $pdo->exec('INSERT INTO '.$pre.'voting (id, modul, title, body, answer, time, enddate, lang, acomm, typ, status) VALUES'
        .' (801, \'\', \'Open poll\', \'A|B\', \'0|0\', \'2026-01-01 10:00:00\', \'2099-12-31 10:00:00\', \'\', 2, 0, 1),'
        .' (802, \'\', \'Later poll\', \'A|B\', \'0|0\', \'2099-01-01 10:00:00\', \'2099-12-31 10:00:00\', \'\', 2, 0, 1)');
    $pdo->exec('INSERT INTO '.$pre.'comment (id, pid, cid, modul, time, uid, name, ip, body, status, shown) VALUES'
        .' (901, 0, 801, \'voting\', NOW(), 3, \'boris\', \'127.0.0.1\', \'Poll [attach=va-aaaaaaaaaa-3.png align=left title=a]\', 1, NOW()),'
        .' (902, 0, 801, \'voting\', NOW(), 2, \'anna\', \'127.0.0.1\', \'Wait [attach=vb-aaaaaaaaaa-2.png align=left title=b]\', 0, NULL),'
        .' (903, 0, 802, \'voting\', NOW(), 3, \'boris\', \'127.0.0.1\', \'Later [attach=vc-aaaaaaaaaa-3.png align=left title=c]\', 1, NOW()),'
        .' (911, 0, 3, \'account\', NOW(), 4, \'clara\', \'127.0.0.1\', \'Hi [attach=pc-aaaaaaaaaa-4.png align=left title=c]\', 1, NOW()),'
        .' (912, 0, 3, \'account\', NOW(), 2, \'anna\', \'127.0.0.1\', \'Wait [attach=pd-aaaaaaaaaa-2.png align=left title=d]\', 0, NULL)');
    $pdo->exec('UPDATE '.$pre.'users SET block = \'Mine [attach=blk-aaaaaaaaaa-3.png align=left title=k]\' WHERE id = 3');
    $pdo->exec('UPDATE '.$pre.'nodes SET comon = 2 WHERE id = 101');
    $code = fn(string $who, string $path): int => getRouteReply($who, 'GET', $path)['code'];
    $file = fn(string $own, int $id, string $key, string $tail = ''): string => 'index.php?go=file&own='.$own.'&id='.$id.'&key='.$key.$tail;
    $view = fn(string $name, string $key): string => 'index.php?go=file&own=comment&name='.$name.'&key='.$key.'&preview=1';
    $out = ['poll' => [$code('', $file('comment', 901, 'va-aaaaaaaaaa-3.png')), $code('', $file('comment', 901, 'va-aaaaaaaaaa-3.png', '&thumb=1')),
        $code('', $file('comment', 902, 'vb-aaaaaaaaaa-2.png')), $code('anna', $file('comment', 902, 'vb-aaaaaaaaaa-2.png')),
        $code('root', $file('comment', 902, 'vb-aaaaaaaaaa-2.png')), $code('', $file('comment', 903, 'vc-aaaaaaaaaa-3.png')),
        $code('', $file('comment', 901, 'vb-aaaaaaaaaa-2.png')), $code('moder', $file('comment', 902, 'vb-aaaaaaaaaa-2.png'))]];
    $out['prof'] = [$code('', $file('comment', 911, 'pc-aaaaaaaaaa-4.png')), $code('', $file('comment', 912, 'pd-aaaaaaaaaa-2.png')),
        $code('anna', $file('comment', 912, 'pd-aaaaaaaaaa-2.png')), $code('root', $file('comment', 912, 'pd-aaaaaaaaaa-2.png')),
        $code('', $file('profile', 3, 'pc-aaaaaaaaaa-4.png'))];
    $out['block'] = [$code('boris', $file('profile', 3, 'blk-aaaaaaaaaa-3.png')), $code('anna', $file('profile', 3, 'blk-aaaaaaaaaa-3.png')),
        $code('', $file('profile', 3, 'blk-aaaaaaaaaa-3.png')), $code('root', $file('profile', 3, 'blk-aaaaaaaaaa-3.png'))];
    $out['vshut'] = [$code('', 'uploads/voting/va-aaaaaaaaaa-3.png'), $code('root', 'uploads/voting/va-aaaaaaaaaa-3.png')];
    $out['vview'] = [$code('anna', $view('voting', 'mine-aaaaaaaaaa-2.png')), $code('boris', $view('voting', 'mine-aaaaaaaaaa-2.png')),
        $code('', $view('voting', 'mine-aaaaaaaaaa-2.png')), $code('root', $view('voting', 'other-bbbbbbbbbb-4.png')), $code('anna', $view('profile', 'own-aaaaaaaaaa-2.png'))];
    $conf = require $rwork.'/config/users.php';
    $conf['users']['prof'] = '1';
    setRouteFile($rwork.'/config/users.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $out['pshut'] = [$code('', $file('comment', 911, 'pc-aaaaaaaaaa-4.png')), $code('anna', $file('comment', 911, 'pc-aaaaaaaaaa-4.png'))];
    $conf['users']['prof'] = '0';
    setRouteFile($rwork.'/config/users.php', $conf);
    if (is_file($rwork.'/config/local.php')) unlink($rwork.'/config/local.php');
    $page = getRouteReply('', 'GET', 'index.php?name=voting&op=view&id=801')['body'];
    $out['vpage'] = [str_contains($page, 'go=file&amp;own=comment&amp;id=901&amp;key=va-aaaaaaaaaa-3.png'), str_contains($page, 'uploads/voting/'),
        str_contains($page, 'vb-aaaaaaaaaa-2.png')];
    $page = getRouteReply('', 'GET', 'index.php?name=account&op=view&uname=boris')['body'];
    $out['ppage'] = [str_contains($page, 'go=file&amp;own=comment&amp;id=911&amp;key=pc-aaaaaaaaaa-4.png'), str_contains($page, 'pd-aaaaaaaaaa-2.png')];
    $html = getRouteReply('anna', 'GET', 'index.php?name=voting&op=view&id=801')['body'];
    $tok = preg_match('#id="formcsave".*?name="token"\s+value="([a-f0-9]{64})"#s', $html, $hit) ? $hit[1] : '';
    $add = fn(string $mod, int $id, string $text): string => getRouteReply('anna', 'POST', 'index.php?go=1&op=addComment&id='.$id.'&mod='.$mod.'&com=1',
        ['text' => $text, 'name' => '', 'token' => $tok], ['HX-Request: true'])['body'];
    $count = fn(string $mod): int => intval($pdo->query('SELECT COUNT(*) FROM '.$pre.'comment WHERE uid = 2 AND modul = '.$pdo->quote($mod))->fetchColumn());
    $was = [$count('voting'), $count('account'), $count('news')];
    $out['pwrite'] = [$tok !== '', str_contains($add('voting', 801, 'Takes [attach=other-bbbbbbbbbb-4.png align=left title=o]'), ': other-bbbbbbbbbb-4.png'),
        str_contains($add('voting', 801, 'Takes [attach=vc-aaaaaaaaaa-3.png align=left title=c]'), ': vc-aaaaaaaaaa-3.png')];
    $add('voting', 801, 'Quotes [attach=va-aaaaaaaaaa-3.png align=left title=a]');
    $add('voting', 801, 'Shows [attach=mine-aaaaaaaaaa-2.png align=left title=m]');
    $out['pwrite'][] = $count('voting') - $was[0];
    $mine = $pdo->query('SELECT id, body FROM '.$pre.'comment WHERE uid = 2 AND modul = \'voting\' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $body = fn(int $id): string => (string)$pdo->query('SELECT body FROM '.$pre.'comment WHERE id = '.$id)->fetchColumn();
    $edit = fn(string $text): array => getRouteReply('anna', 'POST', 'index.php?go=1&op=updateQuickEdit', ['kind' => 'comment', 'id' => (string)$mine['id'],
        'field' => 'body', 'stamp' => sha1($body((int)$mine['id'])."\0"), 'text' => $text], ['X-CSRF-TOKEN: '.$tok]);
    $bad = $edit($mine['body'].' [attach=other-bbbbbbbbbb-4.png align=left title=o]');
    $out['pedit'] = [str_contains($bad['body'], ': other-bbbbbbbbbb-4.png'), $body((int)$mine['id']) === $mine['body']];
    $edit($mine['body'].' [attach=va-aaaaaaaaaa-3.png align=left title=a]');
    $out['pedit'][] = $body((int)$mine['id']) === $mine['body'].' [attach=va-aaaaaaaaaa-3.png align=left title=a]';
    $mtok = getRouteToken(getRouteReply('root', 'GET', 'admin.php?name=comments&op=edit&id=901')['body'], 'editsave');
    $more = $body(901).' [attach=other-bbbbbbbbbb-4.png align=left title=o]';
    getRouteReply('root', 'POST', 'admin.php?name=comments', ['op' => 'editsave', 'id' => '901', 'comment' => $more, 'token' => $mtok]);
    $out['medit'] = [$mtok !== '', $body(901) === $more];
    $out['awrite'] = [str_contains($add('account', 3, 'Takes [attach=blk-aaaaaaaaaa-3.png align=left title=k]'), ': blk-aaaaaaaaaa-3.png')];
    $add('account', 3, 'Shows [attach=own-aaaaaaaaaa-2.png align=left title=m]');
    $out['awrite'][] = $count('account') - $was[1];
    $out['nwrite'] = [str_contains($add('news', 101, 'Takes [attach=other-bbbbbbbbbb.png align=left title=o]'), ': other-bbbbbbbbbb.png')];
    $add('news', 101, 'Quotes [attach=att-aaaaaaaaaa.png align=left title=a]');
    $out['nwrite'][] = $count('news') - $was[2];
    return $out;
}

# The child favhold of the intact run holds the account of anna with one more favorite for two seconds, as a parallel request at the limit does before its commit
if (($argv[2] ?? '') === 'favhold') {
    $hpdo = getRoutePdo((string)($argv[3] ?? ''));
    $hpdo->beginTransaction();
    $hpdo->query('SELECT id FROM '.RPREF.'_users WHERE id = 2 FOR UPDATE');
    $hpdo->exec('INSERT INTO '.RPREF.'_favorites (uid, fid, modul, time) VALUES (2, 103, \'news\', NOW())');
    echo "held\n";
    sleep(2);
    $hpdo->commit();
    exit;
}

$report = ['error' => '', 'clean' => false, 'runs' => []];
$rbase = '';
$rproc = null;
try {
    deleteRouteTree($rwork);
    foreach (['config', 'backup', 'cache', 'counter', 'logs', 'sitemap', 'captcha', 'uploads'] as $rdir) mkdir($rwork.'/'.$rdir, 0777, true);
    [$rpdo, $rbase] = addRouteBase();
    addRouteRows($rpdo);
    $rport = getRoutePort();
    addRouteConfig($rwork, $rbase, $rport);
    addRouteFiles($rwork);
    if (($argv[2] ?? '') === 'sync') addRouteSyncTypes($rpdo, $rwork);
    if (in_array($argv[2] ?? '', ['integ', 'guard', 'intact', 'secure'], true)) addRouteIntegTypes($rpdo, $rwork);
    if (($argv[2] ?? '') === 'secure') addRouteSecureTypes($rpdo, $rwork);
    if (($argv[2] ?? '') === 'tree') addRouteTreeTypes($rpdo, $rwork);
    if (($argv[2] ?? '') === 'guard') addRouteGuardRows($rpdo, $rwork);
    if (($argv[2] ?? '') === 'modes' || ($argv[3] ?? '') === 'modes') addRouteModeTypes($rpdo, $rwork);
    if (($argv[2] ?? '') === 'seo') addRouteSeoTypes($rpdo, $rwork);
    if (($argv[2] ?? '') === 'head') addRouteHeadTypes($rpdo, $rwork);
    $rproc = addRouteServer($rwork, $rport);
    if (($argv[2] ?? '') === 'serve') {
        fwrite(STDERR, 'serving on '.$rport.' with '.$rbase."\n");
        while (!is_file($rwork.'/stop')) sleep(1);
        throw new RuntimeException('stopped');
    }
    if (($argv[2] ?? '') === 'support') {
        $report['runs']['support'] = getRouteSupport($rpdo);
    } elseif (($argv[2] ?? '') === 'sync') {
        $report['runs']['sync'] = getRouteSync($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'integ') {
        $report['runs']['integ'] = getRouteInteg($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'guard') {
        $report['runs']['guard'] = getRouteGuard($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'intact') {
        $report['runs']['intact'] = getRouteIntact($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'secure') {
        $report['runs']['secure'] = getRouteSecure($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'tree') {
        $report['runs']['tree'] = getRouteTree($rpdo);
    } elseif (($argv[2] ?? '') === 'modes') {
        $report['runs']['modes'] = getRouteModes($rpdo);
    } elseif (($argv[2] ?? '') === 'seo') {
        $report['runs']['seo'] = getRouteSeo($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'head') {
        $report['runs']['head'] = getRouteHead();
    } elseif (($argv[2] ?? '') === 'quick') {
        $report['runs']['quick'] = getRouteQuick($rpdo, $rwork);
    } elseif (($argv[2] ?? '') === 'files') {
        $report['runs']['files'] = getRouteFiles($rpdo) + getRouteAccount($rpdo) + getRouteComment($rpdo) + getRouteUnused($rpdo);
    } else {
        $report['runs']['lists'] = getRouteLists($rpdo);
        $report['runs']['view'] = getRouteViewRuns($rpdo);
        $report['runs']['data'] = json_decode((string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($rwork).' view 2>&1'), true);
        $report['runs']['attach'] = getRouteAttach();
        $report['runs']['assets'] = getRouteAssets($rpdo);
        $report['runs']['reports'] = getRouteReports($rpdo);
        $report['runs']['form'] = getRouteForm($rpdo);
        $report['runs']['admin'] = getRouteAdmin($rpdo);
        $report['runs']['types'] = getRouteTypes($rpdo, $rwork);
        $report['runs']['hold'] = getRouteHold($rwork);
    }
    $report['logs'] = [];
    foreach (['error_php.log', 'error_sql.log'] as $rone) {
        $rfile = $rwork.'/logs/'.$rone;
        $report['logs'][$rone] = is_file($rfile) ? array_slice(file($rfile, FILE_IGNORE_NEW_LINES) ?: [], 0, 5) : [];
    }
} catch (Throwable $err) {
    $report['error'] = get_class($err).': '.$err->getMessage().' @'.basename($err->getFile()).':'.$err->getLine();
}
if (is_resource($rproc)) {
    proc_terminate($rproc);
    proc_close($rproc);
}
$report['clean'] = deleteRouteBase($rbase);
echo json_encode($report, JSON_INVALID_UTF8_SUBSTITUTE);
