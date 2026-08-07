<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function links(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 'f.cid');
    $unum = getUserNews($conf['links']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['links']['rate']) {
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
        if ($op == 'best') {
            $orderby = 'IFNULL((f.tvotes/NULLIF(f.votes,0)),0) DESC';
        } elseif ($op) {
            $orderby = 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.time)),0)),0) DESC';
        } else {
            $orderby = 'f.time DESC';
        }
        $qres = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]);
        [$ctitle] = $db->getSqlRow($qres);
        $ctitle = $ctitle ?? _LINKS;
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
        $ntitle = _LINKS;
    }
    setHead(['title' => $ntitle, 'kind' => 'collection']);
    $cont = '';
    if (!$home || ($home && $conf['links']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle, 'htitle' => _LINKS]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['links']['defis'], _LINKS)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT f.id, f.cid, f.name, f.title, f.intro, f.body, f.time, f.counter, f.acomm, f.votes, f.tvotes, f.comments, f.hits, c.title, c.intro, c.img, c.ordern, u.name'
        .' FROM '.PREFIX_DB.'_links AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        .' '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $stitle, $description, , $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg = getCategoryIcon($cimg);
            $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['links']['date']) ? format_time($time) : '';
            $iso = ($conf['links']['date']) ? date('c', strtotime($time)) : '';
            $hits = ($conf['links']['hits']) ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _LINKHITS, 'label' => $hits, 'is_download' => true]) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
            $edit = $afile.'.php?name=links&op=links_add&id='.$id;
            $del = $afile.'.php?name=links&op=links_delete&id='.$id.'&refer=1&token='.$token;
            $cont .= $tpl->getHtmlFrag('card', [
                'id' => $id,
                'is_nested' => false,
                'width' => 100,
                'title_href' => $thref,
                'title_attr' => $stitle,
                'title_text' => $stitle,
                'title_new' => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_icon' => $cimg,
                'category_tone' => $cordern % 6,
                'text' => $prs->filterContent($description, false, $conf['name'], 2),
                'read_href' => $thref,
                'read_text' => _READMORE,
                'post_text' => $post,
                'post_label' => _POSTEDBY,
                'date_text' => $date,
                'date_iso' => $iso,
                'date_label' => _CHNGSTORY,
                'reads_text' => ($conf['links']['read']) ? $counter : '',
                'reads_label' => _READS,
                'hits' => $hits,
                'comm_href' => ($acomm) ? $thref.'#comm' : '',
                'comm_text' => ($acomm) ? $comm : '',
                'comm_label' => _COMMENTS,
                'rating' => $rating,
                'favorites' => getFavoriteButton($id, $conf['name']),
                'voting' => '',
                'is_moder' => $ismoder,
                ...($ismoder ? getTplEditMenu($edit, $del, $stitle) : []),
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        $url_extra = [];
        if ($ncat) $url_extra['cat'] = $ncat;
        if ($op)   $url_extra['op']  = $op;
        $cont .= getTplPager([
            'limit'     => $unum,
            'maxpg'     => $conf['links']['nump'],
            'table'     => '_links',
            'field'     => 'id',
            'mod'       => $conf['name'],
            'where'     => $onum,
            'url_extra' => $url_extra,
            'prefix'    => 'new/',
        ]);
    } else {
        if ((int)$num > 1) setError(404);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $afile, $conf, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $listnum = (int)($conf['links']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $order = "WHERE UCASE(f.title) LIKE BINARY UCASE(:let) AND f.time <= NOW() AND f.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $order = "WHERE f.time <= NOW() AND f.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $listnum);
    $sql = 'SELECT f.id, f.cid, f.name, f.title, f.time, c.title, c.intro, u.name'
        .' FROM '.PREFIX_DB.'_links AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY f.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _LIST, 'htitle' => _LINKS]);
    $rows = [];
    $ismoder = is_moder($conf['name']);
    $token = $ismoder ? getSiteToken() : '';
    while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $cdesc = $cdesc ?: $ctitle;
        $row = [
            'id'            => (string)$id,
            'title_href'    => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'title_attr'    => $title,
            'title_text'    => $title,
            'category_href' => $ctitle ? getSeoUrl(['name' => $conf['name'], 'cat' => $cid]) : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
            'post_text'     => ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM),
            'report'        => getTplTitleTip([['label' => _DATE, 'value' => format_time($time, _TIMESTRING)]]),
        ];
        if ($ismoder) {
            $edit = $afile.'.php?name=links&op=add&id='.$id;
            $del = $afile.'.php?name=links&op=delete&id='.$id.'&refer=1&token='.$token;
            $row += getTplEditMenu($edit, $del, $title);
        }
        $rows[] = $row;
    }
    $onum = ($let) ? "UCASE(title) LIKE BINARY UCASE(:let) AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
    $wparams = ($let) ? ['let' => $let.'%'] : [];
    if (!$rows) setError(404);
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows'        => $rows,
        'before_html' => ($conf['links']['letter'] && $rows) ? letter($conf['name']) : '',
        'table_open'  => [
            'open'       => true,
            'sortable'   => true,
            'col_id'     => _ID,
            'col_title'  => _TITLE,
            'col_cat'    => _CATEGORY,
            'col_poster' => _POSTER,
        ],
        'table_close' => [],
        'pager_html'  => $rows ? getTplPager([
            'limit'        => $listnum,
            'maxpg'        => $conf['links']['nump'],
            'table'        => '_links',
            'field'        => 'id',
            'mod'          => $conf['name'],
            'where'        => $onum,
            'where_params' => $wparams,
            'url_extra'    => $let ? ['op' => 'liste', 'let' => $let] : ['op' => 'liste'],
        ]) : '',
    ]);
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl, $prs;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 'f.cid');
    $sql = 'SELECT f.cid, f.name, f.title, f.url, f.intro, f.body, f.time, f.email, f.counter, f.acomm, f.votes, f.tvotes, f.hits, f.status, c.title, c.intro, c.img, c.ordern, u.name'
        .' FROM '.PREFIX_DB.'_links AS f'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        ." WHERE f.id = :id AND f.time <= NOW() AND f.status != '0' ".$cwhere;
    $result = $db->getSqlQuery($sql, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $authorurl, $description, $bodytext, $time, $aemail, $counter, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($description, false, $conf['name']))), 160);
        $seoimg = getImgText($description, '', false);
        setHead([
            'title'  => $title,
            'ctitle' => $ctitle,
            'cid' => $cid,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = getModuleNavi(['title' => _LINKS, 'is_heading' => false]);
        if ($cid) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['links']['defis'], _LINKS)]);
        $catlist = $conf['links']['viewcat'] ? setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], 0) : '';
        $rawtext = $bodytext ? $description.$bodytext : $description;
        $cdesc = $cdesc ?: $ctitle;
        $cimg = getCategoryIcon($cimg);
        $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['links']['date']) ? format_time($time) : '';
        $iso = ($conf['links']['date']) ? date('c', strtotime($time)) : '';
        $hits = ($conf['links']['hits']) ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _LINKHITS, 'label' => $hits, 'is_download' => true]) : '';
        $rating = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ismoder = is_moder($conf['name']);
        $edit = $afile.'.php?name=links&op=links_add&id='.$id;
        $del = $afile.'.php?name=links&op=links_delete&id='.$id.'&token='.getSiteToken();
        if (is_user() || $conf['links']['links'] == '1') {
            $onclick = ' OnClick="javascript:window.open(\''.str_replace(['\\', "'"], ['\\\\', "\\'"], $authorurl).'\')"';
            $download = $tpl->getHtmlPart('form-wrap', [
                'action' => 'index.php?name='.$conf['name'],
                'method' => 'post',
                'form_attr' => 'class="sl-inline-form"',
                'content_html' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'id', 'value_attr' => (string)$id])
                    .$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'loading'])
                    .$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _DOWNLLINK, 'is_legacy_green' => true, 'input_attr' => trim($onclick)]),
            ]);
        }
        $broken = ($conf['links']['broc'] == 1 && $status != '2') ? $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'broken', 'id' => $id]), 'title' => _BROCLINK, 'label' => _COMPLAINT, 'is_button_blue' => true]) : '';
        $email = ($aemail) ? _AUEMAIL.': '.htmlspecialchars($aemail, ENT_QUOTES, 'UTF-8') : '';
        $home = ($authorurl) ? _SITE.': '.domain($authorurl) : '';
        $cont .= $tpl->getHtmlPart('view', [
            'id'            => $id,
            'favorites'     => $favorites,
            'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'share_title' => $title,
            'title_text'    => filterTextHighlight($title, $word),
            'title_new'     => getTplNewGraphic($time),
            'hits'          => $hits,
            'reads_text'    => ($conf['links']['read']) ? $counter : '',
            'reads_label'   => _READS,
            'post_text'     => $post,
            'post_label'    => _POSTEDBY,
            'date_text'     => $date,
            'date_iso'      => $iso,
            'date_label'    => _CHNGSTORY,
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_icon'  => $cimg,
            'category_tone'  => $cordern % 6,
            'text'          => filterTextHighlight($prs->filterContent($rawtext, false, $conf['name'], 1), $word),
            'size'          => '',
            'version'       => '',
            'email'         => $email,
            'home'          => $home,
            'rating'        => $rating,
            'download'      => $download ?? '',
            'broken'        => $broken,
            'back_title'    => _BACK,
            'back_text'     => _BACK,
            'is_moder'      => $ismoder,
            ...($ismoder ? getTplEditMenu($edit, $del, $title) : []),
        ]);
        $cont .= $catlist;
        if ($conf['links']['link']) {
            $limit = (int)($conf['links']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_links WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT id, title, intro, body, time FROM '.PREFIX_DB.'_links WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= $tpl->getHtmlPart('related', ['open' => true, 'title' => _CATASSOC]);
                while ([$aid, $title, $hometext, $bodytext, $time] = $db->getSqlRow($result)) {
                    $date = ($conf['links']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), ENT_QUOTES, 'UTF-8'), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : getThemeImagePath('logos/slaed_logo_60x60.png');
                    $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]);
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href'       => $href,
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text'  => $date,
                        'date_iso'   => ($conf['links']['date']) ? date('c', strtotime($time)) : '',
                        'date_label' => _CHNGSTORY,
                        'text'       => $text,
                        'img_src'    => $img,
                        'image_link' => ['href' => $href, 'title' => $title, 'img_src' => $img, 'img_alt' => $title, 'is_related_image' => true],
                        'title_link' => ['href' => $href, 'title' => $title, 'label' => $title],
                        'date_badge' => ($date) ? ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => $date, 'is_related_date' => true] : [],
                    ]);
                }
                $cont .= $tpl->getHtmlPart('related', []);
            }
        }
        if ($acomm) $cont .= setComShow($id, $acomm);
        echo $cont;
        setFoot();
    } else {
        setError(404);
    }
}

