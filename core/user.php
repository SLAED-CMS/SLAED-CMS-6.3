<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Render the comment list and submission form for an item
function setComShow(int $id = 0, int $cid = 0): string {
    global $conf, $user;
    $cont = '<a id="comm"></a><div id="repcsave">'.ashowcom($id, $conf['name']).'</div>';
    if (!is_user() && $conf['comments']['anonpost'] == 0) {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOANONCOMMENTS]);
    } else {
        $userinfo = getUserInfo();
        if ($cid == 1 || $userinfo['user_acess'] || (!is_user() && $conf['comments']['anonpost'] == 1)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _POSTNOTE]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post" id="formcsave" method="post">'
        .'<table class="sl_table_form">';
        if (is_user()) {
            $cont .= '<tr><td>'._YOURNAME.':</td><td>'.filterText(substr($user[1], 0, 25)).'<input type="hidden" name="name" value=""></td></tr>';
        } else {
            $cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="name" value="'._ANONYM.'" maxlength="25" class="sl_field '.$conf['style'].'"></td></tr>';
        }
        $cont .= '<tr><td>'._COMMENT.':</td><td>'.textarea(1, 'text', '', $conf['name'], '5').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).'<input type="submit" OnClick="AjaxLoad(\'POST\', \'0\', \'csave\', \'go=1&amp;op=savecom&amp;id='.$id.'&amp;cid='.$cid.'&amp;mod='.$conf['name'].'\', { \'text\':\''._CERROR1.'\' }); ClearForm(formcsave); return false;" value="'._COMMENTREPLY.'" title="'._COMMENTREPLY.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    }
    return $cont;
}

# Render the active site message box for the current language and user role
function setMessageShow(): string {
    global $db, $afile, $conf, $currentlang;
    if ($conf['message'] == 1) {
        $params = [];
        $querylang = ($conf['multilingual'] == 1) ? 'AND (mlanguage = :lang OR mlanguage = \'\')' : '';
        if ($conf['multilingual'] == 1) {
            $params['lang'] = $currentlang;
        }
        $result = $db->getSqlQuery('SELECT mid, title, content, expire, view FROM '.PREFIX_DB.'_message WHERE active = 1 '.$querylang, $params);
        if ($db->getSqlRowCount($result) > 0) {
            while ([$mid, $title, $content, $expire, $view] = $db->getSqlRow($result)) {
                $mid = intval($mid);
                if ($expire && $expire < time()) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET active = 0, expire = 0 WHERE mid = :mid', ['mid' => $mid]);
                $content = filterReplaceText(filterMarkdown($content, 'all', false), 'all');
                $exp = intval($expire - time());
                $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
                $info = '| '._PURCHASED.': '.$exp.' | <a href="'.$afile.'.php?op=msg_add&amp;id='.$mid.'" title="'._EDIT.'">'._EDIT.'</a> ]</div>';
                if ($view == 4 && is_moder()) {
                    $content .= '<div class="sl_center">[ '._VIEW.': '._MVADMIN.' '.$info;
                    return setTemplateBasic('messagebox', ['{%title%}' => $title, '{%content%}' => $content]);
                } elseif (($view == 3 && is_user()) || ($view == 3 && is_user() && is_moder())) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVUSERS.' '.$info;
                    return setTemplateBasic('messagebox', ['{%title%}' => $title, '{%content%}' => $content]);
                } elseif (($view == 2 && !is_user()) || ($view == 2 && !is_user() && is_moder())) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVANON.' '.$info;
                    return setTemplateBasic('messagebox', ['{%title%}' => $title, '{%content%}' => $content]);
                } elseif ($view == 1) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVALL.' '.$info;
                    return setTemplateBasic('messagebox', ['{%title%}' => $title, '{%content%}' => $content]);
                }
            }
        }
    }
    return '';
}

# Render the user account navigation menu with icon links
function getUserNav(): string {
    global $conf;
    $uid = intval((getUserInfo() ?? [])['user_id'] ?? 0);
    if ($conf['name'] !== 'account') getLang('account');

    $navs = [[_HOME, _RETURNACCOUNT, 'index.php?name=account', 'account/home.png']];

    if ($conf['privat']['act']) {
        $navs[] = [_MESSAGES, _PRIVAT, 'index.php?name=account&amp;op=privat', 'account/messages.png'];
    }
    if (is_active('clients') && isModGroup('clients')) {
        getLang('clients');
        $navs[] = [_PRODUCTS, _PRODUCTSINFO, 'index.php?name=clients', 'account/product.png'];
    }
    if (is_active('shop')) {
        getLang('shop');
        $navs[] = [_CLIENT, _CLIENTINFO, 'index.php?name=shop&amp;op=clients', 'account/clients.png'];
        if (($conf['shop']['part'] ?? 0) === 1) {
            $navs[] = [_PARTNER, _PARTNERINFO, 'index.php?name=shop&amp;op=partners', 'account/partners.png'];
        }
    }
    if (is_active('help') && isModGroup('help')) {
        getLang('help');
        $navs[] = [_HELP, _HELPINFO, 'index.php?name=help', 'account/help.png'];
    }
    if ($conf['favorites']['favact']) {
        $navs[] = [_FAVORITES, _FAVORITES, 'index.php?name=account&amp;op=favorites', 'account/favorites.png'];
    }
    $navs[] = [_INFO,   _PERSONALINFO, 'index.php?name=account&amp;op=view&amp;id='.$uid, 'account/account.png'];
    $navs[] = [_CHANGE, _CHANGE,       'index.php?name=account&amp;op=edithome',           'account/preferences.png'];
    $navs[] = [_LOGOUT, _LOGOUT,       'index.php?name=account&amp;op=logout',             'account/exit.png'];

    $cont = '';
    foreach ($navs as [$titl, $itit, $link, $icon]) {
        $cont .= '<div class="sl_catflex-box"><a href="'.$link.'" title="'.$itit.'"><img src="'.img_find($icon).'" alt="'.$itit.'" title="'.$itit.'"><br>'.$titl.'</a></div>';
    }
    return setTemplateBasic('open', []).'<div class="sl_catflex-cont">'.$cont.'</div>'.setTemplateBasic('close', []);
}

