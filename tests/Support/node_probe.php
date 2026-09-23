<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for docs/node: the schema and the closed class map of stage S08, the reads of NodeQuery and the request context of stage S09, and the type writer of stage S10
# It boots the real core the way index.php does, so what it reports about loading is what a request sees: the map registered and no Node class declared yet
# The schema is read from the shipped SQL files and never written out here; the statements are split by the installer splitter lifted out of core/admin.php
# Without a mode two disposable databases are created: one installed fresh, one installed without Node and brought up by the update, twice
# The mode query boots on a scratch copy of the configuration that carries the probe types, fills one disposable database and reads it through NodeQuery
# The mode context is a child of the mode query: it boots against that database as one visitor and answers the context getNodeContext() builds for the request
# The mode service boots on scratch sources, backup, cache and upload root shared with its children crash, restore and race, and drives NodeService on one disposable database
# The mode material boots the same way for stage S11 and writes materials through copies of the reader and the writer whose factory knows a recording extension
# Nothing touches the site database, config/, storage/ or uploads/ of the stand
$probework = (string)($argv[1] ?? '');
$pmode = (string)($argv[2] ?? '');
$pargs = array_slice($argv, 3);
require_once __DIR__.'/probe_boot.php';
$pmat = in_array($pmode, ['material', 'mtree', 'mcrash', 'mlink', 'mpub', 'mpubcrash', 'msched', 'mupload'], true);
$pshare = $pmat || in_array($pmode, ['service', 'crash', 'restore', 'race'], true);
if ($pmode !== '') {
    $pconf = $probework.'/'.($pmode === 'context' ? 'ctx-'.($pargs[1] ?? '') : ($pshare ? 'svc' : 'config'));
    if ($pmode === 'service' || $pmode === 'material') addProbeScratch($probework);
    elseif (!$pshare) addProbeConfig($pconf, $pmode, $pargs);
    define('CONFIG_DIR', $pconf);
    define('BACKUP_DIR', $probework.($pshare ? '/svcbackup' : '/backup'));
    define('CACHE_DIR', $probework.($pshare ? '/svccache' : '/cache'));
    if ($pshare) define('UPLOADS_DIR', $probework.'/svcuploads');
    if ($pmode === 'context') setProbeVisitor($pargs[1] ?? '');
    if ($pmode === 'mupload') setProbeVisitor($pargs[0] ?? '');
}
require_once BASE_DIR.'/core/system.php';
if ($pshare) {
    foreach (['_COVER' => 'Cover', '_GALLERY' => 'Gallery'] as $pkey => $pval) if (!defined($pkey)) define($pkey, $pval);
}

# The database of a writer that dies at its commit: before it the transaction is lost with the connection, after it the commit is stored but the writer never answers
final class ProbeCrashDb extends Database {

    public string $when = '';

    # Die where the commit is, after committing when asked
    public function setSqlCommit(): bool {
        if ($this->when === 'after') parent::setSqlCommit();
        exit(0);
    }
}

# The table prefix of the disposable databases
const PROBEPREF = 'probe';

# The Node tables of the fresh schema in the order they have to be created
const PROBENODE = ['node_types', 'nodes', 'node_assets', 'node_categories', 'node_publish', 'node_relations', 'node_support', 'node_sync'];

# Every name the closed map may load, and names a crafted or future request could ask for
const PROBECLASS = [
    'Node', 'NodeAsset', 'NodeContext', 'NodeException', 'NodeExtension', 'NodeInput', 'NodeQuery', 'NodeRelation', 'NodeService', 'NodeStatus', 'NodeTarget', 'NodeType',
    'NodeTypeInput', 'NodeView',
];
const PROBEFAKE = ['NodeSupport', 'NodeSync', '../../../config/db', 'ext/load', 'entity', 'NodeLoader'];

$GLOBALS['pnames'] = [];

# The Node files the process has included so far, as paths relative to the class directory
function getProbeFiles(): array {
    $root = str_replace('\\', '/', BASE_DIR).'/core/classes/node/';
    $out = [];
    foreach (get_included_files() as $one) {
        $one = str_replace('\\', '/', $one);
        if (str_starts_with($one, $root)) $out[] = substr($one, strlen($root));
    }
    sort($out);
    return $out;
}

# The Node classes, interfaces and enums the process has declared so far
function getProbeDeclared(): array {
    $all = array_merge(get_declared_classes(), get_declared_interfaces());
    $out = array_values(array_intersect(PROBECLASS, $all));
    foreach (PROBECLASS as $one) if (enum_exists($one, false) && !in_array($one, $out, true)) $out[] = $one;
    sort($out);
    return $out;
}

# What a request has loaded after the bootstrap, and what each first use and each crafted name loads on top of it
function getProbeLoad(): array {
    $out = ['boot' => ['files' => getProbeFiles(), 'declared' => getProbeDeclared(), 'ext' => function_exists('getNodeExtension')]];
    new NodeContext(0, [], 0, [], false, false, '127.0.0.1', 'en');
    $out['context'] = getProbeFiles();
    $out['status'] = NodeStatus::Draft->checkStatusMove(NodeStatus::Pending);
    $out['enum'] = getProbeFiles();
    $fake = [];
    foreach (PROBEFAKE as $one) $fake[$one] = class_exists($one) || interface_exists($one) || enum_exists($one);
    $out['fake'] = $fake;
    $out['after'] = getProbeFiles();
    $all = [];
    foreach (PROBECLASS as $one) $all[$one] = class_exists($one) || interface_exists($one) || enum_exists($one);
    $out['all'] = $all;
    $out['files'] = getProbeFiles();
    return $out;
}

# Open one connection to the server, with a disposable database selected when one is named
function getProbeRoot(string $name = ''): PDO {
    global $conf;
    $dsn = 'mysql:host='.$conf['db']['host'].($name === '' ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create one more disposable database and return a connection to it; the site database can never be the one created or dropped
function addProbeBase(): PDO {
    global $conf;
    $name = 'slaed_node_'.bin2hex(random_bytes(4));
    if ($name === $conf['db']['name']) throw new RuntimeException('The disposable name collides with the site database');
    getProbeRoot()->exec('CREATE DATABASE `'.$name.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $GLOBALS['pnames'][] = $name;
    return getProbeRoot($name);
}

# Drop every disposable database again and report whether the server is left without them
function deleteProbeBases(): bool {
    global $conf;
    $done = true;
    foreach ($GLOBALS['pnames'] as $name) {
        if ($name === $conf['db']['name']) continue;
        try {
            getProbeRoot()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
        } catch (Throwable) {
            $done = false;
        }
    }
    return $done;
}

# Lift the installer splitter and the statement cleaner it calls out of core/admin.php, which the public bootstrap of this probe does not load
function addProbeSplitter(): void {
    $code = (string)file_get_contents(BASE_DIR.'/core/admin.php');
    foreach (['getSqlbatch', 'getSqlclean'] as $name) {
        if (function_exists($name)) continue;
        $from = strpos($code, 'function '.$name.'(');
        $to = $from === false ? false : strpos($code, "\n}\n", $from);
        if ($from === false || $to === false) throw new RuntimeException($name.'() is gone from core/admin.php');
        eval(substr($code, $from, $to - $from + 3));
    }
}

# The statements of one shipped SQL file with the placeholders filled the way the installer fills them
function getProbeStatements(string $file, string $pref = PROBEPREF): array {
    $sql = getSqlbatch((string)file_get_contents(BASE_DIR.'/setup/sql/'.$file));
    if ($sql['error'] !== '') throw new RuntimeException($file.' does not split: '.$sql['error']);
    $fill = static fn(string $one): string => str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$pref, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $one);
    return array_map($fill, $sql['statements']);
}

# Run statements one by one on a connection and return the failures as the head of the statement with the driver message
function setProbeRun(PDO $pdo, array $list): array {
    $out = [];
    foreach ($list as $one) {
        try {
            $pdo->exec($one);
        } catch (PDOException $err) {
            $out[] = substr(preg_replace('/\s+/', ' ', trim($one)), 0, 120).' => '.$err->getMessage();
        }
    }
    return $out;
}

# The create statement the server reports for each table, without the counter that only says how many rows were ever written
function getProbeCreate(PDO $pdo, array $list): array {
    $out = [];
    foreach ($list as $name) {
        $row = $pdo->query('SHOW CREATE TABLE `'.PROBEPREF.'_'.$name.'`')->fetch(PDO::FETCH_NUM);
        $out[$name] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string)$row[1]);
    }
    return $out;
}

# The indexes of one table as name => ordered column list, a prefix length kept with its column
function getProbeIndexes(PDO $pdo, string $name): array {
    $sql = 'SELECT INDEX_NAME, COLUMN_NAME, SUB_PART, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        .' ORDER BY INDEX_NAME, SEQ_IN_INDEX';
    $st = $pdo->prepare($sql);
    $st->execute([PROBEPREF.'_'.$name]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = ($row['NON_UNIQUE'] ? '' : 'unique ').$row['INDEX_NAME'];
        $out[$key][] = $row['COLUMN_NAME'].($row['SUB_PART'] ? '('.$row['SUB_PART'].')' : '');
    }
    ksort($out);
    return $out;
}

# The named constraints and the engine of every Node table
function getProbeNames(PDO $pdo): array {
    $st = $pdo->query('SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS'
        .' WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE IN (\'FOREIGN KEY\', \'CHECK\') ORDER BY CONSTRAINT_NAME');
    $out = ['constraints' => [], 'engines' => []];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $out['constraints'][$row['CONSTRAINT_NAME']] = $row['CONSTRAINT_TYPE'];
    $st = $pdo->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE \''.PROBEPREF.'\\_node%\'');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $out['engines'][$row['TABLE_NAME']] = $row['ENGINE'];
    ksort($out['engines']);
    return $out;
}

# Try one statement and answer whether the server accepted it, or the driver code it refused it with
function getProbeTry(PDO $pdo, string $sql): string {
    try {
        $pdo->exec($sql);
    } catch (PDOException $err) {
        return 'refused '.($err->errorInfo[1] ?? 0);
    }
    return 'accepted';
}

# The rows one material still owns in every dependent table, relations counted from both ends
function getProbeOwned(PDO $pdo, int $id): array {
    $out = [];
    foreach (['node_assets', 'node_categories', 'node_publish', 'node_support', 'node_sync'] as $name) {
        $out[$name] = intval($pdo->query('SELECT COUNT(*) FROM `'.PROBEPREF.'_'.$name.'` WHERE nid = '.$id)->fetchColumn());
    }
    $out['node_relations'] = intval($pdo->query('SELECT COUNT(*) FROM `'.PROBEPREF.'_node_relations` WHERE nid = '.$id.' OR rid = '.$id)->fetchColumn());
    return $out;
}

# Every constraint of the fresh schema against rows that keep it and rows that break it, then the physical delete of a material and of its type
function getProbeRules(PDO $pdo): array {
    $pre = '`'.PROBEPREF.'_';
    $node = 'INSERT INTO '.$pre.'nodes` (id, tid, title, intro, body, field) VALUES ';
    $out = [
        'type' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_types` (id, name, title, intro) VALUES (1, \'news\', \'News\', \'\')'),
        'twin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_types` (name, title, intro) VALUES (\'news\', \'Twin\', \'\')'),
        'orphan' => getProbeTry($pdo, $node.'(9, 99, \'x\', \'\', \'\', \'{}\')'),
        'nodes' => getProbeTry($pdo, $node.'(1, 1, \'a\', \'\', \'\', \'{}\'), (2, 1, \'b\', \'\', \'\', \'{}\'), (3, 1, \'c\', \'\', \'\', \'{}\')'),
        'category' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_categories` (nid, cid) VALUES (1, 5), (2, 5)'),
        'catwin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_categories` (nid, cid) VALUES (1, 5)'),
        'catorphan' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_categories` (nid, cid) VALUES (99, 5)'),
        'relation' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_relations` (nid, rid, type) VALUES (1, 2, \'related\'), (2, 1, \'parent\'), (3, 2, \'related\')'),
        'reltwin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_relations` (nid, rid, type) VALUES (1, 2, \'related\')'),
        'relself' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_relations` (nid, rid, type) VALUES (3, 3, \'related\')'),
        'relorphan' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_relations` (nid, rid, type) VALUES (3, 99, \'related\')'),
        'asset' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_assets` (nid, kind, role, src, intro) VALUES (1, \'file\', \'download\', \'a.pdf\', \'\'),'
            .' (1, \'image\', \'cover\', \'a.png\', \'\')'),
        'kind' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_assets` (nid, kind, role, src, intro) VALUES (2, \'doc\', \'download\', \'a.doc\', \'\')'),
        'role' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_assets` (nid, kind, role, src, intro) VALUES (2, \'file\', \'\', \'a.doc\', \'\')'),
        'src' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_assets` (nid, kind, role, src, intro) VALUES (2, \'file\', \'download\', \'\', \'\')'),
        'support' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_support` (nid) VALUES (1)'),
        'suptwin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_support` (nid) VALUES (1)'),
        'state' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_support` (nid, state) VALUES (2, 3)'),
        'prio' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_support` (nid, prio) VALUES (2, 4)'),
        'sync' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_sync` (nid, url) VALUES (1, \'https://example.com/feed\')'),
        'manual' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_sync` (nid, url, refresh) VALUES (2, \'https://example.com/feed\', 0)'),
        'refresh' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_sync` (nid, url, refresh) VALUES (3, \'https://example.com/feed\', 299)'),
        'longest' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_sync` (nid, url, refresh) VALUES (3, \'https://example.com/feed\', 31536001)'),
        'synctwin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_sync` (nid, url) VALUES (1, \'https://example.com/other\')'),
        'publish' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_publish` (nid, published, due) VALUES (1, \'2030-01-01 00:00:00\', \'2030-01-01 00:00:00\')'),
        'pubtwin' => getProbeTry($pdo, 'INSERT INTO '.$pre.'node_publish` (nid, published, due) VALUES (1, \'2031-01-01 00:00:00\', \'2031-01-01 00:00:00\')'),
        'defaults' => $pdo->query('SELECT status, version, comon, home, pinned, cid, uid, aname, ip, poll, published, expires FROM '.$pre.'nodes` WHERE id = 1')
            ->fetch(PDO::FETCH_ASSOC),
        'typedef' => $pdo->query('SELECT ext, active, sort, version FROM '.$pre.'node_types` WHERE id = 1')->fetch(PDO::FETCH_ASSOC),
        'supdef' => $pdo->query('SELECT aid, state, prio, version FROM '.$pre.'node_support` WHERE nid = 1')->fetch(PDO::FETCH_ASSOC),
        'syncdef' => $pdo->query('SELECT refresh, due, etag, modified, fails, error FROM '.$pre.'node_sync` WHERE nid = 1')->fetch(PDO::FETCH_ASSOC),
    ];
    $out['typedrop'] = getProbeTry($pdo, 'DELETE FROM '.$pre.'node_types` WHERE id = 1');
    $out['before'] = getProbeOwned($pdo, 1);
    $out['drop'] = getProbeTry($pdo, 'DELETE FROM '.$pre.'nodes` WHERE id = 1');
    $out['after'] = getProbeOwned($pdo, 1);
    $out['others'] = intval($pdo->query('SELECT COUNT(*) FROM '.$pre.'node_relations`')->fetchColumn());
    $out['retype'] = getProbeTry($pdo, 'UPDATE '.$pre.'nodes` SET tid = 99 WHERE id = 2');
    $out['empty'] = getProbeTry($pdo, 'DELETE FROM '.$pre.'nodes`');
    $out['typegone'] = getProbeTry($pdo, 'DELETE FROM '.$pre.'node_types` WHERE id = 1');
    return $out;
}

# Install the whole fresh schema with foreign key checks left on, then read what the server made of every Node table and try its constraints
function getProbeFresh(): array {
    $pdo = addProbeBase();
    $out = ['checks' => intval($pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn())];
    $out['failed'] = setProbeRun($pdo, getProbeStatements('table.sql'));
    $out['create'] = getProbeCreate($pdo, array_merge(PROBENODE, ['admins']));
    $idx = [];
    foreach (PROBENODE as $name) $idx[$name] = getProbeIndexes($pdo, $name);
    $out['indexes'] = $idx;
    $out += getProbeNames($pdo);
    $out['rules'] = getProbeRules($pdo);
    return $out;
}

# Install the fresh schema without Node and with the 6.2 administrator column, run the whole update twice and read the result after each run
function getProbeUpdate(): array {
    $pdo = addProbeBase();
    $keep = [];
    foreach (getProbeStatements('table.sql') as $one) {
        if (!preg_match('/^CREATE TABLE `'.PROBEPREF.'_node/', ltrim(preg_replace('/^#.*$/m', '', $one)))) $keep[] = $one;
    }
    $out = ['base' => setProbeRun($pdo, $keep)];
    $pdo->exec('ALTER TABLE `'.PROBEPREF.'_admins` MODIFY `modules` VARCHAR(255) NOT NULL DEFAULT \'\'');
    $pdo->exec('INSERT INTO `'.PROBEPREF.'_admins` (name, email, modules) VALUES (\'probe\', \'probe@probe.test\', \'forum,shop,node,node-news\')');
    $out['checks'] = intval($pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn());
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE \''.PROBEPREF.'\\_node%\'';
    $out['tables'] = intval($pdo->query($sql)->fetchColumn());
    $list = getProbeStatements('table_update6_3.sql');
    $out['first'] = setProbeRun($pdo, $list);
    $out['create'] = getProbeCreate($pdo, array_merge(PROBENODE, ['admins']));
    $out['second'] = setProbeRun($pdo, $list);
    $out['again'] = getProbeCreate($pdo, array_merge(PROBENODE, ['admins']));
    $out['modules'] = (string)$pdo->query('SELECT modules FROM `'.PROBEPREF.'_admins` WHERE name = \'probe\'')->fetchColumn();
    return $out;
}

# Write one scratch configuration source in the shape getConfig() reads
function setProbeFile(string $file, array $data): void {
    file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n");
}

# The switches of the features section with the named ones on
function getProbeFeatures(array $on): array {
    $out = [];
    $keys = ['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related', 'tree'];
    foreach ($keys as $key) $out[$key] = in_array($key, $on, true);
    return $out;
}

# One stored role of the assets section
function getProbeRole(string $title, string $mode, array $kinds, int $min, int $max, bool $link, bool $report, int $sort): array {
    return ['title' => $title, 'intro' => '', 'kinds' => $kinds, 'extensions' => [], 'maxbytes' => null, 'min' => $min, 'max' => $max, 'canlink' => $link, 'report' => $report,
        'mode' => $mode, 'active' => true, 'sort' => $sort];
}

