<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function files(): void {
    global $db, $afile, $user, $conf, $home, $op, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $unum = getUserNews($conf['files']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['files']['rate']) {
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
        $ntitle = _FILES;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['files']['homcat'])) {
        $cont .= setModuleNavi(['title' => $ntitle, 'htitle' => _FILES]);
        if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $ncat, $conf['files']['defis'], _FILES)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['files']['subcat'], $conf['files']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.intro, f.body, f.time, f.counter, f.acomm, f.votes, f.tvotes, f.comments, f.hits, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $cid, $uname, $stitle, $description, $bodytext, $time, $counter, $acomm, $votes, $totalvotes, $comm, $hits, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $post = ($conf['files']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
            $date = ($conf['files']['date']) ? format_time($time) : '';
            $hits = ($conf['files']['hits']) ? $tpl->getHtmlFrag('hit-badge', ['title' => _FILEHITS, 'text' => $hits, 'cls' => 'sl_down']) : '';
            $rating = getRatingAsync(0, $id, $conf['name'], $votes, $totalvotes, '');
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$stitle.'&quot;?');
            $cont .= getTplContentCard([
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
                'reads_text' => ($conf['files']['read']) ? $counter : '',
                'reads_label' => _READS,
                'hits' => $hits,
                'comm_href' => ($acomm) ? $thref.'#comm' : '',
                'comm_text' => ($acomm) ? $comm : '',
                'comm_label' => _COMMENTS,
                'rating' => $rating,
                'favorites' => '',
                'voting' => '',
                'editor' => _EDITOR,
                'edit_href' => $afile.'.php?op=files_add&amp;id='.$id,
                'edit_text' => _FULLEDIT,
                'delete_href' => $afile.'.php?op=files_delete&amp;id='.$id.'&amp;refer=1',
                'delete_text' => _ONDELETE,
                'delete_ask' => $ask,
                'back_title' => '',
                'back_text' => '',
                'is_moder' => is_moder($conf['name']),
            ]);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_files', 'cid', $onum, $conf['files']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $tpl;
    $cwhere = catmids($conf['name'], 'f.cid');
    $listnum = intval($conf['files']['listnum']);
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
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, c.title, c.intro, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) '.$order.' '.$cwhere.' ORDER BY time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = setModuleNavi(['title' => _LIST, 'htitle' => _FILES]);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['files']['letter']) ? letter($conf['name']) : '';
        $cont .= $tpl->getHtmlFrag('liste-wrap', ['open' => true, 'letter' => $letter, 'id' => _ID, 'title' => _TITLE, 'category' => _CATEGORY, 'poster' => _POSTER, 'date' => _DATE]);
        while ([$id, $cid, $uname, $title, $time, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $cont .= $tpl->getHtmlFrag('liste-basic', [
                'id' => $id,
                'title_href' => $thref,
                'title_attr' => $title,
                'title_text' => cutstr($title, 40),
                'title_new' => new_graphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
                'post_text' => $post,
                'time_text' => format_time($time),
                'time_iso' => date('c', strtotime($time)),
                'time_label' => _DATE,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('liste-wrap', []);
        $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND status != '0'" : "time <= NOW() AND status != '0'";
        $params = ($let) ? ['let' => $let.'%'] : [];
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_files', 'cid', $onum, $conf['files']['nump'], $params);
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
    $result = $db->getSqlQuery('SELECT f.cid, f.name, f.title, f.url, f.intro, f.body, f.time, f.filesize, f.version, f.email, f.website, f.counter, f.acomm, f.votes, f.tvotes, f.hits, f.status, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB."_users AS u ON (f.uid = u.id) WHERE f.id = :id AND f.time <= NOW() AND f.status != '0' ".$cwhere, ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$cid, $uname, $title, $url, $description, $bodytext, $date, $fsize, $fversion, $aemail, $awebsite, $counter, $acomm, $votes, $totalvotes, $hits, $status, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result);
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
        $cont = setModuleNavi(['title' => _FILES]);
        if ($cid) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $cid, $conf['files']['defis'], _FILES)]);
        if ($conf['files']['viewcat']) $cont .= setCategories($conf['name'], $conf['files']['subcat'], $conf['files']['catdesc'], 0);
        $text = ($bodytext) ? $description.'<br><br>'.$bodytext : $description;
        $cdesc = $cdesc ?: $ctitle;
        $ctitle = ($ctitle) ? $tpl->getHtmlFrag('category-link', ['href' => $chref, 'title' => $cdesc, 'text' => cutstr($ctitle, 15)]) : '';
        $cimg = ($cimg) ? $tpl->getHtmlFrag('category-image', ['href' => $chref, 'title' => $cdesc, 'src' => img_find('categories/'.$cimg)]) : '';
        $post = ($conf['files']['autor']) ? (($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM)) : '';
        $date = ($conf['files']['date']) ? format_time($date) : '';
        $hits = ($conf['files']['hits']) ? $tpl->getHtmlFrag('hit-badge', ['title' => _FILEHITS, 'text' => $hits, 'cls' => 'sl_down']) : '';
        $rating = getRatingAsync(1, $id, $conf['name'], $votes, $totalvotes, '');
        $favorites = getFavoriteButton($id, $conf['name']);
        $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
        $size = _SIZE.': '.filterSize($fsize);
        $version = _VERSION.': '.$fversion;
        if (is_user() || $conf['files']['down'] == '1') {
            $onclick = (!$conf['files']['stream']) ? ' OnClick="javascript:window.open(\''.$url.'\');"' : '';
            $download = $tpl->getHtmlFrag('files-download-form', ['name' => $conf['name'], 'id' => $id, 'onclick' => $onclick, 'submit_label' => _UPLOAD]);
        }
        $broken = ($conf['files']['broc'] == 1 && $status != '2') ? $tpl->getHtmlFrag('action-link', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'broken', 'id' => $id]), 'title' => _BROCFILE, 'label' => _COMPLAINT, 'class' => 'sl_but_blue']) : '';
        $email = ($aemail) ? _AUEMAIL.': '.anti_spam($aemail) : '';
        $home = ($awebsite) ? _SITE.': '.domain($awebsite) : '';
        $admin = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('admin-menu', ['editor_text' => _EDITOR, 'edit_href' => $afile.'.php?op=files_add&amp;id='.$id, 'edit_text' => _FULLEDIT, 'delete_href' => $afile.'.php?op=files_delete&amp;id='.$id, 'delete_ask' => $ask, 'delete_text' => _ONDELETE]) : '';
        $goback = $tpl->getHtmlFrag('back-button', ['title' => _BACK, 'label' => _BACK]);
        $cont .= $tpl->getHtmlFrag('basic-download-view', [
            'id' => $id,
            'favorites' => $favorites,
            'title' => filterTextHighlight($title, $word),
            'hits' => $hits,
            'reads' => ($conf['files']['read']) ? $counter : '',
            'reads_label' => _READS,
            'post' => $post,
            'post_label' => _POSTEDBY,
            'date' => $date,
            'date_iso' => ($date) ? date('c', strtotime($seotime)) : '',
            'date_label' => _CHNGSTORY,
            'ctitle' => $ctitle,
            'cimg' => $cimg,
            'text' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word),
            'size' => $size,
            'version' => $version,
            'email' => $email,
            'home' => $home,
            'rating' => $rating,
            'goback' => $goback,
            'admin' => $admin,
            'download' => $download ?? '',
            'broken' => $broken,
        ]);
        if ($conf['files']['link']) {
            $limit = intval($conf['files']['linknum']);
            [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_files WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0'", ['cid' => $cid, 'id' => $id]));
            if ($count >= $limit) {
                $random = mt_rand(0, $count - $limit);
                $result = $db->getSqlQuery('SELECT id, title, intro, body, time FROM '.PREFIX_DB."_files WHERE cid = :cid AND id != :id AND time <= NOW() AND status != '0' ORDER BY time DESC LIMIT ".$random.', '.$limit, ['cid' => $cid, 'id' => $id]);
                $cont .= $tpl->getHtmlFrag('assoc-wrap', ['open' => true, 'title' => _CATASSOC]);
                while([$aid, $title, $hometext, $bodytext, $time] = $db->getSqlRow($result)) {
                    $date = ($conf['files']['date']) ? _CHNGSTORY.': '.format_time($time) : '';
                    $text = cutstr(htmlspecialchars(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), ENT_QUOTES), 80);
                    $img = getImgText($hometext);
                    $img = ($img) ? $img : img_find('logos/slaed_logo_60x60.png');
                    $cont .= $tpl->getHtmlFrag('assoc-basic', ['href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $aid, 'title' => $title]), 'title_attr' => $title, 'title_text' => $title, 'date_text' => $date, 'date_iso' => ($conf['files']['date']) ? date('c', strtotime($time)) : '', 'date_label' => _CHNGSTORY, 'text' => $text, 'img_src' => $img]);
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
    global $db, $user, $conf, $stop, $tpl;
    if ((is_user() && $conf['files']['add'] == 1) || (!is_user() && $conf['files']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        if (is_user()) {
            $userinfo = getUserInfo();
            $mail = getVar('post', 'mail', 'text', $userinfo['email']);
            $home = getVar('post', 'home', 'url', $userinfo['website']);
        } else {
            $mail = getVar('post', 'mail', 'text');
            $home = getVar('post', 'home', 'url', 'http://');
        }
        $url = getVar('post', 'url', 'url', 'http://');
        $fversion = getVar('post', 'fversion', 'text');
        $fsize = getVar('post', 'fsize', 'num');
        $info = _ADDFNOTE;
        if ($conf['files']['upload'] == 1) $info .= sprintf(_ADDFNOTE2, str_replace(',', ', ', $conf['files']['typefile']), filterSize($conf['files']['max_size']));
        $info .= ' '._ADDFNOTE3;
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD, 'htitle' => _FILES]);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        if ($description) $cont .= preview($title, $description, $bodytext, '', $conf['name']);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $extra = '';
        if ($conf['files']['upload'] == 1) {
            $extra .= $tpl->getHtmlFrag('files-add-upload-row', ['label' => _FILE_USER, 'style' => $conf['style']]);
        }
        $extra .= $tpl->getHtmlFrag('files-add-input-row', ['label' => _URL, 'type' => 'url', 'field' => 'url', 'value' => $url, 'maxlength' => '100', 'style' => $conf['style'], 'placeholder' => _URL]);
        $extra .= $tpl->getHtmlFrag('files-add-input-row', ['label' => _VERSION, 'type' => 'text', 'field' => 'fversion', 'value' => $fversion, 'maxlength' => '10', 'style' => $conf['style'], 'placeholder' => _VERSION]);
        $extra .= $tpl->getHtmlFrag('files-add-input-row', ['label' => _SIZE, 'type' => 'text', 'field' => 'fsize', 'value' => $fsize, 'maxlength' => '10', 'style' => $conf['style'], 'placeholder' => _SIZE]);
        $cont .= $tpl->getHtmlFrag('form-add', [
            'has_name' => true,
            'is_user' => is_user(),
            'name' => $conf['name'],
            'token' => htmlspecialchars(getSiteToken('files'), ENT_QUOTES, 'UTF-8'),
            'style' => $conf['style'],
            'lbl_name' => _YOURNAME,
            'lbl_email' => _AUEMAIL,
            'lbl_title' => _NAME,
            'lbl_cat' => _CATEGORY,
            'lbl_text' => _TEXT,
            'lbl_body' => _ENDTEXT,
            'lbl_site' => _SITE,
            'username' => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname' => $postname,
            'emailval' => $mail,
            'titleval' => $title,
            'catselect' => getcat($conf['name'], $cid, 'cid', $conf['style'], $tpl->getHtmlFrag('form-option', ['value' => '', 'selected' => '', 'label' => _HOMECAT])),
            'hometext' => textarea('1', 'description', $description, $conf['name'], '5', _TEXT, '1'),
            'bodytext' => textarea('2', 'bodytext', $bodytext, $conf['name'], '15', _ENDTEXT, '0'),
            'siteval' => $home,
            'site_attr' => 'site',
            'extrafields' => $extra,
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
    if ((is_user() && $conf['files']['add'] == 1) || (!is_user() && $conf['files']['addquest'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'cid', 'num');
        $description = getVar('post', 'description', 'text');
        $bodytext = getVar('post', 'bodytext', 'text');
        $postname = getVar('post', 'postname', 'name');
        $mail = getVar('post', 'mail', 'text');
        $home = getVar('post', 'home', 'url');
        $url = getVar('post', 'url', 'url');
        $fversion = getVar('post', 'fversion', 'text');
        $fsize = getVar('post', 'fsize', 'num');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'files')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        checkemail($mail);
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_files WHERE title = :title', ['title' => $title])) > 0) $stop[] = _MEDIAEXIST;
        $userid = isset($user[0]) ? intval($user[0]) : '0';
        $filename = upload(1, $conf['files']['temp'], $conf['files']['typefile'], $conf['files']['max_size'], 'files', '1600', '1600', $userid);
        $url = ($filename) ? $conf['files']['temp'].'/'.$filename : $url;
        $fsize = ($filename) ? filesize($url) : $fsize;
        if ($stop) {
            // Do nothing
        } elseif (!$url && getVar('post', 'posttype', 'var') == 'save') {
            $stop[] = _UPLOADEROR2;
        }
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_files (id, cid, uid, name, title, intro, body, url, time, filesize, version, email, website, ip, status) VALUES (NULL, :cid, :postid, :uname, :title, :intro, :body, :url, NOW(), :fsize, :fversion, :mail, :home, :ip, '0')", ['cid' => $cid, 'postid' => $postid, 'uname' => $uname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $url, 'fsize' => $fsize, 'fversion' => $fversion, 'mail' => $mail, 'home' => $home, 'ip' => getIp()]);
            update_points(9);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['files']['addmail'], $conf['name'], $puname, _FILES);
            setHead(['title' => _ADD]);
            $meta = getTplMetaRefresh('index.php?name='.$conf['name']);
            echo setModuleNavi(['title' => _ADD, 'htitle' => _FILES]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISH, 'meta' => $meta]);
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
    if ($conf['files']['broc'] == '1' && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB."_files SET status = '2' WHERE id = :id AND status != '0'", ['id' => $id]);
        setHead(['title' => _BROCFILE]);
        $meta = getTplMetaRefresh('index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id, 5);
        echo setModuleNavi(['title' => _BROCFILE, 'htitle' => _FILES]).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BROCNOTE, 'meta' => $meta]);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function loading(): void {
    global $db, $conf, $tpl;
    $id = getVar('post', 'id', 'num');
    if (($id && is_user()) || ($id && $conf['files']['down'] == '1')) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET hits = hits+1 WHERE id = :id', ['id' => $id]);
        [$stitle, $url] = $db->getSqlRow($db->getSqlQuery('SELECT title, url FROM '.PREFIX_DB.'_files WHERE id = :id', ['id' => $id]));
        update_points(11);
        if ($conf['files']['stream'] == 2) {
            $type = strtolower(substr(strrchr($url, '.'), 1));
            stream($url, getPass(10).'.'.$type);
        } elseif ($conf['files']['stream'] == '1') {
            stream($url, preg_replace('#(.*?)\/#i', '', $url));
        } else {
            $info = sprintf(_NOTEDOWNLOAD, $stitle, $tpl->getHtmlFrag('files-external-link', ['href' => $url, 'title' => _UPLOAD.': '.$stitle, 'label' => $url]));
            setHead(['title' => _FILES]);
            $cont = setModuleNavi(['title' => _FILES]);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
            $cont .= setNaviLower($conf['name']);
            echo $cont;
            setFoot();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: files(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'broken': broken(); break;
    case 'loading': loading(); break;
}
