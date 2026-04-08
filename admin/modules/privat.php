<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function privat(): void {
    global $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    echo $cont.$tpl->getHtmlPart('box', [
        'box_id' => 'repadminPrivateList',
        'content_html' => getAdminPrivateList(1),
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/privat.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => getTplNumberInput((string)$conf['privat']['num'], 'num', 'sl_conf')],
        ['label_html' => _C_34, 'field_html' => getTplNumberInput((string)$conf['privat']['anum'], 'anum', 'sl_conf')],
        ['label_html' => _C_35, 'field_html' => getTplNumberInput((string)$conf['privat']['nump'], 'nump', 'sl_conf')],
        ['label_html' => _C_36, 'field_html' => getTplNumberInput((string)$conf['privat']['anump'], 'anump', 'sl_conf')],
        ['label_html' => _COMLETTER, 'field_html' => getTplNumberInput((string)$conf['privat']['letter'], 'letter', 'sl_conf')],
        ['label_html' => _CSEND, 'field_html' => getTplNumberInput((string)$conf['privat']['send'], 'send', 'sl_conf')],
        ['label_html' => _PRINM, 'field_html' => getTplNumberInput((string)$conf['privat']['messin'], 'messin', 'sl_conf')],
        ['label_html' => _PRSAVEM, 'field_html' => getTplNumberInput((string)$conf['privat']['messsav'], 'messsav', 'sl_conf')],
        ['label_html' => _PRMAIL, 'field_html' => radio_form($conf['privat']['newmail'], 'newmail')],
        ['label_html' => _PRSELF, 'field_html' => radio_form($conf['privat']['himself'], 'himself')],
        ['label_html' => _VPROFIL, 'field_html' => radio_form($conf['privat']['profil'], 'profil')],
        ['label_html' => _VWEB, 'field_html' => radio_form($conf['privat']['web'], 'web')],
        ['label_html' => _PRACT, 'field_html' => radio_form($conf['privat']['act'], 'act')],
    ];
    $confv = $tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden_html' => getTplHiddenInput('name', 'privat').getTplHiddenInput('op', 'save').getTplHiddenInput('token', getSiteToken()),
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
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
    setTplAdminInfoPage([
        'ops' => ['name=privat', 'name=privat&amp;op=config', 'name=privat&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: privat(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