# The stored sections of the three proof types of 06-types.md: news, docs and files as their profiles define them
function getProbeProfiles(): array {
    $all = ['published', 'updated', 'title', 'views', 'rating'];
    $show = ['category', 'author', 'date', 'views'];
    return [
        'news' => [
            'list' => ['orders' => $all, 'order' => 'published', 'dir' => 'desc', 'limit' => 10, 'alpha' => false, 'show' => $show],
            'view' => ['mode' => 'article'],
            'features' => getProbeFeatures(['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related']),
            'assets' => [
                'cover' => getProbeRole('_COVER', 'image', ['image'], 0, 1, false, false, 10),
                'gallery' => getProbeRole('_GALLERY', 'gallery', ['image'], 0, 20, false, false, 20),
            ],
            'integrations' => ['search' => true, 'rss' => true, 'sitemap' => true, 'blocks' => true, 'seo' => 'news'],
        ],
        'docs' => [
            'list' => ['orders' => ['title', 'updated', 'views'], 'order' => 'title', 'dir' => 'asc', 'limit' => 50, 'alpha' => true, 'show' => ['category', 'date', 'views']],
            'view' => ['mode' => 'docs'],
            'features' => getProbeFeatures(['categories', 'favorites', 'related', 'tree']),
            'integrations' => ['search' => true, 'rss' => false, 'sitemap' => true, 'blocks' => true, 'seo' => 'article'],
        ],
        'files' => [
            'list' => ['orders' => $all, 'order' => 'published', 'dir' => 'desc', 'limit' => 25, 'alpha' => true, 'show' => $show],
            'view' => ['mode' => 'files'],
            'features' => getProbeFeatures(['categories', 'comments', 'rating', 'favorites', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related']),
            'assets' => [
                'cover' => getProbeRole('_COVER', 'image', ['image'], 0, 1, false, false, 10),
                'download' => getProbeRole('_DOWNLOAD', 'download', ['file', 'image', 'audio', 'video'], 1, 10, true, true, 20),
            ],
            'integrations' => ['search' => true, 'rss' => true, 'sitemap' => true, 'blocks' => true, 'seo' => 'article'],
        ],
    ];
}

# The two field definitions of the files profile
function getProbeFields(): array {
    return [
        'release' => [
            'title' => '_VERSION', 'intro' => '', 'type' => 'text', 'default' => '', 'options' => ['max' => 100], 'req' => false, 'multi' => false, 'active' => true, 'sort' => 10,
        ],
        'site' => ['title' => '_URL', 'intro' => '', 'type' => 'url', 'default' => '', 'options' => [], 'req' => false, 'multi' => false, 'active' => true, 'sort' => 20],
    ];
}

# The Node configuration of the probe: the defaults of 06-types.md and every probe type, the type stale at the version given
function getProbeNodeConf(int $stale): array {
    $pro = getProbeProfiles();
    $plain = ['features' => getProbeFeatures([])];
    return ['node' => [
        'version' => '1',
        'limits' => ['maxassets' => 100, 'maxlist' => 100, 'syncbatch' => 500],
        'support' => ['state' => ['staff' => 0, 'author' => 1, 'closed' => 2], 'prio' => ['low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3]],
        'defaults' => [
            'list' => ['orders' => ['published'], 'order' => 'published', 'dir' => 'desc', 'limit' => 10, 'alpha' => false, 'show' => ['category', 'author', 'date', 'views']],
            'view' => ['mode' => 'default'],
            'form' => [],
            'workflow' => ['access' => 'user', 'groups' => [], 'publish' => [], 'notify' => ['pending' => true, 'result' => true]],
            'admin' => [],
            'features' => [],
            'assets' => [],
            'integrations' => ['search' => false, 'rss' => false, 'sitemap' => false, 'blocks' => false, 'seo' => 'website'],
        ],
        'types' => [
            'news' => ['version' => 1] + $pro['news'],
            'docs' => ['version' => 1] + $pro['docs'],
            'files' => ['version' => 1] + $pro['files'],
            'off' => ['version' => 1] + $pro['news'],
            'bad' => ['version' => 1, 'list' => ['limit' => 0]] + $pro['news'],
            'probe' => ['version' => 1, 'features' => getProbeFeatures([]), 'ext' => ['own' => true]],
            'stale' => ['version' => $stale] + $plain,
            'drift' => ['version' => 1] + $plain,
            'plain' => ['version' => 1] + $plain,
            'bare' => ['version' => 1] + $plain,
        ],
    ]];
}

# Copy the configuration sources of the stand into scratch and change only what the mode needs: the probe types for the reads, the database and the language for a visitor
function addProbeConfig(string $dir, string $mode, array $args): void {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    if (is_file($dir.'/local.php')) unlink($dir.'/local.php');
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) if (basename($file) !== 'local.php') copy($file, $dir.'/'.basename($file));
    if ($mode === 'context') {
        $data = require BASE_DIR.'/config/db.php';
        $data['db']['name'] = (string)($args[0] ?? '');
        setProbeFile($dir.'/db.php', $data);
        $data = require BASE_DIR.'/config/global.php';
        $data['multilingual'] = (($args[1] ?? '') === 'mono') ? '0' : '1';
        setProbeFile($dir.'/global.php', $data);
        return;
    }
    setProbeFile($dir.'/node.php', getProbeNodeConf(1));
    $data = require BASE_DIR.'/config/fields.php';
    $data['fields']['node'] = ['files' => getProbeFields()];
    setProbeFile($dir.'/fields.php', $data);
    $data = require BASE_DIR.'/config/uploads.php';
    $rules = require BASE_DIR.'/config/ratings.php';
    foreach (['news', 'docs', 'files', 'off', 'bad', 'probe', 'stale', 'drift', 'plain'] as $name) {
        $data['uploads'][$name] = $data['uploads']['all'];
        $rules['ratings']['node.'.$name] = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];
    }
    setProbeFile($dir.'/uploads.php', $data);
    setProbeFile($dir.'/ratings.php', $rules);
}

# Stand in for one visitor before the core boots: the account cookie, the administrator session, and request values that try to forge a context
function setProbeVisitor(string $who): void {
    $glob = require BASE_DIR.'/config/global.php';
    $users = ['anna' => '2:anna:hash-anna', 'boris' => '3:boris:hash-boris', 'dmitri' => '5:dmitri:hash-dmitri', 'pair' => '2:anna:hash-anna'];
    $admins = ['moder' => '2:moder:hash-moder', 'boss' => '3:boss:hash-boss', 'root' => '1:root:hash-root', 'pair' => '2:moder:hash-moder', 'legacy' => '4:legacy:hash-legacy'];
    $_GET = ['task' => '1', 'super' => '1', 'aid' => '1', 'mods' => 'news', 'uid' => '5', 'lang' => 'xx'];
    $_POST = ['task' => '1', 'manage' => '1', 'groups' => '1,2'];
    $_REQUEST = $_GET + $_POST;
    if (isset($users[$who])) $_COOKIE[$glob['user_c'].'-account'] = base64_encode($users[$who]);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($admins[$who])) $_SESSION[$glob['admin_c']] = base64_encode($admins[$who]);
}

# The context the booted request of one visitor gets, twice, with the statements the second call cost
function getProbeVisitor(): array {
    global $db, $locale;
    try {
        $ctx = getNodeContext();
        $num = $db->qnum;
        $again = getNodeContext();
        $num = $db->qnum - $num;
    } catch (Throwable $err) {
        return ['error' => get_class($err).': '.$err->getMessage()];
    }
    return ['error' => '', 'uid' => $ctx->uid, 'groups' => $ctx->groups, 'aid' => $ctx->aid, 'mods' => $ctx->mods, 'manage' => $ctx->manage, 'super' => $ctx->super,
        'ip' => $ctx->ip, 'lang' => $ctx->lang, 'task' => $ctx->task, 'same' => $ctx === $again, 'again' => $num, 'locale' => (string)$locale];
}

# Fill the disposable database: groups, accounts, administrators, categories, the type rows and the materials with their extra categories, relations and resources
function addProbeRows(PDO $pdo): void {
    $pre = PREFIX_DB.'_';
    $pdo->exec('INSERT INTO '.$pre.'groups (id, name, intro, points, extra) VALUES (1, \'club\', \'\', 0, 1), (2, \'active\', \'\', 100, 0)');
    $pdo->exec('INSERT INTO '.$pre.'users (id, name, email, password, block, warnings, field, grp, points, ip) VALUES'
        .' (2, \'anna\', \'anna@probe.test\', \'hash-anna\', \'\', \'\', \'\', 1, 0, \'127.0.0.1\'),'
        .' (3, \'boris\', \'boris@probe.test\', \'hash-boris\', \'\', \'\', \'\', 0, 150, \'127.0.0.1\'),'
        .' (4, \'clara\', \'clara@probe.test\', \'hash-clara\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\'),'
        .' (5, \'dmitri\', \'dmitri@probe.test\', \'hash-dmitri\', \'\', \'\', \'\', 1, 200, \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'admins (id, name, email, password, super, modules, ip) VALUES'
        .' (1, \'root\', \'root@probe.test\', \'hash-root\', 1, \'\', \'127.0.0.1\'),'
        .' (2, \'moder\', \'moder@probe.test\', \'hash-moder\', 0, \'forum,node-news,node-docs,node-news,node-Bad,node-,nodes\', \'127.0.0.1\'),'
        .' (3, \'boss\', \'boss@probe.test\', \'hash-boss\', 0, \'node,shop\', \'127.0.0.1\')');
    $cats = [[1, 'news', 'Open', '0|0', ''], [2, 'news', 'Members', '1|0', ''], [3, 'news', 'Club', '2|1', ''], [4, 'news', 'Sealed', '', ''], [5, 'news', 'English', '0|0', 'en'],
        [6, 'news', 'Russian', '0|0', 'ru'], [7, 'docs', 'Manual', '0|0', ''], [8, 'forum', 'Forum', '0|0', ''], [9, 'news', 'Active', '2|2', '']];
    $st = $pdo->prepare('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, lang) VALUES (?, ?, ?, \'\', ?, ?)');
    foreach ($cats as $row) $st->execute($row);
    $types = [[1, 'news', '', 1, 10, 1], [2, 'docs', '', 1, 20, 1], [3, 'files', '', 1, 30, 1], [4, 'off', '', 0, 40, 1], [5, 'bad', '', 1, 50, 1], [6, 'ghost', '', 1, 60, 1],
        [7, 'probe', 'probe', 1, 70, 1], [10, 'plain', '', 1, 100, 1], [11, 'bare', '', 1, 110, 1]];
    $st = $pdo->prepare('INSERT INTO '.$pre.'node_types (id, name, title, intro, ext, active, sort, version) VALUES (?, ?, ?, \'\', ?, ?, ?, ?)');
    foreach ($types as $row) $st->execute([$row[0], $row[1], ucfirst($row[1]), $row[2], $row[3], $row[4], $row[5]]);
    $news = [
        101 => [0, 2, 'Open air', 2, ['views' => 5, 'score' => 8, 'ratings' => 2, 'ip' => '192.0.2.1', 'comon' => 2]],
        102 => [1, 3, 'Open cat', 2, ['views' => 9, 'score' => 5, 'ratings' => 1]],
        103 => [2, 4, 'Members only', 2, []], 104 => [3, 2, 'Club only', 2, []], 105 => [4, 2, 'Sealed', 2, []], 106 => [0, 2, 'Future', 2, []],
        107 => [0, 2, 'Expired', 2, []], 108 => [0, 2, 'Draft', 0, ['published' => null]], 109 => [0, 2, 'Pending', 1, ['published' => null]],
        110 => [0, 2, 'Disabled', 3, []], 111 => [0, 2, 'Deleted', 4, []], 112 => [5, 2, 'English', 2, []], 113 => [6, 2, 'Russian', 2, []],
        114 => [0, 3, 'Pinned home', 2, ['pinned' => 1, 'home' => 1, 'views' => 50, 'score' => 3, 'ratings' => 1]],
        115 => [2, 3, 'Extra members', 2, []], 116 => [1, 3, 'Extra english', 2, []], 117 => [8, 2, 'Foreign cat', 2, []], 118 => [9, 2, 'Active group', 2, []],
        119 => [0, 2, 'Expiring soon', 2, []], 120 => [0, 4, '100% _sale!', 2, ['views' => 1, 'body' => 'discount 50%_off']],
        121 => [0, 0, 'Anon', 2, ['aname' => 'Guest writer', 'ip' => '10.0.0.9']],
    ];
    $rows = [];
    foreach ($news as $id => $one) $rows[$id] = [1, $one[0], $one[1], $one[2], $one[3], sprintf('2026-01-%02d 10:00:00', $id - 100), $one[4]];
    $docs = [201 => ['Alpha', 7, 2], 202 => ['alpine', 7, 2], 203 => ['Beta', 0, 2], 204 => ['Hidden parent', 0, 0], 205 => ['Orphan', 0, 2], 206 => ['Ärger', 0, 2],
        207 => ['9 lives', 0, 2], 208 => ['%percent', 0, 2]];
    foreach ($docs as $id => $one) $rows[$id] = [2, $one[1], 2, $one[0], $one[2], sprintf('2026-02-%02d 10:00:00', $id - 200), []];
    $rows[301] = [3, 0, 2, 'Tool', 2, '2026-02-20 10:00:00', ['field' => '{"release":"1.2","site":"https://example.com"}']];
    $rows[302] = [3, 0, 3, 'Tool two', 2, '2026-02-21 10:00:00', []];
    $rows[303] = [3, 0, 3, 'Broken fields', 2, '2026-02-22 10:00:00', ['field' => '[1,2]']];
    $rows[304] = [3, 0, 3, 'Files draft', 0, '2026-02-23 10:00:00', []];
    $rows[401] = [4, 0, 2, 'Off one', 2, '2026-02-24 10:00:00', []];
    $rows[701] = [7, 0, 2, 'Mine', 2, '2026-02-25 10:00:00', []];
    $rows[702] = [7, 0, 3, 'Boris own', 2, '2026-02-26 10:00:00', []];
    $rows[703] = [7, 0, 2, 'Mine too', 2, '2026-02-27 10:00:00', []];
    for ($i = 1; $i <= 30; $i++) $rows[1000 + $i] = [10, 0, 2, sprintf('Plain %02d', $i), 2, sprintf('2026-03-%02d 10:00:00', $i), []];
    $st = $pdo->prepare('INSERT INTO '.$pre.'nodes (id, tid, cid, uid, aname, ip, title, intro, body, field, poll, home, comon, pinned, views, score, ratings, status, published)'
        .' VALUES (:id, :tid, :cid, :uid, :aname, :ip, :title, :intro, :body, :field, 0, :home, :comon, :pinned, :views, :score, :ratings, :status, :published)');
    foreach ($rows as $id => [$tid, $cid, $uid, $title, $status, $pub, $more]) {
        $st->execute(['id' => $id, 'tid' => $tid, 'cid' => $cid, 'uid' => $uid, 'aname' => $more['aname'] ?? '', 'ip' => $more['ip'] ?? '', 'title' => $title,
            'intro' => 'intro of '.$id, 'body' => $more['body'] ?? 'body of '.$id, 'field' => $more['field'] ?? '', 'home' => $more['home'] ?? 0, 'comon' => $more['comon'] ?? 0,
            'pinned' => $more['pinned'] ?? 0, 'views' => $more['views'] ?? 0, 'score' => $more['score'] ?? 0, 'ratings' => $more['ratings'] ?? 0, 'status' => $status,
            'published' => array_key_exists('published', $more) ? null : $pub]);
    }
    $pdo->exec('UPDATE '.$pre.'nodes SET published = NOW() + INTERVAL 2 DAY WHERE id = 106');
    $pdo->exec('UPDATE '.$pre.'nodes SET expires = NOW() - INTERVAL 1 DAY WHERE id = 107');
    $pdo->exec('UPDATE '.$pre.'nodes SET expires = NOW() + INTERVAL 3 DAY WHERE id = 119');
    $pdo->exec('INSERT INTO '.$pre.'node_categories (nid, cid) VALUES (115, 1), (116, 5)');
    $pdo->exec('INSERT INTO '.$pre.'node_relations (nid, rid, type, sort) VALUES (202, 201, \'parent\', 1), (203, 202, \'parent\', 2), (205, 204, \'parent\', 3),'
        .' (201, 203, \'related\', 0), (301, 101, \'related\', 0), (101, 102, \'related\', 5)');
    $pdo->exec('INSERT INTO '.$pre.'node_assets (id, nid, kind, role, src, name, intro, mime, size, width, height, hits, sort) VALUES'
        .' (1, 301, \'image\', \'cover\', \'cover.png\', \'cover.png\', \'\', \'image/png\', 1000, 10, 20, 0, 0),'
        .' (2, 301, \'file\', \'download\', \'manual.pdf\', \'manual.pdf\', \'\', \'application/pdf\', 2048, NULL, NULL, 7, 0),'
        .' (3, 301, \'file\', \'download\', \'extra.zip\', \'extra.zip\', \'\', NULL, NULL, NULL, NULL, 0, 1),'
        .' (4, 301, \'image\', \'gone\', \'x.png\', \'x.png\', \'\', NULL, NULL, NULL, NULL, 0, 0),'
        .' (5, 304, \'file\', \'download\', \'draft.pdf\', \'draft.pdf\', \'\', NULL, NULL, NULL, NULL, 0, 0),'
        .' (6, 101, \'image\', \'cover\', \'n.png\', \'n.png\', \'\', NULL, NULL, NULL, NULL, 0, 0)');
}

# The test implementations of the extension interface: one the scratch map registers under the key probe, one no key names
const PROBEEXT = <<<'PHPCODE'
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

# The probe extension: the materials of its type are read by their own author only, through a join and a condition binding the reader three times
final class ProbeExtension implements NodeExtension {
    public function __construct(private Database $db, private NodeContext $ctx) {}
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        if (array_keys($config) !== ['own'] || !is_bool($config['own'])) throw new NodeException('probe config', NodeException::INVALID);
        return $config;
    }
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        return $data;
    }
    public function getNodeScope(NodeType $type): array {
        $join = 'INNER JOIN '.PREFIX_DB.'_users AS pu ON pu.id = n.uid AND pu.id = :owner';
        return ['join' => $join, 'where' => ':owner > 0 AND n.uid = :owner', 'params' => ['owner' => $this->ctx->uid]];
    }
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool {
        return true;
    }
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action): void {}
    public function addNodeData(Node $node, array $data): void {}
    public function updateNodeData(Node $before, Node $after, ?array $data): void {}
    public function deleteNodeData(Node $node): void {}
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        return [];
    }
}

# A second implementation of the interface that no key of the map names
final class ProbeOther implements NodeExtension {
    public function __construct(private Database $db, private NodeContext $ctx) {}
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        return $config;
    }
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        return $data;
    }
    public function getNodeScope(NodeType $type): array {
        return ['join' => '', 'where' => '1 = 1', 'params' => []];
    }
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool {
        return true;
    }
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action): void {}
    public function addNodeData(Node $node, array $data): void {}
    public function updateNodeData(Node $before, Node $after, ?array $data): void {}
    public function deleteNodeData(Node $node): void {}
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        return [];
    }
}
PHPCODE;

# Put a copy of the reader into scratch next to a copy of the extension factory whose closed map names the probe extension, and load the reader from there
# The reader is copied byte for byte and the factory differs in its map line alone, so the probe reads with the shipped code and a test implementation
function addProbeReader(string $dir): array {
    $real = BASE_DIR.'/core/classes/node';
    if (!is_dir($dir.'/ext')) mkdir($dir.'/ext', 0777, true);
    copy($real.'/query.php', $dir.'/query.php');
    $code = (string)file_get_contents($real.'/ext/load.php');
    $map = str_replace('$map = [];', '$map = [\'probe\' => [\'probe.php\', \'ProbeExtension\']];', $code);
    file_put_contents($dir.'/ext/load.php', $map);
    file_put_contents($dir.'/ext/probe.php', PROBEEXT);
    spl_autoload_register(static function (string $name) use ($dir): void {
        if ($name === 'NodeQuery') require_once $dir.'/query.php';
    }, true, true);
    return ['query' => sha1_file($real.'/query.php') === sha1_file($dir.'/query.php'), 'factory' => substr_count($map, "\n") === substr_count($code, "\n") && $map !== $code,
        'lines' => count(array_diff(explode("\n", $map), explode("\n", $code)))];
}

# One context of the probe by the name of its visitor, with the language given
function getProbeContext(string $who, string $lang = ''): NodeContext {
    $map = [
        'guest' => [0, [], 0, [], false, false],
        'clara' => [4, [], 0, [], false, false],
        'anna' => [2, [1], 0, [], false, false],
        'boris' => [3, [2], 0, [], false, false],
        'moder' => [0, [], 2, ['docs', 'news'], false, false],
        'boss' => [0, [], 3, [], true, false],
        'root' => [0, [], 1, [], true, true],
    ];
    [$uid, $grp, $aid, $mods, $man, $sup] = $map[$who];
    return new NodeContext($uid, $grp, $aid, $mods, $man, $sup, '127.0.0.1', $lang);
}

# A fresh reader for one visitor on the disposable database
function getProbeQuery(string $who, string $lang = ''): NodeQuery {
    return new NodeQuery($GLOBALS['pdb'], getProbeContext($who, $lang), $GLOBALS['fld']);
}

# Run one call and answer its value, or the code, the class and the message of what it threw
function getProbeCall(Closure $fn): array {
    try {
        return ['ok' => true, 'value' => $fn()];
    } catch (Throwable $err) {
        return ['ok' => false, 'code' => $err->getCode(), 'class' => get_class($err), 'msg' => $err->getMessage()];
    }
}

# Run one call and answer its value together with the statements it cost on the reader database
function getProbeCost(Closure $fn): array {
    $num = $GLOBALS['pdb']->qnum;
    $val = $fn();
    return [$val, $GLOBALS['pdb']->qnum - $num];
}

# The ids of a list of materials or targets
function getProbeIds(array $list): array {
    return array_values(array_map(fn($v) => $v->id, $list));
}

# Every page of the selection of a reader, ten materials a page, with the count the reader gives for the same selection
function getProbeAll(NodeQuery $query): array {
    $ids = [];
    for ($page = 1; $page < 50; $page++) {
        $list = $query->setNodePage($page, 10)->getNodeList();
        $ids = array_merge($ids, getProbeIds($list));
        if (count($list) < 10) break;
    }
    return ['ids' => $ids, 'count' => $query->getNodeCount()];
}

# One material as plain data: the scalars, the enums by name and every set with the class of its items
function getProbeNode(?Node $node): ?array {
    if ($node === null) return null;
    $out = get_object_vars($node);
    $out['comon'] = $node->comon->name;
    $out['status'] = $node->status->name;
    foreach (['rels', 'assets'] as $key) {
        if ($node->$key !== null) $out[$key] = array_map(fn($v) => ['class' => get_class($v)] + get_object_vars($v), $node->$key);
    }
    return $out;
}

# One type as plain data
function getProbeType(?NodeType $type): ?array {
    return $type === null ? null : get_object_vars($type);
}

# The lines the scratch logs hold about Node
function getProbeLog(): array {
    $out = [];
    foreach (glob(LOGS_DIR.'/*') ?: [] as $file) {
        foreach (file($file) ?: [] as $line) if (str_contains($line, 'Node: ')) $out[] = trim($line);
    }
    return $out;
}

# Types: the assembly from both sources, the memory of the instance, the public and the administrative view, one statement for the list, a global configuration left alone
function getProbeTypes(): array {
    $hash = sha1(serialize($GLOBALS['conf']));
    $query = getProbeQuery('guest');
    $out = ['guest' => []];
    foreach (['news', 'docs', 'files', 'off', 'bad', 'ghost', 'probe', 'plain', 'bare', 'Bad-Name', 'nosuch'] as $name) {
        [$type, $num] = getProbeCost(fn() => $query->getNodeType($name));
        $out['guest'][$name] = ['found' => $type !== null, 'sql' => $num];
    }
    [$again, $num] = getProbeCost(fn() => $query->getNodeType('news'));
    $out['same'] = $again === $query->getNodeType('news');
    $out['againsql'] = $num;
    $out['news'] = getProbeType($again);
    $out['files'] = getProbeType($query->getNodeType('files'));
    $out['probe'] = getProbeType($query->getNodeType('probe'));
    $out['bare'] = getProbeType($query->getNodeType('bare'));
    $out['lists'] = [];
    foreach (['guest', 'moder', 'boss', 'root'] as $who) {
        $query = getProbeQuery($who);
        [$list, $num] = getProbeCost(fn() => $query->getNodeTypeList());
        [$off, $after] = getProbeCost(fn() => [$query->getNodeType('off'), $query->getNodeType('nosuch'), $query->getNodeType('news')]);
        $out['lists'][$who] = ['names' => array_map(fn($v) => $v->name, $list), 'sql' => $num, 'off' => $off[0] !== null, 'after' => $after, 'same' => $off[2] === $list[0]];
    }
    $out['log'] = getProbeLog();
    $out['conf'] = $hash === sha1(serialize($GLOBALS['conf']));
    return $out;
}

