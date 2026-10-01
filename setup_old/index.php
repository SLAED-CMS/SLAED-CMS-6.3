<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('SETUP_FILE')) die('Illegal File Access');
define('FUNC_FILE', true);
define('CONFIG_DIR', BASE_DIR.'/config');
define('LOGS_DIR', BASE_DIR.'/storage/logs');

$conf = require CONFIG_DIR.'/global.php';
$conf = array_merge($conf, require CONFIG_DIR.'/security.php');

# The SQL splitter of the administration panel; it defines functions only, so the installer can borrow it before the rest of the system exists
require_once BASE_DIR.'/core/admin.php';
# The shared configuration lock the runtime rebuilds config/local.php under; the class is self-contained and only needs LOGS_DIR
require_once BASE_DIR.'/core/classes/filemanager.php';
# The logger the database facade reports a refused connection to; the class is self-contained and only needs LOGS_DIR
require_once BASE_DIR.'/core/classes/logger.php';

if ($conf['security']['error'] == 2) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} elseif ($conf['security']['error'] == 1) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL ^ E_NOTICE);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

if (function_exists('set_time_limit')) set_time_limit(1800);
$host = getenv('HTTP_HOST') ? getenv('HTTP_HOST') : getenv('SERVER_NAME');
$url = getProtocol().'://'.$host;
$clang = isset($_COOKIE[$conf['user_c'].'-lang']) ? filterVar($_COOKIE[$conf['user_c'].'-lang']) : 'en';
$op = (isset($_REQUEST['op'])) ? filterVar($_REQUEST['op']) : '';

require_once BASE_DIR.'/lang/'.$clang.'.php';
require_once BASE_DIR.'/setup/lang/'.$clang.'.php';

# The failed codes in a row after which the key config/setup.unlock is removed, so a guessed code needs a new key from the owner every few attempts
const SETUPFAIL = 5;

if (version_compare(PHP_VERSION, '8.4.0', '<')) setExit(_PHPSETUP);
foreach (['mbstring', 'pdo', 'json'] as $ext) {
    if (!extension_loaded($ext)) setExit(_EXTSETUP.': '.$ext);
}
# An installed site keeps the installer shut: no form, no secret, no write and no database until the owner uploads config/setup.unlock with a code of eight characters or more
# Every form of the installer asks for that code before it shows the connection data or writes, and a clean run removes it
# The first request that meets the code in plain text replaces it by its password hash, so a server that hands config/ out as files hands out no usable code
# The hash may carry the count of failed codes on a second line; a key that already counts the last failure is removed and the installer stays locked
$dbconf = getSetupBase();
$skey = CONFIG_DIR.'/setup.unlock';
$scode = (($dbconf['name'] ?? '') !== '' && is_file($skey)) ? trim((string)file_get_contents($skey)) : '';
if (($dbconf['name'] ?? '') !== '' && strlen($scode) < 8) setExit(_SETUPLOCK);
$sline = explode("\n", $scode, 2);
if (password_get_info($sline[0])['algo'] !== null) {
    $scode = $sline[0];
    if (intval($sline[1] ?? 0) >= SETUPFAIL) {
        unlink($skey);
        setExit(_SETUPKEYGONE);
    }
} elseif ($scode !== '') {
    $scode = password_hash($scode, PASSWORD_DEFAULT);
    if (!is_writable($skey) || file_put_contents($skey, $scode, LOCK_EX) !== strlen($scode)) setExit(_FILE.' config/setup.unlock '._SERRORPERM);
}
# The panel file the site runs now: a 6.2 site keeps its name in config/config_security.php until the update carries it over, a 6.3 site in config/security.php
$spanel = filterVar((string)(getSetupConfig(CONFIG_DIR.'/config_security.php')['afile'] ?? $conf['security']['afile'] ?? '')) ?: 'admin';
$copy = '<a href="https://slaed.net" target="_blank" title="SLAED CMS">SLAED CMS</a> © 2005-'.date('Y').' Eduard Laas. Released under MIT License.';

# Saving configurations to a file; every scalar is stored as a string unless $raw keeps the native types the definitions of the extra fields are made of
# The answer says whether the whole file was written: a file or a config/ that is not writable is left as it was and answers false, which every caller reports
function setConfigFile(string $fp, array $arr, array $act = [], bool $raw = false): bool {
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

# Include one configuration source in a scope of its own with its output swallowed and answer its values
# The answer is the array a 6.3 source returns, the first array a 6.2 source assigns to a variable, or an empty array for a missing file or a source without settings
function getSetupConfig(string $file): array {
    if (!is_file($file)) return [];
    ob_start();
    $data = (static function (string $path): array {
        $back = include $path;
        if (is_array($back)) return $back;
        unset($path, $back);
        foreach (get_defined_vars() as $val) if (is_array($val)) return $val;
        return [];
    })($file);
    ob_end_clean();
    return $data;
}

# The connection settings of the site from config/db.php, written by 6.3 as the db area or by 6.2 as the variable $confdb, empty while the file does not exist
function getSetupBase(): array {
    $data = getSetupConfig(CONFIG_DIR.'/db.php');
    return is_array($data['db'] ?? null) ? $data['db'] : $data;
}

function getProtocol(): string {
    if ($_SERVER['SERVER_PORT'] == 443) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
        $proto = 'https';
    } elseif (
        !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on'
    ) {
        $proto = 'https';
    } elseif (strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, 5)) == 'https') {
        $proto = 'https';
    } else {
        $proto = 'http';
    }
    return $proto;
}

function getRandomString(int $m): string {
    $pass = '';
    for ($ix = 0; $ix < $m; $ix++) {
        $te = random_int(48, 122);
        if (($te > 57 && $te < 65) || ($te > 90 && $te < 97)) $te = $te - 9;
        $pass .= chr($te);
    }
    return $pass;
}

function getCrypt(string $pass): string {
    $crypt = md5('0a958d066ab41444be55359c31702bcf'.$pass);
    return $crypt;
}

# Run one SQL file of the installer with its placeholders filled and answer a report row per table statement and per failed one; a DELETE names its removed rows
function getSqlFile(string $file, string $prefix, string $engine, string $charset, string $collate, $db): string {
    $file = BASE_DIR.'/'.$file;
    if (!file_exists($file)) return '';

    $parsed = getSqlbatch((string)file_get_contents($file));
    $output = '';

    if ($parsed['error'] !== '') return getInfo(basename($file), false);

    foreach ($parsed['statements'] as $query) {
        $query = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$prefix, $engine, $charset, $collate], $query);
        $result = $db->getSqlQuery($query);
        $info = getSqlinfo($query);

        $gone = ($result && $info['type'] === 'DELETE') ? ' (rows removed: '.intval($db->getSqlRowCount($result)).')' : '';
        if ($info['table'] !== '') $output .= getInfo($info['table'].$gone, $result);
        elseif (!$result) $output .= getInfo($info['type'], $result);
    }

    return $output;
}

function getIp(): string {
    if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (!empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '0.0.0.0';
    }
    return $ip;
}

function getLang(string $con): string {
    $langs = ['en' => _ENGLISH, 'fr' => _FRENCH, 'de' => _GERMAN, 'pl' => _POLISH, 'ru' => _RUSSIAN, 'uk' => _UKRAINIAN];
    $out = strtr($con, $langs);
    return $out;
}

function getInfo(string $table, mixed $id): string {
    $text = '<tr><td>'._TABLE.':</td><td>'.$table.' '.(($id) ? '</td><td><span class="sl_green">'._OK.'</span></td>' : '<td><span class="sl_red">'._ERROR.'</span></td>').'</tr>';
    return $text;
}

function setHead(): void {
    global $title, $conf;
    echo '<!doctype html>'."\n"
    .'<html lang="'.substr(_LOCALE, 0, 2).'">'."\n"
    .'<head>'."\n"
    .'<meta charset="'._CHARSET.'">'."\n"
    .'<title>'._SETUP_SLAED.' - '.$title.'</title>'."\n"
    .'<meta name="resource-type" content="document">'."\n"
    .'<meta name="document-state" content="dynamic">'."\n"
    .'<meta name="distribution" content="global">'."\n"
    .'<meta name="author" content="'.$conf['sitename'].'">'."\n"
    .'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'."\n"
    .'<link rel="stylesheet" href="setup/templates/style.css">'."\n"
    .'</head>'."\n"
    .'<body id="page_bg">'."\n"
    .'<div id="wrapper">'
    .'<div id="header">'
    .'<div id="header-left">'
    .'<div id="header-right">'
    .'<div id="logo">'
    .'<img src="setup/templates/images/logotype.png" alt="'.$title.'">'
    .'</div>'
    .'</div>'
    .'</div>'
    .'</div>'
    .'<div id="shadow-l">'
    .'<div id="shadow-r">'
    .'<div id="container">'
    .'<h1 class="btitle">'.$title.'</h1>';
}

function setFoot(): void {
    global $copy;
    echo '</div>'
    .'</div>'
    .'</div>'
    .'<div id="footer">'
    .'<div id="footer-r">'
    .'<div id="footer-l">'
    .'<div id="copyright">'.$copy.'</div>'
    .'</div>'
    .'</div>'
    .'</div>'
    .'</div>'."\n"
    .'</body>'."\n"
    .'</html>';
}

function setExit(string $msg, string $typ = ''): never {
    global $conf;
    $cont = '<!doctype html>'."\n"
    .'<html lang="'.substr(_LOCALE, 0, 2).'">'."\n"
    .'<head>'."\n"
    .'<meta charset="'._CHARSET.'">'."\n"
    .'<title>'._SETUP_SLAED.'</title>'."\n"
    .'<meta name="author" content="'.$conf['sitename'].'">'."\n"
    .'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'."\n";
    $cont .= ($typ) ? '<meta http-equiv="refresh" content="5; url='.$conf['homeurl'].'/index.php">'."\n" : '';
    $cont .= '<link rel="stylesheet" href="setup/templates/style.css">'."\n"
    .'</head>'."\n"
    .'<body>'."\n"
    .'<div style="margin: 25%;">'."\n"
    .'<div style="text-align: center;"><img src="setup/templates/images/logotype.png" alt="'.$conf['sitename'].'" title="'.$conf['sitename'].'"></div>'."\n"
    .'<div style="margin-top: 50px; font: 18px Arial, Tahoma, sans-serif, Verdana; color: #1a4674; font-weight: bold; text-align: center;">'.$msg.'</div>'."\n"
    .'<div style="margin-top: 50px; text-align: center;">'._GOBACK.'</div>'."\n"
    .'</div>'."\n"
    .'</body>'."\n"
    .'</html>';
    die($cont);
}

function filterVar(string $vl): string {
    return preg_match('#[^a-zA-Z0-9_\-]#', $vl) ? '' : $vl;
}

# Refuse the installer while a configuration file every branch writes, or config/ for one it creates, is not writable by PHP
# The permissions are never changed, because a file opened to everyone hands the database password to every local account of a shared host
function checkWritableConfig(string $file): void {
    if (!is_writable(is_file(CONFIG_DIR.'/'.$file) ? CONFIG_DIR.'/'.$file : CONFIG_DIR)) setExit(_FILE.' config/'.$file.' '._SERRORPERM);
}

