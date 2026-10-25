<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('SETUP_FILE', true);
# The installer in the document root: the project is the folder above it; a test or a tool may name both folders first
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/core/gate.php';
if (($sgfile = getSiteGate()) !== '') require $sgfile;
checkSiteGate();
define('CONFIG_DIR', BASE_DIR.'/config');
define('BACKUP_DIR', BASE_DIR.'/storage/backup');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('LOGS_DIR', BASE_DIR.'/storage/logs');
define('UPDATE_DIR', BASE_DIR.'/storage/update');

# Read one POST or cookie value through the filter of the installer, as getVar() is not loaded before the last part; pass keeps the spaces of a password
function getSetupVar(string $src, string $key, string $type = ''): string {
    $list = ($src === 'cookie') ? $_COOKIE : $_POST;
    $val = is_string($list[$key] ?? null) ? (string)preg_replace('/[\x00-\x1F\x7F]/u', '', $list[$key]) : '';
    if ($type === 'pass') return $val;
    $val = trim($val);
    if ($type === 'var') return preg_match('/^[a-zA-Z0-9_-]+$/D', $val) ? $val : '';
    if ($type === 'num') return preg_match('/^[0-9]{1,4}$/D', $val) ? $val : '';
    if ($type === 'text') return trim(strip_tags($val));
    return $val;
}

# Whether a POST carries the session token of this installation, the CSRF guard of every form and part, as the core tokens need the core
function checkSetupToken(array $state): bool {
    $code = (string)$state['token'];
    return $code !== '' && hash_equals($code, getSetupVar('post', 'token', 'var'));
}

# The answers of this installation from the session; reach is the furthest stop the browser may post from, part the next part of the run or -1 before it
function getSetupState(): array {
    global $conf;
    $data = $_SESSION[$conf['user_c'].'-setup'] ?? [];
    if (!is_array($data)) $data = [];
    $base = ['token' => '', 'lang' => '', 'reach' => 0, 'base' => [], 'site' => [], 'admin' => [], 'part' => -1, 'count' => 0, 'busy' => -1, 'fail' => '', 'note' => ''];
    return $data + $base + ['made' => 0];
}

# Store the answers of this installation in the session; an empty array removes them, as the closing stop and a refusal of an installed site do
function setSetupState(array $data): void {
    global $conf;
    if ($data === []) unset($_SESSION[$conf['user_c'].'-setup']);
    else $_SESSION[$conf['user_c'].'-setup'] = $data;
}

# Begin the answers of a new installation on its first form: the token, the prefix of the tables and the panel file are random and the address comes from the request
function getSetupFresh(): array {
    global $conf;
    $data = getSetupState();
    $data['token'] = bin2hex(random_bytes(32));
    $data['base'] = ['host' => 'localhost', 'uname' => '', 'pass' => '', 'name' => '', 'prefix' => getSetupRandom(10)];
    $data['site'] = ['name' => (string)($conf['sitename'] ?? ''), 'url' => getSetupUrl(), 'panel' => strtolower(getSetupRandom(10))];
    $data['admin'] = ['name' => '', 'mail' => '', 'hash' => '', 'user' => '1'];
    return $data;
}

# A random string of letters and digits for the prefix of the tables and the name of the panel file, which nobody should be able to guess
function getSetupRandom(int $len): string {
    $text = '';
    for ($i = 0; $i < $len; $i++) {
        $num = random_int(48, 122);
        if (($num > 57 && $num < 65) || ($num > 90 && $num < 97)) $num -= 9;
        $text .= chr($num);
    }
    return $text;
}

# The address the request reached the site by, as protocol and host; a host header outside the grammar of a host gives localhost, because it is only a proposal
function getSetupUrl(): string {
    $https = ($_SERVER['SERVER_PORT'] ?? '') == 443 || in_array(strtolower((string)($_SERVER['HTTPS'] ?? '')), ['on', '1'], true)
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' || strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
    if (!preg_match('/^[a-z0-9.\-]+(?::[0-9]{1,5})?$|^\[[0-9a-f:.]+\](?::[0-9]{1,5})?$/D', $host)) $host = 'localhost';
    return ($https ? 'https' : 'http').'://'.$host;
}

# The six languages of the release with their own names and the flags of the admin theme; a language whose two dictionaries are not both shipped is left out
function getSetupLangs(): array {
    $list = ['en' => ['English', 'gb'], 'de' => ['Deutsch', 'de'], 'fr' => ['Français', 'fr'], 'pl' => ['Polski', 'pl'], 'ru' => ['Русский', 'ru'], 'uk' => ['Українська', 'ua']];
    return array_filter($list, fn(string $v): bool => is_file(BASE_DIR.'/lang/'.$v.'.php') && is_file(BASE_DIR.'/admin/lang/'.$v.'.php'), ARRAY_FILTER_USE_KEY);
}

# The language of this request: the tile posted from the first stop, then the one the session chose, then the language cookie of the core, then English
function getSetupLang(array $state): string {
    global $conf;
    $langs = getSetupLangs();
    foreach ([getSetupVar('post', 'lang', 'var'), $state['lang'], getSetupVar('cookie', $conf['user_c'].'-language', 'var')] as $code) {
        if (isset($langs[$code])) return $code;
    }
    return isset($langs['en']) ? 'en' : (string)array_key_first($langs);
}

