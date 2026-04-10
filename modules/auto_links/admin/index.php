<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('auto_links')) die('Illegal file access');


function auto_links(): void {
    global $db, $afile, $conf, $tpl;
    $ops = ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset&amp;token='.getSiteToken(), 'name=auto_links&amp;op=zerodel&amp;token='.getSiteToken(), 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'];
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
    ]);
    if (!$conf['referers']['refer']) {
        $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _A_NOTE]);
    }
    $result = $db->getSqlQuery(
        'SELECT id, title, url, hits, outs, added FROM '.PREFIX_DB.'_auto_links ORDER BY hits ASC LIMIT :offset, :limit',
        ['offset' => $offset, 'limit' => (int)$conf['auto_links']['anum']]
    );
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $name, $url, $hits, $outs, $added] = $db->getSqlRow($result)) {
            $items = [];
            if ((int)$hits > 0) {
                $items[] = [
                    'href' => $afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id,
                    'label' => _MVIEW,
                    'title' => _MVIEW,
                ];
            }
            $items[] = [
                'href' => $afile.'.php?name=auto_links&amp;op=add&amp;id='.$id,
                'label' => _FULLEDIT,
                'title' => _FULLEDIT,
            ];
            $items[] = [
                'href' => $afile.'.php?name=auto_links&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                'label' => _DELETE,
                'title' => _DELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($name).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('new/table-row', [
                'cells_html' => $tpl->getHtmlFrag('new/table-cells', [
                    'cells' => [
                        ['content_html' => (string)$id],
                        ['content_html' => $tpl->getHtmlFrag('new/title-tip', [
                            'items' => [
                                ['label' => _REG, 'value' => format_time($added, _TIMESTRING), 'is_last' => true],
                            ],
                            'label_text' => cutstr($name, 40),
                            'title_text' => $name,
                        ])],
                        ['content_html' => domain($url)],
                        ['content_html' => (string)$hits],
                        ['content_html' => (string)$outs],
                        ['content_html' => $tpl->getHtmlFrag('new/row-actions', [
                            'trigger_label' => _FUNCTIONS,
                            'items' => $items,
                        ])],
                    ],
                ]),
            ]);
        }
        $body = $tpl->getHtmlFrag('new/table', [
            'is_wrapless' => true,
            'head' => [
                ['content' => _ID],
                ['content' => _SITENAME],
                ['content' => _SITEURL],
                ['content' => _HITS],
                ['content' => _OUTS],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager([
            'limit' => (int)$conf['auto_links']['anum'],
            'maxpg' => (int)$conf['auto_links']['anump'],
            'url' => 'name=auto_links&amp;',
            'table' => '_auto_links',
            'field' => 'id',
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', [
            'content_html' => $tpl->getHtmlFrag('new/alert', ['text' => _NO_INFO]),
        ]);
    }
    echo $cont;
    setFoot();
}

function stats(): void {
    global $db, $afile, $conf, $tpl;
    $id = getVar('req', 'id', 'num');
    $sort = getVar('req', 'sort', 'num');
    $order = getVar('req', 'order', 'num');
    $num = getVar('get', 'num', 'num', 1);
    if ($sort == 1) {
        $count = 'referer';
        $ordby = 'hits';
    } elseif ($sort == 2) {
        $count = 'referer';
        $ordby = 'referer';
    } elseif ($sort == 3) {
        $count = 'url';
        $ordby = 'hits';
    } elseif ($sort == 4) {
        $count = 'url';
        $ordby = 'url';
    } elseif ($sort == 5) {
        $count = 'name';
        $ordby = 'hits';
    } elseif ($sort == 6) {
        $count = 'name';
        $ordby = 'name';
    } elseif ($sort == 7) {
        $count = 'ip';
        $ordby = 'hits';
    } elseif ($sort == 8) {
        $count = 'ip';
        $ordby = 'ip';
    } elseif ($sort == 9) {
        $count = 'time';
        $ordby = 'hits';
    } else {
        $count = 'time';
        $ordby = 'time';
    }
    $ordsc = $order == 1 ? 'ASC' : 'DESC';
    $result = $db->getSqlQuery(
        'SELECT Count('.$count.') AS hits, uid, name, ip, referer, url, time FROM '.PREFIX_DB.'_referer WHERE lid = :lid GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc,
        ['lid' => $id]
    );
    $options = '';
    foreach ([_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF] as $_k => $_v) {
        $_sort = $_k + 1;
        $options .= $tpl->getHtmlFrag('new/select-option', [
            'value_attr' => (string)$_sort,
            'label_text' => $_v,
            'is_selected' => $sort == $_sort,
        ]);
    }
    $ordopts = '';
    foreach ([_ASC, _DESC] as $_k => $_v) {
        $_ord = $_k + 1;
        $ordopts .= $tpl->getHtmlFrag('new/select-option', [
            'value_attr' => (string)$_ord,
            'label_text' => $_v,
            'is_selected' => $order == $_ord,
        ]);
    }
    $subtitle = $id ? $tpl->getHtmlPart('searchbox', ['searchbox' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id,
        'content_html' =>
            _SORTE.': '.
            $tpl->getHtmlFrag('new/select', ['name_attr' => 'sort', 'options_html' => $options]).
            ' '.
            $tpl->getHtmlFrag('new/select', ['name_attr' => 'order', 'options_html' => $ordopts]).
            ' '.
            $tpl->getHtmlFrag('new/submit', ['submit_label' => _OK]),
    ])]) : '';
    $ops = ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset&amp;token='.getSiteToken(), 'name=auto_links&amp;op=zerodel&amp;token='.getSiteToken(), 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'];
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'subtitle_html' => $subtitle,
    ]);
    if (!$conf['referers']['refer']) {
        $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _A_NOTE]);
    }
    $list = [];
    while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
        $list[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
    }
    if ($list) {
        $countall = count($list);
        $pages = (int)ceil($countall / $conf['auto_links']['anum']);
        $page = max(1, min($num, max(1, $pages)));
        $offset = ($page - 1) * $conf['auto_links']['anum'];
        $slice = array_slice($list, $offset, (int)$conf['auto_links']['anum']);
        $rows = '';
        foreach ($slice as $item) {
            $name = $item[1] ? user_info($item[2]) : $item[2];
            $rows .= $tpl->getHtmlFrag('new/table-row', [
                'cells_html' => $tpl->getHtmlFrag('new/table-cells', [
                    'cells' => [
                        ['content_html' => (string)$item[0]],
                        ['content_html' => $tpl->getHtmlFrag('new/title-tip', [
                            'items' => [
                                ['label' => _DATE, 'value' => date(_TIMESTRING, $item[6]), 'is_last' => true],
                            ],
                        ]).$name],
                        ['content_html' => user_geo_ip($item[3], 4)],
                        ['content_html' => domain($item[4], 35)],
                        ['content_html' => domain($item[5], 15)],
                    ],
                ]),
            ]);
        }
        $body = $tpl->getHtmlFrag('new/table', [
            'is_wrapless' => true,
            'disable_sort' => true,
            'head' => [
                ['content' => _ID],
                ['content' => _NICKNAME],
                ['content' => _IP],
                ['content' => _REF_URL],
                ['content' => _IN_URL],
            ],
            'rows_html' => $rows,
        ]);
        if ($countall > (int)$conf['auto_links']['anum']) {
            $prev = $page > 1
                ? $tpl->getHtmlFrag('new/pager-link', ['href' => $afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;num='.($page - 1), 'label' => _BACK, 'title' => _BACK, 'is_nav' => true])
                : $tpl->getHtmlFrag('new/pager-link', ['label' => _BACK, 'title' => _BACK, 'is_cur' => true, 'is_nav' => true]);
            $items = '';
            $maxpg = (int)$conf['auto_links']['anump'];
            $nnum = $maxpg + 1;
            for ($i = 1; $i <= $pages; $i++) {
                if ($i === $page) {
                    $items .= $tpl->getHtmlFrag('new/pager-link', ['label' => (string)$i, 'title' => (string)$i, 'is_cur' => true]);
                } elseif ($i === 1 || $i === $pages || ($i > ($page - $maxpg) && $i < ($page + $maxpg))) {
                    $items .= $tpl->getHtmlFrag('new/pager-link', [
                        'href' => $afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;num='.$i,
                        'label' => (string)$i,
                        'title' => (string)$i,
                    ]).' ';
                }
                if ($i < $pages) {
                    if (($page > $nnum) && ($i === 1)) {
                        $items .= $tpl->getHtmlFrag('new/pager-dots', []);
                    }
                    if (($page < ($pages - $maxpg)) && ($i === ($pages - 1))) {
                        $items .= $tpl->getHtmlFrag('new/pager-dots', []);
                    }
                }
            }
            $next = $page < $pages
                ? $tpl->getHtmlFrag('new/pager-link', ['href' => $afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;num='.($page + 1), 'label' => _NEXT, 'title' => _NEXT, 'is_nav' => true])
                : $tpl->getHtmlFrag('new/pager-link', ['label' => _NEXT, 'title' => _NEXT, 'is_cur' => true, 'is_nav' => true]);
            $body .= $tpl->getHtmlFrag('new/pager', [
                'items' => $items,
                'prev' => $prev,
                'next' => $next,
            ]);
        }
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', [
            'content_html' => $tpl->getHtmlFrag('new/alert', ['text' => _NO_INFO]),
        ]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $stop = $stop ?? [];
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, intro, url, email, hits, outs FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        [$id, $name, $desc, $site, $email, $hits, $outs] = $db->getSqlRow($result);
    } else {
        $id = getVar('post', 'id', 'num');
        $name = getVar('post', 'name', 'title', '');
        $email = getVar('post', 'mail', 'var', '');
        $desc = getVar('post', 'desc', 'text', '');
        $site = getVar('post', 'site', 'url', 'https://');
        $hits = getVar('post', 'hits', 'num', 0);
        $outs = getVar('post', 'outs', 'num', 0);
    }
    $ops = ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset&amp;token='.getSiteToken(), 'name=auto_links&amp;op=zerodel&amp;token='.getSiteToken(), 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'];
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab' => 1,
    ]);
    if ($stop) {
        $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => getStopText((array)$stop)]);
    }
    $rows = [
        ['label_html' => _SITENAME.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'text', 'name_attr' => 'name', 'value_attr' => $name, 'maxlength_num' => 255])],
        ['label_html' => _A_LINKS_L.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'text', 'name_attr' => 'site', 'value_attr' => $site, 'maxlength_num' => 255])],
        ['label_html' => _A_LINKS_E.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => $email, 'maxlength_num' => 255])],
        ['label_html' => _HITS.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'hits', 'value_attr' => (string)$hits])],
        ['label_html' => _OUTS.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'outs', 'value_attr' => (string)$outs])],
        ['label_html' => _A_LINKS_TEXT.':', 'field_html' => $tpl->getHtmlFrag('new/textarea', ['name_attr' => 'desc', 'value_text' => $desc, 'rows_num' => 5])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=auto_links&amp;op=save',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => (string)$id],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num');
    $name = getVar('post', 'name', 'title', '');
    $desc = getVar('post', 'desc', 'text', '');
    $site = getVar('post', 'site', 'url', 'https://');
    $email = getVar('post', 'mail', 'var', '');
    $hits = getVar('post', 'hits', 'num', 0);
    $outs = getVar('post', 'outs', 'num', 0);
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$name) $stop[] = _CERROR10;
        if (!$desc) $stop[] = _CERROR11;
        if (!$site) $stop[] = _CERROR4;
        if (!$stop) {
            if ($id) {
                $db->getSqlQuery(
                    'UPDATE '.PREFIX_DB.'_auto_links SET title = :name, intro = :desc, url = :url, email = :email, hits = :hits, outs = :outs WHERE id = :id',
                    ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs, 'id' => $id]
                );
            } else {
                $db->getSqlQuery(
                    'INSERT INTO '.PREFIX_DB.'_auto_links (title, intro, url, email, hits, outs, added) VALUES (:name, :desc, :url, :email, :hits, :outs, now())',
                    ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs]
                );
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    setRedirect($afile.'.php?name=auto_links', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=auto_links', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function hitreset(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = 0, outs = 0');
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid != 0');
    }
    setRedirect($afile.'.php?name=auto_links', false, 302, $iswarn ? _TOKENMISS : _SUCCCLEAR, $iswarn);
}

