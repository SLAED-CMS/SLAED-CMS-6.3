<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('SETUP_FILE')) die('Illegal File Access');
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));

require_once BASE_DIR.'/config/config_global.php';
require_once BASE_DIR.'/config/config_security.php';

if ($confs['error'] == 2) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} elseif ($confs['error'] == 1) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL ^ E_NOTICE);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

if (function_exists('set_time_limit')) set_time_limit(1800);
$host = getenv('HTTP_HOST') ? getenv('HTTP_HOST') : getenv('SERVER_NAME');
$url = getProtocol().'://'.$host;
$clang = isset($_COOKIE[$conf['user_c'].'-language']) ? isVar($_COOKIE[$conf['user_c'].'-language']) : 'en';
$op = (isset($_REQUEST['op'])) ? isVar($_REQUEST['op']) : '';

require_once BASE_DIR.'/language/'.$clang.'.php';
require_once BASE_DIR.'/setup/language/'.$clang.'.php';

if (version_compare(PHP_VERSION, '8.1.0', '<')) setExit(_PHPSETUP);
if ($conf['lic_h'] != 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt' || $conf['lic_f'] != 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==') setExit(_NO_LICENSE);
$copyright = base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']);

function doConfig(string $fp, string $name, array $array, array|string $actual = '', string $type = ''): void {
    if (is_array($array) && $name) {
        if (is_array($actual)) $array += $actual;
        ksort($array);
        array_walk($array, function (&$v) { $v = is_bool($v) ? strval(intval($v)) : strval($v); });
        $cons = empty($type) ? 'FUNC_FILE' : 'ADMIN_FILE';
        $cont = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL.'if (!defined(\''.$cons.'\')) die(\'Illegal file access\');'.PHP_EOL.PHP_EOL.'$'.$name.' = '.var_export($array, true).';';
        file_put_contents($fp, $cont, LOCK_EX);
    }
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

function getPass(int $m): string {
    $pass = '';
    for ($i = 0; $i < $m; $i++) {
        $te = mt_rand(48, 122);
        if (($te > 57 && $te < 65) || ($te > 90 && $te < 97)) $te = $te - 9;
        $pass .= chr($te);
    }
    return $pass;
}

function getCrypt(string $pass): string {
    $crypt = md5('0a958d066ab41444be55359c31702bcf'.$pass);
    return $crypt;
}

function executeSqlFile(string $file, string $prefix, string $engine, string $charset, string $collate, $db): string {
    $file = BASE_DIR.'/'.$file;
    if (!file_exists($file)) return '';

    $content = file_get_contents($file);
    $queries = explode(';', $content);
    $output = '';

    foreach ($queries as $query) {
        $query = str_replace(
            ['{prefix}', '{engine}', '{charset}', '{collate}'],
            [$prefix, $engine, $charset, $collate],
            trim($query)
        );

        if (empty($query)) continue;

        $result = $db->sql_query($query);

        if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $query)) {
            preg_match('#`([^`]+)`#', $query, $match);
            $table = $match[1] ?? '';
            $output .= getInfo($table, $result);
        }
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
    echo '<!doctype html>'.PHP_EOL
    .'<html lang="'.substr(_LOCALE, 0, 2).'">'.PHP_EOL
    .'<head>'.PHP_EOL
    .'<meta charset="'._CHARSET.'">'.PHP_EOL
    .'<title>'._SETUP_SLAED.' - '.$title.'</title>'.PHP_EOL
    .'<meta name="resource-type" content="document">'.PHP_EOL
    .'<meta name="document-state" content="dynamic">'.PHP_EOL
    .'<meta name="distribution" content="global">'.PHP_EOL
    .'<meta name="author" content="'.$conf['sitename'].'">'.PHP_EOL
    .'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'.PHP_EOL
    .'<link rel="stylesheet" href="setup/templates/style.css">'.PHP_EOL
    .'</head>'.PHP_EOL
    .'<body id="page_bg">'.PHP_EOL
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
    global $copyright;
    echo '</div>'
    .'</div>'
    .'</div>'
    .'<div id="footer">'
    .'<div id="footer-r">'
    .'<div id="footer-l">'
    .'<div id="copyright">'.$copyright.'</div>'
    .'</div>'
    .'</div>'
    .'</div>'
    .'</div>'.PHP_EOL
    .'</body>'.PHP_EOL
    .'</html>';
}

