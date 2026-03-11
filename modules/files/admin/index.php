<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('files')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=conf', 'name=files&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function files(): void {
    global $db, $afile, $conf;
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
        $cont = navi(0, 2, 0, 0);
    } elseif ($status == 2) {
        $st = '2';
        $field = 'name=files&amp;status=2&amp;';
        $refer = '';
        $cont = navi(0, 3, 0, 0);
    } else {
        $st = '1';
        $field = 'name=files&amp;';
        $refer = '';
        $cont = navi(0, 0, 0, 0);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, c.title, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $st]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._POSTEDBY.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $cid, $uname, $title, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $broc = ($st == '2') ? '<a href="'.$afile.'.php?name=files&amp;op=ignore&amp;id='.$id.'" title="'._IGNORE.'">'._IGNORE.'</a>||' : '';
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
            .'<td>'.add_menu($view.$broc.'<a href="'.$afile.'.php?name=files&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=files&amp;op=del&amp;id='.$id.$refer.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_files', '', 'status = \''.$st.'\'', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
        $id = getVar('req', 'id', 'num', 0);
    $fid = $id;
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.description, f.bodytext, f.url, f.time, f.filesize, f.version, f.email, f.homepage, f.ihome, f.acomm, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE id = :id', ['id' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $url, $date, $filesize, $version, $email, $homepage, $ihome, $acomm, $nick] = $db->getSqlRow($result);
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
        $homepage = getVar('post', 'homepage', 'url', 'http://');
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', (array)$stop)]);
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
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" enctype="multipart/form-data" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._CATEGORY.':</td><td>'.getcat('files', $cid, 'cid', 'sl_form', '<option value="">'._HOMECAT.'</option>').'</td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, 'files', '5', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, 'files', '15', _ENDTEXT, '0').'</td></tr>'
    .'<tr><td>'._AUEMAIL.':</td><td><input type="email" name="email" value="'.$email.'" class="sl_form" placeholder="'._AUEMAIL.'"></td></tr>'
    .'<tr><td>'._SITE.':</td><td><input type="url" name="homepage" value="'.$homepage.'" class="sl_form" placeholder="'._SITE.'"></td></tr>'
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
    $cont .= setTemplateBasic('close');
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
    $homepage = getVar('post', 'homepage', 'url', '');
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
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET cid = :cid, uid = :uid, name = :name, title = :title, description = :description, bodytext = :bodytext, url = :url, time = :time, filesize = :filesize, version = :version, email = :email, homepage = :homepage, ihome = :ihome, acomm = :acomm, status = :status WHERE id = :id', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'homepage' => $homepage, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1', 'id' => $fid]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_files (cid, uid, name, title, description, bodytext, url, time, filesize, version, email, homepage, ip, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :description, :bodytext, :url, :time, :filesize, :version, :email, :homepage, :ip, :ihome, :acomm, :status)', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'homepage' => $homepage, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1']);
        }
        setRedirect($afile.'.php?name=files');
    } elseif ($posttype === 'delete') {
        del($fid);
    } else {
        add();
    }
}

