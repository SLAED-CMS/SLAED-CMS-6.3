<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $tab = 0, int $subtab = 0): string {
    $ops = ['name=messages', 'name=messages&amp;op=add', 'name=messages&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs(_MESSAGES, 'messages.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function messages(): void {
    global $prefix, $db, $conf, $aroute;
    head();
    $cont = navi(0, 0);
    $result = $db->sql_query('SELECT mid, title, content, expire, active, view, mlanguage FROM ' . $prefix . '_message ORDER BY mid');
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._PURCHASED.'</th><th>'._VIEW.'</th><th>'._LANGUAGE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while (list($mid, $title, $content, $expire, $active, $view, $mlanguage) = $db->sql_fetchrow($result)) {
            if (($expire && $expire < time()) || (!$active && $expire)) $db->sql_query('UPDATE ' . $prefix . '_message SET active = :active, expire = :expire WHERE mid = :mid', ['active' => 0, 'expire' => 0, 'mid' => $mid]);
            $act = ($active) ? '0' : '1';
            if ($view == 1) {
                $mview = _MVALL;
            } elseif ($view == 2) {
                $mview = _MVANON;
            } elseif ($view == 3) {
                $mview = _MVUSERS;
            } elseif ($view == 4) {
                $mview = _MVADMIN;
            }
            $mlanguage = (!$mlanguage) ? _ALL : $mlanguage;
            $exp = intval($expire - time());
            $exp = ($exp > 0) ? display_time($exp) : _UNLIMITED;
            $cont .= '<tr><td>'.$mid.'</td>'
            .'<td><span title="'.$title.'" class="sl_note">'.cutstr($title, 35).'</span></td>'
            .'<td>'.$exp.'</td>'
            .'<td>'.$mview.'</td>'
            .'<td>'.deflang($mlanguage).'</td>'
            .'<td>'.ad_status('', $active).'</td><td>'.add_menu(ad_status($aroute.'.php?name=messages&amp;op=status&amp;id='.$mid.'&amp;act='.$act, $active).'||<a href="'.$aroute.'.php?name=messages&amp;op=add&amp;id='.$mid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$aroute.'.php?name=messages&amp;op=del&amp;id='.$mid.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $prefix, $db, $conf, $aroute, $stop;
    $mid = getVar('req', 'id', 'num');
    if ($mid) {
        list($title, $content, $expire, $active, $view, $mlanguage) = $db->sql_fetchrow($db->sql_query('SELECT title, content, expire, active, view, mlanguage FROM ' . $prefix . '_message WHERE mid = :mid', ['mid' => $mid]));
    } else {
        $mid = isset($_POST['mid']) ? $_POST['mid'] : '';
        $title = isset($_POST['title']) ? save_text($_POST['title'], 1) : '';
        $content =  isset($_POST['content']) ? save_text($_POST['content']) : '';
        $expire = (isset($_POST['newexpire']) == 1 && !empty($_POST['expire'])) ? time() + ($_POST['expire'] * 86400) : (!empty($_POST['expire']) ? $_POST['expire'] : '');
        $active = isset($_POST['active']) ? $_POST['active'] : '';
        $view = isset($_POST['view']) ? $_POST['view'] : '';
        $mlanguage = isset($_POST['mlanguage']) ? $_POST['mlanguage'] : '';
    }
    head();
    $cont = navi(1, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($content) $cont .= preview($title, $content, '', '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$aroute.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'content', $content, 'all', '10', _TEXT, '1').'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="mlanguage" class="sl_form">'.language($mlanguage).'</select></td></tr>';
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = '<input type="hidden" name="expire" value="'.$oldexpire.'">'._PURCHASED.': '.display_time($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = '<input type="number" name="expire" value="0" class="sl_form" placeholder="'._EXPIRATION.'" required>';
    }
    $cont .= '<tr><td>'._EXPIRATION.':<div class="sl_small">'._CONFINES.'</div></td><td>'.$expire_text.'</td></tr>'
    .'<tr><td>'._VIEWPRIV.'</td><td><select name="view" class="sl_form">';
    $privs = [_MVALL, _MVANON, _MVUSERS, _MVADMIN];
    foreach ($privs as $key => $value) {
        $key = $key + 1;
        $sel = ($view == $key) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($active, 'active').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="mid" value="'.$mid.'"><input type="hidden" name="name" value="messages"><input type="hidden" name="op" value="save"><input type="hidden" name="posttype" value="save"><input type="submit" value="'._SAVE.'" class="sl_but_blue"><input type="hidden" name="newexpire" value="'.$newexpire.'"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $prefix, $db, $aroute, $stop;
    $mid = isset($_POST['mid']) ? intval($_POST['mid']) : 0;
    $title = save_text($_POST['title'], 1);
    $content = save_text($_POST['content']);
    $newexpire = $_POST['newexpire'];
    $expire = $_POST['expire'];
    $active = $_POST['active'];
    $view = $_POST['view'];
    $mlanguage = $_POST['mlanguage'];
    $expire = ($newexpire == 1 && $expire) ? time() + ($expire * 86400) : $expire;
    if (!$title) $stop[] = _CERROR;
    if (!$content) $stop[] = _CERROR1;
    if (!$stop && $_POST['posttype'] == 'save') {
        if ($mid) {
            $result = $db->sql_query('UPDATE ' . $prefix . '_message SET title = :title, content = :content, expire = :expire, active = :active, view = :view, mlanguage = :mlanguage WHERE mid = :mid', ['title' => $title, 'content' => $content, 'expire' => $expire, 'active' => $active, 'view' => $view, 'mlanguage' => $mlanguage, 'mid' => $mid]);
        } else {
            $result = $db->sql_query('INSERT INTO ' . $prefix . '_message (mid, title, content, expire, active, view, mlanguage) VALUES (NULL, :title, :content, :expire, :active, :view, :mlanguage)', ['title' => $title, 'content' => $content, 'expire' => $expire, 'active' => $active, 'view' => $view, 'mlanguage' => $mlanguage]);
        }
        header('Location: '.$aroute.'.php?name=messages');
        exit;
    } elseif ($_POST['posttype'] == 'delete') {
        del($mid);
    } else {
        add();
    }
}

function status(): void {
    global $prefix, $db, $aroute;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    if ($id) $db->sql_query('UPDATE ' . $prefix . '_message SET active = :active WHERE mid = :mid', ['active' => $act, 'mid' => $id]);
    header('Location: '.$aroute.'.php?name=messages');
    exit;
}

function del(): void {
    global $prefix, $db, $aroute;
    $id = getVar('get', 'id', 'num');
    if ($id) $db->sql_query('DELETE FROM ' . $prefix . '_message WHERE mid = :mid', ['mid' => $id]);
    header('Location: '.$aroute.'.php?name=messages');
    exit;
}

function info(): void {
    head();
    echo navi(2, 0).'<div id="repadm_info">'.adm_info(1, 0, 'messages').'</div>';
    foot();
}

switch ($op) {
    default: messages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
