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
    foreach (['news', 'shop'] as $val) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => getModuleName($val),
            'is_selected' => $modul === $val,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'modul',
        'options_html' => $opts,
    ]);
}

function getVotingStatusSelect(int|string $status): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'status',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VCLOSED, 'is_selected' => (string)$status === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VDEACT, 'is_selected' => (string)$status === '0']),
    ]);
}

function getVotingTypeSelect(int|string $typ): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'typ',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VOPEN, 'is_selected' => (string)$typ === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VCLOSE, 'is_selected' => (string)$typ === '0']),
    ]);
}

function getVotingBlockSelect(string $bval): string {
    global $tpl;
    return $tpl->getHtmlFrag('select', [
        'name_attr' => 'block',
        'is_config' => true,
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _VLASTACT, 'is_selected' => $bval === '0'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _VLASTCLO, 'is_selected' => $bval === '1'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _VRANACT, 'is_selected' => $bval === '2'])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _VRANCLO, 'is_selected' => $bval === '3']),
    ]);
}

function voting(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _DOCS],
    ]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = (int)$conf['voting']['anum'];
    $anump = (int)$conf['voting']['anump'];
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, modul, time, enddate, title, lang, typ FROM '.PREFIX_DB.'_voting ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TITLE, 'is_col_title' => true],
        ];
        if ($conf['multilingual'] == 1) $head[] = ['content' => _LANGUAGE, 'class_name' => 'sl-col-lang'];
        $head[] = ['content' => _MODUL, 'class_name' => 'sl-col-module'];
        $head[] = ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true];
        $head[] = ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true];
        $rows = '';
        while ([$id, $modul, $date, $enddate, $title, $lang, $typ] = $db->getSqlRow($result)) {
            if (time() >= strtotime($date) && time() <= strtotime($enddate)) {
                $view = (!$modul) ? [['href' => 'index.php?name=voting&op=view&id='.$id, 'icon_name' => 'eye', 'title' => _MVIEW]] : [];
                $active = '1';
            } else {
                $view = [];
                $active = '0';
            }
            $type = ($typ == '1') ? _VOPEN : _VCLOSE;
            $items = array_merge($view, [
                ['href' => $afile.'.php?name=voting&op=add&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT],
                ['href' => $afile.'.php?name=voting&op=delete&id='.$id.'&refer=1&token='.getSiteToken(), 'icon_name' => 'trash', 'title' => _ONDELETE, 'confirm_text' => _DELETE.' "'.$title.'"?'],
            ]);
            $cells = [
                ['is_col_id' => true, 'content_html' => (string)$id],
                ['is_col_title' => true, 'is_truncate' => true, 'title_text' => (string)$title, 'prefix_html' => $tpl->getHtmlFrag('popover', ['items' => [
                    ['label' => _CHNGSTORY, 'value' => format_time($date, _TIMESTRING), 'is_last' => false],
                    ['label' => _ENDDATE, 'value' => format_time($enddate, _TIMESTRING), 'is_last' => false],
                    ['label' => _TYPE, 'value' => $type, 'is_last' => true],
                ]]), 'has_content_text' => true, 'content_text' => (string)$title],
            ];
            if ($conf['multilingual'] == 1) {
                $cells[] = ['class_name' => 'sl-col-lang', 'content_html' => getLangName((!$lang) ? _ALL : $lang)];
            }
            $cells[] = ['class_name' => 'sl-col-module', 'content_html' => $modul ? getModuleName($modul) : _NONE];
            $cells[] = ['is_col_status' => true, 'content_html' => ad_status('', $active)];
            $cells[] = ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $items])];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
        }
        $head[1]['is_truncate'] = true;
        $body = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => 'name=voting&', 'table' => '_voting', 'field' => 'id']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $stop = $stop ?? '';
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, modul, title, body, answer, time, enddate, multi, lang, acomm, typ, status FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
        [$id, $modul, $title, $body, $answer, $date, $enddate, $multi, $lang, $acomm, $typ, $status] = $db->getSqlRow($result);
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
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _DOCS],
        'tab' => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    if ($id) $cont .= $tpl->getHtmlPart('box', ['content_html' => getVotingView($id, 'voting', true)]);
    $rows = [
        ['label_html' => _TITLE.' / '._POLLTITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'is_required' => true, 'maxlength_num' => 255])],
        ['label_html' => _MODUL, 'field_html' => getVotingModuleSelect($modul)],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'date', 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => _ENDDATE, 'field_html' => getTplAddDateTime(['name' => 'enddate', 'time' => $enddate, 'with' => true, 'max' => 16])],
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions($lang)])];
    }
    $rows[] = ['label_html' => _COMMENTS, 'field_html' => getVotingCommentSelect((int)$acomm)];
    $rows[] = ['label_html' => _MULTI, 'field_html' => getTplRadioGroup(['name' => 'multi', 'value' => $multi, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])];
    $rows[] = ['label_html' => _TYPE, 'field_html' => getVotingTypeSelect($typ)];
    $rows[] = ['label_html' => _AFTEREXPIRATION, 'field_html' => getVotingStatusSelect($status)];
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
                    'label_html' => _POLLEACH,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'name_attr' => 'body[]',
                        'value_attr' => $qval,
                        'placeholder_text' => _POLLEACH.' - '.$a,
                    ]),
                ],
                [
                    'label_html' => _VOTES,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'number',
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'id', 'valueattr' => (string)$id],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $iswarn = !checkSiteToken();
    $id = getVar('post', 'id', 'num', 0);
    $modul = filterVar(getVar('post', 'modul', 'text', ''));
    $title = getVar('post', 'title', 'text', '');
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
    $acomm = ($modul) ? '0' : getVar('post', 'acomm', 'num', 0);
    $typ = getVar('post', 'typ', 'num', 0);
    $status = (!$typ) ? '0' : getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    $posttype = getVar('post', 'posttype', 'var', '');
    if ($iswarn) {
        setRedirect($afile.'.php?name=voting', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_voting SET modul = :modul, title = :title, body = :quest, answer = :answ, time = :time, enddate = :enddate, multi = :multi, lang = :lang, acomm = :acomm, typ = :typ, status = :status WHERE id = :id', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate, 'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'typ' => $typ, 'status' => $status, 'id' => $id]);
        } else {
            $ip = getIp();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_voting (id, modul, title, body, answer, time, enddate, multi, lang, acomm, ip, typ, status) VALUES (NULL, :modul, :title, :quest, :answ, :time, :enddate, :multi, :lang, :acomm, :ip, :typ, :status)', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate, 'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'ip' => $ip, 'typ' => $typ, 'status' => $status]);
        }
        setRedirect($afile.'.php?name=voting', false, 302, _SUCCSAVE, false);
    } elseif ($posttype == 'delete') {
        delete($id);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$id) $id = getVar('req', 'id', 'num', 0);
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'voting\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=voting', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=voting', 'name=voting&op=add', 'name=voting&op=config', 'name=voting&op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _DOCS],
        'tab' => 2,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/voting.php');
    $bval = (string)($conf['voting']['block'] ?? '0');
    $rows = [
        ['label_html' => _VOTING_TIME, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'time', 'value_attr' => (string)intval($conf['voting']['voting_t'] / 86400), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['voting']['num'], 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['voting']['anum'], 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['voting']['nump'], 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['voting']['anump'], 'is_config' => true])],
        ['label_html' => _VANSW, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'answ', 'value_attr' => (string)$conf['voting']['answ'], 'is_config' => true])],
        ['label_html' => _VBLOCK, 'field_html' => getVotingBlockSelect($bval)],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'voting'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
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
        'tabs' => [_HOME, _ADD, _PREFERENCES, _DOCS],
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
