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

function order() {
	global $conf, $confor, $stop;
	if (is_user()) {
		$userinfo = getusrinfo();
		$mail = getVar('post', 'mail', 'text', $userinfo['user_email']);
	} else {
		$mail = getVar('post', 'mail', 'text');
	}
	$field = getVar('post', 'field', 'field');
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => _ORDER));
	$cont .= setTemplateBasic('open');
	$cont .= bb_decode($confor['text'], "all");
	$cont .= setTemplateBasic('close');
	if ($confor['an']) {
		$com = getVar('post', 'com', 'text');
		if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
		$cont .= setTemplateBasic('open');
		$cont .= "<h2>"._OR_1."</h2><form name=\"post\" action=\"index.php?name=".$conf['name']."\" method=\"post\"><table class=\"sl_table_form\">"
		."<tr><td>"._OR_2.":</td><td><input type=\"email\" name=\"mail\" value=\"".$mail."\" maxlength=\"255\" class=\"sl_field ".$conf['style']."\" placeholder=\""._OR_2."\" required></td></tr>"
		.fields_in($field, $conf['name'])
		."<tr><td>"._OR_3.":</td><td><textarea name=\"com\" cols=\"65\" rows=\"5\" class=\"sl_field ".$conf['style']."\">".$com."</textarea></td></tr>"
		."<tr><td colspan=\"2\" class=\"sl_center\">".getCaptcha(1)."<input type=\"hidden\" name=\"op\" value=\"send\"><input type=\"submit\" value=\""._OR_4."\" class=\"sl_but_blue\"></td></tr></table></form>";
		$cont .= setTemplateBasic('close');
	} else {
		$cont .= tpl_warn("warn", _MO_11, "", "", "info");
	}
	echo $cont;
	foot();
}

function send() {
	global $db, $conf, $confor, $stop;
	if ($confor['an']) {
		$mail = getVar('post', 'mail', 'text');
		$info = getVar('post', 'field', 'field');
		$com = getVar('post', 'com', 'text');
		$stop = array();
		checkemail($mail);
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if (!$stop) {
			$status = ($confor['pr']) ? "0" : "1";
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :mail, :info, :com, :ip, :agent, NOW(), :status)', ['mail' => $mail, 'info' => $info, 'com' => $com, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]);
			if ($confor['ad']) {
				$infos = fields_out($info, $conf['name']);
				$amail = ($confor['mail']) ? $confor['mail'] : $conf['adminmail'];
				$subject = $conf['sitename']." - "._ORDER;
				$msg = $conf['sitename']." - "._ORDER."<br><br><b>"._PERSONALINFO."</b><br><br>"._OR_2.": ".$mail."<br>".$infos."<br>"._OR_3.": ".$com;
				mail_send($amail, $mail, $subject, $msg, 1, 1);
			}
			if (!$confor['pr']) {
				$amail = ($confor['mail']) ? $confor['mail'] : $conf['adminmail'];
				$subject = $conf['sitename']." - "._ORDER;
				$msg = $conf['sitename']." - "._ORDER."<br><br>";
				$msg .= bb_decode($confor['sendinfo'], "all");
				mail_send($mail, $amail, $subject, $msg, 0, 3);
			}
			update_points(34);
			head();
			echo setTemplateBasic('title', array('{%title%}' => _ORDER)).tpl_warn("warn", bb_decode($confor['info'], "all"), "?name=".$conf['name'], 30, "info");
			foot();
		} else {
			order();
		}
	} else {
		header("Location: index.php?name=".$conf['name']);
	}
}

switch($op) {
	default: order(); break;
	case 'send': send(); break;
}
?>
