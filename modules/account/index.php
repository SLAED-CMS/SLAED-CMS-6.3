<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function account(): void {
    global $conf, $stop, $tpl;
    if (is_user()) {
        profil();
    } else {
        setHead(['title' => _USERREGLOGIN]);
        $captcha = getCaptcha('login');
        $cont = $tpl->getHtmlFrag('title', ['title' => _USERREGLOGIN, 'is_level_one' => true]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $fields = $tpl->getHtmlFrag('form-field-row', [
            'label' => _NICKNAME,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'user_name', 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'is_required' => true]),
        ]).$tpl->getHtmlFrag('form-field-row', [
            'label' => _PASSWORD,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'user_password', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD, 'is_required' => true]),
        ]);
        $after = $tpl->getHtmlFrag('block-content', [
            'is_form_submit' => true,
            'content' => $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']), 'title' => _PASSWORDLOST, 'label' => _PASSWORDLOST, 'is_footer_button' => true])
                .$tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'newuser']), 'title' => _REGNEWUSER, 'label' => _REGNEWUSER, 'is_footer_button' => true]),
        ]);
        if (!empty($conf['users']['network'])) {
            $after .= $tpl->getHtmlFrag('field-value', ['label' => _LOGINNETWORK, 'value_html' => getNetworks()]);
        }
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$fields,
            'captcha' => $captcha,
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'login', 'label' => _USERLOGIN]),
            'after_submit' => $after,
        ]);
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
    global $conf, $stop, $tpl;
    if (is_user()) {
        profil();
    } else {
        setHead(['title' => _REGNEWUSER]);
        if ($stop) {
            $cont = $tpl->getHtmlFrag('title', ['title' => _NEWUSERERROR, 'is_level_one' => true]);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        } else {
            $cont = $tpl->getHtmlFrag('title', ['title' => _REGNEWUSER, 'is_level_one' => true]);
        }
        if (!$conf['users']['reg']) {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOREG]);
        } else {
            $unkey = substr(getSecret('field'), 0, 32);
            $nick = getVar('post', $unkey, 'text');
            $nick = ($nick) ? filterText(substr($nick, 0, 25)) : '';
            $mail = getVar('post', 'mail', 'text');
            $mail = ($mail) ? filterText($mail) : '';
            $captcha = getCaptcha('register');
            $fields = $tpl->getHtmlFrag('form-field-row', [
                'label' => _NICKNAME,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $unkey, 'value_attr' => $nick, 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'is_required' => true]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label' => _EMAIL,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'mail', 'value_attr' => $mail, 'maxlength_num' => 255, 'placeholder_text' => _EMAIL, 'is_required' => true]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_html' => getTplTitleTip(_BLANKFORAUTO)._PASSWORD,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'user_password', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_html' => getTplTitleTip(_BLANKFORAUTO)._RETYPEPASSWORD,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'user_password2', 'maxlength_num' => 25, 'placeholder_text' => _RETYPEPASSWORD]),
            ]);
            if (!empty($conf['users']['rule'])) {
                $fields .= $tpl->getHtmlFrag('form-field-row', [
                    'label' => _RULES,
                    'field_html' => $tpl->getHtmlFrag('textarea', ['rows_num' => 10, 'cols_num' => 50, 'value_text' => $conf['users']['rules'], 'is_readonly' => true]),
                ]).$tpl->getHtmlFrag('form-field-row', [
                    'label' => _RULES_OK,
                    'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'rules', 'value_attr' => '1', 'is_required' => true]),
                ]);
            }
            $after = $tpl->getHtmlFrag('block-content', [
                'is_form_submit' => true,
                'content' => $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true])
                    .$tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']), 'title' => _PASSWORDLOST, 'label' => _PASSWORDLOST, 'is_footer_button' => true]),
            ]);
            if (!empty($conf['users']['network'])) {
                $after .= $tpl->getHtmlFrag('field-value', ['label' => _LOGINNETWORK, 'value_html' => getNetworks()]);
            }
            $cont .= $tpl->getHtmlPart('form-add', [
                'action' => 'index.php?name='.$conf['name'],
                'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$fields,
                'captcha' => $captcha,
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'finnewuser', 'label' => _NEWUSER]),
                'after_submit' => $after,
            ]);
        }
        echo $cont;
        setFoot();
    }
}