# Read one configuration file and answer its array, an empty array for a missing file or one that returns no array
function getSetupSource(string $file): array {
    $data = is_file(CONFIG_DIR.'/'.$file) ? (static fn(string $path): mixed => include $path)(CONFIG_DIR.'/'.$file) : [];
    return is_array($data) ? $data : [];
}

# The connection settings of config/db.php, empty while the release ships none
function getSetupBase(): array {
    $data = getSetupSource('db.php')['db'] ?? [];
    return is_array($data) ? $data : [];
}

# Write one configuration file, every scalar a string unless $raw keeps native types; a file not writable stays as it was and answers false
function setSetupFile(string $fp, array $arr, array $act = [], bool $raw = false): bool {
    $fp = BASE_DIR.'/config/'.$fp;
    if (!empty($act)) $arr = array_replace_recursive($arr, $act);
    ksort($arr);
    $norm = function ($val) use (&$norm) {
        if (is_array($val)) {
            foreach ($val as $kk => $vv) $val[$kk] = $norm($vv);
            return $val;
        }
        if (is_bool($val)) return (string)(int)$val;
        if (is_int($val)) return (string)$val;
        if (is_float($val)) return (string)$val;
        if (is_null($val)) return '';
        return (string)$val;
    };
    if (!$raw) $arr = $norm($arr);
    $key = pathinfo(basename($fp), PATHINFO_FILENAME);
    $data = ($key === 'global') ? $arr : [$key => $arr];
    $exp = function (array $arr, int $dep = 0) use (&$exp): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['."\n";
        foreach ($arr as $key => $val) {
            $body = is_array($val) ? $exp($val, $dep + 1) : var_export($val, true);
            $out .= $ind.var_export($key, true).' => '.$body.','."\n";
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'."\n"
    .'# Author: Eduard Laas'."\n"
    .'# 2005 - '.date('Y').' SLAED'."\n"
    .'# License: MIT'."\n"
    .'# Website: slaed.net'."\n\n"
    .'return '.$exp($data).';'."\n";
    $lock = FileManager::getPathLock(CONFIG_DIR);
    $done = is_writable(is_file($fp) ? $fp : CONFIG_DIR) && file_put_contents($fp, $cnt, LOCK_EX) === strlen($cnt);
    if (function_exists('opcache_invalidate')) opcache_invalidate($fp, true);
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    FileManager::deletePathLock($lock);
    return $done;
}

# Whether PHP may write a configuration file the run writes, or config/ for one it creates; permissions are never changed, a file opened to everyone hands out the password
function checkSetupWrite(string $file): bool {
    return is_writable(is_file(CONFIG_DIR.'/'.$file) ? CONFIG_DIR.'/'.$file : CONFIG_DIR);
}

# Whether a table prefix keeps to its grammar: letters, digits and _, at most 32 characters
function checkSetupPrefix(string $name): bool {
    return preg_match('/^[A-Za-z0-9_]{1,32}$/D', $name) === 1;
}

# Whether a panel file name keeps to its grammar and names no other file of the document root: index, setup and update are refused, admin.php and the panel may be named
function checkSetupPanel(string $name): bool {
    global $conf;
    $low = strtolower($name);
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $name) || in_array($low, ['index', 'setup', 'update'], true)) return false;
    return !is_file(PUBLIC_DIR.'/'.$name.'.php') || in_array($low, ['admin', strtolower((string)($conf['security']['afile'] ?? ''))], true);
}

# Whether an address may become the home of the site: http or https with a host, no user, query or fragment, within 255 bytes; stored without a trailing slash
function checkSetupUrl(string $url): bool {
    $part = parse_url($url);
    if (strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false || !is_array($part)) return false;
    return in_array(strtolower($part['scheme'] ?? ''), ['http', 'https'], true) && !array_intersect_key($part, array_flip(['user', 'pass', 'query', 'fragment']));
}

# Whether the site is installed: config/db.php names a database with an admin row or one that does not answer, so a downed server is never handed out
function checkSetupDone(array $state): bool {
    $base = getSetupBase();
    if ((string)($base['name'] ?? '') === '' || $state['part'] >= 0) return false;
    try {
        $db = new Database((string)($base['host'] ?? ''), (string)($base['uname'] ?? ''), (string)($base['pass'] ?? ''), (string)$base['name'], (string)($base['collate'] ?? ''));
    } catch (RuntimeException) {
        return true;
    }
    $res = $db->getSqlQuery('SELECT id FROM `'.($base['prefix'] ?? '').'_admins` LIMIT 1');
    if ($res === false) return $db->getSqlError()['sqlstate'] !== '42S02';
    return $db->getSqlRow($res) !== null;
}

