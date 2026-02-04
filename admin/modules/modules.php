<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=modules', 'name=modules&amp;op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs(_MODULES, 'modules.png', '', $ops, $lang, [], [], $tab, (bool)$subtab);
}

function modules(): void {
    global $confmd, $prefix, $db, $aroute, $infos;
    head();
    $cont = navi(0, 0, 0, 0);
    if (isset($infos)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $infos]);

    $config = false;
    $modlist = [];

    // Scan admin modules (admin/modules/*.php) - type 0
    $handle = opendir('admin/modules');
    while (false !== ($file = readdir($handle))) {
        if (preg_match('/^([a-z_]+)\.php$/i', $file, $matches)) {
            $module = $matches[1];
            $modlist[] = $module;
            if (!isset($confmd[$module])) {
                $confmd[$module] = [
                    'lang'   => '_'.strtoupper($module),
                    'img'    => strtolower($module).'.png',
                    'active' => 1,
                    'view'   => 0,
                    'menu'   => 1,
                    'group'  => 0,
                    'side'   => 0,
                    'top'    => 0,
                    'type'   => 0,
                ];
                $config = true;
            }
        }
    }
    closedir($handle);

    // Scan custom modules (modules/*/) - type 1
    $handle = opendir('modules');
    while (false !== ($file = readdir($handle))) {
        if (!preg_match("/\./", $file) && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) {
            $modlist[] = $file;
            if (!isset($confmd[$file])) {
                $confmd[$file] = [
                    'lang'   => '_'.strtoupper($file),
                    'img'    => strtolower($file).'.png',
                    'active' => 0,
                    'view'   => 0,
                    'menu'   => 1,
                    'group'  => 0,
                    'side'   => 0,
                    'top'    => 0,
                    'type'   => 1,
                ];
                $config = true;
            }
        }
    }
    closedir($handle);

    // Check for duplicate module names
    $duplicates = array_diff_assoc($modlist, array_unique($modlist));
    if (!empty($duplicates)) {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'duplicates', 'text' => _MODULES_DUPLICATE.': '.implode(', ', array_unique($duplicates))]);
    }

    // Remove modules from config that no longer exist in filesystem
    foreach (array_keys($confmd) as $module) {
        if (!in_array($module, $modlist)) {
            unset($confmd[$module]);
            $config = true;
        }
    }

    // Save config if changed
    if ($config) {
        setConfigFile('modules.php', 'confmd', $confmd);
    }

    // Sort modules alphabetically
    ksort($confmd);

    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NAME.'</th><th>'._MODUL.'</th><th>'._VIEW.'</th><th>'._GROUP.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';

    $a = 1;
    foreach ($confmd as $title => $mod) {
        $lang = (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']);
        $active = $mod['active'];
        $view = $mod['view'];
        $menu = $mod['menu'];
        $group = $mod['group'];

        $act = $active ? 0 : 1;

        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } elseif ($view == 2) {
            $who_view = _MVADMIN;
        }

        $titlel = ($menu == 0) ? title_tip(_NO_SICHT).$lang : $lang;
        

        if ($group != 0) {
            $grp = $db->sql_fetchrow($db->sql_query('SELECT name FROM '.$prefix.'_groups WHERE id = :id', ['id' => $group]));
            $group_name = $grp['name'];
        } else {
            $group_name = _NONE;
        }

        if (file_exists('modules/'.$title.'/sql/table.sql')) {
            $filename = file_get_contents('modules/'.$title.'/sql/table.sql');
            $stringdump = explode(';', $filename);
            $install = '';
            for ($i = 0; $i < count($stringdump); $i++) {
                $string = str_replace('{prefix}', $prefix, $stringdump[$i]);
                if (preg_match('/CREATE|ALTER|DELETE|DROP|UPDATE/i', $string)) {
                    $table = explode('`', $string);
                    $install = $db->sql_fetchrow($db->sql_query('SELECT Count(*) FROM '.$table[1]));
                }
            }
            if ($install) {
                $sqlimg = '||<a href="'.$aroute.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=1" OnClick="return DelCheck(this, \''._DB_DELETE.' &quot;'.$title.'&quot;?\');" title="'._DB_DELETE.'">'._DB_DELETE.'</a>';
            } else {
                $sqlimg = '||<a href="'.$aroute.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=2" OnClick="return DelCheck(this, \''._DB_INSTALL.' &quot;'.$title.'&quot;?\');" title="'._DB_INSTALL.'">'._DB_INSTALL.'</a>';
            }
        } else {
            $sqlimg = '';
        }

        if (file_exists('modules/'.$title.'/sql/update.sql')) {
            $sqluimg = '||<a href="'.$aroute.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=3" OnClick="return DelCheck(this, \''._DB_UPDATE.' &quot;'.$title.'&quot;?\');" title="'._DB_UPDATE.'">'._DB_UPDATE.'</a>';
        } else {
            $sqluimg = '';
        }

        $cont .= '<tr><td>'.$a.'</td><td>'.$titlel.'</td><td>'.$title.'</td><td>'.$who_view.'</td><td>'.$group_name.'</td><td>'.ad_status('', $active).'</td><td>'.add_menu(ad_status($aroute.'.php?name=modules&amp;op=status&amp;mod='.$title.'&amp;act='.$act, $active).'||<a href="'.$aroute.'.php?name=modules&amp;op=edit&amp;mod='.$title.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'.$sqlimg.$sqluimg).'</td></tr>';
        $a++;
    }

    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function edit(): void {
    global $confmd, $prefix, $db, $aroute;
    $mod = getVar('get', 'mod', 'var');
    if (!isset($confmd[$mod])) {
        header('Location: '.$aroute.'.php?name=modules');
        exit;
    }
    $lang = $confmd[$mod]['lang'] ?? '_'.strtoupper($mod);
    $img = $confmd[$mod]['img'] ?? $mod.'.png';
    $active = $confmd[$mod]['active'];
    $view = $confmd[$mod]['view'];
    $menu = $confmd[$mod]['menu'];
    $group = $confmd[$mod]['group'];
    $side = $confmd[$mod]['side'];
    $top = $confmd[$mod]['top'];
    head();
    $cont = navi(0, 0, 0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$aroute.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._LANGUAGE.':</td><td><input type="text" name="lang" value="'.$lang.'" maxlength="50" class="sl_conf" placeholder="'._LANGUAGE.'"></td></tr>'
    .'<tr><td>'._LOGO.':</td><td><select name="img" id="img_replace" class="sl_conf">';
    $path = 'templates/admin/images/admin/';
    $entries = is_dir($path) ? scandir($path) : [];
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $sel = ($img == $entry) ? ' selected' : '';
                $cont .= '<option value="'.$path.$entry.'"'.$sel.'>'.$entry.'</option>';
            }
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._PREVIEW.':</td><td><img src="'.$path.$img.'" id="picture" alt="'._LOGO.'"></td></tr>'
    .'<tr><td>'._STATUS.':</td><td>'.radio_form($active, 'active').'</td></tr>'
    .'<tr><td>'._VIEWPRIV.'</td><td><select name="view" id="img_replace" class="sl_conf">';
    $privs = [_MVALL, _MVUSERS, _MVADMIN];
    foreach ($privs as $key => $value) {
        $sel = ($view == $key) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>';
    $numrow = $db->sql_numrows($db->sql_query('SELECT * FROM '.$prefix.'_groups'));
    if ($numrow > 0) {
        $cont .= '<tr><td>'._UGROUP.':</td><td><select name="group" class="sl_conf">';
        $result2 = $db->sql_query('SELECT id, name FROM '.$prefix.'_groups');
        while (list($gid, $gname) = $db->sql_fetchrow($result2)) {
            $gsel = ($gid == $group) ? ' selected' : '';
            if (empty($none)) {
                $ggsel = ($group == 0) ? ' selected' : '';
                $cont .= '<option value="0"'.$ggsel.'>'._NONE.'</option>';
                $none = 1;
            }
            $cont .= '<option value="'.$gid.'"'.$gsel.'>'.$gname.'</option>';
            $gsel = '';
        }
        $cont .= '</select></td></tr>';
    } else {
        $cont .= '<input type="hidden" name="group" value="0">';
    }
    $cont .= '<tr><td>'._BLOCKS_MOD.':</td><td><select name="side" class="sl_conf">';
    $bmods = [_BLOCKS_MOD0, _BLOCKS_MOD1, _BLOCKS_MOD2, _BLOCKS_MOD3];
    foreach ($bmods as $key => $value) {
        $sel = ($side == $key) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._BLOCKS_MOD.':</td><td><select name="top" class="sl_conf">';
    $bmodcs = [_BLOCKS_MODC0, _BLOCKS_MODC1, _BLOCKS_MODC2, _BLOCKS_MODC3];
    foreach ($bmodcs as $key => $value) {
        $sel = ($top == $key) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._SHOWINMENU.'</td><td>'.radio_form($menu, 'menu').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="mod" value="'.$mod.'"><input type="hidden" name="name" value="modules"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function status(): void {
    global $confmd, $aroute;
    $mod = getVar('get', 'mod', 'var');
    $act = getVar('get', 'act', 'num');
    if (isset($confmd[$mod])) {
        $confmd[$mod]['active'] = $act;
        setConfigFile('modules.php', 'confmd', $confmd);
    }
    header('Location: '.$aroute.'.php?name=modules');
    exit;
}

function save(): void {
    global $confmd, $aroute;
    $mod = getVar('post', 'mod', 'var');
    if (isset($confmd[$mod])) {
        $view = getVar('post', 'view', 'num');
        $img = str_replace('templates/admin/images/admin/', '', getVar('post', 'img', 'text'));
        $type = $confmd[$mod]['type'] ?? 1;
        $confmd[$mod] = [
            'lang'   => getVar('post', 'lang', 'var') ?: '_'.strtoupper($mod),
            'img'    => $img ?: strtolower($mod).'.png',
            'active' => getVar('post', 'active', 'num'),
            'view'   => $view,
            'menu'   => getVar('post', 'menu', 'num'),
            'group'  => ($view == 1) ? getVar('post', 'group', 'num') : 0,
            'side'   => getVar('post', 'side', 'num'),
            'top'    => getVar('post', 'top', 'num'),
            'type'   => $type,
        ];
        setConfigFile('modules.php', 'confmd', $confmd);
    }
    header('Location: '.$aroute.'.php?name=modules');
    exit;
}

function add(): void {
    global $prefix, $db, $id, $infos;
    $module = getVar('get', 'mod', 'var');
    if ($module && $id) {
        $filename = ($id == 3) ? file_get_contents('modules/'.$module.'/sql/update.sql') : file_get_contents('modules/'.$module.'/sql/table.sql');
        if ($id == 1) {
            $ttitle = _DB_DELETE;
        } elseif ($id == 2) {
            $ttitle = _DB_INSTALL;
        } elseif ($id == 3) {
            $ttitle = _DB_UPDATE;
        }
        $stringdump = explode(';', $filename);
        for ($i = 0; $i < count($stringdump); $i++) {
            $string = str_replace('{prefix}', $prefix, $stringdump[$i]);
            if ($id != 1) $ident = $db->sql_query(stripslashes($string));
            if (preg_match('/CREATE|ALTER|DELETE|DROP|UPDATE/i', $string)) {
                $table = explode('`', $string);
                if ($id == 1) $ident = $db->sql_query('DROP TABLE '.$table[1]);
                $info .= _TABLE.': '.$table[1].' - '._STATUS.': '.(($ident) ? '<span class="sl_green">'._OK.'</span>' : '<span class="sl_red">'._ERROR.'</span>').'<br>';
            }
        }
        $infos = $ttitle.': '.$module.'<br><br>'.$info;
    }
    modules();
}

function info(): void {
    head();
    echo navi(0, 1, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'modules').'</div>';
    foot();
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'add': add(); break;
    case 'info': info(); break;
}
