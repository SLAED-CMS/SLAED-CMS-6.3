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
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink];
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
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
        'subtitle_html' => $subtitle,
    ]);
    echo $cont
        .$tpl->getHtmlFrag('alert', ['text' => _CATDRAGSORT])
        .$tpl->getHtmlFrag('alert', ['text' => _INFOCATDEL])
        .$tpl->getHtmlPart('box', [
            'box_id' => 'repajax_cat',
            'content_html' => getAdminCategoryList($modul, 1),
        ]);
    setFoot();
}

function fix(): void {
    global $db, $afile;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $warn = !checkSiteToken();
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
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink];
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
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
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
        ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'status', 'value' => '0', 'options' => $yesno])],
        ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'value_attr' => '',
            'maxlength_num' => 255,
            'placeholder_text' => _TITLE,
            'is_required' => true,
        ])],
        ['label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
            'value_text' => '',
            'is_config' => true,
        ])],
        ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'imgcat', 'value_attr' => '', 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
        ['label_html' => _MODUL, 'field_html' => getTplCategoryModule('modul', 'sl-form-control', $modul)],
    ];
    if ($conf['multilingual'] == 1) {
        $rows0[] = ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions()])];
    }
    $rows1 = [
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_VIEW, 'hint' => $hint]), 'field_html' => catacess('pview', 'sl-form-control', '', 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_READ, 'hint' => $hint]), 'field_html' => catacess('pread', 'sl-form-control', '', 0)],
    ];
    $rows2 = [
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_POST, 'hint' => $hint]), 'field_html' => catacess('ppost', 'sl-form-control', '', 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_REPLY, 'hint' => $hint]), 'field_html' => catacess('preply', 'sl-form-control', '', 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_EDIT, 'hint' => $hint]), 'field_html' => catacess('pedit', 'sl-form-control', '', 1)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_DELETE, 'hint' => $hint]), 'field_html' => catacess('pdelete', 'sl-form-control', '', 1)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_MOD, 'hint' => $hint]), 'field_html' => catacess('pmod', 'sl-form-control', '', 2)],
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'submit_label' => _ADD,
    ])]).$tpl->getHtmlPart('icon-picker-modal', ['search_text' => _SEARCH, 'close_text' => _CLOSE]);
    setFoot();
}

function subadd(): void {
    global $db, $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink];
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
            'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
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
            ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'status', 'value' => '0', 'options' => $yesno])],
            ['label_html' => _CATEGORY, 'field_html' => getTplCategorySelect($modul, 0, 'cid', 'sl-form-control')],
            ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'value_attr' => '',
                'maxlength_num' => 255,
                'placeholder_text' => _TITLE,
                'is_required' => true,
            ])],
            ['label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'description',
                'value_text' => '',
                'is_config' => true,
            ])],
            ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'imgcat', 'value_attr' => '', 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
            ['label_html' => _MODUL, 'field_html' => getTplCategoryModule('modul', 'sl-form-control', $modul)],
        ];
        if ($conf['multilingual'] == 1) {
            $rows0[] = ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions()])];
        }
        $rows1 = [
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_VIEW, 'hint' => $hint]), 'field_html' => catacess('pview', 'sl-form-control', '', 0)],
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_READ, 'hint' => $hint]), 'field_html' => catacess('pread', 'sl-form-control', '', 0)],
        ];
        $rows2 = [
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_POST, 'hint' => $hint]), 'field_html' => catacess('ppost', 'sl-form-control', '', 0)],
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_REPLY, 'hint' => $hint]), 'field_html' => catacess('preply', 'sl-form-control', '', 0)],
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_EDIT, 'hint' => $hint]), 'field_html' => catacess('pedit', 'sl-form-control', '', 1)],
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_DELETE, 'hint' => $hint]), 'field_html' => catacess('pdelete', 'sl-form-control', '', 1)],
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_MOD, 'hint' => $hint]), 'field_html' => catacess('pmod', 'sl-form-control', '', 2)],
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
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ],
            'submit_label' => _ADD,
        ])]).$tpl->getHtmlPart('icon-picker-modal', ['search_text' => _SEARCH, 'close_text' => _CLOSE]);
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
            'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
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
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink];
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
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
        'tab' => 3,
        'subtitle_html' => $subtitle,
    ]);
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'categories'],
                ['nameattr' => 'op', 'valueattr' => 'edit'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
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
    $ops = ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink];
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
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
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
        ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'status', 'value' => $status, 'options' => $yesno])],
        ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'value_attr' => (string)$title,
            'maxlength_num' => 255,
            'placeholder_text' => _TITLE,
            'is_required' => true,
        ])],
        ['label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
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
        $rows0[] = ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions($lang)])];
    }
    $rows1 = [
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_VIEW, 'hint' => $hint]), 'field_html' => catacess('pview', 'sl-form-control', $pview, 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_READ, 'hint' => $hint]), 'field_html' => catacess('pread', 'sl-form-control', $pread, 0)],
    ];
    $rows2 = [
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_POST, 'hint' => $hint]), 'field_html' => catacess('ppost', 'sl-form-control', $ppost, 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_REPLY, 'hint' => $hint]), 'field_html' => catacess('preply', 'sl-form-control', $preply, 0)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_EDIT, 'hint' => $hint]), 'field_html' => catacess('pedit', 'sl-form-control', $pedit, 1)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_DELETE, 'hint' => $hint]), 'field_html' => catacess('pdelete', 'sl-form-control', $pdelete, 1)],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAN.' '._AUTH_MOD, 'hint' => $hint]), 'field_html' => catacess('pmod', 'sl-form-control', $pmod, 2)],
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
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ],
            $parent == 0 ? [['nameattr' => 'parent', 'valueattr' => '0']] : []
        ),
        'submit_label' => _SAVECHANGES,
    ])]).$tpl->getHtmlPart('icon-picker-modal', ['search_text' => _SEARCH, 'close_text' => _CLOSE]);
    setFoot();
}