# Check a posted code against the hash of config/setup.unlock under an exclusive lock of that file and keep the count of failures in a row on its second line
# A match clears the count; the failure that reaches SETUPFAIL removes the key and stops, so the installer stays locked until the owner uploads a new one
function checkSetupCode(string $code): bool {
    global $skey;
    if (!is_file($skey)) setExit(_SETUPLOCK);
    $file = is_writable($skey) ? fopen($skey, 'r+') : false;
    if (!$file) setExit(_FILE.' config/setup.unlock '._SERRORPERM);
    flock($file, LOCK_EX);
    $line = explode("\n", trim((string)stream_get_contents($file)), 2);
    $good = password_verify($code, $line[0]);
    $fail = $good ? 0 : intval($line[1] ?? 0) + 1;
    $text = $line[0].($fail ? "\n".$fail : '');
    $done = ftruncate($file, 0) && rewind($file) && fwrite($file, $text) === strlen($text) && fflush($file);
    flock($file, LOCK_UN);
    fclose($file);
    if ($fail >= SETUPFAIL) {
        unlink($skey);
        setExit(_SETUPKEYGONE);
    }
    if (!$done) setExit(_FILE.' config/setup.unlock '._SERRORPERM);
    return $good;
}

function language(): void {
    global $title, $clang;
    $title = _LANG;
    setHead();
    $cont = '<table class="sl_table">';
    $langlist = array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), glob('setup/lang/*.php'));
    sort($langlist);
    $col = 3;
    $ix = 1;
    $tdwidth = intval(100/$col);
    foreach ($langlist as $val) {
        $altlang = getLang($val);
        if (($ix - 1) % $col == 0) $cont .= '<tr>';
        $cont .= '<td style="width: '.$tdwidth.'%;" class="sl_center"><a href="setup.php?op=lang&amp;id='.$val.'" title="'.$altlang.'">'
        .'<img src="setup/templates/images/'.$val.'.png" alt="'.$altlang.'"><br><b>'.$altlang.'</b></a></td>';
        if ($ix % $col == 0) $cont .= '</tr>'."\n";
        $ix++;
    }
    if ($clang) {
        $cont .= '<tr><td colspan="'.$col.'" class="sl_center"><form action="setup.php" method="post"><input type="hidden" name="op" value="config">'
        .'<input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></form></td></tr>';
    }
    $cont .= '</table>';
    echo $cont;
    setFoot();
}

function lang(): void {
    global $conf, $url;
    $time = time() + 3600;
    $lang = (preg_match('#[^a-zA-Z0-9_]#', $_GET['id'])) ? 'en' : $_GET['id'];
    $url = parse_url($url);
    $sec = ($url['scheme'] == 'http') ? false : true;
    $options = ['expires' => $time, 'path' => '/', 'domain' => $url['host'], 'secure' => $sec, 'httponly' => true, 'samesite' => 'Lax'];
    setcookie($conf['user_c'].'-lang', $lang, $options);
    header('Location: setup.php');
}

function config(): void {
    global $title, $conf, $scode, $spanel;
    $title = _CONFIG;
    $xcode = is_string($_POST['xcode'] ?? null) ? $_POST['xcode'] : '';
    if ($scode !== '' && ($xcode === '' || !checkSetupCode($xcode))) {
        setHead();
        echo '<form action="setup.php" method="post">'
        .'<table class="sl_table">'
        .(($xcode !== '') ? '<tr><td colspan="2" class="sl_center"><span class="sl_red">'._SETUPCODE.'</span></td></tr>' : '')
        .'<tr><td>'._CONF_11.':</td><td><input type="password" name="xcode" value="" class="sl_cinput" placeholder="'._CONF_11.'" autocomplete="off" required></td></tr>'
        .'<tr><td colspan="2" class="sl_center">'._GOBACK.' <input type="hidden" name="op" value="config"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
        setFoot();
        return;
    }
    foreach (['db.php', 'global.php', 'security.php'] as $name) checkWritableConfig($name);
    $conf['db'] = getSetupBase() + array_fill_keys(['host', 'uname', 'pass', 'name', 'prefix'], '');
    $xhost = ($conf['db']['host']) ? $conf['db']['host'] : 'localhost';
    $xuname = ($conf['db']['uname']) ? $conf['db']['uname'] : '';
    $hint = ($conf['db']['name'] !== '') ? '<div class="sl_small">'._CONF_3_INFO.'</div>' : '';
    $xname = ($conf['db']['name']) ? $conf['db']['name'] : '';
    $xprefix = ($conf['db']['prefix']) ? $conf['db']['prefix'] : getRandomString('10');
    $xafile = ($spanel !== 'admin') ? $spanel : strtolower(getRandomString('10'));
    $info = sprintf(_CONF_10_INFO, strtolower(getRandomString('10')));
    setHead();
    echo '<form action="setup.php" method="post">'
    .'<table class="sl_table">'
    .'<tr><td><label for="new">'._SETUP_NEW.' SLAED CMS '.$conf['version'].':</label></td><td><input type="radio" id="new" name="setup" value="new" checked></td></tr>'
    .'<tr><td><label for="update4_1">'._SUPDATE.' SLAED CMS 4.0 Pro > 4.1 Pro:</label></td><td><input type="radio" id="update4_1" name="setup" value="update4_1"></td></tr>'
    .'<tr><td><label for="update4_2">'._SUPDATE.' SLAED CMS 4.1 Pro > 4.2 Pro:</label></td><td><input type="radio" id="update4_2" name="setup" value="update4_2"></td></tr>'
    .'<tr><td><label for="update4_3">'._SUPDATE.' SLAED CMS 4.2 Pro > 4.3 Pro:</label></td><td><input type="radio" id="update4_3" name="setup" value="update4_3"></td></tr>'
    .'<tr><td><label for="update5_0">'._SUPDATE.' SLAED CMS 4.3 Pro > 5.0 Pro:</label></td><td><input type="radio" id="update5_0" name="setup" value="update5_0"></td></tr>'
    .'<tr><td><label for="update5_1">'._SUPDATE.' SLAED CMS 5.0 Pro > 5.1 Pro:</label></td><td><input type="radio" id="update5_1" name="setup" value="update5_1"></td></tr>'
    .'<tr><td><label for="update6_0">'._SUPDATE.' SLAED CMS 5.3 Pro > 6.1 Pro:</label></td><td><input type="radio" id="update6_0" name="setup" value="update6_0"></td></tr>'
    .'<tr><td><label for="update6_2">'._SUPDATE.' SLAED CMS 6.1 Pro > 6.2 Pro:</label></td><td><input type="radio" id="update6_2" name="setup" value="update6_2"></td></tr>'
    .'<tr><td><label for="update6_3">'._SUPDATE.' SLAED CMS 6.2 Pro > 6.3 Phoenix:</label></td><td><input type="radio" id="update6_3" name="setup" value="update6_3"></td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._CONF_1.':</td><td><input type="text" name="xhost" value="'.$xhost.'" class="sl_cinput" placeholder="'._CONF_1.'" required></td></tr>'
    .'<tr><td>'._CONF_2.':</td><td><input type="text" name="xuname" value="'.$xuname.'" class="sl_cinput" placeholder="'._CONF_2.'" required></td></tr>'
    .'<tr><td>'._CONF_3.':'.$hint.'</td><td><input type="password" name="xpass" value="" class="sl_cinput" placeholder="'._CONF_3.'" autocomplete="new-password"></td></tr>'
    .'<tr><td>'._CONF_4.':</td><td><input type="text" name="xname" value="'.$xname.'" class="sl_cinput" placeholder="'._CONF_4.'" required></td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._CONF_9.':</td><td><input type="text" name="xprefix" value="'.$xprefix.'" class="sl_cinput" placeholder="'._CONF_9.'" required></td></tr>'
    .'<tr><td>'._CONF_10.':<div class="sl_small">'.$info.'</div></td>'
    .'<td><input type="text" name="xafile" value="'.$xafile.'" class="sl_cinput" placeholder="'._CONF_10.'" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center">'._GOBACK.' <input type="hidden" name="xcode" value="'.htmlspecialchars($xcode, ENT_QUOTES).'">'
    .'<input type="hidden" name="op" value="save"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></td></tr>'
    .'</table></form>';
    setFoot();
}

# Check what the 6.3 data update or a clean installation needs before anything is changed and answer the refusal, or an empty string when the run may start
# The server has to enforce CHECK constraints and to know RENAME COLUMN and RENAME INDEX of the schema file, which MariaDB has from 10.5.2 on
# A clean installation needs a database without a table of its prefix; the update needs the users and admins tables of the prefix it names
# Every table of a points, ratings, fields, Node, private message or newsletter transaction has to be InnoDB; nothing is converted, and the branch closes the site itself
function checkUpdateBase(Database $db, string $prefix, bool $fresh = false): string {
    [$ver] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    $min = (stripos((string)$ver, 'mariadb') !== false) ? '10.5.2' : '8.0.16';
    if (version_compare(preg_replace('/[^0-9.].*$/', '', (string)$ver), $min, '<')) return sprintf(_SETUPVER, $ver, $min);
    $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND ';
    if ($fresh) {
        [$num] = $db->getSqlRow($db->getSqlQuery($sql.'table_name LIKE :pre', ['pre' => str_replace('_', '\_', $prefix).'\_%']));
        return ($num > 0) ? sprintf(_SETUPTAKEN, $prefix) : '';
    }
    [$num] = $db->getSqlRow($db->getSqlQuery($sql.'table_name IN (:users, :admins)', ['users' => $prefix.'_users', 'admins' => $prefix.'_admins']));
    if ($num < 2) return sprintf(_SETUPTABLES, $prefix);
    $list = [];
    $tabs = ['users', 'admins', 'comment', 'forum', 'favorites', 'user_oauth', 'points', 'rating_targets', 'rating_actors', 'rating_votes', 'categories', 'voting',
        'newsletter', 'privat'];
    foreach ($tabs as $key => $name) $list['t'.$key] = $prefix.'_'.$name;
    $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (:'.implode(', :', array_keys($list)).')'
        .' AND engine IS NOT NULL AND engine != \'InnoDB\'';
    $res = $db->getSqlQuery($sql, $list);
    $fix = [];
    while ($res && ([$name] = $db->getSqlRow($res))) $fix[] = 'ALTER TABLE `'.$name.'` ENGINE=InnoDB;';
    return $fix ? _SETUPINNODB.' '.implode(' ', $fix) : '';
}

# Write one file of the update backup through a temporary file and a rename, so a reader never meets a half-written snapshot or manifest
function setUpdateBackup(string $path, string $text): bool {
    $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
    return file_put_contents($temp, $text, LOCK_EX) === strlen($text) && rename($temp, $path);
}

