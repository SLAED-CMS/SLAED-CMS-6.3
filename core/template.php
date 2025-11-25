<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Set template of head
if (!function_exists('setTemplateHead')) {
	function setTemplateHead($sub, $val = '') {
		global $theme, $user, $conf, $confu;
		if (is_user()) {
			$uname = htmlspecialchars(substr($user[1], 0, 25));
			$userinfo = getusrinfo();
			$avatar = (file_exists($confu['adirectory'].'/'.$userinfo['user_avatar'])) ? $userinfo['user_avatar'] : 'default/00.gif';
			$cont = tpl_eval('login-logged', _ACCOUNT, $confu['adirectory'].'/'.$avatar, $uname, _LOGOUT);
		} else {
			if ($confu['enter'] == 1) {
				$captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
				$cont = tpl_eval('login', _LOGIN, _NICKNAME, _PASSWORD, $captcha, _LOGIN, _PASSFOR, _REG);
			} else {
				$cont = tpl_eval('login-without', _BREG);
			}
		}
		# Associative array of values passed to the template
		$value = array('{%login%}' => $cont, '{%theme%}' => $theme, '{%lang%}' => substr(_LOCALE, 0, 2), '{%sitename%}' => $conf['sitename'], '{%logo%}' => $conf['site_logo'], '{%homeurl%}' => $conf['homeurl'], '{%slogan%}' => $conf['slogan'], '{%home%}' => _HOME, '{%account%}' => _ACCOUNT, '{%album%}' => _ALBUM, '{%alinks%}' => _A_LINKS, '{%feedback%}' => _FEEDBACK, '{%content%}' => _CONTENT, '{%faq%}' => _FAQ, '{%files%}' => _FILES, '{%forum%}' => _FORUM, '{%help%}' => _HELP, '{%radio%}' => _RADIO, '{%jokes%}' => _JOKES, '{%links%}' => _LINKS, '{%media%}' => _MEDIA, '{%users%}' => _USERS, '{%news%}' => _NEWS, '{%order%}' => _ORDER, '{%pages%}' => _PAGES, '{%recommend%}' => _RECOMMEND, '{%rss%}' => _RSS, '{%search%}' => _SEARCH, '{%shop%}' => _SHOP, '{%topusers%}' => _TOPUSERS, '{%voting%}' => _VOTING, '{%favorites%}' => _S_FAVORITEN, '{%homepage%}' => _S_STARTSEITE);
		$value = is_array($val) ? array_merge($value, $val) : $value;
		return str_replace(array_keys($value), array_values($value), $sub);
	}
}

# Set template of basic
if (!function_exists('setTemplateBasic')) {
	function setTemplateBasic($tpl, $val = '') {
		global $theme, $conf;
		static $argc, $cach, $cont;
		if ($argc != $tpl || !isset($cach)) {
			$argc = $tpl;
			$cont = getThemeFile($argc);
			if ($cont) $cach = file_get_contents($cont);
		}
		if ($cont) {
			# Associative array of values passed to the template
			$value = array('{%theme%}' => $theme);
			$value = is_array($val) ? array_merge($value, $val) : $value;
			return str_replace(array_keys($value), array_values($value), $cach);
		} else {
			return setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => sprintf(_ERRORTPL, $tpl)));
		}
	}
}

# Set template of block
if (!function_exists('setTemplateBlock')) {
	function setTemplateBlock($tpl, $val = '') {
		global $theme, $conf, $pos, $blockfile, $b_id, $home;
		static $argc, $cach;
		if ($pos == 's' || $pos == 'o') {
			$bname = empty($blockfile) ? 'fly-block-'.$b_id : 'fly-'.str_replace('.php', '', $blockfile);
		} else {
			$bname = empty($blockfile) ? 'block-'.$b_id : str_replace('.php', '', $blockfile);
		}
		$file = 'templates/'.$theme.'/'.$bname.'.html';
		$file = file_exists($file) ? $file : false;
		if ($file) {
			if ($argc != $file || !isset($cach)) {
				$argc = $file;
				$cach = file_get_contents($argc);
			}
		} else {
			switch($pos) {
				case 'l':
				$bname ='block-left';
				break;
				case 'r':
				$bname ='block-right';
				break;
				case 'c':
				$bname ='block-center';
				break;
				case 'd':
				$bname ='block-down';
				break;
				case 's':
				$bname ='block-fly';
				break;
				case 'o':
				$bname ='block-fly';
				break;
				default:
				$bname ='block-all';
				break;
			}
			$file = getThemeFile($bname);
			if ($file) {
				if ($argc != $file || !isset($cach)) {
					$argc = $file;
					$cach = file_get_contents($argc);
				}
			} else {
				$file = getThemeFile('block-all');
				if ($file) {
					if ($argc != $file || !isset($cach)) {
						$argc = $file;
						$cach = file_get_contents($argc);
					}
				} else {
					$cach = '<fieldset><legend>{%title%}</legend>{%content%}</fieldset>';
				}
			}
		}
		# Associative array of values passed to the template
		$value = array('{%theme%}' => $theme);
		$value = is_array($val) ? array_merge($value, $val) : $value;
		return str_replace(array_keys($value), array_values($value), $cach);
	}
}

