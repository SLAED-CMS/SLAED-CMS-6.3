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
include('config/config_shop.php');

function navigate($title, $cat='') {
	global $conf, $confso;
	$ncat = getVar('get', 'cat', 'num');
	$ncat = ($ncat) ? '&cat='.$ncat : '';
	$home = '<a href="'.getHref(array('name='.$conf['name'], '', '', '', '', '', '', '')).'" title="'._SHOP.'" class="sl_but_navi">'._HOME.'</a>';
	$best = ($confso['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=best', '', '', '', '', '', '', '')).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
	$pop = ($confso['rate']) ? '<a href="'.getHref(array('name='.$conf['name'].$ncat.'&op=pop', '', '', '', '', '', '', '')).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
	$liste = '<a href="'.getHref(array('name='.$conf['name'].'&op=liste', '', '', '', '', '', '', '')).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
	$catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
	return setTemplateBasic('navi', array('{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => '', '{%catshow%}' => $catshow));
}

function shop() {
	global $prefix, $db, $conf, $confso, $admin_file, $home, $user, $op;
	$cwhere = catmids($conf['name'], 'p.cid');
	$unum = user_news($user[3], $confso['num']);
	$ncat = getVar('get', 'cat', 'num');
	if (!$ncat && $op && $confso['rate']) {
		$caton = 0;
		$field = 'op='.$op.'&';
		if ($op == 'best') {
			$orderby = '(p.totalvotes/p.votes) DESC';
			$ntitle = _BEST;
		} else {
			$orderby = '(p.count/(TO_DAYS(NOW()) - TO_DAYS(p.time))) DESC';
			$ntitle = _POP;
		}
		$order = "WHERE p.time <= NOW() AND p.active != '0' ".$cwhere." ORDER BY ".$orderby;
		$onum = "time <= NOW() AND active != '0'";
	} elseif ($ncat) {
		$field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
		$orderby = ($op) ? (($op == 'best') ? '(p.totalvotes/p.votes) DESC' : '(p.count/(TO_DAYS(NOW()) - TO_DAYS(p.time))) DESC') : 'p.fix DESC, p.time DESC';
		list($ctitle) = $db->sql_fetchrow($db->sql_query("SELECT title FROM ".$prefix."_categories WHERE id = '".$ncat."'"));
		$ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
		$order = "WHERE (p.cid = '".$ncat."' OR p.assoc REGEXP '[[:<:]]".$ncat."[[:>:]]' OR c.parentid = '".$ncat."') AND p.time <= NOW() AND p.active != '0' ".$cwhere." ORDER BY ".$orderby;
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
		$onum = "(".$wcid." OR assoc REGEXP '[[:<:]]".$ncat."[[:>:]]') AND time <= NOW() AND active != '0'";
	} else {
		$caton = 1;
		$field = '';
		$hwhere = ($home) ? "AND p.ihome = '1'" : "";
		$hnwhere = ($home) ? "AND ihome = '1'" : "";
		$order = "WHERE p.time <= NOW() AND p.active != '0' ".$hwhere." ".$cwhere." ORDER BY p.fix DESC, p.time DESC";
		$onum = "time <= NOW() AND active != '0' ".$hnwhere;
		$ntitle = _SHOP;
	}
	head();
	$cont = '';
	if (!$home || ($home && $confso['homcat'])) {
		$cont .= navigate($ntitle, $caton);
		if ($ncat) $cont .= setTemplateBasic('cat-navi', array('{%crumbs%}' => catlink($conf['name'], $ncat, $confso['defis'], _SHOP)));
		if ($caton == 1) $cont .= setCategories($conf['name'], $confso['subcat'], $confso['catdesc'], $ncat);
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $unum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT p.id, p.cid, p.time, p.title, p.text, p.bodytext, p.preis, p.acomm, p.com, p.count, p.votes, p.totalvotes, c.title, c.description, c.img FROM ".$prefix."_products AS p LEFT JOIN ".$prefix."_categories AS c ON (p.cid = c.id) ".$order." LIMIT ".$offset.", ".$unum);
	if ($db->sql_numrows($result) > 0) {
		$cont .= '<div id="shop"><div id="repkasse">'.show_kasse().'</div></div>';
		$width_tab = 100 / $confso['bascol'];
		$i = 1;
		$cont .= '<table>';
		while (list($id, $cid, $time, $stitle, $text, $bodytext, $ppreis, $acomm, $pcom, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $stitle, $text.$bodytext, $ctitle, $cdesc, $cimg));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
			$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
			$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
			$title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
			$read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
			
			#### In Bearbeitung
			$post = isset($confso['autor']) ? (($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym'])) : '';
			$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
			####
			
			$date = ($confso['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</span>' : '';
			$reads = ($confso['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
			$comm = ($acomm) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$pcom.'</a>' : '';
			$rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$admin_file.'.php?op=shop_products_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
			
			#### In Bearbeitung
			$prtitle = empty($opreis) ? _PREIS : _NPREIS;
			$preis = '<span title="'.$prtitle.'" class="sl_shop_price">'.$prtitle.': '.$ppreis.' '.$confso['valute'].'</span>';
			$opreis = empty($opreis) ? '' : '<span title="'._OPREIS.'" class="sl_shop_oprice">'._OPREIS.': '.$ppreis.' '.$confso['valute'].'</span>';
			$discount = empty($discount) ? '' : '<span title="'._DISCOUNT.'" class="sl_shop_discount">'._DISCOUNT.': '.$ppreis.' '.$confso['valute'].'</span>';
			####
			
			$cart = '<a OnClick="AjaxLoad(\'GET\', \'0\', \'kasse\', \'go=2&amp;op=add_kasse&amp;id='.$id.'\', \'\'); AddBasket(\''.$id.'\'); return false;" title="'._SCART.'" class="sl_shop_add">'._SCART.'</a>';
			$kasse = '<a href="index.php?name='.$conf['name'].'&amp;op=kasse" title="'._SCACH.'" class="sl_shop_kasse">'._SCACH.'</a>';
			if (($i - 1) % $confso['bascol'] == 0) $cont .= '<tr>';
			$cont .= '<td style="width: '.$width_tab.'%;">';
			$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => bb_decode($text, $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '', '{%preis%}' => $preis, '{%opreis%}' => $opreis, '{%discount%}' => $discount, '{%cart%}' => $cart, '{%kasse%}' => $kasse));
			$cont .= '</td>';
			if ($i % $confso['bascol'] == 0) $cont .= '</tr>';
			$i++;
		}
		$cont .= '</table>';
		$cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_products', 'cid', $onum, $confso['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function liste() {
	global $prefix, $db, $conf, $confu, $confso;
	$cwhere = catmids($conf['name'], 'p.cid');
	$listnum = intval($confso['listnum']);
	$let = getVar('get', 'let', 'let');
	if ($let) {
		$field = 'op=liste&let='.urlencode($let).'&';
		$order = "WHERE UCASE(p.title) LIKE BINARY '".$let."%' AND p.time <= NOW() AND p.active != '0'";
	} else {
		$field = 'op=liste&';
		$order = "WHERE p.time <= NOW() AND p.active != '0'";
	}
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $listnum;
	$offset = intval($offset);
	$result = $db->sql_query("SELECT p.id, p.cid, p.time, p.title, p.preis, c.title, c.description FROM ".$prefix."_products AS p LEFT JOIN ".$prefix."_categories AS c ON (p.cid = c.id) ".$order." ".$cwhere." ORDER BY p.fix DESC, p.time DESC LIMIT ".$offset.", ".$listnum);
	head();
	$cont = navigate(_LIST);
	if ($db->sql_numrows($result) > 0) {
		$letter = ($confso['letter']) ? letter($conf['name']) : '';
		$cont .= setTemplateBasic('liste-open', array('{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _PREIS, '{%date%}' => _DATE));
		while (list($id, $cid, $time, $title, $preis, $ctitle, $cdesc) = $db->sql_fetchrow($result)) {
			$thref = getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $title, '', $ctitle, $cdesc, ''));
			$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, ''));
			$title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
			$cdesc = ($cdesc) ? $cdesc : $ctitle;
			$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
			$preis = $preis.' '.$confso['valute'];
			$cont .= setTemplateBasic('liste-basic', array('{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $preis, '{%time%}' => format_time($time)));
		}
		$cont .= setTemplateBasic('liste-close');
		$onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND active != '0'" : "time <= NOW() AND active != '0'";
		$cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_products', 'cid', $onum, $confso['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function view() {
	global $prefix, $db, $conf, $confso, $admin_file;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$cwhere = catmids($conf['name'], 'p.cid');
	$result = $db->sql_query("SELECT p.cid, p.time, p.title, p.text, p.bodytext, p.preis, p.vote, p.assoc, p.acomm, p.count, p.votes, p.totalvotes, c.title, c.description, c.img FROM ".$prefix."_products AS p LEFT JOIN ".$prefix."_categories AS c ON (p.cid = c.id) WHERE p.id = '".$id."' AND p.time <= NOW() AND p.active != '0' ".$cwhere);
	if ($db->sql_numrows($result) == 1) {
		$db->sql_query("UPDATE ".$prefix."_products SET count = count+1 WHERE id = '".$id."'");
		list($cid, $time, $title, $text, $bodytext, $ppreis, $vote, $passoc, $acomm, $counter, $votes, $totalvotes, $ctitle, $cdesc, $cimg) = $db->sql_fetchrow($result);
		$chref = getHref(array('name='.$conf['name'].'&cat='.$cid, '', '', '', '', $ctitle, $cdesc, $cimg));
		head();
		$cont = navigate(_SHOP, $confso['viewcat']);
		if ($cid) $cont .= setTemplateBasic('cat-navi', array('{%crumbs%}' => catlink($conf['name'], $cid, $confso['defis'], _SHOP)));
		if ($confso['viewcat']) $cont .= setCategories($conf['name'], $confso['subcat'], $confso['catdesc'], 0);
		$cont .= '<div id="shop"><div id="repkasse">'.show_kasse().'</div></div>';
		$text = ($bodytext) ? $text.'<br><br>'.$bodytext : $text;
		$cdesc = ($cdesc) ? $cdesc : $ctitle;
		$ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
		$cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
		$cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
		
		#### In Bearbeitung
		$post = isset($confso['autor']) ? (($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym'])) : '';
		$post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
		####
		
		$date = ($confso['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</span>' : '';
		$reads = ($confso['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
		$rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
		$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$admin_file.'.php?op=shop_products_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=shop_products_admin&amp;typ=d&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
		$favorites = favorview($id, $conf['name']);
		$goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
		$voting = ($vote) ? '<div id="rep'.$conf['name'].'">'.getVoting($vote, $conf['name']).'</div><hr>' : '';
		
		#### In Bearbeitung
		$prtitle = empty($opreis) ? _PREIS : _NPREIS;
		$preis = '<span title="'.$prtitle.'" class="sl_shop_price">'.$prtitle.': '.$ppreis.' '.$confso['valute'].'</span>';
		$opreis = empty($opreis) ? '' : '<span title="'._OPREIS.'" class="sl_shop_oprice">'._OPREIS.': '.$ppreis.' '.$confso['valute'].'</span>';
		$discount = empty($discount) ? '' : '<span title="'._DISCOUNT.'" class="sl_shop_discount">'._DISCOUNT.': '.$ppreis.' '.$confso['valute'].'</span>';
		####
		
		$cart = '<a OnClick="AjaxLoad(\'GET\', \'0\', \'kasse\', \'go=2&amp;op=add_kasse&amp;id='.$id.'\', \'\'); AddBasket(\''.$id.'\'); return false;" title="'._SCART.'" class="sl_shop_add">'._SCART.'</a>';
		$kasse = '<a href="index.php?name='.$conf['name'].'&amp;op=kasse" title="'._SCACH.'" class="sl_shop_kasse">'._SCACH.'</a>';
		$cont .= setTemplateBasic('basic', array('{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => search_color($title, $word), '{%text%}' => search_color(bb_decode($text, $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => $voting, '{%preis%}' => $preis, '{%opreis%}' => $opreis, '{%discount%}' => $discount, '{%cart%}' => $cart, '{%kasse%}' => $kasse));
		if ($confso['assoc']) {
			$limit = intval($confso['assocnum']);
			list($count) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_products WHERE cid IN (".$passoc.") AND id != '".$id."' AND time <= NOW() AND active != '0'"));
			if ($count >= $limit) {
				$random = mt_rand(0, $count - $limit);
				$result = $db->sql_query("SELECT id, time, title, text, bodytext FROM ".$prefix."_products WHERE cid IN (".$passoc.") AND id != '".$id."' AND time <= NOW() AND active != '0' ORDER BY time DESC LIMIT ".$random.", ".$limit);
				$cont .= setTemplateBasic('assoc-open', array('{%title%}' => _ASPROD));
				while (list($aid, $time, $title, $hometext, $bodytext) = $db->sql_fetchrow($result)) {
					$date = ($confso['date']) ? '<span title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</span>' : '';
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

function kasse() {
	global $prefix, $db, $conf, $confu, $confso, $stop;
	if (is_user()) {
		$userinfo = getusrinfo();
		$sender_id = $userinfo['user_id'];
		$sender_login = $userinfo['user_name'];
		$sender_email = getVar('post', 'sender_email', 'text', $userinfo['user_email']);
		$sender_dom = getVar('post', 'sender_dom', 'url', $userinfo['user_website']);
	} else {
		$sender_id = 0;
		$sender_login = $confu['anonym'];
		$sender_email = getVar('post', 'sender_email', 'text');
		$sender_dom = getVar('post', 'sender_dom', 'url', 'http://');
	}
	$sender_name = getVar('post', 'sender_name', 'text');
	$sender_adr = getVar('post', 'sender_adr', 'text');
	$sender_tel = getVar('post', 'sender_tel', 'text');
	$sender_message = getVar('post', 'sender_message', 'text');
	$opi = getVar('post', 'opi', 'num');
	$cookies = isset($_COOKIE['shop']) ? ((preg_match('/[^0-9,]/', base64_decode($_COOKIE['shop']))) ? '' : base64_decode($_COOKIE['shop'])) : '';
	$id_partner = isset($_COOKIE['part']) ? intval($_COOKIE['part']) : '';
	$stop = (!$cookies) ? _SERRORP : '';
	$form = '<form method="post" action="index.php?name='.$conf['name'].'"><table class="sl_table_form">'
	.'<tr><td>'._C_PIN.':</td><td><input type="text" name="sender_name" value="'.$sender_name.'" class="sl_field '.$conf['style'].'" placeholder="'._C_PINB.'" required></td></tr>'
	.'<tr><td>'._C_PIP.':</td><td><input type="text" name="sender_adr" value="'.$sender_adr.'" class="sl_field '.$conf['style'].'" placeholder="'._C_PIPB.'" required></td></tr>'
	.'<tr><td>'._C_TEL.':</td><td><input type="text" name="sender_tel" value="'.$sender_tel.'" class="sl_field '.$conf['style'].'" placeholder="'._C_TELB.'" required></td></tr>'
	.'<tr><td>'._C_MAIL.':</td><td><input type="email" name="sender_email" value="'.$sender_email.'" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'" required></td></tr>'
	.'<tr><td>'._SDOM.':</td><td><input type="url" name="sender_dom" value="'.$sender_dom.'" class="sl_field '.$conf['style'].'" placeholder="'._SDOMB.'"></td></tr>'
	.'<tr><td>'._C_MESSAGE.':</td><td><textarea name="sender_message" cols="65" rows="5" class="sl_field '.$conf['style'].'" placeholder="'._C_MESSAGE.'">'.$sender_message.'</textarea></td></tr>'
	.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="opi" value="1"><input type="hidden" name="op" value="kasse"><input type="submit" value="'._C_SEND.'" class="sl_but_blue"></td></tr></table></form>';
	head();
	$cont = navigate(_C_TITLE);
	if (!$opi && $cookies) {
		$cont .= '<div id="repkasse">'.show_kasse().'</div>';
		$cont .= setTemplateBasic('title', array('{%title%}' => _C_TITLE)).setTemplateBasic('open').$form.setTemplateBasic('close');
	} elseif ($opi && $cookies) {
		$stop = array();
		checkemail($sender_email);
		if (!$sender_name || !$sender_adr || !$sender_tel || !$sender_email) {
			$stop[] = _ERROR_ALL;
		} elseif ($stop) {
			$stop[] = $stop;
		}
		if (!$stop) {
			$preistotal = 0;
			$content = '';
			$result = $db->sql_query("SELECT id, title, preis FROM ".$prefix."_products WHERE id IN (".$cookies.")");
			while(list($id, $title, $preis) = $db->sql_fetchrow($result)) {
				$massiv = explode(',', $cookies);
				$i = 0;
				foreach ($massiv as $val) {
					if ($val == $id) $i++;
				}
				$preis = $preis * $i;
				$preistotal += $preis;
				$content .= '<tr><td>'.$id.'</td><td>'.$i.'</td><td>'.$title.'</td><td>'.$preis.' '.$confso['valute'].'</td></td></tr>';
			}
			$pinfo = '<table style="width: 100%;"><tr><th>'._ID.'</th><th>'._QUANTITY.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th></tr>'.$content.'<tr><td colspan="5"><br><b>'._PARTNERGES.': '.$preistotal.' '.$confso['valute'].'</b></td></tr></table>';
			if ($confso['mailsend']) {
				$amail = ($confso['mail']) ? $confso['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $conf['sitename'].' - '._C_TITLE.'<br><br>';
				$msg .= $pinfo.'<br><br>';
				$msg .= '<b>'._PERSONALINFO.'</b><br><br>';
				$msg .= _NICKNAME.': '.$sender_login.'<br>';
				$msg .= _C_PIN.': '.$sender_name.'<br>';
				$msg .= _C_PIP.': '.$sender_adr.'<br>';
				$msg .= _C_TEL.': '.$sender_tel.'<br>';
				$msg .= _C_MAIL.': '.$sender_email.'<br>';
				$msg .= _SITEURL.': '.$sender_dom.'<br>';
				$msg .= _C_MESSAGE.': '.$sender_message;
				mail_send($amail, $sender_email, $subject, $msg, 1, 1);
			}
			if ($confso['mailuser']) {
				$amail = ($confso['mail']) ? $confso['mail'] : $conf['adminmail'];
				$subject = $conf['sitename'].' - '._C_TITLE;
				$msg = $conf['sitename'].' - '._C_TITLE.'<br><br>';
				$msg .= bb_decode($confso['sende'], $conf['name']).'<br><br>';
				$msg .= $pinfo.'<br><br>';
				$msg .= '<b>'._PERSONALINFO.'</b><br><br>';
				$msg .= _NICKNAME.': '.$sender_login.'<br>';
				$msg .= _C_PIN.': '.$sender_name.'<br>';
				$msg .= _C_PIP.': '.$sender_adr.'<br>';
				$msg .= _C_TEL.': '.$sender_tel.'<br>';
				$msg .= _C_MAIL.': '.$sender_email.'<br>';
				$msg .= _SDOM.': '.$sender_dom.'<br>';
				$msg .= _C_MESSAGE.': '.$sender_message;
				mail_send($sender_email, $amail, $subject, $msg, 0, 3);
			}
			$massiv = explode(',', $cookies);
			foreach ($massiv as $val) {
				if ($val != '') {
					$sender_regdate = time();
					$db->sql_query("INSERT INTO ".$prefix."_clients VALUES(NULL, '".$sender_id."', '".$val."', '".$id_partner."', '0', '".$sender_name."', '".$sender_adr."', '".$sender_tel."', '".$sender_email."', '".$sender_dom."', '".$sender_regdate."', '0', '0', '2')");
				}
			}
			setcookie('shop', false);
			setcookie('part', false);
			update_points(39);
			$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => bb_decode($confso['sende'], $conf['name'])));
		} else {
			$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
			$cont .= '<div id="repkasse">'.show_kasse().'</div>';
			$cont .= setTemplateBasic('open').$form.setTemplateBasic('close');
		}
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '5', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => $stop));
	}
	echo $cont;
	foot();
}

function part() {
	global $conf, $confso;
	$id = getVar('get', 'id', 'num');
	if ($id) setcookie('part', $id, time() + $confso['part_t']);
	header('Location: index.php?name='.$conf['name']);
}

function clients() {
	global $prefix, $db, $user, $conf, $confso;
	if (is_user() && is_active('shop')) {
		$user_id = intval($user[0]);
		head();
		$cont = navigate(_CLIENTINFO);
		$cont .= navi();
		$result = $db->sql_query("SELECT c.id, c.id_user, c.id_product, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name, p.id, p.title, p.preis FROM ".$prefix."_clients AS c LEFT JOIN ".$prefix."_users AS u ON (u.user_id = c.id_user) LEFT JOIN ".$prefix."_products AS p ON (p.id = c.id_product) WHERE c.id_user = '".$user_id."' ORDER BY c.id ASC");
		if ($db->sql_numrows($result) > 0) {
			$cont .= setTemplateBasic('open');
			$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._L_DATE.'</th><th>'._STATUS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
			while(list($cid, $cid_user, $cid_product, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $user_id, $user_name, $pid, $stitle, $ppreis) = $db->sql_fetchrow($result)) {
				$website = ($cwebsite) ? '<br>'._SITE.': '.$cwebsite : '';
				$note = ($cinfo) ? '<br>'._NOTE.' : '.$cinfo : '';
				$cenddate = ($cenddate != '0') ? rest_time($cenddate) : _NO;
				$rechn = add_menu('<a href="index.php?name='.$conf['name'].'&amp;op=rech&amp;id='.$cid.'" target="_blank" title="'._RECHN_B.'">'._RECHN_B.'</a>');
				$cont .= '<tr id="'.$cid.'">'
				.'<td><a href="#'.$cid.'" title="'.$cid.'" class="sl_pnum">'.$cid.'</a></td>'
				.'<td>'.title_tip(_PREIS.': '.$ppreis.' '.$confso['valute'].$website.$note).'<span title="'.$stitle.'">'.cutstr($stitle, 35).'</span></td>'
				.'<td>'.$cenddate.'</td>'
				.'<td>'.ad_status('', $cactive).'</td>'
				.'<td>'.$rechn.'</td></tr>';
			}
			$cont .= '</tbody></table>';
			$cont .= setTemplateBasic('close');
		}
		$cont .= setTemplateBasic('open').bb_decode($confso['userinfo'], $conf['name']).setTemplateBasic('close');
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
		exit;
	}
}

function rech() {
	global $prefix, $db, $conf, $confso, $theme;
	if (is_user() && is_active('shop')) {
		$defis = urldecode($conf['defis']);
		$id = getVar('get', 'id', 'num');
		$result = $db->sql_query("SELECT c.id, c.id_user, c.id_product, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, p.id, p.title, p.text, p.preis FROM ".$prefix."_clients AS c LEFT JOIN ".$prefix."_products AS p ON (p.id = c.id_product) WHERE c.id = '".$id."' ORDER BY c.id ASC");
		if ($db->sql_numrows($result) > 0) {
			list($cid, $cid_user, $cid_product, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $pid, $stitle, $text, $ppreis) = $db->sql_fetchrow($result);
			$cont = '<!doctype html>'."\n";
			$cont .= '<html>'."\n";
			$cont .= '<head>'."\n";
			$cont .= '<meta charset="'._CHARSET.'">'."\n";
			if (file_exists('templates/'.$theme.'/theme.css')) {
				$cont .= '<link rel="stylesheet" href="templates/'.$theme.'/theme.css">'."\n";
			}
			$cont .= '<title>'.$conf['sitename'].' '.$defis.' '._CLIENTINFO.' '.$defis.' '._RECHN.'</title></head>'
			.'<body><table style="width: 640px; margin: 5%;"><tr><td colspan="2"><hr></td></tr><tr><td style="width: 40%;"><img src="'.img_find('logos/'.$conf['site_logo']).'" alt="'.$conf['sitename'].'"></td><td style="text-align: right;">'.bb_decode($confso['shopinfo'], $conf['name']).'</td></tr><tr><td colspan="2"><hr></td></tr><tr><td colspan="2"><br><p>'._C_PIN.': '.$cname.'<br>'._C_PIP.': '.$cadres.'<br>'._C_TEL.': '.$cphone.'<br>'._C_MAIL.': '.$cemail.'</p></td></tr><tr><td colspan="2"><hr></td></tr><tr><td><b>'._C_NAIM.'</b></td><td style="text-align: right;"><b>'._K_DATE.': '.date(_TIMESTRING, $cregdate).'</b></td></tr><tr><td colspan="2"><hr></td></tr>';
			$cenddate = ($cenddate != '0') ? date(_TIMESTRING, $cenddate) : _UNLIMITED;
			$cont .= '<tr><td>'._PRODUCT.':</td><td style="text-align: right;">'.$stitle.'</td></tr>'
			.'<tr><td>'._SDOM.':</td><td style="text-align: right;">'.$cwebsite.'</td></tr>'
			.'<tr><td>'._NOTE.':</td><td style="text-align: right;">'.$cinfo.'</td></tr>'
			.'<tr><td>'._LIZENS_END.':</td><td style="text-align: right;">'.$cenddate.'</td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2"><b>'._PRODUCT_TEXT.'</b></td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2">'.bb_decode($text, $conf['name']).'</td></tr>'
			.'<tr><td colspan="2"><hr></td></tr>'
			.'<tr><td colspan="2" style="text-align: right;"><b>'._PREIS_TEXT.': '.$ppreis.' '.$confso['valute'].'</b></td></tr>'
			.'</table></body></html>';
			echo $cont;
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
		exit;
	}
}

function partners() {
	global $prefix, $db, $conf, $confso, $stop;
	if (is_user() && is_active('shop')) {
		$userinfo = getusrinfo();
		$user_id = intval($userinfo['user_id']);
		$sender_email = $userinfo['user_email'];
		$sender_dom = $userinfo['user_website'];
		head();
		$cont = navigate(_PARTNERINFO);
		$cont .= navi();
		$result = $db->sql_query("SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM ".$prefix."_partners WHERE id_user = '".$user_id."'");
		if ($db->sql_numrows($result) > 0) {
			list($paid, $paid_user, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive) = $db->sql_fetchrow($result);
			if ($paactive == 2) {
				$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _PARTNERADD_W));
			} elseif ($paactive == 0) {
				$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => _PARTNER_AUS));
			} else {
				$result = $db->sql_query("SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, u.user_id, u.user_name, p.id, p.title, p.preis FROM ".$prefix."_clients AS c LEFT JOIN ".$prefix."_users AS u ON (u.user_id = c.id_user) LEFT JOIN ".$prefix."_products AS p ON (p.id = c.id_product) WHERE c.id_partner = '".$user_id."' AND c.active != 2 ORDER BY c.id ASC");
				$partsum = $partsumges = $a = 0;
				if ($db->sql_numrows($result) > 0) {
					$content = '';
					while(list($cid, $cid_user, $cid_product, $cid_partner, $cpartner_proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $uuser_id, $user_name, $pid, $stitle, $ppreis) = $db->sql_fetchrow($result)) {
						$partsum = $ppreis / 100 * $cpartner_proz;
						$partsumges += $partsum;
						$content .= '<tr id="'.$cid.'">'
						.'<td><a href="#'.$cid.'" title="'.$cid.'" class="sl_pnum">'.$cid.'</a></td>'
						.'<td>'.user_info($user_name).'</td>'
						.'<td>'.title_tip(_PREIS.': '.$ppreis.' '.$confso['valute'].'<br>'._DATE.' : '.date(_TIMESTRING, $cregdate)).'<span title="'.$stitle.'">'.cutstr($stitle, 35).'</span></td>'
						.'<td>'.$cpartner_proz.' %</td>'
						.'<td>'.$partsum.' '.$confso['valute'].'</td></tr>';
						$a++;
					}
					$cont .= setTemplateBasic('open');
					$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PRODUCT.'</th><th>'._PERCENT.'</th><th>'._SUM.'</th></tr></thead><tbody class="sl_table_list_body">'.$content.'</tbody></table>';
					$cont .= setTemplateBasic('close');
				}
				$cont .= setTemplateBasic('open');
				$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._CLIENTEN.'</th><th>'._WEBMONEY.'</th><th>'._PAYPAL.'</th><th>'._PARTNERGES.'</th><th>'._PARTNERREST.'</th><th>'._PARTNERBEK.'</th></tr></thead><tbody class="sl_table_list_body">'
				.'<tr><td>'.$a.'</td><td>'.$pawebmoney.'</td><td>'.$papaypal.'</td>'
				.'<td>'.$partsumges.' '.$confso['valute'].'</td><td>'.$parest.' '.$confso['valute'].'</td><td>'.$pabek.' '.$confso['valute'].'</td></tr></tbody></table>';
				$cont .= setTemplateBasic('close');
				$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _C_26.': '.str_replace('[id]', $user_id, $confso['partlink'])));
				$cont .= setTemplateBasic('open').bb_decode(str_replace('[id]', $user_id, $confso['partinfo2']), $conf['name']).setTemplateBasic('close');
			}
		} else {
			if ($stop) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop));
			$cont .= setTemplateBasic('open').bb_decode($confso['partinfo'], $conf['name']).setTemplateBasic('close');
			$cont .= setTemplateBasic('title', array('{%title%}' => _PARTNERADD));
			$cont .= setTemplateBasic('open');
			$cont .= '<form method="post" action="index.php?name='.$conf['name'].'"><table class="sl_table_form">'
			.'<tr><td>'._C_PIN.':</td><td><input type="text" name="paname" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_PINB.'" required></td></tr>'
			.'<tr><td>'._C_PIP.':</td><td><input type="text" name="paadres" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_PIPB.'" required></td></tr>'
			.'<tr><td>'._C_TEL.':</td><td><input type="text" name="paphone" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_TELB.'" required></td></tr>'
			.'<tr><td>'._EMAIL.':</td><td><input type="email" value="'.$sender_email.'" name="paemail" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'" required></td></tr>'
			.'<tr><td>'._SITE.':</td><td><input type="url" value="'.$sender_dom.'" name="pawebsite" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._SDOMB.'"></td></tr>'
			.'<tr><td>'._WEBMONEY.':</td><td><input type="text" name="pawebmoney" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_WEBMONEYB.'"></td></tr>'
			.'<tr><td>'._PAYPAL.':</td><td><input type="text" name="papaypal" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._C_MAILB.'"></td></tr>'
			.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="paid_user" value="'.$user_id.'"><input type="hidden" name="op" value="partners_send"><input type="submit" value="'._PARTNERSEND.'" class="sl_but_blue"></td></tr></table></form>';
			$cont .= setTemplateBasic('close');
		}
		echo $cont;
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
		exit;
	}
}

function partners_send() {
	global $prefix, $db, $user, $conf, $stop;
	if (is_user() && is_active('shop')) {
		$paid_user = getVar('post', 'paid_user', 'num');
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
			$db->sql_query("INSERT INTO ".$prefix."_partners VALUES(NULL, '".$paid_user."', '".$paname."', '".$paadres."', '".$paphone."', '".$paemail."', '".$pawebsite."', '".$pawebmoney."', '".$papaypal."', '".time()."', '0', '0', '2')");
			header('Location: index.php?name='.$conf['name'].'&op=partners');
		} else {
			partners();
		}
	} else {
		header('Location: index.php?name='.$conf['name']);
		exit;
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
?>