# A version that differs: one re-read of the shared configuration resolves a type another process has just changed, a lasting difference fails the type
function getProbeStale(PDO $pdo): array {
    $pre = PREFIX_DB.'_';
    $hash = sha1(serialize($GLOBALS['conf']));
    $pdo->exec('INSERT INTO '.$pre.'node_types (id, name, title, intro, active, sort, version) VALUES'
        .' (8, \'stale\', \'Stale\', \'\', 1, 80, 2), (9, \'drift\', \'Drift\', \'\', 1, 90, 5)');
    setProbeFile(CONFIG_DIR.'/node.php', getProbeNodeConf(2));
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    $query = getProbeQuery('guest');
    [$stale, $one] = getProbeCost(fn() => $query->getNodeType('stale'));
    [$drift, $two] = getProbeCost(fn() => $query->getNodeType('drift'));
    $out = ['stale' => $stale?->version, 'stalesql' => $one, 'drift' => $drift !== null, 'driftsql' => $two, 'conf' => $hash === sha1(serialize($GLOBALS['conf'])),
        'global' => $GLOBALS['conf']['node']['types']['stale']['version'], 'log' => getProbeLog()];
    $pdo->exec('DELETE FROM '.$pre.'node_types WHERE id IN (8, 9)');
    return $out;
}

# The settings validator: the stored sections of every probe type pass, and each broken variant fails with its own path
function getProbeSettings(): array {
    $query = getProbeQuery('boss');
    $types = $GLOBALS['conf']['node']['types'];
    $strip = fn(array $v): array => array_diff_key($v, ['version' => true]);
    $news = $strip($types['news']);
    $fields = $GLOBALS['fld']->filterFieldList(getProbeFields());
    $role = getProbeRole('_LINK', 'link', ['file'], 1, 1, true, true, 30);
    $out = ['good' => [], 'bad' => []];
    foreach (['news' => ['', []], 'docs' => ['', []], 'files' => ['', $fields], 'plain' => ['', []], 'probe' => ['probe', []]] as $name => [$ext, $defs]) {
        $out['good'][$name] = getProbeCall(fn() => $query->filterNodeSettings($ext, $strip($types[$name]), $defs));
    }
    $set = fn(array $path, mixed $val): array => getProbeSet($news, $path, $val);
    $cases = [
        'section' => [['colour' => []] + $news, ''],
        'listlimit' => [$set(['list', 'limit'], 0), ''],
        'listmax' => [$set(['list', 'limit'], 101), ''],
        'order' => [getProbeSet($set(['list', 'orders'], ['published']), ['list', 'order'], 'views'), ''],
        'orders' => [$set(['list', 'orders'], ['published', 'published']), ''],
        'sortkey' => [$set(['list', 'orders'], ['published', 'new']), ''],
        'dir' => [$set(['list', 'dir'], 'up'), ''],
        'alpha' => [$set(['list', 'alpha'], 'yes'), ''],
        'show' => [$set(['list', 'show'], ['colour']), ''],
        'listkey' => [$set(['list', 'page'], 1), ''],
        'view' => [$set(['view', 'mode'], '../x'), ''],
        'form' => [$set(['form'], ['x' => 1]), ''],
        'features' => [['features' => array_diff_key($news['features'], ['tree' => true])] + $news, ''],
        'feature' => [$set(['features', 'comments'], '1'), ''],
        'integr' => [$set(['integrations', 'mail'], true), ''],
        'seo' => [$set(['integrations', 'seo'], 'blog'), ''],
        'access' => [$set(['workflow', 'access'], 'guest'), ''],
        'groups' => [$set(['workflow', 'access'], 'group'), ''],
        'publish' => [$set(['workflow'], ['access' => 'group', 'groups' => [1], 'publish' => [2]]), ''],
        'notify' => [$set(['workflow', 'notify', 'pending'], 'yes'), ''],
        'assetmode' => [$set(['assets', 'cover', 'mode'], 'banner'), ''],
        'assetkinds' => [$set(['assets', 'cover', 'kinds'], ['audio']), ''],
        'assetmax' => [$set(['assets', 'cover', 'max'], 101), ''],
        'assetmin' => [$set(['assets', 'cover', 'min'], 2), ''],
        'assetkey' => [$set(['assets', 'cover', 'colour'], 'red'), ''],
        'assetstr' => [$set(['assets', 'cover', 'active'], 'true'), ''],
        'assettitle' => [$set(['assets', 'cover', 'title'], '<b>x</b>'), ''],
        'rolename' => [$set(['assets', 'Cover'], $news['assets']['cover']), ''],
        'link' => [$set(['assets', 'link'], ['max' => 2] + $role), ''],
        'twolinks' => [getProbeSet($set(['assets', 'link'], $role), ['assets', 'site'], $role), ''],
        'linklocal' => [$set(['assets', 'link'], ['extensions' => ['pdf']] + $role), ''],
        'extstd' => [$set(['ext'], ['own' => true]), ''],
        'extconf' => [getProbeSet($strip($types['probe']), ['ext', 'own'], 'yes'), 'probe'],
        'extunknown' => [$news, 'support'],
    ];
    foreach ($cases as $name => [$data, $ext]) $out['bad'][$name] = getProbeCall(fn() => $query->filterNodeSettings($ext, $data, []));
    $out['bad']['reserved'] = getProbeCall(fn() => $query->filterNodeSettings('', $news, ['version' => $fields['release']]));
    $out['bad']['nonode'] = getProbeCall(function () use ($query, $news) {
        $keep = $GLOBALS['conf']['node'];
        unset($GLOBALS['conf']['node']);
        try {
            return $query->filterNodeSettings('', $news, []);
        } finally {
            $GLOBALS['conf']['node'] = $keep;
        }
    });
    $out['link'] = getProbeCall(fn() => $query->filterNodeSettings('', $set(['assets', 'link'], $role), []));
    return $out;
}

# Set one value deep inside a stored section
function getProbeSet(array $data, array $path, mixed $val): array {
    $key = array_shift($path);
    $data[$key] = $path ? getProbeSet(is_array($data[$key] ?? null) ? $data[$key] : [], $path, $val) : $val;
    return $data;
}

# Category rights of one type for every kind of visitor: the same materials in the list and the count, and one prefetch per type and instance
function getProbeRights(): array {
    $out = [];
    foreach (['guest', 'clara', 'anna', 'boris', 'moder', 'boss', 'root'] as $who) {
        $query = getProbeQuery($who);
        $all = getProbeAll($query->setNodeType($query->getNodeType('news')));
        sort($all['ids']);
        $query = getProbeQuery($who);
        $query->setNodeType($query->getNodeType('news'));
        [, $first] = getProbeCost(fn() => $query->getNodeCount());
        [, $second] = getProbeCost(fn() => $query->getNodeCount());
        $out[$who] = $all + ['first' => $first, 'second' => $second];
    }
    return $out;
}

# The category filter: main and extra categories without doubles, the right of the filtered category and of the main category of each material
function getProbeCategory(): array {
    $out = [];
    foreach ([['guest', 1], ['clara', 1], ['guest', 2], ['anna', 2], ['guest', 5], ['guest', 8], ['moder', 4], ['guest', 999]] as [$who, $cid]) {
        $query = getProbeQuery($who);
        $out[$who.'-'.$cid] = getProbeAll($query->setNodeType($query->getNodeType('news'))->setNodeCategory($cid));
    }
    $query = getProbeQuery('guest');
    $out['plain'] = getProbeCall(fn() => $query->setNodeType($query->getNodeType('plain'))->setNodeCategory(1)->getNodeList());
    $out['zero'] = getProbeCall(fn() => $query->setNodeCategory(0));
    return $out;
}

# The language of the categories narrows the lists alone: direct reads, targets and sitemap rows of another language stay reachable
function getProbeLanguage(): array {
    $out = [];
    foreach (['ru', 'en'] as $lang) {
        $query = getProbeQuery('guest', $lang);
        $news = $query->getNodeType('news');
        $out[$lang] = getProbeAll($query->setNodeType($news));
        $out[$lang.'cat'] = getProbeAll(getProbeQuery('guest', $lang)->setNodeType($news)->setNodeCategory(5));
        $out[$lang.'deadline'] = getProbeQuery('guest', $lang)->setNodeType($news)->getNodeDeadline();
        $out[$lang.'item'] = [$query->getNode(112, $news) !== null, $query->getNode(113, $news) !== null];
        $out[$lang.'target'] = array_keys($query->getNodeTargetList([112 => 'news', 113 => 'news']));
        $out[$lang.'site'] = array_column($query->getNodeSitemap(0, 500), 'id');
    }
    $query = getProbeQuery('moder', 'ru');
    $out['moder'] = getProbeAll($query->setNodeType($query->getNodeType('news')));
    return $out;
}

# Single reads: the same null for a missing and a closed material, every state for a moderator, the typed sets of the full read and the short read of the text
function getProbeItems(): array {
    $out = ['found' => []];
    foreach (['guest', 'clara', 'moder', 'root'] as $who) {
        $query = getProbeQuery($who);
        $news = $query->getNodeType('news');
        $out['found'][$who] = array_values(array_filter(range(101, 121), fn($v) => $query->getNode($v, $news) !== null));
        $out['content'][$who] = array_values(array_filter(range(101, 121), fn($v) => $query->getNodeContent($v, $news) !== null));
    }
    $query = getProbeQuery('guest');
    $news = $query->getNodeType('news');
    $files = $query->getNodeType('files');
    $out['guest101'] = getProbeNode($query->getNode(101, $news));
    $out['guest102'] = getProbeNode($query->getNode(102, $news));
    $out['guest121'] = getProbeNode($query->getNode(121, $news));
    $out['content101'] = getProbeNode($query->getNodeContent(101, $news));
    $out['files301'] = getProbeNode($query->getNode(301, $files));
    $out['files303'] = getProbeNode($query->getNode(303, $files))['fields'] ?? 'missing';
    $out['foreign'] = [$query->getNode(201, $news) !== null, $query->getNode(101, $files) !== null, $query->getNode(999, $news) !== null];
    [$zero, $num] = getProbeCost(fn() => $query->getNode(0, $news));
    $out['zero'] = [$zero !== null, $num];
    $query = getProbeQuery('moder');
    $out['moder101'] = getProbeNode($query->getNode(101, $query->getNodeType('news')));
    $root = getProbeQuery('root');
    $off = $root->getNodeType('off');
    $out['off'] = [getProbeQuery('guest')->getNode(401, $off) !== null, $root->getNode(401, $off) !== null, getProbeQuery('boss')->getNode(401, $off) !== null];
    $query = getProbeQuery('guest');
    [, $out['fullsql']] = getProbeCost(fn() => $query->getNode(101, $query->getNodeType('news')));
    $query = getProbeQuery('guest');
    [, $out['contentsql']] = getProbeCost(fn() => $query->getNodeContent(101, $query->getNodeType('news')));
    return $out;
}

# Resources: the checks of the material, the route type and an active role of the type, in one statement after the type
function getProbeAssets(): array {
    $out = [];
    foreach ([['guest', 1, 'files'], ['guest', 2, 'files'], ['guest', 3, 'files'], ['guest', 4, 'files'], ['guest', 5, 'files'], ['root', 5, 'files'], ['root', 4, 'files'],
        ['guest', 6, 'files'], ['guest', 6, 'news'], ['guest', 99, 'files'], ['guest', 0, 'files']] as [$who, $id, $name]) {
        $query = getProbeQuery($who);
        $asset = $query->getNodeAsset($id, $query->getNodeType($name));
        $out[$who.'-'.$id.'-'.$name] = $asset === null ? null : ['class' => get_class($asset)] + get_object_vars($asset);
    }
    $query = getProbeQuery('guest');
    [, $out['sql']] = getProbeCost(fn() => $query->getNodeAsset(1, $query->getNodeType('files')));
    return $out;
}

# Sorting and paging: pinned first, every allowed key with its ties, the default sort of a type, refused keys and sizes, and pages past the end
function getProbeOrder(): array {
    $row = fn(Node $v): array => ['id' => $v->id, 'pinned' => $v->pinned, 'title' => $v->title, 'views' => $v->views, 'score' => $v->score, 'ratings' => $v->ratings,
        'pubdate' => $v->pubdate, 'updated' => $v->updated];
    $out = [];
    foreach (['' => '', 'title' => 'asc', 'views' => 'desc', 'rating' => 'desc', 'rating ' => 'asc', 'published' => 'asc'] as $key => $dir) {
        $query = getProbeQuery('root');
        $query->setNodeType($query->getNodeType('news'))->setNodePage(1, 10);
        if ($key !== '') $query->setNodeOrder(trim($key), $dir);
        $list = [];
        for ($page = 1; $page < 5; $page++) $list = array_merge($list, array_map($row, $query->setNodePage($page, 10)->getNodeList()));
        $out['news'.($key === '' ? '' : '-'.trim($key).'-'.$dir)] = $list;
    }
    $query = getProbeQuery('guest');
    $out['docs'] = array_map($row, $query->setNodeType($query->getNodeType('docs'))->getNodeList());
    $out['docsrating'] = getProbeCall(fn() => $query->setNodeOrder('rating')->getNodeList());
    $out['newkey'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeOrder('new'));
    $out['updir'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeOrder('title', 'up'));
    $query = getProbeQuery('guest');
    $plain = $query->getNodeType('plain');
    $out['plain2'] = getProbeIds($query->setNodeType($plain)->setNodePage(2, 10)->getNodeList());
    $out['plaindefault'] = getProbeIds(getProbeQuery('guest')->setNodeType($plain)->getNodeList());
    $out['plain4'] = getProbeIds($query->setNodePage(4, 10)->getNodeList());
    $out['plaincount'] = $query->getNodeCount();
    $out['plainsize'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeType($plain)->setNodePage(1, 11)->getNodeList());
    $out['page0'] = getProbeCall(fn() => getProbeQuery('guest')->setNodePage(0, 10));
    $out['size0'] = getProbeCall(fn() => getProbeQuery('guest')->setNodePage(1, 0));
    $out['notype'] = getProbeCall(fn() => getProbeQuery('guest')->getNodeList());
    return $out;
}

# The list filters, each answered with the materials of the list and the count of the same selection
function getProbeFilters(): array {
    $out = [];
    $run = function (string $who, string $name, Closure $fn) use (&$out): void {
        $query = getProbeQuery($who);
        $out[$name] = getProbeCall(function () use ($query, $fn) {
            $fn($query);
            return getProbeAll($query);
        });
    };
    foreach (['a', 'A', '9', 'ä'] as $one) $run('guest', 'letter-'.$one, fn($q) => $q->setNodeType($q->getNodeType('docs'))->setNodeLetter($one));
    $run('guest', 'letter-news', fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeLetter('a'));
    foreach (['%', 'ab', ' '] as $one) $out['letterbad-'.$one] = getProbeCall(fn() => getProbeQuery('guest')->setNodeLetter($one));
    foreach (['100%', '_sale', '%', 'Open', 'OPEN', '50%_off', 'nothing here'] as $one) {
        $run('guest', 'search-'.$one, fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeSearch($one));
    }
    $out['searchlong'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeSearch(str_repeat('я', 256)));
    $out['searchmax'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeSearch(str_repeat('я', 255)) instanceof NodeQuery);
    $run('guest', 'author-3', fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeAuthor(3));
    $out['author0'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeAuthor(0));
    foreach (['guest', 'moder'] as $who) {
        foreach (NodeStatus::cases() as $state) $run($who, 'status-'.$who.'-'.$state->name, fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeStatus($state));
    }
    $ranges = [['2026-01-02 10:00:00', '2026-01-04 10:00:00'], ['2026-01-19 10:00:00', null], [null, '2026-01-02 10:00:00']];
    foreach ($ranges as $i => [$from, $until]) $run('root', 'range-'.$i, fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodePublished($from, $until));
    $bad = [[null, null], ['2026-02-30 10:00:00', null], ['2026-01-04 10:00:00', '2026-01-04 10:00:00'], ['2026-01-05 10:00:00', '2026-01-04 10:00:00'], ['2026-01-04', null],
        [null, '2026-01-04 24:00:00']];
    foreach ($bad as $i => [$from, $until]) $out['rangebad-'.$i] = getProbeCall(fn() => getProbeQuery('root')->setNodePublished($from, $until));
    $run('guest', 'home', fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeHome());
    $run('guest', 'homeoff', fn($q) => $q->setNodeType($q->getNodeType('news'))->setNodeHome()->setNodeHome(false));
    $run('guest', 'home-docs', fn($q) => $q->setNodeType($q->getNodeType('docs'))->setNodeHome());
    return $out;
}

# Mixed selections and extensions: one union of branches, a factory extension for each type, the assigned instance of one type checked against its key
function getProbeMixed(): array {
    $out = [];
    $query = getProbeQuery('guest');
    $news = $query->getNodeType('news');
    $files = $query->getNodeType('files');
    $mixed = $query->setNodeTypes([$news, $files]);
    $out['mixed'] = getProbeAll($mixed);
    $out['mixedrows'] = array_map(fn($v) => [$v->id, $v->pinned, $v->pubdate], getProbeQuery('guest')->setNodeTypes([$news, $files])->setNodePage(1, 10)->getNodeList());
    $out['mixedtitle'] = getProbeIds(getProbeQuery('guest')->setNodeTypes([$news, $files])->setNodeOrder('title', 'asc')->setNodePage(1, 10)->getNodeList());
    $out['mixeddocs'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeTypes([$news, $query->getNodeType('docs')])->setNodeOrder('rating')->getNodeList());
    $out['empty'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeTypes([]));
    $out['twice'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeTypes([$news, $news]));
    $out['string'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeTypes([$news, 'files']));
    $off = getProbeQuery('root')->getNodeType('off');
    $out['offguest'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeTypes([$news, $off]));
    $out['offtype'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeType($off));
    $out['offroot'] = getProbeCall(fn() => getProbeIds(getProbeQuery('root')->setNodeType($off)->getNodeList()));
    $probe = $query->getNodeType('probe');
    $ext = new ProbeExtension($GLOBALS['pdb'], getProbeContext('anna'));
    foreach (['anna', 'boris', 'guest'] as $who) {
        $own = new ProbeExtension($GLOBALS['pdb'], getProbeContext($who));
        $out['probe-'.$who] = getProbeCall(fn() => getProbeAll(getProbeQuery($who)->setNodeType($probe)->setNodeExtension($own)));
    }
    $out['probenone'] = getProbeCall(fn() => getProbeQuery('anna')->setNodeType($probe)->getNodeList());
    $other = new ProbeOther($GLOBALS['pdb'], getProbeContext('anna'));
    $out['probeother'] = getProbeCall(fn() => getProbeQuery('anna')->setNodeType($probe)->setNodeExtension($other)->getNodeList());
    $out['newsext'] = getProbeCall(fn() => getProbeQuery('anna')->setNodeType($news)->setNodeExtension($ext)->getNodeList());
    $out['probemix'] = getProbeCall(fn() => getProbeAll(getProbeQuery('anna')->setNodeTypes([$news, $probe])));
    $out['probemixext'] = getProbeCall(fn() => getProbeQuery('anna')->setNodeTypes([$news, $probe])->setNodeExtension($ext)->getNodeList());
    $out['probeitem'] = [getProbeQuery('anna')->setNodeExtension($ext)->getNode(701, $probe) !== null,
        getProbeQuery('boris')->setNodeExtension(new ProbeExtension($GLOBALS['pdb'], getProbeContext('boris')))->getNode(701, $probe) !== null];
    $out['probeitemnone'] = getProbeCall(fn() => getProbeQuery('anna')->getNode(701, $probe));
    $out['probetarget'] = array_keys(getProbeQuery('anna')->getNodeTargetList([701 => 'probe', 702 => 'probe', 101 => 'news']));
    return $out;
}

# Light targets: the input order, the same check as a read, at most two statements for a mixed map, and refused maps
function getProbeTargets(): array {
    $out = [];
    $refs = [120 => 'news', 301 => 'files', 108 => 'news', 201 => 'news', 103 => 'news', 101 => 'news', 999 => 'news', 401 => 'off', 202 => 'docs', 105 => 'news', 106 => 'news'];
    foreach (['guest', 'clara', 'moder', 'root'] as $who) {
        $query = getProbeQuery($who);
        [$list, $num] = getProbeCost(fn() => $query->getNodeTargetList($refs));
        [, $again] = getProbeCost(fn() => $query->getNodeTargetList($refs));
        $out[$who] = ['ids' => array_keys($list), 'sql' => $num, 'again' => $again];
    }
    $query = getProbeQuery('guest');
    $one = $query->getNodeTarget('news', 101);
    $out['one'] = $one === null ? null : ['class' => get_class($one), 'type' => $one->type === $query->getNodeType('news'), 'id' => $one->id, 'uid' => $one->uid,
        'title' => $one->title,
        'comon' => $one->comon->name, 'comnum' => $one->comnum, 'score' => $one->score, 'ratings' => $one->ratings, 'props' => array_keys(get_object_vars($one))];
    $out['shared'] = (function () use ($query): bool {
        $list = array_values($query->getNodeTargetList([101 => 'news', 102 => 'news']));
        return count($list) === 2 && $list[0]->type === $list[1]->type;
    })();
    $out['wrongtype'] = $query->getNodeTarget('docs', 101) !== null;
    [$empty, $num] = getProbeCost(fn() => getProbeQuery('guest')->getNodeTargetList([]));
    $out['empty'] = [$empty, $num];
    $big = array_fill_keys(range(1001, 1500), 'plain');
    $query = getProbeQuery('guest');
    [$list, $num] = getProbeCost(fn() => $query->getNodeTargetList($big));
    $out['big'] = ['count' => count($list), 'sql' => $num];
    foreach (['zero' => [0 => 'news'], 'negative' => [-1 => 'news'], 'key' => ['x' => 'news'], 'upper' => [1 => 'News'], 'value' => [1 => 5],
        'many' => array_fill_keys(range(1, 501), 'plain'),
        'single' => null] as $name => $bad) {
        $out['bad-'.$name] = getProbeCall(fn() => $bad === null ? getProbeQuery('guest')->getNodeTarget('news', 0) : getProbeQuery('guest')->getNodeTargetList($bad));
    }
    return $out;
}

