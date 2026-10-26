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
            'label_for' => 'f-user-name',
            'label' => _NICKNAME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'user_name',
                'input_id' => 'f-user-name',
                'maxlength_num' => 25,
                'placeholder_text' => _NICKNAME,
                'is_required' => true,
            ]),
        ]).$tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-user-password',
            'label' => _PASSWORD,
            'field_html' => $tpl->getHtmlFrag('input', [
                'name_attr' => 'user_password',
                'input_id' => 'f-user-password',
                'maxlength_num' => 25,
                'placeholder_text' => _PASSWORD,
                'is_required' => true,
            ]),
        ]);
        $after = $tpl->getHtmlFrag('block-content', [
            'is_form_submit' => true,
            'content' => $tpl->getHtmlFrag('link', [
                'href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']),
                'title' => _PASSWORDLOST,
                'label' => _PASSWORDLOST,
                'is_footer_button' => true,
            ])
                .$tpl->getHtmlFrag('link', [
                    'href' => getSeoUrl(['name' => $conf['name'], 'op' => 'newuser']),
                    'title' => _REGNEWUSER,
                    'label' => _REGNEWUSER,
                    'is_footer_button' => true,
                ]),
        ]);
        $after .= Oauth::getButtons();
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => getSeoUrl(['name' => $conf['name']]),
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
            $nick = ($nick) ? filterCutText(filterText($nick), 25) : '';
            $mail = getVar('post', 'mail', 'text');
            $mail = ($mail) ? filterText($mail) : '';
            $captcha = getCaptcha('register');
            $fields = $tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-'.$unkey,
                'label' => _NICKNAME,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => $unkey,
                    'input_id' => 'f-'.$unkey,
                    'value_attr' => $nick,
                    'maxlength_num' => 25,
                    'placeholder_text' => _NICKNAME,
                    'is_required' => true,
                ]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-mail',
                'label' => _EMAIL,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'mail',
                    'input_id' => 'f-mail',
                    'value_attr' => $mail,
                    'maxlength_num' => 255,
                    'placeholder_text' => _EMAIL,
                    'is_required' => true,
                ]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-user-password',
                'label_html' => getTplTitleTip(_BLANKFORAUTO)._PASSWORD,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'user_password',
                    'input_id' => 'f-user-password',
                    'maxlength_num' => 25,
                    'placeholder_text' => _PASSWORD,
                ]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_html' => getTplTitleTip(_BLANKFORAUTO)._RETYPEPASSWORD,
                'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'user_password2', 'maxlength_num' => 25, 'placeholder_text' => _RETYPEPASSWORD]),
            ]);
            if (!empty($conf['users']['rule'])) {
                $fields .= $tpl->getHtmlFrag('form-field-row', [
                    'label' => _RULES,
                    'field_html' => $tpl->getHtmlFrag('textarea', ['rows_num' => 10, 'cols_num' => 50, 'value_text' => $conf['users']['rules'], 'is_readonly' => true]),
                ]).$tpl->getHtmlFrag('form-field-row', [
                    'label_for' => 'f-rules',
                    'label' => _RULES_OK,
                    'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'rules', 'input_id' => 'f-rules', 'value_attr' => '1', 'is_required' => true]),
                ]);
            }
            $after = $tpl->getHtmlFrag('block-content', [
                'is_form_submit' => true,
                'content' => $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true])
                    .$tpl->getHtmlFrag('link', [
                        'href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']),
                        'title' => _PASSWORDLOST,
                        'label' => _PASSWORDLOST,
                        'is_footer_button' => true,
                    ]),
            ]);
            $after .= Oauth::getButtons();
            $cont .= $tpl->getHtmlPart('form-add', [
                'action' => getSeoUrl(['name' => $conf['name']]),
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
    global $db, $conf, $stop, $tpl, $mailer;
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
            $finishlink = getPublicUrl(['name' => $conf['name'], 'op' => 'activate', 'user' => urlencode($nick), 'num' => $check]);
            $mail = filterText($mail);
            $done = $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_users_temp (id, name, email, password, regdate, code) VALUES (NULL, :name, :email, :password, NOW(), :code)',
                ['name' => $nick, 'email' => $mail, 'password' => getPassHash($pass), 'code' => $check]
            );
            if (!$done) $stop[] = match ($db->getSqlTwin()) { 'email' => _ERROR_EMAIL, 'name' => _NICKTAKEN, default => _ERROR };
        }
        if (!$stop) {
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
                    'action' => HOME_PATH.'index.php',
                    'method' => 'get',
                    'no_enctype' => true,
                    'fields' => $fields,
                    'submit' => $hidden.$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _ACTIVATIONSUB]),
                ]);
            } else {
                $link = $tpl->getHtmlFrag('link', ['href' => $finishlink, 'title' => _ACTIVATIONSUB, 'label' => $finishlink, 'is_blank' => true]);
                $subject = $conf['sitename'].' - '._ACTIVATIONSUB;
                $message = getMailFrame(getTplLines([sprintf(_PASSFSEND, $mail, $conf['sitename'], $link, $nick, $pass), _IFYOUDIDNOTASK], true, true));
                $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
                $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => HOME_PATH, 'secs' => 30]);
                $cont = $tpl->getHtmlFrag('title', ['title' => _ACCOUNTCREATED, 'is_level_one' => true])
                    .$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => getTplLines([_YOUAREREGISTERED, _FINISHUSERCONF, _THANKSUSER], true, true), 'meta' => $meta]);
            }
            echo $cont;
            setFoot();
        } else {
            newuser();
        }
    }
}

function activate(): void {
    global $db, $conf, $locale, $tpl, $pnt;
    $user = getVar('get', 'user', 'name', '');
    $num = getVar('get', 'num', 'text', '');
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE regdate < NOW() - INTERVAL 1 DAY');
    $result = $db->getSqlQuery(
        'SELECT name, email, password, regdate, code FROM '.PREFIX_DB.'_users_temp WHERE name = :uname AND code = :cnum',
        ['uname' => $user, 'cnum' => $num]
    );
    setHead(['title' => _ACTIVATIONSUB]);
    if ($db->getSqlRowCount($result) === 1) {
        [$nick, $mail, $pass, $reg, $check] = $db->getSqlRow($result);
        if ($num == $check) {
            $uip = getIp();
            $uagent = filterCutText(getAgent(), 255);
            $rank = '';
            $open = $db->setSqlBegin();
            $done = $open && $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, `rank`, email, avatar, regdate, password, lang, ip, agent, sig, block, warnings, field)'
                .' VALUES (NULL, :uname, :rank, :email, :avatar, :regdate, :pwd, :lang, :ip, :agent, :sig, :block, :warnings, :field)', ['uname' => $nick, 'rank' => $rank,
                'email' => $mail, 'avatar' => '', 'regdate' => $reg, 'pwd' => str_starts_with($pass, '$2') ? $pass : getPassHash($pass), 'lang' => $locale, 'ip' => $uip,
                'agent' => $uagent, 'sig' => '', 'block' => '', 'warnings' => '', 'field' => '']) !== false;
            $twin = $done ? '' : $db->getSqlTwin();
            $nuid = $done ? intval($db->getSqlLastId()) : 0;
            $done = $done && (new Route($db))->addRoute('user', $nuid, 'users', $nick) !== '' && $db->setSqlCommit();
            if ($open && !$done) $db->setSqlRollback();
            if (!$done) {
                $text = match ($twin) { 'email' => _ERROR_EMAIL, 'name' => _NICKTAKEN, default => _ERROR };
                $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => getSeoUrl(['name' => $conf['name']]), 'secs' => 15]);
                echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONERROR, 'is_level_one' => true])
                    .$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $text, 'meta' => $meta]);
                setFoot();
                return;
            }
            if ($nuid) $pnt->addEvent('register', 'account', 'user:'.$nuid, $nuid);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE name = :uname AND code = :cnum', ['uname' => $nick, 'cnum' => $check]);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = 0', ['uname' => $uip]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => getSeoUrl(['name' => $conf['name']]), 'secs' => 15]);
            echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONYES, 'is_level_one' => true])
                .$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_ACTMSG, getSeoUrl(['name' => $conf['name']])), 'meta' => $meta]);
        } else {
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => getSeoUrl(['name' => $conf['name']]), 'secs' => 15]);
            echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONERROR, 'is_level_one' => true])
                .$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => sprintf(_ACTERROR1, getSeoUrl(['name' => $conf['name']])), 'meta' => $meta]);
        }
    } else {
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => getSeoUrl(['name' => $conf['name']]), 'secs' => 15]);
        echo $tpl->getHtmlFrag('title', ['title' => _ACTIVATIONERROR, 'is_level_one' => true])
            .$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => sprintf(_ACTERROR2, getSeoUrl(['name' => $conf['name']])), 'meta' => $meta]);
    }
    setFoot();
}

