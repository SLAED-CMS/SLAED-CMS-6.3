<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

$labels = [
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

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    $ops = ['name=security', 'name=security&amp;op=block', 'name=security&amp;op=pass', 'name=security&amp;op=conf', 'name=security&amp;op=info'];
    $lang = [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _INFO];
    $sops = ['', ''];
    $slang = [_BANNED_IP, _BANNED_USERS];
    return getAdminTabs('', $ops, $lang, $sops, $slang, $tab, $subtab, $legacy, $id);
}

function security(): void {
    global $afile, $labels;
    setHead();
    $cont = navi(0, 0, 0, 0, 'security');
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._TITLE.'</th><th>'._SIZE.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (preg_match('#(.*)\.log$#', $file)) {
            $name = (string)pathinfo($file, PATHINFO_FILENAME);
            $title = $labels[$name];
            $path = LOGS_DIR.'/'.$file;
            $filesize = filesize($path);
            $cont .= '<tr><td>'.title_tip(_FILE.': storage/logs/'.$file).$title.'</td>'
            .'<td>'.filterSize($filesize).'</td>'
            .'<td>'.date(_TIMESTRING, filemtime($path)).'</td>'
            .'<td>'.add_menu('<a href="'.$afile.'.php?name=security&amp;op=file&amp;file='.$name.'" title="'._INFO.'">'._INFO.'</a>||<a href="'.$afile.'.php?name=security&amp;op=down&amp;file='.$name.'" title="'._DOWN.'">'._DOWN.'</a>||<a href="'.$afile.'.php?name=security&amp;op=del&amp;file='.$name.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function fileview(): void {
    global $labels;
    setHead();
    $cont = navi(0, 0, 0, 0, 'security');
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $title = $labels[$file];
        $path = LOGS_DIR.'/'.$file.'.log';
        $content = (is_file($path) && is_readable($path)) ? file_get_contents($path) : false;
        if ($content === false) {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
            echo $cont;
            setFoot();
            return;
        }
        $cont .= checkPerms($path);
        $cont .= setTemplateBasic('open').'<table class="sl_table_edit"><tr><td><h5>'.$title.'</h5></td></tr><tr><td>'.textarea_code('code', '', 'sl_form', 'message/http', $content).'</td></tr></table>'.setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function block(): void {
    global $conf, $afile;
    $time = getVar('req', 'time', 'num');
    $info = getVar('req', 'info', 'text');
    $hash = getVar('req', 'hash', 'text');
    $cidr = getVar('req', 'cidr', 'text');
    setHead();
    $cont = navi(0, 1, 1, 0, 'security');
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    if (getVar('get', 'send', 'var')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
    $cont .= setTemplateBasic('open');
    $cont .= '<div id="tabcs0" class="tabcont">';
    $bip = explode('||', $conf['security']['blocker_ip']);
    if ($conf['security']['blocker_ip']) {
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._IP.'</th><th>'._IP_CIDR.'</th><th>'._HASH.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $tcidr = getIpCidr($binfo[0]);
                if ($tcidr === false) continue;
                [$tip, $tmask] = explode('/', $tcidr, 2);
                $l = '<a href="'.$afile.'.php?name=security&amp;op=blocksave&amp;cidr='.urlencode($tcidr).'&amp;hash='.urlencode($binfo[1]).'&amp;time='.(int)$binfo[2].'&amp;id=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$tcidr.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>';
                $cont .= '<tr><td>'.title_tip(_BANN_REAS.': '.$binfo[3]).user_geo_ip($tip, 4).'</td>'
                .'<td>/'.$tmask.'</td>'
                .'<td>'.$binfo[1].'</td>'
                .'<td>'.getTimeLeft((int)$binfo[2]).'</td>'
                .'<td>'.add_menu($l).'</td></tr>';
            }
        }
        $cont .= '</tbody></table><hr>';
    }
    $cont .= '<form action="'.$afile.'.php?name=security" method="post"><table class="sl_table_form">'
    .'<tr><td>'._IP_CIDR.':<div class="sl_small">'._IP_CIDR_TIP.'</div></td><td><textarea name="cidr" cols="65" rows="5" class="sl_form" placeholder="'._IP_CIDR_EX.'" required>'.$cidr.'</textarea></td></tr>'
    .'<tr><td>'._HASH.':</td><td><input type="text" name="hash" value="'.$hash.'" maxlength="255" class="sl_form" placeholder="'._HASH.'"></td></tr>'
    .'<tr><td>'._TIME.':</td><td><input type="number" name="time" value="'.$time.'" class="sl_form" placeholder="'._TIME.'" required></td></tr>'
    .'<tr><td>'._BANN_REAS.':</td><td><textarea name="info" cols="65" rows="5" class="sl_form" placeholder="'._BANN_REAS.'" required>'.$info.'</textarea></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="blocksave"><input type="hidden" name="id" value="2"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>'
    .'</div>';
    $cont .= '<div id="tabcs1" class="tabcont">';
    $bip = explode('||', $conf['security']['blocker_user']);
    if ($conf['security']['blocker_user']) {
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._NICKNAME.'</th><th>'._BANN_REAS.'</th><th>'._DATE.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val);
                $cont .= '<tr><td>'.user_info($binfo[0]).'</td>'
                .'<td>'.$binfo[2].'</td>'
                .'<td>'.getTimeLeft($binfo[1]).'</td>'
                .'<td>'.add_menu('<a href="'.$afile.'.php?name=security&amp;op=blocksave&amp;name='.$binfo[0].'&amp;time='.$binfo[1].'&amp;id=3" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$binfo[0].'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
            }
        }
        $cont .= '</tbody></table><hr>';
    }
    $name = getVar('get', 'name', 'name');
    $cookie = $conf['user_c'].'-close-security';
    $check = (getCookies('close-security') == '0') ? '' : ' checked';
    $cont .= '<form action="'.$afile.'.php?name=security" method="post"><table class="sl_table_form">'
    .'<tr><td>'._NICKNAME.':</td><td>'.get_user_search('name', $name, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TIME.':</td><td><input type="number" name="time" value="'.$time.'" class="sl_form" placeholder="'._TIME.'" required></td></tr>'
    .'<tr><td>'._BANN_REAS.':</td><td><textarea name="info" cols="65" rows="5" class="sl_form" placeholder="'._BANN_REAS.'" required>'.$info.'</textarea></td></tr>'
    .'<tr><td>'._MAIL_SENDE.'</td><td><input type="checkbox" name="mail" value="1" OnClick="CloseOpen(\''.$cookie.'\', 0);"'.$check.'></td></tr>'
    .'<tr><td colspan="2"><div id="'.$cookie.'" class="data" data-all=\'{"id": "'.$cookie.'"}\'><table class="sl_table_form"><tr><td>'._MAIL_TEXT.':<div class="sl_small">'._MAIL_INFO.'</div></td><td>'.textarea('1', 'mailtext', replace_break(str_replace('[text]', _BANN_INFO.PHP_EOL.PHP_EOL._BANN_TERM.': [time]'.PHP_EOL._BANN_REAS.': [info]', $conf['mtemp'])), 'all', '10').'</td></tr></table></div></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="blocksave"><input type="hidden" name="id" value="4"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent("securitys")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function blocksave(): void {
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
    setRedirect($afile.'.php?name=security&op=block'.$send);
}

function pass(): void {
    global $conf, $afile;
    setHead();
    $cont = navi(0, 2, 0, 0, 'security');
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= (!$conf['security']['login'] || !$conf['security']['password']) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SEC_AUTH_INFO]) : setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SEC_AUTH_OK]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php?name=security" method="post"><table class="sl_table_form">'
    .'<tr><td>'._SEC_ADMIN_IP.':<div class="sl_small">'._IP_CIDR_TIP.'</div></td><td><textarea name="admin_ip" cols="65" rows="5" class="sl_form" placeholder="'._IP_CIDR_EX.'">'.$conf['security']['admin_ip'].'</textarea></td></tr>';
    if (!$conf['security']['login'] || !$conf['security']['password']) {
        $cont .= '<tr><td>'._SEC_LOGIN.':</td><td><input type="text" name="login" value="" maxlength="255" class="sl_form" placeholder="'._SEC_LOGIN.'"></td></tr>'
        .'<tr><td>'._SEC_PASSWORD.':</td><td><input type="text" name="password" value="" maxlength="255" class="sl_form" placeholder="'._SEC_PASSWORD.'"></td></tr>';
    } else {
        $cont .= '<input type="hidden" name="login" value=""><input type="hidden" name="password" value="">';
    }
    $cont .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="passsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
    setRedirect($afile.'.php?name=security&op=pass');
}