# The tree of one type in batches after a cursor, with the parent only when the context may read it
function getProbeTree(): array {
    $query = getProbeQuery('guest');
    $docs = $query->getNodeType('docs');
    $out = ['all' => $query->setNodeType($docs)->getNodeTree()];
    $out['batch'] = $query->getNodeTree(202, 2);
    $out['root'] = getProbeQuery('root')->setNodeType($docs)->getNodeTree();
    $out['limit'] = getProbeCall(fn() => $query->getNodeTree(0, 501));
    $out['after'] = getProbeCall(fn() => $query->getNodeTree(-1, 10));
    $out['notree'] = getProbeCall(fn() => getProbeQuery('guest')->setNodeType($query->getNodeType('news'))->getNodeTree());
    $out['notype'] = getProbeCall(fn() => getProbeQuery('guest')->getNodeTree());
    $query = getProbeQuery('guest');
    $query->setNodeType($docs);
    [, $out['sql']] = getProbeCost(fn() => $query->getNodeTree());
    return $out;
}

# Sitemap rows of every public type with the integration, walked in batches after the last id until an empty batch
function getProbeSitemap(): array {
    $query = getProbeQuery('guest');
    $out = ['all' => $query->getNodeSitemap(0, 500), 'walk' => []];
    $after = 0;
    for ($i = 0; $i < 50; $i++) {
        $rows = $query->getNodeSitemap($after, 4);
        if (!$rows) break;
        $out['walk'] = array_merge($out['walk'], array_column($rows, 'id'));
        $after = end($rows)['id'];
    }
    $out['limit'] = getProbeCall(fn() => $query->getNodeSitemap(0, 501));
    $out['after'] = getProbeCall(fn() => $query->getNodeSitemap(-1, 10));
    return $out;
}

# The next moment a selection changes by time, against the stored dates read by the database itself, and what it costs
function getProbeDeadline(PDO $pdo): array {
    $pre = PREFIX_DB.'_';
    $sql = 'SELECT LEAST((SELECT UNIX_TIMESTAMP(published) FROM '.$pre.'nodes WHERE id = 106), (SELECT UNIX_TIMESTAMP(expires) FROM '.$pre.'nodes WHERE id = 119))';
    $want = intval($pdo->query($sql)->fetchColumn());
    $out = ['want' => $want];
    foreach (['guest', 'root'] as $who) {
        $query = getProbeQuery($who);
        $news = $query->getNodeType('news');
        $query->setNodeType($news);
        [$out[$who], $out[$who.'sql']] = getProbeCost(fn() => $query->getNodeDeadline());
        [, $out[$who.'again']] = getProbeCost(fn() => $query->getNodeDeadline());
    }
    $query = getProbeQuery('guest');
    $query->setNodeType($query->getNodeType('plain'));
    [$out['plain'], $out['plainsql']] = getProbeCost(fn() => $query->getNodeDeadline());
    $query = getProbeQuery('guest');
    $out['category'] = $query->setNodeType($query->getNodeType('news'))->setNodeCategory(1)->getNodeDeadline();
    $query = getProbeQuery('guest');
    $out['mixed'] = $query->setNodeTypes([$query->getNodeType('news'), $query->getNodeType('plain')])->getNodeDeadline();
    return $out;
}

# The statement budgets of 11-security-performance.md, each measured from a fresh reader including the type read, and the page size never changing the count
function getProbeBudget(): array {
    $out = [];
    $list = function (string $name, int $size): int {
        $query = getProbeQuery('guest');
        [, $num] = getProbeCost(function () use ($query, $name, $size) {
            $query->setNodeType($query->getNodeType($name))->setNodePage(1, $size);
            return [$query->getNodeList(), $query->getNodeCount()];
        });
        return $num;
    };
    $runs = [['plain', 3], ['plain', 10], ['news', 3], ['news', 10], ['docs', 3], ['docs', 10], ['files', 3], ['files', 10]];
    foreach ($runs as [$name, $size]) $out[$name.'-'.$size] = $list($name, $size);
    foreach (['plain', 'news', 'docs', 'files'] as $name) {
        $query = getProbeQuery('guest');
        [, $out['build-'.$name]] = getProbeCost(function () use ($query, $name) {
            $query->setNodeType($query->getNodeType($name))->setNodePage(1, 10);
            return [$query->getNodeList(), $query->getNodeCount(), $query->getNodeDeadline()];
        });
    }
    $query = getProbeQuery('guest');
    [, $out['targets']] = getProbeCost(fn() => $query->getNodeTargetList([101 => 'news', 301 => 'files', 201 => 'docs', 1001 => 'plain']));
    return $out;
}

# Run every scenario of the reads on one disposable database, then ask a child process for the context of each visitor against the same database
function getProbeQueryRuns(): array {
    global $conf;
    foreach (glob(LOGS_DIR.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    addProbeSplitter();
    $pdo = addProbeBase();
    $name = end($GLOBALS['pnames']);
    $out = ['schema' => setProbeRun($pdo, getProbeStatements('table.sql', PREFIX_DB))];
    addProbeRows($pdo);
    $out['reader'] = addProbeReader($GLOBALS['probework'].'/node');
    $GLOBALS['pdb'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $name);
    $out['types'] = getProbeTypes();
    $out['stale'] = getProbeStale($pdo);
    $out['settings'] = getProbeSettings();
    $out['rights'] = getProbeRights();
    $out['category'] = getProbeCategory();
    $out['language'] = getProbeLanguage();
    $out['items'] = getProbeItems();
    $out['assets'] = getProbeAssets();
    $out['order'] = getProbeOrder();
    $out['filters'] = getProbeFilters();
    $out['mixed'] = getProbeMixed();
    $out['targets'] = getProbeTargets();
    $out['tree'] = getProbeTree();
    $out['sitemap'] = getProbeSitemap();
    $out['deadline'] = getProbeDeadline($pdo);
    $out['budget'] = getProbeBudget();
    $out['context'] = [];
    foreach (['guest', 'anna', 'boris', 'dmitri', 'moder', 'boss', 'root', 'pair', 'mono'] as $who) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['probework']).' context '.escapeshellarg($name).' '.escapeshellarg($who);
        $text = (string)shell_exec($cmd.' 2>&1');
        $out['context'][$who] = json_decode($text, true) ?? ['error' => $text];
    }
    $out['log'] = getProbeLog();
    return $out;
}

# Remove one scratch path with everything below it
function deleteProbeTree(string $dir): void {
    if (is_link($dir) || is_file($dir)) {
        unlink($dir);
        return;
    }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteProbeTree($dir.'/'.$one);
    rmdir($dir);
}

# Put the service run on clean scratch: the configuration of the stand with the shipped Node file, and an upload root with the guard of the release
# Three prepared directories: files holds guards alone, pages keeps an old file inside thumb, and Faq differs from a type name only in case
function addProbeScratch(string $work): void {
    foreach (['svc', 'svcbackup', 'svccache', 'svcuploads', 'logs'] as $dir) deleteProbeTree($work.'/'.$dir);
    foreach (['svc', 'logs', 'svcuploads/files/thumb', 'svcuploads/pages/thumb', 'svcuploads/Faq'] as $dir) mkdir($work.'/'.$dir, 0777, true);
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) if (basename($file) !== 'local.php') copy($file, $work.'/svc/'.basename($file));
    $guard = (string)file_get_contents(BASE_DIR.'/uploads/index.html');
    foreach (['svcuploads', 'svcuploads/files', 'svcuploads/files/thumb', 'svcuploads/pages'] as $dir) file_put_contents($work.'/'.$dir.'/index.html', $guard);
    file_put_contents($work.'/svcuploads/pages/thumb/old.txt', 'kept');
}

# Serve the scratch upload root the way a web server carrying the shared nginx rule of docs/node/09 does, and point the site at it, so switching a type on meets a real answer
# The server is the built-in one with tests/Support/web_probe.php as router; it ends with the probe, and the flag file open of the scratch root switches its rule off
function addProbeWeb(): void {
    global $conf;
    $work = $GLOBALS['probework'];
    if (is_file($work.'/open')) unlink($work.'/open');
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    $env = getenv() + ['SLAED_WEB_ROOT' => $work, 'SLAED_WEB_UPLOADS' => UPLOADS_DIR];
    $log = ['file', $work.'/web.log', 'a'];
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, __DIR__.'/web_probe.php'], [1 => $log, 2 => $log], $pipes, $work, $env);
    register_shutdown_function(static function () use ($proc): void {
        proc_terminate($proc);
        proc_close($proc);
    });
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) throw new RuntimeException('The web server of the probe did not start');
    fclose($test);
    $conf['homeurl'] = 'http://127.0.0.1:'.$port;
}

# Fill the disposable database of the service run: two groups, the three administrators and a category an earlier owner of the name media left behind
function addProbeServiceRows(PDO $pdo): void {
    $pre = PREFIX_DB.'_';
    $pdo->exec('INSERT INTO '.$pre.'groups (id, name, intro, points, extra) VALUES (1, \'club\', \'\', 0, 1), (2, \'active\', \'\', 100, 0)');
    $pdo->exec('INSERT INTO '.$pre.'admins (id, name, email, password, super, modules, ip) VALUES'
        .' (1, \'root\', \'root@probe.test\', \'hash-root\', 1, \'\', \'127.0.0.1\'),'
        .' (2, \'moder\', \'moder@probe.test\', \'hash-moder\', 0, \'forum,node-news,node-docs,node-files\', \'127.0.0.1\'),'
        .' (3, \'boss\', \'boss@probe.test\', \'hash-boss\', 0, \'node,shop\', \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, lang) VALUES (1, \'media\', \'Old media\', \'\', \'0|0\', \'\')');
}

# The input of a type with the smallest valid settings, the given keys replaced
function getProbeInput(array $over = []): NodeTypeInput {
    $all = array_replace(['title' => 'Probe', 'intro' => '', 'ext' => '', 'sort' => 10, 'settings' => ['features' => getProbeFeatures([])], 'fields' => [],
        'uploads' => [], 'rating' => []], $over);
    return new NodeTypeInput($all['title'], $all['intro'], $all['ext'], $all['sort'], $all['settings'], $all['fields'], $all['uploads'], $all['rating']);
}

# The input that saves a stored type again, the given keys replaced
function getProbeKeep(NodeType $type, array $over = []): NodeTypeInput {
    return getProbeInput(array_replace(['title' => $type->title, 'intro' => $type->intro, 'ext' => $type->ext, 'sort' => $type->sort, 'settings' => $type->settings,
        'fields' => $type->fields, 'uploads' => $type->uploads, 'rating' => $type->rating], $over));
}

# A fresh writer for one visitor on the disposable database
function getProbeService(string $who): NodeService {
    return new NodeService($GLOBALS['pdb'], getProbeContext($who), $GLOBALS['fld'], $GLOBALS['pnt']);
}

# The stored version of one type row, or zero without the row
function getProbeVersion(string $name): int {
    $res = $GLOBALS['pdb']->getSqlQuery('SELECT version FROM '.PREFIX_DB.'_node_types WHERE name = :name', ['name' => $name]);
    return intval($res ? $res->fetchColumn() : 0);
}

# Every file below a directory as a sorted list of relative paths
function getProbeWalk(string $dir, string $pre = ''): array {
    $out = [];
    foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $one) {
        if ($one === '.' || $one === '..') continue;
        if (is_dir($dir.'/'.$one)) $out = array_merge($out, getProbeWalk($dir.'/'.$one, $pre.$one.'/'));
        else $out[] = $pre.$one;
    }
    sort($out);
    return $out;
}

# The four shared sources, the type rows, the upload tree, the unfinished operation and the cache guards, to prove what an operation left or did not leave
function getProbeState(): array {
    clearstatcache();
    $out = [];
    foreach (['node', 'fields', 'uploads', 'ratings'] as $area) $out[$area] = is_file(CONFIG_DIR.'/'.$area.'.php') ? sha1_file(CONFIG_DIR.'/'.$area.'.php') : '';
    $out['rows'] = $GLOBALS['pdb']->getSqlQuery('SELECT name, active, version FROM '.PREFIX_DB.'_node_types ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $out['tree'] = getProbeWalk(UPLOADS_DIR);
    $out['marker'] = is_file(BACKUP_DIR.'/config/marker.json');
    $out['guards'] = count(glob(CACHE_DIR.'/guards/*.lock') ?: []);
    return $out;
}

# What one type left in the four sources, the database and the file tree
function getProbeTrace(string $name): array {
    $src = [];
    foreach (['node', 'fields', 'uploads', 'ratings'] as $area) {
        $data = is_file(CONFIG_DIR.'/'.$area.'.php') ? require CONFIG_DIR.'/'.$area.'.php' : [];
        $src[$area] = is_array($data[$area] ?? null) ? $data[$area] : [];
    }
    $res = $GLOBALS['pdb']->getSqlQuery('SELECT id, title, intro, ext, active, sort, version FROM '.PREFIX_DB.'_node_types WHERE name = :name', ['name' => $name]);
    $row = $res ? $res->fetch(PDO::FETCH_ASSOC) : false;
    $guard = UPLOADS_DIR.'/'.$name.'/index.html';
    $deny = UPLOADS_DIR.'/'.$name.'/.htaccess';
    $good = is_file($guard) && file_get_contents($guard) === file_get_contents(UPLOADS_DIR.'/index.html') && is_file($deny) && file_get_contents($deny) === 'deny from all';
    return ['row' => $row ?: null, 'node' => $src['node']['types'][$name] ?? null, 'fields' => $src['fields']['node'][$name] ?? null,
        'uploads' => $src['uploads'][$name] ?? null, 'rating' => $src['ratings']['node.'.$name] ?? null, 'all' => $src['uploads']['all'] ?? null,
        'dir' => is_dir(UPLOADS_DIR.'/'.$name), 'guard' => $good,
        'defaults' => $src['node']['defaults'] ?? null];
}

# Rights: a guest, a user and a moderator of a type run no type operation and no export; nothing is read or written for them
function getProbeSvcRights(): array {
    $out = [];
    $before = getProbeState();
    foreach (['guest', 'anna', 'moder'] as $who) {
        $srv = getProbeService($who);
        $out[$who] = [
            'add' => getProbeCall(fn() => $srv->addNodeType('denied', getProbeInput())),
            'update' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeInput(), 1)),
            'status' => getProbeCall(fn() => $srv->updateNodeTypeStatus('news', true, 1)),
            'delete' => getProbeCall(fn() => $srv->deleteNodeType('news', 1)),
            'import' => getProbeCall(fn() => $srv->addNodeTypeImport('{}')),
            'export' => getProbeCall(fn() => getProbeQuery($who)->getNodeTypeExport('news')),
        ];
    }
    $out['same'] = $before === getProbeState();
    return $out;
}

# Names: the grammar, the reserved and system names, a module, a key of a shared area, a directory in another case, a category and a user file of an earlier owner
function getProbeSvcNames(): array {
    $srv = getProbeService('boss');
    $bad = ['', str_repeat('a', 21), '1abc', 'News', 'новости', 'a-b', 'a_b', 'node', 'admin', 'uploads', 'con', 'lpt9', 'account', 'forum', 'all', 'dir', 'faq', 'media',
        'pages'];
    $before = getProbeState();
    $out = ['bad' => [], 'good' => [], 'gone' => [], 'trace' => []];
    foreach ($bad as $name) $out['bad'][$name] = getProbeCall(fn() => $srv->addNodeType($name, getProbeInput()));
    $out['same'] = $before === getProbeState();
    $out['pages'] = getProbeWalk(UPLOADS_DIR.'/pages');
    foreach (['x', 'a'.str_repeat('b', 19)] as $name) {
        $out['good'][$name] = getProbeCall(fn() => $srv->addNodeType($name, getProbeInput())->version);
        $out['gone'][$name] = getProbeCall(fn() => $srv->deleteNodeType($name, 1));
        $out['trace'][$name] = getProbeTrace($name);
    }
    return $out;
}

# Create: news, files over a directory of guards alone and docs as their profiles define them; the stored form, the defaults of both rules, the guard and the cache generation
function getProbeSvcAdd(): array {
    $srv = getProbeService('boss');
    $pro = getProbeProfiles();
    $epoch = Cache::getEpoch();
    $out = ['news' => getProbeCall(fn() => getProbeType($srv->addNodeType('news', getProbeInput(['title' => 'News', 'settings' => $pro['news']]))))];
    $out['epoch'] = Cache::getEpoch() > $epoch;
    $out['trace'] = getProbeTrace('news');
    $out['state'] = getProbeState();
    $out['files'] = getProbeCall(fn() => getProbeType($srv->addNodeType('files', getProbeInput(['title' => '_DOWNLOAD', 'sort' => 30, 'settings' => $pro['files'],
        'fields' => getProbeFields()]))));
    $out['filestrace'] = getProbeTrace('files');
    $out['docs'] = getProbeCall(fn() => getProbeType($srv->addNodeType('docs', getProbeInput(['title' => 'Docs', 'sort' => 20, 'settings' => $pro['docs']]))));
    $out['again'] = getProbeCall(fn() => $srv->addNodeType('news', getProbeInput()));
    $out['read'] = getProbeType(getProbeQuery('boss')->getNodeType('news'));
    $out['public'] = getProbeQuery('guest')->getNodeType('news') === null;
    $out['merged'] = getProbeQuery('boss')->filterNodeSettings('', array_diff_key($out['trace']['node'], ['version' => 0]), []) === ($out['read']['settings'] ?? null);
    return $out;
}

# Update: stale and missing versions, incomplete and broken rules and an unknown extension refuse without a trace; one full update then writes every part at the next version
function getProbeSvcUpdate(): array {
    $srv = getProbeService('boss');
    $type = getProbeQuery('boss')->getNodeType('news');
    $up = $type->uploads;
    $before = getProbeState();
    $out = [
        'stale' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['title' => 'Stale']), 2)),
        'older' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['title' => 'Stale']), 0)),
        'missing' => getProbeCall(fn() => $srv->updateNodeType('nosuch', getProbeKeep($type), 1)),
        'noup' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['uploads' => []]), 1)),
        'norate' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['rating' => []]), 1)),
        'badext' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['uploads' => ['extensions' => 'jpg,exe'] + $up]), 1)),
        'badflag' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['uploads' => ['userupload' => 2] + $up]), 1)),
        'badnum' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['uploads' => ['maxbytes' => '10'] + $up]), 1)),
        'badrate' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['rating' => ['period' => '1.5'] + $type->rating]), 1)),
        'badkey' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['rating' => ['extra' => '1'] + $type->rating]), 1)),
        'unknown' => getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['ext' => 'zzz']), 1)),
    ];
    $out['same'] = $before === getProbeState();
    $set = $type->settings;
    $set['list']['limit'] = 20;
    $rate = ['active' => '1', 'period' => '86400', 'detail' => '0', 'guests' => '0'];
    $over = ['title' => 'News two', 'intro' => 'Plain intro', 'sort' => 5, 'settings' => $set, 'fields' => getProbeFields(), 'uploads' => ['maxfiles' => 3] + $up,
        'rating' => $rate];
    $out['done'] = getProbeCall(fn() => getProbeType($srv->updateNodeType('news', getProbeKeep($type, $over), 1)));
    $out['trace'] = getProbeTrace('news');
    $out['repeat'] = getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($type, ['title' => 'Late']), 1));
    return $out;
}

# Roles and stored resources: a role with resources cannot go, and a role turns into the link mode only over unique external addresses compared byte for byte
function getProbeSvcAssets(PDO $pdo): array {
    $srv = getProbeService('boss');
    $pre = PREFIX_DB.'_';
    $cover = getProbeRole('_COVER', 'image', ['image'], 0, 1, false, false, 10);
    $down = getProbeRole('_DOWNLOAD', 'download', ['file', 'image', 'audio', 'video'], 0, 10, true, true, 20);
    $link = getProbeRole('_DOWNLOAD', 'link', ['file'], 0, 1, true, true, 20);
    $plain = getProbeFeatures([]);
    $type = getProbeService('boss')->addNodeType('lnk', getProbeInput(['settings' => ['features' => $plain, 'assets' => ['cover' => $cover, 'download' => $down]]]));
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, title, intro, body, field, status) VALUES (701, '.$type->id.', \'One\', \'\', \'\', \'\', 0),'
        .' (702, '.$type->id.', \'Two\', \'\', \'\', \'\', 0)');
    $pdo->exec('INSERT INTO '.$pre.'node_assets (id, nid, kind, role, src, name, title, intro) VALUES (1, 701, \'image\', \'cover\', \'a.jpg\', \'a.jpg\', \'\', \'\'),'
        .' (2, 701, \'file\', \'download\', \'manual.pdf\', \'manual.pdf\', \'\', \'\')');
    $set = fn(array $roles): array => ['features' => $plain, 'assets' => $roles];
    $keep = fn(array $roles, int $ver) => getProbeCall(fn() => $srv->updateNodeType('lnk', getProbeKeep(getProbeQuery('boss')->getNodeType('lnk'), ['settings' => $set($roles)]), $ver)->version);
    $out = ['drop' => $keep(['cover' => $cover], 1), 'local' => $keep(['cover' => $cover, 'download' => $link], 1)];
    $pdo->exec('UPDATE '.$pre.'node_assets SET src = \'https://a.test/x\' WHERE id = 2');
    $pdo->exec('INSERT INTO '.$pre.'node_assets (id, nid, kind, role, src, name, title, intro) VALUES (3, 702, \'file\', \'download\', \'https://a.test/x\', \'\', \'\', \'\')');
    $out['twice'] = $keep(['cover' => $cover, 'download' => $link], 1);
    $pdo->exec('UPDATE '.$pre.'node_assets SET src = \'https://a.test/X\' WHERE id = 3');
    $out['case'] = $keep(['cover' => $cover, 'download' => $link], 1);
    $out['empty'] = $keep(['cover' => $cover, 'download' => $link, 'more' => $down], 2);
    $pdo->exec('DELETE FROM '.$pre.'nodes WHERE id IN (701, 702)');
    $out['free'] = $keep(['cover' => $cover], 3);
    $out['gone'] = getProbeCall(fn() => $srv->deleteNodeType('lnk', 4));
    return $out;
}