function finnewuser(): void {
    global $db, $conf, $stop, $tpl;
    if (!$conf['users']['reg']) {
        setHead(['title' => _NOREG]);
        echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOREG]);
        setFoot();
    } else {
        $unkey = substr(getSecret('field'), 0, 32);
        $nick = getVar('post', $unkey, 'name');
        $mail = getVar('post', 'mail', 'text');
        $rules = getVar('post', 'rules', 'num');
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
        checkuser($nick, $mail, $rules);
        $pass = htmlspecialchars(substr(getVar('post', 'user_password', 'text'), 0, 40));
        $pass2 = htmlspecialchars(substr(getVar('post', 'user_password2', 'text'), 0, 40));
        if (checkCaptcha('register')) $stop[] = _SECCODEINCOR;
        if ($pass == '' && $pass2 == '') {
            $pass = getRandomString($conf['users']['minpass']);
        } elseif ($pass != $pass2) {
            $stop[] = _ERROR_PASS;
        } elseif ($pass == $pass2 && strlen($pass) < $conf['users']['minpass']) {
            $stop[] = _CHARMIN.': '.$conf['users']['minpass'];
        }
        if (!$stop) {
            $check = md5(getRandomString(10));
            $time = time();
            $finishlink = $conf['homeurl'].'/index.php?name='.$conf['name'].'&op=activate&user='.urlencode($nick).'&num='.$check;
            $nick = filterText($nick);
            $mail = filterText($mail);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_users_temp (id, name, email, password, regdate, code, time) VALUES (NULL, :name, :email, :password, NOW(), :code, :time)',
                ['name' => $nick, 'email' => $mail, 'password' => $pass, 'code' => $check, 'time' => $time]
            );
            setHead(['title' => _ACCOUNTCREATED]);
            if ($conf['users']['nomail'] == 1) {
                $cont = $tpl->getHtmlFrag('title', ['title' => _ACCOUNTCREATED, 'is_level_one' => true]);
                $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _TOFINISHUSERN]);
                $fields = $tpl->getHtmlFrag('field-value', ['label' => _UNICKNAME, 'value_text' => $nick])
                    .$tpl->getHtmlFrag('field-value', ['label' => _UPASSWORD, 'value_text' => $pass]);
                $hidden = $tpl->getHtmlFrag('hidden', ['name_attr' => 'name', 'value_attr' => $conf['name']])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'activate'])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'user', 'value_attr' => urlencode($nick)])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'num', 'value_attr' => $check]);
                $cont .= $tpl->getHtmlPart('form-add', [
                    'action' => 'index.php',
                    'method' => 'get',
                    'no_enctype' => true,
                    'fields' => $fields,
                    'submit' => $hidden.$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _ACTIVATIONSUB]),
                ]);
            } else {
                $link = $tpl->getHtmlFrag('link', ['href' => $finishlink, 'title' => _ACTIVATIONSUB, 'label' => $finishlink, 'is_blank' => true]);
                $subject = $conf['sitename'].' - '._ACTIVATIONSUB;
                $message = str_replace('[text]', sprintf(_PASSFSEND, $mail, $conf['sitename'], $link, $nick, $pass).'<br><br>'._IFYOUDIDNOTASK, $conf['mtemp']);
                addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
                $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php', 'secs' => 30]);
                $brbr = '<br><br>';
                $cont = $tpl->getHtmlFrag('title', ['title' => _ACCOUNTCREATED, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _YOUAREREGISTERED.$brbr._FINISHUSERCONF.$brbr._THANKSUSER, 'meta' => $meta]);
            }
            echo $cont;
            setFoot();
        } else {
            newuser();
        }
    }
}

function network(): void {
    global $db, $conf, $tpl;
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
            $variants[] = substr($first, 0, 20).'-'.getRandomString(4);
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
                setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $nick, $pass, $story, $blockon, $theme]);
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', ['ip' => $uip, 'agent' => $uagent, 'id' => $uid]);
                login_report(0, 1, $nick, '');
                setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
            } else {
                $uemail = isset($ulog['email']) ? mb_strtolower($ulog['email']) : '';
                $upass = getPassHash(getRandomString(32));
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, email, avatar, regdate, password, ip, agent, network, block, warnings, field) VALUES (NULL, :name, :email, :avatar, NOW(), :password, :ip, :agent, :network, :block, :warnings, :field)', ['name' => $uname, 'email' => $uemail, 'avatar' => '', 'password' => $upass, 'ip' => $uip, 'agent' => $uagent, 'network' => $network, 'block' => '', 'warnings' => '', 'field' => '']);
                [$uid, $nick, $pass, $story, $blockon, $theme] = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE network = :network', ['network' => $network]));
                setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $nick, $pass, $story, $blockon, $theme]);
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
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 15]);
            echo $tpl->getHtmlFrag('title', ['title' => _ERRORINPUT, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERRORSESS, 'meta' => $meta]);
            setFoot();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function activate(): void {
    global $db, $conf, $locale, $tpl;
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
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, rank, email, avatar, regdate, password, lang, ip, agent, network, block, warnings, field) VALUES (NULL, :uname, :rank, :email, :avatar, :regdate, :pwd, :lang, :ip, :agent, :network, :block, :warnings, :field)', ['uname' => $nick, 'rank' => $rank, 'email' => $mail, 'avatar' => '', 'regdate' => $reg, 'pwd' => getPassHash($pass), 'lang' => $locale, 'ip' => $uip, 'agent' => $uagent, 'network' => '', 'block' => '', 'warnings' => '', 'field' => '']);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE name = :uname AND code = :cnum', ['uname' => $nick, 'cnum' => $check]);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = 0', ['uname' => $uip]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 15]);
            echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONYES, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ACTMSG, 'meta' => $meta]);
        } else {
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 15]);
            echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONERROR, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ACTERROR1, 'meta' => $meta]);
        }
    } else {
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 15]);
        echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONERROR, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ACTERROR2, 'meta' => $meta]);
    }
    setFoot();
}

