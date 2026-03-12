<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getAdmintoken(): string {
    static $token = '';
    if ($token === '') $token = hash('sha256', session_id().'|admins-admin');
    return $token;
}

function checkAdmintoken(): bool {
    return hash_equals(getAdmintoken(), (string)getVar('post', 'token', 'raw', ''));
}

function getAdminself(): int {
    global $admin;
    if (empty($admin[0])) return 0;
    return intval(substr((string)$admin[0], 0, 11));
}

function getAdminmods(): array {
    global $conf;
    $mods = [];
    foreach ($conf['modules'] as $name => $info) {
        if ((int)($info['type'] ?? 1) !== 1) continue;
        if (!file_exists(BASE_DIR.'/modules/'.$name.'/admin/index.php')) continue;
        $mods[] = (string)$name;
    }
    sort($mods);
    return $mods;
}

function filterAdminmods(array $mods): array {
    $list = [];
    $allow = getAdminmods();
    foreach ($mods as $name) {
        $name = filterVar((string)$name);
        if ($name === '' || $name === '0') continue;
        if (!in_array($name, $allow, true)) continue;
        $list[] = $name;
    }
    $list = array_values(array_unique($list));
    sort($list);
    return $list;
}

function getAdminrow(int $aid): array {
    global $db;
    $row = $db->getSqlRow($db->getSqlQuery(
        'SELECT id, name, title, url, email, super, editor, smail, modules, lang FROM '.PREFIX_DB.'_admins WHERE id = :id',
        ['id' => $aid]
    ));
    return is_array($row) ? $row : [];
}

