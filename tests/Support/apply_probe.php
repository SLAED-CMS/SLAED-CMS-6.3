<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe of the installation from the panel over real HTTP, step 9 of docs/11-UPDATE-2026.md; nothing touches the site tree or the site database
# It copies the code of the tree into a disposable site with its own database, writes the files.json of 6.3.0 and unpacks a package of 6.3.1 into its stage/
# The package is signed with a fresh key the copy trusts; the built-in server serves the copy's public/, and every exchange is a real request with its own cookies
# Each run starts from the same pristine copy: a full installation and its way back, a failed database step, a failed dump, a cut batch continued or rolled back by the code
# The last run breaks the run copy and rolls back by rescue.now; the argument unit answers the pure parts of Update in a child process instead
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));

# The table prefix of the disposable database, the version of the package and the password of its super-administrator
const APREF = 'probe';
const AVER = '6.3.1';
const APASS = 'root-pass-1';

# The folders of the tree a site copy needs; the editors are left out of public/plugins/, no page of the run loads one
const ADIRS = ['admin', 'blocks', 'core', 'lang', 'modules', 'config'];

$awork = str_replace('\\', '/', (string)($argv[1] ?? sys_get_temp_dir().'/slaed_update_apply'));
$amode = (string)($argv[2] ?? 'all');

# Remove one scratch tree, read-only files included
function deleteProbeTree(string $path): void {
    if (is_link($path) || is_file($path)) {
        chmod($path, 0644);
        unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $one) if ($one !== '.' && $one !== '..') deleteProbeTree($path.'/'.$one);
    rmdir($path);
}

# Copy a tree file by file, leaving out the relative paths of $skip and config/local.php
function addProbeCopy(string $from, string $to, array $skip = []): void {
    if (is_file($from)) {
        if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
        copy($from, $to);
        return;
    }
    if (!is_dir($to)) mkdir($to, 0755, true);
    foreach (scandir($from) ?: [] as $one) {
        if ($one === '.' || $one === '..' || in_array($one, $skip, true) || $one === 'local.php') continue;
        addProbeCopy($from.'/'.$one, $to.'/'.$one);
    }
}

# Write one configuration source in the shape getConfig() reads
function setProbeConf(string $file, array $data): void {
    file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n");
}