# The points unit of the 6.3 data update: keep the starting balances as a hashed snapshot, carry users.point into points.active and leave the mark that opens the subsystem
# The unit resumes from its manifest: verified is skipped, applying and prepared continue
# Journal rows without a manifest stop it, because a current balance is never taken for a starting one
function setUpdatePoints(Database $db, string $prefix): string {
    $dir = BASE_DIR.'/storage/backup/update/points';
    $file = $dir.'/manifest.json';
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($info)) {
        [$rows] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$prefix.'_points`'));
        if ($rows > 0 || isset($mark['points'])) return getInfo('points: the journal already has rows and no manifest exists, the unit is stopped', false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('points: '.$dir.' could not be created', false);
        $list = [];
        $done = $db->setSqlBegin();
        $res = $done ? $db->getSqlQuery('SELECT id, points FROM `'.$prefix.'_users` ORDER BY id ASC') : false;
        while ($res && ([$uid, $sum] = $db->getSqlRow($res))) $list[(string)$uid] = intval($sum);
        if ($done) $db->setSqlCommit();
        $text = (string)json_encode($list);
        if (!$res || !setUpdateBackup($dir.'/balances.json', $text)) return getInfo('points: the snapshot of the balances could not be written', false);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => 0, 'count' => count($list), 'source' => ['balances.json' => hash('sha256', $text)], 'target' => []];
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('points: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        $same = is_file($dir.'/balances.json') && hash_file('sha256', $dir.'/balances.json') === ($info['source']['balances.json'] ?? '');
        if (!$same) return getInfo('points: the snapshot does not match its manifest', false);
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('points: the manifest could not be written', false);
        $users = getSetupConfig(CONFIG_DIR.'/users.php')['users'] ?? [];
        $point = getSetupConfig(CONFIG_DIR.'/points.php')['points'] ?? [];
        $flag = isset($users['point']) ? ($users['point'] ? '1' : '0') : ($point['active'] ?? '');
        $moved = $flag !== ($point['active'] ?? '');
        $stale = isset($users['point']) || isset($users['points']);
        $point['active'] = $flag;
        unset($users['point'], $users['points']);
        if (count($point['actions'] ?? []) !== 14 || !in_array($point['active'], ['0', '1'], true)) return getInfo('points: config/points.php is not a valid points scope', false);
        if ($moved && !setConfigFile('points.php', $point)) return getInfo('points: config/points.php could not be written, run the update again', false);
        if ($stale && !setConfigFile('users.php', $users)) return getInfo('points: config/users.php could not be written, run the update again', false);
        $info['target'] = ['points.php' => hash_file('sha256', CONFIG_DIR.'/points.php'), 'users.php' => hash_file('sha256', CONFIG_DIR.'/users.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('points: the manifest could not be written', false);
    }
    if (!setConfigFile('update.php', ['points' => '6.3.0'] + $mark)) return getInfo('points: config/update.php could not be written, the subsystem stays closed', false);
    return getInfo('points: starting balances kept ('.intval($info['count']).' accounts), the subsystem is open', true);
}

# The ratings unit of the 6.3 data update: keep the aggregate of every remaining target as its starting balance, carry the last participation over and publish the four-key rules
# Nothing is written before the whole preflight passed: a broken aggregate, a broken time or address of a kept row and a broken rule stop the unit with the table and the id
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a row that is already stored has to equal its snapshot
# Rows of the new tables without a manifest stop it, because a current aggregate is never taken for a starting one; rows of polls and of other events are counted and left alone
function setUpdateRatings(Database $db, string $prefix): string {
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'votes', 'tvotes', ''], 'forum' => ['forum', 'ratings', 'score', ' WHERE pid = 0']];
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $bad = [];
    $owners = function () use ($db, $prefix, $maps, &$bad): array|false {
        $list = [];
        foreach ($maps as $scope => [$tab, $cnt, $sum, $cond]) {
            $res = $db->getSqlQuery('SELECT id, '.$cnt.', '.$sum.' FROM `'.$prefix.'_'.$tab.'`'.$cond.' ORDER BY id ASC');
            if (!$res) return false;
            while ([$mid, $num, $tot] = $db->getSqlRow($res)) {
                [$mid, $num, $tot] = [intval($mid), intval($num), intval($tot)];
                if ($num ? ($tot < $num || $tot > 5 * $num) : $tot > 0) $bad[] = $prefix.'_'.$tab.' '.$mid;
                $list[] = [$scope, $mid, $tot, $num];
            }
        }
        return $list;
    };
    $count = function (string $sql) use ($db): int {
        $res = $db->getSqlQuery($sql);
        return $res ? intval($db->getSqlRow($res)[0] ?? -1) : -1;
    };
    $polls = 'SELECT COUNT(*) FROM `'.$prefix.'_rating` WHERE modul = \'voting\'';
    if (!is_array($info)) {
        $rows = 0;
        foreach (['targets', 'actors', 'votes'] as $name) {
            $num = $count('SELECT COUNT(*) FROM `'.$prefix.'_rating_'.$name.'`');
            if ($num < 0) return getInfo('ratings: the table '.$prefix.'_rating_'.$name.' could not be read, the unit is stopped', false);
            $rows += $num;
        }
        if ($rows > 0 || isset($mark['ratings'])) return getInfo('ratings: the new tables already have rows or the mark is set and no manifest exists, the unit is stopped', false);
        $old = is_file(CONFIG_DIR.'/ratings.php') ? ((require CONFIG_DIR.'/ratings.php')['ratings'] ?? []) : [];
        $rules = [];
        $stat = ['voting' => 0, 'foreign' => 0, 'orphan' => 0, 'dropped' => 0];
        foreach ($old as $name => $rule) {
            if (!isset($maps[$name]) && !preg_match('/^node\.[a-z][a-z0-9]{0,19}$/D', $name)) {
                $stat['dropped']++;
                continue;
            }
            $part = is_string($rule) ? explode('|', $rule) : [];
            if (count($part) === 3) $rule = ['active' => $part[1], 'period' => $part[0], 'detail' => $part[2], 'guests' => '1'];
            $good = is_array($rule) && count($rule) === 4 && is_string($rule['period'] ?? null) && preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $rule['period']);
            $good = $good && intval($rule['period']) % 86400 === 0;
            foreach (['active', 'detail', 'guests'] as $key) $good = $good && in_array($rule[$key] ?? null, ['0', '1'], true);
            if ($good) $rules[$name] = ['active' => $rule['active'], 'period' => $rule['period'], 'detail' => $rule['detail'], 'guests' => $rule['guests']];
            else $bad[] = 'config/ratings.php '.$name;
        }
        foreach (array_diff(array_keys($maps), array_keys($old)) as $name) $bad[] = 'config/ratings.php '.$name.' (missing)';
        $done = $db->setSqlBegin();
        $now = $done ? $count('SELECT UNIX_TIMESTAMP()') : -1;
        $list = $done ? $owners() : false;
        $seen = [];
        foreach ($list ?: [] as $row) $seen[$row[0].':'.$row[1]] = true;
        $last = [];
        $res = $done ? $db->getSqlQuery('SELECT id, mid, modul, time, uid, ip FROM `'.$prefix.'_rating` ORDER BY id ASC') : false;
        while ($res && ([$rid, $mid, $mod, $time, $uid, $ip] = $db->getSqlRow($res))) {
            if (!isset($maps[$mod]) || !isset($seen[$mod.':'.$mid])) {
                $stat[$mod === 'voting' ? 'voting' : (isset($maps[$mod]) ? 'orphan' : 'foreign')]++;
                continue;
            }
            $pack = (!intval($uid) && filter_var($ip, FILTER_VALIDATE_IP)) ? inet_pton($ip) : false;
            $norm = $pack === false ? false : inet_ntop($pack);
            $actor = intval($uid) ? 'u:'.intval($uid) : (in_array($norm, [false, '0.0.0.0', '::'], true) ? '' : 'g:'.$norm);
            if ($actor === '' || !preg_match('/^[1-9][0-9]{0,13}$/D', $time) || intval($time) > $now) {
                $bad[] = $prefix.'_rating '.intval($rid);
                continue;
            }
            $last[$mod][intval($mid)][$actor] = max($last[$mod][intval($mid)][$actor] ?? 0, intval($time));
        }
        if ($done) $db->setSqlCommit();
        if (!$done || $now < 1 || $list === false || !$res) return getInfo('ratings: the aggregates and the kept terms could not be read', false);
        if ($bad) return getInfo('ratings: the preflight found broken data, nothing was written ('.count($bad).'): '.implode(', ', array_slice($bad, 0, 50)), false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('ratings: '.$dir.' could not be created', false);
        $terms = [];
        foreach (array_intersect_key($maps, $last) as $scope => $void) {
            ksort($last[$scope]);
            foreach ($last[$scope] as $mid => $acts) {
                ksort($acts, SORT_STRING);
                foreach ($acts as $actor => $time) $terms[] = [$scope, $mid, $actor, $time];
            }
        }
        $text = ['targets.json' => (string)json_encode($list), 'terms.json' => (string)json_encode($terms)];
        $text['rules.json'] = (string)json_encode(['source' => $old, 'rules' => $rules]);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => ['targets' => 0, 'terms' => 0], 'moment' => $now, 'source' => [], 'target' => []];
        $info['count'] = ['targets' => count($list), 'terms' => count($terms)] + $stat;
        foreach ($text as $name => $body) {
            if (!setUpdateBackup($dir.'/'.$name, $body)) return getInfo('ratings: the snapshot '.$name.' could not be written', false);
            $info['source'][$name] = hash('sha256', $body);
        }
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        foreach (['targets.json', 'terms.json', 'rules.json'] as $name) {
            $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === ($info['source'][$name] ?? '');
            if (!$same) return getInfo('ratings: the snapshot '.$name.' does not match its manifest', false);
        }
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
        $sets = ['targets' => ['targets.json', 'rating_targets', ['scope', 'mid', 'base', 'votes'], 2]];
        $sets['terms'] = ['terms.json', 'rating_actors', ['scope', 'mid', 'actor', 'last'], 3];
        foreach ($sets as $kind => [$snap, $tab, $cols, $knum]) {
            $list = json_decode((string)file_get_contents($dir.'/'.$snap), true);
            $keys = array_slice($cols, 0, $knum);
            $cond = implode(' AND ', array_map(fn(string $v): string => $v.' = :'.$v, $keys));
            $more = $kind === 'targets' ? ['created' => intval($info['moment'])] : [];
            $into = array_merge($cols, array_keys($more));
            $sql = 'INSERT INTO `'.$prefix.'_'.$tab.'` ('.implode(', ', $into).') VALUES (:'.implode(', :', $into).')';
            $find = 'SELECT '.implode(', ', $cols).' FROM `'.$prefix.'_'.$tab.'` WHERE '.$cond.' FOR UPDATE';
            for ($pos = intval($info['cursor'][$kind]); $pos < count($list); $pos += 500) {
                $good = $db->setSqlBegin();
                foreach (array_slice($list, $pos, 500) as $row) {
                    $pars = array_combine($cols, $row);
                    $res = $good ? $db->getSqlQuery($find, array_intersect_key($pars, array_flip($keys))) : false;
                    $cur = $res ? $db->getSqlRow($res) : false;
                    if ($cur) $good = array_map('strval', $pars) === array_map('strval', array_intersect_key($cur, $pars));
                    else $good = $res && $db->getSqlQuery($sql, $pars + $more) !== false;
                    if (!$good) break;
                }
                if (!$good || !$db->setSqlCommit()) {
                    $db->setSqlRollback();
                    return getInfo('ratings: a batch of '.$kind.' was refused at row '.$pos.' - a stored row differs from the snapshot or could not be written', false);
                }
                $info['cursor'][$kind] = min($pos + 500, count($list));
                if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
            }
        }
        $real = [];
        foreach ($sets as $kind => [$snap, $tab, $cols]) {
            $list = [];
            $res = $db->getSqlQuery('SELECT '.implode(', ', $cols).' FROM `'.$prefix.'_'.$tab.'` ORDER BY scope ASC, mid ASC'.($kind === 'terms' ? ', actor ASC' : ''));
            while ($res && ($row = $db->getSqlRow($res))) {
                $list[] = [$row['scope'], intval($row['mid']), $kind === 'terms' ? $row['actor'] : intval($row['base']), intval($row[$cols[3]])];
            }
            $real[$snap] = hash('sha256', (string)json_encode($list));
        }
        $list = $owners();
        $same = $real === array_intersect_key($info['source'], $real) && $list !== false && !$bad && hash('sha256', (string)json_encode($list)) === $info['source']['targets.json'];
        $same = $same && $count('SELECT COUNT(*) FROM `'.$prefix.'_rating_votes`') === 0 && $count($polls) === intval($info['count']['voting']);
        if (!$same) return getInfo('ratings: the stored targets, terms, owner aggregates or poll rows do not match the manifest, the rules are not published', false);
        $rules = json_decode((string)file_get_contents($dir.'/rules.json'), true)['rules'] ?? [];
        $same = ((require CONFIG_DIR.'/ratings.php')['ratings'] ?? null) === $rules;
        if (!$same && !setConfigFile('ratings.php', $rules)) return getInfo('ratings: config/ratings.php could not be written, the rules are not published', false);
        $info['target'] = $real + ['ratings.php' => hash_file('sha256', CONFIG_DIR.'/ratings.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
    }
    if (!setConfigFile('update.php', ['ratings' => '6.3.0'] + $mark)) return getInfo('ratings: config/update.php could not be written, the subsystem stays closed', false);
    $stat = $info['count'];
    $text = 'ratings: starting aggregates kept ('.intval($stat['targets']).' targets, '.intval($stat['terms']).' terms), left alone in the old table: '
        .intval($stat['voting']).' poll rows, '
        .intval($stat['foreign']).' rows of other events, '.intval($stat['orphan']).' rows of missing targets; rules dropped: '.intval($stat['dropped']).'; the subsystem is open';
    return getInfo($text, true);
}

