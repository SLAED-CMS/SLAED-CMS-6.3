<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function info(): void {
    global $db, $conf, $tpl;
    $url = getVar('post', 'url', 'url');
    $mod = getVar('post', 'mod', 'text', 'news');
    $cat = getVar('post', 'cat', 'num');
    $num = getVar('post', 'num', 'num');
    
    $rssmod = ($mod) ? '&amp;name='.$mod : '';
    $rsscat = ($cat) ? '&amp;cat='.$cat : '';
    $rssnum = ($num) ? '&amp;num='.$num : '';
    $rsslink = $conf['homeurl'].'/index.php?go=rss'.$rssmod.$rsscat.$rssnum;
    
    $modsOptions = '';
    $mods = ['faq' => _FAQ, 'files' => _FILES, 'links' => _LINKS, 'media' => _MEDIA, 'news' => _NEWS, 'pages' => _PAGES, 'shop' => _SHOP];
    foreach ($mods as $key => $val) {
        if (is_active($key)) {
            $modsOptions .= getTplSelectOption((string)$key, (string)$val, (bool)($key == $mod));
        }
    }
    $numOptions = '';
    $lim = 1;
    while ($lim <= $conf['rss']['max']) {
        $rsslim = ($num) ? $num : $conf['rss']['min'];
        $numOptions .= getTplSelectOption((string)$lim, (string)_RSS_INFO_MENG.' - '.$lim, (bool)($lim == $rsslim));
        $lim++;
    }
    setHead(['title' => _RSS, 'desc' => _RSS_INFO_TEXT]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _RSS]);
    $cont .= $tpl->getHtmlFrag('rss-info-form', [
        'name' => $conf['name'],
        'style' => $conf['style'],
        'info_text' => _RSS_INFO_TEXT,
        'lbl_tip' => _RSS_INFO_TIP,
        'mods_options' => $modsOptions,
        'lbl_categories' => _CATEGORIES,
        'catselect' => getcat($mod, $cat, 'cat', $conf['style'], getTplOption('', _RSS_INFO_ALL, true)),
        'lbl_amount' => _RSS_INFO_MENG,
        'num_options' => $numOptions,
        'lbl_code' => _CODE,
        'rsslink' => $rsslink,
        'submit_label' => _RSS_INFO_CODE,
    ]);
    if ($conf['rss']['use'] == 1) {
        $link = ($url) ? $url : 'http://';
        $cont .= $tpl->getHtmlFrag('rss-read-forms', [
            'name' => $conf['name'],
            'style' => $conf['style'],
            'lbl_select_site' => _SELECTASITE,
            'rss_select' => rss_select(),
            'lbl_url' => _ORTYPEURL,
            'url_value' => $link,
            'submit_label' => _OK,
            'read_content' => rss_read($url, ''),
        ]);
    }
    echo $cont;
    setFoot();
}

switch($op) {
    default: info(); break;
}
