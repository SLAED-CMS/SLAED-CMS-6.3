<?php
# Author: Eduard Laas
# Copyright © 2005 - 2025 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}
get_lang($conf['name']);

function search_result() {
	global $prefix, $db, $admin_file, $conf, $confu;
	$search = explode(",", $conf['search']);
	$word = ($_POST['word']) ? var_filter(urldecode($_POST['word'])) : var_filter(urldecode($_GET['word']));
	$mod = analyze($_POST['mod'] ?? $_GET['mod'] ?? '');
	$mod = in_array($mod, $search, true) ? $mod : '';
	$typ = (isset($_POST['typ'])) ? intval($_POST['typ']) : ((isset($_GET['typ'])) ? intval($_GET['typ']) : "");
	$num = (isset($_GET['num'])) ? intval($_GET['num']) : "1";
	$modcont = "";
	foreach ($search as $val) {
		if (is_active($val) && $val != "") {
			$sel = ($val == $mod && $mod != "") ? " selected" : "";
			$modcont .= "<option value=\"".$val."\"".$sel.">".deflmconst($val)."</option>";
		}
	}
	$stop = ($word && strlen($word) < $conf['slet']) ? _SEARCHLETMIN.": ".$conf['slet'] : "";
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => _SEARCH));
	$cont .= setTemplateBasic('open');
	$cont .= "<form action=\"index.php?name=".$conf['name']."\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._MODUL.":</td><td><select name=\"mod\" OnChange=\"submit()\" class=\"sl_field ".$conf['style']."\"><option value=\"\">"._SEARCHALL."</option>".$modcont."</select></td></tr>";
	if ($mod && $mod == "media") {
		$cont .= "<tr><td>"._SEARCHFROM.":</td><td><select name=\"typ\" class=\"sl_field ".$conf['style']."\">"
		."<option value=\"1\"";
		if ($typ == "1") $cont .= " selected";
		$cont .= ">"._MTITLE."</option>"
		."<option value=\"2\"";
		if ($typ == "2" || !$typ) $cont .= " selected";
		$cont .= ">"._DESCRIPTION."</option>"
		."<option value=\"3\"";
		if ($typ == "3") $cont .= " selected";
		$cont .= ">"._MDIRECTOR."</option>"
		."<option value=\"4\"";
		if ($typ == "4") $cont .= " selected";
		$cont .= ">"._MROLES."</option>"
		."<option value=\"5\"";
		if ($typ == "5") $cont .= " selected";
		$cont .= ">"._MYEAR."</option>"
		."</select></td></tr>";
	}
	$cont .= "<tr><td>"._SEARCH.":</td><td><input type=\"text\" name=\"word\" value=\"".$word."\" maxlength=\"100\" class=\"sl_field ".$conf['style']."\" placeholder=\""._SEARCH."\" required></td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"submit\" title=\""._SEARCH."\" value=\""._SEARCH."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= setTemplateBasic('close');
	if (!$stop && $word) {
		if ($conf['asearch']) {
			$result = $db->sql_query("SELECT sl_word FROM ".$prefix."_search WHERE sl_word = '".$word."'");
			if ($db->sql_numrows($result) > 0) {
				$db->sql_query("UPDATE ".$prefix."_search SET sl_time = NOW(), sl_score = sl_score+1 WHERE sl_word = '".$word."'");
			} else {
				$db->sql_query("INSERT INTO ".$prefix."_search VALUES (NULL, '".$word."', '".$mod."', NOW(), '0')");
			}
		}
		$a = 1;
		foreach ($search as $val) {
			if (($mod === '' || $mod === $val) && is_active($val) && $val !== '') {
				if ($val == "auto_links") {
					$result = $db->sql_query("SELECT id, sitename, added FROM ".$prefix."_auto_links WHERE hits != '0' AND (sitename LIKE '%".$word."%' OR description LIKE '%".$word."%' OR link LIKE '%".$word."%') ORDER BY added DESC");
					while (list($id, $title, $date) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=auto_links_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, "", "", $edit);
						$a++;
					}
				} elseif ($val == "faq") {
					$result = $db->sql_query("SELECT f.fid, f.name, f.title, f.time, c.id, c.title, c.description, u.user_name FROM ".$prefix."_faq AS f LEFT JOIN ".$prefix."_categories AS c ON (f.catid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) WHERE f.time <= NOW() AND f.status != '0' AND (f.title LIKE '%".$word."%' OR f.hometext LIKE '%".$word."%') ORDER BY f.time DESC");
					while (list($id, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=faq_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "files") {
					$result = $db->sql_query("SELECT f.lid, f.name, f.title, f.date, c.id, c.title, c.description, u.user_name FROM ".$prefix."_files AS f LEFT JOIN ".$prefix."_categories AS c ON (f.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) WHERE f.date <= NOW() AND f.status != '0' AND (f.title LIKE '%".$word."%' OR f.description LIKE '%".$word."%' OR f.bodytext LIKE '%".$word."%') ORDER BY f.date DESC");
					while (list($id, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=files_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "forum") {
					if (is_moder("forum")) {
						$frecycle = "";
					} else {
						include("config/config_forum.php");
						$frecycle = "f.catid != '".$conffo['recycle']."' AND";
					}
					$result = $db->sql_query("SELECT f.id, f.pid, f.name, f.title, f.time, c.id, c.title, c.description, u.user_name FROM ".$prefix."_forum AS f LEFT JOIN ".$prefix."_categories AS c ON (f.catid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) WHERE ".$frecycle." f.pid = '0' AND f.time <= NOW() AND f.status != '0' AND (f.title LIKE '%".$word."%' OR f.hometext LIKE '%".$word."%') ORDER BY f.time DESC");
					while (list($id, $pid, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$id = (!$pid) ? $id : $pid;
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"index.php?name=".$val."&amp;op=add&amp;cat=".$cid."&amp;id=".$id."&amp;pid=".$pid."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "jokes") {
					$result = $db->sql_query("SELECT j.jokeid, j.name, j.date, j.title, c.id, c.title, c.description, u.user_name FROM ".$prefix."_jokes AS j LEFT JOIN ".$prefix."_categories AS c ON (j.cat = c.id) LEFT JOIN ".$prefix."_users AS u ON (j.uid = u.user_id) WHERE j.date <= NOW() AND j.status != '0' AND (j.title LIKE '%".$word."%' OR j.joke LIKE '%".$word."%') ORDER BY j.date DESC");
					while (list($id, $uname, $date, $title, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;cat=".$cid."&amp;word=".urlencode($word)."#".$id."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=jokes_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;cat=".$cid."&amp;word=".urlencode($word)."#".$id."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "links") {
					$result = $db->sql_query("SELECT l.lid, l.name, l.title, l.date, c.id, c.title, c.description, u.user_name FROM ".$prefix."_links AS l LEFT JOIN ".$prefix."_categories AS c ON (l.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (l.uid = u.user_id) WHERE l.date <= NOW() AND l.status != '0' AND (l.title LIKE '%".$word."%' OR l.description LIKE '%".$word."%' OR l.bodytext LIKE '%".$word."%' OR l.url LIKE '%".$word."%') ORDER BY l.date DESC");
					while (list($id, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=links_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "media") {
					include("config/config_media.php");
					if ($typ == 1 && $word) {
						$sqlstring = "(m.title LIKE '%".$word."%' OR m.subtitle LIKE '%".$word."%') ORDER BY m.title ASC";
					} elseif ($typ == 2 && $word) {
						$sqlstring = "(m.description LIKE '%".$word."%') ORDER BY m.description ASC";
					} elseif ($typ == 3 && $word) {
						$sqlstring = "(m.director LIKE '%".$word."%') ORDER BY m.director ASC";
					} elseif ($typ == 4 && $word) {
						$sqlstring = "(m.roles LIKE '%".$word."%') ORDER BY m.roles ASC";
					} elseif ($typ == 5 && $word) {
						$sqlstring = "(m.year LIKE '%".$word."%') ORDER BY m.year ASC";
					} else {
						$sqlstring = "(m.title LIKE '%".$word."%' OR m.subtitle LIKE '%".$word."%' OR m.description LIKE '%".$word."%') ORDER BY m.date DESC";
					}
					$result = $db->sql_query("SELECT m.id, m.name, m.title, m.subtitle, m.date, c.id, c.title, c.description, u.user_name FROM ".$prefix."_media AS m LEFT JOIN ".$prefix."_categories AS c ON (m.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (m.uid = u.user_id) WHERE m.date <= NOW() AND m.status != '0' AND ".$sqlstring);
					while (list($id, $uname, $title, $subtitle, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = ($subtitle) ? $title." ".urldecode($confm['mdefis'])." ".$subtitle : $title;
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=media_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "news") {
					$result = $db->sql_query("SELECT s.sid, s.name, s.title, s.time, c.id, c.title, c.description, u.user_name FROM ".$prefix."_news AS s LEFT JOIN ".$prefix."_categories AS c ON (s.catid = c.id) LEFT JOIN ".$prefix."_users AS u ON (s.uid = u.user_id) WHERE s.time <= NOW() AND s.status != '0' AND (s.title LIKE '%".$word."%' OR s.hometext LIKE '%".$word."%' OR s.bodytext LIKE '%".$word."%') ORDER BY s.time DESC");
					while (list($id, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=news_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "pages") {
					$result = $db->sql_query("SELECT p.pid, p.name, p.title, p.time, c.id, c.title, c.description, u.user_name FROM ".$prefix."_pages AS p LEFT JOIN ".$prefix."_categories AS c ON (p.catid = c.id) LEFT JOIN ".$prefix."_users AS u ON (p.uid = u.user_id) WHERE p.time <= NOW() AND p.status != '0' AND (p.title LIKE '%".$word."%' OR p.hometext LIKE '%".$word."%' OR p.bodytext LIKE '%".$word."%') ORDER BY p.time DESC");
					while (list($id, $uname, $title, $date, $cid, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
						$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=page_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, $post, $edit);
						$a++;
					}
				} elseif ($val == "shop") {
					$result = $db->sql_query("SELECT p.id, p.time, p.title, c.id, c.title, c.description FROM ".$prefix."_products AS p LEFT JOIN ".$prefix."_categories AS c ON (p.cid = c.id) WHERE time <= NOW() AND active = '1' AND (p.title LIKE '%".$word."%' OR p.text LIKE '%".$word."%' OR p.bodytext LIKE '%".$word."%') ORDER BY time DESC");
					while (list($id, $date, $title, $cid, $ctitle, $cdesc) = $db->sql_fetchrow($result)) {
						$title = "<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" title=\"".$title."\">".search_color($title, $word)."</a> ".new_graphic($date);
						$date = "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($date)."</span>";
						$modul = "<a href=\"index.php?name=".$val."\" title=\""._MODUL."\" class=\"sl_modul\">".deflmconst($val)."</a>";
						$cdesc = ($cdesc) ? $cdesc : $ctitle;
						$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$val."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
						$edit = (is_moder($val)) ? add_menu("<a href=\"".$admin_file.".php?op=shop_products_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"index.php?name=".$val."&amp;op=view&amp;id=".$id."&amp;word=".urlencode($word)."\" target=\"_blank\" title=\""._WINDOWNEW."\">"._WINDOWNEW."</a>") : "";
						$conts[] = array($a, $id, $title, $date, $modul, $ctitle, "", $edit);
						$a++;
					}
				}
			}
		}
		$anum = $a - 1;
		$set = ($num - 1) * $conf['snum'];
		$tnum = ($set) ? $conf['snum'] + $set : $conf['snum'];
		for ($i = $set; $i < $tnum; $i++) {
			if ($conts[$i] != "") $cont .= tpl_func("basic", $conts[$i][0], $conts[$i][1], $conts[$i][2], $conts[$i][3], $conts[$i][4], $conts[$i][5], $conts[$i][6], $conts[$i][7]);
		}
		if (!$anum) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOMATCHES));
		$pnum = ceil($anum / $conf['snum']);
		$lsear = ($typ) ? '&typ='.$typ : '';
		$cont .= ($anum > $conf['snum']) ? setPageNumbers('pagenum', $conf['name'], $anum, $pnum, $conf['snum'], 'mod='.$mod.'&word='.urlencode($word).$lsear.'&', $conf['snump']) : setNaviLower($conf['name']);
	} else {
		$winfo = ($stop) ? $stop : _SEARCHINFO;
		$wtyp = ($stop) ? 'warn' : 'info';
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => $wtyp, 'text' => $winfo));
	}
	echo $cont;
	foot();
}

switch($op) {
	default: search_result(); break;
}