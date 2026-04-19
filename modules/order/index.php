<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function order(): void {
    global $conf, $stop, $tpl, $prs;
    if (is_user()) {
        $userinfo = getUserInfo();
        $mail = getVar('post', 'mail', 'text', $userinfo['email']);
    } else {
        $mail = getVar('post', 'mail', 'text');
    }
    $field = getVar('post', 'field', 'field');
    setHead(['title' => _ORDER]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _ORDER]);
    $cont .= $prs->filterContent($conf['order']['text'], false, 'all');
    if ($conf['order']['an']) {
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $rows = $tpl->getHtmlFrag('form-field-row', [
            'label' => _OR_2.':',
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'maxlength="255" placeholder="'._OR_2.'" required', 'input_class' => '', 'itype' => 'email', 'name_attr' => 'mail', 'value_attr' => $mail]),
        ]);
        $rows .= getTplFieldsIn(['field' => $field, 'mod' => $conf['name']]);
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => _OR_3.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'note', 'value' => $note, 'mod' => $conf['name'], 'rows' => 5, 'placeholder' => _OR_3])]);
        $cont .= $tpl->getHtmlFrag('heading-2', ['text' => _OR_1]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'captcha' => getCaptcha(1),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'submit' => $tpl->getHtmlFrag('form-submit', ['op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OR_4]),
            'token' => '',
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MO_11]);
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop, $tpl, $prs;
    if ($conf['order']['an']) {
        $mail = getVar('post', 'mail', 'text');
        $field = getVar('post', 'field', 'field');
        $note = getVar('post', 'note', 'text');
        $stop = [];
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop) {
            $status = ($conf['order']['pr']) ? '0' : '1';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :email, :info, :note, :ip, :agent, NOW(), :status)',
                ['email' => $mail, 'info' => $field, 'note' => $note, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]
            );
            if ($conf['order']['ad']) {
                $infos = getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]);
                $amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._ORDER;
                $msg = $tpl->getHtmlFrag('order-email-admin', [
                    'sitename'           => $conf['sitename'],
                    'order_label'        => _ORDER,
                    'personalinfo_label' => _PERSONALINFO,
                    'email_label'        => _OR_2,
                    'email'              => $mail,
                    'info_html'          => $infos,
                    'note_label'         => _OR_3,
                    'note'               => $note,
                ]);
                addMail($amail, $mail, $subject, $msg, 1, 1);
            }
            if (!$conf['order']['pr']) {
                $amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._ORDER;
                $msg = $tpl->getHtmlFrag('money-email-confirm', [
                    'sitename'     => $conf['sitename'],
                    'money_label'  => _ORDER,
                    'content_html' => $prs->filterContent($conf['order']['sendinfo'], false, 'all'),
                ]);
                addMail($mail, $amail, $subject, $msg, 0, 3);
            }
            update_points(34);
            setHead(['title' => _ORDER]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 30]);
            echo $tpl->getHtmlFrag('title', ['title' => _ORDER]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $prs->filterContent($conf['order']['info'], false, 'all'), 'meta' => $meta]);
            setFoot();
        } else {
            order();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: order(); break;
    case 'send': send(); break;
}
