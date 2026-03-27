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
    $rows = $tpl->getHtmlFrag('admin-contact-config-rows', [
        'admins_html' => radio_form($conf['contact']['admins'], 'admins'),
        'admins_label' => _CONTACTALL,
        'info_html' => textarea('1', 'info', $conf['contact']['info'], 'all', '10', _CONTACTINFO, '0'),
        'info_label' => _CONTACTINFO.':',
        'save_label' => _SAVECHANGES,
    ]);
    $hide = getAdminHidden('name', 'contact').getAdminHidden('op', 'save');
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
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

