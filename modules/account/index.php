<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function account(): void {
    global $conf, $stop;
    if (is_user()) {
        profil();
    } else {
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

function checkuser(string $nick, string $mail, int|string $rules): ?array {
    global $db, $conf, $stop;
    if ($conf['users']['rule'] && $rules != '1') $stop[] = _ERROR_RULES;
    checkemail($mail);
    $mailb = explode(',', $conf['users']['mail_b']);
    foreach ($mailb as $val) if ($val != '' && $val == strtolower($mail)) $stop[] = _MAIL_BLOCK;
    $nameb = explode(',', $conf['users']['name_b']);
    foreach ($nameb as $val) if ($val != '' && $val == strtolower($nick)) $stop[] = _NAME_BLOCK;
    if (!$nick || !analyze_name($nick)) $stop[] = _ERRORINVNICK;
    if (strlen($nick) > 25) $stop[] = _NICKLONG;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $nick])) > 0) $stop[] = _NICKTAKEN;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users_temp WHERE name = :name', ['name' => $nick])) > 0) $stop[] = _NICKTAKEN;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $mail])) > 0) $stop[] = _ERROR_EMAIL;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users_temp WHERE email = :email', ['email' => $mail])) > 0) $stop[] = _ERROR_EMAIL;
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
            $nick = getVar('post', $unkey, 'text');
            $nick = ($nick) ? filterText(substr($nick, 0, 25)) : '';
            $mail = getVar('post', 'mail', 'text');
            $mail = ($mail) ? filterText($mail) : '';
            $captcha = ($conf['gfx_chk'] == 3 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
            $cont .= setTemplateBasic('open');
            $cont .= '<form action="index.php?name='.$conf['name'].'" method="post">'
            .'<table class="sl_table_form">'
            .'<tr><td>'._NICKNAME.':</td><td><input type="text" name="'.$unkey.'" value="'.$nick.'" maxlength="25" class="sl_field '.$conf['style'].'" placeholder="'._NICKNAME.'" required></td></tr>'
            .'<tr><td>'._EMAIL.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._EMAIL.'" required></td></tr>'
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
        $nick = getVar('post', $unkey, 'name');
        $mail = getVar('post', 'mail', 'text');
        $rules = getVar('post', 'rules', 'num');
        checkuser($nick, $mail, $rules);
        $pass = htmlspecialchars(substr(getVar('post', 'user_password', 'text'), 0, 40));
        $pass2 = htmlspecialchars(substr(getVar('post', 'user_password2', 'text'), 0, 40));
        if (($conf['gfx_chk'] == 3 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop[] = _SECCODEINCOR;
        if ($pass == '' && $pass2 == '') {
            $pass = getPass($conf['users']['minpass']);
        } elseif ($pass != $pass2) {
            $stop[] = _ERROR_PASS;
        } elseif ($pass == $pass2 && strlen($pass) < $conf['users']['minpass']) {
            $stop[] = _CHARMIN.': '.$conf['users']['minpass'];
        }
        if (!$stop) {
            $check = md5(getPass(10));
            $time = time();
            $finishlink = $conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=activate&amp;user='.urlencode($nick).'&amp;num='.$check;
            $nick = filterText($nick);
            $mail = filterText($mail);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_users_temp (id, name, email, password, regdate, code, time) VALUES (NULL, :name, :email, :password, NOW(), :code, :time)',
                ['name' => $nick, 'email' => $mail, 'password' => $pass, 'code' => $check, 'time' => $time]
            );
            setHead(['title' => _ACCOUNTCREATED]);
            if ($conf['users']['nomail'] == 1) {
                $cont = setTemplateBasic('title', ['{%title%}' => _ACCOUNTCREATED]);
                $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _TOFINISHUSERN]);
                $cont .= setTemplateBasic('open');
                $cont .= '<form action="index.php" method="get">'
                .'<table class="sl_table_form">'
                .'<tr><td>'._UNICKNAME.':</td><td>'.$nick.'</td></tr>'
                .'<tr><td>'._UPASSWORD.':</td><td>'.$pass.'</td></tr>'
                .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="'.$conf['name'].'"><input type="hidden" name="op" value="activate"><input type="hidden" name="user" value="'.urlencode($nick).'"><input type="hidden" name="num" value="'.$check.'"><input type="submit" value="'._ACTIVATIONSUB.'" class="sl_but_blue"></td></tr></table></form>';
                $cont .= setTemplateBasic('close');
            } else {
                $link = '<a href="'.$finishlink.'" target="_blank" title="'._ACTIVATIONSUB.'">'.str_replace('&amp;', '&', $finishlink).'</a>';
                $subject = $conf['sitename'].' - '._ACTIVATIONSUB;
                $message = str_replace('[text]', sprintf(_PASSFSEND, $mail, $conf['sitename'], $link, $nick, $pass).'<br><br>'._IFYOUDIDNOTASK, $conf['mtemp']);
                addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
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
        $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_DEFAULT) ?: (parse_url($conf['homeurl'], PHP_URL_HOST) ?: 'localhost');
        $url = 'https://ulogin.ru/token.php?token='.rawurlencode($token).'&host='.rawurlencode($host);
        set_error_handler(static function () {
            return true;
        }, E_WARNING);
        $s = file_get_contents($url);
        restore_error_handler();
        $ulog = is_string($s) ? json_decode($s, true) : [];
        if (empty($ulog['error']) && isArray($ulog)) {
            $nickname = isset($ulog['nickname']) ? ucfirst(getTranslit($ulog['nickname'], 1)) : '';
            $first = isset($ulog['first_name']) ? ucfirst(getTranslit($ulog['first_name'], 1)) : '';
            $lastn = isset($ulog['last_name']) ? ucfirst(getTranslit($ulog['last_name'], 1)) : '';
            $variants = [];
            $variants[] = substr($first, 0, 25);
            if (!empty($nickname)) {
                $variants[] = substr($nickname, 0, 25);
                $variants[] = substr($nickname.'-'.$first, 0, 25);
            }
            if (!empty($lastn)) {
                $variants[] = substr($lastn, 0, 25);
                $variants[] = substr($first.'-'.$lastn, 0, 25);
            }
            $variants[] = substr($first, 0, 20).'-'.date('Y');
            $variants[] = substr($first, 0, 22).'-'.rand(1, 99);
            $variants[] = substr($first, 0, 20).'-'.getPass(4);
            foreach ($variants as $var) {
                if ($db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $var])) == 0) {
                    $uname = $var;
                    break;
                }
            }
            $uip = getIp();
            $uagent = getAgent();
            $network = isset($ulog['profile']) ? $ulog['profile'] : $ulog['network'];
            $result = $db->getSqlQuery('SELECT id, name, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE network = :network', ['network' => $network]);
            [$uid, $nick, $pass, $story, $blockon, $theme] = $db->getSqlRow($result);
            if ($db->getSqlRowCount($result) == 1) {
                setCookies('account', time() + intval($conf['user_c_t']), [$uid, $nick, $pass, $story, $blockon, $theme]);
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', ['ip' => $uip, 'agent' => $uagent, 'id' => $uid]);
                login_report(0, 1, $nick, '');
                setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
            } else {
                $uemail = isset($ulog['email']) ? mb_strtolower($ulog['email']) : '';
                $upass = getPassHash(getPass(32));
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, email, avatar, regdate, password, ip, agent, network, block, warnings, field) VALUES (NULL, :name, :email, :avatar, NOW(), :password, :ip, :agent, :network, :block, :warnings, :field)', ['name' => $uname, 'email' => $uemail, 'avatar' => 'default/00.gif', 'password' => $upass, 'ip' => $uip, 'agent' => $uagent, 'network' => $network, 'block' => '', 'warnings' => '', 'field' => '']);
                [$uid, $nick, $pass, $story, $blockon, $theme] = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE network = :network', ['network' => $network]));
                setCookies('account', time() + intval($conf['user_c_t']), [$uid, $nick, $pass, $story, $blockon, $theme]);
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET lastvis = NOW() WHERE id = :id', ['id' => $uid]);
                $uphoto = isset($ulog['photo']) ? $ulog['photo'] : '';
                if ($uphoto) {
                    $anetwork = isset($ulog['network']) ? substr(getTranslit($ulog['network'], 1), 0, 25) : 'network';
                    $uavatar = upload(4, $conf['users']['adirectory'], $conf['users']['atypefile'], '104857600', $anetwork, '1600', '1600', $uid, $uphoto);
                    $afile = $conf['users']['adirectory'].'/'.$uavatar;
                    if (file_exists($afile)) {
                        [$awidth] = getimagesize($afile);
                        if ($awidth > $conf['users']['awidth']) create_img_gd($afile, $afile, $conf['users']['awidth']);
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET avatar = :avatar WHERE id = :id', ['avatar' => $uavatar, 'id' => $uid]);
                    }
                }
                login_report(0, 1, $nick, '');
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
    $user = getVar('get', 'user', 'name', '');
    $num = getVar('get', 'num', 'text', '');
    $past = time() - 86400;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE time < :past', ['past' => $past]);
    $result = $db->getSqlQuery('SELECT name, email, password, regdate, code FROM '.PREFIX_DB.'_users_temp WHERE name = :uname AND code = :cnum', ['uname' => $user, 'cnum' => $num]);
    setHead(['title' => _ACTIVATIONSUB]);
    if ($db->getSqlRowCount($result) === 1) {
        [$nick, $mail, $pass, $reg, $check] = $db->getSqlRow($result);
        if ($num == $check) {
            $uip = getIp();
            $uagent = getAgent();
            $rank = '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, rank, email, avatar, regdate, password, lang, ip, agent, network, block, warnings, field) VALUES (NULL, :uname, :rank, :email, :avatar, :regdate, :pwd, :lang, :ip, :agent, :network, :block, :warnings, :field)', ['uname' => $nick, 'rank' => $rank, 'email' => $mail, 'avatar' => 'default/00.gif', 'regdate' => $reg, 'pwd' => md5_salt($pass), 'lang' => $locale, 'ip' => $uip, 'agent' => $uagent, 'network' => '', 'block' => '', 'warnings' => '', 'field' => '']);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE name = :uname AND code = :cnum', ['uname' => $nick, 'cnum' => $check]);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = 0', ['uname' => $uip]);
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
    if ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || isAdmin()) {
        $uname = htmlspecialchars(substr(urldecode(getVar('get', 'uname', 'text')), 0, 25));
        $params = [];
        if ($uname) {
            $where = 'BINARY u.name = :uname';
            $params['uname'] = $uname;
        } else {
            $where = 'u.id = :uid';
            $params['uid'] = getVar('get', 'id', 'num');
        }
        $result = $db->getSqlQuery('SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.occ, u.origin, u.interest, u.sig, u.viewmail, u.lastvis, u.lang, u.points, u.ip, u.warnings, u.birthday, u.gender, u.votes, u.tvotes, u.field, u.agent, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.grp) WHERE '.$where, $params);
        if ($db->getSqlRowCount($result) > 0) {
            [$uid, $nick, $rank, $mail, $site, $avatar, $reg, $occ, $from, $inter, $sig, $view, $last, $lang, $point, $ip, $warn, $birth, $gender, $votes, $total, $field, $agent, $gname, $grank, $gcolor] = $db->getSqlRow($result);
            $seotitle  = $nick;
            $seoctitle = _PERSONALINFO;
            $seodesc   = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($sig ?? '', $conf['name'], false), $conf['name']))), 160);
            $seoimg    = ($avatar && file_exists($conf['users']['adirectory'].'/'.$avatar)) ? $conf['homeurl'].'/'.$conf['users']['adirectory'].'/'.$avatar : '';
            $seotime   = $last;
            $seoauthor = $nick ?: ($uname ?: $conf['sitename']);
            setHead([
                'title' => $seotitle,
                'ctitle' => $seoctitle,
                'desc' => $seodesc,
                'img' => $seoimg,
                'time' => $seotime,
                'author' => $seoauthor,
            ]);
            if (isAdmin()) {
                $id = [_ID, $uid];
                $regdate = [_REG, format_time($reg, _TIMESTRING)];
                $lastvisit = [_LAST_VISIT, format_time($last, _TIMESTRING)];
                $ip = [_IP, user_geo_ip($ip, 4)];
                $agent = [_BROWSER, $agent];
            } else {
                $id = [_ID, _HIDE];
                $regdate = [_REG, format_time($reg)];
                $lastvisit = [_LAST_VISIT, format_time($last)];
                $ip = [_COUNTRY, user_geo_ip($ip, 2)];
                $agent = [_BROWSER, _HIDE];
            }
            $name = [_NICKNAME, $nick];
            $urank = ($rank) ? [_URANK, $rank] : [_URANK, ''];
            $mail = ((isAdmin() || $view) && $mail) ? [_EMAIL, anti_spam($mail)] : [_EMAIL, _HIDE];
            $site = ($site) ? ((isAdmin() || is_user()) ? [_SITEURL, domain($site)] : [_SITEURL, _HIDE]) : [_SITEURL, _NO_INFO];
            $avatar = ($avatar && file_exists($conf['users']['adirectory'].'/'.$avatar)) ? $conf['users']['adirectory'].'/'.$avatar : $conf['users']['adirectory'].'/default/00.gif';
            $occup = ($occ) ? [_OCCUPATION, $occ] : [_OCCUPATION, _NO_INFO];
            $from = ($from) ? [_LOCALITYLANG, $from] : [_LOCALITYLANG, _NO_INFO];
            $inter = ($inter) ? [_INTERESTS, $inter] : [_INTERESTS, _NO_INFO];
            $sign = ((isAdmin() || is_user()) && $sig) ? '<hr>'.filterReplaceText(filterMarkdown($sig, $conf['name'], false), $conf['name']) : '';
            $lang = ($lang) ? [_LANGUAGE, getLangName($lang)] : [_LANGUAGE, getLangName($conf['language'])];
            $points = ($conf['users']['point'] && $point) ? [_POINTS, $point] : [_POINTS, _NO_INFO];
            $warn = [_UWARNS, warnings($warn)];
            if ($birth) {
                preg_match('#([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})#', $birth, $datetime);
                $birth = [_BIRTHDAY, $datetime[3].'.'.$datetime[2].'.'.$datetime[1]];
            } else {
                $birth = [_BIRTHDAY, _NO_INFO];
            }
            $gender = [_GENDER, gender($gender)];
            $rating = [_RATING, ajax_rating(1, $uid, $conf['name'], $votes, $total, '', 1)];
            $field = ($field) ? fields_out($field, $conf['name']) : '';
            $sgroup = ($gname) ? [_SPEC_GROUP, '<span style="color: '.$gcolor.'">'.$gname.'</span>'] : [_SPEC_GROUP, _NO];
            $rgroup = [];
            $uranks = '';
            if ($conf['users']['point'] && $point) {
                $result = $db->getSqlQuery('SELECT name, rank, color FROM '.PREFIX_DB."_groups WHERE points <= :points AND extra != '1' ORDER BY points ASC", ['points' => intval($point)]);
                $group = [];
                while([$guname, $gurank, $gcolor] = $db->getSqlRow($result)) {
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
            $admin = (isAdmin()) ? add_menu('<a href="'.$afile.'.php?op=users_add&amp;id='.$uid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=security_block&amp;new_ip='.$ip.'" OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.$ip.'&quot;?\');" title="'._BANIPSENDER.'">'._BANIPSENDER.'</a>||<a href="'.$afile.'.php?op=users_del&amp;id='.$uid.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$nick.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $privat = (($conf['privat']['act'] ?? 0) && $nick) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'privat', 'uname' => urlencode($nick)]).'" title="'._SENDMES.'" class="sl_but_green">'._MESSAGE.'</a>' : '';
            $profil = (is_user() && $uname == $nick) ? '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._ACCOUNT.'" class="sl_but">'._ACCOUNT.'</a>' : '';
            $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
            $title[] = _COMMENTS;
            $text[] = last($uid, 'comm');
            if (is_active('faq')) {
                $title[] = _FAQ;
                $text[] = last($uid, 'faq');
            }
            if (is_active('files')) {
                $title[] = _FILES;
                $text[] = last($uid, 'files');
            }
            if (is_active('forum')) {
                $title[] = _FORUM;
                $text[] = last($uid, 'forum');
            }
            if (is_active('jokes')) {
                $title[] = _JOKES;
                $text[] = last($uid, 'jokes');
            }
            if (is_active('links')) {
                $title[] = _LINKS;
                $text[] = last($uid, 'links');
            }
            if (is_active('media')) {
                $title[] = _MEDIA;
                $text[] = last($uid, 'media');
            }
            if (is_active('news')) {
                $title[] = _NEWS;
                $text[] = last($uid, 'news');
            }
            if (is_active('pages')) {
                $title[] = _PAGES;
                $text[] = last($uid, 'pages');
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
        $cont .= getUserNav();
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
    $uid = intval($uid);
    $num = getUserNews(25);
    $limit = intval($num);
    $cont = '';
    if ($modul == 'comm') {
        $result = $db->getSqlQuery('SELECT id, cid, modul, time, body FROM '.PREFIX_DB."_comment WHERE uid = :user_id AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $cid, $modul, $date, $comment] = $db->getSqlRow($result)) {
                $comment = cutstr(str_replace([_QUOTE, _CODE], '', filterText(filterReplaceText(filterMarkdown($comment, $conf['name'], false), $conf['name']))), 70);
                $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.': '.format_time($date, _TIMESTRING).'" class="sl_date">'.format_time($date).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $cid]).'#'.$id.'" title="'.$comment.'" class="sl_last">'.$comment.'</a></td></tr>';
            }
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'faq') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_faq WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'files') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_files WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="'.getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'forum') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_forum WHERE uid = :user_id AND pid = '0' AND time <= NOW() AND status > '1' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=forum&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'jokes') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_jokes WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=jokes#'.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'links') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_links WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=links&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'media') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_media WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=media&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'news') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_news WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=news&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
            $cont .= '</table>';
        } else {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    }
    if ($modul == 'pages') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_pages WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        if ($db->getSqlRowCount($result) > 0) {
            $cont .= '<table class="sl_table_amount">';
            while([$id, $title, $time] = $db->getSqlRow($result)) $cont .= '<tr><td style="width: 15%"><time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.': '.format_time($time, _TIMESTRING).'" class="sl_date">'.format_time($time).'</time></td><td><a href="index.php?name=pages&amp;op=view&amp;id='.$id.'" title="'.$title.'" class="sl_last">'.$title.'</a></td></tr>';
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
        $text = ['<div id="repprmessin">'.getPmView(1, 0, 0, 1).'</div>', '<div id="repprmessou">'.getPmView(1, 0, 0, 2).'</div>', '<div id="repprmesssa">'.getPmView(1, 0, 0, 3).'</div>', '<div id="repprmessfo">'.getPmView(1, 0, 0, 4).'</div>'];
        $cont = setTemplateBasic('title', ['{%title%}' => _PRIVAT]).getUserNav().getNaviTabs(0, 'tab', $title, $text);
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
        echo setTemplateBasic('title', ['{%title%}' => _FAVORITES]).getUserNav().setTemplateBasic('open').'<div id="repfavorliste">'.getFavorList(1).'</div>'.setTemplateBasic('close');
        setFoot();
    } else {
        account();
    }
}

