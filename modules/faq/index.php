<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function faq(): void {
	global $db, $afile, $user, $conf, $home, $op;
	$cwhere = catmids($conf['name'], 's.cid');
	$unum = getUserNews($conf['faq']['num']);
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
		[$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$params = ['ncat1' => $ncat, 'ncat2' => $ncat];
		$cids = [];
		$result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parent = :ncat', ['ncat' => $ncat]);
		while ([$caid] = $db->getSqlRow($result)) $cids[] = $caid;
		unset($result);
		if (isArray($cids)) {
			$caton = 1;
			array_unshift($cids, $ncat);
			$wcid = 'cid IN ('.implode(', ', $cids).')';
		} else {
			$caton = 0;
			$wcid = "cid = '".$ncat."'";
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
		$cont .= setModuleNavi(['title' => $ntitle, 'htitle' => _FAQ]);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['faq']['defis'], _FAQ)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], $ncat);
	}
	if ($ncat) {
		$cont .= '<table class="sl_table_faq">';
		$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_faq WHERE cid = :ncat AND time <= NOW() AND status != \'0\' ORDER BY '.$orderbyf, ['ncat' => $ncat]);
		while ([$fid, $ftitle] = $db->getSqlRow($result)) $cont .= '<tr><td><a href="#'.$fid.'" title="'.$ftitle.'" class="sl_faq">'.filterTextHighlight($ftitle, $word).'</a></td></tr>';
		$cont .= '</table>';
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$limit = (!$ncat) ? 'LIMIT '.$offset.', '.$unum : '';
	$result = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, s.body, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) '.$order.' '.$limit, $params);
	if ($db->getSqlRowCount($result) > 0) {
		while ([$id, $cid, $uname, $stitle, $time, $hometext, $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
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
			$cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
		}
		if (!$ncat) $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_faq', 'cid', $onum, $conf['faq']['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf;
	$cwhere = catmids($conf['name'], 's.cid');
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
	$result = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, c.title, c.intro, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) '.$order.' '.$cwhere.' ORDER BY time DESC LIMIT '.$offset.', '.$listnum, $params);
	setHead(['title' => _LIST]);
	$cont = setModuleNavi(['title' => _LIST, 'htitle' => _FAQ]);
	if ($db->getSqlRowCount($result) > 0) {
		$letter = ($conf['faq']['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _QUESTION, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE]);
		while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cadesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cadesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
			$cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $post, '{%time%}' => format_time($time)]);
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
		$params = ($let) ? ['let' => $let.'%'] : [];
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_faq', 'cid', $onum, $conf['faq']['nump'], $params);
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
	$cwhere = catmids($conf['name'], 's.cid');
	$result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.body, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :id AND s.time <= NOW() AND s.status != \'0\' '.$cwhere, ['id' => $id]);
	if ($db->getSqlRowCount($result) == 1) {
		$db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET counter = counter+1 WHERE id = :id', ['id' => $id]);
		[$cid, $uname, $title, $time, $hometext, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
		$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
		$seotitle = $title;
		$seoctitle = $ctitle;
		$seodesc = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), 160);
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
		$cont = setModuleNavi(['title' => _FAQ]);
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
		$favorites = getFavorBtn($id, $conf['name']);
		$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
		$cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => true], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => filterTextHighlight($title, $word), '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($conpag[$arrayelement], $conf['name'], false), $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => '']);
		$cont .= setPageNumbers('pagenum', $conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['faq']['nump'], (int)$pag, '#'.$id);
		if ($conf['faq']['link']) {
			$limit = intval($conf['faq']['linknum']);
			[$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_faq WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->getSqlQuery('SELECT id, title, time, body FROM '.PREFIX_DB.'_faq WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
				$cont .= setTemplateBasic('assoc-open', ['{%title%}' => _CATASSOC]);
				while([$aid, $title, $time, $hometext] = $db->getSqlRow($result)) {
					$date = ($conf['faq']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
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
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		setHead(['title' => _ADD]);
		$cont = setModuleNavi(['title' => _ADD, 'htitle' => _FAQ]);
		if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
		if ($hometext) $cont .= preview($title, $hometext, '', '', $conf['name']);
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SUBMIT.' '._PAGENOTE]);
		$cont .= '<form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">';
		if (is_user()) {
			$cont .= '<tr><td>'._YOURNAME.':</td><td>'.filterText(substr($user[1], 0, 25)).'</td></tr>';
		} else {
			$postname = ($postname) ? $postname : _ANONYM;
			$cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
		}
		$cont .= '<tr><td>'._QUESTION.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._QUESTION.'" required></td></tr>'
		.'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'catid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
		.'<tr><td>'._ANSWER.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '10', _ANSWER, '1').'</td></tr>'
		.'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
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
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		$stop = [];
		if (!$hometext) $stop[] = _CERROR1;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
			$postid = (is_user()) ? intval($user[0]) : '';
			$uname = (!is_user()) ? $postname : '';
			$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_faq (id, cid, uid, name, title, time, body, ip, status) VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :body, :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'body' => $hometext, 'ip' => getIp()]);
			update_points(6);
			$puname = (is_user()) ? $user[1] : $postname;
			addAdminMail($conf['faq']['addmail'], $conf['name'], $puname, _FAQ);
			setHead(['title' => _ADD]);
			echo setModuleNavi(['title' => _ADD, 'htitle' => _FAQ]).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _SUBTEXT]);
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
