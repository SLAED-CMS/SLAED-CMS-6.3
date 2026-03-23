<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const SHOP_NAVI = ['htitle' => _SHOP, 'add_href' => ''];


function shop(): void {
	global $db, $conf, $afile, $home, $user, $op, $tpl;
	$cwhere = catmids($conf['name'], 'p.cid');
	$unum = getUserNews($conf['shop']['num']);
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$params = [];
		if (!$ncat && $op && $conf['shop']['rate']) {
			$caton = 0;
			$field = 'op='.$op.'&';
			if ($op == 'best') {
				$orderby = 'IFNULL((p.tvotes/NULLIF(p.votes,0)),0) DESC';
				$ntitle = _BEST;
			} else {
				$orderby = 'IFNULL((p.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(p.time)),0)),0) DESC';
				$ntitle = _POP;
			}
		$order = "WHERE p.time <= NOW() AND p.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$onum = "time <= NOW() AND status != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
			$orderby = ($op) ? (($op == 'best') ? 'IFNULL((p.tvotes/NULLIF(p.votes,0)),0) DESC' : 'IFNULL((p.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(p.time)),0)),0) DESC') : 'p.fix DESC, p.time DESC';
		[$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (p.cid = :ncat1 OR p.assoc REGEXP :ncat_re OR c.parent = :ncat2) AND p.time <= NOW() AND p.status != '0' ".$cwhere.' ORDER BY '.$orderby;
		$params = ['ncat1' => $ncat, 'ncat_re' => '[[:<:]]'.$ncat.'[[:>:]]', 'ncat2' => $ncat];
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
		$onum = '('.$wcid." OR assoc REGEXP '[[:<:]]".$ncat."[[:>:]]') AND time <= NOW() AND status != '0'";
	} else {
		$caton = 1;
		$field = '';
		$hwhere = ($home) ? "AND p.ihome = '1'" : '';
		$hnwhere = ($home) ? "AND ihome = '1'" : '';
		$order = "WHERE p.time <= NOW() AND p.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC';
		$onum = "time <= NOW() AND status != '0' ".$hnwhere;
		$ntitle = _SHOP;
	}
	setHead(['title' => $ntitle]);
	$cont = '';
	if (!$home || ($home && $conf['shop']['homcat'])) {
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		$cont .= setModuleNavi(['title' => $ntitle] + SHOP_NAVI);
		if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $ncat, $defis, _SHOP)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.intro, p.body, p.price, p.acomm, p.comments, p.counter, p.votes, p.tvotes, c.title, c.intro, c.img FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
	if ($db->getSqlRowCount($result) > 0) {
		$cont .= $tpl->getHtmlFrag('shop-kasse-content', ['has_outer' => true, 'content' => show_kasse()]);
		$width = 100 / $conf['shop']['bascol'];
		$i = 1;
		$cont .= $tpl->getHtmlFrag('grid-table', ['open' => true]);
		while ([$id, $cid, $time, $stitle, $text, $bodytext, $pprice, $acomm, $pcom, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cdesc = $cdesc ?: $ctitle;
			$cimg = ($cimg) ? $tpl->getHtmlFrag('category-image', ['href' => $chref, 'title' => $cdesc, 'src' => img_find('categories/'.$cimg)]) : '';
			$post = '';
			$date = ($conf['shop']['date']) ? $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]) : '';
			$rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
			$prtitle = _PREIS;
			$price = $tpl->getHtmlFrag('shop-price-badge', ['title' => $prtitle, 'text' => $prtitle.': '.$pprice.' '.$conf['shop']['valute']]);
			$opreis = '';
			$discount = '';
			$cart = $tpl->getHtmlFrag('shop-cart-button', ['id' => $id, 'title' => _SCART, 'label' => _SCART]);
			$kasse = $tpl->getHtmlFrag('shop-kasse-link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=kasse', 'title' => _SCACH, 'label' => _SCACH]);
			$title = $tpl->getHtmlFrag('shop-title-link', ['href' => $thref, 'title' => $stitle, 'label' => $stitle, 'new' => new_graphic($time)]);
			$ctitle = ($ctitle) ? $tpl->getHtmlFrag('category-link', ['href' => $chref, 'title' => $cdesc, 'text' => cutstr($ctitle, 15)]) : '';
			$comm = ($acomm) ? $tpl->getHtmlFrag('shop-comment-link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'#comm', 'title' => _COMMENTS, 'label' => $pcom]) : '';
			$read = $tpl->getHtmlFrag('shop-read-link', ['href' => $thref, 'title' => $stitle, 'label' => _READMORE]);
			$admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('admin-menu', ['editor_text' => _EDITOR, 'edit_href' => $afile.'.php?op=shop_products_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'&amp;refer=1', 'delete_ask' => _DELETE.' &quot;'.$stitle.'&quot;?', 'delete_text' => _ONDELETE]) : '';
			if (($i - 1) % $conf['shop']['bascol'] == 0) $cont .= $tpl->getHtmlFrag('grid-table-row', ['open' => true]);
			$cont .= $tpl->getHtmlFrag('grid-table-cell', ['open' => true, 'width' => $width]);
			$cont .= $tpl->getHtmlFrag('basic-shop', ['id' => $id, 'favorites' => '', 'title' => $title, 'comm' => $comm, 'hits' => '', 'reads' => ($conf['shop']['read']) ? $tpl->getHtmlFrag('reads-badge', ['title' => _READS, 'text' => $counter]) : '', 'post' => '', 'date' => $date, 'ctitle' => $ctitle, 'preis' => $price, 'opreis' => $opreis, 'discount' => $discount, 'cart' => $cart, 'kasse' => $kasse, 'voting' => '', 'cimg' => $cimg, 'text' => filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), 'rating' => $rating, 'goback' => '', 'admin' => $admin, 'read' => $read]);
			$cont .= $tpl->getHtmlFrag('grid-table-cell', []);
			if ($i % $conf['shop']['bascol'] == 0) $cont .= $tpl->getHtmlFrag('grid-table-row', []);
			$i++;
		}
		if (($i - 1) % $conf['shop']['bascol'] != 0) $cont .= $tpl->getHtmlFrag('grid-table-row', []);
		$cont .= $tpl->getHtmlFrag('grid-table', []);
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_products', 'cid', $onum, $conf['shop']['nump']);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf, $tpl;
	$cwhere = catmids($conf['name'], 'p.cid');
	$listnum = intval($conf['shop']['listnum']);
	$let = getVar('get', 'let', 'let');
	$params = [];
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(p.title) LIKE BINARY :let AND p.time <= NOW() AND p.status != '0'";
		$params['let'] = $let.'%';
	} else {
		$field = 'op=liste&';
		$order = "WHERE p.time <= NOW() AND p.status != '0'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.price, c.title, c.intro FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$listnum, $params);
	setHead(['title' => _LIST]);
	$cont = setModuleNavi(['title' => _LIST] + SHOP_NAVI);
	if ($db->getSqlRowCount($result) > 0) {
		$letter = ($conf['shop']['letter']) ? letter($conf['name']) : '';
		$cont .= $tpl->getHtmlFrag('liste-wrap', ['open' => true, 'letter' => $letter, 'id' => _ID, 'title' => _TITLE, 'category' => _CATEGORY, 'poster' => _PREIS, 'date' => _DATE]);
		while ([$id, $cid, $time, $title, $price, $ctitle, $cdesc] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cdesc = $cdesc ?: $ctitle;
			$price = $price.' '.$conf['shop']['valute'];
			$cont .= $tpl->getHtmlFrag('liste-basic', ['id' => $id, 'title_href' => $thref, 'title_attr' => $title, 'title_text' => cutstr($title, 40), 'title_new' => new_graphic($time), 'category_href' => $ctitle ? $chref : '', 'category_attr' => $cdesc, 'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO, 'post_text' => $price, 'time_text' => format_time($time), 'time_iso' => date('c', strtotime($time)), 'time_label' => _DATE]);
		}
		$cont .= $tpl->getHtmlFrag('liste-wrap', []);
		$onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
		$params = ($let) ? ['let' => $let.'%'] : [];
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_products', 'cid', $onum, $conf['shop']['nump'], $params);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $conf, $afile, $tpl;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$cwhere = catmids($conf['name'], 'p.cid');
	$result = $db->getSqlQuery('SELECT p.cid, p.time, p.title, p.intro, p.body, p.price, p.vote, p.assoc, p.acomm, p.counter, p.votes, p.tvotes, c.title, c.intro, c.img FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE p.id = :id AND p.time <= NOW() AND p.status != \'0\' '.$cwhere, ['id' => $id]);
	if ($db->getSqlRowCount($result) == 1) {
		$db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET counter = counter+1 WHERE id = :id', ['id' => $id]);
		[$cid, $time, $title, $text, $bodytext, $pprice, $vote, $passoc, $acomm, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result);
		$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
		$seotitle = $title;
		$seoctitle = $ctitle;
		$seodesc = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']))), 160);
		$seoimg = getImgText($text, '', false);
		$seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
		$seotime = $time;
		$seoauthor = ($nick ?? '') ?: (($uname ?? '') ?: $conf['sitename']);
		setHead([
			'title' => $seotitle,
			'ctitle' => $seoctitle,
			'desc' => $seodesc,
			'img' => $seoimg,
			'time' => $seotime,
			'author' => $seoauthor,
		]);
		$cont = setModuleNavi(['title' => _SHOP] + SHOP_NAVI);
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $cid, $defis, _SHOP)]);
		if ($conf['shop']['viewcat']) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], 0);
		$cont .= $tpl->getHtmlFrag('shop-kasse-content', ['has_outer' => true, 'content' => show_kasse()]);
		$text = ($bodytext) ? $text.'<br><br>'.$bodytext : $text;
		$cdesc = $cdesc ?: $ctitle;
		$cimg = ($cimg) ? $tpl->getHtmlFrag('category-image', ['href' => $chref, 'title' => $cdesc, 'src' => img_find('categories/'.$cimg)]) : '';
		$post = '';
		$date = ($conf['shop']['date']) ? $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]) : '';
		$rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
		$favorites = getFavorBtn($id, $conf['name']);
		$voting = ($vote) ? $tpl->getHtmlFrag('shop-voting-box', ['id' => 'rep'.$conf['name'], 'content' => getVoting($vote, $conf['name'])]) : '';
		$prtitle = _PREIS;
		$price = $tpl->getHtmlFrag('shop-price-badge', ['title' => $prtitle, 'text' => $prtitle.': '.$pprice.' '.$conf['shop']['valute']]);
		$opreis = '';
		$discount = '';
		$cart = $tpl->getHtmlFrag('shop-cart-button', ['id' => $id, 'title' => _SCART, 'label' => _SCART]);
		$kasse = $tpl->getHtmlFrag('shop-kasse-link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=kasse', 'title' => _SCACH, 'label' => _SCACH]);
		$ctitle = ($ctitle) ? $tpl->getHtmlFrag('category-link', ['href' => $chref, 'title' => $cdesc, 'text' => cutstr($ctitle, 15)]) : '';
		$goback = $tpl->getHtmlFrag('back-button', ['title' => _BACK, 'label' => _BACK]);
		$admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('admin-menu', ['editor_text' => _EDITOR, 'edit_href' => $afile.'.php?op=shop_products_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id, 'delete_ask' => _DELETE.' &quot;'.$title.'&quot;?', 'delete_text' => _ONDELETE]) : '';
		$cont .= $tpl->getHtmlFrag('basic-shop', ['id' => $id, 'favorites' => $favorites, 'title' => filterTextHighlight($title, $word), 'comm' => '', 'hits' => '', 'reads' => ($conf['shop']['read']) ? $tpl->getHtmlFrag('reads-badge', ['title' => _READS, 'text' => $counter]) : '', 'post' => '', 'date' => $date, 'ctitle' => $ctitle, 'preis' => $price, 'opreis' => $opreis, 'discount' => $discount, 'cart' => $cart, 'kasse' => $kasse, 'voting' => $voting, 'cimg' => $cimg, 'text' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word), 'rating' => $rating, 'goback' => $goback, 'admin' => $admin, 'read' => '']);
		if ($conf['shop']['assoc']) {
			$limit = intval($conf['shop']['assocnum']);
			[$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND status != \'0\'', ['id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->getSqlQuery('SELECT id, time, title, intro, body FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['id' => $id]);
				$cont .= $tpl->getHtmlFrag('assoc-wrap', ['open' => true, 'title' => _ASPROD]);
				while ([$aid, $time, $title, $hometext, $bodytext] = $db->getSqlRow($result)) {
					$date = ($conf['shop']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
					$img = getImgText($hometext);
					$img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
					$cont .= $tpl->getHtmlFrag('assoc-basic', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), 'title_attr' => $title, 'title_text' => $title, 'date_text' => $date, 'date_iso' => ($conf['shop']['date']) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'text' => $text, 'img_src' => $img]);
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

function kasse(): void {
	global $db, $conf, $stop, $tpl;
	if (is_user()) {
		$userinfo = getUserInfo();
		$sid = $userinfo['id'];
		$slogin = $userinfo['name'];
		$smail = getVar('post', 'smail', 'text', $userinfo['email']);
		$sdom = getVar('post', 'sdom', 'url', $userinfo['website']);
	} else {
		$sid = 0;
		$slogin = _ANONYM;
		$smail = getVar('post', 'smail', 'text');
		$sdom = getVar('post', 'sdom', 'url', 'http://');
	}
	$sname = getVar('post', 'sname', 'text');
	$sadr = getVar('post', 'sadr', 'text');
	$stel = getVar('post', 'stel', 'text');
	$smsg = getVar('post', 'smsg', 'text');
	$opi = getVar('post', 'opi', 'num');
	$shopCookie = filter_input(INPUT_COOKIE, 'shop', FILTER_DEFAULT) ?: '';
	$cookieRaw = base64_decode($shopCookie, true);
	$cookies = (is_string($cookieRaw) && !preg_match('/[^0-9,]/', $cookieRaw)) ? $cookieRaw : '';
	$idPartner = filter_input(INPUT_COOKIE, 'part', FILTER_VALIDATE_INT);
	$idPartner = ($idPartner !== false && $idPartner !== null) ? $idPartner : '';
	$stop = (!$cookies) ? _SERRORP : '';
	$form = $tpl->getHtmlFrag('shop-kasse-form', ['name' => $conf['name'], 'token' => htmlspecialchars(getSiteToken('shop'), ENT_QUOTES, 'UTF-8'), 'style' => $conf['style'], 'lbl_name' => _C_PIN, 'ph_name' => _C_PINB, 'lbl_addr' => _C_PIP, 'ph_addr' => _C_PIPB, 'lbl_phone' => _C_TEL, 'ph_phone' => _C_TELB, 'lbl_email' => _C_MAIL, 'ph_email' => _C_MAILB, 'lbl_site' => _SDOM, 'ph_site' => _SDOMB, 'lbl_msg' => _C_MESSAGE, 'sname' => $sname, 'sadr' => $sadr, 'stel' => $stel, 'smail' => $smail, 'sdom' => $sdom, 'smsg' => $smsg, 'submit_label' => _C_SEND]);
	setHead(['title' => _C_TITLE]);
	$cont = setModuleNavi(['title' => _C_TITLE] + SHOP_NAVI);
	if (!$opi && $cookies) {
		$cont .= $tpl->getHtmlFrag('shop-kasse-content', ['has_outer' => false, 'content' => show_kasse()]);
		$cont .= $tpl->getHtmlFrag('title', ['title' => _C_TITLE]).$form;
	} elseif ($opi && $cookies) {
		$stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'shop')) $stop[] = _ERROR;
		checkemail($smail);
		if (!$sname || !$sadr || !$stel || !$smail) {
			$stop[] = _ERROR_ALL;
		}
		if (!$stop) {
			$ptotal = 0;
			$content = '';
			$result = $db->getSqlQuery('SELECT id, title, price FROM '.PREFIX_DB.'_products WHERE id IN ('.$cookies.')');
			while([$id, $title, $price] = $db->getSqlRow($result)) {
				$massiv = explode(',', $cookies);
				$i = 0;
				foreach ($massiv as $val) {
					if ($val == $id) $i++;
				}
				$price = $price * $i;
				$ptotal += $price;
				$content .= $tpl->getHtmlFrag('shop-order-row', ['id' => $id, 'qty' => $i, 'title' => $title, 'price' => $price.' '.$conf['shop']['valute']]);
			}
			$pinfo = $tpl->getHtmlFrag('shop-order-table', ['head_id' => _ID, 'head_qty' => _QUANTITY, 'head_product' => _PRODUCT, 'head_price' => _PREIS, 'rows' => $content, 'total_label' => _PARTNERGES, 'total_value' => $ptotal.' '.$conf['shop']['valute']]);
			if ($conf['shop']['mailsend']) {
				$amail = ($conf['shop']['mail']) ? $conf['shop']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $conf['sitename'].' - '._C_TITLE.'<br><br>';
				$msg .= $pinfo.'<br><br>';
				$msg .= '<b>'._PERSONALINFO.'</b><br><br>';
				$msg .= _NICKNAME.': '.$slogin.'<br>';
				$msg .= _C_PIN.': '.$sname.'<br>';
				$msg .= _C_PIP.': '.$sadr.'<br>';
				$msg .= _C_TEL.': '.$stel.'<br>';
				$msg .= _C_MAIL.': '.$smail.'<br>';
				$msg .= _SITEURL.': '.$sdom.'<br>';
				$msg .= _C_MESSAGE.': '.$smsg;
				addMail($amail, $smail, $subject, $msg, 1, 1);
			}
			if ($conf['shop']['mailuser']) {
				$amail = ($conf['shop']['mail']) ? $conf['shop']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $conf['sitename'].' - '._C_TITLE.'<br><br>';
				$msg .= filterReplaceText(filterMarkdown($conf['shop']['sende'], $conf['name'], false), $conf['name']).'<br><br>';
				$msg .= $pinfo.'<br><br>';
				$msg .= '<b>'._PERSONALINFO.'</b><br><br>';
				$msg .= _NICKNAME.': '.$slogin.'<br>';
				$msg .= _C_PIN.': '.$sname.'<br>';
				$msg .= _C_PIP.': '.$sadr.'<br>';
				$msg .= _C_TEL.': '.$stel.'<br>';
				$msg .= _C_MAIL.': '.$smail.'<br>';
				$msg .= _SDOM.': '.$sdom.'<br>';
				$msg .= _C_MESSAGE.': '.$smsg;
				addMail($smail, $amail, $subject, $msg, 0, 3);
			}
			$massiv = explode(',', $cookies);
			foreach ($massiv as $val) {
				if ($val != '') {
					$sreg = time();
					$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients VALUES(NULL, :sender_id, :val, :id_partner, \'0\', :sender_name, :sender_adr, :sender_tel, :sender_email, :sender_dom, :sender_regdate, \'0\', \'0\', \'2\')', ['sender_id' => $sid, 'val' => $val, 'id_partner' => $idPartner, 'sender_name' => $sname, 'sender_adr' => $sadr, 'sender_tel' => $stel, 'sender_email' => $smail, 'sender_dom' => $sdom, 'sender_regdate' => $sreg]);
				}
			}
			setcookie('shop', false);
			setcookie('part', false);
			update_points(39);
			$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => filterReplaceText(filterMarkdown($conf['shop']['sende'], $conf['name'], false), $conf['name'])]);
		} else {
			$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
			$cont .= $tpl->getHtmlFrag('shop-kasse-content', ['has_outer' => false, 'content' => show_kasse()]);
			$cont .= $form;
		}
	} else {
		$meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'">';
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop, 'meta' => $meta]);
	}
	echo $cont;
	setFoot();
}

