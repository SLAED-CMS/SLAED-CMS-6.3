<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function modules(): void {
    global $conf, $db, $afile, $infos, $tpl;
    $mtype = getVar('req', 'type', 'num', 2);
    $mtype = in_array($mtype, [2, 1, 0], true) ? $mtype : 2;
    $typelink = ($mtype !== 2) ? '&amp;type='.$mtype : '';
    $search = $tpl->getHtmlPart('searchbox', [
        'searchbox' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'modules'],
            ],
            'content_html' => _TYPE.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'type',
                'options_html' =>
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALL, 'is_selected' => $mtype === 2]) .
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _USERS, 'is_selected' => $mtype === 1]) .
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _ADMINS, 'is_selected' => $mtype === 0]),
                'select_attr' => 'OnChange="submit()"',
            ]),
        ]),
    ]);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=modules'.$typelink, 'name=modules&amp;op=info'], 'tabs' => [_HOME, _DOCS], 'subtitle_html' => $search]);
    if (!empty($infos)) $cont .= $tpl->getHtmlFrag('alert', ['text' => $infos]);
    $config = false;
    $modlist = [];
    $new = [];
    $removed = [];
    foreach (scandir('admin/modules') as $file) {
        if (preg_match('/^([a-z_]+)\.php$/i', $file, $matches)) {
            $module = $matches[1];
            $modlist[] = $module;
            if (!isset($conf['modules'][$module])) {
                $conf['modules'][$module] = [
                    'lang' => '_'.strtoupper($module),
                    'img' => strtolower($module).'.png',
                    'active' => 1,
                    'view' => 0,
                    'menu' => 1,
                    'group' => 0,
                    'side' => 0,
                    'top' => 0,
                    'type' => 0,
                ];
                $config = true;
                $new[] = $module;
            }
        }
    }
    foreach (scandir('modules') as $file) {
        if ($file !== '.' && $file !== '..' && is_dir('modules/'.$file) && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) {
            $modlist[] = $file;
            if (!isset($conf['modules'][$file])) {
                $conf['modules'][$file] = [
                    'lang' => '_'.strtoupper($file),
                    'img' => strtolower($file).'.png',
                    'active' => 0,
                    'view' => 0,
                    'menu' => 1,
                    'group' => 0,
                    'side' => 0,
                    'top' => 0,
                    'type' => 1,
                ];
                $config = true;
                $new[] = $file;
            }
        }
    }
    $duplicates = array_diff_assoc($modlist, array_unique($modlist));
    if (!empty($duplicates)) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _MODULES_DUPLICATE.': '.implode(', ', array_unique($duplicates))]);
    }
    if (!empty($new)) {
        $new = array_values(array_unique($new));
        sort($new, SORT_NATURAL | SORT_FLAG_CASE);
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _MODULES_NEW.': '.implode(', ', $new)]);
    }
    foreach (array_keys($conf['modules']) as $module) {
        if (!in_array($module, $modlist, true)) {
            unset($conf['modules'][$module]);
            $removed[] = $module;
            $config = true;
        }
    }
    if (!empty($removed)) {
        $removed = array_values(array_unique($removed));
        sort($removed, SORT_NATURAL | SORT_FLAG_CASE);
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _MODULES_DELETED.': '.implode(', ', $removed)]);
    }
    if ($config) setConfigFile('modules.php', $conf['modules']);
    $mods = [];
    foreach ($conf['modules'] as $mname => $mdata) {
        $type = (int)($mdata['type'] ?? 1);
        if ($mtype === 2 || $mtype === $type) {
            $mods[$mname] = $mdata;
        }
    }
    uksort($mods, function ($a, $b) use (&$mods) {
        $ta = (int)($mods[$a]['type'] ?? 1);
        $tb = (int)($mods[$b]['type'] ?? 1);
        if ($ta === $tb) return strnatcasecmp($a, $b);
        return $ta <=> $tb;
    });
    $rows = [];
    $a = 1;
    foreach ($mods as $title => $mod) {
        $lang = (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']);
        $active = $mod['active'];
        $view = $mod['view'];
        $menu = $mod['menu'];
        $group = $mod['group'];
        $type = $mod['type'];
        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } else {
            $who_view = _MVADMIN;
        }
        $typel = ($type == 0) ? 'tools' : 'people-fill';
        $titlel = ($menu == 0)
            ? $tpl->getHtmlFrag('popover', [
                'items' => [
                    ['label' => _DOCS, 'value' => _NO_SICHT, 'is_last' => true],
                ],
                'label_text' => $lang,
                'title_text' => $lang,
            ])
            : $lang;
        if ($group != 0) {
            $grp = $db->getSqlRow($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $group]));
            $group_name = $grp['name'];
        } else {
            $group_name = _NONE;
        }
        $items = [[
            'href' => $afile.'.php?name=modules&amp;op=status&amp;mod='.$title.'&amp;act='.($active ? '0' : '1').'&amp;type='.$mtype.'&amp;token='.getSiteToken(),
            'label' => $active ? _DEACTIVATE : _ACTIVATE,
            'title' => $active ? _DEACTIVATE : _ACTIVATE,
        ], [
            'href' => $afile.'.php?name=modules&amp;op=edit&amp;mod='.$title.'&amp;type='.$mtype,
            'label' => _FULLEDIT,
            'title' => _FULLEDIT,
        ]];
        if (file_exists('modules/'.$title.'/sql/table.sql')) {
            $filename = file_get_contents('modules/'.$title.'/sql/table.sql');
            $stringdump = explode(';', $filename);
            $install = '';
            for ($i = 0; $i < count($stringdump); $i++) {
                $string = str_replace('{prefix}', PREFIX_DB, $stringdump[$i]);
                if (preg_match('/CREATE|ALTER|DELETE|DROP|UPDATE/i', $string)) {
                    $table = explode('`', $string);
                    $install = $db->getSqlRow($db->getSqlQuery('SELECT Count(*) FROM '.$table[1]));
                }
            }
            $items[] = [
                'href' => $afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id='.($install ? '1' : '2').'&amp;type='.$mtype.'&amp;token='.getSiteToken(),
                'label' => $install ? _DB_DELETE : _DB_INSTALL,
                'title' => $install ? _DB_DELETE : _DB_INSTALL,
                'onclick_attr' => 'OnClick="return DelCheck(this, \''.htmlspecialchars(($install ? _DB_DELETE : _DB_INSTALL).' &quot;'.$title.'&quot;?', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'\');"',
            ];
        }
        if (file_exists('modules/'.$title.'/sql/update.sql')) {
            $items[] = [
                'href' => $afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=3&amp;type='.$mtype.'&amp;token='.getSiteToken(),
                'label' => _DB_UPDATE,
                'title' => _DB_UPDATE,
                'onclick_attr' => 'OnClick="return DelCheck(this, \''.htmlspecialchars(_DB_UPDATE.' &quot;'.$title.'&quot;?', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'\');"',
            ];
        }
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_col_id' => true, 'content_html' => (string)$a],
                ['is_truncate' => true, 'title_text' => $titlel, 'content_html' => $tpl->getHtmlFrag('bootstrap-icon', ['icon_name' => $typel]).' '.$titlel],
                ['is_truncate' => true, 'title_text' => $title, 'content_html' => $title],
                ['content_html' => $who_view],
                ['content_html' => $group_name],
                ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
            ],
        ])]);
        $a++;
    }
    $cont .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _NAME, 'is_truncate' => true],
            ['content' => _MODUL, 'is_truncate' => true],
            ['content' => _VIEW],
            ['content' => _GROUP],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
    ]);
    echo $cont;
    setFoot();
}

