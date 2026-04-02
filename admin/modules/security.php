<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

$labels = [
    'database' => 'Database',
    'dump' => _SEC_STAT_DUM,
    'dump_log' => _SEC_STAT_DUML,
    'error_php' => _SEC_STAT_ERROR_D,
    'error_file' => _SEC_STAT_ERROR_FILE,
    'error_site' => _SEC_STAT_ERROR_S,
    'error_sql' => _SEC_STAT_ERROR_SQL,
    'hack' => _SEC_STAT_HACK,
    'log' => _SEC_STAT_LOG,
    'log_admin' => _SEC_STAT_A,
    'log_user' => _SEC_STAT_U,
    'warn' => _SEC_STAT_WARN
];

function security(): void {
    global $afile, $labels, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $head = getTplAdminTableHead([_TITLE, _SIZE, _DATE, [_FUNCTIONS, 'nosort']]);
    $rows = '';
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (preg_match('#(.*)\.log$#', $file)) {
            $name = (string)pathinfo($file, PATHINFO_FILENAME);
            $title = $labels[$name];
            $path = LOGS_DIR.'/'.$file;
            $filesize = filesize($path);
            $acts = getTplAdminActionMenu([
                getTplLinkAction($afile.'.php?name=security&amp;op=logview&amp;file='.$name, _INFO, _INFO),
                getTplLinkAction($afile.'.php?name=security&amp;op=download&amp;file='.$name, _DOWN, _DOWN),
                getTplAdminDeleteAction($afile.'.php?name=security&amp;op=delete&amp;file='.$name.'&amp;token='.getSiteToken(), _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow(getTplAdminTableCells([
                getTplAdminTitleTip(_FILE.': storage/logs/'.$file).$title,
                filterSize($filesize),
                date(_TIMESTRING, filemtime($path)),
                $acts,
            ]));
        }
    }
    $cont .= getTplAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function logview(): void {
    global $labels, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security']);
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $title = $labels[$file] ?? $file;
        $path = LOGS_DIR.'/'.$file.'.log';
        $content = (is_file($path) && is_readable($path)) ? file_get_contents($path) : false;
        if ($content === false) {
            $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
            echo $cont;
            setFoot();
            return;
        }
        $cont .= checkPerms($path);
        $logv = getTplAdminSection($title).textarea_code('code', '', 'sl_form', 'message/http', $content);
        $cont .= getTplBox($logv);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function banlist(): void {
    global $conf, $afile, $tpl;
    $time = getVar('req', 'time', 'num');
    $info = getVar('req', 'info', 'text');
    $hash = getVar('req', 'hash', 'text');
    $cidr = getVar('req', 'cidr', 'text');
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 1, 'subtab' => 1, 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    if (getVar('get', 'send', 'var')) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MAIL_SEND]);
    $tabone = '';
    $bip = explode('||', $conf['security']['blocker_ip']);
    if ($conf['security']['blocker_ip']) {
        $rows = '';
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $tcidr = getIpCidr($binfo[0]);
                if ($tcidr === false) continue;
                [$tip, $tmask] = explode('/', $tcidr, 2);
                $rows .= getTplAdminTableRow(getTplAdminTableCells([
                    getTplAdminTitleTip(_BANN_REAS.': '.$binfo[3]).user_geo_ip($tip, 4),
                    '/'.$tmask,
                    $binfo[1],
                    getTimeLeft((int)$binfo[2]),
                    getTplAdminActionMenu([getTplAdminDeleteAction($afile.'.php?name=security&amp;op=bansave&amp;cidr='.urlencode($tcidr).'&amp;hash='.urlencode($binfo[1]).'&amp;time='.(int)$binfo[2].'&amp;id=1&amp;token='.getSiteToken(), _DELETE.' "'.$tcidr.'"?', _ONDELETE, _ONDELETE)]),
                ]));
            }
        }
        $tabone .= getTplAdminTable(getTplAdminTableHead([_IP, _IP_CIDR, _HASH, _DATE, [_FUNCTIONS, 'nosort']]), $rows, 'sl_table_list_sort');
        $tabone .= '<hr>';
    }
    $hide = getTplHiddenInput('op', 'bansave').getTplHiddenInput('id', '2').getTplHiddenInput('token', getSiteToken());
    $rows = getTplAdminFormRow(
        $tpl->getHtmlFrag('label-hint', ['label' => _IP_CIDR.':', 'hint' => _IP_CIDR_TIP]),
        getTplTextarea('cidr', $cidr)
    );
    $rows .= getTplAdminFormRow(
        _HASH.':',
        getTplTextInput('hash', $hash)
    );
    $rows .= getTplAdminFormRow(
        _TIME.':',
        getTplNumberInput((string)$time, 'time')
    );
    $rows .= getTplAdminFormRow(
        _BANN_REAS.':',
        getTplTextarea('info', $info)
    );
    $rows .= getTplAdminFormWide(getTplAdminSubmitButton(_ADD), '', 'sl_center');
    $tabone .= getTplAdminForm($afile.'.php?name=security', $rows, $hide);
    $tabtwo = '';
    $busers = explode('||', $conf['security']['blocker_user']);
    if ($conf['security']['blocker_user']) {
        $rows = '';
        foreach ($busers as $val) {
            if ($val != '') {
                $binfo = explode('|', $val);
                $rows .= getTplAdminTableRow(getTplAdminTableCells([
                    user_info($binfo[0]),
                    $binfo[2],
                    getTimeLeft($binfo[1]),
                    getTplAdminActionMenu([getTplAdminDeleteAction($afile.'.php?name=security&amp;op=bansave&amp;name='.$binfo[0].'&amp;time='.$binfo[1].'&amp;id=3&amp;token='.getSiteToken(), _DELETE.' "'.$binfo[0].'"?', _ONDELETE, _ONDELETE)]),
                ]));
            }
        }
        $tabtwo .= getTplAdminTable(getTplAdminTableHead([_NICKNAME, _BANN_REAS, _DATE, [_FUNCTIONS, 'nosort']]), $rows, 'sl_table_list_sort');
        $tabtwo .= '<hr>';
    }
    $name = getVar('get', 'name', 'name');
    $cookie = $conf['user_c'].'-close-security';
    $check = (getCookies('close-security') == '0') ? '' : ' checked';
    $tabtwo .= $tpl->getHtmlFrag('admin-security-ban-user-form', [
        'add_label' => _ADD,
        'check_attr' => $check,
        'cookie_id' => $cookie,
        'info_label' => _BANN_REAS.':',
        'info_placeholder' => _BANN_REAS,
        'info_value' => $info,
        'mail_label' => _MAIL_SENDE,
        'mailtext_hint' => _MAIL_INFO,
        'mailtext_html' => textarea('1', 'mailtext', replace_break(str_replace('[text]', _BANN_INFO.PHP_EOL.PHP_EOL._BANN_TERM.': [time]'.PHP_EOL._BANN_REAS.': [info]', $conf['mtemp'])), 'all', '10'),
        'mailtext_label' => _MAIL_TEXT.':',
        'name_html' => getUserSearch('name', $name, 25, 'sl_form', '1'),
        'name_label' => _NICKNAME.':',
        'route' => $afile,
        'time_label' => _TIME.':',
        'time_value' => (string)$time,
    ]);
    $banv = $tpl->getHtmlFrag('admin-uploads-config-tabs', [
        'tab_one_id' => getTplAdminTabName('security', 0, true),
        'tab_two_id' => getTplAdminTabName('security', 1, true),
        'tab_one_html' => $tabone,
        'tab_two_html' => $tabtwo,
    ]);
    $banv .= getTplAdminTabsSetup('securitys');
    echo $cont.getTplBox($banv);
    setFoot();
}

