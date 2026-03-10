<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $tab = 0, int $subtab = 0): string {
    $ops = ['name=messages', 'name=messages&amp;op=add', 'name=messages&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function messages(): void {
    global $db, $afile;
    setHead();
    $cont = navi(0, 0);
    $result = $db->getSqlQuery('SELECT mid, title, content, expire, active, view, mlanguage FROM '.PREFIX_DB.'_message ORDER BY mid');
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._PURCHASED.'</th><th>'._VIEW.'</th><th>'._LANGUAGE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$mid, $title, $content, $expire, $active, $view, $mlanguage] = $db->getSqlRow($result)) {
            if (($expire && $expire < time()) || (!$active && $expire)) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET active = :active, expire = :expire WHERE mid = :mid', ['active' => 0, 'expire' => 0, 'mid' => $mid]);
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
            $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
            $cont .= '<tr><td>'.$mid.'</td>'
                .'<td><span title="'.$title.'" class="sl_note">'.cutstr($title, 35).'</span></td>'
                .'<td>'.$exp.'</td>'
                .'<td>'.$mview.'</td>'
                .'<td>'.getLangName($mlanguage).'</td>'
                .'<td>'.ad_status('', $active).'</td><td>'.add_menu(ad_status($afile.'.php?name=messages&amp;op=status&amp;id='.$mid.'&amp;act='.$act, $active).'||<a href="'.$afile.'.php?name=messages&amp;op=add&amp;id='.$mid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=messages&amp;op=del&amp;id='.$mid.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $stop;
    $mid = getVar('req', 'id', 'num');
    if ($mid) {
        [$title, $content, $expire, $active, $view, $mlanguage] = $db->getSqlRow($db->getSqlQuery('SELECT title, content, expire, active, view, mlanguage FROM '.PREFIX_DB.'_message WHERE mid = :mid', ['mid' => $mid]));
    } else {
        $mid = getVar('post', 'mid', 'num');
        $title = getVar('post', 'title', 'title');
        $content = getVar('post', 'content', 'text');
        $newexpire = getVar('post', 'newexpire', 'num');
        $expire_input = getVar('post', 'expire', 'num');
        $expire = ($newexpire == 1 && $expire_input) ? time() + ($expire_input * 86400) : $expire_input;
        $active = getVar('post', 'active', 'num');
        $view = getVar('post', 'view', 'num');
        $mlanguage = getVar('post', 'mlanguage', 'var');
    }
    setHead();
    $cont = navi(1, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($content) $cont .= preview($title, $content, '', '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
       .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
       .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'content', $content, 'all', '10', _TEXT, '1').'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="mlanguage" class="sl_form">'.language($mlanguage).'</select></td></tr>';
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = '<input type="hidden" name="expire" value="'.$oldexpire.'">'._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
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
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num');
    $title = getVar('post', 'title', 'title');
    $content = getVar('post', 'content', 'text');
    $newexpire = getVar('post', 'newexpire', 'num');
    $expire = getVar('post', 'expire', 'num');
    $active = getVar('post', 'active', 'num');
    $view = getVar('post', 'view', 'num');
    $mlanguage = getVar('post', 'mlanguage', 'var');
    $posttype = getVar('post', 'posttype', 'var');
    $expire = ($newexpire == 1 && $expire) ? time() + ($expire * 86400) : $expire;
    if (!$title) $stop[] = _CERROR;
    if (!$content) $stop[] = _CERROR1;
    if (!$stop && $posttype == 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET title = :title, content = :content, expire = :expire, active = :active, view = :view, mlanguage = :mlanguage WHERE mid = :mid', ['title' => $title, 'content' => $content, 'expire' => $expire, 'active' => $active, 'view' => $view, 'mlanguage' => $mlanguage, 'mid' => $mid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_message (mid, title, content, expire, active, view, mlanguage) VALUES (NULL, :title, :content, :expire, :active, :view, :mlanguage)', ['title' => $title, 'content' => $content, 'expire' => $expire, 'active' => $active, 'view' => $view, 'mlanguage' => $mlanguage]);
        }
        setRedirect($afile.'.php?name=messages');
    } elseif ($posttype == 'delete') {
        del($mid);
    } else {
        add();
    }
}

function status(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    if ($id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET active = :active WHERE mid = :mid', ['active' => $act, 'mid' => $id]);
    setRedirect($afile.'.php?name=messages');
}

function del(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_message WHERE mid = :mid', ['mid' => $id]);
    setRedirect($afile.'.php?name=messages');
}

function info(): void {
    setHead();
    echo navi(2, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: messages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