# One connection to the database server, with a database selected when one is named
function getProbePdo(string $name = ''): PDO {
    $dbc = (require BASE_DIR.'/config/db.php')['db'];
    $dsn = 'mysql:host='.$dbc['host'].(($name === '') ? '' : ';dbname='.$name).';charset=utf8mb4';
    return new PDO($dsn, $dbc['uname'], $dbc['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Lift the installer splitter and the statement cleaner it calls out of core/admin.php
function addProbeSplitter(): void {
    $code = (string)file_get_contents(BASE_DIR.'/core/admin.php');
    foreach (['getSqlbatch', 'getSqlclean'] as $name) {
        if (function_exists($name)) continue;
        $from = strpos($code, 'function '.$name.'(');
        $to = ($from === false) ? false : strpos($code, "\n}\n", $from);
        if ($from === false || $to === false) throw new RuntimeException($name.'() is gone from core/admin.php');
        eval(substr($code, $from, $to - $from + 3));
    }
}

# Create the disposable database again from the shipped schema with a super-administrator, a note table of the site and a foreign installation beside it
# A huge note gets a key beyond the integer range of PHP, which the portion dump refuses to seek, so the installation stops on its step 4
function setProbeBase(string $name, bool $huge): PDO {
    $dbc = (require BASE_DIR.'/config/db.php')['db'];
    if ($name === $dbc['name'] || !str_starts_with($name, 'slaed_apply_')) throw new RuntimeException('The disposable name collides with the site database');
    getProbePdo()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    getProbePdo()->exec('CREATE DATABASE `'.$name.'` DEFAULT CHARACTER SET utf8mb4 COLLATE '.$dbc['collate']);
    $pdo = getProbePdo($name);
    addProbeSplitter();
    $sql = getSqlbatch((string)file_get_contents(BASE_DIR.'/storage/update/sql/table.sql'));
    foreach ($sql['statements'] as $one) $pdo->exec(str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [APREF, 'InnoDB', 'utf8mb4', $dbc['collate']], $one));
    $pdo->exec('INSERT INTO '.APREF.'_admins (id, name, email, password, super, smail, modules, ip) VALUES (1, \'root\', \'root@probe.test\', '
        .$pdo->quote(password_hash(APASS, PASSWORD_BCRYPT)).', 1, 0, \'\', \'127.0.0.1\')');
    $pdo->exec('CREATE TABLE '.APREF.'_note (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, txt TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO '.APREF.'_note (id, txt) VALUES (1, ?), (2, ?)')->execute(['kept before the installation', "two;\nlines"]);
    if ($huge) $pdo->exec('INSERT INTO '.APREF.'_note (id, txt) VALUES (18446744073709551615, \'beyond the integers of PHP\')');
    $pdo->exec('CREATE TABLE other_admins (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE other_data (id INT NOT NULL PRIMARY KEY, txt VARCHAR(40) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO other_data (id, txt) VALUES (1, \'foreign row\')');
    return $pdo;
}

# Drop the disposable database
function deleteProbeBase(string $name): bool {
    try {
        if (str_starts_with($name, 'slaed_apply_')) getProbePdo()->exec('DROP DATABASE IF EXISTS `'.$name.'`');
        return true;
    } catch (Throwable) {
        return false;
    }
}

# The files.json of a tree: every file below the folders of a release with its SHA-256 and size, the files of the site marked, the list itself left out
function getProbeList(string $root, string $ver): array {
    $out = [];
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $item) {
        $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (!$item->isFile() || $path === 'storage/update/files.json' || str_starts_with($path, 'storage/update/client/')) continue;
        $out[$path] = ['sha256' => hash_file('sha256', $item->getPathname()), 'size' => $item->getSize()] + (Update::isSiteFile($path) ? ['site' => true] : []);
    }
    ksort($out);
    return ['version' => $ver, 'files' => $out];
}

# Write a files.json
function setProbeList(string $root, string $ver): void {
    if (!is_dir($root.'/storage/update')) mkdir($root.'/storage/update', 0755, true);
    file_put_contents($root.'/storage/update/files.json', json_encode(getProbeList($root, $ver), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

# Add a line right after the opening tag of a PHP file
function setProbeLine(string $file, string $line): void {
    file_put_contents($file, preg_replace('#^<\?php\n#', "<?php\n".$line."\n", (string)file_get_contents($file), 1));
}

# The pristine site of 6.3.0 and its package of 6.3.1, built once per run of the probe
# The installed core/system.php notes each boot of it in a log, so is the one of the package; the installed core/apply.php is broken, so only the copy of the package can install
function setProbePristine(string $work, string $base): void {
    $site = $work.'/pristine';
    foreach (ADIRS as $dir) addProbeCopy(BASE_DIR.'/'.$dir, $site.'/'.$dir);
    foreach (['index.php', 'admin.php', 'setup.php', 'error.html', 'robots.txt', 'favicon.ico'] as $one) addProbeCopy(BASE_DIR.'/public/'.$one, $site.'/public/'.$one);
    foreach (['admin', 'lite'] as $one) addProbeCopy(BASE_DIR.'/public/templates/'.$one, $site.'/public/templates/'.$one);
    addProbeCopy(BASE_DIR.'/public/plugins', $site.'/public/plugins', ['editors']);
    $data = require $site.'/config/db.php';
    $data['db'] = array_replace($data['db'], ['name' => $base, 'prefix' => APREF]);
    setProbeConf($site.'/config/db.php', $data);
    $data = require $site.'/config/security.php';
    $data['security'] = array_replace($data['security'], ['afile' => 'admin', 'blocker_ip' => '', 'blocker_user' => '', 'blocker_cookie' => 'banned', 'flood' => '0',
        'error' => '0', 'log_a' => '1', 'log' => '0']);
    $data['security']['captcha']['active'] = '0';
    setProbeConf($site.'/config/security.php', $data);
    $data = require $site.'/config/update.php';
    $data['update'] = array_replace($data['update'], ['steps' => ['6.3.0' => '2026-10-01 00:00:00'], 'check' => ['upcheck' => '0', 'channel' => 'stable', 'keepbak' => '1',
        'upsite' => '0']]);
    setProbeConf($site.'/config/update.php', $data);
    $data = require $site.'/config/scheduler.php';
    $data['scheduler']['active'] = '0';
    setProbeConf($site.'/config/scheduler.php', $data);
    file_put_contents($site.'/core/probegone.php', "<?php\n# a file of 6.3.0 that 6.3.1 deletes\n");
    foreach (['uploads/presentation/sites/thumb', 'uploads/presentation/brand/thumb', 'uploads/presentation/dna', 'storage/cache', 'storage/logs', 'storage/backup'] as $dir) {
        mkdir($site.'/'.$dir, 0755, true);
    }
    setProbeList($site, '6.3.0');
    $pack = $work.'/package';
    addProbeCopy($site, $pack, ['uploads', 'cache', 'logs', 'backup']);
    unlink($pack.'/core/probegone.php');
    file_put_contents($pack.'/core/version.php', str_replace("'6.3.0'", "'".AVER."'", (string)file_get_contents($pack.'/core/version.php')));
    setProbeLine($pack.'/core/system.php', "file_put_contents(dirname(__DIR__).'/storage/probe-core.log', \"new\\n\", FILE_APPEND);");
    file_put_contents($pack.'/core/rescue.php', "\n# the rescuer of ".AVER."\n", FILE_APPEND);
    file_put_contents($pack.'/lang/en.php', "\n# the language of ".AVER."\n", FILE_APPEND);
    file_put_contents($pack.'/public/templates/lite/assets/css/theme.css', "\n/* the theme of ".AVER." */\n", FILE_APPEND);
    file_put_contents($pack.'/core/probenew.php', "<?php\n# a file 6.3.1 adds\n");
    mkdir($pack.'/tools/probe', 0755, true);
    file_put_contents($pack.'/tools/probe/new.txt', "a folder 6.3.1 adds\n");
    $data = require $pack.'/config/global.php';
    setProbeConf($pack.'/config/global.php', $data + ['probeopt' => 'added by 6.3.1']);
    mkdir($pack.'/storage/update/step', 0755, true);
    setProbeList($pack, AVER);
    setProbeLine($site.'/core/system.php', "file_put_contents(dirname(__DIR__).'/storage/probe-core.log', \"old\\n\", FILE_APPEND);");
    file_put_contents($site.'/core/apply.php', "<?php\n# the installed executor, broken on purpose\nexit('installed executor');\n");
    mkdir($site.'/public/templates/probe3', 0755, true);
    file_put_contents($site.'/public/templates/probe3/index.html', "<!-- a theme of a third party, in no release -->\n");
}

# The keys and state of a site that found 6.3.1 and unpacked it: a manifest signed with a fresh key the copy trusts, the stage of the package and its changed files
function setProbeFound(string $site, string $home): void {
    $pair = sodium_crypto_sign_keypair();
    $pub = sodium_crypto_sign_publickey($pair);
    $list = json_decode((string)file_get_contents($site.'/storage/update/client/stage/'.AVER.'/storage/update/files.json'), true);
    $hash = hash('sha256', json_encode($list));
    $man = ['channel' => 'stable', 'commit' => str_repeat('a', 40), 'delta' => null, 'expires' => time() + 86400 * 45, 'full' => ['sha256' => $hash, 'size' => 1000,
        'usize' => 2000], 'kid' => substr(hash('sha256', $pub), 0, 16), 'min' => '6.3.0', 'php' => '8.0', 'security' => false, 'time' => time(), 'type' => 'release',
        'version' => AVER];
    $body = json_encode($man, JSON_UNESCAPED_SLASHES);
    $sig = base64_encode(sodium_crypto_sign_detached(Update::MANIFEST.$body, sodium_crypto_sign_secretkey($pair)));
    $dir = $site.'/storage/update/client';
    file_put_contents($dir.'/keys.json', json_encode(['kseq' => 0, 'primary' => base64_encode($pub), 'recovery' => base64_encode($pub), 'revoked' => []]));
    $state = ['sid' => str_repeat('b', 32), 'home' => hash('sha256', rtrim($home, '/')), 'found' => ['version' => AVER, 'channel' => 'stable', 'security' => false,
        'php' => '8.0', 'min' => '6.3.0', 'expires' => $man['expires'], 'body' => base64_encode($body), 'sig' => $sig], 'stage' => ['version' => AVER, 'kind' => 'full',
        'from' => '', 'sha256' => $hash, 'time' => time(), 'files' => count($list['files']), 'changed' => ['core/system.php'], 'nolist' => false]];
    file_put_contents($dir.'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

# One site of a run: the pristine copy with its address, the package in its stage/ with the database step $step, a fresh database and the built-in server on it
# A delta leaves core/apply.php out of the package with the installed one intact, and the site changes core/version.php after the package was unpacked
function setProbeSite(string $work, string $base, int $port, string $step, bool $huge, bool $delta = false): PDO {
    $site = $work.'/site';
    deleteProbeTree($site);
    addProbeCopy($work.'/pristine', $site);
    $data = require $site.'/config/global.php';
    setProbeConf($site.'/config/global.php', array_replace($data, ['homeurl' => 'http://127.0.0.1:'.$port]));
    $stage = $site.'/storage/update/client/stage/'.AVER;
    addProbeCopy($work.'/package', $stage);
    mkdir($stage.'/storage/update/step', 0755, true);
    file_put_contents($stage.'/storage/update/step/'.AVER.'.sql', $step);
    setProbeList($stage, AVER);
    if ($delta) {
        unlink($stage.'/core/apply.php');
        copy(BASE_DIR.'/core/apply.php', $site.'/core/apply.php');
    }
    setProbeFound($site, 'http://127.0.0.1:'.$port);
    if ($delta) file_put_contents($site.'/core/version.php', "\n# changed on the site after the unpacking\n", FILE_APPEND);
    foreach (glob($work.'/jar*.txt') ?: [] as $file) unlink($file);
    return setProbeBase($base, $huge);
}

# Start the built-in server on the public/ of the site copy and wait until it answers
function addProbeServer(string $work, int $port): mixed {
    $log = ['file', $work.'/server.log', 'a'];
    $cmd = [PHP_BINARY, '-d', 'opcache.revalidate_freq=0', '-d', 'max_execution_time=60', '-S', '127.0.0.1:'.$port, '-t', $work.'/site/public'];
    $proc = proc_open($cmd, [1 => $log, 2 => $log], $pipes, $work.'/site');
    set_error_handler(static fn(): bool => true);
    for ($i = 0; $i < 50 && !($test = stream_socket_client('tcp://127.0.0.1:'.$port, $no, $err, 1)); $i++) usleep(100000);
    restore_error_handler();
    if (!$test) throw new RuntimeException('The probe server did not start');
    fclose($test);
    return $proc;
}

# Stop the built-in server
function deleteProbeServer(mixed $proc): void {
    if (!is_resource($proc)) return;
    $info = proc_get_status($proc);
    if (PHP_OS_FAMILY === 'Windows') exec('taskkill /F /T /PID '.intval($info['pid']).' 2>NUL');
    else proc_terminate($proc);
    proc_close($proc);
}

# One free local port
function getProbePort(): int {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr((string)strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    return $port;
}

# One real exchange with the cookies of the run: the status, the headers by lower-case name and the body
function getProbeReply(string $method, string $path, array $post = []): array {
    global $awork, $aport;
    $curl = curl_init('http://127.0.0.1:'.$aport.'/'.ltrim($path, '/'));
    $jar = $awork.'/jar.txt';
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 120, CURLOPT_CUSTOMREQUEST => $method]);
    if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    $raw = (string)curl_exec($curl);
    $size = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $heads = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (!str_contains($line, ':')) continue;
        [$key, $val] = explode(':', $line, 2);
        $heads[strtolower(trim($key))] = trim($val);
    }
    return ['code' => (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'head' => $heads, 'body' => substr($raw, $size)];
}

# The form of a page of the executor or the rescuer: its method, address and hidden fields, or an empty array for a page without one
function getProbeForm(string $html): array {
    if (preg_match('#<form id="sl-(apply|rescue)-form"[^>]*method="(post|get)" action="([^"]*)">(.*?)</form>#s', $html, $hit) !== 1) return [];
    preg_match_all('#<input type="hidden" name="([^"]+)" value="([^"]*)">#', $hit[4], $all, PREG_SET_ORDER);
    $vals = [];
    foreach ($all as $one) $vals[$one[1]] = html_entity_decode($one[2], ENT_QUOTES);
    return ['verb' => $hit[2], 'act' => html_entity_decode($hit[3]), 'vals' => $vals];
}

# Follow the pages of an installation or a rollback as the browser does until a page asks for nothing more, or $stop names the phase of the lock to halt at
# Each step notes the phase it found, its status and how many boots of either core it caused, so the probe sees which request loaded which core
function getProbeWalk(string $page, string $stop = ''): array {
    global $awork;
    $steps = [];
    $log = $awork.'/site/storage/probe-core.log';
    for ($i = 0; $i < 60; $i++) {
        $form = getProbeForm($page);
        $lock = getProbeLock();
        if ($form === [] || $form['verb'] === 'get' || ($stop !== '' && ($lock['phase'] ?? '') === $stop)) break;
        $before = is_file($log) ? (string)file_get_contents($log) : '';
        $got = getProbeReply('POST', $form['act'], $form['vals']);
        $after = is_file($log) ? (string)file_get_contents($log) : '';
        $steps[] = ['phase' => (string)($lock['phase'] ?? ''), 'code' => $got['code'], 'boot' => trim(substr($after, strlen($before)))];
        $page = $got['body'];
    }
    return ['steps' => $steps, 'page' => $page, 'last' => getProbeForm($page)];
}

# The lock of the installation on the site copy, or an empty array
function getProbeLock(): array {
    global $awork;
    $file = $awork.'/site/storage/update/client/apply.lock';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($data) ? $data : [];
}

# The state of the site copy
function getProbeState(): array {
    global $awork;
    $data = json_decode((string)file_get_contents($awork.'/site/storage/update/client/state.json'), true);
    return is_array($data) ? $data : [];
}

# The hashes of the code and the settings of the site copy, storage/ and uploads/ left out, the cached configuration too
function getProbeTree(): array {
    global $awork;
    $root = $awork.'/site';
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $item) {
        $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if ($item->isFile() && !preg_match('#^(storage|uploads)/|^config/local\.php$#', $path)) $out[$path] = hash_file('sha256', $item->getPathname());
    }
    ksort($out);
    return $out;
}

# The paths two hashed trees disagree on
function getProbeDiff(array $one, array $two): array {
    $keys = array_unique(array_merge(array_keys($one), array_keys($two)));
    sort($keys);
    return array_values(array_filter($keys, static fn(string $key): bool => ($one[$key] ?? '') !== ($two[$key] ?? '')));
}

# What the database holds that an installation changes: the tables, the shape of the admins, the notes of the site and the rows of the foreign installation
function getProbeData(PDO $pdo): array {
    $tabs = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
    return ['tabs' => $tabs, 'admins' => $pdo->query('SHOW CREATE TABLE '.APREF.'_admins')->fetch(PDO::FETCH_NUM)[1],
        'note' => $pdo->query('SELECT id, txt FROM '.APREF.'_note ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        'other' => $pdo->query('SELECT id, txt FROM other_data ORDER BY id')->fetchAll(PDO::FETCH_NUM)];
}

# A configuration source of the site copy as its array
function getProbeConf(string $name): array {
    global $awork;
    $data = (static fn(string $path): mixed => include $path)($awork.'/site/config/'.$name.'.php');
    return is_array($data) ? $data : [];
}

# Log in to the panel of the site copy as its super-administrator and return the token of the updates from the overview
function getProbeLogin(): array {
    getProbeReply('GET', 'admin.php');
    $login = getProbeReply('POST', 'admin.php', ['op' => 'check_admin', 'name' => 'root', 'pwd' => APASS]);
    $page = getProbeReply('GET', 'admin.php?name=update');
    $tok = preg_match('#name="op"\s+value="test".*?name="token"\s+value="([a-f0-9]{64})"#s', $page['body'], $hit) === 1 ? $hit[1] : '';
    return ['login' => $login['code'], 'code' => $page['code'], 'token' => $tok, 'box' => str_contains($page['body'], 'op=install'), 'body' => $page['body']];
}

# The code page of an installation or a rollback: its token and its rescue code
function getProbeCode(array $got): array {
    $tok = preg_match('#name="apply"\s+value="([a-f0-9]{64})"#', $got['body'], $hit) === 1 ? $hit[1] : '';
    $code = preg_match('#\b([0-9a-f]{4}(-[0-9a-f]{4}){4})\b#', $got['body'], $hit) === 1 ? $hit[1] : '';
    return ['code' => $got['code'], 'apply' => $tok, 'rescue' => $code];
}

# Confirm and start the installation of the package, the boxes of the preflight marked
function getProbeStart(string $tok): array {
    $code = getProbeCode(getProbeReply('POST', 'admin.php?name=update&op=install', ['token' => $tok, 'over' => '1']));
    $got = getProbeReply('POST', 'admin.php', ['name' => 'update', 'op' => 'start', 'token' => $tok, 'apply' => $code['apply']]);
    return ['code' => $code, 'start' => $got];
}

# What the site copy shows after an installation or a rollback: version, the files of the package, settings, marks, caches and the leftovers of the run
function getProbeLook(): array {
    global $awork;
    $site = $awork.'/site';
    $dir = $site.'/storage/update/client';
    return ['ver' => preg_match("#'SL_VERSION', '([0-9.]+)'#", (string)file_get_contents($site.'/core/version.php'), $hit) === 1 ? $hit[1] : '',
        'new' => is_file($site.'/core/probenew.php'), 'folder' => is_file($site.'/tools/probe/new.txt'), 'gone' => !is_file($site.'/core/probegone.php'),
        'lang' => str_contains((string)file_get_contents($site.'/lang/en.php'), 'the language of '.AVER),
        'theme' => str_contains((string)file_get_contents($site.'/public/templates/lite/assets/css/theme.css'), 'the theme of '.AVER),
        'third' => is_file($site.'/public/templates/probe3/index.html'), 'rescue' => str_contains((string)file_get_contents($site.'/core/rescue.php'), 'the rescuer of '.AVER),
        'executor' => !str_contains((string)file_get_contents($site.'/core/apply.php'), 'installed executor'), 'option' => isset(getProbeConf('global')['probeopt']),
        'marks' => array_keys(getProbeConf('update')['update']['steps'] ?? []), 'local' => is_file($site.'/config/local.php'), 'lock' => is_file($dir.'/apply.lock'),
        'run' => is_dir($dir.'/run'), 'stage' => is_dir($dir.'/stage'), 'now' => is_file($dir.'/rescue.now'),
        'cache' => count(array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site.'/storage/cache', FilesystemIterator::SKIP_DOTS))),
            static fn(SplFileInfo $one): bool => $one->isFile() && !str_ends_with($one->getFilename(), '.lock'))),
        'backups' => array_map('basename', glob($site.'/storage/backup/update/*', GLOB_ONLYDIR) ?: []),
        'list' => json_decode((string)file_get_contents($site.'/storage/update/files.json'), true)['version'] ?? ''];
}

# The closed site while the lock lies: every entry, the API in JSON, the pseudo-cron, a forged token and a second start
function getProbeClosed(string $tok): array {
    $look = static fn(array $got): array => ['code' => $got['code'], 'type' => $got['head']['content-type'] ?? '', 'retry' => $got['head']['retry-after'] ?? ''];
    return ['site' => $look(getProbeReply('GET', '')), 'api' => $look(getProbeReply('GET', 'api/v1/system/ping')), 'panel' => $look(getProbeReply('GET', 'admin.php')),
        'setup' => $look(getProbeReply('GET', 'setup.php')), 'cron' => $look(getProbeReply('POST', 'index.php?go=3&op=scheduler', ['token' => 'x'])),
        'forged' => $look(getProbeReply('POST', 'admin.php', ['apply' => str_repeat('c', 64)])),
        'again' => $look(getProbeReply('POST', 'admin.php', ['name' => 'update', 'op' => 'install', 'token' => $tok, 'over' => '1']))];
}

# Cut a batch of files midway as a hosting time limit would: half of the moves done, the next one half done, the lock marking the batch as begun but not finished
function setProbeCut(): array {
    global $awork;
    $site = $awork.'/site';
    $lock = getProbeLock();
    $plan = json_decode((string)file_get_contents($site.'/'.$lock['back'].'/plan.json'), true);
    $half = intdiv(count($plan['moves']), 2);
    foreach (array_slice($plan['moves'], 0, $half + 1) as $i => $one) {
        $dest = $site.'/'.$one['path'];
        $keep = $site.'/'.$lock['back'].'/files/'.$one['path'];
        if (is_file($dest)) {
            if (!is_dir(dirname($keep))) mkdir(dirname($keep), 0755, true);
            rename($dest, $keep);
        }
        if ($i === $half || $one['op'] !== 'put') continue;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
        rename($site.'/'.$lock['stage'].'/'.$one['src'], $dest);
    }
    $lock['upto'] = count($plan['moves']);
    file_put_contents($site.'/storage/update/client/apply.lock', json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return ['moves' => count($plan['moves']), 'half' => $half];
}

# The full installation: the preflight refusals and the dry run first, then the closed site, the phases and the cores they boot, the self-check and the way back
function getProbeHappy(PDO $pdo): array {
    global $awork;
    $out = ['login' => getProbeLogin()];
    $tok = $out['login']['token'];
    unset($out['login']['body']);
    $tree = getProbeTree();
    $data = getProbeData($pdo);
    $test = getProbeReply('POST', 'admin.php?name=update&op=test', ['token' => $tok]);
    $out['test'] = ['code' => $test['code'], 'ready' => str_contains($test['body'], 'sl-alert-success'), 'tree' => getProbeDiff($tree, getProbeTree()),
        'data' => getProbeData($pdo) === $data, 'lock' => is_file($awork.'/site/storage/update/client/apply.lock')];
    $nomark = getProbeReply('POST', 'admin.php?name=update&op=install', ['token' => $tok]);
    $out['nomark'] = ['code' => $nomark['code'], 'apply' => getProbeState()['apply'] ?? [], 'lock' => getProbeLock() !== []];
    chmod($awork.'/site/lang/en.php', 0444);
    $deny = getProbeReply('POST', 'admin.php?name=update&op=install', ['token' => $tok, 'over' => '1']);
    $flash = getProbeReply('GET', 'admin.php?name=update');
    chmod($awork.'/site/lang/en.php', 0644);
    $out['deny'] = ['code' => $deny['code'], 'named' => str_contains($flash['body'], 'deny - lang/en.php'), 'apply' => getProbeState()['apply'] ?? []];
    $run = getProbeStart($tok);
    $out['code'] = $run['code'];
    $out['first'] = ['code' => $run['start']['code'], 'form' => getProbeForm($run['start']['body'])['act'] ?? '', 'lock' => getProbeLock()['phase'] ?? ''];
    $out['closed'] = getProbeClosed($tok);
    $walk = getProbeWalk($run['start']['body']);
    $out['walk'] = $walk['steps'];
    if (!is_dir($awork.'/site/'.(getProbeState()['done']['back'] ?? '-'))) return $out + ['halt' => substr(strip_tags($walk['page']), 0, 1500), 'lock' => getProbeLock()];
    $back = $awork.'/site/'.getProbeState()['done']['back'];
    $plan = json_decode((string)file_get_contents($back.'/plan.json'), true);
    $out['plan'] = array_values(array_filter(array_column($plan['moves'], 'path'), static fn(string $path): bool => !str_contains($test['body'], $path)));
    $made = getProbeData($pdo);
    $out['done'] = ['look' => getProbeLook(), 'table' => in_array(APREF.'_probe_new', $made['tabs'], true), 'column' => str_contains($made['admins'], 'probe_col'),
        'kept' => [$made['note'], $made['other']] === [$data['note'], $data['other']], 'state' => getProbeState()['done'] ?? [],
        'oldrescue' => is_file($back.'/files/core/rescue.php')];
    $view = getProbeReply('GET', 'admin.php?name=update');
    $out['view'] = ['code' => $view['code'], 'check' => getProbeState()['done']['check'] ?? '', 'revert' => str_contains($view['body'], 'value="revert"')];
    $out['selfok'] = ['code' => getProbeReply('POST', 'admin.php', ['name' => 'update', 'op' => 'selfok', 'token' => $tok])['code'],
        'check' => getProbeState()['done']['check'] ?? ''];
    $code = getProbeCode(getProbeReply('POST', 'admin.php', ['name' => 'update', 'op' => 'revert', 'token' => $tok]));
    $page = getProbeReply('POST', 'admin.php', ['name' => 'update', 'op' => 'back', 'token' => $tok, 'apply' => $code['apply']]);
    $walk = getProbeWalk($page['body']);
    $out['revert'] = ['code' => $code, 'first' => $page['code'], 'walk' => $walk['steps'], 'look' => getProbeLook(), 'tree' => getProbeDiff($tree, getProbeTree()),
        'data' => getProbeData($pdo) === $data, 'state' => array_intersect_key(getProbeState(), array_flip(['done', 'undo', 'fail'])),
        'site' => getProbeReply('GET', '')['code']];
    return $out;
}

# A database step that fails after it created a table and a column: the installation rolls back the database, config/ and the files by itself
function getProbeStep(PDO $pdo): array {
    $tok = getProbeLogin()['token'];
    $tree = getProbeTree();
    $data = getProbeData($pdo);
    $run = getProbeStart($tok);
    $walk = getProbeWalk($run['start']['body']);
    return ['walk' => $walk['steps'], 'final' => $walk['last']['act'] ?? '', 'look' => getProbeLook(), 'tree' => getProbeDiff($tree, getProbeTree()),
        'data' => getProbeData($pdo) === $data, 'state' => array_intersect_key(getProbeState(), array_flip(['done', 'undo', 'fail'])),
        'site' => getProbeReply('GET', '')['code']];
}

# A dump that cannot frame a row: the installation stops on step 4 with nothing changed and the site open again
function getProbeDump(PDO $pdo): array {
    $tok = getProbeLogin()['token'];
    $tree = getProbeTree();
    $data = getProbeData($pdo);
    $run = getProbeStart($tok);
    $walk = getProbeWalk($run['start']['body']);
    return ['code' => $run['start']['code'], 'walk' => $walk['steps'], 'stopped' => str_contains($walk['page'], 'Installation stopped'), 'look' => getProbeLook(),
        'tree' => getProbeDiff($tree, getProbeTree()), 'data' => getProbeData($pdo) === $data,
        'state' => array_intersect_key(getProbeState(), array_flip(['undo', 'fail'])), 'site' => getProbeReply('GET', '')['code']];
}

# A batch cut midway, then the rescue page: a wrong code refused, the right code with $act continuing the installation or rolling it back
function getProbeCrash(PDO $pdo, string $act): array {
    $tok = getProbeLogin()['token'];
    $tree = getProbeTree();
    $data = getProbeData($pdo);
    $run = getProbeStart($tok);
    $walk = getProbeWalk($run['start']['body'], 'files');
    $out = ['phase' => getProbeLock()['phase'] ?? '', 'cut' => setProbeCut()];
    $form = getProbeReply('GET', '?rescue');
    $wrong = getProbeReply('POST', 'admin.php', ['rescue' => 'ffff-ffff-ffff-ffff-ffff', 'act' => $act]);
    $out['form'] = ['code' => $form['code'], 'field' => str_contains($form['body'], 'name="rescue"'), 'wrong' => $wrong['code'], 'still' => getProbeLock()['phase'] ?? ''];
    $page = getProbeReply('POST', 'admin.php', ['rescue' => strtoupper($run['code']['rescue']), 'act' => $act]);
    if ($act === 'go') $out['old'] = getProbeReply('POST', 'admin.php', $walk['last']['vals'] ?? [])['code'];
    $walk = getProbeWalk($page['body']);
    $out += ['first' => $page['code'], 'walk' => $walk['steps'], 'look' => getProbeLook(), 'tree' => getProbeDiff($tree, getProbeTree()), 'data' => getProbeData($pdo) === $data,
        'state' => array_intersect_key(getProbeState(), array_flip(['done', 'undo', 'fail'])), 'site' => getProbeReply('GET', '')['code']];
    return $out;
}

# A run copy of the executor that no longer matches its hash: the gate refuses it, and rescue.now put by FTP rolls the installation back without any code
function getProbeNow(PDO $pdo): array {
    global $awork;
    $tok = getProbeLogin()['token'];
    $tree = getProbeTree();
    $run = getProbeStart($tok);
    $walk = getProbeWalk($run['start']['body'], 'files');
    $dir = $awork.'/site/storage/update/client';
    file_put_contents($dir.'/run/apply.php', "\n# broken\n", FILE_APPEND);
    $out = ['broken' => getProbeReply('POST', 'admin.php', $walk['last']['vals'] ?? [])['code']];
    touch($dir.'/rescue.now');
    $page = getProbeReply('GET', '');
    $walk = getProbeWalk($page['body']);
    $out += ['first' => $page['code'], 'walk' => $walk['steps'], 'look' => getProbeLook(), 'tree' => getProbeDiff($tree, getProbeTree()),
        'state' => array_intersect_key(getProbeState(), array_flip(['undo', 'fail'])), 'site' => getProbeReply('GET', '')['code']];
    return $out;
}

# A delta without the executor and a file changed after the unpacking: the stop rests on the files as they are now, the overview learns them, the installed executor runs
function getProbeDelta(PDO $pdo): array {
    $tok = getProbeLogin()['token'];
    $nomark = getProbeReply('POST', 'admin.php?name=update&op=install', ['token' => $tok]);
    $view = getProbeReply('GET', 'admin.php?name=update');
    $out = ['nomark' => $nomark['code'], 'changed' => getProbeState()['stage']['changed'] ?? [], 'box' => str_contains($view['body'], 'name="over"'),
        'lock' => getProbeLock() !== []];
    $run = getProbeStart($tok);
    $walk = getProbeWalk($run['start']['body']);
    $made = getProbeData($pdo);
    return $out + ['start' => $run['start']['code'], 'walk' => array_column($walk['steps'], 'phase'), 'look' => getProbeLook(),
        'table' => in_array(APREF.'_probe_new', $made['tabs'], true)];
}

# The pure parts of Update in this process: the opcache rule, the steps of a package, the plan of moves and the space formula
function getProbeUnit(string $work): array {
    define('FUNC_FILE', true);
    define('PUBLIC_DIR', $work.'/pristine/public');
    define('UPDATE_DIR', $work.'/pristine/storage/update');
    define('BACKUP_DIR', $work.'/pristine/storage/backup');
    require_once BASE_DIR.'/core/version.php';
    require_once BASE_DIR.'/core/classes/update.php';
    $ini = static fn(bool $on, bool $check, string $limit): array => Update::getOpcacheState(['on' => $on, 'check' => $check, 'freq' => 2, 'limit' => $limit,
        'path' => '/srv/site/public']);
    $steps = $work.'/steps';
    deleteProbeTree($steps);
    mkdir($steps, 0755, true);
    foreach (['6.2.9.sql', '6.3.1.sql', '6.3.1.php', '6.3.2.sql', 'x.sql'] as $one) file_put_contents($steps.'/'.$one, '');
    $plan = Update::getApplyPlan($work.'/package', 'panel', $work.'/pristine');
    $paths = array_column($plan['moves'], 'path');
    $space = Update::getApplySpace(1000, ['bsize' => 10, 'nsize' => 20], $work.'/pristine', '');
    return ['opcache' => ['off' => $ini(false, false, '/other')['ok'], 'check' => $ini(true, true, '/other')['ok'], 'reset' => $ini(true, false, '/srv/site')['ok'],
        'closed' => $ini(true, false, '/other')['ok']],
        'steps' => array_keys(Update::getStepList('6.3.0', $steps, '6.3.1')), 'files' => Update::getStepList('6.3.0', $steps, '6.3.1')['6.3.1'] ?? [],
        'plan' => ['entry' => array_column($plan['moves'], 'path', 'src')['public/admin.php'] ?? '', 'last' => end($paths) ?: '', 'tail' => array_slice($paths, -4),
            'drops' => $plan['drops'], 'rescue' => in_array('core/rescue.php', $paths, true), 'site' => in_array('config/global.php', $paths, true), 'deny' => $plan['deny']],
        'space' => ['need' => $space['need'], 'same' => $space['same']]];
}

if ($amode === 'unitext') {
    echo json_encode(getProbeUnit($awork), JSON_UNESCAPED_SLASHES);
    exit;
}
$report = ['error' => '', 'runs' => [], 'clean' => false];
$abase = 'slaed_apply_'.bin2hex(random_bytes(4));
$aproc = null;
try {
    deleteProbeTree($awork);
    mkdir($awork, 0755, true);
    define('FUNC_FILE', true);
    require_once BASE_DIR.'/core/classes/update.php';
    setProbePristine($awork, $abase);
    if ($amode === 'all' || $amode === 'unit') {
        $res = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($awork).' unitext 2>&1');
        $report['runs']['unit'] = json_decode($res, true) ?? ['raw' => substr($res, 0, 2000)];
    }
    $good = "CREATE TABLE {prefix}_probe_new (id INT NOT NULL PRIMARY KEY) ENGINE={engine};\nALTER TABLE {prefix}_admins ADD COLUMN probe_col INT NOT NULL DEFAULT 0;\n";
    $runs = ['happy' => [$good, false], 'step' => [$good."INSERT INTO {prefix}_probe_nosuch (id) VALUES (1);\n", false], 'dump' => [$good, true],
        'crashgo' => [$good, false], 'crashback' => [$good, false], 'now' => [$good, false], 'delta' => [$good, false]];
    foreach (($amode === 'all') ? $runs : array_intersect_key($runs, [$amode => true]) as $key => [$step, $huge]) {
        $aport = getProbePort();
        $apdo = setProbeSite($awork, $abase, $aport, $step, $huge, $key === 'delta');
        $aproc = addProbeServer($awork, $aport);
        $report['runs'][$key] = match ($key) {
            'happy' => getProbeHappy($apdo),
            'step' => getProbeStep($apdo),
            'dump' => getProbeDump($apdo),
            'crashgo' => getProbeCrash($apdo, 'go'),
            'crashback' => getProbeCrash($apdo, 'back'),
            'delta' => getProbeDelta($apdo),
            default => getProbeNow($apdo),
        };
        $report['runs'][$key]['logs'] = array_map(static fn(string $file): string => (string)file_get_contents($file), glob($awork.'/site/storage/logs/error_{php,sql}.log',
            GLOB_BRACE) ?: []);
        deleteProbeServer($aproc);
        $aproc = null;
        $apdo = null;
    }
} catch (Throwable $error) {
    $report['error'] = $error->getMessage().' @ '.$error->getFile().':'.$error->getLine();
} finally {
    deleteProbeServer($aproc);
    $report['clean'] = deleteProbeBase($abase);
}
echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