# State: switching on checks what is stored and needs the directory; a repeat is no change; the extension of an active type or of a type with a material stays
function getProbeSvcStatus(PDO $pdo): array {
    $srv = getProbeService('boss');
    $pre = PREFIX_DB.'_';
    $out = ['on' => getProbeCall(fn() => getProbeType($srv->updateNodeTypeStatus('news', true, 2)))];
    $out['again'] = getProbeCall(fn() => getProbeType($srv->updateNodeTypeStatus('news', true, 3)));
    $out['public'] = getProbeType(getProbeQuery('guest')->getNodeType('news'));
    $news = getProbeQuery('boss')->getNodeType('news');
    $out['extactive'] = getProbeCall(fn() => $srv->updateNodeType('news', getProbeKeep($news, ['ext' => 'zzz']), 3));
    $out['off'] = getProbeCall(fn() => getProbeType($srv->updateNodeTypeStatus('news', false, 3)));
    $docs = getProbeQuery('boss')->getNodeType('docs');
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, title, intro, body, field, status) VALUES (800, '.$docs->id.', \'Draft\', \'\', \'\', \'\', 0)');
    $out['extnode'] = getProbeCall(fn() => $srv->updateNodeType('docs', getProbeKeep($docs, ['ext' => 'zzz']), 1));
    $pdo->exec('DELETE FROM '.$pre.'nodes WHERE id = 800');
    $srv->addNodeType('nodir', getProbeInput());
    deleteProbeTree(UPLOADS_DIR.'/nodir');
    $out['nodir'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('nodir', true, 1));
    $srv->addNodeType('brok', getProbeInput());
    $data = require CONFIG_DIR.'/uploads.php';
    $data['uploads']['brok'] = 'gif|1|2';
    setProbeFile(CONFIG_DIR.'/uploads.php', $data);
    $out['brokup'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('brok', true, 1));
    $data = require CONFIG_DIR.'/ratings.php';
    $data['ratings']['node.brok'] = ['active' => 'yes'];
    setProbeFile(CONFIG_DIR.'/ratings.php', $data);
    $data = require CONFIG_DIR.'/uploads.php';
    $data['uploads']['brok'] = $data['uploads']['all'];
    setProbeFile(CONFIG_DIR.'/uploads.php', $data);
    $out['brokrate'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('brok', true, 1));
    $out['cleanup'] = [getProbeCall(fn() => $srv->deleteNodeType('brok', 1)), getProbeCall(fn() => $srv->deleteNodeType('nodir', 1))];
    $out['rows'] = getProbeState()['rows'];
    return $out;
}

# Protection: a type goes public only when the web server itself refuses its directory; a served guard page, a tampered or linked guard keep it off, a missing guard is written
function getProbeSvcGate(): array {
    $srv = getProbeService('boss');
    $dir = UPLOADS_DIR.'/gate';
    $open = $GLOBALS['probework'].'/open';
    $out = ['add' => getProbeCall(fn() => $srv->addNodeType('gate', getProbeInput())->version)];
    $out['made'] = [is_file($dir.'/index.html'), is_file($dir.'/.htaccess') ? file_get_contents($dir.'/.htaccess') : null];
    unlink($dir.'/.htaccess');
    touch($open);
    $out['open'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', true, 1));
    unlink($open);
    $out['written'] = is_file($dir.'/.htaccess') ? file_get_contents($dir.'/.htaccess') : null;
    file_put_contents($dir.'/.htaccess', 'allow from all');
    $out['tampered'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', true, 1));
    unlink($dir.'/.htaccess');
    $out['index'] = file_put_contents($dir.'/index.html', 'changed') > 0 ? getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', true, 1)) : null;
    file_put_contents($dir.'/index.html', (string)file_get_contents(UPLOADS_DIR.'/index.html'));
    $out['on'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', true, 1)->version);
    touch($open);
    $out['again'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', true, 2)->version);
    unlink($open);
    $out['trace'] = getProbeTrace('gate')['guard'];
    $out['off'] = getProbeCall(fn() => $srv->updateNodeTypeStatus('gate', false, 2)->version);
    $out['delete'] = getProbeCall(fn() => $srv->deleteNodeType('gate', 3));
    $out['kept'] = getProbeWalk($dir);
    return $out;
}

# Input: labels, sort, sections, nested values, fields and groups the stored type could not hold are each refused before anything is written
function getProbeSvcInput(): array {
    $srv = getProbeService('boss');
    $plain = getProbeFeatures([]);
    $role = fn(string $title): array => ['features' => $plain, 'assets' => ['cover' => getProbeRole($title, 'image', ['image'], 0, 1, false, false, 10)]];
    $cases = [
        'roleconst' => ['settings' => $role('_NOPE')],
        'rolehtml' => ['settings' => $role('<b>x</b>')],
        'titleconst' => ['title' => '_NOPE'],
        'titlehtml' => ['title' => '<i>x</i>'],
        'titlelong' => ['title' => str_repeat('a', 101)],
        'titleempty' => ['title' => ''],
        'sort' => ['sort' => -1],
        'section' => ['settings' => ['features' => $plain, 'color' => []]],
        'version' => ['settings' => ['features' => $plain, 'version' => 2]],
        'object' => ['settings' => ['features' => $plain, 'list' => ['show' => [new stdClass()]]]],
        'float' => ['settings' => ['features' => $plain, 'list' => ['limit' => 10.0]]],
        'form' => ['settings' => ['features' => $plain, 'form' => ['x' => true]]],
        'column' => ['fields' => ['version' => getProbeFields()['release']]],
        'field' => ['fields' => ['bad' => ['title' => 'Bad']]],
        'groups' => ['settings' => ['features' => $plain, 'workflow' => ['access' => 'group', 'groups' => [1, 99], 'publish' => []]]],
        'extkey' => ['ext' => 'Bad-Key'],
    ];
    $before = getProbeState();
    $out = [];
    foreach ($cases as $key => $over) $out[$key] = getProbeCall(fn() => $srv->addNodeType('inp', getProbeInput($over)));
    $out['same'] = $before === getProbeState();
    $flow = ['features' => $plain, 'workflow' => ['access' => 'group', 'groups' => [1, 2], 'publish' => [2]]];
    $out['groupok'] = getProbeCall(fn() => getProbeType($srv->addNodeType('grp', getProbeInput(['settings' => $flow]))));
    $out['grptrace'] = getProbeTrace('grp');
    return $out;
}

# Export and import: the exact format, a clone equal to its source but for the name, the name of the file, and every broken file refused without a trace
function getProbeSvcPort(): array {
    $srv = getProbeService('boss');
    $json = getProbeQuery('boss')->getNodeTypeExport('files');
    $data = json_decode($json, true);
    $out = ['keys' => array_keys($data), 'type' => array_keys($data['type']), 'format' => $data['format'], 'version' => $data['version'], 'data' => $data['type']];
    $out['missing'] = getProbeCall(fn() => getProbeQuery('boss')->getNodeTypeExport('nosuch'));
    $out['clone'] = getProbeCall(fn() => getProbeType($srv->addNodeTypeImport($json, 'clone')));
    $again = getProbeQuery('boss')->getNodeTypeExport('clone');
    $out['same'] = str_replace('"name": "clone"', '"name": "files"', $again) === $json;
    $out['file'] = getProbeCall(fn() => getProbeType($srv->addNodeTypeImport(str_replace('"name": "files"', '"name": "copy"', $json))));
    $bent = function (Closure $fn) use ($data): string {
        $one = $data;
        $fn($one);
        return (string)json_encode($one);
    };
    $bad = [
        'big' => '{"format":"slaed.node","pad":"'.str_repeat('a', 1048576).'"}',
        'syntax' => '{"format":',
        'scalar' => '"text"',
        'format' => $bent(function (array &$v): void { $v['format'] = 'other'; }),
        'version' => $bent(function (array &$v): void { $v['version'] = 2; }),
        'extra' => $bent(function (array &$v): void { $v['extra'] = 1; }),
        'typekey' => $bent(function (array &$v): void { $v['type']['id'] = 5; }),
        'typemiss' => $bent(function (array &$v): void { unset($v['type']['rating']); }),
        'taken' => $json,
        'settings' => $bent(function (array &$v): void { $v['type']['settings']['list']['limit'] = 0; }),
        'float' => $bent(function (array &$v): void { $v['type']['uploads']['maxbytes'] = 1.5; }),
        'list' => $bent(function (array &$v): void { $v['type']['settings'] = [1, 2]; }),
        'sort' => $bent(function (array &$v): void { $v['type']['sort'] = '10'; }),
        'role' => $bent(function (array &$v): void { $v['type']['settings']['assets']['cover']['mode'] = 'embed'; }),
        'field' => $bent(function (array &$v): void { $v['type']['fields']['site']['type'] = 'html'; }),
        'ext' => $bent(function (array &$v): void { $v['type']['ext'] = 'zzz'; }),
    ];
    $before = getProbeState();
    foreach ($bad as $key => $text) $out['bad'][$key] = getProbeCall(fn() => $srv->addNodeTypeImport($text, $key === 'taken' ? '' : 'imp'));
    $out['unchanged'] = $before === getProbeState();
    return $out;
}

# Delete: a material in the trash, a category and a user file each keep the type; a clean type leaves every area, its right leaves the administrators, its directory stays
function getProbeSvcDelete(PDO $pdo): array {
    $srv = getProbeService('boss');
    $pre = PREFIX_DB.'_';
    $type = getProbeQuery('boss')->getNodeType('files');
    $ver = $type->version;
    $out = ['stale' => getProbeCall(fn() => $srv->deleteNodeType('files', $ver + 1))];
    $pdo->exec('INSERT INTO '.$pre.'nodes (id, tid, title, intro, body, field, status) VALUES (900, '.$type->id.', \'Trash\', \'\', \'\', \'\', 4)');
    $out['node'] = getProbeCall(fn() => $srv->deleteNodeType('files', $ver));
    $pdo->exec('DELETE FROM '.$pre.'nodes WHERE id = 900');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, pread, lang) VALUES (50, \'files\', \'Cat\', \'\', \'0|0\', \'\')');
    $out['category'] = getProbeCall(fn() => $srv->deleteNodeType('files', $ver));
    $pdo->exec('DELETE FROM '.$pre.'categories WHERE id = 50');
    foreach (['thumb/user.jpg', '.hidden'] as $file) {
        file_put_contents(UPLOADS_DIR.'/files/'.$file, 'x');
        $out['file'][$file] = getProbeCall(fn() => $srv->deleteNodeType('files', $ver));
        unlink(UPLOADS_DIR.'/files/'.$file);
    }
    $out['kept'] = getProbeTrace('files');
    $out['done'] = getProbeCall(fn() => $srv->deleteNodeType('files', $ver));
    $out['trace'] = getProbeTrace('files');
    $out['walk'] = getProbeWalk(UPLOADS_DIR.'/files');
    $out['admins'] = $pdo->query('SELECT id, modules FROM '.$pre.'admins ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $out['reuse'] = getProbeCall(fn() => $srv->addNodeType('files', getProbeInput())->version);
    $out['again'] = getProbeCall(fn() => $srv->deleteNodeType('files', 1));
    return $out;
}

# Run one child of the service run and answer its decoded output
function getProbeChild(string $mode, array $args = []): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['probework']).' '.$mode;
    foreach ($args as $one) $cmd .= ' '.escapeshellarg($one);
    $text = trim((string)shell_exec($cmd.' 2>&1'));
    return json_decode($text, true) ?? ['text' => $text];
}

# A writer dies at its commit, before and after it; the type is held, other writes wait, and the restore follows the database, or refuses when the row fits neither side
function getProbeSvcCrash(PDO $pdo): array {
    $pre = PREFIX_DB.'_';
    $out = [];
    foreach (['before', 'after'] as $when) {
        $ver = getProbeVersion('news');
        $old = sha1_file(CONFIG_DIR.'/node.php');
        $one = ['child' => getProbeChild('crash', [$when]), 'row' => getProbeVersion('news')];
        clearstatcache();
        $one += ['marker' => is_file(BACKUP_DIR.'/config/marker.json'), 'was' => $ver];
        $jour = getConfigJournal();
        $one['jour'] = ['why' => $jour['why'] ?? '', 'types' => $jour['types'] ?? [], 'proof' => $jour['proof'] ?? [], 'phase' => $jour['phase'] ?? ''];
        $one['source'] = sha1_file(CONFIG_DIR.'/node.php') !== $old;
        $one['held'] = getProbeQuery('boss')->getNodeType('news') === null;
        $one['other'] = getProbeQuery('boss')->getNodeType('docs') !== null;
        $one['write'] = getProbeCall(fn() => getProbeService('boss')->updateNodeTypeStatus('docs', true, getProbeVersion('docs')));
        if ($when === 'before') {
            $pdo->exec('UPDATE '.$pre.'node_types SET version = version + 5 WHERE name = \'news\'');
            $one['blocked'] = getProbeChild('restore');
            $pdo->exec('UPDATE '.$pre.'node_types SET version = version - 5 WHERE name = \'news\'');
        }
        $one['restore'] = getProbeChild('restore');
        clearstatcache();
        $one['end'] = ['marker' => is_file(BACKUP_DIR.'/config/marker.json'), 'row' => getProbeVersion('news'), 'old' => sha1_file(CONFIG_DIR.'/node.php') === $old,
            'read' => getProbeType(getProbeQuery('boss')->getNodeType('news'))['title'] ?? null, 'guards' => count(glob(CACHE_DIR.'/guards/*.lock') ?: [])];
        $one['end']['recover'] = Cache::checkWriteGuard();
        $one['end']['left'] = count(glob(CACHE_DIR.'/guards/*.lock') ?: []);
        $out[$when] = $one;
    }
    return $out;
}

# Two processes create the same new name at once; the shared lock lets one through and the other finds the name taken
function getProbeSvcRace(): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['probework']).' race';
    $procs = [];
    for ($i = 0; $i < 2; $i++) $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
    $out = [];
    foreach ($procs as $i => $proc) {
        $text = stream_get_contents($pipes[$i][1]).stream_get_contents($pipes[$i][2]);
        proc_close($proc);
        $out[] = json_decode(trim($text), true) ?? ['text' => $text];
    }
    return ['runs' => $out, 'trace' => getProbeTrace('race')];
}

# Run every scenario of the type writer on one disposable database and scratch sources, with children for the crashes, the restore and the race
function getProbeServiceRuns(): array {
    global $conf;
    addProbeSplitter();
    $pdo = addProbeBase();
    $name = end($GLOBALS['pnames']);
    $out = ['schema' => setProbeRun($pdo, getProbeStatements('table.sql', PREFIX_DB))];
    addProbeServiceRows($pdo);
    $data = require CONFIG_DIR.'/db.php';
    $data['db']['name'] = $name;
    setProbeFile(CONFIG_DIR.'/db.php', $data);
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    $GLOBALS['pdb'] = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $name);
    addProbeWeb();
    $out['rights'] = getProbeSvcRights();
    $out['names'] = getProbeSvcNames();
    $out['add'] = getProbeSvcAdd();
    $out['update'] = getProbeSvcUpdate();
    $out['assets'] = getProbeSvcAssets($pdo);
    $out['status'] = getProbeSvcStatus($pdo);
    $out['input'] = getProbeSvcInput();
    $out['port'] = getProbeSvcPort();
    $out['delete'] = getProbeSvcDelete($pdo);
    $out['crash'] = getProbeSvcCrash($pdo);
    $out['race'] = getProbeSvcRace();
    $out['gate'] = getProbeSvcGate();
    $out['log'] = getProbeLog();
    return $out;
}

# The recording extension of the material run: it keeps every call of the writer in order and fails where it is told to, to prove that both parts roll back together
const PROBEHOOK = <<<'PHPCODE'
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

# The recording extension of the material run
final class ProbeHook implements NodeExtension {
    public static array $log = [];
    public static string $fail = '';
    public function __construct(private Database $db, private NodeContext $ctx) {}
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        return $config;
    }
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        if (array_diff(array_keys($data), ['note'])) throw new NodeException('hook data', NodeException::INVALID);
        self::$log[] = ['filter', $node?->id ?? 0];
        return $data;
    }
    public function getNodeScope(NodeType $type): array {
        return ['join' => '', 'where' => '1 = 1', 'params' => []];
    }
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool {
        self::$log[] = ['check', $action];
        return self::$fail !== 'check';
    }
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action): void {
        self::$log[] = ['action', $action];
    }
    public function addNodeData(Node $node, array $data): void {
        self::$log[] = ['add', $node->id > 0, $data];
        if (self::$fail === 'add') throw new RuntimeException('hook add');
    }
    public function updateNodeData(Node $before, Node $after, ?array $data): void {
        self::$log[] = ['update', $after->version - $before->version, $data];
    }
    public function deleteNodeData(Node $node): void {
        self::$log[] = ['delete', $node->id > 0];
    }
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        return [];
    }
}
PHPCODE;

# A one pixel PNG, the smallest image the file layer measures
const PROBEPNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

# Put copies of the reader and the writer into scratch next to a factory whose closed map names the recording extension, and load both from there
# Both files are copied byte for byte, so the run writes with the shipped code; the factory differs in its map line alone
function addProbeWriter(string $dir): array {
    $real = BASE_DIR.'/core/classes/node';
    if (!is_dir($dir.'/ext')) mkdir($dir.'/ext', 0777, true);
    foreach (['query.php', 'service.php'] as $file) copy($real.'/'.$file, $dir.'/'.$file);
    $code = (string)file_get_contents($real.'/ext/load.php');
    file_put_contents($dir.'/ext/load.php', str_replace('$map = [];', '$map = [\'hook\' => [\'hook.php\', \'ProbeHook\']];', $code));
    file_put_contents($dir.'/ext/hook.php', PROBEHOOK);
    spl_autoload_register(static function (string $name) use ($dir): void {
        if ($name === 'NodeQuery') require_once $dir.'/query.php';
        if ($name === 'NodeService') require_once $dir.'/service.php';
    }, true, true);
    return ['query' => sha1_file($real.'/query.php') === sha1_file($dir.'/query.php'), 'service' => sha1_file($real.'/service.php') === sha1_file($dir.'/service.php')];
}

# Give a connection of the run the time zone the booted connection of a request gets, so every process of the run reads one clock
function setProbeZone(Database $pdb): Database {
    global $conf;
    if ($conf['db']['sync']) $pdb->getSqlQuery("SET LOCAL time_zone = '".date('P')."'");
    return $pdb;
}

# The points of the run: the shipped rules with every Node action worth one point and no period, or with a publication worth nothing
function getProbeMatPoint(bool $zero = false): Point {
    global $conf;
    $cfg = $conf['points'];
    foreach (['publish', 'view', 'download', 'visit', 'report'] as $act) $cfg['actions'][$act] = ['points' => '1', 'period' => '0', 'limit' => '0'];
    if ($zero) $cfg['actions']['publish']['points'] = '0';
    return new Point($GLOBALS['pdb'], $cfg);
}

# Fill the disposable database of the material run: groups, accounts, administrators and two polls
function addProbeMatRows(PDO $pdo): void {
    $pre = PREFIX_DB.'_';
    addProbeServiceRows($pdo);
    $pdo->exec('INSERT INTO '.$pre.'users (id, name, email, password, block, warnings, field, grp, points, ip) VALUES'
        .' (2, \'anna\', \'anna@probe.test\', \'hash-anna\', \'\', \'\', \'\', 1, 0, \'127.0.0.1\'),'
        .' (3, \'boris\', \'boris@probe.test\', \'hash-boris\', \'\', \'\', \'\', 0, 150, \'127.0.0.1\'),'
        .' (4, \'clara\', \'clara@probe.test\', \'hash-clara\', \'\', \'\', \'\', 0, 0, \'127.0.0.1\')');
    $pdo->exec('INSERT INTO '.$pre.'voting (id, modul, title, body, answer, status) VALUES (1, \'voting\', \'Poll\', \'\', \'\', 1), (2, \'voting\', \'Off\', \'\', \'\', 0)');
    $pdo->exec('INSERT INTO '.$pre.'admins (id, name, email, password, super, modules, ip) VALUES (4, \'legacy\', \'legacy@probe.test\', \'hash-legacy\', 0, \'news,forum\', \'127.0.0.1\')');
}

# The categories of the material run, which exist only once their types do, because a new type refuses a name that categories still carry
function addProbeMatCats(PDO $pdo): void {
    $pre = PREFIX_DB.'_';
    $pdo->exec('DELETE FROM '.$pre.'categories');
    $pdo->exec('INSERT INTO '.$pre.'categories (id, modul, title, intro, parent, pread, ppost, lang) VALUES'
        .' (10, \'news\', \'Open\', \'\', 0, \'0|0\', \'0|0\', \'\'), (11, \'news\', \'Closed\', \'\', 0, \'0|0\', \'2|0\', \'\'),'
        .' (12, \'news\', \'Child\', \'\', 10, \'0|0\', \'0|0\', \'\'), (13, \'docs\', \'Docs\', \'\', 0, \'0|0\', \'0|0\', \'\'),'
        .' (14, \'files\', \'Files\', \'\', 0, \'0|0\', \'0|0\', \'\'), (15, \'news\', \'Extra\', \'\', 0, \'0|0\', \'0|0\', \'\'),'
        .' (16, \'news\', \'Spare\', \'\', 0, \'0|0\', \'0|0\', \'\'), (17, \'news\', \'Lone\', \'\', 0, \'0|0\', \'0|0\', \'\'),'
        .' (18, \'news\', \'Lone child\', \'\', 17, \'0|0\', \'0|0\', \'\'), (19, \'news\', \'Free\', \'\', 0, \'0|0\', \'0|0\', \'\'),'
        .' (20, \'forum\', \'Forum\', \'\', 0, \'0|0\', \'0|0\', \'\')');
}

