<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('faq')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    return getAdminTabs(_FAQ, 'faq.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function faq(): void {
    global $db, $afile, $conf, $confu;
    $cfg = $conf['faq'] ?? [];
    head();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $cfg['anum'] ?? 25;
    $anump = $cfg['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=faq&amp;status=1&amp;';
        $refer = '&op=faq&status=1';
        $cont = navi(0, 2, 0, 0);
    } else {
        $status = '1';
        $field = 'name=faq&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->sql_query('SELECT f.fid, f.catid, f.name, f.title, f.time, f.ip_sender, t.title, u.user_name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS t ON (f.catid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._QUESTION.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$fid, $catid, $uname, $title, $time, $ip_sender, $ctitle, $user_name] = $db->sql_fetchrow($result)) {
            $ctitle = ($catid) ? $ctitle : _NO;
            $ip_sender = ($ip_sender) ? user_geo_ip($ip_sender, 4) : _NO;
            $post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : ($confu['anonym'] ?? 'Anonym'));
            if ($status == '1' && time() >= strtotime($time)) {
                $ad_view = '<a href="index.php?name=faq&amp;op=view&amp;id='.$fid.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $ad_view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$fid.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip_sender).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($ad_view.'<a href="'.$afile.'.php?name=faq&amp;op=add&amp;id='.$fid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=faq&amp;op=del&amp;id='.$fid.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'fid', '_faq', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $stop, $confu;
    $fid = getVar('req', 'id', 'num', 0);
    if ($fid) {
        $result = $db->sql_query('SELECT s.catid, s.name, s.title, s.time, s.hometext, s.ihome, s.acomm, u.user_name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) WHERE fid = :fid', ['fid' => $fid]);
        [$cat, $uname, $subject, $time, $hometext, $ihome, $acomm, $user_name] = $db->sql_fetchrow($result);
        $postname = ($user_name) ? $user_name : (($uname) ? $uname : ($confu['anonym'] ?? 'Anonym'));
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = save_datetime(1, 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _PAGENOTE]);
    if ($hometext) $cont .= preview($subject, $hometext, '', '', 'faq');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.' / '._QUESTION.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_form" placeholder="'._TITLE.' / '._QUESTION.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('faq', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._ANSWER.':</td><td>'.textarea('1', 'hometext', $hometext, 'faq', '10', _ANSWER, '1').'</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="faq">'.ad_save('fid', $fid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $fid = getVar('post', 'fid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = save_datetime(1, 'time');
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
        $postname = (!is_user_id($postname)) ? text_filter(substr($postname, 0, 25)) : '';
        if ($fid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_faq SET catid = :cat, uid = :postid, name = :postname, title = :subject, time = :time, hometext = :hometext, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE fid = :fid', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'hometext' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
        } else {
            $ip = getip();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_faq (catid, uid, name, title, time, hometext, ihome, acomm, ip_sender, status) VALUES (:cat, :postid, :postname, :subject, :time, :hometext, :ihome, :acomm, :ip, \'1\')', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'hometext' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        header('Location: '.$afile.'.php?name=faq');
        exit;
    } elseif ($posttype == 'delete') {
        del($fid);
    } else {
        add();
    }
}

function del(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_faq WHERE fid = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    header('Location: '.$afile.'.php?name=faq'.$refer);
    exit;
}

function conf(): void {
    global $afile, $conf;
    $cfg = $conf['faq'] ?? [];
    head();
    $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms('faq.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($cfg['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._PAGELINKNUM.':</td><td><input type="number" name="linknum" value="'.($cfg['linknum'] ?? 0).'" class="sl_conf" placeholder="'._PAGELINKNUM.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.($cfg['listnum'] ?? 0).'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($cfg['num'] ?? 0).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($cfg['anum'] ?? 0).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($cfg['nump'] ?? 0).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($cfg['anump'] ?? 0).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($cfg['homcat'] ?? 0, 'homcat').'</td></tr>'
    .'<tr><td>'._VIEWCAT.'</td><td>'.radio_form($cfg['viewcat'] ?? 0, 'viewcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($cfg['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($cfg['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($cfg['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._C_39.'</td><td>'.radio_form($cfg['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._C_40.'</td><td>'.radio_form($cfg['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._C_37.'</td><td>'.radio_form($cfg['autor'] ?? 0, 'autor').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($cfg['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($cfg['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($cfg['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($cfg['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td>'._PAGELINK.'</td><td>'.radio_form($cfg['link'] ?? 0, 'link').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="faq"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
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
    setConfigFile('faq.php', $cont);
    header('Location: '.$afile.'.php?name=faq&op=conf');
    exit;
}

function info(): void {
    head();
    echo navi(0, 4, 0, 0).'<div id="repadm_info">'.adm_info(1, 'faq', 0).'</div>';
    foot();
}

switch ($op) {
    default: faq(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