# Check if the logged-in user meets the group or points requirement for a module
function isModGroup(string $name): int {
    global $db, $user;
    if (is_user()) {
        $uid = intval($user[0]);
        $row = $db->getSqlRow($db->getSqlQuery('SELECT user_points, user_group FROM '.PREFIX_DB.'_users WHERE user_id = :id', ['id' => $uid]));
        $points = $row['user_points'] ?? 0;
        $group = $row['user_group'] ?? 0;
        $mod_conf = $conf['modules'][$name] ?? [];
        $mgroup = intval($mod_conf['group'] ?? 0);
        $grpoints = 0;
        $grextra = 0;
        if ($mgroup) {
            $ginfo = $db->getSqlRow($db->getSqlQuery('SELECT points, extra FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $mgroup]));
            $grpoints = intval($ginfo['points'] ?? 0);
            $grextra = $ginfo['extra'] ?? 0;
        }
        if (intval($group) && $group !== '' && $group == $mgroup && $grextra === '1') {
            return 1;
        } elseif ((intval($points) && $points >= $grpoints && $grextra !== '1') || $mgroup === 0) {
            return 1;
        }
    }
    return 0;
}

# Fetch the full database record for the currently logged-in user
function getUserInfo() {
    global $db, $user;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    if (is_user() && $uid) {
        $info = $db->getSqlRow($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_users WHERE user_id = :uid', ['uid' => $uid]));
        return $info;
    }
}

# Render the user's custom sidebar block if enabled
function getUserBlock(): string {
    global $db, $user;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    $block = (isset($user[4])) ? intval($user[4]) : 0;
    if (is_user() && $block) {
        [$userblock] = $db->getSqlRow($db->getSqlQuery('SELECT user_block FROM '.PREFIX_DB.'_users WHERE user_id = :uid', ['uid' => $uid]));
        $userblock = filterReplaceText(filterMarkdown($userblock, 'account', false), 'account');
        return setTemplateBlock('', ['{%title%}' => _MENUFOR, '{%content%}' => $userblock]);
    }
    return '';
}

# Validate and save a new comment; echoes the updated comment list on success
function addComment() {
    global $db, $user, $conf;
    $id       = getVar('post', 'id',   'num',  0);
    $cid      = getVar('post', 'cid',  'num',  0);
    $mod      = filterVar(getVar('post', 'mod',  'text', ''));
    $postname = filterText(substr(getVar('post', 'name', 'raw', ''), 0, 25));
    $ip       = getip();
    $comment  = trim(getVar('post', 'text', 'raw', ''));
    [$date] = $db->getSqlRow($db->getSqlQuery('SELECT date FROM '.PREFIX_DB.'_comment WHERE host_name = :ip ORDER BY id DESC LIMIT 1', ['ip' => $ip]));
    $stime = strtotime($date) + $conf['comments']['send'];
    $checks = str_replace(["\n", "\r", "\t"], ' ', $comment);
    $e = explode(' ', $checks);
    for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
    $stop = '';
    if ($comment === '') $stop = _CERROR1;
    if ($o > $conf['comments']['letter']) $stop = _CERROR2;
    if ((!is_user() && $postname === '') || (!is_user() && $conf['comments']['anonpost'] == 0)) $stop = _CERROR3;
    if ($stime > time()) $stop = sprintf(_CERROR5, $conf['comments']['send']);
    if (!is_moder($mod) && (($conf['comments']['link'] == 1 && !is_user()) || ($conf['comments']['link'] == 2)) && stripos($comment, 'http://') !== false) $stop = _CERROR9;
    $urlclick = (!is_moder($mod) && (($conf['comments']['alink'] == 1 && !is_user()) || ($conf['comments']['alink'] == 2))) ? 1 : 0;
    if (checkCaptcha(1)) $stop = _SECCODEINCOR;
    if (!$stop && $id && $mod) {
        $comment = filterHtml($comment, $urlclick);
        if (is_user()) {
            $postid = intval($user[0]);
            $userinfo = getUserInfo();
            $postname = $userinfo['user_name'];
            $status = (!is_moder($mod) && ($cid == 1 || $userinfo['user_acess'])) ? 0 : 1;
        } else {
            $postid = '0';
            $postname = $postname;
            $status = (!is_moder($mod) && ($cid == 1 || $conf['comments']['anonpost'] == 1)) ? 0 : 1;
        }
        $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_comment VALUES (NULL, :cid, :modul, NOW(), :uid, :name, :host_name, :comment, :status)',
            ['cid' => $id, 'modul' => $mod, 'uid' => $postid, 'name' => $postname, 'host_name' => $ip, 'comment' => $comment, 'status' => $status]
        );
        if ($status) numcom($id, $mod, 0, $postid);
        [$lcom_id] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND uid = :uid ORDER BY id DESC LIMIT 1', ['cid' => $id, 'uid' => $postid]));
        $finishlink = $conf['homeurl'].'/index.php?name='.$mod.'&amp;op=view&amp;id='.$id.'#'.$lcom_id;
        $clink = '<a href="'.$finishlink.'">'.$finishlink.'</a>';
        addAdminMail($conf['comments']['addmail'], $mod, $postname, getModuleName($mod), 1, $clink);
        echo ashowcom($id, $mod);
    } else {
        $stop = ($stop) ? $stop : _ERROR;
        echo setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Validate and update an existing forum post in-place
function updatePost() {
    global $db, $user, $conf;
    $conf['forum'] = $conf['forum'] ?? [];
    $id    = getVar('post', 'id',  'num',  0)  ?: getVar('get', 'id',  'num',  0);
    $cid   = getVar('post', 'cid', 'num',  0)  ?: getVar('get', 'cid', 'num',  0);
    $typ   = getVar('post', 'typ', 'num',  0)  ?: getVar('get', 'typ', 'num',  0);
    $mod   = filterVar(getVar('post', 'mod', 'text', '') ?: getVar('get', 'mod', 'text', ''));
    $text  = trim(getVar('post', 'text', 'raw', ''));
    if ($conf['forum']['add'] && $id && $cid) {
        [$auth_edit, $auth_mod] = $db->getSqlRow($db->getSqlQuery('SELECT auth_edit, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $cid]));
        $isedit = is_acess($auth_edit);
        $ismod = is_acess($auth_mod);
        [$pid, $uid, $hometext, $fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid, hometext, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($pid) {
            if (is_moder(isset($conf['name']))) {
                [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
            } else {
                [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid AND status != 0', ['pid' => $pid]));
            }
        }
        if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
            if (!$text) {
                $content = ($typ) ? textareae('for'.$id, '1', 'editpost', $id, $cid, '0', $mod, $hometext, '15') : filterReplaceText(filterMarkdown($hometext, $mod, false), $mod);
                echo $content;
            } else {
                $postid = (is_user()) ? intval($user[0]) : '';
                $ip = getip();
                $checks = str_replace(["\n", "\r", "\t"], ' ', $text);
                $e = explode(' ', $checks);
                for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
                $stop = '';
                if ($text == '') $stop[] = _CERROR1;
                if ($o > $conf['forum']['letter']) $stop[] = _CERROR2;
                if (!$stop) {
                    $htext = filterHtml($text);
                    $db->getSqlQuery(
                        'UPDATE '.PREFIX_DB.'_forum SET hometext = :hometext, e_uid = :e_uid, e_ip_send = :e_ip_send, e_time = NOW() WHERE id = :id',
                        ['hometext' => $htext, 'e_uid' => $postid, 'e_ip_send' => $ip, 'id' => $id]
                    );
                    echo filterReplaceText(filterMarkdown($htext, $mod, false), $mod);
                } else {
                    return setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
                }
            }
        } else {
            return setTemplateWarning('warn', ['text' => _ERROR, 'url' => '', 'time' => 0, 'id' => 'warn']);
        }
    } else {
        return setTemplateWarning('warn', ['text' => _ERROR, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Render the private-message inbox, outbox, saved or detail view
function getPmView(int $obj = 0, string $stop = '', string $info = '', int $typ = 0): string {
    global $db, $user, $conf;
    $typ = $typ ?: getVar('get', 'typ', 'num', 0);
    $uid = intval($user[0]);
    $newlistnum = intval($conf['privat']['num']);
    $cid = getVar('get', 'cid', 'num', 1);
    $offset = ($cid-1) * $newlistnum;
    $offset = intval($offset);
    $conf['name'] = 'account';
    $conf['style'] = (string)($conf['style'] ?? '');
    if ($conf['style'] === '') {
        $conf['style'] = 'sl_account';
    }
    $cont = '';
    if ($typ == 1) {
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status <= 1', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $conf['privat']['messin']) {
            $messinfo = sprintf(_PRINEXIT, $conf['privat']['messin']);
            $fstatus = 'warn';
        } elseif ($pr_num >= ($conf['privat']['messin'] / 2)) {
            $acmess = ($conf['privat']['messin'] - $pr_num);
            $messinfo = sprintf(_PRINMAX, $conf['privat']['messin'], $pr_num, $acmess);
            $fstatus = 'info';
        }
        if ($fstatus) $cont .= setTemplateWarning('warn', ['text' => $messinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
        if ($stop) {
            $cont .= setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
        } elseif ($info) {
            $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'info']);
        }
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.user_id) WHERE p.uidin = :uid AND p.status <= 1 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_list"><thead class="sl_table_list_head"><tr><th>'._TITLE.'</th><th>'._PRSE.'</th><th>'._DATE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
            while ([$id, $uidin, $uidout, $title, $date, $status, $user_name] = $db->getSqlRow($result)) {
                if ($status) {
                    $ititle = _PROLD;
                    $hidden = ' sl_hidden';
                } else {
                    $ititle = _PRNEW;
                    $hidden = '';
                }
                $title = '<span title="'.$ititle.'" class="sl_m_in'.$hidden."\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=1', ''); return false;\" title=\"".$title.'">'.cutstr($title, 35).'</a>';
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=1', ''); return false;\" title=\""._SHOW.'">'._SHOW."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmesssave&amp;id=".$id."', ''); return false;\" title=\""._SAVE.'">'._SAVE."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=1', ''); return false;\" title=\""._DELETE.'">'._DELETE.'</a>');
                $cont .= '<tr><td>'.$title.'</td><td>'.$post.'</td><td>'.$date.'</td><td>'.$func.'</td></tr>';
            }
            $cont .= '</tbody></table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'prmess', 'prmessin', 0, '1', '');
    } elseif ($typ == 2) {
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.user_id) WHERE p.uidout = :uid AND p.status <= 1 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_list"><thead class="sl_table_list_head"><tr><th>'._TITLE.'</th><th>'._PRRE.'</th><th>'._DATE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
            while ([$id, $uidin, $uidout, $title, $date, $status, $user_name] = $db->getSqlRow($result)) {
                if ($status) {
                    $ititle = _PROLD;
                    $hidden = ' sl_hidden';
                    $del = '';
                } else {
                    $ititle = _PROUTNEW;
                    $hidden = '';
                    $del = "||<a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=2', ''); return false;\" title=\""._DELETE.'">'._DELETE.'</a>';
                }
                $title = '<span title="'.$ititle.'" class="sl_m_out'.$hidden."\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=2&amp;typ=4&amp;mod=2', ''); return false;\" title=\"".$title.'">'.cutstr($title, 35).'</a>';
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=2&amp;typ=4&amp;mod=2', ''); return false;\" title=\""._SHOW.'">'._SHOW.'</a>'.$del);
                $cont .= '<tr><td>'.$title.'</td><td>'.$post.'</td><td>'.$date.'</td><td>'.$func.'</td></tr>';
            }
            $cont .= '</tbody></table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidout = :uid AND status <= 1', ['uid' => $uid]));
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'prmess', 'prmessou', 0, '2', '');
    } elseif ($typ == 3) {
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $conf['privat']['messsav']) {
            $messinfo = sprintf(_PRSAVEEXIT, $conf['privat']['messsav']);
            $fstatus = 'warn';
        } elseif ($pr_num >= ($conf['privat']['messsav'] / 2)) {
            $acmess = ($conf['privat']['messsav'] - $pr_num);
            $messinfo = sprintf(_PRSAVEMAX, $conf['privat']['messsav'], $pr_num, $acmess);
            $fstatus = 'info';
        }
        if ($fstatus) $cont .= setTemplateWarning('warn', ['text' => $messinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout=u.user_id) WHERE p.uidin = :uid AND p.status = 2 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_list"><thead class="sl_table_list_head"><tr><th>'._TITLE.'</th><th>'._PRSE.'</th><th>'._DATE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
            while ([$id, $uidin, $uidout, $title, $date, $status, $user_name] = $db->getSqlRow($result)) {
            $title = '<span title="'._PRMOVE."\" class=\"sl_m_save\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=3', ''); return false;\" title=\"".$title.'">'.cutstr($title, 35).'</a>';
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=3', ''); return false;\" title=\""._SHOW.'">'._SHOW."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=3', ''); return false;\" title=\""._DELETE.'">'._DELETE.'</a>');
                $cont .= '<tr><td>'.$title.'</td><td>'.$post.'</td><td>'.$date.'</td><td>'.$func.'</td></tr>';
            }
            $cont .= '</tbody></table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'prmess', 'prmesssa', 0, '3', '');
    } elseif ($typ == 4) {
        if ($stop) {
            $cont .= setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
        } elseif ($info) {
            $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'info']);
        }
        $id  = getVar('get', 'id',  'num', 0);
        $cid = getVar('get', 'cid', 'num', 0);
        $mod = getVar('get', 'mod', 'num', 0);
        if ($mod == 1) {
            $prmid = 'prmessin';
        } elseif ($mod == 2) {
            $prmid = 'prmessou';
        } elseif ($mod == 3) {
            $prmid = 'prmesssa';
        } else {
            $prmid = 'prmessfo';
        }
        if ($id) {
            if ($cid == '2') {
                [$idp, $uidin, $uidout, $title, $content, $date, $ip_sender, $status, $user_name] = $db->getSqlRow($db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.content, p.date, p.ip_sender, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.user_id) WHERE p.id = :id AND p.uidout = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
            } else {
                [$idp, $uidin, $uidout, $title, $content, $date, $ip_sender, $status, $user_name] = $db->getSqlRow($db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.content, p.date, p.ip_sender, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.user_id) WHERE p.id = :id AND p.uidin = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
                if (!$status) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_privat SET status = 1 WHERE id = :id AND uidin = :uid AND status != 2', ['id' => $id, 'uid' => $uid]);
            }
            if ($idp) {
                # UNBEKANTE VARIABLEN INITIALISIERUNG VERHINDERN
                $com_name = $com_id = '';

                $result = $db->getSqlQuery('SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_from, u.user_sig, u.user_viewemail, u.user_points, u.user_warnings, u.user_gender, u.user_votes, u.user_totalvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra=1 AND u.user_group=g.id) OR (g.extra!=1 AND u.user_points>=g.points)) WHERE u.user_id = :uidout ORDER BY g.extra DESC, g.points DESC', ['uidout' => $uidout]);
                [$user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor] = $db->getSqlRow($result);
                $avname = ($user_name) ? $user_name : $com_name.' ('._ANONYM.')';
                $date = '<span title="'._PADD.'" class="sl_t_post">'.format_time($date, _TIMESTRING).'</span>';
                $ip = (is_moder($conf['name'])) ? user_geo_ip($ip_sender, 4) : '';
                $avatar = ($user_name) ? (($user_avatar && file_exists($conf['users']['adirectory'].'/'.$user_avatar)) ? $conf['users']['adirectory'].'/'.$user_avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
                $rank = ($user_rank) ? $user_rank : '';
                $trank = ($user_gname) ? _GROUP.': '.$user_gname : _RANK;
                $rlink = ($user_grank && file_exists(img_find('ranks/'.$user_grank))) ? '<img src="'.img_find('ranks/'.$user_grank).'" alt="'.$trank.'" title="'.$trank.'">' : '';
                $rate = ajax_rating(0, $user_id, $conf['name'], $user_votes, $user_totalvotes, $com_id, 1);
                $rwarn = ($user_warnings) ? _UWARNS.': '.warnings($user_warnings) : '';
                $group = ($user_gname) ? _GROUP.': <span style="color: '.$user_gcolor.'">'.$user_gname.'</span>' : '';
                $point = ($conf['users']['point'] && $user_points) ? _POINTS.': '.$user_points : '';
                $regdate = ($user_regdate) ? _REG.': '.format_time($user_regdate) : _NO_INFO;
                $gender = ($user_gender) ? _GENDER.': '.gender($user_gender) : '';
                $from = ($user_from) ? _FROM.': '.$user_from : '';
                $sig = ($user_sig) ? '<hr>'.$user_sig : '';
                $profil = ($conf['privat']['profil'] && $user_name) ? '<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($user_name).'" title="'._PERSONALINFO.'" class="sl_but">'._ACCOUNT.'</a>' : '';
                $web = ($conf['privat']['web'] && $user_website) ? '<a href="'.$user_website.'" target="_blank" title="'._DOWNLLINK.'" class="sl_but">'._SITE.'</a>' : '';
                

                
                $edit = (($uidin == $uid) || ($uidout == $uid && !$status)) ? add_menu("<a OnClick=\"AjaxLoad('GET', '0', '".$prmid."', 'go=1&amp;op=prmessdel&amp;id=".$idp.'&amp;typ='.$mod."', ''); return false;\" title=\""._ONDELETE.'">'._ONDELETE.'</a>') : '';
                $cont .= setTemplateBasic('privat-message', ['{%username%}' => $avname, '{%date%}' => $date, '{%ip%}' => $ip, '{%title%}' => cutstr($title, 35), '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => filterReplaceText(filterMarkdown($content, $conf['name'], false), $conf['name']), '{%sig%}' => filterReplaceText(filterMarkdown($sig, $conf['name'], false), $conf['name']), '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_edit%}' => $edit]);
            }
        }
        if (!$info && (!$cid || $cid == '1')) {
            $name = getVar('post', 'name', 'raw', '') ?: urldecode(getVar('get', 'uname', 'raw', ''));
            $sname = filterText(substr($name, 0, 25));
            $stitle = filterText(trim(getVar('post', 'title', 'raw', '')));
            $stext = filterText(trim(getVar('post', 'text', 'raw', '')));
            $rpost = ($sname) ? $sname : (($user_name ?? '') ? $user_name : '');
            $rtitle = ($stitle) ? $stitle : (($title ?? '') ? _PRREP.': '.$title : '');
            $rcontent = ($stext) ? $stext : (($content ?? '') ? '[quote]'.$content.'[/quote]' : '');
            
            $idp = ($id) ? '2' : '1';
            $cont .= '<form name="post" id="form'.$prmid.'" method="post">'
            .'<table class="sl_table_form">'
            .'<tr><td>'._PRRE.':</td><td>'.get_user_search('name', $rpost, '25', $conf['style'], '1').'</td></tr>'
            .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$rtitle.'" maxlength="100" class="sl_field '.$conf['style'].'"></td></tr>'
            .'<tr><td>'._MESSAGE.':</td><td>'.textarea($idp, 'text', $rcontent, $conf['name'], '15').'</td></tr>'
            ."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"submit\" OnClick=\"AjaxLoad('POST', '0', '".$prmid."', 'go=1&amp;op=prmesssend', { 'name':'"._CERROR6."' }); return false;\" value=\""._SEND.'" title="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
        }
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Validate and send a new private message; returns the updated inbox view
function addPmMsg() {
    global $db, $user, $conf;
    $postname = filterText(substr(getVar('post', 'name',  'raw', ''), 0, 25));
    $title    = trim(getVar('post', 'title', 'raw', ''));
    $text     = trim(getVar('post', 'text',  'raw', ''));
    $ip = getip();

    $uidin = (is_user_id($postname)) ? is_user_id($postname) : '';
    $uidout = (is_user()) ? intval($user[0]) : '';
    
    [$date] = $db->getSqlRow($db->getSqlQuery('SELECT date FROM '.PREFIX_DB.'_privat WHERE uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidout' => $uidout]));
    $stime = strtotime($date) + $conf['privat']['send'];
    $checks = str_replace(["\n", "\r", "\t"], ' ', $text);
    $e = explode(' ', $checks);
    for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
    
    $stop = [];
    if (!$postname) {
        $stop[] = _CERROR6;
    } elseif (!$uidin) {
        $stop[] = _CERROR7;
    }
    if ($conf['privat']['himself'] && $uidin == $uidout) $stop[] = _CERROR8;
    if (!$title) $stop[] = _CERROR;
    if (!$text) $stop[] = _CERROR1;
    if ($o > $conf['privat']['letter']) $stop[] = _CERROR2;
    if (!$uidout) $stop[] = _CERROR3;
    if ($stime > time()) $stop[] = sprintf(_CERROR5, $conf['privat']['send']);

    [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND status <= 1', ['uidin' => $uidin]));
    if ($pr_num >= $conf['privat']['messin']) $stop[] = sprintf(_PRSENDOVER, $postname);
    
    if (!$stop && $conf['privat']['act'] && is_user()) {
        $title = filterHtml($title, 1);
        $text = filterHtml($text);
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_privat VALUES (NULL, :uidin, :uidout, :title, :content, NOW(), :ip_sender, 0)', ['uidin' => $uidin, 'uidout' => $uidout, 'title' => $title, 'content' => $text, 'ip_sender' => $ip]);
        update_points(45);
        if ($conf['privat']['newmail']) {
            [$user_email, $user_psmail] = $db->getSqlRow($db->getSqlQuery('SELECT user_email, user_psmail FROM '.PREFIX_DB.'_users WHERE user_id = :uidin', ['uidin' => $uidin]));
            if ($user_email && $user_psmail) {
                [$id] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidin' => $uidin, 'uidout' => $uidout]));
                $uname = filterText(substr($user[1], 0, 25));
                $finishlink = $conf['homeurl'].'/index.php?name=account&amp;op=privat&amp;id='.$id.'#prmess';
                $link = '<a href="'.$finishlink.'">'.$finishlink.'</a>';
                $subject = $conf['sitename'].' - '._PRIVAT;
                $message = str_replace('[text]', sprintf(_PRNEWMAIL, $uname, $link), $conf['mtemp']);
                addMail($user_email, $conf['adminmail'], $subject, $message, 0, 3);
            }
        }
        $info = sprintf(_PRSENDED, $postname);
        return getPmView(0, 0, $info, 4);
    } else {
        $stop = ($stop) ? $stop : _ERROR;
        return getPmView(0, $stop, 0, 4);
    }
}

# Move a received private message to the user's saved folder
function setPmSaved() {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
    $pr_numi = $pr_num + 1;
    if ($pr_num >= $conf['privat']['messsav']) {
        $stop = sprintf(_PRSAVEEXIT, $conf['privat']['messsav']);
        $info = 0;
    } elseif ($pr_numi >= ($conf['privat']['messsav'] / 2)) {
        $acmess = ($conf['privat']['messsav'] - $pr_numi);
        $stop = 0;
        $info = sprintf(_PRSAVEMAX, $conf['privat']['messsav'], $pr_numi, $acmess);
    }
    if (!$stop && $conf['privat']['act'] && $uid && $id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_privat SET status = 2 WHERE id = :id AND uidin = :uid', ['id' => $id, 'uid' => $uid]);
    return getPmView(0, $stop, $info, 1);
}

# Delete a private message from inbox or outbox and return the updated view
function deletePmMsg() {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id  = getVar('get', 'id',  'num', 0);
    $typ = getVar('get', 'typ', 'num', 1);
    if ($conf['privat']['act'] && $uid && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE (id = :id_in AND uidin = :uid_in) OR (id = :id_out AND uidout = :uid_out AND status = 0)', ['id_in' => $id, 'uid_in' => $uid, 'id_out' => $id, 'uid_out' => $uid]);
    return getPmView(0, 0, 0, $typ);
}

# Render the favorites toggle button for an item (on/off/limit-reached state)
function getFavorBtn(int $fid, string $mod) {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    if ($conf['favorites']['favact'] && $uid) {
        [$fav] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid AND fid = :fid AND modul = :modul', ['uid' => $uid, 'fid' => $fid, 'modul' => $mod]));
        if ($fav) {
            $content = '<span title="'._FAVOR.'" class="sl_favor sl_favor_on"></span>';
        } else {
            [$fav_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
            if ($fav_num >= $conf['favorites']['favorites']) {
                $fav_exit = sprintf(_FAVOR_EXIT, $conf['favorites']['favorites']);
                $content = '<span title="'.$fav_exit.'" class="sl_favor sl_favor_off"></span>';
            } else {
                $content = '<span id="rep'.$fid.$mod."\"><span OnClick=\"AjaxLoad('GET', '0', '".$fid.$mod."', 'go=1&amp;op=favoradd&amp;id=".$fid.'&amp;mod='.$mod."', ''); return false;\" title=\""._FAVOR_ADD.'" class="sl_favor"></span></span>';
            }
        }
        return $content;
    }
}

# Add an item to the user's favorites list and echo the updated toggle button
function addFavor() {
    global $db, $conf, $user;
    $id = getVar('get', 'id',  'num',  0);
    $mod = filterVar(getVar('get', 'mod', 'text', ''));
    $uid = (is_user()) ? intval($user[0]) : 0;
    if ($conf['favorites']['favact'] && $uid && $id && $mod) {
        [$fav] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid AND fid = :fid AND modul = :modul', ['uid' => $uid, 'fid' => $id, 'modul' => $mod]));
        if ($fav) {
            echo getFavorBtn($id, $mod);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_favorites VALUES (NULL, :uid, :fid, :modul)', ['uid' => $uid, 'fid' => $id, 'modul' => $mod]);
            update_points(44);
        }
    }
    echo getFavorBtn($id, $mod);
}

# Render the paginated favorites list for the logged-in user
function getFavorList(int $obj = 0): string {
    global $db, $conf, $user;
    $uid = intval($user[0]);
    $newlistnum = intval($conf['favorites']['num']);
    $cid = getVar('get', 'cid', 'num', 1);
    $offset = ($cid - 1) * $newlistnum;
    $offset = intval($offset);
    $a = ($cid) ? $offset + 1 : 1;
    
    [$fav_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
    if ($fav_num >= $conf['favorites']['favorites']) {
        $favinfo = sprintf(_FAVOR_EXIT, $conf['favorites']['favorites']);
        $fstatus = 'warn';
    } else {
        $acfavor = ($conf['favorites']['favorites'] - $fav_num);
        $favinfo = sprintf(_FAVOR_MAX, $conf['favorites']['favorites'], $fav_num, $acfavor);
        $fstatus = 'info';
    }
    
    $result = $db->getSqlQuery('SELECT fid, modul FROM '.PREFIX_DB.'_favorites WHERE uid = :uid ORDER BY id DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
    while ([$fid, $modul] = $db->getSqlRow($result)) $fmassiv[$modul][] = $fid;
    
    if (is_array($fmassiv)) {
        foreach ($fmassiv as $key => $val) {
            $ids = array_values(array_filter(array_map('intval', $val), static fn($v) => $v > 0));
            if (!$ids) continue;
            $pp = [];
            $pm = ['uid' => $uid];
            foreach ($ids as $k => $v) {
                $ph = 'f'.$k;
                $pp[] = ':'.$ph;
                $pm[$ph] = $v;
            }
            $in = implode(', ', $pp);
            $numl = count($val);
            if ($key == 'faq') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_faq AS n ON (f.fid=n.fid) WHERE f.uid = :uid AND n.fid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'files') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_files AS n ON (f.fid=n.lid) WHERE f.uid = :uid AND n.lid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'forum') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_forum AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'help') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_help AS n ON (f.fid=n.sid) WHERE f.uid = :uid AND n.sid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'links') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_links AS n ON (f.fid=n.lid) WHERE f.uid = :uid AND n.lid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'media') {
                $conf['media'] = $conf['media'] ?? [];
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, n.subtitle FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_media AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title, $subtitle] = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $ffmassiv[] = [$id, $fid, $modul, $title];
                }
            } elseif ($key == 'news') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_news AS n ON (f.fid=n.sid) WHERE f.uid = :uid AND n.sid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'pages') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_pages AS n ON (f.fid=n.pid) WHERE f.uid = :uid AND n.pid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'shop') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_products AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            }
        }
    }
    $cont = setTemplateWarning('warn', ['text' => $favinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
    if ($ffmassiv) {
        $cont .= '<table class="sl_table_list"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
        foreach ($ffmassiv as $key => $val) {
            $id = $val[0];
            $fid = $val[1];
            $modul = $val[2];
            $title = $val[3];
            $surl = 'index.php?name='.$modul.'&amp;op=view&amp;id='.$fid;
            $cont .= '<tr id="'.$a.'">'
            .'<td><a href="#'.$a.'" title="'.$a.'" class="sl_pnum">'.$a.'</a></td>'
            .'<td><a href="'.$surl.'" title="'.$title.'">'.cutstr($title, 100).'</a></td>'
            .'<td>'.add_menu('<a href="index.php?name='.$modul.'&amp;op=view&amp;id='.$fid.'" title="'._SHOW.'">'._SHOW.'</a>||<a href="index.php?name='.$modul.'&amp;op=view&amp;id='.$fid.'" rel="sidebar" title="'.$title.'">'._S_FAVORITEN."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'favorliste', 'go=1&amp;op=favordel&amp;id=".$id."', ''); return false;\" title=\""._DELETE.'">'._DELETE.'</a>').'</td>';
            $a++;
        }
        $cont .= '</tbody></table>';
        $numpages = ceil($fav_num / $newlistnum);
        $cont .= num_ajax('pagenum', $fav_num, $numpages, $newlistnum, $conf['favorites']['nump'], $cid, '0', 1, 'favorliste', 'favorliste', 0, '', '');
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Delete a favorite entry and return the refreshed favorites list
function deleteFavor(): string {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    if ($conf['favorites']['favact'] && $uid && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id AND uid = :uid', ['id' => $id, 'uid' => $uid]);
    return getFavorList(0);
}

# Output the RSS 2.0 feed for the specified module and optional category
function getRssChannel() {
    global $db, $conf;
    header_remove('X-Content-Type-Options');
    header('Content-Type: application/rss+xml; charset='._CHARSET);
    header('Content-Encoding: none');

    $name = filterVar(getVar('post', 'name', 'text', '') ?: getVar('get', 'name', 'text', ''));
    $hmodul = explode(',', $conf['module']);
    $hi = mt_rand(0, count($hmodul) - 1);
    $cname = $hmodul[$hi];
    $name = ($name) ? $name : $cname;
    $cat  = getVar('post', 'cat', 'num', 0) ?: getVar('get', 'cat', 'num', 0);
    $num  = getVar('post', 'num', 'num', 0) ?: getVar('get', 'num', 'num', 0);
    $num = ($num) ? (($num <= $conf['rss']['max']) ? $num : $conf['rss']['max']) : $conf['rss']['min'];
    $id   = getVar('post', 'id',  'num', 0) ?: getVar('get', 'id',  'num', 0);

    if (($name == 'content') && $id) {
        $result = $db->getSqlQuery('SELECT id, title, text, time FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    } elseif ($name == 'faq') {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.fid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'files') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.lid, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_files AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'links') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.lid, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_links AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'media') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_media AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'pages') {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.pid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_pages AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'shop') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.active = 1' : 'WHERE s.time <= NOW() AND s.active = 1';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.title, s.time, s.text, c.title FROM '.PREFIX_DB.'_products AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'news') {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.sid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
        $name = 'news';
    } else {
        $result = '';
        $name = '';
    }

    $content = '<?xml version="1.0" encoding="'._CHARSET."\"?>\n"
    ."<rss version=\"2.0\">\n"
    ."<channel>\n"
    .'<title>'.htmlspecialchars($conf['sitename'])."</title>\n"
    .'<link>'.$conf['homeurl']."</link>\n"
    .'<description>'.htmlspecialchars($conf['slogan'])."</description>\n"
    .'<generator>SLAED CMS '.$conf['version']."</generator>\n"
    .'<copyright>Copyright (c) SLAED CMS '.$conf['version']."</copyright>\n"
    .'<language>'.htmlspecialchars(substr(_LOCALE, 0, 2))."</language>\n"
    .'<lastBuildDate>'.date('D, j M Y H:m:s O')."</lastBuildDate>\n\n";
    if ($name && $name != 'content' && $name != 'shop' && $result) {
        while ([$rid, $uname, $rtitle, $rtime, $rhometext, $rctitle, $user_name] = $db->getSqlRow($result)) {
            $rauthor = ($user_name) ? $user_name : (($uname) ? $uname : _ANONYM);
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</guid>\n"
            .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</link>\n"
            .'<description>'.htmlspecialchars(filterReplaceText(filterMarkdown($rhometext, $name, false), $name))."</description>\n"
            .'<comments>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= '<author>antispam@antispam.com ('.htmlspecialchars($rauthor).")</author>\n"
            ."</item>\n\n";
        }
    } elseif ($name && $name == 'content' && $result) {
        [$rid, $rtitle, $rhometext, $rtime] = $db->getSqlRow($result);
        $content .= "<item>\n"
        .'<title>'.htmlspecialchars($rtitle)."</title>\n"
        .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
        .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</guid>\n"
        .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</link>\n"
        .'<description>'.htmlspecialchars(filterReplaceText(filterMarkdown($rhometext, $name, false), $name))."</description>\n"
        ."</item>\n\n";
    } elseif ($name && $name == 'shop' && $result) {
        while ([$rid, $rtitle, $rtime, $rhometext, $rctitle] = $db->getSqlRow($result)) {
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</guid>\n"
            .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid."</link>\n"
            .'<description>'.htmlspecialchars(filterReplaceText(filterMarkdown($rhometext, $name, false), $name))."</description>\n"
            .'<comments>'.$conf['homeurl'].'/index.php?name='.$name.'&amp;op=view&amp;id='.$rid.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= "</item>\n\n";
        }
    }
    $content .= "</channel>\n</rss>";
    return $content;
}

# Output the OpenSearch description XML for browser search integration
function getOpenSearch() {
    global $conf;
    header('Content-Type: application/opensearchdescription+xml');
    header('Content-Encoding: none');
    return '<?xml version="1.0" encoding="'._CHARSET."\"?>\n"
    ."<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\">\n"
    .'<ShortName>'.htmlspecialchars($conf['sitename'])."</ShortName>\n"
    .'<Description>'.htmlspecialchars($conf['slogan'])."</Description>\n"
    .'<Url type="application/atom+xml" template="'.$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    .'<Url type="application/rss+xml" template="'.$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    .'<Url type="text/html" template="'.$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    .'<Image height="16" width="16" type="image/x-icon">'.$conf['homeurl'].'/templates/'.$conf['theme']."/favicon.ico</Image>\n"
    .'<Image height="16" width="16" type="image/png">'.$conf['homeurl'].'/templates/'.$conf['theme']."/favicon.png</Image>\n"
    .'<Attribution>Copyright (c) SLAED CMS '.$conf['version']."</Attribution>\n"
    .'<Language>'.htmlspecialchars(substr(_LOCALE, 0, 2))."</Language>\n"
    ."</OpenSearchDescription>\n";
}

# Return the processed sitemap XSL template with localized placeholder strings
function getOpenXsl(): string {
    global $conf;
    if (file_exists('config/sitemap/sitemap.xsl')) {
        $file = file_get_contents('config/sitemap/sitemap.xsl');
        $licens = str_replace('&copy;', 'Â©', base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']));
        $title = $conf['sitename'].' - '._SITEMAP;
        $langs = ['$lan[0]' => $title, '$lan[1]' => $licens, '$lan[2]' => _SITEMAP_XML, '$lan[3]' => _URL, '$lan[4]' => _PRIORITY, '$lan[5]' => _CHANGEFREQ, '$lan[6]' => _LASTMOD];
        $cont = strtr($file, $langs);
    } else {
        $cont = '';
    }
    return $cont;
}

# Show statistic
switch(getVar('get', 'stat', 'num', 0)) {
    case 1:
    $img = getVar('get', 'img', 'num', 0) ? '_'.getVar('get', 'img', 'num', 0) : '';
    $sdate = file(CONFIG_DIR.'/statistic.log');
    $con = explode('|', trim($sdate[0]));
    $image = imagecreatefrompng(img_find('banners/stat'.$img.'.png'));
    $white = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 1, 22, 4, $con[2].'/'.$con[1], $white);
    header('Content-type: image/png');
    imagepng($image);
    exit;
}
