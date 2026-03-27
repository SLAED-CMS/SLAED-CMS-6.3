<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function money(): void {
    global $conf, $stop, $tpl;
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
    $cont .= filterReplaceText(filterMarkdown(str_replace(['[proz]', '[kurs]', '[kurs2]'], [$conf['money']['proz'], $conf['money']['kurs'], $conf['money']['kurs2']], $conf['money']['text']), 'all', false), 'all');
    $cont .= '<script>
    function Rechner(form) {
        a = form.a.value;
        b = a/100 * '.$conf['money']['kurs'].' * (100-'.$conf['money']['proz'].");
        b = (Math.round(b * 100) / 100).toString();
        b += (b.indexOf('.') == -1) ? '.00' : '00';
        form.total.value = b.substring(0, b.indexOf('.') + 3);
    }
    </script>";
    $cont .= '<script>
    function Rechner1(form) {
        a = form.a.value;
        b = a/100 * '.$conf['money']['kurs2'].' * (100-'.$conf['money']['proz'].");
        b = (Math.round(b * 100) / 100).toString();
        b += (b.indexOf('.') == -1) ? '.00' : '00';
        form.total.value = b.substring(0, b.indexOf('.') + 3);
    }
    </script>";
    $cont .= '<script>
    function Rechner2(form) {
        a = form.a.value;
        b = a/100 * (100-'.$conf['money']['proz'].");
        b = (Math.round(b * 100) / 100).toString();
        b += (b.indexOf('.') == -1) ? '.00' : '00';
        form.total.value = b.substring(0, b.indexOf('.') + 3);
    }
    </script>";
    $cont .= '<h2>'._MO_1.'</h2>'
    .getMoneyCalcForm('Rechner', _MO_3.' Z:', 'USD')
    .getMoneyCalcForm('Rechner1', _MO_3.' R:', 'RUB')
    .getMoneyCalcForm('Rechner2', _MO_3.' E:', 'EUR');
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $intro = getVar('post', 'intro', 'array', []);
        $note = getVar('post', 'note', 'text');
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        $rows = '';
        $rows .= getFormAddRow(_MO_7.':', getAdminNumberInput('sum', $sum, 'sl_field '.$conf['style'], 'placeholder="'._MO_7.'" required'));
        $rows .= getFormAddRow(_MO_8.':', '<input type="email" name="email" value="'.$email.'" class="sl_field '.$conf['style'].'" placeholder="'._MO_8.'" required>');
        $form = explode(',', $conf['money']['form']);
        $i = 0;
        foreach ($form as $val) {
            if ($val != '') {
                $rows .= getFormAddRow($val.':', getAdminTextInput('intro[]', filterHtml($intro[$i] ?? '', 1), 'sl_field '.$conf['style'], 'maxlength="255" placeholder="'.$val.'" required'));
                $i++;
            }
        }
        $rows .= getFormAddRow(_MO_9.':', textarea('1', 'note', $note, $conf['name'], 5, _MO_9));
        $cont .= '<h2>'._MO_6.'</h2>'.$tpl->getHtmlFrag('form-add', [
            'captcha' => getCaptcha(1),
            'extrafields' => $rows,
            'name' => $conf['name'],
            'style' => $conf['style'],
            'submit' => getFormSubmit('send', _MO_10),
            'token' => '',
        ]);
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop, $tpl;
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
                        $sinfo .= $val.': '.filterHtml($intro[$i] ?? '', 1).'<br>';
                        $i++;
                    }
                }
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $conf['sitename'].' - '._MONEY.'<br><br>';
                $msg .= '<b>'._PERSONALINFO.'</b><br><br>';
                $msg .= _MO_7.': '.$sum.'<br>';
                $msg .= _MO_8.': '.$email.'<br>';
                $msg .= $sinfo.'<br>';
                $msg .= _MO_9.': '.$note;
                addMail($amail, $email, $subject, $msg, 1, 1);
            }
            if (!$conf['money']['pr']) {
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $conf['sitename'].' - '._MONEY.'<br><br>';
                $msg .= filterReplaceText(filterMarkdown($conf['money']['sendinfo'], 'all', false), 'all');
                addMail($email, $amail, $subject, $msg, 0, 3);
            }
            setHead(['title' => _MONEY]);
            $meta = getMetaRefresh('index.php?name='.$conf['name'], 30);
            echo $tpl->getHtmlFrag('title', ['title' => _MONEY]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => filterReplaceText(filterMarkdown($conf['money']['info'], 'all', false), 'all'), 'meta' => $meta]);
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
