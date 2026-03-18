<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function modules(): void {
    global $conf, $db, $afile, $infos;
    $mtype = getVar('req', 'type', 'num', 2);
    $mtype = in_array($mtype, [2, 1, 0], true) ? $mtype : 2;
    $typelink = ($mtype !== 2) ? '&amp;type='.$mtype : '';
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => '<form method="post" action="'.$afile.'.php"><input type="hidden" name="name" value="modules">'._TYPE.': <select name="type" OnChange="submit()"><option value="2"'.(($mtype === 2) ? ' selected' : '').'>'._ALL.'</option><option value="1"'.(($mtype === 1) ? ' selected' : '').'>'._USERS.'</option><option value="0"'.(($mtype === 0) ? ' selected' : '').'>'._ADMINS.'</option></select></form>']);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=modules'.$typelink, 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'sub' => $search]);
    if (isset($infos)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $infos]);
    $config = false;
    $modlist = [];
    $new = [];
    $removed = [];
    $handle = opendir('admin/modules');
    while (false !== ($file = readdir($handle))) {
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
    closedir($handle);
    $handle = opendir('modules');
    while (false !== ($file = readdir($handle))) {
        if (!preg_match("/\./", $file) && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) {
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
    closedir($handle);
    $duplicates = array_diff_assoc($modlist, array_unique($modlist));
    if (!empty($duplicates)) {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _MODULES_DUPLICATE.': '.implode(', ', array_unique($duplicates))]);
    }
    if (!empty($new)) {
        $new = array_values(array_unique($new));
        sort($new, SORT_NATURAL | SORT_FLAG_CASE);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MODULES_NEW.': '.implode(', ', $new)]);
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
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MODULES_DELETED.': '.implode(', ', $removed)]);
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
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NAME.'</th><th>'._MODUL.'</th><th>'._VIEW.'</th><th>'._GROUP.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
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
        $titlel = ($menu == 0) ? title_tip(_NO_SICHT).$lang : $lang;
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
                $sqlimg = '||<a href="'.$afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=1" OnClick="return DelCheck(this, \''._DB_DELETE.' &quot;'.$title.'&quot;?\');" title="'._DB_DELETE.'">'._DB_DELETE.'</a>';
            } else {
                $dbc = '<i class="bi bi-database-fill-add"></i> ';
                $sqlimg = '||<a href="'.$afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=2" OnClick="return DelCheck(this, \''._DB_INSTALL.' &quot;'.$title.'&quot;?\');" title="'._DB_INSTALL.'">'._DB_INSTALL.'</a>';
            }
        } else {
            $dbc = '';
            $sqlimg = '';
        }
        if (file_exists('modules/'.$title.'/sql/update.sql')) {
            $dbu = '<i class="bi bi-database-fill-gear bi-green" title="'._DB_UPDATE.'"></i> ';
            $sqluimg = '||<a href="'.$afile.'.php?name=modules&amp;op=add&amp;mod='.$title.'&amp;id=3" OnClick="return DelCheck(this, \''._DB_UPDATE.' &quot;'.$title.'&quot;?\');" title="'._DB_UPDATE.'">'._DB_UPDATE.'</a>';
        } else {
            $dbu = '';
            $sqluimg = '';
        }
        $cont .= '<tr><td>'.$a.'</td><td><i class="bi bi-'.$typel.'"></i> '.$titlel.'</td><td>'.$title.'</td><td>'.$who_view.'</td><td>'.$group_name.'</td><td>'.$dbc.$dbu.'  
        
        
        
        <!-- <i class="bi bi-database" style="font-size:14px; color:#dc3545"></i>
        <i class="bi bi-exclamation-triangle" style="font-size:14px; color:#dc3545"></i>
        
        <i class="bi bi-person-fill-check" style="font-size:18px; color:#dc3545"></i>

        <i class="bi bi-exclamation-square-fill" style="font-size:16px; color:#dc3545"></i>
        <i class="bi bi-info-square-fill" style="font-size:16px; color:green"></i>

        
        
        '.ad_status('', $active).'-->
        
        </td><td>'.add_menu(ad_status($afile.'.php?name=modules&amp;op=status&amp;mod='.$title.'&amp;act='.$act, $active).'||<a href="'.$afile.'.php?name=modules&amp;op=edit&amp;mod='.$title.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'.$sqlimg.$sqluimg).'</td></tr>';
        $a++;
    }

    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function edit(): void {
    global $conf, $db, $afile;
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
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => '<form method="post" action="'.$afile.'.php"><input type="hidden" name="name" value="modules">'._TYPE.': <select name="type" OnChange="submit()"><option value="2"'.(($mtype === 2) ? ' selected' : '').'>'._ALL.'</option><option value="1"'.(($mtype === 1) ? ' selected' : '').'>'._USERS.'</option><option value="0"'.(($mtype === 0) ? ' selected' : '').'>'._ADMINS.'</option></select></form>']);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=modules'.$typelink, 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'sub' => $search]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
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
    $numrow = $db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_groups'));
    if ($numrow > 0) {
        $cont .= '<tr><td>'._UGROUP.':</td><td><select name="group" class="sl_conf">';
        $result2 = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups');
        while ([$gid, $gname] = $db->getSqlRow($result2)) {
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
                $info .= _TABLE.': '.$table[1].' - '._STATUS.': '.(($ident) ? '<span class="sl_green">'._OK.'</span>' : '<span class="sl_red">'._ERROR.'</span>').'<br>';
            }
        }
        $infos = $ttitle.': '.$mod.'<br><br>'.$info;
    }
    modules();
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=modules', 'name=modules&amp;op=info'], 'tabs' => [_HOME, _INFO], 'tab' => 1]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'add': add(); break;
    case 'info': info(); break;
}
