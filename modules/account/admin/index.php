<?php
# Author: Eduard Laas
# Copyright ï¿½ 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('account')) die('Illegal file access');


function account(): void {
    global $db, $afile, $conf, $tpl;
    $search = getVar('req', 'search', 'num');
    $chng = getVar('req', 'chng');
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
    ]);
    if (getVar('get','send','num')) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MAIL_SEND]);
    $where = '1 = 1';
    $wcnt = '1 = 1';
    $order = 'ORDER BY u.id DESC';
    $params = [];
    if ($search == 1 && $chng) {
        $where = 'u.id LIKE :search';
        $wcnt = 'id LIKE :search';
        $order = 'ORDER BY u.id ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 2 && $chng) {
        $where = 'u.name LIKE :search';
        $wcnt = 'name LIKE :search';
        $order = 'ORDER BY u.name ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 3 && $chng) {
        $where = 'u.email LIKE :search';
        $wcnt = 'email LIKE :search';
        $order = 'ORDER BY u.email ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 4 && $chng) {
        $where = 'u.ip LIKE :search';
        $wcnt = 'ip LIKE :search';
        $order = 'ORDER BY u.ip ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 5 && $chng) {
        $where = 'u.website LIKE :search';
        $wcnt = 'website LIKE :search';
        $order = 'ORDER BY u.website ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 6 && $chng) {
        $where = 'u.grp = :grp';
        $wcnt = 'grp = :grp';
        $order = 'ORDER BY u.id ASC';
        $params['grp'] = $chng;
    } elseif ($search == 7 && $chng) {
        $where = 'u.points >= :pts';
        $wcnt = 'points >= :pts';
        $order = 'ORDER BY u.id ASC';
        $params['pts'] = $chng;
    }
    $num = getVar('get', 'num', 'num', '1');
    $num = $num > 0 ? $num : 1;
    $offset = ($num - 1) * $conf['users']['anum'];
    $pars = $params;
    $params['offset'] = $offset;
    $params['limit'] = $conf['users']['anum'];
    $sql = 'SELECT u.id, u.name, u.email, u.website, u.regdate, u.lastvis, u.points, u.ip, u.gender, u.agent, g.name, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.grp) WHERE '.$where.' '.$order.' LIMIT :offset, :limit';
    $res = $db->getSqlQuery($sql,$params);
    if ($db->getSqlRowCount($res) > 0) {
        $head = $tpl->getHtmlFrag('admin-account-list-head', [
            'email_label' => _EMAIL,
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'ip_label' => _IP,
            'nickname_label' => _NICKNAME,
            'reg_label' => _REG,
        ]);
        $rows = '';
        while ([$uid, $name, $mail, $site, $reg, $last, $point, $ip, $gender, $agent, $gname, $gcolor] = $db->getSqlRow($res)) {
            $sgroup = $gname ? '<span style="color: '.$gcolor.'">'.$gname.'</span>' : _NO;
            $web = $site ? domain($site, 40) : _NO;
            $acts = adminMenuItems([
                adminLinkAction($afile.'.php?name=account&amp;op=add&amp;id='.$uid, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=security&amp;op=banlist&amp;new_ip='.$ip, _BANIPSENDER.' "'.$ip.'"?', _BANIPSENDER, _BANIPSENDER),
                adminDeleteAction($afile.'.php?name=account&amp;op=delete&amp;id='.$uid.'&amp;refer=1', _DELETE.' "'.$name.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-account-list-row', [
                'actions_html' => $acts,
                'email_html' => filterTextHighlight($mail, $chng),
                'id_html' => filterTextHighlight($uid, $chng),
                'ip_html' => filterTextHighlight(user_geo_ip($ip, 4), $chng),
                'nickname_html' => title_tip(_HASH.': '.md5($agent).'<br>'._LAST_VISIT.': '.format_time($last, _TIMESTRING).'<br>'._SPEC_GROUP.': '.$sgroup.'<br>'._SITE.': '.filterTextHighlight($web,$chng).'<br>'._GENDER.': '.gender($gender).'<br>'._POINTS.': '.$point).filterTextHighlight(user_info($name), $chng),
                'reg_text' => format_time($reg, _TIMESTRING),
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $lsear = $search ? '&amp;search='.$search : '';
        $lchg = $chng ? '&amp;chng='.$chng : '';
        $cont .= setArticleNumbers('pagenum', '', $conf['users']['anum'], 'name=account'.$lsear.$lchg.'&amp;', 'id', '_users', '', $wcnt, $conf['users']['anump'], $pars);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _USERNOEXIST]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id > 0) {
        $result = $db->getSqlQuery('SELECT id, name, rank, email, website, avatar, regdate, occ, origin, interest, sig, viewmail, password, storynum, blockon, block, theme, newslet, lang, points, warnings, access, grp, birthday, gender, field FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $id]);
        [$uid, $uname, $rank, $email, $site, $avatar, $reg, $occ, $from, $inter, $sig, $view, $pass, $story, $blockon, $block, $theme, $news, $lang, $point, $warn, $access, $group, $birth, $gender, $field] = $db->getSqlRow($result);
        $warn = ($warn) ? explode('|', $warn) : [];
    } else {
        $uid = getVar('post', 'uid', 'num', 0);
        $uname = getVar('post', 'uname', 'name', '');
        $rank = getVar('post', 'rank', 'text', '');
        $email = getVar('post', 'email', 'text', '');
        $site = getVar('post', 'site', 'url', 'http://');
        $avatar = getVar('post', 'avatar', 'text', '');
        $reg = getVar('post', 'reg', 'time', date('Y-m-d H:i:s'));
        $occ = getVar('post', 'occ', 'text', '');
        $from = getVar('post', 'from', 'text', '');
        $inter = getVar('post', 'inter', 'text', '');
        $sig = getVar('post', 'sig', 'text', '');
        $view = getVar('post', 'view', 'num', 0);
        $pass = getVar('post', 'pass', 'text', '');
        $story = getVar('post', 'story', 'num', (int)($conf['news']['num'] ?? 10));
        $blockon = getVar('post', 'blockon', 'num', 0);
        $block = getVar('post', 'block', 'text', '');
        $theme = getVar('post', 'theme', 'text', '');
        $news = getVar('post', 'news', 'num', 0);
        $lang = getVar('post', 'lang', 'text', '');
        $point = getVar('post', 'point', 'text', '0');
        $warn = getVar('post', 'warn', '', []);
        $access = getVar('post', 'access', 'num', 0);
        $group = getVar('post', 'group', 'num', 0);
        $birth = getVar('post', 'birth', 'time', '');
        $gender = getVar('post', 'gender', 'num', 0);
        $field = getVar('post', 'field', 'field', '');
    }
    $uname = (string)$uname;
    $rank = (string)$rank;
    $email = (string)$email;
    $site = (string)$site;
    $avatar = (string)$avatar;
    $reg = (string)$reg;
    $occ = (string)$occ;
    $from = (string)$from;
    $inter = (string)$inter;
    $sig = (string)$sig;
    $pass = (string)$pass;
    $block = (string)$block;
    $theme = (string)$theme;
    $lang = (string)$lang;
    $point = (string)$point;
    $birth = (string)$birth;
    $field = (string)$field;
    $warn = is_array($warn) ? $warn : [];
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
        'tab'  => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $rows = $tpl->getHtmlFrag('admin-account-add-basic-rows', [
        'allowusers_html' => radio_form($view, 'view'),
        'allowusers_label' => _ALLOWUSERS,
        'avatar_html' => $avatar ? getAdminFormRow(_AVATAR.':', getAdminTextInput('avatar', $avatar, 'sl_form', 'maxlength="255" placeholder="'._AVATAR.'"')) : '',
        'email_label' => _EMAIL.':',
        'email_value' => $email,
        'interests_label' => _INTERESTS.':',
        'interests_value' => $inter,
        'location_label' => _LOCATION.':',
        'location_value' => $from,
        'nickname_label' => _NICKNAME.':',
        'nickname_value' => $uname,
        'occupation_label' => _OCCUPATION.':',
        'occupation_value' => $occ,
        'rank_label' => _URANK.':',
        'rank_value' => $rank,
        'reg_html' => datetime(1, 'reg', $reg ?? '', 16, 'sl_form'),
        'reg_label' => _REG.':',
        'signature_html' => textarea('1', 'sig', $sig, 'account', '5', _SIGNATURE, ''),
        'signature_label_html' => getAdminHintLabel(_SIGNATURE, _SIGNATURE_TEXT),
        'siteurl_label' => _SITEURL.':',
        'siteurl_value' => $site,
    ]);
    if ($conf['users']['news'] == 1) {
        $storyopts = '';
        for ($n = 3; $n <= 20; $n++) {
            $storyopts .= getAdminOption((string)$n, (string)$n, $n == $story);
        }
        $rows .= getAdminFormRow(_C_12.':', getAdminSelect('story', $storyopts, 'sl_form'));
    } else {
        $rows .= getAdminHidden('story', (string)$conf['news']['num']);
    }
    $rows .= $tpl->getHtmlFrag('admin-account-add-menu-rows', [
        'activatepersonal_html' => radio_form($blockon, 'blockon'),
        'activatepersonal_label' => _ACTIVATEPERSONAL,
        'menuconf_html' => textarea('2', 'block', $block, 'account', '5', _MENUCONF, ''),
        'menuconf_label_html' => getAdminHintLabel(_MENUCONF, _MENUINFO),
    ]);
    if ($conf['users']['theme']) {
        $tcategory = '';
        $tcount = 0;
        foreach (scandir('templates') as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $tcategory .= getAdminOption($file, $file, $file == $theme);
                $tcount++;
            }
        }
        if ($tcount > 1) {
            $rows .= $tpl->getHtmlFrag('admin-account-theme-row', [
                'options_html' => $tcategory,
                'theme_label' => _THEME.':',
            ]);
        }
    }
    $rows .= getAdminFormRow(_RNEWSLETTER.':', radio_form($news, 'news'));
    if ($conf['multilingual'] == 1) {
        $rows .= $tpl->getHtmlFrag('admin-account-lang-row', [
            'lang_html' => language($lang),
            'language_label' => _LANGUAGE.':',
        ]);
    }
    $rows .= getAdminFormRow(_POINTS.':', getAdminNumberInput($point, 'point', 'sl_form', 'placeholder="'._POINTS.'"'));
    $warnhtml = '';
    $i = 0;
    while ($i < 5) {
        $a = $i + 1;
        $warnv = empty($warn[$i]) ? '' : $warn[$i];
        $warnhtml .= $tpl->getHtmlFrag('admin-account-warn-row', [
            'add_label' => _ADD,
            'index_text' => (string)$a,
            'is_hidden' => empty($warnv) && $i != 0,
            'next_id' => 'warn'.$a,
            'row_id' => 'warn'.$i,
            'warn_label' => _UWARN,
            'warn_value' => filterText($warnv),
        ]);
        $i++;
    }
    $rows .= getAdminFormWide($warnhtml)
        .getAdminFormRow(_UACESS, radio_form($access, 'access'));
    $grpopts = getAdminOption('0', _NO);
    $result = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups WHERE extra = :extra', ['extra' => '1']);
    while ([$grid, $grname] = $db->getSqlRow($result)) {
        $grpopts .= getAdminOption((string)$grid, $grname, $grid == $group);
    }
    $gender = intval($gender ?? 0);
    $rows .= getAdminFormRow(_SPEC_GROUP.':', getAdminSelect('group', $grpopts, 'sl_form'))
        .getAdminFormRow(_BIRTHDAY.':', datetime(2, 'birth', $birth, 10, 'sl_form'))
        .getAdminFormRow(_GENDER.':', get_gender('gender', $gender, 'sl_form'));
    $check = (getVar('cookie', 'sl_close_9', 'num') == 0) ? '' : ' checked';
    $mailblock = '<div id="sl_close_9">'.getAdminForm('', getAdminFormRow(getAdminHintLabel(_MAIL_TEXT, _MAIL_PASS_INFO), textarea('3', 'mailtext', replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])), 'account', '10', _MAIL_TEXT, ''), 'sl_form')).'</div>';
    $rows .= $tpl->getHtmlFrag('admin-account-add-tail-rows', [
        'check_attr' => $check,
        'fields_html' => fields_in($field, 'account'),
        'mail_sende_label' => _MAIL_SENDE,
        'mailblock_html' => $mailblock,
        'password_label' => _PASSWORD.':',
        'retypepassword_label' => _RETYPEPASSWORD.':',
        'save_html' => getAdminHidden('uid', (string)$uid).getAdminHidden('name', 'account').getAdminHidden('op', 'addsave').'<input type="submit" value="'._SAVE.'" class="sl_but_blue">',
    ]);
    $cont .= getAdminBox(getAdminForm($afile.'.php', $rows, '', 'sl_table_form', 'post', 'post'));
    echo $cont;
    setFoot();
}

