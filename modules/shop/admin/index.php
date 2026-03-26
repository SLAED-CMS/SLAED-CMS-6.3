<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('shop')) die('Illegal file access');


# Build the shared client/partner search box used on every shop admin page
function buildShopSearchBox(): string {
    global $afile;
    $sel = getVar('post', 'search', 'num');
    $txt = getVar('post', 'csearch', 'text');
    $opts = '';
    foreach ([_ID, _NICKNAME, _CLIENTNAME, _EMAIL, _SITE] as $k => $v) {
        $sort = $k + 1;
        $opts .= getAdminOption((string)$sort, $v, $sel == $sort || (!$sel && $sort == 2));
    }
    $inner = _SEARCH.': '.getAdminSelect('search', $opts)
        .' '.get_user_search('csearch', $txt, '30')
        .' <input type="hidden" name="name" value="shop">'
        .'<input type="hidden" name="op" value="clients">'
        .'<input type="submit" value="'._OK.'" class="sl_but_blue">';
    return getAdminSearchBox('<form method="post" action="'.$afile.'.php">'.$inner.'</form>');
}


function clients(): void {
    global $db, $afile, $conf, $tpl;
        $csearch = getVar('post', 'csearch', 'text');
    $search = getVar('post', 'search', 'num');
    setHead();
    $searchCols = [
        1 => 'u.id',
        2 => 'u.name',
        3 => 'c.name',
        4 => 'c.email',
        5 => 'c.website',
    ];
    $searchWhere = '';
    $searchOrder = 'c.enddate ASC';
    $searchParams = [];
    if ($csearch !== '') {
        $searchCol = $searchCols[$search] ?? 'u.name';
        $searchWhere = ' AND '.$searchCol.' LIKE :csearch';
        $searchOrder = $searchCol.' ASC';
        $searchParams['csearch'] = '%'.$csearch.'%';
    }
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['shop']['anum'];
    $a = ($num) ? $offset+1 : 1;
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $_sops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=clients&amp;status=1', 'name=shop&amp;op=clients&amp;status=2', 'name=shop&amp;op=clientadd'];
    $_stabs = [_NEW, _AKTIVE, _DEAKTIVE, _ADD];
    if ($csearch) {
        $sqlstatus = 'status != \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'subtab' => 1,
            'legacy' => 1,
        ]);
    } elseif (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status = \'1\'';
        $field = 'name=shop&amp;op=clients&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'subtab' => 1,
            'legacy' => 1,
        ]);
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'status = \'0\'';
        $field = 'name=shop&amp;op=clients&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'subtab' => 1,
            'legacy' => 2,
        ]);
    } else {
        $sqlstatus = 'status = \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'   => $_ops,
            'tabs'  => $_lang,
            'sub'   => $box,
            'sops'  => $_sops,
            'stabs' => $_stabs,
            'subtab' => 1,
        ]);
    }
    $cont = $navi;
    $result = $db->getSqlQuery('SELECT c.id, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.name, p.title FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.'.$sqlstatus.$searchWhere.' ORDER BY '.$searchOrder.' LIMIT '.$offset.', '.$conf['shop']['anum'], $searchParams);
    [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(c.id) FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) WHERE c.'.$sqlstatus.$searchWhere, $searchParams));
    $numpages = ($conf['shop']['anum'] > 0) ? (int)ceil($numstories / $conf['shop']['anum']) : 1;
    if ($db->getSqlRowCount($result) > 0) {
        $head = '<th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._SITE.'</th><th>'._NICKNAME.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>';
        $trows = '';
        while([$cid, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $nick, $ptitle] = $db->getSqlRow($result)) {
            $cenddate = ($cenddate != '0') ? getTimeLeft($cenddate) : _UNLIMITED;
            $cinfo = ($cinfo) ? $cinfo : _NO;
            if ($nick) {
                $name = $nick;
                $nick = user_info(filterTextHighlight($nick, $csearch));
            } else {
                $name = _ANONYM;
                $nick = _ANONYM;
            }
            $cells = '<td>'.$cid.'</td>'
                .'<td>'.title_tip(_ID.': '.$a.'<br>'._DATE.': '.date(_TIMESTRING, $cregdate).'<br>'._CLIENTNAME.': '.filterTextHighlight($cname, $csearch).'<br>'._CLIENTADRES.': '.$caddr.'<br>'._CLIENTPHONE.': '.$cphone.'<br>'._EMAIL.': '.$cemail.'<br>'._NOTE.': '.$cinfo).'<span title="'.$ptitle.'" class="sl_note">'.cutstr($ptitle, 40).'</span></td>'
                .'<td>'.filterTextHighlight(domain($cwebsite), $csearch).'</td>'
                .'<td>'.$nick.'</td>'
                .'<td>'.$cenddate.'</td>'
                .'<td>'.ad_status('', $cactive).'</td>'
                .'<td>'.add_menu(ad_status($afile.'.php?name=shop&op=clientset&amp;id='.$cid.$refer, $cactive).'||<a href="'.$afile.'.php?name=shop&op=clientadd&amp;cid='.$cid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=clientdel&amp;id='.$cid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td>';
            $trows .= getAdminTableRow($cells);
            $a++;
        }
        $html = getAdminTable($head, $trows);
        $html .= setPageNumbers('pagenum', '', $numstories, $numpages, $conf['shop']['anum'], $field, $conf['shop']['anump']);
        $cont .= getAdminBox($html);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function clientset(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    [$active] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]));
    $active = ($active) ? 0 : 1;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    setRedirect($afile.'.php?name=shop&op=clients');
}

