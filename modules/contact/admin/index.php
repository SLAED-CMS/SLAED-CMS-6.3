<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('contact')) die('Illegal file access');

function contact(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=contact', 'name=contact&amp;op=info'], 'tabs' => [_PREFERENCES, _INFO]]);
    $cont .= checkPerms(CONFIG_DIR.'/contact.php');
    $rows = '';
    $rows .= getAdminFormRow(_CONTACTINFO.':', textarea('1', 'info', $conf['contact']['info'], 'all', '10', _CONTACTINFO, '0'));
    $rows .= getAdminFormRow(_CONTACTALL, radio_form($conf['contact']['admins'], 'admins'));
    $rows .= getAdminFormWide('<input type="hidden" name="name" value="contact"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue">', 'sl_center');
    $cont .= getAdminForm($afile.'.php', $rows);
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
    $cont = setAdminNavi(['ops' => ['name=contact', 'name=contact&amp;op=info'], 'tabs' => [_PREFERENCES, _INFO], 'tab' => 1]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: contact(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}


