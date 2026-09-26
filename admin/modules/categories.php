<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function categories(): void {
    global $afile, $tpl;
    $modul = getVar('req', 'modul', 'var', '');
    $modlink = $modul ? '&modul='.$modul : '';
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
        'name=categories&op=info'.$modlink];
    $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'categories'],
        ],
        'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true, true),
    ])]);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
        'subtitle_html' => $subtitle,
    ]);
    echo $cont
        .$tpl->getHtmlFrag('alert', ['text' => _CATDRAGSORT])
        .$tpl->getHtmlFrag('alert',
            ['text' => _INFOCATDEL.' '.getTplPostButton(['name' => 'categories', 'op' => 'fix'] + ($modul ? ['modul' => $modul] : []), 'arrow-repeat', _FIX)])
        .$tpl->getHtmlPart('box', [
            'box_id' => 'repajax_cat',
            'content_html' => getAdminCategoryList($modul, 1),
        ]);
    setFoot();
}

function fix(): void {
    global $db, $afile;
    $modul = getVar('post', 'modul', 'var', 'forum');
    $warn = !checkAdminPost('categories');
    if (!$warn) {
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern ASC', ['modul' => $modul]);
        $ordern = 0;
        while ([$id] = $db->getSqlRow($result)) {
            $ordern++;
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :id', ['ordern' => $ordern, 'id' => $id]);
        }
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function add(): void {
    global $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
        'name=categories&op=info'.$modlink];
    $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'categories'],
        ],
        'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true),
    ])]);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
        'tab' => 1,
        'subtitle_html' => $subtitle,
    ]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _CACESSI]);
    $hint = _ACESSI.' '._CTRLINFO;
    $yesno = [
        ['value' => '1', 'label' => _YES],
        ['value' => '0', 'label' => _NO],
    ];
    $rows0 = [
        ['label_html' => _ACTIVATE2, 'label_id' => $labid = getFieldIds('', 'status')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'status', 'value' => '0', 'options' => $yesno])],
        ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'input_id' => 'f-title',
            'value_attr' => '',
            'maxlength_num' => 255,
            'placeholder_text' => _TITLE,
            'is_required' => true,
        ])],
        ['label_for' => 'f-description', 'label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
            'input_id' => 'f-description',
            'value_text' => '',
            'is_config' => true,
        ])],
        ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'imgcat', 'value_attr' => '', 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
        ['label_html' => _MODUL, 'field_html' => getTplCategoryModule('modul', 'sl-form-control', $modul)],
    ];
    if ($conf['multilingual'] == 1) {
        $rows0[] = ['label_for' => 'f-lang', 'label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'selectid' => 'f-lang', 'options_html' => getTplLanguageOptions()])];
    }
    $rows1 = [
        ['label_for' => ($fids = getFieldIds('f-pview'))['input'], 'label_html' => _CAN.' '._AUTH_VIEW, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pview', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pread'))['input'], 'label_html' => _CAN.' '._AUTH_READ, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pread', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
    ];
    $rows2 = [
        ['label_for' => ($fids = getFieldIds('f-ppost'))['input'], 'label_html' => _CAN.' '._AUTH_POST, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('ppost', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-preply'))['input'], 'label_html' => _CAN.' '._AUTH_REPLY, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('preply', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pedit'))['input'], 'label_html' => _CAN.' '._AUTH_EDIT, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pedit', 'sl-form-control', '', 1, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pdelete'))['input'], 'label_html' => _CAN.' '._AUTH_DELETE, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pdelete', 'sl-form-control', '', 1, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pmod'))['input'], 'label_html' => _CAN.' '._AUTH_MOD, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pmod', 'sl-form-control', '', 2, $fids['input'], $fids['hint'])],
    ];
    $tabs = $tpl->getHtmlPart('tabs', [
        'id' => 'categories-add',
        'is_runtime' => true,
        'is_subtabs' => true,
        'tabs_html' =>
            $tpl->getHtmlFrag('tabs-link', ['href' => '#', 'is_active' => true, 'label' => _CATEGORY, 'rel' => 'categories-add-panel-0', 'title' => _CATEGORY])
            .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESS, 'rel' => 'categories-add-panel-1', 'title' => _ACESS])
            .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESSF, 'rel' => 'categories-add-panel-2', 'title' => _ACESSF]),
        'content_html' =>
            $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-add-panel-0', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows0])])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-add-panel-1', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows1])])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-add-panel-2', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows2])]),
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'content_html' => $tabs,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'categories'],
            ['nameattr' => 'op', 'valueattr' => 'addsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('categories')],
        ],
        'submit_label' => _ADD,
    ])]).getAdminIconWindow();
    setFoot();
}