function addsave(): void {
    global $db, $afile, $conf, $stop;
    $stop = [];
    $send = '';
    $uid = getVar('post', 'uid', 'num');
    $uname = getVar('post', 'uname', 'name');
    $rank = getVar('post', 'rank');
    $email = getVar('post', 'email');
    $site = getVar('post', 'site', 'url');
    $avatar = getVar('post', 'avatar', '', 'default/00.gif');
    $reg = getVar('req', 'reg', 'time');
    $occ = getVar('post', 'occ');
    $from = getVar('post', 'from');
    $inter = getVar('post', 'inter');
    $sig = getVar('post', 'sig', 'text');
    $view = getVar('post', 'view', 'num');
    $pass = getVar('post', 'pass');
    $pass2 = getVar('post', 'pass2');
    $story = getVar('post', 'story', 'num');
    $blockon = getVar('post', 'blockon', 'num');
    $block = getVar('post', 'block', 'text');
    $theme = getVar('post', 'theme');
    $news = getVar('post', 'news', 'num');
    $lang = getVar('post', 'lang');
    $point = getVar('post', 'point', 'num');
    $warn = isArray(getVar('post', 'warn[]', 'num')) ? filterText(implode('|', str_replace('|', '', getVar('post', 'warn[]', 'num')))) : 0;
    $access = getVar('post', 'access', 'num');
    $group = getVar('post', 'group');
    $birth = getVar('req', 'birth', 'date');
    $gender = getVar('post', 'gender');
    $field = getVar('post', 'field', 'field');
    $mail = getVar('post', 'mail', 'num');

    if (!$uid && (!$uname || !$email || !$pass || !$pass2)) $stop[] = _ERROR_ALL;
    if ($uname) {
        [$existId, $existName] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $uname]));
        [$tempId, $tempName] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_users_temp WHERE name = :name', ['name' => $uname]));
        if (($uid != $existId && $uname == $existName) || ($uid != $tempId && $uname == $tempName)) $stop[] = _USEREXIST;
        [$emailId, $existEmail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $email]));
        [$tempEmailId, $tempEmail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_users_temp WHERE email = :email', ['email' => $email]));
        if (($uid != $emailId && $email == $existEmail) || ($uid != $tempEmailId && $email == $tempEmail)) $stop[] = _ERROR_EMAIL;
    } else {
        $stop[] = _ERROR_ALL;
    }
    if (!analyze_name($uname)) $stop[] = _ERRORINVNICK;
    checkemail($email);
    if ($pass != $pass2) $stop[] = _ERROR_PASS;
    if (!$stop) {
        if ($uid) {
            if ($pass && $pass == $pass2) {
                $saltpass = getPassHash($pass);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET name = :name, rank = :rank, email = :email, website = :website, avatar = :avatar, regdate = :regdate, occ = :occ, origin = :from, interest = :interests, sig = :sig, viewmail = :viewemail, password = :password, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newsletter, lang = :lang, points = :points, warnings = :warnings, access = :access, grp = :group, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', [
                    'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                ]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET name = :name, rank = :rank, email = :email, website = :website, avatar = :avatar, regdate = :regdate, occ = :occ, origin = :from, interest = :interests, sig = :sig, viewmail = :viewemail, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newsletter, lang = :lang, points = :points, warnings = :warnings, access = :access, grp = :group, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', [
                    'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                ]);
            }
        } else {
            $saltpass = getPassHash($pass);
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (name, rank, email, website, avatar, regdate, occ, origin, interest, sig, viewmail, password, storynum, blockon, block, theme, newslet, lang, points, warnings, access, grp, birthday, gender, field) VALUES (:name, :rank, :email, :website, :avatar, :regdate, :occ, :from, :interests, :sig, :viewemail, :password, :storynum, :blockon, :block, :theme, :newsletter, :lang, :points, :warnings, :access, :group, :birthday, :gender, :field)', [
                'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field
            ]);
        }
        if ($mail) {
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$uname;
            $mailtext = getVar('post', 'mailtext', 'text');
            $msg = nl2br(filterReplaceText(filterMarkdown(str_replace('[pass]', $pass, str_replace('[login]', $uname, $mailtext)), 'account', false), 'account'), false);
            addMail($email, $conf['adminmail'], $subject, $msg, 0, 3);
            $send = '&send=1';
        }
        setRedirect($afile.'.php?name=account'.$send);
    } else {
        add();
    }
}

