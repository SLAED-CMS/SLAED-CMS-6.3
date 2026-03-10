<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function navigate(string $title, string|int $cat=''): string {
	global $conf;
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$cpar = $ncat ? ['cat' => $ncat] : [];
	$home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._SHOP.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($conf['shop']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($conf['shop']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => '', '{%catshow%}' => $catshow]);
}

function shop(): void {
	global $db, $conf, $afile, $home, $user, $op;
	$cwhere = catmids($conf['name'], 'p.cid');
	$unum = getUserNews($conf['shop']['num']);
	$cat = getVar('get', 'cat', 'num');
	$ncat = $cat;
	$params = [];
		if (!$ncat && $op && $conf['shop']['rate']) {
			$caton = 0;
			$field = 'op='.$op.'&';
			if ($op == 'best') {
				$orderby = 'IFNULL((p.totalvotes/NULLIF(p.votes,0)),0) DESC';
				$ntitle = _BEST;
			} else {
				$orderby = 'IFNULL((p.count/NULLIF((TO_DAYS(NOW()) - TO_DAYS(p.time)),0)),0) DESC';
				$ntitle = _POP;
			}
		$order = "WHERE p.time <= NOW() AND p.active != '0' ".$cwhere.' ORDER BY '.$orderby;
		$onum = "time <= NOW() AND active != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
			$orderby = ($op) ? (($op == 'best') ? 'IFNULL((p.totalvotes/NULLIF(p.votes,0)),0) DESC' : 'IFNULL((p.count/NULLIF((TO_DAYS(NOW()) - TO_DAYS(p.time)),0)),0) DESC') : 'p.fix DESC, p.time DESC';
		[$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (p.cid = :ncat1 OR p.assoc REGEXP :ncat_re OR c.parentid = :ncat2) AND p.time <= NOW() AND p.active != '0' ".$cwhere.' ORDER BY '.$orderby;
		$params = ['ncat1' => $ncat, 'ncat_re' => '[[:<:]]'.$ncat.'[[:>:]]', 'ncat2' => $ncat];
		$catid = [];
		$result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parentid = :ncat', ['ncat' => $ncat]);
		while ([$caid] = $db->getSqlRow($result)) $catid[] = $caid;
		unset($result);
		if (isArray($catid)) {
			$caton = 1;
			array_unshift($catid, $ncat);
			$wcid = 'cid IN ('.implode(', ', $catid).')';
		} else {
			$caton = 0;
			$wcid = "cid = '".$ncat."'";
		}
		$onum = '('.$wcid." OR assoc REGEXP '[[:<:]]".$ncat."[[:>:]]') AND time <= NOW() AND active != '0'";
	} else {
		$caton = 1;
		$field = '';
		$hwhere = ($home) ? "AND p.ihome = '1'" : '';
		$hnwhere = ($home) ? "AND ihome = '1'" : '';
		$order = "WHERE p.time <= NOW() AND p.active != '0' ".$hwhere.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC';
		$onum = "time <= NOW() AND active != '0' ".$hnwhere;
		$ntitle = _SHOP;
	}
	setHead(['title' => $ntitle]);
	$cont = '';
	if (!$home || ($home && $conf['shop']['homcat'])) {
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $defis, _SHOP)]);
		if ($caton == 1) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.text, p.bodytext, p.preis, p.acomm, p.com, p.count, p.votes, p.totalvotes, c.title, c.description, c.img FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
	if ($db->getSqlRowCount($result) > 0) {
		$cont .= '<div id="shop"><div id="repkasse">'.show_kasse().'</div></div>';
		$width = 100 / $conf['shop']['bascol'];
		$i = 1;
		$cont .= '<table>';
		while ([$id, $cid, $time, $stitle, $text, $bodytext, $ppreis, $acomm, $pcom, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
			$read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
			
			#### In Bearbeitung
			$uname = $uname ?? '';
			$nick = $nick ?? '';
			$post = isset($conf['shop']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : (_ANONYM ?? ''))) : '';
			$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
			####
			
			$date = ($conf['shop']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
			$reads = ($conf['shop']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
			$comm = ($acomm) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$pcom.'</a>' : '';
			$rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=shop_products_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
			
			#### In Bearbeitung
			$prtitle = empty($opreis) ? _PREIS : _NPREIS;
			$preis = '<span title="'.$prtitle.'" class="sl_shop_price">'.$prtitle.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
			$opreis = empty($opreis) ? '' : '<span title="'._OPREIS.'" class="sl_shop_oprice">'._OPREIS.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
			$discount = empty($discount) ? '' : '<span title="'._DISCOUNT.'" class="sl_shop_discount">'._DISCOUNT.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
			####
			
			$cart = '<a OnClick="AjaxLoad(\'GET\', \'0\', \'kasse\', \'go=2&amp;op=add_kasse&amp;id='.$id.'\', \'\'); AddBasket(\''.$id.'\'); return false;" title="'._SCART.'" class="sl_shop_add">'._SCART.'</a>';
			$kasse = '<a href="index.php?name='.$conf['name'].'&amp;op=kasse" title="'._SCACH.'" class="sl_shop_kasse">'._SCACH.'</a>';
			if (($i - 1) % $conf['shop']['bascol'] == 0) $cont .= '<tr>';
			$cont .= '<td style="width: '.$width.'%;">';
			$cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '', '{%preis%}' => $preis, '{%opreis%}' => $opreis, '{%discount%}' => $discount, '{%cart%}' => $cart, '{%kasse%}' => $kasse]);
			$cont .= '</td>';
			if ($i % $conf['shop']['bascol'] == 0) $cont .= '</tr>';
			$i++;
		}
		$cont .= '</table>';
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_products', 'cid', $onum, $conf['shop']['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function liste(): void {
	global $db, $conf;
	$cwhere = catmids($conf['name'], 'p.cid');
	$listnum = intval($conf['shop']['listnum']);
	$let = getVar('get', 'let', 'let');
	$params = [];
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(p.title) LIKE BINARY :let AND p.time <= NOW() AND p.active != '0'";
		$params['let'] = $let.'%';
	} else {
		$field = 'op=liste&';
		$order = "WHERE p.time <= NOW() AND p.active != '0'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.preis, c.title, c.description FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) '.$order.' '.$cwhere.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$listnum, $params);
	setHead(['title' => _LIST]);
	$cont = navigate(_LIST);
	if ($db->getSqlRowCount($result) > 0) {
		$letter = ($conf['shop']['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _PREIS, '{%date%}' => _DATE]);
		while ([$id, $cid, $time, $title, $preis, $ctitle, $cdesc] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
			$chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$preis = $preis.' '.$conf['shop']['valute'];
			$cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $preis, '{%time%}' => format_time($time)]);
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND active != '0'" : "time <= NOW() AND active != '0'";
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_products', 'cid', $onum, $conf['shop']['nump']);
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $conf, $afile;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$cwhere = catmids($conf['name'], 'p.cid');
	$result = $db->getSqlQuery('SELECT p.cid, p.time, p.title, p.text, p.bodytext, p.preis, p.vote, p.assoc, p.acomm, p.count, p.votes, p.totalvotes, c.title, c.description, c.img FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE p.id = :id AND p.time <= NOW() AND p.active != \'0\' '.$cwhere, ['id' => $id]);
	if ($db->getSqlRowCount($result) == 1) {
		$db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET count = count+1 WHERE id = :id', ['id' => $id]);
		[$cid, $time, $title, $text, $bodytext, $ppreis, $vote, $passoc, $acomm, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result);
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
		$cont = navigate(_SHOP, $conf['shop']['viewcat']);
		$defis = $conf['shop']['defis'] ?? ($conf['defis'] ?? '-');
		if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $defis, _SHOP)]);
		if ($conf['shop']['viewcat']) $cont .= setCategories($conf['name'], $conf['shop']['subcat'], $conf['shop']['catdesc'], 0);
		$cont .= '<div id="shop"><div id="repkasse">'.show_kasse().'</div></div>';
		$text = ($bodytext) ? $text.'<br><br>'.$bodytext : $text;
		$cdesc = ($cdesc) ? $cdesc : $ctitle;
		$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
		$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
		$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
		
		#### In Bearbeitung
		$uname = $uname ?? '';
		$nick = $nick ?? '';
		$post = isset($conf['shop']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : (_ANONYM ?? ''))) : '';
		$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
		####
		
		$date = ($conf['shop']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
		$reads = ($conf['shop']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
		$rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
		$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=shop_products_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
		$favorites = getFavorBtn($id, $conf['name']);
		$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
		$voting = ($vote) ? '<div id="rep'.$conf['name'].'">'.getVoting($vote, $conf['name']).'</div><hr>' : '';
		
		#### In Bearbeitung
		$prtitle = empty($opreis) ? _PREIS : _NPREIS;
		$preis = '<span title="'.$prtitle.'" class="sl_shop_price">'.$prtitle.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
		$opreis = empty($opreis) ? '' : '<span title="'._OPREIS.'" class="sl_shop_oprice">'._OPREIS.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
		$discount = empty($discount) ? '' : '<span title="'._DISCOUNT.'" class="sl_shop_discount">'._DISCOUNT.': '.$ppreis.' '.$conf['shop']['valute'].'</span>';
		####
		
		$cart = '<a OnClick="AjaxLoad(\'GET\', \'0\', \'kasse\', \'go=2&amp;op=add_kasse&amp;id='.$id.'\', \'\'); AddBasket(\''.$id.'\'); return false;" title="'._SCART.'" class="sl_shop_add">'._SCART.'</a>';
		$kasse = '<a href="index.php?name='.$conf['name'].'&amp;op=kasse" title="'._SCACH.'" class="sl_shop_kasse">'._SCACH.'</a>';
		$cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => true], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => filterTextHighlight($title, $word), '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => $voting, '{%preis%}' => $preis, '{%opreis%}' => $opreis, '{%discount%}' => $discount, '{%cart%}' => $cart, '{%kasse%}' => $kasse]);
		if ($conf['shop']['assoc']) {
			$limit = intval($conf['shop']['assocnum']);
			[$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND active != \'0\'', ['id' => $id]));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->getSqlQuery('SELECT id, time, title, text, bodytext FROM '.PREFIX_DB.'_products WHERE cid IN ('.$passoc.') AND id != :id AND time <= NOW() AND active != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['id' => $id]);
				$cont .= setTemplateBasic('assoc-open', ['{%title%}' => _ASPROD]);
				while ([$aid, $time, $title, $hometext, $bodytext] = $db->getSqlRow($result)) {
					$date = ($conf['shop']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
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

function kasse(): void {
	global $db, $conf, $stop;
	if (is_user()) {
		$userinfo = getUserInfo();
		$sid = $userinfo['user_id'];
		$slogin = $userinfo['user_name'];
		$smail = getVar('post', 'smail', 'text', $userinfo['user_email']);
		$sdom = getVar('post', 'sdom', 'url', $userinfo['user_website']);
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
	$form = '<form method="post" action="index.php?name='.$conf['name'].'"><table class="sl_table_form">'
	.'<tr><td>'._C_PIN.':</td><td><input type="text" name="sname" value="'.$sname.'" class="sl_field '.$conf['style'].'" placeholder="'._C_PINB.'" required></td></tr>'
	.'<tr><td>'._C_PIP.':</td><td><input type="text" name="sadr" value="'.$sadr.'" class="sl_field '.$conf['style'].'" placeholder="'._C_PIPB.'" required></td></tr>'
	.'<tr><td>'._C_TEL.':</td><td><input type="text" name="stel" value="'.$stel.'" class="sl_field '.$conf['style'].'" placeholder="'._C_TELB.'" required></td></tr>'
	.'<tr><td>'._C_MAIL.':</td><td><input type="email" name="smail" value="'.$smail.'" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'" required></td></tr>'
	.'<tr><td>'._SDOM.':</td><td><input type="url" name="sdom" value="'.$sdom.'" class="sl_field '.$conf['style'].'" placeholder="'._SDOMB.'"></td></tr>'
	.'<tr><td>'._C_MESSAGE.':</td><td><textarea name="smsg" cols="65" rows="5" class="sl_field '.$conf['style'].'" placeholder="'._C_MESSAGE.'">'.$smsg.'</textarea></td></tr>'
	.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="opi" value="1"><input type="hidden" name="op" value="kasse"><input type="submit" value="'._C_SEND.'" class="sl_but_blue"></td></tr></table></form>';
	setHead(['title' => _C_TITLE]);
	$cont = navigate(_C_TITLE);
	if (!$opi && $cookies) {
		$cont .= '<div id="repkasse">'.show_kasse().'</div>';
		$cont .= setTemplateBasic('title', ['{%title%}' => _C_TITLE]).setTemplateBasic('open').$form.setTemplateBasic('close');
	} elseif ($opi && $cookies) {
		$stop = [];
		checkemail($smail);
		if (!$sname || !$sadr || !$stel || !$smail) {
			$stop[] = _ERROR_ALL;
		}
		if (!$stop) {
			$preistotal = 0;
			$content = '';
			$result = $db->getSqlQuery('SELECT id, title, preis FROM '.PREFIX_DB.'_products WHERE id IN ('.$cookies.')');
			while([$id, $title, $preis] = $db->getSqlRow($result)) {
				$massiv = explode(',', $cookies);
				$i = 0;
				foreach ($massiv as $val) {
					if ($val == $id) $i++;
				}
				$preis = $preis * $i;
				$preistotal += $preis;
				$content .= '<tr><td>'.$id.'</td><td>'.$i.'</td><td>'.$title.'</td><td>'.$preis.' '.$conf['shop']['valute'].'</td></td></tr>';
			}
			$pinfo = '<table style="width: 100%;"><tr><th>'._ID.'</th><th>'._QUANTITY.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th></tr>'.$content.'<tr><td colspan="5"><br><b>'._PARTNERGES.': '.$preistotal.' '.$conf['shop']['valute'].'</b></td></tr></table>';
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
			$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => filterReplaceText(filterMarkdown($conf['shop']['sende'], $conf['name'], false), $conf['name'])]);
		} else {
			$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
			$cont .= '<div id="repkasse">'.show_kasse().'</div>';
			$cont .= setTemplateBasic('open').$form.setTemplateBasic('close');
		}
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '5', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => $stop]);
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
	global $db, $user, $conf;
	if (is_user() && is_active('shop')) {
		$uid = intval($user[0]);
		setHead(['title' => _CLIENTINFO]);
		$cont = navigate(_CLIENTINFO);
		$cont .= getUserNav();
		$result = $db->getSqlQuery('SELECT c.id, c.id_user, c.id_product, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name, p.id, p.title, p.preis FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.id_product) WHERE c.id_user = :user_id ORDER BY c.id ASC', ['user_id' => $uid]);
		if ($db->getSqlRowCount($result) > 0) {
			$cont .= setTemplateBasic('open');
			$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._L_DATE.'</th><th>'._STATUS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
			while([$cid, $cuid, $cprod, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $stitle, $ppreis] = $db->getSqlRow($result)) {
				$website = ($cwebsite) ? '<br>'._SITE.': '.$cwebsite : '';
				$note = ($cinfo) ? '<br>'._NOTE.' : '.$cinfo : '';
				$cenddate = ($cenddate != '0') ? getTimeLeft($cenddate) : _NO;
				$rechn = add_menu('<a href="index.php?name='.$conf['name'].'&amp;op=rech&amp;id='.$cid.'" target="_blank" title="'._RECHN_B.'">'._RECHN_B.'</a>');
				$cont .= '<tr id="'.$cid.'">'
				.'<td><a href="#'.$cid.'" title="'.$cid.'" class="sl_pnum">'.$cid.'</a></td>'
				.'<td>'.title_tip(_PREIS.': '.$ppreis.' '.$conf['shop']['valute'].$website.$note).'<span title="'.$stitle.'">'.cutstr($stitle, 35).'</span></td>'
				.'<td>'.$cenddate.'</td>'
				.'<td>'.ad_status('', $cactive).'</td>'
				.'<td>'.$rechn.'</td></tr>';
			}
			$cont .= '</tbody></table>';
			$cont .= setTemplateBasic('close');
		}
		$cont .= setTemplateBasic('open').filterReplaceText(filterMarkdown($conf['shop']['userinfo'], $conf['name'], false), $conf['name']).setTemplateBasic('close');
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function rech(): void {
	global $db, $conf, $theme;
	if (is_user() && is_active('shop')) {
		$defis = urldecode($conf['defis']);
		$id = getVar('get', 'id', 'num');
		$result = $db->getSqlQuery('SELECT c.id, c.id_user, c.id_product, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, p.id, p.title, p.text, p.preis FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.id_product) WHERE c.id = :id ORDER BY c.id ASC', ['id' => $id]);
		if ($db->getSqlRowCount($result) > 0) {
			[$cid, $cuid, $cprod, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $pid, $stitle, $text, $ppreis] = $db->getSqlRow($result);
			$cont = '<!doctype html>'."\n";
			$cont .= '<html>'."\n";
			$cont .= '<head>'."\n";
			$cont .= '<meta charset="'._CHARSET.'">'."\n";
			if (file_exists('templates/'.$theme.'/theme.css')) {
				$cont .= '<link rel="stylesheet" href="templates/'.$theme.'/theme.css">'."\n";
			}
			$cont .= '<title>'.$conf['sitename'].' '.$defis.' '._CLIENTINFO.' '.$defis.' '._RECHN.'</title></head>'
			.'<body><table style="width: 640px; margin: 5%;"><tr><td colspan="2"><hr></td></tr><tr><td style="width: 40%;"><img src="'.img_find('logos/'.$conf['site_logo']).'" alt="'.$conf['sitename'].'"></td><td style="text-align: right;">'.filterReplaceText(filterMarkdown($conf['shop']['shopinfo'], $conf['name'], false), $conf['name']).'</td></tr><tr><td colspan="2"><hr></td></tr><tr><td colspan="2"><br><p>'._C_PIN.': '.$cname.'<br>'._C_PIP.': '.$cadres.'<br>'._C_TEL.': '.$cphone.'<br>'._C_MAIL.': '.$cemail.'</p></td></tr><tr><td colspan="2"><hr></td></tr><tr><td><b>'._C_NAIM.'</b></td><td style="text-align: right;"><b>'._K_DATE.': '.date(_TIMESTRING, $cregdate).'</b></td></tr><tr><td colspan="2"><hr></td></tr>';
			$cenddate = ($cenddate != '0') ? date(_TIMESTRING, $cenddate) : _UNLIMITED;
			$cont .= '<tr><td>'._PRODUCT.':</td><td style="text-align: right;">'.$stitle.'</td></tr>'
			.'<tr><td>'._SDOM.':</td><td style="text-align: right;">'.$cwebsite.'</td></tr>'
			.'<tr><td>'._NOTE.':</td><td style="text-align: right;">'.$cinfo.'</td></tr>'
			.'<tr><td>'._LIZENS_END.':</td><td style="text-align: right;">'.$cenddate.'</td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2"><b>'._PRODUCT_TEXT.'</b></td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2">'.filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']).'</td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2" style="text-align: right;"><b>'._PREIS_TEXT.': '.$ppreis.' '.$conf['shop']['valute'].'</b></td></tr>'
			.'</table></body></html>';
			echo $cont;
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function partners(): void {
	global $db, $conf, $stop;
	if (is_user() && is_active('shop')) {
		$userinfo = getUserInfo();
		$uid = intval($userinfo['user_id']);
		$smail = $userinfo['user_email'];
		$sdom = $userinfo['user_website'];
		setHead(['title' => _PARTNERINFO]);
		$cont = navigate(_PARTNERINFO);
		$cont .= getUserNav();
		$result = $db->getSqlQuery('SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM '.PREFIX_DB.'_partners WHERE id_user = :user_id', ['user_id' => $uid]);
		if ($db->getSqlRowCount($result) > 0) {
			[$paid, $puid, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result);
			if ($paactive == 2) {
				$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _PARTNERADD_W]);
			} elseif ($paactive == 0) {
				$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _PARTNER_AUS]);
			} else {
				$result = $db->getSqlQuery('SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, u.user_id, u.user_name, p.id, p.title, p.preis FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.id_product) WHERE c.id_partner = :user_id AND c.active != 2 ORDER BY c.id ASC', ['user_id' => $uid]);
				$partsum = $partsumges = $a = 0;
				if ($db->getSqlRowCount($result) > 0) {
					$content = '';
					while([$cid, $cuid, $cprod, $cpart, $proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $uuid, $nick, $pid, $stitle, $ppreis] = $db->getSqlRow($result)) {
						$partsum = $ppreis / 100 * $proz;
						$partsumges += $partsum;
						$content .= '<tr id="'.$cid.'">'
						.'<td><a href="#'.$cid.'" title="'.$cid.'" class="sl_pnum">'.$cid.'</a></td>'
						.'<td>'.user_info($nick).'</td>'
						.'<td>'.title_tip(_PREIS.': '.$ppreis.' '.$conf['shop']['valute'].'<br>'._DATE.' : '.date(_TIMESTRING, $cregdate)).'<span title="'.$stitle.'">'.cutstr($stitle, 35).'</span></td>'
						.'<td>'.$proz.' %</td>'
						.'<td>'.$partsum.' '.$conf['shop']['valute'].'</td></tr>';
						$a++;
					}
					$cont .= setTemplateBasic('open');
					$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PRODUCT.'</th><th>'._PERCENT.'</th><th>'._SUM.'</th></tr></thead><tbody class="sl_table_list_body">'.$content.'</tbody></table>';
					$cont .= setTemplateBasic('close');
				}
				$cont .= setTemplateBasic('open');
				$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._CLIENTEN.'</th><th>'._WEBMONEY.'</th><th>'._PAYPAL.'</th><th>'._PARTNERGES.'</th><th>'._PARTNERREST.'</th><th>'._PARTNERBEK.'</th></tr></thead><tbody class="sl_table_list_body">'
				.'<tr><td>'.$a.'</td><td>'.$pawebmoney.'</td><td>'.$papaypal.'</td>'
				.'<td>'.$partsumges.' '.$conf['shop']['valute'].'</td><td>'.$parest.' '.$conf['shop']['valute'].'</td><td>'.$pabek.' '.$conf['shop']['valute'].'</td></tr></tbody></table>';
				$cont .= setTemplateBasic('close');
				$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _C_26.': '.str_replace('[id]', $uid, $conf['shop']['partlink'])]);
				$cont .= setTemplateBasic('open').filterReplaceText(filterMarkdown(str_replace('[id]', $uid, $conf['shop']['partinfo2']), $conf['name'], false), $conf['name']).setTemplateBasic('close');
			}
		} else {
			if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
			$cont .= setTemplateBasic('open').filterReplaceText(filterMarkdown($conf['shop']['partinfo'], $conf['name'], false), $conf['name']).setTemplateBasic('close');
			$cont .= setTemplateBasic('title', ['{%title%}' => _PARTNERADD]);
			$cont .= setTemplateBasic('open');
			$cont .= '<form method="post" action="index.php?name='.$conf['name'].'"><table class="sl_table_form">'
			.'<tr><td>'._C_PIN.':</td><td><input type="text" name="paname" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_PINB.'" required></td></tr>'
			.'<tr><td>'._C_PIP.':</td><td><input type="text" name="paadres" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_PIPB.'" required></td></tr>'
			.'<tr><td>'._C_TEL.':</td><td><input type="text" name="paphone" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_TELB.'" required></td></tr>'
			.'<tr><td>'._EMAIL.':</td><td><input type="email" value="'.$smail.'" name="paemail" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'" required></td></tr>'
			.'<tr><td>'._SITE.':</td><td><input type="url" value="'.$sdom.'" name="pawebsite" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._SDOMB.'"></td></tr>'
			.'<tr><td>'._WEBMONEY.':</td><td><input type="text" name="pawebmoney" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_WEBMONEYB.'"></td></tr>'
			.'<tr><td>'._PAYPAL.':</td><td><input type="text" name="papaypal" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'"></td></tr>'
			.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="puid" value="'.$uid.'"><input type="hidden" name="op" value="partners_send"><input type="submit" value="'._PARTNERSEND.'" class="sl_but_blue"></td></tr></table></form>';
			$cont .= setTemplateBasic('close');
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
		$paadres = getVar('post', 'paadres', 'text');
		$paphone = getVar('post', 'paphone', 'text');
		$paemail = getVar('post', 'paemail', 'text');
		$pawebsite = getVar('post', 'pawebsite', 'url');
		$pawebmoney = getVar('post', 'pawebmoney', 'text');
		$papaypal = getVar('post', 'papaypal', 'text');
		checkemail($paemail);
		if (!$paname || !$paadres || !$paphone) $stop[] = _ERROR_ALL;
		if (!$stop) {
			$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, :puid, :paname, :paadres, :paphone, :paemail, :pawebsite, :pawebmoney, :papaypal, \''.time().'\', \'0\', \'0\', \'2\')', ['puid' => $puid, 'paname' => $paname, 'paadres' => $paadres, 'paphone' => $paphone, 'paemail' => $paemail, 'pawebsite' => $pawebsite, 'pawebmoney' => $pawebmoney, 'papaypal' => $papaypal]);
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
