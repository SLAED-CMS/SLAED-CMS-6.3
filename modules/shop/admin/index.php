<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined("ADMIN_FILE") || !is_admin_modul("shop")) die("Illegal file access");


function shop_navi() {
	global $admin_file;
	panel();
	$narg = func_get_args();
	$ops = array("shop_clients", "shop_products", "shop_partners", "shop_export", "shop_conf", "shop_info");
	$lang = array(_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT." / "._IMPORT, _PREFERENCES, _INFO);
	if ($narg[0] == 0) {
		$sops = array("shop_clients", "shop_clients&amp;status=1", "shop_clients&amp;status=2", "shop_clients_add");
		$slang = array(_NEW, _AKTIVE, _DEAKTIVE, _ADD);
	} elseif ($narg[0] == 1) {
		$sops = array("shop_products", "shop_products&amp;status=1", "shop_products_add");
		$slang = array(_AKTIVE, _DEAKTIVE, _ADD);
	} elseif ($narg[0] == 2) {
		$sops = array("shop_partners", "shop_partners&amp;status=1", "shop_partners&amp;status=2", "shop_partners_add");
		$slang = array(_NEW, _AKTIVE, _DEAKTIVE, _ADD);
	} elseif ($narg[0] == 3) {
		$sops = array("", "");
		$slang = array(_EXPORT, _IMPORT);
	}
	$search = "<form method=\"post\" action=\"".$admin_file.".php\">"._SEARCH.": <select name=\"search\">";
	$priv = array(_ID, _NICKNAME, _CLIENTNAME, _EMAIL, _SITE);
	$psearch = getVar('post', 'search', 'num');
	$pcsearch = getVar('post', 'csearch', 'text');
	foreach ($priv as $key => $value) {
		$sort = $key + 1;
		$sel = ($psearch == $sort || (!$psearch && $sort == 2)) ? " selected" : "";
		$search .= "<option value=\"".$sort."\"".$sel.">".$value."</option>";
	}
	$search .= "</select> ".get_user_search("csearch", $pcsearch, "30")." <input type=\"hidden\" name=\"op\" value=\"shop_clients\"><input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\"></form>";
	$search = tpl_eval("searchbox", $search);
	return navi_gen(_SHOP, "shop.png", $search, $ops, $lang, $sops, $slang, $narg[0], $narg[1], $narg[2], $narg[3], $narg[4]);
}

