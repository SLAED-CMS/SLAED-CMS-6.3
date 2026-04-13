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
    $cont .= $tpl->getHtmlFrag('money-calc-scripts', ['kurs' => $conf['money']['kurs'], 'kurs2' => $conf['money']['kurs2'], 'proz' => $conf['money']['proz']]);
    $cont .= $tpl->getHtmlFrag('heading-2', ['text' => _MO_1]);
    $cont .= getTplMoneyCalcForm('Rechner', _MO_3.' Z:', 'USD')
        .getTplMoneyCalcForm('Rechner1', _MO_3.' R:', 'RUB')
        .getTplMoneyCalcForm('Rechner2', _MO_3.' E:', 'EUR');
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $intro = getVar('post', 'intro', 'array', []);
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        $rows = '';
        $rows .= getTplFormAddRow(_MO_7.':', $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'sum',
            'value_attr' => (string)$sum,
            'input_class' => 'sl_field '.$conf['style'],
            'input_attr' => 'placeholder="'._MO_7.'" required',
        ]));
        $rows .= getTplFormAddRow(_MO_8.':', getTplEmailInput('email', $email, 'sl_field '.$conf['style'], 'placeholder="'._MO_8.'" required'));
        $form = explode(',', $conf['money']['form']);
        $i = 0;
        foreach ($form as $val) {
            if ($val != '') {
                $rows .= getTplFormAddRow($val.':', getTplTextInput('intro[]', filterHtml($intro[$i] ?? '', 1), 'sl_field '.$conf['style'], 'maxlength="255" placeholder="'.$val.'" required'));
                $i++;
            }
        }
        $rows .= getTplFormAddRow(_MO_9.':', textarea('1', 'note', $note, $conf['name'], 5, _MO_9));
        $cont .= $tpl->getHtmlFrag('heading-2', ['text' => _MO_6]);
        $cont .= $tpl->getHtmlFrag('form-add', [
            'captcha' => getCaptcha(1),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'style' => $conf['style'],
            'submit' => getTplFormSubmit('send', _MO_10),
            'token' => '',
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
                $sinfo = '';
                $i = 0;
                foreach ($form as $val) {
                    if ($val != '') {
                        $sinfo .= $tpl->getHtmlFrag('title-tip-item', [
                            'label' => $val,
                            'value' => filterHtml($intro[$i] ?? '', 1),
                            'is_last' => false,
                        ]);
                        $i++;
                    }
                }
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $tpl->getHtmlFrag('money-email-order', [
                    'sitename'           => $conf['sitename'],
                    'money_label'        => _MONEY,
                    'personalinfo_label' => _PERSONALINFO,
                    'amount_label'       => _MO_7,
                    'amount'             => $sum,
                    'email_label'        => _MO_8,
                    'email'              => $email,
                    'sinfo_html'         => $sinfo,
                    'note_label'         => _MO_9,
                    'note'               => $note,
                ]);
                addMail($amail, $email, $subject, $msg, 1, 1);
            }
            if (!$conf['money']['pr']) {
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $tpl->getHtmlFrag('money-email-confirm', [
                    'sitename'    => $conf['sitename'],
                    'money_label' => _MONEY,
                    'content_html' => $prs->filterContent($conf['money']['sendinfo'], false, 'all'),
                ]);
                addMail($email, $amail, $subject, $msg, 0, 3);
            }
            setHead(['title' => _MONEY]);
            $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 30);
            echo $tpl->getHtmlFrag('title', ['title' => _MONEY]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $prs->filterContent($conf['money']['info'], false, 'all'), 'meta' => $meta]);
            setFoot();
        } else {
            money();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: money(); break;
    case 'send': send(); break;
}
