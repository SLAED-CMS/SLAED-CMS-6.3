<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}
get_lang($conf['name']);

function account(): void {
    global $conf, $stop;
    if (is_user()) {
        profil();
    } else{
        setHead(['title' => _USERREGLOGIN]);
        $captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
        $cont = setTemplateBasic('title', ['{%title%}' => _USERREGLOGIN]);
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._NICKNAME.':</td><td><input type="text" name="user_name" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._NICKNAME.'" required></td></tr>'
        .'<tr><td>'._PASSWORD.':</td><td><input type="password" name="user_password" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._PASSWORD.'" required></td></tr>'
        .'<tr><td colspan="2" class="sl_center">'.$captcha.'<input type="hidden" name="op" value="login"><input type="submit" value="'._USERLOGIN.'" class="sl_but_blue"></td></tr>'
        .'<tr><td colspan="2" class="sl_center"><a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']).'" title="'._PASSWORDLOST.'" class="sl_but_foot">'._PASSWORDLOST.'</a><a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'newuser']).'" title="'._REGNEWUSER.'" class="sl_but_foot">'._REGNEWUSER.'</a></td></tr>';
        $cont .= ($conf['users']['network']) ? '<tr><td colspan="2" class="sl_center">'._LOGINNETWORK.'</td></tr><tr><td colspan="2" class="sl_center">'.getNetworks().'</td></tr>' : '';
        $cont .= '</table></form>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    }
}

function checkuser($user_name, $user_email, $rules) {
    global $db, $conf, $stop;
    if ($conf['users']['rule'] && $rules != '1') $stop[] = _ERROR_RULES;
    checkemail($user_email);
    $mail_b = explode(',', $conf['users']['mail_b']);
    foreach ($mail_b as $val) if ($val != '' && $val == strtolower($user_email)) $stop[] = _MAIL_BLOCK;
    $name_b = explode(',', $conf['users']['name_b']);
    foreach ($name_b as $val) if ($val != '' && $val == strtolower($user_name)) $stop[] = _NAME_BLOCK;
    if (!$user_name || !analyze_name($user_name)) $stop[] = _ERRORINVNICK;
    if (strlen($user_name) > 25) $stop[] = _NICKLONG;
    if ($db->sql_numrows($db->sql_query('SELECT user_name FROM '.PREFIX_DB.'_users WHERE user_name = :user_name', ['user_name' => $user_name])) > 0) $stop[] = _NICKTAKEN;
    if ($db->sql_numrows($db->sql_query('SELECT user_name FROM '.PREFIX_DB.'_users_temp WHERE user_name = :user_name', ['user_name' => $user_name])) > 0) $stop[] = _NICKTAKEN;
    if ($db->sql_numrows($db->sql_query('SELECT user_email FROM '.PREFIX_DB.'_users WHERE user_email = :user_email', ['user_email' => $user_email])) > 0) $stop[] = _ERROR_EMAIL;
    if ($db->sql_numrows($db->sql_query('SELECT user_email FROM '.PREFIX_DB.'_users_temp WHERE user_email = :user_email', ['user_email' => $user_email])) > 0) $stop[] = _ERROR_EMAIL;
    return($stop);
}

function newuser(): void {
    global $conf, $stop;
    if (is_user()) {
        profil();
    } else {
        setHead(['title' => _REGNEWUSER]);
        if ($stop) {
            $cont = setTemplateBasic('title', ['{%title%}' => _NEWUSERERROR]);
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        } else {
            $cont = setTemplateBasic('title', ['{%title%}' => _REGNEWUSER]);
        }
        if (!$conf['users']['reg']) {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOREG]);
        } else {
            $unkey = md5_salt($conf['sitekey']);
            $user_name = getVar('post', $unkey, 'text');
            $user_name = ($user_name) ? text_filter(substr($user_name, 0, 25)) : '';
            $user_email = getVar('post', 'user_email', 'text');
            $user_email = ($user_email) ? text_filter($user_email) : '';
            $captcha = ($conf['gfx_chk'] == 3 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
            $cont .= setTemplateBasic('open');
            $cont .= '<form action="index.php?name='.$conf['name'].'" method="post">'
            .'<table class="sl_table_form">'
            .'<tr><td>'._NICKNAME.':</td><td><input type="text" name="'.$unkey.'" value="'.$user_name.'" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._NICKNAME.'" required></td></tr>'
            .'<tr><td>'._EMAIL.':</td><td><input type="email" name="user_email" value="'.$user_email.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._EMAIL.'" required></td></tr>'
            .'<tr><td>'.title_tip(_BLANKFORAUTO)._PASSWORD.':</td><td><input type="password" name="user_password" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._PASSWORD.'"></td></tr>'
            .'<tr><td>'.title_tip(_BLANKFORAUTO)._RETYPEPASSWORD.':</td><td><input type="password" name="user_password2" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._RETYPEPASSWORD.'"></td></tr>';
            if ($conf['users']['rule']) {
                $cont .= '<tr><td>'._RULES.':</td><td><textarea cols="50" rows="10" class="sl_field '.$conf['style'].'">'.$conf['users']['rules'].'</textarea></td></tr>'
                .'<tr><td>'._RULES_OK.'</td><td><input type="checkbox" name="rules" value="1" class="sl_field '.$conf['style'].'" required></td></tr>';
            }
            $cont .= '<tr><td colspan="2" class="sl_center">'.$captcha.'<input type="hidden" name="op" value="finnewuser"><input type="submit" value="'._NEWUSER.'" class="sl_but_blue"></td></tr>'
            .'<tr><td colspan="2" class="sl_center"><a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._USERLOGIN.'" class="sl_but_foot">'._USERLOGIN.'</a><a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']).'" title="'._PASSWORDLOST.'" class="sl_but_foot">'._PASSWORDLOST.'</a></td></tr>';
            $cont .= ($conf['users']['network']) ? '<tr><td colspan="2" class="sl_center">'._LOGINNETWORK.'</td></tr><tr><td colspan="2" class="sl_center">'.getNetworks().'</td></tr>' : '';
            $cont .= '</table></form>';
            $cont .= setTemplateBasic('close');
        }
        echo $cont;
        setFoot();
    }
}