# Read the positional 6.2 definitions of one area and answer [named definitions, slot map by position, number of positions]; every refusal goes to $bad and nothing is guessed
# The four slots are caption, content, type and duty: caption 0 switches a position off, content is the default of a text, the comma list of a select or the default of a date
# Keys are field1, field2 and so on by the original position without closing gaps, options are option1, option2 in their original order, and only an exact 1 makes a field required
# Slots and option captions are trimmed; a switched off position with a known type stays in the slot map as off, with the inactive definition it becomes once a row holds data there
function getUpdateRules(string $area, mixed $text, Field $fld, array &$bad): array {
    if (!is_string($text)) {
        $bad[] = 'config/fields.php '.$area.' (not a 6.2 definition string)';
        return [[], [], 0];
    }
    $types = ['1' => 'text', '2' => 'textarea', '3' => 'select', '4' => 'datetime', '5' => 'date'];
    $list = explode('||', $text);
    $rules = [];
    $slots = [];
    foreach ($list as $pos => $item) {
        $part = array_map('trim', explode('|', $item));
        $type = $types[$part[2] ?? ''] ?? '';
        $on = $item !== '' && $part[0] !== '0';
        if (!$on && (count($part) !== 4 || $type === '')) continue;
        if (count($part) !== 4 || $type === '') {
            $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (four slots and a type from 1 to 5 are expected)';
            continue;
        }
        $name = 'field'.($pos + 1);
        $rule = ['title' => $on ? $part[0] : $name, 'intro' => '', 'type' => $type, 'default' => '', 'options' => [], 'req' => $on && $part[3] === '1', 'multi' => false];
        $rule += ['active' => $on, 'sort' => ($pos + 1) * 10];
        $items = [];
        foreach ($type === 'select' ? explode(',', $part[1]) : [] as $label) {
            $label = trim($label);
            if ($label === '' || (!$on && ($label === '0' || isset($items[$label])))) continue;
            if (isset($items[$label])) $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (an option caption repeats)';
            $items[$label] = 'option'.(count($items) + 1);
            $rule['options']['items'][$items[$label]] = ['title' => $label, 'active' => true, 'sort' => count($items) * 10];
        }
        if ($on && $type !== 'select' && $part[1] !== '' && $part[1] !== '0') $rule['default'] = ($type === 'datetime') ? str_replace(' ', 'T', $part[1]) : $part[1];
        if ($on) $rules[$name] = $rule;
        $slots[$pos] = ['name' => $name, 'type' => $type, 'items' => $items, 'on' => $on, 'rule' => $rule];
    }
    try {
        $rules = $fld->filterFieldList($rules);
    } catch (InvalidArgumentException $err) {
        $bad[] = 'config/fields.php '.$area.' '.$err->getMessage().' (the shared check of definitions refused it)';
    }
    return [$rules, $slots, count($list)];
}

# Turn one positional 6.2 value row into the canonical JSON of its area and answer ['json' => text, 'plan' => layout, 'grow' => needs], or ['why' => reason] without stored data
# The old view indexed every position while a posted form could leave the switched off ones out, so both layouts are tried and one confirmed result is accepted
# A layout that fits the definitions as they are wins; only when none does, one that fits once they grow: a caption without an option, data at a switched off position
# The needs are field name => [caption => true] and the JSON of a growing row is provisional; $plan runs only that layout again once every need of the area is granted
# An empty part is absence; 0 is a value of a text and the placeholder of an empty choice in a select without such an option, in a date and in a switched off position
function getUpdateValue(array $rules, array $slots, int $size, string $text, Field $fld, string $plan = ''): array {
    $part = explode('|', $text);
    $plans = ['full' => range(0, max($size, count($part)) - 1), 'short' => array_keys(array_filter($slots, fn(array $v): bool => $v['on']))];
    if ($plan !== '') $plans = array_intersect_key($plans, [$plan => true]);
    $found = ['fit' => [], 'grow' => []];
    $why = '';
    foreach ($plans as $name => $order) {
        $vals = [];
        $grow = [];
        $test = $rules;
        $fail = '';
        foreach ($part as $num => $val) {
            $slot = isset($order[$num]) ? ($slots[$order[$num]] ?? null) : null;
            $spot = 'value '.($num + 1);
            $data = $val !== '' && $val !== '0';
            if ($slot === null) {
                if ($data) $fail = $spot.' holds data and has no definition';
            } elseif (!$slot['on'] && !$data) {
                continue;
            } elseif ($slot['type'] === 'select') {
                $cap = trim($val);
                if (isset($slot['items'][$cap])) $vals[$slot['name']] = $slot['items'][$cap];
                elseif ($data && $cap !== '') $grow[$slot['name']][$cap] = true;
            } elseif ($slot['type'] === 'date' || $slot['type'] === 'datetime') {
                if ($data) $vals[$slot['name']] = ($slot['type'] === 'datetime') ? str_replace(' ', 'T', $val) : $val;
            } elseif ($val !== '') {
                $vals[$slot['name']] = $val;
            }
            if ($slot !== null && $data && !isset($rules[$slot['name']])) $grow[$slot['name']] ??= [];
            if ($fail !== '') break;
        }
        foreach ($fail === '' ? $grow : [] as $key => $caps) {
            $slot = $slots[intval(substr($key, 5)) - 1];
            $test[$key] ??= $slot['rule'];
            foreach (array_keys($caps) as $cap) {
                $next = count($test[$key]['options']['items'] ?? []) + 1;
                $pick = 'option'.$next;
                $test[$key]['options']['items'][$pick] = ['title' => (string)$cap, 'active' => true, 'sort' => $next * 10];
                $vals[$key] = $pick;
            }
        }
        foreach ($test as $key => $rule) {
            $test[$key]['active'] = true;
            foreach (array_keys($rule['options']['items'] ?? []) as $pick) $test[$key]['options']['items'][$pick]['active'] = true;
        }
        $out = [];
        try {
            $errs = ($fail === '') ? $fld->checkFieldValues($test, $vals, false) : [];
            foreach ($errs as $key => $code) $fail = $key.' is refused by the shared check: '.$code;
            $out = ($fail === '') ? $fld->filterFieldValues($test, $vals) : [];
        } catch (InvalidArgumentException $err) {
            $fail = 'the grown definition '.$err->getMessage().' is refused by the shared check';
        }
        if ($fail === '') {
            $json = $out ? (string)json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $found[$grow ? 'grow' : 'fit'][$json.'|'.json_encode($grow)] ??= ['json' => $json, 'plan' => $name, 'grow' => array_map('array_keys', $grow)];
        } elseif ($name === 'full' || $plan !== '') {
            $why = $fail;
        }
    }
    $list = $found['fit'] ?: $found['grow'];
    if (count($list) === 1) return reset($list);
    return ['why' => $list ? 'the full and the short layout both fit and differ' : $why];
}

