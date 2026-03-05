<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('shop')) die('Illegal file access');


function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    global $afile;
    $ops = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=conf', 'name=shop&amp;op=info'];
    $lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $sops = [];
    $slang = [];
    if ($opt == 0) {
        $sops = ['name=shop&amp;op=clients', 'name=shop&amp;op=clients&amp;status=1', 'name=shop&amp;op=clients&amp;status=2', 'name=shop&amp;op=clientsadd'];
        $slang = [_NEW, _AKTIVE, _DEAKTIVE, _ADD];
    } elseif ($opt == 1) {
        $sops = ['name=shop&amp;op=products', 'name=shop&amp;op=products&amp;status=1', 'name=shop&amp;op=productsadd'];
        $slang = [_AKTIVE, _DEAKTIVE, _ADD];
    } elseif ($opt == 2) {
        $sops = ['name=shop&amp;op=partners', 'name=shop&amp;op=partners&amp;status=1', 'name=shop&amp;op=partners&amp;status=2', 'name=shop&amp;op=partnersadd'];
        $slang = [_NEW, _AKTIVE, _DEAKTIVE, _ADD];
    } elseif ($opt == 3) {
        $sops = ['', ''];
        $slang = [_EXPORT, _IMPORT];
    }
    $box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    $priv = [_ID, _NICKNAME, _CLIENTNAME, _EMAIL, _SITE];
    $search = getVar('post', 'search', 'num');
    $csearch = getVar('post', 'csearch', 'text');
    foreach ($priv as $key => $value) {
        $sort = $key + 1;
        $sel = ($search == $sort || (!$search && $sort == 2)) ? ' selected' : '';
        $box .= '<option value="'.$sort.'"'.$sel.'>'.$value.'</option>';
    }
    $box .= '</select> '.get_user_search('csearch', $csearch, '30').' <input type="hidden" name="name" value="shop"><input type="hidden" name="op" value="clients"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $box = setTemplateBasic('searchbox', ['{%searchbox%}' => $box]);
    return getAdminTabs($box, $ops, $lang, $sops, $slang, $tab, $subtab, $legacy, $id);
}