# The check rows of the server stop; config/ counts as writable only with the files the run writes, as the installer never changes a permission
function getSetupChecks(): array {
    $list = [['PHP '.PHP_VERSION, sprintf(_SETUP_NEED, '8.4'), version_compare(PHP_VERSION, '8.4.0', '>=')]];
    $exts = ['mbstring' => ['mbstring', _SETUP_MBSTRING, true], 'pdo_mysql' => ['PDO MySQL', _SETUP_PDO, true], 'json' => ['JSON', _SETUP_JSON, true],
        'zip' => ['Zip', _SETUP_ZIP, false], 'zlib' => ['Zlib', _SETUP_ZLIB, false]];
    foreach ($exts as $ext => [$name, $note, $must]) {
        $has = extension_loaded($ext);
        $list[] = [$name, $has ? $note : ($must ? _SETUP_NOEXT : _SETUP_NOOPT), $has || !$must, !$has && !$must];
    }
    $cfg = checkSetupWrite('db.php') && checkSetupWrite('global.php') && checkSetupWrite('security.php') && checkSetupWrite('update.php');
    foreach (['config' => $cfg, 'storage' => true, 'uploads' => true] as $dir => $good) {
        $good = $good && is_dir(BASE_DIR.'/'.$dir) && is_writable(BASE_DIR.'/'.$dir);
        $list[] = [$dir.'/', $good ? _SETUP_WRITABLE : _SETUP_NOWRITE, $good];
    }
    $url = getSetupUrl();
    $list[] = [strtoupper((string)parse_url($url, PHP_URL_SCHEME)), sprintf(_SETUP_ANSWERS, $url), true];
    $pub = getSetupRoot() === 'public';
    $list[] = [_SETUP_WEBROOT, $pub ? _SETUP_ROOT_PUB : _SETUP_ROOT_PRJ, true, !$pub];
    $rows = [];
    foreach ($list as $i => $row) $rows[] = ['turn' => $i, 'text' => $row[0], 'note' => $row[1], 'is_fail' => !$row[2], 'is_warn' => $row[3] ?? false];
    return $rows;
}

# The mode of the document root the installer found: public when the server answers from the folder of the entries, project when the project-level .htaccess rewrites into it
function getSetupRoot(): string {
    $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $pub = realpath(PUBLIC_DIR);
    if ($root === false || $pub === false) return 'project';
    $same = (PHP_OS_FAMILY === 'Windows') ? strcasecmp(rtrim($root, '\\/'), rtrim($pub, '\\/')) === 0 : rtrim($root, '/') === rtrim($pub, '/');
    return $same ? 'public' : 'project';
}

# Probe the database of the answers; MariaDB 10.5.2 and MySQL 8.0.16 are the floor, as the schema files use CHECK and RENAME COLUMN
function getSetupProbe(array $base): array {
    $rows = [['turn' => 0, 'text' => sprintf(_SETUP_CONNECT, $base['host']), 'note' => sprintf(_SETUP_CONN_USER, $base['uname']), 'is_fail' => true]];
    if ($base['host'] === '' || $base['uname'] === '' || $base['name'] === '') return ['checks' => $rows, 'fail' => _SQLERRORCON];
    try {
        $db = new Database($base['host'], $base['uname'], $base['pass'], $base['name'], $base['collate']);
    } catch (RuntimeException $err) {
        return ['checks' => $rows, 'fail' => $err->getMessage()];
    }
    $rows[0]['is_fail'] = false;
    $res = $db->getSqlQuery('SELECT VERSION()');
    $ver = $res ? (string)($db->getSqlRow($res)[0] ?? '') : '';
    $maria = stripos($ver, 'mariadb') !== false;
    $min = $maria ? '10.5.2' : '8.0.16';
    $num = (string)preg_replace('/[^0-9.].*$/', '', $ver);
    $old = version_compare($num, $min, '<');
    $rows[] = ['turn' => 1, 'text' => ($maria ? 'MariaDB ' : 'MySQL ').$num, 'note' => sprintf(_SETUP_NEED, $min), 'is_fail' => $old];
    if ($old) return ['checks' => $rows, 'fail' => sprintf(_SETUP_OLDDB, $ver, $min)];
    $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE :pre';
    $res = $db->getSqlQuery($sql, ['pre' => str_replace('_', '\_', $base['prefix']).'\_%']);
    $taken = !$res || intval($db->getSqlRow($res)[0] ?? 1) > 0;
    $rows[] = ['turn' => 2, 'text' => sprintf(_SETUP_FREE, $base['prefix']), 'note' => _SETUP_FREE_NOTE, 'is_fail' => $taken];
    return ['checks' => $rows, 'fail' => $taken ? sprintf(_SETUP_TAKEN, $base['prefix']) : ''];
}

