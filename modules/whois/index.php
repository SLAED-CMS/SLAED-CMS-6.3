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
include('config/config_whois.php');

function navigate($title, $cat='') {
	global $conf, $confw;
	$home = '<a href="'.getHref(array('name='.$conf['name'], '', '', '', '', '', '', '')).'" title="'._WHOIS_LIC.'" class="sl_but_navi">'._HOME.'</a>';
	$add = ((is_user() && $confw['add'] == 1) || (!is_user() && $confw['addquest'] == 1)) ? '<a href="'.getHref(array('name='.$conf['name'].'&op=add', '', '', '', '', '', '', '')).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	return setTemplateBasic('navi', array('{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => '', '{%pop%}' => '', '{%liste%}' => '', '{%add%}' => $add, '{%catshow%}' => ''));
}

function mwhois() {
	global $prefix, $db, $admin_file, $user, $conf, $confu, $confw, $home, $locale;
	global $domain_whois, $ext, $nomatch, $server, $domain_option;
	$domain_licens = getVar('req', 'domain_licens', 'word');
	
	$licens_option = "<fieldset><legend class=\"sl_red\">"._WHOIS_LICENS."</legend>"
	."<form method=\"post\" action=\"index.php?name=".$conf['name']."\">"
	."<table><tr><td><input type=\"text\" name=\"domain_licens\" value=\"".$domain_licens."\" class=\"sl_field ".$conf['style']."\"></td><td><input type=\"hidden\" name=\"option\" value=\"licens\"><input type=\"submit\" value=\""._WHOIS_PR."\" class=\"sl_but_blue\"></td></tr></table>"
	."</form>"
	."</fieldset>";
	
	$domain_whois = getVar('req', 'domain_whois', 'word');
	$ext = getVar('req', 'ext', 'word');
	
	$domain_option = "<fieldset><legend class=\"sl_green\">"._WHOIS_DOM."</legend>"
	."<form method=\"post\" action=\"index.php?name=".$conf['name']."\">"
	."<table><tr><td><input type=\"text\" name=\"domain_whois\" value=\"".$domain_whois."\" class=\"sl_field ".$conf['style']."\"></td><td><select name=\"ext\" class=\"sl_field\">";
	
	$exmas = array("ru", "com", "net", "org", "biz", "info", "name", "us", "de", "in", "co.in", "firm.in", "gen.in", "ind.in", "net.in", "org.in", "com.ru", "net.ru", "org.ru", "pp.ru", "spb.ru", "msk.ru", "ws", "cn");
	foreach ($exmas as $val) {
		if ($val != "") {
			$sel = ($val == $ext) ? "selected" : "";
			$domain_option .= "<option value=\"".$val."\" ".$sel.">.".$val."</option>";
		}
	}
	
	$domain_option .= "</select></td><td><input type=\"hidden\" name=\"option\" value=\"check\"><input type=\"submit\" value=\""._WHOIS_PR."\" class=\"sl_but_blue\"></td></tr></table>"
	."</form>"
	."</fieldset>";

	$serverdefs = array(
	"ru"		=> array("whois.ripn.net","No entries found"),
	"com"	=> array("whois.crsnic.net","No match for"),
	"net"	=> array("whois.crsnic.net","No match for"),
	"org"	=> array("whois.publicinterestregistry.net","NOT FOUND"),
	"biz"	=> array("whois.nic.biz","Not found"),
	"info"	=> array("whois.afilias.net","NOT FOUND"),
	"name"	=> array("whois.nic.name","No match"),
	"us"		=> array("whois.nic.us","Not found:"),
	"de"	=> array("whois.nic.de","status:      free"),
	"in"		=> array("whois.registry.in","NOT FOUND"),
	"co.in"	=> array("whois.registry.in","NOT FOUND"),
	"firm.in"	=> array("whois.registry.in","NOT FOUND"),
	"gen.in"	=> array("whois.registry.in","NOT FOUND"),
	"ind.in"	=> array("whois.registry.in","NOT FOUND"),
	"net.in"	=> array("whois.registry.in","NOT FOUND"),
	"org.in"	=> array("whois.registry.in","NOT FOUND"),
	"com.ru"	=> array("whois.ripn.net","No entries found"),
	"net.ru"	=> array("whois.ripn.net","No entries found"),
	"org.ru"	=> array("whois.ripn.net","No entries found"),
	"pp.ru"	=> array("whois.ripn.net","No entries found"),
	"spb.ru"	=> array("whois.ripn.net","No entries found"),
	"msk.ru"	=> array("whois.ripn.net","No entries found"),
	"ws"	=> array("whois.nic.ws","No match for"),
	"cn"		=> array("whois.cnnic.net.cn","No entries"));
	
	$option = getVar('req', 'option', 'var');
	head();
	$cont = navigate(_WHOIS_LIC);
	$cont .= setTemplateBasic('open');
	$cont .= $licens_option;
	if ($option == "licens" && !namecheck($domain_licens)) {
		$result = $db->sql_query("SELECT website FROM ".$prefix."_clients WHERE active != '2'");
		while (list($website) = $db->sql_fetchrow($result)) $cwebsite[] = $website;
		$cwebsite = implode(",", $cwebsite);
		$cmassiv = explode(",", $cwebsite);
		$wlicens = false;
		foreach ($cmassiv as $val) {
			if ($val != "" && ($val == "http://".strtolower($domain_licens) || $val == "http://www.".strtolower($domain_licens))) {
				$wlicens = true;
				break;
			}
		}
		$cont .= "<fieldset class=\"sl_center\"><legend class=\"sl_blue\">"._WHOIS_SUCH."</legend>";
		if ($wlicens) {
			$cont .= "<span class=\"sl_green\">"._DOMAIN." «".$domain_licens."» "._WHOIS_ISL."!</span>";
		} else {
			$cont .= "<span class=\"sl_red\">"._DOMAIN." «".$domain_licens."» "._WHOIS_NOL."!</span>";
			$cont .= ((is_user() && $confw['add'] == 1) || (!is_user() && $confw['addquest'] == 1)) ? "<form method=\"post\" action=\"index.php?name=".$conf['name']."\"><input type=\"hidden\" name=\"op\" value=\"add\"><input type=\"hidden\" name=\"domain\" value=\"".$domain_licens."\"><input type=\"submit\" value=\""._WHOIS_LICENS_SEND."\" class=\"sl_but_blue\"></form>" : "";
		}
		$cont .= "</fieldset>";
	} elseif ($option == "licens") {
		$cont .= print_results(namecheck($domain_licens), 1);
	}
	$cont .= setTemplateBasic('close');
	$cont .= setTemplateBasic('open');
	if ($option != "check" && $option != "whois") {
		$cont .= $domain_option._WHOIS_TEXT;
	} else {
		if (!namecheck($domain_whois)) {
			if ($serverdefs[$ext]) {
				$server = $serverdefs[$ext][0];
				$nomatch = $serverdefs[$ext][1];
				if ($option=="check") {
					$layout = check_domain($domain_whois,$ext);
					$cont .= print_results($layout, 0);
				}
				if ($option=="whois") $cont .= whois($domain_whois,$ext);
			}
		} else {
			$cont .= print_results(namecheck($domain_whois), 0);
		}
	}
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function add() {
	global $prefix, $db, $user, $conf, $confw, $confu, $stop;
	if ((is_user() && $confw['add'] == 1) || (!is_user() && $confw['addquest'] == 1)) {
		head();
		$cont = navigate(_WHOIS_LICENS_SEND);
		if ($stop) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _ABMIT));
		$hometext = getVar('post', 'hometext', 'text');
		$cont .= setTemplateBasic('open');
		$cont .= "<form name=\"post\" action=\"index.php?name=".$conf['name']."\" method=\"post\"><table class=\"sl_table_form\">";
		if (is_user()) {
			$cont .= "<tr><td>"._YOURNAME.":</td><td>".text_filter(substr($user[1], 0, 25))."</td></tr>";
		} else {
			$postname = getVar('post', 'postname', 'name');
			$postname = ($postname) ? $postname : $confu['anonym'];
			$cont .= "<tr><td>"._YOURNAME.":</td><td><input type=\"text\" name=\"postname\" value=\"".$postname."\" class=\"sl_field ".$conf['style']."\" placeholder=\""._YOURNAME."\" required></td></tr>";
		}
		$domain = getVar('post', 'domain', 'url', 'http://');
		$host = getVar('post', 'host', 'url', 'http://');
		$dc = getVar('post', 'dc', 'url', 'http://');
		
		$cont .= "<tr><td>"._SITE.":</td><td><input type=\"url\" name=\"domain\" value=\"".$domain."\" maxlength=\"255\" class=\"sl_field ".$conf['style']."\" placeholder=\""._SITE."\" required></td></tr>"
		."<tr><td>"._HOST.":</td><td><input type=\"url\" name=\"host\" value=\"".$host."\" maxlength=\"255\" class=\"sl_field ".$conf['style']."\" placeholder=\""._HOST."\"></td></tr>"
		."<tr><td>"._DC.":</td><td><input type=\"url\" name=\"dc\" value=\"".$dc."\" maxlength=\"255\" class=\"sl_field ".$conf['style']."\" placeholder=\""._DC."\"></td></tr>"
		."<tr><td>"._COMMENT.":</td><td><textarea name=\"hometext\" cols=\"65\" rows=\"5\" class=\"sl_field ".$conf['style']."\" placeholder=\""._COMMENT."\">".$hometext."</textarea></td></tr>"
		."<tr><td colspan=\"2\" class=\"sl_center\">".getCaptcha(1)."<input type=\"hidden\" name=\"op\" value=\"send\"><input type=\"submit\" value=\""._SEND."\" class=\"sl_but_blue\"></td></tr></table></form>";
		$cont .= setTemplateBasic('close');
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function send() {
	global $prefix, $db, $user, $conf, $confw, $stop;
	if ((is_user() && $confw['add'] == 1) || (!is_user() && $confw['addquest'] == 1)) {
		$postname = getVar('post', 'postname', 'name');
		$domain = url_filter($_POST['domain']);
		$host = url_filter($_POST['host']);
		$dc = url_filter($_POST['dc']);
		$hometext = text_filter($_POST['hometext']);
		$stop = array();
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (!$domain) $stop[] = _CERROR4;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if ($db->sql_numrows($db->sql_query("SELECT domain FROM ".$prefix."_whois WHERE domain = '".$domain."'")) > 0) $stop[] = _LINKEXIST;
		if (!$stop) {
			$postid = (is_user()) ? intval($user[0]) : "";
			$uname = (!is_user()) ? $postname : "";
			$db->sql_query("INSERT INTO ".$prefix."_whois (id, uid, name, ip, time, domain, host, dc, hometext, st_domain, st_host, st_dc, status) VALUES (NULL, '".$postid."', '".$uname."', '".getIp()."', NOW(), '".$domain."', '".$host."', '".$dc."', '".$hometext."', '0', '0', '0', '0')");
			$puname = (is_user()) ? $user[1] : $postname;
			addmail($confw['addmail'], $conf['name'], $puname, _WHOIS);
			head();
			echo navigate(_WHOIS_LICENS_SEND).setTemplateWarning('warn', array('time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _ABTEXT));
			foot();
		} else {
			add();
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function check_domain($domain_whois, $ext) {
	global $nomatch, $server;
	$output = "";
	if (($sc = fsockopen($server, 43)) == false) {
		return 2;
	}
	fputs($sc, $domain_whois.".".$ext."\n");
	while(!feof($sc)) {
		$output .= fgets($sc, 128);
	}
	fclose($sc);
	if (stripos($nomatch, $output)) {
		return 0;
	} else {
		return 1;
	}
}

function whois($domain_whois, $ext) {
	global $server;
	$cont = "";
	if (($sc = fsockopen($server, 43)) == false) {
		if (($sc = fsockopen($server, 43)) == false) {
			$cont .= "There is a temporary service disruption Please again try later";
			$layout = 2;
			$cont .= print_results($layout, 0);
			exit;
		}
	}
	if ($ext=="com" || $ext=="net") {
		fputs($sc, $domain_whois.".".$ext."\n");
		while(!feof($sc)) {
			$temp = fgets($sc, 128);
			if (stripos("Whois Server:", $temp)) {
				$server = str_replace("Whois Server: ", "", $temp);
				$server = trim($server);
			}
		}
		fclose($sc);
		if (($sc = fsockopen($server, 43)) == false) {
			$layout = 2;
			$cont .= print_results($layout, 0);
			exit;
		}
	}
	$output = "";
	fputs($sc, $domain_whois.".".$ext."\n");
	while(!feof($sc)) {
		$output .= fgets($sc, 128);
	}
	fclose($sc);
	$cont .= print_whois($output);
	return $cont;
}

function print_results($layout, $id) {
	global $domain_whois, $ext, $server, $domain_option, $conf;
	$cont = '';
	if (!$id) $cont .= $domain_option;
	$cont .= "<fieldset class=\"sl_center\"><legend class=\"sl_blue\">"._WHOIS_SUCH."</legend>";
	if ($layout=="0") {
		$cont .= "<span class=\"sl_green\">"._DOMAIN." «".$domain_whois.".".$ext."» "._WHOIS_FREI."!</span>";
	} elseif($layout=="1") {
		$cont .= "<span class=\"sl_red\">"._DOMAIN." «".$domain_whois.".".$ext."» "._WHOIS_B."!</span>"
		."<form method=\"post\" action=\"index.php?name=".$conf['name']."\">"
		."<input type=\"hidden\" name=\"option\" value=\"whois\">"
		."<input type=\"hidden\" name=\"domain_whois\" value=\"".$domain_whois."\">"
		."<input type=\"hidden\" name=\"ext\" value=\"".$ext."\">"
		."<input type=\"submit\" value=\""._WHOIS_INF_US."\" class=\"sl_but_blue\">"
		."</form>";
	} elseif($layout=="2") {
		$cont .= "<span class=\"sl_red\">Could not contact the whois server ".$server."</span>";
	} else {
		$cont .= "<span class=\"sl_red\">".$layout."</span>";
	}
	$cont .= "</fieldset>";
	return $cont;
}

function print_whois($output) {
	global $domain_whois, $ext, $domain_option;
	$cont = "<table><tr><td>"
	.$domain_option
	."<fieldset><legend class=\"sl_blue\">"._WHOIS_INF_US."</legend>"
	."<table><tr><td>";
	$output= explode("\n",$output);
	foreach ($output as $value){
		$cont .= $value."<br>\n";
	}
	$cont .= "</td></tr></table></fieldset>"
	."</td></tr></table>";
	return $cont;
}

function namecheck($domain_whois) {
	if ($domain_whois == "") return _WHOIS_FEL."!";
	if (strlen($domain_whois) < 3) return _WHOIS_FEL1."!";
	if (strlen($domain_whois) > 57) return _WHOIS_FEL2."!";
	if (preg_match("#^-|-$#", $domain_whois)) return _WHOIS_FEL3."!";
	if (preg_match("#[^a-zA-Z0-9._-]#", $domain_whois)) return _WHOIS_FEL4."!";
}

switch($op) {
	default:
	mwhois();
	break;
	
	case "add":
	add();
	break;
	
	case "send":
	send();
	break;
}
?>