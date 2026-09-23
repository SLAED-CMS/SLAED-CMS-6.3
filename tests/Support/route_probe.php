<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for stage S13 of docs/node: the public and administrative routes of Node answered by the real index.php and admin.php over real HTTP
# It builds one disposable MariaDB database from the shipped table.sql, a scratch copy of the configuration that registers three types, a scratch upload root with the
# guards of the release, and serves the tree with the built-in server and tests/Support/route_web.php as router; every exchange is a real request with its own cookies
# The report answers what each exchange returned and what the database, the cache and the files hold afterwards; nothing touches the site database or directories
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
$rwork = str_replace('\\', '/', (string)($argv[1] ?? sys_get_temp_dir().'/slaed_node_route'));
if (($argv[2] ?? '') === 'view') {
    $probework = $rwork.'/child';
    foreach (['CONFIG_DIR' => 'config', 'BACKUP_DIR' => 'backup', 'CACHE_DIR' => 'cache', 'UPLOADS_DIR' => 'uploads'] as $rkey => $rdir) define($rkey, $rwork.'/'.$rdir);
    require_once __DIR__.'/probe_boot.php';
    require_once BASE_DIR.'/core/system.php';
    echo json_encode(getRouteViewData());
    exit;
}
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));

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
    $sql = getSqlbatch((string)file_get_contents(BASE_DIR.'/setup/sql/table.sql'));
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

# The scratch configuration: the sources of the stand with the disposable database, the address of the probe server, the page cache on, news as the start page,
# and three registered types - news with categories, public submission under review and two roles, docs without submission, and the disabled off
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
    $data = require BASE_DIR.'/config/node.php';
    $data['node']['types'] = ['docs' => ['version' => 1, 'features' => getRouteFeatures([])], 'news' => $news, 'off' => ['version' => 1, 'features' => getRouteFeatures([])]];
    setRouteFile($work.'/config/node.php', $data);
    $data = require BASE_DIR.'/config/uploads.php';
    $rate = require BASE_DIR.'/config/ratings.php';
    foreach (['news', 'docs', 'off'] as $name) {
        $data['uploads'][$name] = $data['uploads']['all'];
        $rate['ratings']['node.'.$name] = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];
    }
    setRouteFile($work.'/config/uploads.php', $data);
    setRouteFile($work.'/config/ratings.php', $rate);
}

# The rows of the disposable database: a group, three accounts, four administrators, two categories of news, the three types, their materials and resources
function addRouteRows(PDO $pdo): void {
    $pre = RPREF.'_';
    $pdo->exec('INSERT INTO '.$pre.'groups (id, name, intro, points, extra) VALUES (1, \'club\', \'\', 0, 1)');
    $pdo->exec('INSERT INTO '.$pre.'users (id, name, email, password, block, warnings, field, grp, points, ip) VALUES'
        .' (2, \'anna\', \'anna@probe.test\', \'hash-anna\', \'\', \'\', \'\', 1, 0, \'127.0.0.1\'),'
        .' (3, \'boris\', \'boris@probe.test\', \'hash-boris\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\'),'
        .' (4, \'clara\', \'clara@probe.test\', \'hash-clara\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'admins (id, name, email, password, super, modules, ip) VALUES'
        .' (1, \'root\', \'root@probe.test\', \'hash-root\', 1, \'\', \'127.0.0.1\'),'
        .' (2, \'moder\', \'moder@probe.test\', \'hash-moder\', 0, \'node-news\', \'127.0.0.1\'),'
        .' (3, \'boss\', \'boss@probe.test\', \'hash-boss\', 0, \'node\', \'127.0.0.1\'),'
        .' (4, \'docsman\', \'docsman@probe.test\', \'hash-docsman\', 0, \'node-docs\', \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, ppost, lang) VALUES (1, \'news\', \'Open\', \'\', \'0|0\', \'1|0\', \'\'),'
        .' (2, \'news\', \'Members\', \'\', \'1|0\', \'1|0\', \'\')');
    $pdo->exec('INSERT INTO '.$pre.'node_types (id, name, title, intro, ext, active, sort, version) VALUES (1, \'news\', \'News\', \'\', \'\', 1, 10, 1),'
        .' (2, \'docs\', \'Docs\', \'\', \'\', 1, 20, 1), (3, \'off\', \'Off\', \'\', \'\', 0, 30, 1)');
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

# The scratch upload root with the guard of the release in every type directory and the files the materials point at
function addRouteFiles(string $work): void {
    $guard = (string)file_get_contents(BASE_DIR.'/uploads/index.html');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    file_put_contents($work.'/uploads/index.html', $guard);
    foreach (['news', 'docs', 'off'] as $name) {
        mkdir($work.'/uploads/'.$name.'/thumb', 0777, true);
        file_put_contents($work.'/uploads/'.$name.'/index.html', $guard);
        file_put_contents($work.'/uploads/'.$name.'/.htaccess', 'deny from all');
    }
    foreach (['cover-cccccccccc.png', 'att-aaaaaaaaaa.png', 'other-bbbbbbbbbb.png', 'thumb/att-aaaaaaaaaa.png'] as $one) file_put_contents($work.'/uploads/news/'.$one, $png);
    file_put_contents($work.'/uploads/news/manual-dddddddddd.pdf', '%PDF-1.4'.str_repeat('0123456789', 199).'%%');
    file_put_contents($work.'/upload.png', $png);
}

# Start the built-in server with the route router on a free port and wait until it answers
function addRouteServer(string $work, int $port): mixed {
    $env = getenv() + ['SLAED_ROUTE_ROOT' => $work];
    $log = ['file', $work.'/server.log', 'a'];
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, BASE_DIR.'/tests/Support/route_web.php'], [1 => $log, 2 => $log], $pipes, BASE_DIR, $env);
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