# Take the answers of one stop into the session and answer the first refusal; $test is off for the back button, a database password may be empty
function checkSetupInput(int $stop, array &$state, bool $test): string {
    if ($stop === 0) {
        $code = getSetupVar('post', 'lang', 'var');
        if (isset(getSetupLangs()[$code])) $state['lang'] = $code;
    } elseif ($stop === 2) {
        $state['base'] = ['host' => getSetupVar('post', 'xhost'), 'uname' => getSetupVar('post', 'xuname'), 'pass' => getSetupVar('post', 'xpass', 'pass'),
            'name' => getSetupVar('post', 'xname'), 'prefix' => getSetupVar('post', 'xprefix'), 'collate' => 'utf8mb4_unicode_520_ci'];
        if ($test && !checkSetupPrefix($state['base']['prefix'])) return _SETUP_BADPREFIX;
    } elseif ($stop === 3) {
        $name = mb_substr(getSetupVar('post', 'sname', 'text'), 0, 255);
        $state['site'] = ['name' => $name, 'url' => rtrim(getSetupVar('post', 'surl'), '/'), 'panel' => getSetupVar('post', 'spanel')];
        if (!$test) return '';
        if ($state['site']['name'] === '') return _SITENAME.': '._ERROR;
        if (!checkSetupUrl($state['site']['url'])) return _ADDRESS.': '._ERROR;
        if (!checkSetupPanel($state['site']['panel'])) return _SETUP_BADPANEL;
    } elseif ($stop === 4) {
        $name = getSetupVar('post', 'aname');
        $pwd = getSetupVar('post', 'apwd', 'pass');
        $two = getSetupVar('post', 'apwd2', 'pass');
        $state['admin'] = ['name' => $name, 'mail' => getSetupVar('post', 'amail'), 'hash' => '', 'user' => (getSetupVar('post', 'auser', 'var') === '0') ? '0' : '1'];
        if (!$test) return '';
        if ($name === '' || preg_match('#["\'.:;/*<>&]#', $name)) return _ERRORINVNICK;
        if (strlen($name) > 25) return _NICKLONG;
        if (filter_var($state['admin']['mail'], FILTER_VALIDATE_EMAIL) === false) return _MAIL_BADMAIL;
        if ($pwd === '' && $two === '') return _NOPASS;
        if ($pwd !== $two) return _ERROR_PASS;
        $state['admin']['hash'] = password_hash($pwd, PASSWORD_BCRYPT);
    }
    return '';
}

# The preflight of Install, answering the first refusal; a configuration operation waiting in its journal marker.json refuses the run
function checkSetupRun(array $state): string {
    if (!checkSetupPrefix((string)($state['base']['prefix'] ?? ''))) return _SETUP_BADPREFIX;
    if (!checkSetupPanel((string)($state['site']['panel'] ?? ''))) return _SETUP_BADPANEL;
    foreach (['db.php', 'global.php', 'security.php', 'update.php'] as $file) {
        if (!checkSetupWrite($file)) return _FILE.' config/'.$file.' — '._SETUP_NOWRITE;
    }
    if (is_file(BACKUP_DIR.'/config/marker.json')) return _SETUP_JOURNAL;
    foreach (['table.sql', 'insert.sql'] as $file) {
        if (!getSetupSql($file)) return _FILE.' storage/update/sql/'.$file.' — '._ERROR;
    }
    return getSetupProbe($state['base'])['fail'];
}

# The statements of one schema file of storage/update/sql, an empty list for a missing file or one the splitter of the panel refuses
function getSetupSql(string $file): array {
    $path = BASE_DIR.'/storage/update/sql/'.$file;
    $list = is_file($path) ? getSqlbatch((string)file_get_contents($path)) : ['error' => $file, 'statements' => []];
    return ($list['error'] === '') ? $list['statements'] : [];
}

# The parts of the run in their order, each its own request: the configuration, the tables of table.sql in groups of seven, every statement of insert.sql and the administrator
function getSetupParts(): array {
    $list = [['kind' => 'config']];
    foreach (array_chunk(array_keys(getSetupSql('table.sql')), 7) as $keys) $list[] = ['kind' => 'table', 'keys' => $keys];
    foreach (array_keys(getSetupSql('insert.sql')) as $key) $list[] = ['kind' => 'data', 'keys' => [$key]];
    $list[] = ['kind' => 'admin'];
    return $list;
}

# Take the next part of the run and mark it busy, or -1; a part still busy broke its request off, so it fails the run instead of running twice
function getSetupNext(array &$state): int {
    if (!checkSetupToken($state) || $state['part'] < 0 || $state['fail'] !== '' || $state['part'] >= $state['count']) return -1;
    if ($state['busy'] >= 0) {
        $state['fail'] = _ERROR.': '._SETUP_INSTALL.' '.($state['busy'] + 1).' / '.$state['count'];
        setSetupState($state);
        return -1;
    }
    $state['busy'] = $state['part'];
    setSetupState($state);
    return $state['part'];
}

# Record what a part answered and give the driver of the run in admin-ui.js its progress: the per cent of the parts done, the task just done and whether another part follows
function setSetupNext(array &$state, array $out): array {
    $state['busy'] = -1;
    if ($out['fail'] !== '') $state['fail'] = $out['fail'];
    else $state['part']++;
    setSetupState($state);
    return ['percent' => intval(round($state['part'] * 100 / max(1, $state['count']))), 'task' => $out['task'], 'more' => $out['fail'] === '' && $state['part'] < $state['count']];
}