# The field definitions of the files type in the material run: a required release, an address with a default and an inactive field
function getProbeMatFields(): array {
    $text = ['title' => 'Release', 'intro' => '', 'type' => 'text', 'default' => '', 'options' => ['max' => 100], 'req' => true, 'multi' => false, 'active' => true, 'sort' => 10];
    return [
        'release' => $text,
        'site' => ['title' => '_URL', 'intro' => '', 'type' => 'url', 'default' => 'https://example.com/', 'options' => [], 'req' => false, 'multi' => false, 'active' => true,
            'sort' => 20],
        'old' => ['req' => false, 'active' => false, 'title' => 'Old', 'sort' => 30] + $text,
    ];
}

# Create and switch on the five types of the material run and put the files of three visitors into their upload directories
function addProbeMatTypes(): array {
    $srv = getProbeService('boss');
    $pro = getProbeProfiles();
    $flow = ['access' => 'user', 'groups' => [], 'publish' => [2], 'notify' => ['pending' => true, 'result' => true]];
    $link = ['link' => getProbeRole('Link', 'link', ['file'], 0, 1, true, true, 10), 'cover' => getProbeRole('_COVER', 'image', ['image'], 0, 1, false, false, 20)];
    $defs = [
        'news' => getProbeInput(['title' => 'News', 'settings' => $pro['news'] + ['workflow' => $flow]]),
        'docs' => getProbeInput(['title' => 'Docs', 'settings' => $pro['docs']]),
        'files' => getProbeInput(['title' => 'Files', 'settings' => $pro['files'] + ['workflow' => ['access' => 'all'] + $flow], 'fields' => getProbeMatFields()]),
        'links' => getProbeInput(['title' => 'Links', 'settings' => ['features' => getProbeFeatures(['submit', 'moderation']), 'assets' => $link]]),
        'hook' => getProbeInput(['title' => 'Hook', 'ext' => 'hook', 'settings' => ['features' => getProbeFeatures(['related'])]]),
    ];
    $out = [];
    foreach ($defs as $name => $input) $out[$name] = getProbeCall(fn() => $srv->updateNodeTypeStatus($name, true, $srv->addNodeType($name, $input)->version)->version);
    $png = base64_decode(PROBEPNG);
    $pdf = "%PDF-1.4\n%%EOF\n";
    $put = [
        'news' => ['photo-abcdefghij-2.png' => $png, 'photo-bcdefghijk-3.png' => $png, 'legacy.png' => $png, 'notes-cdefghijkl-2.txt' => 'text',
            'doc-abcdefghij-2.pdf' => $pdf, 'thumb/thumb-abcdefghij-2.png' => $png],
        'files' => ['manual-abcdefghij-2.pdf' => $pdf, 'pic-abcdefghij-3.png' => $png, 'mine-abcdefghij-2.png' => $png],
    ];
    foreach ($put as $name => $list) {
        foreach ($list as $file => $body) {
            $path = UPLOADS_DIR.'/'.$name.'/'.$file;
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
            file_put_contents($path, $body);
        }
    }
    return $out;
}

# One context of the material run: the visitors of the reads plus a moderator who is also the site user boris, one with another address, and the background
function getProbeMatContext(string $who): NodeContext {
    return match ($who) {
        'mixed' => new NodeContext(3, [2], 2, ['docs', 'files', 'hook', 'links', 'news'], false, false, '127.0.0.1', ''),
        'far' => new NodeContext(0, [], 2, ['docs', 'news'], false, false, '10.0.0.9', ''),
        'task' => new NodeContext(0, [], 0, [], false, false, '', '', true),
        default => getProbeContext($who),
    };
}

# A writer of the material run for one visitor, with the one-point rules unless other points or none are given, and the extension given
function getProbeWriter(string $who, ?Point $pnt = null, ?NodeExtension $ext = null, bool $bare = false): NodeService {
    return new NodeService($GLOBALS['pdb'], getProbeMatContext($who), $GLOBALS['fld'], $bare ? null : ($pnt ?? $GLOBALS['mpnt']), $ext);
}

# One type of the material run as the main administrator reads it
function getProbeMatType(string $name): NodeType {
    return (new NodeQuery($GLOBALS['pdb'], getProbeMatContext('root'), $GLOBALS['fld']))->getNodeType($name);
}

# One material of the type as the main administrator reads it
function getProbeMatNode(int $id, string $type): ?Node {
    return (new NodeQuery($GLOBALS['pdb'], getProbeMatContext('root'), $GLOBALS['fld']))->getNode($id, getProbeMatType($type));
}

# The input of one material with the given values replaced
function getProbeIn(array $over = []): NodeInput {
    $all = array_replace(['cid' => 0, 'cids' => [], 'aname' => '', 'title' => 'Probe material', 'intro' => 'Intro', 'body' => 'Body', 'fields' => [], 'poll' => 0,
        'home' => false, 'comon' => CommentMode::Disabled, 'pinned' => false, 'pubdate' => null, 'expires' => null, 'rels' => [], 'assets' => [], 'ext' => []], $over);
    return new NodeInput($all['cid'], $all['cids'], $all['aname'], $all['title'], $all['intro'], $all['body'], $all['fields'], $all['poll'], $all['home'], $all['comon'],
        $all['pinned'], $all['pubdate'], $all['expires'], $all['rels'], $all['assets'], $all['ext']);
}

# The input of the same material again: its stored values with the given ones replaced
function getProbeKeepIn(Node $node, array $over = []): NodeInput {
    $rels = array_map(fn($v) => ['rid' => $v->rid, 'type' => $v->type, 'sort' => $v->sort], $node->rels ?? []);
    $assets = array_map(fn($v) => ['id' => $v->id, 'kind' => $v->kind, 'role' => $v->role, 'src' => $v->src, 'name' => $v->name, 'title' => $v->title, 'intro' => $v->intro,
        'sort' => $v->sort], $node->assets ?? []);
    return getProbeIn(array_replace(['cid' => $node->cid, 'cids' => $node->cids ?? [], 'aname' => $node->aname, 'title' => $node->title, 'intro' => $node->intro,
        'body' => (string)$node->body, 'fields' => $node->fields ?? [], 'poll' => $node->poll, 'home' => $node->home, 'comon' => $node->comon, 'pinned' => $node->pinned,
        'pubdate' => $node->pubdate, 'expires' => $node->expires, 'rels' => $rels, 'assets' => $assets], $over));
}

# One resource of an input set
function getProbeAsset(?int $id, string $kind, string $role, string $src, int $sort = 0): array {
    return ['id' => $id, 'kind' => $kind, 'role' => $role, 'src' => $src, 'name' => '', 'title' => '', 'intro' => '', 'sort' => $sort];
}

# One value of the disposable database
function getProbeValue(string $sql, array $pars = []): mixed {
    return $GLOBALS['pdb']->getSqlQuery($sql, $pars)->fetchColumn();
}

# The clock of the database moved by the given number of seconds
function getProbeClock(int $sec = 0): string {
    return (string)getProbeValue('SELECT NOW() + INTERVAL '.$sec.' SECOND');
}

# The rows the writer can leave behind, counted table by table, with the cache guards
function getProbeMatCount(): array {
    $out = [];
    foreach (['nodes', 'node_categories', 'node_relations', 'node_assets', 'node_publish', 'points', 'categories'] as $tab) {
        $out[$tab] = intval(getProbeValue('SELECT COUNT(*) FROM '.PREFIX_DB.'_'.$tab));
    }
    $out['guards'] = count(glob(CACHE_DIR.'/guards/*.lock') ?: []);
    return $out;
}

# The journal rows of one action and source
function getProbePoints(string $act, string $src): array {
    $sql = 'SELECT uid, points, rid FROM '.PREFIX_DB.'_points WHERE action = :act AND source = :src ORDER BY id';
    return $GLOBALS['pdb']->getSqlQuery($sql, ['act' => $act, 'src' => $src])->fetchAll(PDO::FETCH_ASSOC);
}

# The job of one material, or null
function getProbeJob(int $id): ?array {
    $row = $GLOBALS['pdb']->getSqlQuery('SELECT published, due FROM '.PREFIX_DB.'_node_publish WHERE nid = :id', ['id' => $id])->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

# Let the date of a published material and of its job come: both are moved the given number of seconds away from now, into the past by default
function setProbeCome(int $id, int $sec = -60): void {
    $when = getProbeClock($sec);
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_nodes SET published = :pub WHERE id = :id', ['pub' => $when, 'id' => $id]);
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_node_publish SET published = :pub, due = :due WHERE nid = :id', ['pub' => $when, 'due' => $when, 'id' => $id]);
}

# Create: every refused create writes nothing; a draft, a submission, a direct publication and a guest submission store what the contract says
function getProbeMatCreate(): array {
    [$news, $docs, $files] = [getProbeMatType('news'), getProbeMatType('docs'), getProbeMatType('files')];
    $before = getProbeMatCount();
    $add = fn(string $who, NodeType $type, array $over, NodeStatus $st) => getProbeCall(fn() => getProbeWriter($who)->addNode($type, getProbeIn($over), $st)->id);
    $out = ['bad' => [
        'disabled' => $add('root', $news, [], NodeStatus::Disabled),
        'deleted' => $add('root', $news, [], NodeStatus::Deleted),
        'guest' => $add('guest', $news, [], NodeStatus::Pending),
        'direct' => $add('anna', $news, [], NodeStatus::Published),
        'draft' => $add('anna', $news, [], NodeStatus::Draft),
        'home' => $add('anna', $news, ['home' => true], NodeStatus::Pending),
        'pinned' => $add('anna', $news, ['pinned' => true], NodeStatus::Pending),
        'poll' => $add('anna', $news, ['poll' => 1], NodeStatus::Pending),
        'date' => $add('anna', $news, ['pubdate' => '2030-01-01 00:00:00'], NodeStatus::Pending),
        'comon' => $add('root', $docs, ['comon' => CommentMode::Open], NodeStatus::Draft),
        'nopoll' => $add('root', $docs, ['poll' => 1], NodeStatus::Draft),
        'nohome' => $add('root', $docs, ['home' => true], NodeStatus::Draft),
        'expires' => $add('root', $docs, ['expires' => '2030-01-01 00:00:00'], NodeStatus::Draft),
        'offpoll' => $add('root', $news, ['poll' => 2], NodeStatus::Draft),
        'forum' => $add('root', $news, ['cid' => 20], NodeStatus::Draft),
        'othercat' => $add('root', $news, ['cid' => 13], NodeStatus::Draft),
        'closed' => $add('anna', $news, ['cid' => 11], NodeStatus::Pending),
        'missing' => $add('root', $news, ['cids' => [99]], NodeStatus::Draft),
        'twice' => $add('root', $news, ['cids' => [15, 15]], NodeStatus::Draft),
        'task' => getProbeCall(fn() => getProbeWriter('task')->addNode($news, getProbeIn(), NodeStatus::Pending)),
        'blank' => $add('root', $news, ['title' => '  '], NodeStatus::Draft),
        'control' => $add('root', $news, ['title' => "a\x01b"], NodeStatus::Draft),
        'long' => $add('root', $news, ['title' => str_repeat('я', 101)], NodeStatus::Draft),
        'aname' => $add('anna', $news, ['aname' => 'Someone'], NodeStatus::Pending),
        'ext' => $add('root', $news, ['ext' => ['x' => 1]], NodeStatus::Draft),
        'moder' => $add('moder', $files, ['fields' => ['release' => '1']], NodeStatus::Draft),
        'nosubmit' => $add('anna', $docs, [], NodeStatus::Pending),
        'required' => $add('root', $files, [], NodeStatus::Pending),
        'intro' => $add('root', $news, ['intro' => str_repeat('a', 65536)], NodeStatus::Draft),
        'field' => $add('root', $files, ['fields' => ['release' => str_repeat('x', 101)]], NodeStatus::Draft),
        'nopoint' => getProbeCall(fn() => getProbeWriter('root', null, null, true)->addNode($news, getProbeIn(), NodeStatus::Draft)),
    ]];
    $out['same'] = $before === getProbeMatCount();
    $epoch = Cache::getEpoch();
    $out['draft'] = getProbeCall(fn() => getProbeNode(getProbeWriter('root')->addNode($news, getProbeIn(['aname' => 'Editor', 'cid' => 10, 'cids' => [16, 10, 15], 'poll' => 1]),
        NodeStatus::Draft)));
    $out['epoch'] = Cache::getEpoch() > $epoch;
    $out['pending'] = getProbeCall(fn() => getProbeNode(getProbeWriter('anna')->addNode($news, getProbeIn(['cid' => 12]), NodeStatus::Pending)));
    $out['direct'] = getProbeCall(fn() => getProbeNode(getProbeWriter('boris')->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Published)));
    $out['clock'] = getProbeClock();
    $out['award'] = getProbePoints('publish', 'node:'.($out['direct']['value']['id'] ?? 0));
    $out['filedraft'] = getProbeCall(fn() => getProbeNode(getProbeWriter('root')->addNode($files, getProbeIn(), NodeStatus::Draft)));
    $link = [getProbeAsset(null, 'file', 'download', 'https://example.com/g.zip')];
    $out['guestfile'] = getProbeCall(fn() => getProbeNode(getProbeWriter('guest')->addNode($files, getProbeIn(['fields' => ['release' => '1.0', 'gone' => 'x'],
        'assets' => $link]), NodeStatus::Pending)));
    $out['nomin'] = getProbeCall(fn() => getProbeWriter('guest')->addNode($files, getProbeIn(['fields' => ['release' => '1.0']]), NodeStatus::Pending));
    $out['guards'] = getProbeMatCount()['guards'];
    return $out;
}

# Update: the stale, the foreign and the forged are refused without a trace; a moderator replaces every set, keeps the author and the address and the inactive field
function getProbeMatUpdate(): array {
    [$news, $files] = [getProbeMatType('news'), getProbeMatType('files')];
    $root = getProbeWriter('root');
    $one = $root->addNode($news, getProbeIn(['cid' => 10, 'cids' => [15], 'assets' => [getProbeAsset(null, 'image', 'cover', 'photo-abcdefghij-2.png')]]), NodeStatus::Draft);
    $two = $root->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Draft);
    $before = getProbeMatCount();
    $out = ['bad' => [
        'stale' => getProbeCall(fn() => $root->updateNode($one->id, getProbeKeepIn($one, ['title' => 'Stale']), 2)),
        'anna' => getProbeCall(fn() => getProbeWriter('anna')->updateNode($one->id, getProbeKeepIn($one), 1)),
        'boss' => getProbeCall(fn() => getProbeWriter('boss')->updateNode($one->id, getProbeKeepIn($one), 1)),
        'missing' => getProbeCall(fn() => $root->updateNode(999999, getProbeKeepIn($one), 1)),
        'self' => getProbeCall(fn() => $root->updateNode($one->id, getProbeKeepIn($one, ['rels' => [['rid' => $one->id, 'type' => 'related', 'sort' => 0]]]), 1)),
        'foreign' => getProbeCall(fn() => $root->updateNode($two->id, getProbeKeepIn($two, ['assets' => [getProbeAsset($one->assets[0]->id, 'image', 'cover', 'x.png')]]), 1)),
    ]];
    $out['same'] = $before === getProbeMatCount() && getProbeStored($one->id, 'news')['version'] === 1;
    $set = ['cids' => [16], 'rels' => [['rid' => $two->id, 'type' => 'related', 'sort' => 5]], 'assets' => [getProbeAsset($one->assets[0]->id, 'image', 'cover',
        'photo-abcdefghij-2.png', 3), getProbeAsset(null, 'image', 'gallery', 'photo-bcdefghijk-3.png', 1)]];
    $out['full'] = getProbeCall(fn() => getProbeNode($root->updateNode($one->id, getProbeKeepIn($one, $set + ['title' => 'Changed']), 1)));
    $out['cleared'] = getProbeCall(fn() => getProbeNode($root->updateNode($one->id, getProbeIn(['cid' => 10]), 2)));
    $sub = getProbeWriter('anna')->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Pending);
    $out['keep'] = getProbeCall(fn() => getProbeNode(getProbeWriter('far')->updateNode($sub->id, getProbeKeepIn($sub, ['title' => 'Edited']), 1)));
    $fil = $root->addNode($files, getProbeIn(['fields' => ['release' => '1.0']]), NodeStatus::Draft);
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.PREFIX_DB.'_nodes SET field = :field WHERE id = :id', ['field' => '{"gone":"x","old":"keep","release":"1.0"}', 'id' => $fil->id]);
    $out['fields'] = getProbeCall(fn() => getProbeWriter('mixed')->updateNode($fil->id, getProbeIn(['fields' => ['release' => '2.0', 'old' => 'hack', 'site' => '']]), 1)->fields);
    $ver = getProbeStored($two->id, 'news')['version'];
    $out['first'] = getProbeCall(fn() => $root->updateNode($two->id, getProbeIn(['cid' => 10, 'title' => 'First']), $ver)->version);
    $out['second'] = getProbeCall(fn() => $root->updateNode($two->id, getProbeIn(['cid' => 10, 'title' => 'Second']), $ver));
    $out['again'] = getProbeCall(fn() => $root->updateNode($two->id, getProbeIn(['cid' => 10, 'title' => 'Second']), $ver + 1)->version);
    $out['title'] = getProbeStored($two->id, 'news')['title'];
    return $out;
}

# A material as the main administrator reads it, as plain data
function getProbeStored(int $id, string $type): ?array {
    return getProbeNode(getProbeMatNode($id, $type));
}

# Reach one state from a fresh draft of the type through allowed moves and answer the material
function getProbeReach(NodeType $type, NodeStatus $want, array $over = []): Node {
    $srv = getProbeWriter('root');
    $node = $srv->addNode($type, getProbeIn($over), NodeStatus::Draft);
    $path = match ($want) {
        NodeStatus::Draft => [],
        NodeStatus::Pending => [NodeStatus::Pending],
        NodeStatus::Published => [NodeStatus::Published],
        NodeStatus::Disabled => [NodeStatus::Published, NodeStatus::Disabled],
        NodeStatus::Deleted => [NodeStatus::Deleted],
    };
    foreach ($path as $step) $node = $srv->updateNodeStatus($node->id, $step, $node->version);
    return $node;
}

# States: every pair of the matrix, the repeat without a write, readiness of fields and resources, and the statements of a move with and without a job
function getProbeMatStatus(): array {
    [$news, $files] = [getProbeMatType('news'), getProbeMatType('files')];
    $root = getProbeWriter('root');
    $out = ['pairs' => []];
    foreach (NodeStatus::cases() as $from) {
        foreach (NodeStatus::cases() as $to) {
            $node = getProbeReach($news, $from);
            $res = getProbeCall(fn() => $root->updateNodeStatus($node->id, $to, $node->version));
            $out['pairs'][$from->name.'-'.$to->name] = $res['ok'] ? $res['value']->version - $node->version : $res['code'];
        }
    }
    $node = getProbeReach($news, NodeStatus::Pending);
    $num = $GLOBALS['pdb']->qnum;
    $out['repeat'] = $root->updateNodeStatus($node->id, NodeStatus::Pending, $node->version)->version - $node->version;
    $out['repeatsql'] = $GLOBALS['pdb']->qnum - $num;
    $out['stale'] = getProbeCall(fn() => $root->updateNodeStatus($node->id, NodeStatus::Published, $node->version + 1));
    $out['anna'] = getProbeCall(fn() => getProbeWriter('anna')->updateNodeStatus($node->id, NodeStatus::Published, $node->version));
    $bare = $root->addNode($files, getProbeIn(), NodeStatus::Draft);
    $out['required'] = getProbeCall(fn() => $root->updateNodeStatus($bare->id, NodeStatus::Pending, 1));
    $rel = $root->addNode($files, getProbeIn(['fields' => ['release' => '1']]), NodeStatus::Draft);
    $out['minimum'] = getProbeCall(fn() => $root->updateNodeStatus($rel->id, NodeStatus::Published, 1));
    $full = $root->addNode($files, getProbeIn(['fields' => ['release' => '1'], 'assets' => [getProbeAsset(null, 'file', 'download', 'https://example.com/file.zip')]]),
        NodeStatus::Draft);
    $out['ready'] = getProbeCall(fn() => $root->updateNodeStatus($full->id, NodeStatus::Published, 1)->status->name);
    $plain = $root->addNode($news, getProbeIn(), NodeStatus::Draft);
    $num = $GLOBALS['pdb']->qnum;
    $done = getProbeWriter('root')->updateNodeStatus($plain->id, NodeStatus::Published, 1);
    $out['budget'] = $GLOBALS['pdb']->qnum - $num;
    $out['published'] = $done->pubdate !== null && strcmp($done->pubdate, getProbeClock()) <= 0;
    $late = $root->addNode($news, getProbeIn(['pubdate' => getProbeClock(3600)]), NodeStatus::Draft);
    $num = $GLOBALS['pdb']->qnum;
    getProbeWriter('root')->updateNodeStatus($late->id, NodeStatus::Published, 1);
    $out['budgetjob'] = $GLOBALS['pdb']->qnum - $num;
    $out['job'] = getProbeJob($late->id) !== null;
    $gone = getProbeReach($news, NodeStatus::Deleted);
    $out['restore'] = $root->updateNodeStatus($gone->id, NodeStatus::Disabled, $gone->version)->status->name;
    $out['guards'] = getProbeMatCount()['guards'];
    return $out;
}

