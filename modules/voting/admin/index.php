<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('voting')) die('Illegal file access');

function getVotingCommentSelect(int $selected): string {
    global $tpl;
    $opts = '';
    foreach ([_DEACTIVATE, _APOSTMOD, _APOSTNOMOD] as $idx => $label) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$idx,
            'label_text' => $label,
            'is_selected' => $selected === $idx,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'acomm',
        'selectid' => 'f-acomm',
        'options_html' => $opts,
    ]);
}

function getVotingModuleSelect(string $modul = ''): string {
    global $tpl;
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _NO,
        'is_selected' => $modul === '',
    ]);
    $mods = [];
    foreach (getNodeTypeMap() as $key => $type) if ($type->settings['features']['poll']) $mods[] = $key;
    if ($modul !== '' && !in_array($modul, $mods, true)) $mods[] = $modul;
    foreach ($mods as $val) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => getModuleName($val),
            'is_selected' => $modul === $val,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'modul',
        'selectid' => 'f-modul',
        'options_html' => $opts,
    ]);
}

function getVotingStatusSelect(int|string $status): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'status',
        'selectid' => 'f-status',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VCLOSED, 'is_selected' => (string)$status === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VDEACT, 'is_selected' => (string)$status === '0']),
    ]);
}

function getVotingTypeSelect(int|string $typ): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'typ',
        'selectid' => 'f-typ',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VOPEN, 'is_selected' => (string)$typ === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VCLOSE, 'is_selected' => (string)$typ === '0']),
    ]);
}

function getVotingBlockSelect(string $bval): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'block',
        'selectid' => 'f-block',
        'is_config' => true,
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VLASTACT, 'is_selected' => $bval === '0'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VLASTCLO, 'is_selected' => $bval === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _VRANACT, 'is_selected' => $bval === '2'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _VRANCLO, 'is_selected' => $bval === '3']),
    ]);
}

# The filter of the polls as the request carries it: "-" is a poll of no module, the state is soon, live or done by the dates against SQL NOW(), the query keeps it
function getVotingFilter(string $src = 'get'): array {
    global $conf;
    $modul = getVar($src, 'modul', 'var');
    $status = getVar($src, 'status', 'var');
    if (!in_array($status, ['live', 'soon', 'done'], true)) $status = '';
    $lang = ($conf['multilingual'] == 1) ? getVar($src, 'lang', 'var') : '';
    $chng = trim(getVar($src, 'chng'));
    $cond = ($status !== '') ? [match ($status) {'live' => 'time <= NOW() AND enddate >= NOW()', 'soon' => 'time > NOW()', default => 'time <= NOW() AND enddate < NOW()'}] : [];
    $pars = [];
    if ($modul !== '') {
        $cond[] = 'modul = :modul';
        $pars['modul'] = ($modul === '-') ? '' : $modul;
    }
    if ($lang !== '') {
        $cond[] = '(lang = :lang OR lang = \'\')';
        $pars['lang'] = $lang;
    }
    if ($chng !== '') {
        $cond[] = 'title LIKE :find';
        $pars['find'] = '%'.$chng.'%';
    }
    $keys = array_filter(['modul' => $modul, 'status' => $status, 'lang' => $lang, 'chng' => $chng], static fn($v) => $v !== '');
    return ['modul' => $modul, 'status' => $status, 'lang' => $lang, 'chng' => $chng, 'where' => $cond ? ' WHERE '.implode(' AND ', $cond) : '', 'pars' => $pars,
        'base' => 'name=voting'.($keys ? '&'.http_build_query($keys, '', '&', PHP_QUERY_RFC3986) : '')];
}

