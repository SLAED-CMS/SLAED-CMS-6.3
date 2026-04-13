<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function contact(): void {
    global $db, $conf, $locale, $stop, $tpl, $prs;
    if (is_user()) {
        $userinfo = getUserInfo();
        $sname = getVar('post', 'sname', 'name', $userinfo['name']);
        $semail = getVar('post', 'semail', 'text', $userinfo['email']);
    } else {
        $sname = getVar('post', 'sname', 'name');
        $semail = getVar('post', 'semail', 'text');
    }
    $message = getVar('post', 'message', 'text');
    $asend = '';
    if ($conf['contact']['admins']) {
        $wlang = '';
        $params = [];
        if ($conf['multilingual']) {
            $wlang = 'AND (lang = :locale OR lang = \'\')';
            $params['locale'] = $locale;
        }
        $result = $db->getSqlQuery('SELECT id, name, title FROM '.PREFIX_DB.'_admins WHERE smail = \'1\' '.$wlang.' ORDER BY id', $params);
        if ($db->getSqlRowCount($result) > 0) {
            while ([$id, $aname, $atitle] = $db->getSqlRow($result)) {
                $aname = substr($aname, 0, 25);
                $atitle = substr($atitle, 0, 50);
                $asend .= getTplSelectOption((string)$id, $aname.' - '.$atitle);
            }
        }
    }
    if ($conf['contact']['info']) {
        $title = _CONTACT;
        $info = $prs->filterContent($conf['contact']['info'], false, $conf['name']);
    } else {
        $title = _FEEDBACK;
        $info = '';
    }
    setHead(['title' => $title]);
    $cont = $tpl->getHtmlFrag('title', ['title' => $title]);
    $form = $tpl->getHtmlFrag('contact-form', [
        'info' => $info,
        'name' => $conf['name'],
        'token' => htmlspecialchars(getSiteToken('contact'), ENT_QUOTES, 'UTF-8'),
        'style' => $conf['style'],
        'admin_options' => $asend,
        'lbl_to' => _TO,
        'lbl_name' => _YOURNAME,
        'lbl_email' => _YOUREMAIL,
        'lbl_message' => _MESSAGE,
        'sname' => $sname,
        'semail' => $semail,
        'message' => $message,
        'captcha' => getCaptcha(1),
        'submit' => _SEND,
    ]);
    if (getVar('post', 'send', 'num') == '1') {
        $id = getVar('post', 'id', 'num');
        $sname = getVar('post', 'sname', 'name');
        $semail = getVar('post', 'semail', 'text');
        $message = nl2br(getVar('post', 'message', 'text'), false);
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'contact')) $stop[] = _ERROR;
        if (!$sname) $stop[] = _CERROR3;
        if (!$message) $stop[] = _CERROR1;
        checkemail($semail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop) {
            if ($conf['contact']['admins'] && $id) {
                [$adminmail] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_admins WHERE id = :id AND smail = \'1\'', ['id' => $id]));
                $to = $adminmail;
            } else {
                $to = $conf['adminmail'];
            }
            $subject = $conf['sitename'].' - '._FEEDBACK;
            $msg = $tpl->getHtmlFrag('contact-email-body', ['sitename' => $conf['sitename'], 'feedback_label' => _FEEDBACK, 'sender_label' => _SENDERNAME, 'sname' => $sname, 'email_label' => _SENDEREMAIL, 'semail' => $semail, 'message_label' => _MESSAGE, 'message' => $message]);
            addMail($to, $semail, $subject, $msg, 1, 1);
            update_points(5);
            $meta = getTplMetaRefresh('index.php', 5);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _FBMAILSENT, 'meta' => $meta]);
        } else {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
            $cont .= $form;
        }
    } else {
        $cont .= $form;
    }
    echo $cont;
    setFoot();
}

switch($op) {
    default: contact(); break;
}
