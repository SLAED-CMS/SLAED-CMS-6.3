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

# Saving configurations to a file
function setConfigFile(string $fp, array $arr, array $act = []): void {
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
    foreach ($arr as $key => $val) $arr[$key] = $norm($val);
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
