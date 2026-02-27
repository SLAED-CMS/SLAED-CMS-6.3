<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Show comments and form
function setComShow(int $id = 0, int $cid = 0): string {
    global $conf, $confu, $confc, $user;
    $cont = '<a id="comm"></a><div id="repcsave">'.ashowcom($id, $conf['name']).'</div>';
    if (!is_user() && $confc['anonpost'] == 0) {
        $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOANONCOMMENTS));
    } else {
        $userinfo = getusrinfo();
        if ($cid == 1 || $userinfo['user_acess'] || (!is_user() && $confc['anonpost'] == 1)) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => _POSTNOTE));
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post" id="formcsave" method="post">'
        .'<table class="sl_table_form">';
        if (is_user()) {
            $cont .= '<tr><td>'._YOURNAME.':</td><td>'.text_filter(substr($user[1], 0, 25)).'<input type="hidden" name="name" value=""></td></tr>';
        } else {
            $cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="name" value="'._ANONYM.'" maxlength="25" class="sl_field '.$conf['style'].'"></td></tr>';
        }
        $cont .= '<tr><td>'._COMMENT.':</td><td>'.textarea(1, 'text', '', $conf['name'], '5').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).'<input type="submit" OnClick="AjaxLoad(\'POST\', \'0\', \'csave\', \'go=1&amp;op=savecom&amp;id='.$id.'&amp;cid='.$cid.'&amp;mod='.$conf['name'].'\', { \'text\':\''._CERROR1.'\' }); ClearForm(formcsave); return false;" value="'._COMMENTREPLY.'" title="'._COMMENTREPLY.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    }
    return $cont;
}

# Showing messages on the home page
function setMessageShow() {
    global $db, $afile, $conf, $currentlang;
    if ($conf['message'] == 1) {
        $params = [];
        $querylang = ($conf['multilingual'] == 1) ? 'AND (mlanguage = :lang OR mlanguage = \'\')' : '';
        if ($conf['multilingual'] == 1) {
            $params['lang'] = $currentlang;
        }
        $result = $db->sql_query('SELECT mid, title, content, expire, view FROM '.PREFIX_DB.'_message WHERE active = 1 '.$querylang, $params);
        if ($db->sql_numrows($result) > 0) {
            while (list($mid, $title, $content, $expire, $view) = $db->sql_fetchrow($result)) {
                $mid = intval($mid);
                if ($expire && $expire < time()) $db->sql_query('UPDATE '.PREFIX_DB.'_message SET active = 0, expire = 0 WHERE mid = :mid', ['mid' => $mid]);
                $content = bb_decode($content, 'all');
                $exp = intval($expire - time());
                $exp = ($exp > 0) ? display_time($exp) : _UNLIMITED;
                $info = '| '._PURCHASED.': '.$exp.' | <a href="'.$afile.'.php?op=msg_add&amp;id='.$mid.'" title="'._EDIT.'">'._EDIT.'</a> ]</div>';
                if ($view == 4 && is_moder()) {
                    $content .= '<div class="sl_center">[ '._VIEW.': '._MVADMIN.' '.$info;
                    return setTemplateBasic('messagebox', array('{%title%}' => $title, '{%content%}' => $content));
                } elseif (($view == 3 && is_user()) || ($view == 3 && is_user() && is_moder())) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVUSERS.' '.$info;
                    return setTemplateBasic('messagebox', array('{%title%}' => $title, '{%content%}' => $content));
                } elseif (($view == 2 && !is_user()) || ($view == 2 && !is_user() && is_moder())) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVANON.' '.$info;
                    return setTemplateBasic('messagebox', array('{%title%}' => $title, '{%content%}' => $content));
                } elseif ($view == 1) {
                    if (is_moder()) $content .= '<div class="sl_center">[ '._VIEW.': '._MVALL.' '.$info;
                    return setTemplateBasic('messagebox', array('{%title%}' => $title, '{%content%}' => $content));
                }
            }
        }
    }
}

# User account navigation
function navi() {
    global $conf, $conffav, $confpr;
    $userinfo = getusrinfo();
    $uid = intval($userinfo['user_id']);
    if ($conf['name'] != 'account') get_lang('account');
    
    $title[] = _HOME;
    $ititle[] = _RETURNACCOUNT;
    $link[] = 'index.php?name=account';
    $img[] = 'account/home.png';
    
    if ($confpr['act']) {
        $title[] = _MESSAGES;
        $ititle[] = _PRIVAT;
        $link[] = 'index.php?name=account&amp;op=privat';
        $img[] = 'account/messages.png';
    }
    if (is_active('clients') && is_mod_group('clients')) {
        get_lang('clients');
        $title[] = _PRODUCTS;
        $ititle[] = _PRODUCTSINFO;
        $link[] = 'index.php?name=clients';
        $img[] = 'account/product.png';
    }
    if (is_active('shop')) {
        get_lang('shop');
        $title[] = _CLIENT;
        $ititle[] = _CLIENTINFO;
        $link[] = 'index.php?name=shop&amp;op=clients';
        $img[] = 'account/clients.png';
        $confso = $conf['shop'] ?? [];
        if ($confso['part'] == 1) {
            $title[] = _PARTNER;
            $ititle[] = _PARTNERINFO;
            $link[] = 'index.php?name=shop&amp;op=partners';
            $img[] = 'account/partners.png';
        }
    }
    if (is_active('help') && is_mod_group('help')) {
        get_lang('help');
        $title[] = _HELP;
        $ititle[] = _HELPINFO;
        $link[] = 'index.php?name=help';
        $img[] = 'account/help.png';
    }
    if ($conffav['favact']) {
        $title[] = _FAVORITES;
        $ititle[] = _FAVORITES;
        $link[] = 'index.php?name=account&amp;op=favorites';
        $img[] = 'account/favorites.png';
    }
    $title[] = _INFO;
    $ititle[] = _PERSONALINFO;
    $link[] = 'index.php?name=account&amp;op=view&amp;id='.$uid;
    $img[] = 'account/account.png';
    
    $title[] = _CHANGE;
    $ititle[] = _CHANGE;
    $link[] = 'index.php?name=account&amp;op=edithome';
    $img[] = 'account/preferences.png';
    
    $title[] = _LOGOUT;
    $ititle[] = _LOGOUT;
    $link[] = 'index.php?name=account&amp;op=logout';
    $img[] = 'account/exit.png';
    
    $cont = '';
    foreach ($title as $key => $val) {
        $cont .= '<div class="sl_catflex-box"><a href="'.$link[$key].'" title="'.$ititle[$key].'"><img src="'.img_find($img[$key]).'" alt="'.$ititle[$key].'" title="'.$ititle[$key].'"><br>'.$title[$key].'</a></div>';
    }
    return setTemplateBasic('open', []).'<div class="sl_catflex-cont">'.$cont.'</div>'.setTemplateBasic('close', []);
}

