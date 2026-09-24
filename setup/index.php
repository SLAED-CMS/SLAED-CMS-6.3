<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('SETUP_FILE')) die('Illegal File Access');
define('FUNC_FILE', true);
define('CONFIG_DIR', BASE_DIR.'/config');

$conf = require CONFIG_DIR.'/global.php';
$conf = array_merge($conf, require CONFIG_DIR.'/security.php');

# The SQL splitter of the administration panel; it defines functions only, so the installer can borrow it before the rest of the system exists
require_once BASE_DIR.'/core/admin.php';

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

if (version_compare(PHP_VERSION, '8.4.0', '<')) setExit(_PHPSETUP);
foreach (['mbstring', 'pdo', 'json'] as $ext) {
    if (!extension_loaded($ext)) setExit(_EXTSETUP.': '.$ext);
}
$copy = '<a href="https://slaed.net" target="_blank" title="SLAED CMS">SLAED CMS</a> © 2005-'.date('Y').' Eduard Laas. Released under MIT License.';

# Saving configurations to a file; every scalar is stored as a string unless $raw keeps the native types the definitions of the extra fields are made of
function setConfigFile(string $fp, array $arr, array $act = [], bool $raw = false): void {
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
    file_put_contents($fp, $cnt, LOCK_EX);
    if (function_exists('opcache_invalidate')) opcache_invalidate($fp, true);
}

