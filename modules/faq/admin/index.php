<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('faq')) die('Illegal file access');

function faq(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['faq']['anum'] ?? 25;
    $anump = $conf['faq']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=faq&amp;status=1&amp;';
        $refer = '&op=faq&status=1';
        $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=faq&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, t.title, u.name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS t ON (f.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._QUESTION.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status == '1' && time() >= strtotime($time)) {
                $view = '<a href="index.php?name=faq&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=faq&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=faq&amp;op=del&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_faq', '', 'status = \''.$status.'\'', $anump);
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
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.body, s.ihome, s.acomm, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE id = :fid', ['fid' => $fid]);
        [$cat, $uname, $subject, $time, $hometext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
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
    setFoot();
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
    $time = getVar('req', 'time', 'time');
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
        $postname = (!is_user_id($postname)) ? filterText(substr($postname, 0, 25)) : '';
        if ($fid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET cid = :cat, uid = :postid, name = :postname, title = :subject, time = :time, body = :body, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :fid', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_faq (cid, uid, name, title, time, body, ihome, acomm, ip, status) VALUES (:cat, :postid, :postname, :subject, :time, :body, :ihome, :acomm, :ip, \'1\')', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=faq');
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
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_faq WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=faq'.$refer);
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/faq.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'        => $afile,
        '{%module%}'       => 'faq',
        '{%op%}'           => 'saveconf',
        '{%save%}'         => _SAVECHANGES,
        '{%fields%}'       => '',
        '{%_cdefis%}'      => _CDEFIS,
        '{%defis%}'        => urldecode($conf['faq']['defis'] ?? ''),
        '{%_pagelinknum%}' => _PAGELINKNUM,
        '{%linknum%}'      => $conf['faq']['linknum'] ?? 0,
        '{%_c13%}'         => _C_13,
        '{%listnum%}'      => $conf['faq']['listnum'] ?? 0,
        '{%_c33%}'         => _C_33,
        '{%num%}'          => $conf['faq']['num'] ?? 0,
        '{%_c34%}'         => _C_34,
        '{%anum%}'         => $conf['faq']['anum'] ?? 0,
        '{%_c35%}'         => _C_35,
        '{%nump%}'         => $conf['faq']['nump'] ?? 0,
        '{%_c36%}'         => _C_36,
        '{%anump%}'        => $conf['faq']['anump'] ?? 0,
        '{%_homcat%}'      => _HOMCAT,
        '{%r_homcat%}'     => radio_form($conf['faq']['homcat'] ?? 0, 'homcat'),
        '{%_viewcat%}'     => _VIEWCAT,
        '{%r_viewcat%}'    => radio_form($conf['faq']['viewcat'] ?? 0, 'viewcat'),
        '{%_c32%}'         => _C_32,
        '{%r_catdesc%}'    => radio_form($conf['faq']['catdesc'] ?? 0, 'catdesc'),
        '{%_c15%}'         => _C_15,
        '{%r_subcat%}'     => radio_form($conf['faq']['subcat'] ?? 0, 'subcat'),
        '{%_addamail%}'    => _ADDAMAIL,
        '{%r_addmail%}'    => radio_form($conf['faq']['addmail'] ?? 0, 'addmail'),
        '{%_c39%}'         => _C_39,
        '{%r_add%}'        => radio_form($conf['faq']['add'] ?? 0, 'add'),
        '{%_c40%}'         => _C_40,
        '{%r_addquest%}'   => radio_form($conf['faq']['addquest'] ?? 0, 'addquest'),
        '{%_c37%}'         => _C_37,
        '{%r_autor%}'      => radio_form($conf['faq']['autor'] ?? 0, 'autor'),
        '{%_c17%}'         => _C_17,
        '{%r_date%}'       => radio_form($conf['faq']['date'] ?? 0, 'date'),
        '{%_c18%}'         => _C_18,
        '{%r_read%}'       => radio_form($conf['faq']['read'] ?? 0, 'read'),
        '{%_c19%}'         => _C_19,
        '{%r_rate%}'       => radio_form($conf['faq']['rate'] ?? 0, 'rate'),
        '{%_c20%}'         => _C_20,
        '{%r_letter%}'     => radio_form($conf['faq']['letter'] ?? 0, 'letter'),
        '{%_pagelink%}'    => _PAGELINK,
        '{%r_link%}'       => radio_form($conf['faq']['link'] ?? 0, 'link'),
        'if_flag'          => ['faq' => true],
    ]);
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
    setConfigFile('faq.php', $cont);
    setRedirect($afile.'.php?name=faq&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=faq', 'name=faq&amp;op=add', 'name=faq&amp;status=1', 'name=faq&amp;op=conf', 'name=faq&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: faq(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}





