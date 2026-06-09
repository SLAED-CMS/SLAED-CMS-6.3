<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getCommentsSearch(): string {
    global $db, $afile, $tpl;
    $status = getVar('req', 'status', 'num') ? 1 : 0;
    $modul = getVar('req', 'modul', 'var');
    $search = getVar('req', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('req', 'chng');
    $modopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _ALL,
        'is_selected' => $modul === '',
    ]);
    $result = $db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_comment ORDER BY modul ASC');
    while ([$m] = $db->getSqlRow($result)) {
        if (!$m) continue;
        $modopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $m,
            'label_text' => getModuleName($m).' - '.$m,
            'is_selected' => $modul === $m,
        ]);
    }
    $searchopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ID, 'is_selected' => $search === 1]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _COMMENT, 'is_selected' => $search === 2]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _POSTEDBY, 'is_selected' => $search === 3]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _MODUL, 'is_selected' => $search === 4]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '5', 'label_text' => _IP, 'is_selected' => $search === 5]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => array_values(array_filter([
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            $status ? ['nameattr' => 'status', 'valueattr' => '1'] : null,
        ])),
        'content_html' =>
            _MODUL.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'modul',
                'select_attr' => ' OnChange="submit()"',
                'options_html' => $modopts,
            ]).
            ' '._SEARCH.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'search',
                'options_html' => $searchopts,
            ]).
            ' '.$tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'chng',
                'value_attr' => $chng,
                'maxlength_num' => 60,
            ]).
            ' '.$tpl->getHtmlFrag('button', [
                'submit_label' => _OK,
                'button_type' => 'submit',
            ]),
    ]);
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
}