# The filter bar of the polls: the modules and languages their rows name, the state and the title
function getVotingSearch(array $flt): string {
    global $afile, $db, $conf, $tpl;
    $opts = static function (array $list, string $cur): array {
        $out = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $cur === '']];
        foreach ($list as $key => $label) $out[] = ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $cur === (string)$key];
        return $out;
    };
    $mods = ['-' => _NONE];
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_voting WHERE modul != \'\' ORDER BY modul')) ?: [] as $row) {
        $mods[$row['modul']] = getModuleName((string)$row['modul']);
    }
    $fields = [
        ['field_id' => 'sl-vot-mod', 'label' => _MODUL, 'name_attr' => 'modul', 'options' => $opts($mods, $flt['modul']), 'is_now' => true],
        ['field_id' => 'sl-vot-stat', 'label' => _STATUS, 'name_attr' => 'status', 'options' => $opts(['live' => _VOTING_ACT, 'soon' => _VOTING_SOON, 'done' => _VOTING_END],
            $flt['status']), 'is_now' => true],
    ];
    if ($conf['multilingual'] == 1) {
        $langs = [];
        foreach ($db->getSqlRows($db->getSqlQuery('SELECT DISTINCT lang FROM '.PREFIX_DB.'_voting WHERE lang != \'\' ORDER BY lang')) ?: [] as $row) {
            $langs[$row['lang']] = getLangName((string)$row['lang']);
        }
        $fields[] = ['field_id' => 'sl-vot-lang', 'label' => _LANGUAGE, 'name_attr' => 'lang', 'options' => $opts($langs, $flt['lang']), 'is_now' => true];
    }
    $fields[] = ['field_id' => 'sl-vot-q', 'label' => _TITLE, 'name_attr' => 'chng', 'itype' => 'search', 'value' => $flt['chng']];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'voting']],
        'fields' => $fields,
    ]);
}

