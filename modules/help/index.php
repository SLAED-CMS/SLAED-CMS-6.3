<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const HELP_NAVI = ['htitle' => _HELP, 'bop' => 'closed', 'btitle' => _CLOSED, 'always' => true, 'addquest' => false];

function help(): void {
    global $db, $user, $conf, $home, $op, $tpl, $prs;
    $cwhere = catmids($conf['name'], 's.cid');
    $uid  = (int)($user[0] ?? 0);
    $unum = (int)getUserNews($conf['help']['num']);
    if ($unum < 1) $unum = 1;
    $word   = getVar('get', 'word', 'word');
    $cat    = getVar('get', 'cat', 'num');
    $ncat   = $cat;
    $params = ['uid' => $uid];
    if (!$ncat && $op) {
        $caton = 0;
        if ($op == 'closed') {
            $order  = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum   = "pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
            $ntitle = _CLOSED;
        } else {
            $order  = "WHERE s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.counter DESC';
            $onum   = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _POP;
        }
    } elseif ($ncat) {
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $cids   = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parent = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $cids[] = $caid;
        unset($result);
        $params = ['uid' => $uid, 'ncat1' => $ncat, 'ncat2' => $ncat];
        if (isArray($cids)) {
            $caton = 1;
            array_unshift($cids, $ncat);
            $wcid = 'cid IN ('.implode(', ', array_map('intval', $cids)).')';
        } else {
            $caton = 0;
            $wcid  = 'cid = '.(int)$ncat;
        }
        if ($op == 'closed') {
            $order  = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum   = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
            $ntitle = _CLOSED;
        } elseif ($op == 'pop') {
            $order  = "WHERE s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.counter DESC';
            $onum   = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _POP;
        } else {
            $order  = "WHERE s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum   = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _HELP;
        }
        $ntitle = $ctitle.' '.$conf['defis'].' '.$ntitle;
    } else {
        $caton  = 1;
        $order  = "WHERE s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
        $onum   = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
        $ntitle = _HELPINFO;
    }
    $url_extra = [];
    if ($ncat) $url_extra['cat'] = $ncat;
    if ($op)   $url_extra['op']  = $op;
    setHead(['title' => $ntitle, 'kind' => 'collection']);
    $cont = '';
    if (!$home) {
        $cont .= getModuleNavi(['title' => $ntitle] + HELP_NAVI);
        if ($ncat)      $cont .= $tpl->getHtmlFrag('category-nav', ['label' => _CATEGORIES, 'crumbs' => getTplCategoryTrail($conf['name'], $ncat, $conf['help']['defis'], _HELP)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['help']['subcat'], $conf['help']['catdesc'], $ncat);
    }
    $num    = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.body, s.comments, s.counter, c.title, c.intro, c.img, c.ordern FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $cid, $stitle, $time, $hometext, $comm, $counter, $ctitle, $cdesc, $cimg, $cordern] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $cimg  = getCategoryIcon($cimg);
            $date  = ($conf['help']['date']) ? format_time($time) : '';
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'is_nested'     => false,
                'width'         => 100,
                'title_href'    => $thref,
                'title_attr'    => $stitle,
                'title_text'    => $stitle,
                'title_new'     => getTplNewGraphic($time),
                'category_href' => $ctitle ? $chref : '',
                'category_attr' => $cdesc,
                'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                'category_icon'  => $cimg,
                'category_tone'  => $cordern % 6,
                'text'          => $prs->filterContent($hometext, false, $conf['name'], 2),
                'read_href'     => $thref,
                'read_text'     => _READMORE,
                'post_text'     => '',
                'post_label'    => '',
                'date_text'     => $date,
                'date_iso'      => ($date) ? date('c', strtotime($time)) : '',
                'date_label'    => _CHNGSTORY,
                'reads_text'    => ($conf['help']['read']) ? $counter : '',
                'reads_label'   => _READS,
                'hits'          => '',
                'comm_href'     => $thref.'#'.$id,
                'comm_text'     => $comm,
                'comm_label'    => _MESSAGES,
                'rating'        => '',
                'favorites'     => getFavoriteButton($id, $conf['name']),
                'voting'        => '',
                'is_moder'      => false,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        $cont .= getTplPager([
            'limit'     => $unum,
            'maxpg'     => $conf['help']['nump'],
            'table'     => '_help',
            'field'     => 'id',
            'mod'       => $conf['name'],
            'where'     => $onum,
            'url_extra' => $url_extra,
        ]);
    } else {
        if ((int)$num > 1) setError(404);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $user, $tpl;
    $cwhere  = catmids($conf['name'], 's.cid');
    $uid     = (int)($user[0] ?? 0);
    $listnum = (int)$conf['help']['listnum'];
    if ($listnum < 1) $listnum = 1;
    $let    = getVar('get', 'let', 'let');
    $params = ['uid' => $uid];
    if ($let) {
        $params['let'] = $let.'%';
        $order  = "WHERE UCASE(s.title) LIKE BINARY UCASE(:let) AND s.time <= NOW() AND s.pid = '0' AND s.uid = :uid";
        $onum   = "UCASE(title) LIKE BINARY UCASE('".addslashes($let)."%') AND time <= NOW() AND pid = '0' AND uid = ".$uid;
    } else {
        $order  = "WHERE s.time <= NOW() AND s.pid = '0' AND s.uid = :uid";
        $onum   = "time <= NOW() AND pid = '0' AND uid = ".$uid;
    }
    $url_extra = ['op' => 'liste'];
    if ($let) $url_extra['let'] = $let;
    $num    = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $listnum);
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.status, c.title, c.intro FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.$order.' '.$cwhere.' ORDER BY s.time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _LIST] + HELP_NAVI);
    $rows = [];
    while ([$id, $cid, $title, $time, $status, $ctitle, $cdesc] = $db->getSqlRow($result)) {
        $cdesc = $cdesc ?: $ctitle;
        $rows[] = [
            'id'            => (string)$id,
            'title_href'    => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]),
            'title_attr'    => $title,
            'title_text'    => $title,
            'category_href' => $ctitle ? getSeoUrl(['name' => $conf['name'], 'cat' => $cid]) : '',
            'category_attr' => $cdesc,
            'category_text' => ($ctitle) ? cutstr($ctitle, 15) : _NO,
            'post_text'     => ad_status('', $status ? 0 : 1),
            'report'        => getTplTitleTip([['label' => _DATE, 'value' => format_time($time, _TIMESTRING)]]),
        ];
    }
    if (!$rows) setError(404);
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows'        => $rows,
        'before_html' => ($conf['help']['letter'] && $rows) ? letter($conf['name']) : '',
        'table_open'  => [
            'open'       => true,
            'sortable'   => true,
            'col_id'     => _ID,
            'col_title'  => _TITLE,
            'col_cat'    => _CATEGORY,
            'col_poster' => _STATUS,
        ],
        'table_close' => [],
        'pager_html'  => $rows ? getTplPager([
            'limit'     => $listnum,
            'maxpg'     => $conf['help']['nump'],
            'table'     => '_help',
            'field'     => 'id',
            'mod'       => $conf['name'],
            'where'     => $onum,
            'url_extra' => $url_extra,
        ]) : '',
    ]);
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $user, $conf, $tpl, $prs;
    $id     = getVar('get', 'id', 'num');
    $word   = getVar('get', 'word', 'word');
    $uid    = (int)($user[0] ?? 0);
    $cwhere = catmids($conf['name'], 's.cid');
    $result = $db->getSqlQuery('SELECT s.id, s.pid, s.cid, s.uid, s.aid, s.title, s.time, s.body, s.field, s.counter, s.score, s.ratings, s.status, c.title, c.intro, c.img, c.ordern, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE (s.id = :id1 OR s.pid = :id2) AND s.uid = :uid AND s.time <= NOW() '.$cwhere.' ORDER BY s.time ASC', ['id1' => $id, 'id2' => $id, 'uid' => $uid]);
    if ($db->getSqlRowCount($result) > 0) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$seocid, $seotitle, $seohometext, $seotime, $seoctitle, $seoname] = $db->getSqlRow($db->getSqlQuery(
            'SELECT s.cid, s.title, s.body, s.time, c.title, u.name FROM '.PREFIX_DB.'_help AS s '.
            'LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.
            'LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) '.
            'WHERE s.id = :id AND s.uid = :uid '.$cwhere, ['id' => $id, 'uid' => $uid]
        ));
        $seodesc   = cutstr(trim(strip_tags($prs->filterContent($seohometext, false, $conf['name']))), 160);
        $seoimg    = getImgText($seohometext, '', false);
        $seoimg    = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        $seoauthor = $seoname ?: $conf['sitename'];
        setHead([
            'title'  => $seotitle,
            'ctitle' => $seoctitle,
            'cid' => $seocid,
            'desc'   => $seodesc,
            'img'    => $seoimg,
            'time'   => $seotime,
            'author' => $seoauthor,
        ]);
        $cont = getModuleNavi(['title' => _HELPINFO, 'is_heading' => false] + HELP_NAVI);
        $a = 0;
        while ([$hid, $pid, $cid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $status, $ctitle, $cdesc, $cimg, $cordern, $nick] = $db->getSqlRow($result)) {
            $chref  = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $title  = (string)$title;
            $title  = ($title !== '') ? $title : _MESSAGE.': '.$a;
            $ttext  = filterTextHighlight($title, $word);
            $fields = getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]);
            $text   = $hometext;
            $post   = ($nick) ? user_info($nick) : _ANONYM;
            $date   = ($conf['help']['date']) ? format_time($time) : '';
            $rating = ($haid && $huid != $haid) ? getRatingAsync(1, $hid, $conf['name'], $ratings, $score, '') : '';
            if (!$pid) {
                $reads    = $counter;
                $cdesc    = $cdesc ?: $ctitle;
                $cimg     = getCategoryIcon($cimg);
                $favorites = getFavoriteButton($hid, $conf['name']);
                $cont .= $tpl->getHtmlPart('view', [
                    'id'            => $hid,
                    'favorites'     => $favorites,
                    'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $hid, 'title' => $title, 'ctitle' => $ctitle]),
                    'share_title' => $title,
                    'title_text'    => $ttext,
                    'title_new'     => getTplNewGraphic($time),
                    'hits'          => '',
                    'reads_text'    => $reads,
                    'reads_label'   => _READS,
                    'post_text'     => $post,
                    'post_label'    => _POSTEDBY,
                    'date_text'     => $date,
                    'date_iso'      => ($date) ? date('c', strtotime($time)) : '',
                    'date_label'    => _CHNGSTORY,
                    'category_href' => $ctitle ? $chref : '',
                    'category_attr' => $cdesc,
                    'category_text' => ($ctitle) ? cutstr($ctitle, 15) : '',
                    'category_icon'  => $cimg,
                    'category_tone'  => $cordern % 6,
                    'text'          => filterTextHighlight($prs->filterContent($text, false, $conf['name'], 1), $word),
                    'fields'        => $fields,
                    'voting'        => '',
                    'rating'        => $rating,
                    'back_title'    => _BACK,
                    'back_text'     => _BACK,
                    'is_moder'      => false,
                ]);
            } else {
                $cont .= $tpl->getHtmlFrag('card', [
                    'id'            => $hid,
                    'width'         => 100,
                    'title_href'    => '#'.$hid,
                    'title_attr'    => strip_tags($title),
                    'title_text'    => $ttext,
                    'title_new'     => getTplNewGraphic($time),
                    'category_href' => '',
                    'category_attr' => '',
                    'category_text' => '',
                    'category_icon'  => '',
                    'text'          => filterTextHighlight($prs->filterContent($text, false, $conf['name'], 1), $word),
                    'read_href'     => '',
                    'read_text'     => '',
                    'post_text'     => $post,
                    'post_label'    => _POSTEDBY,
                    'date_text'     => $date,
                    'date_iso'      => ($date) ? date('c', strtotime($time)) : '',
                    'date_label'    => _CHNGSTORY,
                    'reads_text'    => '',
                    'reads_label'   => _READS,
                    'hits'          => '',
                    'comm_href'     => '',
                    'comm_text'     => '',
                    'comm_label'    => _COMMENTS,
                    'rating'        => $rating,
                    'favorites'     => getFavoriteButton($hid, $conf['name']),
                    'voting'        => '',
                    'is_moder'      => false,
                ]);
            }
            $a++;
        }
        $cont .= addview($id);
        echo $cont;
        setFoot();
    } else {
        setError(404);
    }
}

