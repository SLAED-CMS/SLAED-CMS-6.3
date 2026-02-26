<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('links')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO];
    return getAdminTabs(_LINKS, 'links.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function links(): void {
    global $db, $afile, $conf, $confu;
    $cfg = $conf['links'] ?? [];
    head();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $cfg['anum'] ?? 25;
    $anump = $cfg['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $status_req = getVar('get', 'status', 'num', 0);
    if ($status_req == 1) {
        $status = '0';
        $field = 'name=links&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 2, 0, 0);
    } elseif ($status_req == 2) {
        $status = '2';
        $field = 'name=links&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 3, 0, 0);
    } else {
        $status = '1';
        $field = 'name=links&amp;';
        $refer = '&amp;refer=1';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->sql_query('SELECT l.lid, l.cid, l.name, l.title, l.url, l.date, l.ip_sender, c.title, u.user_name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.user_id) WHERE l.status = :status ORDER BY l.date DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._SITEURL.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $url, $date, $ip_sender, $ctitle, $user_name] = $db->sql_fetchrow($result)) {
            $post = $user_name ? user_info($user_name) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip_sender = ($ip_sender) ? user_geo_ip($ip_sender, 4) : _NO;
            $broc = ($status == 2) ? '<a href="'.$afile.'.php?name=links&amp;op=ignore&amp;id='.$id.'" title="'._IGNORE.'">'._IGNORE.'</a>||' : '';
            if ($status && time() >= strtotime($date)) {
                $ad_view = '<a href="index.php?name=links&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $ad_view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip_sender).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 50).'</span></td>'
            .'<td>'.domain($url).'</td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($ad_view.$broc.'<a href="'.$afile.'.php?name=links&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=links&amp;op=del&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'lid', '_links', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $confu, $stop;
    $fid = getVar('req', 'id', 'num', 0);
    if ($fid) {
        $result = $db->sql_query('SELECT l.cid, l.name, l.title, l.description, l.bodytext, l.url, l.date, l.email, l.ihome, l.acomm, u.user_name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.user_id) WHERE l.lid = :fid', ['fid' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $url, $date, $email, $ihome, $acomm, $user_name] = $db->sql_fetchrow($result);
        $postname = $user_name ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $description = getVar('post', 'description', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $url = getVar('post', 'url', 'url', 'http://');
        $date = save_datetime(1, 'date');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $email = getVar('post', 'email', 'text', '');
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if (!empty($description)) $cont .= preview($title, $description, $bodytext, '', 'links');
    $link_url = (!empty($url) && $url !== 'http://') ? '<a href="'.$url.'" target="_blank" title="'._DOWNLLINK.'">'._URL.'</a>' : _URL;
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('links', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, 'links', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'links', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._AUEMAIL.':</td><td><input type="email" name="email" value="'.$email.'" class="sl_form" placeholder="'._AUEMAIL.'" required></td></tr>'
    .'<tr><td>'.$link_url.':</td><td><input type="url" name="url" value="'.$url.'" class="sl_form" placeholder="'._URL.'" required></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="links">'.ad_save('fid', $fid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $fid = getVar('post', 'fid', 'num', 0);
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $description = getVar('post', 'description', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $url = url_filter(getVar('post', 'url', 'text', ''));
    $date = save_datetime(1, 'date');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $email = text_filter(getVar('post', 'email', 'text', ''));
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$description) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$fid && $db->sql_numrows($db->sql_query('SELECT title FROM '.PREFIX_DB.'_links WHERE title = :title', ['title' => $title])) > 0) $stop[] = _LINKEXIST;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? text_filter(substr($postname, 0, 25)) : '';
        if ($fid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_links SET cid = :cid, uid = :uid, name = :name, title = :title, description = :description, bodytext = :bodytext, url = :url, date = :date, email = :email, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE lid = :fid', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'date' => $date, 'email' => $email, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
        } else {
            $ip = getip();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_links (cid, uid, name, title, description, bodytext, url, date, email, ip_sender, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :description, :bodytext, :url, :date, :email, :ip, :ihome, :acomm, \'1\')', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'date' => $date, 'email' => $email, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm]);
        }
        setRedirect($afile.'.php?name=links');
    } elseif ($posttype === 'delete') {
        del($fid);
    } else {
        add();
    }
}

function ignore(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) {
        $db->sql_query('UPDATE '.PREFIX_DB.'_links SET status = \'1\' WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links&status=2');
}

function del(int $dfid = 0): void {
    global $db, $afile;
    $id = $dfid ? $dfid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'links\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'links\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_links WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links');
}

function conf(): void {
    global $afile, $conf;
    $cfg = $conf['links'] ?? [];
    head();
    $cont = navi(0, 4, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/links.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
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
    .'<tr><td>'._L_8.'</td><td>'.radio_form($cfg['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._L_9.'</td><td>'.radio_form($cfg['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._L_11.'</td><td>'.radio_form($cfg['broc'] ?? 0, 'broc').'</td></tr>'
    .'<tr><td>'._L_12.'</td><td>'.radio_form($cfg['links'] ?? 0, 'links').'</td></tr>'
    .'<tr><td>'._C_37.'</td><td>'.radio_form($cfg['autor'] ?? 0, 'autor').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($cfg['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($cfg['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._L_1.'</td><td>'.radio_form($cfg['hits'] ?? 0, 'hits').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($cfg['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($cfg['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td>'._PAGELINK.'</td><td>'.radio_form($cfg['link'] ?? 0, 'link').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="links"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
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
        'broc' => getVar('post', 'broc', 'num', 0),
        'links' => getVar('post', 'links', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'hits' => getVar('post', 'hits', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'link' => getVar('post', 'link', 'num', 0),
    ];
    setConfigFile('links.php', $cont);
    setRedirect($afile.'.php?name=links&op=conf');
}

function info(): void {
    head();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.adm_info(1, 'links', 0).'</div>';
    foot();
}

switch ($op) {
    default: links(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'ignore': ignore(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}