# Delete: the stale and the foreign are refused; the physical delete takes every set, compensates the publication once and leaves the files and a child as a root
function getProbeMatDelete(): array {
    [$news, $docs] = [getProbeMatType('news'), getProbeMatType('docs')];
    $pub = getProbeWriter('boris')->addNode($news, getProbeIn(['cid' => 10, 'cids' => [15]]), NodeStatus::Published);
    getProbeWriter('moder')->updateNode($pub->id, getProbeKeepIn($pub, ['assets' => [getProbeAsset(null, 'image', 'cover', 'photo-bcdefghijk-3.png')]]), 1);
    $out = ['bad' => [
        'stale' => getProbeCall(fn() => getProbeWriter('moder')->deleteNode($pub->id, 1)),
        'anna' => getProbeCall(fn() => getProbeWriter('anna')->deleteNode($pub->id, 2)),
        'nopoint' => getProbeCall(fn() => getProbeWriter('moder', null, null, true)->deleteNode($pub->id, 2)),
    ]];
    $bal = intval(getProbeValue('SELECT points FROM '.PREFIX_DB.'_users WHERE id = 3'));
    $num = $GLOBALS['pdb']->qnum;
    $out['done'] = getProbeCall(fn() => getProbeWriter('moder')->deleteNode($pub->id, 2));
    $out['sql'] = $GLOBALS['pdb']->qnum - $num;
    $out['balance'] = intval(getProbeValue('SELECT points FROM '.PREFIX_DB.'_users WHERE id = 3')) - $bal;
    $out['origin'] = getProbePoints('publish', 'node:'.$pub->id);
    $oid = intval(getProbeValue('SELECT id FROM '.PREFIX_DB.'_points WHERE action = \'publish\' AND source = :src', ['src' => 'node:'.$pub->id]));
    $out['oid'] = $oid;
    $out['reverse'] = getProbePoints('publish', 'reverse:'.$oid);
    $out['rows'] = [];
    foreach (['nodes' => 'id', 'node_assets' => 'nid', 'node_categories' => 'nid'] as $tab => $col) {
        $out['rows'][$tab] = intval(getProbeValue('SELECT COUNT(*) FROM '.PREFIX_DB.'_'.$tab.' WHERE '.$col.' = :id', ['id' => $pub->id]));
    }
    $out['file'] = is_file(UPLOADS_DIR.'/news/photo-bcdefghijk-3.png');
    $anon = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Draft);
    $num = $GLOBALS['pdb']->qnum;
    getProbeWriter('moder')->deleteNode($anon->id, 1);
    $out['sqlnode'] = $GLOBALS['pdb']->qnum - $num;
    $top = getProbeWriter('moder')->addNode($docs, getProbeIn(['cid' => 13]), NodeStatus::Draft);
    $kid = getProbeWriter('moder')->addNode($docs, getProbeIn(['cid' => 13, 'rels' => [['rid' => $top->id, 'type' => 'parent', 'sort' => 0]]]), NodeStatus::Draft);
    getProbeWriter('moder')->deleteNode($top->id, 1);
    $out['child'] = getProbeStored($kid->id, 'docs')['rels'] ?? null;
    return $out;
}

# Full sets: the limits of categories, relations and resources, the roles, the shapes and the ids of resources, the kinds of relations
function getProbeMatSets(): array {
    [$news, $docs] = [getProbeMatType('news'), getProbeMatType('docs')];
    $root = getProbeWriter('root');
    $one = $root->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Draft);
    $add = fn(NodeType $type, array $over) => getProbeCall(fn() => $root->addNode($type, getProbeIn($over), NodeStatus::Draft));
    $cover = getProbeAsset(null, 'image', 'cover', 'photo-abcdefghij-2.png');
    return [
        'cids' => $add($news, ['cids' => range(1000, 1500)]),
        'rels' => $add($news, ['rels' => array_map(fn($v) => ['rid' => $v, 'type' => 'related', 'sort' => 0], range(1000, 1500))]),
        'assets' => $add($news, ['assets' => array_fill(0, 101, $cover)]),
        'max' => $add($news, ['assets' => [$cover, $cover]]),
        'keys' => $add($news, ['assets' => [$cover + ['mime' => 'image/png']]]),
        'zero' => $add($news, ['assets' => [['id' => 0] + $cover]]),
        'role' => $add($news, ['assets' => [getProbeAsset(null, 'image', 'poster', 'photo-abcdefghij-2.png')]]),
        'kind' => $add($news, ['assets' => [getProbeAsset(null, 'video', 'cover', 'photo-abcdefghij-2.png')]]),
        'relkeys' => $add($news, ['rels' => [['rid' => $one->id, 'type' => 'related']]]),
        'reltype' => $add($news, ['rels' => [['rid' => $one->id, 'type' => 'parent', 'sort' => 0]]]),
        'relkind' => $add($news, ['rels' => [['rid' => $one->id, 'type' => 'next', 'sort' => 0]]]),
        'reltwice' => $add($news, ['rels' => [['rid' => $one->id, 'type' => 'related', 'sort' => 0], ['rid' => $one->id, 'type' => 'related', 'sort' => 1]]]),
        'relother' => $add($docs, ['rels' => [['rid' => $one->id, 'type' => 'related', 'sort' => 0]]]),
        'parents' => $add($docs, ['rels' => [['rid' => 1, 'type' => 'parent', 'sort' => 0], ['rid' => 2, 'type' => 'parent', 'sort' => 0]]]),
        'main' => getProbeCall(fn() => $root->addNode($news, getProbeIn(['cid' => 10, 'cids' => [10, 15]]), NodeStatus::Draft)->cids),
    ];
}

# Run two children of one mode at once and answer what each printed
function getProbeRaceRun(string $mode, array $args): array {
    $procs = [];
    $pipes = [];
    foreach ($args as $i => $list) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($GLOBALS['probework']).' '.$mode;
        foreach ($list as $one) $cmd .= ' '.escapeshellarg((string)$one);
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
    }
    $out = [];
    foreach ($procs as $i => $proc) {
        $text = stream_get_contents($pipes[$i][1]).stream_get_contents($pipes[$i][2]);
        proc_close($proc);
        $out[] = json_decode(trim($text), true) ?? ['text' => $text];
    }
    return $out;
}

# Files: new attachments and local sources belong to the visitor, a moderator binds any file, a link is unique inside its type by the whole address
function getProbeMatFiles(): array {
    [$news, $files, $links] = [getProbeMatType('news'), getProbeMatType('files'), getProbeMatType('links')];
    $tag = fn(string $name) => 'Text [attach='.$name.' align=left title=Photo] end';
    $sub = fn(string $who, array $over) => getProbeCall(fn() => getProbeWriter($who)->addNode($news, getProbeIn(['cid' => 10] + $over), NodeStatus::Pending)->id);
    $dld = [getProbeAsset(null, 'file', 'download', 'https://example.com/g.zip')];
    $out = [
        'own' => $sub('anna', ['body' => $tag('photo-abcdefghij-2.png')]),
        'foreign' => $sub('anna', ['body' => $tag('photo-bcdefghijk-3.png')]),
        'legacy' => $sub('anna', ['intro' => $tag('legacy.png')]),
        'text' => $sub('anna', ['body' => $tag('notes-cdefghijkl-2.txt')]),
        'absent' => $sub('anna', ['body' => $tag('gone-abcdefghij-2.png')]),
        'moder' => $sub('root', ['body' => $tag('photo-bcdefghijk-3.png').$tag('legacy.png')]),
        'guest' => getProbeCall(fn() => getProbeWriter('guest')->addNode($files, getProbeIn(['fields' => ['release' => '1'], 'assets' => $dld,
            'body' => $tag('mine-abcdefghij-2.png')]), NodeStatus::Pending)->id),
        'cover' => getProbeCall(fn() => getProbeNode(getProbeWriter('anna')->addNode($news, getProbeIn(['cid' => 10, 'assets' => [getProbeAsset(null, 'image', 'cover',
            'photo-abcdefghij-2.png')]]), NodeStatus::Pending))['assets'] ?? null),
        'coverforeign' => $sub('anna', ['assets' => [getProbeAsset(null, 'image', 'cover', 'photo-bcdefghijk-3.png')]]),
        'pdfimage' => $sub('anna', ['assets' => [getProbeAsset(null, 'image', 'cover', 'doc-abcdefghij-2.pdf')]]),
        'thumb' => $sub('root', ['assets' => [getProbeAsset(null, 'image', 'cover', 'thumb/thumb-abcdefghij-2.png')]]),
        'escape' => $sub('root', ['assets' => [getProbeAsset(null, 'image', 'cover', '../news/photo-abcdefghij-2.png')]]),
        'nolink' => $sub('root', ['assets' => [getProbeAsset(null, 'image', 'cover', 'https://example.com/a.png')]]),
        'pdffile' => getProbeCall(fn() => getProbeNode(getProbeWriter('root')->addNode($files, getProbeIn(['fields' => ['release' => '1'], 'assets' => [getProbeAsset(null,
            'file', 'download', 'manual-abcdefghij-2.pdf')]]), NodeStatus::Draft))['assets'][0] ?? null),
    ];
    $long = 'https://example.com/'.str_repeat('p', 200);
    $lnk = fn(string $src) => getProbeCall(fn() => getProbeWriter('mixed')->addNode($links, getProbeIn(['assets' => [getProbeAsset(null, 'file', 'link', $src)]]),
        NodeStatus::Draft)->id);
    $out['link'] = [
        'first' => $lnk('https://example.com/a'),
        'twin' => $lnk('https://example.com/a'),
        'case' => $lnk('https://example.com/A'),
        'longa' => $lnk($long.'a'),
        'longb' => $lnk($long.'b'),
        'other' => getProbeCall(fn() => getProbeWriter('mixed')->addNode($files, getProbeIn(['fields' => ['release' => '1'], 'assets' => [getProbeAsset(null, 'file',
            'download', 'https://example.com/a')]]), NodeStatus::Draft)->id),
        'local' => $lnk('photo-abcdefghij-2.png'),
        'script' => $lnk('javascript:alert(1)'),
        'creds' => $lnk('https://user:pw@example.com/x'),
    ];
    $first = getProbeMatNode($out['link']['first']['value'] ?? 0, 'links');
    $out['link']['keep'] = getProbeCall(fn() => getProbeWriter('mixed')->updateNode($first->id, getProbeKeepIn($first, ['title' => 'Kept']), 1)->version);
    $out['race'] = getProbeRaceRun('mlink', [['https://example.com/race'], ['https://example.com/race']]);
    return $out;
}

# The file of an attachment: a stored material grants the names its own text carries to whoever may read it, the preview grants the files of the visitor or of a moderator
# Every refusal answers the same empty string; a crafted key, a name of another material, another type, a closed material and the background context open nothing
function getProbeMatAttach(): array {
    [$news, $files] = [getProbeMatType('news'), getProbeMatType('files')];
    $root = UPLOADS_DIR.'/news';
    copy($root.'/photo-abcdefghij-2.png', $root.'/thumb/photo-abcdefghij-2.png');
    $tag = fn(string $name) => 'Text [attach='.$name.' align=left title=Photo] end';
    $pub = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10, 'body' => $tag('photo-abcdefghij-2.png'), 'intro' => $tag('photo-bcdefghijk-3.png')]),
        NodeStatus::Published)->id;
    $wait = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10, 'body' => $tag('photo-abcdefghij-2.png')]), NodeStatus::Pending)->id;
    $rel = fn(string $path): string => ($path === '') ? '' : substr($path, strlen(str_replace('\\', '/', (string)realpath(UPLOADS_DIR))) + 1);
    $file = fn(string $who, NodeType $type, int $id, string $key, bool $thumb = false): string => $rel(getProbeWriter($who)->getNodeFile($type, $id, $key, $thumb));
    $out = [
        'saved' => $file('guest', $news, $pub, 'photo-abcdefghij-2.png'),
        'intro' => $file('guest', $news, $pub, 'photo-bcdefghijk-3.png'),
        'thumb' => $file('guest', $news, $pub, 'photo-abcdefghij-2.png', true),
        'nothumb' => $file('guest', $news, $pub, 'photo-bcdefghijk-3.png', true),
        'absent' => $file('guest', $news, $pub, 'doc-abcdefghij-2.pdf'),
        'pending' => $file('guest', $news, $wait, 'photo-abcdefghij-2.png'),
        'pendroot' => $file('root', $news, $wait, 'photo-abcdefghij-2.png'),
        'othertype' => $file('guest', $files, $pub, 'photo-abcdefghij-2.png'),
        'task' => $file('task', $news, $pub, 'photo-abcdefghij-2.png'),
        'negative' => $file('guest', $news, -1, 'photo-abcdefghij-2.png'),
    ];
    $keys = ['../news/photo-abcdefghij-2.png', 'thumb/photo-abcdefghij-2.png', 'news/photo-abcdefghij-2.png', 'photo-abcdefghij-2.png%00', "photo-abcdefghij-2.png\0",
        'C:photo-abcdefghij-2.png', 'php://filter/photo-abcdefghij-2.png', 'photo-abcdefghij-2.png::$DATA', 'photo%2Dabcdefghij-2.png', 'photo-abcdefghij-2.PNG.php',
        'legacy.png', '.htaccess', 'index.html', str_repeat('a', 240).'-abcdefghij-2.png', '\\news\\photo-abcdefghij-2.png', 'photo-abcdefghij-2.png '];
    foreach ($keys as $i => $key) $out['attack'][$i] = $file('root', $news, $pub, $key).'|'.$file('root', $news, 0, $key);
    $link = $root.'/link-abcdefghij-2.png';
    $out['symlink'] = symlink(BASE_DIR.'/uploads/index.html', $link) ? $file('root', $news, 0, 'link-abcdefghij-2.png') : 'none';
    if (is_link($link)) unlink($link);
    $out['preview'] = [
        'anna' => $file('anna', $news, 0, 'photo-abcdefghij-2.png'),
        'annathumb' => $file('anna', $news, 0, 'photo-abcdefghij-2.png', true),
        'foreign' => $file('anna', $news, 0, 'photo-bcdefghijk-3.png'),
        'boris' => $file('boris', $news, 0, 'photo-bcdefghijk-3.png'),
        'clara' => $file('clara', $news, 0, 'photo-abcdefghij-2.png'),
        'guest' => $file('guest', $news, 0, 'photo-abcdefghij-2.png'),
        'guestall' => $file('guest', $files, 0, 'mine-abcdefghij-2.png'),
        'moder' => $file('moder', $news, 0, 'photo-bcdefghijk-3.png'),
        'root' => $file('root', $news, 0, 'photo-bcdefghijk-3.png'),
        'far' => $file('far', $news, 0, 'photo-bcdefghijk-3.png'),
        'task' => $file('task', $news, 0, 'photo-abcdefghij-2.png'),
        'text' => $file('anna', $news, 0, 'notes-cdefghijkl-2.txt'),
    ];
    $query = getProbeQuery('guest');
    [, $out['sql']] = getProbeCost(fn() => getProbeWriter('guest')->getNodeFile($query->getNodeType('news'), $pub, 'photo-abcdefghij-2.png', false));
    [, $out['previewsql']] = getProbeCost(fn() => getProbeWriter('anna')->getNodeFile($news, 0, 'photo-abcdefghij-2.png', false));
    return $out;
}

# Four documents with the edges A under B and C under D, created fresh for one tree scenario
function getProbeTreeSet(): array {
    $srv = getProbeWriter('moder');
    $docs = getProbeMatType('docs');
    $ids = [];
    foreach (['b', 'd'] as $key) $ids[$key] = $srv->addNode($docs, getProbeIn(['cid' => 13, 'title' => strtoupper($key)]), NodeStatus::Draft)->id;
    $ids['a'] = $srv->addNode($docs, getProbeIn(['cid' => 13, 'title' => 'A', 'rels' => [['rid' => $ids['b'], 'type' => 'parent', 'sort' => 0]]]), NodeStatus::Draft)->id;
    $ids['c'] = $srv->addNode($docs, getProbeIn(['cid' => 13, 'title' => 'C', 'rels' => [['rid' => $ids['d'], 'type' => 'parent', 'sort' => 0]]]), NodeStatus::Draft)->id;
    return $ids;
}

# The parent of one document as the database holds it
function getProbeParent(int $id): int {
    return intval(getProbeValue('SELECT rid FROM '.PREFIX_DB.'_node_relations WHERE nid = :id AND type = \'parent\'', ['id' => $id]));
}

# Move one document under a new parent as the moderator, the way the tree children do it
function getProbeTreeMove(int $id, int $up): array {
    $node = getProbeMatNode($id, 'docs');
    return getProbeCall(fn() => getProbeWriter('moder')->updateNode($id, getProbeKeepIn($node, ['rels' => [['rid' => $up, 'type' => 'parent', 'sort' => 0]]]),
        $node->version)->version);
}

# Tree: a cycle through a descendant is refused, two concurrent moves that close a cycle together let exactly one through,
# and a move that dies before its commit leaves nothing behind for the next one
function getProbeMatTree(): array {
    $ids = getProbeTreeSet();
    $out = ['cycle' => getProbeTreeMove($ids['b'], $ids['a']), 'move' => getProbeTreeMove($ids['a'], $ids['c'])];
    $out['moved'] = getProbeParent($ids['a']) === $ids['c'];
    $set = getProbeTreeSet();
    $out['race'] = getProbeRaceRun('mtree', [[$set['b'], $set['c']], [$set['d'], $set['a']]]);
    $out['edges'] = [getProbeParent($set['b']) === $set['c'], getProbeParent($set['d']) === $set['a']];
    $set = getProbeTreeSet();
    $out['crash'] = getProbeChild('mcrash', [(string)$set['b'], (string)$set['c']]);
    $out['after'] = getProbeParent($set['b']);
    $out['next'] = getProbeChild('mtree', [(string)$set['d'], (string)$set['a']]);
    $out['nextedge'] = getProbeParent($set['d']) === $set['a'];
    $out['cleared'] = Cache::checkWriteGuard();
    return $out;
}

# Publication: a future date gets its job and no points, the job is delivered once when its date came, moved, cancelled, absorbed or delayed as the contract says
function getProbeMatPublish(): array {
    $news = getProbeMatType('news');
    $pre = PREFIX_DB.'_';
    $srv = getProbeWriter('mixed');
    $run = fn(?Point $pnt = null) => getProbeWriter('task', $pnt)->updateNodePublishList();
    $pts = fn(int $id) => count(getProbePoints('publish', 'node:'.$id));
    $late = fn() => $srv->addNode($news, getProbeIn(['cid' => 10, 'pubdate' => getProbeClock(3600)]), NodeStatus::Published);
    $out = [];
    $one = $late();
    $out['future'] = ['job' => getProbeJob($one->id), 'points' => $pts($one->id), 'pub' => $one->pubdate];
    $out['early'] = $run();
    $out['wait'] = getProbeJob($one->id) !== null;
    $out['views'] = getProbeCall(fn() => getProbeWriter('anna')->updateNodeViews($one->id, $news));
    setProbeCome($one->id);
    $out['due'] = $run();
    $out['once'] = ['job' => getProbeJob($one->id), 'points' => $pts($one->id)];
    $out['again'] = $run();
    $out['still'] = $pts($one->id);
    $two = $late();
    $two = $srv->updateNode($two->id, getProbeKeepIn($two, ['pubdate' => getProbeClock(7200)]), 1);
    $out['moved'] = ['job' => getProbeJob($two->id), 'node' => $two->pubdate, 'points' => $pts($two->id)];
    $two = $srv->updateNode($two->id, getProbeKeepIn($two, ['pubdate' => getProbeClock(-60)]), $two->version);
    $out['past'] = ['job' => getProbeJob($two->id), 'points' => $pts($two->id)];
    $off = $late();
    $dis = $srv->updateNodeStatus($off->id, NodeStatus::Disabled, 1);
    $out['cancel'] = ['job' => getProbeJob($off->id), 'points' => $pts($off->id)];
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'nodes SET published = :pub WHERE id = :id', ['pub' => getProbeClock(-60), 'id' => $off->id]);
    $out['republish'] = [$srv->updateNodeStatus($off->id, NodeStatus::Published, $dis->version)->status->name, $pts($off->id)];
    $gone = $late();
    $srv->deleteNode($gone->id, 1);
    $out['deleted'] = getProbeJob($gone->id);
    $zero = $late();
    setProbeCome($zero->id);
    $out['zero'] = ['run' => $run(getProbeMatPoint(true))['extra'], 'job' => getProbeJob($zero->id), 'points' => $pts($zero->id)];
    $dis = $srv->updateNodeStatus($zero->id, NodeStatus::Disabled, 1);
    $out['zero']['later'] = [$srv->updateNodeStatus($zero->id, NodeStatus::Published, $dis->version)->status->name, $pts($zero->id)];
    $anon = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10, 'pubdate' => getProbeClock(3600)]), NodeStatus::Published);
    $lost = $late();
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'nodes SET uid = 99 WHERE id = :id', ['id' => $lost->id]);
    $gap = $srv->addNode($news, getProbeIn(['cid' => 10, 'pubdate' => getProbeClock(3600), 'expires' => getProbeClock(3700)]), NodeStatus::Published);
    foreach ([$anon, $lost] as $item) setProbeCome($item->id);
    setProbeCome($gap->id, -120);
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'nodes SET expires = :exp WHERE id = :id', ['exp' => getProbeClock(-60), 'id' => $gap->id]);
    $out['edge'] = ['run' => $run()['extra'], 'anon' => getProbeJob($anon->id), 'lost' => getProbeJob($lost->id), 'lostpts' => count(getProbePoints('publish',
        'node:'.$lost->id)), 'gap' => getProbeJob($gap->id), 'gappts' => $pts($gap->id)];
    $shut = $late();
    setProbeCome($shut->id);
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'node_types SET active = 0 WHERE name = \'news\'');
    $out['inactive'] = ['run' => $run()['extra'], 'job' => getProbeJob($shut->id) !== null];
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'node_types SET active = 1 WHERE name = \'news\'');
    $out['active'] = ['run' => $run()['extra'], 'job' => getProbeJob($shut->id), 'points' => $pts($shut->id)];
    $fail = $late();
    setProbeCome($fail->id);
    $GLOBALS['pdb']->getSqlQuery('RENAME TABLE '.$pre.'points TO '.$pre.'points_off');
    $res = $run();
    $job = getProbeJob($fail->id);
    $out['broken'] = ['status' => $res['status'], 'run' => $res['extra'], 'job' => $job !== null, 'later' => $job !== null && strcmp($job['due'], getProbeClock(30)) > 0];
    $GLOBALS['pdb']->getSqlQuery('RENAME TABLE '.$pre.'points_off TO '.$pre.'points');
    $GLOBALS['pdb']->getSqlQuery('UPDATE '.$pre.'node_publish SET due = published WHERE nid = :id', ['id' => $fail->id]);
    $out['healed'] = ['run' => $run()['extra'], 'points' => $pts($fail->id)];
    $para = $late();
    setProbeCome($para->id);
    $due = $GLOBALS['pdb']->getSqlQuery('SELECT p.nid, n.title, n.status, p.due FROM '.$pre.'node_publish AS p INNER JOIN '.$pre.'nodes AS n ON n.id = p.nid WHERE p.due <= NOW()')
        ->fetchAll(PDO::FETCH_ASSOC);
    $out['parallel'] = ['due' => $due, 'para' => $para->id, 'runs' => getProbeRaceRun('mpub', [[], []]), 'points' => $pts($para->id), 'job' => getProbeJob($para->id)];
    foreach (['before', 'after'] as $when) {
        $item = $late();
        setProbeCome($item->id);
        $crash = getProbeChild('mpubcrash', [$when]);
        $mid = ['job' => getProbeJob($item->id) !== null, 'points' => $pts($item->id)];
        $out['crash'][$when] = ['child' => $crash, 'mid' => $mid, 'next' => $run()['extra'], 'points' => $pts($item->id), 'job' => getProbeJob($item->id)];
    }
    $out['denied'] = getProbeCall(fn() => getProbeWriter('root')->updateNodePublishList());
    $out['limit'] = getProbeCall(fn() => getProbeWriter('task')->updateNodePublishList(501));
    $out['sched'] = getProbeChild('msched');
    return $out;
}