function addview(int $id): string {
    global $db, $conf, $tpl;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $result = $db->getSqlQuery('SELECT cid, status FROM '.PREFIX_DB.'_help WHERE id = :id', ['id' => $id]);
        [$cid, $status] = $db->getSqlRow($result);
        $rows = $tpl->getHtmlFrag('form-field-row', ['label' => _HELPGLOS, 'field_html' => getTplRadioGroup([
            'name' => 'status',
            'value' => ((string)$status === '0') ? '0' : '1',
            'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ])]);
        $hide = $tpl->getHtmlFrag('hidden', ['name_attr' => 'pid', 'value_attr' => (string)$id, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'catid', 'value_attr' => (string)$cid, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'posttype', 'value_attr' => 'save', 'input_attr' => '']);
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('help')])
            .$rows
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _TEXT,
                'hide_label' => true,
                'field_html' => getTplTextarea([
                    'id' => '1',
                    'name' => 'hometext',
                    'value' => '',
                    'mod' => $conf['name'],
                    'store' => 'help.body',
                    'rows' => 10,
                    'placeholder' => _TEXT,
                    'required' => '1',
                ]),
            ]);
        return $tpl->getHtmlPart('form-add', [
            'name'        => $conf['name'],
            'fields'      => $fields,
            'submit'      => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => $hide, 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _SEND]),
        ]);
    }
    return '';
}