function view(): void {
    global $db, $conf, $afile, $tpl, $com, $pnt;
    if ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || isAdmin()) {
        $uname = htmlspecialchars(substr(urldecode(getVar('get', 'uname', 'text')), 0, 25));
        $params = [];
        if ($uname) {
            $where = 'u.name = :uname AND BINARY u.name = :exact';
            $params = ['uname' => $uname, 'exact' => $uname];
        } else {
            $where = 'u.id = :uid';
            $params['uid'] = getVar('get', 'id', 'num');
        }
        $result = $db->getSqlQuery(
            'SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.occ, u.origin, u.interest, u.sig, u.viewmail, u.lastvis, u.lang, u.points, u.ip, u.warnings,'
                .' u.birthday, u.gender, u.votes, u.tvotes, u.field, u.agent, g.name, g.rank, g.color, (SELECT COUNT(s.id) FROM '.PREFIX_DB.'_session AS s WHERE s.uname = u.name),'
                .' r.path'
                .' FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.grp)'
                .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'user\' AND r.oid = u.id AND r.cur = 1 WHERE '.$where,
            $params
        );
        if ($db->getSqlRowCount($result) > 0) {
            [
                $uid,
                $nick,
                $rank,
                $mail,
                $site,
                $avatar,
                $reg,
                $occ,
                $from,
                $inter,
                $sig,
                $view,
                $last,
                $lang,
                $point,
                $ip,
                $warn,
                $birth,
                $gender,
                $votes,
                $total,
                $field,
                $agent,
                $gname,
                $grank,
                $gcolor,
                $ison,
                $route
            ] = $db->getSqlRow($result);
            setSeoMove(['route' => $route]);
            $userIpRaw = $ip;
            $seotitle  = $nick;
            $seoctitle = _PERSONALINFO;
            $seodesc   = cutstr(trim(strip_tags(getUserSign((string)($sig ?? ''), intval($uid)))), 160);
            $seoimg    = ($avatar) ? getPublicUrl(getUserAvatarUrl(['avatar' => $avatar])) : '';
            $seoauthor = $nick ?: ($uname ?: $conf['sitename']);
            setHead([
                'title' => $seotitle,
                'route' => $route,
                'kind' => 'profile',
                'ctitle' => $seoctitle,
                'desc' => $seodesc,
                'img' => $seoimg,
                'author' => $seoauthor,
            ]);
            $adm = isAdmin();
            $mkrow = fn(string $icon, string $label, string $value, string $html = '', bool $priv = false): array => [
                'icon' => $icon,
                'label' => $label,
                'value' => $value,
                'value_html' => $html,
                'is_hidden' => ($value === _HIDE),
                'is_private' => $priv,
            ];
            if ($adm) {
                $idv = (string)$uid;
                $regdate = format_time($reg, _TIMESTRING);
                $lastvisit = format_time($last, _TIMESTRING) ?: _NO_INFO;
                $georow = $mkrow('hdd-network', _IP, '', Geoip::getIpHtml($userIpRaw), true);
                $agentv = $agent ?: _NO_INFO;
            } else {
                $idv = _HIDE;
                $regdate = format_time($reg);
                $lastvisit = format_time($last) ?: _NO_INFO;
                $geo = Geoip::getInfo($ip);
                $coun = (string)($geo['country_name'] ?: $geo['country']);
                $georow = $mkrow('geo-alt', _COUNTRY, $coun ?: _NO_INFO);
                $agentv = _HIDE;
            }
            $mailv = (($adm || $view) && $mail) ? $mail : _HIDE;
            $sitev = ($site) ? (($adm || is_user()) ? domain($site) : _HIDE) : _NO_INFO;
            $avatar = getUserAvatarUrl(['avatar' => $avatar]);
            $sign = getUserSign((string)$sig, intval($uid));
            $lang = getLangName($lang ?: $conf['language']);
            $points = ($pnt->active && $point) ? number_format((int)$point, 0, '', "\u{202F}") : _NO_INFO;
            $wnum = count(array_filter(explode('|', (string)$warn)));
            $warnhtml = ($wnum) ? warnings($warn) : '';
            if ($birth) {
                preg_match('#([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})#', $birth, $datetime);
                $birth = $datetime[3].'.'.$datetime[2].'.'.$datetime[1];
            } else {
                $birth = _NO_INFO;
            }
            $rating = getRatingAsync(1, $uid, $conf['name'], $votes, $total, '', 1);
            $field = ($field) ? getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]) : '';
            $lvl = getUserLevelData((int)$point, (string)$gcolor, (string)$grank);
            $rgroup = $lvl['groups'];
            $grank = $lvl['rank'];
            $ring = $lvl['ring'];
            $level = $lvl['level'];
            $nextlab = $lvl['nextlab'];
            $tones = ['neutral', 'neutral', 'info', 'info', 'success', 'accent'];
            $chips = [];
            foreach ($rgroup as $pos => $guname) $chips[] = ['name' => $guname, 'tone' => $tones[min($pos, 5)]];
            $tags = ($inter) ? array_values(array_filter(array_map('trim', explode(',', $inter)))) : [];
            $trank = ($gname) ? _GROUP.': '.$gname : (($rgroup) ? _USER_GROUPS.': '.implode(', ', $rgroup) : _RANK);
            $panels = [
                ['title' => _ACCOUNT, 'icon' => getIconName('profile'), 'rows' => [
                    $mkrow('calendar3', _REG, $regdate),
                    $mkrow('clock-history', _LAST_VISIT, $lastvisit),
                    $mkrow('stars', _POINTS, $points),
                    $mkrow('people', _SPEC_GROUP, $gname ?: _NO),
                ]],
                ['title' => _ACCOUNT_PERSON, 'icon' => getIconName('profile'), 'rows' => [
                    $mkrow('cake2', _BIRTHDAY, $birth),
                    $mkrow('geo-alt', _LOCALITYLANG, $from ?: _NO_INFO),
                    $mkrow('person', _GENDER, getGenderText($gender)),
                    $mkrow('translate', _LANGUAGE, $lang),
                ]],
                ['title' => _ACCOUNT_WORK, 'icon' => getIconName('work'), 'rows' => [
                    $mkrow('person-workspace', _OCCUPATION, $occ ?: _NO_INFO),
                    ($sitev !== _HIDE && $sitev !== _NO_INFO) ? $mkrow('globe', _SITEURL, '', $sitev) : $mkrow('globe', _SITEURL, $sitev),
                    $mkrow('envelope-at', _EMAIL, $mailv, '', $adm && !$view && $mailv !== _HIDE),
                ]],
                ['title' => _ACCOUNT_SYSTEM, 'icon' => getIconName('system'), 'rows' => [
                    $mkrow('hash', _ID, $idv, '', $adm),
                    $georow,
                    $mkrow('browser-chrome', _BROWSER, $agentv, '', $adm),
                ]],
            ];
            $hub = [];
            $parts = [];
            $params = [];
            $sums = [];
            foreach (getProfileModules($uid) as $mod => $inf) {
                if ($mod != 'comm' && !isset($inf['type']) && !is_active($mod)) continue;
                if (isset($inf['type'])) {
                    $stat = $inf['stat'];
                    $sums[$mod] = [$stat['num'], $inf['type']->settings['features']['rating'] ? $stat['ratings'] : 0, $stat['score'], $stat['favs']];
                } elseif ($mod != 'comm') {
                    $ron = !empty(getRatingService()->getRule($mod)['active']);
                    $rsel = ($ron && $inf['rate']) ? 'SUM('.$inf['rate'][0].') AS rc, SUM('.$inf['rate'][1].') AS rt' : '0 AS rc, 0 AS rt';
                    $ftab = PREFIX_DB.'_'.$inf['table'];
                    $fsel = '0';
                    if ($inf['fav'] !== '') {
                        $fjoin = " AS n ON (f.fid = n.id) WHERE f.modul = '".$inf['fav']."' AND n.uid = :f".$mod.')';
                        $fsel = '(SELECT COUNT(f.id) FROM '.PREFIX_DB.'_favorites AS f INNER JOIN '.$ftab.$fjoin;
                        $params['f'.$mod] = $uid;
                    }
                    $parts[] = "SELECT '".$mod."' AS mkey, COUNT(id) AS num, ".$rsel.', '.$fsel.' AS fav FROM '.$ftab.' WHERE '.str_replace(':uid', ':u'.$mod, $inf['where']);
                    $params['u'.$mod] = $uid;
                }
                $hub[$mod] = [
                    'icon' => $inf['icon'],
                    'title' => $inf['title'],
                    'count' => '0',
                    'rating' => '',
                    'favs' => '',
                    'href' => ($mod != 'comm') ? getSeoUrl(['name' => $mod]) : '',
                ];
            }
            $ccnt = $com->getUserCount($uid);
            $hub['comm']['count'] = (string)$ccnt;
            $hub['comm']['favs'] = '0';
            $sumn = $ccnt;
            $sumc = 0;
            $sumt = 0;
            $sumf = 0;
            if ($parts) {
                $result = $db->getSqlQuery(implode(' UNION ALL ', $parts), $params);
                while ([$key, $num, $rc, $rt, $fav] = $db->getSqlRow($result)) $sums[$key] = [$num, $rc, $rt, $fav];
            }
            foreach ($sums as $key => [$num, $rc, $rt, $fav]) {
                $hub[$key]['count'] = (string)$num;
                $hub[$key]['rating'] = ($rc > 0) ? number_format($rt / $rc, 2) : '';
                $hub[$key]['favs'] = (string)$fav;
                $sumn += (int)$num;
                $sumc += (int)$rc;
                $sumt += (int)$rt;
                $sumf += (int)$fav;
            }
            $hub = array_values($hub);
            $acts = $adm ? getActionMenu([
                ['href' => HOME_PATH.$afile.'.php?name=account&op=add&id='.$uid, 'title' => _FULLEDIT, 'icon_name' => 'pencil'],
                [
                    'href' => HOME_PATH.$afile.'.php?name=security&op=banlist&new_ip='.$userIpRaw,
                    'title' => _BANIPSENDER,
                    'icon_name' => 'shield-x',
                    'confirm_text' => _BANIPSENDER.' "'.$userIpRaw.'"?',
                ],
                getTplPostAction(['name' => 'account', 'op' => 'delete', 'id[]' => $uid], 'trash', _ONDELETE, _DELETE.' "'.$nick.'"?'),
            ]) : '';
            $pmhref = (($conf['privat']['act'] ?? 0) && !empty($nick)) ? getSeoUrl(['name' => $conf['name'], 'op' => 'privat', 'uname' => urlencode($nick)]) : '';
            $uacts = [];
            if ($pmhref !== '') $uacts[] = ['href' => $pmhref, 'title' => _SENDMES, 'icon_name' => 'envelope'];
            if (is_user() && $uname == $nick) {
                $uacts[] = ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _ACCOUNT, 'icon_name' => 'person'];
            }
            $uacts[] = ['href' => '#', 'title' => _BACK, 'icon_name' => 'arrow-left', 'onclick_attr' => 'onclick="window.history.go(-1);return false;"'];
            echo $tpl->getHtmlPart('account-profile', [
                'name' => $nick,
                'main_head' => [
                    'icon' => getIconName('profile'), 'title' => _PERSONALINFO,
                    'total_html' => getActionMenu($uacts, true).$acts.$tpl->getHtmlFrag('share', [
                        'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'uname' => urlencode($nick), 'route' => $route]),
                        'share_title' => $nick,
                    ]),
                ],
                'avatar' => $avatar,
                'avatar_html' => $tpl->getHtmlFrag('image', ['src' => $avatar, 'alt' => $nick, 'title' => $nick, 'is_avatar' => true]),
                'is_online' => $ison,
                'online_label' => _ONLINE,
                'offline_label' => _OFFLINE,
                'ring' => $ring,
                'urank' => $rank,
                'rank_html' => getRankView((string)$grank, $trank),
                'has_special_group' => !empty($gname),
                'sgroup' => $gname,
                'sgroup_label' => _SPEC_GROUP,
                'pm_href' => $pmhref,
                'pm_label' => _MESSAGE,
                'rating_label' => _RATING,
                'rating_html' => $rating,
                'has_level' => $pnt->active && !empty($point),
                'level' => $level,
                'level_group' => ($rgroup) ? end($rgroup) : '',
                'level_next' => $nextlab,
                'points_text' => $points,
                'points_label' => _POINTS,
                'years' => max(0, intval((time() - strtotime($reg)) / 31556952)),
                'years_label' => _ACCOUNT_YEARS,
                'reg_note' => _REG.': '.format_time($reg),
                'is_warned' => $wnum > 0,
                'warn_title' => ($wnum > 0) ? _UWARNS.': '.$wnum : _ACCOUNT_CLEAN,
                'warn_note' => ($wnum > 0) ? '' : _UWARNS.': '._NO,
                'group_title' => $gname ?: _ACCOUNT_MEMBER,
                'group_note' => ($gname) ? _SPEC_GROUP : _SPEC_GROUP.': '._NO,
                'panels' => $panels,
                'tags_head' => ['icon' => getIconName('tags'), 'title' => _INTERESTS],
                'tags' => $tags,
                'groups_head' => ['icon' => getIconName('groups'), 'title' => _USER_GROUPS],
                'group_chips' => $chips,
                'has_field' => !empty($field),
                'field' => $field,
                'has_warn_list' => $wnum > 0,
                'warn_head' => ['icon' => getIconName('warn'), 'title' => _UWARNS],
                'warn_html' => $warnhtml,
                'has_sign' => !empty($sign),
                'sign' => $sign,
                'hub_head' => ['icon' => getIconName('hub'), 'title' => _ACCOUNT_HUB, 'live_title' => _ACCOUNT_HUB, 'chips' => array_values(array_filter([
                    ['tone' => 'info', 'icon' => getIconName('items'), 'title' => _ACCOUNT_ITEMS, 'text' => (string)$sumn],
                    ($sumc > 0) ? ['tone' => 'success', 'icon' => getIconName('rating'), 'title' => _RATING, 'text' => number_format($sumt / $sumc, 2)] : [],
                    ['tone' => 'accent', 'icon' => getIconName('bookmark'), 'title' => _FAVORITES, 'text' => (string)$sumf],
                ]))],
                'hub' => $hub,
                'col_items' => _ACCOUNT_ITEMS,
                'col_rating' => _RATING,
                'col_favs' => _FAVORITES,
                'feed_html' => getProfileLastView($uid),
            ]);
            echo setComShow($uid, CommentMode::Open->value, $route);
            setFoot();
        } else {
            http_response_code(404);
            setHead(['title' => _USERNOEXIST]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => HOME_PATH, 'secs' => 3]);
            echo $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _USERNOEXIST, 'meta' => $meta]);
            setFoot();
        }
    } else {
        http_response_code(403);
        setHead(['title' => sprintf(_MODULEUSERS, getSeoUrl(['name' => $conf['name']]))]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => HOME_PATH, 'secs' => 15]);
        echo $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_MODULEUSERS, getSeoUrl(['name' => $conf['name']])), 'meta' => $meta]);
        setFoot();
    }
}

