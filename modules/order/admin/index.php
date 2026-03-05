<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('order')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=conf', 'name=order&amp;op=info'];
    $lang = [_HOME, _ADD, _PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function order(): void {
    global $db, $afile, $conf;
        setHead();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get', 'send', 'num', 0)) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _OR_8]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['order']['anum'] ?? 25;
    $anump = $conf['order']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, mail, info, com, ip, agent, date, status FROM '.PREFIX_DB.'_order ORDER BY date DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_order'));
        $r = $numstories;
        if ($numstories > $offset) $r -= $offset;
        $numpages = ceil($numstories / $anum);
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._EMAIL.'</th><th>'._IP.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $mail, $info, $com, $ip, $agent, $date, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $infos = fields_out($info, 'order');
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip($infos.'<br>'._COMMENT.': '.$com.'<br><br>'._BROWSER.': '.$agent).anti_spam($mail).'</td>'
            .'<td>'.user_geo_ip($ip, 4).'</td>'
            .'<td>'.format_time($date, _TIMESTRING).'</td>'
            .'<td>'.ad_status('', $status).'</td>'
            .'<td>'.add_menu(ad_status($afile.'.php?name=order&amp;op=active&amp;id='.$id.'&amp;act='.$act, $status).'||<a href="'.$afile.'.php?name=order&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=order&amp;op=del&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'._ID.': '.$id.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
            $r--;
        }
        $cont .= '</tbody></table>';
        $cont .= setPageNumbers('pagenum', '', $numstories, $numpages, $anum, 'name=order&amp;', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT mail, info, com, date FROM '.PREFIX_DB.'_order WHERE id = :mid', ['mid' => $mid]);
        [$mail, $field, $com, $date] = $db->getSqlRow($result);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $mail = getVar('post', 'mail', 'text', '');
        $field = getVar('post', 'field', 'field');
        $com = getVar('post', 'com', 'text', '');
        $date = getVar('req', 'date', 'time');
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if ($field) $cont .= preview($mail, $field, _COMMENT.': '.$com, '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._OR_9.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="255" class="sl_form" placeholder="'._OR_9.'" required></td></tr>'
    .fields_in($field, 'order')
    .'<tr><td>'._OR_10.':</td><td><textarea name="com" cols="65" rows="5" class="sl_form" placeholder="'._OR_10.'">'.$com.'</textarea></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="order">'.ad_save('mid', $mid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $mail = getVar('post', 'mail', 'text', '');
    $field = getVar('post', 'field', 'field');
    $com = getVar('post', 'com', 'text', '');
    $date = getVar('req', 'date', 'time');
    checkemail($mail);
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET mail = :mail, info = :info, com = :com, date = :date WHERE id = :mid', ['mail' => $mail, 'info' => $field, 'com' => $com, 'date' => $date, 'mid' => $mid]);
        } else {
            $ip = getip();
            $agent = getagent();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :mail, :info, :com, :ip, :agent, :date, \'1\')', ['mail' => $mail, 'info' => $field, 'com' => $com, 'ip' => $ip, 'agent' => $agent, 'date' => $date]);
        }
        setRedirect($afile.'.php?name=order');
    } elseif ($posttype === 'delete') {
        del($mid);
    } else {
        add();
    }
}

function del(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=order');
}

function active(): void {
    global $db, $afile, $conf;
        $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
    if ($act) {
        [$mail] = $db->getSqlRow($db->getSqlQuery('SELECT mail FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]));
        $amail = ($conf['order']['mail'] ?? '') ? $conf['order']['mail'] : ($conf['adminmail'] ?? '');
        $subject = ($conf['sitename'] ?? '').' - '._ORDER;
        $msg = ($conf['sitename'] ?? '').' - '._ORDER.'<br><br>';
        $msg .= bb_decode($conf['order']['sendinfo'] ?? '', 'all');
        addMail($mail, $amail, $subject, $msg, 0, 3);
        setRedirect($afile.'.php?name=order&send=1');
    }
    setRedirect($afile.'.php?name=order');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/order.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._OR_1.':</td><td><input type="email" name="mail" value="'.($conf['order']['mail'] ?? '').'" maxlength="255" class="sl_conf" placeholder="'._OR_1.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['order']['anum'] ?? 25).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['order']['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._OR_2.'</td><td>'.radio_form($conf['order']['an'] ?? 0, 'an').'</td></tr>'
    .'<tr><td>'._OR_3.'</td><td>'.radio_form($conf['order']['pr'] ?? 0, 'pr').'</td></tr>'
    .'<tr><td>'._OR_4.'</td><td>'.radio_form($conf['order']['ad'] ?? 0, 'ad').'</td></tr>'
    .'<tr><td>'._OR_5.':</td><td>'.textarea('1', 'text', $conf['order']['text'] ?? '', 'all', '5', _OR_5, '1').'</td></tr>'
    .'<tr><td>'._OR_6.':</td><td>'.textarea('2', 'info', $conf['order']['info'] ?? '', 'all', '5', _OR_6, '1').'</td></tr>'
    .'<tr><td>'._OR_7.':</td><td>'.textarea('3', 'sendinfo', $conf['order']['sendinfo'] ?? '', 'all', '5', _OR_7, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="order"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'mail' => getVar('post', 'mail', 'text', ''),
        'anum' => getVar('post', 'anum', 'num', 25),
        'anump' => getVar('post', 'anump', 'num', 10),
        'an' => getVar('post', 'an', 'num', 0),
        'pr' => getVar('post', 'pr', 'num', 0),
        'ad' => getVar('post', 'ad', 'num', 0),
        'text' => getVar('post', 'text', 'text', ''),
        'info' => getVar('post', 'info', 'text', ''),
        'sendinfo' => getVar('post', 'sendinfo', 'text', ''),
    ];
    setConfigFile('order.php', $cont);
    setRedirect($afile.'.php?name=order&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: order(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'active': active(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}




