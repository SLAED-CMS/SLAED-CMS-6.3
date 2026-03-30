<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('links')) die('Illegal file access');

function links(): void {
    global $db, $afile, $conf, $tpl;
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
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=links&amp;status=2&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=links&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT l.id, l.cid, l.name, l.title, l.url, l.time, l.ip, c.title, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.status = :status ORDER BY l.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-links-list-head', [
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'postedby_label' => _POSTEDBY,
            'siteurl_label' => _SITEURL,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $cid, $uname, $title, $url, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($status == 2) ? adminLinkAction($afile.'.php?name=links&amp;op=approve&amp;id='.$id, _IGNORE, _IGNORE) : '';
            if ($status && time() >= strtotime($date)) {
                $view = adminLinkAction('index.php?name=links&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW);
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $acts = adminMenuItems([
                $view,
                $broc,
                adminLinkAction($afile.'.php?name=links&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=links&amp;op=delete&amp;id='.$id.$refer, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-links-list-row', [
                'actions_html' => $acts,
                'id_text' => (string)$id,
                'postedby_html' => $post,
                'site_html' => domain($url),
                'status_html' => ad_status('', $active),
                'title_html' => adminTitleTipLabel(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip, $title, cutstr($title, 50)),
            ]));
        }
        $cont .= getTplAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_links', '', 'status = \''.$status.'\'', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
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
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if (!empty($description)) $cont .= preview($title, $description, $bodytext, '', 'links');
    $link = (!empty($site) && $site !== 'http://') ? '<a href="'.$site.'" target="_blank" title="'._DOWNLLINK.'">'._URL.'</a>' : _URL;
    $rows = $tpl->getHtmlFrag('admin-links-add-rows', [
        'acomm_html' => com_access('acomm', $acomm, 'sl_form'),
        'acomm_label' => _COMMENTS.':',
        'bodytext_html' => textarea('2', 'bodytext', $bodytext, 'links', '15', _ENDTEXT, '0'),
        'bodytext_label' => _ENDTEXT.':',
        'cat_html' => getcat('links', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>'),
        'cat_label' => _CATEGORY.':',
        'date_html' => datetime(1, 'date', $date, 16, 'sl_form'),
        'date_label' => _CHNGSTORY.':',
        'description_html' => textarea('1', 'description', $description, 'links', '5', _TEXT, '1'),
        'description_label' => _TEXT.':',
        'email_label' => _AUEMAIL.':',
        'email_value' => $email,
        'ihome_html' => radio_form($ihome, 'ihome'),
        'ihome_label' => _PUBHOME,
        'postname_html' => getUserSearch('postname', $postname, '25', 'sl_form', '1'),
        'postname_label' => _POSTEDBY.':',
        'save_html' => ad_save('fid', $fid, 'save'),
        'site_label_html' => $link.':',
        'site_placeholder' => _URL,
        'site_value' => $site,
        'title_label' => _TITLE.':',
        'title_value' => $title,
    ]);
    $hide = getTplHiddenInput('name', 'links');
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
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
        delete($fid);
    } else {
        add();
    }
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET status = \'1\' WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links&status=2');
}

function delete(int $dfid = 0): void {
    global $db, $afile;
    $id = $dfid ? $dfid : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'links\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'links\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_links WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/links.php');
    $cont .= getTplBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'links',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['links']['defis'] ?? ''),
        '_pagelinknum' => _PAGELINKNUM,
        'linknum' => $conf['links']['linknum'] ?? 0,
        '_c13' => _C_13,
        'listnum' => $conf['links']['listnum'] ?? 0,
        '_c33' => _C_33,
        'num' => $conf['links']['num'] ?? 0,
        '_c34' => _C_34,
        'anum' => $conf['links']['anum'] ?? 0,
        '_c35' => _C_35,
        'nump' => $conf['links']['nump'] ?? 0,
        '_c36' => _C_36,
        'anump' => $conf['links']['anump'] ?? 0,
        '_homcat' => _HOMCAT,
        'r_homcat' => radio_form($conf['links']['homcat'] ?? 0, 'homcat'),
        '_viewcat' => _VIEWCAT,
        'r_viewcat' => radio_form($conf['links']['viewcat'] ?? 0, 'viewcat'),
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['links']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['links']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['links']['addmail'] ?? 0, 'addmail'),
        '_l8' => _L_8,
        'r_add' => radio_form($conf['links']['add'] ?? 0, 'add'),
        '_l9' => _L_9,
        'r_addquest' => radio_form($conf['links']['addquest'] ?? 0, 'addquest'),
        '_l11' => _L_11,
        'r_broc' => radio_form($conf['links']['broc'] ?? 0, 'broc'),
        '_l12' => _L_12,
        'r_links' => radio_form($conf['links']['links'] ?? 0, 'links'),
        '_c37' => _C_37,
        'r_autor' => radio_form($conf['links']['autor'] ?? 0, 'autor'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['links']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'r_read' => radio_form($conf['links']['read'] ?? 0, 'read'),
        '_l1' => _L_1,
        'r_hits' => radio_form($conf['links']['hits'] ?? 0, 'hits'),
        '_c19' => _C_19,
        'r_rate' => radio_form($conf['links']['rate'] ?? 0, 'rate'),
        '_c20' => _C_20,
        'r_letter' => radio_form($conf['links']['letter'] ?? 0, 'letter'),
        '_pagelink' => _PAGELINK,
        'r_link' => radio_form($conf['links']['link'] ?? 0, 'link'),
        'links' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
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
    setRedirect($afile.'.php?name=links&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=links', 'name=links&amp;op=add', 'name=links&amp;status=1', 'name=links&amp;status=2', 'name=links&amp;op=config', 'name=links&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _INFO], 'tab' => 5]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: links(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'approve': approve(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}