function clientadd(): void {
    global $db, $afile, $conf, $stop, $tpl;
        if (getVar('req', 'cid', 'num', 0)) {
        $cid = getVar('req', 'cid', 'num');
        $result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.part, c.proz, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.id, u.name FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.part) WHERE c.id = :cid', ['cid' => $cid]);
        [$cid, $uid, $product, $partner, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick] = $db->getSqlRow($result);
        $cregdate = date('Y-m-d H:i:s', $cregdate);
        $cenddate = ($cenddate) ? date('Y-m-d H:i:s', $cenddate) : date('Y-m-d H:i:s');
    } else {
        $cid = 0;
        $partner = getVar('post', 'partner', 'num');
        $uid = getVar('post', 'uid', 'num');
        $product = getVar('post', 'product', 'num');
        $cname = getVar('post', 'cname', 'text');
        $caddr = getVar('post', 'caddr', 'text');
        $cphone = getVar('post', 'cphone', 'text');
        $cemail = getVar('post', 'cemail', 'text');
        $cwebsite = getVar('post', 'cwebsite', 'url');
        $cregdate = getVar('post', 'cregdate', 'text', date('Y-m-d H:i:s'));
        $cenddate = getVar('post', 'cenddate', 'text', date('Y-m-d H:i:s'));
        $cinfo = getVar('post', 'cinfo', 'text');
        $cactive = getVar('post', 'cactive', 'num');
    }
    setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'    => $_ops,
        'tabs'   => $_lang,
        'sub'    => $box,
        'sops'   => ['name=shop&amp;op=clients', 'name=shop&amp;op=clients&amp;status=1', 'name=shop&amp;op=clients&amp;status=2', 'name=shop&amp;op=clientadd'],
        'stabs'  => [_NEW, _AKTIVE, _DEAKTIVE, _ADD],
        'subtab' => 1,
        'legacy' => 3,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    $cppi = 0;
    $frows = '';
    if ($partner) {
        if (!$proz) {
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT part FROM '.PREFIX_DB.'_clients WHERE part = :partner AND status != 2', ['partner' => $partner]));
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
        $frows .= getAdminFormRow(_PARTNER_NAME.':', $nick)
            .getAdminFormRow(_PARTNER_ID.':', '<input type="hidden" name="partner" value="'.$partner.'">'.$partner)
            .getAdminFormRow(_PERCENT.':', $proz.' %');
    }
    $frows .= getAdminFormRow(_USER_ID.':', '<input type="number" name="uid" value="'.$uid.'" class="sl_form" placeholder="'._USER_ID.'">');
    $productslist = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_products ORDER BY title');
    $prodopts = '';
    while([$pid, $ptitle] = $db->getSqlRow($productslist)) {
        $prodopts .= '<option value="'.$pid.'\'';
        if ($product == $pid) $prodopts .= ' selected';
        $prodopts .= '>'.$ptitle.'</option>';
    }
    $frows .= getAdminFormRow(_PRODUCT.':', '<select name="product" class="sl_form">'.$prodopts.'</select>')
        .getAdminFormRow(_CLIENTNAME.':', '<input type="text" name="cname" value="'.$cname.'" maxlength="255" class="sl_form" placeholder="'._CLIENTNAME.'" required>')
        .getAdminFormRow(_CLIENTADRES.':', '<input type="text" name="caddr" value="'.$caddr.'" maxlength="255" class="sl_form" placeholder="'._CLIENTADRES.'" required>')
        .getAdminFormRow(_CLIENTPHONE.':', '<input type="text" name="cphone" value="'.$cphone.'" maxlength="255" class="sl_form" placeholder="'._CLIENTPHONE.'" required>')
        .getAdminFormRow(_EMAIL.':', '<input type="email" name="cemail" value="'.$cemail.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required>')
        .getAdminFormRow(_SITE.':', '<input type="url" name="cwebsite" value="'.$cwebsite.'" maxlength="255" class="sl_form" placeholder="'._SITE.'">')
        .getAdminFormRow(_CLIENTSTR.':', datetime(1, 'cregdate', $cregdate, 16, 'sl_form'))
        .getAdminFormRow(_CLIENTEND.':', datetime(1, 'cenddate', $cenddate, 16, 'sl_form'))
        .getAdminFormRow(_NOTE.':', '<input type="text" name="cinfo" value="'.$cinfo.'" maxlength="255" class="sl_form" placeholder="'._NOTE.'">')
        .getAdminFormRow(_ACTIVATE2, radio_form($cactive, 'cactive'))
        .getAdminFormWide('<input type="hidden" name="cppi" value="'.$cppi.'">'.ad_save('cid', $cid, 'clientsave', 1), '', 'sl_center');
    $cont .= getAdminBox(getAdminForm($afile.'.php', $frows));
    echo $cont;
    setFoot();
}

function clientsave(): void {
    global $db, $afile, $conf, $stop;
    $partner = getVar('post', 'partner', 'num');
    $uid = getVar('post', 'uid', 'num');
    $product = getVar('post', 'product', 'num');
    $cname = getVar('post', 'cname', 'text');
    $caddr = getVar('post', 'caddr', 'text');
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
    if (!$cname || !$caddr || !$cphone) $stop[] = _ERROR_ALL;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($cid) {
            if ($partner && $cppi) {
                [$pprice] = $db->getSqlRow($db->getSqlQuery('SELECT price FROM '.PREFIX_DB.'_products WHERE id = :product', ['product' => $product]));
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT part FROM '.PREFIX_DB.'_clients WHERE part = :partner AND status != 2', ['partner' => $partner]));
                if ($num >= $conf['shop']['clients2']) {
                    $conf['shop']['proz2'] = ($conf['shop']['proz2']) ? $conf['shop']['proz2'] : 1;
                    $price = $pprice / 100 * $conf['shop']['proz2'];
                    $proz = $conf['shop']['proz2'];
                } elseif ($num >= $conf['shop']['clients1']) {
                    $conf['shop']['proz1'] = ($conf['shop']['proz1']) ? $conf['shop']['proz1'] : 1;
                    $price = $pprice / 100 * $conf['shop']['proz1'];
                    $proz = $conf['shop']['proz1'];
                } elseif ($num >= $conf['shop']['clients']) {
                    $conf['shop']['proz'] = ($conf['shop']['proz']) ? $conf['shop']['proz'] : 1;
                    $price = $pprice / 100 * $conf['shop']['proz'];
                    $proz = $conf['shop']['proz'];
                }
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET rest = rest+:endprice WHERE uid = :partner', ['endprice' => $price, 'partner' => $partner]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET uid = :uid, prod = :product, part = :partner, proz = :cpartner_proz, name = :cname, addr = :caddr, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, status = :cactive WHERE id = :cid', ['uid' => $uid, 'product' => $product, 'partner' => $partner, 'cpartner_proz' => $proz, 'cname' => $cname, 'caddr' => $caddr, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET uid = :uid, prod = :product, name = :cname, addr = :caddr, phone = :cphone, email = :cemail, website = :cwebsite, regdate = :cregdate, enddate = :cenddate, info = :cinfo, status = :cactive WHERE id = :cid', ['uid' => $uid, 'product' => $product, 'cname' => $cname, 'caddr' => $caddr, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive, 'cid' => $cid]);
            }
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients VALUES(NULL, :uid, :product, \'0\', \'0\', :cname, :caddr, :cphone, :cemail, :cwebsite, :cregdate, :cenddate, :cinfo, :cactive)', ['uid' => $uid, 'product' => $product, 'cname' => $cname, 'caddr' => $caddr, 'cphone' => $cphone, 'cemail' => $cemail, 'cwebsite' => $cwebsite, 'cregdate' => $cregdate, 'cenddate' => $cenddate, 'cinfo' => $cinfo, 'cactive' => $cactive]);
        }
        setRedirect($afile.'.php?name=shop&op=clients');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        clientdel($cid);
    } else {
        clientadd();
    }
}

function clientdel(int $id = 0): void {
    global $db, $afile;
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=clients');
}

function products(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num-1) * $conf['shop']['anum'];
    $offset = intval($offset);
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $_sops  = ['name=shop&amp;op=products', 'name=shop&amp;op=products&amp;status=1', 'name=shop&amp;op=productadd'];
    $_stabs = [_AKTIVE, _DEAKTIVE, _ADD];
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status=0';
        $field = 'name=shop&amp;op=products&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'tab'    => 1,
            'subtab' => 1,
            'legacy' => 1,
        ]);
    } else {
        $sqlstatus = 'status=1';
        $field = 'name=shop&amp;op=products&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'   => $_ops,
            'tabs'  => $_lang,
            'sub'   => $box,
            'sops'  => $_sops,
            'stabs' => $_stabs,
            'tab'   => 1,
            'subtab' => 1,
        ]);
    }
    $cont = $navi;
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.price, p.vote, p.status, c.title FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE '.$sqlstatus.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $phead = '<th>'._ID.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th><th class="{sorter: false}"><input type="checkbox" name="markcheck" id="markcheck" title="'._CHECKALL.'" OnClick="CheckBox(\'#markcheck\', \'.sl_check\')"></th>';
        $prows = '';
        while([$pid, $pcid, $ptime, $ptitle, $pprice, $pvote, $pactive, $ctitle] = $db->getSqlRow($result)) {
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
            $cells = '<td>'.$pid.'</td>'
                .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($ptime ?? '', _TIMESTRING)).'<span title="'.$ptitle.'" class="sl_note">'.cutstr($ptitle, 60).'</span></td>'
                .'<td>'.$pprice.' '.$conf['shop']['valute'].'</td>'
                .'<td>'.ad_status('', $active).'</td>'
                .'<td>'.add_menu($view.$vote.ad_status($afile.'.php?name=shop&op=productops&amp;typ=a'.$typ.'&amp;id='.$pid.$refer, $pactive).'||<a href="'.$afile.'.php?name=shop&op=productadd&amp;id='.$pid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=productops&amp;typ=d&amp;id='.$pid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$ptitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td>'
                .'<td><input type="checkbox" name="id[]" class="sl_check" value="'.$pid.'"></td>';
            $prows .= getAdminTableRow($cells);
        }
        $selms = _CHECKOP.': '.edit_list('shop', 'typ', '').' <input type="hidden" name="name" value="shop"><input type="hidden" name="op" value="productops"><input type="hidden" name="refer" value="1"> <input type="submit" value="'._OK.'" class="sl_but_blue">';
        $numpt = setArticleNumbers('pagenum', '', $conf['shop']['anum'], $field, 'id', '_products', '', $sqlstatus, $conf['shop']['anump']);
        $html = getAdminListForm(getAdminTable($phead, $prows), $tpl->getHtmlFrag('list-bottom', ['pager' => $numpt, 'select' => $selms]), '');
        $cont .= getAdminBox($html);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function productadd(): void {
    global $db, $afile, $conf, $stop, $tpl;
    if (getVar('req', 'id', 'num', 0)) {
        $id = getVar('req', 'id', 'num');
        $result = $db->getSqlQuery('SELECT id, cid, time, title, intro, body, price, vote, assoc, ihome, acomm, counter, fix, status FROM '.PREFIX_DB.'_products WHERE id = :id', ['id' => $id]);
        [$pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $pprice, $vote, $passoc, $ihome, $acomm, $pcount, $fix, $pactive] = $db->getSqlRow($result);
        $associated = explode(',', $passoc);
    } else {
        $pid = getVar('post', 'pid', 'num');
        $pcid = getVar('post', 'pcid', 'num');
        $ptitle = getVar('post', 'ptitle', 'title');
        $ptext = getVar('post', 'ptext', 'text');
        $pbodytext = getVar('post', 'pbodytext', 'text');
        $pprice = getVar('post', 'pprice', 'text');
        $vote = getVar('post', 'vote', 'num');
        $ptime = getVar('req', 'ptime', 'time');
        $associated = getVar('post', 'associated', 'array');
        $ihome = getVar('post', 'ihome', 'num');
        $acomm = getVar('post', 'acomm', 'num');
        $fix = getVar('post', 'fix', 'num');
        $pactive = getVar('post', 'pactive', 'num');
    }
    setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'    => $_ops,
        'tabs'   => $_lang,
        'sub'    => $box,
        'sops'   => ['name=shop&amp;op=products', 'name=shop&amp;op=products&amp;status=1', 'name=shop&amp;op=productadd'],
        'stabs'  => [_AKTIVE, _DEAKTIVE, _ADD],
        'tab'    => 1,
        'subtab' => 1,
        'legacy' => 2,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    $ptextpre = ($vote) ? '<div id="repshop">'.getVoting($vote, 'shop').'</div><hr>'.$ptext : $ptext;
    if ($ptextpre) $cont .= preview($ptitle, $ptextpre, $pbodytext, '', 'shop');
    $parows = getAdminFormRow(_TITLE.' / '._PRODUCT.':', '<input type="text" name="ptitle" value="'.$ptitle.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required>')
        .getAdminFormRow(_CATEGORY.':', getcat('shop', $pcid, 'pcid', 'sl_form', '<option value="">'._HOMECAT.'</option>'));
    $result2 = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY parent, title', ['modul' => 'shop']);
    if ($db->getSqlRowCount($result2) > 0) {
        $associnner = '<table class="sl_form"><tr>';
        while ([$id, $title] = $db->getSqlRow($result2)) {
            if ($a == 2) {
                $associnner .= '</tr><tr>';
                $a = 0;
            }
            $check = '';
            if ($associated) foreach ($associated as $val) if ($val == $id) $check = ' checked';
            $associnner .= '<td><input type="checkbox" name="associated[]" value="'.$id.'\''.$check.'> '.$title.'</td>';
            $a++;
        }
        $associnner .= '</tr></table>';
        $parows .= getAdminFormRow(_ASSOTOPIC.':<div class="sl_small">'._ASSOTOPICI.'</div>', $associnner);
    }
    $parows .= getAdminFormRow(_TEXT.':', textarea('1', 'ptext', $ptext, 'shop', '5', _TEXT, '1'))
        .getAdminFormRow(_ENDTEXT.':', textarea('2', 'pbodytext', $pbodytext, 'shop', '15', _ENDTEXT, '0'))
        .getAdminFormRow(_PREIS.':', '<input type="text" name="pprice" value="'.$pprice.'" maxlength="10" class="sl_form" placeholder="'._PREIS.'" required>')
        .getAdminFormRow(_CHNGSTORY.':', datetime(1, 'ptime', $ptime, 16, 'sl_form'))
        .getAdminFormRow(_VOTING.':', add_voting('shop', 'vote', $vote, 'sl_form'))
        .getAdminFormRow(_COMMENTS.':', com_access('acomm', $acomm, 'sl_form'))
        .getAdminFormRow(_PUBHOME, radio_form($ihome, 'ihome'))
        .getAdminFormRow(_FIXED.'?', radio_form($fix, 'fix'))
        .getAdminFormRow(_ACTIVATEP, radio_form($pactive, 'pactive'))
        .getAdminFormWide(ad_save('pid', $pid, 'productsave'), '', 'sl_center');
    $cont .= getAdminBox(getAdminForm($afile.'.php', $parows, '', 'sl_table_form', 'post', 'post'));
    echo $cont;
    setFoot();
}

function productsave(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num');
    $pcid = getVar('post', 'pcid', 'num');
    $ptitle = getVar('post', 'ptitle', 'title');
    $associated = implode(',', getVar('post', 'associated', 'array', []));
    $ptext = getVar('post', 'ptext', 'text');
    $pbodytext = getVar('post', 'pbodytext', 'text');
    $pprice = getVar('post', 'pprice', 'text');
    $vote = getVar('post', 'vote', 'num');
    $ihome = getVar('post', 'ihome', 'num');
    $acomm = getVar('post', 'acomm', 'num');
    $fix = getVar('post', 'fix', 'num');
    $pactive = getVar('post', 'pactive', 'num');
    $ptime = getVar('req', 'ptime', 'time');
    $stop = [];
    if (!$ptitle || !$ptext || !$pprice) $stop[] = _ERROR_ALL;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($pid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET cid = :pcid, time = :ptime, title = :ptitle, intro = :ptext, body = :pbodytext, price = :pprice, vote = :vote, assoc = :assoc, ihome = :ihome, acomm = :acomm, fix = :fix, status = :pactive WHERE id = :pid', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'pprice' => $pprice, 'vote' => $vote, 'assoc' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive, 'pid' => $pid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_products VALUES (NULL, :pcid, :ptime, :ptitle, :ptext, :pbodytext, :pprice, :vote, :assoc, :ihome, :acomm, \'0\', \'0\', \'0\', \'0\', :fix, :pactive)', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'pprice' => $pprice, 'vote' => $vote, 'assoc' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive]);
        }
        setRedirect($afile.'.php?name=shop&op=products');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        productops($pid, 'd');
    } else {
        productadd();
    }
}