function clients(): void {
    global $db, $afile, $conf;
        $csearch = getVar('post', 'csearch', 'text');
    $search = getVar('post', 'search', 'num');
    setHead();
    $searchCols = [
        1 => 'u.user_id',
        2 => 'u.user_name',
        3 => 'c.name',
        4 => 'c.email',
        5 => 'c.website',
    ];
    $searchWhere = '';
    $searchOrder = 'c.enddate ASC';
    $searchParams = [];
    if ($csearch !== '') {
        $searchCol = $searchCols[$search] ?? 'u.user_name';
        $searchWhere = ' AND '.$searchCol.' LIKE :csearch';
        $searchOrder = $searchCol.' ASC';
        $searchParams['csearch'] = '%'.$csearch.'%';
    }
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['shop']['anum'];
    $a = ($num) ? $offset+1 : 1;
    if ($csearch) {
        $sqlstatus = 'active != \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '';
        $cont = navi(0, 0, 1, 1);
    } elseif (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'active = \'1\'';
        $field = 'name=shop&amp;op=clients&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 0, 1, 1);
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'active = \'0\'';
        $field = 'name=shop&amp;op=clients&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 0, 1, 2);
    } else {
        $sqlstatus = 'active = \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 0, 1, 0);
    }
    $result = $db->getSqlQuery('SELECT c.id, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_name, p.title FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.id_product) WHERE c.'.$sqlstatus.$searchWhere.' ORDER BY '.$searchOrder.' LIMIT '.$offset.', '.$conf['shop']['anum'], $searchParams);
    [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(c.id) FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_user) WHERE c.'.$sqlstatus.$searchWhere, $searchParams));
    $numpages = ($conf['shop']['anum'] > 0) ? (int)ceil($numstories / $conf['shop']['anum']) : 1;
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._SITE.'</th><th>'._NICKNAME.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while([$cid, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $nick, $ptitle] = $db->getSqlRow($result)) {
            $cenddate = ($cenddate != '0') ? getTimeLeft($cenddate) : _UNLIMITED;
            $cinfo = ($cinfo) ? $cinfo : _NO;
            if ($nick) {
                $name = $nick;
                $nick = user_info(search_color($nick, $csearch));
            } else {
                $name = _ANONYM;
                $nick = _ANONYM;
            }
            $cont .= '<tr><td>'.$cid.'</td>'
            .'<td>'.title_tip(_ID.': '.$a.'<br>'._DATE.': '.date(_TIMESTRING, $cregdate).'<br>'._CLIENTNAME.': '.search_color($cname, $csearch).'<br>'._CLIENTADRES.': '.$cadres.'<br>'._CLIENTPHONE.': '.$cphone.'<br>'._EMAIL.': '.$cemail.'<br>'._NOTE.': '.$cinfo).'<span title="'.$ptitle.'" class="sl_note">'.cutstr($ptitle, 40).'</span></td>'
            .'<td>'.search_color(domain($cwebsite), $csearch).'</td>'
            .'<td>'.$nick.'</td>'
            .'<td>'.$cenddate.'</td>'
            .'<td>'.ad_status('', $cactive).'</td>'
            .'<td>'.add_menu(ad_status($afile.'.php?name=shop&op=clientsact&amp;id='.$cid.$refer, $cactive).'||<a href="'.$afile.'.php?name=shop&op=clientsadd&amp;cid='.$cid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=clientsdel&amp;id='.$cid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
            $a++;
        }
        $cont .= '</tbody></table>';
        $cont .= setPageNumbers('pagenum', '', $numstories, $numpages, $conf['shop']['anum'], $field, $conf['shop']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function clientsact(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    [$active] = $db->getSqlRow($db->getSqlQuery('SELECT active FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]));
    $active = ($active) ? 0 : 1;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET active = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    setRedirect($afile.'.php?name=shop&op=clients');
}

function clientsadd(): void {
    global $db, $afile, $conf, $stop;
        if (getVar('req', 'cid', 'num', 0)) {
        $cid = getVar('req', 'cid', 'num');
        $result = $db->getSqlQuery('SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = c.id_partner) WHERE c.id = :cid', ['cid' => $cid]);
        [$cid, $uid, $product, $partner, $proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick] = $db->getSqlRow($result);
        $cregdate = date('Y-m-d H:i:s', $cregdate);
        $cenddate = ($cenddate) ? date('Y-m-d H:i:s', $cenddate) : date('Y-m-d H:i:s');
    } else {
        $cid = 0;
        $partner = getVar('post', 'partner', 'num');
        $uid = getVar('post', 'uid', 'num');
        $product = getVar('post', 'product', 'num');
        $cname = getVar('post', 'cname', 'text');
        $cadres = getVar('post', 'cadres', 'text');
        $cphone = getVar('post', 'cphone', 'text');
        $cemail = getVar('post', 'cemail', 'text');
        $cwebsite = getVar('post', 'cwebsite', 'url');
        $cregdate = getVar('post', 'cregdate', 'text', date('Y-m-d H:i:s'));
        $cenddate = getVar('post', 'cenddate', 'text', date('Y-m-d H:i:s'));
        $cinfo = getVar('post', 'cinfo', 'text');
        $cactive = getVar('post', 'cactive', 'num');
    }
    setHead();
    $cont = navi(0, 0, 1, 3);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    $cont .= setTemplateBasic('open');
    $cppi = 0;
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">';
    if ($partner) {
        if (!$proz) {
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id_partner FROM '.PREFIX_DB.'_clients WHERE id_partner = :partner AND active != 2', ['partner' => $partner]));
            if ($num >= $conf['shop']['clients2']) {
                $proz = $conf['shop']['proz2'];
            } elseif ($num >= $conf['shop']['clients1']) {
                $proz = $conf['shop']['proz1'];
            } elseif ($num >= $conf['shop']['clients']) {
                $proz = $conf['shop']['proz'];
            } else {
                $proz = '0';
            }
            $cppi = 1;
        } else {
            $cppi = 0;
        }
        $nick = ($nick) ? user_info($nick) : _ANONYM;
        $cont .= '<tr><td>'._PARTNER_NAME.':</td><td>'.$nick.'</td></tr>'
        .'<tr><td>'._PARTNER_ID.':</td><td><input type="hidden" name="partner" value="'.$partner.'">'.$partner.'</td></tr>'
        .'<tr><td>'._PERCENT.':</td><td>'.$proz.' %</td></tr>';
    }
    $cont .= '<tr><td>'._USER_ID.':</td><td><input type="number" name="uid" value="'.$uid.'" class="sl_form" placeholder="'._USER_ID.'"></td></tr>';
    $productslist = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_products ORDER BY title');
    $cont .= '<tr><td>'._PRODUCT.':</td><td><select name="product" class="sl_form">';
    while([$pid, $ptitle] = $db->getSqlRow($productslist)) {
        $cont .= '<option value="'.$pid.'\'';
        if ($product == $pid) $cont .= ' selected';
        $cont .= '>'.$ptitle.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._CLIENTNAME.':</td><td><input type="text" name="cname" value="'.$cname.'" maxlength="255" class="sl_form" placeholder="'._CLIENTNAME.'" required></td></tr>'
    .'<tr><td>'._CLIENTADRES.':</td><td><input type="text" name="cadres" value="'.$cadres.'" maxlength="255" class="sl_form" placeholder="'._CLIENTADRES.'" required></td></tr>'
    .'<tr><td>'._CLIENTPHONE.':</td><td><input type="text" name="cphone" value="'.$cphone.'" maxlength="255" class="sl_form" placeholder="'._CLIENTPHONE.'" required></td></tr>'
    .'<tr><td>'._EMAIL.':</td><td><input type="email" name="cemail" value="'.$cemail.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required></td></tr>'
    .'<tr><td>'._SITE.':</td><td><input type="url" name="cwebsite" value="'.$cwebsite.'" maxlength="255" class="sl_form" placeholder="'._SITE.'"></td></tr>'
    .'<tr><td>'._CLIENTSTR.': </td><td>'.datetime(1, 'cregdate', $cregdate, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._CLIENTEND.':</td><td>'.datetime(1, 'cenddate', $cenddate, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._NOTE.':</td><td><input type="text" name="cinfo" value="'.$cinfo.'" maxlength="255" class="sl_form" placeholder="'._NOTE.'"></td></tr>'
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($cactive, 'cactive').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="cppi" value="'.$cppi.'">'.ad_save('cid', $cid, 'clientssave', 1).'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function clientssave(): void {
    global $db, $afile, $conf, $stop;
    $partner = getVar('post', 'partner', 'num');
    $uid = getVar('post', 'uid', 'num');
    $product = getVar('post', 'product', 'num');
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
    $stop = [];
    checkemail($cemail);
    if (!$cname || !$cadres || !$cphone) $stop[] = _ERROR_ALL;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($cid) {
            if ($partner && $cppi) {
                [$ppreis] = $db->getSqlRow($db->getSqlQuery('SELECT preis FROM '.PREFIX_DB.'_products WHERE id = :product', ['product' => $product]));
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id_partner FROM '.PREFIX_DB.'_clients WHERE id_partner = :partner AND active != 2', ['partner' => $partner]));
                if ($num >= $conf['shop']['clients2']) {
                    $conf['shop']['proz2'] = ($conf['shop']['proz2']) ? $conf['shop']['proz2'] : 1;
                    $preis = $ppreis / 100 * $conf['shop']['proz2'];
                    $proz = $conf['shop']['proz2'];
                } elseif ($num >= $conf['shop']['clients1']) {
                    $conf['shop']['proz1'] = ($conf['shop']['proz1']) ? $conf['shop']['proz1'] : 1;
                    $preis = $ppreis / 100 * $conf['shop']['proz1'];
                    $proz = $conf['shop']['proz1'];
                } elseif ($num >= $conf['shop']['clients']) {
                    $conf['shop']['proz'] = ($conf['shop']['proz']) ? $conf['shop']['proz'] : 1;
                    $preis = $ppreis / 100 * $conf['shop']['proz'];
                    $proz = $conf['shop']['proz'];
                }
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET rest = rest+:end_preis WHERE id_user = :partner', ['end_preis' => $preis, 'partner' => $partner]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET id_user = :uid, id_product = :product, id_partner = :partner, partner_proz = :cpartner_proz, name = :cname, adres = :cadres, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, active = :cactive WHERE id = :cid', ['uid' => $uid, 'product' => $product, 'partner' => $partner, 'cpartner_proz' => $proz, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET id_user = :uid, id_product = :product, name = :cname, adres = :cadres, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, active = :cactive WHERE id = :cid', ['uid' => $uid, 'product' => $product, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
            }
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients VALUES(NULL, :uid, :product, \'0\', \'0\', :cname, :cadres, :cphone, :cemail, :cwebsite, :cregdate, :cenddate, :cinfo, :cactive)', ['uid' => $uid, 'product' => $product, 'cname' => $cname, 'cadres' => $cadres, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive]);
        }
        setRedirect($afile.'.php?name=shop&op=clients');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        clientsdel($cid);
    } else {
        clientsadd();
    }
}

function clientsdel(int $id = 0): void {
    global $db, $afile;
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=clients');
}

function products(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num-1) * $conf['shop']['anum'];
    $offset = intval($offset);
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'active=0';
        $field = 'name=shop&amp;op=products&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(1, 1, 1, 1);
    } else {
        $sqlstatus = 'active=1';
        $field = 'name=shop&amp;op=products&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(1, 1, 1, 0);
    }
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.preis, p.vote, p.active, c.title FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE '.$sqlstatus.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
        .'<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th><th class="{sorter: false}"><input type="checkbox" name="markcheck" id="markcheck" title="'._CHECKALL.'" OnClick="CheckBox(\'#markcheck\', \'.sl_check\')"></th></tr></thead><tbody>';
        while([$pid, $pcid, $ptime, $ptitle, $ppreis, $pvote, $pactive, $ctitle] = $db->getSqlRow($result)) {
            $ctitle = ($pcid) ? $ctitle : _NO;
            if ($pactive && time() >= strtotime($ptime)) {
                $view = '<a href="index.php?name=shop&amp;op=view&amp;id='.$pid.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $vote = ($pvote) ? '<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$pvote.'" title="'._EDITVOTE.'">'._EDITVOTE.'</a>||' : '';
            $typ = ($pactive) ? '0' : '1';
            $cont .= '<tr><td>'.$pid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($ptime ?? '', _TIMESTRING)).'<span title="'.$ptitle.'" class="sl_note">'.cutstr($ptitle, 60).'</span></td>'
            .'<td>'.$ppreis.' '.$conf['shop']['valute'].'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.$vote.ad_status($afile.'.php?name=shop&op=productsadmin&amp;typ=a'.$typ.'&amp;id='.$pid.$refer, $pactive).'||<a href="'.$afile.'.php?name=shop&op=productsadd&amp;id='.$pid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=productsadmin&amp;typ=d&amp;id='.$pid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$ptitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td>'
            .'<td><input type="checkbox" name="id[]" class="sl_check" value="'.$pid.'"></td></tr>';
        }
        $cont .= '</tbody></table>';
        $selms = _CHECKOP.': '.edit_list('shop', 'typ', '').' <input type="hidden" name="name" value="shop"><input type="hidden" name="op" value="productsadmin"><input type="hidden" name="refer" value="1"> <input type="submit" value="'._OK.'" class="sl_but_blue">';
        $numpt = setArticleNumbers('pagenum', '', $conf['shop']['anum'], $field, 'id', '_products', '', $sqlstatus, $conf['shop']['anump']);
        $cont .= '<table class="searchboxtab"><tr><td>'.$numpt.'</td><td><div class="searchbox">'.$selms.'</div></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function productsadd(): void {
    global $db, $afile, $conf, $stop;
    if (getVar('req', 'id', 'num', 0)) {
        $id = getVar('req', 'id', 'num');
        $result = $db->getSqlQuery('SELECT id, cid, time, title, text, bodytext, preis, vote, assoc, ihome, acomm, count, fix, active FROM '.PREFIX_DB.'_products WHERE id = :id', ['id' => $id]);
        [$pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $ppreis, $vote, $passoc, $ihome, $acomm, $pcount, $fix, $pactive] = $db->getSqlRow($result);
        $associated = explode(',', $passoc);
    } else {
        $pid = getVar('post', 'pid', 'num');
        $pcid = getVar('post', 'pcid', 'num');
        $ptitle = getVar('post', 'ptitle', 'title');
        $ptext = getVar('post', 'ptext', 'text');
        $pbodytext = getVar('post', 'pbodytext', 'text');
        $ppreis = getVar('post', 'ppreis', 'text');
        $vote = getVar('post', 'vote', 'num');
        $ptime = getVar('req', 'ptime', 'time');
        $associated = getVar('post', 'associated', 'array');
        $ihome = getVar('post', 'ihome', 'num');
        $acomm = getVar('post', 'acomm', 'num');
        $fix = getVar('post', 'fix', 'num');
        $pactive = getVar('post', 'pactive', 'num');
    }
    setHead();
    $cont = navi(1, 1, 1, 2);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    $ptextpre = ($vote) ? '<div id="repshop">'.getVoting($vote, 'shop').'</div><hr>'.$ptext : $ptext;
    if ($ptextpre) $cont .= preview($ptitle, $ptextpre, $pbodytext, '', 'shop');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._TITLE.' / '._PRODUCT.':</td><td><input type="text" name="ptitle" value="'.$ptitle.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('shop', $pcid, 'pcid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>';
    $result2 = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY parentid, title', ['modul' => 'shop']);
    if ($db->getSqlRowCount($result2) > 0) {
        $cont .= '<tr><td>'._ASSOTOPIC.':<div class="sl_small">'._ASSOTOPICI.'</div></td><td><table class="sl_form"><tr>';
        while ([$id, $title] = $db->getSqlRow($result2)) {
            if ($a == 2) {
                $cont .= '</tr><tr>';
                $a = 0;
            }
            $check = '';
            if ($associated) foreach ($associated as $val) if ($val == $id) $check = ' checked';
            $cont .= '<td><input type="checkbox" name="associated[]" value="'.$id.'\''.$check.'> '.$title.'</td>';
            $a++;
        }
        $cont .= '</tr></table></td></tr>';
    }
    $cont .= '<tr><td>'._TEXT.':</td><td>'.textarea('1', 'ptext', $ptext, 'shop', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'pbodytext', $pbodytext, 'shop', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._PREIS.':</td><td><input type="text" name="ppreis" value="'.$ppreis.'" maxlength="10" class="sl_form" placeholder="'._PREIS.'" required></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'ptime', $ptime, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._VOTING.':</td><td>'.add_voting('shop', 'vote', $vote, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td>'._FIXED.'?</td><td>'.radio_form($fix, 'fix').'</td></tr>'
    .'<tr><td>'._ACTIVATEP.'</td><td>'.radio_form($pactive, 'pactive').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center">'.ad_save('pid', $pid, 'productssave').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function productssave(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num');
    $pcid = getVar('post', 'pcid', 'num');
    $ptitle = getVar('post', 'ptitle', 'title');
    $associated = implode(',', getVar('post', 'associated', 'array', []));
    $ptext = getVar('post', 'ptext', 'text');
    $pbodytext = getVar('post', 'pbodytext', 'text');
    $ppreis = getVar('post', 'ppreis', 'text');
    $vote = getVar('post', 'vote', 'num');
    $ihome = getVar('post', 'ihome', 'num');
    $acomm = getVar('post', 'acomm', 'num');
    $fix = getVar('post', 'fix', 'num');
    $pactive = getVar('post', 'pactive', 'num');
    $ptime = getVar('req', 'ptime', 'time');
    $stop = [];
    if (!$ptitle || !$ptext || !$ppreis) $stop[] = _ERROR_ALL;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($pid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET cid = :pcid, time = :ptime, title = :ptitle, text = :ptext, bodytext = :pbodytext, preis = :ppreis, vote = :vote, assoc = :associated, ihome = :ihome, acomm = :acomm, fix = :fix, active = :pactive WHERE id = :pid', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'ppreis' => $ppreis, 'vote' => $vote, 'associated' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive, 'pid' => $pid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_products VALUES (NULL, :pcid, :ptime, :ptitle, :ptext, :pbodytext, :ppreis, :vote, :associated, :ihome, :acomm, \'0\', \'0\', \'0\', \'0\', :fix, :pactive)', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'ppreis' => $ppreis, 'vote' => $vote, 'associated' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive]);
        }
        setRedirect($afile.'.php?name=shop&op=products');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        productsadmin($pid, 'd');
    } else {
        productsadd();
    }
}

function productsadmin(int|array $id = 0, string $vtyp = ''): void {
    global $db, $afile;
    $id = getVar('req', 'id', 'array', []);
    $arg = $id;
    if (!is_array($arg) || $arg === []) {
        $id = getVar('req', 'id', 'num', 0);
        $single = $id;
        $arg = ($single > 0) ? [$single] : [];
    }
    if (!is_array($id)) $id = ($id > 0) ? [$id] : [];
    $ids = array_unique(array_filter(array_map('intval', array_merge($arg, $id)), static fn($v): bool => $v > 0));
    $id = (is_array($ids) && $ids !== []) ? implode(',', $ids) : 0;
    $typ = getVar('post', 'typ', 'text');
    if (!$typ) $typ = getVar('get', 'typ', 'text');
    $vtyp = ($typ) ? filterVar($typ) : $vtyp;
    $typ = (is_numeric($vtyp[0])) ? intval($vtyp) : intval(substr($vtyp, 1));
    if ($id) {
        if ($vtyp[0] == 'a') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET active = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
        } elseif ($vtyp[0] == 'f') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET fix = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
        } elseif ($vtyp[0] == 'h') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET ihome = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
        } elseif ($vtyp[0] == 't') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET time = now() WHERE id IN ('.$id.')');
        } elseif ($vtyp[0] == 'c') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET acomm = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
        } elseif ($vtyp[0] == 'd') {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.$id.') AND modul = \'shop\'');
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid IN ('.$id.') AND modul = \'shop\'');
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_products WHERE id IN ('.$id.')');
        } elseif (is_numeric($vtyp[0])) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET cid = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
        }
    }
    setRedirect($afile.'.php?name=shop&op=products');
}