# Answer one part of the run as JSON and end the request; the output buffers of the core are dropped, so nothing but the answer leaves
function setSetupJson(array $data): never {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

# Run the next part before the core: the configuration or one group of statements of the schema; the administrator part boots the core and is run by addSetupAdmin()
function setSetupRun(array $state): array {
    $part = getSetupNext($state);
    if ($part < 0) return ['more' => false];
    $item = getSetupParts()[$part] ?? ['kind' => ''];
    if ($item['kind'] === 'config') $out = setSetupConfig($state);
    elseif ($item['kind'] === 'table' || $item['kind'] === 'data') $out = setSetupTables($state, $item);
    else $out = ['task' => '', 'fail' => _ERROR.': '._SETUP_INSTALL.' '.($part + 1).' / '.$state['count']];
    return setSetupNext($state, $out);
}

# Write the configuration part after one more probe; the password then leaves the session, as the later parts read it from config/db.php
function setSetupConfig(array &$state): array {
    global $conf;
    $base = $state['base'];
    $site = $state['site'];
    $fail = getSetupProbe($base)['fail'];
    if ($fail !== '') return ['task' => '', 'fail' => $fail];
    $glob = getSetupSource('global.php');
    if (!setSetupFile('global.php', $glob, ['language' => $state['lang'], 'homeurl' => $site['url'], 'sitename' => $site['name'], 'webroot' => getSetupRoot()])) {
        return ['task' => '', 'fail' => _FILE.' config/global.php — '._SETUP_NOWRITE];
    }
    $now = (string)($conf['security']['afile'] ?? 'admin');
    $from = is_file(PUBLIC_DIR.'/admin.php') ? 'admin' : $now;
    $panel = $site['panel'];
    if ($panel !== $from && (!is_file(PUBLIC_DIR.'/'.$from.'.php') || !rename(PUBLIC_DIR.'/'.$from.'.php', PUBLIC_DIR.'/'.$panel.'.php'))) $panel = $from;
    if (!in_array(strtolower($now), ['admin', 'index', 'setup', 'update', strtolower($panel)], true) && is_file(PUBLIC_DIR.'/'.$now.'.php')) unlink(PUBLIC_DIR.'/'.$now.'.php');
    $state['site']['panel'] = $panel;
    if (!setSetupFile('security.php', getSetupSource('security.php')['security'] ?? [], ['afile' => $panel])) {
        return ['task' => '', 'fail' => _FILE.' config/security.php — '._SETUP_NOWRITE];
    }
    $cont = ['host' => $base['host'], 'uname' => $base['uname'], 'pass' => $base['pass'], 'name' => $base['name'], 'engine' => 'InnoDB', 'charset' => 'utf8mb4',
        'collate' => $base['collate'], 'prefix' => $base['prefix']];
    if (!setSetupFile('db.php', getSetupBase(), $cont)) return ['task' => '', 'fail' => _FILE.' config/db.php — '._SETUP_NOWRITE];
    if (deleteSetupTypes() === false) return ['task' => '', 'fail' => _FILE.' config/node.php — '._SETUP_NOWRITE];
    $state['base']['pass'] = '';
    $task = ($panel !== $from) ? $from.'.php → '.$panel.'.php — '._SETUP_RENAMED : 'config/db.php — '._SETUP_WRITTEN;
    return ['task' => $task, 'fail' => ''];
}

# Take the types of config/node.php out of their package files as the panel removes a type; node.php goes last, so a repeat still finds them
function deleteSetupTypes(): array|false {
    $ntypes = array_keys(getSetupSource('node.php')['node']['types'] ?? []);
    if (!$ntypes) return [];
    $pack = [];
    foreach (['fields', 'uploads', 'ratings', 'node'] as $name) $pack[$name] = getSetupSource($name.'.php')[$name] ?? [];
    foreach ($ntypes as $name) unset($pack['node']['types'][$name], $pack['fields']['node'][$name], $pack['uploads'][$name], $pack['ratings']['node.'.$name]);
    if (($pack['fields']['node'] ?? null) === []) unset($pack['fields']['node']);
    foreach ($pack as $name => $data) if (!setSetupFile($name.'.php', $data, [], true)) return false;
    return $ntypes;
}

# Run one part of table.sql or insert.sql; the part before the administrator writes config/update.php with the mark node => new addNodeProfiles() reads
function setSetupTables(array &$state, array $item): array {
    $base = getSetupBase();
    try {
        $db = new Database((string)$base['host'], (string)$base['uname'], (string)$base['pass'], (string)$base['name'], (string)$base['collate']);
    } catch (RuntimeException $err) {
        return ['task' => '', 'fail' => $err->getMessage()];
    }
    $file = ($item['kind'] === 'table') ? 'table.sql' : 'insert.sql';
    $list = getSetupSql($file);
    $notes = ['blocks' => _SETUP_SEEDBLOCK, 'categories' => _CATEGORIES, 'forum' => _SETUP_SEEDFORUM];
    $task = '';
    foreach ($item['keys'] as $key) {
        $sql = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$base['prefix'], $base['engine'], $base['charset'], $base['collate']], $list[$key] ?? '');
        $info = getSqlinfo($sql);
        $name = ($info['table'] !== '') ? $info['table'] : $info['type'];
        if ($db->getSqlQuery($sql) === false) return ['task' => '', 'fail' => _ERROR.': '.$name.' — '.$db->getSqlError()['message']];
        $note = ($file === 'table.sql') ? _SETUP_TBL_MADE : ($notes[substr($name, strlen($base['prefix']) + 1)] ?? _OK);
        $task = $name.' — '.$note;
    }
    if ($file === 'table.sql') $state['made'] += count($item['keys']);
    $marks = ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0', 'node' => 'new', 'check' => ['upcheck' => '1', 'channel' => 'stable', 'keepbak' => '1',
        'upsite' => '1'], 'steps' => [SL_VERSION => date('Y-m-d H:i:s')]];
    if ($state['part'] !== $state['count'] - 2) return ['task' => $task, 'fail' => ''];
    if (!setSetupFile('update.php', $marks)) return ['task' => '', 'fail' => _FILE.' config/update.php — '._SETUP_NOWRITE];
    if (!setSetupCheck($state['site']['url'])) return ['task' => '', 'fail' => _FILE.' storage/update/client/state.json, config/scheduler.php — '._SETUP_NOWRITE];
    return ['task' => $task, 'fail' => ''];
}

