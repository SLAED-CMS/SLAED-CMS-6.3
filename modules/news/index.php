<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function news(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 's.cid');
    $unum = getUserNews($conf['news']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['news']['rate']) {
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
            $orderby = 's.fix DESC, s.time DESC';
        }
        $qres = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]);
        [$ctitle] = $db->getSqlRow($qres);
        $ctitle = $ctitle ?? _NEWS;
        $ntitle = ($op)
            ? (($op == 'best')
                ? $ctitle.' '.$conf['defis'].' '._BEST
                : $ctitle.' '.$conf['defis'].' '._POP)
            : $ctitle;
        $order = 'WHERE (s.cid = :ncat1 OR s.assoc REGEXP :ncat_re OR c.parent = :ncat2)'
            ." AND s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $params = ['ncat1' => $ncat, 'ncat_re' => '[[:<:]]'.$ncat.'[[:>:]]', 'ncat2' => $ncat];
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
        $onum = '('.$wcid." OR assoc REGEXP '[[:<:]]".(int)$ncat."[[:>:]]') AND time <= NOW() AND status != '0'";
    } else {
        $caton = 1;
        $hwhere = ($home) ? "AND s.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE s.time <= NOW() AND s.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY s.fix DESC, s.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _NEWS;
    }
    setHead(['title' => $ntitle, 'kind' => 'collection']);
    $cont = '';
    if (!$home || ($home && $conf['news']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle, 'htitle' => _NEWS]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['news']['defis'], _NEWS)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['news']['subcat'], $conf['news']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT s.id, s.cid, s.name, s.title, s.time, s.intro, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.intro, c.img, c.ordern, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $columns = max(1, min(6, (int)$conf['news']['bascol']));
        $ismoder = is_moder($conf['name']);
        $token   = $ismoder ? getSiteToken() : getPageToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $stitle, $time, $hometext, $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl([
                'name' => $conf['name'],
                'op' => 'view',
                'id' => $id,
                'title' => $stitle,
                'ctitle' => $ctitle,
            ]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg = getCategoryIcon($cimg);
            $post = ($conf['news']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['news']['date']) ? format_time($time) : '';
            $iso = ($conf['news']['date']) ? date('c', strtotime($time)) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $ratings, $score, '');
            $edit = $afile.'.php?name=news&op=add&id='.$id;
            $del = $afile.'.php?name=news&op=actions&typ=d&id='.$id.'&refer=2&token='.$token;
            $ptext = $prs->filterContent($hometext, false, $conf['name'], 2);
            $cont .= $tpl->getHtmlFrag('card', [
                'id' => $id,
                'is_nested' => false,
                'columns' => $columns,
                'title_href' => $thref,
                'title_attr' => $stitle,
                'title_text' => $stitle,
                'title_link' => ['href' => $thref, 'title' => $stitle, 'label_html' => $stitle],
                'title_new' => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_link' => $ctitle ? ['href' => $chref, 'title' => $cdesc, 'label' => cutstr($ctitle, 15), 'is_card_category' => true] : [],
                'category_icon' => $cimg,
                'category_tone' => $cordern % 6,
                'text' => $ptext,
                'read_href' => $thref,
                'read_text' => _READMORE,
                'read_link' => ['href' => $thref, 'title' => $stitle, 'label' => _READMORE, 'is_card_read' => true],
                'post_text' => $post,
                'post_label' => _POSTEDBY,
                'post_span' => $post ? ['title' => _POSTEDBY, 'content_html' => $post, 'is_card_post' => true] : [],
                'date_text' => $date,
                'date_iso' => $iso,
                'date_label' => _CHNGSTORY,
                'reads_text' => ($conf['news']['read']) ? $counter : '',
                'reads_label' => _READS,
                'reads_span' => ($conf['news']['read']) ? ['title' => _READS, 'text' => $counter, 'is_card_reads' => true] : [],
                'hits' => '',
                'comm_href' => ($acomm) ? $thref.'#comm' : '',
                'comm_text' => ($acomm) ? $comm : '',
                'comm_label' => _COMMENTS,
                'comm_link' => ($acomm) ? ['href' => $thref.'#comm', 'title' => _COMMENTS, 'label' => $comm, 'is_card_comment' => true] : [],
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
            'limit'      => $unum,
            'maxpg'      => $conf['news']['nump'],
            'table'      => '_news',
            'field'      => 'id',
            'mod'        => $conf['name'],
            'where'      => $onum,
            'url_extra'  => $url_extra,
            'prefix'     => 'new/',
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
    $listnum = (int)($conf['news']['listnum']);
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
        .' FROM '.PREFIX_DB.'_news AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY s.fix DESC, s.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _LIST, 'htitle' => _NEWS]);
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
            $edit = $afile.'.php?name=news&op=add&id='.$id;
            $del = $afile.'.php?name=news&op=actions&typ=d&id='.$id.'&refer=2&token='.$token;
            $row += getTplEditMenu($edit, $del, $title);
        }
        $rows[] = $row;
    }
    $onum = ($let) ? "UCASE(title) LIKE BINARY UCASE(:let) AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
    $wparams = ($let) ? ['let' => $let.'%'] : [];
    if (!$rows) setError(404);
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows'        => $rows,
        'before_html' => ($conf['news']['letter'] && $rows) ? letter($conf['name']) : '',
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
            'limit'         => $listnum,
            'maxpg'         => $conf['news']['nump'],
            'table'         => '_news',
            'field'         => 'id',
            'mod'           => $conf['name'],
            'where'         => $onum,
            'where_params'  => $wparams,
            'url_extra'     => $let ? ['op' => 'liste', 'let' => $let] : ['op' => 'liste'],
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
    $sql = 'SELECT s.cid, s.name, s.title, s.time, s.intro, s.body, s.field, s.vote,'
        .' s.counter, s.acomm, s.score, s.ratings, s.assoc,'
        .' c.title, c.intro, c.img, c.ordern, u.name'
        .' FROM '.PREFIX_DB.'_news AS s'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id)'
        ." WHERE s.id = :id AND s.time <= NOW() AND s.status != '0' ".$cwhere;
    $result = $db->getSqlQuery($sql, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        addDeferredTask(static function() use ($id): void {
            global $db;
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        });
        [$cid, $uname, $title, $time, $hometext, $bodytext, $field, $vote,
            $counter, $acomm, $score, $ratings, $assoc, $ctitle, $cdesc, $cimg, $cordern, $nick
        ] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(
            trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))),
            160
        );
        $seoimg = getImgText($hometext, '', false);
        setHead([
            'title'  => $title,
            'kind'   => 'news',
            'ctitle' => $ctitle,
            'cid' => $cid,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = getModuleNavi(['title' => _NEWS, 'is_heading' => false]);
        if ($cid) $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['news']['defis'], _NEWS)]);
        $catlist = $conf['news']['viewcat'] ? setCategories($conf['name'], $conf['news']['subcat'], $conf['news']['catdesc'], 0) : '';
        $fields = getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]);
        $rawtext = $bodytext ? (empty($conf['news']['intro']) ? $bodytext : $hometext.$bodytext) : $hometext;
        if ($fields) $rawtext .= $fields;
        $conpag = explode('[pagebreak]', $rawtext);
        $pageno = count($conpag);
        if ($pag > $pageno) $pag = $pageno;
        $pagei = (int)$pag - 1;
        $cdesc = $cdesc ?: $ctitle;
        $cimg = getCategoryIcon($cimg);
        $post = ($conf['news']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['news']['date']) ? format_time($time) : '';
        $iso = ($conf['news']['date']) ? date('c', strtotime($time)) : '';
        $rating = getRatingAsync(1, $id, $conf['name'], $ratings, $score, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $voting = ($vote) ? $tpl->getHtmlFrag('block-content', ['id' => 'rep'.$conf['name'], 'is_section' => true, 'content' => getVotingView($vote, $conf['name']), 'has_hr' => true]) : '';
        $ismoder = is_moder($conf['name']);
        $edit = $afile.'.php?name=news&op=add&id='.$id;
        $del = $afile.'.php?name=news&op=actions&typ=d&id='.$id.'&token='.getSiteToken();
        $cont .= $tpl->getHtmlPart('view', [
            'is_moder' => $ismoder,
            'id' => $id,
            'title_text' => filterTextHighlight($title, $word),
            'title_new' => getTplNewGraphic($time),
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_icon' => $cimg,
            'category_tone' => $cordern % 6,
            'text' => filterTextHighlight(
                $prs->filterContent($conpag[$pagei], false, $conf['name'], 1),
                $word
            ),
            'post_text' => $post,
            'post_label' => _POSTEDBY,
            'date_text' => $date,
            'date_iso' => $iso,
            'date_label' => _CHNGSTORY,
            'reads_text' => ($conf['news']['read']) ? $counter : '',
            'reads_label' => _READS,
            'hits' => '',
            'rating' => $rating,
            'favorites' => $favorites,
            'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'share_title' => $title,
            'voting' => $voting,
            ...($ismoder ? getTplEditMenu($edit, $del, $title) : []),
            'back_title' => _BACK,
            'back_text' => _BACK,
        ]);
        $cont .= $catlist;
        $cont .= getPageNumbers(
            $conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['news']['nump'], (int)$pag, '#'.$id
        );
        if ($conf['news']['assoc']) {
            $aids = array_values(array_filter(
                array_map('intval', explode(',', (string)$assoc)),
                static fn(int $val): bool => $val > 0
            ));
            $assin = implode(', ', $aids);
            $limit = (int)($conf['news']['asocnum']);
            if ($assin !== '') {
                $csql = 'SELECT COUNT(id) FROM '.PREFIX_DB.'_news'
                    .' WHERE cid IN ('.$assin.') AND id != :id AND time <= NOW() AND status != \'0\'';
                [$count] = $db->getSqlRow($db->getSqlQuery($csql, ['id' => $id]));
            } else {
                $count = 0;
            }
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $asql = 'SELECT id, title, time, intro, body FROM '.PREFIX_DB.'_news'
                    .' WHERE cid IN ('.$assin.') AND id != :id'
                    ." AND time <= NOW() AND status != '0' ORDER BY time DESC LIMIT ".$random.', '.$limit;
                $result = $db->getSqlQuery($asql, ['id' => $id]);
                $cont .= $tpl->getHtmlPart('related', ['open' => true, 'title' => _ASSTORY]);
                while ([$aid, $title, $time, $hometext, $bodytext] = $db->getSqlRow($result)) {
                    $date = ($conf['news']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(
                        trim(strip_tags(
                            $prs->filterContent($hometext, false, $conf['name'])
                        )),
                        ENT_QUOTES, 'UTF-8'
                    ), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : getThemeImagePath('logos/slaed_logo_60x60.png');
                    $href = getSeoUrl([
                        'name' => $conf['name'], 'op' => 'view',
                        'id' => $aid, 'title' => $title,
                    ]);
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href' => $href,
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text' => $date,
                        'date_iso' => ($conf['news']['date']) ? date('c', strtotime($time)) : '',
                        'date_label' => _CHNGSTORY,
                        'text' => $text,
                        'img_src' => $img,
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
    if ((is_user() && $conf['news']['add'] == 1) || (!is_user() && $conf['news']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $fieldp = getVar('post', 'field[]', 'raw', []);
        $field = is_array($fieldp) ? implode('|', array_map('strval', $fieldp)) : '';
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'htitle' => _NEWS]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($hometext) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $hometext, 'textb' => $bodytext, 'field' => $field, 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBMIT.' '._PAGENOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('news')]);
        $nameField = is_user()
            ? $tpl->getHtmlFrag('span', ['is_form_value' => true, 'text' => filterText(substr($user[1], 0, 25))])
            : $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'postname', 'input_id' => 'f-postname', 'value_attr' => $postname, 'placeholder_text' => _YOURNAME, 'is_required' => true]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label_for' => is_user() ? '' : 'f-postname', 'label' => _YOURNAME, 'field_html' => $nameField]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-title',
            'label' => _TITLE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title', 'value_attr' => $title, 'maxlength_num' => 100, 'placeholder_text' => _TITLE, 'is_required' => true]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label_for' => 'f-catid',
            'label' => _CATEGORY,
            'field_html' => getTplCategorySelect($conf['name'], $cid, 'catid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false])),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _TEXT,
            'label_id' => $labid = getFieldIds('', 'hometext')['label'],
            'field_html' => getTplTextarea(['labelledby' => $labid, 'label' => _TEXT,
                'id' => '1',
                'name' => 'hometext',
                'value' => $hometext,
                'mod' => $conf['name'],
                'store' => 'news.intro',
                'rows' => '5',
                'placeholder' => _TEXT,
                'required' => '1',
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _ENDTEXT,
            'label_id' => $labid = getFieldIds('', 'bodytext')['label'],
            'field_html' => getTplTextarea(['labelledby' => $labid, 'label' => _ENDTEXT,
                'id' => '2',
                'name' => 'bodytext',
                'value' => $bodytext,
                'mod' => $conf['name'],
                'store' => 'news.body',
                'rows' => '15',
                'placeholder' => _ENDTEXT,
                'required' => '0',
            ]),
        ]);
        $fields .= getTplFieldsIn(['field' => $field, 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name' => $conf['name'],
            'fields' => $fields,
            'captcha' => getPageCaptcha('comment'),
            'submit' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['news']['add'] == 1) || (!is_user() && $conf['news']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $fieldp = getVar('post', 'field[]', 'raw', []);
        $field = is_array($fieldp) ? filterFields($fieldp) : getVar('post', 'field', 'field');
        $postname = getVar('post', 'postname', 'name');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'news')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$cid) $stop[] = _ERROR_ALL;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if ($room = checkEditorTextRoom($hometext, 'news.intro')) $stop[] = $room;
        if ($room = checkEditorTextRoom($bodytext, 'news.body')) $stop[] = $room;
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_news'
                .' (id, cid, uid, name, title, time, intro, body, field, assoc, ip, status)'
                ." VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :intro, :body, :field, '', :ip, '0')",
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname,
                    'title' => $title, 'intro' => $hometext, 'body' => $bodytext,
                    'field' => $field, 'ip' => getIp()]
            );
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['news']['addmail'], $conf['name'], $puname, _NEWS);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD, 'htitle' => _NEWS]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBTEXT, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: news(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
