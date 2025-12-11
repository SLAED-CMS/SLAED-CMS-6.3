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
    global $prefix, $db, $aroute, $infos;
    head();
    $cont = navi(0, 0, 0, 0);
    if (isset($infos)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $infos]);
    $handle = opendir('modules');
    $modlist = array();
    while (false !== ($file = readdir($handle))) {
        if (!preg_match("/\./", $file) && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) $modlist[] = $file;
    }
    closedir($handle);
    sort($modlist);
    for ($i = 0; $i < count($modlist); $i++) {
        if ($modlist[$i] != '') {
            list($mid) = $db->sql_fetchrow($db->sql_query('SELECT mid FROM '.$prefix.'_modules WHERE title = :title', ['title' => $modlist[$i]]));
            if (!$mid) $db->sql_query('INSERT INTO '.$prefix.'_modules VALUES (NULL, :title, \'0\', \'0\', \'1\', \'0\', \'0\', \'0\')', ['title' => $modlist[$i]]);
        }
    }
    $result = $db->sql_query('SELECT title FROM '.$prefix.'_modules');
    while (list($title) = $db->sql_fetchrow($result)) {
        $a = 0;
        $handle = opendir('modules');
        while (false !== ($file = readdir($handle))) {
            if ($file == $title && (file_exists('modules/'.$file.'/index.php') || file_exists('modules/'.$file.'/admin/index.php'))) $a = 1;
        }
        closedir($handle);
        if ($a == 0) $db->sql_query('DELETE FROM '.$prefix.'_modules WHERE title = :title', ['title' => $title]);
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NAME.'</th><th>'._MODUL.'</th><th>'._VIEW.'</th><th>'._GROUP.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
    $result = $db->sql_query('SELECT mid, title, active, view, inmenu, mod_group FROM '.$prefix.'_modules ORDER BY title ASC');
    while (list($mid, $title, $active, $view, $inmenu, $mod_group) = $db->sql_fetchrow($result)) {
        $act = ($active) ? '0' : '1';
        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } elseif ($view == 2) {
            $who_view = _MVADMIN;
        }
        $titlel = ($inmenu == 0) ? title_tip(_NO_SICHT).deflmconst($title) : deflmconst($title);
        if ($mod_group != 0) {
            $grp = $db->sql_fetchrow($db->sql_query('SELECT name FROM '.$prefix.'_groups WHERE id = :id', ['id' => $mod_group]));
            $mod_group = $grp['name'];
        } else {
            $mod_group = _NONE;
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
        $cont .= '<tr><td>'.$a.'</td><td>'.$titlel.'</td><td>'.$title.'</td><td>'.$who_view.'</td><td>'.$mod_group.'</td><td>'.ad_status('', $active).'</td><td>'.add_menu(ad_status($aroute.'.php?name=modules&amp;op=status&amp;id='.$mid.'&amp;act='.$act, $active).'||<a href="'.$aroute.'.php?name=modules&amp;op=edit&amp;mid='.$mid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'.$sqlimg.$sqluimg).'</td></tr>';
        $a++;
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function edit(): void {
    global $prefix, $db, $aroute;
    $mid = getVar('get', 'mid', 'num');
    list($view, $inmenu, $mod_group, $blocks_m, $blocks_mc) = $db->sql_fetchrow($db->sql_query('SELECT view, inmenu, mod_group, blocks, blocks_c FROM '.$prefix.'_modules WHERE mid = :mid', ['mid' => $mid]));
    head();
    $cont = navi(0, 0, 0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$aroute.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._VIEWPRIV.'</td><td><select name="view" class="sl_conf">';
    $privs = array(_MVALL, _MVUSERS, _MVADMIN);
    foreach ($privs as $key => $value) {
        $sel = ($view == $key ) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>';
    $numrow = $db->sql_numrows($db->sql_query('SELECT * FROM '.$prefix.'_groups'));
    if ($numrow > 0) {
        $cont .= '<tr><td>'._UGROUP.':</td><td><select name="mod_group" class="sl_conf">';
        $result2 = $db->sql_query('SELECT id, name FROM '.$prefix.'_groups');
        while (list($gid, $gname) = $db->sql_fetchrow($result2)) {
            $gsel = ($gid == $mod_group) ? ' selected' : '';
            if (empty($none)) {
                $ggsel = ($mod_group == 0) ? ' selected' : '';
                $cont .= '<option value="0"'.$ggsel.'>'._NONE.'</option>';
                $none = 1;
            }
            $cont .= '<option value="'.$gid.'"'.$gsel.'>'.$gname.'</option>';
            $gsel = '';
        }
        $cont .= '</select></td></tr>';
    } else {
        $cont .= '<input type="hidden" name="mod_group" value="0">';
    }
    $cont .= '<tr><td>'._BLOCKS_MOD.':</td><td><select name="blocks_m" class="sl_conf">';
    $bmods = array(_BLOCKS_MOD0, _BLOCKS_MOD1, _BLOCKS_MOD2, _BLOCKS_MOD3);
    foreach ($bmods as $key => $value) {
        $sel = ($blocks_m == $key ) ? 'selected' : '';
        $cont .= '<option value="'.$key.'" '.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._BLOCKS_MOD.':</td><td><select name="blocks_mc" class="sl_conf">';
    $bmodcs = array(_BLOCKS_MODC0, _BLOCKS_MODC1, _BLOCKS_MODC2, _BLOCKS_MODC3);
    foreach ($bmodcs as $key => $value) {
        $sel = ($blocks_mc == $key ) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._SHOWINMENU.'</td><td>'.radio_form($inmenu, 'inmenu').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="mid" value="'.$mid.'"><input type="hidden" name="name" value="modules"><input type="hidden" name="op" value="editsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function status(): void {
    global $prefix, $db, $aroute, $act, $id;
    $db->sql_query('UPDATE '.$prefix.'_modules SET active = :act WHERE mid = :id', ['act' => $act, 'id' => $id]);
    header('Location: '.$aroute.'.php?name=modules');
    exit;
}

function save(): void {
    global $prefix, $db, $aroute;
    $mid = getVar('post', 'mid', 'num');
    $view = getVar('post', 'view', 'num');
    $inmenu = getVar('post', 'inmenu', 'num');
    $mod_group = ($view != 1) ? 0 : getVar('post', 'mod_group', 'num');
    $blocks_m = getVar('post', 'blocks_m', 'num');
    $blocks_mc = getVar('post', 'blocks_mc', 'num');
    $db->sql_query('UPDATE '.$prefix.'_modules SET view = :view, inmenu = :inmenu, mod_group = :mod_group, blocks = :blocks_m, blocks_c = :blocks_mc WHERE mid = :mid', [
        'view' => $view, 'inmenu' => $inmenu, 'mod_group' => $mod_group, 'blocks_m' => $blocks_m, 'blocks_mc' => $blocks_mc, 'mid' => $mid
    ]);
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