function bansave(): void {
    global $db, $conf, $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security', 'tab' => 1, 'subtab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $send = '';
    $id = getVar('req', 'id', 'num');
    $cidr = getVar('req', 'cidr', 'text');
    $name = getVar('req', 'name', 'name');
    $mail = getVar('post', 'mail', 'bool');
    $info = trim(getVar('post', 'info', 'text'));
    $info = ($info) ? $info : _BANN_INFO;
    $mailtext = trim(getVar('post', 'mailtext', 'text'));
    $hash = getVar('req', 'hash', 'text', '0');
    $time = getVar('req', 'time', 'num');
    $cidr = $cidr ? getIpCidr($cidr) : '';
    $cont = $conf['security'];
    if ($id == 1 && $cidr) {
        $bip = explode('||', $conf['security']['blocker_ip']);
        $new = '';
        foreach ($bip as $val) {
            if ($val == '') continue;
            $binfo = explode('|', $val, 4);
            if (count($binfo) < 4) continue;
            $tcidr = getIpCidr($binfo[0]);
            if ($tcidr === false) continue;
            if ($tcidr === $cidr && $binfo[1] === $hash && (int)$binfo[2] === (int)$time) continue;
            $new .= $val.'||';
        }
        $cont['blocker_ip'] = $new;
    } elseif ($id == 2 && $cidr) {
        $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
        $cont['blocker_ip'] = $conf['security']['blocker_ip'].$cidr.'|'.$hash.'|'.$time.'|'.$info.'||';
    } elseif ($id == 3 && $name) {
        $blocker_user = preg_replace('#'.$name.'\|'.$time.'\|(.*)\|\|#iU', '', $conf['security']['blocker_user']);
        $cont['blocker_user'] = $blocker_user;
    } elseif ($id == 4 && $name) {
        $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
        $cont['blocker_user'] = $conf['security']['blocker_user'].$name.'|'.$time.'|'.$info.'||';
        if ($mail) {
            [$mail_addr] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $name]));
            $subject = $conf['sitename'].' - '._SECURITY;
            $msg = nl2br(filterReplaceText(filterMarkdown(str_replace('[time]', getTimeLeft($time), str_replace('[info]', $info, $mailtext)), 'all', false), 'all'), false);
            addMail($mail_addr, $conf['adminmail'], $subject, $msg, 0, 3);
            $send = '&send=1';
        }
    }
    setConfigFile('security.php', $cont);
    setRedirect($afile.'.php?name=security&op=banlist'.$send);
}

