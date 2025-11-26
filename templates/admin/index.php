<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

function setTemplateHead($sub, $val = '') {
	global $theme, $user, $conf, $confu, $prefix, $db, $blocks, $admin, $admin_file;
	$langs = $menu = $blocks = $login = '';
	if (is_admin()) {
		if ($conf['multilingual'] == 1) {
			$dir = opendir('language');
			while (false !== ($file = readdir($dir))) {
				if (preg_match('#^lang\-(.+)\.php#', $file, $matches)) {
					$lfound = $matches[1];
					$title = deflang($lfound);
					$langs .= '<a href="'.$admin_file.'.php?newlang='.$lfound.'"><img src="'.img_find('language/'.$lfound.'_mini.png').'" alt="'.$title.'" title="'.$title.'"></a>';
				}
			}
			closedir($dir);
		}
		if (!is_admin_god()) {
			$uname = _HELLO.', '.substr($admin[1], 0, 25).'!';
			$menu = '<li class="sl_first"><a href="#" title="'.$uname.'"><b>'.$uname.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php" title="'._ADMINMENU.'"><b>'._HOME.'</b></a></li>'
			.'<li><a href="index.php" target="_blank" title="'._SITE.'"><b>'._SITE.'</b></a></li>'
			.'<li><a href="index.php?name=account" target="_blank" title="'._ACCOUNT.'"><b>'._ACCOUNT.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php?op=logout" title="'._LOGOUT.'"><b>'._LOGOUT.'</b></a></li>';
		} else {
			$menu = '<li class="sl_first"><a href="'.$admin_file.'.php" title="'._ADMINMENU.'"><b>'._HOME.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php?op=blocks_show" title="'._BLOCKS.'"><b>'._BLOCKS.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php?op=module" title="'._MODULES.'"><b>'._MODULES.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php?op=cat_show" title="'._CATEGORIES.'"><b>'._CATEGORIES.'</b></a></li>'
			.'<li><a href="index.php" target="_blank" title="'._SITE.'"><b>'._SITE.'</b></a></li>'
			.'<li><a href="index.php?name=account" target="_blank" title="'._ACCOUNT.'"><b>'._ACCOUNT.'</b></a></li>'
			.'<li><a href="'.$admin_file.'.php?op=logout" title="'._LOGOUT.'"><b>'._LOGOUT.'</b></a></li>';
		}
		$blocks = panelblock().admininfo().adminblock();
	} else {
		$login = ($db->sql_numrows($db->sql_query("SELECT * FROM ".$prefix."_admins")) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
	}
	$value = array('{%langs%}' => $langs, '{%menu%}' => $menu, '{%blocks%}' => $blocks, '{%login%}' => $login, '{%theme%}' => $theme, '{%lang%}' => substr(_LOCALE, 0, 2));
	$value = is_array($val) ? array_merge($value, $val) : $value;
	return str_replace(array_keys($value), array_values($value), $sub);
}

function setTemplateBlock($tpl, $val = '') {
	global $theme, $conf;
	static $argc, $cach;
	if ($argc != $tpl || !isset($cach)) {
		$argc = $tpl;
		$cont = getThemeFile($argc);
		if ($cont) $cach = file_get_contents($cont);
	}
	$value = array('{%close%}' => _OPCL);
	$value = is_array($val) ? array_merge($value, $val) : $value;
	return str_replace(array_keys($value), array_values($value), $cach);
}

function setTemplateFoot($sub, $val = '') {
	global $theme, $user, $conf, $confu;
	$value = array('{%upper%}' => _PAGETOP);
	$value = is_array($val) ? array_merge($value, $val) : $value;
	return str_replace(array_keys($value), array_values($value), $sub);
}
?>