function profil(): void {
    global $db, $conf, $tpl, $prv, $pnt;
    if (!is_user()) {
        account();
        return;
    }
    $inf = getUserInfo();
    $uid = intval($inf['id'] ?? 0);
    setHead(['title' => _THISISYOURPAGE]);
    $grow = ($inf['grp'] ?? 0)
        ? $db->getSqlRow($db->getSqlQuery('SELECT name, `rank`, color FROM '.PREFIX_DB.'_groups WHERE id = :gid', ['gid' => $inf['grp']]))
        : null;
    $lvl = getUserLevelData((int)($inf['points'] ?? 0), (string)($grow['color'] ?? ''), (string)($grow['rank'] ?? ''));
    $pms = [];
    if ($conf['privat']['act']) {
        foreach ($prv->getRecentList($uid, 6) as $row) {
            $pms[] = [
                'avatar' => ($row['name'] !== '') ? getUserAvatarUrl(['avatar' => $row['avatar']]) : getUserAvatarUrl(),
                'name' => ($row['name'] !== '') ? $row['name'] : _ANONYM,
                'title' => cutstr($row['title'], 45),
                'date' => format_time($row['time']),
            ];
        }
    }
    $favs = [];
    if ($conf['favorites']['favact']) {
        $fmap = [];
        $result = $db->getSqlQuery('SELECT fid, modul FROM '.PREFIX_DB.'_favorites WHERE uid = :uid ORDER BY id DESC LIMIT 8', ['uid' => $uid]);
        while ([$fid, $fmod] = $db->getSqlRow($result)) {
            if (preg_match('/^[a-z_]+$/', (string)$fmod)) $fmap[$fmod][] = (int)$fid;
        }
        $paths = (new Route($db))->getRouteList('node', array_merge(...array_values(array_diff_key($fmap, ['forum' => 0]))));
        $tops = [];
        foreach ($fmap as $fmod => $fids) {
            $ftitles = [];
            if (isset($conf['node']['types'][$fmod])) {
                $ftitles = getNodeTitleMap(array_fill_keys($fids, $fmod));
            } elseif ($fmod === 'forum') {
                $fres = $db->getSqlQuery('SELECT f.id, f.title, r.path FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_routes AS r'
                    .' ON r.kind = \'topic\' AND r.oid = f.id AND r.cur = 1 WHERE f.id IN ('.implode(', ', $fids).')');
                while ($frow = $db->getSqlRow($fres)) [$ftitles[$frow[0]], $tops[$frow[0]]] = [$frow[1], $frow[2]];
            }
            foreach ($ftitles as $fid => $ftitle) {
                $favs[] = [
                    'icon' => getIconName($fmod),
                    'chip_icon' => $conf['modules'][$fmod]['icon'] ?? 'folder',
                    'title' => cutstr($ftitle, 60),
                    'href' => getSeoUrl(['name' => $fmod, 'op' => 'view', 'id' => $fid, 'route' => (($fmod === 'forum') ? $tops : $paths)[$fid] ?? null]),
                    'mod' => getModuleName($fmod),
                ];
            }
        }
    }
    $seen = format_time($inf['lastvis'] ?? null, _TIMESTRING);
    echo $tpl->getHtmlPart('account-home', [
        'home_head' => [
            'icon' => getIconName('home'),
            'title' => _THISISYOURPAGE,
            'chips' => ($seen !== '') ? [['tone' => 'neutral', 'icon' => getIconName('visit'), 'title' => _LAST_VISIT, 'text' => $seen]] : [],
        ],
        'name' => (string)$inf['name'],
        'avatar' => getUserAvatarUrl(['avatar' => (string)($inf['avatar'] ?? '')]),
        'ring' => $lvl['ring'],
        'has_level' => $pnt->active && !empty($inf['points']),
        'level' => $lvl['level'],
        'level_full' => $lvl['level'] >= 100,
        'level_group' => ($lvl['groups']) ? end($lvl['groups']) : '',
        'level_next' => $lvl['nextlab'],
        'rank' => (string)($inf['rank'] ?? ''),
        'online_label' => _ONLINE,
        'group_name' => (string)($grow['name'] ?? ''),
        'group_label' => _SPEC_GROUP,
        'has_points' => $pnt->active,
        'points_text' => number_format((int)($inf['points'] ?? 0), 0, '', "\u{202F}"),
        'points_label' => _POINTS,
        'rating' => ((int)($inf['votes'] ?? 0) > 0) ? number_format($inf['tvotes'] / $inf['votes'], 2) : '',
        'rating_label' => _RATING,
        'profile_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $uid, 'route' => $inf['path'] ?? null]),
        'profile_label' => _PERSONALINFO,
        'actions' => getUserNavItems(),
        'pm_head' => ['icon' => getIconName('messages'), 'title' => _PRIVAT, 'live_title' => _PRIVAT],
        'pm_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'privat']),
        'pms' => $pms,
        'fav_head' => ['icon' => getIconName('favorites'), 'title' => _FAVORITES, 'live_title' => _FAVORITES],
        'favs' => $favs,
        'has_rss' => ($conf['rss']['use'] ?? 0) == 1,
        'rss_head' => ['icon' => getIconName('rss'), 'title' => _RSS],
        'rss_action' => getSeoUrl(['name' => $conf['name'], 'op' => 'rss']),
        'rss_select_label' => _SELECTASITE,
        'rss_options_html' => (($conf['rss']['use'] ?? 0) == 1) ? rss_select() : '',
        'rss_url_label' => _ORTYPEURL,
        'ok_label' => _OK,
        'activity_html' => getProfileLastView($uid),
    ]);
    setFoot();
}

function rssfeed(): void {
    global $conf;
    if (!is_user() || ($conf['rss']['use'] ?? 0) != 1) exit;
    echo getRssView(getVar('req', 'url', 'url', ''));
    exit;
}

function privat(): void {
    global $conf, $tpl, $user, $prv;
    if (is_user() && ($conf['privat']['act'] ?? 0)) {
        setHead([
            'title' => _PRIVAT,
        ]);
        $uid = intval($user[0]);
        $tok = getSiteToken();
        $id = getVar('get', 'id', 'num', 0);
        $typ = getVar('get', 'typ', 'num', 0);
        $name = filterText(mb_substr(urldecode(getVar('get', 'uname', 'raw', '')), 0, 25));
        $open = '';
        if ($id) {
            $view = $prv->getMessageView($uid, $id, PrivatBox::Inbox);
            $side = $view ? 1 : 2;
            if (!$view) $view = $prv->getMessageView($uid, $id, PrivatBox::Outbox);
            if ($view) {
                $typ = ($side == 2) ? 2 : ($view['saved'] ? 3 : 1);
                $open = HOME_PATH.'index.php?go=1&op=setPrivateMessageRead&id='.$id.'&cid='.$side;
            }
        }
        if ($typ < 1 || $typ > 4) $typ = ($name !== '') ? 4 : 1;
        $list = ($typ == 4) ? 1 : $typ;
        $box = match ($list) {2 => PrivatBox::Outbox, 3 => PrivatBox::Saved, default => PrivatBox::Inbox};
        $pick = getPrivatPick();
        $sorts = '';
        foreach (['' => _PRBYNEW, 'old' => _PRBYOLD, 'unread' => _PRBYUNS, 'name' => _PRBYMATE] as $key => $lab) {
            $sorts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $key, 'label_text' => $lab, 'is_selected' => $pick['sort'] === $key]);
        }
        $face = $prv->getBoxFacets($uid, $box);
        $chips = [
            ['grp' => 'all', 'val' => '', 'label' => (string)_ALL, 'num' => $face['total'], 'on' => $pick['stat'] === '' && $pick['perd'] === ''],
            ['grp' => 'stat', 'val' => 'unread', 'label' => (string)_PRUNSEEN, 'num' => $face['unread'], 'on' => $pick['stat'] === 'unread'],
            ['grp' => 'stat', 'val' => 'read', 'label' => (string)_PRSEEN, 'num' => $face['read'], 'on' => $pick['stat'] === 'read'],
            ['grp' => 'perd', 'val' => 'new', 'label' => (string)_PRFRESH, 'num' => $face['fresh'], 'on' => $pick['perd'] === 'new'],
            ['grp' => 'perd', 'val' => 'old', 'label' => (string)_PRSTALE, 'num' => $face['stale'], 'on' => $pick['perd'] === 'old'],
        ];
        echo $tpl->getHtmlFrag('title', ['title' => _PRIVAT, 'is_level_one' => true]).getUserNav()
            .$tpl->getHtmlPart('privat-page', [
                'shelves_html' => getPrivatShelves($typ),
                'focus_html' => getPrivatFocus($typ),
                'notes_html' => getCabinetNotes('', '', $list),
                'find_url' => HOME_PATH.'index.php?go=1&op=getPrivateMessageView',
                'find' => $pick['find'],
                'seek_label' => (string)_PRSEEK,
                'search_label' => (string)_SEARCH,
                'sort_label' => (string)_SORT,
                'filter_label' => (string)_PRFILTER,
                'reset_label' => (string)_PRRESET,
                'chips' => $chips,
                'sort_html' => $tpl->getHtmlFrag('select', [
                    'name_attr' => 'sort', 'title' => _SORT, 'options_html' => $sorts, 'select_attr' => 'id="prsort"',
                ]),
                'tools_html' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'name', 'value_attr' => 'account', 'input_attr' => ''])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'privat', 'input_attr' => ''])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'typ', 'value_attr' => (string)$list, 'input_attr' => ''])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'stat', 'value_attr' => $pick['stat'], 'input_attr' => 'id="prstat"'])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'perd', 'value_attr' => $pick['perd'], 'input_attr' => 'id="prperd"']),
                'pane' => ($open || $typ == 4) ? 'view' : 'list',
                'list_label' => (string)_PRLIST,
                'view_label' => (string)_PRVIEW,
                'back_label' => (string)_PRBACK,
                'token' => $tok,
                'open_post' => $open,
                'list_html' => getPrivateMessageView('', '', $list),
                'shown' => getPrivatShown(),
                'view_html' => getPrivateMessageView('', '', ($typ == 4) ? 4 : 0),
                'send_url' => HOME_PATH.'index.php?go=1&op=addPrivateMessage',
                'token_html' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => $tok, 'input_attr' => '']),
                'to_row_html' => $tpl->getHtmlFrag('form-field-row', [
                    'label_for' => 'privat_message_name',
                    'label' => _PRRE,
                    'field_html' => getTplUserSearchInput([
                        'name' => 'name',
                        'input_id' => 'privat_message_name',
                        'list_id' => 'privat_message_name_list',
                        'maxlength' => 25,
                        'card' => 'prmate',
                        'value' => ($typ == 4) ? $name : '',
                    ]),
                ]),
                'head_row_html' => $tpl->getHtmlFrag('form-field-row', [
                    'label_for' => 'prtitle',
                    'label' => _TITLE,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text', 'name_attr' => 'title', 'value_attr' => '', 'maxlength_num' => 100,
                        'input_id' => 'prtitle', 'placeholder_text' => _TITLE,
                    ]),
                ]),
                'text_row_html' => $tpl->getHtmlFrag('form-field-row', [
                    'label' => _MESSAGE,
                    'label_id' => $labid = getFieldIds('', 'text')['label'],
                    'field_html' => getTplTextarea(['labelledby' => $labid, 'label' => _MESSAGE,
                        'id' => 'privat',
                        'name' => 'text',
                        'value' => '',
                        'mod' => $conf['name'],
                        'store' => 'privat.body',
                        'rows' => '15',
                        'placeholder' => _MESSAGE,
                    ]),
                ]),
                'send_label' => (string)_SEND,
                'wait_note' => intval($conf['privat']['send']) ? sprintf(_PRWAIT, intval($conf['privat']['send'])) : '',
            ]);
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
        $list = getFavoriteList(1);
        echo $tpl->getHtmlFrag('title', ['title' => _FAVORITES, 'is_level_one' => true]).getUserNav()
            .getCabinetNotes('', ($list === '') ? _NO_INFO : '', 0)
            .$tpl->getHtmlFrag('block-content', ['id' => 'repfavorliste', 'content' => $list]);
        setFoot();
    } else {
        account();
    }
}