function passwd(): void {
    global $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 2, 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= (!$conf['security']['login'] || !$conf['security']['password']) ? $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _SEC_AUTH_INFO]) : $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _SEC_AUTH_OK]);
    $hide = getTplHiddenInput('op', 'passsave').getTplHiddenInput('token', getSiteToken());
    $rows = getTplAdminFormRow(
        $tpl->getHtmlFrag('label-hint', ['label' => _SEC_ADMIN_IP.':', 'hint' => _IP_CIDR_TIP]),
        getTplTextarea('admin_ip', $conf['security']['admin_ip'])
    );
    if (!$conf['security']['login'] || !$conf['security']['password']) {
        $rows .= getTplAdminFormRow(
            _SEC_LOGIN.':',
            getTplTextInput('login', '')
        );
        $rows .= getTplAdminFormRow(
            _SEC_PASSWORD.':',
            getTplTextInput('password', '')
        );
    } else {
        $hide .= getTplHiddenInput('login', '');
        $hide .= getTplHiddenInput('password', '');
    }
    $rows .= getTplAdminFormWide(getTplAdminSubmitButton(_SAVECHANGES), '', 'sl_center');
    $cont .= getTplAdminForm($afile.'.php?name=security', $rows, $hide);
    echo $cont;
    setFoot();
}

function passsave(): void {
    global $conf, $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security', 'tab' => 2]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $protect = [PHP_EOL => '', ' ' => ''];
    $admin_ip = getVar('post', 'admin_ip', 'text');
    $login = getVar('post', 'login', 'text');
    $password = getVar('post', 'password', 'text');
    $xadmin_ip = strtr($admin_ip, $protect);
    $xlogin = empty($login) ? $conf['security']['login'] : password_hash($login, PASSWORD_DEFAULT);
    $xpassword = empty($password) ? $conf['security']['password'] : password_hash($password, PASSWORD_DEFAULT);
    $ips = [];
    foreach (explode(',', $xadmin_ip) as $val) {
        $val = trim($val);
        if ($val === '') continue;
        $cidr = getIpCidr($val);
        if ($cidr !== false) $ips[] = $cidr;
    }
    $cont = $conf['security'];
    $cont['admin_ip'] = implode(',', $ips);
    $cont['login'] = $xlogin;
    $cont['password'] = $xpassword;
    setConfigFile('security.php', $cont);
    setRedirect($afile.'.php?name=security&op=passwd');
}

