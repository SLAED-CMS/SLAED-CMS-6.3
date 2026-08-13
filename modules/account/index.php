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
        $captcha = getPageCaptcha('login');
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
        $after .= Oauth::getButtons();
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
            $captcha = getPageCaptcha('register');
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
            $after .= Oauth::getButtons();
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
            $time = time();
            $finishlink = $conf['homeurl'].'/index.php?name='.$conf['name'].'&op=activate&user='.urlencode($nick).'&num='.$check;
            $nick = filterText($nick);
            $mail = filterText($mail);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_users_temp (id, name, email, password, regdate, code, time) VALUES (NULL, :name, :email, :password, NOW(), :code, :time)',
                ['name' => $nick, 'email' => $mail, 'password' => getPassHash($pass), 'code' => $check, 'time' => $time]
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
                $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
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
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (id, name, rank, email, avatar, regdate, password, lang, ip, agent, block, warnings, field) VALUES (NULL, :uname, :rank, :email, :avatar, :regdate, :pwd, :lang, :ip, :agent, :block, :warnings, :field)', ['uname' => $nick, 'rank' => $rank, 'email' => $mail, 'avatar' => '', 'regdate' => $reg, 'pwd' => str_starts_with($pass, '$2') ? $pass : getPassHash($pass), 'lang' => $locale, 'ip' => $uip, 'agent' => $uagent, 'block' => '', 'warnings' => '', 'field' => '']);
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
    global $db, $conf, $afile, $tpl, $prs, $com;
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
        $result = $db->getSqlQuery('SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.occ, u.origin, u.interest, u.sig, u.viewmail, u.lastvis, u.lang, u.points, u.ip, u.warnings, u.birthday, u.gender, u.votes, u.tvotes, u.field, u.agent, g.name, g.rank, g.color, (SELECT COUNT(s.id) FROM '.PREFIX_DB.'_session AS s WHERE s.uname = u.name) FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.grp) WHERE '.$where, $params);
        if ($db->getSqlRowCount($result) > 0) {
            [$uid, $nick, $rank, $mail, $site, $avatar, $reg, $occ, $from, $inter, $sig, $view, $last, $lang, $point, $ip, $warn, $birth, $gender, $votes, $total, $field, $agent, $gname, $grank, $gcolor, $ison] = $db->getSqlRow($result);
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
            $adm = isAdmin();
            $mkrow = fn(string $icon, string $label, string $value, string $html = '', bool $priv = false): array => ['icon' => $icon, 'label' => $label, 'value' => $value, 'value_html' => $html, 'is_hidden' => ($value === _HIDE), 'is_private' => $priv];
            if ($adm) {
                $idv = (string)$uid;
                $regdate = format_time($reg, _TIMESTRING);
                $lastvisit = format_time($last, _TIMESTRING);
                $georow = $mkrow('hdd-network', _IP, '', Geoip::getIpHtml($userIpRaw), true);
                $agentv = $agent ?: _NO_INFO;
            } else {
                $idv = _HIDE;
                $regdate = format_time($reg);
                $lastvisit = format_time($last);
                $geo = Geoip::getInfo($ip);
                $coun = (string)($geo['country_name'] ?: $geo['country']);
                $georow = $mkrow('geo-alt', _COUNTRY, $coun ?: _NO_INFO);
                $agentv = _HIDE;
            }
            $mailv = (($adm || $view) && $mail) ? $mail : _HIDE;
            $sitev = ($site) ? (($adm || is_user()) ? domain($site) : _HIDE) : _NO_INFO;
            $avatar = getUserAvatarUrl(['avatar' => $avatar]);
            $sign = ($sig) ? $prs->filterContent($sig, false, $conf['name']) : '';
            $lang = getLangName($lang ?: $conf['language']);
            $points = ($conf['users']['point'] && $point) ? number_format((int)$point, 0, '', "\u{202F}") : _NO_INFO;
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
            $rankImage = ($grank && file_exists(getThemeImagePath('ranks/'.$grank))) ? getThemeImagePath('ranks/'.$grank) : '';
            $panels = [
                ['title' => _ACCOUNT, 'icon' => 'person-vcard', 'rows' => [
                    $mkrow('calendar3', _REG, $regdate),
                    $mkrow('clock-history', _LAST_VISIT, $lastvisit),
                    $mkrow('stars', _POINTS, $points),
                    $mkrow('people', _SPEC_GROUP, $gname ?: _NO),
                ]],
                ['title' => _ACCOUNT_PERSON, 'icon' => 'person-badge', 'rows' => [
                    $mkrow('cake2', _BIRTHDAY, $birth),
                    $mkrow('geo-alt', _LOCALITYLANG, $from ?: _NO_INFO),
                    $mkrow('person', _GENDER, getGenderText($gender)),
                    $mkrow('translate', _LANGUAGE, $lang),
                ]],
                ['title' => _ACCOUNT_WORK, 'icon' => 'briefcase', 'rows' => [
                    $mkrow('person-workspace', _OCCUPATION, $occ ?: _NO_INFO),
                    ($sitev !== _HIDE && $sitev !== _NO_INFO) ? $mkrow('globe', _SITEURL, '', $sitev) : $mkrow('globe', _SITEURL, $sitev),
                    $mkrow('envelope-at', _EMAIL, $mailv, '', $adm && !$view && $mailv !== _HIDE),
                ]],
                ['title' => _ACCOUNT_SYSTEM, 'icon' => 'pc-display', 'rows' => [
                    $mkrow('hash', _ID, $idv, '', $adm),
                    $georow,
                    $mkrow('browser-chrome', _BROWSER, $agentv, '', $adm),
                ]],
            ];
            $hub = [];
            $parts = [];
            $params = [];
            foreach (getProfileModules() as $mod => $inf) {
                if ($mod != 'comm' && !is_active($mod)) continue;
                if ($mod != 'comm') {
                    $ron = !empty(explode('|', (string)($conf['ratings'][$mod] ?? ''))[1]);
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
                while ([$key, $num, $rc, $rt, $fav] = $db->getSqlRow($result)) {
                    $hub[$key]['count'] = (string)$num;
                    $hub[$key]['rating'] = ($rc > 0) ? number_format($rt / $rc, 2) : '';
                    $hub[$key]['favs'] = (string)$fav;
                    $sumn += (int)$num;
                    $sumc += (int)$rc;
                    $sumt += (int)$rt;
                    $sumf += (int)$fav;
                }
            }
            $hub = array_values($hub);
            $acts = $adm ? getActionMenu([
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
            $pmhref = (($conf['privat']['act'] ?? 0) && !empty($nick)) ? getSeoUrl(['name' => $conf['name'], 'op' => 'privat', 'uname' => urlencode($nick)]) : '';
            $uacts = [];
            if ($pmhref !== '') $uacts[] = ['href' => $pmhref, 'title' => _SENDMES, 'icon_name' => 'envelope'];
            if (is_user() && $uname == $nick) {
                $uacts[] = ['href' => getSeoUrl(['name' => $conf['name']]), 'title' => _ACCOUNT, 'icon_name' => 'person'];
            }
            $uacts[] = ['href' => '#', 'title' => _BACK, 'icon_name' => 'arrow-left', 'onclick_attr' => 'onclick="window.history.go(-1);return false;"'];
            echo $tpl->getHtmlPart('account-profile', [
                'name' => $nick,
                'kicker' => _PERSONALINFO,
                'avatar' => $avatar,
                'avatar_html' => $tpl->getHtmlFrag('image', ['src' => $avatar, 'alt' => $nick, 'title' => $nick, 'is_avatar' => true]),
                'is_online' => $ison,
                'online_label' => _ONLINE,
                'offline_label' => _OFFLINE,
                'ring' => $ring,
                'urank' => $rank,
                'has_rank_image' => !empty($rankImage),
                'rank_src' => $rankImage,
                'rank_alt' => $trank,
                'has_special_group' => !empty($gname),
                'sgroup' => $gname,
                'sgroup_label' => _SPEC_GROUP,
                'pm_href' => $pmhref,
                'pm_label' => _MESSAGE,
                'rating_label' => _RATING,
                'rating_html' => $rating,
                'has_level' => !empty($conf['users']['point']) && !empty($point),
                'level' => $level,
                'level_group' => ($rgroup) ? end($rgroup) : '',
                'level_next' => $nextlab,
                'points_text' => $points,
                'points_label' => _POINTS,
                'user_menu_html' => getActionMenu($uacts, true),
                'has_admin_actions' => $adm,
                'admin_actions_html' => $acts,
                'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'uname' => urlencode($nick)]),
                'share_title' => $nick,
                'years' => max(0, intval((time() - strtotime($reg)) / 31556952)),
                'years_label' => _ACCOUNT_YEARS,
                'reg_note' => _REG.': '.format_time($reg),
                'is_warned' => $wnum > 0,
                'warn_title' => ($wnum > 0) ? _UWARNS.': '.$wnum : _ACCOUNT_CLEAN,
                'warn_note' => ($wnum > 0) ? '' : _UWARNS.': '._NO,
                'group_title' => $gname ?: _ACCOUNT_MEMBER,
                'group_note' => ($gname) ? _SPEC_GROUP : _SPEC_GROUP.': '._NO,
                'panels' => $panels,
                'interests_label' => _INTERESTS,
                'tags' => $tags,
                'groups_label' => _USER_GROUPS,
                'group_chips' => $chips,
                'has_field' => !empty($field),
                'field' => $field,
                'has_warn_list' => $wnum > 0,
                'warn_label' => _UWARNS,
                'warn_html' => $warnhtml,
                'has_sign' => !empty($sign),
                'sign' => $sign,
                'hub_title' => _ACCOUNT_HUB,
                'hub' => $hub,
                'col_module' => _MODUL,
                'col_items' => _ACCOUNT_ITEMS,
                'col_rating' => _RATING,
                'col_favs' => _FAVORITES,
                'tot_items' => (string)$sumn,
                'tot_rating' => ($sumc > 0) ? number_format($sumt / $sumc, 2) : '',
                'tot_favs' => (string)$sumf,
                'feed_html' => getProfileLastView($uid),
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
    global $db, $conf, $tpl, $prv;
    if (!is_user()) {
        account();
        return;
    }
    $inf = getUserInfo();
    $uid = intval($inf['id'] ?? 0);
    setHead(['title' => _THISISYOURPAGE]);
    $grow = ($inf['grp'] ?? 0)
        ? $db->getSqlRow($db->getSqlQuery('SELECT name, rank, color FROM '.PREFIX_DB.'_groups WHERE id = :gid', ['gid' => $inf['grp']]))
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
        $micons = getProfileModules();
        foreach ($fmap as $fmod => $fids) {
            $ftable = ($fmod === 'shop') ? 'products' : $fmod;
            $fres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_'.$ftable.' WHERE id IN ('.implode(', ', $fids).')');
            while ([$fid, $ftitle] = $db->getSqlRow($fres)) {
                $favs[] = [
                    'icon' => $micons[$fmod]['icon'] ?? (($fmod === 'help') ? 'life-preserver' : (($fmod === 'shop') ? 'bag' : 'star')),
                    'chip_icon' => $conf['modules'][$fmod]['icon'] ?? 'folder',
                    'title' => cutstr($ftitle, 60),
                    'href' => 'index.php?name='.$fmod.'&op=view&id='.$fid,
                    'mod' => getModuleName($fmod),
                ];
            }
        }
    }
    echo $tpl->getHtmlPart('account-home', [
        'kicker' => _THISISYOURPAGE,
        'name' => (string)$inf['name'],
        'avatar' => getUserAvatarUrl(['avatar' => (string)($inf['avatar'] ?? '')]),
        'ring' => $lvl['ring'],
        'has_level' => !empty($conf['users']['point']) && !empty($inf['points']),
        'level' => $lvl['level'],
        'level_full' => $lvl['level'] >= 100,
        'level_group' => ($lvl['groups']) ? end($lvl['groups']) : '',
        'level_next' => $lvl['nextlab'],
        'rank' => (string)($inf['rank'] ?? ''),
        'online_label' => _ONLINE,
        'group_name' => (string)($grow['name'] ?? ''),
        'group_label' => _SPEC_GROUP,
        'has_points' => !empty($conf['users']['point']),
        'points_text' => number_format((int)($inf['points'] ?? 0), 0, '', "\u{202F}"),
        'points_label' => _POINTS,
        'rating' => ((int)($inf['votes'] ?? 0) > 0) ? number_format($inf['tvotes'] / $inf['votes'], 2) : '',
        'rating_label' => _RATING,
        'profile_href' => 'index.php?name='.$conf['name'].'&op=view&id='.$uid,
        'profile_label' => _PERSONALINFO,
        'lastvisit' => ($inf['lastvis'] ?? '') ? format_time($inf['lastvis'], _TIMESTRING) : '',
        'lastvisit_label' => _LAST_VISIT,
        'actions' => getUserNavItems(),
        'pm_title' => _PRIVAT,
        'pm_href' => 'index.php?name='.$conf['name'].'&op=privat',
        'pms' => $pms,
        'fav_title' => _FAVORITES,
        'favs' => $favs,
        'has_rss' => ($conf['rss']['use'] ?? 0) == 1,
        'rss_title' => _RSS,
        'rss_action' => 'index.php?name='.$conf['name'].'&op=rss',
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
    echo rss_read(getVar('req', 'url', 'url', ''), '');
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
        $name = filterText(mb_substr(urldecode((string)getVar('get', 'uname', 'raw', '')), 0, 25));
        $open = '';
        if ($id) {
            $view = $prv->getMessageView($uid, $id, PrivatBox::Inbox);
            $side = $view ? 1 : 2;
            if (!$view) $view = $prv->getMessageView($uid, $id, PrivatBox::Outbox);
            if ($view) {
                $typ = ($side == 2) ? 2 : ($view['saved'] ? 3 : 1);
                $open = 'index.php?go=1&op=setPrivateMessageRead&id='.$id.'&cid='.$side;
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
        $chips = [];
        if ($list != 2) {
            $face = $prv->getBoxFacets($uid, $box);
            $chips[] = ['grp' => 'all', 'val' => '', 'label' => (string)_ALL, 'num' => $face['total'], 'on' => $pick['stat'] === '' && $pick['perd'] === ''];
            $chips[] = ['grp' => 'stat', 'val' => 'unread', 'label' => (string)_PRUNSEEN, 'num' => $face['unread'], 'on' => $pick['stat'] === 'unread'];
            $chips[] = ['grp' => 'stat', 'val' => 'read', 'label' => (string)_PRSEEN, 'num' => $face['read'], 'on' => $pick['stat'] === 'read'];
            $chips[] = ($list == 3)
                ? ['grp' => 'perd', 'val' => 'old', 'label' => (string)_PRSTALE, 'num' => $face['stale'], 'on' => $pick['perd'] === 'old']
                : ['grp' => 'perd', 'val' => 'new', 'label' => (string)_PRFRESH, 'num' => $face['fresh'], 'on' => $pick['perd'] === 'new'];
        }
        echo $tpl->getHtmlFrag('title', ['title' => _PRIVAT, 'is_level_one' => true]).getUserNav()
            .$tpl->getHtmlPart('privat-page', [
                'shelves_html' => getPrivatShelves($typ),
                'find_url' => 'index.php?go=1&op=getPrivateMessageView',
                'find' => $pick['find'],
                'seek_label' => (string)_PRSEEK,
                'search_label' => (string)_SEARCH,
                'sort_label' => (string)_PRSORT,
                'filter_label' => (string)_PRFILTER,
                'reset_label' => (string)_PRRESET,
                'chips' => $chips,
                'sort_html' => $tpl->getHtmlFrag('select', [
                    'name_attr' => 'sort', 'title' => _PRSORT, 'options_html' => $sorts, 'select_attr' => 'id="prsort"',
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
                'view_html' => getPrivateMessageView('', '', ($typ == 4) ? 4 : 0),
                'send_url' => 'index.php?go=1&op=addPrivateMessage',
                'token_html' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => $tok, 'input_attr' => '']),
                'to_label' => (string)_PRRE,
                'to_html' => getTplUserSearchInput([
                    'name' => 'name',
                    'input_id' => 'privat_message_name',
                    'list_id' => 'privat_message_name_list',
                    'maxlength' => 25,
                    'value' => ($typ == 4) ? $name : '',
                ]),
                'head_label' => (string)_TITLE,
                'head_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text', 'name_attr' => 'title', 'value_attr' => '', 'maxlength_num' => 100,
                    'input_id' => 'prtitle', 'placeholder_text' => _TITLE,
                ]),
                'text_label' => (string)_MESSAGE,
                'editor_html' => getTplTextarea([
                    'id' => 'privat',
                    'name' => 'text',
                    'value' => '',
                    'mod' => $conf['name'],
                    'store' => 'privat.body',
                    'rows' => '15',
                    'placeholder' => _MESSAGE,
                ]),
                'send_label' => (string)_SEND,
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
            $link = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'title' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'label_html' => $conf['homeurl'].'/index.php?name='.$conf['name']]);
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
            $message = str_replace('[text]', sprintf(_PASSSEND, $nick, $conf['sitename'], $nick, $newpass, $link), $conf['mtemp']);
            $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
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
            $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
            setRedirect('index.php?name='.$conf['name'].'&op=passlost&email='.$email);
        }
    } else {
        passlost();
    }
}

function setUserLogin(int $uid, string $name, string $pass, int $story, int $blockon, string $theme): void {
    global $db, $conf;
    setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $name, $pass, $story, $blockon, $theme]);
    $uip = getIp();
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :uname AND guest = :guest', ['uname' => $uip, 'guest' => 0]);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :id', ['ip' => $uip, 'agent' => getAgent(), 'id' => $uid]);
    Captcha::clearLoginFailures('user');
    login_report(0, 1, $name, '');
}

function login(): void {
    global $db, $conf, $stop;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    if (checkCaptcha('login')) $stop[] = _SECCODEINCOR;
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
                    'store' => 'users.sig',
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
                    'store' => 'users.block',
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
        $tokn = getSiteToken('account');
        $list = scandir($adir);
        foreach ($list ?: [] as $file) {
            if (preg_match("#\.(gif|png|jpe?g|svg)$#is", $file)) {
                $filename = str_replace('_', ' ', preg_replace("/^(.*)\..*$/", '\\1', $file));
                $aset[] = [
                    'action' => 'index.php?name='.$conf['name'],
                    'hidden' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'saveavatar'])
                        .$tpl->getHtmlFrag('hidden', ['name_attr' => 'avatar', 'value_attr' => $file])
                        .$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => $tokn]),
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
        if (str_starts_with((string)($userinfo['password'] ?? ''), '!')) {
            $psetup = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _OAUTHNOPW])
                .$tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'passlost']), 'title' => _PASSWORDLOST, 'label' => _PASSWORDLOST, 'is_footer_button' => true]);
        } else {
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
        }
        $orows = [];
        foreach (Oauth::getLinks((int)($userinfo['id'] ?? 0)) as $lnk) {
            $ohid = $tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'oauth_unlink'])
                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'prov', 'value_attr' => (string)$lnk['provider']])
                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account')]);
            $orows[] = [
                'prov' => (string)$lnk['provider'],
                'label' => ucfirst((string)$lnk['provider']),
                'email' => (string)$lnk['email'],
                'since' => ($lnk['linked']) ? format_time(date('Y-m-d H:i:s', (int)$lnk['linked'])) : '',
                'unlink_html' => $tpl->getHtmlFrag('post-button', [
                    'action' => 'index.php?name='.$conf['name'],
                    'hidden' => $ohid,
                    'icon_name' => 'x-lg',
                    'title' => _DELETE,
                    'confirm_text' => _DELETE.' '.ucfirst((string)$lnk['provider']).'?',
                ]),
            ];
        }
        $obtn = Oauth::getButtons();
        $tabs = [_CHANGE, _AVATARSETUP, _PASSSETUP];
        $texts = [$change, $asetup, $psetup];
        if ($orows || $obtn !== '') {
            $tabs[] = _OAUTHTAB;
            $texts[] = $tpl->getHtmlPart('account-oauth-links', [
                'nopw_text' => '',
                'nopw_href' => '',
                'nopw_label' => '',
                'rows' => $orows,
                'none_text' => ($orows) ? '' : _OAUTHNONE,
                'buttons_html' => $obtn,
            ]);
        }
        echo $tpl->getHtmlFrag('title', ['title' => _CHANGE, 'is_level_one' => true]).getUserNav().$cont.getNaviTabs(0, 'tab', $tabs, $texts);
        setFoot();
    } else {
        account();
    }
}

