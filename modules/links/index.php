<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function links(): void {
    global $db, $afile, $user, $conf, $home, $op, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $unum = getUserNews($conf['links']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['links']['rate']) {
        $caton = 0;
        $field = 'op='.$op.'&';
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
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
            $orderby = ($op) ? (($op == 'best') ? 'IFNULL((f.tvotes/NULLIF(f.votes,0)),0) DESC' : 'IFNULL((f.hits/NULLIF((TO_DAYS(NOW()) - TO_DAYS(f.time)),0)),0) DESC') : 'f.time DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
        $order = "WHERE (f.cid = :ncat1 OR c.parent = :ncat2) AND f.time <= NOW() AND f.status != '0' ".$cwhere.' ORDER BY '.$orderby;
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
        $hwhere = ($home) ? "AND f.ihome = '1'" : '';
        $hnwhere = ($home) ? "AND ihome = '1'" : '';
        $order = "WHERE f.time <= NOW() AND f.status != '0' ".$hwhere.' '.$cwhere.' ORDER BY f.time DESC';
        $onum = "time <= NOW() AND status != '0' ".$hnwhere;
        $ntitle = _LINKS;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['links']['homcat'])) {
        $cont .= setModuleNavi(['title' => $ntitle, 'htitle' => _LINKS]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $ncat, $conf['links']['defis'], _LINKS)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.intro, f.body, f.time, f.counter, f.acomm, f.votes, f.tvotes, f.comments, f.hits, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $cid, $uname, $stitle, $description, $bodytext, $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['links']['date']) ? format_time($time) : '';
            $hits = ($conf['links']['hits']) ? $tpl->getHtmlFrag('hit-badge', ['title' => _LINKHITS, 'text' => $hits, 'cls' => 'sl_down']) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$stitle.'&quot;?');
            $cont .= getContentCard([
                'id' => $id,
                'title_href' => $thref,
                'title_attr' => $stitle,
                'title_text' => $stitle,
                'title_new' => new_graphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img' => $cimg,
                'text' => filterReplaceText(filterMarkdown($description, $conf['name'], false), $conf['name']),
                'read_href' => $thref,
                'read_text' => _READMORE,
                'post_text' => $post,
                'post_label' => _POSTEDBY,
                'date_text' => $date,
                'date_iso' => ($date) ? date('c', strtotime($time)) : '',
                'date_label' => _CHNGSTORY,
                'reads_text' => ($conf['links']['read']) ? $counter : '',
                'reads_label' => _READS,
                'hits' => $hits,
                'comm_href' => ($acomm) ? $thref.'#comm' : '',
                'comm_text' => ($acomm) ? $comm : '',
                'comm_label' => _COMMENTS,
                'rating' => $rating,
                'favorites' => '',
                'voting' => '',
                'editor' => _EDITOR,
                'edit_href' => $afile.'.php?op=links_add&amp;id='.$id,
                'edit_text' => _FULLEDIT,
                'delete_href' => $afile.'.php?op=links_delete&amp;id='.$id.'&amp;refer=1',
                'delete_text' => _ONDELETE,
                'delete_ask' => $ask,
                'back_title' => '',
                'back_text' => '',
                'is_moder' => is_moder($conf['name']),
            ]);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_links', 'cid', $onum, $conf['links']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $listnum = intval($conf['links']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = [];
    if ($let) {
        $field = 'op=liste&let='.urlencode($let).'&';
        $order = "WHERE UCASE(f.title) LIKE BINARY :let AND f.time <= NOW() AND f.status != '0'";
        $params['let'] = $let.'%';
    } else {
        $field = 'op=liste&';
        $order = "WHERE f.time <= NOW() AND f.status != '0'";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $listnum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, c.title, c.intro, u.name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) '.$order.' '.$cwhere.' ORDER BY time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = setModuleNavi(['title' => _LIST, 'htitle' => _LINKS]);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['links']['letter']) ? letter($conf['name']) : '';
        $cont .= $tpl->getHtmlFrag('liste-wrap', ['open' => true, 'letter' => $letter, 'id' => _ID, 'title' => _TITLE, 'category' => _CATEGORY, 'poster' => _POSTER, 'date' => _DATE]);
        while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= $tpl->getHtmlFrag('liste-basic', ['id' => $id, 'title_href' => $thref, 'title_attr' => $title, 'title_text' => cutstr($title, 40), 'title_new' => new_graphic($time), 'category_href' => $ctitle ? $chref : '', 'category_attr' => $cdesc, 'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO, 'post_text' => $post, 'time_text' => format_time($time), 'time_iso' => date('c', strtotime($time)), 'time_label' => _DATE]);
        }
        $cont .= $tpl->getHtmlFrag('liste-wrap', []);
        $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $params = ($let) ? ['let' => $let.'%'] : [];
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_links', 'cid', $onum, $conf['links']['nump'], $params);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $cwhere = catmids($conf['name'], 'f.cid');
    $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.url, f.intro, f.body, f.time, f.email, f.counter, f.acomm, f.votes, f.tvotes, f.hits, f.status, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_links AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id = :id AND f.time <= NOW() AND f.status != \'0\' '.$cwhere, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET counter = counter+1 WHERE id = :id', ['id' => $id]);
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
        $cont = setModuleNavi(['title' => _LINKS]);
        if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $cid, $conf['links']['defis'], _LINKS)]);
        if ($conf['links']['viewcat']) $cont .= setCategories($conf['name'], $conf['links']['subcat'], $conf['links']['catdesc'], 0);
        $text = ($bodytext) ? $description.'<br><br>'.$bodytext : $description;
        $cdesc = $cdesc ?: $ctitle;
        $ctitle = ($ctitle) ? $tpl->getHtmlFrag('category-link', ['href' => $chref, 'title' => $cdesc, 'text' => cutstr($ctitle, 15)]) : '';
        $cimg = ($cimg) ? $tpl->getHtmlFrag('category-image', ['href' => $chref, 'title' => $cdesc, 'src' => img_find('categories/'.$cimg)]) : '';
        $post = ($conf['links']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['links']['date']) ? format_time($date) : '';
        $hits = ($conf['links']['hits']) ? $tpl->getHtmlFrag('hit-badge', ['title' => _LINKHITS, 'text' => $hits, 'cls' => 'sl_down']) : '';
        $rating = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
        if (is_user() || $conf['links']['links'] == '1') {
            $onclick = ' OnClick="javascript:window.open(\''.str_replace(["\\", "'"], ["\\\\", "\\'"], $authorurl).'\')"';
            $download = $tpl->getHtmlFrag('files-download-form', ['name' => $conf['name'], 'id' => $id, 'onclick' => $onclick, 'submit_label' => _DOWNLLINK]);
        }
        $broken = ($conf['links']['broc'] == 1 && $status != '2') ? $tpl->getHtmlFrag('action-link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'broken', 'id' => $id]), 'title' => _BROCLINK, 'label' => _COMPLAINT, 'class' => 'sl_but_blue']) : '';
        $email = ($aemail) ? _AUEMAIL.': '.anti_spam($aemail) : '';
        $home = ($authorurl) ? _SITE.': '.domain($authorurl) : '';
        $admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('admin-menu', ['editor_text' => _EDITOR, 'edit_href' => $afile.'.php?op=links_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=links_delete&amp;id='.$id, 'delete_ask' => $ask, 'delete_text' => _ONDELETE]) : '';
        $goback = $tpl->getHtmlFrag('back-button', ['title' => _BACK, 'label' => _BACK]);
        $cont .= $tpl->getHtmlFrag('basic-download-view', [
            'id' => $id,
            'favorites' => $favorites,
            'title' => filterTextHighlight($title, $word),
            'hits' => $hits,
            'reads' => ($conf['links']['read']) ? $counter : '',
            'reads_label' => _READS,
            'post' => $post,
            'post_label' => _POSTEDBY,
            'date' => $date,
            'date_iso' => ($date) ? date('c', strtotime($seotime)) : '',
            'date_label' => _CHNGSTORY,
            'ctitle' => $ctitle,
            'cimg' => $cimg,
            'text' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word),
            'email' => $email,
            'home' => $home,
            'rating' => $rating,
            'goback' => $goback,
            'admin' => $admin,
            'download' => $download ?? '',
            'broken' => $broken,
        ]);
        if ($conf['links']['link']) {
            $limit = intval($conf['links']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_links WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\'', ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT id, title, intro, body, time FROM '.PREFIX_DB.'_links WHERE cid = :cid AND id != :id AND time <= NOW() AND status != \'0\' ORDER BY time DESC LIMIT '.$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= $tpl->getHtmlFrag('assoc-wrap', ['open' => true, 'title' => _CATASSOC]);
                while([$aid, $title, $hometext, $bodytext, $time] = $db->getSqlRow($result)) {
                    $date = ($conf['links']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $cont .= $tpl->getHtmlFrag('assoc-basic', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), 'title_attr' => $title, 'title_text' => $title, 'date_text' => $date, 'date_iso' => ($conf['links']['date']) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'text' => $text, 'img_src' => $img]);
                }
                $cont .= $tpl->getHtmlFrag('assoc-wrap', []);
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
        $cont = setModuleNavi(['title' => _ADD, 'htitle' => _LINKS]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        if ($description) $cont .= preview($title, $description, $bodytext, '', $conf['name']);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADDFNOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $cont .= $tpl->getHtmlFrag('form-add', [
            'has_name' => true,
            'is_user' => is_user(),
            'name' => $conf['name'],
            'token' => htmlspecialchars(getSiteToken('links'), ENT_QUOTES, 'UTF-8'),
            'style' => $conf['style'],
            'lbl_name' => _YOURNAME,
            'lbl_email' => _AUEMAIL,
            'lbl_title' => _SITENAME,
            'lbl_cat' => _CATEGORY,
            'lbl_text' => _TEXT,
            'lbl_body' => _ENDTEXT,
            'lbl_site' => _URL,
            'username' => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname' => $postname,
            'emailval' => $mail,
            'titleval' => $title,
            'catselect' => getcat($conf['name'], $cid, 'cid', $conf['style'],
                '<option value="">'._HOMECAT.'</option>'),
            'hometext' => textarea('1', 'description', $description, $conf['name'], '5', _TEXT, '1'),
            'bodytext' => textarea('2', 'bodytext', $bodytext, $conf['name'], '15', _ENDTEXT, '0'),
            'siteval' => $site,
            'site_attr' => 'site',
            'captcha' => getCaptcha(1),
            'submit' => ad_save('', '', 'send'),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $user, $conf, $stop, $tpl;
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
        if (!$site) $stop[] = _CERROR4;
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_links WHERE url = :site', ['site' => $site])) > 0) $stop[] = _LINKEXIST;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_links (id, cid, uid, name, title, intro, body, url, time, email, ip, status) VALUES (NULL, :cid, :postid, :uname, :title, :intro, :body, :site, NOW(), :mail, :ip, \'0\')', ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'site' => $site, 'mail' => $mail, 'ip' => getIp()]);
            update_points(21);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['links']['addmail'], $conf['name'], $puname, _LINKS);
            setHead(['title' => _ADD]);
            $meta = getMetaRefresh('index.php?name='.$conf['name']);
            echo setModuleNavi(['title' => _ADD, 'htitle' => _LINKS]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISHL, 'meta' => $meta]);
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
        $meta = getMetaRefresh('index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 5);
        echo setModuleNavi(['title' => _BROCLINK, 'htitle' => _LINKS]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTEL, 'meta' => $meta]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function loading(): void {
    global $db, $conf, $tpl;
    $id = getVar('post', 'id', 'num');
    if (($id && is_user()) || ($id && $conf['links']['links'] == '1')) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$title, $url] = $db->getSqlRow($db->getSqlQuery('SELECT title, url FROM '.PREFIX_DB.'_links WHERE id = :id', ['id' => $id]));
        update_points(23);
        $info = sprintf(_NOTELINKLOAD, $title, domain($url));
        setHead(['title' => _LINKS]);
        $cont = setModuleNavi(['title' => _LINKS]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
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