function view(): void {
    global $db, $conf, $afile, $tpl, $prs;
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
            $userIpRaw = $ip;
            $seotitle  = $nick;
            $seoctitle = _PERSONALINFO;
            $seodesc   = cutstr(trim(strip_tags($prs->filterContent($sig ?? '', false, $conf['name']))), 160);
            $seoimg    = ($avatar) ? $conf['homeurl'].'/'.getUserAvatarUrl(['avatar' => $avatar]) : '';
            $seoauthor = $nick ?: ($uname ?: $conf['sitename']);
            setHead([
                'title' => $seotitle,
                'kind' => 'profile',
                'ctitle' => $seoctitle,
                'desc' => $seodesc,
                'img' => $seoimg,
                'author' => $seoauthor,
            ]);
            if (isAdmin()) {
                $id = [_ID, $uid];
                $regdate = [_REG, format_time($reg, _TIMESTRING)];
                $lastvisit = [_LAST_VISIT, format_time($last, _TIMESTRING)];
                $ip = [_IP, Geoip::getIpHtml($ip)];
                $agent = [_BROWSER, $agent];
            } else {
                $id = [_ID, _HIDE];
                $regdate = [_REG, format_time($reg)];
                $lastvisit = [_LAST_VISIT, format_time($last)];
                $geo = Geoip::getInfo($ip);
                $coun = (string)($geo['country_name'] ?: $geo['country']);
                $ip = [_COUNTRY, $coun ?: _NO_INFO];
                $agent = [_BROWSER, _HIDE];
            }
            $name = [_NICKNAME, $nick];
            $urank = ($rank) ? [_URANK, $rank] : [_URANK, ''];
            $mail = ((isAdmin() || $view) && $mail) ? [_EMAIL, htmlspecialchars($mail, ENT_QUOTES, 'UTF-8')] : [_EMAIL, _HIDE];
            $site = ($site) ? ((isAdmin() || is_user()) ? [_SITEURL, domain($site)] : [_SITEURL, _HIDE]) : [_SITEURL, _NO_INFO];
            $avatar = getUserAvatarUrl(['avatar' => $avatar]);
            $occup = ($occ) ? [_OCCUPATION, $occ] : [_OCCUPATION, _NO_INFO];
            $from = ($from) ? [_LOCALITYLANG, $from] : [_LOCALITYLANG, _NO_INFO];
            $inter = ($inter) ? [_INTERESTS, $inter] : [_INTERESTS, _NO_INFO];
            $sign = ((isAdmin() || is_user()) && $sig) ? $prs->filterContent($sig, false, $conf['name']) : '';
            $lang = ($lang) ? [_LANGUAGE, getLangName($lang)] : [_LANGUAGE, getLangName($conf['language'])];
            $points = ($conf['users']['point'] && $point) ? [_POINTS, $point] : [_POINTS, _NO_INFO];
            $warn = [_UWARNS, warnings($warn)];
            if ($birth) {
                preg_match('#([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})#', $birth, $datetime);
                $birth = [_BIRTHDAY, $datetime[3].'.'.$datetime[2].'.'.$datetime[1]];
            } else {
                $birth = [_BIRTHDAY, _NO_INFO];
            }
            $gender = [_GENDER, getGenderText($gender)];
            $rating = [_RATING, getRatingAsync(1, $uid, $conf['name'], $votes, $total, '', 1)];
            $field = ($field) ? getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]) : '';
            $sgroup = ($gname) ? [_SPEC_GROUP, $gname] : [_SPEC_GROUP, _NO];
            $rgroup = [];
            $uranks = '';
            $groupsText = _NO;
            if ($conf['users']['point'] && $point) {
                $result = $db->getSqlQuery('SELECT name, rank, color FROM '.PREFIX_DB."_groups WHERE points <= :points AND extra != '1' ORDER BY points ASC", ['points' => (int)$point]);
                $group = [];
                while([$guname, $gurank, $gcolor] = $db->getSqlRow($result)) {
                    $group[] = $guname;
                    $rgroup[] = $guname;
                    $uranks = $gurank;
                }
                $groupsText = ($group) ? implode(', ', $group) : _NO_INFO;
                $groups = [_USER_GROUPS, $groupsText];
                $grank = ($grank) ? $grank : $uranks;
            } else {
                $groups = [_USER_GROUPS, _NO];
            }
            $trank = ($gname) ? _GROUP.': '.$gname : ((is_array($rgroup)) ? _USER_GROUPS.': '.implode(', ', $rgroup) : _RANK);
            $rankImage = ($grank && file_exists(getThemeImagePath('ranks/'.$grank))) ? getThemeImagePath('ranks/'.$grank) : '';
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
            $acts = isAdmin() ? getActionMenu([
                ['href' => $afile.'.php?op=users_add&id='.$uid, 'title' => _FULLEDIT, 'icon_name' => 'pencil'],
                [
                    'href' => $afile.'.php?op=security_block&new_ip='.$userIpRaw,
                    'title' => _BANIPSENDER,
                    'icon_name' => 'shield-x',
                    'confirm_text' => _BANIPSENDER.' "'.$userIpRaw.'"?',
                ],
                [
                    'href' => $afile.'.php?op=users_del&id='.$uid,
                    'title' => _ONDELETE,
                    'icon_name' => 'trash',
                    'confirm_text' => _DELETE.' "'.$nick.'"?',
                ],
            ]) : '';
            $report = getTplTitleTip([
                ['label' => $id[0], 'value' => htmlspecialchars((string)$id[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                ['label' => $ip[0], 'value' => $ip[1]],
            ]);
            $uacts = [];
            if (($conf['privat']['act'] ?? 0) && !empty($nick)) {
                $uacts[] = ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'privat', 'uname' => urlencode($nick)]), 'title' => _SENDMES, 'icon_name' => 'envelope'];
            }
            if (is_user() && $uname == $nick) {
                $uacts[] = ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _ACCOUNT, 'icon_name' => 'person'];
            }
            $uacts[] = ['href' => '#', 'title' => _BACK, 'icon_name' => 'arrow-left', 'onclick_attr' => 'onclick="window.history.go(-1);return false;"'];
            echo $tpl->getHtmlPart('account-profile', [
                'has_sign' => !empty($sign),
                'has_field' => !empty($field),
                'has_rank_image' => !empty($rankImage),
                'has_special_group' => !empty($gname),
                'has_admin_actions' => isAdmin(),
                'report' => $report,
                'user_menu_html' => getActionMenu($uacts, true),
                'cname' => $name[0],
                'name' => $name[1],
                'curank' => $urank[0],
                'urank' => $urank[1],
                'cmail' => $mail[0],
                'mail' => $mail[1],
                'csite' => $site[0],
                'site' => $site[1],
                'avatar' => $avatar,
                'avatar_html' => $tpl->getHtmlFrag('image', ['src' => $avatar, 'alt' => $name[1], 'title' => $name[1], 'is_avatar' => true]),
                'cregdate' => $regdate[0],
                'regdate' => $regdate[1],
                'coccup' => $occup[0],
                'occup' => $occup[1],
                'cfrom' => $from[0],
                'from' => $from[1],
                'cinter' => $inter[0],
                'inter' => $inter[1],
                'sign' => $sign,
                'clastvisit' => $lastvisit[0],
                'lastvisit' => $lastvisit[1],
                'clang' => $lang[0],
                'lang' => $lang[1],
                'cpoints' => $points[0],
                'points' => $points[1],
                'cwarn' => $warn[0],
                'warn' => $warn[1],
                'cbirth' => $birth[0],
                'birth' => $birth[1],
                'cgender' => $gender[0],
                'gender' => $gender[1],
                'crating' => $rating[0],
                'rating' => $rating[1],
                'field' => $field,
                'cagent' => $agent[0],
                'agent' => $agent[1],
                'csgroup' => $sgroup[0],
                'sgroup' => $sgroup[1],
                'cgroups' => $groups[0],
                'groups' => $groups[1],
                'rank_src' => $rankImage,
                'rank_alt' => $trank,
                'admin_actions_html' => $acts,
                'tabs' => $tabs,
                'info' => _PERSONALINFO,
            ]);
            setFoot();
        } else {
            setHead(['title' => _USERNOEXIST]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php', 'secs' => 3]);
            echo $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _USERNOEXIST, 'meta' => $meta]);
            setFoot();
        }
    } else {
        setHead(['title' => _MODULEUSERS]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php', 'secs' => 15]);
        echo $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MODULEUSERS, 'meta' => $meta]);
        setFoot();
    }
}