function add(): void {
    global $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['links']['add'] == 1) || (!is_user() && $conf['links']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        if (is_user()) {
            $userinfo = getUserInfo();
            $mail = getVar('post', 'mail', 'text', $userinfo['email']);
            $site = getVar('post', 'site', 'url', $userinfo['website']);
        } else {
            $mail = getVar('post', 'mail', 'text');
            $site = getVar('post', 'site', 'url', 'http://');
        }
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'htitle' => _LINKS]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($description) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $description, 'textb' => $bodytext, 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADDFNOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('links')]);
        $nameField = is_user()
            ? $tpl->getHtmlFrag('span', ['is_form_value' => true, 'text' => filterText(substr($user[1], 0, 25))])
            : $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'postname', 'value_attr' => $postname, 'placeholder_text' => _YOURNAME, 'is_required' => true]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _YOURNAME, 'hide_label' => !is_user(), 'field_html' => $nameField]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _AUEMAIL,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'name_attr' => 'mail',
                'value_attr' => $mail,
                'maxlength_num' => 100,
                'placeholder_text' => _AUEMAIL,
                'is_required' => true,
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _SITENAME,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'value_attr' => $title,
                'maxlength_num' => 100,
                'placeholder_text' => _SITENAME,
                'is_required' => true,
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _CATEGORY, 'field_html' => getTplCategorySelect($conf['name'], $cid, 'cid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false]))]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _TEXT,
            'hide_label' => true,
            'field_html' => getTplTextarea([
                'id' => '1',
                'name' => 'description',
                'value' => $description,
                'mod' => $conf['name'],
                'store' => 'links.intro',
                'rows' => '5',
                'placeholder' => _TEXT,
                'required' => '1',
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _ENDTEXT,
            'hide_label' => true,
            'field_html' => getTplTextarea([
                'id' => '2',
                'name' => 'bodytext',
                'value' => $bodytext,
                'mod' => $conf['name'],
                'store' => 'links.body',
                'rows' => '15',
                'placeholder' => _ENDTEXT,
                'required' => '0',
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _URL,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'site',
                'value_attr' => $site,
                'maxlength_num' => 100,
                'placeholder_text' => _URL,
            ]),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name'      => $conf['name'],
            'fields'    => $fields,
            'captcha'   => getPageCaptcha('comment'),
            'submit'    => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['links']['add'] == 1) || (!is_user() && $conf['links']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        $mail = getVar('post', 'mail', 'text');
        $site = getVar('post', 'site', 'url');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'links')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR10;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if ($room = checkEditorTextRoom($description, 'links.intro')) $stop[] = $room;
        if ($room = checkEditorTextRoom($bodytext, 'links.body')) $stop[] = $room;
        if (!$site) $stop[] = _CERROR4;
        checkemail($mail);
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_links WHERE url = :site', ['site' => $site])) > 0) $stop[] = _LINKEXIST;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_links'
                .' (id, cid, uid, name, title, intro, body, url, time, email, ip, status)'
                ." VALUES (NULL, :cid, :postid, :uname, :title, :intro, :body, :site, NOW(), :mail, :ip, '0')",
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname,
                    'title' => $title, 'intro' => $description, 'body' => $bodytext,
                    'site' => $site, 'mail' => $mail, 'ip' => getIp()]
            );
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['links']['addmail'], $conf['name'], $puname, _LINKS);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD, 'htitle' => _LINKS]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISHL, 'meta' => $meta]);
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
    if ($conf['links']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET status = \'2\' WHERE id = :id AND status != \'0\'', ['id' => $id]);
        setHead(['title' => _BROCLINK]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'].'&op=view&id='.$id, 'secs' => 5]);
        echo getModuleNavi(['title' => _BROCLINK, 'htitle' => _LINKS]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTEL, 'meta' => $meta]);
        setFoot();
    } else {
        setError(404);
    }
}

