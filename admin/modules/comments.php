<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The filter of the moderation list as the request carries it, the modules it is checked against and the addresses that keep it
function getCommentsFilter(string $src = 'get'): array {
    global $com;
    $mods = $com->getModuleList();
    $modul = strtolower(getVar($src, 'modul', 'var'));
    if (!in_array($modul, $mods, true)) $modul = '';
    $search = getVar($src, 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = trim(getVar($src, 'chng'));
    $status = getVar($src, 'status', 'num') ? 1 : 0;
    $num = max(1, intval(getVar($src, 'num', 'num', 1)));
    $base = 'name=comments'.(($modul !== '') ? '&modul='.rawurlencode($modul) : '').'&search='.$search.(($chng !== '') ? '&chng='.rawurlencode($chng) : '');
    $cur = $base.($status ? '&status=1' : '');
    return ['mods' => $mods, 'modul' => $modul, 'search' => $search, 'chng' => $chng, 'status' => $status, 'num' => $num, 'base' => $base, 'cur' => $cur,
        'back' => $cur.(($num > 1) ? '&num='.$num : '')];
}

function getCommentsSearch(array $flt, bool $part): string {
    global $afile, $tpl;
    $mods = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $flt['modul'] === '']];
    foreach ($flt['mods'] as $one) $mods[] = ['value_attr' => $one, 'label_text' => getModuleName($one).' - '.$one, 'is_selected' => $flt['modul'] === $one];
    $finds = [];
    foreach ([1 => _ID, 2 => _COMMENT, 3 => _POSTEDBY, 4 => _MODUL, 5 => _IP] as $key => $label) {
        $finds[] = ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $flt['search'] === $key];
    }
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => $part,
        'hidden' => array_values(array_filter([
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            $flt['status'] ? ['nameattr' => 'status', 'valueattr' => '1'] : null,
        ])),
        'fields' => [
            ['field_id' => 'sl-com-mod', 'label' => _MODUL, 'name_attr' => 'modul', 'options' => $mods, 'is_now' => true],
            ['field_id' => 'sl-com-in', 'label' => _SEARCH, 'name_attr' => 'search', 'options' => $finds,
                'find_id' => 'sl-com-q', 'find_name' => 'chng', 'find_value' => $flt['chng']],
        ],
    ]);
}