function partners(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['shop']['anum'];
    $offset = intval($offset);
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'active=1';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(2, 2, 1, 1);
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'active=0';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(2, 2, 1, 2);
    } else {
        $sqlstatus = 'active=2';
        $field = 'name=shop&amp;op=partners&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(2, 2, 1, 0);
    }
    $result = $db->getSqlQuery('SELECT p.id, p.name, p.adres, p.phone, p.email, p.website, p.regdate, p.rest, p.bek, p.active, u.user_name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = p.id_user) WHERE '.$sqlstatus.' LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PARTNERREST.'</th><th>'._PARTNERBEK.'</th><th>'._SITE.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while([$paid, $paname, $paadres, $paphone, $paemail, $pawebsite, $paregdate, $parest, $pabek, $paactive, $nick] = $db->getSqlRow($result)) {
            if ($nick) {
                $name = $nick;
                $nick = user_info(search_color($nick, ''));
            } else {
                $name = _ANONYM;
                $nick = _ANONYM;
            }
            $cont .= '<tr><td>'.$paid.'</td>'
            .'<td>'.title_tip(_CLIENTNAME.': '.$paname.'<br>'._CLIENTADRES.': '.$paadres.'<br>'._CLIENTPHONE.': '.$paphone.'<br>'._EMAIL.': '.$paemail).$nick.'</td>'
            .'<td>'.$parest.' '.$conf['shop']['valute'].'</td>'
            .'<td>'.$pabek.' '.$conf['shop']['valute'].'</td>'
            .'<td>'.domain($pawebsite).'</td>'
            .'<td>'.date(_TIMESTRING, $paregdate).'</td>'
            .'<td>'.add_menu(ad_status($afile.'.php?name=shop&op=partnersact&amp;id='.$paid.$refer, $paactive).'||<a href="'.$afile.'.php?name=shop&op=partnersdetails&amp;paid='.$paid.'" title="'._MVIEW.'">'._MVIEW.'</a>||<a href="'.$afile.'.php?name=shop&op=partnersadd&amp;paid='.$paid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=partnersdel&amp;id='.$paid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $conf['shop']['anum'], $field, 'id', '_partners', '', $sqlstatus, $conf['shop']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function partnersact(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    [$active] = $db->getSqlRow($db->getSqlQuery('SELECT active FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]));
    $active = ($active == 1) ? 0 : 1;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET active = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    setRedirect($afile.'.php?name=shop&op=partners');
}

