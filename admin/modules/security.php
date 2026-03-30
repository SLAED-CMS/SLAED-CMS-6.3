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
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $head = $tpl->getHtmlFrag('admin-security-list-head', [
        'date_label' => _DATE,
        'functions_label' => _FUNCTIONS,
        'size_label' => _SIZE,
        'title_label' => _TITLE,
    ]);
    $rows = '';
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (preg_match('#(.*)\.log$#', $file)) {
            $name = (string)pathinfo($file, PATHINFO_FILENAME);
            $title = $labels[$name];
            $path = LOGS_DIR.'/'.$file;
            $filesize = filesize($path);
            $acts = adminMenuItems([
                adminLinkAction($afile.'.php?name=security&amp;op=logview&amp;file='.$name, _INFO, _INFO),
                adminLinkAction($afile.'.php?name=security&amp;op=download&amp;file='.$name, _DOWN, _DOWN),
                adminDeleteAction($afile.'.php?name=security&amp;op=delete&amp;file='.$name, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-security-list-row', [
                'actions_html' => $acts,
                'date_text' => date(_TIMESTRING, filemtime($path)),
                'size_text' => filterSize($filesize),
                'title_html' => adminTitleTip(_FILE.': storage/logs/'.$file).$title,
            ]));
        }
    }
    $cont .= getAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function logview(): void {
    global $labels, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'id' => 'security']);
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $title = $labels[$file];
        $path = LOGS_DIR.'/'.$file.'.log';
        $content = (is_file($path) && is_readable($path)) ? file_get_contents($path) : false;
        if ($content === false) {
            $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
            echo $cont;
            setFoot();
            return;
        }
        $cont .= checkPerms($path);
        $logv = $tpl->getHtmlFrag('admin-security-logview-box', [
            'code_html' => textarea_code('code', '', 'sl_form', 'message/http', $content),
            'title_text' => $title,
        ]);
        $cont .= getAdminBox($logv);
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
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 1, 'subtab' => 1, 'id' => 'security']);
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
                $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-security-ban-ip-row', [
                    'actions_html' => adminMenuItems([adminDeleteAction($afile.'.php?name=security&amp;op=bansave&amp;cidr='.urlencode($tcidr).'&amp;hash='.urlencode($binfo[1]).'&amp;time='.(int)$binfo[2].'&amp;id=1', _DELETE.' "'.$tcidr.'"?', _ONDELETE, _ONDELETE)]),
                    'cidr_text' => '/'.$tmask,
                    'date_text' => getTimeLeft((int)$binfo[2]),
                    'hash_text' => $binfo[1],
                    'ip_html' => adminTitleTip(_BANN_REAS.': '.$binfo[3]).user_geo_ip($tip, 4),
                ]));
            }
        }
        $tabone .= getAdminTable($tpl->getHtmlFrag('admin-security-ban-ip-head', [
            'cidr_label' => _IP_CIDR,
            'date_label' => _DATE,
            'functions_label' => _FUNCTIONS,
            'hash_label' => _HASH,
            'ip_label' => _IP,
        ]), $rows, 'sl_table_list_sort');
        $tabone .= '<hr>';
    }
    $tabone .= $tpl->getHtmlFrag('admin-security-ban-ip-form', [
        'add_label' => _ADD,
        'cidr_hint' => _IP_CIDR_TIP,
        'cidr_label' => _IP_CIDR.':',
        'cidr_placeholder' => _IP_CIDR_EX,
        'cidr_value' => $cidr,
        'hash_label' => _HASH.':',
        'hash_value' => $hash,
        'info_label' => _BANN_REAS.':',
        'info_placeholder' => _BANN_REAS,
        'info_value' => $info,
        'route' => $afile,
        'time_label' => _TIME.':',
        'time_value' => (string)$time,
    ]);
    $tabtwo = '';
    $bip = explode('||', $conf['security']['blocker_user']);
    if ($conf['security']['blocker_user']) {
        $rows = '';
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val);
                $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-security-ban-user-row', [
                    'actions_html' => adminMenuItems([adminDeleteAction($afile.'.php?name=security&amp;op=bansave&amp;name='.$binfo[0].'&amp;time='.$binfo[1].'&amp;id=3', _DELETE.' "'.$binfo[0].'"?', _ONDELETE, _ONDELETE)]),
                    'date_text' => getTimeLeft($binfo[1]),
                    'info_text' => $binfo[2],
                    'name_html' => user_info($binfo[0]),
                ]));
            }
        }
        $tabtwo .= getAdminTable($tpl->getHtmlFrag('admin-security-ban-user-head', [
            'date_label' => _DATE,
            'functions_label' => _FUNCTIONS,
            'info_label' => _BANN_REAS,
            'name_label' => _NICKNAME,
        ]), $rows, 'sl_table_list_sort');
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
        'name_html' => get_user_search('name', $name, '25', 'sl_form', '1'),
        'name_label' => _NICKNAME.':',
        'route' => $afile,
        'time_label' => _TIME.':',
        'time_value' => (string)$time,
    ]);
    $banv = $tpl->getHtmlFrag('admin-security-ban-tabs', [
        'tab_one_html' => $tabone,
        'tab_two_html' => $tabtwo,
    ]);
    $banv .= $tpl->getHtmlFrag('admin-uploads-tabs-script', ['group_id' => 'securitys']);
    echo $cont.getAdminBox($banv);
    setFoot();
}

