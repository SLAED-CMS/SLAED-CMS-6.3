<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=favorites', 'name=favorites&amp;op=conf', 'name=favorites&amp;op=info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    return getAdminTabs(_FAVORITES, 'favorites.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function favorites(): void {
    head();
    echo navi(0, 0, 0, 0).setTemplateBasic('open').'<div id="repfav_aliste">'.fav_aliste(1).'</div>'.setTemplateBasic('close');
    foot();
}

function conf(): void {
    global $aroute, $conffav;
    head();
    $cont = navi(0, 1, 0, 0);
    $cont .= checkConfigFile('favorites.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$aroute.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conffav['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conffav['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conffav['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conffav['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._FAVOR_UMAX.':</td><td><input type="number" name="favorites" value="'.$conffav['favorites'].'" class="sl_conf" placeholder="'._FAVOR_UMAX.'" required></td></tr>'
    .'<tr><td>'._FAVOR_ACT.'</td><td>'.radio_form($conffav['favact'], 'favact').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="favorites"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $aroute;
    $cont = [
        'num' => getVar('post', 'num', 'num', 15),
        'anum' => getVar('post', 'anum', 'num', 15),
        'nump' => getVar('post', 'nump', 'num', 5),
        'anump' => getVar('post', 'anump', 'num', 5),
        'favorites' => getVar('post', 'favorites', 'num'),
        'favact' => getVar('post', 'favact', 'num')
    ];
    setConfigFile('favorites.php', 'conffav', $cont);
    header('Location: '.$aroute.'.php?name=favorites&op=conf');
    exit;
}

function info(): void {
    head();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'favorites').'</div>';
    foot();
}

switch ($op) {
    default: favorites(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
