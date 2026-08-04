<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getCommentsSearch(): string {
    global $afile, $tpl, $com;
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
    foreach ($com->getModuleList() as $one) {
        $modopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $one,
            'label_text' => getModuleName($one).' - '.$one,
            'is_selected' => $modul === $one,
        ]);
    }
    $searchopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ID, 'is_selected' => $search === 1]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _COMMENT, 'is_selected' => $search === 2]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _POSTEDBY, 'is_selected' => $search === 3]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _MODUL, 'is_selected' => $search === 4]).
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
    return $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $form]);
}

# The moderation overview: the drifted counters, the bulk form and the comment table
# Every write recomputes the counter of its own target, so a drift left here belongs to a target nobody has commented on since; it is shown rather than repaired, as a symptom
# The table sits outside the bulk form, since the row actions are POST forms of their own and a browser drops a nested form; the checkboxes stay bound through the form attribute
function comments(): void {
    global $conf, $afile, $tpl, $prs, $com;
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $modul = getVar('get', 'modul', 'var');
    $search = getVar('get', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = trim((string)getVar('get', 'chng'));
    $subtitle = getCommentsSearch();
    $baseq = 'name=comments';
    if ($modul !== '') $baseq .= '&modul='.rawurlencode($modul);
    $baseq .= '&search='.$search;
    if ($chng !== '') $baseq .= '&chng='.rawurlencode($chng);
    $curq = $baseq.($status ? '&status=1' : '');
    $cont = getTplAdminTabs([
        'ops' => [
            $baseq,
            $baseq.'&status=1',
            $curq.'&op=config',
            $curq.'&op=info',
        ],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _DOCS],
        'tab' => $status,
        'subtitle_html' => $subtitle,
    ]);
    $drift = $com->getCountDrift();
    if ($drift) {
        $cont .= $tpl->getHtmlFrag('alert', [
            'is_warn' => true,
            'text' => sprintf(_COMMENTS_DRIFT, count($drift)).' '.getTplPostButton(['name' => 'comments', 'op' => 'recount'], 'arrow-repeat', _COMMENTS_SYNC),
        ]);
    }
    $anump = (int)($conf['comments']['anump'] ?? 8);
    $num = getVar('get', 'num', 'num', 1);
    $field = $curq.'&';
    $data = $com->getAdminList($status ? CommentStatus::Pending : CommentStatus::Published, $modul, $search, $chng, $num);
    if ($data['rows']) {
        $rows = '';
        foreach ($data['rows'] as $val) {
            $id = $val['id'];
            $cid = $val['cid'];
            $cmod = $val['modul'];
            $time = $val['time'];
            $ip = $val['ip'];
            $stat = $val['status'];
            $nick = $val['nick'];
            $modname = trim((string)getModuleName($cmod));
            $modlabel = ($modname !== '') ? $modname.' - '.$cmod : $cmod;
            $post = $nick ? filterTextHighlight(user_info($nick), $chng) : filterTextHighlight($val['name'] ?: _ANONYM, $chng);
            $comment = trim(strip_tags((string)$prs->filterContent($val['body'], true, $cmod, 0, $val['format'])));
            $comment = cutstr($comment, 120);
            $comment = filterTextHighlight($comment, $chng);
            $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
            $act = $stat ? 0 : 1;
            $items = [
                ['href' => 'index.php?name='.$cmod.'&op=view&id='.$cid.'#'.$id, 'icon_name' => 'eye', 'title' => _MVIEW],
                ['href' => $afile.'.php?'.$curq.'&op=edit&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT],
            ];
            $acttxt = $stat ? _DEACTIVATE : _ACTIVATE;
            parse_str($curq, $keep);
            $items[] = getTplPostAction($keep + ['op' => 'approve', 'id[]' => $id, 'typ' => $act], 'power', $acttxt);
            $items[] = getTplPostAction($keep + ['op' => 'delete', 'id[]' => $id], 'trash', _ONDELETE, _DELETE.' "'.$comment.'"?');
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
                    ['is_col_status' => true, 'content_html' => ad_status('', $stat)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $items])],
                    ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox',
                        ['name_attr' => 'id[]', 'value_attr' => (string)$id, 'is_check' => true, 'input_attr' => 'form="combulk"'])],
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
        $pager = getTplPagerView($data['page'], $data['pages'], $anump, static fn(int $i): array => ['href' => $afile.'.php?'.$field.'num='.$i], [
            'count' => $data['total'],
            'limit' => $data['limit'],
            'page' => $data['limit'],
        ]);
        $table = $tpl->getHtmlFrag('table', [
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
                ['is_col_check' => true, 'nosort' => true, 'content' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck',
                    'input_id' => 'markcheck', 'is_check' => true, 'input_attr' => 'title="'._CHECKALL.'" form="combulk"'])],
            ],
            'rows_html' => $rows,
        ]);
        $body = $table.$tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=comments&op=actions',
            'form_attr' => 'id="combulk"',
            'hidden' => array_filter([
                ['nameattr' => 'token', 'valueattr' => getSiteToken('comments')],
                ['nameattr' => 'status', 'valueattr' => (string)$status],
                $modul !== '' ? ['nameattr' => 'modul', 'valueattr' => $modul] : null,
                ['nameattr' => 'search', 'valueattr' => (string)$search],
                $chng !== '' ? ['nameattr' => 'chng', 'valueattr' => $chng] : null,
            ]),
            'actions_html' => $tpl->getHtmlFrag('module-foot', [
                'is_list' => true,
                'pager_html' => $pager,
                'actions_html' => $bulk,
            ]),
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function edit(): void {
    global $afile, $tpl, $com;
    $id = getVar('get', 'id', 'num');
    $status = getVar('get', 'status', 'num', 0);
    $modul = getVar('get', 'modul', 'var');
    $search = getVar('get', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('get', 'chng', 'raw', '');
    setHead();
    $baseq = 'name=comments';
    if ($modul !== '') $baseq .= '&modul='.rawurlencode($modul);
    $baseq .= '&search='.$search;
    if ($chng !== '') $baseq .= '&chng='.rawurlencode($chng);
    $curq = $baseq.($status ? '&status=1' : '');
    $cont = getTplAdminTabs([
        'ops' => [
            $baseq,
            $baseq.'&status=1',
            $curq.'&op=config',
            $curq.'&op=info',
        ],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _DOCS],
        'tab' => $status,
        'subtitle_html' => getCommentsSearch(),
    ]);
    $row = $com->getComment($id);
    $cmod = (string)($row['modul'] ?? '');
    $nick = (string)($row['nick'] ?? '');
    $ip = (string)($row['ip'] ?? '');
    $modname = trim((string)getModuleName($cmod));
    $modlabel = ($modname !== '') ? $modname.' - '.$cmod : $cmod;
    $post = $nick ? user_info($nick) : (($row['name'] ?? '') ?: _ANONYM);
    $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
    $rows = [
        ['label_html' => _MODUL, 'field_html' => htmlspecialchars($modlabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _ID, 'field_html' => (string)($row['cid'] ?? '')],
        ['label_html' => _POSTEDBY, 'field_html' => htmlspecialchars((string)$post, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _DATE, 'field_html' => format_time((string)($row['time'] ?? ''), _TIMESTRING)],
        ['label_html' => _IP, 'field_html' => htmlspecialchars((string)$iptext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _STATUS, 'field_html' => ad_status('', $row['status'] ?? null)],
        [
            'label_html' => _COMMENT,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'comment',
                'rows_num' => 12,
                'value_text' => (string)($row['body'] ?? ''),
            ]),
            'is_full' => true,
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?'.$curq.'&op=editsave',
        'hidden' => array_filter([
            ['nameattr' => 'id', 'valueattr' => (string)($row['id'] ?? '')],
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('comments')],
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
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $id = getVar('post', 'id', 'num');
    $text = trim((string)getVar('post', 'comment', 'raw', ''));
    $status = getVar('post', 'status', 'num', 0);
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    if (!$warn) $com->updateBody($id, $text);
    $back = 'name=comments';
    if ($status) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    setRedirect($afile.'.php?'.$back, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function actions(): void {
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $status = getVar('post', 'status', 'num', 0);
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    $typ = getVar('post', 'typ', 'text', '');
    $id = getVar('post', 'id[]', 'num');
    if (!$warn && is_array($id) && $typ !== '') {
        foreach ($id as $val) {
            if (!intval($val)) continue;
            if ($typ[0] === 'a') $com->setStatus(intval($val), (bool)(int)substr($typ, 1));
            elseif ($typ[0] === 'd') $com->deleteComment(intval($val));
        }
    }
    $tab = $status;
    if ($typ === 'a1') $tab = 0;
    if ($typ === 'a0') $tab = 1;
    $back = 'name=comments';
    if ($tab) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    $succ = ($typ[0] === 'd') ? _SUCCDELETE : _SUCCSTATUS;
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : $succ, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&status=1', 'name=comments&op=config', 'name=comments&op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _DOCS], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['comments']['num']])],
        ['label_html' => _COMMENTS_REPS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'reps', 'value_attr' => (string)($conf['comments']['reps'] ?? 5)])],
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
        'action_url' => $afile.'.php?name=comments&op=save',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('comments')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('comments');
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 15),
            'reps' => getVar('post', 'reps', 'num', 5),
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
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $typ = getVar('post', 'typ', 'num');
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    $id = getVar('post', 'id[]', 'num');
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) $com->setStatus(intval($val), (bool)$typ);
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
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $status = getVar('post', 'status', 'num', 0);
    $modul = getVar('post', 'modul', 'var');
    $search = getVar('post', 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = (string)getVar('post', 'chng', 'raw', '');
    $id = getVar('post', 'id[]', 'num');
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) $com->deleteComment(intval($val));
        }
    }
    $back = 'name=comments';
    if ($status) $back .= '&status=1';
    if ($modul !== '') $back .= '&modul='.rawurlencode($modul);
    $back .= '&search='.$search;
    if ($chng !== '') $back .= '&chng='.rawurlencode($chng);
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function recount(): void {
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $done = $warn ? 0 : $com->updateCountDrift($com->getCountDrift());
    setRedirect($afile.'.php?name=comments', true, 302, $warn ? _TOKENMISS : sprintf(_COMMENTS_FIXED, $done), $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=comments', 'name=comments&status=1', 'name=comments&op=config', 'name=comments&op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _DOCS],
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
    case 'recount': recount(); break;
    case 'info': info(); break;
}
