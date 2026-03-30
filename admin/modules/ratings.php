<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function ratings(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=ratings', 'name=ratings&amp;op=info'], 'tabs' => [_HOME, _INFO]]);
    $cont .= checkPerms(CONFIG_DIR.'/ratings.php');
    $mods = ['account', 'faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $i = 0;
    $content = '';
    foreach ($mods as $val) {
        $con = explode('|', $conf['ratings'][$val]);
        $content .= $tpl->getHtmlFrag('admin-ratings-module-block', [
            'index_text' => (string)$i,
            'in_html' => radio_form($con[1], $i.'in'),
            'module_name' => getModuleName($val),
            'module_text' => $val,
            'show_hr' => $i != 0,
            'time_value' => (string)intval($con[0] / 86400),
            'view_html' => radio_form($con[2], $i.'view'),
        ]);
        $i++;
    }
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'ratings',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => $content,
    ]);
    echo $cont.getTplBox($confv);
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
    $cont = setAdminNavi(['ops' => ['name=ratings', 'name=ratings&amp;op=info'], 'tabs' => [_HOME, _INFO], 'tab' => 1]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: ratings(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
