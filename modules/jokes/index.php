<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const JOKES_NAVI = ['htitle' => _JOKES, 'liste_href' => ''];


function jokes(): void {
    global $db, $afile, $user, $conf, $home, $op, $tpl;
    $cwhere = catmids($conf['name'], 'j.cid');
    $word = getVar('get', 'word', 'word');
    $unum = getUserNews($conf['jokes']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = [];
    if (!$ncat && $op && $conf['jokes']['rate']) {
        $caton = 0;
        $field = 'op='.$op.'&';
        if ($op == 'best') {
            $orderby = 'IFNULL((j.rating/NULLIF(j.ratetot,0)),0) DESC';
            $ntitle = _BEST;
        } else {
            $orderby = 'IFNULL((j.ratetot/NULLIF((TO_DAYS(NOW()) - TO_DAYS(j.time)),0)),0) DESC';
            $ntitle = _POP;
        }
        $order = "WHERE j.time <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY '.$orderby;
        $onum = "time <= NOW() AND status != '0'";
    } elseif ($ncat) {
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
        $orderby = ($op) ? (($op == 'best') ? 'IFNULL((j.rating/NULLIF(j.ratetot,0)),0) DESC' : 'IFNULL((j.ratetot/NULLIF((TO_DAYS(NOW()) - TO_DAYS(j.time)),0)),0) DESC') : 'j.time DESC';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $ntitle = ($op) ? (($op == 'best') ? $ctitle.' '.$conf['defis'].' '._BEST : $ctitle.' '.$conf['defis'].' '._POP) : $ctitle;
        $order = "WHERE (j.cid = :ncat1 OR c.parent = :ncat2) AND j.time <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY '.$orderby;
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
        $order = "WHERE j.time <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY j.time DESC';
        $onum = "time <= NOW() AND status != '0'";
        $ntitle = _JOKES;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['jokes']['homcat'])) {
        $cont .= setModuleNavi(['title' => $ntitle] + JOKES_NAVI);
        if ($ncat) $cont .= $tpl->getHtmlFrag('cat-navi', ['crumbs' => catlink($conf['name'], $ncat, $conf['jokes']['defis'], _JOKES)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['jokes']['subcat'], $conf['jokes']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT j.id, j.name, j.time, j.title, j.cid, j.body, j.rating, j.ratetot, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid=u.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $uname, $time, $jtitle, $cid, $joke, $rating, $ratingtot, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $post = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $date = ($conf['jokes']['date']) ? format_time($time) : '';
            $cdesc = $cdesc ?: $ctitle;
            $chref = 'index.php?name='.$conf['name'].'&amp;cat='.$cid;
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $rating = getRatingAsync(1, $id, $conf['name'], $ratingtot, $rating, '');
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$jtitle.'&quot;?');
            $cont .= getContentCard([
                'id' => $id,
                'title_href' => '#'.$id,
                'title_attr' => $jtitle,
                'title_text' => filterTextHighlight($jtitle, $word),
                'title_new' => new_graphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img' => $cimg,
                'text' => filterTextHighlight(filterReplaceText(filterMarkdown($joke, $conf['name'], false), $conf['name']), $word),
                'read_href' => '',
                'read_text' => '',
                'post_text' => $post,
                'post_label' => _POSTEDBY,
                'date_text' => $date,
                'date_iso' => ($date) ? date('c', strtotime($time)) : '',
                'date_label' => _CHNGSTORY,
                'reads_text' => '',
                'reads_label' => _READS,
                'hits' => '',
                'comm_href' => '',
                'comm_text' => '',
                'comm_label' => _COMMENTS,
                'rating' => $rating,
                'favorites' => '',
                'voting' => '',
                'editor' => _EDITOR,
                'edit_href' => $afile.'.php?op=jokes_add&amp;id='.$id,
                'edit_text' => _FULLEDIT,
                'delete_href' => $afile.'.php?op=jokes_delete&amp;id='.$id.'&amp;refer=1',
                'delete_text' => _ONDELETE,
                'delete_ask' => $ask,
                'back_title' => '',
                'back_text' => '',
                'is_moder' => is_moder($conf['name']),
            ]);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_jokes', 'cid', $onum, $conf['jokes']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $user, $conf, $stop, $tpl;
    if ($conf['jokes']['add'] == '1') {
        $title = getVar('post', 'title', 'text');
        $cid = getVar('post', 'cid', 'num');
        $joke = getVar('post', 'joke', 'text');
        $postname = filterText(substr(getVar('post', 'postname', 'name'), 0, 25));
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD] + JOKES_NAVI);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        if ($joke) $cont .= preview($title, $joke, '', '', 'all');
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADD_JNOTE]);
        $cont .= $tpl->getHtmlFrag('form-add', [
            'has_name' => true,
            'is_user' => is_user(),
            'name' => $conf['name'],
            'token' => htmlspecialchars(getSiteToken('jokes'), ENT_QUOTES, 'UTF-8'),
            'style' => $conf['style'],
            'lbl_name' => _YOURNAME,
            'lbl_title' => _JTITLE,
            'lbl_cat' => _CATEGORY,
            'lbl_text' => _JOKE,
            'username' => is_user() ? filterText(substr($user[1], 0, 25)) : '',
            'postname' => $postname ?: _ANONYM,
            'titleval' => $title,
            'catselect' => getcat($conf['name'], $cid, 'cid', $conf['style'], '<option value="">'._HOMECAT.'</option>'),
            'hometext' => textarea('1', 'joke', $joke, $conf['name'], '10', _JOKE, '1'),
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
    if ($conf['jokes']['add'] == '1') {
        $postname = filterText(substr(getVar('post', 'postname', 'name'), 0, 25));
        $title = getVar('post', 'title', 'text');
        $cid = getVar('post', 'cid', 'num');
        $joke = getVar('post', 'joke', 'text');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'jokes')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$joke) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
        if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
            $postid = (is_user()) ? intval($user[0]) : '';
            $uname = (!is_user()) ? $postname : '';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_jokes (id, uid, name, time, title, cid, body, ip, status) VALUES (NULL, :postid, :uname, NOW(), :title, :cid, :joke, :ip, \'0\')', ['postid' => $postid, 'uname' => $uname, 'title' => $title, 'cid' => $cid, 'joke' => $joke, 'ip' => getIp()]);
            update_points(19);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['jokes']['addmail'], $conf['name'], $puname, _JOKES);
            setHead(['title' => _JOKES.' '._ADD, 'desc' => _UPLOADFINISHJ]);
            $meta = getMetaRefresh('index.php?name='.$conf['name']);
            echo setModuleNavi(['title' => _ADD] + JOKES_NAVI).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPLOADFINISHJ, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: jokes(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
