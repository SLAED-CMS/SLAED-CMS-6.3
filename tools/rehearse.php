<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Rehearses a built and signed release before tools/release.php publish lets it out; it never touches the site of this tree or its database
# Usage: php tools/rehearse.php v8.0.1 [--keep]
# The full archive is first installed clean, as a new site takes it: the site answers, its integrity is clean, and its schema is the one every update must reach
# Then each archive gets its own run on a fresh site: the full archive of the previous release, installed by its own setup.php into a disposable database
# The archive and its signed manifest go to the Update class of that site the way an upload by hand gives them, so the site verifies the signature itself
# The panel of the site runs the dry run, installs, checks the site, its integrity and its schema, and goes back through "Restore the previous version"
# The first release with an updater has no previous one and ends after its clean run
# A green rehearsal writes storage/release/<version>/rehearsal.json with the SHA-256 of each archive, which publish requires; a red one deletes it
# The previous archive is storage/release/<previous>/slaed-<previous>.zip, or the one of the GitHub release when this machine has none; --keep leaves the scratch folder

if (PHP_SAPI !== 'cli') die('CLI only');

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', (($argv[1] ?? '') === '--stage') ? (string)($argv[2] ?? '') : dirname(__DIR__)));
define('RELEASE_DIR', BASE_DIR.'/storage/release');

# The table prefix of the rehearsed site, the longer prefix of a foreign installation in the same database, the panel file and the password of the administrator
const RPREF = 'rh';
const RFOREIGN = 'rh_x';
const RPANEL = 'admin';
const RPASS = 'Rehearse-secret-8';

# Print the reason to the error stream and leave with a failure code
function setFailExit(string $text): never {
    fwrite(STDERR, 'Refused: '.$text."\n");
    exit(1);
}

# Print one line of the progress
function setNote(string $text): void {
    fwrite(STDOUT, $text."\n");
}