function savehome(): void {
    global $db, $user, $conf, $stop;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $mail = getVar('post', 'mail', 'text');
    $sig = getVar('post', 'sig', 'text');
    $block = getVar('post', 'block', 'text');
    checkemail($mail);
    if ($room = checkEditorTextRoom($sig, 'users.sig')) $stop[] = $room;
    if ($room = checkEditorTextRoom($block, 'users.block')) $stop[] = $room;
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
            $view = getVar('post', 'view', 'num');
            $story = getVar('post', 'story', 'num');
            $blockon = getVar('post', 'blockon', 'num');
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
    if (!is_user()) {
        edithome();
        return;
    }
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) {
        $stop[] = _ERROR;
        edithome();
        return;
    }
    $uid = (int)$user[0];
    $avatar = getVar('post', 'avatar', 'text');
    $path = '';
    if ($avatar) {
        $avatar = basename($avatar);
        $avatar = (preg_match("#\.(gif|png|jpe?g|svg)$#is", $avatar) && file_exists('templates/'.getTheme().'/images/avatars/presets/'.$avatar)) ? 'presets/'.$avatar : '';
        if (!$avatar) $stop[] = _ERROR_FILE;
    } elseif ($conf['users']['aupload']) {
        $adir = trim(str_replace('\\', '/', $conf['users']['adirectory']), '/');
        $adir = str_starts_with($adir, 'uploads/') ? substr($adir, 8) : (($adir === 'uploads') ? '' : $adir);
        $rule = [
            'extensions' => $conf['users']['atypefile'],
            'maxbytes' => (int)$conf['users']['amaxsize'],
            'maxwidth' => (int)$conf['users']['awidth'],
            'maxheight' => (int)$conf['users']['aheight'],
            'maxfiles' => 1,
            'maxquota' => 0,
        ];
        $res = getUploadService()->addUploadedFile($_FILES['userfile'] ?? [], $rule, $adir, $conf['name'], $uid);
        $avatar = ($res['ok']) ? (string)$res['file'] : '';
        $path = ($res['ok']) ? (string)$res['path'] : '';
        if (!$res['ok']) {
            $stop[] = match ((string)$res['error']) {
                'size' => _ERROR_BIG,
                'extension', 'mime', 'image', 'unsupported' => _ERROR_FILE,
                'dimensions' => _ERROR_SIZE,
                'exists' => _ERROR_EXIST,
                'destination', 'write' => _ERROR_UP,
                default => _ERROR_DOWN,
            };
        }
    }
    if ($stop || !$avatar) {
        edithome();
        return;
    }
    if (!$db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET avatar = :avatar WHERE id = :id', ['avatar' => filterText($avatar), 'id' => $uid])) {
        $stop[] = _ERROR;
        if ($path !== '' && !getUploadService()->deleteStoredFile($path)) {
            $stop[] = _ERROR_UP;
            Logger::addFile('error', 'Avatar upload could not be removed after a failed profile write', ['path' => $path]);
        }
        edithome();
        return;
    }
    setRedirect('index.php?name='.$conf['name'].'&op=edithome');
}

