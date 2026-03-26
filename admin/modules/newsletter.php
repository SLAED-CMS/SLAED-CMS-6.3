<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function newsletter(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, title, mails, send, time, endtime FROM '.PREFIX_DB.'_newsletter ORDER BY id');
    if ($db->getSqlRowCount($result) > 0) {
        $head = '<th>'._ID.'</th><th>'._TITLE.'</th><th>'._NLEND.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>';
        $rows = '';
        while ([$id, $title, $mails, $sended, $time, $endtime] = $db->getSqlRow($result)) {
            $sendtime = ($endtime > $time) ? strtotime($endtime) - strtotime($time) : 0;
            $active = ($mails && $sended && $conf['newsletter']['active']) ? 1 : 0;
            $acts = adminMenuItems([
                '<a href="'.$afile.'.php?name=newsletter&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>',
                '<a href="'.$afile.'.php?name=newsletter&amp;op=delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>',
            ]);
            $cols = '<td>'.$id.'</td>'
            .'<td>'.title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._TIMENL.': '.getDuration($sendtime)).$title.'</td>'
            .'<td>'.$sended.' '._NLUSER.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.$acts.'</td>';
            $rows .= getAdminTableRow($cols);
        }
        $cont .= getAdminTable($head, $rows);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT title, body, mails FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]);
        [$nid, $title, $body, $mails] = [$id, ...$db->getSqlRow($result)];
    } else {
        $nid = getVar('post', 'nid', 'num', '');
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', $conf['mtemp']);
        $mails = getVar('post', 'mails', '', '');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    if ($body) $cont .= preview($title, $body, '', '', 'all');
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users'));
    $sel = ($mails == 1) ? ' selected' : '';
    $option = '<option value="1"'.$sel.'>'._MASSMAIL.' - '.$num.'</option>';
    [$num2] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users WHERE newslet = 1'));
    $sel = ($mails == 2) ? ' selected' : '';
    $option .= '<option value="2"'.$sel.'>'._ANEWSLETTER.' - '.$num2.'</option>';
    $result3 = $db->getSqlQuery('SELECT id, name, points FROM '.PREFIX_DB.'_groups WHERE extra = 1 ORDER BY id');
    if ($db->getSqlRowCount($result3) > 0) {
        while ([$grid, $grname, $points] = $db->getSqlRow($result3)) {
            $result4 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE grp = :grid', ['grid' => $grid]);
            $email3 = '';
            $num3 = 0;
            while ([$user_email] = $db->getSqlRow($result4)) {
                $email3 .= $user_email.',';
                $num3++;
            }
            $sel = ($email3 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email3.'"'.$sel.'>'._SPEC_GROUP.' "'.$grname.'" - '.$num3.'</option>';
        }
    }
    $result5 = $db->getSqlQuery('SELECT id, name, points FROM '.PREFIX_DB.'_groups WHERE extra != 1 ORDER BY id');
    if ($db->getSqlRowCount($result5) > 0) {
        while ([$grid, $grname, $points] = $db->getSqlRow($result5)) {
            $result6 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE points >= :points', ['points' => $points]);
            $email4 = '';
            $num4 = 0;
            while ([$user_email] = $db->getSqlRow($result6)) {
                $email4 .= $user_email.',';
                $num4++;
            }
            $sel = ($email4 == $mails) ? ' selected' : '';
            $option .= '<option value="'.$email4.'"'.$sel.'>'._GROUP.' "'.$grname.'" - '.$num4.'</option>';
        }
    }
    if (is_active('money')) {
        $result7 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_money WHERE status = 1');
        if ($db->getSqlRowCount($result7) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result7)) $aemail[] = $user_email;
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
        $result8 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_order WHERE status = 1');
        if ($db->getSqlRowCount($result8) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result8)) $aemail[] = $user_email;
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
        $result9 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients');
        if ($db->getSqlRowCount($result9) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result9)) $aemail[] = $user_email;
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
        $result10 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients WHERE status = 1');
        if ($db->getSqlRowCount($result10) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result10)) $aemail[] = $user_email;
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
        $result11 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients WHERE status = 0');
        if ($db->getSqlRowCount($result11) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result11)) $aemail[] = $user_email;
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
    $hide = '<input type="hidden" name="nid" value="'.$nid.'"><input type="hidden" name="name" value="newsletter"><input type="hidden" name="op" value="save"><input type="hidden" name="posttype" value="save">';
    $rows = '';
    $rows .= getAdminFormRow(_TITLE.':', '<input type="text" name="title" value="'.$title.'" maxlength="50" class="sl_form" placeholder="'._TITLE.'" required>');
    $rows .= getAdminFormRow(_TEXT.':', textarea('1', 'body', $body, 'all', '10', _TEXT, '1'));
    $rows .= getAdminFormRow(_NLWHERE.':', '<select name="mails" class="sl_form">'.$option.'</select>');
    $rows .= getAdminFormWide('<input type="submit" value="'._SAVE.'" class="sl_but_blue">', '', 'sl_center');
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $nid = getVar('post', 'nid', 'num', 0);
    $title = getVar('post', 'title', 'title');
    $body = getVar('post', 'body', 'text');
    $mails = getVar('post', 'mails', '');
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if (!$stop && getVar('post', 'posttype') == 'save') {
        if ($mails == 1) {
            $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users');
            $emails = [];
            while ([$user_email] = $db->getSqlRow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } elseif ($mails == 2) {
            $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE newslet = 1');
            $emails = [];
            while ([$user_email] = $db->getSqlRow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } else {
            $emails = $mails;
        }
        if ($nid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_newsletter SET title = :title, body = :body, mails = :mails, send = 0, time = now(), endtime = 0 WHERE id = :id', [
                'title' => $title, 'body' => $body, 'mails' => $emails, 'id' => $nid
            ]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_newsletter (title, body, mails, send, time, endtime) VALUES (:title, :body, :mails, 0, now(), 0)', [
                'title' => $title, 'body' => $body, 'mails' => $emails
            ]);
        }
        setRedirect($afile.'.php?name=newsletter');
    } else {
        add();
    }
}

function delete(): void {
    global $db, $afile, $id;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=newsletter');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/newsletter.php');
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'newsletter',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_nlsend' => _NLSEND,
        '_nlsendi' => _NLSENDI,
        'r_active' => radio_form($conf['newsletter']['active'], 'active'),
        '_nlcount' => _NLCOUNT,
        'count' => $conf['newsletter']['count'],
        'newsletter' => true,
    ]);
    echo $cont.getAdminBox($confv);
    setFoot();
}

function configsave(): void {
    global $afile;
    $content = [
        'active' => getVar('post', 'active', 'num', 0),
        'count'  => getVar('post', 'count', 'num', 4),
    ];
    setConfigFile('newsletter.php', $content);
    setRedirect($afile.'.php?name=newsletter&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: newsletter(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}

