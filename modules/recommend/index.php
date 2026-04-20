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
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    $fields = $tpl->getHtmlFrag('form-field-row', [
        'label' => _YOURNAME.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $unkey, 'value_attr' => $sname, 'placeholder_text' => _YOURNAME, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _YOUREMAIL.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'semail', 'value_attr' => $semail, 'placeholder_text' => _YOUREMAIL, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _FFRIENDNAME.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'fname', 'value_attr' => $fname, 'placeholder_text' => _FFRIENDNAME, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _FFRIENDEMAIL.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'femail', 'value_attr' => $femail, 'placeholder_text' => _FFRIENDEMAIL, 'is_required' => true]),
    ]);
    $cont .= $tpl->getHtmlPart('form-add', [
        'action' => 'index.php?name='.$conf['name'],
        'method' => 'post',
        'form_name' => 'post',
        'no_enctype' => true,
        'token' => htmlspecialchars(getSiteToken('recommend'), ENT_QUOTES, 'UTF-8'),
        'fields' => $fields,
        'captcha' => getCaptcha(2),
        'submit' => $tpl->getHtmlFrag('form-submit', ['op' => 'send', 'label' => _SEND]),
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
        $siteLink = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'], 'title' => $conf['sitename'], 'label' => $conf['homeurl'], 'is_blank' => true]);
        $message = $tpl->getHtmlPart('message-block', [
            'title' => _HELLO.' '.$fname.'!',
            'intro_text' => _YOURFRIEND.' '.$sname.' '._OURSITE.' '.$conf['sitename'].' '._INTSENT,
            'lines' => [
                ['label' => _SITENAME, 'value' => $conf['sitename'].' '.urldecode($conf['defis']).' '.$conf['slogan']],
                ['label' => _SITEURL, 'value_html' => $siteLink],
            ],
        ]);
        addMail($femail, $semail, $subject, $message, 0, 3);
        update_points(38);
        setHead(['title' => _RECOMMTITLE]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
        echo $tpl->getHtmlFrag('title', ['title' => _RECOMMTITLE]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'messages' => [_FREFERENCE.' '.$fname.'.', _THANKSREC], 'meta' => $meta]);
        setFoot();
    } else {
        recommend();
    }
}

switch ($op) {
    default: recommend(); break;
    case 'send': send(); break;
}