# The moderation overview: the drifted counters, the bulk form and the comment table, redrawn in place by the list canon
# Every write recomputes the counter of its own target, so a drift left here belongs to a target nobody has commented on since; it is shown rather than repaired, as a symptom
# The table sits outside the bulk form, since the row actions are POST forms of their own and a browser drops a nested form; the checkboxes stay bound through the form attribute
function comments(): void {
    global $conf, $afile, $tpl, $prs, $com;
    setHead();
    $flt = getCommentsFilter();
    $status = $flt['status'];
    $chng = $flt['chng'];
    $head = getTplAdminTabs([
        'ops' => [$flt['base'], $flt['base'].'&status=1', $flt['cur'].'&op=config', $flt['cur'].'&op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _MANUAL],
        'tab' => $status,
        'part' => [0, 1],
        'subtitle_html' => getCommentsSearch($flt, true),
    ]);
    $drift = $com->getCountDrift();
    $note = $drift ? $tpl->getHtmlFrag('alert', [
        'is_warn' => true,
        'text' => sprintf(_COMMENTS_DRIFT, count($drift)),
        'acts_html' => getTplPostButton(['name' => 'comments', 'op' => 'recount'], 'arrow-repeat', _COMMENTS_SYNC, '', 'act'),
    ]) : '';
    $data = $com->getAdminList($status ? CommentStatus::Pending : CommentStatus::Published, $flt['modul'], $flt['search'], $chng, $flt['num']);
    $back = $flt['cur'].(($data['page'] > 1) ? '&num='.$data['page'] : '');
    parse_str($back, $keep);
    $rows = [];
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
        $post = $nick ? filterTextHighlight(user_info($nick, null), $chng) : filterTextHighlight($val['name'] ?: _ANONYM, $chng);
        $comment = trim(strip_tags((string)$prs->filterContent($val['body'], true, $cmod, 0, 'breaks')));
        $comment = cutstr($comment, 120);
        $comment = filterTextHighlight($comment, $chng);
        $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
        $act = $stat ? 0 : 1;
        $who = $nick ?: (string)$val['name'];
        $page = 'index.php?name='.$cmod.'&op=view&id='.$cid.'&at='.$id;
        $find = $afile.'.php?name=comments'.($status ? '&status=1' : '');
        $items = [
            ['href' => $page.'#'.$id, 'icon_name' => 'eye', 'title' => _MVIEW],
            ['href' => $page.'#reply-'.$id, 'icon_name' => 'reply', 'title' => _COMMENTS_ONSITE],
            ['href' => $afile.'.php?'.$back.'&op=edit&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT, 'link_attr' => 'data-sl-edit'],
        ];
        if ($who !== '') {
            $items[] = ['href' => $find.'&search=3&chng='.rawurlencode($who), 'icon_name' => 'person-lines-fill', 'title' => _COMMENTS_AUTHOR, 'link_attr' => 'hx-boost="true"'];
        }
        if ($ip) $items[] = ['href' => $find.'&search=5&chng='.rawurlencode($ip), 'icon_name' => 'hdd-network', 'title' => _COMMENTS_ADDR, 'link_attr' => 'hx-boost="true"'];
        $items[] = getTplPostAction($keep + ['op' => 'delete', 'id[]' => $id], 'trash', _ONDELETE) + ['later' => 'gone del'];
        $rows[] = ['name' => _COMMENT.' '.$id, 'cells' => [
            ['is_col_id' => true, 'content_html' => filterTextHighlight((string)$id, $chng)],
            ['is_truncate' => true, 'title_text' => strip_tags($comment), 'prefix_html' => $tpl->getHtmlFrag('popover', [
                'items' => [
                    ['label' => _MODUL, 'value' => $modlabel],
                    ['label' => 'CID', 'value' => (string)$cid],
                    ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                    ['label' => _IP, 'value' => $iptext, 'is_last' => true],
                ],
                'tip_label' => _DETAILS.': '._COMMENT.' '.$id,
                'tip_id' => 'sl-tip-'.$id,
            ]), 'text_html' => $comment],
            ['class_name' => 'sl-col-module', 'is_truncate' => true, 'title_text' => $modlabel, 'content_html' => filterTextHighlight($modlabel, $chng)],
            ['is_col_author' => true, 'content_html' => $post],
            ['is_col_date' => true, 'stamp' => format_time($time, 'c'), 'content_text' => format_time($time, _TIMESTRING)],
            ['is_col_status' => true, 'stat' => getTplPostAction($keep + ['op' => 'approve', 'id[]' => $id, 'typ' => $act], 'power', $stat ? _DEACTIVATE : _ACTIVATE)
                + ['is_on' => (bool)$stat, 'label' => _STATUS.': '._COMMENT.' '.$id, 'later' => 'gone '.($act ? 'on' : 'off')]],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $items])],
            ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox',
                ['name_attr' => 'id[]', 'value_attr' => (string)$id, 'is_check' => true, 'aria_label' => sprintf(_COMMENTS_PICK, $id), 'input_attr' => 'form="combulk"'])],
        ]];
    }
    $keep['token'] = getSiteToken('comments');
    echo getTplAdminList([
        'head_html' => $head,
        'note_html' => $note,
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
                'input_id' => 'markcheck', 'is_check' => true, 'aria_label' => _CHECKALL, 'input_attr' => 'title="'._CHECKALL.'" form="combulk"'])],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?'.$flt['cur'].'&',
        'page' => $data['page'],
        'pages' => $data['pages'],
        'maxpg' => (int)($conf['comments']['anump'] ?? 8),
        'count' => $data['total'],
        'limit' => $data['limit'],
        'base' => $data['base'],
        'bulk' => [
            'form_id' => 'combulk',
            'action_url' => $afile.'.php?name=comments&op=actions',
            'hidden' => array_map(static fn($k, $v): array => ['nameattr' => $k, 'valueattr' => (string)$v], array_keys($keep), $keep),
            'options' => [
                ['value_attr' => 'a'.($status ? '1' : '0'), 'label_text' => $status ? _ACTIVATE : _DEACTIVATE],
                ['value_attr' => 'd', 'label_text' => _DELETE],
            ],
            'later' => 'gone d=del a1=on a0=off',
            'sweep' => 'sweep',
            'upto' => $data['upto'],
        ],
        'none_text' => ($flt['modul'] !== '' || $chng !== '') ? _NONE_FOUND : ($status ? _ADM_CLEAN : _NO_INFO),
        'is_calm' => $status && $flt['modul'] === '' && $chng === '',
    ]);
    setFoot();
}