function part(): void {
	global $conf;
	$id = getVar('get', 'id', 'num');
	if ($id) setcookie('part', $id, time() + $conf['shop']['part_t']);
	setRedirect('index.php?name='.$conf['name']);
}

function clients(): void {
	global $db, $user, $conf, $tpl;
	if (is_user() && is_active('shop')) {
		$uid = intval($user[0]);
		setHead(['title' => _CLIENTINFO]);
		$cont = setModuleNavi(['title' => _CLIENTINFO] + SHOP_NAVI);
		$cont .= getUserNav();
		$result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.id, u.name, p.id, p.title, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.uid = :user_id ORDER BY c.id ASC', ['user_id' => $uid]);
		if ($db->getSqlRowCount($result) > 0) {
			$cont .= $tpl->getHtmlFrag('shop-clients-open', ['head_id' => _ID, 'head_product' => _PRODUCT, 'head_date' => _L_DATE, 'head_status' => _STATUS, 'head_functions' => _FUNCTIONS]);
			while([$cid, $cuid, $cprod, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $stitle, $pprice] = $db->getSqlRow($result)) {
				$website = ($cwebsite) ? '<br>'._SITE.': '.$cwebsite : '';
				$note = ($cinfo) ? '<br>'._NOTE.' : '.$cinfo : '';
				$cenddate = ($cenddate != '0') ? getTimeLeft($cenddate) : _NO;
				$rechn = $tpl->getHtmlFrag('shop-rechn-link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=rech&amp;id='.$cid, 'title' => _RECHN_B, 'label' => _RECHN_B]);
				$cont .= $tpl->getHtmlFrag('shop-client-row', ['id' => $cid, 'tip' => title_tip(_PREIS.': '.$pprice.' '.$conf['shop']['valute'].$website.$note), 'title' => $stitle, 'title_text' => cutstr($stitle, 35), 'date' => $cenddate, 'status' => ad_status('', $cactive), 'actions' => $rechn]);
			}
			$cont .= $tpl->getHtmlFrag('table-close', []);
		}
		$cont .= filterReplaceText(filterMarkdown($conf['shop']['userinfo'], $conf['name'], false), $conf['name']);
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function rech(): void {
	global $db, $conf, $theme, $tpl;
	if (is_user() && is_active('shop')) {
		$defis = urldecode($conf['defis']);
		$id = getVar('get', 'id', 'num');
		$result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, p.id, p.title, p.intro, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.id = :id ORDER BY c.id ASC', ['id' => $id]);
		if ($db->getSqlRowCount($result) > 0) {
			[$cid, $cuid, $cprod, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $pid, $stitle, $text, $pprice] = $db->getSqlRow($result);
			$themeCss = file_exists('templates/'.$theme.'/theme.css') ? 'templates/'.$theme.'/theme.css' : '';
			$cenddate = ($cenddate != '0') ? date(_TIMESTRING, $cenddate) : _UNLIMITED;
			echo $tpl->getHtmlFrag('shop-rech', [
				'charset' => _CHARSET,
				'theme_css' => $themeCss,
				'title' => $conf['sitename'].' '.$defis.' '._CLIENTINFO.' '.$defis.' '._RECHN,
				'logo_src' => img_find('logos/'.$conf['site_logo']),
				'logo_alt' => $conf['sitename'],
				'shopinfo' => filterReplaceText(filterMarkdown($conf['shop']['shopinfo'], $conf['name'], false), $conf['name']),
				'lbl_name' => _C_PIN,
				'name' => $cname,
				'lbl_addr' => _C_PIP,
				'addr' => $caddr,
				'lbl_phone' => _C_TEL,
				'phone' => $cphone,
				'lbl_email' => _C_MAIL,
				'email' => $cemail,
				'heading' => _C_NAIM,
				'date_label' => _K_DATE,
				'date_value' => date(_TIMESTRING, $cregdate),
				'lbl_product' => _PRODUCT,
				'product' => $stitle,
				'lbl_site' => _SDOM,
				'site' => $cwebsite,
				'lbl_note' => _NOTE,
				'note' => $cinfo,
				'lbl_license_end' => _LIZENS_END,
				'license_end' => $cenddate,
				'product_text_label' => _PRODUCT_TEXT,
				'product_text' => filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']),
				'price_label' => _PREIS_TEXT,
				'price_value' => $pprice.' '.$conf['shop']['valute'],
			]);
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function partners(): void {
	global $db, $conf, $stop, $tpl;
	if (is_user() && is_active('shop')) {
		$userinfo = getUserInfo();
		$uid = intval($userinfo['id']);
		$smail = $userinfo['email'];
		$sdom = $userinfo['website'];
		setHead(['title' => _PARTNERINFO]);
		$cont = setModuleNavi(['title' => _PARTNERINFO] + SHOP_NAVI);
		$cont .= getUserNav();
		$result = $db->getSqlQuery('SELECT id, uid, name, addr, phone, email, website, webmoney, paypal, regdate, rest, bek, status FROM '.PREFIX_DB.'_partners WHERE uid = :user_id', ['user_id' => $uid]);
		if ($db->getSqlRowCount($result) > 0) {
			[$paid, $puid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result);
			if ($paactive == 2) {
				$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PARTNERADD_W]);
			} elseif ($paactive == 0) {
				$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _PARTNER_AUS]);
			} else {
				$result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.part, c.proz, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, u.id, u.name, p.id, p.title, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.part = :user_id AND c.status != 2 ORDER BY c.id ASC', ['user_id' => $uid]);
				$partsum = $partsumges = $a = 0;
				if ($db->getSqlRowCount($result) > 0) {
					$content = '';
					while([$cid, $cuid, $cprod, $cpart, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $uuid, $nick, $pid, $stitle, $pprice] = $db->getSqlRow($result)) {
						$partsum = $pprice / 100 * $proz;
						$partsumges += $partsum;
						$content .= $tpl->getHtmlFrag('shop-partner-row', ['id' => $cid, 'user' => user_info($nick), 'tip' => title_tip(_PREIS.': '.$pprice.' '.$conf['shop']['valute'].'<br>'._DATE.' : '.date(_TIMESTRING, $cregdate)), 'title' => $stitle, 'title_text' => cutstr($stitle, 35), 'percent' => $proz.' %', 'sum' => $partsum.' '.$conf['shop']['valute']]);
						$a++;
					}
					$cont .= $tpl->getHtmlFrag('shop-partners-open', ['head_id' => _ID, 'head_user' => _NICKNAME, 'head_product' => _PRODUCT, 'head_percent' => _PERCENT, 'head_sum' => _SUM]).$content.$tpl->getHtmlFrag('table-close', []);
				}
				$cont .= $tpl->getHtmlFrag('shop-partners-summary', ['head_clients' => _CLIENTEN, 'head_webmoney' => _WEBMONEY, 'head_paypal' => _PAYPAL, 'head_total' => _PARTNERGES, 'head_rest' => _PARTNERREST, 'head_paid' => _PARTNERBEK, 'clients' => $a, 'webmoney' => $pawebmoney, 'paypal' => $papaypal, 'total' => $partsumges.' '.$conf['shop']['valute'], 'rest' => $parest.' '.$conf['shop']['valute'], 'paid' => $pabek.' '.$conf['shop']['valute']]);
				$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _C_26.': '.str_replace('[id]', $uid, $conf['shop']['partlink'])]);
				$cont .= filterReplaceText(filterMarkdown(str_replace('[id]', $uid, $conf['shop']['partinfo2']), $conf['name'], false), $conf['name']);
			}
		} else {
			if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
			$cont .= filterReplaceText(filterMarkdown($conf['shop']['partinfo'], $conf['name'], false), $conf['name']);
			$cont .= $tpl->getHtmlFrag('title', ['title' => _PARTNERADD]);
			$cont .= $tpl->getHtmlFrag('shop-partners-form', ['name' => $conf['name'], 'token' => htmlspecialchars(getSiteToken('shop'), ENT_QUOTES, 'UTF-8'), 'style' => $conf['style'], 'lbl_name' => _C_PIN, 'ph_name' => _C_PINB, 'lbl_addr' => _C_PIP, 'ph_addr' => _C_PIPB, 'lbl_phone' => _C_TEL, 'ph_phone' => _C_TELB, 'lbl_email' => _EMAIL, 'ph_email' => _C_MAILB, 'lbl_site' => _SITE, 'ph_site' => _SDOMB, 'lbl_webmoney' => _WEBMONEY, 'ph_webmoney' => _C_WEBMONEYB, 'lbl_paypal' => _PAYPAL, 'ph_paypal' => _C_MAILB, 'paname' => '', 'paaddr' => '', 'paphone' => '', 'paemail' => $smail, 'pawebsite' => $sdom, 'pawebmoney' => '', 'papaypal' => '', 'puid' => $uid, 'submit_label' => _PARTNERSEND]);
		}
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function partners_send(): void {
	global $db, $user, $conf, $stop;
	if (is_user() && is_active('shop')) {
		$puid = getVar('post', 'puid', 'num');
		$paname = getVar('post', 'paname', 'text');
		$paaddr = getVar('post', 'paaddr', 'text');
		$paphone = getVar('post', 'paphone', 'text');
		$paemail = getVar('post', 'paemail', 'text');
		$pawebsite = getVar('post', 'pawebsite', 'url');
		$pawebmoney = getVar('post', 'pawebmoney', 'text');
		$papaypal = getVar('post', 'papaypal', 'text');
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'shop')) $stop[] = _ERROR;
		checkemail($paemail);
		if (!$paname || !$paaddr || !$paphone) $stop[] = _ERROR_ALL;
		if (!$stop) {
			$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, :puid, :paname, :paaddr, :paphone, :paemail, :pawebsite, :pawebmoney, :papaypal, \''.time().'\', \'0\', \'0\', \'2\')', ['puid' => $puid, 'paname' => $paname, 'paaddr' => $paaddr, 'paphone' => $paphone, 'paemail' => $paemail, 'pawebsite' => $pawebsite, 'pawebmoney' => $pawebmoney, 'papaypal' => $papaypal]);
			setRedirect('index.php?name='.$conf['name'].'&op=partners');
		} else {
			partners();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: shop(); break;
	case 'liste': liste(); break;
	case 'view': view(); break;
	case 'kasse': kasse(); break;
	case 'part': part(); break;
	case 'clients': clients(); break;
	case 'rech': rech(); break;
	case 'partners': partners(); break;
	case 'partners_send': partners_send(); break;
}