# Remove one scratch tree, read-only files included
function deleteWorkTree(string $path): void {
    if (is_link($path) || is_file($path)) {
        chmod($path, 0644);
        unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteWorkTree($path.'/'.$one);
    rmdir($path);
}

# The database server of this tree, from its config/db.php; the rehearsal creates its own database there and drops it afterwards
function getBaseCred(): array {
    $file = BASE_DIR.'/config/db.php';
    $data = is_file($file) ? (static fn(string $path): mixed => include $path)($file) : null;
    if (!is_array($data['db'] ?? null)) setFailExit('config/db.php of this tree names no database server for the disposable database');
    return $data['db'];
}

# One connection to that server, with a database selected when one is named
function getBaseLink(string $name = ''): PDO {
    $dbc = getBaseCred();
    $dsn = 'mysql:host='.$dbc['host'].(($name === '') ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, (string)$dbc['uname'], (string)$dbc['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create the disposable database again, empty; the database of this tree can never be the one dropped
function setWorkBase(string $name): void {
    $dbc = getBaseCred();
    if ($name === $dbc['name'] || !str_starts_with($name, 'slaed_rehearse_')) setFailExit('the disposable database collides with the database of this tree');
    getBaseLink()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    getBaseLink()->exec('CREATE DATABASE `'.$name.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
}

# Drop the disposable database
function deleteWorkBase(string $name): void {
    try {
        if (str_starts_with($name, 'slaed_rehearse_')) getBaseLink()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    } catch (Throwable) {
        setNote('The disposable database '.$name.' could not be dropped');
    }
}

# One free local port
function getFreePort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    return $port;
}

# Start one built-in server on public/ of the site and wait until it answers
function addWorkServer(string $site, int $port): mixed {
    $log = ['file', dirname($site).'/server-'.$port.'.log', 'a'];
    $cmd = [PHP_BINARY, '-d', 'opcache.revalidate_freq=0', '-S', '127.0.0.1:'.$port, '-t', $site.'/public'];
    $proc = proc_open($cmd, [1 => $log, 2 => $log], $pipes, $site);
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) setFailExit('the built-in server on port '.$port.' did not start');
    fclose($test);
    return $proc;
}

# Stop one built-in server with the processes it started
function deleteWorkServer(mixed $proc): void {
    if (!is_resource($proc)) return;
    $pid = intval(proc_get_status($proc)['pid'] ?? 0);
    if ($pid > 0 && PHP_OS_FAMILY === 'Windows') exec('taskkill /F /T /PID '.$pid.' 2>NUL');
    proc_terminate($proc);
    proc_close($proc);
}

# One exchange with the panel server as the browser of the run, with its cookies; $port asks the address of the site instead
function getSiteReply(string $method, string $path, array $post = [], int $port = 0): array {
    global $rport, $rwork;
    $curl = curl_init('http://127.0.0.1:'.(($port > 0) ? $port : $rport).'/'.ltrim($path, '/'));
    $jar = $rwork.'/jar.txt';
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 300, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    $raw = (string)curl_exec($curl);
    $size = intval(curl_getinfo($curl, CURLINFO_HEADER_SIZE));
    return ['code' => intval(curl_getinfo($curl, CURLINFO_RESPONSE_CODE)), 'body' => substr($raw, $size)];
}

# Whether a page shows the text, read as the browser shows it
function checkPageSaid(array $page, string $text): bool {
    return str_contains(html_entity_decode($page['body'], ENT_QUOTES, 'UTF-8'), $text);
}

# The visible text of a page, cut for a report
function getPageText(array $page): string {
    $text = html_entity_decode(strip_tags((string)preg_replace('#<(script|style)\b.*?</\1>#s', '', $page['body'])), ENT_QUOTES, 'UTF-8');
    return substr(trim((string)preg_replace('#\s+#', ' ', $text)), 0, 600);
}

# The clean installation through the stops of setup.php: language, server, database, site, administrator, then every part of the run and the closing stop
function addSiteSetup(string $base, int $home): string {
    $dbc = getBaseCred();
    $page = getSiteReply('GET', 'setup.php');
    $tok = (preg_match('#name="token"\s+value="([a-f0-9]{64})"#', $page['body'], $hit) === 1) ? $hit[1] : '';
    if ($tok === '') return 'setup.php answered '.$page['code'].' without its form: '.getPageText($page);
    $ans = [['lang' => 'en'], [], ['xhost' => $dbc['host'], 'xuname' => $dbc['uname'], 'xpass' => $dbc['pass'], 'xname' => $base, 'xprefix' => RPREF],
        ['sname' => 'Rehearsal', 'surl' => 'http://127.0.0.1:'.$home, 'spanel' => RPANEL],
        ['aname' => 'rehearse', 'amail' => 'rehearse@rehearse.test', 'apwd' => RPASS, 'apwd2' => RPASS, 'auser' => '1']];
    foreach ($ans as $stop => $post) {
        $page = getSiteReply('POST', 'setup.php', ['token' => $tok, 'stop' => (string)$stop, 'go' => 'next'] + $post);
        $want = ($stop === 4) ? 5 : $stop + 1;
        $now = (preg_match('#name="stop"\s+value="(-?\d+)"#', $page['body'], $hit) === 1) ? intval($hit[1]) : -9;
        if ($now !== $want) return 'setup.php stayed on stop '.$now.' after stop '.$stop.': '.getPageText($page);
    }
    for ($i = 0; $i < 200; $i++) {
        $part = json_decode(getSiteReply('POST', 'setup.php', ['token' => $tok, 'stop' => '5', 'go' => 'part'])['body'], true);
        if (empty($part['more'])) break;
    }
    $page = getSiteReply('POST', 'setup.php', ['token' => $tok, 'stop' => '5', 'go' => 'next']);
    return checkPageSaid($page, '/'.RPANEL.'.php') ? '' : 'setup.php did not finish: '.getPageText($page);
}

# Log in to the panel as the administrator of the installation
function addPanelLogin(): string {
    getSiteReply('GET', RPANEL.'.php');
    getSiteReply('POST', RPANEL.'.php', ['op' => 'check_admin', 'name' => 'rehearse', 'pwd' => RPASS]);
    $page = getSiteReply('GET', RPANEL.'.php?name=update');
    return ($page['code'] === 200 && getPageToken($page) !== '') ? '' : 'the panel did not open the updates after the login: '.$page['code'].' '.getPageText($page);
}

# The token of the forms of the updates on a page of the panel, whose menu carries forms of other modules with tokens of their own
function getPageToken(array $page): string {
    preg_match_all('#<form\b.*?</form>#s', $page['body'], $all);
    foreach ($all[0] as $form) {
        $mine = str_contains($form, 'name=update') || preg_match('#name="name"\s+value="update"#', $form) === 1;
        if ($mine && preg_match('#name="token"\s+value="([a-f0-9]{64})"#', $form, $hit) === 1) return $hit[1];
    }
    return '';
}

# The form of a page of the executor or the rescuer: its method, address and hidden fields, or an empty array for a page without one
function getExecForm(string $html): array {
    if (preg_match('#<form id="sl-(apply|rescue)-form"[^>]*method="(post|get)" action="([^"]*)">(.*?)</form>#s', $html, $hit) !== 1) return [];
    preg_match_all('#<input type="hidden" name="([^"]+)" value="([^"]*)">#', $hit[4], $all, PREG_SET_ORDER);
    $vals = [];
    foreach ($all as $one) $vals[$one[1]] = html_entity_decode($one[2], ENT_QUOTES);
    return ['verb' => $hit[2], 'act' => html_entity_decode($hit[3]), 'vals' => $vals];
}

# Follow the pages of an installation or a rollback as the browser does until a page asks for nothing more; the answer is the last page and the number of requests
function getExecWalk(array $page): array {
    for ($i = 0; $i < 500; $i++) {
        $form = getExecForm($page['body']);
        if ($form === [] || $form['verb'] === 'get') break;
        $page = getSiteReply('POST', $form['act'], $form['vals']);
    }
    return ['page' => $page, 'steps' => $i];
}

# The code page of an installation or a rollback started from the panel, and the first page of the executor that its button opens
function getExecStart(string $op, string $go, array $post): array {
    $tok = getPageToken(getSiteReply('GET', RPANEL.'.php?name=update'));
    $code = getSiteReply('POST', RPANEL.'.php?name=update&op='.$op, ['token' => $tok] + $post);
    $run = (preg_match('#name="apply"\s+value="([a-f0-9]{64})"#', $code['body'], $hit) === 1) ? $hit[1] : '';
    if ($run === '') return ['error' => $op.' showed no code page: '.$code['code'].' '.getPageText($code)];
    return ['error' => '', 'page' => getSiteReply('POST', RPANEL.'.php', ['name' => 'update', 'op' => $go, 'token' => $tok, 'apply' => $run])];
}

# The state of the updates of the site
function getSiteState(string $site): array {
    $file = $site.'/storage/update/client/state.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($data) ? $data : [];
}

# The version core/version.php of the site names
function getSiteVersion(string $site): string {
    return (preg_match("#'SL_VERSION', '([0-9.]+)'#", (string)file_get_contents($site.'/core/version.php'), $hit) === 1) ? $hit[1] : '';
}

# The hashes of the code and the settings of the site; storage/, uploads/ and the cached configuration are the site's own and left out
function getTreeHash(string $root): array {
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $item) {
        $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if ($item->isFile() && !preg_match('#^(storage|uploads)/|^config/local\.php$#', $path)) $out[$path] = hash_file('sha256', $item->getPathname());
    }
    ksort($out);
    return $out;
}

# The paths two hashed trees disagree on
function getTreeDiff(array $one, array $two): array {
    $keys = array_unique(array_merge(array_keys($one), array_keys($two)));
    sort($keys);
    return array_values(array_filter($keys, static fn(string $key): bool => ($one[$key] ?? '') !== ($two[$key] ?? '')));
}

# What an installation may change in the database and a rollback must give back: the definition of every table, the canary rows of the site and the foreign rows
function getBaseShape(PDO $pdo): array {
    $tabs = [];
    foreach ($pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN) as $tab) {
        $tabs[$tab] = (string)preg_replace('# AUTO_INCREMENT=\d+#', '', (string)$pdo->query('SHOW CREATE TABLE `'.$tab.'`')->fetch(PDO::FETCH_NUM)[1]);
    }
    $rows = static fn(string $tab): array => isset($tabs[$tab]) ? $pdo->query('SELECT id, txt FROM `'.$tab.'` ORDER BY id')->fetchAll(PDO::FETCH_NUM) : [];
    return ['tabs' => $tabs, 'mark' => $rows(RPREF.'_rehearse'), 'other' => $rows(RFOREIGN.'_data')];
}

# The schema of the site tables without the canary, by name and so blind to the order of columns: engine, collation, columns and keys, to hold an update to a clean installation
function getBaseSchema(PDO $pdo): array {
    $like = str_replace('_', '\_', RPREF).'\_%';
    $out = [];
    $sql = 'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ? AND TABLE_NAME NOT LIKE ?';
    $ask = static function (string $sql, array $args) use ($pdo): array {
        $res = $pdo->prepare($sql);
        $res->execute($args);
        return $res->fetchAll(PDO::FETCH_NUM);
    };
    $skip = str_replace('_', '\_', RFOREIGN).'\_%';
    foreach ($ask($sql, [$like, $skip]) as [$tab, $eng, $coll]) {
        if ($tab === RPREF.'_rehearse') continue;
        $cols = $ask('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = ? ORDER BY COLUMN_NAME', [$tab]);
        $keys = $ask('SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            .' ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$tab]);
        $out[$tab] = [$eng, $coll, $cols, $keys];
    }
    ksort($out);
    return $out;
}

