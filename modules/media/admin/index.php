<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('media')) die('Illegal file access');

function media(): void {
    global $db, $afile, $conf;
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
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=media&amp;status=2&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=media&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.time, m.ip, c.title, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.status = :status ORDER BY m.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $subtitle, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $title = ($subtitle) ? $title.' / '.$subtitle : $title;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($status == '2') ? '<a href="'.$afile.'.php?name=media&amp;op=ignore&amp;id='.$id.'" title="'._IGNORE.'">'._IGNORE.'</a>||' : '';
            if ($status && time() >= strtotime($date)) {
                $view = '<a href="index.php?name=media&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.$broc.'<a href="'.$afile.'.php?name=media&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=media&amp;op=del&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_media', '', 'status = \''.$status.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
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
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
    if ($description) $cont .= preview($mtitle, $description, '', '', 'media');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._MTITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._MTITLE.'" required></td></tr>'
    .'<tr><td>'._MSUBTITLE.':</td><td><input type="text" name="subtitle" value="'.$subtitle.'" maxlength="100" class="sl_form" placeholder="'._MSUBTITLE.'"></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('media', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._MYEAR.':</td><td><select name="year" class="sl_form">';
    $xyear = $date['year'] - 100;
    while ($xyear <= ($date['year'] + 1)) {
        $sel = ($xyear == $year) ? ' selected' : '';
        $cont .= '<option value="'.$xyear.'"'.$sel.'>'.$xyear.'</option>';
        $xyear++;
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._MDIRECTOR.':</td><td><input type="text" name="director" value="'.$director.'" maxlength="100" class="sl_form" placeholder="'._MDIRECTOR.'"></td></tr>'
    .'<tr><td>'._MROLES.':</td><td><input type="text" name="roles" value="'.$roles.'" maxlength="255" class="sl_form" placeholder="'._MROLES.'"></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td>'.textarea('1', 'description', $description, 'media', '10', _DESCRIPTION, '1').'</td></tr>'
    .'<tr><td>'._MCREATEDBY.':</td><td><input type="text" name="createdby" value="'.$createdby.'" maxlength="100" class="sl_form" placeholder="'._MCREATEDBY.'"></td></tr>'
    .'<tr><td>'._MDURATION.':</td><td><input type="text" name="duration" value="'.$duration.'" maxlength="100" class="sl_form" placeholder="'._MDURATION.'"></td></tr>'
    .'<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">';
    $langs = explode(',', $conf['media']['lang'] ?? '');
    foreach ($langs as $val) {
        $sel = ($val == $lang && $val != '') ? ' selected' : '';
        $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._NOTE.':</td><td>'.textarea('2', 'note', $note, 'media', '10', _NOTE, '0').'</td></tr>'
    .'<tr><td>'._MFORMAT.':</td><td><select name="format" class="sl_form">'
    .'<option value="">'._NO_INFO.'</option>';
    $formats = explode(',', $conf['media']['format'] ?? '');
    foreach ($formats as $val) {
        $sel = ($val == $format && $val != '') ? ' selected' : '';
        $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._MQUALITY.':</td><td><select name="quality" class="sl_form">'
    .'<option value="">'._NO_INFO.'</option>';
    $qualities = explode(',', $conf['media']['quality'] ?? '');
    foreach ($qualities as $val) {
        $sel = ($val == $quality && $val != '') ? ' selected' : '';
        $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._MSIZE.':</td><td><input type="text" name="size" value="'.$size.'" maxlength="100" class="sl_form" placeholder="'._MSIZE.'"></td></tr>'
    .'<tr><td>'._MRELEASED.':</td><td><input type="text" name="released" value="'.$released.'" maxlength="100" class="sl_form" placeholder="'._MRELEASED.'"></td></tr>'
    .'<tr><td colspan="2">';
    $i = 0;
    $lnum = (int)($conf['media']['links'] ?? 0);
    while ($i < $lnum) {
        $a = $i + 1;
        $link = $links[$i] ?? '';
        $class = ($i != 0 && $link == '') ? ' class="sl_none"' : '';
        $cont .= '<table id="med'.$i.'"'.$class.'><tr><td><a OnClick="HideShow(\'med'.$a.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._URL.' - '.$a.':</a></td><td><input type="text" name="links[]" value="'.filterText($link).'" class="sl_form" placeholder="'._URL.' - '.$a.'"></td></tr></table>';
        $i++;
    }
    $cont .= '</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'mdate', $mdate, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="media">'.ad_save('mid', $mid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
        del($mid);
    } else {
        add();
    }
}

function del(int $did = 0): void {
    global $db, $afile;
    $id = $did ? $did : getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_media WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=media');
}

function ignore(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET status = \'1\' WHERE id = :id', ['id' => $id]);
	setRedirect($afile.'.php?name=media&status=2');
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/media.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'        => $afile,
        '{%module%}'       => 'media',
        '{%op%}'           => 'saveconf',
        '{%save%}'         => _SAVECHANGES,
        '{%fields%}'       => '',
        '{%_cdefis%}'      => _CDEFIS,
        '{%defis%}'        => urldecode($conf['media']['defis'] ?? ''),
        '{%_pagelinknum%}' => _PAGELINKNUM,
        '{%linknum%}'      => $conf['media']['linknum'] ?? 10,
        '{%_c13%}'         => _C_13,
        '{%listnum%}'      => $conf['media']['listnum'] ?? 10,
        '{%_c33%}'         => _C_33,
        '{%num%}'          => $conf['media']['num'] ?? 25,
        '{%_c34%}'         => _C_34,
        '{%anum%}'         => $conf['media']['anum'] ?? 25,
        '{%_c35%}'         => _C_35,
        '{%nump%}'         => $conf['media']['nump'] ?? 10,
        '{%_c36%}'         => _C_36,
        '{%anump%}'        => $conf['media']['anump'] ?? 10,
        '{%_nokoma%}'      => _NOKOMA,
        '{%_m1%}'          => _M_1,
        '{%mlang%}'        => $conf['media']['lang'] ?? '',
        '{%_m2%}'          => _M_2,
        '{%format%}'       => $conf['media']['format'] ?? '',
        '{%_m3%}'          => _M_3,
        '{%quality%}'      => $conf['media']['quality'] ?? '',
        '{%_m4%}'          => _M_4,
        '{%links%}'        => $conf['media']['links'] ?? 0,
        '{%_defis%}'       => _DEFIS,
        '{%mdefis%}'       => urldecode($conf['media']['mdefis'] ?? ''),
        '{%_homcat%}'      => _HOMCAT,
        '{%r_homcat%}'     => radio_form($conf['media']['homcat'] ?? 0, 'homcat'),
        '{%_viewcat%}'     => _VIEWCAT,
        '{%r_viewcat%}'    => radio_form($conf['media']['viewcat'] ?? 0, 'viewcat'),
        '{%_c32%}'         => _C_32,
        '{%r_catdesc%}'    => radio_form($conf['media']['catdesc'] ?? 0, 'catdesc'),
        '{%_c15%}'         => _C_15,
        '{%r_subcat%}'     => radio_form($conf['media']['subcat'] ?? 0, 'subcat'),
        '{%_addamail%}'    => _ADDAMAIL,
        '{%r_addmail%}'    => radio_form($conf['media']['addmail'] ?? 0, 'addmail'),
        '{%_m7%}'          => _M_7,
        '{%r_add%}'        => radio_form($conf['media']['add'] ?? 0, 'add'),
        '{%_m8%}'          => _M_8,
        '{%r_addquest%}'   => radio_form($conf['media']['addquest'] ?? 0, 'addquest'),
        '{%_m9%}'          => _M_9,
        '{%r_broc%}'       => radio_form($conf['media']['broc'] ?? 0, 'broc'),
        '{%_m10%}'         => _M_10,
        '{%r_hide%}'       => radio_form($conf['media']['hide'] ?? 0, 'hide'),
        '{%_c37%}'         => _C_37,
        '{%r_autor%}'      => radio_form($conf['media']['autor'] ?? 0, 'autor'),
        '{%_c17%}'         => _C_17,
        '{%r_date%}'       => radio_form($conf['media']['date'] ?? 0, 'date'),
        '{%_c18%}'         => _C_18,
        '{%r_read%}'       => radio_form($conf['media']['read'] ?? 0, 'read'),
        '{%_c19%}'         => _C_19,
        '{%r_rate%}'       => radio_form($conf['media']['rate'] ?? 0, 'rate'),
        '{%_c20%}'         => _C_20,
        '{%r_letter%}'     => radio_form($conf['media']['letter'] ?? 0, 'letter'),
        '{%_pagelink%}'    => _PAGELINK,
        '{%r_link%}'       => radio_form($conf['media']['link'] ?? 0, 'link'),
        'if_flag'          => ['media' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
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
    setRedirect($afile.'.php?name=media&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=conf', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 5]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: media(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'ignore': ignore(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}