function profil(): void {
    global $conf, $user, $tpl;
    if (is_user()) {
        setHead(['title' => _THISISYOURPAGE]);
        $cont = $tpl->getHtmlFrag('title', ['title' => _THISISYOURPAGE, 'is_level_one' => true]);
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
            $text[] = $tpl->getHtmlPart('form-add', [
                'action' => 'index.php?name='.$conf['name'],
                'fields' => $tpl->getHtmlFrag('form-field-row', [
                    'label' => _SELECTASITE,
                    'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'url', 'options_html' => rss_select()]),
                ]),
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _OK]),
            ])
            .$tpl->getHtmlPart('form-add', [
                'action' => 'index.php?name='.$conf['name'],
                'fields' => $tpl->getHtmlFrag('form-field-row', [
                    'label' => _ORTYPEURL,
                    'hide_label' => true,
                    'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'url', 'value_attr' => $link, 'maxlength_num' => 200, 'placeholder_text' => _ORTYPEURL]),
                ]),
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _OK]),
            ])
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
    global $db, $conf, $user, $tpl, $prs;
    $uid   = (int)$uid;
    $num   = getUserNews(25);
    $limit = (int)$num;
    $rows = [];
    $handled = true;
    $lastRow = static function (string $time, string $href, string $title) use ($tpl): array {
        return ['cells' => [
            ['content_html' => $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY.': '.format_time($time, _TIMESTRING), 'text' => format_time($time)])],
            ['content_html' => $tpl->getHtmlFrag('link', ['href' => $href, 'title' => $title, 'label' => $title, 'is_last' => true])],
        ]];
    };
    if ($modul == 'comm') {
        $result = $db->getSqlQuery('SELECT id, cid, modul, time, body FROM '.PREFIX_DB."_comment WHERE uid = :user_id AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $cid, $commentModul, $date, $comment] = $row;
            $comment = cutstr(str_replace([_QUOTE, _CODE], '', filterText($prs->filterContent($comment, false, $conf['name']))), 70);
            $rows[] = $lastRow($date, getSeoUrl(['name' => $commentModul, 'op' => 'view', 'id' => $cid]).'#'.$id, $comment);
        }
    } elseif ($modul == 'faq') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_faq WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id, $title);
        }
    } elseif ($modul == 'files') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_files WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]).'#'.$id, $title);
        }
    } elseif ($modul == 'forum') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_forum WHERE uid = :user_id AND pid = '0' AND time <= NOW() AND status > '1' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]), $title);
        }
    } elseif ($modul == 'jokes') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_jokes WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, 'index.php?name=jokes#'.$id, $title);
        }
    } elseif ($modul == 'links') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_links WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]), $title);
        }
    } elseif ($modul == 'media') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_media WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]), $title);
        }
    } elseif ($modul == 'news') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_news WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]), $title);
        }
    } elseif ($modul == 'pages') {
        $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_pages WHERE uid = :user_id AND time <= NOW() AND status != '0' ORDER BY id DESC LIMIT 0,".$limit, ['user_id' => $uid]);
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time] = $row;
            $rows[] = $lastRow($time, getSeoUrl(['name' => $modul, 'op' => 'view', 'id' => $id, 'title' => $title]), $title);
        }
    } else {
        $handled = false;
    }
    return $handled ? $tpl->getHtmlPart('content-list', [
        'rows' => $rows,
        'table_open' => ['open' => true, 'is_amount' => true],
        'table_close' => [],
        'empty_alert' => ['is_warn' => false, 'text' => _NO_INFO],
    ]) : '';
}

