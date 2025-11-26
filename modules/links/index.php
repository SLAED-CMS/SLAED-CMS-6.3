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
include('config/config_links.php');

function navigate($title, $cat='') {
	global $conf, $confl;
	$ncat = getVar('get', 'cat', 'num');
	$ncat = ($ncat) ? '&cat='.$ncat : '';
	$home = '<a href="'.getHref(array('name='.$conf['name'], '', '', '', '', '', '', '')).'" title="'._LINKS.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($confl['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=best', '', '', '', '', '', '', '')).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($confl['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=pop', '', '', '', '', '', '', '')).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$liste = '<a href="'.getHref(array('name='.$conf['name'].'&op=liste', '', '', '', '', '', '', '')).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
	$add = ((is_user() && $confl['add'] == 1) || (!is_user() && $confl['addquest'] == 1)) ? '<a href="'.getHref(array('name='.$conf['name'].'&op=add', '', '', '', '', '', '', '')).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', array('{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow));
}

function links() {
	global $prefix, $db, $admin_file, $user, $conf, $confu, $confl, $home, $op;
	$cwhere = catmids($conf['name'], 'f.cid');
	$unum = user_news($user[3], $confl['num']);
	$ncat = getVar('get', 'cat', 'num');
	if (!$ncat && $op && $confl['rate']) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'best') {
			$orderby = '(f.totalvotes/f.votes) DESC';
			$ntitle = _BEST;
		} else {
			$orderby = '(f.hits/(TO_DAYS(NOW()) - TO_DAYS(f.date))) DESC';
			$ntitle = _POP;
		}
		$order = "WHERE f.date <= NOW() AND f.status != '0' ".$cwhere." ORDER BY ".$orderby;
		$onum = "date <= NOW() AND status != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		$orderby = ($op) ? (($op == 'best') ? '(f.totalvotes/f.votes) DESC' : '(f.hits/(TO_DAYS(NOW()) - TO_DAYS(f.date))) DESC') : 'f.date DESC';
		list($ctitle) = $db->sql_fetchrow($db->sql_query("SELECT title FROM ".$prefix."_categories WHERE id = '".$ncat."'"));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (f.cid = '".$ncat."' OR c.parentid = '".$ncat."') AND f.date <= NOW() AND f.status != '0' ".$cwhere." ORDER BY ".$orderby;
		$catid = array();
		$result = $db->sql_query("SELECT id FROM ".$prefix."_categories WHERE parentid = '".$ncat."'");
		while (list($caid) = $db->sql_fetchrow($result)) $catid[] = $caid;
		unset($result);
		if (isArray($catid)) {
			$caton = 1;
			array_unshift($catid, $ncat);
			$wcid = 'cid IN ('.implode(', ', $catid).')';
		} else {
			$caton = 0;
			$wcid = "cid = '".$ncat."'";
		}
		$onum = $wcid." AND date <= NOW() AND status != '0'";
	} else {
		$caton = 1;
		$field = '';
		$hwhere = ($home) ? "AND f.ihome = '1'" : "";
		$hnwhere = ($home) ? "AND ihome = '1'" : "";
		$order = "WHERE f.date <= NOW() AND f.status != '0' ".$hwhere." ".$cwhere." ORDER BY f.date DESC";
		$onum = "date <= NOW() AND status != '0' ".$hnwhere;
		$ntitle = _LINKS;
	}
	head();
	$cont = '';
	if (!$home || ($home && $confl['homcat'])) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', array('{%crumbs%}' => catlink($conf['name'], $ncat, $confl['defis'], _LINKS)));
		if ($caton == 1) $cont .= setCategories($conf['name'], $confl['subcat'], $confl['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT f.lid, f.cid, f.name, f.title, f.description, f.bodytext, f.date, f.counter, f.acomm, f.votes, f.totalvotes, f.totalcomments, f.hits, c.title, c.description, c.img, u.user_name FROM ".$prefix."_links AS f LEFT JOIN ".$prefix."_categories AS c ON (f.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) ".$order." LIMIT ".$offset.", ".$unum);
	if ($db->sql_numrows($result) > 0) {
		while (list($id, $cid, $uname, $stitle, $description, $bodytext, $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $user_name) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $stitle, $description.$bodytext, $ctitle, $cdesc, $cimg));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
			$read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
			$post = ($confl['autor']) ? (($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym'])) : '';
			$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
			$date = ($confl['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</span>' : '';
			$reads = ($confl['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
			$hits = ($confl['hits']) ? '<span title="'._LINKHITS.'" class="sl_down">'.$hits.'</span>' : '';
			$comm = ($acomm) ? '<a href="'.$thref.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$comm.'</a>' : '';
			$rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$admin_file.'.php?op=links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=links_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
			$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => bb_decode($description, $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $hits, '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => ''));
		}
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'lid', '_links', 'cid', $onum, $confl['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function liste() {
	global $prefix, $db, $conf, $confu, $confl;
	$cwhere = catmids($conf['name'], 'f.cid');
	$listnum = intval($confl['listnum']);
	$let = getVar('get', 'let', 'let');
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(f.title) LIKE BINARY '".$let."%' AND f.date <= NOW() AND f.status != '0'";
	} else {
		$field = 'op=liste&';
		$order = "WHERE f.date <= NOW() AND f.status != '0'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT f.lid, f.cid, f.name, f.title, f.date, c.title, c.description, u.user_name FROM ".$prefix."_links AS f LEFT JOIN ".$prefix."_categories AS c ON (f.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) ".$order." ".$cwhere." ORDER BY date DESC LIMIT ".$offset.", ".$listnum);
	head();
	$cont = navigate(_LIST);
	if ($db->sql_numrows($result) > 0) {
		$letter = ($confl['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', array('{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE));
		while (list($id, $cid, $uname, $title, $time, $ctitle, $cdesc, $user_name) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $title, '', $ctitle, $cdesc, ''));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, ''));
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
			$cont .= setTemplateBasic('liste-basic', array('{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $post, '{%time%}' => format_time($time)));
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY '".$let."%' AND date <= NOW() AND status != '0'" : "date <= NOW() AND status != '0'";
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'lid', '_links', 'cid', $onum, $confl['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function view() {
	global $prefix, $db, $admin_file, $conf, $confu, $confl;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$cwhere = catmids($conf['name'], 'f.cid');
	$result = $db->sql_query("SELECT f.cid, f.name, f.title, f.url, f.description, f.bodytext, f.date, f.email, f.counter, f.acomm, f.votes, f.totalvotes, f.hits, f.status, c.title, c.description, c.img, u.user_name FROM ".$prefix."_links AS f LEFT JOIN ".$prefix."_categories AS c ON (f.cid = c.id) LEFT JOIN ".$prefix."_users AS u ON (f.uid = u.user_id) WHERE lid = '".$id."' AND date <= NOW() AND f.status != '0' ".$cwhere);
	if ($db->sql_numrows($result) == 1) {
		$db->sql_query("UPDATE ".$prefix."_links SET counter = counter+1 WHERE lid = '".$id."'");
		list($cid, $uname, $title, $authorurl, $description, $bodytext, $date, $aemail, $counter, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $user_name) = $db->sql_fetchrow($result);
		$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
		head();
		$cont = navigate(_LINKS, $confl['viewcat']);
		if ($cid) $cont .= setTemplateBasic('cat-navi', array('{%crumbs%}' => catlink($conf['name'], $cid, $confl['defis'], _LINKS)));
		if ($confl['viewcat']) $cont .= setCategories($conf['name'], $confl['subcat'], $confl['catdesc'], 0);
		$text = ($bodytext) ? $description.'<br><br>'.$bodytext : $description;
		$cdesc = ($cdesc) ? $cdesc : $ctitle;
		$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
		$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
		$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
		$post = ($confl['autor']) ? (($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym'])) : '';
		$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
		$date = ($confl['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</span>' : '';
		$reads = ($confl['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
		$hits = ($confl['hits']) ? '<span title="'._LINKHITS.'" class="sl_down">'.$hits.'</span>' : '';
		$rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
		$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$admin_file.'.php?op=links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=links_delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
		$favorites = favorview($id, $conf['name']);
		$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
		if (is_user() || $confl['links'] == '1') {
			$download = '<form action="index.php?name='.$conf['name'].'" method="post" style="display: inline">'
			.'<input type="hidden" name="id" value="'.$id.'">'
			.'<input type="hidden" name="op" value="loading">'
			.'<input type="submit" OnClick="javascript:window.open(\''.$authorurl.'\')" value="'._DOWNLLINK.'" class="sl_but_green"></form>';
		}
		$broken = ($confl['broc'] == 1 && $status != '2') ? '<a OnClick="javascript:window.location.assign(\'index.php?name='.$conf['name'].'&amp;op=broken&amp;id='.$id.'\');" title="'._BROCLINK.'" class="sl_but_blue">'._COMPLAINT.'</a>' : '';
		$email = ($aemail) ? _AUEMAIL.': '.anti_spam($aemail) : '';
		$home = ($authorurl) ? _SITE.': '.domain($authorurl) : '';
		$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => search_color($title, $word), '{%text%}' => search_color(bb_decode($text, $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $hits, '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => '', '{%size%}' => '', '{%version%}' => '', '{%download%}' => $download, '{%broken%}' => $broken, '{%email%}' => $email, '{%home%}' => $home));
		if ($confl['link']) {
			$limit = intval($confl['linknum']);
			list($count) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(lid) FROM ".$prefix."_links WHERE cid = '".$cid."' AND lid != '".$id."' AND date <= NOW() AND status != '0'"));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->sql_query("SELECT lid, title, description, bodytext, date FROM ".$prefix."_links WHERE cid = '".$cid."' AND lid != '".$id."' AND date <= NOW() AND status != '0' ORDER BY date DESC LIMIT ".$random.", ".$limit);
				$cont .= setTemplateBasic('assoc-open', array('{%title%}' => _CATASSOC));
				while(list($aid, $title, $hometext, $bodytext, $time) = $db->sql_fetchrow($result)) {
					$date = ($confl['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</span>' : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags(bb_decode($hometext, $conf['name']))), ENT_QUOTES), 80);
					$img = getImgText($hometext);
					$img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
					$cont .= setTemplateBasic('assoc-basic', array('{%href%}' => getHref(array('name='.$conf['name'].'&op=view&id='.$aid, $time, '', $title, $hometext.$bodytext, '', '', '')), '{%title%}' => $title, '{%date%}' => $date, '{%text%}' => $text, '{%img%}' => $img));
				}
				$cont .= setTemplateBasic('assoc-close');
			}
		}
		if ($acomm) $cont .= setComShow($id, $acomm);
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function add() {
	global $db, $prefix, $user, $conf, $confu, $confl, $stop;
	if ((is_user() && $confl['add'] == 1) || (!is_user() && $confl['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'cid', 'num');
		$description = getVar('post', 'description', 'text');
		$bodytext = getVar('post', 'bodytext', 'text');
		$postname = getVar('post', 'postname', 'name');
		if (is_user()) {
			$userinfo = getusrinfo();
			$authormail = getVar('post', 'authormail', 'text', $userinfo['user_email']);
			$authorurl = getVar('post', 'authorurl', 'url', $userinfo['user_website']);
		} else {
			$authormail = getVar('post', 'authormail', 'text');
			$authorurl = getVar('post', 'authorurl', 'url', 'http://');
		}
		head();
		$cont = navigate(_ADD);
		if ($stop) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
		if ($description) $cont .= preview($title, $description, $bodytext, '', $conf['name']);
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _ADDFNOTE));
		$cont .= setTemplateBasic('open');
		$cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
		if (is_user()) {
			$cont .= '<tr><td>'._YOURNAME.':</td><td>'.text_filter(substr($user[1], 0, 25)).'</td></tr>';
		} else {
			$postname = ($postname) ? $postname : $confu['anonym'];
			$cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
		}
		$cont .= '<tr><td>'._AUEMAIL.':</td><td><input type="email" name="authormail" value="'.$authormail.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._AUEMAIL.'" required></td></tr>'
		.'<tr><td>'._SITENAME.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._SITENAME.'" required></td></tr>'
		.'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
		.'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, $conf['name'], '5', _TEXT, '1').'</td></tr>'
		.'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, $conf['name'], '15', _ENDTEXT, '0').'</td></tr>'
		.'<tr><td>'._URL.':</td><td><input type="url" name="authorurl" value="'.$authorurl.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._URL.'" required></td></tr>'
		.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
		$cont .= setTemplateBasic('close');
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function send() {
	global $prefix, $db, $user, $conf, $confl, $stop;
	if ((is_user() && $confl['add'] == 1) || (!is_user() && $confl['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'cid', 'num');
		$description = getVar('post', 'description', 'text');
		$bodytext = getVar('post', 'bodytext', 'text');
		$postname = getVar('post', 'postname', 'name');
		$authormail = getVar('post', 'authormail', 'text');
		$authorurl = getVar('post', 'authorurl', 'url');
		$stop = array();
		if (!$title) $stop[] = _CERROR10;
		if (!$description) $stop[] = _CERROR1;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (!$authorurl) $stop[] = _CERROR4;
		checkemail($authormail);
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if ($db->sql_numrows($db->sql_query("SELECT url FROM ".$prefix."_links WHERE url = '".$authorurl."'")) > 0) $stop[] = _LINKEXIST;
		if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
			$postid = (is_user()) ? intval($user[0]) : '';
			$uname = (!is_user()) ? $postname : '';
			$db->sql_query("INSERT INTO ".$prefix."_links (lid, cid, uid, name, title, description, bodytext, url, date, email, ip_sender, status) VALUES (NULL, '".$cid."', '".$postid."', '".$uname."', '".$title."', '".$description."', '".$bodytext."', '".$authorurl."', NOW(), '".$authormail."', '".getIp()."', '0')");
			update_points(21);
			$puname = (is_user()) ? $user[1] : $postname;
			addmail($confl['addmail'], $conf['name'], $puname, _LINKS);
			head();
			echo navigate(_ADD).setTemplateWarning('warn', array('time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _UPLOADFINISHL));
			foot();
		} else {
			add();
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function broken() {
	global $prefix, $db, $conf, $confl;
	$id = getVar('get', 'id', 'num');
	if ($confl['broc'] == '1' && $id) {
		$db->sql_query("UPDATE ".$prefix."_links SET status = '2' WHERE lid = '".$id."' AND status != '0'");
		head();
		echo navigate(_BROCLINK).setTemplateWarning('warn', array('time' => '5', 'url' => '?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 'id' => 'info', 'text' => _BROCNOTEL));
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

function loading() {
	global $prefix, $db, $conf, $confl;
	$id = getVar('post', 'id', 'num');
	if (($id && is_user()) || ($id && $confl['links'] == '1')) {
		$db->sql_query("UPDATE ".$prefix."_links SET hits = hits+1 WHERE lid = '".$id."'");
		list($title, $url) = $db->sql_fetchrow($db->sql_query("SELECT title, url FROM ".$prefix."_links WHERE lid = '".$id."'"));
		update_points(23);
		$info = sprintf(_NOTELINKLOAD, $title, domain($url));
		head();
		$cont = navigate(_LINKS);
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => $info));
		$cont .= setNaviLower($conf['name']);
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: links(); break;
	case 'liste': liste(); break;
	case 'view': view(); break;
	case 'add': add(); break;
	case 'send': send(); break;
	case 'broken': broken(); break;
	case 'loading': loading(); break;
}
?>