function edit(): void {
    global $conf, $db, $afile, $tpl;
    $mod = getVar('get', 'mod', 'var');
    if (!isset($conf['modules'][$mod])) {
        setRedirect($afile.'.php?name=modules', false, 302, _NO_INFO, true);
        return;
    }
    $lang = $conf['modules'][$mod]['lang'] ?? '_'.strtoupper($mod);
    $img = $conf['modules'][$mod]['img'] ?? $mod.'.png';
    $active = $conf['modules'][$mod]['active'];
    $view = $conf['modules'][$mod]['view'];
    $menu = $conf['modules'][$mod]['menu'];
    $group = $conf['modules'][$mod]['group'];
    $side = $conf['modules'][$mod]['side'];
    $top = $conf['modules'][$mod]['top'];
    $mtype = getVar('req', 'type', 'num', 2);
    $mtype = in_array($mtype, [2, 1, 0], true) ? $mtype : 2;
    $search = $tpl->getHtmlPart('searchbox', [
        'searchbox' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'modules'],
            ],
            'content_html' => _TYPE.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'type',
                'options_html' =>
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALL, 'is_selected' => $mtype === 2]) .
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _USERS, 'is_selected' => $mtype === 1]) .
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _ADMINS, 'is_selected' => $mtype === 0]),
                'select_attr' => 'OnChange="submit()"',
            ]),
        ]),
    ]);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=modules'.($mtype !== 2 ? '&amp;type='.$mtype : ''), 'name=modules&amp;op=info'], 'tabs' => [_HOME, _DOCS], 'subtitle_html' => $search]);
    $path = 'templates/admin/images/admin/';
    $entries = is_dir($path) ? scandir($path) : [];
    $pickopts = '';
    foreach ($entries as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
            $pickopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $path.$entry, 'label_text' => $entry, 'is_selected' => $img == $entry]);
        }
    }
    $grpopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NONE, 'is_selected' => (int)$group === 0]);
    $numrow = $db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_groups'));
    if ($numrow > 0) {
        $result2 = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups');
        while ([$gid, $gname] = $db->getSqlRow($result2)) {
            $grpopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$gid, 'label_text' => $gname, 'is_selected' => (int)$gid === (int)$group]);
        }
    }
    $rows = [
        ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'lang', 'value_attr' => $lang, 'maxlength_num' => 50, 'placeholder_text' => _LANGUAGE, 'is_config' => true])],
        ['label_html' => _LOGO, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'img', 'options_html' => $pickopts, 'is_config' => true, 'select_attr' => 'id="img_replace"'])],
        ['label_html' => _PREVIEW, 'field_html' => $tpl->getHtmlFrag('image-preview', ['src_attr' => $path.$img, 'image_id' => 'picture', 'alt_text' => _LOGO])],
        ['label_html' => _STATUS, 'field_html' => getTplRadioGroup(['name' => 'active', 'value' => (string)(int)$active, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VIEWPRIV, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => (int)$view === 0]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS, 'is_selected' => (int)$view === 1]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN, 'is_selected' => (int)$view === 2]),
        ])],
        ['label_html' => _UGROUP, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'group', 'options_html' => $grpopts, 'is_config' => true])],
        ['label_html' => _BLOCKS_MOD, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'side',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _BLOCKS_MOD0, 'is_selected' => (int)$side === 0]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _BLOCKS_MOD1, 'is_selected' => (int)$side === 1]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _BLOCKS_MOD2, 'is_selected' => (int)$side === 2]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _BLOCKS_MOD3, 'is_selected' => (int)$side === 3]),
        ])],
        ['label_html' => _BLOCKS_MOD, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'top',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _BLOCKS_MODC0, 'is_selected' => (int)$top === 0]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _BLOCKS_MODC1, 'is_selected' => (int)$top === 1]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _BLOCKS_MODC2, 'is_selected' => (int)$top === 2]) .
                $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _BLOCKS_MODC3, 'is_selected' => (int)$top === 3]),
        ])],
        ['label_html' => _SHOWINMENU, 'field_html' => getTplRadioGroup(['name' => 'menu', 'value' => (string)(int)$menu, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'mod', 'valueattr' => $mod],
            ['nameattr' => 'name', 'valueattr' => 'modules'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'type', 'valueattr' => (string)$mtype],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function status(): void {
    global $conf, $afile;
    $mod = getVar('get', 'mod', 'var');
    $act = getVar('get', 'act', 'num');
    $type = getVar('get', 'type', 'num', 2);
    $warn = !checkSiteToken();
    if (!$warn && isset($conf['modules'][$mod])) {
        $conf['modules'][$mod]['active'] = $act;
        setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=modules'.($type !== 2 ? '&type='.$type : ''), false, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function save(): void {
    global $conf, $afile;
    $mod = getVar('post', 'mod', 'var');
    $typef = getVar('post', 'type', 'num', 2);
    $warn = !checkSiteToken();
    if (!$warn && isset($conf['modules'][$mod])) {
        $view = getVar('post', 'view', 'num');
        $img = str_replace('templates/admin/images/admin/', '', getVar('post', 'img', 'text'));
        $type = $conf['modules'][$mod]['type'] ?? 1;
        $conf['modules'][$mod] = [
            'lang' => getVar('post', 'lang', 'var', '_'.strtoupper($mod)),
            'img' => $img ?: strtolower($mod).'.png',
            'active' => getVar('post', 'active', 'num'),
            'view' => $view,
            'menu' => getVar('post', 'menu', 'num'),
            'group' => ($view == 1) ? getVar('post', 'group', 'num') : 0,
            'side' => getVar('post', 'side', 'num'),
            'top' => getVar('post', 'top', 'num'),
            'type' => $type,
        ];
        setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=modules'.($typef !== 2 ? '&type='.$typef : ''), false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function add(): void {
    global $db, $infos, $afile;
    $mod = getVar('get', 'mod', 'var');
    $id = getVar('get', 'id', 'num');
    $type = getVar('get', 'type', 'num', 2);
    $warn = !checkSiteToken();
    if ($warn) {
        setRedirect($afile.'.php?name=modules'.($type !== 2 ? '&type='.$type : ''), false, 302, _TOKENMISS, true);
        return;
    }
    if ($mod && $id) {
        $filename = ($id == 3) ? file_get_contents('modules/'.$mod.'/sql/update.sql') : file_get_contents('modules/'.$mod.'/sql/table.sql');
        if ($id == 1) {
            $ttitle = _DB_DELETE;
        } elseif ($id == 2) {
            $ttitle = _DB_INSTALL;
        } else {
            $ttitle = _DB_UPDATE;
        }
        $stringdump = explode(';', $filename);
        $lines = [];
        for ($i = 0; $i < count($stringdump); $i++) {
            $string = str_replace('{prefix}', PREFIX_DB, $stringdump[$i]);
            if ($id != 1) $ident = $db->getSqlQuery(stripslashes($string));
            if (preg_match('/CREATE|ALTER|DELETE|DROP|UPDATE/i', $string)) {
                $table = explode('`', $string);
                if ($id == 1) $ident = $db->getSqlQuery('DROP TABLE '.$table[1]);
                $lines[] = _TABLE.': '.$table[1].' - '.((bool)$ident ? _OK : _ERROR);
            }
        }
        $infos = $ttitle.': '.$mod.PHP_EOL.PHP_EOL.implode(PHP_EOL, $lines);
    }
    modules();
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=modules', 'name=modules&amp;op=info'],
        'tabs' => [_HOME, _DOCS],
    ]);
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'add': add(); break;
    case 'info': info(); break;
}