function bansave(): void {
    global $db, $conf, $afile;
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
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 2, 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= (!$conf['security']['login'] || !$conf['security']['password']) ? $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _SEC_AUTH_INFO]) : $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _SEC_AUTH_OK]);
    $hide = $tpl->getHtmlFrag('admin-security-pass-hidden-op');
    if (!$conf['security']['login'] || !$conf['security']['password']) {
        $credhtml = $tpl->getHtmlFrag('admin-security-pass-credential-rows', [
            'login_label' => _SEC_LOGIN.':',
            'login_placeholder' => _SEC_LOGIN,
            'pass_label' => _SEC_PASSWORD.':',
            'pass_placeholder' => _SEC_PASSWORD,
        ]);
    } else {
        $hide .= $tpl->getHtmlFrag('admin-security-pass-hidden-auth');
        $credhtml = '';
    }
    $rows = $tpl->getHtmlFrag('admin-security-pass-rows', [
        'adminip_hint' => _IP_CIDR_TIP,
        'adminip_label' => _SEC_ADMIN_IP.':',
        'adminip_placeholder' => _IP_CIDR_EX,
        'adminip_value' => $conf['security']['admin_ip'],
        'login_row_html' => $credhtml,
        'pass_row_html' => '',
        'save_label' => _SAVECHANGES,
    ]);
    $cont .= getAdminForm($afile.'.php?name=security', $rows, $hide);
    echo $cont;
    setFoot();
}