function finnewuser(): void {
    global $db, $conf, $stop;
    if (!$conf['users']['reg']) {
        setHead(['title' => _NOREG]);
        echo setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOREG]);
        setFoot();
    } else {
        $unkey = md5_salt($conf['sitekey']);
        $user_name = text_filter(getVar('post', $unkey, 'text'), 1);
        $user_email = text_filter(getVar('post', 'user_email', 'text'), 1);
        $rules = getVar('post', 'rules', 'num');
        checkuser($user_name, $user_email, $rules);
        $user_password = htmlspecialchars(substr(getVar('post', 'user_password', 'text'), 0, 40));
        $user_password2 = htmlspecialchars(substr(getVar('post', 'user_password2', 'text'), 0, 40));
        if (($conf['gfx_chk'] == 3 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop[] = _SECCODEINCOR;
        if ($user_password == '' && $user_password2 == '') {
            $user_password = getPass($conf['users']['minpass']);
        } elseif ($user_password != $user_password2) {
            $stop[] = _ERROR_PASS;
        } elseif ($user_password == $user_password2 && strlen($user_password) < $conf['users']['minpass']) {
            $stop[] = _CHARMIN.': '.$conf['users']['minpass'];
        }
        if (!$stop) {
            $check_num = md5(getPass(10));
            $time = time();
            $finishlink = $conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=activate&amp;user='.urlencode($user_name).'&amp;num='.$check_num;
            $user_name = text_filter($user_name);
            $user_email = text_filter($user_email);
            $db->sql_query(
                'INSERT INTO '.PREFIX_DB.'_users_temp (user_id, user_name, user_email, user_password, user_regdate, check_num, time) VALUES (NULL, :user_name, :user_email, :user_password, NOW(), :check_num, :time)',
                ['user_name' => $user_name, 'user_email' => $user_email, 'user_password' => $user_password, 'check_num' => $check_num, 'time' => $time]
            );
            setHead(['title' => _ACCOUNTCREATED]);
            if ($conf['users']['nomail'] == 1) {
                $cont = setTemplateBasic('title', ['{%title%}' => _ACCOUNTCREATED]);
                $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _TOFINISHUSERN]);
                $cont .= setTemplateBasic('open');
                $cont .= '<form action="index.php" method="get">'
                .'<table class="sl_table_form">'
                .'<tr><td>'._UNICKNAME.':</td><td>'.$user_name.'</td></tr>'
                .'<tr><td>'._UPASSWORD.':</td><td>'.$user_password.'</td></tr>'
                .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="'.$conf['name'].'"><input type="hidden" name="op" value="activate"><input type="hidden" name="user" value="'.urlencode($user_name).'"><input type="hidden" name="num" value="'.$check_num.'"><input type="submit" value="'._ACTIVATIONSUB.'" class="sl_but_blue"></td></tr></table></form>';
                $cont .= setTemplateBasic('close');
            } else {
                $link = '<a href="'.$finishlink.'" target="_blank" title="'._ACTIVATIONSUB.'">'.str_replace('&amp;', '&', $finishlink).'</a>';
                $subject = $conf['sitename'].' - '._ACTIVATIONSUB;
                $message = str_replace('[text]', sprintf(_PASSFSEND, $user_email, $conf['sitename'], $link, $user_name, $user_password).'<br><br>'._IFYOUDIDNOTASK, $conf['mtemp']);
                mail_send($user_email, $conf['adminmail'], $subject, $message, 0, 3);
                $cont = setTemplateBasic('title', ['{%title%}' => _ACCOUNTCREATED]).setTemplateWarning('warn', ['time' => '30', 'url' => '', 'id' => 'info', 'text' => _YOUAREREGISTERED.'<br><br>'._FINISHUSERCONF.'<br><br>'._THANKSUSER]);
            }
            echo $cont;
            setFoot();
        } else {
            newuser();
        }
    }
}