# The fields unit of the 6.3 data update: name the positional definitions of account and forum and turn every stored value row into one canonical JSON object
# Nothing is written before the whole preflight passed: a definition or a row that cannot be mapped without guessing stops the unit with the table, the id and the reason
# A caption without an option and data at a switched off position grow the definitions by a disabled option or an inactive field in row order, and the report counts them
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a stored row has to equal its source or its target
# Definitions that are already named while positional rows exist and no manifest does stop it, because the old definitions are the only key to those rows
# Named definitions of the order area are not carried, because the release no longer ships the module that owns them
function setUpdateFields(Database $db, string $prefix): string {
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'field'], 'forum' => ['forum', 'field']];
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $flag = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (!class_exists('Field', false)) require_once BASE_DIR.'/core/classes/field.php';
    $fld = new Field();
    $stored = function (string $area) use ($db, $prefix, $maps): array|false {
        [$tab, $col] = $maps[$area];
        $res = $db->getSqlQuery('SELECT id, '.$col.' FROM `'.$prefix.'_'.$tab.'` WHERE '.$col.' != \'\' ORDER BY id ASC');
        $list = [];
        while ($res && ([$mid, $text] = $db->getSqlRow($res))) $list[] = [intval($mid), $text];
        return $res ? $list : false;
    };
    if (!is_array($info)) {
        if (isset($mark['fields'])) return getInfo('fields: the mark is set and no manifest exists, the unit is stopped', false);
        $old = is_file(CONFIG_DIR.'/fields.php') ? ((require CONFIG_DIR.'/fields.php')['fields'] ?? []) : [];
        $named = count(array_filter(array_intersect_key($old, $maps), 'is_array'));
        $bad = [];
        $rules = [];
        $snaps = [];
        $read = [];
        $grown = ['options' => 0, 'fields' => 0];
        $done = $db->setSqlBegin();
        foreach (array_keys($maps) as $area) $read[$area] = $done ? $stored($area) : false;
        if ($done) $db->setSqlCommit();
        foreach ($maps as $area => [$tab]) {
            $base = count($bad);
            [$rules[$area], $slots, $size] = $named ? [$old[$area] ?? null, [], 0] : getUpdateRules($area, $old[$area] ?? '', $fld, $bad);
            $rows = $read[$area];
            if ($rows === false) return getInfo('fields: the values of '.$prefix.'_'.$tab.' could not be read, the unit is stopped', false);
            if ($named && ($rows || !is_array($rules[$area]))) {
                $text = 'fields: config/fields.php is already in the 6.3 format while '.$prefix.'_'.$tab.' still holds positional rows and no manifest exists';
                return getInfo($text.' - put the 6.2 config/fields.php back and start the update again', false);
            }
            $memo = [];
            $keep = [];
            $snaps[$area] = [];
            foreach (count($bad) > $base ? [] : $rows as [$mid, $text]) {
                $memo[$text] ??= getUpdateValue($rules[$area], $slots, $size, $text, $fld);
                if (isset($memo[$text]['why'])) $bad[] = $prefix.'_'.$tab.' '.$mid.' ('.$memo[$text]['why'].')';
                else $keep[] = [$mid, $text];
            }
            foreach ($keep as [$mid, $text]) {
                foreach ($memo[$text]['grow'] as $name => $caps) {
                    $pos = intval(substr($name, 5)) - 1;
                    if (!isset($rules[$area][$name])) $grown['fields']++;
                    $rules[$area][$name] ??= $slots[$pos]['rule'];
                    foreach ($caps as $cap) {
                        if (isset($slots[$pos]['items'][$cap])) continue;
                        $next = count($slots[$pos]['items']) + 1;
                        $slots[$pos]['items'][$cap] = 'option'.$next;
                        $rules[$area][$name]['options']['items']['option'.$next] = ['title' => (string)$cap, 'active' => false, 'sort' => $next * 10];
                        $grown['options']++;
                    }
                }
            }
            foreach ($keep as [$mid, $text]) {
                if ($memo[$text]['grow'] ?? []) $memo[$text] = getUpdateValue($rules[$area], $slots, $size, $text, $fld, $memo[$text]['plan']);
                if (isset($memo[$text]['why']) || $memo[$text]['grow']) $bad[] = $prefix.'_'.$tab.' '.$mid.' ('.($memo[$text]['why'] ?? 'the grown definitions do not fit it').')';
                else $snaps[$area][] = [$mid, $text, $memo[$text]['json']];
            }
        }
        if ($bad) return getInfo('fields: the preflight found data it will not guess, nothing was written ('.count($bad).'): '.implode(', ', array_slice($bad, 0, 50)), false);
        try {
            foreach ($rules as $area => $set) $rules[$area] = $fld->filterFieldList($set);
            if ($named) $rules += array_diff_key($old, ['order' => true]);
            ksort($rules);
        } catch (InvalidArgumentException $err) {
            return getInfo('fields: config/fields.php '.$area.' '.$err->getMessage().' is refused by the shared check of definitions, nothing was written', false);
        }
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('fields: '.$dir.' could not be created', false);
        $text = ['definitions.json' => json_encode(['source' => $old, 'rules' => $rules], $flag)];
        foreach ($snaps as $area => $list) $text[$area.'.json'] = json_encode($list, $flag);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => array_fill_keys(array_keys($maps), 0), 'source' => [], 'target' => []];
        $info['count'] = array_map('count', $snaps);
        $info['grown'] = $grown;
        foreach ($text as $name => $body) {
            if (!is_string($body) || !setUpdateBackup($dir.'/'.$name, $body)) return getInfo('fields: the snapshot '.$name.' could not be written', false);
            $info['source'][$name] = hash('sha256', $body);
        }
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('fields: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        foreach (array_keys($info['source']) as $name) {
            $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $info['source'][$name];
            if (!$same) return getInfo('fields: the snapshot '.$name.' does not match its manifest', false);
        }
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('fields: the manifest could not be written', false);
        $real = [];
        foreach ($maps as $area => [$tab, $col]) {
            $list = json_decode((string)file_get_contents($dir.'/'.$area.'.json'), true);
            for ($pos = intval($info['cursor'][$area]); $pos < count($list); $pos += 500) {
                $pack = array_slice($list, $pos, 500);
                $ids = [];
                foreach ($pack as $key => $row) $ids['i'.$key] = $row[0];
                $good = $db->setSqlBegin();
                $res = $good ? $db->getSqlQuery('SELECT id, '.$col.' FROM `'.$prefix.'_'.$tab.'` WHERE id IN (:'.implode(', :', array_keys($ids)).') FOR UPDATE', $ids) : false;
                $have = [];
                while ($res && ([$mid, $text] = $db->getSqlRow($res))) $have[intval($mid)] = $text;
                foreach ($res ? $pack : [] as [$mid, $from, $into]) {
                    $cur = $have[$mid] ?? null;
                    $sql = 'UPDATE `'.$prefix.'_'.$tab.'` SET '.$col.' = :val WHERE id = :id';
                    if ($cur !== $into) $good = $cur === $from && $db->getSqlQuery($sql, ['val' => $into, 'id' => $mid]) !== false;
                    if (!$good) break;
                }
                if (!$res || !$good || !$db->setSqlCommit()) {
                    $db->setSqlRollback();
                    $text = 'fields: a batch of '.$prefix.'_'.$tab.' was refused at row '.$pos;
                    return getInfo($text.' - a stored row equals neither its source nor its target or could not be written', false);
                }
                $info['cursor'][$area] = min($pos + 500, count($list));
                if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('fields: the manifest could not be written', false);
            }
            $want = [];
            foreach ($list as [$mid, $from, $into]) {
                if ($into !== '') $want[] = [$mid, $into];
            }
            $rows = $stored($area);
            if ($rows !== $want) return getInfo('fields: the stored values of '.$prefix.'_'.$tab.' do not match the manifest, the definitions are not published', false);
            $real[$area.'.json'] = hash('sha256', (string)json_encode($rows, $flag));
        }
        $rules = json_decode((string)file_get_contents($dir.'/definitions.json'), true)['rules'] ?? [];
        if (((require CONFIG_DIR.'/fields.php')['fields'] ?? null) !== $rules) setConfigFile('fields.php', $rules, [], true);
        if (((require CONFIG_DIR.'/fields.php')['fields'] ?? null) !== $rules) return getInfo('fields: config/fields.php could not be published', false);
        $info['target'] = $real + ['fields.php' => hash_file('sha256', CONFIG_DIR.'/fields.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('fields: the manifest could not be written', false);
    }
    if (!setConfigFile('update.php', ['fields' => '6.3.0'] + $mark)) return getInfo('fields: config/update.php could not be written, the subsystem stays closed', false);
    $stat = $info['count'];
    $text = 'fields: named definitions published, value rows carried over: '.intval($stat['account']).' accounts, '.intval($stat['forum']).' forum posts; kept as switched off: ';
    $text .= intval($info['grown']['options'] ?? 0).' options, '.intval($info['grown']['fields'] ?? 0).' fields';
    return getInfo($text.'; the subsystem is open', true);
}

