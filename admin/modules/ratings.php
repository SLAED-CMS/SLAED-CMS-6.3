<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=ratings', 'name=ratings&amp;op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs(_RATINGS, 'ratings.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function ratings(): void {
    global $afile, $conf;
    setHead();
    $cont = navi(0, 0, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/ratings.php');
    $mods = ['account', 'faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $i = 0;
    $content = '';
    foreach ($mods as $val) {
        $con = explode('|', $conf['ratings'][$val]);
        $hr = ($i == 0) ? '' : '<tr><td colspan="2" class="sl_center"><hr></td></tr>';
        $content .= $hr
           .'<tr><td>'._MODUL.':</td><td><span title="'._MODUL.': '.$val.'" class="sl_note">'.deflmconst($val).'</span></td></tr>'
           .'<tr><td>'._VOTING_TIME.':</td><td><input type="number" name="time[]" value="'.intval($con[0] / 86400).'" class="sl_conf" placeholder="'._VOTING_TIME.'" required></td></tr>'
           .'<tr><td>'._C_21.'</td><td>'.radio_form($con[1], $i.'in').'</td></tr>'
           .'<tr><td>'._C_22.'</td><td>'.radio_form($con[2], $i.'view').'</td></tr>';
        $i++;
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'.$content.'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="ratings"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $content = [];
    $mods = ['account', 'faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $i = 0;
    foreach ($mods as $val) {
        $time_days = getVar('post', 'time['.$i.']', 'num', 0);
        $time = $time_days > 0 ? $time_days * 86400 : 2592000;
        $in = getVar('post', $i.'in', 'num', 0);
        $view = getVar('post', $i.'view', 'num', 0);
        $content[$val] = $time.'|'.$in.'|'.$view;
        $i++;
    }
    setConfigFile('ratings.php', $content);
    setRedirect($afile.'.php?name=ratings');
}

function info(): void {
    setHead();
    echo navi(0, 1, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: ratings(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