function productops(int|array $id = 0, string $vtyp = ''): void {
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
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET status = :typ WHERE id IN ('.$id.')', ['typ' => $typ]);
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
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['shop']['anum'];
    $offset = intval($offset);
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $_sops  = ['name=shop&amp;op=partners', 'name=shop&amp;op=partners&amp;status=1', 'name=shop&amp;op=partners&amp;status=2', 'name=shop&amp;op=partneradd'];
    $_stabs = [_NEW, _AKTIVE, _DEAKTIVE, _ADD];
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status=1';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'tab'    => 2,
            'subtab' => 1,
            'legacy' => 1,
        ]);
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'status=0';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => $_sops,
            'stabs'  => $_stabs,
            'tab'    => 2,
            'subtab' => 1,
            'legacy' => 2,
        ]);
    } else {
        $sqlstatus = 'status=2';
        $field = 'name=shop&amp;op=partners&amp;';
        $refer = '&amp;refer=1';
        $navi = setAdminNavi([
            'ops'   => $_ops,
            'tabs'  => $_lang,
            'sub'   => $box,
            'sops'  => $_sops,
            'stabs' => $_stabs,
            'tab'   => 2,
            'subtab' => 1,
        ]);
    }
    $cont = $navi;
    $result = $db->getSqlQuery('SELECT p.id, p.name, p.addr, p.phone, p.email, p.website, p.regdate, p.rest, p.bek, p.status, u.name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = p.uid) WHERE '.$sqlstatus.' LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $pahead = '<th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PARTNERREST.'</th><th>'._PARTNERBEK.'</th><th>'._SITE.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>';
        $parows = '';
        while([$paid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $paregdate, $parest, $pabek, $paactive, $nick] = $db->getSqlRow($result)) {
            if ($nick) {
                $name = $nick;
                $nick = user_info(filterTextHighlight($nick, ''));
            } else {
                $name = _ANONYM;
                $nick = _ANONYM;
            }
            $cells = '<td>'.$paid.'</td>'
                .'<td>'.title_tip(_CLIENTNAME.': '.$paname.'<br>'._CLIENTADRES.': '.$paaddr.'<br>'._CLIENTPHONE.': '.$paphone.'<br>'._EMAIL.': '.$paemail).$nick.'</td>'
                .'<td>'.$parest.' '.$conf['shop']['valute'].'</td>'
                .'<td>'.$pabek.' '.$conf['shop']['valute'].'</td>'
                .'<td>'.domain($pawebsite).'</td>'
                .'<td>'.date(_TIMESTRING, $paregdate).'</td>'
                .'<td>'.add_menu(ad_status($afile.'.php?name=shop&op=partnerset&amp;id='.$paid.$refer, $paactive).'||<a href="'.$afile.'.php?name=shop&op=partnerinfo&amp;paid='.$paid.'" title="'._MVIEW.'">'._MVIEW.'</a>||<a href="'.$afile.'.php?name=shop&op=partneradd&amp;paid='.$paid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=shop&op=partnerdel&amp;id='.$paid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td>';
            $parows .= getAdminTableRow($cells);
        }
        $html = getAdminTable($pahead, $parows);
        $html .= setArticleNumbers('pagenum', '', $conf['shop']['anum'], $field, 'id', '_partners', '', $sqlstatus, $conf['shop']['anump']);
        $cont .= getAdminBox($html);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function partnerset(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    [$active] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]));
    $active = ($active == 1) ? 0 : 1;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    setRedirect($afile.'.php?name=shop&op=partners');
}

