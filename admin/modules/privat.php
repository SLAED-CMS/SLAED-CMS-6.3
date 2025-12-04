<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined("ADMIN_FILE") || !is_admin_god()) die("Illegal file access");

function privatNavi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
	panel();
	$ops = ['show', 'conf', 'info'];
	$lang = [_HOME, _PREFERENCES, _INFO];
	return getAdminTabs(_PRIVAT, 'privat.png', 'name=privat', $ops, $lang, [], [], $tab, $subtab);
}

function privat() {
	head();
	echo privatNavi(0, 0, 0, 0).tpl_eval("open")."<div id=\"repajax_privat\">".ajax_privat(1)."</div>".tpl_eval("close", "");
	foot();
}

function privatConf() {
	global $admin_file;
	head();
	$cont = privatNavi(0, 1, 0, 0);
	include("config/config_privat.php");
	$permtest = end_chmod("config/config_privat.php", 666);
	if ($permtest) $cont .= tpl_warn("warn", $permtest, "", "", "warn");
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._C_33.":</td><td><input type=\"number\" name=\"num\" value=\"".$confpr['num']."\" class=\"sl_conf\" placeholder=\""._C_33."\" required></td></tr>"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$confpr['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_35.":</td><td><input type=\"number\" name=\"nump\" value=\"".$confpr['nump']."\" class=\"sl_conf\" placeholder=\""._C_35."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$confpr['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td>"._COMLETTER.":</td><td><input type=\"number\" name=\"letter\" value=\"".$confpr['letter']."\" class=\"sl_conf\" placeholder=\""._COMLETTER."\" required></td></tr>"
	."<tr><td>"._CSEND.":</td><td><input type=\"number\" name=\"send\" value=\"".$confpr['send']."\" class=\"sl_conf\" placeholder=\""._CSEND."\" required></td></tr>"
	."<tr><td>"._PRINM.":</td><td><input type=\"number\" name=\"messin\" value=\"".$confpr['messin']."\" class=\"sl_conf\" placeholder=\""._PRINM."\" required></td></tr>"
	."<tr><td>"._PRSAVEM.":</td><td><input type=\"number\" name=\"messsav\" value=\"".$confpr['messsav']."\" class=\"sl_conf\" placeholder=\""._PRSAVEM."\" required></td></tr>"
	."<tr><td>"._PRMAIL."</td><td>".radio_form($confpr['newmail'], "newmail")."</td></tr>"
	."<tr><td>"._PRSELF."</td><td>".radio_form($confpr['himself'], "himself")."</td></tr>"
	."<tr><td>"._VPROFIL."</td><td>".radio_form($confpr['profil'], "profil")."</td></tr>"
	."<tr><td>"._VWEB."</td><td>".radio_form($confpr['web'], "web")."</td></tr>"
	."<tr><td>"._PRACT."</td><td>".radio_form($confpr['act'], "act")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"name\" value=\"privat\"><input type=\"hidden\" name=\"op\" value=\"confsave\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close", "");
	echo $cont;
	foot();
}

function privatConfSave() {
	global $admin_file;
	$xnum = (!intval($_POST['num'])) ? 50 : $_POST['num'];
	$xanum = (!intval($_POST['anum'])) ? 50 : $_POST['anum'];
	$xnump = (!intval($_POST['nump'])) ? 10 : $_POST['nump'];
	$xanump = (!intval($_POST['anump'])) ? 10 : $_POST['anump'];
	$xletter = (!intval($_POST['letter'])) ? 100 : $_POST['letter'];
	$xsend = (!intval($_POST['send'])) ? 60 : $_POST['send'];
	$xmessin = (!intval($_POST['messin'])) ? 250 : $_POST['messin'];
	$xmesssav = (!intval($_POST['messsav'])) ? 250 : $_POST['messsav'];
	$xnewmail = (!intval($_POST['newmail'])) ? "0" : "1";
	$xhimself = (!intval($_POST['himself'])) ? "0" : "1";
	$xprofil = (!intval($_POST['profil'])) ? "0" : "1";
	$xweb = (!intval($_POST['web'])) ? "0" : "1";
	$xact = (!intval($_POST['act'])) ? "0" : "1";
	$content = "\$confpr = array();\n"
	."\$confpr['num'] = \"".$xnum."\";\n"
	."\$confpr['anum'] = \"".$xanum."\";\n"
	."\$confpr['nump'] = \"".$xnump."\";\n"
	."\$confpr['anump'] = \"".$xanump."\";\n"
	."\$confpr['letter'] = \"".$xletter."\";\n"
	."\$confpr['send'] = \"".$xsend."\";\n"
	."\$confpr['messin'] = \"".$xmessin."\";\n"
	."\$confpr['messsav'] = \"".$xmesssav."\";\n"
	."\$confpr['newmail'] = \"".$xnewmail."\";\n"
	."\$confpr['himself'] = \"".$xhimself."\";\n"
	."\$confpr['profil'] = \"".$xprofil."\";\n"
	."\$confpr['web'] = \"".$xweb."\";\n"
	."\$confpr['act'] = \"".$xact."\";\n";
	save_conf("config/config_privat.php", $content);
	header("Location: ".$admin_file.".php?name=privat&op=conf");
}

function privatInfo() {
	head();
	echo privatNavi(0, 2, 0, 0)."<div id=\"repadm_info\">".adm_info(1, 0, "privat")."</div>";
	foot();
}

switch($op) {
	case "show":
	privat();
	break;

	case "conf":
	privatConf();
	break;

	case "confsave":
	privatConfSave();
	break;

	case "info":
	privatInfo();
	break;
}
?>