<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function money(): void {
    global $conf, $stop, $tpl, $prs;
    if (is_user()) {
        $userinfo = getUserInfo();
        $email = getVar('post', 'email', 'text');
        $email = ($email) ? $email : $userinfo['email'];
    } else {
        $email = getVar('post', 'email', 'text');
    }
    setHead(['title' => _MONEY]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _MONEY]);
    $cont .= ($conf['money']['an']) ? $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MO_5.': '.$conf['money']['bal'].' EUR']) : $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _MO_11]);
    $cont .= $prs->filterContent(str_replace(['[proz]', '[kurs]', '[kurs2]'], [$conf['money']['proz'], $conf['money']['kurs'], $conf['money']['kurs2']], $conf['money']['text']), false, 'all');
    $cont .= $tpl->getHtmlPart('money-calc-scripts', ['kurs' => $conf['money']['kurs'], 'kurs2' => $conf['money']['kurs2'], 'proz' => $conf['money']['proz']]);
    $cont .= $tpl->getHtmlFrag('title', ['is_level_two' => true, 'title' => _MO_1]);
    foreach ([
        ['Rechner', _MO_3.' Z:', 'USD'],
        ['Rechner1', _MO_3.' R:', 'RUB'],
        ['Rechner2', _MO_3.' E:', 'EUR'],
    ] as [$fnname, $tolbl, $tocur]) {
        $fields = $tpl->getHtmlFrag('form-field-row', [
            'label' => _MO_2.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'a', 'input_class' => 'sl-calculator-field']).' EUR',
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => $tolbl,
            'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'total', 'input_class' => 'sl-calculator-field']).' '.$tocur,
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => '',
            'field_html' => $tpl->getHtmlFrag('button', ['label' => _MO_4, 'button_attr' => 'OnClick="'.$fnname.'(this.form)"']),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'no_action' => true,
            'method' => 'post',
            'form_name' => 'form',
            'form_attr' => 'class="sl-calculator-form"',
            'no_enctype' => true,
            'fields' => $fields,
        ]);
    }
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $intro = getVar('post', 'intro', 'array', []);
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $rows = '';
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => _MO_7.':', 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'sum',
            'value_attr' => (string)$sum,
            'input_class' => '',
            'input_attr' => 'placeholder="'._MO_7.'" required',
        ])]);
        $rows .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _MO_8.':',
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'placeholder="'._MO_8.'" required', 'input_class' => '', 'itype' => 'email', 'name_attr' => 'email', 'value_attr' => $email]),
        ]);
        $form = explode(',', $conf['money']['form']);
        $i = 0;
        foreach ($form as $val) {
            if ($val != '') {
                $rows .= $tpl->getHtmlFrag('form-field-row', [
                    'label' => $val.':',
                    'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'maxlength="255" placeholder="'.$val.'" required', 'input_class' => '', 'itype' => 'text', 'name_attr' => 'intro[]', 'value_attr' => filterHtml($intro[$i] ?? '', 1)]),
                ]);
                $i++;
            }
        }
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => _MO_9.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'note', 'value' => $note, 'mod' => $conf['name'], 'rows' => 5, 'placeholder' => _MO_9])]);
        $cont .= $tpl->getHtmlFrag('title', ['is_level_two' => true, 'title' => _MO_6]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'captcha' => getCaptcha(1),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _MO_10]),
        ]);
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop, $tpl, $prs;
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $email = getVar('post', 'email', 'text');
        $intro = getVar('post', 'intro', 'array', []);
        $introText = '';
        $stop = [];
        $i = 0;
        foreach ($intro as $val) {
            if ($val != '') {
                if ($i == 0) {
                    $introText = filterHtml($val, 1);
                    $i++;
                } else {
                    $introText .= '|'.filterHtml($val, 1);
                }
            } else {
                $stop[] = _ERROR_ALL;
            }
        }
        $note = getVar('post', 'note', 'text');
        if (!$sum) $stop[] = _MO_SERROR;
        checkemail($email);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop) {
            $status = ($conf['money']['pr']) ? '0' : '1';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_money (`sum`, `email`, `intro`, `note`, `ip`, `agent`, `time`, `status`) VALUES (:sum, :email, :intro, :note, :ip, :agent, NOW(), :status)',
                ['sum' => $sum, 'email' => $email, 'intro' => $introText, 'note' => $note, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]
            );
            if ($conf['money']['ad']) {
                $form = explode(',', $conf['money']['form']);
                $dets = [];
                $i = 0;
                foreach ($form as $val) {
                    if ($val != '') {
                        $dets[] = [
                            'label' => $val,
                            'value_html' => filterHtml($intro[$i] ?? '', 1),
                        ];
                        $i++;
                    }
                }
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $tpl->getHtmlPart('message-block', [
                    'title' => $subject,
                    'heading_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _PERSONALINFO]),
                    'lines' => [
                        ['label' => _MO_7, 'value' => $sum],
                        ['label' => _MO_8, 'value' => $email],
                    ],
                    'details' => $dets,
                    'note_label' => _MO_9,
                    'note_html' => $note,
                ]);
                addMail($amail, $email, $subject, $msg, 1, 1);
            }
            if (!$conf['money']['pr']) {
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $tpl->getHtmlPart('message-block', [
                    'title' => $subject,
                    'content_html' => $prs->filterContent($conf['money']['sendinfo'], false, 'all'),
                ]);
                addMail($email, $amail, $subject, $msg, 0, 3);
            }
            setHead(['title' => _MONEY]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 30]);
            echo $tpl->getHtmlFrag('title', ['title' => _MONEY]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $prs->filterContent($conf['money']['info'], false, 'all'), 'meta' => $meta]);
            setFoot();
        } else {
            money();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: money(); break;
    case 'send': send(); break;
}
