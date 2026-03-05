<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=privat', 'name=privat&amp;op=conf', 'name=privat&amp;op=info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function privat(): void {
    setHead();
    echo navi(0, 0, 0, 0).setTemplateBasic('open').'<div id="repajax_privat">'.ajax_privat(1).'</div>'.setTemplateBasic('close');
    setFoot();
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = navi(0, 1, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/privat.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conf['privat']['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['privat']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conf['privat']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['privat']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._COMLETTER.':</td><td><input type="number" name="letter" value="'.$conf['privat']['letter'].'" class="sl_conf" placeholder="'._COMLETTER.'" required></td></tr>'
    .'<tr><td>'._CSEND.':</td><td><input type="number" name="send" value="'.$conf['privat']['send'].'" class="sl_conf" placeholder="'._CSEND.'" required></td></tr>'
    .'<tr><td>'._PRINM.':</td><td><input type="number" name="messin" value="'.$conf['privat']['messin'].'" class="sl_conf" placeholder="'._PRINM.'" required></td></tr>'
    .'<tr><td>'._PRSAVEM.':</td><td><input type="number" name="messsav" value="'.$conf['privat']['messsav'].'" class="sl_conf" placeholder="'._PRSAVEM.'" required></td></tr>'
    .'<tr><td>'._PRMAIL.'</td><td>'.radio_form($conf['privat']['newmail'], 'newmail').'</td></tr>'
    .'<tr><td>'._PRSELF.'</td><td>'.radio_form($conf['privat']['himself'], 'himself').'</td></tr>'
    .'<tr><td>'._VPROFIL.'</td><td>'.radio_form($conf['privat']['profil'], 'profil').'</td></tr>'
    .'<tr><td>'._VWEB.'</td><td>'.radio_form($conf['privat']['web'], 'web').'</td></tr>'
    .'<tr><td>'._PRACT.'</td><td>'.radio_form($conf['privat']['act'], 'act').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="privat"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
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
    setRedirect($afile.'.php?name=privat&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: privat(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
