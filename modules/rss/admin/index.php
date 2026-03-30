<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('rss')) die('Illegal file access');

function rss(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['', '', 'name=rss&amp;op=info'], 'tabs' => [_RSS, _PREFERENCES, _INFO], 'id' => 'rss']);
    $cont .= checkPerms(CONFIG_DIR.'/rss.php');
    $content = '';
    $fieldc = explode('||', $conf['rss']['rss']);
    for ($c = 0; $c < 50; $c++) {
        preg_match('#(.*)\|(.*)\|(.*)#i', $fieldc[$c], $out);
        $field = '';
        for ($i = 0; $i < 2; $i++) {
            $fieldname = ($i == 0) ? _RSSSITE : _RSSHOME;
            $field .= getTplOption((string)$i, $fieldname, isset($out[3]) && $out[3] == $i);
        }
        $field = getTplSelect('field3[]', $field, 'sl_conf');
        $b = $c + 1;
        $out1 = $out[1] ?? '';
        $out2 = $out[2] ?? '';
        $content .= $tpl->getHtmlFrag('admin-rss-source-block', [
            'add_label' => _ADD,
            'address_label' => _ADDRESS.':',
            'address_value' => $out2,
            'block_id' => 'rss'.$c,
            'hidden' => empty($out1) && $c != 0,
            'index_text' => (string)$b,
            'is_first' => $c == 0,
            'name_label' => _NAME.':',
            'name_value' => $out1,
            'next_id' => 'rss'.$b,
            'rssc_label' => _RSSC.':',
            'uses_html' => $field,
            'uses_label' => _USES.':',
        ]);
    }
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _RSSDESC]);
    $cont .= $tpl->getHtmlFrag('admin-rss-config-form', [
        'act_html' => radio_form($conf['rss']['act'], 'act'),
        'act_label' => _RSSACT.':',
        'max_label' => _RSSMAX.':',
        'max_value' => (string)$conf['rss']['max'],
        'min_label' => _RSSMIN.':',
        'min_value' => (string)$conf['rss']['min'],
        'route' => $afile,
        'rss_sources_html' => $content,
        'tab_one_id' => getTplAdminTabName('rss', 0),
        'tab_two_id' => getTplAdminTabName('rss', 1),
        'save_label' => _SAVECHANGES,
        'temp_hint' => _RSSTEMPINFO,
        'temp_label' => _RSSTEMP.':',
        'temp_placeholder' => _RSSTEMP,
        'temp_value' => $conf['rss']['temp'],
        'use_html' => radio_form($conf['rss']['use'], 'use'),
        'use_label' => _RSSUSE,
    ]);
    echo getTplBox($cont);
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $cont = [
        'min' => getVar('post', 'min', 'num', 10),
        'max' => getVar('post', 'max', 'num', 100),
        'temp' => getVar('post', 'temp', '', ''),
        'act' => getVar('post', 'act', 'bool', 0),
        'use' => getVar('post', 'use', 'bool', 0),
    ];
    $rss = '';
    $field1 = getVar('post', 'field1', 'raw', []);
    $field2 = getVar('post', 'field2', 'raw', []);
    $field3 = getVar('post', 'field3', 'raw', []);
    for ($i = 0; $i < 50; $i++) {
        $ident = ($i == 0) ? '' : '||';
        $rss .= $ident.($field1[$i] ?? '0').'|'.($field2[$i] ?? '0').'|'.intval($field3[$i] ?? 0);
    }
    $cont['rss'] = $rss;
    setConfigFile('rss.php', $cont);
    setRedirect($afile.'.php?name=rss');
}
function info(): void {
    $cont = setAdminNavi(['ops' => ['name=rss', 'name=rss', 'name=rss&amp;op=info'], 'tabs' => [_RSS, _PREFERENCES, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: rss(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