function shop_clients() {
	global $db, $admin_file, $confso, $confu;
	$csearch = getVar('post', 'csearch', 'text');
	$tsearch = getVar('post', 'search', 'num');
	head();
	if ($tsearch == 1 && $csearch) {
		$sqlstring = "AND user_id LIKE'%".$csearch."%' ORDER BY user_id ASC";
	} elseif ($tsearch == 2 && $csearch) {
		$sqlstring = "AND user_name LIKE '%".$csearch."%' ORDER BY user_name ASC";
	} elseif ($tsearch == 3 && $csearch) {
		$sqlstring = "AND name LIKE '%".$csearch."%' ORDER BY name ASC";
	} elseif ($tsearch == 4 && $csearch) {
		$sqlstring = "AND email LIKE '%".$csearch."%' ORDER BY email ASC";
	} elseif ($tsearch == 5 && $csearch) {
		$sqlstring = "AND website LIKE '%".$csearch."%' ORDER BY website ASC";
	} else {
		$sqlstring = "ORDER BY enddate ASC";
	}
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num - 1) * $confso['anum'];
	$a = ($num) ? $offset+1 : 1;
	if ($csearch) {
		$sqlstatus = "active != '2'";
		$field = "op=shop_clients&amp;";
		$refer = "";
		$cont = shop_navi(0, 0, 1, 1);
	} elseif (getVar('get', 'status', 'num') == 1) {
		$sqlstatus = "active = '1'";
		$field = "op=shop_clients&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(0, 0, 1, 1);
	} elseif (getVar('get', 'status', 'num') == 2) {
		$sqlstatus = "active = '0'";
		$field = "op=shop_clients&amp;status=2&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(0, 0, 1, 2);
	} else {
		$sqlstatus = "active = '2'";
		$field = "op=shop_clients&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(0, 0, 1, 0);
	}
	$result = $db->sql_query('SELECT c.id, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_name, p.title FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.id_product) WHERE c.'.$sqlstatus.' '.$sqlstring.' LIMIT '.$offset.', '.$confso['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._PRODUCT."</th><th>"._SITE."</th><th>"._NICKNAME."</th><th>"._DATE."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while(list($cid, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $user_name, $ptitle) = $db->sql_fetchrow($result)) {
			$cenddate = ($cenddate != "0") ? rest_time($cenddate) : _UNLIMITED;
			$cinfo = ($cinfo) ? $cinfo : _NO;
			if ($user_name) {
				$del_name = $user_name;
				$user_name = user_info(search_color($user_name, $csearch));
			} else {
				$del_name = $confu['anonym'];
				$user_name = $confu['anonym'];
			}
			$cont .= "<tr><td>".$cid."</td>"
			."<td>".title_tip(_ID.": ".$a."<br>"._DATE.": ".date(_TIMESTRING, $cregdate)."<br>"._CLIENTNAME.": ".search_color($cname, $csearch)."<br>"._CLIENTADRES.": ".$cadres."<br>"._CLIENTPHONE.": ".$cphone."<br>"._EMAIL.": ".$cemail."<br>"._NOTE.": ".$cinfo)."<span title=\"".$ptitle."\" class=\"sl_note\">".cutstr($ptitle, 40)."</span></td>"
			."<td>".search_color(domain($cwebsite), $csearch)."</td>"
			."<td>".$user_name."</td>"
			."<td>".$cenddate."</td>"
			."<td>".ad_status("", $cactive)."</td>"
			."<td>".add_menu(ad_status($admin_file.".php?op=shop_clients_act&amp;id=".$cid.$refer, $cactive)."||<a href=\"".$admin_file.".php?op=shop_clients_add&amp;cid=".$cid."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=shop_clients_delete&amp;id=".$cid.$refer."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$del_name."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
			$a++;
		}
		$cont .= "</tbody></table>";
		if (!$tsearch || $tsearch >= 3) {
			$sqlstatus = $sqlstatus;
			$table = "_clients";
			$tid = "id";
		} else {
			$sqlstatus = "user_id != ''";
			$table = "_users";
			$tid = "user_id";
		}
		$cont .= setArticleNumbers("pagenum", "", $confso['anum'], $field, $tid, $table, "", $sqlstatus." ".$sqlstring, $confso['anump']);
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function shop_clients_act() {
	global $db, $admin_file;
	$id = getVar('get', 'id', 'num');
	list($active) = $db->sql_fetchrow($db->sql_query('SELECT active FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]));
	$active = ($active) ? 0 : 1;
	$db->sql_query('UPDATE '.PREFIX_DB.'_clients SET active = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
	referer($admin_file.".php?op=shop_clients");
}

function shop_clients_add() {
	global $db, $admin_file, $confso, $confu, $stop;
	if (isset($_REQUEST['cid'])) {
		$cid = getVar('req', 'cid', 'num');
		$result = $db->sql_query('SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_partner) WHERE c.id = :cid', ['cid' => $cid]);
		list($cid, $cid_user, $cid_product, $cid_partner, $cpartner_proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $user_id, $user_name) = $db->sql_fetchrow($result);
		$cregdate = date("Y-m-d H:i:s", $cregdate);
		$cenddate = ($cenddate) ? date("Y-m-d H:i:s", $cenddate) : date("Y-m-d H:i:s");
	} else {
		$cid_partner = getVar('post', 'cid_partner', 'num');
		$cid_user = getVar('post', 'cid_user', 'num');
		$cid_product = getVar('post', 'cid_product', 'num');
		$cname = getVar('post', 'cname', 'text');
		$cadres = getVar('post', 'cadres', 'text');
		$cphone = getVar('post', 'cphone', 'text');
		$cemail = getVar('post', 'cemail', 'text');
		$cwebsite = getVar('post', 'cwebsite', 'url');
		$cregdate = getVar('post', 'cregdate', 'text', date("Y-m-d H:i:s"));
		$cenddate = getVar('post', 'cenddate', 'text', date("Y-m-d H:i:s"));
		$cinfo = getVar('post', 'cinfo', 'text');
		$cactive = getVar('post', 'cactive', 'num');
	}
	head();
	$cont = shop_navi(0, 0, 1, 3);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	$cont .= tpl_eval("open");
	$cont .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">";
	if ($cid_partner) {
		if (!$cpartner_proz) {
			$num = $db->sql_numrows($db->sql_query('SELECT id_partner FROM '.PREFIX_DB.'_clients WHERE id_partner = :cid_partner AND active != 2', ['cid_partner' => $cid_partner]));
			if ($num >= $confso['clients2']) {
				$cpartner_proz = $confso['proz2'];
			} elseif ($num >= $confso['clients1']) {
				$cpartner_proz = $confso['proz1'];
			} elseif ($num >= $confso['clients']) {
				$cpartner_proz = $confso['proz'];
			} else {
				$cpartner_proz = "0";
			}
			$cppi = 1;
		} else {
			$cppi = 0;
		}
		$user_name = ($user_name) ? user_info($user_name) : $confu['anonym'];
		$cont .= "<tr><td>"._PARTNER_NAME.":</td><td>".$user_name."</td></tr>"
		."<tr><td>"._PARTNER_ID.":</td><td><input type=\"hidden\" name=\"cid_partner\" value=\"".$cid_partner."\">".$cid_partner."</td></tr>"
		."<tr><td>"._PERCENT.":</td><td>".$cpartner_proz." %</td></tr>";
	}
	$cont .= "<tr><td>"._USER_ID.":</td><td><input type=\"number\" name=\"cid_user\" value=\"".$cid_user."\" class=\"sl_form\" placeholder=\""._USER_ID."\"></td></tr>";
	$productslist = $db->sql_query("SELECT id, title FROM ".PREFIX_DB."_products ORDER BY title");
	$cont .= "<tr><td>"._PRODUCT.":</td><td><select name=\"cid_product\" class=\"sl_form\">";
	while(list($pid, $ptitle) = $db->sql_fetchrow($productslist)) {
		$cont .= "<option value=\"".$pid."\"";
		if ($cid_product == $pid) $cont .= " selected";
		$cont .= ">".$ptitle."</option>";
	}
	$cont .= "</select></td></tr>"
	."<tr><td>"._CLIENTNAME.":</td><td><input type=\"text\" name=\"cname\" value=\"".$cname."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTNAME."\" required></td></tr>"
	."<tr><td>"._CLIENTADRES.":</td><td><input type=\"text\" name=\"cadres\" value=\"".$cadres."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTADRES."\" required></td></tr>"
	."<tr><td>"._CLIENTPHONE.":</td><td><input type=\"text\" name=\"cphone\" value=\"".$cphone."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTPHONE."\" required></td></tr>"
	."<tr><td>"._EMAIL.":</td><td><input type=\"email\" name=\"cemail\" value=\"".$cemail."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._EMAIL."\" required></td></tr>"
	."<tr><td>"._SITE.":</td><td><input type=\"url\" name=\"cwebsite\" value=\"".$cwebsite."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._SITE."\"></td></tr>"
	."<tr><td>"._CLIENTSTR.": </td><td>".datetime(1, "cregdate", $cregdate, 16, "sl_form")."</td></tr>"
	."<tr><td>"._CLIENTEND.":</td><td>".datetime(1, "cenddate", $cenddate, 16, "sl_form")."</td></tr>"
	."<tr><td>"._NOTE.":</td><td><input type=\"text\" name=\"cinfo\" value=\"".$cinfo."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._NOTE."\"></td></tr>"
	."<tr><td>"._ACTIVATE2."</td><td>".radio_form($cactive, "cactive")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"cppi\" value=\"".$cppi."\">".ad_save("cid", $cid, "shop_clients_save", 1)."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function shop_clients_save() {
	global $db, $admin_file, $confso, $stop;
	$cid_partner = getVar('post', 'cid_partner', 'num');
	$cid_user = getVar('post', 'cid_user', 'num');
	$cid_product = getVar('post', 'cid_product', 'num');
	$cname = getVar('post', 'cname', 'text');
	$cadres = getVar('post', 'cadres', 'text');
	$cphone = getVar('post', 'cphone', 'text');
	$cemail = getVar('post', 'cemail', 'text');
	$cwebsite = getVar('post', 'cwebsite', 'url');
	$cregdate = getVar('post', 'cregdate', 'text');
	$cenddate = getVar('post', 'cenddate', 'text');
	$cinfo = getVar('post', 'cinfo', 'text');
	$cactive = getVar('post', 'cactive', 'num');
	$cppi = getVar('post', 'cppi', 'num');
	$cid = getVar('post', 'cid', 'num');
	$cregdate = ($cregdate) ? strtotime($cregdate) : 0;
	$cenddate = ($cenddate) ? strtotime($cenddate) : 0;
	$stop = array();
	checkemail($cemail);
	if (!$cname || !$cadres || !$cphone) $stop[] = _ERROR_ALL;
	if (!$stop && getVar('post', 'posttype', 'text') == "save") {
		if ($cid) {
			if ($cid_partner && $cppi) {
				list($ppreis) = $db->sql_fetchrow($db->sql_query('SELECT preis FROM '.PREFIX_DB.'_products WHERE id = :cid_product', ['cid_product' => $cid_product]));
				$num = $db->sql_numrows($db->sql_query('SELECT id_partner FROM '.PREFIX_DB.'_clients WHERE id_partner = :cid_partner AND active != 2', ['cid_partner' => $cid_partner]));
				if ($num >= $confso['clients2']) {
					$confso['proz2'] = ($confso['proz2']) ? $confso['proz2'] : 1;
					$end_preis = $ppreis / 100 * $confso['proz2'];
					$cpartner_proz = $confso['proz2'];
				} elseif ($num >= $confso['clients1']) {
					$confso['proz1'] = ($confso['proz1']) ? $confso['proz1'] : 1;
					$end_preis = $ppreis / 100 * $confso['proz1'];
					$cpartner_proz = $confso['proz1'];
				} elseif ($num >= $confso['clients']) {
					$confso['proz'] = ($confso['proz']) ? $confso['proz'] : 1;
					$end_preis = $ppreis / 100 * $confso['proz'];
					$cpartner_proz = $confso['proz'];
				}
				$db->sql_query('UPDATE '.PREFIX_DB.'_partners SET rest = rest+:end_preis WHERE id_user = :cid_partner', ['end_preis' => $end_preis, 'cid_partner' => $cid_partner]);
				$db->sql_query('UPDATE '.PREFIX_DB.'_clients SET id_user = :cid_user, id_product = :cid_product, id_partner = :cid_partner, partner_proz = :cpartner_proz, name = :cname, adres = :cadres, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, active = :cactive WHERE id = :cid', ['cid_user' => $cid_user, 'cid_product' => $cid_product, 'cid_partner' => $cid_partner, 'cpartner_proz' => $cpartner_proz, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
			} else {
				$db->sql_query('UPDATE '.PREFIX_DB.'_clients SET id_user = :cid_user, id_product = :cid_product, name = :cname, adres = :cadres, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, active = :cactive WHERE id = :cid', ['cid_user' => $cid_user, 'cid_product' => $cid_product, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
			}
		} else {
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_clients VALUES(NULL, :cid_user, :cid_product, \'0\', \'0\', :cname, :cadres, :cphone, :cemail, :cwebsite, :cregdate, :cenddate, :cinfo, :cactive)', ['cid_user' => $cid_user, 'cid_product' => $cid_product, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive]);
		}
		header("Location: ".$admin_file.".php?op=shop_clients");
	} elseif (getVar('post', 'posttype', 'text') == "delete") {
		shop_clients_delete($cid);
	} else {
		shop_clients_add();
	}
}

function shop_clients_delete() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]);
	referer($admin_file.".php?op=shop_clients");
}

function shop_products() {
	global $db, $admin_file, $confso;
	head();
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num-1) * $confso['anum'];
	$offset = intval($offset);
	if (getVar('get', 'status', 'num') == 1) {
		$sqlstatus = "active=0";
		$field = "op=shop_products&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(1, 1, 1, 1);
	} else {
		$sqlstatus = "active=1";
		$field = "op=shop_products&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(1, 1, 1, 0);
	}
	$result = $db->sql_query('SELECT p.id, p.cid, p.time, p.title, p.preis, p.vote, p.active, c.title FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE '.$sqlstatus.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$confso['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\">"
		."<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._PRODUCT."</th><th>"._PREIS."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th><th class=\"{sorter: false}\"><input type=\"checkbox\" name=\"markcheck\" id=\"markcheck\" title=\""._CHECKALL."\" OnClick=\"CheckBox('#markcheck', '.sl_check')\"></th></tr></thead><tbody>";
		while(list($pid, $pcid, $ptime, $ptitle, $ppreis, $pvote, $pactive, $ctitle) = $db->sql_fetchrow($result)) {
			$ctitle = ($pcid) ? $ctitle : _NO;
			if ($pactive && time() >= strtotime($ptime)) {
				$ad_view = "<a href=\"index.php?name=shop&amp;op=view&amp;id=".$pid."\" title=\""._MVIEW."\">"._MVIEW."</a>||";
				$active = "1";
			} else {
				$ad_view = "";
				$active = "0";
			}
			$ad_vote = ($pvote) ? "<a href=\"".$admin_file.".php?op=voting_add&amp;id=".$pvote."\" title=\""._EDITVOTE."\">"._EDITVOTE."</a>||" : "";
			$typ = ($pactive) ? "0" : "1";
			$cont .= "<tr><td>".$pid."</td>"
			."<td>".title_tip(_CATEGORY.": ".$ctitle."<br>"._DATE.": ".format_time($ptime, _TIMESTRING))."<span title=\"".$ptitle."\" class=\"sl_note\">".cutstr($ptitle, 60)."</span></td>"
			."<td>".$ppreis." ".$confso['valute']."</td>"
			."<td>".ad_status("", $active)."</td>"
			."<td>".add_menu($ad_view.$ad_vote.ad_status($admin_file.".php?op=shop_products_admin&amp;typ=a".$typ."&amp;id=".$pid.$refer, $pactive)."||<a href=\"".$admin_file.".php?op=shop_products_add&amp;id=".$pid."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=shop_products_admin&amp;typ=d&amp;id=".$pid.$refer."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$ptitle."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td>"
			."<td><input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$pid."\"></td></tr>";
		}
		$cont .= "</tbody></table>";
		$selms = _CHECKOP.": ".edit_list("shop", "typ", "")." <input type=\"hidden\" name=\"op\" value=\"shop_products_admin\"><input type=\"hidden\" name=\"refer\" value=\"1\"> <input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\">";
		$numpt = setArticleNumbers("pagenum", "", $confso['anum'], $field, "id", "_products", "", $sqlstatus, $confso['anump']);
		$cont .= tpl_eval("list-bottom", $numpt, $selms)."</form>";
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function shop_products_add() {
	global $db, $admin_file, $confso, $stop;
	if (isset($_REQUEST['id'])) {
		$id = getVar('req', 'id', 'num');
		$result = $db->sql_query('SELECT id, cid, time, title, text, bodytext, preis, vote, assoc, ihome, acomm, count, fix, active FROM '.PREFIX_DB.'_products WHERE id = :id', ['id' => $id]);
		list($pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $ppreis, $vote, $passoc, $ihome, $acomm, $pcount, $fix, $pactive) = $db->sql_fetchrow($result);
		$associated = explode(",", $passoc);
	} else {
		$pid = getVar('post', 'pid', 'num');
		$pcid = getVar('post', 'pcid', 'num');
		$ptitle = save_text(getVar('post', 'ptitle', 'text'), 1);
		$ptext = save_text(getVar('post', 'ptext', 'text'));
		$pbodytext = save_text(getVar('post', 'pbodytext', 'text'));
		$ppreis = getVar('post', 'ppreis', 'text');
		$vote = getVar('post', 'vote', 'num');
		$ptime = save_datetime(1, "ptime");
		$associated = getVar('post', 'associated', 'array');
		$ihome = getVar('post', 'ihome', 'num');
		$acomm = getVar('post', 'acomm', 'num');
		$fix = getVar('post', 'fix', 'num');
		$pactive = getVar('post', 'pactive', 'num');
	}
	head();
	$cont = shop_navi(1, 1, 1, 2);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	$ptextpre = ($vote) ? "<div id=\"repshop\">".getVoting($vote, "shop")."</div><hr>".$ptext : $ptext;
	if ($ptextpre) $cont .= preview($ptitle, $ptextpre, $pbodytext, "", "shop");
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._TITLE." / "._PRODUCT.":</td><td><input type=\"text\" name=\"ptitle\" value=\"".$ptitle."\" maxlength=\"100\" class=\"sl_form\" placeholder=\""._TITLE."\" required></td></tr>"
	."<tr><td>"._CATEGORY.":</td><td>".getcat("shop", $pcid, "pcid", "sl_form", "<option value=\"\">"._HOMECAT."</option>")."</td></tr>";
	$result2 = $db->sql_query("SELECT id, title FROM ".PREFIX_DB."_categories WHERE modul = 'shop' ORDER BY parentid, title");
	if ($db->sql_numrows($result2) > 0) {
		$cont .= "<tr><td>"._ASSOTOPIC.":<div class=\"sl_small\">"._ASSOTOPICI."</div></td><td><table class=\"sl_form\"><tr>";
		while (list($id, $title) = $db->sql_fetchrow($result2)) {
			if ($a == 2) {
				$cont .= "</tr><tr>";
				$a = 0;
			}
			$check = "";
			if ($associated) foreach ($associated as $val) if ($val == $id) $check = " checked";
			$cont .= "<td><input type=\"checkbox\" name=\"associated[]\" value=\"".$id."\"".$check."> ".$title."</td>";
			$a++;
		}
		$cont .= "</tr></table></td></tr>";
	}
	$cont .= "<tr><td>"._TEXT.":</td><td>".textarea("1", "ptext", $ptext, "shop", "5", _TEXT, "1")."</td></tr>"
	."<tr><td>"._ENDTEXT.":</td><td>".textarea("2", "pbodytext", $pbodytext, "shop", "15", _ENDTEXT, "0")."</td></tr>"
	."<tr><td>"._PREIS.":</td><td><input type=\"text\" name=\"ppreis\" value=\"".$ppreis."\" maxlength=\"10\" class=\"sl_form\" placeholder=\""._PREIS."\" required></td></tr>"
	."<tr><td>"._CHNGSTORY.":</td><td>".datetime(1, "ptime", $ptime, 16, "sl_form")."</td></tr>"
	."<tr><td>"._VOTING.":</td><td>".add_voting("shop", "vote", $vote, "sl_form")."</td></tr>"
	."<tr><td>"._COMMENTS.":</td><td>".com_access("acomm", $acomm, "sl_form")."</td></tr>"
	."<tr><td>"._PUBHOME."</td><td>".radio_form($ihome, "ihome")."</td></tr>"
	."<tr><td>"._FIXED."?</td><td>".radio_form($fix, "fix")."</td></tr>"
	."<tr><td>"._ACTIVATEP."</td><td>".radio_form($pactive, "pactive")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("pid", $pid, "shop_products_save")."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function shop_products_save() {
	global $db, $admin_file, $stop;
	$pid = getVar('post', 'pid', 'num');
	$pcid = getVar('post', 'pcid', 'num');
	$ptitle = save_text(getVar('post', 'ptitle', 'text'), 1);
	$associated = implode(",", getVar('post', 'associated', 'array', []));
	$ptext = save_text(getVar('post', 'ptext', 'text'));
	$pbodytext = save_text(getVar('post', 'pbodytext', 'text'));
	$ppreis = getVar('post', 'ppreis', 'text');
	$vote = getVar('post', 'vote', 'num');
	$ihome = getVar('post', 'ihome', 'num');
	$acomm = getVar('post', 'acomm', 'num');
	$fix = getVar('post', 'fix', 'num');
	$pactive = getVar('post', 'pactive', 'num');
	$ptime = save_datetime(1, "ptime");
	$stop = array();
	if (!$ptitle || !$ptext || !$ppreis) $stop[] = _ERROR_ALL;
	if (!$stop && getVar('post', 'posttype', 'text') == "save") {
		if ($pid) {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET cid = :pcid, time = :ptime, title = :ptitle, text = :ptext, bodytext = :pbodytext, preis = :ppreis, vote = :vote, assoc = :associated, ihome = :ihome, acomm = :acomm, fix = :fix, active = :pactive WHERE id = :pid', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'ppreis' => $ppreis, 'vote' => $vote, 'associated' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive, 'pid' => $pid]);
		} else {
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_products VALUES (NULL, :pcid, :ptime, :ptitle, :ptext, :pbodytext, :ppreis, :vote, :associated, :ihome, :acomm, \'0\', \'0\', \'0\', \'0\', :fix, :pactive)', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'ppreis' => $ppreis, 'vote' => $vote, 'associated' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive]);
		}
		header("Location: ".$admin_file.".php?op=shop_products");
	} elseif (getVar('post', 'posttype', 'text') == "delete") {
		shop_products_admin($pid, "d");
	} else {
		shop_products_add();
	}
}

function shop_products_admin() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	$id = (is_array($id)) ? implode(",", $id) : intval($id);
	$vtyp_post = getVar('post', 'typ', 'text');
	$vtyp_get = getVar('get', 'typ', 'text');
	$vtyp = ($vtyp_post) ? analyze($vtyp_post) : (($vtyp_get) ? analyze($vtyp_get) : $arg[1]);
	$typ = (is_numeric($vtyp[0])) ? intval($vtyp) : intval(substr($vtyp, 1));
	if ($id) {
		if ($vtyp[0] == 'a') {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET active = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
		} elseif ($vtyp[0] == 'f') {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET fix = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
		} elseif ($vtyp[0] == 'h') {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET ihome = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
		} elseif ($vtyp[0] == 't') {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET time = now() WHERE id IN ('.$id.')');
		} elseif ($vtyp[0] == 'c') {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET acomm = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
		} elseif ($vtyp[0] == 'd') {
			$db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.$id.') AND modul = \'shop\'');
			$db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid IN ('.$id.') AND modul = \'shop\'');
			$db->sql_query('DELETE FROM '.PREFIX_DB.'_products WHERE id IN ('.$id.')');
		} elseif (is_numeric($vtyp[0])) {
			$db->sql_query('UPDATE '.PREFIX_DB.'_products SET cid = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
		}
	}
	referer($admin_file.".php?op=shop_products");
}

function shop_partners() {
	global $db, $admin_file, $confso, $confu;
	head();
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num - 1) * $confso['anum'];
	$offset = intval($offset);
	if (getVar('get', 'status', 'num') == 1) {
		$sqlstatus = "active=1";
		$field = "op=shop_partners&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(2, 2, 1, 1);
	} elseif (getVar('get', 'status', 'num') == 2) {
		$sqlstatus = "active=0";
		$field = "op=shop_partners&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(2, 2, 1, 2);
	} else {
		$sqlstatus = "active=2";
		$field = "op=shop_partners&amp;";
		$refer = "&amp;refer=1";
		$cont = shop_navi(2, 2, 1, 0);
	}
	$result = $db->sql_query('SELECT p.id, p.name, p.adres, p.phone, p.email, p.website, p.regdate, p.rest, p.bek, p.active, u.user_name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = p.id_user) WHERE '.$sqlstatus.' LIMIT '.$offset.', '.$confso['anum']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._NICKNAME."</th><th>"._PARTNERREST."</th><th>"._PARTNERBEK."</th><th>"._SITE."</th><th>"._REG."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while(list($paid, $paname, $paadres, $paphone, $paemail, $pawebsite, $paregdate, $parest, $pabek, $paactive, $user_name) = $db->sql_fetchrow($result)) {
			if ($user_name) {
				$del_name = $user_name;
				$user_name = user_info(search_color($user_name, ""));
			} else {
				$del_name = $confu['anonym'];
				$user_name = $confu['anonym'];
			}
			$cont .= "<tr><td>".$paid."</td>"
			."<td>".title_tip(_CLIENTNAME.": ".$paname."<br>"._CLIENTADRES.": ".$paadres."<br>"._CLIENTPHONE.": ".$paphone."<br>"._EMAIL.": ".$paemail).$user_name."</td>"
			."<td>".$parest." ".$confso['valute']."</td>"
			."<td>".$pabek." ".$confso['valute']."</td>"
			."<td>".domain($pawebsite)."</td>"
			."<td>".date(_TIMESTRING, $paregdate)."</td>"
			."<td>".add_menu(ad_status($admin_file.".php?op=shop_partners_act&amp;id=".$paid.$refer, $paactive)."||<a href=\"".$admin_file.".php?op=shop_partners_details&amp;paid=".$paid."\" title=\""._MVIEW."\">"._MVIEW."</a>||<a href=\"".$admin_file.".php?op=shop_partners_add&amp;paid=".$paid."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=shop_partners_delete&amp;id=".$paid.$refer."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$del_name."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
		}
		$cont .= "</tbody></table>";
		$cont .= setArticleNumbers("pagenum", "", $confso['anum'], $field, "id", "_partners", "", $sqlstatus, $confso['anump']);
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function shop_partners_act() {
	global $db, $admin_file;
	$id = getVar('get', 'id', 'num');
	list($active) = $db->sql_fetchrow($db->sql_query('SELECT active FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]));
	$active = ($active == 1) ? 0 : 1;
	$db->sql_query('UPDATE '.PREFIX_DB.'_partners SET active = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
	referer($admin_file.".php?op=shop_partners");
}

function shop_partners_add() {
	global $db, $admin_file, $confu, $stop;
	if (isset($_REQUEST['paid'])) {
		$paid = getVar('req', 'paid', 'num');
		$result = $db->sql_query('SELECT p.id, p.id_user, p.name, p.adres, p.phone, p.email, p.website, p.webmoney, p.paypal, p.regdate, p.rest, p.bek, p.active, u.user_name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = p.id_user) WHERE p.id = :paid', ['paid' => $paid]);
		list($paid, $paid_user, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive, $user_name) = $db->sql_fetchrow($result);
		$paregdate = ($paregdate) ? date("Y-m-d H:i:s", $paregdate) : date("Y-m-d H:i:s");
	} else {
		$paid_user = getVar('post', 'paid_user', 'num');
		$paname = getVar('post', 'paname', 'text');
		$paadres = getVar('post', 'paadres', 'text');
		$paphone = getVar('post', 'paphone', 'text');
		$paemail = getVar('post', 'paemail', 'text');
		$pawebsite = getVar('post', 'pawebsite', 'url');
		$pawebmoney = getVar('post', 'pawebmoney', 'text');
		$papaypal = getVar('post', 'papaypal', 'text');
		$paregdate = getVar('post', 'paregdate', 'text', date("Y-m-d H:i:s"));
		$parest = getVar('post', 'parest', 'text');
		$pabek = getVar('post', 'pabek', 'text');
		$paactive = getVar('post', 'paactive', 'num');
	}
	head();
	$cont = shop_navi(2, 2, 1, 3);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	$cont .= tpl_eval("open");
	$cont .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">";
	if ($paid) {
		$user_name = ($user_name) ? user_info($user_name) : $confu['anonym'];
		$cont .= "<tr><td>"._NICKNAME.":</td><td>".$user_name."</td></tr>";
	}
	$cont .= "<tr><td>"._USER_ID.":</td><td>";
	$cont .= ($paid_user == 0) ? "<input type=\"number\" name=\"paid_user\" value=\"".$paid_user."\" class=\"sl_form\" placeholder=\""._USER_ID."\" required>" : "<input type=\"hidden\" name=\"paid_user\" value=\"".$paid_user."\">".$paid_user;
	$cont .= "</td></tr><tr><td>"._CLIENTNAME.":</td><td><input type=\"text\" name=\"paname\" value=\"".$paname."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTNAME."\" required></td></tr>"
	."<tr><td>"._CLIENTADRES.":</td><td><input type=\"text\" name=\"paadres\" value=\"".$paadres."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTADRES."\" required></td></tr>"
	."<tr><td>"._CLIENTPHONE.":</td><td><input type=\"text\" name=\"paphone\" value=\"".$paphone."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._CLIENTPHONE."\" required></td></tr>"
	."<tr><td>"._EMAIL.":</td><td><input type=\"email\" name=\"paemail\" value=\"".$paemail."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._EMAIL."\" required></td></tr>"
	."<tr><td>"._SITE.":</td><td><input type=\"url\" name=\"pawebsite\" value=\"".$pawebsite."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._SITE."\"></td></tr>"
	."<tr><td>"._WEBMONEY.":</td><td><input type=\"text\" name=\"pawebmoney\" value=\"".$pawebmoney."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._WEBMONEY."\"></td></tr>"
	."<tr><td>"._PAYPAL.":</td><td><input type=\"text\" name=\"papaypal\" value=\"".$papaypal."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._PAYPAL."\"></td></tr>"
	."<tr><td>"._REG.":</td><td>".datetime(1, "paregdate", $paregdate, 16, "sl_form")."</td></tr>";
	if ($paactive != 2) {
		$cont .= "<tr><td>"._PARTNERREST.":</td><td><input type=\"text\" name=\"parest\" value=\"".$parest."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._PARTNERREST."\"></td></tr>"
		."<tr><td>"._PARTNERBEK.":</td><td><input type=\"text\" name=\"pabek\" value=\"".$pabek."\" maxlength=\"255\" class=\"sl_form\" placeholder=\""._PARTNERBEK."\"></td></tr>";
	}
	$cont .= "<tr><td>"._ACTIVATE2."</td><td>".radio_form($paactive, "paactive")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("paid", $paid, "shop_partners_save", 1)."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function shop_partners_save() {
	global $db, $admin_file, $stop;
	$paid_user = getVar('post', 'paid_user', 'num');
	$paname = getVar('post', 'paname', 'text');
	$paadres = getVar('post', 'paadres', 'text');
	$paphone = getVar('post', 'paphone', 'text');
	$paemail = getVar('post', 'paemail', 'text');
	$pawebsite = getVar('post', 'pawebsite', 'url');
	$pawebmoney = getVar('post', 'pawebmoney', 'text');
	$papaypal = getVar('post', 'papaypal', 'text');
	$paregdate = getVar('post', 'paregdate', 'text');
	$parest = getVar('post', 'parest', 'text');
	$pabek = getVar('post', 'pabek', 'text');
	$paactive = getVar('post', 'paactive', 'num');
	$paid = getVar('post', 'paid', 'num');
	$paregdate = ($paregdate) ? strtotime($paregdate) : 0;
	checkemail($paemail);
	if (!$paname || !$paadres || !$paphone) $stop[] = _ERROR_ALL;
	if (!$stop && getVar('post', 'posttype', 'text') == "save") {
		if ($paid) {
			$db->sql_query("UPDATE ".PREFIX_DB."_partners SET id_user = '".$paid_user."', name = '".$paname."', adres = '".$paadres."', phone = '".$paphone."', email = '".$paemail."', website = '".$pawebsite."', webmoney = '".$pawebmoney."', paypal = '".$papaypal."', regdate = '".$paregdate."', rest = '".$parest."', bek = '".$pabek."', active = '".$paactive."' WHERE id = '".$paid."'");
		} else {
			$db->sql_query("INSERT INTO ".PREFIX_DB."_partners VALUES(NULL, '".$paid_user."', '".$paname."', '".$paadres."', '".$paphone."', '".$paemail."', '".$pawebsite."', '".$pawebmoney."', '".$papaypal."', '".$paregdate."', '".$parest."', '".$pabek."', '".$paactive."')");
		}
		header("Location: ".$admin_file.".php?op=shop_partners");
	} elseif (getVar('post', 'posttype', 'text') == "delete") {
		shop_partners_delete($paid);
	} else {
		shop_partners_add();
	}
}

function shop_partners_delete() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]);
	referer($admin_file.".php?op=shop_partners");
}

function shop_partners_details() {
	global $db, $admin_file, $confso;
	$paid = getVar('get', 'paid', 'num');
	head();
	$cont = shop_navi(2, 2, 1, 1);
	$result = $db->sql_query('SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM '.PREFIX_DB.'_partners WHERE id = :paid', ['paid' => $paid]);
	list($paid, $paid_user, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive) = $db->sql_fetchrow($result);
	$result = $db->sql_query('SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name, p.id, p.title, p.preis FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id=c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id=c.id_product) WHERE c.id_partner = :paid_user AND c.active != 2 ORDER BY c.id ASC', ['paid_user' => $paid_user]);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._NICKNAME."</th><th>"._PRODUCT."</th><th>"._PREIS."</th><th>"._PERCENT."</th><th>"._DATE."</th><th class=\"{sorter: false}\">"._SUM."</th></tr></thead><tbody>";
		$partsum = 0;
		$partsumges = 0;
		$a = 0;
		while(list($cid, $cid_user, $cid_product, $cid_partner, $cpartner_proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $user_id, $user_name, $pid, $ptitle, $ppreis) = $db->sql_fetchrow($result)) {
			$partsum = $ppreis / 100 * $cpartner_proz;
			$partsumges += $partsum;
			$cont .= "<tr><td>".$cid."</td>"
			."<td>".user_info($user_name)."</td>"
			."<td>".$ptitle."</td>"
			."<td>".$ppreis." ".$confso['valute']."</td>"
			."<td>".$cpartner_proz." %</td>"
			."<td>".date(_TIMESTRING, $cregdate)."</td>"
			."<td>".$partsum." ".$confso['valute']."</td></tr>";
			$a++;
		}
		$cont .= "</tbody></table>";
		$cont .= tpl_eval("close");
	}
	$cont .= tpl_eval("open");
	$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._CLIENTEN."</th><th>"._WEBMONEY."</th><th>"._PAYPAL."</th><th>"._PARTNERGES."</th><th>"._PARTNERREST."</th><th class=\"{sorter: false}\">"._PARTNERBEK."</th></tr></thead><tbody>"
	."<tr><td>".$a."</td>"
	."<td>".$pawebmoney."</td>"
	."<td>".$papaypal."</td>"
	."<td>".$partsumges." ".$confso['valute']."</td>"
	."<td>".$parest." ".$confso['valute']."</td>"
	."<td>".$pabek." ".$confso['valute']."</td></tr></tbody></table>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function shop_export() {
	global $db, $admin_file;
	$id = getVar('post', 'id', 'num');
	$bd = getVar('post', 'bd', 'text');
	if ($id == 1 && $bd) {
		$list = array();
		if ($bd == "products") {
			$result = $db->sql_query('SELECT id, cid, time, title, text, bodytext, preis, vote, assoc, com, count, votes, totalvotes, fix, active FROM '.PREFIX_DB.'_products ORDER BY id');
			while(list($pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $ppreis, $pvote, $passoc, $pcom, $pcount, $pvotes, $ptotalvotes, $pfix, $pactive) = $db->sql_fetchrow($result)) {
				$list[] = $pid."||".$pcid."||".$ptime."||".$ptitle."||".$ptext."||".$pbodytext."||".$ppreis."||".$pvote."||".$passoc."||".$pcom."||".$pcount."||".$pvotes."||".$ptotalvotes."||".$pfix."||".$pactive;
			}
		} elseif ($bd == "clients") {
			$result = $db->sql_query('SELECT id, id_user, id_product, id_partner, partner_proz, name, adres, phone, email, website, regdate, enddate, info, active FROM '.PREFIX_DB.'_clients ORDER BY id');
			while(list($cid, $cid_user, $cid_product, $cid_partner, $cpartner_proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive) = $db->sql_fetchrow($result)) {
				$list[] = $cid."||".$cid_user."||".$cid_product."||".$cid_partner."||".$cpartner_proz."||".$cname."||".$cadres."||".$cphone."||".$cemail."||".$cwebsite."||".$cregdate."||".$cenddate."||".$cinfo."||".$cactive;
			}
		} elseif ($bd == "partners") {
			$result = $db->sql_query('SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM '.PREFIX_DB.'_partners ORDER BY id');
			while(list($paid, $paid_user, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive) = $db->sql_fetchrow($result)) {
				$list[] = $paid."||".$paid_user."||".$paname."||".$paadres."||".$paphone."||".$paemail."||".$pawebsite."||".$pawebmoney."||".$papaypal."||".$paregdate."||".$parest."||".$pabek."||".$paactive;
			}
		}
		if ($list) {
			$date = date("d.m.Y");
			$fp = fopen("uploads/shop/temp/".$date."_".$bd.".csv", "wb");
			foreach ($list as $val) fputcsv($fp, explode("||", $val));

			fclose($fp);
			stream("uploads/shop/temp/".$date."_".$bd.".csv", $date."_".$bd.".csv");
		} else {
			header("Location: ".$admin_file.".php?op=shop_export");
		}
	} elseif ($id == 2 && $bd) {
		$handle = fopen ("uploads/shop/temp/".$bd,"rb");
		while (($data = fgetcsv($handle, 1000, ","))) {
			if (preg_match("#(.*?)products\.csv#", $bd)) {
				$iid = "id";
				$idb = "products";
				$uquery = "cid = '".$data[1]."', time = '".$data[2]."', title = '".$data[3]."', text = '".$data[4]."', bodytext = '".$data[5]."', preis = '".$data[6]."', vote = '".$data[7]."', assoc = '".$data[7]."', com = '".$data[9]."', count = '".$data[10]."', votes = '".$data[11]."', totalvotes = '".$data[12]."', fix = '".$data[13]."', active = '".$data[14]."'";
				$squery = "'".$data[1]."', '".$data[2]."', '".$data[3]."', '".$data[4]."', '".$data[5]."', '".$data[6]."', '".$data[7]."', '".$data[8]."', '".$data[9]."', '".$data[10]."', '".$data[11]."', '".$data[12]."'";
			} elseif (preg_match("#(.*?)clients\.csv#", $bd)) {
				$iid = "id";
				$idb = "clients";
				$uquery = "id_user = '".$data[1]."', id_product = '".$data[2]."', id_partner = '".$data[3]."', partner_proz = '".$data[4]."', name = '".$data[5]."', adres = '".$data[6]."', phone = '".$data[7]."', email = '".$data[8]."', website = '".$data[9]."', regdate = '".$data[10]."', enddate = '".$data[11]."', info = '".$data[12]."', active = '".$data[13]."'";
				$squery = "'".$data[1]."', '".$data[2]."', '".$data[3]."', '".$data[4]."', '".$data[5]."', '".$data[6]."', '".$data[7]."', '".$data[8]."', '".$data[9]."', '".$data[10]."', '".$data[11]."', '".$data[12]."', '".$data[13]."'";
			} elseif (preg_match("#(.*?)partners\.csv#", $bd)) {
				$iid = "id";
				$idb = "partners";
				$uquery = "id_user = '".$data[1]."', name = '".$data[2]."', adres = '".$data[3]."', phone = '".$data[4]."', email = '".$data[5]."', website = '".$data[6]."', webmoney = '".$data[7]."', paypal = '".$data[8]."', regdate = '".$data[9]."', rest = '".$data[10]."', bek = '".$data[11]."', active = '".$data[12]."'";
				$squery = "'".$data[1]."', '".$data[2]."', '".$data[3]."', '".$data[4]."', '".$data[5]."', '".$data[6]."', '".$data[7]."', '".$data[8]."', '".$data[9]."', '".$data[10]."', '".$data[11]."', '".$data[12]."'";
			}
			$id = intval($data[0]);
			if ($id) {
				if ($db->sql_numrows($db->sql_query('SELECT '.$iid.' FROM '.PREFIX_DB.'_'.$idb.' WHERE '.$iid.' = :id', ['id' => $id]))) {
					$db->sql_query('UPDATE '.PREFIX_DB.'_'.$idb.' SET '.$uquery.' WHERE '.$iid.' = :id', ['id' => $id]);
				} else {
					$db->sql_query('INSERT INTO '.PREFIX_DB.'_'.$idb.' VALUES(:id, '.$squery.')', ['id' => $id]);
				}
			} else {
				$db->sql_query('INSERT INTO '.PREFIX_DB.'_'.$idb.' VALUES(NULL, '.$squery.')');
			}
		}
		fclose ($handle);
		header("Location: ".$admin_file.".php?op=shop_".$idb);
	} else {
		head();
		$cont = shop_navi(3, 3, 1, 0, "shop_export");
		$cont .= checkPerms('uploads/shop/temp', 1);
		$cont .= tpl_warn("warn", _S_NOTE, "", "", "info");
		list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT Count(id) FROM '.PREFIX_DB.'_products'));
		list($cl_num) = $db->sql_fetchrow($db->sql_query('SELECT Count(id) FROM '.PREFIX_DB.'_clients'));
		list($pa_num) = $db->sql_fetchrow($db->sql_query('SELECT Count(id) FROM '.PREFIX_DB.'_partners'));
		$content = "<div id=\"tabcs0\" class=\"tabcont\">";
		if ($pr_num || $cl_num || $pa_num) {
			$content .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
			."<tr><td>"._DATABASE.":</td><td><select name=\"bd\" class=\"sl_form\">";
			$content .= ($pr_num) ? "<option value=\"products\">"._PRODUCTS."</option>" : "";
			$content .= ($cl_num) ? "<option value=\"clients\">"._CLIENTS."</option>" : "";
			$content .= ($pa_num) ? "<option value=\"partners\">"._PARTNERS."</option>" : "";
			$content .= "</select></td></tr><tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"id\" value=\"1\"><input type=\"hidden\" name=\"op\" value=\"shop_export\"><input type=\"submit\" value=\""._SAVE."\" class=\"sl_but_blue\"></td></tr></table></form>";
		} else {
			$content .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
		}
		$content .= "</div><div id=\"tabcs1\" class=\"tabcont\">";
		$ocont = "";
		$dir = opendir("uploads/shop/temp");
		while (false !== ($entry = readdir($dir))) {
			if (preg_match("/(\.csv)$/is", $entry) && $entry != "." && $entry != "..") {
				$in = array("#(.*?)products\.csv#", "#(.*?)clients\.csv#", "#(.*?)partners\.csv#");
				$out = array(_PRODUCTS, _CLIENTS, _PARTNERS);
				$name = preg_replace($in, $out, $entry);
				$ocont .= "<option value=\"".$entry."\">".$name." - ".$entry."</option>";
			}
		}
		closedir($dir);
		if ($ocont) {
			$content .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
			."<tr><td>"._FILE.":</td><td><select name=\"bd\" class=\"sl_form\">".$ocont."</select></td></tr><tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"id\" value=\"2\"><input type=\"hidden\" name=\"op\" value=\"shop_export\"><input type=\"submit\" value=\""._SEND."\" class=\"sl_but_blue\"></td></tr></table></form>";
		} else {
			$content .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
		}
		$content .= "</div>"
		."<script>
		var countries=new ddtabcontent(\"shop_exports\")
		countries.setpersist(true)
		countries.setselectedClassTarget(\"link\")
		countries.init()
		</script>";
		$cont .= tpl_eval("open").$content.tpl_eval("close");
		echo $cont;
		foot();
	}
}

function shop_conf() {
	global $admin_file, $confso;
	head();
	$cont = shop_navi(0, 4, 0, 0);
	$cont .= checkPerms('shop.php');
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._CDEFIS.":</td><td><input type=\"text\" name=\"defis\" value=\"".urldecode($confso['defis'])."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._CDEFIS."\" required></td></tr>"
	."<tr><td>"._C_0.":</td><td><input type=\"number\" name=\"clients\" value=\"".$confso['clients']."\" class=\"sl_conf\" placeholder=\""._C_0."\" required></td></tr>"
	."<tr><td>"._C_1.":</td><td><input type=\"number\" name=\"proz\" value=\"".$confso['proz']."\" class=\"sl_conf\" placeholder=\""._C_1."\" required></td></tr>"
	."<tr><td>"._C_2.":</td><td><input type=\"number\" name=\"clients1\" value=\"".$confso['clients1']."\" class=\"sl_conf\" placeholder=\""._C_2."\" required></td></tr>"
	."<tr><td>"._C_3.":</td><td><input type=\"number\" name=\"proz1\" value=\"".$confso['proz1']."\" class=\"sl_conf\" placeholder=\""._C_3."\" required></td></tr>"
	."<tr><td>"._C_4.":</td><td><input type=\"number\" name=\"clients2\" value=\"".$confso['clients2']."\" class=\"sl_conf\" placeholder=\""._C_4."\" required></td></tr>"
	."<tr><td>"._C_5.":</td><td><input type=\"number\" name=\"proz2\" value=\"".$confso['proz2']."\" class=\"sl_conf\" placeholder=\""._C_5."\" required></td></tr>"
	."<tr><td>"._C_6.":</td><td><input type=\"text\" name=\"valute\" value=\"".$confso['valute']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._C_6."\" required></td></tr>"
	."<tr><td>"._C_7.":</td><td><input type=\"email\" name=\"mail\" value=\"".$confso['mail']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._C_7."\" required></td></tr>"
	."<tr><td>"._C_8.":</td><td><input type=\"number\" name=\"shop_t\" value=\"".intval($confso['shop_t'] / 86400)."\" class=\"sl_conf\" placeholder=\""._C_8."\" required></td></tr>"
	."<tr><td>"._C_9.":</td><td><input type=\"number\" name=\"part_t\" value=\"".intval($confso['part_t'] / 86400)."\" class=\"sl_conf\" placeholder=\""._C_9."\" required></td></tr>"
	."<tr><td>"._BASCOL.":</td><td><input type=\"number\" name=\"bascol\" value=\"".$confso['bascol']."\" class=\"sl_conf\" placeholder=\""._BASCOL."\" required></td></tr>"
	."<tr><td>"._C_11.":</td><td><input type=\"number\" name=\"assocnum\" value=\"".$confso['assocnum']."\" class=\"sl_conf\" placeholder=\""._C_11."\" required></td></tr>"
	."<tr><td>"._C_13.":</td><td><input type=\"number\" name=\"listnum\" value=\"".$confso['listnum']."\" class=\"sl_conf\" placeholder=\""._C_13."\" required></td></tr>"
	."<tr><td>"._C_33.":</td><td><input type=\"number\" name=\"num\" value=\"".$confso['num']."\" class=\"sl_conf\" placeholder=\""._C_33."\" required></td></tr>"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$confso['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_35.":</td><td><input type=\"number\" name=\"nump\" value=\"".$confso['nump']."\" class=\"sl_conf\" placeholder=\""._C_35."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$confso['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td>"._HOMCAT."</td><td>".radio_form($confso['homcat'], "homcat")."</td></tr>"
	."<tr><td>"._VIEWCAT."</td><td>".radio_form($confso['viewcat'], "viewcat")."</td></tr>"
	."<tr><td>"._C_32."</td><td>".radio_form($confso['catdesc'], "catdesc")."</td></tr>"
	."<tr><td>"._C_15."</td><td>".radio_form($confso['subcat'], "subcat")."</td></tr>"
	."<tr><td>"._C_14."</td><td>".radio_form($confso['mailuser'], "mailuser")."</td></tr>"
	."<tr><td>"._C_17."</td><td>".radio_form($confso['date'], "date")."</td></tr>"
	."<tr><td>"._C_18."</td><td>".radio_form($confso['read'], "read")."</td></tr>"
	."<tr><td>"._C_19."</td><td>".radio_form($confso['rate'], "rate")."</td></tr>"
	."<tr><td>"._C_20."</td><td>".radio_form($confso['letter'], "letter")."</td></tr>"
	."<tr><td>"._C_23."</td><td>".radio_form($confso['assoc'], "assoc")."</td></tr>"
	."<tr><td>"._C_24."</td><td>".radio_form($confso['mailsend'], "mailsend")."</td></tr>"
	."<tr><td>"._C_25."</td><td>".radio_form($confso['part'], "part")."</td></tr>"
	."<tr><td>"._C_26.":<div class=\"sl_small\">"._PART_ID."</div></td><td><input type=\"url\" name=\"partlink\" value=\"".$confso['partlink']."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._C_26."\" required></td></tr>"
	."<tr><td>"._C_27.":</td><td>".textarea("1", "sende", $confso['sende'], "shop", "5", _C_27, "1")."</td></tr>"
	."<tr><td>"._C_28.":</td><td>".textarea("2", "userinfo", $confso['userinfo'], "shop", "5", _C_28, "1")."</td></tr>"
	."<tr><td>"._C_29.":</td><td>".textarea("3", "partinfo", $confso['partinfo'], "shop", "5", _C_29, "1")."</td></tr>"
	."<tr><td>"._C_30.":</td><td>".textarea("4", "partinfo2", $confso['partinfo2'], "shop", "5", _C_30, "1")."</td></tr>"
	."<tr><td>"._C_31.":</td><td>".textarea("5", "shopinfo", $confso['shopinfo'], "shop", "5", _C_31, "1")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"shop_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function shop_conf_save() {
	global $admin_file, $conf;
	$defis_val = getVar('post', 'defis', 'text');
	$xdefis = ($defis_val) ? urlencode($defis_val) : "%3E";
	$shop_t_val = getVar('post', 'shop_t', 'num');
	$xshop_t = (!$shop_t_val) ? 2592000 : intval($shop_t_val * 86400);
	$part_t_val = getVar('post', 'part_t', 'num');
	$xpart_t = (!$part_t_val) ? 2592000 : intval($part_t_val * 86400);
	$bascol_val = getVar('post', 'bascol', 'num');
	$xbascol = (!$bascol_val) ? "1" : $bascol_val;
	$xsende = save_text(getVar('post', 'sende', 'text'));
	$xuserinfo= save_text(getVar('post', 'userinfo', 'text'));
	$xpartinfo = save_text(getVar('post', 'partinfo', 'text'));
	$xpartinfo2 = save_text(getVar('post', 'partinfo2', 'text'));
	$xshopinfo = save_text(getVar('post', 'shopinfo', 'text'));
	$cont = [
		'defis' => $xdefis,
		'clients' => getVar('post', 'clients', 'num'),
		'clients1' => getVar('post', 'clients1', 'num'),
		'clients2' => getVar('post', 'clients2', 'num'),
		'proz' => getVar('post', 'proz', 'num'),
		'proz1' => getVar('post', 'proz1', 'num'),
		'proz2' => getVar('post', 'proz2', 'num'),
		'valute' => getVar('post', 'valute', 'text'),
		'mail' => getVar('post', 'mail', 'text'),
		'shop_t' => $xshop_t,
		'part_t' => $xpart_t,
		'bascol' => $xbascol,
		'assocnum' => getVar('post', 'assocnum', 'num'),
		'listnum' => getVar('post', 'listnum', 'num'),
		'num' => getVar('post', 'num', 'num'),
		'anum' => getVar('post', 'anum', 'num'),
		'nump' => getVar('post', 'nump', 'num'),
		'anump' => getVar('post', 'anump', 'num'),
		'homcat' => getVar('post', 'homcat', 'num'),
		'viewcat' => getVar('post', 'viewcat', 'num'),
		'catdesc' => getVar('post', 'catdesc', 'num'),
		'subcat' => getVar('post', 'subcat', 'num'),
		'mailuser' => getVar('post', 'mailuser', 'num'),
		'date' => getVar('post', 'date', 'num'),
		'read' => getVar('post', 'read', 'num'),
		'rate' => getVar('post', 'rate', 'num'),
		'letter' => getVar('post', 'letter', 'num'),
		'assoc' => getVar('post', 'assoc', 'num'),
		'mailsend' => getVar('post', 'mailsend', 'num'),
		'part' => getVar('post', 'part', 'num'),
		'partlink' => $conf['homeurl']."/index.php?name=shop&amp;op=part&amp;id=[id]",
		'sende' => $xsende,
		'userinfo' => $xuserinfo,
		'partinfo' => $xpartinfo,
		'partinfo2' => $xpartinfo2,
		'shopinfo' => $xshopinfo,
	];
	setConfigFile('shop.php', $cont);
	header("Location: ".$admin_file.".php?op=shop_conf");
}

function shop_info() {
	head();
	echo shop_navi(0, 5, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "shop", 0)."</div>";
	foot();
}

switch($op) {
	case "shop_clients":
	shop_clients();
	break;
	
	case "shop_clients_act":
	shop_clients_act();
	break;
	
	case "shop_clients_add":
	shop_clients_add();
	break;
	
	case "shop_clients_save":
	shop_clients_save();
	break;

	case "shop_clients_delete":
	shop_clients_delete();
	break;
	
	case "shop_products":
	shop_products();
	break;
	
	case "shop_products_add":
	shop_products_add();
	break;
	
	case "shop_products_save":
	shop_products_save();
	break;
	
	case "shop_products_admin":
	shop_products_admin();
	break;
	
	case "shop_partners":
	shop_partners();
	break;
	
	case "shop_partners_act":
	shop_partners_act();
	break;
	
	case "shop_partners_add":
	shop_partners_add();
	break;
	
	case "shop_partners_details":
	shop_partners_details();
	break;
	
	case "shop_partners_save":
	shop_partners_save();
	break;

	case "shop_partners_delete":
	shop_partners_delete();
	break;
	
	case "shop_export":
	shop_export();
	break;
	
	case "shop_conf":
	shop_conf();
	break;

	case "shop_conf_save":
	shop_conf_save();
	break;
	
	case "shop_info":
	shop_info();
	break;
}
?>
