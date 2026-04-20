<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function files(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 'f.cid');
    $unum = getUserNews($conf['files']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['files']['rate']) {
        $caton = 0;
        if ($op == 'best') {
            $orderby = 'IFNULL((f.tvotes/NULLIF(f.votes,0)),0) DESC';
            $ntitle = _BEST;
        } else {
            $orderby = 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.time)),0)),0) DESC';
            $ntitle = _POP;
        }
        $order = "WHERE f.time <= NOW() AND f.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $onum = "time <= NOW() AND status != '0'";
    } elseif ($ncat) {
        $orderby = ($op)
            ? (($op == 'best')
                ? 'IFNULL((f.tvotes/NULLIF(f.votes,0)),0) DESC'
                : 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.time)),0)),0) DESC')
            : 'f.time DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ctitle = $ctitle ?? _FILES;
        $ntitle = ($op)
            ? (($op == 'best')
                ? $ctitle.' '.$conf['defis'].' '._BEST
                : $ctitle.' '.$conf['defis'].' '._POP)
            : $ctitle;
        $order = "WHERE (f.cid = :ncat1 OR c.parent = :ncat2) AND f.time <= NOW() AND f.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $params = ['ncat1' => $ncat, 'ncat2' => $ncat];
        $cids = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parent = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $cids[] = $caid;
        unset($result);
        if (isArray($cids)) {
            $caton = 1;
            array_unshift($cids, $ncat);
            $wcid = 'cid IN ('.implode(', ', array_map('intval', $cids)).')';
        } else {
            $caton = 0;
            $wcid = 'cid = '.(int)$ncat;
        }
        $onum = $wcid." AND time <= NOW() AND status != '0'";
    } else {
        $caton = 1;
        $hwhere = ($home) ? "AND f.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE f.time <= NOW() AND f.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY f.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _FILES;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['files']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle, 'htitle' => _FILES]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['files']['defis'], _FILES)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['files']['subcat'], $conf['files']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT f.id, f.cid, f.name, f.title, f.intro, f.body, f.time, f.counter, f.acomm, f.votes, f.tvotes, f.comments, f.hits,'
        .' c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_files AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        .' '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $stitle, $description, $bodytext, $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref  = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc  = $cdesc ?: $ctitle;
            $cimg   = ($cimg) ? img_find('categories/'.$cimg) : '';
            $post   = ($conf['files']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date   = ($conf['files']['date']) ? format_time($time) : '';
            $iso    = ($conf['files']['date']) ? date('c', strtotime($time)) : '';
            $hits   = ($conf['files']['hits']) ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _FILEHITS, 'label' => $hits, 'is_download' => true]) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
            $ask    = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$stitle.'&quot;?');
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'width'         => 100,
                'title_href'    => $thref,
                'title_attr'    => $stitle,
                'title_text'    => $stitle,
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img'  => $cimg,
                'text'          => $prs->filterContent($description, false, $conf['name']),
                'read_href'     => $thref,
                'read_text'     => _READMORE,
                'post_text'     => $post,
                'post_label'    => _POSTEDBY,
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => ($conf['files']['read']) ? $counter : '',
                'reads_label'   => _READS,
                'hits'          => $hits,
                'comm_href'     => ($acomm) ? $thref.'#comm' : '',
                'comm_text'     => ($acomm) ? $comm : '',
                'comm_label'    => _COMMENTS,
                'rating'        => $rating,
                'favorites'     => '',
                'voting'        => '',
                'editor'        => _EDITOR,
                'edit_href'     => $afile.'.php?name=files&amp;op=add&amp;id='.$id,
                'edit_text'     => _FULLEDIT,
                'delete_href'   => $afile.'.php?name=files&amp;op=files_delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token,
                'delete_text'   => _ONDELETE,
                'delete_ask'    => $ask,
                'is_moder'      => $ismoder,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        $url_extra = [];
        if ($ncat) $url_extra['cat'] = $ncat;
        if ($op)   $url_extra['op']  = $op;
        $cont .= getTplPager([
            'limit'     => $unum,
            'maxpg'     => $conf['files']['nump'],
            'table'     => '_files',
            'field'     => 'id',
            'mod'       => $conf['name'],
            'where'     => $onum,
            'url_extra' => $url_extra,
            'prefix'    => 'new/',
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $listnum = (int)($conf['files']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $order = "WHERE UCASE(f.title) LIKE BINARY :let AND f.time <= NOW() AND f.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $order = "WHERE f.time <= NOW() AND f.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $listnum);
    $sql = 'SELECT f.id, f.cid, f.name, f.title, f.time, c.title, c.intro, u.name'
        .' FROM '.PREFIX_DB.'_files AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY f.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST]);
    $cont = getModuleNavi(['title' => _LIST, 'htitle' => _FILES]);
    $rows = [];
    while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $cdesc = $cdesc ?: $ctitle;
        $rows[] = [
            'id'            => (string)$id,
            'title_href'    => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'title_attr'    => $title,
            'title_text'    => cutstr($title, 40),
            'title_new'     => getTplNewGraphic($time),
            'category_href' => $ctitle ? getSeoUrl(['name' => $conf['name'], 'cat' => $cid]) : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
            'post_text'     => ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM),
            'time_text'     => format_time($time),
            'time_iso'      => date('c', strtotime($time)),
            'time_label'    => _DATE,
        ];
    }
    $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
    $wparams = ($let) ? ['let' => $let.'%'] : [];
    $cont .= $tpl->getHtmlPart('liste', [
        'rows'        => $rows,
        'before_html' => ($conf['files']['letter'] && $rows) ? letter($conf['name']) : '',
        'table_open'  => [
            'open'       => true,
            'sortable'   => true,
            'col_id'     => _ID,
            'col_title'  => _TITLE,
            'col_cat'    => _CATEGORY,
            'col_poster' => _POSTER,
            'col_date'   => _DATE,
        ],
        'table_close' => [],
        'pager_html'  => $rows ? getTplPager([
            'limit'        => $listnum,
            'maxpg'        => $conf['files']['nump'],
            'table'        => '_files',
            'field'        => 'id',
            'mod'          => $conf['name'],
            'where'        => $onum,
            'where_params' => $wparams,
            'url_extra'    => $let ? ['op' => 'liste', 'let' => $let] : ['op' => 'liste'],
        ]) : '',
        'empty_alert' => ['is_warn' => false, 'text' => _NO_INFO],
    ]);
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl, $prs;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 'f.cid');
    $result = $db->getSqlQuery(
        'SELECT f.cid, f.name, f.title, f.url, f.intro, f.body, f.time, f.filesize, f.version, f.email, f.website,'
        .' f.counter, f.acomm, f.votes, f.tvotes, f.hits, f.status,'
        .' c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_files AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        ." WHERE f.id = :id AND f.time <= NOW() AND f.status != '0' ".$cwhere,
        ['id' => $id]
    );
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $url, $description, $bodytext, $time, $fsize, $fversion, $aemail, $awebsite,
            $counter, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $nick
        ] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($description, false, $conf['name']))), 160);
        $seoimg = getImgText($description, '', false);
        setHead([
            'title'  => $title,
            'ctitle' => $ctitle,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = getModuleNavi(['title' => _FILES]);
        if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['files']['defis'], _FILES)]);
        if ($conf['files']['viewcat']) $cont .= setCategories($conf['name'], $conf['files']['subcat'], $conf['files']['catdesc'], 0);
        $rawtext = $bodytext ? $description.$bodytext : $description;
        $cdesc = $cdesc ?: $ctitle;
        $cimg  = ($cimg) ? img_find('categories/'.$cimg) : '';
        $post  = ($conf['files']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date  = ($conf['files']['date']) ? format_time($time) : '';
        $iso   = ($conf['files']['date']) ? date('c', strtotime($time)) : '';
        $hits  = ($conf['files']['hits']) ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _FILEHITS, 'label' => $hits, 'is_download' => true]) : '';
        $rating    = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
        if (is_user() || $conf['files']['down'] == '1') {
            $onclick = (!$conf['files']['stream']) ? ' OnClick="javascript:window.open(\''.$url.'\');"' : '';
            $download = $tpl->getHtmlPart('files-download-form', ['name' => $conf['name'], 'id' => $id, 'onclick' => $onclick, 'submit_label' => _UPLOAD]);
        }
        $broken = ($conf['files']['broc'] == 1 && $status != '2') ? $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'broken', 'id' => $id]), 'title' => _BROCFILE, 'label' => _COMPLAINT, 'is_button_blue' => true]) : '';
        $cont .= $tpl->getHtmlPart('view', [
            'is_moder'      => is_moder($conf['name']),
            'id'            => $id,
            'title_text'    => filterTextHighlight($title, $word),
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_img'  => $cimg,
            'text'          => filterTextHighlight($prs->filterContent($rawtext, false, $conf['name']), $word),
            'post_text'     => $post,
            'post_label'    => _POSTEDBY,
            'date_text'     => $date,
            'date_iso'      => $iso,
            'date_label'    => _CHNGSTORY,
            'reads_text'    => ($conf['files']['read']) ? $counter : '',
            'reads_label'   => _READS,
            'hits'          => $hits,
            'rating'        => $rating,
            'favorites'     => $favorites,
            'size'          => _SIZE.': '.filterSize($fsize),
            'version'       => _VERSION.': '.$fversion,
            'email'         => ($aemail) ? _AUEMAIL.': '.anti_spam($aemail) : '',
            'home'          => ($awebsite) ? _SITE.': '.domain($awebsite) : '',
            'download'      => $download ?? '',
            'broken'        => $broken,
            'editor'        => _EDITOR,
            'edit_href'     => $afile.'.php?name=files&amp;op=add&amp;id='.$id,
            'edit_text'     => _FULLEDIT,
            'delete_href'   => $afile.'.php?name=files&amp;op=files_delete&amp;id='.$id.'&amp;token='.getSiteToken(),
            'delete_text'   => _ONDELETE,
            'delete_ask'    => $ask,
            'back_title'    => _BACK,
            'back_text'     => _BACK,
        ]);
        if ($conf['files']['link']) {
            $limit = (int)($conf['files']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery(
                'SELECT COUNT(id) FROM '.PREFIX_DB."_files WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0'",
                ['cid' => $cid, 'id' => $id]
            ));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery(
                    'SELECT id, title, intro, body, time FROM '.PREFIX_DB.'_files'
                    ." WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0' ORDER BY time DESC LIMIT ".$random.', '.$limit,
                    ['cid' => $cid, 'id' => $id]
                );
                $cont .= $tpl->getHtmlFrag('related', ['open' => true, 'title' => _CATASSOC]);
                while ([$aid, $title, $hometext, $bodytext, $time] = $db->getSqlRow($result)) {
                    $date = ($conf['files']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(
                        trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))),
                        ENT_QUOTES, 'UTF-8'
                    ), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href'       => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]),
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text'  => $date,
                        'date_iso'   => ($conf['files']['date']) ? date('c', strtotime($time)) : '',
                        'date_label' => _CHNGSTORY,
                        'text'       => $text,
                        'img_src'    => $img,
                    ]);
                }
                $cont .= $tpl->getHtmlFrag('related', []);
            }
        }
        if ($acomm) $cont .= setComShow($id, $acomm);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function add(): void {
    global $user, $conf, $stop, $tpl;
    if ((is_user() && $conf['files']['add'] == 1) || (!is_user() && $conf['files']['addquest'] == 1)) {
        $title       = getVar('post', 'title', 'title');
        $cid         = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'raw');
        $bodytext    = getVar('post', 'bodytext', 'raw');
        $postname    = getVar('post', 'postname', 'name');
        if (is_user()) {
            $userinfo = getUserInfo();
            $mail = getVar('post', 'mail', 'text', $userinfo['email']);
            $home = getVar('post', 'home', 'url', $userinfo['website']);
        } else {
            $mail = getVar('post', 'mail', 'text');
            $home = getVar('post', 'home', 'url', 'http://');
        }
        $url      = getVar('post', 'url', 'url', 'http://');
        $fversion = getVar('post', 'fversion', 'text');
        $fsize    = getVar('post', 'fsize', 'num');
        $info = _ADDFNOTE;
        if ($conf['files']['upload'] == 1) $info .= sprintf(_ADDFNOTE2, str_replace(',', ', ', $conf['files']['typefile']), filterSize($conf['files']['max_size']));
        $info .= ' '._ADDFNOTE3;
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'htitle' => _FILES]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($description) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $description, 'textb' => $bodytext, 'field' => '', 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $extra = '';
        if ($conf['files']['upload'] == 1) {
            $extra .= $tpl->getHtmlFrag('form-field-row', ['label' => _FILE_USER, 'field_html' => $tpl->getHtmlFrag('file-input', ['name_attr' => 'userfile'])]);
        }
        $extra .= $tpl->getHtmlFrag('form-field-row', ['label' => _URL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'url', 'value_attr' => $url, 'maxlength_num' => '100', 'placeholder_text' => _URL])]);
        $extra .= $tpl->getHtmlFrag('form-field-row', ['label' => _VERSION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'fversion', 'value_attr' => $fversion, 'maxlength_num' => '10', 'placeholder_text' => _VERSION])]);
        $extra .= $tpl->getHtmlFrag('form-field-row', ['label' => _SIZE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'fsize', 'value_attr' => $fsize, 'maxlength_num' => '10', 'placeholder_text' => _SIZE])]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'has_name'    => true,
            'is_user'     => is_user(),
            'name'        => $conf['name'],
            'token'       => htmlspecialchars(getSiteToken('files'), ENT_QUOTES, 'UTF-8'),
            'lbl_name'    => _YOURNAME,
            'lbl_email'   => _AUEMAIL,
            'lbl_title'   => _NAME,
            'lbl_cat'     => _CATEGORY,
            'lbl_text'    => _TEXT,
            'lbl_body'    => _ENDTEXT,
            'lbl_site'    => _SITE,
            'username'    => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname'    => $postname,
            'emailval'    => $mail,
            'titleval'    => $title,
            'catselect'   => getTplCategorySelect($conf['name'], $cid, 'cid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false])),
            'hometext'    => getTplTextarea(['id' => '1', 'name' => 'description', 'value' => $description, 'mod' => $conf['name'], 'rows' => '5', 'placeholder' => _TEXT, 'required' => '1']),
            'bodytext'    => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => $conf['name'], 'rows' => '15', 'placeholder' => _ENDTEXT, 'required' => '0']),
            'siteval'     => $home,
            'site_attr'   => 'site',
            'extrafields' => $extra,
            'captcha'     => getCaptcha(1),
            'submit'      => $tpl->getHtmlFrag('form-submit', ['op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['files']['add'] == 1) || (!is_user() && $conf['files']['addquest'] == 1)) {
        $title       = getVar('post', 'title', 'title');
        $cid         = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext    = getVar('post', 'bodytext', 'text');
        $postname    = getVar('post', 'postname', 'name');
        $mail        = getVar('post', 'mail', 'text');
        $home        = getVar('post', 'home', 'url');
        $url         = getVar('post', 'url', 'url');
        $fversion    = getVar('post', 'fversion', 'text');
        $fsize       = getVar('post', 'fsize', 'num');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'files')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_files WHERE title = :title', ['title' => $title])) > 0) $stop[] = _MEDIAEXIST;
        $userid = isset($user[0]) ? (int)$user[0] : 0;
        $filename = upload(1, $conf['files']['temp'], $conf['files']['typefile'], $conf['files']['max_size'], 'files', '1600', '1600', $userid);
        $url = ($filename) ? $conf['files']['temp'].'/'.$filename : $url;
        $fsize = ($filename) ? filesize($url) : $fsize;
        if (!$stop && !$url && getVar('post', 'posttype', 'var') == 'save') $stop[] = _UPLOADEROR2;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_files (id, cid, uid, name, title, intro, body, url, time, filesize, version, email, website, ip, status)'
                ." VALUES (NULL, :cid, :postid, :uname, :title, :intro, :body, :url, NOW(), :fsize, :fversion, :mail, :home, :ip, '0')",
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'fsize' => $fsize, 'fversion' => $fversion, 'mail' => $mail, 'home' => $home, 'ip' => getIp()]
            );
            update_points(9);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['files']['addmail'], $conf['name'], $puname, _FILES);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD, 'htitle' => _FILES]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISH, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function broken(): void {
    global $db, $conf, $tpl;
    $id = getVar('get', 'id', 'num');
    if ($conf['files']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB."_files SET status = '2' WHERE id = :id AND status != '0'", ['id' => $id]);
        setHead(['title' => _BROCFILE]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 'secs' => 5]);
        echo getModuleNavi(['title' => _BROCFILE, 'htitle' => _FILES]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTE, 'meta' => $meta]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function loading(): void {
    global $db, $conf, $tpl;
    $id = getVar('post', 'id', 'num');
    if (($id && is_user()) || ($id && $conf['files']['down'] == '1')) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$stitle, $url] = $db->getSqlRow($db->getSqlQuery('SELECT title, url FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]));
        update_points(11);
        if ($conf['files']['stream'] == 2) {
            $type = strtolower(substr(strrchr($url, '.'), 1));
            stream($url, getPass(10).'.'.$type);
        } elseif ($conf['files']['stream'] == '1') {
            stream($url, preg_replace('#(.*?)\/#i', '', $url));
        } else {
            $info = sprintf(_NOTEDOWNLOAD, $stitle, $tpl->getHtmlFrag('link', ['href' => $url, 'title' => _UPLOAD.': '.$stitle, 'label' => $url, 'is_blank' => true]));
            setHead(['title' => _FILES]);
            $cont = getModuleNavi(['title' => _FILES]);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
            $cont .= $tpl->getHtmlPart('navi-lower', [
                'back_title' => _BACK,
                'back_label' => _BACK,
                'home_href' => 'index.php?name='.$conf['name'],
                'home_title' => _PAGEHOME,
                'home_label' => _PAGEHOME,
                'top_title' => _PAGETOP,
                'top_label' => _PAGETOP,
            ]);
            echo $cont;
            setFoot();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: files(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
    case 'loading': loading(); break;
}