# The identity of the new installation for the updater: a fresh id in storage/update/client/state.json and the daily minute of updatecheck it gives
function setSetupCheck(string $url): bool {
    require_once BASE_DIR.'/core/classes/update.php';
    $state = Update::getSite($url, true);
    $sched = getSetupSource('scheduler.php')['scheduler'] ?? [];
    if (empty($state['new']) || !is_array($sched)) return false;
    if (!isset($sched['jobs']['updatecheck'])) return true;
    $sched['jobs']['updatecheck']['schedule'] = Update::getCheckTime($state['sid']);
    return setSetupFile('scheduler.php', $sched);
}

# The last part, run with the core: the first administrator, a site account of the same name when asked, the Node profiles, the paths of the seed and the self-check markers
function addSetupAdmin(array &$state, string $hash): array {
    global $db, $conf;
    $adm = $state['admin'];
    $pars = ['name' => $adm['name'], 'url' => $state['site']['url'], 'email' => $adm['mail'], 'pass' => $hash, 'editor' => (string)($conf['editor']['admin'] ?? 'plain'),
        'lang' => $state['lang'], 'ip' => getIp()];
    $sql = 'INSERT INTO '.PREFIX_DB.'_admins VALUES (NULL, :name, \'Admin\', :url, :email, :pass, \'1\', :editor, \'1\', \'\', :lang, :ip, now(), now())';
    $aid = ($hash !== '' && $db->getSqlQuery($sql, $pars)) ? intval($db->getSqlLastId()) : 0;
    if ($aid < 1) return ['task' => '', 'fail' => _ERROR.': '.PREFIX_DB.'_admins — '.$db->getSqlError()['message']];
    $sql = 'INSERT INTO '.PREFIX_DB.'_users (id, name, email, website, avatar, regdate, password, lang, ip, sig, block, warnings, field)'
        .' VALUES (NULL, :name, :email, :website, \'\', now(), :pass, :lang, :ip, \'\', \'\', \'\', \'\')';
    $pars = ['name' => $adm['name'], 'email' => $adm['mail'], 'website' => $state['site']['url'], 'pass' => $hash, 'lang' => $state['lang'], 'ip' => $pars['ip']];
    if ($adm['user'] === '1') {
        $open = $db->setSqlBegin();
        $made = $open && $db->getSqlQuery($sql, $pars) && (new Route($db))->addRoute('user', intval($db->getSqlLastId()), 'users', $adm['name']) !== '';
        $text = $made ? '' : $db->getSqlError()['message'];
        $made = $made && $db->setSqlCommit();
        if ($open && !$made) $db->setSqlRollback();
        if (!$made) return ['task' => '', 'fail' => _ERROR.': '.PREFIX_DB.'_users — '.$text];
    }
    $state['note'] = addNodeProfiles($aid);
    try {
        addRouteBacklog();
    } catch (Throwable $err) {
        return ['task' => '', 'fail' => _ERROR.': '.PREFIX_DB.'_routes — '.$err->getMessage()];
    }
    foreach (getPrivateRoots() as $dir) setPrivateMark($dir);
    setCookies('language', time() + intval($conf['user_c_t'] ?? 0), $state['lang']);
    return ['task' => _ADMIN.' '.$adm['name'].' — '.(($adm['user'] === '1') ? _SETUP_ADM_USER : _SETUP_ADM_MADE), 'fail' => ''];
}

# The closing stop: the installer deletes itself and the session ends; a file the server keeps leaves the warning the panel monitor shows as well
function setSetupDone(array &$state): array {
    global $conf;
    $site = $state['site'];
    $panel = $site['url'].'/'.$site['panel'].'.php';
    $mods = array_filter(getSetupSource('modules.php')['modules'] ?? [], fn(mixed $v): bool => is_array($v) && ($v['active'] ?? '') === '1' && ($v['type'] ?? '1') === '1');
    $lead = sprintf(_SETUP_FINISHED, $conf['version'], $state['made'], count($mods), count(getSetupLangs()));
    $gone = is_writable(PUBLIC_DIR) && unlink(__FILE__);
    $alert = getSetupAlert(sprintf(_SETUP_PANEL_URL, $panel), 'info').getSetupAlert($gone ? _SETUP_GONE : _DELSETUP, $gone ? 'success' : 'warn');
    if ($state['note'] !== '') $alert .= getSetupAlert($state['note'], 'warn');
    $state = [];
    return ['lead' => $lead, 'alert_html' => $alert, 'is_done' => true, 'site_url' => $site['url'].'/', 'site_text' => _SETUP_OPENSITE, 'panel_url' => $panel,
        'panel_text' => _SETUP_OPENPANEL];
}

# Refuse an installed site with no form, write or delete; a kept installer, a development copy too, leaves the warning of the panel instead
function setSetupShut(): void {
    setSetupState([]);
    setSetupPage(-1, ['title' => _SETUP_TITLE, 'alert_html' => getSetupAlert(_SETUP_INSTALLED, 'warn')]);
}

