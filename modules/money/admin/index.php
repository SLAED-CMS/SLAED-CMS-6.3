<?php
# Author: Eduard Laas
# Copyright ï¿½ 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('money')) die('Illegal file access');

function money(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $cont = getTplAdminNavi(['ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    if (getVar('get', 'send', 'num', 0)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MA_15]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['money']['anum'] ?? 25;
    $anump = $conf['money']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, sum, email, intro, note, ip, agent, time, status FROM '.PREFIX_DB.'_money ORDER BY time DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_money'));
        $r = $numstories;
        if ($numstories > $offset) $r -= $offset;
        $numpages = ceil($numstories / $anum);
        $head = getTplAdminTableHead([_ID, _SUM, _EMAIL, _IP, _DATE, [_STATUS, 'nosort'], [_FUNCTIONS, 'nosort']]);
        $rows = '';
        $form = explode(',', $conf['money']['form'] ?? '');
        while ([$id, $sum, $email, $intro, $note, $ip, $agent, $time, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $intro = explode('|', $intro);
            $i = 0;
            $infos = '';
            foreach ($form as $val) {
                if ($val != '') {
                    $infos .= getTplAdminInfoLine($val, $intro[$i] ?? '');
                    $i++;
                }
            }
            $acts = getTplAdminActionMenu([
                ad_status($afile.'.php?name=money&amp;op=activate&amp;id='.$id.'&amp;act='.$act, $status),
                getTplLinkAction($afile.'.php?name=money&amp;op=invoice&amp;id='.$id.'&amp;rnum='.$r, _RECHN_B, _RECHN_B),
                getTplLinkAction($afile.'.php?name=money&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                getTplAdminDeleteAction($afile.'.php?name=money&amp;op=delete&amp;id='.$id, _DELETE.' "'._ID.': '.$id.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-money-list-row', [
                'actions_html' => $acts,
                'date_text' => format_time($time, _TIMESTRING),
                'email_html' => getTplAdminTitleTip($infos.'<br>'._COMMENT.': '.$note.'<br><br>'._BROWSER.': '.$agent).anti_spam($email),
                'id_text' => (string)$id,
                'ip_html' => user_geo_ip($ip, 4),
                'status_html' => ad_status('', $status),
                'sum_text' => $sum.' EUR',
            ]));
            $r--;
        }
        $cont .= getTplAdminTable($head, $rows);
        $cont .= setPageNumbers('pagenum', '', $numstories, $numpages, $anum, 'name=money&amp;', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
        $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT sum, email, intro, note, time FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $mid]);
        [$sum, $email, $intro, $note, $time] = $db->getSqlRow($result);
        $intro = explode('|', $intro);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $sum = getVar('post', 'sum', 'num', 0);
        $email = getVar('post', 'email', 'text', '');
        $intro = getVar('post', 'intro', 'array', []);
        $note = getVar('post', 'note', 'text', '');
        $time = getVar('req', 'time', 'time');
    }
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => getStopText((array)$stop)]);
    if ($intro) {
        $form = explode(',', $conf['money']['form'] ?? '');
        $i = 0;
        $infos = '';
        foreach ($form as $val) {
            if ($val != '') {
                $infos .= getTplAdminInfoLine($val, $intro[$i] ?? '');
                $i++;
            }
        }
        $cont .= preview($email, $infos, _COMMENT.': '.$note, '', 'all');
    }
    $hide = getTplHiddenInput('name', 'money');
    $introhtml = '';
    $form = explode(',', $conf['money']['form'] ?? '');
    $i = 0;
    foreach ($form as $val) {
        if ($val != '') {
            $introhtml .= getTplAdminFormRow($val.':', getTplTextInput('intro[]', $intro[$i] ?? '', 'sl_form', 'maxlength="255" placeholder="'.$val.'"'));
            $i++;
        }
    }
    $rows = $tpl->getHtmlFrag('admin-money-add-rows', [
        'email_label' => _MA_18.':',
        'email_value' => $email,
        'intro_html' => $introhtml,
        'note_label' => _MA_19.':',
        'note_placeholder' => _MA_19,
        'note_value' => $note,
        'save_html' => ad_save('mid', $mid, 'save'),
        'sum_label' => _MA_17.':',
        'sum_value' => (string)$sum,
        'time_html' => datetime(1, 'time', $time, 16, 'sl_form'),
        'time_label' => _CHNGSTORY.':',
    ]);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $sum = getVar('post', 'sum', 'num', 0);
    $email = getVar('post', 'email', 'text', '');
    $intro = getVar('post', 'intro', 'array', []);
    $list = (!empty($intro)) ? filterText(implode('|', $intro)) : '';
    $note = getVar('post', 'note', 'text', '');
    $time = getVar('req', 'time', 'time');
    checkemail($email);
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET sum = :sum, email = :email, intro = :intro, note = :note, time = :time WHERE id = :mid', ['sum' => $sum, 'email' => $email, 'intro' => $list, 'note' => $note, 'time' => $time, 'mid' => $mid]);
        } else {
            $ip = getip();
            $agent = getagent();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_money (`sum`, `email`, `intro`, `note`, `ip`, `agent`, `time`, `status`) VALUES (:sum, :email, :intro, :note, :ip, :agent, :time, \'1\')', ['sum' => $sum, 'email' => $email, 'intro' => $list, 'note' => $note, 'ip' => $ip, 'agent' => $agent, 'time' => $time]);
        }
        setRedirect($afile.'.php?name=money');
    } elseif ($posttype === 'delete') {
        delete($mid);
    } else {
        add();
    }
}

