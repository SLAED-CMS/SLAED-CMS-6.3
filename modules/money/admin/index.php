<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined("ADMIN_FILE") || !is_admin_modul("money")) die("Illegal file access");


function money_navi() {
	panel();
	$narg = func_get_args();
	$ops = array("money", "money_add", "money_conf", "money_info");
	$lang = array(_HOME, _ADD, _PREFERENCES, _INFO);
	return navi_gen(_MONEY, "money.png", "", $ops, $lang, "", "", $narg[0], $narg[1], $narg[2], $narg[3]);
}

function money() {
	global $db, $admin_file, $conf, $confmo;
	head();
	$cont = money_navi(0, 0, 0, 0);
	if (getVar('get', 'send')) $cont .= tpl_warn("warn", _MA_15, "", "", "info");
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num-1) * $confmo['anum'];
	$offset = intval($offset);
	$result = $db->sql_query("SELECT id, sum, mail, info, com, ip, agent, date, status FROM ".PREFIX_DB."_money ORDER BY date DESC LIMIT ".$offset.", ".$confmo['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		list($numstories) = $db->sql_fetchrow($db->sql_query("SELECT Count(id) FROM ".PREFIX_DB."_money"));
		$r = $numstories;
		if ($numstories > $offset) $r -= $offset;
		$numpages = ceil($numstories / $confmo['anum']);
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._SUM."</th><th>"._EMAIL."</th><th>"._IP."</th><th>"._DATE."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		$form = explode(",", $confmo['form']);
		while (list($id, $sum, $mail, $info, $com, $ip, $agent, $date, $status) = $db->sql_fetchrow($result)) {
			$act = ($status) ? 0 : 1;
			$info = explode("|", $info);
			$i = 0;
			$infos = "";
			foreach ($form as $val) {
				if ($val != "") {
					$infos .= $val.": ".$info[$i]."<br>";
					$i++;
				}
			}
			$cont .= "<tr><td>".$id."</td>"
			."<td>".$sum." EUR</td>"
			."<td>".title_tip($infos."<br>"._COMMENT.": ".$com."<br><br>"._BROWSER.": ".$agent).anti_spam($mail)."</td>"
			."<td>".user_geo_ip($ip, 4)."</td>"
			."<td>".format_time($date, _TIMESTRING)."</td>"
			."<td>".ad_status("", $status)."</td>"
			."<td>".add_menu(ad_status($admin_file.".php?op=money_active&amp;id=".$id."&amp;act=".$act, $status)."||<a href=\"".$admin_file.".php?op=money_rechn&amp;id=".$id."&amp;rnum=".$r."\" title=\""._RECHN_B."\">"._RECHN_B."</a>||<a href=\"".$admin_file.".php?op=money_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=money_delete&amp;id=".$id."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;"._ID.": ".$id."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
			$r--;
		}
		$cont .= "</tbody></table>";
		$cont .= setPageNumbers("pagenum", "", $numstories, $numpages, $confmo['anum'], "op=money&amp;", $confmo['anump']);
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function money_add() {
	global $db, $admin_file, $stop, $confmo;
	if ($mid = getVar('req', 'id', 'num')) {
		$result = $db->sql_query('SELECT sum, mail, info, com, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $mid]);
		list($sum, $mail, $info, $com, $date) = $db->sql_fetchrow($result);
		$info = explode("|", $info);
	} else {
		$mid = getVar('post', 'mid', 'num');
		$sum= getVar('post', 'sum', 'num');
		$mail = getVar('post', 'mail', 'text');
		$info = getVar('post', 'info', 'array');
		$com = save_text(getVar('post', 'com', 'text'), 1);
		$date = save_datetime(1, "date");
	}
	head();
	$cont = money_navi(0, 1, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	if ($info) {
		$form = explode(",", $confmo['form']);
		$i = 0;
		$infos = "";
		foreach ($form as $val) {
			if ($val != "") {
				$infos .= $val.": ".$info[$i]."<br>";
				$i++;
			}
		}
		$cont .= preview($mail, $infos, _COMMENT.": ".$com, "", "all");
	}
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._MA_17.":</td><td><input type=\"number\" name=\"sum\" value=\"".$sum."\" class=\"sl_form\" placeholder=\""._MA_17."\" required></td></tr>"
	."<tr><td>"._MA_18.":</td><td><input type=\"email\" name=\"mail\" value=\"".$mail."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._MA_18."\" required></td></tr>";
	$form = explode(",", $confmo['form']);
	$i = 0;
	foreach ($form as $val) {
		if ($val != "") {
			$cont .= "<tr><td>".$val.":</td><td><input type=\"text\" name=\"info[]\" value=\"".$info[$i]."\" maxlength=\"255\" class=\"sl_form\" placeholder=\"".$val."\"></td></tr>";
			$i++;
		}
	}
	$cont .= "<tr><td>"._MA_19.":</td><td><textarea name=\"com\" cols=\"65\" rows=\"5\" class=\"sl_form\" placeholder=\""._MA_19."\">".$com."</textarea></td></tr>"
	."<tr><td>"._CHNGSTORY.":</td><td>".datetime(1, "date", $date, 16, "sl_form")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("mid", $mid, "money_save")."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function money_save() {
	global $db, $admin_file, $stop;
	$mid = getVar('post', 'mid', 'num');
	$sum = getVar('post', 'sum', 'num');
	$mail = text_filter(getVar('post', 'mail', 'text'));
	$info_val = getVar('post', 'info', 'array');
	$info = (!empty($info_val)) ? text_filter(implode("|", $info_val)) : "";
	$com = text_filter(getVar('post', 'com', 'text'));
	$date = save_datetime(1, "date");
	checkemail($mail);
	if (!$stop && getVar('post', 'posttype', 'text') == "save") {
		if ($mid) {
			$db->sql_query('UPDATE '.PREFIX_DB.'_money SET sum = :sum, mail = :mail, info = :info, com = :com, date = :date WHERE id = :mid', ['sum' => $sum, 'mail' => $mail, 'info' => $info, 'com' => $com, 'date' => $date, 'mid' => $mid]);
		} else {
			$ip = getip();
			$agent = getagent();
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_money VALUES (NULL, :sum, :mail, :info, :com, :ip, :agent, :date, \'1\')', ['sum' => $sum, 'mail' => $mail, 'info' => $info, 'com' => $com, 'ip' => $ip, 'agent' => $agent, 'date' => $date]);
		}
		header("Location: ".$admin_file.".php?op=money");
	} elseif (getVar('post', 'posttype', 'text') == "delete") {
		money_delete($mid);
	} else {
		money_add();
	}
}

function money_delete() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]);
	referer($admin_file.".php?op=money");
}

function billing($title, $autor, $infos, $num, $date, $menge, $kurs, $sum) {
	global $theme, $conf;
	$template = file_get_contents("modules/money/templates/billing.html");
	$replacements = [
		'$charset' => _CHARSET,
		'$theme' => $theme,
		'$title' => $title,
		'$site_logo' => $conf['site_logo'],
		'$sitename' => $conf['sitename'],
		'$autor' => $autor,
		'$infos' => $infos,
		'$num' => $num,
		'$date' => $date,
		'$menge' => $menge,
		'$kurs' => $kurs,
		'$sum' => $sum,
	];
	echo str_replace(array_keys($replacements), array_values($replacements), $template);
}

function money_rechn() {
	global $db, $admin_file, $conf, $confmo;
	$id = getVar('get', 'id', 'num');
	list($sum, $mail, $info, $com, $ip, $agent, $date) = $db->sql_fetchrow($db->sql_query('SELECT sum, mail, info, com, ip, agent, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
	setThemeInclude();
	$conf['defis'] = urldecode($conf['defis']);
	$title = _RECHN." ".$conf['defis']." "._MONEY." ".$conf['defis']." ".$conf['sitename'];
	$form = explode(",", $confmo['form']);
	$info = explode("|", $info);
	$i = 0;
	$infos = "";
	foreach ($form as $val) {
		if ($val != "") {
			$infos .= $val.": ".$info[$i]."<br>";
			$i++;
		}
	}
	$num = getVar('get', 'rnum', 'text');
	$menge = $sum /100 * $confmo['kurs'] * (100 - $confmo['proz']);
	$kurs = round($sum / $menge, 2);
	billing($title, bb_decode($confmo['autor'], "money"), bb_decode($infos, "money"), $num, format_time($date), round($menge, 2), $kurs." EUR", $sum." EUR");
}

function money_conf() {
	global $admin_file, $confmo;
	head();
	$cont = money_navi(0, 2, 0, 0);
	$cont .= checkPerms('money.php');
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._MA_3.":</td><td><input type=\"text\" name=\"proz\" value=\"".$confmo['proz']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._MA_3."\" required></td></tr>"
	."<tr><td>"._MA_4.": EUR > USD</td><td><input type=\"text\" name=\"kurs\" value=\"".$confmo['kurs']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._MA_4."\" required></td></tr>"
	."<tr><td>"._MA_4.": EUR > RUB</td><td><input type=\"text\" name=\"kurs2\" value=\"".$confmo['kurs2']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._MA_4."\" required></td></tr>"
	."<tr><td>"._MA_5.":</td><td><input type=\"text\" name=\"bal\" value=\"".$confmo['bal']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._MA_5."\" required></td></tr>"
	."<tr><td>"._MA_6.":</td><td><input type=\"email\" name=\"mail\" value=\"".$confmo['mail']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._MA_6."\" required></td></tr>"
	."<tr><td>"._MA_7.":</td><td><textarea name=\"form\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._MA_7."\" required>".$confmo['form']."</textarea></td></tr>"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$confmo['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$confmo['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td>"._MA_8."</td><td>".radio_form($confmo['an'], "an")."</td></tr>"
	."<tr><td>"._MA_9."</td><td>".radio_form($confmo['pr'], "pr")."</td></tr>"
	."<tr><td>"._MA_10."</td><td>".radio_form($confmo['ad'], "ad")."</td></tr>"
	."<tr><td>"._MA_11.":</td><td>".textarea("1", "text", $confmo['text'], "all", "5", _MA_11, "1")."</td></tr>"
	."<tr><td>"._MA_12.":</td><td>".textarea("2", "info", $confmo['info'], "all", "5", _MA_12, "1")."</td></tr>"
	."<tr><td>"._MA_13.":</td><td>".textarea("3", "sendinfo", $confmo['sendinfo'], "all", "5", _MA_13, "1")."</td></tr>"
	."<tr><td>"._MA_14.":</td><td>".textarea("4", "autor", $confmo['autor'], "all", "5", _MA_14, "1")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"money_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function money_conf_save() {
	global $admin_file;
	$protect = array("\n" => "", "\t" => "", "\r" => "");
	$xkurs = str_replace(",", ".", getVar('post', 'kurs', 'text'));
	$xkurs2 = str_replace(",", ".", getVar('post', 'kurs2', 'text'));
	$xform = strtr(getVar('post', 'form', 'raw'), $protect);
	$xtext= save_text(getVar('post', 'text', 'text'));
	$xinfo = save_text(getVar('post', 'info', 'text'));
	$xsendinfo = save_text(getVar('post', 'sendinfo', 'text'));
	$xautor = save_text(getVar('post', 'autor', 'text'));
	$cont = [
		'proz' => getVar('post', 'proz', 'text'),
		'kurs' => $xkurs,
		'kurs2' => $xkurs2,
		'bal' => getVar('post', 'bal', 'text'),
		'mail' => getVar('post', 'mail', 'text'),
		'form' => $xform,
		'anum' => getVar('post', 'anum', 'num'),
		'anump' => getVar('post', 'anump', 'num'),
		'an' => getVar('post', 'an', 'num'),
		'pr' => getVar('post', 'pr', 'num'),
		'ad' => getVar('post', 'ad', 'num'),
		'text' => $xtext,
		'info' => $xinfo,
		'sendinfo' => $xsendinfo,
		'autor' => $xautor,
	];
	setConfigFile('money.php', $cont);
	header("Location: ".$admin_file.".php?op=money_conf");
}

function money_info() {
	head();
	echo money_navi(0, 3, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "money", 0)."</div>";
	foot();
}

switch($op) {
	case "money":
	money();
	break;
	
	case "money_add":
	money_add();
	break;
	
	case "money_save":
	money_save();
	break;
	
	case "money_active":
	$act = getVar('get', 'act', 'num');
	$id = getVar('get', 'id', 'num');
	$db->sql_query('UPDATE '.PREFIX_DB.'_money SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
	if ($act) {
		list($mail) = $db->sql_fetchrow($db->sql_query('SELECT mail FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
		$amail = ($confmo['mail']) ? $confmo['mail'] : $conf['adminmail'];
		$subject = $conf['sitename']." - "._MONEY;
		$msg = $conf['sitename']." - "._MONEY."<br><br>";
		$msg .= bb_decode($confmo['sendinfo'], "all");
		mail_send($mail, $amail, $subject, $msg, 0, 3);
		header("Location: ".$admin_file.".php?op=money&send=1");
	} else {
		header("Location: ".$admin_file.".php?op=money");
	}
	break;
	
	case "money_delete":
	money_delete();
	break;
	
	case "money_rechn":
	money_rechn();
	break;
	
	case "money_conf":
	money_conf();
	break;
	
	case "money_conf_save":
	money_conf_save();
	break;
	
	case "money_info":
	money_info();
	break;
}
?>