function logins(): void {
    global $conf, $tpl, $user;
    if (!is_user()) {
        account();
        return;
    }
    $page = max(1, getVar('get', 'page', 'num', 1));
    $num = 20;
    $log = getLoginLog(['uid' => intval($user[0]), 'admin' => 0], $page, $num);
    if ($page > 1 && !$log['rows']) setError(404);
    setHead(['title' => _ACCOUNT_LOGINS]);
    $res = ['ok' => _ACCOUNT_LOGOK, 'password' => _ACCOUNT_LOGPASS, 'blocked' => _ACCOUNT_LOGBLOCK];
    $rows = '';
    foreach ($log['rows'] as $row) {
        $agent = html_entity_decode((string)$row['agent'], ENT_QUOTES, 'UTF-8');
        $info = getAgentInfo($agent, 0);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells' => [
            ['text' => format_time((string)$row['time'], _TIMESTRING)],
            ['text' => $res[$row['result']] ?? (string)$row['result']],
            ['text' => ($row['way'] === 'oauth') ? _ACCOUNT_LOGOAUTH : _PASSWORD],
            ['text' => (string)$row['ip']],
            ['primary_text' => $info['browser'].' / '.$info['os'], 'primary_title' => $agent],
        ]]);
    }
    $cont = ($rows === '') ? getCabinetNotes('', _NO_INFO, 0) : $tpl->getHtmlFrag('table', [
        'is_list' => true,
        'rows_html' => $rows,
        'headers' => [['text' => _DATE], ['text' => _STATUS], ['text' => _TYPE], ['text' => _IP], ['text' => _BROWSER]],
    ]).getTplPager(['count' => $log['total'], 'limit' => $num, 'mod' => $conf['name'], 'url_extra' => ['op' => 'logins']]);
    echo $tpl->getHtmlFrag('title', ['title' => _ACCOUNT_LOGINS, 'is_level_one' => true]).getUserNav().$cont;
    setFoot();
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
            'label_for' => 'f-email',
            'label' => _EMAIL,
            'field_html' => $tpl->getHtmlFrag('input', [
                'name_attr' => 'email',
                'input_id' => 'f-email',
                'value_attr' => $email,
                'maxlength_num' => 255,
                'placeholder_text' => _EMAIL,
                'is_required' => true,
            ]),
        ]);
        if (!empty($email)) {
            $fields .= $tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-code',
                'label' => _CONFIRMATIONCODE,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'code',
                    'input_id' => 'f-code',
                    'value_attr' => $code ?: '',
                    'maxlength_num' => 10,
                    'placeholder_text' => _CONFIRMATIONCODE,
                    'is_required' => true,
                ]),
            ]);
        }
        $after = $tpl->getHtmlFrag('block-content', [
            'is_form_submit' => true,
            'content' => $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true])
                .$tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'newuser']), 'title' => _REGNEWUSER, 'label' => _REGNEWUSER,
                    'is_footer_button' => true]),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => getSeoUrl(['name' => $conf['name']]),
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
    global $db, $conf, $stop, $tpl, $mailer;
    $email = getVar('post', 'email', 'text');
    $code = getVar('post', 'code', 'text');
    $code = ($code) ? substr($code, 0, 10) : false;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    checkemail($email);
    if (!$stop) {
        $result = $db->getSqlQuery('SELECT name, email, password FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $email]);
        if ($db->getSqlRowCount($result) == 0) {
            $stop = _NOUSERINFO;
        } else {
            [$nick, $mail, $pass] = $db->getSqlRow($result);
        }
    }
    if (!$stop) {
        $subpass = substr(md5($pass), 0, 10);
        if ($code && $subpass == $code) {
            $newpass = getRandomString($conf['users']['minpass']);
            $chash = getPassHash($newpass);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :password WHERE email = :email', ['password' => $chash, 'email' => $email]);
            $link = $tpl->getHtmlFrag('link', [
                'href' => getPublicUrl(['name' => $conf['name']]),
                'title' => getPublicUrl(['name' => $conf['name']]),
                'label_html' => getPublicUrl(['name' => $conf['name']]),
            ]);
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
            $message = getMailFrame(sprintf(_PASSSEND, $nick, $conf['sitename'], $nick, $newpass, $link));
            $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
            setHead([
                'title' => _PASSWORDLOST,
            ]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => getSeoUrl(['name' => $conf['name']]), 'secs' => 10]);
            echo $tpl->getHtmlFrag('title', ['title' => _PASSWORDLOST, 'is_level_one' => true])
                .$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _USERPASSWORD.' '.$nick.' '._MAILED, 'meta' => $meta]);
            setFoot();
        } else {
            $link = $tpl->getHtmlFrag('link', [
                'href' => getPublicUrl(['name' => $conf['name'], 'op' => 'passlost', 'code' => $subpass, 'email' => $email]),
                'title' => getPublicUrl(['name' => $conf['name'], 'op' => 'passlost', 'code' => $subpass, 'email' => $email]),
                'label_html' => getPublicUrl(['name' => $conf['name'], 'op' => 'passlost', 'code' => $subpass, 'email' => $email]),
            ]);
            $subject = $conf['sitename'].' - '._CODEFOR.' '.$nick;
            $message = getMailFrame(getTplLines([sprintf(_PASSCSEND, $nick, $conf['sitename'], $subpass, $link), _IFYOUDIDNOTASK], true, true));
            $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
            setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'passlost', 'email' => $email]));
        }
    } else {
        passlost();
    }
}

function setUserLogin(int $uid, string $name, string $pass, int $story, int $blockon, string $theme, string $way = 'login'): void {
    global $db, $conf, $pnt;
    setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $name, $pass, $story, $blockon, $theme]);
    $uip = getIp();
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
    getUserLastVisit($uid);
    $pars = ['ip' => $uip, 'agent' => filterCutText(getAgent(), 255), 'id' => $uid];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', $pars);
    $pnt->addEvent('login', 'account', 'day:'.gmdate('Ymd'), $uid);
    Captcha::clearLoginFailures('user');
    addLoginLog($uid, false, 'ok', $name, $way);
}

function login(): void {
    global $db, $conf, $stop;
    $block = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'account');
    if ($block) $stop[] = _ERROR;
    if (checkCaptcha('login')) [$stop[], $block] = [_SECCODEINCOR, true];
    $uname = htmlspecialchars(trim(substr(getVar('post', 'user_name', 'text'), 0, 25)));
    $upass = htmlspecialchars(trim(substr(getVar('post', 'user_password', 'text'), 0, 25)));
    if (!$uname || !$upass) $stop[] = _LOGININCOR;
    $result = $db->getSqlQuery(
        'SELECT id, name, email, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE name = :name',
        ['name' => $uname]
    );
    [$uid, $nick, $mail, $pass, $story, $blockon, $theme] = $db->getSqlRow($result);
    if ($db->getSqlRowCount($result) != 1 || !$uid || $nick != $uname || !checkPassHash($upass, $pass)) $stop[] = _LOGININCOR;
    if (!$stop && password_needs_rehash($pass, PASSWORD_BCRYPT)) {
        $pass = getPassHash($upass);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :pwd WHERE id = :id', ['pwd' => $pass, 'id' => $uid]);
    }
    if (!$stop) {
        setUserLogin((int)$uid, (string)$nick, (string)$pass, (int)$story, (int)$blockon, (string)$theme);
        setRedirect(getSeoUrl(['name' => $conf['name']]), true);
    } else {
        Captcha::registerLoginFailure('user');
        addLoginLog(($nick === $uname) ? intval($uid) : 0, false, $block ? 'blocked' : 'password', $uname);
        account();
    }
}

function logout(): void {
    global $db, $user;
    $nick = (is_array($user) && isset($user[1])) ? htmlspecialchars(substr((string)$user[1], 0, 25)) : '';
    setCookiesDelete('account');
    if ($nick !== '') $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $nick, 'guest' => 2]);
    unset($user);
    setRedirect(HOME_PATH, true);
}

