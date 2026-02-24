<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');


function content_navi() {
	panel();
	$narg = func_get_args();
	$ops = array('content', 'content_add', 'content_conf', 'content_info');
	$lang = array(_HOME, _ADD, _PREFERENCES, _INFO);
	return navi_gen(_CONTENT, 'content.png', '', $ops, $lang, '', '', $narg[0], $narg[1], $narg[2], $narg[3]);
}

function content() {
	global $db, $admin_file, $conf, $confcn;
	head();
	$cont = content_navi(0, 0, 0, 0);
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num-1) * $confcn['anum'];
	$result = $db->sql_query('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$confcn['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._TITLE."</th><th>"._DATE."</th><th>".cutstr(_READS, 4, 1)."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while (list($id, $title, $time, $counter)= $db->sql_fetchrow($result)) {
			if (time() >= strtotime($time)) {
				$ad_view = "<a href=\"index.php?name=content&amp;op=view&amp;id=".$id."\" title=\""._MVIEW."\">"._MVIEW."</a>||";
				$active = "1";
			} else {
				$ad_view = "";
				$active = "0";
			}
			$cont .= "<tr><td>".$id."</td>"
			."<td>".title_tip(_URL.": ".$conf['homeurl']."/index.php?name=content&amp;op=view&amp;id=".$id."<br>"._ORTYPEURL.": ".$conf['homeurl']."/index.php?go=rss&amp;name=content&amp;id=".$id)."<span title=\"".$title."\" class=\"sl_note\">".cutstr($title, 50)."</span></td>"
			."<td>".format_time($time, _TIMESTRING)."</td>"
			."<td>".$counter."</td>"
			."<td>".ad_status("", $active)."</td>"
			."<td>".add_menu($ad_view."<a href=\"".$admin_file.".php?op=content_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=content_delete&amp;id=".$id."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$title."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
		}
		$cont .= "</tbody></table>";
		$cont .= setArticleNumbers("pagenum", "", $confcn['anum'], "op=content&amp;", "id", "_content", "", "", $confcn['anump']);
		$cont .= tpl_eval('close', '');
	} else {
		$cont .= tpl_warn('warn', _NO_INFO, '', '', 'info');
	}
	echo $cont;
	foot();
}