function config(): void {
    global $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 3, 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $ainfo = sprintf(_ADMIN_FILE_INFO, strtolower(getPass('10')));
    $floodhtml = getTplSelect('flood',
        getTplOption('0', _NO, $conf['security']['flood'] == 0)
        .getTplOption('1', _SFLOOD_1, $conf['security']['flood'] == 1)
        .getTplOption('2', _SFLOOD_2, $conf['security']['flood'] == 2)
        .getTplOption('3', _SFLOOD_3, $conf['security']['flood'] == 3),
        'sl_conf');
    $errorhtml = getTplSelect('error',
        getTplOption('0', _NO, $conf['security']['error'] == 0)
        .getTplOption('1', _SEC_VIEW_1, $conf['security']['error'] == 1)
        .getTplOption('2', _SEC_VIEW_2, $conf['security']['error'] == 2),
        'sl_conf');
    $rows = [
        ['label_html' => _SFLOOD.':', 'field_html' => $floodhtml],
        ['label_html' => _SEC_VIEW.':', 'field_html' => $errorhtml],
        ['label_html' => _SFLOD_T.':', 'field_html' => getTplNumberInput((string)$conf['security']['flood_t'], 'flood_t', 'sl_conf')],
        ['label_html' => _SEC_COOKIE.':', 'field_html' => $tpl->getHtmlFrag('input', ['input_class' => 'sl_conf', 'itype' => 'text', 'name_attr' => 'blocker_cookie', 'value_attr' => $conf['security']['blocker_cookie']])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _ADMIN_FILE.':', 'hint' => $ainfo]), 'field_html' => $tpl->getHtmlFrag('input', ['input_class' => 'sl_conf', 'itype' => 'text', 'name_attr' => 'afile', 'value_attr' => $conf['security']['afile']])],
        ['label_html' => _SEC_LOG_SIZE.':', 'field_html' => getTplNumberInput((string)$conf['security']['log_size'], 'log_size', 'sl_conf')],
        ['label_html' => _SEC_LOG_DS.':', 'field_html' => getTplNumberInput((string)intval($conf['security']['sess_d'] / 60), 'sess_d', 'sl_conf')],
        ['label_html' => _SEC_LOG_DB.':', 'field_html' => getTplNumberInput((string)intval($conf['security']['sess_b'] / 60), 'sess_b', 'sl_conf')],
        ['label_html' => _SEC_DB, 'field_html' => radio_form($conf['security']['log_b'], 'log_b')],
        ['label_html' => _SEC_VIEW_JAVA, 'field_html' => radio_form($conf['security']['error_java'], 'error_java')],
        ['label_html' => _SEC_STAT, 'field_html' => radio_form($conf['security']['error_log'], 'error_log')],
        ['label_html' => _SEC_URL_GET, 'field_html' => radio_form($conf['security']['url_get'], 'url_get')],
        ['label_html' => _SEC_URL_POST, 'field_html' => radio_form($conf['security']['url_post'], 'url_post')],
        ['label_html' => _SEC_REF_POST, 'field_html' => radio_form($conf['security']['ref_post'], 'ref_post')],
        ['label_html' => _SEC_MAIL_SEND, 'field_html' => radio_form($conf['security']['mail'], 'mail')],
        ['label_html' => _SEC_MAIL_W_SEND, 'field_html' => radio_form($conf['security']['mail_w'], 'mail_w')],
        ['label_html' => _SEC_MAIL_D_SEND, 'field_html' => radio_form($conf['security']['mail_d'], 'mail_d')],
        ['label_html' => _SEC_HACK_STAT, 'field_html' => radio_form($conf['security']['write_h'], 'write_h')],
        ['label_html' => _SEC_WARN_STAT, 'field_html' => radio_form($conf['security']['write_w'], 'write_w')],
        ['label_html' => _SEC_LOG, 'field_html' => radio_form($conf['security']['log'], 'log')],
        ['label_html' => _SEC_LOG_D, 'field_html' => radio_form($conf['security']['log_d'], 'log_d')],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEC_DUMP_SKIP.':', 'hint' => _SEC_DUMP_SKIP_INFO]), 'field_html' => getTplTextarea('dump_skip', htmlspecialchars((string)($conf['security']['dump_skip'] ?? ''), ENT_QUOTES, 'UTF-8'), 'sl_conf', '', 65, 8), 'row_class' => 'sl-config-item-full'],
        ['label_html' => _SEC_LOG_A, 'field_html' => radio_form($conf['security']['log_a'], 'log_a')],
        ['label_html' => _SEC_LOG_U, 'field_html' => radio_form($conf['security']['log_u'], 'log_u')],
        ['label_html' => _SEC_WARN_BLOCK, 'field_html' => radio_form($conf['security']['block'], 'block')],
    ];
    $confv = $tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'security'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($confv);
    setFoot();
}