function loading(): void {
    global $db, $conf, $tpl;
    $id = getVar('post', 'id', 'num');
    if (($id && is_user()) || ($id && $conf['links']['links'] == '1')) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$title, $url] = $db->getSqlRow($db->getSqlQuery('SELECT title, url FROM '.PREFIX_DB.'_links WHERE id = :id', ['id' => $id]));
        addPointsAction('linkout', $id, 23);
        $info = sprintf(_NOTELINKLOAD, $title, domain($url));
        setHead(['title' => _LINKS]);
        $cont = getModuleNavi(['title' => _LINKS, 'is_heading' => false]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
        $cont .= $tpl->getHtmlPart('navi-lower', [
            'back_button' => ['button_type' => 'button', 'title' => _BACK, 'label' => _BACK, 'is_back' => true, 'is_navi_lower' => true],
            'home_link' => ['href' => 'index.php?name='.$conf['name'], 'title' => _PAGEHOME, 'label' => _PAGEHOME, 'is_navi_lower' => true],
            'top_link' => ['href' => '#top', 'title' => _PAGETOP, 'label' => _PAGETOP, 'is_navi_lower' => true],
        ]);
        echo $cont;
        setFoot();
    } else {
        setError(404);
    }
}

switch ($op) {
    default: links(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
    case 'loading': loading(); break;
}
