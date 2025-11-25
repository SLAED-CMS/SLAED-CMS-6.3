<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('SETUP_FILE')) die('Illegal File Access');
define('FUNC_FILE', true);

include('config/config_global.php');
include('config/config_security.php');

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

$is_safe_mode = (ini_get('safe_mode') == '1') ? 1 : 0;
if (!$is_safe_mode && function_exists('set_time_limit')) set_time_limit(1800);
$host = getenv('HTTP_HOST') ? getenv('HTTP_HOST') : getenv('SERVER_NAME');
$url = getProtocol().'://'.$host;
$clang = isset($_COOKIE[$conf['user_c'].'-language']) ? isVar($_COOKIE[$conf['user_c'].'-language']) : 'english';
$op = (isset($_REQUEST['op'])) ? isVar($_REQUEST['op']) : '';

include_once('language/lang-'.$clang.'.php');
include_once('setup/language/lang-'.$clang.'.php');

if ($conf['lic_h'] != 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt' || $conf['lic_f'] != 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==') setExit(_NO_LICENSE);
$copyright = base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']);

function doConfig($fp, $name, $array, $actual='', $type='') {
	if (is_array($array) && $name) {
		if (is_array($actual)) $array += $actual;
		ksort($array);
		array_walk($array, function (&$v) { $v = is_bool($v) ? strval(intval($v)) : strval($v); });
		$cons = empty($type) ? 'FUNC_FILE' : 'ADMIN_FILE';
		$cont = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL.'if (!defined(\''.$cons.'\')) die(\'Illegal file access\');'.PHP_EOL.PHP_EOL.'$'.$name.' = '.var_export($array, true).';';
		file_put_contents($fp, $cont, LOCK_EX);
	}
}

function getProtocol() {
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

function getPass($m) {
	$m = intval($m);
	$pass = '';
	for ($i = 0; $i < $m; $i++) {
		$te = mt_rand(48, 122);
		if (($te > 57 && $te < 65) || ($te > 90 && $te < 97)) $te = $te - 9;
		$pass .= chr($te);
	}
	return $pass;
}

function getCrypt($pass) {
	$crypt = md5('0a958d066ab41444be55359c31702bcf'.$pass);
	return $crypt;
}

function getIp() {
	if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
		$ip = getenv('REMOTE_ADDR');
	} elseif (!empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
		$ip = $_SERVER['REMOTE_ADDR'];
	} else {
		$ip = '0.0.0.0';
	}
	return $ip;
}

function getLang($con) {
	$langs = array('english' => _ENGLISH, 'french' => _FRENCH, 'german' => _GERMAN, 'polish' => _POLISH, 'russian' => _RUSSIAN, 'ukrainian' => _UKRAINIAN);
	$out = strtr($con, $langs);
	return $out;
}

function getInfo($table, $id) {
	$text = '<tr><td>'._TABLE.':</td><td>'.$table.' '.(($id) ? '</td><td><span class="sl_green">'._OK.'</span></td>' : '<td><span class="sl_red">'._ERROR.'</span></td>').'</tr>';
	return $text;
}

