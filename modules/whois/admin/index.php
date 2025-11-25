<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined("ADMIN_FILE") || !is_admin_modul("whois")) die("Illegal file access");

include("config/config_whois.php");

function whois_navi() {
	panel();
	$narg = func_get_args();
	$ops = array("whois", "whois_add", "whois&amp;status=1", "whois_conf", "whois_info");
	$lang = array(_HOME, _ADD, _NEW, _PREFERENCES, _INFO);
	return navi_gen(_WHOIS, "whois.png", "", $ops, $lang, "", "", $narg[0], $narg[1], $narg[2], $narg[3]);
}

function whois() {
	global $prefix, $db, $admin_file, $confw, $confu;
	head();
	$num = isset($_GET['num']) ? intval($_GET['num']) : "1";
	$offset = ($num-1) * $confw['anum'];
	$offset = intval($offset);
	if ($_GET['status'] == 1) {
		$status = "0";
		$field = "op=whois&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = whois_navi(0, 2, 0, 0);
	} else {
		$status = "1";
		$field = "op=whois&amp;";
		$refer = "&amp;refer=1";
		$cont = whois_navi(0, 0, 0, 0);
	}
	$result = $db->sql_query("SELECT w.id, w.name, w.ip, w.time, w.domain, w.host, w.dc, w.hometext, w.st_domain, w.st_host, w.st_dc, u.user_name FROM ".$prefix."_whois AS w LEFT JOIN ".$prefix."_users AS u ON (w.uid = u.user_id) WHERE status = '".$status."' ORDER BY w.time DESC LIMIT ".$offset.", ".$confw['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list\"><thead><tr><th>"._ID."</th><th>"._POSTEDBY."</th><th colspan=\"2\">"._SITE."</th><th colspan=\"2\">"._HOST."</th><th colspan=\"2\">"._DC."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while (list($id, $uname, $ip_sender, $time, $domain, $host, $dc, $hometext, $st_domain, $st_host, $st_dc, $user_name) = $db->sql_fetchrow($result)) {
			$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
			$ip_sender = ($ip_sender) ? user_geo_ip($ip_sender, 4) : _NO;
			$hometext = ($hometext) ? $hometext : _NO;
			$host = ($host) ? domain($host) : _NO_INFO;
			$dc = ($dc) ? domain($dc) : _NO_INFO;
			$cont .= "<tr><td>".$id."</td>"
			."<td>".title_tip(_DATE.": ".format_time($time, _TIMESTRING)."<br>"._IP.": ".$ip_sender."<br>"._COMMENT.": ".$hometext).$post."</td>"
			."<td>".domain($domain)."</td><td>".ad_status("", $st_domain)."</td>"
			."<td>".$host."</td><td>".ad_status("", $st_host)."</td>"
			."<td>".$dc."</td><td>".ad_status("", $st_dc)."</td>"
			."<td>".add_menu(ad_status($admin_file.".php?op=whois_act&amp;id=".$id."&amp;fid=1".$refer, $st_domain, "", _SITE)."||".ad_status($admin_file.".php?op=whois_act&amp;id=".$id."&amp;fid=2".$refer, $st_host, "", _HOST)."||".ad_status($admin_file.".php?op=whois_act&amp;id=".$id."&amp;fid=3".$refer, $st_dc, "", _DC)."||<a href=\"".$admin_file.".php?op=whois_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=whois_delete&amp;id=".$id.$refer."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$domain."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
		}
		$cont .= "</tbody></table>";
		$cont .= setArticleNumbers("pagenum", "", $confw['anum'], $field, "id", "_whois", "", "status = '".$status."'", $confw['anump']);
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function whois_act() {
	global $prefix, $db, $admin_file;
	$id = intval($_GET['id']);
	$fid = intval($_GET['fid']);
	if ($fid == 1) {
		$field = "st_domain";
	} elseif ($fid == 2) {
		$field = "st_host";
	} elseif ($fid == 3) {
		$field = "st_dc";
	} else {
		$field = "";
	}
	if ($id && $field) {
		list($active) = $db->sql_fetchrow($db->sql_query("SELECT ".$field." FROM ".$prefix."_whois WHERE id = '".$id."'"));
		$active = ($active) ? 0 : 1;
		$db->sql_query("UPDATE ".$prefix."_whois SET ".$field." = '".$active."' WHERE id = '".$id."'");
	}
	referer($admin_file.".php?op=whois");
}

function whois_add() {
	global $prefix, $db, $admin_file, $confu, $stop;
	if (isset($_REQUEST['id'])) {
		$wid = intval($_REQUEST['id']);
		$result = $db->sql_query("SELECT w.id, w.name, w.domain, w.host, w.dc, w.hometext, u.user_name FROM ".$prefix."_whois AS w LEFT JOIN ".$prefix."_users AS u ON (w.uid = u.user_id) WHERE id = '".$wid."'");
		list($id, $uname, $domain, $host, $dc, $hometext, $user_name) = $db->sql_fetchrow($result);
		$postname = ($user_name) ? $user_name : (($uname) ? $uname : $confu['anonym']);
	} else {
		$wid = $_POST['wid'];
		$postname = $_POST['postname'];
		$domain = (isset($_POST['domain'])) ? $_POST['domain'] : "http://";
		$host = (isset($_POST['host'])) ? $_POST['host'] : "http://";
		$dc = (isset($_POST['dc'])) ? $_POST['dc'] : "http://";
		$hometext = $_POST['hometext'];
	}
	head();
	$cont = whois_navi(0, 1, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._POSTEDBY.":</td><td>".get_user_search("postname", $postname, "25", "sl_form", "1")."</td></tr>"
	."<tr><td>"._SITE.":</td><td><input type=\"url\" name=\"domain\" value=\"".$domain."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._SITE."\" required></td></tr>"
	."<tr><td>"._HOST.":</td><td><input type=\"url\" name=\"host\" value=\"".$host."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._HOST."\"></td></tr>"
	."<tr><td>"._DC.":</td><td><input type=\"url\" name=\"dc\" value=\"".$dc."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._DC."\"></td></tr>"
	."<tr><td>"._COMMENT.":</td><td><textarea name=\"hometext\" cols=\"65\" rows=\"5\" class=\"sl_form\" placeholder=\""._COMMENT."\">".$hometext."</textarea></td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("wid", $wid, "whois_save", 1)."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function whois_save() {
	global $prefix, $db, $admin_file, $stop;
	$wid = intval($_POST['wid']);
	$postname = $_POST['postname'];
	$domain = url_filter($_POST['domain']);
	$host = url_filter($_POST['host']);
	$dc = url_filter($_POST['dc']);
	$hometext = text_filter($_POST['hometext']);
	$stop = array();
	if (!$postname) $stop[] = _CERROR3;
	if (!$domain) $stop[] = _CERROR4;
	if (!$stop  && $_POST['posttype'] == "save") {
		$postid = (is_user_id($postname)) ? is_user_id($postname) : "";
		$postname = (!is_user_id($postname)) ? text_filter(substr($postname, 0, 25)) : "";
		if ($wid) {
			$db->sql_query("UPDATE ".$prefix."_whois SET uid = '".$postid."', name = '".$postname."', domain = '".$domain."', host = '".$host."', dc = '".$dc."', hometext = '".$hometext."', status = '1' WHERE id = '".$wid."'");
		} else {
			$ip = getip();
			$db->sql_query("INSERT INTO ".$prefix."_whois (id, uid, name, ip, time, domain, host, dc, hometext, st_domain, st_host, st_dc, status) VALUES (NULL, '".$postid."', '".$postname."', '".$ip."', now(), '".$domain."', '".$host."', '".$dc."', '".$hometext."', '0', '0', '0', '1')");
		}
		header("Location: ".$admin_file.".php?op=whois");
	} elseif ($_POST['posttype'] == "delete") {
		whois_delete($wid);
	} else {
		whois_add();
	}
}

function whois_delete() {
	global $prefix, $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) $db->sql_query("DELETE FROM ".$prefix."_whois WHERE id = '".$id."'");
	referer($admin_file.".php?op=whois");
}

function whois_conf() {
	global $admin_file, $confw;
	head();
	$cont = whois_navi(0, 3, 0, 0);
	$permtest = end_chmod("config/config_whois.php", 666);
	if ($permtest) $cont .= tpl_warn("warn", $permtest, "", "", "warn");
	$cont .= tpl_eval("open");
	$cont .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$confw['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$confw['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td>"._ADDAMAIL."</td><td>".radio_form($confw['addmail'], "addmail")."</td></tr>"
	."<tr><td>"._WHOISADD."</td><td>".radio_form($confw['add'], "add")."</td></tr>"
	."<tr><td>"._WHOISADDG."</td><td>".radio_form($confw['addquest'], "addquest")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"whois_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function whois_conf_save() {
	global $admin_file;
	$content = "\$confw = array();\n"
	."\$confw['anum'] = \"".$_POST['anum']."\";\n"
	."\$confw['anump'] = \"".$_POST['anump']."\";\n"
	."\$confw['addmail'] = \"".$_POST['addmail']."\";\n"
	."\$confw['add'] = \"".$_POST['add']."\";\n"
	."\$confw['addquest'] = \"".$_POST['addquest']."\";\n";
	save_conf("config/config_whois.php", $content);
	header("Location: ".$admin_file.".php?op=whois_conf");
}

function whois_info() {
	head();
	echo whois_navi(0, 4, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "whois", 0)."</div>";
	foot();
}

switch($op) {
	case "whois":
	whois();
	break;
	
	case "whois_act":
	whois_act();
	break;
	
	case "whois_add":
	whois_add();
	break;
	
	case "whois_save":
	whois_save();
	break;
	
	case "whois_delete":
	whois_delete();
	break;
	
	case "whois_conf":
	whois_conf();
	break;
	
	case "whois_conf_save":
	whois_conf_save();
	break;
	
	case "whois_info":
	whois_info();
	break;
}
?>