function subadd(): void {
    global $db, $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
        'name=categories&op=info'.$modlink];
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'categories'],
            ],
            'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true),
        ])]);
        $cont = getTplAdminTabs([
            'ops' => $ops,
            'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
            'tab' => 2,
            'subtitle_html' => $subtitle,
        ]);
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _CACESSI]);
        $hint = _ACESSI.' '._CTRLINFO;
        $yesno = [
            ['value' => '1', 'label' => _YES],
            ['value' => '0', 'label' => _NO],
        ];
        $rows0 = [
            ['label_html' => _ACTIVATE2, 'label_id' => $labid = getFieldIds('', 'status')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'status', 'value' => '0', 'options' => $yesno])],
            ['label_html' => _CATEGORY, 'field_html' => getTplCategorySelect($modul, 0, 'cid', 'sl-form-control')],
            ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'input_id' => 'f-title',
                'value_attr' => '',
                'maxlength_num' => 255,
                'placeholder_text' => _TITLE,
                'is_required' => true,
            ])],
            ['label_for' => 'f-description', 'label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'description',
                'input_id' => 'f-description',
                'value_text' => '',
                'is_config' => true,
            ])],
            ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'imgcat', 'value_attr' => '', 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
            ['label_html' => _MODUL, 'field_html' => getTplCategoryModule('modul', 'sl-form-control', $modul)],
        ];
        if ($conf['multilingual'] == 1) {
            $rows0[] = ['label_for' => 'f-lang', 'label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'selectid' => 'f-lang', 'options_html' => getTplLanguageOptions()])];
        }
        $rows1 = [
            ['label_for' => ($fids = getFieldIds('f-pview'))['input'], 'label_html' => _CAN.' '._AUTH_VIEW, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pview', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
            ['label_for' => ($fids = getFieldIds('f-pread'))['input'], 'label_html' => _CAN.' '._AUTH_READ, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pread', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
        ];
        $rows2 = [
            ['label_for' => ($fids = getFieldIds('f-ppost'))['input'], 'label_html' => _CAN.' '._AUTH_POST, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('ppost', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
            ['label_for' => ($fids = getFieldIds('f-preply'))['input'], 'label_html' => _CAN.' '._AUTH_REPLY, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('preply', 'sl-form-control', '', 0, $fids['input'], $fids['hint'])],
            ['label_for' => ($fids = getFieldIds('f-pedit'))['input'], 'label_html' => _CAN.' '._AUTH_EDIT, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pedit', 'sl-form-control', '', 1, $fids['input'], $fids['hint'])],
            ['label_for' => ($fids = getFieldIds('f-pdelete'))['input'], 'label_html' => _CAN.' '._AUTH_DELETE, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pdelete', 'sl-form-control', '', 1, $fids['input'], $fids['hint'])],
            ['label_for' => ($fids = getFieldIds('f-pmod'))['input'], 'label_html' => _CAN.' '._AUTH_MOD, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pmod', 'sl-form-control', '', 2, $fids['input'], $fids['hint'])],
        ];
        $tabs = $tpl->getHtmlPart('tabs', [
            'id' => 'categories-subadd',
            'is_runtime' => true,
            'is_subtabs' => true,
            'tabs_html' =>
                $tpl->getHtmlFrag('tabs-link', ['href' => '#', 'is_active' => true, 'label' => _CATEGORY, 'rel' => 'categories-subadd-panel-0', 'title' => _CATEGORY])
                .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESS, 'rel' => 'categories-subadd-panel-1', 'title' => _ACESS])
                .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESSF, 'rel' => 'categories-subadd-panel-2', 'title' => _ACESSF]),
            'content_html' =>
                $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-subadd-panel-0', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows0])])
                .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-subadd-panel-1', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows1])])
                .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-subadd-panel-2', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows2])]),
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'content_html' => $tabs,
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'categories'],
                ['nameattr' => 'op', 'valueattr' => 'addsave'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('categories')],
            ],
            'submit_label' => _ADD,
        ])]).getAdminIconWindow();
    } else {
        $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'categories'],
            ],
            'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true),
        ])]);
        $navi = getTplAdminTabs([
            'ops' => $ops,
            'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
            'tab' => 2,
            'subtitle_html' => $subtitle,
        ]);
        $cont = $navi.$tpl->getHtmlFrag('alert', ['text' => sprintf(_ERROR_SUBCAT, getModuleName($modul))]);
    }
    echo $cont;
    setFoot();
}

