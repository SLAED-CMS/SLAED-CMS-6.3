<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function replace(): void {
    global $afile, $conf, $tpl;
    setHead();
    $mods = ['content', 'news'];
    $labs = [_CONTENT, _NEWS];
    $ctab = getVar('get', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab >= count($mods)) $ctab = 0;
    $links = [];
    $panels = [];
    foreach ($mods as $k => $val) {
        $links[] = [
            'href' => '#',
            'is_active' => $ctab === $k,
            'label' => $labs[$k],
            'rel' => 'replace-panel-'.$k,
            'title' => $labs[$k],
        ];
    }
    $links[] = [
        'href' => $afile.'.php?name=replace&amp;op=info&amp;tab='.$ctab,
        'label' => _INFO,
        'link_attr' => 'data-sl-tab-info-link="replace-main"',
        'title' => _INFO,
    ];
    $cont = getTplAdminTabs([
        'is_runtime' => true,
        'links' => $links,
        'tabs_id' => 'replace-main',
        'tabs_index' => $ctab,
        'tabs_sync_selector' => 'input[name="tab"]',
    ]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _REPLACEINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/replace.php');
    foreach ($mods as $k => $val) {
        $fieldc = explode('||', $conf['replace'][$val]);
        $blok = '';
        for ($c = 0; $c < 50; $c++) {
            $out = array_pad(explode('|', (string)($fieldc[$c] ?? ''), 2), 2, '');
            if ($out[0] === '0') $out[0] = '';
            if ($out[1] === '0') $out[1] = '';
            $next = $c + 1;
            $rows = [
                [
                    'label_html' => _WORD,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'name_attr' => 'field1'.$k.'[]',
                        'value_attr' => $out[0],
                        'placeholder_text' => _WORD,
                        'is_config' => true,
                    ]),
                ],
                [
                    'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CONTENT, 'hint' => _REPLACEIN]),
                    'field_html' => $tpl->getHtmlFrag('textarea', [
                        'name_attr' => 'field2'.$k.'[]',
                        'value_text' => $out[1],
                        'rows_num' => '5',
                        'is_config' => true,
                    ]),
                ],
            ];
            $blok .= $tpl->getHtmlPart('toggle-form-block', [
                'block_id' => 'fi'.$k.$c,
                'is_toggle_block' => true,
                'is_hidden' => $out[0] === '' && $out[1] === '' && $c !== 0,
                'toggle_target_id' => 'fi'.$k.$next,
                'title' => _ADD,
                'label_html' => _REPLACE_FIELD.': '.$next,
                'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows]),
            ]);
        }
        $panels[] = $tpl->getHtmlFrag('tabs-panel', [
            'panel_id' => 'replace-panel-'.$k,
            'active' => $ctab === $k,
            'content_html' => $blok,
        ]);
    }
    $repv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'replace'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'tab', 'valueattr' => (string)$ctab],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $tpl->getHtmlPart('tabs', [
            'content_html' => implode('', $panels),
        ]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $repv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    $ctab = getVar('post', 'tab', 'num', 0);
    $cont = [];
    $mods = ['content', 'news'];
    if (!$warn) {
        foreach ($mods as $a => $val) {
            $fields = '';
            for ($i = 0; $i < 50; $i++) {
                $ident = ($i == 0) ? '' : '||';
                $field1 = getVar('post', 'field1'.$a.'['.$i.']', 'word', '0');
                $field2 = getVar('post', 'field2'.$a.'['.$i.']', '', '0');
                $fields .= $ident.$field1.'|'.$field2;
            }
            $cont[$val] = $fields;
        }
        setConfigFile('replace.php', $cont);
    }
    setRedirect($afile.'.php?name=replace&tab='.$ctab, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=replace&amp;tab=0', 'name=replace&amp;tab=1', 'name=replace&amp;op=info'],
        'tabs' => [_CONTENT, _NEWS, _INFO],
    ]);
}

switch ($op) {
    default: replace(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
