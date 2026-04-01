<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function favorites(): void {
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    echo $cont.getTplAdminPlaceholder('repadminFavoriteList', getAdminFavoriteList(1));
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/favorites.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => getTplNumberInput((string)$conf['favorites']['num'], 'num', 'sl_conf')],
        ['label_html' => _C_34, 'field_html' => getTplNumberInput((string)$conf['favorites']['anum'], 'anum', 'sl_conf')],
        ['label_html' => _C_35, 'field_html' => getTplNumberInput((string)$conf['favorites']['nump'], 'nump', 'sl_conf')],
        ['label_html' => _C_36, 'field_html' => getTplNumberInput((string)$conf['favorites']['anump'], 'anump', 'sl_conf')],
        ['label_html' => _FAVOR_UMAX, 'field_html' => getTplNumberInput((string)$conf['favorites']['favorites'], 'favorites', 'sl_conf')],
        ['label_html' => _FAVOR_ACT, 'field_html' => radio_form($conf['favorites']['favact'], 'favact')],
    ];
    $confv = $tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden_html' => getTplHiddenInput('name', 'favorites').getTplHiddenInput('op', 'save').getTplHiddenInput('token', getSiteToken()),
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($confv);
    setFoot();
}

function save(): void {
    global $afile;
    $cont = [
        'num' => getVar('post', 'num', 'num', 15),
        'anum' => getVar('post', 'anum', 'num', 15),
        'nump' => getVar('post', 'nump', 'num', 5),
        'anump' => getVar('post', 'anump', 'num', 5),
        'favorites' => getVar('post', 'favorites', 'num'),
        'favact' => getVar('post', 'favact', 'num')
    ];
    setConfigFile('favorites.php', $cont);
    setRedirect($afile.'.php?name=favorites&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=config', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: favorites(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
