<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# Every private message newest first with the state its columns add up to, deleted copies too, the body rendered as its recipient reads it
function privat(): void {
    global $afile, $conf, $tpl, $prs, $prv;
    setHead();
    $data = $prv->getAdminList(getVar('get', 'num', 'num', 1));
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
        $rows[] = ['name' => ($title !== '') ? cutstr($title, 60) : _ID.' '.$id, 'details' => [
            ['value' => $prs->filterContent($one['body'], true, 'account', 0, 'breaks', ['privat', $id])],
        ], 'drop' => getTplPostAction(['name' => 'privat', 'op' => 'delete', 'id[]' => $id, 'num' => $data['page']], 'trash', _ONDELETE), 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $title, 'text_html' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
            ['is_col_author' => true, 'content_html' => ($one['nameout'] !== '') ? user_info($one['nameout'], null) : _ANONYM],
            ['is_col_author' => true, 'content_html' => ($one['namein'] !== '') ? user_info($one['namein'], null) : _ANONYM],
            ['is_col_date' => true, 'moment' => $one['time']],
            ['is_col_status' => true, 'content_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $tone, 'label' => $note, 'title_text' => $note])],
            ['is_col_actions' => true, 'dial' => []],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=privat', 'name=privat&op=config', 'name=privat&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL], 'part' => [0]]),
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
        'href' => $afile.'.php?name=privat&',
        'page' => $data['page'],
        'pages' => $data['pages'],
        'maxpg' => intval($conf['privat']['anump']),
        'count' => $data['total'],
        'limit' => $data['limit'],
        'base' => $data['base'],
        'upto' => $data['upto'],
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
    if (!$warn && setListPortion('id', PREFIX_DB.'_privat', '', [], static fn(int $id): bool => $prv->deleteAdminMessage([$id]))) exit;
    $done = !$warn && $ids && $prv->deleteAdminMessage(array_map('intval', $ids));
    setRedirect($afile.'.php?name=privat&num='.$num, false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
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