function passlost(): void {
    global $conf, $stop;
    $code = getVar('get', 'code', 'text');
    $code = ($code) ? substr($code, 0, 10) : false;
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
    $code = getVar('post', 'code', 'text');
    $code = ($code) ? substr($code, 0, 10) : false;
    checkemail($email);
    if (!$stop) {
        $result = $db->getSqlQuery('SELECT name, email, password, network FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $email]);
        if ($db->getSqlRowCount($result) == 0) {
            $stop = _NOUSERINFO;
        } else {
            [$nick, $mail, $pass, $network] = $db->getSqlRow($result);
            if (!empty($network)) $stop = _NETWORKPASS;
        }
    }
    if (!$stop) {
        $subpass = substr(md5($pass), 0, 10);
        if ($code && $subpass == $code) {
            $newpass = getPass($conf['users']['minpass']);
            $chash = getPassHash($newpass);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :password WHERE email = :email', ['password' => $chash, 'email' => $email]);
            $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'</a>';
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
            $message = str_replace('[text]', sprintf(_PASSSEND, $nick, $conf['sitename'], $nick, $newpass, $link), $conf['mtemp']);
            addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
            setHead([
                'title' => _PASSWORDLOST,
            ]);
            echo setTemplateBasic('title', ['{%title%}' => _PASSWORDLOST]).setTemplateWarning('warn', ['text' => _USERPASSWORD.' '.$nick.' '._MAILED, 'url' => '?name='.$conf['name'], 'time' => 10, 'id' => 'info']);
            setFoot();
        } else {
            $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=passlost&amp;code='.$subpass.'&amp;email='.$email.'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'&amp;op=passlost&amp;code='.$subpass.'&amp;email='.$email.'</a>';
            $subject = $conf['sitename'].' - '._CODEFOR.' '.$nick;
            $message = str_replace('[text]', sprintf(_PASSCSEND, $nick, $conf['sitename'], $subpass, $link).'<br><br>'._IFYOUDIDNOTASK, $conf['mtemp']);
            addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
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
    $result = $db->getSqlQuery(
        'SELECT id, name, email, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE name = :name AND network = :network',
        ['name' => $uname, 'network' => '']
    );
    [$uid, $nick, $mail, $pass, $story, $blockon, $theme] = $db->getSqlRow($result);
    if ($db->getSqlRowCount($result) != 1 || !$uid || $nick != $uname || !checkPassHash($upass, $pass)) $stop[] = _LOGININCOR;
    if (!$stop && password_needs_rehash($pass, PASSWORD_BCRYPT)) {
        $pass = getPassHash($upass);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :pwd WHERE id = :id', ['pwd' => $pass, 'id' => $uid]);
    }
    if (!$stop) {
        setCookies('account', time() + intval($conf['user_c_t']), [$uid, $nick, $pass, $story, $blockon, $theme]);
        $uip = getIp();
        $uagent = getAgent();
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', ['ip' => $uip, 'agent' => $uagent, 'id' => $uid]);
        login_report(0, 1, $uname, '');
        setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
    } else {
        login_report(0, 0, $uname, $upass);
        account();
    }
}

function logout(): void {
    global $db, $user;
    $nick = (is_array($user) && isset($user[1])) ? htmlspecialchars(substr((string)$user[1], 0, 25)) : '';
    setCookiesDelete('account');
    if ($nick !== '') $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $nick, 'guest' => 2]);
    unset($user);
    setRedirect('index.php', true);
}

function edithome(): void {
    global $db, $user, $conf, $stop;
    if (is_user()) {
        setHead([
            'title' => _CHANGE,
        ]);
        $userinfo = getUserInfo();
        $conf['style'] = (string)($conf['style'] ?? '');
        if ($conf['style'] === '') {
            $conf['style'] = 'sl_account';
        }
        $birthday = trim((string)($userinfo['birthday'] ?? ''));
        if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            $birthday = '';
        }
        $userinfo['theme'] = (!$userinfo['theme']) ? $conf['theme'] : $userinfo['theme'];
        $cont = ($stop) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]) : '';
        $change = '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
        .'<tr><td>'._IP.':</td><td>'.$userinfo['ip'].'</td></tr>'
        .'<tr><td>'._REG.':</td><td>'.format_time($userinfo['regdate']).'</td></tr>';
        if ($conf['users']['point']) $change .= '<tr><td>'._POINTS.':</td><td>'.$userinfo['points'].'</td></tr>';
        $change .= '<tr><td>'._YOURNAME.':</td><td>'.$userinfo['name'].'</td></tr>'
        .'<tr><td>'._BIRTHDAY.':</td><td>'.datetime(2, 'user_birthday', $birthday, 10, $conf['style']).'</td></tr>'
        .'<tr><td>'._GENDER.':</td><td>'.get_gender('gender', $userinfo['gender'], $conf['style']).'</td></tr>'
        .'<tr><td>'._YOUREMAIL.':</td><td><input type="email" name="mail" value="'.$userinfo['email'].'" maxlength="60" class="sl_field '.$conf['style'].'" placeholder="'._YOUREMAIL.'" required></td></tr>'
        .'<tr><td>'._SITEURL.':</td><td><input type="url" name="site" value="'.$userinfo['website'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._SITEURL.'"></td></tr>'
        .'<tr><td>'._OCCUPATION.':</td><td><input type="text" name="occ" value="'.$userinfo['occ'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._OCCUPATION.'"></td></tr>'
        .'<tr><td>'._LOCALITYLANG.':</td><td><input type="text" name="from" value="'.$userinfo['origin'].'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._LOCALITYLANG.'"></td></tr>'
        .'<tr><td>'._INTERESTS.':</td><td><input type="text" name="inter" value="'.$userinfo['interest'].'" maxlength="150" class="sl_field '.$conf['style'].'" placeholder="'._INTERESTS.'"></td></tr>'
        .'<tr><td>'._SIGNATURE.':<div class="sl_small">'._SIGNATURE_TEXT.'</div></td><td>'.textarea('1', 'sig', $userinfo['sig'], $conf['name'], '5', _SIGNATURE, '0').'</td></tr>'
        .fields_in($userinfo['field'], $conf['name']);
        if ($conf['users']['news'] == 1) {
            $change .= '<tr><td>'._C_12.':</td><td><select name="story" class="sl_field '.$conf['style'].'">';
            $xusnum = 3;
            while ($xusnum <= 20) {
                $sel = ($xusnum == $userinfo['storynum']) ? ' selected' : '';
                $change .= '<option value="'.$xusnum.'"'.$sel.'>'.$xusnum.'</option>';
                $xusnum++;
            }
            $change .= '</select></td></tr>';
        } else {
            $change .= '<input type="hidden" name="story" value="'.($conf['news']['num'] ?? 0).'">';
        }
        $change .= '<tr><td>'._RNEWSLETTER.'</td><td>'.radio_form($userinfo['newslet'], 'news').'</td></tr>';
        if (is_active('forum')) $change .= '<tr><td>'._FSMAIL.'</td><td>'.radio_form($userinfo['fsmail'], 'fsmail').'</td></tr>';
        if (($conf['privat']['act'] ?? 0)) $change .= '<tr><td>'._PSMAIL.'</td><td>'.radio_form($userinfo['psmail'], 'psmail').'</td></tr>';
        $change .= '<tr><td>'._ALLOWUSERS.'</td><td>'.radio_form($userinfo['viewmail'], 'view').'</td></tr>'
        .'<tr><td>'._ACTIVATEPERSONAL.'</td><td>'.radio_form($userinfo['blockon'], 'blockon').'</td></tr>'
        .'<tr><td>'._MENUCONF.':<div class="sl_small">'._MENUINFO.'</div></td><td>'.textarea('2', 'block', $userinfo['block'], $conf['name'], '10', _MENUCONF, '0').'</td></tr>';
        if ($conf['users']['theme']) {
            $tcategory = '';
            $tcount = 0;
            $dh = opendir('templates');
            while (($file = readdir($dh)) !== false) {
                if (!preg_match("/\./", $file) && $file != 'admin') {
                    $sel = ($file == $userinfo['theme']) ? ' selected' : '';
                    $tcategory .= '<option value="'.$file.'"'.$sel.'>'.$file.'</option>';
                    $tcount++;
                }
            }
            closedir($dh);
            if ($tcount > 1) $change .= '<tr><td>'._THEME.':</td><td><select name="theme" class="sl_field '.$conf['style'].'">'.$tcategory.'</select></td></tr>';
        }
        $change .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="user_name" value="'.$userinfo['name'].'">'
        .'<input type="hidden" name="op" value="savehome"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
        $asetup = '<table class="sl_table_form">';
        $avatar = (file_exists($conf['users']['adirectory'].'/'.$userinfo['avatar'])) ? $userinfo['avatar'] : 'default/00.gif';
        $asetup .= '<tr><td>'._AVATAR.':<div class="sl_small">'.sprintf(_AVATARINFO, $conf['users']['awidth'], $conf['users']['aheight'], filterSize($conf['users']['amaxsize'])).'</div></td><td><img src="'.$conf['users']['adirectory'].'/'.$avatar.'" alt="'._AVATAR.'" title="'._AVATAR.'" class="sl_avatar"></td></tr>';
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
        $uid = intval($user[0]);
        [$network] = $db->getSqlRow($db->getSqlQuery('SELECT network FROM '.PREFIX_DB.'_users WHERE id = :user_id', ['user_id' => $uid]));
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
        echo setTemplateBasic('title', ['{%title%}' => _CHANGE]).getUserNav().$cont.getNaviTabs(0, 'tab', [_CHANGE, _AVATARSETUP, _PASSSETUP], [$change, $asetup, $psetup]);
        setFoot();
    } else {
        account();
    }
}