function partneradd(): void {
    global $db, $afile, $stop, $tpl;
    if (getVar('req', 'paid', 'num', 0)) {
        $paid = getVar('req', 'paid', 'num');
        $result = $db->getSqlQuery('SELECT p.id, p.uid, p.name, p.addr, p.phone, p.email, p.website, p.webmoney, p.paypal, p.regdate, p.rest, p.bek, p.status, u.name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = p.uid) WHERE p.id = :paid', ['paid' => $paid]);
        [$paid, $uid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive, $nick] = $db->getSqlRow($result);
        $paregdate = ($paregdate) ? date('Y-m-d H:i:s', $paregdate) : date('Y-m-d H:i:s');
    } else {
        $paid = 0;
        $uid = getVar('post', 'uid', 'num');
        $paname = getVar('post', 'paname', 'text');
        $paaddr = getVar('post', 'paaddr', 'text');
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
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'    => $_ops,
        'tabs'   => $_lang,
        'sub'    => $box,
        'sops'   => ['name=shop&amp;op=partners', 'name=shop&amp;op=partners&amp;status=1', 'name=shop&amp;op=partners&amp;status=2', 'name=shop&amp;op=partneradd'],
        'stabs'  => [_NEW, _AKTIVE, _DEAKTIVE, _ADD],
        'tab'    => 2,
        'subtab' => 1,
        'legacy' => 3,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    $parrows = '';
    if ($paid) {
        $nick = ($nick) ? user_info($nick) : _ANONYM;
        $parrows .= getAdminFormRow(_NICKNAME.':', $nick);
    }
    $uidfield = ($uid == 0) ? '<input type="number" name="uid" value="'.$uid.'" class="sl_form" placeholder="'._USER_ID.'" required>' : '<input type="hidden" name="uid" value="'.$uid.'">'.$uid;
    $parrows .= getAdminFormRow(_USER_ID.':', $uidfield)
        .getAdminFormRow(_CLIENTNAME.':', '<input type="text" name="paname" value="'.$paname.'" maxlength="255" class="sl_form" placeholder="'._CLIENTNAME.'" required>')
        .getAdminFormRow(_CLIENTADRES.':', '<input type="text" name="paaddr" value="'.$paaddr.'" maxlength="255" class="sl_form" placeholder="'._CLIENTADRES.'" required>')
        .getAdminFormRow(_CLIENTPHONE.':', '<input type="text" name="paphone" value="'.$paphone.'" maxlength="255" class="sl_form" placeholder="'._CLIENTPHONE.'" required>')
        .getAdminFormRow(_EMAIL.':', '<input type="email" name="paemail" value="'.$paemail.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required>')
        .getAdminFormRow(_SITE.':', '<input type="url" name="pawebsite" value="'.$pawebsite.'" maxlength="255" class="sl_form" placeholder="'._SITE.'">')
        .getAdminFormRow(_WEBMONEY.':', '<input type="text" name="pawebmoney" value="'.$pawebmoney.'" maxlength="255" class="sl_form" placeholder="'._WEBMONEY.'">')
        .getAdminFormRow(_PAYPAL.':', '<input type="text" name="papaypal" value="'.$papaypal.'" maxlength="255" class="sl_form" placeholder="'._PAYPAL.'">')
        .getAdminFormRow(_REG.':', datetime(1, 'paregdate', $paregdate, 16, 'sl_form'));
    if ($paactive != 2) {
        $parrows .= getAdminFormRow(_PARTNERREST.':', '<input type="text" name="parest" value="'.$parest.'" maxlength="255" class="sl_form" placeholder="'._PARTNERREST.'">')
            .getAdminFormRow(_PARTNERBEK.':', '<input type="text" name="pabek" value="'.$pabek.'" maxlength="255" class="sl_form" placeholder="'._PARTNERBEK.'">');
    }
    $parrows .= getAdminFormRow(_ACTIVATE2, radio_form($paactive, 'paactive'))
        .getAdminFormWide(ad_save('paid', $paid, 'partnersave', 1), '', 'sl_center');
    $cont .= getAdminBox(getAdminForm($afile.'.php', $parrows));
    echo $cont;
    setFoot();
}

function partnersave(): void {
    global $db, $afile, $stop;
    $uid = getVar('post', 'uid', 'num');
    $paname = getVar('post', 'paname', 'text');
    $paaddr = getVar('post', 'paaddr', 'text');
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
    if (!$paname || !$paaddr || !$paphone) $stop[] = _ERROR_ALL;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        if ($paid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET uid = \''.$uid.'\', name = \''.$paname.'\', addr = \''.$paaddr.'\', phone = \''.$paphone.'\', email = \''.$paemail.'\', website = \''.$pawebsite.'\', webmoney = \''.$pawebmoney.'\', paypal = \''.$papaypal.'\', regdate = \''.$paregdate.'\', rest = \''.$parest.'\', bek = \''.$pabek.'\', status = \''.$paactive.'" WHERE id = \''.$paid.'\'');
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, \''.$uid.'\', \''.$paname.'\', \''.$paaddr.'\', \''.$paphone.'\', \''.$paemail.'\', \''.$pawebsite.'\', \''.$pawebmoney.'\', \''.$papaypal.'\', \''.$paregdate.'\', \''.$parest.'\', \''.$pabek.'\', \''.$paactive.'\')');
        }
        setRedirect($afile.'.php?name=shop&op=partners');
    } elseif (getVar('post', 'posttype', 'text') == 'delete') {
        partnerdel($paid);
    } else {
        partneradd();
    }
}