function voting(): void {
    global $db, $afile, $conf;
    setHead();
    $flt = getVotingFilter();
    $res = $db->getSqlQuery('SELECT COUNT(id), MAX(id) FROM '.PREFIX_DB.'_voting'.$flt['where'], $flt['pars']);
    [$total, $upto] = array_map('intval', $res->fetch(PDO::FETCH_NUM));
    $anum = getListLimit((int)$conf['voting']['anum'], $total);
    $pages = max(1, (int)ceil($total / $anum));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $sql = 'SELECT id, modul, time, enddate, title, lang, typ FROM '.PREFIX_DB.'_voting'.$flt['where'].' ORDER BY id DESC LIMIT '.(($num - 1) * $anum).', '.$anum;
    $result = $db->getSqlQuery($sql, $flt['pars']);
    parse_str($flt['base'].'&num='.$num, $keep);
    $multi = $conf['multilingual'] == 1;
    $head = [['content' => _ID, 'is_col_id' => true], ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true]];
    if ($multi) $head[] = ['content' => _LANGUAGE, 'class_name' => 'sl-col-lang'];
    $head[] = ['content' => _MODUL, 'class_name' => 'sl-col-module'];
    $head[] = ['content' => _ENDDATE, 'is_col_date' => true];
    $head[] = ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true];
    $head[] = ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true];
    $rows = [];
    while ([$id, $modul, $date, $enddate, $title, $lang, $typ] = $db->getSqlRow($result)) {
        $live = time() >= strtotime($date) && time() <= strtotime($enddate);
        $items = ($live && !$modul) ? [['href' => 'index.php?name=voting&op=view&id='.$id, 'icon_name' => 'eye', 'title' => _MVIEW]] : [];
        $items[] = ['href' => $afile.'.php?name=voting&op=add&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT, 'is_edit' => true];
        $cells = [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => (string)$title,
                'text_html' => filterTextHighlight(htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $flt['chng'])],
        ];
        if ($multi) $cells[] = ['class_name' => 'sl-col-lang', 'content_html' => getLangName((!$lang) ? _ALL : $lang)];
        $cells[] = ['class_name' => 'sl-col-module', 'content_html' => $modul ? getModuleName($modul) : _NONE];
        $cells[] = ['is_col_date' => true, 'moment' => $enddate];
        $cells[] = ['is_col_status' => true, 'content_html' => ad_status('', $live ? '1' : '0')];
        $cells[] = ['is_col_actions' => true, 'dial' => $items];
        $rows[] = ['name' => cutstr((string)$title, 60), 'details' => [
            ['label' => _CHNGSTORY, 'value' => format_time($date, _TIMESTRING)],
            ['label' => _TYPE, 'value' => ($typ == '1') ? _VOPEN : _VCLOSE],
        ], 'drop' => getTplPostAction($keep + ['op' => 'delete', 'id[]' => $id], 'trash', _ONDELETE), 'cells' => $cells];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
            'tabs' => [_HOME, _ADD, _PREFERENCES, _MANUAL], 'part' => [0], 'subtitle_html' => getVotingSearch($flt)]),
        'is_fixed' => true,
        'head' => $head,
        'rows' => $rows,
        'href' => $afile.'.php?'.$flt['base'].'&',
        'page' => $num,
        'pages' => $pages,
        'maxpg' => (int)$conf['voting']['anump'],
        'count' => $total,
        'limit' => $anum,
        'base' => (int)$conf['voting']['anum'],
        'upto' => $upto,
        'none_text' => ($flt['where'] !== '') ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $stop = $stop ?? '';
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, modul, title, body, answer, time, enddate, multi, lang, acomm, typ, status FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
        [$id, $modul, $title, $body, $answer, $date, $enddate, $multi, $lang, $acomm, $typ, $status] = $db->getSqlRow($result);
        $path = (new Route($db))->getRouteList('poll', [intval($id)])[intval($id)] ?? '';
        $pname = substr((string)strrchr('/'.$path, '/'), 1);
        $body = explode('|', $body);
        $answer = explode('|', $answer);
    } else {
        $modul = getVar('post', 'modul', 'text', '');
        $title = getVar('post', 'title', 'text', '');
        $body = getVar('post', 'body[]', '', []);
        $answer = getVar('post', 'answer[]', '', []);
        $date = getVar('req', 'date', 'time');
        $enddate = getVar('req', 'enddate', 'time');
        $multi = getVar('post', 'multi', 'num', 0);
        $lang = getVar('post', 'lang', 'text', '');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $typ = getVar('post', 'typ', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
        $path = '';
        $pname = filterCutText(trim(getVar('post', 'path', 'raw', '')), 255);
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _MANUAL],
        'tab' => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    if ($id) $cont .= $tpl->getHtmlPart('box', ['content_html' => getVotingView($id, 'voting', true)]);
    $rows = [
        [
            'label_for' => 'f-title', 'label_html' => _TITLE.' / '._POLLTITLE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title', 'value_attr' => $title, 'is_required' => true, 'maxlength_num' => 255,
            ]),
        ],
        [
            'label_for' => 'f-path', 'label_html' => _ADDRESS, 'hint_html' => ($path !== '') ? htmlspecialchars('/'.$path, ENT_QUOTES, 'UTF-8') : '',
            'hint_id' => ($path !== '') ? 'f-path-hint' : '',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'path', 'input_id' => 'f-path', 'value_attr' => $pname, 'maxlength_num' => 255]
                + (($path !== '') ? ['describedby' => 'f-path-hint'] : [])),
        ],
        ['label_for' => 'f-modul', 'label_html' => _MODUL, 'field_html' => getVotingModuleSelect($modul)],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'date', 'label' => _CHNGSTORY, 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => _ENDDATE, 'field_html' => getTplAddDateTime(['name' => 'enddate', 'label' => _ENDDATE, 'time' => $enddate, 'with' => true, 'max' => 16])],
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_for' => 'f-lang', 'label_html' => _LANGUAGE,
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'selectid' => 'f-lang', 'options_html' => getTplLanguageOptions($lang)]),
        ];
    }
    $rows[] = ['label_for' => 'f-acomm', 'label_html' => _COMMENTS, 'field_html' => getVotingCommentSelect((int)$acomm)];
    $rows[] = [
        'label_html' => _MULTI, 'label_id' => $labid = getFieldIds('', 'multi')['label'],
        'field_html' => getTplRadioGroup([
            'labelledby' => $labid, 'name' => 'multi', 'value' => $multi, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ]),
    ];
    $rows[] = ['label_for' => 'f-typ', 'label_html' => _TYPE, 'field_html' => getVotingTypeSelect($typ)];
    $rows[] = ['label_for' => 'f-status', 'label_html' => _AFTEREXPIRATION, 'field_html' => getVotingStatusSelect($status)];
    $answ = '';
    for ($i = 0; $i < $conf['voting']['answ']; $i++) {
        $a = $i + 1;
        $qval = filterText((string)($body[$i] ?? ''));
        $aval = filterText((string)($answer[$i] ?? '0'));
        $answ .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'voting-answer-'.$i,
            'is_toggle_block' => true,
            'is_hidden' => $qval === '' && $i !== 0,
            'toggle_target_id' => 'voting-answer-'.$a,
            'title' => _ADD,
            'label_html' => _POLLEACH.' - '.$a,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => [
                [
                    'label_for' => 'f-body-'.$i,
                    'label_html' => _POLLEACH,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'input_id' => 'f-body-'.$i,
                        'name_attr' => 'body[]',
                        'value_attr' => $qval,
                        'placeholder_text' => _POLLEACH.' - '.$a,
                    ]),
                ],
                [
                    'label_for' => 'f-answer-'.$i,
                    'label_html' => _VOTES,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'number',
                        'input_id' => 'f-answer-'.$i,
                        'name_attr' => 'answer[]',
                        'value_attr' => $aval,
                        'placeholder_text' => _VOTES,
                    ]),
                ],
            ]]),
        ]);
    }
    $rows[] = ['label_html' => _ADD, 'field_html' => $answ, 'is_full' => true];
    $posttypeopts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES])
        .($id ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'voting'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('voting')],
            ['nameattr' => 'id', 'valueattr' => (string)$id],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'aria_label' => _FUNCTIONS, 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop, $conf;
    $iswarn = !checkAdminPost('voting');
    $id = getVar('post', 'id', 'num', 0);
    $modul = filterVar(getVar('post', 'modul', 'text', ''));
    if (!isset(getNodeTypeMap()[$modul]) && !isset($conf['modules'][$modul])) $modul = '';
    $title = filterCutText(getVar('post', 'title', 'text', ''), 255);
    $path = filterCutText(trim(getVar('post', 'path', 'raw', '')), 255);
    $body = getVar('post', 'body[]', '', []);
    $answer = getVar('post', 'answer[]', '', []);
    $quest = [];
    $answ = [];
    for ($q = 0; $q < count($body); $q++) {
        if (($body[$q] ?? '') != '') {
            $quest[] = $body[$q];
            $answ[] = (is_numeric($answer[$q] ?? '')) ? (string)$answer[$q] : '0';
        }
    }
    $quest = is_array($quest) ? implode('|', $quest) : '';
    $answ = is_array($answ) ? implode('|', $answ) : '';
    $date = getVar('req', 'date', 'time');
    $enddate = getVar('req', 'enddate', 'time');
    $multi = getVar('post', 'multi', 'num', 0);
    $lang = getVar('post', 'lang', 'text', '');
    if (!isset(getLanguageList()[$lang])) $lang = '';
    $acomm = ($modul) ? '0' : getVar('post', 'acomm', 'num', 0);
    $typ = getVar('post', 'typ', 'num', 0);
    $status = (!$typ) ? '0' : getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    $posttype = getVar('post', 'posttype', 'var', '');
    if ($iswarn) {
        setRedirect($afile.'.php?name=voting', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
        $open = $db->setSqlBegin();
        if ($id) {
            $done = $open && $db->getSqlQuery(
                'UPDATE '.PREFIX_DB.'_voting SET modul = :modul, title = :title, body = :quest, answer = :answ, time = :time,'
                .' enddate = :enddate, multi = :multi, lang = :lang, acomm = :acomm, typ = :typ, status = :status WHERE id = :id',
                [
                    'modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate,
                    'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'typ' => $typ, 'status' => $status, 'id' => $id,
                ]
            ) !== false;
        } else {
            $ip = getIp();
            $done = $open && $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_voting (id, modul, title, body, answer, time, enddate, multi, lang, acomm, ip, typ, status)'
                .' VALUES (NULL, :modul, :title, :quest, :answ, :time, :enddate, :multi, :lang, :acomm, :ip, :typ, :status)',
                [
                    'modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate,
                    'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'ip' => $ip, 'typ' => $typ, 'status' => $status,
                ]
            ) !== false;
            $id = $done ? intval($db->getSqlLastId()) : 0;
        }
        $done = $done && ($modul !== '' || (new Route($db))->setRouteName('poll', intval($id), 'voting', $path, $title) !== '') && $db->setSqlCommit();
        if ($open && !$done) $db->setSqlRollback();
        setRedirect($afile.'.php?name=voting', false, 302, $done ? _SUCCSAVE : _ERROR, !$done);
    } elseif ($posttype == 'delete') {
        delete([$id]);
    } else {
        add();
    }
}

