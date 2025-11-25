<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('clients')) die('Illegal file access');

function clients_navi() {
	panel();
	$narg = func_get_args();
	$ops = array('clients', 'clients_add', 'clients_info');
	$lang = array(_HOME, _ADD, _INFO);
	return navi_gen(_CLIENTSA, 'clients.png', '', $ops, $lang, '', '', $narg[0], $narg[1], $narg[2], $narg[3]);
}

function clients() {
	global $prefix, $db, $admin_file, $stop;
	head();
	$cont = clients_navi(0, 0, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", _CERROR, "", "","warn");
	$result = $db->sql_query("SELECT id, title, infotext, url, num, hits, prod_id, status FROM ".$prefix."_clients_down");
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._CTITLE."</th><th>"._CVERSION."</th><th>"._CDATE."</th><th>"._ID."</th><th>"._CLOADS."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while (list($id, $title, $infotext, $url, $num, $hits, $prod_id, $status) = $db->sql_fetchrow($result)) {
			$act = ($status) ? 0 : 1;
			$time = (file_exists("uploads/clients/".$url)) ? date(_TIMESTRING, filemtime("uploads/clients/".$url)) : _NO_INFO;
			$cont .= "<tr>"
			."<td>".$id."</td>"
			."<td>".$title."</td>"
			."<td>".$num."</td>"
			."<td>".$time."</td>"
			."<td>".$prod_id."</td>"
			."<td>".$hits."</td>"
			."<td>".ad_status("", $status)."</td>"
			."<td>".add_menu(ad_status($admin_file.".php?op=clients_active&amp;id=".$id."&amp;act=".$act, $status)."||<a href=\"".$admin_file.".php?op=clients_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=clients_delete&amp;id=".$id."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$title."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
		}
		$cont .= "</tbody></table>";
		$cont .= tpl_eval('close', '');
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function clients_add() {
	global $prefix, $db, $admin_file, $stop;
	if (isset($_REQUEST['id'])) {
		$cid = intval($_REQUEST['id']);
		$result = $db->sql_query("SELECT title, infotext, url, num, code, prod_id, status FROM ".$prefix."_clients_down WHERE id = '".$cid."'");
		list($title, $infotext, $url, $num, $code, $prod_id, $status) = $db->sql_fetchrow($result);
	} else {
		$cid = $_POST['cid'];
		$title = save_text($_POST['title'], 1);
		$infotext = save_text($_POST['infotext']);
		$url = $_POST['url'];
		$num = $_POST['num'];
		$code = $_POST['code'];
		$prod_id =  $_POST['prod_id'];
		$status = $_POST['status'];
	}
	head();
	$cont = clients_navi(0, 1, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	if ($infotext) $cont .= preview($title, $infotext, "", "", "all");
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._CTITLE.":</td><td><input type=\"text\" name=\"title\" value=\"".$title."\" maxlength=\"100\" class=\"sl_form\" placeholder=\""._CTITLE."\" required></td></tr>"
	."<tr><td>"._TEXT.":</td><td>".textarea("1", "infotext", $infotext, "clients", "15", _TEXT, "1")."</td></tr>"
	."<tr><td>"._CURL.":</td><td><input type=\"text\" name=\"url\" value=\"".$url."\" maxlength=\"100\" class=\"sl_form\" placeholder=\""._CURL."\"></td></tr>"
	."<tr><td>"._CVERSION.":</td><td><input type=\"text\" name=\"num\" value=\"".$num."\" maxlength=\"10\" class=\"sl_form\" placeholder=\""._CVERSION."\"></td></tr>"
	."<tr><td>"._CODE.":</td><td><input type=\"text\" name=\"code\" value=\"".$code."\" maxlength=\"100\" class=\"sl_form\" placeholder=\""._CODE."\"></td></tr>"
	."<tr><td>"._ID.":</td><td><input type=\"number\" name=\"prod_id\" value=\"".$prod_id."\" class=\"sl_form\" placeholder=\""._ID."\"></td></tr>"
	."<tr><td>"._CADOWN."</td><td>".radio_form($status, "status")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("cid", $cid, "clients_save")."</td></tr></table></form>";
	$cont .= tpl_eval('close', '');
	echo $cont;
	foot();
}

function clients_save() {
	global $prefix, $db, $admin_file, $stop;
	$cid = intval($_POST['cid']);
	$title = save_text($_POST['title'], 1);
	$infotext = save_text($_POST['infotext']);
	$url = $_POST['url'];
	$num = $_POST['num'];
	$code = $_POST['code'];
	$prod_id =  $_POST['prod_id'];
	$status = $_POST['status'];
	if (!$title) $stop[] = _CERROR;
	if (!$infotext) $stop[] = _CERROR1;
	if (!$stop && $_POST['posttype'] == "save") {
		if ($cid) {
			$db->sql_query("UPDATE ".$prefix."_clients_down SET title = '".$title."', infotext = '".$infotext."', url = '".$url."', num = '".$num."', code = '".$code."', prod_id = '".$prod_id."', status = '".$status."' WHERE id = '".$cid."'");
		} else {
			$db->sql_query("INSERT INTO ".$prefix."_clients_down VALUES (NULL, '".$title."', '".$infotext."', '".$url."', '".$num."', '".$code."', '0', '".$prod_id."', '".$status."')");
		}
		header("Location: ".$admin_file.".php?op=clients");
	} elseif ($_POST['posttype'] == "delete") {
		clients_delete($cid);
	} else {
		clients_add();
	}
}

function clients_delete() {
	global $prefix, $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) $db->sql_query("DELETE FROM ".$prefix."_clients_down WHERE id = '".$id."'");
	header("Location: ".$admin_file.".php?op=clients");
}

function clients_info() {
	head();
	echo clients_navi(0, 2, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "clients", 0)."</div>";
	foot();
}

switch($op) {
	case "clients":
	clients();
	break;

	case "clients_add":
	clients_add();
	break;
	
	case "clients_save":
	clients_save();
	break;
	
	case "clients_active":
	$db->sql_query("UPDATE ".$prefix."_clients_down SET status = '".$act."' WHERE id = '".$id."'");
	header("Location: ".$admin_file.".php?op=clients");
	break;
	
	case "clients_delete":
	clients_delete();
	break;
	
	case "clients_info":
	clients_info();
	break;
}
?>