function savehome(): void {
    global $db, $user, $conf, $stop;
    $mail = getVar('post', 'mail', 'text');
    checkemail($mail);
    if (!$stop) {
        $uid = intval($user[0]);
        $checkn = htmlspecialchars(substr($user[1], 0, 25));
        $checkp = htmlspecialchars($user[2]);
        [$id, $name, $pass] = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password FROM '.PREFIX_DB.'_users WHERE id = :user_id', ['user_id' => $uid]));
        if ($id == $uid && $name == $checkn && $pass == $checkp) {
            $site = getVar('post', 'site', 'url');
            $occ = getVar('post', 'occ', 'text');
            $from = getVar('post', 'from', 'text');
            $inter = getVar('post', 'inter', 'text');
            $sig = getVar('post', 'sig', 'text');
            $view = getVar('post', 'view', 'num');
            $story = getVar('post', 'story', 'num');
            $blockon = getVar('post', 'blockon', 'num');
            $block = getVar('post', 'block', 'text');
            $theme = getVar('post', 'theme', 'text');
            $news = getVar('post', 'news', 'num');
            $fsmail = getVar('post', 'fsmail', 'num');
            $psmail = getVar('post', 'psmail', 'num');
            $birth = getVar('req', 'user_birthday', 'date');
            $gender = getVar('post', 'gender', 'num');
            $field = getVar('post', 'field', 'field');
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET email = :email, website = :website, viewmail = :viewmail, occ = :occ, origin = :origin, interest = :interest, sig = :sig, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newslet, fsmail = :fsmail, psmail = :psmail, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', ['email' => $mail, 'website' => $site, 'viewmail' => $view, 'occ' => $occ, 'origin' => $from, 'interest' => $inter, 'sig' => $sig, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newslet' => $news, 'fsmail' => $fsmail, 'psmail' => $psmail, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid]);
            $theme = $theme ?: ($conf['theme'] ?? '');
            setCookies('account', time() + intval($conf['user_c_t']), [$uid, $name, $pass, $story, $blockon, $theme]);
            setRedirect('index.php?name='.$conf['name'].'&op=edithome');
        }
    } else {
        edithome();
    }
}