function getProtocol(): string {
    if ($_SERVER['SERVER_PORT'] == 443) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
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

        if ($info['table'] !== '') $output .= getInfo($info['table'], $result);
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

function checkWritableConfig(string $file): void {
    if (file_exists($file)) {
        chmod($file, 0666);
        $permsdir = decoct(fileperms($file));
        $perms = substr($permsdir, -3);
        if ($perms != '666') {
            global $title;
            $title = _FILE.' '.$file.' '._SERRORPERM.' CHMOD - 666';
            setHead();
            setFoot();
            exit;
        }
    }
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
        $cont .= '<td style="width: '.$tdwidth.'%;" class="sl_center"><a href="setup.php?op=lang&amp;id='.$val.'" title="'.$altlang.'"><img src="setup/templates/images/'.$val.'.png" alt="'.$altlang.'"><br><b>'.$altlang.'</b></a></td>';
        if ($ix % $col == 0) $cont .= '</tr>'."\n";
        $ix++;
    }
    if ($clang) {
        $cont .= '<tr><td colspan="'.$col.'" class="sl_center"><form action="setup.php" method="post"><input type="hidden" name="op" value="config"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></form></td></tr>';
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
    global $title, $conf;
    $title = _CONFIG;
    checkWritableConfig(CONFIG_DIR.'/db.php');
    checkWritableConfig(CONFIG_DIR.'/global.php');
    $conf = array_merge($conf, require CONFIG_DIR.'/db.php');
    $xhost = ($conf['db']['host']) ? $conf['db']['host'] : 'localhost';
    $xuname = ($conf['db']['uname']) ? $conf['db']['uname'] : '';
    $xpass = ($conf['db']['pass']) ? $conf['db']['pass'] : '';
    $xname = ($conf['db']['name']) ? $conf['db']['name'] : '';
    $xprefix = ($conf['db']['prefix']) ? $conf['db']['prefix'] : getRandomString('10');
    $xafile = ($conf['security']['afile']) ? $conf['security']['afile'] : strtolower(getRandomString('10'));
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
    .'<tr><td>'._CONF_3.':</td><td><input type="password" name="xpass" value="'.$xpass.'" class="sl_cinput" placeholder="'._CONF_3.'"></td></tr>'
    .'<tr><td>'._CONF_4.':</td><td><input type="text" name="xname" value="'.$xname.'" class="sl_cinput" placeholder="'._CONF_4.'" required></td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._CONF_9.':</td><td><input type="text" name="xprefix" value="'.$xprefix.'" class="sl_cinput" placeholder="'._CONF_9.'" required></td></tr>'
    .'<tr><td>'._CONF_10.':<div class="sl_small">'.$info.'</div></td><td><input type="text" name="xafile" value="'.$xafile.'" class="sl_cinput" placeholder="'._CONF_10.'" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center">'._GOBACK.' <input type="hidden" name="op" value="save"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></td></tr>'
    .'</table></form>';
    setFoot();
}

# Check what the 6.3 data update needs before anything is changed and answer the refusal, or an empty string when the update may start
# The server has to enforce CHECK constraints and every table of a points or ratings transaction has to be InnoDB; nothing is converted, and the branch closes the site itself
function checkUpdateBase(Database $db, string $prefix): string {
    [$ver] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    $min = (stripos((string)$ver, 'mariadb') !== false) ? '10.2.1' : '8.0.16';
    if (version_compare(preg_replace('/[^0-9.].*$/', '', (string)$ver), $min, '<')) return 'The database server '.$ver.' is older than '.$min.'.';
    $list = [];
    $tabs = ['users', 'comment', 'forum', 'order', 'clients', 'favorites', 'user_oauth', 'points', 'products', 'rating_targets', 'rating_actors', 'rating_votes'];
    foreach ($tabs as $key => $name) $list['t'.$key] = $prefix.'_'.$name;
    $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (:'.implode(', :', array_keys($list)).')'
        .' AND engine IS NOT NULL AND engine != \'InnoDB\'';
    $res = $db->getSqlQuery($sql, $list);
    $fix = [];
    while ($res && ([$name] = $db->getSqlRow($res))) $fix[] = 'ALTER TABLE `'.$name.'` ENGINE=InnoDB;';
    return $fix ? 'These tables are not InnoDB, convert them and start the update again: '.implode(' ', $fix) : '';
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
    if (hash_file('sha256', $dir.'/balances.json') !== ($info['source']['balances.json'] ?? '')) return getInfo('points: the snapshot does not match its manifest', false);
    if ($info['state'] !== 'verified') {
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('points: the manifest could not be written', false);
        $users = (require CONFIG_DIR.'/users.php')['users'] ?? [];
        $point = (require CONFIG_DIR.'/points.php')['points'] ?? [];
        $flag = isset($users['point']) ? ($users['point'] ? '1' : '0') : ($point['active'] ?? '');
        $moved = $flag !== ($point['active'] ?? '');
        $stale = isset($users['point']) || isset($users['points']);
        $point['active'] = $flag;
        unset($users['point'], $users['points']);
        if (count($point['actions'] ?? []) !== 15 || !in_array($point['active'], ['0', '1'], true)) return getInfo('points: config/points.php is not a valid points scope', false);
        if ($moved) setConfigFile('points.php', $point);
        if ($stale) setConfigFile('users.php', $users);
        $info['target'] = ['points.php' => hash_file('sha256', CONFIG_DIR.'/points.php'), 'users.php' => hash_file('sha256', CONFIG_DIR.'/users.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('points: the manifest could not be written', false);
    }
    setConfigFile('update.php', ['points' => '6.3.0'] + $mark);
    return getInfo('points: starting balances kept ('.intval($info['count']).' accounts), the subsystem is open', true);
}

# The ratings unit of the 6.3 data update: keep the aggregate of every remaining target as its starting balance, carry the last participation over and publish the four-key rules
# Nothing is written before the whole preflight passed: a broken aggregate, a broken time or address of a kept row and a broken rule stop the unit with the table and the id
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a row that is already stored has to equal its snapshot
# Rows of the new tables without a manifest stop it, because a current aggregate is never taken for a starting one; rows of polls and of other events are counted and left alone
function setUpdateRatings(Database $db, string $prefix): string {
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'votes', 'tvotes', ''], 'forum' => ['forum', 'ratings', 'score', ' WHERE pid = 0'], 'shop' => ['products', 'votes', 'tvotes', '']];
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
    foreach (['targets.json', 'terms.json', 'rules.json'] as $name) {
        $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === ($info['source'][$name] ?? '');
        if (!$same) return getInfo('ratings: the snapshot '.$name.' does not match its manifest', false);
    }
    if ($info['state'] !== 'verified') {
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
        $sets = ['targets' => ['targets.json', 'rating_targets', ['scope', 'mid', 'base', 'votes'], 2]];
        $sets['terms'] = ['terms.json', 'rating_actors', ['scope', 'mid', 'actor', 'last'], 3];
        foreach ($sets as $kind => [$snap, $tab, $cols, $knum]) {
            $list = json_decode((string)file_get_contents($dir.'/'.$snap), true);
            $keys = array_slice($cols, 0, $knum);
            $cond = implode(' AND ', array_map(fn($v) => $v.' = :'.$v, $keys));
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
        if (((require CONFIG_DIR.'/ratings.php')['ratings'] ?? null) !== $rules) setConfigFile('ratings.php', $rules);
        $info['target'] = $real + ['ratings.php' => hash_file('sha256', CONFIG_DIR.'/ratings.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('ratings: the manifest could not be written', false);
    }
    setConfigFile('update.php', ['ratings' => '6.3.0'] + $mark);
    $stat = $info['count'];
    $text = 'ratings: starting aggregates kept ('.intval($stat['targets']).' targets, '.intval($stat['terms']).' terms), left alone in the old table: '
        .intval($stat['voting']).' poll rows, '
        .intval($stat['foreign']).' rows of other events, '.intval($stat['orphan']).' rows of missing targets; rules dropped: '.intval($stat['dropped']).'; the subsystem is open';
    return getInfo($text, true);
}

# Read the positional 6.2 definitions of one area and answer [named definitions, slot map by position, number of positions]; every refusal goes to $bad and nothing is guessed
# The four slots are caption, content, type and duty: caption 0 switches a position off, content is the default of a text, the comma list of a select or the default of a date
# Keys are field1, field2 and so on by the original position without closing gaps, options are option1, option2 in their original order, and only an exact 1 makes a field required
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
        $part = explode('|', $item);
        if ($item === '' || $part[0] === '0') continue;
        $type = $types[$part[2] ?? ''] ?? '';
        if (count($part) !== 4 || $type === '') {
            $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (four slots and a type from 1 to 5 are expected)';
            continue;
        }
        $rule = ['title' => $part[0], 'intro' => '', 'type' => $type, 'default' => '', 'options' => [], 'req' => $part[3] === '1', 'multi' => false];
        $rule += ['active' => true, 'sort' => ($pos + 1) * 10];
        $items = [];
        foreach ($type === 'select' ? explode(',', $part[1]) : [] as $label) {
            if ($label === '') continue;
            if (isset($items[$label])) $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (an option caption repeats)';
            $items[$label] = 'option'.(count($items) + 1);
            $rule['options']['items'][$items[$label]] = ['title' => (string)$label, 'active' => true, 'sort' => count($items) * 10];
        }
        if ($type !== 'select' && $part[1] !== '' && $part[1] !== '0') $rule['default'] = ($type === 'datetime') ? str_replace(' ', 'T', $part[1]) : $part[1];
        $rules['field'.($pos + 1)] = $rule;
        $slots[$pos] = ['name' => 'field'.($pos + 1), 'type' => $type, 'items' => $items];
    }
    try {
        $rules = $fld->filterFieldList($rules);
    } catch (InvalidArgumentException $err) {
        $bad[] = 'config/fields.php '.$area.' '.$err->getMessage().' (the shared check of definitions refused it)';
    }
    return [$rules, $slots, count($list)];
}

# Turn one positional 6.2 value row into the canonical JSON of its area and answer ['json' => text], or ['why' => reason] without any of the stored data in it
# The old view indexed every position while a posted form could leave the switched off ones out, so both layouts are tried and only one confirmed result is accepted
# An empty part is absence; 0 is a value of a text and the placeholder of an empty choice in a select without such an option, in a date and in a position without a definition
function getUpdateValue(array $rules, array $slots, int $size, string $text, Field $fld): array {
    $part = explode('|', $text);
    $found = [];
    $why = '';
    foreach (['full' => range(0, max($size, count($part)) - 1), 'short' => array_keys($slots)] as $plan => $order) {
        $vals = [];
        $fail = '';
        foreach ($part as $num => $val) {
            $slot = isset($order[$num]) ? ($slots[$order[$num]] ?? null) : null;
            $spot = 'value '.($num + 1);
            if ($slot === null) {
                if ($val !== '' && $val !== '0') $fail = $spot.' holds data and has no definition';
            } elseif ($slot['type'] === 'select') {
                if (isset($slot['items'][$val])) $vals[$slot['name']] = $slot['items'][$val];
                elseif ($val !== '' && $val !== '0') $fail = $spot.' is no option of '.$slot['name'];
            } elseif ($slot['type'] === 'date' || $slot['type'] === 'datetime') {
                if ($val !== '' && $val !== '0') $vals[$slot['name']] = ($slot['type'] === 'datetime') ? str_replace(' ', 'T', $val) : $val;
            } elseif ($val !== '') {
                $vals[$slot['name']] = $val;
            }
            if ($fail !== '') break;
        }
        $errs = ($fail === '') ? $fld->checkFieldValues($rules, $vals, false) : [];
        foreach ($errs as $name => $code) $fail = $name.' is refused by the shared check: '.$code;
        if ($fail === '') {
            $data = $fld->filterFieldValues($rules, $vals);
            $found[$data ? (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''] = true;
        } elseif ($plan === 'full') {
            $why = $fail;
        }
    }
    if (count($found) === 1) return ['json' => array_key_first($found)];
    return ['why' => $found ? 'the full and the short layout both fit and differ' : $why];
}

# The fields unit of the 6.3 data update: name the positional definitions of account, forum and order and turn every stored value row into one canonical JSON object
# Nothing is written before the whole preflight passed: a definition or a row that cannot be mapped without guessing stops the unit with the table, the id and the reason
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a stored row has to equal its source or its target
# Definitions that are already named while positional rows exist and no manifest does stop it, because the old definitions are the only key to those rows
function setUpdateFields(Database $db, string $prefix): string {
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'field'], 'forum' => ['forum', 'field'], 'order' => ['order', 'info']];
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
            $snaps[$area] = [];
            foreach (count($bad) > $base ? [] : $rows as [$mid, $text]) {
                $memo[$text] ??= getUpdateValue($rules[$area], $slots, $size, $text, $fld);
                if (isset($memo[$text]['why'])) $bad[] = $prefix.'_'.$tab.' '.$mid.' ('.$memo[$text]['why'].')';
                else $snaps[$area][] = [$mid, $text, $memo[$text]['json']];
            }
        }
        if ($bad) return getInfo('fields: the preflight found data it will not guess, nothing was written ('.count($bad).'): '.implode(', ', array_slice($bad, 0, 50)), false);
        try {
            foreach ($rules as $area => $set) $rules[$area] = $fld->filterFieldList($set);
            if ($named) $rules += $old;
            ksort($rules);
        } catch (InvalidArgumentException $err) {
            return getInfo('fields: config/fields.php '.$area.' '.$err->getMessage().' is refused by the shared check of definitions, nothing was written', false);
        }
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getInfo('fields: '.$dir.' could not be created', false);
        $text = ['definitions.json' => json_encode(['source' => $old, 'rules' => $rules], $flag)];
        foreach ($snaps as $area => $list) $text[$area.'.json'] = json_encode($list, $flag);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => array_fill_keys(array_keys($maps), 0), 'source' => [], 'target' => []];
        $info['count'] = array_map('count', $snaps);
        foreach ($text as $name => $body) {
            if (!is_string($body) || !setUpdateBackup($dir.'/'.$name, $body)) return getInfo('fields: the snapshot '.$name.' could not be written', false);
            $info['source'][$name] = hash('sha256', $body);
        }
        if (!setUpdateBackup($file, (string)json_encode($info))) return getInfo('fields: the manifest could not be written', false);
    }
    foreach (array_keys($info['source']) as $name) {
        $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $info['source'][$name];
        if (!$same) return getInfo('fields: the snapshot '.$name.' does not match its manifest', false);
    }
    if ($info['state'] !== 'verified') {
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
    setConfigFile('update.php', ['fields' => '6.3.0'] + $mark);
    $stat = $info['count'];
    $text = 'fields: named definitions published, value rows carried over: '.intval($stat['account']).' accounts, '.intval($stat['forum']).' forum posts, ';
    return getInfo($text.intval($stat['order']).' orders; the subsystem is open', true);
}

