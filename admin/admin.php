<?php
# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');
include('core/core.php');
get_lang('admin');
setCache('0');
checkAccess();

function add_admin() {
	global $prefix, $db, $admin_file, $conf, $stop;
	if ($db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_admins")) == 0) {
		$aname = $_POST['aname'];
		$aurl = url_filter($_POST['aurl']);
		$aemail = $_POST['aemail'];
		$apwd = md5_salt($_POST['apwd']);
		$apwd2 = md5_salt($_POST['apwd2']);
		$auser_new = intval($_POST['auser_new']);
		$aeditor = intval($conf['redaktor']);
		$alang = getCookies('language');
		$aip = getip();
		if (!$aname || !analyze_name($aname)) $stop = _ERRORINVNICK;
		if (!$_POST['apwd'] && !$_POST['apwd2']) $stop = _NOPASS;
		if ($apwd != $apwd2) $stop = _ERROR_PASS;
		if (strlen($aname) > 25) $stop = _NICKLONG;
		if (!$stop) {
			$db->sql_query("INSERT INTO ".$prefix."_admins VALUES (NULL, '".$aname."', 'Admin', '".$aurl."', '".$aemail."', '".$apwd."', '1', '".$aeditor."', '1', '', '".$alang."', '".$aip."', now(), now())");
			if ($auser_new == 1) {
				$auser_avatar = "default/00.gif";
				$user_exist = $db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_users WHERE user_name = '".$aname."'"));
				if ($user_exist) $db->sql_query("DELETE FROM ".$prefix."_users WHERE user_name='".$aname."'");
				$db->sql_query("INSERT INTO ".$prefix."_users (user_id, user_name, user_email, user_website, user_avatar, user_regdate, user_password, user_lang, user_last_ip) VALUES (NULL, '".$aname."', '".$aemail."', '".$aurl."', '".$auser_avatar."', now(), '".$apwd."', '".$alang."', '".$aip."')");
			}
			header("Location: ".$admin_file.".php");
		} else {
			login();
		}
	} else {
		header("Location: ".$admin_file.".php");
	}
}

function check_admin() {
	global $prefix, $db, $admin_file, $conf, $stop;
	if (($conf['gfx_chk'] == 1 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop = _SECCODEINCOR;
	$name = htmlspecialchars(trim(substr($_POST['name'], 0, 25)));
	$pwd = htmlspecialchars(trim(substr($_POST['pwd'], 0, 25)));
	if (!$name || !$pwd) $stop = _LOGININCOR;
	$result = $db->sql_query("SELECT id, name, pwd, editor FROM ".$prefix."_admins WHERE name = '".$name."' AND pwd = '".md5_salt($pwd)."'");
	if ($db->sql_numrows($result) != 1) $stop = _LOGININCOR;
	list($aid, $aname, $apwd, $aeditor) = $db->sql_fetchrow($result);
	if (!$aid || $aname != $name || $apwd != md5_salt($pwd)) $stop = _LOGININCOR;
	if (!$stop) {
		unset($_SESSION[$conf['admin_c']]);
		$info = base64_encode($aid.":".$aname.":".$apwd.":".$aeditor);
		$_SESSION[$conf['admin_c']] = $info;
		$ip = getip();
		$db->sql_query("DELETE FROM ".$prefix."_session WHERE uname = '".$ip."'");
		$db->sql_query("UPDATE ".$prefix."_admins SET ip = '".$ip."', lastvisit = now() WHERE id = '".$aid."'");
		login_report(1, 1, $name, "");
		header("Location: ".$admin_file.".php");
	} else {
		login_report(1, 0, $name, $pwd);
		login();
	}
}

function login() {
	global $prefix, $db, $admin_file, $conf, $stop;
	head();
	if ($db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_admins")) == 0) {
		$cont = ($stop) ? setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'atten', 'text' => $stop)) : '';
		$cont .= tpl_eval('registration', $admin_file, _NICKNAME, $_POST['aname'], _HOMEPAGE, get_host(), _EMAIL, $_POST['aemail'], _PASSWORD, _RETYPEPASSWORD, _CREATEUSERDATA, _YES, _NO, _SEND);
	} else {
		$captcha = ($conf['gfx_chk'] == 1 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
		$cont = ($stop) ? setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'atten', 'text' => $stop)) : '';
		$cont .= tpl_eval('login', $admin_file, _NICKNAME, _PASSWORD, $captcha, _LOGIN);
	}
	echo $cont;
	foot();
}

