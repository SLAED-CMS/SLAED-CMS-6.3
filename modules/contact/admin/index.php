<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('contact')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=contact', 'name=contact&amp;op=info'];
    $lang = [_PREFERENCES, _INFO];
    return getAdminTabs(_FEEDBACK, 'contact.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function contact(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 0, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/contact.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._CONTACTINFO.':</td><td>'.textarea('1', 'info', $conf['contact']['info'], 'all', '10', _CONTACTINFO, '0').'</td></tr>'
    .'<tr><td>'._CONTACTALL.'</td><td>'.radio_form($conf['contact']['admins'], 'admins').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="contact"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $cont = [
        'info' => getVar('post', 'info', 'text', ''),
        'admins' => getVar('post', 'admins', 'num', 0),
    ];
    setConfigFile('contact.php', $cont);
    setRedirect($afile.'.php?name=contact');
}

function info(): void {
    setHead();
    echo navi(0, 1, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: contact(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}


