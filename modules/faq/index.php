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

function navigate(string $title, string|int $cat=''): string {
	global $conf;
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$cpar = $ncat ? ['cat' => $ncat] : [];
	$home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._FAQ.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($conf['faq']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($conf['faq']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
	$add = ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function faq(): void {
	global $db, $afile, $user, $conf, $home, $op;
	$cwhere = catmids($conf['name'], 's.catid');
	$unum = user_news($user[3] ?? 0, $conf['faq']['num']);
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$word = getVar('get', 'word', 'word');
	$params = [];
	if (!$ncat && $op && $conf['faq']['rate']) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'best') {
			$orderby = 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC';
			$ntitle = _BEST;
		} else {
			$orderby = 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC';
			$ntitle = _POP;
		}
		$order = "WHERE s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$onum = "time <= NOW() AND status != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		$orderby = ($op) ? (($op == 'best') ? 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC' : 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC') : 's.time DESC';
		$orderbyf = ($op) ? (($op == 'best') ? 'IFNULL((score/NULLIF(ratings,0)),0) DESC' : 'IFNULL((counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(time)),0)),0) DESC') : 'time DESC';
		[$ctitle] = $db->sql_fetchrow($db->sql_query('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (s.catid = :ncat1 OR c.parentid = :ncat2) AND s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$params = ['ncat1' => $ncat, 'ncat2' => $ncat];
		$catid = [];
		$result = $db->sql_query('SELECT id FROM '.PREFIX_DB.'_categories WHERE parentid = :ncat', ['ncat' => $ncat]);
		while ([$caid] = $db->sql_fetchrow($result)) $catid[] = $caid;
		unset($result);
		if (isArray($catid)) {
			$caton = 1;
			array_unshift($catid, $ncat);
			$wcid = 'catid IN ('.implode(', ', $catid).')';
		} else {
			$caton = 0;
			$wcid = "catid = '".$ncat."'";
		}
		$onum = $wcid." AND time <= NOW() AND status != '0'";
	} else {
		$caton = 1;
		$field = '';
		$hwhere = ($home) ? "AND s.ihome = '1'" : '';
		$hnwhere = ($home) ? "AND ihome = '1'" : '';
		$order = "WHERE s.time <= NOW() AND s.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY s.time DESC';
		$onum = "time <= NOW() AND status != '0' ".$hnwhere;
		$ntitle = _FAQ;
	}
	setHead(['title' => $ntitle]);
	$cont = '';
	if (!$home || ($home && $conf['faq']['homcat'])) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['faq']['defis'], _FAQ)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], $ncat);
	}
	if ($ncat) {
		$cont .= setTemplateBasic('open');
		$cont .= '<table class="sl_table_faq">';
		$result = $db->sql_query('SELECT fid, title FROM '.PREFIX_DB.'_faq WHERE catid = :ncat AND time <= NOW() AND status != \'0\' ORDER BY '.$orderbyf, ['ncat' => $ncat]);
		while ([$fid, $ftitle] = $db->sql_fetchrow($result)) $cont .= '<tr><td><a href="#'.$fid.'" title="'.$ftitle.'" class="sl_faq">'.search_color($ftitle, $word).'</a></td></tr>';
		$cont .= '</table>';
		$cont .= setTemplateBasic('close');
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$limit = (!$ncat) ? 'LIMIT '.$offset.', '.$unum : '';
	$result = $db->sql_query('SELECT s.fid, s.catid, s.name, s.title, s.time, s.hometext, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) '.$order.' '.$limit, $params);
	if ($db->sql_numrows($result) > 0) {
		while ([$id, $cid, $uname, $stitle, $time, $hometext, $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->sql_fetchrow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
			$read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
			$post = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
			$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
			$date = ($conf['faq']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
			$reads = ($conf['faq']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
			$comm = ($acomm) ? '<a href="'.$thref.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$comm.'</a>' : '';
			$rating = ajax_rating(0, $id, $conf['name'], $ratings, $score, '');
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=faq_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=faq_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
			$cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => bb_decode($hometext, $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
		}
		if (!$ncat) $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'fid', '_faq', 'catid', $onum, $conf['faq']['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf;
	$cwhere = catmids($conf['name'], 's.catid');
	$listnum = intval($conf['faq']['listnum']);
	$let = getVar('get', 'let', 'let');
	$params = [];
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(s.title) LIKE BINARY :let AND s.time <= NOW() AND s.status != '0'";
		$params['let'] = $let.'%';
	} else {
		$field = 'op=liste&';
		$order = "WHERE s.time <= NOW() AND s.status != '0'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->sql_query('SELECT s.fid, s.catid, s.name, s.title, s.time, c.title, c.description, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) '.$order.' '.$cwhere.' ORDER BY time DESC LIMIT '.$offset.', '.$listnum, $params);
	setHead(['title' => _LIST]);
	$cont = navigate(_LIST);
	if ($db->sql_numrows($result) > 0) {
		$letter = ($conf['faq']['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _QUESTION, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE]);
		while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->sql_fetchrow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cadesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cadesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
			$cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $post, '{%time%}' => format_time($time)]);
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'fid', '_faq', 'catid', $onum, $conf['faq']['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $afile, $conf;
	$id = getVar('get', 'id', 'num');
	$num = getVar('get', 'num', 'num', '1');
	$pag = $num;
	$word = getVar('get', 'word', 'word');
	$cwhere = catmids($conf['name'], 's.catid');
	$result = $db->sql_query('SELECT s.catid, s.name, s.title, s.time, s.hometext, s.counter, s.acomm, s.score, s.ratings, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) WHERE s.fid = :id AND s.time <= NOW() AND s.status != \'0\' '.$cwhere, ['id' => $id]);
	if ($db->sql_numrows($result) == 1) {
		$db->sql_query('UPDATE '.PREFIX_DB.'_faq SET counter = counter+1 WHERE fid = :id', ['id' => $id]);
		[$cid, $uname, $title, $time, $hometext, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->sql_fetchrow($result);
		$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
		$seotitle = $title;
		$seoctitle = $ctitle;
		$seodesc = cutstr(trim(strip_tags(bb_decode($hometext, $conf['name']))), 160);
		$seoimg = getImgText($hometext, '', false);
		$seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
		$seotime = $time;
		$seoauthor = $nick ?: ($uname ?: $conf['sitename']);
		setHead([
			'title' => $seotitle,
			'ctitle' => $seoctitle,
			'desc' => $seodesc,
			'img' => $seoimg,
			'time' => $seotime,
			'author' => $seoauthor,
		]);
		$cont = navigate(_FAQ, $conf['faq']['viewcat']);
		if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $conf['faq']['defis'], _FAQ)]);
		if ($conf['faq']['viewcat']) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], 0);
		$conpag = explode('[pagebreak]', $hometext);
		$pageno = count($conpag);
		if ($pag > $pageno) $pag = $pageno;
		$arrayelement = (int)$pag;
		$arrayelement--;
		$cdesc = ($cdesc) ? $cdesc : $ctitle;
		$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
		$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
		$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
		$post = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
		$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
		$date = ($conf['faq']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
		$reads = ($conf['faq']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
		$rating = ajax_rating(1, $id, $conf['name'], $ratings, $score, '');
		$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=faq_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=faq_delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
		$favorites = favorview($id, $conf['name']);
		$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
		$cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => true], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => search_color($title, $word), '{%text%}' => search_color(bb_decode($conpag[$arrayelement], $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => '']);
		$cont .= setPageNumbers('pagenum', $conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['faq']['nump'], (int)$pag, '#'.$id);
		if ($conf['faq']['link']) {
			$limit = intval($conf['faq']['linknum']);
			[$count] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(fid) FROM '.PREFIX_DB.'_faq WHERE catid = :cid AND fid != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->sql_query('SELECT fid, title, time, hometext FROM '.PREFIX_DB.'_faq WHERE catid = :cid AND fid != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
				$cont .= setTemplateBasic('assoc-open', ['{%title%}' => _CATASSOC]);
				while([$aid, $title, $time, $hometext] = $db->sql_fetchrow($result)) {
					$date = ($conf['faq']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags(bb_decode($hometext, $conf['name']))), ENT_QUOTES), 80);
					$img = getImgText($hometext);
					$img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
					$cont .= setTemplateBasic('assoc-basic', ['{%href%}' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), '{%title%}' => $title, '{%date%}' => $date, '{%text%}' => $text, '{%img%}' => $img]);
				}
				$cont .= setTemplateBasic('assoc-close');
			}
		}
		if ($acomm) $cont .= setComShow($id, $acomm);
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function add(): void {
	global $db, $user, $conf, $stop;
	if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$catid = getVar('post', 'catid', 'num');
		$cid = $catid;
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		setHead(['title' => _ADD]);
		$cont = navigate(_ADD);
		if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
		if ($hometext) $cont .= preview($title, $hometext, '', '', $conf['name']);
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SUBMIT.' '._PAGENOTE]);
		$cont .= setTemplateBasic('open');
		$cont .= '<form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">';
		if (is_user()) {
			$cont .= '<tr><td>'._YOURNAME.':</td><td>'.text_filter(substr($user[1], 0, 25)).'</td></tr>';
		} else {
			$postname = ($postname) ? $postname : _ANONYM;
			$cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
		}
		$cont .= '<tr><td>'._QUESTION.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._QUESTION.'" required></td></tr>'
		.'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'catid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
		.'<tr><td>'._ANSWER.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '10', _ANSWER, '1').'</td></tr>'
		.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
		$cont .= setTemplateBasic('close');
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function send(): void {
	global $db, $user, $conf, $stop;
	if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$catid = getVar('post', 'catid', 'num');
		$cid = $catid;
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		$stop = [];
		if (!$hometext) $stop[] = _CERROR1;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
			$postid = (is_user()) ? intval($user[0]) : '';
			$uname = (!is_user()) ? $postname : '';
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_faq (fid, catid, uid, name, title, time, hometext, ip_sender, status) VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :hometext, :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'hometext' => $hometext, 'ip' => getIp()]);
			update_points(6);
			$puname = (is_user()) ? $user[1] : $postname;
			addmail($conf['faq']['addmail'], $conf['name'], $puname, _FAQ);
			setHead(['title' => _ADD]);
			echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _SUBTEXT]);
			setFoot();
		} else {
			add();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: faq(); break;
	case 'liste': liste(); break;
	case 'view': view(); break;
	case 'add': add(); break;
	case 'send': send(); break;
}