# One alert of the admin theme; the text is escaped here, because a refusal can carry what the visitor typed or what the database server answered
function getSetupAlert(string $text, string $type): string {
    global $tpl;
    return $tpl->getHtmlFrag('alert', ['type' => $type, 'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8')]);
}

# The form rows of the database, site and administrator stops, filled from the answers of the session; no password is ever printed back
function getSetupRows(int $stop, array $state): array {
    global $tpl;
    $base = $state['base'];
    $site = $state['site'];
    $adm = $state['admin'];
    $list = [
        2 => [
            ['xhost', _SETUP_SERVER, $base['host'] ?? '', ['is_required' => true]],
            ['xuname', _USER, $base['uname'] ?? '', ['is_required' => true, 'autocomplete_attr' => 'off']],
            ['xpass', _PASSWORD, '', ['itype' => 'password', 'autocomplete_attr' => 'new-password']],
            ['xname', _SETUP_DBNAME, $base['name'] ?? '', ['is_required' => true]],
            ['xprefix', _SETUP_PREFIX, $base['prefix'] ?? '', ['is_required' => true, 'maxlength_num' => 32], _SETUP_PREFIX_TIP],
        ],
        3 => [
            ['sname', _SITENAME, $site['name'] ?? '', ['is_required' => true, 'maxlength_num' => 255]],
            ['surl', _ADDRESS, $site['url'] ?? '', ['itype' => 'url', 'is_required' => true]],
            ['spanel', _SETUP_PANEL, $site['panel'] ?? '', ['is_required' => true, 'autocomplete_attr' => 'off'], sprintf(_SETUP_PANEL_TIP, $site['panel'] ?? '')],
        ],
        4 => [
            ['aname', _NICKNAME, $adm['name'] ?? '', ['is_required' => true, 'maxlength_num' => 25, 'autocomplete_attr' => 'username']],
            ['amail', _EMAIL, $adm['mail'] ?? '', ['itype' => 'email', 'is_required' => true, 'maxlength_num' => 255]],
            ['apwd', _PASSWORD, '', ['itype' => 'password', 'is_required' => true, 'autocomplete_attr' => 'new-password']],
            ['apwd2', _RETYPEPASSWORD, '', ['itype' => 'password', 'is_required' => true, 'autocomplete_attr' => 'new-password']],
        ],
    ];
    $rows = [];
    foreach ($list[$stop] ?? [] as $item) {
        [$name, $label, $value, $opts] = $item;
        $hint = $item[4] ?? '';
        $field = $tpl->getHtmlFrag('input', ['name_attr' => $name, 'value_attr' => $value, 'input_id' => $name, 'describedby' => ($hint !== '') ? $name.'-tip' : ''] + $opts);
        $rows[] = ['label' => $label, 'input_id' => $name, 'field_html' => $field, 'hint' => $hint, 'hint_id' => ($hint !== '') ? $name.'-tip' : ''];
    }
    if ($stop === 4) {
        $items = '';
        foreach (['1' => _YES, '0' => _NO] as $val => $text) {
            $pick = ($adm['user'] ?? '1') === (string)$val;
            $items .= $tpl->getHtmlFrag('radio', ['name_attr' => 'auser', 'value_attr' => (string)$val, 'label_text' => $text, 'is_checked' => $pick]);
        }
        $field = $tpl->getHtmlFrag('block-content', ['switch' => true, 'is_radio_group' => true, 'describedby' => 'auser-tip', 'content' => $items]);
        $rows[] = ['label' => _SETUP_ACCOUNT, 'input_id' => '', 'field_html' => $field, 'hint' => _SETUP_ACCOUNT_TIP, 'hint_id' => 'auser-tip'];
    }
    return $rows;
}

# The data of one stop for the page: its lead and the block it carries, the language tiles, the check rows, the form rows or the run, and its buttons
function getSetupView(int $stop, array $state): array {
    $leads = [_SETUP_LANG_LEAD, _SETUP_SRV_LEAD, _SETUP_DB_LEAD, _SETUP_SITE_LEAD, _SETUP_ADM_LEAD, _SETUP_RUN_LEAD];
    $view = ['lead' => $leads[$stop] ?? ''];
    if ($stop === 0) {
        $view['langs'] = [];
        $now = getSetupLang($state);
        foreach (getSetupLangs() as $code => [$name, $flag]) {
            $icon = 'templates/admin/images/flags/'.$flag.'.svg';
            $view['langs'][] = ['name_attr' => 'lang', 'value_attr' => $code, 'label' => $name, 'flag' => $icon, 'is_checked' => $code === $now];
        }
        $view['next_text'] = _NEXT;
    } elseif ($stop === 1) {
        $view['checks'] = getSetupChecks();
        if (in_array(true, array_column($view['checks'], 'is_fail'), true)) $view['lead'] = _SETUP_SRV_FAIL;
    } elseif ($stop >= 2 && $stop <= 4) {
        $view['rows'] = getSetupRows($stop, $state);
    } elseif ($stop === 5) {
        $view += ['is_run' => true, 'percent' => intval(round(max(0, $state['part']) * 100 / max(1, $state['count']))), 'task' => _SETUP_PREPARE];
    }
    if ($stop === 2) $view['probe_text'] = _SETUP_PROBE;
    if ($stop === 4) $view += ['next_text' => _SETUP_RUN, 'is_install' => true];
    if ($stop >= 1 && $stop <= 4) $view += ['back_text' => _BACK, 'next_text' => _NEXT];
    return $view;
}