function addedit(): void {
    global $db, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
        'name=categories&op=info'.$modlink];
    $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'categories'],
        ],
        'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true),
    ])]);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
        'tab' => 3,
        'subtitle_html' => $subtitle,
    ]);
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'categories'],
                ['nameattr' => 'op', 'valueattr' => 'edit'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('categories')],
            ],
            'rows' => [[
                'label_html' => _CATEGORY,
                'field_html' => getTplCategorySelect($modul, 0, 'cid', 'sl-form-control'),
            ]],
            'submit_label' => _EDIT,
        ])]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => sprintf(_ERROR_SUBCAT, getModuleName($modul))]);
    }
    echo $cont;
    setFoot();
}

function edit(): void {
    global $db, $conf, $afile, $tpl;
    $cid = getVar('req', 'cid', 'num');
    $result = $db->getSqlQuery('SELECT modul, title, intro, img, lang, parent, status, pview, pread, ppost, preply, pedit, pdelete, pmod FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $cid]);
    [$modul, $title, $desc, $imgcat, $lang, $parent, $status, $pview, $pread, $ppost, $preply, $pedit, $pdelete, $pmod] = $db->getSqlRow($result);
    $imgcat = preg_match('/^[a-z0-9-]+$/', (string)$imgcat) ? $imgcat : '';
    $modlink = '&modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
        'name=categories&op=info'.$modlink];
    $subtitle = $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'categories'],
        ],
        'content_html' => _MODUL.': '.getTplCategoryModule('modul', '', $modul, true),
    ])]);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
        'tab' => 3,
        'subtitle_html' => $subtitle,
    ]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _CACESSI]);
    $hint = _ACESSI.' '._CTRLINFO;
    $yesno = [
        ['value' => '1', 'label' => _YES],
        ['value' => '0', 'label' => _NO],
    ];
    $rows0 = [
        ['label_html' => _ACTIVATE2, 'label_id' => $labid = getFieldIds('', 'status')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'status', 'value' => $status, 'options' => $yesno])],
        ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'input_id' => 'f-title',
            'value_attr' => (string)$title,
            'maxlength_num' => 255,
            'placeholder_text' => _TITLE,
            'is_required' => true,
        ])],
        ['label_for' => 'f-description', 'label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
            'input_id' => 'f-description',
            'value_text' => (string)$desc,
            'is_config' => true,
        ])],
        ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'imgcat', 'value_attr' => $imgcat, 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
        ['label_html' => _MODUL, 'field_html' => getTplCategoryModule('modul', 'sl-form-control', $modul)],
    ];
    if ($parent != 0) {
        $rows0[] = ['label_html' => _CATEGORY, 'field_html' => getTplCategorySelect($modul, $parent, 'parent', 'sl-form-control')];
    }
    if ($conf['multilingual'] == 1) {
        $rows0[] = ['label_for' => 'f-lang', 'label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'selectid' => 'f-lang', 'options_html' => getTplLanguageOptions($lang)])];
    }
    $rows1 = [
        ['label_for' => ($fids = getFieldIds('f-pview'))['input'], 'label_html' => _CAN.' '._AUTH_VIEW, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pview', 'sl-form-control', $pview, 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pread'))['input'], 'label_html' => _CAN.' '._AUTH_READ, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pread', 'sl-form-control', $pread, 0, $fids['input'], $fids['hint'])],
    ];
    $rows2 = [
        ['label_for' => ($fids = getFieldIds('f-ppost'))['input'], 'label_html' => _CAN.' '._AUTH_POST, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('ppost', 'sl-form-control', $ppost, 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-preply'))['input'], 'label_html' => _CAN.' '._AUTH_REPLY, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('preply', 'sl-form-control', $preply, 0, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pedit'))['input'], 'label_html' => _CAN.' '._AUTH_EDIT, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pedit', 'sl-form-control', $pedit, 1, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pdelete'))['input'], 'label_html' => _CAN.' '._AUTH_DELETE, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pdelete', 'sl-form-control', $pdelete, 1, $fids['input'], $fids['hint'])],
        ['label_for' => ($fids = getFieldIds('f-pmod'))['input'], 'label_html' => _CAN.' '._AUTH_MOD, 'hint_html' => $hint, 'hint_id' => $fids['hint'], 'field_html' => catacess('pmod', 'sl-form-control', $pmod, 2, $fids['input'], $fids['hint'])],
    ];
    $tabs = $tpl->getHtmlPart('tabs', [
        'id' => 'categories-edit',
        'is_runtime' => true,
        'is_subtabs' => true,
        'tabs_html' =>
            $tpl->getHtmlFrag('tabs-link', ['href' => '#', 'is_active' => true, 'label' => _CATEGORY, 'rel' => 'categories-edit-panel-0', 'title' => _CATEGORY])
            .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESS, 'rel' => 'categories-edit-panel-1', 'title' => _ACESS])
            .$tpl->getHtmlFrag('tabs-link', ['href' => '#', 'label' => _ACESSF, 'rel' => 'categories-edit-panel-2', 'title' => _ACESSF]),
        'content_html' =>
            $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-edit-panel-0', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows0])])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-edit-panel-1', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows1])])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'categories-edit-panel-2', 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows2])]),
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'content_html' => $tabs,
        'hidden' => array_merge(
            [
                ['nameattr' => 'id', 'valueattr' => (string)$cid],
                ['nameattr' => 'name', 'valueattr' => 'categories'],
                ['nameattr' => 'op', 'valueattr' => 'save'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('categories')],
            ],
            $parent == 0 ? [['nameattr' => 'parent', 'valueattr' => '0']] : []
        ),
        'submit_label' => _SAVECHANGES,
    ])]).getAdminIconWindow();
    setFoot();
}

