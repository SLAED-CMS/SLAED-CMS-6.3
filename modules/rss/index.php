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
            $modsOptions .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => (string)$val, 'is_selected' => $key == $mod]);
        }
    }
    $numOptions = '';
    $lim = 1;
    while ($lim <= $conf['rss']['max']) {
        $rsslim = ($num) ? $num : $conf['rss']['min'];
        $numOptions .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$lim, 'label_text' => (string)_RSS_INFO_MENG.' - '.$lim, 'is_selected' => $lim == $rsslim]);
        $lim++;
    }
    setHead(['title' => _RSS, 'desc' => _RSS_INFO_TEXT]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _RSS]);
    $fields = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _RSS_INFO_TEXT]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _RSS_INFO_TIP.':',
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'mod', 'options_html' => $modsOptions, 'select_attr' => 'OnChange="submit()"']),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _CATEGORIES.':',
        'field_html' => getTplCategorySelect($mod, $cat, 'cat', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _RSS_INFO_ALL, 'is_selected' => true])),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _RSS_INFO_MENG.':',
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'num', 'options_html' => $numOptions]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _CODE.':',
        'field_html' => $tpl->getHtmlFrag('textarea', ['cols_num' => 45, 'rows_num' => 3, 'value_text' => $rsslink, 'input_attr' => 'OnClick="this.select()"']),
    ]);
    $cont .= $tpl->getHtmlPart('form-add', [
        'action' => 'index.php?name='.$conf['name'],
        'method' => 'post',
        'form_name' => 'post',
        'no_enctype' => true,
        'fields' => $fields,
        'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'info', 'label' => _RSS_INFO_CODE]),
    ]);
    if ($conf['rss']['use'] == 1) {
        $link = ($url) ? $url : 'http://';
        $cont .= $tpl->getHtmlFrag('block-content', ['is_section' => true, 'has_hr' => true]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'method' => 'post',
            'form_name' => 'post',
            'no_enctype' => true,
            'fields' => $tpl->getHtmlFrag('form-field-row', [
                'label' => _SELECTASITE.':',
                'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'url', 'options_html' => rss_select()]),
            ]),
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _OK]),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'method' => 'post',
            'form_name' => 'post',
            'no_enctype' => true,
            'fields' => $tpl->getHtmlFrag('form-field-row', [
                'label' => _ORTYPEURL.':',
                'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'url', 'value_attr' => $link, 'maxlength_num' => 200, 'placeholder_text' => _ORTYPEURL]),
            ]),
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _OK]),
        ]);
        $cont .= rss_read($url, '');
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: info(); break;
}