function add(): void {
    global $conf, $stop, $tpl;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field    = getVar('post', 'field', 'field');
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD] + HELP_NAVI);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        if ($hometext) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $hometext, 'textb' => '', 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HSUBMIT]);
        $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('help')]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _TITLE,
            'hide_label' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'value_attr' => $title,
                'maxlength_num' => 100,
                'placeholder_text' => _TITLE,
                'is_required' => true,
            ]),
        ]);
        $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _CATEGORY, 'field_html' => getTplCategorySelect($conf['name'], $cid, 'catid', '', $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => false]))]);
        $fields .= $tpl->getHtmlFrag('form-field-row', [
            'label' => _TEXT,
            'hide_label' => true,
            'field_html' => getTplTextarea([
                'id' => '1',
                'name' => 'hometext',
                'value' => $hometext,
                'mod' => $conf['name'],
                'store' => 'help.body',
                'rows' => '10',
                'placeholder' => _TEXT,
                'required' => '1',
            ]),
        ]);
        $fields .= getTplFieldsIn(['field' => $field, 'mod' => $conf['name']]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'name'       => $conf['name'],
            'fields'     => $fields,
            'submit'     => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $user, $conf, $stop, $tpl;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $title    = getVar('post', 'title', 'title');
        $cid      = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field    = getVar('post', 'field', 'field');
        $pid      = getVar('post', 'pid', 'num');
        $status   = ($pid) ? getVar('post', 'status', 'num') : '0';
        $stop     = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'help')) $stop[] = _ERROR;
        if (!$title && !$pid) $stop[] = _CERROR;
        if (!$hometext && !$pid) $stop[] = _CERROR1;
        if ($room = checkEditorTextRoom($hometext, 'help.body')) $stop[] = $room;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = (int)$user[0];
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_help (id, pid, cid, uid, aid, title, time, body, field, ip, status) VALUES (NULL, :pid, :cid, :uid, :aid, :title, NOW(), :body, :field, :ip, \'0\')',
                ['pid' => $pid, 'cid' => $cid, 'uid' => $postid, 'aid' => $postid, 'title' => $title, 'body' => $hometext, 'field' => $field, 'ip' => getIp()]
            );
            if ($pid) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
            $puname = (is_user()) ? $user[1] : '';
            addAdminMail($conf['help']['addmail'], $conf['name'], $puname, _HELP);
            setHead(['title' => _ADD]);
            $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _ADD] + HELP_NAVI).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HSUBTEXT, 'meta' => $meta]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: help(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