function save(): void {
    global $title, $clang, $conf, $url;
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

    $cont = ['language' => $clang, 'homeurl' => $url];
    setConfigFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), $cont);
    $conf = array_merge($conf, require CONFIG_DIR.'/global.php');

    $tafile = ($conf['security']['afile']) ? $conf['security']['afile'] : 'admin';
    if (file_exists($tafile.'.php') && !file_exists($xafile.'.php')) {
        if (!@rename($tafile.'.php', $xafile.'.php')) {
            $xafile = $tafile;
        }
    } else {
        $xafile = file_exists($xafile.'.php') ? $xafile : $tafile;
    }
    $cont = ['afile' => $xafile];
    setConfigFile('security.php', $conf['security'], $cont);
    $conf = array_merge($conf, require CONFIG_DIR.'/security.php');
    
    $conf = array_merge($conf, require CONFIG_DIR.'/db.php');
    $cont = ['host' => $xhost, 'uname' => $xuname, 'pass' => $xpass, 'name' => $xname, 'engine' => $xengine, 'charset' => $xcharset, 'collate' => $xcollate, 'prefix' => $xprefix, 'sync' => $xsync];
    setConfigFile('db.php', $conf['db'], $cont);

    require_once BASE_DIR.'/core/classes/pdo.php';
    $db = new Database($xhost, $xuname, $xpass, $xname, $xcharset);
    
    $bodytext = '';
    if ($setup == 'new') {
        $title = _SAVE_NEW;
        $bodytext .= getSqlFile('setup/sql/table.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
        $bodytext .= getSqlFile('setup/sql/insert.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
        setConfigFile('update.php', ['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0']);
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
        while (list($aid, $apwd) = $db->getSqlRow($result)) {
            $pwdhash = getCrypt($apwd);
            $db->getSqlQuery('UPDATE '.$xprefix.'_admins SET password = :password WHERE id = :id', ['password' => $pwdhash, 'id' => $aid]);
        }
        $bodytext .= getInfo($xprefix.'_admins', $result);
        $result = $db->getSqlQuery('SELECT id, password FROM '.$xprefix.'_users');
        while (list($uid, $upwd) = $db->getSqlRow($result)) {
            $pwdhash = getCrypt($upwd);
            $db->getSqlQuery('UPDATE '.$xprefix.'_users SET password = :pwd WHERE id = :uid', ['pwd' => $pwdhash, 'uid' => $uid]);
        }
        $bodytext .= getInfo($xprefix.'_users', $result);
        $bodytext .= getSqlFile('setup/sql/table_update5_0.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update5_1') {
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update5_1.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);

        $result = $db->getSqlQuery('SELECT poll_id, poll_date, poll_title, poll_questions, poll_answer_1, poll_answer_2, poll_answer_3, poll_answer_4, poll_answer_5, poll_answer_6, poll_answer_7, poll_answer_8, poll_answer_9, poll_answer_10, poll_answer_11, poll_answer_12, pool_comments, planguage, acomm FROM '.$xprefix.'_voting_temp');
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
            $db->getSqlQuery('INSERT INTO '.$xprefix.'_voting (id, modul, title, body, answer, time, enddate, multi, comments, language, acomm, ip, typ, status) VALUES (:id, \'\', :title, :body, :answer, :date, \'2020-05-23 20:58:00\', 0, :comments, :language, :acomm, :ip, 1, 1)', [
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
        while (list($id, $raw) = $db->getSqlRow($result)) {
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
        while (list($id, $raw) = $db->getSqlRow($result)) {
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
        $stop = checkUpdateBase($db, $xprefix);
        if ($stop !== '') setExit($stop);
        setConfigFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), ['close' => '1']);
        $conf['close'] = '1';
        $bodytext .= getInfo('the site is closed for the data update (close = 1), open it in the settings after the result is checked', true);
        $cont = [];
        $apath = BASE_DIR.'/admin/modules';
        if (is_dir($apath) && ($handle = opendir($apath))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match('/^([a-z_]+)\.php$/i', $file, $matches)) {
                    $module = $matches[1];
                    $cont[$module] = [
                        'lang' => '_'.strtoupper($module),
                        'img' => strtolower($module).'.png',
                        'active' => 1,
                        'view' => 0,
                        'menu' => 1,
                        'group' => 0,
                        'side' => 0,
                        'top' => 0,
                        'type' => 0,
                    ];
                }
            }
            closedir($handle);
        }
        $mpath = BASE_DIR.'/modules';
        if (is_dir($mpath) && ($handle = opendir($mpath))) {
            while (false !== ($file = readdir($handle))) {
                if (!preg_match('/\./', $file) && (file_exists($mpath.'/'.$file.'/index.php') || file_exists($mpath.'/'.$file.'/admin/index.php'))) {
                    $cont[$file] = [
                        'lang' => '_'.strtoupper($file),
                        'img' => strtolower($file).'.png',
                        'active' => 0,
                        'view' => 0,
                        'menu' => 1,
                        'group' => 0,
                        'side' => 0,
                        'top' => 0,
                        'type' => 1,
                    ];
                }
            }
            closedir($handle);
        }
        $hasmod = false;
        $tbl = $xprefix.'_modules';
        $tblres = $db->getSqlQuery('SHOW TABLES LIKE :tbl', ['tbl' => $tbl]);
        if ($tblres && $db->getSqlRowCount($tblres) > 0) $hasmod = true;
        if ($hasmod) {
            $map = [];
            $result = $db->getSqlQuery('SELECT mid, title, active, view, inmenu, mod_group, blocks, blocks_c FROM '.$tbl);
            while ($row = $db->getSqlRow($result)) {
                $title = $row['title'];
                $map[(string)$row['mid']] = $title;
                if (!isset($cont[$title])) {
                    $cont[$title] = [
                        'lang' => '_'.strtoupper($title),
                        'img' => strtolower($title).'.png',
                        'active' => 0,
                        'view' => 0,
                        'menu' => 1,
                        'group' => 0,
                        'side' => 0,
                        'top' => 0,
                        'type' => 1,
                    ];
                }
                $cont[$title]['active'] = $row['active'];
                $cont[$title]['view'] = $row['view'];
                $cont[$title]['menu'] = $row['inmenu'];
                $cont[$title]['group'] = $row['mod_group'];
                $cont[$title]['side'] = $row['blocks'];
                $cont[$title]['top'] = $row['blocks_c'];
            }
            if (!empty($map)) {
                $result = $db->getSqlQuery('SELECT id, modules FROM '.$xprefix.'_admins');
                while ($row = $db->getSqlRow($result)) {
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
                    $names = array_values(array_unique($names));
                    $newmod = implode(',', $names);
                    if ($newmod !== $modules) {
                        $db->getSqlQuery('UPDATE '.$xprefix.'_admins SET modules = :modules WHERE id = :id', [
                            'modules' => $newmod,
                            'id' => $row['id'],
                        ]);
                    }
                }
            }
        }
        $exfile = CONFIG_DIR.'/modules.php';
        if (file_exists($exfile)) {
            $exdata = require $exfile;
            $existing = $exdata['modules'] ?? [];
            $cont = array_merge($cont, $existing);
        }
        setConfigFile('modules.php', $cont);
        $nlist = [];
        $ntable = $xprefix.'_newsletter';
        $ncols = $db->getSqlQuery('SHOW COLUMNS FROM `'.$ntable.'` LIKE :col', ['col' => 'mails']);
        if ($ncols && $db->getSqlRowCount($ncols) > 0) {
            $result = $db->getSqlQuery('SELECT id, title, mails FROM `'.$ntable.'` WHERE mails IS NOT NULL AND mails != \'\'');
            while ($row = $db->getSqlRow($result)) $nlist[(int)$row['id']] = ['title' => (string)$row['title'], 'mails' => (string)$row['mails']];
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
                    'priority' => '2',
                    'lock_timeout' => '900',
                    'manual' => '1',
                    'settings' => [],
                ];
                $sdone = true;
            }
            # Node delivers the reward of a future publication when its date comes, through a system job of its own that an upgraded site has not carried yet
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
            # Node checks the external sources of the type sync through a system job of its own that an upgraded site has not carried yet
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
            # A site upgraded while the nightly counter sweep still existed carries the job in its own config, and the sweep now happens on every write
            if (is_array($sched) && isset($sched['jobs']['commentsync'])) {
                unset($sched['jobs']['commentsync']);
                $sdone = true;
            }
            if (is_array($sched) && isset($sched['jobs']['newsletter']) && is_array($sched['jobs']['newsletter'])) {
                $sched['jobs']['newsletter']['active'] = '1';
                $sched['jobs']['newsletter']['schedule'] = '*/5 * * * *';
                $sdone = true;
            }
            # The database backup job gained explicit scope, compression, and retention settings; only missing keys are filled so a configured value is never overwritten
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
            if ($sdone) setConfigFile('scheduler.php', $sched);
        }
        setConfigFile('newsletter.php', ['abort' => '10', 'bouncemax' => '2', 'breakwin' => '100', 'canary' => '100', 'canarymin' => '500']);
        $title = _SAVE_UPDATE;
        $bodytext .= getSqlFile('setup/sql/table_update6_3.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
        $bodytext .= setUpdatePoints($db, $xprefix);
        $bodytext .= setUpdateRatings($db, $xprefix);
        $bodytext .= setUpdateFields($db, $xprefix);
        $rfile = CONFIG_DIR.'/rss.php';
        $rdata = is_file($rfile) ? ((require $rfile)['rss'] ?? []) : [];
        if (is_array($rdata) && $rdata !== [] && (isset($rdata['temp']) || !isset($rdata['bytes'], $rdata['redirects'], $rdata['timeout']))) {
            unset($rdata['temp']);
            setConfigFile('rss.php', $rdata + ['bytes' => '2097152', 'redirects' => '3', 'timeout' => '10']);
        }
        $rnum = $db->getSqlQuery('UPDATE `'.$xprefix.'_blocks` SET content = \'\', time = \'0\' WHERE url != \'\'');
        $bodytext .= getInfo($xprefix.'_blocks RSS bodies cleared for Markdown (rows: '.($rnum ? $db->getSqlRowCount($rnum) : 0).')', $rnum !== false);
        $nsent = 0;
        foreach ($nlist as $nid => $one) {
            $mails = array_values(array_unique(array_filter(array_map('trim', explode(',', $one['mails'])), 'strlen')));
            foreach ($mails as $mail) {
                if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
                $sql = 'INSERT INTO `'.$xprefix.'_mail` (kind, sender, email, title, body, ref, prio, time, ntime)'
                    .' VALUES (\'newsletter\', :from, :mail, :title, \'\', :ref, 3, NOW(), NOW())';
                $db->getSqlQuery($sql, ['from' => (string)$conf['adminmail'], 'mail' => $mail, 'title' => substr($one['title'], 0, 255), 'ref' => $nid]);
                $nsent++;
            }
            $sql = 'UPDATE `'.$ntable.'` SET status = 5, audit = \'list\', expect = :num, total = :num WHERE id = :id';
            $db->getSqlQuery($sql, ['num' => count($mails), 'id' => $nid]);
        }
        $bodytext .= getInfo($ntable.' pending recipients moved into the mail queue (rows written: '.$nsent.')', true);
        [$acount] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$xprefix.'_users` WHERE `avatar` LIKE \'default/%\''));
        $bodytext .= getInfo($xprefix.'_users avatar migration (legacy rows left: '.(int)$acount.')', (int)$acount === 0);
    }
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    setHead();
    echo '<table class="sl_table">'.$bodytext.'</table>'
    .'<div class="sl_center"><form action="'.$conf['security']['afile'].'.php" method="post">'._GOBACK.' <input type="submit" value="'._ADMIN_SE.'" class="sl_but_blue"></form></div>';
    setFoot();
}

switch($op) {
    default: language(); break;
    case 'lang': lang(); break;
    case 'config': config(); break;
    case 'save': save(); break;
}