function del(int $fid = 0): void {
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

function ignore(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET status = :status WHERE id = :id', ['status' => '1', 'id' => $id]);
    }
    setRedirect($afile.'.php?name=files&status=2');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 4, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/files.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._CDEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['files']['defis'] ?? '').'" maxlength="25" class="sl_conf" placeholder="'._CDEFIS.'" required></td></tr>'
    .'<tr><td>'._F_0.':</td><td><input type="text" name="temp" value="'.($conf['files']['temp'] ?? '').'" class="sl_conf" placeholder="'._F_0.'" required></td></tr>'
    .'<tr><td>'._F_1.':</td><td><input type="text" name="path" value="'.($conf['files']['path'] ?? '').'" class="sl_conf" placeholder="'._F_1.'" required></td></tr>'
    .'<tr><td>'._FSIZE._FIN.':</td><td><input type="number" name="maxsize" value="'.($conf['files']['max_size'] ?? 0).'" class="sl_conf" placeholder="'._FSIZE._FIN.'" required></td></tr>'
    .'<tr><td>'._FTYPE.':<div class="sl_small">'._NOKOMA.'</div></td><td><input type="text" name="typefile" value="'.($conf['files']['typefile'] ?? '').'" class="sl_conf" placeholder="'._FTYPE.'" required></td></tr>'
    .'<tr><td>'._PAGELINKNUM.':</td><td><input type="number" name="linknum" value="'.($conf['files']['linknum'] ?? 0).'" class="sl_conf" placeholder="'._PAGELINKNUM.'" required></td></tr>'
    .'<tr><td>'._C_13.':</td><td><input type="number" name="listnum" value="'.($conf['files']['listnum'] ?? 0).'" class="sl_conf" placeholder="'._C_13.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.($conf['files']['num'] ?? 0).'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['files']['anum'] ?? 0).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.($conf['files']['nump'] ?? 0).'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['files']['anump'] ?? 0).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._STREAM.':</td><td><select name="stream" class="sl_conf">'
    .'<option value="0"';
    if (($conf['files']['stream'] ?? null) == '0') $cont .= ' selected';
    $cont .= '>'._STREAM_NO.'</option>'
    .'<option value="1"';
    if (($conf['files']['stream'] ?? null) == '1') $cont .= ' selected';
    $cont .= '>'._STREAM_1.'</option>'
    .'<option value="2"';
    if (($conf['files']['stream'] ?? null) == '2') $cont .= ' selected';
    $cont .= '>'._STREAM_2.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._HOMCAT.'</td><td>'.radio_form($conf['files']['homcat'] ?? 0, 'homcat').'</td></tr>'
    .'<tr><td>'._VIEWCAT.'</td><td>'.radio_form($conf['files']['viewcat'] ?? 0, 'viewcat').'</td></tr>'
    .'<tr><td>'._C_32.'</td><td>'.radio_form($conf['files']['catdesc'] ?? 0, 'catdesc').'</td></tr>'
    .'<tr><td>'._C_15.'</td><td>'.radio_form($conf['files']['subcat'] ?? 0, 'subcat').'</td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['files']['addmail'] ?? 0, 'addmail').'</td></tr>'
    .'<tr><td>'._F_8.'</td><td>'.radio_form($conf['files']['add'] ?? 0, 'add').'</td></tr>'
    .'<tr><td>'._F_9.'</td><td>'.radio_form($conf['files']['addquest'] ?? 0, 'addquest').'</td></tr>'
    .'<tr><td>'._F_11.'</td><td>'.radio_form($conf['files']['broc'] ?? 0, 'broc').'</td></tr>'
    .'<tr><td>'._F_12.'</td><td>'.radio_form($conf['files']['down'] ?? 0, 'down').'</td></tr>'
    .'<tr><td>'._UPFILE.'</td><td>'.radio_form($conf['files']['upload'] ?? 0, 'upload').'</td></tr>'
    .'<tr><td>'._C_37.'</td><td>'.radio_form($conf['files']['autor'] ?? 0, 'autor').'</td></tr>'
    .'<tr><td>'._C_17.'</td><td>'.radio_form($conf['files']['date'] ?? 0, 'date').'</td></tr>'
    .'<tr><td>'._C_18.'</td><td>'.radio_form($conf['files']['read'] ?? 0, 'read').'</td></tr>'
    .'<tr><td>'._F_2.'</td><td>'.radio_form($conf['files']['hits'] ?? 0, 'hits').'</td></tr>'
    .'<tr><td>'._C_19.'</td><td>'.radio_form($conf['files']['rate'] ?? 0, 'rate').'</td></tr>'
    .'<tr><td>'._C_20.'</td><td>'.radio_form($conf['files']['letter'] ?? 0, 'letter').'</td></tr>'
    .'<tr><td>'._PAGELINK.'</td><td>'.radio_form($conf['files']['link'] ?? 0, 'link').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="files"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
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
    setRedirect($afile.'.php?name=files&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: files(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'ignore': ignore(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}