function comments(): void {
    global $conf, $db, $afile, $tpl, $prs;
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $modul = getVar('get', 'modul', 'var');
    $search = getVar('get', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = trim((string)getVar('get', 'chng'));
    $subtitle = getCommentsSearch();
    $baseq = 'name=comments';
    if ($modul !== '') $baseq .= '&amp;modul='.rawurlencode($modul);
    $baseq .= '&amp;search='.$search;
    if ($chng !== '') $baseq .= '&amp;chng='.rawurlencode($chng);
    $curq = $baseq.($status ? '&amp;status=1' : '');
    $cont = getTplAdminTabs([
        'ops' => [
            $baseq,
            $baseq.'&amp;status=1',
            $curq.'&amp;op=config',
            $curq.'&amp;op=info',
        ],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO],
        'tab' => $status,
        'subtitle_html' => $subtitle,
    ]);
    $anum = (int)($conf['comments']['anum'] ?? 25);
    $anump = (int)($conf['comments']['anump'] ?? 8);
    $offset = (int)(($num = getVar('get', 'num', 'num', 1)) - 1) * $anum;
    $order = ($conf['comments']['sort'] ?? 1) ? 'ASC' : 'DESC';
    $where = $status ? 'status = :status' : 'status != :status';
    $wcnt = $where;
    $pars = ['status' => 0];
    if ($modul !== '') {
        $where .= ' AND s.modul = :modul';
        $wcnt .= ' AND modul = :modul';
        $pars['modul'] = $modul;
    }
    if ($chng !== '') {
        if ($search === 1) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.id LIKE :find';
            $wcnt .= ' AND id LIKE :find';
        } elseif ($search === 2) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.body LIKE :find';
            $wcnt .= ' AND body LIKE :find';
        } elseif ($search === 3) {
            $pars['fnam'] = '%'.$chng.'%';
            $pars['fusr'] = '%'.$chng.'%';
            $where .= ' AND (s.name LIKE :fnam OR u.name LIKE :fusr)';
            $wcnt .= ' AND (name LIKE :fnam OR uid IN (SELECT id FROM '.PREFIX_DB.'_users WHERE name LIKE :fusr))';
        } elseif ($search === 4) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.modul LIKE :find';
            $wcnt .= ' AND modul LIKE :find';
            } elseif ($search === 5) {
                $pars['find'] = '%'.$chng.'%';
                $where .= ' AND s.ip LIKE :find';
                $wcnt .= ' AND ip LIKE :find';
            }
        }
    $field = $curq.'&amp;';
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.modul, s.time, s.uid, s.name, s.ip, s.body, s.status, u.name FROM '.PREFIX_DB.'_comment AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE '.$where.' ORDER BY s.time '.$order.' LIMIT '.$offset.', '.$anum, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $com_modul, $time, $uid, $uname, $ip, $com_text, $com_status, $nick] = $db->getSqlRow($result)) {
            $modname = trim((string)getModuleName($com_modul));
            $modlabel = ($modname !== '') ? $modname.' - '.$com_modul : $com_modul;
            $post = $nick ? filterTextHighlight(user_info($nick), $chng) : filterTextHighlight($uname ?: _ANONYM, $chng);
            $comment = trim(strip_tags((string)$prs->filterContent($com_text, false, $com_modul)));
            $comment = cutstr($comment, 120);
            $comment = filterTextHighlight($comment, $chng);
            $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
            $act = ((int)$com_status) ? 0 : 1;
            $items = [
                ['href' => 'index.php?name='.$com_modul.'&amp;op=view&amp;id='.$cid.'#'.$id, 'label' => _MVIEW, 'title' => _MVIEW],
                ['href' => $afile.'.php?'.$curq.'&amp;op=edit&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
            ];
            $acttxt = ((int)$com_status) ? _DEACTIVATE : _ACTIVATE;
            $items[] = [
                'href' => $afile.'.php?'.$curq.'&amp;op=approve&amp;id='.$id.'&amp;typ='.$act.'&amp;token='.getSiteToken(),
                'label' => $acttxt,
                'title' => $acttxt,
            ];
            $items[] = [
                'href' => $afile.'.php?'.$curq.'&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($comment).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => filterTextHighlight((string)$id, $chng)],
                    ['is_truncate' => true, 'title_text' => $comment, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _MODUL, 'value' => $modlabel],
                            ['label' => 'CID', 'value' => (string)$cid],
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                            ['label' => _IP, 'value' => $iptext, 'is_last' => true],
                        ],
                        'title_text' => $comment,
                    ]).$comment],
                    ['class_name' => 'sl-col-module', 'is_truncate' => true, 'title_text' => $modlabel, 'content_html' => filterTextHighlight($modlabel, $chng)],
                    ['is_col_author' => true, 'content_html' => $post],
                    ['is_col_date' => true, 'content_html' => format_time($time, _TIMESTRING)],
                    ['is_col_status' => true, 'content_html' => ad_status('', $com_status)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                    ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => (string)$id, 'is_check' => true])],
                ],
            ])]);
        }
        $bulk = $tpl->getHtmlFrag('inline-badge', ['is_action_label' => true, 'label' => _CHECKOP]).' '
            .$tpl->getHtmlFrag('select', [
                'name_attr' => 'typ',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => 'a'.($status ? '1' : '0'),
                    'label_text' => $status ? _ACTIVATE : _DEACTIVATE,
                ]).$tpl->getHtmlFrag('select-option', [
                    'value_attr' => 'd',
                    'label_text' => _DELETE,
                ]),
            ])
            .$tpl->getHtmlFrag('button', [
                'submit_label' => _OK,
                'button_type' => 'submit',
            ]);
        $pager = getTplPager([
            'limit' => $anum,
            'maxpg' => $anump,
            'url' => $field,
            'table' => '_comment',
            'field' => 'id',
            'where' => $wcnt,
            'where_params' => $pars,
        ]);
        $body = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=comments&amp;op=actions',
            'hidden' => array_filter([
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
                ['nameattr' => 'status', 'valueattr' => (string)$status],
                $modul !== '' ? ['nameattr' => 'modul', 'valueattr' => $modul] : null,
                ['nameattr' => 'search', 'valueattr' => (string)$search],
                $chng !== '' ? ['nameattr' => 'chng', 'valueattr' => $chng] : null,
            ]),
            'content_html' => $tpl->getHtmlFrag('table', [
                'is_wrapless' => true,
                'is_fixed' => true,
                'head' => [
                    ['content' => _ID, 'is_col_id' => true],
                    ['content' => _COMMENT, 'is_truncate' => true],
                    ['content' => _MODUL, 'is_truncate' => true],
                    ['content' => _POSTEDBY, 'is_col_author' => true],
                    ['content' => _DATE, 'is_col_date' => true],
                    ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                    ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
                    ['content' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'input_id' => 'markcheck', 'is_check' => true, 'input_attr' => 'title="'._CHECKALL.'"']), 'is_col_check' => true, 'nosort' => true],
                ],
                'rows_html' => $rows,
            ]),
            'actions_html' => $tpl->getHtmlFrag('module-foot', [
                'is_list' => true,
                'pager_html' => $pager,
                'actions_html' => $bulk,
            ]),
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    }
    if (!isset($body)) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function edit(): void {
    global $db, $afile, $tpl, $prs;
    $id = getVar('get', 'id', 'num');
    $status = getVar('get', 'status', 'num', 0);
    $modul = getVar('get', 'modul', 'var');
    $search = getVar('get', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('get', 'chng', 'raw', '');
    setHead();
    $baseq = 'name=comments';
    if ($modul !== '') $baseq .= '&amp;modul='.rawurlencode($modul);
    $baseq .= '&amp;search='.$search;
    if ($chng !== '') $baseq .= '&amp;chng='.rawurlencode($chng);
    $curq = $baseq.($status ? '&amp;status=1' : '');
    $cont = getTplAdminTabs([
        'ops' => [
            $baseq,
            $baseq.'&amp;status=1',
            $curq.'&amp;op=config',
            $curq.'&amp;op=info',
        ],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO],
        'tab' => $status,
        'subtitle_html' => getCommentsSearch(),
    ]);
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.modul, s.time, s.uid, s.name, s.ip, s.body, s.status, u.name FROM '.PREFIX_DB.'_comment AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :id', ['id' => $id]);
    [$id, $cid, $com_modul, $time, $uid, $uname, $ip, $com_text, $com_status, $nick] = $db->getSqlRow($result);
    $modname = trim((string)getModuleName((string)$com_modul));
    $modlabel = ($modname !== '') ? $modname.' - '.$com_modul : $com_modul;
    $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
    $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
    $rows = [
        ['label_html' => _MODUL, 'field_html' => htmlspecialchars((string)$modlabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _ID, 'field_html' => (string)$cid],
        ['label_html' => _POSTEDBY, 'field_html' => htmlspecialchars((string)$post, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _DATE, 'field_html' => format_time((string)$time, _TIMESTRING)],
        ['label_html' => _IP, 'field_html' => htmlspecialchars((string)$iptext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _STATUS, 'field_html' => ad_status('', $com_status)],
        [
            'label_html' => _COMMENT,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'comment',
                'rows_num' => 12,
                'value_text' => (string)$com_text,
            ]),
            'is_full' => true,
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?'.$curq.'&amp;op=editsave',
        'hidden' => array_filter([
            ['nameattr' => 'id', 'valueattr' => (string)$id],
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            $status ? ['nameattr' => 'status', 'valueattr' => '1'] : null,
            $modul !== '' ? ['nameattr' => 'modul', 'valueattr' => $modul] : null,
            ['nameattr' => 'search', 'valueattr' => (string)$search],
            $chng !== '' ? ['nameattr' => 'chng', 'valueattr' => $chng] : null,
        ]),
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function editsave(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $id = getVar('post', 'id', 'num');
    $com_text = getVar('post', 'comment', 'text', '');
    $status = getVar('post', 'status', 'num', 0);
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    if (!$warn) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET body = :comment WHERE id = :id', ['comment' => $com_text, 'id' => $id]);
    }
    $back = 'name=comments';
    if ($status) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    setRedirect($afile.'.php?'.$back, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function actions(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $status = getVar('post', 'status', 'num', 0);
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    $typ = getVar('post', 'typ', 'text', '');
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (!$warn && is_array($id) && $typ !== '') {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $curstatus] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    if ($typ[0] === 'a') {
                        $next = (int)substr($typ, 1);
                        if ((int)$curstatus !== $next) {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET status = :status WHERE id = :id', ['status' => $next, 'id' => $val]);
                            numcom($cid, $mod, $next ? false : true, $uid);
                        }
                    } elseif ($typ[0] === 'd') {
                        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]);
                        if ($curstatus) numcom($cid, $mod, 1, $uid);
                    }
                }
            }
        }
    }
    $backStatus = $status;
    if ($typ === 'a1') $backStatus = 0;
    if ($typ === 'a0') $backStatus = 1;
    $back = 'name=comments';
    if ($backStatus) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    $succ = ($typ[0] === 'd') ? _SUCCDELETE : _SUCCSTATUS;
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : $succ, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['comments']['num']])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['comments']['anum']])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['comments']['nump']])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['comments']['anump']])],
        ['label_html' => _COMLETTER, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'letter', 'value_attr' => (string)$conf['comments']['letter']])],
        ['label_html' => _CEDITT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'edit', 'value_attr' => (string)intval($conf['comments']['edit'] / 60)])],
        ['label_html' => _CSEND, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'send', 'value_attr' => (string)$conf['comments']['send']])],
        ['label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'sort', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '1']).$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DESC, 'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '0'])])],
        ['label_html' => _ALLOWANONPOST, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'anonpost',
            'is_config' => true,
            'options_html' => $tpl->getHtmlFrag('select-option', [
                'value_attr' => '0',
                'label_text' => _DEACTIVATE,
                'is_selected' => (string)($conf['comments']['anonpost'] ?? '0') === '0',
            ]).$tpl->getHtmlFrag('select-option', [
                'value_attr' => '1',
                'label_text' => _APOSTMOD,
                'is_selected' => (string)($conf['comments']['anonpost'] ?? '0') === '1',
            ]).$tpl->getHtmlFrag('select-option', [
                'value_attr' => '2',
                'label_text' => _APOSTNOMOD,
                'is_selected' => (string)($conf['comments']['anonpost'] ?? '0') === '2',
            ]),
        ])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _NOLINKP, 'hint' => _NOAUM]), 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'link', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '0']).$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ANONIMP, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '1']).$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '2'])])],
        ['label_html' => _NOALINKP, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'alink', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '0']).$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ANONIMP, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '1']).$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '2'])])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)(int)$conf['comments']['addmail'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VPRIVAT, 'field_html' => getTplRadioGroup(['name' => 'privat', 'value' => (string)(int)$conf['comments']['privat'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VPROFIL, 'field_html' => getTplRadioGroup(['name' => 'profil', 'value' => (string)(int)$conf['comments']['profil'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VWEB, 'field_html' => getTplRadioGroup(['name' => 'web', 'value' => (string)(int)$conf['comments']['web'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=comments&amp;op=save',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 15),
            'anum' => getVar('post', 'anum', 'num', 15),
            'nump' => getVar('post', 'nump', 'num', 5),
            'anump' => getVar('post', 'anump', 'num', 5),
            'letter' => getVar('post', 'letter', 'num', 50),
            'edit' => getVar('post', 'edit', 'num', 600) * 60,
            'send' => getVar('post', 'send', 'num', 30),
            'sort' => getVar('post', 'sort', 'num'),
            'anonpost' => getVar('post', 'anonpost', 'num'),
            'link' => getVar('post', 'link', 'num'),
            'alink' => getVar('post', 'alink', 'num'),
            'addmail' => getVar('post', 'addmail', 'num'),
            'privat' => getVar('post', 'privat', 'num'),
            'profil' => getVar('post', 'profil', 'num'),
            'web' => getVar('post', 'web', 'num'),
        ];
        setConfigFile('comments.php', $cont);
    }
    setRedirect($afile.'.php?name=comments&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function approve(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $typ = getVar('post', 'typ', 'num') ?: getVar('get', 'typ', 'num');
    $status = getVar('post', 'status', 'num', getVar('get', 'status', 'num', 0));
    $modul = getVar('post', 'modul', 'var', getVar('get', 'modul', 'var'));
    $search = getVar('post', 'search', 'num', getVar('get', 'search', 'num', 2));
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)(getVar('post', 'chng', 'raw', '') ?: getVar('get', 'chng', 'raw', ''));
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $status] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    $next = $typ ? 1 : 0;
                    if ((int)$status !== (int)$next) {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET status = :status WHERE id = :id', ['status' => $next, 'id' => $val]);
                        numcom($cid, $mod, $typ ? 0 : 1, $uid);
                    }
                }
            }
        }
    }
    $back = 'name=comments';
    if (!$typ) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function delete(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $status = getVar('post', 'status', 'num', getVar('get', 'status', 'num', 0));
    $modul = getVar('post', 'modul', 'var', getVar('get', 'modul', 'var'));
    $search = getVar('post', 'search', 'num', getVar('get', 'search', 'num', 2));
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)(getVar('post', 'chng', 'raw', '') ?: getVar('get', 'chng', 'raw', ''));
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $status] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]);
                    if ($status) numcom($cid, $mod, 1, $uid);
                }
            }
        }
    }
    $back = 'name=comments';
    if ($status) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: comments(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'actions': actions(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'approve': approve(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
