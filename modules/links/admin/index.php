<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('links')) die('Illegal file access');

function links(): void {
    global $db, $afile, $conf;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['links']['anum'] ?? 25;
    $anump = $conf['links']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num', 0);
    if ($status == 1) {
        $status = '0';
        $field = 'name=links&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=links&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=links&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT l.id, l.cid, l.name, l.title, l.url, l.time, l.ip, c.title, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.status = :status ORDER BY l.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._SITEURL.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $url, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($status == 2) ? '<a href="'.$afile.'.php?name=links&amp;op=ignore&amp;id='.$id.'" title="'._IGNORE.'">'._IGNORE.'</a>||' : '';
            if ($status && time() >= strtotime($date)) {
                $view = '<a href="index.php?name=links&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 50).'</span></td>'
            .'<td>'.domain($url).'</td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.$broc.'<a href="'.$afile.'.php?name=links&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=links&amp;op=del&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_links', '', 'status = \''.$status.'\'', $anump);
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
        $result = $db->getSqlQuery('SELECT l.cid, l.name, l.title, l.intro, l.body, l.url, l.time, l.email, l.ihome, l.acomm, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.id = :fid', ['fid' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $site, $date, $email, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $description = getVar('post', 'description', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $site = getVar('post', 'site', 'url', 'http://');
        $date = getVar('req', 'date', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $email = getVar('post', 'email', 'text', '');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if (!empty($description)) $cont .= preview($title, $description, $bodytext, '', 'links');
    $link = (!empty($site) && $site !== 'http://') ? '<a href="'.$site.'" target="_blank" title="'._DOWNLLINK.'">'._URL.'</a>' : _URL;
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('links', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, 'links', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'links', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._AUEMAIL.':</td><td><input type="email" name="email" value="'.$email.'" class="sl_form" placeholder="'._AUEMAIL.'" required></td></tr>'
    .'<tr><td>'.$link.':</td><td><input type="url" name="site" value="'.$site.'" class="sl_form" placeholder="'._URL.'" required></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="links">'.ad_save('fid', $fid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $fid = getVar('post', 'fid', 'num', 0);
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $description = getVar('post', 'description', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $site = getVar('post', 'site', 'url', '');
    $date = getVar('req', 'date', 'time');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $email = getVar('post', 'email', 'text', '');
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$description) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$fid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_links WHERE title = :title', ['title' => $title])) > 0) $stop[] = _LINKEXIST;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($fid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET cid = :cid, uid = :uid, name = :name, title = :title, intro = :intro, body = :body, url = :url, time = :time, email = :email, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :fid', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $site, 'time' => $date, 'email' => $email, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_links (cid, uid, name, title, intro, body, url, time, email, ip, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :intro, :body, :url, :time, :email, :ip, :ihome, :acomm, \'1\')', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $site, 'time' => $date, 'email' => $email, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm]);
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
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET status = \'1\' WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links&status=2');
}

function del(int $dfid = 0): void {
    global $db, $afile;
    $id = $dfid ? $dfid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'links\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'links\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_links WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links');
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/links.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'        => $afile,
        '{%module%}'       => 'links',
        '{%op%}'           => 'saveconf',
        '{%save%}'         => _SAVECHANGES,
        '{%fields%}'       => '',
        '{%_cdefis%}'      => _CDEFIS,
        '{%defis%}'        => urldecode($conf['links']['defis'] ?? ''),
        '{%_pagelinknum%}' => _PAGELINKNUM,
        '{%linknum%}'      => $conf['links']['linknum'] ?? 0,
        '{%_c13%}'         => _C_13,
        '{%listnum%}'      => $conf['links']['listnum'] ?? 0,
        '{%_c33%}'         => _C_33,
        '{%num%}'          => $conf['links']['num'] ?? 0,
        '{%_c34%}'         => _C_34,
        '{%anum%}'         => $conf['links']['anum'] ?? 0,
        '{%_c35%}'         => _C_35,
        '{%nump%}'         => $conf['links']['nump'] ?? 0,
        '{%_c36%}'         => _C_36,
        '{%anump%}'        => $conf['links']['anump'] ?? 0,
        '{%_homcat%}'      => _HOMCAT,
        '{%r_homcat%}'     => radio_form($conf['links']['homcat'] ?? 0, 'homcat'),
        '{%_viewcat%}'     => _VIEWCAT,
        '{%r_viewcat%}'    => radio_form($conf['links']['viewcat'] ?? 0, 'viewcat'),
        '{%_c32%}'         => _C_32,
        '{%r_catdesc%}'    => radio_form($conf['links']['catdesc'] ?? 0, 'catdesc'),
        '{%_c15%}'         => _C_15,
        '{%r_subcat%}'     => radio_form($conf['links']['subcat'] ?? 0, 'subcat'),
        '{%_addamail%}'    => _ADDAMAIL,
        '{%r_addmail%}'    => radio_form($conf['links']['addmail'] ?? 0, 'addmail'),
        '{%_l8%}'          => _L_8,
        '{%r_add%}'        => radio_form($conf['links']['add'] ?? 0, 'add'),
        '{%_l9%}'          => _L_9,
        '{%r_addquest%}'   => radio_form($conf['links']['addquest'] ?? 0, 'addquest'),
        '{%_l11%}'         => _L_11,
        '{%r_broc%}'       => radio_form($conf['links']['broc'] ?? 0, 'broc'),
        '{%_l12%}'         => _L_12,
        '{%r_links%}'      => radio_form($conf['links']['links'] ?? 0, 'links'),
        '{%_c37%}'         => _C_37,
        '{%r_autor%}'      => radio_form($conf['links']['autor'] ?? 0, 'autor'),
        '{%_c17%}'         => _C_17,
        '{%r_date%}'       => radio_form($conf['links']['date'] ?? 0, 'date'),
        '{%_c18%}'         => _C_18,
        '{%r_read%}'       => radio_form($conf['links']['read'] ?? 0, 'read'),
        '{%_l1%}'          => _L_1,
        '{%r_hits%}'       => radio_form($conf['links']['hits'] ?? 0, 'hits'),
        '{%_c19%}'         => _C_19,
        '{%r_rate%}'       => radio_form($conf['links']['rate'] ?? 0, 'rate'),
        '{%_c20%}'         => _C_20,
        '{%r_letter%}'     => radio_form($conf['links']['letter'] ?? 0, 'letter'),
        '{%_pagelink%}'    => _PAGELINK,
        '{%r_link%}'       => radio_form($conf['links']['link'] ?? 0, 'link'),
        'if_flag'          => ['links' => true],
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
    setHead();
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=conf', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 5]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: links(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'ignore': ignore(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}