function delete(int $did = 0): void {
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

function invoice(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num', 0);
    [$sum, $email, $intro, $note, $ip, $agent, $time] = $db->getSqlRow($db->getSqlQuery('SELECT sum, email, intro, note, ip, agent, time FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
    $defis = urldecode($conf['defis'] ?? '%3E');
    $title = _RECHN.' '.$defis.' '._MONEY.' '.$defis.' '.($conf['sitename'] ?? '');
    $form = explode(',', $conf['money']['form'] ?? '');
    $intro = explode('|', $intro);
    $i = 0;
    $infos = '';
    foreach ($form as $val) {
        if ($val != '') {
            $infos .= getTplAdminInfoLine($val, $intro[$i] ?? '');
            $i++;
        }
    }
    $rnum = getVar('get', 'rnum', 'text', '');
    $kurs = (float)($conf['money']['kurs'] ?? 0);
    $proz = (float)($conf['money']['proz'] ?? 0);
    $menge = ($sum / 100) * $kurs * (100 - $proz);
    $kurs = ($menge > 0) ? round($sum / $menge, 2) : 0;
    billing($title, filterReplaceText(filterMarkdown($conf['money']['autor'] ?? '', 'money', false), 'money'), filterReplaceText(filterMarkdown($infos, 'money', false), 'money'), $rnum, format_time($time), (string)round($menge, 2), $kurs.' EUR', $sum.' EUR');
}

function activate(): void {
    global $db, $afile, $conf, $tpl;
        $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
    if ($act) {
        [$email] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
        $amail = ($conf['money']['mail'] ?? '') ? $conf['money']['mail'] : ($conf['adminmail'] ?? '');
        $subject = ($conf['sitename'] ?? '').' - '._MONEY;
        $msg = ($conf['sitename'] ?? '').' - '._MONEY.'<br><br>';
        $msg .= filterReplaceText(filterMarkdown($conf['money']['sendinfo'] ?? '', 'all', false), 'all');
        addMail($email, $amail, $subject, $msg, 0, 3);
        setRedirect($afile.'.php?name=money&send=1');
    }
    setRedirect($afile.'.php?name=money');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/money.php');
    $cont .= getTplBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'money',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_ma3' => _MA_3,
        'proz' => $conf['money']['proz'] ?? '',
        '_ma4_usd' => _MA_4.': EUR > USD',
        'kurs' => $conf['money']['kurs'] ?? '',
        '_ma4_rub' => _MA_4.': EUR > RUB',
        'kurs2' => $conf['money']['kurs2'] ?? '',
        '_ma5' => _MA_5,
        'bal' => $conf['money']['bal'] ?? '',
        '_ma6' => _MA_6,
        'mail' => $conf['money']['mail'] ?? '',
        '_ma7' => _MA_7,
        'form' => $conf['money']['form'] ?? '',
        '_c34' => _C_34,
        'anum' => $conf['money']['anum'] ?? 25,
        '_c36' => _C_36,
        'anump' => $conf['money']['anump'] ?? 10,
        '_ma8' => _MA_8,
        'r_an' => radio_form($conf['money']['an'] ?? 0, 'an'),
        '_ma9' => _MA_9,
        'r_pr' => radio_form($conf['money']['pr'] ?? 0, 'pr'),
        '_ma10' => _MA_10,
        'r_ad' => radio_form($conf['money']['ad'] ?? 0, 'ad'),
        '_ma11' => _MA_11,
        't_text' => textarea('1', 'text', $conf['money']['text'] ?? '', 'all', '5', _MA_11, '1'),
        '_ma12' => _MA_12,
        't_info' => textarea('2', 'info', $conf['money']['info'] ?? '', 'all', '5', _MA_12, '1'),
        '_ma13' => _MA_13,
        't_sendinfo' => textarea('3', 'sendinfo', $conf['money']['sendinfo'] ?? '', 'all', '5', _MA_13, '1'),
        '_ma14' => _MA_14,
        't_autor' => textarea('4', 'autor', $conf['money']['autor'] ?? '', 'all', '5', _MA_14, '1'),
        'money' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
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
    setRedirect($afile.'.php?name=money&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: money(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'activate': activate(); break;
    case 'delete': delete(); break;
    case 'invoice': invoice(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