function privat(): void {
    global $conf, $tpl;
    if (is_user() && ($conf['privat']['act'] ?? 0)) {
        #$typ = (getVar('get', 'uname', 'text')) ? 3 : 0;
        setHead([
            'title' => _PRIVAT,
        ]);
        $title = [
            $tpl->getHtmlFrag('span', ['target_id' => 'prmessin', 'request' => 'go=1&op=getPrivateMessageView&typ=1&token='.getSiteToken(), 'text' => _PRIN, 'is_htmx' => true]),
            $tpl->getHtmlFrag('span', ['target_id' => 'prmessou', 'request' => 'go=1&op=getPrivateMessageView&typ=2&token='.getSiteToken(), 'text' => _PROUT, 'is_htmx' => true]),
            $tpl->getHtmlFrag('span', ['target_id' => 'prmesssa', 'request' => 'go=1&op=getPrivateMessageView&typ=3&token='.getSiteToken(), 'text' => _PRSAVE, 'is_htmx' => true]),
            _SEND
        ];
        $text = [
            $tpl->getHtmlFrag('block-content', ['id' => 'repprmessin', 'content' => getPrivateMessageView(1, 0, 0, 1)]),
            $tpl->getHtmlFrag('block-content', ['id' => 'repprmessou', 'content' => getPrivateMessageView(1, 0, 0, 2)]),
            $tpl->getHtmlFrag('block-content', ['id' => 'repprmesssa', 'content' => getPrivateMessageView(1, 0, 0, 3)]),
            $tpl->getHtmlFrag('block-content', ['id' => 'repprmessfo', 'content' => getPrivateMessageView(1, 0, 0, 4)])
        ];
        $cont = $tpl->getHtmlFrag('title', ['title' => _PRIVAT, 'is_level_one' => true]).getUserNav().getNaviTabs(0, 'tab', $title, $text);
        echo $cont;
        setFoot();
    } else {
        account();
    }
}

function favorites(): void {
    global $conf, $tpl;
    if (is_user() && ($conf['favorites']['favact'] ?? 0)) {
        setHead([
            'title' => _FAVORITES,
        ]);
        echo $tpl->getHtmlFrag('title', ['title' => _FAVORITES, 'is_level_one' => true]).getUserNav().$tpl->getHtmlFrag('block-content', ['id' => 'repfavorliste', 'content' => getFavoriteList(1)]);
        setFoot();
    } else {
        account();
    }
}

function passlost(): void {
    global $conf, $stop, $tpl;
    $code = getVar('get', 'code', 'text');
    $code = ($code) ? substr($code, 0, 10) : false;
    $email = getVar('get', 'email', 'text');
    if ($email) checkemail($email);
    if (!is_user()) {
        setHead([
            'title' => _PASSWORDLOST,
        ]);
        $cont = $tpl->getHtmlFrag('title', ['title' => _PASSWORDLOST, 'is_level_one' => true]);
        $info = ($email) ? _PASSLOSP : _PASSLOSC;
        $send = ($email) ? _SENDPASSWORD : _SEND;
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
        $fields = $tpl->getHtmlFrag('form-field-row', [
            'label' => _EMAIL,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'email', 'value_attr' => $email, 'maxlength_num' => 255, 'placeholder_text' => _EMAIL, 'is_required' => true]),
        ]);
        if (!empty($email)) {
            $fields .= $tpl->getHtmlFrag('form-field-row', [
                'label' => _CONFIRMATIONCODE,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'code', 'value_attr' => $code ?: '', 'maxlength_num' => 10, 'placeholder_text' => _CONFIRMATIONCODE, 'is_required' => true]),
            ]);
        }
        $after = $tpl->getHtmlFrag('block-content', [
            'is_form_submit' => true,
            'content' => $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'], 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true])
                .$tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&op=newuser', 'title' => _REGNEWUSER, 'label' => _REGNEWUSER, 'is_footer_button' => true]),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$fields,
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'passmail', 'label' => $send]),
            'after_submit' => $after,
        ]);
        echo $cont;
        setFoot();
    } elseif (is_user()) {
        profil();
    }
}

