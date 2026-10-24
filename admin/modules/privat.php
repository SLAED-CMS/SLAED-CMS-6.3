<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The filter of the message list as the request carries it, the state among the five getRowState() names, and the query that keeps it
function getPrivatFilter(string $src = 'get'): array {
    $status = getVar($src, 'status', 'var');
    if (!in_array($status, ['new', 'read', 'saved', 'delin', 'delout'], true)) $status = '';
    $search = getVar($src, 'search', 'num', 2);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $chng = trim(getVar($src, 'chng'));
    $keys = array_filter(['status' => $status, 'search' => $search, 'chng' => $chng], static fn($v) => $v !== '');
    return ['status' => $status, 'search' => $search, 'chng' => $chng, 'base' => 'name=privat&'.http_build_query($keys, '', '&', PHP_QUERY_RFC3986)];
}

# The filter bar of the message list: the state, then the column and the text of the search
function getPrivatSearch(array $flt): string {
    global $afile, $tpl;
    $stats = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $flt['status'] === '']];
    foreach (['new' => _PROUTNEW, 'read' => _PROLD, 'saved' => _PRMOVE, 'delin' => _PRIVAT_DELIN, 'delout' => _PRIVAT_DELOUT] as $key => $label) {
        $stats[] = ['value_attr' => $key, 'label_text' => $label, 'is_selected' => $flt['status'] === $key];
    }
    $finds = [];
    foreach ([1 => _ID, 2 => _TITLE, 3 => _PRSE, 4 => _PRRE, 5 => _IP] as $key => $label) {
        $finds[] = ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $flt['search'] === $key];
    }
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'privat']],
        'fields' => [
            ['field_id' => 'sl-prv-stat', 'label' => _STATUS, 'name_attr' => 'status', 'options' => $stats, 'is_now' => true],
            ['field_id' => 'sl-prv-in', 'label' => _SEARCH, 'name_attr' => 'search', 'options' => $finds, 'find_id' => 'sl-prv-q', 'find_name' => 'chng',
                'find_value' => $flt['chng']],
        ],
    ]);
}

