<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs(_EDITADMINS, 'admins.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function admins(): void {
    global $prefix, $db, $aroute, $conf;
    head();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get', 'send', 'num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._NICKNAME.'</th><th>'._URANK.'</th><th>'._URL.'</th><th>'._EMAIL.'</th><th>'._LANGUAGE.'</th><th>'._IP.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
    $result = $db->sql_query('SELECT id, name, title, url, email, pwd, super, lang, ip, regdate, lastvisit FROM '.$prefix.'_admins ORDER BY id');
    while (list($id, $name, $title, $url, $email, $pwd, $super, $lang, $ip, $regdate, $lastvisit) = $db->sql_fetchrow($result)) {
        $lang = (!$lang) ? _ALL : $lang;
        $cont .= '<tr><td>'.title_tip(_REG.': '.format_time($regdate, _TIMESTRING).'<br>'._LAST_VISIT.': '.format_time($lastvisit, _TIMESTRING)).$name.'</td><td>'.$title.'</td><td>'.domain($url).'</td><td>'.mailto($email).'</td><td>'.deflang($lang).'</td><td>'.user_geo_ip($ip, 4).'</td>'
        .'<td>'.add_menu('<a href="'.$aroute.'.php?name=admins&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$aroute.'.php?name=admins&amp;op=del&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function add(): void {
    global $prefix, $db, $aroute, $conf, $stop;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->sql_query('SELECT id, name, title, url, email, pwd, super, editor, smail, modules, lang FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
        list($aid, $name, $title, $url, $email, $pwd, $super, $editor, $smail, $modules, $lang) = $db->sql_fetchrow($result);
    } else {
        $aid = getVar('post', 'aid', 'num', '');
        $name = getVar('post', 'adminname', 'name', '');
        $title = getVar('post', 'title', 'title', '');
        $email = getVar('post', 'email', '', '');
        $url = getVar('post', 'url', 'url', 'https://');
        $amodules = getVar('post', 'amodules[]', 'num') ?: [];
        $modules = $amodules ? implode(',', $amodules) : '';
        $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
        $editor = getVar('post', 'editor', 'num', intval($conf['redaktor']));
        $smail = getVar('post', 'smail', '', '');
        $lang = getVar('post', 'lang', '', $conf['language']);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $check = (empty($_COOKIE['sl_close_9'])) ? '' : ' checked';
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$aroute.'.php?name=admins" method="post">'
    .'<input type="hidden" name="op" value="save">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._NICKNAME.':</td><td>'.get_user_search('adminname', $name, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._URANK.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="50" class="sl_form" placeholder="'._URANK.'"></td></tr>'
    .'<tr><td>'._EMAIL.':</td><td><input type="email" name="email" value="'.$email.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required></td></tr>'
    .'<tr><td>'._URL.':</td><td><input type="url" name="url" value="'.$url.'" maxlength="255" class="sl_form" placeholder="'._URL.'"></td></tr>'
    .'<tr><td>'._PASSWORD.':</td><td><input type="password" name="pwd" value="" maxlength="25" class="sl_form" placeholder="'._PASSWORD.'" required></td></tr>'
    .'<tr><td>'._RETYPEPASSWORD.':</td><td><input type="password" name="pwd2" value="" maxlength="25" class="sl_form" placeholder="'._RETYPEPASSWORD.'" required></td></tr>'
    .'<tr><td>'._SMAIL.'</td><td>'.radio_form($smail, 'smail').'</td></tr>'
    .'<tr><td>'._MAIL_SENDE.'</td><td><input type="checkbox" name="mail" value="1" OnClick="CloseOpen(\'sl_close_9\', 0);"'.$check.'></td></tr>'
    .'<tr><td colspan="2"><div id="sl_close_9"><table class="sl_table_form"><tr><td>'._MAIL_TEXT.':<div class="sl_small">'._MAIL_PASS_INFO.'</div></td><td>'.textarea('1', 'mailtext', replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])), 'account', '10', _MAIL_TEXT, '').'</td></tr></table></div></td></tr>'
    .'<tr><td>'._REDAKTOR.':</td><td>'.redaktor('1', 'editor', 'sl_form', $editor, 0).'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language($lang).'</select></td></tr>';
    $cont .= '<tr><td>'._PERMISSIONS.':</td><td>'
    .'<table>';
    $a = 3;
    $i = 1;
    $result = $db->sql_query('SELECT mid, title FROM '.$prefix.'_modules');
    while (list($mid, $title) = $db->sql_fetchrow($result)) {
        if (file_exists('modules/'.$title.'/admin/index.php') && file_exists('modules/'.$title.'/admin/links.php')) {
            $amodules = explode(',', $modules);
            $sel = '';
            foreach ($amodules as $val) if ($mid == $val) $sel = ' checked';
            $tdwidth = intval(100/$a);
            if (($i - 1) % $a == 0) $cont .= '<tr>';
            $cont .= '<td style="width: '.$tdwidth.'%;"><input type="checkbox" name="amodules[]" value="'.$mid.'"'.$sel.'> <span title="'._MODUL.': '.$title.'" class="sl_note">'.deflmconst($title).'</span></td>';
            if ($i % $a == 0) $cont .= '</tr>';
            $i++;
        }
    }
    $sel1 = ($super == 1) ? ' checked' : '';
    $cont .= '<tr><td colspan="'.$a.'"><input type="checkbox" name="super" value="1"'.$sel1.'> <b>'._SUPERUSER.'</b></td></tr></table>'
    .'</td></tr><tr><td colspan="2" class="sl_center"><input type="hidden" name="aid" value="'.$aid.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $prefix, $db, $aroute, $conf, $stop;
    $aid = getVar('post', 'aid', 'num', 0);
    $name = getVar('post', 'adminname', 'name');
    $title = getVar('post', 'title', 'title');
    $url = getVar('post', 'url', 'url');
    $email = getVar('post', 'email', 'email');
    $pwd = getVar('post', 'pwd', '', 0);
    $pwd2 = getVar('post', 'pwd2', '', 0);
    $lang = getVar('post', 'lang');
    $amodules = getVar('post', 'amodules[]', 'num') ?: [];
    $modules = $amodules ? implode(',', $amodules) : '';
    $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
    $editor = getVar('post', 'editor', 'num', intval($conf['redaktor']));
    $smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
    $mail = getVar('post', 'mail');
    $stop = array();
    $send = '';
    if (!$aid && !$pwd && !$pwd2) $stop[] = _NOPASS;
    if ($name) {
        list($adid, $adname) = $db->sql_fetchrow($db->sql_query('SELECT id, name FROM '.$prefix.'_admins WHERE name = :name', ['name' => $name]));
        if ($aid != $adid && $name == $adname) $stop[] = _USEREXIST;
        list($adid, $ademail) = $db->sql_fetchrow($db->sql_query('SELECT id, email FROM '.$prefix.'_admins WHERE email = :email', ['email' => $email]));
        if ($aid != $adid && $email == $ademail) $stop[] = _ERROR_EMAIL;
    } else {
        $stop[] = _ERROR_ALL;
    }
    if (!analyze_name($name)) $stop[] = _ERRORINVNICK;
    checkemail($email);
    if ($pwd != $pwd2) $stop[] = _ERROR_PASS;
    if (!$stop) {
        if ($aid) {
            if ($pwd && $pwd == $pwd2) {
                $newpass = md5_salt($pwd);
                $db->sql_query('UPDATE '.$prefix.'_admins SET name = :name, title = :title, url = :url, email = :email, pwd = :pwd, super = :super, editor = :editor, smail = :smail, modules = :modules, lang = :lang WHERE id = :id', [
                    'name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pwd' => $newpass, 'super' => $super, 'editor' => $editor, 'smail' => $smail, 'modules' => $modules, 'lang' => $lang, 'id' => $aid
                ]);
            } else {
                $db->sql_query('UPDATE '.$prefix.'_admins SET name = :name, title = :title, url = :url, email = :email, super = :super, editor = :editor, smail = :smail, modules = :modules, lang = :lang WHERE id = :id', [
                    'name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'super' => $super, 'editor' => $editor, 'smail' => $smail, 'modules' => $modules, 'lang' => $lang, 'id' => $aid
                ]);
            }
        } else {
            $password = md5_salt($pwd);
            $db->sql_query('INSERT INTO '.$prefix.'_admins (name, title, url, email, pwd, super, editor, smail, modules, lang, regdate) VALUES (:name, :title, :url, :email, :pwd, :super, :editor, :smail, :modules, :lang, now())', [
                'name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pwd' => $password, 'super' => $super, 'editor' => $editor, 'smail' => $smail, 'modules' => $modules, 'lang' => $lang
            ]);
        }
        if ($mail) {
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$name;
            $mailtext = getVar('post', 'mailtext', 'text');
            $msg = nl2br(bb_decode(str_replace('[pass]', $pwd, str_replace('[login]', $name, $mailtext)), 'account'), false);
            mail_send($email, $conf['adminmail'], $subject, $msg, 0, 3);
            $send = '&send=1';
        }
        header('Location: '.$aroute.'.php?name=admins'.$send);
        exit;
    } else {
        add();
    }
}

function del(): void {
    global $prefix, $db, $aroute;
    $id = getVar('get', 'id', 'num');
    $db->sql_query('DELETE FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
    header('Location: '.$aroute.'.php?name=admins');
    exit;
}

function info(): void {
    head();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'admins').'</div>';
    foot();
}

switch ($op) {
    default: admins(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
