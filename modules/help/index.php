<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}
get_lang($conf['name']);
include('config/config_help.php');

function navigate($title, $cat='') {
	global $conf, $confh;
	$ncat = getVar('get', 'cat', 'num');
	$ncat = ($ncat) ? '&cat='.$ncat : '';
	$home = '<a href="'.getHref(array('name='.$conf['name'], '', '', '', '', '', '', '')).'" title="'._HELP.'" class="sl_but_navi">'._HOME.'</a>';
	$closed = '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=closed', '', '', '', '', '', '', '')).'" title="'._CLOSED.'" class="sl_but_navi">'._CLOSED.'</a>';
	$pop = '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=pop', '', '', '', '', '', '', '')).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>';
	$liste = '<a href="'.getHref(array('name='.$conf['name'].'&op=liste', '', '', '', '', '', '', '')).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
	$add = ($confh['add'] == 1) ? '<a href="'.getHref(array('name='.$conf['name'].'&op=add', '', '', '', '', '', '', '')).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', array('{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $closed, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow));
}

function help() {
	global $prefix, $db, $admin_file, $user, $conf, $confu, $confh, $home, $op;
	$cwhere = catmids($conf['name'], 's.catid');
	$uid = intval($user[0]);
	$unum = user_news($user[3], $confh['num']);
	$ncat = getVar('get', 'cat', 'num');
	if (!$ncat && $op) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'closed') {
			$order = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = '".$uid."' AND s.time <= NOW() ".$cwhere." ORDER BY s.time DESC";
			$onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
			$ntitle = _CLOSED;
		} else {
			$order = "WHERE s.pid = '0' AND s.uid = '".$uid."' AND s.time <= NOW() ".$cwhere." ORDER BY s.counter DESC";
			$onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
			$ntitle = _POP;
		}
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		list($ctitle) = $db->sql_fetchrow($db->sql_query("SELECT title FROM ".$prefix."_categories WHERE id = '".$ncat."'"));
		$catid = array();
		$result = $db->sql_query("SELECT id FROM ".$prefix."_categories WHERE parentid = '".$ncat."'");
		while (list($caid) = $db->sql_fetchrow($result)) $catid[] = $caid;
		unset($result);
		if (isArray($catid)) {
			$caton = 1;
			array_unshift($catid, $ncat);
			$wcid = 'catid IN ('.implode(', ', $catid).')';
		} else {
			$caton = 0;
			$wcid = "catid = '".$ncat."'";
		}
		if ($op == 'closed') {
			$order = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = '".$uid."' AND (s.catid = '".$ncat."' OR c.parentid = '".$ncat."') AND s.time <= NOW() ".$cwhere." ORDER BY s.time DESC";
			$onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
			$ntitle = _CLOSED;
		} elseif ($op == 'pop') {
			$order = "WHERE s.pid = '0' AND s.uid = '".$uid."' AND (s.catid = '".$ncat."' OR c.parentid = '".$ncat."') AND s.time <= NOW() ".$cwhere." ORDER BY s.counter DESC";
			$onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
			$ntitle = _POP;
		} else {
			$order = "WHERE s.pid = '0' AND s.uid = '".$uid."' AND (s.catid = '".$ncat."' OR c.parentid = '".$ncat."') AND s.time <= NOW() ".$cwhere." ORDER BY s.time DESC";
			$onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
			$ntitle = _HELP;
		}
		$ntitle = $ctitle.' '.$conf['defis'].' '.$ntitle;
	} else {
		$caton = 1;
		$field = '';
		$order = "WHERE s.pid = '0' AND s.uid = '".$uid."' AND s.time <= NOW() ".$cwhere." ORDER BY s.time DESC";
		$onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
		$ntitle = _HELPINFO;
	}
	head();
	$cont = '';
	if (!$home) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', array('{%crumbs%}' => catlink($conf['name'], $ncat, $confh['defis'], _HELP)));
		if ($caton == 1) $cont .= setCategories($conf['name'], $confh['subcat'], $confh['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT s.sid, s.catid, s.title, s.time, s.hometext, s.comments, s.counter, c.title, c.description, c.img FROM ".$prefix."_help AS s LEFT JOIN ".$prefix."_categories AS c ON (s.catid = c.id) ".$order." LIMIT ".$offset.", ".$unum);
	if ($db->sql_numrows($result) > 0) {
		while (list($id, $cid, $stitle, $time, $hometext, $comm, $counter, $ctitle, $cdesc, $cimg) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $stitle, $hometext, $ctitle, $cdesc, $cimg));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
			$read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
			$date = ($confh['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</span>' : '';
			$reads = ($confh['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
			$comm = '<a href="'.$thref.'#'.$id.'" title="'._MESSAGES.'" class="sl_coms">'.$comm.'</a>';
			$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => bb_decode($hometext, $conf['name']), '{%read%}' => $read, '{%post%}' => '', '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => '', '{%admin%}' => '', '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => ''));
		}
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'sid', '_help', 'catid', $onum, $confh['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function liste() {
	global $prefix, $db, $conf, $confu, $confh, $user;
	$cwhere = catmids($conf['name'], 's.catid');
	$uid = intval($user[0]);
	$listnum = intval($confh['listnum']);
	$let = getVar('get', 'let', 'let');
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(s.title) LIKE BINARY '".$let."%' AND s.time <= NOW() AND s.pid = '0' AND s.uid = '".$uid."'";
	} else {
		$field = 'op=liste&';
		$order = "WHERE s.time <= NOW() AND s.pid = '0' AND s.uid = '".$uid."'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT s.sid, s.catid, s.title, s.time, s.status, c.title, c.description FROM ".$prefix."_help AS s LEFT JOIN ".$prefix."_categories AS c ON (s.catid = c.id) ".$order." ".$cwhere." ORDER BY s.time DESC LIMIT ".$offset.", ".$listnum);
	head();
	$cont = navigate(_LIST);
	if ($db->sql_numrows($result) > 0) {
		$letter = ($confh['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', array('{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _STATUS, '{%date%}' => _DATE));
		while (list($id, $cid, $title, $time, $status, $ctitle, $cdesc) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $title, '', $ctitle, $cdesc, ''));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, ''));
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$status = ($status) ? 0 : 1;
			$cont .= setTemplateBasic('liste-basic', array('{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => ad_status('', $status), '{%time%}' => format_time($time)));
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND pid = '0' AND uid = '".$uid."'" : "time <= NOW() AND pid = '0' AND uid = '".$uid."'";
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'sid', '_help', 'catid', $onum, $confh['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function view() {
	global $prefix, $db, $admin_file, $user, $conf, $confu, $confh;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$uid = intval($user[0]);
	$cwhere = catmids($conf['name'], 's.catid');
	$result = $db->sql_query("SELECT s.sid, s.pid, s.catid, s.uid, s.aid, s.title, s.time, s.hometext, s.field, s.counter, s.score, s.ratings, s.status, c.title, c.description, c.img, u.user_name FROM ".$prefix."_help AS s LEFT JOIN ".$prefix."_categories AS c ON (s.catid = c.id) LEFT JOIN ".$prefix."_users AS u ON (s.aid = u.user_id) WHERE (s.sid = '".$id."' OR s.pid = '".$id."') AND s.uid = '".$uid."' AND s.time <= NOW() ".$cwhere." ORDER BY s.time ASC");
	if ($db->sql_numrows($result) > 0) {
		$db->sql_query("UPDATE ".$prefix."_help SET counter = counter+1 WHERE sid = '".$id."'");
		head();
		$cont = navigate(_HELPINFO);
		$a = 0;
		while (list($hid, $pid, $cid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $status, $ctitle, $cdesc, $cimg, $user_name) = $db->sql_fetchrow($result)) {
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
			$title = ($title) ? search_color($title, $word) : _MESSAGE.': '.$a;
			$fields = fields_out($field, $conf['name']);
			$fields = ($fields) ? '<br><br>'.$fields : '';
			$text = $hometext.$fields;
			$post = ($user_name) ? user_info($user_name) : $confu['anonym'];
			$post = '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>';
			$date = ($confh['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</span>' : '';
			$rating = ($haid && $huid != $haid) ? ajax_rating(1, $hid, $conf['name'], $ratings, $score, '') : '';
			if (!$pid) {
				$reads = '<span title="'._READS.'" class="sl_views">'.$counter.'</span>';
				$cdesc = ($cdesc) ? $cdesc : $ctitle;
				$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
				$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
				$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
				$favorites = favorview($hid, $conf['name']);
				$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
			} else {
				$reads = $ctitle = $cimg = $favorites = $goback = '';
			}
			$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $hid, '{%title%}' => search_color($title, $word), '{%text%}' => search_color(bb_decode($text, $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => '', '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => ''));
			$a++;
		}
		$cont .= add_view($id);
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function add_view($id) {
	global $prefix, $db, $conf, $confh;
	if ((is_user() && $confh['add'] == 1)) {
		$result = $db->sql_query("SELECT catid, status FROM ".$prefix."_help WHERE sid = '".$id."'");
		list($hcatid, $status) = $db->sql_fetchrow($result);
		$cont = setTemplateBasic('open');
		$cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
		.'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1').'</td></tr>'
		.'<tr><td>'._HELPGLOS.'</td><td>'.radio_form($status, 'status').'</td></tr>'
		.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="catid" value="'.$hcatid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
		$cont .= setTemplateBasic('close');
		return $cont;
	}
}

function add() {
	global $prefix, $db, $conf, $confh, $confu, $stop;
	if ((is_user() && $confh['add'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$field = getVar('post', 'field', 'field');
		head();
		$cont = navigate(_ADD);
		if ($stop) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
		if ($hometext) $cont .= preview($title, $hometext, '', $field, $conf['name']);
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _HSUBMIT));
		$cont .= setTemplateBasic('open');
		$cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
		.'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._TITLE.'" required></td></tr>'
		.'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'catid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
		.'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '10', _TEXT, '1').'</td></tr>'
		.fields_in($field, $conf['name'])
		.'<tr><td colspan="2" class="sl_center">'.ad_save('', '', 'send').'</td></tr></table></form>';
		$cont .= setTemplateBasic('close');
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function send() {
	global $prefix, $db, $user, $conf, $confh, $stop;
	if ((is_user() && $confh['add'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$field = getVar('post', 'field', 'field');
		$pid = getVar('post', 'pid', 'num');
		$status = ($pid) ? getVar('post', 'status', 'num') : '0';
		$stop = array();
		if (!$title && !$pid) $stop[] = _CERROR;
		if (!$hometext && !$pid) $stop[] = _CERROR1;
		if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
			$postid = intval($user[0]);
			$db->sql_query("INSERT INTO ".$prefix."_help (sid, pid, catid, uid, aid, title, time, hometext, field, ip_sender, status) VALUES (NULL, '".$pid."', '".$cid."', '".$postid."', '".$postid."', '".$title."', NOW(), '".$hometext."', '".$field."', '".getIp()."', '0')");
			if ($pid) $db->sql_query("UPDATE ".$prefix."_help SET comments = comments+1, status = '".$status."' WHERE sid = '".$pid."'");
			$puname = (is_user()) ? $user[1] : '';
			addmail($confh['addmail'], $conf['name'], $puname, _HELP);
			head();
			echo navigate(_ADD).setTemplateWarning('warn', array('time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _HSUBTEXT));
			foot();
		} else {
			add();
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: help(); break;
	case 'liste': liste(); break;
	case 'view': view(); break;
	case 'add': add(); break;
	case 'send': send(); break;
}