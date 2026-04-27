<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('shop')) die('Illegal file access');


# Build the shared client/partner search box used on every shop admin page
function buildShopSearchBox(): string {
    global $afile, $tpl;
    $sel = getVar('post', 'search', 'num');
    $txt = getVar('post', 'csearch', 'text');
    $opts = '';
    foreach ([_ID, _NICKNAME, _CLIENTNAME, _EMAIL, _SITE] as $k => $v) {
        $sort = $k + 1;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$sort,
            'label_text' => $v,
            'is_selected' => $sel == $sort || (!$sel && $sort == 2),
        ]);
    }
    return $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=shop&op=clients',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'shop'],
            ['nameattr' => 'op', 'valueattr' => 'clients'],
        ],
        'content_html' =>
            $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'csearch',
                'value_attr' => $txt,
                'maxlength_num' => 100,
                'placeholder_text' => _SEARCH,
                'is_inline_gap' => true,
            ])
            .$tpl->getHtmlFrag('select', [
                'name_attr' => 'search',
                'options_html' => $opts,
                'is_inline_gap' => true,
            ]),
        'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'is_inline_filter' => true,
    ]);
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
    $_ops = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $subtabs = [
        ['href' => $afile.'.php?name=shop&amp;op=clients', 'label' => _NEW, 'title' => _NEW],
        ['href' => $afile.'.php?name=shop&amp;op=clients&amp;status=1', 'label' => _AKTIVE, 'title' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=clients&amp;status=2', 'label' => _DEAKTIVE, 'title' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=clientadd', 'label' => _ADD, 'title' => _ADD],
    ];
    if ($csearch) {
        $sqlstatus = 'status != \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '';
        $subtab = 1;
    } elseif (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status = \'1\'';
        $field = 'name=shop&amp;op=clients&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $subtab = 1;
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'status = \'0\'';
        $field = 'name=shop&amp;op=clients&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $subtab = 2;
    } else {
        $sqlstatus = 'status = \'2\'';
        $field = 'name=shop&amp;op=clients&amp;';
        $refer = '&amp;refer=1';
        $subtab = 0;
    }
    $tabs = '';
    foreach ($subtabs as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === $subtab,
            'label' => $link['label'],
            'title' => $link['title'],
        ]);
    }
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $cont .= $tpl->getHtmlPart('box', [
        'content_html' => $tpl->getHtmlPart('tabs', [
            'tabs_html' => $tabs,
            'is_subtabs' => true,
        ]),
    ]);
    $result = $db->getSqlQuery('SELECT c.id, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.name, p.title FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id = c.prod) WHERE c.'.$sqlstatus.$searchWhere.' ORDER BY '.$searchOrder.' LIMIT '.$offset.', '.$conf['shop']['anum'], $searchParams);
    [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(c.id) FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = c.uid) WHERE c.'.$sqlstatus.$searchWhere, $searchParams));
    $numpages = ($conf['shop']['anum'] > 0) ? (int)ceil($numstories / $conf['shop']['anum']) : 1;
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _NICKNAME],
            ['content' => _PRODUCT],
            ['content' => _SITE],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ];
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
            $tips = [
                ['label' => _ID, 'value' => (string)$a, 'is_last' => false],
                ['label' => _DATE, 'value' => date(_TIMESTRING, $cregdate), 'is_last' => false],
                ['label' => _CLIENTNAME, 'value' => filterTextHighlight($cname, $csearch), 'is_last' => false],
                ['label' => _CLIENTADRES, 'value' => htmlspecialchars((string)$caddr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                ['label' => _CLIENTPHONE, 'value' => htmlspecialchars((string)$cphone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                ['label' => _EMAIL, 'value' => htmlspecialchars((string)$cemail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                ['label' => _NOTE, 'value' => htmlspecialchars((string)$cinfo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => true],
            ];
            $items = [
                [
                    'href' => $afile.'.php?name=shop&amp;op=clientset&amp;id='.$cid.$refer.'&amp;token='.getSiteToken(),
                    'label' => $cactive ? _DEACTIVATE : _ACTIVATE,
                    'title' => $cactive ? _DEACTIVATE : _ACTIVATE,
                ],
                [
                    'href' => $afile.'.php?name=shop&amp;op=clientadd&amp;cid='.$cid,
                    'label' => _FULLEDIT,
                    'title' => _FULLEDIT,
                ],
                [
                    'href' => $afile.'.php?name=shop&amp;op=clientdel&amp;id='.$cid.$refer.'&amp;token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                ],
            ];
            $trows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$cid],
                    ['content_html' => $nick],
                    ['is_truncate' => true, 'title_text' => (string)$ptitle, 'content_html' => $tpl->getHtmlFrag('info-tooltip', ['items' => $tips]).htmlspecialchars((string)$ptitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                    ['is_truncate' => true, 'title_text' => domain($cwebsite), 'content_html' => filterTextHighlight(domain($cwebsite), $csearch)],
                    ['is_col_date' => true, 'content_html' => $cenddate],
                    ['is_col_status' => true, 'content_html' => ad_status('', $cactive)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
            $a++;
        }
        $head[2]['is_truncate'] = true;
        $head[3]['is_truncate'] = true;
        $html = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $trows]);
        $html .= getTplPager([
            'count' => (int)$numstories,
            'pages' => $numpages,
            'limit' => (int)$conf['shop']['anum'],
            'maxpg' => (int)$conf['shop']['anump'],
            'url' => $field,
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $html]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function clientset(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    $id = getVar('get', 'id', 'num');
    if (!$iswarn && $id) {
        [$active] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]));
        $active = ($active) ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=shop&op=clients', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
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
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $tabs = '';
    foreach ([
        ['href' => $afile.'.php?name=shop&amp;op=clients', 'label' => _NEW],
        ['href' => $afile.'.php?name=shop&amp;op=clients&amp;status=1', 'label' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=clients&amp;status=2', 'label' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=clientadd', 'label' => _ADD],
    ] as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === 3,
            'label' => $link['label'],
            'title' => $link['label'],
        ]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('tabs', ['tabs_html' => $tabs, 'is_subtabs' => true])]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    $cppi = 0;
    $rows = [];
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
        $cont .= $tpl->getHtmlFrag('alert', [
            'is_warn' => false,
            'lines' => [
                _PARTNER_ID.': '.$partner,
                _PARTNER_NAME.': '.strip_tags((string)$nick),
                _PERCENT.': '.$proz.' %',
            ],
        ]);
    }
    $rows[] = [
        'label_html' => _USER_ID,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'uid',
            'value_attr' => (string)$uid,
            'placeholder_text' => _USER_ID,
        ]),
    ];
    $productslist = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_products ORDER BY title');
    $prodopts = '';
    while([$pid, $ptitle] = $db->getSqlRow($productslist)) {
        $prodopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$pid,
            'label_text' => $ptitle,
            'is_selected' => $product == $pid,
        ]);
    }
    $rows[] = ['label_html' => _CLIENTNAME, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'cname', 'value_attr' => $cname, 'is_required' => true, 'maxlength_num' => 255])];
    $rows[] = ['label_html' => _CLIENTADRES, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'caddr', 'value_attr' => $caddr, 'is_required' => true, 'maxlength_num' => 255])];
    $rows[] = ['label_html' => _CLIENTPHONE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'cphone', 'value_attr' => $cphone, 'is_required' => true, 'maxlength_num' => 255])];
    $rows[] = ['label_html' => _EMAIL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'cemail', 'value_attr' => $cemail, 'maxlength_num' => 255])];
    $rows[] = ['label_html' => _SITE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'cwebsite', 'value_attr' => $cwebsite, 'maxlength_num' => 255])];
    $rows[] = ['label_html' => _CLIENTSTR, 'field_html' => getTplAddDateTime(['name' => 'cregdate', 'time' => $cregdate, 'with' => true, 'max' => 16])];
    $rows[] = ['label_html' => _CLIENTEND, 'field_html' => getTplAddDateTime(['name' => 'cenddate', 'time' => $cenddate, 'with' => true, 'max' => 16])];
    $rows[] = ['label_html' => _PRODUCT, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'product', 'options_html' => $prodopts])];
    $rows[] = ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'cactive', 'value' => $cactive, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $rows[] = ['label_html' => _NOTE, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'cinfo', 'value_text' => $cinfo, 'rows_num' => 5]), 'is_full' => true];
    $posttypeopts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES])
        .($cid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'shop'],
            ['nameattr' => 'op', 'valueattr' => 'clientsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'cid', 'valueattr' => (string)$cid],
            ['nameattr' => 'partner', 'valueattr' => (string)$partner],
            ['nameattr' => 'cppi', 'valueattr' => (string)$cppi],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function clientsave(): void {
    global $db, $afile, $conf, $stop;
    $iswarn = !checkSiteToken();
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
    $posttype = getVar('post', 'posttype', 'text');
    if ($iswarn) {
        setRedirect($afile.'.php?name=shop&op=clients', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
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
    } elseif ($posttype == 'delete') {
        clientdel($cid);
    } else {
        clientadd();
    }
}

function clientdel(int $id = 0): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_clients WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=clients', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function products(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num-1) * $conf['shop']['anum'];
    $offset = intval($offset);
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $subtabs = [
        ['href' => $afile.'.php?name=shop&amp;op=products', 'label' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=products&amp;status=1', 'label' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=productadd', 'label' => _ADD],
    ];
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status=0';
        $field = 'name=shop&amp;op=products&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $subtab = 1;
    } else {
        $sqlstatus = 'status=1';
        $field = 'name=shop&amp;op=products&amp;';
        $refer = '&amp;refer=1';
        $subtab = 0;
    }
    $tabs = '';
    foreach ($subtabs as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === $subtab,
            'label' => $link['label'],
            'title' => $link['label'],
        ]);
    }
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'tab' => 1,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('tabs', ['tabs_html' => $tabs, 'is_subtabs' => true])]);
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.time, p.title, p.price, p.vote, p.status, c.title FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE '.$sqlstatus.' ORDER BY p.fix DESC, p.time DESC LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $phead = [
            ['content' => '', 'is_col_check' => true],
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _PRODUCT],
            ['content' => _PREIS, 'is_col_count' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ];
        $prows = '';
        while([$pid, $pcid, $ptime, $ptitle, $pprice, $pvote, $pactive, $ctitle] = $db->getSqlRow($result)) {
            $ctitle = ($pcid) ? $ctitle : _NO;
            $active = ($pactive && time() >= strtotime($ptime)) ? '1' : '0';
            $typ = ($pactive) ? '0' : '1';
            $items = [];
            if ($pactive && time() >= strtotime($ptime)) {
                $items[] = ['href' => 'index.php?name=shop&amp;op=view&amp;id='.$pid, 'label' => _MVIEW, 'title' => _MVIEW];
            }
            if ($pvote) {
                $items[] = ['href' => $afile.'.php?name=voting&amp;op=add&amp;id='.$pvote, 'label' => _EDITVOTE, 'title' => _EDITVOTE];
            }
            $items[] = ['href' => $afile.'.php?name=shop&op=productops&amp;typ=a'.$typ.'&amp;id='.$pid.$refer.'&amp;token='.getSiteToken(), 'label' => $pactive ? _DEACTIVATE : _ACTIVATE, 'title' => $pactive ? _DEACTIVATE : _ACTIVATE];
            $items[] = ['href' => $afile.'.php?name=shop&op=productadd&amp;id='.$pid, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = ['href' => $afile.'.php?name=shop&op=productops&amp;typ=d&amp;id='.$pid.$refer.'&amp;token='.getSiteToken(), 'label' => _ONDELETE, 'title' => _ONDELETE, 'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($ptitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"'];
            $prows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => (string)$pid])],
                    ['is_col_id' => true, 'content_html' => (string)$pid],
                    ['is_truncate' => true, 'title_text' => (string)$ptitle, 'content_html' => $tpl->getHtmlFrag('info-tooltip', ['items' => [
                        ['label' => _CATEGORY, 'value' => htmlspecialchars((string)$ctitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                        ['label' => _DATE, 'value' => format_time($ptime ?? '', _TIMESTRING), 'is_last' => true],
                    ]]).htmlspecialchars((string)$ptitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                    ['is_col_count' => true, 'content_html' => $pprice.' '.$conf['shop']['valute']],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $actionopts = '';
        foreach ([
            'a1' => _ACTIVATE,
            'a0' => _DEACTIVATE,
            'f1' => _FIXED,
            'f0' => _LNFIX,
            'h1' => _LHOME,
            'h0' => _LNHOME,
            'c1' => _APOSTMOD,
            'c0' => _APOSTNOMOD,
            't1' => _LADATE,
            'd1' => _DELETE,
        ] as $val => $lab) {
            $actionopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $val, 'label_text' => $lab]);
        }
        $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY parent, title', ['modul' => 'shop']);
        while ([$cid, $ctitle] = $db->getSqlRow($catres)) {
            $actionopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => 'c'.$cid,
                'label_text' => $ctitle,
            ]);
        }
        $phead[2]['is_truncate'] = true;
        $html = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'shop'],
                ['nameattr' => 'op', 'valueattr' => 'productops'],
                ['nameattr' => 'refer', 'valueattr' => '1'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ],
            'content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $phead, 'rows_html' => $prows]),
            'actions_html' => $tpl->getHtmlFrag('inline-badge', ['is_action_label' => true, 'label' => _CHECKOP]).' '.$tpl->getHtmlFrag('select', ['name_attr' => 'typ', 'options_html' => $actionopts, 'is_inline_gap' => true]).$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        ]);
        $html .= getTplPager(['limit' => $conf['shop']['anum'], 'maxpg' => $conf['shop']['anump'], 'url' => $field, 'table' => '_products', 'field' => 'id', 'where' => $sqlstatus]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $html]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function productadd(): void {
    global $db, $afile, $conf, $stop, $tpl, $prs;
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
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'tab' => 1,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $tabs = '';
    foreach ([
        ['href' => $afile.'.php?name=shop&amp;op=products', 'label' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=products&amp;status=1', 'label' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=productadd', 'label' => _ADD],
    ] as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === 2,
            'label' => $link['label'],
            'title' => $link['label'],
        ]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('tabs', ['tabs_html' => $tabs, 'is_subtabs' => true])]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    if ($vote || $ptext || $pbodytext) {
        $cont .= $tpl->getHtmlPart('preview', [
            'title' => _PREVIEW,
            'title_text' => (string)$ptitle,
            'body_a' => $vote ? getVotingView($vote, 'shop') : '',
            'body_b' => $ptext ? $prs->filterContent($ptext, false, 'shop') : '',
            'body_c' => $pbodytext ? $prs->filterContent($pbodytext, false, 'shop') : '',
        ]);
    }
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$pcid]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY parent, title', ['modul' => 'shop']);
    while ([$cid, $ctitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$cid,
            'label_text' => $ctitle,
            'is_selected' => (int)$pcid === (int)$cid,
        ]);
    }
    $rows = [
        ['label_html' => _TITLE.' / '._PRODUCT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'ptitle', 'value_attr' => $ptitle, 'maxlength_num' => 100, 'placeholder_text' => _TITLE, 'is_required' => true])],
        ['label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'pcid', 'options_html' => $catopts])],
    ];
    $result2 = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY parent, title', ['modul' => 'shop']);
    if ($db->getSqlRowCount($result2) > 0) {
        $assoc = '';
        while ([$id, $title] = $db->getSqlRow($result2)) {
            $isch = false;
            if ($associated) foreach ((array)$associated as $val) if ($val == $id) $isch = true;
            $assoc .= $tpl->getHtmlFrag('label', [
                'is_associated_option' => true,
                'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'associated[]', 'value_attr' => (string)$id, 'is_checked' => $isch]).htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]);
        }
        $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _ASSOTOPIC, 'hint' => _ASSOTOPICI]), 'field_html' => $assoc, 'is_full' => true];
    }
    $rows[] = ['label_html' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'ptext', 'value' => $ptext, 'mod' => 'shop', 'rows' => '5', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true];
    $rows[] = ['label_html' => _ENDTEXT, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'pbodytext', 'value' => $pbodytext, 'mod' => 'shop', 'rows' => '15', 'placeholder' => _ENDTEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true];
    $rows[] = ['label_html' => _PREIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'pprice', 'value_attr' => $pprice, 'maxlength_num' => 10, 'placeholder_text' => _PREIS, 'is_required' => true])];
    $commopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows[] = ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'ptime', 'time' => $ptime, 'with' => true, 'max' => 16])];
    $rows[] = ['label_html' => _VOTING, 'field_html' => add_voting('shop', 'vote', $vote, 'sl-form-control')];
    $rows[] = ['label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])];
    $rows[] = ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => $ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $rows[] = ['label_html' => _FIXED.'?', 'field_html' => getTplRadioGroup(['name' => 'fix', 'value' => $fix, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $rows[] = ['label_html' => _ACTIVATEP, 'field_html' => getTplRadioGroup(['name' => 'pactive', 'value' => $pactive, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $posttypeopts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES])
        .($pid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'shop'],
            ['nameattr' => 'op', 'valueattr' => 'productsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'pid', 'valueattr' => (string)$pid],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function productsave(): void {
    global $db, $afile, $stop;
    $iswarn = !checkSiteToken();
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
    $posttype = getVar('post', 'posttype', 'text');
    if ($iswarn) {
        setRedirect($afile.'.php?name=shop&op=products', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
        if ($pid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET cid = :pcid, time = :ptime, title = :ptitle, intro = :ptext, body = :pbodytext, price = :pprice, vote = :vote, assoc = :assoc, ihome = :ihome, acomm = :acomm, fix = :fix, status = :pactive WHERE id = :pid', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'pprice' => $pprice, 'vote' => $vote, 'assoc' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive, 'pid' => $pid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_products VALUES (NULL, :pcid, :ptime, :ptitle, :ptext, :pbodytext, :pprice, :vote, :assoc, :ihome, :acomm, \'0\', \'0\', \'0\', \'0\', :fix, :pactive)', ['pcid' => $pcid, 'ptime' => $ptime, 'ptitle' => $ptitle, 'ptext' => $ptext, 'pbodytext' => $pbodytext, 'pprice' => $pprice, 'vote' => $vote, 'assoc' => $associated, 'ihome' => $ihome, 'acomm' => $acomm, 'fix' => $fix, 'pactive' => $pactive]);
        }
        setRedirect($afile.'.php?name=shop&op=products');
    } elseif ($posttype == 'delete') {
        productops($pid, 'd');
    } else {
        productadd();
    }
}

function productops(int|array $id = 0, string $vtyp = ''): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
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
    if (!$iswarn && $id) {
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
    setRedirect($afile.'.php?name=shop&op=products', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function partners(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['shop']['anum'];
    $offset = intval($offset);
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $subtabs = [
        ['href' => $afile.'.php?name=shop&amp;op=partners', 'label' => _NEW],
        ['href' => $afile.'.php?name=shop&amp;op=partners&amp;status=1', 'label' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=partners&amp;status=2', 'label' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=partneradd', 'label' => _ADD],
    ];
    if (getVar('get', 'status', 'num') == 1) {
        $sqlstatus = 'status=1';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $subtab = 1;
    } elseif (getVar('get', 'status', 'num') == 2) {
        $sqlstatus = 'status=0';
        $field = 'name=shop&amp;op=partners&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $subtab = 2;
    } else {
        $sqlstatus = 'status=2';
        $field = 'name=shop&amp;op=partners&amp;';
        $refer = '&amp;refer=1';
        $subtab = 0;
    }
    $tabs = '';
    foreach ($subtabs as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === $subtab,
            'label' => $link['label'],
            'title' => $link['label'],
        ]);
    }
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'tab' => 2,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('tabs', ['tabs_html' => $tabs, 'is_subtabs' => true])]);
    $result = $db->getSqlQuery('SELECT p.id, p.name, p.addr, p.phone, p.email, p.website, p.regdate, p.rest, p.bek, p.status, u.name FROM '.PREFIX_DB.'_partners AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = p.uid) WHERE '.$sqlstatus.' LIMIT '.$offset.', '.$conf['shop']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $pahead = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _NICKNAME, 'is_truncate' => true],
            ['content' => _SITE, 'is_truncate' => true],
            ['content' => _REG, 'is_col_date' => true],
            ['content' => _PARTNERREST, 'is_col_count' => true],
            ['content' => _PARTNERBEK, 'is_col_count' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ];
        $parows = '';
        while([$paid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $paregdate, $parest, $pabek, $paactive, $nick] = $db->getSqlRow($result)) {
            if ($nick) {
                $name = $nick;
                $nick = user_info(filterTextHighlight($nick, ''));
            } else {
                $name = _ANONYM;
                $nick = _ANONYM;
            }
            $items = [
                ['href' => $afile.'.php?name=shop&op=partnerset&amp;id='.$paid.$refer.'&amp;token='.getSiteToken(), 'label' => $paactive ? _DEACTIVATE : _ACTIVATE, 'title' => $paactive ? _DEACTIVATE : _ACTIVATE],
                ['href' => $afile.'.php?name=shop&op=partnerinfo&amp;paid='.$paid, 'label' => _MVIEW, 'title' => _MVIEW],
                ['href' => $afile.'.php?name=shop&op=partneradd&amp;paid='.$paid, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
                ['href' => $afile.'.php?name=shop&op=partnerdel&amp;id='.$paid.$refer.'&amp;token='.getSiteToken(), 'label' => _ONDELETE, 'title' => _ONDELETE, 'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"'],
            ];
            $parows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$paid],
                    ['is_truncate' => true, 'title_text' => $name, 'content_html' => $tpl->getHtmlFrag('info-tooltip', ['items' => [
                        ['label' => _CLIENTNAME, 'value' => htmlspecialchars((string)$paname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                        ['label' => _CLIENTADRES, 'value' => htmlspecialchars((string)$paaddr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                        ['label' => _CLIENTPHONE, 'value' => htmlspecialchars((string)$paphone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => false],
                        ['label' => _EMAIL, 'value' => htmlspecialchars((string)$paemail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'is_last' => true],
                    ]]).$nick],
                    ['is_truncate' => true, 'title_text' => domain($pawebsite), 'content_html' => domain($pawebsite)],
                    ['is_col_date' => true, 'content_html' => date(_TIMESTRING, $paregdate)],
                    ['is_col_count' => true, 'content_html' => $parest.' '.$conf['shop']['valute']],
                    ['is_col_count' => true, 'content_html' => $pabek.' '.$conf['shop']['valute']],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $html = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $pahead, 'rows_html' => $parows]);
        $html .= getTplPager(['limit' => $conf['shop']['anum'], 'maxpg' => $conf['shop']['anump'], 'url' => $field, 'table' => '_partners', 'field' => 'id', 'where' => $sqlstatus]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $html]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function partnerset(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    $id = getVar('get', 'id', 'num');
    if (!$iswarn && $id) {
        [$active] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]));
        $active = ($active == 1) ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=shop&op=partners', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
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
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'tab' => 2,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $tabs = '';
    foreach ([
        ['href' => $afile.'.php?name=shop&amp;op=partners', 'label' => _NEW],
        ['href' => $afile.'.php?name=shop&amp;op=partners&amp;status=1', 'label' => _AKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=partners&amp;status=2', 'label' => _DEAKTIVE],
        ['href' => $afile.'.php?name=shop&amp;op=partneradd', 'label' => _ADD],
    ] as $idx => $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => $link['href'],
            'is_active' => $idx === 3,
            'label' => $link['label'],
            'title' => $link['label'],
        ]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('tabs', ['tabs_html' => $tabs, 'is_subtabs' => true])]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    $rows = [];
    if ($paid) {
        $nick = ($nick) ? user_info($nick) : _ANONYM;
        $rows[] = ['label_html' => _NICKNAME, 'field_html' => $nick];
    }
    $uidfield = ($uid == 0)
        ? $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'uid', 'value_attr' => (string)$uid, 'placeholder_text' => _USER_ID, 'is_required' => true])
        : $tpl->getHtmlFrag('hidden', ['nameattr' => 'uid', 'valueattr' => (string)$uid]).$uid;
    $rows[] = ['label_html' => _USER_ID, 'field_html' => $uidfield];
    $rows[] = ['label_html' => _CLIENTNAME, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'paname', 'value_attr' => $paname, 'is_required' => true])];
    $rows[] = ['label_html' => _CLIENTADRES, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'paaddr', 'value_attr' => $paaddr, 'is_required' => true])];
    $rows[] = ['label_html' => _CLIENTPHONE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'paphone', 'value_attr' => $paphone, 'is_required' => true])];
    $rows[] = ['label_html' => _EMAIL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'paemail', 'value_attr' => $paemail])];
    $rows[] = ['label_html' => _SITE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'pawebsite', 'value_attr' => $pawebsite])];
    $rows[] = ['label_html' => _WEBMONEY, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'pawebmoney', 'value_attr' => $pawebmoney])];
    $rows[] = ['label_html' => _PAYPAL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'papaypal', 'value_attr' => $papaypal])];
    $rows[] = ['label_html' => _REG, 'field_html' => getTplAddDateTime(['name' => 'paregdate', 'time' => $paregdate, 'with' => true, 'max' => 16])];
    if ($paactive != 2) {
        $rows[] = ['label_html' => _PARTNERREST, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'parest', 'value_attr' => $parest])];
        $rows[] = ['label_html' => _PARTNERBEK, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'pabek', 'value_attr' => $pabek])];
    }
    $rows[] = ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'paactive', 'value' => $paactive, 'options' => [['value' => '2', 'label' => _NEW], ['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $posttypeopts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES])
        .($paid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'shop'],
            ['nameattr' => 'op', 'valueattr' => 'partnersave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'paid', 'valueattr' => (string)$paid],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function partnersave(): void {
    global $db, $afile, $stop;
    $iswarn = !checkSiteToken();
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
    $stop = [];
    checkemail($paemail);
    if (!$paname || !$paaddr || !$paphone) $stop[] = _ERROR_ALL;
    $posttype = getVar('post', 'posttype', 'text');
    if ($iswarn) {
        setRedirect($afile.'.php?name=shop&op=partners', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
        if ($paid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_partners SET uid = :uid, name = :paname, addr = :paaddr, phone = :paphone, email = :paemail, website = :pawebsite, webmoney = :pawebmoney, paypal = :papaypal, regdate = :paregdate, rest = :parest, bek = :pabek, status = :paactive WHERE id = :paid', ['uid' => $uid, 'paname' => $paname, 'paaddr' => $paaddr, 'paphone' => $paphone, 'paemail' => $paemail, 'pawebsite' => $pawebsite, 'pawebmoney' => $pawebmoney, 'papaypal' => $papaypal, 'paregdate' => $paregdate, 'parest' => $parest, 'pabek' => $pabek, 'paactive' => $paactive, 'paid' => $paid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_partners VALUES(NULL, :uid, :paname, :paaddr, :paphone, :paemail, :pawebsite, :pawebmoney, :papaypal, :paregdate, :parest, :pabek, :paactive)', ['uid' => $uid, 'paname' => $paname, 'paaddr' => $paaddr, 'paphone' => $paphone, 'paemail' => $paemail, 'pawebsite' => $pawebsite, 'pawebmoney' => $pawebmoney, 'papaypal' => $papaypal, 'paregdate' => $paregdate, 'parest' => $parest, 'pabek' => $pabek, 'paactive' => $paactive]);
        }
        setRedirect($afile.'.php?name=shop&op=partners');
    } elseif ($posttype == 'delete') {
        partnerdel($paid);
    } else {
        partneradd();
    }
}

function partnerdel(int $id = 0): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    $id = ($id) ? $id : getVar('req', 'id', 'num', 0);
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_partners WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=shop&op=partners', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function partnerinfo(): void {
    global $db, $afile, $conf, $tpl;
    $paid = getVar('get', 'paid', 'num');
    $a = 0;
    $partsumges = 0;
    setHead();
    $_ops  = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $_ops, 'tabs' => $_lang, 'tab' => 2, 'subtitle_html' => buildShopSearchBox()]);
    $result = $db->getSqlQuery('SELECT id, uid, name, addr, phone, email, website, webmoney, paypal, regdate, rest, bek, status FROM '.PREFIX_DB.'_partners WHERE id = :paid', ['paid' => $paid]);
    [$paid, $uid, $paname, $paaddr, $paphone, $paemail, $pawebsite, $pawebmoney, $papaypal, $paregdate, $parest, $pabek, $paactive] = $db->getSqlRow($result);
    $result = $db->getSqlQuery('SELECT c.id, c.uid, c.prod, c.part, c.proz, c.name, c.addr, c.phone, c.email, c.website, c.regdate, c.enddate, c.info, c.status, u.id, u.name, p.id, p.title, p.price FROM '.PREFIX_DB.'_clients AS c LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id=c.uid) LEFT JOIN '.PREFIX_DB.'_products AS p ON (p.id=c.prod) WHERE c.part = :uid AND c.status != 2 ORDER BY c.id ASC', ['uid' => $uid]);
    if ($db->getSqlRowCount($result) > 0) {
        $pihead = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _NICKNAME, 'is_truncate' => true],
            ['content' => _PRODUCT, 'is_truncate' => true],
            ['content' => _PREIS, 'is_col_count' => true],
            ['content' => _PERCENT, 'is_col_count' => true],
            ['content' => _SUM, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
        ];
        $pirows = '';
        $partsum = 0;
        $partsumges = 0;
        $a = 0;
        while([$cid, $uid, $product, $partner, $proz, $cname, $caddr, $cphone, $cemail, $cwebsite, $cregdate, $cenddate, $cinfo, $cactive, $uid, $nick, $pid, $ptitle, $pprice] = $db->getSqlRow($result)) {
            $partsum = $pprice / 100 * $proz;
            $partsumges += $partsum;
            $pirows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['is_col_id' => true, 'content_html' => (string)$cid],
                ['is_truncate' => true, 'title_text' => (string)$nick, 'content_html' => user_info($nick)],
                ['is_truncate' => true, 'title_text' => (string)$ptitle, 'content_html' => htmlspecialchars((string)$ptitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                ['is_col_count' => true, 'content_html' => $pprice.' '.$conf['shop']['valute']],
                ['is_col_count' => true, 'content_html' => $proz.' %'],
                ['is_col_count' => true, 'content_html' => $partsum.' '.$conf['shop']['valute']],
                ['is_col_date' => true, 'content_html' => date(_TIMESTRING, $cregdate)],
            ]])]);
            $a++;
        }
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $pihead, 'rows_html' => $pirows])]);
    }
    $srow = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
        ['content_html' => (string)$a],
        ['content_html' => $pabek.' '.$conf['shop']['valute']],
        ['content_html' => $partsumges.' '.$conf['shop']['valute']],
        ['content_html' => $parest.' '.$conf['shop']['valute']],
        ['content_html' => htmlspecialchars((string)$papaypal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['content_html' => htmlspecialchars((string)$pawebmoney, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
    ]])]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'head' => [
        ['content' => _CLIENTEN],
        ['content' => _PARTNERBEK],
        ['content' => _PARTNERGES],
        ['content' => _PARTNERREST],
        ['content' => _PAYPAL],
        ['content' => _WEBMONEY],
    ], 'rows_html' => $srow])]);
    echo $cont;
    setFoot();
}