# Set template of foot
if (!function_exists('setTemplateFoot')) {
	function setTemplateFoot($sub, $val = '') {
		global $theme, $user, $conf, $confu;
		$cont = '';
		# Associative array of values passed to the template
		$value = array('{%login%}' => $cont, '{%theme%}' => $theme, '{%sitename%}' => $conf['sitename'], '{%logo%}' => $conf['site_logo'], '{%homeurl%}' => $conf['homeurl'], '{%slogan%}' => $conf['slogan'], '{%home%}' => _HOME, '{%account%}' => _ACCOUNT, '{%album%}' => _ALBUM, '{%alinks%}' => _A_LINKS, '{%feedback%}' => _FEEDBACK, '{%content%}' => _CONTENT, '{%faq%}' => _FAQ, '{%files%}' => _FILES, '{%forum%}' => _FORUM, '{%help%}' => _HELP, '{%radio%}' => _RADIO, '{%jokes%}' => _JOKES, '{%links%}' => _LINKS, '{%media%}' => _MEDIA, '{%users%}' => _USERS, '{%news%}' => _NEWS, '{%order%}' => _ORDER, '{%pages%}' => _PAGES, '{%recommend%}' => _RECOMMEND, '{%rss%}' => _RSS, '{%search%}' => _SEARCH, '{%shop%}' => _SHOP, '{%topusers%}' => _TOPUSERS, '{%voting%}' => _VOTING, '{%favorites%}' => _S_FAVORITEN, '{%homepage%}' => _S_STARTSEITE);
		$value = is_array($val) ? array_merge($value, $val) : $value;
		return str_replace(array_keys($value), array_values($value), $sub);
	}
}

# Set template of warning
if (!function_exists('setTemplateWarning')) {
	function setTemplateWarning($tpl, $set = '', $val = '') {
		global $theme, $conf;
		static $argc, $cach, $cont;
		if ($argc != $tpl || !isset($cach)) {
			$argc = $tpl;
			$cont = getThemeFile($argc);
			if ($cont) $cach = file_get_contents($cont);
		}
		if ($cont) {
			# Associative array of values passed to the template
			$value = array('{%theme%}' => $theme);
			$text = is_array($set['text']) ? implode('<br>', $set['text']) : $set['text'];
			$value = $value + array('{%text%}' => $text);
			if ($set['url'] || intval($set['time'])) {
				$meta = '<meta http-equiv="refresh" content="'.$set['time'].'; url=index.php'.$set['url'].'">';
				$value = $value + array('{%meta%}' => $meta);
			} else {
				$value = $value + array('{%meta%}' => '');
			}
			$value = $value + array('{%id%}' => $set['id']);
			$value = is_array($val) ? array_merge($value, $val) : $value;
			return str_replace(array_keys($value), array_values($value), $cach);
		} else {
			return sprintf('<fieldset><legend>'._ERROR.'</legend>'._ERRORTPL.'</fieldset>', $tpl);
		}
	}
}

# DELETE
if (!function_exists("tpl_eval")) {
	function tpl_eval() {
		global $theme, $conf;
		$arg = func_get_args();
		$lan = array(_SEARCH);
		$cont = getThemeFile($arg[0]);
		if ($cont) eval("\$rfl = \"".addslashes(file_get_contents($cont))."\";");
		return ($cont) ? stripslashes($rfl) : tpl_warn("warn", sprintf(_ERRORTPL, $arg[0]), "", "", "warn");
	}
}

if (!function_exists('tpl_func')) {
	function tpl_func() {
		global $theme, $conf;
		static $argc, $cach, $cont;
		$arg = func_get_args();
		$lan = array();
		if ($argc != $arg[0] || !isset($cach)) {
			$argc = $arg[0];
			$cont = getThemeFile($argc);
			if ($cont) $cach = file_get_contents($cont);
		}
		if ($cont) eval("\$rfl = \"".addslashes($cach)."\";");
		return ($cont) ? stripslashes($rfl) : tpl_warn('warn', sprintf(_ERRORTPL, $arg[0]), '', '', 'warn');
	}
}

if (!function_exists("tpl_warn")) {
	function tpl_warn() {
		global $theme, $conf;
		$arg = func_get_args();
		$lan = array();
		$arg[1] = (is_array($arg[1])) ? implode("<br>", $arg[1]) : $arg[1];
		if ($arg[2] || intval($arg[3])) $arg[2] = "<meta http-equiv=\"refresh\" content=\"".$arg[3]."; url=index.php".$arg[2]."\">";
		$arg[3] = $arg[4] ;
		$cont = getThemeFile($arg[0]);
		if ($cont) eval("\$rfl = \"".addslashes(file_get_contents($cont))."\";");
		return ($cont) ? stripslashes($rfl) : sprintf(_ERRORTPL, $arg[0]);
	}
}