function checkAdminlast(int $aid): bool {
    global $db;
    [$super] = $db->getSqlRow($db->getSqlQuery('SELECT super FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid])) ?? [0];
    if ((int)$super !== 1) return false;
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_admins WHERE super = 1')) ?? [0];
    return intval($count) <= 1;
}

function getAdmintext(array $stop): string {
    return implode('<br>', array_filter($stop, 'strlen'));
}

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function adlist(): void {
    global $db, $afile;
    setHead();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get', 'send', 'num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
    if ($msg = trim((string)getVar('get', 'msg', 'text', ''))) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $msg]);
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._NICKNAME.'</th><th>'._URANK.'</th><th>'._EMAIL.'</th><th>'._LANGUAGE
        .'</th><th>'._SUPERUSER.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
    $result = $db->getSqlQuery(
        'SELECT id, name, title, email, lang, regdate, lastvis, super FROM '.PREFIX_DB.'_admins ORDER BY id'
    );
    while ([$aid, $name, $title, $email, $lang, $rdate, $vdate, $super] = $db->getSqlRow($result)) {
        $lang = $lang ? getLangName($lang) : _ALL;
        $show = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        $drop = '<form id="drop'.$aid.'" action="'.$afile.'.php?name=admins&amp;op=del" method="post" style="display:none;">'
            .'<input type="hidden" name="op" value="del"><input type="hidden" name="aid" value="'.$aid.'"><input type="hidden" name="token" value="'
            .htmlspecialchars(getAdmintoken(), ENT_QUOTES, 'UTF-8').'"></form>';
        $edit = '<a href="'.$afile.'.php?name=admins&amp;op=add&amp;id='.$aid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>';
        $drop .= '<a href="#" OnClick="if (DelCheck(this, \''._DELETE.' &quot;'.$show.'&quot;?\')) document.getElementById(\'drop'.$aid
            .'\').submit(); return false;" title="'._ONDELETE.'">'._ONDELETE.'</a>';
        $tip = _REG.': '.format_time((string)$rdate, _TIMESTRING).'<br>'._LAST_VISIT.': '.format_time((string)$vdate, _TIMESTRING);
        $cont .= '<tr><td>'.title_tip($tip).$name.'</td><td>'.$title.'</td><td>'.mailto($email).'</td><td>'.$lang.'</td><td>'
            .(((int)$super === 1) ? _YES : _NO).'</td><td>'.add_menu($edit.'||'.$drop).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function adform(): void {
    global $afile, $conf;
    $aid = getVar('req', 'id', 'num', 0);
    $stop = [];
    if ($aid) {
        $row = getAdminrow($aid);
        if (!$row) {
            setRedirect($afile.'.php?name=admins');
            return;
        }
        [$aid, $name, $title, $url, $email, $super, $editor, $smail, $mods, $lang] = $row;
        $mods = implode(',', filterAdminmods(getAdminModuleNames((string)$mods)));
    } else {
        $name = getVar('post', 'aname', 'name', '');
        $title = getVar('post', 'title', 'title', '');
        $url = getVar('post', 'url', 'url', 'https://');
        $email = getVar('post', 'email', 'email', '');
        $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
        $editor = getVar('post', 'editor', 'num', intval($conf['redaktor']));
        $smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
        $mods = implode(',', filterAdminmods(getVar('post', 'modules[]', 'var', [])));
        $lang = getVar('post', 'lang', '', $conf['language']);
        $stop = $GLOBALS['stop'] ?? [];
    }
    $need = $aid ? '' : ' required';
    $hint = $aid ? '<div class="sl_small">'._ADMINPASSKEEP.'</div>' : '';
    $check = (getVar('cookie', 'sl_close_9', 'num', 0) == 0) ? '' : ' checked';
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => getAdmintext($stop)]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php?name=admins&amp;op=save" method="post"><input type="hidden" name="op" value="save">'
        .'<input type="hidden" name="aid" value="'.$aid.'"><input type="hidden" name="token" value="'
        .htmlspecialchars(getAdmintoken(), ENT_QUOTES, 'UTF-8').'"><table class="sl_table_form">'
        .'<tr><td>'._NICKNAME.':</td><td>'.get_user_search('aname', (string)$name, 25, 'sl_form', '1').'</td></tr>'
        .'<tr><td>'._URANK.':</td><td><input type="text" name="title" value="'.htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8')
        .'" maxlength="50" class="sl_form" placeholder="'._URANK.'"></td></tr>'
        .'<tr><td>'._EMAIL.':</td><td><input type="email" name="email" value="'.htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8')
        .'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required></td></tr>'
        .'<tr><td>'._URL.':</td><td><input type="url" name="url" value="'.htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8')
        .'" maxlength="255" class="sl_form" placeholder="'._URL.'"></td></tr>'
        .'<tr><td>'._PASSWORD.':'.$hint.'</td><td><input type="password" name="pwd" value="" maxlength="25" class="sl_form" placeholder="'
        ._PASSWORD.'"'.$need.'></td></tr><tr><td>'._RETYPEPASSWORD.':</td><td><input type="password" name="pwdtwo" value="" maxlength="25" class="sl_form" placeholder="'
        ._RETYPEPASSWORD.'"'.$need.'></td></tr><tr><td>'._SMAIL.'</td><td>'.radio_form((int)$smail, 'smail').'</td></tr>'
        .'<tr><td>'._MAIL_SENDE.'</td><td><input type="checkbox" name="mail" value="1" OnClick="CloseOpen(\'sl_close_9\', 0);"'.$check.'></td></tr>'
        .'<tr><td colspan="2"><div id="sl_close_9"><table class="sl_table_form"><tr><td>'._MAIL_TEXT.':<div class="sl_small">'
        ._MAIL_PASS_INFO.'</div></td><td>'.textarea(
            '1',
            'mailtext',
            replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])),
            'account',
            '10',
            _MAIL_TEXT,
            ''
        ).'</td></tr></table></div></td></tr><tr><td>'._EDITOR.':</td><td>'.redaktor(1, 'editor', 'sl_form', (int)$editor, 0).'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language((string)$lang).'</select></td></tr>';
    $cont .= '<tr><td>'._PERMISSIONS.':</td><td><table>';
    $cols = 3;
    $indx = 1;
    $mods = getAdminModuleNames((string)$mods);
    foreach (getAdminmods() as $name) {
        $mark = in_array($name, $mods, true) ? ' checked' : '';
        $size = intval(100 / $cols);
        if (($indx - 1) % $cols == 0) $cont .= '<tr>';
        $cont .= '<td style="width: '.$size.'%;"><input type="checkbox" name="modules[]" value="'.$name.'"'.$mark.'> <span title="'
            ._MODUL.': '.$name.'" class="sl_note">'.getModuleName($name).'</span></td>';
        if ($indx % $cols == 0) $cont .= '</tr>';
        $indx++;
    }
    while (($indx - 1) % $cols != 0) {
        $cont .= '<td></td>';
        $indx++;
    }
    $mark = ((int)$super === 1) ? ' checked' : '';
    $cont .= '<tr><td colspan="'.$cols.'"><input type="checkbox" name="super" value="1"'.$mark.'> <b>'._SUPERUSER.'</b></td></tr></table>'
        .'</td></tr><tr><td colspan="2" class="sl_center"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function adsave(): void {
    global $db, $afile, $conf, $stop;
    if (!checkAdmintoken()) {
        setHead();
        echo navi(0, 1, 0, 0).setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $aid = getVar('post', 'aid', 'num', 0);
    $name = getVar('post', 'aname', 'name', '');
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'url', 'https://');
    $email = getVar('post', 'email', 'email', '');
    $pwd = getVar('post', 'pwd', 'raw', '');
    $ptwo = getVar('post', 'pwdtwo', 'raw', '');
    $lang = getVar('post', 'lang', 'raw', $conf['language']);
    $mods = filterAdminmods(getVar('post', 'modules[]', 'var', []));
    $mods = $mods ? implode(',', $mods) : '';
    $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
    $edit = getVar('post', 'editor', 'num', intval($conf['redaktor']));
    $smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
    $mail = getVar('post', 'mail', 'bool', 0) ? 1 : 0;
    $stop = [];
    if (!$aid && ($pwd === '' || $ptwo === '')) $stop[] = _NOPASS;
    if ($name) {
        [$adid, $aname] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_admins WHERE name = :name', ['name' => $name])) ?? [0, ''];
        if ($aid != $adid && $name === $aname) $stop[] = _USEREXIST;
        [$adid, $amail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_admins WHERE email = :email', ['email' => $email])) ?? [0, ''];
        if ($aid != $adid && $email === $amail) $stop[] = _ERROR_EMAIL;
    } else {
        $stop[] = _ERROR_ALL;
    }
    if (!analyze_name($name)) $stop[] = _ERRORINVNICK;
    checkemail($email);
    if ($pwd !== $ptwo) $stop[] = _ERROR_PASS;
    $self = getAdminself();
    if ($aid && $aid === $self && !$super) $stop[] = _ADMINSELFSUPER;
    if ($aid && !$super && checkAdminlast($aid)) $stop[] = _ADMINLASTSUPER;
    if (!$stop) {
        if ($aid) {
            if ($pwd !== '') {
                $pass = md5_salt($pwd);
                $db->getSqlQuery(
                    'UPDATE '.PREFIX_DB.'_admins SET name = :name, title = :title, url = :url, email = :email, password = :pass, super = :super, editor = :edit, smail = :smail, modules = :mods, lang = :lang WHERE id = :id',
                    ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pass' => $pass, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang, 'id' => $aid]
                );
            } else {
                $db->getSqlQuery(
                    'UPDATE '.PREFIX_DB.'_admins SET name = :name, title = :title, url = :url, email = :email, super = :super, editor = :edit, smail = :smail, modules = :mods, lang = :lang WHERE id = :id',
                    ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang, 'id' => $aid]
                );
            }
        } else {
            $pass = md5_salt($pwd);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_admins (name, title, url, email, password, super, editor, smail, modules, lang, regdate) VALUES (:name, :title, :url, :email, :pass, :super, :edit, :smail, :mods, :lang, now())',
                ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pass' => $pass, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang]
            );
        }
        if ($mail) {
            $subj = $conf['sitename'].' - '._USERPASSWORD.' '.$name;
            $text = getVar('post', 'mailtext', 'text', '');
            $text = str_replace('[pass]', $pwd, str_replace('[login]', $name, $text));
            $text = filterReplaceText(filterMarkdown($text, 'account', false), 'account');
            addMail($email, $conf['adminmail'], $subj, nl2br($text, false), 0, 3);
            setRedirect($afile.'.php?name=admins&send=1');
        }
        setRedirect($afile.'.php?name=admins');
    }
    adform();
}

function addrop(): void {
    global $db, $afile;
    if (!checkAdmintoken()) {
        setHead();
        echo navi(0, 0, 0, 0).setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $aid = getVar('post', 'aid', 'num', 0);
    if (!$aid) {
        setRedirect($afile.'.php?name=admins');
        return;
    }
    if ($aid === getAdminself()) {
        setRedirect($afile.'.php?name=admins&msg='.urlencode(_ADMINSELFDEL));
        return;
    }
    if (checkAdminlast($aid)) {
        setRedirect($afile.'.php?name=admins&msg='.urlencode(_ADMINLASTSUPER));
        return;
    }
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid]);
    setRedirect($afile.'.php?name=admins');
}

function adinfo(): void {
    setHead();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: adlist(); break;
    case 'add': adform(); break;
    case 'save': adsave(); break;
    case 'del': addrop(); break;
    case 'info': adinfo(); break;
}
