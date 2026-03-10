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
    $home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._LINKS.'" class="sl_but_navi">'._HOME.'</a>';
    $best = ($conf['links']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
    $pop = ($conf['links']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
    $liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
    $add = ((is_user() && $conf['links']['add'] == 1) || (!is_user() && $conf['links']['addquest'] == 1)) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
    $catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
    return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function links(): void {
    global $db, $afile, $user, $conf, $home, $op;
    $cwhere = catmids($conf['name'], 'f.cid');
    $unum = getUserNews($conf['links']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['links']['rate']) {
        $caton = 0;
        $field = 'op='.$op.'&';
            if ($op == 'best') {
                $orderby = 'IFNULL((f.totalvotes/NULLIF(f.votes,0)),0) DESC';
                $ntitle = _BEST;
            } else {
                $orderby = 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.date)),0)),0) DESC';
                $ntitle = _POP;
            }
        $order = "WHERE f.date <= NOW() AND f.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $onum = "date <= NOW() AND status != '0'";
    } elseif ($ncat) {
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
            $orderby = ($op) ? (($op == 'best') ? 'IFNULL((f.totalvotes/NULLIF(f.votes,0)),0) DESC' : 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.date)),0)),0) DESC') : 'f.date DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
        $order = "WHERE (f.cid = :ncat1 OR c.parentid = :ncat2) AND f.date <= NOW() AND f.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $params = ['ncat1' => $ncat, 'ncat2' => $ncat];
        $catid = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parentid = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $catid[] = $caid;
        unset($result);
        if (isArray($catid)) {
            $caton = 1;
            array_unshift($catid, $ncat);
            $wcid = 'cid IN ('.implode(', ', $catid).')';
        } else {
            $caton = 0;
            $wcid = "cid = '".$ncat."'";
        }
        $onum = $wcid." AND date <= NOW() AND status != '0'";
    } else {
        $caton = 1;
        $field = '';
        $hwhere = ($home) ? "AND f.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE f.date <= NOW() AND f.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY f.date DESC';
        $onum = "date <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _LINKS;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['links']['homcat'])) {
        $cont .= navigate($ntitle, $caton);
        if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['links']['defis'], _LINKS)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT f.lid, f.cid, f.name, f.title, f.description, f.bodytext, f.date, f.counter, f.acomm, f.votes, f.totalvotes, f.totalcomments, f.hits, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $cid, $uname, $stitle, $description, $bodytext, $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
            $title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
            $read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
            $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
            $date = ($conf['links']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
            $reads = ($conf['links']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
            $hits = ($conf['links']['hits']) ? '<span title="'._LINKHITS.'" class="sl_down">'.$hits.'</span>' : '';
            $comm = ($acomm) ? '<a href="'.$thref.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$comm.'</a>' : '';
            $rating = ajax_rating(0, $id, $conf['name'], $votes, $totalvotes, '');
            $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=links_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $hits, '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'lid', '_links', 'cid', $onum, $conf['links']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf;
    $cwhere = catmids($conf['name'], 'f.cid');
    $listnum = intval($conf['links']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $field = 'op=liste&let='.urlencode($let).'&';
        $order = "WHERE UCASE(f.title) LIKE BINARY :let AND f.date <= NOW() AND f.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $field = 'op=liste&';
        $order = "WHERE f.date <= NOW() AND f.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $listnum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT f.lid, f.cid, f.name, f.title, f.date, c.title, c.description, u.user_name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) '.$order.' '.$cwhere.' ORDER BY date DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = navigate(_LIST);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['links']['letter']) ? letter($conf['name']) : '';
        $cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _POSTER, '{%date%}' => _DATE]);
        while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => $post, '{%time%}' => format_time($time)]);
        }
        $cont .= setTemplateBasic('liste-close');
        $onum = ($let) ? "title LIKE BINARY '".$let."%' AND date <= NOW() AND status != '0'" : "date <= NOW() AND status != '0'";
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'lid', '_links', 'cid', $onum, $conf['links']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 'f.cid');
    $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.url, f.description, f.bodytext, f.date, f.email, f.counter, f.acomm, f.votes, f.totalvotes, f.hits, f.status, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) WHERE lid = :id AND date <= NOW() AND f.status != \'0\' '.$cwhere, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET counter = counter+1 WHERE lid = :id', ['id' => $id]);
        [$cid, $uname, $title, $authorurl, $description, $bodytext, $date, $aemail, $counter, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seotitle = $title;
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
        $cont = navigate(_LINKS, $conf['links']['viewcat']);
        if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $conf['links']['defis'], _LINKS)]);
        if ($conf['links']['viewcat']) $cont .= setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], 0);
        $text = ($bodytext) ? $description.'<br><br>'.$bodytext : $description;
        $cdesc = ($cdesc) ? $cdesc : $ctitle;
        $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
        $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
        $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
        $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
        $date = ($conf['links']['date']) ? '<time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</time>' : '';
        $reads = ($conf['links']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
        $hits = ($conf['links']['hits']) ? '<span title="'._LINKHITS.'" class="sl_down">'.$hits.'</span>' : '';
        $rating = ajax_rating(1, $id, $conf['name'], $votes, $totalvotes, '');
        $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=links_delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
        $favorites = getFavorBtn($id, $conf['name']);
        $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
        if (is_user() || $conf['links']['links'] == '1') {
            $download = '<form action="index.php?name='.$conf['name'].'" method="post" style="display: inline">'
            .'<input type="hidden" name="id" value="'.$id.'">'
            .'<input type="hidden" name="op" value="loading">'
            .'<input type="submit" OnClick="javascript:window.open(\''.$authorurl.'\')" value="'._DOWNLLINK.'" class="sl_but_green"></form>';
        }
        $broken = ($conf['links']['broc'] == 1 && $status != '2') ? '<a OnClick="javascript:window.location.assign(\'index.php?name='.$conf['name'].'&amp;op=broken&amp;id='.$id.'\');" title="'._BROCLINK.'" class="sl_but_blue">'._COMPLAINT.'</a>' : '';
        $email = ($aemail) ? _AUEMAIL.': '.anti_spam($aemail) : '';
        $home = ($authorurl) ? _SITE.': '.domain($authorurl) : '';
        $cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => true], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => filterTextHighlight($title, $word), '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $hits, '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => '', '{%size%}' => '', '{%version%}' => '', '{%download%}' => $download, '{%broken%}' => $broken, '{%email%}' => $email, '{%home%}' => $home]);
        if ($conf['links']['link']) {
            $limit = intval($conf['links']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(lid) FROM '.PREFIX_DB.'_links WHERE cid = :cid AND lid != :id AND date <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT lid, title, description, bodytext, date FROM '.PREFIX_DB.'_links WHERE cid = :cid AND lid != :id AND date <= NOW() AND status != \'0\' ORDER BY date DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= setTemplateBasic('assoc-open', ['{%title%}' => _CATASSOC]);
                while([$aid, $title, $hometext, $bodytext, $time] = $db->getSqlRow($result)) {
                    $date = ($conf['links']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
                    $text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $cont .= setTemplateBasic('assoc-basic', ['{%href%}' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), '{%title%}' => $title, '{%date%}' => $date, '{%text%}' => $text, '{%img%}' => $img]);
                }
                $cont .= setTemplateBasic('assoc-close');
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
    global $user, $conf, $stop;
    if ((is_user() && $conf['links']['add'] == 1) || (!is_user() && $conf['links']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        if (is_user()) {
            $userinfo = getUserInfo();
            $mail = getVar('post', 'mail', 'text', $userinfo['user_email']);
            $site = getVar('post', 'site', 'url', $userinfo['user_website']);
        } else {
            $mail = getVar('post', 'mail', 'text');
            $site = getVar('post', 'site', 'url', 'http://');
        }
        setHead(['title' => _ADD]);
        $cont = navigate(_ADD);
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        if ($description) $cont .= preview($title, $description, $bodytext, '', $conf['name']);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _ADDFNOTE]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
        if (is_user()) {
            $cont .= '<tr><td>'._YOURNAME.':</td><td>'.filterText(substr($user[1], 0, 25)).'</td></tr>';
        } else {
            $postname = ($postname) ? $postname : _ANONYM;
            $cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
        }
        $cont .= '<tr><td>'._AUEMAIL.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._AUEMAIL.'" required></td></tr>'
        .'<tr><td>'._SITENAME.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._SITENAME.'" required></td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
        .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'description', $description, $conf['name'], '5', _TEXT, '1').'</td></tr>'
        .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, $conf['name'], '15', _ENDTEXT, '0').'</td></tr>'
        .'<tr><td>'._URL.':</td><td><input type="url" name="site" value="'.$site.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._URL.'" required></td></tr>'
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
    if ((is_user() && $conf['links']['add'] == 1) || (!is_user() && $conf['links']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        $mail = getVar('post', 'mail', 'text');
        $site = getVar('post', 'site', 'url');
        $stop = [];
        if (!$title) $stop[] = _CERROR10;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (!$site) $stop[] = _CERROR4;
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_links WHERE url = :site', ['site' => $site])) > 0) $stop[] = _LINKEXIST;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_links (lid, cid, uid, name, title, description, bodytext, url, date, email, ip_sender, status) VALUES (NULL, :cid, :postid, :uname, :title, :description, :bodytext, :site, NOW(), :mail, :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'site' => $site, 'mail' => $mail, 'ip' => getIp()]);
            update_points(21);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['links']['addmail'], $conf['name'], $puname, _LINKS);
            setHead(['title' => _ADD]);
            echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _UPLOADFINISHL]);
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
    if ($conf['links']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET status = \'2\' WHERE lid = :id AND status != \'0\'', ['id' => $id]);
        setHead(['title' => _BROCLINK]);
        echo navigate(_BROCLINK).setTemplateWarning('warn', ['time' => '5', 'url' => '?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 'id' => 'info', 'text' => _BROCNOTEL]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function loading(): void {
    global $db, $conf;
    $id = getVar('post', 'id', 'num');
    if (($id && is_user()) || ($id && $conf['links']['links'] == '1')) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET hits = hits+1 WHERE lid = :id', ['id' => $id]);
        [$title, $url] = $db->getSqlRow($db->getSqlQuery('SELECT title, url FROM '.PREFIX_DB.'_links WHERE lid = :id', ['id' => $id]));
        update_points(23);
        $info = sprintf(_NOTELINKLOAD, $title, domain($url));
        setHead(['title' => _LINKS]);
        $cont = navigate(_LINKS);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
        $cont .= setNaviLower($conf['name']);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: links(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
    case 'loading': loading(); break;
}