# Counters: a view is counted for a readable publication only and rewarded once, comments and ratings are written only inside the transaction of their owner
function getProbeMatCounters(): array {
    $news = getProbeMatType('news');
    $pub = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Published);
    $draft = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Draft);
    $epoch = Cache::getEpoch();
    $srv = getProbeWriter('anna');
    $out = ['view' => [getProbeCall(fn() => $srv->updateNodeViews($pub->id, $news)), getProbeCall(fn() => $srv->updateNodeViews($pub->id, $news))]];
    $out['draft'] = getProbeCall(fn() => $srv->updateNodeViews($draft->id, $news));
    $out['points'] = getProbePoints('view', 'node:'.$pub->id);
    $out['epoch'] = Cache::getEpoch() === $epoch;
    $db = $GLOBALS['pdb'];
    $out['notx'] = getProbeCall(fn() => $srv->updateNodeComments($pub->id, $news, 3));
    $db->setSqlBegin();
    $out['comments'] = getProbeCall(fn() => $srv->updateNodeComments($pub->id, $news, 3));
    $out['negative'] = getProbeCall(fn() => $srv->updateNodeComments($pub->id, $news, -1));
    $out['rating'] = getProbeCall(fn() => $srv->updateNodeRating($pub->id, $news, 7, 2));
    $out['over'] = getProbeCall(fn() => $srv->updateNodeRating($pub->id, $news, 11, 2));
    $out['under'] = getProbeCall(fn() => $srv->updateNodeRating($pub->id, $news, 1, 2));
    $out['none'] = getProbeCall(fn() => $srv->updateNodeRating($pub->id, $news, 1, 0));
    $out['other'] = getProbeCall(fn() => $srv->updateNodeComments($pub->id, getProbeMatType('docs'), 1));
    $db->setSqlCommit();
    $row = getProbeStored($pub->id, 'news');
    $out['after'] = ['views' => $row['views'], 'comnum' => $row['comnum'], 'score' => $row['score'], 'ratings' => $row['ratings'], 'version' => $row['version'],
        'same' => $row['updated'] === $pub->updated];
    return $out;
}

# One resource row as the counters and the report leave it
function getProbeAssetRow(int $id): array {
    $sql = 'SELECT hits, reported IS NOT NULL AS open, ruid, updated FROM '.PREFIX_DB.'_node_assets WHERE id = :id';
    return $GLOBALS['pdb']->getSqlQuery($sql, ['id' => $id])->fetch(PDO::FETCH_ASSOC) ?: [];
}

# Resources: downloads and visits are counted and rewarded by their mode, a report keeps its first author, and only a moderator of the type decides it
function getProbeMatAssetOps(): array {
    [$files, $links, $news] = [getProbeMatType('files'), getProbeMatType('links'), getProbeMatType('news')];
    $node = getProbeWriter('root')->addNode($files, getProbeIn(['fields' => ['release' => '1'], 'assets' => [getProbeAsset(null, 'file', 'download', 'manual-abcdefghij-2.pdf'),
        getProbeAsset(null, 'image', 'cover', 'pic-abcdefghij-3.png')]]), NodeStatus::Published);
    $map = [];
    foreach ($node->assets as $one) $map[$one->role] = $one->id;
    $link = getProbeWriter('root')->addNode($links, getProbeIn(['assets' => [getProbeAsset(null, 'file', 'link', 'https://example.com/visit')]]), NodeStatus::Published);
    $lid = $link->assets[0]->id;
    $before = getProbeAssetRow($map['download']);
    $epoch = Cache::getEpoch();
    $srv = getProbeWriter('anna');
    $out = ['hits' => [getProbeCall(fn() => $srv->updateNodeAssetHits($map['download'], $files)), getProbeCall(fn() => $srv->updateNodeAssetHits($map['download'], $files))]];
    $out['download'] = getProbePoints('download', 'asset:'.$map['download']);
    $out['visit'] = [getProbeCall(fn() => $srv->updateNodeAssetHits($lid, $links)), getProbePoints('visit', 'asset:'.$lid)];
    $out['cover'] = getProbeCall(fn() => $srv->updateNodeAssetHits($map['cover'], $files));
    $out['wrongtype'] = getProbeCall(fn() => $srv->updateNodeAssetHits($map['download'], $news));
    $out['report'] = [getProbeCall(fn() => $srv->updateNodeAssetReport($map['download'], $files)), getProbeCall(fn() => getProbeWriter('boris')->updateNodeAssetReport(
        $map['download'], $files))];
    $out['noreport'] = getProbeCall(fn() => $srv->updateNodeAssetReport($map['cover'], $files));
    $out['guest'] = getProbeCall(fn() => getProbeWriter('guest')->updateNodeAssetReport($lid, $links));
    $out['rows'] = ['download' => getProbeAssetRow($map['download']), 'link' => getProbeAssetRow($lid)];
    $out['sameupd'] = $out['rows']['download']['updated'] === $before['updated'];
    $out['epoch'] = Cache::getEpoch() === $epoch;
    $out['version'] = getProbeStored($node->id, 'files')['version'];
    $out['decide'] = [
        'anna' => getProbeCall(fn() => $srv->deleteNodeAssetReport($map['download'], $files, true)),
        'boss' => getProbeCall(fn() => getProbeWriter('boss')->deleteNodeAssetReport($map['download'], $files, true)),
        'moder' => getProbeCall(fn() => getProbeWriter('moder')->deleteNodeAssetReport($map['download'], $files, true)),
        'useful' => getProbeCall(fn() => getProbeWriter('mixed')->deleteNodeAssetReport($map['download'], $files, true)),
        'again' => getProbeCall(fn() => getProbeWriter('mixed')->deleteNodeAssetReport($map['download'], $files, true)),
        'guest' => getProbeCall(fn() => getProbeWriter('mixed')->deleteNodeAssetReport($lid, $links, true)),
    ];
    $out['cleared'] = ['download' => getProbeAssetRow($map['download']), 'link' => getProbeAssetRow($lid)];
    $sql = 'SELECT uid, points, source FROM '.PREFIX_DB.'_points WHERE action = \'report\'';
    $out['rewards'] = $GLOBALS['pdb']->getSqlQuery($sql)->fetchAll(PDO::FETCH_ASSOC);
    return $out;
}

# One category row as the category form writes it
function getProbeCatRow(int $id): ?array {
    $sql = 'SELECT modul, title, intro, img, lang, parent, status, pview, pread, ppost, preply, pedit, pdelete, pmod FROM '.PREFIX_DB.'_categories WHERE id = :id';
    return $GLOBALS['pdb']->getSqlQuery($sql, ['id' => $id])->fetch(PDO::FETCH_ASSOC) ?: null;
}

# Categories: a used category never leaves its type and is never deleted, an extra link leaves with a new version, and only an administrator of the type writes
function getProbeMatCategories(): array {
    $news = getProbeMatType('news');
    $use = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 16]), NodeStatus::Draft);
    $ext = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10, 'cids' => [17]]), NodeStatus::Draft);
    $srv = getProbeWriter('boss');
    $epoch = Cache::getEpoch();
    $out = [
        'move' => getProbeCall(fn() => $srv->updateNodeCategory(16, ['modul' => 'forum'] + getProbeCatRow(16))),
        'moveextra' => getProbeCall(fn() => $srv->updateNodeCategory(17, ['modul' => 'forum'] + getProbeCatRow(17))),
        'lang' => getProbeCall(fn() => $srv->updateNodeCategory(16, ['lang' => 'german'] + getProbeCatRow(16))),
        'keys' => getProbeCall(fn() => $srv->updateNodeCategory(16, ['extra' => 1] + getProbeCatRow(16))),
        'anna' => getProbeCall(fn() => getProbeWriter('anna')->updateNodeCategory(16, getProbeCatRow(16))),
        'moder' => getProbeCall(fn() => getProbeWriter('moder')->updateNodeCategory(14, getProbeCatRow(14))),
        'forum' => getProbeCall(fn() => $srv->updateNodeCategory(20, getProbeCatRow(20))),
        'deleteused' => getProbeCall(fn() => $srv->deleteNodeCategory(16)),
        'deletemissing' => getProbeCall(fn() => $srv->deleteNodeCategory(9999)),
    ];
    $out['epoch'] = Cache::getEpoch() > $epoch;
    $out['stored'] = [getProbeCatRow(16)['modul'] ?? null, getProbeCatRow(16)['lang'] ?? null];
    $out['delete'] = getProbeCall(fn() => $srv->deleteNodeCategory(17));
    $out['gone'] = [getProbeCatRow(17), getProbeCatRow(18)];
    $row = getProbeStored($ext->id, 'news');
    $out['extnode'] = ['version' => $row['version'], 'cids' => $row['cids']];
    $out['free'] = getProbeCall(fn() => getProbeWriter('moder')->updateNodeCategory(19, ['modul' => 'forum'] + getProbeCatRow(19)));
    $out['moved'] = getProbeCatRow(19)['modul'] ?? null;
    $out['guards'] = getProbeMatCount()['guards'];
    $out['used'] = $use->cid;
    return $out;
}

# Preview: the same refusals as a create and, on success, an unsaved material with metadata, no row, no job, no points and no new cache generation
function getProbeMatPreview(): array {
    $news = getProbeMatType('news');
    $before = getProbeMatCount();
    $epoch = Cache::getEpoch();
    $srv = getProbeWriter('anna');
    $body = 'x [attach=photo-abcdefghij-2.png align=left title=P]';
    $out = [
        'ok' => getProbeCall(fn() => getProbeNode($srv->getNodePreview($news, getProbeIn(['cid' => 10, 'cids' => [15], 'body' => $body, 'assets' => [getProbeAsset(null,
            'image', 'cover', 'photo-abcdefghij-2.png')]]), NodeStatus::Pending))),
        'foreign' => getProbeCall(fn() => $srv->getNodePreview($news, getProbeIn(['cid' => 10, 'body' => str_replace('abcdefghij-2', 'bcdefghijk-3', $body)]),
            NodeStatus::Pending)),
        'direct' => getProbeCall(fn() => $srv->getNodePreview($news, getProbeIn(['cid' => 10]), NodeStatus::Published)),
        'closed' => getProbeCall(fn() => $srv->getNodePreview($news, getProbeIn(['cid' => 11]), NodeStatus::Pending)),
        'task' => getProbeCall(fn() => getProbeWriter('task')->getNodePreview($news, getProbeIn(), NodeStatus::Pending)),
        'nopoint' => getProbeCall(fn() => getProbeWriter('anna', null, null, true)->getNodePreview($news, getProbeIn(['cid' => 10]), NodeStatus::Pending)->id),
    ];
    $out['same'] = $before === getProbeMatCount();
    $out['epoch'] = Cache::getEpoch() === $epoch;
    return $out;
}

# The extension of a type: its data is checked before and written inside the transaction of every write, and its failure takes the whole write back
function getProbeMatHook(): array {
    require_once $GLOBALS['probework'].'/mclass/ext/load.php';
    $hook = getProbeMatType('hook');
    $news = getProbeMatType('news');
    $ext = getNodeExtension('hook', $GLOBALS['pdb'], getProbeMatContext('mixed'));
    $srv = getProbeWriter('mixed', null, $ext);
    ProbeHook::$log = [];
    $out = ['none' => getProbeCall(fn() => getProbeWriter('mixed')->addNode($hook, getProbeIn(), NodeStatus::Draft)),
        'extra' => getProbeCall(fn() => $srv->addNode($news, getProbeIn(['cid' => 10]), NodeStatus::Draft)),
        'baddata' => getProbeCall(fn() => $srv->addNode($hook, getProbeIn(['ext' => ['bad' => 1]]), NodeStatus::Draft))];
    $node = $srv->addNode($hook, getProbeIn(['ext' => ['note' => 'a']]), NodeStatus::Draft);
    $node = $srv->updateNode($node->id, getProbeKeepIn($node, ['ext' => ['note' => 'b']]), 1);
    $node = $srv->updateNodeStatus($node->id, NodeStatus::Published, $node->version);
    $srv->deleteNode($node->id, $node->version);
    $out['log'] = ProbeHook::$log;
    $before = getProbeMatCount();
    ProbeHook::$fail = 'add';
    $out['fail'] = getProbeCall(fn() => $srv->addNode($hook, getProbeIn(['ext' => ['note' => 'c']]), NodeStatus::Draft));
    ProbeHook::$fail = '';
    $out['same'] = $before === getProbeMatCount();
    return $out;
}

# Trusted tags: every author but the main administrator loses them from the texts, the field values and the captions of resources before anything reads them
function getProbeMatTrust(): array {
    [$news, $files] = [getProbeMatType('news'), getProbeMatType('files')];
    $tags = 'A [usephp]echo 1;[/usephp] [UseHtml]<b>x</b>[/UseHtml] [us[usephp]ephp]B';
    $asset = ['intro' => '[usehtml]cap[/usehtml]'] + getProbeAsset(null, 'image', 'cover', 'photo-abcdefghij-2.png');
    $out = [];
    foreach (['anna' => NodeStatus::Pending, 'moder' => NodeStatus::Draft, 'root' => NodeStatus::Draft] as $who => $st) {
        $node = getProbeWriter($who)->addNode($news, getProbeIn(['cid' => 10, 'intro' => $tags, 'body' => $tags, 'assets' => [$asset]]), $st);
        $out[$who] = ['intro' => $node->intro, 'body' => $node->body, 'cap' => $node->assets[0]->intro];
    }
    $pre = getProbeWriter('anna')->getNodePreview($news, getProbeIn(['cid' => 10, 'body' => $tags]), NodeStatus::Pending);
    $out['preview'] = $pre->body;
    $dld = [getProbeAsset(null, 'file', 'download', 'https://example.com/t.zip')];
    $out['field'] = getProbeWriter('guest')->addNode($files, getProbeIn(['fields' => ['release' => '[usephp]1.0[/usephp]'], 'assets' => $dld]), NodeStatus::Pending)->fields;
    return $out;
}

# A switch turned off keeps what the material already carries: an unchanged poll saves again, a new poll is refused, clearing it is allowed
function getProbeMatKeep(): array {
    $news = getProbeMatType('news');
    $node = getProbeWriter('root')->addNode($news, getProbeIn(['cid' => 10, 'poll' => 1]), NodeStatus::Draft);
    $set = $news->settings;
    $set['features']['poll'] = false;
    $out = ['type' => getProbeCall(fn() => getProbeService('boss')->updateNodeType('news', getProbeKeep($news, ['settings' => $set]), $news->version)->version)];
    $node = getProbeMatNode($node->id, 'news');
    $out['same'] = getProbeCall(fn() => getProbeWriter('root')->updateNode($node->id, getProbeKeepIn($node, ['title' => 'Kept poll']), 1)->poll);
    $out['other'] = getProbeCall(fn() => getProbeWriter('root')->updateNode($node->id, getProbeKeepIn($node, ['poll' => 2]), 2));
    $out['clear'] = getProbeCall(fn() => getProbeWriter('root')->updateNode($node->id, getProbeKeepIn($node, ['poll' => 0]), 2)->poll);
    return $out;
}

# Run every scenario of the material writer on one disposable database, scratch sources, cache and upload root, with children for races and crashes
function getProbeMaterialRuns(): array {
    global $conf;
    addProbeSplitter();
    $pdo = addProbeBase();
    $name = end($GLOBALS['pnames']);
    $out = ['schema' => setProbeRun($pdo, getProbeStatements('table.sql', PREFIX_DB))];
    addProbeMatRows($pdo);
    $data = require CONFIG_DIR.'/db.php';
    $data['db']['name'] = $name;
    setProbeFile(CONFIG_DIR.'/db.php', $data);
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    $GLOBALS['pdb'] = setProbeZone(new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $name));
    $GLOBALS['mpnt'] = getProbeMatPoint();
    addProbeWeb();
    $out['copies'] = $GLOBALS['mcopy'];
    $out['types'] = addProbeMatTypes();
    addProbeMatCats($pdo);
    $out['create'] = getProbeMatCreate();
    $out['update'] = getProbeMatUpdate();
    $out['status'] = getProbeMatStatus();
    $out['delete'] = getProbeMatDelete();
    $out['sets'] = getProbeMatSets();
    $out['files'] = getProbeMatFiles();
    $out['tree'] = getProbeMatTree();
    $out['publish'] = getProbeMatPublish();
    $out['counters'] = getProbeMatCounters();
    $out['assetops'] = getProbeMatAssetOps();
    $out['categories'] = getProbeMatCategories();
    $out['preview'] = getProbeMatPreview();
    $out['attach'] = getProbeMatAttach();
    $out['hook'] = getProbeMatHook();
    $out['upload'] = ['moder' => getProbeChild('mupload', ['moder']), 'anna' => getProbeChild('mupload', ['anna']), 'legacy' => getProbeChild('mupload', ['legacy'])];
    $out['trust'] = getProbeMatTrust();
    $out['keep'] = getProbeMatKeep();
    $out['log'] = getProbeLog();
    return $out;
}

# The children of the material run: each one boots on the scratch sources and the disposable database the parent prepared and answers one call as JSON
if ($pmat) {
    $GLOBALS['mcopy'] = addProbeWriter($probework.'/mclass');
    if ($pmode !== 'material') {
        $GLOBALS['pdb'] = $db;
        if (in_array($pmode, ['mcrash', 'mpubcrash'], true)) {
            $GLOBALS['pdb'] = setProbeZone(new ProbeCrashDb($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name']));
            $GLOBALS['pdb']->when = ($pmode === 'mcrash') ? 'before' : (string)($pargs[0] ?? '');
        }
        $GLOBALS['mpnt'] = getProbeMatPoint();
        $answer = match ($pmode) {
            'mtree', 'mcrash' => getProbeTreeMove(intval($pargs[0] ?? 0), intval($pargs[1] ?? 0)),
            'mlink' => getProbeCall(fn() => getProbeWriter('mixed')->addNode(getProbeMatType('links'), getProbeIn(['assets' => [getProbeAsset(null, 'file', 'link',
                (string)($pargs[0] ?? ''))]]), NodeStatus::Draft)->id),
            'mpub', 'mpubcrash' => getProbeCall(fn() => getProbeWriter('task')->updateNodePublishList()),
            'msched' => addSchedulerRun('nodepublish', 'manual'),
            'mupload' => ['moder' => checkUploadModer('news'), 'forum' => checkUploadModer('forum'), 'shop' => checkUploadModer('shop'), 'none' => checkUploadModer(''),
                'owner' => getEditorFileOwner('news'), 'flag' => getUploadFileArea(getUploadPlaceRule('news.attach'))->getCapabilities()['delete']],
        };
        echo json_encode($answer);
        exit;
    }
}

$report = ['error' => '', 'clean' => false, 'runs' => []];

if ($pmode === 'context') {
    echo json_encode(getProbeVisitor());
    exit;
}

if ($pmode === 'crash') {
    $cdb = new ProbeCrashDb($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name']);
    $cdb->when = (string)($pargs[0] ?? '');
    $type = (new NodeQuery($cdb, getProbeContext('boss'), $fld))->getNodeType('news');
    $cin = new NodeTypeInput('Crash '.$cdb->when, $type->intro, $type->ext, $type->sort, $type->settings, $type->fields, $type->uploads, $type->rating);
    echo json_encode(getProbeCall(fn() => (new NodeService($cdb, getProbeContext('boss'), $fld, $pnt))->updateNodeType('news', $cin, $type->version)->version));
    exit;
}

if ($pmode === 'restore') {
    $done = setConfigRestore();
    $jour = getConfigJournal();
    echo json_encode(['done' => $done, 'why' => $jour['why'] ?? '', 'marker' => $jour !== []]);
    exit;
}

if ($pmode === 'race') {
    $GLOBALS['pdb'] = $db;
    echo json_encode(getProbeCall(fn() => getProbeService('boss')->addNodeType('race', getProbeInput())->version));
    exit;
}

try {
    if ($pmode === 'query') {
        $report['runs'] = getProbeQueryRuns();
    } elseif ($pmode === 'service') {
        $report['runs'] = getProbeServiceRuns();
    } elseif ($pmode === 'material') {
        $report['runs'] = getProbeMaterialRuns();
    } else {
        $report['runs']['load'] = getProbeLoad();
        addProbeSplitter();
        $report['runs']['fresh'] = getProbeFresh();
        $report['runs']['update'] = getProbeUpdate();
    }
} catch (Throwable $err) {
    $report['error'] = get_class($err).': '.$err->getMessage().' @'.basename($err->getFile()).':'.$err->getLine();
}

$report['clean'] = deleteProbeBases();

echo json_encode($report);
