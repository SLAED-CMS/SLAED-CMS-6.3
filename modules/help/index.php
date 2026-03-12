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
    $home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._HELP.'" class="sl_but_navi">'._HOME.'</a>';
    $closed = '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'closed']).'" title="'._CLOSED.'" class="sl_but_navi">'._CLOSED.'</a>';
    $pop = '<a href="'.getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>';
    $liste = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'liste']).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';
    $add = ($conf['help']['add'] == 1) ? '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';
    $catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';
    return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $closed, '{%pop%}' => $pop, '{%liste%}' => $liste, '{%add%}' => $add, '{%catshow%}' => $catshow]);
}

function help(): void {
    global $db, $user, $conf, $home, $op;
    $cwhere = catmids($conf['name'], 's.cid');
    $uid = is_user() ? intval($user[0]) : 0;
    $unum = getUserNews($conf['help']['num']);
    $cat = getVar('get', 'cat', 'num');
    $ncat = $cat;
    $params = ['uid' => $uid];
    if (!$ncat && $op) {
        $caton = 0;
        $field = 'op='.$op.'&';
        if ($op == 'closed') {
            $order = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
            $ntitle = _CLOSED;
        } else {
            $order = "WHERE s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.counter DESC';
            $onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _POP;
        }
    } elseif ($ncat) {
        $field = ($op) ? 'cat='.$ncat.'&op='.$op.'&' : 'cat='.$ncat.'&';
        [$ctitle] = $db->getSqlRow($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_categories WHERE id = :ncat', ['ncat' => $ncat]));
        $cids = [];
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE parent = :ncat', ['ncat' => $ncat]);
        while ([$caid] = $db->getSqlRow($result)) $cids[] = $caid;
        unset($result);
        $params = ['uid' => $uid, 'ncat1' => $ncat, 'ncat2' => $ncat];
        if (isArray($cids)) {
            $caton = 1;
            array_unshift($cids, $ncat);
            $wcid = 'cid IN ('.implode(', ', $cids).')';
        } else {
            $caton = 0;
            $wcid = "cid = '".$ncat."'";
        }
        if ($op == 'closed') {
            $order = "WHERE s.status != '0' AND s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW() AND status != '0'";
            $ntitle = _CLOSED;
        } elseif ($op == 'pop') {
            $order = "WHERE s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.counter DESC';
            $onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _POP;
        } else {
            $order = "WHERE s.pid = '0' AND s.uid = :uid AND (s.cid = :ncat1 OR c.parent = :ncat2) AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
            $onum = $wcid." AND pid = '0' AND uid = '".$uid."' AND time <= NOW()";
            $ntitle = _HELP;
        }
        $ntitle = $ctitle.' '.$conf['defis'].' '.$ntitle;
    } else {
        $caton = 1;
        $field = '';
        $order = "WHERE s.pid = '0' AND s.uid = :uid AND s.time <= NOW() ".$cwhere.' ORDER BY s.time DESC';
        $onum = "pid = '0' AND uid = '".$uid."' AND time <= NOW()";
        $ntitle = _HELPINFO;
    }
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home) {
        $cont .= navigate($ntitle, $caton);
        if ($ncat) $cont .= setTemplateBasic('cat-navi', ['{%crumbs%}' => catlink($conf['name'], $ncat, $conf['help']['defis'], _HELP)]);
        if ($caton == 1) $cont .= setCategories($conf['name'], $conf['help']['subcat'], $conf['help']['catdesc'], $ncat);
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.body, s.comments, s.counter, c.title, c.intro, c.img FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.$order.' LIMIT '.$offset.', '.$unum, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $cid, $stitle, $time, $hometext, $comm, $counter, $ctitle, $cdesc, $cimg] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
            $title = '<a href="'.$thref.'" title="'.$stitle.'">'.$stitle.'</a> '.new_graphic($time);
            $read = '<a href="'.$thref.'" title="'.$stitle.'" class="sl_but_read">'._READMORE.'</a>';
            $date = ($conf['help']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
            $reads = ($conf['help']['read']) ? '<span title="'._READS.'" class="sl_views">'.$counter.'</span>' : '';
            $comm = '<a href="'.$thref.'#'.$id.'" title="'._MESSAGES.'" class="sl_coms">'.$comm.'</a>';
            $cont .= setTemplateBasic('basic', ['{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']), '{%read%}' => $read, '{%post%}' => '', '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => $comm, '{%rating%}' => '', '{%admin%}' => '', '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_help', 'cid', $onum, $conf['help']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $user;
    $cwhere = catmids($conf['name'], 's.cid');
    $uid = intval($user[0]);
    $listnum = intval($conf['help']['listnum']);
    $let = getVar('get', 'let', 'let');
    $params = ['uid' => $uid];
    if ($let) {
        $field = 'op=liste&let='.urlencode($let).'&';
        $order = "WHERE UCASE(s.title) LIKE BINARY :let AND s.time <= NOW() AND s.pid = '0' AND s.uid = :uid";
        $params['let'] = $let.'%';
    } else {
        $field = 'op=liste&';
        $order = "WHERE s.time <= NOW() AND s.pid = '0' AND s.uid = :uid";
    }
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $listnum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.status, c.title, c.intro FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.$order.' '.$cwhere.' ORDER BY s.time DESC LIMIT '.$offset.', '.$listnum, $params);
    setHead(['title' => _LIST]);
    $cont = navigate(_LIST);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['help']['letter']) ? letter($conf['name']) : '';
        $cont .= setTemplateBasic('liste-open', ['{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _STATUS, '{%date%}' => _DATE]);
        while ([$id, $cid, $title, $time, $status, $ctitle, $cdesc] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $title = '<a href="'.$thref.'" title="'.$title.'">'.cutstr($title, 40).'</a> '.new_graphic($time);
            $cdesc = ($cdesc) ? $cdesc : $ctitle;
            $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'">'.cutstr($ctitle, 15).'</a>' : _NO;
            $status = ($status) ? 0 : 1;
            $cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title%}' => $title, '{%ctitle%}' => $ctitle, '{%post%}' => ad_status('', $status), '{%time%}' => format_time($time)]);
        }
        $cont .= setTemplateBasic('liste-close');
        $onum = ($let) ? "title LIKE BINARY '".$let."%' AND time <= NOW() AND pid = '0' AND uid = '".$uid."'" : "time <= NOW() AND pid = '0' AND uid = '".$uid."'";
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_help', 'cid', $onum, $conf['help']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $user, $conf;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $uid = intval($user[0]);
    $cwhere = catmids($conf['name'], 's.cid');
    $result = $db->getSqlQuery('SELECT s.id, s.pid, s.cid, s.uid, s.aid, s.title, s.time, s.body, s.field, s.counter, s.score, s.ratings, s.status, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE (s.id = :id1 OR s.pid = :id2) AND s.uid = :uid AND s.time <= NOW() '.$cwhere.' ORDER BY s.time ASC', ['id1' => $id, 'id2' => $id, 'uid' => $uid]);
    if ($db->getSqlRowCount($result) > 0) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$seotitle, $seohometext, $seotime, $seoctitle, $seoname] = $db->getSqlRow($db->getSqlQuery(
            'SELECT s.title, s.body, s.time, c.title, u.name FROM '.PREFIX_DB.'_help AS s '.
            'LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) '.
            'LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) '.
            'WHERE s.id = :id AND s.uid = :uid '.$cwhere, ['id' => $id, 'uid' => $uid]
        ));
        $seodesc   = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($seohometext, $conf['name'], false), $conf['name']))), 160);
        $seoimg    = getImgText($seohometext, '', false);
        $seoimg    = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        $seoauthor = $seoname ?: $conf['sitename'];
        setHead([
            'title' => $seotitle,
            'ctitle' => $seoctitle,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $seotime,
            'author' => $seoauthor,
        ]);
        $cont = navigate(_HELPINFO);
        $a = 0;
        while ([$hid, $pid, $cid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $status, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $title = ($title) ? filterTextHighlight($title, $word) : _MESSAGE.': '.$a;
            $fields = fields_out($field, $conf['name']);
            $fields = ($fields) ? '<br><br>'.$fields : '';
            $text = $hometext.$fields;
            $post = ($nick) ? user_info($nick) : _ANONYM;
            $post = '<span title="'._POSTEDBY.'" class="sl_post">'.$post.'</span>';
            $date = ($conf['help']['date']) ? '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>' : '';
            $rating = ($haid && $huid != $haid) ? ajax_rating(1, $hid, $conf['name'], $ratings, $score, '') : '';
            if (!$pid) {
                $reads = '<span title="'._READS.'" class="sl_views">'.$counter.'</span>';
                $cdesc = ($cdesc) ? $cdesc : $ctitle;
                $ctitle = ($ctitle) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_cat">'.cutstr($ctitle, 15).'</a>' : '';
                $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
                $cimg = ($cimg) ? '<a href="'.$chref.'" title="'.$cdesc.'" class="sl_icat"><img src="'.$cimg.'" alt="'.$cdesc.'" title="'.$cdesc.'"></a>' : '';
                $favorites = getFavorBtn($hid, $conf['name']);
                $goback = '<span OnClick="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_back">'._BACK.'</span>';
            } else {
                $reads = $ctitle = $cimg = $favorites = $goback = '';
            }
            $cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => !$pid], '{%cid%}' => $cid, '{%cimg%}' => $cimg, '{%ctitle%}' => $ctitle, '{%id%}' => $hid, '{%title%}' => filterTextHighlight($title, $word), '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word), '{%read%}' => '', '{%post%}' => $post, '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => '', '{%comm%}' => '', '{%rating%}' => $rating, '{%admin%}' => '', '{%favorites%}' => $favorites, '{%goback%}' => $goback, '{%voting%}' => '']);
            $a++;
        }
        $cont .= addview($id);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function addview(int $id): string {
    global $db, $conf;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $result = $db->getSqlQuery('SELECT cid, status FROM '.PREFIX_DB.'_help WHERE id = :id', ['id' => $id]);
        [$cid, $status] = $db->getSqlRow($result);
        $cont = setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
        .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1').'</td></tr>'
        .'<tr><td>'._HELPGLOS.'</td><td>'.radio_form($status, 'status').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="catid" value="'.$cid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
        return $cont;
    }
    return '';
}