# One yes/no line of the settings page; anything but the stored zero reads as yes
function getSetupSwitch(string $capt, string $name, string $valu, string $hint = ''): array {
    $ids = getFieldIds('', $name);
    return [
        'label' => $capt,
        'label_id' => $ids['label'],
        'hint' => $hint,
        'hint_id' => $ids['hint'],
        'control_html' => getTplRadioGroup([
            'labelledby' => $ids['label'],
            'describedby' => ($hint !== '') ? $ids['hint'] : '',
            'name' => $name,
            'value' => ($valu === '0') ? '0' : '1',
            'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ]),
    ];
}

# The stored path of a preset picked from the gallery, or '' unless the picture lies in the presets of the current theme
function getAvatarPreset(string $avat): string {
    $avat = basename($avat);
    if ($avat === '' || !preg_match("#\.(gif|png|jpe?g|svg)$#is", $avat)) return '';
    return file_exists(PUBLIC_DIR.'/templates/'.getTheme().'/images/avatars/presets/'.$avat) ? 'presets/'.$avat : '';
}

# What counts as a filled profile; the birthday and the signature stay out, as neither the date control nor the textarea of the editor takes the fill mark
function getProfileFillRate(array $info): array {
    $list = ['gender' => ['gender', '0'], 'mail' => ['email', ''], 'site' => ['website', ''], 'occ' => ['occ', ''], 'from' => ['origin', ''], 'inter' => ['interest', '']];
    $full = 0;
    $zero = [];
    foreach ($list as $name => $pair) {
        $valu = trim((string)($info[$pair[0]] ?? ''));
        if ($valu !== '' && $valu !== $pair[1]) $full++;
        $zero[$name] = $pair[1];
    }
    $tot = count($list);
    return ['rate' => (int)round($full / $tot * 100), 'left' => $tot - $full, 'zero' => $zero];
}

# The state lamps of the account; a lamp names the category tone it paints with only where that tone passes contrast as text, so warning carries none
function getAccountLamps(array $info, array $lnks, array $fill): array {
    global $conf;
    $haspw = !str_starts_with((string)($info['password'] ?? ''), '!');
    $ways = ($haspw) ? [_PASSWORD] : [];
    foreach ($lnks as $lnk) $ways[] = ucfirst((string)$lnk['provider']);
    $seen = !empty($info['viewmail']);
    $subs = [!empty($info['newslet'])];
    if (is_active('forum')) $subs[] = !empty($info['fsmail']);
    if (!empty($conf['privat']['act'])) $subs[] = !empty($info['psmail']);
    $ons = count(array_filter($subs));
    $all = count($subs);
    $ok = ['tone' => 'ok', 'cat' => 1];
    $info = ['tone' => 'info', 'cat' => 4];
    $warn = ['tone' => 'warn', 'cat' => 0];
    return [
        (($haspw) ? $ok : $warn) + ['label' => _SECURITY, 'value' => ($haspw) ? _ACCOUNT_SAFE : _ACCOUNT_NOPASS, 'note' => implode(', ', $ways)],
        (($seen) ? $info : $ok) + ['label' => _EMAIL, 'value' => ($seen) ? _ACCOUNT_SHOWN : _ACCOUNT_HIDDEN, 'note' => ($seen) ? _ACCOUNT_MAILSHOW : _ACCOUNT_MAILHIDE],
        (($ons === $all) ? $ok : $info) + ['label' => _ACCOUNT_MAIL, 'value' => sprintf(_NUMOF, $ons, $all), 'note' => ($ons === $all) ? _ACCOUNT_MAILALL : _ACCOUNT_MAILOFF],
        $info + ['label' => _ACCOUNT_FILLED, 'value' => $fill['rate'].'%', 'note' => _ACCOUNT_LEFT, 'left' => $fill['left'], 'is_meter' => true],
    ];
}

function edithome(): void {
    global $conf, $stop, $tpl;
    if (is_user()) {
        setHead([
            'title' => _CHANGE,
        ]);
        $info = getUserInfo();
        $bday = trim((string)($info['birthday'] ?? ''));
        if ($bday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) $bday = '';
        $info['theme'] = (!$info['theme']) ? $conf['theme'] : $info['theme'];
        $fill = getProfileFillRate($info);
        $lnks = Oauth::getLinks((int)($info['id'] ?? 0));
        $mark = fn(string $key): string => 'data-sl-meter-fill="'.($fill['zero'][$key] ?? '').'"';
        $errs = [];
        $tops = [];
        foreach ((array)$stop as $item) {
            $text = is_array($item) ? (string)($item['text'] ?? '') : (string)$item;
            $sect = is_array($item) ? (string)($item['sect'] ?? '') : '';
            if ($sect === '') $tops[] = $text;
            else $errs[$sect][] = $text;
        }
        $secs = [];
        $flds = [['label' => _BIRTHDAY, 'control_html' => getTplAddDateTime(['name' => 'user_birthday', 'time' => $bday, 'with' => false, 'max' => 10])]];
        $gopt = '';
        foreach ([_NO_INFO, _MAN, _WOMAN] as $key => $val) {
            $gopt .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => $val, 'is_selected' => $key == (int)$info['gender']]);
        }
        $flds[] = ['label' => _GENDER, 'label_for' => 'f-gender', 'control_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'gender',
            'selectid' => 'f-gender',
            'is_account' => true,
            'select_attr' => $mark('gender'),
            'options_html' => $gopt,
        ])];
        $flds[] = ['label' => _YOUREMAIL, 'label_for' => 'f-mail', 'control_html' => $tpl->getHtmlFrag('input', [
            'name_attr' => 'mail',
            'input_id' => 'f-mail',
            'value_attr' => $info['email'],
            'maxlength_num' => 60,
            'placeholder_text' => _YOUREMAIL,
            'input_attr' => $mark('mail'),
            'is_required' => true,
        ])];
        $flds[] = ['label' => _SITEURL, 'label_for' => 'f-site', 'control_html' => $tpl->getHtmlFrag('input', [
            'name_attr' => 'site',
            'input_id' => 'f-site',
            'value_attr' => $info['website'],
            'maxlength_num' => 100,
            'placeholder_text' => _SITEURL,
            'input_attr' => $mark('site'),
        ])];
        $flds[] = ['label' => _OCCUPATION, 'label_for' => 'f-occ', 'control_html' => $tpl->getHtmlFrag('input', [
            'name_attr' => 'occ',
            'input_id' => 'f-occ',
            'value_attr' => $info['occ'],
            'maxlength_num' => 100,
            'placeholder_text' => _OCCUPATION,
            'input_attr' => $mark('occ'),
        ])];
        $flds[] = ['label' => _LOCALITYLANG, 'label_for' => 'f-from', 'control_html' => $tpl->getHtmlFrag('input', [
            'name_attr' => 'from',
            'input_id' => 'f-from',
            'value_attr' => $info['origin'],
            'maxlength_num' => 100,
            'placeholder_text' => _LOCALITYLANG,
            'input_attr' => $mark('from'),
        ])];
        $flds[] = ['label' => _INTERESTS, 'label_for' => 'f-inter', 'control_html' => $tpl->getHtmlFrag('input', [
            'name_attr' => 'inter',
            'input_id' => 'f-inter',
            'value_attr' => $info['interest'],
            'maxlength_num' => 150,
            'placeholder_text' => _INTERESTS,
            'input_attr' => $mark('inter'),
        ])];
        $sids = getFieldIds('', 'sig');
        $flds[] = [
            'label' => _SIGNATURE,
            'label_id' => $sids['label'],
            'hint' => _SIGNATURE_TEXT,
            'hint_id' => $sids['hint'],
            'control_html' => getTplTextarea([
                'labelledby' => $sids['label'],
                'describedby' => $sids['hint'],
                'label' => _SIGNATURE,
                'id' => '1',
                'name' => 'sig',
                'value' => $info['sig'],
                'mod' => 'profile',
                'store' => 'users.sig',
                'rows' => '5',
                'placeholder' => _SIGNATURE,
                'required' => '0',
            ]),
        ];
        $tils = [['icon' => getIconName('person'), 'title' => _ACCOUNT_PERSON, 'width' => 6, 'tone' => 0, 'fields' => $flds]];
        $xtra = getFieldsInRows(['field' => $info['field'], 'mod' => $conf['name']]);
        if ($xtra) $tils[] = ['icon' => getIconName('fields'), 'title' => _ACCOUNT_FIELDS, 'width' => 6, 'tone' => 3, 'fields' => $xtra];
        $secs[] = ['id' => 'personal', 'icon' => getIconName('personal'), 'title' => _PERSONALINFO, 'inform' => true, 'tiles' => $tils];
        $arul = getUploadPlaceRule('users.avatar');
        $take = getVar('post', 'filepath', 'raw', '');
        $take = is_string($take) ? mb_substr(trim($take), 0, 512) : '';
        $upld = '';
        if (checkEditorUploadAccess((string)$arul['mod'], $arul)) {
            $upld = $tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-userfile',
                'label' => _FILE,
                'field_html' => getFileManagerField([
                    'id' => 'f-userfile',
                    'place' => 'users.avatar',
                    'name' => 'userfile',
                    'path' => 'filepath',
                    'path_value' => $take,
                ]),
            ]);
        }
        $tils = [[
            'icon' => getIconName('image'),
            'title' => _AVATAR,
            'width' => 2,
            'tone' => 3,
            'face_id' => 'f-face',
            'face_src' => getUserAvatarUrl($info),
            'face_alt' => _AVATAR,
            'text' => sprintf(_AVATARINFO, $conf['users']['awidth'], $conf['users']['aheight'], filterSize($conf['users']['amaxsize'])),
            'rows_html' => $upld,
        ]];
        $aset = [];
        $adir = 'templates/'.getTheme().'/images/avatars/presets';
        foreach (scandir(PUBLIC_DIR.'/'.$adir) ?: [] as $file) {
            if (!preg_match("#\.(gif|png|jpe?g|svg)$#is", $file)) continue;
            $alt = _AVATARSAVE.' '._ID.' '.str_replace('_', ' ', preg_replace("/^(.*)\..*$/", '\\1', $file));
            $aset[] = [
                'value' => $file,
                'label_html' => $tpl->getHtmlFrag('image', [
                    'src' => HOME_PATH.$adir.'/'.$file,
                    'alt' => $alt,
                    'title' => $alt,
                    'is_avatar' => true,
                    'img_attr' => 'width="64" height="64" loading="lazy" decoding="async"',
                ]),
            ];
        }
        if ($aset) {
            $aids = getFieldIds('', 'avatar');
            $tils[] = [
                'icon' => getIconName('images'),
                'title' => _AVATARSAVE,
                'title_id' => $aids['label'],
                'text' => _AVATARSELECT,
                'text_id' => $aids['hint'],
                'width' => 4,
                'tone' => 3,
                'hx_post' => getSeoUrl(['name' => $conf['name'], 'op' => 'setavatar']),
                'hx_params' => 'avatar,token',
                'hx_target' => '#f-face',
                'done_text' => _SUCCSAVE,
                'rows_html' => getTplRadioGroup([
                    'labelledby' => $aids['label'],
                    'describedby' => $aids['hint'],
                    'name' => 'avatar',
                    'value' => '',
                    'switch' => false,
                    'options' => $aset,
                ]),
            ];
        }
        $secs[] = ['id' => 'avatar', 'icon' => getIconName('profile'), 'title' => _AVATARSETUP, 'inform' => true, 'tiles' => $tils];
        $extra = $tpl->getHtmlFrag('hidden', ['name_attr' => 'user_name', 'value_attr' => $info['name']]);
        $lins = [];
        if ($conf['users']['news'] == 1) {
            $sopt = '';
            $numb = 3;
            while ($numb <= 20) {
                $sopt .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$numb, 'label_text' => (string)$numb, 'is_selected' => $numb == $info['storynum']]);
                $numb++;
            }
            $lins[] = ['label' => _C_12, 'label_for' => 'f-story', 'hint' => _ACCOUNT_STORYNOTE, 'hint_id' => 'f-story-hint', 'control_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'story',
                'selectid' => 'f-story',
                'describedby' => 'f-story-hint',
                'options_html' => $sopt,
            ])];
        }
        $lins[] = getSetupSwitch(_RNEWSLETTER, 'news', (string)$info['newslet'], _ACCOUNT_NEWSNOTE);
        if (is_active('forum')) $lins[] = getSetupSwitch(_FSMAIL, 'fsmail', (string)$info['fsmail'], _ACCOUNT_FSMAILNOTE);
        if (!empty($conf['privat']['act'])) $lins[] = getSetupSwitch(_PSMAIL, 'psmail', (string)$info['psmail'], _ACCOUNT_PSMAILNOTE);
        $tils = [['icon' => getIconName('mail'), 'title' => _ACCOUNT_MAILHEAD, 'width' => 6, 'tone' => 1, 'fields' => $lins]];
        $secs[] = ['id' => 'mail', 'icon' => getIconName('mail'), 'title' => _ACCOUNT_MAIL, 'inform' => true, 'tiles' => $tils];
        $lins = [getSetupSwitch(_ALLOWUSERS, 'view', !empty($info['viewmail']) ? '1' : '0', _ACCOUNT_VIEWNOTE)];
        $lins[] = getSetupSwitch(_ACTIVATEPERSONAL, 'blockon', (string)$info['blockon'], _ACCOUNT_BLOCKNOTE);
        $mids = getFieldIds('', 'block');
        $lins[] = [
            'label' => _MENUCONF,
            'label_id' => $mids['label'],
            'hint' => _MENUINFO,
            'hint_id' => $mids['hint'],
            'control_html' => getTplTextarea([
                'labelledby' => $mids['label'],
                'describedby' => $mids['hint'],
                'label' => _MENUCONF,
                'id' => '2',
                'name' => 'block',
                'value' => $info['block'],
                'mod' => 'profile',
                'store' => 'users.block',
                'rows' => '10',
                'placeholder' => _MENUCONF,
                'required' => '0',
            ]),
        ];
        $topt = '';
        $tcnt = 0;
        if ($conf['users']['theme']) {
            foreach (scandir(PUBLIC_DIR.'/templates') ?: [] as $file) {
                if ($file === '.' || $file === '..' || $file === 'admin') continue;
                if (!is_dir(PUBLIC_DIR.'/templates/'.$file) || !checkThemeAssets($file)) continue;
                $topt .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$file, 'label_text' => (string)$file, 'is_selected' => $file == $info['theme']]);
                $tcnt++;
            }
        }
        if ($tcnt > 1) {
            $lins[] = ['label' => _THEME, 'label_for' => 'f-theme', 'hint' => _ACCOUNT_THEMENOTE, 'hint_id' => 'f-theme-hint', 'control_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'theme',
                'selectid' => 'f-theme',
                'describedby' => 'f-theme-hint',
                'options_html' => $topt,
            ])];
        }
        if (is_dir(PUBLIC_DIR.'/'.getThemeFilePath('sound'))) {
            $zone = ['alert' => _ACCOUNT_SNDALERT, 'msg' => _PRIVAT] + (isAdmin() ? ['mod' => _ACCOUNT_SNDQUEUE, 'win' => _ACCOUNT_SNDWIN] : []);
            $boxes = [];
            foreach ($zone as $key => $label) {
                $boxes[] = ['label_text' => $label, 'input_id' => 'f-sound-'.$key, 'input_attr' => 'data-sl-sound-zone="'.$key.'"', 'is_checked' => $key !== 'mod'];
            }
            $sids = getFieldIds('', 'sound');
            $lins[] = ['label' => _ACCOUNT_SOUND, 'label_id' => $sids['label'], 'hint' => _ACCOUNT_SOUNDNOTE, 'hint_id' => $sids['hint'],
                'control_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $sids['label'], 'describedby' => $sids['hint'], 'items' => $boxes])];
        }
        $tils = [['icon' => getIconName('privacy'), 'title' => _ACCOUNT_PRIVHEAD, 'width' => 6, 'tone' => 2, 'fields' => $lins]];
        $secs[] = ['id' => 'privacy', 'icon' => getIconName('privacy'), 'title' => _ACCOUNT_PRIVACY, 'inform' => true, 'tiles' => $tils];
        if (str_starts_with((string)($info['password'] ?? ''), '!')) {
            $note = _OAUTHNOPW;
            $keys = $tpl->getHtmlFrag('link', [
                'href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']),
                'title' => _PASSWORDLOST,
                'label' => _PASSWORDLOST,
                'is_footer_button' => true,
            ]);
        } else {
            $pass = $tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-newpass',
                'label' => _PASSNEW,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'newpass',
                    'input_id' => 'f-newpass',
                    'itype' => 'password',
                    'autocomplete_attr' => 'new-password',
                    'maxlength_num' => 25,
                    'placeholder_text' => _PASSNEW,
                    'is_required' => true,
                ]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label' => _PASSNEW2,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'newpass2',
                    'itype' => 'password',
                    'autocomplete_attr' => 'new-password',
                    'maxlength_num' => 25,
                    'placeholder_text' => _PASSNEW2,
                    'is_required' => true,
                ]),
            ]).$tpl->getHtmlFrag('form-field-row', [
                'label_for' => 'f-oldpass',
                'label' => _PASSOLD,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'oldpass',
                    'input_id' => 'f-oldpass',
                    'itype' => 'password',
                    'autocomplete_attr' => 'current-password',
                    'maxlength_num' => 25,
                    'placeholder_text' => _PASSOLD,
                    'is_required' => true,
                ]),
            ]);
            $note = _PASSTEXT;
            $keys = $tpl->getHtmlPart('form-add', [
                'action' => getSeoUrl(['name' => $conf['name']]),
                'fields' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]).$pass,
                'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'savepass', 'label' => _SAVECHANGES]),
            ]);
        }
        $secs[] = ['id' => 'keys', 'icon' => getIconName('keys'), 'title' => _PASSSETUP, 'tiles' => [
            ['icon' => getIconName('password'), 'title' => _PASSWORD, 'width' => 6, 'tone' => 4, 'text' => $note, 'rows_html' => $keys],
        ]];
        $orws = [];
        foreach ($lnks as $lnk) {
            $ohid = $tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'oauth_unlink'])
                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'prov', 'value_attr' => (string)$lnk['provider']])
                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]);
            $orws[] = [
                'prov' => (string)$lnk['provider'],
                'label' => ucfirst((string)$lnk['provider']),
                'email' => (string)$lnk['email'],
                'since' => format_time($lnk['linked']),
                'unlink_html' => $tpl->getHtmlFrag('post-button', [
                    'action' => getSeoUrl(['name' => $conf['name']]),
                    'hidden' => $ohid,
                    'icon_name' => 'x-lg',
                    'title' => _DELETE,
                    'confirm_text' => _DELETE.' '.ucfirst((string)$lnk['provider']).'?',
                ]),
            ];
        }
        $obtn = Oauth::getButtons();
        if ($orws || $obtn !== '') {
            $secs[] = ['id' => 'oauth', 'icon' => getIconName('oauth'), 'title' => _OAUTHTAB, 'tiles' => [[
                'width' => 6,
                'tone' => 5,
                'rows_html' => $tpl->getHtmlPart('account-oauth-links', [
                    'nopw_text' => '',
                    'nopw_href' => '',
                    'nopw_label' => '',
                    'rows' => $orws,
                    'none_text' => ($orws) ? '' : _OAUTHNONE,
                    'buttons_html' => $obtn,
                ]),
            ]]];
        }
        $last = -1;
        $rail = [];
        foreach ($secs as $key => $sec) {
            if (!empty($sec['inform'])) {
                if ($last < 0) $secs[$key]['form_open'] = true;
                $last = $key;
            }
            if (!empty($errs[$sec['id']])) $secs[$key]['alert_html'] = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => $errs[$sec['id']]]);
            $rail[] = ['id' => $sec['id'], 'icon' => $sec['icon'], 'title' => $sec['title'], 'at' => count($rail)];
        }
        if ($last >= 0) $secs[$last]['form_close'] = true;
        if (count($rail) < 2) $rail = [];
        foreach ($rail as $key => $mark) $rail[$key]['of'] = count($rail) - 1;
        echo $tpl->getHtmlFrag('title', ['title' => _CHANGE, 'is_level_one' => true]).getUserNav().$tpl->getHtmlPart('account-settings', [
            'alert_html' => ($tops) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => $tops]) : '',
            'form_action' => getSeoUrl(['name' => $conf['name']]),
            'form_hidden' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]),
            'form_submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'extra' => $extra, 'op' => 'savehome', 'label' => _SAVECHANGES]),
            'lamps' => getAccountLamps($info, $lnks, $fill),
            'rail' => $rail,
            'rail_label' => _ACCOUNT_SECTIONS,
            'save_note' => _ACCOUNT_UNSAVED,
            'save_undo' => _ACCOUNT_UNDO,
            'sections' => $secs,
        ]);
        setFoot();
    } else {
        account();
    }
}

