<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const HELP_NAVI = ['htitle' => _HELP, 'bop' => 'closed', 'btitle' => _CLOSED, 'always' => true, 'addquest' => false];

function help(): void {
    global $db, $user, $conf, $home, $op, $tpl;
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
        $cont .= setModuleNavi(['title' => $ntitle] + HELP_NAVI);
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
            $cdesc = $cdesc ?: $ctitle;
            $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
            $date = ($conf['help']['date']) ? format_time($time) : '';
            $cont .= setTemplateBasic('basic', ['{%id%}' => $id, '{%title_href%}' => $thref, '{%title_attr%}' => $stitle, '{%title_text%}' => $stitle, '{%title_new%}' => new_graphic($time), '{%category_href%}' => $ctitle ? $chref : '', '{%category_attr%}' => $cdesc, '{%category_text%}' => ($ctitle) ? cutstr($ctitle, 15) : '', '{%category_img%}' => $cimg, '{%text%}' => filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']), '{%read_href%}' => $thref, '{%read_text%}' => _READMORE, '{%post_text%}' => '', '{%post_label%}' => '', '{%date_text%}' => $date, '{%date_iso%}' => ($date) ? date('c', strtotime($time)) : '', '{%date_label%}' => _CHNGSTORY, '{%reads_text%}' => ($conf['help']['read']) ? $counter : '', '{%reads_label%}' => _READS, '{%hits%}' => '', '{%comm_href%}' => $thref.'#'.$id, '{%comm_text%}' => $comm, '{%comm_label%}' => _MESSAGES, '{%rating%}' => '', '{%favorites%}' => '', '{%voting%}' => '', '{%editor%}' => _EDITOR, '{%edit_href%}' => '', '{%edit_text%}' => '', '{%delete_href%}' => '', '{%delete_text%}' => '', '{%delete_ask%}' => '', '{%back_title%}' => '', '{%back_text%}' => '']);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_help', 'cid', $onum, $conf['help']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function liste(): void {
    global $db, $conf, $user, $tpl;
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
    $cont = setModuleNavi(['title' => _LIST] + HELP_NAVI);
    if ($db->getSqlRowCount($result) > 0) {
        $letter = ($conf['help']['letter']) ? letter($conf['name']) : '';
        $cont .= setTemplateBasic('liste-wrap', ['if_flag' => ['open' => true], '{%letter%}' => $letter, '{%id%}' => _ID, '{%title%}' => _TITLE, '{%category%}' => _CATEGORY, '{%poster%}' => _STATUS, '{%date%}' => _DATE]);
        while ([$id, $cid, $title, $time, $status, $ctitle, $cdesc] = $db->getSqlRow($result)) {
            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $cdesc = $cdesc ?: $ctitle;
            $status = ($status) ? 0 : 1;
            $cont .= setTemplateBasic('liste-basic', ['{%id%}' => $id, '{%title_href%}' => $thref, '{%title_attr%}' => $title, '{%title_text%}' => cutstr($title, 40), '{%title_new%}' => new_graphic($time), '{%category_href%}' => $ctitle ? $chref : '', '{%category_attr%}' => $cdesc, '{%category_text%}' => ($ctitle) ? cutstr($ctitle, 15) : _NO, '{%post_text%}' => ad_status('', $status), '{%time_text%}' => format_time($time), '{%time_iso%}' => date('c', strtotime($time)), '{%time_label%}' => _DATE]);
        }
        $cont .= setTemplateBasic('liste-wrap', []);
        $onum = ($let) ? "title LIKE BINARY :let AND time <= NOW() AND pid = '0' AND uid = :uid" : "time <= NOW() AND pid = '0' AND uid = :uid";
        $params = ($let) ? ['let' => $let.'%', 'uid' => $uid] : ['uid' => $uid];
        $cont .= setArticleNumbers('pagenum', $conf['name'], $listnum, $field, 'id', '_help', 'cid', $onum, $conf['help']['nump'], $params);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
        $cont = setModuleNavi(['title' => _HELPINFO] + HELP_NAVI);
        $a = 0;
        while ([$hid, $pid, $cid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $status, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($result)) {
            $chref = getSeoUrl(['name' => $conf['name'], 'cat' => $cid]);
            $title = ($title) ? filterTextHighlight($title, $word) : _MESSAGE.': '.$a;
            $fields = fields_out($field, $conf['name']);
            $fields = ($fields) ? '<br><br>'.$fields : '';
            $text = $hometext.$fields;
            $post = ($nick) ? user_info($nick) : _ANONYM;
            $date = ($conf['help']['date']) ? format_time($time) : '';
            $rating = ($haid && $huid != $haid) ? ajax_rating(1, $hid, $conf['name'], $ratings, $score, '') : '';
            if (!$pid) {
                $reads = $counter;
                $cdesc = $cdesc ?: $ctitle;
                $cimg = ($cimg) ? img_find('categories/'.$cimg) : '';
                $favorites = getFavorBtn($hid, $conf['name']);
            } else {
                $reads = $ctitle = $cimg = $favorites = '';
                $cdesc = '';
            }
            $cont .= setTemplateBasic('basic', ['if_flag' => ['is_view' => !$pid, 'has_back' => !$pid], '{%id%}' => $hid, '{%title_href%}' => '#'.$hid, '{%title_attr%}' => strip_tags((string)$title), '{%title_text%}' => filterTextHighlight($title, $word), '{%title_new%}' => '', '{%category_href%}' => (!$pid && $ctitle) ? $chref : '', '{%category_attr%}' => $cdesc, '{%category_text%}' => (!$pid && $ctitle) ? cutstr($ctitle, 15) : '', '{%category_img%}' => $cimg, '{%text%}' => filterTextHighlight(filterReplaceText(filterMarkdown($text, $conf['name'], false), $conf['name']), $word), '{%read_href%}' => '', '{%read_text%}' => '', '{%post_text%}' => $post, '{%post_label%}' => _POSTEDBY, '{%date_text%}' => $date, '{%date_iso%}' => ($date) ? date('c', strtotime($time)) : '', '{%date_label%}' => _CHNGSTORY, '{%reads_text%}' => $reads, '{%reads_label%}' => _READS, '{%hits%}' => '', '{%comm_href%}' => '', '{%comm_text%}' => '', '{%comm_label%}' => _COMMENTS, '{%rating%}' => $rating, '{%favorites%}' => $favorites, '{%voting%}' => '', '{%editor%}' => _EDITOR, '{%edit_href%}' => '', '{%edit_text%}' => '', '{%delete_href%}' => '', '{%delete_text%}' => '', '{%delete_ask%}' => '', '{%back_title%}' => _BACK, '{%back_text%}' => _BACK]);
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
        $cont = '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">'
        .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1').'</td></tr>'
        .'<tr><td>'._HELPGLOS.'</td><td>'.radio_form($status, 'status').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('help'), ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="catid" value="'.$cid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr></table></form>';
        return $cont;
    }
    return '';
}

function add(): void {
    global $conf, $stop, $tpl;
    if ((is_user() && $conf['help']['add'] == 1)) {
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field = getVar('post', 'field', 'field');
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD] + HELP_NAVI);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
        if ($hometext) $cont .= preview($title, $hometext, '', $field, $conf['name']);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HSUBMIT]);
        $cont .= setTemplateBasic('form-add', [
            '{%name%}'      => $conf['name'],
            '{%token%}'     => htmlspecialchars(getSiteToken('help'), ENT_QUOTES, 'UTF-8'),
            '{%style%}'     => $conf['style'],
            '{%lbl_title%}' => _TITLE,
            '{%lbl_cat%}'   => _CATEGORY,
            '{%lbl_text%}'  => _TEXT,
            '{%titleval%}'  => $title,
            '{%catselect%}' => getcat($conf['name'], $cid, 'catid', $conf['style'],
                '<option value="">'._HOMECAT.'</option>'),
            '{%hometext%}'  => textarea('1', 'hometext', $hometext, $conf['name'], '10', _TEXT, '1'),
            '{%fields%}'    => fields_in($field, $conf['name']),
            '{%submit%}'    => ad_save('', '', 'send'),
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
        $title = getVar('post', 'title', 'title');
        $cid = getVar('post', 'catid', 'num');
        $hometext = getVar('post', 'hometext', 'text');
        $field = getVar('post', 'field', 'field');
        $pid = getVar('post', 'pid', 'num');
        $status = ($pid) ? getVar('post', 'status', 'num') : '0';
        $stop = [];
        if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'help')) $stop[] = _ERROR;
        if (!$title && !$pid) $stop[] = _CERROR;
        if (!$hometext && !$pid) $stop[] = _CERROR1;
        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $postid = intval($user[0]);
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_help (id, pid, cid, uid, aid, title, time, body, field, ip, status) VALUES (NULL, :pid, :cid, :uid, :aid, :title, NOW(), :body, :field, :ip, \'0\')', ['pid' => $pid, 'cid' => $cid, 'uid' => $postid, 'aid' => $postid, 'title' => $title, 'body' => $hometext, 'field' => $field, 'ip' => getIp()]);
            if ($pid) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
            $puname = (is_user()) ? $user[1] : '';
            addAdminMail($conf['help']['addmail'], $conf['name'], $puname, _HELP);
            setHead(['title' => _ADD]);
            $meta = '<meta http-equiv="refresh" content="10; url=index.php?name='.$conf['name'].'">';
            echo setModuleNavi(['title' => _ADD] + HELP_NAVI).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _HSUBTEXT, 'meta' => $meta]);
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
