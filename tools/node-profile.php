<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The large profile of Node from docs/node/13-testing.md: run by hand, never by PHPUnit or the commit hook
# It creates its own disposable database on the server of config/db.php, installs the shipped schema, boots the real core on a scratch copy of the release
# configuration and creates the ten shipped types through NodeService; then batch SQL fills 100000 materials, 200 categories, two extra categories and two
# relations per material and up to three resources per material where the type has roles, and every route budget of docs/node/11 is warmed and repeated
# The report gives the statements per scenario against the budget, the one against the largest page, p50 and p95 of the wall time, the average statement time
# and the plans of the main statements with the rows each one really read; a scenario over its budget, a full scan of a Node table, a list page reading more rows
# than its page end asks for, a deadline reading more than the timed and pinned rows of its type, a category list off the two category indexes or a failure
# ends the tool with exit code 1
# Options: --rows=<count of materials, 1000..1000000, default 100000> --runs=<repeats, default 30> --out=<report file> --keep (leave database and scratch)
# Nothing touches the site database, config/, storage/ or uploads/ of the site: the directories of the core point into scratch before it boots
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(0);
ini_set('display_errors', '0');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
$popt = getopt('', ['rows:', 'runs:', 'out:', 'keep']);
$prows = max(1000, min(1000000, intval($popt['rows'] ?? 100000)));
$pruns = max(5, min(500, intval($popt['runs'] ?? 30)));
$pwork = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_profile';
$pcred = (require BASE_DIR.'/config/db.php')['db'];
$pname = 'slaed_prof_'.bin2hex(random_bytes(4));

# The table prefix of the disposable database
const PPREF = 'prof';

# The number of categories of every type, twenty per type make the two hundred of the profile
const PCATS = 20;

# The budgets of docs/node/11 as the largest statement count each scenario may reach; a list is held to its upper bound, because every shipped type has categories
const PBUDGET = ['list' => 7, 'build' => 8, 'view' => 5, 'viewrel' => 6, 'attach' => 2, 'asset' => 2, 'download' => 4,
    'report' => 4, 'admin' => 3, 'edit' => 5, 'status' => 6, 'delete' => 5];