# The stop a button leads to: next checks the answers, probe asks the database, back keeps what was typed and Install starts the run after its preflight
function getSetupMove(int $stop, string $go, array &$state): array {
    if ($go === 'back') {
        checkSetupInput($stop, $state, false);
        return [max(0, $stop - 1), ['is_back' => true]];
    }
    if ($go !== 'next' && ($go !== 'probe' || $stop !== 2)) return [$stop, []];
    if ($stop === 5) {
        if ($state['fail'] !== '') return [4, ['alert_html' => getSetupAlert($state['fail'], 'error')]];
        return ($state['part'] < $state['count']) ? [5, []] : [6, setSetupDone($state)];
    }
    $fail = checkSetupInput($stop, $state, true);
    if ($fail === '' && $stop === 1 && in_array(true, array_column(getSetupChecks(), 'is_fail'), true)) return [1, []];
    if ($fail === '' && $stop === 2) {
        $probe = getSetupProbe($state['base']);
        $alert = ($probe['fail'] !== '') ? getSetupAlert($probe['fail'], 'error') : '';
        if ($go === 'probe' || $alert !== '') return [2, ['checks' => $probe['checks'], 'alert_html' => $alert]];
    }
    if ($fail === '' && $stop === 4) $fail = checkSetupRun($state);
    if ($fail !== '') return [$stop, ['alert_html' => getSetupAlert($fail, 'error')]];
    if ($stop === 4) $state = ['part' => 0, 'count' => count(getSetupParts()), 'busy' => -1, 'fail' => '', 'made' => 0] + $state;
    $state['reach'] = max($state['reach'], $stop + 1);
    return [$stop + 1, []];
}

# Answer one request of the stops; a run under way leaves the stops alone, so a second tab or a reload never changes an answer the run uses
function setSetupStep(array $state): void {
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post && !checkSetupToken($state)) $state = [];
    if (($state['token'] ?? '') === '') {
        $state = getSetupFresh();
        setSetupState($state);
        setSetupPage(0, getSetupView(0, $state) + ['alert_html' => $post ? getSetupAlert(_ACCESSDENIED, 'error') : '']);
        return;
    }
    $go = $post ? getSetupVar('post', 'go', 'var') : '';
    $stop = min(intval(getSetupVar('post', 'stop', 'num')), $state['reach']);
    if ($state['part'] >= 0 && ($state['fail'] === '' || !$post)) [$stop, $go] = [5, 'next'];
    [$next, $extra] = getSetupMove($stop, $go, $state);
    setSetupState($state);
    setSetupPage($next, $extra + ($next === 6 ? [] : getSetupView($next, $state)));
}

# Render one of the seven stops on the card of the admin theme; the refusal of an installed site is a stop outside them
function setSetupPage(int $stop, array $view): void {
    $names = [_LANGUAGE, _SETUP_SERVER, _DATABASE, _SITE, _ADMIN, _SETUP_INSTALL, _SETUP_DONE];
    $hide = [['name_attr' => 'token', 'value_attr' => getSetupState()['token']], ['name_attr' => 'stop', 'value_attr' => (string)$stop]];
    setCardPage(_SETUP_TITLE, $names, $stop, $view + ['action' => 'setup.php', 'hidden' => $hide, 'road_text' => _SETUP_PATH]);
}

# The installer renders before the core and without a site address, so the root of its addresses is the folder it is served from
define('HOME_PATH', rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/').'/');
$conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
require_once BASE_DIR.'/core/version.php';
$conf['version'] = SL_VERSION.' '.SL_CODENAME;
date_default_timezone_set($conf['gtime']);
ini_set('display_errors', $conf['security']['error'] > 0 ? '1' : '0');
error_reporting([0, E_ALL ^ E_NOTICE, E_ALL][intval($conf['security']['error'])] ?? 0);
set_time_limit(300);
require_once BASE_DIR.'/core/admin.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$setup = getSetupState();
if (getSetupVar('post', 'go', 'var') === 'part' && $setup['part'] >= 0 && $setup['part'] === $setup['count'] - 1) {
    $ahash = (string)($setup['admin']['hash'] ?? '');
    $apart = getSetupNext($setup);
    $setup['admin']['hash'] = '';
    setSetupState($setup);
    if ($apart < 0) setSetupJson(['more' => false]);
    define('MODULE_FILE', true);
    require_once BASE_DIR.'/core/system.php';
    getLang('admin');
    $setup = getSetupState();
    $aout = addSetupAdmin($setup, $ahash);
    setSetupJson(setSetupNext($setup, $aout));
}
define('FUNC_FILE', true);
require_once BASE_DIR.'/core/classes/filemanager.php';
require_once BASE_DIR.'/core/classes/logger.php';
require_once BASE_DIR.'/core/classes/pdo.php';
require_once BASE_DIR.'/core/classes/template.php';
$slang = getSetupLang($setup);
require_once BASE_DIR.'/lang/'.$slang.'.php';
require_once BASE_DIR.'/admin/lang/'.$slang.'.php';
$tpl = new Template('admin');
if (checkSetupDone($setup)) setSetupShut();
elseif (getSetupVar('post', 'go', 'var') === 'part') setSetupJson(setSetupRun($setup));
else setSetupStep($setup);
