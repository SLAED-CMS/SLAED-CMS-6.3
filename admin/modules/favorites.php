<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The favorites of every user, newest first, each with the title of the material it marks, read per module in one query
function favorites(): void {
    global $afile, $db, $conf, $tpl;
    setHead();
    $limit = max(1, intval($conf['favorites']['anum']));
    $total = intval($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites')->fetchColumn());
    $pages = max(1, (int)ceil($total / $limit));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $sql = 'SELECT f.id, f.fid, f.modul, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) ORDER BY f.id DESC';
    $list = $db->getSqlQuery($sql.' LIMIT '.(($num - 1) * $limit).', '.$limit)->fetchAll(PDO::FETCH_NUM);
    $want = [];
    foreach ($list as [, $fid, $mod]) $want[$mod][intval($fid)] = $mod;
    $names = [];
    foreach ($want as $mod => $refs) {
        if ($mod === 'forum') {
            $pars = [];
            foreach (array_keys($refs) as $i => $one) $pars['f'.$i] = $one;
            $res = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_forum WHERE id IN (:'.implode(', :', array_keys($pars)).')', $pars);
            $names[$mod] = $res ? $res->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } else {
            $names[$mod] = getNodeTitleMap($refs);
        }
    }
    $rows = [];
    foreach ($list as [$id, $fid, $mod, $uname]) {
        $title = getDecodedText((string)($names[$mod][intval($fid)] ?? ''));
        $rows[] = ['name' => ($title !== '') ? cutstr($title, 60) : _ID.' '.$id, 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $title, 'has_content_text' => true, 'content_text' => $title],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => getModuleName($mod)],
            ['is_col_author' => true, 'content_html' => $uname ? user_info($uname, null) : _ANONYM],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => [
                ['href' => 'index.php?name='.$mod.'&op=view&id='.$fid.'#'.$fid, 'icon_name' => 'eye', 'title' => _MVIEW],
                getTplPostAction(['name' => 'favorites', 'op' => 'delete', 'id' => $id, 'num' => $num], 'trash', _ONDELETE) + ['later' => 'gone del'],
            ]])],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL],
            'part' => [0]]),
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true],
            ['content' => _MODUL, 'is_truncate' => true],
            ['content' => _POSTEDBY, 'is_col_author' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?name=favorites&',
        'page' => $num,
        'pages' => $pages,
        'maxpg' => intval($conf['favorites']['anump']),
        'count' => $total,
        'limit' => $limit,
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/favorites.php');
    $rows = [
        [
            'label_for' => 'f-num',
            'label_html' => _C_33,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'num',
                'input_id' => 'f-num',
                'value_attr' => (string)$conf['favorites']['num'],
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
                'value_attr' => (string)$conf['favorites']['anum'],
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
                'value_attr' => (string)$conf['favorites']['nump'],
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
                'value_attr' => (string)$conf['favorites']['anump'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-favorites',
            'label_html' => _FAVOR_UMAX,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'favorites',
                'input_id' => 'f-favorites',
                'value_attr' => (string)$conf['favorites']['favorites'],
                'is_config' => true,
            ]),
        ],
        ['label_html' => _FAVOR_ACT, 'label_id' => $labid = getFieldIds('', 'favact')['label'], 'field_html' => getTplRadioGroup([
            'labelledby' => $labid,
            'name' => 'favact',
            'value' => (string)$conf['favorites']['favact'],
            'options' => [
                ['value' => '1', 'label' => _YES],
                ['value' => '0', 'label' => _NO],
            ],
        ])],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'favorites'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('favorites')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('favorites');
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 15),
            'anum' => getVar('post', 'anum', 'num', 15),
            'nump' => getVar('post', 'nump', 'num', 5),
            'anump' => getVar('post', 'anump', 'num', 5),
            'favorites' => getVar('post', 'favorites', 'num'),
            'favact' => getVar('post', 'favact', 'num')
        ];
        setConfigFile('favorites.php', $cont);
    }
    setRedirect($afile.'.php?name=favorites&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $afile, $db;
    $id = getVar('post', 'id', 'num', 0);
    $num = getVar('post', 'num', 'num', 1);
    $warn = !checkAdminPost('favorites');
    $res = (!$warn && $id > 0) ? $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id', ['id' => $id]) : false;
    $done = $res && $res->rowCount() > 0;
    setRedirect($afile.'.php?name=favorites&num='.$num, false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: favorites(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