# The seven access rules of the category form as stored rule strings: a posted list of level|group values, or the default of the rule when none is valid
function getCategoryRights(): array {
    $out = [];
    foreach (['pview' => '0|0', 'pread' => '0|0', 'ppost' => '0|0', 'preply' => '0|0', 'pedit' => '3|0', 'pdelete' => '3|0', 'pmod' => '3|0'] as $key => $def) {
        $list = array_values(array_filter(getVar('post', $key.'[]', '', []), fn(mixed $v): bool => is_string($v) && preg_match('/^[0-9]+\|[0-9]+$/D', $v)));
        $out[$key] = $list ? scatacess($list) : $def;
    }
    return $out;
}

function addsave(): void {
    global $db, $afile;
    $warn = !checkAdminPost('categories');
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $lang = getVar('post', 'lang', 'var');
    $cid = getVar('post', 'cid', 'num', 0);
    $imgcat = strtolower($imgcat);
    if (!preg_match('/^[a-z0-9-]+$/', $imgcat)) $imgcat = '';
    $status = getVar('post', 'status', 'num');
    $row = ['modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $cid, 'status' => $status] + getCategoryRights();
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    $node = true;
    if (!$warn) {
        $label = htmlspecialchars($modul, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        try {
            $srv = getNodeWriter();
            $node = $srv->checkTypeRegistry([$modul]);
            if ($node) $srv->addNodeCategory($row);
        } catch (NodeException $err) {
            $warn = true;
            $text = match ($err->getCode()) {
                NodeException::CONFLICT => sprintf(_NODE_STALE, $label),
                NodeException::STORAGE => _ERROR_UP,
                default => sprintf(_NODE_BAD, $label),
            };
        }
    }
    if (!$node) {
        [$ordern] = $db->getSqlRow($db->getSqlQuery('SELECT ordern FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern DESC', ['modul' => $modul]));
        $keys = array_keys($row);
        $sql = 'INSERT INTO '.PREFIX_DB.'_categories ('.implode(', ', $keys).', ordern) VALUES (:'.implode(', :', $keys).', :ordern)';
        $db->getSqlQuery($sql, $row + ['ordern' => intval($ordern) + 1]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $text, $warn);
}

function save(): void {
    global $db, $afile;
    $warn = !checkAdminPost('categories');
    $id = getVar('post', 'id', 'num');
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $lang = getVar('post', 'lang', 'var');
    $parent = getVar('post', 'parent', 'num');
    $imgcat = strtolower($imgcat);
    if (!preg_match('/^[a-z0-9-]+$/', $imgcat)) $imgcat = '';
    $status = getVar('post', 'status', 'num');
    $row = ['modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $parent, 'status' => $status] + getCategoryRights();
    $was = (string)$db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id])->fetchColumn();
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    $node = true;
    if (!$warn) {
        $label = htmlspecialchars($modul, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        try {
            $srv = getNodeWriter();
            $old = $srv->checkTypeRegistry([$was]);
            if ($old) $label = htmlspecialchars($was, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $node = $old || $srv->checkTypeRegistry([$modul]);
            if ($node) $srv->updateNodeCategory($id, $row);
        } catch (NodeException $err) {
            $warn = true;
            $text = match ($err->getCode()) {
                NodeException::CONFLICT => sprintf(_NODE_STALE, $label),
                NodeException::STORAGE => _ERROR_UP,
                default => sprintf(_NODE_BAD, $label),
            };
        }
    }
    if (!$node) {
        $set = implode(', ', array_map(fn(string $v): string => $v.' = :'.$v, array_keys($row)));
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET '.$set.' WHERE id = :id', $row + ['id' => $id]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $text, $warn);
}

function change(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $act = getVar('post', 'act', 'num', 0);
    $modul = getVar('post', 'modul', 'var', '');
    $warn = !checkAdminPost('categories');
    $text = $warn ? _TOKENMISS : _SUCCSTATUS;
    $sql = 'SELECT modul, title, intro, img, lang, parent, status, pview, pread, ppost, preply, pedit, pdelete, pmod FROM '.PREFIX_DB.'_categories WHERE id = :id';
    $row = (!$warn && $id) ? $db->getSqlQuery($sql, ['id' => $id])->fetch(PDO::FETCH_ASSOC) : false;
    $node = true;
    if ($row) {
        $label = htmlspecialchars((string)$row['modul'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        try {
            $srv = getNodeWriter();
            $node = $srv->checkTypeRegistry([$row['modul']]);
            if ($node) $srv->updateNodeCategory($id, ['status' => $act ? 0 : 1] + $row);
        } catch (NodeException $err) {
            $warn = true;
            $text = match ($err->getCode()) {
                NodeException::CONFLICT => sprintf(_NODE_STALE, $label),
                NodeException::STORAGE => _ERROR_UP,
                default => sprintf(_NODE_BAD, $label),
            };
        }
    }
    if (!$node) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET status = :status WHERE id = :id', ['status' => $act ? 0 : 1, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=categories'.($modul ? '&modul='.$modul : ''), false, 302, $text, $warn);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $modul = getVar('post', 'modul', 'var', 'forum');
    $warn = !checkAdminPost('categories');
    $text = $warn ? _TOKENMISS : _SUCCDELETE;
    $was = (!$warn && $id) ? (string)$db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id])->fetchColumn() : '';
    $node = true;
    if (!$warn && $id) {
        try {
            $srv = getNodeWriter();
            $node = $srv->checkTypeRegistry([$was]);
            if ($node) $srv->deleteNodeCategory($id);
        } catch (NodeException $err) {
            $warn = true;
            $text = match ($err->getCode()) {
                NodeException::CONFLICT => sprintf(_NODE_STALE, htmlspecialchars($was, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                NodeException::STORAGE => _ERROR_UP,
                default => _INFOCATDEL,
            };
        }
    }
    if (!$node) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE parent = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $text, $warn);
}

function info(): void {
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    setTplAdminInfoPage([
        'ops' => ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink,
            'name=categories&op=info'.$modlink],
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _DOCS],
    ]);
}

switch ($op) {
    default: categories(); break;
    case 'fix': fix(); break;
    case 'add': add(); break;
    case 'subadd': subadd(); break;
    case 'addedit': addedit(); break;
    case 'addsave': addsave(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'change': change(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