function content_add() {
	global $db, $admin_file, $stop;
	if (isset($_REQUEST['id'])) {
		$id = getVar('req', 'id', 'num');
		$result = $db->sql_query('SELECT id, title, text, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
		list($cid, $title, $text, $field, $url, $time, $refresh) = $db->sql_fetchrow($result);
	} else {
		$cid = getVar('post', 'cid', 'num');
		$title = save_text(getVar('post', 'title', 'text'), 1);
		$text = save_text(getVar('post', 'text', 'text'));
		$field = fields_save(getVar('post', 'field', 'array'));
		$url = getVar('post', 'url', 'text');
		$time = save_datetime(1, "time");
		$refresh = getVar('post', 'refresh', 'num');
	}
	head();
	$cont = content_navi(0, 1, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	$fields = ($field) ? "<br><br>".fields_out($field, "content") : "";
	if ($text) $cont .= preview($title, $text, "", $field, "content");
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._TITLE.":</td><td><input type=\"text\" name=\"title\" value=\"".$title."\" maxlength=\"100\" class=\"sl_form\" placeholder=\""._TITLE."\" required></td></tr>"
	."<tr><td>"._RSSFILE.":<div class=\"sl_small\">"._RSSINFO."</div></td><td><input type=\"text\" name=\"url\" value=\"".$url."\" maxlength=\"200\" class=\"sl_form\" placeholder=\""._RSSFILE."\"></td></tr>"
	."<tr><td>"._REFRESHTIME.":<div class=\"sl_small\">"._REFINFO."</div></td><td><select name=\"refresh\" class=\"sl_form\">"
	."<option value='1800'";
	if ($refresh == "1800") $cont .= " selected";
	$cont .= ">30 "._MIN.".</option>"
	."<option value='3600'";
	if ($refresh == "3600" || !$refresh) $cont .= " selected";
	$cont .= ">1 "._HOUR."</option>"
	."<option value='18000'";
	if ($refresh == "18000") $cont .= " selected";
	$cont .= ">5 "._HOUR.".</option>"
	."<option value='36000'";
	if ($refresh == "36000") $cont .= " selected";
	$cont .= ">10 "._HOUR.".</option>"
	."<option value='86400'";
	if ($refresh == "86400") $cont .= " selected";
	$cont .= ">24 "._HOUR.".</option>"
	."</select></td></tr>"
	."<tr><td>"._TEXT.":</td><td>".textarea("1", "text", $text, "content", "25", _TEXT, "0")."</td></tr>"
	.fields_in($field, "content")
	."<tr><td>"._CHNGSTORY.":</td><td>".datetime(1, "time", $time, 16, "sl_form")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("cid", $cid, "content_save")."</td></tr></table></form>";
	$cont .= tpl_eval('close', '');
	echo $cont;
	foot();
}

function content_save() {
	global $db, $admin_file, $stop;
	$cid = getVar('post', 'cid', 'num');
	$title = save_text(getVar('post', 'title', 'text'), 1);
	$url = getVar('post', 'url', 'text');
	$post_text = getVar('post', 'text', 'text');
	$text = ($url) ? rss_read($url, 1) : save_text($post_text);
	$field = fields_save(getVar('post', 'field', 'array'));
	$time = save_datetime(1, "time");
	$refresh = getVar('post', 'refresh', 'num');
	if (!$title) $stop[] = _CERROR;
	if (!$text && !$url) $stop[] = _CERROR1;
	if (!$text && $url) $stop[] = _RSSFAIL;
	$posttype = getVar('post', 'posttype', 'text');
	if (!$stop && $posttype == "save") {
		if ($cid) {
			$db->sql_query('UPDATE '.PREFIX_DB.'_content SET title = :title, text = :text, field = :field, url = :url, time = :time, refresh = :refresh WHERE id = :cid', ['title' => $title, 'text' => $text, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh, 'cid' => $cid]);
		} else {
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_content VALUES (NULL, :title, :text, :field, :url, :time, :refresh, \'0\')', ['title' => $title, 'text' => $text, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh]);
		}
		header("Location: ".$admin_file.".php?op=content");
	} elseif ($posttype == "delete") {
		content_delete($cid);
	} else {
		content_add();
	}
}

function content_delete() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = (!empty($arg[0])) ? $arg[0] : getVar('req', 'id', 'num');
	if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
	referer($admin_file.".php?op=content");
}

function content_conf() {
	global $admin_file, $confcn;
	head();
	$cont = content_navi(0, 2, 0, 0);
	$cont .= checkPerms('content.php');
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._C_33.":</td><td><input type=\"number\" name=\"num\" value=\"".$confcn['num']."\" class=\"sl_conf\" placeholder=\""._C_33."\" required></td></tr>"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$confcn['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_35.":</td><td><input type=\"number\" name=\"nump\" value=\"".$confcn['nump']."\" class=\"sl_conf\" placeholder=\""._C_35."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$confcn['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"content_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval('close', '');
	echo $cont;
	foot();
}

function content_conf_save() {
	global $admin_file;
	$cont = [
		'num' => getVar('post', 'num', 'num'),
		'anum' => getVar('post', 'anum', 'num'),
		'nump' => getVar('post', 'nump', 'num'),
		'anump' => getVar('post', 'anump', 'num'),
	];
	setConfigFile('content.php', $cont);
	header("Location: ".$admin_file.".php?op=content_conf");
}

function content_info() {
	head();
	echo content_navi(0, 3, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "content", 0)."</div>";
	foot();
}

switch($op) {
	case "content":
	content();
	break;
	
	case "content_add":
	content_add();
	break;
	
	case "content_save":
	content_save();
	break;
	
	case "content_delete":
	content_delete();
	break;
	
	case "content_conf":
	content_conf();
	break;

	case "content_conf_save":
	content_conf_save();
	break;
	
	case "content_info":
	content_info();
	break;
}
?>