function add(): void {
    global $conf, $stop;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field = getVar('post', 'field', 'field');
        setHead(['title' => _ADD]);
        $cont = navigate(_ADD);
        if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
        if ($hometext) $cont .= preview($title, $hometext, '', $field, $conf['name']);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _HSUBMIT]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
        .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._TITLE.'" required></td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($conf['name'], $cid, 'catid', $conf['style'], '<option value="">'._HOMECAT.'</option>').'</td></tr>'
        .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '10', _TEXT, '1').'</td></tr>'
        .fields_in($field, $conf['name'])
        .'<tr><td colspan="2" class="sl_center">'.ad_save('', '', 'send').'</td></tr></table></form>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function send(): void {
    global $db, $user, $conf, $stop;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field = getVar('post', 'field', 'field');
        $pid = getVar('post', 'pid', 'num');
        $status = ($pid) ? getVar('post', 'status', 'num') : '0';
        $stop = [];
        if (!$title && !$pid) $stop[] = _CERROR;
        if (!$hometext && !$pid) $stop[] = _CERROR1;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = intval($user[0]);
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_help (id, pid, cid, uid, aid, title, time, body, field, ip, status) VALUES (NULL, :pid, :cid, :postid, :postid, :title, NOW(), :body, :field, :ip, \'0\')', ['pid' => $pid, 'cid' => $cid, 'postid' => $postid, 'title' => $title, 'body' => $hometext, 'field' => $field, 'ip' => getIp()]);
            if ($pid) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
            $puname = (is_user()) ? $user[1] : '';
            addAdminMail($conf['help']['addmail'], $conf['name'], $puname, _HELP);
            setHead(['title' => _ADD]);
            echo navigate(_ADD).setTemplateWarning('warn', ['time' => '10', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _HSUBTEXT]);
            setFoot();
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: help(); break;
    case 'liste': liste(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
