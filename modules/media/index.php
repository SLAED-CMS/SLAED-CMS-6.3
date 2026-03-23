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
    global $db, $afile, $user, $conf, $home, $op, $tpl;
    $cwhere = catmids($conf['name'], 'm.cid');
    $unum = getUserNews($conf['media']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['media']['rate']) {
        $caton = 0;
        $field = 'op='.$op.'&';
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
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
            $orderby = ($op) ? (($op == 'best') ? 'IFNULL((m.tvotes/NULLIF(m.votes,0)),0) DESC' : 'IFNULL((m.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(m.time)),0)),0) DESC') : 'm.time DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
        $order = "WHERE (m.cid = :ncat1 OR c.parent = :ncat2) AND m.time <= NOW() AND m.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $params = ['ncat1' => $ncat, 'ncat2' => $ncat];
        $cids = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parent = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $cids[] = $caid;
        unset($result);
        if (isArray($cids)) {
            $caton = 1;
            array_unshift($cids, $ncat);
            $wcid = 'cid IN ('.implode(', ', $cids).')';
        } else {
            $caton = 0;
            $wcid = "cid = '".$ncat."'";
        }
        $onum = $wcid." AND time <= NOW() AND status != '0'";
    } else {
        $caton = 1;
        $field = '';
        $hwhere = ($home) ? "AND m.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE m.time <= NOW() AND m.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY m.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _MEDIA;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['media']['homcat'])) {
        $cont = setModuleNavi(['title' => $ntitle, 'htitle' => _MEDIA]);
        if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['media']['defis'], _MEDIA)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.intro, m.links, m.time, m.acomm, m.votes, m.tvotes, m.comments, m.hits, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while([$id, $cid, $uname, $title, $subtitle, $description, $links, $time, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $cdesc = $cdesc ?: $ctitle;
            $chref = 'index.php?name='.$conf['name'].'&amp;cat='.$cid;
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $mtitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $thref = 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id;
            $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['media']['date']) ? format_time($time) : '';
            $links = (url_types($links)) ? setTemplateBasic('hit-badge', ['{%title%}' => _MDOWN.': '.url_types($links), '{%text%}' => url_types($links), '{%cls%}' => 'sl_down']) : '';
            $rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$mtitle.'&quot;?');
            $cont .= setTemplateBasic('basic', ['{%id%}' => $id, '{%title_href%}' => $thref, '{%title_attr%}' => $mtitle, '{%title_text%}' => $mtitle, '{%title_new%}' => new_graphic($time), '{%category_href%}' => $ctitle ? $chref : '', '{%category_attr%}' => $cdesc, '{%category_text%}' => ($ctitle) ? cutstr($ctitle, 15) : '', '{%category_img%}' => $cimg, '{%text%}' => cutstr(filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']), 800), '{%read_href%}' => $thref, '{%read_text%}' => _READMORE, '{%post_text%}' => $post, '{%post_label%}' => _POSTEDBY, '{%date_text%}' => $date, '{%date_iso%}' => ($date) ? date('c', strtotime($time)) : '', '{%date_label%}' => _CHNGSTORY, '{%reads_text%}' => ($conf['media']['read']) ? $hits : '', '{%reads_label%}' => _READS, '{%hits%}' => $links, '{%comm_href%}' => ($acomm) ? $thref.'#comm' : '', '{%comm_text%}' => ($acomm) ? $comm : '', '{%comm_label%}' => _COMMENTS, '{%rating%}' => $rating, '{%favorites%}' => '', '{%voting%}' => '', '{%editor%}' => _EDITOR, '{%edit_href%}' => $afile.'.php?op=media_add&amp;id='.$id, '{%edit_text%}' => _FULLEDIT, '{%delete_href%}' => $afile.'.php?op=media_delete&amp;id='.$id.'&amp;refer=1', '{%delete_text%}' => _ONDELETE, '{%delete_ask%}' => $ask, '{%back_title%}' => '', '{%back_text%}' => '', 'if_flag' => ['is_moder' => is_moder($conf['name'])]]);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_media', 'cid', $onum, $conf['media']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $tpl;
    $cwhere = catmids($conf['name'], 'm.cid');
    $listnum = intval($conf['media']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $field = 'op=liste&let='.urlencode($let).'&';
        $order = "WHERE UCASE(m.title) LIKE BINARY :let AND m.time <= NOW() AND m.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $field = 'op=liste&';
        $order = "WHERE m.time <= NOW() AND m.status != '0'";
    }
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num-1) * $listnum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.time, c.title, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid=u.id) '.$order.' '.$cwhere.' ORDER BY time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = setModuleNavi(['title' => _LIST, 'htitle' => _MEDIA]);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['media']['letter']) ? letter($conf['name']) : '';
        $cont .= setTemplateBasic('liste-wrap', ['if_flag' => ['open' => true], '{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE]);
        while([$id, $cid, $uname, $title, $subtitle, $time, $ctitle, $nick] = $db->getSqlRow($result)) {
            $stitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $chref = 'index.php?name='.$conf['name'].'&amp;cat='.$cid;
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title_href%}' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id, '{%title_attr%}' => $stitle, '{%title_text%}' => cutstr($stitle, 40), '{%title_new%}' => new_graphic($time), '{%category_href%}' => $ctitle ? $chref : '', '{%category_attr%}' => $ctitle, '{%category_text%}' => ($ctitle) ? cutstr($ctitle, 15) : _NO, '{%post_text%}' => $post, '{%time_text%}' => format_time($time), '{%time_iso%}' => date('c', strtotime($time)), '{%time_label%}' => _DATE]);
        }
        $cont .= setTemplateBasic('liste-wrap', []);
        $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $params = ($let) ? ['let' => $let.'%'] : [];
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_media', 'cid', $onum, $conf['media']['nump'], $params);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'text');
    $cwhere = catmids($conf['name'], 'm.cid');
    $result = $db->getSqlQuery('SELECT m.cid, m.name, m.title, m.subtitle, m.year, m.director, m.roles, m.intro, m.author, m.duration, m.lang, m.note, m.format, m.quality, m.size, m.released, m.links, m.time, m.acomm, m.votes, m.tvotes, m.hits, m.status, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.id = :id AND m.time <= NOW() AND m.status != \'0\' '.$cwhere, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $subtitle, $year, $director, $roles, $description, $createdby, $duration, $lang, $note, $format, $quality, $size, $released, $links, $date, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
        $ptitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
        $seotitle = $ptitle;
        $seoctitle = $ctitle;
        $seodesc = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']))), 160);
        $seoimg = getImgText($description, '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        $seotime = $date;
        $seoauthor = $nick ?: ($uname ?: $conf['sitename']);
        setHead([
            'title' => $seotitle,
            'ctitle' => $seoctitle,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $seotime,
            'author' => $seoauthor,
        ]);
        $cont = setModuleNavi(['title' => _MEDIA]);
        if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $conf['media']['defis'], _MEDIA)]);
        if ($conf['media']['viewcat']) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], 0);
        $cdesc = ($cdesc) ? $cdesc : $ctitle;
        $ctitle = ($ctitle) ? setTemplateBasic('category-link', ['{%href%}' => 'index.php?name='.$conf['name'].'&amp;cat='.$cid, '{%title%}' => $cdesc, '{%text%}' => cutstr($ctitle, 15)]) : '';
        $cimg = ($cimg) ? setTemplateBasic('category-image', ['{%href%}' => 'index.php?name='.$conf['name'].'&amp;cat='.$cid, '{%title%}' => $cdesc, '{%src%}' => img_find('categories/'.$cimg)]) : '';
        $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $post = ($post) ? setTemplateBasic('media-post-badge', ['{%title%}' => _POSTEDBY, '{%text%}' => $post]) : '';
        $date = ($conf['media']['date']) ? setTemplateBasic('date-badge', ['{%iso%}' => date('c', strtotime($date)), '{%title%}' => _CHNGSTORY, '{%text%}' => format_time($date)]) : '';
        $reads = ($conf['media']['read']) ? setTemplateBasic('reads-badge', ['{%title%}' => _READS, '{%text%}' => $hits]) : '';
        $rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
        $admin = (is_moder($conf['name'])) ? setTemplateBasic('admin-menu', ['{%editor_text%}' => _EDITOR, '{%edit_href%}' => $afile.'.php?op=media_add&amp;id='.$id, '{%edit_text%}' => _FULLEDIT, '{%delete_href%}' => $afile.'.php?op=media_delete&amp;id='.$id, '{%delete_ask%}' => _DELETE.' &quot;'.$ptitle.'&quot;?', '{%delete_text%}' => _ONDELETE]) : '';
        $favorites = getFavorBtn($id, $conf['name']);
        $goback = setTemplateBasic('back-button', ['{%title%}' => _BACK, '{%label%}' => _BACK]);
        $broc = ($conf['media']['broc'] == 1 && $status != '2') ? setTemplateBasic('media-action-link', ['{%href%}' => 'index.php?name='.$conf['name'].'&amp;op=broken&amp;id='.$id, '{%title%}' => _BROCMEDIA, '{%label%}' => _COMPLAINT, '{%class%}' => 'sl_but_blue']) : '';
        
        $year = ($year) ? _MYEAR.': '.$year : '';
        $director = ($director) ? _MDIRECTOR.': '.$director : '';
        $roles = ($roles) ? _MROLES.': '.$roles : '';
        $createdby = ($createdby) ? _MCREATEDBY.': '.$createdby : '';
        $duration = ($duration) ? _MDURATION.': '.$duration : '';
        $lang = ($lang) ? _LANGUAGE.': '.$lang : '';
        $format = ($format) ? _MFORMAT.': '.$format : '';
        $quality = ($quality) ? _MQUALITY.': '.$quality : '';
        $size = ($size) ? _MSIZE.': '.$size : '';
        $released = ($released) ? _MRELEASED.': '.$released : '';
        $note = ($note) ? filterReplaceText(filterMarkdown($note, $conf['name'], false), $conf['name']) : '';
        if ($links) {
            if ((is_user() && $conf['media']['hide'] == '0') || $conf['media']['hide'] == '1') {
                $links = explode(',', $links);
                $e = 1;
                $i = 0;
                $mlinks = '';
                foreach($links as $val) {
                    if ($val != '') {
                        if (substr($val, 0, 4) == 'ed2k') {
                            $esize = explode('|', $val);
                            $size = ($esize[3]) ? _SIZE.': '.filterSize($esize[3]) : '';
                            $elink = setTemplateBasic('media-link-item', ['{%href%}' => $val, '{%title%}' => _URL.' '.$e.' - '.$size, '{%class%}' => 'sl_ed2k', '{%label%}' => _URL.' '.$e.' - '.$size]);
                            $mlinks .= (!$i) ? $elink : setTemplateBasic('media-link-break').$elink;
                            $e++;
                        } else {
                            $hlink = setTemplateBasic('media-link-item', ['{%href%}' => $val, '{%title%}' => _URL.': '.url_types($val), '{%class%}' => 'sl_http', '{%label%}' => _URL.': '.url_types($val)]);
                            $mlinks .= (!$i) ? $hlink : setTemplateBasic('media-link-break').$hlink;
                        }
                        $i++;
                    }
                }
            } else {
                $mlinks = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HIDETEXT]);
            }
        }
        $cont .= setTemplateBasic('basic-media-view', ['{%id%}' => $id, '{%favorites%}' => $favorites, '{%title%}' => filterTextHighlight($ptitle, $word), '{%hits%}' => '', '{%reads%}' => $reads, '{%post%}' => $post, '{%date%}' => $date, '{%ctitle%}' => $ctitle, '{%cimg%}' => $cimg, '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']), $word), '{%year%}' => $year, '{%director%}' => $director, '{%roles%}' => $roles, '{%createdby%}' => $createdby, '{%duration%}' => $duration, '{%lang%}' => $lang, '{%format%}' => $format, '{%quality%}' => $quality, '{%size%}' => $size, '{%released%}' => $released, '{%note%}' => $note, '{%links_label%}' => ($mlinks ?? '') ? _MURLS : '', '{%mlinks%}' => $mlinks ?? '', '{%rating%}' => $rating, '{%goback%}' => $goback, '{%admin%}' => $admin, '{%download%}' => '', '{%broken%}' => $broc]);
        if ($conf['media']['link']) {
            $limit = intval($conf['media']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_media WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT id, title, subtitle, intro, time FROM '.PREFIX_DB.'_media WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= setTemplateBasic('assoc-wrap', ['if_flag' => ['open' => true], '{%title%}' => _CATASSOC]);
                while([$aid, $title, $subtitle, $hometext, $time] = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $adate = ($conf['media']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $atext = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
                    if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
                        $img = 'uploads/'.$conf['name'].'/thumb/'.trim($match[1]);
                    } else {
                        preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
                        $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
                    }
                    $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
                    $cont .= setTemplateBasic('assoc-basic', ['{%href%}' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$aid, '{%title_attr%}' => $title, '{%title_text%}' => $title, '{%date_text%}' => $adate, '{%date_iso%}' => ($conf['media']['date']) ? date('c', strtotime($time)) : '', '{%date_label%}' => _CHNGSTORY, '{%text%}' => $atext, '{%img_src%}' => $img]);
                }
                $cont .= setTemplateBasic('assoc-wrap', []);
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
    global $db, $user, $conf, $stop, $tpl;
    if ((is_user() && $conf['media']['add'] == 1) || (!is_user() && $conf['media']['addquest'] == 1)) {
        $date = getdate();
        $title = getVar('post', 'title', 'text');
        $subtitle = getVar('post', 'subtitle', 'text');
        $mtitle = isset($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
        $cid = getVar('post', 'cid', 'num');
        $year = getVar('post', 'year', 'num', $date['year']);
        $director = getVar('post', 'director', 'text');
        $roles = getVar('post', 'roles', 'text');
        $description = getVar('post', 'description', 'text');
        $createdby = getVar('post', 'createdby', 'text');
        $duration = getVar('post', 'duration', 'text');
        $lang = getVar('post', 'lang', 'text');
        $note = getVar('post', 'note', 'text');
        $format = getVar('post', 'format', 'text');
        $quality = getVar('post', 'quality', 'text');
        $size = getVar('post', 'size', 'text');
        $released = getVar('post', 'released', 'text');
        $links = getVar('post', 'links', 'array');
        if (!$links || !is_array($links)) $links = [];
        $postname = getVar('post', 'postname', 'name');
        
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD, 'htitle' => _MEDIA]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        if ($description) $cont .= preview($mtitle, $description, $note, '', $conf['name']);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADDNOTEM]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $yearOptions = '';
        $linksRows = '';
        $y = $date['year'] - 100;
        while($y <= ($date['year'] + 1)) {
            $yearOptions .= setTemplateBasic('media-select-option', ['if_flag' => ['is_selected' => $y == $year], '{%value%}' => $y, '{%label%}' => $y]);
            $y++;
        }
        $langOptions = '';
        foreach (explode(',', (string)($conf['media']['lang'] ?? '')) as $val) {
            $langOptions .= setTemplateBasic('media-select-option', ['if_flag' => ['is_selected' => $val === $lang && $val !== ''], '{%value%}' => $val, '{%label%}' => $val]);
        }
        $formatOptions = '';
        foreach (explode(',', (string)($conf['media']['format'] ?? '')) as $val) {
            $formatOptions .= setTemplateBasic('media-select-option', ['if_flag' => ['is_selected' => $val === $format && $val !== ''], '{%value%}' => $val, '{%label%}' => $val]);
        }
        $qualityOptions = '';
        foreach (explode(',', (string)($conf['media']['quality'] ?? '')) as $val) {
            $qualityOptions .= setTemplateBasic('media-select-option', ['if_flag' => ['is_selected' => $val === $quality && $val !== ''], '{%value%}' => $val, '{%label%}' => $val]);
        }
        $i = 0;
        while($i < $conf['media']['links']) {
            $a = $i + 1;
            $link = isset($links[$i]) ? $links[$i] : '';
            $linksRows .= setTemplateBasic('media-link-row', ['if_flag' => ['is_hidden' => $i != 0 && $link == ''], '{%id%}' => 'med'.$i, '{%next_id%}' => 'med'.$a, '{%title%}' => _ADD, '{%label%}' => _URL.' - '.$a.':', '{%value%}' => filterText($link), '{%style%}' => $conf['style']]);
            $i++;
        }
        $cont .= setTemplateBasic('media-form-add', [
            'if_flag' => ['has_name' => true, 'is_user' => is_user()],
            '{%name%}' => $conf['name'],
            '{%token%}' => htmlspecialchars(getSiteToken('media'), ENT_QUOTES, 'UTF-8'),
            '{%style%}' => $conf['style'],
            '{%lbl_name%}' => _YOURNAME,
            '{%lbl_title%}' => _MTITLE,
            '{%lbl_subtitle%}' => _MSUBTITLE,
            '{%lbl_cat%}' => _CATEGORY,
            '{%lbl_year%}' => _MYEAR,
            '{%lbl_director%}' => _MDIRECTOR,
            '{%lbl_roles%}' => _MROLES,
            '{%lbl_description%}' => _DESCRIPTION,
            '{%lbl_createdby%}' => _MCREATEDBY,
            '{%lbl_duration%}' => _MDURATION,
            '{%lbl_lang%}' => _LANGUAGE,
            '{%lbl_note%}' => _NOTE,
            '{%lbl_format%}' => _MFORMAT,
            '{%lbl_quality%}' => _MQUALITY,
            '{%lbl_size%}' => _MSIZE,
            '{%lbl_released%}' => _MRELEASED,
            '{%username%}' => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            '{%postname%}' => $postname,
            '{%title%}' => $title,
            '{%subtitle%}' => $subtitle,
            '{%catselect%}' => getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>'),
            '{%year_options%}' => $yearOptions,
            '{%director%}' => $director,
            '{%roles%}' => $roles,
            '{%description%}' => textarea('1', 'description', $description, $conf['name'], '10', _DESCRIPTION, '1'),
            '{%createdby%}' => $createdby,
            '{%duration%}' => $duration,
            '{%lang_options%}' => $langOptions,
            '{%note%}' => textarea('2', 'note', $note, $conf['name'], '5', _NOTE, '0'),
            '{%no_info%}' => _NO_INFO,
            '{%format_options%}' => $formatOptions,
            '{%quality_options%}' => $qualityOptions,
            '{%size%}' => $size,
            '{%released%}' => $released,
            '{%links_rows%}' => $linksRows,
            '{%captcha%}' => getCaptcha(1),
            '{%submit%}' => ad_save('', '', 'send'),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $user, $conf, $stop, $tpl;
    if ((is_user() && $conf['media']['add'] == 1) || (!is_user() && $conf['media']['addquest'] == 1)) {
        $postname = getVar('post', 'postname', 'name');
        $cid = getVar('post', 'cid', 'num');
        $title = getVar('post', 'title', 'text');
        $subtitle = getVar('post', 'subtitle', 'text');
        $year = getVar('post', 'year', 'num');
        $director = getVar('post', 'director', 'text');
        $roles = getVar('post', 'roles', 'text');
        $description = getVar('post', 'description', 'text');
        $createdby = getVar('post', 'createdby', 'text');
        $duration = getVar('post', 'duration', 'text');
        $lang = getVar('post', 'lang', 'text');
        $note = getVar('post', 'note', 'text');
        $format = getVar('post', 'format', 'text');
        $quality = getVar('post', 'quality', 'text');
        $size = getVar('post', 'size', 'text');
        $released = getVar('post', 'released', 'text');
        $links = getVar('post', 'links', 'array');
        $links = ($links) ? filterText(implode(',', str_replace(',', '.', $links))) : '';
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'media')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title, subtitle FROM '.PREFIX_DB.'_media WHERE title = :title AND subtitle = :subtitle', ['title' => $title, 'subtitle' => $subtitle])) > 0) $stop[] = _MEDIAEXIST;
        if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_media (id, cid, uid, name, title, subtitle, year, director, roles, intro, author, duration, lang, note, format, quality, size, released, links, time, ip, status) VALUES (NULL, :cid, :postid, :uname, :title, :subtitle, :year, :director, :roles, :intro, :createdby, :duration, :lang, :note, :format, :quality, :size, :released, :links, NOW(), :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'subtitle' => $subtitle, 'year' => $year, 'director' => $director, 'roles' => $roles, 'intro' => $description, 'createdby' => $createdby, 'duration' => $duration, 'lang' => $lang, 'note' => $note, 'format' => $format, 'quality' => $quality, 'size' => $size, 'released' => $released, 'links' => $links, 'ip' => getIp()]);
            update_points(25);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['media']['addmail'], $conf['name'], $puname, _MEDIA);
            setHead(['title' => _MEDIA.' '._ADD, 'desc' => _UPLOADFINISHM]);
            $meta = '<meta http-equiv="refresh" content="10; url=index.php?name='.$conf['name'].'">';
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
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET status = \'2\' WHERE id = :id AND status != \'0\'', ['id' => $id]);
        setHead(['title' => _BROCMEDIA]);
        $meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'">';
        echo setModuleNavi(['title' => _BROCMEDIA, 'htitle' => _MEDIA]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTEM, 'meta' => $meta]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: media(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
}
