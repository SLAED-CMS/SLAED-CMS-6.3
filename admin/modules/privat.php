<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function privat(): void {
    global $tpl;
    if (strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
        echo getAdminPrivateList(1);
        return;
    }
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
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['privat']['num'], 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['privat']['anum'], 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['privat']['nump'], 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['privat']['anump'], 'is_config' => true])],
        ['label_html' => _COMLETTER, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'letter', 'value_attr' => (string)$conf['privat']['letter'], 'is_config' => true])],
        ['label_html' => _CSEND, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'send', 'value_attr' => (string)$conf['privat']['send'], 'is_config' => true])],
        ['label_html' => _PRINM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'messin', 'value_attr' => (string)$conf['privat']['messin'], 'is_config' => true])],
        ['label_html' => _PRSAVEM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'messsav', 'value_attr' => (string)$conf['privat']['messsav'], 'is_config' => true])],
        ['label_html' => _PRMAIL, 'field_html' => getTplRadioGroup(['name' => 'newmail', 'value' => (string)(int)$conf['privat']['newmail'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _PRSELF, 'field_html' => getTplRadioGroup(['name' => 'himself', 'value' => (string)(int)$conf['privat']['himself'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VPROFIL, 'field_html' => getTplRadioGroup(['name' => 'profil', 'value' => (string)(int)$conf['privat']['profil'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VWEB, 'field_html' => getTplRadioGroup(['name' => 'web', 'value' => (string)(int)$conf['privat']['web'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _PRACT, 'field_html' => getTplRadioGroup(['name' => 'act', 'value' => (string)(int)$conf['privat']['act'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'privat'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
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
    }
    setRedirect($afile.'.php?name=privat&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $afile, $db, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $num = getVar('req', 'num', 'num', 1);
    $ishtmx = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    if (!checkSiteToken()) {
        if ($ishtmx) {
            echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
            return;
        }
        setRedirect($afile.'.php?name=privat&num='.$num, false, 302, _TOKENMISS, true);
        return;
    }
    if ($id > 0) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id = :id', ['id' => $id]);
    }
    if ($ishtmx) {
        echo getAdminPrivateList(1);
        return;
    }
    setRedirect($afile.'.php?name=privat&num='.$num, false, 302, _SUCCDELETE);
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
    case 'delete': delete(); break;
    case 'info': info(); break;
}