function partnersadd(): void {
    global $db, $afile, $stop;
    if (getVar('req', 'paid', 'num', 0)) {
        $paid = getVar('req', 'paid', 'num');
        $result = $db->getSqlQuery('SELECT p.id, p.id_user, p.name, p.adres, p.phone, p.email, p.website, p.webmoney, p.paypal, p.regdate, p.rest, p.bek, p.active, u.user_name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id = p.id_user) WHERE p.id = :paid', ['paid' => $paid]);
        [$paid, $uid, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive, $nick] = $db->getSqlRow($result);
        $paregdate = ($paregdate) ? date('Y-m-d H:i:s', $paregdate) : date('Y-m-d H:i:s');
    } else {
        $paid = 0;
        $uid = getVar('post', 'uid', 'num');
        $paname = getVar('post', 'paname', 'text');
        $paadres = getVar('post', 'paadres', 'text');
        $paphone = getVar('post', 'paphone', 'text');
        $paemail = getVar('post', 'paemail', 'text');
        $pawebsite = getVar('post', 'pawebsite', 'url');
        $pawebmoney = getVar('post', 'pawebmoney', 'text');
        $papaypal = getVar('post', 'papaypal', 'text');
        $paregdate = getVar('post', 'paregdate', 'text', date('Y-m-d H:i:s'));
        $parest = getVar('post', 'parest', 'text');
        $pabek = getVar('post', 'pabek', 'text');
        $paactive = getVar('post', 'paactive', 'num');
    }
    setHead();
    $cont = navi(2, 2, 1, 3);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">';
    if ($paid) {
        $nick = ($nick) ? user_info($nick) : _ANONYM;
        $cont .= '<tr><td>'._NICKNAME.':</td><td>'.$nick.'</td></tr>';
    }
    $cont .= '<tr><td>'._USER_ID.':</td><td>';
    $cont .= ($uid == 0) ? '<input type="number" name="uid" value="'.$uid.'" class="sl_form" placeholder="'._USER_ID.'" required>' : '<input type="hidden" name="uid" value="'.$uid.'">'.$uid;
    $cont .= '</td></tr><tr><td>'._CLIENTNAME.':</td><td><input type="text" name="paname" value="'.$paname.'" maxlength="255" class="sl_form" placeholder="'._CLIENTNAME.'" required></td></tr>'
    .'<tr><td>'._CLIENTADRES.':</td><td><input type="text" name="paadres" value="'.$paadres.'" maxlength="255" class="sl_form" placeholder="'._CLIENTADRES.'" required></td></tr>'
    .'<tr><td>'._CLIENTPHONE.':</td><td><input type="text" name="paphone" value="'.$paphone.'" maxlength="255" class="sl_form" placeholder="'._CLIENTPHONE.'" required></td></tr>'
    .'<tr><td>'._EMAIL.':</td><td><input type="email" name="paemail" value="'.$paemail.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required></td></tr>'
    .'<tr><td>'._SITE.':</td><td><input type="url" name="pawebsite" value="'.$pawebsite.'" maxlength="255" class="sl_form" placeholder="'._SITE.'"></td></tr>'
    .'<tr><td>'._WEBMONEY.':</td><td><input type="text" name="pawebmoney" value="'.$pawebmoney.'" maxlength="255" class="sl_form" placeholder="'._WEBMONEY.'"></td></tr>'
    .'<tr><td>'._PAYPAL.':</td><td><input type="text" name="papaypal" value="'.$papaypal.'" maxlength="255" class="sl_form" placeholder="'._PAYPAL.'"></td></tr>'
    .'<tr><td>'._REG.':</td><td>'.datetime(1, 'paregdate', $paregdate, 16, 'sl_form').'</td></tr>';
    if ($paactive != 2) {
        $cont .= '<tr><td>'._PARTNERREST.':</td><td><input type="text" name="parest" value="'.$parest.'" maxlength="255" class="sl_form" placeholder="'._PARTNERREST.'"></td></tr>'
        .'<tr><td>'._PARTNERBEK.':</td><td><input type="text" name="pabek" value="'.$pabek.'" maxlength="255" class="sl_form" placeholder="'._PARTNERBEK.'"></td></tr>';
    }
    $cont .= '<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($paactive, 'paactive').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center">'.ad_save('paid', $paid, 'partnerssave', 1).'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function partnerssave(): void {
    global $db, $afile, $stop;
    $uid = getVar('post', 'uid', 'num');
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
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($paid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET id_user = \''.$uid.'\', name = \''.$paname.'\', adres = \''.$paadres.'\', phone = \''.$paphone.'\', email = \''.$paemail.'\', website = \''.$pawebsite.'\', webmoney = \''.$pawebmoney.'\', paypal = \''.$papaypal.'\', regdate = \''.$paregdate.'\', rest = \''.$parest.'\', bek = \''.$pabek.'\', active = \''.$paactive.'" WHERE id = \''.$paid.'\'');
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, \''.$uid.'\', \''.$paname.'\', \''.$paadres.'\', \''.$paphone.'\', \''.$paemail.'\', \''.$pawebsite.'\', \''.$pawebmoney.'\', \''.$papaypal.'\', \''.$paregdate.'\', \''.$parest.'\', \''.$pabek.'\', \''.$paactive.'\')');
        }
        setRedirect($afile.'.php?name=shop&op=partners');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        partnersdel($paid);
    } else {
        partnersadd();
    }
}

