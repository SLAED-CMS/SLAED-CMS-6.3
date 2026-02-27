<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}
get_lang($conf['name']);

function navigate($title, $cat='') {
	global $conf, $confj;
	$ncat = getVar('get', 'cat', 'num');
	$cpar = $ncat ? ['cat' => $ncat] : [];
	$home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._JOKES.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($confj['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($confj['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$add = ((is_user() && $confj['add'] == 1) || (!is_user() && $confj['addquest'] == 1)) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => '', '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function jokes() {
	global $db, $afile, $user, $conf, $confu, $confj, $home, $op;
	$cwhere = catmids($conf['name'], 'j.cat');
	$word = getVar('get', 'word', 'word');
	$unum = user_news($user[3] ?? 0, $confj['num']);
	$ncat = getVar('get', 'cat', 'num');
	$params = [];
	if (!$ncat && $op && $confj['rate']) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'best') {
			$orderby = '(j.rating/j.ratingtot) DESC';
			$ntitle = _BEST;
		} else {
			$orderby = '(j.ratingtot/(TO_DAYS(NOW()) - TO_DAYS(j.date))) DESC';
			$ntitle = _POP;
		}
		$order = "WHERE j.date <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$onum = "date <= NOW() AND status != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		$orderby = ($op) ? (($op == 'best') ? '(j.rating/j.ratingtot) DESC' : '(j.ratingtot/(TO_DAYS(NOW()) - TO_DAYS(j.date))) DESC') : 'j.date DESC';
		list($ctitle) = $db->sql_fetchrow($db->sql_query('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (j.cat = :ncat1 OR c.parentid = :ncat2) AND j.date <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$params = ['ncat1' => $ncat, 'ncat2' => $ncat];
		$catid = [];
		$result = $db->sql_query('SELECT id FROM '.PREFIX_DB.'_categories WHERE parentid = :ncat', ['ncat' => $ncat]);
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
		$field = '';
		$order = "WHERE j.date <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY j.date DESC';
		$onum = "date <= NOW() AND status != '0'";
		$ntitle = _JOKES;
	}
	setHead();
	$cont = '';
	if (!$home || ($home && $confj['homcat'])) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $confj['defis'], _JOKES)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $confj['subcat'], $confj['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->sql_query('SELECT j.jokeid, j.name, j.date, j.title, j.cat, j.joke, j.rating, j.ratingtot, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cat=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid=u.user_id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
	if ($db->sql_numrows($result) > 0) {
		while (list($id, $uname, $time, $jtitle, $cid, $joke, $rating, $ratingtot, $ctitle, $cdesc, $cimg, $user_name) = $db->sql_fetchrow($result)) {
			$title = '<a href="#'.$id.'" title="'.$jtitle.'">'.search_color($jtitle, $word).'</a> '.new_graphic($time);
			$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : _ANONYM);
			$post = '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>';
			$date = ($confj['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_icat"><img src="'.img_find('categories/'.$cimg).'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$rating = ajax_rating(1, $id, $conf['name'], $ratingtot, $rating, '');
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=jokes_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=jokes_delete&amp;id='.$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$jtitle."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>') : '';
			$cont .= tpl_func('basic', $cid, $cimg, $ctitle, $id, $title, search_color(bb_decode($joke, $conf['name']), $word), '', $post, $date, '', '', '', $rating, $admin, '', '', '');
		}
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'jokeid', '_jokes', 'cat', $onum, $confj['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function add() {
	global $db, $user, $conf, $confu, $confj, $stop;
	if ($confj['add'] == '1') {
		$title = save_text(getVar('post', 'title', 'text'), 1);
		$cid = getVar('post', 'cid', 'num');
		$joke = save_text(getVar('post', 'joke', 'text'));
		$postname = text_filter(substr(getVar('post', 'postname', 'name'), 0, 25));
		
		setHead();
		$cont = navigate(_ADD);
		if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
		if ($joke) $cont .= preview($title, $joke, '', '', 'all');
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _ADD_JNOTE]);
		$cont .= setTemplateBasic('open');
		$cont .= '<form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">';
		if (is_user()) {
			$cont .= '<tr><td>'._YOURNAME.':</td><td>'.text_filter(substr($user[1], 0, 25)).'</td></tr>';
		} else {
			$postname = ($postname) ? $postname : _ANONYM;
			$cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
		}
		$cont .= '<tr><td>'._JTITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._JTITLE.'" required></td></tr>'
		.'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
		.'<tr><td>'._JOKE.':</td><td>'.textarea('1', 'joke', $joke, $conf['name'], '10', _JOKE, '1').'</td></tr>'
		.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
		$cont .= setTemplateBasic('close');
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function send() {
	global $db, $user, $conf, $confj, $stop;
	if ($confj['add'] == '1') {
		$postname = text_filter(substr(getVar('post', 'postname', 'name'), 0, 25));
		$title = save_text(getVar('post', 'title', 'text'), 1);
		$cid = getVar('post', 'cid', 'num');
		$joke = save_text(getVar('post', 'joke', 'text'));
		$stop = [];
		if (!$title) $stop[] = _CERROR;
		if (!$joke) $stop[] = _CERROR1;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if ($db->sql_numrows($db->sql_query('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
		if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
			$postid = (is_user()) ? intval($user[0]) : '';
			$uname = (!is_user()) ? $postname : '';
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_jokes (jokeid, uid, name, date, title, cat, joke, ip_sender, status) VALUES (NULL, :postid, :uname, NOW(), :title, :cid, :joke, :ip, \'0\')', ['postid' => $postid, 'uname' => $uname, 'title' => $title, 'cid' => $cid, 'joke' => $joke, 'ip' => getIp()]);
			update_points(19);
			$puname = (is_user()) ? $user[1] : $postname;
			addmail($confj['addmail'], $conf['name'], $puname, _JOKES);
			setHead(['title' => _JOKES.' '._ADD, 'desc' => _UPLOADFINISHJ]);
			echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _UPLOADFINISHJ]);
			setFoot();
		} else {
			add();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

switch($op) {
	default:
	jokes();
	break;
	
	case 'add':
	add();
	break;
	
	case 'send':
	send();
	break;
}
