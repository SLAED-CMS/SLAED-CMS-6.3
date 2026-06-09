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
        $cont = getTplAdminTabs(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=media&amp;status=2&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=media&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.time, m.ip, c.title, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.status = :status ORDER BY m.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $subtitle, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $label = $subtitle ? $title.' / '.$subtitle : $title;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $items = [];
            if ($status && time() >= strtotime($date)) {
                $items[] = ['href' => 'index.php?name=media&amp;op=view&amp;id='.$id, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            if ($status == '2') {
                $items[] = ['href' => $afile.'.php?name=media&amp;op=approve&amp;id='.$id.'&amp;token='.getSiteToken(), 'label' => _IGNORE, 'title' => _IGNORE];
            }
            $items[] = ['href' => $afile.'.php?name=media&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=media&amp;op=delete&amp;id='.$id.$refer.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($label).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $label, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cid ? $ctitle : _NO],
                            ['label' => _DATE, 'value' => format_time($date, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? Geoip::getIpHtml($ip) : _NO, 'is_last' => true],
                        ],
                        'label_text' => $label,
                        'title_text' => $label,
                    ])],
                    ['is_col_author' => true, 'content_html' => $post],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _POSTEDBY, 'is_col_author' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_media', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
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
        $links = getVar('post', 'links[]', '', []);
        $links = ($links && is_array($links)) ? $links : [];
        $mdate = getVar('req', 'mdate', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    $mtitle = $subtitle ? $title.' '.urldecode($conf['media']['mdefis'] ?? '%7C').' '.$subtitle : $title;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($description) $cont .= getTplPreviewContent(['title' => $mtitle, 'texta' => $description, 'mod' => 'media']);
    $yearopts = '';
    $xyear = $date['year'] - 100;
    while ($xyear <= ($date['year'] + 1)) {
        $yearopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$xyear,
            'label_text' => (string)$xyear,
            'is_selected' => (int)$xyear === (int)$year,
        ]);
        $xyear++;
    }
    $langopts = '';
    foreach (explode(',', $conf['media']['lang'] ?? '') as $val) {
        if ($val === '') continue;
        $langopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => $val,
            'is_selected' => $val === $lang,
        ]);
    }
    $formatopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NO_INFO, 'is_selected' => $format === '']);
    foreach (explode(',', $conf['media']['format'] ?? '') as $val) {
        if ($val === '') continue;
        $formatopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => $val,
            'is_selected' => $val === $format,
        ]);
    }
    $qualityopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NO_INFO, 'is_selected' => $quality === '']);
    foreach (explode(',', $conf['media']['quality'] ?? '') as $val) {
        if ($val === '') continue;
        $qualityopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => $val,
            'is_selected' => $val === $quality,
        ]);
    }
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cid]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'media\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cid === (int)$catid,
        ]);
    }
    $lnum = (int)($conf['media']['links'] ?? 0);
    $linkshtml = '';
    for ($i = 0; $i < $lnum; $i++) {
        $a = $i + 1;
        $link = filterText($links[$i] ?? '');
        $hidden = $i != 0 && $link === '';
        $linkshtml .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'med'.$i,
            'is_hidden' => $hidden,
            'label_html' => _ADD.' '.$a,
            'content_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'links[]',
                'value_attr' => $link,
                'placeholder_text' => _URL,
                'input_attr' => ' id="med'.($a).'"',
            ]),
        ]);
    }
    $commopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows = [
        ['label_html' => _POSTEDBY, 'field_html' => getTplUserSearchInput([
            'input_id' => 'postname',
            'list_id' => 'postname_list',
            'maxlength' => 25,
            'minlength' => (int)$conf['search']['slet'],
            'name' => 'postname',
            'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
            'value' => $postname,
        ])],
        ['label_html' => _MTITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _MSUBTITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'subtitle', 'value_attr' => $subtitle, 'maxlength_num' => 255])],
        ['label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cid', 'options_html' => $catopts])],
        ['label_html' => _MYEAR, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'year', 'options_html' => $yearopts])],
        ['label_html' => _MDIRECTOR, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'director', 'value_attr' => $director])],
        ['label_html' => _MROLES, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'roles', 'value_attr' => $roles])],
        ['label_html' => _DESCRIPTION, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'description', 'value' => $description, 'mod' => 'media', 'rows' => '10', 'placeholder' => _DESCRIPTION, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _MCREATEDBY, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'createdby', 'value_attr' => $createdby])],
        ['label_html' => _MDURATION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'duration', 'value_attr' => $duration])],
        ['label_html' => _LANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => $langopts])],
        ['label_html' => _NOTE, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'note', 'value' => $note, 'mod' => 'media', 'rows' => '10', 'placeholder' => _NOTE, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _MFORMAT, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'format', 'options_html' => $formatopts])],
        ['label_html' => _MQUALITY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'quality', 'options_html' => $qualityopts])],
        ['label_html' => _MSIZE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'size', 'value_attr' => $size])],
        ['label_html' => _MRELEASED, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'released', 'value_attr' => $released])],
        ['label_html' => '', 'field_html' => $linkshtml, 'is_full' => true],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'mdate', 'time' => $mdate, 'with' => true, 'max' => 16])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($mid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=media&amp;op=save',
        'hidden' => [
            ['nameattr' => 'mid', 'valueattr' => (string)$mid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
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
    $links = getVar('post', 'links[]', '', []);
    $links = filterText(implode(',', str_replace(',', '.', is_array($links) ? $links : [])));
    $mdate = getVar('req', 'mdate', 'time');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if (!$mid && $db->getSqlRowCount($db->getSqlQuery('SELECT title, subtitle FROM '.PREFIX_DB.'_media WHERE title = :title AND subtitle = :subtitle', ['title' => $title, 'subtitle' => $subtitle])) > 0) $stop[] = _MEDIAEXIST;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ?: 0;
            $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
            if ($mid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET cid = :cid, uid = :uid, name = :name, title = :title, subtitle = :subtitle, year = :year, director = :director, roles = :roles, intro = :intro, author = :createdby, duration = :duration, lang = :lang, note = :note, format = :format, quality = :quality, size = :size, released = :released, links = :links, time = :time, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :mid', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'subtitle' => $subtitle, 'year' => $year, 'director' => $director, 'roles' => $roles, 'intro' => $description, 'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang, 'note' => $note, 'format' => $format, 'quality' => $quality, 'size' => $size, 'released' => $released, 'links' => $links, 'time' => $mdate, 'ihome' => $ihome, 'acomm' => $acomm, 'mid' => $mid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_media (id, cid, uid, name, title, subtitle, year, director, roles, intro, author, duration, lang, note, format, quality, size, released, links, time, ihome, acomm, ip, status) VALUES (NULL, :cid, :uid, :name, :title, :subtitle, :year, :director, :roles, :intro, :createdby, :duration, :lang, :note, :format, :quality, :size, :released, :links, :time, :ihome, :acomm, :ip, \'1\')', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'subtitle' => $subtitle, 'year' => $year, 'director' => $director, 'roles' => $roles, 'intro' => $description, 'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang, 'note' => $note, 'format' => $format, 'quality' => $quality, 'size' => $size, 'released' => $released, 'links' => $links, 'time' => $mdate, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($mid);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    setRedirect($afile.'.php?name=media', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$did && !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'media\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_media WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=media', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET status = \'1\' WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=media&status=2', false, 302, $iswarn ? _TOKENMISS : _SUCCSTATUS, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/media.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['media']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _PAGELINKNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'value_attr' => (string)($conf['media']['linknum'] ?? 10), 'is_config' => true])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['media']['listnum'] ?? 10), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['media']['num'] ?? 25), 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['media']['anum'] ?? 25), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)($conf['media']['nump'] ?? 10), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['media']['anump'] ?? 10), 'is_config' => true])],
        ['label_html' => _M_1, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'lang', 'value_attr' => $conf['media']['lang'] ?? '', 'is_config' => true])],
        ['label_html' => _M_2, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'format', 'value_attr' => $conf['media']['format'] ?? '', 'is_config' => true])],
        ['label_html' => _M_3, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'quality', 'value_attr' => $conf['media']['quality'] ?? '', 'is_config' => true])],
        ['label_html' => _M_4, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'links', 'value_attr' => (string)($conf['media']['links'] ?? 0), 'is_config' => true])],
        ['label_html' => _DEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mdefis', 'value_attr' => urldecode($conf['media']['mdefis'] ?? ''), 'is_config' => true])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['media']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['media']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['media']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['media']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['media']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _M_7, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['media']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _M_8, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['media']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _M_9, 'field_html' => getTplRadioGroup(['name' => 'broc', 'value' => (string)($conf['media']['broc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _M_10, 'field_html' => getTplRadioGroup(['name' => 'hide', 'value' => (string)($conf['media']['hide'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['media']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['media']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['media']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['media']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['media']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['media']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=media&amp;op=configsave',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken()]],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $protect = [', ', ' ,', ' , '];
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
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
    }
    setRedirect($afile.'.php?name=media&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=media', 'name=media&amp;op=add', 'name=media&amp;status=1', 'name=media&amp;status=2', 'name=media&amp;op=config', 'name=media&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _BROCMFILES, _PREFERENCES, _INFO],
    ]);
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