# A canary table of the site and a foreign installation with a longer prefix beside it, both written once the installer no longer probes the prefix
function setBaseMarks(PDO $pdo): void {
    $pdo->exec('CREATE TABLE `'.RPREF.'_rehearse` (id INT NOT NULL PRIMARY KEY, txt VARCHAR(80) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO `'.RPREF.'_rehearse` (id, txt) VALUES (1, \'kept by the site\'), (2, \'two;\nlines\')');
    $pdo->exec('CREATE TABLE `'.RFOREIGN.'_admins` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE `'.RFOREIGN.'_data` (id INT NOT NULL PRIMARY KEY, txt VARCHAR(80) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO `'.RFOREIGN.'_data` (id, txt) VALUES (1, \'a foreign installation\')');
}

# The entries the PHP and SQL error journals of the site hold, each as its message and the address that caused it, the same entry once
function getLogLines(string $site): array {
    $out = [];
    foreach (['error_php', 'error_sql'] as $one) {
        $file = $site.'/storage/logs/'.$one.'.log';
        $text = is_file($file) ? trim((string)file_get_contents($file)) : '';
        if ($text === '') continue;
        $msgs = array_map(static fn(string $line): string => (string)(json_decode($line, true)['msg'] ?? $line).' @ '.(string)(json_decode($line, true)['url'] ?? ''),
            explode("\n", $text));
        $out[$one] = array_slice(array_values(array_unique($msgs)), 0, 10);
    }
    return $out;
}

