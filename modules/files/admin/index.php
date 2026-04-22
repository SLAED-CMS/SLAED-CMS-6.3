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
        $cont = getTplAdminTabs(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $st = '2';
        $field = 'name=files&amp;status=2&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $st = '1';
        $field = 'name=files&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, c.title, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $st]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $ctitle = $cid ? $ctitle : _NO;
            $ip = $ip ? user_geo_ip($ip, 4) : _NO;
            $items = [];
            if ($st == '1' && time() >= strtotime($date)) {
                $items[] = ['href' => 'index.php?name=files&amp;op=view&amp;id='.$id, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            if ($st == '2') {
                $items[] = ['href' => $afile.'.php?name=files&amp;op=approve&amp;id='.$id.'&amp;token='.getSiteToken(), 'label' => _IGNORE, 'title' => _IGNORE];
            }
            $items[] = ['href' => $afile.'.php?name=files&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=files&amp;op=delete&amp;id='.$id.$refer.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $ctitle],
                            ['label' => _DATE, 'value' => format_time($date, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip, 'is_last' => true],
                        ],
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['content_html' => $post],
                    ['content_html' => ad_status('', $active)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _POSTEDBY],
                ['content' => _STATUS, 'nosort' => true],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_files', 'field' => 'id', 'where' => 'status = \''.$st.'\'']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.intro, f.body, f.url, f.time, f.filesize, f.version, f.email, f.website, f.ihome, f.acomm, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE id = :id', ['id' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $url, $date, $filesize, $version, $email, $website, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
        $pathsel = '';
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $description = getVar('post', 'description', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $url = getVar('post', 'url', 'text', '');
        $pathsel = getVar('post', 'path', 'name', '');
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
    $cont = getTplAdminTabs(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($description) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $description, 'textb' => $bodytext, 'mod' => 'files']);
    $link = $url ? $tpl->getHtmlFrag('link', ['href' => htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'title' => _DOWNLLINK, 'label' => _URL, 'is_blank' => true]) : _URL;
    $path = $conf['files']['path'] ?? 'uploads/files';
    $pathopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _NO,
        'is_selected' => !$pathsel,
    ]);
    if (file_exists($url)) {
        $pathopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $path,
            'label_text' => $path,
            'is_selected' => $pathsel === $path,
        ]);
        $entries = is_dir($path) ? scandir($path) : [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!preg_match('/\./', $entry)) {
                $dir = $path.'/'.$entry;
                $pathopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $dir,
                    'label_text' => $dir,
                    'is_selected' => $pathsel === $dir,
                ]);
            }
        }
    }
    $catopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _HOMECAT,
        'is_selected' => !$cid,
    ]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'files\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cid === (int)$catid,
        ]);
    }
    $commopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows = [
        ['label_html' => _POSTEDBY.':', 'field_html' => getTplUserSearchInput([
            'input_id' => 'postname',
            'list_id' => 'postname_list',
            'maxlength' => 25,
            'minlength' => (int)$conf['search']['slet'],
            'name' => 'postname',
            'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
            'value' => $postname,
        ])],
        ['label_html' => _TITLE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cid', 'options_html' => $catopts])],
        ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'description', 'value' => $description, 'mod' => 'files', 'rows' => '5', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _ENDTEXT.':', 'field_html' => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => 'files', 'rows' => '15', 'placeholder' => _ENDTEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'date', 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => $link.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'url', 'value_attr' => $url, 'placeholder_text' => _URL])],
        ['label_html' => _FILE_DIR.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'path', 'options_html' => $pathopts])],
        ['label_html' => _SIZENOTE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'filesize', 'value_attr' => (string)$filesize])],
        ['label_html' => _VERSION.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'version', 'value_attr' => $version])],
        ['label_html' => _AUEMAIL.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'email', 'value_attr' => $email])],
        ['label_html' => _SITE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'website', 'value_attr' => $website])],
        ['label_html' => _FILE_SITE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'file', 'name_attr' => 'filesite'])],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($fid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=files&amp;op=save',
        'form_attr' => 'enctype="multipart/form-data"',
        'hidden' => [
            ['nameattr' => 'fid', 'valueattr' => (string)$fid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'select_class' => 'sl-inline-gap'])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
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
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if (!$fid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_files WHERE title = :title', ['title' => $title])) > 0) $stop[] = _MEDIAEXIST;
        $filename = upload(1, $conf['files']['path'] ?? 'uploads/files', $conf['files']['typefile'] ?? 'zip,rar', $conf['files']['max_size'] ?? 1048576, 'files', '1600', '1600', '1');
        $url = $filename ? ($conf['files']['path'] ?? 'uploads/files').'/'.$filename : $url;
        $filesize = $filename ? filesize($url) : $filesize;
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
                        $url = $newfile;
                    }
                }
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET cid = :cid, uid = :uid, name = :name, title = :title, intro = :intro, body = :body, url = :url, time = :time, filesize = :filesize, version = :version, email = :email, website = :website, ihome = :ihome, acomm = :acomm, status = :status WHERE id = :id', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'website' => $website, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1', 'id' => $fid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_files (cid, uid, name, title, intro, body, url, time, filesize, version, email, website, ip, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :intro, :body, :url, :time, :filesize, :version, :email, :website, :ip, :ihome, :acomm, :status)', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'time' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'website' => $website, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm, 'status' => '1']);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($fid);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    setRedirect($afile.'.php?name=files', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$fid && !checkSiteToken();
    if (!$iswarn && $id) {
        [$url] = $db->getSqlRow($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]));
        if (file_exists($url)) unlink($url);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = :modul', ['id' => $id, 'modul' => 'files']);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = :modul', ['id' => $id, 'modul' => 'files']);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=files'.$refer, false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET status = :status WHERE id = :id', ['status' => '1', 'id' => $id]);
    }
    setRedirect($afile.'.php?name=files&status=2', false, 302, $iswarn ? _TOKENMISS : _SUCCSTATUS, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/files.php');
    $streamopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _STREAM_NO, 'is_selected' => ($conf['files']['stream'] ?? null) == '0']) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _STREAM_1, 'is_selected' => ($conf['files']['stream'] ?? null) == '1']) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _STREAM_2, 'is_selected' => ($conf['files']['stream'] ?? null) == '2']);
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['files']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _F_0, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'temp', 'value_attr' => $conf['files']['temp'] ?? '', 'is_config' => true])],
        ['label_html' => _F_1, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'path', 'value_attr' => $conf['files']['path'] ?? '', 'is_config' => true])],
        ['label_html' => _FSIZE._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'maxsize', 'value_attr' => (string)($conf['files']['max_size'] ?? 0), 'is_config' => true])],
        ['label_html' => _NOKOMA, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'typefile', 'value_attr' => $conf['files']['typefile'] ?? '', 'is_config' => true])],
        ['label_html' => _PAGELINKNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'value_attr' => (string)($conf['files']['linknum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['files']['listnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['files']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['files']['anum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)($conf['files']['nump'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['files']['anump'] ?? 0), 'is_config' => true])],
        ['label_html' => _STREAM, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'stream', 'is_config' => true, 'options_html' => $streamopts])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['files']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['files']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['files']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['files']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['files']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _F_8, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['files']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _F_9, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['files']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _F_11, 'field_html' => getTplRadioGroup(['name' => 'broc', 'value' => (string)($conf['files']['broc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _F_12, 'field_html' => getTplRadioGroup(['name' => 'down', 'value' => (string)($conf['files']['down'] ?? 0), 'options' => $yesno])],
        ['label_html' => _UPFILE, 'field_html' => getTplRadioGroup(['name' => 'upload', 'value' => (string)($conf['files']['upload'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['files']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['files']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['files']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _F_2, 'field_html' => getTplRadioGroup(['name' => 'hits', 'value' => (string)($conf['files']['hits'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['files']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['files']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['files']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=files&amp;op=configsave',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken()]],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $typefile = getVar('post', 'typefile', 'text', '');
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
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
    }
    setRedirect($afile.'.php?name=files&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=files', 'name=files&amp;op=add', 'name=files&amp;status=1', 'name=files&amp;status=2', 'name=files&amp;op=config', 'name=files&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO]
    ]);
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