function export(): void {
    global $db, $afile, $tpl;
    $id = getVar('post', 'id', 'num');
    $bd = getVar('post', 'bd', 'text');
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id == 1 && $bd) {
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
    } elseif (!$iswarn && $id == 2 && $bd) {
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
        $cont = getTplAdminTabs(['ops' => $_ops, 'tabs' => $_lang, 'tab' => 3, 'subtitle_html' => buildShopSearchBox()]);
        $cont .= checkPerms(BASE_DIR.'/uploads/shop/temp');
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _S_NOTE]);
        [$pr] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_products'));
        [$cl] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_clients'));
        [$pa] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_partners'));
        $export = '';
        if ($pr || $cl || $pa) {
            $bdopts = ($pr ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'products', 'label_text' => _PRODUCTS]) : '')
                .($cl ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'clients', 'label_text' => _CLIENTS]) : '')
                .($pa ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'partners', 'label_text' => _PARTNERS]) : '');
            $export = $tpl->getHtmlPart('form', [
                'action_url' => $afile.'.php',
                'hidden' => [
                    ['nameattr' => 'name', 'valueattr' => 'shop'],
                    ['nameattr' => 'op', 'valueattr' => 'export'],
                    ['nameattr' => 'id', 'valueattr' => '1'],
                    ['nameattr' => 'token', 'valueattr' => getSiteToken()],
                ],
                'rows' => [[
                    'label_html' => _DATABASE,
                    'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'bd', 'options_html' => $bdopts]),
                ]],
                'submit_label' => _SAVE,
            ]);
        } else {
            $export = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
        }
        $ocont = '';
        $entries = scandir('uploads/shop/temp');
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if (preg_match('/(\\.csv)$/is', $entry) && $entry != '.' && $entry != '..') {
                    $in = ['#(.*?)products\\.csv#', '#(.*?)clients\\.csv#', '#(.*?)partners\\.csv#'];
                    $out = [_PRODUCTS, _CLIENTS, _PARTNERS];
                    $name = preg_replace($in, $out, $entry);
                    $ocont .= $tpl->getHtmlFrag('select-option', ['value_attr' => $entry, 'label_text' => $name.' - '.$entry]);
                }
            }
        }
        $import = '';
        if ($ocont) {
            $import = $tpl->getHtmlPart('form', [
                'action_url' => $afile.'.php',
                'hidden' => [
                    ['nameattr' => 'name', 'valueattr' => 'shop'],
                    ['nameattr' => 'op', 'valueattr' => 'export'],
                    ['nameattr' => 'id', 'valueattr' => '2'],
                    ['nameattr' => 'token', 'valueattr' => getSiteToken()],
                ],
                'rows' => [[
                    'label_html' => _FILE,
                    'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'bd', 'options_html' => $ocont]),
                ]],
                'submit_label' => _SEND,
            ]);
        } else {
            $import = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
        }
        $tabs = $tpl->getHtmlPart('tabs', [
            'id' => 'shop-export-tabs',
            'is_runtime' => true,
            'is_subtabs' => true,
            'tabs_html' =>
                $tpl->getHtmlFrag('tabs-link', ['href' => '#', 'is_active' => true, 'label' => _EXPORT, 'rel' => 'shop-export-panel-0', 'title' => _EXPORT])
                .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _IMPORT, 'rel' => 'shop-export-panel-1', 'title' => _IMPORT]),
            'content_html' =>
                $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'shop-export-panel-0', 'content_html' => $export])
                .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'shop-export-panel-1', 'content_html' => $import]),
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tabs]);
        echo $cont;
        setFoot();
    }
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $_ops = ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'];
    $_lang = [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs([
        'ops' => $_ops,
        'tabs' => $_lang,
        'tab' => 4,
        'subtitle_html' => buildShopSearchBox(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/shop.php');
    $yesno = static fn(string $name, int|string $value): string => getTplRadioGroup([
        'name' => $name,
        'value' => $value,
        'options' => [
            ['value' => '1', 'label' => _YES],
            ['value' => '0', 'label' => _NO],
        ],
    ]);
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['shop']['defis'] ?? '')])],
        ['label_html' => _C_0, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'clients', 'value_attr' => (string)$conf['shop']['clients']])],
        ['label_html' => _C_2, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'clients1', 'value_attr' => (string)$conf['shop']['clients1']])],
        ['label_html' => _C_4, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'clients2', 'value_attr' => (string)$conf['shop']['clients2']])],
        ['label_html' => _C_1, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'proz', 'value_attr' => (string)$conf['shop']['proz']])],
        ['label_html' => _C_3, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'proz1', 'value_attr' => (string)$conf['shop']['proz1']])],
        ['label_html' => _C_5, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'proz2', 'value_attr' => (string)$conf['shop']['proz2']])],
        ['label_html' => _C_6, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'valute', 'value_attr' => (string)$conf['shop']['valute']])],
        ['label_html' => _C_7, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => (string)$conf['shop']['mail']])],
        ['label_html' => _C_8, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'shop', 'value_attr' => (string)intval($conf['shop']['shop_t'] / 86400)])],
        ['label_html' => _C_9, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'partdays', 'value_attr' => (string)intval($conf['shop']['part_t'] / 86400)])],
        ['label_html' => _BASCOL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'bascol', 'value_attr' => (string)$conf['shop']['bascol']])],
        ['label_html' => _C_11, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'assocnum', 'value_attr' => (string)$conf['shop']['assocnum']])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)$conf['shop']['listnum']])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['shop']['num']])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['shop']['anum']])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['shop']['nump']])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['shop']['anump']])],
        ['label_html' => _HOMCAT, 'field_html' => $yesno('homcat', $conf['shop']['homcat'])],
        ['label_html' => _VIEWCAT, 'field_html' => $yesno('viewcat', $conf['shop']['viewcat'])],
        ['label_html' => _C_32, 'field_html' => $yesno('catdesc', $conf['shop']['catdesc'])],
        ['label_html' => _C_15, 'field_html' => $yesno('subcat', $conf['shop']['subcat'])],
        ['label_html' => _C_14, 'field_html' => $yesno('mailuser', $conf['shop']['mailuser'])],
        ['label_html' => _C_17, 'field_html' => $yesno('date', $conf['shop']['date'])],
        ['label_html' => _C_18, 'field_html' => $yesno('read', $conf['shop']['read'])],
        ['label_html' => _C_19, 'field_html' => $yesno('rate', $conf['shop']['rate'])],
        ['label_html' => _C_20, 'field_html' => $yesno('letter', $conf['shop']['letter'])],
        ['label_html' => _C_23, 'field_html' => $yesno('assoc', $conf['shop']['assoc'])],
        ['label_html' => _C_24, 'field_html' => $yesno('mailsend', $conf['shop']['mailsend'])],
        ['label_html' => _C_25, 'field_html' => $yesno('part', $conf['shop']['part'])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _C_26, 'hint' => _PART_ID]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'partlink_view', 'value_attr' => (string)$conf['shop']['partlink'], 'input_attr' => ' readonly']), 'is_full' => true],
        ['label_html' => _C_27, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'sende', 'value_text' => (string)$conf['shop']['sende'], 'rows_num' => 5]), 'is_full' => true],
        ['label_html' => _C_28, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'userinfo', 'value_text' => (string)$conf['shop']['userinfo'], 'rows_num' => 5]), 'is_full' => true],
        ['label_html' => _C_29, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'partinfo', 'value_text' => (string)$conf['shop']['partinfo'], 'rows_num' => 5]), 'is_full' => true],
        ['label_html' => _C_30, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'partinfo2', 'value_text' => (string)$conf['shop']['partinfo2'], 'rows_num' => 5]), 'is_full' => true],
        ['label_html' => _C_31, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'shopinfo', 'value_text' => (string)$conf['shop']['shopinfo'], 'rows_num' => 5]), 'is_full' => true],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'shop'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $defis = getVar('post', 'defis', 'text', urldecode($conf['shop']['defis'] ?? '%3E'));
        $xdefis = ($defis) ? urlencode($defis) : '%3E';
        $shop = getVar('post', 'shop', 'num', (int)(($conf['shop']['shop_t'] ?? 2592000) / 86400));
        $xtshop = (!$shop) ? 2592000 : intval($shop * 86400);
        $part = getVar('post', 'partdays', 'num', getVar('post', 'part', 'num', (int)(($conf['shop']['part_t'] ?? 2592000) / 86400)));
        $xtpart = (!$part) ? 2592000 : intval($part * 86400);
        $bascol = getVar('post', 'bascol', 'num', (int)($conf['shop']['bascol'] ?? 1));
        $xcol = (!$bascol) ? '1' : $bascol;
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
            'sende' => getVar('post', 'sende', 'text', $conf['shop']['sende'] ?? ''),
            'userinfo' => getVar('post', 'userinfo', 'text', $conf['shop']['userinfo'] ?? ''),
            'partinfo' => getVar('post', 'partinfo', 'text', $conf['shop']['partinfo'] ?? ''),
            'partinfo2' => getVar('post', 'partinfo2', 'text', $conf['shop']['partinfo2'] ?? ''),
            'shopinfo' => getVar('post', 'shopinfo', 'text', $conf['shop']['shopinfo'] ?? ''),
        ];
        setConfigFile('shop.php', $cont);
    }
    setRedirect($afile.'.php?name=shop&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=shop&amp;op=clients', 'name=shop&amp;op=products', 'name=shop&amp;op=partners', 'name=shop&amp;op=export', 'name=shop&amp;op=config', 'name=shop&amp;op=info'],
        'tabs' => [_CLIENTS, _PRODUCTS, _PARTNERS, _EXPORT.' / '._IMPORT, _PREFERENCES, _INFO],
        'subtitle_html' => buildShopSearchBox(),
    ]);
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
