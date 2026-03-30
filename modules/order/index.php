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
    global $conf, $stop, $tpl;
    if (is_user()) {
        $userinfo = getUserInfo();
        $mail = getVar('post', 'mail', 'text', $userinfo['email']);
    } else {
        $mail = getVar('post', 'mail', 'text');
    }
    $field = getVar('post', 'field', 'field');
    setHead(['title' => _ORDER]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _ORDER]);
    $cont .= filterReplaceText(filterMarkdown($conf['order']['text'], 'all', false), 'all');
    if ($conf['order']['an']) {
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        $rows = getTplFormAddRow(_OR_2.':', '<input type="email" name="mail" value="'.$mail.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._OR_2.'" required>');
        $rows .= fields_in($field, $conf['name']);
        $rows .= getTplFormAddRow(_OR_3.':', textarea('1', 'note', $note, $conf['name'], 5, _OR_3));
        $cont .= '<h2>'._OR_1.'</h2>'.$tpl->getHtmlFrag('form-add', [
            'captcha' => getCaptcha(1),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'style' => $conf['style'],
            'submit' => getTplFormSubmit('send', _OR_4),
            'token' => '',
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MO_11]);
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop, $tpl;
    if ($conf['order']['an']) {
        $mail = getVar('post', 'mail', 'text');
        $field = getVar('post', 'field', 'field');
        $note = getVar('post', 'note', 'text');
        $stop = [];
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop) {
            $status = ($conf['order']['pr']) ? '0' : '1';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :email, :info, :note, :ip, :agent, NOW(), :status)', ['email' => $mail, 'info' => $field, 'note' => $note, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]);
            if ($conf['order']['ad']) {
                $infos = fields_out($field, $conf['name']);
                $amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._ORDER;
                $msg = $conf['sitename'].' - '._ORDER.'<br><br><b>'._PERSONALINFO.'</b><br><br>'._OR_2.': '.$mail.'<br>'.$infos.'<br>'._OR_3.': '.$note;
                addMail($amail, $mail, $subject, $msg, 1, 1);
            }
            if (!$conf['order']['pr']) {
                $amail = ($conf['order']['mail']) ? $conf['order']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._ORDER;
                $msg = $conf['sitename'].' - '._ORDER.'<br><br>';
                $msg .= filterReplaceText(filterMarkdown($conf['order']['sendinfo'], 'all', false), 'all');
                addMail($mail, $amail, $subject, $msg, 0, 3);
            }
            update_points(34);
            setHead(['title' => _ORDER]);
            $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 30);
            echo $tpl->getHtmlFrag('title', ['title' => _ORDER]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => filterReplaceText(filterMarkdown($conf['order']['info'], 'all', false), 'all'), 'meta' => $meta]);
            setFoot();
        } else {
            order();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: order(); break;
    case 'send': send(); break;
}
