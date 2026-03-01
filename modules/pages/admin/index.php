<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('pages')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=conf', 'name=pages&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    return getAdminTabs(_PAGES, 'pages.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function pages(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['pages']['anum'] ?? 25;
    $anump = $conf['pages']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=pages&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 2, 0, 0);
    } else {
        $status = '1';
        $field = 'name=pages&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->sql_query('SELECT p.pid, p.catid, p.name, p.title, p.time, p.ip_sender, t.title, u.user_name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS t ON (p.catid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.user_id) WHERE p.status = :status ORDER BY p.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$pid, $catid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->sql_fetchrow($result)) {
            $ctitle = ($catid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status && time() >= strtotime($time)) {
                $view = '<a href="index.php?name=pages&amp;op=view&amp;id='.$pid.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$pid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=pages&amp;op=add&amp;id='.$pid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=pages&amp;op=del&amp;id='.$pid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'pid', '_pages', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num', 0);
    $pid = $id;
    if ($pid) {
        $result = $db->sql_query('SELECT p.catid, p.name, p.title, p.time, p.hometext, p.bodytext, p.ihome, p.acomm, u.user_name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.user_id) WHERE pid = :pid', ['pid' => $pid]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $ihome, $acomm, $nick] = $db->sql_fetchrow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $time = save_datetime(1, 'time');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $ihome = getVar('post', 'ihome', 'num', 0);
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if ($hometext) $cont .= preview($subject, $hometext, $bodytext, '', 'pages');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _PAGENOTE]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('pages', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, 'pages', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'pages', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="pages">'.ad_save('pid', $pid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = save_datetime(1, 'time');
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? text_filter(substr($postname, 0, 25)) : '';
        if ($pid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_pages SET catid = :cat, uid = :uid, name = :name, title = :title, time = :time, hometext = :hometext, bodytext = :bodytext, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE pid = :pid', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'hometext' => $hometext, 'bodytext' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'pid' => $pid]);
        } else {
            $ip = getip();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_pages (pid, catid, uid, name, title, time, hometext, bodytext, comments, counter, ihome, acomm, score, ratings, ip_sender, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :hometext, :bodytext, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :ip, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'hometext' => $hometext, 'bodytext' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=pages');
    } elseif ($posttype === 'delete') {
        del($pid);
    } else {
        add();
    }
}

function del(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_pages WHERE pid = :id', ['id' => $id]);
    }
    $refer = getVar('req', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=pages'.$refer);
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/pages.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['pages']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._PAGELINKNUM.':</td><td><input type="number" name="linknum" value="'.($conf['pages']['linknum'] ?? 10).'" class="sl_conf" placeholder="'._PAGELINKNUM.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.($conf['pages']['listnum'] ?? 10).'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($conf['pages']['num'] ?? 25).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['pages']['anum'] ?? 25).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($conf['pages']['nump'] ?? 10).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['pages']['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($conf['pages']['homcat'] ?? 0, 'homcat').'</td></tr>'
    .'<tr><td>'._VIEWCAT.'</td><td>'.radio_form($conf['pages']['viewcat'] ?? 0, 'viewcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($conf['pages']['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($conf['pages']['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['pages']['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._C_39.'</td><td>'.radio_form($conf['pages']['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._C_40.'</td><td>'.radio_form($conf['pages']['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._C_37.'</td><td>'.radio_form($conf['pages']['autor'] ?? 0, 'autor').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($conf['pages']['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($conf['pages']['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($conf['pages']['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($conf['pages']['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td>'._PAGELINK.'</td><td>'.radio_form($conf['pages']['link'] ?? 0, 'link').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="pages"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'linknum' => getVar('post', 'linknum', 'num', 10),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'viewcat' => getVar('post', 'viewcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'link' => getVar('post', 'link', 'num', 0),
    ];
    setConfigFile('pages.php', $cont);
    setRedirect($afile.'.php?name=pages&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 4, 0, 0).'<div id="repadm_info">'.adm_info(1, 'pages', 0).'</div>';
    setFoot();
}

switch ($op) {
    default: pages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}






