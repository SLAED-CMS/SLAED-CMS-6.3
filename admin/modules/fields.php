<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function fields(): void {
    global $afile, $conf, $tpl;
    setHead();
    $mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
    $panels = [];
    $labs = [_ACCOUNT, _CONTENT, _FORUM, _HELP, _NEWS, _ORDER];
    $links = [];
    $ctab = getVar('get', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab >= count($mods)) $ctab = 0;
    $k = 0;
    foreach ($mods as $mod) {
        $links[] = [
            'href' => '#',
            'is_active' => $ctab === $k,
            'label' => $labs[$k],
            'rel' => 'fields-panel-'.$k,
            'title' => $labs[$k],
        ];
        $fset = explode('||', $conf['fields'][$mod]);
        $blok = '';
        for ($c = 0; $c < 10; $c++) {
            $out = array_pad(explode('|', (string)($fset[$c] ?? ''), 4), 4, '');
            if ($out[0] === '0') $out[0] = '';
            if ($out[1] === '0') $out[1] = '';
            if ($out[2] === '0') $out[2] = '';
            if ($out[3] === '0') $out[3] = '';
            $types = [_FIELDINPUT, _FIELDAREA, _FIELDSELECT, _FIELDTIME, _FIELDDATE];
            $opta = '';
            foreach ($types as $key => $txt) {
                $opta .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => (string)($key + 1),
                    'label_text' => $txt,
                    'is_selected' => $out[2] == ($key + 1),
                ]);
            }
            $uses = [_FIELDIN, _FIELDOUT];
            $optb = '';
            foreach ($uses as $key => $txt) {
                $optb .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => (string)($key + 1),
                    'label_text' => $txt,
                    'is_selected' => $out[3] == ($key + 1),
                ]);
            }
            $next = $c + 1;
            $rows = [
                [
                    'label_html' => _NAME,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'name_attr' => 'field1'.$k.'[]',
                        'value_attr' => $out[0],
                        'placeholder_text' => _NAME,
                        'is_required' => true,
                        'is_config' => true,
                    ]),
                ],
                [
                    'label_html' => _CONTENT,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'name_attr' => 'field2'.$k.'[]',
                        'value_attr' => $out[1],
                        'placeholder_text' => _CONTENT,
                        'is_required' => true,
                        'is_config' => true,
                    ]),
                ],
                [
                    'label_html' => _TYPE,
                    'field_html' => $tpl->getHtmlFrag('select', [
                        'name_attr' => 'field3'.$k.'[]',
                        'options_html' => $opta,
                        'is_config' => true,
                    ]),
                ],
                [
                    'label_html' => _USES,
                    'field_html' => $tpl->getHtmlFrag('select', [
                        'name_attr' => 'field4'.$k.'[]',
                        'options_html' => $optb,
                        'is_config' => true,
                    ]),
                ],
            ];
            $blok .= $tpl->getHtmlPart('toggle-form-block', [
                'block_id' => 'fi'.$k.$c,
                'is_hidden' => $out[0] === '' && $out[1] === '' && $c !== 0,
                'toggle_onclick' => "HideShow('fi".$k.$next."', 'slide', 'up', 500);",
                'title' => _ADD,
                'label_html' => _FIELD.': '.$next,
                'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows]),
            ]);
        }
        $panels[] = $tpl->getHtmlFrag('tabs-panel', [
            'panel_id' => 'fields-panel-'.$k,
            'active' => $ctab === $k,
            'content_html' => $blok,
        ]);
        $k++;
    }
    $links[] = [
        'href' => $afile.'.php?name=fields&amp;op=info&amp;tab='.$ctab,
        'label' => _INFO,
        'link_attr' => 'data-sl-tab-info-link="fields-main"',
        'title' => _INFO,
    ];
    $cont = getTplAdminTabs([
        'is_runtime' => true,
        'links' => $links,
        'tabs_id' => 'fields-main',
        'tabs_index' => $ctab,
        'tabs_sync_selector' => 'input[name="tab"]',
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/fields.php');
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _FIELDINFO]);
    $fieldv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'fields'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'tab', 'valueattr' => (string)$ctab],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $tpl->getHtmlPart('tabs', [
            'content_html' => implode('', $panels),
        ]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $fieldv]);
    setFoot();
}

function save(): void {
    global $afile;
    $ctab = getVar('post', 'tab', 'num', 0);
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [];
        $mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
        $a = 0;
        foreach ($mods as $val) {
            $fields = '';
            for ($i = 0; $i < 10; $i++) {
                $ident = ($i == 0) ? '' : '||';
                $field1 = getVar('post', 'field1'.$a.'['.$i.']', 'var', 0);
                $field2 = getVar('post', 'field2'.$a.'['.$i.']', 'var', 0);
                $field3 = getVar('post', 'field3'.$a.'['.$i.']', 'var', 0);
                $field4 = getVar('post', 'field4'.$a.'['.$i.']', 'var', 0);
                $fields .= $ident.$field1.'|'.$field2.'|'.$field3.'|'.$field4;
            }
            $a++;
            $cont[$val] = $fields;
        }
        setConfigFile('fields.php', $cont);
    }
    setRedirect($afile.'.php?name=fields&tab='.$ctab, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => [
            'name=fields&amp;tab=0',
            'name=fields&amp;tab=1',
            'name=fields&amp;tab=2',
            'name=fields&amp;tab=3',
            'name=fields&amp;tab=4',
            'name=fields&amp;tab=5',
            'name=fields&amp;op=info',
        ],
        'tabs' => [_ACCOUNT, _CONTENT, _FORUM, _HELP, _NEWS, _ORDER, _INFO],
    ]);
}

switch ($op) {
    default: fields(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
