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
    global $conf, $stop;
    if (is_user()) {
        $userinfo = getUserInfo();
        $mail = getVar('post', 'mail', 'text');
        $mail = ($mail) ? $mail : $userinfo['user_email'];
    } else {
        $mail = getVar('post', 'mail', 'text');
    }
    setHead(['title' => _MONEY]);
    $cont = setTemplateBasic('title', ['{%title%}' => _MONEY]);
    $cont .= ($conf['money']['an']) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MO_5.': '.$conf['money']['bal'].' EUR']) : setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _MO_11]);
    $cont .= setTemplateBasic('open');
    $cont .= filterReplaceText(filterMarkdown(str_replace(['[proz]', '[kurs]', '[kurs2]'], [$conf['money']['proz'], $conf['money']['kurs'], $conf['money']['kurs2']], $conf['money']['text']), 'all', false), 'all');
    $cont .= setTemplateBasic('close');
    $cont .= setTemplateBasic('open');
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
    .'<form name="form"><table class="sl_table_form"><tr><td>'._MO_2.': <input type="number" name="a" style="width: 65px;" class="sl_field '.$conf['style'].'"> EUR</td><td>'._MO_3.' Z: <input name="total" style="width: 65px;" class="sl_field '.$conf['style'].'"> USD</td><td><input type="button" value="'._MO_4.'" class="sl_but_blue" OnClick=Rechner(this.form)></td></tr></table></form>'
    .'<form name="form"><table class="sl_table_form"><tr><td>'._MO_2.': <input type="number" name="a" style="width: 65px;" class="sl_field '.$conf['style'].'"> EUR</td><td>'._MO_3.' R: <input name="total" style="width: 65px;" class="sl_field '.$conf['style'].'"> RUB</td><td><input type="button" value="'._MO_4.'" class="sl_but_blue" OnClick=Rechner1(this.form)></td></tr></table></form>'
    .'<form name="form"><table class="sl_table_form"><tr><td>'._MO_2.': <input type="number" name="a" style="width: 65px;" class="sl_field '.$conf['style'].'"> EUR</td><td>'._MO_3.' E: <input name="total" style="width: 65px;" class="sl_field '.$conf['style'].'"> EUR</td><td><input type="button" value="'._MO_4.'" class="sl_but_blue" OnClick=Rechner2(this.form)></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $info = getVar('post', 'info', 'array', []);
        $com = getVar('post', 'com', 'text');
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        $cont .= setTemplateBasic('open');
        $cont .= '<h2>'._MO_6.'</h2><form action="index.php?name='.$conf['name'].'" method="post">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._MO_7.':</td><td><input type="number" name="sum" value="'.$sum.'" class="sl_field '.$conf['style'].'" placeholder="'._MO_7.'" required></td></tr>'
        .'<tr><td>'._MO_8.':</td><td><input type="email" name="mail" value="'.$mail.'" class="sl_field '.$conf['style'].'" placeholder="'._MO_8.'" required></td></tr>';
        $form = explode(',', $conf['money']['form']);
        $i = 0;
        foreach ($form as $val) {
            if ($val != '') {
                $cont .= '<tr><td>'.$val.':</td><td><input type="text" name="info[]" value="'.filterHtml($info[$i] ?? '', 1).'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'.$val.'" required></td></tr>';
                $i++;
            }
        }
        $cont .= '<tr><td>'._MO_9.':</td><td><textarea name="com" cols="65" rows="5" class="sl_field '.$conf['style'].'">'.$com.'</textarea></td></tr>'
        .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).'<input type="hidden" name="op" value="send"><input type="submit" value="'._MO_10.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    }
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $conf, $stop;
    if ($conf['money']['an']) {
        $sum = getVar('post', 'sum', 'num');
        $mail = getVar('post', 'mail', 'text');
        $info = getVar('post', 'info', 'array', []);
        $binfo = '';
        $stop = [];
        $i = 0;
        foreach ($info as $val) {
            if ($val != '') {
                if ($i == 0) {
                    $binfo = filterHtml($val, 1);
                    $i++;
                } else {
                    $binfo .= '|'.filterHtml($val, 1);
                }
            } else {
                $stop[] = _ERROR_ALL;
            }
        }
        $com = getVar('post', 'com', 'text');
        if (!$sum) $stop[] = _MO_SERROR;
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop) {
            $status = ($conf['money']['pr']) ? '0' : '1';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_money VALUES (NULL, :sum, :mail, :binfo, :com, :ip, :agent, NOW(), :status)', ['sum' => $sum, 'mail' => $mail, 'binfo' => $binfo, 'com' => $com, 'ip' => getIp(), 'agent' => getAgent(), 'status' => $status]);
            if ($conf['money']['ad']) {
                $form = explode(',', $conf['money']['form']);
                $sinfo = '';
                $i = 0;
                foreach ($form as $val) {
                    if ($val != '') {
                        $sinfo .= $val.': '.filterHtml($info[$i] ?? '', 1).'<br>';
                        $i++;
                    }
                }
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $conf['sitename'].' - '._MONEY.'<br><br>';
                $msg .= '<b>'._PERSONALINFO.'</b><br><br>';
                $msg .= _MO_7.': '.$sum.'<br>';
                $msg .= _MO_8.': '.$mail.'<br>';
                $msg .= $sinfo.'<br>';
                $msg .= _MO_9.': '.$com;
                addMail($amail, $mail, $subject, $msg, 1, 1);
            }
            if (!$conf['money']['pr']) {
                $amail = ($conf['money']['mail']) ? $conf['money']['mail'] : $conf['adminmail'];
                $subject = $conf['sitename'].' - '._MONEY;
                $msg = $conf['sitename'].' - '._MONEY.'<br><br>';
                $msg .= filterReplaceText(filterMarkdown($conf['money']['sendinfo'], 'all', false), 'all');
                addMail($mail, $amail, $subject, $msg, 0, 3);
            }
            setHead(['title' => _MONEY]);
            echo setTemplateBasic('title', ['{%title%}' => _MONEY]).setTemplateWarning('warn', ['time' => '30', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => filterReplaceText(filterMarkdown($conf['money']['info'], 'all', false), 'all')]);
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