function passmail(): void {
    global $db, $conf, $stop, $tpl;
    $email = getVar('post', 'email', 'text');
    $code = getVar('post', 'code', 'text');
    $code = ($code) ? substr($code, 0, 10) : false;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
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
            $newpass = getRandomString($conf['users']['minpass']);
            $chash = getPassHash($newpass);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :password WHERE email = :email', ['password' => $chash, 'email' => $email]);
            $link = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'title' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'label_html' => $conf['homeurl'].'/index.php?name='.$conf['name']]);
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
            $message = str_replace('[text]', sprintf(_PASSSEND, $nick, $conf['sitename'], $nick, $newpass, $link), $conf['mtemp']);
            addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
            setHead([
                'title' => _PASSWORDLOST,
            ]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo $tpl->getHtmlFrag('title', ['title' => _PASSWORDLOST, 'is_level_one' => true]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _USERPASSWORD.' '.$nick.' '._MAILED, 'meta' => $meta]);
            setFoot();
        } else {
            $link = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'].'/index.php?name='.$conf['name'].'&op=passlost&code='.$subpass.'&email='.$email, 'title' => $conf['homeurl'].'/index.php?name='.$conf['name'].'&op=passlost&code='.$subpass.'&email='.$email, 'label_html' => $conf['homeurl'].'/index.php?name='.$conf['name'].'&op=passlost&code='.$subpass.'&email='.$email]);
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
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    if (checkCaptcha('login')) $stop[] = _SECCODEINCOR;
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
        setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $nick, $pass, $story, $blockon, $theme]);
        $uip = getIp();
        $uagent = getAgent();
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', ['ip' => $uip, 'agent' => $uagent, 'id' => $uid]);
        Captcha::clearLoginFailures('user');
        login_report(0, 1, $uname, '');
        setRedirect('index.php?name='.$conf['name'].'&op=profil', true);
    } else {
        Captcha::registerLoginFailure('user');
        login_report(0, 0, $uname, $upass);
        account();
    }
}

function logout(): void {
    global $db, $user;
    $nick = (is_array($user) && isset($user[1])) ? htmlspecialchars(substr((string)$user[1], 0, 25)) : '';
    deletePrivatCounts();
    setCookiesDelete('account');
    if ($nick !== '') $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $nick, 'guest' => 2]);
    unset($user);
    setRedirect('index.php', true);
}