# Turn the scheduler of the site off, so no job of the rehearsal reaches the master of slaed.net
function setSiteQuiet(string $site): void {
    $file = $site.'/config/scheduler.php';
    $data = (static fn(string $path): mixed => include $path)($file);
    $data['scheduler']['active'] = '0';
    file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n");
}

# Hand an archive and its signed manifest to Update of the site in a child process without OPcache, whose cache Windows shares between the runs of one path
function addSiteStage(string $site, string $zip, string $kind): string {
    global $rwork, $rver;
    $copy = $rwork.'/upload-'.$kind.'.zip';
    copy($zip, $copy);
    $keys = $rwork.'/keys.json';
    file_put_contents($keys, json_encode(Update::getKeyList(RELEASE_DIR.'/keys')));
    $base = RELEASE_DIR.'/'.$rver.'/manifest-stable.json';
    $cmd = [PHP_BINARY, '-d', 'opcache.enable_cli=0', __FILE__, '--stage', $site, $copy, $base, $keys];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $site);
    $out = (string)stream_get_contents($pipes[1]).(string)stream_get_contents($pipes[2]);
    proc_close($proc);
    $res = json_decode($out, true);
    return (($res['ok'] ?? false) === true) ? '' : 'the site did not take the '.$kind.' archive: '.substr(trim($out), 0, 600);
}

