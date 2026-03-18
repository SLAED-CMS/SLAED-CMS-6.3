<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function favorites(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=conf', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    echo $cont.setTemplateBasic('open').'<div id="repfav_aliste">'.fav_aliste(1).'</div>'.setTemplateBasic('close');
    setFoot();
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=conf', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/favorites.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'       => $afile,
        '{%module%}'      => 'favorites',
        '{%op%}'          => 'save',
        '{%save%}'        => _SAVECHANGES,
        '{%fields%}'      => '',
        '{%_c33%}'        => _C_33,
        '{%num%}'         => $conf['favorites']['num'],
        '{%_c34%}'        => _C_34,
        '{%anum%}'        => $conf['favorites']['anum'],
        '{%_c35%}'        => _C_35,
        '{%nump%}'        => $conf['favorites']['nump'],
        '{%_c36%}'        => _C_36,
        '{%anump%}'       => $conf['favorites']['anump'],
        '{%_favor_umax%}' => _FAVOR_UMAX,
        '{%favorites%}'   => $conf['favorites']['favorites'],
        '{%_favor_act%}'  => _FAVOR_ACT,
        '{%r_favact%}'    => radio_form($conf['favorites']['favact'], 'favact'),
        'if_flag'         => ['favorites' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
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
    setRedirect($afile.'.php?name=favorites&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=favorites', 'name=favorites&amp;op=conf', 'name=favorites&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: favorites(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
