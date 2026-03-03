<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
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
    $home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._NEWS.'" class="sl_but_navi">'._HOME.'</a>';
    $best = ($conf['news']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'best']).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
    $pop = ($conf['news']['rate']) ? '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
    $liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
    $add = ((is_user() && $conf['news']['add'] == 1) || (!is_user() && $conf['news']['addquest'] == 1)) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
    $catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
    return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $best, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function news(): void {
    global $db, $afile, $conf, $home, $op;
    $cwhere = catmids($conf['name'], 's.catid');
    $unum = getUserNews($conf['news']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['news']['rate']) {
        $caton = 0;
        $field = 'op='.$op.'&';
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
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
        $orderby = ($op) ? (($op == 'best') ? 'IFNULL((s.score/NULLIF(s.ratings,0)),0) DESC' : 'IFNULL((s.counter/NULLIF((TO_DAYS(NOW()) - TO_DAYS(s.time)),0)),0) DESC') : 's.fix DESC, s.time DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
        $order = "WHERE (s.catid = :ncat1 OR s.associated REGEXP :ncat_re OR c.parentid = :ncat2) AND s.time <= NOW() AND s.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $params = ['ncat1' => $ncat, 'ncat_re' => '[[:<:]]'.$ncat.'[[:>:]]', 'ncat2' => $ncat];
        $catid = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parentid = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $catid[] = $caid;
        unset($result);
        if (isArray($catid)) {
            $caton = 1;
            array_unshift($catid, $ncat);
            $wcid = 'catid IN ('.implode(', ', array_map('intval', $catid)).')';
        } else {
            $caton = 0;
            $wcid = 'catid = '.(int)$ncat;
        }
        $onum = '('.$wcid." OR associated REGEXP '[[:<:]]".(int)$ncat."[[:>:]]') AND time <= NOW() AND status != '0'";
    } else {
        $caton = 1;
        $field = '';
        $hwhere = ($home) ? "AND s.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE s.time <= NOW() AND s.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY s.fix DESC, s.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _NEWS;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['news']['homcat'])) {
        $cont .= navigate($ntitle, $caton);
        if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['news']['defis'], _NEWS)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['news']['subcat'], $conf['news']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT s.sid, s.catid, s.name, s.title, s.time, s.hometext, s.bodytext, s.comments, s.counter, s.acomm, s.score, s.ratings, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $width = 100 / $conf['news']['bascol'];
        $i = 1;
        $cont .= '<table>';
        while([$id, $cid, $uname, $stitle, $time, $hometext, $bodytext, $comm, $counter, $acomm, $score, $ratings, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            
            $thref = getSeoUrl([
    'name' => $conf['name'],
    'op' => 'view',
    'id' => $id,
    'title' => $stitle,
    'ctitle' => $ctitle
]);

            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
            $title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
            $read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
            $post = ($conf['news']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
            $date = ($conf['news']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
            $reads = ($conf['news']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
            $comm = ($acomm) ? '<a href="'.$thref.'#comm" title="'._COMMENTS.'" class="sl_coms">'.$comm.'</a>' : '';
            $rating = ajax_rating(0, $id, $conf['name'], $ratings, $score, '');
            $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=news_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=news_admin&amp;typ=d&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
            if (($i - 1) % $conf['news']['bascol'] == 0) $cont .= '<tr>';
            $cont .= '<td style="width: '.$width.'%;">';
            $cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => bb_decode($hometext, $conf['name']), '{%read%}' => $read, '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
            $cont .= '</td>';
            if ($i % $conf['news']['bascol'] == 0) $cont .= '</tr>';
            $i++;
        }
        $cont .= '</table>';
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'sid', '_news', 'catid', $onum, $conf['news']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf;
    $cwhere = catmids($conf['name'], 's.catid');
    $listnum = intval($conf['news']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $field = 'op=liste&let='.urlencode($let).'&';
        $order = "WHERE UCASE(s.title) LIKE BINARY :let AND s.time <= NOW() AND s.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $field = 'op=liste&';
        $order = "WHERE s.time <= NOW() AND s.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $listnum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT s.sid, s.catid, s.name, s.title, s.time, c.title, c.description, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) '.$order.' '.$cwhere.' ORDER BY s.fix DESC, s.time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = navigate(_LIST);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['news']['letter']) ? letter($conf['name']) : '';
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
        $onum = ($let) ? "title LIKE BINARY '".addslashes($let)."%' AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'sid', '_news', 'catid', $onum, $conf['news']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf;
    $id = getVar('get', 'id', 'num');
    $num = getVar('get', 'num', 'num', '1');
    $pag = $num;
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 's.catid');
    $result = $db->getSqlQuery('SELECT s.catid, s.name, s.title, s.time, s.hometext, s.bodytext, s.field, s.vote, s.counter, s.acomm, s.score, s.ratings, s.associated, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.user_id) WHERE s.sid = :id AND s.time <= NOW() AND s.status != \'0\' '.$cwhere, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET counter = counter+1 WHERE sid = :id', ['id' => $id]);
        [$cid, $uname, $title, $time, $hometext, $bodytext, $field, $vote, $counter, $acomm, $score, $ratings, $associated, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
        $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
        $seotitle  = $title;
        $seoctitle = $ctitle;
        $seodesc   = cutstr(trim(strip_tags(bb_decode($hometext, $conf['name']))), 160);
        $seoimg    = getImgText($hometext, '', false);
        $seoimg    = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        $seotime   = $time;
        $seoauthor = $nick ?: ($uname ?: $conf['sitename']);
        setHead([
            'title' => $seotitle,
            'ctitle' => $seoctitle,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $seotime,
            'author' => $seoauthor,
        ]);
        $cont = navigate(_NEWS, $conf['news']['viewcat']);
        if ($cid) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $cid, $conf['news']['defis'], _NEWS)]);
        if ($conf['news']['viewcat']) $cont .= setCategories($conf['name'], $conf['news']['subcat'], $conf['news']['catdesc'], 0);
        $fields = fields_out($field, $conf['name']);
        $fields = ($fields) ? '<br><br>'.$fields : '';
        $text = (!$bodytext) ? $hometext.$fields : $hometext.'<br><br>'.$bodytext.$fields;
        $conpag = explode('[pagebreak]', $text);
        $pageno = count($conpag);
        if ($pag > $pageno) $pag = $pageno;
        $arrayelement = (int)$pag;
        $arrayelement--;
        $cdesc = ($cdesc) ? $cdesc : $ctitle;
        $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
        $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
        $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
        $post = ($conf['news']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $post = ($post) ? '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>' : '';
        $date = ($conf['news']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
        $reads = ($conf['news']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
        $rating = ajax_rating(1, $id, $conf['name'], $ratings, $score, '');
        $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=news_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=news_admin&amp;typ=d&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
        $favorites = favorview($id, $conf['name']);
        $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
        $voting = ($vote) ? '<div id="rep'.$conf['name'].'">'.getVoting($vote, $conf['name']).'</div><hr>' : '';
        $cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => true], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => search_color($title, $word), '{%text%}' => search_color(bb_decode($conpag[$arrayelement], $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => $admin, '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => $voting]);
        $cont .= setPageNumbers('pagenum', $conf['name'], 1, $pageno, 1, 'op=view&id='.$id.'&', $conf['news']['nump'], (int)$pag, '#'.$id);
        if ($conf['news']['assoc']) {
            $assocIds = array_values(array_filter(array_map('intval', explode(',', (string)$associated)), static fn(int $val): bool => $val > 0));
            $assocIn = implode(', ', $assocIds);
            $limit = intval($conf['news']['asocnum']);
            if ($assocIn !== '') {
                [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(sid) FROM '.PREFIX_DB.'_news WHERE catid IN ('.$assocIn.') AND sid != :id AND time <= NOW() AND status != \'0\'', ['id' => $id]));
            } else {
                $count = 0;
            }
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT sid, title, time, hometext, bodytext FROM '.PREFIX_DB.'_news WHERE catid IN ('.$assocIn.') AND sid != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['id' => $id]);
                $cont .= setTemplateBasic('assoc-open', ['{%title%}' => _ASSTORY]);
                while ([$aid, $title, $time, $hometext, $bodytext] = $db->getSqlRow($result)) {
                    $date = ($conf['news']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'._CHNGSTORY.': '.format_time($time).'</time>' : '';
                    $text = cutstr(htmlspecialchars(trim(strip_tags(bb_decode($hometext, $conf['name']))), ENT_QUOTES), 80);
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
    global $conf, $user, $stop;
    if ((is_user() && $conf['news']['add'] == 1) || (!is_user() && $conf['news']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $catid = getVar('post', 'catid', 'num');
        $cid = $catid;
        $hometext = getVar('post', 'hometext', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $field = getVar('post', 'field', 'field');
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = navigate(_ADD);
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        if ($hometext) $cont .= preview($title, $hometext, $bodytext, $field, $conf['name']);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SUBMIT.' '._PAGENOTE]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
        if (is_user()) {
            $cont .= '<tr><td>'._YOURNAME.':</td><td>'.filterText(substr($user[1], 0, 25)).'</td></tr>';
        } else {
            $postname = ($postname) ? $postname : _ANONYM;
            $cont .= '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'.$postname.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>';
        }
        $cont .= '<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._TITLE.'" required></td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'catid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
        .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '5', _TEXT, '1').'</td></tr>'
        .'<tr><td>'._ENDTEXT.':</td><td>'.textarea('2', 'bodytext', $bodytext, $conf['name'], '15', _ENDTEXT, '0').'</td></tr>'
        .fields_in($field, $conf['name'])
        .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop;
    if ((is_user() && $conf['news']['add'] == 1) || (!is_user() && $conf['news']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $catid = getVar('post', 'catid', 'num');
        $cid = $catid;
        $hometext = getVar('post', 'hometext', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $field = getVar('post', 'field', 'field');
        $postname = getVar('post', 'postname', 'name');
        $stop = [];
        if (!$title) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_news (sid, catid, uid, name, title, time, hometext, bodytext, field, associated, ip_sender, status) VALUES (NULL, :cid, :postid, :uname, :title, NOW(), :hometext, :bodytext, :field, \'\', :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'hometext' => $hometext, 'bodytext' => $bodytext, 'field' => $field, 'ip' => getIp()]);
            update_points(31);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['news']['addmail'], $conf['name'], $puname, _NEWS);
            setHead(['title' => _ADD]);
            echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _SUBTEXT]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: news(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