# The child of addSiteStage(), whose BASE_DIR is the site: its own Update takes the answer of a master and the archive, verifying the signature under its keys
function setStageChild(string $site, string $file, string $base, string $keys): never {
    define('PUBLIC_DIR', $site.'/public');
    define('CONFIG_DIR', $site.'/config');
    define('UPDATE_DIR', $site.'/storage/update');
    define('BACKUP_DIR', $site.'/storage/backup');
    define('LOGS_DIR', $site.'/storage/logs');
    require $site.'/core/version.php';
    require $site.'/core/classes/update.php';
    $list = array_map(static fn(array $one): array => ['body' => base64_encode($one['body']), 'sig' => $one['sig'], 'psig' => $one['psig']],
        (array)json_decode((string)file_get_contents($keys), true));
    $man = ['body' => base64_encode((string)file_get_contents($base)), 'sig' => trim((string)file_get_contents($base.'.sig'))];
    $state = Update::setCheckReply(['manifest' => $man, 'keys' => $list, 'notes' => []], Update::getState(), Update::getKeyFile(), 'stable');
    $offer = Update::setState($state) ? Update::getOffer($state, 'stable') : ['ok' => false, 'error' => 'write', 'data' => []];
    $res = $offer['ok'] ? Update::addUpload($file, $offer['data'], $site) : ['ok' => false, 'error' => (string)($state['error'] ?: $offer['error']), 'name' => ''];
    echo json_encode($res);
    exit;
}

# One fresh site of the previous release: unpacked, served on two ports, installed by its setup.php, logged in to its panel, the canary tables written
function addFreshSite(string $zip): array {
    global $rbase, $rport, $rwork;
    $site = $rwork.'/site';
    deleteWorkTree($site);
    foreach (glob($rwork.'/jar.txt') ?: [] as $file) unlink($file);
    $read = new ZipArchive();
    if ($read->open($zip) !== true || !$read->extractTo($site)) setFailExit('cannot unpack '.basename($zip));
    $read->close();
    setWorkBase($rbase);
    $rport = getFreePort();
    $home = getFreePort();
    $procs = [addWorkServer($site, $rport), addWorkServer($site, $home)];
    $error = addSiteSetup($rbase, $home);
    if ($error === '') $error = addPanelLogin();
    if ($error === '') {
        setSiteQuiet($site);
        setBaseMarks(getBaseLink($rbase));
    }
    return ['site' => $site, 'home' => $home, 'procs' => $procs, 'error' => $error];
}

# Whether the site answers its home page and the ping of its API
function getSiteAlive(int $home): array {
    $page = getSiteReply('GET', '', [], $home);
    $ping = getSiteReply('GET', 'api/v1/system/ping', [], $home);
    return ['home' => $page['code'], 'ping' => $ping['code'], 'ok' => $page['code'] === 200 && $ping['code'] === 200];
}