function setExit(string $msg, string $typ = ''): never {
    global $conf;
    $cont = '<!doctype html>'.PHP_EOL
    .'<html lang="'.substr(_LOCALE, 0, 2).'">'.PHP_EOL
    .'<head>'.PHP_EOL
    .'<meta charset="'._CHARSET.'">'.PHP_EOL
    .'<title>'._SETUP_SLAED.'</title>'.PHP_EOL
    .'<meta name="author" content="'.$conf['sitename'].'">'.PHP_EOL
    .'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'.PHP_EOL;
    $cont .= ($typ) ? '<meta http-equiv="refresh" content="5; url='.$conf['homeurl'].'/index.php">'.PHP_EOL : '';
    $cont .= '<link rel="stylesheet" href="setup/templates/style.css">'.PHP_EOL
    .'</head>'.PHP_EOL
    .'<body>'.PHP_EOL
    .'<div style="margin: 25%;">'.PHP_EOL
    .'<div style="text-align: center;"><img src="setup/templates/images/logotype.png" alt="'.$conf['sitename'].'" title="'.$conf['sitename'].'"></div>'.PHP_EOL
    .'<div style="margin-top: 50px; font: 18px Arial, Tahoma, sans-serif, Verdana; color: #1a4674; font-weight: bold; text-align: center;">'.$msg.'</div>'.PHP_EOL
    .'<div style="margin-top: 50px; text-align: center;">'._GOBACK.'</div>'.PHP_EOL
    .'</div>'.PHP_EOL
    .'</body>'.PHP_EOL
    .'</html>';
    die($cont);
}

function isVar(string $v): string {
    $v = (preg_match('#[^a-zA-Z0-9_\-]#', $v)) ? '' : $v;
    return $v;
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
    $langlist = array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), glob('setup/language/*.php'));
    sort($langlist);
    $a = 3;
    $i = 1;
    $tdwidth = intval(100/$a);
    foreach ($langlist as $key => $val) {
        $altlang = getLang($langlist[$key]);
        if (($i - 1) % $a == 0) $cont .= '<tr>';
        $cont .= '<td style="width: '.$tdwidth.'%;" class="sl_center"><a href="setup.php?op=lang&amp;id='.$langlist[$key].'" title="'.$altlang.'"><img src="setup/templates/images/'.$langlist[$key].'.png" alt="'.$altlang.'"><br><b>'.$altlang.'</b></a></td>';
        if ($i % $a == 0) $cont .= '</tr>'.PHP_EOL;
        $i++;
    }
    if ($clang) {
        $cont .= '<tr><td colspan="'.$a.'" class="sl_center"><form action="setup.php" method="post"><input type="hidden" name="op" value="config"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></form></td></tr>';
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
    $options = array('expires' => $time, 'path' => '/', 'domain' => $url['host'], 'secure' => $sec, 'httponly' => true, 'samesite' => 'Lax');
    setcookie($conf['user_c'].'-language', $lang, $options);
    header('Location: setup.php');
}

