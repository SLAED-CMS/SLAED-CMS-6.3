<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('rss')) die('Illegal file access');

function rss(): void {
    global $afile, $conf, $tpl;
    setHead();
    $tab = getVar('get', 'tab', 'num', 0);
    if ($tab < 0 || $tab > 1) $tab = 0;
    $ops = ['name=rss&amp;tab=0', 'name=rss&amp;tab=1', 'name=rss&amp;op=info'];
    $tabs = [_RSS, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => $tab]);
    $cont .= checkPerms(CONFIG_DIR.'/rss.php');
    $rows = [];
    $fieldc = explode('||', (string)($conf['rss']['rss'] ?? ''));
    for ($c = 0; $c < 50; $c++) {
        preg_match('#(.*)\|(.*)\|(.*)#i', $fieldc[$c] ?? '', $out);
        $name = $out[1] ?? '';
        $addr = $out[2] ?? '';
        $uses = (int)($out[3] ?? 0);
        $indx = $c + 1;
        $opts =
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _RSSSITE, 'is_selected' => $uses === 0])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _RSSHOME, 'is_selected' => $uses === 1]);
        $block = $tpl->getHtmlPart('div', ['rows' => [
            ['label_html' => _NAME.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'field1[]', 'value_attr' => $name, 'placeholder_text' => _NAME, 'is_required' => true])],
            ['label_html' => _ADDRESS.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'field2[]', 'value_attr' => $addr, 'placeholder_text' => _ADDRESS])],
            ['label_html' => _USES.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'field3[]', 'options_html' => $opts])],
        ]]);
        $rows[] = $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'rss'.$c,
            'is_hidden' => $name === '' && $c !== 0,
            'toggle_onclick' => true,
            'href' => '#',
            'title' => _ADD,
            'label' => _RSSC.' '.$indx,
            'onclick_attr' => ' OnClick="HideShow(\'rss'.$indx.'\', \'slide\', \'up\', 500); return false;"',
            'content_html' => $block,
        ]);
    }
    $sourcehtml = implode('', $rows);
    $prefs = [
        ['label_html' => _RSSMIN.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'min', 'value_attr' => (string)($conf['rss']['min'] ?? 10), 'is_required' => true])],
        ['label_html' => _RSSMAX.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'max', 'value_attr' => (string)($conf['rss']['max'] ?? 100), 'is_required' => true])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _RSSTEMP.':', 'hint' => _RSSTEMPINFO]), 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'temp', 'value_text' => (string)($conf['rss']['temp'] ?? ''), 'rows_num' => 5, 'is_required' => true]), 'is_full' => true],
        ['label_html' => _RSSACT.':', 'field_html' => getTplRadioGroup(['name' => 'act', 'value' => (string)($conf['rss']['act'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _RSSUSE, 'field_html' => getTplRadioGroup(['name' => 'use', 'value' => (string)($conf['rss']['use'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $tabshml =
        $tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _RSS, 'title' => _RSS, 'is_active' => $tab === 0, 'rel' => 'rss-panel-0'])
        .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _PREFERENCES, 'title' => _PREFERENCES, 'is_active' => $tab === 1, 'rel' => 'rss-panel-1']);
    $panels =
        $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'rss-panel-0', 'content_html' => $sourcehtml])
        .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'rss-panel-1', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $prefs])]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=rss&amp;op=save',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'tab', 'valueattr' => (string)$tab],
        ],
        'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _RSSDESC])
            .$tpl->getHtmlPart('tabs', [
                'id' => 'rss',
                'is_runtime' => true,
                'init_attr' => 'data-sl-tabs-index="'.$tab.'"',
                'tabs_html' => $tabshml,
                'content_html' => $panels,
            ]),
        'submit_label' => _SAVECHANGES,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $form]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $tab = getVar('post', 'tab', 'num', 0);
    if ($tab < 0 || $tab > 1) $tab = 0;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $cont = [
            'min' => getVar('post', 'min', 'num', 10),
            'max' => getVar('post', 'max', 'num', 100),
            'temp' => getVar('post', 'temp', 'text', ''),
            'act' => getVar('post', 'act', 'bool', 0),
            'use' => getVar('post', 'use', 'bool', 0),
        ];
        $rss = '';
        $field1 = getVar('post', 'field1', 'raw', []);
        $field2 = getVar('post', 'field2', 'raw', []);
        $field3 = getVar('post', 'field3', 'raw', []);
        for ($i = 0; $i < 50; $i++) {
            $part = $i == 0 ? '' : '||';
            $rss .= $part.($field1[$i] ?? '0').'|'.($field2[$i] ?? '0').'|'.(int)($field3[$i] ?? 0);
        }
        $cont['rss'] = $rss;
        setConfigFile('rss.php', $cont);
    }
    setRedirect($afile.'.php?name=rss&tab='.$tab, false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=rss&amp;tab=0', 'name=rss&amp;tab=1', 'name=rss&amp;op=info'],
        'tabs' => [_RSS, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: rss(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