# Check group
function is_mod_group($name) {
    global $db, $user, $confmd;
    if (is_user()) {
        $uid = intval($user[0]);
        $row = $db->sql_fetchrow($db->sql_query('SELECT user_points, user_group FROM '.PREFIX_DB.'_users WHERE user_id = :id', ['id' => $uid]));
        $points = $row['user_points'] ?? 0;
        $group = $row['user_group'] ?? 0;
        $mod_conf = $confmd[$name] ?? [];
        $mgroup = intval($mod_conf['group'] ?? 0);
        $grpoints = 0;
        $grextra = 0;
        if ($mgroup) {
            $ginfo = $db->sql_fetchrow($db->sql_query('SELECT points, extra FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $mgroup]));
            $grpoints = intval($ginfo['points'] ?? 0);
            $grextra = $ginfo['extra'] ?? 0;
        }
        if (intval($group) && $group != '' && $group == $mgroup && $grextra == '1') {
            return 1;
        } elseif ((intval($points) && $points >= $grpoints && $grextra != '1') || $mgroup == 0) {
            return 1;
        }
    }
    return 0;
}

# Get user info
function getusrinfo() {
    global $db, $user;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    if (is_user() && $uid) {
        $info = $db->sql_fetchrow($db->sql_query('SELECT * FROM '.PREFIX_DB.'_users WHERE user_id = :uid', ['uid' => $uid]));
        return $info;
    }
}

# Show user block
function userblock() {
    global $db, $user;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    $block = (isset($user[4])) ? intval($user[4]) : 0;
    if (is_user() && $block) {
        list($userblock) = $db->sql_fetchrow($db->sql_query('SELECT user_block FROM '.PREFIX_DB.'_users WHERE user_id = :uid', ['uid' => $uid]));
        $userblock = bb_decode($userblock, 'account');
        return setTemplateBlock('', array('{%title%}' => _MENUFOR, '{%content%}' => $userblock));
    }
}

# Save comments
function savecom() {
    global $db, $user, $conf, $confc;
    $id       = getVar('post', 'id',   'num',  0);
    $cid      = getVar('post', 'cid',  'num',  0);
    $mod      = analyze(getVar('post', 'mod',  'text', ''));
    $postname = text_filter(substr(getVar('post', 'name', 'raw', ''), 0, 25));
    $ip       = getip();
    $comment  = trim(getVar('post', 'text', 'raw', ''));
    list($date) = $db->sql_fetchrow($db->sql_query('SELECT date FROM '.PREFIX_DB.'_comment WHERE host_name = :ip ORDER BY id DESC LIMIT 1', ['ip' => $ip]));
    $stime = strtotime($date) + $confc['send'];
    $checks = str_replace(array("\n", "\r", "\t"), " ", $comment);
    $e = explode(" ", $checks);
    for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
    $stop = "";
    if ($comment == "") $stop = _CERROR1;
    if ($o > $confc['letter']) $stop = _CERROR2;
    if ((!is_user() && $postname == "") || (!is_user() && $confc['anonpost'] == 0)) $stop = _CERROR3;
    if ($stime > time()) $stop = sprintf(_CERROR5, $confc['send']);
    if (!is_moder($mod) && (($confc['link'] == 1 && !is_user()) || ($confc['link'] == 2)) && stripos($comment, "http://") !== false) $stop = _CERROR9;
    $urlclick = (!is_moder($mod) && (($confc['alink'] == 1 && !is_user()) || ($confc['alink'] == 2))) ? 1 : 0;
    if (checkCaptcha(1)) $stop = _SECCODEINCOR;
    if (!$stop && $id && $mod) {
        $comment = save_text($comment, $urlclick);
        if (is_user()) {
            $postid = intval($user[0]);
            $userinfo = getusrinfo();
            $postname = $userinfo['user_name'];
            $status = (!is_moder($mod) && ($cid == 1 || $userinfo['user_acess'])) ? 0 : 1;
        } else {
            $postid = "0";
            $postname = $postname;
            $status = (!is_moder($mod) && ($cid == 1 || $confc['anonpost'] == 1)) ? 0 : 1;
        }
        $db->sql_query(
            'INSERT INTO '.PREFIX_DB.'_comment VALUES (NULL, :cid, :modul, NOW(), :uid, :name, :host_name, :comment, :status)',
            ['cid' => $id, 'modul' => $mod, 'uid' => $postid, 'name' => $postname, 'host_name' => $ip, 'comment' => $comment, 'status' => $status]
        );
        if ($status) numcom($id, $mod, 0, $postid);
        list($lcom_id) = $db->sql_fetchrow($db->sql_query('SELECT id FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND uid = :uid ORDER BY id DESC LIMIT 1', ['cid' => $id, 'uid' => $postid]));
        $finishlink = $conf['homeurl']."/index.php?name=".$mod."&amp;op=view&amp;id=".$id."#".$lcom_id;
        $clink = "<a href=\"".$finishlink."\">".$finishlink."</a>";
        addmail($confc['addmail'], $mod, $postname, deflmconst($mod), 1, $clink);
        echo ashowcom($id, $mod);
    } else {
        $stop = ($stop) ? $stop : _ERROR;
        echo setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Save edit forum post
function editpost() {
    global $db, $user, $conf;
    $conffo = $conf['forum'] ?? [];
    $id    = getVar('post', 'id',  'num',  0)  ?: getVar('get', 'id',  'num',  0);
    $catid = getVar('post', 'cid', 'num',  0)  ?: getVar('get', 'cid', 'num',  0);
    $typ   = getVar('post', 'typ', 'num',  0)  ?: getVar('get', 'typ', 'num',  0);
    $mod   = analyze(getVar('post', 'mod', 'text', '') ?: getVar('get', 'mod', 'text', ''));
    $text  = trim(getVar('post', 'text', 'raw', ''));
    if ($conffo['add'] && $id && $catid) {
        list($auth_edit, $auth_mod) = $db->sql_fetchrow($db->sql_query('SELECT auth_edit, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $isedit = is_acess($auth_edit);
        $ismod = is_acess($auth_mod);
        list($pid, $uid, $hometext, $fstatus) = $db->sql_fetchrow($db->sql_query('SELECT pid, uid, hometext, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($pid) {
            if (is_moder(isset($conf['name']))) {
                list($fstatus) = $db->sql_fetchrow($db->sql_query('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
            } else {
                list($fstatus) = $db->sql_fetchrow($db->sql_query('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid AND status != 0', ['pid' => $pid]));
            }
        }
        if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
            if (!$text) {
                $content = ($typ) ? textareae("for".$id, "1", "editpost", $id, $catid, "0", $mod, $hometext, "15") : bb_decode($hometext, $mod);
                echo $content;
            } else {
                $postid = (is_user()) ? intval($user[0]) : "";
                $ip = getip();
                $checks = str_replace(array("\n", "\r", "\t"), " ", $text);
                $e = explode(" ", $checks);
                for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
                $stop = "";
                if ($text == "") $stop[] = _CERROR1;
                if ($o > $conffo['letter']) $stop[] = _CERROR2;
                if (!$stop) {
                    $htext = save_text($text);
                    $db->sql_query(
                        'UPDATE '.PREFIX_DB.'_forum SET hometext = :hometext, e_uid = :e_uid, e_ip_send = :e_ip_send, e_time = NOW() WHERE id = :id',
                        ['hometext' => $htext, 'e_uid' => $postid, 'e_ip_send' => $ip, 'id' => $id]
                    );
                    echo bb_decode($htext, $mod);
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

# Private messages input view
function prmess(int $obj = 0, string $stop = '', string $info = '', int $typ = 0): string {
    global $db, $user, $conf, $confu, $confpr;
    $typ = $typ ?: getVar('get', 'typ', 'num', 0);
    $uid = intval($user[0]);
    $newlistnum = intval($confpr['num']);
    $num = getVar('get', 'cid', 'num', 1);
    $offset = ($num-1) * $newlistnum;
    $offset = intval($offset);
    $conf['name'] = "account";
    $conf['style'] = ($conf['style']) ? $conf['style'] : "sl_account";
    $cont = "";
    if ($typ == 1) {
        list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status <= 1', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $confpr['messin']) {
            $messinfo = sprintf(_PRINEXIT, $confpr['messin']);
            $fstatus = "warn";
        } elseif ($pr_num >= ($confpr['messin'] / 2)) {
            $acmess = ($confpr['messin'] - $pr_num);
            $messinfo = sprintf(_PRINMAX, $confpr['messin'], $pr_num, $acmess);
            $fstatus = "info";
        }
        if ($fstatus) $cont .= setTemplateWarning('warn', ['text' => $messinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
        if ($stop) {
            $cont .= setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
        } elseif ($info) {
            $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'info']);
        }
        $result = $db->sql_query('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.user_id) WHERE p.uidin = :uid AND p.status <= 1 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= "<table class=\"sl_table_list\"><thead class=\"sl_table_list_head\"><tr><th>"._TITLE."</th><th>"._PRSE."</th><th>"._DATE."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_list_body\">";
            while (list($id, $uidin, $uidout, $title, $date, $status, $user_name) = $db->sql_fetchrow($result)) {
                if ($status) {
                    $ititle = _PROLD;
                    $hidden = " sl_hidden";
                } else {
                    $ititle = _PRNEW;
                    $hidden = "";
                }
                $title = "<span title=\"".$ititle."\" class=\"sl_m_in".$hidden."\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=1', ''); return false;\" title=\"".$title."\">".cutstr($title, 35)."</a>";
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=1', ''); return false;\" title=\""._SHOW."\">"._SHOW."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmesssave&amp;id=".$id."', ''); return false;\" title=\""._SAVE."\">"._SAVE."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=1', ''); return false;\" title=\""._DELETE."\">"._DELETE."</a>");
                $cont .= "<tr><td>".$title."</td><td>".$post."</td><td>".$date."</td><td>".$func."</td></tr>";
            }
            $cont .= "</tbody></table>";
        } else {
            $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
        }
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax("pagenum", $pr_num, $numpages, $newlistnum, $confpr['nump'], $num, "0", "1", "prmess", "prmessin", "", "1", "");
    } elseif ($typ == 2) {
        $result = $db->sql_query('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.user_id) WHERE p.uidout = :uid AND p.status <= 1 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= "<table class=\"sl_table_list\"><thead class=\"sl_table_list_head\"><tr><th>"._TITLE."</th><th>"._PRRE."</th><th>"._DATE."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_list_body\">";
            while (list($id, $uidin, $uidout, $title, $date, $status, $user_name) = $db->sql_fetchrow($result)) {
                if ($status) {
                    $ititle = _PROLD;
                    $hidden = " sl_hidden";
                    $del = "";
                } else {
                    $ititle = _PROUTNEW;
                    $hidden = "";
                    $del = "||<a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=2', ''); return false;\" title=\""._DELETE."\">"._DELETE."</a>";
                }
                $title = "<span title=\"".$ititle."\" class=\"sl_m_out".$hidden."\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=2&amp;typ=4&amp;mod=2', ''); return false;\" title=\"".$title."\">".cutstr($title, 35)."</a>";
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=2&amp;typ=4&amp;mod=2', ''); return false;\" title=\""._SHOW."\">"._SHOW."</a>".$del);
                $cont .= "<tr><td>".$title."</td><td>".$post."</td><td>".$date."</td><td>".$func."</td></tr>";
            }
            $cont .= "</tbody></table>";
        } else {
            $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
        }
        list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidout = :uid AND status <= 1', ['uid' => $uid]));
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax("pagenum", $pr_num, $numpages, $newlistnum, $confpr['nump'], $num, "0", "1", "prmess", "prmessou", "", "2", "");
    } elseif ($typ == 3) {
        list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $confpr['messsav']) {
            $messinfo = sprintf(_PRSAVEEXIT, $confpr['messsav']);
            $fstatus = "warn";
        } elseif ($pr_num >= ($confpr['messsav'] / 2)) {
            $acmess = ($confpr['messsav'] - $pr_num);
            $messinfo = sprintf(_PRSAVEMAX, $confpr['messsav'], $pr_num, $acmess);
            $fstatus = "info";
        }
        if ($fstatus) $cont .= setTemplateWarning('warn', ['text' => $messinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
        $result = $db->sql_query('SELECT p.id, p.uidin, p.uidout, p.title, p.date, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout=u.user_id) WHERE p.uidin = :uid AND p.status = 2 ORDER BY p.date DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= "<table class=\"sl_table_list\"><thead class=\"sl_table_list_head\"><tr><th>"._TITLE."</th><th>"._PRSE."</th><th>"._DATE."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_list_body\">";
            while (list($id, $uidin, $uidout, $title, $date, $status, $user_name) = $db->sql_fetchrow($result)) {
            $title = "<span title=\""._PRMOVE."\" class=\"sl_m_save\"></span><a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=3', ''); return false;\" title=\"".$title."\">".cutstr($title, 35)."</a>";
                $post = ($user_name) ? user_info($user_name) : _ANONYM;
                $date = format_time($date, _TIMESTRING);
                $func = add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmess&amp;id=".$id."&amp;cid=1&amp;typ=4&amp;mod=3', ''); return false;\" title=\""._SHOW."\">"._SHOW."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmessdel&amp;id=".$id."&amp;typ=3', ''); return false;\" title=\""._DELETE."\">"._DELETE."</a>");
                $cont .= "<tr><td>".$title."</td><td>".$post."</td><td>".$date."</td><td>".$func."</td></tr>";
            }
            $cont .= "</tbody></table>";
        } else {
            $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
        }
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= num_ajax("pagenum", $pr_num, $numpages, $newlistnum, $confpr['nump'], $num, "0", "1", "prmess", "prmesssa", "", "3", "");
    } elseif ($typ == 4) {
        if ($stop) {
            $cont .= setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
        } elseif ($info) {
            $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'info']);
        }
        $id  = getVar('get', 'id',  'num', 0);
        $qid = getVar('get', 'cid', 'num', 0);
        $mod = getVar('get', 'mod', 'num', 0);
        if ($mod == 1) {
            $prmid = "prmessin";
        } elseif ($mod == 2) {
            $prmid = "prmessou";
        } elseif ($mod == 3) {
            $prmid = "prmesssa";
        } else {
            $prmid = "prmessfo";
        }
        if ($id) {
            if ($qid == "2") {
                list($idp, $uidin, $uidout, $title, $content, $date, $ip_sender, $status, $user_name) = $db->sql_fetchrow($db->sql_query('SELECT p.id, p.uidin, p.uidout, p.title, p.content, p.date, p.ip_sender, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.user_id) WHERE p.id = :id AND p.uidout = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
            } else {
                list($idp, $uidin, $uidout, $title, $content, $date, $ip_sender, $status, $user_name) = $db->sql_fetchrow($db->sql_query('SELECT p.id, p.uidin, p.uidout, p.title, p.content, p.date, p.ip_sender, p.status, u.user_name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.user_id) WHERE p.id = :id AND p.uidin = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
                if (!$status) $db->sql_query('UPDATE '.PREFIX_DB.'_privat SET status = 1 WHERE id = :id AND uidin = :uid AND status != 2', ['id' => $id, 'uid' => $uid]);
            }
            if ($idp) {
                # UNBEKANTE VARIABLEN INITIALISIERUNG VERHINDERN
                $com_name = $com_id = "";

                $result = $db->sql_query('SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_from, u.user_sig, u.user_viewemail, u.user_points, u.user_warnings, u.user_gender, u.user_votes, u.user_totalvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra=1 AND u.user_group=g.id) OR (g.extra!=1 AND u.user_points>=g.points)) WHERE u.user_id = :uidout ORDER BY g.extra DESC, g.points DESC', ['uidout' => $uidout]);
                list($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor) = $db->sql_fetchrow($result);
                $avname = ($user_name) ? $user_name : $com_name." ("._ANONYM.")";
                $date = "<span title=\""._PADD."\" class=\"sl_t_post\">".format_time($date, _TIMESTRING)."</span>";
                $ip = (is_moder($conf['name'])) ? user_geo_ip($ip_sender, 4) : "";
                $avatar = ($user_name) ? (($user_avatar && file_exists($confu['adirectory']."/".$user_avatar)) ? $confu['adirectory']."/".$user_avatar : $confu['adirectory']."/default/00.gif") : $confu['adirectory']."/default/0.gif";
                $rank = ($user_rank) ? $user_rank : "";
                $trank = ($user_gname) ? _GROUP.": ".$user_gname : _RANK;
                $rlink = ($user_grank && file_exists(img_find("ranks/".$user_grank))) ? "<img src=\"".img_find("ranks/".$user_grank)."\" alt=\"".$trank."\" title=\"".$trank."\">" : "";
                $rate = ajax_rating(0, $user_id, $conf['name'], $user_votes, $user_totalvotes, $com_id, 1);
                $rwarn = ($user_warnings) ? _UWARNS.": ".warnings($user_warnings) : "";
                $group = ($user_gname) ? _GROUP.": <span style=\"color: ".$user_gcolor."\">".$user_gname."</span>" : "";
                $point = ($confu['point'] && $user_points) ? _POINTS.": ".$user_points : "";
                $regdate = ($user_regdate) ? _REG.": ".format_time($user_regdate) : _NO_INFO;
                $gender = ($user_gender) ? _GENDER.": ".gender($user_gender) : "";
                $from = ($user_from) ? _FROM.": ".$user_from : "";
                $sig = ($user_sig) ? "<hr>".$user_sig : "";
                $profil = ($confpr['profil'] && $user_name) ? "<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($user_name)."\" title=\""._PERSONALINFO."\" class=\"sl_but\">"._ACCOUNT."</a>" : "";
                $web = ($confpr['web'] && $user_website) ? "<a href=\"".$user_website."\" target=\"_blank\" title=\""._DOWNLLINK."\" class=\"sl_but\">"._SITE."</a>" : "";
                

                
                $edit = (($uidin == $uid) || ($uidout == $uid && !$status)) ? add_menu("<a OnClick=\"AjaxLoad('GET', '0', '".$prmid."', 'go=1&amp;op=prmessdel&amp;id=".$idp."&amp;typ=".$mod."', ''); return false;\" title=\""._ONDELETE."\">"._ONDELETE."</a>") : "";
                $cont .= setTemplateBasic("privat-message", ['{%username%}' => $avname, '{%date%}' => $date, '{%ip%}' => $ip, '{%title%}' => cutstr($title, 35), '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => bb_decode($content, $conf['name']), '{%sig%}' => bb_decode($sig, $conf['name']), '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_edit%}' => $edit]);
            }
        }
        if (!$info && (!$qid || $qid == "1")) {
            $raw = getVar('post', 'name', 'raw', '') ?: urldecode(getVar('get', 'uname', 'raw', ''));
            $sname = text_filter(substr($raw, 0, 25));
            $stitle = text_filter(trim(getVar('post', 'title', 'raw', '')));
            $stext = text_filter(trim(getVar('post', 'text', 'raw', '')));
            $rpost = ($sname) ? $sname : (($user_name ?? '') ? $user_name : "");
            $rtitle = ($stitle) ? $stitle : (($title ?? '') ? _PRREP.": ".$title : "");
            $rcontent = ($stext) ? $stext : (($content ?? '') ? "[quote]".$content."[/quote]" : "");
            
            $idp = ($id) ? "2" : "1";
            $cont .= "<form name=\"post\" id=\"form".$prmid."\" method=\"post\">"
            ."<table class=\"sl_table_form\">"
            ."<tr><td>"._PRRE.":</td><td>".get_user_search("name", $rpost, "25", $conf['style'], "1")."</td></tr>"
            ."<tr><td>"._TITLE.":</td><td><input type=\"text\" name=\"title\" value=\"".$rtitle."\" maxlength=\"100\" class=\"sl_field ".$conf['style']."\"></td></tr>"
            ."<tr><td>"._MESSAGE.":</td><td>".textarea($idp, "text", $rcontent, $conf['name'], "15")."</td></tr>"
            ."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"submit\" OnClick=\"AjaxLoad('POST', '0', '".$prmid."', 'go=1&amp;op=prmesssend', { 'name':'"._CERROR6."' }); return false;\" value=\""._SEND."\" title=\""._SEND."\" class=\"sl_but_blue\"></td></tr></table></form>";
        }
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Private message send and save
function prmesssend() {
    global $db, $user, $conf, $confpr;
    $postname = text_filter(substr(getVar('post', 'name',  'raw', ''), 0, 25));
    $title    = trim(getVar('post', 'title', 'raw', ''));
    $text     = trim(getVar('post', 'text',  'raw', ''));
    $ip = getip();

    $uidin = (is_user_id($postname)) ? is_user_id($postname) : "";
    $uidout = (is_user()) ? intval($user[0]) : "";
    
    list($date) = $db->sql_fetchrow($db->sql_query('SELECT date FROM '.PREFIX_DB.'_privat WHERE uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidout' => $uidout]));
    $stime = strtotime($date) + $confpr['send'];
    $checks = str_replace(array("\n", "\r", "\t"), " ", $text);
    $e = explode(" ", $checks);
    for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
    
    $stop = array();
    if (!$postname) {
        $stop[] = _CERROR6;
    } elseif (!$uidin) {
        $stop[] = _CERROR7;
    }
    if ($confpr['himself'] && $uidin == $uidout) $stop[] = _CERROR8;
    if (!$title) $stop[] = _CERROR;
    if (!$text) $stop[] = _CERROR1;
    if ($o > $confpr['letter']) $stop[] = _CERROR2;
    if (!$uidout) $stop[] = _CERROR3;
    if ($stime > time()) $stop[] = sprintf(_CERROR5, $confpr['send']);

    list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND status <= 1', ['uidin' => $uidin]));
    if ($pr_num >= $confpr['messin']) $stop[] = sprintf(_PRSENDOVER, $postname);
    
    if (!$stop && $confpr['act'] && is_user()) {
        $title = save_text($title, 1);
        $text = save_text($text);
        $db->sql_query('INSERT INTO '.PREFIX_DB.'_privat VALUES (NULL, :uidin, :uidout, :title, :content, NOW(), :ip_sender, 0)', ['uidin' => $uidin, 'uidout' => $uidout, 'title' => $title, 'content' => $text, 'ip_sender' => $ip]);
        update_points(45);
        if ($confpr['newmail']) {
            list($user_email, $user_psmail) = $db->sql_fetchrow($db->sql_query('SELECT user_email, user_psmail FROM '.PREFIX_DB.'_users WHERE user_id = :uidin', ['uidin' => $uidin]));
            if ($user_email && $user_psmail) {
                list($id) = $db->sql_fetchrow($db->sql_query('SELECT id FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidin' => $uidin, 'uidout' => $uidout]));
                $uname = text_filter(substr($user[1], 0, 25));
                $finishlink = $conf['homeurl']."/index.php?name=account&amp;op=privat&amp;id=".$id."#prmess";
                $link = "<a href=\"".$finishlink."\">".$finishlink."</a>";
                $subject = $conf['sitename']." - "._PRIVAT;
                $message = str_replace("[text]", sprintf(_PRNEWMAIL, $uname, $link), $conf['mtemp']);
                mail_send($user_email, $conf['adminmail'], $subject, $message, 0, 3);
            }
        }
        $info = sprintf(_PRSENDED, $postname);
        return prmess(0, 0, $info, 4);
    } else {
        $stop = ($stop) ? $stop : _ERROR;
        return prmess(0, $stop, 0, 4);
    }
}

# Private message save to user
function prmesssave() {
    global $db, $user, $confpr;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    list($pr_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
    $pr_numi = $pr_num + 1;
    if ($pr_num >= $confpr['messsav']) {
        $stop = sprintf(_PRSAVEEXIT, $confpr['messsav']);
        $info = 0;
    } elseif ($pr_numi >= ($confpr['messsav'] / 2)) {
        $acmess = ($confpr['messsav'] - $pr_numi);
        $stop = 0;
        $info = sprintf(_PRSAVEMAX, $confpr['messsav'], $pr_numi, $acmess);
    }
    if (!$stop && $confpr['act'] && $uid && $id) $db->sql_query('UPDATE '.PREFIX_DB.'_privat SET status = 2 WHERE id = :id AND uidin = :uid', ['id' => $id, 'uid' => $uid]);
    return prmess(0, $stop, $info, 1);
}

# Private message delete
function prmessdel() {
    global $db, $user, $confpr;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id  = getVar('get', 'id',  'num', 0);
    $typ = getVar('get', 'typ', 'num', 1);
    if ($confpr['act'] && $uid && $id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_privat WHERE (id = :id_in AND uidin = :uid_in) OR (id = :id_out AND uidout = :uid_out AND status = 0)', ['id_in' => $id, 'uid_in' => $uid, 'id_out' => $id, 'uid_out' => $uid]);
    return prmess(0, 0, 0, $typ);
}

# Favorites view
function favorview($fid, $mod) {
    global $db, $user, $conffav;
    $uid = (is_user()) ? intval($user[0]) : 0;
    if ($conffav['favact'] && $uid) {
        list($fav) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid AND fid = :fid AND modul = :modul', ['uid' => $uid, 'fid' => $fid, 'modul' => $mod]));
        if ($fav) {
            $content = "<span title=\""._FAVOR."\" class=\"sl_favor sl_favor_on\"></span>";
        } else {
            list($fav_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
            if ($fav_num >= $conffav['favorites']) {
                $fav_exit = sprintf(_FAVOR_EXIT, $conffav['favorites']);
                $content = "<span title=\"".$fav_exit."\" class=\"sl_favor sl_favor_off\"></span>";
            } else {
                $content = "<span id=\"rep".$fid.$mod."\"><span OnClick=\"AjaxLoad('GET', '0', '".$fid.$mod."', 'go=1&amp;op=favoradd&amp;id=".$fid."&amp;mod=".$mod."', ''); return false;\" title=\""._FAVOR_ADD."\" class=\"sl_favor\"></span></span>";
            }
        }
        return $content;
    }
}

# Favorites add
function favoradd() {
    global $db, $user, $conffav;
    $fid = getVar('get', 'id',  'num',  0);
    $mod = analyze(getVar('get', 'mod', 'text', ''));
    $uid = (is_user()) ? intval($user[0]) : 0;
    if ($conffav['favact'] && $uid && $fid && $mod) {
        list($fav) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid AND fid = :fid AND modul = :modul', ['uid' => $uid, 'fid' => $fid, 'modul' => $mod]));
        if ($fav) {
            echo favorview($fid, $mod);
        } else {
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_favorites VALUES (NULL, :uid, :fid, :modul)', ['uid' => $uid, 'fid' => $fid, 'modul' => $mod]);
            update_points(44);
        }
    }
    echo favorview($fid, $mod);
}

# Favorites liste view
function favorliste(int $obj = 0): string {
    global $db, $user, $conffav, $conf;
    $uid = intval($user[0]);
    $newlistnum = intval($conffav['num']);
    $num = getVar('get', 'cid', 'num', 1);
    $offset = ($num-1) * $newlistnum;
    $offset = intval($offset);
    $a = ($num) ? $offset+1 : 1;
    
    list($fav_num) = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
    if ($fav_num >= $conffav['favorites']) {
        $favinfo = sprintf(_FAVOR_EXIT, $conffav['favorites']);
        $fstatus = "warn";
    } else {
        $acfavor = ($conffav['favorites'] - $fav_num);
        $favinfo = sprintf(_FAVOR_MAX, $conffav['favorites'], $fav_num, $acfavor);
        $fstatus = "info";
    }
    
    $result = $db->sql_query('SELECT fid, modul FROM '.PREFIX_DB.'_favorites WHERE uid = :uid ORDER BY id DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
    while (list($fid, $modul) = $db->sql_fetchrow($result)) $fmassiv[$modul][] = $fid;
    
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
            if ($key == "faq") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_faq AS n ON (f.fid=n.fid) WHERE f.uid = :uid AND n.fid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "files") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_files AS n ON (f.fid=n.lid) WHERE f.uid = :uid AND n.lid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "forum") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_forum AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "help") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_help AS n ON (f.fid=n.sid) WHERE f.uid = :uid AND n.sid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "links") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_links AS n ON (f.fid=n.lid) WHERE f.uid = :uid AND n.lid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "media") {
                $confm = $conf['media'] ?? [];
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title, n.subtitle FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_media AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $subtitle) = $db->sql_fetchrow($result)) {
                    $title = ($subtitle) ? $title." ".urldecode($confm['mdefis'])." ".$subtitle : $title;
                    $ffmassiv[] = array($id, $fid, $modul, $title);
                }
            } elseif ($key == "news") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_news AS n ON (f.fid=n.sid) WHERE f.uid = :uid AND n.sid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "pages") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_pages AS n ON (f.fid=n.pid) WHERE f.uid = :uid AND n.pid IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            } elseif ($key == "shop") {
                $result = $db->sql_query('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_products AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title) = $db->sql_fetchrow($result)) $ffmassiv[] = array($id, $fid, $modul, $title);
            }
        }
    }
    $cont = setTemplateWarning('warn', ['text' => $favinfo, 'url' => '', 'time' => 0, 'id' => $fstatus]);
    if ($ffmassiv) {
        $cont .= "<table class=\"sl_table_list\"><thead class=\"sl_table_list_head\"><tr><th>"._ID."</th><th>"._TITLE."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_list_body\">";
        foreach ($ffmassiv as $key => $val) {
            $id = $val[0];
            $fid = $val[1];
            $modul = $val[2];
            $title = $val[3];
            $surl = "index.php?name=".$modul."&amp;op=view&amp;id=".$fid;
            $cont .= "<tr id=\"".$a."\">"
            ."<td><a href=\"#".$a."\" title=\"".$a."\" class=\"sl_pnum\">".$a."</a></td>"
            ."<td><a href=\"".$surl."\" title=\"".$title."\">".cutstr($title, 100)."</a></td>"
            ."<td>".add_menu("<a href=\"index.php?name=".$modul."&amp;op=view&amp;id=".$fid."\" title=\""._SHOW."\">"._SHOW."</a>||<a href=\"index.php?name=".$modul."&amp;op=view&amp;id=".$fid."\" rel=\"sidebar\" title=\"".$title."\">"._S_FAVORITEN."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'favorliste', 'go=1&amp;op=favordel&amp;id=".$id."', ''); return false;\" title=\""._DELETE."\">"._DELETE."</a>")."</td>";
            $a++;
        }
        $cont .= "</tbody></table>";
        $numpages = ceil($fav_num / $newlistnum);
        $cont .= num_ajax("pagenum", $fav_num, $numpages, $newlistnum, $conffav['nump'], $num, "0", "1", "favorliste", "favorliste", "", "", "");
    } else {
        $cont = setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Favorites delete
function favordel() {
    global $db, $user, $conffav;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    if ($conffav['favact'] && $uid && $id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id AND uid = :uid', ['id' => $id, 'uid' => $uid]);
    return favorliste(0);
}

# RSS Channel
function rss_channel() {
    global $db, $conf, $confrs, $confu;
    get_lang();
    header_remove("X-Content-Type-Options");
    header("Content-Type: application/rss+xml; charset="._CHARSET);
    header("Content-Encoding: none");

    $name = analyze(getVar('post', 'name', 'text', '') ?: getVar('get', 'name', 'text', ''));
    $hmodul = explode(",", $conf['module']);
    $hi = mt_rand(0, count($hmodul) - 1);
    $cname = $hmodul[$hi];
    $name = ($name) ? $name : $cname;
    $cat  = getVar('post', 'cat', 'num', 0) ?: getVar('get', 'cat', 'num', 0);
    $num  = getVar('post', 'num', 'num', 0) ?: getVar('get', 'num', 'num', 0);
    $num = ($num) ? (($num <= $confrs['max']) ? $num : $confrs['max']) : $confrs['min'];
    $id   = getVar('post', 'id',  'num', 0) ?: getVar('get', 'id',  'num', 0);

    if (($name == "content") && $id) {
        $result = $db->sql_query('SELECT id, title, text, time FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    } elseif ($name == "faq") {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.fid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == "files") {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.lid, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_files AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == "links") {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.lid, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_links AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == "media") {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.date <= NOW() AND s.status != 0' : 'WHERE s.date <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.id, s.name, s.title, s.date, s.description, c.title, u.user_name FROM '.PREFIX_DB.'_media AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.date DESC LIMIT '.intval($num), $params);
    } elseif ($name == "pages") {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.pid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_pages AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == "shop") {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.active = 1' : 'WHERE s.time <= NOW() AND s.active = 1';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.id, s.title, s.time, s.text, c.title FROM '.PREFIX_DB.'_products AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == "news") {
        $params = [];
        $where = $cat ? 'WHERE s.catid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->sql_query('SELECT s.sid, s.name, s.title, s.time, s.hometext, c.title, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
        $name = "news";
    } else {
        $result = "";
        $name = "";
    }

    $content = "<?xml version=\"1.0\" encoding=\""._CHARSET."\"?>\n"
    ."<rss version=\"2.0\">\n"
    ."<channel>\n"
    ."<title>".htmlspecialchars($conf['sitename'])."</title>\n"
    ."<link>".$conf['homeurl']."</link>\n"
    ."<description>".htmlspecialchars($conf['slogan'])."</description>\n"
    ."<generator>SLAED CMS ".$conf['version']."</generator>\n"
    ."<copyright>Copyright (c) SLAED CMS ".$conf['version']."</copyright>\n"
    ."<language>".htmlspecialchars(substr(_LOCALE, 0, 2))."</language>\n"
    ."<lastBuildDate>".date("D, j M Y H:m:s O")."</lastBuildDate>\n\n";
    if ($name && $name != "content" && $name != "shop" && $result) {
        while (list($rid, $uname, $rtitle, $rtime, $rhometext, $rctitle, $user_name) = $db->sql_fetchrow($result)) {
            $rauthor = ($user_name) ? $user_name : (($uname) ? $uname : _ANONYM);
            $content .= "<item>\n"
            ."<title>".htmlspecialchars($rtitle)."</title>\n"
            ."<pubDate>".htmlspecialchars(date("D, j M Y H:m:s O", strtotime($rtime)))."</pubDate>\n"
            ."<guid>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</guid>\n"
            ."<link>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</link>\n"
            ."<description>".htmlspecialchars(bb_decode($rhometext, $name, 1))."</description>\n"
            ."<comments>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."#".$rid."</comments>\n";
            $content .= ($rctitle) ? "<category>".htmlspecialchars($rctitle)."</category>\n" : "";
            $content .= "<author>antispam@antispam.com (".htmlspecialchars($rauthor).")</author>\n"
            ."</item>\n\n";
        }
    } elseif ($name && $name == "content" && $result) {
        list($rid, $rtitle, $rhometext, $rtime) = $db->sql_fetchrow($result);
        $content .= "<item>\n"
        ."<title>".htmlspecialchars($rtitle)."</title>\n"
        ."<pubDate>".htmlspecialchars(date("D, j M Y H:m:s O", strtotime($rtime)))."</pubDate>\n"
        ."<guid>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</guid>\n"
        ."<link>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</link>\n"
        ."<description>".htmlspecialchars(bb_decode($rhometext, $name))."</description>\n"
        ."</item>\n\n";
    } elseif ($name && $name == "shop" && $result) {
        while (list($rid, $rtitle, $rtime, $rhometext, $rctitle) = $db->sql_fetchrow($result)) {
            $content .= "<item>\n"
            ."<title>".htmlspecialchars($rtitle)."</title>\n"
            ."<pubDate>".htmlspecialchars(date("D, j M Y H:m:s O", strtotime($rtime)))."</pubDate>\n"
            ."<guid>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</guid>\n"
            ."<link>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."</link>\n"
            ."<description>".htmlspecialchars(bb_decode($rhometext, $name))."</description>\n"
            ."<comments>".$conf['homeurl']."/index.php?name=".$name."&amp;op=view&amp;id=".$rid."#".$rid."</comments>\n";
            $content .= ($rctitle) ? "<category>".htmlspecialchars($rctitle)."</category>\n" : "";
            $content .= "</item>\n\n";
        }
    }
    $content .= "</channel>\n</rss>";
    return $content;
}

# Open search
function open_search() {
    global $conf;
    get_lang();
    header("Content-Type: application/opensearchdescription+xml");
    header("Content-Encoding: none");
    return "<?xml version=\"1.0\" encoding=\""._CHARSET."\"?>\n"
    ."<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\">\n"
    ."<ShortName>".htmlspecialchars($conf['sitename'])."</ShortName>\n"
    ."<Description>".htmlspecialchars($conf['slogan'])."</Description>\n"
    ."<Url type=\"application/atom+xml\" template=\"".$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    ."<Url type=\"application/rss+xml\" template=\"".$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    ."<Url type=\"text/html\" template=\"".$conf['homeurl']."/index.php?name=search&amp;word={searchTerms}\"/>\n"
    ."<Image height=\"16\" width=\"16\" type=\"image/x-icon\">".$conf['homeurl']."/templates/".$conf['theme']."/favicon.ico</Image>\n"
    ."<Image height=\"16\" width=\"16\" type=\"image/png\">".$conf['homeurl']."/templates/".$conf['theme']."/favicon.png</Image>\n"
    ."<Attribution>Copyright (c) SLAED CMS ".$conf['version']."</Attribution>\n"
    ."<Language>".htmlspecialchars(substr(_LOCALE, 0, 2))."</Language>\n"
    ."</OpenSearchDescription>\n";
}

# Open xsl template
function open_xsl() {
    global $conf;
    if (file_exists('config/sitemap/sitemap.xsl')) {
        $file = file_get_contents('config/sitemap/sitemap.xsl');
        $licens = str_replace('&copy;', '©', base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']));
        $title = $conf['sitename'].' - '._SITEMAP;
        $langs = array('$lan[0]' => $title, '$lan[1]' => $licens, '$lan[2]' => _SITEMAP_XML, '$lan[3]' => _URL, '$lan[4]' => _PRIORITY, '$lan[5]' => _CHANGEFREQ, '$lan[6]' => _LASTMOD);
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