function changeeditor() {
	global $prefix, $db, $admin, $admin_file, $conf;
	$editor = (isset($_POST['editor'])) ? intval($_POST['editor']) : intval($conf['redaktor']);
	$aid = intval(substr($admin[0], 0, 11));
	$info = base64_decode($_SESSION[$conf['admin_c']]);
	$sinfo = base64_encode(substr($info, 0, -1).$editor);
	unset($_SESSION[$conf['admin_c']]);
	$_SESSION[$conf['admin_c']] = $sinfo;
	$db->sql_query("UPDATE ".$prefix."_admins SET editor = '".$editor."' WHERE id = '".$aid."'");
	referer($admin_file.".php");
}

function logout() {
	global $prefix, $db, $admin, $admin_file, $conf;
	$aname = text_filter(substr($admin[1], 0, 25), 1);
	$db->sql_query("DELETE FROM ".$prefix."_session WHERE uname = '".$aname."' AND guest = '3'");
	unset($_SESSION[$conf['admin_c']], $admin);
	header("Location: ".$admin_file.".php");
}

function adminmenu($url, $title, $image) {
	global $count, $conf, $panel, $content_am, $class;
	$ltitle = ($class) ? $title." - "._DEACT : $title;
	$image = file_exists(img_find("admin/".$image)) ? img_find("admin/".$image) : img_find("admin/components.png");
	if ($panel) {
		if (($count - 1) % $conf['admcol'] == 0) echo "<tr>";
		echo "<td class=\"sl_td_mod".$class."\"><a href=\"".$url."\" title=\"".$ltitle."\"><img src=\"".$image."\" alt=\"".$ltitle."\" title=\"".$ltitle."\" class=\"sl_img_mod\"><br>".$title."</a></td>";
		if ($count % $conf['admcol'] == 0) echo "</tr>";
		$count++;
	} else {
		$content_am .= "<table class=\"sl_tab_blm".$class."\"><tr><td><a href=\"".$url."\" title=\"".$ltitle."\"><img src=\"".$image."\" alt=\"".$ltitle."\" title=\"".$ltitle."\" class=\"sl_img_blm\"></a></td><td><a href=\"".$url."\" title=\"".$ltitle."\">".$title."</a></td></tr></table>";
	}
}

function panelblock() {
	global $prefix, $db, $conf, $panel, $admin_file, $content_am, $locale, $class;
	if (!$panel) {
		if (is_admin_god()) {
			// Auto-discover admin modules
			$modules = [];
			$dir = opendir('admin/modules');
			while (false !== ($file = readdir($dir))) {
				if (preg_match('/^([a-z]+)\.php$/i', $file, $matches)) {
					$modules[] = $matches[1];
				}
			}
			closedir($dir);
			sort($modules);

			// Generate menu entries for admin modules
			$module_meta = getAdminModuleMeta();
			foreach ($modules as $module) {
				$meta = $module_meta[$module] ?? ['title' => ucfirst($module), 'icon' => 'components.png', 'op' => 'show'];
				adminmenu(
					$admin_file.'.php?name='.$module.'&op='.$meta['op'],
					$meta['title'],
					$meta['icon']
				);
			}
			$ablock = setTemplateBlock('block-left', array('{%title%}' => _ADMIN, '{%content%}' => $content_am, '{%id%}' => '1'));
			$content_am = '';
		}

		// Custom modules
		$result = $db->sql_query("SELECT title, active FROM ".$prefix."_modules ORDER BY title ASC");
		while (list($title, $active) = $db->sql_fetchrow($result)) {
			if (is_admin_god() || is_admin_modul($title)) {
				if (file_exists('modules/'.$title.'/admin/index.php') && file_exists('modules/'.$title.'/admin/links.php')) {
					$class = (!$active) ? ' sl_hidden' : '';
					include('modules/'.$title.'/admin/links.php');
					if (file_exists('modules/'.$title.'/admin/language/lang-'.$locale.'.php')) include('modules/'.$title.'/admin/language/lang-'.$locale.'.php');
				}
			}
		}
		$class = '';
		$ablock .= setTemplateBlock('block-left', array('{%title%}' => _MODULES, '{%content%}' => $content_am, '{%id%}' => '2'));
		return $ablock;
	}
}

