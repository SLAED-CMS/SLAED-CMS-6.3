<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const JOKES_NAVI = ['htitle' => _JOKES, 'liste_href' => ''];

function jokes(): void {
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 'j.cid');
    $unum = getUserNews($conf['jokes']['num']);
    $word = getVar('get', 'word', 'word');
    $ncat = getVar('get', 'cat', 'num');
    $params = [];
    if (!$ncat && $op && $conf['jokes']['rate']) {
        $caton = 0;
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
        if ($op == 'best') {
            $orderby = 'IFNULL((j.rating/NULLIF(j.ratetot,0)),0) DESC';
        } elseif ($op) {
            $orderby = 'IFNULL((j.ratetot/NULLIF((TO_DAYS(NOW()) - TO_DAYS(j.time)),0)),0) DESC';
        } else {
            $orderby = 'j.time DESC';
        }
        $qres = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]);
        [$ctitle] = $db->getSqlRow($qres);
        $ctitle = $ctitle ?? _JOKES;
        $ntitle = ($op)
            ? (($op == 'best')
                ? $ctitle.' '.$conf['defis'].' '._BEST
                : $ctitle.' '.$conf['defis'].' '._POP)
            : $ctitle;
        $order = "WHERE (j.cid = :ncat1 OR c.parent = :ncat2) AND j.time <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY '.$orderby;
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
        $order = "WHERE j.time <= NOW() AND j.status != '0' ".$cwhere.' ORDER BY j.time DESC';
        $onum = "time <= NOW() AND status != '0'";
        $ntitle = _JOKES;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home || ($home && $conf['jokes']['homcat'])) {
        $cont .= getModuleNavi(['title' => $ntitle] + JOKES_NAVI);
        if ($ncat) $cont .= $tpl->getHtmlFrag('category-nav', ['crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['jokes']['defis'], _JOKES)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['jokes']['subcat'], $conf['jokes']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $sql = 'SELECT j.id, j.name, j.time, j.title, j.cid, j.body, j.rating, j.ratetot, c.title, c.intro, c.img, u.name'
        .' FROM '.PREFIX_DB.'_jokes AS j'
        .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid = c.id)'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id)'
        .' '.$order.' LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $uname, $time, $jtitle, $cid, $joke, $rating, $ratingtot, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $chref  = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc  = $cdesc ?: $ctitle;
            $cimg   = ($cimg) ? img_find('categories/'.$cimg) : '';
            $post   = ($nick) ? user_info($nick) : (($uname) ? $uname : _ANONYM);
            $date   = ($conf['jokes']['date']) ? format_time($time) : '';
            $iso    = ($conf['jokes']['date']) ? date('c', strtotime($time)) : '';
            $rating = getRatingAsync(1, $id, $conf['name'], $ratingtot, $rating, '');
            $ask    = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$jtitle.'&quot;?');
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'width'         => 100,
                'title_href'    => '#'.$id,
                'title_attr'    => $jtitle,
                'title_text'    => filterTextHighlight($jtitle, $word),
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_img'  => $cimg,
                'text'          => filterTextHighlight($prs->filterContent($joke, false, $conf['name']), $word),
                'read_href'     => '',
                'read_text'     => '',
                'post_text'     => $post,
                'post_label'    => _POSTEDBY,
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => '',
                'reads_label'   => _READS,
                'hits'          => '',
                'comm_href'     => '',
                'comm_text'     => '',
                'comm_label'    => _COMMENTS,
                'rating'        => $rating,
                'favorites'     => '',
                'voting'        => '',
                'editor'        => _EDITOR,
                'edit_href'     => $afile.'.php?name=jokes&amp;op=jokes_add&amp;id='.$id,
                'edit_text'     => _FULLEDIT,
                'delete_href'   => $afile.'.php?name=jokes&amp;op=jokes_delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token,
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
            'maxpg'     => $conf['jokes']['nump'],
            'table'     => '_jokes',
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

function add(): void {
    global $conf, $user, $stop, $tpl;
    if ($conf['jokes']['add'] == '1') {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'cid', 'num');
        $joke     = getVar('post', 'joke', 'raw');
        $postname = getVar('post', 'postname', 'name');
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD] + JOKES_NAVI);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($joke) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $joke, 'textb' => '', 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ADD_JNOTE]);
        if (!is_user()) $postname = $postname ?: _ANONYM;
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('jokes')]);
        $nameField = is_user()
            ? $tpl->getHtmlFrag('span', ['is_form_value' => true, 'text' => filterText(substr($user[1], 0, 25))])
            : $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'postname', 'value_attr' => $postname, 'placeholder_text' => _YOURNAME, 'is_required' => true]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _YOURNAME, 'field_html' => $nameField]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _JTITLE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 100, 'placeholder_text' => _JTITLE, 'is_required' => true]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _CATEGORY,
            'field_html' => getTplCategorySelect($conf['name'], $cid, 'cid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false])),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _JOKE, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'joke', 'value' => $joke, 'mod' => $conf['name'], 'rows' => '10', 'placeholder' => _JOKE, 'required' => '1'])]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name'      => $conf['name'],
            'fields'    => $fields,
            'captcha'   => getCaptcha('comment'),
            'submit'    => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $conf, $user, $stop, $tpl;
    if ($conf['jokes']['add'] == '1') {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'cid', 'num');
        $joke     = getVar('post', 'joke', 'text');
        $postname = getVar('post', 'postname', 'name');
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'jokes')) $stop[] = _ERROR;
        if (!$title) $stop[] = _CERROR;
        if (!$joke) $stop[] = _CERROR1;
        if (!$postname && !is_user()) $stop[] = _CERROR3;
        if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        if ($db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_jokes (id, uid, name, time, title, cid, body, ip, status)'
                ." VALUES (NULL, :postid, :uname, NOW(), :title, :cid, :joke, :ip, '0')",
                ['postid' => $postid, 'uname' => $uname,
                    'title' => $title, 'cid' => $cid, 'joke' => $joke, 'ip' => getIp()]
            );
            update_points(19);
            $puname = (is_user()) ? $user[1] : $postname;
            addAdminMail($conf['jokes']['addmail'], $conf['name'], $puname, _JOKES);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD] + JOKES_NAVI).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SUBTEXT, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: jokes(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