function partnerdel(int $id = 0): void {
    global $db, $afile;
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=partners');
}

function partnerinfo(): void {
    global $db, $afile, $conf, $tpl;
    $paid = getVar('get', 'paid', 'num');
    $a = 0;
    $partsumges = 0;
    setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'    => $_ops,
        'tabs'   => $_lang,
        'sub'    => $box,
        'sops'   => ['name=shop&amp;op=partners', 'name=shop&amp;op=partners&amp;status=1', 'name=shop&amp;op=partners&amp;status=2', 'name=shop&amp;op=partneradd'],
        'stabs'  => [_NEW, _AKTIVE, _DEAKTIVE, _ADD],
        'tab'    => 2,
        'subtab' => 1,
        'legacy' => 1,
    ]);
    $result = $db->getSqlQuery('SELECT id, uid, name, addr, phone, email, website, webmoney, paypal, regdate, rest, bek, status FROM '.PREFIX_DB.'_partners WHERE id = :paid', ['paid' => $paid]);
    [$paid, $uid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result);
    $result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.part, c.proz, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.id, u.name, p.id, p.title, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id=c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id=c.prod) WHERE c.part = :uid AND c.status != 2 ORDER BY c.id ASC', ['uid' => $uid]);
    if ($db->getSqlRowCount($result) > 0) {
        $pihead = '<th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._PRODUCT.'</th><th>'._PREIS.'</th><th>'._PERCENT.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._SUM.'</th>';
        $pirows = '';
        $partsum = 0;
        $partsumges = 0;
        $a = 0;
        while([$cid, $uid, $product, $partner, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $ptitle, $pprice] = $db->getSqlRow($result)) {
            $partsum = $pprice / 100 * $proz;
            $partsumges += $partsum;
            $cells = '<td>'.$cid.'</td>'
                .'<td>'.user_info($nick).'</td>'
                .'<td>'.$ptitle.'</td>'
                .'<td>'.$pprice.' '.$conf['shop']['valute'].'</td>'
                .'<td>'.$proz.' %</td>'
                .'<td>'.date(_TIMESTRING, $cregdate).'</td>'
                .'<td>'.$partsum.' '.$conf['shop']['valute'].'</td>';
            $pirows .= getAdminTableRow($cells);
            $a++;
        }
        $cont .= getAdminBox(getAdminTable($pihead, $pirows));
    }
    $shead = '<th>'._CLIENTEN.'</th><th>'._WEBMONEY.'</th><th>'._PAYPAL.'</th><th>'._PARTNERGES.'</th><th>'._PARTNERREST.'</th><th class="{sorter: false}">'._PARTNERBEK.'</th>';
    $srow = getAdminTableRow('<td>'.$a.'</td><td>'.$pawebmoney.'</td><td>'.$papaypal.'</td><td>'.$partsumges.' '.$conf['shop']['valute'].'</td><td>'.$parest.' '.$conf['shop']['valute'].'</td><td>'.$pabek.' '.$conf['shop']['valute'].'</td>');
    $cont .= getAdminBox(getAdminTable($shead, $srow));
    echo $cont;
    setFoot();
}

