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
include('config/config_contact.php');

function contact() {
	global $prefix, $db, $conf, $confco, $locale, $stop;
	if (is_user()) {
		$userinfo = getusrinfo();
		$sname = getVar('post', 'sname', 'name', $userinfo['user_name']);
		$semail = getVar('post', 'semail', 'text', $userinfo['user_email']);
	} else {
		$sname = getVar('post', 'sname', 'name');
		$semail = getVar('post', 'semail', 'text');
	}
	$message = getVar('post', 'message', 'text');
	if ($confco['admins']) {
		$wlang = ($conf['multilingual']) ? "AND (lang = '".$locale."' OR lang = '')" : '';
		$result = $db->sql_query("SELECT id, name, title FROM ".$prefix."_admins WHERE smail = '1' ".$wlang." ORDER BY id");
		$asend = '';
		if ($db->sql_numrows($result) > 0) {
			while (list($id, $aname, $atitle) = $db->sql_fetchrow($result)) {
				$aname = substr($aname, 0, 25);
				$atitle = substr($atitle, 0, 50);
				$asend .= '<option value="'.$id.'">'.$aname.' - '.$atitle.'</option>';
			}
		}
	}
	if ($confco['info']) {
		$title = _CONTACT;
		$form = bb_decode($confco['info'], $conf['name']).'<hr>';
	} else {
		$title = _FEEDBACK;
		$form = '';
	}
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => $title));
	$form .= '<form action="index.php?name='.$conf['name'].'" method="post">'
	.'<table class="sl_table_form">';
	$form .= ($asend) ? '<tr><td>'._TO.':</td><td><select name="id" class="sl_field '.$conf['style'].'">'.$asend.'</select></td></tr>' : '';
	$form .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="sname" value="'.$sname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>'
	.'<tr><td>'._YOUREMAIL.':</td><td><input type="email" name="semail" value="'.$semail.'" class="sl_field '.$conf['style'].'" placeholder="'._YOUREMAIL.'" required></td></tr>'
	.'<tr><td>'._MESSAGE.':</td><td><textarea name="message" cols="65" rows="10" class="sl_field '.$conf['style'].'" placeholder="'._MESSAGE.'" required>'.$message.'</textarea></td></tr>'
	.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).'<input type="hidden" name="op" value="contact"><input type="hidden" name="send" value="1"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
	if (getVar('post', 'send', 'num') == '1') {
		$id = getVar('post', 'id', 'num');
		$sname = getVar('post', 'sname', 'name');
		$semail = getVar('post', 'semail', 'text');
		$message = nl2br(getVar('post', 'message', 'text'), false);
		$stop = array();
		if (!$sname) $stop[] = _CERROR3;
		if (!$message) $stop[] = _CERROR1;
		checkemail($semail);
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if (!$stop) {
			if ($confco['admins'] && $id) {
				list($adminmail) = $db->sql_fetchrow($db->sql_query("SELECT email FROM ".$prefix."_admins WHERE id = '".$id."' AND smail = '1'"));
				$to = $adminmail;
			} else {
				$to = $conf['adminmail'];
			}
			$subject = $conf['sitename'].' - '._FEEDBACK;
			$msg = $conf['sitename'].' - '._FEEDBACK.'<br><br>'._SENDERNAME.': '.$sname.'<br>'._SENDEREMAIL.': '.$semail.'<br><br>'._MESSAGE.': '.$message;
			mail_send($to, $semail, $subject, $msg, 1, 1);
			update_points(5);
			$cont .= setTemplateWarning('warn', array('time' => '5', 'url' => '', 'id' => 'info', 'text' => _FBMAILSENT));
		} else {
			$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
			$cont .= setTemplateBasic('open').$form.setTemplateBasic('close');
		}
	} else {
		$cont .= setTemplateBasic('open').$form.setTemplateBasic('close');
	}
	echo $cont;
	foot();
}

switch($op) {
	default: contact(); break;
}
?>