function getAdminModuleMeta(): array {
	return [
		'admins' => ['title' => _EDITADMINS, 'icon' => 'admins.png', 'op' => 'show'],
		'blocks' => ['title' => _BLOCKS, 'icon' => 'blocks.png', 'op' => 'show'],
		'categories' => ['title' => _CATEGORIES, 'icon' => 'categories.png', 'op' => 'show'],
		'changelog' => ['title' => 'Changelog', 'icon' => 'editor.png', 'op' => 'show'],
		'comments' => ['title' => _COMMENTS, 'icon' => 'comments.png', 'op' => 'show'],
		'config' => ['title' => _PREFERENCES, 'icon' => 'preferences.png', 'op' => 'show'],
		'database' => ['title' => _DATABASE, 'icon' => 'database.png', 'op' => 'show'],
		'editor' => ['title' => _EDITOR_IN, 'icon' => 'editor.png', 'op' => 'function'],
		'favorites' => ['title' => _FAVORITES, 'icon' => 'favorites.png', 'op' => 'show'],
		'fields' => ['title' => _FIELDS, 'icon' => 'fields.png', 'op' => 'show'],
		'groups' => ['title' => _UGROUPS, 'icon' => 'groups.png', 'op' => 'show'],
		'lang' => ['title' => _LANG_EDIT, 'icon' => 'lang.png', 'op' => 'main'],
		'messages' => ['title' => _MESSAGES, 'icon' => 'messages.png', 'op' => 'show'],
		'modules' => ['title' => _MODULES, 'icon' => 'modules.png', 'op' => 'show'],
		'newsletter' => ['title' => _NEWSLETTER, 'icon' => 'newsletter.png', 'op' => 'show'],
		'privat' => ['title' => _PRIVAT, 'icon' => 'privat.png', 'op' => 'show'],
		'ratings' => ['title' => _RATINGS, 'icon' => 'ratings.png', 'op' => 'show'],
		'referers' => ['title' => _REFERERS, 'icon' => 'referers.png', 'op' => 'show'],
		'replace' => ['title' => _REPLACE, 'icon' => 'replace.png', 'op' => 'show'],
		'rss' => ['title' => _RSS, 'icon' => 'rss.png', 'op' => 'conf'],
		'security' => ['title' => _SECURITY, 'icon' => 'security.png', 'op' => 'show'],
		'sitemap' => ['title' => _SITEMAP, 'icon' => 'sitemap.png', 'op' => 'show'],
		'stat' => ['title' => _STAT, 'icon' => 'stat.png', 'op' => 'show'],
		'template' => ['title' => _THEME, 'icon' => 'template.png', 'op' => 'show'],
		'uploads' => ['title' => _UPLOADSEDIT, 'icon' => 'uploads.png', 'op' => 'show'],
		'users' => ['title' => _USERS, 'icon' => 'users.png', 'op' => 'show'],
	];
}

