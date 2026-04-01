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
	global $db, $afile, $user, $conf, $home, $op, $tpl;
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
		if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $ncat, $conf['faq']['defis'], _FAQ)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], $ncat);
	}
	if ($ncat) {
		$cont .= $tpl->getHtmlFrag('faq-quicklinks-open');
		$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_faq WHERE cid = :ncat AND time <= NOW() AND status != \'0\' ORDER BY '.$orderbyf, ['ncat' => $ncat]);
		while ([$fid, $ftitle] = $db->getSqlRow($result)) $cont .= $tpl->getHtmlFrag('faq-quicklinks-row', ['id' => $fid, 'title' => $ftitle, 'label' => filterTextHighlight($ftitle, $word)]);
		$cont .= $tpl->getHtmlFrag('faq-quicklinks-close');
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
			$cdesc = $cdesc ?: $ctitle;
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$post = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
			$date = ($conf['faq']['date']) ? format_time($time) : '';
			$rating = getRatingAsync(0, $id, $conf['name'], $ratings, $score, '');
			$ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$stitle.'&quot;?');
			$cont .= getTplContentCard(['id' => $id, 'title_href' => $thref, 'title_attr' => $stitle, 'title_text' => $stitle, 'title_new' => new_graphic($time), 'category_href' => $ctitle ? $chref : '', 'category_attr' => $cdesc, 'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '', 'category_img' => $cimg, 'text' => filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']), 'read_href' => $thref, 'read_text' => _READMORE, 'post_text' => $post, 'post_label' => _POSTEDBY, 'date_text' => $date, 'date_iso' => ($date) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'reads_text' => ($conf['faq']['read']) ? $counter : '', 'reads_label' => _READS, 'hits' => '', 'comm_href' => ($acomm) ? $thref.'#comm' : '', 'comm_text' => ($acomm) ? $comm : '', 'comm_label' => _COMMENTS, 'rating' => $rating, 'favorites' => '', 'voting' => '', 'editor' => _EDITOR, 'edit_href' => $afile.'.php?op=faq_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=faq_delete&amp;id='.$id.'&amp;refer=1', 'delete_text' => _ONDELETE, 'delete_ask' => $ask, 'back_title' => '', 'back_text' => '', 'is_moder' => is_moder($conf['name'])]);
		}
		if (!$ncat) $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_faq', 'cid', $onum, $conf['faq']['nump']);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf, $tpl;
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
		$cont .= $tpl->getHtmlFrag('liste-wrap', ['open' => true, 'letter' => $letter, 'id' => _ID, 'title' => _QUESTION, 'category' => _CATEGORY, 'poster' => _POSTER, 'date' => _DATE]);
		while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cadesc = $cdesc ?: $ctitle;
			$post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
			$cont .= $tpl->getHtmlFrag('liste-basic', ['id' => $id, 'title_href' => $thref, 'title_attr' => $title, 'title_text' => cutstr($title, 40), 'title_new' => new_graphic($time), 'category_href' => $ctitle ? $chref : '', 'category_attr' => $cadesc, 'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO, 'post_text' => $post, 'time_text' => format_time($time), 'time_iso' => date('c', strtotime($time)), 'time_label' => _DATE]);
		}
		$cont .= $tpl->getHtmlFrag('liste-wrap', []);
		$onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
		$params = ($let) ? ['let' => $let.'%'] : [];
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_faq', 'cid', $onum, $conf['faq']['nump'], $params);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $afile, $conf, $tpl;
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
		if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $cid, $conf['faq']['defis'], _FAQ)]);
		if ($conf['faq']['viewcat']) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], 0);
		$conpag = explode('[pagebreak]', $hometext);
		$pageno = count($conpag);
		if ($pag > $pageno) $pag = $pageno;
		$arrayelement = (int)$pag;
		$arrayelement--;
		$cdesc = $cdesc ?: $ctitle;
		$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
		$post = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
		$date = ($conf['faq']['date']) ? format_time($time) : '';
		$rating = getRatingAsync(1, $id, $conf['name'], $ratings, $score, '');
		$favorites = getFavoriteButton($id, $conf['name']);
		$ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
		$cont .= getTplContentView(['is_moder' => is_moder($conf['name']), 'id' => $id, 'title_href' => '', 'title_attr' => $title, 'title_text' => filterTextHighlight($title, $word), 'title_new' => '', 'category_href' => $ctitle ? $chref : '', 'category_attr' => $cdesc, 'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '', 'category_img' => $cimg, 'text' => filterTextHighlight(filterReplaceText(filterMarkdown($conpag[$arrayelement], $conf['name'], false), $conf['name']), $word), 'read_href' => '', 'read_text' => '', 'post_text' => $post, 'post_label' => _POSTEDBY, 'date_text' => $date, 'date_iso' => ($date) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'reads_text' => ($conf['faq']['read']) ? $counter : '', 'reads_label' => _READS, 'hits' => '', 'comm_href' => '', 'comm_text' => '', 'comm_label' => _COMMENTS, 'rating' => $rating, 'favorites' => $favorites, 'voting' => '', 'editor' => _EDITOR, 'edit_href' => $afile.'.php?op=faq_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=faq_delete&amp;id='.$id, 'delete_text' => _ONDELETE, 'delete_ask' => $ask, 'back_title' => _BACK, 'back_text' => _BACK]);
		$cont .= setPageNumbers('pagenum', $conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['faq']['nump'], (int)$pag, '#'.$id);
		if ($conf['faq']['link']) {
			$limit = intval($conf['faq']['linknum']);
			[$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_faq WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->getSqlQuery('SELECT id, title, time, body FROM '.PREFIX_DB.'_faq WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
				$cont .= $tpl->getHtmlFrag('assoc-wrap', ['open' => true, 'title' => _CATASSOC]);
				while([$aid, $title, $time, $hometext] = $db->getSqlRow($result)) {
					$date = ($conf['faq']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
					$img = getImgText($hometext);
					$img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
					$cont .= $tpl->getHtmlFrag('assoc-basic', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), 'title_attr' => $title, 'title_text' => $title, 'date_text' => $date, 'date_iso' => ($conf['faq']['date']) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'text' => $text, 'img_src' => $img]);
				}
				$cont .= $tpl->getHtmlFrag('assoc-wrap', []);
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
	global $db, $user, $conf, $stop, $tpl;
	if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		setHead(['title' => _ADD]);
		$cont = setModuleNavi(['title' => _ADD, 'htitle' => _FAQ]);
		if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
		if ($hometext) $cont .= preview($title, $hometext, '', '', $conf['name']);
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBMIT.' '._PAGENOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $cont .= $tpl->getHtmlFrag('form-add', [
            'has_name' => true,
            'is_user' => is_user(),
            'name' => $conf['name'],
            'token' => htmlspecialchars(getSiteToken('faq'), ENT_QUOTES, 'UTF-8'),
            'style' => $conf['style'],
            'lbl_name' => _YOURNAME,
            'lbl_title' => _QUESTION,
            'lbl_cat' => _CATEGORY,
            'lbl_text' => _ANSWER,
            'username' => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname' => $postname,
            'titleval' => $title,
            'catselect' => getcat($conf['name'], $cid, 'catid', $conf['style'], getTplSelectOption('', _HOMECAT)),
            'hometext' => textarea('1', 'hometext', $hometext, $conf['name'], '10', _ANSWER, '1'),
            'captcha' => getCaptcha(1),
            'submit' => ad_save('', '', 'send'),
        ]);
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function send(): void {
	global $db, $user, $conf, $stop, $tpl;
	if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
		$title = getVar('post', 'title', 'title');
		$cid = getVar('post', 'catid', 'num');
		$hometext = getVar('post', 'hometext', 'text');
		$postname = getVar('post', 'postname', 'name');
		$stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'faq')) $stop[] = _ERROR;
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
			$meta = getTplMetaRefresh('index.php?name='.$conf['name']);
			echo setModuleNavi(['title' => _ADD, 'htitle' => _FAQ]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBTEXT, 'meta' => $meta]);
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