function partnersdel(int $id = 0): void {
    global $db, $afile;
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=partners');
}

function partnersdetails(): void {
    global $db, $afile, $conf;
        $paid = getVar('get', 'paid', 'num');
    setHead();
    $cont = navi(2, 2, 1, 1);
    $result = $db->getSqlQuery('SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM '.PREFIX_DB.'_partners WHERE id = :paid', ['paid' => $paid]);
    [$paid, $uid, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result);
    $result = $db->getSqlQuery('SELECT c.id, c.id_user, c.id_product, c.id_partner, c.partner_proz, c.name, c.adres, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.active, u.user_id, u.user_name, p.id, p.title, p.preis FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.user_id=c.id_user) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id=c.id_product) WHERE c.id_partner = :uid AND c.active != 2 ORDER BY c.id ASC', ['uid' => $uid]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th><th>'._PERCENT.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._SUM.'</th></tr></thead><tbody>';
        $partsum = 0;
        $partsumges = 0;
        $a = 0;
        while([$cid, $uid, $product, $partner, $proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $ptitle, $ppreis] = $db->getSqlRow($result)) {
            $partsum = $ppreis / 100 * $proz;
            $partsumges += $partsum;
            $cont .= '<tr><td>'.$cid.'</td>'
            .'<td>'.user_info($nick).'</td>'
            .'<td>'.$ptitle.'</td>'
            .'<td>'.$ppreis.' '.$conf['shop']['valute'].'</td>'
            .'<td>'.$proz.' %</td>'
            .'<td>'.date(_TIMESTRING, $cregdate).'</td>'
            .'<td>'.$partsum.' '.$conf['shop']['valute'].'</td></tr>';
            $a++;
        }
        $cont .= '</tbody></table>';
        $cont .= setTemplateBasic('close');
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._CLIENTEN.'</th><th>'._WEBMONEY.'</th><th>'._PAYPAL.'</th><th>'._PARTNERGES.'</th><th>'._PARTNERREST.'</th><th class="{sorter: false}">'._PARTNERBEK.'</th></tr></thead><tbody>'
    .'<tr><td>'.$a.'</td>'
    .'<td>'.$pawebmoney.'</td>'
    .'<td>'.$papaypal.'</td>'
    .'<td>'.$partsumges.' '.$conf['shop']['valute'].'</td>'
    .'<td>'.$parest.' '.$conf['shop']['valute'].'</td>'
    .'<td>'.$pabek.' '.$conf['shop']['valute'].'</td></tr></tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function exportdata(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $bd = getVar('post', 'bd', 'text');
    if ($id == 1 && $bd) {
        $list = [];
        if ($bd == 'products') {
            $result = $db->getSqlQuery('SELECT id, cid, time, title, text, bodytext, preis, vote, assoc, com, count, votes, totalvotes, fix, active FROM '.PREFIX_DB.'_products ORDER BY id');
            while([$pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $ppreis, $pvote, $passoc, $pcom, $pcount, $pvotes, $ptotalvotes, $pfix, $pactive] = $db->getSqlRow($result)) {
                $list[] = $pid.'||'.$pcid.'||'.$ptime.'||'.$ptitle.'||'.$ptext.'||'.$pbodytext.'||'.$ppreis.'||'.$pvote.'||'.$passoc.'||'.$pcom.'||'.$pcount.'||'.$pvotes.'||'.$ptotalvotes.'||'.$pfix.'||'.$pactive;
            }
        } elseif ($bd == 'clients') {
            $result = $db->getSqlQuery('SELECT id, id_user, id_product, id_partner, partner_proz, name, adres, phone, email, website, regdate, enddate, info, active FROM '.PREFIX_DB.'_clients ORDER BY id');
            while([$cid, $uid, $product, $partner, $proz, $cname, $cadres, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive] = $db->getSqlRow($result)) {
                $list[] = $cid.'||'.$uid.'||'.$product.'||'.$partner.'||'.$proz.'||'.$cname.'||'.$cadres.'||'.$cphone.'||'.$cemail.'||'.$cwebsite.'||'.$cregdate.'||'.$cenddate.'||'.$cinfo.'||'.$cactive;
            }
        } elseif ($bd == 'partners') {
            $result = $db->getSqlQuery('SELECT id, id_user, name, adres, phone, email, website, webmoney, paypal, regdate, rest, bek, active FROM '.PREFIX_DB.'_partners ORDER BY id');
            while([$paid, $uid, $paname, $paadres, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result)) {
                $list[] = $paid.'||'.$uid.'||'.$paname.'||'.$paadres.'||'.$paphone.'||'.$paemail.'||'.$pawebsite.'||'.$pawebmoney.'||'.$papaypal.'||'.$paregdate.'||'.$parest.'||'.$pabek.'||'.$paactive;
            }
        }
        if ($list) {
            $date = date('d.m.Y');
            $fp = fopen('uploads/shop/temp/'.$date.'_'.$bd.'.csv', 'wb');
            foreach ($list as $val) fputcsv($fp, explode('||', $val));

            fclose($fp);
            stream('uploads/shop/temp/'.$date.'_'.$bd.'.csv', $date.'_'.$bd.'.csv');
        } else {
            setRedirect($afile.'.php?name=shop&op=export');
        }
    } elseif ($id == 2 && $bd) {
        $handle = fopen ('uploads/shop/temp/'.$bd,'rb');
        while (($data = fgetcsv($handle, 1000, ','))) {
            if (preg_match('#(.*?)products\\.csv#', $bd)) {
                $iid = 'id';
                $idb = 'products';
                $uquery = 'cid = \''.$data[1].'\', time = \''.$data[2].'\', title = \''.$data[3].'\', text = \''.$data[4].'\', bodytext = \''.$data[5].'\', preis = \''.$data[6].'\', vote = \''.$data[7].'\', assoc = \''.$data[7].'\', com = \''.$data[9].'\', count = \''.$data[10].'\', votes = \''.$data[11].'\', totalvotes = \''.$data[12].'\', fix = \''.$data[13].'\', active = \''.$data[14].'\'';
                $squery = '\''.$data[1].'\', \''.$data[2].'\', \''.$data[3].'\', \''.$data[4].'\', \''.$data[5].'\', \''.$data[6].'\', \''.$data[7].'\', \''.$data[8].'\', \''.$data[9].'\', \''.$data[10].'\', \''.$data[11].'\', \''.$data[12].'\'';
            } elseif (preg_match('#(.*?)clients\\.csv#', $bd)) {
                $iid = 'id';
                $idb = 'clients';
                $uquery = 'id_user = \''.$data[1].'\', id_product = \''.$data[2].'\', id_partner = \''.$data[3].'\', partner_proz = \''.$data[4].'\', name = \''.$data[5].'\', adres = \''.$data[6].'\', phone = \''.$data[7].'\', email = \''.$data[8].'\', website = \''.$data[9].'\', regdate = \''.$data[10].'\', enddate = \''.$data[11].'\', info = \''.$data[12].'\', active = \''.$data[13].'\'';
                $squery = '\''.$data[1].'\', \''.$data[2].'\', \''.$data[3].'\', \''.$data[4].'\', \''.$data[5].'\', \''.$data[6].'\', \''.$data[7].'\', \''.$data[8].'\', \''.$data[9].'\', \''.$data[10].'\', \''.$data[11].'\', \''.$data[12].'\', \''.$data[13].'\'';
            } elseif (preg_match('#(.*?)partners\\.csv#', $bd)) {
                $iid = 'id';
                $idb = 'partners';
                $uquery = 'id_user = \''.$data[1].'\', name = \''.$data[2].'\', adres = \''.$data[3].'\', phone = \''.$data[4].'\', email = \''.$data[5].'\', website = \''.$data[6].'\', webmoney = \''.$data[7].'\', paypal = \''.$data[8].'\', regdate = \''.$data[9].'\', rest = \''.$data[10].'\', bek = \''.$data[11].'\', active = \''.$data[12].'\'';
                $squery = '\''.$data[1].'\', \''.$data[2].'\', \''.$data[3].'\', \''.$data[4].'\', \''.$data[5].'\', \''.$data[6].'\', \''.$data[7].'\', \''.$data[8].'\', \''.$data[9].'\', \''.$data[10].'\', \''.$data[11].'\', \''.$data[12].'\'';
            }
            $id = intval($data[0]);
            if ($id) {
                if ($db->getSqlRowCount($db->getSqlQuery('SELECT '.$iid.' FROM '.PREFIX_DB.'_'.$idb.' WHERE '.$iid.' = :id', ['id' => $id]))) {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_'.$idb.' SET '.$uquery.' WHERE '.$iid.' = :id', ['id' => $id]);
                } else {
                    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_'.$idb.' VALUES(:id, '.$squery.')', ['id' => $id]);
                }
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_'.$idb.' VALUES(NULL, '.$squery.')');
            }
        }
        fclose ($handle);
        setRedirect($afile.'.php?name=shop&op='.$idb);
    } else {
        setHead();
        $cont = navi(3, 3, 1, 0, 'export');
        $cont .= checkPerms(BASE_DIR.'/uploads/shop/temp');
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _S_NOTE]);
        [$pr] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_products'));
        [$cl] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_clients'));
        [$pa] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_partners'));
        $content = '<div id="tabcs0" class="tabcont">';
        if ($pr || $cl || $pa) {
            $content .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
            .'<tr><td>'._DATABASE.':</td><td><select name="bd" class="sl_form">';
            $content .= ($pr) ? '<option value="products">'._PRODUCTS.'</option>' : '';
            $content .= ($cl) ? '<option value="clients">'._CLIENTS.'</option>' : '';
            $content .= ($pa) ? '<option value="partners">'._PARTNERS.'</option>' : '';
            $content .= '</select></td></tr><tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="shop"><input type="hidden" name="id" value="1"><input type="hidden" name="op" value="export"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
        } else {
            $content .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
        $content .= '</div><div id="tabcs1" class="tabcont">';
        $ocont = '';
        $entries = scandir('uploads/shop/temp');
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if (preg_match('/(\\.csv)$/is', $entry) && $entry != '.' && $entry != '..') {
                    $in = ['#(.*?)products\\.csv#', '#(.*?)clients\\.csv#', '#(.*?)partners\\.csv#'];
                    $out = [_PRODUCTS, _CLIENTS, _PARTNERS];
                    $name = preg_replace($in, $out, $entry);
                    $ocont .= '<option value="'.$entry.'">'.$name.' - '.$entry.'</option>';
                }
            }
        }
        if ($ocont) {
            $content .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
            .'<tr><td>'._FILE.':</td><td><select name="bd" class="sl_form">'.$ocont.'</select></td></tr><tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="shop"><input type="hidden" name="id" value="2"><input type="hidden" name="op" value="export"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
        } else {
            $content .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
        $content .= '</div>'
        .'<script>
        var countries=new ddtabcontent(\'exports\')
        countries.setpersist(true)
        countries.setselectedClassTarget(\'link\')
        countries.init()
        </script>';
        $cont .= setTemplateBasic('open').$content.setTemplateBasic('close');
        echo $cont;
        setFoot();
    }
}

function shop(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 4, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/shop.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['shop']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._C_0.':</td><td><input type="number" name="clients" value="'.$conf['shop']['clients'].'" class="sl_conf" placeholder="'._C_0.'" required></td></tr>'
    .'<tr><td>'._C_1.':</td><td><input type="number" name="proz" value="'.$conf['shop']['proz'].'" class="sl_conf" placeholder="'._C_1.'" required></td></tr>'
    .'<tr><td>'._C_2.':</td><td><input type="number" name="clients1" value="'.$conf['shop']['clients1'].'" class="sl_conf" placeholder="'._C_2.'" required></td></tr>'
    .'<tr><td>'._C_3.':</td><td><input type="number" name="proz1" value="'.$conf['shop']['proz1'].'" class="sl_conf" placeholder="'._C_3.'" required></td></tr>'
    .'<tr><td>'._C_4.':</td><td><input type="number" name="clients2" value="'.$conf['shop']['clients2'].'" class="sl_conf" placeholder="'._C_4.'" required></td></tr>'
    .'<tr><td>'._C_5.':</td><td><input type="number" name="proz2" value="'.$conf['shop']['proz2'].'" class="sl_conf" placeholder="'._C_5.'" required></td></tr>'
    .'<tr><td>'._C_6.':</td><td><input type="text" name="valute" value="'.$conf['shop']['valute'].'" maxlength="25" class="sl_conf" placeholder="'._C_6.'" required></td></tr>'
    .'<tr><td>'._C_7.':</td><td><input type="email" name="mail" value="'.$conf['shop']['mail'].'" maxlength="25" class="sl_conf" placeholder="'._C_7.'" required></td></tr>'
    .'<tr><td>'._C_8.':</td><td><input type="number" name="shop" value="'.intval($conf['shop']['shop_t'] / 86400).'" class="sl_conf" placeholder="'._C_8.'" required></td></tr>'
    .'<tr><td>'._C_9.':</td><td><input type="number" name="part" value="'.intval($conf['shop']['part_t'] / 86400).'" class="sl_conf" placeholder="'._C_9.'" required></td></tr>'
    .'<tr><td>'._BASCOL.':</td><td><input type="number" name="bascol" value="'.$conf['shop']['bascol'].'" class="sl_conf" placeholder="'._BASCOL.'" required></td></tr>'
    .'<tr><td>'._C_11.':</td><td><input type="number" name="assocnum" value="'.$conf['shop']['assocnum'].'" class="sl_conf" placeholder="'._C_11.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.$conf['shop']['listnum'].'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conf['shop']['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['shop']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conf['shop']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['shop']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($conf['shop']['homcat'], 'homcat').'</td></tr>'
    .'<tr><td>'._VIEWCAT.'</td><td>'.radio_form($conf['shop']['viewcat'], 'viewcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($conf['shop']['catdesc'], 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($conf['shop']['subcat'], 'subcat').'</td></tr>'
    .'<tr><td>'._C_14.'</td><td>'.radio_form($conf['shop']['mailuser'], 'mailuser').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($conf['shop']['date'], 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($conf['shop']['read'], 'read').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($conf['shop']['rate'], 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($conf['shop']['letter'], 'letter').'</td></tr>'
    .'<tr><td>'._C_23.'</td><td>'.radio_form($conf['shop']['assoc'], 'assoc').'</td></tr>'
    .'<tr><td>'._C_24.'</td><td>'.radio_form($conf['shop']['mailsend'], 'mailsend').'</td></tr>'
    .'<tr><td>'._C_25.'</td><td>'.radio_form($conf['shop']['part'], 'part').'</td></tr>'
    .'<tr><td>'._C_26.':<div class="sl_small">'._PART_ID.'</div></td><td><input type="url" name="partlink" value="'.$conf['shop']['partlink'].'" maxlength="25" class="sl_conf" placeholder="'._C_26.'" required></td></tr>'
    .'<tr><td>'._C_27.':</td><td>'.textarea('1', 'sende', $conf['shop']['sende'], 'shop', '5', _C_27, '1').'</td></tr>'
    .'<tr><td>'._C_28.':</td><td>'.textarea('2', 'userinfo', $conf['shop']['userinfo'], 'shop', '5', _C_28, '1').'</td></tr>'
    .'<tr><td>'._C_29.':</td><td>'.textarea('3', 'partinfo', $conf['shop']['partinfo'], 'shop', '5', _C_29, '1').'</td></tr>'
    .'<tr><td>'._C_30.':</td><td>'.textarea('4', 'partinfo2', $conf['shop']['partinfo2'], 'shop', '5', _C_30, '1').'</td></tr>'
    .'<tr><td>'._C_31.':</td><td>'.textarea('5', 'shopinfo', $conf['shop']['shopinfo'], 'shop', '5', _C_31, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="shop"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $conf;
        $defis = getVar('post', 'defis', 'text', urldecode($conf['shop']['defis'] ?? '%3E'));
    $xdefis = ($defis) ? urlencode($defis) : '%3E';
    $shop = getVar('post', 'shop', 'num', (int)(($conf['shop']['shop_t'] ?? 2592000) / 86400));
    $xtshop = (!$shop) ? 2592000 : intval($shop * 86400);
    $part = getVar('post', 'part', 'num', (int)(($conf['shop']['part_t'] ?? 2592000) / 86400));
    $xtpart = (!$part) ? 2592000 : intval($part * 86400);
    $bascol = getVar('post', 'bascol', 'num', (int)($conf['shop']['bascol'] ?? 1));
    $xcol = (!$bascol) ? '1' : $bascol;
    $sende = getVar('post', 'sende', 'text', $conf['shop']['sende'] ?? '');
    $userinfo = getVar('post', 'userinfo', 'text', $conf['shop']['userinfo'] ?? '');
    $partinfo = getVar('post', 'partinfo', 'text', $conf['shop']['partinfo'] ?? '');
    $partinfo2 = getVar('post', 'partinfo2', 'text', $conf['shop']['partinfo2'] ?? '');
    $shopinfo = getVar('post', 'shopinfo', 'text', $conf['shop']['shopinfo'] ?? '');
    $cont = [
        'defis' => $xdefis,
        'clients' => getVar('post', 'clients', 'num', (int)($conf['shop']['clients'] ?? 1)),
        'clients1' => getVar('post', 'clients1', 'num', (int)($conf['shop']['clients1'] ?? 1)),
        'clients2' => getVar('post', 'clients2', 'num', (int)($conf['shop']['clients2'] ?? 1)),
        'proz' => getVar('post', 'proz', 'num', (int)($conf['shop']['proz'] ?? 1)),
        'proz1' => getVar('post', 'proz1', 'num', (int)($conf['shop']['proz1'] ?? 1)),
        'proz2' => getVar('post', 'proz2', 'num', (int)($conf['shop']['proz2'] ?? 1)),
        'valute' => getVar('post', 'valute', 'text', $conf['shop']['valute'] ?? ''),
        'mail' => getVar('post', 'mail', 'text', $conf['shop']['mail'] ?? ''),
        'shop_t' => $xtshop,
        'part_t' => $xtpart,
        'bascol' => $xcol,
        'assocnum' => getVar('post', 'assocnum', 'num', (int)($conf['shop']['assocnum'] ?? 10)),
        'listnum' => getVar('post', 'listnum', 'num', (int)($conf['shop']['listnum'] ?? 10)),
        'num' => getVar('post', 'num', 'num', (int)($conf['shop']['num'] ?? 10)),
        'anum' => getVar('post', 'anum', 'num', (int)($conf['shop']['anum'] ?? 10)),
        'nump' => getVar('post', 'nump', 'num', (int)($conf['shop']['nump'] ?? 10)),
        'anump' => getVar('post', 'anump', 'num', (int)($conf['shop']['anump'] ?? 10)),
        'homcat' => getVar('post', 'homcat', 'num', (int)($conf['shop']['homcat'] ?? 1)),
        'viewcat' => getVar('post', 'viewcat', 'num', (int)($conf['shop']['viewcat'] ?? 1)),
        'catdesc' => getVar('post', 'catdesc', 'num', (int)($conf['shop']['catdesc'] ?? 1)),
        'subcat' => getVar('post', 'subcat', 'num', (int)($conf['shop']['subcat'] ?? 1)),
        'mailuser' => getVar('post', 'mailuser', 'num', (int)($conf['shop']['mailuser'] ?? 1)),
        'date' => getVar('post', 'date', 'num', (int)($conf['shop']['date'] ?? 1)),
        'read' => getVar('post', 'read', 'num', (int)($conf['shop']['read'] ?? 1)),
        'rate' => getVar('post', 'rate', 'num', (int)($conf['shop']['rate'] ?? 1)),
        'letter' => getVar('post', 'letter', 'num', (int)($conf['shop']['letter'] ?? 1)),
        'assoc' => getVar('post', 'assoc', 'num', (int)($conf['shop']['assoc'] ?? 1)),
        'mailsend' => getVar('post', 'mailsend', 'num', (int)($conf['shop']['mailsend'] ?? 1)),
        'part' => getVar('post', 'part', 'num', (int)($conf['shop']['part'] ?? 1)),
        'partlink' => $conf['homeurl'].'/index.php?name=shop&amp;op=part&amp;id=[id]',
        'sende' => $sende,
        'userinfo' => $userinfo,
        'partinfo' => $partinfo,
        'partinfo2' => $partinfo2,
        'shopinfo' => $shopinfo,
    ];
    setConfigFile('shop.php', $cont);
    setRedirect($afile.'.php?name=shop&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

function conf(): void {
    clients();
}

switch($op) {
    default: shop(); break;
    case 'clients': clients(); break;
    case 'clientsact': clientsact(); break;
    case 'clientsadd': clientsadd(); break;
    case 'clientssave': clientssave(); break;
    case 'clientsdel': clientsdel(); break;
    case 'products': products(); break;
    case 'productsadd': productsadd(); break;
    case 'productssave': productssave(); break;
    case 'productsadmin': productsadmin(); break;
    case 'partners': partners(); break;
    case 'partnersact': partnersact(); break;
    case 'partnersadd': partnersadd(); break;
    case 'partnersdetails': partnersdetails(); break;
    case 'partnerssave': partnerssave(); break;
    case 'partnersdel': partnersdel(); break;
    case 'export': exportdata(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}