# Remove one scratch tree
function deleteProfileTree(string $dir): void {
    if (is_link($dir) || is_file($dir)) {
        unlink($dir);
        return;
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteProfileTree($dir.'/'.$one);
    rmdir($dir);
}

# One connection to the database server of the site configuration, with a database selected when one is named
function getProfilePdo(string $name = ''): PDO {
    global $pcred;
    $dsn = 'mysql:host='.$pcred['host'].($name === '' ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, $pcred['uname'], $pcred['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the disposable database with the shipped schema; the site database can never be the one created
function addProfileBase(): PDO {
    global $pcred, $pname;
    if ($pname === $pcred['name']) throw new RuntimeException('The disposable name collides with the site database');
    getProfilePdo()->exec('CREATE DATABASE `'.$pname.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = getProfilePdo($pname);
    $text = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PPREF, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'],
        (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql'));
    foreach (preg_split('/;\s*\n/', $text) ?: [] as $sql) if (trim($sql) !== '' && !str_starts_with(trim($sql), '--')) $pdo->exec($sql);
    return $pdo;
}

# Drop the disposable database again
function deleteProfileBase(): bool {
    global $pname;
    try {
        getProfilePdo()->exec('DROP DATABASE IF EXISTS `'.$pname.'`');
        return true;
    } catch (Throwable) {
        return false;
    }
}

# Write one configuration source the way the core reads it
function setProfileFile(string $file, array $data): void {
    file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n");
}

# The scratch configuration: every source of the release as committed, the disposable database, the three update marks of a clean installation,
# and the address of the guard server as the home of the site, so switching a type on meets a server that refuses its directory
function setProfileConfig(string $dir, int $port): void {
    global $pcred, $pname;
    mkdir($dir, 0777, true);
    $quiet = ' 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
    $list = explode("\n", trim((string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' ls-tree -r --name-only HEAD config/'.$quiet)));
    foreach ($list as $one) {
        if (!str_ends_with($one, '.php')) continue;
        file_put_contents($dir.'/'.basename($one), (string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' show '.escapeshellarg('HEAD:'.$one).$quiet));
    }
    if (!is_file($dir.'/global.php') || !is_file($dir.'/node.php')) throw new RuntimeException('The release configuration could not be read from git');
    setProfileFile($dir.'/db.php', ['db' => ['name' => $pname, 'prefix' => PPREF, 'sync' => '0'] + $pcred]);
    setProfileFile($dir.'/update.php', ['update' => ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0']]);
    $glob = require $dir.'/global.php';
    setProfileFile($dir.'/global.php', array_replace($glob, ['homeurl' => 'http://127.0.0.1:'.$port, 'close' => '0', 'cache' => '0']));
}

# Start the guard server: every address below it answers 403, as the shared web server rule answers for the directory of a type
function addProfileGuard(string $dir, int $port): mixed {
    file_put_contents($dir.'/guard.php', "<?php\nhttp_response_code(403);\n");
    $log = ['file', $dir.'/guard.log', 'a'];
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $dir.'/guard.php'], [1 => $log, 2 => $log], $pipes, $dir);
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) throw new RuntimeException('The guard server did not start');
    fclose($test);
    return $proc;
}

# Stop the guard server with the processes it started
function deleteProfileGuard(mixed $proc): void {
    if (!is_resource($proc)) return;
    $pid = (int)(proc_get_status($proc)['pid'] ?? 0);
    if ($pid > 0 && PHP_OS_FAMILY === 'Windows') exec('taskkill /F /T /PID '.$pid.' 2>NUL');
    proc_terminate($proc);
    proc_close($proc);
}

# One free local port
function getProfilePort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    return $port;
}

# Finish the run in every case: stop the guard server, drop the database and the scratch unless --keep, write and print the report, exit 1 on an error or a failed check
# It is also the way out of a failure before the core booted, which must never reach the class that extends the database facade of the core
function setProfileEnd(): never {
    global $pguard, $popt, $preport, $pname, $pwork;
    deleteProfileGuard($pguard);
    if (!isset($popt['keep'])) {
        $preport['clean'] = deleteProfileBase();
        deleteProfileTree($pwork);
    } else {
        $preport['kept'] = ['database' => $pname, 'scratch' => $pwork];
    }
    $text = (string)json_encode($preport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (isset($popt['out'])) file_put_contents((string)$popt['out'], $text."\n");
    echo $text."\n";
    exit(($preport['error'] === '' && !$preport['fail']) ? 0 : 1);
}

$pbase = null;
$pguard = null;
$preport = ['error' => '', 'rows' => $prows, 'runs' => $pruns, 'xdebug' => extension_loaded('xdebug'), 'server' => '', 'fail' => []];
try {
    deleteProfileTree($pwork);
    mkdir($pwork.'/uploads', 0777, true);
    copy(BASE_DIR.'/uploads/index.html', $pwork.'/uploads/index.html');
    $pport = getProfilePort();
    $pbase = addProfileBase();
    setProfileConfig($pwork.'/config', $pport);
    $pguard = addProfileGuard($pwork, $pport);
    foreach (['CONFIG_DIR' => 'config', 'BACKUP_DIR' => 'backup', 'CACHE_DIR' => 'cache', 'COUNTER_DIR' => 'counter', 'LOGS_DIR' => 'logs', 'SITEMAP_DIR' => 'sitemap',
        'CAPTCHA_DIR' => 'captcha', 'UPLOADS_DIR' => 'uploads'] as $pkey => $pdir) {
        if (!is_dir($pwork.'/'.$pdir)) mkdir($pwork.'/'.$pdir, 0777, true);
        define($pkey, $pwork.'/'.$pdir);
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) profile';
    $_SERVER['HTTPS'] = 'on';
    require_once BASE_DIR.'/core/system.php';
} catch (Throwable $err) {
    $preport['error'] = get_class($err).': '.$err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}
if ($preport['error'] !== '') setProfileEnd();

# The shared database of the profile: every statement it runs is recorded with its parameters and its time while recording is on
final class ProfileBase extends Database {
    public bool $keep = false;
    public array $trace = [];

    # Run the statement as the facade does and remember it
    function getSqlQuery(string $query = '', array $params = []): PDOStatement|false {
        $time = microtime(true);
        $res = parent::getSqlQuery($query, $params);
        if ($this->keep) $this->trace[] = ['sql' => $query, 'pars' => $params, 'ms' => (microtime(true) - $time) * 1000];
        return $res;
    }
}

# The context of one visitor of the profile: the guest, or the main administrator with every right
function getProfileContext(bool $admin): NodeContext {
    return $admin ? new NodeContext(0, [], 1, [], true, true, '127.0.0.1', '') : new NodeContext(0, [], 0, [], false, false, '127.0.0.1', '');
}

# A fresh reader of one visitor, as a new request builds it
function getProfileQuery(bool $admin, ?NodeType $type = null): NodeQuery {
    global $db, $fld;
    $query = new NodeQuery($db, getProfileContext($admin), $fld);
    if ($type !== null && $type->ext !== '') {
        require_once BASE_DIR.'/core/classes/node/ext/load.php';
        $query->setNodeExtension(getNodeExtension($type->ext, $db, getProfileContext($admin)));
    }
    return $query;
}

# A fresh writer of one visitor with the shared points and the extension of the type
function getProfileWriter(bool $admin, ?NodeType $type = null): NodeService {
    global $db, $fld, $pnt;
    $ext = null;
    if ($type !== null && $type->ext !== '') {
        require_once BASE_DIR.'/core/classes/node/ext/load.php';
        $ext = getNodeExtension($type->ext, $db, getProfileContext($admin));
    }
    return new NodeService($db, getProfileContext($admin), $fld, $pnt, $ext);
}

# The ten shipped profiles as types: each is imported through the writer of the main administrator and switched on, as the clean installation does it;
# media, the type with the most fields and roles, then gets the system page limit of 100 through the same writer, so the largest page can be measured
function addProfileTypes(): array {
    $out = [];
    foreach (glob(BASE_DIR.'/modules/node/profiles/*.json') ?: [] as $file) {
        $srv = getProfileWriter(true);
        $type = $srv->addNodeTypeImport((string)file_get_contents($file));
        $srv->updateNodeTypeStatus($type->name, true, $type->version);
    }
    $media = getProfileQuery(true)->getNodeType('media');
    $set = $media->settings;
    $set['list']['limit'] = 100;
    $input = new NodeTypeInput($media->title, $media->intro, $media->ext, $media->sort, $set, $media->fields, $media->uploads, $media->rating);
    getProfileWriter(true)->updateNodeType('media', $input, $media->version);
    foreach (getProfileQuery(true)->getNodeTypeList() as $type) $out[$type->name] = $type;
    ksort($out);
    return $out;
}

# The resource slots of a type, at most three, each role taking up to its maximum in the order of the roles; external for a role that may link, local otherwise
function getProfileSlots(NodeType $type): array {
    $out = [];
    foreach ($type->settings['assets'] as $role => $def) {
        for ($i = 0; $i < $def['max'] && count($out) < 3; $i++) {
            $kind = in_array('image', $def['kinds'], true) ? 'image' : $def['kinds'][0];
            $out[] = [$role, $kind, $def['canlink'] ? 'https://example.com/'.$role.'/' : $role.'-'];
        }
    }
    return $out;
}

# Fill the database with batch statements: users, categories, materials of every state, extra categories, relations, resources and the rows of both extensions
function addProfileRows(PDO $pdo, array $types, int $rows): array {
    $pre = PPREF.'_';
    $per = intdiv($rows, count($types));
    $pdo->exec('CREATE TABLE profile_digit (d TINYINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO profile_digit VALUES (0), (1), (2), (3), (4), (5), (6), (7), (8), (9)');
    $pdo->exec('CREATE TABLE profile_seq (n INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO profile_seq SELECT a.d + 10 * b.d + 100 * c.d + 1000 * e.d + 10000 * f.d + 100000 * g.d FROM profile_digit AS a, profile_digit AS b,'
        .' profile_digit AS c, profile_digit AS e, profile_digit AS f, profile_digit AS g');
    $pdo->exec('CREATE TABLE profile_map (k INT UNSIGNED NOT NULL PRIMARY KEY, tid INT UNSIGNED NOT NULL, com TINYINT NOT NULL, home TINYINT NOT NULL,'
        .' pin TINYINT NOT NULL, rel TINYINT NOT NULL, tree TINYINT NOT NULL, field VARCHAR(255) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE profile_role (tid INT UNSIGNED NOT NULL, slot INT UNSIGNED NOT NULL, role VARCHAR(50) NOT NULL, kind VARCHAR(16) NOT NULL,'
        .' src VARCHAR(255) NOT NULL) ENGINE=InnoDB');
    $map = $pdo->prepare('INSERT INTO profile_map VALUES (:k, :tid, :com, :home, :pin, :rel, :tree, :field)');
    $role = $pdo->prepare('INSERT INTO profile_role VALUES (:tid, :slot, :role, :kind, :src)');
    $num = 0;
    foreach ($types as $name => $type) {
        $feat = $type->settings['features'];
        $field = ($name === 'files') ? '{"release":"1.0.2","site":"https://example.com/"}' : '{}';
        $map->execute(['k' => $num, 'tid' => $type->id, 'com' => (int)$feat['comments'], 'home' => (int)$feat['home'], 'pin' => (int)$feat['pinned'],
            'rel' => (int)$feat['related'], 'tree' => (int)$feat['tree'], 'field' => $field]);
        foreach (getProfileSlots($type) as $i => [$one, $kind, $src]) $role->execute(['tid' => $type->id, 'slot' => $i, 'role' => $one, 'kind' => $kind, 'src' => $src]);
        $num++;
    }
    $pdo->exec('INSERT INTO '.$pre.'users (id, name, email, password, block, warnings, field, ip) SELECT n + 1, CONCAT(\'user\', n + 1),'
        .' CONCAT(\'user\', n + 1, \'@profile.test\'), \'hash\', \'\', \'\', \'\', \'127.0.0.1\' FROM profile_seq WHERE n < 1000');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, lang, status, ordern) SELECT m.k * '.PCATS.' + s.n + 1, t.name, CONCAT(t.name, \' \', s.n + 1),'
        .' \'\', \'0|0\', \'\', 1, s.n FROM profile_map AS m INNER JOIN '.$pre.'node_types AS t ON t.id = m.tid INNER JOIN profile_seq AS s ON s.n < '.PCATS);
    $state = 'CASE WHEN s.n % 100 < 90 THEN 2 WHEN s.n % 100 < 95 THEN 1 WHEN s.n % 100 < 98 THEN 0 ELSE 3 END';
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, home, comon, pinned, views, score, ratings, status, created, updated,'
        .' published) SELECT s.n + 1, m.tid, m.k * '.PCATS.' + 1 + s.n % '.PCATS.', IF(s.n % 7 = 0, 0, 1 + s.n % 1000), IF(s.n % 7 = 0, \'Guest\', \'\'), \'127.0.0.1\','
        .' CONCAT(CHAR(65 + s.n % 26), \' material \', s.n + 1), CONCAT(\'Intro of material \', s.n + 1), CONCAT(\'Body of material \', s.n + 1, \' with [b]markup[/b]\'),'
        .' m.field, m.home * (s.n % 50 = 0), 2 * m.com, m.pin * (s.n % 97 = 0), s.n % 5000, (s.n % 10) * (s.n % 3), s.n % 3, '.$state.','
        .' NOW() - INTERVAL s.n MINUTE, NOW() - INTERVAL s.n MINUTE, IF('.$state.' IN (0, 1), NULL, NOW() - INTERVAL s.n MINUTE)'
        .' FROM profile_seq AS s INNER JOIN profile_map AS m ON m.k = s.n DIV '.$per.' WHERE s.n < '.($per * count($types)));
    $pdo->exec('INSERT INTO '.$pre.'node_categories (nid, cid) SELECT n.id, m.k * '.PCATS.' + 1 + (n.id - 1 + x.d) % '.PCATS.' FROM '.$pre.'nodes AS n'
        .' INNER JOIN profile_map AS m ON m.tid = n.tid INNER JOIN (SELECT 7 AS d UNION ALL SELECT 13) AS x');
    $pdo->exec('INSERT INTO '.$pre.'node_relations (nid, rid, type, sort) SELECT n.id, m.k * '.$per.' + (n.id - 1 - m.k * '.$per.' + x.d) % '.$per.' + 1, \'related\', x.d'
        .' FROM '.$pre.'nodes AS n INNER JOIN profile_map AS m ON m.tid = n.tid AND m.rel = 1 INNER JOIN (SELECT 1 AS d UNION ALL SELECT 2) AS x WHERE m.tree = 0 OR x.d = 1');
    $pdo->exec('INSERT INTO '.$pre.'node_relations (nid, rid, type, sort) SELECT n.id, m.k * '.$per.' + (n.id - 1 - m.k * '.$per.') DIV 10 + 1, \'parent\', 0'
        .' FROM '.$pre.'nodes AS n INNER JOIN profile_map AS m ON m.tid = n.tid AND m.tree = 1 WHERE n.id - 1 - m.k * '.$per.' >= 1');
    $pdo->exec('INSERT INTO '.$pre.'node_assets (nid, kind, role, src, name, title, intro, mime, size, sort) SELECT n.id, r.kind, r.role, CONCAT(r.src, n.id, \'-\', r.slot,'
        .' IF(r.kind = \'image\', \'.jpg\', IF(r.kind = \'video\', \'.mp4\', \'.zip\'))), CONCAT(r.role, \'-\', n.id), CONCAT(\'Resource \', r.slot + 1), \'\','
        .' IF(r.kind = \'image\', \'image/jpeg\', IF(r.kind = \'video\', \'video/mp4\', \'application/zip\')), 1024 * (n.id % 900 + 1), r.slot'
        .' FROM '.$pre.'nodes AS n INNER JOIN profile_role AS r ON r.tid = n.tid');
    $pdo->exec('INSERT INTO '.$pre.'node_support (nid, aid, state, prio) SELECT n.id, 0, n.id % 3, n.id % 4 FROM '.$pre.'nodes AS n'
        .' INNER JOIN '.$pre.'node_types AS t ON t.id = n.tid AND t.ext = \'support\'');
    $pdo->exec('INSERT INTO '.$pre.'node_sync (nid, url, refresh) SELECT n.id, CONCAT(\'https://example.com/feed/\', n.id, \'.xml\'), 0 FROM '.$pre.'nodes AS n'
        .' INNER JOIN '.$pre.'node_types AS t ON t.id = n.tid AND t.ext = \'sync\'');
    $out = [];
    foreach (['users', 'categories', 'nodes', 'node_categories', 'node_relations', 'node_assets', 'node_support', 'node_sync'] as $one) {
        $pdo->query('ANALYZE TABLE '.$pre.$one)->fetchAll();
        $out[$one] = (int)$pdo->query('SELECT COUNT(*) FROM '.$pre.$one)->fetchColumn();
    }
    return $out;
}

# Warm one scenario, then repeat it: the statements of each run through the shared database, the wall time and the statement time, with the trace of the last run
# and the plans of its read statements, taken at once while the data is still the one the run saw
function getProfileRun(Closure $fn, int $runs): array {
    global $db, $pbase;
    for ($i = 0; $i < 3; $i++) $fn($i);
    $nums = [];
    $walls = [];
    $sqls = 0.0;
    $all = 0;
    for ($i = 0; $i < $runs; $i++) {
        $db->keep = $i === $runs - 1;
        $db->trace = [];
        $num = $db->qnum;
        $sql = $db->sqltime;
        $time = microtime(true);
        $fn($i + 3);
        $walls[] = (microtime(true) - $time) * 1000;
        $nums[] = $db->qnum - $num;
        $sqls += $db->sqltime - $sql;
        $all += $db->qnum - $num;
    }
    $db->keep = false;
    $plans = [];
    foreach ($db->trace as $one) {
        if (($plan = getProfilePlan($pbase, $one)) === []) continue;
        $plans[] = ['sql' => substr((string)preg_replace('/\s+/', ' ', $one['sql']), 0, 160), 'kind' => getProfileKind($one['sql']),
            'parts' => substr_count($one['sql'], ' UNION ALL ') + 1, 'reads' => getProfileReads($pbase, $one), 'plan' => $plan];
    }
    sort($walls);
    $pick = fn(float $part): float => round($walls[min(count($walls) - 1, (int)ceil($part * count($walls)) - 1)], 2);
    return ['sql' => max($nums), 'least' => min($nums), 'p50' => $pick(0.5), 'p95' => $pick(0.95), 'avg' => $all ? round($sqls * 1000 / $all, 3) : 0.0,
        'trace' => $db->trace, 'plans' => $plans];
}

# The plan of one recorded read statement through EXPLAIN with its own parameters: table, access type, key and estimated rows of every step
function getProfilePlan(PDO $pdo, array $one): array {
    if (!preg_match('/^\s*(SELECT|\(SELECT)/i', $one['sql']) || preg_match('/FOR UPDATE\s*$/i', $one['sql'])) return [];
    $stm = $pdo->prepare('EXPLAIN '.$one['sql']);
    $stm->execute($one['pars']);
    $out = [];
    foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $row) $out[] = [(string)$row['table'], (string)$row['type'], (string)($row['key'] ?? ''), (int)$row['rows'],
        (string)($row['Extra'] ?? '')];
    return $out;
}

# The rows one recorded read statement really reads: the handler read counters of the session around one more run of it with its own parameters
function getProfileReads(PDO $pdo, array $one): int {
    $pdo->exec('FLUSH STATUS');
    $stm = $pdo->prepare($one['sql']);
    $stm->execute($one['pars']);
    $stm->fetchAll();
    $num = 0;
    foreach ($pdo->query('SHOW SESSION STATUS LIKE \'Handler_read%\'')->fetchAll(PDO::FETCH_NUM) as [, $val]) $num += (int)$val;
    return $num;
}

# What a read statement of a list does, told by what it reads and never by the shape of the statement: the page, the count or the time bound of the list, else other
function getProfileKind(string $sql): string {
    if (str_contains($sql, ' AS pin, ') && str_contains($sql, ' LIMIT ')) return 'page';
    if (str_contains($sql, 'UNIX_TIMESTAMP(MIN(')) return 'deadline';
    return preg_match('/^SELECT (SUM\(q\.num\)|COUNT\(\*\)) AS num FROM /', $sql) ? 'count' : 'other';
}

# The reads a list statement may reach: every part of a page reads its rows up to the page end and hands them to the union, the rows of the page itself
# pass the page table, their full row and the two joined names, the pinned part sorts all pinned rows of the type and the extra category part all links of the category;
# a count reads the rows of the category, a time bound the pinned and timed rows of the type, and each bound carries a fixed allowance for the lookups of the plan
function getProfileBound(PDO $pdo, array $plan, array $shape): ?int {
    $pre = PPREF.'_';
    $num = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $links = $shape['cid'] ? $num('SELECT COUNT(*) FROM '.$pre.'node_categories WHERE cid = '.$shape['cid']) : 0;
    $pins = $num('SELECT COUNT(*) FROM '.$pre.'nodes WHERE tid = '.$shape['tid'].' AND status = 2 AND pinned <> 0');
    return match ($plan['kind']) {
        'page' => 2 * $shape['end'] * $plan['parts'] + 4 * $shape['size'] + 2 * $pins + 3 * $links + 50,
        'count' => $shape['cid'] ? 3 * ($num('SELECT COUNT(*) FROM '.$pre.'nodes WHERE cid = '.$shape['cid']) + $links) + 50 : null,
        'deadline' => 3 * $num('SELECT COUNT(*) FROM '.$pre.'nodes WHERE tid = '.$shape['tid'].' AND status = 2 AND (pinned <> 0 OR published > NOW() OR expires > NOW())') + 50,
        default => null,
    };
}

# Every scenario of the budgets on the filled database, a type with its extension where it has one; ids are picked from the stored rows, never assumed
function getProfileScenes(PDO $pdo, array $types, int $runs): array {
    $pre = PPREF.'_';
    $pick = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
    $news = $types['news'];
    $files = $types['files'];
    $item = $pick('SELECT n.id FROM '.$pre.'nodes AS n WHERE n.tid = '.$news->id.' AND n.status = 2 AND EXISTS (SELECT 1 FROM '.$pre.'node_relations AS r'
        .' WHERE r.nid = n.id) ORDER BY n.id LIMIT 1');
    $plain = $pick('SELECT id FROM '.$pre.'nodes WHERE tid = '.$types['docs']->id.' AND status = 2 ORDER BY id LIMIT 1');
    $asset = $pick('SELECT a.id FROM '.$pre.'node_assets AS a INNER JOIN '.$pre.'nodes AS n ON n.id = a.nid WHERE n.tid = '.$files->id.' AND n.status = 2'
        .' AND a.role = \'download\' ORDER BY a.id LIMIT 1');
    $cat = $pick('SELECT cid FROM '.$pre.'nodes WHERE id = '.$item);
    $read = function (NodeType $type, int $size, int $page = 1, int $cid = 0, string $let = '', bool $dead = false, array $ord = []) use ($runs): array {
        $fn = function () use ($type, $size, $page, $cid, $let, $dead, $ord): void {
            $query = getProfileQuery(false, $type);
            $query->setNodeType($query->getNodeType($type->name))->setNodePage($page, $size);
            if ($cid) $query->setNodeCategory($cid);
            if ($let !== '') $query->setNodeLetter($let);
            if ($ord) $query->setNodeOrder(...$ord);
            if ($query->getNodeCount()) $query->getNodeList();
            if ($dead) $query->getNodeDeadline();
        };
        return ['budget' => $dead ? 'build' : 'list', 'shape' => ['tid' => $type->id, 'end' => $page * $size, 'size' => $size, 'cid' => $cid]] + getProfileRun($fn, $runs);
    };
    $out = [];
    foreach ($types as $name => $type) {
        if ($type->ext === 'support') continue;
        $out['list '.$name] = $read($type, $type->settings['list']['limit']);
    }
    $out['list media one'] = $read($types['media'], 1);
    $out['list media max'] = $read($types['media'], 100);
    $out['list news category'] = $read($news, 10, 1, $cat);
    $out['list news category page 5'] = $read($news, 10, 5, $cat);
    $out['list news last page'] = $read($news, 10, intdiv($pick('SELECT COUNT(*) FROM '.$pre.'nodes WHERE tid = '.$news->id.' AND status = 2') + 9, 10));
    $out['list news title'] = $read($news, 10, 1, 0, '', false, ['title', 'asc']);
    $out['list news title desc'] = $read($news, 10, 1, 0, '', false, ['title', 'desc']);
    $out['list news updated'] = $read($news, 10, 1, 0, '', false, ['updated', 'desc']);
    $out['list news published asc'] = $read($news, 10, 1, 0, '', false, ['published', 'asc']);
    $out['list docs letter'] = $read($types['docs'], 50, 1, 0, 'b');
    $out['build news'] = $read($news, 10, 1, 0, '', true);
    $out['build news category'] = $read($news, 10, 1, $cat, '', true);
    $out['build media max'] = $read($types['media'], 100, 1, 0, '', true);
    $out['view docs'] = ['budget' => 'view'] + getProfileRun(function () use ($types, $plain): void {
        $query = getProfileQuery(false);
        $type = $query->getNodeType('docs');
        $query->getNode($plain, $type);
    }, $runs);
    $out['view news related'] = ['budget' => 'viewrel'] + getProfileRun(function () use ($item): void {
        $query = getProfileQuery(false);
        $type = $query->getNodeType('news');
        $node = $query->getNode($item, $type);
        $refs = [];
        foreach ($node->rels ?? [] as $rel) if ($rel->type === 'related') $refs[$rel->rid] = 'news';
        if ($refs) $query->getNodeTargetList($refs);
    }, $runs);
    $docs = $types['docs'];
    $deep = $pick('SELECT n.id FROM '.$pre.'nodes AS n WHERE n.tid = '.$docs->id.' AND n.status = 2 AND EXISTS (SELECT 1 FROM '.$pre.'node_relations AS r'
        .' WHERE r.nid = n.id AND r.type = \'parent\') ORDER BY n.id LIMIT 1');
    $batch = intdiv($pick('SELECT COUNT(*) FROM '.$pre.'nodes WHERE tid = '.$docs->id.' AND status = 2'), 500) + 1 + (int)$docs->settings['features']['categories'];
    $out['view docs tree'] = ['budget' => 'viewrel', 'extra' => $batch] + getProfileRun(function () use ($deep): void {
        $query = getProfileQuery(false);
        $type = $query->getNodeType('docs');
        $node = $query->getNode($deep, $type);
        $refs = [];
        foreach ($node->rels ?? [] as $rel) if ($rel->type === 'related') $refs[$rel->rid] = 'docs';
        if ($refs) $query->getNodeTargetList($refs);
        $query->setNodeType($type);
        $after = 0;
        do {
            $part = $query->getNodeTree($after);
            $after = $part ? $part[count($part) - 1]['id'] : 0;
        } while (count($part) === 500);
    }, $runs);
    $out['attach news'] = ['budget' => 'attach'] + getProfileRun(function () use ($item): void {
        $query = getProfileQuery(false);
        $query->getNodeContent($item, $query->getNodeType('news'));
    }, $runs);
    $out['asset files'] = ['budget' => 'asset'] + getProfileRun(function () use ($asset): void {
        $query = getProfileQuery(false);
        $query->getNodeAsset($asset, $query->getNodeType('files'));
    }, $runs);
    $out['download files'] = ['budget' => 'download'] + getProfileRun(function () use ($asset): void {
        $query = getProfileQuery(false);
        $type = $query->getNodeType('files');
        $query->getNodeAsset($asset, $type);
        getProfileWriter(false, $type)->updateNodeAssetHits($asset, $type);
    }, $runs);
    $out['report files'] = ['budget' => 'report'] + getProfileRun(function () use ($asset): void {
        $query = getProfileQuery(false);
        $type = $query->getNodeType('files');
        $query->getNodeAsset($asset, $type);
        getProfileWriter(false, $type)->updateNodeAssetReport($asset, $type);
    }, $runs);
    $out['admin list news'] = ['budget' => 'admin'] + getProfileRun(function () use ($news): void {
        $query = getProfileQuery(true, $news);
        $query->setNodeType($query->getNodeType('news'))->setNodeStatus(NodeStatus::Published)->setNodeSets(false)->setNodePage(1, 10);
        if ($query->getNodeCount()) $query->getNodeList();
    }, $runs);
    $out['edit news'] = ['budget' => 'edit'] + getProfileRun(function () use ($item): void {
        $query = getProfileQuery(true);
        $query->getNode($item, $query->getNodeType('news'));
    }, $runs);
    $ver = fn(int $id): int => $pick('SELECT version FROM '.$pre.'nodes WHERE id = '.$id);
    $out['status news'] = ['budget' => 'status'] + getProfileRun(function (int $i) use ($news, $item, $ver): void {
        getProfileWriter(true, $news)->updateNodeStatus($item, ($i % 2) ? NodeStatus::Published : NodeStatus::Disabled, $ver($item));
    }, $runs);
    $gone = $pdo->query('SELECT id FROM '.$pre.'nodes WHERE tid = '.$news->id.' AND status = 2 AND id > '.$item.' ORDER BY id DESC LIMIT '.($runs + 3))
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($gone as $id) getProfileWriter(true, $news)->updateNodeStatus((int)$id, NodeStatus::Deleted, $ver((int)$id));
    $out['delete news'] = ['budget' => 'delete'] + getProfileRun(function (int $i) use ($news, $gone, $ver): void {
        getProfileWriter(true, $news)->deleteNode((int)$gone[$i], $ver((int)$gone[$i]), $GLOBALS['com']);
    }, $runs);
    return $out;
}

# The statements of a write scenario that belong to Node: only those that name a Node table count against its budget, Point and the shared integrations are measured apart
function getProfileNodeSql(array $trace): int {
    return count(array_filter($trace, fn(array $v): bool => str_contains($v['sql'], PPREF.'_node')));
}

if ($preport['error'] === '') {
    try {
        $preport['server'] = (string)$pbase->query('SELECT VERSION()')->fetchColumn();
        $db = new ProfileBase($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name'], $conf['db']['charset']);
        $pnt = new Point($db, $conf['points'] ?? []);
        $ptime = microtime(true);
        $ptypes = addProfileTypes();
        $preport['types'] = array_map(fn(NodeType $v): array => [$v->id, $v->ext, (int)$v->active, $v->settings['list']['limit']], $ptypes);
        $preport['fill'] = addProfileRows($pbase, $ptypes, $prows);
        $preport['fill']['seconds'] = round(microtime(true) - $ptime, 1);
        $pscenes = getProfileScenes($pbase, $ptypes, $pruns);
        foreach ($pscenes as $pkey => $pone) {
            $pwrite = in_array($pone['budget'], ['status', 'delete'], true);
            $pnum = $pwrite ? getProfileNodeSql($pone['trace']) : $pone['sql'];
            $plans = $pone['plans'];
            foreach ($plans as $pi => $pplan) {
                foreach ($pplan['plan'] as $pstep) {
                    $pnode = str_starts_with($pstep[0], PPREF.'_node') || in_array($pstep[0], ['n', 'nc'], true);
                    if ($pstep[1] === 'ALL' && $pnode && $pstep[3] > 1000) $preport['fail'][] = $pkey.': full scan of '.$pstep[0];
                }
                if (!isset($pone['shape'])) continue;
                $plans[$pi]['bound'] = getProfileBound($pbase, $pplan, $pone['shape']);
                if ($plans[$pi]['bound'] !== null && $pplan['reads'] > $plans[$pi]['bound']) {
                    $preport['fail'][] = $pkey.': the '.$pplan['kind'].' read '.$pplan['reads'].' rows over its bound '.$plans[$pi]['bound'];
                }
                $pkeys = array_map(fn(array $v): string => $v[0].':'.$v[2], $pplan['plan']);
                if ($pone['shape']['cid'] && in_array($pplan['kind'], ['page', 'count'], true) && array_diff(['n:cat', 'nc:cat'], $pkeys)) {
                    $preport['fail'][] = $pkey.': the '.$pplan['kind'].' of a category does not read both category indexes';
                }
            }
            $pcap = PBUDGET[$pone['budget']] + ($pone['extra'] ?? 0);
            if ($pnum > $pcap) $preport['fail'][] = $pkey.': '.$pnum.' statements over the budget '.$pcap;
            $preport['scenes'][$pkey] = ['node' => $pnum, 'budget' => $pcap, 'all' => $pone['sql'], 'least' => $pone['least'], 'p50' => $pone['p50'],
                'p95' => $pone['p95'], 'avg' => $pone['avg'], 'plans' => $plans];
        }
        $pone = $preport['scenes']['list media one']['all'] ?? -1;
        if ($pone !== ($preport['scenes']['list media max']['all'] ?? -2)) $preport['fail'][] = 'the largest page costs other statements than a page of one';
    } catch (Throwable $err) {
        $preport['error'] = get_class($err).': '.$err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
    }
}
setProfileEnd();