# Save the profile; an empty slot of the account cookie means no preference, which every reader resolves for itself
function savehome(): void {
    global $db, $user, $conf, $stop;
    if (!is_user()) {
        account();
        return;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        edithome();
        return;
    }
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $mail = getVar('post', 'mail', 'text');
    $sig = filterSourceText(getVar('post', 'sig', 'raw', ''), isAdmin(true));
    $block = filterSourceText(getVar('post', 'block', 'raw', ''), isAdmin(true));
    $prev = count((array)$stop);
    checkemail($mail);
    $last = count((array)$stop);
    for ($i = $prev; $i < $last; $i++) $stop[$i] = ['text' => (string)$stop[$i], 'sect' => 'personal'];
    $sql = 'SELECT id FROM '.PREFIX_DB.'_users WHERE email = :email AND id != :id';
    if ($db->getSqlRowCount($db->getSqlQuery($sql, ['email' => $mail, 'id' => (int)$user[0]])) > 0) $stop[] = ['text' => _ERROR_EMAIL, 'sect' => 'personal'];
    if ($room = checkEditorTextRoom($sig, 'users.sig')) $stop[] = ['text' => $room, 'sect' => 'personal'];
    if ($room = checkEditorTextRoom($block, 'users.block')) $stop[] = ['text' => $room, 'sect' => 'privacy'];
    [$fold, $sold, $bold] = $db->getSqlRow($db->getSqlQuery('SELECT field, sig, block FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => (int)$user[0]])) ?: ['', '', ''];
    if ($file = checkProfileNames($sig, (string)$sold)) $stop[] = ['text' => $file, 'sect' => 'personal'];
    if ($file = checkProfileNames($block, (string)$bold)) $stop[] = ['text' => $file, 'sect' => 'privacy'];
    $flds = getFieldsPost($conf['name'], (string)$fold);
    foreach ($flds['stop'] as $text) $stop[] = ['text' => $text, 'sect' => 'personal'];
    if (!$stop) {
        $uid = (int)$user[0];
        $checkn = htmlspecialchars(substr($user[1], 0, 25));
        $checkp = htmlspecialchars($user[2]);
        [$id, $name, $pass] = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password FROM '.PREFIX_DB.'_users WHERE id = :user_id', ['user_id' => $uid]));
        if ($id == $uid && $name == $checkn && $pass == $checkp) {
            $site = filterCutText(getVar('post', 'site', 'url'), 255);
            $occ = filterCutText(getVar('post', 'occ', 'text'), 100);
            $from = filterCutText(getVar('post', 'from', 'text'), 100);
            $inter = filterCutText(getVar('post', 'inter', 'text'), 150);
            $view = getVar('post', 'view', 'num');
            $story = getVar('post', 'story', 'num');
            $blockon = getVar('post', 'blockon', 'num');
            $theme = getVar('post', 'theme', 'text');
            if ($theme !== '' && !checkThemeAssets($theme)) $theme = '';
            $news = getVar('post', 'news', 'num');
            $fsmail = getVar('post', 'fsmail', 'num');
            $psmail = getVar('post', 'psmail', 'num');
            $birth = getVar('req', 'user_birthday', 'date', null);
            $gender = getVar('post', 'gender', 'num');
            $field = $flds['json'];
            $done = $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET email = :email, website = :website, viewmail = :viewmail, occ = :occ, origin = :origin, interest = :interest,'
                .' sig = :sig, storynum = COALESCE(NULLIF(:storynum, 0), storynum), blockon = :blockon, block = :block, theme = :theme, newslet = :newslet, fsmail = :fsmail,'
                .' psmail = :psmail, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', ['email' => $mail, 'website' => $site, 'viewmail' => $view,
                'occ' => $occ, 'origin' => $from, 'interest' => $inter, 'sig' => $sig, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme,
                'newslet' => $news, 'fsmail' => $fsmail, 'psmail' => $psmail, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid]);
            if (!$done) {
                $stop[] = ['text' => ($db->getSqlTwin() === 'email') ? _ERROR_EMAIL : _ERROR, 'sect' => 'personal'];
                edithome();
                return;
            }
            $avat = getVar('post', 'avatar', 'text');
            $take = getVar('post', 'filepath', 'raw', '');
            $take = is_string($take) ? mb_substr(trim($take), 0, 512) : '';
            $rule = getUploadPlaceRule('users.avatar');
            $able = checkEditorUploadAccess((string)$rule['mod'], $rule);
            $errn = (int)($_FILES['userfile']['error'] ?? UPLOAD_ERR_NO_FILE);
            $newa = '';
            $path = '';
            if ($avat) {
                $newa = getAvatarPreset($avat);
                if (!$newa) $stop[] = ['text' => _ERROR_FILE, 'sect' => 'avatar'];
            } elseif ($able && $errn !== UPLOAD_ERR_NO_FILE) {
                $res = getUploadService()->addUploadedFile($_FILES['userfile'], $rule, $rule['store'], $rule['mod'], getEditorFileOwner((string)$rule['mod']));
                $newa = ($res['ok']) ? (string)$res['file'] : '';
                $path = ($res['ok']) ? (string)$res['path'] : '';
                if (!$res['ok']) $stop[] = ['text' => getUploadFailText((string)$res['error'], $rule), 'sect' => 'avatar'];
            } elseif ($able && $take !== '') {
                $got = getUploadTakenFile($rule, $take);
                if (!$got['ok']) $stop[] = ['text' => ($got['error'] === 'owner') ? _ACCESSDENIED : _ERROR_FILE, 'sect' => 'avatar'];
                else $newa = (string)$got['file']['name'];
            }
            if ($newa !== '' && !$db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET avatar = :avatar WHERE id = :id', ['avatar' => filterText($newa), 'id' => $uid])) {
                $stop[] = ['text' => _ERROR, 'sect' => 'avatar'];
                if ($path !== '' && !getUploadService()->deleteStoredFile($path)) {
                    $stop[] = ['text' => _ERROR_UP, 'sect' => 'avatar'];
                    Logger::addFile('error', 'Avatar upload could not be removed after a failed profile write', ['path' => $path]);
                }
            }
            setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $name, $pass, $story, $blockon, $theme]);
            if ($stop) {
                edithome();
                return;
            }
            setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'edithome']));
        }
    } else {
        edithome();
    }
}

