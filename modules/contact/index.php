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
                $asend .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$id, 'label_text' => $aname.' - '.$atitle, 'is_selected' => false]);
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
    $fields = $asend ? $tpl->getHtmlFrag('form-field-row', [
        'label' => _TO.':',
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'id', 'options_html' => $asend]),
    ]) : '';
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _YOURNAME.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'sname', 'value_attr' => $sname, 'placeholder_text' => _YOURNAME, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _YOUREMAIL.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'semail', 'value_attr' => $semail, 'placeholder_text' => _YOUREMAIL, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _MESSAGE.':',
        'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'message', 'rows_num' => 10, 'value_text' => $message, 'placeholder_text' => _MESSAGE, 'is_required' => true]),
    ]);
    $form = ($info ? $tpl->getHtmlFrag('block-content', ['is_section' => true, 'content' => $info, 'has_hr' => true]) : '').$tpl->getHtmlPart('form-add', [
        'action' => 'index.php?name='.$conf['name'],
        'method' => 'post',
        'form_name' => 'post',
        'no_enctype' => true,
        'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('contact')]).$fields,
        'captcha' => getCaptcha(1),
        'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit',
            'extra' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'send', 'value_attr' => '1']),
            'op' => 'contact',
            'label' => _SEND,
        ]),
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
            $msg = $tpl->getHtmlPart('message-block', [
                'title' => $subject,
                'lines' => [
                    ['label' => _SENDERNAME, 'value' => $sname],
                    ['label' => _SENDEREMAIL, 'value' => $semail],
                ],
                'body_label' => _MESSAGE,
                'body_html' => $message,
            ]);
            addMail($to, $semail, $subject, $msg, 1, 1);
            update_points(5);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php', 'secs' => 5]);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _FBMAILSENT, 'meta' => $meta]);
        } else {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
            $cont .= $form;
        }
    } else {
        $cont .= $form;
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: contact(); break;
}