# The integrity check of the panel over the installed files: no changed, missing or extra file
function getSiteAudit(string $site): array {
    $tok = getPageToken(getSiteReply('GET', RPANEL.'.php?name=update'));
    getSiteReply('POST', RPANEL.'.php', ['name' => 'update', 'op' => 'audit', 'token' => $tok]);
    $audit = (array)(getSiteState($site)['audit'] ?? []);
    $bad = array_filter(['changed' => $audit['changed'] ?? [], 'missing' => $audit['missing'] ?? [], 'extra' => $audit['extra'] ?? []]);
    return ['ok' => intval($audit['time'] ?? 0) > 0 && ($audit['error'] ?? '') === '' && $bad === [], 'error' => (string)($audit['error'] ?? ''), 'bad' => $bad];
}

# One run of an archive: a fresh previous release takes it, installs, checks itself, its integrity and a schema of a clean installation, goes back and must be the old site
function getRunUpdate(string $kind, string $zip, string $prev, string $from, array $clean): array {
    global $rbase, $rver;
    $out = ['kind' => $kind, 'ok' => false, 'error' => ''];
    $fresh = addFreshSite($prev);
    $site = $fresh['site'];
    try {
        if ($fresh['error'] !== '') return ['error' => $fresh['error']] + $out;
        if (getSiteVersion($site) !== $from) return ['error' => 'the previous archive installs '.getSiteVersion($site).', not '.$from] + $out;
        $alive = getSiteAlive($fresh['home']);
        if (!$alive['ok']) return ['error' => 'the previous release does not answer after its installation: '.json_encode($alive)] + $out;
        $error = addSiteStage($site, $zip, $kind);
        if ($error !== '') return ['error' => $error] + $out;
        $tok = getPageToken(getSiteReply('GET', RPANEL.'.php?name=update'));
        $test = getSiteReply('POST', RPANEL.'.php?name=update&op=test', ['token' => $tok]);
        if (!str_contains($test['body'], 'sl-alert-success')) return ['error' => '"Check the installation" refused: '.getPageText($test)] + $out;
        $pdo = getBaseLink($rbase);
        $tree = getTreeHash($site);
        $shape = getBaseShape($pdo);
        $run = getExecStart('install', 'start', ['over' => '1', 'free' => '1']);
        if ($run['error'] !== '') return ['error' => $run['error']] + $out;
        $walk = getExecWalk($run['page']);
        $done = (array)(getSiteState($site)['done'] ?? []);
        $out['install'] = ['steps' => $walk['steps'], 'to' => $done['to'] ?? ''];
        if (($done['to'] ?? '') !== $rver || getSiteVersion($site) !== $rver) {
            return ['error' => 'the installation did not reach '.$rver.': '.getPageText($walk['page']), 'state' => getSiteState($site)] + $out;
        }
        getSiteReply('GET', RPANEL.'.php?name=update');
        $check = (string)(getSiteState($site)['done']['check'] ?? '');
        if ($check !== 'ok') return ['error' => 'the self-check after the installation answered '.$check] + $out;
        $alive = getSiteAlive($fresh['home']);
        if (!$alive['ok']) return ['error' => 'the new release does not answer: '.json_encode($alive)] + $out;
        $audit = getSiteAudit($site);
        if (!$audit['ok']) return ['error' => '"Check integrity" after the installation is not clean: '.json_encode($audit)] + $out;
        $plan = getBaseSchema($pdo);
        $odd = array_keys(array_filter(array_merge($clean, $plan), static fn(mixed $one, string $tab): bool => ($clean[$tab] ?? null) !== ($plan[$tab] ?? null),
            ARRAY_FILTER_USE_BOTH));
        if ($odd !== []) return ['error' => 'the schema after the update differs from a clean installation of '.$rver.': '.implode(', ', $odd)] + $out;
        $made = getBaseShape($pdo);
        $kept = [$made['mark'], $made['other']] === [$shape['mark'], $shape['other']];
        if (!$kept) return ['error' => 'the installation changed rows of the site or of the foreign installation'] + $out;
        $out['steps'] = array_values(array_diff(array_keys($made['tabs']), array_keys($shape['tabs'])));
        $back = getExecStart('revert', 'back', []);
        if ($back['error'] !== '') return ['error' => $back['error']] + $out;
        $walk = getExecWalk($back['page']);
        $out['revert'] = ['steps' => $walk['steps']];
        if (getSiteVersion($site) !== $from || is_file($site.'/storage/update/client/apply.lock')) {
            return ['error' => '"Restore the previous version" did not reach '.$from.': '.getPageText($walk['page'])] + $out;
        }
        $diff = getTreeDiff($tree, getTreeHash($site));
        if ($diff !== []) return ['error' => 'the files after going back differ from the previous release: '.implode(', ', array_slice($diff, 0, 20))] + $out;
        if (getBaseShape($pdo) !== $shape) return ['error' => 'the database after going back differs from the one before the installation'] + $out;
        $alive = getSiteAlive($fresh['home']);
        if (!$alive['ok']) return ['error' => 'the previous release does not answer after going back: '.json_encode($alive)] + $out;
        $logs = getLogLines($site);
        if ($logs !== []) return ['error' => 'the error journals of the site are not empty', 'logs' => $logs] + $out;
        return ['ok' => true] + $out;
    } finally {
        foreach ($fresh['procs'] as $proc) deleteWorkServer($proc);
    }
}

