<?php
# Author: Eduard Laas
# Copyright ï¿½ 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('account')) die('Illegal file access');


function account(): void {
    global $db, $afile, $conf;
    $search = getVar('req', 'search', 'num');
    $chng = getVar('req', 'chng');
    setHead();
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
    ]);
    if (getVar('get','send','num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
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
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._IP.'</th><th>'._EMAIL.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$uid, $name, $mail, $site, $reg, $last, $point, $ip, $gender, $agent, $gname, $gcolor] = $db->getSqlRow($res)) {
            $sgroup = $gname ? '<span style="color: '.$gcolor.'">'.$gname.'</span>' : _NO;
            $web = $site ? domain($site, 40) : _NO;
            $cont .= '<tr><td>'.filterTextHighlight($uid, $chng).'</td>'
                .'<td>'.title_tip(_HASH.': '.md5($agent).'<br>'._LAST_VISIT.': '.format_time($last, _TIMESTRING).'<br>'._SPEC_GROUP.': '.$sgroup.'<br>'._SITE.': '.filterTextHighlight($web,$chng).'<br>'._GENDER.': '.gender($gender).'<br>'._POINTS.': '.$point).filterTextHighlight(user_info($name), $chng).'</td><td>'.filterTextHighlight(user_geo_ip($ip, 4), $chng).'</td><td>'.filterTextHighlight($mail, $chng).'</td><td>'.format_time($reg, _TIMESTRING).'</td><td>'.add_menu('<a href="'.$afile.'.php?name=account&amp;op=add&amp;id='.$uid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=security&amp;op=banlist&amp;new_ip='.$ip.'" OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.$ip.'&quot;?\');" title="'._BANIPSENDER.'">'._BANIPSENDER.'</a>||<a href="'.$afile.'.php?name=account&amp;op=delete&amp;id='.$uid.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $lsear = $search ? '&amp;search='.$search : '';
        $lchg = $chng ? '&amp;chng='.$chng : '';
        $cont .= setArticleNumbers('pagenum', '', $conf['users']['anum'], 'name=account'.$lsear.$lchg.'&amp;', 'id', '_users', '', $wcnt, $conf['users']['anump'], $pars);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _USERNOEXIST]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
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
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
        'tab'  => 1,
    ]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._NICKNAME.':</td><td><input type="text" name="uname" value="'.$uname.'" maxlength="25" class="sl_form" placeholder="'._NICKNAME.'" required></td></tr>'
    .'<tr><td>'._URANK.':</td><td><input type="text" name="rank" value="'.$rank.'" maxlength="25" class="sl_form" placeholder="'._URANK.'"></td></tr>'
    .'<tr><td>'._EMAIL.':</td><td><input type="email" name="email" value="'.$email.'" maxlength="255" class="sl_form" placeholder="'._EMAIL.'" required></td></tr>'
    .'<tr><td>'._SITEURL.':</td><td><input type="url" name="site" value="'.$site.'" maxlength="255" class="sl_form" placeholder="'._SITEURL.'"></td></tr>';
    if ($avatar) $cont .= '<tr><td>'._AVATAR.':</td><td><input type="text" name="avatar" value="'.$avatar.'" maxlength="255" class="sl_form" placeholder="'._AVATAR.'"></td></tr>';
    $cont .= '<tr><td>'._REG.':</td><td>'.datetime(1, 'reg', $reg ?? '', 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._OCCUPATION.':</td><td><input type="text" name="occ" value="'.$occ.'" maxlength="100" class="sl_form" placeholder="'._OCCUPATION.'"></td></tr>'
    .'<tr><td>'._LOCATION.':</td><td><input type="text" name="from" value="'.$from.'" maxlength="100" class="sl_form" placeholder="'._LOCATION.'"></td></tr>'
    .'<tr><td>'._INTERESTS.':</td><td><input type="text" name="inter" value="'.$inter.'" maxlength="150" class="sl_form" placeholder="'._INTERESTS.'"></td></tr>'
    .'<tr><td>'._SIGNATURE.':<div class="sl_small">'._SIGNATURE_TEXT.'</div></td><td>'.textarea('1', 'sig', $sig, 'account', '5', _SIGNATURE, '').'</td></tr>'
    .'<tr><td>'._ALLOWUSERS.'</td><td>'.radio_form($view, 'view').'</td></tr>';
    if ($conf['users']['news'] == 1) {
        $cont .= '<tr><td>'._C_12.':</td><td><select name="story" class="sl_form">';
        $xusnum = 3;
        while ($xusnum <= 20) {
            $sel = ($xusnum == $story) ? ' selected' : '';
            $cont .= '<option value="'.$xusnum.'"'.$sel.'>'.$xusnum.'</option>';
            $xusnum++;
        }
        $cont .= '</select></td></tr>';
    } else {
        $cont .= '<input type="hidden" name="story" value="'.$conf['news']['num'].'">';
    }
    $cont .= '<tr><td>'._ACTIVATEPERSONAL.'</td><td>'.radio_form($blockon, 'blockon').'</td></tr>'
    .'<tr><td>'._MENUCONF.':<div class="sl_small">'._MENUINFO.'</div></td><td>'.textarea('2', 'block', $block, 'account', '5', _MENUCONF, '').'</td></tr>';
    if ($conf['users']['theme']) {
        $tcategory = '';
        $tcount = 0;
        foreach (scandir('templates') as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $sel = ($file == $theme) ? ' selected' : '';
                $tcategory .= '<option value="'.$file.'"'.$sel.'>'.$file.'</option>';
                $tcount++;
            }
        }
        if ($tcount > 1) $cont .= '<tr><td>'._THEME.':</td><td><select name="theme" class="sl_form">'.$tcategory.'</select></td></tr>';
    }
    $cont .= '<tr><td>'._RNEWSLETTER.':</td><td>'.radio_form($news, 'news').'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language($lang).'</select></td></tr>';
    $cont .= '<tr><td>'._POINTS.':</td><td><input type="number" name="point" value="'.$point.'" class="sl_form" placeholder="'._POINTS.'"></td></tr>'
    .'<tr><td colspan="2">';
    $i = 0;
    while ($i < 5) {
        $a = $i + 1;
        $warnv = empty($warn[$i]) ? '' : $warn[$i];
        $class = (empty($warnv) && $i != 0) ? ' class="sl_none"' : '';
        $cont .= '<table id="warn'.$i.'"'.$class.'><tr><td><a OnClick="HideShow(\'warn'.$a.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._UWARN.' - '.$a.':</a></td><td><input type="text" name="warn[]" value="'.filterText($warnv).'" class="sl_form" placeholder="'._UWARN.' - '.$a.'"></td></tr></table>';
        $i++;
    }
    $cont .= '</td></tr>'
    .'<tr><td>'._UACESS.'</td><td>'.radio_form($access, 'access').'</td></tr>'
    .'<tr><td>'._SPEC_GROUP.':</td><td><select name="group" class="sl_form">'
    .'<option value="0">'._NO.'</option>';
    $result = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups WHERE extra = :extra', ['extra' => '1']);
    while ([$grid, $grname] = $db->getSqlRow($result)) {
        $sel = ($grid == $group) ? ' selected' : '';
        $cont .= '<option value="'.$grid.'"'.$sel.'>'.$grname.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._BIRTHDAY.':</td><td>'.datetime(2, 'birth', $birth, 10, 'sl_form').'</td></tr>'
    .'<tr><td>'._GENDER.':</td><td>'.get_gender('gender', $gender, 'sl_form').'</td></tr>';
    $check = (getVar('cookie', 'sl_close_9', 'num') == 0) ? '' : ' checked';
    $cont .= fields_in($field, 'account')
    .'<tr><td>'._PASSWORD.':</td><td><input type="password" name="pass" value="" maxlength="25" class="sl_form" placeholder="'._PASSWORD.'"></td></tr>'
    .'<tr><td>'._RETYPEPASSWORD.':</td><td><input type="password" name="pass2" value="" maxlength="25" class="sl_form" placeholder="'._RETYPEPASSWORD.'"></td></tr>'
    .'<tr><td>'._MAIL_SENDE.'</td><td><input type="checkbox" name="mail" value="1" OnClick="CloseOpen(\'sl_close_9\', 0);"'.$check.'></td></tr>'
    .'<tr><td colspan="2"><div id="sl_close_9"><table class="sl_table_form"><tr><td>'._MAIL_TEXT.':<div class="sl_small">'._MAIL_PASS_INFO.'</div></td><td class="sl_form">'.textarea('3', 'mailtext', replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])), 'account', '10', _MAIL_TEXT, '').'</td></tr></table></div></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="uid" value="'.$uid.'"><input type="hidden" name="name" value="account"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
    global $db, $afile, $conf;
    setHead();
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
        'tab'  => 2,
    ]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $conf['users']['anum'];
    $result = $db->getSqlQuery('SELECT id, name, email, password, regdate, code FROM '.PREFIX_DB.'_users_temp LIMIT :offset, :limit', ['offset' => $offset, 'limit' => $conf['users']['anum']]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._EMAIL.'</th><th>'._PASSWORD.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$uid, $name, $mail, $pass, $reg, $check] = $db->getSqlRow($result)) {
            $cont .= '<tr><td>'.$uid.'</td>'
            .'<td>'.$name.'</td>'
            .'<td>'.$mail.'</td>'
            .'<td>'.$pass.'</td>'
            .'<td>'.$reg.'</td>'
            .'<td>'.add_menu(ad_status($conf['homeurl'].'/index.php?name=account&amp;op=activate&amp;user='.urlencode($name).'&amp;num='.$check, 0).'||<a href="'.$afile.'.php?name=account&amp;op=newdrop&amp;id='.$uid.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', (int)$conf['users']['anum'], 'name=account&amp;op=newuser&amp;', 'id', '_users_temp', '', '', (int)$conf['users']['anump'], []);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function pointreset(): void {
    global $afile;
    setHead();
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
        'tab'  => 3,
    ]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._POINTS.':</td><td>'.radio_form(0, 'points').'</td></tr>'
    .'<tr><td>'._RATINGS.':</td><td>'.radio_form(0, 'votes').'</td></tr>'
    .'<tr><td>'._UWARNS.':</td><td>'.radio_form(0, 'warnings').'</td></tr>'
    .'<tr><td>'._SIGNATURE.':</td><td>'.radio_form(0, 'sig').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="account"><input type="hidden" name="op" value="resave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
    global $afile, $conf;
    setHead();
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
        'tab'  => 4,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/users.php');
    $minpass_opts = '';
    $xminpass = 3;
    while ($xminpass <= 10) {
        $minpass_opts .= '<option value="'.$xminpass.'"'.($xminpass == $conf['users']['minpass'] ? ' selected' : '').'>'.$xminpass.'</option>';
        $xminpass++;
    }
    $minpass_sel = '<select name="minpass" class="sl_conf">'.$minpass_opts.'</select>';
    $enter_sel = '<select name="enter" class="sl_conf">'
        .'<option value="0"'.($conf['users']['enter'] == '0' ? ' selected' : '').'>'._LOGINL.'</option>'
        .'<option value="1"'.($conf['users']['enter'] == '1' ? ' selected' : '').'>'._LOGINF.'</option>'
        .'</select>';
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'           => $afile,
        '{%module%}'          => 'account',
        '{%op%}'              => 'save',
        '{%save%}'            => _SAVECHANGES,
        '{%fields%}'          => '',
        '{%_adir%}'           => _ADIR,
        '{%adirectory%}'      => $conf['users']['adirectory'],
        '{%_atype%}'          => _ATYPE,
        '{%atypefile%}'       => $conf['users']['atypefile'],
        '{%_asize%}'          => _ASIZE,
        '{%amaxsize%}'        => $conf['users']['amaxsize'],
        '{%_awidthin%}'       => _AWIDTH._AIN,
        '{%awidth%}'          => $conf['users']['awidth'],
        '{%_aheightin%}'      => _AHEIGHT._AIN,
        '{%aheight%}'         => $conf['users']['aheight'],
        '{%_voting_time%}'    => _VOTING_TIME,
        '{%user%}'            => intval($conf['users']['user_t'] / 86400),
        '{%_c34%}'            => _C_34,
        '{%anum%}'            => $conf['users']['anum'],
        '{%_c36%}'            => _C_36,
        '{%anump%}'           => $conf['users']['anump'],
        '{%_passwdlen%}'      => _PASSWDLEN,
        '{%s_minpass%}'       => $minpass_sel,
        '{%_loginfl%}'        => _LOGINFL,
        '{%s_enter%}'         => $enter_sel,
        '{%_update_points%}'  => _UPDATE_POINTS,
        '{%r_point%}'         => radio_form($conf['users']['point'], 'point'),
        '{%_aupload%}'        => _AUPLOAD,
        '{%r_aupload%}'       => radio_form($conf['users']['aupload'], 'aupload'),
        '{%_no_mail_reg%}'    => _NO_MAIL_REG,
        '{%r_nomail%}'        => radio_form($conf['users']['nomail'], 'nomail'),
        '{%_usershomenum%}'   => _USERSHOMENUM,
        '{%r_news%}'          => radio_form($conf['users']['news'], 'news'),
        '{%_useripcheck%}'    => _USERIPCHECK,
        '{%r_check%}'         => radio_form($conf['users']['check'], 'check'),
        '{%_regact%}'         => _REGACT,
        '{%r_reg%}'           => radio_form($conf['users']['reg'], 'reg'),
        '{%_seltheme%}'       => _SELTHEME,
        '{%r_theme%}'         => radio_form($conf['users']['theme'], 'theme'),
        '{%_profact%}'        => _PROFACT,
        '{%r_prof%}'          => radio_form($conf['users']['prof'], 'prof'),
        '{%_networkactive%}'  => _NETWORKACTIVE,
        '{%r_network%}'       => radio_form($conf['users']['network'], 'network'),
        '{%_rulact%}'         => _RULACT,
        '{%r_rule%}'          => radio_form($conf['users']['rule'], 'rule'),
        '{%_rules%}'          => _RULES,
        '{%rules%}'           => $conf['users']['rules'],
        '{%_networkcode%}'    => _NETWORKCODE,
        '{%t_code%}'          => textarea_code('code', 'network', 'sl_conf', 'text/html', $conf['users']['network_c']),
        '{%_name_block%}'     => _NAME_BLOCK,
        '{%_nokoma%}'         => _NOKOMA,
        '{%name_b%}'          => $conf['users']['name_b'],
        '{%_mail_block%}'     => _MAIL_BLOCK,
        '{%mail_b%}'          => $conf['users']['mail_b'],
        'if_flag'             => ['account' => true],
    ]);
    $cont .= setTemplateBasic('close');
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
    global $afile;
    setHead();
    $_search = getVar('post', 'search');
    $_chng = getVar('post', 'chng');
    $_box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $_k => $_v) {
        $_sort = $_k + 1;
        $_box .= '<option value="'.$_sort.'"'.($_search == $_sort || (!$_search && $_sort == 2) ? ' selected' : '').'>'.$_v.'</option>';
    }
    $_box .= '</select> '.get_user_search('chng', $_chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $cont = setAdminNavi([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'sub'  => setTemplateBasic('searchbox', ['{%searchbox%}' => $_box]),
        'tab'  => 5,
    ]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
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
