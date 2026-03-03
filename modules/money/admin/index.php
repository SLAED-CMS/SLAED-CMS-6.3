<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('money')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=conf', 'name=money&amp;op=info'];
    $lang = [_HOME, _ADD, _PREFERENCES, _INFO];
    return getAdminTabs(_MONEY, 'money.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function money(): void {
    global $db, $afile, $conf;
        setHead();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get', 'send', 'num', 0)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MA_15]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['money']['anum'] ?? 25;
    $anump = $conf['money']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, sum, mail, info, com, ip, agent, date, status FROM '.PREFIX_DB.'_money ORDER BY date DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_money'));
        $r = $numstories;
        if ($numstories > $offset) $r -= $offset;
        $numpages = ceil($numstories / $anum);
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._SUM.'</th><th>'._EMAIL.'</th><th>'._IP.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        $form = explode(',', $conf['money']['form'] ?? '');
        while ([$id, $sum, $mail, $info, $com, $ip, $agent, $date, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $info = explode('|', $info);
            $i = 0;
            $infos = '';
            foreach ($form as $val) {
                if ($val != '') {
                    $infos .= $val.': '.($info[$i] ?? '').'<br>';
                    $i++;
                }
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.$sum.' EUR</td>'
            .'<td>'.title_tip($infos.'<br>'._COMMENT.': '.$com.'<br><br>'._BROWSER.': '.$agent).anti_spam($mail).'</td>'
            .'<td>'.user_geo_ip($ip, 4).'</td>'
            .'<td>'.format_time($date, _TIMESTRING).'</td>'
            .'<td>'.ad_status('', $status).'</td>'
            .'<td>'.add_menu(ad_status($afile.'.php?name=money&amp;op=active&amp;id='.$id.'&amp;act='.$act, $status).'||<a href="'.$afile.'.php?name=money&amp;op=rechn&amp;id='.$id.'&amp;rnum='.$r.'" title="'._RECHN_B.'">'._RECHN_B.'</a>||<a href="'.$afile.'.php?name=money&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=money&amp;op=del&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'._ID.': '.$id.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
            $r--;
        }
        $cont .= '</tbody></table>';
        $cont .= setPageNumbers('pagenum', '', $numstories, $numpages, $anum, 'name=money&amp;', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
        $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT sum, mail, info, com, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $mid]);
        [$sum, $mail, $info, $com, $date] = $db->getSqlRow($result);
        $info = explode('|', $info);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $sum = getVar('post', 'sum', 'num', 0);
        $mail = getVar('post', 'mail', 'text', '');
        $info = getVar('post', 'info', 'array', []);
        $com = getVar('post', 'com', 'text', '');
        $date = getVar('req', 'date', 'time');
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if ($info) {
        $form = explode(',', $conf['money']['form'] ?? '');
        $i = 0;
        $infos = '';
        foreach ($form as $val) {
            if ($val != '') {
                $infos .= $val.': '.($info[$i] ?? '').'<br>';
                $i++;
            }
        }
        $cont .= preview($mail, $infos, _COMMENT.': '.$com, '', 'all');
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._MA_17.':</td><td><input type="number" name="sum" value="'.$sum.'" class="sl_form" placeholder="'._MA_17.'" required></td></tr>'
    .'<tr><td>'._MA_18.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="255" class="sl_form" placeholder="'._MA_18.'" required></td></tr>';
    $form = explode(',', $conf['money']['form'] ?? '');
    $i = 0;
    foreach ($form as $val) {
        if ($val != '') {
            $cont .= '<tr><td>'.$val.':</td><td><input type="text" name="info[]" value="'.($info[$i] ?? '').'" maxlength="255" class="sl_form" placeholder="'.$val.'"></td></tr>';
            $i++;
        }
    }
    $cont .= '<tr><td>'._MA_19.':</td><td><textarea name="com" cols="65" rows="5" class="sl_form" placeholder="'._MA_19.'">'.$com.'</textarea></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="money">'.ad_save('mid', $mid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $sum = getVar('post', 'sum', 'num', 0);
    $mail = getVar('post', 'mail', 'text', '');
    $info = getVar('post', 'info', 'array', []);
    $list = (!empty($info)) ? filterText(implode('|', $info)) : '';
    $com = getVar('post', 'com', 'text', '');
    $date = getVar('req', 'date', 'time');
    checkemail($mail);
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET sum = :sum, mail = :mail, info = :info, com = :com, date = :date WHERE id = :mid', ['sum' => $sum, 'mail' => $mail, 'info' => $list, 'com' => $com, 'date' => $date, 'mid' => $mid]);
        } else {
            $ip = getip();
            $agent = getagent();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_money VALUES (NULL, :sum, :mail, :info, :com, :ip, :agent, :date, \'1\')', ['sum' => $sum, 'mail' => $mail, 'info' => $list, 'com' => $com, 'ip' => $ip, 'agent' => $agent, 'date' => $date]);
        }
        setRedirect($afile.'.php?name=money');
    } elseif ($posttype === 'delete') {
        del($mid);
    } else {
        add();
    }
}

function del(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=money');
}

function billing(string $title, string $autor, string $infos, string $num, string $date, string $menge, string $kurs, string $sum): void {
    global $theme, $conf;
    $template = file_get_contents('modules/money/templates/billing.html');
    if ($template === false) {
        return;
    }
    $replacements = [
        '$charset' => _CHARSET,
        '$theme' => $theme,
        '$title' => $title,
        '\$logo' => $conf['site_logo'] ?? '',
        '$sitename' => $conf['sitename'] ?? '',
        '$autor' => $autor,
        '$infos' => $infos,
        '$num' => $num,
        '$date' => $date,
        '$menge' => $menge,
        '$kurs' => $kurs,
        '$sum' => $sum,
    ];
    echo str_replace(array_keys($replacements), array_values($replacements), $template);
}

function rechn(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num', 0);
    [$sum, $mail, $info, $com, $ip, $agent, $date] = $db->getSqlRow($db->getSqlQuery('SELECT sum, mail, info, com, ip, agent, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
    $defis = urldecode($conf['defis'] ?? '%3E');
    $title = _RECHN.' '.$defis.' '._MONEY.' '.$defis.' '.($conf['sitename'] ?? '');
    $form = explode(',', $conf['money']['form'] ?? '');
    $info = explode('|', $info);
    $i = 0;
    $infos = '';
    foreach ($form as $val) {
        if ($val != '') {
            $infos .= $val.': '.($info[$i] ?? '').'<br>';
            $i++;
        }
    }
    $rnum = getVar('get', 'rnum', 'text', '');
    $kurs = (float)($conf['money']['kurs'] ?? 0);
    $proz = (float)($conf['money']['proz'] ?? 0);
    $menge = ($sum / 100) * $kurs * (100 - $proz);
    $kurs = ($menge > 0) ? round($sum / $menge, 2) : 0;
    billing($title, bb_decode($conf['money']['autor'] ?? '', 'money'), bb_decode($infos, 'money'), $rnum, format_time($date), (string)round($menge, 2), $kurs.' EUR', $sum.' EUR');
}

function active(): void {
    global $db, $afile, $conf;
        $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
    if ($act) {
        [$mail] = $db->getSqlRow($db->getSqlQuery('SELECT mail FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
        $amail = ($conf['money']['mail'] ?? '') ? $conf['money']['mail'] : ($conf['adminmail'] ?? '');
        $subject = ($conf['sitename'] ?? '').' - '._MONEY;
        $msg = ($conf['sitename'] ?? '').' - '._MONEY.'<br><br>';
        $msg .= bb_decode($conf['money']['sendinfo'] ?? '', 'all');
        addMail($mail, $amail, $subject, $msg, 0, 3);
        setRedirect($afile.'.php?name=money&send=1');
    }
    setRedirect($afile.'.php?name=money');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/money.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._MA_3.':</td><td><input type="text" name="proz" value="'.($conf['money']['proz'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_3.'" required></td></tr>'
    .'<tr><td>'._MA_4.': EUR > USD</td><td><input type="text" name="kurs" value="'.($conf['money']['kurs'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_4.'" required></td></tr>'
    .'<tr><td>'._MA_4.': EUR > RUB</td><td><input type="text" name="kurs2" value="'.($conf['money']['kurs2'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_4.'" required></td></tr>'
    .'<tr><td>'._MA_5.':</td><td><input type="text" name="bal" value="'.($conf['money']['bal'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_5.'" required></td></tr>'
    .'<tr><td>'._MA_6.':</td><td><input type="email" name="mail" value="'.($conf['money']['mail'] ?? '').'" maxlength="255" class="sl_conf" placeholder="'._MA_6.'" required></td></tr>'
    .'<tr><td>'._MA_7.':</td><td><textarea name="form" cols="65" rows="5" class="sl_conf" placeholder="'._MA_7.'" required>'.($conf['money']['form'] ?? '').'</textarea></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['money']['anum'] ?? 25).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['money']['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._MA_8.'</td><td>'.radio_form($conf['money']['an'] ?? 0, 'an').'</td></tr>'
    .'<tr><td>'._MA_9.'</td><td>'.radio_form($conf['money']['pr'] ?? 0, 'pr').'</td></tr>'
    .'<tr><td>'._MA_10.'</td><td>'.radio_form($conf['money']['ad'] ?? 0, 'ad').'</td></tr>'
    .'<tr><td>'._MA_11.':</td><td>'.textarea('1', 'text', $conf['money']['text'] ?? '', 'all', '5', _MA_11, '1').'</td></tr>'
    .'<tr><td>'._MA_12.':</td><td>'.textarea('2', 'info', $conf['money']['info'] ?? '', 'all', '5', _MA_12, '1').'</td></tr>'
    .'<tr><td>'._MA_13.':</td><td>'.textarea('3', 'sendinfo', $conf['money']['sendinfo'] ?? '', 'all', '5', _MA_13, '1').'</td></tr>'
    .'<tr><td>'._MA_14.':</td><td>'.textarea('4', 'autor', $conf['money']['autor'] ?? '', 'all', '5', _MA_14, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="money"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $xkurs = str_replace(',', '.', getVar('post', 'kurs', 'text', '0'));
    $xkurs2 = str_replace(',', '.', getVar('post', 'kurs2', 'text', '0'));
    $xform = strtr(getVar('post', 'form', 'raw', ''), ["\n" => '', "\t" => '', "\r" => '']);
    $cont = [
        'proz' => getVar('post', 'proz', 'text', '0'),
        'kurs' => $xkurs,
        'kurs2' => $xkurs2,
        'bal' => getVar('post', 'bal', 'text', ''),
        'mail' => getVar('post', 'mail', 'text', ''),
        'form' => $xform,
        'anum' => getVar('post', 'anum', 'num', 25),
        'anump' => getVar('post', 'anump', 'num', 10),
        'an' => getVar('post', 'an', 'num', 0),
        'pr' => getVar('post', 'pr', 'num', 0),
        'ad' => getVar('post', 'ad', 'num', 0),
        'text' => getVar('post', 'text', 'text', ''),
        'info' => getVar('post', 'info', 'text', ''),
        'sendinfo' => getVar('post', 'sendinfo', 'text', ''),
        'autor' => getVar('post', 'autor', 'text', ''),
    ];
    setConfigFile('money.php', $cont);
    setRedirect($afile.'.php?name=money&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: money(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'active': active(); break;
    case 'del': del(); break;
    case 'rechn': rechn(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}









