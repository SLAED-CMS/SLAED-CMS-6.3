<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function privat(): void {
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    echo $cont.getTplAdminPlaceholder('repadminPrivateList', getAdminPrivateList(1));
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/privat.php');
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'privat',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => getTplHiddenInput('token', $token),
        '_c33' => _C_33,
        'num' => $conf['privat']['num'],
        '_c34' => _C_34,
        'anum' => $conf['privat']['anum'],
        '_c35' => _C_35,
        'nump' => $conf['privat']['nump'],
        '_c36' => _C_36,
        'anump' => $conf['privat']['anump'],
        '_comletter' => _COMLETTER,
        'letter' => $conf['privat']['letter'],
        '_csend' => _CSEND,
        'send' => $conf['privat']['send'],
        '_prinm' => _PRINM,
        'messin' => $conf['privat']['messin'],
        '_prsavem' => _PRSAVEM,
        'messsav' => $conf['privat']['messsav'],
        '_prmail' => _PRMAIL,
        'r_newmail' => radio_form($conf['privat']['newmail'], 'newmail'),
        '_prself' => _PRSELF,
        'r_himself' => radio_form($conf['privat']['himself'], 'himself'),
        '_vprofil' => _VPROFIL,
        'r_profil' => radio_form($conf['privat']['profil'], 'profil'),
        '_vweb' => _VWEB,
        'r_web' => radio_form($conf['privat']['web'], 'web'),
        '_pract' => _PRACT,
        'r_act' => radio_form($conf['privat']['act'], 'act'),
        'privat' => true,
    ]);
    echo $cont.getTplBox($confv);
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
    setRedirect($afile.'.php?name=privat&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: privat(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