function setavatar(): void {
    global $db, $user, $tpl;
    if (!is_user() || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') exit;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    $newa = getAvatarPreset(getVar('post', 'avatar', 'text'));
    if ($newa === '' || !$db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET avatar = :avatar WHERE id = :id', ['avatar' => filterText($newa), 'id' => (int)$user[0]])) {
        header('HTTP/1.1 422 Unprocessable Content');
        exit;
    }
    echo $tpl->getHtmlFrag('image', ['src' => getUserAvatarUrl(['avatar' => $newa]), 'alt' => _AVATAR, 'title' => _AVATAR]);
    exit;
}

function savepass(): void {
    global $user, $db, $conf, $stop, $tpl, $mailer;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $newpass = getVar('post', 'newpass', 'text', false);
    $repeat = getVar('post', 'newpass2', 'text', false);
    $oldpass = getVar('post', 'oldpass', 'text', false);
    if (!$stop && is_user() && $oldpass && $newpass && $repeat) {
        if (strlen($newpass) >= $conf['users']['minpass']) {
            $uid = (int)$user[0];
            [$nick, $mail, $pass, $story, $blockon, $theme] = $db->getSqlRow($db->getSqlQuery(
                'SELECT name, email, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE id = :id',
                ['id' => $uid]
            ));
            if (!empty($pass) && checkPassHash($oldpass, $pass)) {
                if ($newpass == $repeat) {
                    $hash = getPassHash($newpass);
                    if (!$db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET password = :password WHERE id = :id', ['password' => $hash, 'id' => $uid])) {
                        $stop[] = _ERROR;
                        edithome();
                        return;
                    }
                    $link = $tpl->getHtmlFrag('link', [
                        'href' => getPublicUrl(['name' => $conf['name']]),
                        'title' => getPublicUrl(['name' => $conf['name']]),
                        'label_html' => getPublicUrl(['name' => $conf['name']]),
                    ]);
                    $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
                    $message = getMailFrame(sprintf(_PASSESEND, $nick, $conf['sitename'], $nick, $link));
                    $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
                    setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $nick, $hash, $story, $blockon, $theme]);
                    setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'edithome']), false, 302, _SUCCSAVE);
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

function oautherror(): void {
    global $conf, $tpl;
    Oauth::setCookie('pt', '', 0);
    Oauth::setCookie('st', '', 0);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    setHead(['title' => _ERROR]);
    echo $tpl->getHtmlFrag('title', ['title' => _ERROR, 'is_level_one' => true])
        .$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _OAUTHFAIL])
        .$tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true]);
    setFoot();
    exit;
}

function oauthretry(string $msg): void {
    global $conf;
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'oauth_finish']), false, 302, $msg, true);
}

function oauthlogin(int $uid, string $prov, string $redir, string $event = ''): void {
    global $db, $conf;
    $row = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]));
    if (!is_array($row) || empty($row['id'])) oautherror();
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_user_oauth SET lastlog = NOW() WHERE uid = :uid AND provider = :prov', ['uid' => $uid, 'prov' => $prov]);
    if ($event !== '') Logger::addOauth('info', $event, ['prov' => $prov, 'uid' => $uid]);
    setUserLogin((int)$row['id'], (string)$row['name'], (string)$row['password'], (int)$row['storynum'], (int)$row['blockon'], (string)$row['theme'], 'oauth');
    setRedirect($redir !== '' ? $redir : getSeoUrl(['name' => $conf['name']]));
}

function oauthinit(): void {
    $prov = strtolower(getVar('get', 'prov', 'word'));
    if (!Oauth::getProvider($prov)) {
        Logger::addOauth('warning', 'oauth_provider_disabled', ['prov' => $prov]);
        oautherror();
    }
    $redir = Oauth::getRedirect(getVar('get', 'redirect', 'text'));
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $verif = bin2hex(random_bytes(32));
    if (!Oauth::setTemp('state', $state, ['provider' => $prov, 'nonce' => $nonce, 'verifier' => $verif, 'redirect' => $redir])) oautherror();
    Oauth::setCookie('st', $state, 600);
    Logger::addOauth('info', 'oauth_init', ['prov' => $prov]);
    setRedirect(Oauth::getAuthUrl($prov, $state, $verif, $nonce));
}

function oauthback(): void {
    global $user;
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    $state = getVar('get', 'state', 'word');
    $fail = getVar('get', 'error', 'word');
    if ($fail !== '') {
        Oauth::deleteTemp($state);
        Logger::addOauth('warning', 'oauth_provider_error', ['note' => substr($fail, 0, 50)]);
        oautherror();
    }
    $ck = Oauth::getCookie('st');
    if ($state === '' || $ck === '' || !hash_equals($ck, $state)) {
        Logger::addOauth('warning', 'state_not_found', ['note' => 'browser binding failed']);
        oautherror();
    }
    Oauth::setCookie('st', '', 0);
    $row = Oauth::getTempOnce('state', $state);
    if ($row === null) {
        Logger::addOauth('warning', 'state_not_found');
        oautherror();
    }
    if (!empty($row['expired'])) {
        Logger::addOauth('warning', 'state_expired');
        oautherror();
    }
    $prov = (string)$row['provider'];
    if (!Oauth::getProvider($prov)) {
        Logger::addOauth('warning', 'oauth_bad_config', ['prov' => $prov]);
        oautherror();
    }
    $code = trim(getVar('get', 'code', 'raw', ''));
    if ($code === '') {
        Logger::addOauth('warning', 'oauth_provider_error', ['prov' => $prov, 'note' => 'code missing']);
        oautherror();
    }
    $data = Oauth::getTokens($prov, $code, (string)$row['verifier']);
    if (!$data['ok'] || empty($data['data']['id_token'])) {
        Logger::addOauth('warning', 'oauth_provider_error', ['prov' => $prov, 'note' => $data['error'] ?: 'no id_token']);
        oautherror();
    }
    try {
        $pay = Oauth::getJwtPayload((string)$data['data']['id_token'], $prov, (string)$row['nonce']);
    } catch (RuntimeException $err) {
        Logger::addOauth('warning', $err->getMessage(), ['prov' => $prov]);
        oautherror();
    }
    $claims = Oauth::getClaims($pay, $prov);
    $acc = (string)($data['data']['access_token'] ?? '');
    if ($acc !== '' && ($claims['email'] === '' || $claims['name'] === '')) {
        $uinf = Oauth::getUserinfo($prov, $acc);
        if ($uinf) {
            $more = Oauth::getClaims($uinf, $prov);
            if ($claims['email'] === '') {
                $claims['email'] = $more['email'];
                $claims['verified'] = false;
            }
            if ($claims['name'] === '') $claims['name'] = $more['name'];
        }
    }
    $claims = Oauth::filterClaims($claims);
    if ($claims['sub'] === '') {
        Logger::addOauth('warning', 'oauth_claims_missing', ['prov' => $prov]);
        oautherror();
    }
    $mail = $claims['verified'] ? $claims['email'] : '';
    $redir = Oauth::getRedirect((string)$row['redirect']);
    $uid = Oauth::getUserId($prov, $claims['sub']);
    if ($uid !== null) {
        oauthlogin($uid, $prov, $redir);
    }
    if (is_user()) {
        $cuid = (int)$user[0];
        $ecode = Oauth::setLink($cuid, $prov, $claims['sub'], $mail);
        if ($ecode !== '') {
            Logger::addOauth('warning', $ecode, ['prov' => $prov, 'uid' => $cuid]);
            oautherror();
        }
        Logger::addOauth('info', 'oauth_link', ['prov' => $prov, 'uid' => $cuid]);
        setRedirect($redir, false, 302, _OAUTHDONE);
    }
    $tok = bin2hex(random_bytes(32));
    if (!Oauth::setTemp('pending', $tok, ['provider' => $prov, 'uid' => $claims['sub'], 'email' => $mail, 'uname' => $claims['name'], 'redirect' => $redir])) oautherror();
    Oauth::setCookie('pt', $tok, 900);
    Logger::addOauth('info', 'oauth_callback_pending', ['prov' => $prov]);
    setRedirect(getSeoUrl(['name' => 'account', 'op' => 'oauth_finish']));
}

