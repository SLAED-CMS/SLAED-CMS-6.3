<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


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


function admins(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    if (getVar('get', 'send', 'num')) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MAIL_SEND]);
    if ($msg = trim(getVar('get', 'msg', 'text', ''))) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $msg]);
    $head = $tpl->getHtmlFrag('admin-admins-table-head', [
        'actions_label' => _FUNCTIONS,
        'email_label' => _EMAIL,
        'language_label' => _LANGUAGE,
        'nickname_label' => _NICKNAME,
        'rank_label' => _URANK,
        'super_label' => _SUPERUSER,
    ]);
    $rows = '';
    $token = htmlspecialchars(getSiteToken('admins'), ENT_QUOTES, 'UTF-8');
    $result = $db->getSqlQuery(
        'SELECT id, name, title, email, lang, regdate, lastvis, super FROM '.PREFIX_DB.'_admins ORDER BY id'
    );
    while ([$aid, $name, $title, $email, $lang, $rdate, $vdate, $super] = $db->getSqlRow($result)) {
        $lang = $lang ? getLangName($lang) : _ALL;
        $show = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        $drop = $tpl->getHtmlFrag('admin-admins-delete-form', [
            'action_url' => $afile.'.php?name=admins&amp;op=delete',
            'admin_id' => $aid,
            'token' => $token,
        ]);
        $edit = $tpl->getHtmlFrag('admin-action-link', [
            'href' => $afile.'.php?name=admins&amp;op=add&amp;id='.$aid,
            'label' => _FULLEDIT,
            'title' => _FULLEDIT,
        ]);
        $drop .= $tpl->getHtmlFrag('admin-admins-delete-link', [
            'admin_id' => $aid,
            'confirm_text' => addcslashes(_DELETE.' "'.(string)$name.'"?', "\\'"),
            'label' => _ONDELETE,
            'title' => _ONDELETE,
        ]);
        $tip = _REG.': '.format_time((string)$rdate, _TIMESTRING).'<br>'._LAST_VISIT.': '.format_time((string)$vdate, _TIMESTRING);
        $acts = adminMenuItems([$edit, $drop]);
        $cells = $tpl->getHtmlFrag('admin-admins-table-cells', [
            'actions_html' => $acts,
            'email_html' => mailto($email),
            'language_text' => $lang,
            'name_html' => title_tip($tip).$show,
            'rank_text' => (string)$title,
            'super_text' => ((int)$super === 1) ? _YES : _NO,
        ]);
        $rows .= getAdminTableRow($cells);
    }
    $cont .= getAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile, $conf, $tpl;
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
    $hint = $aid ? $tpl->getHtmlFrag('admin-admins-password-hint', ['hint_text' => _ADMINPASSKEEP]) : '';
    $check = (getVar('cookie', 'sl_close_9', 'num', 0) == 0) ? '' : ' checked';
    setHead();
    $cont = setAdminNavi(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => getAdmintext($stop)]);
    $hide = $tpl->getHtmlFrag('admin-admins-form-hidden', [
        'admin_id' => $aid,
        'token' => htmlspecialchars(getSiteToken('admins'), ENT_QUOTES, 'UTF-8'),
    ]);
    $mailt = textarea(
        '1',
        'mailtext',
        replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])),
        'account',
        '10',
        _MAIL_TEXT,
        ''
    );
    $mailc = $tpl->getHtmlFrag('admin-admins-mail-panel', [
        'mail_info' => _MAIL_PASS_INFO,
        'mail_label' => _MAIL_TEXT,
        'textarea_html' => $mailt,
    ]);
    $perm = '';
    $cols = 3;
    $indx = 1;
    $open = false;
    $mods = getAdminModuleNames((string)$mods);
    foreach (getAdminmods() as $name) {
        $size = intval(100 / $cols);
        if (($indx - 1) % $cols == 0) {
            $perm .= $tpl->getHtmlFrag('admin-admins-permission-row-open', []);
            $open = true;
        }
        $perm .= $tpl->getHtmlFrag('admin-admins-permission-cell', [
            'checked' => in_array($name, $mods, true),
            'module_name' => $name,
            'module_title' => getModuleName($name),
            'title' => _MODUL.': '.$name,
            'width_num' => $size,
        ]);
        if ($indx % $cols == 0) {
            $perm .= $tpl->getHtmlFrag('admin-admins-permission-row-close', []);
            $open = false;
        }
        $indx++;
    }
    while (($indx - 1) % $cols != 0) {
        $perm .= $tpl->getHtmlFrag('admin-admins-permission-empty', []);
        $indx++;
    }
    if ($open) $perm .= $tpl->getHtmlFrag('admin-admins-permission-row-close', []);
    $perm = $tpl->getHtmlFrag('admin-admins-permissions', [
        'cells_html' => $perm,
        'cols_num' => $cols,
        'super_checked' => (int)$super === 1,
        'super_label' => _SUPERUSER,
    ]);
    $langv = '';
    if ($conf['multilingual'] == 1) {
        $langv = $tpl->getHtmlFrag('admin-admins-language-select', [
            'options_html' => language((string)$lang),
        ]);
    }
    $rows = $tpl->getHtmlFrag('admin-admins-form-rows', [
        'editor_html' => redaktor(1, 'editor', 'sl_form', (int)$editor, 0),
        'editor_label' => _EDITOR,
        'email_value' => htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8'),
        'email_label' => _EMAIL,
        'has_lang' => $conf['multilingual'] == 1,
        'lang_html' => $langv,
        'language_label' => _LANGUAGE,
        'mail_checked' => $check !== '',
        'mail_panel_html' => $mailc,
        'mail_send_label' => _MAIL_SENDE,
        'nickname_html' => get_user_search('aname', (string)$name, 25, 'sl_form', '1'),
        'nickname_label' => _NICKNAME,
        'password_hint_html' => $hint,
        'password_label' => _PASSWORD,
        'password_need' => $need,
        'permissions_html' => $perm,
        'permissions_label' => _PERMISSIONS,
        'rank_value' => htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8'),
        'rank_label' => _URANK,
        'retype_label' => _RETYPEPASSWORD,
        'smail_html' => radio_form((int)$smail, 'smail'),
        'smail_label' => _SMAIL,
        'submit_label' => _SAVE,
        'url_value' => htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8'),
        'url_label' => _URL,
    ]);
    $cont .= getAdminForm($afile.'.php?name=admins&amp;op=save', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $conf, $stop, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'admins')) {
        setHead();
        $cont = setAdminNavi(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
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
                $pass = getPassHash($pwd);
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
            $pass = getPassHash($pwd);
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
    add();
}

function delete(): void {
    global $db, $afile, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'admins')) {
        setHead();
        $cont = setAdminNavi(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
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

function info(): void {
    global $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 2]);
    echo $cont.$tpl->getHtmlFrag('admin-info-box', [
        'info_html' => getAdminInfo(),
    ]);
    setFoot();
}

switch ($op) {
    default: admins(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
