<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function faq(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 's.cid');
    $unum = getUserNews($conf['faq']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $word = getVar('get', 'word', 'word');
    $params = [];
    if (!$ncat && $op && $conf['faq']['rate']) {
        $caton = 0;
        if ($op == 'best') {
            $orderby = 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC';
            $ntitle = _BEST;
        } else {
            $orderby = 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC';
            $ntitle = _POP;
        }
        $order = "WHERE s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $onum = "time <= NOW() AND status != '0'";
    } elseif ($ncat) {
        if ($op == 'best') {
            $orderby  = 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC';
            $orderbyf = 'IFNULL((score/NULLIF(ratings,0)),0) DESC';
        } elseif ($op) {
            $orderby  = 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC';
            $orderbyf = 'IFNULL((counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(time)),0)),0) DESC';
        } else {
            $orderby  = 's.time DESC';
            $orderbyf = 'time DESC';
        }
        $qres = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]);
        [$ctitle] = $db->getSqlRow($qres);
        $ctitle = $ctitle ?? _FAQ;
        $ntitle = ($op)
            ? (($op == 'best')
                ? $ctitle.' '.$conf['defis'].' '._BEST
                : $ctitle.' '.$conf['defis'].' '._POP)
            : $ctitle;
        $order = "WHERE (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
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
        $hwhere = ($home) ? "AND s.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE s.time <= NOW() AND s.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY s.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _FAQ;
    }
    setHead(['title' => $ntitle, 'kind' => 'collection']);
    $cont = '';
    if (!$home || ($home && $conf['faq']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle, 'htitle' => _FAQ]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['faq']['defis'], _FAQ)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], $ncat);
    }
    if ($ncat) {
        $result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE cid = :ncat AND time <= NOW() AND status != '0' ORDER BY ".$orderbyf, ['ncat' => $ncat]);
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$fid, $ftitle] = $row;
            $rows[] = [
                'cells' => [[
                    'href' => '#'.$fid,
                    'title' => $ftitle,
                    'link_label_html' => filterTextHighlight($ftitle, $word),
                    'is_faq_link' => true,
                ]],
            ];
        }
        if ($rows) {
            $cont .= $tpl->getHtmlPart('content-list', [
                'rows' => $rows,
                'table_open' => ['open' => true, 'is_faq' => true],
                'table_close' => [],
            ]);
        }
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $limit = (!$ncat) ? 'LIMIT '.$offset.', '.$unum : '';
    $sql = 'SELECT s.id, s.cid, s.name, s.title, s.time, s.body, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, c.ordern, u.name'
        .' FROM '.PREFIX_DB.'_faq AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' '.$order.' '.$limit;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $stitle, $time, $hometext, $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg  = getCategoryIcon($cimg);
            $post  = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date  = ($conf['faq']['date']) ? format_time($time) : '';
            $iso   = ($conf['faq']['date']) ? date('c', strtotime($time)) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $ratings, $score, '');
            $edit = $afile.'.php?name=faq&op=faq_add&id='.$id;
            $del = $afile.'.php?name=faq&op=faq_delete&id='.$id.'&refer=1&token='.$token;
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'is_nested'     => false,
                'width'         => 100,
                'title_href'    => $thref,
                'title_attr'    => $stitle,
                'title_text'    => $stitle,
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_icon'  => $cimg,
                'category_tone'  => $cordern % 6,
                'text'          => $prs->filterContent($hometext, false, $conf['name'], 2),
                'read_href'     => $thref,
                'read_text'     => _READMORE,
                'post_text'     => $post,
                'post_label'    => _POSTEDBY,
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => ($conf['faq']['read']) ? $counter : '',
                'reads_label'   => _READS,
                'hits'          => '',
                'comm_href'     => ($acomm) ? $thref.'#comm' : '',
                'comm_text'     => ($acomm) ? $comm : '',
                'comm_label'    => _COMMENTS,
                'rating'        => $rating,
                'favorites'     => getFavoriteButton($id, $conf['name']),
                'voting'        => '',
                'is_moder'      => $ismoder,
                ...($ismoder ? getTplEditMenu($edit, $del, $stitle) : []),
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        if (!$ncat) {
            $url_extra = [];
            if ($op) $url_extra['op'] = $op;
            $cont .= getTplPager([
                'limit'     => $unum,
                'maxpg'     => $conf['faq']['nump'],
                'table'     => '_faq',
                'field'     => 'id',
                'mod'       => $conf['name'],
                'where'     => $onum,
                'url_extra' => $url_extra,
                'prefix'    => 'new/',
            ]);
        }
    } else {
        if ((int)$num > 1) setError(404);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $tpl;
    $cwhere = catmids($conf['name'], 's.cid');
    $listnum = (int)($conf['faq']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $order = "WHERE UCASE(s.title) LIKE BINARY UCASE(:let) AND s.time <= NOW() AND s.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $order = "WHERE s.time <= NOW() AND s.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $listnum);
    $sql = 'SELECT s.id, s.cid, s.name, s.title, s.time, c.title, c.intro, u.name'
        .' FROM '.PREFIX_DB.'_faq AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY s.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _LIST, 'htitle' => _FAQ]);
    $rows = [];
    while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $cdesc = $cdesc ?: $ctitle;
        $rows[] = [
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
    }
    $onum = ($let) ? "UCASE(title) LIKE BINARY UCASE(:let) AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
    $wparams = ($let) ? ['let' => $let.'%'] : [];
    if (!$rows) setError(404);
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows'        => $rows,
        'before_html' => ($conf['faq']['letter'] && $rows) ? letter($conf['name']) : '',
        'table_open'  => [
            'open'       => true,
            'sortable'   => true,
            'col_id'     => _ID,
            'col_title'  => _QUESTION,
            'col_cat'    => _CATEGORY,
            'col_poster' => _POSTER,
        ],
        'table_close' => [],
        'pager_html'  => $rows ? getTplPager([
            'limit'        => $listnum,
            'maxpg'        => $conf['faq']['nump'],
            'table'        => '_faq',
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
    $pag = getVar('get', 'num', 'num', '1');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 's.cid');
    $sql = 'SELECT s.cid, s.name, s.title, s.time, s.body, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, c.ordern, u.name'
        .' FROM '.PREFIX_DB.'_faq AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        ." WHERE s.id = :id AND s.time <= NOW() AND s.status != '0' ".$cwhere;
    $result = $db->getSqlQuery($sql, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $time, $hometext, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        setHead([
            'title'  => $title,
            'kind'   => 'article',
            'ctitle' => $ctitle,
            'cid' => $cid,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = getModuleNavi(['title' => _FAQ, 'is_heading' => false]);
        if ($cid) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['faq']['defis'], _FAQ)]);
        $catlist = $conf['faq']['viewcat'] ? setCategories($conf['name'], $conf['faq']['subcat'], $conf['faq']['catdesc'], 0) : '';
        $conpag = explode('[pagebreak]', $hometext);
        $pageno = count($conpag);
        if ($pag > $pageno) $pag = $pageno;
        $pagei = (int)$pag - 1;
        $cdesc = $cdesc ?: $ctitle;
        $cimg  = getCategoryIcon($cimg);
        $post  = ($conf['faq']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date  = ($conf['faq']['date']) ? format_time($time) : '';
        $iso   = ($conf['faq']['date']) ? date('c', strtotime($time)) : '';
        $rating    = getRatingAsync(1, $id, $conf['name'], $ratings, $score, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ismoder = is_moder($conf['name']);
        $edit = $afile.'.php?name=faq&op=faq_add&id='.$id;
        $del = $afile.'.php?name=faq&op=faq_delete&id='.$id.'&token='.getSiteToken();
        $cont .= $tpl->getHtmlPart('view', [
            'is_moder'      => $ismoder,
            'id'            => $id,
            'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'share_title' => $title,
            'title_text'    => filterTextHighlight($title, $word),
            'title_new'     => getTplNewGraphic($time),
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_icon'  => $cimg,
            'category_tone'  => $cordern % 6,
            'text'          => filterTextHighlight($prs->filterContent($conpag[$pagei], false, $conf['name'], 1), $word),
            'post_text'     => $post,
            'post_label'    => _POSTEDBY,
            'date_text'     => $date,
            'date_iso'      => $iso,
            'date_label'    => _CHNGSTORY,
            'reads_text'    => ($conf['faq']['read']) ? $counter : '',
            'reads_label'   => _READS,
            'hits'          => '',
            'rating'        => $rating,
            'favorites'     => $favorites,
            'voting'        => '',
            ...($ismoder ? getTplEditMenu($edit, $del, $title) : []),
            'back_title'    => _BACK,
            'back_text'     => _BACK,
        ]);
        $cont .= $catlist;
        $cont .= getPageNumbers($conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['faq']['nump'], (int)$pag, '#'.$id);
        if ($conf['faq']['link']) {
            $limit = (int)($conf['faq']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery(
                'SELECT COUNT(id) FROM '.PREFIX_DB."_faq WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0'",
                ['cid' => $cid, 'id' => $id]
            ));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery(
                    'SELECT id, title, time, body FROM '.PREFIX_DB.'_faq'
                    ." WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0' ORDER BY time DESC LIMIT ".$random.', '.$limit,
                    ['cid' => $cid, 'id' => $id]
                );
                $cont .= $tpl->getHtmlPart('related', ['open' => true, 'title' => _CATASSOC]);
                while ([$aid, $title, $time, $hometext] = $db->getSqlRow($result)) {
                    $adate = ($conf['faq']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $atext = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), ENT_QUOTES, 'UTF-8'), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]);
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href'       => $href,
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text'  => $adate,
                        'date_iso'   => ($conf['faq']['date']) ? date('c', strtotime($time)) : '',
                        'date_label' => _CHNGSTORY,
                        'text'       => $atext,
                        'img_src'    => $img,
                        'image_link' => ['href' => $href, 'title' => $title, 'img_src' => $img, 'img_alt' => $title, 'is_related_image' => true],
                        'title_link' => ['href' => $href, 'title' => $title, 'label' => $title],
                        'date_badge' => ($adate) ? ['iso' => date('c', strtotime($time)), 'title' => _CHNGSTORY, 'text' => $adate, 'is_related_date' => true] : [],
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
    if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'raw');
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'htitle' => _FAQ]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($hometext) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $hometext, 'textb' => '', 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBMIT.' '._PAGENOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('faq')]);
        $nameField = is_user()
            ? $tpl->getHtmlFrag('span', ['is_form_value' => true, 'text' => filterText(substr($user[1], 0, 25))])
            : $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'postname', 'value_attr' => $postname, 'placeholder_text' => _YOURNAME, 'is_required' => true]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _YOURNAME, 'hide_label' => !is_user(), 'field_html' => $nameField]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _QUESTION,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 100, 'placeholder_text' => _QUESTION, 'is_required' => true]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _CATEGORY,
            'field_html' => getTplCategorySelect($conf['name'], $cid, 'catid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false])),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _ANSWER,
            'hide_label' => true,
            'field_html' => getTplTextarea([
                'id' => '1',
                'name' => 'hometext',
                'value' => $hometext,
                'mod' => $conf['name'],
                'rows' => '10',
                'placeholder' => _ANSWER,
                'required' => '1',
            ]),
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name'      => $conf['name'],
            'fields'    => $fields,
            'captcha'   => getCaptcha('comment'),
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
    if ((is_user() && $conf['faq']['add'] == 1) || (!is_user() && $conf['faq']['addquest'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $postname = getVar('post', 'postname', 'name');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'faq')) $stop[] = _ERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_faq (id, cid, uid, name, title, time, body, ip, status)'
                ." VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :body, :ip, '0')",
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname,
                    'title' => $title, 'body' => $hometext, 'ip' => getIp()]
            );
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['faq']['addmail'], $conf['name'], $puname, _FAQ);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD, 'htitle' => _FAQ]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBTEXT, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: faq(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