function setHead() {
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

function setFoot() {
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

function setExit($msg, $typ = '') {
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

function isVar($v) {
	$v = (preg_match('#[^a-zA-Z0-9_\-]#', $v)) ? '' : $v;
	return $v;
}

function language() {
	global $title, $clang;
	$title = (PHP_VERSION < '5.6') ? _PHPSETUP : _LANG;
	setHead();
	$cont = '<table class="sl_table">';
	$handle = opendir('setup/language');
	while (false !== ($file = readdir($handle))) {
		if (preg_match('#^lang\-(.+)\.php#', $file, $matches)) $langlist[] = $matches[1];
	}
	closedir($handle);
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

function lang() {
	global $conf, $url;
	$time = time() + 3600;
	$lang = (preg_match('#[^a-zA-Z0-9_]#', $_GET['id'])) ? 'english' : $_GET['id'];
	$url = parse_url($url);
	$sec = ($url['scheme'] == 'http') ? false : true;
	$options = array('expires' => $time, 'path' => '/', 'domain' => $url['host'], 'secure' => $sec, 'httponly' => true, 'samesite' => 'Lax');
	setcookie($conf['user_c'].'-language', $lang, $options);
	header('Location: setup.php');
}

function config() {
	global $title, $conf, $confs;
	$title = _CONFIG;
	if (file_exists('config/db.php')) {
		chmod('config/db.php', 0666);
		$permsdir = decoct(fileperms('config/db.php'));
		$perms = substr($permsdir, -3);
		if ($perms != '666') {
			$title = _FILE.' config/db.php '._SERRORPERM.' CHMOD - 666';
			setHead();
			setFoot();
			exit;
		}
	}
	if (file_exists('config/config_global.php')) {
		chmod('config/config_global.php', 0666);
		$permsdir = decoct(fileperms('config/config_global.php'));
		$perms = substr($permsdir, -3);
		if ($perms != '666') {
			$title = _FILE.' config/config_global.php '._SERRORPERM.' CHMOD - 666';
			setHead();
			setFoot();
			exit;
		}
	}
	include('config/db.php');
	$xhost = ($confdb['host']) ? $confdb['host'] : 'localhost';
	$xuname = ($confdb['uname']) ? $confdb['uname'] : '';
	$xpass = ($confdb['pass']) ? $confdb['pass'] : '';
	$xname = ($confdb['name']) ? $confdb['name'] : '';
	$atype = (PHP_VERSION < '5.5.0') ? array('mysqli', 'mysql') : array('mysqli');
	$xtype = '';
	foreach ($atype as $val) {
		if ($val != '') {
			$sel = ($val == $confdb['type']) ? ' selected' : '';
			$xtype .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
		}
	}
	$aengine = ($confdb['engine']) ? array('InnoDB', 'MyISAM', 'Memory', $confdb['engine']) : array('InnoDB', 'MyISAM', 'Memory');
	$aengine = array_unique($aengine);
	$xengine = '';
	foreach ($aengine as $val) {
		if ($val != '') {
			$sel = ($val == $confdb['engine']) ? ' selected' : '';
			$xengine .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
		}
	}
	$acharset = ($confdb['charset']) ? array('utf8mb4', 'utf8', $confdb['charset']) : array('utf8mb4', 'utf8');
	$acharset = array_unique($acharset);
	$xcharset = '';
	foreach ($acharset as $val) {
		if ($val != '') {
			$sel = ($val == $confdb['charset']) ? ' selected' : '';
			$xcharset .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
		}
	}
	$acollate = ($confdb['collate']) ? array('utf8mb4_unicode_ci', 'utf8_general_ci', $confdb['collate']) : array('utf8mb4_unicode_ci', 'utf8_general_ci');
	$acollate = array_unique($acollate);
	$xcollate = '';
	foreach ($acollate as $val) {
		if ($val != '') {
			$sel = ($val == $confdb['collate']) ? ' selected' : '';
			$xcollate .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
		}
	}
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
	.'<tr><td>'._CONF_5.':<div class="sl_small">'._CDEFAULT.'</div></td><td><select name="xtype" class="sl_cinput" title="'._CONF_5.'">'.$xtype.'</select></td></tr>'
	.'<tr><td>'._CONF_6.':<div class="sl_small">'._CDEFAULT.'</div></td><td><select name="xengine" class="sl_cinput" title="'._CONF_6.'">'.$xengine.'</select></td></tr>'
	.'<tr><td>'._CONF_7.':<div class="sl_small">'._CDEFAULT.'</div></td><td><select name="xcharset" class="sl_cinput" title="'._CONF_7.'">'.$xcharset.'</select></td></tr>'
	.'<tr><td>'._CONF_8.':<div class="sl_small">'._CDEFAULT.'</div></td><td><select name="xcollate" class="sl_cinput" title="'._CONF_8.'">'.$xcollate.'</select></td></tr>'
	.'<tr><td>'._CONF_9.':</td><td><input type="text" name="xprefix" value="'.$xprefix.'" class="sl_cinput" placeholder="'._CONF_9.'" required></td></tr>'
	.'<tr><td colspan="2"><hr></td></tr>'
	.'<tr><td>'._CONF_10.':<div class="sl_small">'.$info.'</div></td><td><input type="text" name="xafile" value="'.$xafile.'" class="sl_cinput" placeholder="'._CONF_10.'" required></td></tr>'
	.'<tr><td colspan="2" class="sl_center">'._GOBACK.' <input type="hidden" name="op" value="save"><input type="submit" value="'._NEXT_SE.'" class="sl_but_blue"></td></tr>'
	.'</table></form>';
	setFoot();
}

function save() {
	global $title, $clang, $conf, $confs, $url;
	$setup = (isset($_POST['setup'])) ? $_POST['setup'] : '';
	$xhost = (isset($_POST['xhost'])) ? $_POST['xhost'] : '';
	$xuname = (isset($_POST['xuname'])) ? $_POST['xuname'] : '';
	$xpass = (isset($_POST['xpass'])) ? $_POST['xpass'] : '';
	$xname = (isset($_POST['xname'])) ? $_POST['xname'] : '';
	$xtype = (isset($_POST['xtype'])) ? $_POST['xtype'] : 'mysqli';
	$xengine = (isset($_POST['xengine'])) ? $_POST['xengine'] : 'InnoDB';
	$xcharset = (isset($_POST['xcharset'])) ? $_POST['xcharset'] : 'utf8mb4';
	$xcollate = (isset($_POST['xcollate'])) ? $_POST['xcollate'] : 'utf8mb4_unicode_ci';
	$xprefix = (isset($_POST['xprefix'])) ? $_POST['xprefix'] : 'slaed';
	$xsync = (isset($_POST['xsync'])) ? $_POST['xsync'] : '1';
	$xmode = (isset($_POST['xmode'])) ? $_POST['xmode'] : '1';
	$xafile = (isset($_POST['xafile'])) ? $_POST['xafile'] : 'admin';
	
	$cont = array('language' => $clang, 'homeurl' => $url);
	doConfig('config/config_global.php', 'conf', $cont, $conf, '');
	include('config/config_global.php');
	
	$tafile = ($confs['afile']) ? $confs['afile'] : 'admin';
	rename($tafile.'.php', $xafile.'.php');
	$xafile = (file_exists($xafile.'.php')) ? $xafile : $tafile;
	$cont = array('afile' => $xafile);
	doConfig('config/config_security.php', 'confs', $cont, $confs, '');
	include('config/config_security.php');
	
	include('config/db.php');
	$cont = array('host' => $xhost, 'uname' => $xuname, 'pass' => $xpass, 'name' => $xname, 'type' => $xtype, 'engine' => $xengine, 'charset' => $xcharset, 'collate' => $xcollate, 'prefix' => $xprefix, 'sync' => $xsync, 'mode' => $xmode);
	doConfig('config/db.php', 'confdb', $cont, $confdb, '');
	
	if ($xtype == 'pdo') {
		include('core/classes/pdo.php');
	} else {
		include('core/classes/mysqli.php');
	}
	$db = new sql_db($xhost, $xuname, $xpass, $xname, $xcharset);
	
	$bodytext = '';
	if ($setup == 'new') {
		$title = _SAVE_NEW;
		$filename = file_get_contents('setup/sql/table.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace(array('{prefix}', '{engine}', '{charset}', '{collate}'), array($xprefix, $xengine, $xcharset, $xcollate), $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
		$filename = file_get_contents('setup/sql/insert.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update4_1') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update4_1.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update4_2') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update4_2.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update4_3') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update4_3.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update5_0') {
		$title = _SAVE_UPDATE;
		$result = $db->sql_query("SELECT id, pwd FROM ".$xprefix."_admins");
		while (list($a_id, $a_pwd) = $db->sql_fetchrow($result)) {
			$db->sql_query("UPDATE ".$xprefix."_admins SET pwd = '".getCrypt($a_pwd)."' WHERE id = '".$a_id."'");
		}
		$bodytext .= getInfo($xprefix.'_admins', $result);
		$result = $db->sql_query("SELECT user_id, user_password FROM ".$xprefix."_users");
		while (list($user_id, $user_password) = $db->sql_fetchrow($result)) {
			$db->sql_query("UPDATE ".$xprefix."_users SET user_password = '".getCrypt($user_password)."' WHERE user_id = '".$user_id."'");
		}
		$bodytext .= getInfo($xprefix.'_users', $result);
		$filename = file_get_contents('setup/sql/table_update5_0.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update5_1') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update5_1.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
		
		$result = $db->sql_query("SELECT poll_id, poll_date, poll_title, poll_questions, poll_answer_1, poll_answer_2, poll_answer_3, poll_answer_4, poll_answer_5, poll_answer_6, poll_answer_7, poll_answer_8, poll_answer_9, poll_answer_10, poll_answer_11, poll_answer_12, pool_comments, planguage, acomm FROM ".$xprefix."_voting_temp");
		while (list($poll_id, $poll_date, $poll_title, $poll_questions, $poll_answer_1, $poll_answer_2, $poll_answer_3, $poll_answer_4, $poll_answer_5, $poll_answer_6, $poll_answer_7, $poll_answer_8, $poll_answer_9, $poll_answer_10, $poll_answer_11, $poll_answer_12, $pool_comments, $planguage, $acomm) = $db->sql_fetchrow($result)) {
			$questions = substr($poll_questions, 0, -1);
			$array_answ = array($poll_answer_1, $poll_answer_2, $poll_answer_3, $poll_answer_4, $poll_answer_5, $poll_answer_6, $poll_answer_7, $poll_answer_8, $poll_answer_9, $poll_answer_10, $poll_answer_11, $poll_answer_12);
			$answ = array();
			foreach ($array_answ as $val) if (!empty($val)) $answ[] = trim($val);
			$answ = implode('|', $answ);
			$db->sql_query("INSERT INTO ".$xprefix."_voting (id, modul, title, questions, answer, date, enddate, multi, comments, language, acomm, ip, typ, status) VALUES ('".$poll_id."', '', '".$poll_title."', '".$questions."', '".$answ."', '".$poll_date."', '2020-05-23 20:58:00', '0', '".$pool_comments."', '".$planguage."', '".$acomm."', '".getIp()."', '1', '1')");
			$db->sql_query("DROP TABLE ".$xprefix."_voting_temp");
		}
		$bodytext .= getInfo($xprefix.'_voting', $result);
		
		$result = $db->sql_query("SELECT sid, associated FROM ".$xprefix."_news");
		while (list($id, $associated) = $db->sql_fetchrow($result)) {
			$associated = explode('-', $associated);
			if (is_array($associated)) {
				$assoc = array();
				foreach ($associated as $val) {
					if (!empty($val)) $assoc[] = trim($val);
				}
				$assoc = implode(',', $assoc);
			} else {
				$assoc = '';
			}
			$db->sql_query("UPDATE ".$xprefix."_news SET associated = '".$assoc."' WHERE sid = '".$id."'");
		}
		$bodytext .= getInfo($xprefix.'_news', $result);
		
		$result = $db->sql_query("SELECT id, assoc FROM ".$xprefix."_products");
		while (list($id, $associated) = $db->sql_fetchrow($result)) {
			$associated = explode('-', $associated);
			if (is_array($associated)) {
				$assoc = array();
				foreach ($associated as $val) {
					if (!empty($val)) $assoc[] = trim($val);
				}
				$assoc = implode(',', $assoc);
			} else {
				$assoc = '';
			}
			$db->sql_query("UPDATE ".$xprefix."_products SET assoc = '".$assoc."' WHERE id = '".$id."'");
		}
		$bodytext .= getInfo($xprefix.'_products', $result);
	} elseif ($setup == 'update6_0') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update6_0.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	} elseif ($setup == 'update6_2') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update6_2.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace('{prefix}', $xprefix, $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
	}elseif ($setup == 'update6_3') {
		$title = _SAVE_UPDATE;
		$filename = file_get_contents('setup/sql/table_update6_3.sql');
		$stringdump = explode(';', $filename);
		for ($i = 0; $i < count($stringdump); $i++) {
			$string = str_replace(array('{prefix}', '{engine}', '{charset}', '{collate}'), array($xprefix, $xengine, $xcharset, $xcollate), $stringdump[$i]);
			$id = $db->sql_query($string);
			if (preg_match('#CREATE|ALTER|DELETE|DROP|RENAME|UPDATE#i', $string)) {
				$table = explode('`', $string);
				$bodytext .= getInfo($table[1], $id);
			}
		}
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