function saveavatar(): void {
    global $user, $db, $conf, $stop;
    $avatar = getVar('post', 'avatar', 'text');
    if (!$avatar) $avatar = getVar('get', 'avatar', 'text');
    if (is_user()) {
        $uid = intval($user[0]);
        if (!$avatar && $conf['users']['aupload']) {
            $uavatar = upload(1, $conf['users']['adirectory'], $conf['users']['atypefile'], $conf['users']['amaxsize'], $conf['name'], $conf['users']['awidth'], $conf['users']['aheight'], $uid);
            $avatar = (!$uavatar) ? $avatar : $uavatar;
        } elseif ($avatar) {
            $avatar = (preg_match("#(\.gif|\.png|\.jpg|\.jpeg)$#is", $avatar) && !preg_match("#(\b0\.gif\b|\b00\.gif\b)$#i", $avatar) && file_exists($conf['users']['adirectory'].'/default/'.$avatar)) ? 'default/'.$avatar : '';
        }
        if (!$stop && $avatar) {
            $avatar = filterText($avatar);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET avatar = :avatar WHERE id = :id', ['avatar' => $avatar, 'id' => $uid]);
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
            $uid = intval($user[0]);
            [$pass] = $db->getSqlRow($db->getSqlQuery('SELECT password FROM '.PREFIX_DB.'_users WHERE id = :id AND network = :network', ['id' => $uid, 'network' => '']));
            if (!empty($pass) && checkPassHash($oldpass, $pass)) {
                if ($newpass == $newpass2) {
                    $userinfo = getUserInfo();
                    $mail = $userinfo['email'];
                    $nick = $userinfo['name'];
                    $link = '<a href="'.$conf['homeurl'].'/index.php?name='.$conf['name'].'">'.$conf['homeurl'].'/index.php?name='.$conf['name'].'</a>';
                    $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
                    $message = str_replace('[text]', sprintf(_PASSESEND, $nick, $conf['sitename'], $nick, $newpass, $link), $conf['mtemp']);
                    addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
                    $newpass = getPassHash($newpass);
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :password WHERE id = :id', ['password' => $newpass, 'id' => $uid]);
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
