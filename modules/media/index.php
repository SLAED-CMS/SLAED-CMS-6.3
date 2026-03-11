<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function navigate(string $title, string|int $cat = ''): string {
    global $conf;
    $cat = getVar('get', 'cat', 'num');
    $cpar = $cat ? ['cat' => $cat] : [];
    $home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._MEDIA.'" class="sl_but_navi">'._HOME.'</a>';
    $best = ($conf['media']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
    $pop = ($conf['media']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
    $liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
    $add = ((is_user() && $conf['media']['add'] == 1) || (!is_user() && $conf['media']['addquest'] == 1)) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
    $catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
    return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function media(): void {
    global $db, $afile, $user, $conf, $home, $op;
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
        $cont = navigate($ntitle, $caton);
        if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['media']['defis'], _MEDIA)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT m.id, m.cid, m.name, m.title, m.subtitle, m.intro, m.links, m.time, m.acomm, m.votes, m.tvotes, m.comments, m.hits, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while([$id, $cid, $uname, $title, $subtitle, $description, $links, $time, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
            $cimg = ($cimg) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_icat"><img src="'.img_find('categories/'.$cimg).'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
            $mtitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $title = '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'" title="'.$mtitle.'">'.$mtitle.'</a> '.new_graphic($time);
            $read = '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'" title="'.$mtitle.'" class="sl_but_read">'._READMORE.'</a>';
            $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
            $date = ($conf['media']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
            $reads = ($conf['media']['read']) ? '<span title="'._READS.'" class="sl_views">'.$hits.'</span>' : '';
            $links = (url_types($links)) ? '<span title="'._MDOWN.': '.url_types($links).'" class="sl_down">'.url_types($links).'</span>' : '';
            $comm = ($acomm) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$comm.'</a>' : '';
            $rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
            $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=media_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=media_delete&amp;id='.$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$mtitle."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => cutstr(filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']), 800), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $links, '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_media', 'cid', $onum, $conf['media']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf;
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
    $cont = navigate(_LIST);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['media']['letter']) ? letter($conf['name']) : '';
        $cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE]);
        while([$id, $cid, $uname, $title, $subtitle, $time, $ctitle, $nick] = $db->getSqlRow($result)) {
            $stitle = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
            $title = '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'" title="'.$stitle.'">'.cutstr($stitle, 40).'</a> '.new_graphic($time);
            $ctitle = ($ctitle) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$ctitle.'">'.cutstr($ctitle, 15).'</a>' : _NO;
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $post, '{%time%}' => format_time($time)]);
        }
        $cont .= setTemplateBasic('liste-close', []);
        $onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_media', 'cid', $onum, $conf['media']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf;
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
        $cont = navigate(_MEDIA, $conf['media']['viewcat']);
        if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $conf['media']['defis'], _MEDIA)]);
        if ($conf['media']['viewcat']) $cont .= setCategories($conf['name'], $conf['media']['subcat'], $conf['media']['catdesc'], 0);
        $cdesc = ($cdesc) ? $cdesc : $ctitle;
        $ctitle = ($ctitle) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
        $cimg = ($cimg) ? '<a href="index.php?name='.$conf['name'].'&amp;cat='.$cid.'" title="'.$cdesc.'" class="sl_icat"><img src="'.img_find('categories/'.$cimg).'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
        $post = ($conf['media']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
        $date = ($conf['media']['date']) ? '<time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</time>' : '';
        $reads = ($conf['media']['read']) ? '<span title="'._READS.'" class="sl_views">'.$hits.'</span>' : '';
        $rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
        $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=media_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=media_delete&amp;id='.$id."\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$ptitle."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>') : '';
        $favorites = getFavorBtn($id, $conf['name']);
        $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
        $broc = ($conf['media']['broc'] == 1 && $status != '2') ? '<a OnClick="javascript:window.location.assign(\'index.php?name='.$conf['name'].'&amp;op=broken&amp;id='.$id.'\');" title="'._BROCMEDIA.'" class="sl_but_blue">'._COMPLAINT.'</a>' : '';
        
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
                            $elink = '<a href="'.$val.'" target="_blank" title="'._URL.' '.$e.' - '.$size.'" class="sl_ed2k">'._URL.' '.$e.' - '.$size.'</a>';
                            $mlinks .= (!$i) ? $elink : '<br>'.$elink;
                            $e++;
                        } else {
                            $hlink = '<a href="'.$val.'" target="_blank" title="'._URL.': '.url_types($val).'" class="sl_http">'._URL.': '.url_types($val).'</a>';
                            $mlinks .= (!$i) ? $hlink : '<br>'.$hlink;
                        }
                        $i++;
                    }
                }
            } else {
                $mlinks = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _HIDETEXT]);
            }
        }
        $cont .= setTemplateBasic('basic-media-view', ['{%id%}' => $id, '{%favorites%}' => $favorites, '{%title%}' => filterTextHighlight($ptitle, $word), '{%hits%}' => '', '{%reads%}' => $reads, '{%post%}' => $post, '{%date%}' => $date, '{%ctitle%}' => $ctitle, '{%cimg%}' => $cimg, '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']), $word), '{%year%}' => $year, '{%director%}' => $director, '{%roles%}' => $roles, '{%createdby%}' => $createdby, '{%duration%}' => $duration, '{%lang%}' => $lang, '{%format%}' => $format, '{%quality%}' => $quality, '{%size%}' => $size, '{%released%}' => $released, '{%note%}' => $note, '{%links_label%}' => ($mlinks ?? '') ? _MURLS : '', '{%mlinks%}' => $mlinks ?? '', '{%rating%}' => $rating, '{%goback%}' => $goback, '{%admin%}' => $admin, '{%download%}' => '', '{%broken%}' => $broc]);
        if ($conf['media']['link']) {
            $limit = intval($conf['media']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_media WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT id, title, subtitle, intro, time FROM '.PREFIX_DB.'_media WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= setTemplateBasic('assoc-open', ['{%title%}' => _CATASSOC]);
                while([$aid, $title, $subtitle, $hometext, $time] = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $adate = ($conf['media']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
                    $atext = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
                    if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
                        $img = 'uploads/'.$conf['name'].'/thumb/'.trim($match[1]);
                    } else {
                        preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
                        $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
                    }
                    $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
                    $cont .= setTemplateBasic('assoc-basic', ['{%href%}' => 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$aid, '{%title%}' => $title, '{%date%}' => $adate, '{%text%}' => $atext, '{%img%}' => $img]);
                }
                $cont .= setTemplateBasic('assoc-close', []);
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
    global $db, $user, $conf, $stop;
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
        $cont = navigate(_ADD);
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        if ($description) $cont .= preview($mtitle, $description, $note, '', $conf['name']);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _ADDNOTEM]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">';
        if (is_user()) {
            $cont .= '<tr><td>'._YOURNAME.':</td><td>'.filterText(substr($user[1], 0, 25)).'</td></tr>';
        } else {
            $postname = ($postname) ? $postname : _ANONYM;
            $cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
        }
        $cont .= '<tr><td>'._MTITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MTITLE.'" required></td></tr>'
        .'<tr><td>'._MSUBTITLE.':</td><td><input type="text" name="subtitle" value="'.$subtitle.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MSUBTITLE.'"></td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
        .'<tr><td>'._MYEAR.':</td><td><select name="year" class="sl_field '.$conf['style'].'">';
        $y = $date['year'] - 100;
        while($y <= ($date['year'] + 1)) {
            $sel = ($y == $year) ? ' selected' : '';
            $cont .= '<option value="'.$y.'"'.$sel.'>'.$y.'</option>';
            $y++;
        }
        $cont .= '</select></td></tr>'
        .'<tr><td>'._MDIRECTOR.':</td><td><input type="text" name="director" value="'.$director.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MDIRECTOR.'"></td></tr>'
        .'<tr><td>'._MROLES.':</td><td><input type="text" name="roles" value="'.$roles.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._MROLES.'"></td></tr>'
        .'<tr><td>'._DESCRIPTION.':</td><td>'.textarea('1', 'description', $description, $conf['name'], '10', _DESCRIPTION, '1').'</td></tr>'
        .'<tr><td>'._MCREATEDBY.':</td><td><input type="text" name="createdby" value="'.$createdby.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MCREATEDBY.'"></td></tr>'
        .'<tr><td>'._MDURATION.':</td><td><input type="text" name="duration" value="'.$duration.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MDURATION.'"></td></tr>'
        .'<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_field '.$conf['style'].'">';
        $lang = explode(',', $conf['media']['lang']);
        foreach($lang as $val) {
            $sel = ($val == $lang && $val != '') ? ' selected' : '';
            $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
        }
        $cont .= '</select></td></tr>'
        .'<tr><td>'._NOTE.':</td><td>'.textarea('2', 'note', $note, $conf['name'], '5', _NOTE, '0').'</td></tr>'
        .'<tr><td>'._MFORMAT.':</td><td><select name="format" class="sl_field '.$conf['style'].'">'
        .'<option value="">'._NO_INFO.'</option>';
        $format = explode(',', $conf['media']['format']);
        foreach($format as $val) {
            $sel = ($val == $format && $val != '') ? ' selected' : '';
            $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
        }
        $cont .= '</select></td></tr>'
        .'<tr><td>'._MQUALITY.':</td><td><select name="quality" class="sl_field '.$conf['style'].'">'
        .'<option value="">'._NO_INFO.'</option>';
        $quality = explode(',', $conf['media']['quality']);
        foreach($quality as $val) {
            $sel = ($val == $quality && $val != '') ? ' selected' : '';
            $cont .= '<option value="'.$val.'"'.$sel.'>'.$val.'</option>';
        }
        $cont .= '</select></td></tr>'
        .'<tr><td>'._MSIZE.':</td><td><input type="text" name="size" value="'.$size.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MSIZE.'"></td></tr>'
        .'<tr><td>'._MRELEASED.':</td><td><input type="text" name="released" value="'.$released.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._MRELEASED.'"></td></tr>'
        .'<tr><td colspan="2">';
        $i = 0;
        while($i < $conf['media']['links']) {
            $a = $i + 1;
            $link = isset($links[$i]) ? $links[$i] : '';
            $display = ($i != 0 && $link == '') ? ' sl_none' : '';
            $cont .= '<table id="med'.$i.'" class="sl_table_form'.$display."\"><tr><td><a OnClick=\"HideShow('med".$a."', 'slide', 'up', 500);\" title=\""._ADD.'" class="sl_plus">'._URL.' - '.$a.':</a></td><td><input type="text" name="links[]" value="'.filterText($link).'" class="sl_field '.$conf['style'].'"></td></tr></table>';
            $i++;
        }
        $cont .= '</td></tr>'
        .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $user, $conf, $stop;
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
            echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _UPLOADFINISHM]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function broken(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num');
    if ($conf['media']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET status = \'2\' WHERE id = :id AND status != \'0\'', ['id' => $id]);
        setHead(['title' => _BROCMEDIA]);
        echo navigate(_BROCMEDIA).setTemplateWarning('warn', ['time' => '5', 'url' => '?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 'id' => 'info', 'text' => _BROCNOTEM]);
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