function configsave(): void {
    global $conf, $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security', 'tab' => 3]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $flood_t = getVar('post', 'flood_t', 'num', '1');
    $afile = getVar('post', 'afile', 'text');
    $tafile = ($conf['security']['afile']) ? $conf['security']['afile'] : 'admin';
    if ($afile != $tafile) rename($tafile.'.php', $afile.'.php');
    $afile = (file_exists($afile.'.php')) ? $afile : $tafile;
    $log_size = getVar('post', 'log_size', 'num', '1048576');
    $sess_d = getVar('post', 'sess_d', 'num', 1440) * 60;
    $sess_b = getVar('post', 'sess_b', 'num', 1440) * 60;
    $rawskip = str_replace(["\r\n", "\r"], "\n", (string)getVar('post', 'dump_skip', 'raw', ''));
    $lines = explode("\n", $rawskip);
    $dskip = [];
    foreach ($lines as $line) {
        $line = trim(str_replace('\\', '/', (string)$line));
        $line = preg_replace('#/+#', '/', $line);
        $line = preg_replace('#^\./#', '', (string)$line);
        $line = trim((string)$line, " \t\n\r\0\x0B");
        if ($line === '' || $line === '.' || $line === './') continue;
        if (str_contains($line, '..')) continue;
        if (!str_ends_with($line, '/')) $line .= '/';
        $dskip[] = $line;
    }
    $dskip = array_values(array_unique($dskip));
    $cont = [
        'flood' => getVar('post', 'flood', 'num'),
        'error' => getVar('post', 'error', 'num'),
        'flood_t' => $flood_t,
        'blocker_cookie' => getVar('post', 'blocker_cookie', 'text'),
        'afile' => $afile,
        'log_size' => $log_size,
        'sess_d' => $sess_d,
        'sess_b' => $sess_b,
        'log_b' => getVar('post', 'log_b', 'num'),
        'error_java' => getVar('post', 'error_java', 'num'),
        'error_log' => getVar('post', 'error_log', 'num'),
        'url_get' => getVar('post', 'url_get', 'num'),
        'url_post' => getVar('post', 'url_post', 'num'),
        'ref_post' => getVar('post', 'ref_post', 'num'),
        'mail' => getVar('post', 'mail', 'num'),
        'mail_w' => getVar('post', 'mail_w', 'num'),
        'mail_d' => getVar('post', 'mail_d', 'num'),
        'write_h' => getVar('post', 'write_h', 'num'),
        'write_w' => getVar('post', 'write_w', 'num'),
        'log' => getVar('post', 'log', 'num'),
        'log_d' => getVar('post', 'log_d', 'num'),
        'dump_skip' => implode("\n", $dskip),
        'log_a' => getVar('post', 'log_a', 'num'),
        'log_u' => getVar('post', 'log_u', 'num'),
        'block' => getVar('post', 'block', 'num')
    ];
    $cont['blocker_ip'] = $conf['security']['blocker_ip'];
    $cont['blocker_user'] = $conf['security']['blocker_user'];
    $cont['admin_ip'] = $conf['security']['admin_ip'];
    $cont['login'] = $conf['security']['login'];
    $cont['password'] = $conf['security']['password'];
    setConfigFile('security.php', $cont);
    setRedirect($afile.'.php?name=security&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 4, 'id' => 'security']);
    setAdminInfoPage($cont);
}

function download(): void {
    global $afile;
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $path = LOGS_DIR.'/'.$file.'.log';
        if (is_file($path)) {
            stream($path, date('d.m.Y').'_'.$file.'.log');
            return;
        }
        setRedirect($afile.'.php?name=security');
    } else {
        setRedirect($afile.'.php?name=security');
    }
}

function delete(): void {
    global $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security']);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $path = LOGS_DIR.'/'.$file.'.log';
        if (is_file($path)) unlink($path);
    }
    setRedirect($afile.'.php?name=security');
}

switch ($op) {
    default: security(); break;
    case 'logview': logview(); break;
    case 'download': download(); break;
    case 'delete': delete(); break;
    case 'banlist': banlist(); break;
    case 'bansave': bansave(); break;
    case 'passwd': passwd(); break;
    case 'passsave': passsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