function passsave(): void {
    global $conf, $afile;
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
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 3, 'id' => 'security']);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $ainfo = sprintf(_ADMIN_FILE_INFO, strtolower(getPass('10')));
    $floodhtml = getAdminSelect('flood',
        getAdminOption('0', _NO, $conf['security']['flood'] == 0)
        .getAdminOption('1', _SFLOOD_1, $conf['security']['flood'] == 1)
        .getAdminOption('2', _SFLOOD_2, $conf['security']['flood'] == 2)
        .getAdminOption('3', _SFLOOD_3, $conf['security']['flood'] == 3),
        'sl_conf');
    $errorhtml = getAdminSelect('error',
        getAdminOption('0', _NO, $conf['security']['error'] == 0)
        .getAdminOption('1', _SEC_VIEW_1, $conf['security']['error'] == 1)
        .getAdminOption('2', _SEC_VIEW_2, $conf['security']['error'] == 2),
        'sl_conf');
    $confv = $tpl->getHtmlFrag('admin-security-config-form', [
        'afile_hint' => $ainfo,
        'afile_label' => _ADMIN_FILE.':',
        'afile_placeholder' => _ADMIN_FILE,
        'afile_value' => $conf['security']['afile'],
        'block_html' => radio_form($conf['security']['block'], 'block'),
        'block_label' => _SEC_WARN_BLOCK,
        'cookie_label' => _SEC_COOKIE.':',
        'cookie_placeholder' => _SEC_COOKIE,
        'cookie_value' => $conf['security']['blocker_cookie'],
        'dump_skip_hint' => _SEC_DUMP_SKIP_INFO,
        'dump_skip_label' => _SEC_DUMP_SKIP.':',
        'dump_skip_placeholder' => _SEC_DUMP_SKIP_INFO,
        'dump_skip_value' => htmlspecialchars((string)($conf['security']['dump_skip'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'error_html' => $errorhtml,
        'error_java_html' => radio_form($conf['security']['error_java'], 'error_java'),
        'error_java_label' => _SEC_VIEW_JAVA,
        'error_label' => _SEC_VIEW.':',
        'error_log_html' => radio_form($conf['security']['error_log'], 'error_log'),
        'error_log_label' => _SEC_STAT,
        'flood_html' => $floodhtml,
        'flood_label' => _SFLOOD.':',
        'flood_t_label' => _SFLOD_T.':',
        'flood_t_placeholder' => _SFLOD_T,
        'flood_t_value' => (string)$conf['security']['flood_t'],
        'log_a_html' => radio_form($conf['security']['log_a'], 'log_a'),
        'log_a_label' => _SEC_LOG_A,
        'log_b_html' => radio_form($conf['security']['log_b'], 'log_b'),
        'log_b_label' => _SEC_DB,
        'log_d_html' => radio_form($conf['security']['log_d'], 'log_d'),
        'log_d_label' => _SEC_LOG_D,
        'log_html' => radio_form($conf['security']['log'], 'log'),
        'log_label' => _SEC_LOG,
        'log_size_label' => _SEC_LOG_SIZE.':',
        'log_size_placeholder' => _SEC_LOG_SIZE,
        'log_size_value' => (string)$conf['security']['log_size'],
        'log_u_html' => radio_form($conf['security']['log_u'], 'log_u'),
        'log_u_label' => _SEC_LOG_U,
        'mail_d_html' => radio_form($conf['security']['mail_d'], 'mail_d'),
        'mail_d_label' => _SEC_MAIL_D_SEND,
        'mail_html' => radio_form($conf['security']['mail'], 'mail'),
        'mail_label' => _SEC_MAIL_SEND,
        'mail_w_html' => radio_form($conf['security']['mail_w'], 'mail_w'),
        'mail_w_label' => _SEC_MAIL_W_SEND,
        'ref_post_html' => radio_form($conf['security']['ref_post'], 'ref_post'),
        'ref_post_label' => _SEC_REF_POST,
        'route' => $afile,
        'save_label' => _SAVECHANGES,
        'sess_b_label' => _SEC_LOG_DB.':',
        'sess_b_placeholder' => _SEC_LOG_DB,
        'sess_b_value' => (string)intval($conf['security']['sess_b'] / 60),
        'sess_d_label' => _SEC_LOG_DS.':',
        'sess_d_placeholder' => _SEC_LOG_DS,
        'sess_d_value' => (string)intval($conf['security']['sess_d'] / 60),
        'url_get_html' => radio_form($conf['security']['url_get'], 'url_get'),
        'url_get_label' => _SEC_URL_GET,
        'url_post_html' => radio_form($conf['security']['url_post'], 'url_post'),
        'url_post_label' => _SEC_URL_POST,
        'write_h_html' => radio_form($conf['security']['write_h'], 'write_h'),
        'write_h_label' => _SEC_HACK_STAT,
        'write_w_html' => radio_form($conf['security']['write_w'], 'write_w'),
        'write_w_label' => _SEC_WARN_STAT,
    ]);
    echo $cont.getAdminBox($confv);
    setFoot();
}

function configsave(): void {
    global $conf, $afile;
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
    setHead();
    $cont = setAdminNavi(['ops' => ['name=security', 'name=security&amp;op=banlist', 'name=security&amp;op=passwd', 'name=security&amp;op=config', 'name=security&amp;op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_BANNED_IP, _BANNED_USERS], 'tab' => 4, 'id' => 'security']);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
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
    global $afile;
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