function edit(): void {
    global $afile, $tpl, $com;
    $id = getVar('get', 'id', 'num');
    $flt = getCommentsFilter();
    setHead();
    $cont = getTplAdminTabs([
        'ops' => [$flt['base'], $flt['base'].'&status=1', $flt['cur'].'&op=config', $flt['cur'].'&op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _MANUAL],
        'tab' => $flt['status'],
        'subtitle_html' => getCommentsSearch($flt, false),
    ]);
    $row = $com->getComment($id);
    $cmod = (string)($row['modul'] ?? '');
    $nick = (string)($row['nick'] ?? '');
    $ip = (string)($row['ip'] ?? '');
    $modname = trim((string)getModuleName($cmod));
    $modlabel = ($modname !== '') ? $modname.' - '.$cmod : $cmod;
    $post = $nick ? user_info($nick, null) : (($row['name'] ?? '') ?: _ANONYM);
    $iptext = $ip ? Geoip::getIpHtml($ip) : _NO;
    $rows = [
        ['label_html' => _MODUL, 'field_html' => htmlspecialchars($modlabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _ID, 'field_html' => (string)($row['cid'] ?? '')],
        ['label_html' => _POSTEDBY, 'field_html' => htmlspecialchars((string)$post, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _DATE, 'field_html' => format_time((string)($row['time'] ?? ''), _TIMESTRING)],
        ['label_html' => _COMMENTS_EDITED, 'field_html' => ((string)($row['edited'] ?? '') !== '') ? format_time((string)$row['edited'], _TIMESTRING) : (string)_NO],
        ['label_html' => _IP, 'field_html' => htmlspecialchars((string)$iptext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
        ['label_html' => _STATUS, 'field_html' => ad_status('', $row['status'] ?? null)],
        [
            'label_for' => 'f-comment',
            'label_html' => _COMMENT,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'comment',
                'input_id' => 'f-comment',
                'rows_num' => 12,
                'value_text' => (string)($row['body'] ?? ''),
            ]),
            'is_full' => true,
        ],
    ];
    parse_str($flt['back'], $keep);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?'.$flt['back'].'&op=editsave',
        'hidden' => array_merge([
            ['nameattr' => 'id', 'valueattr' => (string)($row['id'] ?? '')],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('comments')],
        ], array_map(static fn($k, $v): array => ['nameattr' => $k, 'valueattr' => (string)$v], array_keys($keep), $keep)),
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
    $text = trim(getVar('post', 'comment', 'raw', ''));
    $flt = getCommentsFilter('post');
    $room = $warn ? '' : $com->updateBody($id, $text);
    setRedirect($afile.'.php?'.$flt['back'], false, 302, $warn ? _TOKENMISS : ($room ?: _SUCCSAVE), $warn || $room !== '');
}

function actions(): void {
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $flt = getCommentsFilter('post');
    $typ = getVar('post', 'typ', 'text', '');
    $id = getVar('post', 'id[]', 'num');
    $fail = false;
    if (!$warn && is_array($id) && $typ !== '') {
        foreach ($id as $val) {
            if (!intval($val)) continue;
            if ($typ[0] === 'a' && !$com->setStatus(intval($val), (bool)(int)substr($typ, 1))) $fail = true;
            elseif ($typ[0] === 'd' && !$com->deleteComment(intval($val))) $fail = true;
        }
    }
    $tab = $flt['status'];
    if ($typ === 'a1') $tab = 0;
    if ($typ === 'a0') $tab = 1;
    $back = ($tab === $flt['status']) ? $flt['back'] : $flt['base'].($tab ? '&status=1' : '');
    $succ = ($typ !== '' && $typ[0] === 'd') ? _SUCCDELETE : _SUCCSTATUS;
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : ($fail ? _ERROR : $succ), $warn || $fail);
}

function sweep(): void {
    global $com;
    $flt = getCommentsFilter('post');
    $typ = getVar('post', 'typ', 'var', '');
    if (!checkAdminPost('comments') || !in_array($typ, ['a0', 'a1', 'd'], true)) {
        http_response_code(409);
        exit;
    }
    $stat = $flt['status'] ? CommentStatus::Pending : CommentStatus::Published;
    $out = $com->setAdminPortion($stat, $flt['modul'], $flt['search'], $flt['chng'], $typ, getVar('post', 'after', 'num', 0), getVar('post', 'upto', 'num', 0), 100);
    setAdminLog(['result' => $out['fail'] ? 'refused' : 'done']);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($out);
    exit;
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=comments', 'name=comments&status=1', 'name=comments&op=config', 'name=comments&op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _MANUAL],
        'tab' => 2,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $rows = [
        [
            'label_for' => 'f-num',
            'label_html' => _C_33,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'input_id' => 'f-num', 'value_attr' => (string)$conf['comments']['num']]),
        ],
        [
            'label_for' => 'f-reps',
            'label_html' => _COMMENTS_REPS,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'reps',
                'input_id' => 'f-reps',
                'value_attr' => (string)($conf['comments']['reps'] ?? 5),
            ]),
        ],
        [
            'label_for' => 'f-anum',
            'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'input_id' => 'f-anum', 'value_attr' => (string)$conf['comments']['anum']]),
        ],
        [
            'label_for' => 'f-nump',
            'label_html' => _C_35,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'input_id' => 'f-nump', 'value_attr' => (string)$conf['comments']['nump']]),
        ],
        [
            'label_for' => 'f-anump',
            'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'input_id' => 'f-anump', 'value_attr' => (string)$conf['comments']['anump']]),
        ],
        [
            'label_for' => 'f-letter',
            'label_html' => _COMLETTER,
            'hint_html' => _CONFINES,
            'hint_id' => $hntid = getFieldIds('f-letter')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => 'letter',
                'input_id' => 'f-letter',
                'value_attr' => (string)$conf['comments']['letter'],
            ]),
        ],
        [
            'label_for' => 'f-edit',
            'label_html' => _CEDITT,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'edit',
                'input_id' => 'f-edit',
                'value_attr' => (string)intval($conf['comments']['edit'] / 60),
            ]),
        ],
        [
            'label_for' => 'f-send',
            'label_html' => _CSEND,
            'hint_html' => _CONFINES,
            'hint_id' => $hntid = getFieldIds('f-send')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => 'send',
                'input_id' => 'f-send',
                'value_attr' => (string)$conf['comments']['send'],
            ]),
        ],
        [
            'label_for' => 'f-sort',
            'label_html' => _SORT,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'sort',
                'selectid' => 'f-sort',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '1',
                    'label_text' => _ASC,
                    'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '1',
                ]).$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DESC, 'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '0']),
            ]),
        ],
        ['label_for' => 'f-anonpost', 'label_html' => _ALLOWANONPOST, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'anonpost',
            'selectid' => 'f-anonpost',
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
        [
            'label_for' => 'f-link',
            'label_html' => _NOLINKP,
            'hint_html' => _NOAUM,
            'hint_id' => $hntid = getFieldIds('f-link')['hint'],
            'field_html' => $tpl->getHtmlFrag('select', [
                'describedby' => $hntid,
                'name_attr' => 'link',
                'selectid' => 'f-link',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '0',
                    'label_text' => _NO,
                    'is_selected' => (string)($conf['comments']['link'] ?? '0') === '0',
                ]).$tpl->getHtmlFrag('select-option', [
                    'value_attr' => '1',
                    'label_text' => _ANONIMP,
                    'is_selected' => (string)($conf['comments']['link'] ?? '0') === '1',
                ]).$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '2']),
            ]),
        ],
        [
            'label_for' => 'f-alink',
            'label_html' => _NOALINKP,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'alink',
                'selectid' => 'f-alink',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '0',
                    'label_text' => _NO,
                    'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '0',
                ]).$tpl->getHtmlFrag('select-option', [
                    'value_attr' => '1',
                    'label_text' => _ANONIMP,
                    'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '1',
                ]).$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '2']),
            ]),
        ],
        [
            'label_html' => _ADDAMAIL,
            'label_id' => $labid = getFieldIds('', 'addmail')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'addmail',
                'value' => (string)(int)$conf['comments']['addmail'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _VPRIVAT,
            'label_id' => $labid = getFieldIds('', 'privat')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'privat',
                'value' => (string)(int)$conf['comments']['privat'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _VPROFIL,
            'label_id' => $labid = getFieldIds('', 'profil')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'profil',
                'value' => (string)(int)$conf['comments']['profil'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _VWEB,
            'label_id' => $labid = getFieldIds('', 'web')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'web',
                'value' => (string)(int)$conf['comments']['web'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
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
    $flt = getCommentsFilter('post');
    $id = getVar('post', 'id[]', 'num');
    $fail = false;
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val) && !$com->setStatus(intval($val), (bool)$typ)) $fail = true;
        }
    }
    $tab = $typ ? 0 : 1;
    $back = ($tab === $flt['status']) ? $flt['back'] : $flt['base'].($tab ? '&status=1' : '');
    setRedirect($afile.'.php?'.$back, true, 302, $warn ? _TOKENMISS : ($fail ? _ERROR : _SUCCSTATUS), $warn || $fail);
}

function delete(): void {
    global $afile, $com;
    $warn = !checkAdminPost('comments');
    $flt = getCommentsFilter('post');
    $id = getVar('post', 'id[]', 'num');
    $fail = false;
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val) && !$com->deleteComment(intval($val))) $fail = true;
        }
    }
    setRedirect($afile.'.php?'.$flt['back'], true, 302, $warn ? _TOKENMISS : ($fail ? _ERROR : _SUCCDELETE), $warn || $fail);
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
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: comments(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'actions': actions(); break;
    case 'sweep': sweep(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'approve': approve(); break;
    case 'delete': delete(); break;
    case 'recount': recount(); break;
    case 'info': info(); break;
}
