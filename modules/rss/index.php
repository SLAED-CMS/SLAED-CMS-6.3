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
            $modsOptions .= $tpl->getHtmlFrag('form-option', ['value' => (string)$key, 'label' => (string)$val, 'selected' => $key == $mod ? ' selected' : '']);
        }
    }
    $numOptions = '';
    $lim = 1;
    while ($lim <= $conf['rss']['max']) {
        $rsslim = ($num) ? $num : $conf['rss']['min'];
        $numOptions .= $tpl->getHtmlFrag('form-option', ['value' => (string)$lim, 'label' => (string)_RSS_INFO_MENG.' - '.$lim, 'selected' => $lim == $rsslim ? ' selected' : '']);
        $lim++;
    }
    setHead(['title' => _RSS, 'desc' => _RSS_INFO_TEXT]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _RSS]);
    $cont .= $tpl->getHtmlFrag('rss-info-form', [
        'name' => $conf['name'],
        'info_text' => _RSS_INFO_TEXT,
        'lbl_tip' => _RSS_INFO_TIP,
        'mods_options' => $modsOptions,
        'lbl_categories' => _CATEGORIES,
        'catselect' => getTplCategorySelect($mod, $cat, 'cat', '', $tpl->getHtmlFrag('form-option', ['value' => '', 'label' => _RSS_INFO_ALL, 'selected' => ' selected'])),
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

switch ($op) {
    default: info(); break;
}