# The full archive installed clean, as every new site takes it: the site answering, its integrity clean, its journals empty and its schema kept for the update runs
function getRunClean(string $zip): array {
    global $rbase, $rver;
    $out = ['kind' => 'clean', 'ok' => false, 'error' => '', 'schema' => []];
    $fresh = addFreshSite($zip);
    $site = $fresh['site'];
    try {
        if ($fresh['error'] !== '') return ['error' => $fresh['error']] + $out;
        if (getSiteVersion($site) !== $rver) return ['error' => 'the archive installs '.getSiteVersion($site).', not '.$rver] + $out;
        $alive = getSiteAlive($fresh['home']);
        if (!$alive['ok']) return ['error' => 'the site does not answer after its installation: '.json_encode($alive)] + $out;
        $audit = getSiteAudit($site);
        if (!$audit['ok']) return ['error' => '"Check integrity" after the installation is not clean: '.json_encode($audit)] + $out;
        $logs = getLogLines($site);
        if ($logs !== []) return ['error' => 'the error journals of the site are not empty', 'logs' => $logs] + $out;
        $schema = getBaseSchema(getBaseLink($rbase));
        return ['ok' => true, 'schema' => $schema, 'tables' => count($schema)] + $out;
    } finally {
        foreach ($fresh['procs'] as $proc) deleteWorkServer($proc);
    }
}

# The full archive of the previous release: the one built on this machine when its signed manifest names its hash, else the one of the GitHub release
function getPrevArchive(string $from): string {
    global $rwork;
    $dir = RELEASE_DIR.'/'.$from;
    $file = $dir.'/slaed-'.$from.'.zip';
    $man = is_file($dir.'/manifest-stable.json') ? json_decode((string)file_get_contents($dir.'/manifest-stable.json'), true) : null;
    if (is_file($file) && is_array($man) && hash_file('sha256', $file) === ($man['full']['sha256'] ?? '')) return $file;
    $gh = (PHP_OS_FAMILY === 'Windows' && is_file('C:/Program Files/GitHub CLI/gh.exe')) ? 'C:/Program Files/GitHub CLI/gh.exe' : 'gh';
    $proc = proc_open([$gh, 'release', 'download', 'v'.$from, '--pattern', 'slaed-'.$from.'.zip', '--dir', $rwork, '--clobber'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        BASE_DIR);
    $err = (string)stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || !is_file($rwork.'/slaed-'.$from.'.zip')) setFailExit('no full archive of '.$from.' here or in the GitHub release: '.trim($err));
    return $rwork.'/slaed-'.$from.'.zip';
}