# The pages the scratch page cache holds and the bound of the newest one
function getRouteCache(string $work): array {
    $list = glob($work.'/cache/pages/html/*.html') ?: [];
    $meta = [];
    foreach (glob($work.'/cache/pages/html/*.json') ?: [] as $one) $meta[] = json_decode((string)file_get_contents($one), true);
    return ['pages' => count($list), 'until' => array_column($meta, 'until')];
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

# The public list: status, clean parameters, paging, the category rights, the start page, the page cache and a forged context
function getRouteLists(PDO $pdo): array {
    global $rwork;
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $out = [];
    $one = getRouteReply('', 'GET', 'index.php?name=news');
    $out['list'] = [$one['code'], str_contains($one['body'], 'Gamma'), str_contains($one['body'], 'Beta'), str_contains($one['body'], 'Alpha'), str_contains($one['body'],
        'Members')];
    $out['canon'] = str_contains($one['body'], 'rel="canonical" href="http://127.0.0.1');
    $out['nostore'] = str_contains((string)($one['head']['cache-control'] ?? ''), 'no-store');
    $out['cache'] = getRouteCache($rwork);
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
    $out['cat'] = [str_contains(getRouteReply('', 'GET', 'index.php?name=news&cat=2')['body'], '>Members<'),
        str_contains(getRouteReply('anna', 'GET', 'index.php?name=news&cat=2')['body'], '>Members<')];
    $out['off'] = [$code('', 'index.php?name=off'), $code('root', 'index.php?name=off'), $code('', 'index.php?name=off&super=1&mods=off&task=1&aid=1&manage=1')];
    $home = getRouteReply('', 'GET', '');
    $out['home'] = [$home['code'], str_contains($home['body'], 'Gamma')];
    return $out;
}

# One material: the route type, the rights, the views counted after the answer, never for HEAD, and the controlled address of its attachment
function getRouteViewRuns(PDO $pdo): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $one = getRouteReply('', 'GET', 'index.php?name=news&op=view&id=101');
    $out = ['view' => [$one['code'], str_contains($one['body'], '<h1 class="sl-title">Alpha</h1>'), str_contains($one['body'], 'op=attach&amp;id=101&amp;key=att-aaaaaaaaaa.png'),
        str_contains($one['body'], 'op=asset&amp;id=2'), str_contains($one['body'], 'uploads/news/')]];
    $out['views'] = getRouteViews($pdo, 101, 1);
    getRouteReply('', 'HEAD', 'index.php?name=news&op=view&id=101');
    usleep(500000);
    $out['head'] = (int)getRouteCol($pdo, 101, 'views');
    $out['rights'] = [$code('', 'index.php?name=news&op=view&id=103'), $code('anna', 'index.php?name=news&op=view&id=103'), $code('', 'index.php?name=news&op=view&id=104'),
        $code('moder', 'index.php?name=news&op=view&id=104'), $code('docsman', 'index.php?name=news&op=view&id=104'), $code('', 'index.php?name=news&op=view&id=201'),
        $code('', 'index.php?name=news&op=view&id=0'), $code('', 'index.php?name=news&op=view&id=abc')];
    return $out;
}

