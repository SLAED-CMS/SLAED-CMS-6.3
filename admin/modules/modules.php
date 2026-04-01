<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function modules(): void {
    global $conf, $db, $afile, $infos, $tpl;
    $mtype = getVar('req', 'type', 'num', 2);
    $mtype = in_array($mtype, [2, 1, 0], true) ? $mtype : 2;
    $typelink = ($mtype !== 2) ? '&amp;type='.$mtype : '';
    $search = getTplAdminSearchBox($tpl->getHtmlFrag('admin-modules-type-search', [
        'action_url' => $afile.'.php',
        'select_html' => getTplSelect('type',
            getTplOption('2', _ALL, $mtype === 2)
            .getTplOption('1', _USERS, $mtype === 1)
            .getTplOption('0', _ADMINS, $mtype === 0),
            '',
            'OnChange="submit()"'
        ),
        'type_label' => _TYPE,
    ]));
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=modules'.$typelink, 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'sub' => $search]);
    if (isset($infos)) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $infos]);
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
        if ($file !== '.' && $file !== '..' && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) {
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
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _MODULES_DUPLICATE.': '.implode(', ', array_unique($duplicates))]);
    }
    if (!empty($new)) {
        $new = array_values(array_unique($new));
        sort($new, SORT_NATURAL | SORT_FLAG_CASE);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODULES_NEW.': '.implode(', ', $new)]);
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
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODULES_DELETED.': '.implode(', ', $removed)]);
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
    $head = getTplAdminTableHead([_ID, _NAME, _MODUL, _VIEW, _GROUP, [_STATUS, 'nosort'], [_FUNCTIONS, 'nosort']]);
    $rows = '';
    $a = 1;
    foreach ($mods as $title => $mod) {
        $lang = (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']);
        $active = $mod['active'];
        $view = $mod['view'];
        $menu = $mod['menu'];
        $group = $mod['group'];
        $type = $mod['type'];
        $act = $active ? 0 : 1;
        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } elseif ($view == 2) {
            $who_view = _MVADMIN;
        }
        $typel = ($type == 0) ? 'tools' : 'people-fill';
        $titlel = ($menu == 0) ? getTplAdminTitleTip(_NO_SICHT).$lang : $lang;
        if ($group != 0) {
            $grp = $db->getSqlRow($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $group]));
            $group_name = $grp['name'];
        } else {
            $group_name = _NONE;
        }
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
            if ($install) {
                $dbc = '<i class="bi bi-database-fill-dash"></i> ';
                $sqlimg = getTplAdminDeleteAction($afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=1', _DB_DELETE.' "'.$title.'"?', _DB_DELETE, _DB_DELETE);
            } else {
                $dbc = '<i class="bi bi-database-fill-add"></i> ';
                $sqlimg = getTplAdminDeleteAction($afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=2', _DB_INSTALL.' "'.$title.'"?', _DB_INSTALL, _DB_INSTALL);
            }
        } else {
            $dbc = '';
            $sqlimg = '';
        }
        if (file_exists('modules/'.$title.'/sql/update.sql')) {
            $dbu = '<i class="bi bi-database-fill-gear bi-green" title="'._DB_UPDATE.'"></i> ';
            $sqluimg = getTplAdminDeleteAction($afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=3', _DB_UPDATE.' "'.$title.'"?', _DB_UPDATE, _DB_UPDATE);
        } else {
            $dbu = '';
            $sqluimg = '';
        }
        $acts = getTplAdminActionMenu([
            ad_status($afile.'.php?name=modules&amp;op=status&amp;mod='.$title.'&amp;act='.$act, $active),
            getTplLinkAction($afile.'.php?name=modules&amp;op=edit&amp;mod='.$title, _FULLEDIT, _FULLEDIT),
            $sqlimg,
            $sqluimg,
        ]);
        $cols = $tpl->getHtmlFrag('admin-modules-list-row', [
            'actions_html' => $acts,
            'db_html' => $dbc.$dbu,
            'group_label' => $group_name,
            'icon_name' => $typel,
            'id_value' => (string)$a,
            'module_name' => $title,
            'title_html' => $titlel,
            'view_label' => $who_view,
        ]);
        $rows .= getTplAdminTableRow($cols);
        $a++;
    }

    $cont .= getTplAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function edit(): void {
    global $conf, $db, $afile, $tpl;
    $mod = getVar('get', 'mod', 'var');
    if (!isset($conf['modules'][$mod])) setRedirect($afile.'.php?name=modules');
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
    $typelink = ($mtype !== 2) ? '&amp;type='.$mtype : '';
    $search = getTplAdminSearchBox($tpl->getHtmlFrag('admin-modules-type-search', [
        'action_url' => $afile.'.php',
        'select_html' => getTplSelect('type',
            getTplOption('2', _ALL, $mtype === 2)
            .getTplOption('1', _USERS, $mtype === 1)
            .getTplOption('0', _ADMINS, $mtype === 0),
            '',
            'OnChange="submit()"'
        ),
        'type_label' => _TYPE,
    ]));
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=modules'.$typelink, 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'sub' => $search]);
    $hide = getTplHiddenInput('mod', $mod).getTplHiddenInput('name', 'modules').getTplHiddenInput('op', 'save');
    $rows = '';
    $rows .= getTplAdminFormRow(_LANGUAGE.':', getTplTextInput('lang', $lang, 'sl_conf', 'maxlength="50" placeholder="'._LANGUAGE.'"'));
    $path = 'templates/admin/images/admin/';
    $entries = is_dir($path) ? scandir($path) : [];
    $pickopts = '';
    foreach ($entries as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
            $pickopts .= getTplOption($path.$entry, $entry, $img == $entry);
        }
    }
    $rows .= getTplAdminFormRow(_LOGO.':', getTplSelect('img', $pickopts, 'sl_conf', 'id="img_replace"'));
    $rows .= getTplAdminFormRow(_PREVIEW.':', getTplImagePreview($path.$img, _LOGO));
    $rows .= getTplAdminFormRow(_STATUS.':', radio_form($active, 'active'));
    $privopts = '';
    foreach ([_MVALL, _MVUSERS, _MVADMIN] as $key => $value) {
        $privopts .= getTplOption((string)$key, $value, $view == $key);
    }
    $rows .= getTplAdminFormRow(_VIEWPRIV, getTplSelect('view', $privopts, 'sl_conf'));
    $numrow = $db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_groups'));
    if ($numrow > 0) {
        $grpopts = '';
        $result2 = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups');
        $first = true;
        while ([$gid, $gname] = $db->getSqlRow($result2)) {
            if ($first) {
                $grpopts .= getTplOption('0', _NONE, $group == 0);
                $first = false;
            }
            $grpopts .= getTplOption((string)$gid, $gname, $gid == $group);
        }
        $rows .= getTplAdminFormRow(_UGROUP.':', getTplSelect('group', $grpopts, 'sl_conf'));
    } else {
        $hide .= getTplHiddenInput('group', '0');
    }
    $sideopts = '';
    foreach ([_BLOCKS_MOD0, _BLOCKS_MOD1, _BLOCKS_MOD2, _BLOCKS_MOD3] as $key => $value) {
        $sideopts .= getTplOption((string)$key, $value, $side == $key);
    }
    $rows .= getTplAdminFormRow(_BLOCKS_MOD.':', getTplSelect('side', $sideopts, 'sl_conf'));
    $topopts = '';
    foreach ([_BLOCKS_MODC0, _BLOCKS_MODC1, _BLOCKS_MODC2, _BLOCKS_MODC3] as $key => $value) {
        $topopts .= getTplOption((string)$key, $value, $top == $key);
    }
    $rows .= getTplAdminFormRow(_BLOCKS_MOD.':', getTplSelect('top', $topopts, 'sl_conf'));
    $rows .= getTplAdminFormRow(_SHOWINMENU, radio_form($menu, 'menu'));
    $rows .= getTplAdminFormWide(getTplAdminSubmitButton(_SAVECHANGES), '', 'sl_center');
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide, 'sl_table_conf');
    echo $cont;
    setFoot();
}

