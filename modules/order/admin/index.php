<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('order')) die('Illegal file access');

function order(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $cont = setAdminNavi(['ops' => ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    if (getVar('get', 'send', 'num', 0)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _OR_8]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['order']['anum'] ?? 25;
    $anump = $conf['order']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, email, info, note, ip, agent, time, status FROM '.PREFIX_DB.'_order ORDER BY time DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_order'));
        $r = $numstories;
        if ($numstories > $offset) $r -= $offset;
        $numpages = ceil($numstories / $anum);
        $head = $tpl->getHtmlFrag('admin-order-list-head', [
            'date_label' => _DATE,
            'email_label' => _EMAIL,
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'ip_label' => _IP,
            'status_label' => _STATUS,
        ]);
        $rows = '';
        while ([$id, $email, $info, $note, $ip, $agent, $date, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $infos = fields_out($info, 'order');
            $acts = adminMenuItems([
                ad_status($afile.'.php?name=order&amp;op=activate&amp;id='.$id.'&amp;act='.$act, $status),
                adminLinkAction($afile.'.php?name=order&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=order&amp;op=delete&amp;id='.$id, _DELETE.' "'._ID.': '.$id.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-order-list-row', [
                'actions_html' => $acts,
                'date_text' => format_time($date, _TIMESTRING),
                'email_html' => title_tip($infos.'<br>'._COMMENT.': '.$note.'<br><br>'._BROWSER.': '.$agent).anti_spam($email),
                'id_text' => (string)$id,
                'ip_html' => user_geo_ip($ip, 4),
                'status_html' => ad_status('', $status),
            ]));
            $r--;
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setPageNumbers('pagenum', '', $numstories, $numpages, $anum, 'name=order&amp;', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT email, info, note, time FROM '.PREFIX_DB.'_order WHERE id = :mid', ['mid' => $mid]);
        [$email, $field, $note, $date] = $db->getSqlRow($result);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $email = getVar('post', 'email', 'text', '');
        $field = getVar('post', 'field', 'field');
        $note = getVar('post', 'note', 'text', '');
        $date = getVar('req', 'date', 'time');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if ($field) $cont .= preview($email, $field, _COMMENT.': '.$note, '', 'all');
    $hide = '<input type="hidden" name="name" value="order">';
    $rows = $tpl->getHtmlFrag('admin-order-add-rows', [
        'date_html' => datetime(1, 'date', $date, 16, 'sl_form'),
        'date_label' => _CHNGSTORY.':',
        'email_label' => _OR_9.':',
        'email_value' => $email,
        'fields_html' => fields_in($field, 'order'),
        'note_label' => _OR_10.':',
        'note_placeholder' => _OR_10,
        'note_value' => $note,
        'save_html' => ad_save('mid', $mid, 'save'),
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $email = getVar('post', 'email', 'text', '');
    $field = getVar('post', 'field', 'field');
    $note = getVar('post', 'note', 'text', '');
    $date = getVar('req', 'date', 'time');
    checkemail($email);
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET email = :email, info = :info, note = :note, time = :time WHERE id = :mid', ['email' => $email, 'info' => $field, 'note' => $note, 'time' => $date, 'mid' => $mid]);
        } else {
            $ip = getip();
            $agent = getagent();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :email, :info, :note, :ip, :agent, :time, \'1\')', ['email' => $email, 'info' => $field, 'note' => $note, 'ip' => $ip, 'agent' => $agent, 'time' => $date]);
        }
        setRedirect($afile.'.php?name=order');
    } elseif ($posttype === 'delete') {
        delete($mid);
    } else {
        add();
    }
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=order');
}

function activate(): void {
    global $db, $afile, $conf;
        $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
    if ($act) {
        [$email] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]));
        $amail = ($conf['order']['mail'] ?? '') ? $conf['order']['mail'] : ($conf['adminmail'] ?? '');
        $subject = ($conf['sitename'] ?? '').' - '._ORDER;
        $msg = ($conf['sitename'] ?? '').' - '._ORDER.'<br><br>';
        $msg .= filterReplaceText(filterMarkdown($conf['order']['sendinfo'] ?? '', 'all', false), 'all');
        addMail($email, $amail, $subject, $msg, 0, 3);
        setRedirect($afile.'.php?name=order&send=1');
    }
    setRedirect($afile.'.php?name=order');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/order.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'order',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_or1' => _OR_1,
        'mail' => $conf['order']['mail'] ?? '',
        '_c34' => _C_34,
        'anum' => $conf['order']['anum'] ?? 25,
        '_c36' => _C_36,
        'anump' => $conf['order']['anump'] ?? 10,
        '_or2' => _OR_2,
        'r_an' => radio_form($conf['order']['an'] ?? 0, 'an'),
        '_or3' => _OR_3,
        'r_pr' => radio_form($conf['order']['pr'] ?? 0, 'pr'),
        '_or4' => _OR_4,
        'r_ad' => radio_form($conf['order']['ad'] ?? 0, 'ad'),
        '_or5' => _OR_5,
        't_text' => textarea('1', 'text', $conf['order']['text'] ?? '', 'all', '5', _OR_5, '1'),
        '_or6' => _OR_6,
        't_info' => textarea('2', 'info', $conf['order']['info'] ?? '', 'all', '5', _OR_6, '1'),
        '_or7' => _OR_7,
        't_sendinfo' => textarea('3', 'sendinfo', $conf['order']['sendinfo'] ?? '', 'all', '5', _OR_7, '1'),
        'order' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
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
    setRedirect($afile.'.php?name=order&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: order(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'activate': activate(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
