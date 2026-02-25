<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
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
    $cfg = $conf['money'] ?? [];
    head();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get', 'send', 'num', 0)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MA_15]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $cfg['anum'] ?? 25;
    $anump = $cfg['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->sql_query('SELECT id, sum, mail, info, com, ip, agent, date, status FROM '.PREFIX_DB.'_money ORDER BY date DESC LIMIT '.$offset.', '.$anum);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        [$numstories] = $db->sql_fetchrow($db->sql_query('SELECT Count(id) FROM '.PREFIX_DB.'_money'));
        $r = $numstories;
        if ($numstories > $offset) $r -= $offset;
        $numpages = ceil($numstories / $anum);
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._SUM.'</th><th>'._EMAIL.'</th><th>'._IP.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        $form = explode(',', $cfg['form'] ?? '');
        while ([$id, $sum, $mail, $info, $com, $ip, $agent, $date, $status] = $db->sql_fetchrow($result)) {
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
    foot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
    $cfg = $conf['money'] ?? [];
    $mid = getVar('req', 'id', 'num', 0);
    if ($mid) {
        $result = $db->sql_query('SELECT sum, mail, info, com, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $mid]);
        [$sum, $mail, $info, $com, $date] = $db->sql_fetchrow($result);
        $info = explode('|', $info);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $sum = getVar('post', 'sum', 'num', 0);
        $mail = getVar('post', 'mail', 'text', '');
        $info = getVar('post', 'info', 'array', []);
        $com = getVar('post', 'com', 'text', '');
        $date = save_datetime(1, 'date');
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if ($info) {
        $form = explode(',', $cfg['form'] ?? '');
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
    $form = explode(',', $cfg['form'] ?? '');
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
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $sum = getVar('post', 'sum', 'num', 0);
    $mail = text_filter(getVar('post', 'mail', 'text', ''));
    $info_val = getVar('post', 'info', 'array', []);
    $info = (!empty($info_val)) ? text_filter(implode('|', $info_val)) : '';
    $com = text_filter(getVar('post', 'com', 'text', ''));
    $date = save_datetime(1, 'date');
    checkemail($mail);
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        if ($mid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_money SET sum = :sum, mail = :mail, info = :info, com = :com, date = :date WHERE id = :mid', ['sum' => $sum, 'mail' => $mail, 'info' => $info, 'com' => $com, 'date' => $date, 'mid' => $mid]);
        } else {
            $ip = getip();
            $agent = getagent();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_money VALUES (NULL, :sum, :mail, :info, :com, :ip, :agent, :date, \'1\')', ['sum' => $sum, 'mail' => $mail, 'info' => $info, 'com' => $com, 'ip' => $ip, 'agent' => $agent, 'date' => $date]);
        }
        header('Location: '.$afile.'.php?name=money');
        exit;
    } elseif ($posttype === 'delete') {
        del($mid);
    } else {
        add();
    }
}

function del(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]);
    header('Location: '.$afile.'.php?name=money');
    exit;
}

function billing(string $title, string $autor, string $infos, string $num, string $date, string $menge, string $kurs, string $sum): void {
    global $theme, $conf;
    $template = file_get_contents('modules/money/templates/billing.html');
    $replacements = [
        '$charset' => _CHARSET,
        '$theme' => $theme,
        '$title' => $title,
        '$site_logo' => $conf['site_logo'] ?? '',
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
    $cfg = $conf['money'] ?? [];
    $id = getVar('get', 'id', 'num', 0);
    [$sum, $mail, $info, $com, $ip, $agent, $date] = $db->sql_fetchrow($db->sql_query('SELECT sum, mail, info, com, ip, agent, date FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
    setThemeInclude();
    $site_defis = urldecode($conf['defis'] ?? '%3E');
    $title = _RECHN.' '.$site_defis.' '._MONEY.' '.$site_defis.' '.($conf['sitename'] ?? '');
    $form = explode(',', $cfg['form'] ?? '');
    $info = explode('|', $info);
    $i = 0;
    $infos = '';
    foreach ($form as $val) {
        if ($val != '') {
            $infos .= $val.': '.($info[$i] ?? '').'<br>';
            $i++;
        }
    }
    $num = getVar('get', 'rnum', 'text', '');
    $kurs_cfg = (float)($cfg['kurs'] ?? 0);
    $proz_cfg = (float)($cfg['proz'] ?? 0);
    $menge = ($sum / 100) * $kurs_cfg * (100 - $proz_cfg);
    $kurs = ($menge > 0) ? round($sum / $menge, 2) : 0;
    billing($title, bb_decode($cfg['autor'] ?? '', 'money'), bb_decode($infos, 'money'), $num, format_time($date), (string)round($menge, 2), $kurs.' EUR', $sum.' EUR');
}

function active(): void {
    global $db, $afile, $conf;
    $cfg = $conf['money'] ?? [];
    $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $db->sql_query('UPDATE '.PREFIX_DB.'_money SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
    if ($act) {
        [$mail] = $db->sql_fetchrow($db->sql_query('SELECT mail FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
        $amail = ($cfg['mail'] ?? '') ? $cfg['mail'] : ($conf['adminmail'] ?? '');
        $subject = ($conf['sitename'] ?? '').' - '._MONEY;
        $msg = ($conf['sitename'] ?? '').' - '._MONEY.'<br><br>';
        $msg .= bb_decode($cfg['sendinfo'] ?? '', 'all');
        mail_send($mail, $amail, $subject, $msg, 0, 3);
        header('Location: '.$afile.'.php?name=money&send=1');
        exit;
    }
    header('Location: '.$afile.'.php?name=money');
    exit;
}

function conf(): void {
    global $afile, $conf;
    $cfg = $conf['money'] ?? [];
    head();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms('money.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._MA_3.':</td><td><input type="text" name="proz" value="'.($cfg['proz'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_3.'" required></td></tr>'
    .'<tr><td>'._MA_4.': EUR > USD</td><td><input type="text" name="kurs" value="'.($cfg['kurs'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_4.'" required></td></tr>'
    .'<tr><td>'._MA_4.': EUR > RUB</td><td><input type="text" name="kurs2" value="'.($cfg['kurs2'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_4.'" required></td></tr>'
    .'<tr><td>'._MA_5.':</td><td><input type="text" name="bal" value="'.($cfg['bal'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._MA_5.'" required></td></tr>'
    .'<tr><td>'._MA_6.':</td><td><input type="email" name="mail" value="'.($cfg['mail'] ?? '').'" maxlength="255" class="sl_conf" placeholder="'._MA_6.'" required></td></tr>'
    .'<tr><td>'._MA_7.':</td><td><textarea name="form" cols="65" rows="5" class="sl_conf" placeholder="'._MA_7.'" required>'.($cfg['form'] ?? '').'</textarea></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($cfg['anum'] ?? 25).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($cfg['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._MA_8.'</td><td>'.radio_form($cfg['an'] ?? 0, 'an').'</td></tr>'
    .'<tr><td>'._MA_9.'</td><td>'.radio_form($cfg['pr'] ?? 0, 'pr').'</td></tr>'
    .'<tr><td>'._MA_10.'</td><td>'.radio_form($cfg['ad'] ?? 0, 'ad').'</td></tr>'
    .'<tr><td>'._MA_11.':</td><td>'.textarea('1', 'text', $cfg['text'] ?? '', 'all', '5', _MA_11, '1').'</td></tr>'
    .'<tr><td>'._MA_12.':</td><td>'.textarea('2', 'info', $cfg['info'] ?? '', 'all', '5', _MA_12, '1').'</td></tr>'
    .'<tr><td>'._MA_13.':</td><td>'.textarea('3', 'sendinfo', $cfg['sendinfo'] ?? '', 'all', '5', _MA_13, '1').'</td></tr>'
    .'<tr><td>'._MA_14.':</td><td>'.textarea('4', 'autor', $cfg['autor'] ?? '', 'all', '5', _MA_14, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="money"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
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
    header('Location: '.$afile.'.php?name=money&op=conf');
    exit;
}

function info(): void {
    head();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.adm_info(1, 'money', 0).'</div>';
    foot();
}

switch ($op) {
    default: money(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'active': active(); break;
    case 'del': del(); break;
    case 'rechn': rechn(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