# The configuration step of the 6.3 update for a 6.2 site, whose settings live in config/config_<name>.php as a variable of their own
# The site values go over the shipped source of the same name, stat into statistic and seo over global; an unshipped key stays for the data units and the next form save
# The version, the asset lists and the closed site belong to the release and the update, and a language name becomes its code
# A start module, a theme or a site logo that is not in the tree falls back to the shipped value
# Two positional formats changed after 6.2: an upload rule loses its retired eighth field adminlist and gains the guest file limit at the user one
# The upload rule takes the short form the runtime reads, and an address ban turns ip and octet count into one CIDR
# Every source ends in storage/backup/update/config once its target is written and read back, the ones without a successor as well, so the runtime never includes one again
function setUpdateConfig(): string {
    $list = glob(CONFIG_DIR.'/config_*.php') ?: [];
    if (!$list) return '';
    $dir = BASE_DIR.'/storage/backup/update/config';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('config: '.$dir.' could not be created', false);
    $maps = ['stat' => 'statistic', 'seo' => 'global'];
    $skip = ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'templ', 'header', 'chmod', 'core', 'rewrite', 'rules', 'db'];
    $langs = ['english' => 'en', 'french' => 'fr', 'german' => 'de', 'polish' => 'pl', 'russian' => 'ru', 'ukrainian' => 'uk'];
    $mods = array_map('basename', glob(BASE_DIR.'/modules/*', GLOB_ONLYDIR) ?: []);
    $plan = [];
    $left = [];
    foreach ($list as $file) {
        $name = substr(basename($file, '.php'), 7);
        $into = $maps[$name] ?? $name;
        if (in_array($name, $skip, true) || !is_file(CONFIG_DIR.'/'.$into.'.php')) $left[$name] = $file;
        else $plan[$into][$name] = $file;
    }
    $done = [];
    $bad = [];
    $note = [];
    foreach ($plan as $into => $files) {
        $base = getSetupConfig(CONFIG_DIR.'/'.$into.'.php');
        $base = ($into === 'global') ? $base : ($base[$into] ?? []);
        $site = [];
        foreach ($files as $file) $site = array_replace_recursive($site, getSetupConfig($file));
        if (!$site) {
            $left += $files;
            continue;
        }
        if ($into === 'global') {
            $site['close'] = '1';
            if (isset($site['language'])) $site['language'] = $langs[$site['language']] ?? $site['language'];
            $keep = implode(',', array_intersect(array_map('trim', explode(',', (string)($site['module'] ?? ''))), $mods));
            if ($keep === '') unset($site['module']);
            else $site['module'] = $keep;
            unset($site['version'], $site['css_f'], $site['script_f'], $site['amod']);
            if (isset($site['theme']) && !is_dir(BASE_DIR.'/templates/'.basename((string)$site['theme']))) unset($site['theme']);
            $look = BASE_DIR.'/templates/'.basename((string)($site['theme'] ?? $base['theme'] ?? '')).'/images/logos/';
            if (isset($site['site_logo']) && !is_file($look.basename((string)$site['site_logo']))) unset($site['site_logo']);
        }
        if ($into === 'lang' && isset($site['lang'])) $site['lang'] = $langs[$site['lang']] ?? $site['lang'];
        foreach ($into === 'uploads' ? $site : [] as $key => $val) {
            $part = is_string($val) ? explode('|', $val) : [];
            if (count($part) !== 12) continue;
            unset($part[7]);
            $part = array_values($part);
            $site[$key] = implode('|', $part).'|'.$part[8];
        }
        if ($into === 'security' && isset($site['blocker_ip'])) {
            $list = [];
            foreach (explode('||', (string)$site['blocker_ip']) as $item) {
                $part = explode('|', $item, 5);
                $mask = intval($part[1] ?? 0);
                if ($item === '') continue;
                if (count($part) !== 5 || !filter_var($part[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $mask < 1 || $mask > 4) {
                    $note[] = 'the ban entry '.$part[0].' is not a 6.2 address ban and was dropped';
                    continue;
                }
                $net = implode('.', array_pad(array_slice(explode('.', $part[0]), 0, $mask), 4, '0')).'/'.($mask * 8);
                $list[] = $net.'|'.$part[2].'|'.$part[3].'|'.$part[4].'||';
            }
            $site['blocker_ip'] = implode('', $list);
        }
        $data = ($into === 'fields') ? $site : array_replace_recursive($base, $site);
        setConfigFile($into.'.php', $data, [], true);
        $read = getSetupConfig(CONFIG_DIR.'/'.$into.'.php');
        if ((($into === 'global') ? $read : ($read[$into] ?? null)) != $data) {
            $bad[] = $into;
            continue;
        }
        foreach ($files as $file) if (!rename($file, $dir.'/'.basename($file))) $bad[] = basename($file);
        $done[] = $into;
    }
    foreach ($left as $file) if (!rename($file, $dir.'/'.basename($file))) $bad[] = basename($file);
    $text = 'config: 6.2 settings carried into '.($done ? implode(', ', $done) : 'no file').'; not carried: '.($left ? implode(', ', array_keys($left)) : 'none')
        .($note ? '; '.implode('; ', $note) : '').'; the old sources are in storage/backup/update/config';
    $out = getInfo($text, true);
    if ($bad) $out .= getInfo('config: '.implode(', ', $bad).' could not be written or moved, the old sources stay in config/ and the update stops before the schema', false);
    return $out;
}

# The module registry of the 6.3 update, reconciled with the tree as the modules screen does it; the _modules table of a 6.2 site wins for its six switches
# A module of the tree keeps the record of config/modules.php or gets the default, node gets the record of a clean installation, a record without a module is dropped
# The panel rights of the administrators name the modules instead of the numbers of that table, and the answer names the dropped records
# Only the first run reads that table: a repeat after the mark modules keeps the switches the owner set in between, and the answer says so
function setUpdateModules(Database $db, string $prefix, bool $first = true): string {
    $mods = [];
    foreach (scandir(BASE_DIR.'/admin/modules') ?: [] as $file) if (preg_match('/^([a-z_]+)\.php$/i', $file, $matches)) $mods[$matches[1]] = 0;
    foreach (scandir(BASE_DIR.'/modules') ?: [] as $file) {
        if (!str_contains($file, '.') && (file_exists(BASE_DIR.'/modules/'.$file.'/index.php') || file_exists(BASE_DIR.'/modules/'.$file.'/admin/index.php'))) $mods[$file] = 1;
    }
    $cont = [];
    foreach ($mods as $module => $type) {
        $cont[$module] = ['lang' => '_'.strtoupper($module), 'icon' => 'puzzle', 'active' => $type ? 0 : 1, 'view' => 0, 'menu' => 1, 'group' => 0, 'side' => 0,
            'top' => 0, 'type' => $type];
    }
    if (isset($cont['node'])) {
        $cont['node'] = ['lang' => '_NODE', 'icon' => 'collection', 'active' => 1, 'view' => 0, 'menu' => 0, 'group' => 0, 'side' => 2, 'top' => 0, 'type' => 1];
    }
    $exfile = CONFIG_DIR.'/modules.php';
    $existing = file_exists($exfile) ? ((require $exfile)['modules'] ?? []) : [];
    $cont = array_merge($cont, $existing);
    $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl';
    $tblres = $first ? $db->getSqlQuery($sql, ['tbl' => $prefix.'_modules']) : false;
    $fail = ($first && !$tblres) ? ['information_schema'] : [];
    if ($tblres && $db->getSqlRowCount($tblres) > 0) {
        $map = [];
        $result = $db->getSqlQuery('SELECT mid, title, active, view, inmenu, mod_group, blocks, blocks_c FROM `'.$prefix.'_modules`');
        if (!$result) $fail[] = $prefix.'_modules';
        while ($result && ($row = $db->getSqlRow($result))) {
            $name = $row['title'];
            $map[(string)$row['mid']] = $name;
            if (!isset($mods[$name])) continue;
            $site = ['active' => $row['active'], 'view' => $row['view'], 'menu' => $row['inmenu'], 'group' => $row['mod_group'], 'side' => $row['blocks'],
                'top' => $row['blocks_c']];
            $cont[$name] = array_replace($cont[$name], $site);
        }
        if (!empty($map)) {
            $result = $db->getSqlQuery('SELECT id, modules FROM `'.$prefix.'_admins`');
            if (!$result) $fail[] = $prefix.'_admins';
            $sql = 'UPDATE `'.$prefix.'_admins` SET modules = :modules WHERE id = :id';
            while ($result && ($row = $db->getSqlRow($result))) {
                $modules = $row['modules'] ?? '';
                $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
                $names = [];
                foreach ($list as $val) {
                    if (ctype_digit($val) && isset($map[$val])) {
                        $names[] = $map[$val];
                    } elseif (!ctype_digit($val)) {
                        $names[] = $val;
                    }
                }
                $newmod = implode(',', array_values(array_unique($names)));
                if ($newmod !== $modules && !$db->getSqlQuery($sql, ['modules' => $newmod, 'id' => $row['id']])) $fail[] = $prefix.'_admins '.$row['id'];
            }
        }
    }
    $gone = array_keys(array_diff_key($existing, $mods));
    if (!$fail && !setConfigFile('modules.php', array_intersect_key($cont, $mods))) $fail[] = 'config/modules.php';
    if ($fail) return getInfo('module registry: '.implode(', ', $fail).' could not be read or written, the update stops before the schema', false);
    $out = $first ? '' : getInfo('config/modules.php switches of the 6.2 table '.$prefix.'_modules were carried by the first run, the current ones stay', true);
    return $out.($gone ? getInfo('config/modules.php records of removed modules dropped: '.implode(', ', $gone), true) : '');
}

# Take the types of config/node.php out of the four areas of their package - node.types, fields.node, uploads and ratings node.<name> - as the panel removes a type
# Publish the four files and answer the names taken out; a clean installation takes every type, the update keeps the names in $keep that its table registers
# A file that cannot be written answers false; node.php goes last, so a repeat still finds the types it has to take out of the other three
function deleteSetupTypes(array $keep = []): array|false {
    $ntypes = array_values(array_diff(array_keys(getSetupConfig(CONFIG_DIR.'/node.php')['node']['types'] ?? []), $keep));
    if (!$ntypes) return [];
    $pack = [];
    foreach (['fields', 'uploads', 'ratings', 'node'] as $name) $pack[$name] = getSetupConfig(CONFIG_DIR.'/'.$name.'.php')[$name] ?? [];
    foreach ($ntypes as $name) unset($pack['node']['types'][$name], $pack['fields']['node'][$name], $pack['uploads'][$name], $pack['ratings']['node.'.$name]);
    if (($pack['fields']['node'] ?? null) === []) unset($pack['fields']['node']);
    foreach ($pack as $name => $data) if (!setConfigFile($name.'.php', $data, [], true)) return false;
    return $ntypes;
}

# The newsletter step of the 6.3 update: before the schema file drops the mails column the pending recipients of every campaign are kept in storage/backup/update/newsletter
# After the drop they move into the mail queue; an address already queued for its campaign is not written twice, so a break and a repeat neither lose nor double a recipient
# The unit resumes from its manifest like the data units: without the column and without a manifest there is nothing pending, verified is skipped
function setUpdateMails(Database $db, string $prefix, string $from, bool $move): string {
    $dir = BASE_DIR.'/storage/backup/update/newsletter';
    $file = $dir.'/manifest.json';
    $tab = '`'.$prefix.'_newsletter`';
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$move) {
        if (is_array($info)) return '';
        $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tab AND column_name = \'mails\'';
        $res = $db->getSqlQuery($sql, ['tab' => $prefix.'_newsletter']);
        if (!$res) return getInfo('newsletter: '.$prefix.'_newsletter could not be read, the update stops before the schema', false);
        if (!$db->getSqlRowCount($res)) return '';
        $list = [];
        $res = $db->getSqlQuery('SELECT id, title, mails FROM '.$tab.' WHERE mails IS NOT NULL AND mails != \'\' ORDER BY id ASC');
        while ($res && ([$nid, $name, $text] = $db->getSqlRow($res))) {
            $mails = array_filter(array_map('trim', explode(',', (string)$text)), fn(string $v): bool => filter_var($v, FILTER_VALIDATE_EMAIL) !== false);
            $list[] = [intval($nid), (string)$name, array_values(array_unique($mails))];
        }
        if (!$res) return getInfo('newsletter: the pending recipients could not be read, the update stops before the schema', false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('newsletter: '.$dir.' could not be created, the update stops before the schema', false);
        $text = (string)json_encode($list, JSON_UNESCAPED_UNICODE);
        $info = ['version' => '6.3.0', 'state' => 'prepared',
            'count' => ['campaigns' => count($list), 'recipients' => array_sum(array_map(fn(array $v): int => count($v[2]), $list))]];
        $info['source'] = ['recipients.json' => hash('sha256', $text)];
        if (!setUpdateBackup($dir.'/recipients.json', $text) || !setUpdateBackup($file, (string)json_encode($info))) {
            return getInfo('newsletter: the snapshot of the recipients could not be written, the update stops before the schema', false);
        }
        return getInfo('newsletter: '.$info['count']['recipients'].' pending recipients of '.$info['count']['campaigns'].' campaigns kept before the schema change', true);
    }
    if (!is_array($info)) return '';
    if ($info['state'] !== 'verified') {
        $same = is_file($dir.'/recipients.json') && hash_file('sha256', $dir.'/recipients.json') === ($info['source']['recipients.json'] ?? '');
        if (!$same) return getInfo('newsletter: the snapshot of the recipients does not match its manifest', false);
        $find = 'SELECT id FROM `'.$prefix.'_mail` WHERE kind = \'newsletter\' AND ref = :ref AND email = :mail LIMIT 1';
        $sql = 'INSERT INTO `'.$prefix.'_mail` (kind, sender, email, title, body, ref, prio, time, ntime)'
            .' VALUES (\'newsletter\', :from, :mail, :title, \'\', :ref, 3, NOW(), NOW())';
        $mark = 'UPDATE '.$tab.' SET status = 5, audit = \'list\', expect = :num, total = :sum WHERE id = :id';
        $info['sent'] = 0;
        foreach (json_decode((string)file_get_contents($dir.'/recipients.json'), true) as [$nid, $name, $mails]) {
            $good = $db->setSqlBegin();
            foreach ($good ? $mails : [] as $mail) {
                $res = $db->getSqlQuery($find, ['ref' => $nid, 'mail' => $mail]);
                $good = $res !== false;
                if (!$good) break;
                if ($db->getSqlRow($res)) continue;
                $good = $db->getSqlQuery($sql, ['from' => $from, 'mail' => $mail, 'title' => mb_substr($name, 0, 255), 'ref' => $nid]) !== false;
                if (!$good) break;
                $info['sent']++;
            }
            $good = $good && $db->getSqlQuery($mark, ['num' => count($mails), 'sum' => count($mails), 'id' => $nid]) !== false;
            if (!$good || !$db->setSqlCommit()) {
                $db->setSqlRollback();
                return getInfo('newsletter: the recipients of campaign '.$nid.' could not be queued, run the update again', false);
            }
        }
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('newsletter: the manifest could not be written', false);
    }
    return getInfo($prefix.'_newsletter pending recipients moved into the mail queue (rows written: '.intval($info['sent'] ?? 0).')', true);
}

# Run the installer form for a clean installation or an upgrade; an upgrade gives the scheduler of the site the system jobs nodepublish and nodesync it has not carried yet
# An upgrade also moves maildrain off a priority another job holds, because the scheduler form saves no job whose priority is taken
# It drops commentsync, the nightly counter sweep a site upgraded while it existed still carries, because the sweep now happens on every write
# The dbbackup job gains only the missing keys of its scope, compression and retention settings, so a configured value is never overwritten
# The shipped admin.php takes the chosen panel name and replaces a 6.2 loader of that name, and the panel file of the site under another name is removed
# Before the schema file a negative point balance of 6.2 becomes 0, since the schema makes the column unsigned, and the report counts those accounts
function save(): void {
    global $title, $clang, $conf, $url, $scode, $spanel;
    $xcode = is_string($_POST['xcode'] ?? null) ? $_POST['xcode'] : '';
    if ($scode !== '' && !checkSetupCode($xcode)) setExit(_SETUPCODE);
    $setup = (isset($_POST['setup'])) ? $_POST['setup'] : '';
    $xhost = (isset($_POST['xhost'])) ? $_POST['xhost'] : '';
    $xuname = (isset($_POST['xuname'])) ? $_POST['xuname'] : '';
    $xpass = (isset($_POST['xpass'])) ? $_POST['xpass'] : '';
    $xname = (isset($_POST['xname'])) ? $_POST['xname'] : '';
    $xengine = 'InnoDB';
    $xcharset = 'utf8mb4';
    $xcollate = 'utf8mb4_unicode_ci';
    $xprefix = (isset($_POST['xprefix'])) ? $_POST['xprefix'] : 'slaed';
    $xsync = (isset($_POST['xsync'])) ? $_POST['xsync'] : '1';
    $xafile = (isset($_POST['xafile'])) ? $_POST['xafile'] : 'admin';
    if ($xpass === '') $xpass = getSetupBase()['pass'] ?? '';
    $steps = ['new', 'update4_1', 'update4_2', 'update4_3', 'update5_0', 'update5_1', 'update6_0', 'update6_2', 'update6_3'];
    $lower = strtolower($xafile);
    if (!in_array($setup, $steps, true)) setExit(_SETUPTYPE);
    if (!preg_match('/^[A-Za-z0-9_]{1,32}$/D', $xprefix)) setExit(_SETUPPREFIX);
    if (filterVar($xafile) === '' || in_array($lower, ['index', 'setup'], true) || (is_file($xafile.'.php') && !in_array($lower, ['admin', strtolower($spanel)], true))) {
        setExit(_SETUPAFILE);
    }
    foreach (['db.php', 'global.php', 'security.php'] as $name) checkWritableConfig($name);
    if (is_file(BASE_DIR.'/storage/backup/config/marker.json')) setExit(_SETUPJOUR);
    require_once BASE_DIR.'/core/classes/pdo.php';
    $db = new Database($xhost, $xuname, $xpass, $xname, $xcharset);
    $bodytext = '';
    if ($setup == 'new') {
        $stop = checkUpdateBase($db, $xprefix, true);
        if ($stop !== '') setExit($stop);
    }
    if ($setup == 'update6_3') {
        $stop = checkUpdateBase($db, $xprefix);
        if ($stop !== '') setExit($stop);
        if (!setConfigFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), ['close' => '1'])) setExit(_FILE.' config/global.php '._SERRORPERM);
        $bodytext .= getInfo('the site is closed for the data update (close = 1), open it in the settings after the result is checked', true);
        $bodytext .= setUpdateConfig();
        $conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
    }
    $cont = ($setup == 'update6_3') ? [] : ['language' => $clang, 'homeurl' => $url];
    if (!setConfigFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), $cont)) setExit(_FILE.' config/global.php '._SERRORPERM);
    $conf = array_merge($conf, require CONFIG_DIR.'/global.php');
    $from = file_exists('admin.php') ? 'admin' : $spanel;
    if ($xafile !== $from && (!file_exists($from.'.php') || !rename($from.'.php', $xafile.'.php'))) $xafile = $from;
    if (!in_array(strtolower($spanel), ['admin', 'index', 'setup', strtolower($xafile)], true) && is_file($spanel.'.php')) unlink($spanel.'.php');
    $cont = ['afile' => $xafile];
    if (!setConfigFile('security.php', $conf['security'], $cont)) setExit(_FILE.' config/security.php '._SERRORPERM);
    $conf = array_merge($conf, require CONFIG_DIR.'/security.php');
    $conf['db'] = getSetupBase();
    $cont = [
        'host' => $xhost,
        'uname' => $xuname,
        'pass' => $xpass,
        'name' => $xname,
        'engine' => $xengine,
        'charset' => $xcharset,
        'collate' => $xcollate,
        'prefix' => $xprefix,
        'sync' => $xsync,
    ];
    if (!setConfigFile('db.php', $conf['db'], $cont)) setExit(_FILE.' config/db.php '._SERRORPERM);
    if ($setup == 'new') {
        $title = _SAVE_NEW;
        $ntypes = deleteSetupTypes();
        if ($ntypes) $bodytext .= getInfo('config/node.php types of an earlier installation removed with their fields, upload and rating rules: '.implode(', ', $ntypes), true);
        $text = 'config/node.php: earlier types could not be taken out and no table was created; make config/ writable, put your code into config/setup.unlock and install again';
        if ($ntypes === false) $bodytext .= getInfo($text, false);
        $ddl = ($ntypes !== false) ? getSqlFile('setup/sql/table.sql', $xprefix, $xengine, $xcharset, $xcollate, $db) : '';
        $ddl .= ($ddl !== '' && !str_contains($ddl, 'sl_red')) ? getSqlFile('setup/sql/insert.sql', $xprefix, $xengine, $xcharset, $xcollate, $db) : '';
        $bodytext .= $ddl;
        if ($ddl !== '' && !str_contains($ddl, 'sl_red')) {
            $text = 'config/update.php could not be written, points, ratings and fields stay closed: drop the tables, put your code into config/setup.unlock and install again';
            if (!setConfigFile('update.php', ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0', 'node' => 'new'])) $bodytext .= getInfo($text, false);
        } elseif ($ntypes !== false) {
            $text = 'the installation stopped at a failed statement: no mark was written; drop the tables of the prefix, put your code into config/setup.unlock and install again';
            $bodytext .= getInfo($text, false);
        }
    } elseif ($setup == 'update4_1') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update4_1.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update4_2') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update4_2.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update4_3') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update4_3.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update5_0') {
        $title = _SAVE_UPDATE;
        $result = $db->getSqlQuery('SELECT id, password FROM '.$xprefix.'_admins');
        while ([$aid, $apwd] = $db->getSqlRow($result)) {
            $pwdhash = getCrypt($apwd);
            $db->getSqlQuery('UPDATE '.$xprefix.'_admins SET password = :password WHERE id = :id', ['password' => $pwdhash, 'id' => $aid]);
        }
        $bodytext .= getInfo($xprefix.'_admins', $result);
        $result = $db->getSqlQuery('SELECT id, password FROM '.$xprefix.'_users');
        while ([$uid, $upwd] = $db->getSqlRow($result)) {
            $pwdhash = getCrypt($upwd);
            $db->getSqlQuery('UPDATE '.$xprefix.'_users SET password = :pwd WHERE id = :uid', ['pwd' => $pwdhash, 'uid' => $uid]);
        }
        $bodytext .= getInfo($xprefix.'_users', $result);
        $bodytext .= getSqlFile('setup/sql/table_update5_0.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update5_1') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update5_1.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
        $result = $db->getSqlQuery(
            'SELECT poll_id, poll_date, poll_title, poll_questions, poll_answer_1, poll_answer_2, poll_answer_3, poll_answer_4, poll_answer_5, poll_answer_6,'
            .' poll_answer_7, poll_answer_8, poll_answer_9, poll_answer_10, poll_answer_11, poll_answer_12, pool_comments, planguage, acomm'
            .' FROM '.$xprefix.'_voting_temp'
        );
        while ($row = $db->getSqlRow($result)) {
            $pid = $row['poll_id'];
            $pdate = $row['poll_date'];
            $ptitle = $row['poll_title'];
            $pquest = $row['poll_questions'];
            $pcomm = $row['pool_comments'];
            $plang = $row['planguage'];
            $acomm = $row['acomm'];
            $pansw = [];
            for ($ix = 1; $ix <= 12; $ix++) {
                $av = trim($row['poll_answer_'.$ix] ?? '');
                if ($av !== '') $pansw[] = $av;
            }
            $quest = substr($pquest, 0, -1);
            $answ = implode('|', $pansw);
            $db->getSqlQuery('INSERT INTO '.$xprefix.'_voting (id, modul, title, body, answer, time, enddate, multi, comments, language, acomm, ip, typ, status)'
            .' VALUES (:id, \'\', :title, :body, :answer, :date, \'2020-05-23 20:58:00\', 0, :comments, :language, :acomm, :ip, 1, 1)', [
                'id' => $pid,
                'title' => $ptitle,
                'body' => $quest,
                'answer' => $answ,
                'date' => $pdate,
                'comments' => $pcomm,
                'language' => $plang,
                'acomm' => $acomm,
                'ip' => getIp()
            ]);
            $db->getSqlQuery('DROP TABLE '.$xprefix.'_voting_temp');
        }
        $bodytext .= getInfo($xprefix.'_voting', $result);
        $result = $db->getSqlQuery('SELECT id, assoc FROM '.$xprefix.'_news');
        while ([$id, $raw] = $db->getSqlRow($result)) {
            $raw = explode('-', $raw);
            if (is_array($raw)) {
                $assoc = [];
                foreach ($raw as $val) {
                    if (!empty($val)) $assoc[] = trim($val);
                }
                $assoc = implode(',', $assoc);
            } else {
                $assoc = '';
            }
            $db->getSqlQuery('UPDATE '.$xprefix.'_news SET assoc = :assoc WHERE id = :id', ['assoc' => $assoc, 'id' => $id]);
        }
        $bodytext .= getInfo($xprefix.'_news', $result);
        $result = $db->getSqlQuery('SELECT id, assoc FROM '.$xprefix.'_products');
        while ([$id, $raw] = $db->getSqlRow($result)) {
            $raw = explode('-', $raw);
            if (is_array($raw)) {
                $assoc = [];
                foreach ($raw as $val) {
                    if (!empty($val)) $assoc[] = trim($val);
                }
                $assoc = implode(',', $assoc);
            } else {
                $assoc = '';
            }
            $db->getSqlQuery('UPDATE '.$xprefix.'_products SET assoc = :assoc WHERE id = :id', ['assoc' => $assoc, 'id' => $id]);
        }
        $bodytext .= getInfo($xprefix.'_products', $result);
    } elseif ($setup == 'update6_0') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update6_0.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update6_2') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update6_2.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update6_3') {
        $title = _SAVE_UPDATE;
        $umark = getSetupConfig(CONFIG_DIR.'/update.php')['update'] ?? [];
        $first = !isset($umark['modules']);
        $bodytext .= setUpdateModules($db, $xprefix, $first);
        $keep = [];
        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl';
        $res = $db->getSqlQuery($sql, ['tbl' => $xprefix.'_node_types']);
        $good = $res !== false;
        if ($good && $db->getSqlRowCount($res) > 0) {
            $res = $db->getSqlQuery('SELECT name FROM `'.$xprefix.'_node_types`');
            $good = $res !== false;
            while ($good && ([$tname] = $db->getSqlRow($res))) $keep[] = $tname;
        }
        $tgone = $good ? deleteSetupTypes($keep) : [];
        $text = 'config/node.php types without a row in '.$xprefix.'_node_types removed with their fields, upload and rating rules: '.implode(', ', $tgone ?: []);
        if (!$good) $text = 'config/node.php: '.$xprefix.'_node_types could not be read, the update stops before the schema';
        if ($tgone === false) $text = 'config/node.php: the types without a row in '.$xprefix.'_node_types could not be taken out, the update stops before the schema';
        $good = $good && $tgone !== false;
        if (!$good || $tgone) $bodytext .= getInfo($text, $good);
        $ufile = CONFIG_DIR.'/uploads.php';
        $udata = is_file($ufile) ? ((require $ufile)['uploads'] ?? []) : [];
        $ntypes = is_file(CONFIG_DIR.'/node.php') ? ((require CONFIG_DIR.'/node.php')['node']['types'] ?? []) : [];
        $ugone = array_diff_key(array_intersect_key($udata, array_flip(['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media'])), $ntypes);
        if ($ugone && !setConfigFile('uploads.php', array_diff_key($udata, $ugone))) {
            $bodytext .= getInfo('config/uploads.php could not be written, the update stops before the schema', false);
        } elseif ($ugone) {
            $bodytext .= getInfo('config/uploads.php rules of removed modules dropped: '.implode(', ', array_keys($ugone)), true);
        }
        $sfile = CONFIG_DIR.'/scheduler.php';
        if (file_exists($sfile)) {
            $sdata = require $sfile;
            $sched = $sdata['scheduler'] ?? [];
            $sdone = false;
            if (is_array($sched) && !isset($sched['jobs']['maildrain'])) {
                $sched['jobs']['maildrain'] = [
                    'title' => 'Mail delivery',
                    'type' => 'system',
                    'active' => '1',
                    'system' => 'maildrain',
                    'schedule' => '*/5 * * * *',
                    'priority' => '8',
                    'lock_timeout' => '900',
                    'manual' => '1',
                    'settings' => [],
                ];
                $sdone = true;
            }
            if (is_array($sched) && !isset($sched['jobs']['nodepublish'])) {
                $sched['jobs']['nodepublish'] = [
                    'title' => 'Node publication',
                    'type' => 'system',
                    'active' => '1',
                    'system' => 'nodepublish',
                    'schedule' => '* * * * *',
                    'priority' => '6',
                    'lock_timeout' => '180',
                    'manual' => '1',
                    'settings' => ['limit' => '50'],
                ];
                $sdone = true;
            }
            if (is_array($sched) && !isset($sched['jobs']['nodesync'])) {
                $sched['jobs']['nodesync'] = [
                    'title' => 'Node sync',
                    'type' => 'system',
                    'active' => '1',
                    'system' => 'nodesync',
                    'schedule' => '*/5 * * * *',
                    'priority' => '7',
                    'lock_timeout' => '180',
                    'manual' => '1',
                    'settings' => ['limit' => '10'],
                ];
                $sdone = true;
            }
            if (is_array($sched) && isset($sched['jobs']['commentsync'])) {
                unset($sched['jobs']['commentsync']);
                $sdone = true;
            }
            if ($first && is_array($sched) && isset($sched['jobs']['newsletter']) && is_array($sched['jobs']['newsletter'])) {
                $sched['jobs']['newsletter']['active'] = '1';
                $sched['jobs']['newsletter']['schedule'] = '*/5 * * * *';
                $sdone = true;
            }
            if (is_array($sched) && isset($sched['jobs']['dbbackup']) && is_array($sched['jobs']['dbbackup'])) {
                $bset = [
                    'include' => '*',
                    'exclude' => 'ipb_*',
                    'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY',
                    'compress' => 'auto',
                    'keep' => '0',
                    'allow_incomplete' => '0',
                ];
                if (!isset($sched['jobs']['dbbackup']['settings']) || !is_array($sched['jobs']['dbbackup']['settings'])) $sched['jobs']['dbbackup']['settings'] = [];
                foreach ($bset as $key => $val) {
                    if (isset($sched['jobs']['dbbackup']['settings'][$key])) continue;
                    $sched['jobs']['dbbackup']['settings'][$key] = $val;
                    $sdone = true;
                }
            }
            if (is_array($sched) && isset($sched['jobs']['maildrain']) && is_array($sched['jobs']['maildrain'])) {
                $held = [];
                foreach ($sched['jobs'] as $jkey => $jval) if ($jkey !== 'maildrain' && is_array($jval)) $held[] = (int)($jval['priority'] ?? 100);
                if (in_array((int)($sched['jobs']['maildrain']['priority'] ?? 100), $held, true)) {
                    $prio = 1;
                    while (in_array($prio, $held, true)) $prio++;
                    $sched['jobs']['maildrain']['priority'] = (string)$prio;
                    $sdone = true;
                }
            }
            if ($sdone && !setConfigFile('scheduler.php', $sched)) $bodytext .= getInfo('config/scheduler.php could not be written, the update stops before the schema', false);
        }
        $text = ' could not be written, the update stops before the schema';
        if ($first && !str_contains($bodytext, 'sl_red') && !setConfigFile('update.php', ['modules' => '6.3.0'] + $umark)) $bodytext .= getInfo('config/update.php'.$text, false);
        $ndata = getSetupConfig(CONFIG_DIR.'/newsletter.php')['newsletter'] ?? [];
        $nset = $ndata + ['abort' => '10', 'bouncemax' => '2', 'breakwin' => '100', 'canary' => '100', 'canarymin' => '500'];
        if ($nset !== $ndata && !setConfigFile('newsletter.php', $nset)) $bodytext .= getInfo('config/newsletter.php'.$text, false);
        $bodytext .= setUpdateMails($db, $xprefix, (string)$conf['adminmail'], false);
        $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl AND column_name IN (\'points\', \'user_points\')';
        $res = $db->getSqlQuery($sql, ['tbl' => $xprefix.'_users']);
        $pcol = $res ? (string)($db->getSqlRow($res)[0] ?? '') : '';
        $res = ($res && $pcol !== '') ? $db->getSqlQuery('UPDATE `'.$xprefix.'_users` SET `'.$pcol.'` = 0 WHERE `'.$pcol.'` < 0') : $res;
        $text = $xprefix.'_users negative point balances set to 0 before the schema makes the column unsigned (accounts: ';
        $bodytext .= getInfo($text.(($res && $pcol !== '') ? intval($db->getSqlRowCount($res)) : 0).')', $res !== false);
        if (str_contains($bodytext, 'sl_red')) {
            $text = 'the update stopped before the schema file: neither the schema file nor a data unit ran, correct the refusal above and run the update again';
            $bodytext .= getInfo($text, false);
        } else {
            $ddl = getSqlFile('setup/sql/table_update6_3.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
            $bodytext .= $ddl;
            if ($ddl === '' || str_contains($ddl, 'sl_red')) {
                $text = 'the update stopped at the schema file: no data unit ran and no mark was written, correct the failed statement and run the update again';
                $bodytext .= getInfo($text, false);
            } else {
                $bodytext .= setUpdatePoints($db, $xprefix);
                $bodytext .= setUpdateRatings($db, $xprefix);
                $bodytext .= setUpdateFields($db, $xprefix);
                $rdata = getSetupConfig(CONFIG_DIR.'/rss.php')['rss'] ?? [];
                if ($rdata !== [] && (isset($rdata['temp']) || !isset($rdata['bytes'], $rdata['redirects'], $rdata['timeout']))) {
                    unset($rdata['temp']);
                    $rdata += ['bytes' => '2097152', 'redirects' => '3', 'timeout' => '10'];
                    if (!setConfigFile('rss.php', $rdata)) $bodytext .= getInfo('config/rss.php could not be written', false);
                }
                $rnum = $db->getSqlQuery('UPDATE `'.$xprefix.'_blocks` SET content = \'\', time = \'0\' WHERE url != \'\'');
                $bodytext .= getInfo($xprefix.'_blocks RSS bodies cleared for Markdown (rows: '.($rnum ? $db->getSqlRowCount($rnum) : 0).')', $rnum !== false);
                $bpars = [];
                foreach (['news', 'pages', 'faq', 'files', 'jokes', 'jokes_random', 'links', 'center', 'center_media', 'center_plus'] as $i => $one) $bpars['b'.$i] = $one.'.php';
                $bsql = ' WHERE status = 1 AND bfile IN (:'.implode(', :', array_keys($bpars)).')';
                $res = $db->getSqlQuery('SELECT DISTINCT bfile FROM `'.$xprefix.'_blocks`'.$bsql.' ORDER BY bfile', $bpars);
                $boff = $res ? array_column($db->getSqlRows($res) ?: [], 0) : [];
                $good = $res !== false && ($boff === [] || $db->getSqlQuery('UPDATE `'.$xprefix.'_blocks` SET status = 0'.$bsql, $bpars) !== false);
                $bodytext .= getInfo($xprefix.'_blocks of removed modules switched off: '.($boff ? implode(', ', $boff) : 'none'), $good);
                $bodytext .= setUpdateMails($db, $xprefix, (string)$conf['adminmail'], true);
                [$acount] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$xprefix.'_users` WHERE `avatar` LIKE \'default/%\''));
                $bodytext .= getInfo($xprefix.'_users avatar migration (legacy rows left: '.(int)$acount.')', (int)$acount === 0);
                $pars = [];
                foreach (['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media'] as $i => $one) $pars['t'.$i] = $xprefix.'_'.$one;
                $sql = 'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND EXTRA LIKE \'%auto_increment%\''
                    .' AND TABLE_NAME IN (:'.implode(', :', array_keys($pars)).')';
                $res = $db->getSqlQuery($sql, $pars);
                $good = $res !== false;
                $top = 0;
                foreach (($good ? $db->getSqlRows($res) : false) ?: [] as $row) {
                    $got = $db->getSqlQuery('SELECT COALESCE(MAX(`'.$row[1].'`), 0) FROM `'.$row[0].'`');
                    $good = $good && $got !== false;
                    $top = max($top, $got ? intval($db->getSqlRow($got)[0] ?? 0) : 0);
                }
                $got = $good ? $db->getSqlQuery('SHOW CREATE TABLE `'.$xprefix.'_nodes`') : false;
                $next = ($got && preg_match('/\bAUTO_INCREMENT=(\d+)/', $db->getSqlRow($got)[1] ?? '', $hit)) ? intval($hit[1]) : 1;
                $good = $got !== false && ($top < $next || $db->getSqlQuery('ALTER TABLE `'.$xprefix.'_nodes` AUTO_INCREMENT = '.($top + 1)) !== false);
                $bodytext .= getInfo($xprefix.'_nodes new ids start above the highest id of the removed sections ('.$top.')', $good);
            }
        }
        if (!str_contains($bodytext, 'sl_red')) {
            $gone = 0;
            foreach (glob(BASE_DIR.'/storage/backup/update/*/*') ?: [] as $file) if (basename($file) !== 'manifest.json' && unlink($file)) $gone++;
            $bodytext .= getInfo('the snapshots of the update are deleted ('.$gone.' files), the manifests stay in storage/backup/update', true);
        }
    }
    if (!str_contains($bodytext, 'sl_red') && is_file(CONFIG_DIR.'/setup.unlock')) unlink(CONFIG_DIR.'/setup.unlock');
    setHead();
    echo '<table class="sl_table">'.$bodytext.'</table>'
    .'<div class="sl_center"><form action="'.$conf['security']['afile'].'.php" method="post">'._GOBACK
    .' <input type="submit" value="'._ADMIN_SE.'" class="sl_but_blue"></form></div>';
    setFoot();
}

switch($op) {
    default: language(); break;
    case 'lang': lang(); break;
    case 'config': config(); break;
    case 'save': save(); break;
}
