<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function newsletter(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, title, mails, send, time, endtime FROM '.PREFIX_DB.'_newsletter ORDER BY id');
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-newsletter-list-head', [
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'nlend_label' => _NLEND,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $title, $mails, $sended, $time, $endtime] = $db->getSqlRow($result)) {
            $sendtime = ($endtime > $time) ? strtotime($endtime) - strtotime($time) : 0;
            $active = ($mails && $sended && $conf['newsletter']['active']) ? 1 : 0;
            $acts = getTplAdminActionMenu([
                getTplLinkAction($afile.'.php?name=newsletter&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                getTplAdminDeleteAction($afile.'.php?name=newsletter&amp;op=delete&amp;id='.$id, _DELETE.' &quot;'.$title.'&quot;?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-newsletter-list-row', [
                'actions_html' => $acts,
                'id_text' => (string)$id,
                'send_text' => $sended.' '._NLUSER,
                'status_html' => ad_status('', $active),
                'title_html' => getTplAdminTitleTip(_DATE.': '.format_time($time, _TIMESTRING).getTplAdminTipLine(_TIMENL, getDuration($sendtime))).$title,
            ]));
        }
        $cont .= getTplAdminTable($head, $rows);
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
    $cont = getTplAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    if ($body) $cont .= preview($title, $body, '', '', 'all');
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users'));
    $option = getTplOption('1', _MASSMAIL.' - '.$num, $mails == 1);
    [$num2] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users WHERE newslet = 1'));
    $option .= getTplOption('2', _ANEWSLETTER.' - '.$num2, $mails == 2);
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
            $option .= getTplOption($email3, _SPEC_GROUP.' "'.$grname.'" - '.$num3, $email3 == $mails);
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
            $option .= getTplOption($email4, _GROUP.' "'.$grname.'" - '.$num4, $email4 == $mails);
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
            $option .= getTplOption($email5, _CLIENTSM.' "'._MONEY.'" - '.$num5, $email5 == $mails);
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
            $option .= getTplOption($email6, _CLIENTSM.' "'._ORDER.'" - '.$num6, $email6 == $mails);
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
            $option .= getTplOption($email7, _CLIENTSM.' "'._SHOP.'" ('._ALL.') - '.$num7, $email7 == $mails);
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
            $option .= getTplOption($email8, _CLIENTSM.' "'._SHOP.'" ('._AKTIVE.') - '.$num8, $email8 == $mails);
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
            $option .= getTplOption($email9, _CLIENTSM.' "'._SHOP.'" ('._DEAKTIVE.') - '.$num9, $email9 == $mails);
        }
    }
    $hide = getTplHiddenInput('nid', (string)$nid).getTplHiddenInput('name', 'newsletter').getTplHiddenInput('op', 'save').getTplHiddenInput('posttype', 'save');
    $rows = $tpl->getHtmlFrag('admin-newsletter-add-rows', [
        'body_html' => textarea('1', 'body', $body, 'all', '10', _TEXT, '1'),
        'mails_html' => getTplSelect('mails', $option, 'sl_form'),
        'mails_label' => _NLWHERE.':',
        'save_label' => _SAVE,
        'text_label' => _TEXT.':',
        'title_label' => _TITLE.':',
        'title_value' => $title,
    ]);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
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
    $cont = getTplAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
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
    echo $cont.getTplBox($confv);
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
    $cont = getTplAdminNavi(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    setAdminInfoPage($cont);
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
