<?php
# Author: Eduard Laas
# Copyright © 2005 - 2021SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}
get_lang($conf['name']);
include('config/config_jokes.php');

function navigate($title, $cat='') {
	global $conf, $confj;
	$ncat = getVar('get', 'cat', 'num');
	$ncat = ($ncat) ? '&cat='.$ncat : '';
	$home = '<a href="'.getHref(array('name='.$conf['name'], '', '', '', '', '', '', '')).'" title="'._JOKES.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($confj['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=best', '', '', '', '', '', '', '')).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($confj['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=pop', '', '', '', '', '', '', '')).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$add = ((is_user() && $confj['add'] == 1) || (!is_user() && $confj['addquest'] == 1)) ? '<a href="'.getHref(array('name='.$conf['name'].'&op=add', '', '', '', '', '', '', '')).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', array('{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => '', '{%add%}' => $add, '{%catshow%}' => $catshow));
}

function jokes() {
	global $prefix, $db, $admin_file, $user, $conf, $confu, $confj, $home, $op;
	$cwhere = catmids($conf['name'], 'j.cat');
	$word = getVar('get', 'word', 'word');
	$unum = user_news($user[3], $confj['num']);
	$ncat = getVar('get', 'cat', 'num');
	if (!$ncat && $op && $confj['rate']) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'best') {
			$orderby = "(j.rating/j.ratingtot) DESC";
			$ntitle = _BEST;
		} else {
			$orderby = "(j.ratingtot/(TO_DAYS(NOW()) - TO_DAYS(j.date))) DESC";
			$ntitle = _POP;
		}
		$order = "WHERE j.date <= NOW() AND j.status != '0' ".$cwhere." ORDER BY ".$orderby;
		$onum = "date <= NOW() AND status != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		$orderby = ($op) ? (($op == 'best') ? "(j.rating/j.ratingtot) DESC" : "(j.ratingtot/(TO_DAYS(NOW()) - TO_DAYS(j.date))) DESC") : "j.date DESC";
		list($ctitle) = $db->sql_fetchrow($db->sql_query("SELECT title FROM ".$prefix."_categories WHERE id = '".$ncat."'"));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (j.cat = '".$ncat."' OR c.parentid = '".$ncat."') AND j.date <= NOW() AND j.status != '0' ".$cwhere." ORDER BY ".$orderby;
		$catid = array();
		$result = $db->sql_query("SELECT id FROM ".$prefix."_categories WHERE parentid = '".$ncat."'");
		while (list($caid) = $db->sql_fetchrow($result)) $catid[] = $caid;
		unset($result);
		if (isArray($catid)) {
			$caton = 1;
			array_unshift($catid, $ncat);
			$wcid = 'cat IN ('.implode(', ', $catid).')';
		} else {
			$caton = 0;
			$wcid = "cat = '".$ncat."'";
		}
		$onum = $wcid." AND date <= NOW() AND status != '0'";
	} else {
		$caton = 1;
		$field = "";
		$order = "WHERE j.date <= NOW() AND j.status != '0' ".$cwhere." ORDER BY j.date DESC";
		$onum = "date <= NOW() AND status != '0'";
		$ntitle = _JOKES;
	}
	head();
	$cont = '';
	if (!$home || ($home && $confj['homcat'])) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= tpl_eval("cat-navi", catlink($conf['name'], $ncat, $confj['defis'], _JOKES));
		if ($caton == 1) $cont .= setCategories($conf['name'], $confj['subcat'], $confj['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT j.jokeid, j.name, j.date, j.title, j.cat, j.joke, j.rating, j.ratingtot, c.title, c.description, c.img, u.user_name FROM ".$prefix."_jokes AS j LEFT JOIN ".$prefix."_categories AS c ON (j.cat=c.id) LEFT JOIN ".$prefix."_users AS u ON (j.uid=u.user_id) ".$order." LIMIT ".$offset.", ".$unum);
	if ($db->sql_numrows($result) > 0) {
		while (list($id, $uname, $time, $jtitle, $cid, $joke, $rating, $ratingtot, $ctitle, $cdesc, $cimg, $user_name) = $db->sql_fetchrow($result)) {
			$title = "<a href=\"#".$id."\" title=\"".$jtitle."\">".search_color($jtitle, $word)."</a> ".new_graphic($time);
			$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
			$post = "<span title=\""._POSTEDBY."\" class=\"sl_post\">".$post."</span>";
			$date = ($confj['date']) ? "<span title=\""._CHNGSTORY."\" class=\"sl_date\">".format_time($time)."</span>" : "";
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? "<a href=\"index.php?name=".$conf['name']."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_cat\">".cutstr($ctitle, 15)."</a>" : "";
			$cimg = ($cimg) ? "<a href=\"index.php?name=".$conf['name']."&amp;cat=".$cid."\" title=\"".$cdesc."\" class=\"sl_icat\"><img src=\"".img_find("categories/".$cimg)."\" alt=\"".$cdesc."\" title=\"".$cdesc."\"></a>" : "";
			$rating = ajax_rating(1, $id, $conf['name'], $ratingtot, $rating, "");
			$admin = (is_moder($conf['name'])) ? add_menu("<a href=\"".$admin_file.".php?op=jokes_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=jokes_delete&amp;id=".$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$jtitle."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>") : "";
			$cont .= tpl_func("basic", $cid, $cimg, $ctitle, $id, $title, search_color(bb_decode($joke, $conf['name']), $word), "", $post, $date, "", "", "", $rating, $admin, '', '', '');
		}
		$cont .= setArticleNumbers("pagenum", $conf['name'], $unum, $field, "jokeid", "_jokes", "cat", $onum, $confj['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function add() {
	global $db, $prefix, $user, $conf, $confu, $confj, $stop;
	if ($confj['add'] == "1") {
		$title = save_text($_POST['title'], 1);
		$cid = intval($_POST['cid']);
		$joke = save_text($_POST['joke']);
		$postname = text_filter(substr($_POST['postname'], 0, 25));
		
		head();
		$cont = navigate(_ADD);
		if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
		if ($joke) $cont .= preview($title, $joke, "", "", "all");
		$cont .= tpl_warn("warn", _ADD_JNOTE, "", "", "info");
		$cont .= setTemplateBasic('open');
		$cont .= "<form name=\"post\" action=\"index.php?name=".$conf['name']."\" method=\"post\"><table class=\"sl_table_form\">";
		if (is_user()) {
			$cont .= "<tr><td>"._YOURNAME.":</td><td>".text_filter(substr($user[1], 0, 25))."</td></tr>";
		} else {
			$postname = ($postname) ? $postname : $confu['anonym'];
			$cont .= "<tr><td>"._YOURNAME.":</td><td><input type=\"text\" name=\"postname\" value=\"".$postname."\" class=\"sl_field ".$conf['style']."\" placeholder=\""._YOURNAME."\" required></td></tr>";
		}
		$cont .= "<tr><td>"._JTITLE.":</td><td><input type=\"text\" name=\"title\" value=\"".$title."\" maxlength=\"100\" class=\"sl_field ".$conf['style']."\" placeholder=\""._JTITLE."\" required></td></tr>"
		."<tr><td>"._CATEGORY.":</td><td>".getcat($conf['name'], $cid, "cid", $conf['style'], "<option value=\"\">"._HOMECAT."</option>")."</td></tr>"
		."<tr><td>"._JOKE.":</td><td>".textarea("1", "joke", $joke, $conf['name'], "10", _JOKE, "1")."</td></tr>"
		."<tr><td colspan=\"2\" class=\"sl_center\">".getCaptcha(1).ad_save("", "", "send")."</td></tr></table></form>";
		$cont .= setTemplateBasic('close');
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function send() {
	global $prefix, $db, $user, $conf, $confj, $stop;
	if ($confj['add'] == "1") {
		$postname = text_filter(substr($_POST['postname'], 0, 25));
		$title = save_text($_POST['title'], 1);
		$cid = intval($_POST['cid']);
		$joke = save_text($_POST['joke']);
		$stop = array();
		if (!$title) $stop[] = _CERROR;
		if (!$joke) $stop[] = _CERROR1;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if ($db->sql_numrows($db->sql_query("SELECT title FROM ".$prefix."_jokes WHERE title = '".$title."'")) > 0) $stop[] = _JOKEEXIST;
		if (!$stop && $_POST['posttype'] == "save") {
			$postid = (is_user()) ? intval($user[0]) : "";
			$uname = (!is_user()) ? $postname : "";
			$db->sql_query("INSERT INTO ".$prefix."_jokes (jokeid, uid, name, date, title, cat, joke, ip_sender, status) VALUES (NULL, '".$postid."', '".$uname."', NOW(), '".$title."', '".$cid."', '".$joke."', '".getIp()."', '0')");
			update_points(19);
			$puname = (is_user()) ? $user[1] : $postname;
			addmail($confj['addmail'], $conf['name'], $puname, _JOKES);
			head($conf['defis']." "._JOKES." ".$conf['defis']." "._ADD, _UPLOADFINISHJ);
			echo navigate(_ADD).tpl_warn("warn", _UPLOADFINISHJ, "?name=".$conf['name'], 10, "info");
			foot();
		} else {
			add();
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

switch($op) {
	default:
	jokes();
	break;
	
	case "add":
	add();
	break;
	
	case "send":
	send();
	break;
}
?>