function addsave(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $lang = getVar('post', 'lang', 'var');
    $cid = getVar('post', 'cid', 'num', 0);
    $imgcat = strtolower($imgcat);
    if (!preg_match('/^[a-z0-9-]+$/', $imgcat)) $imgcat = '';
    $status = getVar('post', 'status', 'num');
    [$ordern] = $db->getSqlRow($db->getSqlQuery('SELECT ordern FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern DESC', ['modul' => $modul]));
    $ordern++;
    $pview_raw = getVar('post', 'pview[]', 'var', []);
    $pread_raw = getVar('post', 'pread[]', 'var', []);
    $ppost_raw = getVar('post', 'ppost[]', 'var', []);
    $preply_raw = getVar('post', 'preply[]', 'var', []);
    $pedit_raw = getVar('post', 'pedit[]', 'var', []);
    $pdelete_raw = getVar('post', 'pdelete[]', 'var', []);
    $pmod_raw = getVar('post', 'pmod[]', 'var', []);
    $pview = (is_array($pview_raw) && $pview_raw) ? scatacess($pview_raw) : '0|0';
    $pread = (is_array($pread_raw) && $pread_raw) ? scatacess($pread_raw) : '0|0';
    $ppost = (is_array($ppost_raw) && $ppost_raw) ? scatacess($ppost_raw) : '0|0';
    $preply = (is_array($preply_raw) && $preply_raw) ? scatacess($preply_raw) : '0|0';
    $pedit = (is_array($pedit_raw) && $pedit_raw) ? scatacess($pedit_raw) : '3|0';
    $pdelete = (is_array($pdelete_raw) && $pdelete_raw) ? scatacess($pdelete_raw) : '3|0';
    $pmod = (is_array($pmod_raw) && $pmod_raw) ? scatacess($pmod_raw) : '3|0';
    if (!$warn) {
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_categories (id, modul, title, intro, img, lang, parent, status, ordern, pview, pread, ppost, preply, pedit, pdelete, pmod) VALUES (NULL, :modul, :title, :intro, :img, :lang, :parent, :status, :ordern, :pview, :pread, :ppost, :preply, :pedit, :pdelete, :pmod)', [
            'modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $cid, 'status' => $status, 'ordern' => $ordern, 'pview' => $pview, 'pread' => $pread, 'ppost' => $ppost, 'preply' => $preply, 'pedit' => $pedit, 'pdelete' => $pdelete, 'pmod' => $pmod
        ]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function save(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
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
    $pview_raw = getVar('post', 'pview[]', 'var', []);
    $pread_raw = getVar('post', 'pread[]', 'var', []);
    $ppost_raw = getVar('post', 'ppost[]', 'var', []);
    $preply_raw = getVar('post', 'preply[]', 'var', []);
    $pedit_raw = getVar('post', 'pedit[]', 'var', []);
    $pdelete_raw = getVar('post', 'pdelete[]', 'var', []);
    $pmod_raw = getVar('post', 'pmod[]', 'var', []);
    $pview = (is_array($pview_raw) && $pview_raw) ? scatacess($pview_raw) : '0|0';
    $pread = (is_array($pread_raw) && $pread_raw) ? scatacess($pread_raw) : '0|0';
    $ppost = (is_array($ppost_raw) && $ppost_raw) ? scatacess($ppost_raw) : '0|0';
    $preply = (is_array($preply_raw) && $preply_raw) ? scatacess($preply_raw) : '0|0';
    $pedit = (is_array($pedit_raw) && $pedit_raw) ? scatacess($pedit_raw) : '3|0';
    $pdelete = (is_array($pdelete_raw) && $pdelete_raw) ? scatacess($pdelete_raw) : '3|0';
    $pmod = (is_array($pmod_raw) && $pmod_raw) ? scatacess($pmod_raw) : '3|0';
    if (!$warn) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET modul = :modul, title = :title, intro = :intro, img = :img, lang = :lang, parent = :parent, status = :status, pview = :pview, pread = :pread, ppost = :ppost, preply = :preply, pedit = :pedit, pdelete = :pdelete, pmod = :pmod WHERE id = :id', [
            'modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $parent, 'status' => $status, 'pview' => $pview, 'pread' => $pread, 'ppost' => $ppost, 'preply' => $preply, 'pedit' => $pedit, 'pdelete' => $pdelete, 'pmod' => $pmod, 'id' => $id
        ]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function change(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num', 0);
    $modul = getVar('req', 'modul', 'var', '');
    $warn = !checkSiteToken();
    if (!$warn && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET status = :status WHERE id = :id', ['status' => $act ? 0 : 1, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=categories'.($modul ? '&modul='.$modul : ''), false, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $modul = getVar('req', 'modul', 'var', 'forum');
    $warn = !checkSiteToken();
    if (!$warn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE parent = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul, false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&modul='.$modul;
    setTplAdminInfoPage([
        'ops' => ['name=categories'.$modlink, 'name=categories&op=add'.$modlink, 'name=categories&op=subadd'.$modlink, 'name=categories&op=addedit'.$modlink, 'name=categories&op=fix&token='.getSiteToken().$modlink, 'name=categories&op=info'.$modlink],
        'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _DOCS],
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
