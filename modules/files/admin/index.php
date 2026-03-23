<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('files')) die('Illegal file access');

function files(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $status = getVar('get', 'status', 'num', 0);
    $anum = $conf['files']['anum'] ?? 25;
    $anump = $conf['files']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if ($status == 1) {
        $st = '0';
        $field = 'name=files&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $st = '2';
        $field = 'name=files&amp;status=2&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $st = '1';
        $field = 'name=files&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, c.title, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $st]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($st == '2') ? '<a href="'.$afile.'.php?name=files&amp;op=approve&amp;id='.$id.'" title="'._IGNORE.'">'._IGNORE.'</a>||' : '';
            if ($st == '1' && time() >= strtotime($date)) {
                $view = '<a href="index.php?name=files&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($date, _TIMESTRING).'<br>'._IP.': '.$ip).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
            .'<td>'.$post.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.$broc.'<a href="'.$afile.'.php?name=files&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=files&amp;op=delete&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_files', '', 'status = \''.$st.'\'', $anump);
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
        $id = getVar('req', 'id', 'num', 0);
    $fid = $id;
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.intro, f.body, f.url, f.time, f.filesize, f.version, f.email, f.website, f.ihome, f.acomm, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE id = :id', ['id' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $url, $date, $filesize, $version, $email, $website, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $description = getVar('post', 'description', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $url = getVar('post', 'url', 'text', '');
        $path = getVar('post', 'path', 'name', '');
        $date = getVar('req', 'date', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $filesize = getVar('post', 'filesize', 'num', 0);
        $version = getVar('post', 'version', 'name', '');
        $postname = getVar('post', 'postname', 'name', '');
        $email = getVar('post', 'email', 'name', '');
        $website = getVar('post', 'website', 'url', 'http://');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    if ($description) $cont .= preview($title, $description, $bodytext, '', 'files');
    $link = ($url) ? '<a href="'.$url.'" target="_blank" title="'._DOWNLLINK.'">'._URL.'</a>' : _URL;
    $directory = '';
    $path = $conf['files']['path'] ?? 'uploads/files';
    if (file_exists($url)) {
        $entries = is_dir($path) ? scandir($path) : [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!preg_match('/\./', $entry)) {
                $selected = ($path === $path.'/'.$entry) ? ' selected' : '';
                $directory .= '<option value="'.$path.'/'.$entry.'"'.$selected.'>'.$path.'/'.$entry.'</option>';
            }
        }
    }
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form name="post" enctype="multipart/form-data" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('files', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, 'files', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'files', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._AUEMAIL.':</td><td><input type="email" name="email" value="'.$email.'" class="sl_form" placeholder="'._AUEMAIL.'"></td></tr>'
    .'<tr><td>'._SITE.':</td><td><input type="url" name="website" value="'.$website.'" class="sl_form" placeholder="'._SITE.'"></td></tr>'
    .'<tr><td>'._FILE_USER.':</td><td><input type="file" name="userfile" class="sl_form"></td></tr>'
    .'<tr><td>'._FILE_SITE.':</td><td><input type="text" name="sitefile" class="sl_form" placeholder="'._FILE_SITE.'"></td></tr>'
    .'<tr><td>'.$link.':</td><td><input type="text" name="url" value="'.$url.'" class="sl_form" placeholder="'._URL.'"></td></tr>';
    if (file_exists($url)) $cont .= '<tr><td>'._FILE_DIR.':</td><td><select name="path" class="sl_form"><option value="">'._NO.'</option><option value="'.$path.'">'.$path.'</option>'.$directory.'</select></td></tr>';
    $cont .= '<tr><td>'._VERSION.':</td><td><input type="text" name="version" value="'.$version.'" class="sl_form" placeholder="'._VERSION.'"></td></tr>'
    .'<tr><td>'._SIZENOTE.':</td><td><input type="number" name="filesize" value="'.$filesize.'" class="sl_form" placeholder="'._SIZENOTE.'"></td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._PUBHOME.'</td><td>'.radio_form($ihome, 'ihome').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="files">'.ad_save('fid', $fid, 'save').'</td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop, $conf;
        $fid = getVar('post', 'fid', 'num', 0);
    $cid = getVar('post', 'cid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $title = getVar('post', 'title', 'title', '');
    $description = getVar('post', 'description', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $url = getVar('post', 'url', 'text', '');
    $path = getVar('post', 'path', 'name', '');
    $date = getVar('req', 'date', 'time');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $filesize = getVar('post', 'filesize', 'num', 0);
    $version = getVar('post', 'version', 'name', '');
    $email = getVar('post', 'email', 'name', '');
    $website = getVar('post', 'website', 'url', '');
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$description) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    if (!$fid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_files WHERE title = :title', ['title' => $title])) > 0) $stop[] = _MEDIAEXIST;
    $filename = upload(1, $conf['files']['path'] ?? 'uploads/files', $conf['files']['typefile'] ?? 'zip,rar', $conf['files']['max_size'] ?? 1048576, 'files', '1600', '1600', '1');
    $url = ($filename) ? ($conf['files']['path'] ?? 'uploads/files').'/'.$filename : $url;
    $filesize = ($filename) ? filesize($url) : $filesize;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && !$url && $posttype === 'save') {
        $stop[] = _UPLOADEROR2;
    }
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($fid) {
            if ($path) {
                $filel = array_reverse(explode('/', $url));
                if (file_exists($url)) {
                    $newfile = $path.'/'.$filel[0];
                    rename($url, $newfile);
                    $url = $path.'/'.$filel[0];
                }
            }
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET cid = :cid, uid = :uid, name = :name, title = :title, intro = :intro, body = :body, url = :url, time = :time, filesize = :filesize, version = :version, email = :email, website = :website, ihome = :ihome, acomm = :acomm, status = :status WHERE id = :id', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'website' => $website, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1', 'id' => $fid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_files (cid, uid, name, title, intro, body, url, time, filesize, version, email, website, ip, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :intro, :body, :url, :time, :filesize, :version, :email, :website, :ip, :ihome, :acomm, :status)', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'website' => $website, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1']);
        }
        setRedirect($afile.'.php?name=files');
    } elseif ($posttype === 'delete') {
        delete($fid);
    } else {
        add();
    }
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    if ($id) {
        [$url] = $db->getSqlRow($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]));
        if (file_exists($url)) unlink($url);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = :modul', ['id' => $id, 'modul' => 'files']);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = :modul', ['id' => $id, 'modul' => 'files']);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=files'.$refer);
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET status = :status WHERE id = :id', ['status' => '1', 'id' => $id]);
    }
    setRedirect($afile.'.php?name=files&status=2');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/files.php');
    $stream_sel = '<select name="stream" class="sl_conf">'
        .'<option value="0"'.(($conf['files']['stream'] ?? null) == '0' ? ' selected' : '').'>'._STREAM_NO.'</option>'
        .'<option value="1"'.(($conf['files']['stream'] ?? null) == '1' ? ' selected' : '').'>'._STREAM_1.'</option>'
        .'<option value="2"'.(($conf['files']['stream'] ?? null) == '2' ? ' selected' : '').'>'._STREAM_2.'</option>'
        .'</select>';
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'files',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'defis' => urldecode($conf['files']['defis'] ?? ''),
        '_f0' => _F_0,
        'temp' => $conf['files']['temp'] ?? '',
        '_f1' => _F_1,
        'path' => $conf['files']['path'] ?? '',
        '_fsize_fin' => _FSIZE._FIN,
        'maxsize' => $conf['files']['max_size'] ?? 0,
        '_nokoma' => _NOKOMA,
        '_ftype' => _FTYPE,
        'typefile' => $conf['files']['typefile'] ?? '',
        '_pagelinknum' => _PAGELINKNUM,
        'linknum' => $conf['files']['linknum'] ?? 0,
        '_c13' => _C_13,
        'listnum' => $conf['files']['listnum'] ?? 0,
        '_c33' => _C_33,
        'num' => $conf['files']['num'] ?? 0,
        '_c34' => _C_34,
        'anum' => $conf['files']['anum'] ?? 0,
        '_c35' => _C_35,
        'nump' => $conf['files']['nump'] ?? 0,
        '_c36' => _C_36,
        'anump' => $conf['files']['anump'] ?? 0,
        '_stream' => _STREAM,
        's_stream' => $stream_sel,
        '_homcat' => _HOMCAT,
        'r_homcat' => radio_form($conf['files']['homcat'] ?? 0, 'homcat'),
        '_viewcat' => _VIEWCAT,
        'r_viewcat' => radio_form($conf['files']['viewcat'] ?? 0, 'viewcat'),
        '_c32' => _C_32,
        'r_catdesc' => radio_form($conf['files']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'r_subcat' => radio_form($conf['files']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['files']['addmail'] ?? 0, 'addmail'),
        '_f8' => _F_8,
        'r_add' => radio_form($conf['files']['add'] ?? 0, 'add'),
        '_f9' => _F_9,
        'r_addquest' => radio_form($conf['files']['addquest'] ?? 0, 'addquest'),
        '_f11' => _F_11,
        'r_broc' => radio_form($conf['files']['broc'] ?? 0, 'broc'),
        '_f12' => _F_12,
        'r_down' => radio_form($conf['files']['down'] ?? 0, 'down'),
        '_upfile' => _UPFILE,
        'r_upload' => radio_form($conf['files']['upload'] ?? 0, 'upload'),
        '_c37' => _C_37,
        'r_autor' => radio_form($conf['files']['autor'] ?? 0, 'autor'),
        '_c17' => _C_17,
        'r_date' => radio_form($conf['files']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'r_read' => radio_form($conf['files']['read'] ?? 0, 'read'),
        '_f2' => _F_2,
        'r_hits' => radio_form($conf['files']['hits'] ?? 0, 'hits'),
        '_c19' => _C_19,
        'r_rate' => radio_form($conf['files']['rate'] ?? 0, 'rate'),
        '_c20' => _C_20,
        'r_letter' => radio_form($conf['files']['letter'] ?? 0, 'letter'),
        '_pagelink' => _PAGELINK,
        'r_link' => radio_form($conf['files']['link'] ?? 0, 'link'),
        'files' => true,
    ]);
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $typefile = getVar('post', 'typefile', 'text', '');
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'temp' => getVar('post', 'temp', 'text', ''),
        'path' => getVar('post', 'path', 'text', ''),
        'max_size' => getVar('post', 'maxsize', 'num', 1048576),
        'typefile' => $typefile ? strtolower(strtr($typefile, $protect)) : 'zip,gzip,7z,rar,tar',
        'linknum' => getVar('post', 'linknum', 'num', 10),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'stream' => getVar('post', 'stream', 'num', 0),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'viewcat' => getVar('post', 'viewcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'broc' => getVar('post', 'broc', 'num', 0),
        'down' => getVar('post', 'down', 'num', 0),
        'upload' => getVar('post', 'upload', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'hits' => getVar('post', 'hits', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'link' => getVar('post', 'link', 'num', 0),
    ];
    setConfigFile('files.php', $cont);
    setRedirect($afile.'.php?name=files&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 5]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: files(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'approve': approve(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}







