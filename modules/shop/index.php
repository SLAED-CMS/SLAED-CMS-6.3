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
	global $db, $conf, $afile, $home, $user, $op, $tpl, $prs;
	$cwhere = catmids($conf['name'], 'p.cid');
	$unum = (int)getUserNews($conf['shop']['num']);
	if ($unum < 1) $unum = 1;
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$params = [];
		if (!$ncat && $op && $conf['shop']['rate']) {
			$caton = 0;
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
			$wcid = 'cid IN ('.implode(', ', array_map('intval', $cids)).')';
		} else {
			$caton = 0;
			$wcid = 'cid = '.(int)$ncat;
		}
		$onum = '('.$wcid." OR assoc REGEXP '[[:<:]]".$ncat."[[:>:]]') AND time <= NOW() AND status != '0'";
	} else {
		$caton = 1;
		$hwhere = ($home) ? "AND p.ihome = '1'" : '';
		$hnwhere = ($home) ? "AND ihome = '1'" : '';
		$order = "WHERE p.time <= NOW() AND p.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC';
		$onum = "time <= NOW() AND status != '0' ".$hnwhere;
		$ntitle = _SHOP;
	}
	$url_extra = [];
	if ($ncat) $url_extra['cat'] = $ncat;
	if ($op)   $url_extra['op']  = $op;
	setHead(['title' => $ntitle]);
	$cont = '';
	if (!$home || ($home && $conf['shop']['homcat'])) {
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		$cont .= getModuleNavi(['title' => $ntitle] + SHOP_NAVI);
		if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $ncat, $defis, _SHOP)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], $ncat);
	}
	$num    = getVar('get', 'num', 'num', '1');
	$offset = (int)(($num - 1) * $unum);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.intro, p.body, p.price, p.acomm, p.comments, p.counter, p.votes, p.tvotes, c.title, c.intro, c.img FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
	if ($db->getSqlRowCount($result) > 0) {
		$cont .= $tpl->getHtmlFrag('post-div', ['id' => 'shop', 'content' => $tpl->getHtmlFrag('post-div', ['id' => 'repkasse', 'content' => getCartSummary()])]);
		$columns = max(1, min(6, (int)$conf['shop']['bascol']));
		$cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
		while ([$id, $cid, $time, $stitle, $text, $bodytext, $pprice, $acomm, $pcom, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cdesc = $cdesc ?: $ctitle;
			$cimg = ($cimg) ? $tpl->getHtmlFrag('link', ['href' => $chref, 'title' => $cdesc, 'img_src' => img_find('categories/'.$cimg), 'img_alt' => $cdesc, 'is_card_image' => true]) : '';
			$post = '';
			$date = ($conf['shop']['date']) ? $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]) : '';
			$rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
			$prtitle = _PREIS;
			$price = $tpl->getHtmlFrag('span', ['title' => $prtitle, 'text' => $prtitle.': '.$pprice.' '.$conf['shop']['valute'], 'is_shop_price' => true]);
			$opreis = '';
			$discount = '';
			$cart = $tpl->getHtmlFrag('link', ['href' => 'index.php?go=2&amp;op=addCartItem&amp;id='.$id, 'title' => _SCART, 'label' => _SCART, 'class' => 'sl-shop-add', 'is_htmx' => true, 'hx_target' => '#repkasse', 'onclick_attr' => 'onclick="AddBasket(\''.$id.'\');"']);
			$kasse = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=kasse', 'title' => _SCACH, 'label' => _SCACH, 'is_shop_checkout' => true]);
			$title = $tpl->getHtmlFrag('link', ['href' => $thref, 'title' => $stitle, 'label_html' => $stitle, 'suffix_html' => getTplNewGraphic($time)]);
			$ctitle = ($ctitle) ? $tpl->getHtmlFrag('link', ['href' => $chref, 'title' => $cdesc, 'label' => cutstr($ctitle, 15), 'is_category' => true]) : '';
			$comm = ($acomm) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'#comm', 'title' => _COMMENTS, 'label' => $pcom, 'is_comment' => true]) : '';
			$read = $tpl->getHtmlFrag('link', ['href' => $thref, 'title' => $stitle, 'label' => _READMORE, 'is_read' => true]);
			$admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('edit-tip', ['editor_label' => _EDITOR, 'edit_link' => ['href' => $afile.'.php?op=shop_products_add&amp;id='.$id, 'title' => _FULLEDIT, 'label' => _FULLEDIT], 'delete_link' => ['href' => $afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'&amp;refer=1', 'confirm_text' => _DELETE.' &quot;'.$stitle.'&quot;?', 'title' => _ONDELETE, 'label' => _ONDELETE, 'is_delete' => true]]) : '';
			$cont .= $tpl->getHtmlFrag('card', [
				'id'           => $id,
				'columns'      => $columns,
				'favorites'    => '',
				'title_html'   => $title,
				'comm_html'    => $comm,
				'hits'         => '',
				'reads_html'   => ($conf['shop']['read']) ? $tpl->getHtmlFrag('span', ['title' => _READS, 'text' => $counter, 'is_card_reads' => true]) : '',
				'post_text'    => '',
				'date_html'    => $date,
				'category_html'=> $ctitle,
				'aside_items'  => [
					['content_html' => $price],
					['content_html' => $opreis],
					['content_html' => $discount],
					['content_html' => $cart],
					['content_html' => $kasse],
				],
				'voting'       => '',
				'image_html'   => $cimg,
				'text'         => $prs->filterContent($text, false, $conf['name']),
				'rating'       => $rating,
				'footer_items' => [
					['content_html' => $admin],
					['content_html' => $read],
				],
			]);
		}
		$cont .= $tpl->getHtmlFrag('grid', []);
		$cont .= getTplPager([
			'limit'     => $unum,
			'maxpg'     => $conf['shop']['nump'],
			'table'     => '_products',
			'field'     => 'id',
			'mod'       => $conf['name'],
			'where'     => $onum,
			'url_extra' => $url_extra,
			'prefix'    => 'new/',
		]);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf, $tpl;
	$cwhere = catmids($conf['name'], 'p.cid');
	$listnum = (int)$conf['shop']['listnum'];
	if ($listnum < 1) $listnum = 1;
	$let    = getVar('get', 'let', 'let');
	$params = [];
	if ($let) {
		$order = "WHERE UCASE(p.title) LIKE BINARY :let AND p.time <= NOW() AND p.status != '0'";
		$params['let'] = $let.'%';
		$onum = "title LIKE BINARY '".addslashes($let)."%' AND time <= NOW() AND status != '0'";
	} else {
		$order = "WHERE p.time <= NOW() AND p.status != '0'";
		$onum  = "time <= NOW() AND status != '0'";
	}
	$url_extra = ['op' => 'liste'];
	if ($let) $url_extra['let'] = $let;
	$num    = getVar('get', 'num', 'num', '1');
	$offset = (int)(($num - 1) * $listnum);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.price, c.title, c.intro FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$listnum, $params);
	setHead(['title' => _LIST]);
	$cont = getModuleNavi(['title' => _LIST] + SHOP_NAVI);
	$rows = [];
	while ([$id, $cid, $time, $title, $price, $ctitle, $cdesc] = $db->getSqlRow($result)) {
		$cdesc = $cdesc ?: $ctitle;
		$rows[] = [
			'id'            => (string)$id,
			'title_href'    => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
			'title_attr'    => $title,
			'title_text'    => cutstr($title, 40),
			'title_new'     => getTplNewGraphic($time),
			'category_href' => $ctitle ? getSeoUrl(['name' => $conf['name'], 'cat' => $cid]) : '',
			'category_attr' => $cdesc,
			'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
			'post_text'     => $price.' '.$conf['shop']['valute'],
			'time_text'     => format_time($time),
			'time_iso'      => date('c', strtotime($time)),
			'time_label'    => _DATE,
		];
	}
	$cont .= $tpl->getHtmlPart('liste', [
		'rows'        => $rows,
		'before_html' => ($conf['shop']['letter'] && $rows) ? letter($conf['name']) : '',
		'table_open'  => [
			'open'       => true,
			'sortable'   => true,
			'col_id'     => _ID,
			'col_title'  => _TITLE,
			'col_cat'    => _CATEGORY,
			'col_poster' => _PREIS,
			'col_date'   => _DATE,
		],
		'table_close' => [],
		'pager_html'  => $rows ? getTplPager([
			'limit'     => $listnum,
			'maxpg'     => $conf['shop']['nump'],
			'table'     => '_products',
			'field'     => 'id',
			'mod'       => $conf['name'],
			'where'     => $onum,
			'url_extra' => $url_extra,
		]) : '',
		'empty_alert' => ['is_warn' => false, 'text' => _NO_INFO],
	]);
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $conf, $afile, $tpl, $prs;
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
		$seodesc = cutstr(trim(strip_tags($prs->filterContent($text, false, $conf['name']))), 160);
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
		$cont = getModuleNavi(['title' => _SHOP] + SHOP_NAVI);
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $cid, $defis, _SHOP)]);
		if ($conf['shop']['viewcat']) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], 0);
		$cont .= $tpl->getHtmlFrag('post-div', ['id' => 'shop', 'content' => $tpl->getHtmlFrag('post-div', ['id' => 'repkasse', 'content' => getCartSummary()])]);
		$cdesc = $cdesc ?: $ctitle;
		$cimg = ($cimg) ? $tpl->getHtmlFrag('link', ['href' => $chref, 'title' => $cdesc, 'img_src' => img_find('categories/'.$cimg), 'img_alt' => $cdesc, 'is_card_image' => true]) : '';
		$post = '';
		$date = ($conf['shop']['date']) ? $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => format_time($time)]) : '';
		$rating = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
		$favorites = getFavoriteButton($id, $conf['name']);
		$voting = ($vote) ? $tpl->getHtmlFrag('post-div', ['id' => 'rep'.$conf['name'], 'class' => 'sl-section', 'content' => getVotingView($vote, $conf['name']), 'has_hr' => true]) : '';
		$prtitle = _PREIS;
		$price = $tpl->getHtmlFrag('span', ['title' => $prtitle, 'text' => $prtitle.': '.$pprice.' '.$conf['shop']['valute'], 'is_shop_price' => true]);
		$opreis = '';
		$discount = '';
		$cart = $tpl->getHtmlFrag('link', ['href' => 'index.php?go=2&amp;op=addCartItem&amp;id='.$id, 'title' => _SCART, 'label' => _SCART, 'class' => 'sl-shop-add', 'is_htmx' => true, 'hx_target' => '#repkasse', 'onclick_attr' => 'onclick="AddBasket(\''.$id.'\');"']);
		$kasse = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=kasse', 'title' => _SCACH, 'label' => _SCACH, 'is_shop_checkout' => true]);
		$ctitle = ($ctitle) ? $tpl->getHtmlFrag('link', ['href' => $chref, 'title' => $cdesc, 'label' => cutstr($ctitle, 15), 'is_category' => true]) : '';
		$goback = $tpl->getHtmlFrag('span', ['title' => _BACK, 'text' => _BACK, 'is_back' => true]);
			$admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('edit-tip', ['editor_label' => _EDITOR, 'edit_link' => ['href' => $afile.'.php?op=shop_products_add&amp;id='.$id, 'title' => _FULLEDIT, 'label' => _FULLEDIT], 'delete_link' => ['href' => $afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id, 'confirm_text' => _DELETE.' &quot;'.$title.'&quot;?', 'title' => _ONDELETE, 'label' => _ONDELETE, 'is_delete' => true]]) : '';
		$cont .= $tpl->getHtmlFrag('card', [
			'id'           => $id,
			'favorites'    => $favorites,
			'title_html'   => filterTextHighlight($title, $word),
			'comm_html'    => '',
			'hits'         => '',
			'reads_html'   => ($conf['shop']['read']) ? $tpl->getHtmlFrag('span', ['title' => _READS, 'text' => $counter, 'is_card_reads' => true]) : '',
			'post_text'    => '',
			'date_html'    => $date,
			'category_html'=> $ctitle,
			'aside_items'  => [
				['content_html' => $price],
				['content_html' => $opreis],
				['content_html' => $discount],
				['content_html' => $cart],
				['content_html' => $kasse],
			],
			'voting'       => $voting,
			'image_html'   => $cimg,
			'text'         => filterTextHighlight($prs->filterContent($text, false, $conf['name']), $word),
			'body_text'    => ($bodytext) ? filterTextHighlight($prs->filterContent($bodytext, false, $conf['name']), $word) : '',
			'rating'       => $rating,
			'footer_items' => [
				['content_html' => $goback],
				['content_html' => $admin],
			],
		]);
		if ($conf['shop']['assoc']) {
			$limit = (int)$conf['shop']['assocnum'];
			[$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND status != \'0\'', ['id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->getSqlQuery('SELECT id, time, title, intro, body FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['id' => $id]);
				$cont .= $tpl->getHtmlFrag('related', ['open' => true, 'title' => _ASPROD]);
				while ([$aid, $time, $title, $hometext, $bodytext] = $db->getSqlRow($result)) {
					$date = ($conf['shop']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
					$text = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), ENT_QUOTES), 80);
					$img = getImgText($hometext);
					$img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
					$cont .= $tpl->getHtmlFrag('related-item', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), 'title_attr' => $title, 'title_text' => $title, 'date_text' => $date, 'date_iso' => ($conf['shop']['date']) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'text' => $text, 'img_src' => $img]);
				}
				$cont .= $tpl->getHtmlFrag('related', []);
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
	global $db, $conf, $stop, $tpl, $prs;
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
	$fields = '';
	foreach ([
		[_C_PIN, 'text', 'sname', $sname, _C_PINB, true],
		[_C_PIP, 'text', 'sadr', $sadr, _C_PIPB, true],
		[_C_TEL, 'text', 'stel', $stel, _C_TELB, true],
		[_C_MAIL, 'email', 'smail', $smail, _C_MAILB, true],
		[_SDOM, 'url', 'sdom', $sdom, _SDOMB, false],
	] as [$label, $type, $name, $value, $placeholder, $required]) {
		$fields .= $tpl->getHtmlFrag('form-field-row', [
			'label' => $label.':',
			'field_html' => $tpl->getHtmlFrag('input', [
				'itype' => $type,
				'name_attr' => $name,
				'value_attr' => $value,
				'placeholder_text' => $placeholder,
				'is_required' => $required,
			]),
		]);
	}
	$fields .= $tpl->getHtmlFrag('form-field-row', [
		'label' => _C_MESSAGE.':',
		'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'smsg', 'rows_num' => 5, 'value_text' => $smsg, 'placeholder_text' => _C_MESSAGE]),
	]);
	$form = $tpl->getHtmlPart('form-add', [
		'action' => 'index.php?name='.$conf['name'],
		'method' => 'post',
		'form_name' => 'post',
		'no_enctype' => true,
		'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('shop')]).$fields,
		'submit' => $tpl->getHtmlFrag('form-submit', [
			'extra' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'opi', 'value_attr' => '1']),
			'op' => 'kasse',
			'label' => _C_SEND,
		]),
	]);
	setHead(['title' => _C_TITLE]);
	$cont = getModuleNavi(['title' => _C_TITLE] + SHOP_NAVI);
	if (!$opi && $cookies) {
		$cont .= $tpl->getHtmlFrag('post-div', ['id' => 'repkasse', 'content' => getCartSummary()]);
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
			$rows = [];
			$result = $db->getSqlQuery('SELECT id, title, price FROM '.PREFIX_DB.'_products WHERE id IN ('.$cookies.')');
			while ($row = $db->getSqlRow($result)) {
				[$id, $title, $price] = $row;
				$massiv = explode(',', $cookies);
				$i = 0;
				foreach ($massiv as $val) {
					if ($val == $id) $i++;
				}
				$price = $price * $i;
				$ptotal += $price;
				$rows[] = ['cells' => [
					['text' => $id, 'is_num' => true],
					['text' => $i, 'is_num' => true],
					['text' => $title],
					['text' => $price.' '.$conf['shop']['valute']],
				]];
			}
			$rows[] = ['cells' => [
				['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _PARTNERGES.': '.$ptotal.' '.$conf['shop']['valute']]), 'colspan' => 4],
			]];
			$pinfo = $tpl->getHtmlPart('liste', [
				'rows' => $rows,
				'table_open' => ['open' => true, 'headers' => [
					['text' => _ID, 'is_num' => true],
					['text' => _QUANTITY, 'is_num' => true],
					['text' => _PRODUCT],
					['text' => _PREIS],
				]],
				'table_close' => [],
			]);
			if ($conf['shop']['mailsend']) {
				$amail = ($conf['shop']['mail']) ? $conf['shop']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $tpl->getHtmlPart('message-block', [
					'title' => $subject,
					'summary_html' => $pinfo,
					'heading_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _PERSONALINFO]),
					'lines' => [
						['label' => _NICKNAME, 'value' => $slogin],
						['label' => _C_PIN, 'value' => $sname],
						['label' => _C_PIP, 'value' => $sadr],
						['label' => _C_TEL, 'value' => $stel],
						['label' => _C_MAIL, 'value' => $smail],
						['label' => _SITEURL, 'value' => $sdom],
						['label' => _C_MESSAGE, 'value' => $smsg],
					],
				]);
				addMail($amail, $smail, $subject, $msg, 1, 1);
			}
			if ($conf['shop']['mailuser']) {
				$amail = ($conf['shop']['mail']) ? $conf['shop']['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $tpl->getHtmlPart('message-block', [
					'title' => $subject,
					'intro_html' => $prs->filterContent($conf['shop']['sende'], false, $conf['name']),
					'summary_html' => $pinfo,
					'heading_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _PERSONALINFO]),
					'lines' => [
						['label' => _NICKNAME, 'value' => $slogin],
						['label' => _C_PIN, 'value' => $sname],
						['label' => _C_PIP, 'value' => $sadr],
						['label' => _C_TEL, 'value' => $stel],
						['label' => _C_MAIL, 'value' => $smail],
						['label' => _SDOM, 'value' => $sdom],
						['label' => _C_MESSAGE, 'value' => $smsg],
					],
				]);
				addMail($smail, $amail, $subject, $msg, 0, 3);
			}
			$massiv = explode(',', $cookies);
			foreach ($massiv as $val) {
				if ($val != '') {
					$sreg = time();
					$db->getSqlQuery(
						'INSERT INTO '.PREFIX_DB.'_clients VALUES(NULL, :sender_id, :val, :id_partner, \'0\', :sender_name, :sender_adr, :sender_tel, :sender_email, :sender_dom, :sender_regdate, \'0\', \'0\', \'2\')',
						['sender_id' => $sid, 'val' => $val, 'id_partner' => $idPartner, 'sender_name' => $sname, 'sender_adr' => $sadr, 'sender_tel' => $stel, 'sender_email' => $smail, 'sender_dom' => $sdom, 'sender_regdate' => $sreg]
					);
				}
			}
			setcookie('shop', false);
			setcookie('part', false);
			update_points(39);
			$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $prs->filterContent($conf['shop']['sende'], false, $conf['name'])]);
		} else {
			$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
			$cont .= $tpl->getHtmlFrag('post-div', ['id' => 'repkasse', 'content' => getCartSummary()]);
			$cont .= $form;
		}
	} else {
		$meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 5]);
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop, 'meta' => $meta]);
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
	global $db, $user, $conf, $tpl, $prs;
	if (is_user() && is_active('shop')) {
		$uid = (int)$user[0];
		setHead(['title' => _CLIENTINFO]);
		$cont = getModuleNavi(['title' => _CLIENTINFO] + SHOP_NAVI);
		$cont .= getUserNav();
		$result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.id, u.name, p.id, p.title, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.uid = :user_id ORDER BY c.id ASC', ['user_id' => $uid]);
		if ($db->getSqlRowCount($result) > 0) {
			$rows = [];
			while ($row = $db->getSqlRow($result)) {
				[$cid, $cuid, $cprod, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $stitle, $pprice] = $row;
					$tipItems = [['label' => _PREIS, 'value' => $pprice.' '.$conf['shop']['valute'], 'is_last' => false]];
					if ($cwebsite) $tipItems[] = ['label' => _SITE, 'value' => $cwebsite, 'is_last' => false];
					if ($cinfo) $tipItems[] = ['label' => _NOTE, 'value' => $cinfo, 'is_last' => true];
					else $tipItems[count($tipItems) - 1]['is_last'] = true;
					$cenddate = ($cenddate != '0') ? getTimeLeft($cenddate) : _NO;
					$rechn = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=rech&amp;id='.$cid, 'title' => _RECHN_B, 'label' => _RECHN_B, 'is_blank' => true]);
					$rows[] = ['id' => $cid, 'cells' => [
						['href' => '#'.$cid, 'title' => $cid, 'text' => $cid, 'is_num' => true],
						['prefix_html' => getTplTitleTip($tipItems), 'primary_title' => $stitle, 'primary_text' => cutstr($stitle, 35)],
						['content_html' => $cenddate],
						['content_html' => ad_status('', $cactive)],
						['content_html' => $rechn],
					]];
			}
			$cont .= $tpl->getHtmlPart('liste', [
				'rows' => $rows,
				'table_open' => ['open' => true, 'sortable' => true, 'headers' => [
					['text' => _ID, 'is_num' => true],
					['text' => _PRODUCT],
					['text' => _L_DATE],
					['text' => _STATUS],
					['text' => _FUNCTIONS, 'no_sort' => true],
				]],
				'table_close' => [],
			]);
		}
		$cont .= $prs->filterContent($conf['shop']['userinfo'], false, $conf['name']);
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function rech(): void {
	global $db, $conf, $theme, $tpl, $prs;
	if (is_user() && is_active('shop')) {
		$defis = urldecode($conf['defis']);
		$id = getVar('get', 'id', 'num');
		$result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, p.id, p.title, p.intro, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.id = :id ORDER BY c.id ASC', ['id' => $id]);
		if ($db->getSqlRowCount($result) > 0) {
			[$cid, $cuid, $cprod, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $pid, $stitle, $text, $pprice] = $db->getSqlRow($result);
			$themeCss = file_exists('templates/'.$theme.'/assets/css/theme.css') ? 'templates/'.$theme.'/assets/css/theme.css' : '';
			$cenddate = ($cenddate != '0') ? date(_TIMESTRING, $cenddate) : _UNLIMITED;
			echo $tpl->getHtmlFrag('shop-rech', [
				'charset' => _CHARSET,
				'theme_css' => $themeCss,
				'title' => $conf['sitename'].' '.$defis.' '._CLIENTINFO.' '.$defis.' '._RECHN,
				'logo_src' => img_find('logos/'.$conf['site_logo']),
				'logo_alt' => $conf['sitename'],
				'shopinfo' => $prs->filterContent($conf['shop']['shopinfo'], false, $conf['name']),
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
				'product_text' => $prs->filterContent($text, false, $conf['name']),
				'price_label' => _PREIS_TEXT,
				'price_value' => $pprice.' '.$conf['shop']['valute'],
			]);
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function partners(): void {
	global $db, $conf, $stop, $tpl, $prs;
	if (is_user() && is_active('shop')) {
		$userinfo = getUserInfo();
		$uid = (int)$userinfo['id'];
		$smail = $userinfo['email'];
		$sdom = $userinfo['website'];
		setHead(['title' => _PARTNERINFO]);
		$cont = getModuleNavi(['title' => _PARTNERINFO] + SHOP_NAVI);
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
					$rows = [];
					while ($row = $db->getSqlRow($result)) {
						[$cid, $cuid, $cprod, $cpart, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $uuid, $nick, $pid, $stitle, $pprice] = $row;
						$partsum = $pprice / 100 * $proz;
						$partsumges += $partsum;
						$rows[] = ['id' => $cid, 'cells' => [
							['href' => '#'.$cid, 'title' => $cid, 'text' => $cid, 'is_num' => true],
							['content_html' => user_info($nick)],
							['prefix_html' => getTplTitleTip([
							['label' => _PREIS, 'value' => $pprice.' '.$conf['shop']['valute'], 'is_last' => false],
							['label' => _DATE, 'value' => date(_TIMESTRING, $cregdate), 'is_last' => true],
						]), 'primary_title' => $stitle, 'primary_text' => cutstr($stitle, 35)],
							['text' => $proz.' %'],
							['text' => $partsum.' '.$conf['shop']['valute']],
						]];
						$a++;
					}
					$cont .= $tpl->getHtmlPart('liste', [
						'rows' => $rows,
						'table_open' => ['open' => true, 'sortable' => true, 'headers' => [
							['text' => _ID, 'is_num' => true],
							['text' => _NICKNAME],
							['text' => _PRODUCT],
							['text' => _PERCENT],
							['text' => _SUM],
						]],
						'table_close' => [],
					]);
				}
				$cont .= $tpl->getHtmlPart('liste', [
					'rows' => [[
						'cells' => [
							['text' => (string)$a],
							['text' => $pawebmoney],
							['text' => $papaypal],
							['text' => $partsumges.' '.$conf['shop']['valute']],
							['text' => $parest.' '.$conf['shop']['valute']],
							['text' => $pabek.' '.$conf['shop']['valute']],
						],
					]],
					'table_open' => ['open' => true, 'sortable' => true, 'headers' => [
						['text' => _CLIENTEN],
						['text' => _WEBMONEY],
						['text' => _PAYPAL],
						['text' => _PARTNERGES],
						['text' => _PARTNERREST],
						['text' => _PARTNERBEK],
					]],
					'table_close' => [],
				]);
				$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _C_26.': '.str_replace('[id]', $uid, $conf['shop']['partlink'])]);
				$cont .= $prs->filterContent(str_replace('[id]', $uid, $conf['shop']['partinfo2']), false, $conf['name']);
			}
		} else {
			if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
			$cont .= $prs->filterContent($conf['shop']['partinfo'], false, $conf['name']);
			$cont .= $tpl->getHtmlFrag('title', ['title' => _PARTNERADD]);
			$fields = [
				[_C_PIN, 'text', 'paname', '', _C_PINB, true],
				[_C_PIP, 'text', 'paaddr', '', _C_PIPB, true],
				[_C_TEL, 'text', 'paphone', '', _C_TELB, true],
				[_EMAIL, 'email', 'paemail', $smail, _C_MAILB, true],
				[_SITE, 'url', 'pawebsite', $sdom, _SDOMB, false],
				[_WEBMONEY, 'text', 'pawebmoney', '', _C_WEBMONEYB, false],
				[_PAYPAL, 'text', 'papaypal', '', _C_MAILB, false],
			];
			$rows = '';
			foreach ($fields as [$label, $type, $name, $value, $placeholder, $required]) {
				$rows .= $tpl->getHtmlFrag('form-field-row', [
					'label' => $label.':',
					'field_html' => $tpl->getHtmlFrag('input', [
						'itype' => $type,
						'name_attr' => $name,
						'value_attr' => $value,
						'maxlength_num' => 255,
						'placeholder_text' => $placeholder,
						'is_required' => $required,
					]),
				]);
			}
			$extra = $tpl->getHtmlFrag('hidden', ['name_attr' => 'puid', 'value_attr' => (string)$uid, 'input_attr' => '']);
			$cont .= $tpl->getHtmlPart('form-add', [
				'action' => 'index.php?name='.$conf['name'],
				'extrafields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('shop')]).$rows,
				'name' => $conf['name'],
				'submit' => $tpl->getHtmlFrag('form-submit', ['op' => 'partners_send', 'extra' => $extra, 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _PARTNERSEND]),
			]);
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
			$db->getSqlQuery(
				'INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, :puid, :paname, :paaddr, :paphone, :paemail, :pawebsite, :pawebmoney, :papaypal, \''.time().'\', \'0\', \'0\', \'2\')',
				['puid' => $puid, 'paname' => $paname, 'paaddr' => $paaddr, 'paphone' => $paphone, 'paemail' => $paemail, 'pawebsite' => $pawebsite, 'pawebmoney' => $pawebmoney, 'papaypal' => $papaypal]
			);
			setRedirect('index.php?name='.$conf['name'].'&op=partners');
		} else {
			partners();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

switch ($op) {
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