function export(): void {
    global $db, $afile, $tpl;
    $id = getVar('post', 'id', 'num');
    $bd = getVar('post', 'bd', 'text');
    if ($id == 1 && $bd) {
        $list = [];
        if ($bd == 'products') {
            $result = $db->getSqlQuery('SELECT id, cid, time, title, intro, body, price, vote, assoc, comments, counter, votes, tvotes, fix, status FROM '.PREFIX_DB.'_products ORDER BY id');
            while([$pid, $pcid, $ptime, $ptitle, $ptext, $pbodytext, $pprice, $pvote, $passoc, $pcomments, $pcount, $pvotes, $ptotalvotes, $pfix, $pactive] = $db->getSqlRow($result)) {
                $list[] = $pid.'||'.$pcid.'||'.$ptime.'||'.$ptitle.'||'.$ptext.'||'.$pbodytext.'||'.$pprice.'||'.$pvote.'||'.$passoc.'||'.$pcomments.'||'.$pcount.'||'.$pvotes.'||'.$ptotalvotes.'||'.$pfix.'||'.$pactive;
            }
        } elseif ($bd == 'clients') {
            $result = $db->getSqlQuery('SELECT id, uid, prod, part, proz, name, addr, phone, email, website, regdate, enddate, info, status FROM '.PREFIX_DB.'_clients ORDER BY id');
            while([$cid, $uid, $product, $partner, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive] = $db->getSqlRow($result)) {
                $list[] = $cid.'||'.$uid.'||'.$product.'||'.$partner.'||'.$proz.'||'.$cname.'||'.$caddr.'||'.$cphone.'||'.$cemail.'||'.$cwebsite.'||'.$cregdate.'||'.$cenddate.'||'.$cinfo.'||'.$cactive;
            }
        } elseif ($bd == 'partners') {
            $result = $db->getSqlQuery('SELECT id, uid, name, addr, phone, email, website, webmoney, paypal, regdate, rest, bek, status FROM '.PREFIX_DB.'_partners ORDER BY id');
            while([$paid, $uid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result)) {
                $list[] = $paid.'||'.$uid.'||'.$paname.'||'.$paaddr.'||'.$paphone.'||'.$paemail.'||'.$pawebsite.'||'.$pawebmoney.'||'.$papaypal.'||'.$paregdate.'||'.$parest.'||'.$pabek.'||'.$paactive;
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
                $uquery = 'cid = \''.$data[1].'\', time = \''.$data[2].'\', title = \''.$data[3].'\', intro = \''.$data[4].'\', body = \''.$data[5].'\', price = \''.$data[6].'\', vote = \''.$data[7].'\', assoc = \''.$data[7].'\', comments = \''.$data[9].'\', counter = \''.$data[10].'\', votes = \''.$data[11].'\', tvotes = \''.$data[12].'\', fix = \''.$data[13].'\', status = \''.$data[14].'\'';
                $squery = '\''.$data[1].'\', \''.$data[2].'\', \''.$data[3].'\', \''.$data[4].'\', \''.$data[5].'\', \''.$data[6].'\', \''.$data[7].'\', \''.$data[8].'\', \''.$data[9].'\', \''.$data[10].'\', \''.$data[11].'\', \''.$data[12].'\'';
            } elseif (preg_match('#(.*?)clients\\.csv#', $bd)) {
                $iid = 'id';
                $idb = 'clients';
                $uquery = 'uid = \''.$data[1].'\', prod = \''.$data[2].'\', part = \''.$data[3].'\', proz = \''.$data[4].'\', name = \''.$data[5].'\', addr = \''.$data[6].'\', phone = \''.$data[7].'\', email = \''.$data[8].'\', website = \''.$data[9].'\', regdate = \''.$data[10].'\', enddate = \''.$data[11].'\', info = \''.$data[12].'\', status = \''.$data[13].'\'';
                $squery = '\''.$data[1].'\', \''.$data[2].'\', \''.$data[3].'\', \''.$data[4].'\', \''.$data[5].'\', \''.$data[6].'\', \''.$data[7].'\', \''.$data[8].'\', \''.$data[9].'\', \''.$data[10].'\', \''.$data[11].'\', \''.$data[12].'\', \''.$data[13].'\'';
            } elseif (preg_match('#(.*?)partners\\.csv#', $bd)) {
                $iid = 'id';
                $idb = 'partners';
                $uquery = 'uid = \''.$data[1].'\', name = \''.$data[2].'\', addr = \''.$data[3].'\', phone = \''.$data[4].'\', email = \''.$data[5].'\', website = \''.$data[6].'\', webmoney = \''.$data[7].'\', paypal = \''.$data[8].'\', regdate = \''.$data[9].'\', rest = \''.$data[10].'\', bek = \''.$data[11].'\', status = \''.$data[12].'\'';
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
        $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
        $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
        $box = buildShopSearchBox();
        $cont = setAdminNavi([
            'ops'    => $_ops,
            'tabs'   => $_lang,
            'sub'    => $box,
            'sops'   => ['', ''],
            'stabs'  => [_EXPORT, _IMPORT],
            'tab'    => 3,
            'subtab' => 1,
            'id'     => 'export',
        ]);
        $cont .= checkPerms(BASE_DIR.'/uploads/shop/temp');
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _S_NOTE]);
        [$pr] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_products'));
        [$cl] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_clients'));
        [$pa] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_partners'));
        $content = '<div id="tabcs0" class="tabcont">';
        if ($pr || $cl || $pa) {
            $bdopts = ($pr ? '<option value="products">'._PRODUCTS.'</option>' : '')
                .($cl ? '<option value="clients">'._CLIENTS.'</option>' : '')
                .($pa ? '<option value="partners">'._PARTNERS.'</option>' : '');
            $exrows = getAdminFormRow(_DATABASE.':', '<select name="bd" class="sl_form">'.$bdopts.'</select>')
                .getAdminFormWide('<input type="hidden" name="name" value="shop"><input type="hidden" name="id" value="1"><input type="hidden" name="op" value="export"><input type="submit" value="'._SAVE.'" class="sl_but_blue">', '', 'sl_center');
            $content .= getAdminForm($afile.'.php', $exrows);
        } else {
            $content .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
            $improws = getAdminFormRow(_FILE.':', '<select name="bd" class="sl_form">'.$ocont.'</select>')
                .getAdminFormWide('<input type="hidden" name="name" value="shop"><input type="hidden" name="id" value="2"><input type="hidden" name="op" value="export"><input type="submit" value="'._SEND.'" class="sl_but_blue">', '', 'sl_center');
            $content .= getAdminForm($afile.'.php', $improws);
        } else {
            $content .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
        }
        $content .= '</div>'
        .'<script>
        var countries=new ddtabcontent(\'exports\')
        countries.setpersist(true)
        countries.setselectedClassTarget(\'link\')
        countries.init()
        </script>';
        $cont .= getAdminBox($content);
        echo $cont;
        setFoot();
    }
}

function config(): void {
    global $afile, $conf, $tpl;
        setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'  => $_ops,
        'tabs' => $_lang,
        'sub'  => $box,
        'sops'  => ['name=shop&amp;op=clients', 'name=shop&amp;op=clients&amp;status=1', 'name=shop&amp;op=clients&amp;status=2', 'name=shop&amp;op=clientadd'],
        'stabs' => [_NEW, _AKTIVE, _DEAKTIVE, _ADD],
        'tab'  => 4,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/shop.php');
    $cfgrows = getAdminFormRow(_CDEFIS.':', '<input type="text" name="defis" value="'.urldecode($conf['shop']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required>')
        .getAdminFormRow(_C_0.':', '<input type="number" name="clients" value="'.$conf['shop']['clients'].'" class="sl_conf" placeholder="'._C_0.'" required>')
        .getAdminFormRow(_C_1.':', '<input type="number" name="proz" value="'.$conf['shop']['proz'].'" class="sl_conf" placeholder="'._C_1.'" required>')
        .getAdminFormRow(_C_2.':', '<input type="number" name="clients1" value="'.$conf['shop']['clients1'].'" class="sl_conf" placeholder="'._C_2.'" required>')
        .getAdminFormRow(_C_3.':', '<input type="number" name="proz1" value="'.$conf['shop']['proz1'].'" class="sl_conf" placeholder="'._C_3.'" required>')
        .getAdminFormRow(_C_4.':', '<input type="number" name="clients2" value="'.$conf['shop']['clients2'].'" class="sl_conf" placeholder="'._C_4.'" required>')
        .getAdminFormRow(_C_5.':', '<input type="number" name="proz2" value="'.$conf['shop']['proz2'].'" class="sl_conf" placeholder="'._C_5.'" required>')
        .getAdminFormRow(_C_6.':', '<input type="text" name="valute" value="'.$conf['shop']['valute'].'" maxlength="25" class="sl_conf" placeholder="'._C_6.'" required>')
        .getAdminFormRow(_C_7.':', '<input type="email" name="mail" value="'.$conf['shop']['mail'].'" maxlength="25" class="sl_conf" placeholder="'._C_7.'" required>')
        .getAdminFormRow(_C_8.':', '<input type="number" name="shop" value="'.intval($conf['shop']['shop_t'] / 86400).'" class="sl_conf" placeholder="'._C_8.'" required>')
        .getAdminFormRow(_C_9.':', '<input type="number" name="part" value="'.intval($conf['shop']['part_t'] / 86400).'" class="sl_conf" placeholder="'._C_9.'" required>')
        .getAdminFormRow(_BASCOL.':', '<input type="number" name="bascol" value="'.$conf['shop']['bascol'].'" class="sl_conf" placeholder="'._BASCOL.'" required>')
        .getAdminFormRow(_C_11.':', '<input type="number" name="assocnum" value="'.$conf['shop']['assocnum'].'" class="sl_conf" placeholder="'._C_11.'" required>')
        .getAdminFormRow(_C_13.':', '<input type="number" name="listnum" value="'.$conf['shop']['listnum'].'" class="sl_conf" placeholder="'._C_13.'" required>')
        .getAdminFormRow(_C_33.':', '<input type="number" name="num" value="'.$conf['shop']['num'].'" class="sl_conf" placeholder="'._C_33.'" required>')
        .getAdminFormRow(_C_34.':', '<input type="number" name="anum" value="'.$conf['shop']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required>')
        .getAdminFormRow(_C_35.':', '<input type="number" name="nump" value="'.$conf['shop']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required>')
        .getAdminFormRow(_C_36.':', '<input type="number" name="anump" value="'.$conf['shop']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required>')
        .getAdminFormRow(_HOMCAT, radio_form($conf['shop']['homcat'], 'homcat'))
        .getAdminFormRow(_VIEWCAT, radio_form($conf['shop']['viewcat'], 'viewcat'))
        .getAdminFormRow(_C_32, radio_form($conf['shop']['catdesc'], 'catdesc'))
        .getAdminFormRow(_C_15, radio_form($conf['shop']['subcat'], 'subcat'))
        .getAdminFormRow(_C_14, radio_form($conf['shop']['mailuser'], 'mailuser'))
        .getAdminFormRow(_C_17, radio_form($conf['shop']['date'], 'date'))
        .getAdminFormRow(_C_18, radio_form($conf['shop']['read'], 'read'))
        .getAdminFormRow(_C_19, radio_form($conf['shop']['rate'], 'rate'))
        .getAdminFormRow(_C_20, radio_form($conf['shop']['letter'], 'letter'))
        .getAdminFormRow(_C_23, radio_form($conf['shop']['assoc'], 'assoc'))
        .getAdminFormRow(_C_24, radio_form($conf['shop']['mailsend'], 'mailsend'))
        .getAdminFormRow(_C_25, radio_form($conf['shop']['part'], 'part'))
        .getAdminFormRow(_C_26.':<div class="sl_small">'._PART_ID.'</div>', '<input type="url" name="partlink" value="'.$conf['shop']['partlink'].'" maxlength="25" class="sl_conf" placeholder="'._C_26.'" required>')
        .getAdminFormRow(_C_27.':', textarea('1', 'sende', $conf['shop']['sende'], 'shop', '5', _C_27, '1'))
        .getAdminFormRow(_C_28.':', textarea('2', 'userinfo', $conf['shop']['userinfo'], 'shop', '5', _C_28, '1'))
        .getAdminFormRow(_C_29.':', textarea('3', 'partinfo', $conf['shop']['partinfo'], 'shop', '5', _C_29, '1'))
        .getAdminFormRow(_C_30.':', textarea('4', 'partinfo2', $conf['shop']['partinfo2'], 'shop', '5', _C_30, '1'))
        .getAdminFormRow(_C_31.':', textarea('5', 'shopinfo', $conf['shop']['shopinfo'], 'shop', '5', _C_31, '1'))
        .getAdminFormWide('<input type="hidden" name="name" value="shop"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue">', '', 'sl_center');
    $cont .= getAdminBox(getAdminForm($afile.'.php', $cfgrows, '', 'sl_table_conf', 'post', 'post'));
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
    setRedirect($afile.'.php?name=shop&op=config');
}

function info(): void {
    global $afile, $tpl;
    setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $box = buildShopSearchBox();
    $cont = setAdminNavi([
        'ops'  => $_ops,
        'tabs' => $_lang,
        'sub'  => $box,
        'sops'  => ['name=shop&amp;op=clients', 'name=shop&amp;op=clients&amp;status=1', 'name=shop&amp;op=clients&amp;status=2', 'name=shop&amp;op=clientadd'],
        'stabs' => [_NEW, _AKTIVE, _DEAKTIVE, _ADD],
        'tab'  => 5,
    ]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch($op) {
    default: clients(); break;
    case 'clients': clients(); break;
    case 'clientset': clientset(); break;
    case 'clientadd': clientadd(); break;
    case 'clientsave': clientsave(); break;
    case 'clientdel': clientdel(); break;
    case 'products': products(); break;
    case 'productadd': productadd(); break;
    case 'productsave': productsave(); break;
    case 'productops': productops(); break;
    case 'partners': partners(); break;
    case 'partnerset': partnerset(); break;
    case 'partneradd': partneradd(); break;
    case 'partnerinfo': partnerinfo(); break;
    case 'partnersave': partnersave(); break;
    case 'partnerdel': partnerdel(); break;
    case 'export': export(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}


