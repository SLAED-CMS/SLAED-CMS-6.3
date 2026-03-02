<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('account')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    global $afile;
    $search = getVar('post', 'search');
    $chng = getVar('post', 'chng');
    $ops = ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=null', 'name=account&amp;op=conf', 'name=account&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO];
    $box = '<form method="post" action="'.$afile.'.php">'._SEARCH.': <select name="search">';
    $priv = [_ID, _NICKNAME, _EMAIL, _IP, _URL];
    foreach ($priv as $key => $value) {
        $sort = $key + 1;
        $sel = ($search == $sort || (!$search && $sort == 2)) ? ' selected' : '';
        $box .= '<option value="'.$sort.'"'.$sel.'>'.$value.'</option>';
    }
    $box .= '</select> '.get_user_search('chng', $chng, '30').' <input type="hidden" name="name" value="account"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $box = setTemplateBasic('searchbox', ['{%searchbox%}' => $box]);
    return getAdminTabs(_USERS, 'users.png', $box, $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

function account(): void {
    global $db, $afile, $conf;
    $search = getVar('req', 'search', 'num');
    $chng = getVar('req', 'chng');
    setHead();
    $cont = navi(0, 0, 0, 0);
    if (getVar('get','send','num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
    $where = '1 = 1';
    $order = 'ORDER BY u.user_id DESC';
    $params = [];
    if ($search == 1 && $chng) {
        $where = 'user_id LIKE :search';
        $order = 'ORDER BY u.user_id ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 2 && $chng) {
        $where = 'user_name LIKE :search';
        $order = 'ORDER BY u.user_name ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 3 && $chng) {
        $where = 'user_email LIKE :search';
        $order = 'ORDER BY u.user_email ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 4 && $chng) {
        $where = 'user_last_ip LIKE :search';
        $order = 'ORDER BY u.user_last_ip ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 5 && $chng) {
        $where = 'user_website LIKE :search';
        $order = 'ORDER BY u.user_website ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 6 && $chng) {
        $where = 'user_group = :grp';
        $order = 'ORDER BY u.user_id ASC';
        $params['grp'] = $chng;
    } elseif ($search == 7 && $chng) {
        $where = 'user_points >= :pts';
        $order = 'ORDER BY u.user_id ASC';
        $params['pts'] = $chng;
    }
    $num = getVar('get', 'num', 'num', '1');
    $num = $num > 0 ? $num : 1;
    $offset = ($num - 1) * $conf['users']['anum'];
    $pars = $params;
    $params['offset'] = $offset;
    $params['limit'] = $conf['users']['anum'];
    $sql = 'SELECT u.user_id, u.user_name, u.user_email, u.user_website, u.user_regdate, u.user_lastvisit, u.user_points, u.user_last_ip, u.user_gender, u.user_agent, g.name, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.user_group) WHERE '.$where.' '.$order.' LIMIT :offset, :limit';
    $res = $db->getSqlQuery($sql,$params);
    if ($db->getSqlRowCount($res) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._IP.'</th><th>'._EMAIL.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$uid, $name, $mail, $site, $reg, $last, $point, $ip, $gender, $agent, $gname, $gcolor] = $db->getSqlRow($res)) {
            $sgroup = $gname ? '<span style="color: '.$gcolor.'">'.$gname.'</span>' : _NO;
            $web = $site ? domain($site, 40) : _NO;
            $cont .= '<tr><td>'.filterTextHighlight($uid, $chng).'</td>'
                .'<td>'.title_tip(_HASH.': '.md5($agent).'<br>'._LAST_VISIT.': '.format_time($last, _TIMESTRING).'<br>'._SPEC_GROUP.': '.$sgroup.'<br>'._SITE.': '.filterTextHighlight($web,$chng).'<br>'._GENDER.': '.gender($gender).'<br>'._POINTS.': '.$point).filterTextHighlight(user_info($name), $chng).'</td><td>'.filterTextHighlight(user_geo_ip($ip, 4), $chng).'</td><td>'.filterTextHighlight($mail, $chng).'</td><td>'.format_time($reg, _TIMESTRING).'</td><td>'.add_menu('<a href="'.$afile.'.php?name=account&amp;op=add&amp;id='.$uid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=security&amp;op=block&amp;new_ip='.$ip.'" OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.$ip.'&quot;?\');" title="'._BANIPSENDER.'">'._BANIPSENDER.'</a>||<a href="'.$afile.'.php?name=account&amp;op=del&amp;id='.$uid.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $lsear = $search ? '&amp;search='.$search : '';
        $lchg = $chng ? '&amp;chng='.$chng : '';
        $cont .= setArticleNumbers('pagenum', '', $conf['users']['anum'], 'name=account'.$lsear.$lchg.'&amp;', 'user_id', '_users', '', $where, $conf['users']['anump'], $pars);
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
    if (is_numeric($id)) {
        $result = $db->getSqlQuery('SELECT user_id, user_name, user_rank, user_email, user_website, user_avatar, user_regdate, user_occ, user_from, user_interests, user_sig, user_viewemail, user_password, user_storynum, user_blockon, user_block, user_theme, user_newsletter, user_lang, user_points, user_warnings, user_acess, user_group, user_birthday, user_gender, user_field FROM '.PREFIX_DB.'_users WHERE user_id = :id', ['id' => $id]);
        [$uid, $uname, $rank, $email, $site, $avatar, $reg, $occ, $from, $inter, $sig, $view, $pass, $story, $blockon, $block, $theme, $news, $lang, $point, $warn, $access, $group, $birth, $gender, $field] = $db->getSqlRow($result);
        $warn = ($warn) ? explode('|', $warn) : [];
    } else {
        $uid = getVar('post', 'uid', 'num');
        $uname = getVar('post', 'uname', 'name');
        $rank = getVar('post', 'rank');
        $email = getVar('post', 'email');
        $site = getVar('post', 'site', 'url', 'http://');
        $avatar = getVar('post', 'avatar');
        $reg = getVar('post', 'reg');
        $occ = getVar('post', 'occ');
        $from = getVar('post', 'from');
        $inter = getVar('post', 'inter');
        $sig = getVar('post', 'sig', 'text');
        $view = getVar('post', 'view', 'num');
        $pass = getVar('post', 'pass');
        $story = getVar('post', 'story', 'num');
        $blockon = getVar('post', 'blockon', 'num');
        $block = getVar('post', 'block', 'text');
        $theme = getVar('post', 'theme');
        $news = getVar('post', 'news', 'num');
        $lang = getVar('post', 'lang');
        $point = getVar('post', 'point');
        $warn = getVar('post', 'warn');
        $access = getVar('post', 'access', 'num');
        $group = getVar('post', 'group');
        $birth = getVar('post', 'birth');
        $gender = getVar('post', 'gender');
        $field = getVar('post', 'field', 'field');
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
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
        $cont .= '<table id="warn'.$i.'"'.$class.'><tr><td><a OnClick="HideShow(\'warn'.$a.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._UWARN.' - '.$a.':</a></td><td><input type="text" name="warn[]" value="'.text_filter($warnv).'" class="sl_form" placeholder="'._UWARN.' - '.$a.'"></td></tr></table>';
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
    $reg = save_datetime(1, 'reg');
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
    $warn = isArray(getVar('post', 'warn[]', 'num')) ? text_filter(implode('|', str_replace('|', '', getVar('post', 'warn[]', 'num')))) : 0;
    $access = getVar('post', 'access', 'num');
    $group = getVar('post', 'group');
    $birth = save_datetime(2, 'birth');
    $gender = getVar('post', 'gender');
    $field = getVar('post', 'field', 'field');
    $mail = getVar('post', 'mail', 'num');

    if (!$uid && (!$uname || !$email || !$pass || !$pass2)) $stop[] = _ERROR_ALL;
    if ($uname) {
        [$existId, $existName] = $db->getSqlRow($db->getSqlQuery('SELECT user_id, user_name FROM '.PREFIX_DB.'_users WHERE user_name = :name', ['name' => $uname]));
        [$tempId, $tempName] = $db->getSqlRow($db->getSqlQuery('SELECT user_id, user_name FROM '.PREFIX_DB.'_users_temp WHERE user_name = :name', ['name' => $uname]));
        if (($uid != $existId && $uname == $existName) || ($uid != $tempId && $uname == $tempName)) $stop[] = _USEREXIST;
        [$emailId, $existEmail] = $db->getSqlRow($db->getSqlQuery('SELECT user_id, user_email FROM '.PREFIX_DB.'_users WHERE user_email = :email', ['email' => $email]));
        [$tempEmailId, $tempEmail] = $db->getSqlRow($db->getSqlQuery('SELECT user_id, user_email FROM '.PREFIX_DB.'_users_temp WHERE user_email = :email', ['email' => $email]));
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
                $saltpass = md5_salt($pass);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_name = :name, user_rank = :rank, user_email = :email, user_website = :website, user_avatar = :avatar, user_regdate = :regdate, user_occ = :occ, user_from = :from, user_interests = :interests, user_sig = :sig, user_viewemail = :viewemail, user_password = :password, user_storynum = :storynum, user_blockon = :blockon, user_block = :block, user_theme = :theme, user_newsletter = :newsletter, user_lang = :lang, user_points = :points, user_warnings = :warnings, user_acess = :acess, user_group = :group, user_birthday = :birthday, user_gender = :gender, user_field = :field WHERE user_id = :id', [
                    'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'acess' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                ]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_name = :name, user_rank = :rank, user_email = :email, user_website = :website, user_avatar = :avatar, user_regdate = :regdate, user_occ = :occ, user_from = :from, user_interests = :interests, user_sig = :sig, user_viewemail = :viewemail, user_storynum = :storynum, user_blockon = :blockon, user_block = :block, user_theme = :theme, user_newsletter = :newsletter, user_lang = :lang, user_points = :points, user_warnings = :warnings, user_acess = :acess, user_group = :group, user_birthday = :birthday, user_gender = :gender, user_field = :field WHERE user_id = :id', [
                    'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'acess' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                ]);
            }
        } else {
            $saltpass = md5_salt($pass);
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (user_name, user_rank, user_email, user_website, user_avatar, user_regdate, user_occ, user_from, user_interests, user_sig, user_viewemail, user_password, user_storynum, user_blockon, user_block, user_theme, user_newsletter, user_lang, user_points, user_warnings, user_acess, user_group, user_birthday, user_gender, user_field) VALUES (:name, :rank, :email, :website, :avatar, :regdate, :occ, :from, :interests, :sig, :viewemail, :password, :storynum, :blockon, :block, :theme, :newsletter, :lang, :points, :warnings, :acess, :group, :birthday, :gender, :field)', [
                'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warn, 'acess' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field
            ]);
        }
        if ($mail) {
            $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$uname;
            $mailtext = getVar('post', 'mailtext', 'text');
            $msg = nl2br(bb_decode(str_replace('[pass]', $pass, str_replace('[login]', $uname, $mailtext)), 'account'), false);
            mail_send($email, $conf['adminmail'], $subject, $msg, 0, 3);
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
    $cont = navi(0, 2, 0, 0);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $conf['users']['anum'];
    $result = $db->getSqlQuery('SELECT user_id, user_name, user_email, user_password, user_regdate, check_num FROM '.PREFIX_DB.'_users_temp LIMIT :offset, :limit', ['offset' => $offset, 'limit' => $conf['users']['anum']]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._EMAIL.'</th><th>'._PASSWORD.'</th><th>'._REG.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$uid, $name, $mail, $pass, $reg, $check] = $db->getSqlRow($result)) {
            $cont .= '<tr><td>'.$uid.'</td>'
            .'<td>'.$name.'</td>'
            .'<td>'.$mail.'</td>'
            .'<td>'.$pass.'</td>'
            .'<td>'.$reg.'</td>'
            .'<td>'.add_menu(ad_status($conf['homeurl'].'/index.php?name=account&amp;op=activate&amp;user='.urlencode($name).'&amp;num='.$check, 0).'||<a href="'.$afile.'.php?name=account&amp;op=newdel&amp;id='.$uid.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', (int)$conf['users']['anum'], 'name=account&amp;op=newuser&amp;', 'user_id', '_users_temp', '', '', (int)$conf['users']['anump'], []);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function nullpoints(): void {
    global $afile;
    setHead();
    $cont = navi(0, 3, 0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._POINTS.':</td><td>'.radio_form(0, 'points').'</td></tr>'
    .'<tr><td>'._RATINGS.':</td><td>'.radio_form(0, 'votes').'</td></tr>'
    .'<tr><td>'._UWARNS.':</td><td>'.radio_form(0, 'warnings').'</td></tr>'
    .'<tr><td>'._SIGNATURE.':</td><td>'.radio_form(0, 'sig').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="account"><input type="hidden" name="op" value="nullsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function nullsave(): void {
    global $db, $afile;
    $points = getVar('post', 'points', 'num');
    $votes = getVar('post', 'votes', 'num');
    $warnings = getVar('post', 'warnings', 'num');
    $sig = getVar('post', 'sig', 'num');
    if ($points == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_points = :zero', ['zero' => '0']);
    if ($votes == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_votes = :zero, user_totalvotes = :zero', ['zero' => '0']);
    if ($warnings == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_warnings = :zero', ['zero' => '0']);
    if ($sig == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET user_sig = :empty', ['empty' => '']);
    setRedirect($afile.'.php?name=account');
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = navi(0, 4, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/users.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._ADIR.':</td><td><input type="text" name="adirectory" value="'.$conf['users']['adirectory'].'" class="sl_conf" placeholder="'._ADIR.'" required></td></tr>'
    .'<tr><td>'._ATYPE.':</td><td><input type="text" name="atypefile" value="'.$conf['users']['atypefile'].'" class="sl_conf" placeholder="'._ATYPE.'" required></td></tr>'
    .'<tr><td>'._ASIZE.':</td><td><input type="number" name="amaxsize" value="'.$conf['users']['amaxsize'].'" class="sl_conf" placeholder="'._ASIZE.'" required></td></tr>'
    .'<tr><td>'._AWIDTH._AIN.':</td><td><input type="number" name="awidth" value="'.$conf['users']['awidth'].'" class="sl_conf" placeholder="'._AWIDTH._AIN.'" required></td></tr>'
    .'<tr><td>'._AHEIGHT._AIN.':</td><td><input type="number" name="aheight" value="'.$conf['users']['aheight'].'" class="sl_conf" placeholder="'._AHEIGHT._AIN.'" required></td></tr>'
    .'<tr><td>'._VOTING_TIME.':</td><td><input type="number" name="user" value="'.intval($conf['users']['user_t'] / 86400).'" class="sl_conf" placeholder="'._VOTING_TIME.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['users']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['users']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._PASSWDLEN.':</td><td><select name="minpass" class="sl_conf">';
    $xminpass = 3;
    while ($xminpass <= 10) {
        $sel = ($xminpass == $conf['users']['minpass']) ? ' selected' : '';
        $cont .= '<option value="'.$xminpass.'"'.$sel.'>'.$xminpass.'</option>';
        $xminpass++;
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._LOGINFL.':</td><td>'
    .'<select name="enter" class="sl_conf">'
    .'<option value="0"';
    if ($conf['users']['enter'] == '0') $cont .= ' selected';
    $cont .= '>'._LOGINL.'</option>'
    .'<option value="1"';
    if ($conf['users']['enter'] == '1') $cont .= ' selected';
    $cont .= '>'._LOGINF.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._UPDATE_POINTS.'</td><td>'.radio_form($conf['users']['point'], 'point').'</td></tr>'
    .'<tr><td>'._AUPLOAD.'</td><td>'.radio_form($conf['users']['aupload'], 'aupload').'</td></tr>'
    .'<tr><td>'._NO_MAIL_REG.'</td><td>'.radio_form($conf['users']['nomail'], 'nomail').'</td></tr>'
    .'<tr><td>'._USERSHOMENUM.'</td><td>'.radio_form($conf['users']['news'], 'news').'</td></tr>'
    .'<tr><td>'._USERIPCHECK.'</td><td>'.radio_form($conf['users']['check'], 'check').'</td></tr>'
    .'<tr><td>'._REGACT.'</td><td>'.radio_form($conf['users']['reg'], 'reg').'</td></tr>'
    .'<tr><td>'._SELTHEME.'</td><td>'.radio_form($conf['users']['theme'], 'theme').'</td></tr>'
    .'<tr><td>'._PROFACT.'</td><td>'.radio_form($conf['users']['prof'], 'prof').'</td></tr>'
    .'<tr><td>'._NETWORKACTIVE.'</td><td>'.radio_form($conf['users']['network'], 'network').'</td></tr>'
    .'<tr><td>'._RULACT.'</td><td>'.radio_form($conf['users']['rule'], 'rule').'</td></tr>'
    .'<tr><td>'._RULES.':</td><td><textarea name="rules" cols="65" rows="10" class="sl_conf" placeholder="'._RULES.'">'.$conf['users']['rules'].'</textarea></td></tr>'
    .'<tr><td>'._NETWORKCODE.':</td><td>'.textarea_code('code', 'network', 'sl_conf', 'text/html', $conf['users']['network_c']).'</td></tr>'
    .'<tr><td>'._NAME_BLOCK.':<div class="sl_small">'._NOKOMA.'</div></td><td><textarea name="name" cols="65" rows="5" class="sl_conf" placeholder="'._NAME_BLOCK.'">'.$conf['users']['name_b'].'</textarea></td></tr>'
    .'<tr><td>'._MAIL_BLOCK.':<div class="sl_small">'._NOKOMA.'</div></td><td><textarea name="mail" cols="65" rows="5" class="sl_conf" placeholder="'._MAIL_BLOCK.'">'.$conf['users']['mail_b'].'</textarea></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="account"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
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
    setRedirect($afile.'.php?name=account&op=conf');
}

function newdel(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE user_id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=account', true);
}

function del(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE user_id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE uid = :id', ['id' => $id]);
        # $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE uid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=account', true);
}

function info(): void {
    setHead();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: account(); break;
    case 'add': add(); break;
    case 'addsave': addsave(); break;
    case 'newuser': newuser(); break;
    case 'newdel': newdel(); break;
    case 'del': del(); break;
    case 'null': nullpoints(); break;
    case 'nullsave': nullsave(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}


