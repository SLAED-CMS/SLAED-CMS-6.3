<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
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
    $cont = $tpl->getHtmlFrag('title', ['title' => _ORDER, 'is_level_one' => true]);
    $cont .= $prs->filterContent($conf['order']['text'], false, 'all');
    if ($conf['order']['an']) {
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $rows = $tpl->getHtmlFrag('form-field-row', [
            'label' => _OR_2,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'maxlength="255" placeholder="'._OR_2.'" required', 'name_attr' => 'mail', 'value_attr' => $mail]),
        ]);
        $rows .= getTplFieldsIn(['field' => $field, 'mod' => $conf['name']]);
        $rows .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _OR_3,
            'hide_label' => true,
            'field_html' => getTplTextarea([
                'id' => '1',
                'name' => 'note',
                'value' => $note,
                'mod' => $conf['name'],
                'store' => 'order.note',
                'rows' => 5,
                'placeholder' => _OR_3,
            ]),
        ]);
        $cont .= $tpl->getHtmlFrag('title', ['is_level_two' => true, 'title' => _OR_1]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'captcha' => getPageCaptcha('comment'),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OR_4]),
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MO_11]);
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop, $tpl, $prs, $mailer;
    if ($conf['order']['an']) {
        $mail = getVar('post', 'mail', 'text');
        $field = getVar('post', 'field', 'field');
        $note = getVar('post', 'note', 'text');
        $stop = [];
        checkemail($mail);
        if ($room = checkEditorTextRoom($field, 'order.info')) $stop[] = $room;
        if ($room = checkEditorTextRoom($note, 'order.note')) $stop[] = $room;
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
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
                $msg = $tpl->getHtmlPart('message-block', [
                    'title' => $subject,
                    'heading_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _PERSONALINFO]),
                    'lines' => [
                        ['label' => _OR_2, 'value' => $mail],
                    ],
                    'details_html' => $infos,
                    'note_label' => _OR_3,
                    'note_html' => $note,
                ]);
                $mailer->addQueue(['kind' => 'order', 'email' => $amail, 'title' => $subject, 'body' => $msg, 'sender' => $mail, 'prio' => 1, 'client' => true]);
            }
            if (!$conf['order']['pr']) {
                $amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._ORDER;
                $msg = $tpl->getHtmlPart('message-block', [
                    'title' => $subject,
                    'content_html' => $prs->filterContent($conf['order']['sendinfo'], false, 'all'),
                ]);
                $mailer->addQueue(['kind' => 'order', 'email' => $mail, 'title' => $subject, 'body' => $msg, 'sender' => $amail, 'prio' => 3]);
            }
            updatePoints(34);
            setHead(['title' => _ORDER]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 30]);
            echo $tpl->getHtmlFrag('title', ['title' => _ORDER, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $prs->filterContent($conf['order']['info'], false, 'all'), 'meta' => $meta]);
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