function zerodel(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE hits = 0');
    }
    setRedirect($afile.'.php?name=auto_links', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    $ops = ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset&amp;token='.getSiteToken(), 'name=auto_links&amp;op=zerodel&amp;token='.getSiteToken(), 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'];
    $path = 'templates/'.$conf['theme'].'/images/banners/';
    $pickopts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry)) {
            $pickopts .= $tpl->getHtmlFrag('new/select-option', [
                'value_attr' => $entry,
                'label_text' => $entry,
                'is_selected' => $conf['auto_links']['img'] == $entry,
            ]);
        }
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _A_1.':', 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'img', 'options_html' => $pickopts, 'is_config' => true, 'select_attr' => 'id="img_replace"'])],
        ['label_html' => _A_2.':', 'field_html' => $tpl->getHtmlFrag('new/image-preview', ['src_attr' => $path.$conf['auto_links']['img'], 'image_id' => 'picture', 'alt_text' => _SITELOGO])],
        ['label_html' => _C_33.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['auto_links']['num'], 'is_config' => true])],
        ['label_html' => _C_34.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['auto_links']['anum'], 'is_config' => true])],
        ['label_html' => _C_35.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['auto_links']['nump'], 'is_config' => true])],
        ['label_html' => _C_36.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['auto_links']['anump'], 'is_config' => true])],
        ['label_html' => _A_4.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'strip', 'value_attr' => (string)$conf['auto_links']['strip'], 'is_config' => true])],
        ['label_html' => _A_5.':', 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'limit', 'value_attr' => (string)$conf['auto_links']['limit'], 'is_config' => true])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)$conf['auto_links']['addmail'], 'options' => $yesno])],
    ];
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab' => 4,
    ]);
    if (!$conf['referers']['refer']) {
        $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _A_NOTE]);
    }
    $body = checkPerms(CONFIG_DIR.'/auto_links.php');
    $body .= $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=auto_links&amp;op=configsave',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $cont = [
            'img' => getVar('post', 'img', 'var', ''),
            'num' => getVar('post', 'num', 'num', 10),
            'anum' => getVar('post', 'anum', 'num', 10),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'strip' => getVar('post', 'strip', 'num', 100),
            'limit' => getVar('post', 'limit', 'num', 1),
            'addmail' => getVar('post', 'addmail', 'num', 0),
        ];
        setConfigFile('auto_links.php', $cont);
    }
    setRedirect($afile.'.php?name=auto_links&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset&amp;token='.getSiteToken(), 'name=auto_links&amp;op=zerodel&amp;token='.getSiteToken(), 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab' => 5,
    ]);
}

switch ($op) {
    default: auto_links(); break;
    case 'stats': stats(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'hitreset': hitreset(); break;
    case 'zerodel': zerodel(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