function config(): void {
    global $title, $conf, $confs;
    $title = _CONFIG;
    checkWritableConfig('config/db.php');
    checkWritableConfig('config/config_global.php');
    require_once BASE_DIR.'/config/db.php';
    $xhost = ($confdb['host']) ? $confdb['host'] : 'localhost';
    $xuname = ($confdb['uname']) ? $confdb['uname'] : '';
    $xpass = ($confdb['pass']) ? $confdb['pass'] : '';
    $xname = ($confdb['name']) ? $confdb['name'] : '';
    $xprefix = ($confdb['prefix']) ? $confdb['prefix'] : getPass('10');
    $xafile = ($confs['afile']) ? $confs['afile'] : strtolower(getPass('10'));
    $info = sprintf(_CONF_10_INFO, strtolower(getPass('10')));
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
    global $title, $clang, $conf, $confs, $url;
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

    $cont = array('language' => $clang, 'homeurl' => $url);
    doConfig('config/config_global.php', 'conf', $cont, $conf, '');
    require_once BASE_DIR.'/config/config_global.php';

    $tafile = ($confs['afile']) ? $confs['afile'] : 'admin';
    if (file_exists($tafile.'.php') && !file_exists($xafile.'.php')) {
        if (!@rename($tafile.'.php', $xafile.'.php')) {
            $xafile = $tafile;
        }
    } else {
        $xafile = file_exists($xafile.'.php') ? $xafile : $tafile;
    }
    $cont = array('afile' => $xafile);
    doConfig('config/config_security.php', 'confs', $cont, $confs, '');
    require_once BASE_DIR.'/config/config_security.php';
    
    require_once BASE_DIR.'/config/db.php';
    $cont = array('host' => $xhost, 'uname' => $xuname, 'pass' => $xpass, 'name' => $xname, 'engine' => $xengine, 'charset' => $xcharset, 'collate' => $xcollate, 'prefix' => $xprefix, 'sync' => $xsync);
    doConfig('config/db.php', 'confdb', $cont, $confdb, '');

    require_once 'core/classes/pdo.php';
    $db = new sql_db($xhost, $xuname, $xpass, $xname, $xcharset);
    
    $bodytext = '';
    if ($setup == 'new') {
        $title = _SAVE_NEW;
        $bodytext .= executeSqlFile('setup/sql/table.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
        $bodytext .= executeSqlFile('setup/sql/insert.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update4_1') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update4_1.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update4_2') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update4_2.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update4_3') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update4_3.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update5_0') {
        $title = _SAVE_UPDATE;
        $result = $db->sql_query('SELECT id, pwd FROM '.$xprefix.'_admins');
        while (list($a_id, $a_pwd) = $db->sql_fetchrow($result)) {
            $pwd_hash = getCrypt($a_pwd);
            $db->sql_query('UPDATE '.$xprefix.'_admins SET pwd = :pwd WHERE id = :id', ['pwd' => $pwd_hash, 'id' => $a_id]);
        }
        $bodytext .= getInfo($xprefix.'_admins', $result);
        $result = $db->sql_query('SELECT user_id, user_password FROM '.$xprefix.'_users');
        while (list($user_id, $user_password) = $db->sql_fetchrow($result)) {
            $pwd_hash = getCrypt($user_password);
            $db->sql_query('UPDATE '.$xprefix.'_users SET user_password = :pwd WHERE user_id = :uid', ['pwd' => $pwd_hash, 'uid' => $user_id]);
        }
        $bodytext .= getInfo($xprefix.'_users', $result);
        $bodytext .= executeSqlFile('setup/sql/table_update5_0.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update5_1') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update5_1.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);

        $result = $db->sql_query('SELECT poll_id, poll_date, poll_title, poll_questions, poll_answer_1, poll_answer_2, poll_answer_3, poll_answer_4, poll_answer_5, poll_answer_6, poll_answer_7, poll_answer_8, poll_answer_9, poll_answer_10, poll_answer_11, poll_answer_12, pool_comments, planguage, acomm FROM '.$xprefix.'_voting_temp');
        while (list($poll_id, $poll_date, $poll_title, $poll_questions, $poll_answer_1, $poll_answer_2, $poll_answer_3, $poll_answer_4, $poll_answer_5, $poll_answer_6, $poll_answer_7, $poll_answer_8, $poll_answer_9, $poll_answer_10, $poll_answer_11, $poll_answer_12, $pool_comments, $planguage, $acomm) = $db->sql_fetchrow($result)) {
            $questions = substr($poll_questions, 0, -1);
            $array_answ = [$poll_answer_1, $poll_answer_2, $poll_answer_3, $poll_answer_4, $poll_answer_5, $poll_answer_6, $poll_answer_7, $poll_answer_8, $poll_answer_9, $poll_answer_10, $poll_answer_11, $poll_answer_12];
            $answ = [];
            foreach ($array_answ as $val) if (!empty($val)) $answ[] = trim($val);
            $answ = implode('|', $answ);
            $db->sql_query('INSERT INTO '.$xprefix.'_voting (id, modul, title, questions, answer, date, enddate, multi, comments, language, acomm, ip, typ, status) VALUES (:id, \'\', :title, :questions, :answer, :date, \'2020-05-23 20:58:00\', 0, :comments, :language, :acomm, :ip, 1, 1)', [
                'id' => $poll_id,
                'title' => $poll_title,
                'questions' => $questions,
                'answer' => $answ,
                'date' => $poll_date,
                'comments' => $pool_comments,
                'language' => $planguage,
                'acomm' => $acomm,
                'ip' => getIp()
            ]);
            $db->sql_query('DROP TABLE '.$xprefix.'_voting_temp');
        }
        $bodytext .= getInfo($xprefix.'_voting', $result);

        $result = $db->sql_query('SELECT sid, associated FROM '.$xprefix.'_news');
        while (list($id, $associated) = $db->sql_fetchrow($result)) {
            $associated = explode('-', $associated);
            if (is_array($associated)) {
                $assoc = [];
                foreach ($associated as $val) {
                    if (!empty($val)) $assoc[] = trim($val);
                }
                $assoc = implode(',', $assoc);
            } else {
                $assoc = '';
            }
            $db->sql_query('UPDATE '.$xprefix.'_news SET associated = :assoc WHERE sid = :id', ['assoc' => $assoc, 'id' => $id]);
        }
        $bodytext .= getInfo($xprefix.'_news', $result);

        $result = $db->sql_query('SELECT id, assoc FROM '.$xprefix.'_products');
        while (list($id, $associated) = $db->sql_fetchrow($result)) {
            $associated = explode('-', $associated);
            if (is_array($associated)) {
                $assoc = [];
                foreach ($associated as $val) {
                    if (!empty($val)) $assoc[] = trim($val);
                }
                $assoc = implode(',', $assoc);
            } else {
                $assoc = '';
            }
            $db->sql_query('UPDATE '.$xprefix.'_products SET assoc = :assoc WHERE id = :id', ['assoc' => $assoc, 'id' => $id]);
        }
        $bodytext .= getInfo($xprefix.'_products', $result);
    } elseif ($setup == 'update6_0') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update6_0.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update6_2') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update6_2.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    } elseif ($setup == 'update6_3') {
        $title = _SAVE_UPDATE;
        $bodytext .= executeSqlFile('setup/sql/table_update6_3.sql', $xprefix, $xengine, $xcharset, $xcollate, $db);
    }
    
    setHead();
    echo '<table class="sl_table">'.$bodytext.'</table>'
    .'<div class="sl_center"><form action="'.$confs['afile'].'.php" method="post">'._GOBACK.' <input type="submit" value="'._ADMIN_SE.'" class="sl_but_blue"></form></div>';
    setFoot();
}

switch($op) {
    default: language(); break;
    case 'lang': lang(); break;
    case 'config': config(); break;
    case 'save': save(); break;
}
