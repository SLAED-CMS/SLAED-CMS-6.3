<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function pages(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 's.cid');
    $unum = getUserNews($conf['pages']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['pages']['rate']) {
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
            $orderby = 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC';
        } elseif ($op) {
            $orderby = 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC';
        } else {
            $orderby = 's.time DESC';
        }
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ctitle = $ctitle ?? _PAGES;
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
        $ntitle = _PAGES;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['pages']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle, 'htitle' => _PAGES]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('category-nav', ['crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['pages']['defis'], _PAGES)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['pages']['subcat'], $conf['pages']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT s.id, s.cid, s.name, s.title, s.time, s.intro, s.body, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_pages AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $stitle, $time, $hometext, , $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl([
                'name'   => $conf['name'],
                'op'     => 'view',
                'id'     => $id,
                'title'  => $stitle,
                'ctitle' => $ctitle,
            ]);
            $chref  = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc  = $cdesc ?: $ctitle;
            $cimg   = ($cimg) ? img_find('categories/'.$cimg) : '';
            $post   = ($conf['pages']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date   = ($conf['pages']['date']) ? format_time($time) : '';
            $iso    = ($conf['pages']['date']) ? date('c', strtotime($time)) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $ratings, $score, '');
            $edit = $afile.'.php?name=pages&amp;op=add&amp;id='.$id;
            $del = $afile.'.php?op=page_delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token;
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'title_href'    => $thref,
                'title_attr'    => $stitle,
                'title_text'    => $stitle,
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img'  => $cimg,
                'text'          => $prs->filterContent($hometext, false, $conf['name']),
                'read_href'     => $thref,
                'read_text'     => _READMORE,
                'post_text'     => $post,
                'post_label'    => _POSTEDBY,
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => ($conf['pages']['read']) ? $counter : '',
                'reads_label'   => _READS,
                'hits'          => '',
                'comm_href'     => ($acomm) ? $thref.'#comm' : '',
                'comm_text'     => ($acomm) ? $comm : '',
                'comm_label'    => _COMMENTS,
                'rating'        => $rating,
                'favorites'     => '',
                'voting'        => '',
                'is_moder'      => $ismoder,
                ...($ismoder ? getTplEditMenu($edit, $del, $stitle) : []),
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        $url_extra = [];
        if ($ncat) $url_extra['cat'] = $ncat;
        if ($op)   $url_extra['op']  = $op;
        $cont .= getTplPager([
            'limit'     => $unum,
            'maxpg'     => $conf['pages']['nump'],
            'table'     => '_pages',
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
    $cwhere = catmids($conf['name'], 's.cid');
    $listnum = (int)($conf['pages']['listnum']);
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
        .' FROM '.PREFIX_DB.'_pages AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY s.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST]);
    $cont = getModuleNavi(['title' => _LIST, 'htitle' => _PAGES]);
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
            'title_new'     => getTplNewGraphic($time),
            'category_href' => $ctitle ? getSeoUrl(['name' => $conf['name'], 'cat' => $cid]) : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
            'post_text'     => ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM),
            'report'        => getTplTitleTip([['label' => _DATE, 'value' => format_time($time, _TIMESTRING)]]),
        ];
        if ($ismoder) {
            $edit = $afile.'.php?name=pages&amp;op=add&amp;id='.$id;
            $del = $afile.'.php?name=pages&amp;op=delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token;
            $row += getTplEditMenu($edit, $del, $title);
        }
        $rows[] = $row;
    }
    $onum = ($let) ? "UCASE(title) LIKE BINARY UCASE(:let) AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
    $wparams = ($let) ? ['let' => $let.'%'] : [];
    if (!$rows) setError(404);
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows'        => $rows,
        'before_html' => ($conf['pages']['letter'] && $rows) ? letter($conf['name']) : '',
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
            'maxpg'        => $conf['pages']['nump'],
            'table'        => '_pages',
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
    $result = $db->getSqlQuery(
        'SELECT s.cid, s.name, s.title, s.time, s.intro, s.body, s.counter, s.acomm, s.score, s.ratings,'
        .' c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_pages AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        ." WHERE s.id = :id AND s.time <= NOW() AND s.status != '0' ".$cwhere,
        ['id' => $id]
    );
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $time, $hometext, $bodytext, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        setHead([
            'title'  => $title,
            'ctitle' => $ctitle,
            'cid' => $cid,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = getModuleNavi(['title' => _PAGES]);
        if ($cid) $cont .= $tpl->getHtmlFrag('category-nav', ['crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['pages']['defis'], _PAGES)]);
        if ($conf['pages']['viewcat']) $cont .= setCategories($conf['name'], $conf['pages']['subcat'], $conf['pages']['catdesc'], 0);
        $rawtext = $bodytext ? $hometext.$bodytext : $hometext;
        $conpag = explode('[pagebreak]', $rawtext);
        $pageno = count($conpag);
        if ($pag > $pageno) $pag = $pageno;
        $pagei = (int)$pag - 1;
        $cdesc = $cdesc ?: $ctitle;
        $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
        $post = ($conf['pages']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['pages']['date']) ? format_time($time) : '';
        $iso  = ($conf['pages']['date']) ? date('c', strtotime($time)) : '';
        $rating    = getRatingAsync(1, $id, $conf['name'], $ratings, $score, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ismoder = is_moder($conf['name']);
        $edit = $afile.'.php?name=pages&amp;op=add&amp;id='.$id;
        $del = $afile.'.php?op=page_delete&amp;id='.$id.'&amp;token='.getSiteToken();
        $cont .= $tpl->getHtmlPart('view', [
            'is_moder'      => $ismoder,
            'id'            => $id,
            'title_text'    => filterTextHighlight($title, $word),
            'title_new'     => '',
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_img'  => $cimg,
            'text'          => filterTextHighlight($prs->filterContent($conpag[$pagei], false, $conf['name']), $word),
            'post_text'     => $post,
            'post_label'    => _POSTEDBY,
            'date_text'     => $date,
            'date_iso'      => $iso,
            'date_label'    => _CHNGSTORY,
            'reads_text'    => ($conf['pages']['read']) ? $counter : '',
            'reads_label'   => _READS,
            'hits'          => '',
            'rating'        => $rating,
            'favorites'     => $favorites,
            'voting'        => '',
            ...($ismoder ? getTplEditMenu($edit, $del, $title) : []),
            'back_title'    => _BACK,
            'back_text'     => _BACK,
        ]);
        $cont .= getPageNumbers($conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['pages']['nump'], (int)$pag, '#'.$id);
        if ($conf['pages']['link']) {
            $limit = (int)($conf['pages']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery(
                'SELECT COUNT(id) FROM '.PREFIX_DB.'_pages WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'',
                ['cid' => $cid, 'id' => $id]
            ));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery(
                    'SELECT id, title, time, intro, body FROM '.PREFIX_DB.'_pages'
                    .' WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit,
                    ['cid' => $cid, 'id' => $id]
                );
                $cont .= $tpl->getHtmlPart('related', ['open' => true, 'title' => _CATASSOC]);
                while ([$aid, $title, $time, $hometext, $bodytext] = $db->getSqlRow($result)) {
                    $date = ($conf['pages']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(
                        trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))),
                        ENT_QUOTES, 'UTF-8'
                    ), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]);
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href'       => $href,
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text'  => $date,
                        'date_iso'   => ($conf['pages']['date']) ? date('c', strtotime($time)) : '',
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
    if ((is_user() && $conf['pages']['add'] == 1) || (!is_user() && $conf['pages']['addquest'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'raw');
        $bodytext = getVar('post', 'bodytext', 'raw');
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'htitle' => _PAGES]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($hometext) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $hometext, 'textb' => $bodytext, 'field' => '', 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBMIT.' '._PAGENOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('pages')]);
        $nameField = is_user()
            ? $tpl->getHtmlFrag('span', ['is_form_value' => true, 'text' => filterText(substr($user[1], 0, 25))])
            : $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'postname', 'value_attr' => $postname, 'placeholder_text' => _YOURNAME, 'is_required' => true]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _YOURNAME, 'field_html' => $nameField]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _TITLE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 100, 'placeholder_text' => _TITLE, 'is_required' => true]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _CATEGORY,
            'field_html' => getTplCategorySelect($conf['name'], $cid, 'catid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false])),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => $conf['name'], 'rows' => '5', 'placeholder' => _TEXT, 'required' => '1'])]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _ENDTEXT, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => $conf['name'], 'rows' => '15', 'placeholder' => _ENDTEXT, 'required' => '0'])]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name'     => $conf['name'],
            'fields'   => $fields,
            'captcha'  => getCaptcha('comment'),
            'submit'   => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['pages']['add'] == 1) || (!is_user() && $conf['pages']['addquest'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'pages')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_pages (id, cid, uid, name, title, time, intro, body, ip, status)'
                .' VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :intro, :body, :ip, \'0\')',
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'intro' => $hometext, 'body' => $bodytext, 'ip' => getIp()]
            );
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['pages']['addmail'], $conf['name'], $puname, _PAGES);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD, 'htitle' => _PAGES]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBTEXT, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: pages(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
