<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('help')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=conf', 'name=help&amp;op=info'];
    $lang = [_HOME, _CLOSED, _PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function help(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['help']['anum'] ?? 25;
    $anump = $conf['help']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '1';
        $field = 'name=help&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 1, 0, 0);
    } else {
        $status = '0';
        $field = 'name=help&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.comments, s.ip, s.status, c.title, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.pid = \'0\' AND s.status = :status ORDER BY s.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th>'.cutstr(_MESSAGES, 4, 1).'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$sid, $catid, $title, $time, $comments, $ip, $stat, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($catid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : _ANONYM;
            $stat = ($stat) ? 0 : 1;
            $cont .= '<tr><td>'.$sid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.$comments.'</td>'
            .'<td>'.ad_status('', $stat).'</td>'
            .'<td>'.add_menu('<a href="'.$afile.'.php?name=help&amp;op=view&amp;id='.$sid.'" title="'._MVIEW.'">'._MVIEW.'</a>||<a href="'.$afile.'.php?name=help&amp;op=del&amp;id='.$sid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_help', '', 'pid = \'0\' AND status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    $result = $db->getSqlQuery('SELECT s.id, s.pid, s.uid, s.aid, s.title, s.time, s.hometext, s.field, s.counter, s.score, s.ratings, c.title, c.description, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :id1 OR s.pid = :id2 AND s.time <= now() ORDER BY s.time ASC', ['id1' => $id, 'id2' => $id]);
    setHead();
    $cont = navi(0, 0, 0, 0);
    $cont .= setTemplateBasic('open');
    $a = 0;
    while ([$sid, $pid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $title = ($title) ? $title : _MESSAGE.': '.$a;
        $fields = fields_out($field, 'help');
        $fields = ($fields) ? '<br><br>'.$fields : '';
        $text = $hometext.$fields;
        $post = $nick ? user_info($nick) : _ANONYM;
        $post = '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>';
        $date = '<span title="'._CHNGSTORY.'" class="sl_date">'.format_time($time, _TIMESTRING).'</span>';
        $comm = ($a) ? '<a href="#'.$sid.'" title="'._MESSAGE.': '.$a.'" class="sl_pnum">'.$a.'</a>' : '';
        $rating = ($haid && $huid != $haid) ? ajax_rating(0, $sid, 'help', $ratings, $score, '') : '';
        if (!$pid) {
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<span title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</span>' : '';
            $reads =  '<span title="'._READS.'" class="sl_views">'.$counter.'</span>';
        } else {
            $ctitle = '';
            $reads =  '';
        }
        $admin = add_menu('<a href="'.$afile.'.php?name=help&amp;op=add&amp;id='.$sid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=help&amp;op=del&amp;id='.$sid.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>');
        $cont .= setTemplateBasic('basic', ['{%ctitle%}' => $ctitle, '{%id%}' => $sid, '{%title%}' => $title, '{%text%}' => filterReplaceText(filterMarkdown($text, 'help', false), 'help'), '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin]);
        $a++;
    }
    $cont .= setTemplateBasic('close');
    $cont .= addview($id);
    echo $cont;
    setFoot();
}

function addview(int $id): string {
    global $db, $afile, $admin;
    $result = $db->getSqlQuery('SELECT cid, uid, status FROM '.PREFIX_DB.'_help WHERE id = :id', ['id' => $id]);
    [$catid, $uid, $status] = $db->getSqlRow($result);
    $cont = setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $admin[1] ?? '', '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', 'help', '10', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._HELPGLOS.'</td><td>'.radio_form($status, 'status').'</td></tr>'
    .'<tr><td>'._MAIL_SENDE.'</td><td>'.radio_form('1', 'umail').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="help"><input type="hidden" name="refer" value="1"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="cat" value="'.$catid.'"><input type="hidden" name="uid" value="'.$uid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="save"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    return $cont;
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num', 0);
    $sid = $id;
    $sid = $id;
    if ($sid) {
        $result = $db->getSqlQuery('SELECT s.pid, s.cid, s.title, s.time, s.hometext, s.field, s.status, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :sid', ['sid' => $sid]);
        [$pid, $cat, $subject, $time, $hometext, $field, $status, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: _ANONYM;
    } else {
        $sid = getVar('post', 'sid', 'num', 0);
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $field = getVar('post', 'field', 'field');
    }
    $status = getVar('post', 'status', 'num', 0) ? getVar('post', 'status', 'num', 0) : ($status ?? 0);
    setHead();
    $cont = navi(0, 0, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if (!empty($hometext)) $cont .= preview($subject, $hometext, '', $field, 'help');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>';
    if (!$pid) $cont .= '<tr><td>'._TITLE.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>';
    $cont .= '<tr><td>'._CATEGORY.':</td><td>'.getcat('help', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, 'help', '10', _TEXT, '1').'</td></tr>'
    .fields_in($field, 'help')
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="help"><input type="hidden" name="pid" value="'.$pid.'">'.ad_save('sid', $sid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $admin, $conf, $stop;
    $sid = getVar('post', 'sid', 'num', 0);
    $pid = getVar('post', 'pid', 'num', 0);
    $uid = getVar('post', 'uid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $field = getVar('post', 'field', 'field');
    $time = getVar('req', 'time', 'time');
    $status = getVar('post', 'status', 'num', 0);
    $umail = getVar('post', 'umail', 'text', '');
    $stop = [];
    if (!$subject && !$pid) $stop[] = _CERROR;
    if (!$hometext && !$pid) $stop[] = _CERROR1;
    if (!$postname && !$pid) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
        if ($sid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET cid = :cat, aid = :postid, title = :subject, time = :time, hometext = :hometext, field = :field WHERE id = :sid', ['cat' => $cat, 'postid' => $postid, 'subject' => $subject, 'time' => $time, 'hometext' => $hometext, 'field' => $field, 'sid' => $sid]);
            $hid = ($pid) ? $pid : $sid;
            setRedirect($afile.'.php?name=help&op=view&id='.$hid);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_help (pid, cid, uid, aid, title, time, hometext, field, ip, status) VALUES (:pid, :cat, :uid, :postid, :subject, now(), :hometext, \'\', :ip, \'0\')', ['pid' => $pid, 'cat' => $cat, 'uid' => $uid, 'postid' => $postid, 'subject' => $subject, 'hometext' => $hometext, 'ip' => $ip]);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
            if ($umail) {
                $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]);
                if ($db->getSqlRowCount($result) == 1) {
                    [$mail] = $db->getSqlRow($result);
                    $finishlink = ($conf['homeurl'] ?? '').'/index.php?name=help&amp;op=view&amp;id='.$pid;
                    $link = '<a href="'.$finishlink.'">'.$finishlink.'</a>';
                    $subject = ($conf['sitename'] ?? '').' - '._HELP;
                    $message = str_replace('[text]', sprintf(_ADDMAILU, substr($admin[1] ?? '', 0, 25), _HELP, $link), $conf['mtemp'] ?? '');
                    addMail($mail, $conf['adminmail'] ?? '', $subject, $message, 0, 3);
                }
            }
            setRedirect($afile.'.php?name=help');
        }
    } elseif ($posttype === 'delete') {
        del($sid);
    } else {
        add();
    }
}

function del(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'help\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_help WHERE id = :id1 OR pid = :id2', ['id1' => $id, 'id2' => $id]);
    }
    setRedirect($afile.'.php?name=help');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/help.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['help']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.($conf['help']['listnum'] ?? 0).'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($conf['help']['num'] ?? 0).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['help']['anum'] ?? 0).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($conf['help']['nump'] ?? 0).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['help']['anump'] ?? 0).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($conf['help']['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($conf['help']['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['help']['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._HELPADD.'</td><td>'.radio_form($conf['help']['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($conf['help']['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($conf['help']['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($conf['help']['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="help"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
    ];
    setConfigFile('help.php', $cont);
    setRedirect($afile.'.php?name=help&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: help(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}