function status(): void {
    global $conf, $afile;
    $mod = getVar('get', 'mod', 'var');
    $act = getVar('get', 'act', 'num');
    if (isset($conf['modules'][$mod])) {
        $conf['modules'][$mod]['active'] = $act;
        setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=modules');
}

function save(): void {
    global $conf, $afile;
    $mod = getVar('post', 'mod', 'var');
    if (isset($conf['modules'][$mod])) {
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
    setRedirect($afile.'.php?name=modules');
}

function add(): void {
    global $db, $id, $infos;
    $mod = getVar('get', 'mod', 'var');
    if ($mod && $id) {
        $filename = ($id == 3) ? file_get_contents('modules/'.$mod.'/sql/update.sql') : file_get_contents('modules/'.$mod.'/sql/table.sql');
        if ($id == 1) {
            $ttitle = _DB_DELETE;
        } elseif ($id == 2) {
            $ttitle = _DB_INSTALL;
        } elseif ($id == 3) {
            $ttitle = _DB_UPDATE;
        }
        $stringdump = explode(';', $filename);
        for ($i = 0; $i < count($stringdump); $i++) {
            $string = str_replace('{prefix}', PREFIX_DB, $stringdump[$i]);
            if ($id != 1) $ident = $db->getSqlQuery(stripslashes($string));
            if (preg_match('/CREATE|ALTER|DELETE|DROP|UPDATE/i', $string)) {
                $table = explode('`', $string);
                if ($id == 1) $ident = $db->getSqlQuery('DROP TABLE '.$table[1]);
                $info .= getTplAdminInfoLine(_TABLE.': '.$table[1].' - '._STATUS, getTplAdminStatusBadge((bool)$ident, _OK, _ERROR));
            }
        }
        $infos = $ttitle.': '.$mod.'<br><br>'.$info;
    }
    modules();
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=modules', 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'tab' => 1]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'add': add(); break;
    case 'info': info(); break;
}