function edithome(): void {
    global $db, $user, $conf, $stop, $tpl;
    if (is_user()) {
        setHead([
            'title' => _CHANGE,
        ]);
        $userinfo = getUserInfo();
        $birthday = trim((string)($userinfo['birthday'] ?? ''));
        if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            $birthday = '';
        }
        $userinfo['theme'] = (!$userinfo['theme']) ? $conf['theme'] : $userinfo['theme'];
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]) : '';
        $story = '';
        if ($conf['users']['news'] == 1) {
            $xusnum = 3;
            while ($xusnum <= 20) {
                $story .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$xusnum, 'label_text' => (string)$xusnum, 'is_selected' => $xusnum == $userinfo['storynum']]);
                $xusnum++;
            }
        }
        $theme = '';
        $tcount = 0;
        if ($conf['users']['theme']) {
            $list = scandir(BASE_DIR.'/templates');
            foreach ($list ?: [] as $file) {
                if ($file === '.' || $file === '..' || $file === 'admin') continue;
                if (!is_dir(BASE_DIR.'/templates/'.$file) || !checkThemeAssets($file)) continue;
                $theme .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$file, 'label_text' => (string)$file, 'is_selected' => $file == $userinfo['theme']]);
                $tcount++;
            }
        }
        $genderOptions = '';
        foreach ([_NO_INFO, _MAN, _WOMAN] as $key => $val) {
            $genderOptions .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$key,
                'label_text' => $val,
                'is_selected' => $key == (int)$userinfo['gender'],
            ]);
        }
        $fields = $tpl->getHtmlFrag('field-value', ['label' => _IP, 'value_text' => $userinfo['ip']])
            .$tpl->getHtmlFrag('field-value', ['label' => _REG, 'value_text' => format_time($userinfo['regdate'])]);
        if (!empty($conf['users']['point'])) {
            $fields .= $tpl->getHtmlFrag('field-value', ['label' => _POINTS, 'value_text' => $userinfo['points']]);
        }
        $fields .= $tpl->getHtmlFrag('field-value', ['label' => _YOURNAME, 'value_text' => $userinfo['name']])
            .$tpl->getHtmlFrag('form-field-row', ['label' => _BIRTHDAY, 'field_html' => getTplAddDateTime(['name' => 'user_birthday', 'time' => $birthday, 'with' => false, 'max' => 10])])
            .$tpl->getHtmlFrag('form-field-row', ['label' => _GENDER, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'gender', 'is_account' => true, 'options_html' => $genderOptions])])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _YOUREMAIL,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'mail',
                    'value_attr' => $userinfo['email'],
                    'maxlength_num' => 60,
                    'placeholder_text' => _YOUREMAIL,
                    'is_required' => true,
                ]),
            ])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _SITEURL,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'site',
                    'value_attr' => $userinfo['website'],
                    'maxlength_num' => 100,
                    'placeholder_text' => _SITEURL,
                ]),
            ])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _OCCUPATION,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'occ',
                    'value_attr' => $userinfo['occ'],
                    'maxlength_num' => 100,
                    'placeholder_text' => _OCCUPATION,
                ]),
            ])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _LOCALITYLANG,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'from',
                    'value_attr' => $userinfo['origin'],
                    'maxlength_num' => 100,
                    'placeholder_text' => _LOCALITYLANG,
                ]),
            ])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _INTERESTS,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'inter',
                    'value_attr' => $userinfo['interest'],
                    'maxlength_num' => 150,
                    'placeholder_text' => _INTERESTS,
                ]),
            ])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _SIGNATURE,
                'hide_label' => true,
                'field_html' => getTplTitleTip(_SIGNATURE_TEXT).getTplTextarea([
                    'id' => '1',
                    'name' => 'sig',
                    'value' => $userinfo['sig'],
                    'mod' => $conf['name'],
                    'rows' => '5',
                    'placeholder' => _SIGNATURE,
                    'required' => '0',
                ]),
            ])
            .getTplFieldsIn(['field' => $userinfo['field'], 'mod' => $conf['name']]);
        $submitExtra = $tpl->getHtmlFrag('hidden', ['name_attr' => 'user_name', 'value_attr' => $userinfo['name']]);
        if ($conf['users']['news'] == 1) {
            $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _C_12, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'story', 'options_html' => $story])]);
        } else {
            $submitExtra .= $tpl->getHtmlFrag('hidden', ['name_attr' => 'story', 'value_attr' => $conf['news']['num'] ?? 0]);
        }
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _RNEWSLETTER, 'field_html' => getTplRadioGroup(['name' => 'news', 'value' => ((string)$userinfo['newslet'] === '0') ? '0' : '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])]);
        if (is_active('forum')) {
            $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _FSMAIL, 'field_html' => getTplRadioGroup(['name' => 'fsmail', 'value' => ((string)$userinfo['fsmail'] === '0') ? '0' : '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])]);
        }
        if (!empty($conf['privat']['act'])) {
            $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _PSMAIL, 'field_html' => getTplRadioGroup(['name' => 'psmail', 'value' => ((string)$userinfo['psmail'] === '0') ? '0' : '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])]);
        }
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _ALLOWUSERS, 'field_html' => getTplRadioGroup(['name' => 'view', 'value' => ((string)$userinfo['viewmail'] === '0') ? '0' : '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])])
            .$tpl->getHtmlFrag('form-field-row', ['label' => _ACTIVATEPERSONAL, 'field_html' => getTplRadioGroup(['name' => 'blockon', 'value' => ((string)$userinfo['blockon'] === '0') ? '0' : '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _MENUCONF,
                'hide_label' => true,
                'field_html' => getTplTitleTip(_MENUINFO).getTplTextarea([
                    'id' => '2',
                    'name' => 'block',
                    'value' => $userinfo['block'],
                    'mod' => $conf['name'],
                    'rows' => '10',
                    'placeholder' => _MENUCONF,
                    'required' => '0',
                ]),
            ]);
        if ($tcount > 1) {
            $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _THEME, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'theme', 'options_html' => $theme])]);
        }
        $change = $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$fields,
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'extra' => $submitExtra, 'op' => 'savehome', 'label' => _SAVECHANGES]),
        ]);
        $avatar = getUserAvatarUrl($userinfo);
        $asetup = $tpl->getHtmlPart('content-list', [
            'rows' => [[
                'cells' => [
                    ['primary_text' => _AVATAR, 'secondary_text' => sprintf(_AVATARINFO, $conf['users']['awidth'], $conf['users']['aheight'], filterSize($conf['users']['amaxsize']))],
                    ['img_src' => $avatar, 'img_alt' => _AVATAR, 'img_title' => _AVATAR, 'is_avatar' => true],
                ],
            ]],
            'table_open' => ['open' => true, 'is_form' => true],
            'table_close' => [],
        ]);
        if ($conf['users']['aupload']) {
            $asetup .= $tpl->getHtmlPart('form-add', [
                'action' => 'index.php?name='.$conf['name'],
                'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$tpl->getHtmlFrag('form-field-row', [
                    'label' => _AVATAR_USER,
                    'field_html' => $tpl->getHtmlFrag('file-input', ['name_attr' => 'userfile']),
                ]),
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'saveavatar', 'label' => _UPLOAD]),
            ]);
        }
        $a = 6;
        $i = 1;
        $aset = [];
        $arows = [];
        $adir = 'templates/'.getTheme().'/images/avatars/presets';
        $list = scandir($adir);
        foreach ($list ?: [] as $file) {
            if (preg_match("#\.(gif|png|jpe?g|svg)$#is", $file)) {
                $filename = str_replace('_', ' ', preg_replace("/^(.*)\..*$/", '\\1', $file));
                $aset[] = [
                    'href' => 'index.php?name='.$conf['name'].'&op=saveavatar&avatar='.$file,
                    'title' => _AVATARSAVE.' '._ID.' '.$filename,
                    'is_avatar_link' => true,
                    'img_src' => $adir.'/'.$file,
                    'img_alt' => _AVATARSAVE.' '._ID.' '.$filename,
                    'img_title' => _AVATARSAVE.' '._ID.' '.$filename,
                    'is_avatar' => true,
                ];
                if ($i % $a == 0) {
                    $arows[] = ['cells' => $aset];
                    $aset = [];
                }
                $i++;
            }
        }
        if ($aset) $arows[] = ['cells' => $aset];
        if ($i >= 1) {
            $asetup .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _AVATARSELECT])
                .$tpl->getHtmlPart('content-list', [
                    'rows' => $arows,
                    'table_open' => ['open' => true, 'is_form' => true, 'is_avatar_grid' => true],
                    'table_close' => [],
                    'empty_alert' => ['is_warn' => false, 'text' => _NO_INFO],
                ]);
        }
        $uid = (int)$user[0];
        [$network] = $db->getSqlRow($db->getSqlQuery('SELECT network FROM '.PREFIX_DB.'_users WHERE id = :user_id', ['user_id' => $uid]));
        if (empty($network)) {
            $fields = $tpl->getHtmlFrag('form-field-row', [
                'label' => _PASSNEW,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'newpass', 'maxlength_num' => 25, 'placeholder_text' => _PASSNEW, 'is_required' => true]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label' => _PASSNEW2,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'newpass2', 'maxlength_num' => 25, 'placeholder_text' => _PASSNEW2, 'is_required' => true]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label' => _PASSOLD,
                'hide_label' => true,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'oldpass', 'maxlength_num' => 25, 'placeholder_text' => _PASSOLD, 'is_required' => true]),
            ]);
            $psetup = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PASSTEXT])
                .$tpl->getHtmlPart('form-add', [
                'action' => 'index.php?name='.$conf['name'],
                'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$fields,
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'savepass', 'label' => _SAVECHANGES]),
            ]);
        } else {
            $psetup = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NETWORKPASS]);
        }
        echo $tpl->getHtmlFrag('title', ['title' => _CHANGE, 'is_level_one' => true]).getUserNav().$cont.getNaviTabs(0, 'tab', [_CHANGE, _AVATARSETUP, _PASSSETUP], [$change, $asetup, $psetup]);
        setFoot();
    } else {
        account();
    }
}

