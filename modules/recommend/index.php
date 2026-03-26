<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function recommend(): void {
    global $conf, $stop, $tpl;
    $unkey = substr(hash('sha256', 'field|'.$conf['sitekey']), 0, 32);
    if (is_user()) {
        $userinfo = getUserInfo();
        $sname = getVar('post', $unkey, 'name', $userinfo['name']);
        $semail = getVar('post', 'semail', 'text', $userinfo['email']);
    } else {
        $sname = getVar('post', $unkey, 'name');
        $semail = getVar('post', 'semail', 'text');
    }
    $fname = getVar('post', 'fname', 'name');
    $femail = getVar('post', 'femail', 'text');
    setHead(['title' => _RECOMMTITLE]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _RECOMMTITLE]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $cont .= $tpl->getHtmlFrag('recommend-form', [
        'name' => $conf['name'],
        'style' => $conf['style'],
        'token' => htmlspecialchars(getSiteToken('recommend'), ENT_QUOTES, 'UTF-8'),
        'nick_field' => $unkey,
        'sname' => $sname,
        'semail' => $semail,
        'fname' => $fname,
        'femail' => $femail,
        'captcha' => getCaptcha(2),
        'lbl_yourname' => _YOURNAME,
        'lbl_youremail' => _YOUREMAIL,
        'lbl_friendname' => _FFRIENDNAME,
        'lbl_friendemail' => _FFRIENDEMAIL,
        'submit_label' => _SEND,
    ]);
    echo $cont;
    setFoot();
}

function send(): void {
    global $conf, $stop, $tpl;
    $unkey = substr(hash('sha256', 'field|'.$conf['sitekey']), 0, 32);
    $sname = getVar('post', $unkey, 'name');
    $semail = getVar('post', 'semail', 'text');
    $fname = getVar('post', 'fname', 'name');
    $femail = getVar('post', 'femail', 'text');
    $stop = [];
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'recommend')) $stop[] = _ERROR;
    if (!$sname || !$fname) $stop[] = _ERROR_ALL;
    checkemail($semail);
    checkemail($femail);
    if (checkCaptcha(2)) $stop[] = _SECCODEINCOR;
    if (!$stop) {
        $subject = $conf['sitename'].' - '._INTSITE;
        $siteLink = $tpl->getHtmlFrag('recommend-mail-link', ['href' => $conf['homeurl'], 'title' => $conf['sitename'], 'label' => $conf['homeurl']]);
        $message = $tpl->getHtmlFrag('recommend-mail-message', [
            'hello' => _HELLO,
            'friend_name' => $fname,
            'yourfriend' => _YOURFRIEND,
            'sender_name' => $sname,
            'oursite' => _OURSITE,
            'sitename' => $conf['sitename'],
            'intsent' => _INTSENT,
            'sitename_label' => _SITENAME,
            'slogan' => urldecode($conf['defis']).' '.$conf['slogan'],
            'siteurl_label' => _SITEURL,
            'site_link' => $siteLink,
        ]);
        addMail($femail, $semail, $subject, $message, 0, 3);
        update_points(38);
        setHead(['title' => _RECOMMTITLE]);
        $meta = getMetaRefresh('index.php?name='.$conf['name']);
        echo $tpl->getHtmlFrag('title', ['title' => _RECOMMTITLE]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $tpl->getHtmlFrag('recommend-success-text', ['freference' => _FREFERENCE, 'friend_name' => $fname, 'thanksrec' => _THANKSREC]), 'meta' => $meta]);
        setFoot();
    } else {
        recommend();
    }
}

switch($op) {
    default: recommend(); break;
    case 'send': send(); break;
}