function delete(array $ids = []): void {
    global $db, $afile, $com, $conf;
    $iswarn = !checkAdminPost('voting');
    $act = static function (int $id) use ($db, $com, $conf): bool {
        if (empty($conf['node']['types'])) {
            $open = $db->setSqlBegin();
            $gone = $open && $com->deleteTarget('voting', [$id]) ? $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]) : false;
            $done = $gone && $gone->rowCount() > 0
                && (new Route($db))->deleteRoute('poll', $id) && $db->setSqlCommit();
            if ($open && !$done) $db->setSqlRollback();
            return $done;
        }
        $lock = 'node.poll.'.$id;
        $held = $db->getSqlQuery('SELECT GET_LOCK(:name, 5)', ['name' => $lock]);
        $miss = !$held || intval($held->fetchColumn()) !== 1;
        $step = 'before';
        $done = true;
        try {
            $serv = getNodeWriter();
            if ($miss || !$db->setSqlBegin()) throw new NodeException('The deletion of a poll cannot start', NodeException::STORAGE);
            $step = 'open';
            $serv->deleteNodePoll($id);
            if (!$com->deleteTarget('voting', [$id])) throw new NodeException('The comments of the poll were not deleted', NodeException::STORAGE);
            $gone = $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
            if (!$gone || $gone->rowCount() < 1) throw new NodeException('The poll row was not deleted', NodeException::STORAGE);
            if (!(new Route($db))->deleteRoute('poll', $id)) throw new NodeException('The paths of the poll were not marked gone', NodeException::STORAGE);
            $step = 'unknown';
            if (!$db->setSqlCommit()) throw new NodeException('The commit of a poll deletion is uncertain', NodeException::STORAGE);
        } catch (Throwable $err) {
            if ($step === 'open') $db->setSqlRollback();
            $done = false;
            Logger::addSite('error', 'Voting: a poll could not be deleted', ['id' => $id, 'error' => $err->getMessage()]);
        }
        if ($held) $db->getSqlQuery('SELECT RELEASE_LOCK(:name)', ['name' => $lock]);
        return $done;
    };
    $flt = getVotingFilter('post');
    if (!$iswarn && setListPortion('id', PREFIX_DB.'_voting', $flt['where'], $flt['pars'], $act)) exit;
    $ids = $iswarn ? [] : array_map('intval', $ids ?: getVar('post', 'id[]', 'num'));
    $fail = !$iswarn && !$ids;
    foreach ($ids as $id) {
        if (!$act($id)) $fail = true;
    }
    setRedirect($afile.'.php?'.$flt['base'].'&num='.getVar('post', 'num', 'num', 1), false, 302, $iswarn ? _TOKENMISS : ($fail ? _ERROR : _SUCCDELETE), $iswarn || $fail);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _MANUAL],
        'tab' => 2,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/voting.php');
    $bval = (string)($conf['voting']['block'] ?? '0');
    $rows = [
        [
            'label_for' => 'f-time', 'label_html' => _VOTING_TIME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'time', 'input_id' => 'f-time', 'value_attr' => (string)intval($conf['voting']['voting_t'] / 86400), 'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-num', 'label_html' => _C_33,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'num', 'input_id' => 'f-num', 'value_attr' => (string)$conf['voting']['num'], 'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anum', 'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'anum', 'input_id' => 'f-anum', 'value_attr' => (string)$conf['voting']['anum'], 'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-nump', 'label_html' => _C_35,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'nump', 'input_id' => 'f-nump', 'value_attr' => (string)$conf['voting']['nump'], 'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anump', 'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'anump', 'input_id' => 'f-anump', 'value_attr' => (string)$conf['voting']['anump'], 'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-answ', 'label_html' => _VANSW,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'answ', 'input_id' => 'f-answ', 'value_attr' => (string)$conf['voting']['answ'], 'is_config' => true,
            ]),
        ],
        ['label_for' => 'f-block', 'label_html' => _VBLOCK, 'field_html' => getVotingBlockSelect($bval)],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'voting'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('voting')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkAdminPost('voting');
    if (!$iswarn) {
        $cont = [
            'voting_t' => getVar('post', 'time', 'num', 1) * 86400,
            'num' => getVar('post', 'num', 'num', 10),
            'anum' => getVar('post', 'anum', 'num', 10),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'answ' => getVar('post', 'answ', 'num', 10),
            'block' => getVar('post', 'block', 'num', 0),
        ];
        setConfigFile('voting.php', $cont);
    }
    setRedirect($afile.'.php?name=voting&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: voting(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
