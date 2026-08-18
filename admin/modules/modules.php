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
    $typelink = ($mtype !== 2) ? '&type='.$mtype : '';
    $search = $tpl->getHtmlPart('div', [
        'is_searchbox' => true,
        'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'modules'],
            ],
            'content_html' => _TYPE.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'type',
                'options_html' =>
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALL, 'is_selected' => $mtype === 2]).
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _USERS, 'is_selected' => $mtype === 1]).
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _ADMINS, 'is_selected' => $mtype === 0]),
                'select_attr' => 'OnChange="submit()"',
            ]),
        ]),
    ]);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=modules'.$typelink, 'name=modules&op=info'], 'tabs' => [_HOME, _DOCS], 'subtitle_html' => $search]);
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
                    'icon' => 'puzzle',
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
                    'icon' => 'puzzle',
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
        $typel = ($type == 0) ? 'person-gear' : 'people';
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
        $items = [getTplPostAction(['name' => 'modules', 'op' => 'status', 'mod' => $title, 'act' => $active ? '0' : '1', 'type' => $mtype], 'power',
            $active ? _DEACTIVATE : _ACTIVATE), [
            'href' => $afile.'.php?name=modules&op=edit&mod='.$title.'&type='.$mtype,
            'icon_name' => 'pencil',
            'title' => _FULLEDIT,
        ]];
        if (file_exists('modules/'.$title.'/sql/table.sql')) {
            $made = getSqlFileTables((string)file_get_contents('modules/'.$title.'/sql/table.sql'), 'CREATE');
            $install = $made !== [] && checkSqlTable($made[0]);
            $keys = ['name' => 'modules', 'op' => 'add', 'mod' => $title, 'id' => $install ? '1' : '2', 'type' => $mtype];
            $what = $install ? _DB_DELETE : _DB_INSTALL;
            $items[] = getTplPostAction($keys, $install ? 'database-dash' : 'database-add', $what, $what.' "'.$title.'"?');
        }
        if (file_exists('modules/'.$title.'/sql/update.sql')) {
            $items[] = getTplPostAction(['name' => 'modules', 'op' => 'add', 'mod' => $title, 'id' => '3', 'type' => $mtype], 'database-up', _DB_UPDATE,
                _DB_UPDATE.' "'.$title.'"?');
        }
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_col_id' => true, 'content_html' => (string)$a],
                ['is_truncate' => true, 'title_text' => $titlel, 'content_html' => $tpl->getHtmlFrag('bootstrap-icon', ['icon_name' => $typel]).' '.$titlel],
                ['is_truncate' => true, 'title_text' => $title, 'content_html' => $title],
                ['content_html' => $who_view],
                ['content_html' => $group_name],
                ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $items])],
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
    $icon = $conf['modules'][$mod]['icon'] ?? 'puzzle';
    $active = $conf['modules'][$mod]['active'];
    $view = $conf['modules'][$mod]['view'];
    $menu = $conf['modules'][$mod]['menu'];
    $group = $conf['modules'][$mod]['group'];
    $side = $conf['modules'][$mod]['side'];
    $top = $conf['modules'][$mod]['top'];
    $mtype = getVar('req', 'type', 'num', 2);
    $mtype = in_array($mtype, [2, 1, 0], true) ? $mtype : 2;
    $search = $tpl->getHtmlPart('div', [
        'is_searchbox' => true,
        'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'modules'],
            ],
            'content_html' => _TYPE.': '.$tpl->getHtmlFrag('select', [
                'name_attr' => 'type',
                'options_html' =>
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ALL, 'is_selected' => $mtype === 2]).
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _USERS, 'is_selected' => $mtype === 1]).
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _ADMINS, 'is_selected' => $mtype === 0]),
                'select_attr' => 'OnChange="submit()"',
            ]),
        ]),
    ]);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=modules'.($mtype !== 2 ? '&type='.$mtype : ''), 'name=modules&op=info'], 'tabs' => [_HOME, _DOCS], 'subtitle_html' => $search]);
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
        ['label_html' => _ICON, 'field_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'icon', 'value_attr' => $icon, 'placeholder_text' => _ICON, 'button_label' => _ICONPICK])],
        ['label_html' => _STATUS, 'field_html' => getTplRadioGroup(['name' => 'active', 'value' => (string)(int)$active, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VIEWPRIV, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => (int)$view === 0]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS, 'is_selected' => (int)$view === 1]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN, 'is_selected' => (int)$view === 2]),
        ])],
        ['label_html' => _UGROUP, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'group', 'options_html' => $grpopts, 'is_config' => true])],
        ['label_html' => _BLOCKS_MOD, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'side',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _BLOCKS_MOD0, 'is_selected' => (int)$side === 0]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _BLOCKS_MOD1, 'is_selected' => (int)$side === 1]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _BLOCKS_MOD2, 'is_selected' => (int)$side === 2]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _BLOCKS_MOD3, 'is_selected' => (int)$side === 3]),
        ])],
        ['label_html' => _BLOCKS_MOD, 'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'top',
            'is_config' => true,
            'options_html' =>
                $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _BLOCKS_MODC0, 'is_selected' => (int)$top === 0]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _BLOCKS_MODC1, 'is_selected' => (int)$top === 1]).
                $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _BLOCKS_MODC2, 'is_selected' => (int)$top === 2]).
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('modules')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]).$tpl->getHtmlPart('window-icons', ['title_text' => _ICONPICK, 'search_text' => _SEARCH, 'close_text' => _CLOSE]);
    setFoot();
}

