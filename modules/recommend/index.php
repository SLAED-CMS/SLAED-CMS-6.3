<?php
# Author: Eduard Laas
# Copyright © 2005 - 2021 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}
get_lang($conf['name']);

function recommend() {
	global $conf, $stop;
	$unkey = md5_salt($conf['sitekey']);
	if (is_user()) {
		$userinfo = getusrinfo();
		$sname = getVar('post', $unkey, 'name', $userinfo['user_name']);
		$semail = getVar('post', 'semail', 'text', $userinfo['user_email']);
	} else {
		$sname = getVar('post', $unkey, 'name');
		$semail = getVar('post', 'semail', 'text');
	}
	$fname = getVar('post', 'fname', 'name');
	$femail = getVar('post', 'femail', 'text');
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => _RECOMMTITLE));
	if ($stop) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
	$cont .= setTemplateBasic('open');
	$cont .= '<form action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">'
	.'<tr><td>'._YOURNAME.':</td><td><input type="text" name="'.$unkey.'" value="'.$sname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>'
	.'<tr><td>'._YOUREMAIL.':</td><td><input type="email" name="semail" value="'.$semail.'" class="sl_field '.$conf['style'].'" placeholder="'._YOUREMAIL.'" required></td></tr>'
	.'<tr><td>'._FFRIENDNAME.':</td><td><input type="text" name="fname" value="'.$fname.'" class="sl_field '.$conf['style'].'" placeholder="'._FFRIENDNAME.'" required></td></tr>'
	.'<tr><td>'._FFRIENDEMAIL.':</td><td><input type="email" name="femail" value="'.$femail.'" class="sl_field '.$conf['style'].'" placeholder="'._FFRIENDEMAIL.'" required></td></tr>'
	.'<tr><td colspan="2" class="sl_center">'.getCaptcha(2).'<input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function send() {
	global $conf, $stop;
	$unkey = md5_salt($conf['sitekey']);
	$sname = getVar('post', $unkey, 'name');
	$semail = getVar('post', 'semail', 'text');
	$fname = getVar('post', 'fname', 'name');
	$femail = getVar('post', 'femail', 'text');
	$stop = array();
	if (!$sname || !$fname) $stop[] = _ERROR_ALL;
	checkemail($semail);
	checkemail($femail);
	if (checkCaptcha(2)) $stop[] = _SECCODEINCOR;
	if (!$stop) {
		$subject = $conf['sitename'].' - '._INTSITE;
		$message = _HELLO.' '.$fname.'!<br><br>'._YOURFRIEND.' '.$sname.' '._OURSITE.' '.$conf['sitename'].' '._INTSENT.'<br><br>'._SITENAME.': '.$conf['sitename'].' '.urldecode($conf['defis']).' '.$conf['slogan'].'<br>'._SITEURL.': <a href="'.$conf['homeurl'].'" target="_blank" title="'.$conf['sitename'].'">'.$conf['homeurl'].'</a>';
		mail_send($femail, $semail, $subject, $message, 0, 3);
		update_points(38);
		head();
		echo setTemplateBasic('title', array('{%title%}' => _RECOMMTITLE)).setTemplateWarning('warn', array('time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _FREFERENCE.' '.$fname.'.<br>'._THANKSREC));
		foot();
	} else {
		recommend();
	}
}

switch($op) {
	default: recommend(); break;
	case 'send': send(); break;
}
?>