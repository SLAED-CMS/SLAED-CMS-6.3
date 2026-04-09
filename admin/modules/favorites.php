<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function favorites(): void {
    global $tpl;
    if (strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
        echo getAdminFavoriteList(1);
        return;
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    echo $cont.$tpl->getHtmlPart('box', [
        'box_id' => 'repadminFavoriteList',
        'content_html' => getAdminFavoriteList(1),
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/favorites.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['favorites']['num'], 'class' => 'sl_conf'])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['favorites']['anum'], 'class' => 'sl_conf'])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['favorites']['nump'], 'class' => 'sl_conf'])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['favorites']['anump'], 'class' => 'sl_conf'])],
        ['label_html' => _FAVOR_UMAX, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'favorites', 'value_attr' => (string)$conf['favorites']['favorites'], 'class' => 'sl_conf'])],
        ['label_html' => _FAVOR_ACT, 'field_html' => getTplRadioGroup([
            'name' => 'favact',
            'value' => (string)$conf['favorites']['favact'],
            'options' => [
                ['value' => '1', 'label' => _YES],
                ['value' => '0', 'label' => _NO],
            ],
        ])],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'favorites'],
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
            'favorites' => getVar('post', 'favorites', 'num'),
            'favact' => getVar('post', 'favact', 'num')
        ];
        setConfigFile('favorites.php', $cont);
    }
    setRedirect($afile.'.php?name=favorites&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $afile, $db, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $num = getVar('req', 'num', 'num', 1);
    $ishtmx = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    if (!checkSiteToken()) {
        if ($ishtmx) {
            echo $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _TOKENMISS]);
            return;
        }
        setRedirect($afile.'.php?name=favorites&num='.$num, false, 302, _TOKENMISS, true);
        return;
    }
    if ($id > 0) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id', ['id' => $id]);
    }
    if ($ishtmx) {
        echo getAdminFavoriteList(1);
        return;
    }
    setRedirect($afile.'.php?name=favorites&num='.$num, false, 302, _SUCCDELETE);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: favorites(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