# Every private message newest first with the state its columns add up to, deleted copies too, the body rendered as its recipient reads it
function privat(): void {
    global $afile, $conf, $tpl, $prs, $prv;
    setHead();
    $flt = getPrivatFilter();
    $chng = $flt['chng'];
    $find = $flt['search'];
    $data = $prv->getAdminList($flt['status'], $find, $chng, getVar('get', 'num', 'num', 1));
    parse_str($flt['base'].'&num='.$data['page'], $keep);
    $mark = static fn(string $html, int $col): string => ($col === $find) ? filterTextHighlight($html, $chng) : $html;
    $rows = [];
    foreach ($data['rows'] as $one) {
        $id = $one['id'];
        $title = $one['title'];
        $tone = match ($one['state']) {'delin', 'delout' => 'danger', 'saved' => 'accent', 'read' => 'success', default => 'warn'};
        $note = match ($one['state']) {
            'delin' => (string)_PRIVAT_DELIN,
            'delout' => (string)_PRIVAT_DELOUT,
            'saved' => (string)_PRMOVE,
            'read' => (string)_PROLD,
            default => (string)_PROUTNEW,
        };
        $tip = [['value' => $prs->filterContent($one['body'], true, 'account', 0, 'breaks', ['privat', $id])]];
        if ($one['ip'] !== '') $tip[] = ['label' => _IP, 'value' => $mark(Geoip::getIpHtml($one['ip']), 5)];
        $rows[] = ['name' => ($title !== '') ? cutstr($title, 60) : _ID.' '.$id, 'details' => $tip,
            'drop' => getTplPostAction($keep + ['op' => 'delete', 'id[]' => $id], 'trash', _ONDELETE), 'cells' => [
            ['is_col_id' => true, 'content_html' => $mark((string)$id, 1)],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $title, 'text_html' => $mark(htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 2)],
            ['is_col_author' => true, 'content_html' => ($one['nameout'] !== '') ? $mark(user_info($one['nameout'], null), 3) : _ANONYM],
            ['is_col_author' => true, 'content_html' => ($one['namein'] !== '') ? $mark(user_info($one['namein'], null), 4) : _ANONYM],
            ['is_col_date' => true, 'moment' => $one['time']],
            ['is_col_status' => true, 'content_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $tone, 'label' => $note, 'title_text' => $note])],
            ['is_col_actions' => true, 'dial' => []],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=privat', 'name=privat&op=config', 'name=privat&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL], 'part' => [0],
            'subtitle_html' => getPrivatSearch($flt)]),
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true],
            ['content' => _PRSE, 'is_col_author' => true],
            ['content' => _PRRE, 'is_col_author' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?'.$flt['base'].'&',
        'page' => $data['page'],
        'pages' => $data['pages'],
        'maxpg' => intval($conf['privat']['anump']),
        'count' => $data['total'],
        'limit' => $data['limit'],
        'base' => $data['base'],
        'upto' => $data['upto'],
        'none_text' => ($flt['status'] !== '' || $chng !== '') ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=privat', 'name=privat&op=config', 'name=privat&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/privat.php');
    $rows = [
        [
            'label_for' => 'f-num',
            'label_html' => _C_33,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'num',
                'input_id' => 'f-num',
                'value_attr' => (string)$conf['privat']['num'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anum',
            'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anum',
                'input_id' => 'f-anum',
                'value_attr' => (string)$conf['privat']['anum'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-nump',
            'label_html' => _C_35,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'nump',
                'input_id' => 'f-nump',
                'value_attr' => (string)$conf['privat']['nump'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anump',
            'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anump',
                'input_id' => 'f-anump',
                'value_attr' => (string)$conf['privat']['anump'],
                'is_config' => true,
            ]),
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
                'value_attr' => (string)$conf['privat']['letter'],
                'is_config' => true,
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
                'value_attr' => (string)$conf['privat']['send'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-messin',
            'label_html' => _PRINM,
            'hint_html' => _CONFINES,
            'hint_id' => $hntid = getFieldIds('f-messin')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => 'messin',
                'input_id' => 'f-messin',
                'value_attr' => (string)$conf['privat']['messin'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-messsav',
            'label_html' => _PRSAVEM,
            'hint_html' => _CONFINES,
            'hint_id' => $hntid = getFieldIds('f-messsav')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => 'messsav',
                'input_id' => 'f-messsav',
                'value_attr' => (string)$conf['privat']['messsav'],
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _PRMAIL,
            'label_id' => $labid = getFieldIds('', 'newmail')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'newmail',
                'value' => (string)(int)$conf['privat']['newmail'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _PRSELF,
            'label_id' => $labid = getFieldIds('', 'himself')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'himself',
                'value' => (string)(int)$conf['privat']['himself'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _VPROFIL,
            'label_id' => $labid = getFieldIds('', 'profil')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'profil',
                'value' => (string)(int)$conf['privat']['profil'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _VWEB,
            'label_id' => $labid = getFieldIds('', 'web')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'web',
                'value' => (string)(int)$conf['privat']['web'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _PRACT,
            'label_id' => $labid = getFieldIds('', 'act')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'act',
                'value' => (string)(int)$conf['privat']['act'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'privat'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('privat')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('privat');
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 50),
            'anum' => getVar('post', 'anum', 'num', 50),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'letter' => getVar('post', 'letter', 'num', 100),
            'send' => getVar('post', 'send', 'num', 60),
            'messin' => getVar('post', 'messin', 'num', 250),
            'messsav' => getVar('post', 'messsav', 'num', 250),
            'newmail' => getVar('post', 'newmail', 'num'),
            'himself' => getVar('post', 'himself', 'num'),
            'profil' => getVar('post', 'profil', 'num'),
            'web' => getVar('post', 'web', 'num'),
            'act' => getVar('post', 'act', 'num')
        ];
        setConfigFile('privat.php', $cont);
    }
    setRedirect($afile.'.php?name=privat&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $afile, $prv;
    $ids = getVar('post', 'id[]', 'num');
    $num = getVar('post', 'num', 'num', 1);
    $warn = !checkAdminPost('privat');
    $flt = getPrivatFilter('post');
    [$from, $where, $pars] = $prv->getAdminScope($flt['status'], $flt['search'], $flt['chng']);
    if (!$warn && setListPortion('p.id', $from, $where, $pars, static fn(int $id): bool => $prv->deleteAdminMessage([$id]))) exit;
    $done = !$warn && $ids && $prv->deleteAdminMessage(array_map('intval', $ids));
    setRedirect($afile.'.php?'.$flt['base'].'&num='.$num, false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=privat', 'name=privat&op=config', 'name=privat&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: privat(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
