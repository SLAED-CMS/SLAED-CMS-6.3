<?php
# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');

global $path;

include($path.'config/config_global.php');
include($path.'config/config_security.php');
include($path.'language/lang-'.$conf['language'].'.php');

# Denial of Authenticate
function setUnauthorized() {
	header('WWW-Authenticate: Basic realm="SLAED"');
	header('HTTP/1.0 401 Unauthorized');
	setExit(_LOGININCOR);
}

# Get IP
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

# Format exit info
function setExit($msg, $typ = '') {
	global $conf, $path;
	$cont = '<!doctype html>'.PHP_EOL
	.'<html lang="'.substr(_LOCALE, 0, 2).'">'.PHP_EOL
	.'<head>'.PHP_EOL
	.'<meta charset="'._CHARSET.'">'.PHP_EOL
	.'<title>'.$conf['sitename'].' '.urldecode($conf['defis']).' '.$conf['slogan'].'</title>'.PHP_EOL
	.'<meta name="author" content="'.$conf['sitename'].'">'.PHP_EOL
	.'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'.PHP_EOL;
	$cont .= ($typ) ? '<meta http-equiv="refresh" content="5; url='.$conf['homeurl'].'/index.php">'.PHP_EOL : '';
	$cont .= '</head>'.PHP_EOL
	.'<body>'.PHP_EOL
	.'<div style="margin: 25%;">'.PHP_EOL
	.'<div style="text-align: center;"><img src="'.$path.'templates/'.$conf['theme'].'/images/logos/'.$conf['site_logo'].'" alt="'.$conf['sitename'].'" title="'.$conf['sitename'].'"></div>'.PHP_EOL
	.'<div style="margin-top: 50px; font: 18px Arial, Tahoma, sans-serif, Verdana; color: #1a4674; font-weight: bold; text-align: center;">'.$msg.'</div>'.PHP_EOL
	.'</div>'.PHP_EOL
	.'</body>'.PHP_EOL
	.'</html>';
	die($cont);
}

if ($confs['admin_ip'] != '') {
	$admin_ip = explode(',', $confs['admin_ip']);
	foreach ($admin_ip as $val) {
		$temp_ip = getIp();
		$admin_ip = $val;
		if ($confs['admin_mask'] <= 3) {
			$temp_ip = substr($temp_ip, 0, strrpos($temp_ip, '.'));
			$admin_ip = substr($admin_ip, 0, strrpos($admin_ip, '.'));
		}
		if ($confs['admin_mask'] <= 2) {
			$temp_ip = substr($temp_ip, 0, strrpos($temp_ip, '.'));
			$admin_ip = substr($admin_ip, 0, strrpos($admin_ip, '.'));
		}
		if ($confs['admin_mask'] == 1) {
			$temp_ip = substr($temp_ip, 0, strrpos($temp_ip, '.'));
			$admin_ip = substr($admin_ip, 0, strrpos($admin_ip, '.'));
		}
		if ($admin_ip == $temp_ip) {
			$ip_check = true;
			break;
		} else {
			$ip_check = false;
		}
	}
	if (!$ip_check) setExit(_AUTH_ERROR_IP);
}

if ($confs['login'] != '' && $confs['password'] != '') {
	if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) setUnauthorized();
	if (!password_verify($_SERVER['PHP_AUTH_USER'], $confs['login']) || !password_verify($_SERVER['PHP_AUTH_PW'], $confs['password'])) setUnauthorized();
} else {
	setExit(_AUTH_ERROR);
}

unset($conf);
unset($confs);
unset($path);