function savepass(): void {
    global $user, $db, $conf, $stop, $tpl, $mailer;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) $stop[] = _ERROR;
    $newpass = getVar('post', 'newpass', 'text', false);
    $newpass2 = getVar('post', 'newpass2', 'text', false);
    $oldpass = getVar('post', 'oldpass', 'text', false);
    if (is_user() && $oldpass && $newpass && $newpass2) {
        if (strlen($newpass) >= $conf['users']['minpass']) {
            $uid = (int)$user[0];
            [$pass] = $db->getSqlRow($db->getSqlQuery('SELECT password FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid]));
            if (!empty($pass) && checkPassHash($oldpass, $pass)) {
                if ($newpass == $newpass2) {
                    $userinfo = getUserInfo();
                    $mail = $userinfo['email'];
                    $nick = $userinfo['name'];
                    $link = $tpl->getHtmlFrag('link', ['href' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'title' => $conf['homeurl'].'/index.php?name='.$conf['name'], 'label_html' => $conf['homeurl'].'/index.php?name='.$conf['name']]);
                    $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$nick;
                    $message = str_replace('[text]', sprintf(_PASSESEND, $nick, $conf['sitename'], $nick, $newpass, $link), $conf['mtemp']);
                    $mailer->addQueue(['kind' => 'account', 'email' => $mail, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
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

function oautherror(): void {
    global $conf, $tpl;
    Oauth::setCookie('pt', '', 0);
    Oauth::setCookie('st', '', 0);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    setHead(['title' => _ERROR]);
    echo $tpl->getHtmlFrag('title', ['title' => _ERROR, 'is_level_one' => true])
        .$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _OAUTHFAIL])
        .$tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'], 'title' => _USERLOGIN, 'label' => _USERLOGIN, 'is_footer_button' => true]);
    setFoot();
    exit;
}

function oauthretry(string $msg): void {
    global $conf;
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    setRedirect('index.php?name='.$conf['name'].'&op=oauth_finish', false, 302, $msg, true);
}

function oauthlogin(int $uid, string $prov, string $redir, string $event = ''): void {
    global $db, $conf;
    $row = $db->getSqlRow($db->getSqlQuery('SELECT id, name, password, storynum, blockon, theme FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]));
    if (!is_array($row) || empty($row['id'])) oautherror();
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_user_oauth SET lastlog = :time WHERE uid = :uid AND provider = :prov', ['time' => time(), 'uid' => $uid, 'prov' => $prov]);
    if ($event !== '') Oauth::setLog($event, $prov, $uid);
    setUserLogin((int)$row['id'], (string)$row['name'], (string)$row['password'], (int)$row['storynum'], (int)$row['blockon'], (string)$row['theme']);
    setRedirect($redir !== '' ? $redir : 'index.php?name='.$conf['name'].'&op=profil');
}

function oauthinit(): void {
    $prov = strtolower(getVar('get', 'prov', 'word'));
    if (!Oauth::getProvider($prov)) {
        Oauth::setLog('oauth_provider_disabled', $prov);
        oautherror();
    }
    $redir = Oauth::getRedirect(getVar('get', 'redirect', 'text'));
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $verif = bin2hex(random_bytes(32));
    if (!Oauth::setTemp('state', $state, ['provider' => $prov, 'nonce' => $nonce, 'verifier' => $verif, 'redirect' => $redir])) oautherror();
    Oauth::setCookie('st', $state, 600);
    Oauth::setLog('oauth_init', $prov);
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
        Oauth::setLog('oauth_provider_error', '', 0, substr($fail, 0, 50));
        oautherror();
    }
    $ck = Oauth::getCookie('st');
    if ($state === '' || $ck === '' || !hash_equals($ck, $state)) {
        Oauth::setLog('state_not_found', '', 0, 'browser binding failed');
        oautherror();
    }
    Oauth::setCookie('st', '', 0);
    $row = Oauth::getTempOnce('state', $state);
    if ($row === null) {
        Oauth::setLog('state_not_found');
        oautherror();
    }
    if (!empty($row['expired'])) {
        Oauth::setLog('state_expired');
        oautherror();
    }
    $prov = (string)$row['provider'];
    if (!Oauth::getProvider($prov)) {
        Oauth::setLog('oauth_bad_config', $prov);
        oautherror();
    }
    $code = trim((string)getVar('get', 'code', 'raw', ''));
    if ($code === '') {
        Oauth::setLog('oauth_provider_error', $prov, 0, 'code missing');
        oautherror();
    }
    $data = Oauth::getTokens($prov, $code, (string)$row['verifier']);
    if (!$data['ok'] || empty($data['data']['id_token'])) {
        Oauth::setLog('oauth_provider_error', $prov, 0, $data['error'] ?: 'no id_token');
        oautherror();
    }
    try {
        $pay = Oauth::getJwtPayload((string)$data['data']['id_token'], $prov, (string)$row['nonce']);
    } catch (RuntimeException $err) {
        Oauth::setLog($err->getMessage(), $prov);
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
        Oauth::setLog('oauth_claims_missing', $prov);
        oautherror();
    }
    $mail = $claims['verified'] ? $claims['email'] : '';
    $redir = Oauth::getRedirect((string)$row['redirect']);
    $uid = Oauth::getUserId($prov, $claims['sub']);
    if ($uid !== null) {
        oauthlogin($uid, $prov, $redir, 'oauth_callback_success');
    }
    if (is_user()) {
        $cuid = (int)$user[0];
        $ecode = Oauth::setLink($cuid, $prov, $claims['sub'], $mail);
        if ($ecode !== '') {
            Oauth::setLog($ecode, $prov, $cuid);
            oautherror();
        }
        Oauth::setLog('oauth_link', $prov, $cuid);
        setRedirect($redir, false, 302, _OAUTHDONE);
    }
    $tok = bin2hex(random_bytes(32));
    if (!Oauth::setTemp('pending', $tok, ['provider' => $prov, 'uid' => $claims['sub'], 'email' => $mail, 'uname' => $claims['name'], 'redirect' => $redir])) oautherror();
    Oauth::setCookie('pt', $tok, 900);
    Oauth::setLog('oauth_callback_pending', $prov);
    setRedirect('index.php?name=account&op=oauth_finish');
}

function oauthfinish(): void {
    global $db, $conf, $locale, $tpl;
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Referrer-Policy: no-referrer');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        $tok = Oauth::getCookie('pt');
        if ($tok === '') setRedirect('index.php?name='.$conf['name']);
        $row = Oauth::getTemp('pending', $tok);
        if ($row === null) {
            Oauth::setCookie('pt', '', 0);
            setRedirect('index.php?name='.$conf['name']);
        }
        setHead(['title' => _OAUTHTITLE]);
        echo $tpl->getHtmlPart('account-oauth-finish', [
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
            'password_label' => _PASSWORD,
            'login_label' => _USERLOGIN,
            'create_label' => _NEWUSER,
            'suggest' => htmlspecialchars(substr((string)$row['uname'], 0, 25), ENT_QUOTES, 'UTF-8'),
            'captcha' => getPageCaptcha('login'),
            'token' => htmlspecialchars(getSiteToken('account'), ENT_QUOTES, 'UTF-8'),
            'action' => 'index.php?name='.$conf['name'],
        ]);
        setFoot();
        return;
    }
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) {
        Oauth::setLog('csrf_failed');
        oautherror();
    }
    $tok = Oauth::getCookie('pt');
    if ($tok === '') {
        Oauth::setLog('pending_expired');
        oautherror();
    }
    $row = Oauth::getTemp('pending', $tok);
    if ($row === null) {
        Oauth::setLog('pending_expired');
        oautherror();
    }
    $prov = (string)$row['provider'];
    if (!Oauth::getProvider($prov)) {
        Oauth::deleteTemp($tok);
        Oauth::setLog('oauth_bad_config', $prov);
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
            login_report(0, 0, $uname, $upass);
            Oauth::setLog('link_login_failed', $prov);
            oauthretry(_LOGININCOR);
        }
        $luid = (int)$urow['id'];
        $ecode = Oauth::setLink($luid, $prov, (string)$row['uid'], (string)$row['email']);
        if ($ecode !== '') {
            Oauth::deleteTemp($tok);
            Oauth::setLog($ecode, $prov, $luid);
            oautherror();
        }
        Oauth::deleteTemp($tok);
        Oauth::setCookie('pt', '', 0);
        oauthlogin($luid, $prov, $redir, 'oauth_link');
    }
    if ($act === 'create') {
        if (empty($conf['users']['reg'])) {
            Oauth::deleteTemp($tok);
            Oauth::setLog('oauth_reg_disabled', $prov);
            oautherror();
        }
        $uname = getVar('post', 'uname', 'name');
        $uname = trim(substr((string)$uname, 0, 25));
        $nameb = explode(',', $conf['users']['name_b']);
        $badnm = !$uname || !analyze_name($uname) || in_array(strtolower($uname), array_filter($nameb), true);
        if (!$badnm && $db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $uname])) > 0) $badnm = true;
        if (!$badnm && $db->getSqlRowCount($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users_temp WHERE name = :name', ['name' => $uname])) > 0) $badnm = true;
        if ($badnm) {
            Oauth::setLog('create_login_taken', $prov);
            oauthretry(_NICKTAKEN);
        }
        $mail = (string)$row['email'];
        if ($mail !== '') {
            $mailb = explode(',', (string)$conf['users']['mail_b']);
            if (in_array(strtolower($mail), array_filter($mailb), true)) {
                Oauth::deleteTemp($tok);
                Oauth::setLog('email_blocked', $prov);
                oautherror();
            }
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $mail])) > 0) {
                Oauth::setLog('email_exists', $prov);
                oauthretry(_ERROR_EMAIL);
            }
        }
        $pass = '!'.bin2hex(random_bytes(20));
        if (!$db->setSqlBegin()) {
            Oauth::setLog('create_tx_failed', $prov);
            oauthretry(_ERROR);
        }
        $ok = $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_users (id, name, rank, email, avatar, regdate, password, lang, ip, agent, block, warnings, field)'
            .' VALUES (NULL, :uname, :rank, :email, :avatar, NOW(), :pwd, :lang, :ip, :agent, :block, :warnings, :field)',
            [
                'uname' => $uname, 'rank' => '', 'email' => $mail, 'avatar' => '', 'pwd' => $pass, 'lang' => $locale,
                'ip' => getIp(), 'agent' => getAgent(), 'block' => '', 'warnings' => '', 'field' => '',
            ]
        );
        $nuid = ($ok !== false) ? (int)$db->getSqlLastId() : 0;
        $ecode = ($nuid > 0) ? Oauth::setLink($nuid, $prov, (string)$row['uid'], $mail) : 'link_failed';
        if ($nuid < 1 || $ecode !== '') {
            $db->setSqlRollback();
            if ($ecode === 'link_duplicate') {
                Oauth::deleteTemp($tok);
                Oauth::setLog($ecode, $prov, $nuid);
                oautherror();
            }
            Oauth::setLog(($ecode !== '') ? $ecode : 'create_login_taken', $prov, $nuid);
            oauthretry(_NICKTAKEN);
        }
        if (!$db->setSqlCommit()) {
            $db->setSqlRollback();
            Oauth::setLog('create_tx_failed', $prov, $nuid);
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
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !is_user()) setRedirect('index.php?name='.$conf['name']);
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'account')) setRedirect('index.php?name='.$conf['name'].'&op=edithome', false, 302, _TOKENMISS, true);
    $prov = strtolower(getVar('post', 'prov', 'word'));
    $cuid = (int)$user[0];
    $ecode = Oauth::deleteLink($cuid, $prov);
    if ($ecode !== '') setRedirect('index.php?name='.$conf['name'].'&op=edithome', false, 302, _OAUTHLAST, true);
    Oauth::setLog('oauth_unlink', $prov, $cuid);
    setRedirect('index.php?name='.$conf['name'].'&op=edithome', false, 302, _OAUTHOFF);
}

switch ($op) {
    default: account(); break;
    case 'newuser': newuser(); break;
    case 'finnewuser': finnewuser(); break;
    case 'privat': privat(); break;
    case 'rss': rssfeed(); break;
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
    case 'oauth_init': oauthinit(); break;
    case 'oauth': oauthback(); break;
    case 'oauth_finish': oauthfinish(); break;
    case 'oauth_unlink': oauthunlink(); break;
}