# Rehearse a release: its archives against the signed manifest, a run for each of them on a fresh previous release, and the record publish reads
function setRehearsal(string $tag, bool $keep): void {
    global $rbase, $rver, $rwork;
    $rver = substr($tag, 1);
    if (!str_starts_with($tag, 'v') || !Update::isVersion($rver)) setFailExit('the tag is vX.Y.Z');
    $dir = RELEASE_DIR.'/'.$rver;
    $name = $dir.'/manifest-stable.json';
    if (!is_file($name) || !is_file($name.'.sig')) setFailExit('manifest-stable.json of '.$rver.' is not signed; php tools/sign.php '.$rver);
    $man = json_decode((string)file_get_contents($name), true);
    if (!is_array($man) || ($man['version'] ?? '') !== $rver) setFailExit('manifest-stable.json of '.$rver.' names another version');
    $record = $dir.'/rehearsal.json';
    if (is_file($record)) unlink($record);
    $packs = ['full' => $dir.'/slaed-'.$rver.'.zip'] + (is_array($man['delta'] ?? null) ? ['delta' => $dir.'/slaed-'.$rver.'-from-'.$man['delta']['from'].'.zip'] : []);
    foreach ($packs as $kind => $file) {
        if (!is_file($file) || hash_file('sha256', $file) !== ($man[$kind]['sha256'] ?? '')) setFailExit(basename($file).' is not the archive the signed manifest names');
    }
    $rwork = str_replace('\\', '/', sys_get_temp_dir()).'/slaed-rehearse-'.$rver;
    deleteWorkTree($rwork);
    mkdir($rwork, 0755, true);
    $rbase = 'slaed_rehearse_'.bin2hex(random_bytes(4));
    $from = (string)($man['delta']['from'] ?? '');
    $runs = [];
    try {
        setNote('Run clean: '.$rver.' installed clean'.(($from === '') ? ', the first release with an updater' : ''));
        $runs[] = $res = getRunClean($packs['full']);
        $prev = ($from !== '' && $res['ok']) ? getPrevArchive($from) : '';
        foreach (($prev !== '') ? $packs : [] as $kind => $file) {
            setNote('Run '.$kind.': '.$from.' installed clean, then '.basename($file));
            $runs[] = $res = getRunUpdate($kind, $file, $prev, $from, $runs[0]['schema']);
            if (!$res['ok']) break;
        }
        foreach ($runs as $i => $one) unset($runs[$i]['schema']);
    } finally {
        deleteWorkBase($rbase);
        if (!$keep) deleteWorkTree($rwork);
    }
    foreach ($runs as $res) {
        setNote(($res['ok'] ? 'green ' : 'RED ').$res['kind'].($res['ok'] ? '' : ': '.$res['error']).(isset($res['logs']) ? "\n".json_encode($res['logs']) : ''));
    }
    if (in_array(false, array_column($runs, 'ok'), true)) setFailExit('the rehearsal of '.$rver.' is red; fix, build, sign and rehearse again under the same number');
    $vers = getBaseLink()->query('SELECT VERSION()')->fetchColumn();
    $data = ['version' => $rver, 'from' => $from, 'full' => $man['full']['sha256'], 'delta' => $man['delta']['sha256'] ?? null, 'time' => time(), 'php' => PHP_VERSION,
        'db' => (string)$vers, 'runs' => $runs];
    file_put_contents($record, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    setNote('Green: '.$rver.' rehearsed; next: php tools/release.php publish v'.$rver);
}

if (($argv[1] ?? '') === '--stage' && count($argv) === 6) setStageChild(BASE_DIR, $argv[3], $argv[4], $argv[5]);
require_once BASE_DIR.'/core/classes/update.php';
if (!isset($argv[1]) || str_starts_with($argv[1], '--')) {
    fwrite(STDERR, "Usage: php tools/rehearse.php vX.Y.Z [--keep]\n");
    exit(1);
}
setRehearsal((string)$argv[1], in_array('--keep', $argv, true));