function status(): void {
    global $conf, $afile;
    $mod = getVar('post', 'mod', 'var');
    $act = getVar('post', 'act', 'num');
    $type = getVar('post', 'type', 'num', 2);
    $warn = !checkAdminPost('modules');
    if (!$warn && isset($conf['modules'][$mod])) {
        $conf['modules'][$mod]['active'] = $act;
        setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=modules'.($type !== 2 ? '&type='.$type : ''), true, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function save(): void {
    global $conf, $afile;
    $mod = getVar('post', 'mod', 'var');
    $typef = getVar('post', 'type', 'num', 2);
    $warn = !checkAdminPost('modules');
    if (!$warn && isset($conf['modules'][$mod])) {
        $view = getVar('post', 'view', 'num');
        $icon = strtolower(getVar('post', 'icon', 'var'));
        if (!preg_match('/^[a-z0-9-]+$/', $icon)) $icon = 'puzzle';
        $type = $conf['modules'][$mod]['type'] ?? 1;
        $conf['modules'][$mod] = [
            'lang' => getVar('post', 'lang', 'var', '_'.strtoupper($mod)),
            'icon' => $icon,
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
    $mod = getVar('post', 'mod', 'var');
    $id = getVar('post', 'id', 'num');
    $type = getVar('post', 'type', 'num', 2);
    $warn = !checkAdminPost('modules');
    if ($warn) {
        setRedirect($afile.'.php?name=modules'.($type !== 2 ? '&type='.$type : ''), false, 302, _TOKENMISS, true);
        return;
    }
    if ($mod && $id) {
        $file = 'modules/'.$mod.'/sql/'.(($id == 3) ? 'update.sql' : 'table.sql');
        $ttitle = ($id == 1) ? _DB_DELETE : (($id == 2) ? _DB_INSTALL : _DB_UPDATE);
        $lines = [];
        if (!is_file($file)) {
            $lines[] = _ERROR;
        } elseif ($id == 1) {
            foreach (getSqlFileTables((string)file_get_contents($file), 'CREATE') as $name) {
                $done = $db->getSqlQuery('DROP TABLE IF EXISTS `'.$name.'`');
                $lines[] = _TABLE.': '.$name.' - '.($done ? _OK : _ERROR);
            }
        } else {
            $parsed = getSqlbatch(getSqlFilled((string)file_get_contents($file)));
            $queries = ($parsed['error'] === '') ? $parsed['statements'] : [];
            if ($parsed['error'] !== '') $lines[] = $parsed['error'];
            foreach ($queries as $one) {
                $done = $db->getSqlQuery($one);
                $info = getSqlinfo($one);
                $lines[] = ($info['table'] !== '' ? _TABLE.': '.$info['table'] : $info['type']).' - '.($done ? _OK : _ERROR);
            }
        }
        if (!$lines) $lines[] = _NO_INFO;
        $infos = $ttitle.': '.$mod."\n\n".implode("\n", $lines);
    }
    modules();
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=modules', 'name=modules&op=info'],
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