function conf(): void {
    global $conf, $afile;
    setHead();
    $cont = navi(0, 3, 0, 0, 'security');
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $ainfo = sprintf(_ADMIN_FILE_INFO, strtolower(getPass('10')));
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php?name=security" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._SFLOOD.':</td><td><select name="flood" class="sl_conf">'
    .'<option value="0"'.(($conf['security']['flood'] == 0) ? ' selected' : '').'>'._NO.'</option>'
    .'<option value="1"'.(($conf['security']['flood'] == 1) ? ' selected' : '').'>'._SFLOOD_1.'</option>'
    .'<option value="2"'.(($conf['security']['flood'] == 2) ? ' selected' : '').'>'._SFLOOD_2.'</option>'
    .'<option value="3"'.(($conf['security']['flood'] == 3) ? ' selected' : '').'>'._SFLOOD_3.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._SEC_VIEW.':</td><td><select name="error" class="sl_conf">'
    .'<option value="0"'.(($conf['security']['error'] == 0) ? ' selected' : '').'>'._NO.'</option>'
    .'<option value="1"'.(($conf['security']['error'] == 1) ? ' selected' : '').'>'._SEC_VIEW_1.'</option>'
    .'<option value="2"'.(($conf['security']['error'] == 2) ? ' selected' : '').'>'._SEC_VIEW_2.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._SFLOD_T.':</td><td><input type="number" name="flood_t" value="'.$conf['security']['flood_t'].'" class="sl_conf" placeholder="'._SFLOD_T.'" required></td></tr>'
    .'<tr><td>'._SEC_COOKIE.':</td><td><input type="text" name="blocker_cookie" value="'.$conf['security']['blocker_cookie'].'" maxlength="255" class="sl_conf" placeholder="'._SEC_COOKIE.'" required></td></tr>'
    .'<tr><td>'._ADMIN_FILE.':<div class="sl_small">'.$ainfo.'</div></td><td><input type="text" name="afile" value="'.$conf['security']['afile'].'" maxlength="255" class="sl_conf" placeholder="'._ADMIN_FILE.'" required></td></tr>'
    .'<tr><td>'._SEC_LOG_SIZE.':</td><td><input type="number" name="log_size" value="'.$conf['security']['log_size'].'" class="sl_conf" placeholder="'._SEC_LOG_SIZE.'" required></td></tr>'
    .'<tr><td>'._SEC_LOG_DS.':</td><td><input type="number" name="sess_d" value="'.intval($conf['security']['sess_d'] / 60).'" class="sl_conf" placeholder="'._SEC_LOG_DS.'" required></td></tr>'
    .'<tr><td>'._SEC_LOG_DB.':</td><td><input type="number" name="sess_b" value="'.intval($conf['security']['sess_b'] / 60).'" class="sl_conf" placeholder="'._SEC_LOG_DB.'" required></td></tr>'
    .'<tr><td>'._SEC_DB.'</td><td>'.radio_form($conf['security']['log_b'], 'log_b').'</td></tr>'
    .'<tr><td>'._SEC_VIEW_JAVA.'</td><td>'.radio_form($conf['security']['error_java'], 'error_java').'</td></tr>'
    .'<tr><td>'._SEC_STAT.'</td><td>'.radio_form($conf['security']['error_log'], 'error_log').'</td></tr>'
    .'<tr><td>'._SEC_URL_GET.'</td><td>'.radio_form($conf['security']['url_get'], 'url_get').'</td></tr>'
    .'<tr><td>'._SEC_URL_POST.'</td><td>'.radio_form($conf['security']['url_post'], 'url_post').'</td></tr>'
    .'<tr><td>'._SEC_REF_POST.'</td><td>'.radio_form($conf['security']['ref_post'], 'ref_post').'</td></tr>'
    .'<tr><td>'._SEC_MAIL_SEND.'</td><td>'.radio_form($conf['security']['mail'], 'mail').'</td></tr>'
    .'<tr><td>'._SEC_MAIL_W_SEND.'</td><td>'.radio_form($conf['security']['mail_w'], 'mail_w').'</td></tr>'
    .'<tr><td>'._SEC_MAIL_D_SEND.'</td><td>'.radio_form($conf['security']['mail_d'], 'mail_d').'</td></tr>'
    .'<tr><td>'._SEC_HACK_STAT.'</td><td>'.radio_form($conf['security']['write_h'], 'write_h').'</td></tr>'
    .'<tr><td>'._SEC_WARN_STAT.'</td><td>'.radio_form($conf['security']['write_w'], 'write_w').'</td></tr>'
    .'<tr><td>'._SEC_LOG.'</td><td>'.radio_form($conf['security']['log'], 'log').'</td></tr>'
    .'<tr><td>'._SEC_LOG_D.'</td><td>'.radio_form($conf['security']['log_d'], 'log_d').'</td></tr>'
    .'<tr><td>'._SEC_DUMP_SKIP.':<div class="sl_small">'._SEC_DUMP_SKIP_INFO.'</div></td><td><textarea name="dump_skip" cols="65" rows="8" class="sl_conf" placeholder="'._SEC_DUMP_SKIP_INFO.'">'.htmlspecialchars((string)($conf['security']['dump_skip'] ?? ''), ENT_QUOTES, 'UTF-8').'</textarea></td></tr>'
    .'<tr><td>'._SEC_LOG_A.'</td><td>'.radio_form($conf['security']['log_a'], 'log_a').'</td></tr>'
    .'<tr><td>'._SEC_LOG_U.'</td><td>'.radio_form($conf['security']['log_u'], 'log_u').'</td></tr>'
    .'<tr><td>'._SEC_WARN_BLOCK.'</td><td>'.radio_form($conf['security']['block'], 'block').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function confsave(): void {
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
    setRedirect($afile.'.php?name=security&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 4, 0, 0, 'security').'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

function down(): void {
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

function del(): void {
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
    case 'file': fileview(); break;
    case 'down': down(); break;
    case 'del': del(); break;
    case 'block': block(); break;
    case 'blocksave': blocksave(); break;
    case 'pass': pass(); break;
    case 'passsave': passsave(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