function oauthfinish(): void {
    global $db, $conf, $locale, $tpl, $pnt;
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        $tok = Oauth::getCookie('pt');
        if ($tok === '') setRedirect(getSeoUrl(['name' => $conf['name']]));
        $row = Oauth::getTemp('pending', $tok);
        if ($row === null) {
            Oauth::setCookie('pt', '', 0);
            setRedirect(getSeoUrl(['name' => $conf['name']]));
        }
        setHead(['title' => _OAUTHTITLE]);
        $link = $tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-user-name',
            'label' => _NICKNAME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'user_name',
                'input_id' => 'f-user-name',
                'maxlength_num' => 25,
                'placeholder_text' => _NICKNAME,
                'autocomplete_attr' => 'username',
                'is_required' => true,
            ]),
        ]).$tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-user-password',
            'label' => _PASSWORD,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'password',
                'name_attr' => 'user_password',
                'input_id' => 'f-user-password',
                'maxlength_num' => 25,
                'placeholder_text' => _PASSWORD,
                'autocomplete_attr' => 'current-password',
                'is_required' => true,
            ]),
        ]);
        $make = $tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-uname',
            'label' => _NICKNAME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'uname',
                'input_id' => 'f-uname',
                'value_attr' => substr((string)$row['uname'], 0, 25),
                'maxlength_num' => 25,
                'placeholder_text' => _NICKNAME,
                'is_required' => true,
            ]),
        ]);
        echo $tpl->getHtmlPart('account-oauth-finish', [
            'link_fields_html' => $link,
            'create_fields_html' => $make,
            'title' => _OAUTHTITLE,
            'provider' => ucfirst((string)$row['provider']),
            'intro' => _OAUTHINTRO,
            'ext_name' => (string)$row['uname'],
            'ext_email' => (string)$row['email'],
            'name_label' => _NICKNAME,
            'email_label' => _EMAIL,
            'link_title' => _OAUTHLINK,
            'link_text' => _OAUTHLINKT,
            'create_title' => _OAUTHNEW,
            'create_text' => _OAUTHNEWT,
            'login_label' => _USERLOGIN,
            'create_label' => _NEWUSER,
            'captcha' => getCaptcha('login'),
            'token' => htmlspecialchars(getSiteToken('account'), ENT_QUOTES, 'UTF-8'),
            'action' => getSeoUrl(['name' => $conf['name']]),
        ]);
        setFoot();
        return;
    }
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) {
        Logger::addOauth('warning', 'csrf_failed');
        oautherror();
    }
    $tok = Oauth::getCookie('pt');
    if ($tok === '') {
        Logger::addOauth('warning', 'pending_expired');
        oautherror();
    }
    $row = Oauth::getTemp('pending', $tok);
    if ($row === null) {
        Logger::addOauth('warning', 'pending_expired');
        oautherror();
    }
    $prov = (string)$row['provider'];
    if (!Oauth::getProvider($prov)) {
        Oauth::deleteTemp($tok);
        Logger::addOauth('warning', 'oauth_bad_config', ['prov' => $prov]);
        oautherror();
    }
    $redir = Oauth::getRedirect((string)$row['redirect']);
    $act = getVar('post', 'act', 'word');
    if ($act === 'link') {
        $uname = htmlspecialchars(trim(substr(getVar('post', 'user_name', 'text'), 0, 25)));
        $upass = htmlspecialchars(trim(substr(getVar('post', 'user_password', 'text'), 0, 25)));
        $ures = $db->getSqlQuery('SELECT id, password FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $uname]);
        $urow = $db->getSqlRow($ures);
        $badcap = checkCaptcha('login');
        if ($badcap || !$uname || !$upass || !is_array($urow) || empty($urow['id']) || !checkPassHash($upass, (string)$urow['password'])) {
            Captcha::registerLoginFailure('user');
            addLoginLog(intval($urow['id'] ?? 0), false, $badcap ? 'blocked' : 'password', $uname);
            oauthretry(_LOGININCOR);
        }
        $luid = (int)$urow['id'];
        $ecode = Oauth::setLink($luid, $prov, (string)$row['uid'], (string)$row['email']);
        if ($ecode !== '') {
            Oauth::deleteTemp($tok);
            Logger::addOauth('warning', $ecode, ['prov' => $prov, 'uid' => $luid]);
            oautherror();
        }
        Oauth::deleteTemp($tok);
        Oauth::setCookie('pt', '', 0);
        oauthlogin($luid, $prov, $redir, 'oauth_link');
    }
    if ($act === 'create') {
        if (empty($conf['users']['reg'])) {
            Oauth::deleteTemp($tok);
            Logger::addOauth('warning', 'oauth_reg_disabled', ['prov' => $prov]);
            oautherror();
        }
        $uname = trim((string)getVar('post', 'uname', 'name'));
        $nameb = explode(',', $conf['users']['name_b']);
        $badnm = !$uname || !analyze_name($uname) || in_array(strtolower($uname), array_filter($nameb), true);
        if (!$badnm && $db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $uname])) > 0) $badnm = true;
        if (!$badnm && $db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users_temp WHERE name = :name', ['name' => $uname])) > 0) $badnm = true;
        if ($badnm) {
            Logger::addOauth('warning', 'create_login_taken', ['prov' => $prov]);
            oauthretry(_NICKTAKEN);
        }
        $mail = (string)$row['email'];
        if ($mail !== '') {
            $mailb = explode(',', (string)$conf['users']['mail_b']);
            if (in_array(strtolower($mail), array_filter($mailb), true)) {
                Oauth::deleteTemp($tok);
                Logger::addOauth('warning', 'email_blocked', ['prov' => $prov]);
                oautherror();
            }
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $mail])) > 0) {
                Logger::addOauth('warning', 'email_exists', ['prov' => $prov]);
                oauthretry(_ERROR_EMAIL);
            }
        }
        $pass = '!'.bin2hex(random_bytes(20));
        if (!$db->setSqlBegin()) {
            Logger::addOauth('error', 'create_tx_failed', ['prov' => $prov]);
            oauthretry(_ERROR);
        }
        $ok = $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_users (id, name, `rank`, email, avatar, regdate, password, lang, ip, agent, sig, block, warnings, field)'
            .' VALUES (NULL, :uname, :rank, :email, :avatar, NOW(), :pwd, :lang, :ip, :agent, :sig, :block, :warnings, :field)',
            [
                'uname' => $uname, 'rank' => '', 'email' => ($mail !== '') ? $mail : null, 'avatar' => '', 'pwd' => $pass, 'lang' => $locale,
                'ip' => getIp(), 'agent' => filterCutText(getAgent(), 255), 'sig' => '', 'block' => '', 'warnings' => '', 'field' => '',
            ]
        );
        $nuid = ($ok !== false) ? (int)$db->getSqlLastId() : 0;
        $twin = match (($ok === false) ? $db->getSqlTwin() : '') { 'email' => 'email_exists', 'name' => 'create_login_taken', default => 'link_failed' };
        $ecode = ($nuid > 0) ? Oauth::setLink($nuid, $prov, (string)$row['uid'], $mail) : $twin;
        if ($ecode === '' && (new Route($db))->addRoute('user', $nuid, 'users', $uname) === '') $ecode = 'link_failed';
        if ($nuid > 0 && $ecode === '') $pnt->addEvent('register', 'account', 'user:'.$nuid, $nuid);
        if ($nuid < 1 || $ecode !== '') {
            $db->setSqlRollback();
            if ($ecode === 'link_duplicate') {
                Oauth::deleteTemp($tok);
                Logger::addOauth('warning', $ecode, ['prov' => $prov, 'uid' => $nuid]);
                oautherror();
            }
            Logger::addOauth('warning', ($ecode !== '') ? $ecode : 'create_login_taken', ['prov' => $prov, 'uid' => $nuid]);
            oauthretry(($ecode === 'email_exists') ? _ERROR_EMAIL : _NICKTAKEN);
        }
        if (!$db->setSqlCommit()) {
            $db->setSqlRollback();
            Logger::addOauth('error', 'create_tx_failed', ['prov' => $prov, 'uid' => $nuid]);
            oauthretry(_ERROR);
        }
        Oauth::deleteTemp($tok);
        Oauth::setCookie('pt', '', 0);
        oauthlogin($nuid, $prov, $redir, 'oauth_create');
    }
    oautherror();
}

function oauthunlink(): void {
    global $user, $conf;
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !is_user()) setRedirect(getSeoUrl(['name' => $conf['name']]));
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'edithome']), false, 302, _TOKENMISS, true);
    $prov = strtolower(getVar('post', 'prov', 'word'));
    $cuid = (int)$user[0];
    $ecode = Oauth::deleteLink($cuid, $prov);
    if ($ecode !== '') setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'edithome']), false, 302, _OAUTHLAST, true);
    Logger::addOauth('info', 'oauth_unlink', ['prov' => $prov, 'uid' => $cuid]);
    setRedirect(getSeoUrl(['name' => $conf['name'], 'op' => 'edithome']), false, 302, _OAUTHOFF);
}

function unsub(): void {
    global $db, $conf, $tpl;
    $mail = getVar('get', 'mail', 'raw', '');
    $mail = is_string($mail) ? trim($mail) : '';
    $key = getVar('get', 'key', 'var', '');
    $good = is_string($key) && $mail !== '' && $key !== '' && hash_equals(getUnsubKey($mail), $key);
    $post = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    if ($good && $post) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET newslet = 0 WHERE email = :mail', ['mail' => $mail]);
    setHead(['title' => _MAIL_UNSUB]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _MAIL_UNSUB, 'is_level_one' => true]);
    $show = $good ? htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') : '';
    if (!$good) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ACCOUNT_UNSUBBAD]);
    } elseif ($post) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_ACCOUNT_UNSUBOK, $show)]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_ACCOUNT_UNSUBASK, $show)]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => getSeoUrl(['name' => $conf['name'], 'op' => 'unsub', 'key' => $key, 'mail' => rawurlencode($mail)]),
            'no_enctype' => true,
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'unsub', 'label' => _MAIL_UNSUB]),
        ]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: account(); break;
    case 'newuser': newuser(); break;
    case 'finnewuser': finnewuser(); break;
    case 'privat': privat(); break;
    case 'rss': rssfeed(); break;
    case 'favorites': favorites(); break;
    case 'logins': logins(); break;
    case 'view': view(); break;
    case 'login': login(); break;
    case 'logout': logout(); break;
    case 'edithome': edithome(); break;
    case 'savehome': savehome(); break;
    case 'passlost': passlost(); break;
    case 'passmail': passmail(); break;
    case 'activate': activate(); break;
    case 'savepass': savepass(); break;
    case 'setavatar': setavatar(); break;
    case 'oauth_init': oauthinit(); break;
    case 'oauth': oauthback(); break;
    case 'oauth_finish': oauthfinish(); break;
    case 'oauth_unlink': oauthunlink(); break;
    case 'unsub': unsub(); break;
}
