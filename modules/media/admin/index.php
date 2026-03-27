<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('media')) die('Illegal file access');

function media(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['media']['anum'] ?? 25;
    $anump = $conf['media']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num', 0);
    if ($status == 1) {
        $status = '0';
        $field = 'name=media&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=media&amp;status=2&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=media&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.time, m.ip, c.title, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.status = :status ORDER BY m.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-article-list-head', [
            'checkall_html' => '',
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'postedby_label' => _POSTEDBY,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $cid, $uname, $title, $subtitle, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $title = ($subtitle) ? $title.' / '.$subtitle : $title;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($status == '2') ? adminLinkAction($afile.'.php?name=media&amp;op=approve&amp;id='.$id, _IGNORE, _IGNORE) : '';
            if ($status && time() >= strtotime($date)) {
                $view = adminLinkAction('index.php?name=media&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW);
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $acts = adminMenuItems([
                $view,
                $broc,
                adminLinkAction($afile.'.php?name=media&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=media&amp;op=delete&amp;id='.$id.$refer, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-article-list-row', [
                'actions_html' => $acts,
                'checkbox_html' => '',
                'id_text' => (string)$id,
                'post_html' => $post,
                'status_html' => ad_status('', $active),
                'title_html' => title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span>',
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_media', '', 'status = \''.$status.'\'', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
        $date = getdate();
    $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT m.cid, m.name, m.title, m.subtitle, m.year, m.director, m.roles, m.intro, m.author, m.duration, m.lang, m.note, m.format, m.quality, m.size, m.released, m.links, m.time, m.ihome, m.acomm, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE id = :id', ['id' => $mid]);
        [$cid, $uname, $title, $subtitle, $year, $director, $roles, $description, $createdby, $duration, $lang, $note, $format, $quality, $size, $released, $links, $mdate, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
        $links = explode(',', $links);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $title = getVar('post', 'title', 'title', '');
        $subtitle = getVar('post', 'subtitle', 'title', '');
        $year = getVar('post', 'year', 'num', $date['year']);
        $director = getVar('post', 'director', 'text', '');
        $roles = getVar('post', 'roles', 'text', '');
        $description = getVar('post', 'description', 'text', '');
        $createdby = getVar('post', 'createdby', 'text', '');
        $duration = getVar('post', 'duration', 'text', '');
        $lang = getVar('post', 'lang', 'text', '');
        $note = getVar('post', 'note', 'text', '');
        $format = getVar('post', 'format', 'text', '');
        $quality = getVar('post', 'quality', 'text', '');
        $size = getVar('post', 'size', 'text', '');
        $released = getVar('post', 'released', 'text', '');
        $links = getVar('post', 'links', 'array', []);
        $links = ($links && is_array($links)) ? $links : [];
        $mdate = getVar('req', 'mdate', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    $mtitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis'] ?? '%7C').' '.$subtitle : $title;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if ($description) $cont .= preview($mtitle, $description, '', '', 'media');
    $hide = '<input type="hidden" name="name" value="media">';
    $years = '';
    $xyear = $date['year'] - 100;
    while ($xyear <= ($date['year'] + 1)) {
        $years .= getAdminOption((string)$xyear, (string)$xyear, $xyear == $year);
        $xyear++;
    }
    $years = getAdminSelect('year', $years, 'sl_form');
    $langsel = '';
    $langs = explode(',', $conf['media']['lang'] ?? '');
    foreach ($langs as $val) {
        $langsel .= getAdminOption($val, $val, $val == $lang && $val != '');
    }
    $langsel = getAdminSelect('lang', $langsel, 'sl_form');
    $formatc = getAdminOption('', _NO_INFO);
    $formats = explode(',', $conf['media']['format'] ?? '');
    foreach ($formats as $val) {
        $formatc .= getAdminOption($val, $val, $val == $format && $val != '');
    }
    $formatc = getAdminSelect('format', $formatc, 'sl_form');
    $qualityc = getAdminOption('', _NO_INFO);
    $qualities = explode(',', $conf['media']['quality'] ?? '');
    foreach ($qualities as $val) {
        $qualityc .= getAdminOption($val, $val, $val == $quality && $val != '');
    }
    $qualityc = getAdminSelect('quality', $qualityc, 'sl_form');
    $linkc = '';
    $i = 0;
    $lnum = (int)($conf['media']['links'] ?? 0);
    while ($i < $lnum) {
        $a = $i + 1;
        $link = $links[$i] ?? '';
        $linkc .= $tpl->getHtmlFrag('admin-media-link-row', [
            'add_title' => _ADD,
            'hidden' => $i != 0 && $link == '',
            'index_text' => (string)$a,
            'link_value' => filterText($link),
            'next_id' => 'med'.$a,
            'row_id' => 'med'.$i,
            'url_label' => _URL,
        ]);
        $i++;
    }
    $rows = $tpl->getHtmlFrag('admin-media-add-rows', [
        'acomm_html' => com_access('acomm', $acomm, 'sl_form'),
        'cat_html' => getcat('media', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>'),
        'comments_label' => _COMMENTS.':',
        'createdby_value' => $createdby,
        'date_label' => _CHNGSTORY.':',
        'date_html' => datetime(1, 'mdate', $mdate, 16, 'sl_form'),
        'description_html' => textarea('1', 'description', $description, 'media', '10', _DESCRIPTION, '1'),
        'description_label' => _DESCRIPTION.':',
        'director_value' => $director,
        'director_label' => _MDIRECTOR.':',
        'duration_value' => $duration,
        'duration_label' => _MDURATION.':',
        'format_html' => $formatc,
        'format_label' => _MFORMAT.':',
        'ihome_html' => radio_form($ihome, 'ihome'),
        'ihome_label' => _PUBHOME,
        'lang_html' => $langsel,
        'lang_label' => _LANGUAGE.':',
        'links_html' => $linkc,
        'note_label' => _NOTE.':',
        'note_html' => textarea('2', 'note', $note, 'media', '10', _NOTE, '0'),
        'postname_label' => _POSTEDBY.':',
        'postname_html' => get_user_search('postname', $postname, '25', 'sl_form', '1'),
        'quality_html' => $qualityc,
        'quality_label' => _MQUALITY.':',
        'released_value' => $released,
        'released_label' => _MRELEASED.':',
        'roles_label' => _MROLES.':',
        'roles_value' => $roles,
        'save_html' => ad_save('mid', $mid, 'save'),
        'size_label' => _MSIZE.':',
        'size_value' => $size,
        'subtitle_label' => _MSUBTITLE.':',
        'subtitle_value' => $subtitle,
        'title_label' => _MTITLE.':',
        'title_value' => $title,
        'year_label' => _MYEAR.':',
        'year_html' => $years,
        'cat_label' => _CATEGORY.':',
        'createdby_label' => _MCREATEDBY.':',
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $cid = getVar('post', 'cid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $title = getVar('post', 'title', 'title', '');
    $subtitle = getVar('post', 'subtitle', 'title', '');
    $year = getVar('post', 'year', 'num', 0);
    $director = getVar('post', 'director', 'text', '');
    $roles = getVar('post', 'roles', 'text', '');
    $description = getVar('post', 'description', 'text', '');
    $createdby = getVar('post', 'createdby', 'text', '');
    $duration = getVar('post', 'duration', 'text', '');
    $lang = getVar('post', 'lang', 'text', '');
    $note = getVar('post', 'note', 'text', '');
    $format = getVar('post', 'format', 'text', '');
    $quality = getVar('post', 'quality', 'text', '');
    $size = getVar('post', 'size', 'text', '');
    $released = getVar('post', 'released', 'text', '');
    $links = getVar('post', 'links', 'array', []);
    $links = filterText(implode(',', str_replace(',', '.', is_array($links) ? $links : [])));
    $mdate = getVar('req', 'mdate', 'time');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$description) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$mid && $db->getSqlRowCount($db->getSqlQuery('SELECT title, subtitle FROM '.PREFIX_DB.'_media WHERE title = :title AND subtitle = :subtitle', ['title' => $title, 'subtitle' => $subtitle])) > 0) $stop[] = _MEDIAEXIST;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET cid = :cid, uid = :uid, name = :name, title = :title, subtitle = :subtitle, year = :year, director = :director, roles = :roles, intro = :intro, author = :createdby, duration = :duration, lang = :lang, note = :note, format = :format, quality = :quality, size = :size, released = :released, links = :links, time = :time, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :mid', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'subtitle' => $subtitle, 'year' => $year, 'director' => $director, 'roles' => $roles, 'intro' => $description, 'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang, 'note' => $note, 'format' => $format, 'quality' => $quality, 'size' => $size, 'released' => $released, 'links' => $links, 'time' => $mdate, 'ihome' => $ihome, 'acomm' => $acomm, 'mid' => $mid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_media (id, cid, uid, name, title, subtitle, year, director, roles, intro, author, duration, lang, note, format, quality, size, released, links, time, ihome, acomm, ip, status) VALUES (NULL, :cid, :uid, :name, :title, :subtitle, :year, :director, :roles, :intro, :createdby, :duration, :lang, :note, :format, :quality, :size, :released, :links, :time, :ihome, :acomm, :ip, \'1\')', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'subtitle' => $subtitle, 'year' => $year, 'director' => $director, 'roles' => $roles, 'intro' => $description, 'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang, 'note' => $note, 'format' => $format, 'quality' => $quality, 'size' => $size, 'released' => $released, 'links' => $links, 'time' => $mdate, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
        }
        setRedirect($afile.'.php?name=media');
    } elseif ($posttype === 'delete') {
        delete($mid);
    } else {
        add();
    }
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_media WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=media');
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET status = \'1\' WHERE id = :id', ['id' => $id]);
	setRedirect($afile.'.php?name=media&status=2');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/media.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'media',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['media']['defis'] ?? ''),
        '_pagelinknum' => _PAGELINKNUM,
        'linknum' => $conf['media']['linknum'] ?? 10,
        '_c13' => _C_13,
        'listnum' => $conf['media']['listnum'] ?? 10,
        '_c33' => _C_33,
        'num' => $conf['media']['num'] ?? 25,
        '_c34' => _C_34,
        'anum' => $conf['media']['anum'] ?? 25,
        '_c35' => _C_35,
        'nump' => $conf['media']['nump'] ?? 10,
        '_c36' => _C_36,
        'anump' => $conf['media']['anump'] ?? 10,
        '_nokoma' => _NOKOMA,
        '_m1' => _M_1,
        'mlang' => $conf['media']['lang'] ?? '',
        '_m2' => _M_2,
        'format' => $conf['media']['format'] ?? '',
        '_m3' => _M_3,
        'quality' => $conf['media']['quality'] ?? '',
        '_m4' => _M_4,
        'links' => $conf['media']['links'] ?? 0,
        '_defis' => _DEFIS,
        'mdefis' => urldecode($conf['media']['mdefis'] ?? ''),
        '_homcat' => _HOMCAT,
        'r_homcat' => radio_form($conf['media']['homcat'] ?? 0, 'homcat'),
        '_viewcat' => _VIEWCAT,
        'r_viewcat' => radio_form($conf['media']['viewcat'] ?? 0, 'viewcat'),
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['media']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['media']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['media']['addmail'] ?? 0, 'addmail'),
        '_m7' => _M_7,
        'r_add' => radio_form($conf['media']['add'] ?? 0, 'add'),
        '_m8' => _M_8,
        'r_addquest' => radio_form($conf['media']['addquest'] ?? 0, 'addquest'),
        '_m9' => _M_9,
        'r_broc' => radio_form($conf['media']['broc'] ?? 0, 'broc'),
        '_m10' => _M_10,
        'r_hide' => radio_form($conf['media']['hide'] ?? 0, 'hide'),
        '_c37' => _C_37,
        'r_autor' => radio_form($conf['media']['autor'] ?? 0, 'autor'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['media']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'r_read' => radio_form($conf['media']['read'] ?? 0, 'read'),
        '_c19' => _C_19,
        'r_rate' => radio_form($conf['media']['rate'] ?? 0, 'rate'),
        '_c20' => _C_20,
        'r_letter' => radio_form($conf['media']['letter'] ?? 0, 'letter'),
        '_pagelink' => _PAGELINK,
        'r_link' => radio_form($conf['media']['link'] ?? 0, 'link'),
        'media' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $protect = [', ', ' ,', ' , '];
    $xlang = str_replace($protect, ',', getVar('post', 'lang', 'text', ''));
    $xformat = str_replace($protect, ',', getVar('post', 'format', 'text', ''));
    $xquality = str_replace($protect, ',', getVar('post', 'quality', 'text', ''));
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'linknum' => getVar('post', 'linknum', 'num', 10),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'lang' => $xlang,
        'format' => $xformat,
        'quality' => $xquality,
        'links' => getVar('post', 'links', 'num', 0),
        'mdefis' => getVar('post', 'mdefis', 'defis', '%7C'),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'viewcat' => getVar('post', 'viewcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'broc' => getVar('post', 'broc', 'num', 0),
        'hide' => getVar('post', 'hide', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'link' => getVar('post', 'link', 'num', 0),
    ];
    setConfigFile('media.php', $cont);
    setRedirect($afile.'.php?name=media&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 5]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: media(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'approve': approve(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}