function network(): void {
    global $db, $conf;
    $conf['users']['network'] = 1;
    $token = getVar('post', 'token', 'text');
    if ($conf['users']['network'] && $token) {
        $s = file_get_contents('http://ulogin.ru/token.php?token='.$token.'&host='.$_SERVER['HTTP_HOST']);
        $ulog = json_decode($s, true);
        if (empty($ulog['error']) && isArray($ulog)) {
            $nickname = isset($ulog['nickname']) ? ucfirst(getTranslit($ulog['nickname'], 1)) : '';
            $first_name = isset($ulog['first_name']) ? ucfirst(getTranslit($ulog['first_name'], 1)) : '';
            $last_name = isset($ulog['last_name']) ? ucfirst(getTranslit($ulog['last_name'], 1)) : '';
            $variants = [];
            $variants[] = substr($first_name, 0, 25);
            if (!empty($nickname)) {
                $variants[] = substr($nickname, 0, 25);
                $variants[] = substr($nickname.'-'.$first_name, 0, 25);
            }
            if (!empty($last_name)) {
                $variants[] = substr($last_name, 0, 25);
                $variants[] = substr($first_name.'-'.$last_name, 0, 25);
            }
            $variants[] = substr($first_name, 0, 20).'-'.date('Y');
            $variants[] = substr($first_name, 0, 22).'-'.rand(1, 99);
            $variants[] = substr($first_name, 0, 20).'-'.getPass(4);
            foreach ($variants as $var) {
                if ($db->sql_numrows($db->sql_query('SELECT user_name FROM '.PREFIX_DB.'_users WHERE user_name = :user_name', ['user_name' => $var])) == 0) {
                    $uname = $var;
                    break;
                }
            }
            $upass = md5_salt(trim($ulog['identity']));
            $uip = getIp();
            $uagent = getAgent();
            $result = $db->sql_query('SELECT user_id, user_name, user_password, user_storynum, user_blockon, user_theme FROM '.PREFIX_DB.'_users WHERE user_password = :user_password', ['user_password' => $upass]);
            [$user_id, $user_name, $user_password, $user_storynum, $user_blockon, $user_theme] = $db->sql_fetchrow($result);
            if ($db->sql_numrows($result) == 1) {
                setCookies('account', time() + intval($conf['user_c_t']), [$user_id, $user_name, $user_password, $user_storynum, $user_blockon, $user_theme]);
                $db->sql_query('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
                $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_last_ip = :user_last_ip, user_lastvisit = NOW(), user_agent = :user_agent WHERE user_id = :user_id', ['user_last_ip' => $uip, 'user_agent' => $uagent, 'user_id' => $user_id]);
                login_report(0, 1, $user_name, '');
                setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
            } else {
                $uemail = isset($ulog['email']) ? mb_strtolower($ulog['email']) : '';
                $network = isset($ulog['profile']) ? $ulog['profile'] : $ulog['network'];
                $db->sql_query('INSERT INTO '.PREFIX_DB.'_users (user_id, user_name, user_email, user_avatar, user_regdate, user_password, user_last_ip, user_agent, user_network, user_block, user_warnings, user_field) VALUES (NULL, :user_name, :user_email, :user_avatar, NOW(), :user_password, :user_last_ip, :user_agent, :user_network, :user_block, :user_warnings, :user_field)', ['user_name' => $uname, 'user_email' => $uemail, 'user_avatar' => 'default/00.gif', 'user_password' => $upass, 'user_last_ip' => $uip, 'user_agent' => $uagent, 'user_network' => $network, 'user_block' => '', 'user_warnings' => '', 'user_field' => '']);
                [$user_id, $user_name, $user_password, $user_storynum, $user_blockon, $user_theme] = $db->sql_fetchrow($db->sql_query('SELECT user_id, user_name, user_password, user_storynum, user_blockon, user_theme FROM '.PREFIX_DB.'_users WHERE user_password = :user_password', ['user_password' => $upass]));
                setCookies('account', time() + intval($conf['user_c_t']), [$user_id, $user_name, $user_password, $user_storynum, $user_blockon, $user_theme]);
                $db->sql_query('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
                $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_lastvisit = NOW() WHERE user_id = :user_id', ['user_id' => $user_id]);
                $uphoto = isset($ulog['photo']) ? $ulog['photo'] : '';
                if ($uphoto) {
                    $anetwork = isset($ulog['network']) ? substr(getTranslit($ulog['network'], 1), 0, 25) : 'network';
                    $uavatar = upload(4, $conf['users']['adirectory'], $conf['users']['atypefile'], '104857600', $anetwork, '1600', '1600', $user_id, $uphoto);
                    $afile = $conf['users']['adirectory'].'/'.$uavatar;
                    if (file_exists($afile)) {
                        [$awidth] = getimagesize($afile);
                        if ($awidth > $conf['users']['awidth']) create_img_gd($afile, $afile, $conf['users']['awidth']);
                        $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_avatar = :user_avatar WHERE user_id = :user_id', ['user_avatar' => $uavatar, 'user_id' => $user_id]);
                    }
                }
                login_report(0, 1, $user_name, '');
                setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
            }
        } else {
            setHead(['title' => _ERRORINPUT]);
            echo setTemplateBasic('title', ['{%title%}' => _ERRORINPUT]).setTemplateWarning('warn', ['time' => '15', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => _ERRORSESS]);
            setFoot();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function activate(): void {
    global $db, $conf, $locale;
    $uname = getVar('get', 'user', 'name', '');
    $cnum  = getVar('get', 'num', 'text', '');
    $past = time() - 86400;
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_users_temp WHERE time < :past', ['past' => $past]);
    $result = $db->sql_query('SELECT user_name, user_email, user_password, user_regdate, check_num FROM '.PREFIX_DB.'_users_temp WHERE user_name = :uname AND check_num = :cnum', ['uname' => $uname, 'cnum' => $cnum]);
    setHead(['title' => _ACTIVATIONSUB]);
    if ($db->sql_numrows($result) === 1) {
        [$user_name, $user_email, $user_password, $user_regdate, $check_num] = $db->sql_fetchrow($result);
        if ($cnum == $check_num) {
            $uip = getIp();
            $uagent = getAgent();
            $rank = '';
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_users (user_id, user_name, user_rank, user_email, user_avatar, user_regdate, user_password, user_lang, user_last_ip, user_agent, user_network, user_block, user_warnings, user_field) VALUES (NULL, :uname, :rank, :email, :avatar, :regdate, :pwd, :lang, :ip, :agent, :network, :block, :warnings, :field)', ['uname' => $user_name, 'rank' => $rank, 'email' => $user_email, 'avatar' => 'default/00.gif', 'regdate' => $user_regdate, 'pwd' => md5_salt($user_password), 'lang' => $locale, 'ip' => $uip, 'agent' => $uagent, 'network' => '', 'block' => '', 'warnings' => '', 'field' => '']);
            $db->sql_query('DELETE FROM '.PREFIX_DB.'_users_temp WHERE user_name = :uname AND check_num = :cnum', ['uname' => $user_name, 'cnum' => $check_num]);
            $db->sql_query('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = 0', ['uname' => $uip]);
            echo setTemplateBasic('title', ['{%title%}' => _ACTIVATIONYES]).setTemplateWarning('warn', ['time' => '15', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _ACTMSG]);
        } else {
            echo setTemplateBasic('title', ['{%title%}' => _ACTIVATIONERROR]).setTemplateWarning('warn', ['time' => '15', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => _ACTERROR1]);
        }
    } else {
        echo setTemplateBasic('title', ['{%title%}' => _ACTIVATIONERROR]).setTemplateWarning('warn', ['time' => '15', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => _ACTERROR2]);
    }
    setFoot();
}

function view(): void {
    global $db, $conf, $afile;
    if ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || is_admin()) {
        $uname = htmlspecialchars(substr(urldecode(getVar('get', 'uname', 'text')), 0, 25));
        $params = [];
        if ($uname) {
            $where = 'BINARY u.user_name = :uname';
            $params['uname'] = $uname;
        } else {
            $where = 'u.user_id = :uid';
            $params['uid'] = getVar('get', 'id', 'num');
        }
        $result = $db->sql_query('SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_occ, u.user_from, u.user_interests, u.user_sig, u.user_viewemail, u.user_lastvisit, u.user_lang, u.user_points, u.user_last_ip, u.user_warnings, u.user_birthday, u.user_gender, u.user_votes, u.user_totalvotes, u.user_field, u.user_agent, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.user_group) WHERE '.$where, $params);
        if ($db->sql_numrows($result) > 0) {
            [$user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_occ, $user_from, $user_interests, $user_sig, $user_viewemail, $user_lastvisit, $user_lang, $user_points, $user_last_ip, $user_warnings, $user_birthday, $user_gender, $user_votes, $user_totalvotes, $user_field, $user_agent, $gname, $grank, $gcolor] = $db->sql_fetchrow($result);
            $seotitle  = $user_name;
            $seoctitle = _PERSONALINFO;
            $seodesc   = cutstr(trim(strip_tags(bb_decode($user_sig, $conf['name']))), 160);
            $seoimg    = ($user_avatar && file_exists($conf['users']['adirectory'].'/'.$user_avatar)) ? $conf['homeurl'].'/'.$conf['users']['adirectory'].'/'.$user_avatar : '';
            $seotime   = $user_lastvisit;
            $seoauthor = $user_name ?: ($uname ?: $conf['sitename']);
            setHead([
                'title' => $seotitle,
                'ctitle' => $seoctitle,
                'desc' => $seodesc,
                'img' => $seoimg,
                'time' => $seotime,
                'author' => $seoauthor,
            ]);
            if (is_admin()) {
                $id = [_ID, $user_id];
                $regdate = [_REG, format_time($user_regdate, _TIMESTRING)];
                $lastvisit = [_LAST_VISIT, format_time($user_lastvisit, _TIMESTRING)];
                $ip = [_IP, user_geo_ip($user_last_ip, 4)];
                $agent = [_BROWSER, $user_agent];
            } else {
                $id = [_ID, _HIDE];
                $regdate = [_REG, format_time($user_regdate)];
                $lastvisit = [_LAST_VISIT, format_time($user_lastvisit)];
                $ip = [_COUNTRY, user_geo_ip($user_last_ip, 2)];
                $agent = [_BROWSER, _HIDE];
            }
            $name = [_NICKNAME, $user_name];
            $urank = ($user_rank) ? [_URANK, $user_rank] : [_URANK, ''];
            $mail = ((is_admin() || $user_viewemail) && $user_email) ? [_EMAIL, anti_spam($user_email)] : [_EMAIL, _HIDE];
            $site = ($user_website) ? ((is_admin() || is_user()) ? [_SITEURL, domain($user_website)] : [_SITEURL, _HIDE]) : [_SITEURL, _NO_INFO];
            $avatar = ($user_avatar && file_exists($conf['users']['adirectory'].'/'.$user_avatar)) ? $conf['users']['adirectory'].'/'.$user_avatar : $conf['users']['adirectory'].'/default/00.gif';
            $occup = ($user_occ) ? [_OCCUPATION, $user_occ] : [_OCCUPATION, _NO_INFO];
            $from = ($user_from) ? [_LOCALITYLANG, $user_from] : [_LOCALITYLANG, _NO_INFO];
            $inter = ($user_interests) ? [_INTERESTS, $user_interests] : [_INTERESTS, _NO_INFO];
            $sign = ((is_admin() || is_user()) && $user_sig) ? '<hr>'.bb_decode($user_sig, $conf['name']) : '';
            $lang = ($user_lang) ? [_LANGUAGE, deflang($user_lang)] : [_LANGUAGE, deflang($conf['language'])];
            $points = ($conf['users']['point'] && $user_points) ? [_POINTS, $user_points] : [_POINTS, _NO_INFO];
            $warn = [_UWARNS, warnings($user_warnings)];
            if ($user_birthday) {
                preg_match('#([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})#', $user_birthday, $datetime);
                $birth = [_BIRTHDAY, $datetime[3].'.'.$datetime[2].'.'.$datetime[1]];
            } else {
                $birth = [_BIRTHDAY, _NO_INFO];
            }
            $gender = [_GENDER, gender($user_gender)];
            $rating = [_RATING, ajax_rating(1, $user_id, $conf['name'], $user_votes, $user_totalvotes, '', 1)];
            $field = ($user_field) ? fields_out($user_field, $conf['name']) : '';
            $sgroup = ($gname) ? [_SPEC_GROUP, '<span style="color: '.$gcolor.'">'.$gname.'</span>'] : [_SPEC_GROUP, _NO];
            $rgroup = [];
            $uranks = '';
            if ($conf['users']['point'] && $user_points) {
                $result = $db->sql_query('SELECT name, rank, color FROM '.PREFIX_DB."_groups WHERE points <= :points AND extra != '1' ORDER BY points ASC", ['points' => intval($user_points)]);
                $group = [];
                while([$guname, $gurank, $gcolor] = $db->sql_fetchrow($result)) {
                    $group[] = '<span style="color: '.$gcolor.'">'.$guname.'</span>';
                    $rgroup[] = $guname;
                    $uranks = $gurank;
                }
                $group = (is_array($group)) ? implode(', ', $group) : _NO_INFO;
                $groups = [_USER_GROUPS, $group];
                $grank = ($grank) ? $grank : $uranks;
            } else {
                $groups = [_USER_GROUPS, _NO];
            }
            $trank = ($gname) ? _GROUP.': '.$gname : ((is_array($rgroup)) ? _USER_GROUPS.': '.implode(', ', $rgroup) : _RANK);
            $rank = ($grank && file_exists(img_find('ranks/'.$grank))) ? [_RANK, '<img src="'.img_find('ranks/'.$grank).'" alt="'.$trank.'" title="'.$trank.'">'] : ['', ''];
            $admin = (is_admin()) ? add_menu('<a href="'.$afile.'.php?op=users_add&amp;id='.$user_id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=security_block&amp;new_ip='.$user_last_ip.'" OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.$user_last_ip.'&quot;?\');" title="'._BANIPSENDER.'">'._BANIPSENDER.'</a>||<a href="'.$afile.'.php?op=security_block&amp;new_ip='.$user_last_ip.'" OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.$user_last_ip.'&quot;?\');" title="'._BANIPSENDER.'">'._BANIPSENDER.'</a>||<a href="'.$afile.'.php?op=users_del&amp;id='.$user_id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$user_name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $privat = (($conf['privat']['act'] ?? 0) && $user_name) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'privat', 'uname' => urlencode($user_name)]).'" title="'._SENDMES.'" class="sl_but_green">'._MESSAGE.'</a>' : '';
            $profil = (is_user() && $uname == $user_name) ? '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._ACCOUNT.'" class="sl_but">'._ACCOUNT.'</a>' : '';
            $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
            $title[] = _COMMENTS;
            $text[] = last($user_id, 'comm');
            if (is_active('faq')) {
                $title[] = _FAQ;
                $text[] = last($user_id, 'faq');
            }
            if (is_active('files')) {
                $title[] = _FILES;
                $text[] = last($user_id, 'files');
            }
            if (is_active('forum')) {
                $title[] = _FORUM;
                $text[] = last($user_id, 'forum');
            }
            if (is_active('jokes')) {
                $title[] = _JOKES;
                $text[] = last($user_id, 'jokes');
            }
            if (is_active('links')) {
                $title[] = _LINKS;
                $text[] = last($user_id, 'links');
            }
            if (is_active('media')) {
                $title[] = _MEDIA;
                $text[] = last($user_id, 'media');
            }
            if (is_active('news')) {
                $title[] = _NEWS;
                $text[] = last($user_id, 'news');
            }
            if (is_active('pages')) {
                $title[] = _PAGES;
                $text[] = last($user_id, 'pages');
            }
            $tabs = getNaviTabs(0, 'tab', $title, $text);
            echo setTemplateBasic('account-view', ['if_flag' => ['is_view' => true], '{%cid%}' => $id[0], '{%id%}' => $id[1], '{%cname%}' => $name[0], '{%name%}' => $name[1], '{%curank%}' => $urank[0], '{%urank%}' => $urank[1], '{%cmail%}' => $mail[0], '{%mail%}' => $mail[1], '{%csite%}' => $site[0], '{%site%}' => $site[1], '{%avatar%}' => $avatar, '{%cregdate%}' => $regdate[0], '{%regdate%}' => $regdate[1], '{%coccup%}' => $occup[0], '{%occup%}' => $occup[1], '{%cfrom%}' => $from[0], '{%from%}' => $from[1], '{%cinter%}' => $inter[0], '{%inter%}' => $inter[1], '{%sign%}' => $sign, '{%clastvisit%}' => $lastvisit[0], '{%lastvisit%}' => $lastvisit[1], '{%clang%}' => $lang[0], '{%lang%}' => $lang[1], '{%cpoints%}' => $points[0], '{%points%}' => $points[1], '{%cip%}' => $ip[0], '{%ip%}' => $ip[1], '{%cwarn%}' => $warn[0], '{%warn%}' => $warn[1], '{%cbirth%}' => $birth[0], '{%birth%}' => $birth[1], '{%cgender%}' => $gender[0], '{%gender%}' => $gender[1], '{%crating%}' => $rating[0], '{%rating%}' => $rating[1], '{%field%}' => $field, '{%cagent%}' => $agent[0], '{%agent%}' => $agent[1], '{%csgroup%}' => $sgroup[0], '{%sgroup%}' => $sgroup[1], '{%cgroups%}' => $groups[0], '{%groups%}' => $groups[1], '{%crank%}' => $rank[0], '{%rank%}' => $rank[1], '{%admin%}' => $admin, '{%privat%}' => $privat, '{%profil%}' => $profil, '{%goback%}' => $goback, '{%tabs%}' => $tabs, '{%info%}' => _PERSONALINFO]);
            setFoot();
        } else {
            setHead(['title' => _USERNOEXIST]);
            echo setTemplateWarning('warn', ['time' => '3', 'url' => '', 'id' => 'info', 'text' => _USERNOEXIST]);
            setFoot();
        }
    } else {
        setHead(['title' => _MODULEUSERS]);
        echo setTemplateWarning('warn', ['time' => '15', 'url' => '', 'id' => 'info', 'text' => _MODULEUSERS]);
        setFoot();
    }
}

function profil(): void {
    global $conf, $user;
    if (is_user()) {
        setHead(['title' => _THISISYOURPAGE]);
        $cont = setTemplateBasic('title', ['{%title%}' => _THISISYOURPAGE]);
        $cont .= navi();
        $title[] = _COMMENTS;
        $text[] = last($user[0], 'comm');
        if (is_active('faq')) {
            $title[] = _FAQ;
            $text[] = last($user[0], 'faq');
        }
        if (is_active('files')) {
            $title[] = _FILES;
            $text[] = last($user[0], 'files');
        }
        if (is_active('forum')) {
            $title[] = _FORUM;
            $text[] = last($user[0], 'forum');
        }
        if (is_active('jokes')) {
            $title[] = _JOKES;
            $text[] = last($user[0], 'jokes');
        }
        if (is_active('links')) {
            $title[] = _LINKS;
            $text[] = last($user[0], 'links');
        }
        if (is_active('media')) {
            $title[] = _MEDIA;
            $text[] = last($user[0], 'media');
        }
        if (is_active('news')) {
            $title[] = _NEWS;
            $text[] = last($user[0], 'news');
        }
        if (is_active('pages')) {
            $title[] = _PAGES;
            $text[] = last($user[0], 'pages');
        }
        if (($conf['rss']['use'] ?? 0) == 1) {
            $url = getVar('post', 'url', 'url');
            $link = ($url) ? $url : 'http://';
            $title[] = _RSS;
            $text[] = '<form action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form"><tr><td>'._SELECTASITE.':</td><td><select name="url" class="sl_field '.$conf['style'].'">'.rss_select().'</select></td><td><input type="submit" value="'._OK.'" class="sl_but_blue"></td></tr></table></form>'
            .'<form action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form"><tr><td>'._ORTYPEURL.':</td><td><input type="url" name="url" value="'.$link.'" maxlength="200" class="sl_field '.$conf['style'].'" placeholder="'._ORTYPEURL.'"></td><td><input type="submit" value="'._OK.'" class="sl_but_blue"></td></tr></table></form>'
            .rss_read($url, '');
        }
        $cont .= getNaviTabs(0, 'tab', $title, $text);
        echo $cont;
        setFoot();
    } else {
        account();
    }
}

function last(int|string $uid, string $modul): string {
    global $db, $conf, $user;
    $user_id = intval($uid);
    $num = user_news($user[3] ?? 0, 25);
    $limit = intval($num);
    $cont = '';
    if ($modul == 'comm') {
        $result = $db->sql_query('SELECT id, cid, modul, date, comment FROM '.PREFIX_DB."_comment WHERE uid = :user_id AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $cid, $modul, $date, $comment] = $db->sql_fetchrow($result)) {
                $comment = cutstr(str_replace([_QUOTE, _CODE], '', text_filter(bb_decode($comment, $conf['name']))), 70);
                $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.': '.format_time($date, _TIMESTRING).'" class="sl_date">'.format_time($date).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $cid]).'#'.$id.'" title="'.$comment.'" class="sl_last">'.$comment.'</a></td></tr>';
            }
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'faq') {
        $result = $db->sql_query('SELECT fid, title, time FROM '.PREFIX_DB."_faq WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY fid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'files') {
        $result = $db->sql_query('SELECT lid, title, date FROM '.PREFIX_DB."_files WHERE uid = :user_id AND date <= NOW() AND status != '0' ORDER BY lid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'forum') {
        $result = $db->sql_query('SELECT id, title, time FROM '.PREFIX_DB."_forum WHERE uid = :user_id AND pid = '0' AND time <= NOW() AND status > '1' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=forum&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'jokes') {
        $result = $db->sql_query('SELECT jokeid, title, date FROM '.PREFIX_DB."_jokes WHERE uid = :user_id AND date <= NOW() AND status != '0' ORDER BY jokeid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=jokes#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'links') {
        $result = $db->sql_query('SELECT lid, title, date FROM '.PREFIX_DB."_links WHERE uid = :user_id AND date <= NOW() AND status != '0' ORDER BY lid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=links&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'media') {
        $result = $db->sql_query('SELECT id, title, date FROM '.PREFIX_DB."_media WHERE uid = :user_id AND date <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=media&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'news') {
        $result = $db->sql_query('SELECT sid, title, time FROM '.PREFIX_DB."_news WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY sid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=news&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'pages') {
        $result = $db->sql_query('SELECT pid, title, time FROM '.PREFIX_DB."_pages WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY pid DESC LIMIT 0,".$limit, ['user_id' => $user_id]);
        if ($db->sql_numrows($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->sql_fetchrow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=pages&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    return $cont;
}

function privat(): void {
    global $conf;
    if (is_user() && ($conf['privat']['act'] ?? 0)) {
        #$typ = (getVar('get', 'uname', 'text')) ? 3 : 0;
        setHead([
            'title' => _PRIVAT,
        ]);
        $title = ["<span OnClick=\"AjaxLoad('GET', '0', 'prmessin', 'go=1&amp;op=prmess&amp;typ=1', ''); return false;\">"._PRIN.'</span>', "<span OnClick=\"AjaxLoad('GET', '0', 'prmessou', 'go=1&amp;op=prmess&amp;typ=2', ''); return false;\">"._PROUT.'</span>', "<span OnClick=\"AjaxLoad('GET', '0', 'prmesssa', 'go=1&amp;op=prmess&amp;typ=3', ''); return false;\">"._PRSAVE.'</span>', _SEND];
        $text = ['<div id="repprmessin">'.prmess(1, 0, 0, 1).'</div>', '<div id="repprmessou">'.prmess(1, 0, 0, 2).'</div>', '<div id="repprmesssa">'.prmess(1, 0, 0, 3).'</div>', '<div id="repprmessfo">'.prmess(1, 0, 0, 4).'</div>'];
        $cont = setTemplateBasic('title', ['{%title%}' => _PRIVAT]).navi().getNaviTabs(0, 'tab', $title, $text);
        echo $cont;
        setFoot();
    } else {
        account();
    }
}

function favorites(): void {
    global $conf;
    if (is_user() && ($conf['favorites']['favact'] ?? 0)) {
        setHead([
            'title' => _FAVORITES,
        ]);
        echo setTemplateBasic('title', ['{%title%}' => _FAVORITES]).navi().setTemplateBasic('open').'<div id="repfavorliste">'.favorliste(1).'</div>'.setTemplateBasic('close');
        setFoot();
    } else {
        account();
    }
}

function passlost(): void {
    global $conf, $stop;
    $code_get = getVar('get', 'code', 'text');
    $code = ($code_get) ? substr($code_get, 0, 10) : false;
    $email = getVar('get', 'email', 'text');
    if ($email) checkemail($email);
    if (!is_user()) {
        setHead([
            'title' => _PASSWORDLOST,
        ]);
        $cont = setTemplateBasic('title', ['{%title%}' => _PASSWORDLOST]);
        $info = ($email) ? _PASSLOSP : _PASSLOSC;
        $send = ($email) ? _SENDPASSWORD : _SEND;
        if ($stop) $cont .= setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
        $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'info']);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._EMAIL.':</td><td><input type="email" name="email" value="'.$email.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._EMAIL.'" required></td></tr>';
        if ($email) $cont .= '<tr><td>'._CONFIRMATIONCODE.':</td><td><input type="text" name="code" value="'.$code.'" maxlength="10" class="sl_field '.$conf['style'].'" placeholder="'._CONFIRMATIONCODE.'" required></td></tr>';
        $cont .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="passmail"><input type="submit" value="'.$send.'" class="sl_but_blue"></td></tr>'
        .'<tr><td colspan="2" class="sl_center"><a href="index.php?name='.$conf['name'].'" title="'._USERLOGIN.'" class="sl_but_foot">'._USERLOGIN.'</a><a href="index.php?name='.$conf['name'].'&amp;op=newuser" title="'._REGNEWUSER.'" class="sl_but_foot">'._REGNEWUSER.'</a></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    } elseif (is_user()) {
        profil();
    }
}

function passmail(): void {
    global $db, $conf, $stop;
    $email = getVar('post', 'email', 'text');
    $code_post = getVar('post', 'code', 'text');
    $code = ($code_post) ? substr($code_post, 0, 10) : false;
    checkemail($email);
    if (!$stop) {
        $result = $db->sql_query('SELECT user_name, user_email, user_password, user_network FROM '.PREFIX_DB.'_users WHERE user_email = :email', ['email' => $email]);
        if ($db->sql_numrows($result) == 0) {
            $stop = _NOUSERINFO;
        } else {
            [$user_name, $user_email, $user_password, $network] = $db->sql_fetchrow($result);
            if (!empty($network)) $stop = _NETWORKPASS;
        }
    }
    if (!$stop) {
        $subpass = substr(md5($user_password), 0, 10);
        if ($code && $subpass == $code) {
            $newpass = getPass($conf['users']['minpass']);
            $cryptpass = md5_salt($newpass);
            $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_password = :user_password WHERE user_email = :email', ['user_password' => $cryptpass, 'email' => $email]);
            $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'</a>';
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$user_name;
            $message = str_replace('[text]', sprintf(_PASSSEND, $user_name, $conf['sitename'], $user_name, $newpass, $link), $conf['mtemp']);
            mail_send($user_email, $conf['adminmail'], $subject, $message, 0, 3);
            setHead([
                'title' => _PASSWORDLOST,
            ]);
            echo setTemplateBasic('title', ['{%title%}' => _PASSWORDLOST]).setTemplateWarning('warn', ['text' => _USERPASSWORD.' '.$user_name.' '._MAILED, 'url' => '?name='.$conf['name'], 'time' => 10, 'id' => 'info']);
            setFoot();
        } else {
            $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=passlost&amp;code='.$subpass.'&amp;email='.$email.'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=passlost&amp;code='.$subpass.'&amp;email='.$email.'</a>';
            $subject = $conf['sitename'].' - '._CODEFOR.' '.$user_name;
            $message = str_replace('[text]', sprintf(_PASSCSEND, $user_name, $conf['sitename'], $subpass, $link).'<br><br>'._IFYOUDIDNOTASK, $conf['mtemp']);
            mail_send($user_email, $conf['adminmail'], $subject, $message, 0, 3);
            setRedirect('index.php?name='.$conf['name'].'&op=passlost&email='.$email);
        }
    } else {
        passlost();
    }
}

function login(): void {
    global $db, $conf, $stop;
    if (($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop[] = _SECCODEINCOR;
    $uname = htmlspecialchars(trim(substr(getVar('post', 'user_name', 'text'), 0, 25)));
    $upass = htmlspecialchars(trim(substr(getVar('post', 'user_password', 'text'), 0, 25)));
    if (!$uname || !$upass) $stop[] = _LOGININCOR;
    $upasshash = md5_salt($upass);
    $result = $db->sql_query(
        'SELECT user_id, user_name, user_email, user_password, user_storynum, user_blockon, user_theme FROM '.PREFIX_DB.'_users WHERE user_name = :uname AND user_password = :upass AND user_network = :network',
        ['uname' => $uname, 'upass' => $upasshash, 'network' => '']
    );
    if ($db->sql_numrows($result) != 1) $stop[] = _LOGININCOR;
    [$user_id, $user_name, $user_email, $user_password, $user_storynum, $user_blockon, $user_theme] = $db->sql_fetchrow($result);
    if (!$user_id || $user_name != $uname || $user_password != $upasshash) $stop[] = _LOGININCOR;
    if (!$stop) {
        setCookies('account', time() + intval($conf['user_c_t']), [$user_id, $user_name, $user_password, $user_storynum, $user_blockon, $user_theme]);
        $uip = getIp();
        $uagent = getAgent();
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
        $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_last_ip = :user_last_ip, user_lastvisit = NOW(), user_agent = :user_agent WHERE user_id = :user_id', ['user_last_ip' => $uip, 'user_agent' => $uagent, 'user_id' => $user_id]);
        login_report(0, 1, $uname, '');
        setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
    } else {
        login_report(0, 0, $uname, $upass);
        account();
    }
}

function logout(): void {
    global $db, $user;
    $user_name = htmlspecialchars(substr($user[1], 0, 25));
    setCookiesDelete('account');
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $user_name, 'guest' => 2]);
    unset($user);
    setRedirect('index.php', true);
}

function edithome(): void {
    global $db, $user, $conf, $stop;
    if (is_user()) {
        setHead([
            'title' => _CHANGE,
        ]);
        $userinfo = getusrinfo();
        $userinfo['user_theme'] = (!$userinfo['user_theme']) ? $conf['theme'] : $userinfo['user_theme'];
        $cont = ($stop) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]) : '';
        $change = '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
        .'<tr><td>'._IP.':</td><td>'.$userinfo['user_last_ip'].'</td></tr>'
        .'<tr><td>'._REG.':</td><td>'.format_time($userinfo['user_regdate']).'</td></tr>';
        if ($conf['users']['point']) $change .= '<tr><td>'._POINTS.':</td><td>'.$userinfo['user_points'].'</td></tr>';
        $change .= '<tr><td>'._YOURNAME.':</td><td>'.$userinfo['user_name'].'</td></tr>'
        .'<tr><td>'._BIRTHDAY.':</td><td>'.datetime(2, 'user_birthday', $userinfo['user_birthday'], 10, $conf['style']).'</td></tr>'
        .'<tr><td>'._GENDER.':</td><td>'.get_gender('user_gender', $userinfo['user_gender'], $conf['style']).'</td></tr>'
        .'<tr><td>'._YOUREMAIL.':</td><td><input type="email" name="user_email" value="'.$userinfo['user_email'].'" maxlength="60" class="sl_field '.$conf['style'].'" placeholder="'._YOUREMAIL.'" required></td></tr>'
        .'<tr><td>'._SITEURL.':</td><td><input type="url" name="user_website" value="'.$userinfo['user_website'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._SITEURL.'"></td></tr>'
        .'<tr><td>'._OCCUPATION.':</td><td><input type="text" name="user_occ" value="'.$userinfo['user_occ'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._OCCUPATION.'"></td></tr>'
        .'<tr><td>'._LOCALITYLANG.':</td><td><input type="text" name="user_from" value="'.$userinfo['user_from'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._LOCALITYLANG.'"></td></tr>'
        .'<tr><td>'._INTERESTS.':</td><td><input type="text" name="user_interests" value="'.$userinfo['user_interests'].'" maxlength="150" class="sl_field '.$conf['style'].'" placeholder="'._INTERESTS.'"></td></tr>'
        .'<tr><td>'._SIGNATURE.':<div class="sl_small">'._SIGNATURE_TEXT.'</div></td><td>'.textarea('1', 'user_sig', $userinfo['user_sig'], $conf['name'], '5', _SIGNATURE, '0').'</td></tr>'
        .fields_in($userinfo['user_field'], $conf['name']);
        if ($conf['users']['news'] == 1) {
            $change .= '<tr><td>'._C_12.':</td><td><select name="user_storynum" class="sl_field '.$conf['style'].'">';
            $xusnum = 3;
            while ($xusnum <= 20) {
                $sel = ($xusnum == $userinfo['user_storynum']) ? ' selected' : '';
                $change .= '<option value="'.$xusnum.'"'.$sel.'>'.$xusnum.'</option>';
                $xusnum++;
            }
            $change .= '</select></td></tr>';
        } else {
            $change .= '<input type="hidden" name="user_storynum" value="'.($conf['news']['num'] ?? 0).'">';
        }
        $change .= '<tr><td>'._RNEWSLETTER.'</td><td>'.radio_form($userinfo['user_newsletter'], 'user_newsletter').'</td></tr>';
        if (is_active('forum')) $change .= '<tr><td>'._FSMAIL.'</td><td>'.radio_form($userinfo['user_fsmail'], 'user_fsmail').'</td></tr>';
        if (($conf['privat']['act'] ?? 0)) $change .= '<tr><td>'._PSMAIL.'</td><td>'.radio_form($userinfo['user_psmail'], 'user_psmail').'</td></tr>';
        $change .= '<tr><td>'._ALLOWUSERS.'</td><td>'.radio_form($userinfo['user_viewemail'], 'user_viewemail').'</td></tr>'
        .'<tr><td>'._ACTIVATEPERSONAL.'</td><td>'.radio_form($userinfo['user_blockon'], 'user_blockon').'</td></tr>'
        .'<tr><td>'._MENUCONF.':<div class="sl_small">'._MENUINFO.'</div></td><td>'.textarea('2', 'user_block', $userinfo['user_block'], $conf['name'], '10', _MENUCONF, '0').'</td></tr>';
        if ($conf['users']['theme']) {
            $tcategory = '';
            $tcount = 0;
            $dh = opendir('templates');
            while (($file = readdir($dh)) !== false) {
                if (!preg_match("/\./", $file) && $file != 'admin') {
                    $sel = ($file == $userinfo['user_theme']) ? ' selected' : '';
                    $tcategory .= '<option value="'.$file.'"'.$sel.'>'.$file.'</option>';
                    $tcount++;
                }
            }
            closedir($dh);
            if ($tcount > 1) $change .= '<tr><td>'._THEME.':</td><td><select name="user_theme" class="sl_field '.$conf['style'].'">'.$tcategory.'</select></td></tr>';
        }
        $change .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="user_name" value="'.$userinfo['user_name'].'">'
        .'<input type="hidden" name="op" value="savehome"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
        $asetup = '<table class="sl_table_form">';
        $user_avatar = (file_exists($conf['users']['adirectory'].'/'.$userinfo['user_avatar'])) ? $userinfo['user_avatar'] : 'default/00.gif';
        $asetup .= '<tr><td>'._AVATAR.':<div class="sl_small">'.sprintf(_AVATARINFO, $conf['users']['awidth'], $conf['users']['aheight'], files_size($conf['users']['amaxsize'])).'</div></td><td><img src="'.$conf['users']['adirectory'].'/'.$user_avatar.'" alt="'._AVATAR.'" title="'._AVATAR.'" class="sl_avatar"></td></tr>';
        $asetup .= '</table>';
        if ($conf['users']['aupload']) {
            $asetup .= '<hr><form enctype="multipart/form-data" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">'
            .'<tr><td>'._AVATAR_USER.':</td><td><input type="file" name="userfile" class="sl_field '.$conf['style'].'"></td><td><input type="hidden" name="op" value="saveavatar"><input type="submit" value="'._UPLOAD.'" class="sl_but_blue"></td></tr>'
            .'</table></form>';
        }
        $a = 6;
        $i = 1;
        $tdwidth = intval(100/$a);
        $aset = '';
        $adir = $conf['users']['adirectory'].'/default';
        $dh = opendir($adir);
        while (($file = readdir($dh)) !== false) {
            if (preg_match("#(\.gif|\.png|\.jpg|\.jpeg)$#is", $file) && !preg_match("#(\b0\.gif\b|\b00\.gif\b)$#i", $file)) {
                $filename = str_replace('_', ' ', preg_replace("/^(.*)\..*$/", '\\1', $file));
                if (($i - 1) % $a == 0) $aset .= '<tr>';
                $aset .= '<td style="width: '.$tdwidth.'%;"><a href="index.php?name='.$conf['name'].'&amp;op=saveavatar&amp;avatar='.$file.'"><img src="'.$adir.'/'.$file.'" alt="'._AVATARSAVE.' '._ID.' '.$filename.'" title="'._AVATARSAVE.' '._ID.' '.$filename.'" class="sl_avatar"></a></td>';
                if ($i % $a == 0) $aset .= '</tr>';
                $i++;
            }
        }
        closedir($dh);
        if ($i >= 1) $asetup .= '<hr>'.setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _AVATARSELECT]).'<table class="sl_table_form">'.$aset.'</table>';
        $user_id = intval($user[0]);
        [$network] = $db->sql_fetchrow($db->sql_query('SELECT user_network FROM '.PREFIX_DB.'_users WHERE user_id = :user_id', ['user_id' => $user_id]));
        if (empty($network)) {
            $psetup = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _PASSTEXT]);
            $psetup .= '<form action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">'
            .'<tr><td>'._PASSNEW.':</td><td><input type="password" name="newpass" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._PASSNEW.'" required></td></tr>'
            .'<tr><td>'._PASSNEW2.':</td><td><input type="password" name="newpass2" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._PASSNEW2.'" required></td></tr>'
            .'<tr><td>'._PASSOLD.':</td><td><input type="password" name="oldpass" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._PASSOLD.'" required></td></tr>'
            .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="savepass"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>'
            .'</table></form>';
        } else {
            $psetup = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NETWORKPASS]);
        }
        echo setTemplateBasic('title', ['{%title%}' => _CHANGE]).navi().$cont.getNaviTabs(0, 'tab', [_CHANGE, _AVATARSETUP, _PASSSETUP], [$change, $asetup, $psetup]);
        setFoot();
    } else {
        account();
    }
}

function savehome(): void {
    global $db, $user, $conf, $stop;
    $user_email = text_filter(getVar('post', 'user_email', 'text'));
    checkemail($user_email);
    if (!$stop) {
        $user_id = intval($user[0]);
        $checkn = htmlspecialchars(substr($user[1], 0, 25));
        $checkp = htmlspecialchars($user[2]);
        [$id, $name, $pass] = $db->sql_fetchrow($db->sql_query('SELECT user_id, user_name, user_password FROM '.PREFIX_DB.'_users WHERE user_id = :user_id', ['user_id' => $user_id]));
        if ($id == $user_id && $name == $checkn && $pass == $checkp) {
            $user_website = url_filter(getVar('post', 'user_website', 'text'));
            $user_occ = text_filter(getVar('post', 'user_occ', 'text'));
            $user_from = text_filter(getVar('post', 'user_from', 'text'));
            $user_interests = text_filter(getVar('post', 'user_interests', 'text'));
            $user_sig = getVar('post', 'user_sig', 'text');
            $user_viewemail = getVar('post', 'user_viewemail', 'num');
            $user_storynum = getVar('post', 'user_storynum', 'num');
            $user_blockon = getVar('post', 'user_blockon', 'num');
            $user_block = getVar('post', 'user_block', 'text');
            $user_theme = text_filter(getVar('post', 'user_theme', 'text'));
            $user_newsletter = getVar('post', 'user_newsletter', 'num');
            $user_fsmail = getVar('post', 'user_fsmail', 'num');
            $user_psmail = getVar('post', 'user_psmail', 'num');
            $user_birthday = save_datetime(2, 'user_birthday');
            $user_gender = getVar('post', 'user_gender', 'num');
            $user_field = fields_save(getVar('post', 'field', 'array'));
            $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_email = :user_email, user_website = :user_website, user_viewemail = :user_viewemail, user_occ = :user_occ, user_from = :user_from, user_interests = :user_interests, user_sig = :user_sig, user_storynum = :user_storynum, user_blockon = :user_blockon, user_block = :user_block, user_theme = :user_theme, user_newsletter = :user_newsletter, user_fsmail = :user_fsmail, user_psmail = :user_psmail, user_birthday = :user_birthday, user_gender = :user_gender, user_field = :user_field WHERE user_id = :user_id', ['user_email' => $user_email, 'user_website' => $user_website, 'user_viewemail' => $user_viewemail, 'user_occ' => $user_occ, 'user_from' => $user_from, 'user_interests' => $user_interests, 'user_sig' => $user_sig, 'user_storynum' => $user_storynum, 'user_blockon' => $user_blockon, 'user_block' => $user_block, 'user_theme' => $user_theme, 'user_newsletter' => $user_newsletter, 'user_fsmail' => $user_fsmail, 'user_psmail' => $user_psmail, 'user_birthday' => $user_birthday, 'user_gender' => $user_gender, 'user_field' => $user_field, 'user_id' => $user_id]);
            $userinfo = getusrinfo();
            setCookies('account', time() + intval($conf['user_c_t']), [$userinfo['user_id'], $userinfo['user_name'], $userinfo['user_password'], $userinfo['user_storynum'], $userinfo['user_blockon'], $userinfo['user_theme']]);
            setRedirect('index.php?name='.$conf['name'].'&op=edithome');
        }
    } else {
        edithome();
    }
}

function saveavatar(): void {
    global $user, $db, $conf, $stop;
    $post_avatar = getVar('post', 'avatar', 'text');
    $get_avatar = getVar('get', 'avatar', 'text');
    $avatar = ($post_avatar) ? $post_avatar : $get_avatar;
    if (is_user()) {
        $user_id = intval($user[0]);
        if (!$avatar && $conf['users']['aupload']) {
            $uavatar = upload(1, $conf['users']['adirectory'], $conf['users']['atypefile'], $conf['users']['amaxsize'], $conf['name'], $conf['users']['awidth'], $conf['users']['aheight'], $user_id);
            $avatar = (!$uavatar) ? $avatar : $uavatar;
        } elseif ($avatar) {
            $avatar = (preg_match("#(\.gif|\.png|\.jpg|\.jpeg)$#is", $avatar) && !preg_match("#(\b0\.gif\b|\b00\.gif\b)$#i", $avatar) && file_exists($conf['users']['adirectory'].'/default/'.$avatar)) ? 'default/'.$avatar : '';
        }
        if (!$stop && $avatar) {
            $avatar = text_filter($avatar);
            $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_avatar = :user_avatar WHERE user_id = :user_id', ['user_avatar' => $avatar, 'user_id' => $user_id]);
            setRedirect('index.php?name='.$conf['name'].'&op=edithome');
        } else {
            edithome();
        }
    } else {
        edithome();
    }
}

function savepass(): void {
    global $user, $db, $conf, $stop;
    $newpass = getVar('post', 'newpass', 'text', false);
    $newpass2 = getVar('post', 'newpass2', 'text', false);
    $oldpass = getVar('post', 'oldpass', 'text', false);
    if (is_user() && $oldpass && $newpass && $newpass2) {
        if (strlen($newpass) >= $conf['users']['minpass']) {
            $oldpass = md5_salt($oldpass);
            $user_id = intval($user[0]);
            [$pass] = $db->sql_fetchrow($db->sql_query('SELECT user_password FROM '.PREFIX_DB.'_users WHERE user_id = :user_id AND user_network = :network', ['user_id' => $user_id, 'network' => '']));
            if (!empty($pass) && $pass == $oldpass) {
                if ($newpass == $newpass2) {
                    $userinfo = getusrinfo();
                    $user_email = $userinfo['user_email'];
                    $user_name = $userinfo['user_name'];
                    $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'</a>';
                    $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$user_name;
                    $message = str_replace('[text]', sprintf(_PASSESEND, $user_name, $conf['sitename'], $user_name, $newpass, $link), $conf['mtemp']);
                    mail_send($user_email, $conf['adminmail'], $subject, $message, 0, 3);
                    $newpass = md5_salt($newpass);
                    $db->sql_query('UPDATE '.PREFIX_DB.'_users SET user_password = :user_password WHERE user_id = :user_id', ['user_password' => $newpass, 'user_id' => $user_id]);
                    setRedirect('index.php?name='.$conf['name']);
                } else {
                    $stop[] = _ERROR_PASS;
                    edithome();
                }
            } else {
                $stop[] = _ERROROLD;
                edithome();
            }
        } else {
            $stop[] = _CHARMIN.': '.$conf['users']['minpass'];
            edithome();
        }
    } else {
        edithome();
    }
}

switch($op) {
    default: account(); break;
    case 'newuser': newuser(); break;
    case 'finnewuser': finnewuser(); break;
    case 'network': network(); break;
    case 'privat': privat(); break;
    case 'favorites': favorites(); break;
    case 'view': view(); break;
    case 'login': login(); break;
    case 'logout': logout(); break;
    case 'edithome': edithome(); break;
    case 'savehome': savehome(); break;
    case 'passlost': passlost(); break;
    case 'passmail': passmail(); break;
    case 'activate': activate(); break;
    case 'saveavatar': saveavatar(); break;
    case 'savepass': savepass(); break;
}