# The editor attachment of a stored material: its own name only, the thumb, and every crafted key or parameter refused with the same not found
function getRouteAttach(): array {
    $code = fn(string $who, string $path, string $method = 'GET'): int => getRouteReply($who, $method, $path)['code'];
    $one = getRouteReply('', 'GET', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png');
    $head = getRouteReply('', 'HEAD', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png');
    return [
        'file' => [$one['code'], $one['head']['content-type'] ?? '', $one['head']['cache-control'] ?? '', strlen($one['body'])],
        'head' => [$head['code'], strlen($head['body'])],
        'thumb' => $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png&thumb=1'),
        'refused' => [
            $code('', 'index.php?name=news&op=attach&id=101&key=other-bbbbbbbbbb.png'), $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png%00.php'),
            $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png&foo=1'), $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png&preview=1'),
            $code('', 'index.php?name=news&op=attach&id=101&key=att-aaaaaaaaaa.png&thumb=2'), $code('', 'index.php?name=news&op=attach&id=103&key=att-aaaaaaaaaa.png'),
            $code('', 'index.php?name=docs&op=attach&id=101&key=att-aaaaaaaaaa.png'), $code('', 'index.php?name=news&op=attach&key=att-aaaaaaaaaa.png&preview=1'),
        ],
        'direct' => $code('', 'uploads/news/att-aaaaaaaaaa.png'),
        'climb' => str_contains(getRouteReply('', 'GET', 'index.php?name=news&op=attach&id=101&key=..%2Fconfig%2Fdb.php')['body'], "'pass'"),
    ];
}

# The structured resources: an image shown without counting, a download counted before a whole body or a range from zero only, an external visit counted before its redirect
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
    $out['foreign'] = [getRouteReply('', 'GET', 'index.php?name=docs&op=asset&id=2')['code'], getRouteReply('', 'GET', 'index.php?name=news&op=asset&id=99')['code']];
    $out['direct'] = getRouteReply('', 'GET', 'uploads/news/manual-dddddddddd.pdf')['code'];
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
    $key = preg_match('#op=attach&amp;key=([A-Za-z0-9_.-]+)&amp;preview=1#', $prev['body'], $hit) ? $hit[1] : '';
    $path = 'index.php?name=news&op=attach&key='.$key.'&preview=1';
    $mine = getRouteReply('anna', 'GET', $path);
    $out['upload'] = [$prev['code'], $key !== '', str_contains($key, '-2.'), is_file($GLOBALS['rwork'].'/uploads/news/'.$key), $mine['code'], $mine['head']['cache-control'] ?? '',
        getRouteReply('boris', 'GET', $path)['code'], getRouteReply('', 'GET', $path)['code'], getRouteReply('moder', 'GET', $path)['code'], $count() - $was];
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
function getRouteTypes(PDO $pdo, string $work): array {
    $has = fn(string $name): int => (int)$pdo->query('SELECT COUNT(*) FROM '.RPREF.'_node_types WHERE name = '.$pdo->quote($name))->fetchColumn();
    $form = getRouteReply('boss', 'GET', 'admin.php?name=node&op=type');
    $tok = getRouteToken($form['body'], 'type');
    $post = ['name' => 'node', 'op' => 'type', 'token' => $tok, 'tname' => 'temp', 'title' => 'Temp', 'intro' => '', 'ext' => '', 'sort' => '5', 'orders' => ['published'],
        'order' => 'published', 'dir' => 'desc', 'limit' => '10', 'show' => ['date'], 'mode' => 'default', 'access' => ['1|0'], 'notify_pending' => '1', 'seo' => 'website'];
    $made = getRouteReply('boss', 'POST', 'admin.php', $post);
    $out = ['create' => [$made['code'], $has('temp'), (int)$pdo->query('SELECT active FROM '.RPREF.'_node_types WHERE name = \'temp\'')->fetchColumn(),
        is_dir($work.'/uploads/temp')]];
    $out['twice'] = [getRouteReply('boss', 'POST', 'admin.php', $post)['code'], $has('temp')];
    $json = getRouteReply('boss', 'GET', 'admin.php?name=node&op=export&type=temp');
    $out['export'] = [$json['code'], $json['head']['content-type'] ?? '', $json['head']['content-disposition'] ?? '', (json_decode($json['body'], true)['format'] ?? '')];
    $page = getRouteReply('boss', 'GET', 'admin.php?name=node&op=clone&type=temp');
    $copy = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'clone', 'type' => 'temp', 'tname' => 'copy', 'token' => getRouteToken($page['body'], 'clone')]);
    $out['clone'] = [$copy['code'], $has('copy')];
    $page = getRouteReply('boss', 'GET', 'admin.php?name=node&op=config');
    $tok = getRouteToken($page['body'], 'config');
    $low = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '100', 'maxlist' => '1', 'syncbatch' => '500']);
    $cfg = require $work.'/config/node.php';
    $out['limits'] = [$low['code'], $cfg['node']['limits']['maxlist']];
    $few = getRouteReply('boss', 'POST', 'admin.php', ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '1', 'maxlist' => '100', 'syncbatch' => '500']);
    $cfg = require $work.'/config/node.php';
    $out['limits'][] = $few['code'];
    $out['limits'][] = $cfg['node']['limits']['maxassets'];
    $wide = ['name' => 'node', 'op' => 'config', 'token' => $tok, 'maxassets' => '100', 'maxlist' => '150', 'syncbatch' => '500'];
    $out['limits'][] = getRouteReply('boss', 'POST', 'admin.php', $wide)['code'];
    $cfg = require $work.'/config/node.php';
    $out['limits'][] = $cfg['node']['limits']['maxlist'];
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
# Every mode answers exactly the keys of 05-core-api.md; a target answers the same keys empty; the resources carry no source, report or reporter; foreign pairs are refused
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
    foreach (['card', 'block', 'search'] as $mode) $out['keys']['t'.$mode] = array_keys($view->getNodeView($news, $tgt, $mode)) === $keys;
    $full = $view->getNodeView($news, $node, 'view');
    $light = $view->getNodeView($news, $tgt, 'card');
    $out['full'] = ['href' => $full['href'], 'title' => $full['title'], 'intro' => $full['intro'], 'body' => str_contains($full['body_html'], 'op=attach&amp;id=101'),
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
    foreach ([[$news, $node, 'block'], [$news, $tgt, 'view'], [$news, $node, 'print'], [$docs, $node, 'view'], [$docs, $tgt, 'card']] as $i => [$type, $obj, $mode]) {
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
    $out['trusted'] = [
        str_contains($view->getNodeView($news, new Node(8, $news->id, 0, 0, '', null, 'T', '<b>x</b>', null, null, 0, false, CommentMode::Disabled, false, 0, 0, 0, 0,
            NodeStatus::Published, 1, '', '', null, null, null, null, null, null, null), 'card')['intro_html'], '<b>'),
    ];
    return $out;
}

# A type an unfinished configuration operation holds is closed with 503 before any query, and opens again once the marker is gone
function getRouteHold(string $work): array {
    if (!is_dir($work.'/backup/config')) mkdir($work.'/backup/config', 0777, true);
    file_put_contents($work.'/backup/config/marker.json', json_encode(['op' => 'probe-op', 'types' => ['news'], 'files' => []]));
    $one = getRouteReply('', 'GET', 'index.php?name=news');
    unlink($work.'/backup/config/marker.json');
    return [$one['code'], $one['head']['retry-after'] ?? '', str_contains((string)($one['head']['cache-control'] ?? ''), 'no-store'), getRouteReply('', 'GET',
        'index.php?name=news')['code']];
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
    $rproc = addRouteServer($rwork, $rport);
    if (($argv[2] ?? '') === 'serve') {
        fwrite(STDERR, 'serving on '.$rport.' with '.$rbase."\n");
        while (!is_file($rwork.'/stop')) sleep(1);
        throw new RuntimeException('stopped');
    }
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