function savehome(): void {
    global $db, $user, $conf, $stop;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $mail = getVar('post', 'mail', 'text');
    checkemail($mail);
    if (!$stop) {
        $uid = (int)$user[0];
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
            if ($theme !== '' && !checkThemeAssets($theme)) $theme = '';
            $news = getVar('post', 'news', 'num');
            $fsmail = getVar('post', 'fsmail', 'num');
            $psmail = getVar('post', 'psmail', 'num');
            $birth = getVar('req', 'user_birthday', 'date');
            $gender = getVar('post', 'gender', 'num');
            $field = getVar('post', 'field', 'field');
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET email = :email, website = :website, viewmail = :viewmail, occ = :occ, origin = :origin, interest = :interest, sig = :sig, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newslet, fsmail = :fsmail, psmail = :psmail, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', ['email' => $mail, 'website' => $site, 'viewmail' => $view, 'occ' => $occ, 'origin' => $from, 'interest' => $inter, 'sig' => $sig, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newslet' => $news, 'fsmail' => $fsmail, 'psmail' => $psmail, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid]);
            $theme = $theme ?: ($conf['theme'] ?? '');
            setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $name, $pass, $story, $blockon, $theme]);
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
    if (getVar('post', 'op', 'word') == 'saveavatar' && !checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    if (is_user()) {
        $uid = (int)$user[0];
        if (!$avatar && $conf['users']['aupload']) {
            $uavatar = upload(1, $conf['users']['adirectory'], $conf['users']['atypefile'], $conf['users']['amaxsize'], $conf['name'], $conf['users']['awidth'], $conf['users']['aheight'], $uid);
            $avatar = (!$uavatar) ? $avatar : $uavatar;
        } elseif ($avatar) {
            $avatar = basename($avatar);
            $avatar = (preg_match("#\.(gif|png|jpe?g|svg)$#is", $avatar) && file_exists('templates/'.getTheme().'/images/avatars/presets/'.$avatar)) ? 'presets/'.$avatar : '';
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
    global $user, $db, $conf, $stop, $tpl;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $newpass = getVar('post', 'newpass', 'text', false);
    $newpass2 = getVar('post', 'newpass2', 'text', false);
    $oldpass = getVar('post', 'oldpass', 'text', false);
    if (is_user() && $oldpass && $newpass && $newpass2) {
        if (strlen($newpass) >= $conf['users']['minpass']) {
            $uid = (int)$user[0];
            [$pass] = $db->getSqlRow($db->getSqlQuery('SELECT password FROM '.PREFIX_DB.'_users WHERE id = :id AND network = :network', ['id' => $uid, 'network' => '']));
            if (!empty($pass) && checkPassHash($oldpass, $pass)) {
                if ($newpass == $newpass2) {
                    $userinfo = getUserInfo();
                    $mail = $userinfo['email'];
                    $nick = $userinfo['name'];
                    $link = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'title' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'label_html' => $conf['homeurl'].'/index.php?name='.$conf['name']]);
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

switch ($op) {
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
