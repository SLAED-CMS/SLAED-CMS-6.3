<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function media(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 'm.cid');
    $unum = getUserNews($conf['media']['num']);
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['media']['rate']) {
        $caton = 0;
        if ($op == 'best') {
            $orderby = 'IFNULL((m.tvotes/NULLIF(m.votes,0)),0) DESC';
            $ntitle = _BEST;
        } else {
            $orderby = 'IFNULL((m.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(m.time)),0)),0) DESC';
            $ntitle = _POP;
        }
        $order = "WHERE m.time <= NOW() AND m.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $onum = "time <= NOW() AND status != '0'";
    } elseif ($ncat) {
        if ($op == 'best') {
            $orderby = 'IFNULL((m.tvotes/NULLIF(m.votes,0)),0) DESC';
        } elseif ($op) {
            $orderby = 'IFNULL((m.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(m.time)),0)),0) DESC';
        } else {
            $orderby = 'm.time DESC';
        }
        $qres = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]);
        [$ctitle] = $db->getSqlRow($qres);
        $ctitle = $ctitle ?? _MEDIA;
        $ntitle = ($op)
            ? (($op == 'best')
                ? $ctitle.' '.$conf['defis'].' '._BEST
                : $ctitle.' '.$conf['defis'].' '._POP)
            : $ctitle;
        $order = "WHERE (m.cid = :ncat1 OR c.parent = :ncat2) AND m.time <= NOW() AND m.status != '0' ".$cwhere.' ORDER BY '.$orderby;
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
        $hwhere = ($home) ? "AND m.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE m.time <= NOW() AND m.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY m.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _MEDIA;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['media']['homcat'])) {
        $cont .= setModuleNavi(['title' => $ntitle, 'htitle' => _MEDIA]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['media']['defis'], _MEDIA)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.intro, m.links, m.time, m.acomm, m.votes, m.tvotes, m.comments, m.hits, c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_media AS m'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id)'
        .' '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $uname, $title, $subtitle, $description, $links, $time, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $cdesc = $cdesc ?: $ctitle;
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $mtitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $mtitle, 'ctitle' => $ctitle]);
            $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['media']['date']) ? format_time($time) : '';
            $iso  = ($conf['media']['date']) ? date('c', strtotime($time)) : '';
            $links = (url_types($links)) ? $tpl->getHtmlFrag('hit-badge', ['title' => _MDOWN.': '.url_types($links), 'text' => url_types($links), 'cls' => 'sl_down']) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$mtitle.'&quot;?');
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'width'         => 100,
                'title_href'    => $thref,
                'title_attr'    => $mtitle,
                'title_text'    => $mtitle,
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img'  => $cimg,
                'text'          => cutstr($prs->filterContent($description, false, $conf['name']), 800),
                'read_href'     => $thref,
                'read_text'     => _READMORE,
                'post_text'     => $post,
                'post_label'    => _POSTEDBY,
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => ($conf['media']['read']) ? $hits : '',
                'reads_label'   => _READS,
                'hits'          => $links,
                'comm_href'     => ($acomm) ? $thref.'#comm' : '',
                'comm_text'     => ($acomm) ? $comm : '',
                'comm_label'    => _COMMENTS,
                'rating'        => $rating,
                'favorites'     => '',
                'voting'        => '',
                'editor'        => _EDITOR,
                'edit_href'     => $afile.'.php?name=media&amp;op=media_add&amp;id='.$id,
                'edit_text'     => _FULLEDIT,
                'delete_href'   => $afile.'.php?name=media&amp;op=media_delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token,
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
            'maxpg'     => $conf['media']['nump'],
            'table'     => '_media',
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
    $cwhere = catmids($conf['name'], 'm.cid');
    $listnum = (int)($conf['media']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $order = "WHERE UCASE(m.title) LIKE BINARY :let AND m.time <= NOW() AND m.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $order = "WHERE m.time <= NOW() AND m.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $listnum);
    $sql = 'SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.time, c.title, u.name'
        .' FROM '.PREFIX_DB.'_media AS m'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id)'
        .' '.$order.' '.$cwhere.' ORDER BY m.time DESC LIMIT '.$offset.', '.$listnum;
    $result = $db->getSqlQuery($sql, $params);
    setHead(['title' => _LIST]);
    $cont = setModuleNavi(['title' => _LIST, 'htitle' => _MEDIA]);
    if ($db->getSqlRowCount($result) > 0) {
        if ($conf['media']['letter']) $cont .= letter($conf['name']);
        $cont .= $tpl->getHtmlFrag('table', [
            'open'       => true,
            'sortable'   => false,
            'col_id'     => _ID,
            'col_title'  => _TITLE,
            'col_cat'    => _CATEGORY,
            'col_poster' => _POSTER,
            'col_date'   => _DATE,
        ]);
        while ([$id, $cid, $uname, $title, $subtitle, $time, $ctitle, $nick] = $db->getSqlRow($result)) {
            $stitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= $tpl->getHtmlFrag('table-row-liste', [
                'id'            => (string)$id,
                'title_href'    => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]),
                'title_attr'    => $stitle,
                'title_text'    => cutstr($stitle, 40),
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $ctitle,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
                'post_text'     => $post,
                'time_text'     => format_time($time),
                'time_iso'      => date('c', strtotime($time)),
                'time_label'    => _DATE,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('table', []);
        $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $wparams = ($let) ? ['let' => $let.'%'] : [];
        $cont .= getTplPager([
            'limit'        => $listnum,
            'maxpg'        => $conf['media']['nump'],
            'table'        => '_media',
            'field'        => 'id',
            'mod'          => $conf['name'],
            'where'        => $onum,
            'where_params' => $wparams,
            'url_extra'    => $let ? ['op' => 'liste', 'let' => $let] : ['op' => 'liste'],
            'prefix'       => 'new/',
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl, $prs;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 'm.cid');
    $sql = 'SELECT m.cid, m.name, m.title, m.subtitle, m.year, m.director, m.roles, m.intro, m.author,'
        .' m.duration, m.lang, m.note, m.format, m.quality, m.size, m.released, m.links,'
        .' m.time, m.acomm, m.votes, m.tvotes, m.hits, m.status,'
        .' c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_media AS m'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id)'
        ." WHERE m.id = :id AND m.time <= NOW() AND m.status != '0' ".$cwhere;
    $result = $db->getSqlQuery($sql, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $subtitle, $year, $director, $roles, $description, $createdby,
            $duration, $lang, $note, $format, $quality, $size, $released, $links,
            $time, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $nick
        ] = $db->getSqlRow($result);
        $ptitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($description, false, $conf['name']))), 160);
        $seoimg = getImgText($description, '', false);
        setHead([
            'title'  => $ptitle,
            'ctitle' => $ctitle,
            'desc'   => $seodesc,
            'img'    => $seoimg ? $conf['homeurl'].'/'.$seoimg : '',
            'time'   => $time,
            'author' => $nick ?: ($uname ?: $conf['sitename']),
        ]);
        $cont = setModuleNavi(['title' => _MEDIA]);
        if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => getTplCategoryTrail($conf['name'], $cid, $conf['media']['defis'], _MEDIA)]);
        if ($conf['media']['viewcat']) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], 0);
        $cdesc = $cdesc ?: $ctitle;
        $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
        $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['media']['date']) ? format_time($time) : '';
        $iso  = ($conf['media']['date']) ? date('c', strtotime($time)) : '';
        $rating    = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$ptitle.'&quot;?');
        $broc = ($conf['media']['broc'] == 1 && $status != '2') ? $tpl->getHtmlFrag('action-link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'broken', 'id' => $id]), 'title' => _BROCMEDIA, 'label' => _COMPLAINT, 'class' => 'sl-but-blue']) : '';
        $year      = ($year)      ? _MYEAR.': '.$year           : '';
        $director  = ($director)  ? _MDIRECTOR.': '.$director   : '';
        $roles     = ($roles)     ? _MROLES.': '.$roles          : '';
        $createdby = ($createdby) ? _MCREATEDBY.': '.$createdby : '';
        $duration  = ($duration)  ? _MDURATION.': '.$duration   : '';
        $lang      = ($lang)      ? _LANGUAGE.': '.$lang         : '';
        $format    = ($format)    ? _MFORMAT.': '.$format        : '';
        $quality   = ($quality)   ? _MQUALITY.': '.$quality      : '';
        $size      = ($size)      ? _MSIZE.': '.$size            : '';
        $released  = ($released)  ? _MRELEASED.': '.$released   : '';
        $note      = ($note)      ? $prs->filterContent($note, false, $conf['name']) : '';
        $mlinks = '';
        if ($links) {
            if ((is_user() && $conf['media']['hide'] == '0') || $conf['media']['hide'] == '1') {
                $items = explode(',', $links);
                $e = 1;
                $i = 0;
                foreach ($items as $val) {
                    if ($val != '') {
                        if (substr($val, 0, 4) == 'ed2k') {
                            $esize = explode('|', $val);
                            $einfo = ($esize[3]) ? _SIZE.': '.filterSize($esize[3]) : '';
                            $elink = $tpl->getHtmlFrag('media-link-item', ['href' => $val, 'title' => _URL.' '.$e.' - '.$einfo, 'class' => 'sl_ed2k', 'label' => _URL.' '.$e.' - '.$einfo]);
                            $mlinks .= (!$i) ? $elink : $tpl->getHtmlFrag('media-link-break').$elink;
                            $e++;
                        } else {
                            $hlink = $tpl->getHtmlFrag('media-link-item', ['href' => $val, 'title' => _URL.': '.url_types($val), 'class' => 'sl_http', 'label' => _URL.': '.url_types($val)]);
                            $mlinks .= (!$i) ? $hlink : $tpl->getHtmlFrag('media-link-break').$hlink;
                        }
                        $i++;
                    }
                }
            } else {
                $mlinks = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HIDETEXT]);
            }
        }
        $cont .= $tpl->getHtmlFrag('view', [
            'is_moder'      => is_moder($conf['name']),
            'id'            => $id,
            'favorites'     => $favorites,
            'title_text'    => filterTextHighlight($ptitle, $word),
            'hits'          => '',
            'reads_text'    => ($conf['media']['read']) ? $hits : '',
            'reads_label'   => _READS,
            'post_text'     => $post,
            'post_label'    => _POSTEDBY,
            'date_text'     => $date,
            'date_iso'      => $iso,
            'date_label'    => _CHNGSTORY,
            'category_href' => $ctitle ? $chref : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
            'category_img'  => $cimg,
            'text'          => filterTextHighlight($prs->filterContent($description, false, $conf['name']), $word),
            'year'          => $year,
            'director'      => $director,
            'roles'         => $roles,
            'createdby'     => $createdby,
            'duration'      => $duration,
            'lang'          => $lang,
            'format'        => $format,
            'quality'       => $quality,
            'size'          => $size,
            'released'      => $released,
            'note'          => $note,
            'links_label'   => $mlinks ? _MURLS : '',
            'mlinks'        => $mlinks,
            'rating'        => $rating,
            'broken'        => $broc,
            'back_title'    => _BACK,
            'back_text'     => _BACK,
            'editor'        => _EDITOR,
            'edit_href'     => $afile.'.php?name=media&amp;op=media_add&amp;id='.$id,
            'edit_text'     => _FULLEDIT,
            'delete_href'   => $afile.'.php?name=media&amp;op=media_delete&amp;id='.$id.'&amp;token='.getSiteToken(),
            'delete_text'   => _ONDELETE,
            'delete_ask'    => $ask,
        ]);
        if ($conf['media']['link']) {
            $limit = (int)($conf['media']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery(
                'SELECT COUNT(id) FROM '.PREFIX_DB."_media WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0'",
                ['cid' => $cid, 'id' => $id]
            ));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery(
                    'SELECT id, title, subtitle, intro, time FROM '.PREFIX_DB.'_media'
                    ." WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0' ORDER BY time DESC LIMIT ".$random.', '.$limit,
                    ['cid' => $cid, 'id' => $id]
                );
                $cont .= $tpl->getHtmlFrag('related', ['open' => true, 'title' => _CATASSOC]);
                while ([$aid, $title, $subtitle, $hometext, $time] = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $adate = ($conf['media']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $atext = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), ENT_QUOTES, 'UTF-8'), 80);
                    if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
                        $img = 'uploads/'.$conf['name'].'/thumb/'.trim($match[1]);
                    } else {
                        preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
                        $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
                    }
                    $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
                    $cont .= $tpl->getHtmlFrag('related-item', [
                        'href'       => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]),
                        'title_attr' => $title,
                        'title_text' => $title,
                        'date_text'  => $adate,
                        'date_iso'   => ($conf['media']['date']) ? date('c', strtotime($time)) : '',
                        'date_label' => _CHNGSTORY,
                        'text'       => $atext,
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
    global $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['media']['add'] == 1) || (!is_user() && $conf['media']['addquest'] == 1)) {
        $date = getdate();
        $title       = getVar('post', 'title', 'title');
        $subtitle    = getVar('post', 'subtitle', 'title');
        $mtitle      = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
        $cid         = getVar('post', 'cid', 'num');
        $year        = getVar('post', 'year', 'num', $date['year']);
        $director    = getVar('post', 'director', 'text');
        $roles       = getVar('post', 'roles', 'text');
        $description = getVar('post', 'description', 'raw');
        $createdby   = getVar('post', 'createdby', 'text');
        $duration    = getVar('post', 'duration', 'text');
        $lang        = getVar('post', 'lang', 'text');
        $note        = getVar('post', 'note', 'raw');
        $format      = getVar('post', 'format', 'text');
        $quality     = getVar('post', 'quality', 'text');
        $size        = getVar('post', 'size', 'text');
        $released    = getVar('post', 'released', 'text');
        $links       = getVar('post', 'links', 'array');
        if (!$links || !is_array($links)) $links = [];
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD, 'htitle' => _MEDIA]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => getStopText((array)$stop)]);
        if ($description) $cont .= getTplPreviewContent(['title' => $mtitle, 'texta' => $description, 'textb' => $note, 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADDNOTEM]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $yearOptions = '';
        $linksRows = '';
        $y = $date['year'] - 100;
        while ($y <= ($date['year'] + 1)) {
            $yearOptions .= getTplSelectOption((string)$y, (string)$y, (bool)($y == $year));
            $y++;
        }
        $langOptions = '';
        foreach (explode(',', (string)($conf['media']['lang'] ?? '')) as $val) {
            $langOptions .= getTplSelectOption((string)$val, (string)$val, (bool)($val === $lang && $val !== ''));
        }
        $formatOptions = '';
        foreach (explode(',', (string)($conf['media']['format'] ?? '')) as $val) {
            $formatOptions .= getTplSelectOption((string)$val, (string)$val, (bool)($val === $format && $val !== ''));
        }
        $qualityOptions = '';
        foreach (explode(',', (string)($conf['media']['quality'] ?? '')) as $val) {
            $qualityOptions .= getTplSelectOption((string)$val, (string)$val, (bool)($val === $quality && $val !== ''));
        }
        $i = 0;
        while ($i < $conf['media']['links']) {
            $a = $i + 1;
            $link = isset($links[$i]) ? $links[$i] : '';
            $linksRows .= $tpl->getHtmlFrag('media-link-row', ['is_hidden' => $i != 0 && $link == '', 'id' => 'med'.$i, 'next_id' => 'med'.$a, 'title' => _ADD, 'label' => _URL.' - '.$a.':', 'value' => filterText($link)]);
            $i++;
        }
        $cont .= $tpl->getHtmlFrag('media-form-add', [
            'has_name'       => true,
            'is_user'        => is_user(),
            'name'           => $conf['name'],
            'token'          => htmlspecialchars(getSiteToken('media'), ENT_QUOTES, 'UTF-8'),
            'lbl_name'       => _YOURNAME,
            'lbl_title'      => _MTITLE,
            'lbl_subtitle'   => _MSUBTITLE,
            'lbl_cat'        => _CATEGORY,
            'lbl_year'       => _MYEAR,
            'lbl_director'   => _MDIRECTOR,
            'lbl_roles'      => _MROLES,
            'lbl_description'=> _DESCRIPTION,
            'lbl_createdby'  => _MCREATEDBY,
            'lbl_duration'   => _MDURATION,
            'lbl_lang'       => _LANGUAGE,
            'lbl_note'       => _NOTE,
            'lbl_format'     => _MFORMAT,
            'lbl_quality'    => _MQUALITY,
            'lbl_size'       => _MSIZE,
            'lbl_released'   => _MRELEASED,
            'username'       => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname'       => $postname,
            'title'          => $title,
            'subtitle'       => $subtitle,
            'catselect'      => getTplCategorySelect($conf['name'], $cid, 'cid', '', getTplSelectOption('', _HOMECAT)),
            'year_options'   => $yearOptions,
            'director'       => $director,
            'roles'          => $roles,
            'description'    => getTplTextarea(['id' => '1', 'name' => 'description', 'value' => $description, 'mod' => $conf['name'], 'rows' => '10', 'placeholder' => _DESCRIPTION, 'required' => '1']),
            'createdby'      => $createdby,
            'duration'       => $duration,
            'lang_options'   => $langOptions,
            'note'           => getTplTextarea(['id' => '2', 'name' => 'note', 'value' => $note, 'mod' => $conf['name'], 'rows' => '5', 'placeholder' => _NOTE, 'required' => '0']),
            'no_info'        => _NO_INFO,
            'format_options' => $formatOptions,
            'quality_options'=> $qualityOptions,
            'size'           => $size,
            'released'       => $released,
            'links_rows'     => $linksRows,
            'captcha'        => getCaptcha(1),
            'submit'         => getTplFormSubmit(['op' => 'send', 'select' => true]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ((is_user() && $conf['media']['add'] == 1) || (!is_user() && $conf['media']['addquest'] == 1)) {
        $postname    = getVar('post', 'postname', 'name');
        $cid         = getVar('post', 'cid', 'num');
        $title       = getVar('post', 'title', 'text');
        $subtitle    = getVar('post', 'subtitle', 'text');
        $year        = getVar('post', 'year', 'num');
        $director    = getVar('post', 'director', 'text');
        $roles       = getVar('post', 'roles', 'text');
        $description = getVar('post', 'description', 'text');
        $createdby   = getVar('post', 'createdby', 'text');
        $duration    = getVar('post', 'duration', 'text');
        $lang        = getVar('post', 'lang', 'text');
        $note        = getVar('post', 'note', 'text');
        $format      = getVar('post', 'format', 'text');
        $quality     = getVar('post', 'quality', 'text');
        $size        = getVar('post', 'size', 'text');
        $released    = getVar('post', 'released', 'text');
        $links       = getVar('post', 'links', 'array');
        $links = ($links) ? filterText(implode(',', str_replace(',', '.', $links))) : '';
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'media')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title, subtitle FROM '.PREFIX_DB.'_media WHERE title = :title AND subtitle = :subtitle', ['title' => $title, 'subtitle' => $subtitle])) > 0) $stop[] = _MEDIAEXIST;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_media'
                .' (id, cid, uid, name, title, subtitle, year, director, roles, intro, author,'
                .' duration, lang, note, format, quality, size, released, links, time, ip, status)'
                ." VALUES (NULL, :cid, :postid, :uname, :title, :subtitle, :year, :director, :roles,"
                ." :intro, :createdby, :duration, :lang, :note, :format, :quality, :size, :released, :links, NOW(), :ip, '0')",
                ['cid' => $cid, 'postid' => $postid, 'uname' => $uname,
                    'title' => $title, 'subtitle' => $subtitle, 'year' => $year,
                    'director' => $director, 'roles' => $roles, 'intro' => $description,
                    'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang,
                    'note' => $note, 'format' => $format, 'quality' => $quality,
                    'size' => $size, 'released' => $released, 'links' => $links, 'ip' => getIp()]
            );
            update_points(25);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['media']['addmail'], $conf['name'], $puname, _MEDIA);
            setHead(['title' => _MEDIA.' '._ADD, 'desc' => _UPLOADFINISHM]);
            $meta = getTplMetaRefresh('index.php?name='.$conf['name']);
            echo setModuleNavi(['title' => _ADD, 'htitle' => _MEDIA]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISHM, 'meta' => $meta]);
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
    if ($conf['media']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB."_media SET status = '2' WHERE id = :id AND status != '0'", ['id' => $id]);
        setHead(['title' => _BROCMEDIA]);
        $meta = getTplMetaRefresh('index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 5);
        echo setModuleNavi(['title' => _BROCMEDIA, 'htitle' => _MEDIA]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTEM, 'meta' => $meta]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: media(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
}