function newuser(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
        'tab'  => 2,
    ]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $conf['users']['anum'];
    $result = $db->getSqlQuery('SELECT id, name, email, password, regdate, code FROM '.PREFIX_DB.'_users_temp LIMIT :offset, :limit', ['offset' => $offset, 'limit' => $conf['users']['anum']]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-account-newuser-head', [
            'email_label' => _EMAIL,
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'nickname_label' => _NICKNAME,
            'password_label' => _PASSWORD,
            'reg_label' => _REG,
        ]);
        $rows = '';
        while ([$uid, $name, $mail, $pass, $reg, $check] = $db->getSqlRow($result)) {
            $acts = adminMenuItems([
                ad_status($conf['homeurl'].'/index.php?name=account&amp;op=activate&amp;user='.urlencode($name).'&amp;num='.$check, 0),
                adminDeleteAction($afile.'.php?name=account&amp;op=newdrop&amp;id='.$uid.'&amp;refer=1', _DELETE.' "'.$name.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-account-newuser-row', [
                'actions_html' => $acts,
                'email_text' => $mail,
                'id_text' => (string)$uid,
                'nickname_text' => $name,
                'password_text' => $pass,
                'reg_text' => $reg,
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', (int)$conf['users']['anum'], 'name=account&amp;op=newuser&amp;', 'id', '_users_temp', '', '', (int)$conf['users']['anump'], []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function pointreset(): void {
    global $afile, $tpl;
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
        'tab'  => 3,
    ]);
    $rows = $tpl->getHtmlFrag('admin-account-pointreset-rows', [
        'points_html' => radio_form(0, 'points'),
        'points_label' => _POINTS.':',
        'ratings_html' => radio_form(0, 'votes'),
        'ratings_label' => _RATINGS.':',
        'savechanges_label' => _SAVECHANGES,
        'signature_html' => radio_form(0, 'sig'),
        'signature_label' => _SIGNATURE.':',
        'uwarns_html' => radio_form(0, 'warnings'),
        'uwarns_label' => _UWARNS.':',
    ]);
    $hide = getAdminHidden('name', 'account').getAdminHidden('op', 'resave');
    $cont .= getAdminForm($afile.'.php', $rows, $hide, 'sl_table_conf');
    echo $cont;
    setFoot();
}

function resave(): void {
    global $db, $afile;
    $points = getVar('post', 'points', 'num');
    $votes = getVar('post', 'votes', 'num');
    $warnings = getVar('post', 'warnings', 'num');
    $sig = getVar('post', 'sig', 'num');
    if ($points == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET points = :zero', ['zero' => '0']);
    if ($votes == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET votes = :zero, tvotes = :zero', ['zero' => '0']);
    if ($warnings == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET warnings = :zero', ['zero' => '0']);
    if ($sig == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET sig = :empty', ['empty' => '']);
    setRedirect($afile.'.php?name=account');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
        'tab'  => 4,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/users.php');
    $minpass_opts = '';
    for ($n = 3; $n <= 10; $n++) {
        $minpass_opts .= getAdminOption((string)$n, (string)$n, $n == $conf['users']['minpass']);
    }
    $minpass_sel = getAdminSelect('minpass', $minpass_opts, 'sl_conf');
    $enter_sel = getAdminSelect('enter',
        getAdminOption('0', _LOGINL, $conf['users']['enter'] == '0')
        .getAdminOption('1', _LOGINF, $conf['users']['enter'] == '1'),
        'sl_conf'
    );
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'account',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_adir' => _ADIR,
        'adirectory' => $conf['users']['adirectory'],
        '_atype' => _ATYPE,
        'atypefile' => $conf['users']['atypefile'],
        '_asize' => _ASIZE,
        'amaxsize' => $conf['users']['amaxsize'],
        '_awidthin' => _AWIDTH._AIN,
        'awidth' => $conf['users']['awidth'],
        '_aheightin' => _AHEIGHT._AIN,
        'aheight' => $conf['users']['aheight'],
        '_voting_time' => _VOTING_TIME,
        'user' => intval($conf['users']['user_t'] / 86400),
        '_c34' => _C_34,
        'anum' => $conf['users']['anum'],
        '_c36' => _C_36,
        'anump' => $conf['users']['anump'],
        '_passwdlen' => _PASSWDLEN,
        's_minpass' => $minpass_sel,
        '_loginfl' => _LOGINFL,
        's_enter' => $enter_sel,
        '_update_points' => _UPDATE_POINTS,
        'r_point' => radio_form($conf['users']['point'], 'point'),
        '_aupload' => _AUPLOAD,
        'r_aupload' => radio_form($conf['users']['aupload'], 'aupload'),
        '_no_mail_reg' => _NO_MAIL_REG,
        'r_nomail' => radio_form($conf['users']['nomail'], 'nomail'),
        '_usershomenum' => _USERSHOMENUM,
        'r_news' => radio_form($conf['users']['news'], 'news'),
        '_useripcheck' => _USERIPCHECK,
        'r_check' => radio_form($conf['users']['check'], 'check'),
        '_regact' => _REGACT,
        'r_reg' => radio_form($conf['users']['reg'], 'reg'),
        '_seltheme' => _SELTHEME,
        'r_theme' => radio_form($conf['users']['theme'], 'theme'),
        '_profact' => _PROFACT,
        'r_prof' => radio_form($conf['users']['prof'], 'prof'),
        '_networkactive' => _NETWORKACTIVE,
        'r_network' => radio_form($conf['users']['network'], 'network'),
        '_rulact' => _RULACT,
        'r_rule' => radio_form($conf['users']['rule'], 'rule'),
        '_rules' => _RULES,
        'rules' => $conf['users']['rules'],
        '_networkcode' => _NETWORKCODE,
        't_code' => textarea_code('code', 'network', 'sl_conf', 'text/html', $conf['users']['network_c']),
        '_name_block' => _NAME_BLOCK,
        '_nokoma' => _NOKOMA,
        'name_b' => $conf['users']['name_b'],
        '_mail_block' => _MAIL_BLOCK,
        'mail_b' => $conf['users']['mail_b'],
        'account' => true,
    ]));
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
    $cont = [
        'adirectory' => getVar('post', 'adirectory', 'title'),
        'atypefile' => strtolower(strtr(getVar('post', 'atypefile', 'title', 'gif,jpg,jpeg,png'), $protect)),
        'amaxsize' => getVar('post', 'amaxsize', 'num', 51200),
        'awidth' => getVar('post', 'awidth', 'num', 100),
        'aheight' => getVar('post', 'aheight', 'num', 100),
        'user_t' => getVar('post', 'user', 'num', 30) * 86400,
        'anum' => getVar('post', 'anum', 'num', 50),
        'anump' => getVar('post', 'anump', 'num', 10),
        'minpass' => getVar('post', 'minpass', 'num'),
        'enter' => getVar('post', 'enter', 'num'),
        'point' => getVar('post', 'point', 'num'),
        'aupload' => getVar('post', 'aupload', 'num'),
        'nomail' => getVar('post', 'nomail', 'num'),
        'news' => getVar('post', 'news', 'num'),
        'check' => getVar('post', 'check', 'num'),
        'reg' => getVar('post', 'reg', 'num'),
        'theme' => getVar('post', 'theme', 'num'),
        'prof' => getVar('post', 'prof', 'num'),
        'network' => getVar('post', 'network', 'num'),
        'rule' => getVar('post', 'rule', 'num'),
        'rules' => getVar('post', 'rules', 'text'),
        'network_c' => "<<<HTML\n".getVar('post', 'network', 'text')."\nHTML",
        'name_b' => strtolower(strtr(getVar('post', 'name', 'text'), $protect)),
        'mail_b' => strtolower(strtr(getVar('post', 'mail', 'text'), $protect)),
        'points' => $conf['users']['points']
    ];
    setConfigFile('users.php', $cont);
    setRedirect($afile.'.php?name=account&op=config');
}

function newdrop(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=account', true);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE uid = :id', ['id' => $id]);
        # $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE uid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=account', true);
}

function info(): void {
    global $afile, $tpl;
    setHead();
    $_search = (int)getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => getAccountSearchBox($_search, $_chng),
        'tab'  => 5,
    ]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: account(); break;
    case 'add': add(); break;
    case 'addsave': addsave(); break;
    case 'newuser': newuser(); break;
    case 'newdrop': newdrop(); break;
    case 'delete': delete(); break;
    case 'pointreset': pointreset(); break;
    case 'resave': resave(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
