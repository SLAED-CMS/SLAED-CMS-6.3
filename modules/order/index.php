<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function order(): void {
	global $conf, $stop;
	if (is_user()) {
		$userinfo = getusrinfo();
		$mail = getVar('post', 'mail', 'text', $userinfo['user_email']);
	} else {
		$mail = getVar('post', 'mail', 'text');
	}
	$field = getVar('post', 'field', 'field');
	setHead(['title' => _ORDER]);
	$cont = setTemplateBasic('title', ['{%title%}' => _ORDER]);
	$cont .= setTemplateBasic('open');
	$cont .= bb_decode($conf['order']['text'], 'all');
	$cont .= setTemplateBasic('close');
	if ($conf['order']['an']) {
		$com = getVar('post', 'com', 'text');
		if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
		$cont .= setTemplateBasic('open');
		$cont .= '<h2>'._OR_1.'</h2><form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">'
		.'<tr><td>'._OR_2.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._OR_2.'" required></td></tr>'
		.fields_in($field, $conf['name'])
		.'<tr><td>'._OR_3.':</td><td><textarea name="com" cols="65" rows="5" class="sl_field '.$conf['style'].'">'.$com.'</textarea></td></tr>'
		.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).'<input type="hidden" name="op" value="send"><input type="submit" value="'._OR_4.'" class="sl_but_blue"></td></tr></table></form>';
		$cont .= setTemplateBasic('close');
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MO_11]);
	}
	echo $cont;
	setFoot();
}

function send(): void {
	global $db, $conf, $stop;
	if ($conf['order']['an']) {
		$mail = getVar('post', 'mail', 'text');
		$field = getVar('post', 'field', 'field');
		$com = getVar('post', 'com', 'text');
		$stop = [];
		checkemail($mail);
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if (!$stop) {
			$status = ($conf['order']['pr']) ? '0' : '1';
			$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :mail, :info, :com, :ip, :agent, NOW(), :status)', ['mail' => $mail, 'info' => $field, 'com' => $com, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]);
			if ($conf['order']['ad']) {
				$infos = fields_out($field, $conf['name']);
				$amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._ORDER;
				$msg = $conf['sitename'].' - '._ORDER.'<br><br><b>'._PERSONALINFO.'</b><br><br>'._OR_2.': '.$mail.'<br>'.$infos.'<br>'._OR_3.': '.$com;
				addMail($amail, $mail, $subject, $msg, 1, 1);
			}
			if (!$conf['order']['pr']) {
				$amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._ORDER;
				$msg = $conf['sitename'].' - '._ORDER.'<br><br>';
				$msg .= bb_decode($conf['order']['sendinfo'], 'all');
				addMail($mail, $amail, $subject, $msg, 0, 3);
			}
			update_points(34);
			setHead(['title' => _ORDER]);
			echo setTemplateBasic('title', ['{%title%}' => _ORDER]).setTemplateWarning('warn', ['time' => '30', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => bb_decode($conf['order']['info'], 'all')]);
			setFoot();
		} else {
			order();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: order(); break;
	case 'send': send(); break;
}
