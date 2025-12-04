<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function newsletterNavi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['show', 'add', 'info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs(_NEWSLETTER, 'newsletter.png', 'name=newsletter', $ops, $lang, [], [], $tab, $subtab);
}

function newsletter(): void {
    global $prefix, $db, $admin_file, $conf;
    head();
    $cont = newsletterNavi(0, 0, 0, 0);
    $result = $db->sql_query('SELECT id, title, content, mails, send, time, endtime FROM '.$prefix.'_newsletter ORDER BY id');
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._NLEND.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while (list($id, $title, $content, $mails, $sended, $time, $endtime) = $db->sql_fetchrow($result)) {
            $sendtime = ($endtime > $time) ? strtotime($endtime) - strtotime($time) : 0;
            $active = ($mails && $sended && $conf['newsletter']) ? 1 : 0;
            $cont .= '<tr>'
            .'<td>'.$id.'</td>'
            .'<td>'.title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._TIMENL.': '.display_time($sendtime)).$title.'</td>'
            .'<td>'.$sended.' '._NLUSER.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu('<a href="'.$admin_file.'.php?name=newsletter&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?name=newsletter&amp;op=delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function newsletterAdd(): void {
    global $prefix, $db, $admin_file, $conf, $stop;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->sql_query('SELECT title, content, mails FROM '.$prefix.'_newsletter WHERE id = :id', ['id' => $id]);
        list($nid, $title, $content, $mails) = [$id, ...$db->sql_fetchrow($result)];
    } else {
        $nid = getVar('post', 'nid', 'num', '');
        $title = getVar('post', 'title', 'title', '');
        $content = getVar('post', 'content', 'text', $conf['mtemp']);
        $mails = getVar('post', 'mails', '', '');
    }
    $count = getVar('post', 'count', 'num', '');
    $send = getVar('post', 'send', '', '');
    head();
    $cont = newsletterNavi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($content) $cont .= preview($title, $content, '', '', 'all');
    list($num) = $db->sql_fetchrow($db->sql_query('SELECT Count(user_id) FROM '.$prefix.'_users'));
    $sel = ($mails == 1) ? ' selected' : '';
    $option = '<option value="1"'.$sel.'>'._MASSMAIL.' - '.$num.'</option>';
    list($num2) = $db->sql_fetchrow($db->sql_query('SELECT Count(user_id) FROM '.$prefix.'_users WHERE user_newsletter = \'1\''));
    $sel = ($mails == 2) ? ' selected' : '';
    $option .= '<option value="2"'.$sel.'>'._ANEWSLETTER.' - '.$num2.'</option>';
    $result3 = $db->sql_query('SELECT id, name, points FROM '.$prefix.'_groups WHERE extra = \'1\' ORDER BY id');
    if ($db->sql_numrows($result3) > 0) {
        while (list($grid, $grname, $points) = $db->sql_fetchrow($result3)) {
            $result4 = $db->sql_query('SELECT user_email FROM '.$prefix.'_users WHERE user_group = :grid', ['grid' => $grid]);
            $email3 = '';
            $num3 = 0;
            while (list($user_email) = $db->sql_fetchrow($result4)) {
                $email3 .= $user_email.',';
                $num3++;
            }
            $sel = ($email3 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email3.'"'.$sel.'>'._SPEC_GROUP.' "'.$grname.'" - '.$num3.'</option>';
        }
    }
    $result5 = $db->sql_query('SELECT id, name, points FROM '.$prefix.'_groups WHERE extra != \'1\' ORDER BY id');
    if ($db->sql_numrows($result5) > 0) {
        while (list($grid, $grname, $points) = $db->sql_fetchrow($result5)) {
            $result6 = $db->sql_query('SELECT user_email FROM '.$prefix.'_users WHERE user_points >= :points', ['points' => $points]);
            $email4 = '';
            $num4 = 0;
            while (list($user_email) = $db->sql_fetchrow($result6)) {
                $email4 .= $user_email.',';
                $num4++;
            }
            $sel = ($email4 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email4.'"'.$sel.'>'._GROUP.' "'.$grname.'" - '.$num4.'</option>';
        }
    }
    if (is_active('money')) {
        $result7 = $db->sql_query('SELECT mail FROM '.$prefix.'_money WHERE status = \'1\'');
        if ($db->sql_numrows($result7) > 0) {
            $aemail = [];
            while (list($user_email) = $db->sql_fetchrow($result7)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email5 = '';
            $num5 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email5 .= $val.',';
                    $num5++;
                }
            }
            $sel = ($email5 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email5.'"'.$sel.'>'._CLIENTSM.' "'._MONEY.'" - '.$num5.'</option>';
        }
    }
    if (is_active('order')) {
        $result8 = $db->sql_query('SELECT mail FROM '.$prefix.'_order WHERE status = \'1\'');
        if ($db->sql_numrows($result8) > 0) {
            $aemail = [];
            while (list($user_email) = $db->sql_fetchrow($result8)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email6 = '';
            $num6 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email6 .= $val.',';
                    $num6++;
                }
            }
            $sel = ($email6 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email6.'"'.$sel.'>'._CLIENTSM.' "'._ORDER.'" - '.$num6.'</option>';
        }
    }
    if (is_active('shop')) {
        $result9 = $db->sql_query('SELECT email FROM '.$prefix.'_clients');
        if ($db->sql_numrows($result9) > 0) {
            $aemail = [];
            while (list($user_email) = $db->sql_fetchrow($result9)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email7 = '';
            $num7 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email7 .= $val.',';
                    $num7++;
                }
            }
            $sel = ($email7 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email7.'"'.$sel.'>'._CLIENTSM.' "'._SHOP.'" ('._ALL.') - '.$num7.'</option>';
        }
        $result10 = $db->sql_query('SELECT email FROM '.$prefix.'_clients WHERE active = \'1\'');
        if ($db->sql_numrows($result10) > 0) {
            $aemail = [];
            while (list($user_email) = $db->sql_fetchrow($result10)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email8 = '';
            $num8 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email8 .= $val.',';
                    $num8++;
                }
            }
            $sel = ($email8 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email8.'"'.$sel.'>'._CLIENTSM.' "'._SHOP.'" ('._AKTIVE.') - '.$num8.'</option>';
        }
        $result11 = $db->sql_query('SELECT email FROM '.$prefix.'_clients WHERE active = \'0\'');
        if ($db->sql_numrows($result11) > 0) {
            $aemail = [];
            while (list($user_email) = $db->sql_fetchrow($result11)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email9 = '';
            $num9 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email9 .= $val.',';
                    $num9++;
                }
            }
            $sel = ($email9 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email9.'"'.$sel.'>'._CLIENTSM.' "'._SHOP.'" ('._DEAKTIVE.') - '.$num9.'</option>';
        }
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" method="post" action="'.$admin_file.'.php"><table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="50" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'content', $content, 'all', '10', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._NLWHERE.':</td><td><select name="mails" class="sl_form">'.$option.'</select></td></tr>'
    .'<tr><td>'._NLCOUNT.':</td><td><select name="count" class="sl_form">';
    $xusnum = 1;
    while ($xusnum <= 25) {
        $sel = ($xusnum == $count) ? ' selected' : '';
        $cont .= '<option value="'.$xusnum.'"'.$sel.'>'.$xusnum.'</option>';
        $xusnum++;
    }
    $cont .= '</select></td></tr>';
    $cont .= '<tr><td>'._NLSEND.'</td><td>'.radio_form($send, 'send').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="nid" value="'.$nid.'"><input type="hidden" name="name" value="newsletter"><input type="hidden" name="op" value="save"><input type="hidden" name="posttype" value="save"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function newsletterSave(): void {
    global $prefix, $db, $admin_file, $conf, $stop;
    $id = getVar('post', 'nid', 'num', 0);
    $title = getVar('post', 'title', 'title');
    $content = getVar('post', 'content', 'text');
    $mails = getVar('post', 'mails', '');
    $count = getVar('post', 'count', 'num');
    $send = getVar('post', 'send', 'num', 0);
    if (!$title) $stop[] = _CERROR;
    if (!$content) $stop[] = _CERROR1;
    if (!$stop && getVar('post', 'posttype') == 'save') {
        if ($mails == 1) {
            $result = $db->sql_query('SELECT user_email FROM '.$prefix.'_users');
            $emails = [];
            while (list($user_email) = $db->sql_fetchrow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } elseif ($mails == 2) {
            $result = $db->sql_query('SELECT user_email FROM '.$prefix.'_users WHERE user_newsletter = \'1\'');
            $emails = [];
            while (list($user_email) = $db->sql_fetchrow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } else {
            $emails = $mails;
        }
        $emails = ($send) ? $emails : '';
        if ($id) {
            $db->sql_query('UPDATE '.$prefix.'_newsletter SET title = :title, content = :content, mails = :mails, send = \'0\', time = now(), endtime = \'0\' WHERE id = :id', [
                'title' => $title, 'content' => $content, 'mails' => $emails, 'id' => $id
            ]);
        } else {
            $db->sql_query('INSERT INTO '.$prefix.'_newsletter (title, content, mails, send, time, endtime) VALUES (:title, :content, :mails, \'\', now(), \'\')', [
                'title' => $title, 'content' => $content, 'mails' => $emails
            ]);
        }
        $cont = ['newsletter' => $send, 'newslettercount' => $count];
        doConfig('config/config_global.php', 'conf', $cont, $conf, '');
        header('Location: '.$admin_file.'.php?name=newsletter&op=show');
    } else {
        newsletterAdd();
    }
}

function newsletterInfo(): void {
    head();
    echo newsletterNavi(0, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'newsletter').'</div>';
    foot();
}

switch ($op) {
    case 'show':
    newsletter();
    break;

    case 'add':
    newsletterAdd();
    break;

    case 'save':
    newsletterSave();
    break;

    case 'delete':
    $db->sql_query('DELETE FROM '.$prefix.'_newsletter WHERE id = :id', ['id' => $id]);
    header('Location: '.$admin_file.'.php?name=newsletter&op=show');
    break;

    case 'info':
    newsletterInfo();
    break;
}