function panel() {
	global $prefix, $db, $conf, $panel, $count, $admin_file, $locale, $class;
	if (file_exists('setup.php')) echo setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => _DELSETUP));
	$minver = '5.6';
	$info = sprintf(_PHPSETUP, $minver);
	if (PHP_VERSION < $minver) echo setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $info));
	if ($conf['admininfo']) echo setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => $conf['admininfo']));
	if ($panel) {
		$count = 1;
		if (is_admin_god()) {
			// Auto-discover admin modules
			$modules = [];
			$dir = opendir('admin/modules');
			while (false !== ($file = readdir($dir))) {
				if (preg_match('/^([a-z]+)\.php$/i', $file, $matches)) {
					$modules[] = $matches[1];
				}
			}
			closedir($dir);
			sort($modules);

			// Generate menu entries
			$module_meta = getAdminModuleMeta();
			ob_start();
			foreach ($modules as $module) {
				$meta = $module_meta[$module] ?? ['title' => ucfirst($module), 'icon' => 'components.png', 'op' => 'show'];
				adminmenu(
					$admin_file.'.php?name='.$module.'&op='.$meta['op'],
					$meta['title'],
					$meta['icon']
				);
			}
			$cont = ob_get_clean();
			echo tpl_eval("panel-admin", _ADMINMENU, $cont);
		}
		$count = 1;
		$result = $db->sql_query("SELECT title, active FROM ".$prefix."_modules ORDER BY title ASC");
		ob_start();
		while (list($title, $active) = $db->sql_fetchrow($result)) {
			if (is_admin_god() || is_admin_modul($title)) {
				if (file_exists("modules/".$title."/admin/index.php") && file_exists("modules/".$title."/admin/links.php")) {
					$class = (!$active) ? " sl_hidden" : "";
					include("modules/".$title."/admin/links.php");
					if (file_exists("modules/".$title."/admin/language/lang-".$locale.".php")) include("modules/".$title."/admin/language/lang-".$locale.".php");
				}
			}
		}
		$class = "";
		$cont = ob_get_clean();
		echo tpl_eval("panel-modul", _MODULESADMIN, $cont);
	}
}

if (is_admin()) {
	$name = getVar('req', 'name', 'var');
	$op = getVar('req', 'op', 'var', 'show');
	$panel = (empty($name)) ? 1 : 0;
	$id = getVar('req', 'id', 'num');
	$act = getVar('req', 'act', 'num');
	$pagetitle = $conf['defis'].' '._ADMINMENU;

	// Special operations
	if ($op == 'changeeditor') {
		changeeditor();
	} elseif ($op == 'logout') {
		logout();
	} elseif ($panel) {
		// Show admin panel - no specific module requested
		panel();
	} else {
		// Load specific admin module
		if (is_admin_god()) {
			$module_file = 'admin/modules/' . $name . '.php';
			if (file_exists($module_file)) {
				include($module_file);
			}
		}

		// Load custom module admin if exists
		$result = $db->sql_query('SELECT title FROM '.$prefix.'_modules WHERE title = :title', ['title' => $name]);
		if ($db->sql_numrows($result) > 0) {
			list($mtitle) = $db->sql_fetchrow($result);
			if (is_admin_god() || is_admin_modul($mtitle)) {
				if (file_exists('modules/'.$mtitle.'/admin/index.php')) {
					if (file_exists('modules/'.$mtitle.'/admin/language/lang-'.$locale.'.php')) {
						include('modules/'.$mtitle.'/admin/language/lang-'.$locale.'.php');
					}
					include('modules/'.$mtitle.'/admin/index.php');
				}
			}
		}
	}
} else {
	$home = 1;
	$op = getVar('post', 'op', 'var');
	switch($op) {
		default:
		login();
		break;
		
		case 'add_admin':
		add_admin();
		break;
		
		case 'check_admin';
		check_admin();
		break;
	}
}